<?php
# Override these values by creating your own environment file
# called "env.php", in the same directory as this file

# Every ddp server should have a dbhost defined in the hosts file
$DBhost = "dbhost";

$DBName = "statsdb";

# User id/passwd used for read only access
$DBuser = "statsusr";
$DBpass = "_susr";

# User id/passwd used for read only access
$DBwuser = "statsadm";
$DBwpass = "_sadm";

$AdminDB = "ddpadmin";

$ReplHost = ":/data/repl/var/mysql_rep.sock";
$ReplUser = "repladm";
$ReplPass = "_repladm";

$stats_dir = "/data/stats";
$web_temp_dir = $stats_dir . "/temp";
$perfLog = "/data/ddp/log/perf.log";

// Set some defaults
// Default stats directory
$archive_dir = "/data/archive";
$nas_archive_dir = "/nas/archive";
$nas_data_files = "/nas/data_files";

if ( file_exists("/opt/gnuplot/bin/gnuplot" ) ) {
    $gnuPlot = "/opt/gnuplot/bin/gnuplot";
} else {
    $gnuPlot = "/usr/bin/gnuplot";
}

$ddp_dir = "/data/ddp/current";

$ftproot_dir = "/data/ftproot";
