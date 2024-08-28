import mysql.connector
import logging

class StatsDB:
    READ_ONLY = 1
    READ_WRITE = 2
    REPLICATION = 3

    NUMERIC_TYPES = [ 1, 2, 3 , 4, 5, 8, 9, 246 ]

    TYPE_NAMES = {
        1: 'int',
        2: 'int',
        3: 'int',
        4: 'float',
        5: 'float',
        7: 'timestamp',
        8: 'int',
        9: 'int',
        10: 'date',
        11: 'time',
        12: 'datetime',
        13: 'year',
        16: 'bit',
        252: 'string',
        253: 'string',
        254: 'string',
        246: 'float'
    }

    TLS_KEY_FILE = None
    TLS_CERT_FILE = None
    TLS_CA_FILE = None

    _logger = logging.getLogger("db.StatsDB")

    def __init__(self, accesstype = READ_ONLY):
        config = { 'host': 'dbhost', 'database': 'statsdb' }
        if accesstype == StatsDB.READ_ONLY:
            config["user"] = "statsusr"
            config["password"] = "_susr"
        elif accesstype == StatsDB.READ_WRITE:
            config["user"] = "statsadm"
            config["password"] = "_sadm"

        if StatsDB.TLS_KEY_FILE is not None:
            config['ssl_key'] = StatsDB.TLS_KEY_FILE
            config['ssl_ca'] = StatsDB.TLS_CA_FILE
            config['ssl_cert'] = StatsDB.TLS_CERT_FILE.format(config["user"])
            config['ssl_verify_cert'] = True
            config['ssl_verify_identity'] = True

        StatsDB._logger.error("_init_: config=%s", config)

        self.dbconn = mysql.connector.connect(**config)

    def __del__(self):
        self.dbconn.close()

    def select_all(self, sql, param = None, dictionary = False):
        StatsDB._logger.debug("select_all dictionary=%s, param=%s, sql= %s", dictionary, param, sql)
        cursor = self.dbconn.cursor()

        if param is None:
            cursor.execute(sql)
        else:
            StatsDB._logger.debug("param = %s", param)
            cursor.execute(sql, param)

        columns = []
        for column in cursor.description:
            columns.append({'name': column[0], 'type': column[1]})

        if dictionary:
            rows = []
            for inrow in cursor.fetchall():
                outrow = {}
                for index, value in enumerate(inrow):
                    outrow[columns[index]['name']] = value
                rows.append(outrow)
        else:
            rows = cursor.fetchall()

        result = {
            'rows': rows,
            'columns': columns
        }

        StatsDB._logger.debug("result = %s", result)

        cursor.close()

        return result

    def execute(self, sql, param = None, lastid = False):
        cursor = self.dbconn.cursor()

        if param is None:
            cursor.execute(sql)
        else:
            cursor.execute(sql, param)

        self.dbconn.commit()

        id = None
        if lastid:
            id = cursor.lastrowid

        cursor.close()

        return id

    @staticmethod
    def is_numeric(type):
        return type in StatsDB.NUMERIC_TYPES

    @staticmethod
    def type_name(type):
        return StatsDB.TYPE_NAMES[type]

    @staticmethod
    def init_tls(certfile, keyfile, cafile):
        StatsDB.TLS_KEY_FILE = keyfile
        StatsDB.TLS_CERT_FILE = certfile
        StatsDB.TLS_CA_FILE = cafile
