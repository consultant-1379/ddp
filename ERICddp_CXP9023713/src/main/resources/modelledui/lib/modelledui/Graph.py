import xml.etree.ElementTree as ET
import logging
import json

from db import StatsDB

class Graph:
    _logger = logging.getLogger("modelledui.Graph")

    TITLE = 'title'
    TYPE = 'type'
    Y_LABEL = 'ylabel'
    USER_AGG = 'useragg'
    PRESET_AGG = 'presetagg'
    PERSISTENT = 'persistent'
    FORCE_LEGEND = 'forcelegend'
    SB_BARWIDTH = 'sb.barwidth'
    QUERY_LIST = 'querylist'
    TIME_COL = 'timecol'
    WHAT_COL = 'whatcol'
    TABLES = 'tables'
    WHERE = 'where'
    Q_ARGS = 'qargs'
    T_ARGS = 'targs'
    MULTI_SERIES = 'multiseries'
    SERIES_FILE = 'seriesfile'
    CAT_COLUMN = 'cat.column'
    TIME_WHERE = 'time.where'

    WIDTH = 'width'
    HEIGHT = 'height'

    class Error(Exception):
        pass

    def __init__(self, modelfile=None, rootnode=None):
        self.graph_param = {
            Graph.PERSISTENT: True,
            Graph.USER_AGG: True,
            Graph.QUERY_LIST: []
        }

        if modelfile is not None:
            tree = ET.parse(modelfile)
            root = tree.getroot()
        else:
            root = rootnode

        self.__get_modelled_title(root)

        self.graph_param[Graph.TYPE] = root.get('type')

        for optname in [Graph.Y_LABEL, Graph.USER_AGG, Graph.FORCE_LEGEND]:
            if optname in root.attrib:
                self.graph_param[optname] = root.get(optname)

        preset_agg_node = root.find(Graph.PRESET_AGG)
        if preset_agg_node is not None:
            self.graph_param[Graph.PRESET_AGG] = "{0}:{1}".format(preset_agg_node.get('type'), preset_agg_node.get('interval'))

        self.__get_modelled_queries(root)

        self.default_size = None
        size_node = root.find('size')
        if size_node is not None:
            self.default_size = {
                Graph.WIDTH: size_node.get(Graph.WIDTH),
                Graph.HEIGHT: size_node.get(Graph.HEIGHT)
            }

        self.timespan = root.get('timespan')


    def get_id(self):
        graph_def = json.dumps(self.graph_param)

        statsdb = StatsDB.StatsDB(StatsDB.StatsDB.READ_WRITE)
        query_result = statsdb.select_all(
            "SELECT id FROM sql_plot_param WHERE param = %(param)s",
            { 'param': graph_def }
        )
        if len(query_result['rows']) > 0:
            id = query_result['rows'][0][0]
        else:
            id = statsdb.execute(
                "INSERT INTO sql_plot_param (param) VALUES (%(param)s)",
                { 'param': graph_def },
                True
            )

        del statsdb

        return id


    def get_default_size(self):
        return self.default_size


    def get_timespan(self):
        return self.timespan


    def get_parameter_names(self):
        results = set()

        for query_def in self.graph_param[Graph.QUERY_LIST]:
            for arg in query_def[Graph.Q_ARGS]:
                results.add(arg)

        if Graph.T_ARGS in self.graph_param:
            for arg in self.graph_param[Graph.T_ARGS]:
                results.add(arg)

        return list(results)


    def __get_modelled_title(self, root):
        title = root.find('title')
        if title is not None:
            self.graph_param[Graph.TITLE] = title.get('value')
            title_args = []
            for arg in title.findall('param'):
                title_args.append(arg.get('name'))
            if len(title_args) > 0:
                self.graph_param[Graph.T_ARGS] = title_args


    def __get_modelled_queries(self, root):
        for query_node in root.find('queries').findall('query'):
            query_def = {
                Graph.TIME_COL: query_node.get(Graph.TIME_COL),
                Graph.WHAT_COL: {},
                Graph.Q_ARGS: [],
                Graph.WHERE: query_node.find('where').text
            }

            for column_node in query_node.findall('column'):
                query_def[Graph.WHAT_COL][column_node.get('db')] = column_node.get('label')

            for param_node in query_node.findall('param'):
                query_def[Graph.Q_ARGS].append(param_node.get('name'))

            for optname in [Graph.MULTI_SERIES, Graph.CAT_COLUMN]:
                if optname in query_node.attrib:
                    query_def[optname] = query_node.get(optname)

            dbtables_node = query_node.find('dbtables')
            joins = []
            for reference_node in dbtables_node.findall('reference'):
                join_type = 'JOIN'
                if 'join' in reference_node.attrib:
                    join_type = reference_node.get('join')
                join_expr = '{0} {1} ON {2}'.format(
                    join_type,
                    reference_node.get('table'),
                    reference_node.get('condition')
                )
                joins.append(join_expr)
            query_def[Graph.TABLES] = '{0} {1}'.format(dbtables_node.get('main'), ' '.join(joins))

            self.graph_param[Graph.QUERY_LIST].append(query_def)
