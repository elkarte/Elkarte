<?php

/**
 * This file has the hefty job of loading information for the forum.
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

use BBC\ParserWrapper;
use ElkArte\Cache\Cache;
use ElkArte\Debug;
use ElkArte\Errors\Errors;
use ElkArte\FileFunctions;
use ElkArte\Hooks;
use ElkArte\Http\Headers;
use ElkArte\Themes\ThemeLoader;
use ElkArte\Languages\Txt;
use ElkArte\User;
use ElkArte\Util;
use ElkArte\AttachmentsDirectory;

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
 * @global array $modSettings is a giant array of all the forum-wide settings and statistics.
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
			SELECT 
			    variable, value
			FROM {db_prefix}settings',
			array()
		);
		$modSettings = array();
		if (!$request)
		{
			Errors::instance()->display_db_error();
		}
		while (($row = $request->fetch_row()))
		{
			$modSettings[$row[0]] = $row[1];
		}
		$request->free_result();

		// Do a few things to protect against missing settings or settings with invalid values...
		if (empty($modSettings['defaultMaxTopics']) || $modSettings['defaultMaxTopics'] <= 0 || $modSettings['defaultMaxTopics'] > 999)
		{
			$modSettings['defaultMaxTopics'] = 20;
		}

		if (empty($modSettings['defaultMaxMessages']) || $modSettings['defaultMaxMessages'] <= 0 || $modSettings['defaultMaxMessages'] > 999)
		{
			$modSettings['defaultMaxMessages'] = 15;
		}

		if (empty($modSettings['defaultMaxMembers']) || $modSettings['defaultMaxMembers'] <= 0 || $modSettings['defaultMaxMembers'] > 999)
		{
			$modSettings['defaultMaxMembers'] = 30;
		}

		$modSettings['warning_enable'] = $modSettings['warning_settings'][0];

		$cache->put('modSettings', $modSettings, 90);
	}

	$hooks->loadIntegrations();

	// Setting the timezone is a requirement for some functions in PHP >= 5.1.
	if (isset($modSettings['default_timezone']))
	{
		date_default_timezone_set($modSettings['default_timezone']);
	}

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
		{
			call_integration_hook('integrate_load_average', array($modSettings['load_average']));
		}

		// Let's have at least a zero
		if (empty($modSettings['loadavg_forum']) || $modSettings['load_average'] === false)
		{
			$modSettings['current_load'] = 0;
		}
		else
		{
			$modSettings['current_load'] = $modSettings['load_average'];
		}

		if (!empty($modSettings['loadavg_forum']) && $modSettings['current_load'] >= $modSettings['loadavg_forum'])
		{
			Errors::instance()->display_loadavg_error();
		}
	}
	else
	{
		$modSettings['current_load'] = 0;
	}

	// Is post moderation alive and well?
	$modSettings['postmod_active'] = !isset($modSettings['admin_features']) || in_array('pm', explode(',', $modSettings['admin_features']));

	if (!isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) == 'off')
	{
		$modSettings['secureCookies'] = 0;
	}

	// Integration is cool.
	if (defined('ELK_INTEGRATION_SETTINGS'))
	{
		$integration_settings = Util::unserialize(ELK_INTEGRATION_SETTINGS);
		foreach ($integration_settings as $hook => $function)
		{
			add_integration_function($hook, $function);
		}
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
 * @deprecated kept until any trace of $user_info has been completely removed
 */
function loadUserSettings()
{
	User::load(true);
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
	global $txt, $scripturl, $context, $modSettings, $board_info, $board, $topic;

	$db = database();
	$cache = Cache::instance();

	// Assume they are not a moderator.
	User::$info->is_mod = false;
	// @since 1.0.5 - is_mod takes into account only local (board) moderators,
	// and not global moderators, is_moderator is meant to take into account both.
	User::$info->is_moderator = false;

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
		{
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
		}
		else
		{
			loadPermissions();
			new ElkArte\Themes\ThemeLoader();
			if (!empty(User::$info->possibly_robot))
			{
				Headers::instance()
					->removeHeader('all')
					->headerSpecial('HTTP/1.1 410 Gone')
					->sendHeaders();
			}

			throw new \ElkArte\Exceptions\Exception('topic_gone', false);
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
		{
			$temp = $cache->get('topic_board-' . $topic, 120);
		}
		else
		{
			$temp = $cache->get('board-' . $board, 120);
		}

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
				b.id_theme, b.override_theme, b.count_posts, b.old_posts, b.id_profile, b.redirect,
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
		if ($request->num_rows() > 0)
		{
			$row = $request->fetch_assoc();

			// Set the current board.
			if (!empty($row['id_board']))
			{
				$board = (int) $row['id_board'];
			}

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
				'old_posts' => empty($row['old_posts']),
				'cur_topic_approved' => empty($topic) || $row['approved'],
				'cur_topic_starter' => empty($topic) ? 0 : $row['id_member_started'],
			);

			// Load the membergroups allowed, and check permissions.
			$board_info['groups'] = $row['member_groups'] === '' ? array() : explode(',', $row['member_groups']);
			$board_info['deny_groups'] = $row['deny_member_groups'] === '' ? array() : explode(',', $row['deny_member_groups']);

			call_integration_hook('integrate_loaded_board', array(&$board_info, &$row));

			do
			{
				if (!empty($row['id_moderator']))
				{
					$board_info['moderators'][$row['id_moderator']] = array(
						'id' => $row['id_moderator'],
						'name' => $row['real_name'],
						'href' => getUrl('profile', ['action' => 'profile', 'u' => $row['id_moderator']]),
						'link' => '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $row['id_moderator']]) . '">' . $row['real_name'] . '</a>'
					);
				}
			} while (($row = $request->fetch_assoc()));

			// If the board only contains unapproved posts and the user can't approve then they can't see any topics.
			// If that is the case do an additional check to see if they have any topics waiting to be approved.
			if ($board_info['num_topics'] == 0 && $modSettings['postmod_active'] && !allowedTo('approve_posts'))
			{
				// Free the previous result
				$request->free_result();

				// @todo why is this using id_topic?
				// @todo Can this get cached?
				$request = $db->query('', '
					SELECT COUNT(id_topic)
					FROM {db_prefix}topics
					WHERE id_member_started={int:id_member}
						AND approved = {int:unapproved}
						AND id_board = {int:board}',
					array(
						'id_member' => User::$info->id,
						'unapproved' => 0,
						'board' => $board,
					)
				);

				list ($board_info['unapproved_user_topics']) = $request->fetch_row();
			}

			if ($cache->isEnabled() && (empty($topic) || $cache->levelHigherThan(2)))
			{
				// @todo SLOW?
				if (!empty($topic))
				{
					$cache->put('topic_board-' . $topic, $board_info, 120);
				}

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
		$request->free_result();
	}

	if (!empty($topic))
	{
		$_GET['board'] = (int) $board;
	}

	if (!empty($board))
	{
		// Now check if the user is a moderator.
		User::$info->is_mod = isset($board_info['moderators'][User::$info->id]);

		if (count(array_intersect(User::$info->groups, $board_info['groups'])) == 0 && User::$info->is_admin === false)
		{
			$board_info['error'] = 'access';
		}
		if (!empty($modSettings['deny_boards_access']) && count(array_intersect(User::$info->groups, $board_info['deny_groups'])) != 0 && User::$info->is_admin === false)
		{
			$board_info['error'] = 'access';
		}

		// Build up the linktree.
		$context['linktree'] = array_merge(
			$context['linktree'],
			array(array(
					  'url' => getUrl('action', $modSettings['default_forum_action']) . '#c' . $board_info['cat']['id'],
					  'name' => $board_info['cat']['name']
				  )
			),
			array_reverse($board_info['parent_boards']),
			array(array(
					  'url' => getUrl('board', ['board' => $board, 'start' => '0', 'name' => $board_info['name']]),
					  'name' => $board_info['name']
				  )
			)
		);
	}

	// Set the template contextual information.
	$context['user']['is_mod'] = (bool) User::$info->is_mod;
	$context['user']['is_moderator'] = (bool) User::$info->is_moderator;
	$context['current_topic'] = $topic;
	$context['current_board'] = $board;

	// Hacker... you can't see this topic, I'll tell you that. (but moderators can!)
	if (!empty($board_info['error']) && (!empty($modSettings['deny_boards_access']) || $board_info['error'] != 'access' || User::$info->is_moderator === false))
	{
		// The permissions and theme need loading, just to make sure everything goes smoothly.
		loadPermissions();
		new ElkArte\Themes\ThemeLoader();

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
			Headers::instance()
				->removeHeader('all')
				->headerSpecial('HTTP/1.1 403 Forbidden')
				->sendHeaders();
			exit;
		}
		elseif (User::$info->is_guest)
		{
			Txt::load('Errors');
			is_not_guest($txt['topic_gone']);
		}
		else
		{
			if (!empty(User::$info->possibly_robot))
			{
				Headers::instance()
					->removeHeader('all')
					->headerSpecial('HTTP/1.1 410 Gone')
					->sendHeaders();
			}

			throw new \ElkArte\Exceptions\Exception('topic_gone', false);
		}
	}

	if (User::$info->is_mod)
	{
		User::$info->groups = array_merge(User::$info->groups, [3]);
	}
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
 *
 * @throws \ElkArte\Exceptions\Exception
 */
function loadPermissions()
{
	global $board, $board_info, $modSettings;

	$db = database();

	if (User::$info->is_admin)
	{
		banPermissions();

		return;
	}

	$permissions = [];
	$removals = [];

	$cache = Cache::instance();
	if ($cache->isEnabled())
	{
		$cache_groups = User::$info->groups;
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);

		// If it's a spider then cache it different.
		if (User::$info->possibly_robot)
		{
			$cache_groups .= '-spider';
		}
		$cache_key = 'permissions:' . $cache_groups;
		$cache_board_key = 'permissions:' . $cache_groups . ':' . $board;

		if ($cache->levelHigherThan(1) && !empty($board) && $cache->getVar($temp, $cache_board_key, 240) && time() - 240 > $modSettings['settings_updated'])
		{
			list (User::$info->permissions) = $temp;
			banPermissions();

			return;
		}

		if ($cache->getVar($temp, $cache_key, 240) && time() - 240 > $modSettings['settings_updated'])
		{
			if (is_array($temp))
			{
				list ($permissions, $removals) = $temp;
			}
		}
	}

	// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
	$spider_restrict = User::$info->possibly_robot && !empty($modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

	if (empty($permissions))
	{
		// Get the general permissions.
		$db->fetchQuery('
			SELECT
				permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:member_groups})
				' . $spider_restrict,
			[
				'member_groups' => User::$info->groups,
				'spider_group' => !empty($modSettings['spider_group']) && $modSettings['spider_group'] != 1 ? $modSettings['spider_group'] : 0,
			]
		)->fetch_callback(
			function ($row) use (&$removals, &$permissions) {
				if (empty($row['add_deny']))
				{
					$removals[] = $row['permission'];
				}
				else
				{
					$permissions[] = $row['permission'];
				}
			}
		);

		if (isset($cache_key))
		{
			$cache->put($cache_key, [$permissions, $removals], 240);
		}
	}

	// Get the board permissions.
	if (!empty($board))
	{
		// Make sure the board (if any) has been loaded by loadBoard().
		if (!isset($board_info['profile']))
		{
			throw new \ElkArte\Exceptions\Exception('no_board');
		}

		$db->fetchQuery('
			SELECT
				permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE (id_group IN ({array_int:member_groups})
				' . $spider_restrict . ')
				AND id_profile = {int:id_profile}',
			array(
				'member_groups' => User::$info->groups,
				'id_profile' => $board_info['profile'],
				'spider_group' => !empty($modSettings['spider_group']) && $modSettings['spider_group'] != 1 ? $modSettings['spider_group'] : 0,
			)
		)->fetch_callback(
			function ($row) use (&$removals, &$permissions) {
				if (empty($row['add_deny']))
				{
					$removals[] = $row['permission'];
				}
				else
				{
					$permissions[] = $row['permission'];
				}
			}
		);
	}

	User::$info->permissions = $permissions;

	// Remove all the permissions they shouldn't have ;).
	if (!empty($modSettings['permission_enable_deny']))
	{
		User::$info->permissions = array_diff(User::$info->permissions, $removals);
	}

	if (isset($cache_board_key) && !empty($board) && $cache->levelHigherThan(1))
	{
		$cache->put($cache_board_key, [User::$info->permissions, null], 240);
	}

	// Banned?  Watch, don't touch..
	banPermissions();

	// Load the mod cache, so we can know what additional boards they should see, but no sense in doing it for guests
	if (User::$info->is_guest === false)
	{
		User::$info->is_moderator = User::$info->is_mod || allowedTo('moderate_board');
		if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= $modSettings['settings_updated'])
		{
			require_once(SUBSDIR . '/Auth.subs.php');
			rebuildModCache();
		}
		else
		{
			User::$info->mod_cache = $_SESSION['mc'];
		}
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
 * @param int $id_theme = 0
 * @param bool $initialize = true
 * @deprecated since 2.0; use the theme object
 *
 */
function loadTheme($id_theme = 0, $initialize = true)
{
	Errors::instance()->log_deprecated('loadTheme()', '\\ElkArte\\Themes\\ThemeLoader');
	new ThemeLoader($id_theme, $initialize);
}

/**
 * Loads basic user information in to $context['user']
 */
function loadUserContext()
{
	global $context, $txt, $modSettings;

	// Set up the contextual user array.
	$context['user'] = array(
		'id' => (int) User::$info->id,
		'is_logged' => User::$info->is_guest === false,
		'is_guest' => (bool) User::$info->is_guest,
		'is_admin' => (bool) User::$info->is_admin,
		'is_mod' => (bool) User::$info->is_mod,
		'is_moderator' => (bool) User::$info->is_moderator,
		// A user can mod if they have permission to see the mod center, or they are a board/group/approval moderator.
		'can_mod' => (bool) User::$info->canMod($modSettings['postmod_active']),
		'username' => User::$info->username,
		'language' => User::$info->language,
		'email' => User::$info->email,
		'ignoreusers' => User::$info->ignoreusers,
	);

	// @todo Base language is being loaded to late, placed here temporarily
	Txt::load('index+Addons', true, true);

	// Something for the guests
	if (!$context['user']['is_guest'])
	{
		$context['user']['name'] = User::$info->name;
	}
	elseif (!empty($txt['guest_title']))
	{
		$context['user']['name'] = $txt['guest_title'];
	}

	$context['user']['smiley_set'] = determineSmileySet(User::$info->smiley_set, $modSettings['smiley_sets_known']);
	$context['smiley_enabled'] = User::$info->smiley_set !== 'none';
	$context['user']['smiley_path'] = $modSettings['smileys_url'] . '/' . $context['user']['smiley_set'] . '/';
}

/**
 * Determine the current user's smiley set
 *
 * @param array $user_smiley_set
 * @param array $known_smiley_sets
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
 * @deprecated since 2.0; use the theme object
 *
 * - Needed by scheduled tasks,
 * - Needed by any other code that needs language files before the forum (the theme) is loaded.
 */
function loadEssentialThemeData()
{
	Errors::instance()->log_deprecated('loadEssentialThemeData()', '\ElkArte\Themes\ThemeLoader::loadEssentialThemeData()');

	ThemeLoader::loadEssentialThemeData();
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
 * @param string|false $template_name
 * @param string[]|string $style_sheets any style sheets to load with the template
 * @param bool $fatal = true if fatal is true, dies with an error message if the template cannot be found
 *
 * @return bool|null
 * @deprecated since 2.0; use the theme object
 *
 * @uses the requireTemplate() function to actually load the file.
 */
function loadTemplate($template_name, $style_sheets = array(), $fatal = true)
{
	Errors::instance()->log_deprecated('loadTemplate()', 'theme()->getTemplates()->load()');

	return theme()->getTemplates()->load($template_name, $style_sheets, $fatal);
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
 *
 * @return bool
 * @deprecated since 2.0; use the theme object
 */
function loadSubTemplate($sub_template_name, $fatal = false)
{
	Errors::instance()->log_deprecated('loadSubTemplate()', 'theme()->getTemplates()->loadSubTemplate()');
	theme()->getTemplates()->loadSubTemplate($sub_template_name, $fatal);

	return true;
}

/**
 * Add a CSS file for output later
 *
 * @param string[]|string $filenames string or array of filenames to work on
 * @param array $params = array()
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
	{
		return;
	}

	if (!is_array($filenames))
	{
		$filenames = array($filenames);
	}

	if (in_array('admin.css', $filenames))
	{
		$filenames[] = $context['theme_variant'] . '/admin' . $context['theme_variant'] . '.css';
	}

	$params['subdir'] = $params['subdir'] ?? 'css';
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
 * @param array $params = array()
 * Keys are the following:
 * - ['local'] (true/false): define if the file is local, if file does not
 *     start with http its assumed local
 * - ['defer'] (true/false): define if the file should load in <head> with the
 *     defer attribute (script is fetched asynchronously) and run after page is loaded
 * - ['fallback'] (true/false): if true will attempt to load the file from the
 *     default theme if not found in the current this is the default behavior
 *     if this is not supplied
 * - ['async'] (true/false): if the script should be loaded asynchronously and
 *    as soon as its loaded, interrupt parsing to run
 * - ['stale'] (true/false/string): if true or null, use cache stale, false do
 *     not, or used a supplied string
 * @param string $id = '' optional id to use in html id=""
 */
function loadJavascriptFile($filenames, $params = array(), $id = '')
{
	if (empty($filenames))
	{
		return;
	}

	$params['subdir'] = $params['subdir'] ?? 'scripts';
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
 * @param array $params = array()
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
	{
		return;
	}

	$cache = Cache::instance();
	$fileFunc = FileFunctions::instance();

	if (!is_array($filenames))
	{
		$filenames = array($filenames);
	}

	// Static values for all these settings
	$staler_string = '';
	if (!isset($params['stale']) || $params['stale'] === true)
	{
		$staler_string = CACHE_STALE;
	}
	elseif (is_string($params['stale']))
	{
		$staler_string = ($params['stale'][0] === '?' ? $params['stale'] : '?' . $params['stale']);
	}

	$fallback = !isset($params['fallback']) || $params['fallback'] !== false;
	$dir = '/' . $params['subdir'] . '/';

	// Whoa ... we've done this before yes?
	$cache_name = 'load_' . $params['extension'] . '_' . hash('md5', $settings['theme_dir'] . implode('_', $filenames));
	$temp = [];
	if ($cache->getVar($temp, $cache_name, 600))
	{
		if (empty($context[$params['index_name']]))
		{
			$context[$params['index_name']] = [];
		}

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
		$this_build = [];

		// All the files in this group use the above parameters
		foreach ($filenames as $filename)
		{
			// Account for shorthand like admin.ext?xyz11 filenames
			$has_cache_staler = strpos($filename, '.' . $params['extension'] . '?');
			$cache_staler = $staler_string;
			if ($has_cache_staler)
			{
				$params['basename'] = substr($filename, 0, $has_cache_staler + strlen($params['extension']) + 1);
			}
			else
			{
				$params['basename'] = $filename;
			}
			$this_id = empty($id) ? str_replace('?', '_', basename($filename)) : $id;

			// Is this a local file?
			if (!empty($params['local']) || (strpos($filename, 'http') !== 0 && strpos($filename, '//') !== 0))
			{
				$params['local'] = true;
				$params['dir'] = $settings['theme_dir'] . $dir;
				$params['url'] = $settings['theme_url'];

				// Fallback if we are not already in the default theme
				if ($fallback && ($settings['theme_dir'] !== $settings['default_theme_dir']) && !$fileFunc->fileExists($settings['theme_dir'] . $dir . $params['basename']))
				{
					// Can't find it in this theme, how about the default?
					$filename = false;
					if ($fileFunc->fileExists($settings['default_theme_dir'] . $dir . $params['basename']))
					{
						$filename = $settings['default_theme_url'] . $dir . $params['basename'] . $cache_staler;
						$params['dir'] = $settings['default_theme_dir'] . $dir;
						$params['url'] = $settings['default_theme_url'];
					}
				}
				else
				{
					$filename = $settings['theme_url'] . $dir . $params['basename'] . $cache_staler;
				}
			}

			// Add it to the array for use in the template
			if (!empty($filename))
			{
				$this_build[$this_id] = array('filename' => $filename, 'options' => $params);
				$context[$params['index_name']][$this_id] = $this_build[$this_id];

				if ($db_show_debug === true)
				{
					Debug::instance()->add($params['debug_index'], $params['basename'] . '(' . (!empty($params['local']) ? (!empty($params['url']) ? basename($params['url']) : basename($params['dir'])) : '') . ')');
				}
			}

			// Save it, so we don't have to build this so often
			$cache->put($cache_name, $this_build, 600);
		}
	}
}

/**
 * Add a Javascript variable for output later (for feeding text strings and similar to JS)
 *
 * @param array $vars array of vars to include in the output done as 'varname' => 'var value'
 * @param bool $escape = false, whether or not to escape the value
 * @deprecated since 2.0; use the theme object
 *
 */
function addJavascriptVar($vars, $escape = false)
{
	Errors::instance()->log_deprecated('addJavascriptVar()', 'theme()->getTemplates()->addJavascriptVar()');
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
 * @deprecated since 2.0; use the theme object
 *
 */
function addInlineJavascript($javascript, $defer = false)
{
	Errors::instance()->log_deprecated('addInlineJavascript()', 'theme()->addInlineJavascript()');
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
 * @deprecated since 2.0; use the theme object
 *
 */
function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false)
{
	return Txt::load($template_name, $lang, $fatal);
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
 * @throws \ElkArte\Exceptions\Exception parent_not_found
 */
function getBoardParents($id_parent)
{
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
			if ($result->num_rows() == 0)
			{
				throw new \ElkArte\Exceptions\Exception('parent_not_found', 'critical');
			}
			while (($row = $result->fetch_assoc()))
			{
				if (!isset($boards[$row['id_board']]))
				{
					$id_parent = $row['id_parent'];
					$boards[$row['id_board']] = array(
						'url' => getUrl('board', ['board' => $row['id_board'], 'name' => $row['name'], 'start' => '0']),
						'name' => $row['name'],
						'level' => $row['child_level'],
						'moderators' => array()
					);
				}

				// If a moderator exists for this board, add that moderator for all children too.
				if (!empty($row['id_moderator']))
				{
					foreach ($boards as $id => $dummy)
					{
						$boards[$id]['moderators'][$row['id_moderator']] = array(
							'id' => $row['id_moderator'],
							'name' => $row['real_name'],
							'href' => getUrl('profile', ['action' => 'profile', 'u' => $row['id_moderator']]),
							'link' => '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $row['id_moderator']]) . '">' . $row['real_name'] . '</a>'
						);
					}
				}
			}
			$result->free_result();
		}

		$cache->put('board_parents-' . $original_parent, $boards, 480);
	}

	return $boards;
}

/**
 * Attempt to reload our known languages.
 *
 * @param bool $use_cache = true
 *
 * @return array
 */
function getLanguages($use_cache = true)
{
	$cache = Cache::instance();

	// Either we don't use the cache, or its expired.
	$languages = [];
	$language_dir = SOURCEDIR . '/ElkArte/Languages/Index';

	if (!$use_cache || !$cache->getVar($languages, 'known_languages', $cache->levelLowerThan(2) ? 86400 : 3600))
	{
		$dir = dir($language_dir . '/');
		while (($entry = $dir->read()))
		{
			if ($entry === '.' || $entry === '..')
			{
				continue;
			}

			$basename = basename($entry, '.php');
			$languages[$basename] = array(
				'name' => $basename,
				'selected' => false,
				'filename' => $entry,
				'location' => $language_dir . '/' . $entry,
			);
		}
		$dir->close();

		// Let's cash in on this deal.
		$cache->put('known_languages', $languages, $cache->isEnabled() && $cache->levelLowerThan(1) ? 86400 : 3600);
	}

	return $languages;
}

/**
 * Initialize a database connection.
 */
function loadDatabase()
{
	global $db_prefix, $db_name;

	// Database stuffs
	require_once(SOURCEDIR . '/database/Database.subs.php');

	// Safeguard here, if there isn't a valid connection lets put a stop to it.
	try
	{
		$db = database(false);
	}
	catch (Exception $e)
	{
		Errors::instance()->display_db_error();
	}

	// If in SSI mode fix up the prefix.
	if (ELK === 'SSI')
	{
		$db_prefix = $db->fix_prefix($db_prefix, $db_name);
	}

	// Case-sensitive database? Let's define a constant.
	// @NOTE: I think it is already taken care by the abstraction, it should be possible to remove
	if ($db->case_sensitive() && !defined('DB_CASE_SENSITIVE'))
	{
		define('DB_CASE_SENSITIVE', '1');
	}
}

/**
 * Determine the user's avatar type and return the information as an array
 *
 * @param array $profile array containing the users profile data
 *
 * @return array $avatar
 * @todo this function seems more useful than expected, it should be improved. :P
 *
 * @event integrate_avatar allows access to $avatar array before it is returned
 */
function determineAvatar($profile)
{
	global $modSettings, $settings, $context;

	if (empty($profile))
	{
		return [];
	}

	$avatar_protocol = empty($profile['avatar']) ? '' : strtolower(substr($profile['avatar'], 0, 7));
	$alt = $profile['member_name'] ?? '';

	// Build the gravatar request once.
	$gravatar = '//www.gravatar.com/avatar/' .
		hash('md5', strtolower($profile['email_address'] ?? '')) .
		'?s=' . $modSettings['avatar_max_height'] .
		(!empty($modSettings['gravatar_rating']) ? ('&amp;r=' . $modSettings['gravatar_rating']) : '') .
		((!empty($modSettings['gravatar_default']) && $modSettings['gravatar_default'] !== 'none') ? ('&amp;d=' . $modSettings['gravatar_default']) : '');

	// uploaded avatar?
	if ($profile['id_attach'] > 0 && empty($profile['avatar']))
	{
		// where are those pesky avatars?
		$avatar_url = empty($profile['attachment_type']) ? getUrl('action', ['action' => 'dlattach', 'attach' => $profile['id_attach'], 'type' => 'avatar']) : $modSettings['custom_avatar_url'] . '/' . $profile['filename'];

		$avatar = array(
			'name' => $profile['avatar'],
			'image' => '<img class="avatar avatarresize" src="' . $avatar_url . '" alt="' . $alt . '" loading="lazy" />',
			'href' => $avatar_url,
			'url' => '',
		);
	}
	// remote avatar?
	elseif ($avatar_protocol === 'http://' || $avatar_protocol === 'https:/')
	{
		$avatar = array(
			'name' => $profile['avatar'],
			'image' => '<img class="avatar avatarresize" src="' . $profile['avatar'] . '" alt="' . $alt . '" loading="lazy" />',
			'href' => $profile['avatar'],
			'url' => $profile['avatar'],
		);
	}
	// Gravatar instead?
	elseif (!empty($profile['avatar']) && $profile['avatar'] === 'gravatar')
	{
		// Gravatars URL.
		$gravatar_url = $gravatar;
		$avatar = array(
			'name' => $profile['avatar'],
			'image' => '<img class="avatar avatarresize" src="' . $gravatar_url . '" alt="' . $alt . '" loading="lazy" />',
			'href' => $gravatar_url,
			'url' => $gravatar_url,
		);
	}
	// an avatar from the gallery?
	elseif (!empty($profile['avatar']) && !($avatar_protocol === 'http://' || $avatar_protocol === 'https:/'))
	{
		$avatar = array(
			'name' => $profile['avatar'],
			'image' => '<img class="avatar avatarresize" src="' . $modSettings['avatar_url'] . '/' . $profile['avatar'] . '" alt="' . $alt . '" loading="lazy" />',
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
			if (!empty($modSettings['avatar_gravatar_enabled']) && !empty($modSettings['gravatar_as_default'])
				&& $modSettings['gravatar_default'] !== 'none')
			{
				$href = $gravatar;
			}
			else
			{
				// Use the theme, or its variants, default image
				$href = $settings['images_url'] . '/default_avatar.png';
				$href_var = $settings['actual_theme_dir'] . '/images/' . $context['theme_variant'] . '/default_avatar.png';

				if (!empty($context['theme_variant'])
					&& FileFunctions::instance()->fileExists($href_var))
				{
					$href = $settings['images_url'] . '/' . $context['theme_variant'] . '/default_avatar.png';
				}
			}

			// Let's proceed with the default avatar.
			// TODO: This should be incorporated into the theme.
			$avatar = [
				'name' => '',
				'image' => '<img class="avatar avatarresize" src="' . $href .'" alt="' . $alt . '" loading="lazy" />',
				'href' => $href,
				'url' => 'https://',
			];
		}
		else
		{
			$avatar = [];
		}
	}
	// finally ...
	else
	{
		$avatar = [
			'name' => '',
			'image' => '',
			'href' => '',
			'url' => ''
		];
	}

	// Make sure there's a preview for gravatars available.
	$avatar['gravatar_preview'] = $gravatar;

	call_integration_hook('integrate_avatar', array(&$avatar, $profile));

	return $avatar;
}

/**
 * Get information about the server
 *
 * @return \ElkArte\Server
 */
function detectServer()
{
	global $context;
	static $server = null;

	if ($server === null)
	{
		$server = new ElkArte\Server($_SERVER);
		$servers = array('iis', 'apache', 'litespeed', 'lighttpd', 'nginx', 'cgi', 'windows');
		$context['server'] = array();
		foreach ($servers as $name)
		{
			$context['server']['is_' . $name] = $server->is($name);
		}

		$context['server']['iso_case_folding'] = $server->is('iso_case_folding');
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
	global $modSettings, $context, $maintenance, $txt, $options;

	$show_warnings = false;

	$cache = Cache::instance();

	if (User::$info->is_guest === false && allowedTo('admin_forum'))
	{
		// If agreement is enabled, at least the english version shall exists
		if ($modSettings['requireAgreement'] && !file_exists(SOURCEDIR . '/ElkArte/Languages/Agreement/English.txt'))
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
		{
			$show_warnings = true;
		}

		// We are already checking so many files...just few more doesn't make any difference! :P
		$attachmentsDir = new AttachmentsDirectory($modSettings, database());
		$path = $attachmentsDir->getCurrent();
		secureDirectory($path, true);
		secureDirectory(CACHEDIR, false, '"\.(js|css)$"');

		// Active admin session?
		if (isAdminSessionActive())
		{
			$context['warning_controls']['admin_session'] = sprintf($txt['admin_session_active'], (getUrl('admin', ['action' => 'admin', 'area' => 'adminlogoff', 'redir', '{session_data}'])));
		}

		// Maintenance mode enabled?
		if (!empty($maintenance))
		{
			$context['warning_controls']['maintenance'] = sprintf($txt['admin_maintenance_active'], (getUrl('admin', ['action' => 'admin', 'area' => 'serversettings', '{session_data}'])));
		}

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
		if (User::$info->is_admin)
		{
			$context['security_controls_query']['title'] = $txt['query_command_denied'];
			$show_warnings = true;
			foreach ($_SESSION['query_command_denied'] as $command => $error)
			{
				$context['security_controls_query']['errors'][$command] = '<pre>' . Util::htmlspecialchars($error) . '</pre>';
			}
		}
		else
		{
			$context['security_controls_query']['title'] = $txt['query_command_denied_guests'];
			foreach ($_SESSION['query_command_denied'] as $command => $error)
			{
				$context['security_controls_query']['errors'][$command] = '<pre>' . sprintf($txt['query_command_denied_guests_msg'], Util::htmlspecialchars($command)) . '</pre>';
			}
		}
	}

	// Are there any members waiting for approval?
	if (allowedTo('moderate_forum') && ((!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 2) || !empty($modSettings['approveAccountDeletion'])) && !empty($modSettings['unapprovedMembers']))
	{
		$context['warning_controls']['unapproved_members'] = sprintf($txt[$modSettings['unapprovedMembers'] == 1 ? 'approve_one_member_waiting' : 'approve_many_members_waiting'], getUrl('admin', ['action' => 'admin', 'area' => 'viewmembers', 'sa' => 'browse', 'type' => 'approve']), $modSettings['unapprovedMembers']);
	}

	if (!empty($context['open_mod_reports']) && (empty(User::$settings['mod_prefs']) || User::$settings['mod_prefs'][0] == 1))
	{
		$context['warning_controls']['open_mod_reports'] = '<a href="' . getUrl('action', ['action' => 'moderate', 'area' => 'reports']) . '">' . sprintf($txt['mod_reports_waiting'], $context['open_mod_reports']) . '</a>';
	}

	if (!empty($context['open_pm_reports']) && allowedTo('admin_forum'))
	{
		$context['warning_controls']['open_pm_reports'] = '<a href="' . getUrl('action', ['action' => 'moderate', 'area' => 'pm_reports']) . '">' . sprintf($txt['pm_reports_waiting'], $context['open_pm_reports']) . '</a>';
	}

	if (isset($_SESSION['ban']['cannot_post']))
	{
		// An admin cannot be banned (technically he could), and if it is better he knows.
		$context['security_controls_ban']['title'] = sprintf($txt['you_are_post_banned'], User::$info->is_guest ? $txt['guest_title'] : User::$info->name);
		$show_warnings = true;

		$context['security_controls_ban']['errors']['reason'] = '';

		if (!empty($_SESSION['ban']['cannot_post']['reason']))
		{
			$context['security_controls_ban']['errors']['reason'] = $_SESSION['ban']['cannot_post']['reason'];
		}

		if (!empty($_SESSION['ban']['expire_time']))
		{
			$context['security_controls_ban']['errors']['reason'] .= '<span class="smalltext">' . sprintf($txt['your_ban_expires'], standardTime($_SESSION['ban']['expire_time'], false)) . '</span>';
		}
		else
		{
			$context['security_controls_ban']['errors']['reason'] .= '<span class="smalltext">' . $txt['your_ban_expires_never'] . '</span>';
		}
	}

	// Finally, let's show the layer.
	if ($show_warnings || !empty($context['warning_controls']))
	{
		theme()->getLayers()->addAfter('admin_warning', 'body');
	}
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
		{
			$disabledBBC = explode(',', $modSettings['disabledBBC']);
		}
		else
		{
			$disabledBBC = $modSettings['disabledBBC'];
		}
		ParserWrapper::instance()->setDisabled(empty($disabledBBC) ? array() : $disabledBBC);
	}

	return 1;
}

/**
 * This is necessary to support data stored in the pre-1.0.8 way (i.e. serialized)
 *
 * @param string $variable The string to convert
 * @param null|callable $save_callback The function that will save the data to the db
 * @return array the array
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
		catch (Exception $e)
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
