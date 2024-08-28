use strict;
use warnings;

use Data::Dumper;
use Test::More;
use JSON;

use File::Basename;
use Cwd 'abs_path';

use StatsDB;

our $THIS_DIR;

BEGIN {
    # We want to put the analysis/modelled/instr into the INC path
    # This code needs to be in a BEGIN block because any "use" is executed during
    # compile time of the code
    $THIS_DIR = dirname(__FILE__);
    my $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../../../main/resources/analysis");
    unshift @INC, $ANALYSIS_DIR . "/modelled/instr";
    unshift @INC, $ANALYSIS_DIR . "/modelled/instr/modules";
}

use TOR::platform::LvsStats;

our $DEBUG = 0;

sub testLVS() {
    my $hook = new TOR::platform::LvsStats();

    StatsDB::setStatsDB_Debug($DEBUG);
    StatsDB::testMode(sprintf("%s/statsdb_lvsStats.json", $THIS_DIR));

    my $dbh = connect_db();

    my $r_cliArgs = {
        'siteid' => 1,
        'date' => '2022-01-01'
    };
    my $r_model = {};
    my $r_dataSets = [
        {
            'properties' => {
                'lhost' => {'sourcevalue' => 'vip-01'},
                'lport' => {'sourcevalue' => 1},
                'rhost' => {'sourcevalue' => 'otherpod-abcd'},
                'rport' => {'sourcevalue' => 1},
                'proto' => {'sourcevalue' => 'tcp'}
            },
            'samples' => [
                {
                    'eric_l4_external_address_packets_sent' => 1,
                    'time' => 1673396110,
                    'eric_l4_external_address_packets_received' => 0,
                    'eric_l4_external_address_bytes_received' => 0,
                    'eric_l4_external_address_bytes_sent' => 0,
                    'timestamp' => '2023-01-11 00:15:10',
                    'eric_l4_external_address_connections' => 0
                }
            ]
        },
        {
            'properties' => {
                'lhost' => {'sourcevalue' => 'vip-01'},
                'lport' => {'sourcevalue' => 2},
                'rhost' => {'sourcevalue' => 'otherpod-abcd'},
                'rport' => {'sourcevalue' => 2},
                'proto' => {'sourcevalue' => 'tcp'}
            },
            'samples' => [
                {
                    'eric_l4_external_address_packets_sent' => 0,
                    'time' => 1673396110,
                    'eric_l4_external_address_packets_received' => 0,
                    'eric_l4_external_address_bytes_received' => 0,
                    'eric_l4_external_address_bytes_sent' => 0,
                    'timestamp' => '2023-01-11 00:15:10',
                    'eric_l4_external_address_connections' => 0
                }
            ]
        },
        {
            'properties' => {
                'lhost' => {'sourcevalue' => 'vip-02'},
                'lport' => {'sourcevalue' => 2},
                'rhost' => {'sourcevalue' => 'ftspod-abcd'},
                'rport' => {'sourcevalue' => 2},
                'proto' => {'sourcevalue' => 'tcp'}
            },
            'samples' => [
                {
                    'eric_l4_external_address_packets_sent' => 1,
                    'time' => 1673396110,
                    'eric_l4_external_address_packets_received' => 0,
                    'eric_l4_external_address_bytes_received' => 0,
                    'eric_l4_external_address_bytes_sent' => 0,
                    'timestamp' => '2023-01-11 00:15:10',
                    'eric_l4_external_address_connections' => 0
                }
            ]
        },
        {
            'properties' => {
                'lhost' => {'sourcevalue' => 'vip-03'},
                'lport' => {'sourcevalue' => 3},
                'rhost' => {'sourcevalue' => 'ngixpod-abcd'},
                'rport' => {'sourcevalue' => 3},
                'proto' => {'sourcevalue' => 'tcp'}
            },
            'samples' => [
                {
                    'eric_l4_external_address_packets_sent' => 1,
                    'time' => 1673396110,
                    'eric_l4_external_address_packets_received' => 0,
                    'eric_l4_external_address_bytes_received' => 0,
                    'eric_l4_external_address_bytes_sent' => 0,
                    'timestamp' => '2023-01-11 00:15:10',
                    'eric_l4_external_address_connections' => 0
                }
            ]
        }
    ];
    my $r_columnMap = {};

    my $r_got = $hook->prestore(
        $r_cliArgs,
        $dbh,
        $r_model,
        $r_dataSets,
        $r_columnMap
    );

    # fts dataset should be dropped, we should only get a dataset returned for the otherpod
    my $r_expected = [
        {
            'properties' => {
                'lvsid' => {'sourcevalue' => 1}
            },
            'samples' => [
                           {
                             'eric_l4_external_address_connections' => 0,
                             'timestamp' => '2023-01-11 00:15:10',
                             'time' => 1673396110,
                             'eric_l4_external_address_packets_sent' => 1,
                             'eric_l4_external_address_bytes_sent' => 0,
                             'eric_l4_external_address_bytes_received' => 0,
                             'eric_l4_external_address_packets_received' => 0
                           }
                         ]

        }
    ];
    if ( ! is_deeply($r_got, $r_expected, "Verify LvsStats handling for filetransferservice") ) {
        print Dumper("is_deeply failed", $r_got);
    }
}

testLVS();

done_testing();
