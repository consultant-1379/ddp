<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class JobMgrComplexity extends DDPObject {
	
    var $title = "Job Complexity";
    var $cols = array(
        "jobcatgid" => "Job Category Id",
        "jobname" => "Job Name",
        "activityname" => "Activity Name",
        "scriptname" => "Script Name",
        "userid" => "User",
		"createdtime" => "Created Time"
    );
	
    // Override defaults for these
    var $defaultLimit = 20;
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "activityname";

    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");
	function __construct() {
        parent::__construct("job_complex");
    }
	
	
    function getData($site = SITE, $date = DATE) {
        $sql = "
SELECT job_mgr_complexity_data.jobcatgid AS jobcatgid, job_mgr_complexity_data.jobname AS jobname, 
       job_mgr_complexity_data.activityname AS activityname,
       job_mgr_scriptnames.name AS scriptname, oss_users.name AS userid ,
       job_mgr_complexity_data.createdtime AS createdtime 
FROM job_mgr_complexity_data, job_mgr_complexity, job_mgr_scriptnames, oss_users, sites
WHERE job_mgr_complexity.siteid = sites.id AND sites.name = '$site' AND
      job_mgr_complexity.jobcomplexid=job_mgr_complexity_data.id AND 
      job_mgr_complexity_data.userid = oss_users.id AND
      job_mgr_complexity_data.scriptid = job_mgr_scriptnames.id AND
      job_mgr_complexity.date='$date'";
	$this->populateData($sql);
        return $this->data;
	}
}
?>

<?php
require_once PHP_ROOT . "/classes/DDPObject.class.php";

class JobMgrSupervisor extends DDPObject {
	
    var $title = "Job Supervisor";
    var $cols = array(
        "jobid" => "Job Id",
        "jobcatgid" => "Job Category Id",
        "jobname" => "Job Name",
        "status" => "Job Status",
		"frequency" => "Job Frequency" ,
		"userid" => "User" ,
		"jobschedtime" => "Job Schedule Time" ,
		"jobstarttime" => "Job Start Time" ,
		"jobstoptime" => "Job Stop Time"
    );
	
    // Override defaults for these
    var $defaultLimit = 20;
    var $defaultOrderDir = "ASC";
    var $defaultOrderBy = "jobname";

    var $limits = array(25 => 25, 50 => 50, 100 => 100, "" => "Unlimited");
function __construct() 
{
    parent::__construct("job_super");
}
	
function getData($site = SITE, $date = DATE) 
{
        $sql = "SELECT job_mgr_supervisor_data.jobid AS jobid, job_mgr_supervisor_data.jobcatgid AS jobcatgid ,job_mgr_supervisor_data.jobname AS jobname," .
		"job_mgr_supervisor_data.status AS status,job_mgr_supervisor_data.frequency AS frequency, oss_users.name AS userid, job_mgr_supervisor_data.jobschedtime AS jobschedtime, ".
	    "job_mgr_supervisor_data.jobstarttime AS jobstarttime, job_mgr_supervisor_data.jobstoptime AS jobstoptime FROM job_mgr_supervisor_data, job_mgr_supervisor, oss_users WHERE " .
        "job_mgr_supervisor.siteid = (SELECT sites.id FROM sites WHERE sites.name='" . $site . "')  AND job_mgr_supervisor.date ='" . $date . "' AND " .
	    "job_mgr_supervisor.jobsuperid=job_mgr_supervisor_data.id AND oss_users.name=(SELECT oss_users.name from oss_users where oss_users.id=job_mgr_supervisor_data.userid)";
        $this->populateData($sql);
        return $this->data;
    }
}
?>
