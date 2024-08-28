package DupDataSet;

use strict;
use warnings;

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
        $site, $r_model, $r_srvIds, $r_events, $r_hostToSg, $r_incr, $date
    ) = @_;

    my @outEvents = ();
    foreach my $r_event ( @{$r_events} ) {
        push @outEvents, $r_event;
        push @outEvents, $r_event;
    }

    return \@outEvents;
}

1;
