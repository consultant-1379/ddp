<?php
$pageTitle = "Event Streaming Instrumentation";
$YUI_DATATABLE = true;

include "../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/SqlPlotParam.php";
require_once PHP_ROOT . "/classes/DDPObject.class.php";

$EVENT_NAMES =
    array
    (
        '5211' => 'INTERNAL_EVENT_ADMISSION_BLOCKING_STARTED',
        '5212' => 'INTERNAL_EVENT_ADMISSION_BLOCKING_STOPPED',
        '5213' => 'INTERNAL_EVENT_ADMISSION_BLOCKING_UPDATED',
        '5228' => 'INTERNAL_EVENT_ADV_CELL_SUP_DETECTION',
        '5229' => 'INTERNAL_EVENT_ADV_CELL_SUP_RECOVERY_ATTEMPT',
        '5230' => 'INTERNAL_EVENT_ADV_CELL_SUP_RECOVERY_RESULT',
        '5159' => 'INTERNAL_EVENT_ANR_CONFIG_MISSING',
        '5266' => 'INTERNAL_EVENT_ANR_HO_LEVEL_CHANGED',
        '5173' => 'INTERNAL_EVENT_ANR_PCI_REPORT_WANTED',
        '5238' => 'INTERNAL_EVENT_ANR_STOP_MEASURING',
        '5248' => 'INTERNAL_EVENT_CANDNREL_ADD',
        '5249' => 'INTERNAL_EVENT_CANDNREL_REMOVE',
        '5236' => 'INTERNAL_EVENT_CELL_DL_CAPACITY',
        '5255' => 'INTERNAL_EVENT_CELL_WAKEUP_DETECTED',
        '5254' => 'INTERNAL_EVENT_CELL_WAKEUP_TRIGGERED',
        '5226' => 'INTERNAL_EVENT_CMAS_REPET_STOPPED',
        '5223' => 'INTERNAL_EVENT_CMAS_REQ',
        '5224' => 'INTERNAL_EVENT_CMAS_RESP',
        '5258' => 'INTERNAL_EVENT_COV_CELL_DISCOVERY_END',
        '5256' => 'INTERNAL_EVENT_COV_CELL_DISCOVERY_START',
        '5257' => 'INTERNAL_EVENT_COV_CELL_DISCOVERY_UPDATE',
        '5252' => 'INTERNAL_EVENT_DL_COMP_MEAS_CONFIG_REJECT',
        '5253' => 'INTERNAL_EVENT_DL_COMP_MEAS_REP_DISCARD',
        '5259' => 'INTERNAL_EVENT_DYNAMIC_UE_ADMISSION_BLOCKING_STARTED',
        '5260' => 'INTERNAL_EVENT_DYNAMIC_UE_ADMISSION_BLOCKING_STOPPED',
        '5261' => 'INTERNAL_EVENT_DYNAMIC_UE_ADMISSION_BLOCKING_UPDATED',
        '5202' => 'INTERNAL_EVENT_ERAB_DATA_INFO',
        '5265' => 'INTERNAL_EVENT_ERAB_RELEASE_DELAYED',
        '5214' => 'INTERNAL_EVENT_ERAB_ROHC_FAIL_LIC_REJECT',
        '5225' => 'INTERNAL_EVENT_ETWS_REPET_STOPPED',
        '5221' => 'INTERNAL_EVENT_ETWS_REQ',
        '5222' => 'INTERNAL_EVENT_ETWS_RESP',
        '5167' => 'INTERNAL_EVENT_EUTRAN_FREQUENCY_ADD',
        '5168' => 'INTERNAL_EVENT_FREQ_REL_ADD',
        '5205' => 'INTERNAL_EVENT_HO_WRONG_CELL',
        '5206' => 'INTERNAL_EVENT_HO_WRONG_CELL_REEST',
        '5198' => 'INTERNAL_EVENT_IMLB_ACTION',
        '5197' => 'INTERNAL_EVENT_IMLB_CONTROL',
        '5134' => 'INTERNAL_EVENT_INTEGRITY_VER_FAIL_RRC_MSG',
        '5171' => 'INTERNAL_EVENT_IP_ADDR_GET_FAILURE',
        '5250' => 'INTERNAL_EVENT_LB_EVALUATION_TO',
        '5215' => 'INTERNAL_EVENT_LB_INTER_FREQ',
        '5220' => 'INTERNAL_EVENT_LB_SUB_RATIO',
        '5166' => 'INTERNAL_EVENT_LICENSE_UNAVAILABLE',
        '5233' => 'INTERNAL_EVENT_LOAD_CONTROL_STATE_TRANSITION',
        '5139' => 'INTERNAL_EVENT_MAX_FILESIZE_REACHED',
        '5140' => 'INTERNAL_EVENT_MAX_FILESIZE_RECOVERY',
        '5138' => 'INTERNAL_EVENT_MAX_STORAGESIZE_REACHED',
        '5128' => 'INTERNAL_EVENT_MAX_UETRACES_REACHED',
        '5274' => 'INTERNAL_EVENT_MBMS_CELL_SELECTION',
        '5237' => 'INTERNAL_EVENT_MBMS_INTEREST_INDICATION',
        '5262' => 'INTERNAL_EVENT_MEASUREMENT_REPORT_RECEIVED',
        '5174' => 'INTERNAL_EVENT_MEAS_CONFIG_A1',
        '5175' => 'INTERNAL_EVENT_MEAS_CONFIG_A2',
        '5176' => 'INTERNAL_EVENT_MEAS_CONFIG_A3',
        '5177' => 'INTERNAL_EVENT_MEAS_CONFIG_A4',
        '5178' => 'INTERNAL_EVENT_MEAS_CONFIG_A5',
        '5239' => 'INTERNAL_EVENT_MEAS_CONFIG_A6',
        '5235' => 'INTERNAL_EVENT_MEAS_CONFIG_B1_CDMA2000',
        '5273' => 'INTERNAL_EVENT_MEAS_CONFIG_B1_GERAN',
        '5234' => 'INTERNAL_EVENT_MEAS_CONFIG_B1_UTRA',
        '5182' => 'INTERNAL_EVENT_MEAS_CONFIG_B2_CDMA2000',
        '5180' => 'INTERNAL_EVENT_MEAS_CONFIG_B2_GERAN',
        '5181' => 'INTERNAL_EVENT_MEAS_CONFIG_B2_UTRA',
        '5179' => 'INTERNAL_EVENT_MEAS_CONFIG_PERIODICAL_EUTRA',
        '5183' => 'INTERNAL_EVENT_MEAS_CONFIG_PERIODICAL_GERAN',
        '5184' => 'INTERNAL_EVENT_MEAS_CONFIG_PERIODICAL_UTRA',
        '5244' => 'INTERNAL_EVENT_MIMO_SLEEP_DETECTED',
        '5194' => 'INTERNAL_EVENT_NEIGHBCELL_ADDITIONAL_CGI',
        '5144' => 'INTERNAL_EVENT_NEIGHBCELL_CHANGE',
        '5145' => 'INTERNAL_EVENT_NEIGHBENB_CHANGE',
        '5146' => 'INTERNAL_EVENT_NEIGHBREL_ADD',
        '5147' => 'INTERNAL_EVENT_NEIGHBREL_REMOVE',
        '5123' => 'INTERNAL_EVENT_NO_RESET_ACK_FROM_MME',
        '5192' => 'INTERNAL_EVENT_ONGOING_UE_MEAS',
        '5275' => 'INTERNAL_EVENT_PARTITION_CONFIG_MISSING',
        '5217' => 'INTERNAL_EVENT_PCI_CONFLICT_DETECTED',
        '5218' => 'INTERNAL_EVENT_PCI_CONFLICT_RESOLVED',
        '5143' => 'INTERNAL_EVENT_PM_DATA_COLLECTION_LOST',
        '5133' => 'INTERNAL_EVENT_PM_EVENT_SUSPECTMARKED',
        '5127' => 'INTERNAL_EVENT_PM_RECORDING_FAULT_JVM',
        '5170' => 'INTERNAL_EVENT_RECOMMENDED_NR_SI_UPDATES_REACHED',
        '5264' => 'INTERNAL_EVENT_RESUME_LOW_ARP_DRB_DL_RLC_FAIL',
        '5263' => 'INTERNAL_EVENT_RETAIN_UECTXT_HIGH_ARP_DRB',
        '5201' => 'INTERNAL_EVENT_RIM_RAN_INFORMATION_RECEIVED',
        '5247' => 'INTERNAL_EVENT_RIM_RAN_INFORMATION_SENT',
        '5251' => 'INTERNAL_EVENT_RIM_RAN_STATUS_CHANGED',
        '5120' => 'INTERNAL_EVENT_RRC_ERROR',
        '5207' => 'INTERNAL_EVENT_RRC_UE_INFORMATION',
        '5124' => 'INTERNAL_EVENT_S1AP_PROTOCOL_ERROR',
        '5209' => 'INTERNAL_EVENT_S1_ERROR_INDICATION',
        '5208' => 'INTERNAL_EVENT_S1_NAS_NON_DELIVERY_INDICATION',
        '5196' => 'INTERNAL_EVENT_SON_OSCILLATION_DETECTED',
        '5195' => 'INTERNAL_EVENT_SON_UE_OSCILLATION_PREVENTED',
        '5200' => 'INTERNAL_EVENT_SPID_PRIORITY_IGNORED',
        '5203' => 'INTERNAL_EVENT_TOO_EARLY_HO',
        '5204' => 'INTERNAL_EVENT_TOO_LATE_HO',
        '5240' => 'INTERNAL_EVENT_UETR_MEASUREMENT_REPORT_RECEIVED',
        '5241' => 'INTERNAL_EVENT_UETR_RRC_SCELL_DECONFIGURED',
        '5148' => 'INTERNAL_EVENT_UE_ANR_CONFIG_PCI',
        '5227' => 'INTERNAL_EVENT_UE_ANR_CONFIG_PCI_REMOVE',
        '5149' => 'INTERNAL_EVENT_UE_ANR_PCI_REPORT',
        '5172' => 'INTERNAL_EVENT_UE_CAPABILITY',
        '5243' => 'INTERNAL_EVENT_UE_LB_MEAS',
        '5242' => 'INTERNAL_EVENT_UE_LB_QUAL',
        '5157' => 'INTERNAL_EVENT_UE_MEAS_FAILURE',
        '5193' => 'INTERNAL_EVENT_UE_MOBILITY_EVAL',
        '5131' => 'INTERNAL_EVENT_UNEXPECTED_RRC_MSG',
        '5245' => 'INTERNAL_EVENT_WIFI_MOBILITY_EVAL_CONNECTED',
        '5246' => 'INTERNAL_EVENT_WIFI_MOBILITY_EVAL_IDLE',
        '5137' => 'INTERNAL_EVENT_X2AP_PROTOCOL_ERROR',
        '5136' => 'INTERNAL_EVENT_X2_CONN_RELEASE',
        '5210' => 'INTERNAL_EVENT_X2_ERROR_INDICATION',
        '3110' => 'INTERNAL_PER_BRANCH_UL_NOISEINTERF_REPORT',
        '3119' => 'INTERNAL_PER_BRANCH_UPPTS_UL_INTERFERENCE_REPORT',
        '3078' => 'INTERNAL_PER_CAP_LICENSE_UTIL_REP',
        '3114' => 'INTERNAL_PER_CELL_MDT_M3_REPORT',
        '3086' => 'INTERNAL_PER_CELL_QCI_TRAFFIC_REP',
        '3079' => 'INTERNAL_PER_CELL_TRAFFIC_REPORT',
        '3107' => 'INTERNAL_PER_EVENT_CMAS_REPET_COMPL',
        '3106' => 'INTERNAL_PER_EVENT_ETWS_REPET_COMPL',
        '3117' => 'INTERNAL_PER_PARTITION_REPORT',
        '3085' => 'INTERNAL_PER_PRB_LICENSE_UTIL_REP',
        '3084' => 'INTERNAL_PER_PROCESSOR_LOAD',
        '3089' => 'INTERNAL_PER_RADIO_CELL_CQI_SUBBAND',
        '3081' => 'INTERNAL_PER_RADIO_CELL_MEASUREMENT',
        '3083' => 'INTERNAL_PER_RADIO_CELL_MEASUREMENT_TDD',
        '3088' => 'INTERNAL_PER_RADIO_CELL_NOISE_INTERFERENCE_PRB',
        '3075' => 'INTERNAL_PER_RADIO_UE_MEASUREMENT',
        '3108' => 'INTERNAL_PER_RADIO_UE_MEASUREMENT_TA',
        '3072' => 'INTERNAL_PER_RADIO_UTILIZATION',
        '3111' => 'INTERNAL_PER_UETR_BRANCH_UL_NOISEINTERF_REPORT',
        '3120' => 'INTERNAL_PER_UETR_BRANCH_UPPTS_UL_INTERFERENCE_REPORT',
        '3097' => 'INTERNAL_PER_UETR_CAP_LICENSE_UTIL_REP',
        '3095' => 'INTERNAL_PER_UETR_CELL_QCI_TRAFFIC_REP',
        '3099' => 'INTERNAL_PER_UETR_CELL_TRAFFIC_REPORT',
        '3118' => 'INTERNAL_PER_UETR_PARTITION_REPORT',
        '3098' => 'INTERNAL_PER_UETR_PRB_LICENSE_UTIL_REP',
        '3103' => 'INTERNAL_PER_UETR_RADIO_CELL_CQI_SUBBAND',
        '3101' => 'INTERNAL_PER_UETR_RADIO_CELL_MEASUREMENT',
        '3105' => 'INTERNAL_PER_UETR_RADIO_CELL_MEASUREMENT_TDD',
        '3102' => 'INTERNAL_PER_UETR_RADIO_CELL_NOISE_INTERFERENCE_PRB',
        '3092' => 'INTERNAL_PER_UETR_RADIO_UE_MEASUREMENT',
        '3090' => 'INTERNAL_PER_UETR_RADIO_UTILIZATION',
        '3091' => 'INTERNAL_PER_UETR_UE_ACTIVE_SESSION_TIME',
        '3096' => 'INTERNAL_PER_UETR_UE_LCG_TRAFFIC_REP',
        '3094' => 'INTERNAL_PER_UETR_UE_RB_TRAFFIC_REP',
        '3093' => 'INTERNAL_PER_UETR_UE_TRAFFIC_REP',
        '3074' => 'INTERNAL_PER_UE_ACTIVE_SESSION_TIME',
        '3087' => 'INTERNAL_PER_UE_LCG_TRAFFIC_REP',
        '3112' => 'INTERNAL_PER_UE_MDT_M1_REPORT',
        '3113' => 'INTERNAL_PER_UE_MDT_M2_REPORT',
        '3115' => 'INTERNAL_PER_UE_MDT_M4_REPORT',
        '3116' => 'INTERNAL_PER_UE_MDT_M5_REPORT',
        '3077' => 'INTERNAL_PER_UE_RB_TRAFFIC_REP',
        '3076' => 'INTERNAL_PER_UE_TRAFFIC_REP',
        '4117' => 'INTERNAL_PROC_ANR_CGI_REPORT',
        '4135' => 'INTERNAL_PROC_CELL_SLEEP_TRIGGERED',
        '4138' => 'INTERNAL_PROC_CSG_CELL_CGI_REPORT',
        '4107' => 'INTERNAL_PROC_DNS_LOOKUP',
        '4122' => 'INTERNAL_PROC_ERAB_MODIFY',
        '4114' => 'INTERNAL_PROC_ERAB_RELEASE',
        '4099' => 'INTERNAL_PROC_ERAB_SETUP',
        '4105' => 'INTERNAL_PROC_HO_EXEC_S1_IN',
        '4104' => 'INTERNAL_PROC_HO_EXEC_S1_OUT',
        '4113' => 'INTERNAL_PROC_HO_EXEC_X2_IN',
        '4112' => 'INTERNAL_PROC_HO_EXEC_X2_OUT',
        '4103' => 'INTERNAL_PROC_HO_PREP_S1_IN',
        '4102' => 'INTERNAL_PROC_HO_PREP_S1_OUT',
        '4111' => 'INTERNAL_PROC_HO_PREP_X2_IN',
        '4110' => 'INTERNAL_PROC_HO_PREP_X2_OUT',
        '4106' => 'INTERNAL_PROC_INITIAL_CTXT_SETUP',
        '4129' => 'INTERNAL_PROC_M3_SETUP',
        '4130' => 'INTERNAL_PROC_MBMS_SESSION_START',
        '4137' => 'INTERNAL_PROC_MBMS_SESSION_UPDATE',
        '4133' => 'INTERNAL_PROC_MIMO_SLEEP_SWITCHED',
        '4136' => 'INTERNAL_PROC_NAS_TRANSFER_DL',
        '4134' => 'INTERNAL_PROC_NON_PLANNED_PCI_CGI_REPORT',
        '4108' => 'INTERNAL_PROC_REVERSE_DNS_LOOKUP',
        '4121' => 'INTERNAL_PROC_RRC_CONNECTION_RE_ESTABLISHMENT',
        '4120' => 'INTERNAL_PROC_RRC_CONN_RECONF_NO_MOB',
        '4097' => 'INTERNAL_PROC_RRC_CONN_SETUP',
        '4116' => 'INTERNAL_PROC_S1_SETUP',
        '4098' => 'INTERNAL_PROC_S1_SIG_CONN_SETUP',
        '4119' => 'INTERNAL_PROC_S1_TENB_CONF_LOOKUP',
        '4109' => 'INTERNAL_PROC_SCTP_SETUP',
        '4124' => 'INTERNAL_PROC_SCTP_SHUTDOWN',
        '4131' => 'INTERNAL_PROC_SOFT_LOCK',
        '9229' => 'INTERNAL_PROC_TESTEVENT_COMPLEX',
        '9225' => 'INTERNAL_PROC_TESTEVENT_MANY_START',
        '9227' => 'INTERNAL_PROC_TESTEVENT_MANY_STOP',
        '9228' => 'INTERNAL_PROC_TESTEVENT_NOPARAM_START',
        '9226' => 'INTERNAL_PROC_TESTEVENT_POST',
        '4132' => 'INTERNAL_PROC_UETR_RRC_SCELL_CONFIGURED',
        '4128' => 'INTERNAL_PROC_UE_CTXT_FETCH',
        '4126' => 'INTERNAL_PROC_UE_CTXT_MODIFY',
        '4125' => 'INTERNAL_PROC_UE_CTXT_RELEASE',
        '4123' => 'INTERNAL_PROC_X2_RESET',
        '4118' => 'INTERNAL_PROC_X2_SETUP',
        '9220' => 'INTERNAL_TESTEVENT_BASIC',
        '9224' => 'INTERNAL_TESTEVENT_BB_BBM',
        '9221' => 'INTERNAL_TESTEVENT_BB_CELL',
        '9222' => 'INTERNAL_TESTEVENT_BB_RB',
        '9223' => 'INTERNAL_TESTEVENT_BB_UE',
        '9217' => 'INTERNAL_TESTEVENT_CELL1',
        '9219' => 'INTERNAL_TESTEVENT_CELL2',
        '9218' => 'INTERNAL_TESTEVENT_EXT',
        '9257' => 'INTERNAL_TESTEVENT_L3',
        '9230' => 'INTERNAL_TESTEVENT_MANDATORY_OVERRIDE',
        '9233' => 'INTERNAL_TESTEVENT_PROC_BASE_1',
        '9242' => 'INTERNAL_TESTEVENT_PROC_BASE_10',
        '9243' => 'INTERNAL_TESTEVENT_PROC_BASE_11',
        '9244' => 'INTERNAL_TESTEVENT_PROC_BASE_12',
        '9245' => 'INTERNAL_TESTEVENT_PROC_BASE_13',
        '9246' => 'INTERNAL_TESTEVENT_PROC_BASE_14',
        '9247' => 'INTERNAL_TESTEVENT_PROC_BASE_15',
        '9248' => 'INTERNAL_TESTEVENT_PROC_BASE_16',
        '9249' => 'INTERNAL_TESTEVENT_PROC_BASE_17',
        '9250' => 'INTERNAL_TESTEVENT_PROC_BASE_18',
        '9251' => 'INTERNAL_TESTEVENT_PROC_BASE_19',
        '9234' => 'INTERNAL_TESTEVENT_PROC_BASE_2',
        '9252' => 'INTERNAL_TESTEVENT_PROC_BASE_20',
        '9253' => 'INTERNAL_TESTEVENT_PROC_BASE_21',
        '9254' => 'INTERNAL_TESTEVENT_PROC_BASE_22',
        '9255' => 'INTERNAL_TESTEVENT_PROC_BASE_23',
        '9256' => 'INTERNAL_TESTEVENT_PROC_BASE_24',
        '9235' => 'INTERNAL_TESTEVENT_PROC_BASE_3',
        '9236' => 'INTERNAL_TESTEVENT_PROC_BASE_4',
        '9237' => 'INTERNAL_TESTEVENT_PROC_BASE_5',
        '9238' => 'INTERNAL_TESTEVENT_PROC_BASE_6',
        '9239' => 'INTERNAL_TESTEVENT_PROC_BASE_7',
        '9240' => 'INTERNAL_TESTEVENT_PROC_BASE_8',
        '9241' => 'INTERNAL_TESTEVENT_PROC_BASE_9',
        '9216' => 'INTERNAL_TESTEVENT_UE',
        '5185' => 'INTERNAL_UE_MEAS_ABORT',
        '8194' => 'M3_M3_SETUP_FAILURE',
        '8192' => 'M3_M3_SETUP_REQUEST',
        '8193' => 'M3_M3_SETUP_RESPONSE',
        '8197' => 'M3_MBMS_SESSION_START_FAILURE',
        '8195' => 'M3_MBMS_SESSION_START_REQUEST',
        '8196' => 'M3_MBMS_SESSION_START_RESPONSE',
        '8198' => 'M3_MBMS_SESSION_STOP_REQUEST',
        '8199' => 'M3_MBMS_SESSION_STOP_RESPONSE',
        '8206' => 'M3_MBMS_SESSION_UPDATE_FAILURE',
        '8204' => 'M3_MBMS_SESSION_UPDATE_REQUEST',
        '8205' => 'M3_MBMS_SESSION_UPDATE_RESPONSE',
        '8203' => 'M3_MCE_CONFIGURATION_UPDATE_FAILURE',
        '8201' => 'M3_MCE_CONFIGURATION_UPDATE_REQUEST',
        '8202' => 'M3_MCE_CONFIGURATION_UPDATE_RESPONSE',
        '8200' => 'M3_RESET',
        '24'   => 'RRC_CONNECTION_RE_ESTABLISHMENT',
        '25'   => 'RRC_CONNECTION_RE_ESTABLISHMENT_COMPLETE',
        '29'   => 'RRC_CSFB_PARAMETERS_REQUEST_CDMA2000',
        '30'   => 'RRC_CSFB_PARAMETERS_RESPONSE_CDMA2000',
        '6'    => 'RRC_DL_INFORMATION_TRANSFER',
        '31'   => 'RRC_HANDOVER_FROM_EUTRA_PREPARATION_REQUEST',
        '34'   => 'RRC_INTER_FREQ_RSTD_MEASUREMENT_INDICATION',
        '21'   => 'RRC_MASTER_INFORMATION_BLOCK',
        '33'   => 'RRC_MBMS_INTEREST_INDICATION',
        '28'   => 'RRC_MBSFNAREA_CONFIGURATION',
        '11'   => 'RRC_MEASUREMENT_REPORT',
        '7'    => 'RRC_MOBILITY_FROM_E_UTRA_COMMAND',
        '35'   => 'RRC_MOBILITY_FROM_E_UTRA_COMMAND_EXT',
        '8'    => 'RRC_RRC_CONNECTION_RECONFIGURATION',
        '13'   => 'RRC_RRC_CONNECTION_RECONFIGURATION_COMPLETE',
        '1'    => 'RRC_RRC_CONNECTION_REJECT',
        '5'    => 'RRC_RRC_CONNECTION_RELEASE',
        '2'    => 'RRC_RRC_CONNECTION_REQUEST',
        '4'    => 'RRC_RRC_CONNECTION_RE_ESTABLISHMENT_REJECT',
        '3'    => 'RRC_RRC_CONNECTION_RE_ESTABLISHMENT_REQUEST',
        '0'    => 'RRC_RRC_CONNECTION_SETUP',
        '12'   => 'RRC_RRC_CONNECTION_SETUP_COMPLETE',
        '9'    => 'RRC_SECURITY_MODE_COMMAND',
        '17'   => 'RRC_SECURITY_MODE_COMPLETE',
        '18'   => 'RRC_SECURITY_MODE_FAILURE',
        '22'   => 'RRC_SYSTEM_INFORMATION',
        '23'   => 'RRC_SYSTEM_INFORMATION_BLOCK_TYPE_1',
        '10'   => 'RRC_UE_CAPABILITY_ENQUIRY',
        '19'   => 'RRC_UE_CAPABILITY_INFORMATION',
        '27'   => 'RRC_UE_INFORMATION_REQUEST',
        '26'   => 'RRC_UE_INFORMATION_RESPONSE',
        '32'   => 'RRC_UL_HANDOVER_PREPARATION_TRANSFER',
        '16'   => 'RRC_UL_INFORMATION_TRANSFER',
        '1025' => 'S1_DOWNLINK_NAS_TRANSPORT',
        '1083' => 'S1_DOWNLINK_NON_UE_ASSOCIATED_LPPA_TRANSPORT',
        '1024' => 'S1_DOWNLINK_S1_CDMA2000_TUNNELING',
        '1091' => 'S1_DOWNLINK_S1_CDMA2000_TUNNELING_EXT',
        '1081' => 'S1_DOWNLINK_UE_ASSOCIATED_LPPA_TRANSPORT',
        '2067' => 'S1_ENB_CONFIGURATION_TRANSFER',
        '1068' => 'S1_ENB_CONFIGURATION_UPDATE',
        '1069' => 'S1_ENB_CONFIGURATION_UPDATE_ACKNOWLEDGE',
        '1070' => 'S1_ENB_CONFIGURATION_UPDATE_FAILURE',
        '1074' => 'S1_ENB_DIRECT_INFORMATION_TRANSFER',
        '1026' => 'S1_ENB_STATUS_TRANSFER',
        '1049' => 'S1_ERAB_MODIFY_REQUEST',
        '1050' => 'S1_ERAB_MODIFY_RESPONSE',
        '1051' => 'S1_ERAB_RELEASE_COMMAND',
        '1080' => 'S1_ERAB_RELEASE_INDICATION',
        '1052' => 'S1_ERAB_RELEASE_RESPONSE',
        '1054' => 'S1_ERAB_SETUP_REQUEST',
        '1055' => 'S1_ERAB_SETUP_RESPONSE',
        '1027' => 'S1_ERROR_INDICATION',
        '1028' => 'S1_HANDOVER_CANCEL',
        '1029' => 'S1_HANDOVER_CANCEL_ACKNOWLEDGE',
        '1030' => 'S1_HANDOVER_COMMAND',
        '1031' => 'S1_HANDOVER_FAILURE',
        '1032' => 'S1_HANDOVER_NOTIFY',
        '1033' => 'S1_HANDOVER_PREPARATION_FAILURE',
        '1034' => 'S1_HANDOVER_REQUEST',
        '1035' => 'S1_HANDOVER_REQUEST_ACKNOWLEDGE',
        '1036' => 'S1_HANDOVER_REQUIRED',
        '1037' => 'S1_INITIAL_CONTEXT_SETUP_FAILURE',
        '1038' => 'S1_INITIAL_CONTEXT_SETUP_REQUEST',
        '1039' => 'S1_INITIAL_CONTEXT_SETUP_RESPONSE',
        '1040' => 'S1_INITIAL_UE_MESSAGE',
        '1078' => 'S1_KILL_REQUEST',
        '1079' => 'S1_KILL_RESPONSE',
        '2064' => 'S1_LOCATION_REPORT',
        '2063' => 'S1_LOCATION_REPORTING_CONTROL',
        '2065' => 'S1_LOCATION_REPORT_FAILURE_INDICATION',
        '2066' => 'S1_MME_CONFIGURATION_TRANSFER',
        '1071' => 'S1_MME_CONFIGURATION_UPDATE',
        '1072' => 'S1_MME_CONFIGURATION_UPDATE_ACKNOWLEDGE',
        '1073' => 'S1_MME_CONFIGURATION_UPDATE_FAILURE',
        '1075' => 'S1_MME_DIRECT_INFORMATION_TRANSFER',
        '1041' => 'S1_MME_STATUS_TRANSFER',
        '1042' => 'S1_NAS_NON_DELIVERY_INDICATION',
        '1087' => 'S1_OVERLOAD_START',
        '1088' => 'S1_OVERLOAD_STOP',
        '1043' => 'S1_PAGING',
        '1044' => 'S1_PATH_SWITCH_REQUEST',
        '1045' => 'S1_PATH_SWITCH_REQUEST_ACKNOWLEDGE',
        '1046' => 'S1_PATH_SWITCH_REQUEST_FAILURE',
        '1047' => 'S1_RESET',
        '1048' => 'S1_RESET_ACKNOWLEDGE',
        '1056' => 'S1_S1_SETUP_FAILURE',
        '1057' => 'S1_S1_SETUP_REQUEST',
        '1058' => 'S1_S1_SETUP_RESPONSE',
        '1089' => 'S1_THROUGHPUT_ESTIMATION_REQUEST',
        '1090' => 'S1_THROUGHPUT_ESTIMATION_RESPONSE',
        '1059' => 'S1_UE_CAPABILITY_INFO_INDICATION',
        '1060' => 'S1_UE_CONTEXT_MODIFICATION_FAILURE',
        '1061' => 'S1_UE_CONTEXT_MODIFICATION_REQUEST',
        '1062' => 'S1_UE_CONTEXT_MODIFICATION_RESPONSE',
        '1063' => 'S1_UE_CONTEXT_RELEASE_COMMAND',
        '1064' => 'S1_UE_CONTEXT_RELEASE_COMPLETE',
        '1065' => 'S1_UE_CONTEXT_RELEASE_REQUEST',
        '1067' => 'S1_UPLINK_NAS_TRANSPORT',
        '1084' => 'S1_UPLINK_NON_UE_ASSOCIATED_LPPA_TRANSPORT',
        '1066' => 'S1_UPLINK_S1_CDMA2000_TUNNELING',
        '1082' => 'S1_UPLINK_UE_ASSOCIATED_LPPA_TRANSPORT',
        '1085' => 'S1_WIFI_ACCESS_DECISION_REQUEST',
        '1086' => 'S1_WIFI_ACCESS_DECISION_RESPONSE',
        '1076' => 'S1_WRITE_REPLACE_WARNING_REQUEST',
        '1077' => 'S1_WRITE_REPLACE_WARNING_RESPONSE',
        '5155' => 'UE_MEAS_EVENT_FEAT_NOT_AVAIL',
        '5156' => 'UE_MEAS_EVENT_NOT_CONFIG',
        '5269' => 'UE_MEAS_GERAN1',
        '5270' => 'UE_MEAS_GERAN2',
        '5267' => 'UE_MEAS_INTERFREQ1',
        '5268' => 'UE_MEAS_INTERFREQ2',
        '5153' => 'UE_MEAS_INTRAFREQ1',
        '5154' => 'UE_MEAS_INTRAFREQ2',
        '5271' => 'UE_MEAS_UTRAN1',
        '5272' => 'UE_MEAS_UTRAN2',
        '2078' => 'X2_CELL_ACTIVATION_FAILURE',
        '2076' => 'X2_CELL_ACTIVATION_REQUEST',
        '2077' => 'X2_CELL_ACTIVATION_RESPONSE',
        '2073' => 'X2_CONTEXT_FETCH_FAILURE',
        '2071' => 'X2_CONTEXT_FETCH_REQUEST',
        '2072' => 'X2_CONTEXT_FETCH_RESPONSE',
        '2074' => 'X2_CONTEXT_FETCH_RESPONSE_ACCEPT',
        '2053' => 'X2_ENB_CONFIGURATION_UPDATE',
        '2054' => 'X2_ENB_CONFIGURATION_UPDATE_ACKNOWLEDGE',
        '2055' => 'X2_ENB_CONFIGURATION_UPDATE_FAILURE',
        '2052' => 'X2_ERROR_INDICATION',
        '2057' => 'X2_HANDOVER_CANCEL',
        '2062' => 'X2_HANDOVER_PREPARATION_FAILURE',
        '2070' => 'X2_HANDOVER_REPORT',
        '2058' => 'X2_HANDOVER_REQUEST',
        '2059' => 'X2_HANDOVER_REQUEST_ACKNOWLEDGE',
        '2068' => 'X2_PRIVATE_MESSAGE',
        '2082' => 'X2_PROPRIETARY_CELL_SLEEP_FAILURE',
        '2081' => 'X2_PROPRIETARY_CELL_SLEEP_RESPONSE',
        '2079' => 'X2_PROPRIETARY_CELL_SLEEP_START_REQUEST',
        '2080' => 'X2_PROPRIETARY_CELL_SLEEP_STOP_REQUEST',
        '2048' => 'X2_RESET_REQUEST',
        '2049' => 'X2_RESET_RESPONSE',
        '2069' => 'X2_RLF_INDICATION',
        '2060' => 'X2_SN_STATUS_TRANSFER',
        '2061' => 'X2_UE_CONTEXT_RELEASE',
        '2056' => 'X2_X2_SETUP_FAILURE',
        '2050' => 'X2_X2_SETUP_REQUEST',
        '2051' => 'X2_X2_SETUP_RESPONSE',
        '4140' => 'INTERNAL_PROC_RRC_SCELL_CONFIGURED',
        '5278' => 'INTERNAL_EVENT_RRC_SCELL_DECONFIGURED'
    );

function getEventName($eventId)
{
    global $EVENT_NAMES;
    if (array_key_exists($eventId,$EVENT_NAMES))
    {
        return $EVENT_NAMES[$eventId];
    }
    else
    {
        return $eventId;
    }
}

class CtrStreamed extends DDPObject
{
    var $cols = array
                (
                    'wfName'     => 'Workflow',
                    'FilesCTR'   => 'CTR Files',
                    'EventsCTR'  => 'CTR Events',
                    'gbCTR'      => 'CTR Volume (GB)',
                    'FilesCCTR'  => 'CCTR Files',
                    'EventsCCTR' => 'CCTR Events',
                    'gbCCTR'     => 'CCTR Volume (GB)',
                );

    var $title = "CTR Events Streamed";

    function __construct() {
        parent::__construct("eventsStreamedCTR");
    }

    function getData()
    {
        global $date;
        global $site;
        $sql = "
SELECT
 IFNULL(eniq_workflow_names.name,'Totals') AS wfName,
 FORMAT(SUM(eniq_streaming_ctr_collector.FilesCTR),0) AS FilesCTR,
 FORMAT(SUM(eniq_streaming_ctr_collector.EventsCTR),0) AS EventsCTR,
 ROUND(SUM(eniq_streaming_ctr_collector.BytesCTR)/(1024*1024*1024),2) AS gbCTR,
 FORMAT(SUM(eniq_streaming_ctr_collector.FilesCCTR),0) AS FilesCCTR,
 FORMAT(SUM(eniq_streaming_ctr_collector.EventsCCTR),0) AS EventsCCTR,
 ROUND( SUM(eniq_streaming_ctr_collector.BytesCCTR)/(1024*1024*1024), 2 ) AS gbCCTR
FROM
 eniq_streaming_ctr_collector, eniq_workflow_names, sites
WHERE
 eniq_streaming_ctr_collector.siteid = sites.id AND sites.name = '$site' AND
 eniq_streaming_ctr_collector.wfid = eniq_workflow_names.id AND
 eniq_streaming_ctr_collector.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY eniq_workflow_names.name WITH ROLLUP
";
        $this->populateData($sql);
        return $this->data;
    }
}

class CtrEventDistrib extends DDPObject
{
    var $cols = array
                (
                    'eventType'  => 'Event Type',
                    'eventCount' => 'Event Count',
                    'percent'    => 'Percentage'
                );

    var $title = "CTR Event Distribution";
    var $defaultOrderBy = "eventCount";
    var $defaultOrderDir = "DESC";

    function __construct($total)
    {
        parent::__construct("ctrEventDistrib");
        $this->total = $total;
    }

    function getData()
    {
        global $date;
        global $site;
        global $debug;

        $sql = "
SELECT
 eventId AS eventType,
 SUM(count) AS eventCount,
 ROUND( (SUM(count) * 100) / $this->total, 2) AS percent
FROM
 eniq_ctr_eventdistrib, sites
WHERE
 eniq_ctr_eventdistrib.siteid = sites.id AND sites.name = '$site' AND
 eniq_ctr_eventdistrib.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY eventId
";
        $this->populateData($sql);
        $this->columnTypes['eventType'] = 'string';
        foreach ($this->data as &$row)
        {
            $row['eventType' ] = getEventName($row['eventType']);
            if ( $debug ) { echo "<pre>"; print_r($row); echo "</pre>\n"; }
        }
        return $this->data;
    }
}

class CtumStreamed extends DDPObject
{
  var $cols = array
                (
                    'wfName' => 'Workflow',
                    'Files'  => 'Files',
                    'Events' => 'Events',
                    'gb'     => 'Volume (GB)',
                );
    var $title = "CTUM Events Streamed";
    function __construct()
    {
        parent::__construct("eventsStreamedCTUM");
    }
    function getData()
    {
        global $date;
        global $site;
        $sql = "
SELECT
 IFNULL(eniq_workflow_names.name,'Totals') AS wfName,
 FORMAT(SUM(eniq_streaming_ctum_collector.Files),0) AS Files,
 FORMAT(SUM(eniq_streaming_ctum_collector.Events),0) AS Events,
 ROUND(SUM(eniq_streaming_ctum_collector.Bytes)/(1024*1024*1024),2) AS gb
FROM
 eniq_streaming_ctum_collector, eniq_workflow_names, sites
WHERE
 eniq_streaming_ctum_collector.siteid = sites.id AND sites.name = '$site' AND
 eniq_streaming_ctum_collector.wfid = eniq_workflow_names.id AND
 eniq_streaming_ctum_collector.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY eniq_workflow_names.name WITH ROLLUP
";
        $this->populateData($sql);
        return $this->data;
    }
}
if ( isset($_GET['start']) )
{
   $fromDate = $_GET['start'];
   $toDate = $_GET['end'];
}
else
{
   $fromDate = $date;
   $toDate = $date;
}

echo "<H1>CTR</H1>\n";

echo "<H2>CTR Daily Totals</H2>\n";
$ctrTotalTable = new CtrStreamed();
echo $ctrTotalTable->getHtmlTableStr();

echo "<H2>CTR Hourly Totals</H2>\n";
$sqlParamWriter = new SqlPlotParam();
$graphs = new HTML_Table('border=0');
foreach ( array('Files','Events','Bytes') as $counterType )
{
    $ylabel = $counterType;
    $title = $counterType;
    $counterMod = "";
    if ( strcmp($counterType,'Bytes') == 0 )
    {
      $ylabel = 'GB';
      $title = 'Volume';
      $counterMod = "/(1024*1024*1024)";
    }
    $sqlParam =
      array
        (   'title'       => $title,
            'ylabel'      => $ylabel,
            'type'        => 'sb',
            'sb.barwidth' => '3600',
            'presetagg'   => 'SUM:Hourly',
            'persistent'  => 'false',
            'useragg'     => 'false',
            'querylist'   =>
            array
                (
                    array
                        (
                            'timecol' => 'time',
                            'whatcol' => array( $counterType . 'CTR' . $counterMod => "CTR", $counterType . 'CCTR' . $counterMod => "CCTR" ),
                            'tables'  => "eniq_streaming_ctr_collector, sites",
                            'where'   => "eniq_streaming_ctr_collector.siteid = sites.id AND sites.name = '%s'",
                            'qargs'   => array( 'site' )
                        )
                )
        );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $graphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
}
echo $graphs->toHTML();

echo "<H2>CTR Event Distribution</H2>\n";
$statsDB = new StatsDB();
$row = $statsDB->query("
SELECT eventId, SUM(count) AS eventCount
FROM eniq_ctr_eventdistrib,sites
WHERE
 eniq_ctr_eventdistrib.siteid = sites.id AND sites.name = '$site' AND
 eniq_ctr_eventdistrib.time BETWEEN '$date 00:00:00' AND '$date 23:59:59'
GROUP BY eventId
ORDER BY eventCount DESC");
$totalEvents = 0;
$countByEventId = array();
while ( $row = $statsDB->getNextRow() )
{
    $countByEventId[$row[0]] = $row[1];
    $totalEvents += $row[1];
}
$ctrDistribTable = new CtrEventDistrib($totalEvents);
echo $ctrDistribTable->getClientSortableTableStr();
echo "<br>\n";
if ( $debug ) { echo "<pre>"; print_r($countByEventId); echo "</pre>\n"; }
$sqlParam =
  array
    (
        'title'       => "Event Distribution Per Hour",
        'ylabel'      => "%",
        'type'        => 'sb',
        'sb.barwidth' => '3600',
        'presetagg'   => 'SUM:Hourly',
        'persistent'  => 'false',
        'useragg'     => 'false',
        'querylist'   => array()
    );
$otherIds = array();
$countGraphs = 0;
foreach ($countByEventId as $eventId => $eventCount) {
  if ( $countGraphs < 7 )
    {
        $sqlParam['querylist'][] =
        array
            (
                'timecol' => 'time',
                'whatcol' => array( "precent" => getEventName($eventId) ),
                'tables'  => "eniq_ctr_eventdistrib, sites",
                'where'   => "eniq_ctr_eventdistrib.siteid = sites.id AND sites.name = '%s' AND eniq_ctr_eventdistrib.eventid = $eventId",
                'qargs'   => array( 'site' )
            );
    }
    else
    {
        $otherIds[] = $eventId;
    }
    $countGraphs++;
}
if ( $debug ) { echo "<pre>otherids"; print_r($otherIds); echo "</pre>\n"; }
if ( count($otherIds) > 0 ) {
    $sqlParam['querylist'][] =
      array
        (
            'timecol' => 'time',
            'whatcol' => array( "precent" => "Other" ),
            'tables'  => "eniq_ctr_eventdistrib, sites",
            'where'   => "eniq_ctr_eventdistrib.siteid = sites.id AND sites.name = '%s' AND eniq_ctr_eventdistrib.eventid IN (" . implode(",",$otherIds) . ")",
            'qargs'   => array( 'site' )
        );
}
if ( $debug ) { echo "<pre>querylist"; print_r($sqlParam['querylist']); echo "</pre>\n"; }
$id = $sqlParamWriter->saveParams($sqlParam);
echo $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 800, 600 );

/*
 * CTUM
 */
echo "<H1>CTUM</H1>\n";
echo "<H2>CTUM Daily Totals</H2>\n";
$ctumTotalTable = new CtumStreamed();
echo $ctumTotalTable->getHtmlTableStr();

echo "<H2>CTUM Hourly Totals</H2>\n";
$sqlParamWriter = new SqlPlotParam();
$ctumGraphs = new HTML_Table('border=0');
foreach ( array('Files','Events','Bytes') as $counterType )
{
    $ylabel = $counterType;
    $title = $counterType;
    $counterMod = "";
    if ( strcmp($counterType,'Bytes') == 0 )
    {
       $ylabel = 'GB';
       $title = 'Volume';
       $counterMod = "/(1024*1024*1024)";
    }
    $sqlParam =
      array
        (   'title'       => $title,
            'ylabel'      => $ylabel,
            'type'        => 'sb',
            'sb.barwidth' => '3600',
            'presetagg'   => 'SUM:Hourly',
            'persistent'  => 'false',
            'useragg'     => 'false',
            'querylist'   =>
            array
                (
                    array
                        (
                            'timecol' => 'time',
                            'whatcol' => array( $counterType . $counterMod => $counterType ),
                            'tables'  => "eniq_streaming_ctum_collector, sites",
                            'where'   => "eniq_streaming_ctum_collector.siteid = sites.id AND sites.name = '%s'",
                            'qargs'   => array( 'site' )
                        )
                )
        );
    $id = $sqlParamWriter->saveParams($sqlParam);
    $ctumGraphs->addRow( array( $sqlParamWriter->getImgURL( $id, "$date 00:00:00", "$date 23:59:59", true, 640, 240 ) ) );
}
echo $ctumGraphs->toHTML();

include "../common/finalise.php";
?>
