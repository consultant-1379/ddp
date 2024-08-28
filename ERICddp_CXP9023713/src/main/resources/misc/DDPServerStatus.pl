#!/usr/bin/env perl

use strict;
use warnings;
use Data::Dumper;
use Getopt::Long;
our $DEBUG = 0;
use Net::SMTP;
require SendEmail;

sub checkSFTP($) {
    my ($DDPserver) = @_;

    my $result = system("nc -zv $DDPserver 22");

    if ( $result != 0) {
        $result = $result/256;
        return "SFTP Conncection Error: $result<br>";
    }
    return undef;
}

sub checkWebRequest($) {
    my ($DDPserver) = @_;
    my $result = system("curl --silent --fail --insecure --head --output /dev/null https://$DDPserver");
    if ( $result != 0 ) {
        $result = $result/256;
        return "Web Page Connection Error: $result<br>";
    }
    return undef;
}

sub sendMail($) {
    my @mailtxt = @{$_[0]};

    my $to = 'pdlddpserv@pdl.internal.ericsson.com';
    my $subject = "DDP Server Status";
    my $CurrTime = localtime();

    my $message = "There are issues with the below DDP server(s).<br><br>";
    $message .= "<b>Date:</b> $CurrTime <br><br>";

    if ( @mailtxt ) {
        foreach my $txt ( @mailtxt ) {
            $message .= "$txt";
        }
    } else {
        $message .= "<br><br>";
    }

    SendEmail::sendEmail($subject, $message, $to, 'localhost');
}

sub main() {
    my $result = GetOptions(
        "debug=s" => \$DEBUG
    );

    my @mailtxt = ();
    my @DDPServers = ("ddpi", "ddpa", "ddpeo", "ddpenm1", "ddpenm2", "ddp", "ddp2", "ddpenm3", "ddpenm4", "ddpenm5", "ddpenm6", "ddpenm7");

    foreach my $DDPserver (@DDPServers) {
        $DDPserver = $DDPserver . '.athtem.eei.ericsson.se';
        my $sftp = checkSFTP($DDPserver);
        my $web = checkWebRequest($DDPserver);

        if ( defined $sftp || defined $web) {
            push @mailtxt, "<br><b>DDP Server:</b> $DDPserver<br><br>";
            if ( defined $sftp ) {
                push @mailtxt, $sftp;
            }
            if ( defined $web ) {
                push @mailtxt, $web;
            }
        }
    }
    if ( @mailtxt ) {
        sendMail(\@mailtxt);
    }
}

main();

