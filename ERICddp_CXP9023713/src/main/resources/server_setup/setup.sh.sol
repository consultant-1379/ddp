#!/bin/bash

SERVER_SETUP_DIR=`dirname $0`
SERVER_SETUP_DIR=`cd ${SERVER_SETUP_DIR} ; pwd`
. ${SERVER_SETUP_DIR}/setup_common.sh

cfgBkupIf()
{
    HOSTNAME=`hostname`
    BKUP_HOSTNAME="${HOSTNAME}-bup"
    BKUP_IP=`getent hosts ${BKUP_HOSTNAME} | awk '{print $1}'`

    BKUP_NIC=nge1
    echo "${BKUP_HOSTNAME}" > /etc/hostname.${BKUP_NIC}
    echo "${BKUP_IP} ${BKUP_HOSTNAME}" >> /etc/inet/hosts
    echo "192.100.0.0 255.255.255.0" >> /etc/netmasks

    ifconfig ${BKUP_NIC} plumb
    ifconfig ${BKUP_NIC} inet ${BKUP_IP} netmask 255.255.255.0
    ifconfig ${BKUP_NIC} 

}

cfgNetbackup()
{
    SERVER_IP=`getent hosts attembak3back | awk '{print $1}'`
    echo "${SERVER_IP} +" > /.rhosts
    svcadm enable svc:/network/rexec:default

    #cp /usr/openv/netbackup/bp.conf /usr/openv/netbackup/bp.conf.org
    #cat /usr/openv/netbackup/bp.conf.org | sed 's/attembak3$/attembak3back/' > /usr/openv/netbackup/bp.conf    
}

cfgArray()
{
    SSCS=/opt/SUNWstkcam/bin/sscs


    ARRAY=att25d79
    HOST_WWN_LIST="atrnstats4@10000000c97ad034 atrnstats5@10000000c9842e99"

    ARRAY=att25d80
    #HOST_WWN_LIST="atrnstats5@c4@10000000c97acd82"
    #HOST_WWN_LIST="atrnstats5@c5@10000000c97acd83"
    #HOST_WWN_LIST="atrnstats5@c1@10000000c9842e98"

    ARRAY=att25d81
    HOST_WWN_LIST="atrnstats6@10000000c97ace38 atrnstats7@10000000c97acd83"

    ARRAY=att25d82
    #HOST_WWN_LIST="atrnstats7@10000000c97acd82 atrnstats6@10000000c97ace39"
    HOST_WWN_LIST="atrnstats4@c3@10000000c97ad035"


    IP=`getent hosts ${ARRAY} | awk '{print $1}'`
    ${SSCS} login -h localhost --username root
    ${SSCS} add -i ${IP} registeredarray

    
    export HOST_GROUP="DDP_HG"
    ${SSCS} create --array ${ARRAY} hostgroup ${HOST_GROUP}

    for HOST_WWN in ${HOST_WWN_LIST} ; do
        HOST=$(echo ${HOST_WWN} | awk -F@ '{print $1}')
        CTRL=$(echo ${HOST_WWN} | awk -F@ '{print $2}')
        WWN=$(echo ${HOST_WWN} | awk -F@ '{print $3}')

        ${SSCS} create --array ${ARRAY} --hostgroup ${HOST_GROUP} host ${HOST}
        ${SSCS} create --array ${ARRAY} --wwn ${WWN} --host ${HOST} --os-type solaris initiator init_${HOST}_${CTRL}
    done
    
    
    ${SSCS} create --array ${ARRAY} --raid-level 0 --segsize 128K \
        --readahead off --number-of-disks variable profile DDP_PROFILE
    ${SSCS} create --array ${ARRAY} --profile DDP_PROFILE pool DDP_POOL

    DISKS=`${SSCS}  list --array ${ARRAY} disk | egrep '^Tray' | awk '{print $NF}' | sort -n`

    INDEX=1
    while [ ${INDEX} -le 12 ] ; do
        DISK=$(printf "t85d%02d" $INDEX)
        VDISK=$(printf "vdisk%02d" ${INDEX})    
        ${SSCS} create --array ${ARRAY} --pool DDP_POOL --disks ${DISK} vdisk ${VDISK}

        INDEX=$(expr ${INDEX} + 1)
    done

    INDEX=1
    while [ ${INDEX} -le 12 ] ; do
        VDISK=$(printf "vdisk%02d" ${INDEX})    
        VOL=$(printf "vol%02d" ${INDEX})
        echo "${VOL}(${VDISK})"
        ${SSCS} create --array ${ARRAY} --pool DDP_POOL --vdisk ${VDISK} --size 372GB --controller B volume ${VOL}

        INDEX=$(expr ${INDEX} + 1)
    done


    INDEX=1
    while [ ${INDEX} -le 12 ] ; do
        VOL=$(printf "vol%02d" ${INDEX})
        LUN_ID=$(expr ${INDEX} - 1)
        echo "${VOL} ${LUN_ID}"
        ${SSCS} map --array ${ARRAY} --hostgroup ${HOST_GROUP} --lun-id ${LUN_ID} volume ${VOL} 
        INDEX=$(expr ${INDEX} + 1)
    done

    # /net/159.107.173.93/export/jumpstart/Solaris/Solaris_10_U6/x86/Solaris_10/Misc/.install_config/common/lib/get_disk_list.sh | egrep '^c1' > /tmp/disklist.txt
    # sh /net/nfdbuild173/export/jumpstart/Solaris/Solaris_10_U6/x86/Solaris_10/Misc/.install_config/common/lib/label_disks.sh -f /tmp/disklist.txt
    #sh /net/atjumpx1/JUMP/SOL_MEDIA/13/jumpstart/common/lib/get_disk_list.sh | grep '^c3' | egrep -v 'd31$' > /tmp/disklist.txt
    #sh /net/atjumpx1/JUMP/SOL_MEDIA/13/jumpstart/common/lib/label_disks.sh -f /tmp/disklist.txt

    DISKS=`cat /tmp/disklist.txt | awk '{printf "%s ", $1}'`

    #
    # Create the zpool
    #DISKS="c1t202400A0B85AE967d0 c1t202400A0B85AE967d1 c1t202400A0B85AE967d2 c1t202400A0B85AE967d3 c1t202400A0B85AE967d4 c1t202400A0B85AE967d5 c1t202400A0B85AE967d6 c1t202400A0B85AE967d7 c1t202400A0B85AE967d8"
    #zpool create data raidz ${DISKS}
    # zpool create data mirror c1t202400A0B85AEAF6d0 c2t203400A0B85AE6FBd0 mirror c1t202400A0B85AEAF6d1 c2t203400A0B85AE6FBd1 mirror c1t202400A0B85AEAF6d2 c2t203400A0B85AE6FBd2 mirror c1t202400A0B85AEAF6d3 c2t203400A0B85AE6FBd3 mirror c1t202400A0B85AEAF6d4 c2t203400A0B85AE6FBd4 mirror c1t202400A0B85AEAF6d5 c2t203400A0B85AE6FBd5 mirror c1t202400A0B85AEAF6d6 c2t203400A0B85AE6FBd6 mirror c1t202400A0B85AEAF6d7 c2t203400A0B85AE6FBd7 mirror c1t202400A0B85AEAF6d8 c2t203400A0B85AE6FBd8  
    # zpool create -m none datapool c1t1d0 c1t2d0 c1t3d0

    #/net/159.107.177.94/JUMP/SOL_MEDIA/14/jumpstart/common/lib/get_disk_list.sh -r -n | grep -v c1t > /tmp/disks.txt
    #cat /tmp/disks.txt | sed 's/d/ /' | sort -n -k 2 | sed 's/ /d/' | awk 'BEGIN {printf "mirror"} {printf " %s", $1; if ( (NR % 2) == 0 ) { printf " mirror"; } }'

}
 
createDirs()
{    
    ZPOOL=datapool

    # Create and mount the file systems
    zpool status ${ZPOOL} > /dev/null 2>&1
    if [ $? -ne 0 ] ; then
        echo "ERROR: ZPool ${ZPOOL} doesn't exist?"
        exit 1
    fi

    #
    # Setup data volume
    #
    FS_LIST="data data/ftproot data/db data/ddp data/stats data/archive data/repl"
    for FS in ${FS_LIST} ; do
        echo "Creating ${FS} file system"
        zfs create -o mountpoint=/${FS} ${ZPOOL}/${FS}
        if [ $? -ne 0 ] ; then
            echo "ERROR: zfs create failed"
            exit 1
        fi

        chown -R statsadm:statsadm /${FS}
    done

    zfs set compression=on ${ZPOOL}/data/archive

    # File with be written here by apache
    mkdir /data/stats/temp
    chmod 777 /data/stats/temp

    cat > /data/stats/config <<EOF
export INCOMING_ROOT=/data/ftproot
export DATA_DIR=/data/stats
export ARCHIVE_DIR=/nas/data_files
export DATA_FILE_PREFIX=OSS_Data
export ADMINDB=ddpadmin
export MAX_JOBS=4
EOF

    mkdir /data/stats/oss
    #
    # Redirects for /data/stats/oss/index.php and /data/stats/index.php
    #
    cat > /data/stats/oss/index.php <<EOF
<?php
       header("Location: /php/site_index.php");
?>
EOF
    cat > /data/stats/index.php <<EOF
<?php
// The HEAD method is identical to GET except that the server
// MUST NOT return a message-body in the.
// Basically, if it's a HEAD request just exit
if (stripos(\$_SERVER['REQUEST_METHOD'], 'HEAD') !== FALSE) {
    exit();
}

header("Location: /php/site_index.php");
?>
EOF

    #
    # Log dir for makeAllStats
    #
    mkdir /data/ddp/log
    touch /data/ddp/log/sitemgt.log
    chmod 777 /data/ddp/log/sitemgt.log
    chown -R statsadm:statsadm /data/ddp/log
    
    touch /data/stats/oss/sitelist.txt

    chown -R statsadm:statsadm /data/stats

    mkdir /data/tmp
    chown statsadm /data/tmp
    # Allow MySQL to write here as well
    chmod 777 /data/tmp

}

createDb()
{
    for DB in ${STATS_DB} ${ADMIN_DB} tmp ; do
        echo "Creating database ${DB}"
        /opt/mysql/mysql/bin/mysqladmin create ${DB}
        if [ $? -ne 0 ] ; then 
            error "ERROR: Create database failed"
            exit 1
        fi
    done
}

createDbUsers()
{
    echo "Creating DB Userids"
    HOSTNAME=$(hostname)
    /opt/mysql/mysql/bin/mysql <<EOF
CREATE USER statsadm IDENTIFIED BY '_sadm';
CREATE USER statsadm@localhost IDENTIFIED BY '_sadm';

CREATE USER statsusr IDENTIFIED BY '_susr';
CREATE USER statsusr@localhost IDENTIFIED BY '_susr';

CREATE USER ftpusr IDENTIFIED BY '_ftpusr';
CREATE USER ftpusr@localhost IDENTIFIED BY '_ftpusr';

CREATE USER repl IDENTIFIED BY '_repl';
CREATE USER repl@localhost IDENTIFIED BY '_repl';

GRANT ALL PRIVILEGES ON ${STATS_DB}.* TO statsadm;
GRANT ALL PRIVILEGES ON ${ADMIN_DB}.* TO statsadm;
GRANT ALL PRIVILEGES ON tmp.* TO statsadm;
GRANT ALL PRIVILEGES ON ${STATS_DB}.* TO statsadm@localhost;
GRANT ALL PRIVILEGES ON ${ADMIN_DB}.* TO statsadm@localhost;
GRANT ALL PRIVILEGES ON tmp.* TO statsadm@localhost;
GRANT FILE ON *.* TO 'statsadm'@'localhost';

GRANT SELECT ON ${STATS_DB}.* TO statsusr;
GRANT SELECT ON ${ADMIN_DB}.* TO statsusr;
GRANT SELECT, INSERT, CREATE TEMPORARY TABLES ON tmp.* TO statsusr;
GRANT INSERT ON ${ADMIN_DB}.ddp_cache TO statsusr;

GRANT SELECT ON ${ADMIN_DB}.ftpusers TO ftpusr@localhost;
GRANT SELECT ON ${ADMIN_DB}.ftpusers TO ftpusr;

GRANT REPLICATION SLAVE ON *.* TO repl;
GRANT REPLICATION SLAVE ON *.* TO repl@localhost;

-- Remove the anonymous users
DROP USER ''@'localhost';
DROP USER ''@'%';
DROP USER ''@'${HOSTNAME}';

EOF
    
    if [ -r /opt/mysql/mysql/bin/mysql_config_editor ] ; then
        if [ -r /opt/csw/bin/expect ] ; then
            EXPECT=/opt/csw/bin/expect
        else
            EXPECT=/usr/local/bin/expect
        fi
        ${EXPECT} <<EOF
spawn su - statsadm -c "/opt/mysql/mysql/bin/mysql_config_editor set --user=statsadm --password" 
expect "password: "
send "_sadm\r"
expect eof
EOF
    fi
        
    if [ $? -ne 0 ] ; then 
        error "ERROR: Create failed"
        exit 1
    fi    
}

loadDbSchema()
{
    echo "Loading Database schemas"
    for DB_DDL in ${STATS_DB}:statsdb.ddl ${ADMIN_DB}:ddpadmin.sql ; do
        DB=`echo ${DB_DDL} | awk -F: '{print $1}'`
        DDL=`echo ${DB_DDL} | awk -F: '{print $2}'`
        echo " Loading ${DDL} into ${DB}"
        /opt/mysql/mysql/bin/mysql -D${DB} < /tmp/ddp/current/sql/${DDL}
        if [ $? -ne 0 ] ; then
            echo "ERROR: Failed to load ${DDL} into ${DB}"
            exit 1
        fi
    done
}

installCAM()
{
    cd /tmp
    gzip -dc /tmp/mnt/3pp/cam_host_sw_solaris_x86_6.2.0.13.tar.gz | tar xf -
    cd HostSoftwareCD_6.2.0.13
    ./RunMe.bin -s
    cd /tmp
    rm -rf /tmp/HostSoftwareCD_6.2.0.13
}

extractDDP()
{
    mkdir /tmp/ddp
    cd /tmp/ddp
    gzip -dc ${DDP_SW} | tar xf -
    ln -s `ls | grep DDP` current
}

installDDP()
{
    cd /data/ddp

    if [ -L current ] ; then
        rm -f current
    fi
    gzip -dc ${DDP_SW} | tar xf -
    chown -R ddpadm:statsadm /data/ddp
    ln -s `ls | grep DDP` current
    chown ddpadm:statsadm /data/ddp/current

    for SCRIPT_DIR in analysis sitemgt ; do
       chmod -R +x /data/ddp/current/${SCRIPT_DIR}
    done

    for READ_DIR in php plot adminui ; do
       chmod -R +r /data/ddp/current/${READ_DIR}
    done

    cd /data/ddp/current/plot/bin
    tar xf /tmp/mnt/ddp_3pp/plot_jar.tar
}

configDDPsmf() {
    chmod +x /tmp/ddp/current/server_setup/ddpd_sol
    cp /tmp/ddp/current/server_setup/ddpd.smf.xml /var/svc/manifest/application/ddpd.xml
    svccfg import /var/svc/manifest/application/ddpd.xml

    echo "solaris.smf.manage.ddpd:::DDP Service management::" >> /etc/security/auth_attr
    usermod -A solaris.smf.manage.ddpd ddpadm
    usermod -A solaris.smf.manage.ddpd statsadm

    svcadm enable ddpd
}
    

configHTTPsmf() 
{
    # mod_auth_mysql.so module linked to webstack
    if [ ! -r /opt/webstack/mysql/lib/mysql/libmysqlclient_r.so.15 ] ; then
        if [ ! -d /opt/webstack/mysql/lib/mysql ] ; then
            mkdir -p /opt/webstack/mysql/lib/mysql
        fi
        LIB=`find /opt/webstack/mysql/lib/mysql -name 'libmysqlclient_r.so.*' -type f`
        ln -s ${LIB} /opt/webstack/mysql/lib/mysql/libmysqlclient_r.so.15
    fi

    #svccfg import /var/svc/manifest/network/sun-http-apache22.xml
    if [ -r /opt/csw ] ; then
        svcadm enable svc:/network/cswapache2:default
    else
        svcadm enable svc:/network/http:sun-apache22
    fi
}

configFTPsmf() 
{
    if [ -d /opt/proftpd ] ; then       
       # auth module linked to webstack
        if [ ! -r /opt/webstack/mysql/lib/mysql/libmysqlclient.so.15 ] ; then
            if [ ! -d /opt/webstack/mysql/lib/mysql ] ; then
                mkdir -p /opt/webstack/mysql/lib/mysql
            fi
            LIB=`find /opt/webstack/mysql/lib/mysql -name 'libmysqlclient.so.*' -type f`
            ln -s ${LIB} /opt/webstack/mysql/lib/mysql/libmysqlclient.so.15
        fi
        
        cp /tmp/ddp/current/server_setup/proftpd.smf.xml /var/svc/manifest/network/proftpd.xml
        svccfg import /var/svc/manifest/network/proftpd.xml
        svccfg -s svc:/network/ftp:proftpd setenv LD_LIBRARY_PATH /opt/webstack/mysql/lib/mysql
        svcadm refresh svc:/network/ftp:proftpd
        svcadm enable svc:/network/ftp:proftpd
    else
        svcadm enable svc:/network/cswproftpd:default
    fi
}

buildSW() {

    #
    # mod_auth_mysql for Apache
    #
   #scp ~/Download/mod_auth_mysql_sff.c root@atrnstats4.athtem:/tmp/mod_auth_mysql.c
    #/opt/coolstack/apache2/bin/apxs -I /opt/coolstack/mysql_32bit/include/mysql -L /opt/coolstack/mysql_32bit/lib/mysql -l mysqlclient_r -i -a -c /tmp/mod_auth_mysql.c
    export PATH=/usr/ccs/bin:$PATH
    # Need to tell apxs to include the path of the mysqlclient_r 
    # library into the mod_auth_mysql library so the run time linker
    # can find the mysqlclient_r library when apache loads mod_auth_mysql
    /opt/webstack/apache2/2.2/bin/apxs -I /opt/webstack/mysql/include/mysql -L /opt/webstack/mysql/lib/mysql -l mysqlclient_r -Wl,'-R /opt/webstack/mysql/lib/mysql' -ia -a -c /tmp/mod_auth_mysql.c

    #
    # proftpd
    #
    cd /tmp/
#     /usr/sfw/bin/wget ftp://ftp.sunfreeware.com/pub/freeware/intel/10/proftpd-1.3.1-sol10-x86-local.gz
#     gzip -d proftpd-1.3.1-sol10-x86-local.gz
#     pkgadd -d proftpd-1.3.1-sol10-x86-local -a /tmp/admin all
    /usr/sfw/bin/wget ftp://ftp.ch.proftpd.org/mirror/proftpd.org/distrib/source/proftpd-1.3.2.tar.gz
    gzip -dc proftpd-1.3.2.tar.gz | tar xf -
    cd proftpd-1.3.2
    
    export PATH=/usr/ccs/bin:$PATH
    export LDFLAGS="-R /opt/webstack/mysql/lib/mysql"
    ./configure --prefix=/opt/proftpd -with-modules=mod_sql:mod_sql_mysql --with-includes=/opt/webstack/mysql/include/mysql --with-libraries=/opt/webstack/mysql/lib/mysql
    make
    make install

    cd /
    tar cf /var/tmp/proftpd.tar /opt/proftpd
    gzip /var/tmp/proftpd.tar
    
    
    #
    # Gnuplot
    #
    gzip -dc gnuplot-4.0.0.tar.gz | tar xf -
    cd gnuplot-4.0.0
    export PATH=/usr/ccs/bin:/usr/sfw/bin:/usr/local/bin:$PATH
    ./configure --with-gd --prefix=/opt/gnuplot
    make install
    cd /
    tar cf /var/tmp/gnuplot.tar /opt/gnuplot
    gzip /var/tmp/gnuplot.tar
}

configMySQLsmfddp() {
 configMySQLsmf /data/db ddp /etc/my.cnf
}

startMySQLddp() {
 startMySQL ddp /etc/my.cnf
}

#mkdir -p /tmp/mnt/data
#mount -o vers=3 159.107.220.155:/data /tmp/mnt/data

export PATH=/opt/csw/gnu:/opt/csw/bin:$PATH

CLUSTERED=0

while getopts  "t:d:o:e:a:b:c:r:" flag
do
    case "$flag" in
        t) TASK=${OPTARG};;
        d) DDP_SW=${OPTARG};;
        o) PROC_IP=${OPTARG};;
        e) PRES_IP=${OPTARG};;
        a) NODE_A=${OPTARG};;
        b) NODE_B=${OPTARG};;
        c) CLUSTERED=1;;
        r) RESTART_FROM=${OPTARG};;
    esac
done


        
if [ -z "${PRES_IP}" ] ; then
    HOSTNAME=`hostname`
    HOSTIP=`getent hosts ${HOSTNAME} | awk '{print $1}'`
    PRES_IP=${HOSTIP}
    PROC_IP=${HOSTIP}
fi

if [ "${TASK}" = "install" ] ; then
    
    if [ -z "${DDP_SW}" ] ; then
        echo "Usage: $0 -t install -d ddp_sw_path"
        exit 1
    fi
    if [ ! -r ${DDP_SW} ] ; then
        echo "ERROR: Cannot read DDP_SW"
        exit 1
    fi

# Done by Jumpstart
# cfgDNS
# cfgNTP

        TASK_LIST="
updateHosts
configSendMail
createAdminFile
mount3pp
installCSW
addUsers
createDirs
installMySQL
configMySQL
configMySQLsmfddp
startMySQLddp
installSunStudio
installApachePhp
installCswPerlMod
installPearMod
extract3PPtar
extractDDP
installDDP
createDb
loadDbSchema
createDbUsers
setupSudo
configFTP
configHTTP    
setupMaintenance
configDDPsmf
configFTPsmf
configHTTPsmf
"
else
    TASK_LIST="${TASK}"
fi

if [ ! -z "${RESTART_FROM}" ] ; then
    LIST=${TASK_LIST}
    TASK_LIST=""    
    FOUND_TASK=0
    for TASK in ${LIST} ; do
        if [ ${FOUND_TASK} -eq 0 ] ; then
            if [ "${TASK}" = "${RESTART_FROM}" ] ; then
                FOUND_TASK=1
            fi
        fi

        if [ ${FOUND_TASK} -eq 1 ] ; then
            TASK_LIST="${TASK_LIST} ${TASK}"
        fi
    done
fi

for ONE_TASK in ${TASK_LIST} ; do
    DATE=$(date)
    echo ">>>>>>>>"
    echo ">>>>>>>> ${DATE} Performing task ${ONE_TASK}"
    echo ">>>>>>>>"

    ${ONE_TASK}
done
