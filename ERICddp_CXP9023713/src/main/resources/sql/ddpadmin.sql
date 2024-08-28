-- ddpadmin database schema

CREATE TABLE ftpusers (
    siteid SMALLINT UNSIGNED NOT NULL UNIQUE,
    userid VARCHAR(255) NOT NULL,
    passwd VARCHAR(80) NOT NULL,
    uid SMALLINT UNSIGNED NOT NULL DEFAULT 501,
    gid SMALLINT UNSIGNED NOT NULL DEFAULT 501,
    homedir VARCHAR(255) NOT NULL,
    shell CHAR(13) NOT NULL DEFAULT "/sbin/nologin",
    UNIQUE KEY userid (userid)
);

CREATE TABLE file_processing (
    file     VARCHAR(512) NOT NULL,
    date     DATE NOT NULL,
    deltaindex TINYINT UNSIGNED DEFAULT 0,
    uploaded DATETIME NOT NULL,
    siteid    SMALLINT UNSIGNED NOT NULL,
    site     VARCHAR(512) NOT NULL,
    sitetype VARCHAR(16) NOT NULL,
    priority TINYINT NOT NULL DEFAULT 0,
    n_makestats TINYINT NOT NULL DEFAULT 0,
    workerid TINYINT COMMENT "REFERENCES workers(id)",
    starttime DATETIME
);

CREATE TABLE file_parked (
    file     VARCHAR(512) NOT NULL,
    date     DATE NOT NULL,
    deltaindex TINYINT UNSIGNED DEFAULT 0,
    uploaded DATETIME NOT NULL,
    siteid    SMALLINT UNSIGNED NOT NULL,
    site     VARCHAR(512) NOT NULL,
    sitetype VARCHAR(16) NOT NULL,
    priority TINYINT NOT NULL DEFAULT 0,
    n_makestats TINYINT NOT NULL DEFAULT 0,
    workerid TINYINT COMMENT "REFERENCES workers(id)",
    starttime DATETIME
);

CREATE TABLE file_processed (
    siteid    SMALLINT UNSIGNED NOT NULL,
    file_date     DATE NOT NULL,
    archive_index TINYINT UNSIGNED DEFAULT 0,
    INDEX siteDateIdx(siteid,file_date)
) PARTITION BY RANGE ( TO_DAYS(file_date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE workers (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(32) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    max_jobs TINYINT UNSIGNED
);

CREATE TABLE ddp_makestats
(
    siteid    SMALLINT UNSIGNED NOT NULL,
    filesize  INT UNSIGNED NOT NULL,
    filedate  DATE NOT NULL,
    uploaded  DATETIME NOT NULL,
    beginproc DATETIME NOT NULL,
    endproc   DATETIME,
    error VARCHAR(256),
    INDEX siteDateIdx(siteid,filedate)
) PARTITION BY RANGE ( TO_DAYS(filedate) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ddpusers (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    signum VARCHAR(30) NOT NULL,
    mysql_passwd VARCHAR(41),
    use_sql BOOLEAN NOT NULL DEFAULT FALSE,
    passwd VARCHAR(256) NOT NULL,
    get_upgrade_emails BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE KEY signum (signum)
);

INSERT INTO ddpusers (signum,passwd) VALUES ( 'ddp', MD5('ddp_passwd') );

CREATE TABLE ddpuser_group
(
    signum VARCHAR(30) NOT NULL,
    grp ENUM('ddpadm','accadm','usage','watchadm','cpuadm','upgrade','linkadm') NOT NULL,
    UNIQUE KEY sg (signum,grp)
);

CREATE TABLE ddpusers_tmp
(
    signum VARCHAR(30) NOT NULL,
    passwd VARCHAR(256) NOT NULL,
    mysql_passwd VARCHAR(41),
    use_sql BOOLEAN NOT NULL DEFAULT FALSE,
    olduid SMALLINT UNSIGNED,
    actcode VARCHAR(256),
    primary key (signum)
);

CREATE TABLE ddp_page_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT ""
);

CREATE TABLE ddp_page_exec
(
    time DATETIME NOT NULL,
    pageid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ddp_page_names(id)",
    duration DECIMAL(7,3) NOT NULL,
    INDEX timeIdx(time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ddp_table_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT ""
);

CREATE TABLE ddp_table_stats
(
    date DATE NOT NULL,
    tableid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ddp_table_names(id)",
    data BIGINT UNSIGNED NOT NULL,
    idx BIGINT UNSIGNED NOT NULL,
    avglen SMALLINT UNSIGNED NOT NULL,
    type ENUM('statsdb', 'ddpadmin')  NOT NULL DEFAULT 'statsdb' COLLATE latin1_general_cs,
    INDEX dateIdx(date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
    PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ddp_mysql_stats
(
    time DATETIME NOT NULL,
    bytes_sent INT UNSIGNED NOT NULL,
    bytes_received INT UNSIGNED NOT NULL,
    com_commit INT UNSIGNED NOT NULL,
    com_delete INT UNSIGNED NOT NULL,
    com_delete_multi INT UNSIGNED NOT NULL,
    com_insert INT UNSIGNED NOT NULL,
    com_insert_select INT UNSIGNED NOT NULL,
    com_load INT UNSIGNED NOT NULL,
    com_select INT UNSIGNED NOT NULL,
    com_set_option INT UNSIGNED NOT NULL,
    com_update INT UNSIGNED NOT NULL,
    created_tmp_disk_tables INT UNSIGNED NOT NULL,
    created_tmp_files INT UNSIGNED NOT NULL,
    created_tmp_tables INT UNSIGNED NOT NULL,
    opened_tables INT UNSIGNED NOT NULL,
    key_blocks_unused INT UNSIGNED NOT NULL,
    key_blocks_used INT UNSIGNED NOT NULL,
    key_read_requests INT UNSIGNED NOT NULL,
    key_reads INT UNSIGNED NOT NULL,
    key_write_requests INT UNSIGNED NOT NULL,
    key_writes INT UNSIGNED NOT NULL,
    qcache_hits INT UNSIGNED NOT NULL,
    questions INT UNSIGNED NOT NULL,
    select_full_join INT UNSIGNED NOT NULL,
    select_full_range_join INT UNSIGNED NOT NULL,
    select_range INT UNSIGNED NOT NULL,
    select_range_check INT UNSIGNED NOT NULL,
    select_scan INT UNSIGNED NOT NULL,
    sort_merge_passes INT UNSIGNED NOT NULL,
    sort_range INT UNSIGNED NOT NULL,
    sort_rows INT UNSIGNED NOT NULL,
    sort_scan INT UNSIGNED NOT NULL,
    slow_queries INT UNSIGNED NOT NULL,
    table_locks_immediate INT UNSIGNED NOT NULL,
    table_locks_waited INT UNSIGNED NOT NULL,
    uptime SMALLINT UNSIGNED NOT NULL,
    innodb_buffer_pool_pages_dirty INT UNSIGNED,
    innodb_buffer_pool_read_ahead INT UNSIGNED,
    innodb_buffer_pool_read_ahead_evicted INT UNSIGNED,
    innodb_buffer_pool_read_ahead_rnd INT UNSIGNED,
    innodb_buffer_pool_read_requests INT UNSIGNED,
    innodb_buffer_pool_reads INT UNSIGNED,
    innodb_buffer_pool_write_requests INT UNSIGNED,
    innodb_data_read INT UNSIGNED,
    innodb_data_reads INT UNSIGNED,
    innodb_data_writes INT UNSIGNED,
    innodb_data_written INT UNSIGNED,
    innodb_pages_read INT UNSIGNED,
    innodb_pages_written INT UNSIGNED,
    innodb_rows_deleted INT UNSIGNED,
    innodb_rows_inserted INT UNSIGNED,
    innodb_rows_read INT UNSIGNED,
    innodb_rows_updated INT UNSIGNED,
    INDEX timeIdx(time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ddp_script_names
(
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT ""
);

CREATE TABLE ddp_script_exec
(
    date DATE NOT NULL,
    scriptid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ddp_script_names(id)",
    execs INT NOT NULL,
    duration INT NOT NULL,
    INDEX dateIdx(date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ddp_cache (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL,
    component VARCHAR(32) NOT NULL COLLATE latin1_general_cs,
    data MEDIUMTEXT NOT NULL COLLATE latin1_general_cs,
    INDEX cacheIndex(date,siteid)
);

CREATE TABLE healthcheck_results (
    date DATE NOT NULL,
    siteid SMALLINT UNSIGNED NOT NULL,
    reportid SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    data MEDIUMTEXT NOT NULL COLLATE latin1_general_cs,
    generatedAt DATETIME,
    INDEX idx(date,siteid)
) PARTITION BY RANGE ( TO_DAYS(date) ) (
     PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE db_replicas (
    host VARCHAR(255) NOT NULL,
    port SMALLINT UNSIGNED NOT NULL,
    dir  VARCHAR(255) NOT NULL,
    smf  VARCHAR(64),
    cert VARCHAR(64)
);

CREATE TABLE report_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    userid SMALLINT UNSIGNED NOT NULL,
    template TEXT NOT NULL COLLATE latin1_general_cs,
    name VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
    description TEXT NOT NULL COLLATE latin1_general_cs,
    status ENUM('public','private') NOT NULL COLLATE latin1_general_cs DEFAULT 'private',
    UNIQUE INDEX useridName (userid,name),
    PRIMARY KEY (id)
);

CREATE TABLE help_bubble_texts (
    help_id VARCHAR(127) NOT NULL,
    content  VARCHAR(8191) NOT NULL,
    PRIMARY KEY(help_id)
);

CREATE TABLE site_accessgroups (
    siteid SMALLINT UNSIGNED NOT NULL,
    grp VARCHAR(257) NOT NULL COLLATE latin1_general_cs,
    INDEX siteIdx(siteid),
    UNIQUE INDEX siteGrpIdx(siteid,grp)
);

CREATE TABLE ddp_alert_previous_results (
  time DATETIME NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL,
  reportid SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  data MEDIUMTEXT NOT NULL COLLATE latin1_general_cs,
  INDEX idx(siteid)
);

CREATE TABLE ddp_alert_subscriptions (
  siteid SMALLINT UNSIGNED NOT NULL,
  signum VARCHAR(30) NOT NULL,
  reportid SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx(siteid)
);

CREATE TABLE ddp_alert_subscriber_emails (
  signum VARCHAR(30) NOT NULL,
  email VARCHAR(128) NOT NULL COLLATE latin1_general_cs
);

CREATE TABLE upgrade_history (
   from_version VARCHAR(20) NOT NULL,
   to_version VARCHAR(20) NOT NULL,
   start_time DATETIME NOT NULL,
   end_time DATETIME,
   status ENUM('Success', 'Failed', 'In Progress') NOT NULL DEFAULT 'Failed' COLLATE latin1_general_cs,
   initiator varchar(100)
);

CREATE TABLE ddp_custom_reports (
   id INT AUTO_INCREMENT PRIMARY KEY,
   site_type ENUM('OSS','ENIQ','TOR','DDP','UNDEFINED','EO','GENERIC') NOT NULL COLLATE latin1_general_cs,
   access ENUM('PUBLIC','PRIVATE') NOT NULL COLLATE latin1_general_cs DEFAULT 'PRIVATE',
   signum VARCHAR(30) NOT NULL  COLLATE latin1_general_cs,
   reportname VARCHAR(255) NOT NULL COLLATE latin1_general_cs,
   content MEDIUMTEXT NOT NULL COLLATE latin1_general_cs,
   UNIQUE INDEX useridName (signum,reportname)
);

CREATE TABLE ddp_id_tables (
 date DATE NOT NULL,
 tableid SMALLINT UNSIGNED NOT NULL COMMENT "REFERENCES ddp_table_names(id)",
 datatype ENUM('tinyint','smallint','mediumint','int', 'bigint'),
 maxid BIGINT UNSIGNED NOT NULL,
 INDEX dateIdx(date)
);

CREATE TABLE ddp_report_display (
  siteid SMALLINT UNSIGNED NOT NULL,
  signum VARCHAR(30) NOT NULL,
  reportid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
  KEY idx (siteid)
);

CREATE TABLE ddp_certs (
 type ENUM('httpd', 'k8s-client', 'k8master_ca', 'repl-client-repladm-ddprepl') NOT NULL,
 notafter DATE NOT NULL
);

CREATE TABLE slow_queries (
  date DATE NOT NULL,
  count SMALLINT UNSIGNED NOT NULL,
  INDEX dateIdx(date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE external_store (
 siteid SMALLINT UNSIGNED NOT NULL,
 date DATE NOT NULL,
 fileindex SMALLINT UNSIGNED,
 INDEX siteDateIdx(siteid,date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE critical_errors (
  date DATE NOT NULL,
  siteid SMALLINT UNSIGNED NOT NULL,
  command VARCHAR(512) NOT NULL,
  INDEX dateIdx(date)
) PARTITION BY RANGE ( TO_DAYS(date) )
(
 PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ddp_maintenance_times (
  startTime  DATETIME NOT NULL,
  duration SMALLINT UNSIGNED NOT NULL,
  INDEX timeIdx(startTime)
) PARTITION BY RANGE ( TO_DAYS(startTime) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE ddp_old_procids (
    procid SMALLINT UNSIGNED NOT NULL,
    INDEX procIdIdx(procid)
);

CREATE TABLE repl_delay (
  time DATETIME NOT NULL,
  replica VARCHAR(64) NOT NULL,
  delay MEDIUMINT UNSIGNED NOT NULL,
  INDEX timeIdx(time)
) PARTITION BY RANGE ( TO_DAYS(time) )
(
  PARTITION QMAXVALUE VALUES LESS THAN MAXVALUE
);

CREATE TABLE site_options (
  siteid SMALLINT UNSIGNED NOT NULL,
  loadvm BOOLEAN NOT NULL DEFAULT FALSE,
   PRIMARY KEY(siteid)
);

CREATE TABLE ddp_links (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  siteid SMALLINT UNSIGNED NOT NULL,
  label VARCHAR(64) NOT NULL,
  link VARCHAR(256) NOT NULL,
  creator VARCHAR(30) NOT NULL,
  UNIQUE KEY k (siteid, label),
  PRIMARY KEY(id)
);

