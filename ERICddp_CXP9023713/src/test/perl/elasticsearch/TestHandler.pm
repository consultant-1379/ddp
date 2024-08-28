package TestHandler;

use strict;
use warnings;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    $self->{'entries'} = [];
    return $self;
}

sub init($$$$) {
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    push @{$self->{'entries'}}, {
        'timestamp' => $timestamp,
        'host' => $host,
        'program' => $program,
        'severity' => $severity,
        'message' => $message
    };
}

sub handleExceeded($$$) {
}

sub done($$$) {
}

sub getEntries($) {
    my ($self) = @_;

    return $self->{'entries'};
}

1;
