#!/bin/env perl
use strict;
use warnings;

use Data::Dumper;
use Test::More;
use JSON;

use File::Basename;
use Cwd 'abs_path';

our $THIS_DIR;

BEGIN {
    # We want to put the analysis/TOR/versant into the INC path
    # This code needs to be in a BEGIN block because any "use" is executed during
    # compile time of the code
    $THIS_DIR = dirname(__FILE__);
    my $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../../main/resources/analysis/");
    unshift @INC, $ANALYSIS_DIR . "/TOR/versant";
}

require ParseMOs;

our $DEBUG = 0;

sub runTest($$$) {
    my ( $input, $expected, $testName ) = @_;

    my $r_result = ParseMOs::convertToAscii($input);
    if ( ! like($r_result, qr/$expected/, $testName) ) {
        print Dumper("like failed", $r_result, $expected);
    }
}

runTest("123", "123", "ParseMOs::convertToAscii TestA");
runTest("abc", "abc", "ParseMOs::convertToAscii TestB");
runTest("18\x{410}", "18A", "ParseMOs::convertToAscii TestC");
runTest("-_.", "-_.", "ParseMOs::convertToAscii TestD");
runTest("A1 B2", "A1 B2", "ParseMOs::convertToAscii TestE");

done_testing();
