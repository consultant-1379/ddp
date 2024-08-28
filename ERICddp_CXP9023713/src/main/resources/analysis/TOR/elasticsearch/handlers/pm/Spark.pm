package pm::Spark;

use strict;
use warnings;

use Data::Dumper;
use JSON;

use StatsDB;
use StatsTime;
use Instr;
use EnmServiceGroup;

#
# Internal functions
#
our %ACC_MAP = (
    'fe' => 'filteredEvents',
    'cr' => 'completeRecords',
    'sr' => 'suspectRecords',
    'krt' => 'kafkaReadTime',
    'iec' => 'inputEventCount',
    'mws' => 'mapWithStateTime',
    'lrs' => 'longRunningSessions',
    'iss' => 'inactiveSuspectSessions',
    'ets' => 'endTriggeredSuspectSessions',
    'sd' => 'sessionDurations',
    'ims' => 'inMemorySessions',
    'owt' => 'outputWriteTime'
    );


sub processStage($) {
    my ($r_stage) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("processStage: r_stage", $r_stage); }

    my %processedStage = (
        'name' => $r_stage->{'name'},
        'duration' => $r_stage->{'comp_time'} - $r_stage->{'sub_time'}
    );

    my $maxDuration = 0;
    my $totalGC = 0;
    my $maxTask = undef;
    my $numTasks = 0;
    my $totalDuration = 0;

    my %accTotals = ();

    foreach my $r_task ( @{$r_stage->{'tasks'}} ) {
        $numTasks++;

        while ( my ($name,$value) = each %{$r_task->{'acc'}}) {
            $accTotals{$name} += $value;
        }

        $totalDuration += $r_task->{'dur'};
        if ( $r_task->{'dur'} > $maxDuration ) {
            $maxDuration = $r_task->{'dur'};
            $maxTask = $r_task;
        }

        $totalGC += $r_task->{'GC'};
    }

    $processedStage{'numtasks'} = $numTasks;

    if ( $numTasks > 0 ) {
        my %accAvg = ();
        while ( my ($name,$value) = each %accTotals) {
            $accAvg{$name} = int($value/$numTasks);
        }
        $processedStage{'task_avg'} = {
            'duration' => int( $totalDuration / $numTasks ),
            'gc' => int ( $totalGC / $numTasks ),
            'accum' => \%accAvg
        };

        $processedStage{'task_max'} = {
            'duration' => $maxDuration,
            'gc' => $maxTask->{'GC'},
            'location' => $maxTask->{'ei'} . "/". $maxTask->{'ix'},
            'accum' => $maxTask->{'acc'}
        };
    }

    if ( $::DEBUG > 5 ) { print Dumper("processStage: processedStage", \%processedStage); }

    return \%processedStage;
}

sub processBatches($$) {
    my ($r_batches,$r_activestages) = @_;

    my @batchIds = sort { $a <=> $b } keys %{$r_batches};
    my $lastBatchId = $batchIds[$#batchIds];

    my @processedBatches = ();

    foreach my $batchId ( @batchIds ) {
        my $r_batch = $r_batches->{$batchId};

        my @jobs = values %{$r_batch->{'jobs'}};
        if ( $#jobs != 0 ) {
            if ( $#jobs > 0 ) {
                print "ERROR: Unknown batch structure: " . Dumper($r_batch);
                return undef;
            } else {
                print "WARN: Zero jobs in batch " . Dumper($r_batch);
                next;
            }
        }

        my $r_job = $jobs[0];

        # Check if the last batch is complete
        if ( $batchId == $lastBatchId ) {
            my $isComplete = 1;
            foreach my $stageId ( keys %{$r_job->{'stages'}} ) {
                if ( exists $r_activestages->{$stageId} ) {
                    $isComplete = 0;
                }
            }

            if ( ! $isComplete ) {
                next;
            }
        }

        # Batch is complete so remove it from r_batches
        delete $r_batches->{$batchId};

        my $batchTime = ($batchId / 1000) + ($r_batch->{'tzoffset'} * 60);
        my %processedBatch = ( 'time' => $batchTime );
        if ( exists $r_batch->{'totalBatchTime'} ) {
            $processedBatch{'duration'} = $r_batch->{'totalBatchTime'};
        }

        while ( my ($stageId,$r_stage) = each %{$r_job->{'stages'}} ) {
            my $r_processedStage = processStage($r_stage);
            if ( $r_processedStage->{'numtasks'} == 0 ) {
                print "WARN: No tasks found for stage $stageId\n";
                next;
            }

            my $stageType = undef;
            if ( $r_processedStage->{'name'} =~ /^mapPartitionsToPair/ ) {
                $stageType = 'in';

                for my $accum ( 'inputEventCount', 'filteredEvents' ) {
                    $processedBatch{'in_' . $accum . '_sum'} = $r_processedStage->{'task_avg'}->{'accum'}->{$accum} * $r_processedStage->{'numtasks'};
                    $processedBatch{'in_' . $accum . '_max'} = $r_processedStage->{'task_max'}->{'accum'}->{$accum};
                }
                foreach my $accum ( 'kafkaReadTime' ) {
                    $processedBatch{'in_' . $accum . '_avg'} = $r_processedStage->{'task_avg'}->{'accum'}->{$accum};
                    $processedBatch{'in_' . $accum . '_max'} = $r_processedStage->{'task_max'}->{'accum'}->{$accum};
                }
            } elsif ( $r_processedStage->{'name'} =~ /^foreachPartition/ ) {
                $stageType = 'proc';

                $processedBatch{'proc_duration_avg'} = $r_processedStage->{'task_avg'}->{'duration'};
                $processedBatch{'proc_duration_max'} = $r_processedStage->{'task_max'}->{'duration'};
                for my $accum ( 'outputWriteTime', 'completeRecords', 'longRunningSessions', 'inMemorySessions', 'suspectRecords',
                                'endTriggeredSuspectSessions', 'inactiveSuspectSessions', 'sessionDurations' ) {
                    if ( exists $r_processedStage->{'task_avg'}->{'accum'}->{$accum} ) {
                        $processedBatch{'proc_' . $accum . '_sum'} = $r_processedStage->{'task_avg'}->{'accum'}->{$accum} * $r_processedStage->{'numtasks'};
                        $processedBatch{'proc_' . $accum . '_max'} = $r_processedStage->{'task_max'}->{'accum'}->{$accum};
                    } else {
                        print "WARNING: missing acc $accum", Dumper($r_processedStage), "\n";
                    }
                }
                for my $accum ( 'outputWriteTime', 'mapWithStateTime' ) {
                    $processedBatch{'proc_' . $accum . '_avg'} = $r_processedStage->{'task_avg'}->{'accum'}->{$accum};
                    $processedBatch{'proc_' . $accum . '_max'} = $r_processedStage->{'task_max'}->{'accum'}->{$accum};
                }
            }

            if ( defined $stageType ) {
                foreach my $key ( 'duration', 'gc' ) {
                    foreach my $agg ( 'avg', 'max' ) {
                        $processedBatch{$stageType . '_' . $key . '_' . $agg} = $r_processedStage->{'task_'. $agg}->{$key};
                    }
                }
                $processedBatch{$stageType . "_partitions"} = $r_processedStage->{'numtasks'};
            }
        }
        if ( $::DEBUG > 5 ) { print Dumper("processBatches processedBatch",\%processedBatch); }

        push @processedBatches, \%processedBatch;
    }

    return \@processedBatches;
}

sub store($$) {
    my ($r_processedBatches,$site) = @_;

    my @cols = ( 'time', 'duration' );

    foreach my $stageType ( 'in', 'proc' ) {
        foreach my $col ( 'duration', 'gc' ) {
            foreach my $agg ( 'avg', 'max' ) {
                push @cols, sprintf("%s_%s_%s", $stageType, $col, $agg);
            }
        }
        push @cols, sprintf("%s_partitions", $stageType);
    }

    foreach my $col ( 'inputEventCount', 'filteredEvents' ) {
        foreach my $agg ( 'sum', 'max' ) {
            push @cols, sprintf("in_%s_%s", $col, $agg);
        }
    }
    foreach my $col ( 'kafkaReadTime' ) {
        foreach my $agg ( 'avg', 'max' ) {
            push @cols, sprintf("in_%s_%s", $col, $agg);
        }
    }

    foreach my $col ( 'completeRecords', 'longRunningSessions', 'inMemorySessions', 'suspectRecords',
                      'endTriggeredSuspectSessions', 'inactiveSuspectSessions', 'sessionDurations' ) {
        push @cols, 'proc_' . $col . '_sum';
        push @cols, 'proc_' . $col . '_max';
    }
    foreach my $col ( 'outputWriteTime', 'mapWithStateTime' ) {
        push @cols, 'proc_' . $col . '_avg';
        push @cols, 'proc_' . $col . '_max';
    }

    my @samples = ();

    #print join(",",@cols), "\n";
    foreach my $r_processedBatch ( @{$r_processedBatches} ) {
        if ( $::DEBUG > 3 ) { print Dumper("r_processedBatch", $r_processedBatch); }
        my %values = ( 'time' => $r_processedBatch->{'time'}, );
        foreach my $colName ( @cols ) {
            my $value = $r_processedBatch->{$colName};
            if ( defined $value ) {
                $values{$colName} = $value;
            }
        }
        push @samples, \%values;
    }
    if ( $::DEBUG > 8 ) { print Dumper("Spark::store samples", \@samples); }

    my %colMap = ();
    foreach my $col ( @cols ) {
        if ( $col ne 'time' ) {
            $colMap{$col} = $col;
        }
    }
    instrStoreData("enm_str_asrl_spark", $site, {}, \@samples, \%colMap);
}

#
# handler interface functions
#
sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self, $r_cliArgs, $r_incr, $dbh) = @_;

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    if ( exists $r_incr->{'Spark'} ) {
        $self->{'batches'} = $r_incr->{'Spark'}->{'batches'};
        $self->{'stage_tasks'} = $r_incr->{'Spark'}->{'stage_tasks'};
        if ( exists $r_incr->{'Spark'}->{'missing_stages'} ) {
            $self->{'missing_stages'} = $r_incr->{'Spark'}->{'missing_stages'};
        } else {
            $self->{'missing_stages'} = {};
        }
    }
    else {
        $self->{'batches'} = {};
        $self->{'stage_tasks'} = {};
        $self->{'missing_stages'} = {};
    }

    my $r_serverMap = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},"sparkworkerdef");
    my @subscriptions = ();
    foreach my $server ( keys %{$r_serverMap} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'asrl-driver' };
    }

    if ( $::DEBUG > 3 ) { print "Spark::init subscriptions=", Dumper(\@subscriptions); }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;

    if ( $::DEBUG > 3 ) { print "Spark::handle got message from $host $program\n"; }

    my $r_event = undef;
    eval {
        $r_event = decode_json($message);
    };
    if ( ! defined $r_event ) {
        print "WARN: Failed to decode $message\n";
        return;
    }

    my $eventType = $r_event->{'eventType'};
    if ( ! defined $eventType ) {
        print "WARN: No eventType in $message\n";
        return;
    }

    if ( $::DEBUG > 4 ) { print "Spark::handle eventType=$eventType\n"; }

    if ( $eventType eq 'batch' ) {
        my $r_batch = $self->{'batches'}->{$r_event->{'batchId'}};
        if ( ! defined $r_batch ) {
            $r_batch = { 'jobs' => {}, 'tzoffset' => getEsTzOffset($timestamp) };
            $self->{'batches'}->{$r_event->{'batchId'}} = $r_batch;
        }
        foreach my $field ( 'schedulingDelay', 'processingEndTime', 'numRecords', 'processingStartTime', 'totalBatchTime', 'batchProcessingTime', 'subTime' ) {
            $r_batch->{$field} = $r_event->{$field};
        }
    } elsif ( $eventType eq 'job' ) {
        my $r_batch = $self->{'batches'}->{$r_event->{'batchId'}};
        if ( ! defined $r_batch ) {
            $r_batch = { 'jobs' => {}, 'tzoffset' => getEsTzOffset($timestamp) };
            $self->{'batches'}->{$r_event->{'batchId'}} = $r_batch;
        }
        my $r_job = {
            'status' => $r_event->{'status'},
            'stages' => {}
        };

        # Normally, the order of logging is
        # 1. stage
        # 2. job
        # 3. stage
        # However, this not always the case
        while ( my ($stageId,$r_stageInfo) = each %{$r_event->{'stages'}} ){
            delete $r_stageInfo->{'stageId'};
            $r_job->{'stages'}->{$stageId} = $r_stageInfo;
            my $r_stageTasks = delete $self->{'stage_tasks'}->{$stageId};
            if ( ! defined $r_stageTasks ) {
                if ( $::DEBUG > 0 ) { print "Spark::handle missing tasks for stage $stageId\n"; }
                $self->{'missing_stages'}->{$stageId} = $r_stageInfo;
            } else {
                $r_stageInfo->{'tasks'} = $r_stageTasks;
            }
        }
        if ( $::DEBUG > 8 ) { print Dumper("Spark::handle created r_job", $r_job); }
        $r_batch->{'jobs'}->{$r_event->{'jobId'}} = $r_job;
    } elsif ( $eventType eq 'stage' ) {
        my $stageId = $r_event->{'stageId'};
        my @tasks = ();
        while ( my ($taskId,$r_taskInfo) = each %{$r_event->{'tasks'}} ) {
            # TORF-244496 map the "compacted" accumulator names back to the full names
            while ( my ($from,$to) = each %ACC_MAP ) {
                my $value = delete $r_taskInfo->{'acc'}->{$from};
                if ( defined $value ) {
                    $r_taskInfo->{'acc'}->{$to} = $value;
                }
            }

            if ( $::DEBUG > 8 ) { print Dumper("Spark::handle tasks", \@tasks); }
            push @tasks, $r_taskInfo;
        }

        if ( exists $self->{'missing_stages'}->{$stageId} ) {
            if ( $::DEBUG > 0 ) { print "Spark::handle found missing stage $stageId\n"; }
            my $r_stageInfo = delete $self->{'missing_stages'}->{$stageId};
            $r_stageInfo->{'tasks'} = \@tasks;
        } else {
            if ( $::DEBUG > 4 ) { print "Spark::handle stored tasks for stage $stageId\n"; }
            $self->{'stage_tasks'}->{$stageId} = \@tasks;
        }
    } else {
        print "WARN: Unknown event type $eventType in $message\n";
        return;
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self, $dbh, $r_incr) = @_;

    my @batchIds = keys %{$self->{'batches'}};
    if ( $#batchIds == -1 ) {
        return;
    }

    my $r_processedBatches = processBatches($self->{'batches'},$self->{'activestages'});
    if ( defined $r_processedBatches ) {
        store($r_processedBatches,$self->{'site'});
    }

    $r_incr->{'Spark'} = {
        'batches' => $self->{'batches'},
        'stage_tasks' => $self->{'stage_tasks'},
        'missing_stages' => $self->{'missing_stages'}
    };
}

1;
