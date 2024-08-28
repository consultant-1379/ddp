package son;

use StatsDB;
use strict;
use warnings;
use Data::Dumper;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT    = qw( sonInit sonProcessEvent sonDone );

our $CREATEDBY_EUTRAN_ANR      = 0;
our $CREATEDBY_EUTRAN_X2       = 1;
our $CREATEDBY_EUTRAN_OPERATOR = 2;

our $LASTMOD_EUTRAN_ANR_MOD = 0;
our $LASTMOD_EUTRAN_X2_MOD =  1;
our $LASTMOD_EUTRAN_OP_MOD =  2;
our $LASTMOD_EUTRAN_ANR_DEL = 3;
our $LASTMOD_EUTRAN_X2_DEL =  4;
our $LASTMOD_EUTRAN_OP_DEL =  5;
our $LASTMOD_EUTRAN_NOT_MOD = 6;
our $LASTMOD_EUTRAN_MRO_MOD = 7;
our $LASTMOD_EUTRAN_PCI_MOD = 8;
our $LASTMOD_EUTRAN_MLB_MOD = 9;
our $LASTMOD_EUTRAN_RACH_OPT_MOD = 10;
our $LASTMOD_EUTRAN_LM_MOD  = 11;

our @EUTRAN_MO_TYPES = ( 'TermPointToENB', 'ExternalEUtranCellTDD', 'ExternalEUtranCellFDD', 'ExternalENodeBFunction',
                         'EUtranFreqRelation', 'EUtranCellRelation');

our $LASTMOD_GERAN_ANR_MOD = 0;
our $LASTMOD_GERAN_OP_MOD  = 1;
our $LASTMOD_GERAN_NOT_MOD = 2;
our $LASTMOD_GERAN_ANR_DEL = 3;
our $LASTMOD_GERAN_OP_DEL  = 4;

our $LASTMOD_UTRAN_ANR_MOD = 0;
our $LASTMOD_UTRAN_OP_MOD  = 1;
our $LASTMOD_UTRAN_NOT_MOD = 2;
our $LASTMOD_UTRAN_ANR_DEL = 3;
our $LASTMOD_UTRAN_OP_DEL  = 4;
our $LASTMOD_UTRAN_LM_MOD  = 5;

our $CREATEDBY_IRAT_OPERATOR = 0;
our $CREATEDBY_IRAT_ANR      = 1;

our @UTRAN_MO_TYPES  = ( 'UtranCellRelation', 'ExternalUtranCellFDD', 'ExternalUtranCellTDD' );
our @GERAN_MO_TYPES  = ( 'GeranCellRelation', 'ExternalGeranCell');
our @WRAN_MO_TYPES = ( 'UtranRelation', 'ExternalUtranCell' );

our %sonCreatedByMap = ();
our %sonLastModMap = ();

our $r_sonStats = undef;

# Called at startup, will be passed the sonStats read from the incremental file
# This will have the same content as whatever was returned from sonDone in the
# previous execution
sub sonInit($) {
    ($r_sonStats) = @_;

    foreach my $moType ( @EUTRAN_MO_TYPES ) {
        $sonCreatedByMap{$moType}->{$CREATEDBY_EUTRAN_ANR} = 'create_anr';
        $sonCreatedByMap{$moType}->{$CREATEDBY_EUTRAN_X2} = 'create_x2';
        $sonCreatedByMap{$moType}->{$CREATEDBY_EUTRAN_OPERATOR} = 'create_operator';

        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_ANR_MOD} = 'modify_anr';
        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_X2_MOD} = 'modify_x2';
        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_OP_MOD} = 'modify_operator';
        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_NOT_MOD} = 'modify_not';
        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_MRO_MOD} = 'modify_mro';
        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_PCI_MOD} = 'modify_pci';
        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_MLB_MOD} = 'modify_mlb';
        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_RACH_OPT_MOD} = 'modify_rach_opt';
        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_LM_MOD} = 'modify_lm';

        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_ANR_DEL} = 'delete_anr';
        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_X2_DEL} = 'delete_x2';
        $sonLastModMap{$moType}->{$LASTMOD_EUTRAN_OP_DEL} = 'delete_operator';
    }

    foreach my $moType ( @GERAN_MO_TYPES ) {
         $sonCreatedByMap{$moType}->{$CREATEDBY_IRAT_ANR}      = 'create_anr';
         $sonCreatedByMap{$moType}->{$CREATEDBY_IRAT_OPERATOR} = 'create_operator';
         $sonLastModMap{$moType}->{$LASTMOD_GERAN_ANR_MOD} = 'modify_anr';
         $sonLastModMap{$moType}->{$LASTMOD_GERAN_OP_MOD}  = 'modify_operator';
         $sonLastModMap{$moType}->{$LASTMOD_GERAN_NOT_MOD} = 'modify_not';
         $sonLastModMap{$moType}->{$LASTMOD_GERAN_ANR_DEL} = 'delete_anr';
         $sonLastModMap{$moType}->{$LASTMOD_GERAN_OP_DEL}  = 'delete_operator';
    }

    foreach my $moType ( @UTRAN_MO_TYPES ) {
         $sonCreatedByMap{$moType}->{$CREATEDBY_IRAT_ANR}      = 'create_anr';
         $sonCreatedByMap{$moType}->{$CREATEDBY_IRAT_OPERATOR} = 'create_operator';
         $sonLastModMap{$moType}->{$LASTMOD_UTRAN_ANR_MOD} = 'modify_anr';
         $sonLastModMap{$moType}->{$LASTMOD_UTRAN_OP_MOD}  = 'modify_operator';
         $sonLastModMap{$moType}->{$LASTMOD_UTRAN_NOT_MOD} = 'modify_not';
         $sonLastModMap{$moType}->{$LASTMOD_UTRAN_LM_MOD}  = 'modify_lm';
         $sonLastModMap{$moType}->{$LASTMOD_UTRAN_ANR_DEL} = 'delete_anr';
         $sonLastModMap{$moType}->{$LASTMOD_UTRAN_OP_DEL}  = 'delete_operator';
    }

    foreach my $moType ( @WRAN_MO_TYPES ) {
        $sonCreatedByMap{$moType}->{'"ANR"'} = 'create_anr';
        $sonCreatedByMap{$moType}->{'"operator"'} = 'create_operator';
    }

    if ( $::DEBUG > 3 ) {
        print Dumper("initSonMap: sonCreatedByMap", \%sonCreatedByMap);
        print Dumper("initSonMap: sonLastModMap", \%sonLastModMap);
    }
}

# Called for each event where the app is CMS_NEAD
#  eventType: one of $ATTRIBUTE_VALUE_CHANGE, $OBJECT_CREATION, $OBJECT_DELETION
#                    $ASSOCIATION_CREATION, $ASSOCIATION_DELETION
#  moi: MO FDN
#  r_attribs: hash, key attribute name, value attribute value
#  ts: time in the format HHMM
sub sonProcessEvent($$$$) {
    my ($eventType,$moi,$r_attribs,$ts) = @_;

    if ( $::DEBUG > 8 ) { print "sonProcessEvent: eventType=$eventType moi=$moi\n"; }

    my $isAnrX2 = 0;
    my %anrX2Attr = ();
    while ( my ($name,$value) = each %{$r_attribs} ) {
    if ( $::DEBUG > 8 ) { print "sonProcessEvent: name=$name value=$value\n"; }
         if ( $name eq 'lastModification' ||
             $name eq 'createdBy' || $name eq 'cellIndividualOffsetEUtran' || $name eq 'qOffsetCellEUtran' || $name eq 'electricalAntennaTilt' || $name eq 'isHoAllowed'
             || $name eq 'isRemoveAllowed') {
             $isAnrX2 = 1;
             $anrX2Attr{$name} = $value;
         }
    }

    if ( $isAnrX2 ) {
        processSON($eventType,$moi,\%anrX2Attr,$ts);
    }
}

# Called when all events have been processed
#  site: site name
#  date: date being processed
#
# content of returned value will be stored in the incremental value and
# will be passed to sonInit on the next execution (for this date)
sub sonDone($$) {
    my ($site,$date) = @_;

    my $dbh = connect_db();

    my $siteId = getSiteId($dbh,$site);
    ($siteId > -1 ) or die "Failed to get siteid for $site";

    my $tmpDir = "/data/tmp";
    if ( exists $ENV{"TMP_DIR"} ) { $tmpDir = $ENV{"TMP_DIR"}; }

    if ( exists $r_sonStats->{'bytime'} ) {
        my $bcpFile = $tmpDir . "/son_rate.bcp";
        open BCP , ">$bcpFile" or die "Cannot open $bcpFile";
        foreach my $time ( sort { $a <=> $b } keys %{$r_sonStats->{'bytime'}} ) {
            my ($hour,$min) = $time =~ /^(\d{2,2})(\d{2,2})$/;
            my $line = "$date $hour:$min:00\t$siteId";
            foreach my $opType ( 'anr', 'x2' ) {
                foreach my $op ( 'create', 'delete', 'modify' ) {
                    my $value = $r_sonStats->{'bytime'}->{$time}->{$op . "_" . $opType};
                    if ( ! defined $value ) {
                        $value = 0;
                    }
                    $line .= "\t$value";
                }
            }
            print BCP "$line\n";
        }
        close BCP;
        dbDo($dbh, "DELETE FROM son_rate WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'")
            or die "Failed to delete data from son_rate" . $dbh->errstr;
        dbDo($dbh,"LOAD DATA LOCAL INFILE \'$bcpFile\' INTO TABLE son_rate")
            or die "Failed to load data into son_rate" . $dbh->errstr;
        unlink($bcpFile);
    }

    if ( exists $r_sonStats->{'bymo'} ) {
        my @moTypes = keys %{$r_sonStats->{'bymo'}};
        my $r_mocMap = getIdMap($dbh, "mo_names", "id", "name", \@moTypes);

        my $bcpFile = $tmpDir . "/son_mo.bcp";
        open BCP , ">$bcpFile" or die "Cannot open $bcpFile";
        foreach my $moType ( keys %{$r_sonStats->{'bymo'}} ) {
            my $line = "$date\t$siteId\t" . $r_mocMap->{$moType};
            foreach my $opType ( 'anr', 'x2' ) {
                foreach my $op ( 'create', 'delete', 'modify' ) {
                    my $value = $r_sonStats->{'bymo'}->{$moType}->{$op . "_" . $opType};
                    if ( ! defined $value ) {
                        $value = 0;
                    }
                    $line .= "\t$value";
                }
            }
            print BCP "$line\n";
        }
        close BCP;

        dbDo($dbh, "DELETE FROM son_mo WHERE siteid = $siteId AND date = '$date'")
            or die "Failed to delete data from son_mo" . $dbh->errstr;
        dbDo($dbh,"LOAD DATA LOCAL INFILE \'$bcpFile\' INTO TABLE son_mo")
            or die "Failed to load data into son_mo" . $dbh->errstr;
        unlink($bcpFile);
    }
    storeSonRateAdditions($r_sonStats, $date, $dbh,  $siteId, $tmpDir);
    storeSonMoAdditions($r_sonStats, $date, $dbh,  $siteId, $tmpDir);
    storeCioQoffsetRate($r_sonStats, $date, $dbh,  $siteId, $tmpDir);
    storeCioCounts($r_sonStats,$date, $dbh, $siteId, $tmpDir);
    storeQoffsetCounts($r_sonStats, $date, $dbh,  $siteId, $tmpDir);
    storeMocByTime($r_sonStats, $date, $dbh, $siteId, $tmpDir);
    storeElectricalTiltValues($r_sonStats, $date, $dbh, $siteId, $tmpDir);
    storeBlackListedValues($r_sonStats, $date, $dbh, $siteId, $tmpDir);

    $dbh->disconnect();

    return $r_sonStats;
}

sub processSON($$$$) {
    my ($eventType,$moi,$r_anrX2Attr,$ts) = @_;

    my ($moType) = $moi =~ /,([^=,]+)=([^,=]+)$/;
    if ( ! defined $moType ) {
        print "WARN: Failed to extract moType from $moi\n";
        return;
    }

    my ($meConId) = $moi =~ /MeContext=([^,]+),/;
    if ( ! defined $moType ) {
        print "WARN: Failed to extract meContext id from $moi\n";
        return;
    }

    if ( $::DEBUG > 7 ) { print "processSON: eventType=$eventType moType=$moType meConId=$meConId\n"; }

    if ( $eventType == $::OBJECT_CREATION ) {
        my $createdBy = -1;
        if ( exists $r_anrX2Attr->{'createdBy'} ) {
            $createdBy = $r_anrX2Attr->{'createdBy'};
        }
        my $createType = $sonCreatedByMap{$moType}->{$createdBy};
        if ( $::DEBUG > 7 ) { print "processSON: createdBy: $createdBy\n"; }
        if ( defined $createType ) {
            if ( $::DEBUG > 7 ) { print "processSON: createType=$createType\n"; }
            $r_sonStats->{'bymo'}->{$moType}->{$createType}++;
            $r_sonStats->{'bytime'}->{$ts}->{$createType}++;
            $r_sonStats->{'bynode'}->{$meConId}->{$createType}++;
            mocByTime($r_sonStats, $moType, $ts, $createType);
        }
    } elsif ( $eventType == $::ATTRIBUTE_VALUE_CHANGE ) {
        my $lastModification = -1;
        my $lastModType;
        if ( exists $r_anrX2Attr->{'lastModification'} ) {
            ($lastModification) = $r_anrX2Attr->{'lastModification'} =~ /->(\d+)/;
            $lastModType = $sonLastModMap{$moType}->{$lastModification};
        }
        if (!exists $r_anrX2Attr->{'lastModification'} ) { #if it's not in the event
            $lastModType = getlastModTypeFromCache($r_sonStats, $moi);
        }
        if ( $::DEBUG > 7 ) { print "processSON: lastModification: $lastModification\n"; }
        if ( defined $lastModType ) {
            if ( $::DEBUG > 7 ) { print "processSON: lastModType=$lastModType\n"; }
            $r_sonStats->{'bymo'}->{$moType}->{$lastModType}++;
            $r_sonStats->{'bytime'}->{$ts}->{$lastModType}++;
            $r_sonStats->{'bynode'}->{$meConId}->{$lastModType}++;
            mocByTime($r_sonStats, $moType, $ts, $lastModType);
            updateLastModCache($r_sonStats, $moi, $lastModType); #update the cache with the most recent moi -> lastModType
        }
        else {
             if ( defined $sonLastModMap{$moType} ) {
                 updateAvcCacheMissCount($r_sonStats, $ts, $meConId, $moType);
                 mocByTime($r_sonStats, $moType, $ts, 'AVC_cache_miss');
             }
        }

        if ( exists $r_anrX2Attr -> {'cellIndividualOffsetEUtran'} ) {
             my $value = $r_anrX2Attr -> {'cellIndividualOffsetEUtran'};
             my $cio_value = parseCioValue($value);
             my $name = 'cellIndividualOffsetEUtran';
             updateCioQoffsetCount($ts, $r_sonStats, $moi, $lastModType, $cio_value, $name);
        }

        if (exists $r_anrX2Attr -> {'qOffsetCellEUtran'}){
             my $value = $r_anrX2Attr -> {'qOffsetCellEUtran'};
             my $qOffset_value = parseQoffsetValue($value);
             my $name = 'qOffsetCellEUtran';
             updateCioQoffsetCount($ts, $r_sonStats, $moi, $lastModType, $qOffset_value, $name);
        }

        if (exists $r_anrX2Attr -> {'electricalAntennaTilt'}){
             my $value = $r_anrX2Attr -> {'electricalAntennaTilt'};
             my @electricalTiltArray = parseElectricalTiltValue($value);
             my $newValue = $electricalTiltArray[1];
             $newValue =~ s/^\s+|\s+$//g;
             my $oldValue = $electricalTiltArray[0];
             $oldValue =~ s/^\s+|\s+$//g;
             updateElectricalTiltCount($ts, $moi, $r_sonStats, $newValue, $oldValue);
        }

        if (exists $r_anrX2Attr -> {'isHoAllowed'}){
             if ($moType eq 'EUtranCellRelation'){
             my $value = $r_anrX2Attr -> {'isHoAllowed'};
             my @isHoAllowedArr = parseElectricalTiltValue($value);
             my $newValue = $isHoAllowedArr[1];
             $newValue =~ s/^\s+|\s+$//g;
             my $oldValue = $isHoAllowedArr[0];
             $oldValue =~ s/^\s+|\s+$//g;
             updateBlackList($r_sonStats, $ts, $moi, $oldValue, $newValue, 'isHoAllowed');
             }
        }

        if (exists $r_anrX2Attr -> {'isRemoveAllowed'}){
             if ($moType eq 'EUtranCellRelation'){
             my $value = $r_anrX2Attr -> {'isRemoveAllowed'};
             my @isRemoveAllowedArr = parseElectricalTiltValue($value);
             my $newValue = $isRemoveAllowedArr[1];
             $newValue =~ s/^\s+|\s+$//g;
             my $oldValue = $isRemoveAllowedArr[0];
             $oldValue =~ s/^\s+|\s+$//g;
             updateBlackList($r_sonStats, $ts, $moi, $oldValue, $newValue, 'isRemoveAllowed');
             }
        }
    }
}

# updates the blacklist count
# If we encounter a situation such as isHoAllowed: true -> false, then we increment
sub updateBlackList($$$$$$){
     my ($r_sonStats, $ts, $moi, $oldValue, $newValue, $allowedAttr) = @_;

     if ($allowedAttr eq 'isHoAllowed'){

         #if we're going from true to false we increment false count
         if ($oldValue eq 'true' && $newValue eq 'false'){
             $r_sonStats->{'bytime'}->{$ts}->{'blackList'}->{$moi}->{'HoAllowed_false_count'}++;
         }

         #if we're going from false to true we increment true count
         if ($oldValue eq 'false' && $newValue eq 'true'){
             $r_sonStats->{'bytime'}->{$ts}->{'blackList'}->{$moi}->{'HoAllowed_true_count'}++;
         }
     }

     if ($allowedAttr eq 'isRemoveAllowed'){

         if ($oldValue eq 'true' && $newValue eq 'false'){
             $r_sonStats->{'bytime'}->{$ts}->{'blackList'}->{$moi}->{'RemAllowed_false_count'}++;
         }

         #if we're going from false to true we increment true count
         if ($oldValue eq 'false' && $newValue eq 'true'){
             $r_sonStats->{'bytime'}->{$ts}->{'blackList'}->{$moi}->{'RemAllowed_true_count'}++;
         }

     }
}

# Stores counts to check for antenna tilts

sub updateElectricalTiltCount($$$$$){
     my ($ts, $moi, $r_sonStats, $newValue, $oldValue) = @_;

     my $tiltDifference = $newValue - $oldValue;

     if ($tiltDifference > 0 ){
         $r_sonStats -> {'byelectime'} -> {$ts} -> {$moi} -> {'downTilt'}++;
         $r_sonStats -> {'byelectime'} -> {$ts} -> {$moi} -> {'newValue'}  = $newValue;
         $r_sonStats -> {'byelectime'} -> {$ts} ->  {$moi} -> {'oldValue'} = $oldValue;
         $r_sonStats -> {'byelectime'} -> {$ts} ->  {$moi} -> {'tiltDifference'} =  $tiltDifference;
     }
     elsif ($tiltDifference < 0){
         $r_sonStats -> {'byelectime'} -> {$ts} ->  {$moi} -> {'upTilt'}++;
         $r_sonStats -> {'byelectime'} -> {$ts} ->  {$moi} -> {'newValue'} = $newValue;
         $r_sonStats -> {'byelectime'} -> {$ts} -> {$moi} -> {'oldValue'} = $oldValue;
         $r_sonStats -> {'byelectime'} -> {$ts} ->  {$moi} -> {'tiltDifference'} = $tiltDifference;
     }
     elsif ($tiltDifference == 0){
         $r_sonStats -> {'byelectime'} -> {$ts} -> {$moi} -> {'neutralTilt'}++;
         $r_sonStats -> {'byelectime'} -> {$ts} -> {$moi} -> {'newValue'} = $newValue;
         $r_sonStats -> {'byelectime'} -> {$ts} -> {$moi} -> {'oldValue'} = $oldValue;
         $r_sonStats -> {'byelectime'} -> {$ts} -> {$moi} -> {'tiltDifference'} = $tiltDifference;
     }
}

# Stores counts by managed object class and the time it occurs.
# Allows us to seperate out the graphs on the son.php page so that we may see counts by each MOC.

sub mocByTime($$$$){
     my ($r_sonStats, $moType, $ts, $actorOperation) = @_;
     $r_sonStats->{'bymoctime'}->{$moType}->{$ts}->{$actorOperation}++;
}

# Called when a lastModType is defined and retrieved from the event or the cache
# Stores in cache section of the incremental file in the form moi=>lastModType eg. moi=>delete_anr
sub updateLastModCache($$$) {
     my ($r_sonStats, $moi, $lastModType) = @_;
     $r_sonStats -> {'cache'} -> {$moi} = $lastModType;
}

sub getlastModTypeFromCache($$) {
     my ($r_sonStats, $moi) = @_;
     my $lastModType;
     if (exists $r_sonStats -> {'cache'} -> {$moi}) {
         $lastModType = $r_sonStats -> {'cache'} -> {$moi};
     }
     return $lastModType;
}

# Called when a lastModType is undefined ie. cannot be retrieved from the event or the cache
sub updateAvcCacheMissCount($$$$) {
     my ($r_sonStats, $ts, $meConId, $moType) = @_;

     $r_sonStats -> {'bytime'} -> {$ts}        -> {'AVC_cache_miss'}++;
     $r_sonStats -> {'bynode'} -> {$meConId} -> {'AVC_cache_miss'}++;
     $r_sonStats -> {'bymo'}   -> {$moType}   -> {'AVC_cache_miss'}++;
}

sub storeBlackListedValues($$$$$){
     my ($r_sonStats, $date, $dbh, $siteId, $tmpDir) = @_;

     my $countMOI=0; #counter to check for mois
        if ( exists $r_sonStats->{'bytime'}) {
             my $bcpFile = $tmpDir . "/son_black_listed.bcp";
             open BCP , ">$bcpFile" or die "Cannot open $bcpFile";

                 foreach my $time ( sort { $a <=> $b } keys %{$r_sonStats->{'bytime'}} ) {
                     my ($hour,$min) = $time =~ /^(\d{2,2})(\d{2,2})$/;
                     my $line = "$date $hour:$min:00\t$siteId";

                     if (keys %{$r_sonStats->{'bytime'}->{$time}->{'blackList'}}){
                         foreach my $moi (keys %{$r_sonStats->{'bytime'}->{$time}->{'blackList'}}){

                             #extrat the isRemoveAllowed false/true counts
                             my $isRemTrue = $r_sonStats->{'bytime'}->{$time}->{'blackList'}->{$moi}->{'RemAllowed_true_count'};
                             my $isRemFalse = $r_sonStats->{'bytime'}->{$time}->{'blackList'}->{$moi}->{'RemAllowed_false_count'};

                             #extract the isHoAllowed false/true counts
                             my $isHoTrue = $r_sonStats->{'bytime'}->{$time}->{'blackList'}->{$moi}->{'HoAllowed_true_count'};
                             my $isHoFalse = $r_sonStats->{'bytime'}->{$time}->{'blackList'}->{$moi}->{'HoAllowed_false_count'};


                             #if undefined set the counts to 0
                             if (! defined $isRemTrue){
                                 $isRemTrue = 0;
                             }
                             if (! defined $isRemFalse){
                                 $isRemFalse = 0;
                             }

                             if (! defined $isHoTrue){
                                 $isHoTrue = 0;
                             }
                             if (! defined $isHoFalse){
                                 $isHoFalse = 0;
                             }

                             if ($countMOI>0){ #we have more than one moi for a particular time
                                 my $line2 = "$date $hour:$min:00\t$siteId\t$moi\t$isRemTrue\t$isRemFalse\t$isHoTrue\t$isHoFalse";
                                 print BCP "$line2\n";
                             }
                             else{
                                 $line .= "\t$moi\t$isRemTrue\t$isRemFalse\t$isHoTrue\t$isHoFalse";
                             }
                             $countMOI++;
                         }
                         $countMOI=0;
                         print BCP "$line\n";
                     }
                     else{
                     my $moi = "N/A";
                     my $isRemTrue = 0;
                     my $isRemFalse = 0;
                     my $isHoTrue = 0;
                     my $isHoFalse = 0;

                     $line .= "\t$moi\t$isRemTrue\t$isRemFalse\t$isHoTrue\t$isHoFalse";
                     print BCP "$line\n";
                     }
                 }
                 close BCP;

         dbDo($dbh, "DELETE FROM son_anr_augmentation WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'")
             or die "Failed to delete data from son_anr_augmentation" . $dbh->errstr;
         dbDo($dbh,"LOAD DATA LOCAL INFILE \'$bcpFile\' INTO TABLE son_anr_augmentation")
             or die "Failed to load data into son_anr_augmentation" . $dbh->errstr;
         unlink($bcpFile);
         }
}

sub storeElectricalTiltValues($$$$$){
     my ($r_sonStats, $date, $dbh, $siteId, $tmpDir) = @_;
     my $countMOI=0; #counter to check for mois

        if ( exists $r_sonStats->{'bytime'} && $r_sonStats->{'byelectime'} ) {
             my $bcpFile = $tmpDir . "/son_electrical_tilt_rate.bcp";
             open BCP , ">$bcpFile" or die "Cannot open $bcpFile";

             foreach my $time ( sort { $a <=> $b } keys %{$r_sonStats->{'bytime'}} ) {
                 my ($hour,$min) = $time =~ /^(\d{2,2})(\d{2,2})$/;
                 my $line = "$date $hour:$min:00\t$siteId";

                 if (keys %{$r_sonStats -> {'byelectime'} -> {$time}}){
                     foreach my $moi (keys %{$r_sonStats -> {'byelectime'} -> {$time}}){

                         my $downTilt =  $r_sonStats -> {'byelectime'} -> {$time} -> {$moi} ->{'downTilt'};
                         my $upTilt =  $r_sonStats -> {'byelectime'} -> {$time} -> {$moi} ->{'upTilt'};
                         my $neutralTilt =  $r_sonStats -> {'byelectime'} -> {$time} -> {$moi} ->{'neutralTilt'};
                         my $oldValue =  $r_sonStats -> {'byelectime'} -> {$time} -> {$moi} ->{'oldValue'};
                         my $newValue =  $r_sonStats -> {'byelectime'} -> {$time} -> {$moi} ->{'newValue'};

                         my $tiltDifference =  $r_sonStats -> {'byelectime'} -> {$time} -> {$moi} ->{'tiltDifference'};

                         if (! defined $downTilt){
                             $downTilt = 0;
                         }
                         if (! defined $upTilt){
                             $upTilt = 0;
                         }
                         if (! defined $neutralTilt){
                             $neutralTilt = 0;
                         }

                         if ($countMOI>0){ #we have more than one moi for a particular time
                             my $line = "$date $hour:$min:00\t$siteId\t$downTilt\t$upTilt\t$neutralTilt\t$moi\t$oldValue\t$newValue\t$tiltDifference";
                             print BCP "$line\n";
                         }
                         else{
                             $line .= "\t$downTilt\t$upTilt\t$neutralTilt\t$moi\t$oldValue\t$newValue\t$tiltDifference";
                         }
                         $countMOI++;
                     }
                     $countMOI=0;
                     print BCP "$line\n";
                 }
                 else{
                     my $downTilt = 0;
                     my $upTilt = 0;
                     my $neutralTilt = 0;
                     my $oldValue = 0;
                     my $newValue = 0;
                     my $tiltDifference = 0;
                     my $moi = "N/A";
                     $line .= "\t$downTilt\t$upTilt\t$neutralTilt\t$moi\t$oldValue\t$newValue\t$tiltDifference";
                     print BCP "$line\n";
                 }
             }
             close BCP;

             dbDo($dbh, "DELETE FROM son_electrical_tilt_rate WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'")
             or die "Failed to delete data from son_electrical_tilt_rate" . $dbh->errstr;
             dbDo($dbh,"LOAD DATA LOCAL INFILE \'$bcpFile\' INTO TABLE son_electrical_tilt_rate")
             or die "Failed to load data into son_electrical_tilt_rate" . $dbh->errstr;
             unlink($bcpFile);
         }
}

sub storeMocByTime($$$$$){
     my ($r_sonStats, $date, $dbh, $siteId, $tmpDir) = @_;
     if (exists $r_sonStats->{'bymoctime'}) {
         my $bcpFile = $tmpDir . "/son_moc_rate.bcp";
         open BCP , ">$bcpFile" or die "Cannot open $bcpFile";

         foreach my $moType ( keys %{$r_sonStats->{'bymoctime'}} ){ #will return the various managed object classes eg EUtranCellRelation and EUtranFreqRelation
             foreach my $time ( sort { $a <=> $b } keys %{$r_sonStats->{'bymoctime'}->{$moType}}) {  #will get the timestamps related to the managed objects classes
                 my ($hour,$min) = $time =~ /^(\d{2,2})(\d{2,2})$/;
                 my $line = "$date $hour:$min:00\t$siteId\t" . $moType;

                 foreach my $opType ( 'anr', 'x2' ) {
                     foreach my $op ( 'create', 'delete', 'modify' ) {
                         my $value = $r_sonStats->{'bymoctime'}->{$moType}->{$time}->{$op . "_" . $opType};
                         if ( ! defined $value ) {
                             $value = 0;
                         }
                         $line .= "\t$value";
                     }
                 }

                 foreach my $operType ('operator'){
                     foreach my $oper ('create', 'delete', 'modify'){
                         my $value = $r_sonStats->{'bymoctime'}->{$moType}->{$time}->{$oper .'_'.$operType};
                         if (! defined $value){
                         $value = 0;
                         }
                         $line .= "\t$value";
                     }

                 }

                 foreach my $operationType ('not', 'mro', 'pci', 'mlb', 'rach_opt', 'lm'){
                     foreach my $operation ('modify'){
                         my $value = $r_sonStats->{'bymoctime'}->{$moType}->{$time}->{$operation . "_" . $operationType};
                         if ( ! defined $value ) {
                             $value = 0;
                         }
                         $line .= "\t$value";
                     }
                 }

                 my $avc_value = $r_sonStats->{'bymoctime'}->{$moType}->{$time}->{'AVC_cache_miss'};
                 if ( ! defined $avc_value ) {
                 $avc_value = 0;
                 }
                 $line .= "\t$avc_value";

                 print BCP "$line\n";
             }

         }

         close BCP;

         dbDo($dbh, "DELETE FROM son_moc_rate WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'")
             or die "Failed to delete data from son_moc_rate" . $dbh->errstr;
         dbDo($dbh,"LOAD DATA LOCAL INFILE \'$bcpFile\' INTO TABLE son_moc_rate")
             or die "Failed to load data into son_moc_rate" . $dbh->errstr;
         unlink($bcpFile);
     }
}

sub storeSonRateAdditions($$$$$){

     my ($r_sonStats, $date, $dbh, $siteId, $tmpDir) = @_;

     if ( exists $r_sonStats->{'bytime'} ) {

     my $bcpFile = $tmpDir . "/son_rate_additions.bcp";
     open BCP , ">$bcpFile" or die "Cannot open $bcpFile";

         foreach my $time ( sort { $a <=> $b } keys %{$r_sonStats->{'bytime'}} ) {
             my ($hour,$min) = $time =~ /^(\d{2,2})(\d{2,2})$/;
             my $line = "$date $hour:$min:00\t$siteId";

                 foreach my $operType ('operator'){
                     foreach my $oper ('create', 'delete', 'modify'){
                         my $value = $r_sonStats->{'bytime'}->{$time}->{$oper .'_'.$operType};
                         if (! defined $value){
                         $value = 0;
                         }
                         $line .= "\t$value";
                     }

                 }
                 foreach my $opType ('not', 'mro', 'pci', 'mlb', 'rach_opt', 'lm'){
                     foreach my $op ('modify'){
                         my $value = $r_sonStats->{'bytime'}->{$time}->{$op . "_" . $opType};
                         if ( ! defined $value ) {
                             $value = 0;
                         }
                         $line .= "\t$value";
                     }
                 }
                 my $avc_value = $r_sonStats->{'bytime'}->{$time}->{'AVC_cache_miss'};
                 if ( ! defined $avc_value ) {
                 $avc_value = 0;
                 }
                 $line .= "\t$avc_value";

                 print BCP "$line\n";
         }
         close BCP;

         dbDo($dbh, "DELETE FROM son_rate_additions WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'")
             or die "Failed to delete data from son_rate_additions" . $dbh->errstr;
         dbDo($dbh,"LOAD DATA LOCAL INFILE \'$bcpFile\' INTO TABLE son_rate_additions")
             or die "Failed to load data into son_rate_additions" . $dbh->errstr;
         unlink($bcpFile);
     }
}

sub storeSonMoAdditions($$$$$){

     my ($r_sonStats,$date, $dbh, $siteId, $tmpDir) = @_;

     if (exists $r_sonStats->{'bymo'} ) {
     my @moTypes = keys %{$r_sonStats->{'bymo'}};
     my $r_mocMap = getIdMap($dbh, "mo_names", "id", "name", \@moTypes);

     my $bcpFile = $tmpDir . "/son_mo_additions.bcp";
     open BCP , ">$bcpFile" or die "Cannot open $bcpFile";

         foreach my $moType ( keys %{$r_sonStats->{'bymo'}} ) {
         my $line = "$date\t$siteId\t" . $r_mocMap->{$moType};

             foreach my $operType ('operator'){
                 foreach my $oper ('create', 'delete', 'modify'){
                     my $value = $r_sonStats->{'bymo'}->{$moType}->{$oper .'_'.$operType};
                     if (! defined $value){
                         $value = 0;
                     }
                     $line .= "\t$value";
                 }

             }
             foreach my $opType ('not', 'mro', 'pci', 'mlb', 'rach_opt', 'lm'){
                 foreach my $op ('modify'){
                     my $value = $r_sonStats->{'bymo'}->{$moType}->{$op . "_" . $opType};
                     if ( ! defined $value ) {
                         $value = 0;
                     }
                     $line .= "\t$value";
                 }
             }
             my $avc_value = $r_sonStats->{'bymo'}->{$moType}->{'AVC_cache_miss'};
             if ( ! defined $avc_value ) {
             $avc_value = 0;
             }
             $line .= "\t$avc_value";

             print BCP "$line\n";
         }
         close BCP;

         dbDo($dbh, "DELETE FROM son_mo_additions WHERE siteid = $siteId AND date = '$date'")
             or die "Failed to delete data from son_mo_additions" . $dbh->errstr;
         dbDo($dbh,"LOAD DATA LOCAL INFILE \'$bcpFile\' INTO TABLE son_mo_additions")
             or die "Failed to load data into son_mo_additions" . $dbh->errstr;
         unlink($bcpFile);
     }
}

sub storeCioQoffsetRate($$$$$){

     my ($r_sonStats, $date, $dbh, $siteId, $tmpDir) = @_;

     if ( exists $r_sonStats->{'bytime'} ) {
     my $bcpFile = $tmpDir . "/son_cio_qOffset_rate.bcp";
     open BCP , ">$bcpFile" or die "Cannot open $bcpFile";

         foreach my $time ( sort { $a <=> $b } keys %{$r_sonStats->{'bytime'}} ) {
             my ($hour,$min) = $time =~ /^(\d{2,2})(\d{2,2})$/;
             my $line = "$date $hour:$min:00\t$siteId";

             foreach my $opType ( 'operator_total', 'mro_total', 'other_total', 'cache_miss_total' ) {
                 foreach my $op ( 'modify' ) {
                     my $cio_value = $r_sonStats->{'bytime'}->{$time} -> {'cio_changes_count'} -> {$op . "_" . $opType};
                     if ( ! defined $cio_value ){
                         $cio_value = 0;
                     }

                     $line .= "\t$cio_value";
                 }
             }

             foreach my $opType ( 'operator_total', 'mro_total', 'other_total', 'cache_miss_total' ) {
                 foreach my $op ( 'modify' ) {
                     my $qOffset_value = $r_sonStats->{'bytime'}->{$time} -> {'qOffset_changes_count'} -> {$op . "_" . $opType};
                     if ( ! defined $qOffset_value ){
                         $qOffset_value = 0;
                     }

                     $line .= "\t$qOffset_value";
                 }
             }

             print BCP "$line\n";
         }
         close BCP;

         dbDo($dbh, "DELETE FROM son_cio_qOffset_rate WHERE siteid = $siteId AND time BETWEEN '$date 00:00:00' AND '$date 23:59:59'")
             or die "Failed to delete data from son_cio_qOffset_rate" . $dbh->errstr;
         dbDo($dbh,"LOAD DATA LOCAL INFILE \'$bcpFile\' INTO TABLE son_cio_qOffset_rate")
             or die "Failed to load data into son_cio_qOffset_rate" . $dbh->errstr;
         unlink($bcpFile);
     }

}
# Called in sonDone
# dbh: database connection
# tmpDir: location where the bcp files are stored before loading into tables
sub storeCioCounts($$$$$){
     my ($r_sonStats, $date, $dbh, $siteId, $tmpDir) = @_;

     my @moTypes = keys %{$r_sonStats->{'bymo'}};
     my $r_mocMap = getIdMap($dbh, "mo_names", "id", "name", \@moTypes);

     if (exists $r_sonStats->{'bymo'}) {
     my $bcpFile = $tmpDir . "/son_cio_changes.bcp";
     open BCP , ">$bcpFile" or die "Cannot open $bcpFile";

     foreach my $db_value (sort { $a <=> $b } keys %{$r_sonStats -> {'bymo'} -> {'EUtranCellRelation'} -> {'cio_changes_count'} -> {'db_values'}}){
         my $line = "$date\t$siteId\t" . $r_mocMap->{'EUtranCellRelation'} ."\t$db_value";
             foreach my $op ('modify'){
                 foreach my $op_type ('operator', 'mro', 'other', 'cache_miss'){
                     my $cio_count = $r_sonStats -> {'bymo'} -> {'EUtranCellRelation'} -> {'cio_changes_count'} -> {'db_values'} -> {$db_value} -> {$op . "_" . $op_type};
                     if (! defined $cio_count){
                         $cio_count = 0;
                     }
                     $line .="\t$cio_count";
                 }
             }
             print BCP "$line\n";
     }
     close BCP;
     dbDo($dbh, "DELETE FROM son_cio_changes WHERE siteid = $siteId AND date = '$date'")
         or die "Failed to delete data from son_cio_changes" . $dbh->errstr;
     dbDo($dbh,"LOAD DATA LOCAL INFILE \'$bcpFile\' INTO TABLE son_cio_changes")
         or die "Failed to load data into son_cio_changes" . $dbh->errstr;
     unlink($bcpFile);
     }
}

sub storeQoffsetCounts($$$$$){
     my ($r_sonStats, $date, $dbh, $siteId, $tmpDir) = @_;

     my @moTypes = keys %{$r_sonStats->{'bymo'}};
     my $r_mocMap = getIdMap($dbh, "mo_names", "id", "name", \@moTypes);

     if (exists $r_sonStats->{'bymo'}) {
     my $bcpFile = $tmpDir . "/son_qOffset_changes.bcp";
     open BCP , ">$bcpFile" or die "Cannot open $bcpFile";

     foreach my $db_value (sort { $a <=> $b } keys %{$r_sonStats -> {'bymo'} -> {'EUtranCellRelation'} -> {'qOffset_changes_count'} -> {'db_values'}}){
         my $line = "$date\t$siteId\t" . $r_mocMap->{'EUtranCellRelation'} ."\t$db_value";
             foreach my $op ('modify'){
                 foreach my $op_type ('operator', 'mro', 'other', 'cache_miss'){
                     my $qOffset_count = $r_sonStats -> {'bymo'} -> {'EUtranCellRelation'} -> {'qOffset_changes_count'} -> {'db_values'} -> {$db_value} -> {$op . "_" . $op_type};
                     if (! defined $qOffset_count){
                         $qOffset_count = 0;
                     }
                     $line .="\t$qOffset_count";
                 }
             }
             print BCP "$line\n";
     }
     close BCP;

     dbDo($dbh, "DELETE FROM son_qOffset_changes WHERE siteid = $siteId AND date = '$date'")
         or die "Failed to delete data from son_qOffset_changes" . $dbh->errstr;
     dbDo($dbh,"LOAD DATA LOCAL INFILE \'$bcpFile\' INTO TABLE son_qOffset_changes")
         or die "Failed to load data into son_qOffset_changes" . $dbh->errstr;
     unlink($bcpFile);
     }
}

sub updateCioQoffsetCount($$$$$$) {
     my ($ts, $r_sonStats, $moi, $lastModType, $value, $name) = @_;

     my ($moType) = $moi =~ /,([^=,]+)=([^,=]+)$/;
     if ( ! defined $moType ) {
        print "WARN: Failed to extract moType from $moi\n";
        return;
     }

     my ($meConId) = $moi =~ /MeContext=([^,]+),/;
     if ( ! defined $moType ) {
        print "WARN: Failed to extract meContext id from $moi\n";
        return;
     }

     if ($name eq 'cellIndividualOffsetEUtran'){
         if ( !defined $lastModType ) { #if a cache miss occurs
             $lastModType = 'modify_cache_miss';
             updateCioCount($r_sonStats, $ts, $meConId, $moType, $value, $lastModType);
         } elsif ($lastModType eq 'modify_operator') {
             updateCioCount($r_sonStats, $ts, $meConId, $moType, $value, $lastModType);
         } elsif ($lastModType eq 'modify_mro') {
             updateCioCount($r_sonStats, $ts, $meConId, $moType, $value, $lastModType);
         } else {
             $lastModType = 'modify_other';
             updateCioCount($r_sonStats, $ts, $meConId, $moType, $value, $lastModType);
         }
     }

     if ($name eq 'qOffsetCellEUtran'){
         if ( !defined $lastModType ) {
             $lastModType = 'modify_cache_miss';
             updateQOffsetCount($r_sonStats, $ts, $meConId, $moType, $value, $lastModType);
         } elsif ($lastModType eq 'modify_operator') {
             updateQOffsetCount($r_sonStats, $ts, $meConId, $moType, $value, $lastModType);
         } elsif ($lastModType eq 'modify_mro') {
             updateQOffsetCount($r_sonStats, $ts, $meConId, $moType, $value, $lastModType);
         } else {
             $lastModType = 'modify_other';
             updateQOffsetCount($r_sonStats, $ts, $meConId, $moType, $value, $lastModType);
         }
     }

}

sub updateCioCount($$$$$$) {
     my ($r_sonStats, $ts, $meConId, $moType, $cio_value, $lastModType) = @_;

     $r_sonStats -> {'bynode'} -> {$meConId} -> {'cio_changes_count'} -> {'db_values'} -> {$cio_value} -> {$lastModType}++; #mod_type can be 'modify_operator', 'modify_mro' etc.
     $r_sonStats -> {'bymo'}   -> {$moType}   -> {'cio_changes_count'} -> {'db_values'} -> {$cio_value} -> {$lastModType}++;
     $r_sonStats -> {'bytime'} -> {$ts}        -> {'cio_changes_count'} -> {$lastModType.'_'.'total'}++;
     $r_sonStats -> {'bynode'} -> {$meConId} -> {'cio_changes_count'} -> {$lastModType.'_'.'total'}++;
     $r_sonStats -> {'bymo'}   -> {$moType}   -> {'cio_changes_count'} -> {$lastModType.'_'.'total'}++;
}

sub updateQOffsetCount($$$$$$) {
     my ($r_sonStats, $ts, $meConId, $moType, $qOffset_value, $lastModType) = @_;

     $r_sonStats -> {'bynode'} -> {$meConId} -> {'qOffset_changes_count'} -> {'db_values'} -> {$qOffset_value} -> {$lastModType}++;
     $r_sonStats -> {'bymo'}   -> {$moType}   -> {'qOffset_changes_count'} -> {'db_values'} -> {$qOffset_value} -> {$lastModType}++;
     $r_sonStats -> {'bytime'} -> {$ts}        -> {'qOffset_changes_count'} -> {$lastModType.'_'.'total'}++;
     $r_sonStats -> {'bynode'} -> {$meConId} -> {'qOffset_changes_count'} -> {$lastModType.'_'.'total'}++;
     $r_sonStats -> {'bymo'}   -> {$moType}   -> {'qOffset_changes_count'} -> {$lastModType.'_'.'total'}++;
}

sub parseCioValue($) {
     my ($value) = @_;

     my @cio_values = split /->/, $value;
     my $cio_value = pop @cio_values;
     $cio_value =~ s/^\s+|\s+$//g;
     return $cio_value;
}

sub parseQoffsetValue($) {
     my ($value) = @_;

     my @qOffset_values = split /->/, $value;
     my $qOffset_value = pop @qOffset_values;
     $qOffset_value =~ s/^\s+|\s+$//g;
     return $qOffset_value;
}

sub parseElectricalTiltValue($) {
     my ($value) = @_;
     my @electrical_tilts = split /->/, $value;
     return @electrical_tilts;
}

1;