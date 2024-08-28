BEGIN DDP_Bubble.cm_imports.Bulk_CM_Import

The Bulk CM Import data is retrieved from the Elasticsearch logs. These Bulk CM Import values display the following information (A value of 'NA' under any given column of this table stands for "Not Available"):

**Attributes:**

- Job Id: The Id of the import job.
- Status: The status of the import job.
- Job Start: The start time [hh:mm:ss] of the import job.
- Job End: The end time [hh:mm:ss] of the import job.
- Hostname : Jboss Master server hostname for an Import job.
- Schema Validation Time: The time [hh:mm:ss] taken to complete the schema validation operation.
- Parsing Time: The time [hh:mm:ss] taken to complete the parsing operation.
- Model Validation Time: The time [hh:mm:ss] taken to complete the model validation operation.
- Copy Time: The time [hh:mm:ss] taken to complete the copy operation.
- Import Time: The time [hh:mm:ss] taken to complete the import operation.
- Total Elapsed Time: The total time [hh:mm:ss] taken to complete the import job (i.e., the time elapsed between 'Schema Validation Start' and 'Import End').
- Nodes Copied: The total number of nodes copied as part of the import job.
- Nodes Not Copied: The total number of nodes not copied as part of the import job.
- MOs Created: The total number of Managed Objects created.
- MOs Updated: The total number of Managed Objects updated.
- MOs Deleted: The total number of Managed Objects deleted.
- MOs Processed/sec.execution phase: The total number of Managed Objects processed (i.e., MOs Created + MOs Updated + MOs Deleted) per second (of the import time).
- File Format: The format of the file used for the import job.
- Configuration: The name of the configuration used for the import job.
- Import File: The name of the import file used for the import job.
- Error Handling: Error Handling behaviour specified for the job.
- Instance Validation: Was Instance Validation phase requested for the job.

END DDP_Bubble.cm_imports.Bulk_CM_Import

BEGIN DDP_Bubble.cm_imports.bulkcmimportui

Data is collected from the BULK_CMIMPORT.importInvocationParameters  log from the Elasticsearch logs.

**Attributes:**

- Job Id: The Id of the import job.
- Job Name: Name of the Import Job.
- Status: The status of the import job.
- File Name: Supplied Import File Name.
- File Format: The format of the file used for the import job.
- Number of Nodes: Number of nodes processed in the current Invocation(Validate/Execute).
- Hostname : Jboss Master server hostname for an Import job.
- Invocation Flow: Invocation type of the current invocation.
- Validation Policies: Validation policies used in current invocation.
- Execution Policies: Execution policies used in current invocation.
- Job Start: The start time [hh:mm:ss] of the current invocation import job.
- Job End: The end time [hh:mm:ss] of the current invocation import job.
- Elapsed Time: The total time [hh:mm:ss] taken to complete the import job current invocation.
- Create Operations: The total number of Managed Object CREATE Operations present in the supplied file.
- Delete Operations: The total number of Managed Object DELETE Operations present in the supplied file.
- Update Operations: The total number of Managed Object UPDATE Operations present in the supplied file.
- Action Operations: The total number of Managed Object ACTION Operations present in the supplied file.
- MOs Processed: he total number of Managed Objects processed in current invocation (i.e., MOs Created + MOs Updated + MOs Deleted + MO Actions).
- MOs Processed/sec.execution phase: The total number of Managed Objects processed (i.e., MOs Created + MOs Updated + MOs Deleted) per seconds in current invocation.
- Total Valid: Total number of VALID operations for the import job.
- Total InValid: Total number of INVALID operations for the import job.
- Total Executed: Total number of EXECUTED operations for the import job.
- Total Execution Error: Total number of EXECUTION_ERROR operations for the import job.

END DDP_Bubble.cm_imports.bulkcmimportui

BEGIN DDP_Bubble.mscmce_notif_analysis.mscmceYangNotification

Software Sync Invocations graphs are generated from data collected from the following MBean:

**MBean:** com.ericsson.oss.mediation.cba.handlers.instrumentation.cba-cm-sync-node-handlers:type=CommonSoftwareSyncInstrumentation

**Attributes:**

- numberOfSoftwareSyncInvocationsForYangLibraryUpdates: Number of Software Sync Invocations for Yang Library Updates.

Yang Library Notification Analysis Instrumentation graphs are collected from the following MBean.

**MBean:** com.ericsson.oss.mediation.instrumentation.com-ecim-mdb-notification-listener-handler:type=YangLibraryNotificationAnalysisInstrumentation

**Attributes:**

- receivedNotificationsCount: Total Notifications Received.
- processedNotificationsCount: Total Notifications Processed.
- discardedNotificationsCount: Total Notifications Discarded.

END DDP_Bubble.mscmce_notif_analysis.mscmceYangNotification
