package PmServ;

use strict;
use warnings;

use Data::Dumper;
use JSON;

use StatsDB;
use StatsTime;
use EnmServiceGroup;

sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}


sub init($$$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    if ( exists $r_incr->{'PmServ'} ) {
        $self->{'nodesWithErrors'} = $r_incr->{'PmServ'}->{'nodesWithErrors'};
        $self->{'errorMsgTypes'} = $r_incr->{'PmServ'}->{'errorMsgTypes'};
    } else {
        $self->{'nodesWithErrors'} = {};
        $self->{'errorMsgTypes'} = {};
    }

    $self->{'fileCollectionStats'} = {};
    $self->{'discardCount'} = 0;

    $self->{'srvIdMap'} = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},'pmservice');
    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }
    if ( $::DEBUG > 5 ) { print Dumper("PmServ::init subscriptions",\@subscriptions); }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self, $timestamp, $host, $program, $severity, $message, $messageSize) = @_;

    if ( $::DEBUG > 7 ) { print "PmServ::handle got message from $host $program \"$message\"\n"; }

    if ( $severity eq 'info' ) {
        if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\]\s+\(.*\)\s+\[NO USER DATA, PMIC_FILE_COLLECTION_STATISTICS, DETAILED, PMIC.INPUT_EVENT_RECEIVED, COMPONENT EVENT, (.*)\]$/ ) {
            my $statsStr = $1;
            parseCollectionStats($self,$host,$statsStr);
        }
    } elsif ( $severity eq 'err' ) {
        if ( $message =~ /^ERROR\s+\[com\.ericsson\.oss\.itpf\.ERROR_LOGGER\]\s+\(.*\)\s+\[NO USER DATA, PMIC.FILE_COLLECTION_FAILURE, ERROR, PMIC.FILE_COLLECTION_RESULT, (.*)/ ) {
            my $errInfo = $1;
            parseCollectionError($self,$errInfo);
        }
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    store($self->{'nodesWithErrors'},
          $self->{'errorMsgTypes'},
          $self->{'date'},
          $self->{'site'},
          $self->{'fileCollectionStats'},
          $self->{'srvIdMap'},
          $dbh);

    $r_incr->{'PmServ'} = {
        'nodesWithErrors' => $self->{'nodesWithErrors'},
        'errorMsgTypes' => $self->{'errorMsgTypes'}
    };
}

sub mergeSamples($$) {
    my ($r_base,$r_add) = @_;
    print "INFO: Merging samples for $r_base->{'fcs'}\n";

    foreach my $key ( 'numberOfFilesCollected', 'numberOfFilesFailed', 'mb_txfr', 'mb_stor' ) {
        $r_base->{$key} += $r_add->{$key};
    }
    $r_base->{'duration'} = ($r_add->{'ropEndTime'} - ($r_base->{'fcs'} * 1000)) / 1000;
    if ( $::DEBUG > 5 ) {
        print Dumper('mergeSamples: merged base', $r_base);
    }
}

#
# Internal functions
#
sub parseCollectionError($$) {
    my ($self,$errInfo) = @_;

    my ($node) = $errInfo =~ /(NetworkElement=[^,]+)/;
    my ($errType) = $errInfo =~ /Error message:\s*(.*)\.?\]/;
    if ($::DEBUG > 5) { print "parseCollectionError: node=$node errType=$errType errInfo=$errInfo\n"; }

    $self->{'nodesWithErrors'}->{$node}++;
    $self->{'errorMsgTypes'}->{$errType}++;
}

sub parseCollectionStats($$) {
    my ($self,$host,$statsStr) = @_;

    my @statsNV = split(/,/,$statsStr);
    if ($::DEBUG > 5) { print Dumper("parseCollectionStats: statsNV", \@statsNV); }
    my %stats = ();
    foreach my $nameValue ( @statsNV ) {
        my ($name,$value) = split(/=/,$nameValue);
        $stats{$name} = $value;
    }

    if ( $stats{'ropStartTime'} == 0 ) {
        $self->{'discardCount'}++;
        return;
    }

    my $r_pmservStats = $self->{'fileCollectionStats'}->{$host};
    if ( ! defined $r_pmservStats ) {
        $r_pmservStats = {};
        $self->{'fileCollectionStats'}->{$host} = $r_pmservStats;
    }
    my $r_ropStats = $r_pmservStats->{$stats{'ropPeriodInMinutes'}};
    if ( ! defined $r_ropStats ) {
        $r_ropStats = [];
        $r_pmservStats->{$stats{'ropPeriodInMinutes'}} = $r_ropStats;
    }
    if ( $::DEBUG > 5 ) { print Dumper('parseCollectionStats: stats', \%stats); }
    push @{$r_ropStats}, \%stats;
}

sub store($$$$$$$) {
    my ($nodesWithErrors, $errorMsgTypes, $date, $site, $r_fileCollectionStats, $r_serverMap,$dbh) = @_;

    my $siteId = getSiteId($dbh, $site);
    ( $siteId != -1 ) or die "Failed to get siteid for $site";

    my $tmpDir = '/data/tmp';
    if (exists $ENV{'TMP_DIR'}) {
        $tmpDir = $ENV{'TMP_DIR'};
    }

    storeErrors($nodesWithErrors, $errorMsgTypes, $date, $dbh, $siteId, $tmpDir);

    if ( defined $r_fileCollectionStats ) {
        storeStats($r_fileCollectionStats, $r_serverMap,$dbh,$siteId,$tmpDir);
    }
}


sub storeErrors($$$$$$) {
    my ($nodesWithErrors, $errorMsgTypes, $date, $dbh, $siteId, $tmpDir) = @_;

    my $bcpFileNodes = "$tmpDir/pm_error_nodes.bcp";
    open (BCPNODES, "> $bcpFileNodes") or die "Failed to open $bcpFileNodes";
    foreach my $node (sort {$nodesWithErrors->{$b} <=> $nodesWithErrors->{$a}} keys %{$nodesWithErrors}) {
        print BCPNODES "$date\t$siteId\t$node\t$nodesWithErrors->{$node}\n";
    }
    close BCPNODES;

    dbDo( $dbh, "DELETE FROM pm_error_nodes WHERE siteid = $siteId AND date = '$date'" )
        or die "Failed to delete from pm_error_nodes";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileNodes' INTO TABLE pm_error_nodes" )
        or die "Failed to load new data from '$bcpFileNodes' file to 'pm_error_nodes' table";

    my $bcpFileErrors = "$tmpDir/pm_errors.bcp";
    open (BCPERRORS, "> $bcpFileErrors") or die "Failed to open $bcpFileErrors";
    foreach my $error (sort {$errorMsgTypes->{$b} <=> $errorMsgTypes->{$a}} keys %{$errorMsgTypes}) {
        print BCPERRORS "$date\t$siteId\t$error\t$errorMsgTypes->{$error}\n";
    }
    close BCPERRORS;

    dbDo( $dbh, "DELETE FROM pm_errors WHERE siteid = $siteId AND date = '$date'" )
        or die "Failed to delete from pm_errors";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileErrors' INTO TABLE pm_errors" )
        or die "Failed to load new data from '$bcpFileErrors' file to 'pm_errors' table";
}

sub storeStats($$$$$) {
    my ($r_fileCollectionStats, $r_serverMap, $dbh, $siteId, $tmpDir) = @_;

    my %startCollectionOffset = (
        1440 => 5 * 60,
        15 => 5 * 60,
        1 =>  1 * 60,
        5 =>  1 * 60,
        30 => 5 * 60,
        60 => 5 * 60,
        720 => 5 * 60
        );
    my %typeMap = (
        1440 => '1440MIN',
        15 => '15MIN',
        1 => '1MIN',
        5 => '5MIN',
        30 => '30MIN',
        60 => '60MIN',
        720 => '720MIN'
        );
    while ( my ($pmserv,$r_statsByRop) = each %{$r_fileCollectionStats} ) {
        my $serverId = $r_serverMap->{$pmserv};
        while ( my ($ropInterval,$r_samples) = each %{$r_statsByRop} ) {
            my $secondsInROP = $ropInterval * 60;
            # PMIC may write multiple records for the same ROP
            # TORF-155379
            my @mergedSamples = ();
            my $min = undef;
            my $max = undef;
            foreach my $r_sample ( @{$r_samples} ) {
                my $expectedFileCollectionStart = int($r_sample->{'ropStartTimeIdentifier'} / 1000) +
                    $secondsInROP +
                    $startCollectionOffset{$ropInterval};
                my $fcs = formatSiteTime($expectedFileCollectionStart, $StatsTime::TIME_SQL);
                if ( $::DEBUG > 5 ) {
                    printf "storeData: efcs: %d (%s), ropStartTime %d (%s)\n",
                        $expectedFileCollectionStart, $fcs,
                        $r_sample->{'ropStartTime'},
                        formatSiteTime($r_sample->{'ropStartTime'} / 1000, $StatsTime::TIME_SQL);
                }

                if ( ! defined $min || ($min > $expectedFileCollectionStart) ) {
                    $min = $expectedFileCollectionStart;
                }
                if ( ! defined $max || ($max < $expectedFileCollectionStart) ) {
                    $max = $expectedFileCollectionStart;
                }

                $r_sample->{'fcs'} = $expectedFileCollectionStart;
                $r_sample->{'fcs_str'} = $fcs;
                $r_sample->{'duration'} = int ( ($r_sample->{'ropEndTime'} - ($expectedFileCollectionStart*1000)) / 1000);
                $r_sample->{'mb_stor'} = int ($r_sample->{'numberOfBytesStored'}  / (1024*1024) );
                $r_sample->{'mb_txfr'} = int ($r_sample->{'numberOfBytesTransferred'}  / (1024*1024) );

                # Handle multiple log entries that have same ropStartTimeIdentifier and belong to the same ROP
                if ( $#mergedSamples > -1 &&  exists $r_sample->{'ropStartTimeIdentifier'} &&
                     exists $mergedSamples[$#mergedSamples]->{'ropStartTimeIdentifier'} &&
                     $r_sample->{'ropStartTimeIdentifier'} == $mergedSamples[$#mergedSamples]->{'ropStartTimeIdentifier'} ) {
                    mergeSamples($mergedSamples[$#mergedSamples], $r_sample);
                }
                # Handle multiple log entries that have different ropStartTimeIdentifier but belong to the same ROP - TORF-183752
                elsif ( $#mergedSamples > -1 && $r_sample->{'fcs_str'} eq $mergedSamples[$#mergedSamples]->{'fcs_str'} ) {
                    mergeSamples($mergedSamples[$#mergedSamples], $r_sample);
                }
                # Handle the usual cases of one log entry per ROP
                else {
                    push @mergedSamples, $r_sample;
                }
            }

            my $bcpFile = sprintf("%s/enm_pmic_rop_%d_%d.bcp", $tmpDir, $ropInterval, $serverId);
            open OUTPUT, ">$bcpFile" or die "Cannot open $bcpFile";
            foreach my $r_sample ( @mergedSamples ) {
                my @row = ( $siteId, $serverId,
                            $r_sample->{'fcs_str'},
                            $typeMap{$ropInterval}, $r_sample->{'duration'},
                            $r_sample->{'numberOfFilesCollected'}, $r_sample->{'numberOfFilesFailed'},
                            $r_sample->{'mb_txfr'}, $r_sample->{'mb_stor'} );
                print OUTPUT join("\t",@row), "\n";
            }
            close OUTPUT;
            dbDo($dbh,sprintf("DELETE FROM enm_pmic_rop WHERE siteid = %d AND serverid = %d AND fcs BETWEEN '%s' AND '%s' AND type = '%s'",
                              $siteId, $serverId,
                              formatSiteTime($min,$StatsTime::TIME_SQL),
                              formatSiteTime($max,$StatsTime::TIME_SQL),
                              $typeMap{$ropInterval})
                ) or die "Failed to remove old data";
            dbDo($dbh,"LOAD DATA LOCAL INFILE '$bcpFile' INTO TABLE enm_pmic_rop (siteid,serverid,fcs,type,duration,files_succ,files_fail,mb_txfr,mb_stor)")
                or die "Failed to load data";
        }
    }
}

1;
