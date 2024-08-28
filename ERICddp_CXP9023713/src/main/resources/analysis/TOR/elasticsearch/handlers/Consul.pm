package Consul;

use strict;
use warnings;

use Data::Dumper;
use JSON;

use StatsDB;
use StatsTime;
use EnmServiceGroup;

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
    $self->{'analysisDir'} = $r_cliArgs->{'analysisDir'};

    if ( exists $r_incr->{'Consul'} ) {
        $self->{'events'} = $r_incr->{'Consul'}->{'events'};
    }
    else {
        $self->{'events'} = [];
    }

    my $r_serverMap = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},"serviceregistry");
    my @programsToFilter = ('consul', 'SAM');

    my @subscriptions = ();
    foreach my $server ( keys %{$r_serverMap} ) {
        foreach my $program ( @programsToFilter ) {
            push @subscriptions, { 'server' => $server, 'prog' => $program };
        }
    }
    push @subscriptions, {'server' => '*', 'prog' => 'hadley'};

    if ( $::DEBUG > 3 ) { print "Consul::init subscriptions=", Dumper(\@subscriptions); }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;

    if ( $::DEBUG > 3 ) { print "Consul::handle got message from $host $program : $message\n"; }

    if ( $program eq 'SAM' ) {
        if ( $message =~ /^INFO\s+Notifying VNF-LAF of failed members \[(\S+)\]/ ) {
            my $membersStr = $1;

            my @members = split(/[\s,]+/, $membersStr);
            my ($dateNTime, $millisec) = getTime($timestamp);
            foreach my $member (@members) {
                if ( (! defined $member) || ($member eq '') ) {
                    $member = 'NA';
                }
                push ( @{$self->{'events'}}, { 'timestamp' => $dateNTime,
                                               'millisec'  => $millisec,
                                               'type'      => 'SAM',
                                               'member'    => $member,
                                               'message'   => 'MemberFailed_VnfLafNotified' } );
            }
        }
    }
    elsif ( $program eq 'consul' ) {
        if ( $message =~ /^\s*consul:\s+member\s+'([^']*)'\s+(.*)/ ) {
            my $member = $1;
            my $messageStr = $2;

            if ( (! defined $member) || ($member eq '') ) {
                $member = 'NA';
            }

            my $message = 'Other';
            if ( $messageStr =~ /failed.*health.*critical/ ) {
                $message = 'MemberFailed_HealthCritical';
            }
            elsif ( $messageStr =~ /left.*deregistering/ ) {
                $message = 'MemberLeft_Deregistering';
            }
            elsif ( $messageStr =~ /joined.*health.*alive/ ) {
                $message = 'MemberJoined_HealthAlive';
            }

            my ($dateNTime, $millisec) = getTime($timestamp);
            push ( @{$self->{'events'}}, { 'timestamp' => $dateNTime,
                                           'millisec'  => $millisec,
                                           'type'      => 'Consul',
                                           'member'    => $member,
                                           'message'   => $message } );
        }
        elsif (( $message =~ /^.*agent.*server:\s+(.*):\s+member=(.*)/ ) ||
              ( $message =~ /^.*agent.server.serf.lan:\s+serf:\s+(.*):\s+(.*)\s+.*/ )) {
            my $member = $2;
            my $messageStr = $1;

            if ( (! defined $member) || ($member eq '') ) {
                $member = 'NA';
            }

            my $message = 'Other';
            if (( $messageStr =~ /member\s+joined,\s+marking\s+health\s+alive/ ) || ( $messageStr =~ /EventMemberJoin/ )) {
                $message = 'MemberJoined_HealthAlive';
            }
            elsif (( $messageStr =~ /deregistering\s+member/ ) || ( $messageStr =~ /EventMemberLeave/ )) {
                $message = 'MemberLeft_Deregistering';
            }

            my ($dateNTime, $millisec) = getTime($timestamp);
            push ( @{$self->{'events'}}, { 'timestamp' => $dateNTime,
                                           'millisec'  => $millisec,
                                           'type'      => 'Consul',
                                           'member'    => $member,
                                           'message'   => $message } );
        }
        elsif ( $message =~ /^.*agent.client.memberlist.lan:\s+memberlist:\s+Suspect\s+(.*)\s+has\s+(.*)/ ) {
            my $member = $2;
            my $messageStr = $1;

            if ( (! defined $member) || ($member eq '') ) {
                $member = 'NA';
            }

            my $message = 'Other';
            if ( $messageStr =~ /failed,\s+no\s+acks\s+received/ ) {
                $message = 'MemberFailed_HealthCritical';
            }

            my ($dateNTime, $millisec) = getTime($timestamp);
            push ( @{$self->{'events'}}, { 'timestamp' => $dateNTime,
                                           'millisec'  => $millisec,
                                           'type'      => 'Consul',
                                           'member'    => $member,
                                           'message'   => $message } );
        }
    }
    elsif ( $program eq 'hadley' ) {
        if ( $severity eq 'err' && $message =~ /Run\s+failed\s+for\s*:\s*\[([^\]]*)\]/ ) {
            my @failedHCs = split(/[\s,]+/, $1);
            my ($dateNTime, $millisec) = getTime($timestamp);

            foreach my $failedHC (@failedHCs) {
                $failedHC =~ s/^\s*['"]\s*|\s*['"]\s*$//g;
                # If the full path of HC script is too long then store the script name alone
                if ( length $failedHC > 128 ) {
                    $failedHC =~ s/^.*\///;
                }
                $message = 'HcFailed: ' . $failedHC;
                push ( @{$self->{'events'}}, { 'timestamp' => $dateNTime,
                                               'millisec'  => $millisec,
                                               'type'      => 'HADley',
                                               'member'    => $host,
                                               'message'   => $message } );
            }
        }
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 7 ) { print Dumper("Consul::done events", $self->{'events'}); }
    $r_incr->{'Consul'} = { 'events' => $self->{'events'} };

    if ( scalar @{$self->{'events'}} == 0 ) {
        return;
    }

    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    # Get server ID map
    my $serverIdMap = getIdMap($dbh, "servers", "id", "hostname", [], $self->{'siteId'});
    if ( $::DEBUG > 9 ) { print Dumper("Consul::done serverIdMap ", $serverIdMap); }

    # Get health check name ID map
    my @healthCheckNames = ();
    foreach my $event (@{$self->{'events'}}) {
        push (@healthCheckNames, $event->{'message'});
    }
    my $hcName2IdMap = getIdMap($dbh, "enm_consul_event_names", "id", "name", \@healthCheckNames);

    # Store 'Consul/SAM Events'
    my $bcpFileConsulEvents = "$tmpDir/enm_consul_n_sam_events.bcp";
    open (BCP, "> $bcpFileConsulEvents");

    foreach my $event (@{$self->{'events'}}) {
        print BCP $self->{'siteId'} . "\t" . $event->{'timestamp'} . "\t" . $event->{'millisec'} .
                  "\t" . $serverIdMap->{$event->{'member'}} . "\t" . $event->{'type'} . "\t" .
                  $hcName2IdMap->{$event->{'message'}} . "\n";
    }

    close BCP;

    dbDo( $dbh, sprintf("DELETE FROM enm_consul_n_sam_events WHERE siteid = %d AND time BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                        $self->{'siteId'}, $self->{'date'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_consul_n_sam_events";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileConsulEvents' INTO TABLE enm_consul_n_sam_events" )
        or die "Failed to load new data from '$bcpFileConsulEvents' file to 'enm_consul_n_sam_events' table";

    unlink($bcpFileConsulEvents);
}

sub getTime($) {
    my ($timestamp) = @_;

    my ($date, $time) = $timestamp =~ /^([\d-]+)T([\d:]+)/;
    my $millisec = 1;
    if ( $timestamp =~ /^[\d-]+T\d+:\d+:\d+\D(\d{1,3})/ ) {
        $millisec = $1;
        $millisec .= "0" x (3 - length($millisec));
    }

    return ("$date $time", $millisec);
}

1;
