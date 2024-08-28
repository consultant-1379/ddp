<?php

require_once PHP_ROOT . "/classes/DDPTable.php";

class K8SEvents {
    const INVOLVED_OBJECT = 'involvedobject';

    public function __construct($dropJobEvents, $dropNormal, $fromTime, $toTime, $involvedObject = null) {
        global $rootdir_base, $dir;

        $eventsFile = $rootdir_base . "/" . $dir . "/k8s/k8s_events.json";
        if ( ! file_exists($eventsFile) ) {
            return;
        }

        $rawEvents = json_decode(file_get_contents($eventsFile), true); // NOSONAR
        $this->events = $this->filterEvents(
            $rawEvents,
            $dropJobEvents,
            $dropNormal,
            $fromTime,
            $toTime,
            $involvedObject
        );
        debugMsg("events", $this->events, 2);
    }

    public function getTable() {
        if (is_null($this->events)) {
            return null;
        }

        $columns = array(
            array(
                DDPTable::KEY => 'first',
                DDPTable::LABEL => 'First'
            ),
            array(
                DDPTable::KEY => 'last',
                DDPTable::LABEL => 'Last'
            ),
            array(
                DDPTable::KEY => 'type',
                DDPTable::LABEL => 'Type'
            ),
            array(
                DDPTable::KEY => 'reason',
                DDPTable::LABEL => 'Reason'
            ),
            array(
                DDPTable::KEY => 'message',
                DDPTable::LABEL => 'Message',
            )
        );

        if ( count($this->events) > 0 && array_key_exists(self::INVOLVED_OBJECT, $this->events[0])) {
            $columns[] = array(
                DDPTable::KEY => self::INVOLVED_OBJECT,
                DDPTable::LABEL => 'Involved Object',
            );
        }

        return new DDPTable(
            "k8sevents",
            $columns,
            array(
                'data' => $this->events
            )
        );
    }

    private function filterEvents($events, $dropJobEvents, $dropNormal, $fromTime, $toTime, $involvedObject) {
        debugMsg("filterEvents dropJobEvents=$dropJobEvents, dropNormal=$dropNormal");
        if ( $fromTime > 0 ) {
            debugMsg("filterEvents fromTime=$fromTime toTime=$toTime");
        }
        $filteredEvents = array();
        foreach ( $events as $event ) {
            if (! $this->dropEvent($event, $dropJobEvents, $dropNormal, $fromTime, $toTime, $involvedObject)) {
                $filteredEvents[] = $event;
            }
        }
        return $filteredEvents;
    }

    private function dropEvent($event, $dropJobEvents, $dropNormal, $fromTime, $toTime, $involvedObject) {
        debugMsg("event", $event);

        return $this->dropJobEvent($event, $dropJobEvents) ||
            $this->dropNormal($event, $dropNormal) ||
            $this->dropOnTime($event, $fromTime, $toTime) ||
            $this->dropOnInvolvedObject($event, $involvedObject);
    }

    private function dropJobEvent($event, $dropJobEvents) {
        return $dropJobEvents && $event['jobevent'] == 1;
    }

    private function dropNormal($event, $dropNormal) {
        return $dropNormal && $event['type'] === 'Normal';
    }

    private function dropOnTime($event, $fromTime, $toTime) {
        return $fromTime > 0 && ($event['time'] < $fromTime || $event['time'] > $toTime);
    }

    private function dropOnInvolvedObject($event, $involvedObject) {
        return (!is_null($involvedObject)) &&
            (substr($event[self::INVOLVED_OBJECT], 0, strlen($involvedObject)) !== $involvedObject);
    }
}


