BEGIN DDP_Bubble.fm_handler_stats.fmhandlerdt

The data provided in this table below are statistics for O1 FM Mediation, and the Daily Totals table below provides the following information:

**Attributes:**

- **Number Of Successful Transformations**: This is the number of alarms from O1 nodes, which are successfully transformed into event notifications.
- **Number Of Forwarded Alarm Event Notifications**: This is the number of alarms from O1 nodes, which are forwarded successfully to APS for alarm processing.
- **Number Of Forwarded Sync Alarm Event Notifications**: This is the number of sync alarms from O1 nodes, which are forwarded successfully to APS for alarm processing.
- **Number Of Alarms Received**: This is the number of alarms received in FM mediation from O1 nodes.
- **Number Of Heartbeats Received**: This is the number of heartbeat notifications received in FM mediation from O1 nodes.

END DDP_Bubble.fm_handler_stats.fmhandlerdt

BEGIN DDP_Bubble.fm_handler_stats.fmnodestatus

Nodes status graphs are generated from collected data from the following MBean's:

**MBean**: com.ericsson.oss.mediation.o1.heartbeat.instrumentation.o1-fm-mediation-heartbeat-ejb:type=O1FmHeartbeatStatistics

**MBean**: com.ericsson.oss.mediation.fm.o1.engine.instrumentation.o1-fm-mediation-engine-ejb:type=O1FmEngineStatistics

**Attributes:**

- **Total Number of Supervised Nodes**: This is the number of O1 nodes supervised by FM mediation.
- **Total Number of Heartbeat Failures**: This is the number of O1 nodes in heartbeat failure.

END DDP_Bubble.fm_handler_stats.fmnodestatus

BEGIN DDP_Bubble.fm_handler_stats.fmalarmsreceived

Alarms received graphs are generated from collected data from the following MBean:

**MBean**: com.ericsson.oss.mediation.fm.o1.instrumentation.o1-common-handlers-core-jar:type=O1HandlerStatistics

**Attributes:**

- **Total Number Of Alarms Received**: This is the number of alarms received in FM mediation from O1 nodes.

END DDP_Bubble.fm_handler_stats.fmalarmsreceived

BEGIN DDP_Bubble.fm_handler_stats.fmalarmstransformed

Alarms transformed graphs are generated from collected data from the following MBean:

**MBean**: com.ericsson.oss.mediation.fm.o1.instrumentation.o1-common-handlers-core-jar:type=O1HandlerStatistics

**Attribute:**

- **Total Number Of Successful Transformations**: This is the number of alarms from O1 nodes, which are successfully transformed into event notifications.

END DDP_Bubble.fm_handler_stats.fmalarmstransformed

BEGIN DDP_Bubble.fm_handler_stats.fmforwadedevents

Forwarded event notifications are generated from collected data from the following MBean:

**MBean**: com.ericsson.oss.mediation.fm.o1.engine.instrumentation.o1-fm-mediation-engine-ejb:type=O1FmEngineStatistics

**Attributes:**

- **Total Number Of Forwarded Alarm Event Notifications**: This is the number of alarms from O1 nodes, which are forwarded successfully to APS for alarm processing.
- **Total Number Of Forwarded Sync Alarm Event Notifications**: This is the number of sync alarms from O1 nodes, which are forwarded successfully to APS for alarm processing.

END DDP_Bubble.fm_handler_stats.fmforwadedevents

BEGIN DDP_Bubble.fm_handler_stats.Queues
Instrumentation graphs are generated from JMX data collected from the JMS service:

- **Messages Added**: Number of messages added to this queue since it was initially created.
- **Message Count**: Number of messages currently held in this queue awaiting delivery.
END DDP_Bubble.fm_handler_stats.Queues

BEGIN DDP_Bubble.fm_handler_stats.routes
This route instrumentation information is taken from the Camel MBean information.

The MBean name is the same as the route name in the table.

Rows can be selected/multi-selected and plotted.
END DDP_Bubble.fm_handler_stats.routes
