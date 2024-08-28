<?php
ob_start();

include "init.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";


function getAutoIdCheckForm() {
    # Instantiate a new HTML_QuickForm object
    $htmlQuickForm = new HTML_QuickForm('check_autoid_limits', 'POST', '?');

    # Add a text input box for 'Percentage Threshold'
    $htmlQuickForm->addElement('text', 'percent_threshold', 'Percentage Threshold:', array('value' => 100, 'size' => 50, 'maxlength' => 7));

    # Submit the form
    $htmlQuickForm->addElement('submit', null, 'Check...');
    return $htmlQuickForm;
}


# Main Flow
echo "<h1>Check DB Auto-ID Limits</h1>\n";

$user = 'anonymous';
if ( isset($_SESSION['username']) ) {
    $user = $_SESSION['username'];
}

# First, check if we are being redirected to avoid form-resubmission
if ( isset($_GET['percent_threshold']) ) {
    $threshold = $_GET['percent_threshold'];
    echo "<b>The process to check any overflow of auto-increment IDs under the database tables " .
         "(with a threshold of {$threshold}% of maximum allowed values) has been successfully " .
         "initiated.</b>\n<br/><br/>\n";

    $hyperLink = makeLinkForURL(getUrlForFile("/data/ddp/log/autoincrementidcheck.log"), "autoincrementidcheck.log");
    echo "Please check  $hyperLink to monitor the progress.<br/>\n";

    echo "Please click <a href='?'>here</a> to go back to the previous page.\n<br/>\n";

    include "../php/common/finalise.php";
    return;
}

# Creat and display the HTML QuickForm
$autoIdCheckForm = getAutoIdCheckForm();
$autoIdCheckForm->display();

# Process the form submission
if ( isset($_POST['percent_threshold']) ) {
    $threshold = $_POST['percent_threshold'];

    # Execute the 'checkStatsDBAutoIdSize' script in the background
    $cmd = "sudo -u statsadm $ddp_dir/server_setup/checkStatsDBAutoIdSize --threshold $threshold " .
           "--outfile /data/ddp/log/autoincrementidcheck.log --user $user > /dev/null 2>&1 &";
    system($cmd);

    header('location: ' . $_SERVER['REQUEST_URI'] . '&percent_threshold=' . $threshold);
    exit();
}

