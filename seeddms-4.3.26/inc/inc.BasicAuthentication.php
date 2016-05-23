<?php
/**
 * Do authentication of users and session management
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Uwe Steinmann
 * @version    Release: @package_version@
 */

require_once("inc.Utils.php");
require_once("inc.ClassNotificationService.php");
require_once("inc.ClassEmailNotify.php");
require_once("inc.ClassSession.php");

function __authenticate($username, $password) { /* {{{ */
	global $dms, $settings;

	$user = $dms->getUserByLogin($username);
	if ($user) {
		$userid = $user->getID();

		if (($userid == $settings->_guestID) && (!$settings->_enableGuestLogin)) {
			return false;
		}

		if (($userid != $settings->_guestID) && (md5($password) != $user->getPwd())) {
			/* if counting of login failures is turned on, then increment its value */
			if($settings->_loginFailure) {
				$failures = $user->addLoginFailure();
				if($failures >= $settings->_loginFailure)
					$user->setDisabled(true);
			}
			return false;
		}

		// Check if account is disabled
		if($user->isDisabled()) {
			return false;
		}

		// control admin IP address if required
		// TODO: extend control to LDAP autentication
		if ($user->isAdmin() && ($_SERVER['REMOTE_ADDR'] != $settings->_adminIP ) && ( $settings->_adminIP != "") ){
			return false;
		}
		return $user;
	} else {
		return false;
	}
} /* }}} */

if (!isset($_SERVER['PHP_AUTH_USER'])) {
	header('WWW-Authenticate: Basic realm="'.$settings->_siteName.'"');
	header('HTTP/1.0 401 Unauthorized');
	echo getMLText('cancel_basic_authentication');
	exit;
} else {
	if(!($user = __authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']))) {
		header('WWW-Authenticate: Basic realm="'.$settings->_siteName.'"');
		header('HTTP/1.0 401 Unauthorized');
		echo getMLText('cancel_basic_authentication');
		exit;
	}
}

/* Clear login failures if login was successful */
$user->clearLoginFailures();

$dms->setUser($user);
$notifier = new SeedDMS_NotificationService();
if($settings->_enableEmail) {
	$notifier->addService(new SeedDMS_EmailNotify($dms));
}

