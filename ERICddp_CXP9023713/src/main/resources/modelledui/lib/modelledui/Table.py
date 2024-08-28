import xml.etree.ElementTree as ET
import logging
import json
from decimal import Decimal
import datetime

from db import StatsDB

class Table:
    _logger = logging.getLogger("modelledui.Table")

    DEFAULT_PAGE_SIZES = [ 20, 100, 1000, 10000, 100000 ]
    WEBARGS = [ 'site', 'dir', 'date', 'oss' ]

    class Error(Exception):
        pass

    class DataEncoder(json.JSONEncoder):
        def default(self, obj):
            if isinstance(obj, set):
                return ', '.join(obj)
            elif isinstance(obj, Decimal):
                value = float(obj)
                if value.is_integer():
                    return int(value)
                else:
                    return value
            elif isinstance(obj, datetime.datetime) or isinstance(obj, datetime.timedelta) or isinstance(obj, datetime.date):
                return obj.__str__()
            else:
                return json.JSONEncoder.default(self, obj)

    def __init__(self, modelfile):
        tree = ET.parse(modelfile)

        # get root element
        root = tree.getroot()

        dbtables = root.find('dbtables')
        self.main_table = dbtables.get('main')
        self.reference_tables = []
        for reference in dbtables.findall('reference'):
            join = 'JOIN'
            if 'join' in reference.attrib:
                join = reference.get('join')
            self.reference_tables.append({
                'table': reference.get('table'),
                'condition': reference.get('condition'),
                'join': join
                })

        self.__get_modelled_columns(root)

        self.param_names = []
        for param in root.findall('param'):
            self.param_names.append(param.get('name'))
        self.where = root.find('where').text

        self.__get_modelled_groupby(root)
        self.__get_modelled_orderby(root)
        self.__get_modelled_ctxmenu(root)


    def get_table(self, name, params):
        query = self.__build_query(params)

        statsdb = StatsDB.StatsDB()
        query_result = statsdb.select_all(query, dictionary=True)
        del statsdb

        self.column_types = {}
        for column in query_result['columns']:
            self.column_types[column['name']] = column['type']

        table_text = []
        table_text.append('<div id="tablediv_{0}" class="yui-skin-sam"></div>'.format(name))
        table_text.append('<script type="text/javascript">')

        table_def = {
            'name': name,
            'columns': self.__get_column_defs(),
            'data': query_result['rows'],
            'downloadURL': '/php/common/exportTable.php?{0}'.format(params['webargs'])
        }

        if len(query_result['rows']) > Table.DEFAULT_PAGE_SIZES[0]:
            table_def['rowsPerPage'] = Table.DEFAULT_PAGE_SIZES[0]
            table_def['rowsPerPageOptions'] = Table.DEFAULT_PAGE_SIZES

        if self.ctxmenu is not None:
            if self.ctxmenu_target == 'url':
                self.ctxmenu['url'] = params['url']
            elif self.ctxmenu_target == 'modelledgraph' or self.ctxmenu_target == 'modelledgraphset':
                modeltype = self.ctxmenu_target[8:]
                url = '/php/common/modelledtarget.php?modeltype={0}'.format(modeltype)
                for key, value in params.items():
                    if key == 'webargs':
                        url = '{0}&{1}'.format(url, params['webargs'])
                    elif key not in Table.WEBARGS:
                        url = '{0}&{1}={2}'.format(url, key, value)
                self.ctxmenu['url'] = url
            table_def['ctxMenu'] = self.ctxmenu

        table_text.append("var {0}_tableParam = {1};".format(
            name,
            json.dumps(table_def, cls=Table.DataEncoder, indent=4)
        ))
        table_text.append('YAHOO.util.Event.addListener(window, "load", ddpShowTable, {0}_tableParam);'.format(name))
        table_text.append('</script>')
        table_text.append('')

        return {
            'query': query,
            'numrows': len(query_result['rows']),
            'table': "\n".join(table_text)
            }


    def __build_query(self, params):
        where = self.where
        for param_name in self.param_names:
            if param_name in params:
                where = where.replace('%{0}%'.format(param_name), params[param_name])
            else:
                raise Table.Error("No value in params for {0}".format(param_name))

        col_defs = []

        if self.groupbycols is not None and self.rollup:
            col_defs.append('CONCAT({0}) AS rollupcol'.format(', '.join(self.groupbycols)))

        for column in self.columns:
            col_defs.append('{0} AS {1}'.format(column['db'], column['id']))

        joins = []
        for rt in self.reference_tables:
            joins.append('{0} {1} ON {2}'.format(rt['join'], rt['table'], rt['condition']))

        sql = 'SELECT {0} FROM {1} {2} WHERE {3}'.format(
            ', '.join(col_defs),
            self.main_table,
            ' '.join(joins),
            where
        )

        if self.groupbycols is not None:
            if self.rollup:
                sql = "{0} GROUP BY rollupcol WITH ROLLUP".format(sql)
            else:
                sql = "{0} GROUP BY {1}".format(sql, ', '.join(self.groupbycols))

        if self.orderby is not None:
            orderby_parts = []
            for order in self.orderby:
                orderby_parts.append("{0} {1}".format(order['columnid'], order['direction']))
            sql = "{0} ORDER BY {1}".format(sql, ', '.join(orderby_parts))

        Table._logger.debug("sql = %s", sql)

        return sql


    def __get_column_defs(self):
        column_defs = []

        if self.groupbycols is not None and self.rollup:
            column_defs.append({'key': 'rollupcol', 'visibile': False})

        total_col_done = False
        for column in self.columns:
            column_def = {'key': column['id'], 'visible': True }

            if 'label' in column:
                column_def['label'] = column['label']

            if 'visible' in column:
                column_def['visible'] = column['visible']

            column_data_type = self.column_types[column['id']]
            column_def['type'] = StatsDB.StatsDB.type_name(column_data_type)

            total_col_done = self.__get_column_formatter(column, total_col_done, column_def)
            column_defs.append(column_def)

        return column_defs


    def __get_column_formatter(self, column, total_col_done, column_def):
        if 'formatter' in column:
            column_def['formatter'] = column['formatter']
        elif StatsDB.StatsDB.is_numeric(self.column_types[column['id']]):
            column_def['formatter'] = 'ddpFormatNumber'
        elif self.rollup:
            if not total_col_done and column_def['visible']:
                column_def['formatter'] = 'ddpFormatRollupTotals'
                total_col_done = True
            elif total_col_done and column_def['visible']:
                column_def['formatter'] = 'ddpFormatRollupOther'

        return total_col_done

    def __get_modelled_columns(self, root):
        self.columns = []
        for column in root.findall('column'):
            if 'id' in column.attrib:
                id = column.get('id')
            else:
                id = 'col_{0}'.format(len(self.columns))

            visible = True
            if 'visible' in column.attrib:
                visible = column.get('visible') == 'true'

            column_param = {'id': id, 'db': column.get('db'), 'visible': visible }

            if visible:
                column_param['label'] = column.get('label')

            if 'formatter' in column.attrib:
                column_param['formatter'] = column.get('formatter')

            self.columns.append(column_param)


    def __get_modelled_ctxmenu(self, root):
        self.ctxmenu = None
        ctxmenu_node = root.find('ctxmenu')
        if ctxmenu_node is None:
            return

        multiselect = False
        if 'multiselect' in ctxmenu_node.attrib:
            multiselect = ctxmenu_node.get('multiselect') == 'true'

        self.ctxmenu_target = ctxmenu_node.get('targettype')

        menu_items = {}
        for item_node in ctxmenu_node.findall('item'):
            menu_items[item_node.get('id')] = item_node.get('label')

        self.ctxmenu = {
            'col': ctxmenu_node.get('keycol'),
            'multi': multiselect,
            'menu': menu_items
        }

        if self.ctxmenu_target == 'url':
            urltarget_node = ctxmenu_node.find('urltarget')
            self.ctxmenu['key'] = urltarget_node.get('arg')
        elif self.ctxmenu_target == 'modelledgraph' or self.ctxmenu_target == 'modelledgraphset':
            self.ctxmenu['key'] = 'modelname'


    def __get_modelled_groupby(self, root):
        self.groupbycols = None
        self.rollup = False
        groupby = root.find('groupby')
        if groupby is not None:
            self.groupbycols = []
            for column in groupby.findall('column'):
                self.groupbycols.append(column.get('db'))
            if 'rollup' in groupby.attrib:
                self.rollup = groupby.get('rollup') == 'true'
            Table._logger.debug("__get_modlled_GroupBy: groupbycols %s", self.groupbycols)
            Table._logger.debug("__get_modlled_GroupBy: rollup %s", self.rollup)


    def __get_modelled_orderby(self, root):
        self.orderby = []
        for order in root.findall('order'):
            self.orderby.append({ 'columnid': order.get('columnid'), 'direction': order.get('direction')})
        if len(self.orderby) == 0:
            self.orderby = None


        if self.orderby is not None:
            column_ids = []
            for column in self.columns:
                column_ids.append(column['id'])
            for order in self.orderby:
                if order['columnid'] not in column_ids:
                    raise Table.Error("order column {0} not in columns".format(order['columnid'] ))

