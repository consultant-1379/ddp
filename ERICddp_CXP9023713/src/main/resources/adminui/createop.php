<?php
require_once "init.php";
require_once PHP_ROOT . "/classes/QFAdaptor.php";
require_once PHP_ROOT . "/classes/DDPTable.php";

const NEW_OPERATOR = 'new_op';
const CLIENT = 'client';

function newOperatorForm() {
    $form = new HTML_QuickForm('new_operator', 'POST');
    $form->addElement('text', NEW_OPERATOR, 'Operator:', array('size' => 50, 'maxlength' => 50));
    $form->addRule(NEW_OPERATOR, 'Please enter operator', 'required', null, CLIENT);
    $form->addRule(NEW_OPERATOR, 'Punctuation characters not allowed', 'nopunctuation', null, CLIENT);
    $form->addRule(NEW_OPERATOR, 'Spaces not allowed', 'regex', '/^\S+$/', CLIENT);
    $form->addRule(NEW_OPERATOR, 'Special characters not allowed', 'regex', '/^[a-zA-Z0-9_-]*$/', CLIENT);
    $form->addElement('submit', null, 'Create new Operator');
    return $form;
}

function addNewOperator() {
    global $statsDB;
    $reqVal = requestValue(NEW_OPERATOR);
    $sql = "SELECT id FROM operators WHERE UCASE(name) = UCASE('{$statsDB->escape($reqVal)}')";
    $statsDB->query($sql);

    if ($statsDB->getNumRows() != 0) {
        echo "ERROR: operator by this name already exists.<br/>\n";
        mgtlog("error creating operator $reqVal already exists");
    } else {
        $sql = "INSERT INTO operators (name) VALUES ('{$statsDB->escape($reqVal)}')";
        $statsDB->exec($sql);
        $operId = $statsDB->lastInsertId();
        echo "Operator $reqVal created Successfully";
        mgtlog("Created new operator $reqVal - $operId");
    }
}

function getOpList() {
    global $statsDB;

    $rowData = array();
    $statsDB->query("SELECT * from operators");

    while ( $row = $statsDB->getNextNamedRow() ) {
        $rowData[] = $row;
    }
    foreach ($rowData as $key => $d) {
        $rowData[$key] = $d;
    }

    $table = new DDPTable(
        "Exoperator",
        array(
            array('key' => 'id', 'label' => 'OperatorId', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
            array('key' => 'name', 'label' => 'OperatorName'),
        ),
        array('data' => $rowData),
        array(
            DDPTable::ROWS_PER_PAGE => 20,
            DDPTable::ROWS_PER_PAGE_OPTIONS => array(100, 1000, 10000)
        )
    );
    echo $table->getTable();
}

drawHeader("Create New Operator", 1, "createOperator");
$newOpForm = newOperatorForm();
if ($newOpForm->validate()) {
    $newOpForm->freeze();
    addNewOperator();
}
$newOpForm->display();

drawHeader("Existing Operators", 1, "exOperator");
getOpList();


include_once "../php/common/finalise.php";

