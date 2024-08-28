package ReleaseIndependenceLog;

use strict;
use warnings;
use Data::Dumper;
use EnmServiceGroup;
use StatsDB;
use DBI;


sub new
{
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$)
{
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;
    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};
    if ( exists $r_incr->{'ReleseIndependenceLog'} )
    {
        $self->{'r_releseIndependenceLogDiscover'} = $r_incr->{'ReleseIndependenceLog'}->{'r_releseIndependenceLogDiscover'};
    }
    else
    {
        $self->{'r_releseIndependenceLogDiscover'} = {};
    }

    my @subscriptions = ();
    $self->{'serverMap'} = {};
    foreach my $service( "mscm", "cmservice", "comecimmscm", "conscmeditor" ) {
        my $r_serverMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'},$service);
        while ( my ($server,$serverId) = each %{$r_serverMap} ) {
            push ( @subscriptions, {'server' => $server, 'prog' => 'JBOSS'} );
            $self->{'serverMap'}->{$server} = $serverId;
        }
    }
    return \@subscriptions;
}

sub handle($$$$$$$)
{
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    # Skip any warnings/errors
    if ( $severity ne 'info' ) {
        return;
    }

    # Run one regex to check if this is a RELEASE_INDEPENDENCE log entry
    if ( $message =~ /RELEASE_INDEPENDENCE\.(.*)/ ) {
        my $addInfo = $1;

        my ($time)=$timestamp=~/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}).*/;
        if ( $::DEBUG > 3 ) { print "ReleaseIndependenceLog::handle got message from $time $host $program : $message\n"; }

        if( $addInfo =~ /^ADD_CANDIDATE.*productVersion=(.*),neType=(.*),duration=(.*),modelIdentity=(.*),modelStatus=(.*),modelSize=(.*)]/ ) {
            my $serverid = $self->{'serverMap'}->{$host};
            my $activity = "ADD_CANDIDATE";
            push @{$self->{'r_releseIndependenceLogDiscover'}->{$activity}}, {
                'time'                  => $time,
                'serverid'              => $serverid,
                'product_version'           => $1,
                'netype'                    => $2,
                'duration'                  => $3,
                'model_id'                  => $4,
                'model_status'              => $5,
                'model_size'                => $6 };
        } elsif($addInfo =~ /^ADD_SUPPORT.*duration=(.*),neProductVersion=(.*),neType=(.*),result=(.*),numberOfNodes=(.*)]/) {
            my $activity = "ADD_SUPPORT";
            my $serverid = $self->{'serverMap'}->{$host};
            push @{$self->{'r_releseIndependenceLogDiscover'}->{$activity}}, {
                'time'                  => $time,
                'serverid'              => $serverid,
                'product_version'       => $2,
                'netype'                => $3,
                'result'                => $4,
                'numberOfNodes'         => $5,
                'modelIdentity'         => $activity,
                'duration'              => $1};
        } elsif($addInfo =~ /^ADD_SUPPORT.*neProductVersion=(.*),neType=(.*),result=(.*),numberOfNodes=(.*)]/) {
            my $activity = "ADD_SUPPORT";
            my $serverid = $self->{'serverMap'}->{$host};
            push @{$self->{'r_releseIndependenceLogDiscover'}->{$activity}}, {
                'time'                  => $time,
                'serverid'              => $serverid,
                'product_version'       => $1,
                'netype'                => $2,
                'result'                => $3,
                'numberOfNodes'         => $4,
                'modelIdentity'         => $activity,
                'duration'              => '\N'};
        } elsif($addInfo =~/^REMOVE_SUPPORT.*neProductVersion=(.*),neType=(.*),modelIdentity=(.*),numberOfNodes=(.*)]/) {
            my $activity = "REMOVE_SUPPORT";
            my $serverid = $self->{'serverMap'}->{$host};
            push @{$self->{'r_releseIndependenceLogDiscover'}->{$activity}}, {
                'time'                  => $time,
                'serverid'              => $serverid,
                'product_version'       => $1,
                'netype'                => $2,
                'result'                => $activity,
                'numberOfNodes'         => $4,
                'modelIdentity'         => $3,
                'duration'              => '\N'};
        }
    }
}

sub handleExceeded($$$)
{
    my ($self, $host, $program) = @_;
}

sub done($$$)
{
    my ($self,$dbh,$r_incr) = @_;
    my $tmpDir = '/data/tmp';
    my $date=$self->{'date'};
    if (exists $ENV{'TMP_DIR'})
    {
        $tmpDir = $ENV{'TMP_DIR'};
    }
    my $bcpFileCmConfigLogs = "$tmpDir/cm_config_logs";

    if (exists $self->{'r_releseIndependenceLogDiscover'}->{"ADD_CANDIDATE"})
    {
        open (BCP, "> $bcpFileCmConfigLogs") or die "Failed to open $bcpFileCmConfigLogs";
        foreach (@{$self->{'r_releseIndependenceLogDiscover'}->{"ADD_CANDIDATE"}})
        {
            my $activity="ADD_CANDIDATE";
            print BCP "$self->{'siteId'}\t$_->{'time'}\t$_->{'serverid'}\t$_->{'product_version'}\t$_->{'netype'}\t$_->{'duration'}\t$_->{'model_id'}\t$_->{'model_status'}\t$_->{'model_size'}\n";
        }
        close BCP;
        dbDo( $dbh, "DELETE FROM enm_cmconfig_services_logs  WHERE siteid = $self->{'siteId'} AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
        or die "Failed to delete from enm_cmconfig_services_logs" . $dbh->errstr;

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileCmConfigLogs' INTO TABLE enm_cmconfig_services_logs" )
        or die "Failed to load new data from '$bcpFileCmConfigLogs' file to 'enm_cmconfig_services_logs' table" . $dbh->errstr;
        unlink($bcpFileCmConfigLogs);
    }
    if (exists $self->{'r_releseIndependenceLogDiscover'}->{"ADD_SUPPORT"})
    {
        open (BCP, "> $bcpFileCmConfigLogs") or die "Failed to open $bcpFileCmConfigLogs";
        foreach (@{$self->{'r_releseIndependenceLogDiscover'}->{"ADD_SUPPORT"}})
        {
            my $activity="ADD_SUPPORT";
            print BCP "$self->{'siteId'}\t$_->{'time'}\t$_->{'serverid'}\t$activity\t$_->{'product_version'}\t$_->{'netype'}\t$_->{'result'}\t$_->{'numberOfNodes'}\t$_->{'modelIdentity'}\t$_->{'duration'}\n";
        }
        close BCP;
        dbDo( $dbh, "DELETE FROM enm_cmconfig_support_logs  WHERE siteid = $self->{'siteId'} AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND activity='ADD_SUPPORT'" )
        or die "Failed to delete from enm_cmconfig_support_logs" . $dbh->errstr;

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileCmConfigLogs' INTO TABLE enm_cmconfig_support_logs" )
        or die "Failed to load new data from '$bcpFileCmConfigLogs' file to 'enm_cmconfig_support_logs' table" . $dbh->errstr;
        unlink($bcpFileCmConfigLogs);

    }
    if (exists $self->{'r_releseIndependenceLogDiscover'}->{"REMOVE_SUPPORT"})
    {
        open (BCP, "> $bcpFileCmConfigLogs") or die "Failed to open $bcpFileCmConfigLogs";
        foreach (@{$self->{'r_releseIndependenceLogDiscover'}->{"REMOVE_SUPPORT"}})
        {
            my $activity="REMOVE_SUPPORT";
            print BCP "$self->{'siteId'}\t$_->{'time'}\t$_->{'serverid'}\t$activity\t$_->{'product_version'}\t$_->{'netype'}\t$_->{'result'}\t$_->{'numberOfNodes'}\t$_->{'modelIdentity'}\t$_->{'duration'}\n";
        }
        close BCP;
        dbDo( $dbh, "DELETE FROM enm_cmconfig_support_logs  WHERE siteid = $self->{'siteId'} AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59' AND activity='REMOVE_SUPPORT'" )
         or die "Failed to delete from enm_cmconfig_support_logs" . $dbh->errstr;

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileCmConfigLogs' INTO TABLE enm_cmconfig_support_logs" )
        or die "Failed to load new data from '$bcpFileCmConfigLogs' file to 'enm_cmconfig_support_logs' table" . $dbh->errstr;
        unlink($bcpFileCmConfigLogs);
    }
    $r_incr->{'ReleseIndependenceLog'} = {
                                 'r_releseIndependenceLogDiscover' => $self->{'r_releseIndependenceLogDiscover'}
                                 };
}

1;

