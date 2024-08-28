BEGIN DDP_Bubble.security_comaa_stats.dailyTotalHelp

Instrumentation data are collected from the following MBeans:

**MBean:** com.ericsson.oss.services.security.accesscontrol.comaa.com-aa-service:type=LdapData

**Attributes:**

- **numberOfInitialProxyBindReq:** Number of initial proxy user Bind Requests
- **numberOfInitialUserBindReq:** Number of initial user Bind Requests
- **numberOfAddProxyBindReq:** Number of additional proxy user Bind Requests
- **numberOfAddUserBindReq:** Number of additional user Bind Requests
- **numberOfSearchReq:** Number of Search Requests
- **numberOfConnectionReq:** Number of connections
- **numberOfErrorDisconnection:** Number of connections closed because of error
- **numberOfSuccessfullDisconnection:** Number of successfully closed connections
- **numberOfProxyBindError:** Number of Proxy Bind Error
- **numberOfUserBindError:** Number of User Bind Error
- **numberOfTlsHandshakeError:** Number of Tls Handshake Error
- **totalTimeError:** Total time of all connections which has been closed because of error
- **totalTimeSuccessful:** Total time of all successfully closed connections
- **numberOfFastConnection:** Number of Fast Connections ( duration [0,2] seconds )
- **numberOfMediumConnection:** Number of Medium Connections ( duration (2,10] seconds )
- **numberOfHighConnection:** Number of High Connections ( duration (10,50] seconds )
- **numberOfSlowConnection:** Number of Slow Connections ( duration > 50 seconds )

**MBean:** com.ericsson.oss.services.adapter.ldap.ejb.instrumentation.com-aa-service:type=LdapTokenMonitoredData

**Attributes:**

- **numberOfTokenValidationSuccessfull:** Number of successfully token validation to SSO
- **numberOfTokenValidationFailed:** Number of failed token validation to SSO
- **numberOfFastTokenValidation:** Number of fast token validation to SSO ( T = [0, 0.5] seconds )
- **numberOfHighTokenValidation:** Number of high token validation to SSO ( T = [0.5, 2] seconds )
- **numberOfSlowTokenValidation:** Number of long token validation to SSO ( T > 2 seconds )

END DDP_Bubble.security_comaa_stats.dailyTotalHelp

BEGIN DDP_Bubble.security_comaa_stats.instrGraphs

Instrumentation data are collected from the following MBeans:

**MBean:** com.ericsson.oss.services.security.accesscontrol.comaa.com-aa-service:type=LdapData

**Attributes:**

- **numberOfErrorDisconnection:** Number of connections closed because of error
- **numberOfSuccessfullDisconnection:** Number of successfully closed connections
- **numberOfInitialProxyBindReq:** Number of initial proxy user Bind Requests
- **numberOfInitialUserBindReq:** Number of initial user Bind Requests
- **numberOfAddProxyBindReq:** Number of additional proxy user Bind Requests
- **numberOfAddUserBindReq:** Number of additional user Bind Requests
- **numberOfSearchReq:** Number of Search Requests
- **numberOfConnectionReq:** Number of connections
- **maxNumberOfConnectionAlive:** This tracks the highest value of parallel open connections for each minute

The graphs below are a function of their metricName values as below:

          f = (metricName - metricName(n-1))

Average connection duration Graph function is as below:

          f = (totalTimeSuccessful + totalTimeError- totalTimeSuccessful(n-1) - totalTimeError(n-1)) / (numberOfSuccessfullDisconnection + numberOfErrorDisconnection- numberOfSuccessfullDisconnection(n-1) - numberOfErrorDisconnection(n-1))

- **numberOfFastConnection:** Number of Fast Connections ( duration [0,2] seconds )
- **numberOfMediumConnection:** Number of Medium Connections ( duration (2,10] seconds)
- **numberOfHighConnection:** Number of High Connections ( duration (10,50] seconds)
- **numberOfSlowConnection:** Number of Slow Connections ( duration > 50 seconds)

**MBean:** com.ericsson.oss.services.adapter.ldap.ejb.instrumentation.com-aa-service:type=LdapTokenMonitoredData

**Attributes:**

- **numberOfTokenValidationSuccessfull:** Number of successfully token validation to SSO
- **numberOfTokenValidationFailed:** Number of failed token validation to SSO
- **numberOfFastTokenValidation:** Number of fast token validation to SSO ( T = [0, 0.5] seconds )
- **numberOfHighTokenValidation:** Number of high token validation to SSO ( T = [0.5, 2] seconds )
- **numberOfSlowTokenValidation:** Number of long token validation to SSO ( T > 2 seconds )

END DDP_Bubble.security_comaa_stats.instrGraphs
