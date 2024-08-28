<?php

include_once "init.php";
include_once "../php/common/countries.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

function getDateArray() {
    global $statsDB, $site;

    $dateArr = array();
    $sql = "
SELECT
    date
FROM
    site_data, sites
WHERE
    site_data.siteid = sites.id AND
    sites.name = '$site'
ORDER BY date DESC LIMIT 30";

    $statsDB->query($sql);
    $numRows = $statsDB->getNumRows();
    for ($i = 0; $i < $numRows; $i++) {
        $row = $statsDB->getNextRow();
        foreach ($row as $value) {
            array_push($dateArr, $value);
        }
    }
    return $dateArr;
}

function displayHCForm( $dateArr ) {
    echo "<h2>Reprocess Health Checks:</h2>";
    $form = reprocessHealthChecksForm( $dateArr );
    $form->display();
}

function reprocessHC( $dateArr, $oss ) {
    global $ddp_dir, $site;

    $key = requestValue(HC_DATE);

    $params = "--site $site --date $dateArr[$key] --oss $oss 2>&1";
    callGenericPhpToRootWrapper( 'executeRules', $params );
}

function dateConversion( $date ) {
    if ( preg_match('/^\d\d(\d\d)-(\d{2})-(\d{2})$/', $date, $matches) === 1 ) {
        return $matches[3] . $matches[2] . $matches[1];
    } elseif (preg_match('/^(\d\d)(\d\d)(\d\d)$/', $date, $matches) === 1) {
        return "20" . $matches[3] . "-" . $matches[2] . "-" . $matches[1];
    }
}

function displayAlertMeForm( $dateArr ) {
    global $statsDB;

    echo "<h2>Run AlertMe:</h2>";

    $sql = "SELECT id, reportname FROM ddpadmin.ddp_custom_reports";
    $statsDB->query($sql);
    $reports = array( array('id' => '0', 'reportname' => 'Default') );

    while ( $row = $statsDB->getNextNamedRow() ) {
        $reports[] = $row;
    }
    $form = runAlertMeForm( $reports, $dateArr );
    $form->display();
}

function runAlertMe( $dateArr, $oss ) {
    global $ddp_dir, $site;

    $reportId = requestValue(REPORT_ID);
    $reportDate = requestValue('reportDate');
    $dir = dateConversion( $dateArr[$reportDate] );
    $time = date("H:i:s", time());
    $fqdn = getDDPServerName();

    $params = "--site $site --fqdn $fqdn --dir $dir --date $dateArr[$reportDate]";
    $params .= " --time $time --oss $oss --mailhost localhost --reportid $reportId 2>&1";

    callGenericPhpToRootWrapper( 'alertMe', $params );
}

function reprocessHealthChecksForm( $arr ) {
    // Instantiate the HTML_QuickForm object
    $form = new HTML_QuickForm('reprocess_hc', 'POST', '?' . fromServer(QUERY_STRING) );

    // date
    $form->addElement(SELECT, HC_DATE, 'Reprocess Health Checks for: ', $arr);

    // submit
    $form->addElement(SUBMIT, null, 'Reprocess');
    return $form;
}

function runAlertMeForm( $data, $dateArr ) {
    // Instantiate the HTML_QuickForm object
    $form = new HTML_QuickForm('run_alertMe', 'POST', '?' . fromServer(QUERY_STRING));

    $names = array();
    foreach ( $data as $item ) {
        $names[$item['id']] = $item['reportname'];
    }
    //Reports
    $form->addElement(SELECT, REPORT_ID, 'Select a report: ', $names);

    //Date
    $form->addElement(SELECT, 'reportDate', 'Select a Date: ', $dateArr);

    // submit
    $form->addElement(SUBMIT, null, 'Run alertMe');
    return $form;
}


function runFlows( $dateArr, $oss ) {
    if ( issetURLParam(HC_DATE) ) {
        reprocessHC( $dateArr, $oss );
    } elseif ( issetURLParam(REPORT_ID) ) {
        runAlertMe( $dateArr, $oss );
    }
}

function mainFlow() {
    global $site;

    if ( issetURLParam('selectedSite') ) {
        $site = requestValue('selectedSite');
        $data = getSiteData();

        if (! is_array($data)) {
            echo "<b>Problem retrieving site data</b>\n";
        } else {
            $oss = strtolower($data['site_type']);
            $dateArr = getDateArray();

            runFlows( $dateArr, $oss );
            displayHCForm( $dateArr );
            displayAlertMeForm( $dateArr );
        }
    } else {
        drawHeader('Reprocess HC', 1, "");
        getSiteIndex( '30' );
    }
}

mainFlow();

include_once '../php/common/finalise.php';

