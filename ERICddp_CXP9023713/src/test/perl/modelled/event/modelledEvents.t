# vim: filetype=perl

use strict;
use warnings;

use Data::Dumper;
use Test::More;
use Test::MockModule;

use JSON;

use File::Basename;
use Cwd 'abs_path';

our $THIS_DIR;
our $ANALYSIS_DIR;

BEGIN {
    # We want to put the analysis/modelled/instr into the INC path
    # This code needs to be in a BEGIN block because any "use" is executed during
    # compile time of the code
    $THIS_DIR = dirname(__FILE__);
    $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../../main/resources/analysis");
    unshift @INC, $ANALYSIS_DIR . "/modelled/events";

    # Also add testmodules to verify hooks
    unshift @INC, $THIS_DIR . "/modules";
}

require ModelledEvents;
require ModelFile;

require DupDataSet;

our $DEBUG = 0;

sub test_applyScale() {
    my $event = {
        'data' => {
            'ToBeScaled' => 1842000,
            'DontScale' => 12345
        },
        'name' => 'EVENT.B',
        'timestamp' => '2022-04-07T20:21:27.685+07:00'
    };

    my $eventInfo = {
        'metric' => [
            {
                'scale' => '1000',
                'source' => 'ToBeScaled',
                'target' => 'ToBeScaled'
            },
            {
                'source' => 'DontScale',
                'target' => 'DontScale'
            }
        ],
        'name' => 'EVENT.B',
        'time' => 'timestamp'
    };

    my $model = {
        'table' => {
            'keycol' => [],
            'timecol' => 'time',
            'name' => 'test'
        },
        'events' => {
            'EVENT.A' => {
                'time' => 'timestamp',
                'metric' => [
                    {
                        'scale' => '1000',
                        'target' => 'ToBeScaled',
                        'source' => 'ToBeScaled'
                    },
                    {
                        'source' => 'DontScale',
                        'target' => 'DontScale'
                    }
                ],
                'name' => 'EVENT.A',
            },
            'EVENT.B' => {
                'time' => 'timestamp',
                'name' => 'EVENT.B',
                'metric' => [
                    {
                        'source' => 'ToBeScaled',
                        'scale' => '1000',
                        'target' => 'ToBeScaled'
                    },
                    {
                        'source' => 'DontScale',
                        'target' => 'DontScale'
                    }
                ]
            }
        }
    };

    my $multiSourceTarget = {
        'ToBeScaled' => '_ToBeScaled',
        'DontScale' => '_DontScale'
    };

    my $expectedResult = {
        'time' => 1649359287,
        'timestamp' => '2022-04-07 20:21:27',
        '_ToBeScaled' => '1842',
        '_DontScale' => 12345
    };

    my ($r_actualResult, $criticalError) = ModelledEvents::processOneModelEvent(
        $event,
        $eventInfo,
        {},
        $multiSourceTarget,
        $model,
        {}
    );

    my $testResult = is_deeply(
        $r_actualResult,
        $expectedResult,
        "Test scalling of metrics"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_actualResult);
    }
}

# Verify that all the "pseudo" source values work
sub test_PseduoSources() {
    my $event = {
        'data' => {
            'metric1' => 1,
        },
        'name' => 'EVENT.A',
        'host' => 'host1',
        'timestamp' => '2022-04-07T20:21:27.685+07:00'
    };

    my $r_eventInfo = {
        'metric' => [
            {
                'source' => 'metric1',
                'target' => 'metric1'
            },
            {
                'source' => 'host',
                'target' => 'thehost'
            },
            {
                'source' => 'eventtime',
                'target' => 'theeventtime'
            },
            {
                'source' => 'eventname',
                'target' => 'theeventname'
            },
            {
                'source' => 'servicegroup',
                'target' => 'theservicegroup'
            }
        ],
        'name' => 'EVENT.A',
        'time' => 'timestamp'
    };

    my $model = {
        'table' => {
            'keycol' => [],
            'timecol' => 'time',
            'name' => 'test'
        },
        'events' => {
            'EVENT.A' => $r_eventInfo
        }
    };

    my $multiSourceTarget = {};

    my $expectedResult = {
        'time' => 1649359287,
        'eventname' => 'EVENT.A',
        'metric1' => 1,
        'eventtime' => '2022-04-07 20:21:27',
        'servicegroup' => 'sg1',
        'host' => 'host1',
        'timestamp' => '2022-04-07 20:21:27'
    };

    my ($r_actualResult, $criticalError) = ModelledEvents::processOneModelEvent(
        $event,
        $r_eventInfo,
        {},
        {},
        $model,
        {
            'host1' => 'sg1'
        }
    );

    my $testResult = is_deeply(
        $r_actualResult,
        $expectedResult,
        "Test of pseudo metrics"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_actualResult);
    }
}

sub test_aggregateOkay() {
    my $r_events = [
        {
            'data' => {
                'MetricValue1' => 1,
                'GroupByAttr1' => 'Group1'
            },
            'name' => 'EVENT.A',
            'timestamp' => '2022-04-07T20:21:27.685+07:00',
            'host' => 'host1'
        },
        {
            'data' => {
                'MetricValue1' => 2,
                'GroupByAttr1' => 'Group1'
            },
            'name' => 'EVENT.A',
            'host' => 'host1',
            'timestamp' => '2022-04-07T21:21:27.685+07:00'
        }
    ];

    my $r_model = {
        'aggregate' => {
            'groupby' => [
                {
                    'mandatory' => 'true',
                    'name' => 'GroupByAttr1'
                }
            ],
            'aggregations' => [
                {
                    'name' => 'MetricValue1',
                    'type' => 'sum'
                }
            ],
            'interval' => 24 * 3600
        },
        'table' => {
            'keycol' => [],
            'timecol' => 'time',
            'name' => 'test'
        },
        'events' => {
            'EVENT.A' => {
                'time' => 'timestamp',
                'metric' => [
                    {
                        'target' => 'GroupByAttr1',
                        'source' => 'GroupByAttr1'
                    },
                    {
                        'source' => 'MetricValue1',
                        'target' => 'MetricValue1'
                    }
                ],
                'name' => 'EVENT.A',
            }
        }
    };

    my $r_got = ModelledEvents::aggregate($r_model, $r_events, {}, {}, '2022-04-07');
    my $r_expected = [
        {
            'name' => 'EVENT.A',
            'host' => 'host1',
            'timestamp' => '2022-04-07T00:00:00.000',
            'data' => {
                'GroupByAttr1' => 'Group1',
                'MetricValue1' => 3
            }
        }
    ];
    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test ModelledEvents::aggregate okay"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

sub test_aggregateNonMandOkay() {
    my $r_events = [
        {
            'data' => {
                'MetricValue1' => 1,
                'GroupByAttr1' => 'Group1',
                'GroupByAttr2' => 'Group2'
            },
            'name' => 'EVENT.A',
            'timestamp' => '2022-04-07T20:21:27.685+07:00',
            'host' => 'host1'
        },
        {
            'data' => {
                'GroupByAttr1' => 'Group1',
                'MetricValue1' => 4
            },
            'name' => 'EVENT.A',
            'host' => 'host1',
            'timestamp' => '2022-04-07T21:21:27.685+07:00'
        },
        {
            'data' => {
                'MetricValue1' => 5,
                'GroupByAttr1' => 'Group1',
                'GroupByAttr2' => 'Group2'
            },
            'name' => 'EVENT.A',
            'host' => 'host1',
            'timestamp' => '2022-04-07T21:21:27.685+07:00'
        },
        {
            'data' => {
                'MetricValue1' => 3,
                'GroupByAttr1' => 'Group1'
            },
            'name' => 'EVENT.A',
            'host' => 'host1',
            'timestamp' => '2022-04-07T21:21:27.685+07:00'
        }
    ];

    my $r_model = {
        'aggregate' => {
            'groupby' => [
                {
                    'mandatory' => 'true',
                    'name' => 'GroupByAttr1'
                },
                {
                    'mandatory' => 'false',
                    'name' => 'GroupByAttr2'
                }
            ],
            'aggregations' => [
                {
                    'name' => 'MetricValue1',
                    'type' => 'sum'
                }
            ],
            'interval' => 24 * 3600
        },
        'table' => {
            'keycol' => [],
            'timecol' => 'time',
            'name' => 'test'
        },
        'events' => {
            'EVENT.A' => {
                'time' => 'timestamp',
                'metric' => [
                    {
                        'target' => 'GroupByAttr1',
                        'source' => 'GroupByAttr1'
                    },
                    {
                        'source' => 'MetricValue1',
                        'target' => 'MetricValue1'
                    },
                    {
                        'target' => 'GroupByAttr2',
                        'source' => 'GroupByAttr2'
                    }
                ],
                'name' => 'EVENT.A',
            }
        }
    };

    my $r_got = ModelledEvents::aggregate($r_model, $r_events, {}, {}, '2022-04-07');
    my $r_expected = [
        {
            'name' => 'EVENT.A',
            'host' => 'host1',
            'timestamp' => '2022-04-07T00:00:00.000',
            'data' => {
                'GroupByAttr1' => 'Group1',
                'GroupByAttr2' => 'Group2',
                'MetricValue1' => 6
            }
        },
        {
            'timestamp' => '2022-04-07T00:00:00.000',
            'name' => 'EVENT.A',
            'data' => {
                        'GroupByAttr2' => undef,
                        'GroupByAttr1' => 'Group1',
                        'MetricValue1' => 7
                      },
            'host' => 'host1'
        }
    ];
    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test ModelledEvents::aggregate Non-Mandatory group okay"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}
sub test_aggregateMissingGroupAttr() {
    my $r_events = [
        {
            'data' => {
                'MetricValue1' => 1,
                'GroupByAttr1' => 'Group1'
            },
            'name' => 'EVENT.A',
            'timestamp' => '2022-04-07T20:21:27.685+07:00',
            'host' => 'host1'
        },
        {
            'data' => {
                'MetricValue1' => 2,
            },
            'name' => 'EVENT.A',
            'host' => 'host1',
            'timestamp' => '2022-04-07T21:21:27.685+07:00'
        }
    ];

    my $r_model = {
        'aggregate' => {
            'groupby' => [
                {
                    'mandatory' => 'true',
                    'name' => 'GroupByAttr1'
                }
            ],
            'aggregations' => [
                {
                    'name' => 'MetricValue1',
                    'type' => 'sum'
                }
            ],
            'interval' => 24 * 3600
        },
        'table' => {
            'keycol' => [],
            'timecol' => 'time',
            'name' => 'test'
        },
        'events' => {
            'EVENT.A' => {
                'time' => 'timestamp',
                'metric' => [
                    {
                        'target' => 'GroupByAttr1',
                        'source' => 'GroupByAttr1'
                    },
                    {
                        'source' => 'MetricValue1',
                        'target' => 'MetricValue1'
                    }
                ],
                'name' => 'EVENT.A',
            }
        }
    };

    my $r_got = ModelledEvents::aggregate($r_model, $r_events, {}, {}, '2022-04-07');
    ok( ! defined $r_got, "Test ModelledEvents::aggregate fails with missing groupby attr");
}

sub test_aggregateServiceGroup() {
    my $xsd_doc = XML::LibXML::Schema->new(location => $ANALYSIS_DIR . "/modelled/events/models/modelledevents.xsd");
    my $r_model = ModelFile::processModelFile($THIS_DIR . "/aggsg.xml", $xsd_doc);

    my $r_events = [
        {
            'data' => {
                'metricA' => 1,
            },
            'name' => 'EventTypeA',
            'timestamp' => '2022-01-01T20:21:27.685+07:00',
            'host' => 'host1'
        },
        {
            'data' => {
                'metricA' => 2,
            },
            'name' => 'EventTypeA',
            'timestamp' => '2022-01-01T20:22:27.685+07:00',
            'host' => 'host2'
        },
        {
            'data' => {
                'metricA' => 10
            },
            'name' => 'EventTypeA',
            'timestamp' => '2022-01-01T20:23:27.685+07:00',
            'host' => 'host3'
        }
    ];

    my $r_got = undef;
    my $module = new Test::MockModule('DataStore');
    $module->mock(
        'storeIrregularData',
        sub ($$$$$$) {
            my ($r_tableModel, $r_multiId, $service, $r_propertyValues, $r_columnMap, $r_datasets ) = @_;
            $r_got = $r_datasets;
        }
    );

    ModelledEvents::processModelEvents(
        "TestSite",
        $r_model,
        { 'host1' => 1, 'host2' => 1, 'host3' => 3 },
        $r_events,
        { 'host1' => 'sgA', 'host2' => 'sgA', 'host3' => 'sgB' },
        {},
        '2022-01-01'
    );

    # 1st and 2nd event should be agg together
    # 3rd in another sg so we should have a second sample
    my $r_expected = [
          {
            'samples' => [
                          {
                             'timestamp' => '2022-01-01 00:00:00',
                             'metricA' => 3,
                             'time' => 1640995200,
                             'servicegroup' => 'sgA'
                           },
                           {
                             'timestamp' => '2022-01-01 00:00:00',
                             'metricA' => 10,
                             'time' => 1640995200,
                             'servicegroup' => 'sgB'
                           }
                         ],
            'properties' => {}
          }
    ];
    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test aggregate by servicegroup"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

sub test_processModelEventsExplodeEmptyWithIncr() {
    # Fault where parseEventData crashes if
    # explode_array is used but the content is empty
    # when this is processed incrementally, it fails
    my $xsd_doc = XML::LibXML::Schema->new(location => $ANALYSIS_DIR . "/modelled/events/models/modelledevents.xsd");
    my $r_model = ModelFile::processModelFile($THIS_DIR . "/explode.xml", $xsd_doc);

    my $r_events = [
        {
            'data' => {
                'counts' => [],
            },
            'name' => 'testevent',
            'timestamp' => '2022-01-01T20:21:27.685+07:00',
            'host' => 'host1'
        }
    ];

    my $r_incr = {
        'aggregate' => {
            'test' => {
                'groupTimestamp' => 1640995200
            }
        }
    };

    my $module = new Test::MockModule('DataStore');
    my $storeIrregularDataCalled = 0;
    $module->mock('storeIrregularData', sub ($$$$$$) { $storeIrregularDataCalled = 1; });

    ModelledEvents::processModelEvents(
        "TestSite",
        $r_model,
        { 'host1' => 1 },
        $r_events,
        { 'host1' => 'thesg' },
        $r_incr,
        '2022-01-01'
    );

    # processModelEvents should detect that there are no valid events and not call storeIrregularData
    ok( $storeIrregularDataCalled == 0, "Test processModelEvents with explode array where array is empty");
}

sub test_hook() {
    my $xsd_doc = XML::LibXML::Schema->new(location => $ANALYSIS_DIR . "/modelled/events/models/modelledevents.xsd");
    my $r_model = ModelFile::processModelFile($THIS_DIR . "/hook.xml", $xsd_doc);

    my $r_events = [
        {
            'data' => {
                'metricA' => 1
            },
            'name' => 'testevent',
            'timestamp' => '2022-01-01T20:21:27.685+07:00',
            'host' => 'host1'
        }
    ];

    my $r_got = undef;
    my $module = new Test::MockModule('DataStore');
    $module->mock(
        'storeIrregularData',
        sub ($$$$$$) {
            my ($r_tableModel, $r_multiId, $service, $r_propertyValues, $r_columnMap, $r_datasets ) = @_;
            $r_got = $r_datasets;
        }
    );

    ModelledEvents::processModelEvents(
        "TestSite",
        $r_model,
        { 'host1' => 1 },
        $r_events,
        { 'host1' => 'thesg' },
        {},
        '2022-01-01'
    );

    # The DupDataSet hook should have duplicated the events it was passed, so there should be two
    # two samples passed to DataStore::storeIrregularData
    my $r_expected = [
        {
            'properties' => {},
            'samples' => [
                {
                    'metricA' => 1,
                    'timestamp' => '2022-01-01 20:21:27',
                    'time' => 1641068487
                },
                {
                    'metricA' => 1,
                    'timestamp' => '2022-01-01 20:21:27',
                    'time' => 1641068487
                }
            ]
        }
    ];
    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test hook okay"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

sub test_fmx() {
    my $xsd_doc = XML::LibXML::Schema->new(location => $ANALYSIS_DIR . "/modelled/events/models/modelledevents.xsd");
    my $r_model = ModelFile::processModelFile($ANALYSIS_DIR . "/modelled/events/models/TOR/fm/enm_fmx_monitor.xml", $xsd_doc);

    my $r_events = [
        {
            'data' => {
                'alarmsCreatedCount' => 1,
                'alarmsDeletedCount' => 2,
                'contextsCreatedCount' => 0,
                'contextsDeletedCount' => 0,
                'deltaAlarmsCreatedCount' => 1,
                'deltaAlarmsDeletedCount' => 2,
                'deltaContextsCreatedCount' => 0,
                'deltaContextsDeletedCount' => 0
            },
            'name' => 'fmx_monitor',
            'timestamp' => '2022-12-16T11:15:30.709317+00:00',
            'host' => 'fmx-engine-785f76658b-gtz47'
        },
        {
            'data' => {
                'alarmsCreatedCount' => 1,
                'alarmsDeletedCount' => 2,
                'contextsCreatedCount' => 0,
                'contextsDeletedCount' => 0,
                'deltaAlarmsCreatedCount' => 1,
                'deltaAlarmsDeletedCount' => 2,
                'deltaContextsCreatedCount' => 0,
                'deltaContextsDeletedCount' => 0
            },
            'name' => 'fmx_monitor',
            'timestamp' => '2022-12-16T11:15:37.345307+00:00',
            'host' => 'fmx-engine-785f76658b-wftrp'
        },
        {
            'data' => {
                'alarmsCreatedCount' => 1,
                'alarmsDeletedCount' => 2,
                'contextsCreatedCount' => 0,
                'contextsDeletedCount' => 0,
                'deltaAlarmsCreatedCount' => 0,
                'deltaAlarmsDeletedCount' => 0,
                'deltaContextsCreatedCount' => 0,
                'deltaContextsDeletedCount' => 0
            },
            'name' => 'fmx_monitor',
            'timestamp' => '2022-12-16T11:30:30.709317+00:00',
            'host' => 'fmx-engine-785f76658b-wftrp'
        },
        {
            'data' => {
                'alarmsCreatedCount' => 1,
                'alarmsDeletedCount' => 2,
                'contextsCreatedCount' => 0,
                'contextsDeletedCount' => 0,
                'deltaAlarmsCreatedCount' => 0,
                'deltaAlarmsDeletedCount' => 0,
                'deltaContextsCreatedCount' => 0,
                'deltaContextsDeletedCount' => 0
            },
            'name' => 'fmx_monitor',
            'timestamp' => '2022-12-16T11:30:37.345307+00:00',
            'host' => 'fmx-engine-785f76658b-gtz47'
        }
    ];

    my $r_got = undef;
    my $module = new Test::MockModule('DataStore');
    $module->mock(
        'storeIrregularData',
        sub ($$$$$$) {
            my ($r_tableModel, $r_multiId, $service, $r_propertyValues, $r_columnMap, $r_datasets ) = @_;
            $r_got = $r_datasets;
        }
    );

    ModelledEvents::processModelEvents(
        "TestSite",
        $r_model,
        { 'fmx-engine-785f76658b-gtz47' => 1,  'fmx-engine-785f76658b-wftrp' => 2 },
        $r_events,
        { 'fmx-engine-785f76658b-gtz47' => 'fmx-engine', 'fmx-engine-785f76658b-wftrp' => 'fmx-engine' },
        {},
        '2022-12-16'
    );

    # The TOR:FMX should left only one event per time
    # There's only one sample because filteridle should drop the second value
    my $r_expected = [
        {
            'samples' => [
                {
                    'host' => 'fmx-engine-785f76658b-gtz47',
                    'time' => 1671189330,
                    'timestamp' => '2022-12-16 11:15:30',
                    'deltaContextsDeletedCount' => 0,
                    'deltaContextsCreatedCount' => 0,
                    'deltaAlarmsCreatedCount' => 1,
                    'deltaAlarmsDeletedCount' => 2
                }
            ],
            'properties' => {}
        }
    ];
    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test TOR::FMX okay"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

sub test_fmx_rule() {
    my $xsd_doc = XML::LibXML::Schema->new(location => $ANALYSIS_DIR . "/modelled/events/models/modelledevents.xsd");
    my $r_model = ModelFile::processModelFile($ANALYSIS_DIR . "/modelled/events/models/TOR/fm/enm_fmx_rule.xml", $xsd_doc);

    my $r_events = [
        {
            'data' => {
                'count' => 21,
                'moduleName' => 'RNX_Manually_Locked_TRX-GRAN',
                'blockID' => 4,
                'engine' => 1,
                'blockType' => 'UNIX-TRIGGER',
                'ruleName' => 'Update_Node-Cell_HashMap',
                'blockName' => 'Update_Node-Cell_HashMap'
            },
            'name' => 'fmx_rule',
            'host' => 'vio-5653-fmx-1',
            'timestamp' => '2023-01-11T00:00:17.082095+00:00'
        },
        {
          'timestamp' => '2023-01-11T00:00:17.082095+00:00',
          'host' => 'vio-5653-fmx-1',
          'data' => {
                      'ruleName' => 'Cell_Manually_Locked_Alarm_Rule',
                      'blockName' => 'Manually_Locked_Cell_Alarm_Event',
                      'blockType' => 'ENM-EVENT-TRIGGER',
                      'count' => 14359,
                      'blockID' => 28,
                      'moduleName' => 'RNX_Manually_Locked_Cell-WRAN',
                      'engine' => 1
                    },
          'name' => 'fmx_rule'
        },
        {
          'data' => {
                      'ruleName' => 'Update_Node-Cell_HashMap',
                      'blockName' => 'Update_Node-Cell_HashMap',
                      'blockType' => 'UNIX-TRIGGER',
                      'count' => 21,
                      'blockID' => 4,
                      'moduleName' => 'RNX_Manually_Locked_TRX-GRAN',
                      'engine' => 1
                    },
          'name' => 'fmx_rule',
          'timestamp' => '2023-01-11T00:15:07.729402+00:00',
          'host' => 'vio-5653-fmx-1'
        },
        {
          'data' => {
                      'blockType' => 'ENM-EVENT-TRIGGER',
                      'blockName' => 'Manually_Locked_Cell_Alarm_Event',
                      'ruleName' => 'Cell_Manually_Locked_Alarm_Rule',
                      'count' => 14359,
                      'moduleName' => 'RNX_Manually_Locked_Cell-WRAN',
                      'blockID' => 28,
                      'engine' => 1
                    },
          'name' => 'fmx_rule',
          'timestamp' => '2023-01-11T00:15:07.729402+00:00',
          'host' => 'vio-5653-fmx-1'
        }
    ];

    my $r_got = undef;
    my $module = new Test::MockModule('DataStore');
    $module->mock(
        'storeIrregularData',
        sub ($$$$$$) {
            my ($r_tableModel, $r_multiId, $service, $r_propertyValues, $r_columnMap, $r_datasets ) = @_;
            $r_got = $r_datasets;
        }
    );

    ModelledEvents::processModelEvents(
        "TestSite",
        $r_model,
        { 'vio-5653-fmx-1' => 1 },
        $r_events,
        { 'vio-5653-fmx-1' => 'fmx-engine' },
        {},
        '2022-12-16'
    );

    # The TOR:FMX should left only one event per time
    # There's only one sample because filteridle should drop the second value
    my $r_expected = [
          {
            'properties' => {},
            'samples' => [
                           {
                             'count' => 21,
                             'moduleName' => 'RNX_Manually_Locked_TRX-GRAN',
                             'host' => 'vio-5653-fmx-1',
                             'ruleName' => 'Update_Node-Cell_HashMap',
                             'timestamp' => '2023-01-11 00:00:17',
                             'engine' => 1,
                             'time' => 1673395217,
                             'blockID' => 4,
                             'blockName' => 'Update_Node-Cell_HashMap',
                             'blockType' => 'UNIX-TRIGGER'
                           },
                           {
                             'blockType' => 'ENM-EVENT-TRIGGER',
                             'blockName' => 'Manually_Locked_Cell_Alarm_Event',
                             'ruleName' => 'Cell_Manually_Locked_Alarm_Rule',
                             'timestamp' => '2023-01-11 00:00:17',
                             'engine' => 1,
                             'time' => 1673395217,
                             'blockID' => 28,
                             'count' => 14359,
                             'host' => 'vio-5653-fmx-1',
                             'moduleName' => 'RNX_Manually_Locked_Cell-WRAN'
                           },
                           {
                             'blockType' => 'UNIX-TRIGGER',
                             'blockName' => 'Update_Node-Cell_HashMap',
                             'ruleName' => 'Update_Node-Cell_HashMap',
                             'blockID' => 4,
                             'time' => 1673396107,
                             'timestamp' => '2023-01-11 00:15:07',
                             'engine' => 1,
                             'moduleName' => 'RNX_Manually_Locked_TRX-GRAN',
                             'host' => 'vio-5653-fmx-1',
                             'count' => 21
                           },
                           {
                             'blockName' => 'Manually_Locked_Cell_Alarm_Event',
                             'blockType' => 'ENM-EVENT-TRIGGER',
                             'count' => 14359,
                             'host' => 'vio-5653-fmx-1',
                             'moduleName' => 'RNX_Manually_Locked_Cell-WRAN',
                             'ruleName' => 'Cell_Manually_Locked_Alarm_Rule',
                             'engine' => 1,
                             'timestamp' => '2023-01-11 00:15:07',
                             'time' => 1673396107,
                             'blockID' => 28
                           }
                         ]
          }
    ];
    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test TOR::FMX fmx_rule okay"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

sub test_filterValue() {
    my $xsd_doc = XML::LibXML::Schema->new(location => $ANALYSIS_DIR . "/modelled/events/models/modelledevents.xsd");
    my $r_model = ModelFile::processModelFile($THIS_DIR . "/filtervalue.xml", $xsd_doc);

    my $r_events = [
        {
            'data' => {
                'metricA' => 1,
                'metricB' => "VALID"
            },
            'name' => 'EventTypeA',
            'timestamp' => '2022-01-01T20:21:27.685+07:00',
            'host' => 'host1'
        },
        {
            'data' => {
                'metricA' => 2,
                'metricB' => "IN VALID"
            },
            'name' => 'EventTypeA',
            'timestamp' => '2022-01-01T20:22:27.685+07:00',
            'host' => 'host1'
        },
        {
            'data' => {
                'metricA' => 3
            },
            'name' => 'EventTypeB',
            'timestamp' => '2022-01-01T20:23:27.685+07:00',
            'host' => 'host1'
        }
    ];

    my $r_got = undef;
    my $module = new Test::MockModule('DataStore');
    $module->mock(
        'storeIrregularData',
        sub ($$$$$$) {
            my ($r_tableModel, $r_multiId, $service, $r_propertyValues, $r_columnMap, $r_datasets ) = @_;
            $r_got = $r_datasets;
        }
    );

    ModelledEvents::processModelEvents(
        "TestSite",
        $r_model,
        { 'host1' => 1 },
        $r_events,
        { 'host1' => 'thesg' },
        {},
        '2022-01-01'
    );

    # filtervalues should keep
    # 1st event because it matches
    # 3rd event because it's event type doesn't have the filter value
    my $r_expected = [
          {
            'samples' => [
                           {
                             'metricB' => 'VALID',
                             'time' => 1641068487,
                             'timestamp' => '2022-01-01 20:21:27',
                             '_metricA' => 1
                           },
                           {
                             'timestamp' => '2022-01-01 20:23:27',
                             'time' => 1641068607,
                             '_metricA' => 3
                           }
                         ],
            'properties' => {}
          }
    ];
    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test filtervalue"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

sub test_multiSourceValue() {
    my $xsd_doc = XML::LibXML::Schema->new(location => $ANALYSIS_DIR . "/modelled/events/models/modelledevents.xsd");
    my $r_model = ModelFile::processModelFile($THIS_DIR . "/multisourcetarget.xml", $xsd_doc);

    my $r_events = [
        {
            'data' => {
                'metricA' => 1,
            },
            'name' => 'EventTypeA',
            'timestamp' => '2022-01-01T20:21:27.685+07:00',
            'host' => 'host1'
        }
    ];

    my $r_gotDatasets = undef;
    my $module = new Test::MockModule('DataStore');
    $module->mock(
        'storeIrregularData',
        sub ($$$$$$) {
            my ($r_tableModel, $r_multiId, $service, $r_propertyValues, $r_columnMap, $r_datasets ) = @_;
            $r_gotDatasets = $r_datasets;
        }
    );

    ModelledEvents::processModelEvents(
        "TestSite",
        $r_model,
        { 'host1' => 1 },
        $r_events,
        { 'host1' => 'thesg' },
        {},
        '2022-01-01'
    );

    # multi source target metrics should generate samples
    # with a pseudo source name of "_<target>"
    # The model has a multi source target of serverid
    my $r_expected = [
          {
            'samples' => [
                           {
                             'metricA' => 1,
                             'time' => 1641068487,
                             'timestamp' => '2022-01-01 20:21:27',
                             '_serverid' => 'host1'
                           }
                         ],
            'properties' => {}
          }
    ];
    my $testResult = is_deeply(
        $r_gotDatasets,
        $r_expected,
        "Test multiSourceTarget with pseudo source"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_gotDatasets);
    }

}

test_applyScale();
test_PseduoSources();
test_aggregateOkay();
test_aggregateNonMandOkay();
test_aggregateMissingGroupAttr();
test_aggregateServiceGroup();
test_processModelEventsExplodeEmptyWithIncr();
test_hook();
test_fmx();
test_fmx_rule();
test_filterValue();
test_multiSourceValue();

done_testing();
