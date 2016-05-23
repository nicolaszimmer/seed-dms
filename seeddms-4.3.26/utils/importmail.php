<?php
include("../inc/inc.ClassSettings.php");

function usage() { /* {{{ */
	echo "Usage:\n";
	echo "  seeddms-importmail [--config <file>] [-h] [-v] -F <folder id> -d <dirname>\n";
	echo "\n";
	echo "Description:\n";
	echo "  This program accesses a mail box via imap and imports all mail attachments.\n";
	echo "\n";
	echo "Options:\n";
	echo "  -h, --help: print usage information and exit.\n";
	echo "  -v, --version: print version and exit.\n";
	echo "  --config: set alternative config file.\n";
	echo "  -F <folder id>: id of folder the file is uploaded to\n";
	echo "  -d <dirname>: upload this directory\n";
} /* }}} */

$version = "0.0.1";
$shortoptions = "d:F:p:hv";
$longoptions = array('help', 'version', 'config:', 'password:');
if(false === ($options = getopt($shortoptions, $longoptions))) {
	usage();
	exit(0);
}

/* Print help and exit */
if(!$options || isset($options['h']) || isset($options['help'])) {
	usage();
	exit(0);
}

/* Print version and exit */
if(isset($options['v']) || isset($options['verѕion'])) {
	echo $version."\n";
	exit(0);
}

/* Set alternative config file */
if(isset($options['config'])) {
	$settings = new Settings($options['config']);
} else {
	$settings = new Settings();
}

/* Set alternative config file */
if(isset($options['p']) || isset($options['password'])) {
	$password = isset($options['p']) ? $options['p'] : (isset($options['password']) ? $options['password'] : '');
	if($password == '-') {
		$oldStyle = shell_exec('stty -g');
		echo "Please enter password: ";
		shell_exec('stty -echo');
		$line = fgets(STDIN);
		$password = rtrim($line);
		shell_exec('stty ' . $oldStyle);
		echo "\n";
	}
} else {
	$password = '';
}

if(isset($settings->_extraPath))
	ini_set('include_path', $settings->_extraPath. PATH_SEPARATOR .ini_get('include_path'));

require_once("SeedDMS/Core.php");

if(isset($options['F'])) {
	$folderid = (int) $options['F'];
} else {
	echo "Missing folder ID\n";
	usage();
	exit(1);
}

$db = new SeedDMS_Core_DatabaseAccess($settings->_dbDriver, $settings->_dbHostname, $settings->_dbUser, $settings->_dbPass, $settings->_dbDatabase);
$db->connect() or die ("Could not connect to db-server \"" . $settings->_dbHostname . "\"");
$db->_debug = 1;


$dms = new SeedDMS_Core_DMS($db, $settings->_contentDir.$settings->_contentOffsetDir);
if(!$dms->checkVersion()) {
	echo "Database update needed.";
	exit;
}

$dms->setRootFolderID($settings->_rootFolderID);
$dms->setMaxDirID($settings->_maxDirID);
$dms->setEnableConverting($settings->_enableConverting);
$dms->setViewOnlineFileTypes($settings->_viewOnlineFileTypes);

/* Create a global user object */
$user = $dms->getUser(1);

$folder = $dms->getFolder($folderid);
if (!is_object($folder)) {
	echo "Could not find specified folder\n";
	exit(1);
}

if ($folder->getAccessMode($user) < M_READWRITE) {
	echo "Not sufficient access rights\n";
	exit(1);
}

function getpart($mbox,$mid,$p,$partno) {
	// $partno = '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
	global $htmlmsg,$plainmsg,$charset,$attachments;

	// DECODE DATA
	$data = ($partno)?
		imap_fetchbody($mbox,$mid,$partno):  // multipart
		imap_body($mbox,$mid);  // simple
	// Any part may be encoded, even plain text messages, so check everything.
	if ($p->encoding==4)
		$data = quoted_printable_decode($data);
	elseif ($p->encoding==3)
		$data = base64_decode($data);

	// PARAMETERS
	// get all parameters, like charset, filenames of attachments, etc.
	$params = array();
	if (isset($p->parameters))
		foreach ($p->parameters as $x)
			$params[strtolower($x->attribute)] = $x->value;
	if (isset($p->dparameters))
		foreach ($p->dparameters as $x)
			$params[strtolower($x->attribute)] = $x->value;

	// ATTACHMENT
	// Any part with a filename is an attachment,
	// so an attached text file (type 0) is not mistaken as the message.
	if (isset($params['filename']) || isset($params['name'])) {
		// filename may be given as 'Filename' or 'Name' or both
		$filename = (!empty($params['filename'])) ? $params['filename'] : $params['name'];
		// filename may be encoded, so see imap_mime_header_decode()
		$attachments[$filename] = $data;  // this is a problem if two files have same name
	}

	// TEXT
	if ($p->type==0 && $data) {
		// Messages may be split in different parts because of inline attachments,
		// so append parts together with blank row.
		if (strtolower($p->subtype)=='plain')
			$plainmsg .= trim($data) ."\n\n";
		else
			$htmlmsg .= $data ."<br><br>";
		$charset = $params['charset'];  // assume all parts are same charset
	}

	// EMBEDDED MESSAGE
	// Many bounce notifications embed the original message as type 2,
	// but AOL uses type 1 (multipart), which is not handled here.
	// There are no PHP functions to parse embedded messages,
	// so this just appends the raw source to the main message.
	elseif ($p->type==2 && $data) {
		$plainmsg .= $data."\n\n";
	}

	// SUBPART RECURSION
	if (isset($p->parts)) {
		foreach ($p->parts as $partno0=>$p2)
			getpart($mbox,$mid,$p2,$partno.'.'.($partno0+1));  // 1.2, 1.2.1, etc.
	}
}

function import_mail($urn, $user, $password) {
	global $user;
	global $charset,$htmlmsg,$plainmsg,$attachments;

	$htmlmsg = $plainmsg = $charset = '';
	$attachments = array();

	$mbox = imap_open($urn, $user, $password);

	$n_msgs = imap_num_msg($mbox);
	echo "Processing ".$n_msgs." mails\n";
	$headers = imap_headers($mbox);
	if($headers) {
//		print_r($headers);

		for ($mid=1; $mid<=$n_msgs; $mid++) {
			$header = imap_header($mbox, $mid);
			echo "Reading msg ".$header->message_id."\n";
			echo " Subject: ".$header->subject."\n";
			echo " Date: ".$header->date."\n";
			echo " From: ".$header->fromaddress."\n";
//			print_r($header);
			$s = imap_fetchstructure($mbox,$mid);
			print_r($s);
			if (!$s->parts)  // simple
				getpart($mbox,$mid,$s,0);  // pass 0 as part-number
			else {  // multipart: cycle through each part
				foreach ($s->parts as $partno0=>$p)
					getpart($mbox,$mid,$p,$partno0+1);
			}
			foreach($attachments as $name=>$data) {
				echo $name."\n";
			}
		}
	}

	imap_close($mbox);

/*
	$d = dir($dirname);
	$sequence = 1;
	while(false !== ($entry = $d->read())) {
		$path = $dirname.'/'.$entry;
		if($entry != '.' && $entry != '..' && $entry != '.svn') {
			if(is_file($path)) {
				$name = basename($path);
				$filetmp = $path;

				$reviewers = array();
				$approvers = array();
				$comment = '';
				$version_comment = '';
				$reqversion = 1;
				$expires = false;
				$keywords = '';
				$categories = array();

				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mimetype = finfo_file($finfo, $path);
				$lastDotIndex = strrpos($path, ".");
				if (is_bool($lastDotIndex) && !$lastDotIndex) $filetype = ".";
				else $filetype = substr($path, $lastDotIndex);

				echo $mimetype." - ".$filetype." - ".$path."\n";
				$res = $folder->addDocument($name, $comment, $expires, $user, $keywords,
																		$categories, $filetmp, $name,
																		$filetype, $mimetype, $sequence, $reviewers,
																		$approvers, $reqversion, $version_comment);

				if (is_bool($res) && !$res) {
					echo "Could not add document to folder\n";
					exit(1);
				}
				set_time_limit(1200);
			} elseif(is_dir($path)) {
				$name = basename($path);
				$newfolder = $folder->addSubFolder($name, '', $user, $sequence);
				import_folder($path, $newfolder);
			}
			$sequence++;
		}
	}
*/

}

$host = 'mail.mmk-hagen.de';
$port = '993';
$ssl = 'ssl/novalidate-cert';
$folder = 'seeddms';
$urn = "{"."$host:$port/imap/$ssl"."}$folder";
$user = 'steinm';

//echo $urn;

import_mail($urn, $user, $password);

