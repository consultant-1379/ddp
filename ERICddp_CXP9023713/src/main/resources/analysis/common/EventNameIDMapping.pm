#!/usr/bin/perl
######################################################################
#       Description --------- EventNameIDMapping.pm -----------
#       This is a perl module to provide the hash of mapping between event_Ids and Event_names for different sources
#       and returns the hash reference of the corresponding source.
######################################################################

package EventNameIDMapping;
no strict 'refs';
use Exporter;
use vars qw(@ISA @EXPORT);
@ISA = qw(Exporter);
@EXPORT = ("getSourceHashRef");

our %GPEH = (
    0    => "RRC_ACTIVE_SET_UPDATE",
    1    => "RRC_ACTIVE_SET_UPDATE_COMPLETE",
    2    => "RRC_ACTIVE_SET_UPDATE_FAILURE",
    3    => "RRC_CELL_UPDATE",
    4    => "RRC_CELL_UPDATE_CONFIRM",
    5    => "RRC_DOWNLINK_DIRECT_TRANSFER",
    6    => "RRC_MEASUREMENT_CONTROL",
    7    => "RRC_MEASUREMENT_CONTROL_FAILURE",
    8    => "RRC_MEASUREMENT_REPORT",
    9    => "RRC_PAGING_TYPE_2",
    10   => "RRC_RADIO_BEARER_RECONFIGURATION",
    11   => "RRC_RADIO_BEARER_RECONFIGURATION_COMPLETE",
    12   => "RRC_RADIO_BEARER_RECONFIGURATION_FAILURE",
    13   => "RRC_RADIO_BEARER_RELEASE",
    14   => "RRC_RADIO_BEARER_RELEASE_COMPLETE",
    15   => "RRC_RADIO_BEARER_RELEASE_FAILURE",
    16   => "RRC_RADIO_BEARER_SETUP",
    17   => "RRC_RADIO_BEARER_SETUP_COMPLETE",
    18   => "RRC_RADIO_BEARER_SETUP_FAILURE",
    19   => "RRC_RRC_CONNECTION_RELEASE",
    20   => "RRC_RRC_CONNECTION_RELEASE_COMPLETE",
    21   => "RRC_RRC_STATUS",
    22   => "RRC_SECURITY_MODE_COMMAND",
    23   => "RRC_SECURITY_MODE_COMPLETE",
    24   => "RRC_SECURITY_MODE_FAILURE",
    25   => "RRC_SIGNALLING_CONNECTION_RELEASE",
    26   => "RRC_SIGNALLING_CONNECTION_RELEASE_INDICATION",
    27   => "RRC_UE_CAPABILITY_INFORMATION",
    28   => "RRC_UE_CAPABILITY_INFORMATION_CONFIRM",
    29   => "RRC_UPLINK_DIRECT_TRANSFER",
    30   => "RRC_UTRAN_MOBILITY_INFORMATION_CONFIRM",
    31   => "RRC_INITIAL_DIRECT_TRANSFER",
    33   => "RRC_RRC_CONNECTION_REJECT",
    34   => "RRC_RRC_CONNECTION_REQUEST",
    35   => "RRC_RRC_CONNECTION_SETUP",
    36   => "RRC_RRC_CONNECTION_SETUP_COMPLETE",
    37   => "RRC_SYSTEM_INFORMATION_CHANGE_INDICATION",
    38   => "RRC_HANDOVER_FROM_UTRAN_COMMAND",
    39   => "RRC_HANDOVER_FROM_UTRAN_FAILURE",
    40   => "RRC_PHYSICAL_CHANNEL_RECONFIGURATION",
    41   => "RRC_PHYSICAL_CHANNEL_RECONFIGURATION_COMPLETE",
    42   => "RRC_PHYSICAL_CHANNEL_RECONFIGURATION_FAILURE",
    43   => "RRC_UTRAN_MOBILITY_INFORMATION",
    44   => "RRC_UTRAN_MOBILITY_INFORMATION_FAILURE",
    45   => "RRC_CELL_CHANGE_ORDER_FROM_UTRAN",
    46   => "RRC_CELL_CHANGE_ORDER_FROM_UTRAN_FAILURE",
    47   => "RRC_UE_CAPABILITY_ENQUIRY",
    48   => "RRC_URA_UPDATE",
    49   => "RRC_URA_UPDATE_CONFIRM",
    50   => "RRC_TRANSPORT_CHANNEL_RECONFIGURATION",
    51   => "RRC_TRANSPORT_CHANNEL_RECONFIGURATION_COMPLETE",
    52   => "RRC_MBMS_GENERAL_INFORMATION",
    53   => "RRC_MBMS_MODIFIED_SERVICES_INFORMATION",
    54   => "RRC_MBMS_UNMODIFIED_SERVICES_INFORMATION",
    55   => "RRC_MBMS_COMMON_P_T_M_RB_INFORMATION",
    56   => "RRC_MBMS_CURRENT_CELL_P_T_M_RB_INFORMATION",
    57   => "RRC_MBMS_NEIGHBOURING_CELL_P_T_M_RB_INFORMATION",
    58   => "RRC_TRANSPORT_FORMAT_COMBINATION_CONTROL_FAILURE",
    59   => "RRC_TRANSPORT_FORMAT_COMBINATION_CONTROL",
    60   => "RRC_HANDOVER_TO_UTRAN_COMPLETE",
    128  => "NBAP_RADIO_LINK_SETUP_REQUEST",
    129  => "NBAP_RADIO_LINK_SETUP_RESPONSE",
    130  => "NBAP_RADIO_LINK_SETUP_FAILURE",
    131  => "NBAP_RADIO_LINK_ADDITION_REQUEST",
    132  => "NBAP_RADIO_LINK_ADDITION_RESPONSE",
    133  => "NBAP_RADIO_LINK_ADDITION_FAILURE",
    134  => "NBAP_RADIO_LINK_RECONFIGURATION_PREPARE",
    135  => "NBAP_RADIO_LINK_RECONFIGURATION_READY",
    136  => "NBAP_RADIO_LINK_RECONFIGURATION_FAILURE",
    137  => "NBAP_RADIO_LINK_RECONFIGURATION_COMMIT",
    138  => "NBAP_RADIO_LINK_RECONFIGURATION_CANCEL",
    139  => "NBAP_RADIO_LINK_DELETION_REQUEST",
    140  => "NBAP_RADIO_LINK_DELETION_RESPONSE",
    141  => "NBAP_DL_POWER_CONTROL_REQUEST",
    142  => "NBAP_DEDICATED_MEASUREMENT_INITIATION_REQUEST",
    143  => "NBAP_DEDICATED_MEASUREMENT_INITIATION_RESPONSE",
    144  => "NBAP_DEDICATED_MEASUREMENT_INITIATION_FAILURE",
    145  => "NBAP_DEDICATED_MEASUREMENT_REPORT",
    146  => "NBAP_DEDICATED_MEASUREMENT_TERMINATION_REQUEST",
    147  => "NBAP_DEDICATED_MEASUREMENT_FAILURE_INDICATION",
    148  => "NBAP_RADIO_LINK_FAILURE_INDICATION",
    149  => "NBAP_RADIO_LINK_RESTORE_INDICATION",
    150  => "NBAP_ERROR_INDICATION",
    151  => "NBAP_COMMON_TRANSPORT_CHANNEL_SETUP_REQUEST",
    152  => "NBAP_COMMON_TRANSPORT_CHANNEL_SETUP_RESPONSE",
    153  => "NBAP_COMMON_TRANSPORT_CHANNEL_SETUP_FAILURE",
    154  => "NBAP_COMMON_TRANSPORT_CHANNEL_RECONFIGURATION_REQUEST",
    155  => "NBAP_COMMON_TRANSPORT_CHANNEL_RECONFIGURATION_RESPONSE",
    156  => "NBAP_COMMON_TRANSPORT_CHANNEL_RECONFIGURATION_FAILURE",
    157  => "NBAP_COMMON_TRANSPORT_CHANNEL_DELETION_REQUEST",
    158  => "NBAP_COMMON_TRANSPORT_CHANNEL_DELETION_RESPONSE",
    159  => "NBAP_AUDIT_REQUIRED_INDICATION",
    160  => "NBAP_AUDIT_REQUEST",
    161  => "NBAP_AUDIT_RESPONSE",
    162  => "NBAP_AUDIT_FAILURE",
    163  => "NBAP_COMMON_MEASUREMENT_INITIATION_REQUEST",
    164  => "NBAP_COMMON_MEASUREMENT_INITIATION_RESPONSE",
    165  => "NBAP_COMMON_MEASUREMENT_INITIATION_FAILURE",
    166  => "NBAP_COMMON_MEASUREMENT_REPORT",
    167  => "NBAP_COMMON_MEASUREMENT_TERMINATION_REQUEST",
    168  => "NBAP_COMMON_MEASUREMENT_FAILURE_INDICATION",
    169  => "NBAP_CELL_SETUP_REQUEST",
    170  => "NBAP_CELL_SETUP_RESPONSE",
    171  => "NBAP_CELL_SETUP_FAILURE",
    172  => "NBAP_CELL_RECONFIGURATION_REQUEST",
    173  => "NBAP_CELL_RECONFIGURATION_RESPONSE",
    174  => "NBAP_CELL_RECONFIGURATION_FAILURE",
    175  => "NBAP_CELL_DELETION_REQUEST",
    176  => "NBAP_CELL_DELETION_RESPONSE",
    177  => "NBAP_RESOURCE_STATUS_INDICATION",
    178  => "NBAP_SYSTEM_INFORMATION_UPDATE_REQUEST",
    179  => "NBAP_SYSTEM_INFORMATION_UPDATE_RESPONSE",
    180  => "NBAP_SYSTEM_INFORMATION_UPDATE_FAILURE",
    181  => "NBAP_RESET_REQUEST",
    182  => "NBAP_RESET_RESPONSE",
    183  => "NBAP_COMPRESSED_MODE_COMMAND",
    184  => "NBAP_RADIO_LINK_PARAMETER_UPDATE_INDICATION",
    185  => "NBAP_PHYSICAL_SHARED_CHANNEL_RECONFIGURATION_REQUEST",
    186  => "NBAP_PHYSICAL_SHARED_CHANNEL_RECONFIGURATION_RESPONSE",
    187  => "NBAP_PHYSICAL_SHARED_CHANNEL_RECONFIGURATION_FAILURE",
    188  => "NBAP_MBMS_NOTIFICATION_UPDATE_COMMAND",
    189  => "NBAP_SECONDARY_UL_FREQUENCY_REPORT",
    190  => "NBAP_SECONDARY_UL_FREQUENCY_UPDATE_INDICATION",
    191  => "NBAP_RADIO_LINK_PREEMPTION_REQUIRED_INDICATION",
    192  => "NBAP_RADIO_LINK_ACTIVATION_COMMAND",
    256  => "RANAP_RAB_ASSIGNMENT_REQUEST",
    257  => "RANAP_RAB_ASSIGNMENT_RESPONSE",
    258  => "RANAP_IU_RELEASE_REQUEST",
    259  => "RANAP_IU_RELEASE_COMMAND",
    260  => "RANAP_IU_RELEASE_COMPLETE",
    261  => "RANAP_SECURITY_MODE_COMMAND",
    262  => "RANAP_SECURITY_MODE_COMPLETE",
    263  => "RANAP_SECURITY_MODE_REJECT",
    264  => "RANAP_LOCATION_REPORTING_CONTROL",
    265  => "RANAP_DIRECT_TRANSFER",
    266  => "RANAP_ERROR_INDICATION",
    267  => "RANAP_PAGING",
    268  => "RANAP_COMMON_ID",
    269  => "RANAP_INITIAL_UE_MESSAGE",
    270  => "RANAP_RESET",
    271  => "RANAP_RESET_ACKNOWLEDGE",
    272  => "RANAP_RESET_RESOURCE",
    273  => "RANAP_RESET_RESOURCE_ACKNOWLEDGE",
    274  => "RANAP_RELOCATION_REQUIRED",
    275  => "RANAP_RELOCATION_REQUEST",
    276  => "RANAP_RELOCATION_REQUEST_ACKNOWLEDGE",
    277  => "RANAP_RELOCATION_COMMAND",
    278  => "RANAP_RELOCATION_DETECT",
    279  => "RANAP_RELOCATION_COMPLETE",
    280  => "RANAP_RELOCATION_PREPARATION_FAILURE",
    281  => "RANAP_RELOCATION_FAILURE",
    282  => "RANAP_RELOCATION_CANCEL",
    283  => "RANAP_RELOCATION_CANCEL_ACKNOWLEDGE",
    284  => "RANAP_SRNS_CONTEXT_REQUEST",
    285  => "RANAP_SRNS_CONTEXT_RESPONSE",
    286  => "RANAP_SRNS_DATA_FORWARD_COMMAND",
    287  => "RANAP_LOCATION_REPORT",
    288  => "RANAP_RANAP_RELOCATION_INFORMATION",
    289  => "RANAP_RAB_RELEASE_REQUEST",
    290  => "RANAP_MBMS_SESSION_START",
    291  => "RANAP_MBMS_SESSION_START_RESPONSE",
    292  => "RANAP_MBMS_SESSION_START_FAILURE",
    293  => "RANAP_MBMS_SESSION_STOP",
    294  => "RANAP_MBMS_SESSION_STOP_RESPONSE",
    384  => "INTERNAL_IMSI",
    385  => "INTERNAL_RADIO_QUALITY_MEASUREMENTS_UEH",
    386  => "INTERNAL_RADIO_QUALITY_MEASUREMENTS_RNH",
    387  => "INTERNAL_CHANNEL_SWITCHING",
    388  => "INTERNAL_UL_OUTER_LOOP_POWER_CONTROL",
    389  => "INTERNAL_DL_POWER_MONITOR_UPDATE",
    390  => "INTERNAL_CELL_LOAD_MONITOR_UPDATE",
    391  => "INTERNAL_ADMISSION_CONTROL_RESPONSE",
    392  => "INTERNAL_CONGESTION_CONTROL_CHANNEL_SWITCH_AND_TERMINATE_RC",
    393  => "INTERNAL_START_CONGESTION",
    394  => "INTERNAL_STOP_CONGESTION",
    395  => "INTERNAL_DOWNLINK_CHANNELIZATION_CODE_ALLOCATION",
    397  => "INTERNAL_IRAT_HO_CC_REPORT_RECEPTION",
    398  => "INTERNAL_IRAT_HO_CC_EVALUATION",
    399  => "INTERNAL_IRAT_HO_CC_EXECUTION",
    400  => "INTERNAL_IRAT_CELL_CHANGE_DISCARDED_DATA",
    401  => "INTERNAL_CMODE_ACTIVATE",
    402  => "INTERNAL_CMODE_DEACTIVATE",
    403  => "INTERNAL_RRC_ERROR",
    404  => "INTERNAL_NBAP_ERROR",
    405  => "INTERNAL_RANAP_ERROR",
    406  => "INTERNAL_SOFT_HANDOVER_REPORT_RECEPTION",
    407  => "INTERNAL_SOFT_HANDOVER_EVALUATION",
    408  => "INTERNAL_SOFT_HANDOVER_EXECUTION",
    409  => "INTERNAL_CALL_REESTABLISHMENT",
    410  => "INTERNAL_MEASUREMENT_HANDLING_EVALUATION",
    411  => "INTERNAL_BMC_CBS_MESSAGE_DISCARDED",
    412  => "INTERNAL_BMC_CBS_MESSAGE_ATT_SCHEDULED",
    413  => "INTERNAL_RNSAP_ERROR",
    414  => "INTERNAL_RC_SUPERVISION",
    415  => "INTERNAL_RAB_ESTABLISHMENT",
    416  => "INTERNAL_RAB_RELEASE",
    417  => "INTERNAL_UE_MOVE",
    418  => "INTERNAL_UPLINK_SCRAMBLING_CODE_ALLOCATION",
    419  => "INTERNAL_MEASUREMENT_HANDLING_EXECUTION",
    420  => "INTERNAL_IFHO_REPORT_RECEPTION",
    421  => "INTERNAL_IFHO_EVALUATION",
    422  => "INTERNAL_IFHO_EXECUTION",
    423  => "INTERNAL_IFHO_EXECUTION_ACTIVE",
    426  => "INTERNAL_RBS_HW_MONITOR_UPDATE",
    427  => "INTERNAL_SOHO_DS_MISSING_NEIGHBOUR",
    428  => "INTERNAL_SOHO_DS_UNMONITORED_NEIGHBOUR",
    429  => "INTERNAL_UE_POSITIONING_QOS",
    430  => "INTERNAL_UE_POSITIONING_UNSUCC",
    431  => "INTERNAL_SYSTEM_BLOCK",
    432  => "INTERNAL_SUCCESSFUL_HSDSCH_CELL_CHANGE",
    433  => "INTERNAL_FAILED_HSDSCH_CELL_CHANGE",
    434  => "INTERNAL_SUCCESSFUL_HSDSCH_CELL_SELECTION_OLD_ACTIVE_SET",
    435  => "INTERNAL_SUCCESSFUL_HSDSCH_CELL_SELECTION_NEW_ACTIVE_SET",
    436  => "INTERNAL_HSDSCH_CELL_SELECTION_NO_CELL_SELECTED",
    437  => "INTERNAL_TWO_NON_USED_FREQ_EXCEEDED",
    438  => "INTERNAL_SYSTEM_RELEASE",
    439  => "INTERNAL_CNHHO_EXECUTION_ACTIVE",
    440  => "INTERNAL_PS_RELEASE_DUE_TO_CNHHO",
    441  => "INTERNAL_PACKET_DEDICATED_THROUGHPUT",
    442  => "INTERNAL_SUCCESSFUL_TRANSITION_TO_DCH",
    443  => "INTERNAL_FAILED_TRANSITION_TO_DCH",
    444  => "INTERNAL_RECORDING_FAULT",
    445  => "INTERNAL_RECORDING_RECOVERED",
    446  => "INTERNAL_PACKET_DEDICATED_THROUGHPUT_STREAMING",
    447  => "INTERNAL_MBMS_SESSION_START_FAILED",
    448  => "INTERNAL_MBMS_SESSION_START_SUCCESS",
    449  => "INTERNAL_MBMS_SESSION_STOP_SYSTEM",
    450  => "INTERNAL_MBMS_SESSION_STOP_NORMAL",
    451  => "INTERNAL_SYSTEM_UTILIZATION",
    452  => "INTERNAL_PCAP_ERROR",
    453  => "INTERNAL_CBS_MESSAGE_ORDER_DISCARDED",
    454  => "INTERNAL_PACKET_DEDICATED_THROUGHPUT_CONV_UNKNOWN",
    455  => "INTERNAL_PACKET_DEDICATED_THROUGHPUT_CONV_SPEECH",
    456  => "INTERNAL_CALL_SETUP_FAIL",
    457  => "INTERNAL_NORMAL_RELEASE",
    458  => "INTERNAL_OUT_HARD_HANDOVER_FAILURE",
    459  => "INTERNAL_RES_CPICH_ECNO",
    460  => "INTERNAL_CN_OVERLOAD_CONTROL_STATUS",
    461  => "INTERNAL_LOAD_CONTROL_ACTION",
    462  => "INTERNAL_LOAD_CONTROL_TRIGGER",
    463  => "INTERNAL_INC_EXT_HARD_HANDOVER_SUCCESS",
    464  => "INTERNAL_INC_EXT_HARD_HANDOVER_FAILURE",
    465  => "INTERNAL_AMR_MAX_RATE_RESTRICTION",
    466  => "INTERNAL_IFLS_NO_CANDIDATE_EVALUATION",
    467  => "INTERNAL_IFLS_EVALUATION_RESULT",
    468  => "INTERNAL_ANR_ADD_RELATION_PREVENTED",
    469  => "INTERNAL_EVENT_5A",
    470  => "INTERNAL_ACCESS_CLASS_BARRING",
    475  => "INTERNAL_OUT_HARD_HANDOVER_SUCCESS",
    476  => "INTERNAL_POWER_SAVE_ACTION",
    477  => "INTERNAL_PS_IDT_QUEUING_RESULT",
    512  => "RNSAP_COMMON_TRANSPORT_CHANNEL_RESOURCES_RELEASE_REQUEST",
    513  => "RNSAP_COMMON_TRANSPORT_CHANNEL_RESOURCES_REQUEST",
    514  => "RNSAP_COMMON_TRANSPORT_CHANNEL_RESOURCES_RESPONSE",
    515  => "RNSAP_COMMON_TRANSPORT_CHANNEL_RESOURCES_FAILURE",
    516  => "RNSAP_RADIO_LINK_SETUP_REQUEST",
    517  => "RNSAP_RADIO_LINK_SETUP_RESPONSE",
    518  => "RNSAP_RADIO_LINK_SETUP_FAILURE",
    519  => "RNSAP_RADIO_LINK_ADDITION_REQUEST",
    520  => "RNSAP_RADIO_LINK_ADDITION_RESPONSE",
    521  => "RNSAP_RADIO_LINK_ADDITION_FAILURE",
    522  => "RNSAP_RADIO_LINK_RECONFIGURATION_PREPARE",
    523  => "RNSAP_RADIO_LINK_RECONFIGURATION_READY",
    524  => "RNSAP_RADIO_LINK_RECONFIGURATION_FAILURE",
    525  => "RNSAP_RADIO_LINK_RECONFIGURATION_COMMIT",
    526  => "RNSAP_RADIO_LINK_RECONFIGURATION_CANCEL",
    527  => "RNSAP_RADIO_LINK_DELETION_REQUEST",
    528  => "RNSAP_RADIO_LINK_DELETION_RESPONSE",
    529  => "RNSAP_DL_POWER_CONTROL_REQUEST",
    530  => "RNSAP_DEDICATED_MEASUREMENT_INITIATION_REQUEST",
    531  => "RNSAP_DEDICATED_MEASUREMENT_INITIATION_RESPONSE",
    532  => "RNSAP_DEDICATED_MEASUREMENT_INITIATION_FAILURE",
    533  => "RNSAP_DEDICATED_MEASUREMENT_REPORT",
    534  => "RNSAP_DEDICATED_MEASUREMENT_TERMINATION_REQUEST",
    535  => "RNSAP_DEDICATED_MEASUREMENT_FAILURE_INDICATION",
    536  => "RNSAP_RADIO_LINK_FAILURE_INDICATION",
    537  => "RNSAP_RADIO_LINK_RESTORE_INDICATION",
    538  => "RNSAP_COMPRESSED_MODE_COMMAND",
    539  => "RNSAP_ERROR_INDICATION",
    540  => "RNSAP_UPLINK_SIGNALLING_TRANSFER_INDICATION",
    541  => "RNSAP_DOWNLINK_SIGNALLING_TRANSFER_REQUEST",
    542  => "RNSAP_RADIO_LINK_PARAMETER_UPDATE_INDICATION",
    543  => "RNSAP_RADIO_LINK_ACTIVATION_COMMAND",
    640  => "INTERNAL_MEAS_TRANSPORT_CHANNEL_BER",
    641  => "INTERNAL_MEAS_TRANSPORT_CHANNEL_BLER",
    642  => "INTERNAL_MEAS_RLC_BUFFER_INFORMATION",
    643  => "INTERNAL_MEAS_EUL_HARQ_TRANSMISSION",
    768  => "PCAP_ERROR_INDICATION",
    769  => "PCAP_POSITION_INITIATION_REQUEST",
    770  => "PCAP_POSITION_INITIATION_RESPONSE",
    771  => "PCAP_POSITION_INITIATION_FAILURE",
    772  => "PCAP_POSITION_ACTIVATION_REQUEST",
    773  => "PCAP_POSITION_ACTIVATION_RESPONSE",
    774  => "PCAP_POSITION_ACTIVATION_FAILURE",
    896  => "SABP_WRITE_REPLACE",
    897  => "SABP_WRITE_REPLACE_COMPLETE",
    898  => "SABP_WRITE_REPLACE_FAILURE",
    899  => "SABP_KILL",
    900  => "SABP_KILL_COMPLETE",
    901  => "SABP_KILL_FAILURE",
    902  => "SABP_RESET",
    903  => "SABP_RESET_COMPLETE",
    904  => "SABP_RESET_FAILURE",
    905  => "SABP_RESTART",
    906  => "SABP_ERROR_INDICATION",
    907  => "SABP_FAILURE",
    908  => "SABP_MESSAGE_STATUS_QUERY",
    909  => "SABP_MESSAGE_STATUS_QUERY_COMPLETE",
    910  => "SABP_MESSAGE_STATUS_QUERY_FAILURE"
);

our %EPG = (
    0  => "SESSION_DELETION",
    1  => "BEARER_DELETION",
    2  => "SESSION_CREATION",
    3  => "BEARER_CREATION",
    4  => "BEARER_MODIFICATION",
    5  => "BEARER_UPDATE",
    6  => "SESSION_INFO",
    7  => "DATA_USAGE",
    8  => "SESSION_SUSPENSION",
    9  => "SESSION_RESUME",
    10 => "DOWNLINK_DATA_NOTIFICATION",
    11 => "P_SESSION_CREATION",
    12 => "P_SESSION_DELETION",
    13 => "P_BEARER_CREATION",
    14 => "P_BEARER_MODIFICATION",
    15 => "P_BEARER_UPDATE",
    16 => "P_BEARER_DELETION"
);

our %EBM = (
    0   => "ATTACH",
    1   => "ACTIVATE",
    2   => "RAU",
    3   => "ISRAU",
    4   => "DEACTIVATE",
    5   => "L_ATTACH",
    6   => "L_DETACH",
    7   => "L_HANDOVER",
    8   => "L_TAU",
    9   => "L_DEDICATED_BEARER_ACTIVATE",
    10  => "L_DEDICATED_BEARER_DEACTIVATE",
    11  => "L_PDN_CONNECT",
    12  => "L_PDN_DISCONNECT",
    13  => "L_SERVICE_REQUEST",
    14  => "DETACH",
    15  => "SERVICE_REQUEST",
    16  => "L_BEARER_MODIFY",
    17  => "L_CDMA2000"
);

our %EBM2G3G = (
    0   => "ATTACH",
    1   => "ACTIVATE",
    2   => "RAU",
    3   => "ISRAU",
    4   => "DEACTIVATE",
    5   => "L_ATTACH",
    6   => "L_DETACH",
    7   => "L_HANDOVER",
    8   => "L_TAU",
    9   => "L_DEDICATED_BEARER_ACTIVATE",
    10  => "L_DEDICATED_BEARER_DEACTIVATE",
    11  => "L_PDN_CONNECT",
    12  => "L_PDN_DISCONNECT",
    13  => "L_SERVICE_REQUEST",
    14  => "DETACH",
    15  => "SERVICE_REQUEST",
    16  => "L_BEARER_MODIFY",
    17  => "L_CDMA2000"
);

our %CTR = (
    0     =>  "RRC_RRC_CONNECTION_SETUP",
    1     =>  "RRC_RRC_CONNECTION_REJECT",
    2     =>  "RRC_RRC_CONNECTION_REQUEST",
    3     =>  "RRC_RRC_CONNECTION_RE_ESTABLISHMENT_REQUEST",
    4     =>  "RRC_RRC_CONNECTION_RE_ESTABLISHMENT_REJECT",
    5     =>  "RRC_RRC_CONNECTION_RELEASE",
    6     =>  "RRC_DL_INFORMATION_TRANSFER",
    7     =>  "RRC_MOBILITY_FROM_E_UTRA_COMMAND",
    8     =>  "RRC_RRC_CONNECTION_RECONFIGURATION",
    9     =>  "RRC_SECURITY_MODE_COMMAND",
    10    =>  "RRC_UE_CAPABILITY_ENQUIRY",
    11    =>  "RRC_MEASUREMENT_REPORT",
    12    =>  "RRC_RRC_CONNECTION_SETUP_COMPLETE",
    13    =>  "RRC_RRC_CONNECTION_RECONFIGURATION_COMPLETE",
    16    =>  "RRC_UL_INFORMATION_TRANSFER",
    17    =>  "RRC_SECURITY_MODE_COMPLETE",
    18    =>  "RRC_SECURITY_MODE_FAILURE",
    19    =>  "RRC_UE_CAPABILITY_INFORMATION",
    21    =>  "RRC_MASTER_INFORMATION_BLOCK",
    22    =>  "RRC_SYSTEM_INFORMATION",
    23    =>  "RRC_SYSTEM_INFORMATION_BLOCK_TYPE_1",
    24    =>  "RRC_CONNECTION_RE_ESTABLISHMENT",
    25    =>  "RRC_CONNECTION_RE_ESTABLISHMENT_COMPLETE",
    26    =>  "RRC_UE_INFORMATION_RESPONSE",
    27    =>  "RRC_UE_INFORMATION_REQUEST",
    28    =>  "RRC_MBSFNAREA_CONFIGURATION",
    29    =>  "RRC_CSFB_PARAMETERS_REQUEST_CDMA2000",
    30    =>  "RRC_CSFB_PARAMETERS_RESPONSE_CDMA2000",
    31    =>  "RRC_HANDOVER_FROM_EUTRA_PREPARATION_REQUEST",
    32    =>  "RRC_UL_HANDOVER_PREPARATION_TRANSFER",
    33    =>  "RRC_MBMS_INTEREST_INDICATION",
    34    =>  "RRC_INTER_FREQ_RSTD_MEASUREMENT_INDICATION",
    1024  =>  "S1_DOWNLINK_S1_CDMA2000_TUNNELING",
    1025  =>  "S1_DOWNLINK_NAS_TRANSPORT",
    1026  =>  "S1_ENB_STATUS_TRANSFER",
    1027  =>  "S1_ERROR_INDICATION",
    1028  =>  "S1_HANDOVER_CANCEL",
    1029  =>  "S1_HANDOVER_CANCEL_ACKNOWLEDGE",
    1030  =>  "S1_HANDOVER_COMMAND",
    1031  =>  "S1_HANDOVER_FAILURE",
    1032  =>  "S1_HANDOVER_NOTIFY",
    1033  =>  "S1_HANDOVER_PREPARATION_FAILURE",
    1034  =>  "S1_HANDOVER_REQUEST",
    1035  =>  "S1_HANDOVER_REQUEST_ACKNOWLEDGE",
    1036  =>  "S1_HANDOVER_REQUIRED",
    1037  =>  "S1_INITIAL_CONTEXT_SETUP_FAILURE",
    1038  =>  "S1_INITIAL_CONTEXT_SETUP_REQUEST",
    1039  =>  "S1_INITIAL_CONTEXT_SETUP_RESPONSE",
    1040  =>  "S1_INITIAL_UE_MESSAGE",
    1041  =>  "S1_MME_STATUS_TRANSFER",
    1042  =>  "S1_NAS_NON_DELIVERY_INDICATION",
    1043  =>  "S1_PAGING",
    1044  =>  "S1_PATH_SWITCH_REQUEST",
    1045  =>  "S1_PATH_SWITCH_REQUEST_ACKNOWLEDGE",
    1046  =>  "S1_PATH_SWITCH_REQUEST_FAILURE",
    1047  =>  "S1_RESET",
    1048  =>  "S1_RESET_ACKNOWLEDGE",
    1049  =>  "S1_ERAB_MODIFY_REQUEST",
    1050  =>  "S1_ERAB_MODIFY_RESPONSE",
    1051  =>  "S1_ERAB_RELEASE_COMMAND",
    1052  =>  "S1_ERAB_RELEASE_RESPONSE",
    1053  =>  "S1_ERAB_RELEASE_REQUEST",
    1054  =>  "S1_ERAB_SETUP_REQUEST",
    1055  =>  "S1_ERAB_SETUP_RESPONSE",
    1056  =>  "S1_S1_SETUP_FAILURE",
    1057  =>  "S1_S1_SETUP_REQUEST",
    1058  =>  "S1_S1_SETUP_RESPONSE",
    1059  =>  "S1_UE_CAPABILITY_INFO_INDICATION",
    1060  =>  "S1_UE_CONTEXT_MODIFICATION_FAILURE",
    1061  =>  "S1_UE_CONTEXT_MODIFICATION_REQUEST",
    1062  =>  "S1_UE_CONTEXT_MODIFICATION_RESPONSE",
    1063  =>  "S1_UE_CONTEXT_RELEASE_COMMAND",
    1064  =>  "S1_UE_CONTEXT_RELEASE_COMPLETE",
    1065  =>  "S1_UE_CONTEXT_RELEASE_REQUEST",
    1066  =>  "S1_UPLINK_S1_CDMA2000_TUNNELING",
    1067  =>  "S1_UPLINK_NAS_TRANSPORT",
    1068  =>  "S1_ENB_CONFIGURATION_UPDATE",
    1069  =>  "S1_ENB_CONFIGURATION_UPDATE_ACKNOWLEDGE",
    1070  =>  "S1_ENB_CONFIGURATION_UPDATE_FAILURE",
    1071  =>  "S1_MME_CONFIGURATION_UPDATE",
    1072  =>  "S1_MME_CONFIGURATION_UPDATE_ACKNOWLEDGE",
    1073  =>  "S1_MME_CONFIGURATION_UPDATE_FAILURE",
    1074  =>  "S1_ENB_DIRECT_INFORMATION_TRANSFER",
    1075  =>  "S1_MME_DIRECT_INFORMATION_TRANSFER",
    1076  =>  "S1_WRITE_REPLACE_WARNING_REQUEST",
    1077  =>  "S1_WRITE_REPLACE_WARNING_RESPONSE",
    1078  =>  "S1_KILL_REQUEST",
    1079  =>  "S1_KILL_RESPONSE",
    1080  =>  "S1_ERAB_RELEASE_INDICATION",
    1081  =>  "S1_DOWNLINK_UE_ASSOCIATED_LPPA_TRANSPORT",
    1082  =>  "S1_UPLINK_UE_ASSOCIATED_LPPA_TRANSPORT",
    1083  =>  "S1_DOWNLINK_NON_UE_ASSOCIATED_LPPA_TRANSPORT",
    1084  =>  "S1_UPLINK_NON_UE_ASSOCIATED_LPPA_TRANSPORT",
    1087  =>  "S1_OVERLOAD_START",
    1088  =>  "S1_OVERLOAD_STOP",
    2048  =>  "X2_RESET_REQUEST",
    2049  =>  "X2_RESET_RESPONSE",
    2050  =>  "X2_X2_SETUP_REQUEST",
    2051  =>  "X2_X2_SETUP_RESPONSE",
    2052  =>  "X2_ERROR_INDICATION",
    2053  =>  "X2_ENB_CONFIGURATION_UPDATE",
    2054  =>  "X2_ENB_CONFIGURATION_UPDATE_ACKNOWLEDGE",
    2055  =>  "X2_ENB_CONFIGURATION_UPDATE_FAILURE",
    2056  =>  "X2_X2_SETUP_FAILURE",
    2057  =>  "X2_HANDOVER_CANCEL",
    2058  =>  "X2_HANDOVER_REQUEST",
    2059  =>  "X2_HANDOVER_REQUEST_ACKNOWLEDGE",
    2060  =>  "X2_SN_STATUS_TRANSFER",
    2061  =>  "X2_UE_CONTEXT_RELEASE",
    2062  =>  "X2_HANDOVER_PREPARATION_FAILURE",
    2063  =>  "S1_LOCATION_REPORTING_CONTROL",
    2064  =>  "S1_LOCATION_REPORT",
    2065  =>  "S1_LOCATION_REPORT_FAILURE_INDICATION",
    2066  =>  "S1_MME_CONFIGURATION_TRANSFER",
    2067  =>  "S1_ENB_CONFIGURATION_TRANSFER",
    2068  =>  "X2_PRIVATE_MESSAGE",
    2069  =>  "X2_RLF_INDICATION",
    2070  =>  "X2_HANDOVER_REPORT",
    2071  =>  "X2_CONTEXT_FETCH_REQUEST",
    2072  =>  "X2_CONTEXT_FETCH_RESPONSE",
    2073  =>  "X2_CONTEXT_FETCH_FAILURE",
    2074  =>  "X2_CONTEXT_FETCH_RESPONSE_ACCEPT",
    2075  =>  "X2_CONTEXT_FETCH_RESPONSE_REJECT",
    3072  =>  "INTERNAL_PER_RADIO_UTILIZATION",
    3074  =>  "INTERNAL_PER_UE_ACTIVE_SESSION_TIME",
    3075  =>  "INTERNAL_PER_RADIO_UE_MEASUREMENT",
    3076  =>  "INTERNAL_PER_UE_TRAFFIC_REP",
    3077  =>  "INTERNAL_PER_UE_RB_TRAFFIC_REP",
    3078  =>  "INTERNAL_PER_CAP_LICENSE_UTIL_REP",
    3079  =>  "INTERNAL_PER_CELL_TRAFFIC_REPORT",
    3081  =>  "INTERNAL_PER_RADIO_CELL_MEASUREMENT",
    3083  =>  "INTERNAL_PER_RADIO_CELL_MEASUREMENT_TDD",
    3084  =>  "INTERNAL_PER_PROCESSOR_LOAD",
    3085  =>  "INTERNAL_PER_PRB_LICENSE_UTIL_REP",
    3086  =>  "INTERNAL_PER_CELL_QCI_TRAFFIC_REP",
    3087  =>  "INTERNAL_PER_UE_LCG_TRAFFIC_REP",
    3088  =>  "INTERNAL_PER_RADIO_CELL_NOISE_INTERFERENCE_PRB",
    3089  =>  "INTERNAL_PER_RADIO_CELL_CQI_SUBBAND",
    3090  =>  "INTERNAL_PER_UETR_RADIO_UTILIZATION",
    3091  =>  "INTERNAL_PER_UETR_UE_ACTIVE_SESSION_TIME",
    3092  =>  "INTERNAL_PER_UETR_RADIO_UE_MEASUREMENT",
    3093  =>  "INTERNAL_PER_UETR_UE_TRAFFIC_REP",
    3094  =>  "INTERNAL_PER_UETR_UE_RB_TRAFFIC_REP",
    3095  =>  "INTERNAL_PER_UETR_CELL_QCI_TRAFFIC_REP",
    3096  =>  "INTERNAL_PER_UETR_UE_LCG_TRAFFIC_REP",
    3097  =>  "INTERNAL_PER_UETR_CAP_LICENSE_UTIL_REP",
    3098  =>  "INTERNAL_PER_UETR_PRB_LICENSE_UTIL_REP",
    3099  =>  "INTERNAL_PER_UETR_CELL_TRAFFIC_REPORT",
    3101  =>  "INTERNAL_PER_UETR_RADIO_CELL_MEASUREMENT",
    3102  =>  "INTERNAL_PER_UETR_RADIO_CELL_NOISE_INTERFERENCE_PRB",
    3103  =>  "INTERNAL_PER_UETR_RADIO_CELL_CQI_SUBBAND",
    3105  =>  "INTERNAL_PER_UETR_RADIO_CELL_MEASUREMENT_TDD",
    3106  =>  "INTERNAL_PER_EVENT_ETWS_REPET_COMPL",
    3107  =>  "INTERNAL_PER_EVENT_CMAS_REPET_COMPL",
    3108  =>  "INTERNAL_PER_RADIO_UE_MEASUREMENT_TA",
    4097  =>  "INTERNAL_PROC_RRC_CONN_SETUP",
    4098  =>  "INTERNAL_PROC_S1_SIG_CONN_SETUP",
    4099  =>  "INTERNAL_PROC_ERAB_SETUP",
    4102  =>  "INTERNAL_PROC_HO_PREP_S1_OUT",
    4103  =>  "INTERNAL_PROC_HO_PREP_S1_IN",
    4104  =>  "INTERNAL_PROC_HO_EXEC_S1_OUT",
    4105  =>  "INTERNAL_PROC_HO_EXEC_S1_IN",
    4106  =>  "INTERNAL_PROC_INITIAL_CTXT_SETUP",
    4107  =>  "INTERNAL_PROC_DNS_LOOKUP",
    4108  =>  "INTERNAL_PROC_REVERSE_DNS_LOOKUP",
    4109  =>  "INTERNAL_PROC_SCTP_SETUP",
    4110  =>  "INTERNAL_PROC_HO_PREP_X2_OUT",
    4111  =>  "INTERNAL_PROC_HO_PREP_X2_IN",
    4112  =>  "INTERNAL_PROC_HO_EXEC_X2_OUT",
    4113  =>  "INTERNAL_PROC_HO_EXEC_X2_IN",
    4114  =>  "INTERNAL_PROC_ERAB_RELEASE",
    4116  =>  "INTERNAL_PROC_S1_SETUP",
    4117  =>  "INTERNAL_PROC_ANR_CGI_REPORT",
    4118  =>  "INTERNAL_PROC_X2_SETUP",
    4119  =>  "INTERNAL_PROC_S1_TENB_CONF_LOOKUP",
    4120  =>  "INTERNAL_PROC_RRC_CONN_RECONF_NO_MOB",
    4121  =>  "INTERNAL_PROC_RRC_CONNECTION_RE_ESTABLISHMENT",
    4122  =>  "INTERNAL_PROC_ERAB_MODIFY",
    4123  =>  "INTERNAL_PROC_X2_RESET",
    4124  =>  "INTERNAL_PROC_SCTP_SHUTDOWN",
    4125  =>  "INTERNAL_PROC_UE_CTXT_RELEASE",
    4126  =>  "INTERNAL_PROC_UE_CTXT_MODIFY",
    4128  =>  "INTERNAL_PROC_UE_CTXT_FETCH",
    4129  =>  "INTERNAL_PROC_M3_SETUP",
    4130  =>  "INTERNAL_PROC_MBMS_SESSION_START",
    4131  =>  "INTERNAL_PROC_SOFT_LOCK",
    4132  =>  "INTERNAL_PROC_UETR_RRC_SCELL_CONFIGURED",
    4133  =>  "INTERNAL_PROC_MIMO_SLEEP_SWITCHED",
    4134  =>  "INTERNAL_PROC_NON_PLANNED_PCI_CGI_REPORT",
    5120  =>  "INTERNAL_EVENT_RRC_ERROR",
    5123  =>  "INTERNAL_EVENT_NO_RESET_ACK_FROM_MME",
    5124  =>  "INTERNAL_EVENT_S1AP_PROTOCOL_ERROR",
    5127  =>  "INTERNAL_EVENT_PM_RECORDING_FAULT_JVM",
    5128  =>  "INTERNAL_EVENT_MAX_UETRACES_REACHED",
    5131  =>  "INTERNAL_EVENT_UNEXPECTED_RRC_MSG",
    5133  =>  "INTERNAL_EVENT_PM_EVENT_SUSPECTMARKED",
    5134  =>  "INTERNAL_EVENT_INTEGRITY_VER_FAIL_RRC_MSG",
    5136  =>  "INTERNAL_EVENT_X2_CONN_RELEASE",
    5137  =>  "INTERNAL_EVENT_X2AP_PROTOCOL_ERROR",
    5138  =>  "INTERNAL_EVENT_MAX_STORAGESIZE_REACHED",
    5139  =>  "INTERNAL_EVENT_MAX_FILESIZE_REACHED",
    5140  =>  "INTERNAL_EVENT_MAX_FILESIZE_RECOVERY",
    5143  =>  "INTERNAL_EVENT_PM_DATA_COLLECTION_LOST",
    5144  =>  "INTERNAL_EVENT_NEIGHBCELL_CHANGE",
    5145  =>  "INTERNAL_EVENT_NEIGHBENB_CHANGE",
    5146  =>  "INTERNAL_EVENT_NEIGHBREL_ADD",
    5147  =>  "INTERNAL_EVENT_NEIGHBREL_REMOVE",
    5148  =>  "INTERNAL_EVENT_UE_ANR_CONFIG_PCI",
    5149  =>  "INTERNAL_EVENT_UE_ANR_PCI_REPORT",
    5153  =>  "UE_MEAS_INTRAFREQ1",
    5154  =>  "UE_MEAS_INTRAFREQ2",
    5155  =>  "UE_MEAS_EVENT_FEAT_NOT_AVAIL",
    5156  =>  "UE_MEAS_EVENT_NOT_CONFIG",
    5157  =>  "INTERNAL_EVENT_UE_MEAS_FAILURE",
    5159  =>  "INTERNAL_EVENT_ANR_CONFIG_MISSING",
    5162  =>  "INTERNAL_EVENT_LIC_GRACE_PERIOD_STARTED",
    5163  =>  "INTERNAL_EVENT_LIC_GRACE_PERIOD_RESET",
    5164  =>  "INTERNAL_EVENT_LIC_GRACE_PERIOD_EXPIRED",
    5165  =>  "INTERNAL_EVENT_LIC_GRACE_PERIOD_EXPIRING",
    5166  =>  "INTERNAL_EVENT_LICENSE_UNAVAILABLE",
    5167  =>  "INTERNAL_EVENT_EUTRAN_FREQUENCY_ADD",
    5168  =>  "INTERNAL_EVENT_FREQ_REL_ADD",
    5170  =>  "INTERNAL_EVENT_RECOMMENDED_NR_SI_UPDATES_REACHED",
    5171  =>  "INTERNAL_EVENT_IP_ADDR_GET_FAILURE",
    5172  =>  "INTERNAL_EVENT_UE_CAPABILITY",
    5173  =>  "INTERNAL_EVENT_ANR_PCI_REPORT_WANTED",
    5174  =>  "INTERNAL_EVENT_MEAS_CONFIG_A1",
    5175  =>  "INTERNAL_EVENT_MEAS_CONFIG_A2",
    5176  =>  "INTERNAL_EVENT_MEAS_CONFIG_A3",
    5177  =>  "INTERNAL_EVENT_MEAS_CONFIG_A4",
    5178  =>  "INTERNAL_EVENT_MEAS_CONFIG_A5",
    5179  =>  "INTERNAL_EVENT_MEAS_CONFIG_PERIODICAL_EUTRA",
    5180  =>  "INTERNAL_EVENT_MEAS_CONFIG_B2_GERAN",
    5181  =>  "INTERNAL_EVENT_MEAS_CONFIG_B2_UTRA",
    5182  =>  "INTERNAL_EVENT_MEAS_CONFIG_B2_CDMA2000",
    5183  =>  "INTERNAL_EVENT_MEAS_CONFIG_PERIODICAL_GERAN",
    5184  =>  "INTERNAL_EVENT_MEAS_CONFIG_PERIODICAL_UTRA",
    5185  =>  "INTERNAL_UE_MEAS_ABORT",
    5192  =>  "INTERNAL_EVENT_ONGOING_UE_MEAS",
    5193  =>  "INTERNAL_EVENT_UE_MOBILITY_EVAL",
    5194  =>  "INTERNAL_EVENT_NEIGHBCELL_ADDITIONAL_CGI",
    5195  =>  "INTERNAL_EVENT_SON_UE_OSCILLATION_PREVENTED",
    5196  =>  "INTERNAL_EVENT_SON_OSCILLATION_DETECTED",
    5197  =>  "INTERNAL_EVENT_IMLB_CONTROL",
    5198  =>  "INTERNAL_EVENT_IMLB_ACTION",
    5200  =>  "INTERNAL_EVENT_SPID_PRIORITY_IGNORED",
    5201  =>  "INTERNAL_EVENT_RIM_RAN_INFORMATION_RECEIVED",
    5202  =>  "INTERNAL_EVENT_ERAB_DATA_INFO",
    5203  =>  "INTERNAL_EVENT_TOO_EARLY_HO",
    5204  =>  "INTERNAL_EVENT_TOO_LATE_HO",
    5205  =>  "INTERNAL_EVENT_HO_WRONG_CELL",
    5206  =>  "INTERNAL_EVENT_HO_WRONG_CELL_REEST",
    5207  =>  "INTERNAL_EVENT_RRC_UE_INFORMATION",
    5208  =>  "INTERNAL_EVENT_S1_NAS_NON_DELIVERY_INDICATION",
    5209  =>  "INTERNAL_EVENT_S1_ERROR_INDICATION",
    5210  =>  "INTERNAL_EVENT_X2_ERROR_INDICATION",
    5211  =>  "INTERNAL_EVENT_ADMISSION_BLOCKING_STARTED",
    5212  =>  "INTERNAL_EVENT_ADMISSION_BLOCKING_STOPPED",
    5213  =>  "INTERNAL_EVENT_ADMISSION_BLOCKING_UPDATED",
    5214  =>  "INTERNAL_EVENT_ERAB_ROHC_FAIL_LIC_REJECT",
    5215  =>  "INTERNAL_EVENT_LB_INTER_FREQ",
    5217  =>  "INTERNAL_EVENT_PCI_CONFLICT_DETECTED",
    5218  =>  "INTERNAL_EVENT_PCI_CONFLICT_RESOLVED",
    5220  =>  "INTERNAL_EVENT_LB_SUB_RATIO",
    5221  =>  "INTERNAL_EVENT_ETWS_REQ",
    5222  =>  "INTERNAL_EVENT_ETWS_RESP",
    5223  =>  "INTERNAL_EVENT_CMAS_REQ",
    5224  =>  "INTERNAL_EVENT_CMAS_RESP",
    5225  =>  "INTERNAL_EVENT_ETWS_REPET_STOPPED",
    5226  =>  "INTERNAL_EVENT_CMAS_REPET_STOPPED",
    5227  =>  "INTERNAL_EVENT_UE_ANR_CONFIG_PCI_REMOVE",
    5228  =>  "INTERNAL_EVENT_ADV_CELL_SUP_DETECTION",
    5229  =>  "INTERNAL_EVENT_ADV_CELL_SUP_RECOVERY_ATTEMPT",
    5230  =>  "INTERNAL_EVENT_ADV_CELL_SUP_RECOVERY_RESULT",
    5233  =>  "INTERNAL_EVENT_LOAD_CONTROL_STATE_TRANSITION",
    5234  =>  "INTERNAL_EVENT_MEAS_CONFIG_B1_UTRA",
    5235  =>  "INTERNAL_EVENT_MEAS_CONFIG_B1_CDMA2000",
    5236  =>  "INTERNAL_EVENT_CELL_DL_CAPACITY",
    5237  =>  "INTERNAL_EVENT_MBMS_INTEREST_INDICATION",
    5238  =>  "INTERNAL_EVENT_ANR_STOP_MEASURING",
    5239  =>  "INTERNAL_EVENT_MEAS_CONFIG_A6",
    5240  =>  "INTERNAL_EVENT_UETR_MEASUREMENT_REPORT_RECEIVED",
    5241  =>  "INTERNAL_EVENT_UETR_RRC_SCELL_DECONFIGURED",
    5242  =>  "INTERNAL_EVENT_UE_LB_QUAL",
    5243  =>  "INTERNAL_EVENT_UE_LB_MEAS",
    5244  =>  "INTERNAL_EVENT_MIMO_SLEEP_DETECTED",
    8192  =>  "M3_M3_SETUP_REQUEST",
    8193  =>  "M3_M3_SETUP_RESPONSE",
    8194  =>  "M3_M3_SETUP_FAILURE",
    8195  =>  "M3_MBMS_SESSION_START_REQUEST",
    8196  =>  "M3_MBMS_SESSION_START_RESPONSE",
    8197  =>  "M3_MBMS_SESSION_START_FAILURE",
    8198  =>  "M3_MBMS_SESSION_STOP_REQUEST",
    8199  =>  "M3_MBMS_SESSION_STOP_RESPONSE",
    8200  =>  "M3_RESET",
    8201  =>  "M3_MCE_CONFIGURATION_UPDATE_REQUEST",
    8202  =>  "M3_MCE_CONFIGURATION_UPDATE_RESPONSE",
    8203  =>  "M3_MCE_CONFIGURATION_UPDATE_FAILURE",
    9216  =>  "INTERNAL_TESTEVENT_UE",
    9217  =>  "INTERNAL_TESTEVENT_CELL1",
    9218  =>  "INTERNAL_TESTEVENT_EXT",
    9219  =>  "INTERNAL_TESTEVENT_CELL2",
    9220  =>  "INTERNAL_TESTEVENT_BASIC",
    9221  =>  "INTERNAL_TESTEVENT_BB_CELL",
    9222  =>  "INTERNAL_TESTEVENT_BB_RB",
    9223  =>  "INTERNAL_TESTEVENT_BB_UE",
    9224  =>  "INTERNAL_TESTEVENT_BB_BBM",
    9225  =>  "INTERNAL_PROC_TESTEVENT_MANY_START",
    9226  =>  "INTERNAL_PROC_TESTEVENT_POST",
    9227  =>  "INTERNAL_PROC_TESTEVENT_MANY_STOP",
    9228  =>  "INTERNAL_PROC_TESTEVENT_NOPARAM_START",
    9229  =>  "INTERNAL_PROC_TESTEVENT_COMPLEX",
    9233  =>  "INTERNAL_TESTEVENT_PROC_BASE_1",
    9234  =>  "INTERNAL_TESTEVENT_PROC_BASE_2",
    9235  =>  "INTERNAL_TESTEVENT_PROC_BASE_3",
    9236  =>  "INTERNAL_TESTEVENT_PROC_BASE_4",
    9237  =>  "INTERNAL_TESTEVENT_PROC_BASE_5",
    9238  =>  "INTERNAL_TESTEVENT_PROC_BASE_6",
    9239  =>  "INTERNAL_TESTEVENT_PROC_BASE_7",
    9240  =>  "INTERNAL_TESTEVENT_PROC_BASE_8",
    9241  =>  "INTERNAL_TESTEVENT_PROC_BASE_9",
    9242  =>  "INTERNAL_TESTEVENT_PROC_BASE_10",
    9243  =>  "INTERNAL_TESTEVENT_PROC_BASE_11",
    9244  =>  "INTERNAL_TESTEVENT_PROC_BASE_12",
    9245  =>  "INTERNAL_TESTEVENT_PROC_BASE_13",
    9246  =>  "INTERNAL_TESTEVENT_PROC_BASE_14",
    9247  =>  "INTERNAL_TESTEVENT_PROC_BASE_15",
    9248  =>  "INTERNAL_TESTEVENT_PROC_BASE_16",
    9249  =>  "INTERNAL_TESTEVENT_PROC_BASE_17",
    9250  =>  "INTERNAL_TESTEVENT_PROC_BASE_18",
    9251  =>  "INTERNAL_TESTEVENT_PROC_BASE_19",
    9252  =>  "INTERNAL_TESTEVENT_PROC_BASE_20",
    9253  =>  "INTERNAL_TESTEVENT_PROC_BASE_21",
    9254  =>  "INTERNAL_TESTEVENT_PROC_BASE_22",
    9255  =>  "INTERNAL_TESTEVENT_PROC_BASE_23",
    9256  =>  "INTERNAL_TESTEVENT_PROC_BASE_24",
    9257  =>  "INTERNAL_TESTEVENT_L3"
);

our %CTUM  = (
    0  => "CTUM"
);

sub getSourceHashRef {
    my ($sourceName) = @_ ;
    return \%$sourceName;
}
1;
