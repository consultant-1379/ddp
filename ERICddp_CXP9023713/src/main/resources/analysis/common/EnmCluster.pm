package EnmCluster;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT = qw(enmClustSvcSrv enmClustEvtSrv enmClustHostSrv enmClustAllVms);

use warnings;
use strict;

use StatsDB;

sub enmClustSvcSrv($$$) {
    my ($site,$date,$svc) = @_;

    my $dbh = connect_db();
    my %serverMap = ();
    my $r_rows = dbSelectAllArr($dbh,"
SELECT servers.hostname, servers.id
FROM sites, servers, enm_cluster_svc, enm_cluster_svc_names
WHERE
  enm_cluster_svc.siteid = sites.id AND sites.name = '$site' AND
  enm_cluster_svc.date = '$date' AND
  enm_cluster_svc.vmserverid = servers.id AND
  enm_cluster_svc.serviceid = enm_cluster_svc_names.id AND enm_cluster_svc_names.name = '$svc'");
    foreach my $r_row ( @{$r_rows} ) {
       $serverMap{$r_row->[0]} = $r_row->[1];

    }

    return \%serverMap;
}

sub enmClustEvtSrv($$$) {
    my ($site,$date,$svc) = @_;

    my $dbh = connect_db();
    my %serverMap = ();
    my $r_rows = dbSelectAllArr($dbh,"
SELECT servers.hostname, servers.id
FROM sites, servers, enm_cluster_svc, enm_cluster_svc_names
WHERE
  enm_cluster_svc.siteid = sites.id AND sites.name = '$site' AND
  enm_cluster_svc.date = '$date' AND
  enm_cluster_svc.vmserverid = servers.id AND
  enm_cluster_svc.serviceid = enm_cluster_svc_names.id AND enm_cluster_svc_names.name like '$svc'");
    foreach my $r_row ( @{$r_rows} ) {
        $serverMap{$r_row->[0]} = $r_row->[1];
    }

    return \%serverMap;
}

sub enmClustAllVms($$) {
    my ($site,$date) = @_;
    my $dbh = connect_db();
    my %serverMap = ();
    my $r_rows = dbSelectAllArr($dbh,"
SELECT servers.hostname, servers.id
FROM sites, servers, enm_cluster_svc, enm_cluster_svc_names
WHERE
  enm_cluster_svc.siteid = sites.id AND sites.name = '$site' AND
  enm_cluster_svc.date = '$date' AND
  enm_cluster_svc.vmserverid = servers.id AND
  enm_cluster_svc.serviceid = enm_cluster_svc_names.id");

   foreach my $r_row ( @{$r_rows} ) {
       $serverMap{$r_row->[0]} = $r_row->[1];
   }

   return \%serverMap;
}

sub enmClustHostSrv($$$) {
    my ($site,$date,$clusterType) = @_;

    my $dbh = connect_db();
    my %serverMap = ();
    my $r_rows = dbSelectAllArr($dbh,"
SELECT servers.hostname, servers.id
FROM enm_cluster_host, servers, sites
WHERE
  enm_cluster_host.siteid = sites.id AND
  enm_cluster_host.serverid = servers.id AND
  enm_cluster_host.clustertype = '$clusterType' AND
  sites.name = '$site' AND
  enm_cluster_host.date = '$date'");

    foreach my $r_row ( @{$r_rows} ) {
        $serverMap{$r_row->[0]} = $r_row->[1];
    }

    return \%serverMap;
}

1;
