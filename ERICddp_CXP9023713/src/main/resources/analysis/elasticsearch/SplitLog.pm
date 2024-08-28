package SplitLog;

use strict;
use warnings;

use Getopt::Long;
use Data::Dumper;

use StatsTime;

use JSON;
use StatsDB;
use DBI;
use File::Basename;
use Time::HiRes;
use PerlIO::gzip;

use Module::Load;
use File::Basename;

our $isLogComplete = 1;

our $MESSAGE_PER_HOST_LIMT = 1000000;
our $SIZE_PER_HOST_LIMT = 1024 * 1024 * 1024;

our $FIRST_ES_LOG_FILE_NAME = 'elasticsearch.log.gz';

sub getEsLogGzFirstTime($) {
    my ($logFile) = @_;

    my $timestamp = 'z';
    open INPUT, "<:gzip", "$logFile" or do {
        warn "Cannot open $logFile: $!";
        return $timestamp;
    };

    while ( my $line = <INPUT> ) {
        my $r_result = undef;
        eval {
            $r_result = decode_json($line);
        };

        if ( ! defined $r_result ) {
            print "WARN: decode_json failed for line $. in $logFile\n";
        }
        elsif ( exists $r_result->{'error'} ) {
            print "WARN: Error Message Found \"", $r_result->{'error'}, "\"\n";
        }
        else {
            for my $r_logEntry ( @{$r_result->{'hits'}->{'hits'}} ) {
                if ( $::DEBUG > 7 ) { print Dumper("getEsLogGzFirstTime: r_logEntry", $r_logEntry); }

                my $timestamp = '';
                if (exists $r_logEntry->{'fields'}) {
                    my $timestamp = $r_logEntry->{'fields'}->{'timestamp'}->[0];
                } else {
                    my $timestamp = $r_logEntry->{'_source'}->{'timestamp'};
                }
                if ( defined $timestamp &&
                     $timestamp =~ /(\d{4}-\d{2}-\d{2}).(\d{2}:\d{2}:\d{2})/ ) {
                    $timestamp = $1 . ' ' . $2;
                    close INPUT;
                    return $timestamp;
                }
            }
        }
    }

    close INPUT;
    return $timestamp;
}

sub getModuleNames {
    my ($r_moduleNames,$namePrefix,$dir) = @_;

    if ( $::DEBUG > 3 ) { print "getModuleNames: dir=$dir\n"; }
    opendir(my $dh, $dir) or die "Cannot open handler dir";
    while ( my $entry = readdir($dh) ) {
        if ( $::DEBUG > 5 ) { print "getModuleNames: entry=$entry\n"; }
        if ( $entry !~ /^\./ ) {
            my $path = $dir . "/" . $entry;
            if ( -d $path ) {
               getModuleNames($r_moduleNames,$namePrefix . $entry . "::", $path);
            } elsif ( $entry =~ /\.pm$/ ) {
                $entry =~ s/\.pm$//;
                push @{$r_moduleNames}, $namePrefix . $entry;
            }
        }
    }
    closedir $dh;
}
sub loadHandlers($$) {
    my ($handlersStr, $r_handlerDirs) = @_;

    for my $dir ( @{$r_handlerDirs} ) {
        unshift @INC, $dir;
    }

    my @handlerModuleNames = ();
    if ( defined $handlersStr ) {
        @handlerModuleNames = split(/,/, $handlersStr);
    } else {
        for my $dir ( @{$r_handlerDirs} ) {
            getModuleNames(\@handlerModuleNames, "", $dir);
        }
    }
    if ( $::DEBUG > 0 ) { print Dumper("loadHandlers: handlerModuleNames", \@handlerModuleNames); }

    my @handlers = ();
    foreach my $handlerModuleName ( @handlerModuleNames ) {
        load $handlerModuleName;
        push @handlers, $handlerModuleName->new();
    }

    return \@handlers;
}

sub readIncr($$) {
    my ($incrFile, $inDir) = @_;

    my $firstEsLogFile = $inDir . '/' . $FIRST_ES_LOG_FILE_NAME;
    my $firstEsLogFileId = undef;
    if ( -r $firstEsLogFile ) {
        my @fileStats = stat($firstEsLogFile);
        $firstEsLogFileId = 'size=' . $fileStats[7] . ',mtime=' . $fileStats[9];
        print "readIncr: Current ID of '$FIRST_ES_LOG_FILE_NAME': $firstEsLogFileId\n";
    }

    my $VAR1;
    if ( defined $incrFile && -r $incrFile ) {
        my $dumperOutput;
        do {
            local $/ = undef;
            open my $fh, "<", $incrFile
                or die "could not open $incrFile";
            $dumperOutput = <$fh>;
            close $fh;
        };

        if ( $::DEBUG > 8 ) { print "readIncr: dumperOutput=$dumperOutput\n"; }

        # The below statement will load all the incr file content into $VAR1
        eval($dumperOutput);
        if ( $::DEBUG > 7 ) { print Dumper("readIncr: VAR", $VAR1); }

        $VAR1->{'isIncremental'} = 1;
        if ( defined $firstEsLogFileId ) {
            if ( ! defined $VAR1->{'firstEsLogFileId'} ) {
                $VAR1->{'firstEsLogFileId'} = $firstEsLogFileId;
            }
            else {
                print "readIncr: Previous ID of '$FIRST_ES_LOG_FILE_NAME': " .
                      $VAR1->{'firstEsLogFileId'} . "\n";
                if ( $firstEsLogFileId ne $VAR1->{'firstEsLogFileId'} ) {
                    print "readIncr: The current and previous IDs of '$FIRST_ES_LOG_FILE_NAME' " .
                          "don't match.\n";
                    my $clearPastIncrAndStartAfresh = 0;
                    if ( defined $VAR1->{'otherIncrFlags'}->{'esLogGzLastTime'} &&
                         $VAR1->{'otherIncrFlags'}->{'esLogGzLastTime'} =~ /(\d{4}-\d{2}-\d{2}).(\d{2}:\d{2}:\d{2})/ ) {
                        my $esLogGzLastTime = $1 . ' ' . $2;
                        print "readIncr: esLogGzLastTime - $esLogGzLastTime\n";
                        my $currentEsLogGzFirstTime = getEsLogGzFirstTime($firstEsLogFile);
                        print "readIncr: currentEsLogGzFirstTime - $currentEsLogGzFirstTime\n";
                        if ( $currentEsLogGzFirstTime lt $esLogGzLastTime ) {
                            $clearPastIncrAndStartAfresh = 1;
                        }
                    }

                    if ( $clearPastIncrAndStartAfresh ) {
                        print "readIncr: Skipping incremental processing and starting afresh as " .
                              "the current '$FIRST_ES_LOG_FILE_NAME' holds logs which are older " .
                              "than the logs processed last time\n";
                        $VAR1 = {
                            'isIncremental' => 0,
                            'firstEsLogFileId' => $firstEsLogFileId
                        };
                    }
                    else {
                        print "readIncr: Continuing the incremental processing but with " .
                              "'$FIRST_ES_LOG_FILE_NAME' as the next file to process this time " .
                              "as all its logs are more latest than those processed last time\n";
                        $VAR1->{'firstEsLogFileId'} = $firstEsLogFileId;
                        $VAR1->{'fileIndex'} = 0;
                    }
                }
            }
        }
    } else {
        $VAR1->{'isIncremental'} = 0;
        if ( defined $firstEsLogFileId ) {
            $VAR1->{'firstEsLogFileId'} = $firstEsLogFileId;
        }
    }

    return $VAR1;
}

sub writeIncr($$) {
    my ($incrFile,$r_Incr) = @_;

    if ( ! defined $incrFile ) {
        return;
    }

    my $defaultIndent = $Data::Dumper::Indent;
    $Data::Dumper::Indent = 0;
    my $incrDataStr = Dumper($r_Incr);
    $Data::Dumper::Indent = $defaultIndent;

    open INC, ">$incrFile";
    print INC $incrDataStr;
    close INC;
}

sub processEntries($$$$$$$$) {
    my ($r_logEntries, $r_filteredSubscriptions, $r_wildCardSubscriptions, $r_countsByHost, $r_sizeByHost, $r_otherIncrFlags, $esVersion, $r_mappedSrv) = @_;

    my $count = 0;
    my $host = undef;
    my $program = undef;
    my $message = undef;
    my $severity = undef;
    my $r_fields = undef;
    my $timestamp = undef;

    for my $r_logEntry ( @{$r_logEntries} ) {
        my ($host, $program, $message, $timestamp, $severity, $r_fields, $extra);
        if ( $::DEBUG > 9 ) { print Dumper("processEntries: r_logEntry", $r_logEntry); }
        if ($esVersion eq '21') {
            $r_fields = $r_logEntry->{'fields'};
        }
        else {
            $r_fields = $r_logEntry->{'_source'};
        }
        if ( $::DEBUG > 9 ) { print Dumper("processEntries: r_fields", $r_fields); }

        $count++;

        $severity = "NA";
        if ($esVersion eq '21') {
            $host = $r_fields->{'host'}->[0];
            $program = $r_fields->{'program'}->[0];
            $message = $r_fields->{'message'}->[0];
            $timestamp = $r_fields->{'timestamp'}->[0];
            if ( exists $r_fields->{'severity'} ) {
                $severity = $r_fields->{'severity'}->[0];
            }
        }
        else {
            if ( exists $r_fields->{'@timestamp'} ) {
                $host = $r_fields->{'metadata'}->{'pod_name'};
                if ( ! defined $host ) {
                    $host = $r_fields->{'kubernetes'}->{'pod'}->{'name'};
                }
                $program = $r_fields->{'metadata'}->{'container_name'};
                if ( ! defined $program ) {
                    $program = '';
                }
                $timestamp = $r_fields->{'@timestamp'};
                $extra = $r_fields->{'extra_data'};
            } else {
                $host = $r_fields->{'host'};
                $program = $r_fields->{'program'};
                $timestamp = $r_fields->{'timestamp'};
            }
            $message = $r_fields->{'message'};
            if ( exists $r_fields->{'severity'} ) {
                $severity = $r_fields->{'severity'};
            }
        }

        # Try and detect overflowing messages, RFC says max hostname length is 64
        if ( ! defined $host ) {
            $host = "NA";
        } elsif ( $host =~ /^\.\./ || length($host) > 64 ) {
            $host = "INVALID_HOST_OVERFLOW";
            $program = "[OVERFLOW]";
            $message = "[OVERFLOW]";
        }

        if ( defined $program ) {
            if ( $program eq '') {
                $program = "UNKNOWN";
            } else {
                if ( $program =~ /^\.\./ || $program =~ /[=;\(\)]/ ) {
                    $program = "[INVALID_PROGRAM]";
                } elsif ( length($program) > 128 ) {
                    $program = "[INVALID_PROGRAM]";
                } else {
                    $program =~ s/\//_/g;
                }
            }
        } else {
            $program = "UNKNOWN";
        }

        $r_otherIncrFlags->{'esLogGzLastTime'} = $timestamp;

        if ( defined $r_mappedSrv ) {
            my $mappedHost = $r_mappedSrv->{$host};
            if ( defined $mappedHost ) {
                $host = $mappedHost;
            }
        }

        # Severity is an array on ECSON for some reason
        if ( ref $severity eq 'ARRAY' ) {
            $severity = join(",", @{$severity});
        }

        $r_countsByHost->{$host}->{$severity}++;
        my $messageSize = length($message);
        $r_sizeByHost->{$host}->{$severity} += $messageSize;

        if ( $::DEBUG > 9 ) { printf "processEntries: counts for %s:%s = %d\n", $host, $severity, $r_countsByHost->{$host}->{$severity}; }

        # If we have the message
        if ( defined $message ) {
            # If the total message count from this host is less then the limit then
            # process the message
            if ( ($r_countsByHost->{$host}->{$severity} < $MESSAGE_PER_HOST_LIMT) && ($r_sizeByHost->{$host}->{$severity} < $SIZE_PER_HOST_LIMT) ) {
                # Strip the Unicode BOM from the message to stop the 'Wide character in printf' messages
                $message =~ s/^\x{FEFF}//;
                $message =~ s/^- \x{FEFF}//;

                #
                # Figure out which handlers need to process this message
                #
                my @handlers = ();
                # Filter on both host and program
                my $r_subscriptions = $r_filteredSubscriptions->{$host}->{$program};
                if ( defined $r_subscriptions ) {
                    foreach my $r_handler ( @{$r_subscriptions} ) {
                        push @handlers, $r_handler;
                    }
                }
                # Filter on program only
                my $r_wildCardHostSubs = $r_filteredSubscriptions->{'*'};
                if ( defined $r_wildCardHostSubs ) {
                    my $r_subscriptions = $r_wildCardHostSubs->{$program};
                    if ( defined $r_subscriptions ) {
                        foreach my $r_handler ( @{$r_subscriptions} ) {
                            push @handlers, $r_handler;
                        }
                    }
                }
                # Filter on host only
                my $r_wildCardProgSubs = $r_filteredSubscriptions->{$host}->{'*'};
                if ( defined $r_wildCardProgSubs ) {
                    foreach my $r_handler ( @{$r_wildCardProgSubs} ) {
                        push @handlers, $r_handler;
                    }
                }
                # Wildcard subscription. No filtering on either host or program
                foreach my $r_handler ( @{$r_wildCardSubscriptions} ) {
                    push @handlers, $r_handler;
                }

                # Invoke the handlers for this message
                foreach my $r_handler ( @handlers ) {
                    $r_handler->handle($timestamp,$host,$program,$severity,$message,$messageSize,$extra);
                }
            } else {
                my $r_subscriptions = $r_filteredSubscriptions->{$host}->{$program};
                if ( defined $r_subscriptions ) {
                    foreach my $r_handler ( @{$r_subscriptions} ) {
                        $r_handler->handleExceeded($host,$program);
                    }
                }
                foreach my $r_handler ( @{$r_wildCardSubscriptions} ) {
                    $r_handler->handleExceeded($host,$program);
                }
            }
        }
    }
}

sub processFile($$$$$$$$) {
    my ($inFile, $r_filteredSubscriptions, $r_wildCardSubscriptions, $r_countsByHost, $r_sizeByHost, $r_otherIncrFlags, $fileIndex, $r_mappedSrv) = @_;

    my $totalEntries = 0;

    my $totalRead = 0;
    my $totalDecode = 0;
    my $totalProcess = 0;

    if ( $::DEBUG > 8 ) { printf "processFile: %10s %10s %6s %6s %6s\n", "Length", "Entries", "Read", "Decode", "Process" };

    my @stat = stat $inFile;
    my $compressedSize =  $stat[7];
    my $uncompressedSize = 0;
    my $lastError = undef;

    open INPUT, "<:gzip", "$inFile" or do {
        warn "Cannot open $inFile: $!";
        return $fileIndex;
    };
    my $readStart = Time::HiRes::time;
    my $esVersion = undef;
    while ( my $line = <INPUT> ) {
        my $readEnd = Time::HiRes::time;
        my $readDuration = $readEnd - $readStart;
        $totalRead += $readDuration;

        my $length = length($line);
        $uncompressedSize += $length;

        my $decodeStart = Time::HiRes::time();
        my $r_result = undef;
        eval {
            $r_result = decode_json($line);
        };
        my $decodeEnd = Time::HiRes::time();
        my $decodeDuration = $decodeEnd-$decodeStart;
        $totalDecode += $decodeEnd - $decodeStart;

        if ( ! defined $r_result ) {
            print "WARN: decode_json failed for line $. in $inFile\n";
            next;
        }

        my $processDuration = 0;
        my $entriesInLine = 0;
        if ( exists $r_result->{'error'} ) {
            print "WARN: Error Message Found \"", $r_result->{'error'}, "\"\n";
            $lastError = $r_result->{'error'};
        } else {
            if ( ! defined $esVersion) {
                if (exists $r_result->{'hits'}->{'hits'}->[0]->{'fields'}){
                        $esVersion = '21';
                    } else {
                        $esVersion = '56';
                    }
            }
            $entriesInLine = $#{$r_result->{'hits'}->{'hits'}} + 1;
            $totalEntries += $entriesInLine;
            my $processStart = Time::HiRes::time();
            processEntries($r_result->{'hits'}->{'hits'}, $r_filteredSubscriptions, $r_wildCardSubscriptions,
                           $r_countsByHost, $r_sizeByHost, $r_otherIncrFlags, $esVersion, $r_mappedSrv);
            my $processEnd = Time::HiRes::time();
            $processDuration = $processEnd - $processStart;
            $totalProcess += $processDuration;
        }
        if ( $::DEBUG > 8 ) { printf "processFile: %10d %10d %6.2f %6.2f %6.2f\n", $length, $entriesInLine, $readDuration, $decodeDuration, $processDuration };
        $readStart = Time::HiRes::time;
    }
    close INPUT;

    if ( $::DEBUG > 8 ) { printf "processFile: read=%.3f decode=%.3f process=%.3f\n",$totalRead,$totalDecode,$totalProcess; }

    if ( exists $r_otherIncrFlags->{'compressedSize'} ) {
        $compressedSize += $r_otherIncrFlags->{'compressedSize'};
    }
    $r_otherIncrFlags->{'compressedSize'} = $compressedSize;
    if ( exists $r_otherIncrFlags->{'uncompressedSize'} ) {
        $uncompressedSize += $r_otherIncrFlags->{'uncompressedSize'};
    }
    $r_otherIncrFlags->{'uncompressedSize'} = $uncompressedSize;
    $r_otherIncrFlags->{'lastError'} = $lastError;

    return ++$fileIndex;
}

sub storeData($$$$$$) {
    my ($dbh, $siteId, $date, $r_countsByHostSeverity, $r_sizeByHostSeverity, $r_otherIncrFlags) = @_;

    if ( $::DEBUG > 8 ) {
        print Dumper("storeData: r_countsByHostSeverity", $r_countsByHostSeverity);
        print Dumper("storeData: r_sizeByHostSeverity", $r_sizeByHostSeverity);
    }

    my $r_srvIdMap = getIdMap($dbh, "servers", "id", "hostname", [], $siteId);
    dbDo($dbh, "DELETE FROM enm_logs WHERE siteid = $siteId AND date = '$date'")
        or die "Failed to delete from enm_logs";

    while ( my ($host, $r_countsBySeverity) = each %{$r_countsByHostSeverity}) {
        my $hostCount = 0;
        my $hostSize = 0;
        my $r_sizeBySeverity = $r_sizeByHostSeverity->{$host};
        if ( $::DEBUG > 8 ) {
            print Dumper("storeData: host = $host, r_countsBySeverity", $r_countsBySeverity);
            print Dumper("storeData: r_sizeBySeverity", $r_sizeBySeverity);
        }

        while ( my ($severity,$count) = each %{$r_countsBySeverity} ) {
            $hostCount += $count;
            my $size = $r_sizeBySeverity->{$severity};
            if ( defined $size ) {
                $hostSize += $size;
            } else {
                die "No match found for $host $severity";
            }
        }

        my $serverId = 'NULL';
        if ( exists $r_srvIdMap->{$host} ) {
            $serverId = $r_srvIdMap->{$host};
        }

        dbDo($dbh, sprintf("INSERT INTO enm_logs (siteid, date, serverid, entries,size) VALUES ( %d, '%s', %s , %d, %d)",
                           $siteId, $date, $serverId, $hostCount,$hostSize/1024))
            or die "Failed to insert into enm_logs for $host";
    }

    dbDo($dbh, "DELETE FROM enm_elasticsearch_getlog WHERE siteid = $siteId AND date = '$date'")
        or die "Failed to delete from enm_elasticsearch_getlog";
    dbDo($dbh, sprintf("INSERT INTO enm_elasticsearch_getlog (siteid,date,compressedKB,uncompressedKB,lastError) VALUES (%d, '%s', %d, %d, %s)",
                       $siteId, $date,
                       int ( ($r_otherIncrFlags->{'compressedSize'} / 1024) + 0.5),
                       int ( ($r_otherIncrFlags->{'uncompressedSize'} / 1024) + 0.5),
                       $dbh->quote($r_otherIncrFlags->{'lastError'})) )
        or die "Failed to insert into enm_elasticsearch_getlog";
}

sub main() {
    my ($inDir, $csvDir, $outDir, $analysisDir, $date, $site, $incrFile,
        $handlersStr, $maxFiles, $dataDir, $iso);
    my @handlerDirs = ();
    my $keepFiles = 0;
    my $result = GetOptions("indir=s" => \$inDir,
                            "logout=s" => \$outDir,
                            "analysisOut=s" => \$analysisDir,
                            "dataDir=s" => \$dataDir,
                            "date=s" => \$date,
                            "site=s" => \$site,
                            "incr=s" => \$incrFile,
                            "maxfiles=i" => \$maxFiles,
                            "handlers=s" => \$handlersStr,
                            "iso=s" => \$iso,
                            "handlerdir=s" => \@handlerDirs,
                            "keep" => \$keepFiles,
                            "debug=s" => \$::DEBUG
        );
    ($result == 1) or die "Invalid args";
    setStatsDB_Debug($::DEBUG);

    my $r_Incr = readIncr($incrFile, $inDir);

    my $fileIndex = 0;
    if ( exists $r_Incr->{'fileIndex'} ) {
        $fileIndex = $r_Incr->{'fileIndex'};
    }
    my @fileList = ();
    my $keepGoing = 1;
    while ( $keepGoing ) {
        my $filename = $FIRST_ES_LOG_FILE_NAME;
        if ( $fileIndex > 0 ) {
            $filename .= sprintf(".%02d",$fileIndex);
        }
        my $filePath = $inDir . "/" . $filename;
        if ( -r $filePath ) {
            push @fileList, $filePath;
            $fileIndex++;
        } else {
            $keepGoing = 0;
        }
    }
    if ( $#fileList < 0 ) {
        print "WARNING: No files found in $inDir\n";

        # If we've filtered out all of the files (because we already processed them)
        # we still need to delete them
        unlink glob "$inDir/elasticsearch.log*";

        exit 0;
    }
    if ( $::DEBUG > 0 ) { print Dumper("main: fileList",\@fileList); }

    my $dbh = connect_db();
    my $siteId = getSiteId($dbh, $site);
    ( $siteId != -1) or die "Failed to get siteid for $site";

    my %cliArgs = (
        'debug' => $::DEBUG,
        'date' => $date,
        'site' => $site,
        'siteId' => $siteId,
        'outDir' => $outDir,
        'dataDir' => $dataDir,
        'analysisDir' => $analysisDir
        );
    if ( defined $maxFiles ) {
        $cliArgs{'maxFiles'} = $maxFiles;
    }
    if ( defined $iso ) {
        $cliArgs{'iso'} = $iso;
        my ($a, $b, $c) = $iso =~ /^(\d+)\.(\d+)\.(\d+)/;
        $cliArgs{'iso_num'} = ($a * 1000000) + ($b * 1000) + $c;
    }
    if ( $::DEBUG > 3 ) { print Dumper("main: cliArgs", \%cliArgs); }

    my $r_handlers = loadHandlers($handlersStr, \@handlerDirs);

    my %filteredSubscriptions = ();
    my @wildCardSubscriptions = ();
    foreach my $r_handler ( @{$r_handlers} ) {
        my $r_subscriptions = $r_handler->init( \%cliArgs, $r_Incr, $dbh );
        if ( $::DEBUG > 3 ) { print Dumper("main: r_handler, r_subscriptions", $r_handler, $r_subscriptions); }
        if ( defined $r_subscriptions ) {
            foreach my $r_subscription ( @{$r_subscriptions} ) {
                my $r_subscriptionsForSrv = $filteredSubscriptions{$r_subscription->{'server'}};
                if ( ! defined $r_subscriptionsForSrv ) {
                    $r_subscriptionsForSrv = {};
                    $filteredSubscriptions{$r_subscription->{'server'}} = $r_subscriptionsForSrv;
                }
                my $r_subscriptionsForProg = $r_subscriptionsForSrv->{$r_subscription->{'prog'}};
                if ( ! defined $r_subscriptionsForProg ) {
                    $r_subscriptionsForProg = [];
                    $r_subscriptionsForSrv->{$r_subscription->{'prog'}} = $r_subscriptionsForProg;
                }
                push @{$r_subscriptionsForProg}, $r_handler;
            }
        } else {
            push @wildCardSubscriptions, $r_handler;
        }
    }

    # The purpose of the hash '$r_otherIncrFlags' is to hold
    # any general incremental but scalar variables
    my ($r_countsByHost, $r_sizeByHost, $r_otherIncrFlags);
    if ( $r_Incr->{'isIncremental'} ) {
        $r_countsByHost = $r_Incr->{'countsByHost'};
        $r_sizeByHost = $r_Incr->{'sizeByHost'};
        $r_otherIncrFlags = $r_Incr->{'otherIncrFlags'};
    } else {
        $r_countsByHost = {};
        $r_sizeByHost = {};
        $r_otherIncrFlags = {};
    }


    my $r_mappedSrv = undef;
    my $r_rows = dbSelectAllHash($dbh, "
SELECT
 servers.hostname AS srvname, k8s_pod.pod AS podname
FROM k8s_pod, servers
WHERE
 k8s_pod.siteid = $siteId AND
 k8s_pod.date = '$date' AND
 k8s_pod.serverid = servers.id");
    if ( $#{$r_rows} > -1 ) {
        $r_mappedSrv = {};
        foreach my $r_row ( @{$r_rows} ) {
            $r_mappedSrv->{$r_row->{'podname'}} = $r_row->{'srvname'};
        }
    }

    my $nextEsLogIndex = 0;
    foreach my $filePath ( @fileList ) {
        print "Processing " . basename($filePath) . "\n";

        my $currentEsLogIndex = 0;
        if ( $filePath =~ /$FIRST_ES_LOG_FILE_NAME\.(\d+)\s*$/ ) {
            $currentEsLogIndex = int($1);
        }

        $nextEsLogIndex = processFile($filePath, \%filteredSubscriptions, \@wildCardSubscriptions,
                                      $r_countsByHost, $r_sizeByHost, $r_otherIncrFlags, $currentEsLogIndex,
                                      $r_mappedSrv
                                  );
        if ( $nextEsLogIndex == $currentEsLogIndex ) {
            last;
        }
    }

    my %outIncr = ( 'fileIndex' => $nextEsLogIndex,
                    'countsByHost' => $r_countsByHost,
                    'sizeByHost' => $r_sizeByHost,
                    'otherIncrFlags' => $r_otherIncrFlags );

    if ( defined $r_Incr->{'firstEsLogFileId'} ) {
        $outIncr{'firstEsLogFileId'} = $r_Incr->{'firstEsLogFileId'};
    }

    foreach my $r_handler ( @{$r_handlers} ) {
        $r_handler->done($dbh, \%outIncr);
    }

    if ( defined $date ) {
        storeData($dbh, $siteId, $date, $r_countsByHost, $r_sizeByHost, $r_otherIncrFlags);
    }

    writeIncr($incrFile, \%outIncr);

    # We processed the logs, don't need the raw data anymore
    if ( ! $keepFiles ) {
        unlink glob "$inDir/elasticsearch.log*";
    }

    $dbh->disconnect();
}

1;
