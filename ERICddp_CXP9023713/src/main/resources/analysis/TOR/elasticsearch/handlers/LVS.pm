package LVS;

use strict;
use warnings;

use Data::Dumper;
use JSON;
use File::Basename;

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
    if ( exists $r_incr->{'LVSStates'} ) {
        $self->{'r_LVSStates'} = $r_incr->{'LVSStates'}->{'r_LVSStates'};
    } else {
        $self->{'r_LVSStates'} = {};
    }

    my $r_serverMap = enmGetServiceGroupInstances($self->{'site'}, $self->{'date'}, "%lvsrouter");
    my @programsToFilter = ('Keepalived_vrrp');
    my @subscriptions = ();
    foreach my $server ( keys %{$r_serverMap} )
    {
        foreach my $program ( @programsToFilter )
        {
            push ( @subscriptions, {'server' => $server, 'prog' => $program} );
        }
    }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;
    if ( $program eq 'Keepalived_vrrp' ) {
        if ( $message =~ /VRRP_Instance(.*\))\s(.*)/ ) {
            my $stateStr = $2;
            if ( $stateStr =~ /Received higher prio advert/ ) {
                $self->{'r_LVSStates'}->{$host}->{$stateStr}++;

            } elsif ( $stateStr =~ /Transition to MASTER STATE/ ) {
                $self->{'r_LVSStates'}->{$host}->{$stateStr}++;

            } elsif ( $stateStr =~ /Received lower prio advert, forcing new election/ ) {
                $self->{'r_LVSStates'}->{$host}->{$stateStr}++;

            } elsif ( $stateStr =~ /Entering BACKUP STATE/ ) {
                $self->{'r_LVSStates'}->{$host}->{$stateStr}++;
            }
        }
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    my $servername2IdMap = getIdMap($dbh, "servers", "id", "hostname", [], $self->{'siteId'});

    # Get the 'enm_lvs_states.bcp' file ready to store the parsed lvs states data
    my $tmpDir = '/data/tmp';
    if ( exists $ENV{'TMP_DIR'} ) {
        $tmpDir = $ENV{'TMP_DIR'};
    }
    my $bcpFile = "$tmpDir/enm_lvs_states.bcp";
    open (BCP, "> $bcpFile");
    while ( my ($serverName,$r_serverStates) = each %{$self->{'r_LVSStates'}} ) {
        my $serverId = $servername2IdMap->{$serverName};
        if ( defined $serverId ) {
            while ( my ($state,$count) = each %{$r_serverStates} ) {
                printf BCP "%s\t%d\t%s\t%d\t%d\n", $self->{'date'}, $self->{'siteId'}, $state, $count, $serverId;
            }
        }
    }
    close BCP;
    dbDo( $dbh, sprintf("DELETE FROM enm_lvs_states WHERE siteid = %d AND date = '%s'", $self->{'siteId'}, $self->{'date'}) )
        or die "Failed to delete old data from enm_lvs_states";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFile' INTO TABLE enm_lvs_states" )
          or die "Failed to load new data from '$bcpFile' file to 'enm_lvs_states' table";
    unlink($bcpFile);
    $r_incr->{'LVSStates'} = {
        'r_LVSStates' => $self->{'r_LVSStates'}
    };
}

1;

