<?php

include "init.php";
include "../php/common/countries.php";
require_once PHP_ROOT . "/classes/DDPTable.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";

drawHeader('Access Management', 1, '');

function createForm($site) {
  global $statsDB, $AdminDB, $debug;
  // Instantiate the HTML_QuickForm object
  $form = new HTML_QuickForm('editaccess','POST','?' . $_SERVER['QUERY_STRING']);

  $allowedGroups = array();
  $statsDB->query("SELECT grp FROM $AdminDB.site_accessgroups, sites WHERE $AdminDB.site_accessgroups.siteid = sites.id AND sites.name = '$site'");
  while($row = $statsDB->getNextRow()) {
    $allowedGroups[$row[0]] = $row[0];
  }
  if ( $debug ) { echo "<pre>createForm: site=$site allowedGroups\n"; print_r($allowedGroups); echo "</pre>"; }

  $form->addElement('hidden','editsite',$site);

  $removeElements[] = HTML_QuickForm::createElement('select', 'grps', 'Assigned Groups',$allowedGroups);
  $removeElements[] = HTML_QuickForm::createElement('submit', 'removebtn', 'Remove');
  $form->addGroup($removeElements, 'remove', 'Assigned Groups:', ',&nbsp;');


  $addElements[] = HTML_QuickForm::createElement('text','newgrp','New Group:');
  $addElements[] = HTML_QuickForm::createElement('submit', 'addbtn', 'Add');
  $form->addGroup($addElements, 'add', 'Add Group:', ',&nbsp;');

  return $form;
}

function process_data($values) {
    global $debug, $AdminDB, $DBName;

    if ( $debug ) { echo "<pre>process_data\n"; print_r($values); echo "</pre>\n"; }

    // reinitialise the statsDB with write permissions
    $statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);

    $site = $values['editsite'];
    $row = $statsDB->queryRow("SELECT id FROM sites WHERE name = '$site'");
    $siteId = $row[0];

    $statsDB->exec("use $AdminDB"); // Don't use db.table, breaks replication

    $msg = null;
    if ( array_key_exists('remove',$values) && array_key_exists('removebtn',$values['remove']) ) {
        $removeGrp = $values['remove']['grps'];
        $statsDB->query("DELETE FROM site_accessgroups WHERE siteid = $siteId AND grp = '$removeGrp'");
        $msg = "Group $removeGrp removed from site $site";
    } else if ( array_key_exists('add',$values) && array_key_exists('addbtn',$values['add']) ) {
        $newGrp = $values['add']['newgrp'];
        if ( isValidGroup($newGrp) ) {
            if( ! groupAlreadyExists($statsDB, $siteId, $newGrp) ) {
                $statsDB->query("INSERT site_accessgroups (siteid,grp) VALUES ($siteId,'$newGrp')");
                $msg = "Group $newGrp added to site $site";
            } else {
                $msg = "Group $newGrp already exists for site $site";
            }
        } else {
            $msg = "Group $newGrp is not a valid group";
        }
    }
    if ( ! is_null($msg) ) {
        mgtlog($msg);
        echo $msg . addLineBreak(2);
    }

    $statsDB->exec("use $DBName"); // switch back to statsdb
}

function groupAlreadyExists( $statsDB, $siteId, $newGrp ) {
    $sql = "SELECT COUNT(*) FROM site_accessgroups WHERE siteid = '$siteId' AND grp = '$newGrp'";
    $res = $statsDB->queryRow($sql);
    return $res[0];
}

function main(){
    $param = requestValue('selectedSite');
    if ( $param ) {
        $form = createForm( $param );
        if ($form->validate()) {
            # If the form validates, freeze and process the data
            $form->freeze();
            $form->process("process_data", false);
            # Re-create the form so it has up-to-date info
            $form = createForm( $param );
        }
        $form->display();
    } else {
        $addCols = array(
            'access_group' => 'Access Groups'
        );
        getSiteIndex( null, $addCols );
    }
}

main();

include PHP_ROOT . "/common/finalise.php";

