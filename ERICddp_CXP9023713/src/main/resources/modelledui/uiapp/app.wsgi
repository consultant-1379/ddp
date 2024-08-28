#!/usr/bin/python

import sys
import logging
import os

logging.basicConfig(stream=sys.stderr)
sys.path.insert(0,"/data/ddp/current/modelledui/uiapp")
sys.path.insert(0,"/data/ddp/current/modelledui/lib")

from uiapp.app import app as application
from db import StatsDB

application.config['rootdir'] = "/data/ddp/current/modelledui"
if os.path.exists('/etc/certs/db-client.key'):
    StatsDB.StatsDB.init_tls('/etc/certs/db-client-{0}.cer', '/etc/certs/db-client.key', '/etc/certs/db-srv-ca.cer')

