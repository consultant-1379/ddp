package CSV;

use strict;
use warnings;
use StatsDB;
use StatsTime;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}


sub init($$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    if ( defined $r_cliArgs->{'analysisDir'} ) {
        $self->{'analysisDir'} = $r_cliArgs->{'analysisDir'};
        $self->{'isIncremental'} = $r_incr->{'isIncremental'};
        $self->{'isLogComplete'} = 1;
        $self->{'siteId'} = $r_cliArgs->{'siteId'};
        $self->{'date'} = $r_cliArgs->{'date'};

        my $csvFh = undef;
        $self->{'csvTmpFileName'} = $r_cliArgs->{'analysisDir'} . "/.csv.gz";
        open $csvFh, ">:gzip:encoding(UTF-8)", $self->{'csvTmpFileName'} or die "Cannot open $self->{'csvTmpFileName'}: $!";
        $self->{'csvFh'} = $csvFh;
        return undef;
    } else {
        return [];
    }
}

sub handle($$$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize,$r_extra) = @_;

    if ( defined $r_extra ) {
        if ( exists $r_extra->{'logger'} ) {
            $message .= ", logger: " . $r_extra->{'logger'};
        }
        if ( exists $r_extra->{'thread_info'} ) {
            $message .= ", thread_name: " . $r_extra->{'thread_info'}->{'thread_name'};
        }
        if ( exists $r_extra->{'exception'} ) {
            $message .= ", stack_trace: " . $r_extra->{'exception'}->{'stack_trace'};
            $message =~ s/\R/@/g;
            $message =~ s/\t//g;
        }
    }
    printf {$self->{'csvFh'}} "%s@%s@%s@%s\n",$timestamp,$host,$program,$message;
    $self->{'csvLastTime'} = $timestamp;
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
    $self->{'isLogComplete'} = 0;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( ! exists $self->{'csvFh'} ) {
        return;
    }

    close $self->{'csvFh'};

    my $analysisDir = $self->{'analysisDir'};
    my $csvIndex = 1;
    # If we're not doing incremental processing, then delete any existing
    # CSV files
    if ( $self->{'isIncremental'} == 0 ) {
        opendir DIR, $analysisDir;
        while ( my $fileName = readdir(DIR) ) {
            if ( $fileName =~ /^\d{2,2}_\S+.csv.gz/ ) {
                unlink($analysisDir . "/" . $fileName);
            }
        }
    } else {
        my $keepGoing = 1;
        while ( $keepGoing ) {
            my $csvIndexStr = sprintf("%02d",$csvIndex);
            my $completeFile = $analysisDir . "/" . $csvIndexStr . "_complete.csv.gz";
            my $partialFile = $analysisDir . "/" . $csvIndexStr . "_partial.csv.gz";
            if ( (! -r $completeFile) && (! -r $partialFile) ) {
                $keepGoing = 0;
            } else {
                $csvIndex++;
            }
        }
    }

    my $csvFile = sprintf("%02d",$csvIndex);
    if ( $self->{'isLogComplete'} ) {
        $csvFile .= "_complete.csv.gz";
    } else {
        $csvFile .= "_partial.csv.gz";
    }
    rename($self->{'csvTmpFileName'}, $analysisDir . "/" . $csvFile);

    # Touch the CSV file with the timestamp of the last logline
    my $lastTime = $self->{'date'} . " 00:00:00";
    if ( $self->{'csvLastTime'} =~ /^(\d{4}-\d{2}-\d{2})[ T]+(\d{2}:\d{2}:\d{2})/ ) {
        $lastTime = $1 . ' ' . $2;
        my $lastTimeEpoch = parseTime($lastTime, $StatsTime::TIME_YYYYMD_HMS);
        utime($lastTimeEpoch, $lastTimeEpoch, $analysisDir . "/" . $csvFile);
    }

    my @fileStat = stat($analysisDir . "/" . $csvFile);
    my $csvSize = int($fileStat[7] / (1024*1024));

    if ( $self->{'isIncremental'} == 0 ) {
        dbDo($dbh, sprintf("DELETE FROM enm_elasticsearch_logs WHERE siteid=%d AND date='%s'",$self->{'siteId'}, $self->{'date'}))
            or die "Failed to delete from enm_elasticsearch_logs";
    }
    dbDo($dbh, sprintf("INSERT INTO enm_elasticsearch_logs (siteid,date,log_name,log_end_time,log_size) VALUES (%d,'%s','%s','%s', %d)",
                       $self->{'siteId'}, $self->{'date'},
                       $csvFile,
                       $lastTime, $csvSize)
        )
        or die "Failed to insert into enm_elasticsearch_logs";
}

1;
