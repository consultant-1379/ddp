import xml.etree.ElementTree as ET
import logging
import json
import copy
from modelledui.Graph import Graph

from db import StatsDB

class GraphSet:
    _logger = logging.getLogger("modelledui.GraphSet")

    TITLE = 'title'
    TIMESPAN_ATTRIB = 'timespan'
    QUERIES = 'queries'
    QUERY = 'query'

    class Error(Exception):
        pass

    def __init__(self, modelfile=None):
        tree = ET.parse(modelfile)
        root = tree.getroot()

        self.base_query_node = root.find(GraphSet.QUERY)
        self.timespan = root.attrib[GraphSet.TIMESPAN_ATTRIB]
        self.graphs = {}
        self.parameter_names = None
        for graph_node in root.find('graphs').findall('graph'):
            modelledgraph_node = self.__create_modelledgraph(graph_node)
            graph = Graph(rootnode=modelledgraph_node)
            self.graphs[graph_node.attrib['name']] = {
                'id': graph.get_id()
            }
            if self.parameter_names is None:
                self.parameter_names = graph.get_parameter_names()

        self.groups = []
        groups_node = root.find('groups')
        if groups_node is not None:
            for group_node in groups_node.findall('group'):
                group = {
                    'name': group_node.attrib['name'],
                    'members': []
                }
                for member_node in group_node.findall('member'):
                    group['members'].append(member_node.text)
                self.groups.append(group)

    def get_timespan(self):
        return self.timespan

    def get_graphs(self):
        return self.graphs

    def get_groups(self):
        return self.groups

    def get_parameter_names(self):
        return self.parameter_names

    def __create_modelledgraph(self, graph_node):
        modelledgraph_node = ET.Element('modelledgraph')

        title = graph_node.find(GraphSet.TITLE)
        if title is not None:
            modelledgraph_node.append(copy.copy(graph_node.find(GraphSet.TITLE)))

        queries_node = ET.SubElement(modelledgraph_node, GraphSet.QUERIES)
        query_node = copy.deepcopy(self.base_query_node)
        queries_node.append(query_node)
        for column_node in graph_node.findall('column'):
            query_node.append(copy.copy(column_node))

        for name, value in graph_node.attrib.items():
            modelledgraph_node.set(name, value)
        modelledgraph_node.set(GraphSet.TIMESPAN_ATTRIB, self.timespan)

        return modelledgraph_node


