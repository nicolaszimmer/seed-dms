<?php
/**
 * Implementation of Feed view
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C)2016 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

require_once("FeedWriter/Item.php");
require_once("FeedWriter/Feed.php");
require_once("FeedWriter/RSS2.php");

use \FeedWriter\RSS2;

/**
 * Class which outputs the html page for UserList view
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C)2016 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_TimelineFeed extends SeedDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$httproot = $this->params['httproot'];
		$skip = $this->params['skip'];
		$fromdate = $this->params['fromdate'];
		$todate = $this->params['todate'];
		$cachedir = $this->params['cachedir'];
		$sitename = $this->params['sitename'];
		$previewwidthlist = $this->params['previewWidthList'];
		$previewwidthdetail = $this->params['previewWidthDetail'];
		$timeout = $this->params['timeout'];

		if($fromdate) {
			$from = makeTsFromLongDate($fromdate.' 00:00:00');
		} else {
			$from = time()-7*86400;
		}

		if($todate) {
			$to = makeTsFromLongDate($todate.' 23:59:59');
		} else {
			$to = time();
		}

		if($data = $dms->getTimeline($from, $to)) {
			$baseurl = "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$httproot;
			$feed = new RSS2;
			$feed->setTitle($sitename.': Recent Changes');
			$feed->setLink($baseurl);
			$feed->setDescription('Show recent changes in SeedDMS.');
			// Image title and link must match with the 'title' and 'link' channel elements for RSS 2.0,
			// which were set above.
	//		$feed->setImage('Testing & Checking the Feed Writer project', 'https://github.com/mibe/FeedWriter', 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d9/Rss-feed.svg/256px-Rss-feed.svg.png');
			// Use core setChannelElement() function for other optional channel elements.
			// See http://www.rssboard.org/rss-specification#optionalChannelElements
			// for other optional channel elements. Here the language code for American English and
			$feed->setChannelElement('language', 'en-US');
			// The date when this feed was lastly updated. The publication date is also set.
			$feed->setDate(date(DATE_RSS, time()));
			$feed->setChannelElement('pubDate', date(\DATE_RSS, strtotime('2013-04-06')));
			// You can add additional link elements, e.g. to a PubSubHubbub server with custom relations.
			// It's recommended to provide a backlink to the feed URL.
			$feed->setSelfLink($baseurl.'out/out.Feed.php');
	//		$feed->setAtomLink('http://pubsubhubbub.appspot.com', 'hub');
			// You can add more XML namespaces for more custom channel elements which are not defined
			// in the RSS 2 specification. Here the 'creativeCommons' element is used. There are much more
			// available. Have a look at this list: http://feedvalidator.org/docs/howto/declare_namespaces.html
	//		$feed->addNamespace('creativeCommons', 'http://backend.userland.com/creativeCommonsRssModule');
	//		$feed->setChannelElement('creativeCommons:license', 'http://www.creativecommons.org/licenses/by/1.0');
			// If you want you can also add a line to publicly announce that you used
			// this fine piece of software to generate the feed. ;-)
	//		$feed->addGenerator();

			foreach($data as $i=>$item) {
				switch($item['type']) {
				case 'add_version':
					$msg = getMLText('timeline_'.$item['type']);
					break;
				case 'add_file':
					$msg = getMLText('timeline_'.$item['type']);
					break;
				case 'status_change':
					$msg = getMLText('timeline_'.$item['type'], array('version'=> $item['version'], 'status'=> getOverallStatusText($item['status'])));
					break;
				default:
					$msg = '???';
				}
				$data[$i]['msg'] = $msg;
			}
		}

		$previewer = new SeedDMS_Preview_Previewer($cachedir, $previewwidthdetail, $timeout);
		foreach($data as $item) {
			if($item['type'] == 'status_change')
				$classname = $item['type']."_".$item['status'];
			else
				$classname = $item['type'];
			if(!$skip || !in_array($classname, $skip)) {
				$doc = $item['document'];
				$owner = $doc->getOwner();
				$version = $doc->getContentByVersion($item['version']);
				$previewer->createPreview($version);
				$d = makeTsFromLongDate($item['date']);
				$newItem = $feed->createNewItem();
				$newItem->setTitle($doc->getName()." (".$item['msg'].")");
				$newItem->setLink($baseurl.'out/out.ViewDocument.php?documentid='.$doc->getID());
				$newItem->setDescription("<h2>".$item['msg']."</h2>".
					"<p>".getMLText('comment').": <b>".$doc->getComment()."</b></p>".
					"<p>".getMLText('owner').": <b><a href=\"mailto:".$owner->getEmail()."\">".$owner->getFullName()."</a></b></p>".
					"<p>".getMLText("creation_date").": <b>".getLongReadableDate($doc->getDate())."</p>"
				);
				$newItem->setDate(date('c', $d));
				$newItem->setAuthor($owner->getFullName(), $owner->getEmail());
				$newItem->setId('out/out.ViewDocument.php?documentid='.$doc->getID(), true);
				if($previewer->hasPreview($version)) {
					$newItem->addElement('enclosure', null, array('url' => $baseurl.'op/op.Preview.php?documentid='.$item['document']->getId().'&version='.$version->getVersion().'&width='.$previewwidthdetail, 'length'=>$previewer->getFileSize($version), 'type'=>'image/png'));
				}
				$feed->addItem($newItem);
			}
		}

		// OK. Everything is done. Now generate the feed.
		// If you want to send the feed directly to the browser, use the printFeed() method.
		$myFeed = $feed->generateFeed();
		// Do anything you want with the feed in $myFeed. Why not send it to the browser? ;-)
		// You could also save it to a file if you don't want to invoke your script every time.
		header('Content-Type: application/rss+xml');
		echo $myFeed;
	} /* }}} */
}
