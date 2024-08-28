
#CREATE DATABASE cs_isp;

GRANT ALL PRIVILEGES ON cs_isp.* TO 'statsadm'@'localhost' IDENTIFIED BY '_sadm' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON cs_isp.* TO 'statsadm'@'%' IDENTIFIED BY '_sadm' WITH GRANT OPTION;
GRANT FILE ON *.* TO 'cs_isp'@'localhost' IDENTIFIED BY '_sadm';

GRANT SELECT, CREATE TEMPORARY TABLES ON cs_isp.* TO 'cs'@'localhost' IDENTIFIED BY 'esp';
GRANT SELECT, CREATE TEMPORARY TABLES ON cs_isp.* TO 'cs'@'%' IDENTIFIED BY 'esp';

USE cs_isp;

CREATE TABLE sites 
(
	id SMALLINT UNSIGNED NOT NULL,
	name VARCHAR(30) NOT NULL,	
	PRIMARY KEY(id)
);

INSERT INTO sites ( id, name ) SELECT id, name from statsdb.sites where statsdb.sites.name = 'SmarTone_HongKong';
INSERT INTO sites ( id, name ) SELECT id, name from statsdb.sites where statsdb.sites.name = '3_Rome1';
INSERT INTO sites ( id, name ) SELECT id, name from statsdb.sites where statsdb.sites.name = '3_Rome2';
INSERT INTO sites ( id, name ) SELECT id, name from statsdb.sites where statsdb.sites.name = '3_Milan1';
INSERT INTO sites ( id, name ) SELECT id, name from statsdb.sites where statsdb.sites.name = '3_Milan2';
INSERT INTO sites ( id, name ) SELECT id, name from statsdb.sites where statsdb.sites.name = 'Tele2';
INSERT INTO sites ( id, name ) SELECT id, name from statsdb.sites where statsdb.sites.name = 'Hi3G_Sweden';

CREATE TABLE mc_group_names
(
	id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(30) NOT NULL,
	PRIMARY KEY(id)
);

INSERT INTO mc_group_names ( name ) VALUES ('FM');
INSERT INTO mc_group_names ( name ) VALUES ('PM');
INSERT INTO mc_group_names ( name ) VALUES ('CM');


CREATE TABLE mc_grps
(
	grpid SMALLINT UNSIGNED NOT NULL REFERENCES mc_group_names(id),
	mc_name VARCHAR(50) NOT NULL
);

select @grpid:= id from mc_group_names where name = 'FM';
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_asv_1');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_Cirpagent');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_DistributionServer');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_fmx');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_fmxgw');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_handler_1');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_ims');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_mibserver');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_nsc');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_ria');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_service');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'FM_supiproxy');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'fma_axeadaptation_1');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'fma_axeadaptation_APG40');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'fma_cirpman');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'fma_cirpman_ranag');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'fma_cirpman_rbs_1');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'fma_cirpman_rnc');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'fma_snmpsmt');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'IMH_Common');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'IMH_FM_kernel');

select @grpid:= id from mc_group_names where name = 'PM';
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'pms_reg');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'pms_seg');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'sgw' );

select @grpid:= id from mc_group_names where name = 'CM';
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'ARNEServer');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'cms_nead_seg');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'cms_snad_reg');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'ONRM_CS');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'Region_CS');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'Segment_CS');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'wran_bcg');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'wran_pca');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'eam_common');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'eam_handlerAPG30');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'eam_handlerIp');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'eam_handlerMtp');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'eam_handlerText');
INSERT INTO mc_grps ( grpid, mc_name ) VALUES ( @grpid, 'eam_nrma');


CREATE VIEW pms_success AS 
 SELECT statsdb.pms_stats.date, cs_isp.sites.name as sitename, ROUND((collected * 100)/available) AS success_percent 
  FROM statsdb.pms_stats, cs_isp.sites 
   WHERE statsdb.pms_stats.siteid = cs_isp.sites.id and statsdb.pms_stats.siteid in (select id from cs_isp.sites);

CREATE VIEW mc_grp_restarts AS
 SELECT statsdb.mc_restarts.time, cs_isp.sites.name AS sitename, statsdb.mc_names.name as mcname, cs_isp.mc_group_names.name as grpname
  FROM statsdb.mc_restarts, cs_isp.sites, statsdb.mc_names, cs_isp.mc_group_names, cs_isp.mc_grps
   WHERE statsdb.mc_restarts.siteid = cs_isp.sites.id and statsdb.mc_restarts.siteid in (select id from cs_isp.sites) and
         statsdb.mc_names.name = cs_isp.mc_grps.mc_name and statsdb.mc_restarts.nameid = statsdb.mc_names.id and 
 	 cs_isp.mc_grps.grpid = cs_isp.mc_group_names.id;

CREATE VIEW rnc_sync_rate AS
 SELECT statsdb.nead_syncs.date as date, cs_isp.sites.name as sitename, ROUND(AVG(statsdb.nead_syncs.avgmo/statsdb.nead_syncs.avgtime)) as mo_per_sec
  FROM statsdb.nead_syncs, cs_isp.sites
   WHERE statsdb.nead_syncs.siteid = cs_isp.sites.id and statsdb.nead_syncs.siteid in (select id from cs_isp.sites) and
    statsdb.nead_syncs.netype = 'RNC'
   GROUP by statsdb.nead_syncs.date;

CREATE VIEW export_rate AS
 SELECT statsdb.export.start, cs_isp.sites.name as sitename, ROUND(statsdb.export.numMo / (unix_timestamp(statsdb.export.end)-unix_timestamp(statsdb.export.start))) as mo_per_sec
  FROM statsdb.export, cs_isp.sites  
  WHERE statsdb.export.siteid = cs_isp.sites.id and statsdb.export.siteid in (select id from cs_isp.sites) and
   statsdb.export.numMo > 0;
