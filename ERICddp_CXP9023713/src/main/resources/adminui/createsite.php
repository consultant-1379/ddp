<?php
require_once "init.php";
require_once PHP_ROOT . "/StatsDB.php";
require_once PHP_ROOT . "/common/countries.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";
require_once PHP_ROOT . "/common/htmlFunctions.php";

const REGEX = 'regex';
const NO_SPACES = '/^\S+$/';
const NO_SPECIAL_CHARS = '/^[a-zA-Z0-9_-]*$/';
const TESTSITE = 'testsite';
const HIDDEN = 'hidden';
const CALCNAMES = "calcNames(this.form)";

?>

<script type="text/javascript" src="calcnames.js"></script>
      <style type=text/css>
      input.disabled {
          background: #fff;
          border: 0px;
      }
      </style>
<?php

if ( isset($site_creation_disabled) && $site_creation_disabled ) {
    echo "<P><B>Creation of new sites has been disabled</B></P>\n";
    require_once  PHP_ROOT . "/common/finalise.php";
    return;
}

$editDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);

drawHeaderWithHelp("Create Site", 1, "createSite", "DDP_Bubble_467_ENM_CREATE_SITE");

$testsite = 0;

if ( issetURLParam(TESTSITE) ) {
    $testsite = requestValue(TESTSITE);
} elseif ( file_exists( $stats_dir . '/' . TESTSITE ) || file_exists( $stats_dir . '/oss/' . TESTSITE ) ) {
    $testsite = 1;
}

// Instantiate the HTML_QuickForm object
$form = new HTML_QuickForm('registrationform', 'POST');

$form->addElement(HIDDEN, TESTSITE, $testsite);

$textFields = array();

if ( $testsite ) {
    $textFields['owner'] = 'Account Requestor (SIGNUM)';
    $textFields['cust'] = 'Location';
    $textFields['hostname'] = 'Server Hostname';

    $form->addElement('header', null, 'Create New Test Site');
} else {
    $textFields['owner'] = 'Account Requestor (SIGNUM)';
    $textFields['hostname'] = 'Server Hostname';
    $textFields['city'] = 'Server Location(City)';

    $form->addElement('header', null, 'Create New Site');
}

// hack to allow creation of sites without IP check
if (isset($_REQUEST['ipcheck']) && $_REQUEST['ipcheck'] == "false") {
    $form->addElement(HIDDEN, 'ipcheck', 'false');
}

// Add some elements to the form
foreach ($textFields as $name => $label ) {
    $form->addElement('text', $name, $label . ':', array('size' => 50, 'maxlength' => 255, "onChange" => CALCNAMES));
    $form->applyFilter($name, 'trim');
    $form->addRule($name, 'Please enter the ' . $label, 'required', null, 'client');
}
// we can only do this on the test site for now
$typeArr = array(
    'NONE' => 'Select',
    'OSS' => 'OSS',
    'ENIQ' => 'ENIQ',
    'TOR' => 'TOR',
    'EO' => 'EO',
    'DDP' => 'DDP',
    'GENERIC' => 'GENERIC',
    'ECSON' => 'ECSON'
);

$typeSel =  HTML_QuickForm::createElement('select','site_type','Site Type:',$typeArr);
$form->addElement($typeSel);
if (! $testsite) {
    $sql = "SELECT * FROM operators ORDER BY name ASC";
    $editDB->query($sql);
    $opers = array();
    while ($row = $editDB->getNextNamedRow()) {
        $opers[$row['id']] = $row['name'];
    }
    $operSel =  HTML_QuickForm::createElement(
        'select',
        OPERATOR,
        'Operator (as used in filter):',
        $opers,
        array("onChange" => CALCNAMES)
    );
    if (isset($data[OPERATOR])) {
        $operSel->setSelected(array($data[OPERATOR]));
    }
    $form->addElement($operSel);

    $statusArr = array(
        "" => "",
        "live" => "live",
        "lab" => "lab",
    );
    $statusSel = HTML_QuickForm::createElement(
        SELECT,
        SITE_STATUS,
        'Site Status:',
        $statusArr,
        array(
            "onChange" => CALCNAMES
        )
    );
    if (isset($data) && isset($data['site_status'])) {
        $statusSel->setSelected(array($data['site_status']));
    }
    $form->addElement($statusSel);
    $countrySel = HTML_QuickForm::createElement(
        SELECT,
        COUNTRY,
        'Country:',
        $countries,
        array(
            "onChange" => CALCNAMES
        )
    );
    if (isset($data) && isset($data['country'])) {
        $countrySel->setSelected(array($data['country']));
    }
    $form->addElement($countrySel);
}

$sql = "SELECT * FROM deploy_infra ORDER BY name ASC";
$editDB->query($sql);
$infra = array();
while ($row = $editDB->getNextNamedRow()) {
    $infra[$row['id']] = $row['name'];
}
$infraSel =  HTML_QuickForm::createElement(
    SELECT,
    DEPLOYMENT_INFRA,
    'Deployment Infrastructure (as used in filter):',
    $infra,
    array("onChange" => CALCNAMES)
);
if (isset($data[DEPLOYMENT_INFRA])) {
    $infraSel->setSelected(array($data[DEPLOYMENT_INFRA]));
}
$form->addElement($infraSel);

// Extra rules for the following files
if ( $testsite ) {
    $form->addRule($name, 'Spaces not allowed in '. $textFields['hostname'], REGEX, NO_SPACES, 'client');
    $form->addRule(
        $name,
        'Special characters not allowed in '. $textFields['hostname'],
        REGEX,
        NO_SPECIAL_CHARS,
        'client'
    );

    // Default the cust field to LMI
    $form->getElement('cust')->setValue('LMI');
} else {
    foreach ( array('owner', 'hostname', 'city') as $name ) {
        $form->addRule($name, 'Spaces not allowed in '. $textFields[$name], REGEX, NO_SPACES, 'client');
        $form->addRule(
            $name,
            'Special characters not allowed in '. $textFields['hostname'],
            REGEX,
            NO_SPECIAL_CHARS,
            'client'
        );
    }

    $form->addElement('text', 'ftp_acc_vis', "SFTP Login (auto-generated):", "size=50 disabled class=disabled");
    $form->addElement(HIDDEN, 'ftp_acc');
    $form->addElement('text', 'acc_name_vis', "Account Name (auto-generated):", "size=50 disabled class=disabled");
    $form->addElement(HIDDEN, 'acc_name');
}

$form->addElement('submit', null, 'Send');

// Try to validate a form
if ($form->validate()) {
    $form->freeze();
    $form->process('process_data', false);
} else {
    // Output the form
    $form->display();
}

function checkNotExist($oss){
    global $stats_dir;
    $dir = $stats_dir . "/" . strtolower($oss);
    if (!file_exists($dir) || !is_dir($dir)) {
        echo "$dir not found.";
        return 1;
    } else {
        return 0;
    }
}

function checkIfExists($ftpDir, $site, $statsDir, $ftpUserId) {
    global $AdminDB, $editDB;
    $exists = 0;

    if (file_exists($ftpDir) && is_dir($ftpDir)) {
        echo "<P>ERROR: $ftpDir already exists!</P>";
        $exists = 1;
    }

    if (file_exists($statsDir) && is_dir($statsDir)) {
        echo "<P>ERROR: $statsDir already exists!</P>";
        $exists = 1;
    }

    $row = $editDB->queryRow("SELECT COUNT(*) FROM sites WHERE sites.name = '" . $site . "'");
    if ( $row[0] > 0 ) {
        echo "<p>ERROR: Found existing site for $site!</p>\n";
        $exists = 1;
    }

    $editDB->exec("use $AdminDB"); // Don't use db.table, breaks replication
    $row = $editDB->queryRow("SELECT COUNT(*) FROM ftpusers  WHERE userid = '" . $site . "'");
    if ( $row[0] > 0 ) {
        echo "<p>ERROR: SFTPUser $ftpUserId already exists!</p>\n";
        $exists = 1;
    }
    $editDB->exec("use statsdb");

    return $exists;
}

function process_data($values) {
    global $AdminDB, $editDB, $ftproot_dir, $php_webroot, $auth_user;

    debugMsg("process_data: values", $values);

    $oss = getSiteTypeInfo($values);

    if ( checkNotExist($oss) == 1 ) {
        return;
    }

    $cols = "name";
    $vals = "";
    $operId = "";
    $infraId = "";
    $ldap_conn = getLdapConn();
    $userInfo = getLdapUserInfo($ldap_conn,$values['owner'],array("mail"));
    ldap_close($ldap_conn);

    if ( is_null($userInfo) ) {
        echo "<p>ERROR: No match found for ". $values['owner'] . "</B>";
        return;
    }

    // ensure we don't have any extraneous whitespace
    removeWhiteSpace($values);

    $ftpUserId = getFtpUserId($values);
    $site = getSiteName($values);

    $statsDir = "/data/stats/" . strtolower($values['site_type']) . "/" . "$site";
    $ftpDir =  "$ftproot_dir" . "/" . "$ftpUserId";
    $exists = checkIfExists($ftpDir, $site, $statsDir, $ftpUserId);
    if($exists == 1){
        return;
    }

    $ftpPasswd = '_' . $values['hostname'];

    mgtlog("adding site - " . $site);
    if ( array_key_exists(OPERATOR, $values) ) {
        $operId = $values[OPERATOR];
    }

    if ( array_key_exists(DEPLOYMENT_INFRA, $values) ) {
        $infraId = $values[DEPLOYMENT_INFRA];
    }

    getDeploymentInfo($values, $operId, $infraId, $site, $cols, $vals, $oss);

    if ( $oss != 'NONE' ) {
        // add site
        $sql = "INSERT INTO sites (" . $cols . ") VALUES(" . $vals . ")";
        $editDB->exec($sql);
        $siteId = $editDB->lastInsertId();
        mgtlog("added SQL information for site " . $site);

        $editDB->exec("use $AdminDB"); // Don't use db.table, breaks replication
        $sql = sprintf(
            "INSERT INTO ftpusers (siteid,userid,passwd,homedir) VALUES (%d, '%s', %s, '%s/%s')",
            $siteId,
            $ftpUserId,
            getEncrytedFtpPassword($ftpPasswd),
            $ftproot_dir,
            $ftpUserId
        );
        $editDB->exec($sql);
        mgtlog("added SQL ftp user information for site " . $site . " ftpuser " . $ftpUserId);

        if (execSiteMgt("-a -s " . $site . " -f " . $ftpUserId . " -t " . strtolower($oss)) == 0) {
            $serverName = getDDPServerName();
            mgtlog("created site " . $site . " : owner: " . $values['owner']);
            createdMsg( $serverName, $site, $ftpUserId, $ftpPasswd );
        } else {
            mgtlog("created site " . $site . " : owner: " . $values['owner'] . " | filesystem artefact creation failed");
            echo "Site successfully created, but filesystem artefact creation failed\n";
        }
    } else {
        echo "<script type='text/javascript'>
                alert(\"Please select valid site type\")
                window.location.href='createsite.php';
                </script>";
    }
}

function removeWhiteSpace(&$values) {
    foreach ($values as $key => $val) {
        if (! is_array($val)) {
            $values[$key] = trim($val);
        } else {
            $values[$key] = implode("_", $val);
        }
        if ($values[$key] == "") {
            echo "<p>ERROR: " . $key . " is empty</p>\n";
        }
    }
}

function getSiteTypeInfo($values) {
    if (isset($values['site_type']) && $values['site_type'] != "") {
       return $values['site_type'];
    } else {
       return "OSS";
    }
}

function getFtpUserId($values) {
    if ( $values['testsite'] ) {
        $ftpUserId = strtolower($values['cust']) . "_" . $values['hostname'];
    } else {
        $ftpUserId = $values['ftp_acc'];
    }
    return $ftpUserId;
}

function getSiteName($values) {
    if ( $values['testsite'] ) {
        return $values['cust'] . "_" . $values['hostname'];
    } else {
        return $values['acc_name'];
    }
}

function getDeploymentInfo($values, $operId, $infraId, $site, &$cols, &$vals, $oss) {
    global $auth_user;

    $vals .= "'" . $site . "'";
    if (isset ($operId) && is_numeric($operId) && $operId > 0) {
        $cols .= ",oper_id";
        $vals  .= "," . $operId;
    }
    if (isset($values['site_status']) && ($values['site_status'] == "live" || $values['site_status'] == "lab")) {
        $cols .= ",site_status";
        $vals  .= ",'" . $values['site_status'] . "'";
    }
    if (isset($values['country'])) {
        $cols .= ",country";
        $vals  .= ",'" . $values['country'] . "'";
    }

    $cols .= ",site_type";
    $vals .= ",'" . $oss . "'";

    $cols .= ",creator,requestor";
    $vals .= ",'" . $auth_user . "','" . $values['owner'] . "'";

    if (isset ($infraId) && is_numeric($infraId) && $infraId > 0) {
        $cols .= ",infra_id";
        $vals  .= "," . $infraId;
    }
}

function createdMsg( $serverName, $site, $ftpUserId, $ftpPasswd ) {
    global $php_webroot, $oss;

    echo "<h2>Site successfully created with the following information:</h2>";
    $lowOss = strtolower($oss);
    $link = "<a href=\"$php_webroot/index.php?site=$site&oss=$lowOss\">$site</a>";

    $msgList = array(
        "<b>Site Name:</b> $site",
        "<b>DDP Server:</b> $serverName",
        "<b>Link:</b> $link",
        "<b>SFTP Account:</b> $ftpUserId",
        "<b>SFTP Password:</b> $ftpPasswd",
        "<b>ddp.txt</b> file must contain $ftpUserId"
    );
    echo makeHtmlList($msgList);

    if ( $oss != 'DDP' && $oss != 'OSS' ) {
        cloudNativeMsg( $ftpUserId, $ftpPasswd, $serverName );
    }
}

function cloudNativeMsg( $ftpUserId, $ftpPasswd, $serverName ) {
    $fqdn = getDDPServerName();
    $fqdnArr = explode('.', $fqdn);
    $siteName = $fqdnArr[0];

    if ( $siteName == 'ddp' || $siteName == 'ddp2' ) {
        $account = "SFTP user name on the customer's SFTP server";
        $password = "Password for SFTP user on the customer's SFTP server";
        $msg = '';
    } else {
        $account = "$ftpUserId@$serverName";
        $password = "$ftpPasswd";
        $msg = addLineBreak() . '<b>Note:</b> When using the cloud native credentials above, the prefixUpload must be set to "no" in the values.yaml.';
    }

    echo addLineBreak();
    echo "<H2>For cloud native (if applicable) the values.yaml variables are:</H2>";

    $msgList = array(
        "<b>account:</b> $account",
        "<b>password:</b> $password",
        "<b>ddpid:</b> $ftpUserId"
    );
    echo makeHtmlList($msgList);

    echo '<b>Note:</b> In cloud native ddp.txt is automatically set with the value in ddpid' . $msg;
}

include "../php/common/finalise.php";

