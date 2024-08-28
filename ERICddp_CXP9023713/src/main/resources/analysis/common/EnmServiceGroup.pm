package EnmServiceGroup;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT = qw(enmGetServiceGroupInstances);

use warnings;
use strict;

use StatsDB;

#
# If service is undefined, return all services
# If service starts or ends with % return all services whose name is LIKE service
# Otherwise return all instances whose name = service
#
sub enmGetServiceGroupInstances($$$) {
    my ($site,$date,$service) = @_;

    my $filter = "";
    if ( defined $service ) {
	if ( $service =~ /^%/ || $service =~ /%$/ ) {
	    $filter = " AND enm_servicegroup_names.name LIKE '$service'"		
	} else {
	    $filter = " AND enm_servicegroup_names.name = '$service'";
	}
    }
    
    my $dbh = connect_db();
    my %serverMap = ();
    my $r_rows = dbSelectAllArr($dbh,"
SELECT
 servers.hostname AS hostsvr, servers.id AS srvid
FROM enm_servicegroup_instances, enm_servicegroup_names, servers, sites
WHERE
 enm_servicegroup_instances.siteid = sites.id AND sites.name = '$site' AND
 enm_servicegroup_instances.date = '$date' AND
 enm_servicegroup_instances.serverid = servers.id AND
 enm_servicegroup_instances.serviceid = enm_servicegroup_names.id
 $filter
ORDER BY servers.hostname
");
    foreach my $r_row ( @{$r_rows} ) {
       $serverMap{$r_row->[0]} = $r_row->[1];

    }

    return \%serverMap;
}

1;
