package com.ericsson.nms.ddp.report.data;

import java.sql.Statement;
import java.util.Map;

import com.ericsson.nms.ddp.report.db.DBHandle;
import com.ericsson.nms.ddp.report.ui.Config;

public class TemplateList {


	String user = null;
	
	public TemplateList(Map<String, String[]> params, String user) {
		this.user = user;
		DBHandle h = new DBHandle(Config.getAdminDB());		
	}
	
	public TemplateList(Map<String, String[]> params) {
		this(params, null);
	}

	private String getSql() {
		String sql = "SELECT id, userid, name, description, status FROM " + Config.getAdminDB() + ".report_templates, ddpusers";
		if (user != null) sql += " WHERE userid = ddpusers.id AND ddpusers.signum = '" + user + "'";
		else sql += " WHERE status = 'public'";
		return sql;
	}
}
