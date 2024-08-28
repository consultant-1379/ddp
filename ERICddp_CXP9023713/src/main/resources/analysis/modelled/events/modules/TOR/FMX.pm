package TOR::FMX;

use strict;
use warnings;

use StatsCommon;
use Data::Dumper;
use Storable qw(dclone);


#
# handler interface functions
#
sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub preprocess($$$$$$$$) {
    my (
        $self,
        $site,
        $r_model,
        $r_srvIds,
        $r_events,
        $r_hostToSg,
        $r_incr,
        $date
    ) = @_;

    if ( $::DEBUG > 3 ) { printf "TOR::FMX::preprocess %s\n", $r_model->{'table'}->{'name'}; }

    #
    # Each FMX instance is generating the same event, we want to remove the duplicates
    # So group by time (with the seconds removed), then for each group, sort the hosts
    # and then the event for the first sorted host. That way we should always select the same host
    # when re-processing
    #
    # Note: fmx_rule will have multiple events per group so the structure of the group is
    # a hash keyed by host where the value is an array of events
    #
    my %eventsByTime = ();
    my @groupTimes = ();
    foreach my $r_event ( @{$r_events} ) {
        if ( $::DEBUG > 8 ) { print Dumper("TOR::FMX::preprocess r_event", $r_event); }
        my ($groupTime) = $r_event->{'timestamp'} =~ /^(\d{4,4}-\d{2,2}-\d{2,2}T\d{2,2}:\d{2,2})/;
        my $r_group = $eventsByTime{$groupTime};
        if ( ! defined $r_group ) {
            if ( $::DEBUG > 3 ) { print "TOR::FMX::preprocess creating group for $groupTime\n"; }
            $r_group = {};
            $eventsByTime{$groupTime} = $r_group;
            push @groupTimes, $groupTime;
        }
        if ( ! exists $r_group->{$r_event->{'host'}} ) {
            $r_group->{$r_event->{'host'}} = [];
        }
        push @{$r_group->{$r_event->{'host'}}}, $r_event;
    }

    my $currentHost = undef;
    my @outEvents = ();
    foreach my $groupTime ( @groupTimes ) {
        my $r_group = $eventsByTime{$groupTime};
        my @sortedHosts = sort keys %{$r_group};
        my $firstHost = $sortedHosts[0];
        if ( $::DEBUG > 7 ) { print Dumper("TOR::FMX::preprocess groupTime=$groupTime, sortedHosts", \@sortedHosts); }
        foreach my $r_event ( @{$r_group->{$firstHost}} ) {
            push @outEvents, $r_event;
        }
        if (defined $currentHost && $currentHost ne $firstHost) {
            print "INFO: TOR::FMX switched host from $currentHost to $firstHost @ $groupTime\n";
        };
        $currentHost = $firstHost;
    }

    return \@outEvents;
}

1;
