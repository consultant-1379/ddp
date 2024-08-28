<?php
$pageTitle = "Service Restart Information";
include_once "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";
const SITES = 'sites';
const ENIQ_COREDUMP_DETAILS = 'eniq_coredump_details';

//SQL Builder Tables
function createTable( $params, $name, $title, $table, $where ) {
    global $statsDB;

    drawHeaderWithHelp($title, 2, $name);

    $table = SqlTableBuilder::init()
            ->name($name)
            ->tables($table)
            ->where($where);

    foreach ($params as $key => $value) {
        $table->addSimpleColumn($value, $key);
    }

    echo $table->paginate( array(20, 100, 1000, 10000) )
               ->build()
               ->getTable();
    echo addLineBreak(2);
}

main();

function main() {
    global $site, $date;
    $statsDB = new StatsDB();
    $servicesTableAttributes = array (
        'width' => '500',
        'style' => 'text-align:center'
    );
    $coreDumpCount = $statsDB->hasData(ENIQ_COREDUMP_DETAILS, 'occurrence_time', false);

    if ( $coreDumpCount ) {
        $colsArr = array(
                         'Status'    => '"Yes"',
                         'Occurrence Time' => 'eniq_coredump_details.occurrence_time',
                    );
        $name = 'coredumpTitleHelp';
        $title = 'Sybase Coredump';
        $table = array(ENIQ_COREDUMP_DETAILS, SITES);
        $where = $statsDB->where(ENIQ_COREDUMP_DETAILS, 'occurrence_time');
        createTable( $colsArr, $name, $title, $table, $where );
    }

    getSmfServiceTable($statsDB, $servicesTableAttributes);

}

function getSmfServiceTable($statsDB, $servicesTableAttributes) {
    global $date, $site;

    drawHeader("Service Restart", 2, "smfServicesHelp");
    $tableSmfServices = new HTML_Table($servicesTableAttributes);
    $tableSmfServices->addRow( array('<b>Server Name</b>', '<b>Service name</b>', '<b>Number of Occurences</b>', '<b>Last Restart Time</b>') );
    $statsDB->query("
        SELECT
            count(eniq_smf_restart_details.service_id) as occurence, eniq_smf_restart_details.service_id, eniq_smf_restart_details.server_id
        FROM
            eniq_smf_restart_details, eniq_smf_services, sites
        WHERE
            sites.name = '$site' AND
            eniq_smf_restart_details.service_id = eniq_smf_services.service_id AND
            sites.id = eniq_smf_restart_details.site_id AND
            eniq_smf_restart_details.restart_time between '$date 00:00:00' AND '$date 23:59:59'
        GROUP BY
            eniq_smf_services.service_id,eniq_smf_restart_details.server_id
        ORDER BY
            eniq_smf_restart_details.server_id, eniq_smf_restart_details.service_id
    ");

    $arrOfRestartCount = array();
    $arrOfServiceId = array();
    $arrOfServerId = array();

    while( $rowOfCounts = $statsDB->getNextNamedRow() ) {
        $arrOfRestartCount[] = $rowOfCounts['occurence'];
        $arrOfServiceId[] = $rowOfCounts['service_id'];
        $arrOfServerId[] = $rowOfCounts['server_id'];
    }

    $statsDB->query("
        SELECT
            servers.hostname, eniq_smf_services.service_name, max(eniq_smf_restart_details.restart_time) as restart_time, eniq_smf_restart_details.service_id, eniq_smf_restart_details.server_id as server_id
        FROM
            eniq_smf_restart_details, eniq_smf_services, servers, sites
        WHERE
            sites.name = '$site' AND
            eniq_smf_restart_details.service_id = eniq_smf_services.service_id AND
            servers.id = eniq_smf_restart_details.server_id AND
            sites.id = eniq_smf_restart_details.site_id AND
            eniq_smf_restart_details.restart_time <= '$date 23:59:59'
        GROUP BY
            eniq_smf_services.service_id, eniq_smf_restart_details.server_id
        ORDER BY
            hostname, eniq_smf_restart_details.service_id
    ");
    $arrlength = count($arrOfServiceId);

    while ( $rowSMFServices = $statsDB->getNextNamedRow() ) {
        $restartDate = $rowSMFServices['restart_time'];
        $todayStartDate = "$date 00:00:00";
        if(strtotime($restartDate) >= strtotime($todayStartDate)) {
            for($index = 0; $index < $arrlength; $index++) {
                if($rowSMFServices['service_id'] == $arrOfServiceId[$index] &&  $rowSMFServices['server_id'] == $arrOfServerId[$index]) {
                    $tableSmfServices->addRow( array('<font color = "red">' . $rowSMFServices['hostname'] . '</font>', '<font color = "red">' . $rowSMFServices['service_name'] . '</font>', '<font color = "red">' . $arrOfRestartCount[$index] . '</font>', '<font color = "red">' .  $rowSMFServices['restart_time'] . '</font>') );
                }
            }
        }
        else {
            $tableSmfServices->addRow( array($rowSMFServices['hostname'], $rowSMFServices['service_name'], 0, $rowSMFServices['restart_time'] ) );
        }
    }

    echo $tableSmfServices->toHTML();
    echo addLineBreak(2);
}

include "../common/finalise.php";
