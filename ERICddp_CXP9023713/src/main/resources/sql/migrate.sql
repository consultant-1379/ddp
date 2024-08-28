
-- END DDP-2.0.4295
-- END DDP-2.0.4296
-- END DDP-2.0.4297
-- END DDP-2.0.4298
-- END DDP-2.0.4299
-- END DDP-2.0.4300
-- END DDP-2.0.4301

ALTER TABLE enm_plms_instr
  ADD COLUMN totalNumberOfCreateNotifications MEDIUMINT UNSIGNED,
  ADD COLUMN totalNumberOfDeleteNotifications MEDIUMINT UNSIGNED,
  ADD COLUMN totalNumberOfUpdateNotifications MEDIUMINT UNSIGNED,
  ADD COLUMN totalNumberOfAlarmNotifications MEDIUMINT UNSIGNED,
  ADD COLUMN totalNumberOfLinkAlarms MEDIUMINT UNSIGNED;

-- END DDP-2.0.4302
-- END DDP-2.0.4303
-- END DDP-2.0.4304
-- END DDP-2.0.4305

ALTER TABLE enm_neo4j_mocounts
PARTITION BY RANGE ( TO_DAYS(date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4306
-- END DDP-2.0.4307

ALTER TABLE eniq_stats_adaptor_totals
 ADD COLUMN trigger_count TINYINT UNSIGNED;

-- END DDP-2.0.4308
-- END DDP-2.0.4309
-- END DDP-2.0.4310

CREATE TABLE enm_plms_statistics (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  totalNumberOfDiscoveredLinks SMALLINT UNSIGNED NOT NULL,
  totalNumberOfNotDiscoveredLinks SMALLINT UNSIGNED NOT NULL,
  totalNumberOfDefinedLinks SMALLINT UNSIGNED NOT NULL,
  totalNumberOfUndefinedLinks SMALLINT UNSIGNED NOT NULL,
  totalNumberOfPendingLinks SMALLINT UNSIGNED NOT NULL,
  totalNumberOfPhysicalLinks SMALLINT UNSIGNED NOT NULL,
  totalNumberOfLogicalLinks SMALLINT UNSIGNED NOT NULL,
  totalNumberOfUnKnownLinks SMALLINT UNSIGNED NOT NULL,
  totalNumberOfLinks SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4311

CREATE TABLE eniq_dbcc_table_info (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  tableNameId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_aggregated_counter_table_name_id_mapping(id)",
  INDEX siteidDateIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_dbcc_status (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  lastDBCheckTime DATETIME NOT NULL,
  dbAllocationStatus ENUM('PASS','FAIL') COLLATE latin1_general_cs,
  verifyTablesStatus ENUM('PASS','FAIL') COLLATE latin1_general_cs,
  iqmsgCheckStatus ENUM('PASS','FAIL') COLLATE latin1_general_cs,
  INDEX siteidDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4312

TRUNCATE TABLE enm_pmic_rop_ulsa;

ALTER TABLE enm_pmic_rop_ulsa
  ADD COLUMN neid INT UNSIGNED NOT NULL REFERENCES enm_ne(id) AFTER fcs;

-- END DDP-2.0.4313
-- END DDP-2.0.4314

ALTER TABLE mo
PARTITION BY RANGE ( TO_DAYS(date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


-- END DDP-2.0.4315
-- END DDP-2.0.4316
-- END DDP-2.0.4317
-- END DDP-2.0.4318
-- END DDP-2.0.4319
-- END DDP-2.0.4320
-- END DDP-2.0.4321

ALTER TABLE enm_eba_msstr
  ADD COLUMN activeConnections3 SMALLINT UNSIGNED,
  ADD COLUMN createdConnections3 SMALLINT UNSIGNED;

-- END DDP-2.0.4322
-- END DDP-2.0.4323

CREATE TABLE enm_open_am_authorization (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  amAuthorizationPolicySetEvaluateCount SMALLINT UNSIGNED NOT NULL,
  amAuthorizationPolicySetEvaluateActionCount SMALLINT UNSIGNED NOT NULL,
  amAuthorizationPolicySetEvaluateSecondsTotal SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sum_netsim_requests (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 NETCONF MEDIUMINT UNSIGNED NOT NULL,
 CPP MEDIUMINT UNSIGNED NOT NULL,
 SNMP MEDIUMINT UNSIGNED NOT NULL,
 SIMCMD MEDIUMINT UNSIGNED NOT NULL,
 ecim_get MEDIUMINT UNSIGNED NOT NULL,
 ecim_edit MEDIUMINT UNSIGNED NOT NULL,
 ecim_MOaction MEDIUMINT UNSIGNED NOT NULL,
 cpp_createMO MEDIUMINT UNSIGNED NOT NULL,
 cpp_deleteMO MEDIUMINT UNSIGNED NOT NULL,
 cpp_setAttr MEDIUMINT UNSIGNED NOT NULL,
 cpp_getMIB MEDIUMINT UNSIGNED NOT NULL,
 cpp_nextMOinfo MEDIUMINT UNSIGNED NOT NULL,
 cpp_get MEDIUMINT UNSIGNED NOT NULL,
 cpp_MOaction MEDIUMINT UNSIGNED NOT NULL,
 snmp_get MEDIUMINT UNSIGNED NOT NULL,
 snmp_bulk_get MEDIUMINT UNSIGNED,
 snmp_get_next MEDIUMINT UNSIGNED,
 snmp_set MEDIUMINT UNSIGNED NOT NULL,
 AVCbursts MEDIUMINT UNSIGNED NOT NULL,
 MCDbursts MEDIUMINT UNSIGNED NOT NULL,
 AlarmBursts MEDIUMINT UNSIGNED NOT NULL,
 SFTP MEDIUMINT UNSIGNED NOT NULL,
 sftp_FileOpen MEDIUMINT UNSIGNED NOT NULL,
 sftp_get_cwd MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sum_netsim_response (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 NETCONF MEDIUMINT UNSIGNED NOT NULL,
 CORBA MEDIUMINT UNSIGNED NOT NULL,
 SNMP MEDIUMINT UNSIGNED NOT NULL,
 SSH MEDIUMINT UNSIGNED NOT NULL,
 SFTP MEDIUMINT UNSIGNED NOT NULL,
 ecim_avc MEDIUMINT UNSIGNED NOT NULL,
 ecim_MOcreated MEDIUMINT UNSIGNED NOT NULL,
 ecim_MOdeleted MEDIUMINT UNSIGNED NOT NULL,
 ecim_reply MEDIUMINT UNSIGNED NOT NULL,
 cpp_avc MEDIUMINT UNSIGNED NOT NULL,
 cpp_MOcreated MEDIUMINT UNSIGNED NOT NULL,
 cpp_MOdeleted MEDIUMINT UNSIGNED NOT NULL,
 cpp_reply MEDIUMINT UNSIGNED NOT NULL,
 sftp_FileClose MEDIUMINT UNSIGNED NOT NULL,
 snmp_response MEDIUMINT UNSIGNED,
 snmp_traps MEDIUMINT UNSIGNED,
 INDEX siteDateOdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4324

ALTER TABLE enm_flexible_controller
  ADD COLUMN numberOfRequestsForUpdateEndpoint SMALLINT UNSIGNED,
  ADD COLUMN numberOfFlexibleCountersInUpdateAddedToQueue SMALLINT UNSIGNED,
  ADD COLUMN numberOfFlexibleCountersInUpdateRemovedFromQueue SMALLINT UNSIGNED;

-- END DDP-2.0.4325

ALTER TABLE cs_notifications
PARTITION BY RANGE ( TO_DAYS(date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_cm_cell_management
PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4326

ALTER TABLE netsim_requests
 ADD INDEX serverTimeIdx(serverid,time);

ALTER TABLE netsim_response
 ADD INDEX serverTimeIdx(serverid,time);
-- END DDP-2.0.4327
-- END DDP-2.0.4328

CREATE TABLE enm_nsj_statistics(
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  jobStartDuration SMALLINT UNSIGNED NOT NULL,
  jobInsertDuration SMALLINT UNSIGNED NOT NULL,
  jobCommandId ENUM( 'CPP_GET_SL', 'CPP_SET_SL', 'CPP_INSTALL_LAAD', 'CREATE_CREDENTIALS', 'UPDATE_CREDENTIALS',
   'GET_CREDENTIALS', 'ADD_TARGET_GROUPS', 'CPP_IPSEC_STATUS', 'CPP_IPSEC', 'CREATE_SSH_KEY', 'UPDATE_SSH_KEY',
   'IMPORT_NODE_SSH_PRIVATE_KEY', 'TEST_COMMAND', 'CERTIFICATE_ISSUE', 'SNMP_AUTHPRIV', 'SNMP_AUTHNOPRIV',
   'TRUST_DISTRIBUTE', 'SET_ENROLLMENT', 'GET_CERT_ENROLL_STATE', 'GET_TRUST_CERT_INSTALL_STATE', 'CERTIFICATE_REISSUE',
   'LDAP_CONFIGURATION', 'LDAP_RECONFIGURATION', 'TRUST_REMOVE', 'CRL_CHECK_ENABLE', 'GET_JOB', 'CRL_CHECK_DISABLE',
   'CRL_CHECK_GET_STATUS', 'ON_DEMAND_CRL_DOWNLOAD', 'SET_CIPHERS', 'GET_CIPHERS', 'ENROLLMENT_INFO_FILE',
   'RTSEL_ACTIVATE', 'RTSEL_DEACTIVATE', 'RTSEL_GET', 'RTSEL_DELETE', 'HTTPS_ACTIVATE', 'HTTPS_DEACTIVATE',
   'HTTPS_GET_STATUS', 'GET_SNMP', 'GET_SNMP_PLAIN_TEXT', 'FTPES_ACTIVATE', 'FTPES_DEACTIVATE', 'FTPES_GET_STATUS',
   'GET_NODE_SPECIFIC_PASSWORD', 'CAPABILITY_GET', 'LAAD_FILES_DISTRIBUTE', 'NTP_LIST', 'NTP_REMOVE', 'NTP_CONFIGURE',
   'SSO_ENABLE', 'SSO_DISABLE', 'SSO_GET' ) NOT NULL COLLATE latin1_general_cs,
  jobNumWorkflows SMALLINT UNSIGNED NOT NULL,
  jobNumSuccessWorkflows SMALLINT UNSIGNED NOT NULL,
  jobNumErrorWorkflows SMALLINT UNSIGNED NOT NULL,
  jobMinSuccessWorkflowsDuration SMALLINT UNSIGNED NOT NULL,
  jobMaxSuccessWorkflowsDuration SMALLINT UNSIGNED NOT NULL,
  jobAvgSuccessWorkflowsDuration SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4329

CREATE TABLE enm_notification (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  eventtype ENUM( 'AVC', 'CREATE', 'UPDATE', 'DELETE', 'SDN' ) NOT NULL COLLATE latin1_general_cs,
  moid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
  attribid SMALLINT UNSIGNED COMMENT "REFERENCES enm_mscm_attrib_names(id)",
  count INT UNSIGNED NOT NULL,
  servicegroup ENUM('comecimmscm','mscmapg') NOT NULL DEFAULT 'comecimmscm' COLLATE latin1_general_cs,
  INDEX siteTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4330
-- END DDP-2.0.4331
-- END DDP-2.0.4332
-- END DDP-2.0.4333

DROP TABLE enm_notification;

-- END DDP-2.0.4334
-- END DDP-2.0.4335
-- END DDP-2.0.4336
-- END DDP-2.0.4337
-- END DDP-2.0.4338
-- END DDP-2.0.4339
-- END DDP-2.0.4340
-- END DDP-2.0.4341
-- END DDP-2.0.4342
-- END DDP-2.0.4343
-- END DDP-2.0.4344
-- END DDP-2.0.4345

CREATE TABLE enm_pm_file_access_nbi (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  apacheAccessesTotal SMALLINT UNSIGNED NOT NULL,
  apacheSentKilobytesTotal MEDIUMINT UNSIGNED NOT NULL,
  apacheCpuload SMALLINT UNSIGNED NOT NULL,
  apacheUptimeSecondsTotal SMALLINT UNSIGNED NOT NULL,
  apacheDurationMsTotal MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4346
-- END DDP-2.0.4347

-- END DDP-2.0.4348
-- END DDP-2.0.4349
-- END DDP-2.0.4350
-- END DDP-2.0.4351
-- END DDP-2.0.4352
-- END DDP-2.0.4353
-- END DDP-2.0.4354
-- END DDP-2.0.4355
-- END DDP-2.0.4356

-- END DDP-2.0.4357

ALTER TABLE enm_lvs_stats
PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4358

ALTER TABLE enm_pm_file_access_nbi
  ADD COLUMN apacheWorkersStateValueIdle SMALLINT UNSIGNED,
  ADD COLUMN apacheWorkersStateValueBusy SMALLINT UNSIGNED;
-- END DDP-2.0.4359
-- END DDP-2.0.4360
-- END DDP-2.0.4361

ALTER TABLE enm_spsserv_caentity_instr
  ADD COLUMN deleteExecutionTimeTotalMillis SMALLINT UNSIGNED,
  ADD COLUMN deleteMethodFailures MEDIUMINT UNSIGNED,
  ADD COLUMN deleteMethodInvocations MEDIUMINT UNSIGNED;

ALTER TABLE enm_spsserv_endentity_instr
  ADD COLUMN deleteExecutionTimeTotalMillis SMALLINT UNSIGNED,
  ADD COLUMN deleteMethodFailures MEDIUMINT UNSIGNED,
  ADD COLUMN deleteMethodInvocations MEDIUMINT UNSIGNED;

-- END DDP-2.0.4362

ALTER TABLE nma_notifrec
PARTITION BY RANGE ( TO_DAYS(date) )
(
      PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_mscm_notifrec
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4363
-- END DDP-2.0.4364
-- END DDP-2.0.4365
-- END DDP-2.0.4366
-- END DDP-2.0.4367
-- END DDP-2.0.4368
-- END DDP-2.0.4369

ALTER TABLE enm_lvs_stats
 ADD INDEX lvsidIdx(lvsid);

DELETE enm_lvs_stats, enm_lvs
FROM enm_lvs_stats
JOIN enm_lvs ON enm_lvs_stats.lvsid = enm_lvs.id
WHERE
 enm_lvs.rhost LIKE '%filetransferservice%' AND enm_lvs.rport > 10000;

DELETE FROM enm_lvs
WHERE
 enm_lvs.rhost LIKE '%filetransferservice%' AND enm_lvs.rport > 10000;

ALTER TABLE enm_lvs_stats
 DROP INDEX lvsidIdx;
-- END DDP-2.0.4370

ALTER TABLE enm_lvs_stats
 ADD INDEX lvsidIdx(lvsid);

DELETE enm_lvs_stats, enm_lvs
FROM enm_lvs_stats
JOIN enm_lvs ON enm_lvs_stats.lvsid = enm_lvs.id
WHERE
 enm_lvs.rhost LIKE '%eric-oss-ingress-controller-nx%';

DELETE FROM enm_lvs
WHERE
 enm_lvs.rhost LIKE '%eric-oss-ingress-controller-nx%';

ALTER TABLE enm_lvs_stats
 DROP INDEX lvsidIdx;

-- END DDP-2.0.4371
-- END DDP-2.0.4372

CREATE TABLE enm_nginx_path (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

Alter table nginx_requests
  ADD COLUMN pathid SMALLINT UNSIGNED COMMENT "REFERENCES enm_nginx_path(id)",
  ADD COLUMN method ENUM ( 'GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE' ) COLLATE latin1_general_cs;

-- END DDP-2.0.4373
-- END DDP-2.0.4374
-- END DDP-2.0.4375

ALTER TABLE son_mo
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE son_mo_additions
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE son_cio_changes
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE son_qOffset_changes
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_iptrnsprt_notifrec
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4376
-- END DDP-2.0.4377
-- END DDP-2.0.4378
-- END DDP-2.0.4379
-- END DDP-2.0.4380
-- END DDP-2.0.4381
-- END DDP-2.0.4382
-- END DDP-2.0.4383

ALTER TABLE enm_nsj_statistics
 modify column jobMinSuccessWorkflowsDuration SMALLINT UNSIGNED,
 modify column jobMaxSuccessWorkflowsDuration SMALLINT UNSIGNED,
 modify column jobAvgSuccessWorkflowsDuration SMALLINT UNSIGNED;
-- END DDP-2.0.4384

ALTER TABLE nead_notifrec
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_mscmce_notifrec
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4385

CREATE TABLE enm_shm_nbi_rest_job (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  useCaseName ENUM('MainJobStatus', 'NELevelJobStatus', 'ActivityStatus', 'ContinueJob', 'CancelJob',
  'DeleteJob', 'ViewJobs', 'ExportJobLogs') NOT NULL COLLATE latin1_general_cs,
  numOfNodes SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shm_nbi_rest_backup (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  useCaseName ENUM('CreateBackup', 'RestoreBackup', 'BackupHouseKeeping',
  'ManageNodeBackups', 'GetComponentsForAXENodes', 'GetBackupDomainType',
  'BackupInventory', 'DeleteBackup') NOT NULL COLLATE latin1_general_cs,
  numOfNodes SMALLINT UNSIGNED NOT NULL,
  numOfCPPNodes SMALLINT UNSIGNED NOT NULL,
  numOfECIMNodes SMALLINT UNSIGNED NOT NULL,
  numOfMiniLinkNodes SMALLINT UNSIGNED NOT NULL,
  numOfAXENodes SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4386
-- END DDP-2.0.4387
-- END DDP-2.0.4388
-- END DDP-2.0.4389
-- END DDP-2.0.4390
-- END DDP-2.0.4391
-- END DDP-2.0.4392

ALTER TABLE k8s_pod_cadvisor
 ADD INDEX serverDateIdx(serverid,time);
-- END DDP-2.0.4393

ALTER TABLE k8s_pod_cadvisor
 REORGANIZE PARTITION QMAXVALUE INTO ( PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE );
-- END DDP-2.0.4394
-- END DDP-2.0.4395

ALTER TABLE enm_shmcoreserv_details_logs
  ADD COLUMN netypes VARCHAR(64) COLLATE latin1_general_cs AFTER netypeid;

UPDATE enm_shmcoreserv_details_logs
  INNER JOIN ne_types ON enm_shmcoreserv_details_logs.netypeid = ne_types.id
  SET enm_shmcoreserv_details_logs.netypes = ne_types.name;

ALTER TABLE enm_shmcoreserv_details_logs
  DROP COLUMN netypeid;

-- END DDP-2.0.4396
-- END DDP-2.0.4397
-- END DDP-2.0.4398
-- END DDP-2.0.4399
-- END DDP-2.0.4400
-- END DDP-2.0.4401
-- END DDP-2.0.4402
-- END DDP-2.0.4403
-- END DDP-2.0.4404

CREATE TABLE eniq_patch_update_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 patch VARCHAR(40) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(patch),
 PRIMARY KEY(id)
);

CREATE TABLE eniq_om_patch_media_status_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 status VARCHAR(60) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(status),
 PRIMARY KEY(id)
);

CREATE TABLE eniq_om_patch_release_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 releaseName VARCHAR(20) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(releaseName),
 PRIMARY KEY(id)
);

CREATE TABLE eniq_om_media_table_info (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 statusId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_om_patch_media_status_id_mapping(id)",
 releaseId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_om_patch_release_id_mapping(id)",
 installTime DATETIME NOT NULL,
 INDEX siteidDateIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_patch_media_table_info (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 statusId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_om_patch_media_status_id_mapping(id)",
 patchId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_patch_update_id_mapping(id)",
 releaseId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_om_patch_release_id_mapping(id)",
 installTime DATETIME NOT NULL,
 INDEX siteidDateIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4405
-- END DDP-2.0.4406
-- END DDP-2.0.4407
-- END DDP-2.0.4408
-- END DDP-2.0.4409
-- END DDP-2.0.4410
-- END DDP-2.0.4411
-- END DDP-2.0.4412
-- END DDP-2.0.4413
-- END DDP-2.0.4414
-- END DDP-2.0.4415

CREATE TABLE enm_nsm_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  totalCmNodeHeartbeatSupervisionEventsReceived SMALLINT UNSIGNED NOT NULL,
  totalFailedSyncsCountEventsReceived SMALLINT UNSIGNED NOT NULL,
  totalCmNodeSyncMonitorFeatureEventsReceived SMALLINT UNSIGNED NOT NULL,
  totalNoOfCmSyncFailuresBeforeAlarmEventsReceived SMALLINT UNSIGNED NOT NULL,
  totalCmUnsyncedAlarmsRaised SMALLINT UNSIGNED NOT NULL,
  totalCmUnsyncedAlarmsCleared SMALLINT UNSIGNED NOT NULL,
  INDEX siteIdIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4416

CREATE TABLE eniq_netan_pme_fetch_name_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 fetchName VARCHAR(60) NOT NULL,
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pme_query_name_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 queryName VARCHAR(60) NOT NULL,
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pme_measure_name_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 measureName VARCHAR(150) NOT NULL,
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pme_query_category_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 queryCategory VARCHAR(60) NOT NULL,
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pme_data_source_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 dataSource VARCHAR(30) NOT NULL,
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pme_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 fetchId SMALLINT UNSIGNED COMMENT "REFERENCES eniq_netan_pme_fetch_name_id_mapping_details(id)",
 queryId SMALLINT UNSIGNED COMMENT "REFERENCES eniq_netan_pme_query_name_id_mapping_details(id)",
 measureId SMALLINT UNSIGNED COMMENT "REFERENCES eniq_netan_pme_measure_name_id_mapping_details(id)",
 tableId SMALLINT UNSIGNED COMMENT "REFERENCES eniq_aggregated_counter_table_name_id_mapping(id)",
 dataSourceId SMALLINT UNSIGNED COMMENT "REFERENCES eniq_netan_pme_data_source_id_mapping_details(id)",
 queryCategoryId SMALLINT UNSIGNED COMMENT "REFERENCES eniq_netan_pme_query_category_id_mapping_details(id)",
 measureType SET('Counter', 'PDF Counter', 'Ericsson KPI', 'RI', 'DynCounter',
 'Flex Counter', 'Flex+PDF Counter') COLLATE latin1_general_cs,
 timeAggregationLevel ENUM('Busy Hour', 'Day', 'Hour', 'Month', 'ROP', 'Week') COLLATE latin1_general_cs,
 objectAggregationLevel ENUM('No Aggregation', 'All Selected', 'Node', 'Collection',
 'Network', 'Cell', 'SubNetwork') COLLATE latin1_general_cs,
 queryExecutionTime MEDIUMINT UNSIGNED NOT NULL,
 rowCount MEDIUMINT UNSIGNED NOT NULL,
 rowCountMultiFact SMALLINT UNSIGNED NOT NULL,
 startDateTime DATETIME,
 endDateTime DATETIME,
 preFetchFilterDayOfWeek SET('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday',
 'Friday', 'Saturday') COLLATE latin1_general_cs,
 preFetchFilterMeasureType SET('Counter', 'PDF Counter', 'Ericsson KPI', 'RI',
 'DynCounter', 'Flex Counter', 'Flex+PDF Counter') COLLATE latin1_general_cs,
 reportMode ENUM('View', 'Create', 'Edit') COLLATE latin1_general_cs,
 reportID SMALLINT UNSIGNED,
 collectionType ENUM('NETWORK', 'SUBNETWORK', 'NODE', 'COLLECTION') COLLATE latin1_general_cs,
 nodeCount MEDIUMINT UNSIGNED,
 preFetchFilterHourOfDay SET('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15',
 '16', '17', '18', '19', '20', '21', '22', '23') COLLATE latin1_general_cs,
 INDEX siteidTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_netan_pma_process_name_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 processName VARCHAR(60) NOT NULL,
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pma_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 alarmId SMALLINT UNSIGNED NOT NULL,
 processId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_netan_pma_process_name_id_mapping_details(id)",
 subProcessType ENUM('Import meta data in analysis', 'Check if alarm was triggered before', 'Fetching PM data for alarm',
 'Find the rows to send to ENM', 'Send the rows to ENM and Eniq') NOT NULL COLLATE latin1_general_cs,
 subProcessCompletionStatus ENUM('Failed', 'Successful', 'Halted') NOT NULL COLLATE latin1_general_cs,
 tableId SMALLINT UNSIGNED COMMENT "REFERENCES eniq_aggregated_counter_table_name_id_mapping(id)",
 rowCountFromENIQ MEDIUMINT UNSIGNED,
 mfENIQ SMALLINT UNSIGNED,
 rowCountToENM MEDIUMINT UNSIGNED,
 mfENM SMALLINT UNSIGNED,
 queryExecutionTime MEDIUMINT UNSIGNED,
 collectionType ENUM('Single Node', 'Subnetwork', 'Collection') COLLATE latin1_general_cs,
 nodeCount MEDIUMINT UNSIGNED,
 nodeTypeId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
 INDEX siteidTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_netan_pmdb_collections_summary_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 collectionID SMALLINT UNSIGNED NOT NULL,
 collectionType ENUM('System Defined Collection', 'Static Collection',
 'Dynamic Collection') NOT NULL COLLATE latin1_general_cs,
 nodeTypeId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
 createdOn DATETIME,
 lastModifiedOn DATETIME,
 nodeCount MEDIUMINT UNSIGNED,
 INDEX siteidTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_netan_pmdb_alarm_summary_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 alarmID SMALLINT UNSIGNED NOT NULL,
 alarmType ENUM('cdt', 'dynamic', 'cd', 'pcd', 'pcd+cd', 'threshold', 'trend') NOT NULL COLLATE latin1_general_cs,
 measureId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_netan_pme_measure_name_id_mapping_details(id)",
 severity ENUM('MINOR', 'MAJOR', 'CRITICAL', 'INDETERMINATE', 'WARNING') NOT NULL COLLATE latin1_general_cs,
 alarmState ENUM('Inactive', 'Active', 'Deleted') NOT NULL COLLATE latin1_general_cs,
 probableCause ENUM('Bandwidth Reduction', 'Congestion', 'Excessive Error Rate', 'Excessive Retransmission Rate',
 'Performance Degraded', 'Queue Size Exceeded', 'Reduced alarm reporting', 'Reduced event reporting',
 'Reduced logging capability', 'Resource at or Nearing Capacity', 'Response Time Excessive',
 'Re-transmission Rate Excessive', 'System resources overload', 'Threshold Crossed') NOT NULL COLLATE latin1_general_cs,
 schedule SMALLINT UNSIGNED NOT NULL,
 aggregation ENUM('None', '1 Hour', '1 Day') NOT NULL COLLATE latin1_general_cs,
 lookBackVal SMALLINT UNSIGNED,
 lookBackUnit ENUM('ROP', 'DAY', 'HOUR') COLLATE latin1_general_cs,
 dataRangeVal SMALLINT UNSIGNED,
 dataRangeUnit ENUM('ROP', 'DAY', 'HOUR') COLLATE latin1_general_cs,
 nodeTypeId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
 systemArea ENUM('Radio', 'Core', 'Transport') NOT NULL COLLATE latin1_general_cs,
 measureType ENUM('Counter', 'KPI', 'Custom KPI', 'RI') NOT NULL COLLATE latin1_general_cs,
 INDEX siteidTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_netan_pmdb_report_summary_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 reportID SMALLINT UNSIGNED NOT NULL,
 reportAccess ENUM('Private', 'Public') NOT NULL COLLATE latin1_general_cs,
 createdOn DATETIME,
 lastModifiedOn DATETIME,
 INDEX siteidTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_netan_custom_kpi_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 numberOfCustomKPI SMALLINT UNSIGNED NOT NULL,
 INDEX siteidTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4417
-- END DDP-2.0.4418

ALTER TABLE enm_plms_statistics
  MODIFY COLUMN totalNumberOfDiscoveredLinks MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN totalNumberOfNotDiscoveredLinks MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN totalNumberOfDefinedLinks MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN totalNumberOfUndefinedLinks MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN totalNumberOfPendingLinks MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN totalNumberOfPhysicalLinks MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN totalNumberOfLogicalLinks MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN totalNumberOfUnKnownLinks MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN totalNumberOfLinks MEDIUMINT UNSIGNED NOT NULL;
-- END DDP-2.0.4419
-- END DDP-2.0.4420
-- END DDP-2.0.4421
-- END DDP-2.0.4422
-- END DDP-2.0.4423

CREATE TABLE enm_jgroup_partitions (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 clusterid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_jgroup_clusternames(id)",
 partitionCount SMALLINT UNSIGNED NOT NULL
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4424

DELETE FROM enm_apache_uri WHERE uri LIKE '/parametermanagement/v1/modelInfo%';

-- END DDP-2.0.4425

DROP TABLE enm_jgroup_partitions;
CREATE TABLE enm_jgroup_view_mismatch (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 clusterid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_jgroup_clusternames(id)",
 viewCount SMALLINT UNSIGNED NOT NULL
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4426

ALTER TABLE eniq_netan_pme_details
  MODIFY measureType SET('Counter','PDF Counter','Ericsson KPI','RI','DynCounter','Flex Counter','Flex+PDF Counter','Custom KPI') COLLATE latin1_general_cs;

ALTER TABLE eniq_netan_pme_details
  MODIFY preFetchFilterMeasureType SET('Counter','PDF Counter','Ericsson KPI','RI','DynCounter','Flex Counter','Flex+PDF Counter','Custom KPI') COLLATE latin1_general_cs;

-- END DDP-2.0.4427
-- END DDP-2.0.4428
-- END DDP-2.0.4429

-- END DDP-2.0.4430

ALTER TABLE pms_filetransfer_rop
PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4431

CREATE TABLE enm_jboss_shutdown (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 result ENUM( 'STOP', 'KILLED' ) NOT NULL COLLATE latin1_general_cs,
 duration SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4432
-- END DDP-2.0.4433
-- END DDP-2.0.4434
-- END DDP-2.0.4435
-- END DDP-2.0.4436
-- END DDP-2.0.4437
-- END DDP-2.0.4438

-- END DDP-2.0.4439

TRUNCATE TABLE pa_activation_content;
TRUNCATE TABLE pa_activation_pca_actions;
TRUNCATE TABLE pa_activation;
TRUNCATE TABLE pa_import_details;
TRUNCATE TABLE pa_import;
TRUNCATE TABLE arne_import_content;
TRUNCATE TABLE arne_import;

-- END DDP-2.0.4440

DELETE FROM enm_apache_uri where uri LIKE "/enm-nbi/cm/v1/data%";
DELETE FROM enm_apache_uri where uri REGEXP '^/+elex';
DELETE FROM enm_apache_uri where uri LIKE "/server-scripting/services/%";

-- END DDP-2.0.4441

ALTER TABLE enm_ned_swsync
PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_pmic_rop_fls
PARTITION BY RANGE ( TO_DAYS(fcs) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_pmic_rop_ulsa
PARTITION BY RANGE ( TO_DAYS(fcs) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4442

ALTER TABLE enm_pm_file_access_nbi
  DROP COLUMN apacheUptimeSecondsTotal,
  DROP COLUMN apacheDurationMsTotal;
-- END DDP-2.0.4443

DROP TABLE eniq_netan_pme_fetch_name_id_mapping_details;
DROP TABLE eniq_netan_pme_query_name_id_mapping_details;
DROP TABLE eniq_netan_pme_measure_name_id_mapping_details;
DROP TABLE eniq_netan_pme_query_category_id_mapping_details;
DROP TABLE eniq_netan_pme_data_source_id_mapping_details;
DROP TABLE eniq_netan_pma_process_name_id_mapping_details;

CREATE TABLE eniq_netan_pme_fetch_name_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 fetchName VARCHAR(60) NOT NULL,
 UNIQUE INDEX nameIdx(fetchName),
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pme_query_name_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 queryName VARCHAR(60) NOT NULL,
 UNIQUE INDEX nameIdx(queryName),
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pme_measure_name_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 measureName VARCHAR(150) NOT NULL,
 UNIQUE INDEX nameIdx(measureName),
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pme_query_category_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 queryCategory VARCHAR(60) NOT NULL,
 UNIQUE INDEX nameIdx(queryCategory),
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pme_data_source_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 dataSource VARCHAR(30) NOT NULL,
 UNIQUE INDEX nameIdx(dataSource),
 PRIMARY KEY (id)
);

CREATE TABLE eniq_netan_pma_process_name_id_mapping_details (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 processName VARCHAR(60) NOT NULL,
 UNIQUE INDEX nameIdx(processName),
 PRIMARY KEY (id)
);

-- END DDP-2.0.4444
-- END DDP-2.0.4445
-- END DDP-2.0.4446
-- END DDP-2.0.4447
-- END DDP-2.0.4448
-- END DDP-2.0.4449
-- END DDP-2.0.4450

TRUNCATE TABLE enm_ncm_ignored_interfaces;
TRUNCATE TABLE ncm_interfaces;
DELETE FROM mo_names WHERE name LIKE 'Interface=%';

-- END DDP-2.0.4451

ALTER TABLE ne_mim
 ADD INDEX siteDateIdx(siteid,date);

ALTER TABLE ne_up
 ADD INDEX siteDateIdx(siteid,date);

ALTER TABLE ne_mim
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE ne_up
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_ned_tmi
PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_shmcoreserv_job_instrumentation_logs
PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4452

DELETE FROM mo_names WHERE name LIKE '%3gppnetwork.org$%';

-- END DDP-2.0.4453
-- END DDP-2.0.4454
-- END DDP-2.0.4455
-- END DDP-2.0.4456

-- END DDP-2.0.4457

ALTER TABLE enm_ebsl_inst_stats
  ADD COLUMN numberOfLTEcountersDropped SMALLINT UNSIGNED,
  ADD COLUMN numberOfNRcountersDropped SMALLINT UNSIGNED;

DELETE FROM ne_types WHERE name LIKE '%=%';
DELETE FROM ne_types WHERE name REGEXP '^\[0-9]+$';
DELETE FROM ne_types WHERE name LIKE '%,%';
-- END DDP-2.0.4458
-- END DDP-2.0.4459

CREATE TABLE deploy_infra (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx (name)
);
INSERT INTO deploy_infra (name) VALUES ("");

ALTER TABLE sites
 ADD COLUMN infra_id SMALLINT UNSIGNED NOT NULL DEFAULT 1 REFERENCES deploy_infra(id);
-- END DDP-2.0.4460
-- END DDP-2.0.4461
-- END DDP-2.0.4462
-- END DDP-2.0.4463
-- END DDP-2.0.4464
-- END DDP-2.0.4465
-- END DDP-2.0.4466
-- END DDP-2.0.4467

ALTER TABLE enm_jgroup_view_mismatch
 ADD INDEX sitetimeIdx(siteid,time);
-- END DDP-2.0.4468

CREATE TABLE enm_proxy_statistics (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 numTotalProxyAccountLockable MEDIUMINT UNSIGNED NOT NULL,
 numTotalProxyAccountLegacy MEDIUMINT UNSIGNED NOT NULL,
 numTotalProxyAccountEnabledLockable MEDIUMINT UNSIGNED NOT NULL,
 numTotalProxyAccountEnabledLegacy MEDIUMINT UNSIGNED NOT NULL,
 numTotalProxyAccountDisabledLockable MEDIUMINT UNSIGNED NOT NULL,
 numTotalProxyAccountDisabledLegacy MEDIUMINT UNSIGNED NOT NULL,
 numTotalProxyAccountInactiveLocable MEDIUMINT UNSIGNED NOT NULL,
 numTotalProxyAccountInactiveLegacy MEDIUMINT UNSIGNED NOT NULL,
 maxNumTotProxyAccountThreshold MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteidTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4469
ALTER TABLE enm_pmic_subs DROP COLUMN contentid;
DROP TABLE enm_pmic_sub_content;
-- END DDP-2.0.4470
-- END DDP-2.0.4471

ALTER TABLE enm_pmic_subs
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4472
-- END DDP-2.0.4473

CREATE TABLE enm_ecim_node_resync (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 numberOfNodesWaiting SMALLINT UNSIGNED NOT NULL,
 INDEX siteidTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_npam_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 enableRemoteManagementEventsReceived SMALLINT UNSIGNED NOT NULL,
 disableRemoteManagementEventsReceived SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4474

ALTER TABLE enm_mscmce_notifrec
  MODIFY COLUMN eventtype ENUM( 'AVC', 'CREATE', 'DELETE', 'SDN', 'SEQUENCE_DELTA', 'UPDATE' ) NOT NULL COLLATE latin1_general_cs;

-- END DDP-2.0.4475

CREATE TABLE enm_cmd_handler_statistics (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  commandDuration SMALLINT UNSIGNED NOT NULL,
  commandType ENUM( 'LDAP_PROXY_DELETE', 'LDAP_PROXY_ENABLE', 'LDAP_PROXY_DISABLE' ) NOT NULL COLLATE latin1_general_cs,
  numOfItems SMALLINT UNSIGNED NOT NULL,
  numOfSuccessItems SMALLINT UNSIGNED NOT NULL,
  numOfErrorItems SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4476

CREATE TABLE enm_dynamic_flow_control (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  value MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteidTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4477
-- END DDP-2.0.4478
-- END DDP-2.0.4479
-- END DDP-2.0.4480
-- END DDP-2.0.4481

CREATE TABLE enm_sd_assets (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  asset ENUM( 'PENM_UPGRADE', 'CENM_UPGRADE', 'VENM_UPGRADE', 'SENM_UPGRADE', 'OMBS_UPGRADE', 'ENIQ_UPGRADE', 'CTAF_TESTS' ) NOT NULL COLLATE latin1_general_cs,
  total SMALLINT UNSIGNED NOT NULL,
  pass SMALLINT UNSIGNED NOT NULL,
  fail SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4482

CREATE TABLE enm_pmic_notification (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  failedNotificationCount SMALLINT UNSIGNED NOT NULL,
  invalidNotificationCount SMALLINT UNSIGNED NOT NULL,
  lteNotificationCount SMALLINT UNSIGNED NOT NULL,
  mixedModeNotificationCount SMALLINT UNSIGNED NOT NULL,
  nrNotificationCount SMALLINT UNSIGNED NOT NULL,
  successfulNotificationCount SMALLINT UNSIGNED NOT NULL,
  totalNotificationCount SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4483

ALTER TABLE cm_import
  MODIFY COLUMN mos_created MEDIUMINT UNSIGNED DEFAULT NULL,
  MODIFY COLUMN mos_deleted MEDIUMINT UNSIGNED DEFAULT NULL;
-- END DDP-2.0.4484
-- END DDP-2.0.4485

ALTER TABLE enm_nsj_statistics
  MODIFY COLUMN jobCommandId ENUM( 'CPP_GET_SL', 'CPP_SET_SL', 'CPP_INSTALL_LAAD', 'CREATE_CREDENTIALS', 'UPDATE_CREDENTIALS',
   'GET_CREDENTIALS', 'ADD_TARGET_GROUPS', 'CPP_IPSEC_STATUS', 'CPP_IPSEC', 'CREATE_SSH_KEY', 'UPDATE_SSH_KEY',
   'IMPORT_NODE_SSH_PRIVATE_KEY', 'TEST_COMMAND', 'CERTIFICATE_ISSUE', 'SNMP_AUTHPRIV', 'SNMP_AUTHNOPRIV',
   'TRUST_DISTRIBUTE', 'SET_ENROLLMENT', 'GET_CERT_ENROLL_STATE', 'GET_TRUST_CERT_INSTALL_STATE', 'CERTIFICATE_REISSUE',
   'LDAP_CONFIGURATION', 'LDAP_RECONFIGURATION', 'TRUST_REMOVE', 'CRL_CHECK_ENABLE', 'GET_JOB', 'CRL_CHECK_DISABLE',
   'CRL_CHECK_GET_STATUS', 'ON_DEMAND_CRL_DOWNLOAD', 'SET_CIPHERS', 'GET_CIPHERS', 'ENROLLMENT_INFO_FILE',
   'RTSEL_ACTIVATE', 'RTSEL_DEACTIVATE', 'RTSEL_GET', 'RTSEL_DELETE', 'HTTPS_ACTIVATE', 'HTTPS_DEACTIVATE',
   'HTTPS_GET_STATUS', 'GET_SNMP', 'GET_SNMP_PLAIN_TEXT', 'FTPES_ACTIVATE', 'FTPES_DEACTIVATE', 'FTPES_GET_STATUS',
   'GET_NODE_SPECIFIC_PASSWORD', 'CAPABILITY_GET', 'LAAD_FILES_DISTRIBUTE', 'NTP_LIST', 'NTP_REMOVE', 'NTP_CONFIGURE',
   'SSO_ENABLE', 'SSO_DISABLE', 'SSO_GET', 'LDAP_RENEW' ) NOT NULL COLLATE latin1_general_cs;


-- END DDP-2.0.4486
-- END DDP-2.0.4487
-- END DDP-2.0.4488
-- END DDP-2.0.4489
-- END DDP-2.0.4490
-- END DDP-2.0.4491
-- END DDP-2.0.4492
-- END DDP-2.0.4493
-- END DDP-2.0.4494

ALTER TABLE enm_pmic_subs
  MODIFY COLUMN type ENUM('CELLTRACE','CELLTRAFFIC','CONTINUOUSCELLTRACE','CTUM','EBM','GPEH','STATISTICAL','UETR','UETRACE','PRODUCTDATA');

-- END DDP-2.0.4495
-- END DDP-2.0.4496
-- END DDP-2.0.4497
-- END DDP-2.0.4498

CREATE TABLE enm_npam_job_details (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  jobType ENUM( 'ROTATE_NE_ACCOUNT_CREDENTIALS', 'ROTATE_NE_ACCOUNT_CREDENTIALS_AUTOGENERATED',
    'ROTATE_NE_ACCOUNT_CREDENTIALS_FROM_FILE', 'CREATE_NE_ACCOUNT', 'DETACH_NE_ACCOUNT',
    'CHECK_AND_UPDATE_NE_ACCOUNT_CONFIGURATION' ) NOT NULL COLLATE latin1_general_cs,
  numberOfNetworkElements SMALLINT UNSIGNED NOT NULL,
  numberOfNeJobFailed SMALLINT UNSIGNED NOT NULL,
  durationOfJob MEDIUMINT UNSIGNED NOT NULL,
  result ENUM( 'SUCCESS', 'FAILED' ) NOT NULL COLLATE latin1_general_cs,
  status ENUM( 'COMPLETED' ) NOT NULL COLLATE latin1_general_cs,
  neJobRate SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4499

CREATE TABLE cm_subscriptions_nbi (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  createEventsReceived MEDIUMINT UNSIGNED NOT NULL,
  vesEventsToBePushedNotifyMoiCreation MEDIUMINT UNSIGNED NOT NULL,
  vesEventsToBePushedNotifyMoiChangesCreate MEDIUMINT UNSIGNED NOT NULL,
  updateEventsReceived MEDIUMINT UNSIGNED NOT NULL,
  vesEventsToBePushedNotifyMoiAvc MEDIUMINT UNSIGNED NOT NULL,
  vesEventsToBePushedNotifyMoiChangesReplace MEDIUMINT UNSIGNED NOT NULL,
  deleteEventsReceived MEDIUMINT UNSIGNED NOT NULL,
  vesEventsToBePushedNotifyMoiDeletion MEDIUMINT UNSIGNED NOT NULL,
  vesEventsToBePushedNotifyMoiChangesDelete MEDIUMINT UNSIGNED NOT NULL,
  totalEventsReceived MEDIUMINT UNSIGNED NOT NULL,
  totalVesEventsToBePushed MEDIUMINT UNSIGNED NOT NULL,
  totalVesEventsPushedSuccessfully MEDIUMINT UNSIGNED NOT NULL,
  totalVesEventsPushedError MEDIUMINT UNSIGNED NOT NULL,
  totalVesEventsPushedCancelled MEDIUMINT UNSIGNED NOT NULL,
  totalSuccessfulHeartbeatRequests SMALLINT UNSIGNED NOT NULL,
  totalFailedHeartbeatRequests SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4500
-- END DDP-2.0.4501
-- END DDP-2.0.4502
-- END DDP-2.0.4503
-- END DDP-2.0.4504

ALTER TABLE cm_subscriptions_nbi
  ADD COLUMN successfulPostSubscriptions SMALLINT UNSIGNED NOT NULL,
  ADD COLUMN failedPostSubscriptions SMALLINT UNSIGNED NOT NULL,
  ADD COLUMN successfulSubscriptionViews SMALLINT UNSIGNED NOT NULL,
  ADD COLUMN failedSubscriptionViews SMALLINT UNSIGNED NOT NULL,
  ADD COLUMN successfulSubscriptionDeletion SMALLINT UNSIGNED NOT NULL,
  ADD COLUMN failedSubscriptionDeletion SMALLINT UNSIGNED NOT NULL;
-- END DDP-2.0.4505

CREATE TABLE cm_subscribed_events_nbi (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  subscriptionId MEDIUMINT UNSIGNED NOT NULL,
  notificationTypes SET("notifyMOICreation", "notifyMOIDeletion", "notifyMOIAttributeValueChanges", "notifyMOIChanges") COLLATE latin1_general_cs,
  scope SET("BASE_ONLY", "BASE_ALL", "BASE_NTH_LEVEL", "BASE_SUBTREE") COLLATE latin1_general_cs,
  eventName ENUM('CREATED', 'DELETED') NOT NULL COLLATE latin1_general_cs,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4506
-- END DDP-2.0.4507
-- END DDP-2.0.4508
-- END DDP-2.0.4509
-- END DDP-2.0.4510
-- END DDP-2.0.4511
-- END DDP-2.0.4512
-- END DDP-2.0.4513

ALTER TABLE enm_flow_asu_overallsummary
  ADD COLUMN adaptiveRestartNodes SMALLINT UNSIGNED;
-- END DDP-2.0.4514

CREATE TABLE enm_infrastructure_monitor (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  mspmPullFilesSessionCreationTime SMALLINT UNSIGNED NOT NULL,
  mspmPullFilesStoredFS SMALLINT UNSIGNED NOT NULL,
  mspmPullFilesWriteTimeFS SMALLINT UNSIGNED NOT NULL,
  mspmPullFilesBytesStoredFS MEDIUMINT UNSIGNED NOT NULL,
  mspmPullFilesBytesTransfered MEDIUMINT UNSIGNED NOT NULL,
  mspmPullFilesTransferTime SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
      PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4515

CREATE TABLE enm_cm_site_energy_visualization_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  numberOfEnergyElementAcMeterReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementAcPhaseReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementBatteryReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementBatteryStringReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementBatteryUnitReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementDcMeterReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementDieselGeneratorReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementTankReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementServiceIntervalReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementGridReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementHVACReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementPowerInputGridReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementPowerInputWindReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementPowerInputDieselGeneratorReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementPowerManagerReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementPowerSystemReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementRectifierReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementRectifiersReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementSolarReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementSolarConverterReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementWindReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementsReadPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfNodeConnectionPerRequest SMALLINT UNSIGNED NOT NULL,
  numberOfSuccessfulRequestsEF SMALLINT UNSIGNED NOT NULL,
  numberOfFailedRequestsEF SMALLINT UNSIGNED NOT NULL,
  totalResponseTimePerRequestEF MEDIUMINT UNSIGNED NOT NULL,
  totalResponseSizeInKbEF SMALLINT UNSIGNED NOT NULL,
  totalDataReadTimePerRequestEF MEDIUMINT UNSIGNED NOT NULL,
  numberOfPmFileNotificationsReceived SMALLINT UNSIGNED NOT NULL,
  numberOfParsedPmFiles SMALLINT UNSIGNED NOT NULL,
  numberOfUnParsedPmFiles SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementTankInPmFiles SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementDieselGeneratorInPmFiles SMALLINT UNSIGNED NOT NULL,
  numberOfEnergyElementGridInPmFiles SMALLINT UNSIGNED NOT NULL,
  numberOfDbReadOperationsER SMALLINT UNSIGNED NOT NULL,
  totalTimePerReadOperation MEDIUMINT UNSIGNED NOT NULL,
  numberOfDbWriteOperationsER SMALLINT UNSIGNED NOT NULL,
  totalTimePerWriteOperation MEDIUMINT UNSIGNED NOT NULL,
  numberOfPmRecordsPerWriteOperation SMALLINT UNSIGNED NOT NULL,
  numberOfDbDeleteOperationsER SMALLINT UNSIGNED NOT NULL,
  totalTimePerDeleteOperation MEDIUMINT UNSIGNED NOT NULL,
  totalTimeTakenToParsePmFiles MEDIUMINT UNSIGNED NOT NULL,
  numberOfSuccessfulRequestsER SMALLINT UNSIGNED NOT NULL,
  numberOfFailedRequestsER SMALLINT UNSIGNED NOT NULL,
  totalResponseTimePerRequestER MEDIUMINT UNSIGNED NOT NULL,
  totalResponseSizeInKbER SMALLINT UNSIGNED NOT NULL,
  totalTimeTakenForHouseKeepingER MEDIUMINT UNSIGNED NOT NULL,
  numberOfDbConnections MEDIUMINT UNSIGNED NOT NULL,
  totalDataReadTimePerRequestER MEDIUMINT UNSIGNED NOT NULL,
  numberOfSuccessfulRequestsUS SMALLINT UNSIGNED NOT NULL,
  numberOfFailedRequestsUS SMALLINT UNSIGNED NOT NULL,
  totalResponseTimePerRequestUS MEDIUMINT UNSIGNED NOT NULL,
  numberOfSuccessfulUpdates SMALLINT UNSIGNED NOT NULL,
  numberOfFailedUpdates SMALLINT UNSIGNED NOT NULL,
  totalResponseTimePerUpdate MEDIUMINT UNSIGNED NOT NULL,
  numberOfDbReadOperationsUS SMALLINT UNSIGNED NOT NULL,
  totalTimeTakenToReadSettingsPerUser MEDIUMINT UNSIGNED NOT NULL,
  numberOfDbWriteOperationsUS SMALLINT UNSIGNED NOT NULL,
  totalTimeTakenToWriteSettingsPerUser MEDIUMINT UNSIGNED NOT NULL,
  numberOfDbDeleteOperationsUS SMALLINT UNSIGNED NOT NULL,
  totalTimeTakenToDeleteSettingsPerUser MEDIUMINT UNSIGNED NOT NULL,
  totalTimeTakenForHouseKeepingUS MEDIUMINT UNSIGNED NOT NULL,
  totalResponseSizeInKbUS SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
      PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_energy_flow_tasks (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  totalTimeTaken SMALLINT UNSIGNED NOT NULL,
  task ENUM( 'FM_DATA_READ', 'CONFIG_DATA_READ', 'LIVE_DATA_READ',
  'ENERGY_FLOW_VALIDATION', 'ENERGY_FLOW_BUILD',
  'ENERGY_REPORT', 'ENERGY_FLOW' ) NOT NULL COLLATE latin1_general_cs,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4516
-- END DDP-2.0.4517
-- END DDP-2.0.4518
-- END DDP-2.0.4519
-- END DDP-2.0.4520
-- END DDP-2.0.4521
-- END DDP-2.0.4522

DELETE FROM volumes WHERE name LIKE "L_%";

-- END DDP-2.0.4523
-- END DDP-2.0.4524
-- END DDP-2.0.4525
-- END DDP-2.0.4526
-- END DDP-2.0.4527
-- END DDP-2.0.4528

ALTER TABLE enm_site_info
 ADD COLUMN nodecount SMALLINT AFTER date;

UPDATE enm_site_info AS l
 LEFT JOIN (
  SELECT
   enm_network_element_details.siteid AS siteid,
   enm_network_element_details.date AS date,
   SUM(enm_network_element_details.count) AS nodecount
  FROM enm_network_element_details
  JOIN sites ON enm_network_element_details.siteid = sites.id AND enm_network_element_details.date = DATE(sites.lastupload)
  GROUP BY enm_network_element_details.siteid
 ) AS r ON l.siteid = r.siteid AND l.date = r.date
SET l.nodecount = r.nodecount;
-- END DDP-2.0.4529
-- END DDP-2.0.4530

CREATE TABLE enm_neo4j_orphan_mo_count (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  count SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4531
-- END DDP-2.0.4532
-- END DDP-2.0.4533

ALTER TABLE cm_subscriptions_nbi RENAME enm_cm_subscriptions_nbi;

ALTER TABLE enm_cm_subscriptions_nbi
  ADD COLUMN successfulViewAllSubscriptions SMALLINT UNSIGNED NOT NULL,
  ADD COLUMN failedViewAllSubscriptions SMALLINT UNSIGNED NOT NULL;

-- END DDP-2.0.4534
-- END DDP-2.0.4535
-- END DDP-2.0.4536
-- END DDP-2.0.4538

CREATE TABLE enm_fnt_push_service (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  numberOfFilesToTransfer MEDIUMINT UNSIGNED NOT NULL,
  numberOfFilesTransferred MEDIUMINT UNSIGNED NOT NULL,
  numberOfFilesFailed MEDIUMINT UNSIGNED NOT NULL,
  serviceType ENUM('PM_STATS','CM','ProductData') COLLATE latin1_general_cs,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fnt_product_data (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  numberOfNodesSpecifiedToEnableProductData SMALLINT UNSIGNED NOT NULL,
  numberOfNodesEnabledForProductData SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4544

ALTER TABLE enm_pm_orphan_file_del_stats
  MODIFY COLUMN type ENUM( 'StatisticalSubscription', 'GpehSubscription', 'MtrSubscription',
    'BSCRecordingsSubscription', 'UeTraceSubscription', 'CtumSubscription', 'CelltraceSubscription',
    'EbmSubscription', 'CellTrafficSubscription', 'UetrSubscription', 'ProductDataSubscription',
    'EbsSubscription' ) NOT NULL COLLATE latin1_general_cs;
-- END DDP-2.0.4545

ALTER TABLE kpiserv_reststatistics_instr
  ADD COLUMN getfetchKpiValuesexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
  ADD COLUMN getfetchKpiValuesmethodInvocations SMALLINT UNSIGNED,
  ADD COLUMN getfetchHistoricalKpiValuesexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
  ADD COLUMN getfetchHistoricalKpiValuesmethodInvocations SMALLINT UNSIGNED;
-- END DDP-2.0.4546

CREATE TABLE enm_cm_total_notifications (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  received SMALLINT UNSIGNED NOT NULL,
  processed SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4547
-- END DDP-2.0.4548
-- END DDP-2.0.4549
-- END DDP-2.0.4550
-- END DDP-2.0.4551
-- END DDP-2.0.4552


ALTER TABLE enm_cm_subscriptions_nbi
  ADD COLUMN successfulContinuousHeartbeatRequests SMALLINT UNSIGNED,
  ADD COLUMN failedContinuousHeartbeatRequests SMALLINT UNSIGNED;
-- END DDP-2.0.4553

ALTER TABLE enm_nhc_profiles_log
  ADD COLUMN status ENUM( 'Created', 'Imported', 'Partially Imported' ) COLLATE latin1_general_cs;

-- END DDP-2.0.4554
-- END DDP-2.0.4555
-- END DDP-2.0.4556

ALTER TABLE enm_pm_orphan_file_del_stats
  MODIFY COLUMN type ENUM( 'StatisticalSubscription', 'GpehSubscription', 'MtrSubscription', 'BSCRecordingsSubscription',
      'UeTraceSubscription', 'CtumSubscription', 'CelltraceSubscription', 'EbmSubscription',
      'CellTrafficSubscription', 'UetrSubscription', 'EbsSubscription', 'ProductDataSubscription' ) NOT NULL COLLATE latin1_general_cs,
  ADD COLUMN filter ENUM( 'PFD', 'OPFD' ) DEFAULT 'PFD' COLLATE latin1_general_cs;

CREATE TABLE enm_flsdb_file_del_stats (
      siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
      time DATETIME NOT NULL,
      datatypeid SMALLINT UNSIGNED COMMENT "REFERENCES enm_pmic_datatypes(id)",
      expiredRowsToBeDeleted MEDIUMINT UNSIGNED NOT NULL,
      actualRowsDeleted MEDIUMINT UNSIGNED NOT NULL,
      rowsDeletionTime MEDIUMINT UNSIGNED NOT NULL,
      filter ENUM( 'PFD', 'OPFD' ) NOT NULL COLLATE latin1_general_cs,
      INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
      PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_pmic_fs_usage (
      siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
      time DATETIME NOT NULL,
      path ENUM( '/ericsson/pmic1', '/ericsson/pmic2' ) NOT NULL COLLATE latin1_general_cs,
      fsCapacity MEDIUMINT UNSIGNED NOT NULL,
      fsUsage MEDIUMINT UNSIGNED NOT NULL,
      fsAvailable MEDIUMINT UNSIGNED NOT NULL,
      INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
      PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4557
-- END DDP-2.0.4558
-- END DDP-2.0.4559

ALTER TABLE volume_stats
PARTITION BY RANGE ( TO_DAYS(date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4560

ALTER TABLE enm_flow_asu_overallsummary
  ADD COLUMN nodesCancelled SMALLINT UNSIGNED;

ALTER TABLE enm_flow_asu_phasesummary
  ADD COLUMN nodesCancelled SMALLINT UNSIGNED;
-- END DDP-2.0.4561
-- END DDP-2.0.4562

DELETE FROM enm_apache_uri WHERE uri LIKE '/ncm/%';

-- END DDP-2.0.4563

CREATE TABLE enm_pm_file_del_stats_instr (
      siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
      serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
      time DATETIME NOT NULL,
      expiredFilesCount MEDIUMINT UNSIGNED NOT NULL,
      filesDeletedCount MEDIUMINT UNSIGNED NOT NULL,
      filesDeletedTime MEDIUMINT UNSIGNED NOT NULL,
      filesDeletionFailedCount MEDIUMINT UNSIGNED NOT NULL,
      filter ENUM( 'PFD', 'OPFD' ) NOT NULL COLLATE latin1_general_cs,
    INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
      PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4564
-- END DDP-2.0.4565
-- END DDP-2.0.4566

ALTER TABLE enm_cm_total_notifications
  MODIFY COLUMN received MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN processed MEDIUMINT UNSIGNED NOT NULL;
-- END DDP-2.0.4567
-- END DDP-2.0.4568
-- END DDP-2.0.4569

CREATE TABLE nfsd_stat (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 scall SMALLINT UNSIGNED NOT NULL,
 badcall SMALLINT UNSIGNED NOT NULL,
 packet SMALLINT UNSIGNED NOT NULL,
 sread SMALLINT UNSIGNED NOT NULL,
 swrite SMALLINT UNSIGNED NOT NULL,
 saccess SMALLINT UNSIGNED NOT NULL,
 sgetatt SMALLINT UNSIGNED NOT NULL,
 INDEX siteIdTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4570
-- END DDP-2.0.4571

ALTER TABLE enm_ebsl_inst_stats
  ADD COLUMN numberOfNRcountersDroppedDueToMissingParameter MEDIUMINT UNSIGNED;
-- END DDP-2.0.4572
-- END DDP-2.0.4573
-- END DDP-2.0.4574

CREATE TABLE sum_proc_stats
(
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 procid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES process_names(id)",
 cpu MEDIUMINT UNSIGNED NOT NULL,
 mem MEDIUMINT UNSIGNED NOT NULL,
 thr SMALLINT UNSIGNED NOT NULL,
 fd  SMALLINT UNSIGNED NOT NULL,
 rss MEDIUMINT UNSIGNED NOT NULL,
 nproc TINYINT UNSIGNED NOT NULL,
 cpu_rate FLOAT NOT NULL,
 INDEX pidIdx(procid),
 INDEX serverTimeIdx(serverid,date),
 INDEX siteIdIdx(siteid,date)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4575
-- END DDP-2.0.4576
-- END DDP-2.0.4577
-- END DDP-2.0.4578

CREATE TABLE nhc_profiles_requests (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  executionTime MEDIUMINT UNSIGNED NOT NULL,
  type ENUM( 'EXPORT', 'IMPORT' ) NOT NULL COLLATE latin1_general_cs,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4579
-- END DDP-2.0.4580

CREATE TABLE enm_cm_element_manager_usage (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 memoryused TINYINT UNSIGNED NOT NULL,
 sessioncount SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

DROP TABLE nhc_profiles_requests;

CREATE TABLE enm_nhc_profiles_requests (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  executionTime MEDIUMINT UNSIGNED NOT NULL,
  type ENUM( 'EXPORT', 'IMPORT' ) NOT NULL COLLATE latin1_general_cs,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4581
-- END DDP-2.0.4582
-- END DDP-2.0.4583
-- END DDP-2.0.4584
-- END DDP-2.0.4585
-- END DDP-2.0.4586
-- END DDP-2.0.4587

ALTER TABLE enm_modeling_fileread_instr
  MODIFY COLUMN avgModelReadTime MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN maxModelReadTime MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN repoReadTime MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN maxRepoReadTime MEDIUMINT UNSIGNED NOT NULL,
  MODIFY COLUMN repoReads MEDIUMINT UNSIGNED NOT NULL;
-- END DDP-2.0.4588

CREATE TABLE sum_enm_dps_neo4jtx (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  date DATE NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  readTx100MillisecondsCount MEDIUMINT UNSIGNED,
  readTx10MillisecondsCount MEDIUMINT UNSIGNED,
  readTx10SecondsCount MEDIUMINT UNSIGNED,
  readTx1MinuteCount MEDIUMINT UNSIGNED,
  readTx1SecondCount MEDIUMINT UNSIGNED,
  readTx2MinutesCount MEDIUMINT UNSIGNED,
  readTx3MinutesCount MEDIUMINT UNSIGNED,
  readTx4MinutesCount MEDIUMINT UNSIGNED,
  readTx500MillisecondsCount MEDIUMINT UNSIGNED,
  readTx50MillisecondsCount MEDIUMINT UNSIGNED,
  readTx5MillisecondsCount MEDIUMINT UNSIGNED,
  readTx5MinutesCount MEDIUMINT UNSIGNED,
  readTxCount MEDIUMINT UNSIGNED,
  readTxOver5MinutesCount MEDIUMINT UNSIGNED,
  writeTx100MillisecondsCount MEDIUMINT UNSIGNED,
  writeTx10MillisecondsCount MEDIUMINT UNSIGNED,
  writeTx10SecondsCount MEDIUMINT UNSIGNED,
  writeTx1MinuteCount MEDIUMINT UNSIGNED,
  writeTx1SecondCount MEDIUMINT UNSIGNED,
  writeTx2MinutesCount MEDIUMINT UNSIGNED,
  writeTx3MinutesCount MEDIUMINT UNSIGNED,
  writeTx4MinutesCount MEDIUMINT UNSIGNED,
  writeTx500MillisecondsCount MEDIUMINT UNSIGNED,
  writeTx50MillisecondsCount MEDIUMINT UNSIGNED,
  writeTx5MillisecondsCount MEDIUMINT UNSIGNED,
  writeTx5MinutesCount MEDIUMINT UNSIGNED,
  writeTxCount MEDIUMINT UNSIGNED,
  writeTxOver5MinutesCount MEDIUMINT UNSIGNED,
  acquiredTxPermitsCount MEDIUMINT UNSIGNED,
  failedToAcquireTxPermitsCount MEDIUMINT UNSIGNED,
  failureOrTimeoutCount MEDIUMINT UNSIGNED,
  totalDuration MEDIUMINT UNSIGNED,
  txPermitsProcedureCount MEDIUMINT UNSIGNED,
  writeTxWithoutChangesCount MEDIUMINT UNSIGNED,
  totalWriteOperationsPerformed MEDIUMINT UNSIGNED,
  INDEX siteTimeIdx(siteid,date)
)  PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4589

CREATE TABLE enm_pmic_rest_nbi (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  requestType ENUM('List Subscriptions Rest NBI', 'Get Subscription Rest NBI', 'Create Subscription Rest NBI',
  'Activate Subscription Rest NBI', 'Deactivate Subscription Rest NBI', 'Edit Subscription Rest NBI',
  'Delete Subscription Rest NBI') NOT NULL COLLATE latin1_general_cs,
  httpMethod ENUM('GET', 'POST', 'PUT', 'DELETE') NOT NULL COLLATE latin1_general_cs,
  totalTimeTakenToRespondRequest SMALLINT UNSIGNED NOT NULL,
  totalRequestRecieved SMALLINT UNSIGNED NOT NULL,
  totalFailedHttpResponse SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4590

ALTER TABLE kpiserv_reststatistics_instr
  ADD COLUMN getActivationStatusresponseTime MEDIUMINT UNSIGNED,
  ADD COLUMN getActivationStatustotalRequestReceived SMALLINT UNSIGNED,
  ADD COLUMN getKpiInstanceCapabilitiesresponseTime MEDIUMINT UNSIGNED,
  ADD COLUMN getKpiInstanceCapabilitiestotalRequestReceived SMALLINT UNSIGNED,
  ADD COLUMN getActivateOrDeactivateKpiresponseTime MEDIUMINT UNSIGNED,
  ADD COLUMN getActivateOrDeactivateKpitotalRequestReceived SMALLINT UNSIGNED,
  ADD COLUMN getDeleteKpiresponseTime MEDIUMINT UNSIGNED,
  ADD COLUMN getDeleteKpitotalRequestReceived SMALLINT UNSIGNED,
  ADD COLUMN getListKpiresponseTime MEDIUMINT UNSIGNED,
  ADD COLUMN getListKpitotalRequestReceived SMALLINT UNSIGNED,
  ADD COLUMN getCreateKpiresponseTime MEDIUMINT UNSIGNED,
  ADD COLUMN getCreateKpitotalRequestReceived SMALLINT UNSIGNED,
  ADD COLUMN getReadKpiDefinitionresponseTime MEDIUMINT UNSIGNED,
  ADD COLUMN getReadKpiDefinitiontotalRequestReceived SMALLINT UNSIGNED,
  ADD COLUMN getUpdateKpiresponseTime MEDIUMINT UNSIGNED,
  ADD COLUMN getUpdateKpitotalRequestReceived SMALLINT UNSIGNED;
-- END DDP-2.0.4591

ALTER TABLE cm_subscribed_events_nbi RENAME enm_cm_subscribed_events_nbi;
-- END DDP-2.0.4592
-- END DDP-2.0.4593
-- END DDP-2.0.4594

ALTER TABLE tor_ver
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE cm_export
PARTITION BY RANGE ( TO_DAYS(export_end_date_time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE emc_site
PARTITION BY RANGE ( TO_DAYS(filedate) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_amos_commands
PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_apache_requests
PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_bur_backup_throughput_stats
PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_cluster_host
PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_consul_n_sam_events
PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_logs
PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4595
-- END DDP-2.0.4596
-- END DDP-2.0.4597
-- END DDP-2.0.4598

ALTER TABLE enm_neo4j_chkpnts
PARTITION BY RANGE ( TO_DAYS(start) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_neo4j_srv_lr
PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_neo4j_raftevents
PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_netex_queries
PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_vcs_events
PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_winfiol_commands
PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_winfiol_sessions
PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE pm_errors
PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE sfs_bur_backup_throughput_stats
PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE vdb
PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4599
-- END DDP-2.0.4600
-- END DDP-2.0.4601
-- END DDP-2.0.4602

ALTER TABLE enm_kafka_topic_partitions
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_com_ecim_delta_syncs
PARTITION BY RANGE ( TO_DAYS(endtime) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE son_moc_rate
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_mscmcenotification_logs
PARTITION BY RANGE ( TO_DAYS(endtime) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

ALTER TABLE enm_versant_client_connpool
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4603
-- END DDP-2.0.4604
-- END DDP-2.0.4605
-- END DDP-2.0.4606
-- END DDP-2.0.4607
-- END DDP-2.0.4608
-- END DDP-2.0.4609
-- END DDP-2.0.4610
-- END DDP-2.0.4611
-- END DDP-2.0.4612
-- END DDP-2.0.4613
-- END DDP-2.0.4614
-- END DDP-2.0.4615
-- END DDP-2.0.4616
-- END DDP-2.0.4617

ALTER TABLE enm_nsj_statistics
  MODIFY COLUMN jobCommandId ENUM( 'CPP_GET_SL', 'CPP_SET_SL', 'CPP_INSTALL_LAAD', 'CREATE_CREDENTIALS', 'UPDATE_CREDENTIALS',
   'GET_CREDENTIALS', 'ADD_TARGET_GROUPS', 'CPP_IPSEC_STATUS', 'CPP_IPSEC', 'CREATE_SSH_KEY', 'UPDATE_SSH_KEY',
   'IMPORT_NODE_SSH_PRIVATE_KEY', 'TEST_COMMAND', 'CERTIFICATE_ISSUE', 'SNMP_AUTHPRIV', 'SNMP_AUTHNOPRIV',
   'TRUST_DISTRIBUTE', 'SET_ENROLLMENT', 'GET_CERT_ENROLL_STATE', 'GET_TRUST_CERT_INSTALL_STATE', 'CERTIFICATE_REISSUE',
   'LDAP_CONFIGURATION', 'LDAP_RECONFIGURATION', 'TRUST_REMOVE', 'CRL_CHECK_ENABLE', 'GET_JOB', 'CRL_CHECK_DISABLE',
   'CRL_CHECK_GET_STATUS', 'ON_DEMAND_CRL_DOWNLOAD', 'SET_CIPHERS', 'GET_CIPHERS', 'ENROLLMENT_INFO_FILE',
   'RTSEL_ACTIVATE', 'RTSEL_DEACTIVATE', 'RTSEL_GET', 'RTSEL_DELETE', 'HTTPS_ACTIVATE', 'HTTPS_DEACTIVATE',
   'HTTPS_GET_STATUS', 'GET_SNMP', 'GET_SNMP_PLAIN_TEXT', 'FTPES_ACTIVATE', 'FTPES_DEACTIVATE', 'FTPES_GET_STATUS',
   'GET_NODE_SPECIFIC_PASSWORD', 'CAPABILITY_GET', 'LAAD_FILES_DISTRIBUTE', 'NTP_LIST', 'NTP_REMOVE', 'NTP_CONFIGURE',
   'SSO_ENABLE', 'SSO_DISABLE', 'SSO_GET', 'LDAP_RENEW', 'DELETE_SSH_KEY' ) NOT NULL COLLATE latin1_general_cs;
-- END DDP-2.0.4618
-- END DDP-2.0.4619
-- END DDP-2.0.4620

ALTER TABLE enm_ebsl_inst_stats
  ADD COLUMN indexSizeOfNRUplinkThroughputCounters SMALLINT UNSIGNED,
  ADD COLUMN indexSizeOfNRDownlinkVoiceThroughputCounters SMALLINT UNSIGNED,
  ADD COLUMN indexSizeOfNRDownlinkNonVoiceThroughputCounters SMALLINT UNSIGNED,
  ADD COLUMN numberOfSuspectCellsPerRop SMALLINT UNSIGNED;

ALTER TABLE enm_ebsm_inst_stats
  ADD COLUMN indexSizeOfDownlinkNonVoiceThroughputNR SMALLINT UNSIGNED,
  ADD COLUMN indexSizeOfDownlinkVoiceThroughputNR SMALLINT UNSIGNED,
  ADD COLUMN indexSizeOfUplinkThroughputNR SMALLINT UNSIGNED;
-- END DDP-2.0.4621

ALTER TABLE enm_mspmip_instr
  ADD COLUMN numberOfUploadRequestFailuresBulk15m SMALLINT UNSIGNED,
  ADD COLUMN numberOfSuccessfulRequestsBulk15m SMALLINT UNSIGNED,
  ADD COLUMN numberOfProcessingFlowFailuresBulk15m MEDIUMINT UNSIGNED,
  ADD COLUMN numberOfSuccessfulRecoveryRequestsBulk15m MEDIUMINT UNSIGNED,
  ADD COLUMN numberOfFailedRecoveryRequestsBulk15m MEDIUMINT UNSIGNED,
  ADD COLUMN noOfCollectedBulkPmFilesBulk15m MEDIUMINT UNSIGNED,
  ADD COLUMN noOfRecoveredBulkPmFilesBulk15m MEDIUMINT UNSIGNED,
  ADD COLUMN minProcessingHandlerTimeBulk15m MEDIUMINT UNSIGNED,
  ADD COLUMN maxProcessingHandlerTimeBulk15m MEDIUMINT UNSIGNED,
  ADD COLUMN numberOfUploadRequestFailuresBulk24h SMALLINT UNSIGNED,
  ADD COLUMN numberOfSuccessfulRequestsBulk24h SMALLINT UNSIGNED,
  ADD COLUMN numberOfProcessingFlowFailuresBulk24h SMALLINT UNSIGNED,
  ADD COLUMN noOfCollectedBulkPmFilesBulk24h SMALLINT UNSIGNED,
  ADD COLUMN noOfRecoveredBulkPmFilesBulk24h SMALLINT UNSIGNED,
  ADD COLUMN minProcessingHandlerTimeBulk24h SMALLINT UNSIGNED,
  ADD COLUMN maxProcessingHandlerTimeBulk24h SMALLINT UNSIGNED;
-- END DDP-2.0.4622
-- END DDP-2.0.4623
-- END DDP-2.0.4624
-- END DDP-2.0.4625
-- END DDP-2.0.4626

ALTER TABLE enm_mssnmpfm_instr
  ADD COLUMN noOfSnmpTargetDestinationDiscarded MEDIUMINT UNSIGNED,
  ADD COLUMN noOfSnmpTargetDestinationSent MEDIUMINT UNSIGNED;
-- END DDP-2.0.4627
-- END DDP-2.0.4628
-- END DDP-2.0.4629
-- END DDP-2.0.4630
-- END DDP-2.0.4631
-- END DDP-2.0.4632
-- END DDP-2.0.4633
-- END DDP-2.0.4634
-- END DDP-2.0.4635
-- END DDP-2.0.4636
-- END DDP-2.0.4637
-- END DDP-2.0.4638
-- END DDP-2.0.4639
-- END DDP-2.0.4640
-- END DDP-2.0.4641
-- END DDP-2.0.4642
-- END DDP-2.0.4643
-- END DDP-2.0.4644

CREATE TABLE enm_large_bsc_nodes (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
  ropPeriod SMALLINT UNSIGNED NOT NULL,
  totalVolume MEDIUMINT UNSIGNED NOT NULL,
  totalNumberOfFilesCollected SMALLINT UNSIGNED NOT NULL,
  largestFileSize MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4645
-- END DDP-2.0.4646
-- END DDP-2.0.4647

ALTER TABLE eniq_server_info
 MODIFY COLUMN type ENUM('OCS_ADDS', 'OCS_CCS', 'OCS_VDA', 'OCS_WITHOUT_CITRIX', 'BIS', 'NetAnServer', 'ENIQ', 'ACCESSNAS') NOT NULL;

CREATE TABLE enm_fm_handler_statistics (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  totalNoOfHeartbeatFailures SMALLINT UNSIGNED NOT NULL,
  totalNoOfSupervisedNodes SMALLINT UNSIGNED NOT NULL,
  totalNoOfForwardedAlarmEventNotifications SMALLINT UNSIGNED NOT NULL,
  totalNoOfForwardedSyncAlarmEventNotifications MEDIUMINT UNSIGNED NOT NULL,
  totalNoOfAlarmsReceived SMALLINT UNSIGNED NOT NULL,
  totalNoOfHeartbeatsReceived SMALLINT UNSIGNED NOT NULL,
  totalNoOfSuccessfulTransformations MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
-- END DDP-2.0.4648
-- END DDP-2.0.4649
-- END DDP-2.0.4650
-- END DDP-2.0.4651
-- END DDP-2.0.4652

DROP TABLE users_tmp;
DROP TABLE users;
DROP TABLE user_group;

-- END DDP-2.0.4653
-- END DDP-2.0.4654
-- END DDP-2.0.4655
-- END DDP-2.0.4656

ALTER TABLE cm_import
  ADD COLUMN serverid INT UNSIGNED COMMENT "REFERENCES servers(id)";

ALTER TABLE enm_bulk_import_ui
  ADD COLUMN serverid INT UNSIGNED COMMENT "REFERENCES servers(id)";
-- END DDP-2.0.4657
-- END DDP-2.0.4658

ALTER TABLE enm_mscmce_instr
  ADD COLUMN yangNumberOfSoftwareSyncInvocations SMALLINT UNSIGNED,
  ADD COLUMN yangNotificationsReceivedCount SMALLINT UNSIGNED,
  ADD COLUMN yangNotificationsProcessedCount SMALLINT UNSIGNED,
  ADD COLUMN yangNotificationsDiscardedCount SMALLINT UNSIGNED;
-- END DDP-2.0.4659

DELETE FROM enm_apache_uri WHERE uri LIKE '%\%%';

-- END DDP-2.0.4660
-- END DDP-2.0.4661
-- END DDP-2.0.4662

ALTER TABLE eniq_ddp_report
  ADD COLUMN serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)" AFTER siteid,
  ADD COLUMN fileType ENUM( 'ddp_report', 'mpath', 'iq_header' ) COLLATE latin1_general_cs;

DELETE FROM enm_bur_backup_mount_points WHERE backup_mount_point REGEXP 'aa[0-9]+$';

-- END DDP-2.0.4663

CREATE TABLE server_availability (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  INDEX siteDateServIdx( siteid, date, serverid )
) PARTITION BY RANGE ( TO_DAYS( date ) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END DDP-2.0.4664
-- END DDP-2.0.4665

ALTER TABLE enm_network_element_details
  ADD COLUMN pm_supervised_count MEDIUMINT UNSIGNED;

ALTER TABLE enm_secserv_comaa_instr
  ADD COLUMN numberOfSuccessfulTokenValidations MEDIUMINT UNSIGNED,
  ADD COLUMN numberOfFailedTokenValidations MEDIUMINT UNSIGNED,
  ADD COLUMN numberOfFastTokenValidations MEDIUMINT UNSIGNED,
  ADD COLUMN numberOfHighTokenValidations MEDIUMINT UNSIGNED,
  ADD COLUMN numberOfSlowTokenValidations MEDIUMINT UNSIGNED;
-- END DDP-2.0.4666
-- END DDP-2.0.4667

ALTER TABLE enm_geo_kpi_logs
  MODIFY COLUMN application ENUM('CM','PKI','IDAM','FM','NFS','FMX','ENMLogs','SECADM','LDAP','VNFLCM','CMPrePopulation','CMDeltaImport','TotalExport','TotalImport','NCM','NHM')COLLATE latin1_general_cs;
-- END DDP-2.0.4668
-- END DDP-2.0.4669
-- END DDP-2.0.4670

ALTER TABLE enm_ebsl_inst_stats
  MODIFY COLUMN indexSizeOfNRUplinkThroughputCounters MEDIUMINT UNSIGNED,
  MODIFY COLUMN indexSizeOfNRDownlinkVoiceThroughputCounters MEDIUMINT UNSIGNED,
  MODIFY COLUMN indexSizeOfNRDownlinkNonVoiceThroughputCounters MEDIUMINT UNSIGNED,
  MODIFY COLUMN numberOfSuspectCellsPerRop MEDIUMINT UNSIGNED;

ALTER TABLE enm_ebsm_inst_stats
  MODIFY COLUMN indexSizeOfDownlinkNonVoiceThroughputNR MEDIUMINT UNSIGNED,
  MODIFY COLUMN indexSizeOfDownlinkVoiceThroughputNR MEDIUMINT UNSIGNED,
  MODIFY COLUMN indexSizeOfUplinkThroughputNR MEDIUMINT UNSIGNED;
-- END DDP-2.0.4671
-- END DDP-2.0.4672
-- END DDP-2.0.4673
-- END DDP-2.0.4674
-- END DDP-2.0.4675
