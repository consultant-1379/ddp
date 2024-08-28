<?php
require_once "init.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

const NEW_DEPLOYMENT_INFRA = 'new_dp';
const CLIENT = 'client';

function newDeployinfraForm() {
    $form = new HTML_QuickForm('new_deployment_infra', 'POST');
    $form->addElement(
        'text',
        NEW_DEPLOYMENT_INFRA,
        'Deployment Infrastructure:',
        array(
            'size' => 50,
            'maxlength' => 50
        )
    );
    $form->addRule(NEW_DEPLOYMENT_INFRA, 'Please enter Deployment Infrastructure', 'required', null, CLIENT);
    $form->addRule(NEW_DEPLOYMENT_INFRA, 'Punctuation characters not allowed', 'nopunctuation', null, CLIENT);
    $form->addRule(NEW_DEPLOYMENT_INFRA, 'Special characters not allowed', 'regex', '/^[a-zA-Z0-9_ -]*$/', CLIENT);
    $form->addElement('submit', null, 'Create new Deployment Infrastructure');
    return $form;
}

function addNewDeploymentinfra() {
    global $statsDB;

    $reqVal = requestValue(NEW_DEPLOYMENT_INFRA);
    $sql = "SELECT id FROM deploy_infra WHERE UCASE(name) = UCASE('{$statsDB->escape($reqVal)}')";
    $statsDB->query($sql);

    if ($statsDB->getNumRows() != 0) {
        echo "ERROR: Deployment Infrastructure by this name already exists.<br/>\n";
        mgtlog("error creating Deployment Infrastructure $reqVal already exists");
    } else {
        $sql = "INSERT INTO deploy_infra (name) VALUES ('{$statsDB->escape($reqVal)}')";
        $statsDB->exec($sql);
        $infraId = $statsDB->lastInsertId();
        echo "Deployment Infrastructure $reqVal created Successfully";
        mgtlog("Created new Deployment Infrastructure $reqVal - $infraId");
    }
}

function getDpList() {
    global $statsDB;

    $rowData = array();
    $statsDB->query("SELECT * from deploy_infra");

    while ( $row = $statsDB->getNextNamedRow() ) {
        $rowData[] = $row;
    }
    foreach ($rowData as $key => $d) {
        $rowData[$key] = $d;
    }

    $table = new DDPTable(
        "Exdeploy_Infra",
        array(
            array(
                'key' => 'id',
                'label' => 'Deployment Infrastructure Id',
                'sortOptions' => array(
                    'sortFunction' => 'forceSortAsNums'
                )
            ),
            array('key' => 'name', 'label' => 'Deployment Infrastructure Name'),
        ),
        array('data' => $rowData),
        array(
            DDPTable::ROWS_PER_PAGE => 20,
            DDPTable::ROWS_PER_PAGE_OPTIONS => array(100, 1000, 10000)
        )
    );
    echo $table->getTable();
}

function mainFlow() {
    drawHeader("Create New Deployment Infrastructure", 1, "createDeploymentinfra");
    $newDpForm = newDeployinfraForm();
    if ($newDpForm->validate()) {
        $newDpForm->freeze();
        addNewDeploymentinfra();
    }
    $newDpForm->display();
    drawHeader("Existing Deployment Infrastructure", 1, "exDeploymentinfra");
    getDpList();
}

mainFlow();

include_once "../php/common/finalise.php";
