<?php

/**
 * This file has the hefty job of loading information for the forum.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0.8
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Load the $modSettings array and many necessary forum settings.
 *
 * What it does:
 * - load the settings from cache if available, otherwse from the database.
 * - sets the timezone
 * - checks the load average settings if available.
 * - check whether post moderation is enabled.
 * - calls add_integration_function
 * - calls integrate_pre_include, integrate_pre_load,
 *
 * @global array $modSettings is a giant array of all of the forum-wide settings and statistics.
 */
function reloadSettings()
{
	global $modSettings;

	$db = database();

	// Try to load it from the cache first; it'll never get cached if the setting is off.
	if (($modSettings = cache_get_data('modSettings', 90)) == null)
	{
		$request = $db->query('', '
			SELECT variable, value
			FROM {db_prefix}settings',
			array(
			)
		);
		$modSettings = array();
		if (!$request)
			display_db_error();
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

		if (!empty($modSettings['cache_enable']))
			cache_put_data('modSettings', $modSettings, 90);
	}

	// Setting the timezone is a requirement for some functions in PHP >= 5.1.
	if (isset($modSettings['default_timezone']))
		date_default_timezone_set($modSettings['default_timezone']);

	// Check the load averages?
	if (!empty($modSettings['loadavg_enable']))
	{
		if (($modSettings['load_average'] = cache_get_data('loadavg', 90)) == null)
		{
			$modSettings['load_average'] = detectServerLoad();

			cache_put_data('loadavg', $modSettings['load_average'], 90);
		}

		if ($modSettings['load_average'] !== false)
			call_integration_hook('integrate_load_average', array($modSettings['load_average']));

		// Let's have at least a zero
		if (empty($modSettings['loadavg_forum']) || $modSettings['load_average'] === false)
			$modSettings['current_load'] = 0;
		else
			$modSettings['current_load'] = $modSettings['load_average'];

		if (!empty($modSettings['loadavg_forum']) && $modSettings['current_load'] >= $modSettings['loadavg_forum'])
			display_loadavg_error();
	}
	else
		$modSettings['current_load'] = 0;

	// Is post moderation alive and well?
	$modSettings['postmod_active'] = isset($modSettings['admin_features']) ? in_array('pm', explode(',', $modSettings['admin_features'])) : true;

	// @deprecated since 1.0.6 compatibility setting for migration
	if (!isset($modSettings['avatar_max_height']))
		$modSettings['avatar_max_height'] = $modSettings['avatar_max_height_external'];
	if (!isset($modSettings['avatar_max_width']))
		$modSettings['avatar_max_width'] = $modSettings['avatar_max_width_external'];

	// Here to justify the name of this function. :P
	// It should be added to the install and upgrade scripts.
	// But since the convertors need to be updated also. This is easier.
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
 * - sets up the $user_info array
 * - assigns $user_info['query_wanna_see_board'] for what boards the user can see.
 * - first checks for cookie or integration validation.
 * - uses the current session if no integration function or cookie is found.
 * - checks password length, if member is activated and the login span isn't over.
 * - if validation fails for the user, $id_member is set to 0.
 * - updates the last visit time when needed.
 */
function loadUserSettings()
{
	global $context, $modSettings, $user_settings, $cookiename, $user_info, $language;

	$db = database();

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
	elseif (empty($id_member) && isset($_SESSION['login_' . $cookiename]) && ($_SESSION['USER_AGENT'] == $req->user_agent() || !empty($modSettings['disableCheckUA'])))
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
		if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < 2 || ($user_settings = cache_get_data('user_settings-' . $id_member, 60)) == null)
		{
			$request = $db->query('', '
				SELECT mem.*, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = {int:id_member})
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $id_member,
				)
			);
			$user_settings = $db->fetch_assoc($request);
			$db->free_result($request);

			// Make the ID specifically an integer
			$user_settings['id_member'] = (int) $user_settings['id_member'];

			if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
				cache_put_data('user_settings-' . $id_member, $user_settings, 60);
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
		if (ELK != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != '.xml') && empty($_SESSION['id_msg_last_visit']) && (empty($modSettings['cache_enable']) || ($_SESSION['id_msg_last_visit'] = cache_get_data('user_last_visit-' . $id_member, 5 * 3600)) === null))
		{
			// @todo can this be cached?
			// Do a quick query to make sure this isn't a mistake.
			require_once(SUBSDIR . '/Messages.subs.php');
			$visitOpt = basicMessageInfo($user_settings['id_msg_last_visit'], true);

			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

			// If it was *at least* five hours ago...
			if ($visitOpt['poster_time'] < time() - 5 * 3600)
			{
				updateMemberData($id_member, array('id_msg_last_visit' => (int) $modSettings['maxMsgID'], 'last_login' => time(), 'member_ip' => $req->client_ip(), 'member_ip2' => $req->ban_ip()));
				$user_settings['last_login'] = time();

				if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
					cache_put_data('user_settings-' . $id_member, $user_settings, 60);

				if (!empty($modSettings['cache_enable']))
					cache_put_data('user_last_visit-' . $id_member, $_SESSION['id_msg_last_visit'], 5 * 3600);
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
 * - sets up the $board_info array for current board information.
 * - if cache is enabled, the $board_info array is stored in cache.
 * - redirects to appropriate post if only message id is requested.
 * - is only used when inside a topic or board.
 * - determines the local moderators for the board.
 * - adds group id 3 if the user is a local moderator for the board they are in.
 * - prevents access if user is not in proper group nor a local moderator of the board.
 */
function loadBoard()
{
	global $txt, $scripturl, $context, $modSettings;
	global $board_info, $board, $topic, $user_info;

	$db = database();

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
		if (($topic = cache_get_data('msg_topic-' . $_REQUEST['msg'], 120)) === null)
		{
			require_once(SUBSDIR . '/Messages.subs.php');
			$topic = associatedTopic($_REQUEST['msg']);

			// So did it find anything?
			if ($topic !== false)
			{
				// Save save save.
				cache_put_data('msg_topic-' . $_REQUEST['msg'], $topic, 120);
			}
		}

		// Remember redirection is the key to avoiding fallout from your bosses.
		if (!empty($topic))
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
		else
		{
			loadPermissions();
			loadTheme();
			fatal_lang_error('topic_gone', false);
		}
	}

	// Load this board only if it is specified.
	if (empty($board) && empty($topic))
	{
		$board_info = array('moderators' => array());
		return;
	}

	if (!empty($modSettings['cache_enable']) && (empty($topic) || $modSettings['cache_enable'] >= 3))
	{
		// @todo SLOW?
		if (!empty($topic))
			$temp = cache_get_data('topic_board-' . $topic, 120);
		else
			$temp = cache_get_data('board-' . $board, 120);

		if (!empty($temp))
		{
			$board_info = $temp;
			$board = $board_info['id'];
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
				b.id_parent, c.name AS cname, IFNULL(mem.id_member, 0) AS id_moderator,
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
				$board = $row['id_board'];

			// Basic operating information. (globals... :/)
			$board_info = array(
				'id' => $board,
				'moderators' => array(),
				'cat' => array(
					'id' => $row['id_cat'],
					'name' => $row['cname']
				),
				'name' => $row['bname'],
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

			// If the board only contains unapproved posts and the user isn't an approver then they can't see any topics.
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

			call_integration_hook('integrate_loaded_board', array(&$board_info, &$row));

			if (!empty($modSettings['cache_enable']) && (empty($topic) || $modSettings['cache_enable'] >= 3))
			{
				// @todo SLOW?
				if (!empty($topic))
					cache_put_data('topic_board-' . $topic, $board_info, 120);
				cache_put_data('board-' . $board, $board_info, 120);
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
				'url' => $scripturl . '#c' . $board_info['cat']['id'],
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

		// If it's a prefetching agent or we're requesting an attachment.
		if ((isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') || (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'dlattach'))
		{
			@ob_end_clean();
			header('HTTP/1.1 403 Forbidden');
			die;
		}
		elseif ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			is_not_guest($txt['topic_gone']);
		}
		else
			fatal_lang_error('topic_gone', false);
	}

	if ($user_info['is_mod'])
		$user_info['groups'][] = 3;
}

/**
 * Load this user's permissions.
 *
 * What it does:
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

	if (!empty($modSettings['cache_enable']))
	{
		$cache_groups = $user_info['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);

		// If it's a spider then cache it different.
		if ($user_info['possibly_robot'])
			$cache_groups .= '-spider';

		if ($modSettings['cache_enable'] >= 2 && !empty($board) && ($temp = cache_get_data('permissions:' . $cache_groups . ':' . $board, 240)) != null && time() - 240 > $modSettings['settings_updated'])
		{
			list ($user_info['permissions']) = $temp;
			banPermissions();

			return;
		}
		elseif (($temp = cache_get_data('permissions:' . $cache_groups, 240)) != null && time() - 240 > $modSettings['settings_updated'])
			list ($user_info['permissions'], $removals) = $temp;
	}

	// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
	$spider_restrict = $user_info['possibly_robot'] && !empty($modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

	if (empty($user_info['permissions']))
	{
		// Get the general permissions.
		$request = $db->query('', '
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:member_groups})
				' . $spider_restrict,
			array(
				'member_groups' => $user_info['groups'],
				'spider_group' => !empty($modSettings['spider_group']) && $modSettings['spider_group'] != 1 ? $modSettings['spider_group'] : 0,
			)
		);
		$removals = array();
		while ($row = $db->fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		$db->free_result($request);

		if (isset($cache_groups))
			cache_put_data('permissions:' . $cache_groups, array($user_info['permissions'], $removals), 240);
	}

	// Get the board permissions.
	if (!empty($board))
	{
		// Make sure the board (if any) has been loaded by loadBoard().
		if (!isset($board_info['profile']))
			fatal_lang_error('no_board');

		$request = $db->query('', '
			SELECT permission, add_deny
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

	if (isset($cache_groups) && !empty($board) && $modSettings['cache_enable'] >= 2)
		cache_put_data('permissions:' . $cache_groups . ':' . $board, array($user_info['permissions'], null), 240);

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
 * @param int[]|int|string[]|string $users An array of users by id or name
 * @param bool $is_name = false $users is by name or by id
 * @param string $set = 'normal' What kind of data to load (normal, profile, minimal)
 * @return array|bool The ids of the members loaded or false
 */
function loadMemberData($users, $is_name = false, $set = 'normal')
{
	global $user_profile, $modSettings, $board_info, $context;

	$db = database();

	// Can't just look for no users :P.
	if (empty($users))
		return false;

	// Pass the set value
	$context['loadMemberContext_set'] = $set;

	// Make sure it's an array.
	$users = !is_array($users) ? array($users) : array_unique($users);
	$loaded_ids = array();

	if (!$is_name && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		$users = array_values($users);
		for ($i = 0, $n = count($users); $i < $n; $i++)
		{
			$data = cache_get_data('member_data-' . $set . '-' . $users[$i], 240);
			if ($data == null)
				continue;

			$loaded_ids[] = $data['id_member'];
			$user_profile[$data['id_member']] = $data;
			unset($users[$i]);
		}
	}

	// Used by default
	$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.signature, mem.personal_text, mem.location, mem.gender, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.member_ip, mem.member_ip2, mem.posts, mem.last_login, mem.likes_given, mem.likes_received,
			mem.karma_good, mem.id_post_group, mem.karma_bad, mem.lngfile, mem.id_group, mem.time_offset, mem.show_online,
			mg.online_color AS member_group_color, IFNULL(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, IFNULL(pg.group_name, {string:blank_string}) AS post_group,
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
			mem.notify_types, lo.url, mem.ignore_boards, mem.password_salt, mem.pm_prefs, mem.buddy_list';
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
	if (!empty($new_loaded_ids) && $set !== 'minimal' && (in_array('cp', $context['admin_features'])))
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

	// Anthing else integration may want to add to the user_profile array
	if (!empty($new_loaded_ids))
		call_integration_hook('integrate_add_member_data', array($new_loaded_ids, $set));

	if (!empty($new_loaded_ids) && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		for ($i = 0, $n = count($new_loaded_ids); $i < $n; $i++)
			cache_put_data('member_data-' . $set . '-' . $new_loaded_ids[$i], $user_profile[$new_loaded_ids[$i]], 240);
	}

	// Are we loading any moderators?  If so, fix their group data...
	if (!empty($loaded_ids) && !empty($board_info['moderators']) && $set === 'normal' && count($temp_mods = array_intersect($loaded_ids, array_keys($board_info['moderators']))) !== 0)
	{
		if (($group_info = cache_get_data('moderator_group_info', 480)) == null)
		{
			require_once(SUBSDIR . '/Membergroups.subs.php');
			$group_info = membergroupById(3, true);

			cache_put_data('moderator_group_info', $group_info, 480);
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
 * - Always loads the minimal values of username, name, id, href, link, email, show_email, registered, registered_timestamp
 * - if $context['loadMemberContext_set'] is not minimal it will load in full a full set of user information
 * - prepares signature, personal_text, location fields for display (censoring if enabled)
 * - loads in the members custom fields if any
 * - prepares the users buddy list, including reverse buddy flags
 *
 * @param int $user
 * @param bool $display_custom_fields = false
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

	// Well, it's loaded now anyhow.
	$dataLoaded[$user] = true;
	$profile = $user_profile[$user];

	// Censor everything.
	censorText($profile['signature']);
	censorText($profile['personal_text']);
	censorText($profile['location']);

	// Set things up to be used before hand.
	$gendertxt = $profile['gender'] == 2 ? $txt['female'] : ($profile['gender'] == 1 ? $txt['male'] : '');
	$profile['signature'] = str_replace(array("\n", "\r"), array('<br />', ''), $profile['signature']);
	$profile['signature'] = parse_bbc($profile['signature'], true, 'sig' . $profile['id_member']);
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
		'registered' => empty($profile['date_registered']) ? $txt['not_applicable'] : standardTime($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : forum_time(true, $profile['date_registered']),
	);

	// If the set isn't minimal then load the monstrous array.
	if ($context['loadMemberContext_set'] !== 'minimal')
		$memberContext[$user] += array(
			'username_color' => '<span '. (!empty($profile['member_group_color']) ? 'style="color:'. $profile['member_group_color'] .';"' : '') .'>'. $profile['member_name'] .'</span>',
			'name_color' => '<span '. (!empty($profile['member_group_color']) ? 'style="color:'. $profile['member_group_color'] .';"' : '') .'>'. $profile['real_name'] .'</span>',
			'link_color' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['profile_of'] . ' ' . $profile['real_name'] . '" '. (!empty($profile['member_group_color']) ? 'style="color:'. $profile['member_group_color'] .';"' : '') .'>' . $profile['real_name'] . '</a>',
			'is_buddy' => $profile['buddy'],
			'is_reverse_buddy' => in_array($user_info['id'], $buddy_list),
			'buddies' => $buddy_list,
			'title' => !empty($modSettings['titlesEnable']) ? $profile['usertitle'] : '',
			'blurb' => $profile['personal_text'],
			'gender' => array(
				'name' => $gendertxt,
				'image' => !empty($profile['gender']) ? '<img class="gender" src="' . $settings['images_url'] . '/profile/' . ($profile['gender'] == 1 ? 'Male' : 'Female') . '.png" alt="' . $gendertxt . '" />' : ''
			),
			'website' => array(
				'title' => $profile['website_title'],
				'url' => $profile['website_url'],
			),
			'birth_date' => empty($profile['birthdate']) || $profile['birthdate'] === '0001-01-01' ? '0000-00-00' : (substr($profile['birthdate'], 0, 4) === '0004' ? '0000' . substr($profile['birthdate'], 4) : $profile['birthdate']),
			'signature' => $profile['signature'],
			'location' => $profile['location'],
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
				'image_href' => $settings['images_url'] . '/profile/' . ($profile['buddy'] ? 'buddy_' : '') . ($profile['is_online'] ? 'useron' : 'useroff') . '.png',
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
				$value = parse_bbc($value);
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
 * @uses the class from BrowserDetect.class.php
 */
function detectBrowser()
{
	// Load the current user's browser of choice
	$detector = new Browser_Detector;
	$detector->detectBrowser();
}

/**
 * Are we using this browser?
 *
 * - Wrapper function for detectBrowser
 *
 * @param string $browser  the browser we are checking for.
 */
function isBrowser($browser)
{
	global $context;

	// Don't know any browser!
	if (empty($context['browser']))
		detectBrowser();

	return !empty($context['browser'][$browser]) || !empty($context['browser']['is_' . $browser]) ? true : false;
}

/**
 * Load a theme, by ID.
 *
 * What it does:
 * - identify the theme to be loaded.
 * - validate that the theme is valid and that the user has permission to use it
 * - load the users theme settings and site setttings into $options.
 * - prepares the list of folders to search for template loading.
 * - identify what smiley set to use.
 * - sets up $context['user']
 * - detects the users browser and sets a mobile friendly enviroment if needed
 * - loads default JS variables for use in every theme
 * - loads default JS scripts for use in every theme
 *
 * @param int $id_theme = 0
 * @param bool $initialize = true
 */
function loadTheme($id_theme = 0, $initialize = true)
{
	global $user_info, $user_settings, $board_info;
	global $txt, $boardurl, $scripturl, $mbname, $modSettings;
	global $context, $settings, $options, $ssi_theme;

	$db = database();

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

	$member = empty($user_info['id']) ? -1 : $user_info['id'];

	// Do we already have this members theme data and specific options loaded (for agressive cache settings)
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2 && ($temp = cache_get_data('theme_settings-' . $id_theme . ':' . $member, 60)) != null && time() - 60 > $modSettings['settings_updated'])
	{
		$themeData = $temp;
		$flag = true;
	}
	// Or do we just have the system wide theme settings cached
	elseif (($temp = cache_get_data('theme_settings-' . $id_theme, 90)) != null && time() - 60 > $modSettings['settings_updated'])
		$themeData = $temp + array($member => array());
	// Nothing at all then
	else
		$themeData = array(-1 => array(), 0 => array(), $member => array());

	if (empty($flag))
	{
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
		// Pick between $settings and $options depending on whose data it is.
		while ($row = $db->fetch_assoc($result))
		{
			// There are just things we shouldn't be able to change as members.
			if ($row['id_member'] != 0 && in_array($row['variable'], array('actual_theme_url', 'actual_images_url', 'base_theme_dir', 'base_theme_url', 'default_images_url', 'default_theme_dir', 'default_theme_url', 'default_template', 'images_url', 'number_recent_posts', 'smiley_sets_default', 'theme_dir', 'theme_id', 'theme_layers', 'theme_templates', 'theme_url')))
				continue;

			// If this is the theme_dir of the default theme, store it.
			if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1' && empty($row['id_member']))
				$themeData[0]['default_' . $row['variable']] = $row['value'];

			// If this isn't set yet, is a theme option, or is not the default theme..
			if (!isset($themeData[$row['id_member']][$row['variable']]) || $row['id_theme'] != '1')
				$themeData[$row['id_member']][$row['variable']] = substr($row['variable'], 0, 5) == 'show_' ? $row['value'] == '1' : $row['value'];
		}
		$db->free_result($result);

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
		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('theme_settings-' . $id_theme . ':' . $member, $themeData, 60);
		// Only if we didn't already load that part of the cache...
		elseif (!isset($temp))
			cache_put_data('theme_settings-' . $id_theme, array(-1 => $themeData[-1], 0 => $themeData[0]), 90);
	}

	$settings = $themeData[0];
	$options = $themeData[$member];

	$settings['theme_id'] = $id_theme;
	$settings['actual_theme_url'] = $settings['theme_url'];
	$settings['actual_images_url'] = $settings['images_url'];
	$settings['actual_theme_dir'] = $settings['theme_dir'];
	$settings['template_dirs'] = array();

	// This theme first.
	$settings['template_dirs'][] = $settings['theme_dir'];

	// Based on theme (if there is one).
	if (!empty($settings['base_theme_dir']))
		$settings['template_dirs'][] = $settings['base_theme_dir'];

	// Lastly the default theme.
	if ($settings['theme_dir'] != $settings['default_theme_dir'])
		$settings['template_dirs'][] = $settings['default_theme_dir'];

	if (!$initialize)
		return;

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
				list ($k, $v) = each($_GET);
				if ($k != 'wwwRedirect')
					redirectexit('wwwRedirect;' . $k . '=' . $v);
			}
		}

		// #3 is just a check for SSL...
		if (strtr($detected_url, array('https://' => 'http://')) == $boardurl)
			$do_fix = true;

		// Okay, #4 - perhaps it's an IP address?  We're gonna want to use that one, then. (assuming it's the IP or something...)
		if (!empty($do_fix) || preg_match('~^http[s]?://(?:[\d\.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) == 1)
		{
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
	}

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
		$context['user']['name'] = $user_info['name'];
	elseif ($context['user']['is_guest'] && !empty($txt['guest_title']))
		$context['user']['name'] = $txt['guest_title'];

	// Set up some additional interface preference context
	if (!empty($options['admin_preferences']))
	{
		$context['admin_preferences'] = serializeToJson($options['admin_preferences'], function($array_form) {
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
			$context['minmax_preferences'] = serializeToJson($options['minmax_preferences'], function($array_form) {
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

	// Determine the current smiley set.
	$user_info['smiley_set'] = (!in_array($user_info['smiley_set'], explode(',', $modSettings['smiley_sets_known'])) && $user_info['smiley_set'] != 'none') || empty($modSettings['smiley_sets_enable']) ? (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default']) : $user_info['smiley_set'];
	$context['user']['smiley_set'] = $user_info['smiley_set'];

	// Some basic information...
	if (!isset($context['html_headers']))
		$context['html_headers'] = '';
	if (!isset($context['links']))
		$context['links'] = array();
	if (!isset($context['javascript_files']))
		$context['javascript_files'] = array();
	if (!isset($context['css_files']))
		$context['css_files'] = array();
	if (!isset($context['javascript_inline']))
		$context['javascript_inline'] = array('standard' => array(), 'defer' => array());
	if (!isset($context['javascript_vars']))
		$context['javascript_vars'] = array();

	$context['menu_separator'] = !empty($settings['use_image_buttons']) ? ' ' : ' | ';
	$context['session_var'] = $_SESSION['session_var'];
	$context['session_id'] = $_SESSION['session_value'];
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = $context['forum_name'];
	$context['current_action'] = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
	$context['current_subaction'] = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : null;
	$context['can_register'] = empty($modSettings['registration_method']) || $modSettings['registration_method'] != 3;

	foreach (array('theme_header', 'upper_content') as $call)
	{
		if (!isset($context[$call . '_callbacks']))
			$context[$call . '_callbacks'] = array();
	}

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

	// This allows sticking some HTML on the page output - useful for controls.
	$context['insert_after_template'] = '';

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

	$simpleActions = array(
		'findmember',
		'quickhelp',
		'printpage',
		'quotefast',
		'spellcheck',
	);

	call_integration_hook('integrate_simple_actions', array(&$simpleActions));

	// Output is fully XML, so no need for the index template.
	if (isset($_REQUEST['xml']))
	{
		loadLanguage('index+Addons');

		// @todo added because some $settings in template_init are necessary even in xml mode. Maybe move template_init to a settings file?
		loadTemplate('index');
		loadTemplate('Xml');
		Template_Layers::getInstance()->removeAll();
	}
	// These actions don't require the index template at all.
	elseif (!empty($_REQUEST['action']) && in_array($_REQUEST['action'], $simpleActions))
	{
		loadLanguage('index+Addons');
		Template_Layers::getInstance()->removeAll();
	}
	else
	{
		// Custom templates to load, or just default?
		if (isset($settings['theme_templates']))
			$templates = explode(',', $settings['theme_templates']);
		else
			$templates = array('index');

		// Load each template...
		foreach ($templates as $template)
			loadTemplate($template);

		// ...and attempt to load their associated language files.
		$required_files = implode('+', array_merge($templates, array('Addons')));
		loadLanguage($required_files, '', false);

		// Custom template layers?
		if (isset($settings['theme_layers']))
			$layers = explode(',', $settings['theme_layers']);
		else
			$layers = array('html', 'body');

		$template_layers = Template_Layers::getInstance(true);
		foreach ($layers as $layer)
			$template_layers->addBegin($layer);
	}

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

	// Load font Awesome fonts
	loadCSSFile('font-awesome.min.css');

	// We allow theme variants, because we're cool.
	$context['theme_variant'] = '';
	$context['theme_variant_url'] = '';
	if (!empty($settings['theme_variants']))
	{
		// Overriding - for previews and that ilk.
		if (!empty($_REQUEST['variant']))
			$_SESSION['id_variant'] = $_REQUEST['variant'];

		// User selection?
		if (empty($settings['disable_user_variant']) || allowedTo('admin_forum'))
			$context['theme_variant'] = !empty($_SESSION['id_variant']) ? $_SESSION['id_variant'] : (!empty($options['theme_variant']) ? $options['theme_variant'] : '');

		// If not a user variant, select the default.
		if ($context['theme_variant'] == '' || !in_array($context['theme_variant'], $settings['theme_variants']))
			$context['theme_variant'] = !empty($settings['default_variant']) && in_array($settings['default_variant'], $settings['theme_variants']) ? $settings['default_variant'] : $settings['theme_variants'][0];

		// Do this to keep things easier in the templates.
		$context['theme_variant'] = '_' . $context['theme_variant'];
		$context['theme_variant_url'] = $context['theme_variant'] . '/';

		// The most efficient way of writing multi themes is to use a master index.css plus variant.css files.
		if (!empty($context['theme_variant']))
			loadCSSFile($context['theme_variant'] . '/index' . $context['theme_variant'] . '.css');
	}

	// A bit lonely maybe, though I think it should be set up *after* teh theme variants detection
	$context['header_logo_url_html_safe'] = empty($settings['header_logo_url']) ? $settings['images_url'] . '/' . $context['theme_variant_url'] .  'logo_elk.png' : Util::htmlspecialchars($settings['header_logo_url']);

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

	// Set a couple of bits for the template.
	$context['right_to_left'] = !empty($txt['lang_rtl']);
	$context['tabindex'] = 1;

	// RTL languages require an additional stylesheet.
	if ($context['right_to_left'])
		loadCSSFile('rtl.css');

	if (!empty($context['theme_variant']) && $context['right_to_left'])
		loadCSSFile($context['theme_variant'] . '/rtl' . $context['theme_variant'] . '.css');

	// Compatibility.
	if (!isset($settings['theme_version']))
		$modSettings['memberCount'] = $modSettings['totalMembers'];

	// This allows us to change the way things look for the admin.
	$context['admin_features'] = isset($modSettings['admin_features']) ? explode(',', $modSettings['admin_features']) : array('cd,cp,k,w,rg,ml,pm');

	if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		$context['newsfeed_urls'] = array(
			'rss' => $scripturl . '?action=.xml;type=rss2;limit=' . (!empty($modSettings['xmlnews_limit']) ? $modSettings['xmlnews_limit'] : 5),
			'atom' => $scripturl . '?action=.xml;type=atom;limit=' . (!empty($modSettings['xmlnews_limit']) ? $modSettings['xmlnews_limit'] : 5)
		);

	// Since it's nice to have avatars all of the same size, and in some cases the size detection may fail,
	// let's add the css in any case
	if (!empty($modSettings['avatar_max_width']) || !empty($modSettings['avatar_max_height']))
	{
		$context['html_headers'] .= '
		<style>
			.avatarresize {' . (!empty($modSettings['avatar_max_width']) ? '
				max-width:' . $modSettings['avatar_max_width'] . 'px;' : '') . (!empty($modSettings['avatar_max_height']) ? '
				max-height:' . $modSettings['avatar_max_height'] . 'px;' : '') . '
			}
		</style>';
	}

	// Default JS variables for use in every theme
	addJavascriptVar(array(
		'elk_theme_url' => JavaScriptEscape($settings['theme_url']),
		'elk_default_theme_url' => JavaScriptEscape($settings['default_theme_url']),
		'elk_images_url' => JavaScriptEscape($settings['images_url']),
		'elk_smiley_url' => JavaScriptEscape($modSettings['smileys_url']),
		'elk_scripturl' => '\'' . $scripturl . '\'',
		'elk_iso_case_folding' => $context['server']['iso_case_folding'] ? 'true' : 'false',
		'elk_charset' => '"UTF-8"',
		'elk_session_id' => JavaScriptEscape($context['session_id']),
		'elk_session_var' => JavaScriptEscape($context['session_var']),
		'elk_member_id' => $context['user']['id'],
		'ajax_notification_text' => JavaScriptEscape($txt['ajax_in_progress']),
		'ajax_notification_cancel_text' => JavaScriptEscape($txt['modify_cancel']),
		'help_popup_heading_text' => JavaScriptEscape($txt['help_popup']),
		'use_click_menu' => !empty($options['use_click_menu']) ? 'true' : 'false',
		'todayMod' => !empty($modSettings['todayMod']) ? (int) $modSettings['todayMod'] : 0)
	);

	// Auto video embeding enabled, then load the needed JS
	if (!empty($modSettings['enableVideoEmbeding']))
	{
		addInlineJavascript('
		var oEmbedtext = ({
			preview_image : ' . JavaScriptEscape($txt['preview_image']) . ',
			ctp_video : ' . JavaScriptEscape($txt['ctp_video']) . ',
			hide_video : ' . JavaScriptEscape($txt['hide_video']) . ',
			youtube : ' . JavaScriptEscape($txt['youtube']) . ',
			vimeo : ' . JavaScriptEscape($txt['vimeo']) . ',
			dailymotion : ' . JavaScriptEscape($txt['dailymotion']) . '
		});', true);

		loadJavascriptFile('elk_jquery_embed.js', array('defer' => true));
	}

	// Prettify code tags? Load the needed JS and CSS.
	if (!empty($modSettings['enableCodePrettify']))
	{
		loadCSSFile('prettify.css');
		loadJavascriptFile('prettify.min.js', array('defer' => true));

		addInlineJavascript('
		$(document).ready(function(){
			prettyPrint();
		});', true);
	}

	// Relative times?
	if (!empty($modSettings['todayMod']) && $modSettings['todayMod'] > 2)
	{
		addInlineJavascript('
		var oRttime = ({
			referenceTime : ' . forum_time() * 1000 . ',
			now : ' . JavaScriptEscape($txt['rt_now']) . ',
			minute : ' . JavaScriptEscape($txt['rt_minute']) . ',
			minutes : ' . JavaScriptEscape($txt['rt_minutes']) . ',
			hour : ' . JavaScriptEscape($txt['rt_hour']) . ',
			hours : ' . JavaScriptEscape($txt['rt_hours']) . ',
			day : ' . JavaScriptEscape($txt['rt_day']) . ',
			days : ' . JavaScriptEscape($txt['rt_days']) . ',
			week : ' . JavaScriptEscape($txt['rt_week']) . ',
			weeks : ' . JavaScriptEscape($txt['rt_weeks']) . ',
			month : ' . JavaScriptEscape($txt['rt_month']) . ',
			months : ' . JavaScriptEscape($txt['rt_months']) . ',
			year : ' . JavaScriptEscape($txt['rt_year']) . ',
			years : ' . JavaScriptEscape($txt['rt_years']) . ',
		});
		updateRelativeTime();', true);
		$context['using_relative_time'] = true;
	}

	// Queue our Javascript
	loadJavascriptFile(array('elk_jquery_plugins.js', 'script.js', 'script_elk.js', 'theme.js'));

	// If we think we have mail to send, let's offer up some possibilities... robots get pain (Now with scheduled task support!)
	if ((!empty($modSettings['mail_next_send']) && $modSettings['mail_next_send'] < time() && empty($modSettings['mail_queue_use_cron'])) || empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
	{
		if (isBrowser('possibly_robot'))
		{
			// @todo Maybe move this somewhere better?!
			require_once(CONTROLLERDIR . '/ScheduledTasks.controller.php');
			$controller = new ScheduledTasks_Controller();

			// What to do, what to do?!
			if (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
				$controller->action_autotask();
			else
				$controller->action_reducemailqueue();
		}
		else
		{
			$type = empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time() ? 'task' : 'mailq';
			$ts = $type == 'mailq' ? $modSettings['mail_next_send'] : $modSettings['next_task_time'];

			addInlineJavascript('
		function elkAutoTask()
		{
			var tempImage = new Image();
			tempImage.src = elk_scripturl + "?scheduled=' . $type . ';ts=' . $ts . '";
		}
		window.setTimeout("elkAutoTask();", 1);', true);
		}
	}

	// Any files to include at this point?
	call_integration_include_hook('integrate_theme_include');

	// Call load theme integration functions.
	call_integration_hook('integrate_load_theme');

	// We are ready to go.
	$context['theme_loaded'] = true;
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
	$result = $db->query('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND id_theme IN (1, {int:theme_guests})',
		array(
			'no_member' => 0,
			'theme_guests' => $modSettings['theme_guests'],
		)
	);
	while ($row = $db->fetch_assoc($result))
	{
		$settings[$row['variable']] = $row['value'];

		// Is this the default theme?
		if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1')
			$settings['default_' . $row['variable']] = $row['value'];
	}
	$db->free_result($result);

	// Check we have some directories setup.
	if (empty($settings['template_dirs']))
	{
		$settings['template_dirs'] = array($settings['theme_dir']);

		// Based on theme (if there is one).
		if (!empty($settings['base_theme_dir']))
			$settings['template_dirs'][] = $settings['base_theme_dir'];

		// Lastly the default theme.
		if ($settings['theme_dir'] != $settings['default_theme_dir'])
			$settings['template_dirs'][] = $settings['default_theme_dir'];
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
 * - loads a template file with the name template_name from the current, default, or base theme.
 * - detects a wrong default theme directory and tries to work around it.
 * - can be used to only load style sheets by using false as the template name
 *   loading of style sheets with this function is @deprecated, use loadCSSFile instead
 * - if $settings['template_dirs'] is empty, it delays the loading of the template
 *
 * @uses the requireTemplate() function to actually load the file.
 * @param string|false $template_name
 * @param string[]|string $style_sheets any style sheets to load with the template
 * @param bool $fatal = true if fatal is true, dies with an error message if the template cannot be found
 *
 * @return boolean|null
 */
function loadTemplate($template_name, $style_sheets = array(), $fatal = true)
{
	global $context, $settings;
	static $delay = array();

	// If we don't know yet the default theme directory, let's wait a bit.
	if (empty($settings['template_dirs']))
	{
		$delay[] = array(
			$template_name,
			$style_sheets,
			$fatal
		);
		return;
	}
	// If instead we know the default theme directory and we have delayed something, it's time to process
	elseif (!empty($delay))
	{
		foreach ($delay as $val)
			requireTemplate($val[0], $val[1], $val[2]);

		// Forget about them (load them only once)
		$delay = array();
	}

	requireTemplate($template_name, $style_sheets, $fatal);
}

/**
 * <b>Internal function! Do not use it, use loadTemplate instead</b>
 *
 * What it does:
 * - loads a template file with the name template_name from the current, default, or base theme.
 * - detects a wrong default theme directory and tries to work around it.
 * - can be used to only load style sheets by using false as the template name
 *   loading of style sheets with this function is @deprecated, use loadCSSFile instead
 *
 * @uses the template_include() function to include the file.
 * @param string|false $template_name
 * @param string[]|string $style_sheets any style sheets to load with the template
 * @param bool $fatal = true if fatal is true, dies with an error message if the template cannot be found
 *
 * @return boolean|null
 */
function requireTemplate($template_name, $style_sheets, $fatal)
{
	global $context, $settings, $txt, $scripturl, $db_show_debug;
	static $default_loaded = false;

	if (!is_array($style_sheets))
		$style_sheets = array($style_sheets);

	if ($default_loaded === false)
	{
		loadCSSFile('index.css');
		$default_loaded = true;
	}

	// Any specific template style sheets to load?
	if (!empty($style_sheets))
	{
		$sheets = array();
		foreach ($style_sheets as $sheet)
		{
			$sheets[] = stripos('.css', $sheet) !== false ? $sheet : $sheet . '.css';
			if ($sheet == 'admin' && !empty($context['theme_variant']))
				$sheets[] = $context['theme_variant'] . '/admin' . $context['theme_variant'] . '.css';
		}
		loadCSSFile($sheets);
	}

	// No template to load?
	if ($template_name === false)
		return true;

	$loaded = false;
	foreach ($settings['template_dirs'] as $template_dir)
	{
		if (file_exists($template_dir . '/' . $template_name . '.template.php'))
		{
			$loaded = true;
			template_include($template_dir . '/' . $template_name . '.template.php', true);
			break;
		}
	}

	if ($loaded)
	{
		if ($db_show_debug === true)
			$context['debug']['templates'][] = $template_name . ' (' . basename($template_dir) . ')';

		// If they have specified an initialization function for this template, go ahead and call it now.
		if (function_exists('template_' . $template_name . '_init'))
			call_user_func('template_' . $template_name . '_init');
	}
	// Hmmm... doesn't exist?!  I don't suppose the directory is wrong, is it?
	elseif (!file_exists($settings['default_theme_dir']) && file_exists(BOARDDIR . '/themes/default'))
	{
		$settings['default_theme_dir'] = BOARDDIR . '/themes/default';
		$settings['template_dirs'][] = $settings['default_theme_dir'];

		if (!empty($context['user']['is_admin']) && !isset($_GET['th']))
		{
			loadLanguage('Errors');
			if (!isset($context['security_controls_files']['title']))
				$context['security_controls_files']['title'] = $txt['generic_warning'];
			$context['security_controls_files']['errors']['theme_dir'] = '<a href="' . $scripturl . '?action=admin;area=theme;sa=list;th=1;' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['theme_dir_wrong'] . '</a>';
		}

		loadTemplate($template_name);
	}
	// Cause an error otherwise.
	elseif ($template_name != 'Errors' && $template_name != 'index' && $fatal)
		fatal_lang_error('theme_template_error', 'template', array((string) $template_name));
	elseif ($fatal)
		die(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load themes/default/%s.template.php!', (string) $template_name), 'template'));
	else
		return false;
}

/**
 * Load a sub-template.
 *
 * What it does:
 * - loads the sub template specified by sub_template_name, which must be in an already-loaded template.
 * - if ?debug is in the query string, shows administrators a marker after every sub template
 * for debugging purposes.
 *
 * @todo get rid of reading $_REQUEST directly
 * @param string $sub_template_name
 * @param bool|string $fatal = false, $fatal = true is for templates that
 *                 shouldn't get a 'pretty' error screen 'ignore' to skip
 */
function loadSubTemplate($sub_template_name, $fatal = false)
{
	global $context, $txt, $db_show_debug;

	if ($db_show_debug === true)
		$context['debug']['sub_templates'][] = $sub_template_name;

	// Figure out what the template function is named.
	$theme_function = 'template_' . $sub_template_name;

	if (function_exists($theme_function))
		$theme_function();
	elseif ($fatal === false)
		fatal_lang_error('theme_template_error', 'template', array((string) $sub_template_name));
	elseif ($fatal !== 'ignore')
		die(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load the %s sub template!', (string) $sub_template_name), 'template'));

	// Are we showing debugging for templates?  Just make sure not to do it before the doctype...
	if (allowedTo('admin_forum') && isset($_REQUEST['debug']) && !in_array($sub_template_name, array('init')) && ob_get_length() > 0 && !isset($_REQUEST['xml']))
	{
		echo '
<div style="font-size: 8pt; border: 1px dashed red; background: orange; text-align: center; font-weight: bold;">---- ', $sub_template_name, ' ends ----</div>';
	}
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
	if (empty($filenames))
		return;

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
	$params['index_name'] = 'javascript_files';
	$params['debug_index'] = 'javascript';

	loadAssetFile($filenames, $params, $id);
}

/**
 * Add an asset (css, js or other) file for output later
 *
 * What it does:
 * - Can be passed an array of filenames, all which will have the same
 *   parameters applied,
 * - if you need specific parameters on a per file basis, call it multiple times
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
	$cache_name = 'load_' . $params['extension'] . '_' . md5($settings['theme_dir'] . implode('_', $filenames));
	if (($temp = cache_get_data($cache_name, 600)) !== null)
	{
		if (empty($context[$params['index_name']]))
			$context[$params['index_name']] = array();
		$context[$params['index_name']] += $temp;

		if ($db_show_debug === true)
		{
			foreach ($temp as $temp_params)
			{
				$context['debug'][$params['debug_index']][] = $temp_params['options']['basename'] . '(' . (!empty($temp_params['options']['local']) ? (!empty($temp_params['options']['url']) ? basename($temp_params['options']['url']) : basename($temp_params['options']['dir'])) : '') . ')';
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
					$context['debug'][$params['debug_index']][] = $params['basename'] . '(' . (!empty($params['local']) ? (!empty($params['url']) ? basename($params['url']) : basename($params['dir'])) : '') . ')';

			}

			// Save it so we don't have to build this so often
			cache_put_data($cache_name, $this_build, 600);
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
	global $context;

	if (empty($vars) || !is_array($vars))
		return;

	foreach ($vars as $key => $value)
		$context['javascript_vars'][$key] = !empty($escape) ? JavaScriptEscape($value) : $value;
}

/**
 * Add a block of inline Javascript code to be executed later
 *
 * What it does:
 * - only use this if you have to, generally external JS files are better, but for very small scripts
 *   or for scripts that require help from PHP/whatever, this can be useful.
 * - all code added with this function is added to the same <script> tag so do make sure your JS is clean!
 *
 * @param string $javascript
 * @param bool $defer = false, define if the script should load in <head> or before the closing <html> tag
 */
function addInlineJavascript($javascript, $defer = false)
{
	global $context;

	if (!empty($javascript))
		$context['javascript_inline'][(!empty($defer) ? 'defer' : 'standard')][] = $javascript;
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
	global $user_info, $language, $settings, $context, $modSettings;
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

		// Try to find the language file.
		$found = false;
		foreach ($attempts as $k => $file)
		{
			if (file_exists($file[0] . '/languages/' . $file[2] . '/' . $file[1] . '.' . $file[2] . '.php'))
			{
				// Include it!
				template_include($file[0] . '/languages/' . $file[2] . '/' . $file[1] . '.' . $file[2] . '.php');

				// Note that we found it.
				$found = true;

				break;
			}
			// @deprecated since 1.0 - old way of archiving language files, all in one directory
			elseif (file_exists($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php'))
			{
				// Include it!
				template_include($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php');

				// Note that we found it.
				$found = true;

				break;
			}
		}

		// That couldn't be found!  Log the error, but *try* to continue normally.
		if (!$found && $fatal)
		{
			log_error(sprintf($txt['theme_language_error'], $template_name . '.' . $lang, 'template'));
			break;
		}
	}

	if ($fix_arrays)
	{
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

	// Keep track of what we're up to soldier.
	if ($db_show_debug === true)
		$context['debug']['language_files'][] = $template_name . '.' . $lang . ' (' . $theme_name . ')';

	// Remember what we have loaded, and in which language.
	$already_loaded[$template_name] = $lang;

	// Return the language actually loaded.
	return $lang;
}

/**
 * Get all parent boards (requires first parent as parameter)
 *
 * What it does:
 * - It finds all the parents of id_parent, and that board itself.
 * - Additionally, it detects the moderators of said boards.
 * - Returns an array of information about the boards found.
 *
 * @param int $id_parent
 */
function getBoardParents($id_parent)
{
	global $scripturl;

	$db = database();

	// First check if we have this cached already.
	if (($boards = cache_get_data('board_parents-' . $id_parent, 480)) === null)
	{
		$boards = array();
		$original_parent = $id_parent;

		// Loop while the parent is non-zero.
		while ($id_parent != 0)
		{
			$result = $db->query('', '
				SELECT
					b.id_parent, b.name, {int:board_parent} AS id_board, IFNULL(mem.id_member, 0) AS id_moderator,
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
				fatal_lang_error('parent_not_found', 'critical');
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

		cache_put_data('board_parents-' . $original_parent, $boards, 480);
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
	global $settings, $modSettings;

	// Either we don't use the cache, or its expired.
	if (!$use_cache || ($languages = cache_get_data('known_languages', !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600)) == null)
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
		if (!empty($modSettings['cache_enable']))
			cache_put_data('known_languages', $languages, !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
	}

	return $languages;
}

/**
 * Replace all vulgar words with respective proper words. (substring or whole words..)
 *
 * What it does:
 * - it censors the passed string.
 * - if the admin setting allow_no_censored is on it does not censor unless force is also set.
 * - if the admin setting allow_no_censored is off will censor words unless the user has set
 * it to not censor in their profile and force is off
 * - it caches the list of censored words to reduce parsing.
 * - Returns the censored text
 *
 * @param string $text
 * @param bool $force = false
 */
function censorText(&$text, $force = false)
{
	global $modSettings, $options;
	static $censor_vulgar = null, $censor_proper = null;

	// Are we going to censor this string
	if ((!empty($options['show_no_censored']) && !empty($modSettings['allow_no_censored']) && !$force) || empty($modSettings['censor_vulgar']) || trim($text) === '')
		return $text;

	// If they haven't yet been loaded, load them.
	if ($censor_vulgar == null)
	{
		$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
		$censor_proper = explode("\n", $modSettings['censor_proper']);

		// Quote them for use in regular expressions.
		if (!empty($modSettings['censorWholeWord']))
		{
			for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
			{
				$censor_vulgar[$i] = str_replace(array('\\\\\\*', '\\*', '&', '\''), array('[*]', '[^\s]*?', '&amp;', '&#039;'), preg_quote($censor_vulgar[$i], '/'));
				$censor_vulgar[$i] = '/(?<=^|\W)' . $censor_vulgar[$i] . '(?=$|\W)/u' . (empty($modSettings['censorIgnoreCase']) ? '' : 'i');

				// @todo I'm thinking the old way is some kind of bug and this is actually fixing it.
				//if (strpos($censor_vulgar[$i], '\'') !== false)
					//$censor_vulgar[$i] = str_replace('\'', '&#039;', $censor_vulgar[$i]);
			}
		}
	}

	// Censoring isn't so very complicated :P.
	if (empty($modSettings['censorWholeWord']))
		$text = empty($modSettings['censorIgnoreCase']) ? str_replace($censor_vulgar, $censor_proper, $text) : str_ireplace($censor_vulgar, $censor_proper, $text);
	else
		$text = preg_replace($censor_vulgar, $censor_proper, $text);

	return $text;
}

/**
 * Load the template/language file using eval or require? (with eval we can show an error message!)
 *
 * What it does:
 * - loads the template or language file specified by filename.
 * - uses eval unless disableTemplateEval is enabled.
 * - outputs a parse error if the file did not exist or contained errors.
 * - attempts to detect the error and line, and show detailed information.
 *
 * @param string $filename
 * @param bool $once = false, if true only includes the file once (like include_once)
 */
function template_include($filename, $once = false)
{
	global $context, $settings, $txt, $scripturl, $modSettings, $boardurl;
	global $maintenance, $mtitle, $mmessage;
	static $templates = array();

	// We want to be able to figure out any errors...
	@ini_set('track_errors', '1');

	// Don't include the file more than once, if $once is true.
	if ($once && in_array($filename, $templates))
		return;
	// Add this file to the include list, whether $once is true or not.
	else
		$templates[] = $filename;

	// Are we going to use eval?
	if (empty($modSettings['disableTemplateEval']))
	{
		$file_found = file_exists($filename) && eval('?' . '>' . rtrim(file_get_contents($filename))) !== false;
		$settings['current_include_filename'] = $filename;
	}
	else
	{
		$file_found = file_exists($filename);

		if ($once && $file_found)
			require_once($filename);
		elseif ($file_found)
			require($filename);
	}

	if ($file_found !== true)
	{
		@ob_end_clean();
		if (!empty($modSettings['enableCompressedOutput']))
			ob_start('ob_gzhandler');
		else
			ob_start();

		if (isset($_GET['debug']))
			header('Content-Type: application/xhtml+xml; charset=UTF-8');

		// Don't cache error pages!!
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		if (!isset($txt['template_parse_error']))
		{
			$txt['template_parse_error'] = 'Template Parse Error!';
			$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system.  This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please contact the administrator.<br /><br />You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			$txt['template_parse_error_details'] = 'There was a problem loading the <span style="font-family: monospace;"><strong>%1$s</strong></span> template or language file.  Please check the syntax and try again - remember, single quotes (<span style="font-family: monospace;">\'</span>) often have to be escaped with a slash (<span style="font-family: monospace;">\\</span>).  To see more specific error information from PHP, try <a href="%2$s%1$s" class="extern">accessing the file directly</a>.<br /><br />You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="%3$s">use the default theme</a>.';
			$txt['template_parse_undefined'] = 'An undefined error occurred during the parsing of this template';
		}

		// First, let's get the doctype and language information out of the way.
		echo '<!DOCTYPE html>
<html ', !empty($context['right_to_left']) ? 'dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';

		if (!empty($maintenance) && !allowedTo('admin_forum'))
			echo '
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';
		elseif (!allowedTo('admin_forum'))
			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', $txt['template_parse_error_message'], '
	</body>
</html>';
		else
		{
			require_once(SUBSDIR . '/Package.subs.php');

			$error = fetch_web_data($boardurl . strtr($filename, array(BOARDDIR => '', strtr(BOARDDIR, '\\', '/') => '')));
			if (empty($error) && ini_get('track_errors') && !empty($php_errormsg))
				$error = $php_errormsg;
			elseif (empty($error))
				$error = $txt['template_parse_undefined'];

			$error = strtr($error, array('<b>' => '<strong>', '</b>' => '</strong>'));

			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', sprintf($txt['template_parse_error_details'], strtr($filename, array(BOARDDIR => '', strtr(BOARDDIR, '\\', '/') => '')), $boardurl, $scripturl . '?theme=1');

			if (!empty($error))
				echo '
		<hr />

		<div style="margin: 0 20px;"><span style="font-family: monospace;">', strtr(strtr($error, array('<strong>' . BOARDDIR => '<strong>...', '<strong>' . strtr(BOARDDIR, '\\', '/') => '<strong>...')), '\\', '/'), '</span></div>';

			// I know, I know... this is VERY COMPLICATED.  Still, it's good.
			if (preg_match('~ <strong>(\d+)</strong><br( /)?' . '>$~i', $error, $match) != 0)
			{
				$data = file($filename);
				$data2 = highlight_php_code(implode('', $data));
				$data2 = preg_split('~\<br( /)?\>~', $data2);

				// Fix the PHP code stuff...
				if (!isBrowser('gecko'))
					$data2 = str_replace("\t", '<span style="white-space: pre;">' . "\t" . '</span>', $data2);
				else
					$data2 = str_replace('<pre style="display: inline;">' . "\t" . '</pre>', "\t", $data2);

				// Now we get to work around a bug in PHP where it doesn't escape <br />s!
				$j = -1;
				foreach ($data as $line)
				{
					$j++;

					if (substr_count($line, '<br />') == 0)
						continue;

					$n = substr_count($line, '<br />');
					for ($i = 0; $i < $n; $i++)
					{
						$data2[$j] .= '&lt;br /&gt;' . $data2[$j + $i + 1];
						unset($data2[$j + $i + 1]);
					}
					$j += $n;
				}
				$data2 = array_values($data2);
				array_unshift($data2, '');

				echo '
		<div style="margin: 2ex 20px; width: 96%; overflow: auto;"><pre style="margin: 0;">';

				// Figure out what the color coding was before...
				$line = max($match[1] - 9, 1);
				$last_line = '';
				for ($line2 = $line - 1; $line2 > 1; $line2--)
					if (strpos($data2[$line2], '<') !== false)
					{
						if (preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line2], $color_match) != 0)
							$last_line = $color_match[1];
						break;
					}

				// Show the relevant lines...
				for ($n = min($match[1] + 4, count($data2) + 1); $line <= $n; $line++)
				{
					if ($line == $match[1])
						echo '</pre><div style="background: #ffb0b5;"><pre style="margin: 0;">';

					echo '<span style="color: black;">', sprintf('%' . strlen($n) . 's', $line), ':</span> ';
					if (isset($data2[$line]) && $data2[$line] != '')
						echo substr($data2[$line], 0, 2) == '</' ? preg_replace('~^</[^>]+>~', '', $data2[$line]) : $last_line . $data2[$line];

					if (isset($data2[$line]) && preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line], $color_match) != 0)
					{
						$last_line = $color_match[1];
						echo '</', substr($last_line, 1, 4), '>';
					}
					elseif ($last_line != '' && strpos($data2[$line], '<') !== false)
						$last_line = '';
					elseif ($last_line != '' && $data2[$line] != '')
						echo '</', substr($last_line, 1, 4), '>';

					if ($line == $match[1])
						echo '</pre></div><pre style="margin: 0;">';
					else
						echo "\n";
				}

				echo '</pre></div>';
			}

			echo '
	</body>
</html>';
		}

		die;
	}
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
	if (ELK == 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
		$connection = elk_db_initiate($db_server, $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix, array('persist' => $db_persist, 'non_fatal' => true, 'dont_select_db' => true, 'port' => $db_port), $db_type);

	// Either we aren't in SSI mode, or it failed.
	if (empty($connection))
		$connection = elk_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist, 'dont_select_db' => ELK == 'SSI', 'port' => $db_port), $db_type);

	// Safe guard here, if there isn't a valid connection lets put a stop to it.
	if (!$connection)
		display_db_error();

	// If in SSI mode fix up the prefix.
	$db = database();
	if (ELK == 'SSI')
		$db_prefix = $db->fix_prefix($db_prefix, $db_name);

	// Case sensitive database? Let's define a constant.
	if ($db->db_case_sensitive())
		DEFINE('DB_CASE_SENSITIVE', '1');
}

/**
 * Determine the user's avatar type and return the information as an array
 *
 * @todo this function seems more useful than expected, it should be improved. :P
 *
 * @param mixed[] $profile array containing the users profile data
 * @return mixed[] $avatar
 */
function determineAvatar($profile)
{
	global $modSettings, $scripturl, $settings, $context;

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
		$gravatar_url = '//www.gravatar.com/avatar/' . md5(strtolower($profile['email_address'])) . '?s=' . $modSettings['avatar_max_height'] . (!empty($modSettings['gravatar_rating']) ? ('&amp;r=' . $modSettings['gravatar_rating']) : '');

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
	// no custon avatar found yet, maybe a default avatar?
	elseif (!empty($modSettings['avatar_default']) && empty($profile['avatar']) && empty($profile['filename']))
	{
		// $settings not initialized? We can't do anything further..
		if (!empty($settings))
		{
			// Let's proceed with the default avatar.
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
	$avatar['gravatar_preview'] = '//www.gravatar.com/avatar/' . md5(strtolower($profile['email_address'])) . '?s=' . $modSettings['avatar_max_height'] . (!empty($modSettings['gravatar_rating']) ? ('&amp;r=' . $modSettings['gravatar_rating']) : '');

	call_integration_hook('integrate_avatar', array(&$avatar, $profile));

	return $avatar;
}

/**
 * Get information about the server
 */
function detectServer()
{
	global $context;

	$context['server'] = array(
		'is_iis' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false,
		'is_apache' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false,
		'is_litespeed' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false,
		'is_lighttpd' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false,
		'is_nginx' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false,
		'is_cgi' => isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false,
		'is_windows' => strpos(PHP_OS, 'WIN') === 0,
		'iso_case_folding' => ord(strtolower(chr(138))) === 154,
	);

	// A bug in some versions of IIS under CGI (older ones) makes cookie setting not work with Location: headers.
	$context['server']['needs_login_fix'] = $context['server']['is_cgi'] && $context['server']['is_iis'];
}

/**
 * Do some important security checks:
 *
 * What it does:
 * - checks the existence of critical files e.g. install.php
 * - checks for an active admin session.
 * - checks cache directory is writable.
 * - calls secureDirectory to protect attachments & cache.
 * - checks if the forum is in maintance mode.
 */
function doSecurityChecks()
{
	global $modSettings, $context, $maintenance, $user_info, $txt, $scripturl, $user_settings, $options;

	$show_warnings = false;

	if (allowedTo('admin_forum') && !$user_info['is_guest'])
	{
		// If agreement is enabled, at least the english version shall exists
		if ($modSettings['requireAgreement'] && !file_exists(BOARDDIR . '/agreement.txt'))
		{
			$context['security_controls_files']['title'] = $txt['generic_warning'];
			$context['security_controls_files']['errors']['agreement'] = $txt['agreement_missing'];
			$show_warnings = true;
		}

		// Cache directory writeable?
		if (!empty($modSettings['cache_enable']) && !is_writable(CACHEDIR))
		{
			$context['security_controls_files']['title'] = $txt['generic_warning'];
			$context['security_controls_files']['errors']['cache'] = $txt['cache_writable'];
			$show_warnings = true;
		}

		// @todo add a hook here
		$securityFiles = array('install.php', 'upgrade.php', 'convert.php', 'repair_paths.php', 'repair_settings.php', 'Settings.php~', 'Settings_bak.php~');
		foreach ($securityFiles as $securityFile)
		{
			if (file_exists(BOARDDIR . '/' . $securityFile))
			{
				$context['security_controls_files']['title'] = $txt['security_risk'];
				$context['security_controls_files']['errors'][$securityFile] = sprintf($txt['not_removed'], $securityFile);
				$show_warnings = true;

				if ($securityFile == 'Settings.php~' || $securityFile == 'Settings_bak.php~')
				{
					$context['security_controls_files']['errors'][$securityFile] .= '<span class="smalltext">' . sprintf($txt['not_removed_extra'], $securityFile, substr($securityFile, 0, -1)) . '</span>';
				}
			}
		}

		// We are already checking so many files...just few more doesn't make any difference! :P
		require_once(SUBSDIR . '/Attachments.subs.php');
		$path = getAttachmentPath();
		secureDirectory($path, true);
		secureDirectory(CACHEDIR);

		// Active admin session?
		if (empty($modSettings['securityDisable']) && (isset($_SESSION['admin_time']) && $_SESSION['admin_time'] + ($modSettings['admin_session_lifetime'] * 60) > time()))
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
		$context['warning_controls']['open_mod_reports'] = '<a href="' . $scripturl . '?action=moderate;area=reports">' . sprintf($txt['mod_reports_waiting'], $context['open_mod_reports']) . '</a>';

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
		Template_Layers::getInstance()->addAfter('admin_warning', 'body');
}

/**
 * Returns the current server load for nix systems
 *
 * - Used to enable / disable features based on current system overhead
 */
function detectServerLoad()
{
	if (stristr(PHP_OS, 'win'))
		return false;

	$cores = detectServerCores();

	// The internal function should always be available
	if (function_exists('sys_getloadavg'))
	{
		$sys_load = sys_getloadavg();
        return $sys_load[0] / $cores;
	}
	// Maybe someone has a custom compile
	else
	{
		$load_average = @file_get_contents('/proc/loadavg');

		if (!empty($load_average) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $load_average, $matches) != 0)
			return (float) $matches[1] / $cores;
		elseif (($load_average = @`uptime`) != null && preg_match('~load average[s]?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $load_average, $matches) != 0)
			return (float) $matches[1] / $cores;

		return false;
	}
}

/**
 * Determines the number of cpu cores available
 *
 * - Used to normalize server load based on cores
 *
 * @return int
 */
function detectServerCores()
{
	$cores = @file_get_contents('/proc/cpuinfo');

	if (!empty($cores))
	{
		$cores = preg_match_all('~^physical id~m', $cores, $matches);
		if (!empty($cores))
			return (int) $cores;
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
	if ($array_form === null)
	{
		$array_form = Util::unserialize($variable);

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
