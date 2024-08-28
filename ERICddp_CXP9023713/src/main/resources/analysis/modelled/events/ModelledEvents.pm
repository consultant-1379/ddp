package ModelledEvents;

use strict;
use warnings;

use Carp;
use Storable qw(dclone);
use Data::Dumper;
use File::Basename;
use Module::Load;

use StatsTime;
use DataStore;

use lib dirname(__FILE__) . "/modules";

our $CONVERT_TO_STRING = "tostring";
our $EMPTY_KEY = '';

# Process and store the events for a model
#
# This must only be called when there are events available
#
sub processModelEvents($$$$$$$) {
    my ($site, $r_model, $r_srvIds, $r_events, $r_hostToSg, $r_incr, $date) = @_;
    if ( $::DEBUG > 3 ) { printf "processModelEvents: table=%s\n", $r_model->{'table'}->{'name'}; }
    if ( $::DEBUG > 6 ) { print Dumper("processModelEvents: model", $r_model); }

    my $r_hookModuleInstance = undef;
    if ( exists $r_model->{'hooks'} ) {
        my $hookModule = $r_model->{'hooks'}->{'module'};
        if ( ! exists $::hookModules{$hookModule} ) {
            load $hookModule;
            $::hookModules{$hookModule} = $hookModule->new();
        }
        $r_hookModuleInstance = $::hookModules{$hookModule};
    }

    if ( defined $r_hookModuleInstance && exists $r_model->{'hooks'}->{'preprocess'} ) {
        $r_events = $r_hookModuleInstance->preprocess($site, $r_model, $r_srvIds, $r_events, $r_hostToSg, $r_incr, $date);
    }

    if ( exists $r_model->{'merge'} ) {
        $r_events = merge($r_model, $r_events, $r_incr);
    }

    my $r_eventsInfo = $r_model->{'events'};

    #
    # Check if we have events with the explode_array attribute
    #
    my %explodeArrays = ();
    foreach my $r_eventInfo ( values %{$r_eventsInfo} ) {
        my $explode_array = $r_eventInfo->{'explode_array'};
        if ( defined $explode_array ) {
            $explodeArrays{$r_eventInfo->{'name'}} = $explode_array;
        }
    }
    if ( $::DEBUG > 5 ) { print Dumper("ModelledEvents::processModelEvents explodeArrays", \%explodeArrays); }
    #
    # If we have events with the explode_array, convert each array element
    # into it's own event
    #
    if ( %explodeArrays ) {
        my @explodedEvents = ();
        foreach my $r_event ( @{$r_events} ) {
            my $explode_array = $r_eventsInfo->{$r_event->{'name'}}->{'explode_array'};
            if ( defined $explode_array ) {
                my $r_explodedEvents = explodeEvent($r_event, $explode_array);
                foreach my $r_explodedEvent ( @{$r_explodedEvents} ) {
                    push @explodedEvents, $r_explodedEvent;
                }
            } else {
                push @explodedEvents, $r_event;
            }
        }
        $r_events = \@explodedEvents;
        if ( $#{$r_events} == -1 ) {
            print "WARN: Events list is empty after explode\n";
            return;
        }
    }

    if ( exists $r_model->{'aggregate'} ) {
        $r_events = aggregate($r_model, $r_events, $r_hostToSg, $r_incr, $date);
        if ( ! defined $r_events ) {
            printf("WARN: aggregate failed for table=%s\n", $r_model->{'table'}->{'name'});
            return;
        }
    }

    my %filterValueMap = ();
    my %filterIdleMetrics = ();

    #
    # We support mulitple source metrics mapping to the same
    # target column, but DataStore/Instr do not. So here, we
    # need to merge metrics with the same target
    #
    my %sourceByTarget = ();
    foreach my $r_eventInfo ( values %{$r_eventsInfo} ) {
        foreach my $r_metric ( @{$r_eventInfo->{'metric'}} ) {
            my $r_sources = $sourceByTarget{$r_metric->{'target'}};
            if ( ! defined $r_sources ) {
                $r_sources = [];
                $sourceByTarget{$r_metric->{'target'}} = $r_sources;
            }
            push @{$r_sources}, $r_metric->{'source'};

            my $filterValue = $r_metric->{'filtervalue'};
            if ( defined $filterValue ) {
                if ( ! exists $filterValueMap{$r_eventInfo->{'name'}} ) {
                    $filterValueMap{$r_eventInfo->{'name'}} = {};
                }
                $filterValueMap{$r_eventInfo->{'name'}}->{$r_metric->{'source'}} = $filterValue;
            }

            my $filterIdle = $r_metric->{'filteridle'};
            if ( defined $filterIdle && $filterIdle eq 'true') {
                $filterIdleMetrics{$r_metric->{'source'}} = 1;
            }
        }
    }

    my %multiSourceTargets = ();
    while ( my ($target, $r_sources) = each %sourceByTarget ) {
        if ( $#{$r_sources} > 0 ) {
            if ( $::DEBUG > 3 ) { print Dumper("processModelEvents: multiSource target $target, sources", $r_sources); }

            $multiSourceTargets{$target} = '_' . $target;
        }
    }

    if ( $::DEBUG > 3 ) { print Dumper("processModelEvents: filterValueMap", \%filterValueMap); }
    if (%filterValueMap) {
        $r_events = filterEvents(\%filterValueMap, $r_events);
    }

    #
    # Drop idle events
    #
    if ( $::DEBUG > 3 ) { print Dumper("processModelEvents: filterIdleMetrics", \%filterIdleMetrics); }
    if ( %filterIdleMetrics ) {
        my @metricList = keys %filterIdleMetrics;
        $r_events = filterIdleEvents(\@metricList, $r_events);
    }

    # Make sure we still have events after the filtering
    if ( $#${r_events} == -1 ) {
        return;
    }

    # Construct a hash of metrics that are reference values (i.e. use a lookup table)
    # Any metric of this type has to have it's value validated
    my %keyCols = ();
    foreach my $r_keycol ( @{$r_model->{'table'}->{'keycol'}} ) {
        $keyCols{$r_keycol->{'name'}} = 1;
    }
    my %referenceMetrics = ();
    foreach my $r_eventInfo ( values %{$r_eventsInfo} ) {
        foreach my $r_metric ( @{$r_eventInfo->{'metric'}} ) {
            if ( exists $keyCols{$r_metric->{'target'}} ) {
                $referenceMetrics{$r_metric->{'source'}} = 1;
            }
        }
    }

    my @storableEvents = ();
    foreach my $r_event ( @{$r_events} ) {
        my $r_eventInfo = $r_eventsInfo->{$r_event->{'name'}};
        my ($r_storableEvent, $criticalError) = processOneModelEvent($r_event, $r_eventInfo, \%referenceMetrics, \%multiSourceTargets, $r_model, $r_hostToSg);
        if ( defined $r_storableEvent ) {
            push @storableEvents, $r_storableEvent;
        } elsif ( $criticalError ) {
            print "WARN: No data will be stored for this table: $r_model->{'table'}->{'name'}\n";
            return;
        }
    }

    # If the events are part of a merge set, then they may not be in time order
    if ( exists $r_model->{'merge'} ) {
        @storableEvents = sort { $a->{'time'} <=> $b->{'time'} } @storableEvents;
    }

    if ( $::DEBUG > 7 ) {
        print Dumper("processModelEvents: storableEvents", \@storableEvents);
    }

    my %propertyValues = (
        'site' => $site,
    );

    my %columnMap = ();
    while ( my ($target, $source) = each %multiSourceTargets ) {
        $columnMap{$source} = $target;
    }
    foreach my $r_eventInfo ( values %{$r_eventsInfo} ) {
        foreach my $r_metric ( @{$r_eventInfo->{'metric'}} ) {
            if ( ! exists $multiSourceTargets{$r_metric->{'target'}} ) {
                $columnMap{$r_metric->{'source'}} = $r_metric->{'target'};
            }
        }
        foreach my $r_property ( @{$r_eventInfo->{'properties'}} ) {
            $columnMap{$r_property->{'name'}} = $r_property->{'name'};
        }
    }

    $columnMap{'time'} = $r_model->{'table'}->{'timecol'};

    DataStore::storeIrregularData($r_model->{'table'},
                                  undef,
                                  undef,
                                  \%propertyValues,
                                  \%columnMap,
                                  [ { 'samples' => \@storableEvents, 'properties' => {} } ]
                              );
}

#
# Process one event
#
# Process one log event and returns a storable event
#
sub processOneModelEvent($$$$$$) {
    my ($r_event, $r_eventInfo, $r_referenceMetrics, $r_multiSourceTargets, $r_model, $r_hostToSg) = @_;

    my %storableEvent = ();

    if ( $::DEBUG > 9 ) { print Dumper("processOneModelEvent: r_event", $r_event); }

    foreach my $r_metric ( @{$r_eventInfo->{'metric'}} ) {
        my $sourceName = $r_metric->{'source'};

        my $sourceValue = undef;
        # Normal case, source value is a field in the input event
        if ( defined $r_event->{'data'}->{$sourceName} ) {
            $sourceValue = $r_event->{'data'}->{$sourceName};
        # These are pseudo sources
        } elsif ( $sourceName eq 'host' ) {
            $sourceValue = $r_event->{'host'};
        } elsif ( $sourceName eq 'eventname' ) {
            $sourceValue = $r_event->{'name'};
        } elsif ( $sourceName eq 'servicegroup' ) {
            $sourceValue = $r_hostToSg->{$r_event->{'host'}};
        } elsif ( $sourceName eq 'eventtime' ) {
            $sourceValue =
                formatTime(
                    parseTime( $r_event->{'timestamp'}, $StatsTime::TIME_ELASTICSEARCH_MSEC ),
                    $StatsTime::TIME_SQL );
        }
        if ( $::DEBUG > 9 ) { print Dumper("processOneModelEvent: r_metric", $r_metric, $sourceValue); }

        if ( defined $sourceValue ) {
            # Perform any defined scaling
            my $scaleValue = $r_metric->{'scale'};
            if ( defined $scaleValue && $sourceValue ne 'NA' ) {
                $sourceValue = $sourceValue / $scaleValue;
            }

            # Perform any required conversion
            my $conversion = $r_metric->{'convert'};
            if ( defined $conversion ) {
                $sourceValue = convertMetric($sourceValue,$conversion);
            }

            # Validate that any reference values (i.e. use lookup tables)
            # do not end with spaces
            if ( exists $r_referenceMetrics->{$sourceName} ) {
                if ( $sourceValue =~ /\s+$/ ) {
                    print "WARN: Trailing spaces found for $sourceName\n";
                    print Dumper($r_event);
                    return (undef, 1);
                }
            }

            # Handle multi src metrics, multisource targets are mapped to pseudo source
            if ( exists $r_multiSourceTargets->{$r_metric->{'target'}} ) {
                $sourceName = $r_multiSourceTargets->{$r_metric->{'target'}};
            }

            $storableEvent{$sourceName} = $sourceValue;
        }
    }

    my $timeField = $r_eventInfo->{'time'};
    if ( $timeField eq 'timestamp' ) {
        my $parsedTime = parseTimeSafe( $r_event->{'timestamp'}, $StatsTime::TIME_ELASTICSEARCH_MSEC );
        if ( ! defined $parsedTime ) {
            return (undef, 1);
        }
        $storableEvent{'time'} = $parsedTime;
        $storableEvent{'timestamp'} = formatTime( $storableEvent{'time'}, $StatsTime::TIME_SQL );
    } else {
        my $timeFieldValue = $r_event->{'data'}->{$timeField};
        if ( defined $timeFieldValue && $timeFieldValue ne '' ) {
            my $parsedTime = parseTimeSafe( $timeFieldValue, $StatsTime::TIME_SQL );
            if ( ! defined $parsedTime ) {
                return (undef, 1);
            }
            $storableEvent{'time'} = $parsedTime;
            $storableEvent{'timestamp'} = $timeFieldValue;
        } else {
            print "WARN: No value for $timeField in event @ $r_event->{'timestamp'}\n";
            # For merged events, the event with the time field might not have occurred yet
            return (undef, 0);
        }
    }

    foreach my $r_property ( @{$r_eventInfo->{'properties'}} ) {
        if ( $r_property->{'type'} eq 'fixedproperty' ) {
            $storableEvent{$r_property->{'name'}} = $r_property->{'value'};
        }
    }

    return (\%storableEvent, 0);
}

#
# Return only the events that match the filter
#
sub filterEvents($$) {
    my ($r_filterValueMapByEventName, $r_inEvents) = @_;

    my @outEvents = ();
    foreach my $r_event ( @{$r_inEvents} ) {
        my $match = 1;
        my $r_filterValueMap = $r_filterValueMapByEventName->{$r_event->{'name'}};
        if ( defined $r_filterValueMap ) {
            while ( my ($name,$filterValue) = each %{$r_filterValueMap} ) {
                my $eventValue = $r_event->{'data'}->{$name};
                my $thisMatch = (defined $eventValue) && ($eventValue =~ /$filterValue/);
                $match = $match & $thisMatch;
                if ( $::DEBUG > 9 ) { print "filterEvents: match=$match thisMatch=$thisMatch value=", (defined $eventValue ? $eventValue : "undef"), "\n"; }
            }
        }

        if ( $match ) {
            push @outEvents, $r_event;
        }
    }

    return \@outEvents;
}

#
# Return only the events that have at least one none zero metric value
#
sub filterIdleEvents($$) {
    my ($r_filterIdle, $r_inEvents) = @_;

    my @outEvents = ();
    my $firstEvent = 1;
    foreach my $r_event ( @{$r_inEvents} ) {
        if ( $::DEBUG > 9 ) { print Dumper("filterIdleEvents: r_event", $r_event); }
        my $active = 0;
        foreach my $metric ( @{$r_filterIdle} ) {
            my $eventValue = $r_event->{'data'}->{$metric};
            $active = (defined $eventValue) && ($eventValue != 0);
            if ( $active ) {
                if ( $::DEBUG > 8 ) { printf "filterIdleEvents: active metric=%s eventValue=%d\n", $metric, $eventValue; }
                last;
            }
        }
        if ( $active || $firstEvent ) {
            push @outEvents, $r_event;
        }
        $firstEvent = 0;
    }

    if ( $::DEBUG > 4 ) { print Dumper("filterIdleEvents: outEvents", \@outEvents); }
    return \@outEvents;
}

#
# Merge events that have the same values for the groupby fields
#
sub merge($$$) {
    my ($r_model,$r_events,$r_incr) = @_;

    my $r_groupNames = $r_model->{'merge'}->{'groupby'};

    my $r_mergedByKey = {};
    my $r_mergedEvents = [];

    my $r_merged = delete $r_incr->{'merge'}->{$r_model->{'table'}->{'name'}};
    if ( ! defined $r_merged ) {
        $r_mergedByKey = $r_merged->{'bykey'};
        $r_mergedEvents = $r_merged->{'seq'};
    }

    foreach my $r_event ( @{$r_events} ) {
        if ( $::DEBUG > 9 ) { print Dumper("ModelledEvents::merge: r_event", $r_event); }

        my @groupValues = ();
        my $validGroup = 1;
        foreach my $groupBy ( @{$r_groupNames} ) {
            my $value = $r_event->{'data'}->{$groupBy};
            if ( defined $value ) {
                push @groupValues, $value;
            } else {
                print "WARN: merged event does not have value for $groupBy\n";
                last;
            }
            if ( $#groupValues == $#{$r_groupNames} ) {
                my $key = join(":", @groupValues);
                my $r_mergedEvent = $r_mergedByKey->{$key};
                if ( ! defined $r_mergedEvent ) {
                    if ( $::DEBUG > 6 ) { print "ModelledEvents::merged creating new key for key $key\n"; }

                    $r_mergedEvent = {
                        'timestamp' => $r_event->{'timestamp'},
                        'name' => '_MERGED',
                        'host' => $r_event->{'host'},
                        'data' => {}
                    };

                    # Add the group by fields as data
                    foreach my $groupBy ( @{$r_groupNames} ) {
                        $r_mergedEvent->{'data'}->{$groupBy} = $r_event->{'data'}->{$groupBy};
                    }

                    $r_mergedByKey->{$key} = $r_mergedEvent;
                    push @{$r_mergedEvents}, $r_mergedEvent;
                }

                my $r_mergedData = $r_mergedEvent->{'data'};
                $r_mergedData->{$r_event->{'name'} . "._timestamp"} = $r_event->{'timestamp'};
                while ( my ($name,$value) = each %{$r_event->{'data'}} ) {
                    # Only add the field if it's not one of the groupby values
                    if ( ! exists $r_mergedData->{$name} ) {
                        $r_mergedData->{$r_event->{'name'} . "." . $name} = $value;
                    }
                }
            }
        }
    }

    if ( $::DEBUG > 8 ) { print Dumper("ModelledEvents::merge: r_mergedEvents", $r_mergedEvents); }

    $r_incr->{'merge'}->{$r_model->{'table'}->{'name'}} = {
        'bykey' => $r_mergedByKey,
        'seq' => $r_mergedEvents
    };

    return $r_mergedEvents;
}

sub aggregate($$$$$) {
    my ($r_model, $r_events, $r_hostToSg, $r_incr, $date) = @_;

    $#{$r_events} > -1 or confess "aggregate should not be called with an empty r_events";

    my $r_groupNames = $r_model->{'aggregate'}->{'groupby'};
    my $r_aggregations = $r_model->{'aggregate'}->{'aggregations'};
    my $interval = $r_model->{'aggregate'}->{'interval'};

    my $currentGroupTimestamp = parseTime( "$date 00:00:00", $StatsTime::TIME_SQL);
    my $nextGroupTimestamp = $currentGroupTimestamp + ($interval * 60);

    my @results = ();
    my $r_groups = undef; # Groups for the current time interval

    # If the previous execution had log entries for the same minute as the first
    # log entry in this execution, then we need to include those
    my $r_prevAgg = delete $r_incr->{'aggregate'}->{$r_model->{'table'}->{'name'}};
    if ( defined $r_prevAgg ) {
        $currentGroupTimestamp = $r_prevAgg->{'groupTimestamp'};
        $nextGroupTimestamp = $currentGroupTimestamp + ($interval * 60);
        my $firstTime = parseTime( $r_events->[0]->{'timestamp'}, $StatsTime::TIME_ELASTICSEARCH_MSEC );
        if ( $firstTime < $nextGroupTimestamp ) {
            $r_groups = $r_prevAgg->{'groups'};
            foreach my $r_group ( values %{$r_groups} ) {
                push @results, $r_group;
            }
        }
    }

    foreach my $r_event ( @{$r_events} ) {
        if ( $::DEBUG > 9 ) { print Dumper("aggregate: r_event", $r_event); }

        my $time = parseTime( $r_event->{'timestamp'}, $StatsTime::TIME_ELASTICSEARCH_MSEC );
        while ( $time >= $nextGroupTimestamp ) {
            $currentGroupTimestamp = $nextGroupTimestamp;
            if ( $::DEBUG > 7 ) { print "aggregate: new currentGroupTimestamp=$currentGroupTimestamp\n"; }
            $nextGroupTimestamp += ($interval * 60);
            $r_groups = undef;
        }

        if ( ! defined $r_groups ) {
            $r_groups = {};
        }

        my $key = $EMPTY_KEY;
        foreach my $r_groupBy ( @{$r_groupNames} ) {
            my $groupBy = $r_groupBy->{'name'};
            my $mandatory = $r_groupBy->{'mandatory'};
            my $value = undef;

            if ( $groupBy eq 'host' ) {
                $value = $r_event->{'host'};
            } elsif ( $groupBy eq 'servicegroup' ) {
                $value = $r_hostToSg->{$r_event->{'host'}};
            } elsif ( $groupBy eq 'eventname' ) {
                $value = $r_event->{'name'};
            } else {
                $value = $r_event->{'data'}->{$groupBy};
                if ( ! defined $value && $mandatory eq "false" ) {
                    $value = "NA";
                }
            }
            if ( ! defined $value ) {
                print "WARN: Missing groupby value for $groupBy\n";
                return undef;
            }
            if ( $::DEBUG > 8 ) { print "aggregate: groupBy=$groupBy value=$value\n"; }
            $key = $key . ":" . $value;
        }

        if ( $::DEBUG > 8 ) { print "aggregate: key=$key\n"; }
        my $r_group = $r_groups->{$key};
        if ( ! defined $r_group ) {
            my %data = ();
            foreach my $r_groupBy ( @{$r_groupNames} ) {
                my $groupBy = $r_groupBy->{'name'};
                my $value = undef;
                if ( $groupBy ne 'host' && $groupBy ne 'eventname' ) {
                    $data{$groupBy} = $r_event->{'data'}->{$groupBy};
                }
            }
            $r_group = {
                'timestamp' => formatTime( $currentGroupTimestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC ),
                'host' => $r_event->{'host'},
                'name' => $r_event->{'name'},
                'data' => \%data
            };

            $r_groups->{$key} = $r_group;
            push @results, $r_group;
        }
        foreach my $r_aggregation ( @{$r_aggregations} ) {
            if ( $r_aggregation->{'type'} eq 'count' ) {
                $r_group->{'data'}->{$r_aggregation->{'name'}}++;
            } elsif ( $r_aggregation->{'type'} eq 'sum' ) {
                $r_group->{'data'}->{$r_aggregation->{'name'}} += $r_event->{'data'}->{$r_aggregation->{'name'}};
            }
        }
    }
    if ( $::DEBUG > 5 ) { print Dumper("aggregate: results", \@results); }

    $r_incr->{'aggregate'}->{$r_model->{'table'}->{'name'}} = {
        'groupTimestamp' => $currentGroupTimestamp,
        'groups' => $r_groups
    };

    return \@results;
}

sub explodeEvent($$) {
    my ($r_event, $field) = @_;

    if ( $::DEBUG > 9 ) { print Dumper("explodeEvent: r_event", $r_event); }

    my $r_baseEvent = dclone($r_event);

    my $r_array = delete $r_baseEvent->{'data'}->{$field};
    my @results = ();
    foreach my $r_element ( @{$r_array} ) {
        my $r_oneEvent = dclone($r_baseEvent);
        while ( my ($name,$value) = each %{$r_element} ) {
            $r_oneEvent->{'data'}->{$name} = $value;
        }
        push @results, $r_oneEvent;
    }

    if ( $::DEBUG > 5 ) { printf "explodeEvent: returning %d results\n", ($#results+1); }

    return \@results;
}

sub convertMetric($$) {
    my ($metricValue,$conversion) = @_;

    my $result = undef;
    if ( $conversion eq $CONVERT_TO_STRING ) {
        my $type = ref $metricValue;
        #SET data types don't accept spaces in the column
        if ( $type eq 'ARRAY' ) {
            $result = join(",", @{$metricValue});
        } elsif ( $type eq 'HASH' ) {
            my @values = ();
            while ( my ($name,$value) = each %{$metricValue} ) {
                push @values, $name . ": " . $value;
            }
            $result = join(",", @values);
        } else {
            $result = $metricValue;
        }
        if ( $::DEBUG > 8 ) { print Dumper("convertMetric: conversion=$conversion result=$result, metricValue:", $metricValue); }
    } else {
        die "Unknown conversion type $conversion";
    }

    return $result;
}

1;
