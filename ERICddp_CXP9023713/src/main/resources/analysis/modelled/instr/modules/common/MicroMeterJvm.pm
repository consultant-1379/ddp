package common::MicroMeterJvm;

use strict;
use warnings;

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
    my ($self,
        $r_cliArgs,$dbh,$r_model,
        $r_dataSets,$r_columnMap) = @_;

    if ( $::DEBUG > 4 ) {
        printf("common::MicroMeterJvm::prestore r_model->name=%s r_model->matched_service=%s\n",
               (defined $r_model->{'name'} ? $r_model->{'name'} : "undef" ),
               (defined $r_model->{'matched_service'} ? $r_model->{'matched_service'} : "undef" ));
    }

    foreach my $r_dataSet ( @{$r_dataSets} ) {
        foreach my $r_sample ( @{$r_dataSet->{'samples'}} ) {
            if ( $::DEBUG > 9 ) { print Dumper("common::MicroMeterJvm::prestore org sample", $r_sample); }
            my %aggValues = ();
            foreach my $name ( keys %{$r_sample} ) {
                if ( $name =~ /(\S+):([^-]+)-(.*)/ ) {
                    my ($area,$id,$metric) = ($1,$2,$3);
                    my $value = delete $r_sample->{$name};
                    my $aggName = $area . '-' . $metric;
                    if ( ! exists $aggValues{$aggName} ) {
                        $aggValues{$aggName} = $value;
                    } else {
                        $aggValues{$aggName} += $value;
                    }
                }
            }
            while ( my ($name,$value) = each %aggValues ) {
                $r_sample->{$name} = $value;
            }
            if ( $::DEBUG > 9 ) { print Dumper("common::MicroMeterJvm::prestore agg sample", $r_sample); }
        }
        delete $r_dataSet->{'properties'}->{'id'};
        delete $r_dataSet->{'properties'}->{'cause'};
    }

    # Update the column map, replace the non-agg entries with the single agg entry
    if ( $::DEBUG > 9 ) { print Dumper("common::MicroMeterJvm::prestore org columnMap", $r_columnMap); }
    foreach my $name ( keys %{$r_columnMap} ) {
        if ( $name =~ /(\S+):([^-]+)-(.*)/ ) {
            my ($area,$id,$metric) = ($1,$2,$3);
            my $value = delete $r_columnMap->{$name};
            my $aggName = $area . '-' . $metric;
            $r_columnMap->{$aggName} = $value;
        }
    }
    if ( $::DEBUG > 9 ) { print Dumper("common::MicroMeterJvm::prestore agg columnMap", $r_columnMap); }

    return $r_dataSets;
}

1;
