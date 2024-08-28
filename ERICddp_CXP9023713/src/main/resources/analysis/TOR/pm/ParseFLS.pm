package ParseFLS;

use warnings;
use strict;

use Getopt::Long;
use Data::Dumper;

use StatsTime;
use DataStore;

use PerlIO::gzip;
use JSON;

our $PULL = "PULL";
our $PUSH = "PUSH";
our $GENERATION = "GENERATION";
our %ROP_CONSTANTS = ();

our %ROP_PERIOD_MAP = (
    1    => $DataStore::ONE_MINUTE,
    5    => $DataStore::FIVE_MINUTE,
    15   => $DataStore::FIFTEEN_MINUTE,
    30   => $DataStore::THIRTY_MINUTE,
    60   => $DataStore::ONE_HOUR,
    720  => $DataStore::TWELVE_HOUR,
    1440 => $DataStore::ONE_DAY
);

our $NE = 0;
our $NODE_TYPE = 1;
our $DATA_TYPE = 2;
our $FILE_SIZE = 3;
our $COLLECTION_TIME = 4;
our $ROP_START = 5;
our $ROP_END = 6;
our $TRANSFER_TYPE = 7;

sub parseLog($$) {
    my ($logFile,$r_results) = @_;
    open INPUT, "<:gzip", "$logFile" or die "Cannot open $logFile: $!";
    while ( my $line = <INPUT> ) {
        if ( $::DEBUG > 9 ) {
            print "parseLog: line=$line\n";
        }
        chop $line;
        my @parts = split('\|',$line);
        if ( $::DEBUG > 8 ) { print Dumper("parseLog: parts", \@parts); }

        if ( $#parts < 7 ) {
            print "WARNING: Invalid line $line\n";
            next;
        }

        my $r_fccConfig = $ROP_CONSTANTS{$parts[1]}->{$parts[2]};
        if ( ! defined $r_fccConfig ) {
            print "WARNING: Cannot identify transfer type for $parts[1] $parts[2]\n";
            next;
        }

        my $transferType = $r_fccConfig->{'transfer_type'};

        my ($ne) = $parts[0] =~ /=([^=]+)$/;
        my $r_txfr = [
            $ne,
            $parts[1],
            $parts[2],
            $parts[4] + 0,
            $parts[5] + 0,
            $parts[6] + 0,
            $parts[7] + 0,
            $transferType
        ];
        #Discard samples with invalid rop periods as these samples break the script execution at later stages
        my $ropPeriod = ($r_txfr->[$ROP_END] - $r_txfr->[$ROP_START]) / 60;
        if ( ! exists $r_fccConfig->{'fcc'}->{$ropPeriod} ) {
            if ( $::DEBUG > 9 ) {
                print "Cannot handle ropPeriod $ropPeriod\n";
            }
            next;
        }

        if ( $::DEBUG > 8 ) { print Dumper("parseLog: r_txfr",$r_txfr); }
        push @{$r_results}, $r_txfr;
    }
    close INPUT;
}

sub fccInfoToString($) {
    my ($r_fccInfo) = @_;

    return "fccStart=" . formatTime($r_fccInfo->{'start'},$StatsTime::TIME_SQL) .
        ", fccEnd=" . formatTime($r_fccInfo->{'end'},$StatsTime::TIME_SQL);
}

#
# Find the correct File Collection Cycle for a given file transfer
#
sub getFccInfoForTxfr($$) {
    my ($r_txfr,$r_fccInfoByTxTypeRopPeriod) = @_;

    my $ropPeriod = ($r_txfr->[$ROP_END] - $r_txfr->[$ROP_START]) / 60;
    my $txType = $r_txfr->[$TRANSFER_TYPE];
    my $r_fccConfig = $ROP_CONSTANTS{$r_txfr->[$NODE_TYPE]}->{$r_txfr->[$DATA_TYPE]};
    my $r_thisRopConstants = $r_fccConfig->{'fcc'}->{$ropPeriod};
    if ( ! defined $r_thisRopConstants ) {
        die "Cannot handle ropPeriod $ropPeriod";
    }
    if ( $::DEBUG > 8 ) { print Dumper("getFccInfoForTxfr: r_thisRopConstants", $r_thisRopConstants); }
    my $fctime = $r_txfr->[$COLLECTION_TIME];
    my $r_fccInfoList = $r_fccInfoByTxTypeRopPeriod->{$txType}->{$ropPeriod};
    if ( ! defined $r_fccInfoList ) {
        $r_fccInfoList = [];
        $r_fccInfoByTxTypeRopPeriod->{$txType}->{$ropPeriod} = $r_fccInfoList;
        my ($date,$time) = split(" ", formatSiteTime($r_txfr->[$COLLECTION_TIME], $StatsTime::TIME_SQL));
        my $dayStart = parseTime( $date . ' 00:00:00', $StatsTime::TIME_SQL, $StatsTime::TZ_SITE );
        my $fccStart = $dayStart - $r_thisRopConstants->{'cycle_time'} + $r_thisRopConstants->{'collect_delay'};
        while ( ($fccStart + $r_thisRopConstants->{'cycle_time'}) <= $fctime ) {
            $fccStart += $r_thisRopConstants->{'cycle_time'};
        }
        if ( $::DEBUG > 3 ) {
            printf(
                "getFccInfoForTxfr: %s:%d fccStart=%s\n",
                $txType,
                $ropPeriod,
                formatSiteTime($fccStart, $StatsTime::TIME_SQL)
            );
        }

        my %fccInfo = (
            'start' => $fccStart,
            'end' => $fccStart + $r_thisRopConstants->{'cycle_time'}
        );
        push @{$r_fccInfoList}, \%fccInfo;
    }

    my $r_fccInfo = $r_fccInfoList->[$#{$r_fccInfoList}];
    while ( ($r_fccInfo->{'start'} + $r_thisRopConstants->{'cycle_time'}) <= $fctime ) {
        my %fccInfo = (
            'start' => $r_fccInfo->{'start'} + $r_thisRopConstants->{'cycle_time'},
            'end' => $r_fccInfo->{'start'} + ($r_thisRopConstants->{'cycle_time'}*2)
        );
        push @{$r_fccInfoList}, \%fccInfo;
        $r_fccInfo = \%fccInfo;
    }

    return $r_fccInfo;
}

# Remove File collection cycles that don't have an endtime during today
sub filterFCC($$) {
    my ($date, $r_fccInfoByTxTypeRopPeriod) = @_;

    if ( ! defined $date ) {
        return;
    }

    my $dayStart = parseTime("$date 00:00:00", $StatsTime::TIME_SQL, $StatsTime::TZ_SITE);
    my $dayEnd = parseTime("$date 23:59:59", $StatsTime::TIME_SQL, $StatsTime::TZ_SITE);
    $dayEnd++;
    if ( $::DEBUG > 3 ) { print "extractFileCollectionStats: Looking for FCC ending after $dayEnd\n"; }

    while ( my ($transferType,$r_ropFccInfo) = each %{$r_fccInfoByTxTypeRopPeriod} ) {
        while ( my ($ropPeriod,$r_fccInfoList) = each %{$r_ropFccInfo} ) {
            # For the 24 hour ROP we need to skip this check cause a 24 hour FCC
            # will always end in the next day
            if ( $ropPeriod == 1440 ) {
                next;
            }

            my @todaysFCC = ();
            foreach my $r_fccInfo ( @{$r_fccInfoList} ) {
                if ( ($ropPeriod <= 15 && $r_fccInfo->{'end'} > $dayStart && $r_fccInfo->{'end'} <= $dayEnd) ||
                     ($ropPeriod > 15 && $r_fccInfo->{'start'} > $dayStart && $r_fccInfo->{'start'} <= $dayEnd) ) {
                    push @todaysFCC, $r_fccInfo;
                } else {
                    print "INFO: Discarding ROP " . formatSiteTime($r_fccInfo->{'start'}, $StatsTime::TIME_SQL) . '@' . $ropPeriod . ":" . $transferType . "\n";
                }
            }
            # We might have removed the only entry in the fccInfoList above
            # so if fccInfoList is now empty, remove it
            if ($#todaysFCC == -1) {
                print "INFO: Remove empty ropFccInfo for ropPeriod $ropPeriod\n";
                delete $r_ropFccInfo->{$ropPeriod};
            } else {
                $r_ropFccInfo->{$ropPeriod} = \@todaysFCC;
            }
        }
    }
}

sub extractFileCollectionStats($$$) {
    my ($r_allTxfr,$r_incrData,$date) = @_;
    my %fccInfoByTxTypeRopPeriod = ();
    if ( exists $r_incrData->{'fccLast'} ) {
        while ( my ($transferType,$r_ropFccInfo) = each %{$r_incrData->{'fccLast'}} ) {
            while ( my ($ropPeriod,$r_fccInfo) = each %{$r_ropFccInfo} ) {
                if ( $::DEBUG > 3 ) {
                    printf "extractFileCollectionStats: added %d (%s) for ropPeriod %d\n",
                        $r_fccInfo->{'start'}, formatTime($r_fccInfo->{'start'},$StatsTime::TIME_SQL),
                        $ropPeriod;
                }
                $fccInfoByTxTypeRopPeriod{$transferType}->{$ropPeriod} = [ $r_fccInfo ];
            }
        }
    }

    my $invalidStartROP = 0;
    foreach my $r_txfr ( @{$r_allTxfr} ) {
        if ( $::DEBUG > 9 ) { print Dumper("extractFileCollectionStats: processing txfr", $r_txfr); }

        my $r_fccInfo = getFccInfoForTxfr($r_txfr,\%fccInfoByTxTypeRopPeriod);

        my $outsideFCC = 0;
        if ( $r_txfr->[$ROP_START] !~ /0$/ ) {
            if ( $::DEBUG > 5 ) { print "extractFileCollectionStats: Invalid ROP start\n"; }
            $invalidStartROP++;
            $outsideFCC = 1;
        } else {
            my $expectedFccStart = 0;
            my $ropPeriod = ($r_txfr->[$ROP_END] - $r_txfr->[$ROP_START]) / 60;
            my $r_fccConfig = $ROP_CONSTANTS{$r_txfr->[$NODE_TYPE]}->{$r_txfr->[$DATA_TYPE]};
            my $r_thisRopConstants = $r_fccConfig->{'fcc'}->{$ropPeriod};
            $expectedFccStart = $r_txfr->[$ROP_END] + $r_thisRopConstants->{'collect_delay'};

            my $expectedFccEnd = $expectedFccStart + $r_thisRopConstants->{'cycle_time'};
            if ( $::DEBUG > 9 ) {
                printf "extractFileCollectionStats: expectedFccStart %d (%s), expectedFccEnd %d (%s) collection_time %d (%s) for ropPeriod %d transfertype %s\n",
                $expectedFccStart, formatTime($expectedFccStart,$StatsTime::TIME_SQL), $expectedFccEnd, formatTime($expectedFccEnd,$StatsTime::TIME_SQL),
                $r_txfr->[$COLLECTION_TIME], formatTime($r_txfr->[$COLLECTION_TIME],$StatsTime::TIME_SQL), $ropPeriod, $r_txfr->[$TRANSFER_TYPE];
            }

            if ( $r_txfr->[$COLLECTION_TIME] < $expectedFccStart || $r_txfr->[$COLLECTION_TIME] >= $expectedFccEnd ) {
                $outsideFCC = 1;
            }
        }

        my $r_neTypes = $r_fccInfo->{'ne_types'};
        if ( ! defined $r_neTypes ) {
            $r_neTypes = {};
            $r_fccInfo->{'ne_types'} = $r_neTypes;
        }
        my $r_dataTypes = $r_neTypes->{$r_txfr->[$NODE_TYPE]};
        if ( ! defined $r_dataTypes ) {
            $r_dataTypes = {};
            $r_neTypes->{$r_txfr->[$NODE_TYPE]} = $r_dataTypes;
        }
        my $r_statsForDataType = $r_dataTypes->{$r_txfr->[$DATA_TYPE]};
        my $fctime = $r_txfr->[$COLLECTION_TIME];
        if ( ! defined $r_statsForDataType ) {
            $r_statsForDataType = {
                'files' => 0,
                'size' => 0,
                'outside' => 0,
                'first' => $r_txfr->[$COLLECTION_TIME],
                'last' => $r_txfr->[$COLLECTION_TIME],
                $TRANSFER_TYPE => $r_txfr->[$TRANSFER_TYPE]
            };
            $r_dataTypes->{$r_txfr->[$DATA_TYPE]} = $r_statsForDataType;
        }
        $r_statsForDataType->{'files'}++;
        $r_statsForDataType->{'size'} += $r_txfr->[$FILE_SIZE];
        if ( $r_txfr->[$COLLECTION_TIME] > $r_statsForDataType->{'last'} ) {
            $r_statsForDataType->{'last'} = $r_txfr->[$COLLECTION_TIME];
        }
        if ( $outsideFCC ) {
            $r_statsForDataType->{'outside'}++;
        }
    }

    if ( $invalidStartROP > 0 ) {
        print "WARN: $invalidStartROP samples found with invalid start time\n";
    }

    if ( $::DEBUG > 3 ) { print Dumper("extractFileCollectionStats: fccInfoByTxTypeRopPeriod", \%fccInfoByTxTypeRopPeriod); }

    filterFCC($date, \%fccInfoByTxTypeRopPeriod);

    my %fccLast = ();
    while ( my ($transferType,$r_ropFccInfo) = each %fccInfoByTxTypeRopPeriod ) {
        while ( my ($ropPeriod,$r_fccInfoList) = each %{$r_ropFccInfo} ) {
            $fccLast{$transferType}->{$ropPeriod} = $r_fccInfoList->[$#{$r_fccInfoList}];
        }
    }
    $r_incrData->{'fccLast'} = \%fccLast;
    return \%fccInfoByTxTypeRopPeriod;
}

sub parsePmFileCollectionCategories($) {
    my ($filePath) = @_;

    my $json_text = do {
        open(my $json_fh, "<:encoding(UTF-8)", $filePath)
            or die("Can't open \$filePath\": $!\n");
        local $/;
        <$json_fh>
    };
    my $r_json = decode_json($json_text);

    while ( my ($transferType,$r_configs) = each %{$r_json->{'pmic'}}) {
        # When support was introduced for PUSH_ONDEMAND, the implementation mapped to
        # PUSH
        # See https://gerrit.ericsson.se/#/c/4417954/
        # So we do the same here
        if ( $transferType eq 'PUSH_ONDEMAND' ) {
            $transferType = 'PUSH';
        }

        foreach my $r_config ( @{$r_configs} ) {
            my $r_neTypes = $r_config->{'neTypes'};
            my $r_dataTypes = $r_config->{'dataTypes'};
            my $r_cycleConfigs = $r_config->{'config'};
            my %fileCollectionCycles = ();
            foreach my $r_cycleConfig ( @{$r_cycleConfigs} ) {
                my $durationInMin = int($r_cycleConfig->{'durationInSeconds'}/60);
                if ( $durationInMin > 0 ) {
                    $fileCollectionCycles{$durationInMin} = {
                        'collect_delay' => $r_cycleConfig->{'collectionDelay'},
                        'cycle_time' => $r_cycleConfig->{'durationInSeconds'}
                    };
                }
            }
            foreach my $neType ( @{$r_neTypes} ) {
                foreach my $dataType ( @{$r_dataTypes} ) {
                    if ( exists $ROP_CONSTANTS{$neType}->{$dataType} ) {
                        print "WARN: Overwritting ROP_CONSTANTS for $neType $dataType\n";
                    }
                    $ROP_CONSTANTS{$neType}->{$dataType} = {
                        'fcc' => \%fileCollectionCycles,
                        'transfer_type' => $transferType
                    };
                }
            }
        }
    }
    if ( $::DEBUG > 3 ) { print Dumper("parsePmFileCollectionCategories: ROP_CONSTANTS", \%ROP_CONSTANTS); }
}

sub store($$) {
    my ($site,$r_fccInfoByTxTypeRopPeriod) = @_;

    my %propertyValues = (
        'site' => $site
    );
    my %tableModel = (
        'name' => 'enm_pmic_rop_fls',
        'keycol' => [
            { 'name' => 'netypeid', 'reftable' => 'ne_types' },
            { 'name' => 'datatypeid', 'reftable' => 'enm_pmic_datatypes' }
        ]
    );

    my %columnMap = ( 'time' => 'fcs' );
    foreach my $col ( 'first_offset', 'last_offset', 'files', 'volumekb', 'outside' ) {
        $columnMap{$col} = $col;
    }

    while ( my ($transferType,$r_ropFccInfo) = each %{$r_fccInfoByTxTypeRopPeriod} ) {
        while ( my ($ropPeroid,$r_fccList) = each %{$r_ropFccInfo} ) {
            if ( $#{$r_fccList} == -1 ) {
                next;
            }

            my %dataSets = ();

            foreach my $r_fccInfo ( @{$r_fccList} ) {
                if ( $::DEBUG > 7 ) { print Dumper("store: r_fccInfo",$r_fccInfo); }
                my $fcs = formatSiteTime($r_fccInfo->{'start'},$StatsTime::TIME_SQL);
                my $localTime = parseTime($fcs,$StatsTime::TIME_SQL);
                while ( my ($neType,$r_statsByFileType) = each %{$r_fccInfo->{'ne_types'}} ) {
                    while ( my ($dataType,$r_stats) = each %{$r_statsByFileType} ) {
                        my $r_dataSet = $dataSets{$neType}->{$dataType};
                        if ( ! defined $r_dataSet ) {
                            $r_dataSet = {
                                'properties' => {
                                    'transfertype' => { 'sourcevalue' => $transferType },
                                    'rop' => { 'sourcevalue' => sprintf("%dMIN", $ropPeroid) },
                                    'netypeid' => { 'sourcevalue' => $neType },
                                    'datatypeid' => { 'sourcevalue' => $dataType }
                                },
                                'property_options' => {
                                    'netypeid' => { 'ignorefordelete' => 1 },
                                    'datatypeid' => { 'ignorefordelete' => 1 }
                                },
                                'samples' => []
                            };
                            $dataSets{$neType}->{$dataType} = $r_dataSet;
                        }

                        my %row = (
                            'time' => $localTime,
                            'first_offset' => $r_stats->{'first'} - $r_fccInfo->{'start'},
                            'last_offset' => $r_stats->{'last'}  - $r_fccInfo->{'start'},
                            'files' => $r_stats->{'files'},
                            'volumekb' => $r_stats->{'size'} / 1024,
                            'outside' => $r_stats->{'outside'}
                        );
                        push @{$r_dataSet->{'samples'}}, \%row;
                    }
                }
            }

            my @dataSetArr = ();
            while ( my ($neType,$r_dataTypeMap) = each %dataSets ) {
                while ( my ($dataType,$r_dataSet) = each %{$r_dataTypeMap} ) {
                    push @dataSetArr, $r_dataSet;
        }
    }
            DataStore::storePeriodicData($ROP_PERIOD_MAP{$ropPeroid},
                                         \%tableModel,
                                         undef,
                                         "pmservice",
                                         \%propertyValues,
                                         \%columnMap,
                                         \@dataSetArr);
        }
    }
}

sub main() {
    my ($flsDir,$incrFile,$site,$date,$pmicJsonFile);
    my $result = GetOptions("dir=s"  => \$flsDir,
                            "site=s" => \$site,
                            "incr=s" => \$incrFile,
                            "date=s" => \$date,
                            "json=s" => \$pmicJsonFile,
                            "debug=s" => \$::DEBUG
                        );
    ($result == 1) or die "Invalid args";
    setStatsTime_Debug($::DEBUG);
    parsePmFileCollectionCategories($pmicJsonFile);
    my @flsFiles = ();
    opendir(my $dh, $flsDir) || die "can't opendir fls dir: $!";
    while ( my $file = readdir($dh) ) {
        if ( $::DEBUG > 4 ) { print "main: checking file $file\n"; }
        if ( $file =~ /^fls.log.(\d+).gz$/ ) {
            push @flsFiles, { 'index' => $1, 'file' => $flsDir . "/" . $file };
        }
    }
    closedir $dh;

    # If there's no files then nothing more to do
    if ( ! @flsFiles ) {
        return 0;
    }

    @flsFiles = sort { $a->{'index'} <=> $b->{'index'} } @flsFiles;
    if ( $::DEBUG > 3 ) { print Dumper("main: flsFiles", \@flsFiles); }

    my %incrData = ();
    if ( defined $incrFile && -r $incrFile ) {
        my $dumperOutput;
        do {
            local $/ = undef;
            open my $fh, "<", $incrFile or die "could not open $incrFile: $!";
            $dumperOutput = <$fh>;
            close $fh;
        };
        my $VAR1;
        eval($dumperOutput);
        if ( exists $VAR1->{'fccLast'} ) {
            %incrData = %{$VAR1};
        }
        if ( $::DEBUG > 3 ) { print Dumper("main: incrData", \%incrData); }
    }

    if ( exists $incrData{'last_processed'} ) {
        while ( $#flsFiles > -1 && $flsFiles[0]->{'index'} <= $incrData{'last_processed'} ) {
            if ( $::DEBUG > 3 ) { print "main: discarding already processed file: " . $flsFiles[0]->{'file'} . "\n"; }
            shift @flsFiles;
        }
    }
    print "Parsing FLS Log\n";
    #
    # r_allTxfr is an array of arrays, one per file transfer where the array elements contain
    #    $NE
    #    $NODE_TYPE
    #    $DATA_TYPE
    #    $FILE_SIZE
    #    $ROP_START
    #    $ROP_END
    #    $COLLECTION_TIME
    my @allTxFr = ();
    foreach my $r_flsFile ( @flsFiles ) {
        print "Processing $r_flsFile->{'file'}\n";
        parseLog($r_flsFile->{'file'},\@allTxFr);
    }

    if ( $#allTxFr == -1 ) {
        print "INFO: No data found\n";
        exit 1;
    }

    $incrData{'last_processed'} = $flsFiles[$#flsFiles]->{'index'};

    print "Sorting\n";
    @allTxFr = sort { $a->[$COLLECTION_TIME] <=> $b->[$COLLECTION_TIME] } @allTxFr;

    print "Extract FCC Stats\n";
    my $r_fccByRopPeroid = extractFileCollectionStats(\@allTxFr,\%incrData,$date);

    if ( defined $site ) {
        store($site,$r_fccByRopPeroid);
    }

    if ( defined $incrFile ) {
        my $incrDataStr = Dumper(\%incrData);
        open INC, ">$incrFile";
        print INC $incrDataStr;
        close INC;
    }
}

1;
