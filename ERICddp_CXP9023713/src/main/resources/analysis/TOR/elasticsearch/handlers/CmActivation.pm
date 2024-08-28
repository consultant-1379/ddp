package CmActivation;

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

sub init($$$$){
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;
    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};
    if ( exists $r_incr->{'CmActivation'} ) {
        $self->{'r_cmActivation'} = $r_incr->{'CmActivation'}->{'r_cmActivation'};
        $self->{'r_cmHistory'} = $r_incr->{'CmActivation'}->{'r_cmHistory'};
    } else {
        $self->{'r_cmActivation'} = {};
        $self->{'r_cmHistory'} = {};
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

sub handle($$$$$$$)
{
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;
    if ( $severity ne 'info' )
    {
        return;
    }

    if ( $::DEBUG > 3 ) { print "CmActivation::handle got message from $host $program : $message\n"; }
    if ( $program eq 'JBOSS' )
    {
       if ( $message =~ /.*ACTIVATE_CONFIG_SERVICE_JOB.*startTime=([0-9]{4}-[0-9]{2}-[0-9]{2})T(\d+:\d+:\d+\.?\d+).*result=(\w+),failedChanges=(\d+),configName=([0-9A-Za-z-_]*).*jobId=(\d+),successfulChanges=(\d+),processedChangessPerSec=(\d*\.?\d{1,2}).*endTime=([0-9]{4}-[0-9]{2}-[0-9]{2})T(\d+:\d+:\d+\.?\d+).*statusDetail=(.*)]/)
        {
            my $startTime= $1." ".$2;
            my $endTime= $9. " ".$10;
            my $cmActivationKey = $startTime . '@@' . $endTime . '@@' . $host;
            my $status = '';

            if( $11 eq "COMPLETED Some applied change has failed" ) {
                $status = "COMPLETED_C_FAIL";
           }
            elsif ( $11 eq "COMPLETED Warning: History data not successfully stored. Please see online help." ) {
                $status = "COMPLETED_H_FAIL";
            }
            elsif ( $11 eq "COMPLETED Some applied change has failed. Warning: History data not successfully stored. Please see online help." ) {
                $status = "COMPLETED_CH_FAIL";
            }
            else {
                $status = $11;
            }

            if ( ! exists $self->{'r_cmActivation'}->{$cmActivationKey} ) {
                   $self->{'r_cmActivation'}->{$cmActivationKey} = {
                        'startTime'               => $startTime,
                        'result'                  => $3,
                        'failedChanges'           => $4,
                        'configName'              => $5,
                        'jobId'                   => $6,
                        'successfulChanges'       => $7,
                        'processedChangessPerSec' => $8,
                        'endTime'                 => $endTime,
                        'statusDetail'            => $status
                    };
            }
        }elsif ( $message =~ /.*ACTIVATE_CONFIG_SERVICE_JOB.*startTime=([0-9]{4}-[0-9]{2}-[0-9]{2})T(\d+:\d+:\d+\.?\d+).*result=(\w+),failedChanges=(\d+),configName=([0-9A-Za-z-_]*).*jobId=(\d+),successfulChanges=(\d+),processedChangessPerSec=(\d*\.?\d{1,2}).*endTime=([0-9]{4}-[0-9]{2}-[0-9]{2})T(\d+:\d+:\d+\.?\d+)/ )
        {

            my $startTime= $1." ".$2;
            my $endTime= $9. " ".$10;
            my $cmActivationKey = $startTime . '@@' . $endTime . '@@' . $host;
            if ( ! exists $self->{'r_cmActivation'}->{$cmActivationKey} )
            {
                $self->{'r_cmActivation'}->{$cmActivationKey} = {
                     'startTime'               => $startTime,
                        'result'                  => $3,
                        'failedChanges'           => $4,
                        'configName'              => $5,
                        'jobId'                   => $6,
                        'successfulChanges'       => $7,
                        'processedChangessPerSec' => $8,
                        'endTime'                 => $endTime,
                        'statusDetail'            => 'NA'
                    };
            }
        }

        if ( $message =~ /.*HISTORICAL_DATA_WRITER.*totalMoToWrite=(\d+),startTime=([0-9]{4}-[0-9]{2}-[0-9]{2})T(\d+:\d+:\d+\.?\d+).*attribute_modification=(\d+),mib_root_created=(\d+).*mo_created=(\d+),jobId=(\d+),mo_deleted=(\d+),endTime=([0-9]{4}-[0-9]{2}-[0-9]{2})T(\d+:\d+:\d+\.?\d+).*action_performed=(\d+),size=(\d+)/ )
        {
            my $startTime= $2." ". $3;
            my $endTime =$9 . " ".$10;

            my $cmHistoryKey = $startTime . '@@' . $endTime . '@@' . $host;
            if ( ! exists $self->{'r_cmHistory'}->{$cmHistoryKey} )
            {
                $self->{'r_cmHistory'}->{$cmHistoryKey} = {
                        'totalMoToWrite'         => $1,
                        'startTime'              => $startTime,
                        'attribute_modification' => $4,
                        'mib_root_created'       => $5,
                        'mo_created'             => $6,
                        'jobId'                  => $7,
                        'mo_deleted'             => $8,
                        'endTime'                => $endTime,
                        'action_performed'       => $11,
                        'size'                   => $12

                    };
            }
        }
    }
}

sub handleExceeded($$$)
{
    my ($self, $host, $program) = @_;
}

sub done($$$)
{
    my ($self, $dbh, $r_incr) = @_;
    #print "hash is".Dumper($self);
     my $tmpDir = '/data/tmp';
     if (exists $ENV{'TMP_DIR'})
    {
        $tmpDir = $ENV{'TMP_DIR'};
    }
    # Write CM ACTIVATION  related data to 'enm_cm_activation.bcp' file

    my $bcpFileActivation = "$tmpDir/enm_cm_activation.bcp";
    open (BCP, "> $bcpFileActivation") or die "Failed to open $bcpFileActivation";
    foreach my $ActivationStatKey(sort keys %{$self->{'r_cmActivation'}})
    {
        my @ActivationStatKey = split(/@@/, $ActivationStatKey);
        print BCP $self->{'siteId'}."\t".
        $self->{'r_cmActivation'}->{$ActivationStatKey}->{'jobId'}."\t".
        $self->{'r_cmActivation'}->{$ActivationStatKey}->{'startTime'}."\t".
        $self->{'r_cmActivation'}->{$ActivationStatKey}->{'endTime'}."\t".
        $self->{'r_cmActivation'}->{$ActivationStatKey}->{'successfulChanges'}."\t".
        $self->{'r_cmActivation'}->{$ActivationStatKey}->{'failedChanges'}."\t".
        $self->{'r_cmActivation'}->{$ActivationStatKey}->{'result'}."\t".
        $self->{'r_cmActivation'}->{$ActivationStatKey}->{'processedChangessPerSec'}."\t".
        $self->{'r_cmActivation'}->{$ActivationStatKey}->{'configName'}."\t".
        $self->{'r_cmActivation'}->{$ActivationStatKey}->{'statusDetail'}."\n";
    }
    close BCP;
    dbDo( $dbh, "DELETE FROM enm_cm_activation WHERE siteid = $self->{'siteId'} AND start BETWEEN '$self->{'date'} 00:00:00' AND '$self->{'date'} 23:59:59'" )
        or die "Failed to delete from enm_cm_activation";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileActivation' INTO TABLE enm_cm_activation" )
        or die "Failed to load new data from '$bcpFileActivation' file to 'enm_cm_activation' table";

    # Write CM HISTORY  related data to 'enm_cm_history.bcp' file
    my  $bcpFileHistory = "$tmpDir/enm_cm_history.bcp";
    open (BCP, "> $bcpFileHistory") or die "Failed to open $bcpFileHistory";
    foreach my $HistoryStatKey(sort keys %{$self->{'r_cmHistory'}})
    {
        my @HistoryStatKey = split(/@@/, $HistoryStatKey);
         print BCP $self->{'siteId'}."\t".
         $self->{'r_cmHistory'}->{$HistoryStatKey}->{'jobId'}."\t".
         $self->{'r_cmHistory'}->{$HistoryStatKey}->{'startTime'}."\t".
         $self->{'r_cmHistory'}->{$HistoryStatKey}->{'endTime'}."\t".
         $self->{'r_cmHistory'}->{$HistoryStatKey}->{'totalMoToWrite'}."\t".
         $self->{'r_cmHistory'}->{$HistoryStatKey}->{'mib_root_created'}."\t".
         $self->{'r_cmHistory'}->{$HistoryStatKey}->{'mo_created'}."\t".
         $self->{'r_cmHistory'}->{$HistoryStatKey}->{'attribute_modification'}."\t".
         $self->{'r_cmHistory'}->{$HistoryStatKey}->{'mo_deleted'}."\t".
         $self->{'r_cmHistory'}->{$HistoryStatKey}->{'action_performed'}."\t".
         $self->{'r_cmHistory'}->{$HistoryStatKey}->{'size'}."\n";

    }
    close BCP;

    dbDo( $dbh, "DELETE FROM enm_cm_history WHERE siteid = $self->{'siteId'} AND start BETWEEN '$self->{'date'} 00:00:00' AND '$self->{'date'} 23:59:59'" )
        or die "Failed to delete from enm_cm_history";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileHistory' INTO TABLE enm_cm_history" )
        or die "Failed to load new data from '$bcpFileHistory' file to 'enm_cm_history' table";
    unlink($bcpFileActivation);
    unlink($bcpFileHistory);

    $r_incr->{'CmActivation'} = {
                                     'r_cmActivation' => $self->{'r_cmActivation'},
                                     'r_cmHistory'    => $self->{'r_cmHistory'}
                                     };
}
1;
