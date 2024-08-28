<?php

$pageTitle = "Auto Provisioning";

include "../../common/init.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";
require_once PHP_ROOT . '/classes/ModelledTable.php';
require_once PHP_ROOT . "/classes/ModelledGraph.php";
require_once PHP_ROOT . "/common/graphFunctions.php";

class OrderProjectExecTimes extends DDPobject {
    var $cols = array(
                      array('key' => 'time', 'label' => 'Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'project', 'label' => 'Project'),
                      array('key' => 'validate_project_time', 'label' => 'VALIDATE_PROJECT_TIME', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'create_project_mo_time', 'label' => 'CREATE_PROJECT_MO', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'create_and_write_project_artifacts_time', 'label' => 'CREATE_AND_WRITE_PROJECT_ARTIFACTS', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'total', 'label' => 'Total', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums'))
                      );

    function __construct() {
        parent::__construct("apserv_order_project_exec_times");
    }
    var $title = "Order Project Execution Times (Millisec)";

    function getData() {
        global $date;
        global $site;

        $sql = "
SELECT
    enm_ap_order_project_stats.time AS 'time',
    enm_ap_project_names.name AS 'project',
    IFNULL(enm_ap_order_project_stats.validate_project_time, 'NA') AS 'validate_project_time',
    IFNULL(enm_ap_order_project_stats.create_project_mo_time, 'NA') AS 'create_project_mo_time',
    IFNULL(enm_ap_order_project_stats.create_and_write_project_artifacts_time, 'NA') AS 'create_and_write_project_artifacts_time',
    IF( validate_project_time IS NULL AND create_project_mo_time IS NULL AND create_and_write_project_artifacts_time IS NULL,
        'NA',
        IFNULL(validate_project_time, 0) + IFNULL(create_project_mo_time, 0) + IFNULL(create_and_write_project_artifacts_time, 0) ) AS 'total'
FROM
    enm_ap_order_project_stats,
    enm_ap_project_names,
    sites
WHERE
    enm_ap_order_project_stats.siteid = sites.id AND
    enm_ap_order_project_stats.projectid = enm_ap_project_names.id AND
    sites.name = '$site' AND
    enm_ap_order_project_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
UNION
SELECT
    'Average' AS 'time',
    'All' AS 'project',
    IFNULL( ROUND( AVG(enm_ap_order_project_stats.validate_project_time), 0 ), 'NA' ) AS 'validate_project_time',
    IFNULL( ROUND( AVG(enm_ap_order_project_stats.create_project_mo_time), 0 ), 'NA' ) AS 'create_project_mo_time',
    IFNULL( ROUND( AVG(enm_ap_order_project_stats.create_and_write_project_artifacts_time), 0 ), 'NA' ) AS 'create_and_write_project_artifacts_time',
    IFNULL( ROUND( AVG(
                        IF( validate_project_time IS NULL AND create_project_mo_time IS NULL AND create_and_write_project_artifacts_time IS NULL,
                            NULL,
                            IFNULL(validate_project_time, 0) + IFNULL(create_project_mo_time, 0) + IFNULL(create_and_write_project_artifacts_time, 0) )
                       ), 0 ), 'NA' ) AS 'total'
FROM
    enm_ap_order_project_stats,
    enm_ap_project_names,
    sites
WHERE
    enm_ap_order_project_stats.siteid = sites.id AND
    enm_ap_order_project_stats.projectid = enm_ap_project_names.id AND
    sites.name = '$site' AND
    enm_ap_order_project_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY time ASC";

        $this->populateData($sql);
        $this->defaultOrderBy = "time";
        $this->defaultOrderDir = "ASC";

        if ( count($this->data) == 1 && $this->data[0]['time'] == 'Average' ) {
            $this->data = array();
        }

        return $this->data;
    }
}

class OrderNodeExecTimes extends DDPobject {
    var $cols = array(
                      array('key' => 'time', 'label' => 'Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'project', 'label' => 'Project'),
                      array('key' => 'node', 'label' => 'Node'),
                      array('key' => 'create_node_mo', 'label' => 'CREATE_NODE_MO', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'create_node_children_mos', 'label' => 'CREATE_NODE_CHILDREN_MOS', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'setup_configuration', 'label' => 'SETUP_CONFIGURATION', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'add_node', 'label' => 'ADD_NODE', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'generate_security', 'label' => 'GENERATE_SECURITY', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'create_file_artifact', 'label' => 'CREATE_FILE_ARTIFACT', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'create_node_user_credentials', 'label' => 'CREATE_NODE_USER_CREDENTIALS', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'bind_during_order', 'label' => 'BIND_DURING_ORDER', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'total', 'label' => 'Total', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums'))
                      );

    function __construct() {
        parent::__construct("apserv_order_node_exec_times");
    }
    var $title = "Order Node Execution Times (Millisec)";

    function getData() {
        global $date;
        global $site;

        $sql = "
SELECT
    enm_ap_order_node_stats.time AS 'time',
    enm_ap_project_names.name AS 'project',
    enm_ne.name AS 'node',
    IFNULL(enm_ap_order_node_stats.create_node_mo, 'NA') AS 'create_node_mo',
    IFNULL(enm_ap_order_node_stats.create_node_children_mos, 'NA') AS 'create_node_children_mos',
    IFNULL(enm_ap_order_node_stats.setup_configuration, 'NA') AS 'setup_configuration',
    IFNULL(enm_ap_order_node_stats.add_node, 'NA') AS 'add_node',
    IFNULL(enm_ap_order_node_stats.generate_security, 'NA') AS 'generate_security',
    IFNULL(enm_ap_order_node_stats.create_file_artifact, 'NA') AS 'create_file_artifact',
    IFNULL(enm_ap_order_node_stats.create_node_user_credentials, 'NA') AS 'create_node_user_credentials',
    IFNULL(enm_ap_order_node_stats.bind_during_order, 'NA') AS 'bind_during_order',
    IF( create_node_mo IS NULL AND create_node_children_mos IS NULL AND setup_configuration IS NULL AND
        add_node IS NULL AND generate_security IS NULL AND create_file_artifact IS NULL AND
        create_node_user_credentials IS NULL AND bind_during_order IS NULL,
        'NA',
        IFNULL(create_node_mo, 0) + IFNULL(create_node_children_mos, 0) + IFNULL(setup_configuration, 0) +
            IFNULL(add_node, 0) + IFNULL(generate_security, 0) + IFNULL(create_file_artifact, 0) +
            IFNULL(create_node_user_credentials, 0) + IFNULL(bind_during_order, 0) ) AS 'total'
FROM
    enm_ap_order_node_stats,
    enm_ap_project_names,
    enm_ne,
    sites
WHERE
    enm_ap_order_node_stats.siteid = sites.id AND
    enm_ap_order_node_stats.projectid = enm_ap_project_names.id AND
    enm_ap_order_node_stats.neid = enm_ne.id AND
    sites.name = '$site' AND
    enm_ap_order_node_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
UNION
SELECT
    'Average' AS 'time',
    'All' AS 'project',
    'All' AS 'node',
    IFNULL( ROUND( AVG(enm_ap_order_node_stats.create_node_mo), 0 ), 'NA' ) AS 'create_node_mo',
    IFNULL( ROUND( AVG(enm_ap_order_node_stats.create_node_children_mos), 0 ), 'NA' ) AS 'create_node_children_mos',
    IFNULL( ROUND( AVG(enm_ap_order_node_stats.setup_configuration), 0 ), 'NA' ) AS 'setup_configuration',
    IFNULL( ROUND( AVG(enm_ap_order_node_stats.add_node), 0 ), 'NA' ) AS 'add_node',
    IFNULL( ROUND( AVG(enm_ap_order_node_stats.generate_security), 0 ), 'NA' ) AS 'generate_security',
    IFNULL( ROUND( AVG(enm_ap_order_node_stats.create_file_artifact), 0 ), 'NA' ) AS 'create_file_artifact',
    IFNULL( ROUND( AVG(enm_ap_order_node_stats.create_node_user_credentials), 0 ), 'NA' ) AS 'create_node_user_credentials',
    IFNULL( ROUND( AVG(enm_ap_order_node_stats.bind_during_order), 0 ), 'NA' ) AS 'bind_during_order',
    IFNULL( ROUND( AVG(
                        IF( create_node_mo IS NULL AND create_node_children_mos IS NULL AND setup_configuration IS NULL AND
                            add_node IS NULL AND generate_security IS NULL AND create_file_artifact IS NULL AND
                            create_node_user_credentials IS NULL AND bind_during_order IS NULL,
                            'NA',
                            IFNULL(create_node_mo, 0) + IFNULL(create_node_children_mos, 0) + IFNULL(setup_configuration, 0) +
                                IFNULL(add_node, 0) + IFNULL(generate_security, 0) + IFNULL(create_file_artifact, 0) +
                                IFNULL(create_node_user_credentials, 0) + IFNULL(bind_during_order, 0) )
                       ), 0 ), 'NA' ) AS 'total'
FROM
    enm_ap_order_node_stats,
    enm_ap_project_names,
    enm_ne,
    sites
WHERE
    enm_ap_order_node_stats.siteid = sites.id AND
    enm_ap_order_node_stats.projectid = enm_ap_project_names.id AND
    enm_ap_order_node_stats.neid = enm_ne.id AND
    sites.name = '$site' AND
    enm_ap_order_node_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY time ASC";

        $this->populateData($sql);
        $this->defaultOrderBy = "time";
        $this->defaultOrderDir = "ASC";

        if ( count($this->data) == 1 && $this->data[0]['time'] == 'Average' ) {
            $this->data = array();
        }

        return $this->data;
    }
}

class IntegrateNodeExecTimes extends DDPobject {
    var $cols = array(
                      array('key' => 'time', 'label' => 'Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'project', 'label' => 'Project'),
                      array('key' => 'node', 'label' => 'Node'),
                      array('key' => 'initiate_sync_node', 'label' => 'INITIATE_SYNC_NODE', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'import_configurations', 'label' => 'IMPORT_CONFIGURATIONS', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'enable_supervision', 'label' => 'ENABLE_SUPERVISION', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'create_cv', 'label' => 'CREATE_CV', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'create_backup', 'label' => 'CREATE_BACKUP', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'activate_optional_features', 'label' => 'ACTIVATE_OPTIONAL_FEATURES', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'gps_position_check', 'label' => 'GPS_POSITION_CHECK', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'unlock_cells', 'label' => 'UNLOCK_CELLS', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'upload_cv', 'label' => 'UPLOAD_CV', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'upload_backup', 'label' => 'UPLOAD_BACKUP', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'total', 'label' => 'Total', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums'))
                      );

    function __construct() {
        parent::__construct("apserv_integrate_node_exec_times");
    }
    var $title = "Integrate Node Execution Times (Millisec)";

    function getData() {
        global $date;
        global $site;

        $sql = "
SELECT
    enm_ap_integrate_node_stats.time AS 'time',
    enm_ap_project_names.name AS 'project',
    enm_ne.name AS 'node',
    IFNULL(enm_ap_integrate_node_stats.initiate_sync_node, 'NA') AS 'initiate_sync_node',
    IFNULL(enm_ap_integrate_node_stats.import_configurations, 'NA') AS 'import_configurations',
    IFNULL(enm_ap_integrate_node_stats.enable_supervision, 'NA') AS 'enable_supervision',
    IFNULL(enm_ap_integrate_node_stats.create_cv, 'NA') AS 'create_cv',
    IFNULL(enm_ap_integrate_node_stats.create_backup, 'NA') AS 'create_backup',
    IFNULL(enm_ap_integrate_node_stats.activate_optional_features, 'NA') AS 'activate_optional_features',
    IFNULL(enm_ap_integrate_node_stats.gps_position_check, 'NA') AS 'gps_position_check',
    IFNULL(enm_ap_integrate_node_stats.unlock_cells, 'NA') AS 'unlock_cells',
    IFNULL(enm_ap_integrate_node_stats.upload_cv, 'NA') AS 'upload_cv',
    IFNULL(enm_ap_integrate_node_stats.upload_backup, 'NA') AS 'upload_backup',
    IF( initiate_sync_node IS NULL AND import_configurations IS NULL AND enable_supervision IS NULL AND
        create_cv IS NULL AND create_backup IS NULL AND activate_optional_features IS NULL AND
        gps_position_check IS NULL AND unlock_cells IS NULL AND upload_cv IS NULL AND upload_backup IS NULL,
        'NA',
        IFNULL(initiate_sync_node, 0) + IFNULL(import_configurations, 0) + IFNULL(enable_supervision, 0) +
            IFNULL(create_cv, 0) + IFNULL(create_backup, 0) + IFNULL(activate_optional_features, 0) +
            IFNULL(gps_position_check, 0) + IFNULL(unlock_cells, 0) + IFNULL(upload_cv, 0) +
            IFNULL(upload_backup, 0) ) AS 'total'
FROM
    enm_ap_integrate_node_stats,
    enm_ap_project_names,
    enm_ne,
    sites
WHERE
    enm_ap_integrate_node_stats.siteid = sites.id AND
    enm_ap_integrate_node_stats.projectid = enm_ap_project_names.id AND
    enm_ap_integrate_node_stats.neid = enm_ne.id AND
    sites.name = '$site' AND
    enm_ap_integrate_node_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
UNION
SELECT
    'Average' AS 'time',
    'All' AS 'project',
    'All' AS 'node',
    IFNULL( ROUND( AVG(enm_ap_integrate_node_stats.initiate_sync_node), 0 ), 'NA' ) AS 'initiate_sync_node',
    IFNULL( ROUND( AVG(enm_ap_integrate_node_stats.import_configurations), 0 ), 'NA' ) AS 'import_configurations',
    IFNULL( ROUND( AVG(enm_ap_integrate_node_stats.enable_supervision), 0 ), 'NA' ) AS 'enable_supervision',
    IFNULL( ROUND( AVG(enm_ap_integrate_node_stats.create_cv), 0 ), 'NA' ) AS 'create_cv',
    IFNULL( ROUND( AVG(enm_ap_integrate_node_stats.create_backup), 0 ), 'NA' ) AS 'create_backup',
    IFNULL( ROUND( AVG(enm_ap_integrate_node_stats.activate_optional_features), 0 ), 'NA' ) AS 'activate_optional_features',
    IFNULL( ROUND( AVG(enm_ap_integrate_node_stats.gps_position_check), 0 ), 'NA' ) AS 'gps_position_check',
    IFNULL( ROUND( AVG(enm_ap_integrate_node_stats.unlock_cells), 0 ), 'NA' ) AS 'unlock_cells',
    IFNULL( ROUND( AVG(enm_ap_integrate_node_stats.upload_cv), 0 ), 'NA' ) AS 'upload_cv',
    IFNULL( ROUND( AVG(enm_ap_integrate_node_stats.upload_backup), 0 ), 'NA' ) AS 'upload_backup',
    IFNULL( ROUND( AVG(
                        IF( initiate_sync_node IS NULL AND import_configurations IS NULL AND enable_supervision IS NULL AND
                            create_cv IS NULL AND create_backup IS NULL AND activate_optional_features IS NULL AND
                            gps_position_check IS NULL AND unlock_cells IS NULL AND upload_cv IS NULL AND upload_backup IS NULL,
                            'NA',
                            IFNULL(initiate_sync_node, 0) + IFNULL(import_configurations, 0) + IFNULL(enable_supervision, 0) +
                                IFNULL(create_cv, 0) + IFNULL(create_backup, 0) + IFNULL(activate_optional_features, 0) +
                                IFNULL(gps_position_check, 0) + IFNULL(unlock_cells, 0) + IFNULL(upload_cv, 0) +
                                IFNULL(upload_backup, 0) )
                       ), 0 ), 'NA' ) AS 'total'
FROM
    enm_ap_integrate_node_stats,
    enm_ap_project_names,
    enm_ne,
    sites
WHERE
    enm_ap_integrate_node_stats.siteid = sites.id AND
    enm_ap_integrate_node_stats.projectid = enm_ap_project_names.id AND
    enm_ap_integrate_node_stats.neid = enm_ne.id AND
    sites.name = '$site' AND
    enm_ap_integrate_node_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY time ASC";

        $this->populateData($sql);
        $this->defaultOrderBy = "time";
        $this->defaultOrderDir = "ASC";

        if ( count($this->data) == 1 && $this->data[0]['time'] == 'Average' ) {
            $this->data = array();
        }

        return $this->data;
    }
}

class DeleteNodeExecTimes extends DDPobject {
    var $cols = array(
                      array('key' => 'time', 'label' => 'Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'project', 'label' => 'Project'),
                      array('key' => 'node', 'label' => 'Node'),
                      array('key' => 'remove_node', 'label' => 'REMOVE_NODE', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'cancel_security', 'label' => 'CANCEL_SECURITY', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'remove_backup', 'label' => 'REMOVE_BACKUP', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'delete_raw_and_generated_node_artifacts', 'label' => 'DELETE_RAW_AND_GENERATED_NODE_ARTIFACTS', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'delete_node_mo', 'label' => 'DELETE_NODE_MO', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'total', 'label' => 'Total', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums'))
                      );

    function __construct() {
        parent::__construct("apserv_delete_node_exec_times");
    }
    var $title = "Delete Node Execution Times (Millisec)";

    function getData() {
        global $date;
        global $site;

        $sql = "
SELECT
    enm_ap_delete_node_stats.time AS 'time',
    enm_ap_project_names.name AS 'project',
    enm_ne.name AS 'node',
    IFNULL(enm_ap_delete_node_stats.remove_node, 'NA') AS 'remove_node',
    IFNULL(enm_ap_delete_node_stats.cancel_security, 'NA') AS 'cancel_security',
    IFNULL(enm_ap_delete_node_stats.remove_backup, 'NA') AS 'remove_backup',
    IFNULL(enm_ap_delete_node_stats.delete_raw_and_generated_node_artifacts, 'NA') AS 'delete_raw_and_generated_node_artifacts',
    IFNULL(enm_ap_delete_node_stats.delete_node_mo, 'NA') AS 'delete_node_mo',
    IF( remove_node IS NULL AND cancel_security IS NULL AND remove_backup IS NULL AND
        delete_raw_and_generated_node_artifacts IS NULL AND delete_node_mo IS NULL,
        'NA',
        IFNULL(remove_node, 0) + IFNULL(cancel_security, 0) + IFNULL(remove_backup, 0) +
            IFNULL(delete_raw_and_generated_node_artifacts, 0) + IFNULL(delete_node_mo, 0) ) AS 'total'
FROM
    enm_ap_delete_node_stats,
    enm_ap_project_names,
    enm_ne,
    sites
WHERE
    enm_ap_delete_node_stats.siteid = sites.id AND
    enm_ap_delete_node_stats.projectid = enm_ap_project_names.id AND
    enm_ap_delete_node_stats.neid = enm_ne.id AND
    sites.name = '$site' AND
    enm_ap_delete_node_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
UNION
SELECT
    'Average' AS 'time',
    'All' AS 'project',
    'All' AS 'node',
    IFNULL( ROUND( AVG(enm_ap_delete_node_stats.remove_node), 0 ), 'NA' ) AS 'remove_node',
    IFNULL( ROUND( AVG(enm_ap_delete_node_stats.cancel_security), 0 ), 'NA' ) AS 'cancel_security',
    IFNULL( ROUND( AVG(enm_ap_delete_node_stats.remove_backup), 0 ), 'NA' ) AS 'remove_backup',
    IFNULL( ROUND( AVG(enm_ap_delete_node_stats.delete_raw_and_generated_node_artifacts), 0 ), 'NA' ) AS 'delete_raw_and_generated_node_artifacts',
    IFNULL( ROUND( AVG(enm_ap_delete_node_stats.delete_node_mo), 0 ), 'NA' ) AS 'delete_node_mo',
    IFNULL( ROUND( AVG(
                        IF( remove_node IS NULL AND cancel_security IS NULL AND remove_backup IS NULL AND
                            delete_raw_and_generated_node_artifacts IS NULL AND delete_node_mo IS NULL,
                            'NA',
                            IFNULL(remove_node, 0) + IFNULL(cancel_security, 0) + IFNULL(remove_backup, 0) +
                                IFNULL(delete_raw_and_generated_node_artifacts, 0) + IFNULL(delete_node_mo, 0) )
                       ), 0 ), 'NA' ) AS 'total'
FROM
    enm_ap_delete_node_stats,
    enm_ap_project_names,
    enm_ne,
    sites
WHERE
    enm_ap_delete_node_stats.siteid = sites.id AND
    enm_ap_delete_node_stats.projectid = enm_ap_project_names.id AND
    enm_ap_delete_node_stats.neid = enm_ne.id AND
    sites.name = '$site' AND
    enm_ap_delete_node_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY time ASC";

        $this->populateData($sql);
        $this->defaultOrderBy = "time";
        $this->defaultOrderDir = "ASC";

        if ( count($this->data) == 1 && $this->data[0]['time'] == 'Average' ) {
            $this->data = array();
        }

        return $this->data;
    }
}

class DeleteProjectExecTimes extends DDPobject {
    var $cols = array(
                      array('key' => 'time', 'label' => 'Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'project', 'label' => 'Project'),
                      array('key' => 'delete_raw_and_generated_project_artifacts', 'label' => 'DELETE_RAW_AND_GENERATED_PROJECT_ARTIFACTS', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'delete_project_mo', 'label' => 'DELETE_PROJECT_MO', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'total', 'label' => 'Total', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums'))
                      );

    function __construct() {
        parent::__construct("delete_order_project_exec_times");
    }
    var $title = "Delete Project Execution Times (Millisec)";

    function getData() {
        global $date;
        global $site;

        $sql = "
SELECT
    enm_ap_delete_project_stats.time AS 'time',
    enm_ap_project_names.name AS 'project',
    IFNULL(enm_ap_delete_project_stats.delete_raw_and_generated_project_artifacts, 'NA') AS 'delete_raw_and_generated_project_artifacts',
    IFNULL(enm_ap_delete_project_stats.delete_project_mo, 'NA') AS 'delete_project_mo',
    IF( delete_raw_and_generated_project_artifacts IS NULL AND delete_project_mo IS NULL,
        'NA',
        IFNULL(delete_raw_and_generated_project_artifacts, 0) + IFNULL(delete_project_mo, 0) ) AS 'total'
FROM
    enm_ap_delete_project_stats,
    enm_ap_project_names,
    sites
WHERE
    enm_ap_delete_project_stats.siteid = sites.id AND
    enm_ap_delete_project_stats.projectid = enm_ap_project_names.id AND
    sites.name = '$site' AND
    enm_ap_delete_project_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
UNION
SELECT
    'Average' AS 'time',
    'All' AS 'project',
    IFNULL( ROUND( AVG(enm_ap_delete_project_stats.delete_raw_and_generated_project_artifacts), 0 ), 'NA' ) AS 'delete_raw_and_generated_project_artifacts',
    IFNULL( ROUND( AVG(enm_ap_delete_project_stats.delete_project_mo), 0 ), 'NA' ) AS 'delete_project_mo',
    IFNULL( ROUND( AVG(
                        IF( delete_raw_and_generated_project_artifacts IS NULL AND delete_project_mo IS NULL,
                            'NA',
                            IFNULL(delete_raw_and_generated_project_artifacts, 0) + IFNULL(delete_project_mo, 0) )
                       ), 0 ), 'NA' ) AS 'total'
FROM
    enm_ap_delete_project_stats,
    enm_ap_project_names,
    sites
WHERE
    enm_ap_delete_project_stats.siteid = sites.id AND
    enm_ap_delete_project_stats.projectid = enm_ap_project_names.id AND
    sites.name = '$site' AND
    enm_ap_delete_project_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY time ASC";

        $this->populateData($sql);
        $this->defaultOrderBy = "time";
        $this->defaultOrderDir = "ASC";

        if ( count($this->data) == 1 && $this->data[0]['time'] == 'Average' ) {
            $this->data = array();
        }

        return $this->data;
    }
}

class HardwareReplaceExecTimes extends DDPobject {
    var $cols = array(
                      array('key' => 'time', 'label' => 'Time', 'formatter' => 'ddpFormatTime'),
                      array('key' => 'node', 'label' => 'Node Name'),
                      array('key' => 'generate_hardware_replace_node_data_time', 'label' => 'GENERATE_HARDWARE_REPLACE_NODE_DATA', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'generate_hardware_replace_icf_time', 'label' => 'GENERATE_HARDWARE_REPLACE_ICF', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums')),
                      array('key' => 'total', 'label' => 'Total', 'formatter' => 'ddpFormatNumber', 'sortOptions' => array('sortFunction' => 'forceSortAsNums'))
                      );

    function __construct() {
        parent::__construct("hardware_replace_exec_times");
    }
    var $title = "Hardware Replace Execution Times (Millisec)";

    function getData() {
        global $date;
        global $site;

        $sql = "
SELECT
    enm_ap_hardware_replace_stats.time AS 'time',
    enm_ne.name AS 'node',
    IFNULL(enm_ap_hardware_replace_stats.generate_hardware_replace_node_data_time, 'NA') AS 'generate_hardware_replace_node_data_time',
    IFNULL(enm_ap_hardware_replace_stats.generate_hardware_replace_icf_time, 'NA') AS 'generate_hardware_replace_icf_time',
    IF( generate_hardware_replace_node_data_time IS NULL AND generate_hardware_replace_icf_time IS NULL,
        'NA',
        IFNULL(generate_hardware_replace_node_data_time, 0) + IFNULL(generate_hardware_replace_icf_time, 0) ) AS 'total'
FROM
    enm_ap_hardware_replace_stats,
    enm_ne,
    sites
WHERE
    enm_ap_hardware_replace_stats.siteid = sites.id AND
    enm_ap_hardware_replace_stats.nodeid = enm_ne.id AND
    sites.name = '$site' AND
    enm_ap_hardware_replace_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
UNION
SELECT
    'Average' AS 'time',
    'All' AS 'node',
    IFNULL( ROUND( AVG(enm_ap_hardware_replace_stats.generate_hardware_replace_node_data_time), 0 ), 'NA' ) AS 'generate_hardware_replace_node_data_time',
    IFNULL( ROUND( AVG(enm_ap_hardware_replace_stats.generate_hardware_replace_icf_time), 0 ), 'NA' ) AS 'generate_hardware_replace_icf_time',
    IFNULL( ROUND( AVG(
                        IF( generate_hardware_replace_node_data_time IS NULL AND generate_hardware_replace_icf_time IS NULL,
                            'NA',
                            IFNULL(generate_hardware_replace_node_data_time, 0) + IFNULL(generate_hardware_replace_icf_time, 0) )
                       ), 0 ), 'NA' ) AS 'total'
FROM
    enm_ap_hardware_replace_stats,
    enm_ne,
    sites
WHERE
    enm_ap_hardware_replace_stats.siteid = sites.id AND
    enm_ap_hardware_replace_stats.nodeid = enm_ne.id AND
    sites.name = '$site' AND
    enm_ap_hardware_replace_stats.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
ORDER BY time ASC";

        $this->populateData($sql);
        $this->defaultOrderBy = "time";
        $this->defaultOrderDir = "ASC";

        if ( count($this->data) == 1 && $this->data[0]['time'] == 'Average' ) {
            $this->data = array();
        }

        return $this->data;
    }
}

function orderProjParams() {
    return array(
        'Order Project Daily Stats',
        'order_proj_daily_stats',
        'orderProjDaily',
        'order_proj_stats'
    );
}

function orderNodeParams() {
    return array(
        'Order Node Daily Stats',
        'order_node_daily_stats',
        'orderNodeDaily',
        'order_node_stats'
    );
}

function integrateNodeParams() {
    return array(
        'Integrate Node Daily Stats',
        'integrate_node_daily_stats',
        'integrateNodeDaily',
        'integrate_node_stats'
    );
}

function deleteNodeParams() {
    return array(
        'Delete Node Daily Stats',
        'delete_node_daily_stats',
        'deleteNodeDaily',
        'delete_node_stats'
    );
}

function deleteProjParams() {
    return array(
        'Delete Project Daily Stats',
        'delete_proj_daily_stats',
        'deleteProjDaily',
        'delete_proj_stats'
    );
}

function ztIntParams() {
    $att = array(
            'zt_att',
            'Zero Touch Attempted (Bind Data)',
            'ApZeroTouchStats'
    );

    $succ = array(
            'zt_succ',
            'Zero Touch Successful (Full Response)',
            'relreq'
    );

    return array( $att, $succ );
}

function ztIntegrations() {
    $params = ztIntParams();

    foreach ( $params as $param ) {
        drawHeader( $param[1], 2, $param[2] );
        $tbl = new ModelledTable( 'TOR/cm/apserv/' . $param[0], $param[2] );
        echo $tbl->getTable();
        echo addLineBreak();
    }
}

function nodeProvToolTable() {
    drawHeader( 'Node Provisioning Tool', 2, 'nodeProvTool' );
    $tbl = new ModelledTable( 'TOR/cm/apserv/node_prov_tool', 'nodeProvTool' );
    echo $tbl->getTable();
    echo addLineBreak();
}

function subFlow( $param ) {
    global $statsDB, $site;

    $instances = getInstances("enm_apserv_metrics", 'time', "AND enm_apserv_metrics.view LIKE 'METRIC'");

    foreach ( $instances as $inst ) {
        drawHeader( $param[0], 2, $param[2] );
        $tbl = new ModelledTable( 'TOR/cm/apserv/' . $param[1], $param[2] );
        echo $tbl->getTable();
        echo addLineBreak();
        $serverid = getServerId($statsDB, $site, $inst );
        $params = array( 'serverid' => $serverid);
        $modelledGraph = new ModelledGraph('TOR/cm/apserv/' . $param[3]);
        plotGraphs( array( $modelledGraph->getImage($params) ) );
    }
}

function drawLinks( $hasHwReplace ) {
    $links = array();

    $links[] = makeAnchorLink('orderProjDaily', 'Order Project Stats');
    $links[] = makeAnchorLink('orderNodeDaily', 'Order Node Stats');
    $links[] = makeAnchorLink('integrateNodeDaily', 'Integrate Node Daily Stats');
    $links[] = makeAnchorLink('deleteNodeDaily', 'Delete Node Daily Stats');
    $links[] = makeAnchorLink('deleteProjDaily', 'Delete Project Daily Stats');
    if ( $hasHwReplace ) {
        $links[] = makeAnchorLink('ApHardwareReplaceExecTimesHelp_anchor', 'Hardware Replace Execution Time');
    }
    $links[] = makeAnchorLink('ApZeroTouchStats_anchor', 'Zero Touch Stats');

    echo makeHTMLList( $links );
}

function mainFlow() {
    global $statsDB;

    $hasHwReplace = $statsDB->hasData('enm_ap_hardware_replace_stats');

    drawHeader('Auto Provisioning', 1, '');

    drawLinks( $hasHwReplace );
    nodeProvToolTable();

    $params = orderProjParams();
    subFlow( $params );
    $orderProjectExecTimes = new OrderProjectExecTimes();
    drawHeaderWithHelp(
        "Order Project Execution Times (Millisec)",
        2,
        "ApOrderProjectExecTimesHelp",
        "DDP_Bubble_326_APSERV_ORDER_PROJECT_EXEC_TIMES"
    );
    echo $orderProjectExecTimes->getClientSortableTableStr();
    echo addLineBreak(2);

    $params = orderNodeParams();
    subFlow( $params );
    $orderNodeExecTimes = new OrderNodeExecTimes();
    drawHeaderWithHelp(
        "Order Node Execution Times (Millisec)",
        2,
        "ApOrderNodeExecTimesHelp",
        "DDP_Bubble_327_APSERV_ORDER_NODE_EXEC_TIMES"
    );
    echo $orderNodeExecTimes->getClientSortableTableStr();
    echo addLineBreak(2);

    $params = integrateNodeParams();
    subFlow( $params );
    $integrateNodeExecTimes = new IntegrateNodeExecTimes();
    drawHeaderWithHelp(
        "Integrate Node Execution Times (Millisec)",
        2,
        "ApIntegrateNodeExecTimesHelp",
        "DDP_Bubble_331_APSERV_INTEGRATE_NODE_EXEC_TIMES"
    );
    echo $integrateNodeExecTimes->getClientSortableTableStr();
    echo addLineBreak(2);

    $params = deleteNodeParams();
    subFlow( $params );
    $deleteNodeExecTimes = new DeleteNodeExecTimes();
    drawHeaderWithHelp(
        "Delete Node Execution Times (Millisec)",
        2,
        "ApDeleteNodeExecTimesHelp",
        "DDP_Bubble_332_APSERV_DELETE_NODE_EXEC_TIMES"
    );
    echo $deleteNodeExecTimes->getClientSortableTableStr();
    echo addLineBreak(2);

    $params = deleteProjParams();
    subFlow( $params );
    $deleteProjectExecTimes = new DeleteProjectExecTimes();
    drawHeaderWithHelp(
        "Delete Project Execution Times (Millisec)",
        2,
        "ApDeleteProjectExecTimesHelp",
        "DDP_Bubble_333_APSERV_DELETE_PROJECT_EXEC_TIMES"
    );
    echo $deleteProjectExecTimes->getClientSortableTableStr();
    echo addLineBreak(2);

    if ( $hasHwReplace ) {
        $hardwareRepalceExecTimes = new HardwareReplaceExecTimes();
        drawHeaderWithHelp(
            "Hardware Replace Execution Times (Millisec)",
            2,
            "ApHardwareReplaceExecTimesHelp",
            "DDP_Bubble_412_APSERV_HARDWARE_REPLACE_EXEC_TIMES"
        );
        echo $hardwareRepalceExecTimes->getClientSortableTableStr();
        echo addLineBreak();
    }
    ztIntegrations();
}

mainFlow();

include PHP_ROOT . "/common/finalise.php";

