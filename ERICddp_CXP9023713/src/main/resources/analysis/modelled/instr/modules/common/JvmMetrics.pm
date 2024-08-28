package common::JvmMetrics;

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
        printf("common::JvmMetrics::prestore r_model->name=%s r_model->matched_service=%s\n",
               (defined $r_model->{'name'} ? $r_model->{'name'} : "undef" ),
               (defined $r_model->{'matched_service'} ? $r_model->{'matched_service'} : "undef" ));
    }

    #
    # If there is only a single genjmx model instance and then model instance
    # has a value for matched_service, then use that for the jmxname
    #
    my $jmxname = undef;
    my $r_instancesForServer = $r_model->{'modeldef'}->{'instances'}->{$r_cliArgs->{'server'}};
    my $genjmxInstanceCount = $#{$r_instancesForServer} + 1;
    if ( ($genjmxInstanceCount == 1) && (defined $r_model->{'matched_service'}) ) {
        $jmxname = $r_model->{'matched_service'};
    } else {
        ($jmxname) = $r_model->{'name'} =~ /\@genjmx_(\S+)/;
    }

    if ( $::DEBUG > 4 ) { print "common::JvmMetrics::prestore jmxname = $jmxname, genjmxInstanceCount=$genjmxInstanceCount\n"; }

    $r_dataSets->[0]->{'properties'}->{'nameid'} = { 'sourcevalue' => $jmxname };

    return $r_dataSets;
}

1;
