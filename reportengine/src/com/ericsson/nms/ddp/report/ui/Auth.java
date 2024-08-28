package com.ericsson.nms.ddp.report.ui;

import java.util.List;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import com.ericsson.nms.ddp.report.db.DBHandle;

public class Auth {
	private String userName = null;
	private int userId = -1;
	private String password = null;
	private HttpServletResponse response;
	
	private boolean authed = false;

	public Auth(HttpServletRequest request, HttpServletResponse response) {
		this.response = response;
		String authHeader = request.getHeader("Authorization");
		if (authHeader != null) {
			java.util.StringTokenizer st = new java.util.StringTokenizer(authHeader);
			if (st.hasMoreTokens()) {
				String basic = st.nextToken();
				if (basic.equalsIgnoreCase("Basic")) {
					String credentials = st.nextToken();
					String userPass = new String(
							org.apache.commons.codec.binary.Base64.decodeBase64(credentials)
					);
					int p = userPass.indexOf(":");
					if (p != -1) {
						userName = userPass.substring(0, p);
						password = userPass.substring(p+1);
						
						String sql = "SELECT " + Config.getAdminDB() + ".ddpusers.id " +
								"FROM " + Config.getAdminDB() + ".ddpusers " +
								"WHERE signum = '" + userName + "' AND mysql_passwd = PASSWORD('" + password + "')";
						DBHandle hdl = new DBHandle();
						List<String> result = hdl.getDataRow(sql);
						if (result.size() == 1) {
							setAuthed(true);
							setUserId(Integer.parseInt(result.get(0)));
						}
					}
				}
			}
		}
	}
	
	public void checkAuth() {
		if (isAuthed()) return;
		response.setHeader("WWW-Authenticate", "Basic realm=\"DDP Authentication\"");
		response.setStatus(401);
	}

	public void setAuthed(boolean authed) {
		this.authed = authed;
	}

	public boolean isAuthed() {
		return authed;
	}
	
	public String getUserName() {
		return userName;
	}

	public void setUserId(int userId) {
		this.userId = userId;
	}

	public int getUserId() {
		return userId;
	}
}