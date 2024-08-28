package ComEcimNotifAnalysis;

use strict;
use warnings;

use Data::Dumper;
use EnmServiceGroup;
use StatsDB;
use DBI;


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

    if ( exists $r_incr->{'ComEcimNotifAnalysis'} ) {
        $self->{'r_notifStats'} = $r_incr->{'ComEcimNotifAnalysis'}->{'r_notifStats'};
    }
    else {
        $self->{'r_notifStats'} = {};
    }
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

    if ( $::DEBUG > 9 ) { print "ComEcimNotifAnalysis::handle got message from $host $program : $message\n"; }

    if ( $program eq 'JBOSS' ) {
        if( $message =~ /NOTIFICATION_RECEIVER_HANDLER.*NOTIFICATION_STATS\s*(\d{4}-\d{2}-\d{2}).(\d{2}:\d{2}:\d{2})\s*-\s*(\d{4}-\d{2}-\d{2}).(\d{2}:\d{2}:\d{2})/ ) {
            my $startTime = $1 . ' ' . $2;
            my $endTime = $3 . ' ' . $4;

            # Return here itself, if it's not one of the following log lines
            # Sample Log Lines:
            #   {NOTIFICATION_STATS 2017-06-01 19:12:05 - 2017-06-01 19:14:02} DISCARDED_NODE_NOTIFICATIONS(Top 1/1)=[LTE01dg2ERBS00001:4]Total_1:4]
            #   {NOTIFICATION_STATS 2017-06-01 19:12:05 - 2017-06-01 19:14:02} TOTAL_NOTIFICATIONS=[Received:6,Processed:37]]
            #   {NOTIFICATION_STATS 2017-06-01 19:12:05 - 2017-06-01 19:14:02} NOTIFICATION_LEAD_TIME=[Min:534,Max:5443,Avg:1759,Samples_Size:23]]
            #   {NOTIFICATION_STATS 2017-06-01 19:12:05 - 2017-06-01 19:14:02} VALIDATION_HANDLER_TIME=[Min:0,Max:1768,Avg:290,Samples_Size:109]]
            #   {NOTIFICATION_STATS 2017-06-01 19:12:05 - 2017-06-01 19:14:02} WRITE_HANDLER_TIME=[Min:105,Max:70253,Avg:2605,Samples_Size:32]]
            if ( $message !~ /TOTAL_NOTIFICATIONS|DISCARDED_NODE_NOTIFICATIONS|NOTIFICATION_LEAD_TIME|VALIDATION_HANDLER_TIME|WRITE_HANDLER_TIME/ ) {
                return;
            }

            if ( $::DEBUG > 7 ) { print "ComEcimNotifAnalysis::handle notification_message : $message\n"; }

            my $notifStatKey = $startTime . '@@' . $endTime . '@@' . $host;
            if ( ! exists $self->{'r_notifStats'}->{$notifStatKey} ) {
                $self->{'r_notifStats'}->{$notifStatKey} = {
                    'totalnotificationsreceived'  => '\N',
                    'totalnotificationsprocessed' => '\N',
                    'totalnotificationsdiscarded' => '\N',
                    'leadtimemax'                 => '\N',
                    'leadtimeavg'                 => '\N',
                    'validationhandlertimemax'    => '\N',
                    'validationhandlertimeavg'    => '\N',
                    'writehandlertimemax'         => '\N',
                    'writehandlertimeavg'         => '\N'
                };
            }

            if( $message =~ /TOTAL_NOTIFICATIONS\s*=\s*\[\s*Received\s*:\s*(\d+)\s*,\s*Processed\s*:\s*(\d+).*/ ) {
                $self->{'r_notifStats'}->{$notifStatKey}->{'totalnotificationsreceived'} = $1;
                $self->{'r_notifStats'}->{$notifStatKey}->{'totalnotificationsprocessed'} = $2;
            }
            elsif ( $message =~ /DISCARDED_NODE_NOTIFICATIONS.*Total_[0-9]+\s*:\s*(\d+)\s*\]/ ) {
                $self->{'r_notifStats'}->{$notifStatKey}->{'totalnotificationsdiscarded'} = $1;
            }
            elsif ( $message =~ /NOTIFICATION_LEAD_TIME\s*=\s*\[.*Max\s*:\s*(\d+)\s*,\s*Avg\s*:\s*(\d+).*/ ) {
                $self->{'r_notifStats'}->{$notifStatKey}->{'leadtimemax'} = $1;
                $self->{'r_notifStats'}->{$notifStatKey}->{'leadtimeavg'} = $2;
            }
            elsif ( $message =~ /VALIDATION_HANDLER_TIME\s*=\s*\[.*Max\s*:\s*(\d+)\s*,\s*Avg\s*:\s*(\d+).*/ ) {
                $self->{'r_notifStats'}->{$notifStatKey}->{'validationhandlertimemax'} = $1;
                $self->{'r_notifStats'}->{$notifStatKey}->{'validationhandlertimeavg'} = $2;
            }
            elsif ( $message =~ /WRITE_HANDLER_TIME\s*=\s*\[.*Max\s*:\s*(\d+)\s*,\s*Avg\s*:\s*(\d+).*/ ) {
                $self->{'r_notifStats'}->{$notifStatKey}->{'writehandlertimemax'} = $1;
                $self->{'r_notifStats'}->{$notifStatKey}->{'writehandlertimeavg'} = $2;
            }
        }
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 7 ) { print Dumper("ComEcimNotifAnalysis::done r_notifStats", $self->{'r_notifStats'}); }

    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    # Get server ID map
    my $serverIdMap = getIdMap($dbh, "servers", "id", "hostname", [], $self->{'siteId'});
    if ( $::DEBUG > 9 ) { print Dumper("ComEcimNotifAnalysis::done serverIdMap ", $serverIdMap); }

    # Store 'Notification Analysis Details'
    my $bcpFileNotifStats = "$tmpDir/enm_mscmcenotification_logs.bcp";
    open (BCP, "> $bcpFileNotifStats");

    foreach my $notifStatKey (sort keys %{$self->{'r_notifStats'}}) {
        # Get 'Start Time', 'End Time' and 'Hostname' by splitting the $notifStatKey
        my @notifStatKey = split(/@@/, $notifStatKey);
        print BCP $self->{'siteId'} . "\t" . $notifStatKey[0] . "\t" . $notifStatKey[1] . "\t" .
                  $serverIdMap->{$notifStatKey[2]} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'totalnotificationsreceived'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'totalnotificationsprocessed'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'totalnotificationsdiscarded'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'leadtimemax'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'leadtimeavg'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'validationhandlertimemax'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'validationhandlertimeavg'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'writehandlertimemax'} . "\t" .
                  $self->{'r_notifStats'}->{$notifStatKey}->{'writehandlertimeavg'} . "\n";
    }
    close BCP;

    dbDo( $dbh, sprintf("DELETE FROM enm_mscmcenotification_logs WHERE siteid = %d AND endtime BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                        $self->{'siteId'}, $self->{'date'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_mscmcenotification_logs";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNotifStats' INTO TABLE enm_mscmcenotification_logs" )
        or die "Failed to load new data from '$bcpFileNotifStats' file to 'enm_mscmcenotification_logs' table";

    unlink($bcpFileNotifStats);

    $r_incr->{'ComEcimNotifAnalysis'} = {
                                         'r_notifStats' => $self->{'r_notifStats'}
                                         };
}

1;
