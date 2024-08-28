use strict;
use warnings;

use Test::More;
use Test::Exception;

use File::Basename;

require MarkDownHelp;

our $DEBUG = 0;
our $THIS_DIR = dirname(__FILE__);

sub test_Fail($) {
    my ($filename) = @_;

    MarkDownHelp::parseMarkDownFile($THIS_DIR . "/markdown/faulty/" . $filename, {});
}

sub test_Okay() {

    my %helpRows = ();
    MarkDownHelp::parseMarkDownDir($THIS_DIR . "/markdown/okay", \%helpRows);

    my @actualKeys = sort keys %helpRows;
    my @exportedKeys = ( "file1_help1", "file1_help2", "file2_help1" );

    return is_deeply(\@actualKeys, \@exportedKeys, "test_Okay: expected keys");
}

test_Okay();

dies_ok{ test_Fail('mismatched_beginend.md') } 'Die when BEGIN and END keys do not match';
dies_ok{ test_Fail('mismatched_beginbegin.md') } 'Die when BEGIN followed by another BEGIN';
dies_ok{ test_Fail('mismatched_beginnoend.md') } 'Die when BEGIN has no END';

done_testing();
