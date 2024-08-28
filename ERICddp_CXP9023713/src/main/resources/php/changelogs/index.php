<?php
$CAL = false; // disables the calendar
$SHOW_CAL = false; // disables the calendar div - sets it to hidden

require_once "../common/init.php";

echo "<div id=content>\n";


require_once PHP_ROOT . "/classes/DDPTable.php";

$changes = json_decode(file_get_contents("./changelog.json"), true); // NOSONAR
$changes = array_reverse($changes);

$rows = array();
foreach ( $changes as $change ) {
    $row = array(
        'version' => $change['version'],
        'commits' => makeHTMLList($change['commits'])
    );
    $schema = $change['schema'];
    foreach ( array('CREATE', 'ALTER', 'DROP' ) as $op) {
        $tables = array();
        foreach ( array('statsdb', 'ddpadmin') as $db ) {
            if ( array_key_exists($db, $schema) ) {    
                foreach ( $schema[$db]['summary'][$op] as $table ) {
                  $tables[] = $db . "."  . $table;
                }
            }
        }
        if ( count($tables) > 0 ) {
            $row[$op] = makeHTMLList($tables);
        }
    }
    $rows[] = $row;
}

$table = new DDPTable(
    "changelog",
    array(
        array(
            DDPTable::KEY => 'version',
            DDPTable::LABEL => 'Version'
        ),
        array(
            DDPTable::KEY => 'commits',
            DDPTable::LABEL => 'Commits'
        ),
        array(
            DDPTable::KEY => 'CREATE',
            DDPTable::LABEL => 'Creates'
        ),
        array(
            DDPTable::KEY => 'ALTER',
            DDPTable::LABEL => 'Alters'
        ),
        array(
            DDPTable::KEY => 'DROP',
            DDPTable::LABEL => 'Drops',
        )
    ),
    array(
        'data' => $rows
    ),
    array(
        DDPTable::ROWS_PER_PAGE => 20,
        DDPTable::ROWS_PER_PAGE_OPTIONS => array(100, 1000)
      )
);
echo $table->getTableWithHeader("DDP Releases");


include "../common/finalise.php";
