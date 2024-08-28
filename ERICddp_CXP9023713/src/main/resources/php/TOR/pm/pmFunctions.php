<?php
include_once PHP_ROOT . "/common/graphFunctions.php";
const STOREDVOLUME = 'Stored Volume(KB)';

function flsParams() {
    return array(
            array(
                TITLE => FILES,
                'type' => 'sb',
                'cols' => array(
                    'files'  => FILES
                )
            ),
            array(
                TITLE => STOREDVOLUME,
                'type' => 'sb',
                'cols' => array(
                    'volumekb'  => STOREDVOLUME
                )
            ),
            array(
                TITLE => 'Files Outside FCC',
                'type' => 'sb',
                'cols' => array(
                    'outside'  => FILES
                )
            ),
            array(
                TITLE => 'Duration',
                'type' => 'tsc',
                'cols' => array(
                    'last_offset'  => 'Duration'
                )
            )
    );
}

function plotFls($rop, $transferType, $selectedStr) {
    global $date;

    $graphs = array();
    $title = $rop . " " . $transferType;
    $bubble = $rop . "_" . $transferType . ".flsROPHelp";
    drawHeader($title, 1, $bubble);

    $sqlParamWriter = new SqlPlotParam();
    $instrGraphParams = flsParams();

    $dbTables = array("enm_pmic_rop_fls", "enm_pmic_datatypes", "ne_types", StatsDB::SITES);
    $where = "
enm_pmic_rop_fls.rop = '%s' AND
enm_pmic_rop_fls.TRANSFERTYPE = '%s' AND
enm_pmic_rop_fls.siteid = sites.id AND sites.name = '%s' AND
enm_pmic_rop_fls.datatypeid =  enm_pmic_datatypes.id AND
enm_pmic_rop_fls.netypeid = ne_types.id AND
CONCAT(enm_pmic_rop_fls.netypeid,':',enm_pmic_rop_fls.datatypeid) IN ( %s )";

    $quotedIds = "'" . implode("','", explode(",", $selectedStr)) . "'";
    foreach ( $instrGraphParams as $instrGraphParam ) {
        $sqlParam = SqlPlotParamBuilder::init()
                  ->title($instrGraphParam['title'])
                  ->type($instrGraphParam['type'])
                  ->barwidth(60)
                  ->yLabel("")
                  ->forcelegend("true")
                  ->makePersistent()
                  ->addQuery(
                      'fcs',
                      $instrGraphParam['cols'],
                      $dbTables,
                      $where,
                      array( 'rop', 'TRANSFERTYPE', 'site',  'ids' ),
                      'CONCAT(ne_types.name,":",enm_pmic_datatypes.name)'
                  )
                  ->build();

        if ( $instrGraphParam['type'] == 'sb' ) {
            $sqlParam['sb.barwidth'] = str_replace("MIN", "", $rop) * 60;
        }

        $id = $sqlParamWriter->saveParams($sqlParam);
        $extra = "rop=$rop&TRANSFERTYPE=$transferType&ids=$quotedIds";
        $graphs[] = $sqlParamWriter->getImgURL($id, "$date 00:00:00", "$date 23:59:59", true, 800, 300, $extra);
    }
    plotGraphs($graphs);
}

