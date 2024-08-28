package misc::AutoProv;

use strict;
use warnings;

use Data::Dumper;

use StatsDB;
use StatsTime;
use EnmServiceGroup;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};
    $self->{'srvIdMap'} = {};
    foreach my $sg ('autoprovisioning', 'consautoprov') {
        my $r_srvMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, $sg);
        while ( my ($srv, $srvId) = each %{$r_srvMap} ) {
            $self->{'srvIdMap'}->{$srv} = $srvId;
        }
    }

    if ( exists $r_incr->{'misc::AutoProv'} ) {
        $self->{'data'} = $r_incr->{'misc::AutoProv'};
    } else {
        $self->{'data'} = {
            'apservDailyStats' => [],
            'apservEventStats' => {
                'order_project' => {},
                'order_node'    => {}
            },
            'orderProjectMetadata' => {},
            'orderNodeMetadata' => {},
            'integrateNodeMetadata' => {},
            'deleteNodeMetadata' => {},
            'deleteProjectMetadata' => {},
            'hardwareReplaceMetadata' => {}
        };
    }

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }

    if ( $::DEBUG > 5 ) { print Dumper("misc::AutoProv::init subscriptions",\@subscriptions) ; }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }

    if ( $::DEBUG > 7 ) { print "misc::AutoProv::handle timestamp=$timestamp message=$message\n"; }

    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.COMMAND_LOGGER\] \(.*\) \[[^,]+, AUTO_PROVISIONING/ ) {
        $self->parseAutoProv($timestamp,$host,$message);
    } elsif ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.COMMAND_LOGGER\] \(.*\) \[[^,]+, CM_CELL_MANAGEMENT/ ) {
        if ( $messageSize < 10000 ) {
            $self->parseCellMgt($timestamp,$host,$message);
        } else {
            # Workaround for problem with CM_CELL_MANAGEMENT messages (TORF-261389)
            print "WARN: AutoProv::handle Discarding excessive size message for CM_CELL_MANAGEMENT\n";
        }
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}


sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("misc::AutoProv::done self", $self); }

    storeApservData($self->{'data'}->{'apservDailyStats'},
                    $self->{'data'}->{'apservEventStats'},
                    $self->{'date'},
                    $self->{'siteId'},
                    $dbh);

    $r_incr->{'misc::AutoProv'} = $self->{'data'};
}

#
# Internal methods
#
sub parseAutoProv($$$$) {
    my ($self,$timestamp,$host,$message) = @_;

    if ( $::DEBUG > 6 ) { print "misc::AutoProv::parseAutoProv timestamp=$timestamp message=$message\n"; }

    # Get execution times of various phases of different AP events
    # ORDER_PROJECT
    if ( $message =~ /AUTO_PROVISIONING\.ORDER_PROJECT.*Project\s*=\s*([^,]*),/ ||
             $message =~ /AUTO_PROVISIONING\.ORDER_PROJECT.*,\s*([^,]*)\.zip/ ) {
        my $project = $1;
        if ( $message =~ /AUTO_PROVISIONING\.ORDER_PROJECT,\s*(?:FINISHED_WITH_SUCCESS|FINISHED_WITH_ERROR)/ ) {
            # Add an empty execution times hash for 'ORDER_PROJECT' usecases failed at the very beginning itself
            if ( (! exists $self->{'data'}->{'orderProjectMetadata'}->{$project}->{'new_order_project'}) || $self->{'data'}->{'orderProjectMetadata'}->{$project}->{'new_order_project'} ) {
                my $orderProjectExecTimes = getEmptyExecTimesHash('ORDER_PROJECT');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project} ) {
                    $self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project}}, $orderProjectExecTimes);
                $self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project}->[$#{$self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project}}]->{'time'} = es2sql($timestamp);
            }

            $self->{'data'}->{'orderProjectMetadata'}->{$project}->{'new_order_project'} = 1;
            $self->{'data'}->{'orderProjectMetadata'}->{$project}->{'last_finish_epoch'} = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
        } else {
            if ( (! exists $self->{'data'}->{'orderProjectMetadata'}->{$project}->{'new_order_project'}) || $self->{'data'}->{'orderProjectMetadata'}->{$project}->{'new_order_project'} ) {
                $self->{'data'}->{'orderProjectMetadata'}->{$project}->{'new_order_project'} = 0;
                my $orderProjectExecTimes = getEmptyExecTimesHash('ORDER_PROJECT');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project} ) {
                    $self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project}}, $orderProjectExecTimes);
            }

            # Logic to handle the cases where some 'PHASE' log lines come after
            # 'FINISHED_WITH_SUCCESS' or 'FINISHED_WITH_ERROR' due to exactly same timestamps
            my $projectIndex = $#{$self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project}};
            my $currentPhaseEpoch = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
            if ( exists $self->{'data'}->{'orderProjectMetadata'}->{$project}->{'last_finish_epoch'} &&
                     ($currentPhaseEpoch - $self->{'data'}->{'orderProjectMetadata'}->{$project}->{'last_finish_epoch'}) == 0 ) {
                $projectIndex--;
            }

            my $messageMatch = 0;
            if ( $message =~ /AUTO_PROVISIONING\.ORDER_PROJECT\.VALIDATE_PROJECT.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project}->[$projectIndex]->{'VALIDATE_PROJECT'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.ORDER_PROJECT\.CREATE_PROJECT_MO.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project}->[$projectIndex]->{'CREATE_PROJECT_MO'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.ORDER_PROJECT\.CREATE_AND_WRITE_PROJECT_ARTIFACTS.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project}->[$projectIndex]->{'CREATE_AND_WRITE_PROJECT_ARTIFACTS'} = $1;
            }

            if ( $messageMatch && $self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project}->[$projectIndex]->{'time'} eq "\\N" ) {
                $self->{'data'}->{'apservEventStats'}->{'order_project'}->{$project}->[$projectIndex]->{'time'} = es2sql($timestamp);
            }
        }
    }
    # ORDER_NODE
    elsif ( $message =~ /AUTO_PROVISIONING\.ORDER_NODE.*,\s*Project\s*=\s*([^,]*),\s*Node\s*=\s*([^,]*),/ ) {
        my $projectNode = $1 . '@@@' . $2;
        if ( $message =~ /AUTO_PROVISIONING\.ORDER_NODE,\s*(?:FINISHED_WITH_SUCCESS|FINISHED_WITH_ERROR)/ ) {
            # Add an empty execution times hash for 'ORDER_NODE' usecases failed at the very beginning itself
            if ( (! exists $self->{'data'}->{'orderNodeMetadata'}->{$projectNode}->{'new_order_node'}) || $self->{'data'}->{'orderNodeMetadata'}->{$projectNode}->{'new_order_node'} ) {
                my $orderNodeExecTimes = getEmptyExecTimesHash('ORDER_NODE');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode} ) {
                    $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}}, $orderNodeExecTimes);
                $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$#{$self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}}]->{'time'} = es2sql($timestamp);
            }

            $self->{'data'}->{'orderNodeMetadata'}->{$projectNode}->{'new_order_node'} = 1;
            $self->{'data'}->{'orderNodeMetadata'}->{$projectNode}->{'last_finish_epoch'} = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
        } else {
            if ( (! exists $self->{'data'}->{'orderNodeMetadata'}->{$projectNode}->{'new_order_node'}) || $self->{'data'}->{'orderNodeMetadata'}->{$projectNode}->{'new_order_node'} ) {
                $self->{'data'}->{'orderNodeMetadata'}->{$projectNode}->{'new_order_node'} = 0;
                my $orderNodeExecTimes = getEmptyExecTimesHash('ORDER_NODE');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode} ) {
                    $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}}, $orderNodeExecTimes);
            }

            # Logic to handle the cases where some 'PHASE' log lines come after
            # 'FINISHED_WITH_SUCCESS' or 'FINISHED_WITH_ERROR' due to exactly same timestamps
            my $projectNodeIndex = $#{$self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}};
            my $currentPhaseEpoch = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
            if ( exists $self->{'data'}->{'orderNodeMetadata'}->{$projectNode}->{'last_finish_epoch'} &&
                     ($currentPhaseEpoch - $self->{'data'}->{'orderNodeMetadata'}->{$projectNode}->{'last_finish_epoch'}) == 0 ) {
                $projectNodeIndex--;
            }

            my $messageMatch = 0;
            if ( $message =~ /AUTO_PROVISIONING\.ORDER_NODE\.CREATE_NODE_MO.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'CREATE_NODE_MO'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.ORDER_NODE\.CREATE_NODE_CHILDREN_MOS.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'CREATE_NODE_CHILDREN_MOS'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.ORDER_NODE\.SETUP_CONFIGURATION.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'SETUP_CONFIGURATION'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.ORDER_NODE\.ADD_NODE.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'ADD_NODE'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.ORDER_NODE\.GENERATE_SECURITY.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'GENERATE_SECURITY'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.ORDER_NODE\.CREATE_FILE_ARTIFACT.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                my $execTime = $1;
                $messageMatch = 1;
                if ( $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'CREATE_FILE_ARTIFACT'} =~ /^\d+$/ ) {
                    $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'CREATE_FILE_ARTIFACT'} += $execTime;
                } else {
                    $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'CREATE_FILE_ARTIFACT'} = $execTime;
                }
            } elsif ( $message =~ /AUTO_PROVISIONING\.ORDER_NODE\.CREATE_NODE_USER_CREDENTIALS.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'CREATE_NODE_USER_CREDENTIALS'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.ORDER_NODE\.BIND_DURING_ORDER.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'BIND_DURING_ORDER'} = $1;
            }

            if ( $messageMatch && $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'time'} eq "\\N" ) {
                $self->{'data'}->{'apservEventStats'}->{'order_node'}->{$projectNode}->[$projectNodeIndex]->{'time'} = es2sql($timestamp);
            }
        }
    }
    # INTEGRATE_NODE
    elsif ( $message =~ /AUTO_PROVISIONING\.INTEGRATE.*,\s*Project\s*=\s*([^,]*),\s*Node\s*=\s*([^,]*),/ ) {
        my $projectNode = $1 . '@@@' . $2;
        if ( $message =~ /AUTO_PROVISIONING\.INTEGRATE,\s*(?:FINISHED_WITH_SUCCESS|FINISHED_WITH_ERROR)/ ) {
            # Add an empty execution times hash for 'INTEGRATE_NODE' usecases failed at the very beginning itself
            if ( (! exists $self->{'data'}->{'integrateNodeMetadata'}->{$projectNode}->{'new_integrate_node'}) || $self->{'data'}->{'integrateNodeMetadata'}->{$projectNode}->{'new_integrate_node'} ) {
                my $integrateNodeExecTimes = getEmptyExecTimesHash('INTEGRATE_NODE');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode} ) {
                    $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}}, $integrateNodeExecTimes);
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$#{$self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}}]->{'time'} = es2sql($timestamp);
            }

            $self->{'data'}->{'integrateNodeMetadata'}->{$projectNode}->{'new_integrate_node'} = 1;
            $self->{'data'}->{'integrateNodeMetadata'}->{$projectNode}->{'last_finish_epoch'} = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
        } else {
            if ( (! exists $self->{'data'}->{'integrateNodeMetadata'}->{$projectNode}->{'new_integrate_node'}) || $self->{'data'}->{'integrateNodeMetadata'}->{$projectNode}->{'new_integrate_node'} ) {
                $self->{'data'}->{'integrateNodeMetadata'}->{$projectNode}->{'new_integrate_node'} = 0;
                my $integrateNodeExecTimes = getEmptyExecTimesHash('INTEGRATE_NODE');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode} ) {
                    $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}}, $integrateNodeExecTimes);
            }

            # Logic to handle the cases where some 'PHASE' log lines come after
            # 'FINISHED_WITH_SUCCESS' or 'FINISHED_WITH_ERROR' due to exactly same timestamps
            my $projectNodeIndex = $#{$self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}};
            my $currentPhaseEpoch = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
            if ( exists $self->{'data'}->{'integrateNodeMetadata'}->{$projectNode}->{'last_finish_epoch'} &&
                     ($currentPhaseEpoch - $self->{'data'}->{'integrateNodeMetadata'}->{$projectNode}->{'last_finish_epoch'}) == 0 ) {
                $projectNodeIndex--;
            }

            my $messageMatch = 0;
            if ( $message =~ /AUTO_PROVISIONING\.INTEGRATE_NODE\.INITIATE_SYNC_NODE.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'INITIATE_SYNC_NODE'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.INTEGRATE_NODE\.IMPORT_CONFIGURATIONS.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'IMPORT_CONFIGURATIONS'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.INTEGRATE_NODE\.ENABLE_SUPERVISION.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'ENABLE_SUPERVISION'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.INTEGRATE_NODE\.CREATE_CV.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                my $execTime = $1;
                $messageMatch = 1;
                if ( $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'CREATE_CV'} =~ /^\d+$/ ) {
                    $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'CREATE_CV'} += $execTime;
                } else {
                    $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'CREATE_CV'} = $execTime;
                }
            } elsif ( $message =~ /AUTO_PROVISIONING\.INTEGRATE_NODE\.CREATE_BACKUP.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'CREATE_BACKUP'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.INTEGRATE_NODE\.ACTIVATE_OPTIONAL_FEATURES.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'ACTIVATE_OPTIONAL_FEATURES'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.INTEGRATE_NODE\.GPS_POSITION_CHECK.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'GPS_POSITION_CHECK'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.INTEGRATE_NODE\.UNLOCK_CELLS.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'UNLOCK_CELLS'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.INTEGRATE_NODE\.UPLOAD_CV.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'UPLOAD_CV'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.INTEGRATE_NODE\.UPLOAD_BACKUP.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'UPLOAD_BACKUP'} = $1;
            }

            if ( $messageMatch && $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'time'} eq "\\N" ) {
                $self->{'data'}->{'apservEventStats'}->{'integrate_node'}->{$projectNode}->[$projectNodeIndex]->{'time'} = es2sql($timestamp);
            }
        }
    }
    # DELETE_NODE
    elsif ( $message =~ /AUTO_PROVISIONING\.DELETE_NODE.*,\s*Project\s*=\s*([^,]*),\s*Node\s*=\s*([^,]*),/ ) {
        my $projectNode = $1 . '@@@' . $2;
        if ( $message =~ /AUTO_PROVISIONING\.DELETE_NODE,\s*(?:FINISHED_WITH_SUCCESS|FINISHED_WITH_ERROR)/ ) {
            # Add an empty execution times hash for 'DELETE_NODE' usecases failed at the very beginning itself
            if ( (! exists $self->{'data'}->{'deleteNodeMetadata'}->{$projectNode}->{'new_delete_node'}) || $self->{'data'}->{'deleteNodeMetadata'}->{$projectNode}->{'new_delete_node'} ) {
                my $deleteNodeExecTimes = getEmptyExecTimesHash('DELETE_NODE');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode} ) {
                    $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}}, $deleteNodeExecTimes);
                $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}->[$#{$self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}}]->{'time'} = es2sql($timestamp);
            }

            $self->{'data'}->{'deleteNodeMetadata'}->{$projectNode}->{'new_delete_node'} = 1;
            $self->{'data'}->{'deleteNodeMetadata'}->{$projectNode}->{'last_finish_epoch'} = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
        } else {
            if ( (! exists $self->{'data'}->{'deleteNodeMetadata'}->{$projectNode}->{'new_delete_node'}) || $self->{'data'}->{'deleteNodeMetadata'}->{$projectNode}->{'new_delete_node'} ) {
                $self->{'data'}->{'deleteNodeMetadata'}->{$projectNode}->{'new_delete_node'} = 0;
                my $deleteNodeExecTimes = getEmptyExecTimesHash('DELETE_NODE');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode} ) {
                    $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}}, $deleteNodeExecTimes);
            }

            # Logic to handle the cases where some 'PHASE' log lines come after
            # 'FINISHED_WITH_SUCCESS' or 'FINISHED_WITH_ERROR' due to exactly same timestamps
            my $projectNodeIndex = $#{$self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}};
            my $currentPhaseEpoch = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
            if ( exists $self->{'data'}->{'deleteNodeMetadata'}->{$projectNode}->{'last_finish_epoch'} &&
                     ($currentPhaseEpoch - $self->{'data'}->{'deleteNodeMetadata'}->{$projectNode}->{'last_finish_epoch'}) == 0 ) {
                $projectNodeIndex--;
            }

            my $messageMatch = 0;
            if ( $message =~ /AUTO_PROVISIONING\.DELETE_NODE\.REMOVE_NODE.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}->[$projectNodeIndex]->{'REMOVE_NODE'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.DELETE_NODE\.CANCEL_SECURITY.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}->[$projectNodeIndex]->{'CANCEL_SECURITY'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.DELETE_NODE\.REMOVE_BACKUP.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}->[$projectNodeIndex]->{'REMOVE_BACKUP'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.DELETE_NODE\.DELETE_RAW_AND_GENERATED_NODE_ARTIFACTS.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}->[$projectNodeIndex]->{'DELETE_RAW_AND_GENERATED_NODE_ARTIFACTS'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.DELETE_NODE\.DELETE_NODE_MO.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}->[$projectNodeIndex]->{'DELETE_NODE_MO'} = $1;
            }

            if ( $messageMatch && $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}->[$projectNodeIndex]->{'time'} eq "\\N" ) {
                $self->{'data'}->{'apservEventStats'}->{'delete_node'}->{$projectNode}->[$projectNodeIndex]->{'time'} = es2sql($timestamp);
            }
        }
    }
    # DELETE_PROJECT
    elsif ( $message =~ /^AUTO_PROVISIONING\.DELETE_PROJECT.*Project\s*=\s*([^,]*),/ ||
                $message =~ /^AUTO_PROVISIONING\.DELETE_PROJECT.*,\s*([^,]*)\.zip/ ) {
        my $project = $1;
        if ( $message =~ /AUTO_PROVISIONING\.DELETE_PROJECT,\s*(?:FINISHED_WITH_SUCCESS|FINISHED_WITH_ERROR)/ ) {
            # Add an empty execution times hash for 'DELETE_PROJECT' usecases failed at the very beginning itself
            if ( (! exists $self->{'data'}->{'deleteProjectMetadata'}->{$project}->{'new_delete_project'}) || $self->{'data'}->{'deleteProjectMetadata'}->{$project}->{'new_delete_project'} ) {
                my $orderProjectExecTimes = getEmptyExecTimesHash('DELETE_PROJECT');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project} ) {
                    $self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project}}, $orderProjectExecTimes);
                $self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project}->[$#{$self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project}}]->{'time'} = es2sql($timestamp);
            }

            $self->{'data'}->{'deleteProjectMetadata'}->{$project}->{'new_delete_project'} = 1;
            $self->{'data'}->{'deleteProjectMetadata'}->{$project}->{'last_finish_epoch'} = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
        } else {
            if ( (! exists $self->{'data'}->{'deleteProjectMetadata'}->{$project}->{'new_delete_project'}) || $self->{'data'}->{'deleteProjectMetadata'}->{$project}->{'new_delete_project'} ) {
                $self->{'data'}->{'deleteProjectMetadata'}->{$project}->{'new_delete_project'} = 0;
                my $orderProjectExecTimes = getEmptyExecTimesHash('DELETE_PROJECT');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project} ) {
                    $self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project}}, $orderProjectExecTimes);
            }

            # Logic to handle the cases where some 'PHASE' log lines come after
            # 'FINISHED_WITH_SUCCESS' or 'FINISHED_WITH_ERROR' due to exactly same timestamps
            my $projectIndex = $#{$self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project}};
            my $currentPhaseEpoch = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
            if ( exists $self->{'data'}->{'deleteProjectMetadata'}->{$project}->{'last_finish_epoch'} &&
                     ($currentPhaseEpoch - $self->{'data'}->{'deleteProjectMetadata'}->{$project}->{'last_finish_epoch'}) == 0 ) {
                $projectIndex--;
            }

            my $messageMatch = 0;
            if ( $message =~ /AUTO_PROVISIONING\.DELETE_PROJECT\.DELETE_RAW_AND_GENERATED_PROJECT_ARTIFACTS.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project}->[$projectIndex]->{'DELETE_RAW_AND_GENERATED_PROJECT_ARTIFACTS'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.DELETE_PROJECT\.DELETE_PROJECT_MO.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project}->[$projectIndex]->{'DELETE_PROJECT_MO'} = $1;
            }

            if ( $messageMatch && $self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project}->[$projectIndex]->{'time'} eq "\\N" ) {
                $self->{'data'}->{'apservEventStats'}->{'delete_project'}->{$project}->[$projectIndex]->{'time'} = es2sql($timestamp);
            }
        }
    }

    #Hardware Replace
    elsif ( $message =~ /AUTO_PROVISIONING\.HARDWARE_REPLACE.*Node\s*=\s*([^,]*),/ ) {
        my $node = $1;
        if ( $message =~ /AUTO_PROVISIONING\.HARDWARE_REPLACE,\s*(?:FINISHED_WITH_SUCCESS|FINISHED_WITH_ERROR)/ ) {
            # Add an empty execution times hash for 'HARDWARE_REPLACE' usecases failed at the very beginning itself
            if ( (! exists $self->{'data'}->{'hardwareReplaceMetadata'}->{$node}->{'new_hardware_replace'}) || $self->{'data'}->{'hardwareReplaceMetadata'}->{$node}->{'new_hardware_replace'} ) {
                my $hardwareReplaceExecTimes = getEmptyExecTimesHash('HARDWARE_REPLACE');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node} ) {
                    $self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node}}, $hardwareReplaceExecTimes);
                $self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node}->[$#{$self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node}}]->{'time'} = es2sql($timestamp);
            }

            $self->{'data'}->{'hardwareReplaceMetadata'}->{$node}->{'new_hardware_replace'} = 1;
            $self->{'data'}->{'hardwareReplaceMetadata'}->{$node}->{'last_finish_epoch'} = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
        } else {
            if ( (! exists $self->{'data'}->{'hardwareReplaceMetadata'}->{$node}->{'new_hardware_replace'}) || $self->{'data'}->{'hardwareReplaceMetadata'}->{$node}->{'new_hardware_replace'} ) {
                $self->{'data'}->{'hardwareReplaceMetadata'}->{$node}->{'new_hardware_replace'} = 0;
                my $hardwareReplaceExecTimes = getEmptyExecTimesHash('HARDWARE_REPLACE');
                if ( ! exists $self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node} ) {
                    $self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node} = [];
                }
                push (@{$self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node}}, $hardwareReplaceExecTimes);
            }

            # Logic to handle the cases where some 'PHASE' log lines come after
            # 'FINISHED_WITH_SUCCESS' or 'FINISHED_WITH_ERROR' due to exactly same timestamps
            my $projectIndex = $#{$self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node}};
            my $currentPhaseEpoch = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC, $StatsTime::TZ_GMT);
            if ( exists $self->{'data'}->{'hardwareReplaceMetadata'}->{$node}->{'last_finish_epoch'} &&
                     ($currentPhaseEpoch - $self->{'data'}->{'hardwareReplaceMetadata'}->{$node}->{'last_finish_epoch'}) == 0 ) {
                $projectIndex--;
            }

            my $messageMatch = 0;
            if ( $message =~ /AUTO_PROVISIONING\.HARDWARE_REPLACE\.GENERATE_HARDWARE_REPLACE_NODE_DATA.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node}->[$projectIndex]->{'GENERATE_HARDWARE_REPLACE_NODE_DATA'} = $1;
            } elsif ( $message =~ /AUTO_PROVISIONING\.HARDWARE_REPLACE\.GENERATE_HARDWARE_REPLACE_ICF.*FINISHED_WITH_SUCCESS.*EXECUTION_TIME\s*=\s*(\d+)\s*milliseconds/ ) {
                $messageMatch = 1;
                $self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node}->[$projectIndex]->{'GENERATE_HARDWARE_REPLACE_ICF'} = $1;
            }

            if ( $messageMatch && $self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node}->[$projectIndex]->{'time'} eq "\\N" ) {
                $self->{'data'}->{'apservEventStats'}->{'hardware_replace'}->{$node}->[$projectIndex]->{'time'} = es2sql($timestamp);
            }
        }
    }

    # Get 'daily totals' of different AP events
    if ( $message =~/AUTO_PROVISIONING.(ORDER_PROJECT|ORDER_NODE|INTEGRATE|DELETE_NODE|DELETE_PROJECT),\s*FINISHED_WITH_SUCCESS.*EXECUTION_TIME=(\d+).*TOTAL_NODE\(S\)=(\d+)/ ) {
        my $serverId = $self->{'srvIdMap'}->{$host};
        push @{$self->{'data'}->{'apservDailyStats'}}, {
            'time'                         => es2sql($timestamp),
            'serverid'                     => $serverId,
            'status'                       => "success",
            'useCaseType'                  => $1,
            'executionTime'                => $2/1000,
            'totalNode'                    => $3,
            'view'                         => "Metric",
            'relationType'                 => "NA"
        };
    } elsif ( $message =~/AUTO_PROVISIONING.(INTEGRATE),\s*FINISHED_WITH_SUCCESS/ ) {
        my $serverId = $self->{'srvIdMap'}->{$host};
        push @{$self->{'data'}->{'apservDailyStats'}}, {
            'time'                         => es2sql($timestamp),
            'serverid'                     => $serverId,
            'status'                       => "success",
            'useCaseType'                  => $1,
            'executionTime'                => "0",
            'totalNode'                    => "0",
            'view'                         => "Metric",
            'relationType'                 => "NA"
        };
    } elsif ( $message =~/AUTO_PROVISIONING.(ORDER_PROJECT|ORDER_NODE|INTEGRATE|DELETE_NODE|DELETE_PROJECT),\s*FINISHED_WITH_ERROR/ ) {
        my $serverId = $self->{'srvIdMap'}->{$host};
        push @{$self->{'data'}->{'apservDailyStats'}}, {
            'time'                         => es2sql($timestamp),
            'serverid'                     => $serverId,
            'status'                       => "failure",
            'useCaseType'                  => $1,
            'executionTime'                => "0",
            'totalNode'                    => "0",
            'view'                         => "Metric",
            'relationType'                 => "NA"
        };
    }
}

sub parseCellMgt($$$$) {
    my ($self,$timestamp,$host,$message) = @_;

    if ( $::DEBUG > 6 ) { print "misc::AutoProv::parseCellMgt timestamp=$timestamp message=$message\n"; }

    if ( $message =~/CM_CELL_MANAGEMENT\.(.*?),\s*(.*?),\s*(.*?),.*Relation Type\s*=(.*?),.*Request Result\s*=\s*(.*?),.*EXECUTION_TIME\s*=(.*)]/ ) {
        my $serverId = $self->{'srvIdMap'}->{$host};
        push @{$self->{'data'}->{'apservDailyStats'}}, {
            'time'                        => es2sql($timestamp),
            'serverid'                    => $serverId,
            'status'                      => $5,
            'useCaseType'                 => $1,
            'executionTime'               => $6,
            'totalNode'                   => "0",
            'view'                        => $3,
            'relationType'                => $4
        };
    }
}

sub es2sql($) {
    my ($timestamp) = @_;
    my $time = parseTime($timestamp,$StatsTime::TIME_ELASTICSEARCH_MSEC);
    return formatTime($time,$StatsTime::TIME_SQL);
}

sub getEmptyExecTimesHash($) {
    my ( $usecase ) = @_;
    my $execTimesHash = {};

    if ( $usecase eq 'ORDER_PROJECT' ) {
        $execTimesHash = {
            'time'                               => '\N',
            'VALIDATE_PROJECT'                   => '\N',
            'CREATE_PROJECT_MO'                  => '\N',
            'CREATE_AND_WRITE_PROJECT_ARTIFACTS' => '\N'
        };
    }
    elsif ( $usecase eq 'ORDER_NODE' ) {
        $execTimesHash = {
            'time'                         => '\N',
            'CREATE_NODE_MO'               => '\N',
            'CREATE_NODE_CHILDREN_MOS'     => '\N',
            'SETUP_CONFIGURATION'          => '\N',
            'ADD_NODE'                     => '\N',
            'GENERATE_SECURITY'            => '\N',
            'CREATE_FILE_ARTIFACT'         => '\N',
            'CREATE_NODE_USER_CREDENTIALS' => '\N',
            'BIND_DURING_ORDER'            => '\N'
        };
    }
    elsif ( $usecase eq 'INTEGRATE_NODE' ) {
        $execTimesHash = {
            'time'                       => '\N',
            'INITIATE_SYNC_NODE'         => '\N',
            'IMPORT_CONFIGURATIONS'      => '\N',
            'ENABLE_SUPERVISION'         => '\N',
            'CREATE_CV'                  => '\N',
            'CREATE_BACKUP'              => '\N',
            'ACTIVATE_OPTIONAL_FEATURES' => '\N',
            'GPS_POSITION_CHECK'         => '\N',
            'UNLOCK_CELLS'               => '\N',
            'UPLOAD_CV'                  => '\N',
            'UPLOAD_BACKUP'              => '\N'
        };
    }
    elsif ( $usecase eq 'DELETE_NODE' ) {
        $execTimesHash = {
            'time'                                    => '\N',
            'REMOVE_NODE'                             => '\N',
            'CANCEL_SECURITY'                         => '\N',
            'REMOVE_BACKUP'                           => '\N',
            'DELETE_RAW_AND_GENERATED_NODE_ARTIFACTS' => '\N',
            'DELETE_NODE_MO'                          => '\N'
        };
    }
    elsif ( $usecase eq 'DELETE_PROJECT' ) {
        $execTimesHash = {
            'time'                                       => '\N',
            'DELETE_RAW_AND_GENERATED_PROJECT_ARTIFACTS' => '\N',
            'DELETE_PROJECT_MO'                          => '\N'
        };
    }
    elsif ( $usecase eq 'HARDWARE_REPLACE' ) {
        $execTimesHash = {
            'time'                                 => '\N',
            'GENERATE_HARDWARE_REPLACE_NODE_DATA'  => '\N',
            'GENERATE_HARDWARE_REPLACE_ICF'        => '\N'
        };
    }

    return $execTimesHash;
}


sub storeApservData($$$$) {
    my ( $apservDailyStats, $apservEventStats, $date, $siteId, $dbh ) = @_;

    # Store AP 'Daily Stats' data
    my $bcpFileApservDailyStats= getBcpFileName("enm_apserv_daily_stats");
    open (BCP, "> $bcpFileApservDailyStats") or die "Failed to open $bcpFileApservDailyStats";
    foreach my $apservStats (@{$apservDailyStats}) {
        print BCP "$siteId\t$apservStats->{'time'}\t$apservStats->{'serverid'}\t$apservStats->{'useCaseType'}\t$apservStats->{'status'}\t$apservStats->{'executionTime'}\t$apservStats->{'totalNode'}\t$apservStats->{'view'}\t$apservStats->{'relationType'}\n";
    }
    close BCP;

    dbDo( $dbh, "DELETE FROM enm_apserv_metrics WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
        or die "Failed to delete from enm_apserv_metrics";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileApservDailyStats' INTO TABLE enm_apserv_metrics" )
        or die "Failed to load new data from '$bcpFileApservDailyStats' file to 'enm_apserv_metrics' table" . $dbh->errstr;


    # Store AP 'Event Stats' data
    # Get the list of the project and node names
    my %projectNames = ();
    my %nodeNames = ();
    foreach my $project (keys %{$apservEventStats->{'order_project'}}) {
        $projectNames{$project} = 1;
    }
    foreach my $projectNode (keys %{$apservEventStats->{'order_node'}}) {
        my ($project, $node) = $projectNode =~ /^(.*)@@@(.*)$/;
        $projectNames{$project} = 1;
        $nodeNames{$node} = 1;
    }
    foreach my $node (keys %{$apservEventStats->{'hardware_replace'}}) {
        $nodeNames{$node} = 1;
    }

    # Store and get the 'name' -> 'id' mapping for all the project and node names
    my @projectNames = keys %projectNames;
    my @nodeNames = keys %nodeNames;
    my $projectName2IdMap = getIdMap($dbh, "enm_ap_project_names", "id", "name", \@projectNames);
    my $nodeName2IdMap = getIdMap($dbh, "enm_ne", "id", "name", \@nodeNames, $siteId);

    my $bcpFileOrderProjectStats= getBcpFileName("enm_apserv_order_project_stats");
    open (BCP, "> $bcpFileOrderProjectStats") or die "Failed to open $bcpFileOrderProjectStats";
    foreach my $project (sort keys %{$apservEventStats->{'order_project'}}) {
        foreach my $orderProjectEvent (@{$apservEventStats->{'order_project'}->{$project}}) {
            print BCP $siteId . "\t" . $orderProjectEvent->{'time'} . "\t" . $projectName2IdMap->{$project} . "\t" .
                  $orderProjectEvent->{'VALIDATE_PROJECT'} . "\t" . $orderProjectEvent->{'CREATE_PROJECT_MO'} . "\t" .
                  $orderProjectEvent->{'CREATE_AND_WRITE_PROJECT_ARTIFACTS'} . "\n";
        }
    }
    close BCP;

    dbDo( $dbh, "DELETE FROM enm_ap_order_project_stats WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
        or die "Failed to delete from enm_ap_order_project_stats";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileOrderProjectStats' INTO TABLE enm_ap_order_project_stats" )
        or die "Failed to load new data from '$bcpFileOrderProjectStats' file to 'enm_ap_order_project_stats' table" . $dbh->errstr;


    my $bcpFileOrderNodeStats= getBcpFileName("enm_apserv_order_node_stats");
    open (BCP, "> $bcpFileOrderNodeStats") or die "Failed to open $bcpFileOrderNodeStats";
    foreach my $projectNode (sort keys %{$apservEventStats->{'order_node'}}) {
        my ($project, $node) = $projectNode =~ /^(.*)@@@(.*)$/;
        foreach my $orderNodeEvent (@{$apservEventStats->{'order_node'}->{$projectNode}}) {
            print BCP $siteId . "\t" . $orderNodeEvent->{'time'} . "\t" . $projectName2IdMap->{$project} . "\t" .
                  $nodeName2IdMap->{$node} . "\t" . $orderNodeEvent->{'CREATE_NODE_MO'} . "\t" .
                  $orderNodeEvent->{'CREATE_NODE_CHILDREN_MOS'} . "\t" . $orderNodeEvent->{'SETUP_CONFIGURATION'} . "\t" .
                  $orderNodeEvent->{'ADD_NODE'} . "\t" . $orderNodeEvent->{'GENERATE_SECURITY'} . "\t" .
                  $orderNodeEvent->{'CREATE_FILE_ARTIFACT'} . "\t" . $orderNodeEvent->{'CREATE_NODE_USER_CREDENTIALS'} .
                  "\t" . $orderNodeEvent->{'BIND_DURING_ORDER'} . "\n";
        }
    }
    close BCP;

    dbDo( $dbh, "DELETE FROM enm_ap_order_node_stats WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
        or die "Failed to delete from enm_ap_order_node_stats";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileOrderNodeStats' INTO TABLE enm_ap_order_node_stats" )
        or die "Failed to load new data from '$bcpFileOrderNodeStats' file to 'enm_ap_order_node_stats' table" . $dbh->errstr;


    my $bcpFileIntegrateNodeStats= getBcpFileName("enm_apserv_integrate_node_stats");
    open (BCP, "> $bcpFileIntegrateNodeStats") or die "Failed to open $bcpFileIntegrateNodeStats";
    foreach my $projectNode (sort keys %{$apservEventStats->{'integrate_node'}}) {
        my ($project, $node) = $projectNode =~ /^(.*)@@@(.*)$/;
        foreach my $integrateNodeEvent (@{$apservEventStats->{'integrate_node'}->{$projectNode}}) {
            print BCP $siteId . "\t" . $integrateNodeEvent->{'time'} . "\t" . $projectName2IdMap->{$project} . "\t" .
                  $nodeName2IdMap->{$node} . "\t" . $integrateNodeEvent->{'INITIATE_SYNC_NODE'} . "\t" .
                  $integrateNodeEvent->{'IMPORT_CONFIGURATIONS'} . "\t" . $integrateNodeEvent->{'ENABLE_SUPERVISION'} . "\t" .
                  $integrateNodeEvent->{'CREATE_CV'} . "\t" . $integrateNodeEvent->{'CREATE_BACKUP'} . "\t" .
                  $integrateNodeEvent->{'ACTIVATE_OPTIONAL_FEATURES'} . "\t" . $integrateNodeEvent->{'GPS_POSITION_CHECK'} . "\t" .
                  $integrateNodeEvent->{'UNLOCK_CELLS'} . "\t" . $integrateNodeEvent->{'UPLOAD_CV'} . "\t" .
                  $integrateNodeEvent->{'UPLOAD_BACKUP'} . "\n";
        }
    }
    close BCP;

    dbDo( $dbh, "DELETE FROM enm_ap_integrate_node_stats WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
        or die "Failed to delete from enm_ap_integrate_node_stats";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileIntegrateNodeStats' INTO TABLE enm_ap_integrate_node_stats" )
        or die "Failed to load new data from '$bcpFileIntegrateNodeStats' file to 'enm_ap_integrate_node_stats' table" . $dbh->errstr;


    my $bcpFileDeleteNodeStats= getBcpFileName("enm_apserv_delete_node_stats");
    open (BCP, "> $bcpFileDeleteNodeStats") or die "Failed to open $bcpFileDeleteNodeStats";
    foreach my $projectNode (sort keys %{$apservEventStats->{'delete_node'}}) {
        my ($project, $node) = $projectNode =~ /^(.*)@@@(.*)$/;
        foreach my $deleteNodeEvent (@{$apservEventStats->{'delete_node'}->{$projectNode}}) {
            print BCP $siteId . "\t" . $deleteNodeEvent->{'time'} . "\t" . $projectName2IdMap->{$project} . "\t" .
                  $nodeName2IdMap->{$node} . "\t" . $deleteNodeEvent->{'REMOVE_NODE'} . "\t" .
                  $deleteNodeEvent->{'CANCEL_SECURITY'} . "\t" . $deleteNodeEvent->{'REMOVE_BACKUP'} . "\t" .
                  $deleteNodeEvent->{'DELETE_RAW_AND_GENERATED_NODE_ARTIFACTS'} . "\t" .
                  $deleteNodeEvent->{'DELETE_NODE_MO'} . "\n";
        }
    }
    close BCP;

    dbDo( $dbh, "DELETE FROM enm_ap_delete_node_stats WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
        or die "Failed to delete from enm_ap_delete_node_stats";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileDeleteNodeStats' INTO TABLE enm_ap_delete_node_stats" )
        or die "Failed to load new data from '$bcpFileDeleteNodeStats' file to 'enm_ap_delete_node_stats' table" . $dbh->errstr;


    my $bcpFileDeleteProjectStats= getBcpFileName("enm_apserv_delete_project_stats");
    open (BCP, "> $bcpFileDeleteProjectStats") or die "Failed to open $bcpFileDeleteProjectStats";
    foreach my $project (sort keys %{$apservEventStats->{'delete_project'}}) {
        foreach my $deleteProjectEvent (@{$apservEventStats->{'delete_project'}->{$project}}) {
            print BCP $siteId . "\t" . $deleteProjectEvent->{'time'} . "\t" . $projectName2IdMap->{$project} . "\t" .
                  $deleteProjectEvent->{'DELETE_RAW_AND_GENERATED_PROJECT_ARTIFACTS'} . "\t" .
                  $deleteProjectEvent->{'DELETE_PROJECT_MO'} . "\n";
        }
    }
    close BCP;

    dbDo( $dbh, "DELETE FROM enm_ap_delete_project_stats WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
        or die "Failed to delete from enm_ap_delete_project_stats";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileDeleteProjectStats' INTO TABLE enm_ap_delete_project_stats" )
        or die "Failed to load new data from '$bcpFileDeleteProjectStats' file to 'enm_ap_delete_project_stats' table" . $dbh->errstr;

    my $bcpFileHardwareReplaceStats= getBcpFileName("enm_apserv_hardware_replace_stats");
    open (BCP, "> $bcpFileHardwareReplaceStats") or die "Failed to open $bcpFileHardwareReplaceStats";
    foreach my $node (sort keys %{$apservEventStats->{'hardware_replace'}}) {
        foreach my $hardwareReplaceEvent (@{$apservEventStats->{'hardware_replace'}->{$node}}) {
            print BCP $siteId . "\t" . $hardwareReplaceEvent->{'time'} . "\t" . $nodeName2IdMap->{$node} . "\t" .
            $hardwareReplaceEvent->{'GENERATE_HARDWARE_REPLACE_NODE_DATA'} . "\t" . $hardwareReplaceEvent->{'GENERATE_HARDWARE_REPLACE_ICF'} . "\n";
        }
    }
    close BCP;

    dbDo( $dbh, "DELETE FROM enm_ap_hardware_replace_stats WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
        or die "Failed to delete from enm_ap_hardware_replace_stats";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileHardwareReplaceStats' INTO TABLE enm_ap_hardware_replace_stats" )
        or die "Failed to load new data from '$bcpFileHardwareReplaceStats' file to 'enm_ap_hardware_replace_stats' table" . $dbh->errstr;

}

1;
