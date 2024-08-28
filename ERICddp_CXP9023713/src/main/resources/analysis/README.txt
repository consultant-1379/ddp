
crontb 
 if already running (LOGDIR/mas.txt exists) exit

 run makeAllStats
 copy logs in to LOGDIR/processed.${DATE}

 delete LOGDIR/processed. older then 14 days

makeAllStats
	foreach row in sitelist.txt
             foreach OSS_Data file in INCOMING_ROOT for site
                decompress
                extract tar
                
                while max makeStats running
                    sleep
 
                makeStats
             done
        done



crontab
makeAllStats
makeStats

                 
