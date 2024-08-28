<?php
$pageTitle = "NFS Server V3 Operations";

include "common/init.php";
require_once 'HTML/Table.php';
require_once "SqlPlotParam.php";

function getTable($statsDB,$serverId,$fromDate,$toDate) {
  global $site;

  $table = new HTML_Table('border=1');
  $table->addRow( array( 'Stat', 'Total', 'Average'), null, 'th');

  $row = $statsDB->queryNamedRow("
SELECT
  SUM(null_op) AS null_op_total, ROUND( AVG(null_op), 0 ) AS null_op_avg,
  SUM(getattr) AS getattr_total, ROUND( AVG(getattr), 0 ) AS getattr_avg,
  SUM(setattr) AS setattr_total, ROUND( AVG(setattr), 0 ) AS setattr_avg,
  SUM(lookup) AS lookup_total, ROUND( AVG(lookup), 0 ) AS lookup_avg,
  SUM(access) AS access_total, ROUND( AVG(access), 0 ) AS access_avg,
  SUM(readlink) AS readlink_total, ROUND( AVG(readlink), 0 ) AS readlink_avg,
  SUM(read_op) AS read_op_total, ROUND( AVG(read_op), 0 ) AS read_op_avg,
  SUM(write_op) AS write_op_total, ROUND( AVG(write_op), 0 ) AS write_op_avg,
  SUM(create_op) AS create_op_total, ROUND( AVG(create_op), 0 ) AS create_op_avg,
  SUM(mkdir) AS mkdir_total, ROUND( AVG(mkdir), 0 ) AS mkdir_avg,
  SUM(symlink) AS symlink_total, ROUND( AVG(symlink), 0 ) AS symlink_avg,
  SUM(mknod) AS mknod_total, ROUND( AVG(mknod), 0 ) AS mknod_avg,
  SUM(remove) AS remove_total, ROUND( AVG(remove), 0 ) AS remove_avg,
  SUM(rmdir) AS rmdir_total, ROUND( AVG(rmdir), 0 ) AS rmdir_avg,
  SUM(rename_op) AS rename_op_total, ROUND( AVG(rename_op), 0 ) AS rename_op_avg,
  SUM(link) AS link_total, ROUND( AVG(link), 0 ) AS link_avg,
  SUM(readdir) AS readdir_total, ROUND( AVG(readdir), 0 ) AS readdir_avg,
  SUM(readdirplus) AS readdirplus_total, ROUND( AVG(readdirplus), 0 ) AS readdirplus_avg,
  SUM(fsstat) AS fsstat_total, ROUND( AVG(fsstat), 0 ) AS fsstat_avg,
  SUM(fsinfo) AS fsinfo_total, ROUND( AVG(fsinfo), 0 ) AS fsinfo_avg,
  SUM(pathconf) AS pathconf_total, ROUND( AVG(pathconf), 0 ) AS pathconf_avg,
  SUM(commit_op) AS commit_op_total, ROUND( AVG(commit_op), 0 ) AS commit_op_avg
 FROM nfsd_v3ops, sites
 WHERE
  nfsd_v3ops.siteid = sites.id AND sites.name = '$site' AND
  serverid = $serverId AND
  time BETWEEN '$fromDate 00:00:00' AND '$toDate 23:59:59'");

  $opList = array( "null_op","getattr","setattr","lookup","access","readlink","read_op","write_op","create_op","mkdir","symlink","mknod","remove","rmdir","rename_op","link","readdir","readdirplus","fsstat","fsinfo","pathconf","commit_op");

  foreach ( $opList as $op ) {
    $table->addRow( array( $op, $row[$op . "_total"], $row[$op . "_avg"]) );
  }

  return $table;
}

#
# Main
#
$statsDB = new StatsDB();

if ( isset($_GET['start']) ) {
   $fromDate = $_GET['start'];
   $toDate = $_GET['end'];
} else {
   $fromDate = $date;
   $toDate = $date;
}
$serverId = $_GET['serverid'];

drawHeader("NFS Server V3 Operations", 1, "NFS_Server_V3_Operations");

$graphTable = new HTML_Table('border=0');

$where = "nfsd_v3ops.siteid = sites.id AND sites.name = '%s' AND nfsd_v3ops.serverid = %d";
$tables = "sites, nfsd_v3ops";
$qargs = array( 'site', 'serverid' );

$sqlParam =
  array( 'title'      => "File Read/Write Operations",
     'ylabel'     => 'Operations',
     'useragg'    => 'true',
     'persistent' => 'true',
     'type'       => 'tsc',
     'querylist' =>
     array(
           array (
              'timecol' => 'time',
              'whatcol' => array(
                     'write_op'  => 'write',
                     'read_op'   => 'read',
                      ),
              SqlPlotParam::TABLES => $tables,
              SqlPlotParam::WHERE  => $where,
              SqlPlotParam::Q_ARGS => $qargs
              )
                 )
     );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
$graphTable->addRow( array(
            $sqlParamWriter->getImgURL( $id,
                        "$fromDate 00:00:00", "$toDate 23:59:59",
                        true, 640, 240,
                        "serverid=$serverId" )
                ));


$sqlParam =
  array( 'title'      => "Filesystem Write Operations",
     'ylabel'     => 'Operations',
     'useragg'    => 'true',
     'persistent' => 'true',
     'type'       => 'sa',
     'querylist' =>
     array(
           array (
              'timecol' => 'time',
              'whatcol' => array(
                     'setattr'   => 'setattr',
                     'create_op' => 'create',
                     'remove'    => 'remove',
                     'rename_op' => 'rename',
                     'link'      => 'link',
                     'symlink'   => 'symlink',
                      ),
              SqlPlotParam::TABLES => $tables,
              SqlPlotParam::WHERE  => $where,
              SqlPlotParam::Q_ARGS => $qargs
              )
                 )
     );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
$graphTable->addRow( array(
            $sqlParamWriter->getImgURL( $id,
                        "$fromDate 00:00:00", "$toDate 23:59:59",
                        true, 640, 240,
                        "serverid=$serverId" )
                ));

$sqlParam =
  array( 'title'      => "Filesystem Read Operations",
     'ylabel'     => 'Operations',
     'useragg'    => 'true',
     'persistent' => 'true',
     'type'       => 'sa',
     'querylist' =>
     array(
           array (
              'timecol' => 'time',
              'whatcol' => array(
                     'getattr'   => 'getattr',
                     'lookup'    => 'lookup',
                     'readlink'  => 'readlink',
                     'access'    => 'access'
                      ),
              SqlPlotParam::TABLES => $tables,
              SqlPlotParam::WHERE  => $where,
              SqlPlotParam::Q_ARGS => $qargs
              )
                 )
     );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
$graphTable->addRow( array(
               $sqlParamWriter->getImgURL( $id,
                               "$fromDate 00:00:00", "$toDate 23:59:59",
                               true, 640, 240,
                               "serverid=$serverId" )
               ));

$sqlParam =
  array( 'title'      => "Other Operations",
     'ylabel'     => 'Operations',
     'useragg'    => 'true',
     'persistent' => 'true',
     'type'       => 'sa',
     'querylist' =>
     array(
           array (
              'timecol' => 'time',
              'whatcol' => array(
                     'mkdir'    => 'mkdir',
                     'readdir'  => 'readdir',
                     'readdirplus' => 'readdirplus',
                     'access'    => 'access',
                     'fsstat' => 'fsstat',
                     'commit_op'   => 'commit'
                      ),
              SqlPlotParam::TABLES => $tables,
              SqlPlotParam::WHERE  => $where,
              SqlPlotParam::Q_ARGS => $qargs
              )
                 )
     );
$sqlParamWriter = new SqlPlotParam();
$id = $sqlParamWriter->saveParams($sqlParam);
$graphTable->addRow( array(
               $sqlParamWriter->getImgURL( $id,
                               "$fromDate 00:00:00", "$toDate 23:59:59",
                               true, 640, 240,
                               "serverid=$serverId" )
               ));


echo $graphTable->toHTML();

echo "<H2>Operation Stats"; drawHelpLink("opstat"); echo "</H2>\n";
drawHelp("opstat", "NFS Operations", "

<table>
<tr> <td>Procedure Name</td> <td>Procedure Summary</td> <td>Description</td> </tr>
<tr> <td>null</td> <td>Do Nothing</td> <td>Dummy procedure provided for testing purposes.</td> </tr>
<tr> <td>getattr</td> <td>Get File Attributes</td> <td>Retrieves the attributes of a file on a remote server.</td> </tr>
<tr> <td>setattr</td> <td>Set File Attributes</td> <td>Sets (changes) the attributes of a file on a remote server.</td> </tr>
<tr> <td>lookup</td> <td>Look Up File Name</td> <td>Returns the file handle of a file for the client to use.</td> </tr>
<tr> <td>readlink</td> <td>Read From Symbolic Link</td> <td>Reads the name of a file specified using a symbolic link.</td> </tr>
<tr> <td>read</td> <td>Read From File</td> <td>Reads data from a file.</td> </tr>
<tr> <td>write</td> <td>Write To File</td> <td>Writes data to a file.</td> </tr>
<tr> <td>create</td> <td>Create File</td> <td>Creates a file on the server.</td> </tr>
<tr> <td>remove</td> <td>Remove File</td> <td>Deletes a file from the server.</td> </tr>
<tr> <td>rename</td> <td>Rename File</td> <td>Changes the name of a file.</td> </tr>
<tr> <td>link</td> <td>Create Link To File</td> <td>Creates a hard (non-symbolic) link to a file.</td> </tr>
<tr> <td>symlink</td> <td>Create Symbolic Link</td> <td>Creates a symbolic link to a file.</td> </tr>
<tr> <td>mkdir</td> <td>Create Directory</td> <td>Creates a directory on the server.</td> </tr>
<tr> <td>rmdir</td> <td>Remove Directory</td> <td>Deletes a directory.</td> </tr>
<tr> <td>readdir</td> <td>Read From Directory</td> <td>Reads the contents of a directory.</td> </tr>
<tr> <td>access</td> <td>Check Access Permission</td> <td>Determines the access rights that a user has for a particular file system object.</td> </tr>
<tr> <td>mknod</td> <td>Create A Special Device</td> <td>Creates a special file such as a named pipe or device file.</td> </tr>
<tr> <td>readdirplus</td> <td>Extended Read From Directory</td> <td>Retrieves additional information from a directory.</td> </tr>
<tr> <td>fsstat</td> <td>Get Dynamic File System Information</td> <td>Returns volatile (dynamic) file system status information such as the current amount of file system free space and the number of free file slots.</td> </tr>
<tr> <td>fsinfo</td> <td>Get Static File System Information</td> <td>Returns static information about the file system, such as general data about how the file system is used, and parameters for how requests to the server should be structured.</td> </tr>
<tr> <td>pathconf</td> <td>Retrieve POSIX Information</td> <td>Retrieves additional information for a file or directory.</td> </tr>
<tr> <td>commit</td> <td>Commit Cached Data On A Server To Stable Storage</td> <td>Flushes any data that the server is holding in a write cache to storage. This is used to ensure that any data that the client has sent to the server but that the server has held pending write to storage is in fact written out.</td> </tr>
</table>");

$statTable = getTable($statsDB,$serverId,$fromDate,$toDate);
echo $statTable->toHTML();

$statsDB->disconnect();

include "common/finalise.php";
