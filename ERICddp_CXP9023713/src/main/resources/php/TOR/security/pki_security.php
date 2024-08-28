<?php
$pageTitle = 'PKI Security';

include_once '../../common/init.php';
require_once PHP_ROOT . '/classes/ModelledTable.php';

function params() {
    return array(
        array('pki_entity_man_daily_totals', 'EM_DT', 'Entity Management Daily Totals'),
        array('pki_ca_cert_man_daily_totals', 'CA_CM_DT', 'CA Certificate Management Daily Totals'),
        array('pki_entity_cert_man_daily_totals', 'ECM_DT', 'Entity Certificate Management Daily Totals'),
        array('pki_crl_man_daily_totals', 'CRLM_DT', 'CRL Management Daily Totals'),
        array('pki_revocation_man_daily_totals', 'RM_DT', 'Revocation Management Daily Totals'),
        array('pki_cdps_daily_totals', 'CDPS', 'CDPS'),
        array('pki_cdps_log', 'CDPS_logs', 'CDPS FAILED OPERATIONS'),
        array('pki_tdps_daily_totals', 'TDPS', 'TDPS'),
        array('pki_tdps_log', 'TDPS_log', 'TDPS FAILED OPERATIONS'),
        array('pki_scep_daily_totals', 'SCEP', 'SCEP'),
        array('pki_cmp_daily_totals', 'CMP', 'CMP')
    );
}

function mainFlow() {
    $params = params();
    $links = array();
    $tables = array();

    foreach ($params as $param) {
        $table = new ModelledTable( "TOR/security/$param[0]", $param[1] );
        if ( $table->hasRows() ) {
            $links[] = makeAnchorLink($param[1], $param[2]);
            $tables[] = $table->getTableWithHeader($param[2]);
        }
    }
    echo makeHTMLList($links);
    foreach ( $tables as $tab ) {
        echo $tab;
    }
}

mainFlow();
include_once PHP_ROOT . '/common/finalise.php';

