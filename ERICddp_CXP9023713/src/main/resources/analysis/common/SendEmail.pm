package SendEmail;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT = qw(sendEmail);

use strict;
use warnings;
use Data::Dumper;
use Net::SMTP;

sub sendEmail($$$$) {
    my ($subject, $mailBodyHtml, $to, $smtpAdd) = @_;
    my $fromAddress = 'ddp-notifications@ericsson.com';

    my $smtp = Net::SMTP->new($smtpAdd) or die "Not Able To Create Connection With SMTP";
    $smtp->mail( $fromAddress );

    my @emailList = split(',', $to);

    foreach my $address (@emailList) {
        $smtp->bcc( $address, { SkipBad => 1 } );
    }

    my $message = "<!DOCTYPE html><html><body>$mailBodyHtml</body></html>";

    my $footer =<<'EOT';
<br>
<b>Disclaimer:</b>
<br><br>
ddp-notifications@ericsson.com is an unattended email. Please do not reply to this email. No confidential and/or personal related data should be forwarded to this email address. For contact, use this email address, PDLDDPSERV@pdl.internal.ericsson.com
EOT

    $smtp->data();
    $smtp->datasend("MIME-Version: 1.0\n");
    $smtp->datasend("Content-Type: text/html; charset=ISO-8859-1\n");

    # Send the header.
    $smtp->datasend("From: $fromAddress\n");
    $smtp->datasend("Subject: $subject\n");

    # Send the body.
    $smtp->datasend( $message );

    # Send the footer
    $smtp->datasend($footer);

    $smtp->dataend();
    $smtp->quit();
}

1;
