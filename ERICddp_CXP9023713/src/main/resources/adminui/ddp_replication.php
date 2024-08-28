<?php
include "init.php";
require_once 'HTML/Table.php';

if ( issetURLParam('run')) {
    callGenericPhpToRootWrapper( 'updaterepl', null, '/data/ddp/log/updaterepl.log' );

    echo "You will be redirected to replication status page shortly...";

    $base_page = basename(__FILE__);
    header( "refresh:2;url=$base_page" );
    exit;
}

$repls_array = array();

callGenericPhpToRootWrapper( 'updaterepl', '-c', '/data/tmp/updatereplstatus.txt' );

$filecontents = shell_exec("cat /data/tmp/updatereplstatus.txt");
$file_array = array_filter(explode("\n", $filecontents));
foreach ( $file_array as $lines_array ) {
    if ( preg_match('/Replication Status for ([^\s]+) (.+)$/', $lines_array, $matches) ) {
        array_push( $repls_array, array($matches[1], $matches[2]) );
    }
}

$statuscmd = "ps -ef | grep 'sh .*[u]pdateRepl'";
$result = exec($statuscmd);
if(preg_match("/updateRepl/", $result)){
    $repl_running = true;
} else {
    $repl_running = false;
}

$update_required = false;
echo "<h1>DDP Replication Status</h1>\n";
$new_array = array();
$statusTable = new HTML_Table("border=1");
foreach ( $repls_array as $repl ) {
   if ( $repl_running ) {
       $statusTable->addRow( array( "Replication Status&nbsp;" . "<br>" . "[" . $repl[0] . "]:" , "REPLICATION UPDATE IN PROGRESS..." ) );
   } elseif ( "$repl[1]" === "synced"  ) {
       $statusTable->addRow( array( "Replication Status&nbsp;" . "<br>" . "[" . $repl[0] . "]:" , strtoupper($repl[1]) ) );
   } else {
       $statusTable->addRow( array( "Replication Status&nbsp;" . "<br>" . "[" . $repl[0] . "]:" , strtoupper($repl[1]) ) );
       $update_required = true;
   }
}
echo $statusTable->toHTML();

if ( $update_required ) {
    echo "<br>";
    echo "<a href='?run=true' onclick=\"return confirm('Are you sure to run update replication script?')\"><b>Run Update replication script again!</b></a>\n";
}

include "../php/common/finalise.php";

?>
