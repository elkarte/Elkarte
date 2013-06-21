<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file is concerned pretty entirely, as you see from its name, with
 * logging in and out members, and the validation of that.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class Auth_Controller
{
	/**
	 * Ask them for their login information. (shows a page for the user to type
	 *  in their username and password.)
	 *  It caches the referring URL in $_SESSION['login_url'].
	 *  It is accessed from ?action=login.
	 *  @uses Login template and language file with the login sub-template.
	 *  @uses the protocol_login sub-template in the Wireless template,
	 *   if you are using a wireless device
	 */
	public function action_login()
	{
		global $txt, $context, $scripturl, $user_info;

		// You are already logged in, go take a tour of the boards
		if (!empty($user_info['id']))
			redirectexit();

		// Load the Login template/language file.
		loadLanguage('Login');
		loadTemplate('Login');
		$context['sub_template'] = 'login';

		// Get the template ready.... not really much else to do.
		$context['page_title'] = $txt['login'];
		$context['default_username'] = &$_REQUEST['u'];
		$context['default_password'] = '';
		$context['never_expire'] = false;

		// Add the login chain to the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=login',
			'name' => $txt['login'],
		);

		// Set the login URL - will be used when the login process is done (but careful not to send us to an attachment).
		if (isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0)
			$_SESSION['login_url'] = $_SESSION['old_url'];
		else
			unset($_SESSION['login_url']);

		// Create a one time token.
		createToken('login');
	}

	/**
	 * Actually logs you in.
	 * What it does:
	 * - checks credentials and checks that login was successful.
	 * - it employs protection against a specific IP or user trying to brute force
	 *  a login to an account.
	 * - upgrades password encryption on login, if necessary.
	 * - after successful login, redirects you to $_SESSION['login_url'].
	 * - accessed from ?action=login2, by forms.
	 * On error, uses the same templates action_login() uses.
	 */
	public function action_login2()
	{
		global $txt, $scripturl, $user_info, $user_settings;
		global $cookiename, $maintenance, $modSettings, $context, $sc;

		// Load cookie authentication and all stuff.
		require_once(SUBSDIR . '/Auth.subs.php');

		// Beyond this point you are assumed to be a guest trying to login.
		if (!$user_info['is_guest'])
			redirectexit();

		// Are you guessing with a script?
		checkSession('post');
		$tk = validateToken('login');
		spamProtection('login');

		// Set the login_url if it's not already set (but careful not to send us to an attachment).
		if ((empty($_SESSION['login_url']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0) || (isset($_GET['quicklogin']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'login') === false))
			$_SESSION['login_url'] = $_SESSION['old_url'];

		// Been guessing a lot, haven't we?
		if (isset($_SESSION['failed_login']) && $_SESSION['failed_login'] >= $modSettings['failed_login_threshold'] * 3)
			fatal_lang_error('login_threshold_fail', 'critical');

		// Set up the cookie length.  (if it's invalid, just fall through and use the default.)
		if (isset($_POST['cookieneverexp']) || (!empty($_POST['cookielength']) && $_POST['cookielength'] == -1))
			$modSettings['cookieTime'] = 3153600;
		elseif (!empty($_POST['cookielength']) && ($_POST['cookielength'] >= 1 || $_POST['cookielength'] <= 525600))
			$modSettings['cookieTime'] = (int) $_POST['cookielength'];

		loadLanguage('Login');

		// Load the template stuff
		loadTemplate('Login');
		$context['sub_template'] = 'login';

		// Set up the default/fallback stuff.
		$context['default_username'] = isset($_POST['user']) ? preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($_POST['user'])) : '';
		$context['default_password'] = '';
		$context['never_expire'] = $modSettings['cookieTime'] == 525600 || $modSettings['cookieTime'] == 3153600;
		$context['login_errors'] = array($txt['error_occurred']);
		$context['page_title'] = $txt['login'];

		// Add the login chain to the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=login',
			'name' => $txt['login'],
		);

		if (!empty($_POST['openid_identifier']) && !empty($modSettings['enableOpenID']))
		{
			require_once(SUBSDIR . '/OpenID.subs.php');
			if (($open_id = openID_validate($_POST['openid_identifier'])) !== 'no_data')
				return $open_id;
		}

		// You forgot to type your username, dummy!
		if (!isset($_POST['user']) || $_POST['user'] == '')
		{
			$context['login_errors'] = array($txt['need_username']);
			return;
		}

		// Hmm... maybe 'admin' will login with no password. Uhh... NO!
		if ((!isset($_POST['passwrd']) || $_POST['passwrd'] == '') && (!isset($_POST['hash_passwrd']) || strlen($_POST['hash_passwrd']) != 40))
		{
			$context['login_errors'] = array($txt['no_password']);
			return;
		}

		// No funky symbols either.
		if (preg_match('~[<>&"\'=\\\]~', preg_replace('~(&#(\\d{1,7}|x[0-9a-fA-F]{1,6});)~', '', $_POST['user'])) != 0)
		{
			$context['login_errors'] = array($txt['error_invalid_characters_username']);
			return;
		}

		// Are we using any sort of integration to validate the login?
		if (in_array('retry', call_integration_hook('integrate_validate_login', array($_POST['user'], isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) == 40 ? $_POST['hash_passwrd'] : null, $modSettings['cookieTime'])), true))
		{
			$context['login_errors'] = array($txt['login_hash_error']);
			$context['disable_login_hashing'] = true;
			return;
		}

		// Find them... if we can
		$user_settings = loadExistingMember($_POST['user']);

		// Let them try again, it didn't match anything...
		if (empty($user_settings))
		{
			$context['login_errors'] = array($txt['username_no_exist']);
			return;
		}

		// Figure out the password using Elk's encryption - if what they typed is right.
		if (isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) == 40)
		{
			// Needs upgrading?
			if (strlen($user_settings['passwd']) != 40)
			{
				$context['login_errors'] = array($txt['login_hash_error']);
				$context['disable_login_hashing'] = true;
				unset($user_settings);
				return;
			}
			// Challenge passed.
			elseif ($_POST['hash_passwrd'] == sha1($user_settings['passwd'] . $sc . $tk))
				$sha_passwd = $user_settings['passwd'];
			else
			{
				// Don't allow this!
				validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood']);

				$_SESSION['failed_login'] = isset($_SESSION['failed_login']) ? ($_SESSION['failed_login'] + 1) : 1;

				if ($_SESSION['failed_login'] >= $modSettings['failed_login_threshold'])
					redirectexit('action=reminder');
				else
				{
					log_error($txt['incorrect_password'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', 'user');

					$context['disable_login_hashing'] = true;
					$context['login_errors'] = array($txt['incorrect_password']);
					unset($user_settings);
					return;
				}
			}
		}
		else
			$sha_passwd = sha1(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));

		// Bad password!  Thought you could fool the database?!
		if ($user_settings['passwd'] != $sha_passwd)
		{
			// Let's be cautious, no hacking please. thanx.
			validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood']);

			// Maybe we were too hasty... let's try some other authentication methods.
			$other_passwords = array();

			// None of the below cases will be used most of the time (because the salt is normally set.)
			if (!empty($modSettings['enable_password_conversion']) && $user_settings['password_salt'] == '')
			{
				// YaBB SE, Discus, MD5 (used a lot), SHA-1 (used some), SMF 1.0.x, IkonBoard, and none at all.
				$other_passwords[] = crypt($_POST['passwrd'], substr($_POST['passwrd'], 0, 2));
				$other_passwords[] = crypt($_POST['passwrd'], substr($user_settings['passwd'], 0, 2));
				$other_passwords[] = md5($_POST['passwrd']);
				$other_passwords[] = sha1($_POST['passwrd']);
				$other_passwords[] = md5_hmac($_POST['passwrd'], strtolower($user_settings['member_name']));
				$other_passwords[] = md5($_POST['passwrd'] . strtolower($user_settings['member_name']));
				$other_passwords[] = md5(md5($_POST['passwrd']));
				$other_passwords[] = $_POST['passwrd'];

				// This one is a strange one... MyPHP, crypt() on the MD5 hash.
				$other_passwords[] = crypt(md5($_POST['passwrd']), md5($_POST['passwrd']));

				// Snitz style - SHA-256.  Technically, this is a downgrade, but most PHP configurations don't support sha256 anyway.
				if (strlen($user_settings['passwd']) == 64 && function_exists('mhash') && defined('MHASH_SHA256'))
					$other_passwords[] = bin2hex(mhash(MHASH_SHA256, $_POST['passwrd']));

				// phpBB3 users new hashing.  We now support it as well ;).
				$other_passwords[] = phpBB3_password_check($_POST['passwrd'], $user_settings['passwd']);

				// APBoard 2 Login Method.
				$other_passwords[] = md5(crypt($_POST['passwrd'], 'CRYPT_MD5'));
			}
			// The hash should be 40 if it's SHA-1, so we're safe with more here too.
			elseif (!empty($modSettings['enable_password_conversion']) && strlen($user_settings['passwd']) == 32)
			{
				// vBulletin 3 style hashing?  Let's welcome them with open arms \o/.
				$other_passwords[] = md5(md5($_POST['passwrd']) . stripslashes($user_settings['password_salt']));

				// Hmm.. p'raps it's Invision 2 style?
				$other_passwords[] = md5(md5($user_settings['password_salt']) . md5($_POST['passwrd']));

				// Some common md5 ones.
				$other_passwords[] = md5($user_settings['password_salt'] . $_POST['passwrd']);
				$other_passwords[] = md5($_POST['passwrd'] . $user_settings['password_salt']);
			}
			elseif (strlen($user_settings['passwd']) == 40)
			{
				// Maybe they are using a hash from before the password fix.
				$other_passwords[] = sha1(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));

				// BurningBoard3 style of hashing.
				if (!empty($modSettings['enable_password_conversion']))
					$other_passwords[] = sha1($user_settings['password_salt'] . sha1($user_settings['password_salt'] . sha1($_POST['passwrd'])));

				// Perhaps we converted to UTF-8 and have a valid password being hashed differently.
				if (!empty($modSettings['previousCharacterSet']) && $modSettings['previousCharacterSet'] != 'utf8')
				{
					// Try iconv first, for no particular reason.
					if (function_exists('iconv'))
						$other_passwords['iconv'] = sha1(strtolower(iconv('UTF-8', $modSettings['previousCharacterSet'], $user_settings['member_name'])) . un_htmlspecialchars(iconv('UTF-8', $modSettings['previousCharacterSet'], $_POST['passwrd'])));

					// Say it aint so, iconv failed!
					if (empty($other_passwords['iconv']) && function_exists('mb_convert_encoding'))
						$other_passwords[] = sha1(strtolower(mb_convert_encoding($user_settings['member_name'], 'UTF-8', $modSettings['previousCharacterSet'])) . un_htmlspecialchars(mb_convert_encoding($_POST['passwrd'], 'UTF-8', $modSettings['previousCharacterSet'])));
				}
			}

			// ELKARTE's sha1 function can give a funny result on Linux (Not our fault!). If we've now got the real one let the old one be valid!
			if (stripos(PHP_OS, 'win') !== 0)
			{
				require_once(SUBSDIR . '/Compat.subs.php');
				$other_passwords[] = sha1_smf(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));
			}

			// Allows mods to easily extend the $other_passwords array
			call_integration_hook('integrate_other_passwords', array(&$other_passwords));

			// Whichever encryption it was using, let's make it use ELKARTE's now ;).
			if (in_array($user_settings['passwd'], $other_passwords))
			{
				$user_settings['passwd'] = $sha_passwd;
				$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);

				// Update the password and set up the hash.
				updateMemberData($user_settings['id_member'], array('passwd' => $user_settings['passwd'], 'password_salt' => $user_settings['password_salt'], 'passwd_flood' => ''));
			}
			// Okay, they for sure didn't enter the password!
			else
			{
				// They've messed up again - keep a count to see if they need a hand.
				$_SESSION['failed_login'] = isset($_SESSION['failed_login']) ? ($_SESSION['failed_login'] + 1) : 1;

				// Hmm... don't remember it, do you?  Here, try the password reminder ;).
				if ($_SESSION['failed_login'] >= $modSettings['failed_login_threshold'])
					redirectexit('action=reminder');
				// We'll give you another chance...
				else
				{
					// Log an error so we know that it didn't go well in the error log.
					log_error($txt['incorrect_password'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', 'user');

					$context['login_errors'] = array($txt['incorrect_password']);
					return;
				}
			}
		}
		elseif (!empty($user_settings['passwd_flood']))
		{
			// Let's be sure they weren't a little hacker.
			validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood'], true);

			// If we got here then we can reset the flood counter.
			updateMemberData($user_settings['id_member'], array('passwd_flood' => ''));
		}

		// Correct password, but they've got no salt; fix it!
		if ($user_settings['password_salt'] == '')
		{
			$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);
			updateMemberData($user_settings['id_member'], array('password_salt' => $user_settings['password_salt']));
		}

		// Check their activation status.
		if (!checkActivation())
			return;

		doLogin();
	}

	/**
 	* Logs the current user out of their account.
 	* It requires that the session hash is sent as well, to prevent automatic logouts by images or javascript.
 	* It redirects back to $_SESSION['logout_url'], if it exists.
 	* It is accessed via ?action=logout;session_var=...
 	*
 	* @param bool $internal if true, it doesn't check the session
 	* @param $redirect
 	*/
	public function action_logout($internal = false, $redirect = true)
	{
		global $user_info, $user_settings, $context, $modSettings;

		// Make sure they aren't being auto-logged out.
		if (!$internal)
			checkSession('get');

		require_once(SUBSDIR . '/Auth.subs.php');

		if (isset($_SESSION['pack_ftp']))
			$_SESSION['pack_ftp'] = null;

		// They cannot be open ID verified any longer.
		if (isset($_SESSION['openid']))
			unset($_SESSION['openid']);

		// It won't be first login anymore.
		unset($_SESSION['first_login']);

		// Just ensure they aren't a guest!
		if (!$user_info['is_guest'])
		{
			// Pass the logout information to integrations.
			call_integration_hook('integrate_logout', array($user_settings['member_name']));

			// If you log out, you aren't online anymore :P.
			logOnline($user_info['id'], false);
		}

		$_SESSION['log_time'] = 0;

		// Empty the cookie! (set it in the past, and for id_member = 0)
		setLoginCookie(-3600, 0);

		// Off to the merry board index we go!
		if ($redirect)
		{
			if (empty($_SESSION['logout_url']))
				redirectexit('', $context['server']['needs_login_fix']);
			elseif (!empty($_SESSION['logout_url']) && (strpos('http://', $_SESSION['logout_url']) === false && strpos('https://', $_SESSION['logout_url']) === false))
			{
				unset ($_SESSION['logout_url']);
				redirectexit();
			}
			else
			{
				$temp = $_SESSION['logout_url'];
				unset($_SESSION['logout_url']);

				redirectexit($temp, $context['server']['needs_login_fix']);
			}
		}
	}

	/**
	 * Throws guests out to the login screen when guest access is off.
	 * It sets $_SESSION['login_url'] to $_SERVER['REQUEST_URL'].
	 * It uses the 'kick_guest' sub template found in Login.template.php.
	 */
	public function action_kickguest()
	{
		global $txt, $context;

		loadLanguage('Login');
		loadTemplate('Login');

		// Never redirect to an attachment
		if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false)
			$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];

		$context['sub_template'] = 'kick_guest';
		$context['page_title'] = $txt['login'];
	}

	/**
 	* Display a message about the forum being in maintenance mode.
 	* Displays a login screen with sub template 'maintenance'.
 	* It sends a 503 header, so search engines don't index while we're in maintenance mode.
 	*/
	public function action_maintenance_mode()
	{
		global $txt, $mtitle, $mmessage, $context;

		loadLanguage('Login');
		loadTemplate('Login');

		// Send a 503 header, so search engines don't bother indexing while we're in maintenance mode.
		header('HTTP/1.1 503 Service Temporarily Unavailable');

		// Basic template stuff..
		$context['sub_template'] = 'maintenance';
		$context['title'] = &$mtitle;
		$context['description'] = &$mmessage;
		$context['page_title'] = $txt['maintain_mode'];
	}

	/**
	 * Checks the cookie and update salt.
	 * If successful, it redirects to action=auth;sa=check.
	 * Accessed by ?action=auth;sa=salt.
	 */
	public function action_salt()
	{
		global $user_info, $user_settings, $context;

		// we deal only with logged in folks in here!
		if (!$user_info['is_guest'])
		{
			if (isset($_COOKIE[$cookiename]) && preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~', $_COOKIE[$cookiename]) === 1)
				list (, , $timeout) = @unserialize($_COOKIE[$cookiename]);
			elseif (isset($_SESSION['login_' . $cookiename]))
				list (, , $timeout) = @unserialize($_SESSION['login_' . $cookiename]);
			else
				trigger_error('Auth: Cannot be logged in without a session or cookie', E_USER_ERROR);

			$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);
			updateMemberData($user_info['id'], array('password_salt' => $user_settings['password_salt']));

			setLoginCookie($timeout - time(), $user_info['id'], sha1($user_settings['passwd'] . $user_settings['password_salt']));

			redirectexit('action=auth;sa=check;member=' . $user_info['id'], $context['server']['needs_login_fix']);
		}

		// Lets be sure.
		redirectexit();
	}

	/**
	 * Double check the cookie.
	 */
	public function action_check()
	{
		global $user_info, $modSettings;

		// Only our members, please.
		if (!$user_info['is_guest'])
		{
			// Strike!  You're outta there!
			if ($_GET['member'] != $user_info['id'])
				fatal_lang_error('login_cookie_error', false);

			$user_info['can_mod'] = allowedTo('access_mod_center') || (!$user_info['is_guest'] && ($user_info['mod_cache']['gq'] != '0=1' || $user_info['mod_cache']['bq'] != '0=1' || ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']))));
			if ($user_info['can_mod'] && isset($user_settings['openid_uri']) && empty($user_settings['openid_uri']))
			{
				$_SESSION['moderate_time'] = time();
				unset($_SESSION['just_registered']);
			}

			// Some whitelisting for login_url...
			if (empty($_SESSION['login_url']))
				redirectexit();
			elseif (!empty($_SESSION['login_url']) && (strpos('http://', $_SESSION['login_url']) === false && strpos('https://', $_SESSION['login_url']) === false))
			{
				unset ($_SESSION['login_url']);
				redirectexit();
			}
			else
			{
				// Best not to clutter the session data too much...
				$temp = $_SESSION['login_url'];
				unset($_SESSION['login_url']);

				redirectexit($temp);
			}
		}

		// It'll never get here... until it does :P
		redirectexit();
	}
}

/**
 * Check activation status of the current user.
 */
function checkActivation()
{
	global $context, $txt, $scripturl, $user_settings, $modSettings;

	if (!isset($context['login_errors']))
		$context['login_errors'] = array();

	// What is the true activation status of this account?
	$activation_status = $user_settings['is_activated'] > 10 ? $user_settings['is_activated'] - 10 : $user_settings['is_activated'];

	// Check if the account is activated - COPPA first...
	if ($activation_status == 5)
	{
		$context['login_errors'][] = $txt['coppa_no_concent'] . ' <a href="' . $scripturl . '?action=coppa;member=' . $user_settings['id_member'] . '">' . $txt['coppa_need_more_details'] . '</a>';
		return false;
	}
	// Awaiting approval still?
	elseif ($activation_status == 3)
		fatal_lang_error('still_awaiting_approval', 'user');
	// Awaiting deletion, changed their mind?
	elseif ($activation_status == 4)
	{
		if (isset($_REQUEST['undelete']))
		{
			updateMemberData($user_settings['id_member'], array('is_activated' => 1));
			updateSettings(array('unapprovedMembers' => ($modSettings['unapprovedMembers'] > 0 ? $modSettings['unapprovedMembers'] - 1 : 0)));
		}
		else
		{
			$context['disable_login_hashing'] = true;
			$context['login_errors'][] = $txt['awaiting_delete_account'];
			$context['login_show_undelete'] = true;
			return false;
		}
	}
	// Standard activation?
	elseif ($activation_status != 1)
	{
		log_error($txt['activate_not_completed1'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', false);

		$context['login_errors'][] = $txt['activate_not_completed1'] . ' <a href="' . $scripturl . '?action=activate;sa=resend;u=' . $user_settings['id_member'] . '">' . $txt['activate_not_completed2'] . '</a>';
		return false;
	}
	return true;
}

/**
 * This function performs the logging in.
 * It sets the cookie, it call hooks, updates runtime settings for the user.
 */
function doLogin()
{
	global $user_info, $user_settings;
	global $cookiename, $maintenance, $modSettings, $context;

	// Load authentication stuffs.
	require_once(SUBSDIR . '/Auth.subs.php');

	// Call login integration functions.
	call_integration_hook('integrate_login', array($user_settings['member_name'], isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) == 40 ? $_POST['hash_passwrd'] : null, $modSettings['cookieTime']));

	// Get ready to set the cookie...
	$username = $user_settings['member_name'];
	$user_info['id'] = $user_settings['id_member'];

	// Bam!  Cookie set.  A session too, just in case.
	setLoginCookie(60 * $modSettings['cookieTime'], $user_settings['id_member'], sha1($user_settings['passwd'] . $user_settings['password_salt']));

	// Reset the login threshold.
	if (isset($_SESSION['failed_login']))
		unset($_SESSION['failed_login']);

	$user_info['is_guest'] = false;
	$user_settings['additional_groups'] = explode(',', $user_settings['additional_groups']);
	$user_info['is_admin'] = $user_settings['id_group'] == 1 || in_array(1, $user_settings['additional_groups']);

	// Are you banned?
	is_not_banned(true);

	// An administrator, set up the login so they don't have to type it again.
	if ($user_info['is_admin'] && isset($user_settings['openid_uri']) && empty($user_settings['openid_uri']))
	{
		$_SESSION['admin_time'] = time();
		unset($_SESSION['just_registered']);
	}

	// Don't stick the language or theme after this point.
	unset($_SESSION['language'], $_SESSION['id_theme']);

	// We want to know if this is first login
	if (isFirstLogin($user_info['id']))
		$_SESSION['first_login'] = true;
	else
		unset($_SESSION['first_login']);

	// You're one of us: need to know all about you now, IP, stuff.
	$req = request();

	// You've logged in, haven't you?
	updateMemberData($user_info['id'], array('last_login' => time(), 'member_ip' => $user_info['ip'], 'member_ip2' => $req->ban_ip()));

	// Get rid of the online entry for that old guest....
	deleteOnline('ip' . $user_info['ip']);
	$_SESSION['log_time'] = 0;

	// Log this entry, only if we have it enabled.
	if (!empty($modSettings['loginHistoryDays']))
		logLoginHistory($user_info['id'], $user_info['ip'], $user_info['ip2']);

	// Just log you back out if it's in maintenance mode and you AREN'T an admin.
	if (empty($maintenance) || allowedTo('admin_forum'))
		redirectexit('action=auth;sa=check;member=' . $user_info['id'], $context['server']['needs_login_fix']);
	else
		redirectexit('action=logout;' . $context['session_var'] . '=' . $context['session_id'], $context['server']['needs_login_fix']);
}

/**
 * MD5 Encryption used for older passwords. (SMF 1.0.x/YaBB SE 1.5.x hashing)
 *
 * @param string $data
 * @param string $key
 * @return string, the HMAC MD5 of data with key
 */
function md5_hmac($data, $key)
{
	$key = str_pad(strlen($key) <= 64 ? $key : pack('H*', md5($key)), 64, chr(0x00));
	return md5(($key ^ str_repeat(chr(0x5c), 64)) . pack('H*', md5(($key ^ str_repeat(chr(0x36), 64)) . $data)));
}

/**
 * Custom encryption for phpBB3 based passwords.
 *
 * @param string $passwd
 * @param string $passwd_hash
 * @return string
 */
function phpBB3_password_check($passwd, $passwd_hash)
{
	// Too long or too short?
	if (strlen($passwd_hash) != 34)
		return;

	// Range of characters allowed.
	$range = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

	// Tests
	$strpos = strpos($range, $passwd_hash[3]);
	$count = 1 << $strpos;
	$salt = substr($passwd_hash, 4, 8);

	$hash = md5($salt . $passwd, true);
	for (; $count != 0; --$count)
		$hash = md5($hash . $passwd, true);

	$output = substr($passwd_hash, 0, 12);
	$i = 0;
	while ($i < 16)
	{
		$value = ord($hash[$i++]);
		$output .= $range[$value & 0x3f];

		if ($i < 16)
			$value |= ord($hash[$i]) << 8;

		$output .= $range[($value >> 6) & 0x3f];

		if ($i++ >= 16)
			break;

		if ($i < 16)
			$value |= ord($hash[$i]) << 16;

		$output .= $range[($value >> 12) & 0x3f];

		if ($i++ >= 16)
			break;

		$output .= $range[($value >> 18) & 0x3f];
	}

	// Return now.
	return $output;
}