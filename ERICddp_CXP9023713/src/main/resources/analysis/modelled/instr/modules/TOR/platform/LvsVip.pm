package TOR::platform::LvsVip;

use strict;
use warnings;

use StatsTime;

use Data::Dumper;

#
# handler interface functions
#
sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub prestore($$$$) {
    my (
        $self,
        $r_cliArgs,
        $dbh,
        $r_model,
        $r_dataSets,
        $r_columnMap
    ) = @_;

    # We only want to store "active" VIPs and only when they "become" active
    # i.e. when the sample value is 1 and the previous value is undefined
    # (first sample of the day) or previous value was zero

    # We want to store the "initial" state for today. That means we need to
    # ignore any sample that are for before the start of today
    my $dayStart = parseTime(
        $r_cliArgs->{'date'} . " 00:00:00",
        $StatsTime::TIME_SQL,
        $StatsTime::TZ_SITE
    );

    my $r_incr = $r_cliArgs->{'_incr'}->{'TOR::platform::LvsVip'};
    if ( ! defined $r_incr ) {
        $r_incr = {};
        $r_cliArgs->{'_incr'}->{'TOR::platform::LvsVip'} = $r_incr;
    }

    my @outputDataSets = ();
    foreach my $r_dataSet ( @{$r_dataSets} ) {
        my $key = sprintf(
            "%s@%s@%s",
            $r_cliArgs->{'server'},
            $r_dataSet->{'properties'}->{'vip'}->{'sourcevalue'},
            $r_dataSet->{'properties'}->{'nicid'}->{'sourcevalue'}
        );
        my $previousValue = $r_incr->{$key};
        if ( $::DEBUG > 5 ) {
            printf(
                "TOR::platform::LvsVip key=%s previousValue=%s\n",
                $key,
                (defined $previousValue ? $previousValue : "undef")
            );
        }
        my @outputSamples = ();
        foreach my $r_sample ( @{$r_dataSet->{'samples'}} ) {
            # Skip the sample if it's not for today
            if ( $r_sample->{'time'} < $dayStart ) {
                if ( $::DEBUG ) {
                    printf("TOR::platform::LvsVip dropping sample for %s %s\n", $key, $r_sample->{'timestamp'});
                }
                next;
            }
            my $currentValue = $r_sample->{'eric_net_l4_master'};
            if (($currentValue == 1) && ((!defined $previousValue) || ($previousValue == 0))) {
                push @outputSamples, $r_sample;
            }
            $previousValue = $currentValue;
        }

        $r_incr->{$key} = $previousValue;

        if ( $#outputSamples > -1 ) {
            $r_dataSet->{'samples'} = \@outputSamples;
            push @outputDataSets, $r_dataSet;
        }
    }

    if ( $::DEBUG > 4 ) { print Dumper("TOR::platform::LvsVip outputDataSets", \@outputDataSets); }

    return \@outputDataSets;
}

1;
