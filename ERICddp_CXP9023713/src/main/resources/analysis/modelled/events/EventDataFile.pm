package EventDataFile;

use strict;
use warnings;

use JSON;
use PerlIO::gzip;
use Time::HiRes;
use Data::Dumper;

our $FIRST_ES_LOG_FILE_NAME = 'eventdata.log.gz';

#
# Process one eventdata file
#
sub processEventFile($$$$) {
    my ($inFile, $r_eventMap, $r_lastEntries, $r_mappedSrv) = @_;

    my $totalEntries = 0;

    my $totalRead = 0;
    my $totalDecode = 0;
    my $totalProcess = 0;

    if ( $::DEBUG > 8 ) { printf "processEventFile: %10s %10s %6s %6s %6s\n", "Length", "Entries", "Read", "Decode", "Process"; }

    my $isFirstEntry = 1;
    open INPUT, "<:gzip", "$inFile" or die "Cannot open file $inFile";
    my $readStart = Time::HiRes::time;
    my $esVersion = undef;
    while ( my $line = <INPUT> ) {
        my $readEnd = Time::HiRes::time;
        my $readDuration = $readEnd - $readStart;
        $totalRead += $readDuration;

        my $length = length($line);

        my $decodeStart = Time::HiRes::time();
        my $r_result = undef;
        eval {
            $r_result = decode_json($line);
        };
        my $decodeEnd = Time::HiRes::time();
        my $decodeDuration = $decodeEnd-$decodeStart;
        $totalDecode += $decodeEnd - $decodeStart;

        if ( ! defined $r_result ) {
            print "WARN: decode_json failed for line $. in $inFile\n";
            next;
        }

        my $processDuration = 0;
        my $entriesInLine = 0;
        if ( exists $r_result->{'error'} ) {
            print "WARN: Error Message Found \"", $r_result->{'error'}, "\"\n";
        } else {
            if ( ! defined $esVersion) {
                if (exists $r_result->{'hits'}->{'hits'}->[0]->{'fields'}){
                        $esVersion = '21';
                    } else {
                        $esVersion = '56';
                    }
            }
            my $r_entries = $r_result->{'hits'}->{'hits'};

            if ( $isFirstEntry ) {
                $isFirstEntry = 0;
                duplicateCheck($r_entries, $r_lastEntries, $esVersion);
            }

            $entriesInLine = $#{$r_entries} + 1;
            $totalEntries += $entriesInLine;
            my $processStart = Time::HiRes::time();
            processEntries($r_result->{'hits'}->{'hits'}, $r_eventMap, $esVersion, $r_mappedSrv);
            my $processEnd = Time::HiRes::time();
            $processDuration = $processEnd - $processStart;
            $totalProcess += $processDuration;

            if ( $entriesInLine > 0 ) {
                my $index = $entriesInLine - 1;
                my $lastTimestamp = getTimestamp($r_entries->[$index], $esVersion);
                $r_lastEntries = [];
                while ( ($index >= 0) &&
                            getTimestamp($r_entries->[$index], $esVersion) eq $lastTimestamp ) {
                    unshift @{$r_lastEntries}, $r_entries->[$index];
                    $index--;
                }
            }
        }

        if ( $::DEBUG > 8 ) { printf "processEventFile: %10d %10d %6.2f %6.2f %6.2f\n", $length, $entriesInLine, $readDuration, $decodeDuration, $processDuration; }
        $readStart = Time::HiRes::time;
    }
    close INPUT;

    if ( $::DEBUG > 8 ) { printf "processEventFile: read=%.3f decode=%.3f process=%.3f\n",$totalRead,$totalDecode,$totalProcess; }
    return $r_lastEntries;
}

sub processEntries($$$$) {
    my ($r_logEntries, $r_eventMap, $esVersion, $r_mappedSrv) = @_;
    my $r_fields = undef;
    my $host = undef;
    my $program = undef;
    my $message = undef;
    my $timestamp = undef;

    for my $r_logEntry ( @{$r_logEntries} ) {
        if ( $::DEBUG > 9 ) { print Dumper("processEntries: r_logEntry", $r_logEntry); }
        if ($esVersion eq '21') {
            $r_fields = $r_logEntry->{'fields'};
        }
        else {
            $r_fields = $r_logEntry->{'_source'};
        }
        if ( $::DEBUG > 9 ) { print Dumper("processEntries: r_fields", $r_fields); }

        if ($esVersion eq '21') {
            $host = $r_fields->{'host'}->[0];
            $program = $r_fields->{'program'}->[0];
            $message = $r_fields->{'message'}->[0];
            $timestamp = $r_fields->{'timestamp'}->[0];
        }
        else {
            $host = $r_fields->{'host'};
            $program = $r_fields->{'program'};
            $message = $r_fields->{'message'};
            $timestamp = $r_fields->{'timestamp'};
        }
        $message =~ s/^\x{FEFF}//;
        if ( $::DEBUG > 8 ) { print "host=$host program=$program message=$message\n"; }

        my ($eventName,$eventDataStr);
        if ( $program eq 'JBOSS' ) {
            $message =~ s/^\x{FEFF}//;
            ($eventName,$eventDataStr) = $message =~ /^\[com\.ericsson\.oss\.itpf\.EVENT_DATA_LOGGER\] (\S+) (.*)/;
        } elsif ( $program eq 'DDCDATA' ) {
            ($eventName,$eventDataStr) = $message =~ /^\s?(\S+) (.*)/;
        } else {
            next;
        }

        if ( (! defined $eventName) || (! defined $eventDataStr) ) {
            next;
        }

        if ( $::DEBUG > 7 ) { print "processEntries: eventName=$eventName\n"; }

        if ( defined $r_mappedSrv ) {
            my $mappedHost = $r_mappedSrv->{$host};
            if ( defined $mappedHost ) {
                $host = $mappedHost;
            }
        }

        my $r_eventSets = $r_eventMap->{$host}->{$eventName};
        if ( ! defined $r_eventSets ) {
            $r_eventSets = $r_eventMap->{$ParseEventData::ALL_SG}->{$eventName};
        }
        if ( ! defined $r_eventSets ) {
            if ( $::DEBUG > 9 ) { print "processEntries: No mapping found for eventName=$eventName\n"; }
            next;
        }

        my $r_eventData = undef;
        eval {
            $r_eventData = decode_json($eventDataStr);
        };
        if ( defined $r_eventData ) {
            my %event = (
                'timestamp' => $timestamp,
                'name' => $eventName,
                'host' => $host,
                'data' => $r_eventData
            );
            if ( $::DEBUG > 6 ) { print Dumper("processEntries: matched event", \%event); }
            foreach my $r_events ( @{$r_eventSets} ) {
                push @{$r_events}, \%event;
            }
        } else {
            print "WARNING: Invalid event data for $eventName from $host at $timestamp, no events of this type will be processed, event data = '$eventDataStr'\n";

            # Purge any existing data collected and stop processing further exists
            while ( my ($host,$r_eventsByName) = each %{$r_eventMap} ) {
                my $r_existingEventSets = delete $r_eventsByName->{$eventName};
                if ( defined $r_existingEventSets ) {
                    foreach my $r_existingEvents ( @{$r_existingEventSets} ) {
                        $#{$r_existingEvents} = -1;
                    }
                }
            }
        }


    }
}

sub duplicateCheck($$$) {
    my ($r_entries, $r_lastEntries, $esVersion) = @_;

    if ( $::DEBUG > 9 ) { print Dumper("duplicateCheck: r_lastEntries", $r_lastEntries); }

    if ( ! defined $r_lastEntries ) {
        return;
    }

    if ( $#{$r_entries} < $#{$r_lastEntries} ) {
        return;
    }

    foreach my $r_lastEntry ( @{$r_lastEntries} ) {
        my $isSame = 1;
        my $r_lastEntryData = undef;
        my $r_firstEntryData = undef;
        if ($esVersion eq '21') {
            $r_firstEntryData = $r_entries->[0]->{'fields'};
            $r_lastEntryData = $r_lastEntry->{'fields'};
        } else {
            $r_firstEntryData = $r_entries->[0]->{'_source'};
            $r_lastEntryData = $r_lastEntry->{'_source'};
        }

        while ( my ($key,$lastValue) = each %{$r_lastEntryData} ) {
            my $firstValue = $r_firstEntryData->{$key};
            if ( $esVersion eq '21' ) {
                $lastValue = $lastValue->[0];
                $firstValue = $firstValue->[0];
            }
            if ( $firstValue ne $lastValue ) {
                $isSame = 0;
            }
        }
        if ( $::DEBUG > 8 ) { print Dumper("duplicateCheck: checking last entry for duplicate isSame=$isSame", $r_firstEntryData, $r_lastEntryData); }

        if ( $isSame ) {
            shift @{$r_entries};
        }
    }
}

sub getFiles($$) {
    my ($inDir,$r_Incr) = @_;

    my $fileIndex = 0;
    if ( exists $r_Incr->{'fileIndex'} ) {
        $fileIndex = $r_Incr->{'fileIndex'};
    }

    my @fileList = ();
    my $keepGoing = 1;
    while ( $keepGoing ) {
        my $filename = $FIRST_ES_LOG_FILE_NAME;
        if ( $fileIndex > 0 ) {
            $filename .= sprintf(".%04d",$fileIndex);
        }
        my $filePath = $inDir . "/" . $filename;
        if ( -r $filePath ) {
            push @fileList, $filePath;
            $fileIndex++;
        } else {
            $keepGoing = 0;
        }
    }

    $r_Incr->{'fileIndex'} = $fileIndex;

    if ( $::DEBUG > 0 ) { print Dumper("getFiles: fileList",\@fileList); }

    return \@fileList;
}

sub getTimestamp($$) {
    my ($r_entry, $esVersion) = @_;

    if ($esVersion eq '21') {
        return $r_entry->{'fields'}->{'timestamp'}->[0];
    } else {
        return $r_entry->{'_source'}->{'timestamp'};
    }
}

1;
