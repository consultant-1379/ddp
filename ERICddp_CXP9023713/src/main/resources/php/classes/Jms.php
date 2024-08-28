<?php

require_once PHP_ROOT . "/SqlPlotParam.php";

class Jms {
  var $site;
  var $queueOrTopic;
  var $names;
  var $fromDate;
  var $toDate;
  var $height;
  var $width;

  function __construct($statsDB,$site,$queueOrTopic,$names,$fromDate,$toDate,$width=640,$height=240) {
    $this->statsDB = new StatsDB();
    $this->site = $site;
    $this->queueOrTopic = $queueOrTopic;
    $this->names = $names;
    $this->fromDate = $fromDate;
    $this->toDate = $toDate;
    $this->height = $height;
    $this->width = $width;
  }

  function getQPlot($title,$ylabel,$whatcol) {
    global $debug;

    $colNames = array_keys($whatcol);

    $name_table = "enm_jms" . $this->queueOrTopic . "_names";
    $stat_table = "enm_jms" . $this->queueOrTopic;
    $typeid_attr = $this->queueOrTopic . "id";

    $sql = "
$stat_table.siteid = sites.id AND sites.name = '%s' AND
$stat_table.$typeid_attr = $name_table.id AND $name_table.name IN (%s)";
    $tableStr = "$name_table, $stat_table, sites";
    $qArgsArr = array( 'site', 'names' );

    $sqlParam =
      array(
            'title'      => $title,
            'ylabel'     => $ylabel,
            'useragg'    => 'true',
            'persistent' => 'true',
            'forcelegend'=> 'true',
            'querylist' =>
            array(
                  array (
                         'timecol' => 'time',
                         'multiseries'=> "$name_table.name",
                         'whatcol' => $whatcol,
                         'tables'  => $tableStr,
                         'where'   => $sql,
                         'qargs'   => $qArgsArr
                         )
                  )
             );


    $sqlParamWriter = new SqlPlotParam();
    $id = $sqlParamWriter->saveParams($sqlParam);
    $namesStr = "'" . implode("','", explode(",",$this->names)) . "'";
    $url =  $sqlParamWriter->getImgURL( $id,
                                        $this->fromDate . " 00:00:00", $this->toDate . " 23:59:59",
                                        true, $this->width, $this->height,
                                        "names=" . urlencode($namesStr) );
    return $url;
  }

  function getMessageGraphs() {
    $graphArray = array();
    $graphArray[] = $this->getQPlot("Messages Added", "#", array( 'messagesAdded' => 'Messages Added'));
    $graphArray[] = $this->getQPlot("Message Count", "#", array( 'messageCount' => 'Message Count'));
    return $graphArray;
  }

  function getGraphArray() {
    $graphArray = array();

    if ( $this->queueOrTopic == "queue" ) {
      $graphArray[] = $this->getQPlot("Messages Added", "#", array( 'messagesAdded' => 'Messages Added'));
      $graphArray[] = $this->getQPlot("Message Count", "#", array( 'messageCount' => 'Message Count'));
      $graphArray[] = $this->getQPlot("Consumer Count", "#", array( 'consumerCount' => 'Consumer Count'));
      $graphArray[] = $this->getQPlot("Delivering Count", "#", array( 'deliveringCount' => 'Delivering Count'));
      $graphArray[] = $this->getQPlot("Scheduled Count", "#", array( 'scheduledCount' => 'Scheduled Count'));
    } else {
      $graphArray[] = $this->getQPlot("Messages Added", "#", array( 'messagesAdded' => 'Messages Added'));
      $graphArray[] = $this->getQPlot("Message Count", "#", array( 'messageCount' => 'Message Count'));
      $graphArray[] = $this->getQPlot("Subscriptions", "#", array( 'subscriptionCount' => 'Subscription Count'));
      $graphArray[] = $this->getQPlot("Delivering Count", "#", array( 'deliveringCount' => 'Delivering Count'));
    }

    return $graphArray;
  }
}

?>
