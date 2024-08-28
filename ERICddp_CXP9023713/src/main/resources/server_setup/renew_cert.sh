#!/bin/bash

/usr/bin/certbot renew > /var/log/renew.log 2>&1

egrep --silent 'No renewals were attempted' /var/log/renew.log
if [ $? -ne 0 ] ; then
    systemctl restart httpd
fi

