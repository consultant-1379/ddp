package UlsaSpectrumAnalyser;

use strict;
use warnings;
use Data::Dumper;
use EnmServiceGroup;
use StatsDB;
use DBI;
use StatsTime;

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
    $self->{'events'} = [];


    my @subscriptions = ();
    $self->{'serverMap'} = {};
    foreach my $service( "pmservice", "saservice" ) {
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
    if ( $::DEBUG > 9 ) { print "UlsaSpectrumAnalyser::handle got message from $host $program : $message\n"; }

    # Skip any warnings/errors
    if ( $severity ne 'info' ) {
        return;
    }

    my ($time)=$timestamp=~/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}).*/;
    my ($epochtime) = getDateTimeInMilliSeconds($timestamp);
    if ( $::DEBUG > 3 ) { print "UlsaSpectrumAnalyser::handle got message from $time $host $program : $message\n"; }

    #Sample Log
    #2017-09-08 14:55:36,028 INFO [com.ericsson.oss.itpf.EVENT_LOGGER] (ajp-executor-threads - 18) [administrator, Spectrum Analyzer, DETAILED, #ULSA_COMPONENT_FFT, MeContext=lienb4003, Samples=65510; FileParsingTime(ms)=50; FastFourierTime(ms)=370; PostProcessingTime(ms)=3; #ChartScalingTime(ms)=1; TotalTime(ms)=424]

    if( $message =~ /.*ULSA_COMPONENT_FFT,\s+(\S+),\s+Samples=(\d+);\s+FileParsingTime\S+=(\d+);\s+FastFourierTime\S+=(\d+);\s+PostProcessingTime\S+=(\d+);\s+ChartScalingTime\S+=(\d+);\s+TotalTime\S+=(\d+)]/ ) {
       my $serverid = $self->{'serverMap'}->{$host};
       my %event = (
           'time'                  => $time,
           'serverid'              => $serverid,
           'epochtime'             => $epochtime,
           'source'                => $1,
           'sample'                => $2,
           'file_parsing_time'     => $3,
           'fast_fourier_time'     => $4,
           'post_processing_time'  => $5,
           'chart_scaling_time'    => $6,
           'total_time'            => $7
        );
        push @{$self->{'events'}}, \%event;
    }

}

sub handleExceeded($$$)
{
    my ($self, $host, $program) = @_;
}

sub done($$$)
{
    my ($self, $dbh, $r_incr) = @_;
    if ( $#{$self->{'events'}} == -1 ) {
        return;
    }

    my $bcpFileUlsaAnalyserLogs = getBcpFileName("ulsa_spectrum_analyser_logs");
    open(BCP, "> $bcpFileUlsaAnalyserLogs") or die "Failed to open $bcpFileUlsaAnalyserLogs";

    foreach my $activity (@{$self->{'events'}}) {
        print BCP $self->{'siteId'} . "\t" .
            $activity->{'serverid'} . "\t" .
            $activity->{'time'} . "\t" .
            $activity->{'epochtime'} . "\t" .
            $activity->{'source'} . "\t" .
            $activity->{'sample'} . "\t" .
            $activity->{'file_parsing_time'} . "\t" .
            $activity->{'fast_fourier_time'} . "\t" .
            $activity->{'post_processing_time'} . "\t" .
            $activity->{'chart_scaling_time'} . "\t" .
            $activity->{'total_time'} . "\n";
    }
    close BCP;
    my $siteid = $self->{'siteId'};
    my $fromTime = $self->{'events'}->[0]->{'time'};
    my $toTime = $self->{'events'}->[$#{$self->{'events'}}]->{'time'};
    dbDo($dbh, sprintf("DELETE FROM enm_ulsa_spectrum_analyser_logs WHERE siteid = $siteid AND time BETWEEN '$fromTime' AND '$toTime'"))
        or die "Failed to delete old data from enm_ulsa_spectrum_analyser_logs";

    dbDo($dbh, "LOAD DATA LOCAL INFILE '$bcpFileUlsaAnalyserLogs' INTO TABLE enm_ulsa_spectrum_analyser_logs")
        or die "Failed to load new data from '$bcpFileUlsaAnalyserLogs' file to 'enm_ulsa_spectrum_analyser_logs' table" . $dbh->errstr;
}

1;
