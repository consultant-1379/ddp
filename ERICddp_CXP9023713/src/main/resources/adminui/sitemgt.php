<?php

include_once "init.php";
include_once "../php/common/countries.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";

const SITE = "site ";

function createForm($data) {
    global $statsDB, $countries;

    echo '<h2>Edit Site:</h2>';

    // Instantiate the HTML_QuickForm object
    $form = new HTML_QuickForm(EDIT_SITE, 'POST', '?' . fromServer(QUERY_STRING));

    // name
    $form->addElement('text', 'site', 'Site Name:', 'value="' . $data['site'] . '"');

    // site_type
    $typeArr = array(
        'OSS' => "OSS",
        'ENIQ' => "ENIQ",
        'TOR' => "TOR",
        'NAVIGATOR' => 'Navigator',
        'SERVICEON' => "ServiceOn",
        'DDP' => "DDP",
        'GENERIC' => 'Generic',
        'EO' => "EO",
        'ECSON' => 'ECSON',
        'UNDEFINED' => "Undefined"
    );
    $typeSel = HTML_QuickForm::createElement(SELECT, SITE_TYPE, 'Site Type:', $typeArr);
    $typeSel->setSelected(array($data[SITE_TYPE]));
    $form->addElement($typeSel);

    // country
    $countrySel = HTML_QuickForm::createElement(SELECT, COUNTRY, 'Country:', $countries);
    $countrySel->setSelected(array($data[COUNTRY]));
    $form->addElement($countrySel);

    // operator
    $sql = "SELECT * FROM operators ORDER BY name ASC";
    $statsDB->query($sql);
    $opers = array();

    while ($row = $statsDB->getNextNamedRow()) {
        $opers[$row['id']] = $row['name'];
    }

    $operSel =  HTML_QuickForm::createElement(SELECT, OPERATOR, 'Operator:', $opers);
    $operSel->setSelected(array($data[OPERATOR]));
    $form->addElement($operSel);

    // status
    $statusArr = array(
        "" => "",
        "live" => "live",
        "lab" => "lab",
        "inactive" => "inactive",
    );
    $statusSel = HTML_QuickForm::createElement(SELECT, SITE_STATUS, 'Site Status:', $statusArr);
    $statusSel->setSelected(array($data[SITE_STATUS]));
    $form->addElement($statusSel);

    // ftp details
    $form->addElement('text', FTPUSERID, 'SFTP User ID:', 'value="' . $data[FTPUSERID] . '"');
    $form->addElement('password', 'ftppasswd', 'New SFTP Password:');
    $form->addElement('password', 'verify_ftppasswd', 'Verify Password:');

    // Deployment Infrastructure
    $sql = "SELECT * FROM deploy_infra ORDER BY name ASC";
    $statsDB->query($sql);
    $infra = array();

    while ($row = $statsDB->getNextNamedRow()) {
        $infra[$row['id']] = $row['name'];
    }

    $infraSel =  HTML_QuickForm::createElement(SELECT, DEPLOYMENT_INFRA, 'Deployment Infrastructure:', $infra);
    $infraSel->setSelected(array($data[DEPLOYMENT_INFRA]));
    $form->addElement($infraSel);

    // submit
    $form->addElement('submit', null, 'Update ...');

    return $form;
}

function checkIfSiteExists( $data ) {
    global $statsDB;

    $site = requestValue('site');

    if ( $site != $data['site'] ) {
        $sql = "SELECT COUNT(*) FROM sites WHERE name = '$site'";
        $row = $statsDB->queryRow($sql);
        if ( $row[0] > 0) {
            echo "<P>ERROR: Site with that name already exists!</p>";
            return true;
        }
    }
    return false;
}

function checkIfFTPExists( $data ) {
    global $statsDB, $AdminDB, $DBName, $ftproot_dir, $stats_dir;

    $ftpId = requestValue(FTPUSERID);
    $ftpDir =  "$ftproot_dir" . "/" . "$ftpId";
    if ( $ftpId != $data[FTPUSERID] ) {
        $statsDB->exec("use $AdminDB");
        $sql = "SELECT COUNT(*) FROM ftpusers WHERE userid = '$ftpId'";
        $row = $statsDB->queryRow($sql);
        $statsDB->exec("use $DBName");
        if ( $row[0] > 0) {
            echo "<P>ERROR: Site with that ftpuserid already exists!</p>";
            return true;
        }
        if ( file_exists($ftpDir) && is_dir($ftpDir) ) {
            echo "<P>ERROR: $ftpDir already exists!</P>";
            return true;
        }
    }
    return false;
}

function checkIfStatsDirExists( $data ) {
    global $stats_dir;

    $reqSite = requestValue('site');
    $reqType = requestValue(SITE_TYPE);

    $statsDir = $stats_dir . '/' . strtolower($reqType) . "/" . $reqSite . "/";
    $validChange = false;

    if ( ($reqSite != $data['site']) || ($reqType != $data[SITE_TYPE]) ) {
        $validChange = true;
    }
    if ( $validChange && file_exists($statsDir) && is_dir($statsDir) ) {
        echo "<P>ERROR: $statsDir already exists!</P>";
        return true;
    }
    return false;
}

function checkDataProcessing($data) {
    global $statsDB, $AdminDB, $DBName;

    // Proceed only if there is no DDC data being processed or waiting to be processed for this site
    $statsDB->exec("use $AdminDB");
    $sql = "SELECT * FROM file_processing WHERE siteid = " . $data['siteid'] . " LIMIT 1";
    $row = $statsDB->queryRow($sql);
    $statsDB->exec("use $DBName");
    $link = makeLink('../../adminui/service.php', 'Here');
    if ( ! empty($row) ) {
        echo <<<EOF
Unable to edit site at the moment as DDC data for this site is either being processed or waiting to be processed.<br/>\n
Please check the processing status $link and try again once processing has completed.<br/>\n
EOF;
        echo addLineBreak();
        return true;
    }
    return false;
}

function changeOperator( $data ) {
    global $statsDB;
    $operId = requestValue(OPERATOR);

    if (isset($operId) && is_numeric($operId) && $operId != $data[OPERATOR]) {
        echo "Changing operator.";
        echo addLineBreak();
        $sql = "UPDATE sites SET oper_id = {$operId} WHERE name = '{$data['site']}'";
        $statsDB->exec($sql);
        mgtlog(SITE . $data['site'] . ": changed operator ID to " . $operId);
        echo "Operator changed.\n";
        echo addLineBreak();
        return true;
    } else {
        return false;
    }
}

function changeDeployinfra( $data ) {
    global $statsDB;
    $infraId = requestValue(DEPLOYMENT_INFRA);

    if (isset($infraId) && is_numeric($infraId) && $infraId != $data[DEPLOYMENT_INFRA]) {
        echo "Changing Deployment Infrastructure.";
        echo addLineBreak();
        $sql = "UPDATE sites SET infra_id = {$infraId} WHERE name = '{$data['site']}'";
        $statsDB->exec($sql);
        mgtlog(SITE . $data['site'] . ": changed Deployment Infrastructure ID to " . $infraId);
        echo "Deployment Infrastructure changed.\n";
        echo addLineBreak();
        return true;
    } else {
        return false;
    }
}

function changeSiteType( $data ) {
    global $statsDB;

    $reqVal = requestValue(SITE_TYPE);
    $dataSiteType = $data[SITE_TYPE];
    $site = $data['site'];

    if ($reqVal != $dataSiteType) {
        echo "Changing site type from $dataSiteType to $reqVal.";
        echo addLineBreak();
        $lowDataType = strtolower($dataSiteType);
        $lowReqType = strtolower($reqVal);
        $ftpId = $data[FTPUSERID];
        $cmd = "-m -s  $site -t $lowDataType  -T $lowReqType -f $ftpId";

        if ( execSiteMgt($cmd) == 0 ) {
            $sql = "UPDATE sites SET site_type = '$reqVal' WHERE name = '$site'";
            $statsDB->exec($sql);
            mgtlog(SITE . $site . ": changed site_type from " . $dataSiteType . " to " . $reqVal);
            echo "Site type changed.\n";
            echo addLineBreak();
            return true;
        } else {
            mgtlog(SITE . $site . ": error changing site_type");
            echo "Site type change failed.\n";
            echo addLineBreak();
            return false;
        }
    }
}

function changeCountry( $data ) {
    global $statsDB;
    $reqVal = requestValue(COUNTRY);

    if ($reqVal != $data[COUNTRY]) {
        echo "Changing Country.";
        echo addLineBreak();
        $sql = "UPDATE sites SET country = '{$statsDB->escape($reqVal)}' WHERE name = '{$data['site']}'";
        $statsDB->exec($sql);
        mgtlog(SITE . $data['site'] . ": changed country to " . $reqVal);
        echo "Country Changed.";
        echo addLineBreak();
        return true;
    } else {
        return false;
    }
}

function changeSiteStatus( $data ) {
    global $statsDB;

    $reqVal = requestValue(SITE_STATUS);

    if ($reqVal != $data[SITE_STATUS]) {
        $sql = "UPDATE sites SET site_status = '{$reqVal}' WHERE name = '{$data['site']}'";
        $statsDB->exec($sql);
        mgtlog(SITE . $data['site'] . ": changed site_status to " . $reqVal);
        echo "Site Status Changed to $reqVal.\n";
        echo addLineBreak();
        return true;
    } else {
        return false;
    }
}

function changeFTPUID( $data ) {
    global $statsDB, $ftproot_dir, $AdminDB, $DBName;

    $success = false;
    // Don't use db.table, breaks replication
    $statsDB->exec("use $AdminDB");
    $dataId = $data[FTPUSERID];
    $reqVal = requestValue(FTPUSERID);
    $site = $data['site'];

    if ($reqVal != $dataId) {
        echo "Changing FTP user id to $reqVal.";
        echo addLineBreak();

        mgtlog("site {$data['site']} : changing ftp userid");

        $lcSiteType = strtolower(requestValue(SITE_TYPE));
        $cmd = "-m -s {$data['site']} -f {$data[FTPUSERID]} -F $reqVal -t $lcSiteType";

        $reqVal = $statsDB->escape($reqVal);

        if ( execSiteMgt( $cmd ) == 0 ) {
            $ftpRootDir = "/data/ftproot/$reqVal";
            $sql = "UPDATE ftpusers SET userid = '$reqVal', homedir = '$ftpRootDir' WHERE userid = '$dataId'";
            $statsDB->exec($sql);

            echo "FTP Used ID Changed.";
            echo addLineBreak();
            mgtlog("Site $site: ftpuserid changed from $dataId to $reqVal");
            $success = true;
        } else {
            mgtlog("Site $site: error changing ftpuserid");
            echo "Failed to change FTP user id.\n";
            echo addLineBreak();
        }
    }

    // switch back to statsdb
    $statsDB->exec("use $DBName");

    return $success;
}

function changeFTPPw( $data ) {
    global $statsDB, $AdminDB, $DBName;

    $pwd = requestValue('ftppasswd');
    if ( is_null($pwd) || $pwd == "") {
        return false;
    }

    $vPwd = requestValue('verify_ftppasswd');
    if ( is_null($vPwd) || $pwd != $vPwd) {
        echo "Cannot change FTP password, Values don't match\n";
        echo addLineBreak();
        return false;
    }


    echo "Changing FTP password.";
    $ftpUserId = $data[FTPUSERID];
    $sql = sprintf(
        "UPDATE ftpusers SET passwd =  %s WHERE userid = '%s'",
        getEncrytedFtpPassword($pwd),
        $ftpUserId
    );

    // Don't use db.table, breaks replication
    $statsDB->exec("use $AdminDB");
    $statsDB->exec($sql);
    $statsDB->exec("use $DBName");

    echo addLineBreak();
    echo "FTP Password Changed.\n";
    echo addLineBreak();

    return true;
}

function changeName( $data ) {
    global $statsDB, $site;

    $reqVal = requestValue('site');
    $dataSite = $data['site'];
    $type = strtolower( requestValue(SITE_TYPE) );

    if ($reqVal != $dataSite) {
        echo "Changing site name to $reqVal.";
        mgtlog("site $dataSite: changing site name");
        $cmd = "-m -s $dataSite -S $reqVal -t $type";

        if ( execSiteMgt( $cmd ) == 0 ) {
            $sql = "UPDATE sites set name = '{$statsDB->escape($reqVal)}' WHERE name = '{$dataSite}'";
            $statsDB->exec($sql);
            echo addLineBreak();
            echo "Site Name changed to $reqVal.\n";
            $site = $reqVal;
        } else {
            mgtlog("site $dataSite: error changing site name");
            echo addLineBreak();
            echo "Failed to change site name.\n";
        }
        return true;
    }
    return false;
}

function clearCache( $data ) {
    global $statsDB, $AdminDB, $DBName;

    // Don't use db.table, breaks replication
    $statsDB->exec("use $AdminDB");
    $sql = "DELETE FROM ddp_cache WHERE siteid = " . $data['siteid'];
    $statsDB->exec($sql);
    mgtlog("Site {$data['site']}: cleared the cache under ddpadmin.ddp_cache table");
    // switch back to statsdb
    $statsDB->exec("use $DBName");
}

//Return true to redisplay the editsite form
function editSite( $data, $form ) {

    $exists = false;
    if (  checkIfSiteExists( $data ) || checkIfFTPExists( $data ) || checkIfStatsDirExists( $data ) ) {
        $exists = true;
    }
    if ( $exists || checkDataProcessing($data, $form) ) {
        return false;
    }

    echo "Editing site...\n";
    echo addLineBreak();

    // Do operator first, May be a new operator
    changeOperator( $data );
    $changeSiteType = changeSiteType( $data );
    changeCountry( $data );
    changeSiteStatus( $data );
    changeFTPUID( $data );
    changeFTPPw( $data );
    changeDeployinfra( $data );
    // Change site name last, Otherwise screws things up for the previous changes
    $changeName = changeName( $data );

    // Clear the cache entries corresponding to the given site from 'ddp_cache'
    // table. Otherwise the links under the main page of that site won't work
    if ($changeName || $changeSiteType) {
        clearCache($data);
    }
    if ($changeName) {
        return false;
    }
    return true;
}

function mainFlow() {
    global $statsDB, $site, $php_webroot;
    // edit site
    $data = getSiteData();

    if (! is_array($data)) {
        echo "<b>Problem retrieving site data</b><br/>\n";
    } else {
        echo "<h1>Site Management for $site</h1>";

        $editForm = createForm($data);

        if ( issetURLParam('site') ) {
            $edit = editSite( $data, $editForm );

            if ( $edit ) {
                $editForm->display();
            } else {
                $url = "/../adminui/sitemgt.php";
                echo addLineBreak();
                echo  makeLink($url, 'Go back to index page.');
                echo addLineBreak();
            }
        } else {
            $editForm->display();
        }
    }
}

// reinitialise the statsDB with write permissions
$statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);

if ( issetURLParam('selectedSite') ) {
    $site = requestValue('selectedSite');
    mainFlow();
} else {
    $addCols = array(
        'ftpuser' => 'SFTP User ID',
        COUNTRY => 'Country',
        OPERATOR => 'Operator',
        'creator' => 'Created By',
        'requestor' => 'Requested By',
        'utilver' => 'Util Version',
        DEPLOYMENT_INFRA => 'Deployment Infrastructure'
    );
    drawHeader('Site Management', 1, "");
    getSiteIndex( null, $addCols);
}

include_once FINALISE;

