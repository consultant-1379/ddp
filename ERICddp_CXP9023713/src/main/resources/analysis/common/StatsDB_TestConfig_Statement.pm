package StatsDB_TestConfig_Statement;

use Data::Dumper;

use strict;
use warnings;

sub new {
    my $class = shift;

    my $self = {
        'template' => shift,
        'data' => shift
    };

    if ( $::DEBUG > 11 ) { print Dumper("StatsDB_TestConfig_Statement::new template", $self->{'template'}); }

    return bless $self, $class;
}

sub execute() {
    my $self = shift;

    if ( ! defined $self->{'data'} ) {
        StatsDB_TestConfig::recordError("StatsDB_TestConfig_Statement::execute Cound not find data for " . $self->{'template'});
        return 0;
    }

    my $prepStmtArgsKey = join("@", @_);
    if ($::DEBUG > 5) { printf("StatsDB_TestConfig_Statement::execute %s %s\n", $self->{'template'}, $prepStmtArgsKey); }

    my $r_rows = undef;
    foreach my $r_dataset ( @{$self->{'data'}} ) {
        my $key = join("@", @{$r_dataset->{'param'}});
        if ( $::DEBUG > 11 ) { print "StatsDB_TestConfig_Statement::execute key=$key $prepStmtArgsKey\n"; }
        if ( $key eq $prepStmtArgsKey ) {
            if ( $::DEBUG > 11 ) { print "StatsDB_TestConfig_Statement::execute matched key $key\n"; }
            $r_rows = $r_dataset->{'rows'};
        }
    }
    $self->{'index'} = 0;
    if ( defined $r_rows ) {
        $self->{'rows'} = $r_rows;
        return 1;
    } else {
        StatsDB_TestConfig::recordError("StatsDB_TestConfig_Statement::execute No match for params $prepStmtArgsKey");
        return 0;
    }
}

sub getNext() {
    my $self = shift;

    my $result = undef;
    if ( $self->{'index'} <= $#{$self->{'rows'}} ) {
        $result = $self->{'rows'}->[$self->{'index'}];
    }
    $self->{'index'}++;

    return $result;
}

sub fetchrow_hashref() {
    my $self = shift;
    return $self->getNext();    
}

sub fetchrow_array() {
    my $self = shift;
    my $array_ref = $self->getNext();
    if ( defined $array_ref ) {
        return @{$array_ref};
    } else {
        return ();
    }
}

sub finish() {
    my $self = shift;
}

1;
