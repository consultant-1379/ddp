package com.ericsson.nms.ddp.report.data;

import java.util.ArrayList;
import java.util.Iterator;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

import com.ericsson.nms.umts.ranos.util.instr2.config.ConfigTreeNode;

/**
 * Wrapper class for the report.
 * @author EEICJON
 *
 */
public class Report {
	private String name = "No Name";
	private Map<String, DDPTimeSeries> tsMap = new LinkedHashMap<String, DDPTimeSeries>();
	private String desc = "";
	private Map<String, String[]> params;
	
	/**
	 * Create an empty report, usually for handling an error condition
	 * 
	 * @param name
	 * @param desc
	 * @param params
	 */
	public Report(String name, String desc, Map<String, String[]> params) {
		this.name = name;
		this.desc = desc;
		this.params = params;
	}
	
	public Report(String name, String desc, ConfigTreeNode cfg, Map<String, String[]> params) {
		this.name = name;
		this.desc = desc;
		this.params = params;
		
		// process config
		for (Iterator<ConfigTreeNode> i = cfg.getChildren().iterator() ; i.hasNext() ; ) {
			ConfigTreeNode node = i.next();
			if (node.baseName().equals("timeseries")) {
				tsMap.put(node.getAttribute("title"), new DDPTimeSeries(node, params));
			}
		}
	}
	
	public Map<String, String> getOverview() {
		Map<String, String> m = new LinkedHashMap<String, String>();
		m.put("Name", name);
		List<String> dl = new ArrayList<String>();
		dl.add(desc);
		m.put("Description", desc);
		//m.put("Start Time", params.get("start_time")[0]);
		//m.put("End Time", params.get("end_time")[0]);
		m.put("", "");
		for (Iterator<String> k = params.keySet().iterator() ; k.hasNext() ; ) {
			String n = k.next();
			m.put(n, params.get(n)[0]);
		}
		
		return m;
	}
	
	public DDPTimeSeries getTimeSeries(String name) {
		return getTimeSeriesMap().get(name);
	}

	public Map<String, DDPTimeSeries> getTimeSeriesMap() {
		return tsMap;
	}

	public void setName(String name) {
		this.name = name;
	}

	public String getName() {
		return name;
	}
	
	public String getDescription() {
		return desc;
	}
}
