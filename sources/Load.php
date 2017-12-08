<?php

/**
 * This file has the hefty job of loading information for the forum.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1.1
 *
 */

/**
 * Load the $modSettings array and many necessary forum settings.
 *
 * What it does:
 *
 * - load the settings from cache if available, otherwise from the database.
 * - sets the timezone
 * - checks the load average settings if available.
 * - check whether post moderation is enabled.
 * - calls add_integration_function
 * - calls integrate_pre_include, integrate_pre_load,
 *
 * @event integrate_load_average is called if load average is enabled
 * @event integrate_pre_include to allow including files at startup
 * @event integrate_pre_load to call any pre load integration functions.
 *
 * @global array $modSettings is a giant array of all of the forum-wide settings and statistics.
 */
function reloadSettings()
{
	global $modSettings;

	$db = database();
	$cache = Cache::instance();
	$hooks = Hooks::instance();

	// Try to load it from the cache first; it'll never get cached if the setting is off.
	if (!$cache->getVar($modSettings, 'modSettings', 90))
	{
		$request = $db->query('', '
			SELECT variable, value
			FROM {db_prefix}settings',
			array(
			)
		);
		$modSettings = array();
		if (!$request)
			Errors::instance()->display_db_error();
		while ($row = $db->fetch_row($request))
			$modSettings[$row[0]] = $row[1];
		$db->free_result($request);

		// Do a few things to protect against missing settings or settings with invalid values...
		if (empty($modSettings['defaultMaxTopics']) || $modSettings['defaultMaxTopics'] <= 0 || $modSettings['defaultMaxTopics'] > 999)
			$modSettings['defaultMaxTopics'] = 20;
		if (empty($modSettings['defaultMaxMessages']) || $modSettings['defaultMaxMessages'] <= 0 || $modSettings['defaultMaxMessages'] > 999)
			$modSettings['defaultMaxMessages'] = 15;
		if (empty($modSettings['defaultMaxMembers']) || $modSettings['defaultMaxMembers'] <= 0 || $modSettings['defaultMaxMembers'] > 999)
			$modSettings['defaultMaxMembers'] = 30;
		if (empty($modSettings['subject_length']))
			$modSettings['subject_length'] = 24;

		$modSettings['warning_enable'] = $modSettings['warning_settings'][0];

		// @deprecated since 1.1.0 - Just in case the upgrade script was run before B3
		if (empty($modSettings['cal_limityear']))
		{
			$modSettings['cal_limityear'] = 10;
			updateSettings(array(
				'cal_limityear' => 10
			));
		}

		$cache->put('modSettings', $modSettings, 90);
	}

	$hooks->loadIntegrations();

	// Setting the timezone is a requirement for some functions in PHP >= 5.1.
	if (isset($modSettings['default_timezone']))
		date_default_timezone_set($modSettings['default_timezone']);

	// Check the load averages?
	if (!empty($modSettings['loadavg_enable']))
	{
		if (!$cache->getVar($modSettings['load_average'], 'loadavg', 90))
		{
			require_once(SUBSDIR . '/Server.subs.php');
			$modSettings['load_average'] = detectServerLoad();

			$cache->put('loadavg', $modSettings['load_average'], 90);
		}

		if ($modSettings['load_average'] !== false)
			call_integration_hook('integrate_load_average', array($modSettings['load_average']));

		// Let's have at least a zero
		if (empty($modSettings['loadavg_forum']) || $modSettings['load_average'] === false)
			$modSettings['current_load'] = 0;
		else
			$modSettings['current_load'] = $modSettings['load_average'];

		if (!empty($modSettings['loadavg_forum']) && $modSettings['current_load'] >= $modSettings['loadavg_forum'])
			Errors::instance()->display_loadavg_error();
	}
	else
		$modSettings['current_load'] = 0;

	// Is post moderation alive and well?
	$modSettings['postmod_active'] = isset($modSettings['admin_features']) ? in_array('pm', explode(',', $modSettings['admin_features'])) : true;

	// @deprecated since 1.0.6 compatibility setting for migration
	if (!isset($modSettings['avatar_max_height']))
	{
		$modSettings['avatar_max_height'] = isset($modSettings['avatar_max_height_external']) ? $modSettings['avatar_max_height_external'] : 65;
	}
	if (!isset($modSettings['avatar_max_width']))
	{
		$modSettings['avatar_max_width'] = isset($modSettings['avatar_max_width_external']) ? $modSettings['avatar_max_width_external'] : 65;
	}

	if (!isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) == 'off')
	{
		$modSettings['secureCookies'] = 0;
	}

	// Here to justify the name of this function. :P
	// It should be added to the install and upgrade scripts.
	// But since the converters need to be updated also. This is easier.
	if (empty($modSettings['currentAttachmentUploadDir']))
	{
		updateSettings(array(
			'attachmentUploadDir' => serialize(array(1 => $modSettings['attachmentUploadDir'])),
			'currentAttachmentUploadDir' => 1,
		));
	}

	// Integration is cool.
	if (defined('ELK_INTEGRATION_SETTINGS'))
	{
		$integration_settings = Util::unserialize(ELK_INTEGRATION_SETTINGS);
		foreach ($integration_settings as $hook => $function)
			add_integration_function($hook, $function);
	}

	// Any files to pre include?
	call_integration_include_hook('integrate_pre_include');

	// Call pre load integration functions.
	call_integration_hook('integrate_pre_load');
}

/**
 * Load all the important user information.
 *
 * What it does:
 *
 * - sets up the $user_info array
 * - assigns $user_info['query_wanna_see_board'] for what boards the user can see.
 * - first checks for cookie or integration validation.
 * - uses the current session if no integration function or cookie is found.
 * - checks password length, if member is activated and the login span isn't over.
 * - if validation fails for the user, $id_member is set to 0.
 * - updates the last visit time when needed.
 *
 * @event integrate_verify_user allow for integration to verify a user
 * @event integrate_user_info to allow for adding to $user_info array
 */
function loadUserSettings()
{
	global $context, $modSettings, $user_settings, $cookiename, $user_info, $language;

	$db = database();
	$cache = Cache::instance();

	// Check first the integration, then the cookie, and last the session.
	if (count($integration_ids = call_integration_hook('integrate_verify_user')) > 0)
	{
		$id_member = 0;
		foreach ($integration_ids as $integration_id)
		{
			$integration_id = (int) $integration_id;
			if ($integration_id > 0)
			{
				$id_member = $integration_id;
				$already_verified = true;
				break;
			}
		}
	}
	else
		$id_member = 0;

	// We'll need IPs and user agent and stuff, they came to visit us with!
	$req = request();

	if (empty($id_member) && isset($_COOKIE[$cookiename]))
	{
		list ($id_member, $password) = serializeToJson($_COOKIE[$cookiename], function ($array_from) use ($cookiename) {
			global $modSettings;

			require_once(SUBSDIR . '/Auth.subs.php');
			$_COOKIE[$cookiename] = json_encode($array_from);
			setLoginCookie(60 * $modSettings['cookieTime'], $array_from[0], $array_from[1]);
		});
		$id_member = !empty($id_member) && strlen($password) > 0 ? (int) $id_member : 0;
	}
	elseif (empty($id_member) && isset($_SESSION['login_' . $cookiename]) && (!empty($modSettings['disableCheckUA']) || $_SESSION['USER_AGENT'] == $req->user_agent()))
	{
		// @todo Perhaps we can do some more checking on this, such as on the first octet of the IP?
		list ($id_member, $password, $login_span) = serializeToJson($_SESSION['login_' . $cookiename], function ($array_from) use ($cookiename) {
			$_SESSION['login_' . $cookiename] = json_encode($array_from);
		});
		$id_member = !empty($id_member) && strlen($password) == 64 && $login_span > time() ? (int) $id_member : 0;
	}

	// Only load this stuff if the user isn't a guest.
	if ($id_member != 0)
	{
		// Is the member data cached?
		if ($cache->levelLowerThan(2) || $cache->getVar($user_settings, 'user_settings-' . $id_member, 60) === false)
		{
			$this_user = $db->fetchQuery('
				SELECT mem.*, COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = {int:id_member})
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $id_member,
				)
			);

			if (!empty($this_user))
			{
				list ($user_settings) = $this_user;

				// Make the ID specifically an integer
				$user_settings['id_member'] = (int) $user_settings['id_member'];
			}

			if ($cache->levelHigherThan(1))
				$cache->put('user_settings-' . $id_member, $user_settings, 60);
		}

		// Did we find 'im?  If not, junk it.
		if (!empty($user_settings))
		{
			// As much as the password should be right, we can assume the integration set things up.
			if (!empty($already_verified) && $already_verified === true)
				$check = true;
			// SHA-256 passwords should be 64 characters long.
			elseif (strlen($password) == 64)
				$check = hash('sha256', ($user_settings['passwd'] . $user_settings['password_salt'])) == $password;
			else
				$check = false;

			// Wrong password or not activated - either way, you're going nowhere.
			$id_member = $check && ($user_settings['is_activated'] == 1 || $user_settings['is_activated'] == 11) ? (int) $user_settings['id_member'] : 0;
		}
		else
			$id_member = 0;

		// If we no longer have the member maybe they're being all hackey, stop brute force!
		if (!$id_member)
			validatePasswordFlood(!empty($user_settings['id_member']) ? $user_settings['id_member'] : $id_member, !empty($user_settings['passwd_flood']) ? $user_settings['passwd_flood'] : false, $id_member != 0);
	}

	// Found 'im, let's set up the variables.
	if ($id_member != 0)
	{
		// Let's not update the last visit time in these cases...
		// 1. SSI doesn't count as visiting the forum.
		// 2. RSS feeds and XMLHTTP requests don't count either.
		// 3. If it was set within this session, no need to set it again.
		// 4. New session, yet updated < five hours ago? Maybe cache can help.
		if (ELK != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != '.xml') && empty($_SESSION['id_msg_last_visit']) && (!$cache->isEnabled() || !$cache->getVar($_SESSION['id_msg_last_visit'], 'user_last_visit-' . $id_member, 5 * 3600)))
		{
			// @todo can this be cached?
			// Do a quick query to make sure this isn't a mistake.
			require_once(SUBSDIR . '/Messages.subs.php');
			$visitOpt = basicMessageInfo($user_settings['id_msg_last_visit'], true);

			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

			// If it was *at least* five hours ago...
			if ($visitOpt['poster_time'] < time() - 5 * 3600)
			{
				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData($id_member, array('id_msg_last_visit' => (int) $modSettings['maxMsgID'], 'last_login' => time(), 'member_ip' => $req->client_ip(), 'member_ip2' => $req->ban_ip()));
				$user_settings['last_login'] = time();

				if ($cache->levelHigherThan(1))
					$cache->put('user_settings-' . $id_member, $user_settings, 60);

				$cache->put('user_last_visit-' . $id_member, $_SESSION['id_msg_last_visit'], 5 * 3600);
			}
		}
		elseif (empty($_SESSION['id_msg_last_visit']))
			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

		$username = $user_settings['member_name'];

		if (empty($user_settings['additional_groups']))
			$user_info = array(
				'groups' => array($user_settings['id_group'], $user_settings['id_post_group'])
			);
		else
			$user_info = array(
				'groups' => array_merge(
					array($user_settings['id_group'], $user_settings['id_post_group']),
					explode(',', $user_settings['additional_groups'])
				)
			);

		// Because history has proven that it is possible for groups to go bad - clean up in case.
		foreach ($user_info['groups'] as $k => $v)
			$user_info['groups'][$k] = (int) $v;

		// This is a logged in user, so definitely not a spider.
		$user_info['possibly_robot'] = false;
	}
	// If the user is a guest, initialize all the critical user settings.
	else
	{
		// This is what a guest's variables should be.
		$username = '';
		$user_info = array('groups' => array(-1));
		$user_settings = array();

		if (isset($_COOKIE[$cookiename]))
			$_COOKIE[$cookiename] = '';

		// Create a login token if it doesn't exist yet.
		if (!isset($_SESSION['token']['post-login']))
			createToken('login');
		else
			list ($context['login_token_var'],,, $context['login_token']) = $_SESSION['token']['post-login'];

		// Do we perhaps think this is a search robot? Check every five minutes just in case...
		if ((!empty($modSettings['spider_mode']) || !empty($modSettings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300))
		{
			require_once(SUBSDIR . '/SearchEngines.subs.php');
			$user_info['possibly_robot'] = spiderCheck();
		}
		elseif (!empty($modSettings['spider_mode']))
			$user_info['possibly_robot'] = isset($_SESSION['id_robot']) ? $_SESSION['id_robot'] : 0;
		// If we haven't turned on proper spider hunts then have a guess!
		else
		{
			$ci_user_agent = strtolower($req->user_agent());
			$user_info['possibly_robot'] = (strpos($ci_user_agent, 'mozilla') === false && strpos($ci_user_agent, 'opera') === false) || preg_match('~(googlebot|slurp|crawl|msnbot|yandex|bingbot|baidu)~u', $ci_user_agent) == 1;
		}
	}

	// Set up the $user_info array.
	$user_info += array(
		'id' => $id_member,
		'username' => $username,
		'name' => isset($user_settings['real_name']) ? $user_settings['real_name'] : '',
		'email' => isset($user_settings['email_address']) ? $user_settings['email_address'] : '',
		'passwd' => isset($user_settings['passwd']) ? $user_settings['passwd'] : '',
		'language' => empty($user_settings['lngfile']) || empty($modSettings['userLanguage']) ? $language : $user_settings['lngfile'],
		'is_guest' => $id_member == 0,
		'is_admin' => in_array(1, $user_info['groups']),
		'is_mod' => false,
		'theme' => empty($user_settings['id_theme']) ? 0 : $user_settings['id_theme'],
		'last_login' => empty($user_settings['last_login']) ? 0 : $user_settings['last_login'],
		'ip' => $req->client_ip(),
		'ip2' => $req->ban_ip(),
		'posts' => empty($user_settings['posts']) ? 0 : $user_settings['posts'],
		'time_format' => empty($user_settings['time_format']) ? $modSettings['time_format'] : $user_settings['time_format'],
		'time_offset' => empty($user_settings['time_offset']) ? 0 : $user_settings['time_offset'],
		'avatar' => array_merge(array(
			'url' => isset($user_settings['avatar']) ? $user_settings['avatar'] : '',
			'filename' => empty($user_settings['filename']) ? '' : $user_settings['filename'],
			'custom_dir' => !empty($user_settings['attachment_type']) && $user_settings['attachment_type'] == 1,
			'id_attach' => isset($user_settings['id_attach']) ? $user_settings['id_attach'] : 0
		), determineAvatar($user_settings)),
		'smiley_set' => isset($user_settings['smiley_set']) ? $user_settings['smiley_set'] : '',
		'messages' => empty($user_settings['personal_messages']) ? 0 : $user_settings['personal_messages'],
		'mentions' => empty($user_settings['mentions']) ? 0 : max(0, $user_settings['mentions']),
		'unread_messages' => empty($user_settings['unread_messages']) ? 0 : $user_settings['unread_messages'],
		'total_time_logged_in' => empty($user_settings['total_time_logged_in']) ? 0 : $user_settings['total_time_logged_in'],
		'buddies' => !empty($modSettings['enable_buddylist']) && !empty($user_settings['buddy_list']) ? explode(',', $user_settings['buddy_list']) : array(),
		'ignoreboards' => !empty($user_settings['ignore_boards']) && !empty($modSettings['allow_ignore_boards']) ? explode(',', $user_settings['ignore_boards']) : array(),
		'ignoreusers' => !empty($user_settings['pm_ignore_list']) ? explode(',', $user_settings['pm_ignore_list']) : array(),
		'warning' => isset($user_settings['warning']) ? $user_settings['warning'] : 0,
		'permissions' => array(),
	);
	$user_info['groups'] = array_unique($user_info['groups']);

	// Make sure that the last item in the ignore boards array is valid.  If the list was too long it could have an ending comma that could cause problems.
	if (!empty($user_info['ignoreboards']) && empty($user_info['ignoreboards'][$tmp = count($user_info['ignoreboards']) - 1]))
		unset($user_info['ignoreboards'][$tmp]);

	// Do we have any languages to validate this?
	if (!empty($modSettings['userLanguage']) && (!empty($_GET['language']) || !empty($_SESSION['language'])))
		$languages = getLanguages();

	// Allow the user to change their language if its valid.
	if (!empty($modSettings['userLanguage']) && !empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')]))
	{
		$user_info['language'] = strtr($_GET['language'], './\\:', '____');
		$_SESSION['language'] = $user_info['language'];
	}
	elseif (!empty($modSettings['userLanguage']) && !empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')]))
		$user_info['language'] = strtr($_SESSION['language'], './\\:', '____');

	// Just build this here, it makes it easier to change/use - administrators can see all boards.
	if ($user_info['is_admin'])
		$user_info['query_see_board'] = '1=1';
	// Otherwise just the groups in $user_info['groups'].
	else
		$user_info['query_see_board'] = '((FIND_IN_SET(' . implode(', b.member_groups) != 0 OR FIND_IN_SET(', $user_info['groups']) . ', b.member_groups) != 0)' . (!empty($modSettings['deny_boards_access']) ? ' AND (FIND_IN_SET(' . implode(', b.deny_member_groups) = 0 AND FIND_IN_SET(', $user_info['groups']) . ', b.deny_member_groups) = 0)' : '') . (isset($user_info['mod_cache']) ? ' OR ' . $user_info['mod_cache']['mq'] : '') . ')';
	// Build the list of boards they WANT to see.
	// This will take the place of query_see_boards in certain spots, so it better include the boards they can see also

	// If they aren't ignoring any boards then they want to see all the boards they can see
	if (empty($user_info['ignoreboards']))
		$user_info['query_wanna_see_board'] = $user_info['query_see_board'];
	// Ok I guess they don't want to see all the boards
	else
		$user_info['query_wanna_see_board'] = '(' . $user_info['query_see_board'] . ' AND b.id_board NOT IN (' . implode(',', $user_info['ignoreboards']) . '))';

	call_integration_hook('integrate_user_info');
}

/**
 * Check for moderators and see if they have access to the board.
 *
 * What it does:
 *
 * - sets up the $board_info array for current board information.
 * - if cache is enabled, the $board_info array is stored in cache.
 * - redirects to appropriate post if only message id is requested.
 * - is only used when inside a topic or board.
 * - determines the local moderators for the board.
 * - adds group id 3 if the user is a local moderator for the board they are in.
 * - prevents access if user is not in proper group nor a local moderator of the board.
 *
 * @event integrate_load_board_query allows to add tables and columns to the query, used
 * to add to the $board_info array
 * @event integrate_loaded_board called after board_info is populated, allows to add
 * directly to $board_info
 *
 */
function loadBoard()
{
	global $txt, $scripturl, $context, $modSettings;
	global $board_info, $board, $topic, $user_info;

	$db = database();
	$cache = Cache::instance();

	// Assume they are not a moderator.
	$user_info['is_mod'] = false;
	// @since 1.0.5 - is_mod takes into account only local (board) moderators,
	// and not global moderators, is_moderator is meant to take into account both.
	$user_info['is_moderator'] = false;

	// Start the linktree off empty..
	$context['linktree'] = array();

	// Have they by chance specified a message id but nothing else?
	if (empty($_REQUEST['action']) && empty($topic) && empty($board) && !empty($_REQUEST['msg']))
	{
		// Make sure the message id is really an int.
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Looking through the message table can be slow, so try using the cache first.
		if (!$cache->getVar($topic, 'msg_topic-' . $_REQUEST['msg'], 120))
		{
			require_once(SUBSDIR . '/Messages.subs.php');
			$topic = associatedTopic($_REQUEST['msg']);

			// So did it find anything?
			if ($topic !== false)
			{
				// Save save save.
				$cache->put('msg_topic-' . $_REQUEST['msg'], $topic, 120);
			}
		}

		// Remember redirection is the key to avoiding fallout from your bosses.
		if (!empty($topic))
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
		else
		{
			loadPermissions();
			loadTheme();
			throw new Elk_Exception('topic_gone', false);
		}
	}

	// Load this board only if it is specified.
	if (empty($board) && empty($topic))
	{
		$board_info = array('moderators' => array());
		return;
	}

	if ($cache->isEnabled() && (empty($topic) || $cache->levelHigherThan(2)))
	{
		// @todo SLOW?
		if (!empty($topic))
			$temp = $cache->get('topic_board-' . $topic, 120);
		else
			$temp = $cache->get('board-' . $board, 120);

		if (!empty($temp))
		{
			$board_info = $temp;
			$board = (int) $board_info['id'];
		}
	}

	if (empty($temp))
	{
		$select_columns = array();
		$select_tables = array();
		// Wanna grab something more from the boards table or another table at all?
		call_integration_hook('integrate_load_board_query', array(&$select_columns, &$select_tables));

		$request = $db->query('', '
			SELECT
				c.id_cat, b.name AS bname, b.description, b.num_topics, b.member_groups, b.deny_member_groups,
				b.id_parent, c.name AS cname, COALESCE(mem.id_member, 0) AS id_moderator,
				mem.real_name' . (!empty($topic) ? ', b.id_board' : '') . ', b.child_level,
				b.id_theme, b.override_theme, b.count_posts, b.id_profile, b.redirect,
				b.unapproved_topics, b.unapproved_posts' . (!empty($topic) ? ', t.approved, t.id_member_started' : '') . (!empty($select_columns) ? ', ' . implode(', ', $select_columns) : '') . '
			FROM {db_prefix}boards AS b' . (!empty($topic) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})' : '') . (!empty($select_tables) ? '
				' . implode("\n\t\t\t\t", $select_tables) : '') . '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = {raw:board_link})
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
			WHERE b.id_board = {raw:board_link}',
			array(
				'current_topic' => $topic,
				'board_link' => empty($topic) ? $db->quote('{int:current_board}', array('current_board' => $board)) : 't.id_board',
			)
		);
		// If there aren't any, skip.
		if ($db->num_rows($request) > 0)
		{
			$row = $db->fetch_assoc($request);

			// Set the current board.
			if (!empty($row['id_board']))
				$board = (int) $row['id_board'];

			// Basic operating information. (globals... :/)
			$board_info = array(
				'id' => $board,
				'moderators' => array(),
				'cat' => array(
					'id' => $row['id_cat'],
					'name' => $row['cname']
				),
				'name' => $row['bname'],
				'raw_description' => $row['description'],
				'description' => $row['description'],
				'num_topics' => $row['num_topics'],
				'unapproved_topics' => $row['unapproved_topics'],
				'unapproved_posts' => $row['unapproved_posts'],
				'unapproved_user_topics' => 0,
				'parent_boards' => getBoardParents($row['id_parent']),
				'parent' => $row['id_parent'],
				'child_level' => $row['child_level'],
				'theme' => $row['id_theme'],
				'override_theme' => !empty($row['override_theme']),
				'profile' => $row['id_profile'],
				'redirect' => $row['redirect'],
				'posts_count' => empty($row['count_posts']),
				'cur_topic_approved' => empty($topic) || $row['approved'],
				'cur_topic_starter' => empty($topic) ? 0 : $row['id_member_started'],
			);

			// Load the membergroups allowed, and check permissions.
			$board_info['groups'] = $row['member_groups'] == '' ? array() : explode(',', $row['member_groups']);
			$board_info['deny_groups'] = $row['deny_member_groups'] == '' ? array() : explode(',', $row['deny_member_groups']);

			call_integration_hook('integrate_loaded_board', array(&$board_info, &$row));

			do
			{
				if (!empty($row['id_moderator']))
					$board_info['moderators'][$row['id_moderator']] = array(
						'id' => $row['id_moderator'],
						'name' => $row['real_name'],
						'href' => $scripturl . '?action=profile;u=' . $row['id_moderator'],
						'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
					);
			}
			while ($row = $db->fetch_assoc($request));

			// If the board only contains unapproved posts and the user can't approve then they can't see any topics.
			// If that is the case do an additional check to see if they have any topics waiting to be approved.
			if ($board_info['num_topics'] == 0 && $modSettings['postmod_active'] && !allowedTo('approve_posts'))
			{
				// Free the previous result
				$db->free_result($request);

				// @todo why is this using id_topic?
				// @todo Can this get cached?
				$request = $db->query('', '
					SELECT COUNT(id_topic)
					FROM {db_prefix}topics
					WHERE id_member_started={int:id_member}
						AND approved = {int:unapproved}
						AND id_board = {int:board}',
					array(
						'id_member' => $user_info['id'],
						'unapproved' => 0,
						'board' => $board,
					)
				);

				list ($board_info['unapproved_user_topics']) = $db->fetch_row($request);
			}

			if ($cache->isEnabled() && (empty($topic) || $cache->levelHigherThan(2)))
			{
				// @todo SLOW?
				if (!empty($topic))
					$cache->put('topic_board-' . $topic, $board_info, 120);
				$cache->put('board-' . $board, $board_info, 120);
			}
		}
		else
		{
			// Otherwise the topic is invalid, there are no moderators, etc.
			$board_info = array(
				'moderators' => array(),
				'error' => 'exist'
			);
			$topic = null;
			$board = 0;
		}
		$db->free_result($request);
	}

	if (!empty($topic))
		$_GET['board'] = (int) $board;

	if (!empty($board))
	{
		// Now check if the user is a moderator.
		$user_info['is_mod'] = isset($board_info['moderators'][$user_info['id']]);

		if (count(array_intersect($user_info['groups'], $board_info['groups'])) == 0 && !$user_info['is_admin'])
			$board_info['error'] = 'access';
		if (!empty($modSettings['deny_boards_access']) && count(array_intersect($user_info['groups'], $board_info['deny_groups'])) != 0 && !$user_info['is_admin'])
			$board_info['error'] = 'access';

		// Build up the linktree.
		$context['linktree'] = array_merge(
			$context['linktree'],
			array(array(
				'url' => $scripturl . $modSettings['default_forum_action'] . '#c' . $board_info['cat']['id'],
				'name' => $board_info['cat']['name']
			)),
			array_reverse($board_info['parent_boards']),
			array(array(
				'url' => $scripturl . '?board=' . $board . '.0',
				'name' => $board_info['name']
			))
		);
	}

	// Set the template contextual information.
	$context['user']['is_mod'] = &$user_info['is_mod'];
	$context['user']['is_moderator'] = &$user_info['is_moderator'];
	$context['current_topic'] = $topic;
	$context['current_board'] = $board;

	// Hacker... you can't see this topic, I'll tell you that. (but moderators can!)
	if (!empty($board_info['error']) && (!empty($modSettings['deny_boards_access']) || $board_info['error'] != 'access' || !$user_info['is_moderator']))
	{
		// The permissions and theme need loading, just to make sure everything goes smoothly.
		loadPermissions();
		loadTheme();

		$_GET['board'] = '';
		$_GET['topic'] = '';

		// The linktree should not give the game away mate!
		$context['linktree'] = array(
			array(
				'url' => $scripturl,
				'name' => $context['forum_name_html_safe']
			)
		);

		// If it's a prefetching agent, stop it
		stop_prefetching();

		// If we're requesting an attachment.
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'dlattach')
		{
			ob_end_clean();
			header('HTTP/1.1 403 Forbidden');
			exit;
		}
		elseif ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			is_not_guest($txt['topic_gone']);
		}
		else
			throw new Elk_Exception('topic_gone', false);
	}

	if ($user_info['is_mod'])
		$user_info['groups'][] = 3;
}

/**
 * Load this user's permissions.
 *
 * What it does:
 *
 * - If the user is an admin, validate that they have not been banned.
 * - Attempt to load permissions from cache for cache level > 2
 * - See if the user is possibly a robot and apply added permissions as needed
 * - Load permissions from the general permissions table.
 * - If inside a board load the necessary board permissions.
 * - If the user is not a guest, identify what other boards they have access to.
 */
function loadPermissions()
{
	global $user_info, $board, $board_info, $modSettings;

	$db = database();

	if ($user_info['is_admin'])
	{
		banPermissions();
		return;
	}

	$removals = array();

	$cache = Cache::instance();

	if ($cache->isEnabled())
	{
		$cache_groups = $user_info['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);

		// If it's a spider then cache it different.
		if ($user_info['possibly_robot'])
			$cache_groups .= '-spider';

		$temp = array();
		if ($cache->levelHigherThan(1) && !empty($board) && $cache->getVar($temp, 'permissions:' . $cache_groups . ':' . $board, 240) && time() - 240 > $modSettings['settings_updated'])
		{
			list ($user_info['permissions']) = $temp;
			banPermissions();

			return;
		}
		elseif ($cache->getVar($temp, 'permissions:' . $cache_groups, 240) && time() - 240 > $modSettings['settings_updated'])
		{
			if (is_array($temp))
			{
				list ($user_info['permissions'], $removals) = $temp;
			}
		}
	}

	// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
	$spider_restrict = $user_info['possibly_robot'] && !empty($modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

	if (empty($user_info['permissions']))
	{
		// Get the general permissions.
		$request = $db->query('', '
			SELECT 
				permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:member_groups})
				' . $spider_restrict,
			array(
				'member_groups' => $user_info['groups'],
				'spider_group' => !empty($modSettings['spider_group']) && $modSettings['spider_group'] != 1 ? $modSettings['spider_group'] : 0,
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		$db->free_result($request);

		if (isset($cache_groups))
			$cache->put('permissions:' . $cache_groups, array($user_info['permissions'], !empty($removals) ? $removals : array()), 2);
	}

	// Get the board permissions.
	if (!empty($board))
	{
		// Make sure the board (if any) has been loaded by loadBoard().
		if (!isset($board_info['profile']))
			throw new Elk_Exception('no_board');

		$request = $db->query('', '
			SELECT 
				permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE (id_group IN ({array_int:member_groups})
				' . $spider_restrict . ')
				AND id_profile = {int:id_profile}',
			array(
				'member_groups' => $user_info['groups'],
				'id_profile' => $board_info['profile'],
				'spider_group' => !empty($modSettings['spider_group']) && $modSettings['spider_group'] != 1 ? $modSettings['spider_group'] : 0,
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		$db->free_result($request);
	}

	// Remove all the permissions they shouldn't have ;).
	if (!empty($modSettings['permission_enable_deny']))
		$user_info['permissions'] = array_diff($user_info['permissions'], $removals);

	if (isset($cache_groups) && !empty($board) && $cache->levelHigherThan(1))
		$cache->put('permissions:' . $cache_groups . ':' . $board, array($user_info['permissions'], null), 240);

	// Banned?  Watch, don't touch..
	banPermissions();

	// Load the mod cache so we can know what additional boards they should see, but no sense in doing it for guests
	if (!$user_info['is_guest'])
	{
		$user_info['is_moderator'] = $user_info['is_mod'] || allowedTo('moderate_board');
		if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= $modSettings['settings_updated'])
		{
			require_once(SUBSDIR . '/Auth.subs.php');
			rebuildModCache();
		}
		else
			$user_info['mod_cache'] = $_SESSION['mc'];
	}
}

/**
 * Loads an array of users' data by ID or member_name.
 *
 * @event integrate_load_member_data allows to add to the columns & tables for $user_profile
 * array population
 * @event integrate_add_member_data called after data is loaded, allows integration
 * to directly add to the user_profile array
 *
 * @param int[]|int|string[]|string $users An array of users by id or name
 * @param bool $is_name = false $users is by name or by id
 * @param string $set = 'normal' What kind of data to load (normal, profile, minimal)
 *
 * @return array|bool The ids of the members loaded or false
 */
function loadMemberData($users, $is_name = false, $set = 'normal')
{
	global $user_profile, $modSettings, $board_info, $context, $user_info;

	$db = database();
	$cache = Cache::instance();

	// Can't just look for no users :P.
	if (empty($users))
		return false;

	// Pass the set value
	$context['loadMemberContext_set'] = $set;

	// Make sure it's an array.
	$users = !is_array($users) ? array($users) : array_unique($users);
	$loaded_ids = array();

	if (!$is_name && $cache->isEnabled() && $cache->levelHigherThan(2))
	{
		$users = array_values($users);
		for ($i = 0, $n = count($users); $i < $n; $i++)
		{
			$data = $cache->get('member_data-' . $set . '-' . $users[$i], 240);
			if ($cache->isMiss())
				continue;

			$loaded_ids[] = $data['id_member'];
			$user_profile[$data['id_member']] = $data;
			unset($users[$i]);
		}
	}

	// Used by default
	$select_columns = '
			COALESCE(lo.log_time, 0) AS is_online, COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.signature, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.member_ip, mem.member_ip2, mem.posts, mem.last_login, mem.likes_given, mem.likes_received,
			mem.karma_good, mem.id_post_group, mem.karma_bad, mem.lngfile, mem.id_group, mem.time_offset, mem.show_online,
			mg.online_color AS member_group_color, COALESCE(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, COALESCE(pg.group_name, {string:blank_string}) AS post_group,
			mem.is_activated, mem.warning, ' . (!empty($modSettings['titlesEnable']) ? 'mem.usertitle, ' : '') . '
			CASE WHEN mem.id_group = 0 OR mg.icons = {string:blank_string} THEN pg.icons ELSE mg.icons END AS icons';
	$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';

	// We add or replace according to the set
	switch ($set)
	{
		case 'normal':
			$select_columns .= ', mem.buddy_list';
			break;
		case 'profile':
			$select_columns .= ', mem.openid_uri, mem.id_theme, mem.pm_ignore_list, mem.pm_email_notify, mem.receive_from,
			mem.time_format, mem.secret_question, mem.additional_groups, mem.smiley_set,
			mem.total_time_logged_in, mem.notify_announcements, mem.notify_regularity, mem.notify_send_body,
			mem.notify_types, lo.url, mem.ignore_boards, mem.password_salt, mem.pm_prefs, mem.buddy_list, mem.otp_secret, mem.enable_otp';
			break;
		case 'minimal':
			$select_columns = '
			mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.hide_email, mem.date_registered,
			mem.posts, mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group';
			$select_tables = '';
			break;
		default:
			trigger_error('loadMemberData(): Invalid member data set \'' . $set . '\'', E_USER_WARNING);
	}

	// Allow addons to easily add to the selected member data
	call_integration_hook('integrate_load_member_data', array(&$select_columns, &$select_tables, $set));

	if (!empty($users))
	{
		// Load the member's data.
		$request = $db->query('', '
			SELECT' . $select_columns . '
			FROM {db_prefix}members AS mem' . $select_tables . '
			WHERE mem.' . ($is_name ? 'member_name' : 'id_member') . (count($users) == 1 ? ' = {' . ($is_name ? 'string' : 'int') . ':users}' : ' IN ({' . ($is_name ? 'array_string' : 'array_int') . ':users})'),
			array(
				'blank_string' => '',
				'users' => count($users) == 1 ? current($users) : $users,
			)
		);
		$new_loaded_ids = array();
		while ($row = $db->fetch_assoc($request))
		{
			$new_loaded_ids[] = $row['id_member'];
			$loaded_ids[] = $row['id_member'];
			$row['options'] = array();
			$user_profile[$row['id_member']] = $row;
		}
		$db->free_result($request);
	}

	// Custom profile fields as well
	if (!empty($new_loaded_ids) && !empty($user_info['id']) && $set !== 'minimal' && (in_array('cp', $context['admin_features'])))
	{
		$request = $db->query('', '
			SELECT id_member, variable, value
			FROM {db_prefix}custom_fields_data
			WHERE id_member' . (count($new_loaded_ids) == 1 ? ' = {int:loaded_ids}' : ' IN ({array_int:loaded_ids})'),
			array(
				'loaded_ids' => count($new_loaded_ids) == 1 ? $new_loaded_ids[0] : $new_loaded_ids,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$user_profile[$row['id_member']]['options'][$row['variable']] = $row['value'];
		$db->free_result($request);
	}

	// Anything else integration may want to add to the user_profile array
	if (!empty($new_loaded_ids))
		call_integration_hook('integrate_add_member_data', array($new_loaded_ids, $set));

	if (!empty($new_loaded_ids) && $cache->levelHigherThan(2))
	{
		for ($i = 0, $n = count($new_loaded_ids); $i < $n; $i++)
			$cache->put('member_data-' . $set . '-' . $new_loaded_ids[$i], $user_profile[$new_loaded_ids[$i]], 240);
	}

	// Are we loading any moderators?  If so, fix their group data...
	if (!empty($loaded_ids) && !empty($board_info['moderators']) && $set === 'normal' && count($temp_mods = array_intersect($loaded_ids, array_keys($board_info['moderators']))) !== 0)
	{
		$group_info = array();
		if (!$cache->getVar($group_info, 'moderator_group_info', 480))
		{
			require_once(SUBSDIR . '/Membergroups.subs.php');
			$group_info = membergroupById(3, true);

			$cache->put('moderator_group_info', $group_info, 480);
		}

		foreach ($temp_mods as $id)
		{
			// By popular demand, don't show admins or global moderators as moderators.
			if ($user_profile[$id]['id_group'] != 1 && $user_profile[$id]['id_group'] != 2)
				$user_profile[$id]['member_group'] = $group_info['group_name'];

			// If the Moderator group has no color or icons, but their group does... don't overwrite.
			if (!empty($group_info['icons']))
				$user_profile[$id]['icons'] = $group_info['icons'];
			if (!empty($group_info['online_color']))
				$user_profile[$id]['member_group_color'] = $group_info['online_color'];
		}
	}

	return empty($loaded_ids) ? false : $loaded_ids;
}

/**
 * Loads the user's basic values... meant for template/theme usage.
 *
 * What it does:
 *
 * - Always loads the minimal values of username, name, id, href, link, email, show_email, registered, registered_timestamp
 * - if $context['loadMemberContext_set'] is not minimal it will load in full a full set of user information
 * - prepares signature for display (censoring if enabled)
 * - loads in the members custom fields if any
 * - prepares the users buddy list, including reverse buddy flags
 *
 * @event integrate_member_context allows to manipulate $memberContext[user]
 * @param int $user
 * @param bool $display_custom_fields = false
 *
 * @return boolean
 */
function loadMemberContext($user, $display_custom_fields = false)
{
	global $memberContext, $user_profile, $txt, $scripturl, $user_info;
	global $context, $modSettings, $settings;
	static $dataLoaded = array();

	// If this person's data is already loaded, skip it.
	if (isset($dataLoaded[$user]))
		return true;

	// We can't load guests or members not loaded by loadMemberData()!
	if ($user == 0)
		return false;

	if (!isset($user_profile[$user]))
	{
		trigger_error('loadMemberContext(): member id ' . $user . ' not previously loaded by loadMemberData()', E_USER_WARNING);
		return false;
	}

	$parsers = \BBC\ParserWrapper::instance();

	// Well, it's loaded now anyhow.
	$dataLoaded[$user] = true;
	$profile = $user_profile[$user];

	// Censor everything.
	$profile['signature'] = censor($profile['signature']);

	// TODO: We should look into a censoring toggle for custom fields

	// Set things up to be used before hand.
	$profile['signature'] = str_replace(array("\n", "\r"), array('<br />', ''), $profile['signature']);
	$profile['signature'] = $parsers->parseSignature($profile['signature'], true);
	$profile['is_online'] = (!empty($profile['show_online']) || allowedTo('moderate_forum')) && $profile['is_online'] > 0;
	$profile['icons'] = empty($profile['icons']) ? array('', '') : explode('#', $profile['icons']);

	// Setup the buddy status here (One whole in_array call saved :P)
	$profile['buddy'] = in_array($profile['id_member'], $user_info['buddies']);
	$buddy_list = !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : array();

	// These minimal values are always loaded
	$memberContext[$user] = array(
		'username' => $profile['member_name'],
		'name' => $profile['real_name'],
		'id' => $profile['id_member'],
		'href' => $scripturl . '?action=profile;u=' . $profile['id_member'],
		'link' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['profile_of'] . ' ' . trim($profile['real_name']) . '">' . $profile['real_name'] . '</a>',
		'email' => $profile['email_address'],
		'show_email' => showEmailAddress(!empty($profile['hide_email']), $profile['id_member']),
		'registered_raw' => empty($profile['date_registered']) ? 0 : $profile['date_registered'],
		'registered' => empty($profile['date_registered']) ? $txt['not_applicable'] : standardTime($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : forum_time(true, $profile['date_registered']),
	);

	// If the set isn't minimal then load the monstrous array.
	if ($context['loadMemberContext_set'] !== 'minimal')
	{
		$memberContext[$user] += array(
			'username_color' => '<span ' . (!empty($profile['member_group_color']) ? 'style="color:' . $profile['member_group_color'] . ';"' : '') . '>' . $profile['member_name'] . '</span>',
			'name_color' => '<span ' . (!empty($profile['member_group_color']) ? 'style="color:' . $profile['member_group_color'] . ';"' : '') . '>' . $profile['real_name'] . '</span>',
			'link_color' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['profile_of'] . ' ' . $profile['real_name'] . '" ' . (!empty($profile['member_group_color']) ? 'style="color:' . $profile['member_group_color'] . ';"' : '') . '>' . $profile['real_name'] . '</a>',
			'is_buddy' => $profile['buddy'],
			'is_reverse_buddy' => in_array($user_info['id'], $buddy_list),
			'buddies' => $buddy_list,
			'title' => !empty($modSettings['titlesEnable']) ? $profile['usertitle'] : '',
			'website' => array(
				'title' => $profile['website_title'],
				'url' => $profile['website_url'],
			),
			'birth_date' => empty($profile['birthdate']) || $profile['birthdate'] === '0001-01-01' ? '0000-00-00' : (substr($profile['birthdate'], 0, 4) === '0004' ? '0000' . substr($profile['birthdate'], 4) : $profile['birthdate']),
			'signature' => $profile['signature'],
			'real_posts' => $profile['posts'],
			'posts' => comma_format($profile['posts']),
			'avatar' => determineAvatar($profile),
			'last_login' => empty($profile['last_login']) ? $txt['never'] : standardTime($profile['last_login']),
			'last_login_timestamp' => empty($profile['last_login']) ? 0 : forum_time(false, $profile['last_login']),
			'karma' => array(
				'good' => $profile['karma_good'],
				'bad' => $profile['karma_bad'],
				'allow' => !$user_info['is_guest'] && !empty($modSettings['karmaMode']) && $user_info['id'] != $user && allowedTo('karma_edit') &&
				($user_info['posts'] >= $modSettings['karmaMinPosts'] || $user_info['is_admin']),
			),
			'likes' => array(
				'given' => $profile['likes_given'],
				'received' => $profile['likes_received']
			),
			'ip' => htmlspecialchars($profile['member_ip'], ENT_COMPAT, 'UTF-8'),
			'ip2' => htmlspecialchars($profile['member_ip2'], ENT_COMPAT, 'UTF-8'),
			'online' => array(
				'is_online' => $profile['is_online'],
				'text' => Util::htmlspecialchars($txt[$profile['is_online'] ? 'online' : 'offline']),
				'member_online_text' => sprintf($txt[$profile['is_online'] ? 'member_is_online' : 'member_is_offline'], Util::htmlspecialchars($profile['real_name'])),
				'href' => $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'],
				'link' => '<a href="' . $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'] . '">' . $txt[$profile['is_online'] ? 'online' : 'offline'] . '</a>',
				'label' => $txt[$profile['is_online'] ? 'online' : 'offline']
			),
			'language' => Util::ucwords(strtr($profile['lngfile'], array('_' => ' '))),
			'is_activated' => isset($profile['is_activated']) ? $profile['is_activated'] : 1,
			'is_banned' => isset($profile['is_activated']) ? $profile['is_activated'] >= 10 : 0,
			'options' => $profile['options'],
			'is_guest' => false,
			'group' => $profile['member_group'],
			'group_color' => $profile['member_group_color'],
			'group_id' => $profile['id_group'],
			'post_group' => $profile['post_group'],
			'post_group_color' => $profile['post_group_color'],
			'group_icons' => str_repeat('<img src="' . str_replace('$language', $context['user']['language'], isset($profile['icons'][1]) ? $settings['images_url'] . '/group_icons/' . $profile['icons'][1] : '') . '" alt="[*]" />', empty($profile['icons'][0]) || empty($profile['icons'][1]) ? 0 : $profile['icons'][0]),
			'warning' => $profile['warning'],
			'warning_status' => !empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $profile['warning'] ? 'mute' : (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $profile['warning'] ? 'moderate' : (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $profile['warning'] ? 'watch' : (''))),
			'local_time' => standardTime(time() + ($profile['time_offset'] - $user_info['time_offset']) * 3600, false),
			'custom_fields' => array(),
		);
	}

	// Are we also loading the members custom fields into context?
	if ($display_custom_fields && !empty($modSettings['displayFields']))
	{
		if (!isset($context['display_fields']))
			$context['display_fields'] = Util::unserialize($modSettings['displayFields']);

		foreach ($context['display_fields'] as $custom)
		{
			if (!isset($custom['title']) || trim($custom['title']) == '' || empty($profile['options'][$custom['colname']]))
				continue;

			$value = $profile['options'][$custom['colname']];

			// BBC?
			if ($custom['bbc'])
				$value = $parsers->parseCustomFields($value);
			// ... or checkbox?
			elseif (isset($custom['type']) && $custom['type'] == 'check')
				$value = $value ? $txt['yes'] : $txt['no'];

			// Enclosing the user input within some other text?
			if (!empty($custom['enclose']))
				$value = strtr($custom['enclose'], array(
					'{SCRIPTURL}' => $scripturl,
					'{IMAGES_URL}' => $settings['images_url'],
					'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
					'{INPUT}' => $value,
				));

			$memberContext[$user]['custom_fields'][] = array(
				'title' => $custom['title'],
				'colname' => $custom['colname'],
				'value' => $value,
				'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
			);
		}
	}

	call_integration_hook('integrate_member_context', array($user, $display_custom_fields));
	return true;
}

/**
 * Loads information about what browser the user is viewing with and places it in $context
 *
 * @uses Browser_Detector class from BrowserDetect.class.php
 */
function detectBrowser()
{
	// Load the current user's browser of choice
	$detector = new Browser_Detector;
	$detector->detectBrowser();
}

/**
 * Get the id of a theme
 *
 * @param int $id_theme
 * @return int
 */
function getThemeId($id_theme = 0)
{
	global $modSettings, $user_info, $board_info, $ssi_theme;

	// The theme was specified by parameter.
	if (!empty($id_theme))
		$id_theme = (int) $id_theme;
	// The theme was specified by REQUEST.
	elseif (!empty($_REQUEST['theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
	{
		$id_theme = (int) $_REQUEST['theme'];
		$_SESSION['id_theme'] = $id_theme;
	}
	// The theme was specified by REQUEST... previously.
	elseif (!empty($_SESSION['id_theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
		$id_theme = (int) $_SESSION['id_theme'];
	// The theme is just the user's choice. (might use ?board=1;theme=0 to force board theme.)
	elseif (!empty($user_info['theme']) && !isset($_REQUEST['theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
		$id_theme = $user_info['theme'];
	// The theme was specified by the board.
	elseif (!empty($board_info['theme']))
		$id_theme = $board_info['theme'];
	// The theme is the forum's default.
	else
		$id_theme = $modSettings['theme_guests'];

	call_integration_hook('integrate_customize_theme_id', array(&$id_theme));

	// Verify the id_theme... no foul play.
	// Always allow the board specific theme, if they are overriding.
	if (!empty($board_info['theme']) && $board_info['override_theme'])
		$id_theme = $board_info['theme'];
	// If they have specified a particular theme to use with SSI allow it to be used.
	elseif (!empty($ssi_theme) && $id_theme == $ssi_theme)
		$id_theme = (int) $id_theme;
	elseif (!empty($modSettings['knownThemes']) && !allowedTo('admin_forum'))
	{
		$themes = explode(',', $modSettings['knownThemes']);
		if (!in_array($id_theme, $themes))
			$id_theme = $modSettings['theme_guests'];
		else
			$id_theme = (int) $id_theme;
	}
	else
		$id_theme = (int) $id_theme;

	return $id_theme;
}

/**
 * Load in the theme variables for a given theme / member combination
 *
 * @param int $id_theme
 * @param int $member
 *
 * @return array
 */
function getThemeData($id_theme, $member)
{
	global $modSettings, $boardurl;

	$cache = Cache::instance();

	// Do we already have this members theme data and specific options loaded (for aggressive cache settings)
	$temp = array();
	if ($cache->levelHigherThan(1) && $cache->getVar($temp, 'theme_settings-' . $id_theme . ':' . $member, 60) && time() - 60 > $modSettings['settings_updated'])
	{
		$themeData = $temp;
		$flag = true;
	}
	// Or do we just have the system wide theme settings cached
	elseif ($cache->getVar($temp, 'theme_settings-' . $id_theme, 90) && time() - 60 > $modSettings['settings_updated'])
		$themeData = $temp + array($member => array());
	// Nothing at all then
	else
		$themeData = array(-1 => array(), 0 => array(), $member => array());

	if (empty($flag))
	{
		$db = database();

		// Load variables from the current or default theme, global or this user's.
		$result = $db->query('', '
			SELECT variable, value, id_member, id_theme
			FROM {db_prefix}themes
			WHERE id_member' . (empty($themeData[0]) ? ' IN (-1, 0, {int:id_member})' : ' = {int:id_member}') . '
				AND id_theme' . ($id_theme == 1 ? ' = {int:id_theme}' : ' IN ({int:id_theme}, 1)'),
			array(
				'id_theme' => $id_theme,
				'id_member' => $member,
			)
		);

		$immutable_theme_data = array('actual_theme_url', 'actual_images_url', 'base_theme_dir', 'base_theme_url', 'default_images_url', 'default_theme_dir', 'default_theme_url', 'default_template', 'images_url', 'number_recent_posts', 'smiley_sets_default', 'theme_dir', 'theme_id', 'theme_layers', 'theme_templates', 'theme_url');

		// Pick between $settings and $options depending on whose data it is.
		while ($row = $db->fetch_assoc($result))
		{
			// There are just things we shouldn't be able to change as members.
			if ($row['id_member'] != 0 && in_array($row['variable'], $immutable_theme_data))
				continue;

			// If this is the theme_dir of the default theme, store it.
			if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1' && empty($row['id_member']))
				$themeData[0]['default_' . $row['variable']] = $row['value'];

			// If this isn't set yet, is a theme option, or is not the default theme..
			if (!isset($themeData[$row['id_member']][$row['variable']]) || $row['id_theme'] != '1')
				$themeData[$row['id_member']][$row['variable']] = substr($row['variable'], 0, 5) == 'show_' ? $row['value'] == '1' : $row['value'];
		}
		$db->free_result($result);

		if (file_exists($themeData[0]['default_theme_dir'] . '/cache') && is_writable($themeData[0]['default_theme_dir'] . '/cache'))
		{
			$themeData[0]['default_theme_cache_dir'] = $themeData[0]['default_theme_dir'] . '/cache';
			$themeData[0]['default_theme_cache_url'] = $themeData[0]['default_theme_url'] . '/cache';
		}
		else
		{
			$themeData[0]['default_theme_cache_dir'] = CACHEDIR;
			$themeData[0]['default_theme_cache_url'] = $boardurl . '/cache';
		}

		// Set the defaults if the user has not chosen on their own
		if (!empty($themeData[-1]))
		{
			foreach ($themeData[-1] as $k => $v)
			{
				if (!isset($themeData[$member][$k]))
					$themeData[$member][$k] = $v;
			}
		}

		// If being aggressive we save the site wide and member theme settings
		if ($cache->levelHigherThan(1))
			$cache->put('theme_settings-' . $id_theme . ':' . $member, $themeData, 60);
		// Only if we didn't already load that part of the cache...
		elseif (!isset($temp))
			$cache->put('theme_settings-' . $id_theme, array(-1 => $themeData[-1], 0 => $themeData[0]), 90);
	}

	return $themeData;
}

/**
 * Initialize a theme for use
 *
 * @param int $id_theme
 */
function initTheme($id_theme = 0)
{
	global $user_info, $settings, $options, $context;

	// Validate / fetch the themes id
	$id_theme = getThemeId($id_theme);

	// Need to know who we are loading the theme for
	$member = empty($user_info['id']) ? -1 : $user_info['id'];

	// Load in the theme variables for them
	$themeData = getThemeData($id_theme, $member);
	$settings = $themeData[0];
	$options = $themeData[$member];

	$settings['theme_id'] = $id_theme;
	$settings['actual_theme_url'] = $settings['theme_url'];
	$settings['actual_images_url'] = $settings['images_url'];
	$settings['actual_theme_dir'] = $settings['theme_dir'];

	// Reload the templates
	Templates::instance()->reloadDirectories($settings);

	// Setup the default theme file. In the future, this won't exist and themes will just have to extend it if they want
	require_once($settings['default_theme_dir'] . '/Theme.php');
	$default_theme_instance = new \Themes\DefaultTheme\Theme(1);

	// Check if there is a Theme file
	if ($id_theme != 1 && !empty($settings['theme_dir']) && file_exists($settings['theme_dir'] . '/Theme.php'))
	{
		require_once($settings['theme_dir'] . '/Theme.php');

		$class = '\\Themes\\' . basename(ucfirst($settings['theme_dir'])) . 'Theme\\Theme';

		$theme = new $class($id_theme);

		$context['theme_instance'] = $theme;
	}
	else
	{
		$context['theme_instance'] = $default_theme_instance;
	}
}

/**
 * Load a theme, by ID.
 *
 * What it does:
 *
 * - identify the theme to be loaded.
 * - validate that the theme is valid and that the user has permission to use it
 * - load the users theme settings and site settings into $options.
 * - prepares the list of folders to search for template loading.
 * - identify what smiley set to use.
 * - sets up $context['user']
 * - detects the users browser and sets a mobile friendly environment if needed
 * - loads default JS variables for use in every theme
 * - loads default JS scripts for use in every theme
 *
 * @event integrate_init_theme used to call initialization theme integration functions and
 * change / update $settings
 * @event integrate_theme_include allows to include files at this point
 * @event integrate_load_theme calls functions after theme is loaded
 * @param int $id_theme = 0
 * @param bool $initialize = true
 */
function loadTheme($id_theme = 0, $initialize = true)
{
	global $user_info, $user_settings;
	global $txt, $scripturl, $mbname, $modSettings;
	global $context, $settings, $options;

	initTheme($id_theme);

	if (!$initialize)
		return;

	loadThemeUrls();

	loadUserContext();

	// Set up some additional interface preference context
	if (!empty($options['admin_preferences']))
	{
		$context['admin_preferences'] = serializeToJson($options['admin_preferences'], function ($array_form) {
			global $context;

			$context['admin_preferences'] = $array_form;
			require_once(SUBSDIR . '/Admin.subs.php');
			updateAdminPreferences();
		});
	}
	else
	{
		$context['admin_preferences'] = array();
	}

	if (!$user_info['is_guest'])
	{
		if (!empty($options['minmax_preferences']))
		{
			$context['minmax_preferences'] = serializeToJson($options['minmax_preferences'], function ($array_form) {
				global $settings, $user_info;

				// Update the option.
				require_once(SUBSDIR . '/Themes.subs.php');
				updateThemeOptions(array($settings['theme_id'], $user_info['id'], 'minmax_preferences', json_encode($array_form)));
			});
		}
		else
		{
			$context['minmax_preferences'] = array();
		}
	}
	// Guest may have collapsed the header, check the cookie to prevent collapse jumping
	elseif ($user_info['is_guest'] && isset($_COOKIE['upshrink']))
		$context['minmax_preferences'] = array('upshrink' => $_COOKIE['upshrink']);

	// Load the basic layers
	theme()->loadDefaultLayers();

	// @todo when are these set before loadTheme(0, true)?
	loadThemeContext();

	// @todo These really don't belong in loadTheme() since they are more general than the theme.
	$context['session_var'] = $_SESSION['session_var'];
	$context['session_id'] = $_SESSION['session_value'];
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = $context['forum_name'];
	$context['current_action'] = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
	$context['current_subaction'] = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : null;

	// Set some permission related settings.
	if ($user_info['is_guest'] && !empty($modSettings['enableVBStyleLogin']))
	{
		$context['show_login_bar'] = true;
		$context['theme_header_callbacks'][] = 'login_bar';
		loadJavascriptFile('sha256.js', array('defer' => true));
	}

	// This determines the server... not used in many places, except for login fixing.
	detectServer();

	// Detect the browser. This is separated out because it's also used in attachment downloads
	detectBrowser();

	// Set the top level linktree up.
	array_unshift($context['linktree'], array(
		'url' => $scripturl,
		'name' => $context['forum_name']
	));

	// Just some mobile-friendly settings
	if ($context['browser_body_id'] == 'mobile')
	{
		// Disable the preview text.
		$modSettings['message_index_preview'] = 0;
		// Force the usage of click menu instead of a hover menu.
		$options['use_click_menu'] = 1;
		// No space left for a sidebar
		$options['use_sidebar_menu'] = false;
		// Disable the search dropdown.
		$modSettings['search_dropdown'] = false;
	}

	if (!isset($txt))
		$txt = array();

	// Defaults in case of odd things
	$settings['avatars_on_indexes'] = 0;

	// Initialize the theme.
	if (function_exists('template_init'))
		$settings = array_merge($settings, template_init());

	// Call initialization theme integration functions.
	call_integration_hook('integrate_init_theme', array($id_theme, &$settings));

	// Guests may still need a name.
	if ($context['user']['is_guest'] && empty($context['user']['name']))
		$context['user']['name'] = $txt['guest_title'];

	// Any theme-related strings that need to be loaded?
	if (!empty($settings['require_theme_strings']))
		loadLanguage('ThemeStrings', '', false);

	theme()->loadSupportCSS();

	// We allow theme variants, because we're cool.
	if (!empty($settings['theme_variants']))
	{
		theme()->loadThemeVariant();
	}

	// A bit lonely maybe, though I think it should be set up *after* the theme variants detection
	$context['header_logo_url_html_safe'] = empty($settings['header_logo_url']) ? $settings['images_url'] . '/' . $context['theme_variant_url'] . 'logo_elk.png' : Util::htmlspecialchars($settings['header_logo_url']);
	$context['right_to_left'] = !empty($txt['lang_rtl']);

	// Allow overriding the board wide time/number formats.
	if (empty($user_settings['time_format']) && !empty($txt['time_format']))
		$user_info['time_format'] = $txt['time_format'];

	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'always')
	{
		$settings['theme_url'] = $settings['default_theme_url'];
		$settings['images_url'] = $settings['default_images_url'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}

	// Make a special URL for the language.
	$settings['lang_images_url'] = $settings['images_url'] . '/' . (!empty($txt['image_lang']) ? $txt['image_lang'] : $user_info['language']);

	// RTL languages require an additional stylesheet.
	if ($context['right_to_left'])
		loadCSSFile('rtl.css');

	if (!empty($context['theme_variant']) && $context['right_to_left'])
		loadCSSFile($context['theme_variant'] . '/rtl' . $context['theme_variant'] . '.css');

	// This allows us to change the way things look for the admin.
	$context['admin_features'] = isset($modSettings['admin_features']) ? explode(',', $modSettings['admin_features']) : array('cd,cp,k,w,rg,ml,pm');

	if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		$context['newsfeed_urls'] = array(
			'rss' => $scripturl . '?action=.xml;type=rss2;limit=' . (!empty($modSettings['xmlnews_limit']) ? $modSettings['xmlnews_limit'] : 5),
			'atom' => $scripturl . '?action=.xml;type=atom;limit=' . (!empty($modSettings['xmlnews_limit']) ? $modSettings['xmlnews_limit'] : 5)
		);

	theme()->loadThemeJavascript();

	Hooks::instance()->newPath(array('$themedir' => $settings['theme_dir']));

	// Any files to include at this point?
	call_integration_include_hook('integrate_theme_include');

	// Call load theme integration functions.
	call_integration_hook('integrate_load_theme');

	// We are ready to go.
	$context['theme_loaded'] = true;
}

/**
 * Detects url and checks against expected boardurl
 *
 * Attempts to correct improper URL's
 */
function loadThemeUrls()
{
	global $scripturl, $boardurl, $modSettings;

	// Check to see if they're accessing it from the wrong place.
	if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
	{
		$detected_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 'https://' : 'http://';
		$detected_url .= empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
		$temp = preg_replace('~/' . basename($scripturl) . '(/.+)?$~', '', strtr(dirname($_SERVER['PHP_SELF']), '\\', '/'));
		if ($temp != '/')
			$detected_url .= $temp;
	}

	if (isset($detected_url) && $detected_url != $boardurl)
	{
		// Try #1 - check if it's in a list of alias addresses.
		if (!empty($modSettings['forum_alias_urls']))
		{
			$aliases = explode(',', $modSettings['forum_alias_urls']);
			foreach ($aliases as $alias)
			{
				// Rip off all the boring parts, spaces, etc.
				if ($detected_url == trim($alias) || strtr($detected_url, array('http://' => '', 'https://' => '')) == trim($alias))
					$do_fix = true;
			}
		}

		// Hmm... check #2 - is it just different by a www?  Send them to the correct place!!
		if (empty($do_fix) && strtr($detected_url, array('://' => '://www.')) == $boardurl && (empty($_GET) || count($_GET) == 1) && ELK != 'SSI')
		{
			// Okay, this seems weird, but we don't want an endless loop - this will make $_GET not empty ;).
			if (empty($_GET))
				redirectexit('wwwRedirect');
			else
			{
				if (key($_GET) !== 'wwwRedirect')
					redirectexit('wwwRedirect;' . key($_GET) . '=' . current($_GET));
			}
		}

		// #3 is just a check for SSL...
		if (strtr($detected_url, array('https://' => 'http://')) == $boardurl)
			$do_fix = true;

		// Okay, #4 - perhaps it's an IP address?  We're gonna want to use that one, then. (assuming it's the IP or something...)
		if (!empty($do_fix) || preg_match('~^http[s]?://(?:[\d\.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) == 1)
		{
			fixThemeUrls($detected_url);
		}
	}
}

/**
 * Loads various theme related settings into context and sets system wide theme defaults
 */
function loadThemeContext()
{
	global $context, $settings, $modSettings, $txt;

	// Some basic information...
	$init = array(
		'html_headers' => '',
		'links' => array(),
		'css_files' => array(),
		'javascript_files' => array(),
		'css_rules' => array(),
		'javascript_inline' => array('standard' => array(), 'defer' => array()),
		'javascript_vars' => array(),
	);
	foreach ($init as $area => $value)
	{
		$context[$area] = isset($context[$area]) ? $context[$area] : $value;
	}

	// Set a couple of bits for the template.
	$context['right_to_left'] = !empty($txt['lang_rtl']);
	$context['tabindex'] = 1;

	$context['theme_variant'] = '';
	$context['theme_variant_url'] = '';

	$context['menu_separator'] = !empty($settings['use_image_buttons']) ? ' ' : ' | ';
	$context['can_register'] = empty($modSettings['registration_method']) || $modSettings['registration_method'] != 3;

	foreach (array('theme_header', 'upper_content') as $call)
	{
		if (!isset($context[$call . '_callbacks']))
		{
			$context[$call . '_callbacks'] = array();
		}
	}

	// This allows sticking some HTML on the page output - useful for controls.
	$context['insert_after_template'] = '';
}

/**
 * Loads basic user information in to $context['user']
 */
function loadUserContext()
{
	global $context, $user_info, $txt, $modSettings;

	// Set up the contextual user array.
	$context['user'] = array(
		'id' => $user_info['id'],
		'is_logged' => !$user_info['is_guest'],
		'is_guest' => &$user_info['is_guest'],
		'is_admin' => &$user_info['is_admin'],
		'is_mod' => &$user_info['is_mod'],
		'is_moderator' => &$user_info['is_moderator'],
		// A user can mod if they have permission to see the mod center, or they are a board/group/approval moderator.
		'can_mod' => allowedTo('access_mod_center') || (!$user_info['is_guest'] && ($user_info['mod_cache']['gq'] != '0=1' || $user_info['mod_cache']['bq'] != '0=1' || ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap'])))),
		'username' => $user_info['username'],
		'language' => $user_info['language'],
		'email' => $user_info['email'],
		'ignoreusers' => $user_info['ignoreusers'],
	);

	// Something for the guests
	if (!$context['user']['is_guest'])
	{
		$context['user']['name'] = $user_info['name'];
	}
	elseif ($context['user']['is_guest'] && !empty($txt['guest_title']))
	{
		$context['user']['name'] = $txt['guest_title'];
	}

	$context['user']['smiley_set'] = determineSmileySet($user_info['smiley_set'], $modSettings['smiley_sets_known']);
	$context['smiley_enabled'] = $user_info['smiley_set'] !== 'none';
	$context['user']['smiley_path'] = $modSettings['smileys_url'] . '/' . $context['user']['smiley_set'] . '/';
}

/**
 * Called if the detected URL is not the same as boardurl but is a common
 * variation in which case it updates key system variables so it works.
 *
 * @param string $detected_url
 */
function fixThemeUrls($detected_url)
{
	global $boardurl, $scripturl, $settings, $modSettings, $context, $board_info;

	// Caching is good ;).
	$oldurl = $boardurl;

	// Fix $boardurl and $scripturl.
	$boardurl = $detected_url;
	$scripturl = strtr($scripturl, array($oldurl => $boardurl));
	$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], array($oldurl => $boardurl));

	// Fix the theme urls...
	$settings['theme_url'] = strtr($settings['theme_url'], array($oldurl => $boardurl));
	$settings['default_theme_url'] = strtr($settings['default_theme_url'], array($oldurl => $boardurl));
	$settings['actual_theme_url'] = strtr($settings['actual_theme_url'], array($oldurl => $boardurl));
	$settings['images_url'] = strtr($settings['images_url'], array($oldurl => $boardurl));
	$settings['default_images_url'] = strtr($settings['default_images_url'], array($oldurl => $boardurl));
	$settings['actual_images_url'] = strtr($settings['actual_images_url'], array($oldurl => $boardurl));

	// And just a few mod settings :).
	$modSettings['smileys_url'] = strtr($modSettings['smileys_url'], array($oldurl => $boardurl));
	$modSettings['avatar_url'] = strtr($modSettings['avatar_url'], array($oldurl => $boardurl));

	// Clean up after loadBoard().
	if (isset($board_info['moderators']))
	{
		foreach ($board_info['moderators'] as $k => $dummy)
		{
			$board_info['moderators'][$k]['href'] = strtr($dummy['href'], array($oldurl => $boardurl));
			$board_info['moderators'][$k]['link'] = strtr($dummy['link'], array('"' . $oldurl => '"' . $boardurl));
		}
	}

	foreach ($context['linktree'] as $k => $dummy)
		$context['linktree'][$k]['url'] = strtr($dummy['url'], array($oldurl => $boardurl));
}

/**
 * Determine the current user's smiley set
 *
 * @param mixed[] $user_smiley_set
 * @param mixed[] $known_smiley_sets
 *
 * @return mixed
 */
function determineSmileySet($user_smiley_set, $known_smiley_sets)
{
	global $modSettings, $settings;

	if ((!in_array($user_smiley_set, explode(',', $known_smiley_sets)) && $user_smiley_set !== 'none') || empty($modSettings['smiley_sets_enable']))
	{
		$set = !empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default'];
	}
	else
	{
		$set = $user_smiley_set;
	}

	return $set;
}

/**
 * This loads the bare minimum data.
 *
 * - Needed by scheduled tasks,
 * - Needed by any other code that needs language files before the forum (the theme) is loaded.
 */
function loadEssentialThemeData()
{
	global $settings, $modSettings, $mbname, $context;

	$db = database();

	// Get all the default theme variables.
	$db->fetchQueryCallback('
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND id_theme IN (1, {int:theme_guests})',
		array(
			'no_member' => 0,
			'theme_guests' => $modSettings['theme_guests'],
		),
		function ($row)
		{
			global $settings;

			$settings[$row['variable']] = $row['value'];

			// Is this the default theme?
			if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1')
				$settings['default_' . $row['variable']] = $row['value'];
		}
	);

	// Check we have some directories setup.
	if (!Templates::instance()->hasDirectories())
	{
		Templates::instance()->reloadDirectories($settings);
	}

	// Assume we want this.
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = $context['forum_name'];

	loadLanguage('index+Addons');
}

/**
 * Load a template - if the theme doesn't include it, use the default.
 *
 * What it does:
 *
 * - loads a template file with the name template_name from the current, default, or base theme.
 * - detects a wrong default theme directory and tries to work around it.
 * - can be used to only load style sheets by using false as the template name
 *   loading of style sheets with this function is deprecated, use loadCSSFile instead
 * - if $settings['template_dirs'] is empty, it delays the loading of the template
 *
 * @uses the requireTemplate() function to actually load the file.
 * @param string|false $template_name
 * @param string[]|string $style_sheets any style sheets to load with the template
 * @param bool $fatal = true if fatal is true, dies with an error message if the template cannot be found
 *
 * @return boolean|null
 * @throws Elk_Exception
 */
function loadTemplate($template_name, $style_sheets = array(), $fatal = true)
{
	return Templates::instance()->load($template_name, $style_sheets, $fatal);
}

/**
 * Load a sub-template.
 *
 * What it does:
 *
 * - loads the sub template specified by sub_template_name, which must be in an already-loaded template.
 * - if ?debug is in the query string, shows administrators a marker after every sub template
 * for debugging purposes.
 *
 * @param string $sub_template_name
 * @param bool|string $fatal = false
 * - $fatal = true is for templates that shouldn't get a 'pretty' error screen
 * - $fatal = 'ignore' to skip
 * @throws Elk_Exception
 */
function loadSubTemplate($sub_template_name, $fatal = false)
{
	Templates::instance()->loadSubTemplate($sub_template_name, $fatal);

	return true;
}

/**
 * Add a CSS file for output later
 *
 * @param string[]|string $filenames string or array of filenames to work on
 * @param mixed[] $params = array()
 * Keys are the following:
 * - ['local'] (true/false): define if the file is local
 * - ['fallback'] (true/false): if false  will attempt to load the file
 *   from the default theme if not found in the current theme
 * - ['stale'] (true/false/string): if true or null, use cache stale,
 *   false do not, or used a supplied string
 * @param string $id optional id to use in html id=""
 */
function loadCSSFile($filenames, $params = array(), $id = '')
{
	global $context;

	if (empty($filenames))
		return;

	if (!is_array($filenames))
		$filenames = array($filenames);

	if (in_array('admin.css', $filenames))
		$filenames[] = $context['theme_variant'] . '/admin' . $context['theme_variant'] . '.css';

	$params['subdir'] = 'css';
	$params['extension'] = 'css';
	$params['index_name'] = 'css_files';
	$params['debug_index'] = 'sheets';

	loadAssetFile($filenames, $params, $id);
}

/**
 * Add a Javascript file for output later
 *
 * What it does:
 *
 * - Can be passed an array of filenames, all which will have the same
 *   parameters applied,
 * - if you need specific parameters on a per file basis, call it multiple times
 *
 * @param string[]|string $filenames string or array of filenames to work on
 * @param mixed[] $params = array()
 * Keys are the following:
 * - ['local'] (true/false): define if the file is local, if file does not
 *     start with http its assumed local
 * - ['defer'] (true/false): define if the file should load in <head> or before
 *     the closing <html> tag
 * - ['fallback'] (true/false): if true will attempt to load the file from the
 *     default theme if not found in the current this is the default behavior
 *     if this is not supplied
 * - ['async'] (true/false): if the script should be loaded asynchronously (HTML5)
 * - ['stale'] (true/false/string): if true or null, use cache stale, false do
 *     not, or used a supplied string
 * @param string $id = '' optional id to use in html id=""
 */
function loadJavascriptFile($filenames, $params = array(), $id = '')
{
	if (empty($filenames))
		return;

	$params['subdir'] = 'scripts';
	$params['extension'] = 'js';
	$params['index_name'] = 'js_files';
	$params['debug_index'] = 'javascript';

	loadAssetFile($filenames, $params, $id);
}

/**
 * Add an asset (css, js or other) file for output later
 *
 * What it does:
 *
 * - Can be passed an array of filenames, all which will have the same
 *   parameters applied,
 * - If you need specific parameters on a per file basis, call it multiple times
 *
 * @param string[]|string $filenames string or array of filenames to work on
 * @param mixed[] $params = array()
 * Keys are the following:
 * - ['subdir'] (string): the subdirectory of the theme dir the file is in
 * - ['extension'] (string): the extension of the file (e.g. css)
 * - ['index_name'] (string): the $context index that holds the array of loaded
 *     files
 * - ['debug_index'] (string): the index that holds the array of loaded
 *     files for debugging debug
 * - ['local'] (true/false): define if the file is local, if file does not
 *     start with http or // (schema-less URLs) its assumed local.
 *     The parameter is in fact useful only for files whose name starts with
 *     http, in any other case (e.g. passing a local URL) the parameter would
 *     fail in properly adding the file to the list.
 * - ['defer'] (true/false): define if the file should load in <head> or before
 *     the closing <html> tag
 * - ['fallback'] (true/false): if true will attempt to load the file from the
 *     default theme if not found in the current this is the default behavior
 *     if this is not supplied
 * - ['async'] (true/false): if the script should be loaded asynchronously (HTML5)
 * - ['stale'] (true/false/string): if true or null, use cache stale, false do
 *     not, or used a supplied string
 * @param string $id = '' optional id to use in html id=""
 */
function loadAssetFile($filenames, $params = array(), $id = '')
{
	global $settings, $context, $db_show_debug;

	if (empty($filenames))
		return;

	$cache = Cache::instance();

	if (!is_array($filenames))
		$filenames = array($filenames);

	// Static values for all these settings
	if (!isset($params['stale']) || $params['stale'] === true)
		$staler_string = CACHE_STALE;
	elseif (is_string($params['stale']))
		$staler_string = ($params['stale'][0] === '?' ? $params['stale'] : '?' . $params['stale']);
	else
		$staler_string = '';

	$fallback = (!empty($params['fallback']) && ($params['fallback'] === false)) ? false : true;
	$dir = '/' . $params['subdir'] . '/';

	// Whoa ... we've done this before yes?
	$cache_name = 'load_' . $params['extension'] . '_' . hash('md5', $settings['theme_dir'] . implode('_', $filenames));
	$temp = array();
	if ($cache->getVar($temp, $cache_name, 600))
	{
		if (empty($context[$params['index_name']]))
			$context[$params['index_name']] = array();

		$context[$params['index_name']] += $temp;

		if ($db_show_debug === true)
		{
			foreach ($temp as $temp_params)
			{
				Debug::instance()->add($params['debug_index'], $temp_params['options']['basename'] . '(' . (!empty($temp_params['options']['local']) ? (!empty($temp_params['options']['url']) ? basename($temp_params['options']['url']) : basename($temp_params['options']['dir'])) : '') . ')');
			}
		}
	}
	else
	{
		$this_build = array();

		// All the files in this group use the above parameters
		foreach ($filenames as $filename)
		{
			// Account for shorthand like admin.ext?xyz11 filenames
			$has_cache_staler = strpos($filename, '.' . $params['extension'] . '?');
			if ($has_cache_staler)
			{
				$cache_staler = $staler_string;
				$params['basename'] = substr($filename, 0, $has_cache_staler + strlen($params['extension']) + 1);
			}
			else
			{
				$cache_staler = '';
				$params['basename'] = $filename;
			}
			$this_id = empty($id) ? strtr(basename($filename), '?', '_') : $id;

			// Is this a local file?
			if (!empty($params['local']) || (substr($filename, 0, 4) !== 'http' && substr($filename, 0, 2) !== '//'))
			{
				$params['local'] = true;
				$params['dir'] = $settings['theme_dir'] . $dir;
				$params['url'] = $settings['theme_url'];

				// Fallback if we are not already in the default theme
				if ($fallback && ($settings['theme_dir'] !== $settings['default_theme_dir']) && !file_exists($settings['theme_dir'] . $dir . $params['basename']))
				{
					// Can't find it in this theme, how about the default?
					if (file_exists($settings['default_theme_dir'] . $dir . $params['basename']))
					{
						$filename = $settings['default_theme_url'] . $dir . $params['basename'] . $cache_staler;
						$params['dir'] = $settings['default_theme_dir'] . $dir;
						$params['url'] = $settings['default_theme_url'];
					}
					else
						$filename = false;
				}
				else
					$filename = $settings['theme_url'] . $dir . $params['basename'] . $cache_staler;
			}

			// Add it to the array for use in the template
			if (!empty($filename))
			{
				$this_build[$this_id] = $context[$params['index_name']][$this_id] = array('filename' => $filename, 'options' => $params);

				if ($db_show_debug === true)
				{
					Debug::instance()->add($params['debug_index'], $params['basename'] . '(' . (!empty($params['local']) ? (!empty($params['url']) ? basename($params['url']) : basename($params['dir'])) : '') . ')');
				}
			}

			// Save it so we don't have to build this so often
			$cache->put($cache_name, $this_build, 600);
		}
	}
}

/**
 * Add a Javascript variable for output later (for feeding text strings and similar to JS)
 *
 * @param mixed[] $vars array of vars to include in the output done as 'varname' => 'var value'
 * @param bool $escape = false, whether or not to escape the value
 */
function addJavascriptVar($vars, $escape = false)
{
	theme()->addJavascriptVar($vars, $escape);
}

/**
 * Add a block of inline Javascript code to be executed later
 *
 * What it does:
 *
 * - only use this if you have to, generally external JS files are better, but for very small scripts
 *   or for scripts that require help from PHP/whatever, this can be useful.
 * - all code added with this function is added to the same <script> tag so do make sure your JS is clean!
 *
 * @param string $javascript
 * @param bool $defer = false, define if the script should load in <head> or before the closing <html> tag
 */
function addInlineJavascript($javascript, $defer = false)
{
	theme()->addInlineJavascript($javascript, $defer);
}

/**
 * Load a language file.
 *
 * - Tries the current and default themes as well as the user and global languages.
 *
 * @param string $template_name
 * @param string $lang = ''
 * @param bool $fatal = true
 * @param bool $force_reload = false
 * @return string The language actually loaded.
 */
function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false)
{
	global $user_info, $language, $settings, $modSettings;
	global $db_show_debug, $txt;
	static $already_loaded = array();

	// Default to the user's language.
	if ($lang == '')
		$lang = isset($user_info['language']) ? $user_info['language'] : $language;

	if (!$force_reload && isset($already_loaded[$template_name]) && $already_loaded[$template_name] == $lang)
		return $lang;

	// Do we want the English version of language file as fallback?
	if (empty($modSettings['disable_language_fallback']) && $lang != 'english')
		loadLanguage($template_name, 'english', false);

	// Make sure we have $settings - if not we're in trouble and need to find it!
	if (empty($settings['default_theme_dir']))
		loadEssentialThemeData();

	// What theme are we in?
	$theme_name = basename($settings['theme_url']);
	if (empty($theme_name))
		$theme_name = 'unknown';

	$fix_arrays = false;
	// For each file open it up and write it out!
	foreach (explode('+', $template_name) as $template)
	{
		if ($template === 'index')
			$fix_arrays = true;

		// Obviously, the current theme is most important to check.
		$attempts = array(
			array($settings['theme_dir'], $template, $lang, $settings['theme_url']),
			array($settings['theme_dir'], $template, $language, $settings['theme_url']),
		);

		// Do we have a base theme to worry about?
		if (isset($settings['base_theme_dir']))
		{
			$attempts[] = array($settings['base_theme_dir'], $template, $lang, $settings['base_theme_url']);
			$attempts[] = array($settings['base_theme_dir'], $template, $language, $settings['base_theme_url']);
		}

		// Fall back on the default theme if necessary.
		$attempts[] = array($settings['default_theme_dir'], $template, $lang, $settings['default_theme_url']);
		$attempts[] = array($settings['default_theme_dir'], $template, $language, $settings['default_theme_url']);

		// Fall back on the English language if none of the preferred languages can be found.
		if (!in_array('english', array($lang, $language)))
		{
			$attempts[] = array($settings['theme_dir'], $template, 'english', $settings['theme_url']);
			$attempts[] = array($settings['default_theme_dir'], $template, 'english', $settings['default_theme_url']);
		}

		$templates = Templates::instance();

		// Try to find the language file.
		$found = false;
		foreach ($attempts as $k => $file)
		{
			if (file_exists($file[0] . '/languages/' . $file[2] . '/' . $file[1] . '.' . $file[2] . '.php'))
			{
				// Include it!
				$templates->templateInclude($file[0] . '/languages/' . $file[2] . '/' . $file[1] . '.' . $file[2] . '.php');

				// Note that we found it.
				$found = true;

				break;
			}
			// @deprecated since 1.0 - old way of archiving language files, all in one directory
			elseif (file_exists($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php'))
			{
				// Include it!
				$templates->templateInclude($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php');

				// Note that we found it.
				$found = true;

				break;
			}
		}

		// That couldn't be found!  Log the error, but *try* to continue normally.
		if (!$found && $fatal)
		{
			Errors::instance()->log_error(sprintf($txt['theme_language_error'], $template_name . '.' . $lang, 'template'));
			break;
		}
	}

	if ($fix_arrays)
		fix_calendar_text();

	// Keep track of what we're up to soldier.
	if ($db_show_debug === true)
	{
		Debug::instance()->add('language_files', $template_name . '.' . $lang . ' (' . $theme_name . ')');
	}

	// Remember what we have loaded, and in which language.
	$already_loaded[$template_name] = $lang;

	// Return the language actually loaded.
	return $lang;
}

/**
 * Loads / Sets arrays for use in date display
 */
function fix_calendar_text()
{
	global $txt;

	$txt['days'] = array(
		$txt['sunday'],
		$txt['monday'],
		$txt['tuesday'],
		$txt['wednesday'],
		$txt['thursday'],
		$txt['friday'],
		$txt['saturday'],
	);
	$txt['days_short'] = array(
		$txt['sunday_short'],
		$txt['monday_short'],
		$txt['tuesday_short'],
		$txt['wednesday_short'],
		$txt['thursday_short'],
		$txt['friday_short'],
		$txt['saturday_short'],
	);
	$txt['months'] = array(
		1 => $txt['january'],
		$txt['february'],
		$txt['march'],
		$txt['april'],
		$txt['may'],
		$txt['june'],
		$txt['july'],
		$txt['august'],
		$txt['september'],
		$txt['october'],
		$txt['november'],
		$txt['december'],
	);
	$txt['months_titles'] = array(
		1 => $txt['january_titles'],
		$txt['february_titles'],
		$txt['march_titles'],
		$txt['april_titles'],
		$txt['may_titles'],
		$txt['june_titles'],
		$txt['july_titles'],
		$txt['august_titles'],
		$txt['september_titles'],
		$txt['october_titles'],
		$txt['november_titles'],
		$txt['december_titles'],
	);
	$txt['months_short'] = array(
		1 => $txt['january_short'],
		$txt['february_short'],
		$txt['march_short'],
		$txt['april_short'],
		$txt['may_short'],
		$txt['june_short'],
		$txt['july_short'],
		$txt['august_short'],
		$txt['september_short'],
		$txt['october_short'],
		$txt['november_short'],
		$txt['december_short'],
	);
}

/**
 * Get all parent boards (requires first parent as parameter)
 *
 * What it does:
 *
 * - It finds all the parents of id_parent, and that board itself.
 * - Additionally, it detects the moderators of said boards.
 * - Returns an array of information about the boards found.
 *
 * @param int $id_parent
 *
 * @return array
 * @throws Elk_Exception parent_not_found
 */
function getBoardParents($id_parent)
{
	global $scripturl;

	$db = database();
	$cache = Cache::instance();
	$boards = array();

	// First check if we have this cached already.
	if (!$cache->getVar($boards, 'board_parents-' . $id_parent, 480))
	{
		$boards = array();
		$original_parent = $id_parent;

		// Loop while the parent is non-zero.
		while ($id_parent != 0)
		{
			$result = $db->query('', '
				SELECT
					b.id_parent, b.name, {int:board_parent} AS id_board, COALESCE(mem.id_member, 0) AS id_moderator,
					mem.real_name, b.child_level
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
				WHERE b.id_board = {int:board_parent}',
				array(
					'board_parent' => $id_parent,
				)
			);
			// In the EXTREMELY unlikely event this happens, give an error message.
			if ($db->num_rows($result) == 0)
			{
				throw new Elk_Exception('parent_not_found', 'critical');
			}
			while ($row = $db->fetch_assoc($result))
			{
				if (!isset($boards[$row['id_board']]))
				{
					$id_parent = $row['id_parent'];
					$boards[$row['id_board']] = array(
						'url' => $scripturl . '?board=' . $row['id_board'] . '.0',
						'name' => $row['name'],
						'level' => $row['child_level'],
						'moderators' => array()
					);
				}

				// If a moderator exists for this board, add that moderator for all children too.
				if (!empty($row['id_moderator']))
					foreach ($boards as $id => $dummy)
					{
						$boards[$id]['moderators'][$row['id_moderator']] = array(
							'id' => $row['id_moderator'],
							'name' => $row['real_name'],
							'href' => $scripturl . '?action=profile;u=' . $row['id_moderator'],
							'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
						);
					}
			}
			$db->free_result($result);
		}

		$cache->put('board_parents-' . $original_parent, $boards, 480);
	}

	return $boards;
}

/**
 * Attempt to reload our known languages.
 *
 * @param bool $use_cache = true
 */
function getLanguages($use_cache = true)
{
	global $settings;

	$cache = Cache::instance();

	// Either we don't use the cache, or its expired.
	$languages = array();

	if (!$use_cache || !$cache->getVar($languages, 'known_languages', $cache->levelLowerThan(2) ? 86400 : 3600))
	{
		// If we don't have our theme information yet, lets get it.
		if (empty($settings['default_theme_dir']))
			loadTheme(0, false);

		// Default language directories to try.
		$language_directories = array(
			$settings['default_theme_dir'] . '/languages',
			$settings['actual_theme_dir'] . '/languages',
		);

		// We possibly have a base theme directory.
		if (!empty($settings['base_theme_dir']))
			$language_directories[] = $settings['base_theme_dir'] . '/languages';

		// Remove any duplicates.
		$language_directories = array_unique($language_directories);

		foreach ($language_directories as $language_dir)
		{
			// Can't look in here... doesn't exist!
			if (!file_exists($language_dir))
				continue;

			$dir = dir($language_dir);
			while ($entry = $dir->read())
			{
				// Only directories are interesting
				if ($entry == '..' || !is_dir($dir->path . '/' . $entry))
					continue;

				// @todo at some point we may want to simplify that stuff (I mean scanning all the files just for index)
				$file_dir = dir($dir->path . '/' . $entry);
				while ($file_entry = $file_dir->read())
				{
					// Look for the index language file....
					if (!preg_match('~^index\.(.+)\.php$~', $file_entry, $matches))
						continue;

					$languages[$matches[1]] = array(
						'name' => Util::ucwords(strtr($matches[1], array('_' => ' '))),
						'selected' => false,
						'filename' => $matches[1],
						'location' => $language_dir . '/' . $entry . '/index.' . $matches[1] . '.php',
					);
				}
				$file_dir->close();
			}
			$dir->close();
		}

		// Lets cash in on this deal.
		$cache->put('known_languages', $languages, $cache->isEnabled() && $cache->levelLowerThan(1) ? 86400 : 3600);
	}

	return $languages;
}

/**
 * Initialize a database connection.
 */
function loadDatabase()
{
	global $db_persist, $db_server, $db_user, $db_passwd, $db_port;
	global $db_type, $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix;

	// Database stuffs
	require_once(SOURCEDIR . '/database/Database.subs.php');

	// Figure out what type of database we are using.
	if (empty($db_type) || !file_exists(SOURCEDIR . '/database/Db-' . $db_type . '.class.php'))
		$db_type = 'mysql';

	// If we are in SSI try them first, but don't worry if it doesn't work, we have the normal username and password we can use.
	if (ELK === 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
		$connection = elk_db_initiate($db_server, $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix, array('persist' => $db_persist, 'non_fatal' => true, 'dont_select_db' => true, 'port' => $db_port), $db_type);

	// Either we aren't in SSI mode, or it failed.
	if (empty($connection))
		$connection = elk_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist, 'dont_select_db' => ELK === 'SSI', 'port' => $db_port), $db_type);

	// Safe guard here, if there isn't a valid connection lets put a stop to it.
	if (!$connection)
		Errors::instance()->display_db_error();

	// If in SSI mode fix up the prefix.
	$db = database();
	if (ELK === 'SSI')
		$db_prefix = $db->fix_prefix($db_prefix, $db_name);

	// Case sensitive database? Let's define a constant.
	if ($db->db_case_sensitive() && !defined('DB_CASE_SENSITIVE'))
		DEFINE('DB_CASE_SENSITIVE', '1');
}

/**
 * Determine the user's avatar type and return the information as an array
 *
 * @todo this function seems more useful than expected, it should be improved. :P
 *
 * @event integrate_avatar allows access to $avatar array before it is returned
 * @param mixed[] $profile array containing the users profile data
 *
 * @return mixed[] $avatar
 */
function determineAvatar($profile)
{
	global $modSettings, $scripturl, $settings;

	if (empty($profile))
		return array();

	$avatar_protocol = substr(strtolower($profile['avatar']), 0, 7);

	// uploaded avatar?
	if ($profile['id_attach'] > 0 && empty($profile['avatar']))
	{
		// where are those pesky avatars?
		$avatar_url = empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $profile['filename'];

		$avatar = array(
			'name' => $profile['avatar'],
			'image' => '<img class="avatar avatarresize" src="' . $avatar_url . '" alt="" />',
			'href' => $avatar_url,
			'url' => '',
		);
	}
	// remote avatar?
	elseif ($avatar_protocol === 'http://' || $avatar_protocol === 'https:/')
	{
		$avatar = array(
			'name' => $profile['avatar'],
			'image' => '<img class="avatar avatarresize" src="' . $profile['avatar'] . '" alt="" />',
			'href' => $profile['avatar'],
			'url' => $profile['avatar'],
		);
	}
	// Gravatar instead?
	elseif (!empty($profile['avatar']) && $profile['avatar'] === 'gravatar')
	{
		// Gravatars URL.
		$gravatar_url = '//www.gravatar.com/avatar/' . hash('md5', strtolower($profile['email_address'])) . '?s=' . $modSettings['avatar_max_height'] . (!empty($modSettings['gravatar_rating']) ? ('&amp;r=' . $modSettings['gravatar_rating']) : '');

		$avatar = array(
			'name' => $profile['avatar'],
			'image' => '<img class="avatar avatarresize" src="' . $gravatar_url . '" alt="" />',
			'href' => $gravatar_url,
			'url' => $gravatar_url,
		);
	}
	// an avatar from the gallery?
	elseif (!empty($profile['avatar']) && !($avatar_protocol === 'http://' || $avatar_protocol === 'https:/'))
	{
		$avatar = array(
			'name' => $profile['avatar'],
			'image' => '<img class="avatar avatarresize" src="' . $modSettings['avatar_url'] . '/' . $profile['avatar'] . '" alt="" />',
			'href' => $modSettings['avatar_url'] . '/' . $profile['avatar'],
			'url' => $modSettings['avatar_url'] . '/' . $profile['avatar'],
		);
	}
	// no custom avatar found yet, maybe a default avatar?
	elseif (!empty($modSettings['avatar_default']) && empty($profile['avatar']) && empty($profile['filename']))
	{
		// $settings not initialized? We can't do anything further..
		if (!empty($settings))
		{
			// Let's proceed with the default avatar.
			// TODO: This should be incorporated into the theme.
			$avatar = array(
				'name' => '',
				'image' => '<img class="avatar avatarresize" src="' . $settings['images_url'] . '/default_avatar.png" alt="" />',
				'href' => $settings['images_url'] . '/default_avatar.png',
				'url' => 'http://',
			);
		}
		else
		{
			$avatar = array();
		}
	}
	// finally ...
	else
		$avatar = array(
			'name' => '',
			'image' => '',
			'href' => '',
			'url' => ''
		);

	// Make sure there's a preview for gravatars available.
	$avatar['gravatar_preview'] = '//www.gravatar.com/avatar/' . hash('md5', strtolower($profile['email_address'])) . '?s=' . $modSettings['avatar_max_height'] . (!empty($modSettings['gravatar_rating']) ? ('&amp;r=' . $modSettings['gravatar_rating']) : '');

	call_integration_hook('integrate_avatar', array(&$avatar, $profile));

	return $avatar;
}

/**
 * Get information about the server
 */
function detectServer()
{
	global $context;
	static $server = null;

	if ($server === null)
	{
		$server = new Server($_SERVER);
		$servers = array('iis', 'apache', 'litespeed', 'lighttpd', 'nginx', 'cgi', 'windows');
		$context['server'] = array();
		foreach ($servers as $name)
		{
			$context['server']['is_' . $name] = $server->is($name);
		}

		$context['server']['iso_case_folding'] = $server->is('iso_case_folding');
		// A bug in some versions of IIS under CGI (older ones) makes cookie setting not work with Location: headers.
		$context['server']['needs_login_fix'] = $server->is('needs_login_fix');
	}

	return $server;
}

/**
 * Returns if a webserver is of type server (apache, nginx, etc)
 *
 * @param $server
 *
 * @return bool
 */
function serverIs($server)
{
	return detectServer()->is($server);
}

/**
 * Do some important security checks:
 *
 * What it does:
 *
 * - Checks the existence of critical files e.g. install.php
 * - Checks for an active admin session.
 * - Checks cache directory is writable.
 * - Calls secureDirectory to protect attachments & cache.
 * - Checks if the forum is in maintenance mode.
 */
function doSecurityChecks()
{
	global $modSettings, $context, $maintenance, $user_info, $txt, $scripturl, $user_settings, $options;

	$show_warnings = false;

	$cache = Cache::instance();

	if (allowedTo('admin_forum') && !$user_info['is_guest'])
	{
		// If agreement is enabled, at least the english version shall exists
		if ($modSettings['requireAgreement'] && !file_exists(BOARDDIR . '/agreement.txt'))
		{
			$context['security_controls_files']['title'] = $txt['generic_warning'];
			$context['security_controls_files']['errors']['agreement'] = $txt['agreement_missing'];
			$show_warnings = true;
		}

		// Cache directory writable?
		if ($cache->isEnabled() && !is_writable(CACHEDIR))
		{
			$context['security_controls_files']['title'] = $txt['generic_warning'];
			$context['security_controls_files']['errors']['cache'] = $txt['cache_writable'];
			$show_warnings = true;
		}

		if (checkSecurityFiles())
			$show_warnings = true;

		// We are already checking so many files...just few more doesn't make any difference! :P
		require_once(SUBSDIR . '/Attachments.subs.php');
		$path = getAttachmentPath();
		secureDirectory($path, true);
		secureDirectory(CACHEDIR, false, '"\.(js|css)$"');

		// Active admin session?
		if (isAdminSessionActive())
			$context['warning_controls']['admin_session'] = sprintf($txt['admin_session_active'], ($scripturl . '?action=admin;area=adminlogoff;redir;' . $context['session_var'] . '=' . $context['session_id']));

		// Maintenance mode enabled?
		if (!empty($maintenance))
			$context['warning_controls']['maintenance'] = sprintf($txt['admin_maintenance_active'], ($scripturl . '?action=admin;area=serversettings;' . $context['session_var'] . '=' . $context['session_id']));

		// New updates
		if (defined('FORUM_VERSION'))
		{
			$index = 'new_in_' . str_replace(array('ElkArte ', '.'), array('', '_'), FORUM_VERSION);
			if (!empty($modSettings[$index]) && empty($options['dismissed_' . $index]))
			{
				$show_warnings = true;
				$context['new_version_updates'] = array(
					'title' => $txt['new_version_updates'],
					'errors' => array(replaceBasicActionUrl($txt['new_version_updates_text'])),
				);
			}
		}
	}

	// Check for database errors.
	if (!empty($_SESSION['query_command_denied']))
	{
		if ($user_info['is_admin'])
		{
			$context['security_controls_query']['title'] = $txt['query_command_denied'];
			$show_warnings = true;
			foreach ($_SESSION['query_command_denied'] as $command => $error)
				$context['security_controls_query']['errors'][$command] = '<pre>' . Util::htmlspecialchars($error) . '</pre>';
		}
		else
		{
			$context['security_controls_query']['title'] = $txt['query_command_denied_guests'];
			foreach ($_SESSION['query_command_denied'] as $command => $error)
				$context['security_controls_query']['errors'][$command] = '<pre>' . sprintf($txt['query_command_denied_guests_msg'], Util::htmlspecialchars($command)) . '</pre>';
		}
	}

	// Are there any members waiting for approval?
	if (allowedTo('moderate_forum') && ((!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 2) || !empty($modSettings['approveAccountDeletion'])) && !empty($modSettings['unapprovedMembers']))
		$context['warning_controls']['unapproved_members'] = sprintf($txt[$modSettings['unapprovedMembers'] == 1 ? 'approve_one_member_waiting' : 'approve_many_members_waiting'], $scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve', $modSettings['unapprovedMembers']);

	if (!empty($context['open_mod_reports']) && (empty($user_settings['mod_prefs']) || $user_settings['mod_prefs'][0] == 1))
	{
		$context['warning_controls']['open_mod_reports'] = '<a href="' . $scripturl . '?action=moderate;area=reports">' . sprintf($txt['mod_reports_waiting'], $context['open_mod_reports']) . '</a>';
	}

	if (!empty($context['open_pm_reports']) && allowedTo('admin_forum'))
	{
		$context['warning_controls']['open_pm_reports'] = '<a href="' . $scripturl . '?action=moderate;area=pm_reports">' . sprintf($txt['pm_reports_waiting'], $context['open_pm_reports']) . '</a>';
	}

	if (isset($_SESSION['ban']['cannot_post']))
	{
		// An admin cannot be banned (technically he could), and if it is better he knows.
		$context['security_controls_ban']['title'] = sprintf($txt['you_are_post_banned'], $user_info['is_guest'] ? $txt['guest_title'] : $user_info['name']);
		$show_warnings = true;

		$context['security_controls_ban']['errors']['reason'] = '';

		if (!empty($_SESSION['ban']['cannot_post']['reason']))
			$context['security_controls_ban']['errors']['reason'] = $_SESSION['ban']['cannot_post']['reason'];

		if (!empty($_SESSION['ban']['expire_time']))
			$context['security_controls_ban']['errors']['reason'] .= '<span class="smalltext">' . sprintf($txt['your_ban_expires'], standardTime($_SESSION['ban']['expire_time'], false)) . '</span>';
		else
			$context['security_controls_ban']['errors']['reason'] .= '<span class="smalltext">' . $txt['your_ban_expires_never'] . '</span>';
	}

	// Finally, let's show the layer.
	if ($show_warnings || !empty($context['warning_controls']))
		\Template_Layers::instance()->addAfter('admin_warning', 'body');
}

/**
 * Load everything necessary for the BBC parsers
 */
function loadBBCParsers()
{
	global $modSettings;

	// Set the default disabled BBC
	if (!empty($modSettings['disabledBBC']))
	{
		if (!is_array($modSettings['disabledBBC']))
			$disabledBBC = explode(',', $modSettings['disabledBBC']);
		else
			$disabledBBC = $modSettings['disabledBBC'];
		\BBC\ParserWrapper::instance()->setDisabled(empty($disabledBBC) ? array() : (array) $disabledBBC);
	}

	return 1;
}

/**
 * This is necessary to support data stored in the pre-1.0.8 way (i.e. serialized)
 *
 * @param string $variable The string to convert
 * @param null|callable $save_callback The function that will save the data to the db
 * @return mixed[] the array
 */
function serializeToJson($variable, $save_callback = null)
{
	$array_form = json_decode($variable, true);

	// decoding failed, let's try with unserialize
	if (!is_array($array_form))
	{
		try
		{
			$array_form = Util::unserialize($variable);
		}
		catch (\Exception $e)
		{
			$array_form = false;
		}

		// If unserialize fails as well, let's just store an empty array
		if ($array_form === false)
		{
			$array_form = array(0, '', 0);
		}

		// Time to update the value if necessary
		if ($save_callback !== null)
		{
			$save_callback($array_form);
		}
	}

	return $array_form;
}
