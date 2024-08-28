CREATE TABLE operators (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx (name)
);
INSERT INTO operators (name) VALUES ("");

CREATE TABLE deploy_infra (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx (name)
);
INSERT INTO deploy_infra (name) VALUES ("");

CREATE TABLE sites
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    site_type ENUM('OSS','ENIQ','TOR','SERVICEON','DDP','NAVIGATOR',
       'UNDEFINED','GENERIC','EO','ECSON') NOT NULL DEFAULT 'OSS' COLLATE latin1_general_cs,
    utilver CHAR(10) COLLATE latin1_general_cs,
    lastupload DATETIME DEFAULT NULL,
    country CHAR(2) COLLATE latin1_general_cs,
    oper_id SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "REFERENCES operators(id)",
    site_status ENUM('live','lab','inactive') COLLATE latin1_general_cs,
    creator VARCHAR(30) COLLATE latin1_general_cs,
    requestor VARCHAR(30) COLLATE latin1_general_cs,
    infra_id SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "REFERENCES deploy_infra(id)",
    UNIQUE INDEX nameIdx (name),
    PRIMARY KEY(id)
);

CREATE TABLE site_data (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 dataAvailabilityTime DATETIME,
 INDEX siteIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE getalarmlist
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    duration SMALLINT UNSIGNED NOT NULL,
    size MEDIUMINT UNSIGNED NOT NULL
);

CREATE TABLE sybase_users
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(30) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE sybase_usage_by_user
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    userid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_users(id)",
    cpu    INT UNSIGNED NOT NULL,
    io     INT UNSIGNED NOT NULL,
    INDEX siteidDate (siteid,date)
);

CREATE TABLE sybase_usage_by_user_hires
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    userid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_users(id)",
    cpu    INT UNSIGNED NOT NULL,
    io     INT UNSIGNED NOT NULL,
    INDEX siteidTime (siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE sybase_dbnames
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(name),
    PRIMARY KEY(id)
);

CREATE TABLE sybase_dbspace
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    dbid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_dbnames(id)",
    dbsize MEDIUMINT UNSIGNED NOT NULL,
    datasize MEDIUMINT UNSIGNED NOT NULL,
    datafree MEDIUMINT UNSIGNED NOT NULL,
    logsize MEDIUMINT UNSIGNED NOT NULL,
    logfree MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteidDate (siteid,date)
);

CREATE TABLE sybase_mda
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    cpu_sys  INT UNSIGNED NOT NULL,
    cpu_user INT UNSIGNED NOT NULL,
    cpu_io   INT UNSIGNED NOT NULL,
    cpu_idle INT UNSIGNED NOT NULL,
    cache_search INT UNSIGNED NOT NULL,
    cache_read   INT UNSIGNED NOT NULL,
    cache_write  INT UNSIGNED NOT NULL,
    cache_lread  INT UNSIGNED NOT NULL,
    cache_stall  INT UNSIGNED NOT NULL,
        INDEX siteIdx(siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sybase_mda_device_name
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE sybase_mda_device_io
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    devid  SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_mda_device_name(id)",
    nreads    INT UNSIGNED NOT NULL,
    apfReads INT UNSIGNED NOT NULL,
    nwrites   INT UNSIGNED NOT NULL,
    req       INT UNSIGNED NOT NULL,
    reqw      INT UNSIGNED NOT NULL,
    iotime    INT UNSIGNED NOT NULL,
        INDEX siteIdx(siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE sybase_logins
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    userid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_users(id)",
        status ENUM ( 'alarm sleep', 'background', 'latch sleep', 'lock sleep', 'PLC sleep', 'recv sleep', 'remote i/o', 'runnable', 'running', 'send sleep', 'sleeping', 'stopped', 'sync sleep' ) COLLATE latin1_general_cs,
        num SMALLINT UNSIGNED NOT NULL,
        INDEX siteIdx(siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);



CREATE TABLE pms_stats
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    collected INT UNSIGNED NOT NULL,
    available INT UNSIGNED NOT NULL,
    avgroptime SMALLINT UNSIGNED NOT NULL,
    maxroptime SMALLINT UNSIGNED NOT NULL,
    uetr INT UNSIGNED NOT NULL,
    ctr INT  UNSIGNED NOT NULL,
    gpeh INT UNSIGNED NOT NULL,
    datavol INT UNSIGNED,
    rncavail MEDIUMINT UNSIGNED,
    rncmiss  MEDIUMINT UNSIGNED,
    rbsavail INT UNSIGNED,
    rbsmiss  INT UNSIGNED,
    rxiavail MEDIUMINT UNSIGNED,
    rximiss  MEDIUMINT UNSIGNED,
    erbsavail INT UNSIGNED,
    erbsmiss  INT UNSIGNED,
    prbsavail INT UNSIGNED,
    prbsmiss  INT UNSIGNED,
    dscavail INT UNSIGNED,
    dscmiss  INT UNSIGNED,
    extra MEDIUMINT UNSIGNED,
    tzoffset TINYINT,

    act_PREDEF MEDIUMINT UNSIGNED,
    sus_PREDEF MEDIUMINT UNSIGNED,
    act_USERDEF MEDIUMINT UNSIGNED,
    sus_USERDEF MEDIUMINT UNSIGNED,
    act_GPEH MEDIUMINT UNSIGNED,
    sus_GPEH MEDIUMINT UNSIGNED,
    act_UETR MEDIUMINT UNSIGNED,
    sus_UETR MEDIUMINT UNSIGNED,
    act_CTR MEDIUMINT UNSIGNED,
    sus_CTR MEDIUMINT UNSIGNED
);

CREATE TABLE pms_userdef_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE pms_profile_detail
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    list TEXT NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE pms_profile
(
    date        DATE NOT NULL,
    siteid   SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    name     VARCHAR(50) NOT NULL,
    type     ENUM( 'UETR', 'CTR', 'GPEH_RNC', 'STATS', 'SYSTEM', 'GPEH_RBS', 'UTRAN_RELATION_STATS', 'MO_INSTANCE', 'LTE_RBS_STATS', 'LTE_CELL_TRACE', 'EXT_LTE_CELL_TRACE', 'LTE_UE_TRACE', 'CONTINUOUS_ERBS_CELL_TRACE', 'ALL_USER_WRAN_STATS_HOLDER', 'ALL_UETR_TYPES_HOLDER', 'ALL_CTR_TYPES_HOLDER', 'ALL_GPEH_RNC_TYPES_HOLDER', 'ALL_GPEH_RBS_TYPES_HOLDER', 'ECIM_MEASUREMENT_STATS', 'ECIM_THRESHOLD_STATS', 'ECIM_REALTIME_STATS',
'ECIM_SGSN_MME', 'ECIM_MO_INSTANCE_BASED_STATS', 'ALL_RDT_RNC_TYPES_HOLDER', 'RDT_RNC' ) NOT NULL COLLATE latin1_general_cs,
    admin_state ENUM ( 'INACTIVE', 'ACTIVE', 'SCHEDULED' ) COLLATE latin1_general_cs,
    numne    SMALLINT UNSIGNED,
    detailid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES pms_profile_detail(id)",
    INDEX siteidDate (siteid,date)
);

CREATE TABLE pms_rnc_counters
(
    date     DATE NOT NULL,
    siteid   SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    rnsid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES rns(id)",
    numCntr INT UNSIGNED NOT NULL
);

CREATE TABLE pms_connectbytime
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    numConn SMALLINT UNSIGNED,
    tssGetAccAvg SMALLINT UNSIGNED,
    tssGetAccMax SMALLINT UNSIGNED,
    tssGetPwAvg SMALLINT UNSIGNED,
    tssGetPwMax SMALLINT UNSIGNED,
    nodeConnAvg SMALLINT UNSIGNED,
    nodeConnMax SMALLINT UNSIGNED,
    nodeAuthAvg SMALLINT UNSIGNED,
    nodeAuthMax SMALLINT UNSIGNED,
    INDEX (siteid,time)
);

CREATE TABLE pms_connectbynode
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
    nodeConnAvg SMALLINT UNSIGNED,
    nodeConnMax SMALLINT UNSIGNED,
    nodeAuthAvg SMALLINT UNSIGNED,
    nodeAuthMax SMALLINT UNSIGNED,
    jobAvg INT UNSIGNED,
    jobMax INT UNSIGNED,
    INDEX (siteid,date)
);

CREATE TABLE pms_filetransfer_rop
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    period ENUM( '15MIN', '1MIN', '5MIN', '60MIN' ) NOT NULL DEFAULT '15MIN',
    netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
    totalkb INT UNSIGNED NOT NULL,
    avgthroughput MEDIUMINT UNSIGNED NOT NULL,
    minthroughput MEDIUMINT UNSIGNED NOT NULL,
    filetype ENUM ( 'STATS', 'UETR', 'CTR', 'GPEH', 'CELLTRACE', 'UETRACE' ) COLLATE latin1_general_cs,
    lasttime DATETIME,
    numfiles MEDIUMINT,
    filesoutsiderop MEDIUMINT,
        -- collectedbx gives the count of files collected in a given interval
        -- collectedb0 counts files collected in first three mins
        -- collectedb1 counts files collected in the next three mins (3-5), an so on
    collectedb0 SMALLINT UNSIGNED,
    collectedb1 SMALLINT UNSIGNED,
    collectedb2 SMALLINT UNSIGNED,
    collectedb3 SMALLINT UNSIGNED,
    collectedb4 SMALLINT UNSIGNED,
    INDEX timeSiteId (time,siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE pms_filetransfer_node
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
    filetype ENUM ( 'STATS', 'UETR', 'CTR', 'GPEH', 'CELLTRACE', 'UETRACE' ) COLLATE latin1_general_cs,
    period ENUM( '15MIN', '1MIN', '5MIN', '60MIN' ),
    totalkb INT UNSIGNED NOT NULL,
    avgthroughput MEDIUMINT UNSIGNED NOT NULL,
    minthroughput MEDIUMINT UNSIGNED NOT NULL,
    files MEDIUMINT UNSIGNED,
    available SMALLINT UNSIGNED,
    missing SMALLINT UNSIGNED,
    INDEX dateSiteId (date,siteid)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE pms_listscanners_names (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE pms_listscanners_execution (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    listscannersid TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES pms_listscanners_names(id)",
    duration SMALLINT UNSIGNED NOT NULL DEFAULT 0
);


CREATE TABLE export
(
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    start DATETIME NOT NULL,
    end DATETIME NOT NULL,
    root VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    file VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    numMo INT UNSIGNED,
    numNode INT UNSIGNED,
        numCachedMo INT UNSIGNED,
        numCachedNode INT UNSIGNED,
    user VARCHAR(12) COLLATE latin1_general_cs,
    filter VARCHAR(12) COLLATE latin1_general_cs,
    error TEXT COLLATE latin1_general_cs,
        INDEX siteIdIdx(siteid)
);

CREATE TABLE pa_import
(
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    start DATETIME NOT NULL,
    end DATETIME NOT NULL,
    pa VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    file VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    numMo INT UNSIGNED,
    error TEXT COLLATE latin1_general_cs,
    INDEX siteIdstart(siteid, start),
    PRIMARY KEY(id)
);

CREATE TABLE pa_import_details
(
    importid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES pa_import(id)",
    moid     SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
    created  SMALLINT UNSIGNED NOT NULL,
    deleted  SMALLINT UNSIGNED NOT NULL,
    updated  SMALLINT UNSIGNED NOT NULL,
    INDEX importIdIdx ( importid )
);



CREATE TABLE pa_activation
(
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 start DATETIME NOT NULL,
 end DATETIME NOT NULL,
 pa VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 result ENUM ('SUCCESS','FAILURE','PARTIAL_FAILURE','NOT_STARTED') COLLATE latin1_general_cs,
 mocount SMALLINT UNSIGNED,
 type ENUM ( 'system', 'pca', 'ne' ) COLLATE latin1_general_cs,
 INDEX idx(siteid,start),
 PRIMARY KEY (id)
);

CREATE TABLE pa_activation_content
(
    actid INT UNSIGNED NOT NULL COMMENT "REFERENCES pa_activation(id)",
    moid     SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
    created  SMALLINT UNSIGNED NOT NULL,
    deleted  SMALLINT UNSIGNED NOT NULL,
    updated  SMALLINT UNSIGNED NOT NULL,
    INDEX actIdIdx ( actid )
);

CREATE TABLE pa_activation_pca
(
    actid INT UNSIGNED NOT NULL COMMENT "REFERENCES pa_activation(id)",
        numActions   SMALLINT UNSIGNED NOT NULL,
    tTotal       SMALLINT UNSIGNED NOT NULL,
    tAlgo        SMALLINT UNSIGNED NOT NULL,
    tReadActions SMALLINT UNSIGNED NOT NULL,
    tUnPlan      SMALLINT UNSIGNED NOT NULL,
        numTxCommit  SMALLINT UNSIGNED NOT NULL,
    tTxCommit    SMALLINT UNSIGNED NOT NULL,
    numJmsSend   SMALLINT UNSIGNED NOT NULL,
    tJmsSend     SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY(actid)
);

CREATE TABLE pa_activation_pca_actions
(
    actid INT UNSIGNED NOT NULL COMMENT "REFERENCES pa_activation(id)",
    action ENUM ( 'CREATE', 'DELETE', 'UPDATE' ) COLLATE latin1_general_cs,
    moid     SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
    tTOTAL   SMALLINT UNSIGNED NOT NULL,
    nTOTAL   SMALLINT UNSIGNED NOT NULL,
    tFINDMO  SMALLINT UNSIGNED NOT NULL,
    nFINDMO  SMALLINT UNSIGNED NOT NULL,
    tCSCALL  SMALLINT UNSIGNED NOT NULL,
    nCSCALL  SMALLINT UNSIGNED NOT NULL,
    tGETPLAN SMALLINT UNSIGNED NOT NULL,
    nGETPLAN SMALLINT UNSIGNED NOT NULL,
    INDEX actIdIdx ( actid )
);

CREATE TABLE pa_activation_nead (
 actid INT UNSIGNED NOT NULL COMMENT "REFERENCES pa_activation(id)",
 neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
 ttotal MEDIUMINT UNSIGNED,
 tread MEDIUMINT UNSIGNED,
 tfinalact MEDIUMINT UNSIGNED,
 tnecommit MEDIUMINT UNSIGNED,
 tnemibtl MEDIUMINT UNSIGNED,
 tnesync MEDIUMINT UNSIGNED,
 tx2prox MEDIUMINT UNSIGNED,
 tnode MEDIUMINT UNSIGNED,
 tcs MEDIUMINT UNSIGNED,
 tother MEDIUMINT UNSIGNED,
 nMoCreated SMALLINT UNSIGNED,
 nMoDeleted SMALLINT UNSIGNED,
 nMoModified SMALLINT UNSIGNED,
 nProxCreated SMALLINT UNSIGNED,
 nProxDeleted SMALLINT UNSIGNED,
 INDEX actIdIdx ( actid )
);

CREATE TABLE process_names
(
        id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
        INDEX nameIdx( name ),
    PRIMARY KEY(id)
);

CREATE TABLE proc_stats
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    procid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES process_names(id)",
    cpu SMALLINT UNSIGNED DEFAULT NULL, -- NULL values for times with no delta (start of day)
    mem MEDIUMINT UNSIGNED NOT NULL,
    thr SMALLINT UNSIGNED NOT NULL,
    fd  SMALLINT UNSIGNED,
    rss MEDIUMINT UNSIGNED,
    nproc TINYINT UNSIGNED NOT NULL DEFAULT 0,
    sample_interval SMALLINT UNSIGNED DEFAULT NULL, -- interval since previous sample. 18 hours max.
    INDEX pidIdx(procid),
    INDEX serverTimeIdx(serverid,time),
    INDEX siteIdIdx(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

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

CREATE TABLE servers
(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    type ENUM ('MASTER',
        'ENIQ', 'ENIQ_COORDINATOR', 'ENIQ_MZ', 'ENIQ_UI', 'ENIQ_IQR', 'ENIQ_IQW', 'ENIQ_ES',
        'ENIQ_CEP', 'NetAnServer', 'SON_COORDINATOR', 'ENIQ_STATS', 'STATS_COORDINATOR', 'STATS_IQR', 'STATS_ENGINE',
        'MATE', 'EBAS', 'EBSS', 'EBSW', 'RPMO', 'SMRS', 'SMRS_SLAVE', 'NESS', 'NEDSS', 'NETSIM', 'PEER',
        'SFS', 'ACCESSNAS', 'TOR', 'TOR_SERVICE_CONTROLLER', 'TOR_MANAGEMENT_SERVER', 'TOR_PAYLOAD', 'OTHER',
        'ENM_SERVICE_HOST', 'ENM_DB_HOST', 'ENM_SCRIPTING_HOST', 'ENM_VM', 'ENM_EVENT_HOST', 'VIRTUALCONNECT',
        'ENM_STREAMING_HOST', 'MONITORING', 'BIS', 'ENM_AUTOMATION_HOST', 'UAS', 'ENM_EBS_HOST', 'ENM_ASR_HOST',
        'ENM_ESN_HOST', 'ESXI', 'OCS_ADDS', 'OCS_CCS', 'OCS_VDA', 'ENM_EBA_HOST', 'K8S_NODE', 'K8S_MASTER', 'WORKLOAD',
        'READER_1','READER_2', 'OCS'
        ) DEFAULT 'MASTER' COLLATE latin1_general_cs,
    hostname VARCHAR(64) COLLATE latin1_general_cs,
    UNIQUE INDEX siteHostIdx (siteid,hostname),
    PRIMARY KEY(id)
);

CREATE TABLE disks
(
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    name VARCHAR(96) NOT NULL COLLATE latin1_general_cs,
        UNIQUE INDEX serverDiskIdx (serverid,name),
    PRIMARY KEY(id)
);

CREATE TABLE disk_stats
(
    date DATE NOT NULL,
    diskid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES disks(id)",
    busy TINYINT UNSIGNED NOT NULL,
    rws SMALLINT UNSIGNED NOT NULL,
    blks SMALLINT UNSIGNED NOT NULL,
    avque DECIMAL(4,1) UNSIGNED NOT NULL,
    avwait DECIMAL(4,1) UNSIGNED NOT NULL,
    avserv DECIMAL(4,1) UNSIGNED NOT NULL
);

CREATE TABLE volumes
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE volume_stats
(
    date DATE NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    volid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES volumes(id)",
    size INT UNSIGNED NOT NULL,
    used INT UNSIGNED NOT NULL,
    INDEX (serverid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE network_interfaces
(
 id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 INDEX nameIdx(name),
 INDEX serverIdx(serverid),
 PRIMARY KEY(id)
);

CREATE TABLE network_interface_ip
(
 date DATE NOT NULL,
 ifid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES network_interfaces(id)",
 ipaddress VARCHAR(64) COLLATE latin1_general_cs,
 isvirtual BOOLEAN,
 INDEX ifIdx(ifid,date)
);

CREATE TABLE network_interface_config
(
    date DATE NOT NULL,
    ifid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES network_interfaces(id)",
    speed SMALLINT UNSIGNED NOT NULL,
    duplex ENUM ( 'half', 'full' ) COLLATE latin1_general_cs,
    drv VARCHAR(64) COLLATE latin1_general_cs,
    fw VARCHAR(64) COLLATE latin1_general_cs,
    INDEX ifIdx(ifid,date)
);

CREATE TABLE network_interface_stats
(
        time DATETIME NOT NULL,
        ifid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES network_interfaces(id)",
    inpkts INT UNSIGNED NOT NULL,
        outpkts INT UNSIGNED NOT NULL,
        INDEX ifIdx(ifid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE nfsd_v3ops (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 null_op MEDIUMINT UNSIGNED,
 getattr MEDIUMINT UNSIGNED,
 setattr MEDIUMINT UNSIGNED,
 lookup MEDIUMINT UNSIGNED,
 access MEDIUMINT UNSIGNED,
 readlink MEDIUMINT UNSIGNED,
 read_op MEDIUMINT UNSIGNED,
 write_op MEDIUMINT UNSIGNED,
 create_op MEDIUMINT UNSIGNED,
 mkdir MEDIUMINT UNSIGNED,
 symlink MEDIUMINT UNSIGNED,
 mknod MEDIUMINT UNSIGNED,
 remove MEDIUMINT UNSIGNED,
 rmdir MEDIUMINT UNSIGNED,
 rename_op MEDIUMINT UNSIGNED,
 link MEDIUMINT UNSIGNED,
 readdir MEDIUMINT UNSIGNED,
 readdirplus MEDIUMINT UNSIGNED,
 fsstat MEDIUMINT UNSIGNED,
 fsinfo MEDIUMINT UNSIGNED,
 pathconf MEDIUMINT UNSIGNED,
 commit_op  MEDIUMINT UNSIGNED,
 INDEX siteIdTimeIdx (siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE nfsd_pool (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 packets_arrived MEDIUMINT NOT NULL,
 sockets_enqueued MEDIUMINT NOT NULL,
 threads_woken MEDIUMINT NOT NULL,
 threads_timedout SMALLINT NOT NULL,
 INDEX siteIdTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

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

CREATE TABLE mc_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE mc_restart_types
(
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE mc_restarts
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mc_names(id)",
    duration SMALLINT UNSIGNED NOT NULL,
        ind_warm_cold ENUM('WARM','COLD') NOT NULL DEFAULT 'COLD' COLLATE latin1_general_cs,
    typeid TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES mc_restart_types(id)",
    restart_reason ENUM('other','upgrade','application','planned','ha-failover') COLLATE latin1_general_cs,
    restart_reason_txt VARCHAR(255) COLLATE latin1_general_cs,
    userid MEDIUMINT UNSIGNED COMMENT "REFERENCES oss_users(id)",
    groupid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mc_names(id)",
    groupstatus ENUM('STANDALONE','GROUP','GROUP_MEMBER') DEFAULT 'STANDALONE' COLLATE latin1_general_cs,
    INDEX timeIdx (time),
    INDEX siteIdx (siteid)
);

CREATE TABLE server_reboots
(
    time DATETIME NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    duration SMALLINT UNSIGNED,
    INDEX servIdIdx (serverid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

#
#
# NEAD stuff
#
CREATE TABLE rns
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE ne_types (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE ne
(
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    rnsid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES rns(id)",
    netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
    name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE ne_sync_success
(
    endtime DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
    numMoCreate MEDIUMINT UNSIGNED NOT NULL,
    numMoDelete MEDIUMINT UNSIGNED NOT NULL,
    numMoRead MEDIUMINT UNSIGNED NOT NULL,
    numMoWrite MEDIUMINT UNSIGNED NOT NULL,
    timeMoCreate SMALLINT UNSIGNED NOT NULL,
    timeMoDelete SMALLINT UNSIGNED NOT NULL,
    timeMoRead SMALLINT UNSIGNED NOT NULL,
    timeMoWrite SMALLINT UNSIGNED NOT NULL,
    timeOther SMALLINT NOT NULL,
    timeTotal SMALLINT NOT NULL,
    timeReadMoMirror SMALLINT NOT NULL,
    timeReadMoNe SMALLINT NOT NULL,
    numTx SMALLINT,
    gcDelta SMALLINT,
    numReadMoNe MEDIUMINT UNSIGNED,
    numReadMoMirror MEDIUMINT UNSIGNED,
    timeFind SMALLINT UNSIGNED,
    numFind MEDIUMINT UNSIGNED,
    timeCommit SMALLINT UNSIGNED,
    timeConvert SMALLINT UNSIGNED,
    timeReadWaitQ SMALLINT UNSIGNED,
    timeWriteWaitQ SMALLINT UNSIGNED,
    ngc SMALLINT UNSIGNED,
        restart BOOLEAN,
        isdelta BOOLEAN DEFAULT FALSE,
    INDEX siteidEndtime (siteid,endtime)
) PARTITION BY RANGE ( TO_DAYS(endtime) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ne_sync_failure_type
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE ne_sync_failure_error
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name TEXT NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);


CREATE TABLE ne_sync_failure
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
    typeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_sync_failure_type(id)",
    errorid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_sync_failure_error(id)",
    count SMALLINT UNSIGNED NOT NULL,
    INDEX siteidDate (siteid,date)
);


CREATE TABLE cms_net_sync
(
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    siteid    SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    starttime DATETIME NOT NULL,
    endtime   DATETIME NOT NULL,
    alive     SMALLINT UNSIGNED NOT NULL,
    synced    SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY(id)
);

CREATE TABLE nead_attrib_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE nead_notifrec
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    eventtype ENUM( 'AVC', 'CREATE', 'DELETE', 'SDN' ) NOT NULL COLLATE latin1_general_cs,
    nodetype ENUM( 'RNC', 'RBS', 'RANAG', 'TDRNC', 'TDRBS', 'ERBS' ) NOT NULL COLLATE latin1_general_cs,
    moid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
    attribid SMALLINT UNSIGNED COMMENT "REFERENCES nead_attrib_names(id)",
    count INT UNSIGNED NOT NULL,
    INDEX myidx (date,siteid)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE nead_notiftop
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
    count MEDIUMINT UNSIGNED NOT NULL,
    INDEX myidx (siteid,date)
);


CREATE TABLE nead_connections
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    conn MEDIUMINT UNSIGNED NOT NULL
);

CREATE TABLE rns_list
(
    date DATE NOT NULL,
    siteid  SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    rnsid   SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES rns(id)",
    numne   SMALLINT UNSIGNED NOT NULL
);

CREATE TABLE swh_failreason
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE swh_activities
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    type ENUM ( 'INSTALL', 'UNINSTALL', 'UPGRADE', 'CONFIRM', 'REVERT', 'UP_FETCH', 'BACKUP', 'RESTORE', 'COPY_CV_TO_FTP', 'REMOVE_CV_FROM_NE', 'REMOVE_CV_FROM_FTP', 'SET_CV_STARTABLE', 'CANCEL' ) NOT NULL COLLATE latin1_general_cs,
    total SMALLINT UNSIGNED NOT NULL,
    success SMALLINT UNSIGNED NOT NULL,
    failed  SMALLINT UNSIGNED NOT NULL,
    indeterminate SMALLINT UNSIGNED NOT NULL,
    netotal SMALLINT UNSIGNED NOT NULL,
    nesuccess SMALLINT UNSIGNED NOT NULL,
    nefailed SMALLINT UNSIGNED NOT NULL,
    neindeterminate SMALLINT UNSIGNED NOT NULL
);

CREATE TABLE swh_nefailures
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    acttype ENUM ( 'INSTALL', 'UNINSTALL', 'UPGRADE', 'CONFIRM', 'REVERT', 'UP_FETCH', 'BACKUP', 'RESTORE', 'COPY_CV_TO_FTP', 'REMOVE_CV_FROM_NE', 'REMOVE_CV_FROM_FTP', 'SET_CV_STARTABLE', 'CANCEL' ) NOT NULL COLLATE latin1_general_cs,
    failreason SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES swh_failreason(id)",
    count SMALLINT UNSIGNED NOT NULL
);



CREATE TABLE smo_activity_name
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE smo_job
(
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    activityid BIGINT UNSIGNED NOT NULL,
    name VARCHAR(32) COLLATE latin1_general_cs,
    typeOfNe VARCHAR(32) COLLATE latin1_general_cs,
    workflow VARCHAR(128) COLLATE latin1_general_cs,
    comment VARCHAR(128) COLLATE latin1_general_cs,
    param VARCHAR(128) COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE smo_execution
(
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    jobid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES smo_job(id)",
    starttime DATETIME,
    stoptime  DATETIME,
    PRIMARY KEY(id)
);

CREATE TABLE smo_ne_name
(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    name VARCHAR(64) COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE smo_activity_result
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE smo_job_ne_detail
(
    exeid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES smo_execution(id)",
    neid INT UNSIGNED NOT NULL COMMENT "REFERENCES smo_ne_name(id)",
    actTypeId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES smo_activity_name(id)",
    starttime DATETIME NOT NULL,
    endtime DATETIME,
    resultid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES smo_activity_result(id)",
    INDEX neActivity(exeid, neid, actTypeId)
);


CREATE TABLE oss_ver_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE oss_ver
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    verid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES oss_ver_names(id)",
    wran_net_size MEDIUMINT UNSIGNED,
    gsm_net_size MEDIUMINT UNSIGNED,
    core_net_size MEDIUMINT UNSIGNED,
    lte_net_size MEDIUMINT UNSIGNED,
    tdran_net_size MEDIUMINT UNSIGNED
);

CREATE TABLE eniq_ver_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE eniq_ver (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    verid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_ver_names(id)"
);

ALTER TABLE oss_ver  ADD INDEX bysite (date,siteid);

CREATE TABLE eniq_events_table_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
        UNIQUE INDEX nameIdx(name),
    PRIMARY KEY(id)
);

CREATE TABLE eniq_events_loaded (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 tableid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_events_table_names(id)",
 numrows INT UNSIGNED NOT NULL,
 INDEX siteIdx(siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_task_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

-- As of ERICddc R2A01, we collect all data from the META_TRANSFER_BATCHES
-- table, not just the Aggregator and Loader data. Therefore we store all this
-- data in a single table and use the eniq_settype_names table to identify the settype

CREATE TABLE eniq_settype_names (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE eniq_meta_transfer_batches (
    time DATETIME not null,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    taskid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_task_names(id)",
    duration SMALLINT UNSIGNED NOT NULL,
    settype TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_settypes(id)",
    status ENUM ("FINISHED","STARTED","FAILED") COLLATE latin1_general_cs,
    INDEX siteidTime (siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE iq_dbspaces (
       date DATE NOT NULL,
       siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
       space ENUM ("IQ_MAIN", "IQ_SYSTEM_MAIN", "IQ_SYSTEM_TEMP" ) NOT NULL,
       size  INT UNSIGNED NOT NULL,
       used  TINYINT UNSIGNED NOT NULL,
       files SMALLINT UNSIGNED NOT NULL,
       INDEX siteiddate(siteid,date)
);

-- Active|                    Main Cache                         |                Temp Cache
-- Users|  Finds   HR%  Reads/Writes  GDirty  Pin% Dirty% InUse%|  Finds   HR%  Reads/Writes  GDirty  Pin% Dirty% InUse%
CREATE TABLE iq_monitor_summary
(
    time DATETIME not null,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    active_users SMALLINT UNSIGNED,
    main_finds  MEDIUMINT UNSIGNED,
    main_hr     DECIMAL(4,1) UNSIGNED,
    main_reads  MEDIUMINT UNSIGNED,
    main_writes MEDIUMINT UNSIGNED,
    main_gdirty MEDIUMINT UNSIGNED,
    main_pin    DECIMAL(4,1) UNSIGNED,
    main_dirty  DECIMAL(4,1) UNSIGNED,
    main_inuse  DECIMAL(4,1) UNSIGNED,
    temp_finds  MEDIUMINT UNSIGNED,
    temp_hr     DECIMAL(4,1) UNSIGNED,
    temp_reads  MEDIUMINT UNSIGNED,
    temp_writes MEDIUMINT UNSIGNED,
    temp_gdirty MEDIUMINT UNSIGNED,
    temp_pin    DECIMAL(4,1) UNSIGNED,
    temp_dirty  DECIMAL(4,1) UNSIGNED,
    temp_inuse  DECIMAL(4,1) UNSIGNED,
    INDEX serverIdTimeIdx (serverid, time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);




CREATE TABLE sdmu_perf
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time_load SMALLINT UNSIGNED NOT NULL,
    time_agg SMALLINT UNSIGNED NOT NULL,
    time_del SMALLINT UNSIGNED NOT NULL
);

CREATE TABLE sdmu_parser
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    xmlfiles  INT UNSIGNED NOT NULL,
    bcpfiles  INT UNSIGNED NOT NULL,
    bcpvolume INT UNSIGNED NOT NULL,
    time_parse SMALLINT UNSIGNED NOT NULL,
    time_map   SMALLINT UNSIGNED NOT NULL,
    time_write SMALLINT UNSIGNED NOT NULL,
    time_bat   SMALLINT UNSIGNED NOT NULL
);

CREATE TABLE cmd_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE cmd_mc
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE cmds
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    mcid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES cmd_mc(id)",
    cmdid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES cmd_names(id)",
    count MEDIUMINT UNSIGNED NOT NULL
);

CREATE TABLE vdb_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE vdb
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    vdbid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES vdb_names(id)",
    vdbvolumeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES vdb_volumes(id)",
    size INT UNSIGNED NOT NULL,
    free INT UNSIGNED NOT NULL,
    percentagefree SMALLINT UNSIGNED,
    pageread INT UNSIGNED,
    pagewrite INT UNSIGNED,
    llogwrite INT UNSIGNED,
    plogwrite INT UNSIGNED,
    lktimeout INT UNSIGNED,
    lkwait INT UNSIGNED,
    txactive SMALLINT UNSIGNED,
    txcommit INT UNSIGNED,
    txrollback INT UNSIGNED,
    hitrate DECIMAL(6,4) UNSIGNED NOT NULL
) PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE model_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE mim_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(30) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE mo_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(216) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE mo
(
    date DATE NOT NULL,
    siteid  SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    vdbid    SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES vdb_names(id)",
    modelid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES model_names(id)",
    mimid   SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mim_names(id)",
    moid    SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
    count   INT UNSIGNED NOT NULL,
    planned INT UNSIGNED,
    INDEX siteidDate (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE cs_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE cs
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    csid   SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES cs_names(id)",
    vdbid  SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES vdb_names(id)",
    tx     INT UNSIGNED NOT NULL
);

CREATE TABLE cslib_confighome_stats (
        time DATETIME not null,
        siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
        nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names(id)",
        ConfigurationManagerCount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PersistenceManagerCount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        OngoingCsTransactionCount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        INDEX siteIdIdx (siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE cslib_vdb_stats (
        time DATETIME not null,
        siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
        nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names(id)",
        PmClosedDuringTx INT UNSIGNED NOT NULL DEFAULT 0,
        PmCreated INT UNSIGNED NOT NULL DEFAULT 0,
        PmIdleInPool INT UNSIGNED NOT NULL DEFAULT 0,
        PmOpen INT UNSIGNED NOT NULL DEFAULT 0,
        TxCommitted INT UNSIGNED NOT NULL DEFAULT 0,
        TxRolledBack INT UNSIGNED NOT NULL DEFAULT 0,
        TxStarted INT UNSIGNED NOT NULL DEFAULT 0,
    TotalOpenedConnections SMALLINT UNSIGNED,
        INDEX siteIdIdx (siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE jmx_names (
        id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(70) NOT NULL COLLATE latin1_general_cs,
        PRIMARY KEY(id)
);

CREATE TABLE me_types
(
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE node_ver
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(30) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE onrm_ne_counts
(
    date DATE NOT NULL,
    siteid  SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    me_typeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES me_types(id)",
    node_verid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES node_ver(id)",
    count   SMALLINT UNSIGNED NOT NULL,
    connected SMALLINT UNSIGNED NOT NULL,
        INDEX (siteid,date)
);

CREATE TABLE fm_failures (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);
INSERT INTO fm_failures (name) VALUES ("Success");

CREATE TABLE fm_obj (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE fm_sync (
       starttime DATETIME NOT NULL,
       siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
       endtime DATETIME NOT NULL,
       objid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES fm_obj(id)",
       result ENUM ('SUCCESS','FAILURE' ) COLLATE latin1_general_cs,
       reason SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES fm_failures(id)",
       received SMALLINT UNSIGNED,
       constructed SMALLINT UNSIGNED,
       cleared SMALLINT UNSIGNED,
       INDEX startSite (starttime,siteid)
)
PARTITION BY RANGE ( TO_DAYS(starttime) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE alarmevents_by_metype
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    me_typeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES me_types(id)",
    event_total MEDIUMINT UNSIGNED NOT NULL,
    active SMALLINT UNSIGNED NOT NULL,
    total  SMALLINT UNSIGNED,
    event_x1 MEDIUMINT UNSIGNED,
    event_x2 MEDIUMINT UNSIGNED,
    event_x3 MEDIUMINT UNSIGNED,
    event_x4 MEDIUMINT UNSIGNED,
    event_x5 MEDIUMINT UNSIGNED
);

CREATE TABLE hires_fm
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    x1 SMALLINT UNSIGNED NOT NULL,
    x2 SMALLINT UNSIGNED NOT NULL,
    x3 SMALLINT UNSIGNED NOT NULL,
    x4 SMALLINT UNSIGNED NOT NULL,
    x5 SMALLINT UNSIGNED NOT NULL,
    avgdelay SMALLINT UNSIGNED NOT NULL,
    maxdelay SMALLINT UNSIGNED NOT NULL,
    INDEX siteIdx(siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ne_mim_ver
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(16) COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE ne_mim
(
    date DATE NOT NULL,
    siteid  SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
    mimid   SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_mim_ver(id)",
    conn    SMALLINT UNSIGNED NOT NULL,
    dis     SMALLINT UNSIGNED,
    never   SMALLINT UNSIGNED,
    INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ne_up_ver
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE ne_up
(
    date DATE NOT NULL,
    siteid  SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
    upid    SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_up_ver(id)",
    numne   SMALLINT UNSIGNED NOT NULL,
    INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE arne_import
(
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    siteid  SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    start DATETIME NOT NULL,
    end   DATETIME NOT NULL,
    PRIMARY KEY(id)
);

CREATE TABLE arne_import_detail
(
    importid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES arne_import(id)",
        plan    INT UNSIGNED,
        created DATETIME NOT NULL,
    modified DATETIME,
    pistart DATETIME,
    piend DATETIME,
    ldap DATETIME,
    utran DATETIME,
    geran DATETIME,
    updated DATETIME,
    deleted DATETIME,
        wmaTRT SMALLINT UNSIGNED,
        wmaTET SMALLINT UNSIGNED,
        wmaTAT SMALLINT UNSIGNED,
        wmaTACT SMALLINT UNSIGNED,
        wmaTVRT SMALLINT UNSIGNED,
        wmaTMRT SMALLINT UNSIGNED,
    INDEX importIdIdx(importid)
);


CREATE TABLE arne_import_content
(
    importid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES arne_import(id)",
        plan    INT UNSIGNED,
    moid    SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
    creates SMALLINT UNSIGNED NOT NULL,
    updates SMALLINT UNSIGNED NOT NULL,
    deletes SMALLINT UNSIGNED NOT NULL,
    INDEX importIdIdx(importid)
);


CREATE TABLE hires_nead_stat
(
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 total        SMALLINT UNSIGNED,
 alive        SMALLINT UNSIGNED,
 synced       SMALLINT UNSIGNED,
 unsynced     SMALLINT UNSIGNED,
 topology     SMALLINT UNSIGNED,
 attribute    SMALLINT UNSIGNED,
 sync_rnc     TINYINT  UNSIGNED,
 sync_rbs     TINYINT  UNSIGNED,
 sync_erbs    TINYINT  UNSIGNED,
 sync_rxi     TINYINT  UNSIGNED,
 n_buff       MEDIUMINT UNSIGNED,
 n_recv       MEDIUMINT UNSIGNED,
 n_discard    MEDIUMINT UNSIGNED,
 n_proc_avg_t MEDIUMINT UNSIGNED,
 n_proc_max_t MEDIUMINT UNSIGNED,
 tp_wait      SMALLINT UNSIGNED,
 tp_exe       SMALLINT UNSIGNED,
 tp2_wait     SMALLINT UNSIGNED,
 tp2_exe      SMALLINT UNSIGNED,
 ping_okay    SMALLINT UNSIGNED,
 ping_fail    SMALLINT UNSIGNED,
 ping_avg_t   MEDIUMINT UNSIGNED,
 ping_max_t   MEDIUMINT UNSIGNED,
 nesu_wait    SMALLINT UNSIGNED,
 nesu_exe     SMALLINT UNSIGNED,
 notifnodes   SMALLINT UNSIGNED,
 num_me_writes SMALLINT UNSIGNED,
 max_me_q SMALLINT UNSIGNED,
 avg_me_write_delay MEDIUMINT UNSIGNED,
 INDEX siteTime (siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE hires_server_stat
(
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 user     TINYINT UNSIGNED,
 sys      TINYINT UNSIGNED,
 iowait   TINYINT UNSIGNED,
 steal    TINYINT UNSIGNED,
 guest    TINYINT UNSIGNED,
 freeram  INT UNSIGNED,
 freeswap INT UNSIGNED,
 numproc  SMALLINT UNSIGNED,
 proc_s   SMALLINT UNSIGNED,
 pgscan   SMALLINT UNSIGNED,
 runq SMALLINT UNSIGNED,
 -- the following values are only applicable to Linux systems
 memused  INT UNSIGNED,
 membuffers INT UNSIGNED,
 memcached INT UNSIGNED,
 INDEX myidx (serverid,time),
 INDEX siteIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) ) (
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE hires_disk_stat_old
(
    time DATETIME NOT NULL,
    diskid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES disks(id)",
    busy TINYINT UNSIGNED NOT NULL,
    rws INT UNSIGNED NOT NULL,
    blks INT UNSIGNED NOT NULL,
    readblks INT UNSIGNED,
    avque DECIMAL(4,1) UNSIGNED NOT NULL,
    avwait DECIMAL(5,1) UNSIGNED NOT NULL,
    avserv DECIMAL(5,1) UNSIGNED NOT NULL,
    INDEX myidx (diskid,time)
) PARTITION BY RANGE ( TO_DAYS(time) ) (
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE hires_disk_stat
(
 time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  diskid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES disks(id)",
  busy TINYINT UNSIGNED NOT NULL,
  rws INT UNSIGNED NOT NULL,
  blks INT UNSIGNED NOT NULL,
  readblks INT UNSIGNED,
  avque DECIMAL(4,1) UNSIGNED NOT NULL,
  avwait DECIMAL(5,1) UNSIGNED NOT NULL,
  avserv DECIMAL(5,1) UNSIGNED NOT NULL,
  INDEX siteIdIdx (siteid,time),
  INDEX srvIdIdx (serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) ) (
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE snad_instr (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 mastersInCache INT UNSIGNED,
 proxiesInCache INT UNSIGNED,
 mosInTempArea INT UNSIGNED,
 discoveredRncs INT UNSIGNED,
 recoveringRncs INT UNSIGNED,
 snRecoveryMoCount INT UNSIGNED,
 mosCheckedSoFar INT UNSIGNED,
 sleepyNotificationQueueSize INT UNSIGNED,
 sleepyNotificationQueueInactiveSize INT UNSIGNED,
 sleepyNotificationQueueActiveSize INT UNSIGNED,
 totalNotificationsReceived INT UNSIGNED,
 INDEX (siteid)
)  PARTITION BY RANGE ( TO_DAYS(time) ) (
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE snad_cc
(
    start DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    end   DATETIME NOT NULL,
    status ENUM('false','true') COLLATE latin1_general_cs,
    pc_status TINYINT UNSIGNED NULL,  -- OSS-RC 12 WP00558: CR 862/109 18-FCP 103 8147/13 A [See migrate.sql, BG 2011-12-15]
    numcheck INT UNSIGNED,
    numchecked INT UNSIGNED,
    consist INT UNSIGNED,
    inconsist INT UNSIGNED,
    missing INT UNSIGNED,
    multiple INT UNSIGNED,
    findmo_num INT UNSIGNED,
    findmo_time INT UNSIGNED,
    modifymo_num INT UNSIGNED,
    modifymo_time INT UNSIGNED,
    createmo_num INT UNSIGNED,
    createmo_time INT UNSIGNED,
    deletemo_num INT UNSIGNED,
    deletemo_time INT UNSIGNED,
    starttx_num INT UNSIGNED,
    starttx_time INT UNSIGNED,
    rollbacktx_num INT UNSIGNED,
    rollbacktx_time INT UNSIGNED,
    committx_num INT UNSIGNED,
    committx_time INT UNSIGNED,
        INDEX bysites (siteid,start)
);

CREATE TABLE halog_resources (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE halog_groups (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE halog_status (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE halog_cmdnames (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);


CREATE TABLE oss_users (
    -- MEDIUMINT: 65535 is too small, 16 mill. should be enough
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL DEFAULT "" COLLATE latin1_general_cs
);
INSERT INTO oss_users (name) VALUES ("");

CREATE TABLE halog_events (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    resource SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES halog_resources(id)",
    restype ENUM('G','R') NOT NULL COLLATE latin1_general_cs, -- G == HA Group ; R == HA Resource
    status SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES halog_status",
    reason VARCHAR(255) NOT NULL DEFAULT "" COLLATE latin1_general_cs,
    owner MEDIUMINT UNSIGNED COMMENT "REFERENCES oss_users(id)",
    grp SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES halog_groups(id)",
    time DATETIME NOT NULL,
    host INT UNSIGNED COMMENT "REFERENCES servers(id)",
    INDEX(siteid),
    INDEX(time)
);

CREATE TABLE halog_cmds (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    resource SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES halog_resources(id)",
    restype ENUM('G','R') NOT NULL COLLATE latin1_general_cs, -- G == HA Group ; R == HA Resource
    cmd SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES halog_cmdnames(id)",
    user MEDIUMINT UNSIGNED COMMENT "REFERENCES oss_users(id)",
    time DATETIME NOT NULL,
    host INT UNSIGNED COMMENT "REFERENCES servers(id)",
    INDEX(siteid),
    INDEX(time)
);

CREATE TABLE eba_mdc (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    begin_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    granularity TINYINT NOT NULL,
    neun ENUM('rpmo','ebsw','ebss') NOT NULL COLLATE latin1_general_cs,
    nedn VARCHAR(20) NOT NULL COLLATE latin1_general_cs,
    INDEX siteidBeginTimeNeun (siteid,begin_time,neun)
);

CREATE TABLE eba_moid (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) COLLATE latin1_general_cs
);

--
-- Below are the initial schemas for the EBA
-- tables. As stats get uploaded the tables
-- will be altered to add extra columns to these
-- tables as new counters are encountered. To
-- get an up-to-date picture of these tables
-- a DESCRIBE query should be run on the server
-- in question.
--

CREATE TABLE eba_ebss (
    mdc_id INT UNSIGNED NOT NULL COMMENT "REFERENCES eba_mdc(id)",
    INDEX mdcId (mdc_id)
);

CREATE TABLE eba_ebss_sgsn (
    mdc_id INT UNSIGNED NOT NULL COMMENT "REFERENCES eba_mdc(id)",
    moid_id INT UNSIGNED COMMENT "REFERENCES eba_moid(id)",
    INDEX mdcId (mdc_id)
);

CREATE TABLE eba_ebsw (
    mdc_id INT UNSIGNED NOT NULL COMMENT "REFERENCES eba_mdc(id)",
    INDEX mdcId (mdc_id)
);

CREATE TABLE eba_ebsw_rnc (
    mdc_id INT UNSIGNED NOT NULL COMMENT "REFERENCES eba_mdc(id)",
    moid_id INT UNSIGNED COMMENT "REFERENCES eba_moid(id)",
    INDEX mdcId (mdc_id)
);

CREATE TABLE eba_rpmo (
    mdc_id INT UNSIGNED NOT NULL COMMENT "REFERENCES eba_mdc(id)",
    INDEX mdcId (mdc_id)
);

CREATE TABLE eba_rpmo_bsc (
    mdc_id INT UNSIGNED NOT NULL COMMENT "REFERENCES eba_mdc(id)",
    moid_id INT UNSIGNED COMMENT "REFERENCES eba_moid(id)",
    INDEX mdcId (mdc_id)
);

--
-- The gran_cm_activities table is used to store data
-- about the utilisation of the CNA and BSM functions.
-- Data stored originates in log files which follow the
-- format described in 1/19818-3/AOM 901 048 Rev. C
--
CREATE TABLE gran_cm_activities (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    start DATETIME NOT NULL,
    end DATETIME NOT NULL,
    activity ENUM('cna_adjust','cna_update','cna_cc','cna_ccheck','cna_import','cna_export','cnai_import','cnai_export','bsm_adjust_controller','bsm_import','bsm_export') NOT NULL COLLATE latin1_general_cs,
    args VARCHAR(255) NOT NULL DEFAULT "" COLLATE latin1_general_cs,
    status ENUM('OK','FAIL', 'INCOMPLETE') NOT NULL DEFAULT 'INCOMPLETE' COLLATE latin1_general_cs,
    reason VARCHAR(255) NOT NULL DEFAULT "" COLLATE latin1_general_cs,
    userid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES oss_users.id",
    pid SMALLINT UNSIGNED NOT NULL,
    startMillis INT UNSIGNED NOT NULL,
    INDEX siteStart (siteid,start),
    UNIQUE INDEX startMillisUserPid (startMillis,userid,pid)
);

--
-- The sdm_load table is used to store information about
-- SDM data loaded as retrieved from the LOAD files generated
-- by SDM. The sdm_delete table stores corresponding delete
-- information. The data stored originates in log files
-- generated as a result of 38/159 41-FCP 103 6749 Rev. A
--
CREATE TABLE sdm_load (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    objectid TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES sdm_objects(id)",
    sample_time DATETIME NOT NULL,
    num_nodes SMALLINT UNSIGNED,
    duration SMALLINT UNSIGNED NOT NULL,
    UNIQUE INDEX siteidObjectidSampleTime (siteid,objectid,sample_time)
);

CREATE TABLE sdm_delete (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    objectid TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES sdm_objects(id)",
    start_time DATETIME NOT NULL,
    duration SMALLINT UNSIGNED NOT NULL,
    UNIQUE INDEX siteidObjectidStartTime (siteid,objectid,start_time)
);

CREATE TABLE sdm_objects (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE crontabs (
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 process_name_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES process_names(id)",
 date DATE NOT NULL,
 execs SMALLINT NOT NULL,
 INDEX serverIdStartTime (serverid, date),
 INDEX fixProcNameIdx( process_name_id )
) PARTITION BY RANGE ( TO_DAYS(date) ) (
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- Tables to store the PM metrics collected
-- by the single_instr run - these are SGw, PDM, PDM_SNMP,
-- SMIA, as defined in 38/159 41-FCP 103 6749 Rev. A and
-- 1/102 62-52/FCP 103 6749 Rev. F

-- ### N.B. ###
-- When adding columns to these instr tables for new parameters, remember that
-- their data may not be collected from machines that are on earlier OSS
-- releases, and shoudln't be constantly displayed as empty for those machines.
-- So, make sure to default them to NULL so we know they are not present
-- in the version of the OSS we are currently processing...

CREATE TABLE sgw_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    actNumOfFilesCollectedMSC INT UNSIGNED NOT NULL DEFAULT 0,
    totDataVolCollectedMSC INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenToCollectMSC INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenToCollectMSC INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesForNorthboundMSC INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenForNorthboundMSC INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenForNorthboundMSC INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesForBcpMSC INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenForBcpMSC INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenForBcpMSC INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesCollectedBSC INT UNSIGNED NOT NULL DEFAULT 0,
    totDataVolCollectedBSC INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenToCollectBSC INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenToCollectBSC INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesForNorthboundBSC INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenForNorthboundBSC INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenForNorthboundBSC INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesForBcpBSC INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenForBcpBSC INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenForBcpBSC INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesCollectedGEN_AXE INT UNSIGNED NOT NULL DEFAULT 0,
    totDataVolCollectedGEN_AXE INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenToCollectGEN_AXE INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenToCollectGEN_AXE INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesForNorthboundGEN_AXE INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenForNorthboundGEN_AXE INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenForNorthboundGEN_AXE INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesForBcpGEN_AXE INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenForBcpGEN_AXE INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenForBcpGEN_AXE INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesCollectedMGw INT UNSIGNED NOT NULL DEFAULT 0,
    totDataVolCollectedMGw INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenToCollectMGw INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenToCollectMGw INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesForNorthboundMGw INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenForNorthboundMGw INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenForNorthboundMGw INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesForBcpMGw INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenForBcpMGw INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenForBcpMGw INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesCollectedSGSN INT UNSIGNED NOT NULL DEFAULT 0,
    totDataVolCollectedSGSN INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenToCollectSGSN INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenToCollectSGSN INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesForNorthboundSGSN INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenForNorthboundSGSN INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenForNorthboundSGSN INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesForBcpSGSN INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenForBcpSGSN INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenForBcpSGSN INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfFilesCollectedMME INT UNSIGNED DEFAULT NULL,
    totDataVolCollectedMME INT UNSIGNED DEFAULT NULL,
    avgTimeTakenToCollectMME INT UNSIGNED DEFAULT NULL,
    maxTimeTakenForNorthboundSTN INT UNSIGNED DEFAULT NULL,
    totTimeTakenToCollectSTN INT UNSIGNED DEFAULT NULL,
    totDataVolCollectedSTN INT UNSIGNED DEFAULT NULL,
    actNumOfFilesCollectedSTN INT UNSIGNED DEFAULT NULL,
    actNumOfSymLinksSTN INT UNSIGNED DEFAULT NULL,
    actNumOfFilesColCpgUeTrace INT UNSIGNED DEFAULT NULL,
    totDataVolColCpgUeTrace INT UNSIGNED DEFAULT NULL,
    totTimeToColCpgUeTrace INT UNSIGNED DEFAULT NULL,
    maxTimeToColCpgUeTrace INT UNSIGNED DEFAULT NULL,
    actNumOfFilesForNorthboundSTN INT UNSIGNED DEFAULT NULL,
    actNumOfDataFilesCollectedSTN INT UNSIGNED DEFAULT NULL,
    maxTimeToCollectNorthboundSTN INT UNSIGNED DEFAULT NULL,
    totTimeToCollectNorthboundSTN INT UNSIGNED DEFAULT NULL,
    maxTimeToCollectSTN INT UNSIGNED DEFAULT NULL,
    totTimeToCollectSTN INT UNSIGNED DEFAULT NULL,
    INDEX siteIdDate (siteid,date)
);

CREATE TABLE smia_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    MSCSts INT UNSIGNED NOT NULL DEFAULT 0,
    MSCOms INT UNSIGNED NOT NULL DEFAULT 0,
    GEN_AXESts INT UNSIGNED NOT NULL DEFAULT 0,
    GEN_AXEOms INT UNSIGNED NOT NULL DEFAULT 0,
    BSCSts INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX siteIdDate (siteid,date)
);

CREATE TABLE pdm_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    actNumOfFilesCollectedSASN INT UNSIGNED NOT NULL DEFAULT 0,
    totDataVolCollectedSASN INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenToCollectSASN INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenToCollectSASN INT UNSIGNED NOT NULL DEFAULT 0,
    -- new parameters are null so we know they are not present in the version
    -- of the OSS we are currently processing...
    actNumOfFilesCollectedMLPPP INT UNSIGNED DEFAULT NULL,
    totDataVolCollectedMLPPP INT UNSIGNED DEFAULT NULL,
    actNumOfFilesCollectedEdgeRouter INT UNSIGNED DEFAULT NULL,
    totDataVolCollectedEdgeRouter INT UNSIGNED DEFAULT NULL,
    actNumOfFilesCollectedSAEGW INT UNSIGNED DEFAULT NULL,
    totDataVolCollectedSAEGW INT UNSIGNED DEFAULT NULL,
    actNumOfFilesCollectedSmartMetro INT UNSIGNED DEFAULT NULL,
    totDataVolCollectedSmartMetro INT UNSIGNED DEFAULT NULL,
    INDEX siteIdDate (siteid,date)
);
CREATE TABLE pdm_snmp_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    actNumOfXmlGenSNMP INT UNSIGNED NOT NULL DEFAULT 0,
    totDataVolXmlGenSNMP INT UNSIGNED NOT NULL DEFAULT 0,
    avgTimeTakenXmlGenSNMP INT UNSIGNED NOT NULL DEFAULT 0,
    maxTimeTakenXmlGenSNMP INT UNSIGNED NOT NULL DEFAULT 0,
    actNumOfBcpGenSNMP INT UNSIGNED DEFAULT NULL,
    avgTimeTakenBcpGenSNMP INT UNSIGNED DEFAULT NULL,
    maxTimeTakenBcpGenSNMP INT UNSIGNED DEFAULT NULL,
    INDEX siteIdDate (siteid,date)
);

-- BEGIN SYBASE MDA DATA
CREATE TABLE sybase_mon_pcache_total (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    inserttime DATETIME,
    MemUsageKB INT UNSIGNED,
    CacheSizeKB INT UNSIGNED,
    UNIQUE INDEX siteIdTime (siteid, inserttime)
);

CREATE TABLE sybase_pcache_objtypes (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT "" COLLATE latin1_general_cs
);

CREATE TABLE sybase_mon_pcache_obj (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    inserttime DATETIME,
    objtypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_pcache_objtypes(id)",
    MemUsageKB INT UNSIGNED,
    UNIQUE INDEX siteIdTimeObj (siteid, inserttime, objtypeid)
);

CREATE TABLE sybase_mon_pcache_db (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    inserttime DATETIME,
    dbname SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_dbnames(id)",
    MemUsageKB INT UNSIGNED,
    UNIQUE INDEX siteIdTimeDB (siteid, inserttime, dbname)
);

CREATE TABLE sybase_procedure_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT "" COLLATE latin1_general_cs
);

CREATE TABLE sybase_mon_pstatements (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    inserttime DATETIME,
    dbname SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_dbnames(id)",
    procedure_name SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_procedure_names(id)",
    LineNumber INT UNSIGNED NOT NULL,
    AvgElapsedMS INT UNSIGNED NOT NULL,
    GlobalAvgElapsedMS INT UNSIGNED NOT NULL,
    UNIQUE INDEX siteIdTimeDB (siteid, inserttime, dbname)
);

CREATE TABLE sybase_sqltext (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    text VARCHAR(255) NOT NULL DEFAULT "" COLLATE latin1_general_cs
);

CREATE TABLE sybase_mon_tobjects (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    inserttime DATETIME,
    SPID INT UNSIGNED NOT NULL,
    KPID INT UNSIGNED NOT NULL,
    Login SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_users(id)",
    TempdbName SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_dbnames(id)",
    TempdbUsageKB INT UNSIGNED,
    TempdbUsagePerCent FLOAT(5,2) UNSIGNED,
    LineNumber SMALLINT UNSIGNED,
    SequenceInLine SMALLINT UNSIGNED,
    SQLTextID INT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_sqltext(id)",
    BatchID SMALLINT UNSIGNED,
    TempObjNumber SMALLINT UNSIGNED,
    INDEX siteIdTime (siteid, inserttime)
);

CREATE TABLE sybase_mon_topcpu (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    inserttime DATETIME,
    SPID INT UNSIGNED NOT NULL,
    KPID INT UNSIGNED NOT NULL,
    Login SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_users(id)",
    CPUTimePct TINYINT UNSIGNED,
    CPUTime INT UNSIGNED,
    StartTime DATETIME,
    EndTime DATETIME,
    ElapsedTime INT UNSIGNED,
    SequenceInBatch INT UNSIGNED,
    SQLTextID INT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_sqltext(id)",
    BatchID SMALLINT UNSIGNED,
    INDEX siteIdTime (siteid, inserttime)
);

CREATE TABLE sybase_mon_topio (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    inserttime DATETIME,     SPID INT UNSIGNED NOT NULL,
    KPID INT UNSIGNED NOT NULL,
    Login SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_users(id)",
    IOPerCent TINYINT UNSIGNED,
    LogicalReads INT UNSIGNED,
    PhysicalReads INT UNSIGNED,
    PagesModified INT UNSIGNED,
    StartTime DATETIME,
    EndTime DATETIME,
    ElapsedTime INT UNSIGNED,
    SequenceInBatch INT UNSIGNED,
    SQLTextID INT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_sqltext(id)",
    BatchID SMALLINT UNSIGNED,
    INDEX siteIdTime (siteid, inserttime)
);

CREATE TABLE sybase_table_names (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT "" COLLATE latin1_general_cs
);

CREATE TABLE sybase_mon_tusage (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    inserttime DATETIME,
    dbname SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_dbnames(id)",
    tablename INT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_table_names(id)",
    LogicalReads INT UNSIGNED,
    PhysicalReads INT UNSIGNED,
    PhysicalWrites INT UNSIGNED,
    IOTotal INT UNSIGNED,
    RowsUpdated INT UNSIGNED,
    RowsInserted INT UNSIGNED,
    RowsDeleted INT UNSIGNED,
    UsedCount INT UNSIGNED,
    LockWaits INT UNSIGNED,
    INDEX siteIdTime (siteid, inserttime)
);

CREATE TABLE sybase_info_text (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    text VARCHAR(255) NOT NULL DEFAULT "" COLLATE latin1_general_cs
);

CREATE TABLE sybase_mon_waits (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    inserttime DATETIME,
    WaitEventID INT UNSIGNED,
    WaitsNumber INT UNSIGNED,
    WaitTime INT UNSIGNED,
    TotalWaitPerCent TINYINT UNSIGNED,
    ClassInfo INT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_info_text(id)",
    EventInfo INT UNSIGNED NOT NULL COMMENT "REFERENCES sybase_info_text(id)",
    INDEX siteIdTime (siteid, inserttime)
);

-- END SYBASE MDA DATA

-- BEGIN JOB MANAGER DATA
CREATE TABLE job_mgr_jobs (
  date DATE NOT NULL ,
  siteid SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES sites (id ) ",
  sched_jobs SMALLINT(5) UNSIGNED NULL ,
  completed_jobs SMALLINT(5) UNSIGNED NULL ,
  terminated_jobs SMALLINT(5) UNSIGNED NULL ,
  failed_jobs SMALLINT(5) UNSIGNED NULL ,
  INDEX siteid (siteid)
  );

CREATE TABLE job_mgr_scriptnames (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) COLLATE latin1_general_cs,
    INDEX nameIdx(name)
);

CREATE TABLE job_mgr_complexity_data (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  jobcatgid MEDIUMINT UNSIGNED NOT NULL ,
  scriptid SMALLINT UNSIGNED NOT NULL,
  jobname      VARCHAR(120) NOT NULL COLLATE latin1_general_cs,
  activityname VARCHAR(120) NOT NULL COLLATE latin1_general_cs,
  userid       MEDIUMINT(8) UNSIGNED NOT NULL COMMENT "REFERENCES oss_users (id ) ",
  createdtime DATETIME NULL ,
  INDEX parseJmIdx(scriptid,jobcatgid,userid,createdtime),
  PRIMARY KEY (id)
);

CREATE TABLE job_mgr_complexity (
  date DATE NOT NULL ,
  jobcomplexid INT UNSIGNED NOT NULL COMMENT "REFERENCES job_mgr_complexity_data (id ) ",
  siteid SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES sites (id ) ",
  INDEX siteid (siteid ASC) ,
  INDEX jobcomplexid (jobcomplexid)
);

CREATE TABLE job_mgr_supervisor_data (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT ,
  jobid SMALLINT UNSIGNED NOT NULL ,
  jobcatgid MEDIUMINT UNSIGNED NOT NULL ,
  jobname VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
  status ENUM('Completed','Planned','Terminated','Scheduled','Failed','Starting','Started') NOT NULL COLLATE latin1_general_cs,
  frequency ENUM('PERIODIC','NON_PERIODIC') NOT NULL COLLATE latin1_general_cs,
  userid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES oss_users (id)",
  jobschedtime DATETIME NULL ,
  jobstarttime DATETIME NULL ,
  jobstoptime DATETIME NULL ,
  INDEX jobIdx(jobid,jobcatgid),
  PRIMARY KEY (id)
  );

CREATE TABLE job_mgr_supervisor (
  date DATE NOT NULL ,
  jobsuperid INT UNSIGNED NOT NULL COMMENT "REFERENCES job_mgr_supervisor_data (id ) ",
  siteid SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES sites (id ) ",
  INDEX siteid (siteid ASC) ,
  INDEX jobsuperid (jobsuperid)
  );
-- END JOB MANAGER DATA

-- BEGIN GENERIC JMX DATA
-- HO71175 - Add new serverid column [2011-12-16 eronkeo]
CREATE TABLE generic_jmx_stats (
  time DATETIME NOT NULL ,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id )",
  serverid INT UNSIGNED NULL COMMENT "REFERENCES servers (id )",
  nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names (id ) ",
  hp_committed SMALLINT UNSIGNED NULL ,
  hp_init SMALLINT UNSIGNED NULL ,
  hp_max SMALLINT UNSIGNED NULL ,
  hp_used SMALLINT UNSIGNED NULL ,
  nh_committed SMALLINT UNSIGNED NULL ,
  nh_init SMALLINT UNSIGNED NULL ,
  nh_max SMALLINT UNSIGNED NULL ,
  nh_used SMALLINT UNSIGNED NULL ,
  nio_mem_direct SMALLINT UNSIGNED NULL,
  nio_mem_mapped MEDIUMINT UNSIGNED NULL,
  threadcount SMALLINT UNSIGNED NULL ,
  peakthreadcount SMALLINT UNSIGNED NULL,
  cputime SMALLINT UNSIGNED NULL,
  gc_youngcount SMALLINT UNSIGNED NULL,
  gc_youngtime SMALLINT UNSIGNED NULL,
  gc_oldcount SMALLINT UNSIGNED NULL,
  gc_oldtime SMALLINT UNSIGNED NULL,
  fd SMALLINT UNSIGNED NULL,
  INDEX siteTimeIdx(siteid,time),
  INDEX serverTimeIdx(serverid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sum_generic_jmx_stats (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED NULL COMMENT "REFERENCES servers (id)",
 nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names (id)",
 cputime MEDIUMINT UNSIGNED NULL,
 gc_youngtime MEDIUMINT UNSIGNED NULL,
 gc_oldtime MEDIUMINT UNSIGNED NULL,
 threadcount SMALLINT UNSIGNED NULL,
 fd SMALLINT UNSIGNED NULL,
 INDEX siteDateIdx(siteid,date)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE jvm_lr (
  time DATETIME NOT NULL ,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id )",
  serverid INT UNSIGNED NULL COMMENT "REFERENCES servers (id )",
  nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names (id ) ",
  cc_committed SMALLINT UNSIGNED NULL ,
  cc_used SMALLINT UNSIGNED NULL ,
  ccs_committed SMALLINT UNSIGNED NULL ,
  ccs_used SMALLINT UNSIGNED NULL ,
  meta_committed SMALLINT UNSIGNED NULL ,
  meta_used SMALLINT UNSIGNED NULL ,
  t_compilation SMALLINT UNSIGNED NULL,
  INDEX siteTimeIdx(siteid,time),
  INDEX serverTimeIdx(serverid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- END GENERIC JMX DATA

-- BEGIN wran RBS Reparent
CREATE TABLE wran_rbs_reparent
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    src_rns SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES rns(id)",
    dest_rns SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES rns(id)",
    count SMALLINT UNSIGNED NOT NULL,
    INDEX siteIdDate (siteid, date)
);
-- END wran RBS Reparent

CREATE TABLE gen_meas_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    grp VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE gen_measurements
(
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 mid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES gen_meas_name(id)",
 value INT UNSIGNED NOT NULL,
 INDEX idx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sql_plot_param
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    param TEXT NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

-- cs export improvements - JMX metrics
-- see 26/159 41-FCP 103 8147 Rev. B
-- some metrics may require redimensioning

CREATE TABLE cslib_filteredexport_all (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names(id)",
    baseMOsExported INT UNSIGNED,               -- public Long getBaseMosExported();
    baseMOsHandled INT UNSIGNED,                -- public Long getBaseMosHandled();
    baseMOsSkipped INT UNSIGNED,                -- public Long getBaseMosSkipped();
    baseMOsSuccessPercentage SMALLINT UNSIGNED, -- public Short getBaseMoSuccessPercentage();
    consumerBlocked INT UNSIGNED,               -- public Integer getConsumerBlocked();
    dbObjectsRead INT UNSIGNED,                 -- public Long getDbObjectsRead();
    dbReadRate INT UNSIGNED,                    -- public Integer getDbReadRate();
    errors INT UNSIGNED,                        -- public Long getErrors();
    exportRate INT UNSIGNED,                    -- public Integer getExportRate();
    filteredByConfigurationFilter INT UNSIGNED, -- public Long getFilteredByConfigurationFilter();
    filteredByMoTypeFilter INT UNSIGNED,        -- public Long getFilteredByMoTypeFilter();
    filteredByMoTypePathFilter INT UNSIGNED,    -- public Long getFilteredByMoTypePathFilter();
    filteredPercentage SMALLINT UNSIGNED,       -- public Integer getFilteredPercentage();
    numberOfExports INT UNSIGNED,               -- public Integer getNumberOfExports();
    producerBlocked INT UNSIGNED                -- public Integer getProducerBlocked();
);

CREATE TABLE cslib_filteredexport_last (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names(id)",
    baseMOsExported INT UNSIGNED, -- public Long getBaseMosExported();
    baseMOsHandled INT UNSIGNED,  -- public Long getBaseMosHandled();
    baseMOsSkipped INT UNSIGNED,  -- public Long getBaseMosSkipped();
    baseMOsSuccessPercentage SMALLINT UNSIGNED,                           -- public Short getBaseMoSuccessPercentage();
    consumerBlocked INT UNSIGNED,                                         -- public Integer getConsumerBlocked();
    dbObjectsRead INT UNSIGNED,                                           -- public Long getDbObjectsRead();
    dbReadRate INT UNSIGNED,                                              -- public Integer getDbReadRate();
    dbReadTime INT UNSIGNED,                                              -- public String getDbReadTime();
    errors INT UNSIGNED,                                                  -- public Long getErrors();
    exportRate INT UNSIGNED,                                              -- public Integer getExportRate();
    exportStarted VARCHAR(100) COLLATE latin1_general_cs,                 -- public String getExportStarted();
    exportTime VARCHAR(100) COLLATE latin1_general_cs,                    -- public String getExportTime();
    exportType VARCHAR(100) COLLATE latin1_general_cs,                    -- public String getExportType();
    filteredByConfigurationFilter INT UNSIGNED,                           -- public Long getFilteredByConfigurationFilter();
    filteredByMoTypeFilter INT UNSIGNED,                                  -- public Long getFilteredByMoTypeFilter();
    filteredByMoTypePathFilter INT UNSIGNED,                              -- public Long getFilteredByMoTypePathFilter();
    filteredPercentage SMALLINT UNSIGNED,                                 -- public Short getFilteredPercentage();
    -- jdoQuery,
    -- postProcessConfiguration,
    -- postProcessPath,
    -- postProcessType,
    producerBlocked INT UNSIGNED                                          -- public Integer getProducerBlocked();
);

CREATE TABLE cslib_filteredexport_configuration (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names(id)",
    CS_FILTERED_EXPORT_MAX_QUEUE_SIZE INT UNSIGNED,              -- public Integer getMaxQueueSize();
    CS_FILTERED_EXPORT_MO_BATCH_SIZE INT UNSIGNED,               -- public Integer getMoBatchSize();
    CS_FILTERED_EXPORT_POST_PROCESS_VALID_CONFIGURATION BOOLEAN, -- public Boolean getPostProcessValidConfiguration();
    CS_FILTERED_EXPORT_TYPE_QUERY_THRESHOLD INT UNSIGNED         -- public Integer getTypeQueryThreshold();
);

CREATE TABLE system_startstop
(
 id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
 begintime DATETIME NOT NULL,
 endtime DATETIME NULL NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 type ENUM( 'START', 'SHUTDOWN' ) NOT NULL COLLATE latin1_general_cs,
 INDEX sitetimeIdx(begintime,siteid),
 PRIMARY KEY(id)
);

CREATE TABLE system_startstop_details
(
  ssid MEDIUMINT UNSIGNED COMMENT "REFERENCES system_startstop(id)",
  eventtime DATETIME NOT NULL,
  mcid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mc_names(id)",
  orderval TINYINT UNSIGNED NOT NULL,
  eventduration SMALLINT UNSIGNED NOT NULL,
  INDEX ssIdIdx(ssId)
);

-- Tables to store the OPS Instrumentation
-- XXX: adding a second index to deal with the way the site_index page
-- is loaded.
CREATE TABLE ops_instrumentation (
    siteid SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    ops_script_id MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES process_names(id)",
    userid MEDIUMINT(8) UNSIGNED NOT NULL COMMENT "REFERENCES oss_users(id)",
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    cpuusage DECIMAL(7,2) UNSIGNED NOT NULL,
    INDEX siteIdStartTime (siteid, start_time),
    INDEX siteIdEndTime (siteid, end_time)
) PARTITION BY RANGE ( TO_DAYS(start_time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ops_scriptnames
(
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

-- Tables to store CHA Insturmentation as per 199/15941-FCP1038147 Rev. C
CREATE TABLE cha_cmd_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE cha_instrumentation (
    cmdid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES cha_cmd_names(id)",
    siteid SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    uid MEDIUMINT(8) UNSIGNED NOT NULL COMMENT "REFERENCES oss_users(id)",
    cmdtype ENUM('COMMAND','SYSTEM_COMMAND') NOT NULL COLLATE latin1_general_cs,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    result VARCHAR(10) NOT NULL COLLATE latin1_general_cs,
    prMem SMALLINT UNSIGNED NOT NULL,
    rssMem SMALLINT UNSIGNED NOT NULL,
    cpuusage SMALLINT UNSIGNED NOT NULL,
    INDEX siteIdEndTime (siteid, end_time),
    INDEX uidIndex (uid),
    INDEX cmdIndex (cmdid),
    INDEX typeIndex (cmdtype)
);

CREATE TABLE nic_stat_old (
    nicid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES network_interfaces(id)",
    time DATETIME NOT NULL,
    ibytes_per_sec INT UNSIGNED NOT NULL,
    obytes_per_sec INT UNSIGNED NOT NULL,
    ipkts_per_sec INT UNSIGNED,
    opkts_per_sec INT UNSIGNED,
    INDEX nicIdTime (nicid, time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE nic_stat (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 nicid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES network_interfaces(id)",
 time DATETIME NOT NULL,
 ibytes_per_sec INT UNSIGNED NOT NULL,
 obytes_per_sec INT UNSIGNED NOT NULL,
 ipkts_per_sec INT UNSIGNED,
 opkts_per_sec INT UNSIGNED,
 INDEX srvIdTime (serverid, time),
 INDEX siteIdTime (siteid, time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sum_nic_stat (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  nicid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES network_interfaces(id)",
  date DATETIME NOT NULL,
  ibytes_per_sec INT UNSIGNED NOT NULL,
  obytes_per_sec INT UNSIGNED NOT NULL,
  ipkts_per_sec INT UNSIGNED,
  opkts_per_sec INT UNSIGNED,
  INDEX srvIdTime (serverid, date),
  INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE nic_errors (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 nicid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES network_interfaces(id)",
 coll SMALLINT UNSIGNED,
 rxdrop SMALLINT UNSIGNED,
 rxerr SMALLINT UNSIGNED,
 rxfifo SMALLINT UNSIGNED,
 rxfram SMALLINT UNSIGNED,
 txcarr SMALLINT UNSIGNED,
 txdrop SMALLINT UNSIGNED,
 txerr SMALLINT UNSIGNED,
 txfifo SMALLINT UNSIGNED,
 INDEX siteTimeIdx (siteid, time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

-- smf_events is populated with events parsed from a collected SMF
-- log file.
CREATE TABLE smf_events (
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    smfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES smf_names(id)",
    time DATETIME NOT NULL,
    event ENUM("start","stop") NOT NULL COLLATE latin1_general_cs,
    -- reason is populated for a stop event
    reasonid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES smf_reasons(id)",
    -- status is populated for a start event - 0 is the only successful
    -- status
    status TINYINT UNSIGNED NOT NULL,
    -- sequenceid is a daily counter to indicate the order events occur
    -- since the granularity of the SMF timestamps is insufficient on its own.
    -- the sequence will increment for each event on the smf service, and will
    -- be reset at midnight.
    sequenceid SMALLINT UNSIGNED NOT NULL,
    INDEX serveridTime (serverid, time)
);

CREATE TABLE smf_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE smf_reasons (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs
);

-- smf_status should be populated at the start of the day by the contents of the
-- svcs-p.txt file.
CREATE TABLE smf_status (
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    smfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES smf_names(id)",
    date DATE NOT NULL,
    status ENUM("uninitialized","offline","online","degraded","maintenance","disabled","legacy-run") NOT NULL COLLATE latin1_general_cs,
    INDEX serveridDate (serverid, date)
);

-- SMF service downtime per day - this is calculated by taking the status of the service
-- at the start of the day as recorded in smf_status and using the events as recorded in
-- smf_events to identify state changes which mark downtime periods
CREATE TABLE smf_downtime (
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    smfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES smf_names(id)",
    date DATE NOT NULL,
    downtime INT UNSIGNED NOT NULL,
    INDEX serveridDate (serverid, date)
);

-- IP 199/15941-FCP 1038147 Rev C WI:1.3 EAM Instrumentation Tables
CREATE TABLE eam_init_stats (
    siteid SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    cmd_responders_initiators ENUM('ehm_ac_in','eht_ac_in','ehip_ac_in','ehap_ac_in','ehms_ac_in','ehm_ac_spr','ehm_af_or','ehm_af_ir','ehm_ac_rpr','eht_ac_rpr','ehip_ac_spr','ehip_af_or','ehap_ac_spr','eac_tf_id') NOT NULL COLLATE latin1_general_cs,
    date DATE NOT NULL,
    count SMALLINT UNSIGNED NOT NULL,
    INDEX siteIdDate (siteid, date)
);

CREATE TABLE eam_ne_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

--
-- The following three tables have been replaced by eam_trimmed_cmd_names, eam_trimmed_app_names
-- and eam_cmd_ne, eam_cmd_time
--
CREATE TABLE eam_cmd_names (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE eam_app_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE eam_ne_config (
    siteid SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    neid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_ne_names(id)",
    initiator_name VARCHAR(25) NOT NULL COLLATE latin1_general_cs,
    conn_idle_to SMALLINT(5) UNSIGNED NOT NULL,
    short_buf_to SMALLINT(5) UNSIGNED NOT NULL,
    long_buf_to SMALLINT(5) UNSIGNED NOT NULL,
    INDEX siteIdDate (siteid, date),
    INDEX (neid),
    INDEX (initiator_name)
);

CREATE TABLE eam_trimmed_cmd_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(512) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx (name),
    PRIMARY KEY(id)
);

CREATE TABLE eam_trimmed_app_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(512) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx (name),
    PRIMARY KEY(id)
);

CREATE TABLE eam_initiator_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx (name),
    PRIMARY KEY(id)
);

-- cmd in eam_cmd_time is a count not a reference to eam_trimmed_cmd_names
CREATE TABLE eam_cmd_time (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    initiatorid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_initiator_names(id)",
    appid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_trimmed_app_names(id)",
    cmd MEDIUMINT UNSIGNED NOT NULL,
    session SMALLINT UNSIGNED NOT NULL,
    ne SMALLINT UNSIGNED NOT NULL,
    INDEX siteIdx(siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eam_cmd_ne (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    neid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_ne_names(id)",
    initiatorid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_initiator_names(id)",
    cmdid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_trimmed_cmd_names(id)",
    cmdcount MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdx(siteid)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


-- IP 130/1594-FCP 103 8147 Uen - usage based instrumentation
-- collect user information (obfuscated)

CREATE TABLE uid_username_map (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    uid SMALLINT UNSIGNED NOT NULL,
    username VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX uidUser (uid, username)
);

-- we store the distinct login events from the last log
-- We don't store any duration information, because of the way the
-- last log works - individual rows are updated regardless of how far
-- back they go, so there's a good chance that we'll never see the logout
-- of an older login event (esp. since we don't know how rotation will be
-- implemented).
CREATE TABLE logins (
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    user_id MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES uid_username_map(id)",
    terminal MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES terminals(id)",
    INDEX serverIdTime (serverid, time)
);

CREATE TABLE os_users (
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    date DATE NOT NULL,
    user_id MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES uid_username_map(id)",
    INDEX serveridDate(serverid, date)
);

CREATE TABLE terminals (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE rpmo_metrics (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    type ENUM("valueHolders","Monitor","StatisticsReportNotification","EventConsumers") COLLATE latin1_general_cs,
    value INT UNSIGNED NOT NULL,
    INDEX siteIdTime (siteid, time)
);

--
-- Server Hardware Tables
--
CREATE TABLE cputypes
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
    mhz INT UNSIGNED,
    kbCache INT UNSIGNED,
    cores TINYINT UNSIGNED NULL DEFAULT NULL,
    threadsPerCore TINYINT UNSIGNED NULL DEFAULT NULL,
    normCpu FLOAT,
    additionalInfo VARCHAR(1024) NULL DEFAULT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX idx(name,mhz,kbCache,cores,threadsPerCore)
);

CREATE TABLE servercpu
(
    cfgid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES servercfgtypes(id)",
    typeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES cputypes(id)",
    num SMALLINT UNSIGNED NOT NULL,
    INDEX cfgIdx(cfgid)
);

-- The CPUs for a servercfg type are held in servercpu table
CREATE TABLE servercfgtypes
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    system VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
    mbram INT UNSIGNED
);

CREATE TABLE servercfg
(
    date DATE NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    cfgid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES servercfgtypes(id)",
    biosver VARCHAR(64) COLLATE latin1_general_cs,
    INDEX srvDateIdx(serverid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


--
-- ENIQ RNC / RBS loaded
--
CREATE TABLE rnc_rbs_loaded (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    type ENUM('RNC','RBS') NOT NULL COLLATE latin1_general_cs,
    count SMALLINT UNSIGNED NOT NULL
);

-- 333/159 41-FCP 103 8147 Rev. A WI 1.4 Usage Based Instrumentation
-- Note initial WI did not define very much information
CREATE TABLE cna_bsc_counts (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    bsc_ver_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES bsc_ver(id)",
    count SMALLINT UNSIGNED NOT NULL,
    INDEX dateSiteIdx (date, siteid)
);

-- VARCHAR(42) - corresponds to size of "version" column on
-- cnadb.bsc table in OSS
CREATE TABLE bsc_ver (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(42) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE cna_bsc_cell_counts (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    bsc_name_id MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES bsc_names(id)",
    count SMALLINT UNSIGNED NOT NULL,
    INDEX dateSiteIdx (date, siteid)
);

-- name is 8 characters to correspond to size of bsc column in
-- cnadb.cna_cell_sites_view
CREATE TABLE bsc_names (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(8) NOT NULL COLLATE latin1_general_cs
);

--
-- Tables to store SMRS stats
--
CREATE TABLE smrs_slave (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
        hostname VARCHAR(32) COLLATE latin1_general_cs,
    UNIQUE INDEX srvIdx (siteid,hostname),
    PRIMARY KEY(id)
);

CREATE TABLE smrs_slave_createtar (
 time DATETIME NOT NULL,
 slaveid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES smrs_slave(id)",
 duration SMALLINT UNSIGNED NOT NULL,
 type ENUM('GRAN','CORE') NOT NULL COLLATE latin1_general_cs,
 findtime SMALLINT UNSIGNED NOT NULL,
 numfiles MEDIUMINT UNSIGNED NOT NULL,
 tartime SMALLINT UNSIGNED NOT NULL,
 tarsizekb MEDIUMINT UNSIGNED NOT NULL,
 tartimestamp DATETIME NOT NULL,
 INDEX slaveIdx(slaveid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE smrs_master_gettar (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 duration SMALLINT UNSIGNED NOT NULL,
 type ENUM('GRAN','CORE') NOT NULL COLLATE latin1_general_cs,
 copytime SMALLINT UNSIGNED NOT NULL,
 extracttime SMALLINT UNSIGNED NOT NULL,
 slaveid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES smrs_slave(id)",
 tartimestamp DATETIME NOT NULL,
 INDEX siteIdx(siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE citrix_farm (
       date DATE NOT NULL,
       serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
       name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
       INDEX serverIdx(serverId)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

--
-- Store ZFS kstat metrics
--
CREATE TABLE zfs_cache (
    time DATETIME NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    size MEDIUMINT UNSIGNED NOT NULL,
    hitratio TINYINT UNSIGNED NOT NULL,
    INDEX serveridTime (serverid, time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


--
-- New tables to store mapping between volume managers and disks
--
CREATE TABLE vrts_disks
(
    date DATE NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    dg VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
    diskid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES disks(id)",
    INDEX (date,serverid)
);

CREATE TABLE svm_disks
(
    date DATE NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    md VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
    diskid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES disks(id)",
    INDEX (date,serverid)
);

CREATE TABLE zfs_disks
(
    date DATE NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    pool VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
    diskid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES disks(id)",
    INDEX (date,serverid)
);

CREATE TABLE nfs_mounts
(
    date DATE NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    mnt VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
    remoteip VARCHAR(15) COLLATE latin1_general_cs,
    diskid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES disks(id)",
    INDEX (date,serverid)
);

CREATE TABLE raw_devices
(
    date DATE NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    diskid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES disks(id)",
    INDEX (date,serverid)
);

--
-- IP: 227/159 41-FCP 103 8147 Table to hold Open LDAP Monitor Information
--
CREATE TABLE open_ldap_monitor_info
(
    time                DATETIME NOT NULL,
    siteid          SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    operations_bind     INT UNSIGNED NOT NULL DEFAULT 0,
    operations_unbind   INT UNSIGNED NOT NULL DEFAULT 0,
    operations_search   INT UNSIGNED NOT NULL DEFAULT 0,
    operations_compare  INT UNSIGNED NOT NULL DEFAULT 0,
    operations_modify   INT UNSIGNED NOT NULL DEFAULT 0,
    operations_modrdn   INT UNSIGNED NOT NULL DEFAULT 0,
    operations_add      INT UNSIGNED NOT NULL DEFAULT 0,
    operations_delete   INT UNSIGNED NOT NULL DEFAULT 0,
    statistics_bytes    INT UNSIGNED NOT NULL DEFAULT 0,
    statistics_entries  INT UNSIGNED NOT NULL DEFAULT 0,
    threads_max     INT UNSIGNED NOT NULL DEFAULT 0,
    threads_open        INT UNSIGNED NOT NULL DEFAULT 0,
    threads_active      INT UNSIGNED NOT NULL DEFAULT 0,
    time_uptime     INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE INDEX (time, siteid)
);

CREATE TABLE systems
(
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    system       VARCHAR(256)    NOT NULL COLLATE latin1_general_cs,
    system_info  VARCHAR(1024)  NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

-- Common Explorer tables BG 07-10-2010
CREATE TABLE jmx_metric_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE jmx_metric_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names (id)",
    metricid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_metric_names (id)",
    type ENUM('ABSOLUTE', 'DELTA') NOT NULL DEFAULT 'ABSOLUTE' COLLATE latin1_general_cs,
    UNIQUE INDEX typeIdx (nameid,metricid),
    PRIMARY KEY(id)
);

CREATE TABLE cex_tasks_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id)",
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names (id)",
    finished_tasks_length SMALLINT UNSIGNED NULL,
    requested_tasks_length SMALLINT UNSIGNED NULL,
    running_tasks_length SMALLINT UNSIGNED NULL,
    INDEX timeSiteidNameId(time,siteid,nameid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE cex_nsd_pm_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id)",
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names (id)",
    cell_requests SMALLINT UNSIGNED NULL,
    cluster_requests SMALLINT UNSIGNED NULL,
    kpi_send_chunk SMALLINT UNSIGNED NULL,
    rbs_requests SMALLINT UNSIGNED NULL,
    rnc_requests SMALLINT UNSIGNED NULL,
    INDEX timeSiteidNameId(time,siteid,nameid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE cex_nsd_fm_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id)",
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names (id)",
    ack_alarms MEDIUMINT UNSIGNED NULL,
    alarm_list_rebuild MEDIUMINT UNSIGNED NULL,
    cleared_alarms MEDIUMINT UNSIGNED NULL,
    delete_alarms MEDIUMINT UNSIGNED NULL,
    new_alarms MEDIUMINT UNSIGNED NULL,
    other_alarms MEDIUMINT UNSIGNED NULL,
    total_alarms MEDIUMINT UNSIGNED NULL,
    INDEX timeSiteidNameId(time,siteid,nameid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE activemq_cexbroker_stats(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id)",
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names (id)",
    temporary_queues SMALLINT UNSIGNED NULL,
    topics SMALLINT UNSIGNED NULL,
    total_consumer_count SMALLINT UNSIGNED NULL,
    total_dequeue_count MEDIUMINT UNSIGNED NULL,
    total_enqueue_count MEDIUMINT UNSIGNED NULL,
    total_message_count SMALLINT UNSIGNED NULL,
    INDEX siteIdTime(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE activemq_queue_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id)",
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names (id)",
    ConsumerCount MEDIUMINT UNSIGNED NOT NULL,
    DequeueCount MEDIUMINT UNSIGNED NOT NULL,
    DispatchCount MEDIUMINT UNSIGNED NOT NULL,
    EnqueueCount MEDIUMINT UNSIGNED NOT NULL,
    QueueSize MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdTime(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


-- O11 E11 WP00103: BCG Instrumentation
CREATE TABLE bcg_instr_operation_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE bcg_instr_import (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    activityid INT NOT NULL,
    start_time time NOT NULL,
    end_time time NOT NULL,
    num_commands MEDIUMINT NULL,
    num_successful_commands MEDIUMINT NULL,
    num_failed_commands MEDIUMINT NULL,
    num_import_trans MEDIUMINT NULL,
    num_retries_trans MEDIUMINT NULL,
    num_retries_for_locks MEDIUMINT NULL,
    overall_import_status ENUM ('SUCCESS', 'FAILURE', 'PARTIALLY SUCCESSFUL') NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX (date,siteid,activityid)
);

CREATE TABLE bcg_instr_export (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    activityid INT NOT NULL,
    start_time time NOT NULL,
    end_time time NOT NULL,
    total_num_mo_exports MEDIUMINT NULL,
    num_mo_successful_exports MEDIUMINT NULL,
    num_mo_failed_exports MEDIUMINT NULL,
    mo_per_sec_export FLOAT(12,8) NULL,
    UNIQUE INDEX (date,siteid,activityid)
);

CREATE TABLE bcg_instr_other_operations (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    activityid INT NOT NULL,
    other_oper_id TINYINT NOT NULL COMMENT "REFERENCES bcg_instr_operation_types",
    operation ENUM ('import', 'export') NOT NULL COLLATE latin1_general_cs,
    moid SMALLINT NULL COMMENT "REFERENCES mo_names(id)",
    system_moid SMALLINT NULL COMMENT "REFERENCES bcg_instr_system_mo_names(id)",
    num_hits MEDIUMINT NULL,
    calls_per_sec FLOAT(12,8) NULL,
    total_time MEDIUMINT UNSIGNED NULL,
    fdn VARCHAR(255) NULL COLLATE latin1_general_cs,
    cs_export_xml_file VARCHAR(255) NULL COLLATE latin1_general_cs,
    INDEX (date,siteid,activityid)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- O11 E11 WP00103: BCG Instrumentation
-- Further tables
CREATE TABLE bcg_instr_mo_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    activityid INT NOT NULL,
    moid SMALLINT NOT NULL COMMENT "REFERENCES mo_names(id)",
    mo_type_command ENUM('create', 'update', 'delete') NULL COLLATE latin1_general_cs,
    num_instances MEDIUMINT NULL,
    cumulative_mo_per_sec FLOAT(12,8) NULL,
    total_time MEDIUMINT UNSIGNED NULL,
    INDEX (date,siteid,activityid)
);

CREATE TABLE bcg_instr_system_mo_names (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);


-- CR 175/109 18-FCP 103 8147/11: IA 175: Storage of Timeout Settings for EAM Special Commands
CREATE TABLE eam_sp_cmd_groups (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE eam_sp_cmd_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);


CREATE TABLE eam_sp_cmd_settings (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    description VARCHAR(255) NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE eam_sp_cmd_details (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    datasetid TINYINT NOT NULL COMMENT "REFERENCES eam_datasets(id)",
    grpid SMALLINT NOT NULL COMMENT "REFERENCES eam_sp_cmd_groups(id)",
    cmdid SMALLINT NOT NULL COMMENT "REFERENCES eam_sp_cmd_names(id)",
    settingid SMALLINT NOT NULL COMMENT "REFERENCES eam_sp_cmd_settings",
    setting_value SMALLINT NULL,
    UNIQUE INDEX unqidx(siteid,date,datasetid,grpid,cmdid,settingid)
);

--
-- WI 1.36: 443/159 41-FCP 103 8147
-- DDP - Modifications needed for post process GPI log
--
CREATE TABLE gpi_events (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    BTSadded SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    BTSremoved SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    BTSmodified SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    AssocCreated SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    AssocRemoved SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CabiCreated SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CabiRemoved SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    INDEX siteIdStartTime(siteid, start_time),
    INDEX siteIdEndTime(siteid, end_time)
);

--
-- CR 175/109 18-FCP 103 8147/11: IA 175: Storage of Timeout Settings for EAM Special Commands
-- Further changes & additions
CREATE TABLE eam_datasets (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE eam_spr_periods (
    id TINYINT UNSIGNED NOT NULL,
    len ENUM ('QUARTERHOUR', 'HALFHOUR', 'HOUR', 'DAY') DEFAULT 'HALFHOUR' NOT NULL COLLATE latin1_general_cs,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    PRIMARY KEY(id)
);

CREATE TABLE eam_spr_details (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    datasetid TINYINT NOT NULL COMMENT "REFERENCES eam_datasets(id)",
    periodid TINYINT NOT NULL DEFAULT 0 COMMENT "REFERENCES eam_spr_periods(id)",
    is_spontaneous TINYINT NOT NULL,
    eam_neid SMALLINT NOT NULL COMMENT "REFERENCES eam_ne_names(id)",
    INDEX (time,siteid,datasetid)
) PARTITION BY RANGE ( TO_DAYS(time) ) (
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE vxstat
(
    time DATETIME NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    volid    SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES volumes(id)",
    rop      INT UNSIGNED NOT NULL,
    wop      INT UNSIGNED NOT NULL,
    rblk     INT UNSIGNED NOT NULL,
    wblk     INT UNSIGNED NOT NULL,
    rtime    DECIMAL(5,1) UNSIGNED NOT NULL,
    wtime    DECIMAL(5,1) UNSIGNED NOT NULL,
    INDEX srvIdIdx (serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) ) (
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE vxfs_inode_cache (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 vxi_icache_inuseino INT UNSIGNED,
 inodes INT UNSIGNED,
 lookups INT UNSIGNED,
 recycle MEDIUMINT UNSIGNED,
 hitrate DECIMAL(5,2),
 INDEX siteIdTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) ) (
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

--
-- All lvlog_% tables have names defined as 255 VARCHARS to make it easier to
-- store unique values without needing to know each individual column size
--
CREATE TABLE lvlog_entries_by_day (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    log_type ENUM('COMMAND','ERROR','NETWORK','SECURITY','SYSTEM','OTHER') COLLATE latin1_general_cs, -- use "other" for empty strings and currently unknown types
    application_name SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES lvlog_application_names(id)",
    command_name SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES lvlog_command_names(id)",
    type SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES lvlog_types(id)",
    severity ENUM('OFF','COARSE','DETAILED','WARNING','MINOR','MAJOR','CRITICAL','OTHER') COLLATE latin1_general_cs, -- use "other" for empty strings and currently unknown types
    -- prepend "old_state", "new_state" columns to additional info for now
    additional_info BIGINT UNSIGNED NOT NULL COMMENT "REFERENCES lvlog_additional_info(id)",
    count INT UNSIGNED NOT NULL,
    INDEX siteDateIdx(siteid,date),
    INDEX addInfoIdx(additional_info)
);

CREATE TABLE lvlog_application_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(name)
);

CREATE TABLE lvlog_additional_info (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs, -- restrict to 255 chars for now regardless of actual length
    UNIQUE INDEX nameIdx(name)
);

CREATE TABLE lvlog_command_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(name)
);

CREATE TABLE lvlog_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(name)
);

--
-- CR 149/109 18-FCP 103 8147/11 (WP OSS-RC 11 WP00349): EAM Instrumentation for Process Manager
--
CREATE TABLE eai_esi_map_detail (
    date            DATE NOT NULL,
    siteid          SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    eam_neid        SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_ne_names(id)",
    initiatorid     SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_initiator_responders(id)",
    initiatortypeid TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_initiator_responder_types(id)",
    PRIMARY KEY(date,siteid,eam_neid,initiatorid)
);

CREATE TABLE eam_initiator_responders (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(25) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE eam_initiator_responder_types (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(25) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

--
-- Replaced by eam_cmd_ne and eam_cmd_time
--
CREATE TABLE eam_connected_ne_detail (
    time                   DATETIME NOT NULL,
    siteid                 SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    commandid              MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_cmd_names(id)",
    command_str            VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    appid                  SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_app_names(id)",
    eam_neid               SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_ne_names(id)",
    initiatorid            TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_initiator_responders(id)",
    initiatortypeid        TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_initiator_responder_types(id)",
    initiator_index        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    periodid               TINYINT NOT NULL DEFAULT 0 COMMENT "REFERENCES eam_spr_periods(id)",
    associd                INT UNSIGNED NULL,
    primary_command_state  TINYINT UNSIGNED NULL,
    primary_response_state TINYINT UNSIGNED NULL,
    INDEX siteIdx(siteid)
) PARTITION BY RANGE ( TO_DAYS(time) ) (
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

--
-- OSS-RC 11 WP00557 (CR: 451/109 18-FCP 103 8147/11): Tables to store the data in the nodelist.txt feed file
--
CREATE TABLE onrm_mo_details (
        id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
        PRIMARY KEY(id)
);

CREATE TABLE onrm_source_types (
        id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
        PRIMARY KEY(id)
);

CREATE TABLE onrm_io_types (
        id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(10) NOT NULL COLLATE latin1_general_cs,
        PRIMARY KEY(id)
);

CREATE TABLE onrm_node_list (
        date                        DATE NOT NULL,
        siteid                     SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
        onrm_moid            MEDIUMINT NOT NULL COMMENT "REFERENCES onrm_mo_details(id)",
        me_typeid             SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES me_types(id)",
        node_verid            SMALLINT UNSIGNED NULL COMMENT "REFERENCES node_ver(id)",
        connection_status   ENUM('true', 'false') COLLATE latin1_general_cs,
        source_typeid        SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES onrm_source_types(id)",
        ip_address             VARCHAR(17) NULL COLLATE latin1_general_cs,
        security_state         ENUM ('ON', 'OFF') COLLATE latin1_general_cs,
        io_typeid               TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT "REFERENCES onrm_io_types(id)",
        INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

--
-- CR 425/109 18-FCP 103 8147/11 A: Tables to store the AMOS sessions data. [BG 2011-03-04]
--
CREATE TABLE amos_metrics (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE amos_sessions (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    metricid TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES amos_metrics(id)",
    value INT UNSIGNED NULL,
    INDEX siteTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE enm_amos_clusters (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 commandCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX enmAmosClustersIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

--
-- Store the messages file somewhere central so we can retrieve it
-- without filesystem access
--

CREATE TABLE server_messages (
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    date                        DATE NOT NULL,
    data MEDIUMTEXT COLLATE latin1_general_cs,
    UNIQUE INDEX serveridDate (serverid, date)
)
PARTITION BY RANGE ( TO_DAYS(date) ) (
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

--
-- Notification Event Channels per day - HM86355
--

CREATE TABLE na_eventchannels_per_day (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    service ENUM("Internal","External") NOT NULL,
    category SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES na_eventchannel_categories(id)",
    nconsumers SMALLINT UNSIGNED NOT NULL,
    nsuppliers SMALLINT UNSIGNED NOT NULL,
    events_received INT UNSIGNED NOT NULL,
    events_delivered INT UNSIGNED NOT NULL,
    nconsumers_with_discarded_events SMALLINT UNSIGNED NOT NULL,
    max_queue_length MEDIUMINT UNSIGNED NOT NULL,
    max_events_per_consumer MEDIUMINT UNSIGNED NOT NULL,
    max_reconnect_attempts MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteidDate (siteid, date)
);

CREATE TABLE na_eventchannel_categories (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE na_consumers (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ident VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    category SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES na_eventchannel_categories(id)",
    filter TEXT NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE na_suppliers (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ident VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    category SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES na_eventchannel_categories(id)"
);

CREATE TABLE na_consumers_by_day (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    consumer SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES na_consumers(id)",
    discarded_events BOOLEAN NOT NULL DEFAULT FALSE,
    events_delivered INT UNSIGNED NOT NULL,
    INDEX siteidDate (siteid, date)
);

CREATE TABLE na_suppliers_by_day (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    supplier SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES na_suppliers(id)",
    events_received INT UNSIGNED NOT NULL,
    INDEX siteidDate (siteid, date)
);


--
-- OSS-RC 12 WP00004: IP 362/159 41-FCP 103 8147: COSM instr data tables [BG 2011-05-05]
--
CREATE TABLE cosm_mx_stats (
        time DATETIME NOT NULL,
        siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id)",
        nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names(id)",
        total_tasks_created SMALLINT UNSIGNED NULL,
        total_requests_dispatched SMALLINT UNSIGNED NULL,
        registered_callbacks SMALLINT UNSIGNED NULL,
        received_callbacks SMALLINT UNSIGNED NULL,
        processed_callbacks SMALLINT UNSIGNED NULL,
        failed_callbacks SMALLINT UNSIGNED NULL,
        INDEX timeSiteidNameId(time,siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE cosm_fileauditor_stats (
        time DATETIME NOT NULL,
        siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id)",
        nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names(id)",
        last_cleanup_operation_start_time DATETIME NULL,
        polled_data_location VARCHAR(50) NULL COLLATE latin1_general_cs,
        total_files_deleted SMALLINT UNSIGNED NULL,
        total_files_processed SMALLINT UNSIGNED NULL,
        total_space_recovered_in_bytes SMALLINT UNSIGNED NULL,
        INDEX siteIdTime(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE cosm_os_stats (
        time DATETIME NOT NULL,
        siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id)",
        nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_names(id)",
        max_file_descriptor_count SMALLINT UNSIGNED NULL,
        open_file_descriptor_count SMALLINT UNSIGNED NULL,
        INDEX siteNameIdx(time,siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

--
-- OSS-RC 12 WP00003: 479/159 41-FCP 103 8147: Eclipse Agent data tables
--

CREATE TABLE agent_user_names (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);


CREATE TABLE agent_app_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE agent_names (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

-- Note here that there are three "event types" and the values in the ENUM for
-- the event_type field of the agent_eclipse_stats table were taken from javadoc:
-- Bundle Event: http://www.osgi.org/javadoc/r4v43/org/osgi/framework/BundleEvent.html
-- Framework Event: http://www.osgi.org/javadoc/r4v43/org/osgi/framework/FrameworkEvent.html
-- Service Event: http://www.osgi.org/javadoc/r4v43/org/osgi/framework/ServiceEvent.html
CREATE TABLE agent_eclipse_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    userid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES agent_user_names(id)",
    processid MEDIUMINT UNSIGNED NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    appid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES agent_app_names(id)",
    time_stamp BIGINT  UNSIGNED NOT NULL,
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES agent_names(id)",
    event_type ENUM ( 'ERROR', 'INFO', 'INSTALLED', 'LAZY_ACTIVATION', 'MODIFIED', 'MODIFIED_ENDMATCH', 'PACKAGES_REFRESHED', 'REGISTERED', 'RESOLVED', 'STARTED', 'STARTING', 'STARTLEVEL_CHANGED', 'STOPPED', 'STOPPED_BOOTCLASSPATH_MODIFIED', 'STOPPED_UPDATE', 'STOPPING', 'UNINSTALLED', 'UNKNOWN', 'UNREGISTERING', 'UNRESOLVED', 'UPDATED', 'WAIT_TIMEDOUT', 'WARNING' ) NOT NULL DEFAULT 'UNKNOWN' COLLATE latin1_general_cs,
    INDEX (time,siteid)
);

CREATE TABLE agent_memory_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    userid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES agent_user_names(id)",
    processid MEDIUMINT UNSIGNED NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    appid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES agent_app_names(id)",
    time_stamp BIGINT  UNSIGNED NOT NULL,
    hp_committed SMALLINT UNSIGNED NOT NULL,
    hp_init SMALLINT UNSIGNED NOT NULL,
    hp_max SMALLINT UNSIGNED NOT NULL,
    hp_used SMALLINT UNSIGNED NOT NULL,
    INDEX (time,siteid)
);

CREATE TABLE agent_threading_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    userid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES agent_user_names(id)",
    processid MEDIUMINT UNSIGNED NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    appid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES agent_app_names(id)",
    time_stamp BIGINT  UNSIGNED NOT NULL,
    thread_count SMALLINT UNSIGNED NOT NULL,
    INDEX (time,siteid)
);

-- 553/159 41-FCP 103 8147  / WI <1.31> Create table for sma.log stats [RK]
CREATE TABLE hires_sma_stat
(
     time DATETIME NOT NULL,
     siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
     adj_ncalls SMALLINT UNSIGNED,
     alive SMALLINT UNSIGNED,
     avg_me_write_delay MEDIUMINT UNSIGNED,
     avg_sync_time_stn SMALLINT UNSIGNED,
     comp_nodes SMALLINT UNSIGNED,
     dead_nodes SMALLINT UNSIGNED,
     max_sync_time_stn MEDIUMINT UNSIGNED,
     min_sync_time_stn MEDIUMINT UNSIGNED,
     nvr_connected_nodes SMALLINT UNSIGNED,
     num_fail_me_writes SMALLINT UNSIGNED,
     num_ignored_att_detach SMALLINT UNSIGNED,
     num_threads_sys SMALLINT UNSIGNED,
     num_stn_syncs_finish_lop SMALLINT UNSIGNED,
     num_stn_syncs_start_lop SMALLINT UNSIGNED,
     num_removed_me SMALLINT UNSIGNED,
     num_succ_me_writes SMALLINT UNSIGNED,
     synched SMALLINT UNSIGNED,
     tp2_comp SMALLINT UNSIGNED,
     tp2_queued SMALLINT UNSIGNED,
     tp_exe SMALLINT UNSIGNED,
     tp_wait SMALLINT UNSIGNED,
     total SMALLINT UNSIGNED,
     unsynched SMALLINT UNSIGNED,
     ug_attempts SMALLINT UNSIGNED,
     ug_succ SMALLINT UNSIGNED,

     INDEX siteTime (siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

-- OSS-RC 12 WP00250: 652/15941-FCP 103 8147: RTR: New Features - incl. Modify RAC and other Improvs.
CREATE TABLE rbs_names
(
        id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
        PRIMARY KEY(id)
);

-- WP00367: CR: 11/109 18-FCP 103 8147/13: EAM Alarm Handling Improvement - IPC-DIR responses.
CREATE TABLE eam_node_fdn_names (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE eam_alarm_details (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    datasetid TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_datasets(id)",
    node_fdnid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES eam_node_fdn_names(id)",
    alarm_number MEDIUMINT UNSIGNED NOT NULL ,
    time_received DATETIME NOT NULL,
    time_forwarded DATETIME NOT NULL,
    INDEX siteIdDate(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- WP00584: CR: 418/109 18-FCP 103 8147/13: Versant Instrumentation
CREATE TABLE vdb_stats
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    vdbid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES vdb_names(id)",
    located BIGINT UNSIGNED,
    datareads BIGINT UNSIGNED,
    datawrites BIGINT UNSIGNED,
    llogwrite BIGINT UNSIGNED,
    plogwrite BIGINT UNSIGNED,
    lktimeout SMALLINT UNSIGNED,
    lkwait SMALLINT UNSIGNED,
    xactactive SMALLINT UNSIGNED,
    xactcommit BIGINT UNSIGNED,
    xactrollback BIGINT UNSIGNED,
    checkpts SMALLINT UNSIGNED,
    llogfull SMALLINT UNSIGNED,
    llogend SMALLINT UNSIGNED,
    threads SMALLINT UNSIGNED,
    INDEX siteidTimeIdx (siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE vdb_profile_types
(
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sysvol varchar(10) COLLATE latin1_general_cs,
    plogvol varchar(10) COLLATE latin1_general_cs,
    llogvol varchar(10) COLLATE latin1_general_cs,
    extent_size SMALLINT,
    logging varchar(5) COLLATE latin1_general_cs,
    locking varchar(5) COLLATE latin1_general_cs,
    commit_flush varchar(5) COLLATE latin1_general_cs,
    polling_optimize varchar(5) COLLATE latin1_general_cs,
    async_buffer_cleaner SMALLINT,
    async_logger SMALLINT,
    event_registration_mode varchar(20) COLLATE latin1_general_cs,
    event_msg_mode varchar(20) COLLATE latin1_general_cs,
    event_msg_transient_queue_size INT,
    bf_dirty_high_water_mark SMALLINT,
    bf_dirty_low_water_mark SMALLINT,
    class SMALLINT,
    db_timeout SMALLINT,
    prof_index SMALLINT,
    llog_buf_size VARCHAR(10) COLLATE latin1_general_cs,
    lock_wait_timeout SMALLINT,
    max_page_buffs INT,
    multi_latch VARCHAR(5) COLLATE latin1_general_cs,
    plog_buf_size VARCHAR(5) COLLATE latin1_general_cs,
    heap_size INT,
    heap_arena_size VARCHAR(10) COLLATE latin1_general_cs,
    heap_arena_size_increment VARCHAR(10) COLLATE latin1_general_cs,
    heap_arena_trim_threshold VARCHAR(10) COLLATE latin1_general_cs,
    heap_max_arenas SMALLINT,
    heap_arena_segment_merging VARCHAR(10) COLLATE latin1_general_cs,
    transaction SMALLINT,
    user SMALLINT,
    volume SMALLINT,
    stat VARCHAR(10) COLLATE latin1_general_cs,
    assertion_level SMALLINT,
    trace_entries INT,
    trace_file VARCHAR(15) COLLATE latin1_general_cs,
    versant_be_dbalogginglevel SMALLINT,
    be_syslog_level SMALLINT,
    blackbox_trace_comps VARCHAR(10) COLLATE latin1_general_cs,
    treat_vstr_of_1b_as_string_in_query VARCHAR(10) COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE versant_dbs
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    vdbid   SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES vdb_names(id)",
    profileid  MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES vdb_profile_types(id)",
    UNIQUE INDEX (date,siteid,vdbid)
);

CREATE TABLE vdb_locks
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    vdbid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES vdb_names(id)",
    total BIGINT,
    outstanding INT,
    deadlocks INT,
    conflicts INT,
    requests BIGINT,
    objects INT,
    UNIQUE INDEX (date,siteid,vdbid)
);

CREATE TABLE vdb_logs
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    vdbid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES vdb_names(id)",
    data MEDIUMTEXT COLLATE latin1_general_cs,
    INDEX idx(date,siteid,vdbid)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE vdb_connections
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    vdbid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES vdb_names(id)",
    procid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES process_names(id)",
    count SMALLINT UNSIGNED NOT NULL,
    INDEX siteidDate (siteid,date)
);

-- OSS-RC 12 WP00558: CR 862/109 18-FCP 103 8147/13 A
-- Store the data for the NEAD failed notifications
CREATE TABLE nead_failed_notif (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    num_notif SMALLINT UNSIGNED NOT NULL,
    num_failed_notif SMALLINT UNSIGNED NOT NULL,
    INDEX (time,siteid)
);

-- OSS-RC 12 WP00564: IP 2/159 41-11/FCP 103 8147/2
-- contains mappings of metric names to their relevant IDs
CREATE TABLE fm_metrics (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(70) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

-- contains mappings of names of processes to their relevant IDs
CREATE TABLE fm_process_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(70) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

-- OSS-RC 12 WP00117: LTE: PCI Performance and Usability Improvements [IP: 651/15941-FCP1038147]

-- PCI JMX instrumentation data
CREATE TABLE pci_jmx_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    datasetid TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES pci_jmx_dataset_names(id)",
    metricid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES jmx_metric_names(id)",
    value MEDIUMINT UNSIGNED NOT NULL,
    INDEX (time,siteid,datasetid,metricid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


-- PCI CIF log data
CREATE TABLE app_user_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE pci_function_names (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE pci_consideration_names (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE pci_cif_log_initialisation_of_service_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    identity VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
    user_idid SMALLINT UNSIGNED NULL COMMENT "REFERENCES app_user_names(id)",
    current_number_of_ongoing_threads SMALLINT UNSIGNED NOT NULL,
    time_taken_to_initialise_the_pci_service MEDIUMINT UNSIGNED NOT NULL,
    time_taken_to_initialise_the_handlers_facade MEDIUMINT UNSIGNED NOT NULL,
    time_taken_to_initialise_the_cache_manager SMALLINT UNSIGNED NOT NULL,
    INDEX(time, siteid)
);

CREATE TABLE pci_cif_log_build_cache_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    identity VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
    user_idid SMALLINT UNSIGNED NULL COMMENT "REFERENCES app_user_names(id)",
    current_number_of_ongoing_threads SMALLINT UNSIGNED NOT NULL,
    time_taken_to_build_the_cache MEDIUMINT UNSIGNED NOT NULL,
    time_taken_to_read_from_the_cs MEDIUMINT UNSIGNED NOT NULL,
    time_taken_to_populate_all_cache_maps MEDIUMINT UNSIGNED NOT NULL,
    time_taken_to_create_celltosubunit_map SMALLINT UNSIGNED NOT NULL,
    time_taken_to_create_celltoantennagain_map SMALLINT UNSIGNED NOT NULL,
    time_taken_to_create_celltoantennabearing_map SMALLINT UNSIGNED NOT NULL,
    time_taken_to_populate_enodeb_map SMALLINT UNSIGNED NOT NULL,
    time_taken_to_populate_cells_map MEDIUMINT UNSIGNED NOT NULL,
    INDEX(time, siteid)
);

CREATE TABLE pci_cif_log_notification_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    user_idid SMALLINT UNSIGNED NULL COMMENT "REFERENCES app_user_names(id)",
    interval_start DATETIME NOT NULL,
    interval_end DATETIME NOT NULL,
    number_of_notifications_received MEDIUMINT UNSIGNED NOT NULL,
    current_number_of_buffered_notifications SMALLINT UNSIGNED NOT NULL,
    peak_number_of_buffered_notifications SMALLINT UNSIGNED NOT NULL,
    number_of_notifications_introduced MEDIUMINT UNSIGNED NOT NULL,
    number_of_invalid_notifications SMALLINT UNSIGNED NOT NULL,
    number_of_cache_updates MEDIUMINT UNSIGNED NOT NULL,
    number_of_cache_builds_ordered SMALLINT UNSIGNED NOT NULL,
    INDEX(time, siteid)
);

-- ENIQ 12 - WP00155 [CR: 42/109 18-FCP 103 8147/14 A]
-- Create eniq_workflow_events, eniq_workflow_names & eniq_workflow_types tables

CREATE TABLE eniq_workflow_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
  PRIMARY KEY (id)
);


CREATE TABLE eniq_workflow_types (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
  PRIMARY KEY (id)
);

CREATE TABLE eniq_workflow_events (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
  typeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_types(id)",
  eventcount MEDIUMINT UNSIGNED NOT NULL,
  avgduration FLOAT UNSIGNED DEFAULT NULL,
  maxduration MEDIUMINT UNSIGNED DEFAULT NULL,
  INDEX (siteid,nameid,typeid)
);

-- OSS-RC 12 - WP00374 [IP 728/159 41-FCP 103 8147 Uen ]
-- Create cs_application_names table
CREATE TABLE cs_application_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(70) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

-- OSS-RC 12 - WP00297 [IP 731/159 41-FCP 103 8147 Uen ]
-- Create caas_performance table
CREATE TABLE caas_performance
(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    authentications    SMALLINT UNSIGNED NOT NULL,
    authorization     SMALLINT UNSIGNED NOT NULL,
    answered     SMALLINT UNSIGNED NOT NULL,
    avg_proc_time     SMALLINT UNSIGNED NOT NULL,
    INDEX siteIdx (siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


--
-- OSS-RC 12 WP00781: CR: 665/109 18-FCP 103 8147/13 & IP: 850/15941-FCP1038147
-- Collect OpenDJ monitor information (Replacement for OpenLDAP)
--

CREATE TABLE opendj_ldap_stats (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NULL COMMENT "REFERENCES servers(id)",
 port ENUM( 'LdapUserStore', 'LdapCtsStore', 'DsReplicationCtsStore',
    'DsReplicationUserStore', 'DsReplicationConfigStore', 'HTTP Connection Handler',
    'LdapConfigStore', 'LDAPS', 'Administration Connector', 'LDAP'
 ) NOT NULL COLLATE latin1_general_cs,
 bind_cnt MEDIUMINT UNSIGNED NOT NULL,
 unbind_cnt MEDIUMINT UNSIGNED NOT NULL,
 search_cnt MEDIUMINT UNSIGNED NOT NULL,
 compare_cnt MEDIUMINT UNSIGNED NOT NULL,
 mod_cnt MEDIUMINT UNSIGNED NOT NULL,
 moddn_cnt MEDIUMINT UNSIGNED NOT NULL,
 add_cnt MEDIUMINT UNSIGNED NOT NULL,
 delete_cnt MEDIUMINT UNSIGNED NOT NULL,
 bind_time MEDIUMINT UNSIGNED NOT NULL,
 unbind_time MEDIUMINT UNSIGNED NOT NULL,
 search_time MEDIUMINT UNSIGNED NOT NULL,
 compare_time MEDIUMINT UNSIGNED NOT NULL,
 mod_time MEDIUMINT UNSIGNED NOT NULL,
 moddn_time MEDIUMINT UNSIGNED NOT NULL,
 add_time MEDIUMINT UNSIGNED NOT NULL,
 delete_time MEDIUMINT UNSIGNED NOT NULL,
 bind_query_rate SMALLINT UNSIGNED,
 unbind_query_rate SMALLINT UNSIGNED,
 search_query_rate SMALLINT UNSIGNED,
 compare_query_rate SMALLINT UNSIGNED,
 mod_query_rate SMALLINT UNSIGNED,
 moddn_query_rate SMALLINT UNSIGNED,
 add_query_rate SMALLINT UNSIGNED,
 delete_query_rate SMALLINT UNSIGNED,
 avg_byteswritten_per_op MEDIUMINT,
 avg_bytesread_per_op MEDIUMINT,
 abandon_cnt MEDIUMINT UNSIGNED,
 search_base_cnt MEDIUMINT UNSIGNED,
 search_sub_cnt MEDIUMINT UNSIGNED,
 abandon_time MEDIUMINT UNSIGNED,
 search_sub_time MEDIUMINT UNSIGNED,
 search_base_time MEDIUMINT UNSIGNED,
 bytes_read_total MEDIUMINT UNSIGNED,
 bytes_read_count MEDIUMINT UNSIGNED,
 bytes_written_total MEDIUMINT UNSIGNED,
 bytes_written_count MEDIUMINT UNSIGNED,
 INDEX(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

--
-- OSS RC-13 WP00162 - [IP: 815/159 41-FCP 103 8147]
-- IPRAN TRANSPORT - Colect SMARTEDGE MA Statistic information
--
CREATE TABLE sema_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    adjust_numcalls SMALLINT UNSIGNED,
    avedelaymecontextwrite SMALLINT UNSIGNED,
    average_reply_time_netop SMALLINT UNSIGNED,
    average_xslt_map_time_smartedge SMALLINT UNSIGNED,
    compatible_nodes SMALLINT UNSIGNED,
    max_reply_time_netop SMALLINT UNSIGNED,
    max_xslt_map_time_smartedge SMALLINT UNSIGNED,
    min_reply_time_netop SMALLINT UNSIGNED,
    min_xslt_map_time_smartedge SMALLINT UNSIGNED,
    neverconnected_nodes SMALLINT UNSIGNED,
    nufailedmecontextwrites SMALLINT UNSIGNED,
    nuignoredattachdetach SMALLINT UNSIGNED,
    number_of_threads_system SMALLINT UNSIGNED,
    num_smartedge_syncs_finished_last_output_period SMALLINT UNSIGNED,
    num_smartedge_syncs_started_last_output_period SMALLINT UNSIGNED,
    nuremovemedcontext SMALLINT UNSIGNED,
    nusuccmecontextwrites SMALLINT UNSIGNED,
    threadpool2_completed SMALLINT UNSIGNED,
    threadpool2_queued SMALLINT UNSIGNED,
    threadpool_executing SMALLINT UNSIGNED,
    threadpool_waiting SMALLINT UNSIGNED,
    total_nodes SMALLINT UNSIGNED,
    unsynced_nodes SMALLINT UNSIGNED,
    upgrade_attempts VARCHAR(30) NOT NULL,
    upgrade_successes VARCHAR(30) NOT NULL,
    INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

--
-- HP40698: AMOS Commands data
--
CREATE TABLE amos_command_names
(
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE amos_commands
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    cmdid INT UNSIGNED NOT NULL COMMENT "REFERENCES amos_command_names(id)",
    count MEDIUMINT UNSIGNED NOT NULL,
    UNIQUE INDEX (date,siteid,cmdid)
);

--
-- Updates to FM Statistics data storage after refactory of parse & store script
-- Updated tables include: "fm_metrics, fm_metric_types, fm_supi_stats, imh_fmai_server_stats,
-- fma_handler_1_stats, imh_alarm_server_stats"
--
CREATE TABLE fm_metric_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES fm_process_names (id)",
    metricid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES fm_metrics (id)",
    type ENUM('ABSOLUTE', 'DELTA') NOT NULL DEFAULT 'DELTA' COLLATE latin1_general_cs,
    UNIQUE INDEX typeIdx (nameid,metricid),
    PRIMARY KEY(id)
);

CREATE TABLE fm_supi_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES fm_process_names(id)",
    total_events_from_manager SMALLINT UNSIGNED,
    total_actions_from_kernel SMALLINT UNSIGNED,
    total_commands_to_kernel MEDIUMINT UNSIGNED,
    actions_from_kernel_queue_size SMALLINT UNSIGNED,
    command_to_kernel_queue_size MEDIUMINT UNSIGNED,
    event_from_manager_queue_size SMALLINT UNSIGNED,
    start_time_secs INT UNSIGNED,
    INDEX siteIdTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE imh_fmai_server_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES fm_process_names(id)",
    incoming_fm_xedd_events TINYINT UNSIGNED,
    incoming_alarms MEDIUMINT UNSIGNED,
    incoming_context_d_correlated_mess TINYINT UNSIGNED,
    outgoing_alarm_total MEDIUMINT UNSIGNED,
    outgoing_alarm_fmx SMALLINT UNSIGNED,
    start_time_secs INT UNSIGNED,
    INDEX siteIdTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE imh_alarm_server_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES fm_process_names(id)",
    incoming_total_alarms MEDIUMINT UNSIGNED,
    incoming_fmx_alarms MEDIUMINT UNSIGNED,
    incoming_ack_events MEDIUMINT UNSIGNED,
    incoming_unack_events SMALLINT UNSIGNED,
    incoming_comment_events SMALLINT UNSIGNED,
    outgoing_alarms MEDIUMINT UNSIGNED,
    start_time_secs INT UNSIGNED,
    INDEX siteIdTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE fma_handler_1_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES fm_process_names(id)",
    received_alarms MEDIUMINT UNSIGNED NULL,
    received_admin_events MEDIUMINT UNSIGNED NULL,
    received_network_model_notifications MEDIUMINT UNSIGNED NULL,
    sent_fmii_comments SMALLINT UNSIGNED NULL,
    sent_fmii_ack MEDIUMINT UNSIGNED NULL,
    sent_fmii_unack SMALLINT UNSIGNED NULL,
    sent_fmii_alarm MEDIUMINT UNSIGNED NULL,
    sent_action_messages MEDIUMINT UNSIGNED NULL,
    sent_context_dcorrelated_message SMALLINT UNSIGNED NULL,
    sent_fmxi_messages MEDIUMINT UNSIGNED NULL,
    sent_fmai_ack MEDIUMINT UNSIGNED NULL,
    sent_fmai_unack SMALLINT UNSIGNED NULL,
    message_processed_by_list_thread_0 MEDIUMINT UNSIGNED NULL,
    message_processed_by_list_thread_1 MEDIUMINT UNSIGNED NULL,
    message_processed_by_list_thread_2 MEDIUMINT UNSIGNED NULL,
    message_processed_by_list_thread_3 MEDIUMINT UNSIGNED NULL,
    message_processed_by_list_thread_4 MEDIUMINT UNSIGNED NULL,
    message_processed_by_list_thread_5 MEDIUMINT UNSIGNED NULL,
    message_processed_by_list_thread_6 MEDIUMINT UNSIGNED NULL,
    message_processed_by_list_thread_7 MEDIUMINT UNSIGNED NULL,
    alarm_message_processed_by_log_thread_0 MEDIUMINT UNSIGNED NULL,
    alarm_message_processed_by_log_thread_1 MEDIUMINT UNSIGNED NULL,
    alarm_message_processed_by_log_thread_2 MEDIUMINT UNSIGNED NULL,
    alarm_message_processed_by_log_thread_3 MEDIUMINT UNSIGNED NULL,
    alarm_message_processed_by_log_thread_4 MEDIUMINT UNSIGNED NULL,
    alarm_message_processed_by_log_thread_5 MEDIUMINT UNSIGNED NULL,
    alarm_message_processed_by_log_thread_6 MEDIUMINT UNSIGNED NULL,
    alarm_message_processed_by_log_thread_7 MEDIUMINT UNSIGNED NULL,
    correlation_message_processed_by_log_thread_0 MEDIUMINT UNSIGNED NULL,
    correlation_message_processed_by_log_thread_1 MEDIUMINT UNSIGNED NULL,
    correlation_message_processed_by_log_thread_2 MEDIUMINT UNSIGNED NULL,
    correlation_message_processed_by_log_thread_3 MEDIUMINT UNSIGNED NULL,
    correlation_message_processed_by_log_thread_4 MEDIUMINT UNSIGNED NULL,
    correlation_message_processed_by_log_thread_5 MEDIUMINT UNSIGNED NULL,
    correlation_message_processed_by_log_thread_6 MEDIUMINT UNSIGNED NULL,
    correlation_message_processed_by_log_thread_7 MEDIUMINT UNSIGNED NULL,
    list_thread_qsize_0 SMALLINT UNSIGNED NULL,
    list_thread_qsize_1 SMALLINT UNSIGNED NULL,
    list_thread_qsize_2 SMALLINT UNSIGNED NULL,
    list_thread_qsize_3 SMALLINT UNSIGNED NULL,
    list_thread_qsize_4 SMALLINT UNSIGNED NULL,
    list_thread_qsize_5 SMALLINT UNSIGNED NULL,
    list_thread_qsize_6 SMALLINT UNSIGNED NULL,
    list_thread_qsize_7 SMALLINT UNSIGNED NULL,
    log_thread_qsize_0 INT UNSIGNED NULL,
    log_thread_qsize_1 INT UNSIGNED NULL,
    log_thread_qsize_2 INT UNSIGNED NULL,
    log_thread_qsize_3 INT UNSIGNED NULL,
    log_thread_qsize_4 INT UNSIGNED NULL,
    log_thread_qsize_5 INT UNSIGNED NULL,
    log_thread_qsize_6 INT UNSIGNED NULL,
    log_thread_qsize_7 INT UNSIGNED NULL,
    start_time_secs INT UNSIGNED,
    INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

--
-- OSS-RC 13 WP00128 - IP: 761/15941-FCP 103 8147 [RK 2012-08-23]
--
CREATE TABLE rttfi_ops
(
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    starttime DATETIME NOT NULL,
    endtime DATETIME NOT NULL,
    op VARCHAR(200) NOT NULL COLLATE latin1_general_cs,
    onrm_moid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES onrm_mo_details(id)",
    duration SMALLINT UNSIGNED NOT NULL,
    INDEX (siteid,starttime)
);

--
-- EMC Clariion Stats
--

CREATE TABLE emc_sys (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX idx (name),
    PRIMARY KEY(id)
);

CREATE TABLE emc_lun (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    name VARCHAR(512) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX idx (sysid,name),
    PRIMARY KEY(id)
);

CREATE TABLE emc_lun_stats (
    lunid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_lun(id)",
    sysid SMALLINT UNSIGNED COMMENT "REFERENCES emc_sys(id)",
    time DATETIME NOT NULL,
    utilization DECIMAL(5,2) NOT NULL,
    utilnonopt DECIMAL(5,2),
    qlen DECIMAL(6,2) NOT NULL,
    qlenbusy DECIMAL(6,2),
    resptime DECIMAL(6,2) NOT NULL,
    servtime DECIMAL(6,2) NOT NULL,
    readbw SMALLINT UNSIGNED NOT NULL,
    readiops SMALLINT UNSIGNED NOT NULL,
    writebw SMALLINT UNSIGNED NOT NULL,
    writeiops SMALLINT UNSIGNED NOT NULL,
    spc_read_hit SMALLINT UNSIGNED,
    spc_read_miss SMALLINT UNSIGNED,
    spc_write_hit SMALLINT UNSIGNED,
    spc_write_miss SMALLINT UNSIGNED,
    spc_forced_flush SMALLINT UNSIGNED,
    spc_write_rehit SMALLINT UNSIGNED,
    fsw SMALLINT UNSIGNED,
    disk_crossings SMALLINT UNSIGNED,
   INDEX idx1 (lunid,time),
   INDEX sysIdIdx(sysid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE emc_lun_iosize (
    lunid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_lun(id)",
    time DATETIME NOT NULL,
    read_512 SMALLINT UNSIGNED NOT NULL,
    read_1k SMALLINT UNSIGNED NOT NULL,
    read_2k SMALLINT UNSIGNED NOT NULL,
    read_4k SMALLINT UNSIGNED NOT NULL,
    read_8k SMALLINT UNSIGNED NOT NULL,
    read_16k SMALLINT UNSIGNED NOT NULL,
    read_32k SMALLINT UNSIGNED NOT NULL,
    read_64k SMALLINT UNSIGNED NOT NULL,
    read_128k SMALLINT UNSIGNED NOT NULL,
    read_256k SMALLINT UNSIGNED NOT NULL,
    read_512k SMALLINT UNSIGNED NOT NULL,
    write_512 SMALLINT UNSIGNED NOT NULL,
    write_1k SMALLINT UNSIGNED NOT NULL,
    write_2k SMALLINT UNSIGNED NOT NULL,
    write_4k SMALLINT UNSIGNED NOT NULL,
    write_8k SMALLINT UNSIGNED NOT NULL,
    write_16k SMALLINT UNSIGNED NOT NULL,
    write_32k SMALLINT UNSIGNED NOT NULL,
    write_64k SMALLINT UNSIGNED NOT NULL,
    write_128k SMALLINT UNSIGNED NOT NULL,
    write_256k SMALLINT UNSIGNED NOT NULL,
    write_512k SMALLINT UNSIGNED NOT NULL,
    INDEX idx1 (lunid,time)
);

CREATE TABLE emc_rg (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    name VARCHAR(96) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX idx (sysid,name),
    PRIMARY KEY(id)
);

CREATE TABLE emc_pool (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    name VARCHAR(96) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX idx (sysid,name),
    PRIMARY KEY(id)
);

CREATE TABLE emc_rg_stats (
    rgid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_rg(id)",
    time DATETIME NOT NULL,
    utilization DECIMAL(5,2) NOT NULL,
    qlen DECIMAL(6,2) NOT NULL,
    qlenbusy DECIMAL(6,2),
    resptime DECIMAL(6,2) NOT NULL,
    servtime DECIMAL(6,2) NOT NULL,
    readbw SMALLINT UNSIGNED NOT NULL,
    readiops SMALLINT UNSIGNED NOT NULL,
    writebw SMALLINT UNSIGNED NOT NULL,
    writeiops SMALLINT UNSIGNED NOT NULL,
    avgseekdist SMALLINT UNSIGNED NOT NULL,
    INDEX idx1 (rgid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE emc_sp_stats (
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    sp ENUM ( 'A', 'B' ) NOT NULL,
    time DATETIME NOT NULL,
    utilization DECIMAL(5,2) NOT NULL,
    readbw SMALLINT UNSIGNED,
    readiops SMALLINT UNSIGNED,
    writebw SMALLINT UNSIGNED,
    writeiops SMALLINT UNSIGNED,
    spc_dirty TINYINT UNSIGNED,
    spc_flushbw SMALLINT UNSIGNED,
    cpu0_util DECIMAL(5,2),
    cpu1_util DECIMAL(5,2),
    cpu2_util DECIMAL(5,2),
    cpu3_util DECIMAL(5,2),
    spc_read_hr DECIMAL(5,2),
    spc_write_hr DECIMAL(5,2),
    INDEX sysTimeIdx (sysid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE emc_lun_disks (
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    lunid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_lun(id)",
    filedate DATE NOT NULL,
    diskids VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
    INDEX idx1 (sysid,lunid,filedate)
);

CREATE TABLE emc_lun_rg (
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    lunid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_lun(id)",
    filedate DATE NOT NULL,
    rgid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_rg(id)",
    INDEX idx1 (sysid,lunid,filedate)
);

CREATE TABLE emc_pool_lun (
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    poolid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_pool(id)",
    filedate DATE NOT NULL,
    lunid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_lun(id)",
    sizeAllocatedGB SMALLINT UNSIGNED,
    snapsSizeAllocatedGB SMALLINT UNSIGNED,
    metadataSizeAllocatedGB SMALLINT UNSIGNED,
    snapCount TINYINT UNSIGNED,
    dataReductionRatio FLOAT,
    INDEX idx1 (sysid,poolid,filedate)
);

CREATE TABLE emc_pool_cfg (
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    poolid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_pool(id)",
    filedate DATE NOT NULL,
    sizeGB MEDIUMINT UNSIGNED NOT NULL,
    usedGB MEDIUMINT UNSIGNED NOT NULL,
    subscribedGB MEDIUMINT UNSIGNED,
    numdisks SMALLINT UNSIGNED NOT NULL,
    raid VARCHAR(16) NOT NULL COLLATE latin1_general_cs,
    snapSizeUsedGB SMALLINT UNSIGNED,
    metadataSizeUsedGB SMALLINT UNSIGNED,
    dataReductionRatio FLOAT,
    INDEX idx1 (sysid,poolid,filedate)
);

CREATE TABLE emc_pool_rg (
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    poolid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_pool(id)",
    filedate DATE NOT NULL,
    rgid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_rg(id)",
    INDEX idx1 (sysid,poolid,filedate)
);

CREATE TABLE emc_filesystem (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    name VARCHAR(512) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX idx (sysid,name),
    PRIMARY KEY(id)
);

CREATE TABLE emc_filesystem_stats (
 fsid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_filesystem(id)",
 sysid SMALLINT UNSIGNED COMMENT "REFERENCES emc_sys(id)",
 time DATETIME NOT NULL,
 clientreadkb MEDIUMINT UNSIGNED NOT NULL,
 clientreads SMALLINT UNSIGNED NOT NULL,
 clientread_srvt SMALLINT UNSIGNED,
 clientwritekb MEDIUMINT UNSIGNED NOT NULL,
 clientwrites SMALLINT UNSIGNED NOT NULL,
 clientwrite_srvt SMALLINT UNSIGNED,
 readiops SMALLINT UNSIGNED NOT NULL,
 readkb MEDIUMINT UNSIGNED NOT NULL,
 writeiops SMALLINT UNSIGNED NOT NULL,
 writekb MEDIUMINT UNSIGNED NOT NULL,
 INDEX idx1 (fsid,time),
 INDEX sysIdIdx(sysid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE emc_nfsv4_ops (
 sysid SMALLINT UNSIGNED COMMENT "REFERENCES emc_sys(id)",
 time DATETIME NOT NULL,
 op ENUM( 'Access', 'BackChanCtl', 'BindConn', 'Clntid_Conf', 'Close',
  'Commit', 'Compound', 'Create', 'CreateSess', 'DelegPrg', 'DelegRet', 'DestroyClid',
  'DestroySess', 'ExchangeId', 'FreeStateid', 'GetAttr', 'GetDevInfo', 'GetDevList',
  'GetDirDeleg', 'GetFh', 'Illegal', 'LayoutCmmt', 'LayoutGet', 'LayoutRet', 'Link',
  'Lock', 'LockT', 'LockU', 'Lookup', 'Lookupp', 'NVerify', 'Null', 'Open', 'OpenAttr',
  'Open_Conf', 'Open_DG', 'PutFh', 'PutpubFh', 'PutrootFh', 'Read', 'ReadDir', 'ReadLink',
  'ReclaimCmpl', 'Rel_Lockown', 'Remove', 'Rename', 'Renew', 'Reserved', 'RestoreFh',
  'SaveFh', 'SecInfo', 'SecinfoNoName', 'Sequence', 'SetAttr', 'SetClntid', 'SetSsv',
  'TestStateid', 'Verify', 'WantDeleg', 'Write' ) NOT NULL,
 calls_a MEDIUMINT UNSIGNED NOT NULL,
 calls_b MEDIUMINT UNSIGNED NOT NULL,
 failures_a SMALLINT UNSIGNED NOT NULL,
 failures_b SMALLINT UNSIGNED NOT NULL,
 srvt_a SMALLINT UNSIGNED,
 srvt_b SMALLINT UNSIGNED,
 INDEX sysIdIdx(sysid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE emc_nas (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    name VARCHAR(512) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX idx (sysid,name),
    PRIMARY KEY(id)
);

CREATE TABLE emc_nas_state (
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    nasid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_nas(id)",
    filedate DATE NOT NULL,
    homesp ENUM('spa','spb'),
    currsp ENUM('spa','spb'),
    INDEX sysidDateIdx (sysid,filedate)
) PARTITION BY RANGE ( TO_DAYS(filedate) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE emc_filesystem_state (
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    fsid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_filesystem(id)",
    poolid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_pool(id)",
    nasid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_nas(id)",
    filedate DATE NOT NULL,
    sizeGB SMALLINT UNSIGNED NOT NULL,
    usedGB SMALLINT UNSIGNED NOT NULL,
    sizeAllocatedTotalGB SMALLINT UNSIGNED NOT NULL,
    metadataSizeAllocatedGB SMALLINT UNSIGNED NOT NULL,
    snapsSizeAllocatedGB SMALLINT UNSIGNED NOT NULL,
    snapCount TINYINT UNSIGNED NOT NULL,
    dataReductionRatio FLOAT NOT NULL,
    INDEX sysidDateIdx (sysid,filedate)
) PARTITION BY RANGE ( TO_DAYS(filedate) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE emc_nar (
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    time DATETIME NOT NULL,
    UNIQUE INDEX idx1 (sysid,time)
);

CREATE TABLE emc_site (
    sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    filedate DATE NOT NULL,
    INDEX idx1 (sysid,siteid)
) PARTITION BY RANGE ( TO_DAYS(filedate) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE emc_config (
 date DATE NOT NULL,
 sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
 dae   SMALLINT UNSIGNED NOT NULL,
 disks SMALLINT UNSIGNED NOT NULL,
 hwm   SMALLINT UNSIGNED,
 lwm   SMALLINT UNSIGNED,
 writecache SMALLINT UNSIGNED,
 readcache  SMALLINT UNSIGNED,
 totalmem   SMALLINT UNSIGNED NOT NULL,
 freemem    SMALLINT UNSIGNED,
 name VARCHAR(256) NULL NULL COLLATE latin1_general_cs,
 model VARCHAR(64) NULL NULL COLLATE latin1_general_cs,
 version VARCHAR(64) NULL NULL COLLATE latin1_general_cs,
 INDEX(sysid,date)
);

CREATE TABLE emc_snapshot (
 sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
 date DATE NOT NULL,
 lastsynced DATETIME NOT NULL,
 name VARCHAR(128) NULL NULL COLLATE latin1_general_cs,
 source_lun VARCHAR(128) NULL NULL COLLATE latin1_general_cs,
 INDEX(sysid,date)
);

CREATE TABLE emc_alerts (
 sysid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES emc_sys(id)",
 date DATE NOT NULL,
 msg VARCHAR(256) COLLATE latin1_general_cs,
 INDEX(sysid,date)
);

--
-- BG 2012-09-25
-- Fix to TBAC data. Now TSS data and in one table.
--
CREATE TABLE tss_instr_stats (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    auth_db_acivityactsetconns SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_aclentries SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_aclgentries SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_acts SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_actsetactsetconns SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_actsets SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_roles SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_tgtgrps SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_tgtgrptgtgrpconns SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_tgttgtgrpconns SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_userroleconns SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_db_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_general_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_general_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getallwacts_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getallwacts_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getallwacts_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getallwacts_sizes SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getallwacts_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getallwtgts_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getallwtgts_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getallwtgts_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getallwtgts_sizes SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getallwtgts_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getauthtgtgrps_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getauthtgtgrps_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getauthtgtgrps_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getauthtgtgrps_sizes SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getauthtgtgrps_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getauthtgts_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getauthtgts_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getauthtgts_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getauthtgts_sizes SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_getauthtgts_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_isauth_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_isauth_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_isauth_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_isauth_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_isauthbatch_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_isauthbatch_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_isauthbatch_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_isauthbatch_sizes SMALLINT UNSIGNED NULL DEFAULT NULL,
    auth_isauthbatch_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisctd_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisctd_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisctd_exc_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisctd_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisctd_exc_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisctd_imp_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisctd_imp_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisctd_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisctd_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisctd_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisdel_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisdel_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisdel_exc_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisdel_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisdel_exc_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisdel_imp_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisdel_imp_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisdel_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisdel_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_aclentryisdel_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_general_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_general_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_general_exc_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_general_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_general_exc_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_general_imp_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_general_imp_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_general_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_general_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_general_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsaddtotgtgrp_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsaddtotgtgrp_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsaddtotgtgrp_exc_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsaddtotgtgrp_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsaddtotgtgrp_exc_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsaddtotgtgrp_imp_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsaddtotgtgrp_imp_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsaddtotgtgrp_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsaddtotgtgrp_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsaddtotgtgrp_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsremfromtgtgrp_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsremfromtgtgrp_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsremfromtgtgrp_exc_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsremfromtgtgrp_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsremfromtgtgrp_exc_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsremfromtgtgrp_imp_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsremfromtgtgrp_imp_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsremfromtgtgrp_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsremfromtgtgrp_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtgrpsremfromtgtgrp_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsaddtotgtgrp_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsaddtotgtgrp_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsaddtotgtgrp_exc_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsaddtotgtgrp_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsaddtotgtgrp_exc_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsaddtotgtgrp_imp_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsaddtotgtgrp_imp_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsaddtotgtgrp_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsaddtotgtgrp_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsaddtotgtgrp_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsremfromtgtgrp_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsremfromtgtgrp_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsremfromtgtgrp_exc_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsremfromtgtgrp_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsremfromtgtgrp_exc_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsremfromtgtgrp_imp_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsremfromtgtgrp_imp_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsremfromtgtgrp_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsremfromtgtgrp_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_tgtsremfromtgtgrp_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersaddtorole_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersaddtorole_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersaddtorole_exc_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersaddtorole_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersaddtorole_exc_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersaddtorole_imp_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersaddtorole_imp_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersaddtorole_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersaddtorole_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersaddtorole_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersdel_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersdel_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersdel_exc_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersdel_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersdel_exc_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersdel_imp_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersdel_imp_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersdel_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersdel_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersdel_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersremfromrole_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersremfromrole_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersremfromrole_exc_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersremfromrole_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersremfromrole_exc_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersremfromrole_imp_tgts SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersremfromrole_imp_users SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersremfromrole_realexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersremfromrole_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    notif_usersremfromrole_waittime SMALLINT UNSIGNED NULL DEFAULT NULL,
    pw_general_status SMALLINT UNSIGNED NULL DEFAULT NULL,
    pw_getpassword_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    pw_getpassword_exc_calls SMALLINT UNSIGNED NULL DEFAULT NULL,
    pw_getpassword_exc_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    pw_getpassword_totexectime SMALLINT UNSIGNED NULL DEFAULT NULL,
    INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

-- OSSRC 12 WP00856: WRAN: Re-parenting solution improvements single drag and drop application [28-09-2012 RK]
-- TDDDCDDP-285: DDP to  provide drill through for RRPM
CREATE TABLE rrpm_project_names (
        id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
        PRIMARY KEY(id)
);

-- RRPM: Overall Project data
CREATE TABLE rrpm_opd
(
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES rrpm_project_names(id)",
    numberNodesSuccessful MEDIUMINT UNSIGNED,
    numberNodes MEDIUMINT UNSIGNED,
    projectDuration VARCHAR(8) COLLATE latin1_general_cs,
    numberKPIROPs MEDIUMINT UNSIGNED,
    numberCells MEDIUMINT UNSIGNED,
    numberNeighbourRNC MEDIUMINT UNSIGNED,
    projectName VARCHAR(255) COLLATE latin1_general_cs,
    projectEndTime TIME,
    numberRelation MEDIUMINT UNSIGNED,
    numberNodesFailed MEDIUMINT UNSIGNED,
    numberNodesRemoved MEDIUMINT UNSIGNED,
    lockingPolicy ENUM('SOFT_LOCK', 'HARD_LOCK') COLLATE latin1_general_cs,
    projectStartTime TIME
);

-- RRPM: Project Phase data
CREATE TABLE rrpm_ppd
(
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES rrpm_project_names(id)",
    PhaseStartTime TIME,
    DPEnabled ENUM('YES', 'NO', 'NA') COLLATE latin1_general_cs,
    PhaseName ENUM(
        'GENERATE FILES',
        'PRECUTOVER1',
        'PRECUTOVER2',
        'CUTOVER 1',
        'CUTOVER 2',
        'CUTOVER 3',
        'POST CUTOVER',
        'ROLLBACK GENERATE FILES',
        'ROLLBACK CUTOVER 1',
        'ROLLBACK CUTOVER 2',
        'ROLLBACK CUTOVER 3',
        'ROLLBACK POST CUTOVER',
        'GENERATE_FILES',
        'CUTOVER1',
        'CUTOVER2',
        'CUTOVER3',
        'POSTCUTOVER'
    ) COLLATE latin1_general_cs,
    NumberRBSFailed SMALLINT UNSIGNED,
    PhaseDuration VARCHAR(8) COLLATE latin1_general_cs,
    PhaseEndTime TIME,
    NumberRBSProcessed SMALLINT UNSIGNED
);

-- RRPM: Project Node Data
CREATE TABLE rrpm_prd
(
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES rrpm_project_names(id)",
    NumberEUtranFreqRelationsDeleted SMALLINT UNSIGNED,
    RBSName VARCHAR(255) COLLATE latin1_general_cs,
    NumberInterUtranRelationsDeleted SMALLINT UNSIGNED,
    ManageTargetRBSDuration VARCHAR(8) COLLATE latin1_general_cs,
    NumberCells MEDIUMINT UNSIGNED,
    NumberIntraUtranRelationsCreated SMALLINT UNSIGNED,
    RBSType ENUM('IP','ATM') COLLATE latin1_general_cs,
    UnManageSourceRBSDuration VARCHAR(8) COLLATE latin1_general_cs,
    RemoveSourceRBSDuration VARCHAR(8) COLLATE latin1_general_cs,
    NumberInterUtranRelationsCreated SMALLINT UNSIGNED,
    NumberGSMRelationsDeleted SMALLINT UNSIGNED,
    AddTargetRBSDuration VARCHAR(8) COLLATE latin1_general_cs,
    NumberInterOSSUtranRelationsDeleted SMALLINT UNSIGNED,
    NumberCoverageRelationsDeleted SMALLINT UNSIGNED,
    SourceRNC VARCHAR(255) COLLATE latin1_general_cs,
    TargetRNC VARCHAR(255) COLLATE latin1_general_cs,
    NumberGSMRelationsCreated SMALLINT UNSIGNED,
    NumberEUtranFreqRelationsCreated SMALLINT UNSIGNED,
    NumberIntraUtranRelationsUpdated SMALLINT UNSIGNED,
    NumberCoverageRelationsCreated SMALLINT UNSIGNED,
    NumberInterOSSUtranRelationsCreated SMALLINT UNSIGNED,
    NumberInterUtranRelationsUpdated SMALLINT UNSIGNED,
    NumberIntraUtranRelationsDeleted SMALLINT UNSIGNED,
    RBSStatus ENUM('NOT_STARTED','REPARENT_SUCCESSFUL','REPARENT_FAILED','REMOVED') COLLATE latin1_general_cs
);

-- RRPM: Removed RBS data
CREATE TABLE rrpm_rrd
(
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES rrpm_project_names(id)",
    RemoveRBSDuration VARCHAR(8) COLLATE latin1_general_cs,
    RBSName VARCHAR(255) COLLATE latin1_general_cs,
    RemoveRBSStartTime TIME,
    PhaseRemoved ENUM(
        'GENERATE FILES',
        'PRECUTOVER1',
        'PRECUTOVER2',
        'CUTOVER1',
        'CUTOVER2'
    ) COLLATE latin1_general_cs,
    RemoveRBSEndTime TIME
);

-- RRPM: Project PreCheck data
CREATE TABLE rrpm_ppcd
(
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES rrpm_project_names(id)",
    preCheckName VARCHAR(255) COLLATE latin1_general_cs,
    preCheckStartTime TIME,
    preCheckEndTime TIME,
    preCheckDuration VARCHAR(8) COLLATE latin1_general_cs
);

-- RRPM: Project KPI data
CREATE TABLE rrpm_kd
(
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    nameid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES rrpm_project_names(id)",
    kpiName VARCHAR(255)
);

CREATE TABLE eniq_stats_source (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX idx (name),
    PRIMARY KEY(id)
);

CREATE TABLE eniq_stats_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX idx (name),
    PRIMARY KEY(id)
);

CREATE TABLE eniq_stats_adaptor_totals (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 day DATE NOT NULL,
 sourceid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_stats_source(id)",
 typeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_stats_types(id)",
 rows_avg DECIMAL(11,2) UNSIGNED NOT NULL,
 rows_max MEDIUMINT UNSIGNED NOT NULL,
 rows_sum INT UNSIGNED NOT NULL,
 cntr_avg DECIMAL(11,2) UNSIGNED NOT NULL,
 cntr_max MEDIUMINT UNSIGNED NOT NULL,
 cntr_sum BIGINT UNSIGNED NOT NULL,
 rop_count MEDIUMINT UNSIGNED NOT NULL,
 workflow_type SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_stats_workflow_types(workflow_type_id)",
 trigger_count TINYINT UNSIGNED,
 INDEX idx1 (siteid,day)
);

CREATE TABLE eniq_stats_adaptor_sessions (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 sourceid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_stats_source(id)",
 timeslot DATETIME NOT NULL,
 minstart DATETIME NOT NULL,
 maxend DATETIME NOT NULL,
 cntr_sum INT UNSIGNED NOT NULL,
 workflow_type SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_stats_workflow_types(workflow_type_id)",
 INDEX siteidTimeslotIdx (siteid,timeslot)
) PARTITION BY RANGE ( TO_DAYS(timeslot) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_loader_sessions (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 typeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_stats_types(id)",
 minstart DATETIME NOT NULL,
 maxend DATETIME NOT NULL,
 total_rows INT UNSIGNED NOT NULL,
 INDEX siteidMinstartIdx (siteid,minstart)
) PARTITION BY RANGE ( TO_DAYS(minstart) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_loader_running (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 running TINYINT UNSIGNED NOT NULL,
 INDEX siteidTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_aggregator_sessions (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 typeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_stats_types(id)",
 timelevel ENUM('DAY','COUNT','RANKBH','DAYBH'),
 start DATETIME NOT NULL,
 end DATETIME NOT NULL,
 rowcount INT UNSIGNED NOT NULL,
 INDEX idx1 (siteid)
) PARTITION BY RANGE ( TO_DAYS(start) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_aggregator_running (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 running TINYINT UNSIGNED NOT NULL,
 INDEX siteidTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

-- OSS-RC 13 WP00488: CEX Usability Storage
CREATE TABLE cex_usage_stats(
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    event_start TIMESTAMP NOT NULL,
    event_stop TIMESTAMP NULL,
    event_start_millis INT UNSIGNED NOT NULL,
    event_stop_millis INT UNSIGNED NULL,
    event_type MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES cex_event_types(id)",
    event_name MEDIUMINT UNSIGNED NULL COMMENT "REFERENCES cex_event_names(id)",
    event_id MEDIUMINT UNSIGNED NULL COMMENT "REFERENCES cex_event_ids(id)",
    INDEX siteIdEventStart(siteid,event_start)
);


-- contains mappings of names of processes to their relevant IDs
CREATE TABLE cex_event_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(70) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);


-- contains mappings of names of processes to their relevant IDs
CREATE TABLE cex_event_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(70) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);


-- contains mappings of names of processes to their relevant IDs
CREATE TABLE cex_event_ids (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(70) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

-- OSS-RC 13 WP00256 - NMA instrumentation
CREATE TABLE nma_node_sync_status_data(
    date TIMESTAMP NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    sync_success SMALLINT UNSIGNED,
    sync_failed SMALLINT UNSIGNED,
    unsynced SMALLINT UNSIGNED,
    top_sync SMALLINT UNSIGNED,
    att_sync SMALLINT UNSIGNED,
    sgsn_mme_ongoing TINYINT UNSIGNED,
    epg_ongoing TINYINT UNSIGNED,
    h_two_s_ongoing TINYINT UNSIGNED,
    mtas_ongoing TINYINT UNSIGNED,
    cscf_ongoing TINYINT UNSIGNED,
    prbs_ongoing TINYINT UNSIGNED,
    INDEX (date,siteid)
);

CREATE TABLE nma_sync_by_node_data(
    date TIMESTAMP NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    neid MEDIUMINT UNSIGNED NOT NULL,
    failure SMALLINT UNSIGNED,
    success SMALLINT UNSIGNED,
    INDEX (siteid,date)
);

CREATE TABLE nma_stats_data(
    date TIMESTAMP NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    sync_success SMALLINT UNSIGNED NOT NULL,
    alive_nodes SMALLINT UNSIGNED NOT NULL,
    total_node_sync SMALLINT UNSIGNED NOT NULL,
    node_count SMALLINT UNSIGNED NOT NULL,
    INDEX (date,siteid)
);

CREATE TABLE nma_notif_recieved_data(
    date TIMESTAMP NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    event_type VARCHAR(10) NOT NULL COLLATE latin1_general_cs,
    node_type VARCHAR(10) NOT NULL COLLATE latin1_general_cs,
    mo VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    attribute VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    count MEDIUMINT UNSIGNED,
    INDEX (date,siteid)
);

CREATE TABLE nma_con_status_data(
    date TIMESTAMP NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    neid MEDIUMINT UNSIGNED NOT NULL,
    no_connect SMALLINT UNSIGNED,
    no_disconnect SMALLINT UNSIGNED,
    INDEX (date,siteid)
);

CREATE TABLE nma_notif_handling_data(
    date TIMESTAMP NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    notif_in_buffer MEDIUMINT UNSIGNED NOT NULL,
    notif_rec_in_fifteen_min MEDIUMINT UNSIGNED NOT NULL,
    avg_ttp_notif SMALLINT UNSIGNED NOT NULL,
    mx_ttp_notif MEDIUMINT UNSIGNED NOT NULL,
    INDEX (date,siteid)
);

CREATE TABLE nma_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  nameid SMALLINT UNSIGNED NULL COMMENT "REFERENCES jmx_names(id)",
  NESup_Execution MEDIUMINT UNSIGNED,
  NESup_Waiting MEDIUMINT UNSIGNED,
  NoOfNotificationInBuffer MEDIUMINT UNSIGNED,
  NoOfPartiallySynchedNodes MEDIUMINT UNSIGNED,

  NoOfSynchOngoingForBBSC MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForBSP MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForCBA_REF MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForCSCF MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForDSC MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForDUA_S MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForEPG MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForH2S MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForMSRBS_V1 MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForMSRBS_V2 MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForMTAS MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForPGM MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForPRBS MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForSAPC MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForSASN MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForSBG MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForSDNC_P MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForSGSN MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForSGSN_MME MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForTCU MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForUPG MEDIUMINT UNSIGNED,
  NoOfSynchOngoingForWCG MEDIUMINT UNSIGNED,

  NoOfUnSynchedNodes MEDIUMINT UNSIGNED,
  NumberOfTotalAliveNodes MEDIUMINT UNSIGNED,
  NumberOfTotalNodesSynched MEDIUMINT UNSIGNED,
  TotalFailedPings MEDIUMINT UNSIGNED,
  TotalNotificationsReceived MEDIUMINT UNSIGNED,
  TotalNumberOfNodes MEDIUMINT UNSIGNED,
  TotalPingTime MEDIUMINT UNSIGNED,
  TotalSuccessfulPings MEDIUMINT UNSIGNED,
  TotalTimeTakenToProcessNotifications MEDIUMINT UNSIGNED,
  INDEX (siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE nma_sync_success
(
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 mcid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mc_names(id)",
 neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
 twait INT UNSIGNED,
 tneconnect SMALLINT UNSIGNED,
 tneresp INT UNSIGNED,
 tneclose SMALLINT UNSIGNED,
 tnesub SMALLINT UNSIGNED,
 tnma INT UNSIGNED,
 ttotal INT UNSIGNED,
 treadmo INT UNSIGNED,
 tcreatemo SMALLINT UNSIGNED,
 tupdatemo SMALLINT UNSIGNED,
 tdeletemo SMALLINT UNSIGNED,
 ncreatedmo SMALLINT UNSIGNED,
 nupdatedmo SMALLINT UNSIGNED,
 ndeletedmo SMALLINT UNSIGNED,
 INDEX (siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE nma_notifrec
(
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  date DATE NOT NULL,
  mcid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mc_names(id)",
  eventtype ENUM( 'AVC', 'CREATE', 'DELETE' ) NOT NULL COLLATE latin1_general_cs,
  nodetype ENUM( 'CSCF', 'DSC', 'EPG', 'H2S', 'MSRBS_V1', 'MTAS', 'PRBS', 'SAPC', 'SGSN',
                 'BBSC', 'BSP', 'CBA_REF', 'DUA_S', 'MSRBS_V2', 'PGM', 'SASN', 'SBG',
                 'SDNC_P', 'SGSN_MME', 'TCU', 'UPG', 'WCG' ) NOT NULL COLLATE latin1_general_cs,
  moid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
  attribid SMALLINT UNSIGNED COMMENT "REFERENCES nead_attrib_names(id)",
  count BIGINT UNSIGNED NOT NULL,
 INDEX myidx (siteid,date)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE netconf_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  nameid SMALLINT UNSIGNED NULL COMMENT "REFERENCES jmx_names(id)",
  ActionCount MEDIUMINT UNSIGNED,
  ActiveSessions MEDIUMINT UNSIGNED,
  BytesRx MEDIUMINT UNSIGNED,
  BytesTx MEDIUMINT UNSIGNED,
  NotificationCount MEDIUMINT UNSIGNED,
  ReqCRUDProcessTime MEDIUMINT UNSIGNED,
  ReqRPCConstructionTime MEDIUMINT UNSIGNED,
  ReqTRPCProcessTime MEDIUMINT UNSIGNED,
  RequestCount MEDIUMINT UNSIGNED,
  ResCRUDProcessTime MEDIUMINT UNSIGNED,
  ResRPCExtractionTime MEDIUMINT UNSIGNED,
  ResTRPCProcessTime MEDIUMINT UNSIGNED,
  ResponseCount MEDIUMINT UNSIGNED,
  SimultaneousSessions MEDIUMINT UNSIGNED,
  INDEX (siteid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

-- TDDDCDDP-377: Process and store Software Inventory (RPM) from LITP Server in DDP [27-7-2013 CH]

CREATE TABLE tor_rstate_list (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(70) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE tor_app_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(70) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id)
);

CREATE TABLE cs_notifications (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 type ENUM('AVC','CREATE','DELETE','ASSOC_CREATE','ASSOC_DELETE'),
 csid   SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES cs_names(id)",
 cs_application_nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES cs_application_name (id)",
 modelid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES model_names(id)",
 moid SMALLINT NOT NULL COMMENT "REFERENCES mo_names(id)",
 attribid SMALLINT UNSIGNED COMMENT "REFERENCES nead_attrib_names(id)",
 count INT UNSIGNED NOT NULL,
 totalsize INT UNSIGNED NOT NULL,
 maxsize INT UNSIGNED NOT NULL,
 INDEX idx1 (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE son_mo (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 moid SMALLINT NOT NULL COMMENT "REFERENCES mo_names(id)",
 created_anr INT UNSIGNED NOT NULL,
 deleted_anr INT UNSIGNED NOT NULL,
 modified_anr INT UNSIGNED NOT NULL,
 created_x2 INT UNSIGNED NOT NULL,
 deleted_x2 INT UNSIGNED NOT NULL,
 modified_x2 INT UNSIGNED NOT NULL,
 INDEX idx1 (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE son_rate (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 created_anr INT UNSIGNED NOT NULL,
 deleted_anr INT UNSIGNED NOT NULL,
 modified_anr INT UNSIGNED NOT NULL,
 created_x2 INT UNSIGNED NOT NULL,
 deleted_x2 INT UNSIGNED NOT NULL,
 modified_x2 INT UNSIGNED NOT NULL,
 INDEX idx1 (siteid,time)
);

CREATE TABLE eniq_streaming (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 rop DATETIME NOT NULL,
 type ENUM('ctr','ctum','cctr') NOT NULL,
 nodes SMALLINT UNSIGNED NOT NULL,
 totalvolmb  INT UNSIGNED NOT NULL,
 totalevents INT UNSIGNED NOT NULL,
 peakvolkb   INT UNSIGNED NOT NULL,
 peakevents INT UNSIGNED NOT NULL,
 INDEX (siteid,rop)
);

CREATE TABLE eniq_folder_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX idx (name),
  PRIMARY KEY (id)
);

CREATE TABLE eniq_workflowgroup_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX idx (name),
  PRIMARY KEY (id)
);

CREATE TABLE eniq_workflow_executions (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 fldrid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_folder_names(id)",
 grpid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflowgroup_names(id)",
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 tload DATETIME NOT NULL,
 trun DATETIME NOT NULL,
 tidle DATETIME NOT NULL,
 aborted TINYINT UNSIGNED NOT NULL,
 INDEX sitetloadIdx(siteid,tload)
) PARTITION BY RANGE ( TO_DAYS(tload) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_streaming_ctr_collector (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 FilesCTR SMALLINT UNSIGNED NOT NULL,
 EventsCTR INT UNSIGNED NOT NULL,
 BytesCTR INT UNSIGNED NOT NULL,
 FilesCCTR SMALLINT UNSIGNED NOT NULL,
 EventsCCTR INT UNSIGNED NOT NULL,
 BytesCCTR INT UNSIGNED NOT NULL,
 INDEX (siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_ctr_eventdistrib (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 eventId SMALLINT UNSIGNED NOT NULL,
 count INT UNSIGNED NOT NULL,
 precent DECIMAL(5,2),
 INDEX(siteid,time)
);

CREATE TABLE eniq_streaming_ctum_collector (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 Files SMALLINT UNSIGNED NOT NULL,
 Events INT UNSIGNED NOT NULL,
 Bytes INT UNSIGNED NOT NULL,
 INDEX (siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_ltees_counter (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 Files SMALLINT UNSIGNED NOT NULL,
 Events INT UNSIGNED NOT NULL,
 Bytes INT UNSIGNED NOT NULL,
 BytesOnDisk INT UNSIGNED NOT NULL,
 FilesToReCreateSymLink SMALLINT UNSIGNED NOT NULL,
 FilesToArchive SMALLINT UNSIGNED NOT NULL,
 ProcessedCount SMALLINT UNSIGNED NOT NULL,
 Delay INT UNSIGNED NOT NULL,
 INDEX (siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_sgeh_processing_nfs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 Files SMALLINT UNSIGNED NOT NULL,
 Events INT UNSIGNED NOT NULL,
 Bytes INT UNSIGNED NOT NULL,
 Delay INT UNSIGNED NOT NULL,
 Succ4 INT UNSIGNED NOT NULL,
 Err4 INT UNSIGNED NOT NULL,
 Succ23 INT UNSIGNED NOT NULL,
 Err23 INT UNSIGNED NOT NULL,
 CorruptedEvents INT UNSIGNED NOT NULL,
 INDEX sitetimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_lteefa_processor (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 Files SMALLINT UNSIGNED NOT NULL,
 Events INT UNSIGNED NOT NULL,
 Bytes INT UNSIGNED NOT NULL,
 BytesOnDisk INT UNSIGNED NOT NULL,
 Delay INT UNSIGNED NOT NULL,
 CFA INT UNSIGNED NOT NULL,
 HFA INT UNSIGNED NOT NULL,
 INDEX (siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_streaming_dvtp_collector (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 Files SMALLINT UNSIGNED NOT NULL,
 Events INT UNSIGNED NOT NULL,
 Bytes INT UNSIGNED NOT NULL,
 INDEX (siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_dvtp_processor (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 Files SMALLINT UNSIGNED NOT NULL,
 Events INT UNSIGNED NOT NULL,
 Bytes INT UNSIGNED NOT NULL,
 BytesOnDisk INT UNSIGNED NOT NULL,
 Delay INT UNSIGNED NOT NULL,
 INDEX (siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_dvtp_eventdistrib (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 eventId SMALLINT UNSIGNED NOT NULL,
 count INT UNSIGNED NOT NULL,
 precent DECIMAL(5,2),
 INDEX(siteid,time)
);

-- TORF-12296: Update DDP DB tables to handle new format of Streaming csv file

CREATE TABLE tor_streaming_datapath_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 INDEX nameIdx( name ),
 PRIMARY KEY(id)
);

CREATE TABLE tor_stream_out_datapath_id (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 INDEX nameIdx( name ),
 PRIMARY KEY(id)
);

CREATE TABLE tor_stream_out_ipaddress (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 INDEX nameIdx( name ),
 PRIMARY KEY(id)
);

CREATE TABLE tor_stream_out_ports (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 INDEX nameIdx( name ),
 PRIMARY KEY(id)
);

CREATE TABLE stream_in_active_connections (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  datapath SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_streaming_datapath_names(id)",
  cpid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_stream_out_datapath_id(id)",
  count MEDIUMINT UNSIGNED NOT NULL,
  min_rate FLOAT(13,4) UNSIGNED,
  mean_rate FLOAT(13,4) UNSIGNED,
  five_min_rate FLOAT(13,4) UNSIGNED,
  fif_min_rate FLOAT(13,4) UNSIGNED,
  UNIQUE INDEX siteServerIdx(time,siteid,serverid,datapath,cpid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE stream_in_created_connections (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  datapath SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_streaming_datapath_names(id)",
  cpid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_stream_out_datapath_id(id)",
  count MEDIUMINT UNSIGNED NOT NULL,
  min_rate FLOAT(13,4) UNSIGNED,
  mean_rate FLOAT(13,4) UNSIGNED,
  five_min_rate FLOAT(13,4) UNSIGNED,
  fif_min_rate FLOAT(13,4) UNSIGNED,
  UNIQUE INDEX siteServerIdx(time,siteid,serverid,datapath,cpid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE stream_in_events (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  datapath SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_streaming_datapath_names(id)",
  cpid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_stream_out_datapath_id(id)",
  count MEDIUMINT UNSIGNED NOT NULL,
  min_rate FLOAT(13,4) UNSIGNED,
  mean_rate FLOAT(13,4) UNSIGNED,
  five_min_rate FLOAT(13,4) UNSIGNED,
  fif_min_rate FLOAT(13,4) UNSIGNED,
  UNIQUE INDEX siteServerIdx(time,siteid,serverid,datapath,cpid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE stream_in_dropped_connections (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  datapath SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_streaming_datapath_names(id)",
  cpid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_stream_out_datapath_id(id)",
  count MEDIUMINT UNSIGNED NOT NULL,
  min_rate FLOAT(13,4) UNSIGNED,
  mean_rate FLOAT(13,4) UNSIGNED,
  five_min_rate FLOAT(13,4) UNSIGNED,
  fif_min_rate FLOAT(13,4) UNSIGNED,
  UNIQUE INDEX siteServerIdx(time,siteid,serverid,datapath,cpid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE stream_out_events_sent (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  datapath SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_streaming_datapath_names(id)",
  datapath_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_stream_out_datapath_id(id)",
  count MEDIUMINT UNSIGNED NOT NULL,
  min_rate FLOAT(13,4) UNSIGNED,
  mean_rate FLOAT(13,4) UNSIGNED,
  five_min_rate FLOAT(13,4) UNSIGNED,
  fif_min_rate FLOAT(13,4) UNSIGNED,
  UNIQUE INDEX tssddIdx(time,siteid,serverid,datapath,datapath_id)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE stream_out_events_filtered (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  datapath SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_streaming_datapath_names(id)",
  datapath_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_stream_out_datapath_id(id)",
  count MEDIUMINT UNSIGNED NOT NULL,
  min_rate FLOAT(13,4) UNSIGNED,
  mean_rate FLOAT(13,4) UNSIGNED,
  five_min_rate FLOAT(13,4) UNSIGNED,
  fif_min_rate FLOAT(13,4) UNSIGNED,
  UNIQUE INDEX tssddIdx(time,siteid,serverid,datapath,datapath_id)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE stream_out_events_lost (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  datapath SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_streaming_datapath_names(id)",
  datapath_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_stream_out_datapath_id(id)",
  count MEDIUMINT UNSIGNED NOT NULL,
  min_rate FLOAT(13,4) UNSIGNED,
  mean_rate FLOAT(13,4) UNSIGNED,
  five_min_rate FLOAT(13,4) UNSIGNED,
  fif_min_rate FLOAT(13,4) UNSIGNED,
  UNIQUE INDEX tssddIdx(time,siteid,serverid,datapath,datapath_id)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

-- TOR version table for standalone TOR

CREATE TABLE tor_ver_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  PRIMARY KEY(id)
);

CREATE TABLE tor_ver (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  verid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_ver_names(id)",
  INDEX siteidDateIdx (siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE stream_in_south_bound_dropped_events (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  datapath SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_streaming_datapath_names(id)",
  cpid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_stream_out_datapath_id(id)",
  count MEDIUMINT UNSIGNED NOT NULL,
  min_rate FLOAT(13,4) UNSIGNED,
  mean_rate FLOAT(13,4) UNSIGNED,
  five_min_rate FLOAT(13,4) UNSIGNED,
  fif_min_rate FLOAT(13,4) UNSIGNED,
  UNIQUE INDEX tssdcIdx(time,siteid,serverid,datapath,cpid)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE glassfish_stats
(
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  hostip VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  userid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES glassfish_users(id)",
  urlid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES resource_urls(id)",
  time_dimension ENUM('15','30','60','120','360','720','1440','10080') NOT NULL,
  type VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  node VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  imsi BIGINT UNSIGNED,
  tac BIGINT UNSIGNED,
  msisdn BIGINT UNSIGNED,
  groupname VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  display ENUM('grid','chart') NOT NULL,
  tzoffset VARCHAR(5) NOT NULL COLLATE latin1_general_cs,
  maxrows SMALLINT UNSIGNED,
  response_status SMALLINT UNSIGNED,
  response_length SMALLINT UNSIGNED,
  response_time SMALLINT UNSIGNED,
  cookie VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  user_agent_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES user_agent(id)",
  browserid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES browser_name(id)",
  browser_version VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  http_version VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  INDEX siteidTimeIdx (siteid,time)
);

CREATE TABLE glassfish_users
(
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  PRIMARY KEY(id)
);

CREATE TABLE resource_urls
(
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  url VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  PRIMARY KEY(id)
);

CREATE TABLE browser_name
(
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  browser_name VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  PRIMARY KEY(id)
);

CREATE TABLE user_agent
(
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_agent VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  PRIMARY KEY(id)
);

CREATE TABLE enm_logs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 entries INT UNSIGNED NOT NULL,
 size INT UNSIGNED,
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ne (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  netypeid SMALLINT UNSIGNED COMMENT "REFERENCES ne_types(id)",
  UNIQUE INDEX siteNameIdx (siteid,name),
  PRIMARY KEY(id)
);

CREATE TABLE enm_cm_syncs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 start DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 type ENUM('FULL','DELTA') DEFAULT 'FULL',
 t_ne_top MEDIUMINT UNSIGNED NOT NULL,
 t_dps_top  MEDIUMINT UNSIGNED NOT NULL,
 t_ne_attr  MEDIUMINT UNSIGNED NOT NULL,
 t_dps_attr MEDIUMINT UNSIGNED NOT NULL,
 t_complete MEDIUMINT UNSIGNED NOT NULL,
 n_mo  MEDIUMINT UNSIGNED NOT NULL,
 n_mo_created INT UNSIGNED,
 n_mo_deleted INT UNSIGNED,
 n_attr  MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,start)
) PARTITION BY RANGE ( TO_DAYS(start) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_med_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 attributeSyncInvocations SMALLINT UNSIGNED NOT NULL,
 topologySyncInvocations SMALLINT UNSIGNED NOT NULL,
 dpsInvocationAttributeSync SMALLINT UNSIGNED NOT NULL,
 dpsInvocationController SMALLINT UNSIGNED NOT NULL,
 dpsInvocationTopologySync SMALLINT UNSIGNED NOT NULL,
 dpsCounterForSuccessfulSync SMALLINT UNSIGNED NOT NULL,
 dpsNumberOfFailedSyncs SMALLINT UNSIGNED NOT NULL,
 numberOfFailedSyncs SMALLINT UNSIGNED NOT NULL,
 dpsSuccessfulDeltaSync SMALLINT UNSIGNED,
 dpsFailedDeltaSync SMALLINT UNSIGNED,
 dpsDeltaInvocationAttributeSync SMALLINT UNSIGNED,
 numberOfSuccessfulMibUpgrade SMALLINT UNSIGNED,
 numberOfFailedMibUpgrade SMALLINT UNSIGNED,
 INDEX siteInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_supervision (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 type ENUM('CPP','COMECIM','ROUTER','SNMP','APG') NOT NULL DEFAULT 'CPP',
 supervised MEDIUMINT UNSIGNED,
 synced MEDIUMINT UNSIGNED,
 subscribed MEDIUMINT UNSIGNED,
 INDEX siteInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_pmic_datatypes (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx (name),
  PRIMARY KEY(id)
);

CREATE TABLE enm_pmic_rop_fls (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 fcs DATETIME NOT NULL,
 rop ENUM( '15MIN', '1MIN', '1440MIN', '5MIN', '30MIN', '60MIN', '720MIN' ) NOT NULL DEFAULT '15MIN',
 netypeid SMALLINT UNSIGNED COMMENT "REFERENCES ne_types(id)",
 datatypeid SMALLINT UNSIGNED COMMENT "REFERENCES enm_pmic_datatypes(id)",
 first_offset SMALLINT UNSIGNED NOT NULL,
 last_offset SMALLINT UNSIGNED NOT NULL,
 files MEDIUMINT UNSIGNED NOT NULL,
 volumekb INT UNSIGNED NOT NULL,
 outside MEDIUMINT UNSIGNED NOT NULL,
 transfertype ENUM( 'PULL', 'PUSH', 'GENERATION' ) NOT NULL DEFAULT 'PULL' COLLATE latin1_general_cs,
 INDEX siteTimeIdx(siteid,fcs)
) PARTITION BY RANGE ( TO_DAYS(fcs) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_pmic_rop (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 fcs DATETIME NOT NULL,
 type ENUM( '15MIN', '1MIN', '1440MIN', '5MIN', '30MIN', '60MIN', '720MIN' ) NOT NULL DEFAULT '15MIN',
 duration SMALLINT UNSIGNED NOT NULL,
 files_succ MEDIUMINT UNSIGNED NOT NULL,
 files_fail MEDIUMINT UNSIGNED NOT NULL,
 mb_txfr    MEDIUMINT UNSIGNED NOT NULL,
 mb_stor    MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteRopIdx(siteid,fcs)
);

CREATE TABLE enm_pmic_filecollection (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time  DATETIME NOT NULL,
 files_succ MEDIUMINT UNSIGNED NOT NULL,
 files_fail MEDIUMINT UNSIGNED NOT NULL,
 mb_txfr    MEDIUMINT UNSIGNED NOT NULL,
 mb_stor    MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteRopIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_pmic_subs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 administrationState ENUM('ACTIVE','INACTIVE'),
 type ENUM('CELLTRACE','CELLTRAFFIC','CONTINUOUSCELLTRACE','CTUM','EBM','GPEH','STATISTICAL','UETR','UETRACE','PRODUCTDATA'),
 rop ENUM('ONE_MIN', 'FIFTEEN_MIN' ),
 numberOfNodes SMALLINT UNSIGNED,
 name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 scannerStates VARCHAR(256) COLLATE latin1_general_cs,
 scannerErrorCodes VARCHAR(256) COLLATE latin1_general_cs,
 cellTypes ENUM('ASR','CELLTRACE','CELLTRACE_AND_EBSL_FILE','CELLTRACE_AND_EBSL_STREAM','CELLTRACE_NRAN','CELLTRACE_NRAN_AND_EBSN_FILE','CELLTRACE_NRAN_AND_EBSN_STREAM','EBSL_STREAM','ESN','NRAN_EBSN_STREAM'),
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fmack (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 ackAlarmCount MEDIUMINT UNSIGNED NOT NULL,
 failedAckAlarmCount MEDIUMINT UNSIGNED NOT NULL,
 unAckAlarmCount MEDIUMINT UNSIGNED  NOT NULL,
 failedUnAckAlarmCount MEDIUMINT UNSIGNED NOT NULL,
 failedClearAlarmCount MEDIUMINT UNSIGNED NOT NULL,
 manualClearAlarmCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE backlog_interface
(
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  backlog_intf VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  PRIMARY KEY(id)
);

CREATE TABLE backlog_monitoring_stats
(
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  intf_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES backlog_interface(id)",
  total_backlog SMALLINT UNSIGNED,
  files_in_backlog SMALLINT UNSIGNED,
  files_in_process SMALLINT UNSIGNED,
  processing_time SMALLINT UNSIGNED,
  file_size MEDIUMINT UNSIGNED,
  INDEX (siteid,time)
);

CREATE TABLE vdb_volumes
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    volumename VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
    sysname VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id),
    UNIQUE KEY(volumename, sysname)
);

CREATE TABLE cm_export (
    jobid MEDIUMINT UNSIGNED NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL,
    export_start_date_time DATETIME NOT NULL,
    export_end_date_time DATETIME NOT NULL,
    total_mos INT UNSIGNED,
    typeid SMALLINT UNSIGNED,
    expected_nodes MEDIUMINT UNSIGNED,
    exported MEDIUMINT UNSIGNED,
    not_exported MEDIUMINT UNSIGNED,
    nodes_no_match_found MEDIUMINT UNSIGNED,
    job_name VARCHAR(255) COLLATE latin1_general_cs,
    source_nameid MEDIUMINT UNSIGNED,
    filter_choice_nameid MEDIUMINT UNSIGNED,
    status VARCHAR(255) NOT NULL,
    merge_start_time DATETIME,
    merge_duration MEDIUMINT UNSIGNED,
    master_server_id VARCHAR(32),
    export_file  VARCHAR(255),
    export_non_synchronized_nodes ENUM('true','false'),
    compression_type ENUM('ZIP','NONE','GZIP'),
    elapsedTime SMALLINT UNSIGNED,
    dpsReadDuration MEDIUMINT UNSIGNED,
    INDEX cmexportidx(siteid, export_end_date_time)
) PARTITION BY RANGE ( TO_DAYS(export_end_date_time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE cm_export_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50),
    PRIMARY KEY (id)
);

CREATE TABLE cm_export_source_names (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255),
    PRIMARY KEY (id)
);

CREATE TABLE cm_export_filter_choice_names (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255),
    PRIMARY KEY (id)
);

CREATE TABLE event_notification_succ_aggr_jmx_stats (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NULL COMMENT "REFERENCES sites(id)",
 nameid SMALLINT UNSIGNED NULL COMMENT "REFERENCES jmx_names (id)",
 3g_jms_msg_bytes_in BIGINT UNSIGNED,
 3g_jms_num_msgs MEDIUMINT UNSIGNED,
 3g_jms_num_msgs_in MEDIUMINT UNSIGNED,
 3g_radio_jms_msg_bytes_in BIGINT UNSIGNED,
 3g_radio_jms_num_msgs MEDIUMINT UNSIGNED,
 3g_radio_jms_num_msgs_in MEDIUMINT UNSIGNED,
 4g_jms_msg_bytes_in BIGINT UNSIGNED,
 4g_jms_num_msgs MEDIUMINT UNSIGNED,
 4g_jms_num_msgs_in MEDIUMINT UNSIGNED,
 eventqueue_4g_queue_size MEDIUMINT UNSIGNED,
 eventqueue_mss_queue_size MEDIUMINT UNSIGNED,
 eventqueue_3g_queue_size MEDIUMINT UNSIGNED,
 eventqueue_3g_radio_queue_size MEDIUMINT UNSIGNED,
 eventqueue_total_4g_events BIGINT UNSIGNED,
 eventqueue_total_mss_events BIGINT UNSIGNED,
 eventqueue_total_3g_events BIGINT UNSIGNED,
 eventqueue_total_3g_radio_events BIGINT UNSIGNED,
 mss_msg_bytes_in BIGINT UNSIGNED,
 mss_num_msgs MEDIUMINT UNSIGNED,
 mss_num_msgs_in MEDIUMINT UNSIGNED,
 INDEX timeSiteidServerid(siteid,time)
 );

CREATE TABLE enm_ap_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 inst TINYINT NOT NULL,
 time DATETIME NOT NULL,
 orderNodeFailureCount SMALLINT UNSIGNED NOT NULL,
 importFailureCount MEDIUMINT UNSIGNED NOT NULL,
 unorderFailureCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ombs_backup_metrics (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    successful_backup_time datetime NOT NULL
);

CREATE TABLE rolling_snapshot_backup_metrics (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    successful_roll_snap_time datetime NOT NULL
);

CREATE TABLE enm_fmnbalarm_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    eventsPresentInNotificationsList SMALLINT UNSIGNED NOT NULL,
    alarmsSentToNotificationService MEDIUMINT UNSIGNED NOT NULL,
    alarmsReceivedfromCorbaserverQueue MEDIUMINT UNSIGNED NOT NULL,
    eventsReceivedfromCorbaserverQueue MEDIUMINT UNSIGNED NOT NULL,
    alarmsPresentInNotificationsList MEDIUMINT UNSIGNED NOT NULL,
    eventsSentToNotificationService MEDIUMINT UNSIGNED NOT NULL,
    activeNMSSubscriptionsCount INT UNSIGNED NOT NULL,
    alarmLatency INT UNSIGNED NOT NULL,
    latencyAlarmCount INT UNSIGNED NOT NULL,
    eventsReceivedfromFmNorthBoundQueue INT UNSIGNED NOT NULL,
    alarmsReceivedfromFmNorthBoundQueue INT UNSIGNED NOT NULL,
    failedAlarmsCount INT UNSIGNED NOT NULL,
    INDEX sitetimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cluster_host (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 clustertype ENUM ( 'UNKNOWN', 'DB', 'SERVICE', 'SCRIPTING', 'EVENT', 'STREAMING', 'AUTOMATION', 'EBS', 'ASR', 'ESN', 'EBA' ),
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 nodename VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
 INDEX siteDate(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cluster_svc_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(127) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id),
    UNIQUE KEY(name)
);

CREATE TABLE enm_cluster_svc_app_ids (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(127) NOT NULL COLLATE latin1_general_cs,
 PRIMARY KEY(id),
 UNIQUE KEY(name)
);

CREATE TABLE enm_cluster_svc (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 hostserverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 serviceid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_cluster_svc_names(id)",
 appid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_cluster_svc_app_ids(id)",
 state ENUM('ONLINE','OFFLINE','FAILED','OTHER'),
 actstand BOOLEAN,
 vmserverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 INDEX siteDate(siteid,date)
);

CREATE TABLE enm_servicegroup_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(127) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id),
    UNIQUE KEY(name)
);

CREATE TABLE enm_servicegroup_instances (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serviceid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_servicegroup_names(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 INDEX siteDate(siteid,date)
);

CREATE TABLE enm_vcs_events (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 clustertype ENUM ( 'UNKNOWN', 'DB', 'SERVICE', 'SCRIPTING', 'EVENT', 'STREAMING', 'AUTOMATION', 'EBS', 'ASR', 'ESN', 'EBA' ),
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 serviceid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_cluster_svc_names(id)",
 appid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_cluster_svc_app_ids(id)",
 eventtype ENUM ('unfreeze persistent','freeze persistent evacuate',
                 'MonitorTimeout','CleanStart','CleanCompleted',
                 'RestartStart','RestartCompleted', 'MonitorOffline',
                 'OfflineStart', 'OfflineCompleted', 'OnlineStart', 'OnlineCompleted', 'Faulted'),
 INDEX siteDate(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_jgroup_nics (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 nicid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES network_interfaces(id)",
 INDEX siteDate(siteid,date)
);

CREATE TABLE enm_jgroup_clusternames (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(127) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id),
    UNIQUE KEY(name)
);

CREATE TABLE enm_jgroup_udp_stats (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 clusterid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_jgroup_clusternames(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 count SMALLINT UNSIGNED NOT NULL,
 num_bytes_received INT UNSIGNED,
 num_single_msgs_sent INT UNSIGNED,
 num_single_msgs_received INT UNSIGNED,
 num_batches_sent INT UNSIGNED,
 num_rejected_msgs INT UNSIGNED,
 num_bytes_sent INT UNSIGNED,
 num_msgs_sent INT UNSIGNED,
 num_internal_msgs_received INT UNSIGNED,
 num_oob_msgs_received INT UNSIGNED,
 num_batches_received INT UNSIGNED,
 num_incoming_msgs_received INT UNSIGNED,
 num_msgs_received INT UNSIGNED,
 INDEX sitetimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_jgroup_view_mismatch (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 clusterid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_jgroup_clusternames(id)",
 viewCount SMALLINT UNSIGNED NOT NULL,
 INDEX sitetimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sap_iq_metrics (
time DATETIME not null,
serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
cache_allocated INT UNSIGNED NOT NULL,
cache_free INT UNSIGNED NOT NULL,
cache_current_size MEDIUMINT UNSIGNED NOT NULL,
cache_used MEDIUMINT UNSIGNED NOT NULL,
siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
page_size SMALLINT UNSIGNED NOT NULL,
INDEX serverIdTime(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sap_db_users (
db_username VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
db_user_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
);

CREATE TABLE sap_db_users_connections (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 db_user_id SMALLINT UNSIGNED NOT NULL,
 number_of_connections SMALLINT UNSIGNED,
 INDEX siteIdTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_jmstopic_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(127) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id),
    UNIQUE KEY(name)
);

CREATE TABLE enm_jmstopic (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 topicid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_jmstopic_names(id)",
 messageCount MEDIUMINT UNSIGNED NOT NULL,
 messagesAdded MEDIUMINT UNSIGNED NOT NULL,
 subscriptionCount MEDIUMINT UNSIGNED NOT NULL,
 deliveringCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX sitetimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_jmsqueue_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(127) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id),
    UNIQUE KEY(name)
);

CREATE TABLE enm_jmsqueue (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 queueid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_jmsqueue_names(id)",
 messageCount MEDIUMINT UNSIGNED NOT NULL,
 messagesAdded MEDIUMINT UNSIGNED NOT NULL,
 consumerCount MEDIUMINT UNSIGNED NOT NULL,
 deliveringCount MEDIUMINT UNSIGNED NOT NULL,
 scheduledCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX sitetimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_msfm_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 cpp_totalAlarmCountSendFromMediation MEDIUMINT UNSIGNED NOT NULL,
 cpp_failedAlarmCountSendFromMediation MEDIUMINT UNSIGNED NOT NULL,
 cpp_nodesUnderSupervision SMALLINT UNSIGNED NOT NULL,
 cpp_nodeUnderNodeSuspendedState SMALLINT UNSIGNED NOT NULL,
 cpp_nodesUnderHeartBeatFailure SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_postgres_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(127) NOT NULL COLLATE latin1_general_cs,
 PRIMARY KEY(id),
 UNIQUE KEY(name)
);

CREATE TABLE enm_postgres_stats_db (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 dbid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_postgres_names(id)",
 numbackends SMALLINT UNSIGNED NOT NULL,
 blks_hit INT UNSIGNED,
 blks_read INT UNSIGNED,
 conflicts INT UNSIGNED,
 deadlocks INT UNSIGNED,
 temp_bytes INT UNSIGNED,
 temp_files INT UNSIGNED,
 tup_deleted INT UNSIGNED,
 tup_fetched INT UNSIGNED,
 tup_inserted INT UNSIGNED,
 tup_returned INT UNSIGNED,
 tup_updated INT UNSIGNED,
 xact_commit INT UNSIGNED,
 xact_rollback INT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_postgres_largest_table (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_postgres_dbsize (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 dbid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_postgres_names(id)",
 sizemb MEDIUMINT UNSIGNED NOT NULL,
 alloc_table_size MEDIUMINT UNSIGNED,
 allocSize MEDIUMINT UNSIGNED,
 current_table_size MEDIUMINT UNSIGNED,
 id MEDIUMINT UNSIGNED,
 largest_table_id SMALLINT UNSIGNED COMMENT "REFERENCES enm_postgres_largest_table(id)",
 INDEX siteTimeIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_radionode_filetransfer (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 total SMALLINT UNSIGNED NOT NULL,
 ftpesCount SMALLINT UNSIGNED,
 sftpCount SMALLINT UNSIGNED,
 type ENUM( 'Radionode', 'Controller6610' ) DEFAULT 'Radionode' COLLATE latin1_general_cs,
 INDEX siteDate(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_addnode_stats (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 start DATETIME NOT NULL,
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 ne_time INT UNSIGNED NOT NULL,
 cpp_time INT UNSIGNED NOT NULL,
 total_time INT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,start)
) PARTITION BY RANGE ( TO_DAYS(start) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_topics_queues (
  Name varchar(100) NOT NULL,
  Type enum('TOPIC','QUEUE') NOT NULL,
  Description varchar(500) NOT NULL DEFAULT '',
  CreatedDate date DEFAULT NULL,
  ModifiedDate date DEFAULT NULL,
  DeletedDate date DEFAULT NULL,
  CreatedBy varchar(31) DEFAULT NULL,
  ModifiedBy varchar(31) DEFAULT NULL,
  PRIMARY KEY (Name)
);


CREATE TABLE pm_error_nodes (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 nodeName VARCHAR(127) NOT NULL,
 nodeCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteIdx(siteid,date)
);

CREATE TABLE pm_errors (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 errorMsg VARCHAR(255),
 errorCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE disk_harderror_details (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    disk VARCHAR(50) NOT NULL,
    harderrorCount SMALLINT UNSIGNED NOT NULL
) PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE active_techpack_details (
 time DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 techpack_name VARCHAR(50) NOT NULL,
 product_number VARCHAR(20) NOT NULL,
 r_state VARCHAR(15) NOT NULL,
 type TINYTEXT NOT NULL,
 status CHAR(6) NOT NULL,
 dwh_creation_date VARCHAR(20) NOT NULL
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE enm_dps_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 n_findMo MEDIUMINT UNSIGNED,
 t_findMo MEDIUMINT UNSIGNED,
 n_findPo MEDIUMINT UNSIGNED,
 t_findPo MEDIUMINT UNSIGNED,
 n_createMo MEDIUMINT UNSIGNED,
 n_setAttribute MEDIUMINT UNSIGNED,
 n_createPo MEDIUMINT UNSIGNED,
 n_deleteMo MEDIUMINT UNSIGNED,
 n_deletePo MEDIUMINT UNSIGNED,
 n_addAssoc SMALLINT UNSIGNED,
 n_changelogQueriesWithRestrictions SMALLINT UNSIGNED,
 n_changelogQueriesWithoutRestrictions SMALLINT UNSIGNED,
 n_containmentQueriesWithRestrictions SMALLINT UNSIGNED,
 n_containmentQueriesWithoutRestrictions SMALLINT UNSIGNED,
 n_groupQueriesWithRestrictions SMALLINT UNSIGNED,
 n_groupQueriesWithoutRestrictions SMALLINT UNSIGNED,
 n_projectionsOnChangelogQueriesWithRestrictions SMALLINT UNSIGNED,
 n_projectionsOnChangelogQueriesWithoutRestrictions SMALLINT UNSIGNED,
 n_projectionsOnContainmentQueriesWithRestrictions SMALLINT UNSIGNED,
 n_projectionsOnContainmentQueriesWithoutRestrictions SMALLINT UNSIGNED,
 n_projectionsOnGroupQueriesWithRestrictions SMALLINT UNSIGNED,
 n_projectionsOnGroupQueriesWithoutRestrictions SMALLINT UNSIGNED,
 n_projectionsOnTypeContainmentQueriesWithRestrictions SMALLINT UNSIGNED,
 n_projectionsOnTypeContainmentQueriesWithoutRestrictions SMALLINT UNSIGNED,
 n_projectionsOnTypeQueriesWithRestrictions SMALLINT UNSIGNED,
 n_projectionsOnTypeQueriesWithoutRestrictions SMALLINT UNSIGNED,
 n_queriesCount SMALLINT UNSIGNED,
 n_removeAssoc SMALLINT UNSIGNED,
 n_transactionsWithEventsActive SMALLINT UNSIGNED,
 n_typeContainmentQueriesWithRestrictions SMALLINT UNSIGNED,
 n_typeContainmentQueriesWithoutRestrictions SMALLINT UNSIGNED,
 n_typeQueriesWithRestrictions SMALLINT UNSIGNED,
 n_typeQueriesWithoutRestrictions SMALLINT UNSIGNED,
 n_qOptNone SMALLINT UNSIGNED,
 n_qOptDescendantsAtMixedLevels SMALLINT UNSIGNED,
 n_qOptDescendantsAtOneLevel SMALLINT UNSIGNED,
 n_qOptDirectPathExpression SMALLINT UNSIGNED,
 n_qOptPathsWithRecursion SMALLINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE fm_alarmprocessing_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 minorAlarmsProcessedByAPSPerMinute MEDIUMINT UNSIGNED NOT NULL,
 majorAlarmsProcessedByAPSPerMinute MEDIUMINT UNSIGNED NOT NULL,
 warningAlarmsProcessedByAPSPerMinute MEDIUMINT UNSIGNED NOT NULL,
 indeterminateAlarmsProcessedByAPSPerMinute MEDIUMINT UNSIGNED NOT NULL,
 criticalAlarmsProcessedByAPSPerMinute MEDIUMINT UNSIGNED NOT NULL,
 clearAlarmsProcessedByAPSPerMinute MEDIUMINT UNSIGNED NOT NULL,
 alarmProcessedByAPSPerMinute MEDIUMINT UNSIGNED NOT NULL,
 failedAlarmCountByAPSPerMinute SMALLINT UNSIGNED NOT NULL,
 alarmCountReceivedByAPSPerMinute MEDIUMINT UNSIGNED NOT NULL,
 alarmRootNotApplicableProcessedByAPS SMALLINT UNSIGNED NOT NULL,
 alarmRootPrimaryProcessedByAPS SMALLINT UNSIGNED NOT NULL,
 alarmRootSecondaryProcessedByAPS SMALLINT UNSIGNED NOT NULL,
 alarmCountDiscardedByAPS MEDIUMINT UNSIGNED,
 alertCountDiscardedByAPS MEDIUMINT UNSIGNED,
 nodeCountSuppressedByAPS SMALLINT UNSIGNED,
 INDEX siteInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE windows_processor_details (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    processorTimePercent FLOAT(5,2) UNSIGNED NOT NULL,
    userTimePercent FLOAT(5,2) UNSIGNED NOT NULL,
    totalTimePercent FLOAT(5,2) UNSIGNED NOT NULL,
    INDEX serveridTime(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE windows_system_details (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    processorQueueLength SMALLINT UNSIGNED NOT NULL,
    numberOfProcesses SMALLINT UNSIGNED NOT NULL,
    INDEX serveridTime(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE windows_memory_details (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    freeRam INT UNSIGNED NOT NULL,
    INDEX serveridTime(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE windows_physicaldisk_details (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    avgDiskQueueLength FLOAT(7,4) UNSIGNED NOT NULL,
    readsPerSec FLOAT(7,4) UNSIGNED NOT NULL,
    writesPerSec FLOAT(7,4) UNSIGNED NOT NULL,
    idleTimePercent FLOAT(5,2) UNSIGNED NOT NULL,
    INDEX serveridTime(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_secserv_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 numOfFailedDistributedTrustedCertificates MEDIUMINT UNSIGNED NOT NULL,
 numOfFailedEnrolledCertificates MEDIUMINT UNSIGNED NOT NULL,
 numOfFailedGeneratedISCFFiles MEDIUMINT UNSIGNED NOT NULL,
 numOfFailedGeneratedSSHKeys MEDIUMINT UNSIGNED NOT NULL,
 numOfFailedWorkflows MEDIUMINT UNSIGNED NOT NULL,
 numOfRunningWorkflows MEDIUMINT UNSIGNED NOT NULL,
 numOfSuccesfulGeneratedISCFFiles MEDIUMINT UNSIGNED NOT NULL,
 numOfSuccessfulDistributedTrustedCertificates MEDIUMINT UNSIGNED NOT NULL,
 numOfSuccessfulEnrolledCertificates MEDIUMINT UNSIGNED NOT NULL,
 numOfSuccessfulGeneratedSSHKeys MEDIUMINT UNSIGNED NOT NULL,
 numOfSuccessfulWorkflows MEDIUMINT UNSIGNED NOT NULL,
 SL2InitCertEnrollmentFailures MEDIUMINT UNSIGNED NOT NULL,
 SL2InstallTrustedCertificateFailures MEDIUMINT UNSIGNED NOT NULL,
 activateSL2Failures MEDIUMINT UNSIGNED NOT NULL,
 activateSL2Invocations MEDIUMINT UNSIGNED NOT NULL,
 deActivateSL2Failures MEDIUMINT UNSIGNED NOT NULL,
 deActivateSL2Invocations MEDIUMINT UNSIGNED NOT NULL,
 activateIpsecFailures MEDIUMINT UNSIGNED NOT NULL,
 activateIpsecInvocations MEDIUMINT UNSIGNED NOT NULL,
 ipsecInitCertEnrollmentFailures MEDIUMINT UNSIGNED NOT NULL,
 ipsecInstallTrustedCertificateFailures MEDIUMINT UNSIGNED NOT NULL,
 deActivateIpsecFailures MEDIUMINT UNSIGNED NOT NULL,
 deActivateIpsecInvocations MEDIUMINT UNSIGNED NOT NULL,
 numOfSuccessfulIscfInvocations MEDIUMINT UNSIGNED,
 numOfFailedIscfInvocations MEDIUMINT UNSIGNED,
 numOfErroredWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutWorkflows MEDIUMINT UNSIGNED,
 numOfPendingWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulSSHKeyGenerationWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutSSHKeyGenerationWorkflows MEDIUMINT UNSIGNED,
 numOfErroredSSHKeyGenerationWorkflows MEDIUMINT UNSIGNED,
 numOfFailedSSHKeyGenerationWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppSL2ActivateWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppSL2ActivateWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppSL2ActivateWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppSL2ActivateWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppSL2DeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppSL2DeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppSL2DeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppSL2DeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppIpSecActivateWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppIpSecActivateWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppIpSecActivateWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppIpSecActivateWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppIpSecDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppIpSecDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppIpSecDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppIpSecDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppCertificateEnrollmentWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppCertificateEnrollmentWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppCertificateEnrollmentWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppCertificateEnrollmentWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulEcimCertificateEnrollmentWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutEcimCertificateEnrollmentWorkflows MEDIUMINT UNSIGNED,
 numOfErroredEcimCertificateEnrollmentWorkflows MEDIUMINT UNSIGNED,
 numOfFailedEcimCertificateEnrollmentWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppTrustDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppTrustDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppTrustDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppTrustDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulEcimTrustDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutEcimTrustDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfErroredEcimTrustDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfFailedEcimTrustDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppTrustRemoveWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppTrustRemoveWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppTrustRemoveWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppTrustRemoveWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulEcimTrustRemoveWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutEcimTrustRemoveWorkflows MEDIUMINT UNSIGNED,
 numOfErroredEcimTrustRemoveWorkflows MEDIUMINT UNSIGNED,
 numOfFailedEcimTrustRemoveWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulEcimLdapConfigureWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutEcimLdapConfigureWorkflows MEDIUMINT UNSIGNED,
 numOfErroredEcimLdapConfigureWorkflows MEDIUMINT UNSIGNED,
 numOfFailedEcimLdapConfigureWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppCRLCheckWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppCRLCheckWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppCRLCheckWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppCRLCheckWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulEcimCRLCheckWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutEcimCRLCheckWorkflows MEDIUMINT UNSIGNED,
 numOfErroredEcimCRLCheckWorkflows MEDIUMINT UNSIGNED,
 numOfFailedEcimCRLCheckWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppOnDemandCRLDownloadWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppOnDemandCRLDownloadWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppOnDemandCRLDownloadWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppOnDemandCRLDownloadWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulEcimOnDemandCRLDownloadWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutEcimOnDemandCRLDownloadWorkflows MEDIUMINT UNSIGNED,
 numOfErroredEcimOnDemandCRLDownloadWorkflows MEDIUMINT UNSIGNED,
 numOfFailedEcimOnDemandCRLDownloadWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulSetCiphersWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutSetCiphersWorkflows MEDIUMINT UNSIGNED,
 numOfErroredSetCiphersWorkflows MEDIUMINT UNSIGNED,
 numOfFailedSetCiphersWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppRTSELActivateWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppRTSELActivateWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppRTSELActivateWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppRTSELActivateWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppRTSELDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppRTSELDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppRTSELDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppRTSELDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppRTSELDeleteWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppRTSELDeleteWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppRTSELDeleteWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppRTSELDeleteWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppHTTPSActivateWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppHTTPSActivateWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppHTTPSActivateWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppHTTPSActivateWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppHTTPSDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppHTTPSDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppHTTPSDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppHTTPSDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppHTTPSGetWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppHTTPSGetWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppHTTPSGetWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppHTTPSGetWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulEcimFTPESActivateWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutEcimFTPESActivateWorkflows MEDIUMINT UNSIGNED,
 numOfErroredEcimFTPESActivateWorkflows MEDIUMINT UNSIGNED,
 numOfFailedEcimFTPESActivateWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulEcimFTPESDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutEcimFTPESDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfErroredEcimFTPESDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfFailedEcimFTPESDeactivateWorkflows MEDIUMINT UNSIGNED,
 numOfSuccessfulCppLaadDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfTimedOutCppLaadDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfErroredCppLaadDistributeWorkflows MEDIUMINT UNSIGNED,
 numOfFailedCppLaadDistributeWorkflows MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sim_node (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    node VARCHAR(50) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE sim_plugin (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    plugin VARCHAR(50) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE sim_stats (
    start_time DATETIME NOT NULL,
    stop_time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    no_of_files SMALLINT UNSIGNED,
    nodeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sim_node(id)",
    pluginid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sim_plugin(id)",
    INDEX siteidStartTimeIdx (siteid,start_time)
) PARTITION BY RANGE ( TO_DAYS(start_time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sim_error (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time VARCHAR(50) NOT NULL,
    class_name VARCHAR(250) NOT NULL,
    error_reason VARCHAR(250) NOT NULL,
    exception VARCHAR(250) NOT NULL,
    INDEX siteidTime(siteid,time)
);

CREATE TABLE netsim_network_stats (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 simulation VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
 bandwidth VARCHAR(10) NOT NULL,
 latency VARCHAR(10) NOT NULL,
 num_of_nodes INT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE netsim_netypes (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 PRIMARY KEY(id),
 UNIQUE KEY(name)
);

CREATE TABLE netsim_simulations (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES netsim_netypes(id)",
 numne SMALLINT UNSIGNED NOT NULL,
 simulation VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 INDEX siteTimeIdx(siteid,date)
);

CREATE TABLE netsim_resource_usage (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES netsim_netypes(id)",
 cpu SMALLINT UNSIGNED NOT NULL,
 rss SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE netsim_requests (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 NETCONF SMALLINT UNSIGNED NOT NULL,
 CPP SMALLINT UNSIGNED NOT NULL,
 SNMP SMALLINT UNSIGNED NOT NULL,
 SIMCMD SMALLINT UNSIGNED NOT NULL,
 ecim_get SMALLINT UNSIGNED NOT NULL,
 ecim_edit SMALLINT UNSIGNED NOT NULL,
 ecim_MOaction SMALLINT UNSIGNED NOT NULL,
 cpp_createMO SMALLINT UNSIGNED NOT NULL,
 cpp_deleteMO SMALLINT UNSIGNED NOT NULL,
 cpp_setAttr SMALLINT UNSIGNED NOT NULL,
 cpp_getMIB SMALLINT UNSIGNED NOT NULL,
 cpp_nextMOinfo SMALLINT UNSIGNED NOT NULL,
 cpp_get SMALLINT UNSIGNED NOT NULL,
 cpp_MOaction SMALLINT UNSIGNED NOT NULL,
 snmp_get SMALLINT UNSIGNED NOT NULL,
 snmp_bulk_get SMALLINT UNSIGNED,
 snmp_get_next SMALLINT UNSIGNED,
 snmp_set SMALLINT UNSIGNED NOT NULL,
 AVCbursts SMALLINT UNSIGNED NOT NULL,
 MCDbursts SMALLINT UNSIGNED NOT NULL,
 AlarmBursts SMALLINT UNSIGNED NOT NULL,
 SFTP SMALLINT UNSIGNED NOT NULL,
 sftp_FileOpen SMALLINT UNSIGNED NOT NULL,
 sftp_get_cwd SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time),
 INDEX serverTimeIdx(serverid,time)
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

CREATE TABLE netsim_response (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 NETCONF SMALLINT UNSIGNED NOT NULL,
 CORBA SMALLINT UNSIGNED NOT NULL,
 SNMP SMALLINT UNSIGNED NOT NULL,
 SSH SMALLINT UNSIGNED NOT NULL,
 SFTP SMALLINT UNSIGNED NOT NULL,
 ecim_avc SMALLINT UNSIGNED NOT NULL,
 ecim_MOcreated SMALLINT UNSIGNED NOT NULL,
 ecim_MOdeleted SMALLINT UNSIGNED NOT NULL,
 ecim_reply SMALLINT UNSIGNED NOT NULL,
 cpp_avc SMALLINT UNSIGNED NOT NULL,
 cpp_MOcreated SMALLINT UNSIGNED NOT NULL,
 cpp_MOdeleted SMALLINT UNSIGNED NOT NULL,
 cpp_reply SMALLINT UNSIGNED NOT NULL,
 sftp_FileClose SMALLINT UNSIGNED NOT NULL,
 snmp_response SMALLINT UNSIGNED,
 snmp_traps SMALLINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time),
 INDEX serverTimeIdx(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
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

CREATE TABLE netsim_numstarted (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 numstarted SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE netsim_nrm_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE netsim_module_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE netsim_nrm (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 nrmid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES netsim_nrm_names(id)",
 moduleid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES netsim_module_names(id)",
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE netanserver_userauditlog_details(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    noOfAnalystUser SMALLINT UNSIGNED NOT NULL,
    noOfAuthorUser SMALLINT UNSIGNED NOT NULL,
    noOfConsumerUser SMALLINT UNSIGNED NOT NULL,
    noOfOtherUser SMALLINT UNSIGNED NOT NULL,
    totalUser SMALLINT UNSIGNED NOT NULL,
    typeid SMALLINT UNSIGNED NOT NULL,
    INDEX siteidTime(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_vm_critical_error_messages (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 message VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(message),
 PRIMARY KEY(id)
);
INSERT INTO enm_vm_critical_error_messages (message) VALUES ("NA");

CREATE TABLE enm_vm_critical_errors (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 errorid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_vm_critical_error_messages(id)",
 errorCount MEDIUMINT UNSIGNED NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

#In enm_ecim_syncs start is actualy the endtime
#This is because we are unable to change the partition by range
CREATE TABLE enm_ecim_syncs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 start DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 duration MEDIUMINT UNSIGNED NOT NULL,
 mo_parsed MEDIUMINT UNSIGNED NOT NULL,
 t_read_mo_ne  MEDIUMINT UNSIGNED NOT NULL,
 t_ne_trans_mo  MEDIUMINT UNSIGNED NOT NULL,
 n_mo_write MEDIUMINT UNSIGNED NOT NULL,
 n_mo_create MEDIUMINT UNSIGNED DEFAULT NULL,
 n_mo_update MEDIUMINT UNSIGNED DEFAULT NULL,
 n_mo_delete MEDIUMINT UNSIGNED DEFAULT NULL,
 t_mo_write MEDIUMINT UNSIGNED NOT NULL,
 t_mo_delta INT UNSIGNED NOT NULL,
 n_mo_attr_read INT UNSIGNED NOT NULL,
 n_mo_attr_trans  MEDIUMINT UNSIGNED NOT NULL,
 n_mo_attr_null  MEDIUMINT UNSIGNED NOT NULL,
 n_mo_attr_discard  MEDIUMINT UNSIGNED NOT NULL,
 timeToSendNotificationsCmEventNBI MEDIUMINT UNSIGNED,
 numOfNotificationsSendToCmEventsNBI MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,start)
) PARTITION BY RANGE ( TO_DAYS(start) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_comecim_syncstatus_reason (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_comecim_syncstatus (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 syncstatus ENUM('SYNCHRONIZED', 'TOPOLOGY', 'PENDING', 'DELTA', 'UNSYNCHRONIZED'),
 reasonid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_comecim_syncstatus_reason(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mssnmpfm_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 alarmForwardedFailures MEDIUMINT UNSIGNED NOT NULL,
 alarmsForwarded MEDIUMINT UNSIGNED NOT NULL,
 alarmsProcessingFailures MEDIUMINT UNSIGNED NOT NULL,
 alarmsProcessingNotSupported MEDIUMINT UNSIGNED NOT NULL,
 alarmsReceived  MEDIUMINT UNSIGNED NOT NULL,
 trapsDiscarded  MEDIUMINT UNSIGNED NOT NULL,
 trapsForwarded MEDIUMINT UNSIGNED NOT NULL,
 trapsForwardedFailures  MEDIUMINT UNSIGNED NOT NULL,
 trapsReceived MEDIUMINT UNSIGNED NOT NULL,
 numOfSupervisedNodes MEDIUMINT UNSIGNED NOT NULL,
 numOfSuspendedNodes MEDIUMINT UNSIGNED NOT NULL,
 numOfHBFailureNodes MEDIUMINT UNSIGNED NOT NULL,
 processingAlarmTime MEDIUMINT UNSIGNED NOT NULL,
 alarmProcessingDiscarded MEDIUMINT UNSIGNED NOT NULL,
 alarmProcessingInvalidRecordType MEDIUMINT UNSIGNED NOT NULL,
 alarmProcessingLossOfTrap MEDIUMINT UNSIGNED NOT NULL,
 alarmProcessingPing MEDIUMINT UNSIGNED NOT NULL,
 alarmProcessingSuccess MEDIUMINT UNSIGNED NOT NULL,
 forwardedProcessedAlarmFailures MEDIUMINT UNSIGNED NOT NULL,
 syncAlarmCommand INT UNSIGNED NOT NULL,
 multiEventProcessed mediumint UNSIGNED NOT NULL,
 multiEventReordered mediumint UNSIGNED NOT NULL,
 multiEventFailed mediumint UNSIGNED NOT NULL,
 decryptionErrors SMALLINT UNSIGNED,
 notInTimeWindows SMALLINT UNSIGNED,
 numberOfConnections SMALLINT UNSIGNED,
 snmpInTraps SMALLINT UNSIGNED,
 snmpTrapInASNParseErrs SMALLINT UNSIGNED,
 snmpTrapInBadVersions SMALLINT UNSIGNED,
 unknownEngineIDs SMALLINT UNSIGNED,
 unknownUserNames SMALLINT UNSIGNED,
 unsupportedSecLevels SMALLINT UNSIGNED,
 wrongDigests SMALLINT UNSIGNED,
 noOfSnmpTargetDestinationDiscarded MEDIUMINT UNSIGNED,
 noOfSnmpTargetDestinationSent MEDIUMINT UNSIGNED,
INDEX siteTimeMsSnmpfmIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mscmce_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 hbSubValidationFail MEDIUMINT UNSIGNED NOT NULL,
 hbSupervisionStart MEDIUMINT UNSIGNED NOT NULL,
 hbSupervisionStop MEDIUMINT UNSIGNED NOT NULL,
 hbSubValidationSucc MEDIUMINT UNSIGNED NOT NULL,
 deltaEvents MEDIUMINT UNSIGNED,
 deltaSyncSucc MEDIUMINT UNSIGNED,
 deltaSyncFail MEDIUMINT UNSIGNED,
 ncSuccSubs MEDIUMINT UNSIGNED,
 ncFailedSubs MEDIUMINT UNSIGNED,
 ncSessReqCRUDProcessTime MEDIUMINT UNSIGNED,
 ncSessReqRPCConstructionTime MEDIUMINT UNSIGNED,
 ncSessRequestCount MEDIUMINT UNSIGNED,
 ncSessResCRUDProcessTime MEDIUMINT UNSIGNED,
 ncSessResponseCount MEDIUMINT UNSIGNED,
 ncSessrResRPCExtractionTime MEDIUMINT UNSIGNED,
 ncWriteActionFail MEDIUMINT UNSIGNED,
 ncWriteActionSucc MEDIUMINT UNSIGNED,
 ncWriteCreateFail MEDIUMINT UNSIGNED,
 ncWriteCreateSucc MEDIUMINT UNSIGNED,
 ncWriteDeleteFail MEDIUMINT UNSIGNED,
 ncWriteDeleteSucc MEDIUMINT UNSIGNED,
 ncWriteModifyFail MEDIUMINT UNSIGNED,
 ncWriteModifySucc MEDIUMINT UNSIGNED,
 nonPersistReadSucc MEDIUMINT UNSIGNED,
 nonPersistReadFail MEDIUMINT UNSIGNED,
 nonPersistReadAttr MEDIUMINT UNSIGNED,
 syncSucc MEDIUMINT UNSIGNED,
 syncFail MEDIUMINT UNSIGNED,
 syncMO MEDIUMINT UNSIGNED,
 notifBuffered MEDIUMINT UNSIGNED,
 notifDirect MEDIUMINT UNSIGNED,
 averageModelIdCalculationTimeTaken SMALLINT UNSIGNED,
 averageNoModelIdCalculationTimeTaken SMALLINT UNSIGNED,
 numberOfModelIdCalculation SMALLINT UNSIGNED,
 numberOfSoftwareSyncWithError SMALLINT UNSIGNED,
 numberOfSoftwareSyncWithModelIdCalculation SMALLINT UNSIGNED,
 numberOfSoftwareSyncWithoutModelIdCalculation SMALLINT UNSIGNED,
 softwareSyncInvocations SMALLINT UNSIGNED,
 totalModelIdCalculationTimeTaken SMALLINT UNSIGNED,
 totalWithoutModelIdCalculationTimeTaken SMALLINT UNSIGNED,
 numberOfFailedMibUpgrade SMALLINT UNSIGNED,
 numberOfSuccessfulMibUpgrade SMALLINT UNSIGNED,
 yangNumberOfSoftwareSyncInvocations SMALLINT UNSIGNED,
 yangNotificationsReceivedCount SMALLINT UNSIGNED,
 yangNotificationsProcessedCount SMALLINT UNSIGNED,
 yangNotificationsDiscardedCount SMALLINT UNSIGNED,
 INDEX siteTimeMSCMCEHbSupervision(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE elasticsearch_tp_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 PRIMARY KEY(id),
 UNIQUE KEY(name)
);

CREATE TABLE elasticsearch_tp (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 tpid SMALLINT UNSIGNED COMMENT "REFERENCES elasticsearch_tp_names(id)",
 completed MEDIUMINT UNSIGNED,
 rejected SMALLINT UNSIGNED,
 active SMALLINT UNSIGNED,
 queue SMALLINT UNSIGNED,
 servicetype ENUM('elasticsearch', 'eshistory') NOT NULL DEFAULT 'elasticsearch' COLLATE latin1_general_cs,
 INDEX (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE elasticsearch_indices (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 searchQueryCount MEDIUMINT UNSIGNED,
 searchQueryTime MEDIUMINT UNSIGNED,
 searchFetchCount MEDIUMINT UNSIGNED,
 searchFetchTime MEDIUMINT UNSIGNED,
 indexCount MEDIUMINT UNSIGNED,
 indexTime MEDIUMINT UNSIGNED,
 storeSizeMB MEDIUMINT UNSIGNED,
 docsDeleted MEDIUMINT UNSIGNED NOT NULL,
 servicetype ENUM('elasticsearch', 'eshistory') NOT NULL DEFAULT 'elasticsearch' COLLATE latin1_general_cs,
 INDEX (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE cmserv_clistatistics_instr (
   siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
   serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
   time DATETIME NOT NULL,
   SCRsubmitCommandexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   SCRsubmitCommandmethodInvocations MEDIUMINT UNSIGNED,
   SFDaddCommandexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   SFDaddCommandmethodInvocations MEDIUMINT UNSIGNED,
   SFDgetCommandStatusexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   SFDgetCommandStatusmethodInvocations MEDIUMINT UNSIGNED,
   SFDgetCommandResponseexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   SFDgetCommandResponsemethodInvocations MEDIUMINT UNSIGNED,
   scriptEngineExecuteCommandmethodInvocations MEDIUMINT UNSIGNED,
   scriptEngineExecuteCommandexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   requestsFromCLIVisits SMALLINT UNSIGNED,
   INDEX siteidTime(siteid,time)
)  PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_elasticsearch_logs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 log_name VARCHAR(24) NOT NULL COLLATE latin1_general_cs,
 log_end_time DATETIME NOT NULL,
 log_size MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE enm_elasticsearch_getlog (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 compressedKB INT UNSIGNED,
 uncompressedKB INT UNSIGNED,
 lastError VARCHAR(256),
 INDEX siteDateIndex(siteid,date)
);

CREATE TABLE enm_kpiserv_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 inst TINYINT NOT NULL,
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 numberOfKpiQueries MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodes_activeCellLevelKPI MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodes_activeNodeLevelKPI MEDIUMINT UNSIGNED NOT NULL,
 numberOf_activeCellLevelKPI MEDIUMINT UNSIGNED NOT NULL,
 numberOf_activeNodeLevelKPI MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveEUtranCellFDDKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveEUtranCellFDDKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveEUtranCellTDDKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveEUtranCellTDDKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveNbIotCellKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveNbIotCellKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveENodeBFunctionKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveENodeBFunctionKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveERBSKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveERBSKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveRadioNodeKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveRadioNodeKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActivePicoKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActivePicoKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveRBSKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveRBSKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveRNCKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveRNCKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveRncFunctionKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveRncFunctionKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveUtranCellKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveUtranCellKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveNodeBFunctionKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveNodeBFunctionKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveHsDschResourcesKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveHsDschResourcesKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActivePmGroupUnitMeasKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActivePmGroupUnitMeasKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActivePmGroupPortMeasKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActivePmGroupPortMeasKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveFronthaulKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveFronthaulKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveRouter6672Kpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveRouter6672Kpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveRouter6675Kpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveRouter6675Kpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActivePortHistoryKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActivePortHistoryKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveGlobalKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveGlobalKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveContextKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveContextKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveDot1qHistoryKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveDot1qHistoryKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveLinkGroupHistoryKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveLinkGroupHistoryKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveRadioTNodeKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveRadioTNodeKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveEthernetPortKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveEthernetPortKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveVlanPortKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveVlanPortKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveInterfaceIPV4Kpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveInterfaceIPV4Kpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveInterfaceIPV6Kpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveInterfaceIPV6Kpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveSuperChannelKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveSuperChannelKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveTgTransportKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveTgTransportKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveTwampTestSessionKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveTwampTestSessionKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveSgsnMmeKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveSgsnMmeKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveSgsnMmeROKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveSgsnMmeROKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveMmeFunctionKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveMmeFunctionKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveRAKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveRAKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveQosClassIdentifierKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveQosClassIdentifierKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveIpInterfacePmKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveIpInterfacePmKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveEthPortKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveEthPortKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfActiveSgsnFunctionKpis MEDIUMINT UNSIGNED NOT NULL,
 avgNumberOfNodesOfActiveSgsnFunctionKpis MEDIUMINT UNSIGNED NOT NULL,
 numberOfKpiViewerExportRequests SMALLINT UNSIGNED NOT NULL,
 numberOfKpisExported SMALLINT UNSIGNED NOT NULL,
 numberOfKpisRequested SMALLINT UNSIGNED NOT NULL,
 numberGetAllElementsInScopeRequests SMALLINT UNSIGNED NOT NULL,
 numberGetQueryScopeRequests SMALLINT UNSIGNED NOT NULL,
 numberGetAttributesForScopeRequests SMALLINT UNSIGNED NOT NULL,
 numberGetNeTypesInScopeRequets SMALLINT UNSIGNED NOT NULL,
 numberIsScopeReadyRequests SMALLINT UNSIGNED NOT NULL,
 numberAddElementToScopeRequests SMALLINT UNSIGNED NOT NULL,
 numberAddElementsToScopeRequests SMALLINT UNSIGNED NOT NULL,
 numberAddElementsToSelectedScopeRequests SMALLINT UNSIGNED NOT NULL,
 numberCreateFixedScopeRequests SMALLINT UNSIGNED NOT NULL,
 numberDeleteFixedScopeRequests SMALLINT UNSIGNED NOT NULL,
 numberDeleteFromScopeRequests SMALLINT UNSIGNED NOT NULL,
 numberDeleteScopeByIdRequests SMALLINT UNSIGNED NOT NULL,
 numberDeleteAllElementsInScopeRequests SMALLINT UNSIGNED NOT NULL,
 numberOfCellTabRequests SMALLINT UNSIGNED NOT NULL,
 numberOfKPITabRequests SMALLINT UNSIGNED NOT NULL,
 numberOfNHAExportsRequested SMALLINT UNSIGNED NOT NULL,
 numberOfNhaCmStateRequests SMALLINT UNSIGNED NOT NULL,
 numberOfNhaKpisRequested SMALLINT UNSIGNED NOT NULL,
 numberOfNodeTabRequests SMALLINT UNSIGNED NOT NULL,
 numberNetworkStateWidgetRequests SMALLINT UNSIGNED NOT NULL,
 numberSyncStateWidgetRequests SMALLINT UNSIGNED NOT NULL,
 numberOfAdminStateEventsPushed SMALLINT UNSIGNED NOT NULL,
 numberOfAvailabilityStatusEventsPushed SMALLINT UNSIGNED NOT NULL,
 numberOfNodeLevelStateEventsPushed SMALLINT UNSIGNED NOT NULL,
 numberOfOpStateEventsPushed SMALLINT UNSIGNED NOT NULL,
 numberOfSyncStateEventsPushed SMALLINT UNSIGNED NOT NULL,
 numberOfHistoricalDataRequests SMALLINT UNSIGNED,
 numberOfKpiRequestsLessBreachInfoSent SMALLINT UNSIGNED,
 numberOfKpiResponsesLessBreachInfoReceived SMALLINT UNSIGNED,
 numberOfKpiRequestsLessBreachInfoFailed SMALLINT UNSIGNED,
 INDEX siteInstIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE fm_bnsi_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 alarmsTranslated MEDIUMINT UNSIGNED NOT NULL,
 apsAlarmsTranslated MEDIUMINT UNSIGNED NOT NULL,
 totalDelay INT UNSIGNED NOT NULL,
 totalDelayOnlyBnsi INT UNSIGNED NOT NULL,
 counterOverTimeMax  MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeFMBnsiIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE data_source_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 data_source VARCHAR(50) NOT NULL
);

CREATE TABLE ede_output_csv_log_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 ede_instance VARCHAR(50) NOT NULL,
 data_source_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES data_source_id_mapping(id)",
 rop VARCHAR(12) NOT NULL,
 file_count INT UNSIGNED NOT NULL,
 file_size FLOAT(9,3) UNSIGNED NOT NULL,
 event_count INT UNSIGNED NOT NULL,
 INDEX edeOutputCsvLogDetailsIdx(siteid,time,data_source_id,ede_instance)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ede_controller (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 ede_instance VARCHAR(50) NOT NULL,
 data_source_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES data_source_id_mapping(id)",
 rop DATETIME NOT NULL,
 INDEX siteidRopInstanceSourceid(siteid, rop, ede_instance, data_source_id)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_versant_health_check_types (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 check_type VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(check_type),
 PRIMARY KEY(id)
);
INSERT INTO enm_versant_health_check_types (check_type) VALUES ("NA");

CREATE TABLE enm_versant_health_status_types (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 status_type VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(status_type),
 PRIMARY KEY(id)
);
INSERT INTO enm_versant_health_status_types (status_type) VALUES ("NA");

CREATE TABLE enm_versant_health_checks (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 checkid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_versant_health_check_types(id)",
 statusid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_versant_health_status_types(id)",
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_amos_commands (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    cmdName VARCHAR(255),
    successcount MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    failurecount MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_amos_users (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    userName VARCHAR(255),
    cmdCount MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteDateIdx(siteid,date)
);

CREATE TABLE enm_amos_sessions (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    sessions MEDIUMINT UNSIGNED NOT NULL,
    processes MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ipsmserv_instr  (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    nodecount SMALLINT UNSIGNED NOT NULL,
    INDEX siteIpsmservIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE business_cases (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_case VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
  PRIMARY KEY(id)
);

CREATE TABLE glassfish_browser_aggregation (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  browser_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES browser_name(id)",
  browser_version VARCHAR(1024) NOT NULL COLLATE latin1_general_cs,
  total_request SMALLINT UNSIGNED,
  success SMALLINT UNSIGNED,
  failure SMALLINT UNSIGNED,
  INDEX browserAggIdx (siteid,date,browser_id)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE glassfish_time_dimension_aggregation (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time_dim SMALLINT UNSIGNED,
  total_request SMALLINT UNSIGNED,
  success SMALLINT UNSIGNED,
  failure SMALLINT UNSIGNED,
  INDEX timeAggIdx (siteid,date)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE glassfish_url_aggregation (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  url SMALLINT UNSIGNED,
  business_case SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES business_cases(id)",
  total_request SMALLINT UNSIGNED,
  success SMALLINT UNSIGNED,
  failure SMALLINT UNSIGNED,
  INDEX urlAggIdx (siteid,date,url)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ede_node_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 node_name VARCHAR(55) NOT NULL
);

CREATE TABLE ede_node_event_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 ede_instance VARCHAR(50) NOT NULL,
 data_source_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES data_source_id_mapping(id)",
 rop VARCHAR(12) NOT NULL,
 node_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ede_node_id_mapping(id)",
 event_count INT UNSIGNED NOT NULL,
 INDEX edeNodeWiseEventDetailsIdx(siteid,time,data_source_id,ede_instance)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE cep_source_node (
    id   SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    node VARCHAR(50) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE ede_linkfile_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 ede_instance VARCHAR(50) NOT NULL,
 data_source_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES data_source_id_mapping(id)",
 rop DATETIME NOT NULL,
 INDEX siteidRopInstanceSourceid(siteid, rop, ede_instance, data_source_id)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE shm_inventorymediation_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 increaseInventoryMediationInvocations SMALLINT UNSIGNED NOT NULL,
 inventoryMediationParallelInvocations SMALLINT UNSIGNED NOT NULL,
 inventoryMediationTimeTaken MEDIUMINT UNSIGNED NOT NULL,
 processTimeTakenForConverting SMALLINT UNSIGNED NOT NULL,
 processTimeTakenForNodeResponse MEDIUMINT UNSIGNED NOT NULL,
 processTimeTakenForOrderInventory MEDIUMINT UNSIGNED NOT NULL,
 processTimeTakenForParsing SMALLINT UNSIGNED NOT NULL,
 processTimeTakenForPersistingIntoDPS MEDIUMINT UNSIGNED NOT NULL,
 processTimeTakenForRetrieveInventoryXml MEDIUMINT UNSIGNED NOT NULL,
 inventorySyncInvocations SMALLINT UNSIGNED,
 inventoryUnsyncInvocations SMALLINT UNSIGNED,
 orderInventoryProcessSuccessCount SMALLINT UNSIGNED,
 nodeResponseProcessSuccessCount SMALLINT UNSIGNED,
 xmlParsingProcessSuccessCount SMALLINT UNSIGNED,
 inventoryXmlProcessSuccessCount SMALLINT UNSIGNED,
 conversionProcessSuccessCount SMALLINT UNSIGNED,
 persistingIntoDPSProcessSuccessCount SMALLINT UNSIGNED,
 ongoingInventoryMediationSyncs MEDIUMINT UNSIGNED,
 INDEX siteShmInventoryMediationIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_route_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(192) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_route_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 routeid SMALLINT UNSIGNED COMMENT "REFERENCES enm_route_instr(id)",
 time DATETIME NOT NULL,
 ExchangesTotal MEDIUMINT UNSIGNED,
 ExchangesCompleted MEDIUMINT UNSIGNED,
 ExchangesFailed MEDIUMINT UNSIGNED,
 TotalProcessingTime MEDIUMINT UNSIGNED,
 INDEX siteSrvTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE sum_enm_route_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 routeid SMALLINT UNSIGNED COMMENT "REFERENCES enm_route_instr(id)",
 date DATE NOT NULL,
 ExchangesTotal MEDIUMINT UNSIGNED,
 ExchangesCompleted MEDIUMINT UNSIGNED,
 ExchangesFailed MEDIUMINT UNSIGNED,
 TotalProcessingTime MEDIUMINT UNSIGNED,
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_lcmserv_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    sgsnLicenseUsage BIGINT UNSIGNED NOT NULL,
    sgsnLicensePluginUsage BIGINT UNSIGNED NOT NULL,
    sgsnUsageCollectingTime BIGINT UNSIGNED NOT NULL,
    timesOfSgsnUsageTriggered BIGINT UNSIGNED NOT NULL,
    erbsLicenseUsage BIGINT UNSIGNED NOT NULL,
    erbsLicensePluginUsage BIGINT UNSIGNED NOT NULL,
    erbsUsageCollectingTime BIGINT UNSIGNED NOT NULL,
    timesOfErbsUsageTriggered BIGINT UNSIGNED NOT NULL,
    mgwLicenseUsage BIGINT UNSIGNED NOT NULL,
    mgwLicensePluginUsage BIGINT UNSIGNED NOT NULL,
    mgwUsageCollectingTime BIGINT UNSIGNED NOT NULL,
    timesOfMgwUsageTriggered BIGINT UNSIGNED NOT NULL,
    r6000LicenseUsage BIGINT UNSIGNED NOT NULL,
    r6000UsageCollectingTime BIGINT UNSIGNED NOT NULL,
    timesOfR6000UsageTriggered BIGINT UNSIGNED NOT NULL,
    sentinelConnected BOOLEAN  NOT NULL,
    INDEX siteLcmInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_versantcrashinfo (
    date DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    filename VARCHAR(255)
);

CREATE TABLE ede_event_name_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_name VARCHAR(100) NOT NULL
);

CREATE TABLE ede_event_distribution_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 ede_instance VARCHAR(50) NOT NULL,
 data_source_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES data_source_id_mapping(id)",
 rop VARCHAR(12) NOT NULL,
 event_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ede_event_name_id_mapping(id)",
 event_count INT UNSIGNED NOT NULL,
 total_events_count INT UNSIGNED NOT NULL,
 INDEX edeEventWiseDetailsIdx(siteid,time,data_source_id,ede_instance)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_coredump_details (
 occurrence_time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 INDEX siteidOccurrencetime(siteid, occurrence_time)
)PARTITION BY RANGE ( TO_DAYS(occurrence_time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_zfs_snapshot_criteria_test_status (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 status VARCHAR(4) NOT NULL,
 INDEX zfsSnapshotCriteriaIdx(siteid,serverid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_upgrade_stage_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_upgrade_events (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 seqno TINYINT UNSIGNED NOT NULL,
 stageid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_upgrade_events(id)",
 state ENUM ('START','END' ),
 additionalInfo VARCHAR(512),
 INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE eniq_smf_services (
 service_name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 service_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
);

CREATE TABLE eniq_smf_restart_details (
 site_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 server_id INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 service_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_smf_services(service_id)",
 restart_time DATETIME NOT NULL,
 INDEX eniqSmfServiceRestartDetailsIndx(site_id,restart_time)
) PARTITION BY RANGE ( TO_DAYS(restart_time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_dumploginfo (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 type VARCHAR(10),
 filename VARCHAR(255),
 INDEX enmDumpLogInfoIndx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE netanserver_user_session_statistics_details(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    userName VARCHAR(50) NOT NULL,
    openFileCount SMALLINT UNSIGNED NOT NULL,
    loggedInDuration TIME NOT NULL,
    serviceId VARCHAR(50) NOT NULL,
    INDEX netanUserSessionStatisticsIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE netanserver_open_file_statistics_details(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    analysisName VARCHAR(100) NOT NULL,
    serviceId VARCHAR(50) NOT NULL,
    INDEX netanOpenFileStatisticsIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE netanserver_auditlog_details (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 user_name VARCHAR(50) NOT NULL,
 operation_name VARCHAR(25) NOT NULL,
 analysis_name VARCHAR(50) NOT NULL,
 status VARCHAR(10) NOT NULL,
 serviceId VARCHAR(50) NOT NULL,
 INDEX netanServerSiteidTime(siteid,time)
 ) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_loader_aggregator_failedset_details (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time_stamp DATETIME NOT NULL,
 techpack_name VARCHAR(50) NOT NULL,
 failed_set VARCHAR(100) NOT NULL,
 INDEX loaderAggregatorFailedSetDetailsIndx(siteid,time_stamp)
) PARTITION BY RANGE ( TO_DAYS(time_stamp) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ne_release (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_model_identity (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(48) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_network_element_details (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
 releaseid SMALLINT UNSIGNED COMMENT "REFERENCES enm_ne_release(id)",
 modelid SMALLINT UNSIGNED COMMENT "REFERENCES enm_model_identity(id)",
 technology_domain SET('EPS', 'GSM', 'IMS', 'UMTS', '5GS'),
 count MEDIUMINT UNSIGNED NOT NULL,
 cm_supervised_count MEDIUMINT UNSIGNED NOT NULL,
 fm_supervised_count MEDIUMINT UNSIGNED NOT NULL,
 cm_synced_count MEDIUMINT UNSIGNED NOT NULL,
 shm_synced_count MEDIUMINT UNSIGNED NOT NULL,
 pm_supervised_count MEDIUMINT UNSIGNED,
 INDEX networkelement(siteid,date)
 ) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_solr_core_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_solr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 coreid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_solr_core_names(id)",
 cacheInserts INT UNSIGNED,
 cacheLookups INT UNSIGNED,
 cacheHits INT UNSIGNED,
 cacheEvictions INT UNSIGNED,
 cacheSize SMALLINT UNSIGNED,
 selectRequests SMALLINT UNSIGNED,
 selectTime INT UNSIGNED,
 selectErrors SMALLINT UNSIGNED,
 selectTimeouts SMALLINT UNSIGNED,
 updateRequests SMALLINT UNSIGNED,
 updateTime INT UNSIGNED,
 updateErrors SMALLINT UNSIGNED,
 updateTimeouts SMALLINT UNSIGNED,
 searcherNumDocs INT UNSIGNED,
 updateJsonRequests SMALLINT UNSIGNED,
 updateJsonTime INT UNSIGNED,
 updateJsonErrors SMALLINT UNSIGNED,
 updateJsonTimeouts SMALLINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_solr_daily (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 coreid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_solr_core_names(id)",
 indexSizeBytes BIGINT UNSIGNED NOT NULL,
 INDEX esdIndex(siteid,date)
);

CREATE TABLE enm_cppsync_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    softwareSyncInvocations SMALLINT UNSIGNED NOT NULL,
    numberOfSoftwareSyncWithError SMALLINT UNSIGNED NOT NULL,
    numberOfSoftwareSyncWithModelIdCalculation SMALLINT UNSIGNED NOT NULL,
    numberOfSoftwareSyncWithoutModelIdCalculation SMALLINT UNSIGNED NOT NULL,
    INDEX siteCppSyncInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_lvs (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 proto ENUM('TCP','UDP'),
 lhost VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
 lport SMALLINT UNSIGNED NOT NULL,
 rhost VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 rport SMALLINT UNSIGNED NOT NULL,
 UNIQUE INDEX keyIdx(proto,lhost,lport,rhost,rport),
 PRIMARY KEY(id)
);

CREATE TABLE enm_lvs_stats (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 lvsid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_lvs(id)",
 conns SMALLINT UNSIGNED,
 inpkts INT UNSIGNED,
 outpkts INT UNSIGNED,
 inbytes INT UNSIGNED,
 outbytes INT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_lvs_conntrack (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 tcp MEDIUMINT UNSIGNED NOT NULL,
 udp MEDIUMINT UNSIGNED NOT NULL,
 port_Other MEDIUMINT UNSIGNED NOT NULL,
 port_56834 MEDIUMINT UNSIGNED NOT NULL,
 port_2049 MEDIUMINT UNSIGNED NOT NULL,
 port_6513 MEDIUMINT UNSIGNED NOT NULL,
 port_80 MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE enm_lvs_viphost (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 vip VARCHAR(32)  NOT NULL COLLATE latin1_general_cs,
 nicid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES network_interfaces(id)",
 INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE enm_healthcheck_thresholds (
 preset_name VARCHAR(128) NOT NULL,
 CPU_AVG_THRESHOLD TINYINT UNSIGNED NOT NULL,
 CPU_MAX_THRESHOLD TINYINT UNSIGNED NOT NULL,
 IOWAIT_AVG_THRESHOLD TINYINT UNSIGNED NOT NULL,
 IOWAIT_MAX_THRESHOLD TINYINT UNSIGNED NOT NULL,
 GC_AVG_THRESHOLD INT UNSIGNED NOT NULL,
 GC_MAX_THRESHOLD INT UNSIGNED NOT NULL,
 QUEUE_THRESHOLD INT UNSIGNED NOT NULL,
 TOPIC_THRESHOLD INT UNSIGNED NOT NULL,
 AVGROP_DURATION_THRESHOLD MEDIUMINT UNSIGNED NOT NULL,
 MAXROP_DURATION_THRESHOLD MEDIUMINT UNSIGNED NOT NULL,
 SUCCESS_RATE_THRESHOLD TINYINT UNSIGNED NOT NULL,
 MIN_RATE_MOS MEDIUMINT UNSIGNED NOT NULL,
 AVERAGE_RATE_MOS MEDIUMINT UNSIGNED NOT NULL,
 EXPORT_TIME_THRESHOLD SMALLINT UNSIGNED NOT NULL,
 PCT_NODES_EXPORTED TINYINT UNSIGNED NOT NULL,
 AVG_SYNC_THRESHOLD MEDIUMINT UNSIGNED NOT NULL,
 MIN_SYNC_THRESHOLD MEDIUMINT UNSIGNED NOT NULL,
 MAX_KVM_LD_USAGE TINYINT UNSIGNED NOT NULL,
 MAX_BM_LUN_USAGE TINYINT UNSIGNED NOT NULL,
 MAX_LVM_FS_USAGE TINYINT UNSIGNED NOT NULL,
 MIN_CPP_SUBSCRIBED_PCT_THRESHOLD TINYINT UNSIGNED NOT NULL,
 MIN_CPP_SYNCED_PCT_THRESHOLD TINYINT UNSIGNED NOT NULL,
 CRITICAL_MS_VAR_USAGE TINYINT UNSIGNED NOT NULL,
 WARNING_MS_VAR_USAGE TINYINT UNSIGNED NOT NULL,
 CRITICAL_LOG_ENTRIES INT UNSIGNED NOT NULL,
 WARNING_LOG_ENTRIES INT UNSIGNED NOT NULL,
 WARNING_CPP_DELTA_SYNC_PCT SMALLINT UNSIGNED NOT NULL,
 CRITICAL_CPP_DELTA_SYNC_PCT SMALLINT UNSIGNED NOT NULL,
 WARNING_CPP_FULL_SYNC_PCT SMALLINT UNSIGNED NOT NULL,
 CRITICAL_CPP_FULL_SYNC_PCT SMALLINT UNSIGNED NOT NULL,
 NIC_RX_AVG_PER_VM INT UNSIGNED NOT NULL,
 NIC_TX_AVG_PER_VM INT UNSIGNED NOT NULL,
 NIC_RX_AVG_PER_NONVM INT UNSIGNED NOT NULL,
 NIC_TX_AVG_PER_NONVM INT UNSIGNED NOT NULL,
 WARNING_LIVE_CREATED_MOS SMALLINT UNSIGNED NOT NULL,
 WARNING_LIVE_UPDATED_MOS SMALLINT UNSIGNED NOT NULL,
 WARNING_LIVE_DELETED_MOS SMALLINT UNSIGNED NOT NULL,
 WARNING_NONLIVE_CREATED_MOS SMALLINT UNSIGNED NOT NULL,
 WARNING_NONLIVE_UPDATED_MOS SMALLINT UNSIGNED NOT NULL,
 WARNING_NONLIVE_DELETED_MOS SMALLINT UNSIGNED NOT NULL,
 DISK_MAX_BUSY TINYINT UNSIGNED NOT NULL,
 DISK_AVG_BUSY TINYINT UNSIGNED NOT NULL,
 DISK_AVG_SERV TINYINT UNSIGNED NOT NULL,
 WARNING_PUPPET_EXECUTION_TIME MEDIUMINT UNSIGNED NOT NULL,
 CRITICAL_PUPPET_EXECUTION_TIME MEDIUMINT UNSIGNED NOT NULL,
 PCT_FILE_DESCRIPTORS_INCREASE TINYINT UNSIGNED NOT NULL,
 PRIMARY KEY (preset_name)
);

CREATE TABLE ede_controller_node_count (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 ede_instance VARCHAR(50) NOT NULL,
 data_source_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES data_source_id_mapping(id)",
 nodeCount SMALLINT UNSIGNED NOT NULL,
 INDEX edeControllerNodeCountIndex(siteid,ede_instance,data_source_id)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_wpserv_instr  (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 totalIncomingEvents BIGINT UNSIGNED NOT NULL,
 totalLoss BIGINT UNSIGNED NOT NULL,
 totalPushedEvents BIGINT UNSIGNED NOT NULL,
 totalSubscriber BIGINT UNSIGNED NOT NULL,
 INDEX siteWpservIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mscm_attrib_names
(
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_mscm_notifrec
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    eventtype ENUM( 'AVC', 'CREATE', 'DELETE', 'SDN' ) NOT NULL COLLATE latin1_general_cs,
    moid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
    attribid SMALLINT UNSIGNED COMMENT "REFERENCES enm_mscm_attrib_names(id)",
    count INT UNSIGNED NOT NULL,
    INDEX myidx (date,siteid)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mscm_notiftop
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
    count MEDIUMINT UNSIGNED NOT NULL,
    INDEX myidx (siteid,date)
);

CREATE TABLE eniq_ltees_counter_details (
 datetime DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 counter_name VARCHAR(100) NOT NULL,
 INDEX lteesCountersIndex(datetime,siteid)
) PARTITION BY RANGE ( TO_DAYS(datetime) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_sfs_storage_fs_details (
 date DATE NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 fileSystem varchar(30) NOT NULL,
 status varchar(10) NOT NULL,
 fileSystemSize varchar(10) NOT NULL,
 layout varchar(20) NOT NULL,
 mirrors varchar(20) NOT NULL,
 columns varchar(20) NOT NULL,
 usePercentage FLOAT(5,1) UNSIGNED NOT NULL,
 nfsShared varchar(5) NOT NULL,
 cifsShared varchar(5) NOT NULL,
 ftpShared varchar(5) NOT NULL,
 secondaryTier varchar(5) NOT NULL,
 poolList varchar(20) NOT NULL,
 INDEX sfsStorageIndex(siteId,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_sfs_snap_cache_status (
 date DATE NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 cacheName VARCHAR(30) NOT NULL,
 totalCache INT UNSIGNED NOT NULL,
 usedCache INT UNSIGNED NOT NULL,
 usedCachePercent SMALLINT UNSIGNED NOT NULL,
 availableCache INT UNSIGNED NOT NULL,
 availableCachePercent SMALLINT UNSIGNED NOT NULL,
 sdcnt SMALLINT UNSIGNED NOT NULL,
 INDEX sfsCacheStatusIndex(date,siteId)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fmfmx_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    acksSentToFMX  BIGINT UNSIGNED NOT NULL,
    unAcksSentToFMX  BIGINT UNSIGNED NOT NULL,
    clearsSentToFMX  BIGINT UNSIGNED NOT NULL,
    activeSubscriptionsCount INT UNSIGNED NOT NULL,
    numberOfShowAlarmRequests  BIGINT UNSIGNED NOT NULL,
    numberOfHideAlarmRequests  BIGINT UNSIGNED NOT NULL,
    numberOfAlarmsSyncRequests  BIGINT UNSIGNED NOT NULL,
    numberOfUpdateAlarmRequests  BIGINT UNSIGNED NOT NULL,
    newAlarmsFromFMX  BIGINT UNSIGNED NOT NULL,
    totalNumberOfAlarmsSentToFMX  BIGINT UNSIGNED NOT NULL,
    INDEX siteFmFmxInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_delta_topology (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 files SMALLINT UNSIGNED NOT NULL,
 ossrc SMALLINT UNSIGNED NOT NULL,
 INDEX deltaTopologyIndex(siteid,time)
);

CREATE TABLE virtualconnect (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 INDEX vcIndex(siteid,date)
);

CREATE TABLE mscmip_supervision_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 startSupervision SMALLINT UNSIGNED NOT NULL,
 stoppedSupervision SMALLINT UNSIGNED NOT NULL,
 failedSubscriptionValidations SMALLINT UNSIGNED NOT NULL,
 successfullSubscriptionValidations SMALLINT UNSIGNED NOT NULL,
 INDEX siteMscmipSupInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

 CREATE TABLE mscmip_sync_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 dpsCounterForSuccessfulSync SMALLINT UNSIGNED NOT NULL,
 dpsNumberOfFailedSyncs SMALLINT UNSIGNED NOT NULL,
 numberOfMosBeingSynced MEDIUMINT UNSIGNED NOT NULL,
 fh6000numberOfMosBeingSynced MEDIUMINT UNSIGNED NOT NULL,
 fh6000dpsNumberOfFailedSyncs SMALLINT UNSIGNED NOT NULL,
 fh6000dpsCounterForSuccessfulSync SMALLINT UNSIGNED NOT NULL,
 fh6080numberOfMosBeingSynced MEDIUMINT UNSIGNED NOT NULL,
 fh6080dpsNumberOfFailedSyncs SMALLINT UNSIGNED NOT NULL,
 fh6080dpsCounterForSuccessfulSync SMALLINT UNSIGNED NOT NULL,
 INDEX siteMscmSyncInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

 CREATE TABLE mscmip_notif_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 totalBufferableNotifications SMALLINT UNSIGNED NOT NULL,
 totalAvcCreateDeleteNotifications SMALLINT UNSIGNED NOT NULL,
 totalAvcCreateDeleteSuccessfulNotifications SMALLINT UNSIGNED NOT NULL,
 totalAvcCreateDeleteFailedNotifications SMALLINT UNSIGNED NOT NULL,
 totalAvcNotifications SMALLINT UNSIGNED NOT NULL,
 totalCreateNotifications SMALLINT UNSIGNED NOT NULL,
 totalDeleteNotifications SMALLINT UNSIGNED NOT NULL,
 totalNotificationsCounter SMALLINT UNSIGNED NOT NULL,
 averageTimeTakenPerNotification MEDIUMINT UNSIGNED NOT NULL,
 averageBufferedTimePerNotification MEDIUMINT UNSIGNED NOT NULL,
 averageDpsBeforeBufferedTimePerNotification MEDIUMINT UNSIGNED NOT NULL,
 averageDpsEndTimePerNotification MEDIUMINT UNSIGNED NOT NULL,
 maxTimeTakenPerNotification MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteMscmipNotifInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

 CREATE TABLE mscmip_yangcud_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 averageOverallYangOperationTimeTaken MEDIUMINT UNSIGNED NOT NULL,
 maxOverallYangOperationTimeTaken MEDIUMINT UNSIGNED NOT NULL,
 minOverallYangOperationTimeTaken MEDIUMINT UNSIGNED NOT NULL,
 noOfFailedYangOperations SMALLINT UNSIGNED NOT NULL,
 numberOfYangOperationsForCreate SMALLINT UNSIGNED NOT NULL,
 numberOfYangOperationsForDelete SMALLINT UNSIGNED NOT NULL,
 numberOfYangOperationsForModify SMALLINT UNSIGNED NOT NULL,
 numberOfYangRpcRequests SMALLINT UNSIGNED NOT NULL,
 overallYangOperationTimeTaken MEDIUMINT UNSIGNED NOT NULL,
 yangRpcConstructionTime MEDIUMINT UNSIGNED NOT NULL,
 yangRpcResponseTime MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteMscmipYangInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE shm_waitingjob_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    NHCWaitingMainJobs SMALLINT UNSIGNED,
    NHCWaitingNEJobs SMALLINT UNSIGNED,
    licenseRefreshWaitingMainJobs SMALLINT UNSIGNED,
    licenseRefreshWaitingNEJobs SMALLINT UNSIGNED,
    dusGen2LicenseRefreshWaitingNeJobs SMALLINT  UNSIGNED,
    vRANUpgradeWaitingMainJobs SMALLINT UNSIGNED,
    vRANUpgradeWaitingNEJobs SMALLINT UNSIGNED,
    INDEX siteShmcoreservwaitingjobIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE shm_mainjob_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    upgradeMainJobs MEDIUMINT UNSIGNED NOT NULL,
    backupMainJobs MEDIUMINT UNSIGNED NOT NULL,
    licenseMainJobs MEDIUMINT UNSIGNED NOT NULL,
    restoreMainJobs MEDIUMINT UNSIGNED NOT NULL,
    deleteBackupMainJobs MEDIUMINT UNSIGNED NOT NULL,
    deleteUpgradePackageMainJobs MEDIUMINT UNSIGNED NOT NULL,
    nodeHealthCheckMainJobs SMALLINT UNSIGNED,
    lkfRefreshMainJobs SMALLINT UNSIGNED,
    INDEX siteShmcoreservmainjobIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE shm_upgradejob_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    upgradeJobAverage MEDIUMINT UNSIGNED NOT NULL,
    sgsnUpgradeJobAverage MEDIUMINT UNSIGNED NOT NULL,
    erbsUpgradeJobAverage MEDIUMINT UNSIGNED NOT NULL,
    dusGen2UpgradeJobAverage MEDIUMINT UNSIGNED NOT NULL,
    upgradeJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    sgsnUpgradeJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    erbsUpgradeJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    dusGen2UpgradeJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    INDEX siteShmcoreservupgradejobIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE shm_deletebackupjob_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    deleteBackupJobAverage MEDIUMINT UNSIGNED NOT NULL,
    sgsnDeleteBackupJobAverage MEDIUMINT UNSIGNED NOT NULL,
    erbsDeleteBackupJobAverage MEDIUMINT UNSIGNED NOT NULL,
    dusGen2DeleteBackupJobAverage MEDIUMINT UNSIGNED NOT NULL,
    backupBackupJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    sgsnDeleteBackupJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    erbsDeleteBackupJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    dusGen2DeleteBackupJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    INDEX siteShmcoreservdeletebackupjobIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE shm_licensejob_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    licenseJobAverage MEDIUMINT UNSIGNED NOT NULL,
    sgsnLicenseJobAverage MEDIUMINT UNSIGNED NOT NULL,
    erbsLicenseJobAverage MEDIUMINT UNSIGNED NOT NULL,
    dusGen2LicenseJobAverage MEDIUMINT UNSIGNED NOT NULL,
    licenseJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    erbsLicenseJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    dusGen2LicenseJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    sgsnLicenseJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    INDEX siteShmcoreservlicensejobIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE shm_restorejob_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    restoreJobAverage MEDIUMINT UNSIGNED NOT NULL,
    sgsnRestoreJobAverage MEDIUMINT UNSIGNED NOT NULL,
    erbsRestoreJobAverage MEDIUMINT UNSIGNED NOT NULL,
    dusGen2RestoreJobAverage MEDIUMINT UNSIGNED NOT NULL,
    restoreJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    sgsnRestoreJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    erbsRestoreJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    dusGen2RestoreJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    INDEX siteShmcoreservrestorejobIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE shm_backupjob_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    backupJobAverage MEDIUMINT UNSIGNED NOT NULL,
    sgsnBackupJobAverage MEDIUMINT UNSIGNED NOT NULL,
    erbsBackupJobAverage MEDIUMINT UNSIGNED NOT NULL,
    dusGen2BackupJobAverage MEDIUMINT UNSIGNED NOT NULL,
    backupJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    sgsnBackupJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    erbsBackupJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    dusGen2BackupJobSuccessRate SMALLINT UNSIGNED NOT NULL,
    INDEX siteShmcoreservbackupjobIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_activation (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 jobid BIGINT UNSIGNED NOT NULL ,
 start  DATETIME(2) NOT NULL,
 end DATETIME(2) NOT NULL,
 successfulChanges SMALLINT UNSIGNED NOT NULL,
 failedChanges SMALLINT UNSIGNED NOT NULL,
 result ENUM ('SUCCESS','FAILURE','PARTIAL') COLLATE latin1_general_cs,
 processedChangessPerSec FLOAT UNSIGNED NOT NULL,
 configName VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 statusDetail VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 INDEX siteIdStartIdx(siteid,start)
) PARTITION BY RANGE ( TO_DAYS(start) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_history (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 jobid BIGINT UNSIGNED NOT NULL,
 start DATETIME(2) NOT NULL,
 end DATETIME(2) NOT NULL,
 totalMoToWrite SMALLINT UNSIGNED NOT NULL,
 mib_root_created SMALLINT UNSIGNED NOT NULL,
 mo_created SMALLINT UNSIGNED NOT NULL,
 attribute_modification SMALLINT UNSIGNED NOT NULL,
 mo_deleted SMALLINT UNSIGNED NOT NULL,
 action_performed SMALLINT UNSIGNED NOT NULL,
 size INT UNSIGNED NOT NULL,
 INDEX cmhistidx(siteid,jobid,start)
 ) PARTITION BY RANGE ( TO_DAYS(start) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_sgeh_success_handling (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 eventid SMALLINT UNSIGNED NOT NULL,
 total_ingress BIGINT UNSIGNED NOT NULL,
 success_ingress BIGINT UNSIGNED NOT NULL,
 succ_db_egress BIGINT UNSIGNED NOT NULL,
 succ_cand_for_filter BIGINT UNSIGNED NOT NULL,
 INDEX eniqSuccessHandling(time,siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_apache_uri (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 uri VARCHAR(512) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX uriIdx(uri),
 PRIMARY KEY(id)
);

CREATE TABLE enm_apache_app_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_apache_requests (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 method ENUM ( 'GET', 'POST', 'HEAD', 'PUT', 'DELETE' ),
 uriid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_apache_uri(id)",
 appid SMALLINT UNSIGNED COMMENT "REFERENCES enm_apache_app_names(id)",
 requests MEDIUMINT UNSIGNED NOT NULL,
 sgid SMALLINT UNSIGNED COMMENT "REFERENCES enm_servicegroup_names(id)",
 INDEX siteDateIndex(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_apache_srv_unavail (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 uriid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_apache_uri(id)",
 num SMALLINT UNSIGNED NOT NULL,
 INDEX siteDateIndex(siteid,date)
);

CREATE TABLE enm_ui_app_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_ui_app (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 uiappid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ui_app_names(id)",
 num SMALLINT UNSIGNED,
 n_users SMALLINT UNSIGNED,
 INDEX siteDateIdx(siteid,date)
);

CREATE TABLE enm_apache_slots (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 internal SMALLINT UNSIGNED,
 external SMALLINT UNSIGNED,
 keepalive SMALLINT UNSIGNED,
 sendreply SMALLINT UNSIGNED,
 waitingconn SMALLINT UNSIGNED,
 other SMALLINT UNSIGNED,
 INDEX siteDateIndex(siteid,time)
);

CREATE TABLE enm_context_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_sg_contexts (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serviceid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_servicegroup_names(id)",
 contextid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_context_names(id)",
 INDEX siteIdDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date))
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE shm_nejob_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    cppUpgradeNeJobs MEDIUMINT UNSIGNED NOT NULL,
    cppBackupNeJobs MEDIUMINT UNSIGNED NOT NULL,
    cppLicenseNeJobs MEDIUMINT UNSIGNED NOT NULL,
    cppRestoreNeJobs MEDIUMINT UNSIGNED NOT NULL,
    cppDeleteBackupNeJobs MEDIUMINT UNSIGNED NOT NULL,
    cppDeleteUpgradePackageNeJobs MEDIUMINT UNSIGNED NOT NULL,
    ecimUpgradeNeJobs MEDIUMINT UNSIGNED NOT NULL,
    ecimBackupNeJobs MEDIUMINT UNSIGNED NOT NULL,
    ecimLicenseNeJobs MEDIUMINT UNSIGNED NOT NULL,
    ecimRestoreNeJobs MEDIUMINT UNSIGNED NOT NULL,
    ecimDeleteBackupNeJobs MEDIUMINT UNSIGNED NOT NULL,
    ecimDeleteUpgradePackageNeJobs MEDIUMINT UNSIGNED NOT NULL,
    ecimNodeHealthCheckNeJobscount SMALLINT UNSIGNED,
    AXEUpgradeNeJobs MEDIUMINT UNSIGNED NOT NULL,
    axeLicenseNeJobs SMALLINT UNSIGNED,
    AXEBackupNeJobs SMALLINT UNSIGNED,
    AXEDeleteBackupNeJobs SMALLINT UNSIGNED,
    ecimLicenseRefreshNeJobscount SMALLINT UNSIGNED,
    vranUpgradeNeJobscount SMALLINT UNSIGNED,
    INDEX siteShmcoreservNejobIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time))
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE shm_activityjob_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    cppUpgradeInstalls  MEDIUMINT UNSIGNED NOT NULL,
    cppUpgradeVerifies  MEDIUMINT UNSIGNED NOT NULL,
    cppUpgradeUpgrades  MEDIUMINT UNSIGNED NOT NULL,
    cppUpgradeConfirms  MEDIUMINT UNSIGNED NOT NULL,
    cppBackupCreateCVs  MEDIUMINT UNSIGNED NOT NULL,
    cppBackupSetCVAsStartables  MEDIUMINT UNSIGNED NOT NULL,
    cppBackupSetCVFirstInRollbackLists  MEDIUMINT UNSIGNED NOT NULL,
    cppBackupExportCVs MEDIUMINT UNSIGNED NOT NULL,
    cppLicenseInstalls MEDIUMINT UNSIGNED NOT NULL,
    cppDeleteBackup MEDIUMINT UNSIGNED NOT NULL,
    cppDeleteUpgradePackages MEDIUMINT UNSIGNED NOT NULL,
    cppRestoreDownloadCVs MEDIUMINT UNSIGNED NOT NULL,
    cppRestoreVerifyCVs MEDIUMINT UNSIGNED NOT NULL,
    cppRestoreInstallCVs MEDIUMINT UNSIGNED NOT NULL,
    cppRestoreRestores MEDIUMINT UNSIGNED NOT NULL,
    cppRestoreConfirmCVs MEDIUMINT UNSIGNED NOT NULL,
    ecimUpgradePrepares MEDIUMINT UNSIGNED NOT NULL,
    ecimUpgradeVerifys  MEDIUMINT UNSIGNED NOT NULL,
    ecimUpgradeActivates  MEDIUMINT UNSIGNED NOT NULL,
    ecimUpgradeConfirms   MEDIUMINT UNSIGNED NOT NULL,
    ecimBackupCreateBackups  MEDIUMINT UNSIGNED NOT NULL,
    ecimBackupUploads MEDIUMINT UNSIGNED NOT NULL,
    ecimDeleteBackups MEDIUMINT UNSIGNED NOT NULL,
    ecimDeleteUpgradePackages MEDIUMINT UNSIGNED NOT NULL,
    ecimRestoreDownloadBackups MEDIUMINT UNSIGNED NOT NULL,
    ecimRestoreRestoreBackups  MEDIUMINT UNSIGNED NOT NULL,
    ecimRestoreConfirmBackups  MEDIUMINT UNSIGNED NOT NULL,
    ecimLicenseInstalls  MEDIUMINT UNSIGNED NOT NULL,
    ecimNodeHealthCHecks SMALLINT UNSIGNED,
    axeLicenseInstalls SMALLINT UNSIGNED,
    axeBackupCreateBackups SMALLINT UNSIGNED,
    axeBackupUploads SMALLINT UNSIGNED,
    axeDeleteBackups SMALLINT UNSIGNED,
    ecimLicenseRefreshJobRefreshActivities SMALLINT UNSIGNED,
    ecimLicenseRefreshJobRequestActivities SMALLINT UNSIGNED,
    ecimLicenseRefreshJobInstallActivities SMALLINT UNSIGNED,
    vranUpgradeActivates SMALLINT UNSIGNED,
    vranUpgradeConfirms SMALLINT UNSIGNED,
    vranUpgradePrepares SMALLINT UNSIGNED,
    vranUpgradeVerifies SMALLINT UNSIGNED,
    INDEX siteShmcoreservActivityjobIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE son_mo_additions (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 moid SMALLINT NOT NULL COMMENT "REFERENCES mo_names(id)",
 created_operator INT UNSIGNED NOT NULL,
 deleted_operator INT UNSIGNED NOT NULL,
 modified_operator INT UNSIGNED NOT NULL,
 modified_not INT UNSIGNED NOT NULL,
 modified_mro INT UNSIGNED NOT NULL,
 modified_pci INT UNSIGNED NOT NULL,
 modified_mlb INT UNSIGNED NOT NULL,
 modified_rach_opt INT UNSIGNED NOT NULL,
 modified_lm INT UNSIGNED NOT NULL,
 avc_cache_miss INT UNSIGNED NOT NULL,
 INDEX idx1 (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE son_rate_additions (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 created_operator INT UNSIGNED NOT NULL,
 deleted_operator INT UNSIGNED NOT NULL,
 modified_operator INT UNSIGNED NOT NULL,
 modified_not INT UNSIGNED NOT NULL,
 modified_mro INT UNSIGNED NOT NULL,
 modified_pci INT UNSIGNED NOT NULL,
 modified_mlb INT UNSIGNED NOT NULL,
 modified_rach_opt INT UNSIGNED NOT NULL,
 modified_lm INT UNSIGNED NOT NULL,
 avc_cache_miss INT UNSIGNED NOT NULL,
 INDEX idx1 (siteid,time)
);

CREATE TABLE son_cio_changes(
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 moid SMALLINT NOT NULL COMMENT "REFERENCES mo_names(id)",
 cellIndividualOffsetEUtran INT NOT NULL,
 modified_operator INT UNSIGNED NOT NULL,
 modified_mro INT UNSIGNED NOT NULL,
 modified_other INT UNSIGNED NOT NULL,
 modified_cache_miss INT UNSIGNED NOT NULL,
 INDEX idx1 (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE son_qOffset_changes (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 moid SMALLINT NOT NULL COMMENT "REFERENCES mo_names(id)",
 qOffsetCellEUtran INT NOT NULL,
 modified_operator INT UNSIGNED NOT NULL,
 modified_mro INT UNSIGNED NOT NULL,
 modified_other INT UNSIGNED NOT NULL,
 modified_cache_miss INT UNSIGNED NOT NULL,
 INDEX idx1 (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE son_cio_qOffset_rate (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 cio_modified_operator INT UNSIGNED NOT NULL,
 cio_modified_mro INT UNSIGNED NOT NULL,
 cio_modified_other INT UNSIGNED NOT NULL,
 cio_modified_cache_miss INT UNSIGNED NOT NULL,
 qOffset_modified_operator INT UNSIGNED NOT NULL,
 qOffset_modified_mro INT UNSIGNED NOT NULL,
 qOffset_modified_other INT UNSIGNED NOT NULL,
 qOffset_modified_cache_miss INT UNSIGNED NOT NULL,
 INDEX idx1 (siteid,time)
);

CREATE TABLE eniq_lteefa_rf_enrichment (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 eventid SMALLINT UNSIGNED NOT NULL,
 success INT UNSIGNED NOT NULL,
 failure INT UNSIGNED NOT NULL,
 outofwindow INT UNSIGNED NOT NULL,
 INDEX eniqLteefaRfEnrichment(time,siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_apserv_metrics (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 useCaseType VARCHAR(50) NOT NULL,
 status VARCHAR(30) NOT NULL,
 executionTime MEDIUMINT UNSIGNED DEFAULT NULL,
 totalNode SMALLINT(5) UNSIGNED DEFAULT NULL,
 view VARCHAR(30) DEFAULT 'Metric' NOT NULL,
 relationType VARCHAR(50) DEFAULT 'NA' NOT NULL,
 INDEX apservMetricsIdx(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_ltees_topology (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 NoOfEnodeB INT UNSIGNED NOT NULL,
 NoOfCells INT UNSIGNED NOT NULL,
 NoOfExtCells INT UNSIGNED NOT NULL,
 NoOfEutranCellRelations INT UNSIGNED NOT NULL,
 NoOfUtranCellRelations INT UNSIGNED NOT NULL,
 NoOfGeranCellRelations INT UNSIGNED NOT NULL,
 NoOfExtEUtranCells INT UNSIGNED NOT NULL,
 NoOfExtUtranCells INT UNSIGNED NOT NULL,
 NoOfExtGeranCells INT UNSIGNED NOT NULL,
 INDEX eniqLteesTopology (siteid,time,wfid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE son_moc_rate (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 moType VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 created_anr INT UNSIGNED NOT NULL,
 deleted_anr INT UNSIGNED NOT NULL,
 modified_anr INT UNSIGNED NOT NULL,
 created_x2 INT UNSIGNED NOT NULL,
 deleted_x2 INT UNSIGNED NOT NULL,
 modified_x2 INT UNSIGNED NOT NULL,
 created_operator INT UNSIGNED NOT NULL,
 deleted_operator INT UNSIGNED NOT NULL,
 modified_operator INT UNSIGNED NOT NULL,
 modified_not INT UNSIGNED NOT NULL,
 modified_mro INT UNSIGNED NOT NULL,
 modified_pci INT UNSIGNED NOT NULL,
 modified_mlb INT UNSIGNED NOT NULL,
 modified_rach_opt INT UNSIGNED NOT NULL,
 modified_lm INT UNSIGNED NOT NULL,
 avc_cache_miss INT UNSIGNED NOT NULL,
 INDEX idx1 (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE cm_import (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 jobid MEDIUMINT UNSIGNED NOT NULL,
 status VARCHAR(64) DEFAULT NULL COLLATE latin1_general_cs,
 job_start DATETIME DEFAULT NULL,
 job_end DATETIME DEFAULT NULL,
 validate_schema_time INT UNSIGNED DEFAULT NULL,
 parsing_time INT UNSIGNED DEFAULT NULL,
 model_validation_time INT UNSIGNED DEFAULT NULL,
 copy_time INT UNSIGNED DEFAULT NULL,
 import_time INT UNSIGNED DEFAULT NULL,
 nodes_copied SMALLINT UNSIGNED DEFAULT NULL,
 nodes_not_copied SMALLINT UNSIGNED DEFAULT NULL,
 mos_created MEDIUMINT UNSIGNED DEFAULT NULL,
 mos_updated MEDIUMINT UNSIGNED DEFAULT NULL,
 mos_deleted MEDIUMINT UNSIGNED DEFAULT NULL,
 file_format VARCHAR(64) DEFAULT NULL COLLATE latin1_general_cs,
 configuration VARCHAR(256) DEFAULT NULL COLLATE latin1_general_cs,
 import_file VARCHAR(256) DEFAULT NULL COLLATE latin1_general_cs,
 error_handling VARCHAR(256) NOT NULL,
 instance_validation VARCHAR(256) NOT NULL,
 lastValidationTime DATETIME,
 interfaceType ENUM('CM Bulk UI/NBI V2', 'CLI/NBI V1'),
 executionType ENUM('parallel', 'sequential'),
 averageBatchExecutionTime MEDIUMINT UNSIGNED,
 totalBatchExecutionTime MEDIUMINT UNSIGNED,
 numberOfNodes SMALLINT UNSIGNED,
 numberOfPartitions SMALLINT UNSIGNED,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 INDEX siteIdJobEndIdx(siteid, job_end)
) PARTITION BY RANGE ( TO_DAYS(job_end) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_import_ntn (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 jobid MEDIUMINT UNSIGNED NOT NULL,
 numberOfPartitionsWithBscmChanges SMALLINT UNSIGNED,
 ntnMosCreated SMALLINT UNSIGNED,
 ntnMosModified SMALLINT UNSIGNED,
 ntnMosDeleted SMALLINT UNSIGNED,
 numberOfNtnResultEvents SMALLINT UNSIGNED,
 ntnNumberOfPartialEvents SMALLINT UNSIGNED,
 ntnNumberOfFailedEvents SMALLINT UNSIGNED,
 averageEventWaitTime SMALLINT UNSIGNED,
 totalEventWaitTime SMALLINT UNSIGNED,
 maxEventWaitTime SMALLINT UNSIGNED,
 numberOfComNoResources SMALLINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_oom_error (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date  DATETIME(2) NOT NULL,
 serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 program VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 errorCount SMALLINT UNSIGNED  DEFAULT NULL,
 INDEX oomidx(siteid,date)
 );

CREATE TABLE enm_workload_profile_category (
 id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_workload_profile_errors (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 categoryid TINYINT UNSIGNED DEFAULT NULL COMMENT "REFERENCES enm_workload_profile_category(id)",
 profilenum VARCHAR(5) COLLATE latin1_general_cs DEFAULT NULL,
 errcount SMALLINT UNSIGNED NOT NULL,
 INDEX siteDateIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_profilelog (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATETIME DEFAULT NULL,
 categoryid TINYINT UNSIGNED DEFAULT NULL COMMENT "REFERENCES enm_workload_profile_category(id)",
 profilenum VARCHAR(5) COLLATE latin1_general_cs DEFAULT NULL,
 state ENUM('SLEEPING', 'STARTING', 'STOPPING', 'RUNNING', 'ERROR', 'DEAD', 'COMPLETED' ) DEFAULT NULL,
 INDEX profilesitedateIdx(siteid, date)
)PARTITION BY RANGE ( TO_DAYS(date) )
(
PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_nhc_acceptance_criteria (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 acceptance_criteria_name VARCHAR(128),
 UNIQUE INDEX acNameIdx(acceptance_criteria_name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_nhc_users (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user VARCHAR(64) COLLATE latin1_general_cs,
 UNIQUE INDEX userIdx(user),
 PRIMARY KEY(id)
);

CREATE TABLE enm_nhc_logs (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  activityID VARCHAR(60) NOT NULL DEFAULT '',
  usrID SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_nhc_users(id)",
  startTime VARCHAR(20) DEFAULT NULL,
  stopTime VARCHAR(20) DEFAULT NULL,
  nodeNo VARCHAR(20) DEFAULT NULL,
  checkList VARCHAR(150) DEFAULT NULL,
  acceptanceCriteriaID SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_nhc_acceptance_criteria(id)",
  requestNo VARCHAR(20) DEFAULT NULL,
  responseNo VARCHAR(20) DEFAULT NULL,
  reportName VARCHAR(60) DEFAULT NULL,
  INDEX cmservnhclogs(time,siteid)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_saidserv_instr  (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 dpsAttributeChangedEventCount MEDIUMINT UNSIGNED NOT NULL,
 dpsEventNotProcessedCount MEDIUMINT UNSIGNED NOT NULL,
 dpsObjectCreatedEventCount MEDIUMINT UNSIGNED NOT NULL,
 dpsObjectDeletedEventCount MEDIUMINT UNSIGNED NOT NULL,
 numberOfCellsResolved SMALLINT UNSIGNED NOT NULL,
 numberOfCheckConflicts SMALLINT UNSIGNED NOT NULL,
 numberOfConflictsResolved SMALLINT UNSIGNED NOT NULL,
 numberOfHighPriorityNetworkSyncEvents SMALLINT UNSIGNED NOT NULL,
 numberOfLowPriorityNetworkSyncEvents SMALLINT UNSIGNED NOT NULL,
 numberOfCellsNotProposed SMALLINT UNSIGNED NOT NULL,
 numberOfUniqueCellsConflicting SMALLINT UNSIGNED NOT NULL,
 dpsEUtranCellPciChangesCount  MEDIUMINT UNSIGNED NOT NULL,
 numberOfHistoricalConflictReportsCount MEDIUMINT UNSIGNED NOT NULL,
 numberOfHistoricalExcludedResultsReportsCount MEDIUMINT UNSIGNED NOT NULL,
 numberOfHistoricalPCIResolveResultsCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteSaidServIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_file_descriptors (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date  DATETIME(2) NOT NULL,
 serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 program VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 warningCount SMALLINT UNSIGNED  DEFAULT NULL,
 INDEX fidx(siteid,date)
);

CREATE TABLE cm_event_nbi_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 cmEventsNbiNumError INT UNSIGNED NOT NULL,
 cmEventsNbiNumQueries INT UNSIGNED NOT NULL,
 cmEventsNbiNumSuccess INT UNSIGNED NOT NULL,
 cmEventsNbiTotalDurationOfEvents INT UNSIGNED NOT NULL,
 cmEventsNbiTotalNumberOfEvents BIGINT UNSIGNED NOT NULL,
 INDEX cmEventNbiIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_secserv_comaa_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 numberOfInitialProxyBindReq MEDIUMINT UNSIGNED NOT NULL,
 numberOfInitialUserBindReq MEDIUMINT UNSIGNED NOT NULL,
 numberOfAddProxyBindReq MEDIUMINT UNSIGNED NOT NULL,
 numberOfAddUserBindReq MEDIUMINT UNSIGNED NOT NULL,
 numberOfSearchReq MEDIUMINT UNSIGNED NOT NULL,
 numberOfConnectionReq MEDIUMINT UNSIGNED NOT NULL,
 numberOfErrorDisconnection MEDIUMINT UNSIGNED NOT NULL,
 numberOfSuccessfullDisconnection MEDIUMINT UNSIGNED NOT NULL,
 totalTimeError BIGINT UNSIGNED NOT NULL,
 totalTimeSuccessful BIGINT UNSIGNED NOT NULL,
 maxNumberOfConnectionAlive MEDIUMINT UNSIGNED NOT NULL,
 numberOfProxyBindError MEDIUMINT UNSIGNED,
 numberOfUserBindError MEDIUMINT UNSIGNED,
 numberOfTlsHandshakeError MEDIUMINT UNSIGNED,
 numberOfFastConnection MEDIUMINT UNSIGNED,
 numberOfMediumConnection MEDIUMINT UNSIGNED,
 numberOfHighConnection MEDIUMINT UNSIGNED,
 numberOfSlowConnection MEDIUMINT UNSIGNED,
 numberOfSuccessfulTokenValidations MEDIUMINT UNSIGNED,
 numberOfFailedTokenValidations MEDIUMINT UNSIGNED,
 numberOfFastTokenValidations MEDIUMINT UNSIGNED,
 numberOfHighTokenValidations MEDIUMINT UNSIGNED,
 numberOfSlowTokenValidations MEDIUMINT UNSIGNED,
 INDEX siteSecservComaaIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fmx_monitor (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 startTime  DATETIME(2) NOT NULL,
 alarmCreated INT UNSIGNED,
 alarmDeleted INT UNSIGNED,
 activeAlarms INT UNSIGNED NOT NULL,
 RuleContextCreated INT UNSIGNED,
 RuleContextDeleted INT UNSIGNED,
 activeRuleContext INT UNSIGNED NOT NULL,
 INDEX fmxmonidx(siteid,startTime)
 )PARTITION BY RANGE ( TO_DAYS(startTime) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_coredump (
 collectionTime DATETIME NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 coredumpCreationTime DATETIME NOT NULL,
 coredumpName VARCHAR(100) NOT NULL,
 coredumpSize BIGINT UNSIGNED NOT NULL,
 INDEX coredumpIdx(siteId, collectionTime)
)PARTITION BY RANGE ( TO_DAYS(collectionTime) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_coredump_path (
 date DATE NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 coredumpPath VARCHAR(100) NOT NULL,
 coredumpPathType VARCHAR(20) NOT NULL,
 allocatedSpace VARCHAR(10) NOT NULL,
 usedSpace BIGINT UNSIGNED NOT NULL,
 coredumpCount SMALLINT UNSIGNED NOT NULL,
 INDEX coredumpPathIdx(siteId, date)
);

CREATE TABLE enm_profilelog_utilversion (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date  DATE NOT NULL,
 torutilitiesVersion VARCHAR(255),
 INDEX profileutilversionsitedateIdx(siteid,date)
);

CREATE TABLE enm_fmx_message_queue (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 startTime  DATETIME NOT NULL,
 allQueueLength INT UNSIGNED NOT NULL,
 allQueueRate FLOAT UNSIGNED NOT NULL,
 contextsQueueLength INT UNSIGNED NOT NULL,
 contextsQueueRate FLOAT UNSIGNED NOT NULL,
 INDEX fmxmsgqueueidx(siteid,startTime)
 )PARTITION BY RANGE ( TO_DAYS(startTime) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mscmnotification_logs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 totalnotificationsreceived MEDIUMINT UNSIGNED NOT NULL,
 totalnotificationsprocessed MEDIUMINT UNSIGNED NOT NULL,
 totalnotificationsdiscarded MEDIUMINT UNSIGNED NOT NULL,
 evictions MEDIUMINT UNSIGNED NOT NULL,
 largeNodeCacheMax MEDIUMINT UNSIGNED NOT NULL,
 cachesizemax MEDIUMINT UNSIGNED NOT NULL,
 cachesizeavg MEDIUMINT UNSIGNED,
 leadtimemax INT UNSIGNED NOT NULL,
 leadtimeavg INT UNSIGNED NOT NULL,
 validationhandlertimemax INT UNSIGNED NOT NULL,
 validationhandlertimeavg INT UNSIGNED,
 writehandlertimemax INT UNSIGNED NOT NULL,
 writehandlertimeavg INT UNSIGNED NOT NULL,
 bufferednodenotifications MEDIUMINT UNSIGNED,
 INDEX mscmServNotifIdx(siteid, time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE son_electrical_tilt_rate (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 down_tilt INT UNSIGNED NOT NULL,
 up_tilt INT UNSIGNED NOT NULL,
 neutral_tilt INT UNSIGNED NOT NULL,
 moi VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 old_value INT UNSIGNED NOT NULL,
 new_value INT UNSIGNED NOT NULL,
 tilt_difference INT NOT NULL,
 INDEX eTiltIndex (siteid,time)
);

CREATE TABLE enm_puppet_stoppages (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  date DATETIME DEFAULT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  INDEX puppetStatusIdx(siteid, date)
);

CREATE TABLE enm_puppet_failures (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  date DATE DEFAULT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  failures MEDIUMINT UNSIGNED NOT NULL,
  failed_dependencies MEDIUMINT UNSIGNED NOT NULL,
  INDEX puppetFailuresIdx(siteid, date)
);

CREATE TABLE eniq_lteefa_rfevents_load_balance (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 wfid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_workflow_names(id)",
 RfFiles SMALLINT UNSIGNED NOT NULL,
 Bytes INT UNSIGNED NOT NULL,
 BytesOnDisk INT UNSIGNED NOT NULL,
 RfEvents INT UNSIGNED NOT NULL,
 Delay INT UNSIGNED NOT NULL,
 INDEX lteefaLoadBalanceIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_feature_upgrade_list (
 date DATE NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 upgradeTypeId SMALLINT UNSIGNED NOT NULL,
 featureName VARCHAR(100) NOT NULL,
 INDEX featureUpgradeIdx(siteId, date)
);

CREATE TABLE eniq_upgrade_timing_detail (
 date DATE NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 upgradeSection VARCHAR(100) NOT NULL,
 upgradeStage VARCHAR(100) NOT NULL,
 upgradeStartTime DATETIME NOT NULL,
 upgradeEndTime DATETIME NOT NULL,
 upgradeExecutionTime TIME NOT NULL,
 INDEX upgradeTimingIdx(siteId, date)
);

CREATE TABLE eniq_missing_upgrade_detail (
 date DATE NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 upgradeSection VARCHAR(100) NOT NULL,
 upgradeStage VARCHAR(100) NOT NULL,
 upgradeFailureMessage VARCHAR(200) NOT NULL,
 INDEX missingUpgradeIdx(siteId, date)
);

CREATE TABLE eniq_ltees_latency (
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 fileCreationTime DATETIME NOT NULL,
 ropStartTime DATETIME NOT NULL,
 ropEndTime DATETIME NOT NULL,
 lteesFileSize FLOAT(5,2) UNSIGNED NOT NULL,
 fdnName VARCHAR(125) NOT NULL,
 latency SMALLINT UNSIGNED NOT NULL,
 INDEX lteesLatencyIdx (siteId, fileCreationTime)
) PARTITION BY RANGE ( TO_DAYS(fileCreationTime) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fmx_rule (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 startTime  DATETIME NOT NULL,
 engine SMALLINT UNSIGNED NOT NULL,
 blockType VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 moduleName VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 ruleName VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 blockID BIGINT UNSIGNED NOT NULL,
 blockkName VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 count BIGINT UNSIGNED NOT NULL,
 INDEX fmxruleidx(siteid,startTime)
 )PARTITION BY RANGE ( TO_DAYS(startTime) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE son_anr_augmentation (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 moi VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
 remAllowedTrueCount INT UNSIGNED NOT NULL,
 remAllowedFalseCount INT UNSIGNED NOT NULL,
 hoAllowedTrueCount INT UNSIGNED NOT NULL,
 hoAllowedFalseCount INT UNSIGNED NOT NULL,
 INDEX sonANRIndx (siteid,time)
);

CREATE TABLE enm_impexpserv_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    ExportStartInstrumentCount INT UNSIGNED NOT NULL,
    ExportStartInstrumentRestDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportStartInstrumentServiceDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportStartInstrumentRestDelay MEDIUMINT UNSIGNED NOT NULL,
    ExportStartInstrumentRestDelayPercentage SMALLINT UNSIGNED NOT NULL,
    ExportStatusInstrumentCount INT UNSIGNED NOT NULL,
    ExportStatusInstrumentRestDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportStatusInstrumentServiceDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportStatusInstrumentRestDelay MEDIUMINT UNSIGNED NOT NULL,
    ExportStatusInstrumentRestDelayPercentage SMALLINT UNSIGNED NOT NULL,
    ExportStatusListInstrumentCount INT UNSIGNED NOT NULL,
    ExportStatusListInstrumentRestDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportStatusListInstrumentServiceDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportStatusListInstrumentRestDelay MEDIUMINT UNSIGNED NOT NULL,
    ExportStatusListInstrumentRestDelayPercentage SMALLINT UNSIGNED NOT NULL,
    ExportReportInstrumentCount INT UNSIGNED NOT NULL,
    ExportReportInstrumentRestDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportReportInstrumentServiceDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportReportInstrumentRestDelay MEDIUMINT UNSIGNED NOT NULL,
    ExportReportInstrumentRestDelayPercentage SMALLINT UNSIGNED NOT NULL,
    ExportFiltersInstrumentCount INT UNSIGNED NOT NULL,
    ExportFiltersInstrumentRestDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportFiltersInstrumentServiceDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportFiltersInstrumentRestDelay MEDIUMINT UNSIGNED NOT NULL,
    ExportFiltersInstrumentRestDelayPercentage SMALLINT UNSIGNED NOT NULL,
    ExportDownloadInstrumentCount INT UNSIGNED NOT NULL,
    ExportDownloadInstrumentRestDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportDownloadInstrumentServiceDuration MEDIUMINT UNSIGNED NOT NULL,
    ExportDownloadInstrumentRestDelay MEDIUMINT UNSIGNED NOT NULL,
    ExportDownloadInstrumentRestDelayPercentage SMALLINT UNSIGNED NOT NULL,
    ImportDoUploadInstrumentCount INT UNSIGNED NOT NULL,
    ImportDoUploadInstrumentRestDuration MEDIUMINT UNSIGNED NOT NULL,
    ImportDoUploadInstrumentServiceDuration MEDIUMINT UNSIGNED NOT NULL,
    ImportDoUploadInstrumentRestDelay MEDIUMINT UNSIGNED NOT NULL,
    ImportDoUploadInstrumentRestDelayPercentage SMALLINT UNSIGNED NOT NULL,
    ImportJobDetailsInstrumentCount INT UNSIGNED NOT NULL,
    ImportJobDetailsInstrumentRestDuration MEDIUMINT UNSIGNED NOT NULL,
    ImportJobDetailsInstrumentServiceDuration MEDIUMINT UNSIGNED NOT NULL,
    ImportJobDetailsInstrumentRestDelay MEDIUMINT UNSIGNED NOT NULL,
    ImportJobDetailsInstrumentRestDelayPercentage SMALLINT UNSIGNED NOT NULL,
    ImportAllJobDetailsInstrumentCount INT UNSIGNED NOT NULL,
    ImportAllJobDetailsInstrumentRestDuration MEDIUMINT UNSIGNED NOT NULL,
    ImportAllJobDetailsInstrumentServiceDuration MEDIUMINT UNSIGNED NOT NULL,
    ImportAllJobDetailsInstrumentRestDelay MEDIUMINT UNSIGNED NOT NULL,
    ImportAllJobDetailsInstrumentRestDelayPercentage SMALLINT UNSIGNED NOT NULL,
    ImportAllOperationInstrumentCount INT UNSIGNED NOT NULL,
    ImportAllOperationInstrumentRestDuration MEDIUMINT UNSIGNED NOT NULL,
    ImportAllOperationInstrumentServiceDuration MEDIUMINT UNSIGNED NOT NULL,
    ImportAllOperationInstrumentRestDelay MEDIUMINT UNSIGNED NOT NULL,
    ImportAllOperationInstrumentRestDelayPercentage SMALLINT UNSIGNED NOT NULL,
    INDEX impexpservSiteTimeIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_spsserv_entity_instr   (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 createExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 createMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 createMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 deleteExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 deleteMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 deleteMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 getExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 getMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 getMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 updateExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 updateMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 updateMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 INDEX spsServEntityInstrIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_spsserv_caentity_instr  (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 generateExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 generateMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 generateMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 rekeyExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 rekeyMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 rekeyMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 renewExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 renewMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 renewMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 deleteExecutionTimeTotalMillis SMALLINT UNSIGNED,
 deleteMethodFailures MEDIUMINT UNSIGNED,
 deleteMethodInvocations MEDIUMINT UNSIGNED,
 INDEX spsServCAEntityInstrIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_spsserv_endentity_instr  (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 generateExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 generateMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 generateMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 rekeyExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 rekeyMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 rekeyMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 renewExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 renewMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 renewMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 deleteExecutionTimeTotalMillis SMALLINT UNSIGNED,
 deleteMethodFailures MEDIUMINT UNSIGNED,
 deleteMethodInvocations MEDIUMINT UNSIGNED,
 INDEX spsServEndEntityInstrIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_sso_openam_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    AuthenticationSuccessCount BIGINT UNSIGNED NOT NULL,
    AuthenticationFailureCount BIGINT UNSIGNED NOT NULL,
    AuthenticationSuccessRate BIGINT UNSIGNED NOT NULL,
    AuthenticationFailureRate BIGINT UNSIGNED NOT NULL,
    SessionActiveCount BIGINT UNSIGNED NOT NULL,
    SessionCreatedCount BIGINT UNSIGNED NOT NULL,
    IdRepoCacheEntries BIGINT UNSIGNED NOT NULL,
    IdRepoCacheHits BIGINT UNSIGNED NOT NULL,
    IdRepoGetRqts BIGINT UNSIGNED NOT NULL,
    IdRepoSearchCacheHits BIGINT UNSIGNED NOT NULL,
    IdRepoSearchRqts BIGINT UNSIGNED NOT NULL,
    INDEX openamSiteTimeIdx(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_nhc_ac_modifications (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 acceptance_criteria_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_nhc_acceptance_criteria(id)",
 userid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_nhc_users(id)",
 INDEX nhcACModfSiteidTime(siteid,time)
);

CREATE TABLE enm_latest_healthcheck_summary (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 hc_summary TEXT NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX siteIdx(siteid)
);

CREATE TABLE enm_puppet_execution_times (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 start_time TIME NOT NULL,
 end_time TIME NOT NULL,
 INDEX puppetExecTimesSiteidDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_secserv_sls_instr   (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 credentialManagerGeneratePKCS12CredentialCalls BIGINT UNSIGNED NOT NULL,
 credentialManagerGeneratePKCS12CredentialCallsTotalTime BIGINT UNSIGNED NOT NULL,
 credentialManagerGenerateXMLCredentialCalls BIGINT UNSIGNED NOT NULL,
 credentialManagerGenerateXMLCredentialCallsTotalTime BIGINT UNSIGNED NOT NULL,
 credentialManagerListUsersTotalTime BIGINT UNSIGNED NOT NULL,
 credentialManagerRevokeCredentialsTotalTime BIGINT UNSIGNED NOT NULL,
 generateCredentialsErrors BIGINT UNSIGNED NOT NULL,
 generateCredentialsRequests BIGINT UNSIGNED NOT NULL,
 generateCredentialsTotalTime BIGINT UNSIGNED NOT NULL,
 listUsersErrors BIGINT UNSIGNED NOT NULL,
 listUsersRequests BIGINT UNSIGNED NOT NULL,
 listUsersTotalTime BIGINT UNSIGNED NOT NULL,
 revokeCredentialsErrors BIGINT UNSIGNED NOT NULL,
 revokeCredentialsRequests BIGINT UNSIGNED NOT NULL,
 revokeCredentialsTotalTime BIGINT UNSIGNED NOT NULL,
 INDEX secServSlsInstrIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_openalarms (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 num MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_revocation_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    undoJobId MEDIUMINT UNSIGNED NOT NULL,
    startTime DATETIME NOT NULL,
    endTime DATETIME NOT NULL,
    totalCreate MEDIUMINT UNSIGNED NOT NULL,
    totalDelete MEDIUMINT UNSIGNED NOT NULL,
    totalModify MEDIUMINT UNSIGNED NOT NULL,
    totalHistoryItems MEDIUMINT UNSIGNED NOT NULL,
    totalExcludedUnsupportedOperations MEDIUMINT UNSIGNED NOT NULL,
    totalExcludedNonNrmMos MEDIUMINT UNSIGNED NOT NULL,
    totalExcludedSystemCreatedMos MEDIUMINT UNSIGNED NOT NULL,
    queryDuration MEDIUMINT UNSIGNED NOT NULL,
    processingDuration MEDIUMINT UNSIGNED NOT NULL,
    fileWriteDuration MEDIUMINT UNSIGNED NOT NULL,
    application VARCHAR(255) NOT NULL,
    applicationJobId MEDIUMINT UNSIGNED NOT NULL,
    totalExcludedNotDeletableMos MEDIUMINT UNSIGNED,
    INDEX siteIdTimeIdx(siteid,endTime)
) PARTITION BY RANGE ( TO_DAYS(endTime) )
(
     PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_loading_parsing_duration (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 ropStartTime DATETIME NOT NULL,
 parsingStartTime DATETIME,
 loadingEndTime DATETIME,
 loadingTimeDuration SMALLINT UNSIGNED NOT NULL,
 duration SMALLINT UNSIGNED,
 INDEX loadingparsingduration(siteid, ropStartTime)
) PARTITION BY RANGE ( TO_DAYS(ropStartTime) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_str_jvm_names (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  jvm_name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(jvm_name),
  PRIMARY KEY(id)
);

CREATE TABLE enm_str_fwd (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  jvmid TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "REFERENCES enm_str_jvm_names(id)",
  time DATETIME NOT NULL,
  flowsDeployed SMALLINT UNSIGNED NOT NULL,
  eventsIn INT UNSIGNED NOT NULL,
  eventsOut INT UNSIGNED NOT NULL,
  eventsOut_asrn INT UNSIGNED,
  eventsIn_asrn INT UNSIGNED,
  INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_str_apeps (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  jvmid TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "REFERENCES enm_str_jvm_names(id)",
  time DATETIME NOT NULL,
  flowsDeployed SMALLINT UNSIGNED NOT NULL,
  eventsIn INT UNSIGNED NOT NULL,
  eventsOut INT UNSIGNED NOT NULL,
  eventsProcessed INT UNSIGNED NOT NULL,
  connectionsReceived SMALLINT UNSIGNED NOT NULL,
  disconnectsReceived SMALLINT UNSIGNED NOT NULL,
  missedConnectionProcessed SMALLINT UNSIGNED NOT NULL,
  rpmAvroEventsIn MEDIUMINT UNSIGNED,
  rttAvroEventsIn MEDIUMINT UNSIGNED,
  INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_esi_eventcounts (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 eventid SMALLINT UNSIGNED NOT NULL,
 eventcount BIGINT UNSIGNED NOT NULL,
 INDEX siteIdx(siteid, date)
);

CREATE TABLE enm_eba_eventcounts (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 eventid SMALLINT UNSIGNED NOT NULL,
 eventcount BIGINT UNSIGNED NOT NULL,
 INDEX siteIdx(siteid, date)
);

CREATE TABLE enm_str_msstr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  jvmid TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "REFERENCES enm_str_jvm_names(id)",
  time DATETIME NOT NULL,
  events3 INT UNSIGNED NOT NULL,
  kbytesProcessed3 INT UNSIGNED NOT NULL,
  eventsProcessed INT UNSIGNED NOT NULL,
  createdConnections3 SMALLINT UNSIGNED NOT NULL,
  droppedConnections3 SMALLINT UNSIGNED NOT NULL,
  activeConnections3 SMALLINT UNSIGNED NOT NULL,
  events2 INT UNSIGNED,
  kbytesProcessed2 INT UNSIGNED,
  createdConnections2 SMALLINT UNSIGNED,
  droppedConnections2 SMALLINT UNSIGNED,
  activeConnections2 SMALLINT UNSIGNED,
  INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE kafka_topic_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_kafka_topic (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  topicid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES kafka_topic_names(id)",
  MBytesIn INT UNSIGNED NOT NULL,
  MBytesOut INT UNSIGNED NOT NULL,
  BytesRejected INT UNSIGNED NOT NULL,
  FailedFetchRequests INT UNSIGNED NOT NULL,
  FailedProduceRequests INT UNSIGNED NOT NULL,
  MessagesIn INT UNSIGNED NOT NULL,
  TotalFetchRequests INT UNSIGNED NOT NULL,
  TotalProduceRequests INT UNSIGNED NOT NULL,
  INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_kafka_topic_partitions (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 topicid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES kafka_topic_names(id)",
 partnum TINYINT UNSIGNED NOT NULL,
 logOffset INT UNSIGNED NOT NULL,
 INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_kafka_srv (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 messagesIn INT UNSIGNED NOT NULL,
 requestHandlerAvgIdlePercent TINYINT UNSIGNED NOT NULL,
 networkProcessorAvgIdlePercent TINYINT UNSIGNED NOT NULL,
 INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE kafka_client_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(48) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE kafka_consumer (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 topicid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES kafka_topic_names(id)",
 clientid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES kafka_client_names(id)",
 records_consumed MEDIUMINT UNSIGNED NOT NULL,
 fetch_size MEDIUMINT UNSIGNED,
 INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_str_asrl (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  inputEventCount INT UNSIGNED NOT NULL,
  filteredEvents INT UNSIGNED NOT NULL,
  completeRecords INT UNSIGNED NOT NULL,
  suspectRecords INT UNSIGNED NOT NULL,
  numberOfBearers MEDIUMINT UNSIGNED,
  driverType ENUM('ASRL','ASRN') NOT NULL DEFAULT 'ASRL',
  INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_str_asrl_spark (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 duration SMALLINT UNSIGNED NOT NULL,
 in_partitions TINYINT UNSIGNED NOT NULL,
 in_duration_avg SMALLINT UNSIGNED NOT NULL,
 in_duration_max SMALLINT UNSIGNED NOT NULL,
 in_gc_avg SMALLINT UNSIGNED NOT NULL,
 in_gc_max SMALLINT UNSIGNED NOT NULL,
 in_inputEventCount_sum INT UNSIGNED NOT NULL,
 in_inputEventCount_max MEDIUMINT UNSIGNED,
 in_filteredEvents_sum INT UNSIGNED NOT NULL,
 in_filteredEvents_max MEDIUMINT UNSIGNED,
 in_kafkaReadTime_avg SMALLINT UNSIGNED NOT NULL,
 in_kafkaReadTime_max SMALLINT UNSIGNED,
 proc_partitions TINYINT UNSIGNED NOT NULL,
 proc_duration_avg SMALLINT UNSIGNED NOT NULL,
 proc_duration_max SMALLINT UNSIGNED NOT NULL,
 proc_gc_avg SMALLINT UNSIGNED NOT NULL,
 proc_gc_max SMALLINT UNSIGNED NOT NULL,
 proc_completeRecords_sum INT UNSIGNED NOT NULL,
 proc_completeRecords_max MEDIUMINT UNSIGNED,
 proc_longRunningSessions_sum MEDIUMINT UNSIGNED NOT NULL,
 proc_longRunningSessions_max MEDIUMINT UNSIGNED,
 proc_inMemorySessions_sum MEDIUMINT UNSIGNED NOT NULL,
 proc_inMemorySessions_max MEDIUMINT UNSIGNED,
 proc_suspectRecords_sum MEDIUMINT UNSIGNED NOT NULL,
 proc_suspectRecords_max MEDIUMINT UNSIGNED,
 proc_endTriggeredSuspectSessions_sum MEDIUMINT UNSIGNED NOT NULL,
 proc_endTriggeredSuspectSessions_max MEDIUMINT UNSIGNED,
 proc_inactiveSuspectSessions_sum MEDIUMINT UNSIGNED NOT NULL,
 proc_inactiveSuspectSessions_max MEDIUMINT UNSIGNED,
 proc_sessionDurations_sum INT UNSIGNED NOT NULL,
 proc_sessionDurations_max INT UNSIGNED,
 proc_outputWriteTime_avg SMALLINT UNSIGNED NOT NULL,
 proc_outputWriteTime_max SMALLINT UNSIGNED,
 proc_mapWithStateTime_avg SMALLINT UNSIGNED NOT NULL,
 proc_mapWithStateTime_max SMALLINT UNSIGNED,
 INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cmserv_nhcinstr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  numberOfRequests BIGINT UNSIGNED NOT NULL,
  numberOfResponses BIGINT UNSIGNED NOT NULL,
  INDEX siteCmservNHCIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mscm_nhcinstr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  numberOfRequests BIGINT UNSIGNED NOT NULL,
  INDEX siteMscmNHCIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ebsm_epsid (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  EpsIdText VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(EpsIdText),
  PRIMARY KEY(id)
);

CREATE TABLE enm_ebsm_inst_stats (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 EpsId SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "REFERENCES enm_ebsm_epsid(id)",
 expected_num_input_rop MEDIUMINT UNSIGNED NOT NULL,
 rop_received MEDIUMINT UNSIGNED NOT NULL,
 incomplete_input_rops MEDIUMINT UNSIGNED NOT NULL,
 invalid_events SMALLINT UNSIGNED NOT NULL,
 files_received MEDIUMINT UNSIGNED NOT NULL,
 erroneous_files MEDIUMINT UNSIGNED NOT NULL,
 file_output_time MEDIUMINT UNSIGNED NOT NULL,
 eventsprocessedLTE INT UNSIGNED NOT NULL,
 countersproducedLTE INT UNSIGNED NOT NULL,
 numoffileswrittenLTE INT UNSIGNED NOT NULL,
 eventsprocessedMME INT UNSIGNED,
 countersproducedMME INT UNSIGNED,
 numoffileswrittenMME INT UNSIGNED,
 eventsprocessedNR INT UNSIGNED,
 countersproducedNR INT UNSIGNED,
 numoffileswrittenNR INT UNSIGNED,
 numberOfEventsIgnoredLTE MEDIUMINT UNSIGNED,
 numberOfEventsIgnoredNR MEDIUMINT UNSIGNED,
 indexSizeOfDownlinkNonVoiceThroughputNR MEDIUMINT UNSIGNED,
 indexSizeOfDownlinkVoiceThroughputNR MEDIUMINT UNSIGNED,
 indexSizeOfUplinkThroughputNR MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE site_healthcheck_watchers (
  siteid SMALLINT UNSIGNED NOT NULL,
  watcher VARCHAR(30) NOT NULL,
  UNIQUE INDEX siteWatcherIdx(siteid, watcher)
);

CREATE TABLE enm_spsserv_crlrevokemgt_instr  (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 generateExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 generateMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 generateMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 revokeExecutionTimeTotalMillis BIGINT UNSIGNED NOT NULL,
 revokeMethodFailures MEDIUMINT UNSIGNED NOT NULL,
 revokeMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
 INDEX spsServCrlRevokeInstrIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shmcoreserv_details_logs (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  jobType VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  job_name VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  number_of_network_elements SMALLINT(5) UNSIGNED NOT NULL,
  duration INT UNSIGNED,
  progress_percentage SMALLINT(5) unsigned NOT NULL,
  status VARCHAR(20) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  result VARCHAR(20) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  healthy_nodes_count SMALLINT(5) UNSIGNED,
  category VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_general_cs,
  netypes VARCHAR(64) COLLATE latin1_general_cs,
  t_nejobcreation MEDIUMINT UNSIGNED,
  n_components SMALLINT UNSIGNED,
  configTypeId SMALLINT UNSIGNED COMMENT "REFERENCES nhc_config_types(id)",
  INDEX siteidTimeIdx (siteid, time)
 ) PARTITION BY RANGE ( TO_DAYS(time ))
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shm_axeactivity_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_shm_axeactivity (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_shm_axeactivity_names(id)",
 n_count SMALLINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE enm_saidserv_instr_motypes (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 PRIMARY KEY(id)
);

CREATE TABLE enm_saidserv_function_instr  (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 moid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_saidserv_instr_motypes(id)",
 dpsFunctionCreatedEventCount MEDIUMINT UNSIGNED NOT NULL,
 dpsFunctionAttributeChangedEventCount MEDIUMINT UNSIGNED NOT NULL,
 dpsFunctionDeletedEventCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteSaidServFunctionIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_sutlogs (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  duration VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  INDEX enmSutLogIndex (time,siteid)
 ) PARTITION BY RANGE ( TO_DAYS(time ))
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE bis_active_users_list(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    userName VARCHAR(50) NOT NULL,
    INDEX bisActiveUsersListIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_bis_netan_user_type_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 userType VARCHAR(20) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(userType),
 PRIMARY KEY(id)
);

CREATE TABLE bis_users_list(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    siName VARCHAR(50) NOT NULL,
    siLastLogOnTime DATETIME,
    userTypeId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_bis_netan_user_type_id_mapping(id)",
    INDEX bisUsersListIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE bis_report_instances(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    reportName VARCHAR(250) NOT NULL,
    noOfInstance SMALLINT UNSIGNED NOT NULL,
    INDEX bisReportInstanceIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE bis_report_refresh_time(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    userName VARCHAR(50) NOT NULL,
    reportName VARCHAR(250) NOT NULL,
    reportStatus VARCHAR(100) NOT NULL,
    reportType VARCHAR(50) NOT NULL,
    duration MEDIUMINT UNSIGNED NOT NULL,
    startTime DATETIME NOT NULL,
    cuid VARCHAR(50) NOT NULL,
    INDEX bisReportRefreshTimeIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE bis_report_list(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    reportName VARCHAR(250) NOT NULL,
    reportLastupatedTime DATETIME NOT NULL,
    reportLastRunTime DATETIME,
    INDEX bisReportListIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cmconfig_logs (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    jobId INT UNSIGNED NOT NULL,
    batchstatus VARCHAR(255) NOT NULL,
    startTime DATETIME NOT NULL,
    endTime DATETIME NOT NULL,
    elapsedTime MEDIUMINT UNSIGNED NOT NULL,
    sourceConfig VARCHAR(255) NOT NULL,
    targetConfig VARCHAR(255) NOT NULL,
    expectedNodesCopied MEDIUMINT UNSIGNED NOT NULL,
    nodesCopied MEDIUMINT UNSIGNED NOT NULL,
    nodesNotCopied MEDIUMINT UNSIGNED NOT NULL,
    nodesNoMatchFound MEDIUMINT UNSIGNED NOT NULL,
    INDEX cmServConfigCopyInstrIdx(siteid,startTime)
);

CREATE TABLE enm_cache_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_cache_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 cacheid SMALLINT UNSIGNED COMMENT "REFERENCES enm_cache_names(id)",
 time DATETIME NOT NULL,
 numberOfEntries MEDIUMINT UNSIGNED,
 stores MEDIUMINT UNSIGNED,
 removeHits MEDIUMINT UNSIGNED,
 received_messages MEDIUMINT UNSIGNED,
 sent_messages MEDIUMINT UNSIGNED,
 received_bytes MEDIUMINT UNSIGNED,
 sent_bytes MEDIUMINT UNSIGNED,
 replicationCount SMALLINT UNSIGNED,
 replicationFailures SMALLINT UNSIGNED,
 INDEX siteSrvTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_jboss_threadpools (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 async_completedTaskCount SMALLINT UNSIGNED,
 async_activeCount SMALLINT UNSIGNED,
 async_queueSize SMALLINT UNSIGNED,
 async_rejectedCount SMALLINT UNSIGNED,
 default_completedTaskCount SMALLINT UNSIGNED,
 default_activeCount SMALLINT UNSIGNED,
 default_queueSize SMALLINT UNSIGNED,
 default_rejectedCount SMALLINT UNSIGNED,
 workmanager_long_rejectedCount SMALLINT UNSIGNED,
 workmanager_long_queueSize SMALLINT UNSIGNED,
 workmanager_short_rejectedCount SMALLINT UNSIGNED,
 workmanager_short_queueSize SMALLINT UNSIGNED,
 http_executor_rejectedCount SMALLINT UNSIGNED,
 http_executor_queueSize SMALLINT UNSIGNED,
 ajp_executor_rejectedCount SMALLINT UNSIGNED,
 ajp_executor_queueSize SMALLINT UNSIGNED,
 ajp_executor_currentThreadCount SMALLINT UNSIGNED,
 http_executor_currentThreadCount SMALLINT UNSIGNED,
 workmanager_long_currentThreadCount SMALLINT UNSIGNED,
 workmanager_short_currentThreadCount SMALLINT UNSIGNED,
 job_executor_tp_currentThreadCount SMALLINT UNSIGNED,
 job_executor_tp_queueSize SMALLINT UNSIGNED,
 job_executor_tp_rejectedCount SMALLINT UNSIGNED,
 INDEX siteSrvTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

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

CREATE TABLE enm_raserv_cmp_instr   (
siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
time DATETIME NOT NULL,
enrollmentInvocations MEDIUMINT UNSIGNED NOT NULL,
enrollmentSuccess MEDIUMINT UNSIGNED NOT NULL,
INDEX raServCmpInstrIdx(siteid,time)
);

CREATE TABLE enm_raserv_scep_instr   (
siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
time DATETIME NOT NULL,
enrollmentInvocations MEDIUMINT UNSIGNED NOT NULL,
enrollmentSuccess MEDIUMINT UNSIGNED NOT NULL,
pkcsRequests MEDIUMINT UNSIGNED NOT NULL,
INDEX raServScepInstrIdx(siteid,time)
);

CREATE TABLE enm_raserv_cdps_instr   (
siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
time DATETIME NOT NULL,
publishMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
publishMethodSuccess MEDIUMINT UNSIGNED NOT NULL,
unPublishMethodInvocations MEDIUMINT UNSIGNED NOT NULL,
unPublishMethodSuccess MEDIUMINT UNSIGNED NOT NULL,
INDEX raServCdpsInstrIdx(siteid,time)
);

CREATE TABLE enm_raserv_tdps_instr   (
siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
time DATETIME NOT NULL,
publishFailures MEDIUMINT UNSIGNED NOT NULL,
publishInvocations MEDIUMINT UNSIGNED NOT NULL,
unPublishFailures MEDIUMINT UNSIGNED NOT NULL,
unPublishInvocations MEDIUMINT UNSIGNED NOT NULL,
INDEX raServTdpsInstrIdx(siteid,time)
);

CREATE TABLE enm_ra_tdps_logs (
time DATETIME NOT NULL,
serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
operation_type varchar(50) NOT NULL,
Certificate_Status varchar(50) NOT NULL,
IssuerName varchar(50) NOT NULL,
SerialNo varchar(50) NOT NULL,
timestamp varchar(50) NOT NULL,
INDEX RaServLogTdpsIdx(siteid,time)
);

CREATE TABLE enm_ra_cdps_logs (
time DATETIME NOT NULL,
serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
operation_type varchar(50) NOT NULL,
IssuerName varchar(50) NOT NULL,
SerialNo varchar(50) NOT NULL,
timestamp varchar(50) NOT NULL,
INDEX RaServLogCdpsIdx(siteid,time)
);

CREATE TABLE enm_fmservnetlog_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  numOfDescribeCommands SMALLINT UNSIGNED NOT NULL,
  numOfUploadCommands SMALLINT UNSIGNED NOT NULL,
  numOfStatusCommands SMALLINT UNSIGNED NOT NULL,
  numOfDownloadCommands SMALLINT UNSIGNED NOT NULL,
  numOfDeleteCommands SMALLINT UNSIGNED NOT NULL,
  numOfCollectionStarted SMALLINT UNSIGNED NOT NULL,
  numOfCollectionFailed SMALLINT UNSIGNED NOT NULL,
  numOfReadyForExported SMALLINT UNSIGNED NOT NULL,
  numOfCollectionRescheduled SMALLINT UNSIGNED NOT NULL,
  longestTimeOfUpload BIGINT UNSIGNED NOT NULL,
  shortestTimeOfUpload BIGINT UNSIGNED NOT NULL,
  greatestFileDimension BIGINT UNSIGNED NOT NULL,
  availableDiskSpace BIGINT UNSIGNED NOT NULL,
  totalDiskSpace BIGINT UNSIGNED NOT NULL,
  numOfRetentionTimerRun SMALLINT UNSIGNED NOT NULL,
  numOfObjectInCache BIGINT UNSIGNED NOT NULL,
  bufferedProcessedAlarmsCount MEDIUMINT UNSIGNED,
  INDEX fmservnetlogInstIndex (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_msnetlog_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  numMedTaskRequestReceived SMALLINT UNSIGNED NOT NULL,
  numCollectionStarted SMALLINT UNSIGNED NOT NULL,
  executionTime BIGINT UNSIGNED NOT NULL,
  INDEX msnetlogInstIndex (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_netexserv_topologysearch_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    objectsTotalResponseTime BIGINT UNSIGNED NOT NULL,
    totalSearchTime BIGINT UNSIGNED NOT NULL,
    totalCmTime BIGINT UNSIGNED NOT NULL,
    mergeQueryResultsTotalTime BIGINT UNSIGNED NOT NULL,
    INDEX netexServTopologySearchSiteTimeIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_netexserv_topologycollection_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    collectionTotalTime BIGINT UNSIGNED NOT NULL,
    collectionDatabaseTotalTime BIGINT UNSIGNED NOT NULL,
    collectionWithContentsTotalTime BIGINT UNSIGNED NOT NULL,
    collectionWithContentsDatabaseTotalTime BIGINT UNSIGNED NOT NULL,
    createCollectionTotalTime BIGINT UNSIGNED NOT NULL,
    createCollectionDatabaseTotalTime BIGINT UNSIGNED NOT NULL,
    updateCollectionTotalTime BIGINT UNSIGNED NOT NULL,
    updateCollectionDatabaseTotalTime BIGINT UNSIGNED NOT NULL,
    deleteCollectionTotalTime BIGINT UNSIGNED NOT NULL,
    deleteCollectionDatabaseTotalTime BIGINT UNSIGNED NOT NULL,
    collectionBatchesTotalTime BIGINT UNSIGNED NOT NULL,
    savedSearchesTotalTime BIGINT UNSIGNED NOT NULL,
    savedSearchesDatabaseTotalTime BIGINT UNSIGNED NOT NULL,
    createSavedSearchTotalTime BIGINT UNSIGNED NOT NULL,
    createSavedSearchDatabaseTotalTime BIGINT UNSIGNED NOT NULL,
    deleteSavedSearchTotalTime BIGINT UNSIGNED NOT NULL,
    deleteSavedSearchDatabaseTotalTime BIGINT UNSIGNED NOT NULL,
    publicSavedSearchesCount BIGINT UNSIGNED NOT NULL,
    privateSavedSearchesCount BIGINT UNSIGNED NOT NULL,
    autoGeneratedCollectionsCount BIGINT UNSIGNED NOT NULL,
    publicCollectionsCount BIGINT UNSIGNED NOT NULL,
    privateCollectionsCount BIGINT UNSIGNED NOT NULL,
    createCollectionDatabaseLargestSize BIGINT UNSIGNED NOT NULL,
    createCollectionDatabaseAverageSize BIGINT UNSIGNED NOT NULL,
    createCollectionDatabaseMedianSize BIGINT UNSIGNED NOT NULL,
    createCollectionDatabaseTotalObjectCount BIGINT UNSIGNED NOT NULL,
    labelsCount SMALLINT UNSIGNED,
    INDEX netexServTopologyCollectionSiteTimeIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_netex_queries (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 count SMALLINT NOT NULL,
 results MEDIUMINT NOT NULL,
 duration MEDIUMINT NOT NULL,
 query VARCHAR(512) NOT NULL COLLATE latin1_general_cs,
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE versant_client (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    kbReceived MEDIUMINT UNSIGNED NOT NULL,
    kbSent MEDIUMINT UNSIGNED NOT NULL,
    objectsReceived MEDIUMINT UNSIGNED NOT NULL,
    objectsSent MEDIUMINT UNSIGNED NOT NULL,
    rpcCount MEDIUMINT UNSIGNED NOT NULL,
    INDEX(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_upgrade_type_detail (
 date DATE NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 upgradeType VARCHAR(30) NOT NULL,
 INDEX upgradeTypeIdx(siteId, date)
);

CREATE TABLE fls_role_type_id_mapping (
 id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 roleType VARCHAR(8) NOT NULL
);

CREATE TABLE fls_server_name_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 serverName VARCHAR(30) NOT NULL
);

CREATE TABLE eniq_fls_master_slave_details (
 time DATETIME NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 roleId TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES fls_role_type_id_mapping(id)",
 serverId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES fls_server_name_id_mapping(id)",
 INDEX flsMasterSlaveIdx(siteId, time)
);

CREATE TABLE eniq_stats_workflow_types (
 workflow_type_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 workflow_type VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX workflowTypeIdx (workflow_type),
 PRIMARY KEY(workflow_type_id)
);

CREATE TABLE enm_cmserv_cmreader_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  allDeployedNetypesVisits SMALLINT UNSIGNED NOT NULL,
  descriptionsForNetypeVisits SMALLINT UNSIGNED NOT NULL,
  descriptionsWithListOfOutputSpecificationsVisits SMALLINT UNSIGNED NOT NULL,
  moByFdnVisits SMALLINT UNSIGNED NOT NULL,
  posByPoIdsVisits SMALLINT UNSIGNED NOT NULL,
  searchWithListOfOutputSpecificationsVisits MEDIUMINT UNSIGNED NOT NULL,
  searchWithListOfOutputSpecificationsTotalExecutionTime SMALLINT UNSIGNED,
  getCommandProcessorTotalExecutionTime SMALLINT UNSIGNED,
  getCommandProcessorVisits SMALLINT UNSIGNED,
  sendBackToCmEditorRequestQueueVisits SMALLINT UNSIGNED,
  INDEX cmreader(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cmserv_cmwriter_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  createManagedObjectVisits SMALLINT UNSIGNED NOT NULL,
  createMibRootVisits SMALLINT UNSIGNED NOT NULL,
  createPersistenceObjectVisits SMALLINT UNSIGNED NOT NULL,
  performActionVisits SMALLINT UNSIGNED NOT NULL,
  performBatchActionVisits SMALLINT UNSIGNED NOT NULL,
  setManagedObjectAttributesVisits SMALLINT UNSIGNED NOT NULL,
  setManagedObjectsAttributesBatchVisits SMALLINT UNSIGNED NOT NULL,
  deleteCmObjectsBatchVisits SMALLINT UNSIGNED NOT NULL,
  cmWriterhandleSetRequestVisits SMALLINT UNSIGNED,
  cmWriterhandleSetRequestTotalExecutionTime SMALLINT UNSIGNED,
  sendBackToCmEditorRequestQueueVisits SMALLINT UNSIGNED,
  INDEX cmwriter(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cmserv_cmsearchreader_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  cmContainmentQueryTotalQueryResultCount BIGINT(20) UNSIGNED NOT NULL,
  cmContainmentQueryVisits BIGINT(20) UNSIGNED NOT NULL,
  cmFdnQueryTotalQueryResultCount BIGINT(20) UNSIGNED NOT NULL,
  cmFdnQueryVisits BIGINT(20) UNSIGNED NOT NULL,
  cmParentChildQueryTotalQueryResultCount BIGINT(20) UNSIGNED NOT NULL,
  cmParentChildQueryVisits BIGINT(20) UNSIGNED NOT NULL,
  cmPoQueryTotalQueryResultCount BIGINT(20) UNSIGNED NOT NULL,
  cmPoQueryVisits BIGINT(20) UNSIGNED NOT NULL,
  cmTypeQueryTotalQueryResultCount BIGINT(20) UNSIGNED NOT NULL,
  cmTypeQueryVisits BIGINT(20) UNSIGNED NOT NULL,
  compositeCmQueryTotalQueryResultCount BIGINT(20) UNSIGNED NOT NULL,
  compositeCmQueryVisits BIGINT(20) UNSIGNED NOT NULL,
  fastCmTypeQueryTotalQueryResultCount BIGINT(20) UNSIGNED NOT NULL,
  fastCmTypeQueryVisits BIGINT(20) UNSIGNED NOT NULL,
  cmContainmentQueryTotalExecutionTime SMALLINT UNSIGNED,
  cmFDNQueryTotalExecutionTime SMALLINT UNSIGNED,
  cmParentChildQueryTotalExecutionTime SMALLINT UNSIGNED,
  cmPoQueryTotalExecutionTime SMALLINT UNSIGNED,
  cmTypeQueryTotalExecutionTime SMALLINT UNSIGNED,
  compositeCmQueryTotalExecutionTime SMALLINT UNSIGNED,
  fastCmTypeQueryTotalExecutionTime SMALLINT UNSIGNED,
  INDEX cmSearchReader(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE enm_amos_generalscripting_sessionsinstr (
   siteid   SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
   serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
   time     DATETIME NOT NULL,
   numCurrentSessions INT UNSIGNED NOT NULL,
   cpuUsed INT UNSIGNED,
   memoryUsed INT UNSIGNED,
   processes SMALLINT UNSIGNED,
   INDEX amosSessionsInstridx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE fls_file_symlink_nodeType_id_mapping (
 id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 nodeType VARCHAR(20) NOT NULL
);

CREATE TABLE eniq_stats_fls_file_details (
 time DATETIME NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 nodeId TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES fls_file_symlink_nodeType_id_mapping(id)",
 timeTaken SMALLINT UNSIGNED NOT NULL,
 fileCount INT UNSIGNED NOT NULL,
 INDEX flsFileIdx(siteId, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_fls_symlink_details (
 time DATETIME NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 nodeId TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES fls_file_symlink_nodeType_id_mapping(id)",
 fileType VARCHAR(15) NOT NULL,
 timeTaken SMALLINT UNSIGNED NOT NULL,
 INDEX flsSymlinkIdx(siteId, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE frh_controller_backlog
(
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  interfaceId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES frh_interface(id)",
  totalBacklog MEDIUMINT UNSIGNED,
  filesInBacklog MEDIUMINT UNSIGNED,
  filesInProcess MEDIUMINT UNSIGNED,
  processingTime MEDIUMINT UNSIGNED,
  INDEX frhControllerBacklogIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE frh_interface
(
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  frhInterface VARCHAR(1024) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE enm_solr_core_failures (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATE DEFAULT NULL,
  core VARCHAR(20) DEFAULT NULL,
  reason VARCHAR(200) DEFAULT NULL,
  INDEX solrCoreFailuresIndex(siteid,time)
);

CREATE TABLE enm_bur_backup_stage_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    backup_stage_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_stage_names(id)",
    backup_stage_status_id TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_stage_statuses(id)",
    duration MEDIUMINT UNSIGNED DEFAULT NULL,
    backup_keyword_id MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_keywords(id)",
    INDEX siteTimeIdx(siteid, start_time)
);

CREATE TABLE enm_pmserv_uplink_instr (
   siteid   SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
   serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
   time     DATETIME NOT NULL,
   averageCollectionDuration BIGINT UNSIGNED NOT NULL,
   collectionDuration BIGINT UNSIGNED NOT NULL,
   maximumCollectionDuration BIGINT UNSIGNED NOT NULL,
   minimumCollectionDuration BIGINT UNSIGNED NOT NULL,
   numberOfFileTransferNotifications BIGINT UNSIGNED NOT NULL,
   numberOfFilesCollected BIGINT UNSIGNED NOT NULL,
   numberOfFilesFailed BIGINT UNSIGNED NOT NULL,
   numberOfFilesRecovered MEDIUMINT UNSIGNED,
   numberOfCliStartSnapshotRequests MEDIUMINT UNSIGNED,
   numberOfCliStartSnapshotFailedRequests MEDIUMINT UNSIGNED,
   numberOfCliStartContinuousRequests MEDIUMINT UNSIGNED,
   numberOfCcliStartContinuousFailedRequests MEDIUMINT UNSIGNED,
   numberOfCliStartConditionalRequests MEDIUMINT UNSIGNED,
   numberOfCliStartConditionalFailedRequests MEDIUMINT UNSIGNED,
   numberOfCliStartScheduledRequests MEDIUMINT UNSIGNED,
   numberOfCliStartScheduledFailedRequests MEDIUMINT UNSIGNED,
   numberOfRestStartSnapshotRequests MEDIUMINT UNSIGNED,
   numberOfRestStartSnapshotFailedRequests MEDIUMINT UNSIGNED,
   numberOfRestStartContinuousRequests MEDIUMINT UNSIGNED,
   numberOfRestStartContinuousFailedRequests MEDIUMINT UNSIGNED,
   numberOfRestStartScheduledRequests MEDIUMINT UNSIGNED,
   numberOfRestStartScheduledFailedRequests MEDIUMINT UNSIGNED,
   numberOfRestStartConditionalRequests MEDIUMINT UNSIGNED,
   numberOfRestStartConditionalFailedRequests MEDIUMINT UNSIGNED,
   numberOfScheduledStartSnapshotRequests MEDIUMINT UNSIGNED,
   numberOfScheduledStartSnapshotFailedRequests MEDIUMINT UNSIGNED,
   numberOfScheduledStartSnapshotDiscardedRequests MEDIUMINT UNSIGNED,
   numberOfScheduledStartContinuousDiscardedRequests MEDIUMINT UNSIGNED,
   numberOfScheduledStartContinuousRequests MEDIUMINT UNSIGNED,
   numberOfScheduledStartContinuousFailedRequests MEDIUMINT UNSIGNED,
   averageStartCommandDuration MEDIUMINT UNSIGNED,
   maximumStartCommandDuration MEDIUMINT UNSIGNED,
   minimumStartCommandDuration MEDIUMINT UNSIGNED,
   INDEX pmUplinkInstrIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE pm_uplink_errored_res (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 resourceName VARCHAR(255) NOT NULL,
 resCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteIdIdx(siteid,date)
);

CREATE TABLE pm_uplink_errors (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 errorMsg VARCHAR(255) NOT NULL,
 errorCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteIdIdx(siteid,date)
);

CREATE TABLE enm_shmcoreserv_jobexecution_logs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 activity VARCHAR(30) NOT NULL,
 flow VARCHAR(50) NOT NULL,
 INDEX enm_shmcoreservIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shmmodeling_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    cacheMisses INT UNSIGNED NOT NULL,
    cacheRequests INT UNSIGNED NOT NULL,
    cacheSize INT UNSIGNED NOT NULL,
    modelCount INT UNSIGNED NOT NULL,
    cacheDescriptionTexts SMALLINT UNSIGNED,
    maxIdleTimeInCache MEDIUMINT,
    maxCacheSize MEDIUMINT UNSIGNED,
    cacheEvictions SMALLINT UNSIGNED,
    readWriteRatio SMALLINT UNSIGNED,
    INDEX shmModelingInstrSiteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE kpiserv_reststatistics_instr (
   siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
   serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
   time DATETIME NOT NULL,
   NetworkScopeServiceBeanexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   NetworkScopeServiceBeanmethodInvocations MEDIUMINT UNSIGNED,
   AsyncNetworkScopeManagerBeanexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   AsyncNetworkScopeManagerBeanmethodInvocations MEDIUMINT UNSIGNED,
   getNeTypeOpStateCountmethodInvocations MEDIUMINT UNSIGNED,
   processNeStateChangemethodInvocations MEDIUMINT UNSIGNED,
   processStateChangeEventmethodInvocations MEDIUMINT UNSIGNED,
   getWorstPerformersmethodInvocations MEDIUMINT UNSIGNED,
   getKpiAndNetworkDataexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   getKpiAndNetworkDatamethodInvocations MEDIUMINT UNSIGNED,
   getNeStateDataInScopeexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   getNeStateDataInScopemethodInvocations MEDIUMINT UNSIGNED,
   getStateexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   getStatemethodInvocations MEDIUMINT UNSIGNED,
   getCellStatusDataexecutionTimeMillis MEDIUMINT UNSIGNED,
   getCellStatusDatamethodInvocations SMALLINT UNSIGNED,
   getKpiViewerDataexecutionTimeMillis MEDIUMINT UNSIGNED,
   getKpiViewerDatamethodInvocations SMALLINT UNSIGNED,
   getNeTypeOpStateCountexecutionTimeMillis MEDIUMINT UNSIGNED,
   processNeStateChangeexecutionTimeMillis MEDIUMINT UNSIGNED,
   processStateChangeEventexecutionTimeMillis MEDIUMINT UNSIGNED,
   getWorstPerformersexecutionTimeMillis MEDIUMINT UNSIGNED,
   getKpiBreachSummaryexecutionTimeMillis MEDIUMINT UNSIGNED,
   getKpiBreachSummarymethodInvocations SMALLINT UNSIGNED,
   getfetchKpiValuesexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   getfetchKpiValuesmethodInvocations SMALLINT UNSIGNED,
   getfetchHistoricalKpiValuesexecutionTimeTotalMillis MEDIUMINT UNSIGNED,
   getfetchHistoricalKpiValuesmethodInvocations SMALLINT UNSIGNED,
   getActivationStatusresponseTime MEDIUMINT UNSIGNED,
   getActivationStatustotalRequestReceived SMALLINT UNSIGNED,
   getKpiInstanceCapabilitiesresponseTime MEDIUMINT UNSIGNED,
   getKpiInstanceCapabilitiestotalRequestReceived SMALLINT UNSIGNED,
   getActivateOrDeactivateKpiresponseTime MEDIUMINT UNSIGNED,
   getActivateOrDeactivateKpitotalRequestReceived SMALLINT UNSIGNED,
   getDeleteKpiresponseTime MEDIUMINT UNSIGNED,
   getDeleteKpitotalRequestReceived SMALLINT UNSIGNED,
   getListKpiresponseTime MEDIUMINT UNSIGNED,
   getListKpitotalRequestReceived SMALLINT UNSIGNED,
   getCreateKpiresponseTime MEDIUMINT UNSIGNED,
   getCreateKpitotalRequestReceived SMALLINT UNSIGNED,
   getReadKpiDefinitionresponseTime MEDIUMINT UNSIGNED,
   getReadKpiDefinitiontotalRequestReceived SMALLINT UNSIGNED,
   getUpdateKpiresponseTime MEDIUMINT UNSIGNED,
   getUpdateKpitotalRequestReceived SMALLINT UNSIGNED,
   INDEX kpiservRestQueryInstr(siteid,time)
)  PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ilo_logs (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  date DATE NOT NULL,
  blade varchar(255) DEFAULT NULL,
  sys_health_status varchar(255) DEFAULT NULL,
  blade_power_setting varchar(255) DEFAULT NULL,
  INDEX siteDateIdx(siteid,date)
);

CREATE TABLE enm_ap_order_project_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    projectid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ap_project_names(id)",
    validate_project_time INT UNSIGNED DEFAULT NULL,
    create_project_mo_time INT UNSIGNED DEFAULT NULL,
    create_and_write_project_artifacts_time INT UNSIGNED DEFAULT NULL,
    INDEX siteTimeIdx(siteid, time)
);

CREATE TABLE enm_ap_order_node_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    projectid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ap_project_names(id)",
    neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
    create_node_mo INT UNSIGNED DEFAULT NULL,
    create_node_children_mos INT UNSIGNED DEFAULT NULL,
    setup_configuration INT UNSIGNED DEFAULT NULL,
    add_node INT UNSIGNED DEFAULT NULL,
    generate_security INT UNSIGNED DEFAULT NULL,
    create_file_artifact INT UNSIGNED DEFAULT NULL,
    create_node_user_credentials INT UNSIGNED DEFAULT NULL,
    bind_during_order INT UNSIGNED DEFAULT NULL,
    INDEX siteTimeIdx(siteid, time)
);

CREATE TABLE enm_ap_integrate_node_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    projectid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ap_project_names(id)",
    neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
    initiate_sync_node INT UNSIGNED DEFAULT NULL,
    import_configurations INT UNSIGNED DEFAULT NULL,
    enable_supervision INT UNSIGNED DEFAULT NULL,
    create_cv INT UNSIGNED DEFAULT NULL,
    create_backup INT UNSIGNED DEFAULT NULL,
    activate_optional_features INT UNSIGNED DEFAULT NULL,
    gps_position_check INT UNSIGNED DEFAULT NULL,
    unlock_cells INT UNSIGNED DEFAULT NULL,
    upload_cv INT UNSIGNED DEFAULT NULL,
    upload_backup INT UNSIGNED DEFAULT NULL,
    INDEX siteTimeIdx(siteid, time)
);

CREATE TABLE enm_ap_delete_node_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    projectid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ap_project_names(id)",
    neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
    remove_node INT UNSIGNED DEFAULT NULL,
    cancel_security INT UNSIGNED DEFAULT NULL,
    remove_backup INT UNSIGNED DEFAULT NULL,
    delete_raw_and_generated_node_artifacts INT UNSIGNED DEFAULT NULL,
    delete_node_mo INT UNSIGNED DEFAULT NULL,
    INDEX siteTimeIdx(siteid, time)
);

CREATE TABLE enm_ap_delete_project_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    projectid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ap_project_names(id)",
    delete_raw_and_generated_project_artifacts INT UNSIGNED DEFAULT NULL,
    delete_project_mo INT UNSIGNED DEFAULT NULL,
    INDEX siteTimeIdx(siteid, time)
);

CREATE TABLE enm_ap_project_names (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(name),
    PRIMARY KEY(id)
);

CREATE TABLE eniq_sap_iq_version_patch_details (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 version VARCHAR(8) NOT NULL,
 patch VARCHAR(12) NOT NULL
);

CREATE TABLE enm_cmconfig_services_logs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 product_version VARCHAR(100),
 netype VARCHAR(40),
 duration MEDIUMINT UNSIGNED,
 model_id VARCHAR(40),
 model_status VARCHAR(50),
 model_size VARCHAR(50),
INDEX enm_cmservIdx(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cmconfig_support_logs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 activity VARCHAR(40),
 product_version VARCHAR(100),
 netype VARCHAR(40),
 result VARCHAR(40),
 numberOfNodes INT UNSIGNED,
 modelIdentity VARCHAR(40),
 duration SMALLINT UNSIGNED DEFAULT NULL,
INDEX enm_cmservSupportIdx(siteid,time)
)
PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_versant_health_checks_ldtx (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    deadtxcount INT UNSIGNED NOT NULL,
    longrunningtxcount INT UNSIGNED NOT NULL,
    INDEX versantldtxSiteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mscmce_notiftop
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
    count MEDIUMINT UNSIGNED NOT NULL,
    servicegroup ENUM( 'comecimmscm', 'mscmapg' ) NOT NULL DEFAULT 'comecimmscm' COLLATE latin1_general_cs,
    INDEX siteTimeIdx(siteid, date)
);

CREATE TABLE enm_mscmce_notifrec
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    eventtype ENUM( 'AVC', 'CREATE', 'DELETE', 'SDN', 'SEQUENCE_DELTA', 'UPDATE' ) NOT NULL COLLATE latin1_general_cs,
    moid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
    attribid SMALLINT UNSIGNED COMMENT "REFERENCES enm_mscm_attrib_names(id)",
    count INT UNSIGNED NOT NULL,
    servicegroup ENUM( 'comecimmscm', 'mscmapg' ) NOT NULL DEFAULT 'comecimmscm' COLLATE latin1_general_cs,
    INDEX siteTimeIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_neo4j_srv (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 cacheKbRead MEDIUMINT UNSIGNED,
 cacheKbWritten MEDIUMINT UNSIGNED,
 cacheEvictionExceptions SMALLINT UNSIGNED NOT NULL,
 cacheEvictions MEDIUMINT UNSIGNED NOT NULL,
 cacheFaults MEDIUMINT UNSIGNED NOT NULL,
 cacheFlushes MEDIUMINT UNSIGNED NOT NULL,
 cachePins INT UNSIGNED NOT NULL,
 transCommitted MEDIUMINT UNSIGNED NOT NULL,
 transOpen SMALLINT UNSIGNED NOT NULL,
 transOpened MEDIUMINT UNSIGNED NOT NULL,
 transRolledBack MEDIUMINT UNSIGNED NOT NULL,
 transLastCommitted BIGINT UNSIGNED,
 boltProcTime SMALLINT UNSIGNED,
 boltQTime SMALLINT UNSIGNED,
 boltConnOpened SMALLINT UNSIGNED,
 boltConnClosed SMALLINT UNSIGNED,
 boltConnRunning SMALLINT UNSIGNED,
 boltConnIdle SMALLINT UNSIGNED,
 boltMsgRecv MEDIUMINT UNSIGNED,
 boltMsgStarted MEDIUMINT UNSIGNED,
 boltMsgDone MEDIUMINT UNSIGNED,
 boltMsgFailed SMALLINT UNSIGNED,
 clustAppendIndex BIGINT UNSIGNED,
 clustAppliedIndex BIGINT UNSIGNED,
 clustCommitIndex BIGINT UNSIGNED,
 clustMsgProcDelay SMALLINT UNSIGNED,
 INDEX siteTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_neo4j_srv_lr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 propIds INT UNSIGNED NOT NULL,
 nodeIds INT UNSIGNED NOT NULL,
 relIds INT UNSIGNED NOT NULL,
 relTypeIds INT UNSIGNED,
 logMB INT UNSIGNED,
 totalMB INT UNSIGNED,
 INDEX siteTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_neo4j_chkpnts (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 start DATETIME NOT NULL,
 end DATETIME NOT NULL,
 type ENUM ( 'TIME', 'TX', 'STORE_COPY', 'DB_SHUTDOWN', 'FORCE_CHKPNT', 'UNKNOWN' ) NOT NULL,
 txid BIGINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid, start)
) PARTITION BY RANGE ( TO_DAYS(start) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_neo4j_raftevents (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 seqno TINYINT UNSIGNED NOT NULL,
 leaderid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 type ENUM ( 'START_ELECTION', 'CHANGE_ROLE_LEADER', 'CHANGE_ROLE_CANDIDATE', 'CHANGE_ROLE_FOLLOWER' ) NOT NULL,
 changeleader BOOLEAN NOT NULL,
 dbname ENUM ('dps', 'system', 'graph.db') COLLATE latin1_general_cs,
 INDEX siteTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_neo4j_mocounts (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 namespaceid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES model_names(id)",
 motypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
 total INT UNSIGNED NOT NULL,
 nonlive MEDIUMINT UNSIGNED NOT NULL,
 INDEX idxSiteDate(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_sap_iq_large_memory_details (
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 totalMemory BIGINT UNSIGNED NOT NULL,
 flexiblePercentage MEDIUMINT UNSIGNED NOT NULL,
 flexibleUsed MEDIUMINT UNSIGNED NOT NULL,
 inflexiblePercentage MEDIUMINT UNSIGNED NOT NULL,
 inflexibleUsed MEDIUMINT UNSIGNED NOT NULL,
 antiStarvationPercentage MEDIUMINT UNSIGNED NOT NULL,
 INDEX eniq_sap_iq_large_memory_detailsIdx(siteId,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_bur_backup_throughput_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    backup_keyword_id MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_keywords(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    throughput_mb_per_sec FLOAT(13,4) UNSIGNED DEFAULT NULL,
    backup_mount_point_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_mount_points(id)",
    filesystem_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_filesystems(id)",
    filesystem_used_size INT UNSIGNED NOT NULL,
    filesystem_size INT UNSIGNED NOT NULL,
    INDEX siteDateIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_bur_backup_keywords (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    backup_keyword VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(backup_keyword),
    PRIMARY KEY(id)
);

CREATE TABLE enm_bur_backup_mount_points (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    backup_mount_point VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(backup_mount_point),
    PRIMARY KEY(id)
);

CREATE TABLE enm_bur_filesystems (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fs_name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(fs_name),
    PRIMARY KEY(id)
);

CREATE TABLE enm_bur_backup_stage_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    backup_stage_name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(backup_stage_name),
    PRIMARY KEY(id)
);

CREATE TABLE enm_bur_backup_stage_statuses (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    backup_stage_status_name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(backup_stage_status_name),
    PRIMARY KEY(id)
);

CREATE TABLE enm_dead_mscms (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    DeadMscms INT UNSIGNED NOT NULL,
    INDEX siteInstTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mscmcenotification_logs (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    starttime DATETIME NOT NULL,
    endtime DATETIME NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    totalnotificationsreceived MEDIUMINT UNSIGNED DEFAULT NULL,
    totalnotificationsprocessed MEDIUMINT UNSIGNED DEFAULT NULL,
    totalnotificationsdiscarded MEDIUMINT UNSIGNED DEFAULT NULL,
    leadtimemax INT UNSIGNED DEFAULT NULL,
    leadtimeavg INT UNSIGNED DEFAULT NULL,
    validationhandlertimemax INT UNSIGNED DEFAULT NULL,
    validationhandlertimeavg INT UNSIGNED DEFAULT NULL,
    writehandlertimemax INT UNSIGNED DEFAULT NULL,
    writehandlertimeavg INT UNSIGNED DEFAULT NULL,
    INDEX mscmceServNotifIdx(siteid, endtime)
) PARTITION BY RANGE ( TO_DAYS(endtime) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_neo4j_leader (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    init BOOLEAN NOT NULL,
    dbname ENUM ('dps', 'system', 'graph.db') COLLATE latin1_general_cs,
    INDEX siteTimeIdx(siteid, time)
);

CREATE TABLE enm_com_ecim_delta_syncs (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    starttime DATETIME NOT NULL,
    endtime DATETIME NOT NULL,
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
    n_mo_created MEDIUMINT UNSIGNED DEFAULT NULL,
    n_mo_deleted MEDIUMINT UNSIGNED DEFAULT NULL,
    n_mo_updated MEDIUMINT UNSIGNED DEFAULT NULL,
    INDEX siteTimeIdx(siteid, endtime)
) PARTITION BY RANGE ( TO_DAYS(endtime) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_parsing_duration (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 source VARCHAR(100),
 parsingEndTime DATETIME NOT NULL,
 duration SMALLINT UNSIGNED,
 ropTime DATETIME NOT NULL,
 INDEX parsingdurationadaptor(siteid, ropTime)
) PARTITION BY RANGE ( TO_DAYS(ropTime) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mscmipnotification_logs (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    starttime DATETIME NOT NULL,
    endtime DATETIME NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    totalnotificationsreceived MEDIUMINT UNSIGNED DEFAULT NULL,
    totalnotificationsprocessed MEDIUMINT UNSIGNED DEFAULT NULL,
    totalnotificationsdiscarded MEDIUMINT UNSIGNED DEFAULT NULL,
    leadtimemax INT UNSIGNED DEFAULT NULL,
    leadtimeavg INT UNSIGNED DEFAULT NULL,
    validationhandlertimemax INT UNSIGNED DEFAULT NULL,
    validationhandlertimeavg INT UNSIGNED DEFAULT NULL,
    writehandlertimemax INT UNSIGNED DEFAULT NULL,
    writehandlertimeavg INT UNSIGNED DEFAULT NULL,
    INDEX mscmipServNotifIdx (siteid, endtime)
);

CREATE TABLE enm_mspmip_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  snmpGetNumberOperation INT UNSIGNED NOT NULL,
  snmpGetDurationTime BIGINT UNSIGNED NOT NULL,
  snmpGetSizeResponseMessage BIGINT UNSIGNED NOT NULL,
  fileGeneratedNumber INT UNSIGNED NOT NULL,
  fileGeneratedTime BIGINT UNSIGNED NOT NULL,
  fileGeneratedSize BIGINT UNSIGNED NOT NULL,
  noOfContinuousFiles15min SMALLINT UNSIGNED,
  noOfHistoricalFiles15min SMALLINT UNSIGNED,
  noOfRecoveredHistoricalFiles15min SMALLINT UNSIGNED,
  minCounterValues15min SMALLINT UNSIGNED,
  avgCounterValues15min SMALLINT UNSIGNED,
  maxCounterValues15min SMALLINT UNSIGNED,
  minCollectionHandlerTime15min SMALLINT UNSIGNED,
  maxCollectionHandlerTime15min MEDIUMINT UNSIGNED,
  minCounterCollectionTime15min MEDIUMINT UNSIGNED,
  maxCounterCollectionTime15min MEDIUMINT UNSIGNED,
  minCreationHandlerTime15min MEDIUMINT UNSIGNED,
  maxCreationHandlerTime15min MEDIUMINT UNSIGNED,
  noOfContinuousFiles24h SMALLINT UNSIGNED,
  noOfHistoricalFiles24h SMALLINT UNSIGNED,
  noOfRecoveredHistoricalFiles24h SMALLINT UNSIGNED,
  minCounterValues24h SMALLINT UNSIGNED,
  avgCounterValues24h SMALLINT UNSIGNED,
  maxCounterValues24h SMALLINT UNSIGNED,
  minCollectionHandlerTime24h MEDIUMINT UNSIGNED,
  maxCollectionHandlerTime24h MEDIUMINT UNSIGNED,
  minCounterCollectionTime24h MEDIUMINT UNSIGNED,
  maxCounterCollectionTime24h MEDIUMINT UNSIGNED,
  minCreationHandlerTime24h MEDIUMINT UNSIGNED,
  maxCreationHandlerTime24h MEDIUMINT UNSIGNED,
  noOfCollectedEthernetFiles15min SMALLINT UNSIGNED,
  noOfRecoveredEthernetFiles15min SMALLINT UNSIGNED,
  minProcessingHandlerTime15min MEDIUMINT UNSIGNED,
  maxProcessingHandlerTime15min MEDIUMINT UNSIGNED,
  noOfCollectedEthernetFiles24h SMALLINT UNSIGNED,
  noOfRecoveredEthernetFiles24h SMALLINT UNSIGNED,
  minProcessingHandlerTime24h MEDIUMINT UNSIGNED,
  maxProcessingHandlerTime24h MEDIUMINT UNSIGNED,
  noOfCollectedSOAMFiles15min SMALLINT UNSIGNED,
  noOfRecoveredSOAMFiles15min SMALLINT UNSIGNED,
  SOAMminProcessingHandlerTime15min MEDIUMINT UNSIGNED,
  SOAMmaxProcessingHandlerTime15min MEDIUMINT UNSIGNED,
  noOfCollectedSOAMFiles24h SMALLINT UNSIGNED,
  noOfRecoveredSOAMFiles24h SMALLINT UNSIGNED,
  SOAMminProcessingHandlerTime24h MEDIUMINT UNSIGNED,
  SOAMmaxProcessingHandlerTime24h MEDIUMINT UNSIGNED,
  noOfMlOutdoorFiles15min MEDIUMINT UNSIGNED,
  noOfMlOutdoorRecoveredFiles15min MEDIUMINT UNSIGNED,
  minProcessingHandlerTimeMlOutdoor15min SMALLINT UNSIGNED,
  maxProcessingHandlerTimeMlOutdoor15min MEDIUMINT UNSIGNED,
  maxuploadHandlerTimeMlOutdoor15min MEDIUMINT UNSIGNED,
  minuploadHandlerTimeMlOutdoor15min SMALLINT UNSIGNED,
  noOfMlOutdoorFiles24h MEDIUMINT UNSIGNED,
  noOfMlOutdoorRecoveredFiles24h MEDIUMINT UNSIGNED,
  minProcessingHandlerTimeMlOutdoor24h SMALLINT UNSIGNED,
  maxuploadHandlerTimeMlOutdoor24h MEDIUMINT UNSIGNED,
  minuploadHandlerTimeMlOutdoor24h SMALLINT UNSIGNED,
  maxProcessingHandlerTimeMlOutdoor24h MEDIUMINT UNSIGNED,
  noOfSnmpPingFailures15min SMALLINT UNSIGNED,
  noOfInterfacePopulationFailures15min SMALLINT UNSIGNED,
  noOfZeroCounterFiles15min SMALLINT UNSIGNED,
  noOfErrorsInFiles15min MEDIUMINT UNSIGNED,
  noOfSnmpPingFailures24h SMALLINT UNSIGNED,
  noOfInterfacePopulationFailures24h SMALLINT UNSIGNED,
  noOfZeroCounterFiles24h SMALLINT UNSIGNED,
  noOfErrorsInFiles24h MEDIUMINT UNSIGNED,
  numberOfUploadRequestFailuresEthernet15m SMALLINT UNSIGNED,
  numberOfSuccessfulRequestsEthernet15m SMALLINT UNSIGNED,
  numberOfProcessingFlowFailuresEthernet15m MEDIUMINT UNSIGNED,
  numberOfSuccessfulRecoveryRequestsEthernet15m MEDIUMINT UNSIGNED,
  numberOfFailedRecoveryRequestsEthernet15m MEDIUMINT UNSIGNED,
  numberOfUploadRequestFailuresSoam15m SMALLINT UNSIGNED,
  numberOfSuccessfulRequestsSoam15m SMALLINT UNSIGNED,
  numberOfProcessingFlowFailuresSoam15m MEDIUMINT UNSIGNED,
  numberOfSuccessfulRecoveryRequestsSoam15m MEDIUMINT UNSIGNED,
  numberOfFailedRecoveryRequestsSoam15m MEDIUMINT UNSIGNED,
  numberOfUploadRequestFailuresEthernet24h SMALLINT UNSIGNED,
  numberOfSuccessfulRequestsEthernet24h SMALLINT UNSIGNED,
  numberOfProcessingFlowFailuresEthernet24h SMALLINT UNSIGNED,
  numberOfUploadRequestFailuresSoam24h SMALLINT UNSIGNED,
  numberOfSuccessfulRequestsSoam24h SMALLINT UNSIGNED,
  numberOfProcessingFlowFailuresSoam24h SMALLINT UNSIGNED,
  numberOfEmptyFilePathFailures15m SMALLINT UNSIGNED,
  numberOfParsedDataFailures15m SMALLINT UNSIGNED,
  numberOfSshConnectionFailures15m SMALLINT UNSIGNED,
  numberOfUploadCommandFailures15m SMALLINT UNSIGNED,
  numberOfEmptyFilePathFailures24h SMALLINT UNSIGNED,
  numberOfParsedDataFailures24h SMALLINT UNSIGNED,
  numberOfSshConnectionFailures24h SMALLINT UNSIGNED,
  numberOfUploadCommandFailures24h SMALLINT UNSIGNED,
  numberOfUploadRequestFailuresBulk15m SMALLINT UNSIGNED,
  numberOfSuccessfulRequestsBulk15m SMALLINT UNSIGNED,
  numberOfProcessingFlowFailuresBulk15m MEDIUMINT UNSIGNED,
  numberOfSuccessfulRecoveryRequestsBulk15m MEDIUMINT UNSIGNED,
  numberOfFailedRecoveryRequestsBulk15m MEDIUMINT UNSIGNED,
  noOfCollectedBulkPmFilesBulk15m MEDIUMINT UNSIGNED,
  noOfRecoveredBulkPmFilesBulk15m MEDIUMINT UNSIGNED,
  minProcessingHandlerTimeBulk15m MEDIUMINT UNSIGNED,
  maxProcessingHandlerTimeBulk15m MEDIUMINT UNSIGNED,
  numberOfUploadRequestFailuresBulk24h SMALLINT UNSIGNED,
  numberOfSuccessfulRequestsBulk24h SMALLINT UNSIGNED,
  numberOfProcessingFlowFailuresBulk24h SMALLINT UNSIGNED,
  noOfCollectedBulkPmFilesBulk24h SMALLINT UNSIGNED,
  noOfRecoveredBulkPmFilesBulk24h SMALLINT UNSIGNED,
  minProcessingHandlerTimeBulk24h SMALLINT UNSIGNED,
  maxProcessingHandlerTimeBulk24h SMALLINT UNSIGNED,
  INDEX mspmipInstrSiteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_pmpolicy_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 activePmRouterPolicy BOOLEAN NOT NULL,
 numberOfManagedNodesInStickyCache INT UNSIGNED NOT NULL,
 INDEX pmPolicyInstrSiteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_versant_client_connpool (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    size INT UNSIGNED NOT NULL,
    connectionsInUse INT UNSIGNED NOT NULL,
    allocationFailures INT UNSIGNED NOT NULL,
    connectionFailures INT UNSIGNED NOT NULL,
    allocationTimeouts INT UNSIGNED NOT NULL,
    INDEX cliConnPoolIndx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE esxi_servers(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    hostname VARCHAR(64) NOT NULL,
    UNIQUE INDEX siteHostIdx (siteid,hostname,date),
    PRIMARY KEY(id)
);

create table esxi_cpu_obj_details (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATETIME NOT NULL,
    serverid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES esxi_servers(id)",
    metric VARCHAR(40) NOT NULL,
    instance VARCHAR(50) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    metric_value INT UNSIGNED NOT NULL,
    host_type VARCHAR(40) NOT NULL,
    hostname VARCHAR(40) NOT NULL,
INDEX esxi_cpu_detailsIdx(siteid,date)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

create table esxi_mem_obj_details (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATETIME NOT NULL,
    serverid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES esxi_servers(id)",
    metric VARCHAR(40) NOT NULL,
    instance VARCHAR(50) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    metric_value INT UNSIGNED NOT NULL,
    host_type VARCHAR(40) NOT NULL,
    hostname VARCHAR(40) NOT NULL,
INDEX esxi_mem_detailsIdx(siteid,date)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

create table esxi_net_obj_details (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATETIME NOT NULL,
    serverid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES esxi_servers(id)",
    metric VARCHAR(40) NOT NULL,
    instance VARCHAR(50) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    metric_value INT UNSIGNED NOT NULL,
    host_type VARCHAR(40) NOT NULL,
    hostname VARCHAR(40) NOT NULL,
INDEX esxi_net_detailsIdx(siteid,date)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

create table esxi_disk_obj_details (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATETIME NOT NULL,
    serverid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES esxi_servers(id)",
    metric VARCHAR(40) NOT NULL,
    instance VARCHAR(50) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    metric_value INT UNSIGNED NOT NULL,
    host_type VARCHAR(40) NOT NULL,
    hostname VARCHAR(40) NOT NULL,
INDEX esxi_disk_detailsIdx(siteid,date)
)
PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_file_ingress_processed (
    rop_time DATETIME NOT NULL,
    site_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    no_of_files_created BIGINT UNSIGNED,
    no_of_files_parsed BIGINT UNSIGNED,
    INDEX siteidTime(site_id,rop_time)
) PARTITION BY RANGE ( TO_DAYS(rop_time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_es_index_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(name),
    PRIMARY KEY(id)
);

CREATE TABLE enm_es_indices_cmd_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    index_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_es_index_names(id)",
    health ENUM ('green', 'yellow', 'red', 'other'),
    INDEX siteDateIdx(siteid, date)
);

CREATE TABLE enm_logging_subsystem_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(name),
    PRIMARY KEY(id)
);

CREATE TABLE enm_jboss_logging_levels (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    subsystem_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_logging_subsystem_names(id)",
    logging_level ENUM ('DEBUG', 'TRACE'),
    INDEX siteTimeIdx(siteid, time)
);

CREATE TABLE enm_consul_event_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(name),
    PRIMARY KEY(id)
);

CREATE TABLE enm_consul_n_sam_events (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    time_only_millisec SMALLINT UNSIGNED NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    event_type ENUM ('Consul', 'SAM', 'HADley'),
    event_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_consul_event_names(id)",
    INDEX siteTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_dwhdb_count (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    dbName VARCHAR(15) NOT NULL,
    dbCount INT UNSIGNED NOT NULL,
    INDEX dwhdbConnectionIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_repdb_count (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    dbName VARCHAR(15) NOT NULL,
    dbCount INT UNSIGNED NOT NULL,
    INDEX repdbConnectionIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_dmesg (
 date DATE NOT NULL,
 timeStamp VARCHAR(30) NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 errorMesg VARCHAR(200) NOT NULL
);

CREATE TABLE eniq_stats_faulty_hardware_details (
  date DATE NOT NULL,
  siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites (id)",
  serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers (id)",
  eventId varchar(60) NOT NULL,
  msgId varchar(30) NOT NULL,
  occurrenceTime varchar(20) NOT NULL,
  severity varchar(10) NOT NULL,
  problemClass varchar(100) NOT NULL,
  affects varchar(500) NOT NULL,
  INDEX siteDateIdx(siteId, date)
);

CREATE TABLE enm_ulsa_spectrum_analyser_logs (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    epochtime BIGINT UNSIGNED NOT NULL,
    source VARCHAR(512) NOT NULL,
    sample MEDIUMINT UNSIGNED NOT NULL,
    file_parsing_time MEDIUMINT UNSIGNED NOT NULL,
    fast_fourier_time MEDIUMINT UNSIGNED NOT NULL,
    post_processing_time MEDIUMINT UNSIGNED NOT NULL,
    chart_scaling_time MEDIUMINT UNSIGNED NOT NULL,
    total_time MEDIUMINT UNSIGNED NOT NULL,
    INDEX ulsaSpectrumAnalyserIdx(siteid,time)
);

CREATE TABLE bis_scheduling_info(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    reportName VARCHAR(50) NOT NULL,
    startDate DATETIME NOT NULL,
    endDate DATETIME NOT NULL,
    recurrenceCode SMALLINT UNSIGNED NOT NULL,
    scheduleIntervalMin SMALLINT UNSIGNED NOT NULL,
    scheduleIntervalHour SMALLINT UNSIGNED NOT NULL,
    scheduleIntervalMonth SMALLINT UNSIGNED NOT NULL,
    scheduleIntervalNday SMALLINT UNSIGNED NOT NULL,
    userName VARCHAR(50) NOT NULL,
    recurrence VARCHAR(50) NOT NULL,
    intervalTime VARCHAR(50) NOT NULL,
    INDEX bisSchedulingInfoIndex(siteid, time, startDate)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ebsm_stream_logs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 ropid SMALLINT UNSIGNED NOT NULL,
 nodename VARCHAR(255) NOT NULL,
 countersproduced INT UNSIGNED NOT NULL,
 numoffileswritten INT UNSIGNED NOT NULL,
 numOfFileReWritten INT UNSIGNED NOT NULL,
 epsid SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "REFERENCES enm_ebsm_epsid(id)",
 INDEX ebsmStreamSiteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ebsl_ne_stats (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 countersProduced INT UNSIGNED NOT NULL,
 numOfFilesWritten SMALLINT UNSIGNED NOT NULL,
 numOfFilesReWritten SMALLINT UNSIGNED NOT NULL,
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ebsl_inst_stats (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 epsid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ebsm_epsid(id)",
 countersProduced INT UNSIGNED NOT NULL,
 countersProducedNR INT UNSIGNED,
 numOfFilesWritten SMALLINT UNSIGNED NOT NULL,
 numOfFilesWrittenNR SMALLINT UNSIGNED,
 numOfFilesReWritten SMALLINT UNSIGNED NOT NULL,
 numOfFilesReWrittenNR SMALLINT UNSIGNED,
 numberOfLTEcountersDropped SMALLINT UNSIGNED,
 numberOfNRcountersDropped SMALLINT UNSIGNED,
 numberOfNRcountersDroppedDueToMissingParameter MEDIUMINT UNSIGNED,
 indexSizeOfNRUplinkThroughputCounters MEDIUMINT UNSIGNED,
 indexSizeOfNRDownlinkVoiceThroughputCounters MEDIUMINT UNSIGNED,
 indexSizeOfNRDownlinkNonVoiceThroughputCounters MEDIUMINT UNSIGNED,
 numberOfSuspectCellsPerRop MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE bis_prompt_info (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    reportName varchar(100)  NOT NULL,
    cuid varchar(50)  NOT NULL,
    noOfPrompt varchar(10)  NOT NULL,
    promptName varchar(50)  NOT NULL,
    countOfPrompt varchar(50)  NOT NULL,
    promptValue varchar(600)  NOT NULL,
    INDEX bispromptinfo(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_scheduler_heap_memory(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    memoryUsage DOUBLE UNSIGNED,
    INDEX eniqSchedulerHeapIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_engine_heap_memory(
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    memoryUsage DOUBLE UNSIGNED,
    INDEX eniqEngineHeapIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fm_bsc_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  alarmsProcessingFailure BIGINT UNSIGNED NOT NULL,
  alarmsProcessingSuccess BIGINT UNSIGNED NOT NULL,
  axeAlarmsDiscarded BIGINT UNSIGNED NOT NULL,
  axeAlarmsReceived BIGINT UNSIGNED NOT NULL,
  heartBeatPingsReceived BIGINT UNSIGNED NOT NULL,
  numOfHBFailureNodes INT UNSIGNED NOT NULL,
  numOfSupervisedNodes INT UNSIGNED NOT NULL,
  processedAlarmsForwarded BIGINT UNSIGNED NOT NULL,
  processedAlarmsForwardedFailure BIGINT UNSIGNED NOT NULL,
  processedSyncAlarmsForwarded BIGINT UNSIGNED NOT NULL,
  processedSyncAlarmsForwardedFailures BIGINT UNSIGNED NOT NULL,
  spontAlarmsReceived BIGINT UNSIGNED NOT NULL,
  syncAlarmsReceived BIGINT UNSIGNED NOT NULL,
  unKnownAlarmTypesReceived BIGINT UNSIGNED NOT NULL,
  INDEX siteMsfmBscIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ebsmstream_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  jvmid TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "REFERENCES enm_ebsmstream_jvm_names(id)",
  time DATETIME NOT NULL,
  count INT UNSIGNED NOT NULL,
  processedEventsCounter INT UNSIGNED NOT NULL,
  processedEventsCounter5G INT UNSIGNED,
  droppedEventsCounter INT UNSIGNED NOT NULL,
  droppedEventsCounter5G INT UNSIGNED,
  ebs_qsize MEDIUMINT UNSIGNED,
  esi_qsize MEDIUMINT UNSIGNED,
  INDEX siteidTimeEbsStreamInstrIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shared_netlog_mediation_handler_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  numMedTaskRequestReceived SMALLINT UNSIGNED NOT NULL,
  numCollectionStarted SMALLINT UNSIGNED NOT NULL,
  executionTime BIGINT UNSIGNED NOT NULL,
  INDEX sharedNetlogMediationHandlerInstIndex (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ap_hardware_replace_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    nodeid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
    generate_hardware_replace_node_data_time INT UNSIGNED DEFAULT NULL,
    generate_hardware_replace_icf_time INT UNSIGNED DEFAULT NULL,
    INDEX siteTimeIdx(siteid, time)
);

CREATE TABLE enm_fmsnmpnbi_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  totalNumberOfSubscriptions INT UNSIGNED NOT NULL,
  eventsConsumedFromInputTopic BIGINT UNSIGNED NOT NULL,
  eventsInDispatcherQueue BIGINT UNSIGNED NOT NULL,
  alarmsSentToNotifierQueues BIGINT UNSIGNED NOT NULL,
  alertsSentToNotifierQueues BIGINT UNSIGNED NOT NULL,
  alarmTrapsSentToNMS BIGINT UNSIGNED NOT NULL,
  alertTrapsSentToNMS BIGINT UNSIGNED NOT NULL,
  numberOfAlarmsOnSnmpAgentMib BIGINT UNSIGNED NOT NULL,
  numberOfAlertsOnSnmpAgentMib BIGINT UNSIGNED NOT NULL,
  numberOfSnmpGetOnAlarmTables BIGINT UNSIGNED NOT NULL,
  numberOfSnmpGetOnAlertTables BIGINT UNSIGNED NOT NULL,
  numberOfSnmpGetOnScalars BIGINT UNSIGNED NOT NULL,
  overallAverageLatency INT UNSIGNED NOT NULL,
  nbSnmpNbiAverageLatency INT UNSIGNED NOT NULL,
  INDEX sitefmSnmpNbiInstrIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ebsmstream_jvm_names (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  jvm_name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIndex(jvm_name),
  PRIMARY KEY(id)
);

CREATE TABLE enm_nhm_activekpis (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 kpiname VARCHAR(255) NOT NULL,
 roid SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "REFERENCES enm_nhm_ro(id)",
 count INT UNSIGNED NOT NULL,
 INDEX enmNhmActiveKpiSiteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_nhm_ro (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  roname VARCHAR(64),
  UNIQUE INDEX enmNhmRoIdx(id),
  PRIMARY KEY(id)
);

CREATE TABLE enm_bur_restore_stage_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    restore_stage_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_restore_stage_names(id)",
    duration MEDIUMINT UNSIGNED DEFAULT NULL,
    restore_keyword_id MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_restore_keywords(id)",
    INDEX siteTimeIdx(siteid, start_time)
);

CREATE TABLE enm_bur_restore_throughput_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    restore_keyword_id MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_restore_keywords(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    throughput_mb_per_sec FLOAT(13,4) UNSIGNED DEFAULT NULL,
    backup_mount_point_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_mount_points(id)",
    filesystem_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_filesystems(id)",
    filesystem_used_size INT UNSIGNED NOT NULL,
    filesystem_size INT UNSIGNED NOT NULL,
    INDEX siteDateIdx(siteid, date)
);

CREATE TABLE enm_bur_restore_keywords (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    restore_keyword VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(restore_keyword),
    PRIMARY KEY(id)
);

CREATE TABLE enm_bur_restore_stage_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    restore_stage_name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(restore_stage_name),
    PRIMARY KEY(id)
);

CREATE TABLE enm_bur_restore_filesystems (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fs_name VARCHAR(128) COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(fs_name),
    PRIMARY KEY(id)
);

CREATE TABLE sfs_bur_backup_stage_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    backup_stage_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_stage_names(id)",
    backup_stage_status_id TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_stage_statuses(id)",
    duration MEDIUMINT UNSIGNED DEFAULT NULL,
    backup_keyword_id MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_keywords(id)",
    INDEX siteTimeIdx(siteid, start_time)
);

CREATE TABLE sfs_bur_backup_throughput_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    date DATE NOT NULL,
    backup_keyword_id MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_keywords(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    throughput_mb_per_sec FLOAT(13,4) UNSIGNED DEFAULT NULL,
    backup_mount_point_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_backup_mount_points(id)",
    filesystem_id SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_bur_filesystems(id)",
    filesystem_used_size INT UNSIGNED NOT NULL,
    filesystem_size INT UNSIGNED NOT NULL,
    INDEX siteDateIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_iptrnsprt_notifrec
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    eventtype ENUM( 'AVC', 'CREATE', 'DELETE', 'SDN' ) NOT NULL COLLATE latin1_general_cs,
    moid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
    attribid SMALLINT UNSIGNED COMMENT "REFERENCES enm_iptrnsprt_attrib_names(id)",
    count INT UNSIGNED NOT NULL,
    INDEX iptrnsprt_notifrecIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_iptrnsprt_notiftop
(
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    neid MEDIUMINT UNSIGNED NOT NULL COMMENT "REFERENCES ne(id)",
    count MEDIUMINT UNSIGNED NOT NULL,
    INDEX iptrnsprt_notiftopIdx(siteid, date)
);

CREATE TABLE  enm_iptrnsprt_attrib_names
(
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_cm_nodeevictions (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 networkelement VARCHAR(255) NOT NULL,
 evnotificationcount INT UNSIGNED NOT NULL,
 INDEX enmCmEvictedNotificationSiteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mscmip_syncs_stats (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 start DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 ne_type VARCHAR(32) NOT NULL,
 duration MEDIUMINT UNSIGNED NOT NULL,
 ecim_mo_parsed MEDIUMINT UNSIGNED NOT NULL,
 ecim_t_read_mo_ne  MEDIUMINT UNSIGNED NOT NULL,
 ecim_t_ne_trans_mo  MEDIUMINT UNSIGNED NOT NULL,
 yang_mo_parsed MEDIUMINT UNSIGNED NOT NULL,
 total_mo_parsed MEDIUMINT UNSIGNED NOT NULL,
 yang_t_read_mo_ne  MEDIUMINT UNSIGNED NOT NULL,
 t_read_mo_ne  MEDIUMINT UNSIGNED NOT NULL,
 yang_t_ne_trans_mo  MEDIUMINT UNSIGNED NOT NULL,
 total_t_ne_trans_mo  MEDIUMINT UNSIGNED NOT NULL,
 n_mo_write MEDIUMINT UNSIGNED NOT NULL,
 t_mo_write MEDIUMINT UNSIGNED NOT NULL,
 t_mo_delta INT UNSIGNED NOT NULL,
 ecim_n_mo_attr_read INT UNSIGNED NOT NULL,
 ecim_n_mo_attr_trans  MEDIUMINT UNSIGNED NOT NULL,
 ecim_n_mo_attr_null  MEDIUMINT UNSIGNED NOT NULL,
 ecim_n_mo_attr_delegate  MEDIUMINT UNSIGNED NOT NULL,
 ecim_n_mo_attr_error_trans  MEDIUMINT UNSIGNED NOT NULL,
 ecim_n_mo_error  MEDIUMINT UNSIGNED NOT NULL,
 sync_type VARCHAR(16) NOT NULL,
 INDEX mscmipSyncStatsIdx(siteid,start)
) PARTITION BY RANGE ( TO_DAYS(start) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_smrs_log_stats (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    activeSftpCount SMALLINT UNSIGNED NOT NULL,
    sftpSpawnCount SMALLINT UNSIGNED,
    INDEX smrsLogStatsIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_system_bo (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  pid MEDIUMINT UNSIGNED NOT NULL,
  processTime DATETIME NOT NULL,
  name VARCHAR(30) NOT NULL,
  description VARCHAR(100) NOT NULL,
  cpu DOUBLE UNSIGNED NOT NULL,
  ws DOUBLE UNSIGNED NOT NULL,
  boPath VARCHAR(255) NOT NULL,
  INDEX siteIdTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE enm_stn_cmsync (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 duration MEDIUMINT UNSIGNED NOT NULL,
 num_mo MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteIdIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mssnmpcm_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 startSupervision SMALLINT UNSIGNED NOT NULL,
 stoppedSupervision SMALLINT UNSIGNED NOT NULL,
 successfullSync SMALLINT UNSIGNED NOT NULL,
 failedSyncs SMALLINT UNSIGNED NOT NULL,
 mosSynced SMALLINT UNSIGNED NOT NULL,
 startEciSynchronization SMALLINT UNSIGNED,
 startEciSupervision SMALLINT UNSIGNED,
 INDEX siteIdIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_minilink_cmsync (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 duration MEDIUMINT UNSIGNED NOT NULL,
 mo_synced MEDIUMINT UNSIGNED NOT NULL,
 mo_createdUpdated MEDIUMINT UNSIGNED NOT NULL,
 mo_deleted MEDIUMINT UNSIGNED NOT NULL,
 cmDataTransformTime MEDIUMINT UNSIGNED,
 cmDataRetrievalTime MEDIUMINT UNSIGNED,
 cmDataWriterTime MEDIUMINT UNSIGNED,
 model ENUM('UNRM', 'NRM') CHARACTER SET latin1 COLLATE latin1_general_cs,
 cliDataRetrievalTime MEDIUMINT UNSIGNED,
 numberOfCliMOsSynched SMALLINT UNSIGNED,
 INDEX siteIdIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_vnflaf_wfnames (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_vnflaf_wfexec (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 start DATETIME NOT NULL,
 end DATETIME,
 nameid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_vnflaf_wfnames(id)",
 instanceId VARCHAR(48) NOT NULL COLLATE latin1_general_cs,
 INDEX siteIdIdx(siteid,start)
);

CREATE TABLE eniq_stats_os_memory_profile (
 time DATETIME NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 metric VARCHAR(20) NOT NULL COLLATE latin1_general_cs,
 pages BIGINT UNSIGNED NOT NULL,
 bytes VARCHAR(10) NOT NULL,
 totalPercent VARCHAR(10) NOT NULL,
 INDEX siteTimeIdx(siteId,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_pmic_rop_ulsa (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 fcs DATETIME NOT NULL,
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 netypeid SMALLINT UNSIGNED COMMENT "REFERENCES ne_types(id)",
 datatypeid SMALLINT UNSIGNED COMMENT "REFERENCES enm_pmic_datatypes(id)",
 radiounit VARCHAR(32) NOT NULL,
 rfport VARCHAR(32) NOT NULL,
 filesize MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,fcs)
) PARTITION BY RANGE ( TO_DAYS(fcs) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_secserv_comaaExtIdp_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 externalIdpBindFailed MEDIUMINT UNSIGNED NOT NULL,
 externalIdpBindSuccess MEDIUMINT UNSIGNED NOT NULL,
 externalIdpSearchRequests MEDIUMINT UNSIGNED NOT NULL,
 externalIdpSearchResponseError MEDIUMINT UNSIGNED NOT NULL,
 externalIdpSearchResponseSuccess MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteSecservComaaExtIdpIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_smrsaudit_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 auditStartTime DATETIME NOT NULL,
 netypeid SMALLINT UNSIGNED COMMENT "REFERENCES ne_types(id)",
 auditProcessingTime MEDIUMINT UNSIGNED NOT NULL,
 totalNumberOfDirectoriesScanned MEDIUMINT UNSIGNED NOT NULL,
 totalNumberOfDetectedFiles MEDIUMINT UNSIGNED NOT NULL,
 totalNumberOfMTRsSent MEDIUMINT UNSIGNED NOT NULL,
 totalBytesTransferred INT UNSIGNED,
 INDEX smrsSiteIdTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_kpicalcserv_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 inst TINYINT NOT NULL,
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 notificationHandler_NumberOfFileNotificationsReceived SMALLINT UNSIGNED NOT NULL,
 parserHandler_NumberOfFilesSuccessfullyParsed MEDIUMINT UNSIGNED NOT NULL,
 parserHandler_NumberOfPmCountersParsed MEDIUMINT UNSIGNED NOT NULL,
 kpiRuleHandler_NumberOfPmCountersUsed MEDIUMINT UNSIGNED NOT NULL,
 numberOfKpiValuesWriteSucc MEDIUMINT UNSIGNED NOT NULL,
 moGeneratorHandler_NumberOfMOsGenerated BIGINT UNSIGNED NOT NULL,
 kpiRuleHandler_NumberOfKPIsSuccessfullyGenerated BIGINT UNSIGNED NOT NULL,
 parserHandler_NumberOfFilesUnSuccessfullyParsed MEDIUMINT UNSIGNED NOT NULL,
 averageKpiCalculationTime MEDIUMINT UNSIGNED NOT NULL,
 numberOfKpiValuesWriteFail MEDIUMINT UNSIGNED NOT NULL,
 notificationHandler_NumberOfFilesFoundOnSystem SMALLINT UNSIGNED NOT NULL,
 numberOfMediationEventsReceived SMALLINT UNSIGNED,
 numberOfDiscardedMediationEvents SMALLINT UNSIGNED,
 numberOfRealTimeKpisSuccessfullyGenerated SMALLINT UNSIGNED,
 numberOfPmCountersUsed SMALLINT UNSIGNED,
 numberOfRealTimeWebPushEvents SMALLINT UNSIGNED,
 numberOfFailedRealTimeWebPushEvents SMALLINT UNSIGNED,
 numberOfExecutedQueries SMALLINT UNSIGNED,
 numberOfQueriesResolvedFromCache SMALLINT UNSIGNED,
 numberOfNetworkElementsRetrievedFromQueriesExecution SMALLINT UNSIGNED,
 numberOfNetworkElementsRetrievedFromCachedQueriesResults SMALLINT UNSIGNED,
 numberOfKpiActuallyUpdated SMALLINT UNSIGNED,
 numberOfAllKpisExamined SMALLINT UNSIGNED,
 INDEX siteInstIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_esxi_metrics (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    cpu_ready_summation INT UNSIGNED,
    cpu_costop_summation INT UNSIGNED,
    INDEX siteIdIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) ) (
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE cm_transportcimnormalization_instr (
    time DATETIME not null,
    siteid SMALLINT UNSIGNED NOT NULL,
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    tcimNormalizationNumSuccess INT UNSIGNED,
    tcimNormalizationNumFailure INT UNSIGNED,
    tcimNormalizationTotalNumberOfMoNormalized INT UNSIGNED,
    tcimNormalizationTotalDurationOfNormalization INT UNSIGNED,
    INDEX sitetimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_sg_specific_threadpool_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_sg_specific_threadpool (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 threadpoolid SMALLINT UNSIGNED COMMENT "REFERENCES threadpool_names(id)",
 time DATETIME NOT NULL,
 activeCount SMALLINT UNSIGNED,
 completedTaskCount SMALLINT UNSIGNED,
 queueSize SMALLINT UNSIGNED,
 rejectedCount SMALLINT UNSIGNED,
 INDEX sitetimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_plms_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 averageTimeTakenToCreateOneLink MEDIUMINT UNSIGNED,
 averageTimeTakenToDeleteOneLink MEDIUMINT UNSIGNED,
 averageTimeTakenToImportOneFile MEDIUMINT UNSIGNED,
 averageTimeTakenToListLink MEDIUMINT UNSIGNED,
 numberOfFailedLinkCreation MEDIUMINT UNSIGNED,
 numberOfFailedLinkDeletion MEDIUMINT UNSIGNED,
 numberOfSuccessfulLinkCreation MEDIUMINT UNSIGNED,
 numberOfSuccessfulLinkDeletion MEDIUMINT UNSIGNED,
 numberOfSuccessfulLinkListed MEDIUMINT UNSIGNED,
 totalNumberOfCreateRequests MEDIUMINT UNSIGNED,
 totalNumberOfDeleteRequests MEDIUMINT UNSIGNED,
 totalNumberOfImportFileRequests MEDIUMINT UNSIGNED,
 totalNumberOfImportLinkRequests MEDIUMINT UNSIGNED,
 totalNumberOfListRequests MEDIUMINT UNSIGNED,
 totalNumberOfCreateNotifications MEDIUMINT UNSIGNED,
 totalNumberOfDeleteNotifications MEDIUMINT UNSIGNED,
 totalNumberOfUpdateNotifications MEDIUMINT UNSIGNED,
 totalNumberOfAlarmNotifications MEDIUMINT UNSIGNED,
 totalNumberOfLinkAlarms MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_dpmediation_sas_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 minTxExpiryTimePerMinute MEDIUMINT UNSIGNED,
 totalNumberOfHbResponsesFromSAS MEDIUMINT UNSIGNED,
 totalNumberOfTransmitExpiryTimeSetOnNode MEDIUMINT UNSIGNED,
 totalTransmitExpiryTimePerHbResponseFromSas MEDIUMINT UNSIGNED,
 totalTransmitExpiryTimeSetOnNode MEDIUMINT UNSIGNED,
 maxHbResponseTimePerMinute MEDIUMINT UNSIGNED,
 numberOfFailedAttempsWithSas MEDIUMINT UNSIGNED,
 totalHbResponseTimeFromSas MEDIUMINT UNSIGNED,
 totalNumberOfHbToSAS MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ned_tmi (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 operation ENUM( 'ADDED', 'REMOVED' ),
 netypeid SMALLINT NOT NULL COMMENT "REFERENCES ne_types(id)",
 tmi VARCHAR(64) COLLATE latin1_general_cs,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ned_swsync (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 netypeid SMALLINT NOT NULL COMMENT "REFERENCES ne_types(id)",
 n_nodes SMALLINT NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mdt_execution (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 n_deployed MEDIUMINT UNSIGNED,
 n_undeployed MEDIUMINT UNSIGNED,
 n_unique MEDIUMINT UNSIGNED,
 t_total  MEDIUMINT UNSIGNED,
 t_phase1 MEDIUMINT UNSIGNED,
 t_phase2 MEDIUMINT UNSIGNED,
 t_phase3 MEDIUMINT UNSIGNED,
 t_rootdir_io MEDIUMINT UNSIGNED,
 n_new MEDIUMINT UNSIGNED,
 n_overwritten MEDIUMINT UNSIGNED,
 n_notwritten MEDIUMINT UNSIGNED,
 n_validated MEDIUMINT UNSIGNED,
 n_dependencies MEDIUMINT UNSIGNED,
 n_model_jars SMALLINT UNSIGNED,
 n_meta_info SMALLINT UNSIGNED,
 orphansCreated SMALLINT UNSIGNED,
 orphansRemoved SMALLINT UNSIGNED,
 orphansReclaimed SMALLINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE enm_mtr_processing (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 n_count MEDIUMINT UNSIGNED,
 t_delay MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE enm_eventbasedclient (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 n_selectms MEDIUMINT UNSIGNED,
 t_selectms MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_routerpolicy_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_routerpolicy (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 policyid SMALLINT UNSIGNED COMMENT "REFERENCES enm_routerpolicy_names(id)",
 n_selectms MEDIUMINT UNSIGNED,
 t_selectms MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_cell_management_uc (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_cm_cell_management (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 usecaseid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_cm_cell_management_uc(id)",
 t_execution MEDIUMINT UNSIGNED,
 result ENUM ( 'SUCCESS', 'PARTIAL_SUCCESS', 'ERROR', 'NO_UPDATE_REQUIRED' ),
 rattypes SET('LTE', 'WCDMA', 'GSM', 'NR', 'NB-IoT'),
 reltypeid SMALLINT UNSIGNED COMMENT "REFERENCES mo_names(id)",
 motypeid SMALLINT UNSIGNED COMMENT "REFERENCES mo_names(id)",
 direction ENUM ('INCOMING', 'OUTGOING'),
 rescount SMALLINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_trs_relreq (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 n_poid SMALLINT UNSIGNED,
 t_response MEDIUMINT UNSIGNED,
 app ENUM('network-viewer-logical', 'network-viewer-geo' ),
 reltypes SET('TRANSPORT_LINK', 'X2_eNB-gNB'),
 nodetypes VARCHAR(1024) COLLATE latin1_general_cs,
 relfound VARCHAR(64) COLLATE latin1_general_cs,
 INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE enm_trs_switchview (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 view ENUM('InitialView-geo', 'logical', 'geo'),
 INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE enm_parammgt_genimport (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 size MEDIUMINT UNSIGNED,
 type ENUM('THREE_GPP', 'EDFF'),
 duration MEDIUMINT UNSIGNED,
 n_mo MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE enm_parammgt_gencsv (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 fileSize MEDIUMINT UNSIGNED,
 fileWriteDurationInMs MEDIUMINT UNSIGNED,
 n_poids MEDIUMINT UNSIGNED,
 n_attributes MEDIUMINT UNSIGNED,
INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE enm_domainproxy_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 deregistrationRequestsCount SMALLINT UNSIGNED NOT NULL,
 deregistrationResponsesCount SMALLINT UNSIGNED NOT NULL,
 grantRequestsCount SMALLINT UNSIGNED NOT NULL,
 grantResponsesCount SMALLINT UNSIGNED NOT NULL,
 heartbeatRequestsCount SMALLINT UNSIGNED NOT NULL,
 heartbeatResponsesCount SMALLINT UNSIGNED NOT NULL,
 spectrumInquiryRequestsCount SMALLINT UNSIGNED NOT NULL,
 spectrumInquiryResponsesCount SMALLINT UNSIGNED NOT NULL,
 frequenciesChangedCount SMALLINT UNSIGNED NOT NULL,
 EUtranFrequenciesDeletedCount MEDIUMINT UNSIGNED NOT NULL,
 EUtranFrequencyRelationsDeletedCount SMALLINT UNSIGNED NOT NULL,
 MOsReadFromDPSCount SMALLINT UNSIGNED NOT NULL,
 numberOfTerminatedGrantsIncremental SMALLINT UNSIGNED NOT NULL,
 numberOfRelinquishedGrantsIncremental SMALLINT UNSIGNED NOT NULL,
 numberOfRevokedGrantsIncremental SMALLINT UNSIGNED NOT NULL,
 numberOfSuspendedGrantsIncremental SMALLINT UNSIGNED NOT NULL,
 numberOfFailedCbsdRegistrationsIncremental SMALLINT UNSIGNED NOT NULL,
 numberOfFailedConnectionAttemptsWithSasIncremental SMALLINT UNSIGNED NOT NULL,
 numberOfValidGrantsCount SMALLINT UNSIGNED NOT NULL,
 numberOfMaintainedGrantsCount SMALLINT UNSIGNED NOT NULL,
 numberOfInactiveCellsCount SMALLINT UNSIGNED NOT NULL,
 numberOfActiveCellsCount SMALLINT UNSIGNED NOT NULL,
 postToSasTimeRunningTotal MEDIUMINT UNSIGNED,
 requestsPostedToSASCount SMALLINT UNSIGNED NOT NULL,
 setCbrsTxExpireTimeTimeRunningTotal MEDIUMINT UNSIGNED,
 setCbrsTxExpireTimeCount SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ops_server (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 cliSessionsActive SMALLINT UNSIGNED,
 guiSessionsActive SMALLINT UNSIGNED,
 totSessionsActive SMALLINT UNSIGNED,
 failedCliSession SMALLINT UNSIGNED,
 successfulCliSession SMALLINT UNSIGNED,
 guiSessionsCompleted SMALLINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_winfiol_sessions (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 state ENUM('Connected', 'Failed'),
 n_sessions MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_winfiol_commands (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 n_commands MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shm_filesize_logs (
 time DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 jobType ENUM('Backup') COLLATE latin1_general_cs,
 netypeid SMALLINT NOT NULL COMMENT "REFERENCES ne_types(id)",
 component VARCHAR(10) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 fileSize INT UNSIGNED,
 INDEX siteidTimeIdx (siteid, time)
 ) PARTITION BY RANGE ( TO_DAYS(time ))
 (
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cmutilities (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 numTemplatesCreated SMALLINT UNSIGNED NOT NULL,
 numTemplatesListed SMALLINT UNSIGNED NOT NULL,
 numTemplatesDeleted SMALLINT UNSIGNED NOT NULL,
 numTemplatesRetrieved SMALLINT UNSIGNED NOT NULL,
 createTWCMethodInvocations SMALLINT UNSIGNED NOT NULL,
 createTWCExecutionTimeTotalMillis MEDIUMINT UNSIGNED NOT NULL,
 deleteTMethodInvocations SMALLINT UNSIGNED NOT NULL,
 deleteTExecutionTimeTotalMillis MEDIUMINT UNSIGNED NOT NULL,
 getTMethodInvocations SMALLINT UNSIGNED NOT NULL,
 getTExecutionTimeTotalMillis MEDIUMINT UNSIGNED NOT NULL,
 getTBNMethodInvocations SMALLINT UNSIGNED NOT NULL,
 getTBNExecutionTimeTotalMillis MEDIUMINT UNSIGNED NOT NULL,
 getTSMethodInvocations SMALLINT UNSIGNED NOT NULL,
 getTSExecutionTimeTotalMillis MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
 ) PARTITION BY RANGE ( TO_DAYS(time ))
 (
   PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_geo_kpi_logs (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 usecase ENUM('Export','Import') COLLATE latin1_general_cs,
 application ENUM('CM','PKI','IDAM','FM','NFS','FMX','ENMLogs','SECADM','LDAP','VNFLCM','CMPrePopulation','CMDeltaImport','TotalExport','TotalImport','NCM','NHM')COLLATE latin1_general_cs,
 duration INT UNSIGNED NOT NULL,
 count  MEDIUMINT UNSIGNED,
 totaldata FLOAT(6,2) UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE ocs_processor_details (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    processorTimePercent FLOAT(5,2) UNSIGNED NOT NULL,
    userTimePercent FLOAT(5,2) UNSIGNED NOT NULL,
    totalTimePercent FLOAT(5,2) UNSIGNED NOT NULL,
    INDEX serveridTime(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ocs_system_details (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    processorQueueLength SMALLINT UNSIGNED NOT NULL,
    numberOfProcesses SMALLINT UNSIGNED NOT NULL,
    INDEX serveridTime(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ocs_memory_details (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    freeRam INT UNSIGNED NOT NULL,
    INDEX serveridTime(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ocs_physicaldisk_details (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    avgDiskQueueLength FLOAT(7,4) UNSIGNED NOT NULL,
    readsPerSec FLOAT(7,4) UNSIGNED NOT NULL,
    writesPerSec FLOAT(7,4) UNSIGNED NOT NULL,
    idleTimePercent FLOAT(5,2) UNSIGNED NOT NULL,
    INDEX serveridTime(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shm_axe_inventory (
siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
time DATETIME NOT NULL,
totalTimeTakenToReceiveInventoryResponse MEDIUMINT UNSIGNED NOT NULL,
totalTimeTakenToParsePersistSwInventory MEDIUMINT UNSIGNED NOT NULL,
totalSwInventoryRequests SMALLINT UNSIGNED NOT NULL,
totalTimeTakenToParsePersistHwInventory MEDIUMINT UNSIGNED NOT NULL,
totalHwInventoryRequests SMALLINT UNSIGNED NOT NULL,
totalTimeTakenToParsePersistLicenseInventory MEDIUMINT UNSIGNED NOT NULL,
totalLicenseInventoryRequests SMALLINT UNSIGNED NOT NULL,
totalTimeTakenToParsePersistBackupInventory MEDIUMINT UNSIGNED NOT NULL,
totalBackupInventoryRequests SMALLINT UNSIGNED NOT NULL,
totalTimeTakenForInventorySync MEDIUMINT UNSIGNED NOT NULL,
totalNoOfMediationInvocations SMALLINT UNSIGNED NOT NULL,
synchronizedNodes SMALLINT UNSIGNED NOT NULL,
unSynchronizedNodes SMALLINT UNSIGNED NOT NULL,
INDEX siteIdTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_stats_os_memory_profile_Rhel (
    timeStamp DATETIME NOT NULL,
    siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    metrics VARCHAR(30) NOT NULL,
    bytes BIGINT UNSIGNED NOT NULL,
    INDEX siteTimeIdx(siteId,timeStamp)
) PARTITION BY RANGE ( TO_DAYS(timeStamp) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_msInstances (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    UNIQUE INDEX nameIdx(name),
    PRIMARY KEY(id)
);

CREATE TABLE enm_mssnmpcm_eci_syncstat (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    msInstanceid SMALLINT NOT NULL COMMENT "REFERENCES enm_msInstances(id)",
    duration INT UNSIGNED,
    mstype ENUM('ECI-LightSoft') COLLATE latin1_general_cs,
    msSyncStaus ENUM('PENDING','SYNCHRONIZED','UNSYNCHRONIZED') COLLATE latin1_general_cs,
    msAdded SMALLINT UNSIGNED,
    nesInFile MEDIUMINT UNSIGNED,
    nesAdded MEDIUMINT UNSIGNED,
    nesDeleted MEDIUMINT UNSIGNED,
    INDEX siteIdIdx (siteid,time)
);

CREATE TABLE enm_eba_msstr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 events3 MEDIUMINT UNSIGNED NOT NULL,
 MbytesProcessed3 MEDIUMINT  UNSIGNED NOT NULL,
 droppedConnections3 SMALLINT UNSIGNED NOT NULL,
 activeConnections3 SMALLINT UNSIGNED,
 createdConnections3 SMALLINT UNSIGNED,
 INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_flowautomation (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    activatedFlowsCount SMALLINT UNSIGNED NOT NULL,
    currentlyRunningFlowsCount SMALLINT UNSIGNED NOT NULL,
    enabledFlowsCount SMALLINT UNSIGNED NOT NULL,
    flowInstancesExecutedCount SMALLINT UNSIGNED NOT NULL,
    importedFlowsCount SMALLINT UNSIGNED NOT NULL,
    INDEX siteidTime(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shm_lrf_logs (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    noOfNodes SMALLINT UNSIGNED,
    noOfQuantityUpdates MEDIUMINT UNSIGNED,
    totalTimeTaken MEDIUMINT UNSIGNED,
    fileSize MEDIUMINT UNSIGNED,
    status ENUM('COMPLETED','FAILED') COLLATE latin1_general_cs,
    INDEX siteIdIdx (siteid,time)
);

CREATE TABLE bis_ocs_hardware_details (
    date DATE NOT NULL,
    siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    serverType VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    bios VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    osName VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    osVersion VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    systemBootTime DATETIME NOT NULL,
    physicalMemory INT NOT NULL,
    totalDisk FLOAT(8,2) NOT NULL,
    num SMALLINT NOT NULL,
    cpuType VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
    clockSpeed INT UNSIGNED,
    cores TINYINT UNSIGNED NOT NULL,
    INDEX siteIdDateIdx (siteId,date)
);

CREATE TABLE enm_bulknode_cli_logs (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    job_id INTEGER UNSIGNED,
    job_status ENUM ('IN_PROGRESS', 'SUCCESS', 'FAILED',  'CANCELLED') COLLATE latin1_general_cs,
    duration MEDIUMINT UNSIGNED,
    no_of_commands MEDIUMINT UNSIGNED,
    collection_type ENUM ('SAVED_SEARCH', 'TOPOLOGY_COLLECTION', 'NETWORK_ELEMENTS') COLLATE latin1_general_cs,
    collection_name VARCHAR(255) COLLATE latin1_general_cs,
    total_sessions SMALLINT UNSIGNED,
    sessions_completed SMALLINT UNSIGNED,
    sessions_skipped SMALLINT UNSIGNED,
    sessions_not_supported SMALLINT UNSIGNED,
    INDEX siteIdIdx (siteid,time)
);

CREATE TABLE enm_eba_rpmoflow (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  jvmid TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT "REFERENCES enm_str_jvm_names(id)",
  time DATETIME NOT NULL,
  Number_Of_Event_Files_Written MEDIUMINT UNSIGNED NOT NULL,
  Number_Of_Ctrl_Files_Written MEDIUMINT  UNSIGNED NOT NULL,
  Number_Of_Ctrl_Files_Rewritten MEDIUMINT UNSIGNED NOT NULL,
  Number_Of_Event_Files_Rewritten MEDIUMINT UNSIGNED NOT NULL,
  Total_number_of_output_events MEDIUMINT UNSIGNED NOT NULL,
  Processed_Event_Rate_per_Second MEDIUMINT UNSIGNED NOT NULL,
  binaryFilesWritten SMALLINT UNSIGNED,
  binaryFilesRewritten SMALLINT UNSIGNED,
  binaryEventRate INT UNSIGNED,
  binaryOutputEvents INT UNSIGNED,
  INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE minilink_inventorymediation_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    increaseInventoryMediationInvocations MEDIUMINT UNSIGNED NOT NULL,
    processTimeTakenForNodeResponse MEDIUMINT UNSIGNED NOT NULL,
    processTimeTakenForParsing MEDIUMINT UNSIGNED NOT NULL,
    processTimeTakenForPersistingIntoDPS MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fm_eci_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    alarmsReceived MEDIUMINT UNSIGNED,
    alarmProcessingSuccess MEDIUMINT UNSIGNED,
    alarmProcessingPing MEDIUMINT UNSIGNED,
    alarmsProcessingFailures MEDIUMINT UNSIGNED,
    alarmProcessingLossOfTrap MEDIUMINT UNSIGNED,
    alarmProcessingDiscarded MEDIUMINT UNSIGNED,
    alarmProcessingInvalidRecordType MEDIUMINT UNSIGNED,
    alarmsProcessingNotSupported MEDIUMINT UNSIGNED,
    alarmsForwarded MEDIUMINT UNSIGNED,
    forwardedProcessedAlarmFailures MEDIUMINT UNSIGNED,
    trapsDiscarded  MEDIUMINT UNSIGNED,
    trapsForwarded MEDIUMINT UNSIGNED,
    trapsForwardedFailures  MEDIUMINT UNSIGNED,
    trapsReceived MEDIUMINT UNSIGNED,
    numOfSupervisedNodes MEDIUMINT UNSIGNED,
    numOfSuspendedNodes MEDIUMINT UNSIGNED,
    numOfHBFailureNodes MEDIUMINT UNSIGNED,
    processingAlarmTime MEDIUMINT UNSIGNED,
    syncAlarmCommand MEDIUMINT UNSIGNED,
    INDEX siteIdIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_netex_AddNodeInstr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    numberOfSuccessfulSetLocation SMALLINT UNSIGNED NOT NULL,
    numberOfFailedSetLocation SMALLINT UNSIGNED NOT NULL,
    INDEX siteidTime(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mr (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
PRIMARY KEY(id)
);

CREATE TABLE enm_mr_execution (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 mrid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_mr(id)",
 n_executions SMALLINT UNSIGNED NOT NULL,
INDEX siteTimeIdx(siteid,time)
);

CREATE TABLE enm_lvs_states (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 state ENUM( 'Transition to MASTER STATE', 'Received higher prio advert', 'Received lower prio advert, forcing new election', 'Entering BACKUP STATE' ) COLLATE latin1_general_cs,
 count SMALLINT UNSIGNED NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_system_bo_all (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  cpu DOUBLE UNSIGNED NOT NULL,
  ws DOUBLE UNSIGNED NOT NULL,
  INDEX eniqSystemBoAllSiteTimeIdx(time, siteid)
)  PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE partition_agg (
 tablename VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 partitionname VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
 agginterval ENUM('hour', 'fifteen_min') NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX idx(tablename,partitionname)
);

CREATE TABLE enm_eba_rttflow (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  numberOfEventFilesWritten MEDIUMINT UNSIGNED NOT NULL,
  numberOfEventFilesRewritten MEDIUMINT UNSIGNED NOT NULL,
  totalNumberOfOutputEvents MEDIUMINT UNSIGNED NOT NULL,
  processedEventratePerSecond MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_ver_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

CREATE TABLE eo_ver (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  verid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES tor_ver_names(id)",
  INDEX siteidDateIdx (siteid, date)
);

CREATE TABLE enm_aim_fm_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    totalAlarmsReceived SMALLINT UNSIGNED NOT NULL,
    totalAlarmsDroppedBecauseOfScopeFiltering SMALLINT UNSIGNED NOT NULL,
    totalAlarmsDroppedDueToInvalidData SMALLINT UNSIGNED NOT NULL,
    totalAlarmsDroppedForOtherReasons SMALLINT UNSIGNED NOT NULL,
    INDEX siteIdIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_aim_lifecycle_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    nestingProcessTime MEDIUMINT UNSIGNED NOT NULL,
    totalReceivedIncidentsToNest SMALLINT UNSIGNED NOT NULL,
    totalIncidentsCreated_Nesting SMALLINT UNSIGNED NOT NULL,
    totalIncidentsProcessed SMALLINT UNSIGNED NOT NULL,
    confidenceCalculationTimeInNesting MEDIUMINT UNSIGNED NOT NULL,
    knowledgeBaseRestCallTimeInNesting MEDIUMINT UNSIGNED NOT NULL,
    KBRestCallFailures SMALLINT UNSIGNED NOT NULL,
    KBRestCalls SMALLINT UNSIGNED NOT NULL,
    totalNestingFailures SMALLINT UNSIGNED NOT NULL,
    totalIncidentsCreatedWithFMDataSource SMALLINT UNSIGNED NOT NULL,
    totalIncidentsCreatedWithPMDataSource SMALLINT UNSIGNED NOT NULL,
    totalIncidentsCreatedWithFMAndPMDataSource SMALLINT UNSIGNED NOT NULL,
    totalIncidentsChangedFromFMToFMAndPM SMALLINT UNSIGNEd NOT NULL,
    totalIncidentsChangedFromPMToFMAndPM SMALLINT UNSIGNED NOT NULL,
    totalActiveIncidents MEDIUMINT UNSIGNED NOT NULL,
    totalInactiveIncidents MEDIUMINT UNSIGNED NOT NULL,
    totalClosedEventsReceived SMALLINT UNSIGNED NOT NULL,
    totalActiveIncidentsInCache MEDIUMINT UNSIGNED NOT NULL,
    totalIncidentsSetToInactive SMALLINT UNSIGNED NOT NULL,
    currentIncidentTableSizeInMB MEDIUMINT UNSIGNED NOT NULL,
    currentEventTableSizeInMB MEDIUMINT UNSIGNED NOT NULL,
    singleDimensionKeysSize MEDIUMINT UNSIGNED NOT NULL,
    combinedDimensionKeysSize MEDIUMINT UNSIGNED NOT NULL,
    maxNetworkWideLoadKPIValueCacheSize MEDIUMINT UNSIGNED NOT NULL,
    loadKpiSize SMALLINT UNSIGNED NOT NULL,
    monitoringKpisSize SMALLINT UNSIGNED NOT NULL,
    networkElementScopeSize MEDIUMINT UNSIGNED NOT NULL,
    priorityRankScoresPerCellSize MEDIUMINT UNSIGNED NOT NULL,
    utilizationCellKpiValuesSize MEDIUMINT UNSIGNED NOT NULL,
    totalTimeCreatingIncidents MEDIUMINT UNSIGNED NOT NULL,
    totalTimeUpdatingIncidents MEDIUMINT UNSIGNED NOT NULL,
    totalTimeDeletingIncidents MEDIUMINT UNSIGNED NOT NULL,
    totalTimeFetchingIncidents MEDIUMINT UNSIGNED NOT NULL,
    totalIncidentsCreated SMALLINT UNSIGNED NOT NULL,
    totalIncidentsUpdated SMALLINT UNSIGNED NOT NULL,
    totalIncidentsDeleted SMALLINT UNSIGNED NOT NULL,
    totalIncidentsFetched MEDIUMINT UNSIGNED NOT NULL,
    totalFailedOperations MEDIUMINT UNSIGNED NOT NULL,
    totalOperationsSkippedDueToWriteLock MEDIUMINT UNSIGNED NOT NULL,
    totalIncidentsInAIM MEDIUMINT UNSIGNED NOT NULL,
    totalActiveIncidentsWithFMDataSource SMALLINT UNSIGNED,
    totalActiveIncidentsWithPMDataSource SMALLINT UNSIGNED,
    totalActiveIncidentsWithFMAndPMDataSource SMALLINT UNSIGNED,
    percentageOfLTECellsTrained TINYINT UNSIGNED,
    percentageOfWCDMACellsTrained TINYINT UNSIGNED,
    percentageOfRNCsTrained TINYINT UNSIGNED,
    totalCorrelationsBetweenFMIncidentAndTT MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenPMIncidentAndTT MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenFMPMIncidentAndTT MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenIncidentAndTT MEDIUMINT UNSIGNED,
    totalTroubleTicketsReceived MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenFMIncidentAndWO MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenPMIncidentAndWO MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenFMPMIncidentAndWO MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenIncidentAndWO MEDIUMINT UNSIGNED,
    totalWorkOrdersReceived MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenFMIncidentAndOtherEnrichmentEvents MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenPMIncidentAndOtherEnrichmentEvents MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenFMPMIncidentAndOtherEnrichmentEvents MEDIUMINT UNSIGNED,
    totalCorrelationsBetweenIncidentAndOtherEnrichmentEvents MEDIUMINT UNSIGNED,
    totalOtherEnrichmentEventsReceived MEDIUMINT UNSIGNED,
    totalEventsProcessed MEDIUMINT UNSIGNED,
    eventProcessingTime MEDIUMINT UNSIGNED,
    totalBatchNotificationsSent MEDIUMINT UNSIGNED,
    totalEventsSent MEDIUMINT UNSIGNED,
    totalNotificationErrors MEDIUMINT UNSIGNED,
    eventSendingTime MEDIUMINT UNSIGNED,
    totalSingleIncidentRequest MEDIUMINT UNSIGNED,
    totalMultipleFiltersIncidentRequest MEDIUMINT UNSIGNED,
    multipleFiltersIncidentSendingTime MEDIUMINT UNSIGNED,
    avgEventsInIncident SMALLINT UNSIGNED,
    avgTimeIncidentIsOpen MEDIUMINT UNSIGNED,
    totalOfCellTopologyReads MEDIUMINT UNSIGNED,
    INDEX siteIdIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_aim_knowledge_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    totalCombinedDimensionKeysCreated SMALLINT UNSIGNED NOT NULL,
    totalElementsReceivedToProcess SMALLINT UNSIGNED NOT NULL,
    singleDimensionKeysSize MEDIUMINT UNSIGNED NOT NULL,
    combinedDimensionKeysSize INT UNSIGNED NOT NULL,
    totalCombinedDimensionKeysRemovedFromMemory MEDIUMINT UNSIGNED NOT NULL,
    totalEvictedKeysFromSingleDimensionKeys MEDIUMINT UNSIGNED NOT NULL,
    totalTimeReadingCombinedDimensionKeys MEDIUMINT UNSIGNED NOT NULL,
    totalTimeReadingSingleDimensionKeys MEDIUMINT UNSIGNED NOT NULL,
    totalTimeSavingCombinedDimensionKeys MEDIUMINT UNSIGNED NOT NULL,
    totalTimeSavingSingleDimensionKeys MEDIUMINT UNSIGNED NOT NULL,
    totalCallsToStorageWhenCacheIsUsed SMALLINT UNSIGNED NOT NULL,
    totalRequestsToGetCombinedDimensionKeysInBatch SMALLINT UNSIGNED NOT NULL,
    totalTimeToGetSingleDimensionKeysInBatch MEDIUMINT UNSIGNED NOT NULL,
    totalRequestToGetOneCombinedDimensionKey SMALLINT UNSIGNED NOT NULL,
    totalRequestsToGetSingleDimensionKeysInBatch SMALLINT UNSIGNED NOT NULL,
    totalRequestToGetOneSingleDimensionKey SMALLINT UNSIGNED NOT NULL,
    totalTimeToGetCombinedDimensionKeysInBatch MEDIUMINT UNSIGNED NOT NULL,
    totalTimeToGetOneCombinedDimensionKey MEDIUMINT UNSIGNED NOT NULL,
    totalTimeToGetOneSingleDimensionKey  MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_aim_anomaly_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    validKpiValuesProcessed MEDIUMINT UNSIGNED NOT NULL,
    invalidKpiValuesProcessed MEDIUMINT UNSIGNED NOT NULL,
    emptyKpiResultsReceived MEDIUMINT UNSIGNED NOT NULL,
    totalEventsCreated SMALLINT UNSIGNED NOT NULL,
    totalOpenKpiEventsCreated SMALLINT UNSIGNED NOT NULL,
    totalClosedKpiEventsCreated SMALLINT UNSIGNED NOT NULL,
    totalEventsDropped SMALLINT UNSIGNED NOT NULL,
    totalProcessingTime MEDIUMINT UNSIGNED NOT NULL,
    currentlyActiveAnomalies MEDIUMINT UNSIGNED NOT NULL,
    totalKpiResultsCollected MEDIUMINT UNSIGNED NOT NULL,
    totalLoadKpiValuesProcessed MEDIUMINT UNSIGNED NOT NULL,
    totalMonitoringKpiValuesProcessed MEDIUMINT UNSIGNED NOT NULL,
    loadKpiProcessingTime MEDIUMINT UNSIGNED NOT NULL,
    monitoringKpiProcessingTime MEDIUMINT UNSIGNED NOT NULL,
    totalAnomalyReadingsCreated MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_aim_grouping_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    totalGroupsCreated_GB SMALLINT UNSIGNED NOT NULL,
    totalGroupingProcessTime MEDIUMINT UNSIGNED NOT NULL,
    totalReshufflesExecuted SMALLINT UNSIGNED NOT NULL,
    eventsReceivedToProcess SMALLINT UNSIGNED NOT NULL,
    eventsProcessed SMALLINT UNSIGNED NOT NULL,
    eventsDroppedDueToTimeOut SMALLINT UNSIGNED NOT NULL,
    totalGroupsCreated_IB SMALLINT UNSIGNED NOT NULL,
    totalProcessingTime MEDIUMINT UNSIGNED NOT NULL,
    totalGroupsReceivedToBeProcessed SMALLINT UNSIGNED NOT NULL,
    totalIncidentsCreated SMALLINT UNSIGNED NOT NULL,
    totalIncidentsReceived SMALLINT UNSIGNED NOT NULL,
    totalUnionBatchProcessTime MEDIUMINT UNSIGNED NOT NULL,
    totalEventsInsideIncidentsCreated MEDIUMINT UNSIGNED NOT NULL,
    failedPublishTransactions SMALLINT UNSIGNED NOT NULL,
    combinedDimensionKeysSize MEDIUMINT UNSIGNED NOT NULL,
    totalCombinedDKRequestsToBatchRestService SMALLINT UNSIGNED NOT NULL,
    totalSingleDKRequestsToRestService SMALLINT UNSIGNED NOT NULL,
    totalCombinedDKRequestsToSingleRestService SMALLINT UNSIGNED NOT NULL,
    totalTimeGettingCombinedDimensionKeys MEDIUMINT UNSIGNED NOT NULL,
    totalTimeCalculatingConfidence MEDIUMINT UNSIGNED NOT NULL,
    totalTimeCalculatingGroupConfidence MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_comecim_notification (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    rateCom_ecim_1 MEDIUMINT UNSIGNED NOT NULL,
    rateCom_ecim_2 MEDIUMINT UNSIGNED NOT NULL,
    rateCom_ecim_3 MEDIUMINT UNSIGNED NOT NULL,
    rateCom_ecim_4 MEDIUMINT UNSIGNED NOT NULL,
    rateCom_ecim_5 MEDIUMINT UNSIGNED NOT NULL,
    rateCom_ecim_6 MEDIUMINT UNSIGNED NOT NULL,
    rateCom_ecim_7 MEDIUMINT UNSIGNED NOT NULL,
    rateCom_ecim_8 MEDIUMINT UNSIGNED NOT NULL,
    rateCom_ecim_9 MEDIUMINT UNSIGNED NOT NULL,
    rateCom_ecim_10 MEDIUMINT UNSIGNED NOT NULL,
    useCom_ecim_1 MEDIUMINT UNSIGNED NOT NULL,
    useCom_ecim_2 MEDIUMINT UNSIGNED NOT NULL,
    useCom_ecim_3 MEDIUMINT UNSIGNED NOT NULL,
    useCom_ecim_4 MEDIUMINT UNSIGNED NOT NULL,
    useCom_ecim_5 MEDIUMINT UNSIGNED NOT NULL,
    useCom_ecim_6 MEDIUMINT UNSIGNED NOT NULL,
    useCom_ecim_7 MEDIUMINT UNSIGNED NOT NULL,
    useCom_ecim_8 MEDIUMINT UNSIGNED NOT NULL,
    useCom_ecim_9 MEDIUMINT UNSIGNED NOT NULL,
    useCom_ecim_10 MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ipos_notification (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    useIpos_1 MEDIUMINT UNSIGNED NOT NULL,
    rateIpos_1 MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdx(siteid, time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mskpirt_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    numberOfCollectedCounters MEDIUMINT UNSIGNED NOT NULL,
    numberOfNodesCollected SMALLINT UNSIGNED NOT NULL,
    numberOfRequestsForAllNodes MEDIUMINT UNSIGNED NOT NULL,
    accumulatedFlowsProcessingTime MEDIUMINT UNSIGNED NOT NULL,
    totalFlowsRanCount MEDIUMINT UNSIGNED NOT NULL,
    numberOfFailedCollectionFlows MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_integration_bind (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    projectName VARCHAR(50) NOT NULL,
    neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
    netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
    bindType ENUM('Late','Early') NOT NULL,
    activityType ENUM('Integration', 'Migration') NOT NULL DEFAULT 'Integration',
    INDEX siteTimeIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_zt_integration_time_response_log (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    projectName VARCHAR(50) NOT NULL,
    neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
    netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
    startIntegrationTime MEDIUMINT UNSIGNED NOT NULL,
    downloadIntegrationTime MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteTimeIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE k8s_pod (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 appid SMALLINT UNSIGNED COMMENT "REFERENCES k8s_pod_app_names(id)",
 pod VARCHAR(127) NOT NULL COLLATE latin1_general_cs,
 podIP VARCHAR(40) COLLATE latin1_general_cs,
 nodeid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 INDEX siteDate(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE k8s_node (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 intIP VARCHAR(15) COLLATE latin1_general_cs,
 kubeletVer VARCHAR(15) COLLATE latin1_general_cs ,
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE k8s_pod_cadvisor (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 appid SMALLINT UNSIGNED COMMENT "REFERENCES k8s_pod_app_names(id)",
 cpu_user SMALLINT UNSIGNED,
 cpu_sys SMALLINT UNSIGNED,
 cpu_throttled SMALLINT UNSIGNED,
 mem_mb MEDIUMINT UNSIGNED,
 mem_cache MEDIUMINT UNSIGNED,
 disk_read_mb SMALLINT UNSIGNED,
 disk_write_mb SMALLINT UNSIGNED,
 net_rx_mb SMALLINT UNSIGNED,
 net_tx_mb SMALLINT UNSIGNED,
 net_rx_kpkts SMALLINT UNSIGNED,
 net_tx_kpkts SMALLINT UNSIGNED,
 net_rx_err SMALLINT UNSIGNED,
 net_tx_err SMALLINT UNSIGNED,
 net_rx_drop SMALLINT UNSIGNED,
 net_tx_drop SMALLINT UNSIGNED,
 INDEX siteDateIdx(siteid,time),
 INDEX serverDateIdx(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION PMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE k8s_pod_app_names (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(127) NOT NULL COLLATE latin1_general_cs,
    PRIMARY KEY(id),
    UNIQUE KEY(name)
);

CREATE TABLE k8s_container_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

CREATE TABLE k8s_container_cadvisor (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 appid SMALLINT UNSIGNED COMMENT "REFERENCES k8s_pod_app_names(id)",
 containerid SMALLINT UNSIGNED COMMENT "REFERENCES k8s_container_names(id)",
 cpu_user SMALLINT UNSIGNED,
 cpu_sys SMALLINT UNSIGNED,
 cpu_throttled SMALLINT UNSIGNED,
 mem_mb MEDIUMINT UNSIGNED,
 mem_cache MEDIUMINT UNSIGNED,
 disk_read_mb SMALLINT UNSIGNED,
 disk_write_mb SMALLINT UNSIGNED,
 INDEX siteDateIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE k8s_ha (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 containerid SMALLINT UNSIGNED COMMENT "REFERENCES k8s_container_names(id)",
 workerid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 type ENUM ('Kill', 'UnhealthyReadiness', 'UnhealthyLiveness', 'UnhealthyStartup', 'BackOffRestart'),
 pod VARCHAR(127) NOT NULL COLLATE latin1_general_cs,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE k8s_helm_update (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 end DATETIME NOT NULL,
 start DATETIME NOT NULL,
 name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 operation ENUM('Install', 'Upgrade') NOT NULL,
 toVersion VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
 fromVersion VARCHAR(32) COLLATE latin1_general_cs,
 INDEX siteTimeIdx(siteid,end)
) PARTITION BY RANGE ( TO_DAYS(end) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ccd_version (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 version VARCHAR(15) COLLATE latin1_general_cs ,
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE swim (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 name VARCHAR(32) COLLATE latin1_general_cs,
 pnumber VARCHAR(32) COLLATE latin1_general_cs,
 revision VARCHAR(16) COLLATE latin1_general_cs,
 commercialName VARCHAR(32) COLLATE latin1_general_cs,
 semanticVersion VARCHAR(32) COLLATE latin1_general_cs,
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shmcoreserv_job_instrumentation_logs (
  siteid SMALLINT(5) unsigned NOT NULL,
  time DATETIME NOT NULL,
  jobType ENUM('UPGRADE','BACKUP','RESTORE','LICENSE','SYSTEM','DELETEBACKUP','BACKUP_HOUSEKEEPING',
  'NODERESTART','ONBOARD','DELETE_SOFTWAREPACKAGE','DELETE_UPGRADEPACKAGE','NODE_HEALTH_CHECK',
  'LICENSE_REFRESH') COLLATE latin1_general_cs,
  netypeid SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
  totalCount SMALLINT(5) UNSIGNED NOT NULL,
  jobName VARCHAR(65) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  activities SET('install','verify','upgrade','confirm','createcv','exportcv',
  'setcvasstartable','setcvfirstinrollbacklist','deletecv','download','restore','cleancv',
  'manualrestart','prepare','activate','createbackup','uploadbackup','downloadbackup',
  'restorebackup','confirmbackup','deletebackup','refresh','request','backup',
  'deleteupgradepackage','nodehealthcheck','axeActivity'),
  successCount SMALLINT(5) UNSIGNED NOT NULL,
  failedCount SMALLINT(5) UNSIGNED NOT NULL,
  skippedCount SMALLINT(5) UNSIGNED NOT NULL,
  cancelledCount SMALLINT(5) UNSIGNED NOT NULL,
  INDEX siteidTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE eniq_loader_delimiter_error (
 time_stamp DATETIME NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 techpack_name VARCHAR(50) NOT NULL,
 loader_set VARCHAR(100) NOT NULL,
 INDEX loaderDelimiterErrorIndx(siteid, time_stamp)
) PARTITION BY RANGE ( TO_DAYS(time_stamp) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_sso_app_openam_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 activeSession MEDIUMINT UNSIGNED NOT NULL,
 localFailedUserAuth SMALLINT UNSIGNED NOT NULL,
 localFailedUserPamAuth SMALLINT UNSIGNED NOT NULL,
 localSuccessUserAuth SMALLINT UNSIGNED NOT NULL,
 localSuccessUserPamAuth SMALLINT UNSIGNED NOT NULL,
 remoteFailedUserAuth SMALLINT UNSIGNED NOT NULL,
 remoteFailedUserPamAuth SMALLINT UNSIGNED NOT NULL,
 remoteSuccessUserAuth SMALLINT UNSIGNED NOT NULL,
 remoteSuccessUserPamAuth SMALLINT UNSIGNED NOT NULL,
 unknownFailedUserAuth SMALLINT UNSIGNED NOT NULL,
 unknownFailedUserPamAuth SMALLINT UNSIGNED NOT NULL,
 minLocalLoginResponseTime SMALLINT UNSIGNED NOT NULL,
 avgLocalLoginResponseTime SMALLINT UNSIGNED NOT NULL,
 maxLocalLoginResponseTime SMALLINT UNSIGNED NOT NULL,
 minRemoteLoginResponseTime SMALLINT UNSIGNED NOT NULL,
 avgRemoteLoginResponseTime SMALLINT UNSIGNED NOT NULL,
 maxRemoteLoginResponseTime SMALLINT UNSIGNED NOT NULL,
 minLocalPamResponseTime SMALLINT UNSIGNED NOT NULL,
 avgLocalPamResponseTime SMALLINT UNSIGNED NOT NULL,
 maxLocalPamResponseTime SMALLINT UNSIGNED NOT NULL,
 minRemotePamResponseTime SMALLINT UNSIGNED NOT NULL,
 avgRemotePamResponseTime SMALLINT UNSIGNED NOT NULL,
 maxRemotePamResponseTime SMALLINT UNSIGNED NOT NULL,
 logoutSuccessCount SMALLINT UNSIGNED,
 pamValidateErrorCount SMALLINT UNSIGNED,
 pamValidateSuccessCount SMALLINT UNSIGNED,
 INDEX siteIdx(siteid, time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_site_info (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 nodecount SMALLINT,
 cellcount MEDIUMINT NOT NULL,
 deployment_type ENUM('Small_ENM_customer_cloud', 'SIENM_multi_technology', 'SIENM_transport_only',
  'ENM_extra_small', 'ENM_feature_test_multi_instance', 'ENM_feature_test_single_instance',
  'Test', 'Medium_ENM', 'Large_ENM', 'Extra_Large_ENM', 'Large_Transport_only_ENM',
  'Small_CloudNative_ENM', 'Extra_Large_CloudNative_ENM', 'OSIENM_transport_only', 'Extra_Large_ENM_On_Rack_Servers' ) COLLATE latin1_general_cs,
 INDEX siteIdDateIdx(siteid, date)
)PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE enm_flow_automation_flows_log (
      siteid SMALLINT unsigned NOT NULL,
      time DATETIME NOT NULL,
      FlowId VARCHAR(128) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
      FlowVersion VARCHAR(16) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
      FlowType ENUM('INTERNAL','EXTERNAL','PREDEFINED') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
      EventType ENUM('FLOW_IMPORT','FLOW_ENABLED','FLOW_DISABLED','FLOW_DELETED') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
      FlowName VARCHAR(64) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
      INDEX flowAutoFlowIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
      PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_flow_automation_execution_log (
      siteid SMALLINT UNSIGNED NOT NULL,
      time DATETIME NOT NULL,
      FlowId VARCHAR(128) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
      Duration MEDIUMINT UNSIGNED NOT NULL,
      EventType ENUM('FLOW_EXECUTION_START','FLOW_EXECUTION_SETTING_UP','FLOW_EXECUTION_DELETE','FLOW_EXECUTION_SUSPEND',
          'FLOW_EXECUTION_EXECUTING','FLOW_EXECUTION_CONFIRM_EXECUTE','FLOW_EXECUTION_COMPLETE','FLOW_EXECUTION_STOP',
          'FLOW_EXECUTION_STOPPING','FLOW_EXECUTION_STOPPED','FLOW_EXECUTION_FAILED','FLOW_EXECUTION_FAILED_SETUP',
          'FLOW_EXECUTION_FAILED_EXECUTE','FLOW_EXECUTION_CANCELLED') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
      FlowExecutionName VARCHAR(64) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
      INDEX flowAutoExecIndex(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
      PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ebsgflow (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 inputEventRatePerSecond MEDIUMINT UNSIGNED NOT NULL,
 filteredEventRatePerSecond MEDIUMINT UNSIGNED NOT NULL,
 processedEventRatePerSecond MEDIUMINT UNSIGNED NOT NULL,
 outputCounterVolume MEDIUMINT UNSIGNED NOT NULL,
 processedCounterVolume MEDIUMINT UNSIGNED NOT NULL,
 droppedCounterVolume MEDIUMINT UNSIGNED NOT NULL,
 numberOfCounterFilesWritten SMALLINT UNSIGNED NOT NULL,
 numberOfCounterFilesRewritten SMALLINT UNSIGNED NOT NULL,
 numberOfMonitorFilesWritten SMALLINT UNSIGNED,
 numberOfMonitorFilesRewritten SMALLINT UNSIGNED,
 outputMonitorVolume MEDIUMINT UNSIGNED,
 INDEX siteIdx(siteid, time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);
CREATE TABLE esm_alert_types (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE esm_alert_def (
 siteid SMALLINT UNSIGNED NOT NULL,
 date DATE NOT NULL,
 typeId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES esm_alert_types(id)",
 INDEX siteDateIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_mspmsftp_instr (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    successfulFtpConnectionCounter MEDIUMINT UNSIGNED NOT NULL,
    failureFtpConnectionCounter MEDIUMINT UNSIGNED NOT NULL,
    successfulMinimumFtpConnectionDuration MEDIUMINT UNSIGNED NOT NULL,
    successfulMaximumFtpConnectionDuration MEDIUMINT UNSIGNED NOT NULL,
    failureMinimumFtpConnectionDuration MEDIUMINT UNSIGNED NOT NULL,
    failureMaximumFtpConnectionDuration MEDIUMINT UNSIGNED NOT NULL,
    INDEX siteIdIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fm_alarmoverload_protection (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  overload ENUM('OFF','ON','WARN') NOT NULL COLLATE latin1_general_cs,
  INDEX siteidTimeIdx (siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_capacity (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 area ENUM('alarmInflowPerDay', 'volumeOf15minuteSnmpGetCounters', 'volumeOf15MinuteDataStoredMB',
           'numberOfMosSyncWrite', 'notificationLevelPerDay', 'nodeTransferVolumePerDayMB',
           'eventStreamRatePerSec', 'numberConcurrentConnectionsToManagedNetwork') NOT NULL,
 used INT UNSIGNED NOT NULL,
 available INT UNSIGNED,
 INDEX siteDateIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fmsnmp_nodestatus (
 siteid SMALLINT UNSIGNED NOT NULL,
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 nodeEngineId VARCHAR(255) NOT NULL,
 prevStatus ENUM('UNKNOWN', 'IN_SERVICE', 'OUT_OF_SERVICE') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 newStatus ENUM('UNKNOWN', 'IN_SERVICE', 'OUT_OF_SERVICE') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 hbEventSent BOOLEAN NOT NULL,
 syncReqSent BOOLEAN NOT NULL,
 INDEX siteIdTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fmsnmp_supervisionstatus (
 siteid SMALLINT UNSIGNED NOT NULL,
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 supervisionEvent ENUM('SUPERVISION_ON_SWITCH', 'SUPERVISION_OFF', 'SUPERVISION_ON',
 'SUPERVISION_ON_DUPLICATED', 'SUPERVISION_ON_OLD', 'SUPERVISION_OFF_OLD', 'SYSTEM_GENERATED_OFF')
 CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 requestTime INT UNSIGNED NOT NULL,
 prevStatus ENUM('IDLE', 'IN_SERVICE', 'HEART_BEAT_FAILURE', 'NODE_SUSPENDED', 'SYNCHRONIZATION',
 'SYNC_ONGOING', 'ALARM_SUPPRESSED', 'TECHNICIAN_PRESENT', 'OUT_OF_SYNC')
 CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 newStatus ENUM('IDLE', 'IN_SERVICE', 'HEART_BEAT_FAILURE', 'NODE_SUSPENDED', 'SYNCHRONIZATION',
 'SYNC_ONGOING', 'ALARM_SUPPRESSED', 'TECHNICIAN_PRESENT', 'OUT_OF_SYNC')
 CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 responseSent ENUM('NOT_SENT', 'SENT_OK', 'SENT_FAIL') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 responseDelay SMALLINT NOT NULL,
 switchSent ENUM('NOT_SENT', 'SENT_OK', 'SENT_FAIL') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 INDEX siteIdTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE enm_fmsnmp_syncstatus (
 siteid SMALLINT UNSIGNED NOT NULL,
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 syncEvent ENUM('SYNCHRONIZATION_STARTED', 'SYNCHRONIZATION_ENDED', 'SYNCHRONIZATION_ABORTED',
 'ALARMS_FORWARDED', 'COMMAND ONGOING') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 autoSync BOOLEAN NOT NULL,
 numOfAlarms SMALLINT UNSIGNED NOT NULL,
 elapsedTime MEDIUMINT UNSIGNED,
 INDEX siteIdTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fmsnmp_operationonnode (
 siteid SMALLINT UNSIGNED NOT NULL,
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 duplicated BOOLEAN,
 snmpSecLevel ENUM('NO_AUTH_NO_PRIV', 'AUTH_NO_PRIV', 'AUTH_PRIV', 'NONE') CHARACTER SET latin1 COLLATE latin1_general_cs,
 snmpAuthProtocol ENUM('MD5', 'SHA1', 'NONE') CHARACTER SET latin1 COLLATE latin1_general_cs,
 executionTime SMALLINT UNSIGNED NOT NULL,
 snmpVersion ENUM('SNMP_V1', 'SNMP_V2C', 'SNMP_V3') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 snmpAgentPort SMALLINT UNSIGNED,
 snmpPrivProtocol ENUM('DES', 'AES128', 'NONE') CHARACTER SET latin1 COLLATE latin1_general_cs,
 operationType ENUM('AddNode', 'DelNode') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 snmpTrapPort SMALLINT UNSIGNED,
 snmpReadCommunity VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_general_cs,
 snmpWriteCommunity VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_general_cs,
 INDEX siteIdTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fmsnmp_heartbeat (
 siteid SMALLINT UNSIGNED NOT NULL,
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 executionTime SMALLINT UNSIGNED NOT NULL,
 timeout SMALLINT UNSIGNED,
 operationType ENUM('AddNode', 'DelNode') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 heartbeatMode VARCHAR(5) CHARACTER SET latin1 COLLATE latin1_general_cs,
 intervalValue SMALLINT UNSIGNED,
 result ENUM('SUCCESS', 'FAILURE') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 INDEX siteIdTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE enm_fmsnmp_lossoftrapevent (
 siteid SMALLINT UNSIGNED NOT NULL,
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 typeOfTrap ENUM('ALARM', 'HEARTBEAT') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
 expected MEDIUMINT UNSIGNED NOT NULL,
 actual MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteIdTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ecson_pm_eventtypes (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE ecson_pm_events_cell_pipeline (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 eventtypeId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ecson_pm_eventtypes(id)",
 typeid SMALLINT UNSIGNED COMMENT "REFERENCES event_type_names(id)",
 events MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ecson_pm_events_adjacency_pipeline (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 eventtypeId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ecson_pm_eventtypes(id)",
 events MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ecson_event_data_collector (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 pm_parser_files SMALLINT UNSIGNED,
 pm_parser_events MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shm_import_software_package_log (
  siteid SMALLINT UNSIGNED NOT NULL,
  time datetime NOT NULL,
  netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
  totalTime MEDIUMINT UNSIGNED NOT NULL,
  fileSize SMALLINT UNSIGNED NOT NULL,
  result ENUM('Success','Failed') NOT NULL COLLATE latin1_general_cs,
  importingFrom ENUM('CAS-C','Local') NOT NULL COLLATE latin1_general_cs,
  packageid SMALLINT UNSIGNED COMMENT "REFERENCES ne_up_ver(id)",
  KEY siteIdIdx (siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_secserv_sso_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 enmEniqCitrixIdtSuccess SMALLINT UNSIGNED NOT NULL,
 enmEniqCitrixNetworkAnalyticsServerSuccess SMALLINT UNSIGNED NOT NULL,
 enmEniqCitrixUdtSuccess SMALLINT UNSIGNED NOT NULL,
 enmEniqCitrixWircSuccess SMALLINT UNSIGNED NOT NULL,
 enmEniqWebBoBilaunchpadSuccess SMALLINT UNSIGNED NOT NULL,
 enmEniqWebBoCmcSuccess SMALLINT UNSIGNED NOT NULL,
 enmEniqWebNetanWebplayerSuccess SMALLINT UNSIGNED NOT NULL,
 enmEniqCitrixIdtFailure SMALLINT UNSIGNED NOT NULL,
 enmEniqCitrixNetworkAnalyticsServerFailure SMALLINT UNSIGNED NOT NULL,
 enmEniqCitrixUdtFailure SMALLINT UNSIGNED NOT NULL,
 enmEniqCitrixWircFailure SMALLINT UNSIGNED NOT NULL,
 enmEniqWebBoBilaunchpadFailure SMALLINT UNSIGNED NOT NULL,
 enmEniqWebBoCmcFailure SMALLINT UNSIGNED NOT NULL,
 enmEniqWebNetanWebplayerFailure SMALLINT UNSIGNED NOT NULL,
 INDEX siteIdIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shm_metadatafilecount_log (
  siteid SMALLINT UNSIGNED NOT NULL,
  time DATETIME NOT NULL,
  netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
  count SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_shm_releasenotecount_log (
  siteid SMALLINT UNSIGNED NOT NULL,
  time DATETIME NOT NULL,
  netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
  count SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_sso_token_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 UserTokenValidationSuccess SMALLINT UNSIGNED NOT NULL,
 UserTokenValidationFailure SMALLINT UNSIGNED NOT NULL,
 UserTokenValidationResponseTime MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteIdIdx(siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_fs_snapshot_utilization (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    fileSystem VARCHAR(50) NOT NULL,
    pool VARCHAR(50) NOT NULL,
    attributes VARCHAR(20) NOT NULL,
    size VARCHAR(15) NOT NULL,
    poolOrigin VARCHAR(30) DEFAULT NULL,
    data_percent FLOAT(5,2) UNSIGNED DEFAULT NULL,
    INDEX siteidTimeSnapshotIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE bis_netan_logical_drive_details (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 name VARCHAR(2) NOT NULL,
 capacity FLOAT(5,2) UNSIGNED NOT NULL,
 freeSpace FLOAT(5,2) UNSIGNED NOT NULL,
 usedSpacePercent FLOAT(5,2) UNSIGNED NOT NULL,
 INDEX siteIdDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_vm_hc (
    time DATETIME NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    status ENUM('Long-running health check', 'Monitor Offline', 'Monitor Timeout', 'Warning') NOT NULL COLLATE latin1_general_cs,
    summaryId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_vm_hc_summarys(id)",
    summaryData SMALLINT UNSIGNED,
    INDEX siteTimeIdx (siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_vm_hc_summarys (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(200) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_node_tcim_normalization (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
    netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
    tcimNormalizedNodeState ENUM('CREATED','RUNNING','NORMALIZED', 'FAILED ') NOT NULL COLLATE latin1_general_cs,
    tcimNormalizedMoPerNode SMALLINT UNSIGNED NOT NULL,
    tcimNormalizedMoDurationPerNode SMALLINT UNSIGNED NOT NULL,
    tcimInterfacesCount SMALLINT UNSIGNED,
    tcimNumberOfFailedMos SMALLINT UNSIGNED,
    INDEX siteTimeIdx (siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_assets (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 type ENUM( 'tenants', 'subtenants', 'packages', 'vims', 'vdcs', 'vapps', 'vns',
            'subnets', 'vms', 'vm_vnics', 'bsvs', 'srts', 'cps', 'vrfs', 'srvs',
            'scg', 'site', 'vlinks', 'vnfms', 'images', 'network_services', 'sdnc',
            'subscriptions', 'sgs', 'sgrs', 'sks', 'srvgrps', 'cism_clusters', 'projects',
            'domains', 'dcgws', 'dcgw_vrfs', 'dcgw_vrf_assocs' ) NOT NULL COLLATE latin1_general_cs,
 count MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteIdDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE esm_performance_aggregation_metrics (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    time DATETIME NOT NULL,
    alertDate DATETIME,
    alertDataPurged MEDIUMINT UNSIGNED,
    eventDate DATETIME,
    eventDataPurged MEDIUMINT UNSIGNED,
    aletDefDate DATETIME,
    alertdefinitionspurged MEDIUMINT UNSIGNED,
    dbMaintainDate DATETIME,
    databaseMaintenanceCompleted MEDIUMINT UNSIGNED,
    dataPurgeDate DATETIME,
    dataPurgeJob MEDIUMINT UNSIGNED,
    rawDataDate DATETIME,
    rawDataAggregation MEDIUMINT UNSIGNED,
    rawDataCount SMALLINT UNSIGNED,
    oneHourDate DATETIME,
    oneHourDataAggregation MEDIUMINT UNSIGNED,
    onehourDataCount SMALLINT UNSIGNED,
    sixHourDate DATETIME,
    sixhourDataAggregation MEDIUMINT UNSIGNED,
    sixhourDataCount SMALLINT UNSIGNED,
    serverDate DATETIME,
    serverCount SMALLINT UNSIGNED,
    serviceDate DATETIME,
    serviceCount SMALLINT UNSIGNED,
    platformDate DATETIME,
    platformCount  SMALLINT UNSIGNED,
    INDEX siteTimeIdx (siteid,time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);


CREATE TABLE enm_aim_node_training_status (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 total SMALLINT UNSIGNED NOT NULL,
 netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
 training TINYINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_dps_neo4jtx (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  readTx100MillisecondsCount SMALLINT UNSIGNED,
  readTx10MillisecondsCount SMALLINT UNSIGNED,
  readTx10SecondsCount SMALLINT UNSIGNED,
  readTx1MinuteCount SMALLINT UNSIGNED,
  readTx1SecondCount SMALLINT UNSIGNED,
  readTx2MinutesCount SMALLINT UNSIGNED,
  readTx3MinutesCount SMALLINT UNSIGNED,
  readTx4MinutesCount SMALLINT UNSIGNED,
  readTx500MillisecondsCount SMALLINT UNSIGNED,
  readTx50MillisecondsCount SMALLINT UNSIGNED,
  readTx5MillisecondsCount SMALLINT UNSIGNED,
  readTx5MinutesCount SMALLINT UNSIGNED,
  readTxCount SMALLINT UNSIGNED,
  readTxOver5MinutesCount SMALLINT UNSIGNED,
  writeTx100MillisecondsCount SMALLINT UNSIGNED,
  writeTx10MillisecondsCount SMALLINT UNSIGNED,
  writeTx10SecondsCount SMALLINT UNSIGNED,
  writeTx1MinuteCount SMALLINT UNSIGNED,
  writeTx1SecondCount SMALLINT UNSIGNED,
  writeTx2MinutesCount SMALLINT UNSIGNED,
  writeTx3MinutesCount SMALLINT UNSIGNED,
  writeTx4MinutesCount SMALLINT UNSIGNED,
  writeTx500MillisecondsCount SMALLINT UNSIGNED,
  writeTx50MillisecondsCount SMALLINT UNSIGNED,
  writeTx5MillisecondsCount SMALLINT UNSIGNED,
  writeTx5MinutesCount SMALLINT UNSIGNED,
  writeTxCount SMALLINT UNSIGNED,
  writeTxOver5MinutesCount SMALLINT UNSIGNED,
  acquiredTxPermitsCount SMALLINT UNSIGNED,
  failedToAcquireTxPermitsCount SMALLINT UNSIGNED,
  failureOrTimeoutCount SMALLINT UNSIGNED,
  totalDuration SMALLINT UNSIGNED,
  txPermitsProcedureCount SMALLINT UNSIGNED,
  writeTxWithoutChangesCount MEDIUMINT UNSIGNED,
  totalWriteOperationsPerformed MEDIUMINT UNSIGNED,
  INDEX siteTimeIdx(siteid,time)
)  PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

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

CREATE TABLE enm_private_network_count (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 privatePrivateNetwork SMALLINT UNSIGNED,
 publicPrivateNetwork SMALLINT UNSIGNED,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fidm_syncronizer (
    siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
    serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
    time DATETIME NOT NULL,
    usecase ENUM('EXT_IDP_SYNCRONIZATION', 'MERGE', 'INTERNAL_DS', 'FORCED_DELETE') COLLATE latin1_general_cs,
    taskStartTime DATETIME NOT NULL,
    taskDuration MEDIUMINT UNSIGNED NOT NULL,
    searchRequestsSuccess SMALLINT UNSIGNED,
    searchResultsSuccess SMALLINT UNSIGNED,
    searchRequestsError SMALLINT UNSIGNED,
    ldapErrors SMALLINT UNSIGNED,
    federatedUsers SMALLINT UNSIGNED,
    extIpdEntries SMALLINT UNSIGNED,
    opendjEntries SMALLINT UNSIGNED,
    userCreateSuccess SMALLINT UNSIGNED,
    userCreateError SMALLINT UNSIGNED,
    userUpdateSuccess SMALLINT UNSIGNED,
    userUpdateError SMALLINT UNSIGNED,
    userDeleteSuccess SMALLINT UNSIGNED,
    userDeleteError SMALLINT UNSIGNED,
    INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ecson_cm_topology_model (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  cm_logical_process_time_count SMALLINT UNSIGNED NOT NULL,
  cm_logical_request_count SMALLINT UNSIGNED NOT NULL,
  cm_change_total_count SMALLINT UNSIGNED NOT NULL,
  cm_proposed_change_total_count SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_aim_kpi_training_status (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 kpiname VARCHAR(50) NOT NULL,
 celltraining TINYINT UNSIGNED NOT NULL,
 overtraining TINYINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fmemergency_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  alarmCountReceivedByFmEmergency SMALLINT UNSIGNED NOT NULL,
  failoverCount SMALLINT UNSIGNED NOT NULL,
  heartbeatCount SMALLINT UNSIGNED NOT NULL,
  sentAlarmCountToNBI SMALLINT UNSIGNED NOT NULL,
  supervisedNodes SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ecson_cm_loader_er (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
  transformed_nodes SMALLINT UNSIGNED NOT NULL,
  skipped_nodes SMALLINT UNSIGNED NOT NULL,
  not_persisted_nodes SMALLINT UNSIGNED NOT NULL,
  numberParsedNodes SMALLINT UNSIGNED,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ecson_cm_loader_mos (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  exportjobid SMALLINT UNSIGNED NOT NULL,
  number_invalid_mos SMALLINT UNSIGNED NOT NULL,
  number_processed_mos MEDIUMINT UNSIGNED NOT NULL,
  number_invalid_parsed_nodes SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_winfiol_services (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 service ENUM( 'wfaxems', 'wfbisvc', 'wfftpsvc', 'wfserver' ) COLLATE latin1_general_cs,
 connections SMALLINT UNSIGNED,
 disconnections SMALLINT UNSIGNED,
 failedconnections SMALLINT UNSIGNED,
 commands SMALLINT UNSIGNED,
 openconnections SMALLINT UNSIGNED,
 INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE spark_executor (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 shuftotalmbread SMALLINT UNSIGNED,
 shufremotembread SMALLINT UNSIGNED,
 shufmbwritten SMALLINT UNSIGNED,
 shufrecordsread MEDIUMINT UNSIGNED,
 shufrecordswritten MEDIUMINT UNSIGNED,
 shuffetchtime SMALLINT UNSIGNED,
 shufwritetime MEDIUMINT UNSIGNED,
 tpactivetasks SMALLINT UNSIGNED,
 tpcompletetasks SMALLINT UNSIGNED,
 shuflocalmbread SMALLINT UNSIGNED,
 shuflocalblocksfetched SMALLINT UNSIGNED,
 shufremoteblocksfetched SMALLINT UNSIGNED,
 shufremotembreadtodisk SMALLINT UNSIGNED,
 INDEX siteDateIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncm_nodes_list_realignment (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  status ENUM('SUCCESS', 'FAILURE') COLLATE latin1_general_cs,
  numOfNodes SMALLINT UNSIGNED NOT NULL,
  duration MEDIUMINT UNSIGNED NOT NULL,
  info VARCHAR(100) DEFAULT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncm_node_realignment (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  status ENUM('SUCCESS', 'FAILURE') COLLATE latin1_general_cs,
  neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
  duration MEDIUMINT UNSIGNED NOT NULL,
  jobId MEDIUMINT UNSIGNED NOT NULL,
  ncmNodeId SMALLINT UNSIGNED NOT NULL,
  info VARCHAR(100) DEFAULT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncm_session (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  status ENUM('OPENED', 'CLOSED', 'PING_FAILURE', 'HB_FAILURE') COLLATE latin1_general_cs,
  id  VARCHAR(10) NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncm_links_realignment (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  status ENUM('SUCCESS', 'FAILURE') COLLATE latin1_general_cs,
  numOfLinks SMALLINT UNSIGNED NOT NULL,
  duration MEDIUMINT UNSIGNED NOT NULL,
  info VARCHAR(100) DEFAULT NULL,
  numOfInvalidLinks SMALLINT UNSIGNED,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncmagent_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  addNodeReceivedEvents SMALLINT UNSIGNED NOT NULL,
  addNodeSentEvents SMALLINT UNSIGNED NOT NULL,
  deleteNodeReceivedEvents SMALLINT UNSIGNED NOT NULL,
  deleteNodeSentEvents SMALLINT UNSIGNED NOT NULL,
  nodeDisalignSentEvents SMALLINT UNSIGNED NOT NULL,
  unrmReceivedEvents SMALLINT UNSIGNED NOT NULL,
  unrmEventsProcessingDuration MEDIUMINT UNSIGNED NOT NULL,
  addLinkReceivedEvents SMALLINT UNSIGNED NOT NULL,
  addLinkSentEvents SMALLINT UNSIGNED NOT NULL,
  deleteLinkReceivedEvents SMALLINT UNSIGNED NOT NULL,
  deleteLinkSentEvents SMALLINT UNSIGNED NOT NULL,
  updateLinkReceivedEvents SMALLINT UNSIGNED NOT NULL,
  updateLinkSentEvents SMALLINT UNSIGNED NOT NULL,
  receivedEvents SMALLINT UNSIGNED NOT NULL,
  sentEvents SMALLINT UNSIGNED NOT NULL,
  dpsEventsDelay MEDIUMINT UNSIGNED NOT NULL,
  discardedEventsDueToQueueFull SMALLINT UNSIGNED NOT NULL,
  queueFullEvents SMALLINT UNSIGNED NOT NULL,
  extraEventManagerInvocation SMALLINT UNSIGNED NOT NULL,
  eventsNotSentSinceAgentNotConnected SMALLINT UNSIGNED NOT NULL,
  eventsNotSent SMALLINT UNSIGNED NOT NULL,
  eventsDiscardedSinceAlreadyManaged SMALLINT UNSIGNED NOT NULL,
  nodesListRealignments SMALLINT UNSIGNED NOT NULL,
  nodesListRealignmentsSuccess SMALLINT UNSIGNED NOT NULL,
  nodesListRealignmentsFailed SMALLINT UNSIGNED NOT NULL,
  nodesListRealignmentsSuccessDuration SMALLINT UNSIGNED NOT NULL,
  nodesListRealignmentsFailedDuration SMALLINT UNSIGNED NOT NULL,
  nodeRealignmentsStart SMALLINT UNSIGNED NOT NULL,
  nodeRealignmentsEnd SMALLINT UNSIGNED NOT NULL,
  nodeRealignmentsSuccessDuration SMALLINT UNSIGNED NOT NULL,
  nodeRealignmentsFailedDuration SMALLINT UNSIGNED NOT NULL,
  getMessages SMALLINT UNSIGNED NOT NULL,
  linksRealignments SMALLINT UNSIGNED NOT NULL,
  linksRealignmentsSuccess SMALLINT UNSIGNED NOT NULL,
  linksRealignmentsFailed SMALLINT UNSIGNED NOT NULL,
  linksRealignmentsSuccessDuration SMALLINT UNSIGNED NOT NULL,
  linksRealignmentsFailedDuration SMALLINT UNSIGNED NOT NULL,
  fullRealignments SMALLINT UNSIGNED NOT NULL,
  fullRealignmentsSuccess SMALLINT UNSIGNED NOT NULL,
  fullRealignmentsFailed SMALLINT UNSIGNED NOT NULL,
  fullRealignmentsSuccessDuration SMALLINT UNSIGNED NOT NULL,
  fullRealignmentsFailedDuration SMALLINT UNSIGNED NOT NULL,
  sessionsOpened TINYINT UNSIGNED NOT NULL,
  sessionsClosed TINYINT UNSIGNED NOT NULL,
  sessionsPingFailed TINYINT UNSIGNED NOT NULL,
  sessionsHearthbeatFailed TINYINT UNSIGNED NOT NULL,
  executedCommands TINYINT UNSIGNED,
  failedCommands TINYINT UNSIGNED,
  openSessionFailures TINYINT UNSIGNED,
  openedSessions TINYINT UNSIGNED,
  agentDisalignGeneratedEvents SMALLINT UNSIGNED,
  agentDisalignProcessedEvents SMALLINT UNSIGNED,
  realignEmRequests SMALLINT UNSIGNED,
  realignEmRequestFailures SMALLINT UNSIGNED,
  realignNodesRequests SMALLINT UNSIGNED,
  realignNodesRequestFailures SMALLINT UNSIGNED,
  cmSyncResumedReceivedEvents SMALLINT UNSIGNED,
  nodeLinkUpSentEvents SMALLINT UNSIGNED,
  lostCmSyncReceivedEvents SMALLINT UNSIGNED,
  nodeLinkDownSentEvents SMALLINT UNSIGNED,
  managementStopReceivedEvents SMALLINT UNSIGNED,
  managementStopSentEvents SMALLINT UNSIGNED,
  managementStartReceivedEvents SMALLINT UNSIGNED,
  managementStartSentEvents SMALLINT UNSIGNED,
  nodeChangedReceivedEvents SMALLINT UNSIGNED,
  r6kReceivedEvents SMALLINT UNSIGNED,
  r6kEventsProcessingDuration MEDIUMINT UNSIGNED,
  processDpsEventManagerQueueEventsSkippedDueToOverload SMALLINT UNSIGNED,
  nodeRealignmentsFailed SMALLINT UNSIGNED,
  validRealignedLinks SMALLINT UNSIGNED,
  invalidRealignedLinks SMALLINT UNSIGNED,
  linksDiscoveredAtNodeRealignment SMALLINT UNSIGNED,
  closedSessions TINYINT UNSIGNED,
  closeSessionFailures TINYINT UNSIGNED,
  skippedCommands TINYINT UNSIGNED,
  tcimNodeChangedReceivedEvents SMALLINT UNSIGNED,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE f5_pool_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE f5_pool_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  poolid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES f5_pool_names(id)",
  kbitsInPerSec SMALLINT UNSIGNED NOT NULL,
  kbitsOutPerSec SMALLINT UNSIGNED NOT NULL,
  connections SMALLINT UNSIGNED NOT NULL,
  requests SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ecson_frequency_manager (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  alg_execution_count SMALLINT UNSIGNED NOT NULL,
  alg_execution_time MEDIUMINT UNSIGNED NOT NULL,
  kpi_calculation_time MEDIUMINT UNSIGNED NOT NULL,
  kpi_on_demand_calculation_requests SMALLINT UNSIGNED NOT NULL,
  kpi_on_demand_calculation_time MEDIUMINT UNSIGNED NOT NULL,
  configuration_get_request SMALLINT UNSIGNED NOT NULL,
  configuration_get_time MEDIUMINT UNSIGNED NOT NULL,
  configuration_update_requests SMALLINT UNSIGNED NOT NULL,
  configuration_update_time MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_windows_certi_name_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 certificateName VARCHAR(60) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(certificateName),
 PRIMARY KEY(id)
);

CREATE TABLE eniq_windows_certi_purpose_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 certificatePurpose VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(certificatePurpose),
 PRIMARY KEY(id)
);

CREATE TABLE eniq_windows_certificate_details (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 certificateNameId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_windows_certi_name_id_mapping(id)",
 certificatePurposeId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_windows_certi_purpose_id_mapping(id)",
 expiryDate DATE NOT NULL,
 expiryInDays SMALLINT UNSIGNED NOT NULL,
 INDEX siteIdDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ecson_cm_data_loader (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  parsingTransformingProcessTime SMALLINT UNSIGNED NOT NULL,
  processTimeP0 SMALLINT UNSIGNED NOT NULL,
  processTimeP1 SMALLINT UNSIGNED NOT NULL,
  processTimeP2 SMALLINT UNSIGNED NOT NULL,
  processTimeP3 SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ombs_activity_monitor (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  policyName TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES ombs_policies(id)",
  clientName SMALLINT UNSIGNED  NOT NULL COMMENT "REFERENCES ombs_clients(id)",
  storageUnit TINYINT UNSIGNED NOT NULL COMMENT "REFERENCES ombs_storage_units(id)",
  backupPath SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ombs_paths(id)",
  jobid MEDIUMINT UNSIGNED NOT NULL,
  endTime datetime NOT NULL,
  backupSize MEDIUMINT UNSIGNED NOT NULL,
  activity ENUM ('Backup','Catalog_Backup','Restore','NA','Image_Cleanup'),
  schedule ENUM ('Daily_Incr', 'ENM_Full_Backup', 'Differential-Inc', 'NA', 'Weekly_Full', 'Full_Backup','Full','Custom'),
  numberOfBackupFiles MEDIUMINT UNSIGNED NOT NULL,
  elapsedTime MEDIUMINT UNSIGNED NOT NULL,
  throughPut SMALLINT UNSIGNED NOT NULL,
  jobState ENUM ('Done', 'Active', 'Queued','NA'),
  jobReturnCode SMALLINT UNSIGNED NOT NULL,
  INDEX siteDateIdx(siteid, endTime)
);

CREATE TABLE ombs_policies (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

CREATE TABLE ombs_clients (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

CREATE TABLE ombs_storage_units (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

CREATE TABLE ombs_paths (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

CREATE TABLE enm_nhc_profiles_log (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  profile varchar(100) NOT NULL COLLATE latin1_general_cs,
  netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
  swVerId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_nhc_profiles_sw_versions(id)",
  labelId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_nhc_profiles_labels(id)",
  numberofRulesIncluded SMALLINT UNSIGNED NOT NULL,
  status ENUM( 'Created', 'Imported', 'Partially Imported' ) COLLATE latin1_general_cs,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_nhc_profiles_sw_versions (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(30) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_nhc_profiles_labels (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE nhc_config_types (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(75) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE ecson_kpi_service (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  calculation_time SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE generic_ver_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

CREATE TABLE generic_ver (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  verid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES generic_ver_names(id)",
  INDEX siteidDateIdx (siteid, date)
);

CREATE TABLE enm_nr_eventcounts (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 date DATE NOT NULL,
 eventidNR SMALLINT UNSIGNED NOT NULL,
 eventcount BIGINT UNSIGNED NOT NULL,
 INDEX siteIdx(siteid, date)
);

CREATE TABLE ecson_cm_change_mediation (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  statusUpdateHttpRequest SMALLINT UNSIGNED NOT NULL,
  activationChangeHttpRequest SMALLINT UNSIGNED NOT NULL,
  succeededActivation SMALLINT UNSIGNED NOT NULL,
  succeededChange SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_pm_smrs_housekeeping (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
  procTime MEDIUMINT UNSIGNED NOT NULL,
  filesDeleted MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid, time)
)PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cmwriter_minilink_indoor (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  numberOfSuccessCreateOperations SMALLINT UNSIGNED NOT NULL,
  numberOfSuccessModifyOperations SMALLINT UNSIGNED NOT NULL,
  numberOfSuccessDeleteOperations SMALLINT UNSIGNED NOT NULL,
  numberOfSuccessActionOperations SMALLINT UNSIGNED NOT NULL,
  numberOfSuccessReadOperations SMALLINT UNSIGNED NOT NULL,
  numberOfFailedCreateOperations SMALLINT UNSIGNED NOT NULL,
  numberOfFailedModifyOperations SMALLINT UNSIGNED NOT NULL,
  numberOfFailedDeleteOperations SMALLINT UNSIGNED NOT NULL,
  numberOfFailedActionOperations SMALLINT UNSIGNED NOT NULL,
  numberOfFailedReadOperations SMALLINT UNSIGNED NOT NULL,
  successfullCreateOperationsDuration SMALLINT UNSIGNED NOT NULL,
  successfullModifyOperationsDuration SMALLINT UNSIGNED NOT NULL,
  successfullDeleteOperationsDuration SMALLINT UNSIGNED NOT NULL,
  successfullActionOperationsDuration SMALLINT UNSIGNED NOT NULL,
  successfullReadOperationsDuration SMALLINT UNSIGNED NOT NULL,
  failedCreateOperationsDuration SMALLINT UNSIGNED NOT NULL,
  failedModifyOperationsDuration SMALLINT UNSIGNED NOT NULL,
  failedDeleteOperationsDuration SMALLINT UNSIGNED NOT NULL,
  failedActionOperationsDuration SMALLINT UNSIGNED NOT NULL,
  failedReadOperationsDuration SMALLINT UNSIGNED NOT NULL,
  numberOfSnmpOperationRequests SMALLINT UNSIGNED NOT NULL,
  snmpOperationConstructionTime SMALLINT UNSIGNED NOT NULL,
  snmpOperationSuccessResponseTime SMALLINT UNSIGNED NOT NULL,
  snmpOperationFailureResponseTime SMALLINT UNSIGNED NOT NULL,
  numberOfFailedSnmpOperations SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_flow_asu_overallsummary (
  siteId SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  flowExecutionName VARCHAR(128) NOT NULL DEFAULT '',
  totalNodes SMALLINT(5) UNSIGNED NOT NULL,
  nodesSuccessful SMALLINT(5) UNSIGNED NOT NULL,
  nodesFailed SMALLINT(5) UNSIGNED NOT NULL,
  totalTimetaken MEDIUMINT(8) UNSIGNED NOT NULL,
  timeTakenForSetup MEDIUMINT(8) UNSIGNED NOT NULL,
  timeTakenForInitialization MEDIUMINT(8) UNSIGNED,
  timeTakenForPreparation MEDIUMINT(8) UNSIGNED NOT NULL,
  timeTakenForActivation MEDIUMINT(8) UNSIGNED NOT NULL,
  result ENUM('SUCCESS','FAILED','Successful with Warnings') NOT NULL,
  eventName ENUM ('ASU','ORAN') NOT NULL DEFAULT 'ASU' COLLATE latin1_general_cs,
  nodesCompletedwithwarnings SMALLINT UNSIGNED,
  nodeswithDegradedHealth SMALLINT UNSIGNED,
  adaptiveRestartNodes SMALLINT UNSIGNED,
  nodesCancelled SMALLINT UNSIGNED,
  INDEX siteTimeIdx (siteId,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_flow_asu_phasesummary (
  siteid SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  flowExecutionName VARCHAR(128) NOT NULL DEFAULT '',
  nodesParticipated SMALLINT(5) UNSIGNED NOT NULL,
  nodesSuccess SMALLINT(5) UNSIGNED NOT NULL,
  nodesFailed SMALLINT(5) UNSIGNED NOT NULL,
  timeTaken MEDIUMINT(8) UNSIGNED NOT NULL,
  phase ENUM('Preparation','Activation') NOT NULL,
  activitiesSelected SET('Upgrade','Delete Upgrade Package',
  'Backup HouseKeeping','PreInstall NHC','PreInstall Backup','License Request',
  'PreInstall License','PostInstall License','PostInstall Backup','RadioNode: Pre-Install Script',
  'ERBS: Pre-Install Script','Pre-Upgrade NHC','Pre-Upgrade License','Pre-Upgrade Backup',
  'FM Supervision','Post-Upgrade NHC','Post-Upgrade Backup','RadioNode: Pre-Upgrade Script',
  'RadioNode: Post-Upgrade Script','RadioNode: cleanUp Script','ERBS: Pre-Upgrade Script',
  'ERBS: Post-Upgrade Script','ERBS: cleanUp Script') COLLATE latin1_general_cs,
  eventName ENUM ('ASU','ORAN') NOT NULL DEFAULT 'ASU' COLLATE latin1_general_cs,
  nodesCompletedwithwarnings SMALLINT UNSIGNED,
  nodesCancelled SMALLINT UNSIGNED,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncmagent_messageMbean_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 receivedCliMessageExec SMALLINT UNSIGNED NOT NULL,
 failedCliMessageExec SMALLINT UNSIGNED NOT NULL,
 receivedCliMessageInvokeCandidate SMALLINT UNSIGNED NOT NULL,
 failedCliMessageInvokeCandidate SMALLINT UNSIGNED NOT NULL,
 receivedCliMessageCommit SMALLINT UNSIGNED NOT NULL,
 failedCliMessageCommit SMALLINT UNSIGNED NOT NULL,
 receivedCliMessageInvokeAutoCommit SMALLINT UNSIGNED NOT NULL,
 failedCliMessageInvokeAutoCommit SMALLINT UNSIGNED NOT NULL,
 receivedCliMessageAbort SMALLINT UNSIGNED NOT NULL,
 failedCliMessageAbort SMALLINT UNSIGNED NOT NULL,
 receivedR6KCliMessageExec SMALLINT UNSIGNED NOT NULL,
 failedR6KCliMessageExec SMALLINT UNSIGNED NOT NULL,
 receivedR6KCliMessageInvokeCandidate SMALLINT UNSIGNED NOT NULL,
 failedR6KCliMessageInvokeCandidate SMALLINT UNSIGNED NOT NULL,
 receivedR6KCliMessageCommit SMALLINT UNSIGNED NOT NULL,
 failedR6KCliMessageCommit SMALLINT UNSIGNED NOT NULL,
 receivedR6KCliMessageInvokeAutoCommit SMALLINT UNSIGNED NOT NULL,
 failedR6KCliMessageInvokeAutoCommit SMALLINT UNSIGNED NOT NULL,
 receivedR6KCliMessageAbort SMALLINT UNSIGNED NOT NULL,
 failedR6KCliMessageAbort SMALLINT UNSIGNED NOT NULL,
 successR6KCliMessageExecDuration MEDIUMINT UNSIGNED NOT NULL,
 failedR6KCliMessageExecDuration MEDIUMINT UNSIGNED NOT NULL,
 successR6KCliMessageInvokeCandidateDuration MEDIUMINT UNSIGNED NOT NULL,
 failedR6KCliMessageInvokeCandidateDuration MEDIUMINT UNSIGNED NOT NULL,
 successR6KCliMessageCommitDuration MEDIUMINT UNSIGNED NOT NULL,
 failedR6KCliMessageCommitDuration MEDIUMINT UNSIGNED NOT NULL,
 successR6KCliMessageInvokeAutoCommitDuration MEDIUMINT UNSIGNED NOT NULL,
 failedR6KCliMessageInvokeAutoCommitDuration MEDIUMINT UNSIGNED NOT NULL,
 successR6KCliMessageAbortDuration MEDIUMINT UNSIGNED NOT NULL,
 failedR6KCliMessageAbortDuration MEDIUMINT UNSIGNED NOT NULL,
 receivedMiniLinkCliMessageExec SMALLINT UNSIGNED NOT NULL,
 failedMiniLinkCliMessageExec SMALLINT UNSIGNED NOT NULL,
 receivedMiniLinkCliMessageInvokeCandidate SMALLINT UNSIGNED NOT NULL,
 failedMiniLinkCliMessageInvokeCandidate SMALLINT UNSIGNED NOT NULL,
 receivedMiniLinkCliMessageCommit SMALLINT UNSIGNED NOT NULL,
 failedMiniLinkCliMessageCommit SMALLINT UNSIGNED NOT NULL,
 receivedMiniLinkCliMessageInvokeAutoCommit SMALLINT UNSIGNED NOT NULL,
 failedMiniLinkCliMessageInvokeAutoCommit SMALLINT UNSIGNED NOT NULL,
 receivedMiniLinkCliMessageAbort SMALLINT UNSIGNED NOT NULL,
 failedMiniLinkCliMessageAbort SMALLINT UNSIGNED NOT NULL,
 successMiniLinkCliMessageExecDuration MEDIUMINT UNSIGNED NOT NULL,
 failedMiniLinkCliMessageExecDuration MEDIUMINT UNSIGNED NOT NULL,
 successMiniLinkCliMessageInvokeCandidateDuration MEDIUMINT UNSIGNED NOT NULL,
 failedMiniLinkCliMessageInvokeCandidateDuration MEDIUMINT UNSIGNED NOT NULL,
 successMiniLinkCliMessageCommitDuration MEDIUMINT UNSIGNED NOT NULL,
 failedMiniLinkCliMessageCommitDuration MEDIUMINT UNSIGNED NOT NULL,
 successMiniLinkCliMessageInvokeAutoCommitDuration MEDIUMINT UNSIGNED NOT NULL,
 failedMiniLinkCliMessageInvokeAutoCommitDuration MEDIUMINT UNSIGNED NOT NULL,
 successMiniLinkCliMessageAbortDuration MEDIUMINT UNSIGNED NOT NULL,
 failedMiniLinkCliMessageAbortDuration MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE f5_node_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

CREATE TABLE f5_node_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  nodeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES f5_node_names(id)",
  kbitsOutPerSec SMALLINT UNSIGNED NOT NULL,
  kbitsInPerSec SMALLINT UNSIGNED NOT NULL,
  connections SMALLINT UNSIGNED NOT NULL,
  requests SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE kafka_producer (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  topicid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES kafka_topic_names(id)",
  time DATETIME NOT NULL,
  clientid SMALLINT UNSIGNED NOT NULL,
  records_send MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ecson_ret_custom_service (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  cmChangeCount SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_api_counters (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  cinderv2ApiCount SMALLINT UNSIGNED NOT NULL,
  cinderv3ApiCount SMALLINT UNSIGNED NOT NULL,
  glanceApiCount SMALLINT UNSIGNED NOT NULL,
  heatApiCount SMALLINT UNSIGNED NOT NULL,
  keystoneApiCount SMALLINT UNSIGNED NOT NULL,
  neutronApiCount SMALLINT UNSIGNED NOT NULL,
  novaApiCount SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE event_type_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY (id)
);

CREATE TABLE ecson_pm_events_jdbc_updates (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  typeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES event_type_names(id)",
  time DATETIME NOT NULL,
  jdbcUpdates SMALLINT UNSIGNED NOT NULL,
  failedJdbcUpdates SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ecson_pm_events_processor (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time datetime NOT NULL,
  incomingEvents MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_domainproxy_v2_instr(
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  MOsReadFromDPSCount SMALLINT UNSIGNED NOT NULL,
  MOsReadFromDPSTimeRunningTotal MEDIUMINT UNSIGNED NOT NULL,
  hbResponseTimeFromSas MEDIUMINT UNSIGNED NOT NULL,
  minTransmitExpiryTimePerMinute SMALLINT UNSIGNED NOT NULL,
  numberOfActiveCells SMALLINT UNSIGNED NOT NULL,
  numberOfDeregistrationRequests SMALLINT UNSIGNED NOT NULL,
  numberOfDeregistrationResponses SMALLINT UNSIGNED NOT NULL,
  numberOfFailedAttemptsWithSas SMALLINT UNSIGNED NOT NULL,
  numberOfGrantRequests SMALLINT UNSIGNED NOT NULL,
  numberOfGrantResponses SMALLINT UNSIGNED NOT NULL,
  numberOfHeartbeatRequests SMALLINT UNSIGNED NOT NULL,
  numberOfHeartbeatResponses SMALLINT UNSIGNED NOT NULL,
  numberOfInactiveCells SMALLINT UNSIGNED NOT NULL,
  numberOfMaintainedGrants SMALLINT UNSIGNED NOT NULL,
  numberOfRegisteredCbsds SMALLINT UNSIGNED NOT NULL,
  numberOfRegistrationRequests SMALLINT UNSIGNED NOT NULL,
  numberOfRegistrationResponses SMALLINT UNSIGNED NOT NULL,
  numberOfRelinquishmentRequests SMALLINT UNSIGNED NOT NULL,
  numberOfRelinquishmentResponses SMALLINT UNSIGNED NOT NULL,
  numberOfRenewedGrants SMALLINT UNSIGNED NOT NULL,
  numberOfRequestsPostedToSAS SMALLINT UNSIGNED NOT NULL,
  numberOfSpectrumInquiryRequests SMALLINT UNSIGNED NOT NULL,
  numberOfSpectrumInquiryResponses SMALLINT UNSIGNED NOT NULL,
  numberOfSuspendedGrants SMALLINT UNSIGNED NOT NULL,
  numberOfTerminatedGrants SMALLINT UNSIGNED NOT NULL,
  numberOfTimesFrequenciesChanged SMALLINT UNSIGNED NOT NULL,
  numberOfTimesNodeUpdatedWithExpiryTime SMALLINT UNSIGNED NOT NULL,
  numberOfTransmitExpiryTimePerHbResponseFromSas SMALLINT UNSIGNED NOT NULL,
  numberOfTransmitExpiryTimesSetOnCells SMALLINT UNSIGNED NOT NULL,
  numberOfValidGrants SMALLINT UNSIGNED NOT NULL,
  slowestHbResponseTimePerMinute MEDIUMINT UNSIGNED NOT NULL,
  timeTakenToPostRequestsToSas MEDIUMINT UNSIGNED NOT NULL,
  timeTakenToUpdateExpiryTimeOnNode INT UNSIGNED NOT NULL,
  valueOfTransmitExpiryTimePerHbResponseFromSas INT UNSIGNED NOT NULL,
  valueOfTransmitExpiryTimeSetOnCells INT UNSIGNED NOT NULL,
  numberOfTransmittingCells SMALLINT UNSIGNED,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE f5_virtual_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE f5_virtual_stats (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 virtualid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES f5_virtual_names(id)",
 clientsidekbitsout SMALLINT UNSIGNED NOT NULL,
 clientsidekbitsin SMALLINT UNSIGNED NOT NULL,
 clientsidetotconns SMALLINT UNSIGNED NOT NULL,
 clientsideslowkilled SMALLINT UNSIGNED NOT NULL,
 clientsideevictedconn SMALLINT UNSIGNED NOT NULL,
 ephemeralkbitsout SMALLINT UNSIGNED NOT NULL,
 ephemeralkbitsin SMALLINT UNSIGNED NOT NULL,
 ephemeraltotconns SMALLINT UNSIGNED NOT NULL,
 ephmeralslowkilled SMALLINT UNSIGNED NOT NULL,
 ephmeralevictedconns SMALLINT UNSIGNED NOT NULL,
 totrequests SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncm_cli_command (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
 command VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
 info VARCHAR(100) DEFAULT NULL COLLATE latin1_general_cs,
 ncmNodeId SMALLINT UNSIGNED NOT NULL,
 duration MEDIUMINT UNSIGNED NOT NULL,
 status ENUM ( 'SUCCESS','FAILURE' ) NOT NULL COLLATE latin1_general_cs,
 compliance ENUM ( 'NODE_OK', 'NODE_FAILED', 'NODE_MAX_SSH', 'NODE_SESSION_EXPIRED', 'NODE_INVALID_REQUEST' ) NOT NULL COLLATE latin1_general_cs,
 messageCompliance ENUM ( 'MSG_OK', 'MSG_RMI_ERROR', 'MSG_NEINFO_NOTFOUND' ) NOT NULL COLLATE latin1_general_cs,
 jobId MEDIUMINT UNSIGNED NOT NULL,
 sessionId MEDIUMINT UNSIGNED NOT NULL,
 requestId MEDIUMINT UNSIGNED NOT NULL,
 operationType ENUM('invoke-candidate','invoke-autocommit','exec','commit','abort','invalid') COLLATE latin1_general_cs,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE postgres_checkpoints_bufferwrites (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  checkpointsTimed SMALLINT UNSIGNED NOT NULL,
  checkpointsRequest SMALLINT UNSIGNED NOT NULL,
  checkpointsBuffer SMALLINT UNSIGNED NOT NULL,
  bufferClean SMALLINT UNSIGNED NOT NULL,
  bufferBackend SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE postgres_locks (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  dbid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_postgres_names(id)",
  time DATETIME NOT NULL,
  locks SMALLINT UNSIGNED NOT NULL,
  mode ENUM ('accessexclusivelock', 'accesssharelock', 'sharelock',
  'exclusivelock', 'rowexclusivelock', 'rowsharelock', 'sharerowexclusivelock',
  'shareupdateexclusivelock') NOT NULL COLLATE latin1_general_cs,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncm_node_events_recieved (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 date DATE NOT NULL,
 ncmNodeId SMALLINT UNSIGNED NOT NULL,
 eventType ENUM('NETWORK_ELEMENT_ADD', 'NETWORK_ELEMENT_REMOVE', 'LINK_UP', 'LINK_DOWN', 'NM_DISALIGN', 'MANAGEMENT_START', 'MANAGEMENT_STOP') NOT NULL,
 count SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncm_link_events_recieved (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 date DATE NOT NULL,
 networkLink VARCHAR(150) NOT NULL COLLATE latin1_general_cs,
 eventType ENUM('NETWORK_LINK_ADD', 'NETWORK_LINK_REMOVE', 'NETWORK_LINK_UPDATE') NOT NULL,
 count SMALLINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE f5_cpu_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY (id)
);

CREATE TABLE f5_cpu_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  cpuid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES f5_cpu_names(id)",
  user SMALLINT UNSIGNED NOT NULL,
  system SMALLINT UNSIGNED NOT NULL,
  iowait SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncmcompliance (
 id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_ncm_mef_service_lcm (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  jobId MEDIUMINT UNSIGNED NOT NULL,
  neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
  ncmNodeId SMALLINT UNSIGNED NOT NULL,
  operation ENUM('create','delete','modify') NOT NULL COLLATE latin1_general_cs,
  affectedEntity ENUM('crossConnection','cesPortConfiguration','dataPortParameters','oamConfiguration') NOT NULL COLLATE latin1_general_cs,
  duration MEDIUMINT UNSIGNED NOT NULL,
  status ENUM('SUCCESS', 'FAILURE') COLLATE latin1_general_cs,
  ncmComplianceID TINYINT NOT NULL COMMENT "REFERENCES enm_ncmcompliance(id)",
  info VARCHAR(100) DEFAULT NULL COLLATE latin1_general_cs,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_audit_service (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 duration MEDIUMINT UNSIGNED NOT NULL,
 status ENUM ( 'FAILED','EXECUTED' ) NOT NULL COLLATE latin1_general_cs,
 jobId MEDIUMINT UNSIGNED NOT NULL,
 numberCellsAudited MEDIUMINT UNSIGNED NOT NULL,
 numberCorrectiveOperations MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE kafka_producer_clients (
  siteid SMALLINT UNSIGNED NOT NULL NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  clientid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES kafka_client_names(id)",
  time datetime NOT NULL,
  errorTotal SMALLINT UNSIGNED NOT NULL,
  lantancyMax SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ncmagent_mefServiceLcm_instr (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 configMessages SMALLINT UNSIGNED NOT NULL,
 unexpectedFailed SMALLINT UNSIGNED NOT NULL,
 createCrossConnectionsSuccess SMALLINT UNSIGNED NOT NULL,
 createCrossConnectionsFailed SMALLINT UNSIGNED NOT NULL,
 createCrossConnectionsSuccessDuration MEDIUMINT UNSIGNED NOT NULL,
 createCrossConnectionsFailedDuration MEDIUMINT UNSIGNED NOT NULL,
 deleteCrossConnectionsSuccess SMALLINT UNSIGNED NOT NULL,
 deleteCrossConnectionsFailed SMALLINT UNSIGNED NOT NULL,
 deleteCrossConnectionsSuccessDuration MEDIUMINT UNSIGNED NOT NULL,
 deleteCrossConnectionsFailedDuration MEDIUMINT UNSIGNED NOT NULL,
 modifyCrossConnectionsSuccess SMALLINT UNSIGNED NOT NULL,
 modifyCrossConnectionsFailed SMALLINT UNSIGNED NOT NULL,
 modifyCrossConnectionsSuccessDuration MEDIUMINT UNSIGNED NOT NULL,
 modifyCrossConnectionsFailedDuration MEDIUMINT UNSIGNED NOT NULL,
 setDataPortParametersSuccess SMALLINT UNSIGNED NOT NULL,
 setDataPortParametersFailed SMALLINT UNSIGNED NOT NULL,
 setDataPortParametersSuccessDuration MEDIUMINT UNSIGNED NOT NULL,
 setDataPortParametersFailedDuration MEDIUMINT UNSIGNED NOT NULL,
 setCesPortConfigSuccess SMALLINT UNSIGNED NOT NULL,
 setCesPortConfigFailed SMALLINT UNSIGNED NOT NULL,
 setCesPortConfigSuccessDuration MEDIUMINT UNSIGNED NOT NULL,
 setCesPortConfigFailedDuration  MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_perf_service_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE eo_perf_service_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serviceid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eo_perf_service_names(id)",
  count SMALLINT UNSIGNED NOT NULL,
  total MEDIUMINT UNSIGNED NOT NULL,
  min MEDIUMINT UNSIGNED NOT NULL,
  max MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ap_node_prov (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  date DATE NOT NULL,
  activityType ENUM('greenfield', 'expansion', 'hardwareReplace', 'greenfieldZT', 'hardwareReplaceZT', 'migration', 'migrationZT') NOT NULL,
  other SMALLINT UNSIGNED NOT NULL,
  unknown SMALLINT UNSIGNED NOT NULL,
  ect SMALLINT UNSIGNED NOT NULL,
  pci SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_f5_memory_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  memoryTotal SMALLINT UNSIGNED NOT NULL,
  memoryUsed SMALLINT UNSIGNED NOT NULL,
  tmmMemoryTotal SMALLINT UNSIGNED NOT NULL,
  tmmMemoryUsed SMALLINT UNSIGNED NOT NULL,
  swapTotal SMALLINT UNSIGNED NOT NULL,
  swapUsed SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_f5_nic_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(16) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE eo_f5_nic_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  nicid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eo_f5_nic_names(id)",
  kbitsOutPerSec SMALLINT UNSIGNED NOT NULL,
  kbitsInPerSec SMALLINT UNSIGNED NOT NULL,
  dropsAll SMALLINT UNSIGNED NOT NULL,
  errorsAll SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_sam_server_failure_report (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  reason ENUM('Health Check and Serf Health','Health Check','Serf Health','All Failures') COLLATE latin1_general_cs,
  members varchar(5100) NOT NULL,
  count SMALLINT UNSIGNED NOT NULL,
  notifiedLCM ENUM ('true','false') COLLATE latin1_general_cs,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_flexible_controller (
 time datetime NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 numberOfFlexibleCountersInSystem SMALLINT UNSIGNED NOT NULL,
 numberOfRequestForCreateEndpoint SMALLINT UNSIGNED NOT NULL,
 numberOfRequestForImportEndpoint SMALLINT UNSIGNED NOT NULL,
 numberOfRequestsForDeleteEndpoint SMALLINT UNSIGNED NOT NULL,
 numberOfBatchesAddedToQueue SMALLINT UNSIGNED,
 numberOfBatchesRemovedFromQueue SMALLINT UNSIGNED,
 numberOfFlexibleCountersAddedToQueue SMALLINT UNSIGNED,
 numberOfFlexibleCountersRemovedFromQueue SMALLINT UNSIGNED,
 numberOfFlexibleCountersInCreationAddedToQueue SMALLINT UNSIGNED,
 numberOfFlexibleCountersInCreationRemovedFromQueue SMALLINT UNSIGNED,
 numberOfFlexibleCountersInDeletionAddedToQueue SMALLINT UNSIGNED,
 numberOfFlexibleCountersInDeletionRemovedFromQueue SMALLINT UNSIGNED,
 numberOfFlexibleCountersCreated SMALLINT UNSIGNED,
 numberOfRequestsForUpdateEndpoint SMALLINT UNSIGNED,
 numberOfFlexibleCountersInUpdateAddedToQueue SMALLINT UNSIGNED,
 numberOfFlexibleCountersInUpdateRemovedFromQueue SMALLINT UNSIGNED,
 INDEX siteIdTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_f5_http_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  numberReqs SMALLINT UNSIGNED NOT NULL,
  postReqs SMALLINT UNSIGNED NOT NULL,
  getReqs SMALLINT UNSIGNED NOT NULL,
  resp_2xxCnt SMALLINT UNSIGNED NOT NULL,
  resp_3xxCnt SMALLINT UNSIGNED NOT NULL,
  resp_4xxCnt SMALLINT UNSIGNED NOT NULL,
  resp_5xxCnt SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_f5_tcp_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  connects SMALLINT UNSIGNED NOT NULL,
  connFails SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_asr_job (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 inputEventCount MEDIUMINT UNSIGNED NOT NULL,
 filteredEvents MEDIUMINT UNSIGNED NOT NULL,
 completeRecords MEDIUMINT UNSIGNED NOT NULL,
 suspectRecords MEDIUMINT UNSIGNED NOT NULL,
 readlongTaskDuration SMALLINT UNSIGNED,
 kafkaReadTime SMALLINT UNSIGNED,
 readNumTasks SMALLINT UNSIGNED,
 kafkaReadTimeMax SMALLINT UNSIGNED,
 inputEventsMax SMALLINT UNSIGNED,
 filteredEventsMax SMALLINT UNSIGNED,
 writelongTaskDuration SMALLINT UNSIGNED,
 writeNumTasks SMALLINT UNSIGNED,
 completeRecordsMax SMALLINT UNSIGNED,
 endTriggeredSessions SMALLINT UNSIGNED,
 endTriggeredSessionsMax SMALLINT UNSIGNED,
 inactiveSuspectSession SMALLINT UNSIGNED,
 inactiveSuspectSessionMax SMALLINT UNSIGNED,
 kafkaWriteTime SMALLINT UNSIGNED,
 kafkaWriteTimeMax SMALLINT UNSIGNED,
 mapStateTime SMALLINT UNSIGNED,
 mapStateTimeMax SMALLINT UNSIGNED,
 jobType ENUM('ASRN','ASRL') NOT NULL DEFAULT 'ASRN',
 INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_f5_pool_states (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  poolMemid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES f5_pool_names(id)",
  state BOOLEAN NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_f5_node_states (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  nodeMemid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES f5_node_names(id)",
  state BOOLEAN NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_f5_virtual_states (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  virtServId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES f5_virtual_names(id)",
  state BOOLEAN NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_release_version_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 rhelVersion VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(rhelVersion),
 PRIMARY KEY(id)
);

CREATE TABLE eniq_rhel_version (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 rhelId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_release_version_id_mapping(id)",
 INDEX rhelVersionIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_patch_version_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 patchVersion VARCHAR(50) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(patchVersion),
 PRIMARY KEY(id)
);

CREATE TABLE eniq_patch_version (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 patchId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_patch_version_id_mapping(id)",
 INDEX patchVersionIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_f5_ld_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  size MEDIUMINT UNSIGNED NOT NULL,
  vgFree SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_aggregated_counter_table_name_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tableName VARCHAR(70) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(tableName),
 PRIMARY KEY(id)
);

CREATE TABLE eniq_aggregated_counter_name_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 counterName VARCHAR(80) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(counterName),
 PRIMARY KEY(id)
);

CREATE TABLE eniq_aggregated_counter_feature_name_id_mapping (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 featureName VARCHAR(200) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(featureName),
 PRIMARY KEY(id)
);

CREATE TABLE eniq_aggregated_accessed_counter_details (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 tableNameId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_aggregated_counter_table_name_id_mapping(id)",
 counterNameId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_aggregated_counter_name_id_mapping(id)",
 featureNameId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_aggregated_counter_feature_name_id_mapping(id)",
 accessedCount SMALLINT UNSIGNED NOT NULL,
 lastAccessedDate DATE NOT NULL,
 INDEX siteidDateIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_cassandra_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  casReadTimeouts SMALLINT UNSIGNED NOT NULL,
  casWriteTimeouts SMALLINT UNSIGNED NOT NULL,
  hostTimeouts SMALLINT UNSIGNED NOT NULL,
  casReadFailures SMALLINT UNSIGNED NOT NULL,
  casWriteFailures SMALLINT UNSIGNED NOT NULL,
  casReadUnavailables SMALLINT UNSIGNED NOT NULL,
  casWriteUnavailables SMALLINT UNSIGNED NOT NULL,
  casReadLatency SMALLINT UNSIGNED NOT NULL,
  casWriteLatency SMALLINT UNSIGNED NOT NULL,
  readLatency SMALLINT UNSIGNED NOT NULL,
  writeLatency SMALLINT UNSIGNED NOT NULL,
  readTimeouts SMALLINT UNSIGNED NOT NULL,
  writeTimeouts SMALLINT UNSIGNED NOT NULL,
  writeFailures SMALLINT UNSIGNED NOT NULL,
  readFailures SMALLINT UNSIGNED NOT NULL,
  writeUnavailables SMALLINT UNSIGNED NOT NULL,
  readUnavailables SMALLINT UNSIGNED NOT NULL,
  completedTasksMemtableFlushWriter SMALLINT UNSIGNED NOT NULL,
  completedTasksMutation SMALLINT UNSIGNED NOT NULL,
  completedTasksRead SMALLINT UNSIGNED NOT NULL,
  totalBlockedTasksCompactionExecutor SMALLINT UNSIGNED NOT NULL,
  totalBlockedTasksMemtableFlushWriter SMALLINT UNSIGNED NOT NULL,
  totalBlockedTasksMutationStage SMALLINT UNSIGNED NOT NULL,
  totalBlockedTasksReadStage SMALLINT UNSIGNED NOT NULL,
  commitLogWaitingOnSegmentAllocation SMALLINT UNSIGNED NOT NULL,
  droppedRead SMALLINT UNSIGNED NOT NULL,
  droppedMutations SMALLINT UNSIGNED NOT NULL,
  storageExceptions SMALLINT UNSIGNED NOT NULL,
  storageLoad MEDIUMINT UNSIGNED NOT NULL,
  totalHints SMALLINT UNSIGNED NOT NULL,
  clientConnectedNativeClients SMALLINT UNSIGNED NOT NULL,
  pendingCompactions SMALLINT UNSIGNED NOT NULL,
  pendingFlushes SMALLINT UNSIGNED NOT NULL,
  totalCompactions SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_transport_ims_core_node_details (
 date DATE NOT NULL,
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 technology ENUM('TRANSPORT','IMS','CORE') NOT NULL COLLATE latin1_general_cs,
 nodeType ENUM('ROUTER6k','MINI-LINK Indoor','MINI-LINK Outdoor','FRONTHAUL','CISCO-ASR','JUNIPER',
  'SCU ESC','BASEBAND-T605','SBG-IS','CSCF-TSP','HSS-FE-TSP','MTAS-TSP','SBG vSBG','MTAS vMTAS','DSC vDSC',
  'CSCF vCSCF','HSS-FE vHSS-FE','IPWorks vIPWorks','MRS vMRS','MRF vMRF','BGF vBGF','EME vEME','WCG vWCG DUA-S',
  'MRFC MRF-PTT','PGM','PTT-AS','IMS','vAFG','cSAPC-TSP','BSP','SAPC vSAPC eSAPC','UPG vUPG','WMG vWMG','CUDB vCUDB',
  'EIR-FE vEIR-FE','MGW M-MGW','EPG vEPG','EPG-OI vEPG-OI','SGSN-MME vSGSN-MME','vECE','MSC-DB','MSC-DB-BSP',
  'MSC vMSC MSCServer vMSCServer','IP-STP vIP-STP STP vSTP','IP-STP-BSP','MSC-BC-IS','MSC-BC-BSP','vMSC-HC','MSC-BC',
  'HLR-FE vHLR-FE','HLR-FE-BSP','HLR-FE-IS','HLR-S','HLR','GGSN','HSS','REDBACK','EPDG','IS-MGC IS-MMGC','vNSDS','WMG-OI vWMG-OI') NOT NULL COLLATE latin1_general_cs,
 nodeTypeCount MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteidDateIdx(siteid, date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_webpush_active_channel_names (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(256) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_webpush_active_channels (
 time datetime NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 siteid SMALLINT UNSIGNED NOT NULL NOT NULL COMMENT "REFERENCES sites(id)",
 channelid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_webpush_active_channel_names(id)",
 incoming_events MEDIUMINT UNSIGNED,
 outgoing_events MEDIUMINT UNSIGNED,
 INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_bo_display_name_id_mapping (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  displayName VARCHAR(70) NOT NULL,
  UNIQUE INDEX nameIdx(displayName),
  PRIMARY KEY (id)
);

CREATE TABLE eniq_bo_version_details (
 date DATE DEFAULT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 displayId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_bo_display_name_id_mapping(id)",
 type ENUM ("BIS") NOT NULL DEFAULT 'BIS',
 INDEX siteIdDateX (siteId,date)
)
 PARTITION BY RANGE (to_days(date))
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
 );

CREATE TABLE ncm_interfaces (
 id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
 name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
 UNIQUE INDEX nameIdx(name),
 PRIMARY KEY(id)
);

CREATE TABLE enm_ncm_ignored_interfaces (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
 interfaceId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ncm_interfaces(id)",
 misconfiguredMoId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
 compliance ENUM('UNSUPPORTED_INTERFACE_NAME', 'UNRM_INTERFACE_MISSING', 'UNRM_INTERFACE_TYPE_NULL',
  'UNRM_INTERFACE_HIGHER_LAYER_IF_SIZE_ERROR', 'RLT_WITH_HIGHER_RL_IME', 'HIGHER_WAN_MISSING', 'HIGHER_WAN_SPEED_NULL',
  'ETHERNET_MO_MISSING', 'ETHERNET_MO_PORT_USAGE_NULL', 'ETHERNET_MO_PORT_USAGE_UNSUPPORTED', 'SWITCH_PORT_CONFIG_MO_MISSING',
  'SWITCH_PORT_CONFIG_MO_PORT_ROLE_UNSUPPORTED', 'LAG_LOWER_LAYER_IF_NULL', 'LAG_LOWER_LAYER_IF_EMPTY',
  'WAN_MEMBER_OF_LAG_WITH_LOWER_LAYER_IF_NULL', 'WAN_MEMBER_OF_LAG_WITH_LOWER_LAYER_IF_EMPTY', 'PORT_SERVICE_PROFILE_ERROR') NOT NULL COLLATE latin1_general_cs,
 INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_radio_node_count_details (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  technology ENUM('GSM', 'WCDMA', 'LTE', '5G NR') NOT NULL,
  g1Count SMALLINT UNSIGNED NOT NULL,
  g2Count SMALLINT UNSIGNED NOT NULL,
  mixedCount SMALLINT UNSIGNED NOT NULL,
  totalCount SMALLINT  UNSIGNED NOT NULL,
  INDEX siteDateIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_radio_cell_count_details (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  technology ENUM('GSM', 'WCDMA', 'LTE', '5G NR') NOT NULL,
  g1CellCount MEDIUMINT UNSIGNED NOT NULL,
  g2CellCount MEDIUMINT UNSIGNED NOT NULL,
  mixedCellCount SMALLINT UNSIGNED NOT NULL,
  totalCellCount MEDIUMINT UNSIGNED NOT NULL,
  g1NodeCount SMALLINT UNSIGNED NOT NULL,
  g2NodeCount SMALLINT UNSIGNED NOT NULL,
  mixedNodeCount SMALLINT UNSIGNED NOT NULL,
  totalNodeCount SMALLINT UNSIGNED NOT NULL,
  INDEX siteDateIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_ocs_license_usage_details (
 time DATETIME NOT NULL,
 siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 totalLicenses SMALLINT UNSIGNED NOT NULL,
 licenseUsage SMALLINT UNSIGNED NOT NULL,
 INDEX siteIdTimeIdx (siteId,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_bo_process_name_id_mapping (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  processName VARCHAR(50) NOT NULL,
  UNIQUE INDEX nameIdx(processName),
  PRIMARY KEY (id)
);

CREATE TABLE eniq_bo_path_id_mapping (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pathName VARCHAR(150) NOT NULL,
  UNIQUE INDEX nameIdx(pathName),
  PRIMARY KEY (id)
);

CREATE TABLE eniq_bo_desc_id_mapping (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  description VARCHAR(150) NOT NULL,
  UNIQUE INDEX nameIdx(description),
  PRIMARY KEY (id)
);

CREATE TABLE eniq_ocs_system_bo (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  pid MEDIUMINT UNSIGNED NOT NULL,
  proId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_bo_process_name_id_mapping(id)",
  pathId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_bo_path_id_mapping(id)",
  desId SMALLINT UNSIGNED NULL COMMENT "REFERENCES eniq_bo_desc_id_mapping(id)",
  processStartTime DATETIME NOT NULL,
  cpu MEDIUMINT UNSIGNED NOT NULL,
  ws MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_ocs_system_bo_all (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  cpu MEDIUMINT UNSIGNED NOT NULL,
  ws MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_jboss_connection_pool (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  poolid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eo_jboss_connection_pool_names(id)",
  blockingFailureCount SMALLINT UNSIGNED NOT NULL,
  createdCount SMALLINT UNSIGNED NOT NULL,
  destroyedCount SMALLINT UNSIGNED NOT NULL,
  timedOut SMALLINT UNSIGNED NOT NULL,
  totalBlockingTime SMALLINT UNSIGNED NOT NULL,
  totalCreationTime SMALLINT UNSIGNED NOT NULL,
  waitCount SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eo_jboss_connection_pool_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(64) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);


CREATE TABLE eniq_pico_rnc_cell_count_details (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  technology ENUM( 'WCDMA', 'LTE') NOT NULL,
  picoCellCount SMALLINT UNSIGNED NOT NULL,
  rncCellCount SMALLINT UNSIGNED NOT NULL,
  totalCellCount SMALLINT UNSIGNED NOT NULL,
  picoNodeCount SMALLINT UNSIGNED NOT NULL,
  rncNodeCount SMALLINT UNSIGNED NOT NULL,
  totalNodeCount SMALLINT UNSIGNED NOT NULL,
  INDEX siteDateIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_pico_rnc_node_count_details (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  technology ENUM( 'WCDMA', 'LTE') NOT NULL,
  picoCount SMALLINT UNSIGNED NOT NULL,
  rncCount SMALLINT UNSIGNED NOT NULL,
  totalCount SMALLINT UNSIGNED NOT NULL,
  INDEX siteDateIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cmwriter_minilink_outdoor (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  numberOfSuccessCreateOperations SMALLINT UNSIGNED NOT NULL,
  numberOfSuccessModifyOperations SMALLINT UNSIGNED NOT NULL,
  numberOfSuccessDeleteOperations SMALLINT UNSIGNED NOT NULL,
  numberOfSuccessActionOperations SMALLINT UNSIGNED NOT NULL,
  numberOfSuccessReadOperations SMALLINT UNSIGNED NOT NULL,
  numberOfFailedCreateOperations SMALLINT UNSIGNED NOT NULL,
  numberOfFailedModifyOperations SMALLINT UNSIGNED NOT NULL,
  numberOfFailedDeleteOperations SMALLINT UNSIGNED NOT NULL,
  numberOfFailedActionOperations SMALLINT UNSIGNED NOT NULL,
  numberOfFailedReadOperations SMALLINT UNSIGNED NOT NULL,
  successfullCreateOperationsDuration SMALLINT UNSIGNED NOT NULL,
  successfullModifyOperationsDuration SMALLINT UNSIGNED NOT NULL,
  successfullDeleteOperationsDuration SMALLINT UNSIGNED NOT NULL,
  successfullActionOperationsDuration SMALLINT UNSIGNED NOT NULL,
  successfullReadOperationsDuration SMALLINT UNSIGNED NOT NULL,
  failedCreateOperationsDuration SMALLINT UNSIGNED NOT NULL,
  failedModifyOperationsDuration SMALLINT UNSIGNED NOT NULL,
  failedDeleteOperationsDuration SMALLINT UNSIGNED NOT NULL,
  failedActionOperationsDuration SMALLINT UNSIGNED NOT NULL,
  failedReadOperationsDuration SMALLINT UNSIGNED NOT NULL,
  numberOfSnmpOperationRequests SMALLINT UNSIGNED NOT NULL,
  snmpOperationConstructionTime SMALLINT UNSIGNED NOT NULL,
  snmpOperationSuccessResponseTime SMALLINT UNSIGNED NOT NULL,
  snmpOperationFailureResponseTime SMALLINT UNSIGNED NOT NULL,
  numberOfFailedSnmpOperations SMALLINT UNSIGNED NOT NULL,
  numberOfCliOperationRequests SMALLINT UNSIGNED NOT NULL,
  cliOperationConstructionTime SMALLINT UNSIGNED NOT NULL,
  cliOperationSuccessResponseTime SMALLINT UNSIGNED NOT NULL,
  cliOperationFailureResponseTime SMALLINT UNSIGNED NOT NULL,
  numberOfFailedCliOperations SMALLINT UNSIGNED NOT NULL,
  numberOfSkippedCreateOperations SMALLINT UNSIGNED,
  numberOfSkippedModifyOperations SMALLINT UNSIGNED,
  numberOfSkippedDeleteOperations SMALLINT UNSIGNED,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_ddp_report (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverId INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  status ENUM('Yes') COLLATE latin1_general_cs,
  fileType ENUM( 'ddp_report', 'mpath', 'iq_header' ) COLLATE latin1_general_cs,
  INDEX siteDateIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_eshistory_indices_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  indexId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES enm_es_index_names(id)",
  noOfDocs INT UNSIGNED NOT NULL,
  noOfDocsDeleted MEDIUMINT UNSIGNED NOT NULL,
  sizeOfIndex MEDIUMINT UNSIGNED NOT NULL,
  numIndex SMALLINT UNSIGNED,
  INDEX siteDateIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_jboss_threadpools_nonstandard (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  threadpoolid SMALLINT UNSIGNED COMMENT "REFERENCES enm_sg_specific_threadpool_names(id)",
  queueSize SMALLINT UNSIGNED NOT NULL,
  busytaskThreadcount SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_aim_measurement_training (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  netypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
  motypeid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES mo_names(id)",
  measurementObjectsCount SMALLINT UNSIGNED NOT NULL,
  measurementObjectsTraining SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_modeling_fileread_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  avgModelReadTime MEDIUMINT UNSIGNED NOT NULL,
  maxModelReadTime MEDIUMINT UNSIGNED NOT NULL,
  repoReadTime MEDIUMINT UNSIGNED NOT NULL,
  maxRepoReadTime MEDIUMINT UNSIGNED NOT NULL,
  repoReads MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_agg_fail_counter_date (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  failedDate DATE NOT NULL,
  INDEX siteDateIdx (siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_asr_batch (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 duration SMALLINT UNSIGNED NOT NULL,
 schDelay SMALLINT UNSIGNED NOT NULL,
 batchProcTime SMALLINT UNSIGNED NOT NULL,
 INDEX siteIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_windows_interface_stats (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  MDLReadHits MEDIUMINT UNSIGNED NOT NULL,
  MDLReadsPerSec MEDIUMINT UNSIGNED NOT NULL,
  usagePeak MEDIUMINT UNSIGNED NOT NULL,
  processorTime MEDIUMINT NOT NULL,
  userTime MEDIUMINT UNSIGNED NOT NULL,
  elapsedTime MEDIUMINT UNSIGNED NOT NULL,
  ioDataBytes MEDIUMINT UNSIGNED NOT NULL,
  ioDataOperation MEDIUMINT UNSIGNED NOT NULL,
  ioOtherBytes MEDIUMINT UNSIGNED NOT NULL,
  ioOtherOperation MEDIUMINT UNSIGNED NOT NULL,
  ioReadBytes MEDIUMINT UNSIGNED NOT NULL,
  ioReadOperation MEDIUMINT UNSIGNED NOT NULL,
  ioWriteBytes MEDIUMINT UNSIGNED NOT NULL,
  ioWriteOperation MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteidTime(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE zookeeper (
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  appid SMALLINT UNSIGNED COMMENT "REFERENCES k8s_pod_app_names(id)",
  packetsReceived MEDIUMINT UNSIGNED NULL,
  watchCount SMALLINT UNSIGNED NULL,
  maxLatency TINYINT UNSIGNED NULL,
  avgLatency TINYINT UNSIGNED NULL,
  minLatency TINYINT UNSIGNED NULL,
  pendingSyncs SMALLINT UNSIGNED NULL,
  znodeCount SMALLINT UNSIGNED NULL,
  outstandingRequests SMALLINT UNSIGNED NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_snmp_sync_failures (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  snmpNotAssessedMoType SET('BandwidthProfile','CarrierTermination','CesPseudoWire','CesPwAssignedTdmInterface',
  'CesPwOverEthernet','CesPwOverIp','CesServer','CeVlanEvc','CeVlanEvcSetting','CistPortConfig','CosProfile',
  'CosToPriorityMap','CosToPriorityProfile','CosValueGroup','CVidMapping','ERPModeConfig','Ethernet',
  'EthernetBwNotificationConfig','EthernetRingProtection','EthernetRingProtectionPort','EthernetVirtualConnection',
  'FarEndCarrierTermination','FarEndRadioLinkTerminal','FeatureKey','HrlbGroup','HrlbMember','HwItem','Interface',
  'L1Connection','L1EndPoint','L2VlanConnection','L2VlanEndPoint','LinkAggregationGroup','LldpData','Lm','LocalMEP',
  'LocalUNI','MacAddress','MacList','MaintenanceAssociation','MaintenanceDomain','ManagedElement','MEP','MimoGroup',
  'MIP','MSTI','MstiPortConfig','NetworkInstance','PriorityMapping','QinQTermination','RadioLinkTerminal','RemoteUNI',
  'RLTProtectionGroup','RLTProtectionGroups','RstpPortConfig','StpPort','SwitchPortConfig','SwItem','SwVersion',
  'TdmConnection','UserPriorityMapping','VlanGroup','VlanTranslation','XpicPair') COLLATE latin1_general_cs NOT NULL,
  neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
  INDEX siteTimeIdx(siteid, time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ingress_controller_traffic (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  clientConnActive SMALLINT UNSIGNED NOT NULL,
  clientConnRead SMALLINT UNSIGNED NOT NULL,
  clientConnWrite SMALLINT UNSIGNED NOT NULL,
  clientConnWait SMALLINT UNSIGNED NOT NULL,
  totalConnection SMALLINT UNSIGNED NOT NULL,
  bytesRead SMALLINT UNSIGNED NOT NULL,
  bytesWrite SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_data_layer_sap_iq_versions (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(5) NOT NULL,
  UNIQUE INDEX sapIqVersionIdx(name),
  PRIMARY KEY (id)
);

CREATE TABLE eniq_data_layer_sap_iq (
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  sapIqVersionId SMALLINT UNSIGNED COMMENT "REFERENCES eniq_data_layer_sap_iq_versions(id)",
  mainDbUsagePercentage TINYINT UNSIGNED,
  mainDbFiles MEDIUMINT UNSIGNED,
  mainDbSizeInGb SMALLINT UNSIGNED,
  sysmainDbUsagePercentage TINYINT UNSIGNED,
  sysmainDbFiles MEDIUMINT UNSIGNED,
  sysmainDbSizeInGb SMALLINT UNSIGNED,
  mainCacheInUsePercentage TINYINT UNSIGNED,
  tempCacheInUsePercentage TINYINT UNSIGNED,
  tempDbUsagePercentage TINYINT UNSIGNED,
  tempDbFiles MEDIUMINT UNSIGNED,
  tempDbSizeInGb SMALLINT UNSIGNED,
  mainCacheHitRateInPercentage TINYINT UNSIGNED,
  tempCacheHitRateInPercentage TINYINT UNSIGNED,
  cacheUsedInPercentage TINYINT UNSIGNED,
  activeConnections SMALLINT UNSIGNED,
  largeMemoryFlexiblePercentage SMALLINT UNSIGNED,
  largeMemoryInflexiblePercentage SMALLINT UNSIGNED,
  totalConnections SMALLINT UNSIGNED,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE nginx_requests (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  appid SMALLINT UNSIGNED COMMENT "REFERENCES k8s_pod_app_names(id)",
  statusCode SMALLINT UNSIGNED NOT NULL,
  numRequests SMALLINT UNSIGNED NOT NULL,
  pathid SMALLINT UNSIGNED COMMENT "REFERENCES enm_nginx_path(id)",
  method ENUM ( 'GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE' ) COLLATE latin1_general_cs,
  INDEX siteTimeIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_housekeeping_function_timings (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  function ENUM('SCAN','HOUSEKEEPING') COLLATE latin1_general_cs,
  duration SMALLINT UNSIGNED NOT NULL,
  INDEX siteDateIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_housekeeping_function_details (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  netypeId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ne_types(id)",
  networkLocked SMALLINT UNSIGNED NOT NULL,
  locked SMALLINT UNSIGNED NOT NULL,
  unlocked SMALLINT UNSIGNED NOT NULL,
  deleted SMALLINT UNSIGNED NOT NULL,
  INDEX siteDateIdx (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_comecim_tcim_status (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
  tcimState ENUM('CREATED', 'RUNNING', 'UPDATING', 'NORMALIZED', 'FAILED'),
  reason ENUM('Node is added in ENM', 'TCIM normalization is running', 'TCIM normalization is updating', 'Fully Normalized', 'Partially Normalized', 'Failure during processing MOs'),
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE elasticsearch_filesystem (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  fsAvailableMBytes MEDIUMINT UNSIGNED,
  fsFreeMBytes MEDIUMINT UNSIGNED,
  servicetype ENUM('elasticsearch') NOT NULL DEFAULT 'elasticsearch' COLLATE latin1_general_cs,
  INDEX (siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_fc_port_switch_names (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  switchName VARCHAR(20) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(switchName),
  PRIMARY KEY(id)
);

CREATE TABLE eniq_fc_switch_port_alarms (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  switchNameID SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_fc_port_switch_names(id)",
  port SMALLINT UNSIGNED NOT NULL,
  state ENUM ('Disabled') NOT NULL COLLATE latin1_general_cs,
  INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_resource_requests (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 time DATETIME NOT NULL,
 serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
 requestResource ENUM('determine-candidate-cells', 'determine-reparented-relations', 'determine-reparented-cells',
  'determine-reparented-links', 'determine-delete-candidate-cells', 'determine-impacts',
  'determine-cutover-candidate-cells', 'determine-cutover-reparented-cells', 'determine-customize-cells',
  'determine-reparented-conflicting-relations', 'determine-candidate-conflicting-relations',
  'determine-delete-conflicting-relations', 'determine-delete-candidate-relations',
  'determine-delete-redundant-external-cells', 'determine-delete-candidate-links', 'execute-pre-checks',
  'determine-cutover-candidate-links', 'determine-precutover-reparented-cells', 'determine-delete-basestations',
  'determine-reparented-basestations', 'parse-excel') NOT NULL,
 requestTechnologyType ENUM('GSM', 'WCDMA') COLLATE latin1_general_cs NOT NULL,
 requestIncludeMscOperations BOOLEAN,
 requestSize SMALLINT UNSIGNED,
 responseStatus ENUM('ACCEPTED', 'RUNNING', 'COMPLETED', 'UNKNOWN', 'FAILED') COLLATE latin1_general_cs NOT NULL,
 responseSize SMALLINT UNSIGNED,
 executionTime MEDIUMINT UNSIGNED NOT NULL,
 INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_sync_status_changes (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
  syncStatus ENUM('SYNCHRONIZED', 'UNSYNCHRONIZED', 'TOPOLOGY', 'PENDING', 'ATTRIBUTE') NOT NULL COLLATE latin1_general_cs,
  reason ENUM('SW sync started', 'SW sync started due to configuration change on the node',
   'SW sync started due to failure in previous sync', 'Bulk sync started',
   'Synchronization was successful', 'Sync failure due to failure during transformation of Node data to U-NRM data',
   'Node not connected', 'Sync failure due to failure during persisting MO',
   'Sync failure due to Communication issues with the node during data retrieval',
   'SW sync started as part of Upgrade process', 'SW sync started as part of Periodic task',
   'Synchronization status changed to UNSYNCHRONIZED as sync status for the node is in PENDING or TOPOLOGY for more than defined threshold',
   'Synchronization status changed to UNSYNCHRONIZED as max CM sync retries exceeds defined threshold',
   'Synchronization status changed to UNSYNCHRONIZED due to failure in initiating sync flow',
   'Synchronization status changed to UNSYNCHRONIZED as part of heartbeat enabling',
   'Synchronization status changed to UNSYNCHRONIZED as part of heartbeat enabling due to SNMP connection parameters change',
   'Sync failure due to unable to retrieve the OMI',
   'Synchronization status changed to UNSYNCHRONIZED due to unexpected previous sync state',
   'Synchronization status changed to UNSYNCHRONIZED as SW sync is initiated and CM Supervision is not enabled',
   'Sync failure due to unable to retrieve the node model',
   'Synchronization status changed to PENDING for SHM Upgrade and Restore jobs to avoid unexpected sync state',
   'SW sync started as part of Manual sync',
   'Synchronization status changed to UNSYNCHRONIZED due to failure while performing Create(CRUD) operation for the MO',
   'Synchronization status changed to UNSYNCHRONIZED due to failure while performing Update(CRUD) operation for the MO',
   'Synchronization status changed to UNSYNCHRONIZED due to failure while performing Delete(CRUD) operation for the MO',
   'Sync failed as adding node to the node registry failed',
   'Synchronization status changed to UNSYNCHRONIZED as part of peridic task',
   'Synchronization status changed to UNSYNCHRONIZED as CM supervision is disabled') NOT NULL COLLATE latin1_general_cs,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_snmp_node_heartbeat_status (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  neid INT UNSIGNED NOT NULL COMMENT "REFERENCES enm_ne(id)",
  netypeid SMALLINT UNSIGNED COMMENT "REFERENCES ne_types(id)",
  syncRequest ENUM('yes', 'no'),
  previousHBStatus ENUM('UNKNOWN', 'IN_SERVICE', 'OUT_OF_SERVICE', 'OUT_OF_SYNC', 'IN_SYNC') NOT NULL COLLATE latin1_general_cs,
  currentHBStatus ENUM('UNKNOWN', 'IN_SERVICE', 'OUT_OF_SERVICE', 'OUT_OF_SYNC', 'IN_SYNC') NOT NULL COLLATE latin1_general_cs,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_minilink_failed_syncs_summary (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  netypeid SMALLINT UNSIGNED COMMENT "REFERENCES ne_types(id)",
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_dps_neo4j_client_connection_pool (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  acquiredCount MEDIUMINT UNSIGNED NOT NULL,
  acquiringCount SMALLINT UNSIGNED NOT NULL,
  closedCount SMALLINT UNSIGNED NOT NULL,
  createdCount SMALLINT UNSIGNED NOT NULL,
  failedToCreateCount SMALLINT UNSIGNED NOT NULL,
  idleCount SMALLINT UNSIGNED NOT NULL,
  inUseCount SMALLINT UNSIGNED NOT NULL,
  timedOutToAcquireCount SMALLINT UNSIGNED NOT NULL,
  totalAcquisitionTime MEDIUMINT UNSIGNED NOT NULL,
  totalConnectionTime SMALLINT UNSIGNED NOT NULL,
  totalInUseCount MEDIUMINT UNSIGNED NOT NULL,
  totalInUseTime MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_fm_nbi_lifecycle_instr (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  totalIncidentsSentToFm SMALLINT UNSIGNED NOT NULL,
  pmOnlyIncidentsSentToFm SMALLINT UNSIGNED NOT NULL,
  fmOnlyIncidentsSentTOFm SMALLINT UNSIGNED NOT NULL,
  fmAndPmIncidentsSentToFm SMALLINT UNSIGNED NOT NULL,
  INDEX fmnbiSiteTimeIdx(siteid,time)
 ) PARTITION BY RANGE ( TO_DAYS(time) )
 (
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
 );

CREATE TABLE enm_cm_crud_nbi (
 siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
 serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
 time DATETIME NOT NULL,
 getBaseOnlyCount MEDIUMINT UNSIGNED  NOT NULL,
 getBaseOnlyAsyncResponses MEDIUMINT UNSIGNED NOT NULL,
 getBaseOnlyTotalAvgTime SMALLINT UNSIGNED NOT NULL,
 getBaseOnlyTotalMaxTime SMALLINT UNSIGNED NOT NULL,
 getBaseOtherAllCount MEDIUMINT UNSIGNED NOT NULL,
 getBaseOtherAllAsyncResponses  MEDIUMINT UNSIGNED NOT NULL,
 getBaseOtherAllTotalAvgTime SMALLINT UNSIGNED NOT NULL,
 getBaseOtherAllTotalMaxTime SMALLINT UNSIGNED NOT NULL,
 deleteCount MEDIUMINT UNSIGNED NOT NULL,
 deleteAsyncResponses  MEDIUMINT UNSIGNED NOT NULL,
 deleteTotalAvgTime SMALLINT UNSIGNED NOT NULL,
 deleteTotalMaxTime SMALLINT UNSIGNED NOT NULL,
 patch3gppJPatchCount MEDIUMINT UNSIGNED NOT NULL,
 patch3gppJPatchAsyncResponses  MEDIUMINT UNSIGNED NOT NULL,
 patch3gppJPatchTotalAvgTime SMALLINT UNSIGNED NOT NULL,
 patch3gppJPatchTotalMaxTime SMALLINT UNSIGNED NOT NULL,
 patchJPatchCount MEDIUMINT UNSIGNED NOT NULL,
 patchJPatchAsyncResponses MEDIUMINT UNSIGNED NOT NULL,
 patchJPatchTotalAvgTime SMALLINT UNSIGNED NOT NULL,
 patchJPatchTotalMaxTime SMALLINT UNSIGNED NOT NULL,
 postCount MEDIUMINT UNSIGNED NOT NULL,
 postAsyncResponses MEDIUMINT UNSIGNED NOT NULL,
 postTotalAvgTime SMALLINT UNSIGNED NOT NULL,
 postTotalMaxTime SMALLINT UNSIGNED NOT NULL,
 putCreateCount MEDIUMINT UNSIGNED NOT NULL,
 putCreateAsyncResponses MEDIUMINT UNSIGNED NOT NULL,
 putCreateTotalAvgTime SMALLINT UNSIGNED NOT NULL,
 putCreateTotalMaxTime SMALLINT UNSIGNED NOT NULL,
 putModifyCount MEDIUMINT UNSIGNED NOT NULL,
 putModifyAsyncResponses MEDIUMINT UNSIGNED NOT NULL,
 putModifyTotalAvgTime SMALLINT UNSIGNED NOT NULL,
 putModifyTotalMaxTime SMALLINT UNSIGNED NOT NULL,
 getBaseOnlyExecAvgTime MEDIUMINT UNSIGNED NOT NULL,
 getBaseOnlyExecMaxTime MEDIUMINT UNSIGNED NOT NULL,
 getBaseOtherAllExecAvgTime MEDIUMINT UNSIGNED NOT NULL,
 getBaseOtherAllExecMaxTime MEDIUMINT UNSIGNED NOT NULL,
 deleteExecAvgTime MEDIUMINT UNSIGNED NOT NULL,
 deleteExecMaxTime MEDIUMINT UNSIGNED NOT NULL,
 patch3gppJPatchExecAvgTime MEDIUMINT UNSIGNED NOT NULL,
 patch3gppJPatchExecMaxTime MEDIUMINT UNSIGNED NOT NULL,
 patchJPatchExecAvgTime MEDIUMINT UNSIGNED NOT NULL,
 patchJPatchExecMaxTime MEDIUMINT UNSIGNED NOT NULL,
 postExecAvgTime MEDIUMINT UNSIGNED NOT NULL,
 postExecMaxTime MEDIUMINT UNSIGNED NOT NULL,
 putCreateExecAvgTime MEDIUMINT UNSIGNED NOT NULL,
 putCreateExecMaxTime MEDIUMINT UNSIGNED NOT NULL,
 putModifyExecAvgTime MEDIUMINT UNSIGNED NOT NULL,
 putModifyExecMaxTime MEDIUMINT UNSIGNED NOT NULL,
 INDEX serverIdTime(serverid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_windows_type_id_mapping (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(20) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

CREATE TABLE eniq_ocs_published_application (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  biWirc SMALLINT UNSIGNED NULL,
  biUdt SMALLINT UNSIGNED NULL,
  biIdt SMALLINT UNSIGNED NULL,
  netanAnalyst SMALLINT UNSIGNED NULL,
  INDEX siteidTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_restconf_nbi (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  userIndex SMALLINT UNSIGNED NOT NULL,
  resStatus ENUM('SUCCESS', 'FAILURE') NOT NULL COLLATE latin1_general_cs,
  reqMethod ENUM('OPTIONS', 'HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE') NOT NULL COLLATE latin1_general_cs,
  rootModuleName ENUM('ietf-te:te', 'ietf-restconf-monitoring:restconf-state', 'ietf-subscribed-notifications:streams',
   'ietf-subscribed-notifications:filters', 'ietf-subscribed-notifications:subscriptions',
   'ietf-network-state:networks', 'ietf-l3vpn-svc:l3vpn-svc', 'ietf-eth-tran-service:etht-svc',
   'ietf-trans-client-service:client-svc', 'ietf-interfaces:interfaces', 'ietf-interfaces:interfaces-state',
   'ietf-l2vpn-svc:l2vpn-svc', 'ietf-network:networks', 'ietf-microwave-radio-link:radio-link-protection-groups',
   'ietf-microwave-radio-link:xpic-pairs', 'ietf-microwave-radio-link:mimo-groups') NOT NULL COLLATE latin1_general_cs,
  reqType ENUM('DATA', 'OPERATION', 'HELLO', 'STREAM', 'MODULE') NOT NULL COLLATE latin1_general_cs,
  totalMOsUpdated SMALLINT UNSIGNED,
  totalMOsDeleted SMALLINT UNSIGNED,
  totalMOsRead SMALLINT UNSIGNED,
  totalMOsCreated SMALLINT UNSIGNED,
  totalReqDataSize SMALLINT UNSIGNED,
  totalResDataSize SMALLINT UNSIGNED,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_server_info (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  type ENUM('OCS_ADDS', 'OCS_CCS', 'OCS_VDA', 'OCS_WITHOUT_CITRIX', 'BIS', 'NetAnServer', 'ENIQ', 'ACCESSNAS') NOT NULL,
  INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE logtransformer (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  events_in MEDIUMINT UNSIGNED,
  events_out MEDIUMINT UNSIGNED,
  events_duration_millis MEDIUMINT UNSIGNED,
  pipelines_events_in MEDIUMINT UNSIGNED,
  pipelines_events_out MEDIUMINT UNSIGNED,
  pipelines_events_duration_millis SMALLINT UNSIGNED,
  pipelines_events_queue_push_duration SMALLINT UNSIGNED,
  pipelines_queue_size_mb SMALLINT UNSIGNED,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_flow_asu_group_summary (
  siteId SMALLINT(5) UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  flowExecutionName VARCHAR(128) NOT NULL DEFAULT '',
  sequenceOrder SMALLINT UNSIGNED NOT NULL,
  totalNodes SMALLINT UNSIGNED NOT NULL,
  nodesSuccessful SMALLINT UNSIGNED NOT NULL,
  totalFailed SMALLINT UNSIGNED NOT NULL,
  upgradedNodeswithWarnings SMALLINT UNSIGNED NOT NULL,
  startedInMaintenanceWindow SMALLINT UNSIGNED NOT NULL,
  timeTaken MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteId,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ecim_notif_supervision_instr (
  siteId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  createNotificationsCount SMALLINT UNSIGNED NOT NULL,
  deleteNotificationsCount SMALLINT UNSIGNED NOT NULL,
  updateNotificationsCount SMALLINT UNSIGNED NOT NULL,
  failedCreateNotificationsCount SMALLINT UNSIGNED NOT NULL,
  failedDeleteNotificationsCount SMALLINT UNSIGNED NOT NULL,
  failedUpdateNotificationsCount SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx (siteId,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE table enm_filetransfer_connections (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  connectionType ENUM('SFTP', 'FTP', 'FTPES') NOT NULL COLLATE latin1_general_cs,
  numOfSessions SMALLINT UNSIGNED NOT NULL,
  readSize MEDIUMINT UNSIGNED NOT NULL,
  writeSize MEDIUMINT UNSIGNED NOT NULL,
  usecase ENUM('PM', 'SHM-BACKUP', 'SHM-SOFTWARE', 'SHM-LICENSE', 'CERTIFICATE', 'LAAD', 'AI', 'ORADIO', 'OTHERS', 'UL_SPECTRUM', 'NETLOG') NOT NULL COLLATE latin1_general_cs,
  successSessionCount SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_ebsn_fdn_mos (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  numberOfNRCUCP_GNBCUCPFunction_Mos SMALLINT UNSIGNED NOT NULL,
  numberOfNRCUCP_NRCellCU_Mos SMALLINT UNSIGNED NOT NULL,
  numberOfNRCUCP_GNBFunction_Mos SMALLINT UNSIGNED NOT NULL,
  numberOfNRCUUP_LINK_Mos SMALLINT UNSIGNED NOT NULL,
  numberOfNRDU_GNBDUFunction_Mos SMALLINT UNSIGNED NOT NULL,
  numberOfNRDU_NRCellDU_Mos SMALLINT UNSIGNED NOT NULL,
  numberOfNR_EUTRANCellRelation_Mos SMALLINT UNSIGNED NOT NULL,
  numberOfNR_NRCellRelation_Mos SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_pm_file_del_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  datatypeid SMALLINT UNSIGNED COMMENT "REFERENCES enm_pmic_datatypes(id)",
  filesToDelete MEDIUMINT UNSIGNED NOT NULL,
  filesDeletedFS MEDIUMINT UNSIGNED NOT NULL,
  filesDeletedFLSDB MEDIUMINT UNSIGNED NOT NULL,
  timeToDelete MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_pm_orphan_file_del_stats (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  type ENUM( 'StatisticalSubscription', 'GpehSubscription', 'MtrSubscription', 'BSCRecordingsSubscription',
    'UeTraceSubscription', 'CtumSubscription', 'CelltraceSubscription', 'EbmSubscription',
    'CellTrafficSubscription', 'UetrSubscription', 'EbsSubscription', 'ProductDataSubscription' ) NOT NULL COLLATE latin1_general_cs,
  deletedFiles MEDIUMINT UNSIGNED NOT NULL,
  timeToDelete MEDIUMINT UNSIGNED NOT NULL,
  filter ENUM( 'PFD', 'OPFD' ) DEFAULT 'PFD' COLLATE latin1_general_cs,
INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE table enm_mscmce_notification (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  receivedNotificationsCount SMALLINT UNSIGNED NOT NULL,
  processedNotificationsCount SMALLINT UNSIGNED NOT NULL,
  discardedNotificationsCount SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE eniq_IBS_techpack_id_mapping (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  techPackName VARCHAR(100) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(techPackName),
  PRIMARY KEY(id)
);

CREATE TABLE eniq_IBS_loaderset_id_mapping (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  loaderSetName VARCHAR(60) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(loaderSetName),
  PRIMARY KEY(id)
);

CREATE TABLE eniq_IBS_error_loaderset (
  timeStamp DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  techpackId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_IBS_techpack_id_mapping(id)",
  loaderSetId SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES eniq_IBS_loaderset_id_mapping(id)",
  INDEX siteIdDateIdx(siteid,timeStamp)
) PARTITION BY RANGE ( TO_DAYS(timeStamp) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_bulk_import_ui (
  jobId MEDIUMINT UNSIGNED NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  fileFormat ENUM('THREE_GPP', 'EDFF', 'JSON') NOT NULL COLLATE latin1_general_cs,
  numberOfNodes MEDIUMINT UNSIGNED NOT NULL,
  invocation ENUM('validate', 'execute') NOT NULL COLLATE latin1_general_cs,
  validationPolicies SET('instance-validation', 'no-instance-validation', 'node-validation', 'no-node-validation') NOT NULL COLLATE latin1_general_cs,
  executionPolicies SET('sequential', 'parallel', 'STOP', 'NODE', 'OPERATION') NOT NULL COLLATE latin1_general_cs,
  createOperations MEDIUMINT UNSIGNED NOT NULL,
  deleteOperations MEDIUMINT UNSIGNED NOT NULL,
  updateOperations MEDIUMINT UNSIGNED NOT NULL,
  actionOperations MEDIUMINT UNSIGNED NOT NULL,
  status ENUM('validated', 'executed', 'audited', 'parsed', 'created', 'cancelled') NOT NULL COLLATE latin1_general_cs,
  elapsedTime MEDIUMINT UNSIGNED NOT NULL,
  mosProcessed MEDIUMINT UNSIGNED NOT NULL,
  validCount MEDIUMINT UNSIGNED NOT NULL,
  invalidCount MEDIUMINT UNSIGNED NOT NULL,
  executedCount MEDIUMINT UNSIGNED NOT NULL,
  executionErrorCount MEDIUMINT UNSIGNED NOT NULL,
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_plms_statistics (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  totalNumberOfDiscoveredLinks MEDIUMINT UNSIGNED NOT NULL,
  totalNumberOfNotDiscoveredLinks MEDIUMINT UNSIGNED NOT NULL,
  totalNumberOfDefinedLinks MEDIUMINT UNSIGNED NOT NULL,
  totalNumberOfUndefinedLinks MEDIUMINT UNSIGNED NOT NULL,
  totalNumberOfPendingLinks MEDIUMINT UNSIGNED NOT NULL,
  totalNumberOfPhysicalLinks MEDIUMINT UNSIGNED NOT NULL,
  totalNumberOfLogicalLinks MEDIUMINT UNSIGNED NOT NULL,
  totalNumberOfUnKnownLinks MEDIUMINT UNSIGNED NOT NULL,
  totalNumberOfLinks MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

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
   'SSO_ENABLE', 'SSO_DISABLE', 'SSO_GET', 'LDAP_RENEW', 'DELETE_SSH_KEY' ) NOT NULL COLLATE latin1_general_cs,
  jobNumWorkflows SMALLINT UNSIGNED NOT NULL,
  jobNumSuccessWorkflows SMALLINT UNSIGNED NOT NULL,
  jobNumErrorWorkflows SMALLINT UNSIGNED NOT NULL,
  jobMinSuccessWorkflowsDuration SMALLINT UNSIGNED,
  jobMaxSuccessWorkflowsDuration SMALLINT UNSIGNED,
  jobAvgSuccessWorkflowsDuration SMALLINT UNSIGNED,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_pm_file_access_nbi (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  apacheAccessesTotal SMALLINT UNSIGNED NOT NULL,
  apacheSentKilobytesTotal MEDIUMINT UNSIGNED NOT NULL,
  apacheCpuload SMALLINT UNSIGNED NOT NULL,
  apacheWorkersStateValueIdle SMALLINT UNSIGNED,
  apacheWorkersStateValueBusy SMALLINT UNSIGNED,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_nginx_path (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL COLLATE latin1_general_cs,
  UNIQUE INDEX nameIdx(name),
  PRIMARY KEY(id)
);

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
 'Flex Counter', 'Flex+PDF Counter', 'Custom KPI') COLLATE latin1_general_cs,
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
 'DynCounter', 'Flex Counter', 'Flex+PDF Counter', 'Custom KPI') COLLATE latin1_general_cs,
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
 UNIQUE INDEX nameIdx(processName),
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

CREATE TABLE enm_cm_subscriptions_nbi (
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
  successfulPostSubscriptions SMALLINT UNSIGNED NOT NULL,
  failedPostSubscriptions SMALLINT UNSIGNED NOT NULL,
  successfulSubscriptionViews SMALLINT UNSIGNED NOT NULL,
  failedSubscriptionViews SMALLINT UNSIGNED NOT NULL,
  successfulSubscriptionDeletion SMALLINT UNSIGNED NOT NULL,
  failedSubscriptionDeletion SMALLINT UNSIGNED NOT NULL,
  successfulViewAllSubscriptions SMALLINT UNSIGNED NOT NULL,
  failedViewAllSubscriptions SMALLINT UNSIGNED NOT NULL,
  successfulContinuousHeartbeatRequests SMALLINT UNSIGNED,
  failedContinuousHeartbeatRequests SMALLINT UNSIGNED,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE enm_cm_subscribed_events_nbi (
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

CREATE TABLE enm_neo4j_orphan_mo_count (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  time DATETIME NOT NULL,
  count SMALLINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

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

CREATE TABLE enm_cm_total_notifications (
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  time DATETIME NOT NULL,
  received MEDIUMINT UNSIGNED NOT NULL,
  processed MEDIUMINT UNSIGNED NOT NULL,
  INDEX siteTimeIdx(siteid,time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

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

CREATE TABLE server_availability (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES sites(id)",
  serverid INT UNSIGNED NOT NULL COMMENT "REFERENCES servers(id)",
  INDEX siteDateServIdx( siteid, date, serverid )
) PARTITION BY RANGE ( TO_DAYS( date ) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

