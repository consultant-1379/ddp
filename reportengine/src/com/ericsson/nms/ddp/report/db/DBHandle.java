package com.ericsson.nms.ddp.report.db;

import java.io.File;
import java.io.IOException;
import java.io.PrintWriter;
import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

import javax.naming.InitialContext;
import javax.naming.NamingException;
import javax.sql.DataSource;

import com.ericsson.nms.ddp.report.data.Report;
import com.ericsson.nms.ddp.report.ui.Config;
import com.ericsson.nms.umts.ranos.util.instr2.InstrException;
import com.ericsson.nms.umts.ranos.util.instr2.config.ConfigReader;

public class DBHandle {
    private static final ThreadLocal<String> ddpServer = new ThreadLocal<String>();

    public static void setDDPServer(String ddpServer) {
	DBHandle.ddpServer.set(ddpServer);
    }
    
	public DBHandle() {
	}

	public Connection getConn() {
	    String ddpServ = ddpServer.get();
	    if ( ddpServ == null )
		ddpServ = "local";

		Connection conn = null;
		try {
			InitialContext ctx = new InitialContext();
			DataSource ds = (DataSource)ctx.lookup("jdbc/" + ddpServ + "StatsDB");
			conn = ds.getConnection();
			System.out.println("ddpServ=" + ddpServ + ", ddpServer=" + ddpServer.get());
			System.out.println(conn.getMetaData().getURL());
				
		} catch (NamingException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
			//	conn =
			//		DriverManager.getConnection("jdbc:mysql://" + host + ":" + port + "/" + dbName + "?" +
		    //                               "user=" + user + "&password=" + pass);			
			
		} catch (SQLException ex) {
				// TODO Auto-generated catch block
				ex.printStackTrace();
		}
		return conn;
	}
	
	public Map<String, List<String>> getDataSet(String query, String timeCol, List<String> cols) {
		Connection conn = null;
		Statement stmnt = null;
		Map<String, List<String>> data = new LinkedHashMap<String, List<String>>();
		for (java.util.Iterator<String> i = cols.iterator() ; i.hasNext() ; ) {
			data.put(i.next(), new ArrayList<String>());
		}
		if (timeCol != null) data.put(timeCol, new ArrayList<String>());
		try {
			conn = getConn();
			stmnt = conn.createStatement();
			if (stmnt.execute(query)) {
				ResultSet rs = stmnt.getResultSet();
				while (rs.next()) {
					// what columns have we got?
					if (timeCol != null) {
						data.get(timeCol).add(rs.getString(timeCol));
					}
					for (java.util.Iterator<String> i = cols.iterator() ; i.hasNext() ; ) {
						String colName = i.next();
						data.get(colName).add(rs.getString(colName));
					}
				}
			}
		} catch (SQLException e) {
			e.printStackTrace();			
		} finally {
			try {
				if (stmnt != null) stmnt.close();
				if (conn != null) conn.close();
			} catch (SQLException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		}
		return data;
	}
	
	/**
	 * Execute an SQL query and return a single row from the resultset.
	 * 
	 * @param query - an SQL query to be executed
	 * @return - a list containing the first row of data returned by the query,
	 * if any. The list is ordered according to the order of the columns in the 
	 * SQL select statement if the query is a select statement.
	 */
	public List<String> getDataRow(String query) {
		List<String> d = new ArrayList<String>();
		Connection conn = null;
		Statement stmnt = null;
		try {
			conn = getConn();
			stmnt = conn.createStatement();
			if (stmnt.execute(query)) {
				ResultSet rs = stmnt.getResultSet();
				if (rs.next()) {
					int nCols = rs.getMetaData().getColumnCount();
					for (int i = 1 ; i <= nCols ; i++ ) {
						d.add(rs.getString(i));
					}
				}
			}
		} catch (SQLException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		} finally {
			try {
				if (stmnt != null) stmnt.close();
				if (conn != null) conn.close();
			} catch (SQLException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		}
		return d;
	}
	
	/**
	 * Convenience method for retrieving an XML template by ID
	 * 
	 */
	public Report getReport(int tplId, Map<String, String[]> params) {
		String reportName = "No report";
		String xml = "";
		String description = "";
		Report rep = null;
		
		Connection conn = null;
		Statement stmnt = null;
		try {
			conn = getConn();
			stmnt = conn.createStatement();
			if (stmnt.execute("SELECT template, name, description FROM " + Config.getAdminDB() + ".report_templates WHERE id = " + tplId)) {
				ResultSet rs = stmnt.getResultSet();
				rs.next();
				xml = rs.getString(1);
				reportName = rs.getString(2);
				description = rs.getString(3);
			}
			stmnt.close();
			conn.close();
		} catch (SQLException e) {
			e.printStackTrace();
		}
		try {
			File f = File.createTempFile("xyz", null);
			PrintWriter p = new PrintWriter(f);
			// TODO: get ConfigReader to take a string
			p.print(xml);
			p.close();
			ConfigReader r = new ConfigReader(f.getAbsolutePath());
			f.delete();

			rep = new Report(reportName, description, r.getBaseNode(), params);
		} catch (InstrException e) {
			reportName = "Error generating report from supplied parameters";
			description = e.getMessage();
		} catch (IOException e) {
			reportName = "Error generating report from supplied parameters";
			description = e.getMessage();
		} finally {
			try {
				if (stmnt != null) stmnt.close();
				if (conn != null) conn.close();
			} catch (SQLException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		}
		if (rep == null) rep = new Report(reportName, description, params);
		return rep;
	}
}
