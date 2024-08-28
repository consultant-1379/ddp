package ParseModeledInstr;

use strict;
use warnings;

use Getopt::Long;
use Data::Dumper;
use DBI;
use Storable qw(dclone);
use Module::Load;
use File::Basename;
use lib dirname($0) . "/modules";

use StatsDB;
use StatsCommon;
use Instr;

use lib dirname($0);
use ModelledInstr;

our $DEBUG = 0;
our %parseMetrics = ();

our $INCR_VERSION = 1;

#
# Return a hash
#  key is a string containing the profileName@FileName where FileName has '.xml' removed
#  value is a hash containing
#    'provider_metrics' => hash with key metric group name, value array of metrics
#    'period' => sample period
#
sub getCfg($) {
    my ($instrCfgDir) = @_;

    #
    # Support a single file or directory being passed in
    #
    my @cfgFiles = ();
    if ( -d $instrCfgDir ) {
        opendir(DIR, $instrCfgDir) or die "Cannot open $instrCfgDir";
        foreach my $file (readdir(DIR)) {
            if ( $file =~ /\.xml$/ ) {
                push @cfgFiles, $instrCfgDir . "/" . $file;
            }
        }
        closedir(DIR);
    } elsif ( -f $instrCfgDir ) {
        push @cfgFiles, $instrCfgDir;
    } else {
        die "$instrCfgDir not found";
    }

    if ( $DEBUG > 3 ) { print Dumper("getCfg: cfgFiles", \@cfgFiles); }

    my %profiles = ();
    foreach my $file ( @cfgFiles ) {
        my $r_thisCfg = parseConfig($file);
        my ($namespace) = basename($file) =~ /(.+)\.xml$/;
        if ( $DEBUG > 9 ) { print Dumper( "getCfgFiles $file r_thisCfg", $r_thisCfg ); }
        while ( my ($profileName,$r_instrProfile) = each %{$r_thisCfg} ) {
            my $r_profile = $profiles{$profileName . '@' . $namespace};
            if ( ! defined $r_profile ) {
                $r_profile = { 'metric_groups' => {} };
                $profiles{$profileName . '@' . $namespace} = $r_profile;
                my $period = $r_instrProfile->{'pollInterval'};
                if ( ! defined $period ) {
                    $period = 60;
                }
                $r_profile->{'period'} = $period;
            }
            my $r_metricGroups = $r_profile->{'metric_groups'};
            while ( my ($providerName,$r_provider) = each %{$r_instrProfile->{'providers'}} ) {
                $r_metricGroups->{$providerName} = $r_provider;
            }
        }
    }

    if ( $DEBUG > 7 ) { print Dumper("getCfg: profiles", \%profiles); }
    return \%profiles;
}



#
# We've found a profile matching the namespace of a model. This function checks
# if that profile contains providers required by this model
#
sub modelActive($$$$$$$$) {
    my ($profileNameSpace,$r_profile,
        $server,$r_services,$r_model,
        $r_metricGroupsToParse,$r_modelsWithProfiles,
        $r_hookModules ) = @_;

    my ($r_modelInstance, $r_instanceMgByName) = ModelledInstr::getModelInstance(
        $r_model, $r_services, $profileNameSpace, $r_profile->{'period'}
    );
    if ( ! defined $r_modelInstance ) {
        return;
    }

    my @params = $profileNameSpace =~ $r_model->{'namespace'};

    #
    # Now we interate through the metric groups in the profile, and look to see if any of
    # any have a match in the model (i.e is there any entry in instanceMgByName who's
    # key matches the metric group name)
    #
    while ( my ($cfgMgName,$r_cfgMg) = each %{$r_profile->{'metric_groups'}} ) {
        if ( $DEBUG > 5 ) { print "modelActive: metricGroupName $cfgMgName\n"; }
        while ( my ($modelMgName,$r_modelMG) = each %{$r_instanceMgByName} ) {
            if ( $DEBUG > 7 ) { print "modelActive:  checking modelMgName $modelMgName\n"; }

            if ( $cfgMgName =~ /$modelMgName/ ) {
                if ( $DEBUG > 5 ) { print "modelActive:   adding to metricGroupsToParse with cfgMgName $cfgMgName\n"; }

                # In some cases the provider name name is different in the cfg file and
                # the instr file (i.e. for jvmgc), so check if the model sets the provider name
                # and if so use it
                my $providerName = $cfgMgName;
                if ( exists $r_modelMG->{'providername'} ) {
                    $providerName = ModelledInstr::replaceParams($r_modelMG->{'providername'},\@params);
                    if ( $DEBUG > 5 ) { print "modelActive:    overriding providername with $providerName\n"; }
                }
                $r_metricGroupsToParse->{$providerName} = $r_cfgMg;

                $r_modelInstance->{'metricgroup'}->{$providerName} = $r_modelMG;
            };
        }
    }

    # If we have matching metrics groups, then add this modelInstance to the modelsWithProfiles
    if ( %{$r_modelInstance->{'metricgroup'}} ) {
        push @{$r_modelsWithProfiles}, $r_modelInstance;

        my $instanceKey = $server;
        if ( ! defined $instanceKey ) {
            $instanceKey = 'site';
        }
        my $r_instancesForServer = $r_model->{'instances'}->{$instanceKey};
        if ( ! defined $r_instancesForServer ) {
            $r_instancesForServer = [];
            $r_model->{'instances'}->{$instanceKey} = $r_instancesForServer;
        }
        push @{$r_instancesForServer}, $r_modelInstance;

        if ( exists $r_modelInstance->{'hooks'} ) {
            my $hookModule = $r_modelInstance->{'hooks'}->{'module'};
            if ( ! exists $r_hookModules->{$hookModule} ) {
                load $hookModule;
                $r_hookModules->{$hookModule} = $hookModule->new();
            }
            $r_modelInstance->{'hooks'}->{'instance'} = $r_hookModules->{$hookModule};
        }

        # Figure out if there can only be one provider (later this will tell us
        # if we need to run a group function)
        my $isSingleton = 0;
        if ( keys %{$r_model->{'metricgroup'}} == 1 ) {
            $isSingleton = 1;
        }
        $r_modelInstance->{'singleton'} = $isSingleton;

        if ( $DEBUG > 4 ) { print Dumper("modelActive: modelInstance", $r_modelInstance); }
    }
}

sub getMetricGroup($$) {
    my ($r_data, $metricsGroup) = @_;
    return $r_data->{$metricsGroup};
}

sub main() {
    my ($site, $server, $instrCfgDir, $instrData, @modelDirs, $incrFile, $date);
    my @services = ();
    my $result = GetOptions(
        "site=s"    => \$site,
        "server=s"  => \$server,
        "service=s" => \@services,
        "cfg=s"     => \$instrCfgDir,
        "data=s"    => \$instrData,
        "model=s"   => \@modelDirs,
        "incr=s"    => \$incrFile,
        "date=s"    => \$date,
        "debug=s"   => \$DEBUG
    );
    ($result == 1) or die "Invalid options";

    setStatsDB_Debug($DEBUG);
    setInstr_Debug($DEBUG);
    $Data::Dumper::Indent = 1;

    my $dbh = connect_db();

    my $siteId = getSiteId( $dbh, $site );
    ($siteId > -1 ) or die "Failed to get siteid for $site";

    defined $date or die "main: date not defined";

    my %cliArgs = (
        'site' => $site,
        'siteid' => $siteId,
        'date' => $date
    );

    if ( defined $server ) {
        my $serverId = getServerId( $dbh, $siteId, $server );
        $cliArgs{'server'} = $server;
        $cliArgs{'serverid'} =  $serverId;
    }

    # Get the list of instr profiles this server has
    my $r_profilesByNamespace = getCfg($instrCfgDir);

    # Load the list of known models
    my $r_models = ModelledInstr::loadModels(\@modelDirs, undef);

    # Figure out which models have "active" metricgroups in the instr profiles
    my %metricGroupsToParse = ();
    my @modelsWithProfiles = ();
    my %hooksModules = ();
    foreach my $r_model ( @{$r_models} ) {
        my $modelNamespace = $r_model->{'namespace'};
        if ( $DEBUG > 5 ) { print "main: checking model with namespace $modelNamespace\n"; }

        while ( my ($profileNameSpace,$r_profiles) = each %{$r_profilesByNamespace} ) {
            if ( $profileNameSpace =~ /$modelNamespace/ ) {
                modelActive($profileNameSpace,$r_profiles,$server,\@services,
                            $r_model,\%metricGroupsToParse,\@modelsWithProfiles,\%hooksModules);
            }
        }
    }

    if ( $DEBUG > 5 ) {
        print Dumper("main: metricGroupsToParse", \%metricGroupsToParse);
        print Dumper("main: modelsWithProfiles", \@modelsWithProfiles);
    }

    # Parse the instr files and extract the data for the metricgroups
    # that are referenced from the models
    my $r_incrData = incrRead( $incrFile );

    # Check that the incremental data is compatiple with this version
    if ( ! defined $r_incrData->{'version'} || $r_incrData->{'version'} != $INCR_VERSION ) {
        $r_incrData = {};
    }
    $r_incrData->{'version'} = $INCR_VERSION;

    my $dataOffset = 0;
    if ( ! defined $r_incrData->{'offset'} ) {
        $r_incrData->{'offset'} = 0;
    }

    if ( exists $r_incrData->{'metricGroupsToParse'} ) {
        while ( my ($mgName,$r_mgCfg) = each %metricGroupsToParse ) {
            my $r_prevCfg = $r_incrData->{'metricGroupsToParse'}->{$mgName};
            if ( defined $r_prevCfg ) {
                if ( ModelledInstr::compareMetrics($r_mgCfg->{'metrics'}, $r_prevCfg->{'metrics'} ) == 1 ) {
                    print "WARN: Cfg for $mgName has changed, using previous cfg\n";
                    $r_mgCfg->{'metrics'} = $r_prevCfg->{'metrics'};
                }
            }
        }
    }

    my $r_data = parseDataForCfg( { 'profile' => { 'providers' => \%metricGroupsToParse} },
                                  $instrData, $r_incrData->{'offset'} );
    if ( $DEBUG > 8 ) { print Dumper( "main: r_data", $r_data ); }

    # Process the data for each model
    foreach my $r_model ( @modelsWithProfiles ) {
        ModelledInstr::processModel(
            $r_model,
            $r_data, \&getMetricGroup,
            \%cliArgs, $dbh, $r_incrData
        );
    }

    $dbh->disconnect();

    $r_incrData->{'offset'} = instrGetOffset($instrData);
    $r_incrData->{'metricGroupsToParse'} = \%metricGroupsToParse;
    incrWrite( $incrFile, $r_incrData );
}

1;

