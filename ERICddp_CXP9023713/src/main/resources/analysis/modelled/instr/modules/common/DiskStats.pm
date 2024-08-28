package common::DiskStats;

use strict;
use warnings;

use Data::Dumper;

use StatsDB;

#
# handler interface functions
#
sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub prestore($$$$) {
    my ($self,
        $r_cliArgs,$dbh,$r_model,
        $r_dataSets,$r_columnMap) = @_;

    if ( $::DEBUG > 4 ) {
        printf("common::DiskStats::prestore r_model->name=%s r_model->matched_service=%s\n",
               (defined $r_model->{'name'} ? $r_model->{'name'} : "undef" ),
               (defined $r_model->{'matched_service'} ? $r_model->{'matched_service'} : "undef" ));
    }

    if ( $::DEBUG > 9 ) { print Dumper("common::DiskStats::prestore r_dataSets", $r_dataSets); }
    foreach my $r_dataSet ( @{$r_dataSets} ) {
        foreach my $r_sample ( @{$r_dataSet->{'samples'}} ) {
            my $avserv = 0;
            if ( $r_sample->{'rws'} > 0 ) {
                my $ioTimeSec = $r_sample->{'node_disk_io_time_seconds_total'} * $r_sample->{'_rate_duration'} / 100;
                my $ioCount = $r_sample->{'rws'} * $r_sample->{'_rate_duration'};
                $avserv = ($ioTimeSec * 1000) / $ioCount;
            }

            if ( $::DEBUG > 8 ) { print Dumper("common::DiskStats::prestore avgsrv=$avserv r_sample", $r_sample); }
            $r_sample->{'avserv'} = $avserv;
        }
    }

    return $r_dataSets;
}

1;
