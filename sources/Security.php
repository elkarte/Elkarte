<?php

/**
 * This file has the very important job of ensuring forum security.
 * This task includes banning and permissions, namely.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Cache\Cache;
use ElkArte\Controller\Auth;
use ElkArte\EventManager;
use ElkArte\FileFunctions;
use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;
use ElkArte\TokenHash;
use ElkArte\User;
use ElkArte\Util;

/**
 * Check if the user is who he/she says he is.
 *
 * What it does:
 *
 * - This function makes sure the user is who they claim to be by requiring a
 * password to be typed in every hour.
 * - This check can be turned on and off by the securityDisable setting.
 * - Uses the adminLogin() function of subs/Auth.subs.php if they need to login,
 * which saves all request (POST and GET) data.
 *
 * @event integrate_validateSession Called at start of validateSession
 *
 * @param string $type = admin
 *
 * @return bool|string
 */
function validateSession($type = 'admin')
{
	global $modSettings;

	// Guests are not welcome here.
	is_not_guest();

	// Validate what type of session check this is.
	$types = array();
	call_integration_hook('integrate_validateSession', array(&$types));
	$type = in_array($type, $types) || $type === 'moderate' ? $type : 'admin';

	// Set the lifetime for our admin session. Default is ten minutes.
	$refreshTime = 10;

	if (isset($modSettings['admin_session_lifetime']))
	{
		// Maybe someone is paranoid or mistakenly misconfigured the param? Give them at least 5 minutes.
		if ($modSettings['admin_session_lifetime'] < 5)
		{
			$refreshTime = 5;
		}

		// A whole day should be more than enough..
		elseif ($modSettings['admin_session_lifetime'] > 14400)
		{
			$refreshTime = 14400;
		}

		// We are between our internal min and max. Let's keep the board owner's value.
		else
		{
			$refreshTime = $modSettings['admin_session_lifetime'];
		}
	}

	// If we're using XML give an additional ten minutes grace as an admin can't log on in XML mode.
	if (isset($_GET['api']) && $_GET['api'] === 'xml')
	{
		$refreshTime += 10;
	}

	$refreshTime *= 60;

	// Is the security option off?
	// @todo remove the exception (means update the db as well)
	if (!empty($modSettings['securityDisable' . ($type !== 'admin' ? '_' . $type : '')]))
	{
		return true;
	}

	// If their admin or moderator session hasn't expired yet, let it pass, let the admin session trump a moderation one as well
	if ((!empty($_SESSION[$type . '_time']) && $_SESSION[$type . '_time'] + $refreshTime >= time()) || (!empty($_SESSION['admin_time']) && $_SESSION['admin_time'] + $refreshTime >= time()))
	{
		return true;
	}

	require_once(SUBSDIR . '/Auth.subs.php');

	// Coming from the login screen
	if (isset($_POST[$type . '_pass']) || isset($_POST[$type . '_hash_pass']))
	{
		checkSession();
		validateToken('admin-login');

		// Hashed password, ahoy!
		if (isset($_POST[$type . '_hash_pass']) && strlen($_POST[$type . '_hash_pass']) === 64
			&& checkPassword($type, true))
		{
			return true;
		}

		// Posting the password... check it.
		if (isset($_POST[$type . '_pass']) && str_replace('*', '', $_POST[$type . '_pass']) !== '')
		{
			if (checkPassword($type))
			{
				return true;
			}
		}
	}

	// Better be sure to remember the real referer
	if (empty($_SESSION['request_referer']))
	{
		$_SESSION['request_referer'] = $_SERVER['HTTP_REFERER'] ?? '';
	}
	elseif (empty($_POST))
	{
		unset($_SESSION['request_referer']);
	}

	// Need to type in a password for that, man.
	if (!isset($_GET['api']))
	{
		adminLogin($type);
	}

	return 'session_verify_fail';
}

/**
 * Validates a supplied password is correct
 *
 * What it does:
 *
 * - Uses integration function to verify password is enabled
 * - Uses validateLoginPassword to check using standard ElkArte methods
 *
 * @event integrate_verify_password allows integration to verify the password
 * @param string $type
 * @param bool $hash if the supplied password is in _hash_pass
 *
 * @return bool
 */
function checkPassword($type, $hash = false)
{
	$password = $_POST[$type . ($hash ? '_hash_pass' : '_pass')];

	// Allow integration to verify the password
	$good_password = in_array(true, call_integration_hook('integrate_verify_password', array(User::$info->username, $password, $hash)), true);

	// Password correct?
	if ($good_password || validateLoginPassword($password, User::$info->passwd, $hash ? '' : User::$info->username))
	{
		$_SESSION[$type . '_time'] = time();
		unset($_SESSION['request_referer']);

		return true;
	}

	return false;
}

/**
 * Require a user who is logged in. (not a guest.)
 *
 * What it does:
 *
 * - Checks if the user is currently a guest, and if so asks them to login with a message telling them why.
 * - Message is what to tell them when asking them to login.
 *
 * @param string $message = ''
 * @param bool $is_fatal = true
 *
 * @return bool
 */
function is_not_guest($message = '', $is_fatal = true)
{
	global $txt, $context, $scripturl;

	// Luckily, this person isn't a guest.
	if (isset(User::$info->is_guest) && User::$info->is_guest === false)
	{
		return true;
	}

	// People always worry when they see people doing things they aren't actually doing...
	$_GET['action'] = '';
	$_GET['board'] = '';
	$_GET['topic'] = '';
	writeLog(true);

	// Just die.
	if (isset($_REQUEST['api']) && $_REQUEST['api'] === 'xml' || !$is_fatal)
	{
		obExit(false);
	}

	// Attempt to detect if they came from dlattach.
	if (ELK !== 'SSI' && empty($context['theme_loaded']))
	{
		new ElkArte\Themes\ThemeLoader();
	}

	// Never redirect to an attachment
	if (validLoginUrl($_SERVER['REQUEST_URL']))
	{
		$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];
	}

	// Load the Login template and language file.
	Txt::load('Login');

	// Apparently we're not in a position to handle this now. Let's go to a safer location for now.
	if (!theme()->getLayers()->hasLayers())
	{
		$_SESSION['login_url'] = $scripturl . '?' . $_SERVER['QUERY_STRING'];
		redirectexit('action=login');
	}
	elseif (isset($_GET['api']))
	{
		return false;
	}
	else
	{
		theme()->getTemplates()->load('Login');
		loadJavascriptFile('sha256.js', array('defer' => true));
		createToken('login');
		$context['sub_template'] = 'kick_guest';
		$context['robot_no_index'] = true;
	}

	// Use the kick_guest sub template...
	$context['kick_message'] = $message;
	$context['page_title'] = $txt['login'];
	$context['default_password'] = '';

	obExit();

	// We should never get to this point, but if we did we wouldn't know the user isn't a guest.
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

/**
 * Apply restrictions for banned users. For example, disallow access.
 *
 * What it does:
 *
 * - If the user is banned, it dies with an error.
 * - Caches this information for optimization purposes.
 * - Forces a recheck if force_check is true.
 *
 * @param bool $forceCheck = false
 *
 * @throws \ElkArte\Exceptions\Exception
 */
function is_not_banned($forceCheck = false)
{
	global $txt, $modSettings, $cookiename;

	$db = database();

	// You cannot be banned if you are an admin - doesn't help if you log out.
	if (User::$info->is_admin)
	{
		return;
	}

	// Only check the ban every so often. (to reduce load.)
	if ($forceCheck || !isset($_SESSION['ban']) || empty($modSettings['banLastUpdated']) || ($_SESSION['ban']['last_checked'] < $modSettings['banLastUpdated']) || $_SESSION['ban']['id_member'] != User::$info->id || $_SESSION['ban']['ip'] != User::$info->ip || $_SESSION['ban']['ip2'] != User::$info->ip2 || (isset(User::$info->email, $_SESSION['ban']['email']) && $_SESSION['ban']['email'] != User::$info->email))
	{
		// Innocent until proven guilty.  (but we know you are! :P)
		$_SESSION['ban'] = array(
			'last_checked' => time(),
			'id_member' => User::$info->id,
			'ip' => User::$info->ip,
			'ip2' => User::$info->ip2,
			'email' => User::$info->email,
		);

		$ban_query = array();
		$ban_query_vars = array('current_time' => time());
		$flag_is_activated = false;

		// Check both IP addresses.
		foreach (['ip', 'ip2'] as $ip_number)
		{
			if ($ip_number === 'ip2' && User::$info->ip2 === User::$info->ip)
			{
				continue;
			}

			$ban_query[] = constructBanQueryIP(User::$info->{$ip_number});

			// IP was valid, maybe there's also a hostname...
			if (empty($modSettings['disableHostnameLookup']) && User::$info->{$ip_number} !== 'unknown')
			{
				$hostname = host_from_ip(User::$info->{$ip_number});
				if ($hostname !== '')
				{
					$ban_query[] = '({string:hostname} LIKE bi.hostname)';
					$ban_query_vars['hostname'] = $hostname;
				}
			}
		}

		// Is their email address banned?
		if (User::$info->email !== '')
		{
			$ban_query[] = '({string:email} LIKE bi.email_address)';
			$ban_query_vars['email'] = User::$info->email;
		}

		// How about this user?
		if (User::$info->is_guest === false && !empty(User::$info->id))
		{
			$ban_query[] = 'bi.id_member = {int:id_member}';
			$ban_query_vars['id_member'] = User::$info->id;
		}

		// Check the ban, if there's information.
		if (!empty($ban_query))
		{
			$restrictions = array(
				'cannot_access',
				'cannot_login',
				'cannot_post',
				'cannot_register',
			);
			$db->fetchQuery('
				SELECT 
					bi.id_ban, bi.email_address, bi.id_member, bg.cannot_access, bg.cannot_register,
					bg.cannot_post, bg.cannot_login, bg.reason, COALESCE(bg.expire_time, 0) AS expire_time
				FROM {db_prefix}ban_items AS bi
					INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time}))
				WHERE
					(' . implode(' OR ', $ban_query) . ')',
				$ban_query_vars
			)->fetch_callback(
				function ($row) use ($restrictions, &$flag_is_activated) {
					// Store every type of ban that applies to you in your session.
					foreach ($restrictions as $restriction)
					{
						if (!empty($row[$restriction]))
						{
							$_SESSION['ban'][$restriction]['reason'] = $row['reason'];
							$_SESSION['ban'][$restriction]['ids'][] = $row['id_ban'];
							if (!isset($_SESSION['ban']['expire_time']) || ($_SESSION['ban']['expire_time'] != 0 && ($row['expire_time'] == 0 || $row['expire_time'] > $_SESSION['ban']['expire_time'])))
							{
								$_SESSION['ban']['expire_time'] = $row['expire_time'];
							}

							if (User::$info->is_guest === false && $restriction === 'cannot_access' && ($row['id_member'] == User::$info->id || $row['email_address'] === User::$info->email))
							{
								$flag_is_activated = true;
							}
						}
					}
				}
			);
		}

		// Mark the cannot_access and cannot_post bans as being 'hit'.
		if (isset($_SESSION['ban']['cannot_access']) || isset($_SESSION['ban']['cannot_post']) || isset($_SESSION['ban']['cannot_login']))
		{
			log_ban(array_merge(isset($_SESSION['ban']['cannot_access']) ? $_SESSION['ban']['cannot_access']['ids'] : array(), isset($_SESSION['ban']['cannot_post']) ? $_SESSION['ban']['cannot_post']['ids'] : array(), isset($_SESSION['ban']['cannot_login']) ? $_SESSION['ban']['cannot_login']['ids'] : array()));
		}

		// If for whatever reason the is_activated flag seems wrong, do a little work to clear it up.
		if (User::$info->id && ((User::$settings['is_activated'] >= 10 && !$flag_is_activated)
				|| (User::$settings['is_activated'] < 10 && $flag_is_activated)))
		{
			require_once(SUBSDIR . '/Bans.subs.php');
			updateBanMembers();
		}
	}

	// Hey, I know you! You're ehm...
	if (!isset($_SESSION['ban']['cannot_access']) && !empty($_COOKIE[$cookiename . '_']))
	{
		$bans = explode(',', $_COOKIE[$cookiename . '_']);
		foreach ($bans as $key => $value)
		{
			$bans[$key] = (int) $value;
		}

		$db->fetchQuery('
			SELECT 
				bi.id_ban, bg.reason
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
			WHERE bi.id_ban IN ({array_int:ban_list})
				AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})
				AND bg.cannot_access = {int:cannot_access}
			LIMIT ' . count($bans),
			array(
				'cannot_access' => 1,
				'ban_list' => $bans,
				'current_time' => time(),
			)
		)->fetch_callback(
			function ($row) {
				$_SESSION['ban']['cannot_access']['ids'][] = $row['id_ban'];
				$_SESSION['ban']['cannot_access']['reason'] = $row['reason'];
			}
		);

		// My mistake. Next time better.
		if (!isset($_SESSION['ban']['cannot_access']))
		{
			require_once(SUBSDIR . '/Auth.subs.php');
			$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));
			elk_setcookie($cookiename . '_', '', time() - 3600, $cookie_url[1], $cookie_url[0], false, false);
		}
	}

	// If you're fully banned, it's end of the story for you.
	if (isset($_SESSION['ban']['cannot_access']))
	{
		require_once(SUBSDIR . '/Auth.subs.php');

		// We don't wanna see you!
		if (User::$info->is_guest === false)
		{
			$controller = new Auth(new EventManager());
			$controller->setUser(User::$info);
			$controller->action_logout(true, false);
		}

		// 'Log' the user out.  Can't have any funny business... (save the name!)
		$old_name = (string) User::$info->name !== '' ? User::$info->name : $txt['guest_title'];
		User::logOutUser(true);
		loadUserContext();

		// A goodbye present.
		$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));
		elk_setcookie($cookiename . '_', implode(',', $_SESSION['ban']['cannot_access']['ids']), time() + 3153600, $cookie_url[1], $cookie_url[0], false, false);

		// Don't scare anyone, now.
		$_GET['action'] = '';
		$_GET['board'] = '';
		$_GET['topic'] = '';
		writeLog(true);

		// You banned, sucka!
		throw new \ElkArte\Exceptions\Exception(sprintf($txt['your_ban'], $old_name) . (empty($_SESSION['ban']['cannot_access']['reason']) ? '' : '<br />' . $_SESSION['ban']['cannot_access']['reason']) . '<br />' . (!empty($_SESSION['ban']['expire_time']) ? sprintf($txt['your_ban_expires'], standardTime($_SESSION['ban']['expire_time'], false)) : $txt['your_ban_expires_never']), 'user');
	}
	// You're not allowed to log in but yet you are. Let's fix that.
	elseif (isset($_SESSION['ban']['cannot_login']) && User::$info->is_guest === false)
	{
		// We don't wanna see you!
		require_once(SUBSDIR . '/Logging.subs.php');
		deleteMemberLogOnline();

		// 'Log' the user out.  Can't have any funny business... (save the name!)
		$old_name = (string) User::$info->name != '' ? User::$info->name : $txt['guest_title'];
		User::logOutUser(true);
		loadUserContext();

		// Wipe 'n Clean(r) erases all traces.
		$_GET['action'] = '';
		$_GET['board'] = '';
		$_GET['topic'] = '';
		writeLog(true);

		// Log them out
		$controller = new Auth(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_logout(true, false);

		// Tell them thanks
		throw new \ElkArte\Exceptions\Exception(sprintf($txt['your_ban'], $old_name) . (empty($_SESSION['ban']['cannot_login']['reason']) ? '' : '<br />' . $_SESSION['ban']['cannot_login']['reason']) . '<br />' . (!empty($_SESSION['ban']['expire_time']) ? sprintf($txt['your_ban_expires'], standardTime($_SESSION['ban']['expire_time'], false)) : $txt['your_ban_expires_never']) . '<br />' . $txt['ban_continue_browse'], 'user');
	}

	// Fix up the banning permissions.
	if (isset(User::$info->permissions))
	{
		banPermissions();
	}
}

/**
 * Fix permissions according to ban status.
 *
 * What it does:
 *
 * - Applies any states of banning by removing permissions the user cannot have.
 *
 * @event integrate_post_ban_permissions Allows to update denied permissions
 * @event integrate_warn_permissions Allows changing of permissions for users on warning moderate
 * @package Bans
 */
function banPermissions()
{
	global $modSettings, $context;

	// Somehow they got here, at least take away all permissions...
	if (isset($_SESSION['ban']['cannot_access']))
	{
		User::$info->permissions = array();
	}
	// Okay, well, you can watch, but don't touch a thing.
	elseif (isset($_SESSION['ban']['cannot_post']) || (!empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= User::$info->warning))
	{
		$denied_permissions = array(
			'pm_send',
			'calendar_post', 'calendar_edit_own', 'calendar_edit_any',
			'poll_post',
			'poll_add_own', 'poll_add_any',
			'poll_edit_own', 'poll_edit_any',
			'poll_lock_own', 'poll_lock_any',
			'poll_remove_own', 'poll_remove_any',
			'manage_attachments', 'manage_smileys', 'manage_boards', 'admin_forum', 'manage_permissions',
			'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news',
			'profile_identity_any', 'profile_extra_any', 'profile_title_any',
			'post_new', 'post_reply_own', 'post_reply_any',
			'delete_own', 'delete_any', 'delete_replies',
			'make_sticky',
			'merge_any', 'split_any',
			'modify_own', 'modify_any', 'modify_replies',
			'move_any',
			'send_topic',
			'lock_own', 'lock_any',
			'remove_own', 'remove_any',
			'post_unapproved_topics', 'post_unapproved_replies_own', 'post_unapproved_replies_any',
		);
		theme()->getLayers()->addAfter('admin_warning', 'body');

		call_integration_hook('integrate_post_ban_permissions', array(&$denied_permissions));
		User::$info->permissions = array_diff(User::$info->permissions, $denied_permissions);
	}
	// Are they absolutely under moderation?
	elseif (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= User::$info->warning)
	{
		// Work out what permissions should change...
		$permission_change = array(
			'post_new' => 'post_unapproved_topics',
			'post_reply_own' => 'post_unapproved_replies_own',
			'post_reply_any' => 'post_unapproved_replies_any',
			'post_attachment' => 'post_unapproved_attachments',
		);
		call_integration_hook('integrate_warn_permissions', array(&$permission_change));
		foreach ($permission_change as $old => $new)
		{
			if (!in_array($old, User::$info->permissions))
			{
				unset($permission_change[$old]);
			}
			else
			{
				User::$info->permissions = array_merge((array) User::$info->permissions, $new);
			}
		}
		User::$info->permissions = array_diff(User::$info->permissions, array_keys($permission_change));
	}

	// @todo Find a better place to call this? Needs to be after permissions loaded!
	// Finally, some bits we cache in the session because it saves queries.
	if (isset($_SESSION['mc']) && $_SESSION['mc']['time'] > $modSettings['settings_updated'] && $_SESSION['mc']['id'] == User::$info->id)
	{
		User::$info->mod_cache = $_SESSION['mc'];
	}
	else
	{
		require_once(SUBSDIR . '/Auth.subs.php');
		rebuildModCache();
	}

	// Now that we have the mod cache taken care of lets setup a cache for the number of mod reports still open
	if (isset($_SESSION['rc']) && $_SESSION['rc']['time'] > $modSettings['last_mod_report_action'] && $_SESSION['rc']['id'] == User::$info->id)
	{
		$context['open_mod_reports'] = $_SESSION['rc']['reports'];
		if (allowedTo('admin_forum'))
		{
			$context['open_pm_reports'] = $_SESSION['rc']['pm_reports'];
		}
	}
	elseif ($_SESSION['mc']['bq'] != '0=1')
	{
		require_once(SUBSDIR . '/Moderation.subs.php');
		recountOpenReports(true, allowedTo('admin_forum'));
	}
	else
	{
		$context['open_mod_reports'] = 0;
	}
}

/**
 * Log a ban in the database.
 *
 * What it does:
 *
 * - Log the current user in the ban logs.
 * - Increment the hit counters for the specified ban ID's (if any.)
 *
 * @param int[] $ban_ids = array()
 * @param string|null $email = null
 * @package Bans
 */
function log_ban($ban_ids = array(), $email = null)
{
	$db = database();

	// Don't log web accelerators, it's very confusing...
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] === 'prefetch')
	{
		return;
	}

	$db->insert('',
		'{db_prefix}log_banned',
		array(
			'id_member' => 'int',
			'ip' => 'string-16',
			'email' => 'string',
			'log_time' => 'int'
		),
		array(
			User::$info->id,
			User::$info->ip,
			$email ?? (string) User::$info->email,
			time()
		),
		array('id_ban_log')
	);

	// One extra point for these bans.
	if (!empty($ban_ids))
	{
		$db->query('', '
			UPDATE {db_prefix}ban_items
			SET hits = hits + 1
			WHERE id_ban IN ({array_int:ban_ids})',
			array(
				'ban_ids' => $ban_ids,
			)
		);
	}
}

/**
 * Checks if a given email address might be banned.
 *
 * What it does:
 *
 * - Check if a given email is banned.
 * - Performs an immediate ban if the turns turns out positive.
 *
 * @param string $email
 * @param string $restriction
 * @param string $error
 *
 * @throws \ElkArte\Exceptions\Exception
 * @package Bans
 */
function isBannedEmail($email, $restriction, $error)
{
	global $txt;

	$db = database();

	// Can't ban an empty email
	if (empty($email) || trim($email) === '')
	{
		return;
	}

	// Let's start with the bans based on your IP/hostname/memberID...
	$ban_ids = isset($_SESSION['ban'][$restriction]) ? $_SESSION['ban'][$restriction]['ids'] : array();
	$ban_reason = isset($_SESSION['ban'][$restriction]) ? $_SESSION['ban'][$restriction]['reason'] : '';

	// ...and add to that the email address you're trying to register.
	$db->fetchQuery('
		SELECT 
			bi.id_ban, bg.' . $restriction . ', bg.cannot_access, bg.reason
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
		WHERE {string:email} LIKE bi.email_address
			AND (bg.' . $restriction . ' = {int:cannot_access} OR bg.cannot_access = {int:cannot_access})
			AND (bg.expire_time IS NULL OR bg.expire_time >= {int:now})',
		array(
			'email' => $email,
			'cannot_access' => 1,
			'now' => time(),
		)
	)->fetch_callback(
		function ($row) use (&$ban_ids, &$ban_reason, $restriction) {
			if (!empty($row['cannot_access']))
			{
				$_SESSION['ban']['cannot_access']['ids'][] = $row['id_ban'];
				$_SESSION['ban']['cannot_access']['reason'] = $row['reason'];
			}

			if (!empty($row[$restriction]))
			{
				$ban_ids[] = $row['id_ban'];
				$ban_reason = $row['reason'];
			}
		}
	);

	// You're in biiig trouble.  Banned for the rest of this session!
	if (isset($_SESSION['ban']['cannot_access']))
	{
		log_ban($_SESSION['ban']['cannot_access']['ids']);
		$_SESSION['ban']['last_checked'] = time();

		throw new \ElkArte\Exceptions\Exception(sprintf($txt['your_ban'], $txt['guest_title']) . $_SESSION['ban']['cannot_access']['reason'], false);
	}

	if (!empty($ban_ids))
	{
		// Log this ban for future reference.
		log_ban($ban_ids, $email);
		throw new \ElkArte\Exceptions\Exception($error . $ban_reason, false);
	}
}

/**
 * Make sure the user's correct session was passed, and they came from here.
 *
 * What it does:
 *
 * - Checks the current session, verifying that the person is who he or she should be.
 * - Also checks the referrer to make sure they didn't get sent here.
 * - Depends on the disableCheckUA setting, which is usually missing.
 * - Will check GET, POST, or REQUEST depending on the passed type.
 * - Also optionally checks the referring action if passed. (note that the referring action must be by GET.)
 *
 * @param string $type = 'post' (post, get, request)
 * @param string $from_action = ''
 * @param bool $is_fatal = true
 *
 * @return string the error message if is_fatal is false.
 */
function checkSession($type = 'post', $from_action = '', $is_fatal = true)
{
	global $modSettings, $boardurl;

	// We'll work out user agent checks
	$req = request();

	// Is it in as $_POST['sc']?
	if ($type === 'post')
	{
		$check = $_POST[$_SESSION['session_var']] ?? (empty($modSettings['strictSessionCheck']) && isset($_POST['sc']) ? $_POST['sc'] : null);
		if ($check !== $_SESSION['session_value'])
		{
			$error = 'session_timeout';
		}
	}
	// How about $_GET['sesc']?
	elseif ($type === 'get')
	{
		$check = $_GET[$_SESSION['session_var']] ?? (empty($modSettings['strictSessionCheck']) && isset($_GET['sesc']) ? $_GET['sesc'] : null);
		if ($check !== $_SESSION['session_value'])
		{
			$error = 'session_verify_fail';
		}
	}
	// Or can it be in either?
	elseif ($type === 'request')
	{
		$check = $_GET[$_SESSION['session_var']] ?? (empty($modSettings['strictSessionCheck']) && isset($_GET['sesc']) ? $_GET['sesc'] : ($_POST[$_SESSION['session_var']] ?? (empty($modSettings['strictSessionCheck']) && isset($_POST['sc']) ? $_POST['sc'] : null)));

		if ($check !== $_SESSION['session_value'])
		{
			$error = 'session_verify_fail';
		}
	}

	// Verify that they aren't changing user agents on us - that could be bad.
	if ((!isset($_SESSION['USER_AGENT']) || $_SESSION['USER_AGENT'] != $req->user_agent()) && empty($modSettings['disableCheckUA']))
	{
		$error = 'session_verify_fail';
	}

	// Make sure a page with session check requirement is not being prefetched.
	stop_prefetching();

	// Check the referring site - it should be the same server at least!
	$referrer_url = $_SESSION['request_referer'] ?? ($_SERVER['HTTP_REFERER'] ?? '');

	$referrer = @parse_url($referrer_url);

	if (!empty($referrer['host']))
	{
		if (strpos($_SERVER['HTTP_HOST'], ':') !== false)
		{
			$real_host = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
		}
		else
		{
			$real_host = $_SERVER['HTTP_HOST'];
		}

		$parsed_url = parse_url($boardurl);

		// Are global cookies on? If so, let's check them ;).
		if (!empty($modSettings['globalCookies']))
		{
			if (preg_match('~(?:[^\.]+\.)?([^\.]{3,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			{
				$parsed_url['host'] = $parts[1];
			}

			if (preg_match('~(?:[^\.]+\.)?([^\.]{3,}\..+)\z~i', $referrer['host'], $parts) == 1)
			{
				$referrer['host'] = $parts[1];
			}

			if (preg_match('~(?:[^\.]+\.)?([^\.]{3,}\..+)\z~i', $real_host, $parts) == 1)
			{
				$real_host = $parts[1];
			}
		}

		// Okay: referrer must either match parsed_url or real_host.
		if (isset($parsed_url['host']) && strtolower($referrer['host']) != strtolower($parsed_url['host']) && strtolower($referrer['host']) != strtolower($real_host))
		{
			$error = 'verify_url_fail';
			$log_error = true;
			$sprintf = array(Util::htmlspecialchars($referrer_url));
		}
	}

	// Well, first of all, if a from_action is specified you'd better have an old_url.
	if (!empty($from_action) && (!isset($_SESSION['old_url']) || preg_match('~[?;&]action=' . $from_action . '([;&]|$)~', $_SESSION['old_url']) == 0))
	{
		$error = 'verify_url_fail';
		$log_error = true;
		$sprintf = array(Util::htmlspecialchars($referrer_url));
	}

	// Everything is ok, return an empty string.
	if (!isset($error))
	{
		return '';
	}
	// A session error occurred, show the error.
	elseif ($is_fatal)
	{
		if (isset($_REQUEST['api']))
		{
			@ob_end_clean();
			Headers::instance()
				->removeHeader('all')
				->headerSpecial('HTTP/1.1 403 Forbidden - Session timeout')
				->sendHeaders();
			die;
		}
		else
		{
			throw new \ElkArte\Exceptions\Exception($error, isset($log_error) ? 'user' : false, $sprintf ?? array());
		}
	}
	// A session error occurred, return the error to the calling function.
	else
	{
		return $error;
	}

	// We really should never fall through here, for very important reasons.  Let's make sure.
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

/**
 * Let's give you a token of our appreciation.
 *
 * What it does:
 *
 * - Creates a one time use form token
 *
 * @param string $action The specific site action that a token will be generated for
 * @param string $type = 'post' If the token will be returned via post or get
 *
 * @return string[] array of token var, time, csrf, token
 */
function createToken($action, $type = 'post')
{
	global $context;

	// Generate a new token token_var pair
	$tokenizer = new TokenHash();
	$token_var = $tokenizer->generate_hash(rand(7, 12));
	$token = $tokenizer->generate_hash(32);

	// We need user agent and the client IP
	$req = request();
	$csrf_hash = hash('sha1', $token . $req->client_ip() . $req->user_agent());

	// Save the session token and make it available to the forms
	$_SESSION['token'][$type . '-' . $action] = array($token_var, $csrf_hash, time(), $token);
	$context[$action . '_token'] = $token;
	$context[$action . '_token_var'] = $token_var;

	return array($action . '_token_var' => $token_var, $action . '_token' => $token);
}

/**
 * Only patrons with valid tokens can ride this ride.
 *
 * What it does:
 *
 * Validates that the received token is correct
 *  1. The token exists in session.
 *  2. The {$type} variable should exist.
 *  3. We concatenate the variable we received with the user agent
 *  4. Match that result against what is in the session.
 *  5. If it matches, success, otherwise we fallout.
 *
 * @param string $action
 * @param string $type = 'post' (get, request, or post)
 * @param bool $reset = true Reset the token on failure
 * @param bool $fatal if true a fatal_lang_error is issued for invalid tokens, otherwise false is returned
 *
 * @return bool|string except for $action == 'login' where the token is returned
 * @throws \ElkArte\Exceptions\Exception token_verify_fail
 */
function validateToken($action, $type = 'post', $reset = true, $fatal = true)
{
	$type = ($type === 'get' || $type === 'request') ? $type : 'post';
	$token_index = $type . '-' . $action;

	// Logins are special: the token is used to have the password with javascript before POST it
	if ($action === 'login')
	{
		if (isset($_SESSION['token'][$token_index]))
		{
			$return = $_SESSION['token'][$token_index][3];
			unset($_SESSION['token'][$token_index]);

			return $return;
		}

		return '';
	}

	if (!isset($_SESSION['token'][$token_index]))
	{
		return false;
	}

	// We need the user agent and client IP
	$req = request();

	// Shortcut
	$passed_token_var = $GLOBALS['_' . strtoupper($type)][$_SESSION['token'][$token_index][0]] ?? null;
	$csrf_hash = hash('sha1', $passed_token_var . $req->client_ip() . $req->user_agent());

	// Checked what was passed in combination with the user agent
	if (isset($passed_token_var)
		&& $csrf_hash === $_SESSION['token'][$token_index][1])
	{
		// Consume the token, let them pass
		unset($_SESSION['token'][$token_index]);

		return true;
	}

	// Patrons with invalid tokens get the boot.
	if ($reset)
	{
		// Might as well do some cleanup on this.
		cleanTokens();

		// I'm back baby.
		createToken($action, $type);

		if ($fatal)
		{
			throw new \ElkArte\Exceptions\Exception('token_verify_fail', false);
		}
	}
	// You don't get a new token
	else
	{
		// Explicitly remove this token
		unset($_SESSION['token'][$token_index]);

		// Remove older tokens.
		cleanTokens();
	}

	return false;
}

/**
 * Removes old unused tokens from session
 *
 * What it does:
 *
 * - Defaults to 3 hours before a token is considered expired
 * - if $complete = true will remove all tokens
 *
 * @param bool $complete = false
 * @param string $suffix = false
 */
function cleanTokens($complete = false, $suffix = '')
{
	// We appreciate cleaning up after yourselves.
	if (!isset($_SESSION['token']))
	{
		return;
	}

	// Clean up tokens, trying to give enough time still.
	foreach ($_SESSION['token'] as $key => $data)
	{
		if (!empty($suffix))
		{
			$force = $complete || strpos($key, $suffix);
		}
		else
		{
			$force = $complete;
		}

		if ($data[2] + 10800 < time() || $force)
		{
			unset($_SESSION['token'][$key]);
		}
	}
}

/**
 * Check whether a form has been submitted twice.
 *
 * What it does:
 *
 * - Registers a sequence number for a form.
 * - Checks whether a submitted sequence number is registered in the current session.
 * - Depending on the value of is_fatal shows an error or returns true or false.
 * - Frees a sequence number from the stack after it's been checked.
 * - Frees a sequence number without checking if action == 'free'.
 *
 * @param string $action
 * @param bool $is_fatal = true
 *
 * @return bool
 * @throws \ElkArte\Exceptions\Exception error_form_already_submitted
 */
function checkSubmitOnce($action, $is_fatal = false)
{
	global $context;

	if (!isset($_SESSION['forms']))
	{
		$_SESSION['forms'] = array();
	}

	// Register a form number and store it in the session stack. (use this on the page that has the form.)
	if ($action === 'register')
	{
		$tokenizer = new TokenHash();
		$context['form_sequence_number'] = '';
		while (empty($context['form_sequence_number']) || in_array($context['form_sequence_number'], $_SESSION['forms']))
		{
			$context['form_sequence_number'] = $tokenizer->generate_hash();
		}
	}
	// Check whether the submitted number can be found in the session.
	elseif ($action === 'check')
	{
		if (!isset($_REQUEST['seqnum']))
		{
			return true;
		}
		elseif (!in_array($_REQUEST['seqnum'], $_SESSION['forms']))
		{
			// Mark this one as used
			$_SESSION['forms'][] = (string) $_REQUEST['seqnum'];

			return true;
		}
		elseif ($is_fatal)
		{
			throw new \ElkArte\Exceptions\Exception('error_form_already_submitted', false);
		}
		else
		{
			return false;
		}
	}
	// Don't check, just free the stack number.
	elseif ($action === 'free' && isset($_REQUEST['seqnum']) && in_array($_REQUEST['seqnum'], $_SESSION['forms']))
	{
		$_SESSION['forms'] = array_diff($_SESSION['forms'], array($_REQUEST['seqnum']));
	}
	elseif ($action !== 'free')
	{
		trigger_error('checkSubmitOnce(): Invalid action \'' . $action . '\'', E_USER_WARNING);
	}
}

/**
 * This function checks whether the user is allowed to do permission. (ie. post_new.)
 *
 * What it does:
 *
 * - If boards parameter is specified, checks those boards instead of the current one (if applicable).
 * - Always returns true if the user is an administrator.
 *
 * @param string[]|string $permission permission
 * @param int[]|int|null $boards array of board IDs, a single id or null
 *
 * @return bool if the user can do the permission
 */
function allowedTo($permission, $boards = null)
{
	$db = database();

	// You're always allowed to do nothing. (unless you're a working man, MR. LAZY :P!)
	if (empty($permission))
	{
		return true;
	}

	// You're never allowed to do something if your data hasn't been loaded yet!
	if (empty(User::$info) || !isset(User::$info['permissions']))
	{
		return false;
	}

	// Administrators are supermen :P.
	if (User::$info->is_admin)
	{
		return true;
	}

	// Make sure permission is a valid array
	if (!is_array($permission))
	{
		$permission = array($permission);
	}

	// Are we checking the _current_ board, or some other boards?
	if ($boards === null)
	{
		if (empty(User::$info->permissions))
		{
			return false;
		}

		// Check if they can do it, you aren't allowed, by default.
		return count(array_intersect($permission, User::$info->permissions)) !== 0;
	}

	if (!is_array($boards))
	{
		$boards = array($boards);
	}

	if (empty(User::$info->groups))
	{
		return false;
	}

	$request = $db->query('', '
		SELECT 
			MIN(bp.add_deny) AS add_deny
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}board_permissions AS bp ON (bp.id_profile = b.id_profile)
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
		WHERE b.id_board IN ({array_int:board_list})
			AND bp.id_group IN ({array_int:group_list}, {int:moderator_group})
			AND bp.permission IN ({array_string:permission_list})
			AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})
		GROUP BY b.id_board',
		array(
			'current_member' => User::$info->id,
			'board_list' => $boards,
			'group_list' => User::$info->groups,
			'moderator_group' => 3,
			'permission_list' => $permission,
		)
	);

	// Make sure they can do it on all the boards.
	if ($request->num_rows() != count($boards))
	{
		return false;
	}

	$result = true;
	while (($row = $request->fetch_assoc()))
	{
		$result = $result && !empty($row['add_deny']);
	}
	$request->free_result();

	// If the query returned 1, they can do it... otherwise, they can't.
	return $result;
}

/**
 * This function returns fatal error if the user doesn't have the respective permission.
 *
 * What it does:
 *
 * - Uses allowedTo() to check if the user is allowed to do permission.
 * - Checks the passed boards or current board for the permission.
 * - If they are not, it loads the Errors language file and shows an error using $txt['cannot_' . $permission].
 * - If they are a guest and cannot do it, this calls is_not_guest().
 *
 * @param string[]|string $permission array of or single string, of permissions to check
 * @param int[]|null $boards = null
 *
 * @throws \ElkArte\Exceptions\Exception cannot_xyz where xyz is the permission
 */
function isAllowedTo($permission, $boards = null)
{
	global $txt;

	static $heavy_permissions = array(
		'admin_forum',
		'manage_attachments',
		'manage_smileys',
		'manage_boards',
		'edit_news',
		'moderate_forum',
		'manage_bans',
		'manage_membergroups',
		'manage_permissions',
	);

	// Make it an array, even if a string was passed.
	$permission = is_array($permission) ? $permission : array($permission);

	// Check the permission and return an error...
	if (!allowedTo($permission, $boards))
	{
		// Pick the last array entry as the permission shown as the error.
		$error_permission = array_shift($permission);

		// If they are a guest, show a login. (because the error might be gone if they do!)
		if (User::$info->is_guest)
		{
			Txt::load('Errors');
			is_not_guest($txt['cannot_' . $error_permission]);
		}

		// Clear the action because they aren't really doing that!
		$_GET['action'] = '';
		$_GET['board'] = '';
		$_GET['topic'] = '';
		writeLog(true);

		throw new \ElkArte\Exceptions\Exception('cannot_' . $error_permission, false);
	}

	// If you're doing something on behalf of some "heavy" permissions, validate your session.
	// (take out the heavy permissions, and if you can't do anything but those, you need a validated session.)
	if (!allowedTo(array_diff($permission, $heavy_permissions), $boards))
	{
		validateSession();
	}
}

/**
 * Return the boards a user has a certain (board) permission on. (array(0) if all.)
 *
 * What it does:
 *
 * - Returns a list of boards on which the user is allowed to do the specified permission.
 * - Returns an array with only a 0 in it if the user has permission to do this on every board.
 * - Returns an empty array if he or she cannot do this on any board.
 * - If check_access is true will also make sure the group has proper access to that board.
 *
 * @param string[]|string $permissions array of permission names to check access against
 * @param bool $check_access = true
 * @param bool $simple = true Set $simple to true to use this function in compatibility mode
 *             otherwise, the resultant array becomes split into the multiple
 *             permissions that were passed. Other than that, it's just the normal
 *             state of play that you're used to.
 *
 * @return int[]
 * @throws \ElkArte\Exceptions\Exception
 */
function boardsAllowedTo($permissions, $check_access = true, $simple = true)
{
	$db = database();

	// Arrays are nice, most of the time.
	if (!is_array($permissions))
	{
		$permissions = [$permissions];
	}

	// I am the master, the master of the universe!
	if (User::$info->is_admin)
	{
		if ($simple)
		{
			return [0];
		}

		$boards = [];
		foreach ($permissions as $permission)
		{
			$boards[$permission] = [0];
		}

		return $boards;
	}

	// All groups the user is in except 'moderator'.
	$groups = array_diff(User::$info->groups, [3]);

	$boards = [];
	$deny_boards = [];
	$db->fetchQuery('
		SELECT 
			b.id_board, bp.add_deny' . ($simple ? '' : ', bp.permission') . '
		FROM {db_prefix}board_permissions AS bp
			INNER JOIN {db_prefix}boards AS b ON (b.id_profile = bp.id_profile)
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
		WHERE bp.id_group IN ({array_int:group_list}, {int:moderator_group})
			AND bp.permission IN ({array_string:permissions})
			AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})' .
		($check_access ? ' AND {query_see_board}' : ''),
		array(
			'current_member' => User::$info->id,
			'group_list' => $groups,
			'moderator_group' => 3,
			'permissions' => $permissions,
		)
	)->fetch_callback(
		function ($row) use ($simple, &$deny_boards, &$boards) {
			if ($simple)
			{
				if (empty($row['add_deny']))
				{
					$deny_boards[] = (int) $row['id_board'];
				}
				else
				{
					$boards[] = (int) $row['id_board'];
				}
			}
			elseif (empty($row['add_deny']))
			{
				$deny_boards[$row['permission']][] = (int) $row['id_board'];
			}
			else
			{
				$boards[$row['permission']][] = (int) $row['id_board'];
			}
		}
	);

	if ($simple)
	{
		$boards = array_unique(array_values(array_diff($boards, $deny_boards)));
	}
	else
	{
		foreach ($permissions as $permission)
		{
			// Never had it to start with
			if (empty($boards[$permission]))
			{
				$boards[$permission] = [];
			}
			else
			{
				// Or it may have been removed
				$deny_boards[$permission] = $deny_boards[$permission] ?? [];
				$boards[$permission] = array_unique(array_values(array_diff($boards[$permission], $deny_boards[$permission])));
			}
		}
	}

	return $boards;
}

/**
 * Returns whether an email address should be shown and how.
 *
 * What it does:
 *
 * Possible outcomes are:
 * - 'yes': show the full email address
 * - 'yes_permission_override': show the full email address, either you
 * are a moderator or it's your own email address.
 * - 'no_through_forum': don't show the email address, but do allow
 * things to be mailed using the built-in forum mailer.
 * - 'no': keep the email address hidden.
 *
 * @param bool $userProfile_hideEmail
 * @param int $userProfile_id
 *
 * @return string (yes, yes_permission_override, no_through_forum, no)
 */
function showEmailAddress($userProfile_hideEmail, $userProfile_id)
{
	// Should this user's email address be shown?
	// If you're guest: no.
	// If the user is post-banned: no.
	// If it's your own profile, and you've not set your address hidden: yes_permission_override.
	// If you're a moderator with sufficient permissions: yes_permission_override.
	// If the user has set their profile to do not email me: no.
	// Otherwise: no_through_forum. (don't show it but allow emailing the member)
	if (User::$info->is_guest || isset($_SESSION['ban']['cannot_post']))
	{
		return 'no';
	}

	if ((User::$info->is_guest === false && User::$info->id == $userProfile_id && !$userProfile_hideEmail))
	{
		return 'yes_permission_override';
	}

	if (allowedTo('moderate_forum'))
	{
		return 'yes_permission_override';
	}

	if ($userProfile_hideEmail)
	{
		return 'no';
	}

	return 'no_through_forum';
}

/**
 * This function attempts to protect from carrying out specific actions repeatedly.
 *
 * What it does:
 *
 * - Checks if a user is trying specific actions faster than a given minimum wait threshold.
 * - The time taken depends on error_type - generally uses the modSetting.
 * - Generates a fatal message when triggered, suspending execution.
 *
 * @event integrate_spam_protection Allows updating action wait timeOverrides
 * @param string $error_type used also as a $txt index. (not an actual string.)
 * @param bool $fatal is the spam check a fatal error on failure
 *
 * @return bool|int|mixed
 * @throws \ElkArte\Exceptions\Exception
 */
function spamProtection($error_type, $fatal = true)
{
	global $modSettings;

	$db = database();

	// Certain types take less/more time.
	$timeOverrides = array(
		'login' => 2,
		'register' => 2,
		'remind' => 30,
		'contact' => 30,
		'sendtopic' => $modSettings['spamWaitTime'] * 4,
		'sendmail' => $modSettings['spamWaitTime'] * 5,
		'reporttm' => $modSettings['spamWaitTime'] * 4,
		'search' => !empty($modSettings['search_floodcontrol_time']) ? $modSettings['search_floodcontrol_time'] : 1,
	);
	call_integration_hook('integrate_spam_protection', array(&$timeOverrides));

	// Moderators are free...
	if (!allowedTo('moderate_board'))
	{
		$timeLimit = $timeOverrides[$error_type] ?? $modSettings['spamWaitTime'];
	}
	else
	{
		$timeLimit = 2;
	}

	// Delete old entries...
	$db->query('', '
		DELETE FROM {db_prefix}log_floodcontrol
		WHERE log_time < {int:log_time}
			AND log_type = {string:log_type}',
		array(
			'log_time' => time() - $timeLimit,
			'log_type' => $error_type,
		)
	);

	// Add a new entry, deleting the old if necessary.
	$request = $db->replace(
		'{db_prefix}log_floodcontrol',
		array('ip' => 'string-16', 'log_time' => 'int', 'log_type' => 'string'),
		array(User::$info->ip, time(), $error_type),
		array('ip', 'log_type')
	);

	// If affected is 0 or 2, it was there already.
	if ($request->affected_rows() != 1)
	{
		// Spammer!  You only have to wait a *few* seconds!
		if ($fatal)
		{
			throw new \ElkArte\Exceptions\Exception($error_type . '_WaitTime_broken', false, array($timeLimit));
		}
		else
		{
			return $timeLimit;
		}
	}

	// They haven't posted within the limit.
	return false;
}

/**
 * A generic function to create a pair of index.php and .htaccess files in a directory
 *
 * @param string $path the (absolute) directory path
 * @param bool $allow_localhost if access should be allowed to localhost
 * @param string $files (optional, default '*') parameter for the Files tag
 *
 * @return string[]|string|bool on success error string if anything fails
 */
function secureDirectory($path, $allow_localhost = false, $files = '*')
{
	if (empty($path))
	{
		return 'empty_path';
	}

	if (!FileFunctions::instance()->isWritable($path))
	{
		return 'path_not_writable';
	}

	$directoryname = basename($path);

	// How deep is this from our boarddir
	$tree = explode(DIRECTORY_SEPARATOR, $path);
	$root = explode(DIRECTORY_SEPARATOR,BOARDDIR);
	$count = max(count($tree) - count($root), 0);

	$errors = array();

	if (file_exists($path . '/.htaccess'))
	{
		$errors[] = 'htaccess_exists';
	}
	else
	{
		$fh = @fopen($path . '/.htaccess', 'wb');
		if ($fh)
		{
			fwrite($fh, '# Apache 2.4
<IfModule mod_authz_core.c>
	Require all denied
	<Files ' . ($files === '*' ? $files : '~ ' . $files) . '>
		<RequireAll>
			Require all granted
			Require not env blockAccess' . (empty($allow_localhost) ? '
		</RequireAll>
	</Files>' : '
		Require host localhost
		</RequireAll>
	</Files>

	RemoveHandler .php .php3 .phtml .cgi .fcgi .pl .fpl .shtml') . '
</IfModule>

# Apache 2.2
<IfModule !mod_authz_core.c>
	Order Deny,Allow
	Deny from all

	<Files ' . $files . '>
		Allow from all' . (empty($allow_localhost) ? '
	</Files>' : '
		Allow from localhost
	</Files>

	RemoveHandler .php .php3 .phtml .cgi .fcgi .pl .fpl .shtml') . '
</IfModule>');
			fclose($fh);
		}
		$errors[] = 'htaccess_cannot_create_file';
	}

	if (file_exists($path . '/index.php'))
	{
		$errors[] = 'index-php_exists';
	}
	else
	{
		$fh = @fopen($path . '/index.php', 'wb');
		if ($fh)
		{
			fwrite($fh, '<?php

/**
 * This file is here solely to protect your ' . $directoryname . ' directory.
 */

// Look for Settings.php....
if (file_exists(dirname(__FILE__, ' . ($count + 1) . ') . \'/Settings.php\'))
{
	// Found it!
	require(dirname(__FILE__, ' . ($count + 1) . ') . \'/Settings.php\');
	header(\'Location: \' . $boardurl);
}
// Can\'t find it... just forget it.
else
	exit;');
			fclose($fh);
		}
		$errors[] = 'index-php_cannot_create_file';
	}

	if (!empty($errors))
	{
		return $errors;
	}
	else
	{
		return true;
	}
}

/**
 * Helper function that puts together a ban query for a given ip
 *
 * What it does:
 *
 * - Builds the query for ipv6, ipv4 or 255.255.255.255 depending on what's supplied
 *
 * @param string $fullip An IP address either IPv6 or not
 *
 * @return string A SQL condition
 */
function constructBanQueryIP($fullip)
{
	// First attempt a IPv6 address.
	if (isValidIPv6($fullip))
	{
		$ip_parts = convertIPv6toInts($fullip);

		$ban_query = '((' . $ip_parts[0] . ' BETWEEN bi.ip_low1 AND bi.ip_high1)
			AND (' . $ip_parts[1] . ' BETWEEN bi.ip_low2 AND bi.ip_high2)
			AND (' . $ip_parts[2] . ' BETWEEN bi.ip_low3 AND bi.ip_high3)
			AND (' . $ip_parts[3] . ' BETWEEN bi.ip_low4 AND bi.ip_high4)
			AND (' . $ip_parts[4] . ' BETWEEN bi.ip_low5 AND bi.ip_high5)
			AND (' . $ip_parts[5] . ' BETWEEN bi.ip_low6 AND bi.ip_high6)
			AND (' . $ip_parts[6] . ' BETWEEN bi.ip_low7 AND bi.ip_high7)
			AND (' . $ip_parts[7] . ' BETWEEN bi.ip_low8 AND bi.ip_high8))';
	}
	// Check if we have a valid IPv4 address.
	elseif (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $fullip, $ip_parts) == 1)
	{
		$ban_query = '((' . $ip_parts[1] . ' BETWEEN bi.ip_low1 AND bi.ip_high1)
			AND (' . $ip_parts[2] . ' BETWEEN bi.ip_low2 AND bi.ip_high2)
			AND (' . $ip_parts[3] . ' BETWEEN bi.ip_low3 AND bi.ip_high3)
			AND (' . $ip_parts[4] . ' BETWEEN bi.ip_low4 AND bi.ip_high4))';
	}
	// We use '255.255.255.255' for 'unknown' since it's not valid anyway.
	else
	{
		$ban_query = '(bi.ip_low1 = 255 AND bi.ip_high1 = 255
			AND bi.ip_low2 = 255 AND bi.ip_high2 = 255
			AND bi.ip_low3 = 255 AND bi.ip_high3 = 255
			AND bi.ip_low4 = 255 AND bi.ip_high4 = 255)';
	}

	return $ban_query;
}

/**
 * Decide if we are going to do any "bad behavior" scanning for this user
 *
 * What it does:
 *
 * - Admins and Moderators get a free pass
 * - Returns true if Accept header is missing
 * - Check with project Honey Pot for known miscreants
 *
 * @return bool true if bad, false otherwise
 */
function runBadBehavior()
{
	global $modSettings;

	// Admins and Mods get a free pass
	if (!empty(User::$info->is_moderator) || !empty(User::$info->is_admin))
	{
		return false;
	}

	// Clients will have an "Accept" header, generally only bots or scrappers don't
	if (!empty($modSettings['badbehavior_accept_header']) && !array_key_exists('HTTP_ACCEPT', $_SERVER))
	{
		return true;
	}

	// Do not block private IP ranges 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16 or 127.0.0.0/8
	if (preg_match('~^((10|172\.(1[6-9]|2\d|3[01])|192\.168|127)\.)~', $_SERVER['REMOTE_ADDR']) === 1)
	{
		return false;
	}

	// Project honey pot blacklist check [Your Access Key] [Octet-Reversed IP] [List-Specific Domain]
	if (empty($modSettings['badbehavior_httpbl_key']) || empty($_SERVER['REMOTE_ADDR']))
	{
		return false;
	}

	// Try to load it from the cache first
	$cache = Cache::instance();
	$dnsQuery = $modSettings['badbehavior_httpbl_key'] . '.' . implode('.', array_reverse(explode('.', $_SERVER['REMOTE_ADDR']))) . '.dnsbl.httpbl.org';
	if (!$cache->getVar($dnsResult, 'dnsQuery-' . $_SERVER['REMOTE_ADDR'], 240))
	{
		$dnsResult = gethostbyname($dnsQuery);
		$cache->put('dnsQuery-' . $_SERVER['REMOTE_ADDR'], $dnsResult, 240);
	}

	if (!empty($dnsResult) && $dnsResult !== $dnsQuery)
	{
		$result = explode('.', $dnsResult);
		$result = array_map('intval', $result);
		if ($result[0] === 127 // Valid Response
			&& ($result[3] & 3 || $result[3] & 5) // Listed as Suspicious + Harvester || Suspicious + Comment Spammer
			&& $result[2] >= $modSettings['badbehavior_httpbl_threat'] // Level
			&& $result[1] <= $modSettings['badbehavior_httpbl_maxage']) // Age
		{
			return true;
		}
	}

	return false;
}

/**
 * This protects against brute force attacks on a member's password.
 *
 * What it does:
 *
 * - Importantly, even if the password was right we DON'T TELL THEM!
 * - Allows 5 attempts every 10 seconds
 *
 * @param int $id_member
 * @param string|bool $password_flood_value = false or string joined on |'s
 * @param bool $was_correct = false
 *
 * @throws \ElkArte\Exceptions\Exception no_access
 */
function validatePasswordFlood($id_member, $password_flood_value = false, $was_correct = false)
{
	global $cookiename;

	// As this is only brute protection, we allow 5 attempts every 10 seconds.

	// Destroy any session or cookie data about this member, as they validated wrong.
	require_once(SUBSDIR . '/Auth.subs.php');
	setLoginCookie(-3600, 0);

	if (isset($_SESSION['login_' . $cookiename]))
	{
		unset($_SESSION['login_' . $cookiename]);
	}

	// We need a member!
	if (!$id_member)
	{
		// Redirect back!
		redirectexit();

		// Probably not needed, but still make sure...
		throw new \ElkArte\Exceptions\Exception('no_access', false);
	}

	// Let's just initialize to something (and 0 is better than nothing)
	$time_stamp = 0;
	$number_tries = 0;

	// Right, have we got a flood value?
	if ($password_flood_value !== false)
	{
		@list ($time_stamp, $number_tries) = explode('|', $password_flood_value);
	}

	// Timestamp invalid or non-existent?
	if (empty($number_tries) || $time_stamp < (time() - 10))
	{
		// If it wasn't *that* long ago, don't give them another five goes.
		$number_tries = !empty($number_tries) && $time_stamp < (time() - 20) ? 2 : $number_tries;
		$time_stamp = time();
	}

	$number_tries++;

	// Broken the law?
	if ($number_tries > 5)
	{
		throw new \ElkArte\Exceptions\Exception('login_threshold_brute_fail', 'critical');
	}

	// Otherwise set the members data. If they correct on their first attempt then we actually clear it, otherwise we set it!
	require_once(SUBSDIR . '/Members.subs.php');
	updateMemberData($id_member, array('passwd_flood' => $was_correct && $number_tries == 1 ? '' : $time_stamp . '|' . $number_tries));
}

/**
 * This sets the X-Frame-Options header.
 *
 * @param string|null $override the frame option, defaults to deny.
 */
function frameOptionsHeader($override = null)
{
	global $modSettings;

	$option = 'SAMEORIGIN';

	if (is_null($override) && !empty($modSettings['frame_security']))
	{
		$option = $modSettings['frame_security'];
	}
	elseif (in_array($override, array('SAMEORIGIN', 'DENY')))
	{
		$option = $override;
	}

	// Don't bother setting the header if we have disabled it.
	if ($option === 'DISABLE')
	{
		return;
	}

	// Finally set it.
	Headers::instance()->header('X-Frame-Options', $option);
}

/**
 * This adds additional security headers that may prevent browsers from doing something they should not
 *
 * What it does:
 *
 * - X-XSS-Protection header - This header enables the Cross-site scripting (XSS) filter
 * built into most recent web browsers. It's usually enabled by default, so the role of this
 * header is to re-enable the filter for this particular website if it was disabled by the user.
 * - X-Content-Type-Options header - It prevents the browser from doing MIME-type sniffing,
 * only IE and Chrome are honouring this header. This reduces exposure to drive-by download attacks
 * and sites serving user uploaded content that could be treated as executable or dynamic HTML files.
 *
 * @param bool|null $override
 */
function securityOptionsHeader($override = null)
{
	if ($override !== true)
	{
		Headers::instance()
			->header('X-XSS-Protection', '1')
			->header('X-Content-Type-Options', 'nosniff');
	}
}

/**
 * Stop some browsers pre fetching activity to reduce server load
 */
function stop_prefetching()
{
	if ((isset($_SERVER['HTTP_PURPOSE']) && $_SERVER['HTTP_PURPOSE'] === 'prefetch')
		|| (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] === 'prefetch'))
	{
		@ob_end_clean();
		Headers::instance()
			->removeHeader('all')
			->headerSpecial('HTTP/1.1 403 Prefetch Forbidden')
			->sendHeaders();
		die;
	}
}

/**
 * Check if the admin's session is active
 *
 * @return bool
 */
function isAdminSessionActive()
{
	global $modSettings;

	return empty($modSettings['securityDisable']) && (isset($_SESSION['admin_time']) && $_SESSION['admin_time'] + ($modSettings['admin_session_lifetime'] * 60) > time());
}

/**
 * Check if security files exist
 *
 * If files are found, populate $context['security_controls_files']:
 * * 'title'    - $txt['security_risk']
 * * 'errors'    - An array of strings with the key being the filename and the value an error with the filename in it
 *
 * @event integrate_security_files Allows adding / modifying security files array
 *
 * @return bool
 */
function checkSecurityFiles()
{
	global $txt, $context;

	$has_files = false;

	$securityFiles = array('install.php', 'upgrade.php', 'convert.php', 'repair_paths.php', 'repair_settings.php', 'Settings.php~', 'Settings_bak.php~');
	call_integration_hook('integrate_security_files', array(&$securityFiles));

	foreach ($securityFiles as $securityFile)
	{
		if (file_exists(BOARDDIR . '/' . $securityFile))
		{
			$has_files = true;

			$context['security_controls_files']['title'] = $txt['security_risk'];
			$context['security_controls_files']['errors'][$securityFile] = sprintf($txt['not_removed'], $securityFile);

			if ($securityFile === 'Settings.php~' || $securityFile === 'Settings_bak.php~')
			{
				$context['security_controls_files']['errors'][$securityFile] .= '<span class="smalltext">' . sprintf($txt['not_removed_extra'], $securityFile, substr($securityFile, 0, -1)) . '</span>';
			}
		}
	}

	return $has_files;
}

/**
 * The login URL should not redirect to certain areas (attachments, js actions, etc)
 * this function does these checks and return if the URL is valid or not.
 *
 * @param string $url - The URL to validate
 * @param bool $match_board - If true tries to match board|topic in the URL as well
 * @return bool
 */
function validLoginUrl($url, $match_board = false)
{
	if (empty($url))
	{
		return false;
	}

	if (substr($url, 0, 7) !== 'http://' && substr($url, 0, 8) !== 'https://')
	{
		return false;
	}

	$invalid_strings = array('dlattach' => '~(board|topic)[=,]~', 'jslocale' => '', 'login' => '');
	call_integration_hook('integrate_validLoginUrl', array(&$invalid_strings));

	foreach ($invalid_strings as $invalid_string => $valid_match)
	{
		if (strpos($url, $invalid_string) !== false || ($match_board === true && !empty($valid_match) && preg_match($valid_match, $url) == 0))
		{
			return false;
		}
	}

	return true;
}
