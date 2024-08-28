package cm::ImpExp;

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
    $self->{'srvIdMap'} = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},'importexportservice');

    foreach my $key ( 'importEventList_03', 'exportEventList' ) {
        if ( exists $r_incr->{'ImpExp'}->{$key} ) {
            $self->{$key} = $r_incr->{'ImpExp'}->{$key};
        } else {
            $self->{$key} = {};
        }
    }

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }

    if ( $::DEBUG > 5 ) { print Dumper("ImpExp::init subscriptions",\@subscriptions) ; }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }

    if ( $::DEBUG > 7 ) { print "ImpExp::handle timestamp=$timestamp message=$message\n"; }

    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\] \(.*\) \[[^,]+, BULK_IMPORT, DETAILED, import-service/ ) {
        getImportEvent($message, $self->{'importEventList_03'});
    } elsif ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\] \(.*\) \[[^,]+, EXPORT_SERVICE\S+ COARSE, export-service component/ ||
              $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\] \(.*\) \[[^,]+, EXPORT_MERGE_FILES, COARSE, export-service component/ ) {
        getExportEvent($message, $self->{'exportEventList'});
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}


sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("ImpExp::done self", $self); }

    storeImportExportEventList($dbh, $self->{'siteId'}, $self->{'importEventList_03'}, $self->{'exportEventList'}, $self->{'date'});

    foreach my $key ( 'importEventList_03', 'exportEventList' ) {
        $r_incr->{'ImpExp'}->{$key} = $self->{$key};
    }
}

#
# Internal functions
#
sub getImportEvent($$) {
    my $logline = shift;
    my $importEventList_03 = shift;

    if ( $::DEBUG > 6 ) { print "ImpExp::getImportEvent logline=$logline\n"; }

    if ( $logline =~ /importJobParameters.*jobId\s*=\s*(\d+)/ || $logline =~ /jobId\s*=\s*(\d+).*(?:validateSchemaStarttime|parseFileStarttime|modelValidationStarttime|copyRootsStarttime|copyManagedObjectsStarttime|executeImportStarttime)/ ) {
        # Import log-format(seven-line).
        # Sample 'Import' Line 1: INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (Batch Thread - 11) [CMIMPORT_03_2016-10-27_19-51-12-2239_USER_0, \
        #   BULK_IMPORT, DETAILED, import-service, parseFile, jobId=6350, parseFileStarttime=2016-10-28T08:45:58.215, parseFileEndtime=2016-10-28T08:45:59.304]
        # Sample 'Import' Line 2: INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (Batch Thread - 11) [CMIMPORT_03_2016-10-27_19-51-12-2239_USER_0, \
        #   BULK_IMPORT, DETAILED, import-service, copyRoots, jobId=6350, copyRootsStarttime=2016-10-28T08:46:01.500, copyRootsEndtime=2016-10-28T08:46:01.855]
        # Sample 'Import' Line 3: INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (Batch Thread - 11) [CMIMPORT_03_2016-10-27_19-51-12-2239_USER_0, \
        #   BULK_IMPORT, DETAILED, import-service, copyManagedObjects, jobId=6350, copyManagedObjectsStarttime=2016-10-28T08:46:01.862, \
        #   copyManagedObjectsEndtime=2016-10-28T08:46:45.095]
        # Sample 'Import' Line 4: INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (Batch Thread - 11) [CMIMPORT_03_2016-10-27_19-51-12-2239_USER_0, \
        #   BULK_IMPORT, DETAILED, import-service, executeImport, jobId=6350, executeImportStarttime=2016-10-28T08:46:45.100, \
        #   executeImportEndtime=2016-10-28T08:46:55.452]
        # Sample 'Import' Line 5: INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (Batch Thread - 11) [CMIMPORT_03_2016-10-27_19-51-12-2239_USER_0, \
        #   BULK_IMPORT, DETAILED, import-service, importJobParameters,  jobId=6350, Status=COMPLETED, File Format=THREE_GPP, Nodes Copied=100, \
        #   Nodes Not Copied=0, MOs Created=0, MOs Updated=200, MOs Deleted=0, Configuration=cm_import_03, Import File=cm_import_03_locked.xml]
        # Sample 'Import' Line 6: INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (Batch Thread - 11) [CMIMPORT_03_2016-10-27_19-51-12-2239_USER_0, \
        #   BULK_IMPORT, DETAILED, import-service, validateSchema, jobId=6350, validateSchemaStarttime=2016-10-28T08:45:58.144, \
        #   validateSchemaEndtime=2016-10-28T08:45:58.206]
        # Sample 'Import' Line 7: INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (Batch Thread - 11) [CMIMPORT_03_2016-10-27_19-51-12-2239_USER_0, \
        #   BULK_IMPORT, DETAILED, import-service, modelValidation, jobId=6350, modelValidationStarttime=2016-10-28T08:45:59.310, \
        #   modelValidationEndtime=2016-10-28T08:46:01.495]
        my $jobid = $1;
        if ( ! exists $importEventList_03->{$jobid} ) {
            $importEventList_03->{$jobid} = {
                'validate_schema_start_time'   => '\N',
                'validate_schema_end_time'     => '\N',
                'parse_file_start_time'        => '\N',
                'parse_file_end_time'          => '\N',
                'model_validation_start_time'  => '\N',
                'model_validation_end_time'    => '\N',
                'status'                       => '\N',
                'copy_roots_start_time'        => '\N',
                'copy_roots_end_time'          => '\N',
                'copy_mos_start_time'          => '\N',
                'copy_mos_end_time'            => '\N',
                'execute_import_start_time'    => '\N',
                'execute_import_end_time'      => '\N',
                'nodes_copied'                 => '\N',
                'nodes_not_copied'             => '\N',
                'mos_created'                  => '\N',
                'mos_updated'                  => '\N',
                'mos_deleted'                  => '\N',
                'file_format'                  => '\N',
                'configuration'                => '\N',
                'import_file'                  => '\N',
                'error_handling'               => '\N',
                'instance_validation'          => '\N'
            };
        }

        if ( $logline =~ /validateSchemaStarttime/ ) {
            parseImportStartAndEndTimes('validateSchemaStarttime', 'validateSchemaEndtime', 'validate_schema_start_time', 'validate_schema_end_time',
                                        $logline, $jobid, $importEventList_03
                                        );
        }
        elsif ( $logline =~ /parseFileStarttime/ ) {
            parseImportStartAndEndTimes('parseFileStarttime', 'parseFileEndtime', 'parse_file_start_time', 'parse_file_end_time',
                                        $logline, $jobid, $importEventList_03
                                        );
        }
        elsif ( $logline =~ /modelValidationStarttime/ ) {
            parseImportStartAndEndTimes('modelValidationStarttime', 'modelValidationEndtime', 'model_validation_start_time', 'model_validation_end_time',
                                        $logline, $jobid, $importEventList_03
                                        );
        }
        elsif ( $logline =~ /copyRootsStarttime/ ) {
            parseImportStartAndEndTimes('copyRootsStarttime', 'copyRootsEndtime', 'copy_roots_start_time', 'copy_roots_end_time',
                                        $logline, $jobid, $importEventList_03
                                        );
        }
        elsif ( $logline =~ /copyManagedObjectsStarttime/ ) {
            parseImportStartAndEndTimes('copyManagedObjectsStarttime', 'copyManagedObjectsEndtime', 'copy_mos_start_time', 'copy_mos_end_time',
                                        $logline, $jobid, $importEventList_03
                                        );
        }
        elsif ( $logline =~ /executeImportStarttime/ ) {
            parseImportStartAndEndTimes('executeImportStarttime', 'executeImportEndtime', 'execute_import_start_time', 'execute_import_end_time',
                                        $logline, $jobid, $importEventList_03
                                        );
        }
        elsif ( $logline =~ /importJobParameters/ ) {
            parseImportJobParameters($logline, $jobid, $importEventList_03);
        }

        if ( $::DEBUG > 7 ) {
            print Dumper( "getImportEvent: Job Id: $jobid", $importEventList_03->{$jobid} );
        }
    }
}

sub getExportEvent($$) {
    my ( $logline, $exportEventList ) = @_;

    if ( $::DEBUG > 6 ) { print "ImpExp::getExportEvent logline=$logline\n"; }

    if( $logline =~ /(EXPORT_SERVICE|EXPORT_MERGE_FILES).*export-service.*jobId=(\d+)/) {
    # Export log format (one-line):
    # Sample 'Export' Line 1 (of 1): INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (Batch Thread - 1) [NO USER DATA, EXPORT_SERVICE.COMPLETED, COARSE, \
    #   export-service component, AssembleBatchlet, jobId=11,batchStatus=COMPLETED,startTime=2016-02-04T01:17:50,endTime=2016-02-04T01:17:50,elapsedTime=0,\
    #   exportType=3GPP,expectedNodesExported=1,nodesExported=1,nodesNotExported=0,nodesNoMatchFound=0,MOsExported=1173,exportFile=\
    #   /ericsson/batch/data/export/3gpp_export/scheduled_export_3GPP_2016-02-04T01-17-50-001_72c6428c-efeb-4bd6-84c1-cc5a0c35f255.xml,\
    #   jobName=scheduled_export_3GPP_2016-02-04T01-17-50-001_72c6428c-efeb-4bd6-84c1-cc5a0c35f255]
    #   TORF-445703 Removes filter content
    my $jobid = $2;
    if ( ! exists $exportEventList->{$jobid} ) {
    $exportEventList->{$jobid} = {
        'status'               => '\N',
        'export_start_date_time' => '\N',
        'export_end_date_time' => '\N',
        'type'                 => 'NA',
        'expected_nodes'       => '\N',
        'exported'             => '\N',
        'not_exported'         => '\N',
        'nodes_no_match_found' => '\N',
        'total_mos'            => '\N',
        'file_name'            => 'NA',
        'job_name'             => 'NA',
        'source'               => 'NA',
        'filter_choice'        => 'NA',
        'merge_start_time'       => 'NA',
        'merge_duration'       => 'NA',
        'master_server_id'       => 'NA',
        'export_non_synchronized_nodes'       => 'NA',
        'compression_type'       => 'NA',
        'user_id'       => 'NA'
    };
    }

    if ( $logline =~ /batchStatus\s*=\s*([^, ]+)/ ) {
        $exportEventList->{$jobid}->{'status'} = $1;
    }
    if ( $logline =~ /startTime\s*=\s*(\d{4,4}-\d{2,2}-\d{2,2})T(\d{2,2}:\d{2,2}:\d{2,2})/ ) {
        $exportEventList->{$jobid}->{'export_start_date_time'} = $1 . ' ' . $2;
    }
    if ( $logline =~ /endTime\s*=\s*(\d{4,4}-\d{2,2}-\d{2,2})T(\d{2,2}:\d{2,2}:\d{2,2})/ ) {
        $exportEventList->{$jobid}->{'export_end_date_time'} = $1 . ' ' . $2;
    }
    if( $logline =~ /exportType\s*=\s*(\w+)/ ) {
        $exportEventList->{$jobid}->{'type'} = $1;
    }
    if ( $logline =~ /expectedNodesExported\s*=\s*(\d+)/ ) {
        $exportEventList->{$jobid}->{'expected_nodes'} = $1;
    }
    if ( $logline =~ /nodesExported\s*=\s*(\d+)/ ) {
        $exportEventList->{$jobid}->{'exported'} = $1;
    }
    if ( $logline =~ /nodesNotExported\s*=\s*(\d+)/ ) {
        $exportEventList->{$jobid}->{'not_exported'} = $1;
    }
    if ( $logline =~ /nodesNoMatchFound\s*=\s*(\d+)/ ) {
        $exportEventList->{$jobid}->{'nodes_no_match_found'} = $1;
    }
    if ( $logline =~ /MOsExported\s*=\s*(\d+)/ ) {
        $exportEventList->{$jobid}->{'total_mos'} = $1;
    }
    if ( $logline =~ /exportFile\s*=\s*([^,]+)/ ) {
        $exportEventList->{$jobid}->{'file_name'} = $1;
    }
    if ( $logline =~ /jobName\s*=\s*([^\],]+)/ ) {
        my $jobName = $1;
        # Remove the timestamp from the job name if it has one
        if ( $jobName =~ /^(.*)_[0-9]{4}-[0-9]{2}-[0-9]{2}T/ ) {
            $jobName = $1;
        }
        $exportEventList->{$jobid}->{'job_name'} = $jobName;
    }
    if ( $logline =~ /source\s*=\s*([^,]+)/ ) {
        $exportEventList->{$jobid}->{'source'} = $1;
    }
    if ( $logline =~ /filterChoice\s*=\s*([^,]+)/ ) {
        $exportEventList->{$jobid}->{'filter_choice'} = $1;
    }
    if ( $logline =~ /mergeStartTime\s*=\s*(\d{4,4}-\d{2,2}-\d{2,2})T(\d{2,2}:\d{2,2}:\d{2,2})/ ) {
        $exportEventList->{$jobid}->{'merge_start_time'} = $1 . " " . $2;
    }
    if ( $logline =~ /mergeDuration\s*=\s*(\d+)/ ) {
        $exportEventList->{$jobid}->{'merge_duration'} = $1;
    }
    if ( $logline =~ /masterServerId\s*=(\s*([^\],]+))/ ) {
        $exportEventList->{$jobid}->{'master_server_id'} = $1;
    }
    if ( $logline =~ /exportNonSynchronizedNodes\s*=\s*(true|false)/ ) {
        $exportEventList->{$jobid}->{'export_non_synchronized_nodes'} = $1;
    }
    if ( $logline =~ /compressionType\s*=\s*([^,]+)/ ) {
        $exportEventList->{$jobid}->{'compression_type'} = $1;
    }
    if ( $logline =~ /userId\s*=\s*([^,]+)/ ) {
        $exportEventList->{$jobid}->{'user_id'} = $1;
    }

    if ( $::DEBUG > 7 ) {
            print Dumper( "getExportEvent Job Id: $jobid", $exportEventList->{$jobid} );
    }
    }
}


sub parseImportStartAndEndTimes($$$$$$$) {
    my ( $startTimePattern, $endTimePattern, $startKey, $endKey, $logline, $jobid, $importEventList_03 ) = @_;

    if ( $logline =~ /$startTimePattern\s*=\s*(\d{4,4}-\d{2,2}-\d{2,2}T\d{2,2}:\d{2,2}:\d{2,2})\.?(\d{0,6})/ ) {
        my $startMicrosec = $2 . '0' x ( 6 - (length $2) );
        $importEventList_03->{$jobid}->{$startKey} = $1 . '.' . $startMicrosec;
        if ( $logline =~ /$endTimePattern\s*=\s*(\d{4,4}-\d{2,2}-\d{2,2}T\d{2,2}:\d{2,2}:\d{2,2})\.?(\d{0,6})/ ) {
            my $endMicrosec = $2 . '0' x ( 6 - (length $2) );
            $importEventList_03->{$jobid}->{$endKey} = $1 . '.' . $endMicrosec;
        }
    }
}

sub parseImportJobParameters($$$) {
    my ( $logline, $jobid, $importEventList ) = @_;

    if ( $logline =~ /Status\s*=\s*([^,]+)/ ) {
        $importEventList->{$jobid}->{'status'} = $1;
    }
    if ( $logline =~ /File\s*Format\s*=\s*([^,]+)/ ) {
        $importEventList->{$jobid}->{'file_format'} = $1;
    }
    if ( $logline =~ /Nodes\s*Copied\s*=\s*(\d+)/ ) {
        $importEventList->{$jobid}->{'nodes_copied'} = $1;
    }
    if ( $logline =~ /Nodes\s*Not\s*Copied\s*=\s*(\d+)/ ) {
        $importEventList->{$jobid}->{'nodes_not_copied'} = $1;
    }
    if ( $logline =~ /MOs\s*Created\s*=\s*(\d+)/ ) {
        $importEventList->{$jobid}->{'mos_created'} = $1;
    }
    if ( $logline =~ /MOs\s*Updated\s*=\s*(\d+)/ ) {
        $importEventList->{$jobid}->{'mos_updated'} = $1;
    }
    if ( $logline =~ /MOs\s*Deleted\s*=\s*(\d+)/ ) {
        $importEventList->{$jobid}->{'mos_deleted'} = $1;
    }
    if ( $logline =~ /Configuration\s*=\s*([^,]+)/ ) {
        $importEventList->{$jobid}->{'configuration'} = $1;
    }
    if ( $logline =~ /Import\s*File\s*=\s*([^,\]\s]+)/ ) {
        $importEventList->{$jobid}->{'import_file'} = $1;
    }
    if ( $logline =~ /Error\s*Handling\s*=\s*([^,\]\s]+)/ ) {
        $importEventList->{$jobid}->{'error_handling'} = $1;
    }
    if ( $logline =~ /Instance\s*Validation\s*=\s*([^,\]\s]+)/ ) {
        $importEventList->{$jobid}->{'instance_validation'} = $1;
    }
}

sub sortTimestamps($$) {
    my ( $timestamps, $state ) = @_;

    my @timestamps = sort(grep {!/^\\N$/} @{$timestamps});
    if ( scalar @timestamps == 0 ) {
        return '\N';
    }
    elsif ( $state eq 'start' ) {
        return $timestamps[0];
    }
    elsif ( $state eq 'end' ) {
        return $timestamps[$#timestamps];
    }
}

sub storeImportExportEventList($$$$$$$) {
    my ( $dbh, $siteId, $r_importEventList_03, $r_exportEventList, $date ) = @_;

    if ( $date !~ /\d{4}-\d{2}-\d{2}/ ) {
        return;
    }

    my $tableImport_03 = "cm_import";
    my $tableExport = "cm_export";

    if ( scalar (keys %{$r_importEventList_03}) > 0 ) {
        my $bcpFileNameImport_03 = writeBulkImportFile03($siteId, $r_importEventList_03);

        dbDo( $dbh, "DELETE FROM $tableImport_03 WHERE siteid = $siteId AND job_end BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
            or die "Failed to delete from $tableImport_03";

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNameImport_03' INTO TABLE $tableImport_03" )
            or die "Failed to load data in $bcpFileNameImport_03 into $tableImport_03";
    }

    if ( scalar (keys %{$r_exportEventList}) > 0 ) {
        my $bcpFileNameExport = writeBulkExportFile( $dbh, $siteId, $r_exportEventList );

        dbDo( $dbh, "DELETE FROM $tableExport WHERE siteid = $siteId AND export_end_date_time BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
            or die "Failed to delete from $tableExport";

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNameExport' INTO TABLE $tableExport" )
            or die "Failed to load data in $bcpFileNameExport into $tableExport";
    }
}

sub writeBulkImportFile03($$) {
    my ( $siteId, $r_imports ) = @_;

    my $bcpFileName = getBcpFileName("cm_import");
    open BCP, ">$bcpFileName" or die "Cannot open $bcpFileName";

    foreach my $jobid (sort keys %{$r_imports}) {
        my $copyStart = sortTimestamps([$r_imports->{$jobid}->{'copy_roots_start_time'},
                                        $r_imports->{$jobid}->{'copy_mos_start_time'}
                                        ], 'start'
                                       );
        my $copyEnd   = sortTimestamps([$r_imports->{$jobid}->{'copy_roots_end_time'},
                                        $r_imports->{$jobid}->{'copy_mos_end_time'}
                                        ], 'end'
                                       );

        my $jobStart  = sortTimestamps([$r_imports->{$jobid}->{'validate_schema_start_time'}, $r_imports->{$jobid}->{'parse_file_start_time'},
                                        $r_imports->{$jobid}->{'model_validation_start_time'}, $copyStart,
                                        $r_imports->{$jobid}->{'execute_import_start_time'}
                                        ], 'start'
                                       );
        my $jobEnd    = sortTimestamps([$r_imports->{$jobid}->{'validate_schema_end_time'}, $r_imports->{$jobid}->{'parse_file_end_time'},
                                        $r_imports->{$jobid}->{'model_validation_end_time'}, $copyEnd,
                                        $r_imports->{$jobid}->{'execute_import_end_time'}
                                        ], 'end'
                                       );

        my $valSchemaTimeInMillisec = '\N';
        if ( $r_imports->{$jobid}->{'validate_schema_start_time'} ne "\\N" &&  $r_imports->{$jobid}->{'validate_schema_end_time'} ne "\\N" ) {
            my $valSchemaStartInMicrosec = getElasticsearchTimeInMicroSeconds($r_imports->{$jobid}->{'validate_schema_start_time'});
            my $valSchemaEndInMicrosec   = getElasticsearchTimeInMicroSeconds($r_imports->{$jobid}->{'validate_schema_end_time'});
            $valSchemaTimeInMillisec     = sprintf("%.0f", ($valSchemaEndInMicrosec - $valSchemaStartInMicrosec)/1000);
        }

        my $parseTimeInMillisec = '\N';
        if ( $r_imports->{$jobid}->{'parse_file_start_time'} ne "\\N" &&  $r_imports->{$jobid}->{'parse_file_end_time'} ne "\\N" ) {
            my $parseStartInMicrosec = getElasticsearchTimeInMicroSeconds($r_imports->{$jobid}->{'parse_file_start_time'});
            my $parseEndInMicrosec   = getElasticsearchTimeInMicroSeconds($r_imports->{$jobid}->{'parse_file_end_time'});
            $parseTimeInMillisec     = sprintf("%.0f", ($parseEndInMicrosec - $parseStartInMicrosec)/1000);
        }

        my $modelValTimeInMillisec = '\N';
        if ( $r_imports->{$jobid}->{'model_validation_start_time'} ne "\\N" &&  $r_imports->{$jobid}->{'model_validation_end_time'} ne "\\N" ) {
            my $modelValStartInMicrosec = getElasticsearchTimeInMicroSeconds($r_imports->{$jobid}->{'model_validation_start_time'});
            my $modelValEndInMicrosec   = getElasticsearchTimeInMicroSeconds($r_imports->{$jobid}->{'model_validation_end_time'});
            $modelValTimeInMillisec     = sprintf("%.0f", ($modelValEndInMicrosec - $modelValStartInMicrosec)/1000);
        }

        my $copyTimeInMillisec = '\N';
        if ( $copyStart ne "\\N" &&  $copyEnd ne "\\N" ) {
            my $copyStartInMicrosec = getElasticsearchTimeInMicroSeconds($copyStart);
            my $copyEndInMicrosec   = getElasticsearchTimeInMicroSeconds($copyEnd);
            $copyTimeInMillisec     = sprintf("%.0f", ($copyEndInMicrosec - $copyStartInMicrosec)/1000);
        }

        my $importTimeInMillisec = '\N';
        if ( $r_imports->{$jobid}->{'execute_import_start_time'} ne "\\N" &&  $r_imports->{$jobid}->{'execute_import_end_time'} ne "\\N" ) {
            my $importStartInMicrosec = getElasticsearchTimeInMicroSeconds($r_imports->{$jobid}->{'execute_import_start_time'});
            my $importEndInMicrosec   = getElasticsearchTimeInMicroSeconds($r_imports->{$jobid}->{'execute_import_end_time'});
            $importTimeInMillisec     = sprintf("%.0f", ($importEndInMicrosec - $importStartInMicrosec)/1000);
        }

        my $jobStartRounded = '\N';
        if ( $jobStart ne "\\N" ) {
            my $jobStartEpoch = sprintf("%.0f", getElasticsearchTimeInMicroSeconds($jobStart)/1000000);
            $jobStartRounded = formatTime($jobStartEpoch, $StatsTime::TIME_SQL);
        }
        else {
            print "Ignoring the import with 'JobId: $jobid' due to missing job start time\n";
            next;
        }

        my $jobEndRounded = '\N';
        if ( $jobEnd ne "\\N" ) {
            my $jobEndEpoch = sprintf("%.0f", getElasticsearchTimeInMicroSeconds($jobEnd)/1000000);
            $jobEndRounded = formatTime($jobEndEpoch, $StatsTime::TIME_SQL);
        }

        my @row = (
            $siteId,
            $jobid,
            $r_imports->{$jobid}->{'status'},
            $jobStartRounded,
            $jobEndRounded,
            $valSchemaTimeInMillisec,
            $parseTimeInMillisec,
            $modelValTimeInMillisec,
            $copyTimeInMillisec,
            $importTimeInMillisec,
            $r_imports->{$jobid}->{'nodes_copied'},
            $r_imports->{$jobid}->{'nodes_not_copied'},
            $r_imports->{$jobid}->{'mos_created'},
            $r_imports->{$jobid}->{'mos_updated'},
            $r_imports->{$jobid}->{'mos_deleted'},
            $r_imports->{$jobid}->{'file_format'},
            $r_imports->{$jobid}->{'configuration'},
            $r_imports->{$jobid}->{'import_file'},
            $r_imports->{$jobid}->{'error_handling'},
            $r_imports->{$jobid}->{'instance_validation'},
        );

        print BCP join( "\t", @row ), "\n";
    }
    close BCP;
    return $bcpFileName;
}

sub writeBulkExportFile($$$) {
    my ( $dbh, $siteId, $r_exports ) = @_;

    my $bcpFileName = getBcpFileName("cm_export.bcp");
    open BCP, ">$bcpFileName" or die "Cannot open $bcpFileName";

    my @typeList = ();
    my @fileNameList = ();
    my @sourceNameList = ();
    my @filterChoiceNameList = ();
    foreach my $jobid (sort keys %{$r_exports} ) {
        push @typeList, $r_exports->{$jobid}->{'type'};
        push @fileNameList, $r_exports->{$jobid}->{'file_name'};
        push @sourceNameList, $r_exports->{$jobid}->{'source'};
        push @filterChoiceNameList, $r_exports->{$jobid}->{'filter_choice'};
    }

    my $r_typeIdMap = getIdMap($dbh, "cm_export_types", "id", "name", \@typeList);
    my $r_sourceNameIdMap = getIdMap($dbh, "cm_export_source_names", "id", "name", \@sourceNameList);
    my $r_filterChoiceNameIdMap = getIdMap($dbh, "cm_export_filter_choice_names", "id", "name", \@filterChoiceNameList);

    foreach my $jobid (sort keys %{$r_exports} ) {
        my $type = $r_exports->{$jobid}->{'type'};
        my $fileName = $r_exports->{$jobid}->{'file_name'};
        my $sourceName = $r_exports->{$jobid}->{'source'};
        my $filterChoiceName = $r_exports->{$jobid}->{'filter_choice'};
        my $mergeStartTime = $r_exports->{$jobid}->{'merge_start_time'};
        my $mergeDuration = $r_exports->{$jobid}->{'merge_duration'};
        my $masterServerId = $r_exports->{$jobid}->{'master_server_id'};
        my $exportNonSynchronizedNodes = $r_exports->{$jobid}->{'export_non_synchronized_nodes'};
        my $compressionType = $r_exports->{$jobid}->{'compression_type'};
        my $userId = $r_exports->{$jobid}->{'user_id'};



        my @row = (
            $jobid,
            $siteId,
            $r_exports->{$jobid}->{'export_start_date_time'},
            $r_exports->{$jobid}->{'export_end_date_time'},
            $r_exports->{$jobid}->{'total_mos'},
            $r_typeIdMap->{$type},
            $r_exports->{$jobid}->{'expected_nodes'},
            $r_exports->{$jobid}->{'exported'},
            $r_exports->{$jobid}->{'not_exported'},
            $r_exports->{$jobid}->{'nodes_no_match_found'},
            $r_exports->{$jobid}->{'job_name'},
            $r_sourceNameIdMap->{$sourceName},
            $r_filterChoiceNameIdMap->{$filterChoiceName},
            $r_exports->{$jobid}->{'status'},
            $mergeStartTime,
            $mergeDuration,
            $masterServerId,
            $fileName,
            $exportNonSynchronizedNodes,
            $compressionType,
            $userId
        );

        print BCP join( "\t", @row ), "\n";
    }
    close BCP;
    return $bcpFileName;
}

1;
