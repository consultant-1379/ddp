<?php

const AHREF = '<a href="';
const END_LIST = '</ul>';
const PHP_SELF = 'PHP_SELF';

/** Functions for creating Links **/

/**
 *Returns the link to the jmx for a service
 *
 *@author Lorcan Williamson
 *
 *@param string $service The service to get the link to.
 *
**/
function makeGenJmxLink($service) {
    global $webargs, $php_webroot, $date, $site, $statsDB;

    $names = array();
    $srvList = enmGetServiceInstances($statsDB, $site, $date, $service);
    foreach ( $srvList as $server => $serverid ) {
        $names[] = $server . "," . $service;
    }
    return PHP_WEBROOT . "/genjmx.php?$webargs&names=" . implode(";", $names);
}

/**
 *Returns the full link to the jmx for a service
 *
 *@author Patrick O Connor
 *
 *@param string $service The service to get the link to.
 *@param string $label The label for the link.
 *
**/
function makeFullGenJmxLink($service, $label) {
    $hoverOverText = 'Click here to go to the GenJmx page.';
    return "<a title='$hoverOverText' href=\"" . makeGenJmxLink($service) . "\">$label</a>";
}

/**
 *Generates a DDP anchor link (A link that will bring you to a specified anchor on the same page).
 *Takes in anchor and label, can also take title.
 *Example in /php/TOR/cm/cm_dp_med.php.
 *
 *@author Patrick O Connor
 *
 *@param string $anchor This is the anchor link to be assigned to href
 *@param string $label This is the label to be used as the anchor
 *@param string $title This is the title of the anchor
 *
 */
function makeAnchorLink($anchor, $label, $title=null) {
    $link = "<a ";
    if ( ! is_null($title) ) {
        $link .= "title='$title' ";
    }
    return $link . "href='#$anchor'>$label</a>";
}

/**
 *Generates a URL for a DDP page.
 *Takes in the path and label, can also take other args.
 *
 *@param string $path This is the path to the php file.
 *@param array $otherArgs These are other arguments that will be appended to the link after $webargs.
 *
 */
function makeURL($path, $otherArgs=null) {
    global $webargs;

    $url = PHP_WEBROOT . $path . "?" . $webargs;
    if ( ! is_null($otherArgs) ) {
        foreach ( $otherArgs as $name => $value ) {
            $url .= "&" . $name . "=" . $value;
        }
    }
    return $url;
}

/**
 *Generates a DDP link.
 *Takes in the path, label, otherArgs & title.
 *Examples in /php/TOR/index_inc.php.
 *
 *@author Patrick O Connor
 *
 *@param string $path This is the path to the php file.
 *@param string $label This is the label to appear on the link.
 *@param array $otherArgs These are other arguments that will be appended to the link after $webargs.
 *@param string $title This is used for the hover over text on the link.
 *
 */
function makeLink($path, $label, $otherArgs=null, $title=null) {
    return makeLinkForURL(makeURL($path, $otherArgs), $label, $title);
}

/**
 *Generates a DDP link.
 *Takes in the url ,label & title
 *
 *@param string $url The URL
 *@param string $label This is the label to appear on the link.
 *@param string $title This is used for the hover over text on the link.
 *@return string The content of the hyperlink
 */
function makeLinkForURL($url, $label, $title=null) {
    if ( ! is_null($title) ) {
        $link = "<a title='$title' ";
        return $link . "href='$url'>$label</a>";
    }
    return AHREF . $url . "\">$label</a>";
}

/**
 *Generates a link to the page being executed including the webargs
 *
 */
function makeSelfLink() {
    global $webargs;
    return fromServer(PHP_SELF) . '?' . $webargs;
}



/* Other Functions */

/**
 *Adds one or more line breaks onto a php page(Will add one by default)
 *
 *@author Patrick O Connor
 *
 *@param int $lines number of lines to add.
 *
**/
function addLineBreak( $lines = 1 ) {
    $break = "";
    for ($i = 1; $i <= $lines; $i++) {
        $break .= "<BR>";
    }

    return $break .= "\n"; //NOSONAR
}

/**
 *Generates a HTML List.
 *Takes in an array of items and adds each item to a HTML list.
 *If the list is to be ordered a specific way it will need to be applied to the array before calling makeHTMLList.
 *Example in /php/TOR/index_inc.php.
 *
 *@author Patrick O Connor
 *
 *@param array $items This is the list of items to be added to the HTML list
 *
 */
function makeHTMLList( $items ) {
    $list = "<ul>\n";
    foreach ($items as $item) {
        $list .= " " . makeHTMLListItem($item) . "\n";
    }
    return $list .= END_LIST; //NOSONAR
}

/**
 *Generates a item in a HTML List.
 *
 * @param string $item This is the item
 *
 */
function makeHTMLListItem( $item ) {
    return "<li>$item</li>";
}

