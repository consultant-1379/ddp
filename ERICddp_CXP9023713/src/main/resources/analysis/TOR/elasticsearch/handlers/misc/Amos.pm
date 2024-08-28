package misc::Amos;

use strict;
use warnings;

use Data::Dumper;
use Digest::MD5 qw(md5_hex);

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

    if ( exists $r_incr->{'Amos'} ) {
        $self->{'data'} = $r_incr->{'Amos'};
        if ( ! exists $self->{'data'}->{'anonUserMap'} ) {
            $self->{'data'}->{'anonUserMap'} = {};
        }
    } else {
        $self->{'data'} = {
            'scriptName' => {},
            'cmdName' => {},
            'userName' => {},
            'serverCommandTimeCounts' => {},
            'anonUserMap' => {}
        };
    }

    $self->{'srvIdMap'} = {};
    for my $sg ( 'amos', 'generalscripting', 'general-scripting' ) {
        my $r_map = enmGetServiceGroupInstances($self->{'site'},$self->{'date'},$sg);
        while ( my ($server,$id) = each %{$r_map} ) {
            $self->{'srvIdMap'}->{$server} = $id;
        }
    }
    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }

    if ( $::DEBUG > 5 ) { print Dumper("Amos::init subscriptions",\@subscriptions) ; }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }

    #2018-03-01T00:39:10.700+00:00@scp-1-amos@JBOSS@INFO  [com.ericsson.oss.itpf.EVENT_LOGGER] (http-executor-threads - 24) [AMOS_04_1212-02503709_u119, AMOS.COMMAND, DETAILED, application name = AMOS, Network Element, Message = lst RfPort ; DateTime=2018-03-01.00:38:59 was executed on ieatnetsimv7007-49_LTE20ERBS00051 (ieatnetsimv7007-49_LTE20ERBS00051). Result=OK]

    if( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.EVENT_LOGGER\]\s+\([^\)]+\)\s+\[([^,\s]+?),\s+AMOS\.COMMAND, (.*)\]$/ ) {
        my ($user,$commandInfo) = ($1,$2);
        if ( $::DEBUG > 4 ) { print "Amos::handle user=$user commandInfo=$commandInfo\n" ; }
        my ($command,$cmdStatus) = $commandInfo =~ /Message =(.*?) [;\sDateTime |was executed] .* Result=(OK|Fail)/;

        if ( ! defined $command || ! defined $cmdStatus ) {
            if ( $::DEBUG > 4 ) { print "WARN: Amos::handle failed to parse commandInfo $commandInfo timestamp=$timestamp host=$host message=$message\n"; }
            return;
        }

        $self->{'data'}->{'userName'}->{$user}++;

        if($command =~/[\s\w\s]*[~!@#\$\%^&*()_+|\}\{:"<>?,.\/';\]\[=\-`]+[\s\w\s]*/) {
            if( $cmdStatus eq 'OK' ) {
                $self->{'data'}->{'scriptName'}->{"$host"}{'successcount'}++;
            }
            elsif ( $cmdStatus eq 'Fail' ) {
                $self->{'data'}->{'scriptName'}->{"$host"}{'failurecount'}++;
            }
        } elsif($command =~/^[a-zA-Z0-9\s]+$/) {
            $command =~ s/\d+/[NUM]/g;
            my $activityId = $host . "@" . $command;
            $self->{'data'}->{'cmdName'}->{"$activityId"}{'server'} = $host;
            $self->{'data'}->{'cmdName'}->{"$activityId"}{'command'} = $command;
            if( $cmdStatus eq 'OK' ) {
                $self->{'data'}->{'cmdName'}->{"$activityId"}{'successcount'}++;
            } elsif ( $cmdStatus eq 'Fail' ) {
                $self->{'data'}->{'cmdName'}->{"$activityId"}{'failurecount'}++;
            }
        }

        my $time = parseTime($timestamp, $StatsTime::TIME_ELASTICSEARCH_MSEC);
        my $alignedTime = $time - ($time % 60);
        $self->{'data'}->{'serverCommandTimeCounts'}->{$host}{$alignedTime}++;
    }
}

sub handleExceeded($$$) {
    my ($self, $host, $program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 3 ) { print Dumper("Amos::handle::done self", $self); }

    $self->storeAmos($dbh);
    $r_incr->{'Amos'} = $self->{'data'};
}

sub anonUserId {
    my ($realUser,$r_mappedIds) = @_;

    my $anonUser = 'ANON_USER_' . substr(md5_hex( $realUser ),0,8);
    if ( exists $r_mappedIds->{$anonUser} ) {
        if ( $r_mappedIds->{$anonUser} ne $realUser ) {
            print "WARNING: anonUserId clash for $realUser and " . $r_mappedIds->{$anonUser} . "\n";
        }
    } else {
        $r_mappedIds->{$anonUser} = $realUser;
    }

    return $anonUser;
}

sub storeAmos($$) {
    my ($self,$dbh) = @_;

    my $userName                = $self->{'data'}->{'userName'};
    my $cmdName                 = $self->{'data'}->{'cmdName'};
    my $scriptName              = $self->{'data'}->{'scriptName'};
    my $serverCommandTimeCounts = $self->{'data'}->{'serverCommandTimeCounts'};
    my $date                    = $self->{'date'};
    my $site                    = $self->{'site'};
    my $script=undef;
    my $count=0;
    my $script_total_count=0;

    my $siteId = getSiteId($dbh, $site);
    ( $siteId != -1 ) or die "Failed to get siteid for $site";

    # Start processing data for enm_amos_users table
    if ( %{$userName} ) {
        my $bcpFileUsers = getBcpFileName("enm_amos_users");
        open (BCPUSERS, "> $bcpFileUsers") or die "Failed to open $bcpFileUsers";
        my $r_anonUserMap = $self->{'data'}->{'anonUserMap'};
        while ( my ($userName,$count) = each %{$userName} ) {
            my $anonUser = anonUserId($userName,$r_anonUserMap);
            print BCPUSERS "$date\t$siteId\t$anonUser\t$count\n";
        }
        close BCPUSERS;
        dbDo( $dbh, "DELETE FROM enm_amos_users WHERE siteid = $siteId AND date = '$date'" )
            or die "Failed to delete from enm_amos_users".$dbh->errstr."\n";
        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileUsers' INTO TABLE enm_amos_users" )
            or die "Failed to load new data from '$bcpFileUsers' file to 'enm_amos_users' table".$dbh->errstr."\n";
    }
    # Start processing data for enm_amos_commands table
    if ( %{$scriptName} || %{$cmdName} ) {
        my $bcpFileCmds = getBcpFileName('enm_amos_commands');
        $script="script";
        open (BCPCOMMANDS, "> $bcpFileCmds") or die "Failed to open $bcpFileCmds";

        foreach my $key (sort {$cmdName->{$b} <=> $cmdName->{$a}} keys %{$cmdName}) {
            my $serverid = $self->{'srvIdMap'}->{$cmdName->{$key}->{'server'}};
            if ( ! defined $cmdName->{$key}->{'successcount'} ) {
                $cmdName->{$key}->{'successcount'} = 0;
            }
            if ( ! defined $cmdName->{$key}->{'failurecount'} ) {
                $cmdName->{$key}->{'failurecount'} = 0;
            }
            print BCPCOMMANDS "$date\t$siteId\t$serverid\t$cmdName->{$key}->{'command'}\t$cmdName->{$key}->{'successcount'}\t$cmdName->{$key}->{'failurecount'}\n";
        }

        foreach my $server ( keys %{$scriptName}) {
            my $serverid = $self->{'srvIdMap'}->{$server};
            if ( ! defined $scriptName->{$server}->{'successcount'} ) {
                $scriptName->{$server}->{'successcount'} = 0;
            }
            if ( ! defined $scriptName->{$server}->{'failurecount'} ) {
                $scriptName->{$server}->{'failurecount'} = 0;
            }
            print BCPCOMMANDS "$date\t$siteId\t$serverid\t$script\t$scriptName->{$server}->{'successcount'}\t$scriptName->{$server}->{'failurecount'}\n";
        }
        close BCPCOMMANDS;

        dbDo( $dbh, "DELETE FROM enm_amos_commands WHERE siteid = $siteId AND date = '$date'" )
            or die "Failed to delete from enm_amos_commands".$dbh->errstr."\n";

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileCmds' INTO TABLE enm_amos_commands" )
            or die "Failed to load new data from '$bcpFileCmds' file to 'enm_amos_commands' table".$dbh->errstr."\n";
    }
    # Start processing data for enm_amos_clusters table
    if ( %{$serverCommandTimeCounts} ) {
        my $bcpFileClusters = getBcpFileName("enm_amos_clusters");
        open (BCPCLUSTERS, "> $bcpFileClusters") or die "Failed to open $bcpFileClusters";
        foreach my $serverName (keys %{$serverCommandTimeCounts}) {
            my $serverid = $self->{'srvIdMap'}->{$serverName};
            my $serverId = getServerId( $dbh, $siteId, $serverName );
            while (my ($time, $count) = each %{ $serverCommandTimeCounts->{$serverName} }) {
                my $sqlDateTime = formatTime($time, $StatsTime::TIME_SQL);
                print BCPCLUSTERS "$sqlDateTime\t$siteId\t$serverId\t$serverCommandTimeCounts->{$serverName}{$time}\n";
            }
        }
        close BCPCLUSTERS;

        dbDo( $dbh, "DELETE FROM enm_amos_clusters WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'" )
            or die "Failed to delete from enm_amos_clusters".$dbh->errstr."\n";

        dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileClusters' INTO TABLE enm_amos_clusters" )
            or die "Failed to load new data from '$bcpFileClusters' file to 'enm_amos_clusters' table".$dbh->errstr."\n";
    }
}

1;
