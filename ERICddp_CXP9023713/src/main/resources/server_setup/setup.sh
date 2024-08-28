#!/bin/bash

SERVER_SETUP_DIR=$(dirname $0)
SERVER_SETUP_DIR=$(cd ${SERVER_SETUP_DIR} ; pwd)

MARIADB_IMAGE=mariadb:10.5.22
MARIADB_MOUNT_ARGS="-v /etc/my.cnf.d:/etc/mysql/conf.d -v /data/db:/data/db -v /etc/passwd:/etc/passwd -v /etc/group:/etc/group"

rhelVer() {
    if [ -z "${RHEL_VER}" ] ; then
        RH_RELEASE=$(grep release /etc/redhat-release | sed 's/.* release //' | awk '{print $1}')
        echo ${RH_RELEASE} | egrep --silent '^8'
        if [ $? -eq 0 ] ; then
            RHEL_VER=8
        else
            RHEL_VER=7
        fi
    fi
}

installPkgs() {
    local PKGS="$1"

    rhelVer
    if [ ${RHEL_VER} -eq 7 ] ; then
        yum -y --setopt=skip_missing_names_on_install=False install ${PKGS} > /tmp/install_pkgs.log 2>&1
    else
        dnf -y install ${PKGS} > /tmp/install_pkgs.log 2>&1
    fi

    if [ $? -ne 0 ] ; then
        echo "ERROR: Install failed for ${PKGS}"
        cat /tmp/install_pkgs.log
        exit 1
    fi
}

configSFTP() {
    sed -i 's/^#Port.*/Port 202/' /etc/ssh/sshd_config
    systemctl restart sshd

    sed -i 's/^ Port 2222/ Port 22/' /etc/proftpd.conf
    systemctl restart proftpd
}

configYUM()
{
   # Disable any http caching and do a yum update
   if [ ! -r /etc/yum.conf.org ] ; then
       cp /etc/yum.conf /etc/yum.conf.org
   fi

   echo 'deltarpm=0' >> /etc/yum.conf
   find /etc/yum.repos.d/ -type f -delete

   rhelVer
   if [ ${RHEL_VER} -eq 7 ] ; then
       # Need rhel7-extra for php-pear-HTML and proftpd
       for REPO in server extras rh-common optional ; do
            REPO_DIR=rhel-x86_64-server-7
            if [ "${REPO}" != "server" ] ; then
                REPO_DIR=${REPO_DIR}-${REPO}
            fi
            cat > /etc/yum.repos.d/${REPO}.repo <<EOF
[${REPO}]
name=${REPO}
baseurl=https://arm.sero.gic.ericsson.se/artifactory/proj-redhat-repos-rpm-local/repos/${REPO_DIR}
enabled=1
gpgcheck=0
EOF
       done

        # Make sure deltarpm is installed
        installPkgs deltarpm
        if [ $? -ne 0 ] ; then
            echo "ERROR: Failed to install deltarpm"
            exit 1
        fi

       cat > /etc/yum.repos.d/epel.repo <<EOF
[epel]
name=Extra Packages for Enterprise Linux 7 - $basearch
metalink=https://mirrors.fedoraproject.org/metalink?repo=epel-7&arch=\$basearch
enabled=1
gpgcheck=0
EOF
   else
       VER=80
       if [ "${VER}" -eq 80 ] ; then
           for REPO in BaseOS AppStream ; do
               REPO_LC=$(echo ${REPO} | tr '[:upper:]' '[:lower:]')
               cat > /etc/yum.repos.d/${REPO}.repo <<EOF
[${REPO}]
name=${REPO}
baseurl=https://arm.sero.gic.ericsson.se/artifactory/proj-redhat-repos-rpm-local/repos/rhel-x86_64-${REPO_LC}-8
#baseurl=http://yum.linux.ericsson.se/repos/rhel-x86_64-${REPO_LC}-8
#baseurl=http://yum.linux.ericsson.se/distros/rhel-server-8.0-x86_64/${REPO}
enabled=1
gpgcheck=0

EOF
           done
       fi

       # Needed for EPEL
       cat > /etc/yum.repos.d/builder.repo <<EOF
[builder]
name=builder
baseurl=https://arm.sero.gic.ericsson.se/artifactory/proj-redhat-repos-rpm-local/repos/rhel-x86_64-codeready-builder-8/
enabled=1
gpgcheck=0

EOF


       CONFIG_EPEL="YES"
       if [ ${CONFIG_EPEL} = "YES" ] ; then
       cat > /etc/yum.repos.d/epel.repo <<'EOF'
[epel]
name=Extra Packages for Enterprise Linux 8 - $basearch
metalink=https://mirrors.fedoraproject.org/metalink?repo=epel-$releasever&arch=$basearch
enabled=1
gpgcheck=0
EOF
       fi
   fi


   yum check-update > /dev/null
   if [ $? -eq 1 ] ; then
       echo "ERROR: Problem with yum check-update"
       exit 1
   fi

    # Setup for MariaDB 10.5
    yum -y module enable mariadb:10.5
}

configNetwork() {
    if [ ! -r /root/hosts.org ] ; then
        cp /etc/hosts /root/hosts.org
    fi
    cat /root/hosts.org | egrep -v '^::1' > /etc/hosts

    CONNECTION=$(nmcli --terse --fields NAME connection show --active | head -1)
    DEVICE=$(nmcli --terse --fields GENERAL.DEVICES connection show "${CONNECTION}" | awk -F: '{print $2}')
    # Seems to be different formats
    # IP4.ADDRESS[1]:ip = 10.44.79.45/24, gw = 10.44.79.1
    # IP4.DNS[1]:159.107.173.3
    # IP4.DNS[2]:159.107.173.12
    # or
    # IP4.ADDRESS[1]:10.44.79.46/24
    #IP4.GATEWAY:10.44.79.1
    #IP4.ROUTE[1]:dst = 159.107.173.209/32, nh = 10.44.79.1, mt = 100
    #IP4.DNS[1]:159.107.173.3
    #IP4.DNS[2]:159.107.173.12
    #IP4.DOMAIN[1]:athtem.eei.ericsson.se


    nmcli --terse -fields IP4 connection show "${CONNECTION}" > /tmp/nic.IP4
    grep IP4.GATEWAY /tmp/nic.IP4 > /dev/null
    if [ $? -eq 0 ] ; then
        IP_ADDR_NETMASK=$(egrep '^IP4.ADDRESS' /tmp/nic.IP4 | awk -F: '{print $2}')
        DEFAULT_ROUTE=$(egrep '^IP4.GATEWAY' /tmp/nic.IP4 | awk -F: '{print $2}')
    else
        IP_ADDR_NETMASK=$(egrep '^IP4.ADDRESS' /tmp/nic.IP4 | awk '{print $3}' | sed 's/,//')
        DEFAULT_ROUTE=$(egrep '^IP4.ADDRESS' /tmp/nic.IP4 | awk '{print $6}')
    fi
    DNS_DOMAIN=$(egrep '^IP4.DOMAIN' /tmp/nic.IP4 | awk -F: '{print $2}')
    DNS_SERVERS=$(egrep '^IP4.DNS' /tmp/nic.IP4 | awk -F: '{printf "%s ", $2}')

    DNS_SEARCH_ARG=""
    if [ ! -z "${DNS_DOMAIN}" ] ; then
        DNS_SEARCH_ARG="ipv4.dns-search ${DNS_DOMAIN}"
    fi

    #nmcli connection delete "${CONNECTION}"
    nmcli connection modify "${CONNECTION}" ipv4.method manual \
          ip4 ${IP_ADDR_NETMASK} gw4 ${DEFAULT_ROUTE}
    nmcli connection modify "${CONNECTION}" \
          ipv4.dns "${DNS_SERVERS}" ${DNS_SEARCH_ARG}
    nmcli connection modify "${CONNECTION}" ipv6.method ignore

    # nmcli conn mod ${NIC} \
    #     ipv4.method manual \
    #     ipv4.addresses "${IP_ADDR_NETMASK} ${DEFAULT_ROUTE}" \
    #     ipv4.dns "${DNS_SERVERS}" ipv4.dns-search ${DNS_DOMAIN}
    #nmcli conn mod eno2 connection.autoconnect yes ipv4.method manual ipv4.addresses "159.107.180.120/24 159.107.180.1"

    UUID_LIST=$(nmcli --mode tabular --terse --fields UUID connection show --active)
    for UUID in ${UUID_LIST} ; do
        nmcli --terse --fields DHCP4 connection show ${UUID} > /tmp/DHCP4.txt
        IP=$(grep ':ip_address' /tmp/DHCP4.txt | awk '{print $3}' | grep 192.168.255)
        if [ ! -z "${IP}" ] ; then
            NETMASK=$(grep ':subnet_mask' /tmp/DHCP4.txt | awk '{print $3}')
            #CIDR=$(ipcal -p ${IP} ${NETMASK})
            CIDR=24
            ARG="ipv4.method manual ipv4.addr ${IP}/${CIDR}"
            DNS_SERV=$(grep ':domain_name_servers' /tmp/DHCP4.txt | awk '{print $3}')
            DNS_SEARCH=$(grep ':domain_search' /tmp/DHCP4.txt | awk '{print $3}')
            ARG="${ARG} ipv4.dns ${DNS_SERV} ipv4.dns-search ${DNS_SEARCH}"
            nmcli connection modify ${UUID} ${ARG}
            nmcli connection up ${UUID}
        fi
    done

    HOSTNAME=$(hostname)
    echo ${HOSTNAME} | egrep '\.' > /dev/null
    if [ $? -eq 0 ] ; then
        SHORT_HOSTNAME=$(echo ${HOSTNAME} | awk -F\. '{print $1}')
        hostnamectl set-hostname ${SHORT_HOSTNAME}
    fi

}

configPriv() {
    nmcli connection delete ${NIC_PRIV}
    nmcli connection add type ethernet ifname ${NIC_PRIV} con-name ${NIC_PRIV} ip4 ${IP_PRIV}/24
    nmcli connection up ${NIC_PRIV}
    cp /etc/hosts /etc/hosts.sav
    cat /etc/hosts.sav | egrep -v '-priv$' > /etc/hosts

    cat >> /etc/hosts <<EOF
192.168.255.2 ddprepl-priv
${IP_PRIV} ${SERVICE_NAME}-priv
EOF

}

configNTP() {
    installPkgs chrony
    if [ ! -r /etc/chrony.conf.org ] ; then
        cp /etc/chrony.conf /etc/chrony.conf.org
    fi
    cat /etc/chrony.conf.org | egrep -v '^server' > /etc/chrony.conf
    echo "server 159.107.173.12" >> /etc/chrony.conf
    if [ -x /usr/sbin/ntpdate ] ; then
        /usr/sbin/ntpdate 159.107.173.12
    fi
    systemctl restart chronyd.service
}

install3PP() {
    installDb3PP
    installProcessing3PP
    installPresentation3PP
}

installDb3PP() {
    installPkgs "podman rsync"
}

configCPAN() {
    if [ -r /root/.cpan/CPAN/MyConfig.pm ] ; then
        return
    fi

    if [ ! -d /root/.cpan/CPAN ] ;then
        mkdir -p /root/.cpan/CPAN
    fi

    cat > /root/.cpan/CPAN/MyConfig.pm <<EOF
\$CPAN::Config = {
  'applypatch' => q[],
  'auto_commit' => q[0],
  'build_cache' => q[100],
  'build_dir' => q[/root/.cpan/build],
  'build_dir_reuse' => q[0],
  'build_requires_install_policy' => q[yes],
  'bzip2' => q[/usr/bin/bzip2],
  'cache_metadata' => q[1],
  'check_sigs' => q[0],
  'colorize_output' => q[0],
  'commandnumber_in_prompt' => q[1],
  'connect_to_internet_ok' => q[1],
  'cpan_home' => q[/root/.cpan],
  'ftp_passive' => q[1],
  'ftp_proxy' => q[],
  'getcwd' => q[cwd],
  'gpg' => q[/usr/bin/gpg],
  'gzip' => q[/usr/bin/gzip],
  'halt_on_failure' => q[0],
  'histfile' => q[/root/.cpan/histfile],
  'histsize' => q[100],
  'http_proxy' => q[],
  'inactivity_timeout' => q[0],
  'index_expire' => q[1],
  'inhibit_startup_message' => q[0],
  'keep_source_where' => q[/root/.cpan/sources],
  'load_module_verbosity' => q[none],
  'make' => q[/usr/bin/make],
  'make_arg' => q[],
  'make_install_arg' => q[],
  'make_install_make_command' => q[/usr/bin/make],
  'makepl_arg' => q[],
  'mbuild_arg' => q[],
  'mbuild_install_arg' => q[],
  'mbuild_install_build_command' => q[./Build],
  'mbuildpl_arg' => q[],
  'no_proxy' => q[],
  'pager' => q[/usr/bin/less],
  'patch' => q[],
  'perl5lib_verbosity' => q[none],
  'prefer_external_tar' => q[1],
  'prefer_installer' => q[MB],
  'prefs_dir' => q[/root/.cpan/prefs],
  'prerequisites_policy' => q[follow],
  'scan_cache' => q[atstart],
  'shell' => q[/bin/bash],
  'show_unparsable_versions' => q[0],
  'show_upload_date' => q[0],
  'show_zero_versions' => q[0],
  'tar' => q[/usr/bin/tar],
  'tar_verbosity' => q[none],
  'term_is_latin' => q[1],
  'term_ornaments' => q[1],
  'test_report' => q[0],
  'trust_test_report_history' => q[0],
  'unzip' => q[/usr/bin/unzip],
  'urllist' => [q[http://mirror.netcologne.de/cpan/], q[http://mirror.yandex.ru/mirrors/cpan/], q[http://cpan.panu.it/]],
  'use_sqlite' => q[0],
  'version_timeout' => q[15],
  'wget' => q[/usr/bin/wget],
  'yaml_load_code' => q[0],
  'yaml_module' => q[YAML],
};
1;
__END__
EOF
}

installProcessing3PP() {
    PKG_LIST="moreutils-parallel zip perl-DBD-MySQL libdbi-dbd-mysql perl-TimeDate  gnuplot perl-CPAN perl-Time-HiRes perl-local-lib perl-XML-Parser perl-XML-SAX perl-XML-Simple perl-JSON perl-String-CRC32 perl-Date-Calc perl-LDAP moreutils-parallel perl-Archive-Zip perl-PerlIO-gzip perl-Module-Load sysstat mariadb perl-XML-LibXML perl-Test-Simple unzip perl-Data-Dumper gcc make perl-XML-DOM perl-JSON-XS perl-Text-Unidecode"

    CPAN_LIST="XML::SAX::Expat Net::Graphite DBD::SQLite DateTime"

    rhelVer

    if [ ${RHEL_VER} -eq 8 ] ; then
        PKG_LIST="${PKG_LIST} java-11-openjdk perl-Test"
    else
        PKG_LIST="${PKG_LIST} java-1.7.0-openjdk"
    fi

    installPkgs "${PKG_LIST}"

    configCPAN
    for MODULE in ${CPAN_LIST} ; do
        perl -MCPAN -e "CPAN::Shell->install(${MODULE})" > /tmp/${MODULE}.log 2>&1
        if [ $? -ne 0 ] ; then
            echo "ERROR: Failed to install perl module ${MODULE}"
            exit 1
        fi
    done
}

installTomcat() {
    local VER=$(curl --silent http://ftp.heanet.ie/mirrors/www.apache.org/dist/tomcat/tomcat-9/ | grep 'href="v9.0' | awk '{print $2}' | sed 's/.*">//' | sed 's|/.*||' | tail -1)
    local VER_NUM=$(echo "${VER}" | sed 's/^v//')
    local TC_URL=http://ftp.heanet.ie/mirrors/www.apache.org/dist/tomcat/tomcat-9/${VER}/bin/apache-tomcat-${VER_NUM}.tar.gz
    curl --show-error --silent --output /tmp/tomcat.tar.gz ${TC_URL}
    if [ $? -ne 0 ] ; then
        echo "ERROR: Failed to download tomcat ${TC_URL}"
        exit 1
    fi
    cd /usr/local
    tar -xf /tmp/tomcat.tar.gz
    if [ $? -ne 0 ] ; then
        echo "ERROR: Failed to extract tomcat"
        exit 1
    fi
    mv apache-tomcat* tomcat
    useradd -r tomcat
    chown -R tomcat:tomcat /usr/local/tomcat
    cat > /etc/systemd/system/tomcat.service <<EOF
[Unit]
Description=Apache Tomcat Server
After=syslog.target network.target

[Service]
Type=forking
User=tomcat
Group=tomcat
EnvironmentFile=/etc/tomcat/tomcat.conf

ExecStart=/usr/local/tomcat/bin/catalina.sh start
ExecStop=/usr/local/tomcat/bin/catalina.sh stop

RestartSec=10
Restart=always
[Install]
WantedBy=multi-user.target
EOF

    mkdir /etc/tomcat
    cat > /etc/tomcat/tomcat.conf <<EOF
CATALINA_PID=/usr/local/tomcat/temp/tomcat.pid
CATALINA_HOME=/usr/local/tomcat
CATALINA_BASE=/usr/local/tomcat
CATALINA_TMPDIR=/usr/local/tomcat/temp

EOF
    systemctl daemon-reload
}

installPresentation3PP() {
    # liberation-sans-fonts.noarch for qplot graphs
    # perl-Data-Dumper, perl-DBD-MySQL,  perl-JSON perl-JSON-XS perl-Time-HiRes CPAN -DateTime needed cause the maintenance task
    # runs on the presentation server
    # Also need the  perl-XML-LibXML perl-XML-DOM to allow the re-processing of the healthchecks
    PKG_LIST="postfix httpd php php-ldap expect php-pear liberation-sans-fonts.noarch php-gd mailx sysstat unzip mariadb wget perl-Data-Dumper perl-DBD-MySQL perl-TimeDate perl-XML-Simple perl-JSON perl-XML-LibXML perl-Test-Simple perl-JSON-XS perl-XML-DOM proftpd proftpd-mysql screen perl-Time-HiRes perl-CPAN perl-List-MoreUtils"

    rhelVer
    if [ ${RHEL_VER} -eq 7 ] ; then
        PKG_LIST="${PKG_LIST} php-mysql php-pear-HTML-QuickForm.noarch php-pear-HTML-Table java-1.7.0-openjdk tomcat python-flask mysql-connector-python mod_wsgi"
        PEAR_LIST="HTML_QuickForm2"
        PHP_COMPOSER_LIST=""
        PERL_LIST="Text::MultiMarkdown"
        PIP_LIST=""
    else
        PKG_LIST="${PKG_LIST} php-mysqlnd java-11-openjdk php-json php-pecl-zip perl-Test python3-pip python3-flask python3-mod_wsgi"
        # Use 2.2.0 for HTML_QuickForm2, seems to be a problem with 2.3.0 (require_once are commented out)
        PEAR_LIST="HTML_Table Mail HTML_QuickForm2-2.2.0"
        PHP_COMPOSER_LIST="firebase/php-jwt"
        PERL_LIST="Text::MultiMarkdown DateTime"
        export PERL_CANARY_STABILITY_NOPROMPT=1
        # Seems to be a problem with mysql-connector-python 8.0.30
        # We get an error mysql.connector.errors.ProgrammingError: Character set 'utf8' unsupported
        PIP_LIST="mysql-connector-python==8.0.29"
    fi

    installPkgs "${PKG_LIST}"

    if [ ! -z "${PEAR_LIST}" ] ; then
        #pear config-set http_proxy http://atproxy1.athtem.eei.ericsson.se:3128/
        # Connection to `ssl://atproxy1.athtem.eei.ericsson.se:3128' failed:
        for ONE in ${PEAR_LIST} ; do
            # pear will pick up on http_proxy env variable so we need to make sure it's not set
            env -u http_proxy pear install ${ONE}
            if [ $? -ne 0 ] ; then
                echo "ERROR: Failed to install ${ONE}"
                exit 1
            fi
        done
    fi

    if [ ! -z "${PHP_COMPOSER_LIST}" ] ; then
        if [ ! -r /usr/local/bin/composer ] ; then
            curl -sS https://getcomposer.org/installer | php
            mv composer.phar /usr/local/bin/composer
            chmod +x /usr/local/bin/composer
        fi

        if [ ! -d /usr/share/php-composer ] ; then
            mkdir /usr/share/php-composer
        fi

        cd /usr/share/php-composer
        for PKG in ${PHP_COMPOSER_LIST} ; do
            COMPOSER_ALLOW_SUPERUSER=1 /usr/local/bin/composer require ${PKG}
        done
    fi

    if [ ! -z "${PERL_LIST}" ] ; then
        configCPAN
        for MODULE in ${PERL_LIST} ; do
            perl -MCPAN -e "CPAN::Shell->install(${MODULE})" > /tmp/${MODULE}.log 2>&1
            if [ $? -ne 0 ] ; then
                echo "ERROR: Failed to install perl module ${MODULE}"
                exit 1
            fi
        done
    fi

    if [ ! -z "${PIP_LIST}" ] ; then
        for MODULE in ${PIP_LIST} ; do
            pip3 install ${MODULE} > /tmp/${MODULE}.log 2>&1
            if [ $? -ne 0 ] ; then
                echo "ERROR: Failed to install pip module ${MODULE}"
                cat /tmp/${MODULE}.log
                exit 1
            fi
        done
    fi

    if [ ${RHEL_VER} -eq 8 ] ; then
        installTomcat
    fi

    if [ ! -d /tmp/mnt/ddp_3pp ] ; then
        mkdir -p /tmp/mnt
        mount -o ro ${DDP_3PP} /tmp/mnt
        if [ $? -ne 0 ] ; then
            echo "ERROR: Failed to mount ${DDP_3PP}"
            rm -rf /tmp/mnt/ddp_3pp
            exit 1
        fi
    fi

    if [ ! -d /opt/yui ] ; then
        cd /opt
        gzip -dc /tmp/mnt/ddp_3pp/yui.tar.gz | tar xf -
    fi

    if [ ! -d /opt/highcharts ] ; then
        mkdir /opt/highcharts /opt/jquery
        cd /opt
        gzip -dc /tmp/mnt/ddp_3pp/highcharts.tar.gz | tar xf -
        cp /tmp/mnt/ddp_3pp/jquery-3.0.0.js /opt/jquery
    fi
}

installDDC() {
    RELEASE=$(wget -q -O - https://arm1s11-eiffel004.eiffel.gic.ericsson.se:8443/nexus/content/repositories/releases/com/ericsson/oss/itpf/monitoring/ERICddccore_CXP9035927/maven-metadata.xml | grep release | sed 's/^ *<release>//' | sed 's|</release>.*||')
    wget -q -O /root/ERICddccore_CXP9035927-${RELEASE}.rpm https://arm1s11-eiffel004.eiffel.gic.ericsson.se:8443/nexus/content/repositories/releases/com/ericsson/oss/itpf/monitoring/ERICddccore_CXP9035927/${RELEASE}/ERICddccore_CXP9035927-${RELEASE}.rpm
    installPkgs /root/ERICddccore_CXP9035927-${RELEASE}.rpm
    systemctl stop ddc
    /bin/rm /opt/ericsson/ERICddc/etc/appl.env
    echo "APPL=DDP" > /opt/ericsson/ERICddc/etc/appl.env
    mkdir /opt/ericsson/ERICddc/etc/appl
    cat > /opt/ericsson/ERICddc/etc/appl/DDP.env <<EOF
SITEDATAROOT=/data/ddc_data
DATAROOT=/data/ddc_data
EOF
    mkdir /data/ddc_data /data/ddc_data/config /data/ddc_data/config/plugins
    cat > /data/ddc_data/config/plugins/ddp.dat <<EOF
SCRIPT=/data/ddp/current/server_setup/ddc_plugin.sh
EOF
    systemctl start ddc

}

addUsers()
{
    #
    # Create the statsuser
    #
    groupadd -g 500 statsadm
    useradd -g 500 -u 500 statsadm
    #
    # Create the statsuser
    #
    groupadd -g 501 statsuser
    useradd -g 501 -u 501 statsuser
    usermod -G statsuser statsadm
}

createDirs() {
    mkdir /data/ddp /data/ftproot /data/stats
    chown statsadm:statsadm /data /data/ddp /data/ftproot /data/stats

    #
    # Log dir for makeAllStats
    #
    mkdir /data/ddp/log
    touch /data/ddp/log/sitemgt.log /data/ddp/log/perf.log /data/ddp/log/execution.log
    chmod 777 /data/ddp/log/sitemgt.log /data/ddp/log/perf.log /data/ddp/log/execution.log
    chown -R statsadm:statsadm /data/ddp/log

    CPU_COUNT=$(nproc)
    MAX_JOBS=$(expr ${CPU_COUNT} / 2)

    cat > /data/stats/config <<EOF
export INCOMING_ROOT=/data/ftproot
export DATA_DIR=/data/stats
export ARCHIVE_DIR=/nas/data_files
export ADMINDB=ddpadmin
export MAX_JOBS=${MAX_JOBS}
EOF
    for OSS in oss ddp eniq tor ; do
        mkdir /data/stats/${OSS}
    done

    # qplot puts files in here
    mkdir /data/stats/temp
    chmod 777 /data/stats/temp

    chown -R statsadm:statsadm /data/stats


    mkdir /data/tmp /data/tmp/incr /data/ddp/upgrade
    chown statsadm:statsadm /data/tmp /data/tmp/incr /data/ddp/upgrade
    # Allow MySQL to write here as well
    chmod 777 /data/tmp

    # Make sure /data is owned by statsadm
    chown statsadm:statsadm /data
}

createFS() {
    for FS in ${FS_LIST} ; do
        NAME=$(echo ${FS} | awk -F: '{print $1}')
        SIZE=$(echo ${FS} | awk -F: '{print $2}')
        MNT=$(echo ${FS} | awk -F: '{print $3}')

        if [ ! -d ${MNT}/${NAME} ] ; then
            mkdir ${MNT}/${NAME}
        fi

        if [ "${SIZE}" = "100%FREE" ] ; then
            SIZE_ARG="--extents 100%FREE"
        else
            SIZE_ARG="--size ${SIZE}"
        fi
        lvcreate --name ${NAME} ${SIZE_ARG} ${LVG}
        if [ $? -ne 0 ] ; then
            echo "ERROR: Failed to create ${NAME}"
            exit 1
        fi

        TARGET_DEV=/dev/${LVG}/${NAME}

        mkfs --type=xfs -f -q ${TARGET_DEV}
    done
}

mountFS() {
    for FS in ${FS_LIST} ; do
        NAME=$(echo ${FS} | awk -F: '{print $1}')
        SIZE=$(echo ${FS} | awk -F: '{print $2}')
        MNT=$(echo ${FS} | awk -F: '{print $3}')

        TARGET_DEV=/dev/${LVG}/${NAME}

        UNIT_NAME=$(echo ${MNT}/${NAME} | sed 's|^/||' | sed 's|/|-|g')
        if [ -z "${VDO_DEV}" ] ; then
            REQUIRES_LINE=""
        else
            REQUIRES_LINE="Requires = vdo.service"
        fi
        cat > /etc/systemd/system/${UNIT_NAME}.mount <<EOF
[Unit]
Description = Mount ${NAME} file system
Conflicts = umount.target
${REQUIRES_LINE}

[Mount]
What = ${TARGET_DEV}
Where = ${MNT}/${NAME}
Type = xfs

[Install]
WantedBy = multi-user.target
EOF

        systemctl daemon-reload
        systemctl enable ${UNIT_NAME}.mount
        systemctl start ${UNIT_NAME}.mount
    done

}

installMySQLdb() {
    DB_ROOT=$1
    CFG=$2
    if [ ! -d "${DB_ROOT}" ] ; then
        echo "installMySQLdb: directory doesn't exist ${DB_ROOT}"
        exit 1
    fi

    for DIR in data log var tmpdir ; do
        mkdir ${DB_ROOT}/${DIR}
    done

    grep --silent -w mysql /etc/passwd
    if [ $? -ne 0 ] ; then
        useradd --user-group --no-create-home --home-dir ${DB_ROOT} --shell /sbin/nologin mysql
    fi

    chown -R mysql:mysql ${DB_ROOT}

    podman run --rm ${MARIADB_MOUNT_ARGS} --entrypoint /usr/bin/mysql_install_db ${MARIADB_IMAGE} --basedir=/usr --datadir=${DB_ROOT}/data --user=mysql > /tmp/install_db.txt 2>&1
    if [ $? -ne 0 ] ; then
        echo "ERROR: Failed to install db in ${DB_ROOT}"
        cat /tmp/install_db.txt
        exit 1
    fi

    chown -R mysql:mysql ${DB_ROOT}
}

createMySqlCnf() {

    installPkgs crudini

    if [ -r /etc/my.cnf ] ; then
        mv /etc/my.cnf /root
    fi
    cat > /etc/my.cnf <<EOF
#
# include all files from the config directory
#
!includedir /etc/my.cnf.d
EOF
    if [ -r /etc/my.cnf.d/mariadb-server.cnf ] ; then
        mv /etc/my.cnf.d/mariadb-server.cnf /root
        ln -s /dev/null /etc/my.cnf.d/mariadb-server.cnf
    fi

    if [ -z "${MY_CNF}" ] ; then
        MY_CNF=${SERVER_SETUP_DIR}/my.cnf
    fi
    /bin/cp ${MY_CNF} /etc/my.cnf.d/ddpdb.cnf

   # MariaDB 10 doesn't use the mysqld_safe entries
   rhelVer
   if [ ${RHEL_VER} -gt 7 ] ; then
       sed -i 's/^\[mysqld_safe\]//' /etc/my.cnf.d/ddpdb.cnf
   fi

    RAM_GB=$(grep MemTotal /proc/meminfo | awk '{printf "%d", ($2/(1024*1024))}')
    LOG_FILE=1024
    if [ ${RAM_GB} -ge 23 ] ; then
        POOL_SIZE=16
        POOL_INST=8
        LOG_BUFF=512
    elif [ ${RAM_GB} -ge 15 ] ; then
        POOL_SIZE=12
        POOL_INST=4
        LOG_BUFF=256
    else # dev server
        POOL_SIZE=1
        POOL_INST=2
        LOG_BUFF=128
        LOG_FILE=128
    fi

    crudini  --set /etc/my.cnf.d/ddpdb.cnf mysqld innodb_buffer_pool_size ${POOL_SIZE}G
    crudini  --set /etc/my.cnf.d/ddpdb.cnf mysqld innodb_buffer_pool_instances ${POOL_INST}
    crudini  --set /etc/my.cnf.d/ddpdb.cnf mysqld innodb_log_buffer_size ${LOG_BUFF}M
    crudini  --set /etc/my.cnf.d/ddpdb.cnf mysqld innodb_log_file_size ${LOG_FILE}M

    # Tell clients where the socket is. Initial we use the socket cause
    # we haven't created our users yet. Later when we've created our users
    # we'll overwrite the client.cnf do that we're using dbhost to connect
    # instead of the socket
    cat > /etc/my.cnf.d/client.cnf <<EOF
[client]
socket=/data/db/var/socket
EOF

}

configMySQL()
{
    echo "Configuring MySQL"

    createMySqlCnf

    installMySQLdb /data/db /etc/my.cnf

    if [ ! -d /etc/systemd/system/mariadb.service.d ] ; then
        mkdir /etc/systemd/system/mariadb.service.d
    fi
    cat > /etc/systemd/system/mariadb.service.d/limits.conf <<EOF
[Service]
LimitNOFILE=160000
EOF
    cat > /etc/systemd/system/mariadb.service.d/depend.conf <<EOF
[Unit]
After = data-db.mount
Requires = data-db.mount
EOF

cat > /usr/lib/systemd/system/mariadb.service <<EOF
[Unit]
Description=MariaDB Podman Container
After=network.target

[Service]
Type=simple
TimeoutStartSec=5m
ExecStartPre=-/usr/bin/podman rm mariadb
ExecStart=/usr/bin/podman run --name mariadb --network=host ${MARIADB_MOUNT_ARGS} ${MARIADB_IMAGE}

ExecReload=-/usr/bin/podman stop mariadb
ExecReload=-/usr/bin/podman rm mariadb
ExecStop=-/usr/bin/podman stop mariadb
Restart=always
RestartSec=30

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable mariadb.service
    systemctl start mariadb.service

    # Wait here for MariaDB to start
    sleep 30
}


createDb()
{
    for DB in ${STATS_DB} ${ADMIN_DB} tmp ; do
        echo "Creating database ${DB}"
        mysqladmin create ${DB}
        if [ $? -ne 0 ] ; then
            echo "ERROR: Create database failed"
            exit 1
        fi
    done
}

extractDDP()
{
    if [ -d /tmp/ddp ] ; then
        rm -rf /tmp/ddp
    fi

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
    DDP_SW_DIR=$(ls | grep DDP)
    chown -R statsadm:statsadm /data/ddp/${DDP_SW_DIR}
    ln -s ${DDP_SW_DIR} current
    chown statsadm:statsadm /data/ddp/current

    for SCRIPT_DIR in analysis sitemgt ; do
       chmod -R +x /data/ddp/current/${SCRIPT_DIR}
    done

    for READ_DIR in php plot adminui ; do
       chmod -R +r /data/ddp/current/${READ_DIR}
    done
}


loadDbSchema()
{
    echo "Loading Database schemas"
    for DB_DDL in ${STATS_DB}:statsdb.ddl ${ADMIN_DB}:ddpadmin.sql ; do
        DB=`echo ${DB_DDL} | awk -F: '{print $1}'`
        DDL=`echo ${DB_DDL} | awk -F: '{print $2}'`
        echo " Loading ${DDL} into ${DB}"
        mysql -D${DB} < /data/ddp/current/sql/${DDL}
        if [ $? -ne 0 ] ; then
            echo "ERROR: Failed to load ${DDL} into ${DB}"
            exit 1
        fi
    done
}

createDbCronJobs() {
    cat > /etc/cron.d/ddp_cleanup_var <<EOF
0 1 * * * mysql find /data/db/var -type f -name '*.bcp' -not -empty -mtime +1 -delete
EOF
}

createDbSrvCerts() {
    if [ -z "${SERVICE_NAME}" ] ; then
        echo "ERROR: SERVICE_NAME must be defined"
        exit 1
    fi

    if [ ! -d /data/db/certs ] ; then
        mkdir /data/db/certs
    fi

    local ALL_CA_FILE=/data/db/certs/allca.cer

    local CA_KEY_FILE=/data/db/certs/ca.key
    if [ ! -r ${CA_KEY_FILE} ] ; then
        openssl genrsa -out ${CA_KEY_FILE} 2048
    fi

    local CA_CERT_FILE=/data/db/certs/ca.cer
    openssl req -new -key ${CA_KEY_FILE} -subj "/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}-db-ca" -out /tmp/ca.csr
    cat > /tmp/cert.ext <<EOF
basicConstraints=critical,CA:true,pathlen:5
keyUsage=critical,keyCertSign,cRLSign
EOF
    openssl x509 -req -set_serial 1 -days 3650 -in /tmp/ca.csr -extfile /tmp/cert.ext -signkey ${CA_KEY_FILE} -out ${CA_CERT_FILE}
    cat ${CA_CERT_FILE} > ${ALL_CA_FILE}

    local SRV_KEY_FILE=/data/db/certs/server.key
    if [ ! -r ${SRV_KEY_FILE} ] ; then
        openssl genrsa -out ${SRV_KEY_FILE} 2048
    fi
    SRV_CERT_FILE=/data/db/certs/server.cer
    openssl req -new -key ${SRV_KEY_FILE} -subj "/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}-db" -out /tmp/srv.csr
    IP_ADDR=$(ip -o -4 addr | grep 192.168.255 | awk '{print $4}' | awk -F\/ '{print $1}')
    cat > /tmp/cert.ext <<EOF
keyUsage=critical,digitalSignature,keyEncipherment,keyAgreement
subjectAltName=IP:${IP_ADDR},DNS:${SERVICE_NAME}-db,DNS:dbhost
subjectKeyIdentifier=hash
EOF
    openssl x509 -req -set_serial 1 -days 3650 -in /tmp/srv.csr -extfile /tmp/cert.ext -CA ${CA_CERT_FILE} -CAkey ${CA_KEY_FILE} -out ${SRV_CERT_FILE}

    cat > /tmp/cert.ext <<EOF
basicConstraints=critical,CA:true,pathlen:5
keyUsage=critical,keyCertSign,cRLSign
EOF
    for USER in statsadm statsusr ftpusr repl ; do
        KEY_FILE=/data/db/certs/${USER}-ca.key
        openssl genrsa -out ${KEY_FILE} 2048
        openssl req -new -key ${KEY_FILE} -subj "/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}-db-${USER}-ca" -out /tmp/user.csr
        CERT_FILE=/data/db/certs/${USER}-ca.cer
        openssl x509 -req -set_serial 1 -days 3650 -in /tmp/user.csr -extfile /tmp/cert.ext -CA ${CA_CERT_FILE} -CAkey ${CA_KEY_FILE} -out ${CERT_FILE}
        cat ${CERT_FILE} >> ${ALL_CA_FILE}
    done
    chown mysql:mysql /data/db/certs/*
}

signDbClientCer() {
    local CSR=$1
    local EXT=$2
    local CER=$3
    local USER=$4

    scp $CSR dbhost:/tmp/user.csr
    scp $EXT dbhost:/tmp/user.ext
    ssh dbhost openssl x509 -req -set_serial 1 -days 3650 -in /tmp/user.csr -extfile /tmp/user.ext -CA /data/db/certs/${USER}-ca.cer -CAkey /data/db/certs/${USER}-ca.key -out /tmp/user.cer
    ssh dbhost "cat /data/db/certs/${USER}-ca.cer >> /tmp/user.cer"
    scp dbhost:/tmp/user.cer $CER
}

signClientCertTask() {
    # Hack use service name to get the name of the cert
    if [ -z "${SERVICE_NAME}" ] ; then
        echo "ERROR: SERVICE_NAME must be defined"
        exit 1
    fi

    signDbClientCer /tmp/${SERVICE_NAME}.csr /tmp/${SERVICE_NAME}.ext /tmp/${SERVICE_NAME}.cer ${SERVICE_NAME}
}

createDbClientCerts() {
    if [ -z "${SERVICE_NAME}" ] ; then
        echo "ERROR: SERVICE_NAME must be defined"
        exit 1
    fi

    mkdir -p /etc/certs
    local KEY_FILE=/etc/certs/db-client.key
    if [ ! -r ${KEY_FILE} ] ; then
        openssl genrsa -out ${KEY_FILE} 2048
    fi
    chmod 644 ${KEY_FILE}

    local PRIV_IP=$(getent hosts ${SERVICE_NAME}-priv | awk '{print $1}')
    cat > /tmp/cert.ext <<EOF
keyUsage=critical,digitalSignature,keyEncipherment,keyAgreement
subjectAltName=IP:${PRIV_IP},DNS:${SERVICE_NAME}-priv
subjectKeyIdentifier=hash
EOF
    for USER in statsadm statsusr ftpusr repl ; do
        openssl req -new -key ${KEY_FILE} -subj "/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}-db-${USER}" -out /tmp/user.csr
        signDbClientCer /tmp/user.csr /tmp/cert.ext /etc/certs/db-client-${USER}.cer ${USER}
    done

    scp dbhost:/data/db/certs/ca.cer /etc/certs/db-srv-ca.cer
}

createDbAdminUser()
{
    mysql <<EOF
CREATE USER statsadm@'%' IDENTIFIED BY '_sadm'
 REQUIRE ISSUER '/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}-db-statsadm-ca';
GRANT ALL PRIVILEGES ON *.* TO statsadm@'%' WITH GRANT OPTION;
EOF
    if [ $? -ne 0 ] ; then
        echo "ERROR: createDbAdminUser failed"
        exit 1
    fi
}

createDbPresentationUsers()
{
    if [ -z "${PRESENTATION_HOST}" ] ; then
        echo "ERROR: PRESENTATION_HOST is not defined"
        exit 1
    fi

   local PRESENTATION_HOST_IP=$(getent hosts ${PRESENTATION_HOST} | awk '{print $1}')
    if [ -z "${PRESENTATION_HOST_IP}" ] ; then
        echo "ERROR: Cannot resolve ${PRESENTATION_HOST}"
        exit 1
    fi

    mysql <<EOF
CREATE USER statsusr@${PRESENTATION_HOST_IP} IDENTIFIED BY '_susr'
 REQUIRE ISSUER '/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}-db-statsusr-ca';
CREATE USER ftpusr@${PRESENTATION_HOST_IP} IDENTIFIED BY '_ftpusr'
 REQUIRE ISSUER '/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}-db-ftpusr-ca';

GRANT SELECT ON ${STATS_DB}.* TO statsusr@${PRESENTATION_HOST_IP};
GRANT SELECT ON ${ADMIN_DB}.* TO statsusr@${PRESENTATION_HOST_IP};
GRANT SELECT, INSERT, CREATE TEMPORARY TABLES ON tmp.* TO statsusr@${PRESENTATION_HOST_IP};
GRANT INSERT ON ${ADMIN_DB}.ddp_cache TO statsusr@${PRESENTATION_HOST_IP};

GRANT SELECT ON ${ADMIN_DB}.ftpusers TO ftpusr@${PRESENTATION_HOST_IP};
EOF
    if [ $? -ne 0 ] ; then
        echo "ERROR: createDbPresentationUsers failed"
        exit 1
    fi
}

createDbProcessingUsers()
{
    if [ -z "${PROCESSING_HOST}" ] ; then
        echo "ERROR: PROCESSING_HOST is not defined"
        exit 1
    fi

    local PROCESSING_HOST_IP=$(getent hosts ${PROCESSING_HOST} | awk '{print $1}')
    if [ -z "${PROCESSING_HOST_IP}" ] ; then
        echo "ERROR: Cannot resolve ${PROCESSING_HOST}"
        exit 1
    fi

    # If the processing and presentation hosts are the same (i.e. a combined host)
    # then the user will already exist
    mysql --batch --skip-column-names mysql <<EOF > /tmp/count.txt
SELECT COUNT(*) FROM user WHERE User = 'statsadm' AND Host = '${PROCESSING_HOST_IP}';
EOF
    COUNT=$(cat /tmp/count.txt)
    if [ "${COUNT}" = "0" ] ; then
        mysql <<EOF
CREATE USER statsadm@'${PROCESSING_HOST}' IDENTIFIED BY '_sadm'
 REQUIRE ISSUER '/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}-db-statsadm-ca';
EOF
        if [ $? -ne 0 ] ; then
            echo "ERROR: createDbPresentationUsers failed"
            exit 1
        fi
    fi

    mysql <<EOF
GRANT ALL PRIVILEGES ON ${STATS_DB}.* TO statsadm@'${PROCESSING_HOST_IP}';
GRANT ALL PRIVILEGES ON ${ADMIN_DB}.* TO statsadm@'${PROCESSING_HOST_IP}';
GRANT ALL PRIVILEGES ON tmp.* TO statsadm@'${PROCESSING_HOST_IP}';
EOF
    if [ $? -ne 0 ] ; then
        echo "ERROR: createDbPresentationUsers failed"
        exit 1
    fi
}

createDbReplUser()
{
    mysql <<EOF
CREATE USER repl@'%' IDENTIFIED BY '_repl'
 REQUIRE ISSUER '/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}-db-repl-ca';
GRANT REPLICATION SLAVE ON *.* TO repl@'%';
GRANT SELECT ON ${ADMIN_DB}.ddpusers TO repl@'%';
EOF

    # We need to sign the CSR for repl
    signDbClientCer /tmp/db-client-repl.csr /tmp/db-client-repl.ext /tmp/db-client-repl.cer repl

    # If we creating the Repl User, that means we're going to need a repladm cert
    openssl req -new -key /etc/certs/db-client.key -subj "/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}-repl-repladm" -out /etc/certs/repl-client-repladm.csr
}

dropAnonDbUsers()
{
    HOSTNAME=$(hostname)
    mysql <<EOF
-- Remove the anonymous users
DROP USER ''@'localhost';
DROP USER ''@'%';
DROP USER ''@'${HOSTNAME}';
EOF
}

configPHP()
{
    PHP_INI=/etc/php.ini
    if [ ! -r ${PHP_INI}.org ] ; then
        cp ${PHP_INI} ${PHP_INI}.org
    fi

    mkdir -p /data/ddp/log/php
    chmod 777 /data/ddp/log/php

    installPkgs crudini
    if [ $? -ne 0 ] ; then
        echo "ERROR: Failed to install crudini"
        exit 1
    fi

    crudini --set ${PHP_INI} PHP short_open_tag On
    crudini --set ${PHP_INI} PHP upload_max_filesize 20M
    crudini --set ${PHP_INI} PHP error_log /data/ddp/log/php/errors.log
    crudini --set ${PHP_INI} Date date.timezone Europe/Dublin

    if [ -r /etc/php-fpm.d/www.conf ] ; then
        sed -i 's|^php_admin_value\[error_log\].*|php_admin_value[error_log] = /data/ddp/log/php/errors.log|' /etc/php-fpm.d/www.conf
    fi

    # Create '/data/ddp/env.php' config file
    PHP_ENV_FILE="/data/ddp/env.php"
    cat > ${PHP_ENV_FILE} <<'EOF'
<?php
EOF

    # Make the domainname of DDP site available under PHP
    if [ "${SERVICE_NAME}" != "${HOSTNAME}" ] ; then
        echo "\$ddp_site_domainname = '${SERVICE_NAME}.athtem.eei.ericsson.se';" >> ${PHP_ENV_FILE}
    fi

    if [ ${SELINUX} -eq 1 ] ; then
        chcon --reference /var/www ${PHP_ENV_FILE}
    fi
}

configHTTP()
{

    #
    #
    # HTTP configuration
    #
    #
    if [ ! -r /etc/httpd/conf/httpd.conf.bak ] ; then
        cp /etc/httpd/conf/httpd.conf /etc/httpd/conf/httpd.conf.bak
    fi
    # Remove the DocumentRoot from httpd.conf
    cat /etc/httpd/conf/httpd.conf.bak | sed 's/^DocumentRoot/#DocumentRoot/' > /etc/httpd/conf/httpd.conf

    export DDP_ROOT=/data/ddp/current
    cat > /etc/httpd/conf.d/stats.conf <<EOF
DirectoryIndex index.html index.php
XBitHack on

DocumentRoot /data/stats
<Directory "/data/stats">
    Options FollowSymLinks Includes
    Require all granted
</Directory>

Alias /php ${DDP_ROOT}/php
<Directory "${DDP_ROOT}/php">
    Options FollowSymLinks Includes
    Require all granted
</Directory>

Alias /plot ${DDP_ROOT}/plot/bin
<Directory "${DDP_ROOT}/plot/bin">
    Options FollowSymLinks Includes
    Require all granted
</Directory>

Alias /adminui ${DDP_ROOT}/adminui
<Directory "${DDP_ROOT}/adminui">
    Options FollowSymLinks Includes
    Require all granted
</Directory>

Alias /archive /nas/archive
<Directory "/nas/archive">
    Options FollowSymLinks Includes
    Require all granted
</Directory>

<Location "/plotsrv/">
 ProxyPass "http://localhost:8080/plot/"
 ProxyPassReverse "https://localhost:8080/plot/"
</Location>

EOF

    cat > /etc/httpd/conf.d/ddp_3pp.conf <<EOF
Alias /yui /opt/yui
<Directory /opt/yui>
    Options FollowSymLinks Includes
    Require all granted
</Directory>

Alias /highcharts /opt/highcharts
<Directory "/opt/highcharts">
    Options FollowSymLinks Includes
    Require all granted
</Directory>

Alias /jquery /opt/jquery
<Directory "/opt/jquery">
    Options FollowSymLinks Includes
    Require all granted
</Directory>

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

    if [ ${SELINUX} -eq 1 ] ; then
        semanage fcontext -a -t httpd_sys_content_t "/data/stats(/.*)?"
        restorecon -R /data/stats

        semanage fcontext -a -f l -t httpd_sys_content_t "/data/ddp/current"
        restorecon -v /data/ddp/current

        semanage fcontext -a -t httpd_sys_content_t "/data/ddp/log/php(.*)?"
        restorecon -R /data/ddp/log/php

        VERSION_DIR=$(realpath /data/ddp/current)
        chcon -R --reference /var/www ${VERSION_DIR}/php
        chcon -R --reference /var/www ${VERSION_DIR}/adminui

        setsebool -P httpd_can_network_connect_db 1
        setsebool -P httpd_can_network_connect 1
    fi

    systemctl enable httpd.service
    systemctl restart httpd.service

}

configFTP()
{
    if [ ! -r /etc/proftpd.conf.org ] ; then
        cp /etc/proftpd.conf /etc/proftpd.conf.org
    fi

    cat ${SERVER_SETUP_DIR}/proftpd.conf > /etc/proftpd.conf

    mkdir /data/ddp/log/proftpd
    chmod 644 /data/ddp/log/proftpd

    if [ ${SELINUX} -eq 1 ] ; then
        chcon --reference /var/ftp /data/ftproot
        chcon --reference /var/log /data/ddp/log/proftpd
    fi

    cat > /etc/logrotate.d/ddp_proftpd <<EOF
/data/ddp/log/proftpd/*log {
    size 10M
    rotate 10
    compress
    missingok
    notifempty
    sharedscripts
    postrotate
        systemctl reload proftpd.service
    endscript
}
EOF
    # ProFTDd getting picky about permissions
    # fatal: SFTPHostKey: unable to use '/etc/ssh/ssh_host_rsa_key' as host key,
    # as it is group- or world-accessible on line 75 of '/etc/proftpd.conf'
    chmod 600 /etc/ssh/ssh_host_rsa_key
    # Next problem, in RHEL 8, the default format for ssh_host_rsa_key is OPENSSH but ProFTPd needs RSA
    egrep --silent 'BEGIN OPENSSH PRIVATE KEY' /etc/ssh/ssh_host_rsa_key
    if [ $? -eq 0 ] ; then
        /bin/cp -p /etc/ssh/ssh_host_rsa_key /etc/ssh/ssh_host_rsa_key.proftpd
        ssh-keygen -p -N "" -m pem -f /etc/ssh/ssh_host_rsa_key.proftpd
        sed -i 's|SFTPHostKey.*|SFTPHostKey /etc/ssh/ssh_host_rsa_key.proftpd|' /etc/proftpd.conf
    fi

    systemctl enable proftpd.service
    #systemctl restart proftpd.service
}

configTomcatPlot() {
    if [ -d /var/lib/tomcat/webapps ] ; then
        local WEB_APPS_DIR=/var/lib/tomcat/webapps
        local TC_VER=7
    else
        local WEB_APPS_DIR=/usr/local/tomcat/webapps
        local TC_VER=8
    fi

    mkdir ${WEB_APPS_DIR}/plot

    mkdir ${WEB_APPS_DIR}/plot/WEB-INF
    ln -s /data/ddp/current/plot/bin ${WEB_APPS_DIR}/plot/WEB-INF/lib
    cat > ${WEB_APPS_DIR}/plot/WEB-INF/web.xml <<EOF
<web-app xmlns="http://java.sun.com/xml/ns/j2ee"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://java.sun.com/xml/ns/j2ee http://java.sun.com/xml/ns/j2ee/web-app_2_4.xsd"
    version="2.4">

    <display-name>QPlot</display-name>
    <description>
    </description>

    <servlet>
        <servlet-name>PlotServlet</servlet-name>
        <servlet-class>PlotServlet</servlet-class>
    </servlet>

    <servlet-mapping>
        <servlet-name>PlotServlet</servlet-name>
        <url-pattern>/</url-pattern>
    </servlet-mapping>

</web-app>
EOF

    mkdir ${WEB_APPS_DIR}/plot/META-INF

    if [ ${TC_VER} -eq 8 ] ; then
        cat > ${WEB_APPS_DIR}/plot/META-INF/context.xml <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<Context path="/plot">
 <Resources allowLinking="true" />
</Context>
EOF
    else
        cat > ${WEB_APPS_DIR}/plot/META-INF/context.xml <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<Context path="/plot" allowLinking="true">
</Context>
EOF
    fi

    echo 'CATALINA_OPTS="-Xmx512m"' >> /etc/tomcat/tomcat.conf

    systemctl enable tomcat.service
    systemctl restart tomcat.service
}

configCrontab()
{
   #
   # Crontab
   #
   cat >> /etc/cron.d/ddp_maint <<EOF
0 1 * * * statsadm /data/ddp/current/analysis/main/maintenance start >> /data/ddp/log/maintenance.log 2>&1
0 6 * * * statsadm /data/ddp/current/analysis/main/maintenance stop >> /data/ddp/log/maintenance.log 2>&1
EOF
}

configSudo()
{
    if [ ! -r /etc/sudoers.org ] ; then
        cp /etc/sudoers /etc/sudoers.org
    fi
    cat /etc/sudoers.org | sed 's/Defaults    requiretty/#Defaults    requiretty/' > /etc/sudoers

    cat - >> /etc/sudoers.d/ddp <<EOF
statsadm ALL = (root) NOPASSWD: /usr/bin/systemctl
apache ALL = (statsadm) NOPASSWD: /data/ddp/current/sitemgt/siteMgt
apache ALL = (statsadm) NOPASSWD: /data/ddp/current/server_setup/upgrade
apache ALL = (statsadm) NOPASSWD: /data/ddp/current/server_setup/checkStatsDBAutoIdSize
apache ALL = (root) NOPASSWD: /data/ddp/current/server_setup/genericPhpToRootWrapper
statsadm ALL = (mysql) NOPASSWD: /usr/bin/mysqlhotcopy
statsadm ALL = (root) NOPASSWD: /data/ddp/current/server_setup/snapdbfs
statsadm ALL = (root) NOPASSWD: /data/ddp/current/server_setup/ddc_plugin.sh
EOF
}
configDDPD()
{
    cat > /lib/systemd/system/ddpd.service <<EOF
[Unit]
Description=DDP
After=data.mount
Requires=data.mount

[Service]
Type=forking
PIDFile=/tmp/ddpd.pid
ExecStart=/data/ddp/current/server_setup/ddpd_sol start
ExecStop=/data/ddp/current/server_setup/ddpd_sol stop
TimeoutSec=5400

[Install]
WantedBy=multi-user.target
EOF
    systemctl enable ddpd.service
}

configSerialConsole() {
    if [ ! -r /root/grub.org ] ; then
        cp /etc/default/grub /root/grub.org
    fi
    GRUB_CMDLINE_LINUX=$(egrep '^GRUB_CMDLINE_LINUX=' /root/grub.org | sed 's/^GRUB_CMDLINE_LINUX=//')
    GRUB_CMDLINE_LINUX=$(echo "${GRUB_CMDLINE_LINUX}" | sed -e 's/ quiet//' -e 's/ rhgb//' -e 's/ console=[^ "]*//' -e 's/"$/ quiet console=ttyS1,115200n8"/')

    cat /root/grub.org | egrep -v '^GRUB_TERMINAL|^GRUB_SERIAL_COMMAND|^GRUB_CMDLINE_LINUX' > /etc/default/grub
    cat >> /etc/default/grub <<EOF
GRUB_CMDLINE_LINUX=${GRUB_CMDLINE_LINUX}
GRUB_TERMINAL="serial console"
GRUB_SERIAL_COMMAND="serial --speed=115200 --unit=1 --word=8 --parity=no --stop=1"
EOF

    grub2-mkconfig -o /boot/grub2/grub.cfg

    echo "ttyS1" >> /etc/securetty
}

configDdpSite() {
    mysql statsdb -e "INSERT INTO sites (name,site_type) VALUES ( 'DDP', 'DDP' )"
    SITE_ID=$(mysql statsdb --batch --skip-column-names -e "SELECT id FROM sites WHERE name = 'DDP'")
    mysql ddpadmin -e "INSERT INTO ftpusers (siteid,userid,passwd,homedir) VALUES ( ${SITE_ID},'ddp','\N','/data/ftproot/ddp')"
    mkdir /data/ftproot/ddp /data/stats/ddp/DDP /data/stats/ddp/DDP/data /data/stats/ddp/DDP/analysis
    chown statsadm:statsadm /data/ftproot/ddp /data/stats/ddp/DDP /data/stats/ddp/DDP/data /data/stats/ddp/DDP/analysis

    mkdir -p /var/tmp/ddc_data/config
    echo "ddp" > /var/tmp/ddc_data/config/ddp.txt

    su - statsadm -c "/data/ddp/current/sitemgt/siteMgt -a -s DDP -t ddp -f ddp -c /data/stats/config"
    crontab -l > /root/crontab
    echo "30 0 * * * find /var/tmp/ddc_data -type f -name 'DDC_Data_*.tar.gz' -cmin +30 -exec mv {} /data/ftproot/ddp \;" >> /root/crontab
    crontab < /root/crontab


}

configSftpUpload() {
    mkdir /data/ftproot/upload
    chown statsadm:statsuser /data/ftproot/upload
    chmod 775 /data/ftproot/upload

    local ENCRYPTED_PASSWD=$(echo -n '_!upLoad' | openssl dgst -binary -md5 | openssl enc -base64)
    local PASSWD_STR="{md5}${ENCRYPTED_PASSWD}"
    mysql ddpadmin <<EOF
INSERT INTO ftpusers (siteid,userid,passwd,homedir) VALUES ( 65535,'upload','${PASSWD_STR}','/data/ftproot/upload');
EOF
    echo '* * * * * statsadm /data/ddp/current/analysis/main/sftpUploads /data/stats/config /data/ftproot/upload >> /data/ddp/log/sftpUploads.log 2>&1' > /etc/cron.d/ddp_sftp_uploads
}

configADC() {
    if [ -z "${ADC_PASSWORD}" ] ; then
        echo "ERROR: ADC_PASSWORD must be set"
        exit 1
    fi

    mkdir /data/ftproot/adc
    chown statsadm:statsuser /data/ftproot/adc
    chmod 775 /data/ftproot/adc

    mysql ddpadmin <<EOF
INSERT INTO ftpusers (siteid,userid,passwd,homedir) VALUES ( 65534,'adc',PASSWORD('${ADC_PASSWORD}','/data/ftproot/adc');
EOF

    echo "*/10 * * * * root /data/ddp/current/analysis/main/adc /data/stats/config /data/ftproot/adc >> /data/ddp/log/adc.log 2>&1" > /etc/cron.d/adc
}

configNFS() {
    # Disable mounts from eei as reverse DNS lookups aren't working
    # *.eei.ericsson.se(ro)
    echo "/data/stats *.athtem.eei.ericsson.se(ro,insecure)" >> /etc/exports

    installPkgs nfs-utils

    # Fix for NFS hanging issue
    cat > /etc/sysctl.d/98-ddp.conf <<EOF
kernel.panic_on_io_nmi = 1
kernel.panic_on_unrecovered_nmi = 1
kernel.unknown_nmi_panic = 1
fs.leases-enable = 0
EOF

    NFS_SERVICE_LIST="rpcbind nfs-server nfs-idmap"
    for NFS_SERVICE in ${NFS_SERVICE_LIST} ; do
        echo "Enabling ${NFS_SERVICE}"
        systemctl enable ${NFS_SERVICE}
    done
    for NFS_SERVICE in ${NFS_SERVICE_LIST} ; do
        echo "Starting ${NFS_SERVICE}"
        systemctl start ${NFS_SERVICE}
    done

}

configGPGKey() {
    EXPIRE_DATE=$(date --iso-8601 --date="now + 3 years" )
    cat > /tmp/genkey.cfg <<EOF
%echo Generating a basic OpenPGP key
Key-Type: RSA
Key-Length: 2048
Key-Usage: encrypt
Name-Real: BDGS SA OSS PDU NM Release & Supp Mgmt
Name-Comment: Encryption key for DDP tool-related data collections
Name-Email: michael.finan@ericsson.com
Expire-Date: ${EXPIRE_DATE}
%no-protection
%commit
%echo done
EOF
    gpg --gen-key --batch /tmp/genkey.cfg

    gpg --export --armor --output /root/ddpupload.pubkey.asc
    gpg --export-secret-keys --armor --output /root/ddpupload.privkey.asc

    #gpg --import /root/ddpupload.pubkey.asc
    #gpg --import /root/ddpupload.privkey.asc
}

configLDAP() {
    CA_FILE="/etc/openldap/symantec_ca_g4.cer"
    cat > ${CA_FILE} <<EOF
-----BEGIN CERTIFICATE-----
MIIFODCCBCCgAwIBAgIQUT+5dDhwtzRAQY0wkwaZ/zANBgkqhkiG9w0BAQsFADCB
yjELMAkGA1UEBhMCVVMxFzAVBgNVBAoTDlZlcmlTaWduLCBJbmMuMR8wHQYDVQQL
ExZWZXJpU2lnbiBUcnVzdCBOZXR3b3JrMTowOAYDVQQLEzEoYykgMjAwNiBWZXJp
U2lnbiwgSW5jLiAtIEZvciBhdXRob3JpemVkIHVzZSBvbmx5MUUwQwYDVQQDEzxW
ZXJpU2lnbiBDbGFzcyAzIFB1YmxpYyBQcmltYXJ5IENlcnRpZmljYXRpb24gQXV0
aG9yaXR5IC0gRzUwHhcNMTMxMDMxMDAwMDAwWhcNMjMxMDMwMjM1OTU5WjB+MQsw
CQYDVQQGEwJVUzEdMBsGA1UEChMUU3ltYW50ZWMgQ29ycG9yYXRpb24xHzAdBgNV
BAsTFlN5bWFudGVjIFRydXN0IE5ldHdvcmsxLzAtBgNVBAMTJlN5bWFudGVjIENs
YXNzIDMgU2VjdXJlIFNlcnZlciBDQSAtIEc0MIIBIjANBgkqhkiG9w0BAQEFAAOC
AQ8AMIIBCgKCAQEAstgFyhx0LbUXVjnFSlIJluhL2AzxaJ+aQihiw6UwU35VEYJb
A3oNL+F5BMm0lncZgQGUWfm893qZJ4Itt4PdWid/sgN6nFMl6UgfRk/InSn4vnlW
9vf92Tpo2otLgjNBEsPIPMzWlnqEIRoiBAMnF4scaGGTDw5RgDMdtLXO637QYqzu
s3sBdO9pNevK1T2p7peYyo2qRA4lmUoVlqTObQJUHypqJuIGOmNIrLRM0XWTUP8T
L9ba4cYY9Z/JJV3zADreJk20KQnNDz0jbxZKgRb78oMQw7jW2FUyPfG9D72MUpVK
Fpd6UiFjdS8W+cRmvvW1Cdj/JwDNRHxvSz+w9wIDAQABo4IBYzCCAV8wEgYDVR0T
AQH/BAgwBgEB/wIBADAwBgNVHR8EKTAnMCWgI6Ahhh9odHRwOi8vczEuc3ltY2Iu
Y29tL3BjYTMtZzUuY3JsMA4GA1UdDwEB/wQEAwIBBjAvBggrBgEFBQcBAQQjMCEw
HwYIKwYBBQUHMAGGE2h0dHA6Ly9zMi5zeW1jYi5jb20wawYDVR0gBGQwYjBgBgpg
hkgBhvhFAQc2MFIwJgYIKwYBBQUHAgEWGmh0dHA6Ly93d3cuc3ltYXV0aC5jb20v
Y3BzMCgGCCsGAQUFBwICMBwaGmh0dHA6Ly93d3cuc3ltYXV0aC5jb20vcnBhMCkG
A1UdEQQiMCCkHjAcMRowGAYDVQQDExFTeW1hbnRlY1BLSS0xLTUzNDAdBgNVHQ4E
FgQUX2DPYZBV34RDFIpgKrL1evRDGO8wHwYDVR0jBBgwFoAUf9Nlp8Ld7LvwMAnz
Qzn6Aq8zMTMwDQYJKoZIhvcNAQELBQADggEBAF6UVkndji1l9cE2UbYD49qecxny
H1mrWH5sJgUs+oHXXCMXIiw3k/eG7IXmsKP9H+IyqEVv4dn7ua/ScKAyQmW/hP4W
Ko8/xabWo5N9Q+l0IZE1KPRj6S7t9/Vcf0uatSDpCr3gRRAMFJSaXaXjS5HoJJtG
QGX0InLNmfiIEfXzf+YzguaoxX7+0AjiJVgIcWjmzaLmFN5OUiQt/eV5E1PnXi8t
TRttQBVSK/eHiXgSgW7ZTaoteNTCLD0IX4eRnh8OsN4wUmSGiaqdZpwOdgyA8nTY
Kvi4Os7X1g8RvmurFPW9QaAiY4nxug9vKWNmLT+sjHLF+8fk1A/yO0+MKcc=
-----END CERTIFICATE-----
EOF
    if [ ! -r /etc/openldap/ldap.conf.sav ] ; then
        cp /etc/openldap/ldap.conf /etc/openldap/ldap.conf.sav
    fi
    cat /etc/openldap/ldap.conf.sav | grep -v TLS_CACERT > /etc/openldap/ldap.conf
    echo "TLS_CACERT ${CA_FILE}" >> /etc/openldap/ldap.conf

    systemctl restart httpd
}

configLdapPasswords() {
    PW1=$(echo "${LDAP_USER}" | awk '{print $1}')
    PW2=$(echo "${LDAP_PASSWORD}" | awk '{print $2}')
    cat > /etc/httpd/conf.d/ldap_creds.conf <<EOF
SetEnv DDP_LDAP_USERID "${LDAP_USER}"
SetEnv DDP_LDAP_PASSWD "${LDAP_PASSWORD}"
EOF
}

generateCA() {
    if [ ! -r /root/cert/${SERVICE_NAME}_ca.cer ] ; then
        openssl req -new -days 1095 \
                -key /root/cert/${SERVICE_NAME}.key \
                -subj "/C=IE/O=Ericsson/OU=DDP/CN=${SERVICE_NAME}" \
                -out /root/cert/${SERVICE_NAME}_ca.csr
        if [ $? -ne 0 ] ; then
            echo "ERROR: Failed to generate CSR for CA"
            exit 1
        fi

        cat > /root/cert/${SERVICE_NAME}_ca.ext <<EOF
basicConstraints=critical,CA:true
keyUsage=critical,keyCertSign,cRLSign
subjectKeyIdentifier=hash
EOF
        openssl x509 -req -set_serial 1 -days 1095 \
                -in /root/cert/${SERVICE_NAME}_ca.csr \
                -extfile /root/cert/${SERVICE_NAME}_ca.ext \
                -signkey /root/cert/${SERVICE_NAME}.key \
                -out /root/cert/${SERVICE_NAME}_ca.cer
    fi
}

generateCSR() {
    if [ ! -d /root/cert ] ; then
        mkdir /root/cert
    fi

    cat > /root/cert/${SERVICE_NAME}-https.csr_conf <<EOF
[ req ]
default_bits       = 2048
distinguished_name = req_distinguished_name
req_extensions     = req_ext
prompt = no

[ req_distinguished_name ]
countryName = SE
stateOrProvinceName = Stockholm
localityName = Stockholm
organizationName = Ericsson AB
OU = IT
commonName = ${SERVICE_NAME}.athtem.eei.ericsson.se

[ req_ext ]
subjectAltName = DNS: ${SERVICE_NAME}.athtem.eei.ericsson.se
EOF

    # Certificate services won't allow key to be re-used - some vague comment
    # about "IT security guidelines"
    openssl req -nodes -newkey rsa:2048 -keyout /root/cert/${SERVICE_NAME}-https.key -out /root/cert/${SERVICE_NAME}-https.csr -config /root/cert/${SERVICE_NAME}-https.csr_conf
    cat /root/cert/${SERVICE_NAME}-https.csr
}

generateSelfSignedCerts() {
    mkdir /root/cert
    openssl req -x509 -newkey rsa:2048 -keyout /root/cert/${SERVICE_NAME}-https.key -nodes -out /root/cert/${SERVICE_NAME}-https.cer -subj "/C=SE/ST=Stockholm/L=Stockholm/O=Ericsson/OU=IT/CN=${SERVICE_NAME}.athtem.eei.ericsson.se"
}

configWSGI() {

    cat > /etc/httpd/conf.d/ddp_modelledui.conf <<EOF
Listen 127.0.0.1:8888
SetEnv MODELLED_UI_ENDPOINT "http://127.0.0.1:8888"

<VirtualHost 127.0.0.1:8888>
    ServerName localhost
     WSGIDaemonProcess ddp user=apache group=apache threads=2
     WSGIScriptAlias / /data/ddp/current/modelledui/uiapp/app.wsgi

     <Directory /data/ddp/current/modelledui/uiapp>
         Options FollowSymLinks
         Require all granted
     </Directory>
</VirtualHost>

EOF

}

configExternalACME() {
    dnf install -y --quiet certbot

    # Disable HTTP on Port 80 if required
    egrep --silent '^Listen 80$' /etc/httpd/conf/httpd.conf
    if [ $? -eq 0 ] ; then
        # HTTP is used by the static image plot when fetching data
        echo "INFO: HTTP on localhost only"
        sed -i 's/^Listen 80/Listen 127.0.0.1:80/' /etc/httpd/conf/httpd.conf
        systemctl restart httpd
    fi

    curl --silent http://pki.ericsson.se/CertData/EGADIssuingCA3.crt -o /etc/pki/ca-trust/source/anchors/EGADIssuingCA3.crt
    curl --silent http://pki.ericsson.se/CertData/EGADRootCA.crt -o /etc/pki/ca-trust/source/anchors/EGADRootCA.crt
    update-ca-trust

    IP=$(getent ahosts ${SERVICE_NAME}.athtem.eei.ericsson.se | grep STREAM | grep -v : | awk '{print $1}')

    if [ ! -r /etc/letsencrypt/live/${SERVICE_NAME}.athtem.eei.ericsson.se/fullchain.pem ] ; then
        certbot certonly --non-interactive --quiet --standalone --http-01-address ${IP} -d ${SERVICE_NAME}.athtem.eei.ericsson.se --key-type rsa --agree-tos -m conor.murphy@ericsson.com --server https://clm-api.internal.ericsson.com/VAcme/v2/ddp/directory/
        if [ $? -ne 0 ] ; then
            echo "ERROR: Failed to get cert"
            exit 1
        fi
    fi

    cat > /etc/cron.d/ddp_certbot <<EOF
10 04 * * 0 root bash /data/ddp/current/server_setup/renew_cert.sh
EOF


    cat > /etc/httpd/conf.d/ddp_https.conf <<EOF
LoadModule ssl_module modules/mod_ssl.so

<VirtualHost ${IP}:443>
    ServerName ${SERVICE_NAME}.athtem.eei.ericsson.se
    SSLEngine on
    SSLCertificateFile "/etc/letsencrypt/live/${SERVICE_NAME}.athtem.eei.ericsson.se/fullchain.pem"
    SSLCertificateKeyFile "/etc/letsencrypt/live/${SERVICE_NAME}.athtem.eei.ericsson.se/privkey.pem"
</VirtualHost>
EOF
    systemctl restart httpd
}

configHTTPS() {
    if [ -z "${SERVICE_NAME}" ] ; then
        echo "ERROR: SERVICE_NAME must be defined"
        exit 1
    fi

    if [ ! -r /root/cert/${SERVICE_NAME}-https.key ] || [ ! -r /root/cert/${SERVICE_NAME}-https.cer ] ; then
        echo "ERROR: Cert files missing"
        exit 1
    fi

    if [ ! -r /usr/lib64/httpd/modules/mod_ssl.so ] ; then
        installPkgs mod_ssl openssl
    fi

    if [ ! -d /etc/httpd/sslcert ] ; then
        mkdir /etc/httpd/sslcert
    fi
    cp /root/cert/${SERVICE_NAME}-https.key /etc/httpd/sslcert
    cp /root/cert/${SERVICE_NAME}-https.cer /etc/httpd/sslcert

    IP=$(getent ahosts ${SERVICE_NAME}.athtem.eei.ericsson.se | grep STREAM | grep -v : | awk '{print $1}')

    cat > /etc/httpd/conf.d/ddp_https.conf <<EOF
LoadModule ssl_module modules/mod_ssl.so

<VirtualHost ${IP}:443>
    ServerName ${SERVICE_NAME}.athtem.eei.ericsson.se
    SSLEngine on
    SSLCertificateFile "/etc/httpd/sslcert/${SERVICE_NAME}-https.cer"
    SSLCertificateKeyFile "/etc/httpd/sslcert/${SERVICE_NAME}-https.key"
</VirtualHost>

EOF

    if [ -r /root/cert/${SERVICE_NAME}_ca.cer ] ; then
        IP_PRIV=$(getent hosts ${SERVICE_NAME}-priv | awk '{print $1}')
        cp /root/cert/${SERVICE_NAME}_ca.cer /etc/httpd/sslcert
        cat >> /etc/httpd/conf.d/ddp_https-priv.conf <<EOF

<VirtualHost ${IP_PRIV}:443>
    ServerName ${SERVICE_NAME}-priv.athtem.eei.ericsson.se
    SSLEngine on
    SSLCertificateFile "/etc/httpd/sslcert/${SERVICE_NAME}.cer"
    SSLCertificateKeyFile "/etc/httpd/sslcert/${SERVICE_NAME}.key"

    SSLVerifyClient require
    SSLVerifyDepth 1
    SSLOptions +StdEnvVars
    SSLCACertificateFile "/etc/httpd/sslcert/${SERVICE_NAME}_ca.cer"
</VirtualHost>
EOF
    fi
    systemctl restart httpd

    # Config HTTPS for tomcat
    if [ -r /root/cert/${SERVICE_NAME}.p12 ] ; then
        /bin/rm /root/cert/${SERVICE_NAME}.p12
    fi

    openssl pkcs12 -export -in /root/cert/${SERVICE_NAME}-https.cer -inkey /root/cert/${SERVICE_NAME}-https.key -out /root/cert/${SERVICE_NAME}-https.p12 -name tomcat -password pass:""
    if [ $? -ne 0 ] ; then
        echo "ERROR: Failed to generate p12 file"
        exit 1
    fi


    if [ -d /usr/share/tomcat ] ; then
        KEYSTORE_FILE=/usr/share/tomcat/.keystore
        TRUSTSTORE_FILE=/usr/share/tomcat/.truststore
        CONF_DIR=/etc/tomcat
        TC_VER=7
    else
        KEYSTORE_FILE=/usr/local/tomcat/.keystore
        TRUSTSTORE_FILE=/usr/local/tomcat/.truststore
        CONF_DIR=/usr/local/tomcat/conf
        TC_VER=8
    fi
    keytool -v -importkeystore -srckeystore /root/cert/${SERVICE_NAME}-https.p12 -srcstoretype PKCS12 -srcstorepass "" -destkeystore ${KEYSTORE_FILE} -deststoretype JKS -storepass changeit -destkeypass changeit -alias tomcat -noprompt
    if [ $? -ne 0 ] ; then
        echo "ERROR: Failed to import p12 to tomcat keystore"
        exit 1
    fi

    # Uncomment SSL Connector
    if [ ! -r ${CONF_DIR}/server.xml.org ] ; then
        cp ${CONF_DIR}/server.xml ${CONF_DIR}/server.xml.org
    fi

    if [ ${TC_VER} -eq 8 ] ; then
        cat ${CONF_DIR}/server.xml.org | \
            sed 's/<!--/\x0<!--/g;s/-->/-->\x0/g' | grep -zv '^<!--' | tr -d '\0' | grep -v "^\s*$" | \
            sed 's|"Catalina">|"Catalina">\n    <Connector port="8443" protocol="org.apache.coyote.http11.Http11NioProtocol"\n               maxThreads="15" SSLEnabled="true" scheme="https" secure="true"\n               clientAuth="false" sslProtocol="TLS"\n               keystoreFile="KEYSTORE_FILE" keystorePass="changeit" />|' > ${CONF_DIR}/server.xml
        sed -i "s|KEYSTORE_FILE|${KEYSTORE_FILE}|" ${CONF_DIR}/server.xml
    else
        cat /etc/tomcat/server.xml.org | \
            sed 's/<!--/\x0<!--/g;s/-->/-->\x0/g' | grep -zv '^<!--' | tr -d '\0' | grep -v "^\s*$" | \
            sed 's|"Catalina">|"Catalina">\n    <Connector port="8443" protocol="org.apache.coyote.http11.Http11Protocol"\n               maxThreads="15" SSLEnabled="true" scheme="https" secure="true"\n               clientAuth="false" sslProtocol="TLS" />|' > /etc/tomcat/server.xml
    fi

    # Need to configure truststore so that when tomcat connects to qplot, it trusts the cert
    keytool -delete -alias mykey -noprompt -keystore ${TRUSTSTORE_FILE} -storepass "changeit"
    keytool -import -file /root/cert/${SERVICE_NAME}-https.cer -noprompt -keystore ${TRUSTSTORE_FILE} -storepass "changeit"
    cat >> /etc/tomcat/tomcat.conf <<EOF
JAVA_OPTS="-Djavax.net.ssl.trustStore=${TRUSTSTORE_FILE} -Djavax.net.ssl.trustStorePassword=changeit"
EOF

    systemctl restart tomcat
}

configSshKeyForCurrentUser() {
    HOME_DIR=$(eval echo "~/")
    if [ ! -r ${HOME_DIR}/.ssh/id_rsa.pub ] ; then
        if [ ! -d ${HOME_DIR}/.ssh ] ; then
            mkdir ${HOME_DIR}/.ssh
            chmod 700 ${HOME_DIR}/.ssh
        fi

        ssh-keygen -q -t rsa -N "" -f ${HOME_DIR}/.ssh/id_rsa
        chmod 600 ${HOME_DIR}/.ssh/id_rsa
    fi

    ADD_KEY=1
    if [ -r ${HOME_DIR}/.ssh/authorized_keys ] ; then
        KEY=$(cat ${HOME_DIR}/.ssh/id_rsa.pub | awk '{print $2}')
        grep --silent "${KEY}" ${HOME_DIR}/.ssh/authorized_keys
        if [ $? -eq 0 ] ; then
            ADD_KEY=0
        fi
    fi
    if [ ${ADD_KEY} -eq 1  ] ; then
        cat ${HOME_DIR}/.ssh/id_rsa.pub >> ${HOME_DIR}/.ssh/authorized_keys
        chmod 600 ${HOME_DIR}/.ssh/authorized_keys
    fi
}

configSshKeys() {
    for USER in root statsadm ; do
        su - ${USER} -c "$0 -t configSshKeyForCurrentUser"
    done
}

configRepl() {
    echo "Add following to /root/.ssh/authorized_keys on ddprepl"
    cat /root/.ssh/id_rsa.pub

    read -p "Press [Enter] when the authorized_keys has been updated"

    expect <<EOF
spawn ssh root@ddprepl "echo test"
expect {
 ")?" {
   send "yes\r"
   exp_continue
  }
 eof {}
}
EOF
    PORT=$(ssh -n root@ddprepl "egrep '^port=' /etc/my_repl_${SERVICE_NAME}.cnf" | tail -1 | awk -F= '{print $2}')
    if [ -z "${PORT}" ] ; then
        echo "ERROR: Could not get repl port"
        exit 1
    fi

    mysql ddpadmin -e "TRUNCATE TABLE db_replicas"
    mysql ddpadmin -e "INSERT INTO db_replicas (host,port,dir,smf) VALUES ('ddprepl-priv',${PORT},'rsync:repl_${SERVICE_NAME}','repl_${SERVICE_NAME}')"

    installPkgs rsync
    /data/ddp/current/server_setup/updateRepl

    cat > /etc/cron.d/ddp_updateRepl <<EOF
0 0 * * * root /data/ddp/current/server_setup/updateRepl >> /data/ddp/log/updaterepl.log 2>&1
EOF
}

configMTA() {
    yum remove -q -y sendmail > /dev/null 2>&1
    installPkgs postfix

    if [ ! -r /etc/postfix/main.cf.org ] ; then
        cp -p /etc/postfix/main.cf /etc/postfix/main.cf.org
    fi
    cat /etc/postfix/main.cf.org | egrep -v '^myhostname|^myorigin|^relayhost|^inet_interfaces|^mydestination' > /etc/postfix/main.cf
    FQDN=$(hostname -f)
    cat >> /etc/postfix/main.cf <<EOF
myhostname = ${FQDN}
relayhost = [192.168.255.2]
inet_interfaces = loopback-only
EOF

    rhelVer
    if [ ${RHEL_VER} -ne 7 ] ; then
        echo 'smtpd_relay_restrictions = permit_mynetworks, reject' >> /etc/postfix/main.cf
    fi

    systemctl restart postfix
    systemctl enable postfix
}

configDDPLogRotate() {
    mkdir /data/ddp/log/old
    chown statsadm:statsadm /data/ddp/log/old
    cat > /etc/logrotate.d/ddp_logs <<EOF
# Leave errors.log rotate in it's own directory
/data/ddp/log/php/errors.log {
 monthly
 rotate 12
 compress
 missingok
 notifempty
 create 644 apache apache
 su apache apache
}

# Normally perf.log is managed by DDC
# so this rule shouldn't fire
/data/ddp/log/perf.log {
 rotate 2
 size 100M
 compress
 missingok
 create 644 apache apache
 su apache apache
 olddir /data/ddp/log/old
}

# High volume logs written to by statsadm
/data/ddp/log/sftpUploads.log /data/ddp/log/maintenance.log {
 monthly
 rotate 12
 compress
 missingok
 notifempty
 create 644 statsadm statsadm
 su statsadm statsadm
 olddir /data/ddp/log/old
}

# Low volume logs written to by statsadm
/data/ddp/log/archive.log /data/ddp/log/updaterepl.log {
 yearly
 rotate 5
 compress
 missingok
 notifempty
 create 644 statsadm statsadm
 su statsadm statsadm
 olddir /data/ddp/log/old
}

# We want to keep sitemgt
# needs to be world writable
/data/ddp/log/sitemgt.log {
 yearly
 rotate 5
 compress
 missingok
 notifempty
 create 666 statsadm statsadm
 su statsadm statsadm
 olddir /data/ddp/log/old
}

# Root written logs
/data/ddp/log/ddpd.log.bak /data/ddp/log/adc.log {
 monthly
 rotate 12
 compress
 copytruncate
 missingok
 notifempty
 create 644 root root
 su root root
 olddir /data/ddp/log/old
}
EOF

    # Make journalctl persistent
    sed -i 's/#Storage=auto/Storage=persistent/' /etc/systemd/journald.conf
    systemctl restart systemd-journald.service
}

configDbHost() {
    if [ -z "${DBHOST_IP}" ] ; then
        echo "ERROR: DBHOST_IP must be set"
        exit 1
    fi

    grep --silent -w dbhost /etc/hosts
    if [ $? -eq 0 ] ; then
        return
    fi

    egrep --silent "^${DBHOST_IP} " /etc/hosts
    if [ $? -eq 0 ] ; then
        sed -i "s/^${DBHOST_IP} \(.*\)/${DBHOST_IP} \1 dbhost/" /etc/hosts
    else
        echo "${DBHOST_IP} dbhost" >> /etc/hosts
    fi

    ssh-keyscan -H dbhost >> ~/.ssh/known_hosts

    cat > /etc/my.cnf.d/client.cnf <<EOF
[client]
host=dbhost
user=statsadm
password=_sadm
ssl_cert = /etc/certs/db-client-statsadm.cer
ssl_key = /etc/certs/db-client.key
ssl_ca = /etc/certs/db-srv-ca.cer
ssl-verify-server-cert
EOF
}


configDDPHealth() {
    if [ -z "${SERVICE_NAME}" ] ; then
        echo "ERROR: SERVICE_NAME must be defined"
        exit 1
    fi

    cat > /etc/cron.d/ddp_health <<EOF
0 * * * * root /data/ddp/current/server_setup/DDPHealth.pl --server "${SERVICE_NAME}"
EOF
}

#export http_proxy=http://www-proxy.ericsson.se:8080/

export STATS_DB="statsdb"
export ADMIN_DB="ddpadmin"
export DDP_3PP="192.168.255.253:/infra"

SELINUX=0

while getopts  "d:t:s:i:n:u:p:h:e:l" flag
do
    case "$flag" in
        s) SERVICE_NAME=${OPTARG};;
        t) TASK=${OPTARG};;
        d) DDP_SW=${OPTARG};;
        i) IP_PRIV=${OPTARG};;
        n) NIC_PRIV=${OPTARG};;
        u) LDAP_USER="${OPTARG}";;
        p) LDAP_PASSWORD="${OPTARG}";;
        h) DBHOST_IP="${OPTARG}";;
        e) SELINUX=1;;
    esac
done

if [ "${TASK}" = "install" ] ; then
    export http_proxy=http://atproxy1.athtem.eei.ericsson.se:3128/

    if [ -z "${DDP_SW}" ] ; then
        echo "ERROR: You must provide DDP tar file for install"
        exit 1
    fi

    PROCESSING_HOST=localhost
    PRESENTATION_HOST=localhost

        TASK_LIST="
    configNTP
    addUsers
    createFS
    mountFS
    createDirs
    install3PP
    extractDDP
    installDDP
    createDbSrvCerts
    configMySQL
    createDb
    loadDbSchema
    createDbCronJobs
    dropAnonDbUsers
    createDbClientCerts
    createDbPresentationUsers localhost
    createDbProcessingUsers localhost
    configPHP
    configHTTP
    configWSGI
    configTomcatPlot
    configFTP
    configSudo
    configDDPD
    configCrontab
    configDDPLogRotate
    configDdpSite
    configNFS
    configMTA
    configLDAP"
elif [ "${TASK}" = "installpres" ] ; then
    if [ -z "${DDP_SW}" ] ; then
        echo "ERROR: You must provide DDP tar file for install"
        exit 1
    fi
    if [ -z "${LDAP_USER}" ] || [ -z "${LDAP_PASSWORD}" ] ; then
        echo "ERROR: You must provide LDAP creds"
        exit 1
    fi

    LVG=vg_data
    FS_LIST="data:100%FREE:"

    TASK_LIST="
    configYUM
    configNTP
    addUsers
    createFS
    mountFS
    createDirs
    installPresentation3PP
    extractDDP
    installDDP
    configPHP
    configHTTP
    configWSGI
    configTomcatPlot
    configFTP
    configSudo
    configDDPD
    configCrontab
    configDDPLogRotate
    configNFS
    configMTA
    configLdapPasswords
    configSshKeys"
elif [ "${TASK}" = "installdb" ] ; then
    LVG=vg_db
    SIZE_GB=$(vgs --noheadings -o vg_free --units g --nosuffix vg_db | awk '{print $1}' | sed 's/\..*//g' | awk '{printf "%d", $1*0.8}')
    export FS_LIST="db:${SIZE_GB}g:/data"
    PRESENTATION_HOST=${IP_PRIV}
    TASK_LIST="
    installDb3PP
    createFS
    mountFS
    createDbSrvCerts
    configMySQL
    createDb
    loadDbSchema
    createDbCronJobs
    dropAnonDbUsers
    createDbAdminUser
    createDbPresentationUsers"
elif [ ! -z "${TASK}" ] ; then
    TASK_LIST=${TASK}
fi

for ONE_TASK in ${TASK_LIST} ; do
    DATE=$(date)
    echo ">>>>>>>> ${DATE} Performing task ${ONE_TASK}"
    ${ONE_TASK}
    echo "<<<<<<<<"
    echo ""
done
