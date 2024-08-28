package TOR::platform::LvsStats;

use strict;
use warnings;

use Data::Dumper;
use Storable qw(dclone);

use StatsDB;
use Instr;

our @DROP_APPS = ('eric-oss-ingress-controller-nx', 'filetransferservice');
#
# handler interface functions
#
sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub prestore($$$$) {
    my ($self,
        $r_cliArgs,$dbh,$r_model,
        $r_dataSets,$r_columnMap) = @_;

    if ( $::DEBUG > 5 ) { printf("TOR::platform::LvsStats #dataSets=%d\n", ($#{$r_dataSets}+1)); }
    if ( $::DEBUG > 1 ) { print Dumper("TOR::platform::LvsStats dataSets", $r_dataSets); }

    my $queryTemplate = <<EOS;
SELECT
 k8s_pod.pod,
 servers.hostname,
 k8s_pod_app_names.name
FROM k8s_pod
JOIN servers ON k8s_pod.serverid = servers.id
LEFT OUTER JOIN k8s_pod_app_names ON k8s_pod.appid = k8s_pod_app_names.id
WHERE
k8s_pod.siteid = %d AND
k8s_pod.date = '%s'
EOS

    my $r_rows = dbSelectAllArr($dbh, sprintf($queryTemplate, $r_cliArgs->{'siteid'}, $r_cliArgs->{'date'}))
        or die "Failed to query k8s_pod";
    my %podMap = ();
    foreach my $r_row ( @{$r_rows} ) {
        $podMap{$r_row->[0]} = {
            'server' => $r_row->[1],
            'app' => $r_row->[2]
        };
    }

    my %lvsMap = ();
    my $r_lvsRows = dbSelectAllArr($dbh, "SELECT id, lhost, lport, rhost, rport, proto FROM enm_lvs");
    foreach my $r_lvsRow ( @{$r_lvsRows} ) {
        my $id = shift @{$r_lvsRow};
        my $key = join("@", @{$r_lvsRow});
        $lvsMap{$key} = $id;
    }

    my %dropApps = ();
    foreach my $app ( @DROP_APPS ) {
        $dropApps{$app} = 1;
    }
    my @outDataSets = ();
    my %droppedAppCounts = ();
    my $droppedEmptyDataSets = 0;
    foreach my $r_dataSet ( @{$r_dataSets} ) {
        if ( $::DEBUG > 5 ) { print Dumper("TOR::platform::LvsStats properties", $r_dataSet->{'properties'}); }

        # Discard completely empty dataSets (only one sample with all zeros)
        if ( $#{$r_dataSet->{'samples'}} == 0 ) {
            if ( $::DEBUG > 5 ) { print Dumper("TOR::platform::LvsStats idle check", $r_dataSet->{'samples'}->[0]); }
            my $total = 0;
            while ( my ($name,$value) = each %{$r_dataSet->{'samples'}->[0]} ) {
                if ( $name ne 'time' && $name ne 'timestamp' ) {
                    $total += $value;
                }
            }
            if ( $::DEBUG > 5 ) { print "TOR::platform::LvsStats idle check total=$total\n"; }
            if ( $total == 0 ) {
                $droppedEmptyDataSets++;
                next;
            }
        }

        my $lhost = $r_dataSet->{'properties'}->{'lhost'}->{'sourcevalue'};
        my $lport = $r_dataSet->{'properties'}->{'lport'}->{'sourcevalue'};
        my $rhost = $r_dataSet->{'properties'}->{'rhost'}->{'sourcevalue'};
        my $mappedRhost = $podMap{$rhost}->{'server'};
        if ( ! defined $mappedRhost ) {
            print "WARN: TOR::platform::LvsStats no mapping for $rhost\n";
            next;
        }
        my $app = $podMap{$rhost}->{'app'};
        if ( defined $app && exists $dropApps{$app} ) {
            $droppedAppCounts{$app}++;
            next;
        }

        my $rport = $r_dataSet->{'properties'}->{'rport'}->{'sourcevalue'};
        my $proto = $r_dataSet->{'properties'}->{'proto'}->{'sourcevalue'};

        my $key = sprintf("%s@%d@%s@%d@%s", $lhost, $lport, $mappedRhost, $rport, $proto);
        my $lvsid = $lvsMap{$key};
        if ( ! defined $lvsid ) {
            dbDo(
                $dbh,
                sprintf(
                    "INSERT INTO enm_lvs (lhost,lport,rhost,rport,proto) VALUES ('%s', %d, '%s', %d, '%s')",
                    $lhost,
                    $lport,
                    $mappedRhost,
                    $rport,
                    $proto
                )
            ) or die "Failed to insert row";
            $lvsid = $dbh->last_insert_id( undef, undef, "enm_lvs", "id" );
            $lvsMap{$key} = $lvsid;
        }
        $r_dataSet->{'properties'} = {
            'lvsid' => {
                'sourcevalue' => $lvsid
            }
        };
        push @outDataSets, $r_dataSet;
    }
    if ( %droppedAppCounts ) {
        print "WARN: TOR::LvsStats dropped datasets\n";
        while ( my ($app,$count) = each %droppedAppCounts ) {
            print "    $app: $count\n";
        }
    }
    if ( $droppedEmptyDataSets > 0) {
        print "WARN: TOR::LvsStats dropped $droppedEmptyDataSets empty dataSets\n"
    }
    # Update the model as we replaced the multi keys with just lvsid
    $r_model->{'multi'} = [ 'lvsid' ];

    return \@outDataSets;
}

1;
