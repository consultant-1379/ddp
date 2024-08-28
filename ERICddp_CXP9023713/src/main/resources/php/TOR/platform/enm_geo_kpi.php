<?php
$pageTitle = "GEO-R";

include_once "../../common/init.php";

require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once 'HTML/Table.php';

const TITLE = 'title';
const EXPORT = 'Export';
const IMPORT = 'Import';
const COUNT = 'count';
const VNFLCM = "VNFLCM";
const RATE = 'ROUND(count/(duration/1000),2)';

function tableParams( $app, $usecase ) {
    $params = array(
        'Application' => 'application',
        'Start Time' => 'TIMEDIFF(time(time),
        SEC_TO_TIME(ROUND(duration/1000)))',
        'End Time' => 'time(time)',
        'Total Duration' => 'SEC_TO_TIME(ROUND(duration/1000))'
    );

    if ($app == "CM") {
        $sectionTitle = "$usecase of CM data";
        $params["No of MOs {$usecase}ed"] = COUNT;
        $params['MO/sec'] = RATE;
    } elseif ($app == "PKI") {
        $sectionTitle = "Export of PKI Entity Profiles";
        $params['Total entities Exported'] = COUNT;
        $params['Entity/sec'] = RATE;
    } elseif ($app == "IDAM") {
        $sectionTitle = "$usecase of IDAM User Data";
        $params["Total users {$usecase}ed"] = COUNT;
        $params['User/sec'] = RATE;
    } elseif ($app == "FM" || $app == "NFS" || $app == "FMX" || $app == "ENMLogs") {
        $sectionTitle = "$usecase of $app";
        $params["Total data {$usecase}ed in MB"] = 'totaldata';
        $params['MB/sec'] = 'ROUND(totaldata/(duration/1000),2)';
    } elseif ($app == "LDAP" || $app == VNFLCM ) {
        $sectionTitle = "$usecase of $app";
    } elseif ($app == "TotalExport" || $app == "TotalImport") {
        $sectionTitle = "$app";
    } elseif ($app == "CMDeltaImport" || $app == "CMPrePopulation") {
        $sectionTitle = "Import of $app";
    } elseif ($app == "SECADM") {
        $sectionTitle = "Import of $app Data";
        $params['Total NEs Imported'] = COUNT;
        $params['NE\'s/Sec'] = RATE;
    } elseif ($app == "NCM") {
        $sectionTitle = "$usecase of NCM";
    } elseif ($app == "NHM") {
        $sectionTitle = "$usecase of NHM";
        $params["Total KPIs {$usecase}ed"] = COUNT;
    }

    return array( TITLE => $sectionTitle, 'params' => $params );
}

function drawTables( $app, $tableParams, $usecase ) {
    global $statsDB;

    $where =  $statsDB->where('enm_geo_kpi_logs');

    $where .= " AND enm_geo_kpi_logs.application = '$app' AND enm_geo_kpi_logs.usecase = '$usecase'";

    $builder = SqlTableBuilder::init()
           ->name($app.$usecase)
           ->tables( array('enm_geo_kpi_logs', StatsDB::SITES) )
           ->where($where);

    foreach ($tableParams['params'] as $key => $value) {
        $builder->addSimpleColumn("$value", $key);
    }

    $table = $builder->build();
    if ( $table->hasRows() ) {
        echo $table->getTableWithHeader(
            $tableParams[TITLE],
            2,
            "",
            "",
            $tableParams[TITLE]
        );
    }
}

function mainFlow() {

    drawHeader('Available Data', 1, 'data');
    $expApps = array('CM', 'PKI', 'IDAM', 'NFS', 'FMX', 'ENMLogs', 'LDAP', VNFLCM, 'TotalExport', 'NCM', 'NHM');
    $impApps = array('CMPrePopulation', 'CMDeltaImport', 'FM', 'NFS', 'FMX', 'IDAM', 'SECADM', VNFLCM,
                     'TotalImport', 'NCM', 'NHM');

    foreach ($impApps as $app) {
        $tableParams =  tableParams( $app, IMPORT );
        drawTables( $app, $tableParams, IMPORT );
    }
    foreach ($expApps as $app) {
        $tableParams =  tableParams( $app, EXPORT );
        drawTables( $app, $tableParams, EXPORT );
    }
}
mainFlow();

include_once PHP_ROOT . "/common/finalise.php";
