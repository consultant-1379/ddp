BEGIN DDP_Bubble.hc.NeoAvgMsgTime

This check will show when the bolt service time exceeds the limits which can indicate that there is a problem with the underlying storage layer.

**AMBER:**

- Average Message Processing Time (msec) for a specific hour has breached %boltAvgProcTimeperhour-warn% msec.

**RED:**

- Average Message Processing Time (msec) for a specific hour has breached %boltAvgProcTimeperhour% msec.

END DDP_Bubble.hc.NeoAvgMsgTime

BEGIN DDP_Bubble.hc.cpuHealth
This check will show servers(excluding MSPM, excluding ebsm for Gen 9 Systems) where any of the following conditions are met:

**AMBER:**

- Maximum CPU exceeds the warning value of 98%.

**RED:**

- Average CPU exceeds the value of 70%.
- Maximum CPU exceeds the value of 99%.
- Average IO-Wait exceeds the value of 25%.
- Maximum IO Wait exceeds the value of 70%.

END DDP_Bubble.hc.cpuHealth

BEGIN DDP_Bubble.hc.DDPFlagFileStatus
This check shows the health status of DDP report based on availability of flag files.

**GREEN:**

- **ddp_report:** If ddp.report file is not available.
- **mpath:** Required LUNs mpath values are correctly present in the config file.
- **iq_header:** All database LUN having IQheader information.

**RED:**

- **ddp_report:** If ddp.report file is available.
- **mpath:** Required LUNs mpath values are missing in the config file. Follow the section "Verify LUN and Mpath Mapping" in the ENIQ Statistics Preventive Maintenance Guide. If the issue persists after the section is followed then contact Ericsson Customer Support.
- **iq_header:** The database LUN missing IQheader information, contact Ericsson Customer Support.

END DDP_Bubble.hc.DDPFlagFileStatus
