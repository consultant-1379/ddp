#!/bin/bash
currentDateYYMMDD=$1
date3HrsBefore=$2
currentDate=$3

if [ -f "/data/tmp/DBCriticalErrors.txt" ]; then
  rm -f /data/tmp/DBCriticalErrors.txt
fi
if [ -f "/data/tmp/DBCriticalErrorOutput.txt" ]; then
    rm -f /data/tmp/DBCriticalErrorOutput.txt
fi

awk "/^$currentDate.*INFO: Starting/,0" "/data/ddp/log/updaterepl.log" | grep "is marked as crashed\|Failed to start replication\|Failed to mount db snap\|Stopping replication" >> "/data/tmp/DBCriticalErrors.txt"

if [[ $currentDateYYMMDD == $date3HrsBefore ]]; then
  awk "/^($currentDateYYMMDD).*/,0" "/data/db/log/mysqld.log" | grep "is marked as crashed" >> "/data/tmp/DBCriticalErrors.txt"
else
  awk "/^($currentDateYYMMDD|$date3HrsBefore).*/,0" "/data/db/log/mysqld.log" | grep "is marked as crashed" >> "/data/tmp/DBCriticalErrors.txt"
fi

