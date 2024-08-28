#!/usr/bin/env perl

use Getopt::Long;
use Time::Local;
use strict;
use Data::Dumper;
use StatsDB;
use DBI;

our $DEBUG = 0;
our $site;
our $date;

main();

sub main() {
	my ($logFile);
	my $result = GetOptions(
		"logfile=s" => \$logFile,
		"debug=s" => \$DEBUG,
		"site=s" =>\$site,
		"date=s" => \$date,
	);
	($result == 1) or die "Invalid arguments";

	processLogFile($logFile);
}

sub processLogFile {
	my $logFile = shift;
	if ($DEBUG > 5) { print "Processing ", $logFile, "\n"; }
	open LF, $logFile or die "Cannot open $logFile : $!";
	my @userEvents = ();
	my @statusEvents = ();
	my $line;
	while ($line = <LF>) {
		my ($y, $m, $d, $h, $M, $s);
		# Only look for halog-specific lines with a timestamp
		if ($line =~ /^([1-2][0-9][0-9][0-9])\/([0-1][0-9])\/([0-3][0-9]) ([0-2][0-9]):([0-5][0-9]):([0-5][0-9]) VCS/) {
			$y = $1 ; $m = $2 ; $d = $3 ; $h = $4 ; $M = $5 ; $s = $6;

			# Match user-initialised commands
			if ($line =~ /User (\w+) fired command: (\w+) -(\w+) (\w+)  (\w+)/) {
				my $user = $1; my $restype = $2; my $cmd = $3; my $resource = $4; my $host = $5;
				if ($DEBUG > 5) {
					print "$y/$m/$d $h:$M:$s: Matched event ", $3, " (", $2, ") for resource ", $4, " by user ", $user, "\n";
				}
				if ($restype == "hagrp") { $restype = "G"; }
				elsif ($restype = "hares") { $restype = "R"; }
				push @userEvents, {
					time => "$y-$m-$d $h:$M:$s",
					resource => $resource,
					restype => $restype,
					cmd => $cmd,
					user => $user,
					host => $host,
				};
			}

			# Match group status events
			elsif ($line =~ /Group (\w+) is (\w+) on system (\w+)/) {
				my $resource = $1; my $status = $2; my $host = $3;
				if ($DEBUG > 5) {
					print "$y/$m/$d $h:$M:$s: Matched status event ", $status,
					" for group resource ", $resource, " on host ", $host, "\n";
				}
				push @statusEvents, {
					time => "$y-$m-$d $h:$M:$s",
					resource => $resource,
					restype => "G",
					status => $status,
					reason => "",
					owner => "",
					gr => $resource,
					host => $host,
				};
			}
			# Match resource status events
			elsif ($line =~ /Resource (\w+) \(Owner: (\w+), Group: (\w+)\) is (\w+) \((.*)\) on sys (\w+)/) {
				my $resource = $1; my $owner = $2 ; my $group = $3 ; my $status = $4 ; my $reason = $5 ; my $host = $6 ;
				if ($DEBUG > 5) {
					print "$y/$m/$d $h:$M:$s: Matched status event ", $status,
					" for resource ", $resource, " (group ", $group, ") ",
						" reason \"", $reason, "\" on host ", $host, "\n";
				}
				push @statusEvents, {
					time => "$y-$m-$d $h:$M:$s",
					resource => $resource,
					restype => "R",
					status => $status,
					reason => $reason,
					owner => $owner,
					grp => $group,
					host => $host,
				};
			}
		}
	}

	if ($DEBUG > 8) {
		print Dumper("User commands: ", @userEvents);
		print Dumper("Status Events: ", @statusEvents);
	}
	storeResults(\@userEvents, \@statusEvents);
}

sub storeResults() {
	my ($userEvents, $statusEvents) = @_;
	if ( $DEBUG > 0 ) { setStatsDB_Debug($DEBUG); }
	my $dbh = connect_db();
	my $siteId = getSiteId($dbh,$site);
	if ( $siteId == -1 ) {
		print "ERROR: Could not find siteid for $site\n";
		return;
	}
	if ($DEBUG > 5) { print "SITE ID : ", $siteId, "\n"; }
	# Store names of various items
	my (@resources, @groups, @status, @cmdnames, @users, @hosts);
	foreach (@$userEvents) {
		push(@resources, @$_{'resource'});
		push(@cmdnames, @$_{'cmd'});
		push(@users, @$_{'user'});
		push(@hosts, @$_{'host'});
	}
	foreach (@$statusEvents) {
		push(@resources, @$_{'resource'});
		push(@status, @$_{'status'});
		push(@users, @$_{'owner'});
		push(@groups, @$_{'grp'});
		push(@hosts, @$_{'host'});
	}
	# TODO: Sort restype == G resources and store them as groups (?)
	my $resourceMap = getIdMap($dbh, "halog_resources", "id", "name", \@resources);
	my $groupMap = getIdMap($dbh, "halog_groups", "id", "name", \@groups);
	my $statusMap = getIdMap($dbh, "halog_status", "id", "name", \@status);
	my $cmdMap = getIdMap($dbh, "halog_cmdnames", "id", "name", \@cmdnames);
	my $userMap = getIdMap($dbh, "oss_users", "id", "name", \@users);
	my $hostMap = getIdMap($dbh, "servers", "id", "hostname", \@hosts);

	if ($DEBUG > 7) {
		print Dumper("RESOURCENAMES: ", $resourceMap);
	}

	$dbh->do("DELETE FROM halog_events WHERE siteid = $siteId AND time BETWEEN \'" . $date . " 00:00:00\' AND \'" . $date . " 23:59:59\'");
	$dbh->do("DELETE FROM halog_cmds WHERE siteid = $siteId AND time BETWEEN \'" . $date . " 00:00:00\' AND \'" . $date . " 23:59:59\'");
	foreach (@$userEvents) {
		my $event = $_;
		if ($DEBUG > 5) { print "Storing user command ", @$event{'cmd'}, " for ",
			@$event{'resource'}, " ; ", @$event{'time'}, "\n"; }

		my $sql = sprintf("INSERT INTO halog_cmds (siteid,resource,restype,cmd,user,time,host) " .
			"VALUES(%d,%d,\'%s\',%d,%d,\'%s\',%d)",
			$siteId,
			$resourceMap->{@$event{'resource'}},
			@$event{'restype'},
			$cmdMap->{@$event{'cmd'}},
			$userMap->{@$event{'user'}},
			@$event{'time'},
			$hostMap->{@$event{'host'}}
		); 
		if ($DEBUG > 5) { print "SQL: " . $sql . "\n"; }
		$dbh->do($sql) or die "Failed to insert event " . @$event{'event'} . ": " . $dbh->errstr;
	}
	foreach (@$statusEvents) {
		my $event = $_;
		if ($DEBUG > 5) {
			print "Storing status event " .
			@$event{'status'} . " for " . @$event{'resource'} . " ; " .
			@$event{'time'} . "\n";
		}
		my $sql = sprintf("INSERT INTO halog_events (siteid,resource,restype,status,reason,owner,grp,time,host) " .
			"VALUES(%d,%d,\'%s\',%d,\'%s\',%d,%d,\'%s\',%d)",
			$siteId,
			$resourceMap->{@$event{'resource'}},
			@$event{'restype'},
			$statusMap->{@$event{'status'}},
			@$event{'reason'},
			$userMap->{@$event{'user'}},
			$groupMap->{@$event{'grp'}},
			@$event{'time'},
			$hostMap->{@$event{'host'}}
		);
		if ($DEBUG > 5) { print "SQL: " . $sql . "\n"; }
		$dbh->do($sql) or die "Failed to insert event " . @$event{'event'} . ": " . $dbh->errstr;
	}
}

