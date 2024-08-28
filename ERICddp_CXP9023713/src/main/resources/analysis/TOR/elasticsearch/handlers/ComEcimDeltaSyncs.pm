package ComEcimDeltaSyncs;

use strict;
use warnings;

use Data::Dumper;
use EnmServiceGroup;
use StatsDB;
use DBI;

use Time::Local;
use StatsTime;


sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self, $r_cliArgs, $r_incr, $dbh) = @_;

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    $self->{'deltaSyncStats'} = [];

    my @subscriptions = ();
    $self->{'srvIdMap'} = {};
    foreach my $service( "comecimmscm", "mscmapg" ) {
        my $r_serverMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'},$service);
        while ( my ($server,$serverId) = each %{$r_serverMap} ) {
            push ( @subscriptions, {'server' => $server, 'prog' => 'JBOSS'} );
            $self->{'srvIdMap'}->{$server} = $serverId;
        }
    }
    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }

    # Sample Log Line:
    # INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (Camel (camel-1) thread #1 - JmsConsumer[ComEcimMdbNotificationListener_0]) [NO USER DATA, \
    #     COM-ECIM-NOTIFICATION-SUPERVISION-HANDLER.NOTIFICATION_RECEIVER_HANDLER, DETAILED, LTE45dg2ERBS00149, Node Notifications, \
    #     {NOTIFICATION_STATS 2017-08-02 00:07:20 - 2017-08-02 00:07:21} DELTA_NOTIFICATIONS=[CREATE:0,DELETE:0,MODIFY:2]#012Total_2:3]
    if ( $message =~ /COM-ECIM-NOTIFICATION-SUPERVISION-HANDLER.NOTIFICATION_RECEIVER_HANDLER.*DELTA_NOTIFICATIONS/ ) {
        if ( $::DEBUG > 7 ) { print "ComEcimDeltaSyncs::handle notification_message : $message\n"; }

        if ( $message !~ /NOTIFICATION_STATS\s*(\d{4}-\d{2}-\d{2}).(\d{2}:\d{2}:\d{2})\s*-\s*(\d{4}-\d{2}-\d{2}).(\d{2}:\d{2}:\d{2})/ ) {
            return;
        }
        my $startTime = $1 . ' ' . $2;
        my $endTime = $3 . ' ' . $4;

        if ( $message !~ /NOTIFICATION_RECEIVER_HANDLER,\s*DETAILED\s*,\s*([^,\s]*)\s*,\s*Node\s*Notifications/ ) {
            return;
        }
        my $neName = $1;

        my %deltaSync = (
            'start'   =>  $startTime,
            'end'     =>  $endTime,
            'host'    => $host,
            'ne'      => $neName,
            'create'  => '\N',
            'delete'  => '\N',
            'update'  => '\N'
        );

        if ( $message =~ /CREATE\s*:\s*(\d+)\s*/ ) {
            $deltaSync{'create'} = $1;
        }
        if ( $message =~ /DELETE\s*:\s*(\d+)\s*/ ) {
            $deltaSync{'delete'} = $1;
        }
        if ( $message =~ /MODIFY\s*:\s*(\d+)\s*/ ) {
            $deltaSync{'update'} = $1;
        }

        if ( $::DEBUG > 6 ) { print Dumper("ComEcimDeltaSyncs::handle deltaSync", \%deltaSync); }
        push @{$self->{'deltaSyncStats'}}, \%deltaSync;
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 7 ) { print Dumper("ComEcimDeltaSyncs::done deltaSyncStats", $self->{'deltaSyncStats'}); }

    if ( $#{$self->{'deltaSyncStats'}} == -1 ) {
        # No delta syncs do nothing to do
        return;
    }

    # Get Network Element (NE) ID Map
    my %allNeNames = ();
    foreach my $r_deltaSync (@{$self->{'deltaSyncStats'}}) {
        $allNeNames{$r_deltaSync->{'ne'}} = 1;
    }
    my @neNameList = keys %allNeNames;
    my $neIdMap = getIdMap( $dbh, "enm_ne", "id", "name", \@neNameList, $self->{'siteId'} );

    # Store 'Delta Notification Details'
    my $bcpFileNotifStats = getBcpFileName("enm_com_ecim_delta_syncs");
    open (BCP, "> $bcpFileNotifStats");

    foreach my $r_deltaSync ( @{$self->{'deltaSyncStats'}} ) {
        my @row = ( $self->{'siteId'}, $r_deltaSync->{'start'}, $r_deltaSync->{'end'},
                    $self->{'srvIdMap'}->{$r_deltaSync->{'host'}},
                    $neIdMap->{$r_deltaSync->{'ne'}},
                    $r_deltaSync->{'create'},
                    $r_deltaSync->{'delete'},
                    $r_deltaSync->{'update'} );
        print BCP join("\t", @row), "\n";
    }
    dbDo( $dbh, sprintf("DELETE FROM enm_com_ecim_delta_syncs WHERE siteid = %d AND endtime BETWEEN '%s' AND '%s'",
                        $self->{'siteId'},
                        $self->{'deltaSyncStats'}->[0]->{'end'},
                        $self->{'deltaSyncStats'}->[$#{$self->{'deltaSyncStats'}}]->{'end'}
                    )
      ) or die "Failed to delete old data from enm_com_ecim_delta_syncs";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNotifStats' INTO TABLE enm_com_ecim_delta_syncs" )
        or die "Failed to load new data from '$bcpFileNotifStats' file to 'enm_com_ecim_delta_syncs' table";
}

1;
