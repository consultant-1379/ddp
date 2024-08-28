package PromCommon;

use strict;
use warnings;

use Data::Dumper;

our @DEFAULT_INSTANCE_NAME_LABELS = ( 'pod', 'kubernetes_pod_name', 'pod_name' );

sub getLabelValue($$) {
    my ($r_labels, $r_labelsToCheck) = @_;

    if ($::DEBUG > 10 ) { print Dumper("PromCommon::getLabelValue: r_labelsToCheck", $r_labelsToCheck); }

    foreach my $labelName ( @{$r_labelsToCheck} ) {
        my $value = $r_labels->{$labelName};
        if ( defined $value ) {
            return $value;
        }
    }

    return undef;
}

sub getInstance($$) {
    my ($instance, $r_instanceMap) = @_;

    if ( $::DEBUG > 6 ) { print "PromCommon::getInstance: instance=$instance\n"; }

    my $r_srvInstance = $r_instanceMap->{$instance};
    if ( (! defined $r_srvInstance) && $instance =~ /^([\d\.]+):\d+$/ ) {
        my $ip = $1;
        if ( $::DEBUG > 5 ) { print "PromCommon::getInstance:: ip=$ip\n"; }
        $r_srvInstance = $r_instanceMap->{$ip};
    }
    if ( $::DEBUG > 5 ) { print Dumper("PromCommon::getInstance: r_srvInstance", $r_srvInstance); }
    return $r_srvInstance;
}

sub appendTS($$$$$$$$) {
    my ($r_metricsByInstance, $instance, $profileNameSpace, $group, $metric, $r_ts, $r_labelValues, $model_label_filter) = @_;


    if ( $::DEBUG > 9 ) {
        print "appendTS: group=$group\n";
        print Dumper("appendTS: r_labelValues", $r_labelValues);
        print Dumper("appendTS: model_label_filter", $model_label_filter);
    }

    my $groupKey = $group;
    # If we have labelValues (the values of the labels used during filtering in handlePromMetric)
    # we add the label values to to the group name in the order
    # in which the labels are listed in the model_label_filter
    # Note: lots of warnings where the value is undefined
    if ( defined $r_labelValues && %{$r_labelValues} ) {
        my @values = ();
        foreach my $r_LabelFilter ( @{$model_label_filter} ) {
            if ( $r_LabelFilter->{'addtogroup'} ) {
                my $value = $r_labelValues->{$r_LabelFilter->{'name'}};
                # The filter can be optional so we have to allow the value not being present
                if ( defined $value ) {
                    push @values, $value;
                } else {
                    push @values, "";
                }
            }
        }
        ($groupKey) = $group =~ /^[\^]?([^:]+)/;
        $groupKey .= ":" . join(":", @values);
    }

    my $r_tsData = $r_metricsByInstance->{$instance}->{'metrics'}->{$profileNameSpace}->{$groupKey}->{$metric};
    if ( $::DEBUG > 7 ) { printf("appendTS: instance=%s groupKey=%s metric=%s exists=%s\n", $instance, $groupKey, $metric, (defined $r_tsData ? "yes" : "no")); }

    my $startIndex = 0;
    if ( defined $r_tsData ) {
        # Make sure the data is "newer"
        my $firstNewTimeStamp = $r_ts->{'Timestamps'}->[0];
        my $lastOldTimeStamp = $r_tsData->{'timestamps'}->[$#{$r_tsData->{'timestamps'}}];
        if ( ! defined $lastOldTimeStamp ) {
            print Dumper($r_tsData);
            exit;
        }
        if ( $::DEBUG > 8 ) { print "appendTS: firstNewTimeStamp=$firstNewTimeStamp lastOldTimeStamp=$lastOldTimeStamp\n"; }
        if ( $firstNewTimeStamp < $lastOldTimeStamp ) {
            print "ERROR: Data out of order for instance=$instance profileNameSpace=$profileNameSpace groupKey=$groupKey metric=$metric\n";
            print Dumper("old data", $r_tsData);
            print Dumper("new data", $r_ts);
            exit 1;
        }

        $startIndex = $#{$r_tsData->{'timestamps'}} + 1;

        push @{$r_tsData->{'timestamps'}}, @{$r_ts->{'Timestamps'}};
        push @{$r_tsData->{'values'}}, @{$r_ts->{'Values'}};
    } else {
        $r_tsData = {
            'timestamps' => $r_ts->{'Timestamps'},
            'values' => $r_ts->{'Values'},
        };
        if ( defined $r_labelValues && %{$r_labelValues} ) {
            $r_tsData->{'labels'} = $r_labelValues;
        }
        $r_metricsByInstance->{$instance}->{'metrics'}->{$profileNameSpace}->{$groupKey}->{$metric} = $r_tsData;
    }

    if ( $::DEBUG > 5 ) {
        printf "appendTS: instance=%s metric=%s startIndex=%d total samples=%d #r_ts=%d\n",
            $instance, $metric, $startIndex, $#{$r_tsData->{'timestamps'}} + 1,
            $#{$r_ts->{'Timestamps'}} + 1;
    }

    return $startIndex;
}

1;
