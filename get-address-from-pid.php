<?php
/**
  * get-address-from-pid.php - A PHP script to retrieve civic address details for a given 
  * Prince Edward Island Property Identification Number (PID).
  *
  * Created in support of an effort to add value to Charlottetown Building Permits released
  * as open government data. 
  *
  * Requires XML_Serializer -- install with 'pear install XML_Serializer'.
  *
  * Pass this script a PID, like 344523, like this:
  *
  * php get-address-from-pid.php 344523
  *
  * And it should return something like this:
  *
  * <?xml version="1.0" encoding="UTF-8"?>
  * <addresses>
  *     <address>
  *         <county>QUN</county>
  *         <apt_no />
  *         <comm_nm>CHARLOTTETOWN</comm_nm>
  *         <street_no>170</street_no>
  *         <street_nm>FITZROY ST</street_nm>
  *         <govurl>http://www.gov.pe.ca/civicaddress/locator/details.php3?county=QUN&amp;street_no=170&amp;street_nm=FITZROY+ST&amp;comm_nm=CHARLOTTETOWN</govurl>
  *         <latitude>46.23855</latitude>
  *         <longitude>-63.12609</longitude>
  *         <googlemapsurl>http://maps.google.com/maps?q=46.23855,+-63.12609</googlemapsurl>
  *         <openstreetmapurl>http://www.openstreetmap.org/?mlat=46.23855&amp;mlon=-63.12609&amp;zoom=15</openstreetmapurl>
  *     </address>
  * </addresses>  
  *   
  * This program is free software; you can redistribute it and/or modify
  * it under the terms of the GNU General Public License as published by
  * the Free Software Foundation; either version 2 of the License, or (at
  * your option) any later version.
  *
  * This program is distributed in the hope that it will be useful, but
  * WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
  * General Public License for more details.
  * 
  * You should have received a copy of the GNU General Public License
  * along with this program; if not, write to the Free Software
  * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307
  * USA
  *
  * @version 0.1, March 18, 2011
  * @link https://github.com/reinvented/Charlottetown-Building-Permits
  * @author Peter Rukavina <peter@rukavina.net>
  * @copyright Copyright &copy; 2011, Reinvented Inc.
  * @license http://www.fsf.org/licensing/licenses/gpl.txt GNU Public License
  */

/**
  * If called as a web service, retrieve parameters from the URL, otherwise from the command line.
  * First parameter is the PID number, and is required.
  * Second parameter is the output format -- xml or json -- and is optional, with xml as the default.
  */

if ($_GET) {
  $pid = $_GET['pid'];
  $format = $_GET['format'];
}
else if ($argv[1]) {
  $pid = $argv[1];
  $format = $argv[2];
}
if (!$format) {
  $format = "xml";
}

/**
  * Return an error if no PID parameter.
  */
if (!$pid) {
    $result = array("error" => "You must pass a numeric Property Identification Number as the 'pid' parameter");
}
else {

  /**
    * Return an error if PID parameter is not numeric.
    */
  if (!is_numeric($pid)) {
    $result = array("error" => "You must pass a numeric Property Identification Number as the 'pid' parameter. Non-numeric value received.");
  }
  else {
  
    /**
      * This is the Government of PEI's "Geolinc" service, which returns URLs encoded with address data when passed a PID.
      * We call this with the PID as a parameter, and screen scrape out URLs that have the relevant civic address
      * data encoded into them. Note that more than one civic address may be associated with a given PID.
      */
    $permitsurl = "http://eservices.gov.pe.ca/pei-icis/address-locator/findCivicAddress.do?parcelNumber=$pid";
    $handle = fopen($permitsurl,'rb');
    $html = stream_get_contents($handle);
    fclose($handle);

    /**
      * The URLs we're trying to scrape out look like this:
      * <a href="/pei-icis/address-locator/map/map-applet.jsp?county=QUN&amp;apartmentNumber=1%252F2&amp;communityName=CHARLOTTETOWN&amp;streetNumber=316&amp;streetName=QUEEN%2BST">View Map</a>
      */
    preg_match_all("/county=(\D\D\D)&amp;apartmentNumber=(.*)&amp;communityName=(.*)&amp;streetNumber=(.*)&amp;streetName=(.*)\"/",$html,$matches,PREG_SET_ORDER);

    /**
      * Loop through the matches, and stuff the results in an array.
      */
    foreach($matches as $m) {
      $a = array();
      $a['county'] = $m[1];
      $a['apt_no'] = urldecode(urldecode($m[2]));
      $a['comm_nm'] = urldecode(urldecode($m[3]));
      $a['street_no'] = urldecode(urldecode($m[4]));
      $a['street_nm'] = urldecode(urldecode($m[5]));

      /**
        * This is the Government of PEI's "Address Locator" service, which returns lots of information about
        * a given civic address, including, helpfully for our purposes, the latitude and longitude.
        */
      $a['govurl'] = "http://www.gov.pe.ca/civicaddress/locator/details.php3?county=" . urlencode($a['county']) . "&street_no=" . urlencode($a['street_no']) . "&street_nm=" . urlencode($a['street_nm']) . "&comm_nm=" . urlencode($a['comm_nm']);
      $handle = fopen($a['govurl'],'rb');
      $html = stream_get_contents($handle);
      fclose($handle);

      /**
        * More screen scraping, this time looking for the latitude and longitude:
        * <B>N 46<SUP>o</SUP> 14' 80"</B> <FONT SIZE=2>(46.23545 decimal)</FONT><BR>
        * <B>W 63<SUP>o</SUP> 70' 45"</B> <FONT SIZE=2>(-63.12909 decimal)</FONT><BR>
        */
      preg_match("/<FONT SIZE=2>\((\d\d\.\d\d\d\d\d) decimal/",$html,$lat);
      $a['latitude'] = $lat[1];
      preg_match("/<FONT SIZE=2>\((-\d\d\.\d\d\d\d\d) decimal/",$html,$lon);
      $a['longitude'] = $lon[1];

      /**
        * Make some handy links from the latitude and longitude.
        */
      $a['googlemapsurl'] = "http://maps.google.com/maps?q=" . $a['latitude'] . ",+" . $a['longitude'];
      $a['openstreetmapurl'] = "http://www.openstreetmap.org/?mlat=" . $a['latitude'] . "&mlon=" . $a['longitude'] . "&zoom=15";

      $addresses[] = $a;
    }
    $result = $addresses;
  }
}

/**
  * If the format is XML, then use the PEAR XML_Serializer to turn our result array into XML.
  * Otherwise output it as JSON (using PHP's built-in JSON juju).
  */
if ($format == "xml") {
  require_once("XML/Serializer.php");

  $options = array(
    "indent"          => "    ",
    "linebreak"       => "\n",
    "typeHints"       => false,
    "addDecl"         => true,
    "encoding"        => "UTF-8",
    "rootName"        => "addresses",
    "defaultTagName"  => "address",
    "attributesArray" => "_attributes"
    );

  $serializer = new XML_Serializer($options);
  $result = $serializer->serialize($result);
  if($result === true) {
   print $serializer->getSerializedData();
  }
}
else {
  print json_encode($result);
}
