package StatsCommon;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT    = qw(jpsShortName jpsAddShortName getMeInfoShort getRnsShort getShortNode readNe setStatsCommon_Debug incrRead incrWrite);

use Data::Dumper;
use strict;
use StatsDB;
use File::Basename;

our $neadClass = "UmtsNeadMain";
our $snadClass = "cif.nead.NeAdapterImpl cms.snad.regionserver.main.SnadMain";

our $StatsCommon_DEBUG = 0;

our %shortNames =
(
    "cif.cs.ConfigurationServer ONRM_CS" => "CsONRM",
    "cif.cs.ConfigurationServer Region_CS" => "CsReg",
    "cif.cs.ConfigurationServer SelfMgmtDomainMIBCs" => "CsSm",
    "cif.cs.ConfigurationServer ConfigService" => "ConfigService",
    "com.inprise.vbroker.naming.ExtFactory NameService" => "NameService",
    "fms.subscriptionhandling.segmentserver.SHSegmentMC" => "FmsShSeg",
    "eqh.segmentmc.SegmentMCServer" => "EqhSeg",
    "pms.segmentmc.SegmentMCServer" => "PmsSeg",
    "openfusion.orb.Service file:../localhost/NotificationService/NotificationService.xml" => "NotifService",
    "openfusion.orb.Service file:../localhost/ExternalNotificationService/ExternalNotificationService.xml" => "ExternNotifService",
    "cif.smssr.StartStopServerMain" => "StartStop",
    "rah.mcrah.RahMCServer" => "RAH",
    "cif.nead.NeAdapterImpl cif.smc.nead.NeAdapter_SM " => "NeadSm",
    "UmtsNeadMain" => "NEAD",
    "SnadMain" => "SNAD",
    "parser.SDMParser" => "SDMParser",
    "MAFMcServer"        => "MAF",
    "maf.test.Adjust" => "maf.adjust",
    "ActivitySupportMCServer" => "JobManager",
    "bin/fmack" => "fmack",
    "bin/set_alv_mo" => "set_alv_mo",
    "bin/fmlist" => "fmlist",
    "bin/imcmd" => "imcmd",
    "internal/imcmd" => "imcmd",
    '^com.ericsson.cepmediation.server.management.ProcessManager\s.*$' => 'ProcessManager',
    "^bcg.regionserver.cli.control.BCGCLI" => "[BCGCLI]",

    "bin/imui_cirp_form" => "imui_cirp_form",
    "arne.server.impl.xml.XmlExporter" => "arne.XmlExporter",
    "arne.server.impl.xml.XmlImporter" => "arne.XmlImporter",
    "arne.client.wizard\.WizardController" => "arne.WizardController",
    "bin/fma_sync" => "fma_sync",
    "sdm/bin/sdm_cfh" => "sdm_cfh",
    "sdm/etc/sdm_bcp" => "sdm_bcp",
    "^sdmu/bin/sdm_bcp_t" => "sdmu_bcp_t",
    "bin/CFHSend" => "CFHSend",
    'bin/CFHDelRouting' => '[CFHDelRouting]',
    'bin/CFHImRouting' => '[CFHImRouting]',
    'bin/cha' => '[cha]',
    "bin/ops_parser" => "ops_parser",
    '^/opt/ericsson/bin/ops_nui' => '[ops_nui]',
    "sdm/etc/sdm_generateBcpFile" => "sdm_generateBcpFile",
    "nms_axe_cha_cfh/bin/CFHConnect" => "cha_cfh/CFHConnect",
    "cif.ist.ped.ParameterEditor" => "ped",
    "cif.ist.cist.Controller" => "cist",
    "cif.sm.cli.SmCLI" => "smtool",

    "^cif.sm.cli.LogHandler" => "cif_sm/LogHandler",

    "^/opt/ericsson/bin/cfi" => "ericsson_cfi",
    "^cfi " => "ericsson_cfi",
    "^emt_cfi/bin/cfi" => "emt_cfi",
    "^emt_tgw/bin/emt_tgw_eaw" => "emt_tgw_eaw",
    "^emt_tgw/bin/emt_tgw_telnet_login.bin" => "emt_tgw_telnet_login.bin",

    "^ops.cfd.agent.OpsNui" => "OpsNui",
    "^ops.cfd.OpsGuiStarter" => "OpsGuiStarter",

    "^nms_rnr_pmr/bin_source/PMR_Parser" => "PMR_Parser",
    "^/opt/ericsson/bin/PMR_Parser" => "ericsson_PMR_Parser",
    "^nms_rnr_pmr/bin_source/PMR_Collector" => "PMR_Collector",
    "^/opt/ericsson/bin/PMR_Collector" => "ericsson_PMR_Collector",
    "^nms_rnr_pmr/bin_source/PMR_APGCollector" => "PMR_APGCollector",
    "^/opt/ericsson/bin/PMR_APGCollector" => "ericsson_PMR_APGCollector",
    "^nms_rnr_pmr/bin_source/PMR_ManagedMain" => "PMR_ManagedMain",
    "^nms_rnr_pmr/bin_source/PMR_UserInterface" => "PMR_UserInterface",

    "^bsmcm/bin/bsm_apg40_adjust" => "bsm_apg40_adjust",
    "^bsmcm/bin/bsm_adjust_task" => "bsm_adjust_task",
    "^bsmcm/bin/bcm_cmi" => "bsmcm/bcm_cmi",
    "^bsmcm/bin/bsm_adjust_controller" => "bsmcm/bsm_adjust_controller",
    "^bsmcm/bin/bsm_nrm" => "bsmcm/bsm_nrm",

    "^bss.sm.modules.cello.cli.CelloHwCli" => "bss.CelloHwCli",
    "^bss.sm.modules.axe.cli.AxeCli" => "bss.AxeCli",
    "^bss.sm.smo.client.Smo" => "smo/Client",
    "^bss.sm.modules.cello.cli.CelloCLI" => "bss.CelloCLI",
    "^bss.sm.modules.axe.distribute.CliClient" => "bss.CliClient",

    "^nms_smo_asm/bin/asm_fti_write" => "smo/asm_fti_write",
    "^nms_smo_asm/bin/asm_fti_read" => "smo/asm_fti_read",
    "^nms_smo_grsm/bin/asm_fti_write" => "smo_grsm/asm_fti_write",
    "^nms_smo_asm/bin/axs_axe_talk" => "smo_asm/axs_axe_talk",
    "^nms_smo_grsm/bin/axs_axe_talk" => "smo_grsm/axs_axe_talk",
    "^nms_smo_asm/lbin/axs_adj" => "smo_asm/axs_adj",
    "^nms_smo_asm/bin/AHWServer.exe" => "smo_asm/AHWServer.exe",

    "^nms_cif_cnam/bin/ttcshr" => "cnam_ttcshr",
    "^emt_emam/bin/emt_ttcshr" => "emam/emt_ttcshr",

    "^/var/opt/ericsson/fm/CXC_134_58/GSE_procedures/FSP_MML_sub" => "fm_GSE_procedures_FSP_MML_sub",
    '^\$FMX_HOME/GSE_procedures/FSP_Send_MML_sub' => 'fm_GSE_procedures_FSP_Send_MML_sub',
    "^/var/opt/ericsson/fm/CXC_134_58/mml-block-module/FSP_MML" => "fm_FSP_MML",
    "^/var/opt/ericsson/fm/CXC_134_58/mml-block-module/FSP_Send_MML" => "fm_FSP_Send_MML",
    '^\$FMX_HOME/WRX_procedures/get_topology_info' => 'fm_WRX_procedures_get_topology_info',
    '/usr/bin/perl /var/opt/ericsson/fm/CXC_134_58/GSE_procedures/ops_wrapper.pl' => 'perl fm_GSE_procedures/ops_wrapper.pl',

    "^fm/CXC_134_56/0/bin/ack" => "fm_ack",
    "^fm/CXC_134_54/0/bin/allist" => "fm/allist",
    "^fm/fmx/bin/fmxac_exec" => "fmxac_exec",
    '^/opt/ericsson/bin/fmxac_exec' => 'fmxac_exec',
    "^fm/CXC_134_58/0/bin/fmxac_exec" => "fmxac_exec",
    "^fm/CXC_134_54/0/bin/fm_context_d" => "fm/fm_context_d",
    "^fm/CXC_134_54/0/bin/fm_log_d" => "fm/fm_log_d",
    "^fm/CXC_134_54/0/bin/fm_nsc_d" => "fm/fm_nsc_d",
    "^fm/CXC_134_56/0/bin/fm_alarmlist_d" => "fm/fm_alarmlist_d",
    "^fm/CXC_134_54/0/bin/fmii_server IMH_alarm_server" => "fm/fmii_server IMH_alarm_server",
    "^fm/CXC_134_54/0/bin/fmai_server IMH_FMAI_server" => "fm/fmai_server IMH_FMAI_server",
    "^fm_core/bin/fm_mibserver" => "fm/fm_mibserver",
    "^fm/CXC_134_449/0/bin/fm_mibserver" => "fm_mibserver",
    "^fm_core/bin/list_msg" => "fm_list_msg",

    "^fm/CXC_134_58/0/bin/gwaction" => "fm/gwaction",
    "^fm.ria.main.ResourceInfoAdapter" => "fm/ResourceInfoAdapter",
    "^fm.ds.StartUp AdmHost" => "fm.ds.StartUp",
    "^fm.gui.alb.communication.ALBSearchExecutor" => "fm/gui/ALBSearchExecutor",
    "^fm.alb.communication.ALBSearchExecutor" => "fm/gui/ALBSearchExecutor",

    "^fm.gui.alv.ALVMain" => "fm.gui.ALVMain",
    "^fm.ims.client.ImsCli" => "fm/ImsCli",
    "^fm.ims.Server AdmHost" => "fm.ims.Server",
    "fma_handler_1 \S+ -f" => "[fma_handler_1]",

    "^cnai/bin/cna_import" => "cna_import",
    "^cnai/bin/cnai import " => "cnai import",
    "^cna/bin/cna_adjust" => "cna_adjust",
    "^cna/bin/cna_update" => "cna_update",
    "^cna/bin/cna_report" => "cna_report",
    '^cna_report ' => 'cna_report',
    "^cnai/bin/cna_export" => "cna_export",
    "^cna/bin/cna_ccheck" => "cna_ccheck",
    '^cna/bin/cna_job_scheduler' => 'cna_job_scheduler',
    '^cna_job_scheduler ' => 'cna_job_scheduler',
    "^/opt/ericsson/bin/cna_adjust" => "cna_adjust",
    "^/opt/ericsson/bin/cna_ccheck" => "cna_ccheck",
    "^/opt/ericsson/bin/cna_update" => "cna_update",
    "^cacad_main_printout_req cna_adjust " => "cacad_main_printout_req cna_adjust",
    "^cacad_main_importer " => "cacad_main_importer",

    "^nms_nio_ahm/bin/AHWServer.exe" => "nio_ahm/AHWServer",
    "^smia/bin/AuditServer" => "smia/AuditServer",

    "^com.ericsson.edd.d2bcm.D2Delta" => "edd.D2Delta",
    "^com.ericsson.edd.d2bcm.ExportFilter" => "edd.ExportFilter",
    "^com.ericsson.edd.ranos.gem.Gem" => "edd.Gem",

    "^nms_axe_cha_cha/nms_axe_cha_cfh/bin/cfh" => "axe_cha/cfh",

    "^nms_tss_server/bin/TSSAuthServer" => "TSSAuthServer",
    "^nms_tss_server/bin/TSSPWServer" => "TSSPWServer",
    "^nms_tss_server/bin/TSSBasicServ" => "TSSBasicServ",
    "^nms_tss_client/bin/pwAdmin" => "tss_pwAdmin",

    "^eba_ebsw/bin/weba" => "ebsw/weba",

    "^umts.cnos.config.activitysupport.batchinstaller.Installer" => "cnos.activitysupport.batchinstaller.Installer",

    "^nms_emt_winf_un/bin/winfiol.bin" => "winfiol.bin",

    "^bcmirp.bcmr99.BasicCMIRPMcServer" => "bcmirp.BasicCMIRPMcServer",

    "^/bin/bash /tmp/ist_run\." => "bash ist_run",

    "^ncms/bin/ncms_execas start CNA ADJUST " => "ncms/bin/ncms_execas start CNA ADJUST",
    "^ncms/bin/ncms_execas start BSM ADJUST " => "ncms/bin/ncms_execas start BSM ADJUST",
    "^ncms/bin/ncms_execas start CNA CC " => "ncms/bin/ncms_execas start CNA CC",
    "^ncms/bin/ncms_execas start CNA UPDATE " => "ncms/bin/ncms_execas start CNA UPDATE",

    "^rsh ebswservice cd " => "EBSW RSH",
    "^rsh rpmoservice cd " => "RPMO RSH",

    "^nms_tss_client/bin/targetGroupAdmin" => "tss/targetGroupAdmin",

    "^/bin/gzip -f OSS_Data_" => "OSS_Data gzip",
    "^\S+/bcp ebsdb" => "bcp ebsdb",

    "^/bin/sh /opt/ericsson/eniqm/bin/ENIQM_command" => "ENIQM_command",

    "^/bin/sh /opt/ericsson/nms_smo_asm/bin/axs_adj" => "axs_adj",

    "^/bin/sh /opt/ericsson/sdm/etc/sdm_mgwLoad_fork.sh" => "sdm_mgwLoad_fork.sh",
    "^/bin/sh /opt/ericsson/sdm/etc/sdm_getSubscriberVLR.sh" => "sdm_getSubscriberVLR.sh",
    "^/bin/sh /opt/ericsson/sdm/bin/SDM_getSTScounters.sh" => "sdm_getSubscriberVLR.sh",
    "^/bin/sh /opt/ericsson/sdm/bin/SDM_command" => "SDM_command",
    '^/bin/sh /opt/ericsson/sdm/etc/sdm_mscLoad_fork.sh' => "sdm_mscLoad_fork.sh",
    "^/bin/sh /bin/sh /opt/ericsson/sdm/etc/sdm_wppGsnBcp_fork.sh" => "sdm_wppGsnBcp_fork.sh",
    "^/bin/sh /bin/sh /opt/ericsson/sdm/etc/sdm_createBcpFile.sh" => "sdm_createBcpFile.sh",
    "^/bin/sh /opt/ericsson/sdmu/etc/ObjectLoad.sh" => "sdmu_ObjectLoad.sh",
    '^/bin/sh /opt/ericsson/sdmu/etc/sdm_utran_objectLoad.sh' => 'sdm_utran_objectLoad.sh',

    "^/bin/ksh /ericsson/syb/etc/error_wrapper" => "syb/error_wrapper",
    "^/bin/ksh /ericsson/syb/etc/thresh_wrapper" => "syb/thresh_wrapper",

    "copy_backup_trace.sh " => "copy_backup_trace",
    "copy_backup_trace_and_zip.sh " => "copy_backup_trace_and_zip",
    "^/bin/sh /net/ftp/export/files/eeicmuy/misc/concatTrace" => "concatTrace",

    "^nms_eam_eac/bin/esi_config " => "nms_eam_eac/bin/esi_config",
    "^nms_axe_cha_cha/nms_axe_cha_cfh/bin/CFHRouting " => "nms_axe_cha_cha/nms_axe_cha_cfh/bin/CFHRouting",
    "^cstest " => "cstest",
    "util/bin/dumpLog " => "util/dumpLog",

    "^emt_tgw_eaw " => "emt_tgw_eaw",

    "^com.ericsson.bytel.chili.XMLReader " => "ericsson.bytel.chili.XMLReader",
    "^lic.SUNW " => "Lic.SUNW",
    "^/usr/sbin/save" => "save",

    "moshell" => "[MOSHELL]",
    "^bin/scli.cbin" => "scli.cbin",
    "^/tmp/ist_run" => "[TMP_IST_RUN]",

    "^EventDumper -xml" => "EventDumper [XML]",

    "/opt/mv36/core/lib/mv36_gui_jam.jar" => "mv36_gui_jam",
    "^mv36_pen_evos" => "[mv36_pen_evos]",
    "^oracleems " => "oracleems",
    "^oracleeoems " => "oracleems",

    "^ftpd: " => "ftpd",

    "^net.atomique.ksar.Main" => "[ksar]",

    "^com.sun.javaws.Main" => "com.sun.javaws.Main",

    '^ctxlogin$' => '/opt/CTXSmf/slib/ctxlogin',
    '^-oForwardX11=no' => 'bin/scli.cbin',

    '^postgres:' => '[POSTGRES]',

    '^com.ericsson.oss.rps.cli.ExternalCommandProvider' => '[rps.cli]',
    '^com.ericsson.oss.utilities.grouping.cli.ExternalCommandProvider' => '[grouping.cli]',
    '^com.ericsson.oss.utilities.profiles.cli.ExternalCommandProvider' => '[profiles.cli]',
    '^com.ericsson.oss.utilities.templates.cli.ExternalCommandProvider' => '[templates.cli]',
    '^com.ericsson.oss.utilities.scheduling.cli.ExternalCommandProvider' => '[scheduling.cli]',

    'prop_cli' => 'prop_cli',
    'com.ericsson.oss.pci.cliclient.main.PCICliClient' => 'PCICliClient',
    'nms_pci_client/bin/pciselection.sh' => 'nms_pci_client/bin/pciselection.sh',

    "dbisql" => "dbisql",

    '^\d{10,10}\.[a-z]$' => "[AT_JOB]",

    '^%p -XX:HeapDumpPath=/ericsson/tor' => '[JBOSS]',

    '^-csh |^-sh |^-tcsh ' => '[DASH_CMD]'
);

our @OS_SHELL_LIST = ( 'sh', 'csh', 'ksh', 'bash', 'tcsh', 'pfsh' );
our @SCRIPTING_SHELL_LIST = ( 'expect', 'perl', 'python', 'python2', 'python2', 'ruby', 'python2.7', 'python3' );
our %SHELLS = ();
our %OS_SHELLS = ();
foreach my $shell ( @OS_SHELL_LIST ) {
    $SHELLS{$shell} = 1;
    $OS_SHELLS{$shell} = 1;
}
foreach my $shell ( @SCRIPTING_SHELL_LIST ) {
    $SHELLS{$shell} = 1;
}

our %stripArgs =
(
    'dtexec' => 1,
    'dtterm' => 1,
    'dtpad' => 1,
    'dtgreet' => 1,
    'dtfile' => 1,
    'dtmail' => 1,
    'dthelpview' => 1,
    'dtlogin' => 1,
    'cat' => 1,
    'bcp' => 1,
    'axs_axe_talk' => 1,
    'awk' => 1,
    'gawk' => 1,
    'pfiles' => 1,
    'telnet' => 1,
    'ping' => 1,
    'traceroute' => 1,
    'time' => 1,
    'less' => 1,
    'ls' => 1,
    'pmap' => 1,
    'more' => 1,
    "pkgadd" => 1,
    "pkgrm" => 1,
    'pkginstall' => 1,
    'vi' => 1,
    "gedit" => 1,
    'view' => 1,
    'vedit' => 1,
    'textedit' => 1,
    "n" => 1,
    "nedit" => 1,
    'mail' => 1,
    'tar' => 1,
    'gtar' => 1,
    "tee" => 1,
    'grep' => 1,
    'egrep' => 1,
    'du' => 1,
    'find' => 1,
    "ctxXtw" => 1,
    "ctxmsg" => 1,
    "ctxlogin" => 1,
    "zip" => 1,
    'gzip' => 1,
    'gunzip' => 1,
    "xterm" => 1,
    "util.pm.PerfMon" => 1,
    "util.iubcc.wal.IubCC" => 1,
    "util.mibutil.MibUtil" => 1,
    "util.pa.PaCli" => 1,
    "truss" => 1,
    'dtrace' => 1,
    "tail" => 1,
    "SunAwtRobot" => 1,
    "ssh" => 1,
    "ftp" => 1,
    "snoop" => 1,
    "sed" => 1,
    "rsh" => 1,
    'rcp' => 1,
    'scp' => 1,
    "rlogin" => 1,
    "rm" => 1,
    'cp' => 1,
    "mv" => 1,
    "prstat" => 1,
    "vxassist" => 1,
    "prop_necom" => 1,
    "jconsole" => 1,
    "login" => 1,
    "mdb" => 1,
    "man" => 1,
    "top" => 1,
    'mibiisa' => 1,
    'vbackup' => 1,
    'ln' => 1,
    'vxvol' => 1,
    'vxplex' => 1,
    'vxdump' => 1,
    'vxstat' => 1,
    'bpbkar' => 1,
    'bpbkar32' => 1,
    'bphdb' => 1,
    'snmpXdmid' => 1,
    'tqusrprb' => 1,
    'iostat' => 1,
    'vmstat' => 1,
    'script' => 1,
    'nawk' => 1,
    'wget' => 1,
    'mozilla-bin' => 1,
    'mozilla' => 1,
    'netscape.bin' => 1,
    '.netscape.bin' => 1,
    'sybmultbuf' => 1,
    'isql' => 1,
    'cmd_bsc' => 1,
    'cmd_hlr' => 1,
    'cmd_msc' => 1,
    'cmd_paramete' => 1,
    'sftp5.cbin' => 1,
    'sleep' => 1,
    'FSP_Send_MML' => 1,
    'FSP_MML' => 1,
    'SGwInteractWithNe' => 1,
    'cna_cdm2cnai' => 1,
    'EAServer' => 1,
    'aos_top_tmcmain' => 1,
    'aos_top_tem' => 1,
    'CFHConnect' => 1,
    'sybase_info' => 1,
    'Xvnc' => 1,
    'ufsdump' => 1,
    'sort' => 1,
    'nbpr' => 1,
    'nbpl' => 1,
    'nbdiag' => 1,
    'nbheul' => 1,
    'oss.rndbi.parser.MrrExportToBcp' => 1,
    'mixer_applet2' => 1,
    'gnome-settings-daemon' => 1,
    'clock-applet' => 1,
    'Xgo' => 1,
    'bcgtool.sh' => 1,
    'sendStats' => 1,
    'cna_cdmadjust' => 1,
    'cif.ist.Controller' => 1,
    'autocheck' => 1,
    'Xorg' => 1,
    'patchadd' => 1,
    'pkgremove' => 1,
    'wnck-applet' => 1,
    'nautilus-throbber' => 1,
    'jdshelp-server' => 1,
    'gnome-vfs-daemon' => 1,
    'gnome-netstatus-applet' => 1,
    'firefox-bin' => 1,
    'cna_cdmprogressmon' => 1,
    'cna_cdmexport' => 1,
    'cna_cdmadjust' => 1,
    'mv36_orb_conet' => 1,
    'FMBA_update_statistics' => 1,
    'scli.bin' => 1,
    'pstack' => 1,
    'metacity' => 1,
    'gnuplot' => 1,
    'fptest' => 1,
    'blm' => 1,
    'Xsun' => 1,
    'lupop' => 1,
    'lumake' => 1,
    'lucreate' => 1,
    'lucopy' => 1,
    'jmap' => 1,
    'NE_CHSMX' => 1,
    'NE_AHSMX' => 1,
    'mv36_sys_jaws' => 1,
    'fmxac_exec' => 1,
    'gnome-terminal' => 1,
    'pkgserv' => 1,
    'proftpd:' => 1,
    'tckw.emlauncher.EMLauncher' => 1,
    'SDO_ntp_master_parse.sh' => 1,
    'sshd:' => 1,
    'create_snapshots.bsh' => 1,
    'nascli' => 1,
    'com.ericsson.netsim.netsimgui.NetsimUI' => 1,
    'com.zerog.lax.LAX' => 1,
    'ExtractData' => 1,
    'ldapmodify' => 1,
    'sftp' => 1,
    'snmpwalk' => 1,
    'snmpget' => 1,
    'pacli' => 1,
    'bulkcm' => 1,
    'parseCsLibEventLog' => 1,
    'bulkcmperf' => 1,
    'naviseccli' => 1,
    'jstack' => 1,
    'dbtool' => 1,
    'emc_snap.bsh' => 1,
    'create_snapshots.bsh' => 1,
    '7z' => 1,
    '7za' => 1,
    'cna_im_interface' => 1,
    'fm_context_d' => 1,
    'in.telnetd:' => 1,
    'nsi.parser.CompositeParser' => 1,
    'kstat' => 1,
    'mobatch' => 1,
    'amosbatch' => 1,
    'FMBA_reorg_compact' => 1,
    'dstack_manage_amos.sh' => 1,
    'SDO_AXE' => 1,
    'rsync' => 1,
    'PDMTransfer' => 1,
    'bmrsavecfg' => 1,
    'charpick_applet2' => 1,
    'gwaction' => 1,
    'roleAdmin' => 1,
    'Parser.pl' => 1,
    'gsm_synch' => 1,
    'bpbrm' => 1,
    'dtcm' => 1,
    'at-spi-registryd' => 1,
    'CalculatePercentile' => 1,
    'cmdform.CmdForm' => 1,
    'cnacmi' => 1,
    'cnai' => 1,
    'sendmail:' => 1,
    'cnamdcode.sh' => 1,
    'tcku.emlauncher.EMLauncher' => 1,
    'sun.plugin2.main.client.PluginMain' => 1,
    'se.ericsson.cello.configtestclient.SimpleMoBrowser' => 1,
    'cfh' => 1,
    'CFHRouting' => 1,
    'lu_report_progress' => 1,
    'eaw' => 1,
    'emt_tgw_eaw' => 1,
    'cfi' => 1,
    'import.sh' => 1,
    'pwAdmin' => 1,
    'smocpp' => 1,
    'allist' => 1,
    'allog' => 1,
    'als_search' => 1,
    'RLdumpBSS' => 1,
    'se.ericsson.security.starter.Starter' => 1,
    'org.hyperic.hq.hqapi1.tools.Shell' => 1,
    'com.att.nse.mbbt.ericlte.nrt_arv.AgentMain' => 1,
    'com.att.nse.mbbt.ericlte.nrt_arv.AgtMmeMain' => 1,
    'userAdmin' => 1,
    'isAuthorized' => 1,
    'com.ericsson.bytel.hss.XMLReader' => 1,
    'cisexporter.jar' => 1,
    'bzip2' => 1,
    'bptm' => 1,
    'asm_fti_write' => 1,
    'asm_fti_read' => 1,
    'nbproxy' => 1,
    'bpjava-susvc' => 1,
    'bpbackup' => 1,
    'metacity-dialog' => 1,
    'ps' => 1,
    'file-roller' => 1,
    'emt_tgw_comcli' => 1,
    'diff' => 1,
    'date' => 1,
    'com.ericsson.ltng.cli.Cli' => 1,
    'getfault_DIP.pl' => 1,
    'sgehobfuscator.py' => 1,
    'ops_gui' => 1,
    'sdt_shell' => 1,
    'dmtool' => 1,
    'bpstart_notify' => 1,
    'cna_export' => 1,
    'dbping' => 1,
    'Parser.pl' => 1,
    'SirCell2G' => 1,
    'bpend_notify' => 1,
    'conv_hlr.py' => 1,
    'gnome-text-editor' => 1,
    'modifyUTRANxml.pl' => 1,
    'bpbrmds' => 1,
    'bpdm' => 1,
    'xml2gpeh-w13a' => 1,
    'bsm_cdmadjust' => 1,
    'litp' => 1,
    'Uetrace2pcap.pl' => 1,
    'updatets.x86_64' => 1,
    'cpio' => 1,
    'mount' => 1,
    'simulator.client.Client' => 1,
    'client' => 1,
    'ncms_execas' => 1,
    'cdm_cli' => 1,
    'export.sh' => 1,
    'se.ericsson.security.caas.admin.ui.cli.CaasAdmin' => 1,
    'watch' => 1,
    'vim' => 1,
    'touch' => 1,
    'getlog' => 1,
    'pidof' => 1,
    'curl' => 1,
    'iso_importer.py' => 1,
    'e2eXMLGenerator' => 1,
    'stress_worker.sh' => 1,
    'stress_san_worker.sh' => 1,
    'firefox-binselenium' => 1,
    'parseVersantLogfile' => 1,
    'node_populator' => 1,
    'workload' => 1,
    'netsim' => 1,
    'cli_app' => 1,
    'com.sun.btrace.client.Main' => 1,
    'com.ericsson.monitoring.jboss.JbossAttributeCollector' => 1,
    'com.ericsson.monitoring.jboss.InstrumentedBeansFinder' => 1,
    'com.ericsson.eniq.datagen.ede.util.stub.DiscardServer' => 1,
    'com.digitalroute.picostart.cmdline.CommandLine' => 1,
    'arping' => 1,
    'puppet' => 1,
    'mco' => 1,
    'yum' => 1,
    'pvs' => 1,
    'lvs' => 1,
    'nfsstat' => 1,
    'ddcDataUpload' => 1,
    'instr' => 1,
    'se.ericsson.cello.alarm.ActiveAlarm' => 1,
    'firefox' => 1,
    'com.ericsson.oss.services.eps.core.main.EpsApplication' => 1,
    'cppem.smartloader.SmartLoader' => 1,
    'parse_ebm_log.pl' => 1,
    'zabbix_server:' => 1,
    'zabbix_proxy:' => 1,
    'Parser.pl' => 1,
    'lsof' => 1,
    'x2Baseband.pl' => 1,
    'vbda' => 1,
    'dmtool.sh' => 1,
    'FileConverter' => 1,
    'eba_event_dumper' => 1,
    'util.cellopinger.CelloPinger' => 1,
    'sendcmd_cfh' => 1,
    'amos_ping_all' => 1,
    'check_active_alarm.pl' => 1,
    'delete_bsc.pl' => 1,
    'zabbix_agentd' => 1,
    'stsrt.linux' => 1,
    'ldapsearch' => 1,
    'SirCelSirCell4G_nohup.pl' => 1,
    'SirCell4G_nohup.pl' => 1,
    'ops_nui' => 1,
    'strace' => 1,
    'eog-image-viewer' => 1,
    'dmtool' => 1,
    'cfi' => 1,
    'cifam_cli' => 1,
    'bcgtool.sh' => 1,
    'BackupSchedule.sh' => 1,
    'rla' => 1,
    'UtranCellFixUtranCellFix.pl' => 1,
    'UtranExtGsmCellFix.pl' => 1,
    'xmllint' => 1,
    'admin' => 1,
    'cypher-shell' => 1,
    'bemcli.jar' => 1,
    'com.distocraft.dc5000.etl.engine.main.EngineAdmin' => 1,
    'com.ericsson.ltng.cli.LtngDecoder' => 1,
    'com.ericsson.ltt.client.viewer.TraceViewer' => 1,
    'ect.RnoMain' => 1,
    'dump_stash' => 1,
    'gpi.cli.GpiClient' => 1,
    'MATCH' => 1,
    'neo4j' => 1,
    'activitySetAdmin' => 1,
    'sun.applet.PluginMain' => 1,
    'unzip' => 1,
    'ping6' => 1,
    'echo' => 1,
    'printf' => 1,
    'OPS_Parser' => 1,
    'kubectl' => 1,
    'md5sum' => 1,
    'iqisql' => 1,
    'wfgui' => 1,
    '-t' => 1,
    'sudo' => 1,
    'containerd-shim' => 1,
    'docker-containerd-shim-current' => 1,
    'docker-current' => 1,
    'docker-proxy' => 1,
    'sybase.isql.ISQLLoader' => 1,
    'config.py' => 1,
    'vcs.bsh' => 1,
    'XPOSS45.jar' => 1,
    'pyworker' => 1,
    'extended' => 1,
    'lte_rec.sh' => 1,
    'genstat_report.sh' => 1,
    'genStats' => 1
);

sub setStatsCommon_Debug {
    my ($newDebug) = @_;
    $StatsCommon_DEBUG = $newDebug;
}

sub jpsAddShortName {
    my ($longName,$shortName) = @_;
    $shortNames{$longName} = $shortName;
}

sub jpsShortName {
    my ($cmd,$host,$omc) = @_;
    if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: cmd=$cmd\n"; }

    # Try and do cheapest matches first
    # MC name
    if ( $cmd =~ /^-Ds=(\S+)/ ) {
        return $1;
    }

    if ( $cmd =~ /^jboss\.node\.name=(\S+)$/ ) {
        my $jbossName = $1;
        # vENM format
        if ( $jbossName =~ /^([a-z0-9-]+)-([a-z]+)-(\d{1})$/ ) {
            my ($prefix, $service, $inst) = ($1, $2, $3);
            return "[JBOSS $service]";
        } elsif ( $jbossName =~ /^([a-z]{3})-(\d{1,2})-([a-z]+)/ ) {
            my ($cluster, $bladeIndex, $service) = ($1, $2, $3);
            return "[JBOSS $service]";
        } else {
            return $cmd;
        }
    } elsif ( $cmd =~ /^org.elasticsearch.bootstrap.Elasticsearch\s+-p\s+\/var\/run\/(\S+)\/.*/ ) {
        return $1;
    }

    # JBoss Instance
    if ( $cmd =~ /^\/home\/jboss\/([^\/]+)\/jboss-modules.jar / )  {
        return "[JBOSS $1]";
    }

    if ( $cmd =~ /^(\S+) MainLoop - next/ ) {
        return "$1 MainLoop";
    }

    # Get rid of any leading spaces
    $cmd =~ s/^\s+//;

    # StipArgs
    if ( $cmd =~ /^(\S+) / ) {
        my $firstPart = $1;
        if ( $StatsCommon_DEBUG > 9 ) { print "jpsShortName: checking if we can strip args for $firstPart\n" };

        my @parts = split(/\//, $firstPart );
        if ( exists $stripArgs{$parts[$#parts]} ) {
            if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: found match in stripArgs for $parts[$#parts]\n"; }
            # Keep the full path of the command as this makes it easier to exclude OS processes in
            # queries
            # Now call jpsShortName on the stripped command (to deal with things like /home/a72201/moshell/./gawk being mapped to [MOSHELL]
            return jpsShortName($firstPart);
        } elsif ( $firstPart =~ /^(com|org)\./ ) {
            # We assume this is a class name
            return $firstPart;
        }
    }

    foreach my $pattern ( keys %shortNames ) {
        if ( $cmd =~ /$pattern/ ) {
            if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: Found match in shortNames Mapping ", $cmd , " to " , $shortNames{$pattern}, "\n"; }
            return $shortNames{$pattern};
        }
    }

    if ( $cmd =~ /^fm.cirpman.CIRPMan \S+/ ) {
        my $cirpman = $cmd;
        #fm.cirpman.CIRPMan atrcus135 AdmHost=atrcus135,ManagedComponentFM_man
        if ( $cmd =~ /ManagedComponentFM_manager=([^,]+)/ ) {
            $cirpman = $1;
            if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: Mapping fmt1 cirpman from ", $cmd, " to $cirpman\n"; }
        }
        else {
            #fm.cirpman.CIRPMan ssranos02 fma_cirpman_rbs_2,fma_cirpman_rbs_2
            ($cirpman) = $cmd =~ /^fm.cirpman.CIRPMan \S+ ([^,]*),/;
            if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: Mapping fmt2 cirpman from ", $cmd, " to $cirpman\n"; }
        }
        return $cirpman;
    }
    elsif ( $cmd =~ /oss_data\/\d{6,6}/ ) {
        $cmd =~ s/oss_data\/\d{6,6}/oss_data\/\[date\]/;
        if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: Stripping date from ", $cmd, "\n"; }
        return $cmd;
    }
    elsif ( $cmd =~ /ddc_data\/\d{6,6}/ ) {
        $cmd =~ s/ddc_data\/\d{6,6}/ddc_data\/\[date\]/;
        if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: Stripping date from ", $cmd, "\n"; }
        return jpsShortName($cmd);
    }
    elsif ( $cmd =~ /ddc_data\/[^\/]+\/\d{6,6}/ ) {
        $cmd =~ s/ddc_data\/[^\/]+\/\d{6,6}/ddc_data\/\[date\]/;
        if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: Stripping host and date from ", $cmd, "\n"; }
        return jpsShortName($cmd);
    }
    elsif ( $cmd =~ /^util.dumplog.DumpLog (\S+)/ ) {
        $cmd = "util.dumplog.DumpLog $1";
        return $cmd;
    }
    elsif ( $cmd =~ /^fm\S+TXF_AlarmAdaptation .*txf_([^_ ]+)_/ ) {
        return "fm/TXF_AlarmAdaptation $1";
    }
    elsif ( $cmd =~ /^nms_cif_tmos_tbs\/bin\/tbsProxy\s+(\S+)/ ) {
        return "TBSProxy $1";
    }
    elsif ( $cmd =~ /(.*)\/data\/ddp\/DDP[_-][^\/]+\/analysis\/(\S+)/ ) {
        return $1 . "/data/ddp/[DDP_VER]/analysis/" . $2;
    }
    elsif ( $cmd =~ /(.*)\/data\/ddp\/current\/analysis\/(\S+)/ ) {
        return $1 . "/data/ddp/[DDP_VER]/analysis/" . $2;
    }
    elsif ( $cmd =~ /^\/netsim\/\S+\/(\S+)/ ) {
        return "[netsim " . $1 . "]";
    }
    elsif ($cmd =~ /^sendmail:.*/) {
        $cmd = "[SENDMAIL]";
        return $cmd;
    }
    # Fix for fault parsing of jps log
    elsif ( $cmd =~ /^\d+ R[A-Z0-9_]+ (.*)/ )
    {
        return $1;
    }
    # Fix for fault in parseCron
    elsif ( $cmd =~ /CMD: (.*)/ ) {
        my $realCmd = $1;
        return jpsShortName($realCmd,$host,$omc);
    }
    elsif ( $cmd =~ /^\[(\S+)\/\d+\]$/ || $cmd =~ /^\[(\S+)\/\d+:\d+\]$/ || $cmd =~ /^\[(\S+)-\d+\]$/ ) {
        # Merge Linux kernel threads
        return "[" . $1 . "/NUM]";
    }
    elsif ( $cmd =~ /^startdb -noprint -skip -ssdport \d+ (.*)/ ) {
        $cmd = "startdb -noprint -skip -ssdport [PORT] " . $1;
    }

    # Various generic things to strip out of commands
    if ( $cmd =~ /(.*?)\s+\d+\.\d+\.\d+\.\d+\s+(.*)/ ) {
        my ($pre,$post) = ($1,$2);
        $cmd = $pre . " [IPV4ADDR] " . $post;
    }
    if ( $host ne "" && $cmd =~ /$host/i ) {
        $cmd =~ s/$host/\[host\]/gi;
        if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: Stripping $host from ", $cmd, "\n"; }
    }
    if ( $omc ne "" && $cmd =~ /$omc/ ) {
        $cmd =~ s/$omc/\[OMC\]/g;
        if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: Stripping $host from ", $cmd, "\n"; }
    }
    if ( $cmd =~ /\d{13,13}/ ) {
        $cmd = s/\d{13,13}//g;
    }
    if ( $cmd =~ / -o\d+ / ) {
        $cmd =~ s/ -o\d+ / -o\[NUM\] /;
    }
    # HK80569: Adding a generic short name for parseCron to present TIMESTAMP
    if ( $cmd =~ /^\d+.a$/ ) {
        $cmd =~ s/\d+/\[TIMESTAMP\]/;
    }
    if ( $cmd =~ /-node \S+/ ) {
        $cmd =~ s/ -node \S+/ \[-node\]/;
    }

    # Strip off all vbroker options
    $cmd =~ s/NameService=\S+//;
    $cmd =~ s/-D?vb\S*//g;
    $cmd =~ s/-D?ORBInit\S+//;
    $cmd =~ s/-D?ORBDefaultInit\S+//;
    $cmd =~ s/iiop\S+//;
    $cmd =~ s/\s{2,}/ /g; # Remove any blanks created by the previous statements
    $cmd =~ s/ *$//;

    my ( $firstPart, $remainder ) = $cmd =~ /^(\S+)(.*)/;
    $remainder =~ s/^\s+//;
    if ( $StatsCommon_DEBUG > 7 ) { print "jpsShortName: firstPart=$firstPart, remainder=$remainder\n"; }
    if ( $firstPart =~ /\/home\// || $firstPart =~ /\$HOME\//  ) {
        $cmd = "[HOME_SCRIPT]";
    }
    elsif ( $firstPart =~ /\/root\// ) {
        $cmd = "[ROOT_SCRIPT]";
    }
    elsif ( $firstPart eq "source" ) {
        if ( $remainder =~ /([^ ;]+); (.*)/ ) {
            my ($sourceFile,$restOfCmd) = ($1,$2);
            if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: sourceFile=$sourceFile restOfCmd=$restOfCmd\n"; }
            $cmd = jpsShortName($restOfCmd);
        }
    }
    elsif ( (defined $remainder) && (length($remainder) > 0) ) {
        my $basename = basename($firstPart);
        if ( exists $SHELLS{$basename} ) {
            if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: found shell $firstPart\n"; }
            while ( $remainder =~ /^-\S{1,2}\s+(.*)/ ) {
                $remainder = $1;
            }

            if ( $remainder =~ /^\/?\/home/ ) {
                $cmd = $firstPart . ' ' . "[HOME_SCRIPT]";
            } elsif ( $remainder =~ /^\/export\/home/ ) {
                $cmd = $firstPart . ' ' . "[HOME_SCRIPT]";
            } elsif ( $remainder =~ /^\./ ) {
                $cmd = $firstPart . ' ' . "[DOT_SCRIPT]";
            } elsif ( $remainder =~ /^\/tmp\// || $remainder =~ /^\/var\/tmp\// ) {
                $cmd = $firstPart . ' '  . "[TMP_SCRIPT]";
            } else {
                my ($script) = $remainder =~ /^\s*(\S+)/;
                if ( $script =~ /^\[/ ) {
                    if ( $StatsCommon_DEBUG > 5 ) { print "jpsShortName: script already mapped\n"; }
                } elsif ( $script !~ /\// && exists $OS_SHELLS{$basename} ) {
                    $remainder = "[SHELL_CMD]"
                } else {
                    $remainder = jpsShortName($script);
                }
                $cmd = $firstPart . ' ' . $remainder;
            }
        }
        elsif ( $firstPart =~ /ttcshr$/ ) {
            my ($subCmd) = $remainder =~ /.* -c (.*)/;
            $subCmd =~ s/^\'//;
            $subCmd =~ s/\'$//;
            $subCmd =~ s/^\\q//;
            $subCmd =~ s/\\q$//;

            if ( $subCmd =~ /^\S*echo \"(.*)/ ) {
                $subCmd = $1;
                $subCmd =~ s/" \| \S*sh\s*$//;
                $subCmd = "[ECHO] " . jpsShortName($subCmd) . " | [SH]";
            } else {
                $subCmd = jpsShortName($subCmd);
            }
            $cmd = "[TTSCHR] " . $subCmd;
        }
        elsif ( $firstPart eq "[ttschr]" ) {
            if ( $remainder =~ /^\[ECHO\] (.*)/ ) {
                my $afterEcho = $1;
                if ( $afterEcho =~ /^env \S+=\S+ (.*)/ ) {
                    $cmd = "[TTCSHR] [ECHO] [ENV] " . jpsShortName($1);
                }
                else {
                    $cmd = "[TTCSHR] [ECHO] " . jpsShortName($afterEcho);
                }
            }
            else {
                $cmd = "[TTSCHR] " . jpsShortName($remainder);
            }
        }
        elsif ( $firstPart eq "su" ) {
            if ( $remainder =~ /^- (\S+) -(\S+) (.*)/ ) {
                my ($userid,$arg,$subcmd) = ($1,$2,$3);
                if ( $subcmd =~ /^['"]/ ) {
                    $subcmd =~ s/['"]//g;
                }
                my $newcmd = jpsShortName($subcmd);
                if ( $newcmd ne $subcmd ) {
                    $cmd = "su - $userid -$arg $newcmd";
                }
            }
        }
    }
    return $cmd;
}

sub getRnsShort {
    my ($fdn) = @_;

    my $rns;
    if ( $fdn =~ /^([^,]+),/ ) {
        $rns = $1;
    }
    else {
        $rns = 'RANAG';
    }

    if ( $StatsCommon_DEBUG > 8 ) { print "getRnsShort: fdn=$fdn rns=$rns\n"; }

    return $rns;
}

sub getMeInfoShort {
    my ($fdn) = @_;

    my $r_MeInfo = {};
    if ( $fdn =~ /^([^,]+),(.+)/ ) {
        $r_MeInfo->{'rns'} = $1;
        $r_MeInfo->{'id'} = $2;
        if ( $r_MeInfo->{'rns'} eq $r_MeInfo->{'id'} ) {
            $r_MeInfo->{'type'} = "RNC";
        }
        else {
            $r_MeInfo->{'type'} = "RBS";
        }
    }
    else {
        $r_MeInfo->{'rns'} = 'RANAG';
        $r_MeInfo->{'type'} = 'RANAG';
        $r_MeInfo->{'id'} = $fdn;
    }

    if ( $StatsCommon_DEBUG > 8 ) { print Dumper("getMeInfoShort: fdn=$fdn meInfo", $r_MeInfo); }

    return $r_MeInfo;
}

sub getShortNode
{
    my ($meCon) = @_;
    my $result = "";
    my @rdnList = split ( /,/, $meCon );

    if ( $#rdnList == 2 ) {
        my ($rns) = $rdnList[1] =~ /^SubNetwork=(.*)/;
        $result = $rns . ",";
    }
    my ($meCon) = $rdnList[$#rdnList] =~ /^MeContext=(.*)/;
    $result .= $meCon;
    return $result;
}

sub readNe($$) {
    my ($dbh,$siteId) = @_;
    my $r_AllNe = dbSelectAllHash($dbh, "SELECT rns.name AS rns, ne.name AS name, ne.id AS neid, ne_types.name AS type FROM ne,ne_types,rns WHERE siteid = $siteId AND ne.rnsid = rns.id AND ne.netypeid = ne_types.id");
    return $r_AllNe;
}

#
# Reads in a file previously written by incrWrite
#
# Returns either
#  - empty hash if incrFile not defined or the file doesn't exist
#  - The hash written by a previous call to incrWrite
#
sub incrRead($) {
    my ($incrFile) = @_;

    my %incrData = ();
    if ( defined $incrFile && -r $incrFile ) {
        my $incrDataStr;
        do {
            local $/ = undef;
            open(INC, $incrFile) or die "Could not open $incrFile: $!";;
            $incrDataStr = <INC>;
            close INC;
        };

        my $VAR1 = {};
        eval($incrDataStr);
        %incrData = %{$VAR1};
        if ( $StatsCommon_DEBUG > 3 ) { print Dumper("incrRead: incrData", \%incrData); }
    }

    return \%incrData;
}

#
# Writes out a file containing the content of the incrData hash
#
# If incrFile is not defined, no file is written.
#
sub incrWrite($$) {
    my ($incrFile, $r_incrData) = @_;
    if ( defined $incrFile ) {
        my $incrDataStr = Dumper($r_incrData);
        open(INC, ">$incrFile");
        print INC $incrDataStr;
        close INC;
    }
}

sub logMsg(@_) {
    my ($sec, $min, $hour) = localtime();
        printf("%02d:%02d:%02d: ", $hour, $min, $sec);
        printf(@_);
        print "\n";
}

sub debugMsg(@_) {
    my $level = shift;
    if ( $::DEBUG >= $level ) {
        logMsg(@_);
    }
}

sub debugMsgWithObj(@_) {
    my $level = shift;
    if ( $::DEBUG >= $level ) {
        my $objRef = shift;
        logMsg(@_);
        print Dumper($objRef);
    }
}

1;
