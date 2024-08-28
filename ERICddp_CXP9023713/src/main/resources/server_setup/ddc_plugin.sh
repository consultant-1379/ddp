#!/bin/bash


isMySqlMonitorRunning() {
    MONITOR_PID=0
    if [ -r ${OUTPUT_DIR}/mysql_monitor.pid ] ; then
        PID=$(cat ${OUTPUT_DIR}/mysql_monitor.pid)
        if [ -d /proc/${PID} ] ; then
            COMM=$(cat /proc/${PID}/comm)
            if [ "${COMM}" = "ddc_plugin.sh" ] ; then
                MONITOR_PID=${PID}
            fi
        fi
    fi
}

startMySqlMonitor() {
    if [ ! -d ${OUTPUT_DIR} ] ; then
        echo "ERROR: startMySqlMonitor output dir ${OUTPUT_DIR} not found"
        exit 1
    fi

    isMySqlMonitorRunning
    if [ ${MONITOR_PID} -ne 0 ] ; then
        echo "ERROR: startMySqlMonitor already running with pid ${MONITOR_PID}"
        exit 1
    fi

    /bin/rm -f ${OUTPUT_DIR}/mysql_monitor.exit
    echo $$ > ${OUTPUT_DIR}/mysql_monitor.pid
    START_DAY=$(date +%d)
    while [ -d ${OUTPUT_DIR} ] && [ ! -r ${OUTPUT_DIR}/mysql_monitor.exit ] ; do
        CURRENT_DAY=$(date +%d)
        if [ "${START_DAY}" -ne "${CURRENT_DAY}" ] ; then
            echo "ERROR: startMySqlMonitor: Day ${CURRENT_DAY} != ${START_DAY}"
            exit 1
        fi

        DATE=$(date +%d%m%y)
        TIME=$(date +%H:%M)

        echo "${DATE} ${TIME}" >> ${OUTPUT_DIR}/mysql-extended-status.txt
        /usr/bin/mysqladmin extended-status >> ${OUTPUT_DIR}/mysql-extended-status.txt

        SLEEP_COUNT=0
        while [ ${SLEEP_COUNT} -lt 60 ] && [ ! -r ${OUTPUT_DIR}/mysql_monitor.exit ] ; do
            sleep 1
            SLEEP_COUNT=$(expr ${SLEEP_COUNT} + 1)
        done
    done

    /bin/rm -f ${OUTPUT_DIR}/mysql_monitor.exit
}

stopMySqlMonitor() {
    isMySqlMonitorRunning
    if [ ${MONITOR_PID} -ne 0 ] ; then
        touch ${OUTPUT_DIR}/mysql_monitor.exit
        INDEX=0
        while [ ${INDEX} -lt 10 ] && [ -r ${OUTPUT_DIR}/mysql_monitor.exit ] ; do
            INDEX=$(expr ${INDEX} + 1)
            sleep 10
        done
    fi
}

doStart() {
    # Lets figure out if we're the "primary", currently /data/ftproot only exists on the primary
    if [ -d /data/ftproot ] ; then
        touch ${DATEDIR}/DDC_MASTER
    else
        # If we're not on the primary server, nothing more to do
        return
    fi

    for SAN_TYPE in unity clariion ; do
        if [ -r /root/${SAN_TYPE}.cfg ] && [ ! -d ${DATEDIR}/${SAN_TYPE} ] ; then
            /bin/mkdir ${DATEDIR}/${SAN_TYPE}
            /bin/cp /root/${SAN_TYPE}.cfg ${DATEDIR}/${SAN_TYPE}/san.cfg
        fi
    done

    mysqladmin variables > ${OUTPUT_DIR}/mysql-variables.txt

    SLOW_QUERY_LOG=$(grep slow_query_log_file ${OUTPUT_DIR}/mysql-variables.txt | awk -F\| '{print $3}' | awk '{print $1}')
    if [ ! -r ${OUTPUT_DIR}/mysql-sqlines.txt ] ; then
        ssh -qn dbhost wc -l ${SLOW_QUERY_LOG} | awk '{print $1}' > ${OUTPUT_DIR}/mysql-sqlines.txt
    fi

    PLUGIN_OUTPUT_DIR=$(dirname ${OUTPUT_DIR})
    $0 MYSQL_MONITOR ${PLUGIN_OUTPUT_DIR} &
}

doTrigger() {
    if [ ! -r ${DATEDIR}/DDC_MASTER ] ; then
        # If we're not on the primary server, nothing more to do
        return
    fi

    DATE=$(date +%d%m%y)
    TIME=$(date +%H:%M)

    if [ -r /data/stats/config ] ; then
        . /data/stats/config
        NUM_FILES_WAITING=`find ${INCOMING_ROOT} -type f -name '*.tar.gz' | wc -l | awk '{print $0}'`
        echo "${DATE} ${TIME} ${NUM_FILES_WAITING}" >> ${OUTPUT_DIR}/fileswaiting.txt
    fi

    echo "${DATE} ${TIME}" >> ${OUTPUT_DIR}/processlist.txt
    mysql -e 'SHOW FULL PROCESSLIST' >> ${OUTPUT_DIR}/processlist.txt

    /data/ddp/current/server_setup/updateRepl -m | grep 'Replication Status for' > /tmp/repl.$$
    while read -r REPLICA_LINE ; do
        local REPLICA=$(echo "${REPLICA_LINE}" | awk '{print $6}')
        local STATUS=$(echo "${REPLICA_LINE}" | sed "s/.* ${REPLICA} //")
        echo "${STATUS}" | egrep --silent "^Seconds_Behind_Master: "
        if [ $? -eq 0 ] ; then
            local DELAY=$(echo "${STATUS}" | awk '{print $2}')
        else
            local DELAY=65535
        fi
        echo "${DATE}:${TIME}:00 ${DELAY}" >> ${OUTPUT_DIR}/repl_${REPLICA}.txt
    done < /tmp/repl.$$
    /bin/rm /tmp/repl.$$
}

doStopAndMaketar() {
    if [ ! -r ${DATEDIR}/DDC_MASTER ] ; then
        return
    fi

    local COLLECT_PEERS=0
    if [ -r /opt/ericsson/ERICddc/etc/appl/DDP.env ] ; then
        . /opt/ericsson/ERICddc/etc/appl/DDP.env
        if [ ! -z "${SITEDATAROOT}" ] && [ "${SITEDATAROOT}" != "${DATAROOT}" ] ; then
            COLLECT_PEERS=1
            triggerOnPeerNodes ${TASK}
        fi
    fi

    if  [ -r /data/ddp/log/perf.log ] ; then
        mv /data/ddp/log/perf.log /data/ddp/log/perf.log.tmp
        touch /data/ddp/log/perf.log
        chmod 777 /data/ddp/log/perf.log
        chown statsadm:statsadm /data/ddp/log/perf.log

        SQL_DATE_ONLY=$(date '+%Y-%m-%d')
        cat /data/ddp/log/perf.log.tmp | awk -v DATE="${SQL_DATE_ONLY}" 'BEGIN { found = 0 } found == 0 { if ($1 == DATE) { found = 1;  print }; next } found == 1 { print; next }' >> ${OUTPUT_DIR}/perf.log
        rm /data/ddp/log/perf.log.tmp
    fi

    if [ -r /data/ddp/log/execution.log ] ; then
        mv /data/ddp/log/execution.log /data/ddp/log/execution.log.tmp

        if [ -r ${OUTPUT_DIR}/execution.log ] ; then
            mv ${OUTPUT_DIR}/execution.log ${OUTPUT_DIR}/execution.log.1
        fi
        INDEX=1
        while [ -r ${OUTPUT_DIR}/execution.log.${INDEX} ] ; do
            INDEX=$(expr ${INDEX} + 1)
        done
        mv /data/ddp/log/execution.log.tmp ${OUTPUT_DIR}/execution.log.${INDEX}
    fi

    if [ "${TASK}" = "STOP" ] ; then
        stopMySqlMonitor
    fi

    for DB in statsdb ddpadmin ; do
        if [ ! -r ${OUTPUT_DIR}/${DB}.txt ] ; then
            mysql --batch > ${OUTPUT_DIR}/${DB}.txt <<EOF
                SELECT TABLE_NAME AS tbl,
                DATA_LENGTH  AS data,
                INDEX_LENGTH AS idx,
                AVG_ROW_LENGTH AS avglen,
                CREATE_TIME AS ctime,
                UPDATE_TIME AS utime
                FROM information_schema.TABLES WHERE
                TABLE_SCHEMA = '${DB}';
EOF
        fi
    done

    if [ ! -r ${OUTPUT_DIR}/id_tables.txt ] ; then
        mysql --batch > ${OUTPUT_DIR}/id_tables.txt <<EOF
SELECT cols.COLUMN_NAME, cols.TABLE_NAME, cols.DATA_TYPE, tables.AUTO_INCREMENT
FROM INFORMATION_SCHEMA.COLUMNS AS cols, INFORMATION_SCHEMA.TABLES AS tables
WHERE tables.TABLE_SCHEMA = cols.TABLE_SCHEMA AND tables.TABLE_NAME = cols.TABLE_NAME AND
      cols.TABLE_SCHEMA = 'statsdb' AND
      cols.COLUMN_KEY = 'PRI' AND cols.EXTRA = 'auto_increment';
EOF
    fi

    if [ -r ${OUTPUT_DIR}/mysql-sqlines.txt ] ; then
        SLOW_QUERY_LOG=$(grep slow_query_log_file ${OUTPUT_DIR}/mysql-variables.txt | awk -F\| '{print $3}' | awk '{print $1}')
        SLOW_QUERY_LOG_LINES=$(cat ${OUTPUT_DIR}/mysql-sqlines.txt)
        ssh -qn dbhost tail --lines=+${SLOW_QUERY_LOG_LINES} ${SLOW_QUERY_LOG} > ${OUTPUT_DIR}/mysql-slowquerylog.txt
    fi

    # Collect certs
    local SERVICE_NAME=$(hostname)
    if [ -r /etc/letsencrypt/live/${SERVICE_NAME}.athtem.eei.ericsson.se/fullchain.pem ] ; then
        /bin/cp /etc/letsencrypt/live/${SERVICE_NAME}.athtem.eei.ericsson.se/fullchain.pem ${OUTPUT_DIR}/httpd.cer
    elif [ -r /etc/httpd/sslcert/${SERVICE_NAME}-https.cer ] ; then
        /bin/cp /etc/httpd/sslcert/${SERVICE_NAME}-https.cer ${OUTPUT_DIR}/httpd.cer
    elif [ -r /etc/httpd/sslcert/${SERVICE_NAME}.cer ] ; then
        /bin/cp /etc/httpd/sslcert/${SERVICE_NAME}.cer ${OUTPUT_DIR}/httpd.cer
    fi
    if [ -r /etc/certs/k8s-${SERVICE_NAME}.cer ] ; then
        /bin/cp /etc/certs/k8s-${SERVICE_NAME}.cer ${OUTPUT_DIR}/k8s-client.cer
    fi
    if [ -r /etc/certs/k8master_ca.cer ] ; then
        /bin/cp /etc/certs/k8master_ca.cer ${OUTPUT_DIR}/k8master_ca.cer
    fi
    if [ -r /etc/certs/repl-client-repladm-ddprepl.cer ] ; then
        /bin/cp /etc/certs/repl-client-repladm-ddprepl.cer ${OUTPUT_DIR}/repl-client-repladm-ddprepl.cer
    fi

    if [ ${COLLECT_PEERS} -eq 1 ] ; then
        collectPeerArchives
    fi
}

collectPeerArchives() {
    # Make directory for peer nodes on management server data folder
    REMOTEHOSTS_DIR="${DATEDIR}/remotehosts"
    if [ ! -d "${REMOTEHOSTS_DIR}" ] ; then
        mkdir ${REMOTEHOSTS_DIR}
    fi

    DATE=$(basename ${DATEDIR})
    MYDIR=$(basename ${DATAROOT})

    # Only include valid dir's in the list of hosts
    # so that we ignore other dirs like config, lost+found, etc.
    REMOTE_HOSTS=""
    REMOTE_HOSTS_DEAD=""
    DIR_LIST=$(ls ${SITEDATAROOT})
    for DIR in ${DIR_LIST} ; do
        SERVER_DIR=$(echo ${DIR} | awk -F\_ '{print $1}')
        if [ "${SERVER_DIR}" != "${MYDIR}" ] ; then
            if [ -d "${SITEDATAROOT}/${DIR}/${DATE}" ] || [ -r ${SITEDATAROOT}/${DIR}/DDC_Data_${DATE}.tar.gz ] ; then
                REMOTE_HOSTS="${REMOTE_HOSTS} ${DIR}"
            fi
        fi
    done

    LOOP_COUNT=0
    MAX_RETRIES=5
    while [ ! -z "${REMOTE_HOSTS}" ] && [ ${LOOP_COUNT} -lt ${MAX_RETRIES} ] ; do
        # wait one minute so we allow tar file creation to complete
        sleep 60
        REMAINING_HOSTS_DEAD=""
        REMAINING_HOSTS_ACTIVE=""
        for REMOTE_HOST in ${REMOTE_HOSTS} ${REMOTE_HOSTS_DEAD} ; do
            if [ -d "${SITEDATAROOT}/${REMOTE_HOST}" ] ; then
                # collect tar file
                # logDebug "Checking ${REMOTE_HOST} for archive files"
                if [ ! -d "${REMOTEHOSTS_DIR}/${REMOTE_HOST}" ] ; then
                    mkdir ${REMOTEHOSTS_DIR}/${REMOTE_HOST}
                fi
                TODAYS_FILE="${SITEDATAROOT}/${REMOTE_HOST}/DDC_Data_${DATE}.tar.gz"
                ALL_FILES=$(find ${SITEDATAROOT}/${REMOTE_HOST} -maxdepth 1 -name 'DDC_Data_*.tar.gz')
                FOUND_TODAY=false
                for file in ${ALL_FILES} ; do
                    if [ "${file}" = "${TODAYS_FILE}" ] ; then
                        FOUND_TODAY=true
                    fi
                    mv ${file} ${REMOTEHOSTS_DIR}/${REMOTE_HOST}
                done
                if [ "${FOUND_TODAY}" = "false" ] ; then
                    # Skip checking for the archive file if DDC failed to trigger the MAKETAR
                    #  on the given host
                    if [ -f ${SITEDATAROOT}/${REMOTE_HOST}/.failedToTrigger ] ; then
                        REMAINING_HOSTS_DEAD="${REMAINING_HOSTS_DEAD} ${REMOTE_HOST}"
                    else
                        DDC_COMMAND=""
                        if [ -r ${SITEDATAROOT}/${REMOTE_HOST}/.ddcCommandRemote ] ; then
                            DDC_COMMAND=$(cat ${SITEDATAROOT}/${REMOTE_HOST}/.ddcCommandRemote)
                        fi

                        # Consider a given host dead/inactive if MAKETAR '.ddcCommandRemote' file
                        #  exists under its '/var/ericsson/ddc_data/<HOSTNAME>_TOR/' directory
                        #  even after 3 min from the start of MAKETAR
                        if [ ${LOOP_COUNT} -ge 2 ] && [ "${DDC_COMMAND}" = "MAKETAR" ] ; then
                            REMAINING_HOSTS_DEAD="${REMAINING_HOSTS_DEAD} ${REMOTE_HOST}"
                        else
                            REMAINING_HOSTS_ACTIVE="${REMAINING_HOSTS_ACTIVE} ${REMOTE_HOST}"
                        fi
                    fi
                fi
            fi
        done

        # Iterate again only if there are active hosts for which we still need collect an
        #  archive file
        REMOTE_HOSTS=${REMAINING_HOSTS_ACTIVE}
        REMOTE_HOSTS_DEAD=${REMAINING_HOSTS_DEAD}

        let LOOP_COUNT=($LOOP_COUNT+1)
    done

    for REMOTE_HOST in ${REMOTE_HOSTS_DEAD} ; do
        [ -f ${SITEDATAROOT}/${REMOTE_HOST}/.failedToTrigger ] && /bin/rm -f ${SITEDATAROOT}/${REMOTE_HOST}/.failedToTrigger
    done

    if [ ! -z "${REMOTE_HOSTS}" ] || [ ! -z "${REMOTE_HOSTS_DEAD}" ] ; then
        echo "Unable to collect archive file on these hosts due to timeout: ${REMOTE_HOSTS}"
    fi
}

triggerOnPeerNodes() {
    ACTION=$1

    DATE=$(basename ${DATEDIR})
    MYDIR=$(basename ${DATAROOT})

    # Only include valid dir's in the list of hosts
    # so that we ignore other dirs like config, lost+found, etc.
    REMOTE_HOSTS=""
    REMOTE_HOSTS_DEAD=""
    DIR_LIST=$(ls ${SITEDATAROOT})
    for DIR in ${DIR_LIST} ; do
        SERVER_DIR=$(echo ${DIR} | awk -F\_ '{print $1}')
        if [ "${SERVER_DIR}" != "${MYDIR}" ] ; then
            if [ -d "${SITEDATAROOT}/${DIR}/${DATE}" ] ; then
                REMOTE_HOSTS="${REMOTE_HOSTS} ${DIR}"
            fi
        fi
    done

    # Trigger each host in the list to make a tar file
    for REMOTE_HOST in ${REMOTE_HOSTS} ; do
        TRIGGER_FAILED_FLAG_FILE="${SITEDATAROOT}/${REMOTE_HOST}/.failedToTrigger"
        if [ -f ${TRIGGER_FAILED_FLAG_FILE} ] ; then
            /bin/rm -f ${TRIGGER_FAILED_FLAG_FILE}
        fi

        if [ ! -f "${SITEDATAROOT}/${REMOTE_HOST}/.ddcCommandRemote" ] ; then
            echo "${ACTION}" > ${SITEDATAROOT}/${REMOTE_HOST}/.ddcCommandRemote
        else
            if [ "$(cat ${SITEDATAROOT}/${REMOTE_HOST}/.ddcCommandRemote)" != "${ACTION}" ] ; then
                touch ${TRIGGER_FAILED_FLAG_FILE}
            fi
        fi
    done
}

# Check user
# Must be running as root
ME=$(id | sed 's/^uid=[0-9]*(\([a-z].*\)) gid=.*$/\1/')
if [ "${ME}" != "root" ] ; then
    exec /usr/bin/sudo -u root /data/ddp/current/server_setup/ddc_plugin.sh $*
fi

TASK=$1
OUTPUT_DIR=$2

if [ -z "${TASK}" ] || [ -z "${OUTPUT_DIR}" ] ; then
    die "Usage: $0 <START|STOP|TRIGGER|MAKETAR> <OUTPUT_DIR>"
fi


DATEDIR=$(dirname ${OUTPUT_DIR})
OUTPUT_DIR=${OUTPUT_DIR}/DDP
if [ ! -d "${OUTPUT_DIR}" ] ; then
    mkdir ${OUTPUT_DIR}
    [ $? -ne 0 ] && die "could not create output data directory: ${OUTPUT_DIR}"
fi

case $TASK in
    "START") doStart ;;
    "TRIGGER") doTrigger ;;
    "STOP") doStopAndMaketar;;
    "MAKETAR") doStopAndMaketar;;
    "MYSQL_MONITOR") startMySqlMonitor
esac

