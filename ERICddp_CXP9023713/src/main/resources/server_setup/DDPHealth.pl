#!/usr/bin/env perl

#=====================================================================
## Script  : DDPHealth.pl
## Purpose : The purpose of this script is to send mail when
##           the health checks have not run recently
## Usage   : perl DDPHealth.pl  --server "$server"
##=====================================================================

use strict;
use warnings;
use lib '/data/ddp/current/analysis/common';
require SendEmail;
use Data::Dumper;
use StatsDB;
use Getopt::Long;
use DBI;
our $DEBUG = 0;
use POSIX qw(strftime);

sub main() {
    my $server;
    my $result = GetOptions(
        "server=s" => \$server,
        "debug=s" => \$DEBUG
    );
    setStatsDB_Debug($DEBUG);

    my $mailtxt;
    my $mailRecipients = 'pdlddpserv@pdl.internal.ericsson.com';
    my $dbh = connect_db() or die "Couldn't execute statement:";

    $mailtxt = checkDDDPHCs( $dbh, $mailtxt, $server );

    if ( defined $mailtxt ) {
        sendMail($mailtxt, $server, $mailRecipients);
    }
    $dbh->disconnect() or die "Can't disconnect";
}

main();

sub sendMail($$$$) {
    my ($mailtxt, $server, $to) =  @_;

    my $subject = "$server Health Status Mail";
    my $CurrTime = localtime();

    my $message = "Please find the DDP Service Health Status below.<br><br>";
    $message .= "Instance: $server<br>";
    $message .= "Date: $CurrTime<br>";

    if ( defined $mailtxt ) {
        $message .= "<br>$mailtxt";
    } else {
        $message .= "<br><br>";
    }

    my $serverLC = lc $server;
    $message .= "<br><br>DDP Status: https://$serverLC.athtem.eei.ericsson.se/adminui/service.php<br>";

    SendEmail::sendEmail($subject, $message, $to, 'localhost');
}

sub checkDDDPHCs($$$) {
    my ($dbh, $mailtxt, $server) = @_;

    my $serverLC = lc $server;

    my $query = <<"END_SQL";
SELECT
    TIME_TO_SEC(TIMEDIFF(NOW(), MAX(generatedAt))) AS diff
FROM
    ddpadmin.healthcheck_results JOIN sites ON ddpadmin.healthcheck_results.siteid = sites.id
WHERE
    sites.name LIKE '%LMI_ddp%' AND
    ddpadmin.healthcheck_results.date >= DATE_SUB(DATE(NOW()), INTERVAL 2 DAY);
END_SQL

    my $sth = $dbh->prepare( $query )
        or die "prepare statement failed: $dbh->errstr()";
    $sth->execute() or die "execution failed: $dbh->errstr()";

    my @row = $sth->fetchrow_array;
    my $secSinceLast = $row[0];
    my $currHour = (localtime)[2];
    my $date = strftime("%F", localtime);
    my $dir = substr($date, 8, 2) . substr($date, 5, 2) . substr($date, 2, 2);

    if ( $currHour <= 22 && $currHour > 5 ) {
        if (! defined ( $secSinceLast)) {
            $mailtxt .= "Error: No Rows Returned<br>";
        } elsif ( $secSinceLast > 7200 ) {
            my $hrs = ($secSinceLast/60/60);
            $hrs = sprintf "%.0f", $hrs;
            $mailtxt .= "It has been $hrs hours since the last DDP Health Status update.<br>";
            $mailtxt .= "https://$serverLC.athtem.eei.ericsson.se/php/common/hc.php?site=LMI_$serverLC&dir=$dir&date=$date&oss=ddp<br>";
        }
    }

    return $mailtxt;
}

