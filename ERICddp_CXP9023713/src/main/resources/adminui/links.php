<?php

include_once "init.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

function linkExists( $siteId, $label ) {
    global $AdminDB, $statsDB;

    $statsDB->exec("use $AdminDB");

    $sql = "SELECT COUNT(*) FROM ddp_links WHERE siteid = $siteId  AND label = '$label'";
    $statsDB->query($sql);

    $row = $statsDB->getNextRow();
    return $row[0];
}

function getLinkPartsById( $id, &$siteId, &$label, &$link ) {
    global $AdminDB, $statsDB;

    $sql = "SELECT * FROM $AdminDB.ddp_links WHERE id = $id";
    $statsDB->query($sql);

    $row = $statsDB->getNextNamedRow();
    $siteId = $row['siteid'];
    $label = $row['label'];
    $link = $row['link'];
}

function getSiteNames( &$siteNames ) {
    global $statsDB;

    $sql = "SELECT id, name FROM sites;";
    $statsDB->query($sql);

    while ( $row = $statsDB->getNextNamedRow() ) {
        $siteNames[$row['id']] = $row['name'];
    }
}

function validateData( $label, $link ) {
    $res = 0;
    if ( filter_var($link, FILTER_VALIDATE_URL) === false ) {
        drawHeader( "<b style='color:red;'>Error: $link is not a valid URL</b>", 1, '' );
        $res = 1;
    }
    if ( ! preg_match( '/^[a-zA-Z0-9_ -]*$/', $label ) ) {
        drawHeader( "<b style='color:red;'>Error: $label does not match /^[a-zA-Z0-9_ -]*$/</b>", 1, '' );
        $res = 1;
    }

    return $res;
}

function createLink( $siteId, $label, $link ) {
    global $AdminDB, $auth_user, $statsDB;

    $validation = validateData( $label, $link );
    if ( $validation === 1 ) {
        return;
    }

    $statsDB->exec("use $AdminDB");
    $sql = sprintf(
        "INSERT INTO $AdminDB.ddp_links( siteid, label, link, creator ) VALUES ('%d','%s','%s','%s')",
        $siteId,
        $label,
        $link,
        $auth_user
    );
    $statsDB->query($sql);

    mgtlog("Created link with SiteId:$siteId Label:$label Link:$link");
}

function deleteLink( $id ) {
    global $AdminDB, $statsDB;

    $statsDB->exec("use $AdminDB");

    getLinkPartsById( $id, $siteId, $label, $link );

    $sql = sprintf(
        "DELETE FROM ddp_links WHERE siteid = '%d' AND label = '%s' AND link = '%s'",
        $siteId,
        $label,
        $link
    );

    $statsDB->query($sql);
    mgtlog("Deleted link with SiteId:$siteId Label:$label Link:$link");
}

function modifyLink( $id, $newSiteId, $newLabel, $newLink ) {
    global $AdminDB, $auth_user, $statsDB;

    $validation = validateData( $newLabel, $newLink );
    if ( $validation === 1 ) {
        return;
    }

    getLinkPartsById( $id, $siteId, $label, $link );

    $statsDB->exec("use $AdminDB");
    $query = "UPDATE ddp_links SET siteid = '%d', label = '%s', link = '%s', creator = '%s'";
    $query .= " WHERE siteid = '%d' AND label = '%s' AND link = '%s'";

    $sql = sprintf(
        $query,
        $newSiteId,
        $newLabel,
        $newLink,
        $auth_user,
        $siteId,
        $label,
        $link
    );

    $statsDB->query($sql);
    mgtlog("Updating link SiteId:$siteId Label:$label Link:$link");
    mgtlog("Modified link SiteId:$newSiteId Label:$newLabel Link:$newLink");
}

function showAllLinks() {
    global $AdminDB, $statsDB;

    $rowData = array();
    $statsDB->exec("use statsdb");

    $sql = "
SELECT
    $AdminDB.ddp_links.id AS id,
    sites.name AS site,
    $AdminDB.ddp_links.label AS label,
    $AdminDB.ddp_links.link AS link,
    $AdminDB.ddp_links.creator AS creator
FROM
    sites,
    $AdminDB.ddp_links
WHERE
    sites.id = $AdminDB.ddp_links.siteid
";
    $statsDB->query($sql);

    while ( $row = $statsDB->getNextNamedRow() ) {
        $rowData[] = $row;
    }

    // Add edit link to site column
    foreach ($rowData as $key => $d) {
        $d['site'] = "<a href=\"?selected=" .  urlencode($d['id']) . "\">". $d['site'] . "</a>";
        $rowData[$key] = $d;
    }

    $cols = array(
        array('key' => 'site', DDPTable::LABEL => 'Site'),
        array('key' => 'label', DDPTable::LABEL => 'Label'),
        array('key' => 'link', DDPTable::LABEL => 'Link'),
        array('key' => 'creator', DDPTable::LABEL => 'Creator')
    );

    $table = new DDPTable(
        "DDP_Links",
        $cols,
        array('data' => $rowData)
    );
    echo $table->getTable();
}

function addLinkForm() {
    $form = new HTML_QuickForm( 'add_link_btn', 'POST', '?' . fromServer(QUERY_STRING) );
    $form->addElement(SUBMIT, "addLink", "Add a new Link");

    return $form;
}

function deleteLinkForm() {
    $form = new HTML_QuickForm( 'delete_link_btn', 'POST', '?' . fromServer(QUERY_STRING) );
    $form->addElement(SUBMIT, "deleteLink", "Delete Link");

    return $form;
}

function modifyLinkForm( $id, $siteNames ) {
    getLinkPartsById( $id, $siteId, $label, $link );
    $form = new HTML_QuickForm( 'modifyLink', 'POST', '?' . fromServer(QUERY_STRING) );

    $select = HTML_QuickForm::createElement( SELECT, 'site', 'Site:', $siteNames );
    $select->setSelected(array($siteId));
    $form->addElement($select);

    $form->addElement('text', 'lbl', "Label:", 'value="' . $label . '" size=50');
    $form->addElement('text', 'link', "Link:", 'value="' . $link . '" size=50');
    $form->addElement(SUBMIT, "updateLink", 'Update Link');

    return $form;
}

function createNewLinkForm( $siteNames ) {
    $form = new HTML_QuickForm( 'createLink', 'POST', '?' . fromServer(QUERY_STRING) );
    $form->addElement(SELECT, 'site', 'Site:', $siteNames);
    $form->addElement('text', 'lbl', "Label:", 'size=50');
    $form->addElement('text', 'link', "Link:", 'size=50');
    $form->addElement(SUBMIT, "createLink", 'Create Link');

    return $form;
}

function mainView() {
    drawHeader( 'Links', 1, 'linkMan' );
    showAllLinks();
    $form = addLinkForm();
    $form->display();
}

$statsDB = new StatsDB(StatsDB::ACCESS_READ_WRITE);

$selected = requestValue('selected');
$siteId = requestValue('site');
$label = requestValue('lbl');
$link = requestValue('link');
getSiteNames( $siteNames );

if ( issetURLParam('createLink') ) {
    if ( ! linkExists($siteId, $label ) ) {
        createLink( $siteId, $label, $link );
    } else {
        $msg = "<b style='color:red;'>Error: Failed to create link, Label can only be used once per site.</b>";
        drawHeader( $msg, 1, '' );
    }
    mainView();
} elseif ( issetURLParam('addLink') ) {
    drawHeader( 'Create a new link', 1, 'linkMan' );
    $form = createNewLinkForm( $siteNames );
    $form->display();
} elseif ( issetURLParam('updateLink') ) {
    modifyLink( $selected, $siteId, $label, $link );
    mainView();
} elseif ( issetURLParam('deleteLink') ) {
    deleteLink( $selected );
    mainView();
} elseif ( $selected ) {
    drawHeader( 'Modify the selected link', 1, 'linkMan' );
    $modForm = modifyLinkForm( $selected, $siteNames );
    $modForm->display();

    drawHeader( 'Delete the selected link', 1, 'linkMan' );
    $delForm = deleteLinkForm();
    $delForm->display();
} else {
    mainView();
}

include_once '../php/common/finalise.php';
