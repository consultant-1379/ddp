BEGIN DDP_Bubble.pmserv.largeBscNodes

*EVENT_NAME* : LARGE_BSC_NODES

**Attributes:**

- Node Name: Node name of Large Node
- Total Volume: Total Volume of Large Node
- Total Number of Files Collected: Total Number of files collected for Large Node
- Avg Largest File Size: Avg Largest File Size of Large Node

END DDP_Bubble.pmserv.largeBscNodes

BEGIN DDP_Bubble.ebsm_stream_statistics.sessionaggregation

Below graph is generated from data collected from the following MBeans per JVM of servie group **ebsstream**

**MBeans:**

- com.ericsson.component.aia.services.eps.core.statistics.ebs-eps*X*:name=com.ericsson.oss.mediation.pm.ebs.ebsm.stream.metrics.handler.StreamMetricsOutputHandler.Index_Size_Of_Nr_Uplink_Throughput_Counters
- com.ericsson.component.aia.services.eps.core.statistics.ebs-eps*X*:name=com.ericsson.oss.mediation.pm.ebs.ebsm.stream.metrics.handler.StreamMetricsOutputHandler.Index_Size_Of_Nr_Downlink_Voice_Throughput_Counters
- com.ericsson.component.aia.services.eps.core.statistics.ebs-eps*X*:name=com.ericsson.oss.mediation.pm.ebs.ebsm.stream.metrics.handler.StreamMetricsOutputHandler.Index_Size_Of_Nr_Downlink_Non_Voice_Throughput_Counters

**Attributes:**

- Count

END DDP_Bubble.ebsm_stream_statistics.sessionaggregation

BEGIN DDP_Bubble.ebsm_stream_statistics.suspectcells

Below graph is generated from data collected from the following MBean per JVM of servie group **ebsstream**

**MBean:**

- com.ericsson.component.aia.services.eps.core.statistics.ebs-eps*X*:name=com.ericsson.oss.mediation.pm.ebs.ebsm.stream.metrics.handler.StreamMetricsOutputHandler.Number_Of_Suspect_Cells_Per_Rop

**Attributes:**

- Count

END DDP_Bubble.ebsm_stream_statistics.suspectcells

BEGIN DDP_Bubble.ebsm_statistics.throughputcounters

Below graph is generated from data collected from the following MBeans per JVM of servie group **ebsm**

**MBeans:**

- com.ericsson.component.aia.services.eps.core.statistics.ebs-eps\d+:name=Index_Size_Of_Nr_Downlink_Non_Voice_Throughput_Counters
- com.ericsson.component.aia.services.eps.core.statistics.ebs-eps\d+:name=Index_Size_Of_Nr_Downlink_Voice_Throughput_Counters
- com.ericsson.component.aia.services.eps.core.statistics.ebs-eps\d+:name=Index_Size_Of_Nr_Uplink_Throughput_Counters

**Attributes:**

- Count

END DDP_Bubble.ebsm_statistics.throughputcounters
