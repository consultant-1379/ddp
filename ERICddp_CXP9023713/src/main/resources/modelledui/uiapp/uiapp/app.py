#!/usr/bin/env python

from flask import request
from flask import Flask
from flask import Response
import json
from modelledui.Table import Table
from modelledui.Graph import Graph
from modelledui.GraphSet import GraphSet
from db import StatsDB
import logging
import os
import sys
import traceback

app = Flask(__name__)
app.debug = True

@app.route('/table/<path:tablepath>', methods=['POST'])
def table(tablepath):
    global app
    modelfile = '{0}/models/table/{1}.xml'.format(app.config['rootdir'], tablepath)
    app.logger.debug("table: request.json=%s", request.json)
    try:
        table_builder = Table(modelfile)
        result = table_builder.get_table(request.json['name'], request.json['param'])
        return json.dumps(result)
    except: # catch *all* exceptions
        return json.dumps({'error': "Exception occurred: {0}".format(traceback.format_exc())})

@app.route('/graph/<path:graphpath>', methods=['POST'])
def graph(graphpath):
    global app
    modelfile = '{0}/models/graph/{1}.xml'.format(app.config['rootdir'], graphpath)
    app.logger.debug("graph: request.json=%s", request.json)
    try:
        graph_builder = Graph(modelfile)
        graph = {
            'id': graph_builder.get_id(),
            'timespan': graph_builder.get_timespan(),
            'parameters': graph_builder.get_parameter_names()
        }
        default_size = graph_builder.get_default_size()
        if default_size is not None:
            graph['size'] = default_size
        reply = json.dumps(graph)
        app.logger.debug("graph: reply=%s", reply)
        return reply
    except: # catch *all* exceptions
        return json.dumps({'error': "Exception occurred: {0}".format(traceback.format_exc())})

@app.route('/graphset/<path:graphsetpath>', methods=['POST'])
def graphset(graphsetpath):
    global app
    modelfile = '{0}/models/graphset/{1}.xml'.format(app.config['rootdir'], graphsetpath)
    app.logger.debug("graphset: request.json=%s", request.json)
    try:
        builder = GraphSet(modelfile)
        graphset = {
            'timespan': builder.get_timespan(),
            'graphs': builder.get_graphs(),
            'groups': builder.get_groups(),
            'parameters': builder.get_parameter_names()
        }
        reply = json.dumps(graphset)
        app.logger.debug("graphset: reply=%s", reply)
        return reply
    except: # catch *all* exceptions
        return json.dumps({'error': "Exception occurred: {0}".format(traceback.format_exc())})

if __name__ == "__main__":
    # This is only for running standalone for debugging - normal init code is in ../app.wsgi
    app.config['rootdir'] = os.path.abspath(__file__ + "/../../../")
    if os.path.exists('/etc/certs/db-client.key'):
        app.logger.debug("main: enable DB TLS")
        StatsDB.StatsDB.init_tls('/etc/certs/db-client-{0}.cer', '/etc/certs/db-client.key', '/etc/certs/db-srv-ca.cer')
    logging.basicConfig(level=logging.DEBUG)
    logging.debug("Starting")
    app.run()
