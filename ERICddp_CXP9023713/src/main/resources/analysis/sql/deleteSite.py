import mysql.connector
from mysql.connector.constants import ClientFlag
import pprint
import logging
from datetime import timedelta
from datetime import datetime
import time
import argparse

def get_tables(cnx):
    all_tables = []
    cursor = cnx.cursor()
    cursor.execute("SELECT TABLE_NAME AS tbl_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()")
    for row in cursor:
        all_tables.append(row[0])
    cursor.close()

    siteid_tables = []
    cursor = cnx.cursor()
    cursor.execute("SELECT TABLE_NAME AS tbl_name FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'siteid'")
    for row in cursor:
        siteid_tables.append(row[0])
        all_tables.remove(row[0])
    cursor.close()

    autoid_tables = []
    cursor = cnx.cursor()
    cursor.execute("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND EXTRA LIKE '%auto_increment%'")
    for row in cursor:
        if row[0] not in siteid_tables:
            autoid_tables.append(row[0])
            all_tables.remove(row[0])
    cursor.close()

    logging.debug("siteid_tables=%s", siteid_tables)
    logging.debug("autoid_tables=%s", autoid_tables)
    logging.debug("all_tables=%s", all_tables)

    return siteid_tables

def get_siteids(cnx, site_names):
    site_ids = []
    for site_name in site_names:
        cursor = cnx.cursor()
        cursor.execute("SELECT id FROM sites WHERE name = %(site)s", { 'site': site_name, })
        row = cursor.fetchone()
        cursor.close()
        site_ids.append(str(row[0]))

    logging.debug("get_sitesids: site_ids=%s", site_ids)
    return site_ids

parser = argparse.ArgumentParser()
parser.add_argument('--sites', nargs='+', required=True)
parser.add_argument('--delete', default=False, required=False, action='store_true')
parser.add_argument('--verbose', default=False, required=False, action='store_true')

args = parser.parse_args()

level = logging.INFO
if args.verbose:
    level=logging.DEBUG
logging.basicConfig(
    format='%(asctime)s %(levelname)s %(message)s',
    level=level
)

config = {
    "user": 'statsadm',
    "password": '_sadm',
    "host": 'dbhost',
    "database": "statsdb",
    "client_flags": [ClientFlag.SSL],
    "ssl_cert": "/etc/certs/db-client-statsadm.cer",
    "ssl_key": "/etc/certs/db-client.key",
    "ssl_ca": "/etc/certs/db-srv-ca.cer"
}
cnx = mysql.connector.connect(**config)

siteid_tables = get_tables(cnx)
site_ids = get_siteids(cnx, args.sites)
for table in siteid_tables:
    sql = "DELETE FROM {0} WHERE siteid  IN ({1})".format(table, ",".join(site_ids))
    logging.debug("Delete query=%s", sql)

    if args.delete:
        d1 = datetime.now()
        cursor = cnx.cursor()
        cursor.execute(sql)
        cnx.commit()
        cursor.close()
        d2 = datetime.now()

        delta = d2 - d1
        duration = delta.total_seconds()

        logging.info("Deleted from %s, rows=%d duration=%s", table, cursor.rowcount, duration)



cnx.close()
