package com.ericsson.nms.ddp.report.data;

import java.util.ArrayList;
import java.util.Iterator;
import java.util.List;
import java.util.Map;

import com.ericsson.nms.ddp.report.db.DBHandle;
import com.ericsson.nms.umts.ranos.util.instr2.config.ConfigTreeNode;

public class DDPData {
	private ConfigTreeNode cfg;
	private Map<String, String[]> params;
	protected String start;
	protected String end;
	protected String title = "No title set";
	private String timeCol = "time";
	private String tables = "";
	protected String cols = "";
	private List<String> colArray = new ArrayList<String>();
	private String filter = "";
	
	public DDPData(Map<String, String[]> params) {
		this.params = params;
	}
	
	public DDPData(ConfigTreeNode cfg, Map<String, String[]> params) {
		this.cfg = cfg;
		this.params = params;
		title = cfg.getAttribute("title");
		String type = cfg.getAttribute("type");
		
		for (Iterator<ConfigTreeNode> i = cfg.getChildren().iterator() ; i.hasNext() ; ) {
			ConfigTreeNode n = i.next();
			if (n.baseName().equals("table")) {
				if (tables.equals("")) tables = n.getData();
				else tables += "," + n.getData();
			} else if (n.baseName().equals("col")) {
				String colQ = n.getAttribute("name") + " AS '" + n.getAttribute("label") + "'";
				cols += "," + colQ;
				colArray.add(n.getAttribute("label"));
			} else if (n.baseName().equals("filter")) {
				String fStr = null;
				List<String> fArgs = new ArrayList<String>();
				for (Iterator<ConfigTreeNode> j = n.getChildren().iterator() ; j.hasNext() ; ) {
					ConfigTreeNode f = j.next();
					if (f.baseName().equals("filterString")) {
						fStr = f.getData();
					} else if (f.baseName().equals("param")) {
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
			} else if (n.baseName().equals("timecol")) {
				timeCol = n.getAttribute("name");
			}
		}
		String[] tStr = params.get("start_time");
		if (tStr != null) this.start = tStr[0];
		tStr = params.get("end_time");
		if (tStr != null) this.end = tStr[0];
	}
	
	public String getSql() {
		String query = "SELECT " + this.cols + " FROM " + this.tables + " WHERE " + this.timeCol +
			" BETWEEN '" + this.start + "' AND '" + this.end + "'";
		if (this.filter != null && ! this.filter.equals("")) query += " AND " + this.filter;
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
	
	public List<String> getCols() {
		return colArray;
	}
	
	public Map<String, List<String>> getData() {
		return new DBHandle().getDataSet(getSql(), timeCol, colArray);
	}
}
