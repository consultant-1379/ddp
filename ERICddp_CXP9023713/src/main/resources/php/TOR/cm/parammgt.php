<?php
$pageTitle = "Parameter Management";

require_once "../../common/init.php";
require_once PHP_ROOT . "/classes/SqlTable.php";

function mainFlow() {
    global $site, $date, $debug;

    drawHeaderWithHelp("CPM Import File Generation", 2, "importfilegen");
    $statsDB = new StatsDB();
    $table = SqlTableBuilder::init()
           ->name("importfilegen")
           ->tables(array('enm_parammgt_genimport', StatsDB::SITES))
           ->where($statsDB->where("enm_parammgt_genimport"))
           ->addColumn('time', 'time', 'Time', DDPTable::FORMAT_TIME)
           ->addSimpleColumn('size', 'File Size')
           ->addSimpleColumn('type', 'File Type')
           ->addSimpleColumn('duration', 'Duration')
           ->addSimpleColumn('n_mo', 'Total MOs')
           ->addSimpleColumn('ROUND(n_mo*1000/duration,1)', 'MOs/sec')
           ->build();
    echo $table->getTable();

    drawHeaderWithHelp("CPM Export to CSV", 2, "csvfilegen");
    $table = SqlTableBuilder::init()
           ->name("csvgen")
           ->tables(array('enm_parammgt_gencsv', StatsDB::SITES))
           ->where($statsDB->where("enm_parammgt_gencsv"))
           ->addColumn('time', 'time', 'Time', DDPTable::FORMAT_TIME)
           ->addSimpleColumn('fileSize', 'File Size')
           ->addSimpleColumn('fileWriteDurationInMs', 'Duration')
           ->addSimpleColumn('n_poids', 'POIDs')
           ->addSimpleColumn('n_attributes', 'Attributes')
           ->build();
    echo $table->getTable();
}

mainFlow();

require_once PHP_ROOT . "/common/finalise.php";
