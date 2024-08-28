<?php
$pageTitle = "User Statistics";
$YUI_DATATABLE = true;
include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . "/classes/ModelledTable.php";

$statsDB = new StatsDB();
$activeUsersListCount = $_GET['activeUsersListCount'];
$usersListCount = $_GET['usersListCount'];

$usersStatisticsHelp = <<<EOT
    This page helps to analyze the activity of BIS users.
EOT;
drawHeaderWithHelp("User Statistics", 1, "usersStatisticsHelp", $usersStatisticsHelp);

$activeUsersHelp = <<<EOT
    The below graph plots the number of unique BO users across the day.
EOT;
drawHeaderWithHelp("BO Users", 2, "activeUsersHelp", $activeUsersHelp);

if( $activeUsersListCount > 0 ) {
    $sqlParam =
        array( 'title'   => 'BO Users',
            'ylabel'     => 'Number of BO Users',
            'type'       => 'sb',
            'useragg'    => 'true',
            'persistent' => 'true',
            'presetagg'  => 'COUNT:Per Minute',
            'querylist'  =>
            array(
                array(
                    'timecol'     => 'time',
                    'multiseries' => 'userName',
                    'whatcol'     => array('distinct(userName)' => 'BO Users'),
                    'tables'      => "bis_active_users_list, sites",
                    'where'       => "bis_active_users_list.siteid = sites.id AND sites.name = '%s'",
                    'qargs'       => array('site')
                )
            )
        );

    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 );
    echo "<br><br>";
}
else {
    echo "<H1><font color='red'>No data to be displayed</font></H1>";
    echo "<br><br>";
}

if( $usersListCount > 0 ) {
    $userListHelp = <<<EOT
    The below table displays information about BIS users.
    <ul>
        <li><b>User name</b>: This shows the BIS user name.
        <li><b>Last Login Time</b>: This shows the time when the user last logged into BIS.
    </ul>
    <b>NOTE</b>: If last login time is shown as “---“ , then it indicates that the corresponding user never logged into BIS.
EOT;
    drawHeaderWithHelp("User List", 2, "userListHelp", $userListHelp);
    class UserListTable extends DDPObject {
        var $cols;

        function __construct() {
            parent::__construct("UserListTable");
        }

        function getData() {
            global $date, $site;

            $sql = "
                SELECT
                 siName, max(siLastLogOnTime) as maxLastLogOnTime
                FROM
                 bis_users_list, sites
                WHERE
                 sites.name = '$site' AND
                 sites.id = bis_users_list.siteid AND bis_users_list.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
                GROUP BY
                 bis_users_list.siName
            ";
            $this->populateData($sql);
            foreach ($this->data as &$row) {
                $maxLastLogInTime = $row['maxLastLogOnTime'];
                if($maxLastLogInTime == "0000-00-00 00:00:00") {
                    $maxLastLogInTime = "- - -";
                }
                $row['maxLastLogOnTime'] = $maxLastLogInTime;
            }
            return $this->data;
        }
    }

    $userList = new UserListTable();
    $userList->cols = array('siName' => 'User Name', 'maxLastLogOnTime' => 'Last Login Time');
    $userList->getData();
    echo $userList->getClientSortableTableStr();
    echo "<br>";
}
else {
    if ($usersListCount == 0) {
        $table = new ModelledTable('ENIQ/bis_user_list', 'bisUserListHelp');
        echo $table->getTableWithHeader("User List");
        echo addLineBreak();
    } else {
        echo "<H1><font color='red'>No data to be displayed</font></H1>";
        echo "<br>";
    }
}

include "../common/finalise.php";
?>