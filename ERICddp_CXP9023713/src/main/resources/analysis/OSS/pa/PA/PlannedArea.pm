package PlannedArea;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT = qw(&countMo &storeJobId &getJobName &getStartTime &getEndTime &updatePlannedActivationsHash &parseLvLog &storePaActivation &getPaImportDetails &storeImportDetailsValues &storeImportValues &getImportValues &getMoIds &getPaImports &parseImportPerfLog &readMoCount &getImportId );

########################################
# LIBRARIES
########################################
use strict;
use Time::Local;
use Getopt::Long;
use Data::Dumper;
use DBI;
use StatsDB;
use StatsTime;

########################################
# SUBROUTINES 
########################################

#=======================================
# STEP 1. EXTRACT VALUES FROM LVLOG
#=======================================

# This subroutine counts the number of MOs per Activation ID
# A hash of ActivationId-MoNum is returned
sub countMo {
    my ($lvlog,$DEBUG) = @_;

    # Declare variables
    my ($line,$task,$op,$actid);
    my @lines;
    my %mocount;

    # Slurp input file
    open LVLOG, "<$lvlog";
    @lines = <LVLOG>;
    close LVLOG;

    # Parse input file array
    foreach $line (@lines) {
        if(($task,$op,$actid) = $line =~ /\"(cms_nead_seg|cms_snad_reg|wran_pca?)\" .* \"(MODIFY|UPDATE|DELETE?)\" \"(\d*)\"/){
            # Init count value for actid if undefined
            if($mocount{$actid}{'monum'} eq undef) {
                $mocount{$actid}{'monum'}=0;
                # Otherwise increment counter
            }else{
                $mocount{$actid}{'monum'}++;
            }
        }
    }

    # Debug output
    if($DEBUG > 8){
        foreach my $id (%mocount){
            if($id =~ /^\d*$/) { print "ID: $id MO Num: $mocount{$id}{'monum'}\n"; }
        }
    }
    return %mocount;
}

# This subroutine parses the lvlog and returns a hash of 
# ActivationId-JobId pairs
sub storeJobId {
    my ($lvLog) = $_[0];
    my %myActId2JobId;

    my @mylist = `egrep "Job ID.* Activity ID" $lvLog | sed 's/.*Job ID :/JobID:/' | sed 's/Activity Name :.*//' | sed 's/ Job Name :.* Activity ID :/ ActivityID:/' | awk '{print \$2,\$4}'`;
    foreach my $line (@mylist) {
        my ($jobId, $actId) = $line =~ /(\d+) (\d+)/;
        $myActId2JobId{$actId}{'JobId'}=$jobId;
    }
    return %myActId2JobId;
}

# This subroutine parse the lvlog and extracts the Job Name 
# for a specified Activation ID
sub getJobName {
    my ($actId, $lvLog) = @_;

    # Get required line
    my $myLine = `egrep "Activate Planned Configuration" $lvLog|egrep $actId |egrep "Job ID"| egrep "Activity ID"`;

    # Get Job Name
    my ($myJobName) = $myLine =~ /Activate Planned Configuration : ([^\"]*) Job Type .*$/;
    return $myJobName;
}

# This subroutine parses the lvlog and extracts the Start Time 
# of a PA Activation for a specified Job ID
sub getStartTime {
    my ($myJobId, $lvLog) = @_;

    # Get required line
    my $myLine = `egrep "Activate Planned Configuration" $lvLog |egrep $myJobId | egrep Started`;

    # Get Start Time
    my ($myStartTime) = $myLine =~ /^(\S+ \S+)/;

    return $myStartTime;
}

# This subroutine parses the lvlog and extracts the End Time 
# of a PA Activation for a specified Job ID
sub getEndTime {
    my ($myJobId, $lvLog) = @_;

    # Get required line
    my $myLine = `egrep "Activate Planned Configuration" $lvLog |egrep $myJobId | egrep "Failed|Completed"`;

    # Get End Time
    my ($myEndTime) = $myLine =~ /^(\S+ \S+)/;

    return $myEndTime
}

#=======================================
# STEP 2. ADD DATA TO HASH ARRAY
#=======================================

# This subroutine takes in a list of parameters and returns a hash
# containing the parameters
sub updatePlannedActivationsHash {
    my ($jobId,$actId,$mySiteId,$start,$end,$pa,$result,$mocount,$type,%myPaActivations) = @_;

    $myPaActivations{$jobId}{'actid'}=$actId;
    $myPaActivations{$jobId}{'siteid'}=$mySiteId;
    $myPaActivations{$jobId}{'start'}=$start;
    $myPaActivations{$jobId}{'end'}=$end;
    $myPaActivations{$jobId}{'pa'}=$pa;
    $myPaActivations{$jobId}{'result'}=$result;
    $myPaActivations{$jobId}{'mocount'}=$mocount;
    $myPaActivations{$jobId}{'type'}=$type;

    return %myPaActivations;
}

# This subroutine takes in a list of parameters and returns a hash
# containing the parameters
sub updatePlannedActivationPcaHash {
    my ($jobId,$numActions,$tTotal,$tAlgo,$tReadActions,$tUnPlan,$numTxCommit,$tTxCommit,$numJmsSend,$tJmsSend,%myPaActivationPca) = @_;

    $myPaActivationPca{$jobId}{'numActions'}=$numActions;
    $myPaActivationPca{$jobId}{'tTotal'}=$tTotal;
    $myPaActivationPca{$jobId}{'tAlgo'}=$tAlgo;
    $myPaActivationPca{$jobId}{'tReadActions'}=$tReadActions;
    $myPaActivationPca{$jobId}{'tUnPlan'}=$tUnPlan;
    $myPaActivationPca{$jobId}{'numTxCommit'}=$numTxCommit;
    $myPaActivationPca{$jobId}{'tTxCommit'}=$tTxCommit;
    $myPaActivationPca{$jobId}{'numJmsSend'}=$numJmsSend;
    $myPaActivationPca{$jobId}{'tJmsSend'}=$tJmsSend;

    return %myPaActivationPca;
}

# This subroutine takes in a list of parameters and returns a hash
# containing the parameters
sub updatePlannedActivationContentHash {
    my ($jobId,$moid,$created,$deleted,$updated,%myPaActivationContent) = @_;

    $myPaActivationContent{$jobId}{'moid'}=$moid;
    $myPaActivationContent{$jobId}{'created'}=$created;
    $myPaActivationContent{$jobId}{'deleted'}=$deleted;
    $myPaActivationContent{$jobId}{'updated'}=$updated;

    return %myPaActivationContent;
}

# This subroutine takes in a list of parameters and returns a hash
# containing the parameters
sub updatePlannedActivationPcaActionsHash {
    my ($jobId,$action,$moid,$tTotal,$nTotal,$tFindMo,$nFindMo,$tCsCall,$nCsCall,$tGetPlan,$nGetPlan,%myPaActivationPcaActions);

    $myPaActivationPcaActions{$jobId}{'action'}=$action;
    $myPaActivationPcaActions{$jobId}{'moid'}=$moid;
    $myPaActivationPcaActions{$jobId}{'tTotal'}=$tTotal;
    $myPaActivationPcaActions{$jobId}{'nTotal'}=$nTotal;
    $myPaActivationPcaActions{$jobId}{'tFindMo'}=$tFindMo;
    $myPaActivationPcaActions{$jobId}{'nFindMo'}=$nFindMo;
    $myPaActivationPcaActions{$jobId}{'tCsCall'}=$tCsCall;
    $myPaActivationPcaActions{$jobId}{'nCsCall'}=$nCsCall;
    $myPaActivationPcaActions{$jobId}{'tGetPlan'}=$tGetPlan;
    $myPaActivationPcaActions{$jobId}{'nGetPlan'}=$nGetPlan;

    return %myPaActivationPcaActions;
}

#=======================================
# STEP 3. UPDATE DATABASE WITH VALUES
#=======================================

# This subroutine parses the lvlog.log and extracts information 
# relating to Planned Area activations
sub parseLvLog {
    my ($lvLog, $siteId, %myActId2JobId, $DEBUG, %paActivations) = @_; # Read input parameteres

    # Declare variables
    my ($actId,$jobId,$startTime,$result,$moNum,$plannedArea,$endTime,$date);
    my ($time,$mc,$userId,$cmd,$actId,$startDateTime,$endDateTime);
    my ($typeIdentifier,$typeSeparator,$actType,$durationMs,$tmpActId,$tmpJobId);
    my %ActJobId;

    #=========================================
    # 1. Extract ActId-JobId Pair from LvLog
    #=========================================

    if ( $DEBUG > 8 ) { print Dumper %ActJobId; }

    # Check if LVLOG File exists
    if ( -r $lvLog ) { # LOOP 1 START
        #=========================================
        # 2. Count MOs in LVLOG and save to hash
        #=========================================
        my %mocount = countMo($lvLog);

        # Set regex used for extracting relevant lines from lvlog
        my $re = "(ACTIVATE_PLANNED_CONFIGURATION|COMMAND \"cms_nead_seg\" .*\"Plan Name)";

        #=========================================
        # 3. Extract LVLOG Values
        #=========================================
        open LV_LOG, "<$lvLog" or return "Cannot open command log $lvLog";

        # Iterate thru line in LVLOG file
        while ( my $line = <LV_LOG> ) { # LOOP 2 START
            if ( $line =~ /$re/ ) { # LOOP 3 START
                # Check if LVLOG entry is for PA Activation
                if ( $line =~ /ACTIVATE_PLANNED_CONFIGURATION/ ) { # LOOP 4 START
                    my $starttime = time;

                    # Read in LVLOG entry parameters
                    ($date,$time, $mc, $userId, $cmd, $actId, $result, $typeIdentifier, $typeSeparator, $actType) = $line =~ /^(\S+) (\S+) COMMAND \"([^\"]*)\".+\"([^\"]*)\" \"([^\"]*)\" \"(\d*)\" \".*\" \w* \".*\" \"(\w*)\" .*Activation (Type|Scheme)(: |=)(\w*).*\"$/;

                    # Check if valid Activation Type - currently only "Activation Scheme"(old) and "Activation Type"(new) are recognised
                    ##########################    
                    # Activation Type Format
                    ##########################    
                    if ( $typeIdentifier =~ /Type/ ) { # LOOP 5 START
                        ###########################################    
                        # Activation Type: Plan or NetworkElement
                        ###########################################    
                        if(($actType eq 'Plan') || ($actType eq 'NetworkElement')) { # LOOP 6 START
                            ($date,$time,$mc,$userId,$cmd,$actId,$result,$plannedArea,$startTime,$endTime,$durationMs,$actType,$moNum) = 
                            $line =~ /^(\S+) (\S+) COMMAND \"([^\"]*)\".+\"([^\"]*)\" \"([^\"]*)\" \"(\d*)\" \".*\" \w* \".*\" \"(\w*)\" .*Planned Area=([^\"]*)\,.*Start Time=\"\S+ (\S+)\".*End Time=\"\S+ (\S+)\".*Duration=(\d*).*Activation Type=(\w*).*Number of MOs\/Actions=(\d*)\"$/; 

                            # Set actType variable
                            if ($actType eq 'Plan') { $actType = "pca"; }
                            if ($actType eq 'NetworkElement') { $actType = "ne"; }

                            if ( $DEBUG > 8 ) { 
                                print "Date: $date\nTime: $time\nMC: $mc\nUser ID: $userId\nCommand: $cmd\nActivation ID: $actId\nResult: $result\nPlanned Area: $plannedArea\n";
                                print "Start Time: $startTime\nEnd Time: $endTime\nDuration: $durationMs\nActivation Type: $actType\nMO Number: $moNum\n";
                            }

                            ###########################################    
                            # Activation Type: System
                            ###########################################    
                        } elsif($actType eq 'System') { # LOOP 6 CONTINUE

                            # Extract required variables from lvlog line
                            ($date,$time,$mc,$userId,$cmd,$actId,$result,$plannedArea,$startTime,$endTime,$durationMs,$actType) = $line =~ /^(\S+) (\S+) COMMAND \"([^\"]*)\".+\"([^\"]*)\" \"([^\"]*)\" \"(\d*)\" \".*\" \w* \".*\" \"(\w*)\" .*Planned Area=([^\"]*)\,.*Start Time=\"\S+ (\S+)\".*End Time=\"\S+ (\S+)\".*Duration=(\d*).*Activation Type=(\w*).*\"$/;

                            # Set activation type
                            $actType = "system"; 

                            # Get MO count
                            $moNum = $mocount{$actId}{'monum'};

                            # Get Job ID
                            $jobId=$myActId2JobId{$actId}{'JobId'};

                            if ( $DEBUG > 8 ) { 
                                print "Date: $date\nTime: $time\nMC: $mc\nUser ID: $userId\n";
                                print "Command: $cmd\nActivation ID: $actId\nResult: $result\n";
                                print "Planned Area: $plannedArea\nStart Time: $startTime\n";
                                print "End Time: $endTime\nDuration: $durationMs\n";
                                print "Activation Type: $actType\nMO Number: $moNum\n";
                            }

                        } else {
                            print "plannedArea - parseLvLog: ERROR - Invalid Activation Type: $actType\n"; 
                        } # LOOP 6 END

                        ###########################    
                        # Activation Scheme Format 
                        ###########################    
                    } elsif ( $typeIdentifier =~ /Scheme/ ) { # LOOP 5 CONTINUE

                        if ( $DEBUG > 8 ) { print "\nSCHEME"; }

                        # Extract required variable from lvlog line
                        ($date,$time,$mc,$userId,$cmd,$actId,$result,$actType) =
                        $line =~ /^(\S+) (\S+) COMMAND \"([^\"]*)\".+\"([^\"]*)\" \"([^\"]*)\" \"(\d*)\" \".*\" \w* \".*\" \"(\w*)\" .*Activation Scheme: ([^\"]*).*\"$/;

                        # Set actType variable value
                        if ($actType eq 'Plan') { $actType = "pca"; }
                        if ($actType eq 'NetworkElement') { $actType = "ne"; }
                        if ($actType eq 'System') { $actType = "system"; }

                        # Get MO number
                        $moNum = $mocount{$actId}{'monum'};

                        # Get JobId
                        $jobId=$myActId2JobId{$actId}{'JobId'};

                        # Get PA Job Name
                        $plannedArea=getJobName($actId,$lvLog);

                        # Get Job Start & End times
                        $startTime=getStartTime($jobId, $lvLog);
                        $endTime=getEndTime($jobId, $lvLog);

                    } else {
                        return "plannedArea - parseLvLog: ERROR - Invalid Activation Type \"$typeIdentifier\" in $lvLog\n$line\n"; 
                    } # LOOP 5 END

                    # Connect to DDP Database
                    #my $dbh = connect_db() or return "Error connecting to database $ENV{'STATS_DB'}\n";
                    # Get Site ID
                    #my $siteId = getSiteId($dbh,$site);
                    #($siteId > -1 ) or return "Failed to get siteid for $site";

                    # Set correct DateTime format
                    $startTime =~ s/\./\:/;
                    if ( $startTime !~ /\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/ ) { 
                        $startDateTime="$date $startTime"; 
                    } else {  
                        $startDateTime=$startTime; 
                    }
                    $startDateTime =~ s/\:\d{3}$//;

                    $endTime =~ s/\./\:/;
                    if ( $endTime !~ /\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/ ) { 
                        $endDateTime="$date $endTime"; 
                    } else {
                        $endDateTime=$endTime; 
                    }
                    $endDateTime =~ s/\:\d{3}$//;

                    if ( $DEBUG > 8 ) { 
                        print "Site ID: $siteId\nJob ID: $jobId\nDate: $date\nTime: $time\n";
                        print "MC: $mc\nUser ID: $userId\nCommand: $cmd\nActivation ID: $actId\n";
                        print "Result: $result\nActivation Type: $actType\nMO Number: $moNum\n";
                        print "Start Time: $startDateTime\nEnd Time: $endDateTime\n";
                        print "PA: $plannedArea\n";
                    }

                    # Update paActivations hash
                    %paActivations = updatePlannedActivationsHash($jobId,$actId,$siteId,$startDateTime,$endDateTime,$plannedArea,$result,$moNum,$actType,%paActivations);

                } elsif ( $line  =~ /COMMAND \"cms_nead_seg\" .*Plan Name = (\S+)Name: ([^"]+)/ ) { # LOOP 4 CONTINUE

                    # Get required variable values
                    my ($planName, $info) = ($1,$2);
                    ($actId) = $line =~ /\"(\d+)\"/;

                    # Get jobId
                    $jobId=$myActId2JobId{$actId}{'JobId'};

                    if ( defined $jobId ) {
                        if ( ! exists $paActivations{$jobId}{'content'} ) {
                            $paActivations{$jobId}{'content'} = [];
                            $paActivations{$jobId}{'type'} = 'system';
                        }

                        # Single records per MO, so no splitting out
                        my ( $fdn, $op ) = $info =~ /(\S+) Value: ([A-Z]+)/;

                        if ( $DEBUG > 8 ) { print "parseSystemAndCmdLog: planName=$planName fdn=$fdn, op=$op\n"; }

                        # Ignore "SUCCESS" ops
                        if ( $op ne "SUCCESS" ) {
                            my @change = ( $fdn, $op );
                            push @{$paActivations{$jobId}{'content'}}, \@change;
                        }
                    } else {
                        print "\nWARN: Unable to determine activationId for $line";
                    }

                } # LOOP 4 END

            }
        }
    } else { 
        print "DID NOT PARSE LVLOG\n"; 
    }

    if ($DEBUG > 8) { print Dumper %paActivations; }

    return %paActivations;
}

# This subroutine stores the PA Activations to the MYSQL database
sub storePaActivation {
    my ($siteId,$sqlDate,$DEBUG,%paActivations) = @_;

    # Create BCP file
    my $pa_activation_file = "/var/tmp/pa_activation.$$.bcp";

    # Enable DB debugging
    if ( $DEBUG > 4 ) { setStatsDB_Debug($DEBUG); }

    # Create BCP files
    open(PA_ACTIVATION, ">>$pa_activation_file");

    # Write data to files
    foreach my $jobId ( keys %paActivations ) {
        print PA_ACTIVATION '\N' . "|$paActivations{$jobId}{'siteid'}|$paActivations{$jobId}{'start'}|";
        print PA_ACTIVATION "$paActivations{$jobId}{'end'}|$paActivations{$jobId}{'pa'}|";
        print PA_ACTIVATION "$paActivations{$jobId}{'result'}|$paActivations{$jobId}{'mocount'}|";
        print PA_ACTIVATION "$paActivations{$jobId}{'type'}\n";
    }

    # Close BCP files
    close(PA_ACTIVATION);

    # Connect to DDP Database
    my $dbh = connect_db() || die "Error connecting to database $ENV{'STATS_DB'}\n";
    if ( $DEBUG > 6 ) { print "DBH: $dbh\n"; }

    # Delete existing entries for current date
    my $sqlDelete = "DELETE FROM pa_activation WHERE start BETWEEN '" . $sqlDate . " 00:00:00' AND '" . $sqlDate . " 23:59:59' AND siteid = " . $siteId ;

    if ($DEBUG > 6) { print "DELETE SQL: " . $sqlDelete . "\n"; }

    # Run the DELETE
    dbDo($dbh, $sqlDelete) or die "ERROR: Failed to clear data pa_activation for sqlDelete with statement " . $sqlDelete . "\n";

    # Generate sql command
    my $sqlInsert = "LOAD DATA LOCAL INFILE \'$pa_activation_file\' INTO TABLE pa_activation FIELDS TERMINATED BY '|'";

    # Run the INSERT
    dbDo($dbh, $sqlInsert) or die "ERROR: Failed to insert data into pa_activation with statement " . $sqlInsert . "\n";
    $dbh->disconnect;
    
    # Clean up temp files
    `rm -f $pa_activation_file`;
}

#=========================================
# IMPORT SUBROUTINES
#=========================================
# Store values to pa_import_details table
sub storeImportDetailsValues {
    my ($sqlDate,$siteid,$DEBUG,%importValues) = @_;
    my $pa_import_details_file = "/var/tmp/pa_import_details.$$.bcp";

    if($DEBUG > 8) { print Dumper("storeImportDetailsValues: %importValues", \%importValues); }

    # Create BCP files
    open(PA_IMPORT_DETAILS, ">>$pa_import_details_file");

    # Write data to files
    foreach my $jobId (keys %importValues) {
        print PA_IMPORT_DETAILS "$importValues{$jobId}{'paimportId'}|$importValues{$jobId}{'moId'}|";
        print PA_IMPORT_DETAILS "$importValues{$jobId}{'created'}|$importValues{$jobId}{'deleted'}|";
        print PA_IMPORT_DETAILS "$importValues{$jobId}{'updated'}\n";
    }

    # Close BCP files
    close(PA_IMPORT_DETAILS);

    if ($DEBUG > 8) { print STDOUT "PA_IMPORT_DETAILS: $pa_import_details_file\n"; }

    # Delete existing entries for current date
    my $sqlDelete = "DELETE FROM pa_import_details WHERE moid in (" .
    "SELECT id FROM pa_import WHERE start BETWEEN '" . $sqlDate . " 00:00:00' ".
    "AND '" . $sqlDate . " 23:59:59' AND siteid = " . $siteid . ")";

    if ($DEBUG > 6) { print "DELETE SQL: " . $sqlDelete . "\n"; }

    # Connect to DDP database
    my $dbh = connect_db();

    # Run the DELETE
    dbDo($dbh, $sqlDelete) or die "ERROR: Failed to clear data pa_import for sqlDelete with statement " . $sqlDelete . "\n";

    # Generate sql command
    my $sqlInsert = "LOAD DATA LOCAL INFILE \'$pa_import_details_file\' INTO TABLE pa_import_details FIELDS TERMINATED BY '|'";

    # Run the INSERT
    dbDo($dbh, $sqlInsert) or die "ERROR: Failed to insert data into pa_import_details with statement " . $sqlInsert . "\n";
    $dbh->disconnect;

    # Clean up temp files
    `rm -f $pa_import_details_file`;
}

# Store values to pa_import table
sub storeImportValues {
    my ($sqlDate,$siteid,$DEBUG,%importValues) = @_;
    my $pa_import_file = "/var/tmp/pa_import.$$.bcp";

    if($DEBUG > 8) { print Dumper("storeImportValues: %importValues", \%importValues); }

    # Create BCP files
    open(PA_IMPORT, ">>$pa_import_file");

    # Write data to files
    foreach my $jobId (keys %importValues) {
         '\N' . "|" . $sqlDate . "|" .
        print PA_IMPORT '\N' . "|$importValues{$jobId}{'siteId'}|$importValues{$jobId}{'start'}|$importValues{$jobId}{'end'}|";
        print PA_IMPORT "$importValues{$jobId}{'pa'}|$importValues{$jobId}{'file'}|$importValues{$jobId}{'numMo'}|$importValues{$jobId}{'error'}\n";
    }

    # Close BCP files
    close(PA_IMPORT);

    # Delete existing entries for current date
    my $sqlDelete = "DELETE FROM pa_import WHERE start BETWEEN '" . $sqlDate . " 00:00:00' AND '" . $sqlDate . " 23:59:59' AND siteid = " . $siteid ;

    if ($DEBUG > 6) { print "DELETE SQL: " . $sqlDelete . "\n"; }

    # Connect to db
    my $dbh = connect_db();

    # Run the DELETE
    dbDo($dbh, $sqlDelete) or die "ERROR: Failed to clear data pa_import for sqlDelete with statement " . $sqlDelete . "\n";

    # Generate sql command
    my $sqlInsert = "LOAD DATA LOCAL INFILE \'$pa_import_file\' INTO TABLE pa_import FIELDS TERMINATED BY '|'";

    # Run the INSERT
    dbDo($dbh, $sqlInsert) or die "ERROR: Failed to insert data into pa_import with statement " . $sqlInsert . "\n";
    $dbh->disconnect;

    # Clean up temp files
    `rm -f $pa_import_file`;
}

# Parse lvlog.log & importPerf.log to return hash containing values
# for import to pa_imports table
sub getImportValues{
    my ($siteId,$importPerfLog,$lvlog,$DEBUG) = @_;
    my %all_imports;

    #========================
    # Main functions
    my $updatedPerfLog = parseImportPerfLog($importPerfLog);
    my %imports = getPaImports($lvlog,$updatedPerfLog,$DEBUG);
    my %importDetails = getPaImportDetails($importPerfLog);
    my $r_moNameIds = getMoIds(%importDetails);

    foreach my $jobId ( keys %imports ) {
        $all_imports{$jobId}{'siteId'}=$siteId;
        $all_imports{$jobId}{'start'}=$imports{$jobId}{'StartTime'};
        $all_imports{$jobId}{'end'}=$imports{$jobId}{'EndTime'};

        $all_imports{$jobId}{'pa'}=$importDetails{$imports{$jobId}{'ActivityId'}}{'pa'};
        $all_imports{$jobId}{'file'}=$imports{$jobId}{'File'};
        $all_imports{$jobId}{'numMo'}=$imports{$jobId}{'moCount'};
        $all_imports{$jobId}{'error'}=$imports{$jobId}{'Error'};

        $all_imports{$jobId}{'moId'}=$r_moNameIds->{$importDetails{$imports{$jobId}{'ActivityId'}}{'mo'}};
        $all_imports{$jobId}{'created'}=$importDetails{$imports{$jobId}{'ActivityId'}}{'numCreates'};
        $all_imports{$jobId}{'deleted'}=$importDetails{$imports{$jobId}{'ActivityId'}}{'numDeletes'};
        $all_imports{$jobId}{'updated'}=$importDetails{$imports{$jobId}{'ActivityId'}}{'numUpdates'};
    }

    if ( $DEBUG > 8 ) { print Dumper("getImportValues: %all_imports", \%all_imports); }

    # Cleanup file originally created in parseImportPerfLog
    `rm -f $updatedPerfLog`;

    return %all_imports;
}

# This subroutine reads in values from a hash and returns
# a hash containing jobId-importID pairs
sub getImportId {
    my ($table,$DEBUG,%impArr) = @_;

    my $dbh = connect_db();

    foreach my $jobId ( keys %impArr ) {
        my $sql = "SELECT id from $table " . 
        "WHERE start=\"$impArr{$jobId}{'start'}\" " .
        "AND end=\"$impArr{$jobId}{'end'}\" " .
        "AND pa=\"$impArr{$jobId}{'pa'}\" " .
        "AND siteid=$impArr{$jobId}{'siteId'};";

        if ( $DEBUG > 8 ) { print "SQL: $sql\n"; }

        # Execute sql command to retrieve id from pa_import table
        my $sth = $dbh->prepare($sql);
        $sth->execute();
        my @paimportId = $sth->fetchrow_array();
        $impArr{$jobId}{'paimportId'}=$paimportId[0];
    }

    $dbh->disconnect;

    return %impArr;
}

# Return reference to hash containing MoName-Id pairs
sub getMoIds {
    my %impDetails = $_[0];

    my ($key,$subkey);
    my (@monames);

    foreach $key (keys %impDetails) {
        foreach $subkey (keys %{ $impDetails{$key}} ) {
            push(@monames, $impDetails{$key}{'mo'});
        }
    }

    my $dbh = connect_db();
    my $r_moNameIds = getIdMap($dbh, "mo_names", "id", "name", @monames );
    $dbh->disconnect;

    return $r_moNameIds;
}

# Parse lvlog and return hash containing PA Import data
sub getPaImports {
    my ($lvlog, $myimportperflog,$DEBUG) = @_;

    my %myImports;
    my ($date);
    my $pidlist="";

    # Get MO Count
    my %actIdMoCounts = readMoCount($myimportperflog,$DEBUG); # Input params: Updated Perf Log & Activity ID

    # Sample output from $cmd1
    # Date Time JobId JobName Operation ActivityId ActivityName
    # 2012-05-21 08:06.22 874299 delete_UtranCell_RNC08.xml ImportConfigurationFile 874300 ImportConfigurationFile:delete_UtranCell_RNC08.xml
    my @cmd=`egrep "Job ID.*Activity ID" $lvlog |egrep ImportConfigurationFile| sed 's/[a-zA-Z].*Job ID :/JobId:/g' |sed 's/ Activity IOR.*//g' | sed 's/ Job Owner :.* Activity ID :/ ActivityID:/g'|sed 's/Job Type :/JobType:/g' | sed 's/Job Name :/JobName:/g' | sed 's/Activity Name :/ActivityName:/g'|awk '{print \$1,\$2,\$4,\$6,\$8,\$10,\$12}'`;

    # Sample output from $cmd2
    # JobId Time JobStatus
    # 116428 13:00.14 Started
    # 116432 13:00.58 Completed
    foreach (@cmd){
        split;

        # Save PID to array
        $pidlist .= "$_[2]|";

        #$myImports{$_[2]}{'Date'} = $_[0];
        $date = $_[0];
        $myImports{$_[2]}{'JobName'} = $_[3];
        $myImports{$_[2]}{'Operation'} = $_[4];
        $myImports{$_[2]}{'ActivityId'} = $_[5];
        $myImports{$_[2]}{'ActivityName'} = $_[6];
        ($myImports{$_[2]}{'File'}) = $_[6] =~ /\:(.*)$/;

        # Get MoCount for Job ActivationID
        $myImports{$_[2]}{'moCount'} = $actIdMoCounts{$_[5]}{'moCount'};
    }
    # Set & execute command to get job start & end times and slurp output into array
    chop $pidlist;
    @cmd = `egrep \"\($pidlist\) .*New job status : (Started\|Completed\|Failed)" $lvlog | awk '{print \$16,\$2,\$NF}' |sed 's/\"//g'|egrep -i "Started|Failed|Completed" |sort -n`;

    # Save start & end times for relevant Job IDs to hash
    foreach (@cmd){
        split;
        $_[1] =~ s/\./\:/g;
        if($_[2] =~ /Started/){
            $myImports{$_[0]}{'StartTime'} = "$date $_[1]";
        } elsif($_[2] =~ /Completed|Failed/) {
            $myImports{$_[0]}{'EndTime'} = "$date $_[1]";
        } else {
            print "Error recognising time value - $_[2]\n";
        }
    }

    # Output hash contents
    if($DEBUG > 8) { print Dumper("getPaImports: \%myImports", \%myImports); }

    if($DEBUG > 8) {
        foreach my $val (keys %myImports) {
            my $actId = $myImports{$val}{'ActivityId'};
            print "ActId=$actId\n";
            print "Job ID: $val, Job Name: $myImports{$val}{'JobName'}, Operation: $myImports{$val}{'Operation'}, ";
            print "Activity ID: $myImports{$val}{'ActivityId'}, Activity Name: $myImports{$val}{'ActivityName'}";
            print "StartTime: $myImports{$val}{'StartTime'}, EndTime: $myImports{$val}{'EndTime'}, MO Count: $myImports{$val}{'moCount'}\n";
        }
    }

    return %myImports;
}

# Parse ImportPerf file to extract START & END times. Vals written to returned file
sub parseImportPerfLog {
    my ($file)=@_;

    # Declare variables
    my ($line,$fileprefix,$newfile,$outfile);
    my ($line1,$line2,$newline);
    my $count=0;

    # Create new file with required content for START|END times
    ($fileprefix) = $0 =~ /\/(\w+)$/;
    $newfile="/var/tmp/".$fileprefix."_new.$$.tmp";
    $outfile="/var/tmp/".$fileprefix."_out.$$.tmp";

    # Extract required data from importperf.log and export to new file
    `egrep \"\(START|END\)\" $file > $newfile`;

    # Open file in read mode
    open(FILE, "<$newfile");
    foreach $line (<FILE>) {
        chomp($line);

        # Append every 2nd line and write to new file
        if(($count%2) eq 0) {
            $line1 = $line;
            chomp($line1);
        } else {
            $line2 = $line;
            $newline = $line1."\@".$line2;
            `echo $newline >> $outfile`;
        }
        $count++;
    }
    close(FILE);

    # Remove temp file
    `rm -f $newfile`;

    return $outfile;
}

# This Subroutine returns a hash containing the actId-MoCount pairs
sub readMoCount {
    my ($myUpdatedPerfLog,$DEBUG) = @_;
    my %actIdMoCount;

    # Extract ActivityID, MoCount values to hash from updatedPerfLog file
    open(PERFLOG, "< $myUpdatedPerfLog");
    foreach my $myLine (<PERFLOG>) {
        chomp($myLine);
        my @linearr=split(/\@/,$myLine);

        # Set values for Activity ID & MoCount and save to hash
        my $date=$linearr[1];
        my $actId=$linearr[2];
        my $moCount=$linearr[7];

        # Extract Activity ID value from line
        $actId=~ m/Activity ID: (\d+)\. User/;
        $actId = $1;

        # Add values to hash
        $actIdMoCount{$actId}{'moCount'}=$moCount;

        # DEBUG
        if($DEBUG > 8 ) { print "Activity ID: $actId\nMO Count: $moCount\n"; }
    }
    close(PERFLOG);

    # Return Hash
    return %actIdMoCount;
}

# The following Subroutine parses the importperf.txt file and extracts the following data:
#   - ActivityID: PA Name,Num Create Ops,MO Type,Num Update Ops,End (DateTime),
#                 Num Delete Ops,Start (DateTime)
sub getPaImportDetails{
    my ($file)=@_;

    my ($createcount,$deletecount,$updatecount,$actId,$line,$end,$startdate,$starttime);
    my (%importsCreateDeleteUpdateHash);

    # Open file in read mode
    open(FILE, "<$file");
    foreach $line (<FILE>) {
            chomp($line);

            split(/\@/, $line);

            if($_[0] =~ /START/) {
                    # Reset job counters
                    $createcount = 0;
                    $deletecount = 0;
                    $updatecount = 0;

                    # Get required params from START line
                    ($actId) = $_[2] =~ /Activity ID: (\d+)/;
                    ($startdate, $starttime) = $_[1] =~ /(\d{4}-\d{2}-\d{2}).(\d{2}.\d{2}.\d{2})/;

                    # Save hash params
                    $importsCreateDeleteUpdateHash{$actId}{'start'}="$startdate $starttime";
                    $importsCreateDeleteUpdateHash{$actId}{'pa'}=$_[3];
            } elsif ($_[0] =~ /EVENT/) {
                    # Get EVENT type and increment job counter
                    if($_[3] eq 0) { $createcount+=$_[4]; }
                    elsif($_[3] eq 1) { $deletecount+=$_[4]; }
                    elsif($_[3] eq 2) { $updatecount+=$_[4]; }

                    # Check if job is create|delete|update
                    $importsCreateDeleteUpdateHash{$actId}{'mo'}=$_[2];
            } elsif ($_[0] =~ /END/) {
                    # Get required params from END line
                    ($end) = $_[1] =~ /(\d{2}.\d{2}.\d{2}).\d{3}/;

                    # Save job counts
                    $importsCreateDeleteUpdateHash{$actId}{'numCreates'}=$createcount;
                    $importsCreateDeleteUpdateHash{$actId}{'numDeletes'}=$deletecount;
                    $importsCreateDeleteUpdateHash{$actId}{'numUpdates'}=$updatecount;
                    $importsCreateDeleteUpdateHash{$actId}{'end'}="$startdate $end";
            }
    }

    close(FILE);

    return %importsCreateDeleteUpdateHash;
}
1;
