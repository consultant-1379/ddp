package TOR::common::DPS;

use strict;
use warnings;

use Data::Dumper;

use StatsDB;
use Instr;

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

    # We get in multiple providers for each of the main operation types
    # createMo/createMo
    # deleteMo/deleteMo
    # setAttribute
    # findMo/findPo
    # We need to sum the metrics for each type, e.g. sum the methodInvocations for the instances of setAttribute providers
    # 'svc-6-mscm-com.ericsson.oss.itpf.datalayer.dps.bucket.instrumentation.DpsProfiledBean.inbound-dps-handler-code:type=setAttribute(interface com.ericsson.oss.itpf.datalayer.dps.bucket.instrumentation.DpsProfiledBean$VoidExecution)',
    # 'svc-6-mscm-com.ericsson.oss.itpf.datalayer.dps.bucket.instrumentation.DpsProfiledBean.inventory-handler-code:type=setAttribute(interface com.ericsson.oss.itpf.datalayer.dps.bucket.instrumentation.DpsProfiledBean$VoidExecution)',
    # 'svc-6-mscm-com.ericsson.oss.itpf.datalayer.dps.bucket.instrumentation.DpsProfiledBean.notification-receiver-handler-code-handler:type=setAttribute(interface com.ericsson.oss.itpf.datalayer.dps.bucket.instrumentation.DpsProfiledBean$VoidExecution)',
    #'svc-6-mscm-com.ericsson.oss.itpf.datalayer.dps.bucket.instrumentation.DpsProfiledBean.software-sync-handler-code:type=setAttribute(interface com.ericsson.oss.itpf.datalayer.dps.bucket.instrumentation.DpsProfiledBean$VoidExecution)'

    # Get the providers for each type
    if ( $::DEBUG > 4 ) { print Dumper("TOR::common::DPS::prestore r_model metricgroup", $r_model->{'metricgroup'}); }
    my %typesToProviders = ();
    foreach my $providerName ( keys %{$r_model->{'metricgroup'}} ) {
        my ($type) = $providerName =~ /:type=([^\(]+)\(interface/;
        if ( defined $type ) {
            my $r_providerList = $typesToProviders{$type};
            if ( ! defined $r_providerList ) {
                $r_providerList = [];
                $typesToProviders{$type} = $r_providerList;
            }
            push $r_providerList, $providerName;
        }
    }
    if ( $::DEBUG > 4 ) { print Dumper("TOR::common::DPS::prestore r_model typesToProviders", \%typesToProviders); }

    my $r_samples = $r_dataSets->[0]->{'samples'};
    # Aggregate the samples from each type
    my %samplesByType = ();
    foreach my $r_sample ( @{$r_samples} ) {
        if ( $::DEBUG > 8 ) { print Dumper("TOR::common::DPS::prestore r_sample", $r_sample); }
        while ( my ($type,$r_providerList) = each %typesToProviders ) {
            if ( $::DEBUG > 8 ) { print Dumper("TOR::common::DPS::prestore type=$type r_providerList", $r_providerList); }

            my $r_typeSample = { 'time' => $r_sample->{'time'}, 'timestamp' => $r_sample->{'timestamp'}, 'methodInvocations' => 0 };
            if ( $type eq 'findMo' || $type eq 'findPo' ) {
                $r_typeSample->{'executionTimeTotalMillis'} = 0;
            }

            foreach my $providerName ( @{$r_providerList} ) {
                my $key = $providerName . "-methodInvocations";
                my $value = $r_sample->{$key};
                if ( $::DEBUG > 9 ) { printf "TOR::common::DPS::prestore key=%s value=%s\n", $key, (defined $value ? $value : "undef"); }
                if ( defined $value ) {
                    $r_typeSample->{'methodInvocations'} += $value;
                }

                if ( $type eq 'findMo' || $type eq 'findPo' ) {
                    $key = $providerName . "-executionTimeTotalMillis";
                    $value = $r_sample->{$key};
                    if ( defined $value ) {
                        $r_typeSample->{'executionTimeTotalMillis'} += $value;
                    }
                }
            }
            if ( $::DEBUG > 8 ) { print Dumper("TOR::common::DPS::prestore r_typeSample", $r_typeSample); }

            my $r_typeSamples = $samplesByType{$type};
            if ( ! defined $r_typeSamples ) {
                $r_typeSamples = [];
                $samplesByType{$type} = $r_typeSamples;
            }
            push @{$r_typeSamples}, $r_typeSample;
        }
    }

    my $r_groupedSamples = instrGroupDataFunc(\%samplesByType,\&instrJoinMetrics);
    $r_dataSets->[0]->{'samples'} = $r_groupedSamples;

    # Create a columnMap with the correct mapping
    map { delete $r_columnMap->{$_} } keys %{$r_columnMap};
    foreach my $type ( keys %samplesByType ) {
        $r_columnMap->{$type . '-methodInvocations'} = 'n_' . $type;
        if ( $type eq 'findMo' || $type eq 'findPo' ) {
            $r_columnMap->{$type . '-executionTimeTotalMillis'} = 't_' . $type;
        }
    }

    return $r_dataSets;
}

1;
