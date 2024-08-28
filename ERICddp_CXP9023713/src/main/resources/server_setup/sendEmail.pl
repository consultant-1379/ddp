#!/usr/bin/env perl

##=====================================================================
## Script  : sendEmail.pl
## Author  : epatoco
## Purpose : The purpose of this script is to send emails from php pages
## Usage   : perl sendEmail.pl --subject ${SUBJECT} --body ${BODY} --emails ${MAILS}
##=====================================================================

use strict;
use warnings;
use Getopt::Long;
require SendEmail;

sub main() {
    my ($subject, $mailBodyHtml, $emails);
    my $mailhost = 'localhost';
    my $result = GetOptions(
        "subject=s" => \$subject,
        "body=s" => \$mailBodyHtml,
        "emails=s" => \$emails,
        "mailhost=s" => \$mailhost
    );

    SendEmail::sendEmail($subject, $mailBodyHtml, $emails, $mailhost);
}

main();
