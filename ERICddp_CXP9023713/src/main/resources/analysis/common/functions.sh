
log() {
    MSG="$1"
    SCRIPT=$(basename $0)
    echo "${MSG}" | awk -f ${ANALYSIS_BIN}/main/outputProcessing.awk scriptname="$SCRIPT" sitename="$SITE" tardate="$DATE" format="$LOG_LINE_FORMAT"
}

run() {
    local SCRIPTNAME=$(basename $1)

    local COMMAND=""
    while [ -n "$1" ] ; do
        COMMAND="${COMMAND} \"$1\""
        shift
    done

    local START_TIME=$(date +%s%3N)
    eval $COMMAND |& awk -f ${ANALYSIS_BIN}/main/outputProcessing.awk scriptname="$SCRIPTNAME" sitename="$SITE" tardate="$DATE" format="$LOG_LINE_FORMAT"
    local COMMAND_EXIT=${PIPESTATUS[0]}
    local END_TIME=$(date +%s%3N)

    if [ ${COMMAND_EXIT} -ne 0 ] ; then
        echo "CRITICAL ERROR: Non zero exit for ${SCRIPTNAME}" | awk -f ${ANALYSIS_BIN}/main/outputProcessing.awk scriptname="$SCRIPT" sitename="$SITE" tardate="$DATE" format="$LOG_LINE_FORMAT"
        if [ ! -z "${CRITICAL_ERROR_BCP}" ] ; then
            echo "${COMMAND}" >> ${CRITICAL_ERROR_BCP}
        fi
    fi

    local DURATION=$(expr ${END_TIME} - ${START_TIME})
    local TIMESTAMP=$(date "+%F %T")

    if [ -z "${EXE_LOG}" ] ; then
        EXE_LOG=/data/ddp/log/execution.log
    fi
    echo "${TIMESTAMP} ${SITE} ${DATE} ${DURATION} ${COMMAND}" >> ${EXE_LOG}
}
