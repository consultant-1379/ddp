package EAMCommon;

use warnings;
use strict;
use File::Basename;

sub stripCommand($) {
    my ($cmd) = @_;

    if ( $cmd =~ /^ManagedElement/ ) {
        return "Tree Navigation";
    } elsif ($cmd =~ /^SystemFunction/ || $cmd =~ /^AxeFunctions/ ) {
        return "[INVALID_CMD_FDN]";
    } elsif ( $cmd !~ /^[A-Za-z\[]/ ) {
        return "[INVALID_CMD]";
    } elsif ( $cmd =~ /^([^:;= ]+)[:;=](.+)/ ) {
        # Strip any arg values
        my ($mainCmd,$args) = ($1,$2);

        if ( $::DEBUG > 6 ) { print "readCommandLog: mainCmd=$mainCmd args=$args\n"; }
        my @argList = ();
        foreach my $arg ( split(",",$args) ) {
            my ($argName) = $arg =~ /^([^=]+)/;
            $argName =~ s/-\d+/-[NUM]/g; # Strip number of end of args like UPDR-16658
            $argName =~ s/^\d+$/[NUM]/;  # Strip number out where argname is just a number
            $argName =~ s/_\d+/_[NUM]/g;  # Strip number out where argname contains underscore with number
            $argName =~ s/#\d+/#[NUM]/g;  # Strip number out where argname contains underscore with number
            push @argList, $argName;
        }
        $cmd = $mainCmd;
        if ( $#argList > - 1 ) {
            $cmd .= ":" . join(",", @argList);
        }
        if ( $::DEBUG > 6 ) { print "readCommandLog: cmd=$cmd\n"; }
    } elsif ( $cmd =~ /\s+/ ) {
        my @parts = split (" ",$cmd);
        if ( $::DEBUG > 6 ) { print Dumper("readCommandLog: cmd parts", \@parts); }
        $cmd = shift @parts;
        foreach my $part ( @parts ) {
            if ( $part =~ /^-/ ) {
                $cmd .= " $part";
            }
        }
        if ( $::DEBUG > 6 ) { print "readCommandLog: cmd=$cmd\n"; }
    }

    return $cmd;
}

1;
