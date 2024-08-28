package ESI;

use strict;
use warnings;

use Data::Dumper;
use JSON;

use StatsDB;
use StatsTime;
use Instr;
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

    if ( exists $r_incr->{'ESI'} ) {
        $self->{'countByEvent'} = $r_incr->{'ESI'}->{'countByEvent'};
    }
    else {
        $self->{'countByEvent'} = {};
    }

    # This required log entries are generated from hosts running msstr instances
    # This every host in the streaming cluster
    my $sql = sprintf("
SELECT
  servers.hostname AS svr
FROM enm_cluster_host, servers
WHERE
 enm_cluster_host.siteid = %d AND
 enm_cluster_host.date = '%s' AND
 enm_cluster_host.serverid = servers.id AND
 enm_cluster_host.clustertype = 'STREAMING'
ORDER BY
enm_cluster_host.nodename",
                      $self->{'siteId'},
                      $self->{'date'}
        );

    my $r_rows = dbSelectAllArr($dbh,$sql);
    my @subscriptions = ();
    foreach my $r_row ( @{$r_rows} ) {
        my $server = $r_row->[0];
        push @subscriptions, { 'server' => $server, 'prog' => 'PMSTREAM' };
        if ( ! exists $self->{'countByEvent'}->{$server} ) {
            $self->{'countByEvent'}->{$server} = {};
        }
    }

    if ( $::DEBUG > 3 ) { print "ESI::init subscriptions=", Dumper(\@subscriptions); }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;

    if ( $::DEBUG > 5 ) { print "ESI::handle got \"$severity\" message from $host:$program message=\"$message\"\n"; }

    if ( $severity ne 'info' ) {
        return;
    }

    if ( $message =~ /^ stream_in_server\@3Event_id_event_count,[\d-]+ [\d:]+,\{([^}]*)\}/ ) {
        my $eventStr = $1;
        my @eventIdCounts = split(/, /, $eventStr);
        if ( $::DEBUG > 3 ) { print Dumper("ESI::handle eventIdCounts",\@eventIdCounts); }
        foreach my $eventIdCount ( @eventIdCounts ) {
            my ($eventId,$eventCount) = $eventIdCount =~ /^(\d+)=(\d+)/;
            my $r_info = $self->{'countByEvent'}->{$host}->{$eventId};
            if ( ! defined $r_info ) {
                $r_info = { 'total' => 0 };
                $self->{'countByEvent'}->{$host}->{$eventId} = $r_info;
            } else {
                if ( $eventCount > $r_info->{'last'} ) {
                    $r_info->{'total'} += $eventCount - $r_info->{'last'};
                } elsif ( $eventCount < $r_info->{'last'} ) {
                    # Assume the host/service restarts and the counter reset to zero
                    $r_info->{'total'} += $eventCount;
                }
            }
            $r_info->{'last'} = $eventCount;
        }
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    if ( $::DEBUG > 3 ) { print Dumper("ESI::done countByEvent",$self->{'countByEvent'}); }

    $r_incr->{'ESI'} = {'countByEvent' => $self->{'countByEvent'}};

    my %totalByEventId = ();
    while ( my ($host,$r_countByEvents) = each %{$self->{'countByEvent'}} ) {
        while ( my ($eventId,$r_info) = each %{$r_countByEvents} ) {
            $totalByEventId{$eventId} += $r_info->{'total'};
        }
    }

    if ( ! %totalByEventId ) {
        return;
    }

    dbDo($dbh,
         sprintf("DELETE FROM enm_esi_eventcounts WHERE siteid = %d AND date = '%s'",
                 $self->{'siteId'}, $self->{'date'})
        ) or return;
    while ( my ($eventId,$eventCount) = each %totalByEventId ) {
        if ( $eventCount > 0 ) {
            dbDo($dbh,
                 sprintf("INSERT INTO enm_esi_eventcounts (siteid,date,eventid,eventcount) VALUES (%d,'%s',%d,%d)",
                         $self->{'siteId'}, $self->{'date'}, $eventId, $eventCount));
        }
    }
}

1;
