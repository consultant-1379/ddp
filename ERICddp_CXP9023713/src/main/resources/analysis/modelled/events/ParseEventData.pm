package ParseEventData;

use strict;
use warnings;

use Getopt::Long;
use Data::Dumper;
use File::Basename;

use Module::Load;
use File::Basename;

use StatsDB;
use StatsCommon;

use lib dirname($0);
use EventDataFile;
use ModelFile;
use ModelledEvents;


our $ALL_SG = 'allsg';
our %hookModules = ();

# Create hash servicegroup -> host -> serverid
sub getSgInstances($$$) {
    my ($dbh, $site, $date) = @_;
    my %sgInstances = ();

    my $r_rows = dbSelectAllHash($dbh,"
SELECT
 enm_servicegroup_names.name AS sg,
 servers.hostname AS host,
 servers.id AS srvid
FROM enm_servicegroup_instances, enm_servicegroup_names, servers, sites
WHERE
 enm_servicegroup_instances.siteid = sites.id AND sites.name = '$site' AND
 enm_servicegroup_instances.date = '$date' AND
 enm_servicegroup_instances.serverid = servers.id AND
 enm_servicegroup_instances.serviceid = enm_servicegroup_names.id
ORDER BY enm_servicegroup_names.name, servers.hostname
");
    foreach my $r_row ( @{$r_rows} ) {
        my $r_instances = $sgInstances{$r_row->{'sg'}};
        if ( ! defined $r_instances ) {
            $r_instances = {};
            $sgInstances{$r_row->{'sg'}} = $r_instances;
        }
        $r_instances->{$r_row->{'host'}} = $r_row->{'srvid'};
    }
    if ( $::DEBUG > 6 ) { print Dumper("getSgInstances: sgInstances", \%sgInstances); }

    return \%sgInstances;
}

sub addEventSet($$$$) {
    my ($r_eventMap, $instance, $eventName, $r_events) = @_;

    my $r_list = $r_eventMap->{$instance}->{$eventName};
    if ( ! defined $r_list ) {
        $r_list = [];
        $r_eventMap->{$instance}->{$eventName} = $r_list;
    }
    push @{$r_list}, $r_events;
}

sub buildEventMap($$$$) {
    my ($r_sgInstances,$r_models,$r_eventMap,$r_modeledEvents) = @_;

    # Build r_eventMap
    #  hostname->eventMap->[]
    # Each array element is an array containing the all the events
    # single model wants.
    # We use an array to support the case where there are multiple models feeding off
    # the same event
    foreach my $r_model ( @{$r_models} ) {
        my @events = ();
        my %srvIds = ();
        push @{$r_modeledEvents}, { 'model' => $r_model, 'events' => \@events, 'srvids' => \%srvIds };

        while ( my ($eventName,$r_eventDef) = each %{$r_model->{'events'}} ) {
            if ( exists $r_model->{'services'} ) {
                if ( $::DEBUG > 5 ) { print "buildEventMap: checking services for " . $r_model->{'table'}->{'name'} . "\n"; }
                foreach my $service ( keys %{$r_model->{'services'}} ) {
                    if ( $::DEBUG > 5 ) { print "buildEventMap: checking $service\n"; }
                    my $r_instances = $r_sgInstances->{$service};
                    if ( defined $r_instances ) {
                        while ( my ($hostname,$serverid) = each %{$r_instances} ) {
                            if ( $::DEBUG > 5 ) { print "buildEventMap: adding $hostname\n"; }
                            addEventSet($r_eventMap, $hostname, $eventName, \@events);
                            $srvIds{$hostname} = $serverid;
                        }
                    }
                }
            } else {
                addEventSet($r_eventMap, $ALL_SG, $eventName, \@events);
            }
        }
    }

    if ( $::DEBUG > 4 ) { print Dumper("buildEventMap: r_eventMap", $r_eventMap); }
}



sub main() {
    my ($inDir, $date, $site, $modelDir, $incrFile);

    my $result = GetOptions(
        "indir=s" => \$inDir,
        "date=s" => \$date,
        "site=s" => \$site,
        "model=s"   => \$modelDir,
        "incr=s" => \$incrFile,
        "debug=s" => \$::DEBUG
    );
    ($result == 1) or die "Invalid args";
    setStatsDB_Debug($::DEBUG);

    # Load the list of know models
    my $r_models = [];
    my $dirname = dirname(__FILE__);
    my $xsd_doc = XML::LibXML::Schema->new(location => $dirname . "/models/modelledevents.xsd");

    # Normally modelDir is a direcory but we can pass it a file
    # to use only one model file (for debugging)
    if ( -d $modelDir ) {
        ModelFile::getModels($modelDir,$r_models, $xsd_doc);
    } else {
        push @{$r_models}, ModelFile::processModelFile($modelDir,$xsd_doc);
    }

    if ( $::DEBUG > 7 ) { print Dumper("main: models", $r_models); }

    my $dbh = connect_db();

    # eventMap host->eventname->[]
    my %eventMap = ();
    my @modeledEvents = ();
    my $r_sgInstances = getSgInstances($dbh, $site, $date);
    buildEventMap($r_sgInstances, $r_models, \%eventMap, \@modeledEvents);

    my $r_Incr = incrRead($incrFile);

    my $r_fileList = EventDataFile::getFiles($inDir,$r_Incr);
    if ( $#{$r_fileList} < 0 ) {
        print "WARNING: No files found in $inDir\n";
        exit 0;
    }

    my $r_lastEntry = $r_Incr->{'lastEntry'};

    my $r_mappedSrv = undef;
    my $siteId = getSiteId($dbh, $site);
    my $r_rows = dbSelectAllHash($dbh, "
SELECT
 servers.hostname AS srvname, k8s_pod.pod AS podname
FROM k8s_pod, servers
WHERE
 k8s_pod.siteid = $siteId AND
 k8s_pod.date = '$date' AND
 k8s_pod.serverid = servers.id");
    if ( $#{$r_rows} > -1 ) {
        $r_mappedSrv = {};
        foreach my $r_row ( @{$r_rows} ) {
            $r_mappedSrv->{$r_row->{'podname'}} = $r_row->{'srvname'};
        }
    }

    foreach my $filePath ( @{$r_fileList} ) {
        print "Processing " . basename($filePath) . "\n";
        $r_lastEntry = EventDataFile::processEventFile($filePath, \%eventMap, $r_lastEntry, $r_mappedSrv);
    }

    if ( $::DEBUG > 9 ) { print Dumper("main: modeledEvents", \@modeledEvents); }

    # Create hash hostname -> servicegroup
    my %hostToSg = ();
    while ( my ($servicegroup, $r_instances) = each %{$r_sgInstances} ) {
        foreach my $host ( keys %{$r_instances} ) {
            $hostToSg{$host} = $servicegroup;
        }
    }

    foreach my $r_modelAndEvents ( @modeledEvents ) {
        my $eventCount = $#{$r_modelAndEvents->{'events'}};
        my $tableName = $r_modelAndEvents->{'model'}->{'table'}->{'name'};
        if ( $::DEBUG > 3 ) {
            print "main: model table=$tableName #events=$eventCount\n";
        }
        if ( $eventCount > 1000) {
            print "table=$tableName #events=$eventCount\n";
        }
        if ( $eventCount > -1 ) {
            ModelledEvents::processModelEvents(
                $site,
                $r_modelAndEvents->{'model'},
                $r_modelAndEvents->{'srvids'},
                $r_modelAndEvents->{'events'},
                \%hostToSg,
                $r_Incr,
                $date
            );
        }
    }

    $r_Incr->{'lastEntry'} = $r_lastEntry;

    incrWrite($incrFile, $r_Incr);
}

1;