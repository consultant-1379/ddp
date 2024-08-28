BEGIN DDP_Bubble.infra_monitor.infra_monitor

Below  data is collected from the following MBean:


**mBean: com.ericsson.oss.mediation.ftp.instrumentation.MultipleFileCollectionHandler:type=PmFileCollectionMetricInstrumentation**

##Network ingress:

**Attributes:**

- mspmPullFilesSessionCreationTime: SFTP/FTPES connection time in seconds.

- mspmPullFilesTransferTime: Transfer time in seconds.

- mspmPullFilesBytesTransfered: Bytes transferred.

- sftpBandwidth: Bytes stored /  transfer time.



##Shared File Storage Write:

**Attributes:**

- mspmPullFilesBytesStoredFS: Bytes stored.

- mspmPullFilesWriteTimeFS: Write time in seconds.

- mspmPullFilesStoredFS: Number of files stored.

- fileWriteBandwidth: Bytes stored / total write time.

END DDP_Bubble.infra_monitor.infra_monitor

BEGIN DDP_Bubble.enm_geo_kpi.NCMExport
Data is collected from the Script API for instrumentation to the source will be in eventdata.log.gz.n.

- **Application**: Application for which the logs are being displayed as part of georeplication export/import
- **Start time**: Start time of the export/import task of the specific application
- **End time**: End time of the export/import task of the specific application
- **Total Duration**: Duration taken to complete georeplication export/import in seconds.

END DDP_Bubble.enm_geo_kpi.NCMExport

BEGIN DDP_Bubble.enm_geo_kpi.NCMImport
Data is collected from the Script API for instrumentation to the source will be in eventdata.log.gz.n.

- **Application**: Application for which the logs are being displayed as part of georeplication export/import
- **Start time**: Start time of the export/import task of the specific application
- **End time**: End time of the export/import task of the specific application
- **Total Duration**: Duration taken to complete georeplication export/import in seconds.

END DDP_Bubble.enm_geo_kpi.NCMImport

BEGIN DDP_Bubble.enm_geo_kpi.NHMExport
Data is collected from the Script API for instrumentation to the source will be in eventdata.log.gz.n.

- **Application**: Application for which the logs are being displayed as part of georeplication export/import
- **Start time**: Start time of the export/import task of the specific application
- **End time**: End time of the export/import task of the specific application
- **Total Duration**: Duration taken to complete georeplication export/import in seconds.
- **Total KPIs Exported**: Total number of users exported as part of Georeplication NHM Export

END DDP_Bubble.enm_geo_kpi.NHMExport

BEGIN DDP_Bubble.enm_geo_kpi.NHMImport
Data is collected from the Script API for instrumentation to the source will be in eventdata.log.gz.n.

- **Application**: Application for which the logs are being displayed as part of georeplication export/import
- **Start time**: Start time of the export/import task of the specific application
- **End time**: End time of the export/import task of the specific application
- **Total Duration**: Duration taken to complete georeplication export/import in seconds.
- **Total KPIs Imported**: Total number of users exported as part of Georeplication NHM Import

END DDP_Bubble.enm_geo_kpi.NHMImport

BEGIN DDP_Bubble.enm_geo_kpi.data
We will only show a table when there is data available for the selected date.
END DDP_Bubble.enm_geo_kpi.data
