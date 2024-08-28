package pm::SmrsAuditInst;

use strict;
use warnings;

use Data::Dumper;
use EnmServiceGroup;
use StatsDB;
use StatsTime;
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
    $self->{'r_instrDataEvent'}->{'smrsInstrStats'} = [];

    $self->{'r_serverMap'} = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, 'smrsservice');
    my @programsToFilter = ('JBOSS', 'UNKNOWN');

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'r_serverMap'}}  ) {
        foreach my $program ( @programsToFilter ) {
            push ( @subscriptions, {'server' => $server, 'prog' => $program} );
        }
    }
    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;
    if ( $::DEBUG > 9 ) { print "SmrsAuditInst::handle got message from $host $program : $message\n"; }

    if ( $severity ne 'info' ) {
        return;
    }

    my ($date, $time) = $timestamp =~ m/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2}).*/g;
    my ($timeStr)=$timestamp=~/(\d{2}:\d{2}:\d{2}).*/;

    if ( $::DEBUG > 7 ) { print "SmrsAuditInst::handle got message from $host $program : $message\n"; }

    my $serverid=$self->{'r_serverMap'}->{$host};

    if( $message !~ /SMRS-PMPUSH.*-INSTRUMENTATION.*auditStartTime=([^,]+)/ ) {
        return;
    }

    if( $message =~ /SMRS-PMPUSH-INSTRUMENTATION.*auditStartTime=([^,]+)/ ) {
        my %event = (
            'serverid'                        => $serverid,
            'time'                            => $date . " " . $time,
            'auditStartTime'                  => '\N',
            'neType'                          => '\N',
            'auditProcessingTime'             => '\N',
            'totalNumberOfDirectoriesScanned' => '\N',
            'totalNumberOfDetectedFiles'      => '\N',
            'totalNumberOfMTRsSent'           => '\N',
            'totalBytesTransferred'           => '\N'
        );

        if ($message =~ /neType\s*=\s*([^,]*)\s*/) {
            $event{'neType'} = $1;
            $self->{'neTypes'}->{$1} = $1;
        }

        if ($message =~ /auditStartTime\s*=\s*([\d+]*)\s*/) {
            $event{'auditStartTime'} = $1;
        }

        if ($message =~ /auditProcessingTime\s*=\s*([\d+]*)\s*/) {
            $event{'auditProcessingTime'} = $1;
        }

        if ($message =~ /totalNumberOfDirectoriesScanned\s*=\s*([\d+]*)\s*/) {
            $event{'totalNumberOfDirectoriesScanned'} = $1;
        }

        if ($message =~ /totalNumberOfDetectedFiles\s*=\s*([\d+]*)\s*/) {
            $event{'totalNumberOfDetectedFiles'} = $1;
        }
        if ($message =~ /totalNumberOfMTRsSent\s*=\s*([\d+]*)\s*/) {
            $event{'totalNumberOfMTRsSent'} = $1;
        }
        if ($message =~ /totalBytesTransferred\s*=\s*([\d+]*)\s*/) {
            $event{'totalBytesTransferred'} = $1;
        }
        push @{$self->{'r_instrDataEvent'}->{'smrsInstrStats'}}, \%event;
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 7 ) { print Dumper("SmrsAuditInst::done r_instrDataEvent", $self->{'r_instrDataEvent'}); }

    my $smrsAuditInstrStats  = getBcpFileName("enm_smrsaudit_instr");

    my @neTypes = keys %{$self->{'neTypes'}};
    my @neNames = keys %{$self->{'neNames'}};
    # Get server ID map
    my $serverIdMap = getIdMap($dbh, "servers", "id", "hostname", [], $self->{'siteId'});
    my $neTypesIdMap = getIdMap($dbh, "ne_types", "id", "name", \@neTypes);

    if ( $::DEBUG > 9 ) { print Dumper("SmrsAuditInst::done serverIdMap ", $serverIdMap); }

    if ( $#{$self->{'r_instrDataEvent'}->{'smrsInstrStats'}} != -1 ) {
        open (BCP, ">$smrsAuditInstrStats");
        foreach my $smrsAuditInstrKey (@{$self->{'r_instrDataEvent'}->{'smrsInstrStats'}}) {
            print BCP $self->{'siteId'} . "\t" .
                $smrsAuditInstrKey->{'serverid'} . "\t" .
                $smrsAuditInstrKey->{'time'} . "\t" .
                formatSiteTime($smrsAuditInstrKey->{'auditStartTime'}/1000, $StatsTime::TIME_SQL) . "\t" .
                $neTypesIdMap->{$smrsAuditInstrKey->{'neType'}} . "\t" .
                $smrsAuditInstrKey->{'auditProcessingTime'} . "\t" .
                $smrsAuditInstrKey->{'totalNumberOfDirectoriesScanned'} . "\t" .
                $smrsAuditInstrKey->{'totalNumberOfDetectedFiles'} . "\t" .
                $smrsAuditInstrKey->{'totalNumberOfMTRsSent'} . "\t" .
                $smrsAuditInstrKey->{'totalBytesTransferred'} . "\n";
        }
        close BCP;
        # Store 'SMRS Audit Instr Details'

        dbDo( $dbh, sprintf("DELETE FROM enm_smrsaudit_instr WHERE siteid = %d AND time BETWEEN '%s' AND '%s'",
            $self->{'siteId'}, $self->{'r_instrDataEvent'}->{'smrsInstrStats'}->[0]->{'time'},$self->{'r_instrDataEvent'}->{'smrsInstrStats'}->[$#{$self->{'r_instrDataEvent'}->{'smrsInstrStats'}}]->{'time'}) )
            or die "Failed to delete old data from enm_smrsaudit_instr";

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$smrsAuditInstrStats' INTO TABLE enm_smrsaudit_instr" )
            or die "Failed to load new data from '$smrsAuditInstrStats' file to 'enm_smrsaudit_instr' table";
    }
}

1;

