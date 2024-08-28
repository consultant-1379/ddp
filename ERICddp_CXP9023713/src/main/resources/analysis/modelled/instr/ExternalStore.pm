package ExternalStore;

use strict;
use warnings;

use URI;
use IO::Socket;
use Data::Dumper;

our $CHUNKED = 1;
our $POST = 2;
our $LWP = 3;

our $MODE = $LWP;

our $BATCH_SIZE = 100000;

sub new {
    my $klass = shift;
    my $endPoint = shift;
    my $site = shift;

    my $self = bless {}, $klass;

    $self->{'batch'} = [];
    $self->{'endpoint'} = $endPoint;
    $self->{'site'} = $site;

    return $self;
}

sub open($) {
    my ($self) = @_;

    if ( $MODE == $CHUNKED || $MODE == $POST ) {
        my $uri = URI->new($self->{'endpoint'});
        my $sock = new IO::Socket::INET (
            PeerAddr => $uri->host,
            PeerPort => $uri->port,
            Proto => 'tcp',
            );
        $self->{'sock'} = $sock;
    } elsif ( $MODE == $LWP ) {
        $self->{'ua'} = new LWP::UserAgent();
    }

    if ( $MODE == $CHUNKED ) {
        $self->writePostHeader(undef);
    }
}

sub writePostHeader($$) {
    my ($self,$contentSize) = @_;

    my $uri = URI->new($self->{'endpoint'});

    $self->{'sock'}->printf("POST %s HTTP/1.1\r\n", $uri->path);
    $self->{'sock'}->printf("Host: %s:%d\r\n", $uri->host, $uri->port);
    $self->{'sock'}->print("Connection: Keep-Alive\r\n");
    $self->{'sock'}->print("Content-Type: text/plain\r\n");
    if ( $MODE == $CHUNKED ) {
        $self->{'sock'}->print("Transfer-Encoding: chunked\r\n");
    } else {
        $self->{'sock'}->printf("Content-Length: %d\r\n", $contentSize);
    }

    $self->{'sock'}->print("\r\n");
}

sub close($) {
    my ($self) = @_;

    if ( $#{$self->{'batch'}} != -1 ) {
        $self->sendBatch();
    }

    if ( $MODE == $CHUNKED ) {
        $self->{'sock'}->print("0\r\n\r\n");
    }

    if ( $MODE == $CHUNKED || $MODE == $POST ) {
        $self->{'sock'}->close();
    }
}

sub sendBatch($) {
    my ($self) = @_;

    if ( $MODE == $CHUNKED ) {
        $self->sendWithChunk();
    } elsif ( $MODE == $POST ) {
        $self->sendWithPost();
    } elsif ( $MODE == $LWP ) {
        $self->sendWithLWP();
    }

    $self->{'batch'} = [];
}

sub sendWithChunk($) {
    my ($self) = @_;

    my $content = join("\r\n", @{$self->{'batch'}}) . "\r\n";

    #my $res = $remoteClient->post( $remoteEndPoint, Content => $content );
    $self->{'sock'}->printf("%X\r\n", length($content));
    $self->{'sock'}->print($content);
    $self->{'sock'}->print("\r\n");
}

sub sendWithPost($) {
    my ($self) = @_;

    my $content = join("\r\n", @{$self->{'batch'}}) . "\r\n";
    $self->writePostHeader(length($content));
    $self->{'sock'}->print($content);
}

sub sendWithLWP($) {
    my ($self) = @_;

    my $content = join("\r\n", @{$self->{'batch'}}) . "\r\n";

    my $req = HTTP::Request->new( 'POST' => $self->{'endpoint'} );
    $req->content($content);
    $req->header('Content-Length', length($content));

    my $response = $self->{'ua'}->simple_request( $req );
    if ( ! $response->is_success() ) {
        print("ERROR: " . $response->status_line() . "\n");
    }
}

sub process($$$) {
    my ($self, $r_ts) = @_;

    my $encodeStart = Time::HiRes::time();

    my $r_labels = $r_ts->{'Labels'};
    my $name = $r_labels->{'__name__'};

    my @labelNameValues = 'site="' . $self->{'site'} . '"';
    while ( my ($name,$value) = each %{$r_labels} ) {
        if ( $name ne '__name__' ) {
            my $escapedValue = $value;
            $escapedValue =~ s/"/\\"/g;
            push @labelNameValues, $name . '="' . $escapedValue . '"';
        }
    }
    my $prefix = $name  . '{' . join(',', @labelNameValues) . '}';

    my $r_timestamps = $r_ts->{'Timestamps'};
    my $numSamples = $#{$r_timestamps} + 1;
    my $r_values = $r_ts->{'Values'};
    my $r_batch = $self->{'batch'};
    for ( my $index = 0; $index < $numSamples; $index++ ) {
        push @{$r_batch}, $prefix . ' ' . $r_values->[$index] . ' ' . $r_timestamps->[$index];
    }
    my $encodeEnd = Time::HiRes::time();

    if ( ! exists $::parseMetrics{'externalWrite.count'} ) {
        $::parseMetrics{'externalWrite.count'} = 0;
        $::parseMetrics{'externalWrite.encodeTimeTotal'} = 0;
        $::parseMetrics{'externalWrite.metricsTotal'} = 0;
        $::parseMetrics{'externalWrite.postTimeTotal'} = 0;
    }
    $::parseMetrics{'externalWrite.count'}++;
    $::parseMetrics{'externalWrite.encodeTimeTotal'} += $encodeEnd - $encodeStart;
    $::parseMetrics{'externalWrite.metricsTotal'} += $numSamples;

    if ( $#{$r_batch} > $BATCH_SIZE ) {
        my $postStart = Time::HiRes::time();
        $self->sendBatch();
        my $postEnd = Time::HiRes::time();
        $::parseMetrics{'externalWrite.postTimeTotal'} += $postEnd - $postStart;
    }

}

1;
