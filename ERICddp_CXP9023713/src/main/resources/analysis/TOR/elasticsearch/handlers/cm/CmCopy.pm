package cm::CmCopy;

use strict;
use warnings;

use Data::Dumper;

use StatsDB;
use StatsTime;
use EnmServiceGroup;

#
# Internal functions
#
sub parseLogEntry($$$) {
    my ($self,$timestamp,$host,$message) = @_;

    if ($message =~ /cm-config-service.*jobId\s*=\s*(\d+)/) {
        if ( $::DEBUG > 5 ) { print "cm::CmCopy::parseLogEntry matched $message\n"; }
        my %batch = (
            'jobid'               => $1,
            'batchstatus'         => '',
            'starttime'           => '',
            'endtime'             => '',
            'elapsedtime'         => '',
            'sourceconfig'        => '',
            'targetconfig'        => '',
            'expectednodescopied' => '',
            'nodescopied'         => '',
            'nodesnotcopied'      => '',
            'nodesnomatchfound'   => ''
        );

        chomp $message;
        $message =~ s/\]$//;

        if ( $message =~ /batchStatus\s*=\s*([^,]+)/ ) {
            $batch{'batchstatus'} = $1;
        }
        if ( $message =~ /startTime\s*=\s*(\d{4,4}-\d{2,2}-\d{2,2})T(\d{2,2}:\d{2,2}:\d{2,2})/ ) {
            $batch{'starttime'} = $1 . " " . $2;
        }
        if ( $message =~ /endTime\s*=\s*(\d{4,4}-\d{2,2}-\d{2,2})T(\d{2,2}:\d{2,2}:\d{2,2})/ ) {
            $batch{'endtime'} = $1 . " " . $2;
        }
        if ( $message =~ /elapsedTime\s*=\s*(\d+)/ ) {
            $batch{'elapsedtime'} = $1;
        }
        if ( $message =~ /sourceConfig\s*=\s*([^,]+)/ ) {
            $batch{'sourceconfig'} = $1;
        }
        if ( $message =~ /targetConfig\s*=\s*([^,]+)/ ) {
            $batch{'targetconfig'} = $1;
        }
        if ( $message =~ /expectedNodesCopied\s*=\s*(\d+)/ ) {
            $batch{'expectednodescopied'} = $1;
        }
        if ( $message =~ /nodesCopied\s*=\s*(\d+)/ ) {
            $batch{'nodescopied'} = $1;
        }
        if ( $message =~ /nodesNotCopied\s*=\s*(\d+)/ ) {
            $batch{'nodesnotcopied'} = $1;
        }
        if ( $message =~ /nodesNoMatchFound\s*=\s*(\d+)/ ) {
            $batch{'nodesnomatchfound'} = $1;
        }

        if ( $::DEBUG > 5 ) { print Dumper("cm::CmCopy::parseLogEntry batch", \%batch); }
        push @{$self->{'batches'}}, \%batch;
    }
}

sub storeCmservConfigLogs($$$) {
    my($cmservConfigLogStats,$siteId,$dbh) = @_;

    my $bcpFileCmservConfigLogs = getBcpFileName("enm_cmconfig_logs");
    open (BCP, "> $bcpFileCmservConfigLogs") or die "Failed to open $bcpFileCmservConfigLogs";
    foreach my $r_batch ( @{$cmservConfigLogStats} ) {
        print BCP "$siteId\t$r_batch->{'jobid'}\t$r_batch->{'batchstatus'}\t$r_batch->{'starttime'}\t$r_batch->{'endtime'}\t$r_batch->{'elapsedtime'}\t$r_batch->{'sourceconfig'}\t$r_batch->{'targetconfig'}\t$r_batch->{'expectednodescopied'}\t$r_batch->{'nodescopied'}\t$r_batch->{'nodesnotcopied'}\t$r_batch->{'nodesnomatchfound'}\n";
    }

    close BCP;
    dbDo( $dbh, sprintf("DELETE FROM enm_cmconfig_logs WHERE siteid = %d AND startTime BETWEEN '%s' AND '%s'",
                        $siteId,
                        $cmservConfigLogStats->[0]->{'starttime'},
                        $cmservConfigLogStats->[$#{$cmservConfigLogStats}]->{'starttime'}) )
        or die "Failed to delete from enm_cmconfig_logs" . $dbh->errstr;

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileCmservConfigLogs' INTO TABLE enm_cmconfig_logs" )
        or die "Failed to load new data from '$bcpFileCmservConfigLogs' file to 'enm_cmconfig_logs' table" . $dbh->errstr;
}


#
# handler interface functions
#
sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub init($$$$) {
    my ($self,$r_cliArgs,$r_incr,$dbh) = @_;

    $self->{'batches'} = [];

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    $self->{'srvIdMap'} = {};
    foreach my $sg ( 'cmservice', 'conscmeditor' ) {
        my $r_srvMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, $sg);
        while ( my ($srv, $srvId) = each %{$r_srvMap} ) {
            $self->{'srvIdMap'}->{$srv} = $srvId;
        }
    }

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }

    if ( $::DEBUG > 5 ) { print Dumper("cm::CmCopy::init subscriptions",\@subscriptions) ; }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $::DEBUG > 7 ) { print "cm::CmCopy::handle timestamp=$timestamp program=$program message=$message\n"; }

    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\] \(.*\) \[[^,]+, COPY_SERVICE\.COMPLETED/ ) {
        $self->parseLogEntry($timestamp,$host,$message);
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}


sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("cm::CmCopy::done self", $self); }

    if ( $#{$self->{'batches'}} > -1 ) {
        storeCmservConfigLogs($self->{'batches'},$self->{'siteId'},$dbh);
    }
}


1;
