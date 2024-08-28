package alarmLog;

use strict;
use warnings;
use Data::Dumper;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT = qw(setAlarmLog_Debug processEvents readLog REC_TYPE);

our %REC_TYPE =
    (
     1 => 'Alarm',     
     2 => 'Error message',
     3 => 'Non_synchable Alarm',
     4 => 'Repeated Alarm',
     5 => 'Synchronization Alarm',
     6 => 'Heartbeat Alarm',
     7 => 'Synchronization Started',
     8 => 'Synchronization Ended',
     9 => 'Synchronization Aborted',
     10 => 'Synchronization Ignored',
     12 => 'Adaptation Restart',
     13 => 'Adaptation Shutdown',
     14 => 'Clear List',
     15 => 'FMX Show',
     16 => 'FMX Hide',
     17 => 'FMX Show Acknowledged',
     18 => 'Repeated Error Message',
     19 => 'Repeated Non_Synchable Alarm',
     20 => 'Update '
    );

our $alarmLog_DEBUG = 0;


sub setAlarmLog_Debug($) {
    my ($newDebug) = @_;
    $alarmLog_DEBUG = $newDebug;
}

sub processEvents($) {
    my ($r_events) = @_;

    my %activeSyncs = ();
    my $activeCount = 0;
    my @syncs = ();
    foreach my $r_event ( @{$r_events} ) {
	my $node = $r_event->{'node'};
	my $recType = $r_event->{'rec_type'};

	if ( $alarmLog_DEBUG > 8 ) { print Dumper("processEvents: event", $r_event); }

	if ( $recType == 7 ) { # Sync Start
	    my $r_onGoingSync = delete $activeSyncs{$node};
	    if ( defined $r_onGoingSync ) {
		$activeCount--;
		my $r_sync = {
		    'node'      => $node,
		    'start'     => $r_onGoingSync->{'event_time'},
		    'end'       => $r_event->{'event_time'},
		    'result'    => 0,
		    'reason'    => 'Another sync started'
		};
		push @syncs, $r_sync;
	    }
	    $activeSyncs{$node} = $r_event;
	    $activeCount++;
	} elsif ( $recType == 8 ) { # Sync End
	    my $r_onGoingSync = delete $activeSyncs{$node};
	    if ( ! defined $r_onGoingSync ) {
		print "WARN: Missing sync start for $node\n";
		print "       Completed at ", $r_event->{'event_time'}, "\n";
	    } else {
		$activeCount--;
		my ( $rec, $con, $clr ) = $r_event->{'prob_data'} =~ 
		    /^(\d+) Alarms received during synchronization (\d+) Constructed alarms during synchronization (\d+) Clearings constructed during synchronization/;
		my $r_sync = {
		    'node'      => $node,
		    'start'     => $r_onGoingSync->{'event_time'},
		    'end'       => $r_event->{'event_time'},
		    'result'    => 1,
		    'alarm_rec' => $rec,
		    'alarm_con' => $con,
		    'alarm_clr' => $clr
		};
					   
		push @syncs, $r_sync;
	    }
	} elsif ( $recType == 6 ) { # Heartbeat
	    if ( $r_event->{'prec_sev'} == 1 ) { # 1 on a raise, 5 on a clear
		my $r_onGoingSync = delete $activeSyncs{$node};
		if ( defined $r_onGoingSync ) {
		    $activeCount--;
		    my $r_sync = {
			'node'      => $node,
			'start'     => $r_onGoingSync->{'event_time'},
			'end'       => $r_event->{'event_time'},
			'result'    => 0,
			'reason'    => 'HBF_Rasied'
		    };
		    push @syncs, $r_sync;
		}	    
	    }
	} elsif ( $recType == 2 ) { # Error
	    #This alarm is created by the FM Kernel to end the ongoing synchronization that has timed out for this node. (Synchronization timeout is 3600 sec)
	    if ( $r_event->{'prob_text'} =~ /synchronization that has timed/ ) {
		my $r_onGoingSync = delete $activeSyncs{$node};
		if ( defined $r_onGoingSync ) {
		    $activeCount--;
		    my $r_sync = {
			'node'      => $node,
			'start'     => $r_onGoingSync->{'event_time'},
			'end'       => $r_event->{'event_time'},
			'result'    => 0,
			'reason'    => $r_event->{'prob_text'}
		    };
		    push @syncs, $r_sync;
		}
	    }
	}		 
    }
    my @activeNodes = keys %activeSyncs;
    if ( $alarmLog_DEBUG > 0 ) { print scalar localtime(time), " activeCount=$activeCount #activeNodes=" . $#activeNodes . "\n";; }

    if ( $alarmLog_DEBUG > 6 ) { print Dumper("processEvents: syncs", \@syncs ); }
    
    return \@syncs;
}



sub readLine() {
    my $buff = "";
    my $line;
    while ( defined($line = <LOG>) && ($line !~ /REC_END;$/ ) ) {
	chop $line;
	$buff .= $line;
    }

    my $result = $buff;
    if ( defined $line ) {
	$result .= $line;
    }

    if ( $alarmLog_DEBUG > 9 ) { print "readLine: result = $result"; }

    return $result;
}
	
    
sub readLog($) {
    my ($logFile) = @_;

    my @events = ();
    open LOG, $logFile or die "Cannot open alarm log";

    while ( my $line = readLine() ) {
	my @fields = split( /;/, $line );
	shift @fields;
	pop @fields;

	if ( $alarmLog_DEBUG > 7 ) { print Dumper("readLog: fields", \@fields); }

	my $r_event = {
	    'insert_time' => $fields[0],
	    'event_time'  => $fields[1],
	    'node'        => $fields[2],
	    'rec_type'    => $fields[3],
	    'prec_sev'    => $fields[4],
	    'prob_cause'  => $fields[5],
	    'spec_prob'   => $fields[6],
	    'alarm_id'    => $fields[7],
	    'rec_id'      => $fields[8],
	    'prob_data'   => $fields[9],
	    'prob_text'   => $fields[10]		
	};
	push @events, $r_event;
    }
    close LOG;

    return \@events;
}

1;
