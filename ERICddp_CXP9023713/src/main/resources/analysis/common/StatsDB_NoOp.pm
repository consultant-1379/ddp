package StatsDB_NoOp;

sub connect_db() {
    return undef;
}

sub createServer($$$$) {
    my ( $dbh, $siteId, $host, $type ) = @_;

    return 1;
}

sub dbDo($$) {
    my ( $dbh, $sql ) = @_;

    return 0;
}

sub dbSelectAllArr($$) {
    my ( $dbh, $sql ) = @_;

    return [];
}

sub dbSelectAllHash($$) {
    my ( $dbh, $sql ) = @_;

    return [];
}

sub getIdMap {
    my ( $dbh, $tableName, $idCol, $nameCol, $r_ValList, $extraColValue, $colName ) = @_;

    my %result = ();
    my $index = 1;
    foreach my $value ( @{$r_ValList} ) {
        $result{$value} = $index;
        $index++;
    }
    return \%result;
}

sub getServerId($$$) {
    my ( $dbh, $siteId, $host ) = @_;

    return 1;
}

sub getServerIdWithoutFail($$$) {
    my ( $dbh, $siteId, $host ) = @_;

    return 1;
}

sub getSiteId($$) {
    my ( $dbh, $site ) = @_;

    return 1;
}

1;
