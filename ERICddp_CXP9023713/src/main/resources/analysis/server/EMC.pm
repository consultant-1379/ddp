package EMC;

use StatsDB;

sub registerSysForSite($$$$) {
    my ($dbh, $siteId, $date, $r_sysList) = @_;

    my $r_sysToId = getIdMap($dbh, "emc_sys", "id", "name", $r_sysList);
    my %sysIds = ();
    # Need to check that the systems has been registered from this site
    foreach my $sys ( @{$r_sysList} ) {
        my $sysId = $r_sysToId->{$sys};
        $sysIds{$sys} = $sysId;
        my $r_rows = dbSelectAllArr($dbh, "SELECT COUNT(*) FROM emc_site WHERE sysid = $sysId AND siteid = $siteId AND filedate = '$date'");
        if ( $r_rows->[0]->[0] == 0 ) {
            dbDo($dbh, sprintf("INSERT INTO emc_site (sysid,siteid,filedate) VALUES (%d,%d,'%s')",
                               $sysId, $siteId, $date))
                or die "Cannot insert site reference";
        }
    }
    return \%sysIds;
}

1;
