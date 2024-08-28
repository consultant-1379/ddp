package com.ericsson.nms.ddp.report.ui;

public class Config {
	private static String yuiUrl = "http://ddpi.athtem.eei.ericsson.se/yui/build";
	private static String adminDB = "ddpadmin";
	private static String statsDB = "statsdb";

	public static String getYuiUrl() {
		return yuiUrl;
	}

	public static void setYuiUrl(String yuiUrl) {
		Config.yuiUrl = yuiUrl;
	}

	public static void setAdminDB(String adminDB) {
		Config.adminDB = adminDB;
	}

	public static String getAdminDB() {
		return adminDB;
	}

	public static void setStatsDB(String statsDB) {
		Config.statsDB = statsDB;
	}

	public static String getStatsDB() {
		return statsDB;
	}
}