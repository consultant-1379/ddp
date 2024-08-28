package pm::UplinkSpectrum;

use strict;
use warnings;

use Data::Dumper;

use StatsDB;
use StatsTime;
use EnmServiceGroup;

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

    if ( exists $r_incr->{'UplinkSpecturm'} ) {
        $self->{'data'} = $r_incr->{'UplinkSpecturm'};
    } else {
        $self->{'data'} = {
            'resWithErrors' => {},
            'errorMsgTypes' => {}
        }
    }

    $self->{'srvIdMap'} = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},'saservice');
    my @servers = keys %{$self->{'srvIdMap'}};
    # if there are no instances of saservice, then assume this is an old deployment
    # there the feature is part of pmservice
    if ( $#servers == -1 ) {
        $self->{'srvIdMap'} = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},'pmservice');
    }

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }
    if ( $::DEBUG > 5 ) { print Dumper("UplinkSpecturm::init subscriptions",\@subscriptions); }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;

    if ( $::DEBUG > 7 ) { print "UplinkSpecturm::handle got message from $host $program \"$message\"\n"; }

    if ( $severity eq 'err' ) {
        #ERROR [com.ericsson.oss.itpf.ERROR_LOGGER] (Thread-108 (HornetQ-client-global-threads-2061216999)) [PM_52_0222-19383283_u0, Uplink spectrum file collection, ERROR, , SubNetwork=NETSimW,ManagedElement=LTE01dg2ERBS00141,NodeSupport=1,UlSpectrumAnalyzer=1, Uplink spectrum file collection FAILURE: state transition from STARTED to FAILED. Error message: NO_RESOURCES]
        if ( $message =~ /^ERROR\s+\[com\.ericsson\.oss\.itpf\.ERROR_LOGGER\]\s+\([^\]]+\[[^,]+, Uplink spectrum file collection, ERROR, (.*)]/ ) {
            my $errInfo = $1;
            if ( $::DEBUG > 6 ) { print "UplinkSpecturm::handle errInfo=$errInfo\n"; }
            if ( $errInfo =~ /, (\S+), Uplink spectrum file collection.*Error message:\s*(.*)/ ) {
                my ($resource,$errorMsg) = ($1,$2);
                if ( $::DEBUG > 5 ) { print "UplinkSpecturm::handle resource=$resource errorMsg=$errorMsg\n"; }
                $self->{'data'}->{'resWithErrors'}->{$resource}++;
                $self->{'data'}->{'errorMsgTypes'}->{$errorMsg}++;
            }
        }
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    storeErrors($self->{'data'}->{'resWithErrors'},
                $self->{'data'}->{'errorMsgTypes'},
                $self->{'date'},
                $dbh,
                $self->{'siteId'});

    $r_incr->{'UplinkSpecturm'} = $self->{'data'};
}

#
# Internal functions
#
sub storeErrors($$$$$$) {
    my ($resWithErrors, $errorMsgTypes, $date, $dbh, $siteId, $tmpDir) = @_;

    my @resources = keys %{$resWithErrors};
    if ( $#resources > - 1 ) {
        my $bcpFile = getBcpFileName("pm_uplink_errored_res");
        open (BCPRES, "> $bcpFile") or die "Failed to open $bcpFile";
        foreach my $res ( @resources ) {
            print BCPRES "$date\t$siteId\t$res\t$resWithErrors->{$res}\n";
        }
        close BCPRES;

        dbDo( $dbh, "DELETE FROM pm_uplink_errored_res WHERE siteid = $siteId AND date = '$date'" )
            or die "Failed to delete from pm_uplink_errored_res";

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFile' INTO TABLE pm_uplink_errored_res" )
            or die "Failed to load new data from '$bcpFile' file to 'pm_uplink_errored_res' table";
    }

    my @errorMsgs = keys %{$errorMsgTypes};
    if ( $#errorMsgs > -1  ) {
        my $bcpFile = getBcpFileName("pm_uplink_errors");
        open (BCPERRORS, "> $bcpFile") or die "Failed to open $bcpFile";
        foreach my $error ( @errorMsgs ) {
            print BCPERRORS "$date\t$siteId\t$error\t$errorMsgTypes->{$error}\n";
        }
        close BCPERRORS;

        dbDo( $dbh, "DELETE FROM pm_uplink_errors WHERE siteid = $siteId AND date = '$date'" )
            or die "Failed to delete from pm_uplink_errors";

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFile' INTO TABLE pm_uplink_errors" )
            or die "Failed to load new data from '$bcpFile' file to 'pm_uplink_errors' table";
    }
}

1;
