use strict;
use warnings;

use Data::Dumper;
use Test::More;
use JSON;

use File::Basename;
use Cwd 'abs_path';

our $THIS_DIR;

BEGIN {
    # We want to put the analysis/modelled/instr into the INC path
    # This code needs to be in a BEGIN block because any "use" is executed during
    # compile time of the code
    $THIS_DIR = abs_path(dirname(__FILE__));
    my $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../main/resources/analysis");
    unshift @INC, $ANALYSIS_DIR . "/elasticsearch";
    unshift @INC, $THIS_DIR;
}

require SplitLog;
require TestHandler;

sub test_processEntriesENM() {
    my $testHandler = TestHandler->new();

    my $r_logEntries = [
            {
                "_source" => {
                    "host" => "svc-8-msapgfm",
                    "message" => "\ufeffWARN  [com.ericsson.oss.itpf.datalayer.dps.neo4j.driver.transport.bolt.metrics.DefaultQueryMetrics] (QueryCacheLoggerTimer) [TQ]4,FM:NeConversionRules,N,0;",
                    "program" => "JBOSS",
                    "severity" => "warning",
                    "timestamp" => "2022-11-21T00:00:00.000+00:00"
                }
            },
    ];

    my $r_expected = [
        {
            'message' => 'FeffWARN  [com.ericsson.oss.itpf.datalayer.dps.neo4j.driver.transport.bolt.metrics.DefaultQueryMetrics] (QueryCacheLoggerTimer) [TQ]4,FM:NeConversionRules,N,0;',
            'severity' => 'warning',
            'host' => 'svc-8-msapgfm',
            'program' => 'JBOSS',
            'timestamp' => '2022-11-21T00:00:00.000+00:00'
        }
    ];

    SplitLog::processEntries(
        $r_logEntries,
        {},  # $r_filteredSubscriptions
        [ $testHandler ],  # $r_wildCardSubscriptions
        {},  # $r_countsByHost
        {},  # $r_sizeByHost
        {},  # $r_otherIncrFlags
        "9", # esVersion,
        {}   # r_mappedSrv
    );

    my $r_got = $testHandler->getEntries();

    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test handling of ENM Log entry"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

sub test_processEntriesEIAP() {
    my $testHandler = TestHandler->new();

    my $r_logEntries = [
            {
                "_source" =>  {
                    "severity" =>  "info",
                    "metadata" =>  {
                        "pod_name" =>  "eric-oss-enm-fns-6b4789f7d5-hz8bq"
                    },
                    '@timestamp' =>  "2022-11-18T12:17:26.758Z",
                    "message" =>  "2022-11-18T12:17:26.758Z\u00a0\u00a0INFO 1 --- [nio-8080-exec-6] c.e.o.dmi.controller.health.HealthCheck\u00a0\u00a0: eric-oss-enm-fns is UP and healthy"
                }
            }
    ];

    my $r_expected = [
        {
            'timestamp' => '2022-11-18T12:17:26.758Z',
            'program' => 'UNKNOWN',
            'message' => '2022-11-18T12:17:26.758Z00a000a0INFO 1 --- [nio-8080-exec-6] c.e.o.dmi.controller.health.HealthCheck00a000a0: eric-oss-enm-fns is UP and healthy',
            'host' => 'eric-oss-enm-fns-6b4789f7d5-hz8bq',
            'severity' => 'info'
        }
    ];

    SplitLog::processEntries(
        $r_logEntries,
        {},  # $r_filteredSubscriptions
        [ $testHandler ],  # $r_wildCardSubscriptions
        {},  # $r_countsByHost
        {},  # $r_sizeByHost
        {},  # $r_otherIncrFlags
        "9", # esVersion,
        {}   # r_mappedSrv
    );

    my $r_got = $testHandler->getEntries();

    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test handling of EIAP Log entry without container_name"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

sub test_processEntriesEIAPwithContainer() {
    my $testHandler = TestHandler->new();

    my $r_logEntries = [
            {
                "_source" =>  {
                    "severity" =>  "info",
                    "metadata" =>  {
                        "pod_name" =>  "eric-oss-enm-fns-6b4789f7d5-hz8bq",
                        "container_name" => "thecontainer"
                    },
                    '@timestamp' =>  "2022-11-18T12:17:26.758Z",
                    "message" =>  "2022-11-18T12:17:26.758Z\u00a0\u00a0INFO 1 --- [nio-8080-exec-6] c.e.o.dmi.controller.health.HealthCheck\u00a0\u00a0: eric-oss-enm-fns is UP and healthy"
                }
            }
    ];

    my $r_expected = [
        {
            'timestamp' => '2022-11-18T12:17:26.758Z',
            'program' => 'thecontainer',
            'message' => '2022-11-18T12:17:26.758Z00a000a0INFO 1 --- [nio-8080-exec-6] c.e.o.dmi.controller.health.HealthCheck00a000a0: eric-oss-enm-fns is UP and healthy',
            'host' => 'eric-oss-enm-fns-6b4789f7d5-hz8bq',
            'severity' => 'info'
        }
    ];

    SplitLog::processEntries(
        $r_logEntries,
        {},  # $r_filteredSubscriptions
        [ $testHandler ],  # $r_wildCardSubscriptions
        {},  # $r_countsByHost
        {},  # $r_sizeByHost
        {},  # $r_otherIncrFlags
        "9", # esVersion,
        {}   # r_mappedSrv
    );

    my $r_got = $testHandler->getEntries();

    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test handling of EIAP Log entry with container_name"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

sub test_MissingHost() {
    my $testHandler = TestHandler->new();

    my $r_logEntries = [
            {
                "_source" => {
                    "severity" => [
                        "INFO",
                        "INFO"
                    ],
                    '@timestamp' => "2023-03-01T00:00:44.929Z",
                    "service_id" => "eric-pm-events-processor-er",
                    "message" => "\"operatorName\" : \"flatMapGroupsWithState\","
                }
            }
    ];

    my $r_expected = [
        {
            'message' => '"operatorName" : "flatMapGroupsWithState",',
            'program' => 'UNKNOWN',
            'host' => 'NA',
            'severity' => 'INFO,INFO',
            'timestamp' => '2023-03-01T00:00:44.929Z'
        }
    ];

    SplitLog::processEntries(
        $r_logEntries,
        {},  # $r_filteredSubscriptions
        [ $testHandler ],  # $r_wildCardSubscriptions
        {},  # $r_countsByHost
        {},  # $r_sizeByHost
        {},  # $r_otherIncrFlags
        "9", # esVersion,
        {}   # r_mappedSrv
    );

    my $r_got = $testHandler->getEntries();

    my $testResult = is_deeply(
        $r_got,
        $r_expected,
        "Test handling of ECSON Log entry without hostname"
    );

    if ( ! $testResult ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

test_processEntriesENM();
test_processEntriesEIAP();
test_processEntriesEIAPwithContainer();
test_MissingHost();


done_testing();
