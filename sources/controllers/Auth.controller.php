<?php

/**
 * This file is concerned pretty entirely, as you see from its name, with
 * logging in and out members, and the validation of that.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * Auth_Controller class.
 * Deals with logging in and out members, and the validation of them
 *
 * @package Authorization
 */
class Auth_Controller extends Action_Controller
{
	/**
	 * {@inheritdoc }
	 */
	public function needSecurity($action = '')
	{
		return $action !== 'action_keepalive';
	}

	/**
	 * Entry point in Auth controller
	 *
	 * - (well no, not really. We route directly to the rest.)
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// What can we do? login page!
		$this->action_login();
	}

	/**
	 * Ask them for their login information.
	 *
	 * What it does:
	 *  - Shows a page for the user to type in their username and password.
	 *  - It caches the referring URL in $_SESSION['login_url'].
	 *  - It is accessed from ?action=login.
	 *
	 *  @uses Login template and language file with the login sub-template.
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
		loadJavascriptFile('sha256.js', array('defer' => true));
		$context['sub_template'] = 'login';

		// Get the template ready.... not really much else to do.
		$context['page_title'] = $txt['login'];
		$_REQUEST['u'] = isset($_REQUEST['u']) ? Util::htmlspecialchars($_REQUEST['u']) : '';
		$context['default_username'] = &$_REQUEST['u'];
		$context['using_openid'] = isset($_GET['openid']);
		$context['default_password'] = '';
		$context['never_expire'] = false;

		// Add the login chain to the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=login',
			'name' => $txt['login'],
		);

		// Set the login URL - will be used when the login process is done (but careful not to send us to an attachment).
		if (isset($_SESSION['old_url']) && validLoginUrl($_SESSION['old_url'], true))
			$_SESSION['login_url'] = $_SESSION['old_url'];
		else
			unset($_SESSION['login_url']);

		// Create a one time token.
		createToken('login');
	}

	/**
	 * Actually logs you in.
	 *
	 * What it does:
	 *
	 * - Checks credentials and checks that login was successful.
	 * - It employs protection against a specific IP or user trying to brute force
	 *   a login to an account.
	 * - Upgrades password encryption on login, if necessary.
	 * - After successful login, redirects you to $_SESSION['login_url'].
	 * - Accessed from ?action=login2, by forms.
	 *
	 * @uses the same templates action_login()
	 */
	public function action_login2()
	{
		global $txt, $scripturl, $user_info, $user_settings, $modSettings, $context;

		// Load cookie authentication and all stuff.
		require_once(SUBSDIR . '/Auth.subs.php');

		// Beyond this point you are assumed to be a guest trying to login.
		if (!$user_info['is_guest'])
			redirectexit();

		// Are you guessing with a script?
		checkSession('post');
		validateToken('login');
		spamProtection('login');

		// Set the login_url if it's not already set (but careful not to send us to an attachment).
		if (empty($_SESSION['login_url']) && isset($_SESSION['old_url']) && validLoginUrl($_SESSION['old_url'], true))
			$_SESSION['login_url'] = $_SESSION['old_url'];

		// Been guessing a lot, haven't we?
		if (isset($_SESSION['failed_login']) && $_SESSION['failed_login'] >= $modSettings['failed_login_threshold'] * 3)
			throw new Elk_Exception('login_threshold_fail', 'critical');

		// Set up the cookie length.  (if it's invalid, just fall through and use the default.)
		if (isset($_POST['cookieneverexp']) || (!empty($_POST['cookielength']) && $_POST['cookielength'] == -1))
			$modSettings['cookieTime'] = 3153600;
		elseif (!empty($_POST['cookielength']) && ($_POST['cookielength'] >= 1 && $_POST['cookielength'] <= 525600))
			$modSettings['cookieTime'] = (int) $_POST['cookielength'];

		loadLanguage('Login');

		// Load the template stuff
		loadTemplate('Login');
		loadJavascriptFile('sha256.js', array('defer' => true));
		$context['sub_template'] = 'login';

		// Set up the default/fallback stuff.
		$context['default_username'] = isset($_POST['user']) ? preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($_POST['user'], ENT_COMPAT, 'UTF-8')) : '';
		$context['default_password'] = '';
		$context['never_expire'] = $modSettings['cookieTime'] == 525600 || $modSettings['cookieTime'] == 3153600;
		$context['login_errors'] = array($txt['error_occurred']);
		$context['page_title'] = $txt['login'];

		// Add the login chain to the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=login',
			'name' => $txt['login'],
		);

		// This is an OpenID login. Let's validate...
		if (!empty($_POST['openid_identifier']) && !empty($modSettings['enableOpenID']))
		{
			require_once(SUBSDIR . '/OpenID.subs.php');
			$open_id = new OpenID();

			if (($open_id->validate($_POST['openid_identifier'])) !== 'no_data')
				return $open_id;
			else
			{
				$context['login_errors'] = array($txt['openid_not_found']);
				return false;
			}
		}

		// You forgot to type your username, dummy!
		if (!isset($_POST['user']) || $_POST['user'] === '')
		{
			$context['login_errors'] = array($txt['need_username']);
			return false;
		}

		// No one needs a username that long, plus we only support 80 chars in the db
		if (Util::strlen($_POST['user']) > 80)
			$_POST['user'] = Util::substr($_POST['user'], 0, 80);

		// Can't use a password > 64 characters sorry, to long and only good for a DoS attack
		// Plus we expect a 64 character one from SHA-256
		if ((isset($_POST['passwrd']) && strlen($_POST['passwrd']) > 64) || (isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) > 64))
		{
			$context['login_errors'] = array($txt['improper_password']);
			return false;
		}

		// Hmm... maybe 'admin' will login with no password. Uhh... NO!
		if ((!isset($_POST['passwrd']) || $_POST['passwrd'] === '') && (!isset($_POST['hash_passwrd']) || strlen($_POST['hash_passwrd']) != 64))
		{
			$context['login_errors'] = array($txt['no_password']);
			return false;
		}

		// No funky symbols either.
		if (preg_match('~[<>&"\'=\\\]~', preg_replace('~(&#(\\d{1,7}|x[0-9a-fA-F]{1,6});)~', '', $_POST['user'])) != 0)
		{
			$context['login_errors'] = array($txt['error_invalid_characters_username']);
			return false;
		}

		// Are we using any sort of integration to validate the login?
		if (in_array('retry', call_integration_hook('integrate_validate_login', array($_POST['user'], isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) == 40 ? $_POST['hash_passwrd'] : null, $modSettings['cookieTime'])), true))
		{
			$context['login_errors'] = array($txt['login_hash_error']);
			$context['disable_login_hashing'] = true;
			return false;
		}

		// Find them... if we can
		$user_settings = loadExistingMember($_POST['user']);

		// User using 2FA for login? Let's validate the token...
		if (!empty($modSettings['enableOTP']) && !empty($user_settings['enable_otp']) && empty($_POST['otp_token']))
		{
			$context['login_errors'] = array($txt['otp_required']);
			return false;
		}

		if (!empty($_POST['otp_token']))
		{
			require_once(EXTDIR . '/GoogleAuthenticator.php');
			$ga = New GoogleAuthenticator();

			$ga->getCode($user_settings['otp_secret']);
			$checkResult = $ga->verifyCode($user_settings['otp_secret'], $_POST['otp_token'], 2);
			if (!$checkResult)
			{
				$context['login_errors'] = array($txt['invalid_otptoken']);
				return false;
			}
			// OTP already used? Sorry, but this is a ONE TIME password..
			if ($user_settings['otp_used'] == $_POST['otp_token'])
			{
				$context['login_errors'] = array($txt['otp_used']);
				return false;
			}
		}

		// Let them try again, it didn't match anything...
		if (empty($user_settings))
		{
			$context['login_errors'] = array($txt['username_no_exist']);
			return false;
		}

		// Figure out if the password is using Elk's encryption - if what they typed is right.
		if (isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) === 64)
		{
			// Challenge what was passed
			$valid_password = validateLoginPassword($_POST['hash_passwrd'], $user_settings['passwd']);

			// Let them in
			if ($valid_password)
			{
				$sha_passwd = $_POST['hash_passwrd'];
				$valid_password = true;
			}
			// Maybe is an old SHA-1 and needs upgrading if the db string is an actual 40 hexchar SHA-1
			elseif (preg_match('/^[0-9a-f]{40}$/i', $user_settings['passwd']) && isset($_POST['old_hash_passwrd']) && $_POST['old_hash_passwrd'] === hash('sha1', $user_settings['passwd'] . $_SESSION['session_value']))
			{
				// Old password passed, turn off hashing and ask for it again so we can update the db to something more secure.
				$context['login_errors'] = array($txt['login_hash_error']);
				$context['disable_login_hashing'] = true;
				unset($user_settings);

				return false;
			}
			// Bad password entered
			else
			{
				// Don't allow this!
				validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood']);

				$_SESSION['failed_login'] = isset($_SESSION['failed_login']) ? ($_SESSION['failed_login'] + 1) : 1;

				// To many tries, maybe they need a reminder
				if ($_SESSION['failed_login'] >= $modSettings['failed_login_threshold'])
					redirectexit('action=reminder');
				else
				{
					Errors::instance()->log_error($txt['incorrect_password'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', 'user');

					// Wrong password, lets enable plain text responses in case form hashing is causing problems
					$context['disable_login_hashing'] = true;
					$context['login_errors'] = array($txt['incorrect_password']);
					unset($user_settings);

					return false;
				}
			}
		}
		// Plain text password, no JS or hashing has been turned off
		else
		{
			// validateLoginPassword will hash this like the form normally would and check its valid
			$sha_passwd = $_POST['passwrd'];
			$valid_password = validateLoginPassword($sha_passwd, $user_settings['passwd'], $user_settings['member_name']);
		}

		// Bad password!  Thought you could fool the database?!
		if ($valid_password === false)
		{
			// Let's be cautious, no hacking please. thanx.
			validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood']);

			// Maybe we were too hasty... let's try some other authentication methods.
			$other_passwords = $this->_other_passwords($user_settings);

			// Whichever encryption it was using, let's make it use ElkArte's now ;).
			if (in_array($user_settings['passwd'], $other_passwords))
			{
				$tokenizer = new Token_Hash();
				$user_settings['passwd'] = validateLoginPassword($sha_passwd, '', '', true);
				$user_settings['password_salt'] = $tokenizer->generate_hash(4);

				// Update the password hash and set up the salt.
				require_once(SUBSDIR . '/Members.subs.php');
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
					Errors::instance()->log_error($txt['incorrect_password'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', 'user');

					$context['login_errors'] = array($txt['incorrect_password']);
					return false;
				}
			}
		}
		elseif (!empty($user_settings['passwd_flood']))
		{
			// Let's be sure they weren't a little hacker.
			validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood'], true);

			// If we got here then we can reset the flood counter.
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($user_settings['id_member'], array('passwd_flood' => ''));
		}

		// Correct password, but they've got no salt; fix it!
		if ($user_settings['password_salt'] === '')
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$tokenizer = new Token_Hash();
			$user_settings['password_salt'] = $tokenizer->generate_hash(4);
			updateMemberData($user_settings['id_member'], array('password_salt' => $user_settings['password_salt']));
		}

		// Let's track the last used one-time password.
		if (!empty($_POST['otp_token']))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($user_settings['id_member'], array('otp_used' => (int) $_POST['otp_token']));
		}
		// Check their activation status.
		if (!checkActivation())
			return false;

		doLogin();
	}

	/**
	 * Logs the current user out of their account.
	 *
	 * What it does:
	 *
	 * - It requires that the session hash is sent as well, to prevent automatic logouts by images or javascript.
	 * - It redirects back to $_SESSION['logout_url'], if it exists.
	 * - It is accessed via ?action=logout;session_var=...
	 *
	 * @param boolean $internal if true, it doesn't check the session
	 * @param boolean $redirect if true, redirect to the board index
	 * @throws Elk_Exception
	 */
	public function action_logout($internal = false, $redirect = true)
	{
		global $user_info, $user_settings;

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
			require_once(SUBSDIR . '/Logging.subs.php');
			logOnline($user_info['id'], false);
		}

		// Logout? Let's kill the admin/moderate/other sessions, too.
		$types = array('admin', 'moderate');
		call_integration_hook('integrate_validateSession', array(&$types));
		foreach ($types as $type)
			unset($_SESSION[$type . '_time']);

		$_SESSION['log_time'] = 0;

		// Empty the cookie! (set it in the past, and for id_member = 0)
		setLoginCookie(-3600, 0);

		// And some other housekeeping while we're at it.
		session_destroy();
		if (!empty($user_info['id']))
		{
			$tokenizer = new Token_Hash();
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($user_info['id'], array('password_salt' => $tokenizer->generate_hash(4)));
		}

		// Off to the merry board index we go!
		if ($redirect)
		{
			if (empty($_SESSION['logout_url']))
				redirectexit('', detectServer()->is('needs_login_fix'));
			elseif (!empty($_SESSION['logout_url']) && (substr($_SESSION['logout_url'], 0, 7) !== 'http://' && substr($_SESSION['logout_url'], 0, 8) !== 'https://'))
			{
				unset($_SESSION['logout_url']);
				redirectexit();
			}
			else
			{
				$temp = $_SESSION['logout_url'];
				unset($_SESSION['logout_url']);

				redirectexit($temp, detectServer()->is('needs_login_fix'));
			}
		}
	}

	/**
	 * Throws guests out to the login screen when guest access is off.
	 *
	 * What it does:
	 *
	 * - It sets $_SESSION['login_url'] to $_SERVER['REQUEST_URL'].
	 *
	 * @uses 'kick_guest' sub template found in Login.template.php.
	 */
	public function action_kickguest()
	{
		global $txt, $context;

		loadLanguage('Login');
		loadTemplate('Login');
		loadJavascriptFile('sha256.js', array('defer' => true));
		createToken('login');

		// Never redirect to an attachment
		if (validLoginUrl($_SERVER['REQUEST_URL']))
			$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];

		$context['sub_template'] = 'kick_guest';
		$context['page_title'] = $txt['login'];
		$context['default_password'] = '';
	}

	/**
	 * Display a message about the forum being in maintenance mode.
	 *
	 * What it does:
	 *
	 * - Displays a login screen with sub template 'maintenance'.
	 * - It sends a 503 header, so search engines don't index while we're in maintenance mode.
	 */
	public function action_maintenance_mode()
	{
		global $txt, $mtitle, $mmessage, $context;

		loadLanguage('Login');
		loadTemplate('Login');
		loadJavascriptFile('sha256.js', array('defer' => true));
		createToken('login');

		// Send a 503 header, so search engines don't bother indexing while we're in maintenance mode.
		header('HTTP/1.1 503 Service Temporarily Unavailable');

		// Basic template stuff..
		$context['sub_template'] = 'maintenance';
		$context['title'] = &$mtitle;
		$context['description'] = un_htmlspecialchars($mmessage);
		$context['page_title'] = $txt['maintain_mode'];
	}

	/**
	 * Double check the cookie.
	 */
	public function action_check()
	{
		global $user_info, $modSettings, $user_settings;

		// Only our members, please.
		if (!$user_info['is_guest'])
		{
			// Strike!  You're outta there!
			if ($_GET['member'] != $user_info['id'])
				throw new Elk_Exception('login_cookie_error', false);

			$user_info['can_mod'] = allowedTo('access_mod_center') || (!$user_info['is_guest'] && ($user_info['mod_cache']['gq'] != '0=1' || $user_info['mod_cache']['bq'] != '0=1' || ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']))));
			if ($user_info['can_mod'] && isset($user_settings['openid_uri']) && empty($user_settings['openid_uri']))
			{
				$_SESSION['moderate_time'] = time();
				unset($_SESSION['just_registered']);
			}

			// Some whitelisting for login_url...
			if (empty($_SESSION['login_url']) || validLoginUrl($_SESSION['login_url']) === false)
			{
				$temp = '';
			}
			else
			{
				// Best not to clutter the session data too much...
				$temp = $_SESSION['login_url'];
			}
			unset($_SESSION['login_url']);
			redirectexit($temp);
		}

		// It'll never get here... until it does :P
		redirectexit();
	}

	/**
	 * Ping the server to keep the session alive and not let it disappear.
	 */
	public function action_keepalive()
	{
		dieGif();
	}

	/**
	 * Loads other possible password hash / crypts using the post data
	 *
	 * What it does:
	 *
	 * - Used when a board is converted to see if the user credentials and a 3rd
	 * party hash satisfy whats in the db passwd field
	 *
	 * @param mixed[] $user_settings
	 */
	private function _other_passwords($user_settings)
	{
		global $modSettings;

		// What kind of data are we dealing with
		$pw_strlen = strlen($user_settings['passwd']);

		// Start off with none, that's safe
		$other_passwords = array();

		// None of the below cases will be used most of the time (because the salt is normally set.)
		if (!empty($modSettings['enable_password_conversion']) && $user_settings['password_salt'] === '')
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

			// SHA-256
			if ($pw_strlen === 64)
			{
				// Snitz style
				$other_passwords[] = bin2hex(hash('sha256', $_POST['passwrd']));

				// Normal SHA-256
				$other_passwords[] = hash('sha256', $_POST['passwrd']);
			}

			// phpBB3 users new hashing.  We now support it as well ;).
			$other_passwords[] = phpBB3_password_check($_POST['passwrd'], $user_settings['passwd']);

			// APBoard 2 Login Method.
			$other_passwords[] = md5(crypt($_POST['passwrd'], 'CRYPT_MD5'));

			// Xenforo 1.2+
			$other_passwords[] = crypt($_POST['passwrd'], $user_settings['passwd']);
		}
		// The hash should be 40 if it's SHA-1, so we're safe with more here too.
		elseif (!empty($modSettings['enable_password_conversion']) && $pw_strlen === 32)
		{
			// vBulletin 3 style hashing?  Let's welcome them with open arms \o/.
			$other_passwords[] = md5(md5($_POST['passwrd']) . stripslashes($user_settings['password_salt']));

			// Hmm.. p'raps it's Invision 2 style?
			$other_passwords[] = md5(md5($user_settings['password_salt']) . md5($_POST['passwrd']));

			// Some common md5 ones.
			$other_passwords[] = md5($user_settings['password_salt'] . $_POST['passwrd']);
			$other_passwords[] = md5($_POST['passwrd'] . $user_settings['password_salt']);
		}
		// The hash is 40 characters, lets try some SHA-1 style auth
		elseif ($pw_strlen === 40)
		{
			// Maybe they are using a hash from before our password upgrade
			$other_passwords[] = sha1(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));
			$other_passwords[] = sha1($user_settings['passwd'] . $_SESSION['session_value']);

			if (!empty($modSettings['enable_password_conversion']))
			{
				// BurningBoard3 style of hashing.
				$other_passwords[] = sha1($user_settings['password_salt'] . sha1($user_settings['password_salt'] . sha1($_POST['passwrd'])));

				// PunBB 1.4 and later
				$other_passwords[] = sha1($user_settings['password_salt'] . sha1($_POST['passwrd']));
			}

			// Perhaps we converted from a non UTF-8 db and have a valid password being hashed differently.
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
		// SHA-256 will be 64 characters long, lets check some of these possibilities
		elseif (!empty($modSettings['enable_password_conversion']) && $pw_strlen === 64)
		{
			// PHP-Fusion7
			$other_passwords[] = hash_hmac('sha256', $_POST['passwrd'], $user_settings['password_salt']);

			// Plain SHA-256?
			$other_passwords[] = hash('sha256', $_POST['passwrd'] . $user_settings['password_salt']);

			// Xenforo?
			$other_passwords[] = sha1(sha1($_POST['passwrd']) . $user_settings['password_salt']);
			$other_passwords[] = hash('sha256', (hash('sha256', ($_POST['passwrd']) . $user_settings['password_salt'])));
		}

		// ElkArte's sha1 function can give a funny result on Linux (Not our fault!). If we've now got the real one let the old one be valid!
		if (stripos(PHP_OS, 'win') !== 0)
		{
			require_once(SUBSDIR . '/Compat.subs.php');
			$other_passwords[] = sha1_smf(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));
		}

		// Allows mods to easily extend the $other_passwords array
		call_integration_hook('integrate_other_passwords', array(&$other_passwords));

		return $other_passwords;
	}
}

/**
 * Check activation status of the current user.
 *
 * What it does:
 * is_activated value key is as follows:
 * - > 10 Banned with activation status as value - 10
 * - 5 = Awaiting COPPA consent
 * - 4 = Awaiting Deletion approval
 * - 3 = Awaiting Admin approval
 * - 2 = Awaiting reactivation from email change
 * - 1 = Approved and active
 * - 0 = Not active
 *
 * @package Authorization
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
		$context['login_errors'][] = $txt['coppa_no_concent'] . ' <a href="' . $scripturl . '?action=register;sa=coppa;member=' . $user_settings['id_member'] . '">' . $txt['coppa_need_more_details'] . '</a>';
		return false;
	}
	// Awaiting approval still?
	elseif ($activation_status == 3)
		throw new Elk_Exception('still_awaiting_approval', 'user');
	// Awaiting deletion, changed their mind?
	elseif ($activation_status == 4)
	{
		if (isset($_REQUEST['undelete']))
		{
			require_once(SUBSDIR . '/Members.subs.php');
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
		Errors::instance()->log_error($txt['activate_not_completed1'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', false);

		$context['login_errors'][] = $txt['activate_not_completed1'] . ' <a class="linkbutton" href="' . $scripturl . '?action=register;sa=activate;resend;u=' . $user_settings['id_member'] . '">' . $txt['activate_not_completed2'] . '</a>';
		return false;
	}
	return true;
}

/**
 * This function performs the logging in.
 *
 * What it does:
 *  - It sets the cookie, it call hooks, updates runtime settings for the user.
 *
 * @package Authorization
 */
function doLogin()
{
	global $user_info, $user_settings, $maintenance, $modSettings, $context;

	// Load authentication stuffs.
	require_once(SUBSDIR . '/Auth.subs.php');

	// Call login integration functions.
	call_integration_hook('integrate_login', array($user_settings['member_name'], isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) == 64 ? $_POST['hash_passwrd'] : null, $modSettings['cookieTime']));

	// Get ready to set the cookie...
	$user_info['id'] = $user_settings['id_member'];

	// Bam!  Cookie set.  A session too, just in case.
	setLoginCookie(60 * $modSettings['cookieTime'], $user_settings['id_member'], hash('sha256', ($user_settings['passwd'] . $user_settings['password_salt'])));

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
		// Let's validate if they really want..
		if (!empty($modSettings['auto_admin_session']) && $modSettings['auto_admin_session'] == 1)
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
	require_once(SUBSDIR . '/Members.subs.php');
	updateMemberData($user_info['id'], array('last_login' => time(), 'member_ip' => $user_info['ip'], 'member_ip2' => $req->ban_ip()));

	// Get rid of the online entry for that old guest....
	require_once(SUBSDIR . '/Logging.subs.php');
	deleteOnline('ip' . $user_info['ip']);
	$_SESSION['log_time'] = 0;

	// Log this entry, only if we have it enabled.
	if (!empty($modSettings['loginHistoryDays']))
		logLoginHistory($user_info['id'], $user_info['ip'], $user_info['ip2']);

	// Just log you back out if it's in maintenance mode and you AREN'T an admin.
	if (empty($maintenance) || allowedTo('admin_forum'))
		redirectexit('action=auth;sa=check;member=' . $user_info['id'], detectServer()->is('needs_login_fix'));
	else
		redirectexit('action=logout;' . $context['session_var'] . '=' . $context['session_id'], detectServer()->is('needs_login_fix'));
}

/**
 * MD5 Encryption used for older passwords. (SMF 1.0.x/YaBB SE 1.5.x hashing)
 *
 * @package Authorization
 * @param string $data
 * @param string $key
 * @return string the HMAC MD5 of data with key
 */
function md5_hmac($data, $key)
{
	$key = str_pad(strlen($key) <= 64 ? $key : pack('H*', md5($key)), 64, chr(0x00));
	return md5(($key ^ str_repeat(chr(0x5c), 64)) . pack('H*', md5(($key ^ str_repeat(chr(0x36), 64)) . $data)));
}

/**
 * Custom encryption for phpBB3 based passwords.
 *
 * @package Authorization
 * @param string $passwd
 * @param string $passwd_hash
 * @return string
 */
function phpBB3_password_check($passwd, $passwd_hash)
{
	// Too long or too short?
	if (strlen($passwd_hash) != 34)
		return false;

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
