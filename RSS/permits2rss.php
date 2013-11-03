<?php
/**
  * permits2rss.php - A PHP script to create an RSS feed of City of Charlottetown Building Permit summaries.
  *
  * The City of Charlottetown, Prince Edward Island, Canada, produces weekly PDF summaries
  * of building permits issues, indexed on their website at:
  *
  * 	http://www.city.charlottetown.pe.ca/buildingpermitapproval.php
  *
  * The URLs of the individual PDF files look like this:
  *
  *		http://www.city.charlottetown.pe.ca/pdfs/permits/Weekly_approvals_webpage_21_Oct_2011.pdf
  *
  * Each filename begins with the string 'Weekly_approvals_webpage_'.
  *
  * This script scrapes the index page, compares the URLs containing that string
  * to a MySQL table that caches previously scraped URLs, adds any new URLs the table,
  * and generates an RSS feed.
  * 
  * The cache of previously indexed permits is stored in a MySQL table with this structure:
  *
  * 	CREATE TABLE `permitcache` (
  *			`number` int(11) NOT NULL auto_increment,
  *			`dateadded` datetime NOT NULL default '0000-00-00 00:00:00',
  *			`url` text NOT NULL,
  *			`heading` varchar(50) NOT NULL default '',
  *			`filesize` int(11) NOT NULL default '0',
  *			UNIQUE KEY `numberdex` (`number`)
  *		) 
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
  * @version 0.6, November 3, 2013
  * @link http://ruk.ca/wiki/Charlottetown_Building_Permits_in_RSS
  * @author Peter Rukavina <peter@rukavina.net>
  * @copyright Copyright &copy; 2011, Reinvented Inc.
  * @license http://www.fsf.org/licensing/licenses/gpl.txt GNU Public License
  */

/**
  * User configurable options.
  */

require_once("permits2rss-settings.php");

/**
  * Include HTML parsing functionality.
  * We depend on the presence of the XML_HTMLSax PEAR package for PHP ({@link http://pear.php.net/package/XML_HTMLSax}).
  * You can probably install this like this:
  *
  * pear install XML_HTMLSax
  *
  */
require_once('XML/XML_HTMLSax.php');

/**
  * Include RSS feed creation functionality.
  * We depend on the presence of FeedCreator.class.php for PHP ({@link http://www.bitfolge.de/rsscreator-en.html}).
  */
require_once("feedcreator.class.php"); 

// Connect to the MySQL server
MYSQL_CONNECT($db_host,$db_user,$db_pw);
MYSQL_SELECT_DB($db) or die( "Unable to select database");

//Select previously indexed URLs from the cache table, and put them into an array $oneurl
$query = "select url from $db_table";
$result = MYSQL_QUERY($query);
$howmanyrecords = MYSQL_NUM_ROWS($result);
$currentrecord = 0;

while ($currentrecord < $howmanyrecords) {
 $url = trim(MYSQL_RESULT($result,$currentrecord,"url"));
 $oneurl[$url] = 1;
 $currentrecord++;
}

date_default_timezone_set('America/Halifax');

/**
  * Handlers for HTML parsing.
  */
class MyHandler {
    function MyHandler(){}

	/**
	  * Handler for opening tags.
	  * Whenever we encounter an 'opening' tag (like <a href=...>), the handler is called by the parser.
	  * We look to see if the tag has an 'href' attribute, and if that attribute contains the string
	  * common to all URLs for weekly building permits, and, if so, we insert it into the cache if it
	  * wasn't there before.
	  */
    function openHandler(& $parser,$name,$attrs) {
    	global $oneurl,$db_table;
    	
    	$match_strings = array("Bldgprmit","bldpermit","bldngprmt","Week_Ending_","Week Ending ","bldgprmt","WPA","Weekly_approvals_webpage_","weekly_permits_approved_","Weekly_approvals_","Weeklyapprovals","Weekly-approvals-webpage-");
    	
        foreach($attrs as $key => $value) {

            $did_it_match = 0;

            if ($key == "href") {
                foreach($match_strings as $key2 => $value2) {
        			if (preg_match("/$value2/",$value)) {
        			    $did_it_match = 1;
        			    $matched_string = $value2;
        			    break;
        			}
        		}
            }

			if ($did_it_match) {
				if (!$oneurl[$value]) {
					print $value . "\n";
					$heading = str_replace("pdfs/permits/" . $matched_string,"",$value);
					$heading = str_replace("pdfs2013/permits/" . $matched_string,"",$value);
					$heading = str_replace(".pdf","",$heading);
					$heading = str_replace("_"," ",$heading);

                    $fp = fopen("http://www.city.charlottetown.pe.ca/" . $value, 'r');
                    $meta = stream_get_meta_data($fp);
                    $size = 0;
                    for ($j = 0; isset($meta['wrapper_data'][$j]); $j++) {
                        if (strstr(strtolower($meta['wrapper_data'][$j]), 'content-length')) {
                          $size = substr($meta['wrapper_data'][$j], 15);
                          break;
                        }
                                     }
                    fclose($fp);
					
					list($dateadded,$heading) = MangleDate($heading);
					
                    $query = "INSERT into $db_table (dateadded,url,heading,filesize) values (
                             '$dateadded',
                             '$value',
                             '$heading',
                             '$size')";
                    
                    $result = MYSQL_QUERY($query);
				}
			}
		}
    }
	/**
	  * Handler for closing tags.
	  * Whenever we encounter an 'closing' tag (like </a>), the handler is called by the parser.
	  * We don't need this handler to do *anything*.
	  */
    function closeHandler(& $parser,$name) {
    }
}

// Grab the entire contents of the URL containing the permits index into a variable we can parse.
$handle = fopen($permitsurl,'rb');
$html = stream_get_contents($handle);
fclose($handle);

// Instantiate the handler
$handler=new MyHandler();

// Instantiate the parser
$parser=& new XML_HTMLSax();

// Register the handler with the parser
$parser->set_object($handler);

// Set a parser option
$parser->set_option('XML_OPTION_TRIM_DATA_NODES');

// Set the handlers
$parser->set_element_handler('openHandler','closeHandler');

// Parse the document
$parser->parse($html);

// Begin creation of the RSS feed.

$rss = new UniversalFeedCreator(); 
$rss->title 		= $blogtitle;
$rss->description 	= $blogexp;
$rss->link 			= $blogurl;
$rss->syndicationURL= $blogrss;
$rss->copyright     = strftime("%Y") . " " . $blogauthor;
	
// Grab the most recent 100 of the cached URLs (i.e. building permit summaries) from the MySQL table.	
$query = "SELECT url,heading,dateadded,filesize from permitcache order by dateadded DESC limit 10";

$result = MYSQL_QUERY($query);
$howmanyrecords = MYSQL_NUMROWS($result);

$currentrecord = 0 ;
	
while ($currentrecord < $howmanyrecords) {
	$url = mysql_result($result,$currentrecord,"url");
	$heading = mysql_result($result,$currentrecord,"heading");
	$dateadded = mysql_result($result,$currentrecord,"dateadded");
	$filesize = mysql_result($result,$currentrecord,"filesize");

	// Parse apart the date into its component parts.
	list($date,$time) = explode(" ",$dateadded);
	list($year,$month,$day) = explode("-",$date);
	list($hour,$minute,$second) = explode(":",$time);
	$date_number = mktime($hour,$minute,$second,$month,$day,$year);

	// Add a channel item to the RSS feed.
	$item = new FeedItem(); 
	$item->title 		= $heading . " Building Permit Summary";
	$item->link 		= "http://www.city.charlottetown.pe.ca/" . $url;
	$item->description 	= "<a href=\"http://www.city.charlottetown.pe.ca/$url\">$heading Building Permit Summary</a>";
	$item->date 		= strftime("%Y-%m-%dT%H:%M:%S%z",$date_number);
	$item->source 		= "http://www.city.charlottetown.pe.ca/residents/application_fees.cfm"; 
	$item->guid         = "http://www.city.charlottetown.pe.ca/" . $url; 
	$item->addEnclosure("http://www.city.charlottetown.pe.ca/" . $url, $filesize, "text/pdf");
	$item->author 		= "peter@rukavina.net (Peter Rukavina)"; 
	
	$rss->addItem($item); 
	$currentrecord++;
}

// Save the RSS feed.  This should be a web-accessible location.
$rss->saveFeed("RSS2.0", $rssfile, FALSE); 

/**
  * Parse apart the part of the filename that contains the date, and make it standard.
  * There is inconsistency in the date format used in URLs.  Examples of formats used:
  * 
  * May 28 05
  * August 27 2005
  * Feb 4 2006
  *
  * The format is always Month - space - day - space - year, but the month can 
  * be abbreviated or spelled out in full, and the year can be two- or four-digit.
  */
function MangleDate($target) {
    
    print $target . "\n";
    
    $target = str_replace("-08",", 2008",$target);
    $target = str_replace("-09",", 2009",$target);
    
    $datehacks = array("05-17-08" => "2008-05-17",
                    "May2408" => "2008-05-24",
                    "07Dec15" => "2007-12-15",
                    "dec2207" => "2007-12-22",
                    "08-Jan-19" => "2008-01-19",
                    "July14-07" => "2007-07-14",
                    "July21-07" => "2007-07-21",
                    "Sept107v2" => "2007-09-10",
                    "Sept807" => "2007-09-08",
                    "sept2207" => "2007-09-22",
                    "07-Oct-27" => "2007-10-27",
                    "07-Nov-17" => "2007-11-17",
                    "Apr 5th-09" => "2009-04-05",
                    "05-10, 2008" => "2008-05-10",
                    "05-17, 2008" => "2008-05-17",
                    "080112" => "2008-01-12",
                    "07-Dec, 2008" => "2008-12-07",
                    "07-Nov-03" => "2007-11-03",
                    "07-Nov-10" => "2007-11-10");

    if ($datehacks[$target]) {
        list($year,$monthnum,$day) = explode("-",$datehacks[$target]);
    }
    else {
        $magic = strtotime($target);
        if ($magic) {
            list($year,$monthnum,$day) = explode("-",strftime("%Y-%m-%d",$magic));
        }
        else {
        	list($day,$month,$year) = explode(" ",$target);
        	if (strlen($year) == 2) {
        		$year = "20" . $year;
        	}
	
        	for ($i = 1 ; $i <= 12 ; $i++) {
        		$longmonth = strftime("%B",mktime(0,0,0,$i,1,2000));
        		$shortmonth = strftime("%b",mktime(0,0,0,$i,1,2000));
        		if ($longmonth == $month) {
        			$monthnum = $i;
        		}
        		else if ($shortmonth == $month) {
        			$monthnum = $i;
        		}
        	}
    	}
	}
	print "$monthnum-$day-$year\n";
	
	// Return a two-element array, like ('2006-01-01','January 1, 2006')
	return array("$year-$monthnum-$day",strftime("%B %e, %Y",mktime(0,0,0,$monthnum,$day,$year)));
}
