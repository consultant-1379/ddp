package cm::SavedSearches;

use strict;
use warnings;

use Data::Dumper;

use EnmServiceGroup;
use StatsDB;

our %ATTR_KEEP_VALUE = (
    'neType' => 1
);

sub stripQuery($) {
    my ($query) = @_;

    # One word queries are likely to be single node names
    if ( $query =~ /^(\S+)$/ ) {
        if ( $query !~ /MeContext/i ) {
            $query =~ s/[^\*]+/[STRIPPED]/;
        }
    } else {
        # First off, make sure any = has spaces either side
        $query =~ s/(\S)=/$1 =/g;
        $query =~ s/=(\S)/= $1/g;

        my @parts = split (/\s+/, $query);
        for ( my $index = 0; $index <= $#parts; $index++ ) {
            if ( $parts[$index] =~ /^".+"$/ ) {
                # Dump any quoted part
                $parts[$index] = '[STRIPPED]';
            } elsif ( $index > 0 && $parts[$index-1] eq 'node' && $index == $#parts ) {
                # Dump the node name at the end of query
                $parts[$index] =~ s/[^\*]+/[STRIPPED]/;
            } elsif ( $index > 0 && $index < $#parts && $parts[$index] eq '=' ) {
                if ( ! exists $ATTR_KEEP_VALUE{$parts[$index-1]} &&  $parts[$index-1] !~ /State$|Status$/ ) {
                    $parts[$index+1] =~ s/[^\*]+/[STRIPPED]/;
                }
            } elsif ( $index < $#parts && $parts[$index] eq 'collection' ) {
                $parts[$index+1] = '[STRIPPED]';
            }
        }

        $query = join(" ", @parts);
    }

    return $query;
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

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};

    if ( exists $r_incr->{'cm::SavedSearches'} ) {
        $self->{'queries'} = $r_incr->{'cm::SavedSearches'}->{'queries'};
    } else {
        $self->{'queries'} = {};
    }

    $self->{'invalid'} = 0;

    # Now figure out the hosts where httpd is running
    my $r_serverMap = enmGetServiceGroupInstances($r_cliArgs->{'site'},$r_cliArgs->{'date'},"networkexplorer");
    my @subscriptions = ();
    foreach my $server ( keys %{$r_serverMap} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'JBOSS' };
    }

    return \@subscriptions;
}




sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $severity ne 'info' ) {
        return;
    }

    if ( $::DEBUG > 7 ) { print "$timestamp $host $message\n"; }

    #INFO  [com.ericsson.oss.itpf.COMMAND_LOGGER] (EJB default - 46) [administrator, TopologySearchService.SearchQuery, FINISHED_WITH_SUCCESS, REST, MeContext where object MeContext has parent SubNetwork with SubNetworkId = "Torrance" and where object MeContext has child Ip and Ip has attr nodeIpAddress=11.204.12*, {user:administrator, results:35, Time taken:199ms}]
    if ( $message =~ /^INFO\s+\[com\.ericsson\.oss\.itpf\.COMMAND_LOGGER\] \(.*\) \[[^,]+, TopologySearchService.SearchQuery, FINISHED_WITH_SUCCESS, REST, ([^,]+), \{(.*)\}]$/ ) {
        my ($query,$metadata) = ($1, $2);
        if ( $::DEBUG > 6 ) { print "cm::SavedSearches query=\"$query\", metadata=\"$metadata\"\n"; }

        my ($results) = $metadata =~ /, results:(\d+)/;
        my ($duration) = $metadata =~ /, Time taken:(\d+)/;
        if ( (! defined $results) || (! defined $duration) ) {
            $self->{'invalid'}++;
            return;
        }

        my $strippedQuery = stripQuery($query);
        my $r_queryStats = $self->{'queries'}->{$strippedQuery};
        if ( ! defined $r_queryStats ) {
            $r_queryStats = {
                'count' => 0,
                'duration' => 0,
                'results' => 0
            };
            $self->{'queries'}->{$strippedQuery} = $r_queryStats;
        }
        $r_queryStats->{'count'}++;
        $r_queryStats->{'duration'} += $duration;
        $r_queryStats->{'results'} += $results;
    }
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 4 ) { print Dumper("cm::SavedSearches self", $self); }

    if ( $self->{'invalid'} > 0 ) {
        print "WARN: cm::SavedSearches invalid log entries found: " . $self->{'invalid'} . "\n";
    }

    my $bcpFileName = getBcpFileName('enm_netex_queries');
    open BCP, ">$bcpFileName" or die "Cannot open $bcpFileName";
    while ( my ($query,$r_queryStats) = each %{$self->{'queries'}} ) {
        printf BCP "%d\t%s\t%d\t%d\t%d\t%s\n",
            $self->{'siteId'},
            $self->{'date'},
            $r_queryStats->{'count'},
            $r_queryStats->{'results'},
            $r_queryStats->{'duration'},
            $query;
    }
    close BCP;

    dbDo( $dbh, sprintf("DELETE FROM enm_netex_queries WHERE siteid = %d AND date = '%s'",
                        $self->{'siteId'},
                        $self->{'date'})
      ) or die "Failed to delete from enm_netex_queries";

    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFileName' INTO TABLE enm_netex_queries (siteid,date,count,results,duration,query)"   )
        or die "Failed to load data in $bcpFileName into enm_netex_queries";

    $r_incr->{'cm::SavedSearches'} = {
        'queries' => $self->{'queries'}
    };
}

1;
