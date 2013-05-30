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
 * This file is mainly concerned with the Who's Online list.
 * Although, it also handles credits. :P
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class Who_Controller
{
	/**
	 * Default action of this class.
	 * ?action=who
	 */
	function action_index()
	{
		// we know how to... peek at who's online
		$this->action_who();
	}

	/**
	 * Who's online, and what are they doing?
	 * This function prepares the who's online data for the Who template.
	 * It requires the who_view permission.
	 * It is enabled with the who_enabled setting.
	 * It is accessed via ?action=who.
	 *
	 * @uses Who template, main sub-template
	 * @uses Who language file.
	 */
	function action_who()
	{
		global $context, $scripturl, $user_info, $txt, $modSettings, $memberContext;

		$db = database();

		// Permissions, permissions, permissions.
		isAllowedTo('who_view');

		// You can't do anything if this is off.
		if (empty($modSettings['who_enabled']))
			fatal_lang_error('who_off', false);

		// Load the 'Who' template.
		loadTemplate('Who');
		loadLanguage('Who');

		// Sort out... the column sorting.
		$sort_methods = array(
			'user' => 'mem.real_name',
			'time' => 'lo.log_time'
		);

		$show_methods = array(
			'members' => '(lo.id_member != 0)',
			'guests' => '(lo.id_member = 0)',
			'all' => '1=1',
		);

		// Store the sort methods and the show types for use in the template.
		$context['sort_methods'] = array(
			'user' => $txt['who_user'],
			'time' => $txt['who_time'],
		);
		$context['show_methods'] = array(
			'all' => $txt['who_show_all'],
			'members' => $txt['who_show_members_only'],
			'guests' => $txt['who_show_guests_only'],
		);

		// Can they see spiders too?
		if (!empty($modSettings['show_spider_online']) && ($modSettings['show_spider_online'] == 2 || allowedTo('admin_forum')) && !empty($modSettings['spider_name_cache']))
		{
			$show_methods['spiders'] = '(lo.id_member = 0 AND lo.id_spider > 0)';
			$show_methods['guests'] = '(lo.id_member = 0 AND lo.id_spider = 0)';
			$context['show_methods']['spiders'] = $txt['who_show_spiders_only'];
		}
		elseif (empty($modSettings['show_spider_online']) && isset($_SESSION['who_online_filter']) && $_SESSION['who_online_filter'] == 'spiders')
			unset($_SESSION['who_online_filter']);

		// Does the user prefer a different sort direction?
		if (isset($_REQUEST['sort']) && isset($sort_methods[$_REQUEST['sort']]))
		{
			$context['sort_by'] = $_SESSION['who_online_sort_by'] = $_REQUEST['sort'];
			$sort_method = $sort_methods[$_REQUEST['sort']];
		}
		// Did we set a preferred sort order earlier in the session?
		elseif (isset($_SESSION['who_online_sort_by']))
		{
			$context['sort_by'] = $_SESSION['who_online_sort_by'];
			$sort_method = $sort_methods[$_SESSION['who_online_sort_by']];
		}
		// Default to last time online.
		else
		{
			$context['sort_by'] = $_SESSION['who_online_sort_by'] = 'time';
			$sort_method = 'lo.log_time';
		}

		$context['sort_direction'] = isset($_REQUEST['asc']) || (isset($_REQUEST['sort_dir']) && $_REQUEST['sort_dir'] == 'asc') ? 'up' : 'down';

		$conditions = array();
		if (!allowedTo('moderate_forum'))
			$conditions[] = '(IFNULL(mem.show_online, 1) = 1)';

		// Fallback to top filter?
		if (isset($_REQUEST['submit_top']) && isset($_REQUEST['show_top']))
			$_REQUEST['show'] = $_REQUEST['show_top'];
		// Does the user wish to apply a filter?
		if (isset($_REQUEST['show']) && isset($show_methods[$_REQUEST['show']]))
		{
			$context['show_by'] = $_SESSION['who_online_filter'] = $_REQUEST['show'];
			$conditions[] = $show_methods[$_REQUEST['show']];
		}
		// Perhaps we saved a filter earlier in the session?
		elseif (isset($_SESSION['who_online_filter']))
		{
			$context['show_by'] = $_SESSION['who_online_filter'];
			$conditions[] = $show_methods[$_SESSION['who_online_filter']];
		}
		else
			$context['show_by'] = $_SESSION['who_online_filter'] = 'all';

		// Get the total amount of members online.
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_online AS lo
				LEFT JOIN {db_prefix}members AS mem ON (lo.id_member = mem.id_member)' . (!empty($conditions) ? '
			WHERE ' . implode(' AND ', $conditions) : ''),
			array(
			)
		);
		list ($totalMembers) = $db->fetch_row($request);
		$db->free_result($request);

		// Prepare some page index variables.
		$context['page_index'] = constructPageIndex($scripturl . '?action=who;sort=' . $context['sort_by'] . ($context['sort_direction'] == 'up' ? ';asc' : '') . ';show=' . $context['show_by'], $_REQUEST['start'], $totalMembers, $modSettings['defaultMaxMembers']);
		$context['start'] = $_REQUEST['start'];

		// Look for people online, provided they don't mind if you see they are.
		$request = $db->query('', '
			SELECT
				lo.log_time, lo.id_member, lo.url, INET_NTOA(lo.ip) AS ip, mem.real_name,
				lo.session, mg.online_color, IFNULL(mem.show_online, 1) AS show_online,
				lo.id_spider
			FROM {db_prefix}log_online AS lo
				LEFT JOIN {db_prefix}members AS mem ON (lo.id_member = mem.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_member} THEN mem.id_post_group ELSE mem.id_group END)' . (!empty($conditions) ? '
			WHERE ' . implode(' AND ', $conditions) : '') . '
			ORDER BY {raw:sort_method} {raw:sort_direction}
			LIMIT {int:offset}, {int:limit}',
			array(
				'regular_member' => 0,
				'sort_method' => $sort_method,
				'sort_direction' => $context['sort_direction'] == 'up' ? 'ASC' : 'DESC',
				'offset' => $context['start'],
				'limit' => $modSettings['defaultMaxMembers'],
			)
		);
		$context['members'] = array();
		$member_ids = array();
		$url_data = array();
		while ($row = $db->fetch_assoc($request))
		{
			$actions = @unserialize($row['url']);
			if ($actions === false)
				continue;

			// Send the information to the template.
			$context['members'][$row['session']] = array(
				'id' => $row['id_member'],
				'ip' => allowedTo('moderate_forum') ? $row['ip'] : '',
				// It is *going* to be today or yesterday, so why keep that information in there?
				'time' => strtr(relativeTime($row['log_time']), array($txt['today'] => '', $txt['yesterday'] => '')),
				'timestamp' => forum_time(true, $row['log_time']),
				'query' => $actions,
				'is_hidden' => $row['show_online'] == 0,
				'id_spider' => $row['id_spider'],
				'color' => empty($row['online_color']) ? '' : $row['online_color']
			);

			$url_data[$row['session']] = array($row['url'], $row['id_member']);
			$member_ids[] = $row['id_member'];
		}
		$db->free_result($request);

		// Load the user data for these members.
		loadMemberData($member_ids);

		// Load up the guest user.
		$memberContext[0] = array(
			'id' => 0,
			'name' => $txt['guest_title'],
			'group' => $txt['guest_title'],
			'href' => '',
			'link' => $txt['guest_title'],
			'email' => $txt['guest_title'],
			'is_guest' => true
		);

		// Are we showing spiders?
		$spiderContext = array();
		if (!empty($modSettings['show_spider_online']) && ($modSettings['show_spider_online'] == 2 || allowedTo('admin_forum')) && !empty($modSettings['spider_name_cache']))
		{
			foreach (unserialize($modSettings['spider_name_cache']) as $id => $name)
				$spiderContext[$id] = array(
					'id' => 0,
					'name' => $name,
					'group' => $txt['spiders'],
					'href' => '',
					'link' => $name,
					'email' => $name,
					'is_guest' => true
				);
		}

		$url_data = determineActions($url_data);

		// Setup the linktree and page title (do it down here because the language files are now loaded..)
		$context['page_title'] = $txt['who_title'];
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=who',
			'name' => $txt['who_title']
		);

		// Put it in the context variables.
		foreach ($context['members'] as $i => $member)
		{
			if ($member['id'] != 0)
				$member['id'] = loadMemberContext($member['id']) ? $member['id'] : 0;

			// Keep the IP that came from the database.
			$memberContext[$member['id']]['ip'] = $member['ip'];
			$context['members'][$i]['action'] = isset($url_data[$i]) ? $url_data[$i] : $txt['who_hidden'];
			if ($member['id'] == 0 && isset($spiderContext[$member['id_spider']]))
				$context['members'][$i] += $spiderContext[$member['id_spider']];
			else
				$context['members'][$i] += $memberContext[$member['id']];
		}

		// Some people can't send personal messages...
		$context['can_send_pm'] = allowedTo('pm_send');
		$context['can_send_email'] = allowedTo('send_email_to_members');

		// any profile fields disabled?
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();
	}

	/**
	 * It prepares credit and copyright information for the credits page or the admin page.
	 * Accessed by ?action=who;sa=credits
	 *
	 */
	function action_credits()
	{
		global $context, $txt;

		loadLanguage('Who');

		prepareCreditsData();

		loadTemplate('Who');
		$context['sub_template'] = 'credits';
		$context['robot_no_index'] = true;
		$context['page_title'] = $txt['credits'];
	}
}

/**
 * This function determines the actions of the members passed in urls.
 *
 * Adding actions to the Who's Online list:
 * Adding actions to this list is actually relatively easy...
 *  - for actions anyone should be able to see, just add a string named whoall_ACTION.
 *    (where ACTION is the action used in index.php.)
 *  - for actions that have a subaction which should be represented differently, use whoall_ACTION_SUBACTION.
 *  - for actions that include a topic, and should be restricted, use whotopic_ACTION.
 *  - for actions that use a message, by msg or quote, use whopost_ACTION.
 *  - for administrator-only actions, use whoadmin_ACTION.
 *  - for actions that should be viewable only with certain permissions,
 *    use whoallow_ACTION and add a list of possible permissions to the
 *    $allowedActions array, using ACTION as the key.
 *
 * @param mixed $urls  a single url (string) or an array of arrays, each inner array being (serialized request data, id_member)
 * @param string $preferred_prefix = false
 * @return array, an array of descriptions if you passed an array, otherwise the string describing their current location.
 */
function determineActions($urls, $preferred_prefix = false)
{
	global $txt, $user_info, $modSettings, $context;

	$db = database();

	if (!allowedTo('who_view'))
		return array();
	loadLanguage('Who');

	// Actions that require a specific permission level.
	$allowedActions = array(
		'admin' => array('moderate_forum', 'manage_membergroups', 'manage_bans', 'admin_forum', 'manage_permissions', 'send_mail', 'manage_attachments', 'manage_smileys', 'manage_boards', 'edit_news'),
		'ban' => array('manage_bans'),
		'boardrecount' => array('admin_forum'),
		'calendar' => array('calendar_view'),
		'editnews' => array('edit_news'),
		'mailing' => array('send_mail'),
		'maintain' => array('admin_forum'),
		'manageattachments' => array('manage_attachments'),
		'manageboards' => array('manage_boards'),
		'memberlist' => array('view_mlist'),
		'moderate' => array('access_mod_center', 'moderate_forum', 'manage_membergroups'),
		'optimizetables' => array('admin_forum'),
		'repairboards' => array('admin_forum'),
		'search' => array('search_posts'),
		'search2' => array('search_posts'),
		'setcensor' => array('moderate_forum'),
		'setreserve' => array('moderate_forum'),
		'stats' => array('view_stats'),
		'viewErrorLog' => array('admin_forum'),
		'viewmembers' => array('moderate_forum'),
	);

	if (!is_array($urls))
		$url_list = array(array($urls, $user_info['id']));
	else
		$url_list = $urls;

	// These are done to later query these in large chunks. (instead of one by one.)
	$topic_ids = array();
	$profile_ids = array();
	$board_ids = array();

	$data = array();
	foreach ($url_list as $k => $url)
	{
		// Get the request parameters..
		$actions = @unserialize($url[0]);
		if ($actions === false)
			continue;

		// If it's the admin or moderation center, and there is an area set, use that instead.
		if (isset($actions['action']) && ($actions['action'] == 'admin' || $actions['action'] == 'moderate') && isset($actions['area']))
			$actions['action'] = $actions['area'];

		// Check if there was no action or the action is display.
		if (!isset($actions['action']) || $actions['action'] == 'display')
		{
			// It's a topic!  Must be!
			if (isset($actions['topic']))
			{
				// Assume they can't view it, and queue it up for later.
				$data[$k] = $txt['who_hidden'];
				$topic_ids[(int) $actions['topic']][$k] = $txt['who_topic'];
			}
			// It's a board!
			elseif (isset($actions['board']))
			{
				// Hide first, show later.
				$data[$k] = $txt['who_hidden'];
				$board_ids[$actions['board']][$k] = $txt['who_board'];
			}
			// It's the board index!!  It must be!
			else
				$data[$k] = $txt['who_index'];
		}
		// Probably an error or some goon?
		elseif ($actions['action'] == '')
			$data[$k] = $txt['who_index'];
		// Some other normal action...?
		else
		{
			// Viewing/editing a profile.
			if ($actions['action'] == 'profile')
			{
				// Whose?  Their own?
				if (empty($actions['u']))
					$actions['u'] = $url[1];

				$data[$k] = $txt['who_hidden'];
				$profile_ids[(int) $actions['u']][$k] = $actions['action'] == 'profile' ? $txt['who_viewprofile'] : $txt['who_profile'];
			}
			elseif (($actions['action'] == 'post' || $actions['action'] == 'post2') && empty($actions['topic']) && isset($actions['board']))
			{
				$data[$k] = $txt['who_hidden'];
				$board_ids[(int) $actions['board']][$k] = isset($actions['poll']) ? $txt['who_poll'] : $txt['who_post'];
			}
			// A subaction anyone can view... if the language string is there, show it.
			elseif (isset($actions['sa']) && isset($txt['whoall_' . $actions['action'] . '_' . $actions['sa']]))
				$data[$k] = $preferred_prefix && isset($txt[$preferred_prefix . $actions['action'] . '_' . $actions['sa']]) ? $txt[$preferred_prefix . $actions['action'] . '_' . $actions['sa']] : $txt['whoall_' . $actions['action'] . '_' . $actions['sa']];
			// An action any old fellow can look at. (if ['whoall_' . $action] exists, we know everyone can see it.)
			elseif (isset($txt['whoall_' . $actions['action']]))
				$data[$k] = $preferred_prefix && isset($txt[$preferred_prefix . $actions['action']]) ? $txt[$preferred_prefix . $actions['action']] : $txt['whoall_' . $actions['action']];
			// Viewable if and only if they can see the board...
			elseif (isset($txt['whotopic_' . $actions['action']]))
			{
				// Find out what topic they are accessing.
				$topic = (int) (isset($actions['topic']) ? $actions['topic'] : (isset($actions['from']) ? $actions['from'] : 0));

				$data[$k] = $txt['who_hidden'];
				$topic_ids[$topic][$k] = $txt['whotopic_' . $actions['action']];
			}
			elseif (isset($txt['whopost_' . $actions['action']]))
			{
				// Find out what message they are accessing.
				$msgid = (int) (isset($actions['msg']) ? $actions['msg'] : (isset($actions['quote']) ? $actions['quote'] : 0));

				$result = $db->query('', '
					SELECT m.id_topic, m.subject
					FROM {db_prefix}messages AS m
						INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
						INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic' . ($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') . ')
					WHERE m.id_msg = {int:id_msg}
						AND {query_see_board}' . ($modSettings['postmod_active'] ? '
						AND m.approved = {int:is_approved}' : '') . '
					LIMIT 1',
					array(
						'is_approved' => 1,
						'id_msg' => $msgid,
					)
				);
				list ($id_topic, $subject) = $db->fetch_row($result);
				$data[$k] = sprintf($txt['whopost_' . $actions['action']], $id_topic, $subject);
				$db->free_result($result);

				if (empty($id_topic))
					$data[$k] = $txt['who_hidden'];
			}
			// Viewable only by administrators.. (if it starts with whoadmin, it's admin only!)
			elseif (allowedTo('moderate_forum') && isset($txt['whoadmin_' . $actions['action']]))
				$data[$k] = $txt['whoadmin_' . $actions['action']];
			// Viewable by permission level.
			elseif (isset($allowedActions[$actions['action']]))
			{
				if (allowedTo($allowedActions[$actions['action']]))
					$data[$k] = $txt['whoallow_' . $actions['action']];
				elseif (in_array('moderate_forum', $allowedActions[$actions['action']]))
					$data[$k] = $txt['who_moderate'];
				elseif (in_array('admin_forum', $allowedActions[$actions['action']]))
					$data[$k] = $txt['who_admin'];
				else
					$data[$k] = $txt['who_hidden'];
			}
			elseif (!empty($actions['action']))
				$data[$k] = $txt['who_generic'] . ' ' . $actions['action'];
			else
				$data[$k] = $txt['who_unknown'];
		}

		// Maybe the action is integrated into another system?
		if (count($integrate_actions = call_integration_hook('integrate_whos_online', array($actions))) > 0)
		{
			foreach ($integrate_actions as $integrate_action)
			{
				if (!empty($integrate_action))
				{
					$data[$k] = $integrate_action;
					break;
				}
			}
		}
	}

	// Load topic names.
	if (!empty($topic_ids))
	{
		require_once(SUBSDIR . '/Topic.subs.php');
		$topics_data = topicsList(array_keys($topic_ids));

		foreach ($topics_data as $topic)
		{
			// Show the topic's subject for each of the members looking at this...
			foreach ($topic_ids[$topic['id_topic']] as $k => $session_text)
				$data[$k] = sprintf($session_text, $topic['id_topic'], $topic['subject']);
		}
	}

	// Load board names.
	if (!empty($board_ids))
	{
		require_once(SUBSDIR . '/Boards.subs.php');
		$boards_list = getBoardList(array('use_permissions' => true, 'included_boards' => array_keys($board_ids)), true);
		foreach ($boards_list as $board)
		{
			// Put the board name into the string for each member...
			foreach ($board_ids[$board['id_board']] as $k => $session_text)
				$data[$k] = sprintf($session_text, $board['id_board'], $board['board_name']);
		}
	}

	// Load member names for the profile.
	if (!empty($profile_ids) && (allowedTo('profile_view_any') || allowedTo('profile_view_own')))
	{
		require_once(SUBSDIR . '/Members.subs.php');
		$result = getBasicMemberData(array_keys($profile_ids));
		foreach ($result as $row)
		{
			// If they aren't allowed to view this person's profile, skip it.
			if (!allowedTo('profile_view_any') && $user_info['id'] != $row['id_member'])
				continue;

			// Set their action on each - session/text to sprintf.
			foreach ($profile_ids[$row['id_member']] as $k => $session_text)
				$data[$k] = sprintf($session_text, $row['id_member'], $row['real_name']);
		}
	}

	if (!is_array($urls))
		return isset($data[0]) ? $data[0] : false;
	else
		return $data;
}

/**
 * Prepare credits for display.
 * This is a helper function, used by admin panel for credits and support page, and by the credits page.
 */
function prepareCreditsData()
{
	global $context, $modSettings, $forum_copyright, $forum_version, $txt;

	// Don't blink. Don't even blink. Blink and you're dead.
	$context['credits'] = array(
		array(
			'pretext' => $txt['credits_intro'],
			'title' => $txt['credits_team'],
			'groups' => array(
				array(
					'title' => $txt['credits_groups_dev'],
					'members' => array(
						'Add this at some point',
					),
				),
			),
		),
	);

	// Give credit to any graphic library's, software library's, plugins etc
	$context['credits_software_graphics'] = array(
		'graphics' => array(
			'<a href="http://p.yusukekamiyamane.com/">Fugue Icons</a> | &copy; 2012 Yusuke Kamiyamane | These icons are licensed under a Creative Commons Attribution 3.0 License',
			'<a href="http://www.oxygen-icons.org/">Oxygen Icons</a> | These icons are licensed under <a href="http://creativecommons.org/licenses/by-sa/3.0/">CC BY-SA 3.0</a>',
		),
		'fonts' => array(
			'<a href="http://openfontlibrary.org/en/font/dotrice">Dotrice</a> | &copy; 2010 <a href="http://hisdeedsaredust.com/">Paul Flo Williams</a> | This font is licensed under the SIL Open Font License, Version 1.1',
			'<a href="http://openfontlibrary.org/en/font/klaudia-and-berenika">Berenika</a> | &copy; 2011 wmk69 | This font is licensed under the SIL Open Font License, Version 1.1',
			'<a href="http://openfontlibrary.org/en/font/vshexagonica-v1-0-1">vSHexagonica</a> | &copy; 2012 T.B. von Strong | This font is licensed under the SIL Open Font License, Version 1.1',
			'<a href="http://openfontlibrary.org/en/font/press-start-2p">Press Start 2P</a> | &copy; 2012 Cody "CodeMan38" Boisclair | This font is licensed under the SIL Open Font License, Version 1.1',
			'<a href="http://openfontlibrary.org/en/font/architect-s-daughter">Architect\'s Daughter</a> | &copy; 2010 <a href="http://kimberlygeswein.com/">Kimberly Geswein</a> | This font is licensed under the SIL Open Font License, Version 1.1',
			'<a href="http://openfontlibrary.org/en/font/vds">VDS</a> | &copy; 2012 <a href="http://www.wix.com/artmake1/artmaker">artmaker</a> | This font is licensed under the SIL Open Font License, Version 1.1',
		),
		'software' => array(
			'<a href="http://www.simplemachines.org/">Simple Machines</a> | &copy; Simple Machines | Licensed under <a href="http://www.simplemachines.org/about/smf/license.php">The BSD License</a>',
			'<a href="http://jquery.org/">JQuery</a> | &copy; John Resig | Licensed under <a href="http://github.com/jquery/jquery/blob/master/MIT-LICENSE.txt">The MIT License (MIT)</a>',
			'<a href="http://cherne.net/brian/resources/jquery.hoverIntent.html">hoverIntent</a> | &copy; Brian Cherne | Licensed under <a href="http://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
			'<a href="http://users.tpg.com.au/j_birch/plugins/superfish/">Superfish</a> | &copy; Joel Birch | Licensed under <a href="http://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
			'<a href="http://www.sceditor.com/">SCEditor</a> | &copy; Sam Clarke | Licensed under <a href="http://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
			'<a href="http://wayfarerweb.com/jquery/plugins/animadrag/">animaDrag</a> | &copy; Abel Mohler | Licensed under <a href="http://en.wikipedia.org/wiki/MIT_License">The MIT License (MIT)</a>',
		),
	);

	require_once(SUBSDIR . '/Who.subs.php');

	// Add-ons authors: to add credits, the simpler and better way is to add in your package.xml the <credits> <license> tags.
	// Support for addons that use the <credits> tag via the package manager
	$context['credits_addons'] = addonsCredits();

	// An alternative for add-ons credits is to use a hook.
	call_integration_hook('integrate_credits');

	// Copyright information
	$context['copyrights']['elkarte'] = '&copy; 2013 ElkArte contributors';
}