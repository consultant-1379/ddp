package ParseConfig;

use strict;
use warnings;

use Getopt::Long;
use Data::Dumper;
use DBI;
use JSON;

use StatsDB;
use StatsCommon;
use StatsTime;
use ServerCfg;

# Increasing to 512 as required by ebsflow: TORF-694075
our $MAX_POD_INSTANCE = 512;

# ADP design rule DR-D1121-144
# app.kubernetes.io/name	The name of the ADP service
# ADP design rule DR-D1121-145
# app.kubernetes.io/instance	{{ .Release.Name | quote }}
# i.e. app.kubernetes.io/instance should be the chart name
our @APP_LABELS = ( 'app', 'app.kubernetes.io/name' );
our @FILTERED_APPS = ( 'sep' );

sub processPod($$) {
    my ($r_item, $r_replicaSets) = @_;

    if ( $::DEBUG > 9 ) { print Dumper("processPod: r_item", $r_item); }
    my $r_metadata = $r_item->{'metadata'};
    if ( $::DEBUG > 8 ) { print Dumper("processPod: r_metadata", $r_metadata); }
    my %pod = ( 'name' => $r_metadata->{'name'}, 'uid' => $r_metadata->{'uid'} );
    if ( exists $r_metadata->{'ownerReferences'}->[0]->{'kind'} ) {
        $pod{'kind'} = $r_metadata->{'ownerReferences'}->[0]->{'kind'};
    }

    foreach my $labelName ( @APP_LABELS ) {
        my $labelValue = $r_metadata->{'labels'}->{$labelName};
        if ( defined $labelValue ) {
            $pod{'app'} = $labelValue;
            last;
        }
    }

    if ( exists $r_metadata->{'labels'}->{'app.kubernetes.io/instance'} ) {
        $pod{'app.kubernetes.io/instance'} = $r_metadata->{'labels'}->{'app.kubernetes.io/instance'};
    }

    if ( exists $r_item->{'spec'}->{'nodeName'} ) {
        $pod{'nodeName'} = $r_item->{'spec'}->{'nodeName'};
    }

    my $r_status = $r_item->{'status'};
    if ( $::DEBUG > 8 ) { print Dumper("processPod: r_status", $r_status); }
    if ( exists $r_status->{'podIP'} ) {
        $pod{'podIP'} = $r_status->{'podIP'};
    } else {
        $pod{'podIP'} = undef;
    }

    my $r_ownerRef = undef;
    if ( exists $r_item->{'metadata'}->{'ownerReferences'} &&
        $#{$r_item->{'metadata'}->{'ownerReferences'}} > -1 &&
        exists $r_item->{'metadata'}->{'ownerReferences'}->[0]->{'kind'} ) {
        $r_ownerRef = $r_item->{'metadata'}->{'ownerReferences'}->[0];
    }
    if ( defined $r_ownerRef ) {
        if ( $r_ownerRef->{'kind'} eq 'ReplicaSet' && exists $r_replicaSets->{$r_ownerRef->{'name'}} ) {
            $pod{'replicaset'} = $r_replicaSets->{$r_ownerRef->{'name'}};
        }
    }

    # hash of containers in the pod
    # container name => container uid => start time
    my %containers = ();
    foreach my $r_containerStatus ( @{$r_item->{'status'}->{'containerStatuses'}} ) {
        if ( exists $r_containerStatus->{'containerID'} ) {
            my %container = (
                'containerID' => $r_containerStatus->{'containerID'},
                'restartCount' => $r_containerStatus->{'restartCount'}
            );
            my $startedAt = $r_containerStatus->{'state'}->{'running'}->{'startedAt'};
            if ( defined $startedAt ) {
                $container{'startedAt'} = parseTime($startedAt, $StatsTime::TIME_K8S);
            }
            $containers{$r_containerStatus->{'name'}} = [ \%container ];
        }
    }
    $pod{'containers'} = \%containers;

    if ( $::DEBUG > 6 ) { print Dumper("processPod: pod, item", \%pod, $r_item); }
    return \%pod;
}

sub parsePods($) {
    my ($dir) = @_;

    my $filePath = "$dir/pods.json";
    if ( ! -r $filePath ) {
        return [];
    }

    my $json_text = do {
        open(my $json_fh, "<:encoding(UTF-8)", $filePath)
            or die("Can't open \$filename\": $!\n");
        local $/;
        <$json_fh>
    };

    my $r_json = decode_json($json_text);
    my @pods = ();
    foreach my $r_item ( @{$r_json->{'items'}} ) {
        push @pods, processPod($r_item, {});
    }

    if ( $::DEBUG > 5 ) { print Dumper("parsePods: pods", \@pods); }
    return \@pods;
}

#
# Return array of hash
#  intIP
#  memory
#  cpu
#  hostname
#  kubeletVer
#  role (MASTER/WORKER)
#
sub processNodes($) {
    my ($r_json) = @_;

    my @nodes = ();

    foreach my $r_item ( @{$r_json->{'items'}} ) {
        if ( $::DEBUG > 8 ) { print Dumper("processNodes r_item metadata", $r_item->{'metadata'}); }

        my $hostname = $r_item->{'metadata'}->{'name'};
        if ( ! defined $hostname ) {
            foreach my $r_address ( @{$r_item->{'status'}->{'addresses'}} ) {
                if ( $r_address->{'type'} eq 'Hostname' ) {
                    $hostname = $r_address->{'address'};
                }
            }
        }

        my $role = undef;
        if ( exists $r_item->{'metadata'}->{'labels'}->{'node-role.kubernetes.io/worker'} ) {
            $role = 'WORKER';
        } elsif ( exists $r_item->{'metadata'}->{'labels'}->{'node-role.kubernetes.io/master'} ) {
            $role = 'MASTER'
        } else {
            print "WARNING: unknown node type $hostname, assuming WORKER\n";
            $role = 'WORKER';
        }

        my $r_nodeInfo = $r_item->{'status'}->{'nodeInfo'};
        my $r_capacity = $r_item->{'status'}->{'capacity'};


        my $memory = $r_capacity->{'memory'};
        if ( $memory =~ /(\d+)Ki$/ ) {
            $memory = int($1 / 1024);
        } elsif ( $memory =~ /(\d+)Mi/ ) {
            $memory = $1;
        }
        my %node = (
            'hostname' => $hostname,
            'role' => $role,
            'cpu' => $r_capacity->{'cpu'},
            'memory' => $memory,
            'kubeletVer' => $r_nodeInfo->{'kubeletVersion'}
        );

        foreach my $r_address ( @{$r_item->{'status'}->{'addresses'}} ) {
            if ( $r_address->{'type'} eq 'InternalIP' ) {
                $node{'intIP'} = $r_address->{'address'};
            }
        }

        push @nodes, \%node;
    }

    if ( $::DEBUG > 5 ) { print Dumper("processNodes: nodes", \@nodes); }
    return \@nodes;
}

sub getCcdVer($) {
    my ($r_json) = @_;

    foreach my $r_item ( @{$r_json->{'items'}} ) {
        my $ccdVer = $r_item->{'metadata'}->{'labels'}->{'ccd/version'};
        if ( defined $ccdVer ) {
            if ( $::DEBUG > 5 ) { print "getCcdVer: $ccdVer\n"; }
            return $ccdVer;
        }
    }

    return undef;
}

sub parseNodes($) {
    my ($dir) = @_;

    my $nodesFile = "$dir/nodes.json";
    if ( ! -r $nodesFile ) {
        return [];
    }

    my $json_text = do {
        open(my $json_fh, "<:encoding(UTF-8)", $nodesFile)
            or die("Can't open \$filename\": $!\n");
        local $/;
        <$json_fh>
    };

    my $r_json = decode_json($json_text);

    return processNodes($r_json);
    #print Dumper($r_json);
}

sub processDeployments($) {
    my ($r_json) = @_;

    if ( ! exists $r_json->{'deployments'} ) {
        return {};
    }

    my %deployments = ();
    foreach my $r_deploymentItem ( @{$r_json->{'deployments'}->{'items'}} ) {
        my %deployment = ( 'name' => $r_deploymentItem->{'metadata'}->{'name'} );
        my $app = $r_deploymentItem->{'metadata'}->{'labels'}->{'app'};
        if ( defined $app ) {
            $deployment{'app'} = $app;
        }
        my $sgname = $r_deploymentItem->{'metadata'}->{'labels'}->{'sgname'};
        if ( defined $sgname ) {
            $deployment{'sgname'} = $sgname;
        }
        $deployments{$deployment{'name'}} = \%deployment;
    }

    return \%deployments;
}

sub processReplicaSets($$) {
    my ($r_json, $r_deployments) = @_;

    if ( ! exists $r_json->{'replicasets'} ) {
        return {};
    }

    my %replicaSets = ();
    foreach my $r_item ( @{$r_json->{'replicasets'}->{'items'}} ) {
        my %replicaSet = ( 'name' => $r_item->{'metadata'}->{'name'} );
        my $r_ownerRefs = $r_item->{'metadata'}->{'ownerReferences'};
        if ( defined $r_ownerRefs ) {
            if ( $r_ownerRefs->[0]->{'kind'} eq 'Deployment' && exists $r_deployments->{$r_ownerRefs->[0]->{'name'}} ) {
                $replicaSet{'deployment'} = $r_deployments->{$r_ownerRefs->[0]->{'name'}};
            }
        }

        my $app = $r_item->{'metadata'}->{'labels'}->{'app'};
        if ( defined $app ) {
            $replicaSet{'app'} = $app;
        }
        $replicaSets{$replicaSet{'name'}} = \%replicaSet;
    }

    return \%replicaSets;
}

sub parseConfig($) {
    my ($dir) = @_;

    my $jsonFile = "$dir/config.json";
    my $json_text = do {
        open(my $json_fh, "<:encoding(UTF-8)", $jsonFile)
            or die("Can't open \$jsonFile\": $!\n");
        local $/;
        <$json_fh>
    };

    my $r_json = decode_json($json_text);
    if ( $::DEBUG > 9 ) { print Dumper("parseConfig: r_json", $r_json); }

    my $r_deployments = processDeployments($r_json);
    my $r_replicaSets = processReplicaSets($r_json, $r_deployments);

    my @pods = ();
    foreach my $r_item ( @{$r_json->{'pods'}->{'items'}} ) {
        push @pods, processPod($r_item, $r_replicaSets);
    }

    return (
        processNodes($r_json->{'nodes'}),
        \@pods,
        $r_replicaSets,
        getCcdVer($r_json->{'nodes'})
    );
}

sub getFiles($$$$) {
    my ($inDir, $r_Incr, $fileBase, $incrKey) = @_;

    my $fileIndex = 0;
    if ( exists $r_Incr->{$incrKey} ) {
        $fileIndex = $r_Incr->{$incrKey};
    }

    my @fileList = ();
    my $keepGoing = 1;
    while ( $keepGoing ) {
        my $filename = sprintf("%s.%03d", $fileBase, $fileIndex + 1);
        my $filePath = $inDir . "/" . $filename;
        if ( $::DEBUG > 0 ) { print "getFiles: checking $filePath\n"; }
        if ( -r $filePath ) {
            push @fileList, $filePath;
            $fileIndex++;
        } else {
            $keepGoing = 0;
        }
    }

    $r_Incr->{$incrKey} = $fileIndex;

    if ( $::DEBUG > 0 ) { print Dumper("getFiles: fileList",\@fileList); }

    return \@fileList;
}

sub getEventTime($) {
    my ($r_event) = @_;

    my $eventTimeStr = $r_event->{'lastTimestamp'};
    if ( ! defined $eventTimeStr && defined $r_event->{'eventTime'} ) {
        $eventTimeStr = $r_event->{'eventTime'};
        $eventTimeStr =~ s/\.\d{6}Z$/Z/;
    }
    my $eventTime = 0;
    if ( defined $eventTimeStr ) {
        my $eventTimeUTC = parseTime( $eventTimeStr, $StatsTime::TIME_K8S );
        $eventTime = parseTime(
            formatSiteTime( $eventTimeUTC, $StatsTime::TIME_SQL),
            $StatsTime::TIME_SQL
        );
    }

    return $eventTime;
}

sub getOutputEvent($$$$) {
    my ($r_event, $r_replicaSets, $r_eventsByPod, $r_jobPods) = @_;

    my $podName = undef;
    my $involvedObject = undef;
    my $jobEvent = 0;
    my $r_createdPod = undef;

    if ( exists $r_event->{'involvedObject'} ) {
        $involvedObject = $r_event->{'involvedObject'}->{'kind'} . "/" . $r_event->{'involvedObject'}->{'name'};
        if ( $r_event->{'involvedObject'}->{'kind'} eq 'Pod' &&
            exists $r_event->{'involvedObject'}->{'fieldPath'} &&
            $r_event->{'involvedObject'}->{'fieldPath'} =~  /^spec.(containers|initContainers)\{([^\}]+)\}/ ) {
            $involvedObject .= "/" . $2;
        }
    }

    if ( $r_event->{'involvedObject'}->{'kind'} eq 'Job' ) {
        $jobEvent = 1;
        if ( $r_event->{'reason'} eq 'SuccessfulCreate' && $r_event->{'message'} =~ /Created pod: (\S+)/ ) {
            $podName = $1;
            if ( $::DEBUG > 5 ) { print "getOutputEvent: podName=$podName\n"; }
            $r_jobPods->{$podName} = $r_event->{'involvedObject'}->{'name'};

            if ( exists $r_eventsByPod->{$podName} ) {
                foreach my $r_outEvent ( @{$r_eventsByPod->{$podName}} ) {
                    $r_outEvent->{'jobevent'} = 1;
                }
            }
        }
    } elsif ( $r_event->{'involvedObject'}->{'kind'} eq 'Pod' ) {
        $podName = $r_event->{'involvedObject'}->{'name'};
        if ( exists $r_jobPods->{$podName} ) {
            $jobEvent = 1;
        }
        if ( exists $r_event->{'_pod_info'} && $jobEvent == 0) {
            $r_createdPod = processPod($r_event->{'_pod_info'}, $r_replicaSets);
        }
        if ( $::DEBUG > 5 ) {
            printf(
                "getOutputEvent pod event uid=%s, pod name=%s, reason=%s message=%s\n",
                $r_event->{'involvedObject'}->{'uid'},
                $podName,
                $r_event->{'reason'},
                $r_event->{'message'}
            );
        }
    } elsif ( $r_event->{'involvedObject'}->{'kind'} eq 'CronJob' ) {
        $jobEvent = 1;
    }
    if ( $::DEBUG > 5 ) { printf "getOutputEvent: jobEvent=%d\n", $jobEvent; }

    # In K8S, if the same event re-occurs (e.g. Liveness probe fails of same pod)
    # this is not new event, it's an update of the existing event. So some events have
    # a count field
    my $message = $r_event->{'message'};
    if ( exists $r_event->{'count'} && $r_event->{'count'} > 1 ) {
        $message .= sprintf("(x %d)", $r_event->{'count'});
    }

    my %outEvent = (
        'first' => $r_event->{'firstTimestamp'},
        'last' => $r_event->{'lastTimestamp'},
        'type' => $r_event->{'type'},
        'reason' => $r_event->{'reason'},
        'message' => $message,
        'jobevent' => $jobEvent,
        'involvedobject' => $involvedObject,
        'time' => getEventTime($r_event),
        'uid' => $r_event->{'metadata'}->{'uid'}
    );
    return (\%outEvent, $podName, $r_createdPod);
}

sub getHaEvent($$) {
    my ($r_event, $r_pod) = @_;

    my $kind = $r_event->{'involvedObject'}->{'kind'};
    my $reason = $r_event->{'reason'};
    if ( $::DEBUG > 5 ) { printf("getHaEvent kind=%s reason=%s\n", $kind, $reason); }
    if ( $kind ne 'Pod' ) {
        return undef;
    }

    # Strip off "combined from similar events" from message
    my $message = $r_event->{'message'};
    if ( defined $message ) {
        $message =~ s/^\(combined from similar events\): //;
    }

    my $eventType = undef;
    if ( $reason eq 'Killing' ) {
        $eventType = 'Kill'
    } elsif ( $reason eq 'Unhealthy' ) {
        if (  $message =~ /^(\S+) probe (failed|errored):/ ) {
            $eventType = 'Unhealthy' . $1;
        }
    } elsif ( $reason eq 'BackOff' ) {
        if ( $message =~ /restarting failed container/ ) {
            $eventType = 'BackOffRestart';
        }
    }
    if ( ! defined $eventType ) {
        return undef;
    }

    my $timestamp = parseTime($r_event->{'lastTimestamp'}, $StatsTime::TIME_K8S);
    my %haEvent = (
        'type' => $eventType,
        'pod' => $r_event->{'involvedObject'}->{'name'},
        'timestamp' => $timestamp,
        'time' => formatSiteTime($timestamp, $StatsTime::TIME_SQL)
    );
    if ( $r_event->{'involvedObject'}->{'fieldPath'} =~ /^spec.containers\{(\S+)\}/ ) {
        $haEvent{'container'} = $1;
    }
    if ( exists $r_event->{'source'}->{'host'} ) {
        $haEvent{'worker'} = $r_event->{'source'}->{'host'};
    }

    if ( $::DEBUG > 6 ) { print Dumper("getHaEvent", \%haEvent); }
    return \%haEvent;
}

sub parseEvents($$) {
    my ($dir, $r_Incr) = @_;

    my $r_eventFiles = getFiles($dir, $r_Incr, "events.json", "fileIndex");
    my @rawEvents = ();
    foreach my $eventFile ( @{$r_eventFiles} ) {
        open INPUT, $eventFile or die "Cannot open $eventFile";
        my $file_content = do { local $/; <INPUT> };
        close INPUT;
        my $r_events = decode_json($file_content);
        foreach my $r_event ( @{$r_events} ) {
            push @rawEvents, $r_event;
        }
    }

    return \@rawEvents;
}

sub parseDelta($$) {
    my ($dir, $r_Incr) = @_;

    my $r_files = getFiles($dir, $r_Incr, "delta.json", "deltaIndex");
    my %results = (
        'events' => [],
        'podstatus' => {},
        'helm' => [],
        'swim' => []
    );
    foreach my $file ( @{$r_files} ) {
        if ( $::DEBUG > 3 ) { print "parseDelta: file=$file\n"; }
        open INPUT, $file or die "Cannot open $file";
        my $file_content = do { local $/; <INPUT> };
        close INPUT;
        my $r_data = decode_json($file_content);
        if ( $::DEBUG > 10 ) { print Dumper("parseDelta: r_data", $r_data); }
        foreach my $r_event ( @{$r_data->{'events'}} ) {
            push @{$results{'events'}}, $r_event;
        }

         foreach my $r_chartInstance ( @{$r_data->{'helm'}} ) {
            push @{$results{'helm'}}, $r_chartInstance;
        }

        if ( exists $r_data->{'swim'} ) {
            foreach my $r_configMap ( @{$r_data->{'swim'}} ) {
                push @{$results{'swim'}}, $r_configMap;
            }
        }

        while ( my ($podUid, $r_podStatus) = each %{$r_data->{'podstatus'}} ) {
            if ( ! exists $results{'podstatus'}->{$podUid} ) {
                $results{'podstatus'}->{$podUid} = [];
            }
            push @{$results{'podstatus'}->{$podUid}}, $r_podStatus;
        }
    }

    if ( $::DEBUG > 8 ) { print Dumper("parseDelta: results", \%results); }

    return \%results;
}

# The purpose here is to figure out why are container restarted. We do this by looking at the lastState
# in the "replacement" container
# The complexity here is because the Killed event doesn't contain the containerID for the container that
# was killed, so we have to try and track the containers so that when we get the Killed event, we assume
# that the container that was killed was the last/most recent one that we know about for that container
# name/pod
# e.g.
=begin
{
    "containerID": "containerd://ad8da1470169f234c5eeb574d9700b5a83bfb54f011883995709f82564c193a2",
    "image": "armdocker.rnd.ericsson.se/proj-enm/eric-enmsg-msap:1.29.0-14",
    "imageID": "armdocker.rnd.ericsson.se/proj-enm/eric-enmsg-msap@sha256:b6fd3d9221f16740b1b75aea9a1b8fafaa31bf701fce13684bafdc72c27b72b9",
    "lastState": {
        "terminated": {
            "containerID": "containerd://2dbc952be46d4999db12eb39fdc6881bc5466d1846b3a9fa07396586fc437f0e",
            "exitCode": 137,
            "finishedAt": "2022-06-26T21:51:24Z",
            "reason": "OOMKilled",
            "startedAt": "2022-06-21T09:18:04Z"
        }
    },
    "name": "msap",
    "ready": true,
    "restartCount": 1,
    "started": true,
    "state": {
        "running": {
            "startedAt": "2022-06-26T21:51:25Z"
        }
    }
},
=cut
sub extractRestartInfo($$$$$) {
    my ($r_outputEvent, $r_event, $r_existingPods, $r_killedContainerEvents, $r_containerLastState) = @_;

    my ($containerName) = $r_outputEvent->{'involvedobject'} =~ /^Pod\/\S+\/(\S+)$/;
    if ( ! defined $containerName ) {
        return;
    }

    my $podUID = $r_event->{'involvedObject'}->{'uid'};
    if ( $::DEBUG > 5 ) { print "extractRestartInfo: podUID=$podUID containerName=$containerName reason=$r_outputEvent->{'reason'}\n"; }
    if ( $r_outputEvent->{'reason'} eq 'Killing' ) {
        my $r_pod = $r_existingPods->{$podUID};
        if ( defined $r_pod ) {
            my $r_containerInstances = $r_pod->{'containers'}->{$containerName};
            if ( defined $r_containerInstances && $#{$r_containerInstances} > -1 ) {
                my $containerID = $r_containerInstances->[$#{$r_containerInstances}]->{'containerID'};
                $r_killedContainerEvents->{$containerID} = $r_outputEvent;
            } else {
                print "WARN: Could not find container instances for $containerName for Killed pod with UID $podUID\n";
                if ( $::DEBUG > 3 ) { print Dumper("extractRestartInfo: r_pod", $r_pod); }
            }
        } else {
            print "WARN: Could not find Killed pod with UID $podUID\n";
        }
    } elsif ( $r_outputEvent->{'reason'} eq 'Created' && exists $r_event->{'_pod_info'} ) {
        foreach my $r_containerStatus ( @{$r_event->{'_pod_info'}->{'status'}->{'containerStatuses'}} ) {
            if ( $::DEBUG > 6 ) { print "extractRestartInfo: Checking containerStatus for $r_containerStatus->{'name'}\n"; }
            if ( $r_containerStatus->{'name'} eq $containerName && exists $r_containerStatus->{'lastState'} ) {
                if ( exists $r_containerStatus->{'lastState'}->{'terminated'} ) {
                    my $r_termState = $r_containerStatus->{'lastState'}->{'terminated'};
                    if ( $::DEBUG > 6 ) { print "extractRestartInfo: termState $r_termState->{'containerID'} $r_termState->{'reason'}\n"; }
                    $r_containerLastState->{$r_termState->{'containerID'}} = $r_termState->{'reason'};
                }
            }
        }
    }
}

# We seem to get a "Created" event for Pods foreach container in the Pod
# We try and extract the containerID from the _podInfo. e.g.
=begin
    "containerStatuses": [
        {
            "containerID": "containerd://7ccc7b5af4db16359393cf5043335e4c1748cde10b5521e647f3eb521303a2e3",
            "image": "armdocker.rnd.ericsson.se/proj-enm/eric-enmsg-access-control:1.29.0-20",
            "imageID": "armdocker.rnd.ericsson.se/proj-enm/eric-enmsg-access-control@sha256:3b4ad71d904749c8435ff8178dc0c7776ce5e42f48efaae9bb964070e65a41e4",
            "lastState": {},
            "name": "accesscontrol",
            "ready": true,
            "restartCount": 0,
            "started": true,
            "state": {
                "running": {
                    "startedAt": "2022-06-27T10:02:33Z"
                }
            }
        },
=cut
# However, sometimes the containers are in a state where the containerID isn't
# defined yet e.g.
=begin
    "containerStatuses": [
        {
            "image": "armdocker.rnd.ericsson.se/proj-enm/eric-enmsg-pmservice:1.29.0-20",
            "imageID": "",
            "lastState": {},
            "name": "pmserv",
            "ready": false,
            "restartCount": 0,
            "started": false,
            "state": {
                "waiting": {
                    "reason": "PodInitializing"
                }
            }
        },
=cut
sub processCreatedPod($$$) {
    my ($r_createdPod, $r_existingPods, $r_pods) = @_;
    my $podUID = $r_createdPod->{'uid'};
    if ( $::DEBUG > 3 ) { print "processCreatedPod: $podUID $r_createdPod->{'name'} \n"; }

    my $r_existingPod = $r_existingPods->{$podUID};
    if ( ! defined $r_existingPod ) {
        if ( $::DEBUG > 3 ) { print "processCreatedPod: $podUID adding pod " . $r_createdPod->{'name'} . "\n"; }
        $r_existingPods->{$podUID} = $r_createdPod;
        push @{$r_pods}, $r_createdPod;
        $r_existingPod = $r_createdPod;
    }


    # Iterate though the containers in r_createdPod adding any container that doesn't
    # already exist into the containers in existing pod
    my $r_existingContainersByName = $r_existingPods->{$podUID}->{'containers'};
    while ( my ($containerName, $r_createdContainerInstances) = each %{$r_createdPod->{'containers'}} ) {
        if ( $::DEBUG > 5 ) { print "processCreatedPod: $podUID checking $containerName\n"; }
        my $r_existingContainerInstances = $r_existingContainersByName->{$containerName};
        if ( ! defined $r_existingContainerInstances ) {
            $r_existingContainerInstances = [];
            $r_existingContainersByName->{$containerName} = $r_existingContainerInstances;
        }
        foreach my $r_createdContainerInstance ( @{$r_createdContainerInstances} ) {
            my $matchFound = 0;
            if ( $::DEBUG > 5 ) { print "processCreatedPod: $podUID  checking $r_createdContainerInstance->{'containerID'}\n"; }
            foreach my $r_existingInstance ( @{$r_existingContainerInstances} ) {
                if ( $::DEBUG > 8 ) { printf("processCreatedPod: $podUID  checking r_existingInstance %s\n", $r_existingInstance->{'containerID'}); }
                if ( $r_createdContainerInstance->{'containerID'} eq $r_existingInstance->{'containerID'} ) {
                    $matchFound = 1;
                }
            }
            if ( ! $matchFound ) {
                if ( $::DEBUG > 5 ) { print "processCreatedPod: $podUID  adding new container\n"; }
                push @{$r_existingContainerInstances}, $r_createdContainerInstance;
            }
        }
    }

    if ( $::DEBUG > 7) { print Dumper("processCreatedPod: $podUID r_existingContainersByName", $r_existingContainersByName); }
}

sub processEvents($$$$$$) {
    my ($r_events, $r_Incr, $r_nodes, $r_pods, $r_replicaSets, $outDir) = @_;

    # Add a sortable timestamp to the events and drop any duplicates
    my %eventKeys = ();
    my @sortedRawEvents = ();
    my $dropCount = 0;
    foreach my $r_event ( @{$r_events} ) {
        my $r_meta = $r_event->{'metadata'};
        my $eventKey = sprintf("%s:%s", $r_meta->{'uid'}, $r_meta->{'resourceVersion'});
        if ( exists $eventKeys{$eventKey} ) {
            if ( $::DEBUG > 3 ) { print "WARN: Duplicate event $r_meta->{'creationTimestamp'} $eventKey\n" };
            $dropCount++;
        } else {
            $eventKeys{$eventKey} = 1;
            $r_event->{'_timestamp'} = parseTime($r_meta->{'creationTimestamp'}, $StatsTime::TIME_K8S);
            push @sortedRawEvents, $r_event;
        }
    }
    @sortedRawEvents = sort { $a->{'_timestamp'} <=> $b->{'_timestamp'} } @sortedRawEvents;
    if ( $dropCount > 0 ) {
        print "INFO: Dropped $dropCount duplicate events\n";
    }

    my $r_outEvents = [];

    # If we're operating in incremental mode, then read in the existing processed events
    # from the k8s_events.json file
    my $outFile = $outDir . "/k8s_events.json";
    if ( -s $outFile && exists $r_Incr->{'appendEvents'} ) {
        open INPUT, $outFile or die "Cannot open $outFile";
        my $file_content = do { local $/; <INPUT> };
        close INPUT;
        $r_outEvents = decode_json($file_content);
    }
    # Tell ourself to append to the k8s_events.json in the next run
    $r_Incr->{'appendEvents'} = 1;

    my %existingPods = ();
    foreach my $r_pod ( @{$r_pods} ) {
        $existingPods{$r_pod->{'uid'}} = $r_pod;
    }
    if ( $::DEBUG > 3 ) {
        my @keys = keys %existingPods;
        print Dumper("processEvents: existingPods keys", \@keys);
    }
    my %jobPods = ();
    my %eventsByPod = ();
    my @haEvents = ();
    my %killedContainerEvents = ();
    my %containerLastState = ();

    foreach my $r_event ( @sortedRawEvents ) {
        if ( $::DEBUG > 8 ) { print Dumper("processEvents: r_event", $r_event); }

        my ($r_outputEvent, $podName, $r_createdPod) = getOutputEvent($r_event, $r_replicaSets, \%eventsByPod, \%jobPods);
        push @{$r_outEvents}, $r_outputEvent;

        if ( defined $podName ) {
            my $r_podEvents = $eventsByPod{$podName};
            if ( ! defined $r_podEvents ) {
                $r_podEvents = [];
                $eventsByPod{$podName} = $r_podEvents;
            }
            push @{$r_podEvents}, $r_outputEvent;

        }

        extractRestartInfo($r_outputEvent, $r_event, \%existingPods, \%killedContainerEvents, \%containerLastState);

        if ( defined $r_createdPod ) {
            processCreatedPod($r_createdPod, \%existingPods, $r_pods);
        }

        my $r_haEvent = getHaEvent($r_event, $r_pods);
        if ( defined $r_haEvent ) {
            push @haEvents, $r_haEvent;
        }
    }

    if ( $::DEBUG > 5 ) {
        print Dumper("processEvents: containerLastState", \%containerLastState);
        print Dumper("processEvents: killedContainerEvents", \%killedContainerEvents);
    }
    while ( my ($containerID,$termReason) = each %containerLastState ) {
        my $r_outputEvent = $killedContainerEvents{$containerID};
        if ( defined $r_outputEvent ) {
            $r_outputEvent->{'message'} = $r_outputEvent->{'message'} . " [" . $termReason . "]";
        }
    }

    if ( @{$r_outEvents} ) {
        open OUTPUT, ">$outFile" or die "Cannot open output file $outFile";
        print OUTPUT encode_json($r_outEvents);
        close OUTPUT;
    }

    my @sortedHaEvents = sort {$a->{'timestamp'} <=> $b->{'timestamp'}} @haEvents;
    if ( $::DEBUG > 3 ) { print Dumper("processEvents: sortedHaEvents", \@sortedHaEvents); }
    return \@sortedHaEvents;
}

sub processHelmHooks($) {
    my ($r_chartInfo) = @_;

    my $r_hooks = $r_chartInfo->{'data'}->{'release'}->{'hooks'};
    if ( ! defined $r_hooks ) {
        return [];
    }
    # We can get multiple entries for the same execution, just with different values for Kind
    my %processedHooks = ();
    foreach my $r_hook ( @{$r_hooks} ) {
        if ( $::DEBUG > 7 ) { print Dumper("processHelmHooks: r_hook", $r_hook); }
        my $r_lastRun = $r_hook->{'last_run'};
        if ( $r_lastRun->{'phase'} eq 'Succeeded' ) {
            my $startTime = parseTime($r_lastRun->{'started_at'}, $StatsTime::TIME_ISO8601, $StatsTime::TZ_SITE);
            my $endTime = parseTime($r_lastRun->{'completed_at'}, $StatsTime::TIME_ISO8601, $StatsTime::TZ_SITE);
            my $key = $r_hook->{'name'} . "-" . $startTime;
            my $r_processedHook = $processedHooks{$key};

            # We need to know if this is a post hook (so we can consider it for timing chart exec)
            my $isPostHook = 0;
            foreach my $hookEvent ( @{$r_hook->{'events'}} ) {
                if ( $hookEvent eq 'post-install' || $hookEvent eq 'post-upgrade' ) {
                    $isPostHook = 1;
                }
            }
            if ( $::DEBUG > 3 ) { print Dumper("processHelmHooks: isPostHook=$isPostHook", $r_hook->{'events'}); }

            if ( ! defined $r_processedHook ) {
                $r_processedHook = {
                    'name' => $r_hook->{'name'},
                    'kinds' => [],
                    'start' => formatSiteTime($startTime, $StatsTime::TIME_SQL),
                    'post' => $isPostHook,
                    'endtime' => 0
                };
                $processedHooks{$key} = $r_processedHook;
            }
            push @{$r_processedHook->{'kinds'}},  $r_hook->{'kind'};
            if ( $endTime > $r_processedHook->{'endtime'} ) {
                $r_processedHook->{'endtime'} = $endTime;
                $r_processedHook->{'end'} = formatSiteTime($endTime, $StatsTime::TIME_SQL);
            }
        }
    }
    my @processedHooksList = values %processedHooks;
    return \@processedHooksList;
}

# Return parsed version of helm chart object
# undef returned if the status of the chart != deployed.
sub processOneChart($$) {
    my ($r_chartInfo, $r_helmVersionToChartVersion) = @_;

    if ( $::DEBUG > 8 ) { print Dumper("processOneChart: r_chartInfo", $r_chartInfo); }
    # We store the mapping of helm secret version => helm chart version
    # for all charts irespective of their status, this is so we can work out the
    # fromVersion for deployed cahrts
    my $helmSecretVersion = $r_chartInfo->{'metadata'}->{'labels'}->{'version'};
    my $chartVersion = $r_chartInfo->{'data'}->{'release'}->{'chart'}->{'metadata'}->{'version'};
    my $chartName = $r_chartInfo->{'data'}->{'release'}->{'chart'}->{'metadata'}->{'name'};
    my $status = $r_chartInfo->{'metadata'}->{'labels'}->{'status'};
    my $uid = $r_chartInfo->{'metadata'}->{'uid'};
    my $resourceVersion = $r_chartInfo->{'metadata'}->{'resourceVersion'};
    if ( $::DEBUG > 3 ) {
        printf(
            "processOneChart: name=%s creationTimestamp=%s uid=%s status=%s label version=%s resourceVersion=%s\n",
            $chartName,
            $r_chartInfo->{'metadata'}->{'creationTimestamp'},
            $uid,
            $status,
            $r_chartInfo->{'metadata'}->{'labels'}->{'version'},
            $resourceVersion
        );
    }

    $r_helmVersionToChartVersion->{$chartName}->{$helmSecretVersion} = $chartVersion;

    if ( $status ne 'deployed' ) {
        return undef;
    }

    my %chartInstance = (
        'metadata.name' => $chartName,
        'metadata.managedFields.time' => $r_chartInfo->{'metadata'}->{'managedFields'}->[0]->{'time'},
        'metadata.managedFields.operation' => $r_chartInfo->{'metadata'}->{'managedFields'}->[0]->{'operation'},
        'metadata.creationTimestamp' => $r_chartInfo->{'metadata'}->{'creationTimestamp'},
        'metadata.uid' => $uid,
        'metadata.labels.name' => $r_chartInfo->{'metadata'}->{'labels'}->{'name'},
        'metadata.labels.status' => $status,
        # For initial installs, this is 1, anything other then 1 indicates an upgrade
        'metadata.labels.status.version' => $r_chartInfo->{'metadata'}->{'labels'}->{'version'},
        'data.release.chart.metadata.version' => $r_chartInfo->{'data'}->{'release'}->{'chart'}->{'metadata'}->{'version'},
        'data.release.chart.metadata.name' => $r_chartInfo->{'data'}->{'release'}->{'chart'}->{'metadata'}->{'name'},
        'data.release.info.first_deployed' => $r_chartInfo->{'data'}->{'release'}->{'info'}->{'first_deployed'},
        'data.release.info.last_deployed' => $r_chartInfo->{'data'}->{'release'}->{'info'}->{'last_deployed'},
        'data.release.hooks' => processHelmHooks($r_chartInfo)
    );
    if ( exists $r_chartInfo->{'metadata'}->{'labels'}->{'modifiedAt'} ) {
        $chartInstance{'metadata.labels.modifiedAt'} = $r_chartInfo->{'metadata'}->{'labels'}->{'modifiedAt'},
        $chartInstance{'metadata.labels.modifiedAtTime'} = formatSiteTime($r_chartInfo->{'metadata'}->{'labels'}->{'modifiedAt'}, $StatsTime::TIME_SQL),
    } elsif ( exists $r_chartInfo->{'metadata'}->{'labels'}->{'createdAt'} ) {
        $chartInstance{'metadata.labels.createdAt'} = $r_chartInfo->{'metadata'}->{'labels'}->{'createdAt'},
        $chartInstance{'metadata.labels.createdAtTime'} = formatSiteTime($r_chartInfo->{'metadata'}->{'labels'}->{'createdAt'}, $StatsTime::TIME_SQL)
    }

    # If the chart has post hooks, use their endtime to calculate the end time for chart
    my $lastHookEnd = undef;
    foreach my $r_hook ( @{$chartInstance{'data.release.hooks'}} ) {
        if ( $r_hook->{'post'} ) {
            if ( ! defined $lastHookEnd || $lastHookEnd < $r_hook->{'endtime'} ) {
                $lastHookEnd = $r_hook->{'endtime'}
            }
        }
    }
    if ( defined $lastHookEnd ) {
        $chartInstance{'hooks.post.endtime'} = $lastHookEnd;
    }

    return \%chartInstance;
}

sub setFromVersion($$) {
    my ($r_processedChart, $r_helmVersionToChartVersion) = @_;
    my $helmSecretVersion = $r_processedChart->{'metadata.labels.status.version'};
    if ( $helmSecretVersion == 1 ) {
        $r_processedChart->{'operation'} = 'Install';
    } else {
        $r_processedChart->{'operation'} = 'Upgrade';
        my $name = $r_processedChart->{'data.release.chart.metadata.name'};
        $r_processedChart->{'fromVersion'} = $r_helmVersionToChartVersion->{$name}->{$helmSecretVersion-1};
    }
}

# Return map of pod uid => pod chart (app.kubernetes.io/instance)
sub getPodChart($) {
    my ($r_pods) = @_;

    my %podChart = ();
    foreach my $r_pod ( @{$r_pods} ) {
        if ( $::DEBUG > 4 ) { print Dumper("getPodChart: r_pod", $r_pod); }
        if ( exists $r_pod->{'app.kubernetes.io/instance'} ) {
            $podChart{$r_pod->{'uid'}} = $r_pod->{'app.kubernetes.io/instance'}
        }
    }
    if ( $::DEBUG > 5 ) { print Dumper("getPodChart: podChart", \%podChart); }
    return \%podChart;
}

sub processHelm($$$) {
    my ($r_data, $r_incr, $date) = @_;

    # Note: DDC should only correct a chart once but if it ends up collecting it
    # more then once, then storing the charts based on their uid in r_deployedCharts
    # will ensure we only "keep" the last one
    my ($r_deployedCharts, $r_helmVersionToChartVersion);
    if ( exists $r_incr->{'helm'} ) {
        my $r_deployedCharts = $r_incr->{'helm'}->{'deployedCharts'};
        my $r_helmVersionToChartVersion = $r_incr->{'helm'}->{'helmVersionToChartVersion'};
    } else {
        $r_deployedCharts = {};
        $r_helmVersionToChartVersion = {};
        $r_incr->{'helm'} = {
            'deployedCharts' => $r_deployedCharts,
            'helmVersionToChartVersion' => $r_helmVersionToChartVersion
        };
    }

    foreach my $r_rawChart ( @{$r_data} ) {
        my $r_processedChart = processOneChart($r_rawChart, $r_helmVersionToChartVersion);
        if ( defined $r_processedChart ) {
            $r_deployedCharts->{$r_processedChart->{'metadata.uid'}} = $r_processedChart;
        }
    }
    if ( $::DEBUG > 7 ) {
        print Dumper("processHelm: r_deployedCharts", $r_deployedCharts);
        print Dumper("processHelm: r_helmVersionToChartVersion", $r_helmVersionToChartVersion);
    }

    my $dayStart = parseTime("$date 00:00:00", $StatsTime::TIME_SQL, $StatsTime::TZ_SITE);
    my $dayEnd = parseTime("$date 23:59:59", $StatsTime::TIME_SQL, $StatsTime::TZ_SITE);
    if ( $::DEBUG > 6 ) { print "processHelm: dayStart=$dayStart dayEnd=$dayEnd\n"; }

    my @results = ();

    foreach my $r_processedChart ( values %{$r_deployedCharts} ) {
        # Now check if this is for today (starts or ends)
        my $endTime = $r_processedChart->{'metadata.labels.modifiedAt'};
        # If we have a hooks.post.endtime, use that instead (so we ignore exec of helm tests)
        # Note: This only really works as long as there are other post hooks
        if ( exists $r_processedChart->{'hooks.post.endtime'} ) {
            $endTime = $r_processedChart->{'hooks.post.endtime'};
        }
        if ( $::DEBUG > 6 ) {
            printf(
                "processHelm: timecheck %s endTime= %d (%s), >= dayStart = %d, <= dayEnd = %d\n",
                $r_processedChart->{'metadata.name'},
                $endTime,
                formatSiteTime($endTime, $StatsTime::TIME_SQL),
                $endTime >= $dayStart,
                $endTime <= $dayEnd
            );
        }
        if ( $endTime >= $dayStart && $endTime <= $dayEnd) {
            $r_processedChart->{'endTime'} = $endTime;
            setFromVersion($r_processedChart, $r_helmVersionToChartVersion);
            push @results, $r_processedChart;
        }
    }

    # Now iterator through the charts and for upgrades, add the fromVersion

    my @sorted_results = sort { $a->{'endTime'} <=> $b->{'endTime'} } @results;
    if ( $::DEBUG > 3 ) { print Dumper("processHelm: sorted_results", \@sorted_results); }
    return \@sorted_results;
}

sub processPodStatus($$) {
    my ($r_data, $r_incr) = @_;

    my $r_podStatusByPodUid = $r_incr->{'podstatus'};
    if ( ! defined $r_podStatusByPodUid ) {
        $r_podStatusByPodUid = {};
        $r_incr->{'podstatus'} = $r_podStatusByPodUid;
    }

    while ( my ($podUid,$r_podStatusUpdateList) = each %{$r_data} ) {
        if ( $::DEBUG > 5 ) { printf("processPodStatus: processing pod %s\n", $podUid); }
        my $r_podStatus = $r_podStatusByPodUid->{$podUid};
        if ( ! defined $r_podStatus  ) {
            $r_podStatus = {
                'podname' => $r_podStatusUpdateList->[0]->{'metadata'}->{'name'},
                'conditions' => {},
                'containers' => {}
            };
            $r_podStatusByPodUid->{$podUid} = $r_podStatus;
        }

        foreach my $r_podStatusUpdate ( @{$r_podStatusUpdateList} ) {
            foreach my $r_condition ( @{$r_podStatusUpdate->{'status'}->{'conditions'}} ) {
                my $key = $r_condition->{'type'};
                if ( $r_condition->{'status'} eq 'True' && ! exists $r_podStatus->{'conditions'}->{$key} ) {
                    my $lastTransitionTimeNum = parseTime($r_condition->{'lastTransitionTime'}, $StatsTime::TIME_K8S);
                    my $timeStr = formatSiteTime($lastTransitionTimeNum, $StatsTime::TIME_SQL);
                    $r_podStatus->{'conditions'}->{$key} = {
                        'type' => $r_condition->{'type'},
                        'lastTransitionTime' => $timeStr,
                        'lastTransitionTimeNum' => $lastTransitionTimeNum
                    };
                }
            }

            foreach my $r_containerStatus ( @{$r_podStatusUpdate->{'status'}->{'containerStatuses'}} ) {
                if ( $::DEBUG > 8 ) { print Dumper("processPodStatus: $podUid r_containerStatus\n", $r_containerStatus); }
                my $cid = $r_containerStatus->{'containerID'};
                if ( defined $cid ) {
                    my $cname = $r_containerStatus->{'name'};
                    if ( $::DEBUG > 5 ) { printf("processPodStatus: %s container=%s cid=%s\n", $podUid, $cname, $cid); }
                    my $r_containerInstances = $r_podStatus->{'containers'}->{$cname};
                    if ( ! defined $r_containerInstances ) {
                        $r_containerInstances = [];
                        $r_podStatus->{'containers'}->{$cname} = $r_containerInstances;
                    }
                    my $found = 0;
                    foreach my $r_containerInstance ( @{$r_containerInstances} ) {
                        my $existingCid = $r_containerInstance->{'containerID'};
                        if ( $::DEBUG > 5 ) { printf("processPodStatus: podUid=%s  existingCid=%s cid=%s\n", $podUid, $existingCid, $cid); }
                        if ( $existingCid eq $cid ) {
                            $found = 1;
                        }
                    }
                    if ( ! $found ) {
                        push @{$r_containerInstances}, { 'containerID' => $cid };
                    }
                }
            }
        }
    }
    if ( $::DEBUG > 5 ) { print Dumper("processPodStatus: r_podStatusByPodUid", $r_podStatusByPodUid); }
    return $r_podStatusByPodUid;
}

#
# Look for any pod events that occur during the chart. Events related
# to Jobs are dropped
#
sub processEventsForChart($$$$) {
    my ($r_chartInstance, $r_events, $r_podStatusByName, $r_podToChart) = @_;

    # first_deployed seems to refer to when the first version of chart was installed
    my $startTime = parseTime($r_chartInstance->{'data.release.info.last_deployed'}, $StatsTime::TIME_ISO8601, $StatsTime::TZ_SITE);

    # default to the modifiedAt as the endTime but test hooks break this
    my $endTime = $r_chartInstance->{'metadata.labels.modifiedAt'};
    if ( exists $r_chartInstance->{'hooks.post.endtime'} ) {
        $endTime = $r_chartInstance->{'hooks.post.endtime'};
    }

    if ( $::DEBUG > 3 ) {
        printf(
            "processEventsForChart: look for events between %s and %s\n",
            formatSiteTime($startTime, $StatsTime::TIME_SQL),
            formatSiteTime($endTime, $StatsTime::TIME_SQL)
        );
    }

    my $chartName = $r_chartInstance->{'metadata.labels.name'};

    my %pods = ();
    my %jobPods = ();
    foreach my $r_event ( @{$r_events} ) {
        my $eventTime = undef;
        if ( defined $r_event->{'lastTimestamp'} ) {
            $eventTime = parseTime($r_event->{'lastTimestamp'}, $StatsTime::TIME_K8S);
        } elsif ( defined $r_event->{'eventTime'} ) {
            my $eventTimeStr = $r_event->{'eventTime'};
            $eventTimeStr =~ s/\.(\d+)Z$/Z/;
            $eventTime = parseTime($eventTimeStr, $StatsTime::TIME_K8S);
        }

        if ( ! defined $eventTime ) {
            print Dumper($r_event);
        }

        if ( $eventTime < $startTime || $eventTime > $endTime ) {
            next;
        }

        if ( $r_event->{'involvedObject'}->{'kind'} eq 'Job' ) {
            if ( $r_event->{'reason'} eq 'SuccessfulCreate' && $r_event->{'message'} =~ /Created pod: (\S+)/ ) {
                $jobPods{$1} = 1;
            }
        } elsif ( $r_event->{'involvedObject'}->{'kind'} eq 'Pod' ) {
            my $key = $r_event->{'involvedObject'}->{'uid'};
            # Discard event if the pod isn't part of this chart
            my $podChart = $r_podToChart->{$key};
            if ( (! defined $podChart) || ($podChart ne $chartName) ) {
                if ( $::DEBUG > 5 ) {
                    printf(
                        "processEventsForChart: Discard pod event for %s chartName=%s podChart=%s\n",
                        $r_event->{'involvedObject'}->{'name'},
                        $chartName,
                        ( defined $podChart ? $podChart : "undefined")
                    );
                }
                next;
            }

            if ( $::DEBUG > 5 ) { printf "processEventsForChart: found pod event for %s %s\n", $key, $r_event->{'involvedObject'}->{'name'}; }
            if ( $::DEBUG > 6 ) { print Dumper("processEventsForChart: r_event", $r_event); }

            my $r_pod = $pods{$key};
            if ( ! defined $r_pod ) {
                $r_pod = {
                    'name' => $r_event->{'involvedObject'}->{'name'},
                    'containers' => {},
                    'scheduled' => 0
                };
                $pods{$key} = $r_pod;
            }

            if ( $r_event->{'reason'} eq 'Scheduled') {
                $r_pod->{'scheduled'} = 1;
            }

            if (defined $r_event->{'involvedObject'}->{'fieldPath'} &&
                $r_event->{'involvedObject'}->{'fieldPath'} =~ /^spec.(containers|initContainers)\{([^\}]+)\}/ ) {
                my ($type, $container) = ($1, $2);
                if ( $::DEBUG > 5 ) { printf "processEventsForChart: %s %s %s\n", $r_event->{'involvedObject'}->{'fieldPath'}, $type, $container; }
                my $r_containerEvents = $r_pod->{$type}->{$container};
                if ( ! defined $r_containerEvents ) {
                    $r_containerEvents = [];
                    $r_pod->{$type}->{$container} = $r_containerEvents;
                }
                push @{$r_containerEvents}, {
                    'time' => $eventTime,
                    'reason' => $r_event->{'reason'}
                };
            }
        }
    }

    # Delete pods that belong to jobs
    my @jobPodUids = ();
    while ( my ($podUid, $r_pod) = each %pods ) {
        if ( exists $jobPods{$r_pod->{'name'}} ) {
            push @jobPodUids, $podUid;
        }
    }
    foreach my $podUid ( @jobPodUids ) {
        delete $pods{$podUid};
    }

    my @nonScheduledPods = ();
    while ( my ($podUID, $r_pod) = each %pods ) {
        if ( $r_pod->{'scheduled'} ) {
            my $r_podStatus = $r_podStatusByName->{$podUID};
            if ( defined $r_podStatus ) {
                while ( my ($key,$r_condition) = each %{$r_podStatus->{'conditions'}} ) {
                    $r_pod->{'conditions'}->{$r_condition->{'type'}} = $r_condition->{'lastTransitionTime'};
                }
            }
        } else {
            push @nonScheduledPods, $podUID;
        }
    }
    # Delete pods where we don't have a scheduled event
    foreach my $podUID ( @nonScheduledPods ) {
        delete $pods{$podUID};
    }

    my %result = (
        'start' => formatSiteTime($startTime, $StatsTime::TIME_SQL),
        'end' => formatSiteTime($endTime, $StatsTime::TIME_SQL),
        'name' => $r_chartInstance->{'data.release.chart.metadata.name'},
        'operation' => $r_chartInstance->{'operation'},
        'toVersion' => $r_chartInstance->{'data.release.chart.metadata.version'},
        'fromVersion' => $r_chartInstance->{'fromVersion'},
        'hooks' => $r_chartInstance->{'data.release.hooks'},
        'pods' => \%pods,
    );

    if ( $::DEBUG > 5 ) { print Dumper("processEventsForChart: result", \%result); }
    return \%result;
}

sub writeHelmUpdate($$) {
    my ($r_helmUpdate, $output_dir) = @_;

    if ( $::DEBUG > 8 ) { print Dumper("writeHelmPods: r_helmUpdate", $r_helmUpdate); }

    my @rows = ();
    while ( my ($podUid, $r_pod) = each %{$r_helmUpdate->{'pods'}} ) {
        my %row = (
            'poduid' => $podUid,
            'podname' => $r_pod->{'name'}
        );
        my $r_conditions = $r_pod->{'conditions'};
        foreach my $key ( 'PodScheduled', 'Initialized', 'Ready' ) {
            $row{$key} = $r_conditions->{$key};
        }
        push @rows, \%row;
    }
    my @sortedRows = sort { $a->{'podname'} cmp $b->{'podname'} } @rows;

    my @sortedHooks = sort { $a->{'name'} cmp $b->{'name'} } @{$r_helmUpdate->{'hooks'}};

    my %outputData = (
        'pods' => \@sortedRows,
        'hooks' => \@sortedHooks
    );
    my $output_file = sprintf(
        "%s/helm-%s-%s.json",
        $output_dir,
        $r_helmUpdate->{'name'},
        parseTime($r_helmUpdate->{'end'}, $StatsTime::TIME_SQL)
    );
    if ( $::DEBUG > 3 ) { printf("writeHelmPods: writing to %s\n", $output_file); }
    open OUTPUT, ">$output_file";
    print OUTPUT encode_json(\%outputData);
    close OUTPUT;
}

sub processSwim($$) {
    my ($r_data, $r_incr) = @_;

    if ( $::DEBUG > 7 ) { print Dumper("processSwim: r_data", $r_data); }

    my $r_swimByProdNum = $r_incr->{'swim'};
    if ( ! defined $r_swimByProdNum ) {
        $r_swimByProdNum = {};
        $r_incr->{'swim'} = $r_swimByProdNum;
    }

    foreach my $r_configMap ( @{$r_data} ) {
        my %product = (
            'number' => $r_configMap->{'metadata'}->{'annotations'}->{'ericsson.com/product-number'},
            'revision' => $r_configMap->{'metadata'}->{'annotations'}->{'ericsson.com/product-revision'},
            'name'  => $r_configMap->{'metadata'}->{'annotations'}->{'ericsson.com/product-name'},
            'commercialName' => $r_configMap->{'metadata'}->{'annotations'}->{'ericsson.com/commercial-name'},
            'semanticVersion' => $r_configMap->{'metadata'}->{'annotations'}->{'ericsson.com/semantic-version'}
        );
        if ( $::DEBUG > 5 ) { print Dumper("processSwim: product", \%product); }
        $r_swimByProdNum->{$product{'number'}} = \%product;
    }

    my @results = values %{$r_swimByProdNum};
    if ( $::DEBUG > 3 ) { print Dumper("processSwim: results", \@results); }
    return \@results;
}

sub parseAppMap($) {
    my ($appMapFile) = @_;

    open INPUT, $appMapFile or die "Cannot open $appMapFile";
    my $file_content = do { local $/; <INPUT> };
    close INPUT;
    return decode_json($file_content);
}

sub mapPodName($$) {
    my ($r_pod, $r_mappedPodByServerName) = @_;

    my $mappedName = undef;

    if ( $r_pod->{'kind'} eq 'StatefulSet' ) {
        $mappedName = $r_pod->{'name'};
    } elsif ( exists $r_pod->{'app'} ) {
        my $baseName = $r_pod->{'app'};
        my $filteredNameCheck = grep( /^$baseName$/, @FILTERED_APPS );
        if ( exists $r_pod->{'replicaset'} && exists $r_pod->{'replicaset'}->{'deployment'} && $filteredNameCheck == 0 ) {
            $baseName = $r_pod->{'replicaset'}->{'deployment'}->{'name'};
        }
        my $instanceIndex = 0;
        do {
            if ( $instanceIndex < $MAX_POD_INSTANCE ) {
                $mappedName = sprintf("%s-%02d", $baseName, $instanceIndex);
                $instanceIndex++;
            } else {
                die("mapPodName: Aborting as index:$instanceIndex for $baseName is greater than 200");
            }
        } while ( exists $r_mappedPodByServerName->{$mappedName} );
    }

    if ( $::DEBUG > 5 ) { print "mapPodName: " . $r_pod->{'name'} . "->" . (defined $mappedName ? $mappedName : "undef") . "\n" }

    return $mappedName;
}

sub storeHelm($$$) {
    my ($dbh, $siteId, $r_helmUpdates) = @_;

    if ( $#{$r_helmUpdates} == -1 ) {
        return;
    }

    dbDo(
        $dbh,
        sprintf(
            "DELETE FROM k8s_helm_update WHERE siteid = %d AND end BETWEEN '%s' AND '%s'",
            $siteId,
            $r_helmUpdates->[0]->{'end'},
            $r_helmUpdates->[$#{$r_helmUpdates}]->{'end'}
        )
    ) or die "Failed to remove old data";

    for my $r_helmUpdate ( @{$r_helmUpdates} ) {
        my $fromVersion = "NULL";
        if ( defined $r_helmUpdate->{'fromVersion'} ) {
            $fromVersion = sprintf("'%s'", $r_helmUpdate->{'fromVersion'});
        }
        dbDo(
            $dbh,
            sprintf(
                "INSERT INTO k8s_helm_update (siteid, end, start, name, operation, toVersion, fromVersion) VALUES (%d, '%s', '%s', '%s', '%s', '%s', %s)",
                $siteId,
                $r_helmUpdate->{'end'},
                $r_helmUpdate->{'start'},
                $r_helmUpdate->{'name'},
                $r_helmUpdate->{'operation'},
                $r_helmUpdate->{'toVersion'},
                $fromVersion
            )
        ) or die "Failed to insert row";
    }

}

sub getServiceGroup($$) {
    my ($r_pod, $r_appMap) = @_;
    my $serviceGroup = $r_pod->{'app'};
    if ( exists $r_appMap->{$serviceGroup} ) {
        $serviceGroup = $r_appMap->{$serviceGroup};
        if ( $::DEBUG > 5 ) { printf "getServiceGroup: mapping %s to appMap %s\n", $r_pod->{'app'}, $serviceGroup; }
    } elsif ( exists $r_pod->{'replicaset'} && $r_pod->{'replicaset'}->{'deployment'} ) {
        my $r_deployment = $r_pod->{'replicaset'}->{'deployment'};
        if ( exists $r_deployment->{'sgname'} ) {
            my $sgname = $r_deployment->{'sgname'};
            if ( $sgname ne $serviceGroup ) {
                $serviceGroup = $sgname;
                if ( $::DEBUG > 5 ) { printf "getServiceGroup: mapping %s to sgname %s\n", $r_pod->{'app'}, $serviceGroup; }
            }
        }
    }

    if ( $::DEBUG > 8 ) { printf("getServiceGroup app=%s serviceGroup=%s\n", $r_pod->{'app'}, $serviceGroup); }
    return $serviceGroup;
}

sub storeEnmServiceGroupInstances($$$$$$) {
    my ($dbh, $siteId, $date, $r_pods, $r_mappedPodByPodNameIP, $r_appMap) = @_;

    my $r_svcIdMap = getIdMap($dbh, "enm_servicegroup_names", "id", "name",  [] );
    my $r_sgRows = dbSelectAllHash($dbh,"
SELECT serviceid, serverid
FROM enm_servicegroup_instances
WHERE
 date = '$date' AND
 siteid = $siteId") or die "Failed to get query enm_servicegroup_instances";
    my %existingSgInstances = ();
    foreach my $r_sgRow ( @{$r_sgRows} ) {
        my $key = sprintf("%d:%d", $r_sgRow->{'serverid'}, $r_sgRow->{'serviceid'});
        $existingSgInstances{$key} = 1;
    }
    if ( $::DEBUG > 5 ) { print Dumper("storeEnmServiceGroupInstances: existingSgInstances", \%existingSgInstances); }

    foreach my $r_pod ( @{$r_pods} ) {
        # If we doen't have an app, we can't map to a service group
        if ( ! exists $r_pod->{'app'} ) {
            if ( $::DEBUG > 3 ) { print Dumper("storeEnmServiceGroupInstances: skipping r_pod", $r_pod); }
            next;
        }

        my $mappedPodKey = $r_pod->{'name'} . ":" . (defined $r_pod->{'podIP'} ? $r_pod->{'podIP'} : "");
        my $r_mappedPod = $r_mappedPodByPodNameIP->{$mappedPodKey};
        if ( ! defined $r_mappedPod ) {
            if ( $::DEBUG ) { print "storeEnmServiceGroupInstances: could not find mappedPod for $r_pod->{'name'}\n"; }
            next;
        }

        if ( $::DEBUG > 5 ) { print Dumper("storeEnmServiceGroupInstances: processing r_pod", $r_pod); }

        my $serviceGroup = getServiceGroup($r_pod, $r_appMap);

        if ( ! exists $r_svcIdMap->{$serviceGroup} ) {
            $r_svcIdMap = getIdMap($dbh, "enm_servicegroup_names", "id", "name",  [ $serviceGroup ] );
        }

        my $key = sprintf("%d:%d", $r_mappedPod->{'serverid'}, $r_svcIdMap->{$serviceGroup});
        if ( $::DEBUG > 5 ) { print "storeEnmServiceGroupInstances: checking key=$key for $r_pod->{'name'}\n"; }
        if ( ! exists $existingSgInstances{$key} ) {
            dbDo($dbh, sprintf("INSERT INTO enm_servicegroup_instances (siteid,date,serviceid,serverid) VALUES (%d, '%s', %d, %d)",
                                $siteId, $date, $r_svcIdMap->{$serviceGroup}, $r_mappedPod->{'serverid'}
                            )
                ) or die "Failed to add entry to enm_servicegroup_instances";
        }
    }
}

sub storePods($$$$$$$) {
    my ($dbh, $siteId, $date, $r_pods, $site_type, $r_appMap, $r_nodes) = @_;

    # Build map of node name -> node server id
    my %serverIds = ();
    foreach my $r_node ( @{$r_nodes} ) {
        $serverIds{$r_node->{'hostname'}} = $r_node->{'serverid'};
    }
    if ( $::DEBUG > 8 ) { print Dumper("storePods: serverIds", \%serverIds); }

    #
    # First, we get any existing mapping from yesterday,today or tomorrow so that
    # we can maintain the pod->server mapping across days
    #
    my $r_rows = dbSelectAllHash($dbh,"
SELECT
 k8s_pod.pod AS podname, k8s_pod.podIP AS podip, k8s_pod.serverid AS serverid, k8s_pod.date AS date, servers.hostname AS hostname
FROM k8s_pod, servers
WHERE
 k8s_pod.siteid = $siteId AND
 k8s_pod.date IN ( '$date', '$date' + INTERVAL 1 DAY, '$date' - INTERVAL 1 DAY ) AND
 k8s_pod.serverid = servers.id
") or die "Failed to query k8s_pod";

    my %mappedPodByPodNameIP = ();
    my %mappedPodByServerName = ();
    foreach my $r_row ( @{$r_rows} ) {
        if ( $::DEBUG > 6 ) { print Dumper("storePod: row=", $r_row); }
        my $key = $r_row->{'podname'} . ":" . (defined $r_row->{'podip'} ? $r_row->{'podip'} : "");
        my $r_mappedPod = $mappedPodByPodNameIP{$key};
        if ( ! defined $r_mappedPod ) {
            $r_mappedPod = {
                'podname'  => $r_row->{'podname'},
                'serverid' => $r_row->{'serverid'},
                'hostname' => $r_row->{'hostname'}
            };
            $mappedPodByPodNameIP{$key} = $r_mappedPod;
            if ( exists $mappedPodByServerName{$r_row->{'hostname'}} ) {
                push @{$mappedPodByServerName{$r_row->{'hostname'}}}, $r_mappedPod;
            } else {
                $mappedPodByServerName{$r_row->{'hostname'}} = [ $r_mappedPod ];
            }
        }
        if ( $r_row->{'date'} eq $date ) {
            $r_mappedPod->{'has_today'} = 1;
        }
    }
    if ( $::DEBUG > 5 ) { print Dumper("storePod: mappedPodByPodNameIP", \%mappedPodByPodNameIP); }
    my $r_appIdMap = getIdMap($dbh, "k8s_pod_app_names", "id", "name",  [] );

    #
    # Now, iterate through the pods and check if we have an existing mapping. If we don't get/create
    # the mapped server
    # If we don't have an existing row for today, insert it
    #
    foreach my $r_pod ( @{$r_pods} ) {
        if ( $::DEBUG > 4 ) { print Dumper("storePods: r_pod", $r_pod); }
        my $key = $r_pod->{'name'} . ":" . (defined $r_pod->{'podIP'} ? $r_pod->{'podIP'} : "");
        my $r_mappedPod = $mappedPodByPodNameIP{$key};
        if ( $::DEBUG > 6 ) { printf("storePod: key=%s exists=%s\n", $key, (defined $key ? "true" : "false")); }
        if ( ! defined $r_mappedPod ) {
            if ( (! exists $r_pod->{'kind'}) || ($r_pod->{'kind'} eq 'Job') ) {
                if ( $::DEBUG > 0 ) { print "storePods: skipping $r_pod->{'name'}, invalid kind\n"; }
                next;
            }

            my $mappedName = mapPodName($r_pod, \%mappedPodByServerName);
            if ( ! defined $mappedName ) {
                print "WARN: Cannot map name for $r_pod->{'name'}\n";
                next;
            }

            my $serverId = getServerIdWithoutFail($dbh, $siteId, $mappedName);
            if ( ! defined $serverId || $serverId == 0 ) {
                $serverId = createServer($dbh, $siteId, $mappedName, 'ENM_VM');
            }
            $r_mappedPod = {
                'podname'  => $r_pod->{'name'},
                'serverid' => $serverId,
                'hostname' => $mappedName
            };
            $mappedPodByPodNameIP{$key} = $r_mappedPod;
            if ( exists $mappedPodByServerName{$mappedName} ) {
                push @{$mappedPodByServerName{$mappedName}}, $r_mappedPod;
            } else {
                $mappedPodByServerName{$mappedName} = [ $r_mappedPod ];
            }
        }

        if ( ! exists $r_mappedPod->{'has_today'} ) {
            my $appId = undef;
            my $app = $r_pod->{'app'};
            if ( defined $app ) {
                $appId = $r_appIdMap->{$app};
                if ( ! defined $appId ) {
                    $r_appIdMap = getIdMap($dbh, "k8s_pod_app_names", "id", "name",  [ $app ] );
                    $appId = $r_appIdMap->{$app};
                }
            }
            if ( ! defined $appId ) {
                $appId = "NULL";
            }

            my $nodeId = undef;
            my $nodeName = $r_pod->{'nodeName'};
            if ( defined $nodeName && exists $serverIds{$nodeName} ) {
                $nodeId = $serverIds{$nodeName};
            }
            if ( ! defined $nodeId ) {
                $nodeId = "NULL";
            }

            dbDo($dbh,
                 sprintf("INSERT INTO k8s_pod (siteid,date,serverid,appid,pod,podIP,nodeId) VALUES (%d, '%s', %d, %s, '%s', %s, %s)",
                         $siteId, $date, $r_mappedPod->{'serverid'},  $appId,
                         $r_mappedPod->{'podname'}, $dbh->quote($r_pod->{'podIP'}), $nodeId
                     )
             ) or die "Failed to insert $r_pod->{'name'} into k8s_pod";
            $r_mappedPod->{'has_today'} = 1;
        }
    }

    if ( defined $site_type && $site_type eq 'TOR' ) {
        storeEnmServiceGroupInstances(
            $dbh,
            $siteId,
            $date,
            $r_pods,
            \%mappedPodByPodNameIP,
            $r_appMap
        );
    }
}

sub storeNodes($$$$) {
    my ($dbh, $siteId, $date, $r_nodes) = @_;

    my $cpuId = undef;

    dbDo($dbh, "DELETE FROM k8s_node WHERE siteid = $siteId AND date = '$date'")
        or die "Failed to remove old data";

    foreach my $r_node ( @{$r_nodes} ) {
        my $serverId = getServerIdWithoutFail( $dbh, $siteId, $r_node->{'hostname'} );
        if ( (! defined $serverId) || ($serverId == 0) ) {
            my $serverType = 'K8S_NODE';
            if ( defined $r_node->{'role'} && $r_node->{'role'} eq 'MASTER' ) {
                $serverType = 'K8S_MASTER';
            }
            $serverId = createServer( $dbh, $siteId, $r_node->{'hostname'}, $serverType );
        }
        $r_node->{'serverid'} = $serverId;

        if ( exists $r_node->{'memory'} ) {
            my %data = (
                'systemtype' => 'k8s_node',
                'memory' => $r_node->{'memory'},
                'cpugrp' => { '' => { 'name' => 'unknown', 'count' => $r_node->{'cpu'} } }
            );
            ServerCfg::store( $dbh, $siteId, $serverId, $date, \%data );
        }

        my @columns = ( 'siteid', 'date', 'serverid', 'intIP', 'kubeletVer' );
        my @values = (
            $siteId, $dbh->quote($date), $serverId,
            $dbh->quote($r_node->{'intIP'}), $dbh->quote($r_node->{'kubeletVer'})
        );
        dbDo($dbh, sprintf("INSERT INTO k8s_node (%s) VALUES (%s)", join(",", @columns), join(",", @values) ) )
            or die "Insert failed";
    }
}

sub storeHaEvents($$$$) {
    my ($dbh, $siteId, $r_haEvents, $r_nodes) = @_;

    if ( $#{$r_haEvents} == -1 ) {
        return;
    }

    # Build map of node name -> node server id
    my %serverIds = ();
    foreach my $r_node ( @{$r_nodes} ) {
        $serverIds{$r_node->{'hostname'}} = $r_node->{'serverid'};
    }

    dbDo(
        $dbh,
        sprintf(
            "DELETE FROM k8s_ha WHERE siteid = %d AND time BETWEEN '%s' AND '%s'",
            $siteId,
            $r_haEvents->[0]->{'time'},
            $r_haEvents->[$#{$r_haEvents}]->{'time'}
        )
    ) or die "Failed to remove old data";

    my %haContainerHash = ();
    for my $r_haEvent ( @{$r_haEvents} ) {
        if ( exists $r_haEvent->{'container'} ) {
            $haContainerHash{$r_haEvent->{'container'}} = 1;
        }
    }
    my @haContainerList = sort keys %haContainerHash;

    my $r_containerIdMap = getIdMap($dbh, "k8s_container_names", "id", "name",  \@haContainerList );

    for my $r_haEvent ( @{$r_haEvents} ) {
        my $containerId = 'NULL';
        if ( exists $r_haEvent->{'container'} && exists $r_containerIdMap->{$r_haEvent->{'container'}} ) {
            $containerId = $r_containerIdMap->{$r_haEvent->{'container'}};
        }

        my $workerId = 'NULL';
        if ( exists $r_haEvent->{'worker'} && exists $serverIds{$r_haEvent->{'worker'}} ) {
            $workerId = $serverIds{$r_haEvent->{'worker'}};
        }

        dbDo(
            $dbh,
            sprintf(
                "INSERT INTO k8s_ha (siteid, time, pod, containerid, workerid, type) VALUES (%d, '%s', '%s', %s, %s, '%s')",
                $siteId,
                $r_haEvent->{'time'},
                $r_haEvent->{'pod'},
                $containerId,
                $workerId,
                $r_haEvent->{'type'}
            )
        ) or die "Failed to insert row";
    }
}

sub storeCcdVer($$$$) {
    my ($dbh, $siteId, $date, $ccdVer) = @_;

    if ( ! defined $ccdVer ) {
        return;
    }

    dbDo($dbh, "DELETE FROM ccd_version WHERE siteid = $siteId AND date = '$date'") or die "Failed to remove from ccd_version";
    dbDo($dbh, "INSERT INTO ccd_version (siteid,date,version) VALUES ($siteId, '$date', '$ccdVer')") or die "Failed to insert to ccd_version";
}

sub storeSwim($$$$) {
    my ($dbh, $siteId, $date, $r_swim) = @_;

    if ( $::DEBUG > 6 ) { print Dumper("storeSwim: r_swim", $r_swim); }
    if ( $#{$r_swim} == -1 ) {
        return;
    }

    dbDo($dbh, "DELETE FROM swim WHERE siteid = $siteId AND date = '$date'") or die "Failed to remove from swim";
    foreach my $r_product ( @{$r_swim} ) {
        dbDo(
            $dbh,
            sprintf(
                "INSERT INTO swim (siteid, date, name, pnumber, revision, commercialName, semanticVersion) VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s')",
                $siteId,
                $date,
                $r_product->{'name'},
                $r_product->{'number'},
                $r_product->{'revision'},
                $r_product->{'commercialName'},
                $r_product->{'semanticVersion'}
            )
        ) or die "Failed to insert into swim";
    }
}

sub store($$$$$$$$$$) {
    my ($site, $site_type, $date, $r_nodes, $r_pods, $r_appMap, $r_haEvents, $ccdVer, $r_helmUpdates, $r_swim) = @_;

    my $dbh = connect_db();

    my $siteId = getSiteId( $dbh, $site );
    ($siteId > -1 ) or die "Failed to get siteid for $site";

    storeNodes($dbh, $siteId, $date, $r_nodes);
    storePods($dbh, $siteId, $date, $r_pods, $site_type, $r_appMap, $r_nodes);
    storeHaEvents($dbh, $siteId, $r_haEvents, $r_nodes);
    storeCcdVer($dbh, $siteId, $date, $ccdVer);
    storeHelm($dbh, $siteId, $r_helmUpdates);
    storeSwim($dbh, $siteId, $date, $r_swim);

    $dbh->disconnect();
}

sub addNodesFromPods($$) {
    my ($r_pods, $r_nodes) = @_;

    my %nodesByName = ();
    foreach my $r_node ( @{$r_nodes} ) {
        $nodesByName{$r_node->{'hostname'}} = $r_node;
    }
    if ( $::DEBUG > 5) { print Dumper("addNodesFromPods: known nodes", keys %nodesByName); }
    foreach my $r_pod ( @{$r_pods} ) {
        my $nodeName = $r_pod->{'nodeName'};
        if ( defined $nodeName && ! exists $nodesByName{$nodeName} ) {
            if ( $::DEBUG > 5) { print "addNodesFromPods: adding $nodeName\n"; }
            my $r_node = { 'hostname' => $nodeName };
            $nodesByName{$nodeName} = $r_node;
            push @{$r_nodes}, $r_node;
        }
    }
}

sub main() {
    my ($site, $date, $dir, $site_type, $incrFile, $outDir, $appMapFile);
    my $result = GetOptions(
        "site=s"    => \$site,
        "site_type=s"     => \$site_type,
        "dir=s"     => \$dir,
        "date=s"    => \$date,
        "incr=s"    => \$incrFile,
        "outdir=s" => \$outDir,
        "appmap=s" => \$appMapFile,
        "debug=s"   => \$::DEBUG
    );
    ($result == 1) or die "Invalid options";
    setStatsDB_Debug($::DEBUG);
    $Data::Dumper::Indent = 1;

    my $r_appMap = {};
    if ( defined $appMapFile ) {
        $r_appMap = parseAppMap($appMapFile);
    }

    my $r_Incr = incrRead($incrFile);

    my ($r_nodes, $r_pods, $r_replicaSets, $ccdVer);
    if ( exists $r_Incr->{'nodes'} ) {
        $r_nodes = $r_Incr->{'nodes'};
        $r_pods = $r_Incr->{'pods'};
        if ( exists $r_Incr->{'replicaSets'} ) {
            $r_replicaSets = $r_Incr->{'replicaSets'};
        } else {
            $r_replicaSets = {};
        }
        $ccdVer = $r_Incr->{'ccdVer'};
    } else {
        my $configFile = "$dir/config.json";
        if ( -r $configFile ) {
            ($r_nodes, $r_pods, $r_replicaSets, $ccdVer) = parseConfig($dir);
        } else {
            $r_nodes = parseNodes($dir);
            $r_pods = parsePods($dir);
            $r_replicaSets = {};
        }
        $r_Incr->{'nodes'} = $r_nodes;
        $r_Incr->{'pods'} = $r_pods;
        $r_Incr->{'replicaSets'} = $r_replicaSets;
        $r_Incr->{'ccdVer'} = $ccdVer;
    }
    my $r_rawEvents = undef;
    my @helmUpdates = ();
    my $r_podStatusByPodUid = {};
    my $r_swim = undef;
    my $r_haEvents = undef;
    if ( -r $dir . "/delta.json.001" ) {
        my $r_delta = parseDelta($dir,$r_Incr);
        $r_rawEvents = $r_delta->{'events'};
        $r_haEvents = processEvents($r_rawEvents, $r_Incr, $r_nodes, $r_pods, $r_replicaSets, $outDir);

        my $r_helmUpdates = processHelm($r_delta->{'helm'}, $r_Incr, $date);
        $r_podStatusByPodUid = processPodStatus($r_delta->{'podstatus'}, $r_Incr);
        $r_swim = processSwim($r_delta->{'swim'}, $r_Incr);
        my $r_podChartByUid = getPodChart($r_pods);
        foreach my $r_helmUpdate ( @{$r_helmUpdates} ) {
            my $r_result = processEventsForChart($r_helmUpdate, $r_rawEvents, $r_podStatusByPodUid, $r_podChartByUid);
            writeHelmUpdate($r_result, $outDir);
            push @helmUpdates, $r_result;
        }
    } else {
        $r_rawEvents = parseEvents($dir, $r_Incr);
        $r_haEvents = processEvents($r_rawEvents, $r_Incr, $r_nodes, $r_pods, $r_replicaSets, $outDir);
    }



    # We may not have the nodes info present in the config.json, in this case add in minimal (hostname)
    # info from the info in the pods
    addNodesFromPods($r_pods, $r_nodes);

    store($site, $site_type, $date, $r_nodes, $r_pods, $r_appMap, $r_haEvents, $ccdVer, \@helmUpdates, $r_swim);

    incrWrite($incrFile, $r_Incr);
}

1;
