<?php
$pageTitle = "mBean Viewer";

include_once "../../common/init.php";
require_once 'HTML/Table.php';
require_once PHP_ROOT . "/classes/DDPTable.php";

const LINK = '/TOR/system/mBeanViewer.php';
const M_BEAN = 'mBean';
const SERVER = 'server';
const CFG = '/CFG/';

//Gets the data from inst.txt for the passed mBean and server.
function getInstrData( $selectedmBean, $srv ) {
    global $site, $dir, $stats_dir;
    $validData = array();

    //Add a space so we don't match mBeans ending with EXT when matching the non-Ext version
    $selectedmBean .= ' ';

    $path = "$stats_dir/tor/$site/data/$dir/tor_servers/{$srv}_TOR/$dir";
    $fName = "$path/instr.txt";
    if ( !file_exists($fName) ) {
        $fName = "$path/instr.txt.gz";
        if ( !file_exists($fName) ) {
            return $validData;
        }
    }
    $fh = gzopen($fName, 'r');
    if ( $fh ) {
        while (!gzeof($fh)) {
            $line = gzgets($fh, 8192);
            if ( strpos($line, $selectedmBean) !== false ) {
                $validData[] = $line;
            }
        }
        gzclose($fh);
    }
    return $validData;
}


function readXML( $filePath, $file, &$providerList ) {
    $xmlData = file_get_contents($filePath); //NOSONAR
    $xml = simplexml_load_string($xmlData) or die("Error: Cannot create object");
    foreach ( $xml->profile as $profile ) {
        $profileName = (string)$profile['name'];
        $mBeanNameList = array();
        foreach ($profile->provider as $provider) {
            $providerName = (string)$provider['name'];
            foreach ($provider->metricGroup as $metricGroup) {
                $mBeanName = (string)$metricGroup['name'];
                $mBeanNameList[] = array($mBeanName => $file);
            }
            if ( ! empty($mBeanNameList) ) {
                $key = 'Profile: ' . $profileName . ' Provider: ' . $providerName;
                $providerList[$key] = array($providerName => $mBeanNameList);
            }
        }
    }
}

//Takes the path to the xml files and gets a list of available mBeans in each file.
function getMBeanList( $site, $dir, $server ) {
    global $statsDB, $stats_dir;

    $dir = "$stats_dir/tor/$site/data/$dir/tor_servers/{$server}_TOR/$dir/instr/";
    $files = array();
    if ( is_dir($dir) ) {
        $files = scandir($dir);
    }
    $providerList = array();

    foreach ( $files as $file ) {
        $filePath = $dir.$file;
        $info = pathinfo($filePath);
        if ( file_exists($filePath) && $info["extension"] == "xml" ) {
            readXML( $filePath, $file, $providerList );
        }
    }
    return $providerList;
}

//Makes links from the passed list.
function makeLinks( $list, $other = null ) {
    $linkList = array();
    if (is_array($list)) {
        $list = array_filter($list);
    } else {
        return $linkList;
    }
    foreach ($list as $item) {
        if ( ! $other ) {
            $linkList[] =  makeLink( LINK, $item, array('SG' => $item) );
        } else {
            $key = key($item);
            $other[M_BEAN] = rawurlencode($key);
            $other['file'] = $item[$key];
            $linkList[] =  makeLink( LINK, $key, $other );
        }
    }
    return $linkList;
}

//Lists all mBeans as links under headings which are the xml file for them mBeans.
function selectMBean($server) {
    global $statsDB, $site, $date, $dir;

    $mBeans = getMBeanList($site, $dir, $server);
    if ( !$mBeans ) {
        drawHeader("No mBeans available for $server", 1, '');
    } else {
        drawHeader("Available mBeans for $server", 1, '');
        foreach ($mBeans as $file => $profile) {
            drawHeader($file, 2 ,'');
            foreach ($profile as $list) {
                $other = array(
                    SERVER => $server,
                    M_BEAN => true,
                    'display' => '1',
                    'file' => true
                );
                $links = makeLinks( $list, $other );
                echo makeHTMLList( $links );
            }
        }
    }
}

//Draws a HTML table with the passed variables.
function drawTable( $width, $data, $other = null ) {
    $table = new HTML_Table("border=0");
    while ( count($data) > 0 ) {
        $list = array();
        for ($i = 0; $i < $width; $i++) {
            if ( !empty($data) ) {
                $list[] = array_shift($data);
            }
        }
        $table->addRow( makeLinks($list, $other) );
    }
    echo $table->toHTML();
}

//Creates a list of links to each instance.
function selectInstance( $servers ) {
    echo "<H1>Select Instance:</H1>";
    $servers = array_keys( $servers);
    foreach ( $servers as $srv ) {
        echo makeLink( LINK, $srv, array(SERVER => $srv));
        echo addLineBreak(2);
    }
}

//Displays the data on the page once an mBean is selected.
function displayData($srv, $mBean) {
    global $site, $stats_dir, $dir, $php_webroot;

    $lines = getInstrData( $mBean, $srv );
    if ( empty($lines) ) {
        echo "No data available for $mBean";
    } else {
        $columnNames = array();
        $heading = "$srv: $mBean";

        //Display xml Link
        $fileName = requestValue('file');
        $filePath = "$stats_dir/tor/$site/data/$dir/tor_servers/{$srv}_TOR/$dir/instr/$fileName";
        $link = "<a href='" . getUrlForFile($filePath) . "' target='_blank'\> View: $fileName </a>";
        echo makeHtmlList( array($link) );

        $tableData = array();
        $cfgLine = getFirstCFGLine($lines);

        $count = 0;
        foreach ($lines as $line) {
            $count++;
            if ( preg_match(CFG, $line) ) {
                $cfgLine = $line;
                generateColumnNames($columnNames, $line);
            } elseif ( preg_match('/AttributeNotFoundException/', $line) ) {
                error_log("mBeanViewer.php Row skipped as AttributeNotFoundException found on instr.txt line $count");
                //Reset $columnNames as AttributeNotFoundException seems
                //to occur with new metrics being added and we have already added
                //the new column names to $columnNames
                $columnNames = array();
            } else {
                $tableData[] = prepareTableData( $line, $cfgLine );
            }
        }
        drawDDPTable($columnNames, $tableData, $heading);
    }
}

//This is to for the case when there is no CFG line an 00:00, we get the first available CFG line and assume
//that it contains the same metrics(This will break if a new metric is added in the first found line).
function getFirstCFGLine( $lines ) {
    $cfgLine = '';
    if ( !preg_match(CFG, $lines[0]) ) {
        foreach ($lines as $line) {
            if ( preg_match(CFG, $line) ) {
                $cfgLine = $line;
                break;
            }
        }
    }
    return $cfgLine;
}

//Displayes the help text, XML parser and bash script to run the parser for the given mbean
function displayMBeanInfo($srv, $mbean) {
    global $site, $stats_dir, $dir;

    $fileName = requestValue('file');
    $filePath = "$stats_dir/tor/$site/data/$dir/tor_servers/{$srv}_TOR/$dir/instr/$fileName";

    $xml = simplexml_load_file($filePath) or die('Failed to load file');
    foreach (getChildrenByName($xml->profile->provider, 'metricGroup') as $metricGroup) {
        $metricGroupName = (string)$metricGroup->attributes()['name'];
        if ( strpos($mbean, $metricGroupName) !== false ) {
            $metrics = getChildrenByName($metricGroup, "metric");
            $service = getServiceByServer($srv);
            echo "<H1>Help Text</H1>";
            echo makeHelpText($metricGroupName, $metrics) . addLineBreak(2);
            echo "<H1>XML Parser</H1>";
            echo "<H3><font color=\"red\">Target assumed same as source, table left blank</font></H3>";
            echo makeParser($service, $metrics, $metricGroupName) . addLineBreak(2);
            echo "<H1>Run Parser Script</H1>";
            echo makeParserScript($srv, $service) . addLineBreak(2);
            break;
        }
    }
}

//Get the children for a XML element only if they are of the desired type.
function getChildrenByName($xml, $name) {
    $children = array();
    foreach ($xml->children() as $child) {
        if ($child->getName() == $name) {
            $children[] = $child;
        }
    }
    return $children;
}

function getServiceByServer($srv) {
    global $date, $site, $statsDB;

    $statsDB->query("
SELECT
    enm_servicegroup_names.name AS SG
FROM
    enm_servicegroup_instances,
    enm_servicegroup_names,
    servers,
    sites
WHERE
    enm_servicegroup_instances.siteid = sites.id
        AND sites.name = '$site'
        AND enm_servicegroup_instances.date = '$date'
        AND enm_servicegroup_instances.serverid = servers.id
        AND enm_servicegroup_instances.serviceid = enm_servicegroup_names.id
        AND servers.hostname = '$srv'
ORDER BY servers.hostname");

    return $statsDB->getNextNamedRow()['SG'];
}

//Generate help text with information for the given metricGroup
function makeHelpText($metricGroup, $metrics) {
    $helpText = "<br><br><b>Data is collected from the following MBean:</b>\n\n";
    $helpText .= makeHTMLList(array($metricGroup));
    $helpText .= "<b>Attributes:</b>\n\n";
    $metricInfo = array();
    foreach ($metrics as $metric) {
        $metricInfo[] = "<b>{$metric->attributes()['name']}:</b> {$metric->attributes()['DisplayName']}";
    }
    return $helpText . makeHTMLList($metricInfo);
}

//Generate the XML parser for the given metricGroup, assumes the source and target for metrics are the same
function makeParser($service, $metrics, $metricGroupName) {
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
         <modelledinstr namespace="^(\S+)-Instrumentation@e2e_\S+">
           <services>
             <service name="$service"/>
           </services>

           <table name="">
             <keycol name="serverid" reftable="servers"/>
           </table>

           <metricgroups>
             <metricgroup name="^%1%-$metricGroupName$">
XML;

    foreach ($metrics as $metric) {
        $metricName = $metric->attributes()['name'];
        $delta = $metric->attributes()['CollectionType'] == "TRENDSUP" ? 'delta="true" filteridle="true"' : '';
        $xml .= "\n               <metric source=\"$metricName\" target=\"$metricName\" $delta/>";
    }

    $xml .= "
             </metricgroup>
           </metricgroups>

         </modelledinstr>";

    return '<pre>' . htmlentities($xml) .  '</pre>';
}

//Generate a bash script that will run the parser for a given service.
function makeParserScript($server, $service) {
    global $site, $dir;

    return <<<SCRIPT
DATE="$dir"<br>
SITE="$site"<br>
SERVER="$server"<br>
SERVICE="$service"<br><br>
MODEL="/data/ddp/current/analysis/modelled/instr/models/TOR/<font color="red">{finish_path_to_model}</font>"<br>
CFG="/data/stats/tor/\${SITE}/data/\${DATE}/tor_servers/\${SERVER}_TOR/$dir/instr"<br>
DATA="/data/stats/tor/\${SITE}/data/\${DATE}/tor_servers/\${SERVER}_TOR/$dir/instr.txt"<br>
INCR="/data/tmp/incr/\${SITE}/\${DATE}/\${SERVER}_TOR/parseModeledInstr"<br><br>
/data/ddp/current/analysis/modelled/instr/parseModeledInstr
    --model \${MODEL} --cfg \${CFG} --data \${DATA}
    --site \${SITE} --server \${SERVER} --service \${SERVICE}
SCRIPT;
}

//Takes a Data Line and a CFG Line and creates a row can be
//added to an array that DDPTable can understand.
function prepareTableData( $line, $cfgLine ) {
    $row = array();
    $line = filterLine($line);
    $values = explode(" ", $line);
    $cfgValues = explode(" ", $cfgLine);

    //Remove last value from $values & $cfgValues, which is an empty string.
    array_pop($values);
    array_pop($cfgValues);

    //Remove first three elements from $cfgValues (date, time & CFG).
    //Leaving only the attributes (columns).
    array_shift($cfgValues);
    array_shift($cfgValues);
    $cfgCheck = array_shift($cfgValues);
    //If $cfgValues contains a ( start removing elements until ) appears.
    if (preg_match('/\(/', $cfgCheck)) {
        while ( ! preg_match('/\)/', $cfgCheck) ) {
            $cfgCheck = array_shift($cfgValues);
        }
    }

    //Get date and time from $values and create $dateTime then add it to $row.
    $date = array_shift($values);
    $time = array_shift($values);
    $dateTime = formatTime($date, $time);
    $row['Time'] = $dateTime;

    //Go through each value and assign it to $row with $cfg as the key (column name).
    while ( count($values) > 0 ) {
        $val = array_shift($values);
        $cfg = array_shift($cfgValues);
        $row[$cfg] = $val;
    }

    return $row;
}

//Converts yyyy-mm-dd format to ddmmyy format OR
//Converts ddmmyy format to yyyy-mm-dd format
//Assumes this code will never be used after 2099-12-31
function dateConversion( $date ) {
    if ( preg_match('/^\d{2}(\d{2})-(\d{2})-(\d{2})$/', $date, $matches) === 1 ) {
        return $matches[3] . $matches[2] . $matches[1];
    } elseif (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $date, $matches) === 1) {
        return "20" . $matches[3] . "-" . $matches[2] . "-" . $matches[1];
    }
}

function formatTime( $date, $time ) {
    $date = str_replace('-', '', $date);
    $date = dateConversion( $date );
    $time = substr($time, 0, 8);
    return "$date $time";
}

//Draws the DDPTable with the generated columns and processed data.
function drawDDPTable($columnNames, $tableData, $heading) {
    drawHeaderWithHelp($heading, 1, 'UsageHelp');
    if ( ! empty($tableData) ) {
        $tableCols = array();
        foreach ($columnNames as $col) {
            //Get $val to check for true/false
            $val = $tableData[0][$col];
            if ( $col == 'Time' ) {
                $tableCols[] = array('key' => $col, DDPTable::LABEL => $col, 'formatter' => 'ddpFormatTime');
            } elseif ( $val == 'true' || $val == 'false' ) {
                $tableCols[] = array('key' => $col, DDPTable::LABEL => $col, 'type' => 'string');
            } else {
                $tableCols[] = array('key' => $col, DDPTable::LABEL => $col, 'type' => 'int');
            }
        }
        $rowsPerPage = array('rowsPerPage' => 50,'rowsPerPageOptions' => array(100, 500, 1000, 10000));
        $table = new DDPTable( 'mBeanData', $tableCols, array('data' => $tableData), $rowsPerPage );
        echo $table->getTable();
    } else {
        echo "No values found!";
    }
}

//Takes the CFG Lines and generates a list from the column names
function generateColumnNames( &$columnNames, $line ) {
    if ( ! preg_match('/\(/', $line) ) {
        preg_match('/.*CFG\S*\s(.*)/', $line, $matches);
    } else {
        preg_match('/.*CFG\S*\s.*\)\s(.*)/', $line, $matches);
    }

    $names = preg_split('/\s+/', $matches[1]);
    array_pop($names);

    foreach ($names as $name) {
        $columnNames[$name] = $name;
    }

    $columnNames['1Time'] = 'Time';
    ksort($columnNames);
}

//Filters Line to remove duplicated data e.g.
//25-07-19 00:00:11.126 svc-1-netex-com.ericsson.oss.itpf.datalayer.dps.bucket.data-persistence-service-runtime:type=
//DpsInstrumentedBean 0 0 0 0 0 0 793 0 3246 0 0 148404 183441 0 0 0 0 0 0 0 0 0 0 31405 232 440 0 56078 0 0 0 2150 0
//Becomes:
//25-07-19 00:00:11.126 0 0 0 0 0 0 793 0 3246 0 0 148404 183441 0 0 0 0 0 0 0 0 0 0 31405 232 440 0 56078 0 0 0 2150 0
function filterLine( $line ) {
    if (! preg_match('/\(/', $line)) {
        preg_match_all('/\s\S+/', $line, $matches);
        $line = str_replace($matches[0][1], '', $line);
    } else {
        preg_match('/\d{2}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}.\d{3}(\s.*\))/', $line, $matches);
        $line = str_replace($matches[1], '', $line);
    }
    return $line;
}

function main() {
    global $statsDB, $site, $date;
    //Displays All available SGs in a table.
    $srvcGrps = getServiceGroupList($site, $date);
    natcasesort($srvcGrps);
    drawheader('Select A Service Group', 1, '');
    drawTable( '5', $srvcGrps );
}

function getServers( $sg ) {
    global $statsDB, $site, $date;
    return enmGetServiceInstances($statsDB, $site, $date, $sg);
}

//Flow: main->selectInstance->selectMBean->displayData
if ( issetURLParam('display') ) {
    $mbean = rawurldecode(requestValue(M_BEAN));
    $server = requestValue(SERVER);
    if ( issetURLParam('info') && strpos($mbean, 'com') !== false ) {
        displayMBeanInfo( $server, $mbean );
    } else {
        displayData( $server, $mbean );
    }
} elseif ( issetURLParam(SERVER) ) {
    selectMBean( requestValue(SERVER) );
} elseif ( issetURLParam('SG') ) {
    $servers = getServers( requestValue('SG') );
    selectInstance( $servers );
} else {
    main();
}

include_once PHP_ROOT . "/common/finalise.php";

