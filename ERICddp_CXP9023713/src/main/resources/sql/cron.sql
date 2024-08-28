CREATE TABLE crontabs (
    serverid INT UNSIGNED NOT NULL REFERENCES servers(id),
	process_name_id SMALLINT UNSIGNED NOT NULL REFERENCES process_names(id),
	userid MEDIUMINT UNSIGNED NOT NULL REFERENCES oss_users(id),
	start_time DATETIME NOT NULL,
	end_time DATETIME NOT NULL,
	exec_time SMALLINT UNSIGNED NOT NULL,
	ret_code TINYINT UNSIGNED NOT NULL,
	INDEX serverIdStartTime (server_id, start_time)
);

CREATE TABLE cronstats (
	serverid INT UNSIGNED NOT NULL REFERENCES servers(id),
	process_name_id SMALLINT UNSIGNED NOT NULL REFERENCES process_names(id),
	date DATE NOT NULL,
	userid MEDIUMINT UNSIGNED NOT NULL REFERENCES oss_users(id),
	cmdcount SMALLINT UNSIGNED NOT NULL,
	maxtime SMALLINT UNSIGNED NOT NULL,
	avgtime SMALLINT UNSIGNED NOT NULL,
	total SMALLINT UNSIGNED NOT NULL,
	INDEX serverIdDate (server_id,date)
);