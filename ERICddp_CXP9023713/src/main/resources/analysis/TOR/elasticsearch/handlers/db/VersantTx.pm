package db::VersantTx;

use strict;
use warnings;

use Data::Dumper;
use Storable qw(dclone);

use StatsDB;
use StatsTime;
use EnmCluster;

#
# Internal functions
#
sub storeldTxStats($$) {
    my ($self,$dbh) = @_;

    my $siteId = $self->{'siteId'};

    # Write DB roll back transactions related data to 'enm_versant_health_checks_ldtx.bcp' file
    my $bcpFile = getBcpFileName("enm_versant_health_checks_ldtx");
    open (BCP, "> $bcpFile");
    foreach my $r_row ( @{$self->{'counts'}} ) {
        print BCP "$siteId\t$r_row->{'serverid'}\t$r_row->{'timestamp'}\t$r_row->{'deadtxcount'}\t$r_row->{'longrunningtxcount'}\n";
    }
    close BCP;

    # Add the Versant health-check related data to database
    # Delete the old stats from 'enm_versant_health_checks_ldtx' table for the given date for the dead & long running tx
    dbDo( $dbh, sprintf("DELETE FROM enm_versant_health_checks_ldtx WHERE siteid = %d AND time BETWEEN '%s' AND '%s'",
                        $siteId,
                        $self->{'counts'}->[0]->{'timestamp'},
                        $self->{'counts'}->[$#{$self->{'counts'}}]->{'timestamp'}) )
        or die "Failed to remove old data from 'enm_versant_health_checks_ldtx' table";

    #  Populate the 'enm_versant_health_checks_ldtx' DB table with the new tx related data
    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFile' INTO TABLE enm_versant_health_checks_ldtx" )
        or die "Failed to load new data from '$bcpFile' file to 'enm_versant_health_checks_ldtx' table";
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

    $self->{'srvIdMap'} = enmClustHostSrv($self->{'site'}, $self->{'date'}, "DB");

    $self->{'counts'} = [];
    if ( exists $r_incr->{'db::VersantTx'} ) {
        push @{$self->{'counts'}}, $r_incr->{'db::VersantTx'};
    }

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'Versant' };
    }

    if ( $::DEBUG > 5 ) { print Dumper("db::VersantTx::init subscriptions",\@subscriptions) ; }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $::DEBUG > 7 ) { print "db::VersantTx::handle timestamp=$timestamp program=$program message=$message\n"; }

    if ( $message =~ /^ Admin: \[WARNING\]: Transaction Info - TID.*Seconds\s*:\s*([^,]+).*Server Pid.Tid\s*:\s*([^,]+)/) {
        if ( $::DEBUG > 5 ) { print "db::VersantTx::handle matched $message\n"; }

        my ( $seconds, $serverPID ) = ($1,$2);

        my $time = formatTime(parseTime($timestamp,$StatsTime::TIME_ELASTICSEARCH_MSEC),
                              $StatsTime::TIME_SQL);
        my $r_row = undef;
        if ( ($#{$self->{'counts'}} > -1) ) {
            my $r_lastrow = $self->{'counts'}->[$#{$self->{'counts'}}];
            if ( $r_lastrow->{'timestamp'} eq $time ) {
                $r_row = $r_lastrow;
            }
        }
        if ( ! defined $r_row ) {
            $r_row = {
                'timestamp' => $time,
                'serverid' => $self->{'srvIdMap'}->{$host},
                'deadtxcount' => 0,
                'longrunningtxcount' => 0
            };
            push @{$self->{'counts'}}, $r_row;
        }

        if ( $serverPID eq "dead" ) {
            $r_row->{'deadtxcount'}++;
        } elsif( $seconds > 600 && ($serverPID =~ m/^\d+.\d+$/) ) {
            $r_row->{'longrunningtxcount'}++;
        }
        if ( $::DEBUG > 5 ) { print Dumper("db::VersantTx::handle r_row", $r_row); }
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}


sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("db::VersantTx::done self", $self); }

    if ( $#{$self->{'counts'}} == -1 ) {
        return;
    }

    $self->storeldTxStats($dbh);

    $r_incr->{'db::VersantTx'} = dclone($self->{'counts'}->[$#{$self->{'counts'}}]);
}

1;
