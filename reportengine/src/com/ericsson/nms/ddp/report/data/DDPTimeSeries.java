package com.ericsson.nms.ddp.report.data;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.Iterator;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

import org.jfree.data.time.TimeSeries;
import org.jfree.data.time.TimeSeriesCollection;
import org.jfree.data.time.TimeTableXYDataset;

import com.ericsson.nms.ddp.report.db.DBHandle;
import com.ericsson.nms.ddp.report.ui.Config;
import com.ericsson.nms.ddp.report.ui.ReportEngine;
import com.ericsson.nms.umts.ranos.util.instr2.config.ConfigTreeNode;

public class DDPTimeSeries {
	private Map<String, String[]> params;
	private String start;
	private String end;
	private String title = "No title set";
	private String timeCol = "time";
	private String tables = "";
	private String cols = "";
	private List<String> colArray = new ArrayList<String>();
	private String filter = "";
	private boolean stacked = false;
	
	private DBHandle dbh;
	
	// pertinent data structures
	Map<String, List<String>> dataTable;
	
	// string identifying a column to convert into columns
	private String colSeriesCol;
	
	public DDPTimeSeries(ConfigTreeNode cfg, Map<String, String[]> params) {
		this.params = params;
		title = cfg.getAttribute("title");
		String type = cfg.getAttribute("type");
		String[] tStr = params.get("start_time");
		if (tStr != null) this.start = tStr[0];
		tStr = params.get("end_time");
		if (tStr != null) this.end = tStr[0];
		
		if (type != null && type.equals("stacked")) stacked = true;
		
		for (Iterator<ConfigTreeNode> i = cfg.getChildren().iterator() ; i.hasNext() ; ) {
			ConfigTreeNode n = i.next();
			if (n.baseName().equalsIgnoreCase("table")) {
				if (tables.equals("")) tables = n.getData();
				else tables += "," + n.getData();
			} else if (n.baseName().equalsIgnoreCase("col")) {
				String colQ = n.getAttribute("name") + " AS '" + n.getAttribute("label") + "'";
				cols += "," + colQ;
				colArray.add(n.getAttribute("label"));
			} else if (n.baseName().equalsIgnoreCase("columnseries")) {
				processColumnSeries(n);
			} else if (n.baseName().equalsIgnoreCase("filter")) {
				String fStr = null;
				List<String> fArgs = new ArrayList<String>();
				for (Iterator<ConfigTreeNode> j = n.getChildren().iterator() ; j.hasNext() ; ) {
					ConfigTreeNode f = j.next();
					if (f.baseName().equalsIgnoreCase("filterString")) {
						fStr = f.getData();
					} else if (f.baseName().equalsIgnoreCase("param")) {
						fArgs.add(f.getAttribute("name"));
					}
				}
				if (fStr != null) {
					for (Iterator<String> j = fArgs.iterator() ; j.hasNext() ; ) {
						// Only handle one parameter, the first one
						String paramName = j.next();
						String[] pStrArr = params.get(paramName);
						if (pStrArr != null) {
							fStr = String.format(fStr, pStrArr[0]);
						} else {
							fStr = String.format(fStr, "unknown");
						}
					}
				}
				if (this.filter.equals(""))	this.filter = fStr;
				else this.filter += " AND " + fStr;
			} else if (n.baseName().equalsIgnoreCase("timecol")) {
				timeCol = n.getAttribute("name");
			}
		}
		cols = timeCol + cols;
	}
	
	public Map<String, List<String>> getDataTable() {
		if (dataTable == null) populateDataTable();
		return dataTable;
	}
	
	/**
	 * Create a column - list map defining the contents of this timeseries
	 */
	private void populateDataTable() {
		Connection conn = null;
		Statement stmnt = null;
		// Initialise the data table
		dataTable = new HashMap<String, List<String>>();
		
		// for each column in our query, create an ArrayList in our dataTable
		if (timeCol != null) dataTable.put(timeCol, new ArrayList<String>());
		
		// populate each column
		try {
			conn = getDBHandle().getConn();
			stmnt = conn.createStatement();
			if (stmnt.execute(getSql())) {
				ResultSet rs = stmnt.getResultSet();
				if (colSeriesCol != null) {
					String lastTimeCol = "";
					while (rs.next()) {
						String col = rs.getString(colSeriesCol);
						ReportEngine.println("Got col " + col);
						if (! dataTable.containsKey(col)) {
							dataTable.put(col, new ArrayList<String>());
						}
						//ReportEngine.println("DataTable Col: " + col + ": " + rs.getTime(timeCol) + " : " + rs.getDouble("data") + "\n");
						String thisTimeCol = rs.getString(timeCol);
						
						// only add a specific time period once
						if (! thisTimeCol.equals(lastTimeCol)) {
							dataTable.get(timeCol).add(thisTimeCol);
							lastTimeCol = thisTimeCol;
						}
						dataTable.get(col).add(rs.getString("data"));
					}
				} else {
					// what columns have we got?
					for (java.util.Iterator<String> i = this.colArray.iterator() ; i.hasNext() ; ) {
						dataTable.put(i.next(), new ArrayList<String>());
					}
					while (rs.next()) {
						if (timeCol != null) {
							dataTable.get(timeCol).add(rs.getString(timeCol));
						}
						for (java.util.Iterator<String> i = colArray.iterator() ; i.hasNext() ; ) {
							String colName = i.next();
							dataTable.get(colName).add(rs.getString(colName));
						}
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
	}
	
	public TimeTableXYDataset getTimeTableXYDataset() {
		TimeTableXYDataset xyds = new TimeTableXYDataset();
		Connection conn = null;
		Statement stmnt = null;
		try {
			conn = getDBHandle().getConn();
			stmnt = conn.createStatement();
			if (stmnt.execute(getSql())) {
				ResultSet rs = stmnt.getResultSet();
				// do we have a column series?
				if (colSeriesCol != null) {
					ReportEngine.println("Processing colseries for " + colSeriesCol + ": " + getSql());
					while (rs.next()) {
						String colName = rs.getString(colSeriesCol);
						xyds.add(new org.jfree.data.time.Millisecond(rs.getTimestamp(timeCol)),
								rs.getDouble("data"), colName);
					}
				} else {
					while (rs.next()) {
						// what columns have we got?
						for (java.util.Iterator<String> i = colArray.iterator() ; i.hasNext() ; ) {
							String colName = i.next();
							xyds.add(new org.jfree.data.time.Millisecond(rs.getTimestamp(timeCol)),
									rs.getDouble(colName), colName);
						}
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
		return xyds;
	}
	
	public TimeSeriesCollection getTimeSeriesCollection() {
		// We should probably look to using the generic dataTable above,
		// but for now it is more efficient to use the DB rather than try to manipulate 
		// the contents of the dataTable HashMap into a TimeSeriesCollection.
		Connection conn = null;
		Statement stmnt = null;
		
		Map<String, TimeSeries> tsMap = new LinkedHashMap<String, TimeSeries>();
		try {
			conn = getDBHandle().getConn();
			stmnt = conn.createStatement();
			if (stmnt.execute(getSql())) {
				ResultSet rs = stmnt.getResultSet();

				ReportEngine.println("Got resultset");
				
				// do we have a column series?
				if (colSeriesCol != null) {
					ReportEngine.println("Processing colseries for " + colSeriesCol + ": " + getSql());
					while (rs.next()) {
						String col = rs.getString(colSeriesCol);
						if (! tsMap.containsKey(col)) {
							tsMap.put(col, new TimeSeries(col));
						}
						//ReportEngine.println("Col: " + col + ": " + rs.getTime(timeCol) + " : " + rs.getDouble("data") + "\n");
						tsMap.get(col).addOrUpdate(new org.jfree.data.time.Millisecond(rs.getTimestamp(timeCol)), rs.getDouble("data"));
					}
				} else {
					for (java.util.Iterator<String> i = colArray.iterator() ; i.hasNext() ; ) {
						String colName = i.next();
						tsMap.put(colName, new TimeSeries(colName));
					}
					while (rs.next()) {
						// what columns have we got?
						for (java.util.Iterator<String> i = tsMap.keySet().iterator() ; i.hasNext() ; ) {
							String col = i.next();
							TimeSeries ts = tsMap.get(col);
							ts.addOrUpdate(new org.jfree.data.time.Millisecond(rs.getTimestamp(timeCol)),
									rs.getDouble(col));
						}
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
		TimeSeriesCollection tsc = new TimeSeriesCollection();
		for (java.util.Iterator<String> i = tsMap.keySet().iterator() ; i.hasNext() ; ) {
			tsc.addSeries(tsMap.get(i.next()));
		}
		return tsc;
	}
	
	public String getSql() {
		String myCols = this.cols;
		if (colSeriesCol != null) myCols += "," + colSeriesCol;
		String query = "SELECT " + myCols + " FROM " + this.tables + " WHERE " + this.timeCol +
			" BETWEEN '" + this.start + "' AND '" + this.end + "'";
		if (this.filter != null && ! this.filter.equals("")) query += " AND " + this.filter;
		query += " ORDER BY " + this.timeCol + " ASC";
		System.out.println("QUERY: " + query);
		return query;
	}
		
	public void setStartTime(String start) {
		this.start = start;
	}
	
	public void setEndTime(String end) {
		this.end = end;
	}
	
	public String getTitle() {
		return this.title;
	}
	
	public String getTimeCol() {
		return this.timeCol;
	}
	
	public String getColSeriesCol() {
		return this.colSeriesCol;
	}
	
	public List<String> getCols() {
		return colArray;
	}
	
	public boolean isStacked() {
		return stacked;
	}
	
	/**
	 * Process a configTreeNode representing a columnSeries. A columnSeries
	 * is an XML artefact describing a set of values, retrieved by a separate SQL
	 * query, which will be used as columns in the time series data set.
	 * 
	 * An example of this is the disk IO tables, where a query for a single server will return multiple
	 * rows, one for each disk:
	 * 
	 * SELECT time AS ts, disks.name AS seriesid,  avque as 'avque' FROM hires_disk_stat, disks
	 * WHERE time BETWEEN '2011-07-21 00:00:00' AND '2011-07-21 23:59:59' AND
	 * hires_disk_stat.diskid = disks.id AND
	 * disks.id IN (15305,15304,15303,15302)  ORDER BY ts
	 * 
	 * Returns:
	 * 
	 * ts, seriesid, avque
	 * '2011-07-21 00:06:00', 'nas8:/vx/events1-eventdata-07', '0.0'
	 * '2011-07-21 00:06:00', 'nas8:/vx/events1-eventdata-15', '0.0'
	 * '2011-07-21 00:06:00', 'nas8:/vx/events1-pushdata-07', '0.0'
	 * '2011-07-21 00:06:00', 'nas8:/vx/events1-pushdata-15', '0.0'
	 * '2011-07-21 00:11:00', 'nas8:/vx/events1-eventdata-07', '0.0'
	 * '2011-07-21 00:11:00', 'nas8:/vx/events1-eventdata-15', '0.0'
	 * '2011-07-21 00:11:00', 'nas8:/vx/events1-pushdata-07', '0.0'
	 * '2011-07-21 00:11:00', 'nas8:/vx/events1-pushdata-15', '0.0'
	 * 
	 * In this case, what we actually want to see is a dataset like:
	 * 
	 * ts, nas8:/vx/events1-eventdata-07, nas8:/vx/events1-eventdata-15, nas8:/vx/events1-pushdata-07, nas8:/vx/events1-pushdata-15
	 * '2011-07-21 00:06:00', '0.0', '0.0', '0.0', '0.0'
	 * '2011-07-21 00:11:00', '0.0', '0.0', '0.0', '0.0'
	 * 
	 * We do this by defining a column name that we will use as a column selector, 
	 * and a filter column we will use to reduce the columns we are interested in.
	 * 
	 * @param n
	 */
	private void processColumnSeries(ConfigTreeNode n) {
		String colSeriesTables = "";
		String colSeriesTimeCol = "time";
		String colSeriesFilterCol = "";
		String colSeriesFilter = "";
		String limit = "";
		String order = "";
		for (Iterator<ConfigTreeNode> csIter = n.getChildren().iterator() ; csIter.hasNext() ; ) {
			ConfigTreeNode csn = csIter.next();
			if (csn.baseName().equalsIgnoreCase("table")) {
				if (colSeriesTables.equals("")) colSeriesTables = csn.getData();
				else colSeriesTables += "," + csn.getData();
			} else if (csn.baseName().equalsIgnoreCase("timecol")) {
				colSeriesTimeCol = csn.getAttribute("name");
			} else if (csn.baseName().equalsIgnoreCase("col")) {
				colSeriesCol = csn.getAttribute("name");
			} else if (csn.baseName().equalsIgnoreCase("filtercol")) {
				colSeriesFilterCol = csn.getAttribute("name");
			} else if (csn.baseName().equalsIgnoreCase("datacol")) {
				cols = "," + csn.getAttribute("name") + " AS data";
			} else if (csn.baseName().equalsIgnoreCase("limit")) {
				limit = " LIMIT " + csn.getAttribute("count");
			} else if (csn.baseName().equalsIgnoreCase("order")) {
				order = " ORDER BY " + csn.getAttribute("by");
			} else if (csn.baseName().equalsIgnoreCase("filter")) {
				String fStr = null;
				List<String> fArgs = new ArrayList<String>();
				for (Iterator<ConfigTreeNode> j = csn.getChildren().iterator() ; j.hasNext() ; ) {
					ConfigTreeNode f = j.next();
					if (f.baseName().equalsIgnoreCase("filterString")) {
						fStr = f.getData();
					} else if (f.baseName().equalsIgnoreCase("param")) {
						fArgs.add(f.getAttribute("name"));
					}
				}
				if (fStr != null) {
					for (Iterator<String> j = fArgs.iterator() ; j.hasNext() ; ) {
						// Only handle one parameter, the first one
						String paramName = j.next();
						String[] pStrArr = params.get(paramName);
						if (pStrArr != null) {
							fStr = String.format(fStr, pStrArr[0]);
						} else {
							fStr = String.format(fStr, "unknown");
						}
					}
					colSeriesFilter += " AND " + fStr;
				}
			}
		}
		String sql = "SELECT " + colSeriesFilterCol + " AS id FROM "
					+ colSeriesTables + " WHERE " + colSeriesTimeCol +
					" BETWEEN '" + this.start + "' AND '" + this.end + "'"
					+ colSeriesFilter + " GROUP BY id" + order + limit;
		System.out.println("Processing Column Series: " + sql);
		List<String> colSeriesColNames = new ArrayList<String>();
		colSeriesColNames.add("id");
		Map<String, List<String>> colSeriesData =
			getDBHandle().getDataSet(sql, null, colSeriesColNames);

		// Do we have any columns to collect? If not, don't bother running
		// the next query, it will fail.
		if (colSeriesData.get("id").isEmpty()) return;
		String colFilter = colSeriesFilterCol + " IN (";
		String delim = "";
		for (Iterator<String> colIter = colSeriesData.get("id").iterator() ; colIter.hasNext() ; ) {
			colFilter += delim + colIter.next();
			delim = ",";
		}
		colFilter += ")";
		if (this.filter.equals(""))	this.filter = colFilter;
		else this.filter += " AND " + colFilter;
	}
	
	private DBHandle getDBHandle() {
		if (dbh == null) dbh = new DBHandle();
		return dbh;
	}
}