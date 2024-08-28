package misc::ApacheAccessLog;

use strict;
use warnings;

use Data::Dumper;

use EnmServiceGroup;
use StatsDB;
use JSON;


#
# Internal functions
#
our @IGNORE_TYPE_LIST = ( 'js', 'svg', 'json', 'jpg', 'png', 'PNG', 'JPG', 'xml', 'css', 'ico', 'ttf', 'yaml' );
our @VALID_METHODS = ( 'GET', 'POST', 'PUT', 'PATCH', 'HEAD', 'DELETE' );
our @TERMINATION_WORDS = (
    'users', 'file', 'fdn', 'session', 'model', 'template', 'roles', 'targetgroups',
    'delete', 'jobs', 'ldapsearchForSystemAndComRoles', 'download', 'network-elements',
    'executions', 'inventoryExportProgress', 'subscription', 'cell', 'auto-provisioning',
    'templates', 'connectionprofiles', 'discoveryactivities', 'attributes', 'node',
    'lrfProgress', 'filters', 'login', 'topology-operator', 'data', 'elex', 'server-scripting',
    'modelInfo', 'ncm', 'kpi-management', 'subscriptions', 'supported', 'ops-attach',
    'summary', 'history', 'kpi-values', 'kpis'
);

sub getTermHash() {
    my %termination_words = ();
    foreach my $word ( @TERMINATION_WORDS ) {
        $termination_words{$word} = 1;
    }
    return \%termination_words;
}

sub stripURI($$) {
    my ($uri,$r_term) = @_;
    my @inUriParts = split("/",$uri);
    if ( $::DEBUG > 8 ) { print Dumper("stripURI: inUriParts", \@inUriParts); }
    my @outUriParts = ();
    if ( $#inUriParts > -1 ) {
        shift @inUriParts;      # Dump the empty first part

        my $foundEnd = 0;
        while ( $#inUriParts > -1 && ($foundEnd == 0) ) {
            my $part = shift @inUriParts;
            if ( $part =~ /^\d+$/ ||
                 $part =~ /^-\d+$/ ||
                 $part =~ /[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}/ ||
                 $part =~ /[,:\.]/ ||
                 $part =~ /^~/ ||
                 $part =~ /%/
             ) {
                $foundEnd = 1;
            } elsif ( exists $r_term->{$part} ) {
                push @outUriParts, $part;
                $foundEnd = 1;
            } else {
                push @outUriParts, $part;
            }
        }
    }
    $uri = "/" . join("/", @outUriParts);

    if ( $::DEBUG > 8 ) { print "stripURI: returning $uri\n"; }

    return $uri;
}

sub parseLogEntry($$) {
    my ($self,$line) = @_;

    $self->{'count'}++;

    $line =~ s/\s+/ /go;

    # TORF-439067: New format added as part of JIRA TORF-372053 adding Balancer Work Route
    # LogFormat "%h %p %l %u %t %D \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %{X-Forwarded-For}i %{X-Tor-Application}i \"BALANCER_WORKER_ROUTE: %{BALANCER_WORKER_ROUTE}e\"" combined
    # Regex changed to support both new and existing log format
    # TORF-280680: New format adding port and execution time
    # Looks like it was changed in ERIChttpdconfig_CXP9031096 1.37.3 which chaning in 18.10 (ISO 1.59.59)
    #LogFormat "%h %p %l %u %t %D \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %{X-Forwarded-For}i %{X-Tor-Application}i" combined
    # %p    The canonical port of the server serving the request
    # %l    Remote logname (from identd, if supplied).
    # %u    Remote user if the request was authenticated
    # %t    Time the request was received, in the format [18/Sep/2011:19:18:28 -0400]. The last number indicates the timezone offset from GMT
    # %D    The time taken to serve the request, in microseconds.
    # %r    First line of request.
    # %s    Status. For requests that have been internally redirected, this is the status of the original request. Use %>s for the final status.
    # %b    Size of response in bytes, excluding HTTP headers. In CLF format, i.e. a '-' rather than a 0 when no bytes are sent.
    # condense one or more whitespace character to one single space
    my ($clientAddress, $usernamel, $usernamer,
        $localTime, $httpRequest, $statusCode,
        $bytesSentToClient, $userAgent, $remainder) = $line =~
            /^(\S+) \d+ (\S+) (\S+) \[(.+)\] \d+ \"(.+)\" (\S+) (\S+) \"[^\"]*\" \"([^\"]*)\"\s*(.*)/o;
    if ( ! defined $userAgent ) {
        # Try old format (TORF-280680)
        #LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %{X-Forwarded-For}i" combined
        ($clientAddress, $usernamel, $usernamer,
         $localTime, $httpRequest, $statusCode,
         $bytesSentToClient, $userAgent, $remainder) = $line =~
            /^(\S+) (\S+) (\S+) \[(.+)\] \"(.+)\" (\S+) (\S+) \"(.*)\"\s*(.*)/o;
    }
    if ( ! defined $userAgent ) {
        if ( $::DEBUG > 1 ) { print "misc::ApacheAccessLog::parseLog failed to parse $line\n"; }
        $self->{'fail'}++;
        return;
    }

    my ($method, $uri, $junk) = split(' ', $httpRequest, 3);
    if ( $::DEBUG > 8 ) { print "ApacheAccessLog::parseLog: httpRequest=$httpRequest statusCode=$statusCode method=$method uri=$uri junk=$junk remainder=$remainder\n"; }

    if ( ! exists $self->{'valid_methods'}->{$method} ) {
        $self->{'invalid_methods'}->{$method}++;
        return;
    }

    # Ignore javascript files or svg files
    if ( $uri =~ /\.([A-Za-z]+)$/ ) {
        my $fileExt = $1;
        if ( exists $self->{'ignoreHash'}->{$fileExt} ) {
            return;
        }
    }

    # Strip any args off the uri
    my $strippedURI = $uri;
    $strippedURI =~ s/\?.*//;
    $strippedURI = stripURI($strippedURI,$self->{'termination_words'});

    if ( $statusCode == 503 ) {
        $self->{'data'}->{'service_unavailable'}->{$strippedURI}++;
    } elsif ( $statusCode >= 200 && $statusCode < 300 ) {
        if ( $uri =~ /^\/\?applicationPath=(.+)/ ) {
            my $json_str = $1;
            if ( $::DEBUG > 7 ) { print "ApacheAccessLog::parseLog json_str=$json_str\n"; }
            $json_str =~ s/\%([A-Fa-f0-9]{2})/pack('C', hex($1))/seg;
            $json_str =~ s/\+/ /g;
            my $r_apppath = undef;
            eval {
                $r_apppath = decode_json($json_str);
            };
            if ( ! defined $r_apppath ) {
                print "WARN: ApacheAccessLog::parseLog Failed to decode $json_str\n";
                return;
            }

            if ( $#{$r_apppath} > -1 ) {
                my $key = join(">", @{$r_apppath});
                $self->{'data'}->{'app_counts'}->{$key}++;
                if ( $usernamer ne '-' ) {
                    $self->{'data'}->{'app_users'}->{$key}->{$usernamer} = 1;
                }
            }
        } else {
            my $xTorApplication = "NA";
            if ( defined $remainder ) {
                if ( $remainder =~ /(\S+)\s+\"BALANCER/ ) {
                    $xTorApplication = $1;
                } else {
                    ($xTorApplication) = $remainder =~ /(\S+)\s*$/;
                }
                if ( (!defined $xTorApplication) || ($xTorApplication eq '-') || ($xTorApplication =~ /\d+\.\d+\.\d+\.\d+/) ) {
                    $xTorApplication = "NA";
                }
            }

            if ( $::DEBUG > 7 ) { print "ApacheAccessLog::parseLog: xTorApplication=$xTorApplication strippedURI=\"$strippedURI\"\n"; }
            $self->{'data'}->{'requestCounts'}->{$strippedURI}->{$method}->{$xTorApplication}++;
            return;
        }
    } else {
        if ( $::DEBUG > 3 ) { print "ApacheAccessLog::parseLog: discarding url httpRequest due to statusCode $statusCode\n"; }
    }
}

sub store($$$) {
    my ($self,$dbh) = @_;

    my $siteId = $self->{'siteId'};
    my $r_requestCounts = $self->{'data'}->{'requestCounts'};
    my $date = $self->{'date'};

    my %allUri = ();
    my %allApps = ();
    while ( my ($uri,$r_counts) = each %{$r_requestCounts} ) {
        $allUri{$uri} = 1;
        while ( my ($method,$r_countByApp) = each %{$r_counts} ) {
            while ( my ($xTorApplication,$count) = each %{$r_countByApp} ) {
                $allApps{$xTorApplication} = 1;
            }
        }
    }

    my $r_srvUnavail = $self->{'data'}->{'service_unavailable'};
    foreach my $uri ( keys %{$r_srvUnavail} ) {
        $allUri{$uri} = 1;
    }

    my @uriList = keys %allUri;
    if ( $#uriList == -1 ) {
        return;
    }
    my $r_idMap = getIdMap($dbh, "enm_apache_uri", "id", "uri", \@uriList);

    my @appList = keys %allApps;
    my $r_appIdMap = getIdMap($dbh, "enm_apache_app_names", "id", "name", \@appList);


    my $r_contexts = dbSelectAllHash($dbh, "
SELECT enm_context_names.name AS context, enm_sg_contexts.serviceid AS sgid
FROM enm_sg_contexts, enm_context_names
WHERE
 enm_sg_contexts.siteid = $siteId AND
 enm_sg_contexts.date = '$date' AND
 enm_sg_contexts.contextid = enm_context_names.id") or die "Failed to query enm_sg_contexts";

    dbDo($dbh, "DELETE FROM enm_apache_requests WHERE siteid = $siteId AND date = '$date'")
        or die "Failed to delete from enm_apache_requests";
    while ( my ($uri,$r_counts) = each %{$r_requestCounts} ) {
        # Figure out what service group handles this URI by finding a content
        # registered with modcluster
        my $sgid = '\N';
        foreach my $r_context ( @{$r_contexts} ) {
            if ( $uri =~ /^$r_context->{'context'}/ ) {
                $sgid = $r_context->{'sgid'};
                last;
            }
        }

        while ( my ($method,$r_countByApp) = each %{$r_counts} ) {
            while ( my ($xTorApplication,$count) = each %{$r_countByApp} ) {
                dbDo($dbh, sprintf("INSERT INTO enm_apache_requests (siteid,date,method,uriid,appid,requests,sgid) VALUES (%d,'%s','%s',%d,%d,%d,%s)",
                                   $siteId,$date,$method,$r_idMap->{$uri},$r_appIdMap->{$xTorApplication},$count, $sgid) )
                    or die "Failed to insert into enm_apache_requests";
            }
        }
    }

    dbDo($dbh, "DELETE FROM enm_apache_srv_unavail WHERE siteid = $siteId AND date = '$date'")
        or die "Failed to delete from enm_apache_srv_unavail";
    while ( my ($uri,$count) = each %{$r_srvUnavail} ) {
        dbDo($dbh, sprintf("INSERT INTO enm_apache_srv_unavail (siteid,date,uriid,num) VALUES (%d,'%s',%d,%d)",
                           $siteId,$date,$r_idMap->{$uri},$count) )
            or die "Failed to insert into enm_apache_srv_unavail";
    }

    my @appPaths = keys %{$self->{'data'}->{'app_counts'}};
    if ( $#appPaths > -1 ) {
        my $r_appPathIds = getIdMap($dbh, "enm_ui_app_names", "id", "name", \@appPaths);
        dbDo($dbh, "DELETE FROM enm_ui_app WHERE siteid = $siteId AND date = '$date'")
            or die "Failed to delete from enm_ui_app";
        while ( my ($appPath,$count) = each %{$self->{'data'}->{'app_counts'}} ) {
            my $userCount = '\N';
            if ( exists $self->{'data'}->{'app_users'}->{$appPath} ) {
                my @users = keys %{$self->{'data'}->{'app_users'}->{$appPath}};
                $userCount = $#users + 1;
            }
            dbDo($dbh, sprintf("INSERT INTO enm_ui_app (siteid,date,uiappid,num,n_users) VALUES (%d,'%s',%d,%d,%s)",
                               $siteId,$date,$r_appPathIds->{$appPath},$count,$userCount) )
                or die "Failed to insert into enm_ui_app";
        }
    }
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

    $self->{'ignoreHash'} = {};
    foreach my $type ( @IGNORE_TYPE_LIST ) {
        $self->{'ignoreHash'}->{$type} = 1;
    }

    $self->{'valid_methods'} = {};
    foreach my $method ( @VALID_METHODS ) {
        $self->{'valid_methods'}->{$method} = 1;
    }

    $self->{'termination_words'} = getTermHash();

    $self->{'site'} = $r_cliArgs->{'site'};
    $self->{'siteId'} = $r_cliArgs->{'siteId'};
    $self->{'date'} = $r_cliArgs->{'date'};
    $self->{'analysisDir'} = $r_cliArgs->{'analysisDir'};

    if ( exists $r_incr->{'misc::ApacheAccessLog'} ) {
        $self->{'data'} = $r_incr->{'misc::ApacheAccessLog'};
        if ( ! exists $self->{'data'}->{'service_unavailable'} ) {
            $self->{'data'}->{'service_unavailable'} = {};
        }
        if ( ! exists $self->{'data'}->{'app_counts'} ) {
            $self->{'data'}->{'app_counts'} = {};
        }
    } else {
        $self->{'data'} = {
            'index' => 0,
            'requestCounts' => {},
            'service_unavailable' => {},
            'app_counts' => {}
        };
        opendir DIR, $self->{'analysisDir'} or die "Failed to open " . $self->{'analysisDir'};
        while ( my $fileName = readdir(DIR) ) {
            if ( $fileName =~ /^apache_access.log\.\d+\.gz/ ) {
                unlink( $self->{'analysisDir'} . "/" . $fileName);
            }
        }
        closedir DIR;
    }

    # Point at the next output file
    $self->{'data'}->{'index'}++;
    my $filename = sprintf("%s/apache_access.log.%03d.gz", $self->{'analysisDir'}, $self->{'data'}->{'index'});
    open my $fh, ">:gzip", $filename or die "Cannot open $filename: $!";
    $self->{'outFh'} = $fh;

    # Now figure out the hosts where httpd is running
    my $r_serverMap = enmGetServiceGroupInstances($r_cliArgs->{'site'},$r_cliArgs->{'date'},"httpd");
    my @subscriptions = ();
    foreach my $server ( keys %{$r_serverMap} ) {
        push @subscriptions, { 'server' => $server, 'prog' => 'httpd_access_log' };
    }

    # If there are no httpd instances then assume we're on cENM
    if ( $#subscriptions == -1 ) {
        push @subscriptions, { 'server' => '*', 'prog' => 'httpd_access_log' };
    }

    $self->{'count'} = 0;
    $self->{'fail'} = 0;

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $::DEBUG > 8 ) { print "misc::ApacheAccessLog $timestamp $messageSize\n"; }

    my $msg = $message;
    $msg =~ s/^\s*//;
    print {$self->{'outFh'}} $msg, "\n";

    $self->parseLogEntry($msg);
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}

sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( exists $self->{'invalid_methods'} ) {
        while ( my ($method,$count) = each %{$self->{'invalid_methods'}} ) {
            printf "WARN: ApacheAccessLog: Found %d invalid method %s\n", $count, $method;
        }
    }

    if ( $self->{'fail'} > 0 ) {
        printf "WARN: ApacheAccessLog failed to parse %d of %d entries\n", $self->{'fail'}, $self->{'count'};
    }

    if ( $::DEBUG > 4 ) { print Dumper("misc::ApacheAccessLog self", $self); }

    close $self->{'outFh'};

    $self->store($dbh);

    $r_incr->{'misc::ApacheAccessLog'} = $self->{'data'};
}

1;
