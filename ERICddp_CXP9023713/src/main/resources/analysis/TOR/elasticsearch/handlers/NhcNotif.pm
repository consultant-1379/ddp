package NhcNotif;

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

    if ( exists $r_incr->{'NhcNotif'} ) {
        $self->{'r_notifStats'} = $r_incr->{'NhcNotif'}->{'r_notifStats'};
    } else {
        $self->{'r_notifStats'} = {};
    }

    $self->{'srvIdMap'} = {};
    foreach my $sg ( 'cmservice', 'conscmeditor' ) {
        my $r_srvMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, $sg);
        while ( my ($srv, $srvId) = each %{$r_srvMap} ) {
            $self->{'srvIdMap'}->{$srv} = $srvId;
        }
    }

    my @programsToFilter = ('JBOSS');
    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        foreach my $program ( @programsToFilter ) {
            push ( @subscriptions, {'server' => $server, 'prog' => $program} );
        }
    }
    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;
    #my ($time)=$timestamp=~/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}).*/;
    my ($date, $time) = $timestamp =~ m/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2}).*/g;
    my ($timeStr)=$timestamp=~/(\d{2}:\d{2}:\d{2}).*/;
    if ( $::DEBUG > 9 ) { print "NhcNotif::handle got message from $host $program : $message\n"; }

    if ( $program eq 'JBOSS' ) {
        if( $message =~ /NHC_SERVICE.JOB_STATUS_RECORD_CREATED.*activityId=(\w+).*requestNo=(\w+).*/ ) {
            my $activity = "JOB_STATUS_RECORD_CREATED";
            my $activityId = $1;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'activityId'} = $1;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'startTime'} = $timeStr;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'requestNo'} = $2;
        }
        elsif ( $message =~ /NHC_SERVICE.ACTIVITY_CREATED.*activityId=(\w+),userId=(.*),networkElementNo=(\w+),checks=\{\[(.*)\]\},acceptanceCriteriaName=(\w+),reportFileName=(\w+.xml|\w+).*/ ) {
            my $activity = "ACTIVITY_CREATED";
            my $activityId = $1;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'time'} = $date . " " . $time;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'activityId'} = $1;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'userId'} = $2;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'networkElementNo'} = $3;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'check'} = $4;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'acceptanceCriteriaName'} = $5;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'reportFileName'} = $6;

            $self->{'userIds'}->{$2} = 1;
            $self->{'acceptanceCriteriaNames'}->{$5} = 1;
        }
        elsif ( $message =~ /NHC_SERVICE.JOB_STATUS_RECORD_DELETED.*activityId=(\w+),requestNo=(\w+),responseNo=(\w+).*/ ) {
            my $activity = "JOB_STATUS_RECORD_DELETED";
            my $activityId = $1;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'stopTime'} = $timeStr;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'activityId'} = $1;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'requestNo'} = $2;
            $self->{'r_notifStats'}->{$activity}->{$activityId}->{'responseNo'} = $3;
        }
        elsif ( $message =~ /NHC_SERVICE.AC_MODIFIED/ ) {
            my %nhcACModification = ();
            # Sample 'NHC AC (Modification)' Log Line: INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (Thread-283 (HornetQ-client-global-threads-787969571)) \
            #   [CPPHealthcheckUser, NHC_SERVICE.AC_MODIFIED, COARSE, , , [userName=CPPHealthcheckUser,acName=ERBSgold]]
            if ( $message =~ /.*acName\s*=\s*([^,\]]*)/ ) {
                $nhcACModification{'modificationTime'} = $date . " " . $time;
                $nhcACModification{'server'} = $host;
                $nhcACModification{'acName'} = "NA";
                if ( $1 ne "" ) {
                    $nhcACModification{'acName'} = $1;
                }

                $nhcACModification{'userId'} = "NA";
                if ( $message =~ /[,\[]\s*userName\s*=\s*([^,\]]*)/ ) {
                    if ( $1 ne "" ) {
                        $nhcACModification{'userId'} = $1;
                    }
                }
                push(@{$self->{'r_notifStats'}->{'nhcacmodifications'}}, \%nhcACModification);

                $self->{'userIds'}->{$nhcACModification{'userId'}} = 1;
                $self->{'acceptanceCriteriaNames'}->{$nhcACModification{'acName'}} = 1;
            }
        }
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 7 ) { print Dumper("NhcNotif::done r_notifStats", $self->{'r_notifStats'}); }
    my $date = $self->{'date'};
    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    my @userIds = keys %{$self->{'userIds'}};
    my @acceptanceCriteriaNames = keys %{$self->{'acceptanceCriteriaNames'}};

    my $user2IdMap = getIdMap($dbh, "enm_nhc_users", "id", "user", \@userIds);
    my $acName2IdMap = getIdMap($dbh, "enm_nhc_acceptance_criteria", "id", "acceptance_criteria_name", \@acceptanceCriteriaNames);

    # Store NHC activities under DB
    my $bcpFileCmservNHCActivities = "$tmpDir/cmserv_nhc_activities";
    open (BCPACTV, "> $bcpFileCmservNHCActivities") or die "Failed to open $bcpFileCmservNHCActivities";
    foreach my $key (keys %{$self->{'r_notifStats'}->{'ACTIVITY_CREATED'}}) {
      my $requestNo="";
      if(! defined $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_CREATED'}->{$key}->{'activityId'}) {
          $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_CREATED'}->{$key}->{'startTime'} = "null";
      }

      if(! defined $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_DELETED'}->{$key}->{'activityId'}) {
          $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_DELETED'}->{$key}->{'stopTime'} = "null";
          $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_DELETED'}->{$key}->{'responseNo'} = "null";
      }

      if(defined $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_CREATED'}->{$key}->{'requestNo'} && $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_CREATED'}->{$key}->{'requestNo'} ne "null") {
          $requestNo= $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_CREATED'}->{$key}->{'requestNo'};
      } elsif(defined $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_DELETED'}->{$key}->{'requestNo'} && $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_DELETED'}->{$key}->{'requestNo'} ne "null") {
          $requestNo= $self->{'r_notifStats'}->{'JOB_STATUS_RECORD_DELETED'}->{$key}->{'requestNo'};
      } else {
           $requestNo="null";
      }

      print BCPACTV "$self->{'siteId'}\t$self->{'r_notifStats'}->{'ACTIVITY_CREATED'}->{$key}->{'time'}\t$self->{'r_notifStats'}->{'ACTIVITY_CREATED'}->{$key}->{'activityId'}\t$user2IdMap->{$self->{'r_notifStats'}->{'ACTIVITY_CREATED'}->{$key}->{'userId'}}\t$self->{'r_notifStats'}->{'JOB_STATUS_RECORD_CREATED'}->{$key}->{'startTime'}\t$self->{'r_notifStats'}->{'JOB_STATUS_RECORD_DELETED'}->{$key}->{'stopTime'}\t$self->{'r_notifStats'}->{'ACTIVITY_CREATED'}->{$key}->{'networkElementNo'}\t$self->{'r_notifStats'}->{'ACTIVITY_CREATED'}->{$key}->{'check'}\t$acName2IdMap->{$self->{'r_notifStats'}->{'ACTIVITY_CREATED'}->{$key}->{'acceptanceCriteriaName'}}\t$requestNo\t$self->{'r_notifStats'}->{'JOB_STATUS_RECORD_DELETED'}->{$key}->{'responseNo'}\t$self->{'r_notifStats'}->{'ACTIVITY_CREATED'}->{$key}->{'reportFileName'}\n";
    }
    close BCPACTV;

     dbDo( $dbh, "DELETE FROM enm_nhc_logs  WHERE siteid = $self->{'siteId'} AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'") or die "Failed to delete from enm_nhc_logs" . $dbh->errstr;

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileCmservNHCActivities' INTO TABLE enm_nhc_logs" )
        or die "Failed to load new data from '$bcpFileCmservNHCActivities' file to 'enm_nhc_logs' table" . $dbh->errstr;

    # Store NHC Acceptance Criteria Modifications under DB
    my $bcpFileACModifications = "$tmpDir/cmserv_nhc_ac_modifications";
    open (BCPMODF, "> $bcpFileACModifications") or die "Failed to open $bcpFileACModifications";

    my $serverIdMap = getIdMap($dbh, "servers", "id", "hostname", [], $self->{'siteId'});
    foreach my $nhcACModification (@{$self->{'r_notifStats'}->{'nhcacmodifications'}}) {
        my $serverId = '\N';
        if ( exists $serverIdMap->{$nhcACModification->{'server'}} ) {
            $serverId = $serverIdMap->{$nhcACModification->{'server'}};
        }
        print BCPMODF "$self->{'siteId'}\t$nhcACModification->{'modificationTime'}\t$serverId\t$acName2IdMap->{$nhcACModification->{'acName'}}\t$user2IdMap->{$nhcACModification->{'userId'}}\n";
    }
    close BCPMODF;

    dbDo( $dbh, "DELETE FROM enm_nhc_ac_modifications WHERE siteid = $self->{'siteId'} AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'") or die "Failed to delete from enm_nhc_ac_modifications" . $dbh->errstr;
    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileACModifications' INTO TABLE enm_nhc_ac_modifications" )
        or die "Failed to load new data from '$bcpFileACModifications' file to 'enm_nhc_ac_modifications' table" . $dbh->errstr;

    unlink($bcpFileCmservNHCActivities);
    unlink($bcpFileACModifications);

    $r_incr->{'NhcNotif'} = { 'r_notifStats' => $self->{'r_notifStats'} };
}

1;
