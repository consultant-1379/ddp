#!/usr/bin/env perl
use strict;
use warnings;
use Test::More;
use File::Basename;
use Cwd 'abs_path';
our $THIS_DIR = dirname($0);
our $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../../main/resources/analysis");
our $PARSE_ESHISTORY_MODULE = $ANALYSIS_DIR . "/TOR/elasticsearch/ParseEShistoryIndicesLog.pm";
require $PARSE_ESHISTORY_MODULE;

our $DEBUG = 0;

sub Test_ParseEShistoryIndicesLog($$)
{
    my ( $indicesLogs,$expectedSum ) = @_;
    my $actualSum = 0;
    my $eshistoryIndicesLogs = ParseEShistoryIndicesLog::parseLog($indicesLogs);

    while ( my ($indexName, $r_indexData) = each %{$eshistoryIndicesLogs} ) {
        while ( my ($timestamp, $r_sample) = each %{$r_indexData} ) {
            $actualSum += $r_sample->{'noOfDocs'};
        }
    }
    return $expectedSum == $actualSum;
}

ok(
    Test_ParseEShistoryIndicesLog(
        $THIS_DIR . "/eshistory.log",
        5566105
    ),
    "parseLog"
);

done_testing();
