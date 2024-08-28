#!/usr/bin/env perl

#=====================================================================
# Script  : genericPhpToRootWrapper.pl
# Author  : Maheedhar Reddy Rachamalla (xmahrac)
# Purpose : The purpose of this script is to provide a general purpose wrapper script with
#           sudo (root) permissions which can be used to call other scripts that needs to be
#           executed as 'root' from PHP scripts (eg: DDP Admin GUI)
# Usage   : ${DDP_ROOT}/${NEW_DDP_VER}/server_setup/genericPhpToRootWrapper.pl \
#           --scriptid <SCRIPT_ID_THAT_NEEDS_TO_BE_RUN_AS_ROOT> --options <INPUT_OPTIONS_FOR_SCRIPT> \
#           --outputfile <PATH_TO_LOG_TO_REDIRECT_SCRIPT_OUTPUT>
#=====================================================================

use strict;
use warnings;
use Getopt::Long;

my %SUPPORTED_SCRIPTS = (
    'updaterepl' => '/data/ddp/current/server_setup/updateRepl',
    'parseResUsgLastNDays' => '/data/ddp/current/analysis/TOR/parseResUsgLastNDays',
    'alertMe' => '/data/ddp/current/rules/alertMe',
    'executeRules' => '/data/ddp/current/rules/executeRules',
    'loadMetrics' => '/data/ddp/current/analysis/common/loadMetrics'
);

sub main {
    my ( $scriptId, $inputOptions, $outputFile );
    my $result = GetOptions (
        "scriptid=s" => \$scriptId,
        "options=s" => \$inputOptions,
        "outputfile=s" => \$outputFile
    );

    if ( ! defined $scriptId || $scriptId eq "" || ! exists($SUPPORTED_SCRIPTS{$scriptId}) ) {
        &printUsage();
    }

    if ( ! defined $inputOptions ) {
        $inputOptions = "";
    }

    if ( ! defined $outputFile ) {
        $outputFile = "";
    }

    # If the script reaches this point then all the input arguments must be valid.
    # Add below any new scripts which need to be run as 'root' from php
    my $cmd = undef;
    if ( $scriptId eq 'updaterepl' ) {
        if ( $inputOptions =~ /-c/ ) {
            $cmd = "$SUPPORTED_SCRIPTS{$scriptId} $inputOptions > $outputFile";
        } else {
            $cmd = "$SUPPORTED_SCRIPTS{$scriptId} $inputOptions >> $outputFile &";
        }
    } elsif ( $scriptId eq 'parseResUsgLastNDays' ) {
        $cmd = "sudo -u statsadm PERL5OPT=\"-I/data/ddp/current/analysis/common\" $SUPPORTED_SCRIPTS{$scriptId} $inputOptions";
    } elsif ( $scriptId eq 'loadMetrics' ) {
        $cmd = "sudo -u statsadm $SUPPORTED_SCRIPTS{$scriptId} $inputOptions";
    } elsif ( $scriptId eq 'alertMe' || $scriptId eq 'executeRules' ) {
        $cmd = "$SUPPORTED_SCRIPTS{$scriptId} $inputOptions";
    }

    # https://perldoc.perl.org/functions/system
    # The return value is the exit status of the program as returned by the wait call.
    # To get the actual exit value, shift right by eight
    my $exitCode = system($cmd) >> 8;
    exit($exitCode);
}

sub printUsage() {
    print "It looks like there is some problem with the input arguments provided. " .
          "Please check the usage below:\n\n";
    print "Usage: ./genericPhpToRootWrapper --scriptid <SCRIPT_ID_THAT_NEEDS_TO_BE_RUN_AS_ROOT> " .
          "--options <INPUT_OPTIONS_FOR_SCRIPT> " .
          "--outputfile <PATH_TO_LOG_TO_REDIRECT_SCRIPT_OUTPUT>\n\n";
    print "Supported script ids and their respective scripts:\n";
    print "\t$_\t$SUPPORTED_SCRIPTS{$_}\n"    foreach(sort keys %SUPPORTED_SCRIPTS);
    exit 1;
}

main();
