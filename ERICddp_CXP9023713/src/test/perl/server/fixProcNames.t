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
    $THIS_DIR = dirname(__FILE__);
    my $ANALYSIS_DIR = abs_path($THIS_DIR . "/../../../main/resources/analysis");
    unshift @INC, $ANALYSIS_DIR . "/server";
}

require FixProcNames;

our $DEBUG = 0;

sub testDoRemap() {
    StatsDB::testMode(sprintf("%s/statsdb_doremap.json", $THIS_DIR));

    my %remapCmds = (
        # cmd2 to cmd1 is a direct remap - i.e. no existing rows for cmd1
        # so just have to change the proc_id to that for cmd1
        'cmd2' => 'cmd1',
        # cmd4 => cmd3 is an update of existing row, i.e. we already have
        # a sample for the server and timestamp for cmd3 so we need to update
        # the exist row
        'cmd4' => 'cmd3',
        # cmd6 => cmd5 is and in place update, i.e we do have rows for cmd5 but
        # none that match the serverid/procid/time of the cmd6 row so we can just
        # update procid for the cmd6 row
        'cmd6' => 'cmd5'
    );

    my $dbh = StatsDB::connect_db();

    FixProcNames::setDoUpdate(1);
    FixProcNames::doRemap(\%remapCmds, $dbh, 1);

    my $error = $dbh->errstr();
    is($error, undef, "Verfiy FixProcNames::doRemap")
}

testDoRemap();

done_testing();



