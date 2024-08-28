<?php
$pageTitle = "COM-AA external LDAP Stats";

$YUI_DATATABLE = true;

include "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

require_once 'HTML/Table.php';

const DDPFORMATNUMBER = "'formatter' => 'ddpFormatNumber'";
const EXTERNALIDPBINDSUCCESS = "externalIdpBindSuccess";
const EXTERNALIDPSEARCHREQUESTS = "externalIdpSearchRequests";
const EXTERNALIDPSEARCHRESPONSEERROR = "externalIdpSearchResponseError";
const EXTERNALIDPSEARCHRESPONSESUCCESS = "externalIdpSearchResponseSuccess";
const EXTERNALIDPBINDFAILED = "externalIdpBindFailed";
const TITLE = "title";
const LABEL = "label";

function comaaExtIdpSecurityStatsTotals($statsDB) {
    global $site, $date;
    $where = $statsDB->where("enm_secserv_comaaExtIdp_instr");
    $where .= 'AND enm_secserv_comaaExtIdp_instr.serverid = servers.id GROUP BY servers.hostname WITH ROLLUP';
    $reqBind = SqlTableBuilder::init()
              ->name("dailyTotalHelp")
              ->tables(array('enm_secserv_comaaExtIdp_instr', 'servers', StatsDB::SITES))
              ->where($where)
              ->addSimpleColumn('IFNULL(servers.hostname,\'Totals\')', 'inst')
              ->addSimpleColumn('SUM(externalIdpBindFailed)', 'External LDAP Bind Failed')
              ->addSimpleColumn('SUM(externalIdpBindSuccess)', 'External LDAP Bind Success')
              ->addSimpleColumn('SUM(externalIdpSearchResponseError)', 'External LDAP Search Response Error')
              ->addSimpleColumn('SUM(externalIdpSearchRequests)', 'External LDAP Search Requests')
              ->addSimpleColumn('SUM(externalIdpSearchResponseSuccess)', 'External LDAP Search Response Success')
              ->paginate()
              ->build();
    echo $reqBind->getTableWithHeader(
        "Daily Totals",
        1,
        "DDP_Bubble_473_ENM_COMAA_Ext_Idp_Daily_Totals",
        "",
        "dailyTotalHelp"
    );

}

function showComaaExtIdpSecurityServiceGraphs() {
    global $date;
    /* Graphs  */
    $sqlParamWriter = new SqlPlotParam();
    drawHeader("COMAA External LDAP Instrumentation Graphs", HEADER_1, "COMAA_Ext_Idp_Instrumentation_Graphs");
    $graphTable = new HTML_Table("border=0");
    $instrGraphParams = array(
      array(EXTERNALIDPBINDFAILED => array(
        TITLE => 'External LDAP Bind Failed',
        'ylabel' => 'Count',
        'cols' => array(
            EXTERNALIDPBINDFAILED  => EXTERNALIDPBINDFAILED
            )
        ),
        EXTERNALIDPBINDSUCCESS => array(
        TITLE => 'External LDAP Bind Success',
        'ylabel' => 'Count',
        'cols' => array(
            EXTERNALIDPBINDSUCCESS  => EXTERNALIDPBINDSUCCESS
            )
          )
        ),
      array(EXTERNALIDPSEARCHREQUESTS => array(
        TITLE => 'External LDAP Search Requests',
        'ylabel' => 'Count',
        'cols' => array(
            EXTERNALIDPSEARCHREQUESTS  => EXTERNALIDPSEARCHREQUESTS
            )
        ),
        EXTERNALIDPSEARCHRESPONSEERROR => array(
        TITLE => 'External LDAP Search Response Error',
        'ylabel' => 'Count',
        'cols' => array(
            EXTERNALIDPSEARCHRESPONSEERROR  => EXTERNALIDPSEARCHRESPONSEERROR
            )
          )
        ),
      array(EXTERNALIDPSEARCHRESPONSESUCCESS => array(
        TITLE => 'External LDAP Search Response Success',
        'ylabel' => 'Count',
        'cols' => array(
            EXTERNALIDPSEARCHRESPONSESUCCESS  => EXTERNALIDPSEARCHRESPONSESUCCESS
            )
         )
       )
    );

    foreach ( $instrGraphParams as $instrGraphParam ) {
     $row = array();
      foreach ( $instrGraphParam as $instrGraphParamName ) {
        $sqlParam = array(
        TITLE => $instrGraphParamName[TITLE],
        'type' => 'sb',
        'sb.barwidth' => 60,
        'ylabel'     => $instrGraphParamName['ylabel'],
        'persistent' => 'true',
        'useragg' => 'true',
        'querylist' => array(
            array(
                'timecol' => 'time',
                'whatcol' => $instrGraphParamName['cols'],
                'tables'  => "enm_secserv_comaaExtIdp_instr, sites, servers",
                "multiseries" => "servers.hostname",
                'where'   => "enm_secserv_comaaExtIdp_instr.siteid = sites.id AND sites.name = '%s' AND enm_secserv_comaaExtIdp_instr.serverid = servers.id",
                'qargs'   => array('site')
                )
            )
        );
        $id = $sqlParamWriter->saveParams($sqlParam);
        $row[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 640, 320);
    }
    $graphTable->addRow($row);
  }
  echo $graphTable->toHTML();
}

function mainFlow($statsDB) {

    /* Daily Summary table */
    comaaExtIdpSecurityStatsTotals($statsDB);


    /* Get the graphs */
    showComaaExtIdpSecurityServiceGraphs();
}

$statsDB = new StatsDB();

mainFlow($statsDB);
include PHP_ROOT . "/common/finalise.php";

?>


