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
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * View a summary.
 */
function action_summary()
{
	global $context, $memberContext, $txt, $modSettings, $user_info, $user_profile;
	global $scripturl, $settings;

	$db = database();

	$memID = currentMemberID();

	// Attempt to load the member's profile data.
	if (!loadMemberContext($memID) || !isset($memberContext[$memID]))
		fatal_lang_error('not_a_user', false);

	loadTemplate('ProfileInfo');

	// Set up the stuff and load the user.
	$context += array(
		'page_title' => sprintf($txt['profile_of_username'], $memberContext[$memID]['name']),
		'can_send_pm' => allowedTo('pm_send'),
		'can_send_email' => allowedTo('send_email_to_members'),
		'can_have_buddy' => allowedTo('profile_identity_own') && !empty($modSettings['enable_buddylist']),
		'can_issue_warning' => in_array('w', $context['admin_features']) && allowedTo('issue_warning') && !empty($modSettings['warning_enable']),
	);
	$context['member'] = &$memberContext[$memID];
	$context['can_view_warning'] = in_array('w', $context['admin_features']) && (allowedTo('issue_warning') && !$context['user']['is_owner']) || (!empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $context['user']['is_owner']));

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl . '?action=profile;u=' . $memID;

	// Are there things we don't show?
	$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

	// Menu tab
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['summary'],
		'icon' => 'profile_hd.png'
	);

	// See if they have broken any warning levels...
	if (!empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $context['member']['warning'])
		$context['warning_status'] = $txt['profile_warning_is_muted'];
	elseif (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $context['member']['warning'])
		$context['warning_status'] = $txt['profile_warning_is_moderation'];
	elseif (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $context['member']['warning'])
		$context['warning_status'] = $txt['profile_warning_is_watch'];

	// They haven't even been registered for a full day!?
	$days_registered = (int) ((time() - $user_profile[$memID]['date_registered']) / (3600 * 24));
	if (empty($user_profile[$memID]['date_registered']) || $days_registered < 1)
		$context['member']['posts_per_day'] = $txt['not_applicable'];
	else
		$context['member']['posts_per_day'] = comma_format($context['member']['real_posts'] / $days_registered, 3);

	// Set the age...
	if (empty($context['member']['birth_date']))
	{
		$context['member'] += array(
			'age' => $txt['not_applicable'],
			'today_is_birthday' => false
		);
	}
	else
	{
		list ($birth_year, $birth_month, $birth_day) = sscanf($context['member']['birth_date'], '%d-%d-%d');
		$datearray = getdate(forum_time());
		$context['member'] += array(
			'age' => $birth_year <= 4 ? $txt['not_applicable'] : $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1),
			'today_is_birthday' => $datearray['mon'] == $birth_month && $datearray['mday'] == $birth_day
		);
	}

	if (allowedTo('moderate_forum'))
	{
		// Make sure it's a valid ip address; otherwise, don't bother...
		if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $memberContext[$memID]['ip']) == 1 && empty($modSettings['disableHostnameLookup']))
			$context['member']['hostname'] = host_from_ip($memberContext[$memID]['ip']);
		else
			$context['member']['hostname'] = '';

		$context['can_see_ip'] = true;
	}
	else
		$context['can_see_ip'] = false;

	if (!empty($modSettings['who_enabled']))
	{
		include_once(CONTROLLERDIR . '/Who.controller.php');
		$action = determineActions($user_profile[$memID]['url']);

		if ($action !== false)
			$context['member']['action'] = $action;
	}

	// If the user is awaiting activation, and the viewer has permission - setup some activation context messages.
	if ($context['member']['is_activated'] % 10 != 1 && allowedTo('moderate_forum'))
	{
		$context['activate_type'] = $context['member']['is_activated'];
		// What should the link text be?
		$context['activate_link_text'] = in_array($context['member']['is_activated'], array(3, 4, 5, 13, 14, 15)) ? $txt['account_approve'] : $txt['account_activate'];

		// Should we show a custom message?
		$context['activate_message'] = isset($txt['account_activate_method_' . $context['member']['is_activated'] % 10]) ? $txt['account_activate_method_' . $context['member']['is_activated'] % 10] : $txt['account_not_activated'];
	}

	// Is the signature even enabled on this forum?
	$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;

	// How about, are they banned?
	$context['member']['bans'] = array();
	if (allowedTo('moderate_forum'))
	{
		// Can they edit the ban?
		$context['can_edit_ban'] = allowedTo('manage_bans');

		$ban_query = array();
		$ban_query_vars = array(
			'time' => time(),
		);
		$ban_query[] = 'id_member = ' . $context['member']['id'];
		$ban_query[] = constructBanQueryIP($memberContext[$memID]['ip']);

		// Do we have a hostname already?
		if (!empty($context['member']['hostname']))
		{
			$ban_query[] = '({string:hostname} LIKE hostname)';
			$ban_query_vars['hostname'] = $context['member']['hostname'];
		}

		// Check their email as well...
		if (strlen($context['member']['email']) != 0)
		{
			$ban_query[] = '({string:email} LIKE bi.email_address)';
			$ban_query_vars['email'] = $context['member']['email'];
		}

		// So... are they banned?  Dying to know!
		$request = $db->query('', '
			SELECT bg.id_ban_group, bg.name, bg.cannot_access, bg.cannot_post, bg.cannot_register,
				bg.cannot_login, bg.reason
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND (bg.expire_time IS NULL OR bg.expire_time > {int:time}))
			WHERE (' . implode(' OR ', $ban_query) . ')',
			$ban_query_vars
		);
		while ($row = $db->fetch_assoc($request))
		{
			// Work out what restrictions we actually have.
			$ban_restrictions = array();
			foreach (array('access', 'register', 'login', 'post') as $type)
				if ($row['cannot_' . $type])
					$ban_restrictions[] = $txt['ban_type_' . $type];

			// No actual ban in place?
			if (empty($ban_restrictions))
				continue;

			// Prepare the link for context.
			$ban_explanation = sprintf($txt['user_cannot_due_to'], implode(', ', $ban_restrictions), '<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $row['id_ban_group'] . '">' . $row['name'] . '</a>');

			$context['member']['bans'][$row['id_ban_group']] = array(
				'reason' => empty($row['reason']) ? '' : '<br /><br /><strong>' . $txt['ban_reason'] . ':</strong> ' . $row['reason'],
				'cannot' => array(
					'access' => !empty($row['cannot_access']),
					'register' => !empty($row['cannot_register']),
					'post' => !empty($row['cannot_post']),
					'login' => !empty($row['cannot_login']),
				),
				'explanation' => $ban_explanation,
			);
		}
		$db->free_result($request);
	}

	// Load up the most recent attachments for this user for use in profile views etc.
	$context['thumbs'] = array();
	if (!empty($modSettings['attachmentEnable']) && allowedTo('view_attachments'))
	{
		$limit = 8;
		$boardsAllowed = boardsAllowedTo('view_attachments');
		if (empty($boardsAllowed))
			$boardsAllowed = array(-1);
		$attachments = list_getAttachments(0, $limit, 'm.poster_time DESC', $boardsAllowed , $context['member']['id']);

		// load them in to $context for use in the template
		$i = 0;

		// @todo keep or loose the mime thumbs ... useful at all?
		$mime_images_url = $settings['default_images_url'] . '/mime_images/';
		$mime_path = $settings['default_theme_dir'] . '/images/mime_images/';

		for ($i = 0, $count = count($attachments); $i < $count; $i++)
		{
			$context['thumbs'][$i] = array(
				'url' => $scripturl . '?action=dlattach;topic=' . $attachments[$i]['topic'] . '.0;attach=' . $attachments[$i]['id'],
				'img' => '',
			);

			// Show a thumbnail image well?
			if ($attachments[$i]['is_image'] && !empty($modSettings['attachmentShowImages']) && !empty($modSettings['attachmentThumbnails']))
			{
				if (!empty($attachments[$i]['id_thumb']))
					$context['thumbs'][$i]['img'] = '<img src="' . $scripturl . '?action=dlattach;topic=' . $attachments[$i]['topic'] . '.0;attach=' . $attachments[$i]['id_thumb'] . ';image" title="' . $attachments[$i]['subject'] . '" alt="" />';
				else
				{
					// no thumbnail available ... use html instead
					if (!empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight']))
					{
						if ($attachments[$i]['width'] > $modSettings['attachmentThumbWidth'] || $attachments[$i]['height'] > $modSettings['attachmentThumbHeight'])
							$context['thumbs'][$i]['img'] = '<img src="' . $scripturl . '?action=dlattach;topic=' . $attachments[$i]['topic'] . '.0;attach=' . $attachments[$i]['id'] . '" title="' . $attachments[$i]['subject'] . '" alt="" width="' . $modSettings['attachmentThumbWidth']. '" height="' . $modSettings['attachmentThumbHeight'] . '" />';
						else
							$context['thumbs'][$i]['img'] = '<img src="' . $scripturl . '?action=dlattach;topic=' . $attachments[$i]['topic'] . '.0;attach=' . $attachments[$i]['id'] . '" title="' . $attachments[$i]['subject'] . '" alt="" width="' . $attachments[$i]['width'] . '" height="' . $attachments[$i]['height'] . '" />';
					}
				}
			}
			// Not an image so lets set a mime thumbnail based off the filetype
			else
			{
				if ((!empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight'])) && (128 > $modSettings['attachmentThumbWidth'] || 128 > $modSettings['attachmentThumbHeight']))
					$context['thumbs'][$i]['img'] = '<img src="' . $mime_images_url . (!file_exists($mime_path . $attachments[$i]['fileext'] . '.png') ? 'default' : $attachments[$i]['fileext']) . '.png" title="' . $attachments[$i]['subject'] . '" alt="" width="' . $modSettings['attachmentThumbWidth']. '" height="' . $modSettings['attachmentThumbHeight']. '" />';
				else
					$context['thumbs'][$i]['img'] = '<img src="' . $mime_images_url . (!file_exists($mime_path . $attachments[$i]['fileext'] . '.png') ? 'default' : $attachments[$i]['fileext']) . '.png" title="' . $attachments[$i]['subject'] . '" alt="" />';
			}
		}
	}

	// Would you be mine? Could you be mine? Be my buddy :D
	if (!empty($modSettings['enable_buddylist']) && $context['user']['is_owner'] && !empty($user_info['buddies']))
	{
		$context['buddies'] = array();
		loadMemberData($user_info['buddies'], false, 'profile');

		// Get the info for this buddy
		foreach ($user_info['buddies'] as $buddy)
		{
			loadMemberContext($buddy);
			$context['buddies'][$buddy] = $memberContext[$buddy];
		}
	}

	// To finish this off, custom profile fields.
	require_once(SUBSDIR . '/Profile.subs.php');
	loadCustomFields($memID);
}

/**
 * Show all posts by the current user
 * @todo This function needs to be split up properly.
 *
 */
function action_showPosts()
{
	global $txt, $user_info, $scripturl, $modSettings;
	global $context, $user_profile, $board;

	$db = database();

	$memID = currentMemberID();

	// Some initial context.
	$context['start'] = (int) $_REQUEST['start'];
	$context['current_member'] = $memID;

	loadTemplate('ProfileInfo');

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['showPosts'],
		'description' => $txt['showPosts_help'],
		'icon' => 'profile_hd.png',
		'tabs' => array(
			'messages' => array(
			),
			'topics' => array(
			),
			'disregardedtopics' => array(
			),
			'attach' => array(
			),
		),
	);

	// Set the page title
	$context['page_title'] = $txt['showPosts'] . ' - ' . $user_profile[$memID]['real_name'];

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($modSettings['loadavg_show_posts']) && $context['load_average'] >= $modSettings['loadavg_show_posts'])
		fatal_lang_error('loadavg_show_posts_disabled', false);

	// If we're specifically dealing with attachments use that function!
	if (isset($_GET['sa']) && $_GET['sa'] == 'attach')
		return action_showAttachments($memID);
	// Instead, if we're dealing with disregarded topics (and the feature is enabled) use that other function.
	elseif (isset($_GET['sa']) && $_GET['sa'] == 'disregardedtopics' && $modSettings['enable_disregard'])
		return action_showDisregarded($memID);

	// Are we just viewing topics?
	$context['is_topics'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? true : false;

	// If just deleting a message, do it and then redirect back.
	if (isset($_GET['delete']) && !$context['is_topics'])
	{
		checkSession('get');

		// We need msg info for logging.
		require_once(SUBSDIR . '/Messages.subs.php');
		$info = getExistingMessage((int) $_GET['delete'], true);

		// Trying to remove a message that doesn't exist.
		if (empty($info))
			redirectexit('action=profile;u=' . $memID . ';area=showposts;start=' . $_GET['start']);

		// We can be lazy, since removeMessage() will check the permissions for us.
		require_once(SUBSDIR . '/Messages.subs.php');
		removeMessage((int) $_GET['delete']);

		// Add it to the mod log.
		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info['id_member'] != $user_info['id']))
			logAction('delete', array('topic' => $info['id_topic'], 'subject' => $info['subject'], 'member' => $info['id_member'], 'board' => $info['id_board']));

		// Back to... where we are now ;).
		redirectexit('action=profile;u=' . $memID . ';area=showposts;start=' . $_GET['start']);
	}

	// Default to 10.
	if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		$_REQUEST['viewscount'] = '10';

	if ($context['is_topics'])
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})') . '
			WHERE t.id_member_started = {int:current_member}' . (!empty($board) ? '
				AND t.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
				AND t.approved = {int:is_approved}'),
			array(
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => $board,
			)
		);
	else
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}messages AS m' . ($user_info['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
				AND m.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
				AND m.approved = {int:is_approved}'),
			array(
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => $board,
			)
		);
	list ($msgCount) = $db->fetch_row($request);
	$db->free_result($request);

	$request = $db->query('', '
		SELECT MIN(id_msg), MAX(id_msg)
		FROM {db_prefix}messages AS m
		WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
			AND m.id_board = {int:board}' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($min_msg_member, $max_msg_member) = $db->fetch_row($request);
	$db->free_result($request);

	$reverse = false;
	$range_limit = '';
	$maxIndex = (int) $modSettings['defaultMaxMessages'];

	// Make sure the starting place makes sense and construct our friend the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=showposts' . ($context['is_topics'] ? ';sa=topics' : '') . (!empty($board) ? ';board=' . $board : ''), $context['start'], $msgCount, $maxIndex);
	$context['current_page'] = $context['start'] / $maxIndex;

	// Reverse the query if we're past 50% of the pages for better performance.
	$start = $context['start'];
	$reverse = $_REQUEST['start'] > $msgCount / 2;
	if ($reverse)
	{
		$maxIndex = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $modSettings['defaultMaxMessages'];
		$start = $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $modSettings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $modSettings['defaultMaxMessages'];
	}

	// Guess the range of messages to be shown.
	if ($msgCount > 1000)
	{
		$margin = floor(($max_msg_member - $min_msg_member) * (($start + $modSettings['defaultMaxMessages']) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));
		// Make a bigger margin for topics only.
		if ($context['is_topics'])
		{
			$margin *= 5;
			$range_limit = $reverse ? 't.id_first_msg < ' . ($min_msg_member + $margin) : 't.id_first_msg > ' . ($max_msg_member - $margin);
		}
		else
			$range_limit = $reverse ? 'm.id_msg < ' . ($min_msg_member + $margin) : 'm.id_msg > ' . ($max_msg_member - $margin);
	}

	// Find this user's posts.  The left join on categories somehow makes this faster, weird as it looks.
	$looped = false;
	while (true)
	{
		if ($context['is_topics'])
		{
			$request = $db->query('', '
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, t.id_member_started, t.id_first_msg, t.id_last_msg,
					t.approved, m.body, m.smileys_enabled, m.subject, m.poster_time, m.id_topic, m.id_msg
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE t.id_member_started = {int:current_member}' . (!empty($board) ? '
					AND t.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY t.id_first_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT ' . $start . ', ' . $maxIndex,
				array(
					'current_member' => $memID,
					'is_approved' => 1,
					'board' => $board,
				)
			);
		}
		else
		{
			$request = $db->query('', '
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, m.id_topic, m.id_msg,
					t.id_member_started, t.id_first_msg, t.id_last_msg, m.body, m.smileys_enabled,
					m.subject, m.poster_time, m.approved
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
					AND b.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY m.id_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT ' . $start . ', ' . $maxIndex,
				array(
					'current_member' => $memID,
					'is_approved' => 1,
					'board' => $board,
				)
			);
		}

		// Make sure we quit this loop.
		if ($db->num_rows($request) === $maxIndex || $looped)
			break;
		$looped = true;
		$range_limit = '';
	}

	// Start counting at the number of the first message displayed.
	$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
	$context['posts'] = array();
	$board_ids = array('own' => array(), 'any' => array());
	while ($row = $db->fetch_assoc($request))
	{
		// Censor....
		censorText($row['body']);
		censorText($row['subject']);

		// Do the code.
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And the array...
		$context['posts'][$counter += $reverse ? -1 : 1] = array(
			'body' => $row['body'],
			'counter' => $counter,
			'alternate' => $counter % 2,
			'category' => array(
				'name' => $row['cname'],
				'id' => $row['id_cat']
			),
			'board' => array(
				'name' => $row['bname'],
				'id' => $row['id_board']
			),
			'topic' => $row['id_topic'],
			'subject' => $row['subject'],
			'start' => 'msg' . $row['id_msg'],
			'time' => relativeTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'id' => $row['id_msg'],
			'can_reply' => false,
			'can_mark_notify' => false,
			'can_delete' => false,
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()),
			'approved' => $row['approved'],
		);

		if ($user_info['id'] == $row['id_member_started'])
			$board_ids['own'][$row['id_board']][] = $counter;
		$board_ids['any'][$row['id_board']][] = $counter;
	}
	$db->free_result($request);

	// All posts were retrieved in reverse order, get them right again.
	if ($reverse)
		$context['posts'] = array_reverse($context['posts'], true);

	// These are all the permissions that are different from board to board..
	if ($context['is_topics'])
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
				'mark_any_notify' => 'can_mark_notify',
			)
		);
	else
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
				'delete_own' => 'can_delete',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
				'mark_any_notify' => 'can_mark_notify',
				'delete_any' => 'can_delete',
			)
		);

	// For every permission in the own/any lists...
	foreach ($permissions as $type => $list)
	{
		foreach ($list as $permission => $allowed)
		{
			// Get the boards they can do this on...
			$boards = boardsAllowedTo($permission);

			// Hmm, they can do it on all boards, can they?
			if (!empty($boards) && $boards[0] == 0)
				$boards = array_keys($board_ids[$type]);

			// Now go through each board they can do the permission on.
			foreach ($boards as $board_id)
			{
				// There aren't any posts displayed from this board.
				if (!isset($board_ids[$type][$board_id]))
					continue;

				// Set the permission to true ;).
				foreach ($board_ids[$type][$board_id] as $counter)
					$context['posts'][$counter][$allowed] = true;
			}
		}
	}

	// Clean up after posts that cannot be deleted and quoted.
	$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
	foreach ($context['posts'] as $counter => $dummy)
	{
		$context['posts'][$counter]['can_delete'] &= $context['posts'][$counter]['delete_possible'];
		$context['posts'][$counter]['can_quote'] = $context['posts'][$counter]['can_reply'] && $quote_enabled;
	}
}

/**
 * Show all the attachments of a user.
 *
 */
function action_showAttachments()
{
	global $txt, $user_info, $scripturl, $modSettings, $board;
	global $context, $user_profile;

	$db = database();

	// OBEY permissions!
	$boardsAllowed = boardsAllowedTo('view_attachments');

	// Make sure we can't actually see anything...
	if (empty($boardsAllowed))
		$boardsAllowed = array(-1);

	$memID = currentMemberID();

	require_once(SUBSDIR . '/List.subs.php');

	// This is all the information required to list attachments.
	$listOptions = array(
		'id' => 'attachments',
		'width' => '100%',
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['show_attachments_none'],
		'base_href' => $scripturl . '?action=profile;area=showposts;sa=attach;u=' . $memID,
		'default_sort_col' => 'filename',
		'get_items' => array(
			'function' => 'list_getAttachments',
			'params' => array(
				$boardsAllowed,
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getNumAttachments',
			'params' => array(
				$boardsAllowed,
				$memID,
			),
		),
		'data_check' => array(
			'class' => create_function('$data', '
				return $data[\'approved\'] ? \'\' : \'approvebg\';
			')
		),
		'columns' => array(
			'filename' => array(
				'header' => array(
					'value' => $txt['show_attach_downloads'],
					'class' => 'lefttext',
					'style' => 'width: 25%;',
				),
				'data' => array(
					'db' => 'filename',
				),
				'sort' => array(
					'default' => 'a.filename',
					'reverse' => 'a.filename DESC',
				),
			),
			'downloads' => array(
				'header' => array(
					'value' => $txt['show_attach_downloads'],
					'style' => 'width: 12%;',
				),
				'data' => array(
					'db' => 'downloads',
					'comma_format' => true,
				),
				'sort' => array(
					'default' => 'a.downloads',
					'reverse' => 'a.downloads DESC',
				),
			),
			'subject' => array(
				'header' => array(
					'value' => $txt['message'],
					'class' => 'lefttext',
					'style' => 'width: 30%;',
				),
				'data' => array(
					'db' => 'subject',
				),
				'sort' => array(
					'default' => 'm.subject',
					'reverse' => 'm.subject DESC',
				),
			),
			'posted' => array(
				'header' => array(
					'value' => $txt['show_attach_posted'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'posted',
					'timeformat' => true,
				),
				'sort' => array(
					'default' => 'm.poster_time',
					'reverse' => 'm.poster_time DESC',
				),
			),
		),
	);

	// Create the request list.
	createList($listOptions);
}

/**
 * Get a list of attachments for this user
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param array $boardsAllowed
 * @param ing $memID
 * @return array
 */
function list_getAttachments($start, $items_per_page, $sort, $boardsAllowed, $memID)
{
	global $board, $modSettings, $context;

	$db = database();

	// Retrieve some attachments.
	$request = $db->query('', '
		SELECT a.id_attach, a.id_msg, a.filename, a.downloads, a.approved, a.fileext, a.width, a.height, ' .
			(empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : ' IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height, ') . '
			m.id_msg, m.id_topic, m.id_board, m.poster_time, m.subject, b.name
		FROM {db_prefix}attachments AS a' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : '
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)') . '
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE a.attachment_type = {int:attachment_type}
			AND a.id_msg != {int:no_message}
			AND m.id_member = {int:current_member}' . (!empty($board) ? '
			AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_list})' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}') . '
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'boards_list' => $boardsAllowed,
			'attachment_type' => 0,
			'no_message' => 0,
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	);
	$attachments = array();
	while ($row = $db->fetch_assoc($request))
		$attachments[] = array(
			'id' => $row['id_attach'],
			'filename' => $row['filename'],
			'fileext' => $row['fileext'],
			'width' => $row['width'],
			'height' => $row['height'],
			'downloads' => $row['downloads'],
			'is_image' => !empty($row['width']) && !empty($row['height']) && !empty($modSettings['attachmentShowImages']),
			'id_thumb' => $row['id_thumb'],
			'subject' => censorText($row['subject']),
			'posted' => $row['poster_time'],
			'msg' => $row['id_msg'],
			'topic' => $row['id_topic'],
			'board' => $row['id_board'],
			'board_name' => $row['name'],
			'approved' => $row['approved'],
		);

	$db->free_result($request);

	return $attachments;
}

/**
 * Gets the total number of attachments for the user
 *
 * @param type $boardsAllowed
 * @param type $memID
 * @return type
 */
function list_getNumAttachments($boardsAllowed, $memID)
{
	global $board, $modSettings, $context;

	$db = database();

	// Get the total number of attachments they have posted.
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE a.attachment_type = {int:attachment_type}
			AND a.id_msg != {int:no_message}
			AND m.id_member = {int:current_member}' . (!empty($board) ? '
			AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_list})' : '') . (!$modSettings['postmod_active'] || $context['user']['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'boards_list' => $boardsAllowed,
			'attachment_type' => 0,
			'no_message' => 0,
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($attachCount) = $db->fetch_row($request);
	$db->free_result($request);

	return $attachCount;
}

/**
 * Show all the disregarded topics.
 *
 */
function action_showDisregarded()
{
	global $txt, $user_info, $scripturl, $modSettings, $board, $context;

	$db = database();

	$memID = currentMemberID();

	// Only the owner can see the list (if the function is enabled of course)
	if ($user_info['id'] != $memID || !$modSettings['enable_disregard'])
		return;

	require_once(SUBSDIR . '/List.subs.php');

	// And here they are: the topics you don't like
	$listOptions = array(
		'id' => 'disregarded_topics',
		'width' => '100%',
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['disregarded_topics_none'],
		'base_href' => $scripturl . '?action=profile;area=showposts;sa=disregardedtopics;u=' . $memID,
		'default_sort_col' => 'started_on',
		'get_items' => array(
			'function' => 'list_getDisregarded',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getNumDisregarded',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => $txt['subject'],
					'class' => 'lefttext',
					'style' => 'width: 30%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?topic=%1$d.0">%2$s</a>',
						'params' => array(
							'id_topic' => false,
							'subject' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'm.subject',
					'reverse' => 'm.subject DESC',
				),
			),
			'started_by' => array(
				'header' => array(
					'value' => $txt['started_by'],
					'style' => 'width: 15%;',
				),
				'data' => array(
					'db' => 'started_by',
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'started_on' => array(
				'header' => array(
					'value' => $txt['on'],
					'class' => 'lefttext',
					'style' => 'width: 20%;',
				),
				'data' => array(
					'db' => 'started_on',
					'timeformat' => true,
				),
				'sort' => array(
					'default' => 'm.poster_time',
					'reverse' => 'm.poster_time DESC',
				),
			),
			'last_post_by' => array(
				'header' => array(
					'value' => $txt['last_post'],
					'style' => 'width: 15%;',
				),
				'data' => array(
					'db' => 'last_post_by',
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'last_post_on' => array(
				'header' => array(
					'value' => $txt['on'],
					'class' => 'lefttext',
					'style' => 'width: 20%;',
				),
				'data' => array(
					'db' => 'last_post_on',
					'timeformat' => true,
				),
				'sort' => array(
					'default' => 'm.poster_time',
					'reverse' => 'm.poster_time DESC',
				),
			),
		),
	);

	// Create the request list.
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'disregarded_topics';
}

/**
 * Get the relevant topics in the disregarded list
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param int $memID
 */
function list_getDisregarded($start, $items_per_page, $sort, $memID)
{
	$db = database();

	// Get the list of topics we can see
	$request = $db->query('', '
		SELECT lt.id_topic
		FROM {db_prefix}log_topics as lt
			LEFT JOIN {db_prefix}topics as t ON (lt.id_topic = t.id_topic)
			LEFT JOIN {db_prefix}boards as b ON (t.id_board = b.id_board)
			LEFT JOIN {db_prefix}messages as m ON (t.id_first_msg = m.id_msg)' . (in_array($sort, array('mem.real_name', 'mem.real_name DESC', 'mem.poster_time', 'mem.poster_time DESC')) ? '
			LEFT JOIN {db_prefix}members as mem ON (m.id_member = mem.id_member)' : '') . '
		WHERE lt.id_member = {int:current_member}
			AND disregarded = 1
			AND {query_see_board}
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'current_member' => $memID,
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	);
	$topics = array();
	while ($row = $db->fetch_assoc($request))
		$topics[] = $row['id_topic'];
	$db->free_result($request);

	// Any topics found?
	$topicsInfo = array();
	if (!empty($topics))
	{
		$request = $db->query('', '
			SELECT mf.subject, mf.poster_time as started_on, IFNULL(memf.real_name, mf.poster_name) as started_by, ml.poster_time as last_post_on, IFNULL(meml.real_name, ml.poster_name) as last_post_by, t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
			WHERE t.id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$topicsInfo[] = $row;
		$db->free_result($request);
	}

	return $topicsInfo;
}

/**
 * Count the number of topics in the disregarded list
 *
 * @param int $memID
 */
function list_getNumDisregarded($memID)
{
	global $user_info;

	$db = database();

	// Get the total number of attachments they have posted.
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_topics as lt
		LEFT JOIN {db_prefix}topics as t ON (lt.id_topic = t.id_topic)
		LEFT JOIN {db_prefix}boards as b ON (t.id_board = b.id_board)
		WHERE id_member = {int:current_member}
			AND disregarded = 1
			AND {query_see_board}',
		array(
			'current_member' => $memID,
		)
	);
	list ($disregardedCount) = $db->fetch_row($request);
	$db->free_result($request);

	return $disregardedCount;
}

/**
 * Gets the user stats for display
 *
 */
function action_statPanel()
{
	global $txt, $scripturl, $context, $user_profile, $user_info, $modSettings;

	$db = database();

	$memID = currentMemberID();

	$context['page_title'] = $txt['statPanel_showStats'] . ' ' . $user_profile[$memID]['real_name'];

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($modSettings['loadavg_userstats']) && $context['load_average'] >= $modSettings['loadavg_userstats'])
		fatal_lang_error('loadavg_userstats_disabled', false);

	loadTemplate('ProfileInfo');

	// General user statistics.
	$timeDays = floor($user_profile[$memID]['total_time_logged_in'] / 86400);
	$timeHours = floor(($user_profile[$memID]['total_time_logged_in'] % 86400) / 3600);
	$context['time_logged_in'] = ($timeDays > 0 ? $timeDays . $txt['totalTimeLogged2'] : '') . ($timeHours > 0 ? $timeHours . $txt['totalTimeLogged3'] : '') . floor(($user_profile[$memID]['total_time_logged_in'] % 3600) / 60) . $txt['totalTimeLogged4'];
	$context['num_posts'] = comma_format($user_profile[$memID]['posts']);

	// Menu tab
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['statPanel_generalStats'] . ' - ' . $context['member']['name'],
		'icon' => 'stats_info_hd.png'
	);

	// Number of topics started.
	$result = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics
		WHERE id_member_started = {int:current_member}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND id_board != {int:recycle_board}' : ''),
		array(
			'current_member' => $memID,
			'recycle_board' => $modSettings['recycle_board'],
		)
	);
	list ($context['num_topics']) = $db->fetch_row($result);
	$db->free_result($result);

	// Number polls started.
	$result = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics
		WHERE id_member_started = {int:current_member}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND id_board != {int:recycle_board}' : '') . '
			AND id_poll != {int:no_poll}',
		array(
			'current_member' => $memID,
			'recycle_board' => $modSettings['recycle_board'],
			'no_poll' => 0,
		)
	);
	list ($context['num_polls']) = $db->fetch_row($result);
	$db->free_result($result);

	// Number polls voted in.
	$result = $db->query('distinct_poll_votes', '
		SELECT COUNT(DISTINCT id_poll)
		FROM {db_prefix}log_polls
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $memID,
		)
	);
	list ($context['num_votes']) = $db->fetch_row($result);
	$db->free_result($result);

	// Format the numbers...
	$context['num_topics'] = comma_format($context['num_topics']);
	$context['num_polls'] = comma_format($context['num_polls']);
	$context['num_votes'] = comma_format($context['num_votes']);

	// Grab the board this member posted in most often.
	$result = $db->query('', '
		SELECT
			b.id_board, MAX(b.name) AS name, MAX(b.num_posts) AS num_posts, COUNT(*) AS message_count
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_member = {int:current_member}
			AND b.count_posts = {int:count_enabled}
			AND {query_see_board}
		GROUP BY b.id_board
		ORDER BY message_count DESC
		LIMIT 10',
		array(
			'current_member' => $memID,
			'count_enabled' => 0,
		)
	);
	$context['popular_boards'] = array();
	while ($row = $db->fetch_assoc($result))
	{
		$context['popular_boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'posts_percent' => $user_profile[$memID]['posts'] == 0 ? 0 : ($row['message_count'] * 100) / $user_profile[$memID]['posts'],
			'total_posts' => $row['num_posts'],
			'total_posts_member' => $user_profile[$memID]['posts'],
		);
	}
	$db->free_result($result);

	// Now get the 10 boards this user has most often participated in.
	$result = $db->query('profile_board_stats', '
		SELECT
			b.id_board, MAX(b.name) AS name, b.num_posts, COUNT(*) AS message_count,
			CASE WHEN COUNT(*) > MAX(b.num_posts) THEN 1 ELSE COUNT(*) / MAX(b.num_posts) END * 100 AS percentage
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_member = {int:current_member}
			AND {query_see_board}
		GROUP BY b.id_board, b.num_posts
		ORDER BY percentage DESC
		LIMIT 10',
		array(
			'current_member' => $memID,
		)
	);
	$context['board_activity'] = array();
	while ($row = $db->fetch_assoc($result))
	{
		$context['board_activity'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'percent' => comma_format((float) $row['percentage'], 2),
			'posts_percent' => (float) $row['percentage'],
			'total_posts' => $row['num_posts'],
		);
	}
	$db->free_result($result);

	// Posting activity by time.
	$result = $db->query('user_activity_by_time', '
		SELECT
			HOUR(FROM_UNIXTIME(poster_time + {int:time_offset})) AS hour,
			COUNT(*) AS post_count
		FROM {db_prefix}messages
		WHERE id_member = {int:current_member}' . ($modSettings['totalMessages'] > 100000 ? '
			AND id_topic > {int:top_ten_thousand_topics}' : '') . '
		GROUP BY hour',
		array(
			'current_member' => $memID,
			'top_ten_thousand_topics' => $modSettings['totalTopics'] - 10000,
			'time_offset' => (($user_info['time_offset'] + $modSettings['time_offset']) * 3600),
		)
	);
	$maxPosts = $realPosts = 0;
	$context['posts_by_time'] = array();
	while ($row = $db->fetch_assoc($result))
	{
		// Cast as an integer to remove the leading 0.
		$row['hour'] = (int) $row['hour'];

		$maxPosts = max($row['post_count'], $maxPosts);
		$realPosts += $row['post_count'];

		$context['posts_by_time'][$row['hour']] = array(
			'hour' => $row['hour'],
			'hour_format' => stripos($user_info['time_format'], '%p') === false ? $row['hour'] : date('g a', mktime($row['hour'])),
			'posts' => $row['post_count'],
			'posts_percent' => 0,
			'is_last' => $row['hour'] == 23,
		);
	}
	$db->free_result($result);

	if ($maxPosts > 0)
		for ($hour = 0; $hour < 24; $hour++)
		{
			if (!isset($context['posts_by_time'][$hour]))
				$context['posts_by_time'][$hour] = array(
					'hour' => $hour,
					'hour_format' => stripos($user_info['time_format'], '%p') === false ? $hour : date('g a', mktime($hour)),
					'posts' => 0,
					'posts_percent' => 0,
					'relative_percent' => 0,
					'is_last' => $hour == 23,
				);
			else
			{
				$context['posts_by_time'][$hour]['posts_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $realPosts);
				$context['posts_by_time'][$hour]['relative_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $maxPosts);
			}
		}

	// Put it in the right order.
	ksort($context['posts_by_time']);

	// Custom stats (just add a template_layer to add it to the template!)
 	call_integration_hook('integrate_profile_stats', array($memID));
}

/**
 * Show permissions for a user.
 *
 */
function action_showPermissions()
{
	global $scripturl, $txt, $board, $modSettings;
	global $user_profile, $context, $user_info;

	$db = database();

	// Verify if the user has sufficient permissions.
	isAllowedTo('manage_permissions');

	loadLanguage('ManagePermissions');
	loadLanguage('Admin');
	loadTemplate('ManageMembers');
	loadTemplate('ProfileInfo');

	// Load all the permission profiles.
	require_once(SUBSDIR . '/ManagePermissions.subs.php');
	loadPermissionProfiles();

	$memID = currentMemberID();

	$context['member']['id'] = $memID;
	$context['member']['name'] = $user_profile[$memID]['real_name'];

	$context['page_title'] = $txt['showPermissions'];
	$board = empty($board) ? 0 : (int) $board;
	$context['board'] = $board;

	// Determine which groups this user is in.
	if (empty($user_profile[$memID]['additional_groups']))
		$curGroups = array();
	else
		$curGroups = explode(',', $user_profile[$memID]['additional_groups']);
	$curGroups[] = $user_profile[$memID]['id_group'];
	$curGroups[] = $user_profile[$memID]['id_post_group'];

	// Load a list of boards for the jump box - except the defaults.
	$request = $db->query('order_by_board_order', '
		SELECT b.id_board, b.name, b.id_profile, b.member_groups, IFNULL(mods.id_member, 0) AS is_mod
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
		WHERE {query_see_board}',
		array(
			'current_member' => $memID,
		)
	);
	$context['boards'] = array();
	$context['no_access_boards'] = array();
	while ($row = $db->fetch_assoc($request))
	{
		if (count(array_intersect($curGroups, explode(',', $row['member_groups']))) === 0 && !$row['is_mod'])
			$context['no_access_boards'][] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'is_last' => false,
			);
		elseif ($row['id_profile'] != 1 || $row['is_mod'])
			$context['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'selected' => $board == $row['id_board'],
				'profile' => $row['id_profile'],
				'profile_name' => $context['profiles'][$row['id_profile']]['name'],
			);
	}
	$db->free_result($request);

	if (!empty($context['no_access_boards']))
		$context['no_access_boards'][count($context['no_access_boards']) - 1]['is_last'] = true;

	$context['member']['permissions'] = array(
		'general' => array(),
		'board' => array()
	);

	// If you're an admin we know you can do everything, we might as well leave.
	$context['member']['has_all_permissions'] = in_array(1, $curGroups);
	if ($context['member']['has_all_permissions'])
		return;

	$denied = array();

	// Get all general permissions.
	$result = $db->query('', '
		SELECT p.permission, p.add_deny, mg.group_name, p.id_group
		FROM {db_prefix}permissions AS p
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = p.id_group)
		WHERE p.id_group IN ({array_int:group_list})
		ORDER BY p.add_deny DESC, p.permission, mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'group_list' => $curGroups,
			'newbie_group' => 4,
		)
	);
	while ($row = $db->fetch_assoc($result))
	{
		// We don't know about this permission, it doesn't exist :P.
		if (!isset($txt['permissionname_' . $row['permission']]))
			continue;

		if (empty($row['add_deny']))
			$denied[] = $row['permission'];

		// Permissions that end with _own or _any consist of two parts.
		if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
			$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
		else
			$name = $txt['permissionname_' . $row['permission']];

		// Add this permission if it doesn't exist yet.
		if (!isset($context['member']['permissions']['general'][$row['permission']]))
			$context['member']['permissions']['general'][$row['permission']] = array(
				'id' => $row['permission'],
				'groups' => array(
					'allowed' => array(),
					'denied' => array()
				),
				'name' => $name,
				'is_denied' => false,
				'is_global' => true,
			);

		// Add the membergroup to either the denied or the allowed groups.
		$context['member']['permissions']['general'][$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['id_group'] == 0 ? $txt['membergroups_members'] : $row['group_name'];

		// Once denied is always denied.
		$context['member']['permissions']['general'][$row['permission']]['is_denied'] |= empty($row['add_deny']);
	}
	$db->free_result($result);

	$request = $db->query('', '
		SELECT
			bp.add_deny, bp.permission, bp.id_group, mg.group_name' . (empty($board) ? '' : ',
			b.id_profile, CASE WHEN mods.id_member IS NULL THEN 0 ELSE 1 END AS is_moderator') . '
		FROM {db_prefix}board_permissions AS bp' . (empty($board) ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = {int:current_board})
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})') . '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = bp.id_group)
		WHERE bp.id_profile = {raw:current_profile}
			AND bp.id_group IN ({array_int:group_list}' . (empty($board) ? ')' : ', {int:moderator_group})
			AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})'),
		array(
			'current_board' => $board,
			'group_list' => $curGroups,
			'current_member' => $memID,
			'current_profile' => empty($board) ? '1' : 'b.id_profile',
			'moderator_group' => 3,
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		// We don't know about this permission, it doesn't exist :P.
		if (!isset($txt['permissionname_' . $row['permission']]))
			continue;

		// The name of the permission using the format 'permission name' - 'own/any topic/event/etc.'.
		if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
			$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
		else
			$name = $txt['permissionname_' . $row['permission']];

		// Create the structure for this permission.
		if (!isset($context['member']['permissions']['board'][$row['permission']]))
			$context['member']['permissions']['board'][$row['permission']] = array(
				'id' => $row['permission'],
				'groups' => array(
					'allowed' => array(),
					'denied' => array()
				),
				'name' => $name,
				'is_denied' => false,
				'is_global' => empty($board),
			);

		$context['member']['permissions']['board'][$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][$row['id_group']] = $row['id_group'] == 0 ? $txt['membergroups_members'] : $row['group_name'];

		$context['member']['permissions']['board'][$row['permission']]['is_denied'] |= empty($row['add_deny']);
	}
	$db->free_result($request);
}

/**
 * View a members warnings?
 *
 */
function action_viewWarning()
{
	global $modSettings, $context, $txt, $scripturl;

	// Firstly, can we actually even be here?
	if (!allowedTo('issue_warning') && (empty($modSettings['warning_show']) || ($modSettings['warning_show'] == 1 && !$context['user']['is_owner'])))
		fatal_lang_error('no_access', false);

	loadTemplate('ProfileInfo');
	// We need this because of template_load_warning_variables
	loadTemplate('Profile');

	// Make sure things which are disabled stay disabled.
	$modSettings['warning_watch'] = !empty($modSettings['warning_watch']) ? $modSettings['warning_watch'] : 110;
	$modSettings['warning_moderate'] = !empty($modSettings['warning_moderate']) && !empty($modSettings['postmod_active']) ? $modSettings['warning_moderate'] : 110;
	$modSettings['warning_mute'] = !empty($modSettings['warning_mute']) ? $modSettings['warning_mute'] : 110;

	// Let's use a generic list to get all the current warnings
	// and use the issue warnings grab-a-granny thing.
	require_once(SUBSDIR . '/List.subs.php');

	$memID = currentMemberID();

	$listOptions = array(
		'id' => 'view_warnings',
		'title' => $txt['profile_viewwarning_previous_warnings'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['profile_viewwarning_no_warnings'],
		'base_href' => $scripturl . '?action=profile;area=viewwarning;sa=user;u=' . $memID,
		'default_sort_col' => 'log_time',
		'get_items' => array(
			'function' => 'list_getUserWarnings',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getUserWarningCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'log_time' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_time'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'lc.log_time DESC',
					'reverse' => 'lc.log_time',
				),
			),
			'reason' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_reason'],
					'style' => 'width: 50%;',
				),
				'data' => array(
					'db' => 'reason',
				),
			),
			'level' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_level'],
				),
				'data' => array(
					'db' => 'counter',
				),
				'sort' => array(
					'default' => 'lc.counter DESC',
					'reverse' => 'lc.counter',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['profile_viewwarning_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex;',
			),
		),
	);

	// Create the list for viewing.
	createList($listOptions);

	// Create some common text bits for the template.
	$context['level_effects'] = array(
		0 => '',
		$modSettings['warning_watch'] => $txt['profile_warning_effect_own_watched'],
		$modSettings['warning_moderate'] => $txt['profile_warning_effect_own_moderated'],
		$modSettings['warning_mute'] => $txt['profile_warning_effect_own_muted'],
	);
	$context['current_level'] = 0;
	$context['sub_template'] = 'viewWarning';
	foreach ($context['level_effects'] as $limit => $dummy)
		if ($context['member']['warning'] >= $limit)
			$context['current_level'] = $limit;
}