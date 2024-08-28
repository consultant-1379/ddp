use strict;
use warnings;

use Data::Dumper;
use Test::More;
use JSON;
use File::Basename;

require Instr;

our $DEBUG = 0;

sub test_getMinMaxTimes($$$) {
    my ($r_datasets, $expectedMin, $expectedMax) = @_;

    my ($gotMin, $gotMax) = Instr::getMinMaxTimes($r_datasets);

    if ( ($expectedMin ne $gotMin) || ($expectedMax ne $gotMax) ) {
        printf(
            "test_getMinMaxTimes min expected %s got %s, max expected %s got %s\n",
            $expectedMin,
            $gotMin,
            $expectedMax,
            $gotMax
        );
        return 0;
    } else {
        return 1;
    }
}

sub test_createRow($$$$$$) {
    my ($desc, $r_propertiesValues, $r_sample, $r_metricColumns, $useTimeStamp, $r_expected) = @_;
    my $r_got = Instr::createRow($r_propertiesValues, $r_sample, $r_metricColumns, $useTimeStamp);
    is_deeply($r_got, $r_expected, $desc);
}

sub test_writeBcp($$) {
    my ($r_testData, $testDir) = @_;

    my $bcpFile = Instr::writeBcpFile(
        "test",
        $r_testData->{'dataSets'},
        $r_testData->{'dbColumnNames'},
        $r_testData->{'metricColumns'}
    );

    my $expected = $testDir . "/" . $r_testData->{'expectedBCP'};
    my $diffResult = system(("diff", $expected, $bcpFile));
    ok(
        $diffResult == 0,
        $r_testData->{'desc'}
    );

    unlink($bcpFile);
}

sub test_instrRateSamples($$$$) {
    my ($r_inputSamples, $r_columnsToRate, $r_expectedResult, $description) = @_;
    my $r_gotResult = Instr::instrRateSamples($r_inputSamples, $r_columnsToRate, $r_expectedResult);
    print Dumper($r_gotResult);
    return is_deeply($r_gotResult, $r_expectedResult, "instrRateSamples: " . $description);
}

sub test_instrFilterIdleSamplesWithThresholds($$$$$) {
    my ($r_inputSamples, $r_thresholds, $keepFirst, $description, $r_expectedResult) = @_;
    my $r_gotResult = Instr::instrFilterIdleSamplesWithThresholds($r_inputSamples, $r_thresholds, $keepFirst);
    my $passed = is_deeply($r_gotResult, $r_expectedResult, "instrFilterIdleSamplesWithThresholds: $description");
    if ( ! $passed ) {
        print Dumper($r_gotResult, $r_expectedResult);
    }
    return $passed;
}

sub test_instrFilterIdleSamples($$$$$) {
    my ($r_inputSamples, $r_metricNames, $keepFirst, $description, $r_expectedResult) = @_;
    my $r_gotResult = Instr::instrFilterIdleSamples($r_inputSamples, $r_metricNames, $keepFirst);
    my $passed = is_deeply($r_gotResult, $r_expectedResult, "instrFilterIdleSamples: $description");
    if ( ! $passed ) {
        print Dumper($r_gotResult, $r_expectedResult);
    }
    return $passed;
}

our $testDir = "/tmp/instr" . sprintf("%d", time());
mkdir $testDir;
$ENV{'TMP_DIR'} = $testDir;
Instr::setInstr_Debug($DEBUG);

our $THIS_DIR = dirname(__FILE__);
our $TEST_DATA_DIR = $THIS_DIR . "/instr";
opendir(DIR, $TEST_DATA_DIR) or die "Failed to open $TEST_DATA_DIR";
my @files = grep(/^testbcp.*\.json$/,readdir(DIR));
closedir(DIR);


foreach my $jsonFile ( @files ) {
    my $filePath = $TEST_DATA_DIR . "/" . $jsonFile;
    open INPUT, $filePath or die "Cannot open $filePath";
    my $file_content = do { local $/; <INPUT> };
    close INPUT;
    my $r_testData = decode_json($file_content);

    if ( $DEBUG > 1 ) { printf("jsonFile: %s\n", $jsonFile); }
    test_writeBcp($r_testData, $TEST_DATA_DIR);
}

# Test getMinMaxTimes behaves correctly when sample doesn't contain timestamp
# i.e. it does a formatTime on the time field
ok(
    test_getMinMaxTimes(
        [
            {
                'samples' => [
                    {
                        'time' => 1623999240,
                        'timestamp' => '18-06-21 07:54:00.000'
                    }
                ]
            }
        ],
        "2021-06-18 07:54:00",
        "2021-06-18 07:54:00"
    ),
    "Test getMinMaxTimes behaves correctly when property_options doesn't contain usetimestamp"
);

ok(
    test_getMinMaxTimes(
        [
            {
                'samples' => [
                    {
                        'time' => 1623999240,
                        'timestamp' => '2021-06-17 23:54:00'
                    }
                ],
                'property_options' => { 'time' => { 'usetimestamp' => 1 } }
            }
        ],
        "2021-06-17 23:54:00",
        "2021-06-17 23:54:00"
    ),
    "Test getMinMaxTimes behaves correctly when property_options does contain usetimestamp"
);

test_createRow(
    "Test createRow behaves correctly when useTimeStamp = 0",
    [],
    {
        'time' => 1623999240,
        'timestamp' => '18-06-21 07:54:00.000'
    },
    [],
    0,
    [
        "2021-06-18 07:54:00"
    ]
);

test_createRow(
    "Test createRow behaves correctly when useTimeStamp = 1",
    [1, 2],
    {
        'time' => 1623999240,
        'timestamp' => "2021-06-17 23:54:00",
        'a' => 3,
        'b' => 4
    },
    [
        { 'src' => [ 'a' ], 'column' => 'col1', 'schema' => { 'NUMBER' => 1 } },
        { 'src' => [ 'b' ], 'column' => 'col2', 'schema' => { 'NUMBER' => 1 } }
    ],
    1,
    [
        "2021-06-17 23:54:00",
        1,
        2,
        3,
        4
    ]
);

test_instrRateSamples(
    [
        {
            'node_network_receive_packets_total' => 120,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
        },
        {
            'node_network_receive_packets_total' => 120,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        },
        {
            'node_network_receive_packets_total' => 240,
            'timestamp' => '2022-10-05 02:00:00',
            'time' => 1664931600
        },
        {
            'node_network_receive_packets_total' => 6,
            'timestamp' => '2022-10-05 02:00:00',
            'time' => 1664931600
        },
        {
            'node_network_receive_packets_total' => 120,
            'timestamp' => '2022-10-05 02:01:00',
            'time' => 1664931660
        }
    ],
    [ 'node_network_receive_packets_total' ],
    [
        {
            'node_network_receive_packets_total' => 2,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
            '_rate_duration' => 60
        },
        {
            'node_network_receive_packets_total' => 2,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
            '_rate_duration' => 60
        },
        {
            'node_network_receive_packets_total' => 2,
            'timestamp' => '2022-10-05 02:00:00',
            'time' => 1664931600,
            '_rate_duration' => 120
        },
        {
            'node_network_receive_packets_total' => 2,
            'timestamp' => '2022-10-05 02:01:00',
            'time' => 1664931660,
            '_rate_duration' => 60
        }
    ],
    "Normal case"
);

# Handle where only a one sample is supplied, empty result set should be returned
test_instrRateSamples(
    [
        {
            'node_network_receive_packets_total' => 120,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
        }
    ],
    [ 'node_network_receive_packets_total' ],
    [],
    "Only one sample"
);


# All samples below threshold and no keepfirst so expect empty results
test_instrFilterIdleSamplesWithThresholds(
    [
        {
            'a' => 1,
            'b' => 10,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
        },
        {
            'a' => 2,
            'b' => 10,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        }
    ],
    { 'a' => 3 },
    0,
    "Drop all results",
    []
);

# All samples below threshold but have keepfirst so expect empty 1 result
test_instrFilterIdleSamplesWithThresholds(
    [
        {
            'a' => 1,
            'b' => 10,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
        },
        {
            'a' => 2,
            'b' => 10,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        }
    ],
    { 'a' => 3 },
    1,
    "Keep first sample",
    [
        {
            'a' => 1,
            'b' => 10,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
        }
    ]
);

# Make sure non-idle sample has a "idle" sample either side
test_instrFilterIdleSamplesWithThresholds(
    [
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
        },
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        },
        {
            'a' => 2,
            'b' => 10,
            'timestamp' => '2022-10-05 02:00:00',
            'time' => 1664931600
        },
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 02:01:00',
            'time' => 1664931660
        },
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 02:02:00',
            'time' => 1664931720
        }
    ],
    { 'a' => 1 },
    0,
    "Check bookend",
    [
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        },
        {
            'a' => 2,
            'b' => 10,
            'timestamp' => '2022-10-05 02:00:00',
            'time' => 1664931600
        },
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 02:01:00',
            'time' => 1664931660
        }
    ]
);

# All samples below threshold and no keep first so expect empty results
test_instrFilterIdleSamples(
    [
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
        },
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        }
    ],
    [ 'a' ],
    0,
    "Drop all results",
    []
);

# All samples below threshold but have keepfirst so expect empty 1 result
test_instrFilterIdleSamples(
    [
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
        },
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        }
    ],
    [ 'a' ],
    1,
    "Keep first sample",
    [
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
        }
    ]
);

# Make sure non-idle sample has a "idle" sample either side
test_instrFilterIdleSamples(
    [
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:57:00',
            'time' => 1664931420,
        },
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        },
        {
            'a' => 2,
            'b' => 10,
            'timestamp' => '2022-10-05 02:00:00',
            'time' => 1664931600
        },
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 02:01:00',
            'time' => 1664931660
        },
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 02:02:00',
            'time' => 1664931720
        }
    ],
    [ 'a' ],
    0,
    "Check bookend",
    [
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        },
        {
            'a' => 2,
            'b' => 10,
            'timestamp' => '2022-10-05 02:00:00',
            'time' => 1664931600
        },
        {
            'a' => 0,
            'b' => 10,
            'timestamp' => '2022-10-05 02:01:00',
            'time' => 1664931660
        }
    ]
);

# Make sure non-idle sample has a "idle" sample either side
test_instrFilterIdleSamples(
    [
        {
            'a' => 1,
            'b' => 0,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        },
        {
            'a' => 0,
            'b' => 1,
            'timestamp' => '2022-10-05 02:00:00',
            'time' => 1664931600
        }
    ],
    [ 'a', 'b' ],
    0,
    "Verify any non-idle value keeps sample",
    [
        {
            'a' => 1,
            'b' => 0,
            'timestamp' => '2022-10-05 01:58:00',
            'time' => 1664931480,
        },
        {
            'a' => 0,
            'b' => 1,
            'timestamp' => '2022-10-05 02:00:00',
            'time' => 1664931600
        }
    ]
);

rmdir $testDir;

done_testing();
