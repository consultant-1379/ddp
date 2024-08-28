package common::JvmName;

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
        printf("common::JvmName::prestore r_model->name=%s r_model->matched_service=%s\n",
               (defined $r_model->{'name'} ? $r_model->{'name'} : "undef" ),
               (defined $r_model->{'matched_service'} ? $r_model->{'matched_service'} : "undef" ));
    }

    foreach my $r_dataSet ( @{$r_dataSets} ) {
        my $r_nameProperty = $r_dataSet->{'properties'}->{'nameid'};

        my @jmxNameParts = ();
        if ( defined $r_model->{'matched_service'} ) {
            push @jmxNameParts, $r_model->{'matched_service'};
        }

        if ( $r_nameProperty->{'sourcevalue'} ne '') {
            push @jmxNameParts, $r_nameProperty->{'sourcevalue'};
        }
        my $jmxname = join("-", @jmxNameParts);
        if ( $::DEBUG > 4 ) { print "common::JvmName::prestore jmxname = $jmxname\n"; }

        $r_nameProperty->{'sourcevalue'} = $jmxname;
    }

    return $r_dataSets;
}

1;
