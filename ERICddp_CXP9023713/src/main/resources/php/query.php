<?php

/*
Works with the SqlTable class.

query.php is used to feed the rows back to Javascript table in ddptable.js

SqlTable will store in the query in a temporary file.

ddptable.js will issue GET requests with the following parameters
- qid: the name of the temporary file containing the query
The rest of the parameters are optional
- sort: The column to sort on
- dir: direction to sort in
- startIndex: The offset in the result set, e.g. if there are 1000 rows
              returned by the query, and the rowsPerPage is set to 100,
              then when the user clicks on next/page 2, startIndex will be set
              to 100, and then 200 for the next page, etc.
- results: How many rows to return, will be set to rowsPerPage

*/

$UI = false;
$NOREDIR = true;

include "./common/init.php";

$queryId = $_REQUEST['qid'];
$filename = $web_temp_dir . "/" . $queryId;
if ( file_exists($filename) ) {
    $querySql = file_get_contents($filename);
} else {
    echo "ERROR: File dose not exist";
    exit();
}

$orderBy = "";
if ( isset($_REQUEST['sort']) ) {
  if ( isset($_REQUEST['sortfunction']) && $_REQUEST['sortfunction'] == 'forceSortAsNums' ) {
    $orderBy = "ORDER BY CAST(" . $_REQUEST['sort'] . " as UNSIGNED) " . $_REQUEST['dir'];
  } else {
    $orderBy = "ORDER BY " . $_REQUEST['sort'] . " " . $_REQUEST['dir'];
  }
}

$startIndex = 0;
$limit = "";
if ( isset($_REQUEST['startIndex']) || isset($_REQUEST['results']) ) {
  $startIndex = $_REQUEST['startIndex'];
  $limit = "LIMIT "  . $_REQUEST['results'] . " OFFSET " . $_REQUEST['startIndex'];
}

$sql = "$querySql $orderBy $limit";

$statsDB = new StatsDB();
$statsDB->query($sql);

$result = array();

if ( isset($_REQUEST['export']) && $_REQUEST['export'] == 'csv' ) {
  # Output CSV file.

  # Disable buffering to avoid running out of memory.
  if (ob_get_level()) {
    ob_end_clean();
  }

  $createdDate = date('Y-m-d\TH:i:s');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=ddp-report-' . $createdDate . '.csv');

  $output = fopen('php://output', 'w');
  $tableParams = json_decode($_REQUEST['tableParams'], true);
  $columns = array();
  $columnIds = array();
  foreach ( $tableParams['columns'] as $col) {
    if ( !array_key_exists('visible', $col ) || $col['visible'] ) {
      $columns[] = $col['label'];
      $columnIds[] = $col['key'];
    }
  }
  fputcsv($output, $columns);

  while($row = $statsDB->getNextNamedRow()) {
    $dataRow = array();
    foreach ( $columnIds as $col) {
      $dataRow[] = $row[$col];
    }
    fputcsv($output, $dataRow);
  }
  exit;

} else {
  # Output JSON

  while($row = $statsDB->getNextNamedRow()) {
    $result[] = $row;
  }

  header('Content-Type: application/json');
  echo json_encode(array( 'rows' => $result,
                          'startIndex' => $startIndex
                          ),
                   JSON_HEX_TAG|JSON_HEX_AMP);
}
?>
