package misc::Shm;

use strict;
use warnings;

use Data::Dumper;

use StatsDB;
use StatsTime;
use EnmServiceGroup;

#
# Internal functions
#
sub durationToMsec($) {
    my ($durationStr) = @_;

    my @fields = split(":",$durationStr);
    my $msec =
        (
         (
          ($fields[0] * (24*60*60)) +
          ($fields[1] * (60*60)) +
          ($fields[2] * 60) +
          $fields[3]
         ) * 1000
        ) + $fields[4];

    if ( $::DEBUG > 6 ) { print "misc::Shm::durationToMsec $durationStr $msec\n"; }
    return $msec;
}

sub parseLogEntry($$$$) {
    my ($self,$timestamp,$server,$message) = @_;

    if ($::DEBUG > 6) { print "misc::Shm::parseLogEntry: message=$message\n"; }

    # MainJobComplete, COARSE, NHC , Main Job is completed with : , JobType=NODE_HEALTH_CHECK, JobName=Report_administrator_07082018131607, NumberOfNetworkElements=3, StartTime=2018-08-07 13:22:48, EndTime=2018-08-07 13:38:13, Duration of Job=00:00:15:25:183, ProgressPercentage=100.000000, Status=COMPLETED, Result=SUCCESS, *HealthyNodesCount=3*, ReportCategory=PRE_INSTALL, NeTypes: RadioNode]
    if ($message =~ /MainJobComplete.*JobType=(\w+),\s+JobName=([\w\.-]+),\s+NumberOfNetworkElements=(\d+),\s+StartTime=(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}).*EndTime=(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}).*Duration of Job=(.*),\s+ProgressPercentage=(\d*\.?\d+)\..*Status=(\w+),\s+Result=(\w+),\s+HealthyNodesCount=(\d+),\s+ReportCategory=(\w+),\s+NeTypes:\s+(\w+)]/) {
        push @{$self->{'nhcJobDetails'}}, {
            'Date' => formatTime( parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC),
                                  $StatsTime::TIME_SQL ),
            'Type' => $1,
            'JobName' => $2,
            'NumberOfNetworkElements' => $3,
            'StartTime' => $4,
            'EndTime'=> $5,
            'Duration' => durationToMsec($6),
            'ProgressPercentage' => $7,
            'Status' => $8,
            'Result' => $9,
            'HealthyNodesCount' => $10,
            'ReportCategory' => $11,
            'NeTypes' => $12
        };
    # Example for SHM 2016-11-15T05:35:00.692+00:00@svc-2-shmcoreserv@JBOSS@INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (job-executor-tp-threads - 39) [administrator, MainJobComplete, COARSE,  , Main Job is completed with : , JobType=BACKUP, JobName=BackupJob_administrator_Duplicate2, NumberOfNetworkElements=1, StartTime=2016-11-15 05:34:53, EndTime=2016-11-15 05:35:00, Duration of Job=7 second(s)  224 millisecond(s), ProgressPercentage=100.000000, Status=COMPLETED, Result=SUCCESS.]
    } elsif( $message =~ /MainJobComplete.*JobType=(\w+),\s+JobName=([\w\.-]+),\s+NumberOfNetworkElements=(\d+),\s+StartTime=(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}).*EndTime=(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}).*Duration of Job=(.*),\s+ProgressPercentage=(.*)\..*Status=(\w+),\s+Result=(\w+)/ ) {
        push @{$self->{'shmJobDetails'}}, {
            'Date' => formatTime( parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC),
                                  $StatsTime::TIME_SQL ),
            'Type' => $1,
            'JobName' => $2,
            'NumberOfNetworkElements' => $3,
            'StartTime' => $4,
            'EndTime'=> $5,
            'Duration' => durationToMsec($6),
            'ProgressPercentage' => $7,
            'Status' => $8,
            'Result' => $9
        };
    } elsif($message =~ /SHM\.CPP\.UPGRADE\.UPGRADE\_[COMPLETED|FAILED].*Flow : (.*)]/) {
        push @{$self->{'cppDetails'}}, {
            'time'      => formatTime( parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC),
                                       $StatsTime::TIME_SQL ),
            'serverid'  => $self->{'srvIdMap'}->{$server},
            'activity'  =>  'CppUpgrade',
            'flow'      =>   $1
        };
    } elsif($message =~ /SHM\.CPP\.RESTORE\.RESTORE\_[COMPLETED|FAILED].*Flow : (.*)]/) {
        push @{$self->{'cppDetails'}}, {
            'time'      => formatTime( parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC),
                                       $StatsTime::TIME_SQL ),
            'serverid'  => $self->{'srvIdMap'}->{$server},
            'activity'  =>  'CppRestore',
            'flow'      =>   $1
        };
    } elsif($message =~ /SHM\.ECIM\.UPGRADE\.ACTIVATE_[COMPLETED|FAILED].*Flow : (.*)]/) {
        push @{$self->{'cppDetails'}}, {
            'time'      => formatTime( parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC),
                                       $StatsTime::TIME_SQL ),
            'serverid'  => $self->{'srvIdMap'}->{$server},
            'activity'  =>  'EcimUpgrade',
            'flow'      =>   $1
        };
    } elsif($message =~ /SHM\.ECIM\.RESTORE\.RESTOREBACKUP_[COMPLETED|FAILED].*Flow : (.*)]/) {
        push @{$self->{'cppDetails'}}, {
            'time'      => formatTime( parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC),
                                       $StatsTime::TIME_SQL ),
            'serverid'  => $self->{'srvIdMap'}->{$server},
            'activity'  =>  'EcimRestore',
            'flow'      =>   $1
        };
    } elsif($message =~ /SHM\.CPP\.BACKUP\.UPLOAD\_[COMPLETED|FAILED].*Flow : (.*)]/) {
        push @{$self->{'cppDetails'}}, {
            'time'      => formatTime( parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC),
                                       $StatsTime::TIME_SQL ),
            'serverid'  => $self->{'srvIdMap'}->{$server},
            'activity'  =>  'CppUpload',
            'flow'      =>   $1
        };
    } elsif($message =~ /SHM\.ECIM\.BACKUP\.UPLOADBACKUP\_[COMPLETED|FAILED].*Flow : (.*)]/) {
        push @{$self->{'cppDetails'}}, {
            'time'      => formatTime( parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC),
                                       $StatsTime::TIME_SQL ),
            'serverid'  => $self->{'srvIdMap'}->{$server},
            'activity'  =>  'EcimUpload',
            'flow'      =>   $1
        };
    } elsif($message =~ /SHM\.ECIM\.BACKUP\.CREATEBACKUP\_[COMPLETED|FAILED].*Flow : (.*)]/) {
        push @{$self->{'cppDetails'}}, {
            'time'      => formatTime( parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC),
                                       $StatsTime::TIME_SQL ),
            'serverid'  => $self->{'srvIdMap'}->{$server},
            'activity'  =>  'EcimCreate',
            'flow'      =>   $1
        };
    }
}

sub storeShmcoreservJobsLogs($$){
  my($self,$dbh)=@_;

  my $shmJobDetails = $self->{'shmJobDetails'};
  my $nhcJobDetails = $self->{'nhcJobDetails'};
  my $cppDetails = $self->{'cppDetails'};
  my $siteId = $self->{'siteId'};
  my @neTypes = keys %{$self->{'NeTypes'}};
  my $neTypesIdMap = getIdMap($dbh, "ne_types", "id", "name", \@neTypes);

  if ( $#{$shmJobDetails} > -1 ) {
      my $bcpFileShmDetailsLogs = getBcpFileName("enm_shm_details_logs");
      open(BCPACTV, "> $bcpFileShmDetailsLogs") or die "Failed to open $bcpFileShmDetailsLogs";
      foreach my $shmJobDetail (@{$shmJobDetails}) {
          print BCPACTV $shmJobDetail->{'Date'} . "\t" .
              $siteId . "\t" .
              $shmJobDetail->{'Type'} . "\t" .
              $shmJobDetail->{'JobName'} . "\t" .
              $shmJobDetail->{'NumberOfNetworkElements'} . "\t" .
              $shmJobDetail->{'Duration'} . "\t" .
              $shmJobDetail->{'ProgressPercentage'} . "\t" .
              $shmJobDetail->{'Status'} . "\t" .
              $shmJobDetail->{'Result'} . "\n";
      }
      close BCPACTV;

      dbDo($dbh, sprintf("DELETE FROM enm_shmcoreserv_details_logs  WHERE siteid = $siteId AND time BETWEEN '%s' AND '%s'",
          $shmJobDetails->[0]->{'Date'}, $shmJobDetails->[$#{$shmJobDetails}]->{'Date'}))
          or die "Failed to delete from enm_shmcoreserv_details_logs" . $dbh->errstr;
      dbDo($dbh, "LOAD DATA LOCAL INFILE '$bcpFileShmDetailsLogs' INTO TABLE enm_shmcoreserv_details_logs")
          or die "Failed to load new data from '$bcpFileShmDetailsLogs' file to 'enm_shmcoreserv_details_logs' table" . $dbh->errstr;
  }

  if ( $#{$nhcJobDetails} > -1 ) {
      my $bcpFileNhcDetailsLogs = getBcpFileName("enm_nhc_details_logs");
      open (BCPACTV, "> $bcpFileNhcDetailsLogs") or die "Failed to open $bcpFileNhcDetailsLogs";
      foreach my $nhcJobDetail ( @{$nhcJobDetails} ) {
          print BCPACTV $nhcJobDetail->{'Date'} . "\t" .
            $siteId . "\t" .
            $nhcJobDetail->{'Type'} . "\t" .
            $nhcJobDetail->{'JobName'} . "\t" .
            $nhcJobDetail->{'NumberOfNetworkElements'} . "\t" .
            $nhcJobDetail->{'Duration'} . "\t" .
            $nhcJobDetail->{'ProgressPercentage'} . "\t" .
            $nhcJobDetail->{'Status'} . "\t" .
            $nhcJobDetail->{'Result'} . "\t" .
            $nhcJobDetail->{'HealthyNodesCount'} . "\t" .
            $nhcJobDetail->{'ReportCategory'} . "\t" .
            $neTypesIdMap->{$nhcJobDetail->{'NeTypes'}} . "\n";
      }
      close BCPACTV;
      dbDo( $dbh, sprintf("DELETE FROM enm_shmcoreserv_details_logs  WHERE siteid = $siteId AND time BETWEEN '%s' AND '%s' AND netypeid IS NOT NULL",
                          $nhcJobDetails->[0]->{'Date'}, $nhcJobDetails->[$#{$nhcJobDetails}]->{'Date'}))
          or die "Failed to delete from enm_shmcoreserv_details_logs" . $dbh->errstr;
      dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNhcDetailsLogs' INTO TABLE enm_shmcoreserv_details_logs" )
          or die "Failed to load new data from '$bcpFileNhcDetailsLogs' file to 'enm_shmcoreserv_details_logs' table" . $dbh->errstr;
  }

  if ( $#{$cppDetails} > -1 ) {
      my $bcpFileShmcoreservJob = getBcpFileName("enm_shmcoreserv_jobexecution_logs");
      open (BCP, "> $bcpFileShmcoreservJob") or die "Failed to open $bcpFileShmcoreservJob";
      foreach my $cppDetail (@{$cppDetails}) {
          print BCP "$siteId\t$cppDetail->{'time'}\t$cppDetail->{'serverid'}\t$cppDetail->{'activity'}\t$cppDetail->{'flow'}\n";
      }
      close BCP;
      dbDo( $dbh, sprintf("DELETE FROM enm_shmcoreserv_jobexecution_logs WHERE siteid = %s AND time BETWEEN '%s' AND '%s'",
                          $siteId, $cppDetails->[0]->{'time'},$cppDetails->[$#{$cppDetails}]->{'time'}))
          or die "Failed to delete from enm_shmcoreserv_jobexecution_logs";
      dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileShmcoreservJob' INTO TABLE enm_shmcoreserv_jobexecution_logs" )
          or die "Failed to load new data from '$bcpFileShmcoreservJob' file to 'enm_shmcoreserv_jobexecution_logs' table" . $dbh->errstr;
  }
}

#
# handler interface functions
#
sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    $self->{'cppDetails'} = [];
    $self->{'nhcJobDetails'} = [];
    $self->{'shmJobDetails'} = [];

    $self->{'srvIdMap'} = {};
    foreach my $sg ( 'shmcoreservice', 'consshm' ) {
        my $r_srvMap = enmGetServiceGroupInstances($self->{'site'},$self->{'date'}, $sg);
        while ( my ($srv,$srvId) = each %{$r_srvMap} ) {
            $self->{'srvIdMap'}->{$srv} = $srvId;
        }
    }

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }

    if ( $::DEBUG > 5 ) { print Dumper("misc::Shm::init subscriptions",\@subscriptions) ; }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }

    if ( $::DEBUG > 7 ) { print "misc::Shm::handle timestamp=$timestamp message=$message\n"; }

    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\] \(.*\) \[[^,]+, (?:MainJobComplete|SHM|NHC\.)/ ) {
        $self->parseLogEntry($timestamp,$host,$message);
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("misc::Shm::done self", $self); }
    $self->storeShmcoreservJobsLogs($dbh);
}

1;
