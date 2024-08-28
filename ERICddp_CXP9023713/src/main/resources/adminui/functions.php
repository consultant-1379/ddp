<?php

require_once "constants.php";

function getSiteIndex( $dateLimit = null, $cols = null ) {
    global $statsDB, $countries, $AdminDB;

    $rowData = array();

    $sql = "
SELECT
    sites.name AS site,
    sites.id AS siteid,
    site_type,
    country,
    operators.name AS operator,
    site_status,
    deploy_infra.name AS deployinfra,
    utilver,
    DATE(lastupload) AS lastupload_date,
    GROUP_CONCAT($AdminDB.site_accessgroups.grp) AS access_group,
    $AdminDB.ftpusers.userid AS ftpuser,
    creator,
    requestor
FROM
    sites
    LEFT JOIN $AdminDB.site_accessgroups ON sites.id = $AdminDB.site_accessgroups.siteid
    JOIN operators ON sites.oper_id = operators.id
    LEFT OUTER JOIN deploy_infra ON sites.infra_id = deploy_infra.id
    JOIN $AdminDB.ftpusers ON sites.id = $AdminDB.ftpusers.siteid";

    if ( $dateLimit ) {
        $sql .= " WHERE DATEDIFF( NOW(), DATE(lastupload) ) < $dateLimit";
    }
    $sql .= " GROUP BY site";

    $statsDB->query($sql);

    while ( $row = $statsDB->getNextNamedRow() ) {
        $rowData[] = $row;
    }

    // Add edit link to site column
    foreach ($rowData as $key => $d) {
        $d['site'] = "<a href=\"?selectedSite=" .  urlencode($d['site']) . "\">". $d['site'] . "</a>";
        $d[COUNTRY] = $countries[$d[COUNTRY]];
        $rowData[$key] = $d;
    }

    $defaultCols = array(
        array('key' => 'site', DDPTable::LABEL => 'Site'),
        array('key' => SITE_TYPE, DDPTable::LABEL => 'Site Type'),
        array('key' => SITE_STATUS, DDPTable::LABEL => 'Site Status'),
        array('key' => 'lastupload_date', DDPTable::LABEL => 'Last Upload'),
    );

    if ( $cols != null ) {
        foreach ( $cols as $key => $label ) {
            $defaultCols[] = array('key' => $key, DDPTable::LABEL => $label );
        }
    }

    $table = new DDPTable(
        "Site_Managment",
        $defaultCols,
        array('data' => $rowData)
    );
    echo $table->getTable();
}

function getSiteData() {
    global $site, $statsDB, $AdminDB;

    echo addLineBreak();
    $siteData = array();
    $sql = "SELECT sites.id AS siteid, sites.name AS site, site_type, country, oper_id AS operator,
        site_status, infra_id AS deployinfra, utilver, DATE(lastupload) AS lastupload_date
        FROM (sites, operators)
        LEFT OUTER JOIN deploy_infra ON (sites.infra_id = deploy_infra.id)
        WHERE sites.oper_id = operators.id AND
        sites.name = '" . $statsDB->escape($site) . "'";
    $statsDB->query($sql);
    if ($statsDB->getNumRows() != 1) {
        if ($statsDB->getNumRows() == 0) {
            echo "<h1>No site called '" . $site . "' exists</h1>\n";
        } else {
            echo "<h1>Found " . $statsDB->getNumRows() . " entries for site '" . $site . "'</h1>\n";
        }
        return false;
    }
    $data = $statsDB->getNextNamedRow();
    $siteData['site'] = $site;
    $siteData[SITEID] = $data[SITEID];
    $siteData[SITE_TYPE] = $data[SITE_TYPE];
    $siteData[OPERATOR] = $data[OPERATOR];
    $siteData[SITE_STATUS] = $data[SITE_STATUS];
    $siteData[DEPLOYMENT_INFRA] = $data[DEPLOYMENT_INFRA];
    $siteData[COUNTRY] = $data[COUNTRY];
    $sql = "SELECT userid AS ftpuserid,homedir AS ftphomedir FROM $AdminDB.ftpusers WHERE siteid = " . $data[SITEID];
    $statsDB->query($sql);
    if ($statsDB->getNumRows() != 1) {
        if ($statsDB->getNumRows() == 0) {
            echo "<b>ERROR: No FTP user for this site!</b>\n";
        } else {
            echo "<b>ERROR: Found " . $statsDB->getNumRows() . " ftp entries for site '" . $site . "'</b>\n";
        }
        return false;
    }

    $row = $statsDB->getNextNamedRow();
    $siteData['ftpuserid'] = $row['ftpuserid'];
    $siteData['ftphomedir'] = $row['ftphomedir'];
    return $siteData;
}

function getDDPServerName() {
    if ( isset($ddp_site_domainname) ) {
        return $ddp_site_domainname;
    } else {
        return gethostname() . ".athtem.eei.ericsson.se";
    }
}

function isTestVm() {
    $ddpServer = getDDPServerName();
    if ( substr( $ddpServer, 0, 7 ) === "atddpvm" ) {
        return 1;
    }
    return 0;
}

/*
 * Returns a string that can be used to set the passwd column of the
 * ftpusers table
 */
function getEncrytedFtpPassword($password) {
    return "'{md5}" . base64_encode(pack("H*", md5($password))) . "'";
}
