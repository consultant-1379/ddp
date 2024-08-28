package OutOfMemory;
#=====================================================================
## Script  : parseOomError
## Author  : Rani Maddala (xranmad)
## Purpose : The purpose of this script is to parse log lines which contains outofmemory error
##           and need to store the corresponding server name,process name and error count.
##=====================================================================
use strict;
use warnings;

use Getopt::Long;
use Data::Dumper;
use StatsDB;
use EnmServiceGroup;
use DBI;
use StatsTime;

our $SINGLE_OOM_TIME = 600;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}


sub init($$$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteid'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};
    $self->{'svcServerMap'} = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},undef);
    if ( exists $r_incr->{'OutOfMemory'} ) {
	$self->{'exceptionData'} = $r_incr->{'OutOfMemory'}->{'exceptionData'};
    } else {
	$self->{'exceptionData'} = {};
    }
    return undef;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;
    if ( $severity ne "err" && $severity ne "warning" ) {
        return;
    }

    if ( $message !~ /java\.lang\.OutOfMemoryError/ ) {
	return;
    }

    my $time = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC);

    my $serverId = $self->{'svcServerMap'}->{$host};
    if ( ! defined $serverId ) {
	$serverId = 0;
    }

    my $key = $serverId . "-" . $program;
    my $r_exceptionsFromProgram = $self->{'exceptionData'}->{$key};
    if ( ! defined ) {
        $r_exceptionsFromProgram = [];
        $self->{'exceptionData'}->{$key} = $r_exceptionsFromProgram;
    }
    my $isNewOOM = 1;
    if ( $#{$r_exceptionsFromProgram} > -1 ) {
        my $r_previousException = $r_exceptionsFromProgram->[$#{$r_exceptionsFromProgram}];
        if ( ($time - $r_previousException->{'time'}) < $SINGLE_OOM_TIME ) {
            $r_previousException->{'errorCount'}++;
            $isNewOOM = 0;
        }
    }
    if ( $isNewOOM ) {
        my %javaException = (
            'time' => $time,
            'startTime'  => formatTime($time, $StatsTime::TIME_SQL),
            'server'     => $serverId,
            'program'    => $program,
            'errorCount' => 1
            );
        push @{$r_exceptionsFromProgram}, \%javaException;
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    my @keys = keys %{$self->{'exceptionData'}};
    if ( $#keys < 0 ) {
        return;
    }

    my $bcpFileOOMError = getBcpFileName('enm_oom_error');
    open (BCP, "> $bcpFileOOMError") or die "Failed to open $bcpFileOOMError";
    foreach my $key ( @keys ) {
        foreach my $oomError ( @{$self->{'exceptionData'}->{$key}} ) {
            printf  BCP "%d\t%s\t%d\t%s\t\%dn", $self->{'siteId'}, $oomError->{'startTime'},
                $oomError->{'server'}, $oomError->{'program'}, $oomError->{'errorCount'};
        }
    }
    close BCP;
    dbDo( $dbh, 
	  sprintf("DELETE FROM enm_oom_error WHERE siteid = %d AND date BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
		  $self->{'siteId'}, $self->{'date'}, $self->{'date'})
	) or die "Failed to delete from enm_oom_error".$dbh->errstr."\n";
    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileOOMError' INTO TABLE enm_oom_error" )
        or die "Failed to load new data from '$bcpFileOOMError' file to 'enm_oom_error' table".$dbh->errstr."\n";

    $r_incr->{'OutOfMemory'} = { 'exceptionData' => $self->{'exceptionData'} };
}

1;
