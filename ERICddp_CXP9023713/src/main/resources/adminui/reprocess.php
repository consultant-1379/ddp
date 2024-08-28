<?php

include_once "init.php";
include_once "../php/common/countries.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

function displayReprocessMessage( $reprocessRetVal ) {
    global $site, $php_webroot;

    $tarFile = 'DDC tar file';
    if ( issetURLParam('tar_file') ) {
        $tarFile = requestValue('tar_file');
    }

    echo addLineBreak();

    if ( $reprocessRetVal == 0 ) {

        echo <<<EOF
<b>Reprocessing of '{$tarFile}' of '{$site}' has been successfully initiated.<BR>
Please check <a href="$php_webroot../../adminui/service.php\">DDP Service Status</a> for more info,
It may take a couple of minutes for the reprocessing job be displayed on this page.<BR>
EOF;

    } elseif ( $reprocessRetVal == 3 ) {

        echo <<<EOF
<b>Failed to reprocess '{$tarFile}' of '{$site}' as another DDC tar file for the same date &
site is either currently being processed or waiting to be processed.</b><BR>\n
Please check <a href="$php_webroot../../adminui/service.php">DDP Service Status</a>
and try again once processing has completed.<BR>\n
EOF;

    } else {
        echo "<b>Failed to move {$tarFile} of '{$site}' from NAS to FTPROOT directory.</b>\n<br/>\n";
    }
}

function reprocessForm( $datesArr ) {
    // Instantiate the HTML_QuickForm object
    $form = new HTML_QuickForm('reprocess_site', 'POST', '?' . fromServer(QUERY_STRING) );

    // date
    $form->addElement(SELECT, DATE_FILE, 'File to be processed:', $datesArr);

    // submit
    $form->addElement(SUBMIT, null, 'Reprocess...');
    return $form;
}

function reprocessSite( $nassitepath, $site, $datesArr ) {
    global $statsDB, $AdminDB, $DBName;

    $key = requestValue(DATE_FILE);
    $sql = "SELECT homedir FROM ddpadmin.ftpusers, sites WHERE siteid = sites.id and name = '" . $site . "'";
    $statsDB->query($sql);
    $row = $statsDB->getNextNamedRow();
    $ftpsitePath = $row['homedir'];
    $ddcTarFile = "$datesArr[$key]";
    $statsDB->exec("use $AdminDB");
    $sql = "SELECT * FROM file_processing WHERE site = '$site' AND file like '%{$statsDB->escape($ddcTarFile)}'";
    $statsDB->query($sql);
    $reprocessRetVal = 0;
    if ( $statsDB->getNumRows() == 0 ) {
        //No DDC tar for the given site and date is currently being processed.
        //So go ahead with moving the tar file from NAS to FTPROOT
        $source = "$nassitepath/$ddcTarFile";
        $dest = "$ftpsitePath/$ddcTarFile";
        $reprocessRetVal = execSiteMgt("-r -N " . $source . " -O " . $dest);
    } else {
        //Looks like another DDC tar file the same date and site is currently
        //getting processed. So don't proceed with the reprocessing
        $reprocessRetVal = 3;
    }
    $statsDB->exec("use $DBName");

    displayReprocessMessage( $reprocessRetVal );
}

function displayReprocessForm( $nassitepath, $site, $datesArr ) {
    if ( file_exists($nassitepath) ) {
        echo "<h2>Reprocess Site: $site</h2>";
        $form = reprocessForm( $datesArr );
        $form->display();
    }
}

function mainFlow() {
    global $site, $nas_data_files;
    $nassitepath = '';
    $datesArr = '';
    $testVm = isTestVm();

    if ( issetURLParam('selectedSite') ) {
        $site = requestValue('selectedSite');
        $data = getSiteData();

        if (! is_array($data)) {
            echo "<b>Problem retrieving site data</b>\n";
        } else {
            if ( $testVm == 0 ) {
                $nassitepath = "$nas_data_files/$site";
                if ( file_exists($nassitepath) ) {
                    $datesArr = preg_grep( '/^DDC_Data_\d{6}.tar.gz$/', scandir($nassitepath) );
                } else {
                    echo "<b>ERROR: NAS Directory does not exist for this site!</b>\n";
                }
            }
            if ( issetURLParam(DATE_FILE) ) {
                reprocessSite( $nassitepath, $site, $datesArr );
            }
            if ( $testVm == 0 ) {
                displayReprocessForm( $nassitepath, $site, $datesArr );
            }
        }
    } else {
        drawHeader('Reprocess Site', 1, "");
        getSiteIndex( '30' );
    }
}

mainFlow();

include_once '../php/common/finalise.php';

