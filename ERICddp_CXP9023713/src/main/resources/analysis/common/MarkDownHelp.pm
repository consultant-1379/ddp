package MarkDownHelp;

use strict;
use warnings;

use Text::MultiMarkdown 'markdown';
use Data::Dumper;

sub parseMarkDownDir($$) {
    my ($dir,$r_helpRows) = @_;

    opendir DIR,$dir;
    my @dirEntries = readdir(DIR);
    close DIR;

    foreach my $dirEntry ( @dirEntries ) {
        if ( $dirEntry =~ /.md$/ ) {
            parseMarkDownFile($dir . "/" . $dirEntry, $r_helpRows);
        }
    }
}

sub parseMarkDownFile($$) {
    my ($filePath,$r_helpRows) = @_;
    open (my $fh, '<', $filePath) or die "Could not open $filePath";
    my $keepGoing = 1;
    while ( $keepGoing ) {
        my ($key,$r_markDownLines) = getEntry($fh);
        if ( $::DEBUG > 4 ) { printf "parseMarkDownFile: key=%s\n", (defined $key ? $key : "undefined"); }
        if (defined $key) {
            my $markDownStr = join('', @{$r_markDownLines});
            my $html = markdown($markDownStr);
            if ( $::DEBUG > 8 ) { print "parseMarkDownFile: html\n", $html; }
            $r_helpRows->{$key} = $html;
        } else {
            $keepGoing = 0;
        }
    }
    close($fh);
}

sub getEntry($) {
    my ($fh) = @_;
    my $key = undef;
    my $r_lines = undef;

    while (my $line = <$fh> ) {
        if ( $::DEBUG > 9 ) { print "getEntry: line=$line"; }
        if ($line =~ /^BEGIN (\S+)$/ ) {
            my $keyVal = $1;
            if ( $::DEBUG > 5 ) { print "getEntry: BEGIN keyVal=$keyVal\n"; }
            if (defined $key) {
                die "BEGIN for $keyVal while processing $key";
            } else {
                $key = $keyVal;
                $r_lines = [];
            }
        } elsif ($line =~ /^END (\S+)$/ ) {
            my $keyVal = $1;
            if ( $::DEBUG > 5 ) { print "getEntry: END keyVal=$keyVal\n"; }
            if (defined $key) {
                $keyVal eq $key or die "END for $keyVal while processing $key";
                if ($::DEBUG > 5) { print Dumper("getEntry returning", $keyVal,$r_lines);}
                return ($keyVal,$r_lines);
            }
        } elsif (defined $r_lines) {
            if ( $::DEBUG > 5 ) { print "getEntry: Adding line=$line"; }
            push @{$r_lines}, $line;
        }
    }

    # If we get here with key defined it means the last section
    # is not well formed, i.e. no END
    ! defined $key or die "No END found for $key";

    return (undef, undef);
}

1;
