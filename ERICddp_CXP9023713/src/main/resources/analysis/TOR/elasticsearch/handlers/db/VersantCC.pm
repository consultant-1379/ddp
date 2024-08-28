package db::VersantCC;

use strict;
use warnings;

use Data::Dumper;

use StatsDB;
use StatsTime;
use EnmCluster;

#
# Internal functions
#
sub parseLogEntry($$$) {
    my ($self,$timestamp,$program,$message) = @_;

    # Not sure if the code below is matching program or message or
    # both, so "safest" option is to create a compatible string
    # which includes both
    my $logLine = '@' . $program . '@' . $message;

    my $ccLogType = undef;
    if ( $logLine =~ /[^_]backup_database.*[^_]backup_database.sh\s+started\s+at/i ) {
        #elsif ( $logLine =~ /backup_and_consistency_check_database.*Command\s+executed:.*[^_]backup_database.sh.*--db_name/i ) {
        # Log line that indicates the start of 'backup_database.sh' wrapper
        #  script by 'backup_and_consistency_check_database.sh' script
        $ccLogType = 'DB_BACKUP_SCRIPT_START_LOGLINE';
    }
    elsif ( $logLine =~ /[^_]backup_database.*done\s+successfully\s+at/i ) {
        # Log line that indicates the successful backup of the database
        $ccLogType = 'DB_BACKUP_SUCCESSFUL_LOGLINE';
    }
    elsif ( $logLine =~ /[^_]backup_database.*Performing\s+a\s+cleanup\s+before\s+terminating/i ) {
        # Log line that indicates the start of cleanup before the termination of
        #  'backup_database.sh'
        $ccLogType = 'DB_BACKUP_CLEANUP_BEFORE_TERMINATION';
    }
    elsif ( $logLine =~ /backup_and_consistency_check_database.*[^_]backup_database.sh\s+did\s+not\s+complete/i ) {
        # Log line that indicates the termination of DB backup due to failure
        $ccLogType = 'DB_BACKUP_FAILURE_LOGLINE';
    }
    elsif ( $logLine =~ /[^_]consistency_check_database.*Start\s+consistency\s+check\s+at/i ) {
        #elsif ( $logLine =~ /backup_and_consistency_check_database.*Command\s+executed:.*[^_]consistency_check_database.sh.*--db_name/i ) {
        # Log line that indicates the start of 'consistency_check_database.sh'
        #  wrapper script by 'backup_and_consistency_check_database.sh' script
        $ccLogType = 'CC_SCRIPT_START_LOGLINE';
    }
    elsif ( $logLine =~ /[^_]consistency_check_database.*Running\s+(?:Versant\s+database\s+|)consistency\s+check\s+for/i ) {
        # Log line that indicates the start of Versant CC by
        #  'consistency_check_database.sh' wrapper script
        $ccLogType = 'VERSANT_CC_START_LOGLINE';
    }
    elsif ( $logLine =~ /[^_]consistency_check_database.*consistency\s+check\s+is\s+successful\s+for/i ) {
        # Log line that indicates that the Versant CC is successfull and
        #  no inconsistencies have been detected
        $ccLogType = 'VERSANT_CC_CONSISTENT_LOGLINE';
    }
    elsif ( $logLine =~ /[^_]consistency_check_database.*Inconsistencies\s+detected\s+in.*for\s+database/i && $logLine !~ /No\s+.*Inconsistencies/i ) {
        # Log line that indicates that Versant CC has found some inconsistencies
        $ccLogType = 'VERSANT_CC_INCONSISTENT_LOGLINE';
    }
    elsif ( $logLine =~ /[^_]consistency_check_database.*Running\s+DPS\s+consistency\s+check\s+for/i ) {
        # Log line that indicates the start of DPS CC by
        #  'consistency_check_database.sh' wrapper script
        $ccLogType = 'DPS_CC_START_LOGLINE';
    }
    elsif ( $logLine =~ /(?:\@\@|\@UNKNOWN\@|\@dps_consistency_check_run\@).*Consistency\s+check\s+passed\s+and\s+no\s+inconsistencies\s+detected/i ) {
        # Log line that indicates that the DPS CC is successfull and
        #  no inconsistencies have been detected
        $ccLogType = 'DPS_CC_CONSISTENT_LOGLINE';
    }
    elsif ( $logLine =~ /(?:\@\@|\@UNKNOWN\@|\@dps_consistency_check_run\@).*Inconsistencies\s+have\s+been\s+detected:/i ) {
        # Log line that indicates that DPS CC has found some inconsistencies
        $ccLogType = 'DPS_CC_INCONSISTENT_LOGLINE';
    }
    elsif ( $logLine =~ /[^_]consistency_check_database.*(?:Inconsistencies.*found.*already\s+suspect\s+Archive|New\s+inconsistencies.*found).*Creating\s+archive/i ) {
        # Log line that indicates that Overall CC has found some inconsistencies
        $ccLogType = 'OVERALL_CC_INCONSISTENT_LOGLINE';
    }
    elsif ( $logLine =~ /[^_]consistency_check_database.*Performing\s+a\s+cleanup\s+before\s+terminating/i ) {
        # Log line that indicates the start of cleanup before the termination of
        #  'consistency_check_database.sh'
        $ccLogType = 'CC_CLEANUP_BEFORE_TERMINATION';
    }
    elsif ( $logLine =~ /backup_and_consistency_check_database.*[^_]consistency_check_database.sh\s+did\s+not\s+complete/i ) {
        # Log line that indicates the termination of Versant CC due to failure
        $ccLogType = 'CC_FAILURE_LOGLINE';
    }
    elsif ( $logLine =~ /backup_and_consistency_check_database.*backup_and_consistency_check_database.sh\s+exited\s+as\s+timeout\s+reached/i ) {
        # Log line that indicates the termination of CC due to timeout
        $ccLogType = 'BCC_TIMEOUT_LOGLINE';
    }
    elsif ( $logLine =~ /backup_and_consistency_check_database.*backup_and_consistency_check_database.sh\s+failed/i ) {
        # Log line that indicates the termination of CC due to other unknown failure
        $ccLogType = 'BCC_FAILURE_LOGLINE';
    }

    if ( defined $ccLogType ) {
        if ( $::DEBUG > 6 ) { print "db::VersantCC parseLogEntry ccLogType=$ccLogType timestamp=$timestamp program=$program message=$message\n"; }
        $self->{'data'}->{'ccLogTypes'}->{$timestamp} = $ccLogType;
    }
}

sub getCcStatuses($) {
    my $ccLogTypes = shift;

    my %ccStatuses = ();
    my @timestamps = sort keys %{$ccLogTypes};
    for (my $i = 0; $i < scalar @timestamps; $i++) {
        if ( $ccLogTypes->{$timestamps[$i]} eq 'DB_BACKUP_SCRIPT_START_LOGLINE' ) {
            if ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'DB_BACKUP_SUCCESSFUL_LOGLINE' ) {
                $ccStatuses{$timestamps[++$i]} = 'DB Backup@Successful';
            }
            elsif ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'BCC_TIMEOUT_LOGLINE' ) {
                $ccStatuses{$timestamps[++$i]} = 'DB Backup@Executed but Timed Out';
            }
            elsif ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} =~ /^(?:DB_BACKUP_FAILURE_LOGLINE|BCC_FAILURE_LOGLINE)$/ ) {
                $ccStatuses{$timestamps[++$i]} = 'DB Backup@Executed but Failed/Terminated';
            }
            elsif ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'DB_BACKUP_CLEANUP_BEFORE_TERMINATION' ) {
                $i++;
                if ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'BCC_TIMEOUT_LOGLINE' ) {
                    $ccStatuses{$timestamps[++$i]} = 'DB Backup@Executed but Timed Out';
                }
                elsif ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} =~ /^(?:DB_BACKUP_FAILURE_LOGLINE|BCC_FAILURE_LOGLINE)$/ ) {
                    $ccStatuses{$timestamps[++$i]} = 'DB Backup@Executed but Failed/Terminated';
                }
                else {
                    $ccStatuses{$timestamps[$i]} = 'DB Backup@Executed but Failed/Terminated';
                }
            }
            else {
                $ccStatuses{$timestamps[$i]} = 'DB Backup@Executed but Failed/Terminated';
            }
        }
        elsif ( $ccLogTypes->{$timestamps[$i]} eq 'CC_SCRIPT_START_LOGLINE' ) {
            if ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'CC_CLEANUP_BEFORE_TERMINATION' ) {
                $i++;
            }

            if ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'BCC_TIMEOUT_LOGLINE' ) {
                $ccStatuses{$timestamps[++$i]} = 'Overall Status@Executed but Timed Out';
            }
            elsif ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} =~ /^(?:CC_FAILURE_LOGLINE|BCC_FAILURE_LOGLINE)$/ ) {
                $ccStatuses{$timestamps[++$i]} = 'Overall Status@Executed but Failed/Terminated';
            }
        }
        elsif ( $ccLogTypes->{$timestamps[$i]} eq 'VERSANT_CC_START_LOGLINE' ) {
            if ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'VERSANT_CC_CONSISTENT_LOGLINE' ) {
                $ccStatuses{$timestamps[++$i]} = 'Versant CC@Consistent';
            }
            elsif ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'VERSANT_CC_INCONSISTENT_LOGLINE' ) {
                $ccStatuses{$timestamps[++$i]} = 'Versant CC@Not Consistent';
            }
            else {
                if ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'CC_CLEANUP_BEFORE_TERMINATION' ) {
                    $i++;
                }

                if ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'BCC_TIMEOUT_LOGLINE' ) {
                    $ccStatuses{$timestamps[++$i]} = 'Versant CC@Executed but Timed Out';
                }
                elsif ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} =~ /^(?:CC_FAILURE_LOGLINE|BCC_FAILURE_LOGLINE)$/ ) {
                    $ccStatuses{$timestamps[++$i]} = 'Versant CC@Executed but Failed/Terminated';
                }
                else {
                    $ccStatuses{$timestamps[$i]} = 'Versant CC@Executed but Failed/Terminated';
                }
            }
        }
        elsif ( $ccLogTypes->{$timestamps[$i]} eq 'DPS_CC_START_LOGLINE' ) {
            if ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'DPS_CC_CONSISTENT_LOGLINE' ) {
                $ccStatuses{$timestamps[++$i]} = 'DPS CC@Consistent';
            }
            elsif ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'DPS_CC_INCONSISTENT_LOGLINE' ) {
                $ccStatuses{$timestamps[++$i]} = 'DPS CC@Not Consistent';
            }
            else {
                if ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'CC_CLEANUP_BEFORE_TERMINATION' ) {
                    $i++;
                }

                if ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} eq 'BCC_TIMEOUT_LOGLINE' ) {
                    $ccStatuses{$timestamps[++$i]} = 'DPS CC@Executed but Timed Out';
                }
                elsif ( $i < $#timestamps && $ccLogTypes->{$timestamps[$i+1]} =~ /^(?:CC_FAILURE_LOGLINE|BCC_FAILURE_LOGLINE)$/ ) {
                    $ccStatuses{$timestamps[++$i]} = 'DPS CC@Executed but Failed/Terminated';
                }
                else {
                    $ccStatuses{$timestamps[$i]} = 'DPS CC@Executed but Failed/Terminated';
                }
            }
        }
        elsif ( $ccLogTypes->{$timestamps[$i]} eq 'OVERALL_CC_INCONSISTENT_LOGLINE' ) {
            $ccStatuses{$timestamps[$i]} = 'Overall Status@Not Consistent';
        }
    }

    if ( $::DEBUG > 6 ) { print Dumper("db::VersantCC  getCcStatuses", \%ccStatuses); }

    return \%ccStatuses;
}

# Subroutine to store the parsed health-check related data under database
sub storeHealthCheckData($$$$) {
    my $dbh           = shift;
    my $siteId        = shift;
    my $date          = shift;
    my $versantChecks = shift;

    # Get Versant health-check 'Type' -> 'ID' mapping
    my @versantCheckTypes = keys %{$versantChecks};
    my $hcType2IdMap = getIdMap($dbh, "enm_versant_health_check_types", "id", "check_type", \@versantCheckTypes );

    my $bcpFile = getBcpFileName("enm_versant_health_checks");
    open (BCP, "> $bcpFile");

    # Write DB consistency-check related data to 'enm_versant_health_checks.bcp' file
    my $dbConsCheckId = $hcType2IdMap->{'Consistency Check'};
    my %uniqueDbCheckStatus = ();
    $uniqueDbCheckStatus{$_} = 1    foreach(values %{$versantChecks->{'Consistency Check'}});
    my @dbCheckStatus = keys %uniqueDbCheckStatus;
    # Get DB consistency-check status 'Type' -> 'ID' mapping
    my $dbCheckStatusType2IdMap = getIdMap($dbh, "enm_versant_health_status_types", "id", "status_type", \@dbCheckStatus );
    foreach my $timestamp (sort keys %{$versantChecks->{'Consistency Check'}}) {
        my $dbConsCheckStatusId = $dbCheckStatusType2IdMap->{$versantChecks->{'Consistency Check'}->{$timestamp}};
        $timestamp =~ s/^(\d{4,4}-\d{2,2}-\d{2,2})[T\s](\d{2,2}:\d{2,2}:\d{2,2}).*$/$1 $2/;
        print BCP "$siteId\t$timestamp\t$dbConsCheckId\t$dbConsCheckStatusId\n";
    }
    close BCP;

    # Add the Versant health-check related data to database
    # Delete the old stats from 'enm_versant_health_checks' table for the given date
    dbDo( $dbh, sprintf("DELETE FROM enm_versant_health_checks WHERE siteid = %d AND checkid = %d AND time BETWEEN '%s 00:00:00' AND '%s 23:59:59'",
                        $siteId, $dbConsCheckId, $date, $date) )
        or die "Failed to remove old data from 'enm_versant_health_checks' table";

    # Populate the 'enm_versant_health_checks' DB table with the new Versant health-check
    #  related data
    dbDo( $dbh, "LOAD DATA LOCAL INFILE '$bcpFile' INTO TABLE enm_versant_health_checks" )
        or die "Failed to load new data from '$bcpFile' file to 'enm_versant_health_checks' table";
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

    $self->{'srvIdMap'} = enmClustHostSrv($self->{'site'}, $self->{'date'}, "DB");


    if ( exists $r_incr->{'db::VersantCC'} ) {
        $self->{'data'} = $r_incr->{'db::VersantCC'};
    } else {
        $self->{'data'} = {
            'ccLogTypes' => {}
        };
    }

    my @subscriptions = ();
    foreach my $server ( keys %{$self->{'srvIdMap'}} ) {
        push @subscriptions, { 'server' => $server, 'prog' => '*' };
    }

    if ( $::DEBUG > 5 ) { print Dumper("db::VersantCC::init subscriptions",\@subscriptions) ; }

    return \@subscriptions;
}

sub handle($$$$$$$) {
    my ($self,$timestamp,$host,$program,$severity,$message,$messageSize) = @_;

    if ( $program !~ /versant|consistency_check_database|backup_database|dps_consistency_check_run/ ) {
        return;
    }

    if ( $::DEBUG > 7 ) { print "db::VersantCC::handle timestamp=$timestamp program=$program message=$message\n"; }
    $self->parseLogEntry($timestamp,$program,$message);
}

sub handleExceeded($$$) {
    my ($self,$host,$program) = @_;
}


sub done($$$) {
    my ($self,$dbh,$r_incr) = @_;

    if ( $::DEBUG > 5 ) { print Dumper("db::VersantCC::done self", $self); }

    my %versantChecks =  ( 'Consistency Check' => getCcStatuses($self->{'data'}->{'ccLogTypes'}) );
    if ( scalar $versantChecks{'Consistency Check'} ) {
        storeHealthCheckData($dbh,$self->{'siteId'},$self->{'date'},\%versantChecks);
    }

    $r_incr->{'db::VersantCC'} = $self->{'data'};
}


1;
