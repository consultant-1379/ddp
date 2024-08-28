use strict;
use warnings;

use Data::Dumper;
use Test::More;
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
}

use StatsDB;

require ParseEventData;
require ModelFile;
require EventDataFile;

our $DEBUG = 0;

sub test_getSgInstances() {
    StatsDB::testMode($THIS_DIR . "/statsdb_getSgInstances.json");
    my %expected = (
        'thesg' => {
            'thehost' => 1
        }
    );

    my $dbh = connect_db();
    my $r_got = ParseEventData::getSgInstances($dbh, "TestSite", "2022-01-01");
    is_deeply($r_got, \%expected, "Verify getSgInstances");
}

sub test_MultiModel() {
    my $xsd_doc = XML::LibXML::Schema->new(location => $ANALYSIS_DIR . "/modelled/events/models/modelledevents.xsd");
    my @models = (
        ModelFile::processModelFile($THIS_DIR . "/multi_model_1.xml", $xsd_doc),
        ModelFile::processModelFile($THIS_DIR . "/multi_model_2.xml", $xsd_doc)
    );
    my %eventMap = ();
    my @modeledEvents = ();
    my %sgInstances = (
        'thesg' => {
            'thehost' => 1
        }
    );
    ParseEventData::buildEventMap(\%sgInstances, \@models, \%eventMap, \@modeledEvents);
    my $r_logEntries = [
        {
            '_source' => {
                'host' => 'host1',
                'program' => 'DDCDATA',
                'message' => 'EventTypeA { "metricA": 1 }',
                'timestamp' => '2022-01-01T20:21:27.685+07:00'
            }
        }
    ];
    EventDataFile::processEntries($r_logEntries, \%eventMap, 'Not21', undef);

    my @expected = ();
    my @got = ();
    foreach my $r_modeledEvents ( @modeledEvents ) {
        push @expected, [
            {
                'timestamp' => '2022-01-01T20:21:27.685+07:00',
                'name' => 'EventTypeA',
                'data' => {
                            'metricA' => 1
                        },
                'host' => 'host1'
            }
        ];
        push @got, $r_modeledEvents->{'events'};
    }
    is_deeply(\@got, \@expected, "Verify Multiple models for event");
}

setStatsDB_Debug($DEBUG);

test_getSgInstances();
test_MultiModel();

done_testing();
