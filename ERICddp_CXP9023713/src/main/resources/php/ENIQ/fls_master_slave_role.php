<?php
$pageTitle = "FLS Instrumentation";
include "../common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once 'HTML/Table.php';

echo '<h1>File Lookup Service</h1>';
$flsServerRoleHelp = <<<EOT
    The following table gives information about the role of servers for File Lookup Service(FLS) on ENIQ STATS.
EOT;
drawHeaderWithHelp("Server Role", 2, "flsServerRoleHelp", $flsServerRoleHelp);
class FlsServerRoleTable extends DDPObject {
    var $cols = array(
        'serverName'   => 'Server Name',
        'roleType'     => 'Role'
    );

    function __construct() {
        parent::__construct("FlsServerRoleTable");
    }

    function getData() {
        global $date;
        global $site;

        $sql = "
            SELECT
             serverName, roleType
            FROM
             eniq_fls_master_slave_details, sites, fls_role_type_id_mapping, fls_server_name_id_mapping
            WHERE
             sites.name = '$site' AND
             sites.id = eniq_fls_master_slave_details.siteId AND
             eniq_fls_master_slave_details.roleId  = fls_role_type_id_mapping.id AND
             eniq_fls_master_slave_details.serverId = fls_server_name_id_mapping.id AND
             eniq_fls_master_slave_details.time BETWEEN '" . $date . " 00:00:00' AND '" . $date . " 23:59:59'
        ";
        $this->populateData($sql);
        return $this->data;
    }
}
$flsServerRole = new FlsServerRoleTable();
echo $flsServerRole->getSortableHtmlTable();

include "../common/finalise.php";
?>