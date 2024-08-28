package misc::ApacheErrorLog;

use strict;
use warnings;
use EnmServiceGroup;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}


sub init($$$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    $self->{'analysisDir'} = $r_cliArgs->{'analysisDir'};

    if ( exists $r_incr->{'misc::ApacheErrorLog'} ) {
        $self->{'data'} = $r_incr->{'misc::ApacheErrorLog'};
    } else {
        $self->{'data'} = {
            'index' => 0,
        };
        opendir DIR, $self->{'analysisDir'} or die "Failed to open " . $self->{'analysisDir'};
        while ( my $fileName = readdir(DIR) ) {
            if ( $fileName =~ /^apache_error.log\.\d+\.gz/ ) {
                unlink( $self->{'analysisDir'} . "/" . $fileName);
            }
        }
        closedir DIR;
    }

    # Point at the next output file
    $self->{'data'}->{'index'}++;
    my $filename = sprintf("%s/apache_error.log.%03d.gz", $self->{'analysisDir'}, $self->{'data'}->{'index'});
    open my $fh, ">:gzip", $filename or die "Cannot open $filename: $!";
    $self->{'outFh'} = $fh;

    my $r_serverMap = enmGetServiceGroupInstances($r_cliArgs->{'site'},$r_cliArgs->{'date'},"httpd");
    my @subscriptions = ();
    foreach my $server ( keys %{$r_serverMap} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'httpd_error_log' };
    }
    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    my $msg = $message;
    $msg =~ s/^\s*//;
    print {$self->{'outFh'}} $msg, "\n";
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( ! exists $self->{'outFh'} ) {
        return;
    }

    close $self->{'outFh'};

    $r_incr->{'misc::ApacheErrorLog'} = $self->{'data'};    
}

1;
