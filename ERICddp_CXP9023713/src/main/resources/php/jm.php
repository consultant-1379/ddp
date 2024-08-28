<?php
$pageTitle = "Job Manager";
include "common/init.php";

require_once 'HTML/Table.php';
?>


<h1>Job Manager Statistics</h1>
<ul>
 <li><a href="#summary">Job Summary</a></li>
 <li><a href="#db">Job Database</a></li>
 <li><a href="#supervisor">Job Supervisor</a></li>
 <li><a href="#complex">Job Complexity</a></li>
<?php
echo "<a name=summary>\n";
  echo "<H2>"; drawHelpLink("summaryhelp"); echo "Job Summary</H2>\n";
    drawHelp("summaryhelp", "Job Summary",
        "
Displays the overall summary of Jobs. This information is retrieved via the ActivityManager Database.
");
?> 

<table border>
 
<?php
$statsDB = new StatsDB();
$sitesql = "SELECT sites.id  FROM servers, sites  WHERE sites.name = \"$site\"";
$row = $statsDB->queryRow($sitesql);
$siteId = $row[0];

$sql = "
SELECT job_mgr_jobs.sched_jobs, job_mgr_jobs.completed_jobs, job_mgr_jobs.terminated_jobs, job_mgr_jobs.failed_jobs FROM job_mgr_jobs
WHERE job_mgr_jobs.siteid = $siteId AND date = '$date'";
if ( $debug ) { echo "<p>sql = $sql</p>"; }
$row = $statsDB->queryNamedRow($sql);
 
echo "<tr><td>Scheduled Jobs</td><td>$row[sched_jobs]</td></tr><tr><td>Completed Jobs</td><td>$row[completed_jobs]</td></tr> <tr><td>Terminated Jobs</td><td>$row[terminated_jobs]</td></tr>  <tr><td>Failed Jobs</td><td>$row[failed_jobs]</td></tr>\n";
?>
</table>



<?php
echo "<a name=db>\n";
  echo "<H2>"; drawHelpLink("dbhelp"); echo "Job Database</H2>\n";
    drawHelp("dbhelp", "Job Database",
        "
Displays the overall usage of Job Manager Database.
");
?> 

<table border>
  <?php

$dbsql = "SELECT sybase_dbnames.name AS Name, dbsize AS size, dbsize - datasize AS ldev_size, datasize AS ddev_size, ((datasize - datafree) / datasize) * 100 AS Percent_Used FROM sybase_dbnames, sybase_dbspace WHERE siteid = $siteId AND date = '$date' AND sybase_dbspace.dbid = sybase_dbnames.id AND sybase_dbnames.name='ActivitySupportDatabase'" ;
if ( $debug ) { echo "<p>dbsql = $dbsql</p>"; }
$statsDB->query($dbsql);
$row = $statsDB->queryNamedRow($dbsql);
  echo "<tr><td>Database Name</td><td>$row[Name]</td></tr><tr><td>Database Size</td><td>$row[size]</td></tr><tr><td>LDEV Size</td><td>$row[ldev_size]</td></tr><tr><td>DDEV Size</td><td>$row[ddev_size]</td></tr><tr><td>Percent Used</td><td>$row[Percent_Used]</td></tr>\n";

?>
</table>

<?php
require_once "classes/JobMgr.php";
$jobCmpTbl = new JobMgrComplexity();
$jobSuperTbl = new JobMgrSupervisor();
?>

<h2><?php drawHelpLink("helpcmp"); ?>Job Complexity</h2>
<?php

drawHelp("helpcmp", "Job Complexity", "Job Complexity describes a job with associated activity/activity groups and scripts. This information is retrieved from ActivitySupportDatabase");
$jobCmpTbl->getSortableHtmlTable();
?>

<h2><?php drawHelpLink("helpsup"); ?>Job Supervisor</h2>
<?php

drawHelp("helpsup", "Job Supervisor", "Job Supervisor describes a job with associated category groups with its frequency & status. This information is retrieved from ActivitySupportDatabase");
$jobSuperTbl->getSortableHtmlTable();
?>

<?php
$statsDB->disconnect();
include "common/finalise.php";
?>
