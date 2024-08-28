package Counts;

use strict;
use warnings;

use Archive::Zip qw( :ERROR_CODES :CONSTANTS );
use JSON;
use Data::Dumper;

use StatsDB;
use StatsTime;

our $MAX_JSON_ROWS = 1000;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    if ( defined $r_cliArgs->{'analysisDir'} ) {
        $self->{'analysisDir'} = $r_cliArgs->{'analysisDir'};
        $self->{'date'} = $r_cliArgs->{'date'};
        $self->{'siteId'} = $r_cliArgs->{'siteId'};
        $self->{'srvIdMap'} = getIdMap($dbh, "servers", "id", "hostname", [], $self->{'siteId'});

        if ( exists $r_incr->{'Counts'} ) {
            $self->{'messageCounts'} = $r_incr->{'Counts'}->{'messageCounts'};
            $self->{'ratesByHost'} = $r_incr->{'Counts'}->{'ratesByHost'};
        } else {
            $self->{'messageCounts'} = {};
            $self->{'ratesByHost'} = {};
        }

        return undef;
    } else {
        return [];
    }

    return undef;
}

sub handle($$$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize,$r_extra) = @_;

    if ( ! exists $self->{'srvIdMap'}->{$host} ) {
        if ( $::DEBUG > 5 ) { print "Counts::handle unknown host $host\n"; }
        $host = "Unknown";
    }

    my ($hour, $min) = $timestamp =~ /^[\d-]+T(\d+):(\d+):/;
    if ( defined $hour ) {
        $self->{'ratesByHost'}->{($hour*60)+$min}->{$host}++;
    }

    stripMessage(\$message,$messageSize,$r_extra);
    $self->{'messageCounts'}->{$host}->{$program}->{$severity}->{$message}->{"count"}++;

    my ($logTime) = $timestamp =~ /^[\d-]+T(.*)$/;
    if ( not exists $self->{'messageCounts'}->{$host}->{$program}->{$severity}->{$message}->{"firstTime"}) {
        $self->{'messageCounts'}->{$host}->{$program}->{$severity}->{$message}->{"firstTime"} = $logTime;
    }
    $self->{'messageCounts'}->{$host}->{$program}->{$severity}->{$message}->{"lastTime"} = $logTime;
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
    $self->{'messageCounts'}->{$host}->{$program}->{'err'}->{'LOG_LIMIT_EXCEEDED'}->{'count'}++;

}


sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( ! exists $self->{'messageCounts'} ) {
        return;
    }

    $self->writeCountsByHost();
    $self->writePlot();

    $r_incr->{'Counts'} = { 'messageCounts' => $self->{'messageCounts'},
                            'ratesByHost' => $self->{'ratesByHost' } };
}

sub writeCountsByHost($) {
    my ($self) = @_;

    my $zip = Archive::Zip->new();

    my $r_messageCounts = $self->{'messageCounts'};
    my $analysisDir = $self->{'analysisDir'};

    foreach my $host ( keys %{$r_messageCounts} ) {
        my @rows = ();

        if ( $::DEBUG > 4 ) { print "writeCountsByHost: host=$host\n"; }
        if ( $::DEBUG > 5 ) { print "writeCountsByHost: messageCounts",  Dumper($r_messageCounts->{$host}); }
        while ( my ($program,$r_sevHash) = each %{$r_messageCounts->{$host}} ) {
            while ( my ($severity,$r_messHash) = each %{$r_sevHash} ) {
                while ( my ($message,$r_countHash) = each %{$r_messHash} ) {
                    my $count = $r_countHash->{"count"};
                    if ( ! defined $count ) {
                        print Dumper("WARNING: Count undefined for program $program serverity $severity message $message",$r_countHash);
                    }
                    my $firstTime = $r_countHash->{"firstTime"};
                    my $lastTime = $r_countHash->{"lastTime"};
                    push @rows, { 'program' => $program, 'severity' => $severity,
                                  'message' => $message, 'count' => $count,
                                  'firstTime' => $firstTime, 'lastTime' => $lastTime };
                }
            }
        }
        if ( $::DEBUG > 5 ) { print Dumper("rows", \@rows); }

        my @sortedRows = sort { $b->{'count'}  <=> $a->{'count'} } @rows;

        if ( $#sortedRows >= $MAX_JSON_ROWS ) {
            my $droppedRows = 0;
            my $droppedCount = 0;

            while ( $#sortedRows >= ($MAX_JSON_ROWS-1) ) {
                my $r_droppedRow = pop @sortedRows;
                $droppedRows++;
                $droppedCount += $r_droppedRow->{'count'};
            }
            my $dropMsg = "MAX_ROWS_EXCEEDED: Dropped $droppedRows rows with a total of $droppedCount entries";
            unshift @sortedRows, { 'program' => 'NA', 'severity' => 'err',
                                   'message' => $dropMsg, 'count' => 1,
                                   'firstTime' => '', 'lastTime' => '' };
        }

        if ( $::DEBUG > 4 ) { print "writeCountsByHost: encoding ", ($#sortedRows + 1), " rows for $host\n"; }
        my @encodedRows = ();
        foreach my $r_row ( @sortedRows ) {
            push @encodedRows, encode_json($r_row);
        }

        if ( $::DEBUG > 4 ) { print "writeCountsByHost: storing in zip\n"; }
        $zip->addString(join("\n",@encodedRows), $host . ".json" )->desiredCompressionMethod( COMPRESSION_DEFLATED );
    }

    if ( $::DEBUG > 4 ) { print "writeCountsByHost: writing zip file\n"; }
    $zip->writeToFileNamed($analysisDir . "/hosts.zip");
}

sub writePlot($) {
    my ($self) = @_;

    my $plotFile = $self->{'analysisDir'} . "/plot.json";
    my $r_ratesByHost = $self->{'ratesByHost'};
    my $date = $self->{'date'};

    if ( $::DEBUG > 7 ) { print Dumper("writePlot: r_ratesByHost", $r_ratesByHost); }

    my %totalByHost = ();
    foreach my $time ( keys %{$r_ratesByHost} ) {
        foreach my $host ( keys %{$r_ratesByHost->{$time}} ) {
            $totalByHost{$host} += $r_ratesByHost->{$time}->{$host};
        }
    }
    if ( $::DEBUG > 5 ) { print Dumper("writePlot: totalByHost", \%totalByHost); }

    my @hostList = sort { $totalByHost{$b} <=> $totalByHost{$a} } keys %totalByHost;
    if ( $::DEBUG > 5 ) { print Dumper("writePlot: hostList", \@hostList); }

    if ( $#hostList > 7 ) {
        splice(@hostList, 7);
    }
    if ( $::DEBUG > 5 ) { print Dumper("writePlot: truncated hostList", \@hostList); }

    my %seriesByName = ();
    my %hostLookup = ();
    foreach my $host ( @hostList ) {
        $seriesByName{$host} = { 'name' => $host, 'data' => [] };
        $hostLookup{$host} = 1;
    }
    $seriesByName{'Other'} = { 'name' => 'Other', 'data' => [] };

    foreach my $time ( sort { $a <=> $b } keys %{$r_ratesByHost} ) {
        my $timestamp = parseTime(sprintf("%s %02d:%02d:00", $date, $time / 60, $time % 60),
                                  $StatsTime::TIME_SQL) * 1000;

        my $other = 0;
        foreach my $host ( keys %{$r_ratesByHost->{$time}} ) {
            if ( defined $r_ratesByHost->{$time}->{$host} ) {
                if ( exists $hostLookup{$host} ) {
                    push @{$seriesByName{$host}->{'data'}}, [ $timestamp, $r_ratesByHost->{$time}->{$host} ];
                }
                else {
                    $other += $r_ratesByHost->{$time}->{$host};
                }
            }
        }

        if ( $other > 0 ) {
            push @{$seriesByName{'Other'}->{'data'}}, [ $timestamp, $other ];
        }
    }

    open PLOT, ">$plotFile" or die "Cannot write to $plotFile";
    print PLOT "[\n";
    print PLOT encode_json($seriesByName{'Other'});
    foreach my $host ( @hostList ) {
        print PLOT ",\n", encode_json($seriesByName{$host});
    }
    print PLOT "\n]\n";
    close PLOT;
}

sub stripString($) {
    my ($r_strRef) = @_;

    $$r_strRef =~ s/TaskId \S+/[TASKID]/;
    $$r_strRef =~ s/taskId: \S+/[TASKID]/g;

    # UUID
    #https://en.wikipedia.org/wiki/Universally_unique_identifier
    $$r_strRef =~ s/ BasicAction: \S+/ BasicAction [ID]/g;
    # UUID
    # 123e4567-e89b-12d3-a456-426655440000
    $$r_strRef =~ s/[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}/[UUID]/;

    $$r_strRef =~ s/Xid to recover: [\d\-\.]+/[XID_TO_RECOVER]/;
    $$r_strRef =~ s/Transaction key: [\d\-\.:a-f]+/[TRANSACTION_KEY]/;
    $$r_strRef =~ s/for TX [\d\-\.:a-f]+/for [TX_ID]/;
    $$r_strRef =~ s/successfully canceled TX [\d\-\.:a-f]+/successfully canceled [TX_ID]/;
    $$r_strRef =~ s/_uid=[\da-f-:]+/[UID]/g;
    $$r_strRef =~ s/t of action [\da-f:-]+/t of action [ID]/;
    $$r_strRef =~ s/t of action id [\da-f:-]+/t of action id [ID]/;

    # PM Files
    $$r_strRef =~ s/A2\d{3,3}\S+/[FILE]/g;

    $$r_strRef =~ s/jobId=\S+/[JOBID]/g;

    $$r_strRef =~ s/NetworkElement=[,A-Za-z0-9_=-]+/[NETWORKELEMENT]/g;
    $$r_strRef =~ s/SubNetwork=[,A-Za-z0-9_=-]+/[FDN]/g;

    $$r_strRef =~ s/@[\da-f]+/:[OBJREF]/g;
    $$r_strRef =~ s/value=[\da-f-]+/value=[VALUE]/;
    $$r_strRef =~ s/action-id [0-9a-z:-]+/[ACTION-ID]/;

    #This is used to convert SrcIp address to decrease the similar logs
    $$r_strRef =~ s/srcIpAddr: \/\S+/[SRC_IP_ADDR]/;

    # This is at "catch-all" and must be left as the last check
    $$r_strRef =~ s/\d+/[NUM]/g;
}

sub processStack($) {
    my ($stackStr) = @_;

    $stackStr =~ s/#012/\n/g;
    $stackStr =~ s/#011/\t/g;

    my @inStack = split(/\n/,$stackStr);
    shift @inStack;
    my @stack = ();
    if ( $::DEBUG > 6 ) { print Dumper("processStack: inStack",\@inStack); }
    foreach my $stackLine ( @inStack ) {
        if ( $stackLine =~ /^\tat/ ) {
            if ( $stackLine !~ /org.jboss.invocation.|org.jboss.as|org.jboss.weld|sun.reflect|java.lang.reflect/ ) {
                push @stack, $stackLine;
            }
        } else {
            stripString(\$stackLine);
            push @stack, $stackLine;
        }
    }
    if ( $::DEBUG > 6 ) { print Dumper("processStack: stack",\@stack); }

    return \@stack;
}

sub stripMessage($$$) {
    my ($r_message,$messageSize,$r_extra) = @_;

    # Note $r_message is a ref to a string
    # to update you need to use $$r_message

    my $r_stack = undef;
    if ( defined $r_extra ) {
        if ( exists $r_extra->{'logger'} ) {
            $$r_message .= ", logger: " . $r_extra->{'logger'};
        }
        if ( exists $r_extra->{'thread_info'} ) {
            $$r_message .= ", thread_name: " . $r_extra->{'thread_info'}->{'thread_name'};
        }
        if ( $::DEBUG > 6 ) { printf("stripMessage: message=%s\n", $$r_message); }
        if ( exists $r_extra->{'exception'} ) {
            $r_stack = $r_extra->{'exception'}->{'stack_trace'};
        }
    } else {
        # If it's a large message, odds are it's an exception
        if ( $messageSize > 5000 ) {
            my $stackStart = index($$r_message,"#012#011at");
            if ( $stackStart > -1 ) {
                my $stackStr = substr($$r_message,$stackStart);
                $$r_message = substr($$r_message,0,$stackStart);
                if ( $::DEBUG > 8 ) { print "processEntries: stackStr=$stackStr\n"; }
                $r_stack = processStack($stackStr);
                if ( defined $r_stack ) {
                    $r_stack = join("\n",@{$r_stack});
                }
            } else {
                # It's a very large message that's not a stack dump. We need to tuncate these as
                # running stripMessage on anything this large takes forever
                $$r_message = substr( $$r_message, 0, 5000 ) . " [TRUNCATED]";
            }
        }
    }

    stripString($r_message);

    if ( defined $r_stack ) {
        $$r_message = $$r_message . "STACKTRACE_TAG" . $r_stack;
    }

}

1;
