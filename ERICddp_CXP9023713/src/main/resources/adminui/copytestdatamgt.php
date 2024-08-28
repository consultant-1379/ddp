<?php

$YUI_DATATABLE = TRUE;

ob_start();
include "init.php";
include "../php/common/countries.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

// reinitialise the statsDB with write permissions
$statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);

echo "<h1>Copy Test Data</h1>";

if ( ! issetURLParam('selectedSite') ) {
    getSiteIndex( '30' );
    include "../php/common/finalise.php";
    return;
}

// edit site
$site = requestValue('selectedSite');

# Check if we are being redirected to display the exit status of a reprocessing attempt
if ( isset($_GET['copyTestDataRetVal']) ) {
    $tarFile = 'DDC tar file';
    if ( isset($_GET['tar_file']) ) {
        $tarFile = $_GET['tar_file'];
    }

    if ( $_GET['copyTestDataRetVal'] == 0 ) {
        echo "<b>File '{$tarFile}' of '{$site}' has been copied successfully.</b>\n<br/>\n";
    } else if ( $_GET['copyTestDataRetVal'] == 2) {
        echo "<b>Permission denied for Wrong credentials. Failed to move {$tarFile} of '{$site}' from NAS to FTP directory.</b>\n<br/>\n";
    } else if ( $_GET['copyTestDataRetVal'] == 3) {
        echo "<b>Destination directory not exists. Failed to move {$tarFile} of '{$site}' from NAS to FTP directory.</b>\n<br/>\n";
    } else if ( $_GET['copyTestDataRetVal'] == 4) {
        echo "<b>Unknown Host. Failed to move {$tarFile} of '{$site}' from NAS to FTP directory.</b>\n<br/>\n";
    } else if ( $_GET['copyTestDataRetVal'] == 5) {
        echo "<b>Please check the inputs and try again. Failed to move {$tarFile} of '{$site}' from NAS to FTP directory.</b>\n<br/>\n";
    } else {
        echo "<b>Failed to move {$tarFile} of '{$site}' from NAS to FTP directory.</b>\n<br/>\n"; 
    }

    echo "<br/>";
    echo "Please click <a href=\"?selectedSite=" . $site . "\">here</a> to go back to the previous page.\n<br/>\n";
    include "../php/common/finalise.php";
    return;
}

function copyTestDataForm() {
    global $datesArr;
    // Instantiate the HTML_QuickForm object
    $form = new HTML_QuickForm('copy_site_data','POST','?' . $_SERVER['QUERY_STRING']);
    // date
    $form->addElement('select','date_file','File to be Copied:',$datesArr);

    $textFields = array();
    $textFields['hostname'] = 'Server Hostname';
    $textFields['destination'] = 'Remote Server Destination Path';
    $textFields['username'] = 'User Name';

    // Add some elements to the form
    foreach ($textFields as $name => $label ) {
        $form->addElement('text', $name, $label . ':', array('size' => 50, 'maxlength' => 255));
        $form->applyFilter($name, 'trim');
        $form->addRule($name, 'Please enter the ' . $label, 'required', null, 'client');
        $form->addRule($name, 'Spaces not allowed in '. $textFields[$name], 'regex', '/^\S+$/', 'client');
        $form->addRule($name, 'Special characters not allowed in '. $textFields[$name], 'regex', '/^[a-zA-Z0-9_-/.]*$/', 'client');
    }   
 
    $form->addElement('password','password','Password:',array('size' => 50, 'maxlength' => 255));
    $form->applyFilter('password', 'trim');
    $form->addRule('password', 'Please enter the Password', 'required', null, 'client');
    
    
    // submit
    $form->addElement('submit', null, 'Submit');
    return $form;
}

$data = getSiteData();

if (! is_array($data)) {
    echo "<b>Problem retrieving site data</b><br/>\n";
    include "../php/common/finalise.php";
    return;
}

$nassitepath = "/nas/data_files/$site";
if ( file_exists($nassitepath) ) {

    echo"<h2>Site: $site</h2>";

    $datesArr = preg_grep( '/^DDC_Data_\d{6}.tar.gz$/', scandir($nassitepath) );
    $form = copyTestDataForm();
    $form->display();
    if ( isset($_POST['date_file']) && isset($_POST['destination']) && isset($_POST['hostname']) && isset($_POST['username']) &&
    isset($_POST['password']) ) {
        $key = $_POST['date_file'];
        $ddcTarFile = "$datesArr[$key]";
        $copyTestDataRetVal = 0;

        $destDir = $_POST['destination'];

        $host = $_POST['hostname'];
        $user = $_POST['username'];
        $passwd = $_POST['password'];

        if ( $copyTestDataRetVal == 0 ) {
            # No DDC tar for the given site and date is currently being processed.
            # So go ahead with moving the tar file from NAS to FTPROOT
            $source = "$nassitepath/$ddcTarFile";
            $dest = "$destDir/$ddcTarFile";
            $copyTestDataRetVal = execCopyTestDataMgt(" -h " . $host . " -s " . $source . " -u " . $user . " -p " . $passwd .  " -d " . $dest . " -D " . $destDir);
        } else {
            # Looks like another DDC tar file the same date and site is currently
            # getting processed. So don't proceed with the reprocessing
            $copyTestDataRetVal = 6;
        }
        header('location: ' . $_SERVER['REQUEST_URI'] . '&copyTestDataRetVal=' . $copyTestDataRetVal . '&tar_file=' . $ddcTarFile);
        exit();
    }
} else {
    echo "<b>ERROR: NAS Directory does not exist for this site!</b>\n";
}

include "../php/common/finalise.php";
?>

