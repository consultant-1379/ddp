<?php
require_once "init.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";
const SITESTATUSFILE = '/data/tmp/site_status.php';
const NON_BREAKING_SPACE = '&nbsp;';

function createForm(){
    $fileExists = file_exists( SITESTATUSFILE );
    drawHeader("Update DDP Status", 2, "StatusMsgBroadCast");
    // Instantiate the HTML_QuickForm object
    $form = new HTML_QuickForm('ddpstatusform', 'POST');
    $form->addElement('header', null, 'Create a new DDP Status Message');
    $form->addElement('text', 'broadcastStatusMessage', 'Message:', array('size' => 50, 'maxlength' => 400));
    if ( $fileExists ) {
        $form->setDefaults( array('broadcastStatusMessage' => file_get_contents( SITESTATUSFILE) ) );
    }
    $form->applyFilter('broadcastStatusMessage', 'trim');
    $form->addRule('broadcastStatusMessage', 'Please enter Message', 'required', null, 'client');
    $buttons = array();
    $buttons[] = $form->addElement('submit', 'null', 'Submit');
    if( $fileExists ){
        $buttons[] = $form->addElement('submit', 'RemoveBroadCast', 'Remove');
    }
    $form->addGroup($buttons, null, null, NON_BREAKING_SPACE);
    if ($form->validate()) {
        $form->freeze();
        $form->process('processData', false);
    }
    $form->display();
}

function processData($values) {
    debugMsg("processData: values", $values);
    if( isset( $values['RemoveBroadCast'] ) ){
         unlink( SITESTATUSFILE );
    } else {
        file_put_contents( SITESTATUSFILE, $values['broadcastStatusMessage']);
    }
   echo "<meta http-equiv='refresh' content='0'>";
}

function main(){
    createForm();
}

main();

include "../php/common/finalise.php";
?>
