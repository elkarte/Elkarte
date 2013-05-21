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
 * Profile history main function.
 * Re-directs to sub-actions (@todo it should only set the context)
 *
 */
function action_history()
{
	global $context, $txt, $scripturl, $modSettings, $user_profile;

	$memID = currentMemberID();

	$subActions = array(
		'activity' => array('action_trackactivity', $txt['trackActivity']),
		'ip' => array('action_trackip', $txt['trackIP']),
		'edits' => array('action_trackedits', $txt['trackEdits']),
		'logins' => array('action_tracklogin', $txt['trackLogins']),
	);

	$context['history_area'] = isset($_GET['sa']) && isset($subActions[$_GET['sa']]) ? $_GET['sa'] : 'activity';

	// @todo what is $types? it is never set so this will never be true
	if (isset($types[$context['history_area']][1]))
		require_once(SOURCEDIR . '/' . $types[$context['history_area']][1]);

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['history'],
		'description' => $txt['history_description'],
		'icon' => 'profile_hd.png',
		'tabs' => array(
			'activity' => array(),
			'ip' => array(),
			'edits' => array(),
		),
	);

	// Moderation must be on to track edits.
	if (empty($modSettings['modlog_enabled']))
		unset($context[$context['profile_menu_name']]['tab_data']['edits']);

	// Set a page title.
	$context['page_title'] = $txt['trackUser'] . ' - ' . $subActions[$context['history_area']][1] . ' - ' . $user_profile[$memID]['real_name'];

	// Pass on to the actual function.
	$subActions[$context['history_area']][0]($memID);
}

/**
 * Subaction for profile history actions: activity log.
 *
 * @param int $memID id_member
 */
function action_trackactivity($memID)
{
	global $scripturl, $txt, $modSettings;
	global $user_profile, $context;

	$db = database();

	// Verify if the user has sufficient permissions.
	isAllowedTo('moderate_forum');

	$context['last_ip'] = $user_profile[$memID]['member_ip'];
	if ($context['last_ip'] != $user_profile[$memID]['member_ip2'])
		$context['last_ip2'] = $user_profile[$memID]['member_ip2'];
	$context['member']['name'] = $user_profile[$memID]['real_name'];

	// Set the options for the list component.
	$listOptions = array(
		'id' => 'track_name_user_list',
		'title' => $txt['errors_by'] . ' ' . $context['member']['name'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['no_errors_from_user'],
		'base_href' => $scripturl . '?action=profile;area=history;sa=user;u=' . $memID,
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getUserErrors',
			'params' => array(
				'le.id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'get_count' => array(
			'function' => 'list_getUserErrorCount',
			'params' => array(
				'id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'columns' => array(
			'ip_address' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=profile;area=history;sa=ip;searchip=%1$s;u=' . $memID. '">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'le.ip',
					'reverse' => 'le.ip DESC',
				),
			),
			'message' => array(
				'header' => array(
					'value' => $txt['message'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '%1$s<br /><a href="%2$s">%2$s</a>',
						'params' => array(
							'message' => false,
							'url' => false,
						),
					),
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'le.id_error DESC',
					'reverse' => 'le.id_error',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['errors_desc'],
				'class' => 'windowbg2',
				'style' => 'padding: 1ex 2ex;',
			),
		),
	);

	// Create the list for viewing.
	require_once(SUBSDIR . '/List.subs.php');
	createList($listOptions);

	// @todo cache this
	// If this is a big forum, or a large posting user, let's limit the search.
	if ($modSettings['totalMessages'] > 50000 && $user_profile[$memID]['posts'] > 500)
	{
		$request = $db->query('', '
			SELECT MAX(id_msg)
			FROM {db_prefix}messages AS m
			WHERE m.id_member = {int:current_member}',
			array(
				'current_member' => $memID,
			)
		);
		list ($max_msg_member) = $db->fetch_row($request);
		$db->free_result($request);

		// There's no point worrying ourselves with messages made yonks ago, just get recent ones!
		$min_msg_member = max(0, $max_msg_member - $user_profile[$memID]['posts'] * 3);
	}

	// Default to at least the ones we know about.
	$ips = array(
		$user_profile[$memID]['member_ip'],
		$user_profile[$memID]['member_ip2'],
	);

	// @todo cache this
	// Get all IP addresses this user has used for his messages.
	$request = $db->query('', '
		SELECT poster_ip
		FROM {db_prefix}messages
		WHERE id_member = {int:current_member}
		' . (isset($min_msg_member) ? '
			AND id_msg >= {int:min_msg_member} AND id_msg <= {int:max_msg_member}' : '') . '
		GROUP BY poster_ip',
		array(
			'current_member' => $memID,
			'min_msg_member' => !empty($min_msg_member) ? $min_msg_member : 0,
			'max_msg_member' => !empty($max_msg_member) ? $max_msg_member : 0,
		)
	);
	$context['ips'] = array();
	while ($row = $db->fetch_assoc($request))
	{
		$context['ips'][] = '<a href="' . $scripturl . '?action=profile;area=history;sa=ip;searchip=' . $row['poster_ip'] . ';u=' . $memID . '">' . $row['poster_ip'] . '</a>';
		$ips[] = $row['poster_ip'];
	}
	$db->free_result($request);

	// Now also get the IP addresses from the error messages.
	$request = $db->query('', '
		SELECT COUNT(*) AS error_count, ip
		FROM {db_prefix}log_errors
		WHERE id_member = {int:current_member}
		GROUP BY ip',
		array(
			'current_member' => $memID,
		)
	);
	$context['error_ips'] = array();
	while ($row = $db->fetch_assoc($request))
	{
		$context['error_ips'][] = '<a href="' . $scripturl . '?action=profile;area=history;sa=ip;searchip=' . $row['ip'] . ';u=' . $memID . '">' . $row['ip'] . '</a>';
		$ips[] = $row['ip'];
	}
	$db->free_result($request);

	// Find other users that might use the same IP.
	$ips = array_unique($ips);
	$context['members_in_range'] = array();
	if (!empty($ips))
	{
		// Get member ID's which are in messages...
		$request = $db->query('', '
			SELECT mem.id_member
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.poster_ip IN ({array_string:ip_list})
			GROUP BY mem.id_member
			HAVING mem.id_member != {int:current_member}',
			array(
				'current_member' => $memID,
				'ip_list' => $ips,
			)
		);
		$message_members = array();
		while ($row = $db->fetch_assoc($request))
			$message_members[] = $row['id_member'];
		$db->free_result($request);

		// Fetch their names, cause of the GROUP BY doesn't like giving us that normally.
		if (!empty($message_members))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			// Get the latest activated member's display name.
			$result = getBasicMemberData($message_members);
			foreach ($result as $row)
				$context['members_in_range'][$row['id_member']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		}

		$request = $db->query('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member != {int:current_member}
				AND member_ip IN ({array_string:ip_list})',
			array(
				'current_member' => $memID,
				'ip_list' => $ips,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$context['members_in_range'][$row['id_member']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		$db->free_result($request);
	}
	$context['sub_template'] = 'trackActivity';
}

/**
 * Get the number of user errors.
 * Callback for createList in action_trackip() and action_trackactivity()
 *
 * @param string $where
 * @param array $where_vars = array()
 * @return string number of user errors
 */
function list_getUserErrorCount($where, $where_vars = array())
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*) AS error_count
		FROM {db_prefix}log_errors
		WHERE ' . $where,
		$where_vars
	);
	list ($count) = $db->fetch_row($request);
	$db->free_result($request);

	return $count;
}

/**
 * Callback for createList in action_trackip() and action_trackactivity()
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param string $where
 * @param array $where_vars
 * @return array error messages
 */
function list_getUserErrors($start, $items_per_page, $sort, $where, $where_vars = array())
{
	global $txt, $scripturl;

	$db = database();

	// Get a list of error messages from this ip (range).
	$request = $db->query('', '
		SELECT
			le.log_time, le.ip, le.url, le.message, IFNULL(mem.id_member, 0) AS id_member,
			IFNULL(mem.real_name, {string:guest_title}) AS display_name, mem.member_name
		FROM {db_prefix}log_errors AS le
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = le.id_member)
		WHERE ' . $where . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($where_vars, array(
			'guest_title' => $txt['guest_title'],
		))
	);
	$error_messages = array();
	while ($row = $db->fetch_assoc($request))
		$error_messages[] = array(
			'ip' => $row['ip'],
			'member_link' => $row['id_member'] > 0 ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>' : $row['display_name'],
			'message' => strtr($row['message'], array('&lt;span class=&quot;remove&quot;&gt;' => '', '&lt;/span&gt;' => '')),
			'url' => $row['url'],
			'time' => relativeTime($row['log_time']),
			'timestamp' => forum_time(true, $row['log_time']),
		);
	$db->free_result($request);

	return $error_messages;
}

/**
 * Callback for createList() in TrackIP()
 *
 * @param string $where
 * @param array $where_vars
 * @return string count of messages matching the IP
 */
function list_getIPMessageCount($where, $where_vars = array())
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*) AS message_count
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE {query_see_board} AND ' . $where,
		$where_vars
	);
	list ($count) = $db->fetch_row($request);
	$db->free_result($request);

	return $count;
}

/**
 * Callback for createList() in TrackIP()
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param string $where
 * @param array $where_vars
 * @return array an array of messages
 */
function list_getIPMessages($start, $items_per_page, $sort, $where, $where_vars = array())
{
	global $txt, $scripturl;

	$db = database();

	// Get all the messages fitting this where clause.
	// @todo SLOW This query is using a filesort.
	$request = $db->query('', '
		SELECT
			m.id_msg, m.poster_ip, IFNULL(mem.real_name, m.poster_name) AS display_name, mem.id_member,
			m.subject, m.poster_time, m.id_topic, m.id_board
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE {query_see_board} AND ' . $where . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($where_vars, array(
		))
	);
	$messages = array();
	while ($row = $db->fetch_assoc($request))
		$messages[] = array(
			'ip' => $row['poster_ip'],
			'member_link' => empty($row['id_member']) ? $row['display_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>',
			'board' => array(
				'id' => $row['id_board'],
				'href' => $scripturl . '?board=' . $row['id_board']
			),
			'topic' => $row['id_topic'],
			'id' => $row['id_msg'],
			'subject' => $row['subject'],
			'time' => relativeTime($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time'])
		);
	$db->free_result($request);

	return $messages;
}

/**
 * Track an IP address.
 * Accessed through ?action=trackip
 * and through ?action=profile;area=history;sa=ip
 *
 * @param int $memID = 0 id_member
 */
function action_trackip($memID = 0)
{
	global $user_profile, $scripturl, $txt, $user_info, $modSettings;
	global $context;

	$db = database();

	// Can the user do this?
	isAllowedTo('moderate_forum');

	if ($memID == 0)
	{
		$context['ip'] = $user_info['ip'];
		loadTemplate('Profile');
		loadLanguage('Profile');
		$context['sub_template'] = 'trackIP';
		$context['page_title'] = $txt['profile'];
		$context['base_url'] = $scripturl . '?action=trackip';
	}
	else
	{
		$context['ip'] = $user_profile[$memID]['member_ip'];
		$context['base_url'] = $scripturl . '?action=profile;area=history;sa=ip;u=' . $memID;
	}

	// Searching?
	if (isset($_REQUEST['searchip']))
		$context['ip'] = trim($_REQUEST['searchip']);

	if (preg_match('/^\d{1,3}\.(\d{1,3}|\*)\.(\d{1,3}|\*)\.(\d{1,3}|\*)$/', $context['ip']) == 0 && isValidIPv6($context['ip']) === false)
		fatal_lang_error('invalid_tracking_ip', false);

	$ip_var = str_replace('*', '%', $context['ip']);
	$ip_string = strpos($ip_var, '%') === false ? '= {string:ip_address}' : 'LIKE {string:ip_address}';

	if (empty($context['history_area']))
		$context['page_title'] = $txt['trackIP'] . ' - ' . $context['ip'];

	$request = $db->query('', '
		SELECT id_member, real_name AS display_name, member_ip
		FROM {db_prefix}members
		WHERE member_ip ' . $ip_string,
		array(
			'ip_address' => $ip_var,
		)
	);
	$context['ips'] = array();
	while ($row = $db->fetch_assoc($request))
		$context['ips'][$row['member_ip']][] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>';
	$db->free_result($request);

	ksort($context['ips']);

	// Gonna want this for the list.
	require_once(SUBSDIR . '/List.subs.php');

	// Start with the user messages.
	$listOptions = array(
		'id' => 'track_message_list',
		'title' => $txt['messages_from_ip'] . ' ' . $context['ip'],
		'start_var_name' => 'messageStart',
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['no_messages_from_ip'],
		'base_href' => $context['base_url'] . ';searchip=' . $context['ip'],
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getIPMessages',
			'params' => array(
				'm.poster_ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'get_count' => array(
			'function' => 'list_getIPMessageCount',
			'params' => array(
				'm.poster_ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'columns' => array(
			'ip_address' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $context['base_url'] . ';searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'INET_ATON(m.poster_ip)',
					'reverse' => 'INET_ATON(m.poster_ip) DESC',
				),
			),
			'poster' => array(
				'header' => array(
					'value' => $txt['poster'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
			'subject' => array(
				'header' => array(
					'value' => $txt['subject'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?topic=%1$s.msg%2$s#msg%2$s" rel="nofollow">%3$s</a>',
						'params' => array(
							'topic' => false,
							'id' => false,
							'subject' => false,
						),
					),
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'm.id_msg DESC',
					'reverse' => 'm.id_msg',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['messages_from_ip_desc'],
				'class' => 'windowbg2',
				'style' => 'padding: 1ex 2ex;',
			),
		),
	);

	// Create the messages list.
	createList($listOptions);

	// Set the options for the error lists.
	$listOptions = array(
		'id' => 'track_ip_user_list',
		'title' => $txt['errors_from_ip'] . ' ' . $context['ip'],
		'start_var_name' => 'errorStart',
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['no_errors_from_ip'],
		'base_href' => $context['base_url'] . ';searchip=' . $context['ip'],
		'default_sort_col' => 'date2',
		'get_items' => array(
			'function' => 'list_getUserErrors',
			'params' => array(
				'le.ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'get_count' => array(
			'function' => 'list_getUserErrorCount',
			'params' => array(
				'ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'columns' => array(
			'ip_address2' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $context['base_url'] . ';searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'INET_ATON(le.ip)',
					'reverse' => 'INET_ATON(le.ip) DESC',
				),
			),
			'display_name' => array(
				'header' => array(
					'value' => $txt['display_name'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
			'message' => array(
				'header' => array(
					'value' => $txt['message'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '%1$s<br /><a href="%2$s">%2$s</a>',
						'params' => array(
							'message' => false,
							'url' => false,
						),
					),
				),
			),
			'date2' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'le.id_error DESC',
					'reverse' => 'le.id_error',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['errors_from_ip_desc'],
				'class' => 'windowbg2',
				'style' => 'padding: 1ex 2ex;',
			),
		),
	);

	// Create the error list.
	createList($listOptions);

	$context['single_ip'] = strpos($context['ip'], '*') === false;
	if ($context['single_ip'])
	{
		$context['whois_servers'] = array(
			'afrinic' => array(
				'name' => $txt['whois_afrinic'],
				'url' => 'http://www.afrinic.net/cgi-bin/whois?searchtext=' . $context['ip'],
				'range' => array(41, 154, 196),
			),
			'apnic' => array(
				'name' => $txt['whois_apnic'],
				'url' => 'http://wq.apnic.net/apnic-bin/whois.pl?searchtext=' . $context['ip'],
				'range' => array(58, 59, 60, 61, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123, 124,
					125, 126, 133, 150, 153, 163, 171, 202, 203, 210, 211, 218, 219, 220, 221, 222),
			),
			'arin' => array(
				'name' => $txt['whois_arin'],
				'url' => 'http://whois.arin.net/rest/ip/' . $context['ip'],
				'range' => array(7, 24, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 96, 97, 98, 99,
					128, 129, 130, 131, 132, 134, 135, 136, 137, 138, 139, 140, 142, 143, 144, 146, 147, 148, 149,
					152, 155, 156, 157, 158, 159, 160, 161, 162, 164, 165, 166, 167, 168, 169, 170, 172, 173, 174,
					192, 198, 199, 204, 205, 206, 207, 208, 209, 216),
			),
			'lacnic' => array(
				'name' => $txt['whois_lacnic'],
				'url' => 'http://lacnic.net/cgi-bin/lacnic/whois?query=' . $context['ip'],
				'range' => array(186, 187, 189, 190, 191, 200, 201),
			),
			'ripe' => array(
				'name' => $txt['whois_ripe'],
				'url' => 'http://www.db.ripe.net/whois?searchtext=' . $context['ip'],
				'range' => array(62, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95,
					141, 145, 151, 188, 193, 194, 195, 212, 213, 217),
			),
		);

		foreach ($context['whois_servers'] as $whois)
		{
			// Strip off the "decimal point" and anything following...
			if (in_array((int) $context['ip'], $whois['range']))
				$context['auto_whois_server'] = $whois;
		}
	}
	$context['sub_template'] = 'trackIP';
}

/**
 * Tracks the logins of a given user.
 * Accessed by ?action=trackip
 * and ?action=profile;area=history;sa=ip
 *
 * @param int $memID = 0 id_member
 */
function action_tracklogin($memID = 0)
{
	global $user_profile, $scripturl, $txt, $user_info, $modSettings;
	global $context;

	$db = database();

	// Gonna want this for the list.
	require_once(SUBSDIR . '/List.subs.php');

	if ($memID == 0)
		$context['base_url'] = $scripturl . '?action=trackip';
	else
		$context['base_url'] = $scripturl . '?action=profile;area=history;sa=ip;u=' . $memID;

	// Start with the user messages.
	$listOptions = array(
		'id' => 'track_logins_list',
		'title' => $txt['trackLogins'],
		'no_items_label' => $txt['trackLogins_none_found'],
		'base_href' => $context['base_url'],
		'get_items' => array(
			'function' => 'list_getLogins',
			'params' => array(
				'id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'get_count' => array(
			'function' => 'list_getLoginCount',
			'params' => array(
				'id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'columns' => array(
			'time' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $context['base_url'] . ';searchip=%1$s">%1$s</a> (<a href="' . $context['base_url'] . ';searchip=%2$s">%2$s</a>) ',
						'params' => array(
							'ip' => false,
							'ip2' => false
						),
					),
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['trackLogins_desc'],
				'class' => 'windowbg2',
				'style' => 'padding: 1ex 2ex;',
			),
		),
	);

	// Create the messages list.
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'track_logins_list';
}

/**
 * Callback for trackLogins for counting history.
 * (createList() in TrackLogins())
 *
 * @param string $where
 * @param array $where_vars
 * @return string count of messages matching the IP
 */
function list_getLoginCount($where, $where_vars = array())
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*) AS message_count
		FROM {db_prefix}member_logins
		WHERE id_member = {int:id_member}',
		array(
			'id_member' => $where_vars['current_member'],
		)
	);
	list ($count) = $db->fetch_row($request);
	$db->free_result($request);

	return $count;
}

/**
 * Callback for trackLogins data.
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param string $where
 * @param array $where_vars
 * @return array an array of messages
 */
function list_getLogins($start, $items_per_page, $sort, $where, $where_vars = array())
{
	global $txt, $scripturl;

	$db = database();

	$request = $db->query('', '
		SELECT time, ip, ip2
		FROM {db_prefix}member_logins
		WHERE {int:id_member}
		ORDER BY time DESC',
		array(
			'id_member' => $where_vars['current_member'],
		)
	);
	$logins = array();
	while ($row = $db->fetch_assoc($request))
		$logins[] = array(
			'time' => relativeTime($row['time']),
			'ip' => $row['ip'],
			'ip2' => $row['ip2'],
		);
	$db->free_result($request);

	return $logins;
}

/**
 * Logs edits to a members profile.
 *
 * @param int $memID id_member
 */
function action_trackedits($memID)
{
	global $scripturl, $txt, $modSettings, $context;

	$db = database();

	require_once(SUBSDIR . '/List.subs.php');

	// Get the names of any custom fields.
	$request = $db->query('', '
		SELECT col_name, field_name, bbc
		FROM {db_prefix}custom_fields',
		array(
		)
	);
	$context['custom_field_titles'] = array();
	while ($row = $db->fetch_assoc($request))
		$context['custom_field_titles']['customfield_' . $row['col_name']] = array(
			'title' => $row['field_name'],
			'parse_bbc' => $row['bbc'],
		);
	$db->free_result($request);

	// Set the options for the error lists.
	$listOptions = array(
		'id' => 'edit_list',
		'title' => $txt['trackEdits'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'no_items_label' => $txt['trackEdit_no_edits'],
		'base_href' => $scripturl . '?action=profile;area=history;sa=edits;u=' . $memID,
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getProfileEdits',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getProfileEditCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'action' => array(
				'header' => array(
					'value' => $txt['trackEdit_action'],
				),
				'data' => array(
					'db' => 'action_text',
				),
			),
			'before' => array(
				'header' => array(
					'value' => $txt['trackEdit_before'],
				),
				'data' => array(
					'db' => 'before',
				),
			),
			'after' => array(
				'header' => array(
					'value' => $txt['trackEdit_after'],
				),
				'data' => array(
					'db' => 'after',
				),
			),
			'time' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'id_action DESC',
					'reverse' => 'id_action',
				),
			),
			'applicator' => array(
				'header' => array(
					'value' => $txt['trackEdit_applicator'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
		),
	);

	// Create the error list.
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'edit_list';
}

/**
 * How many edits?
 *
 * @param int $memID id_member
 * @return string number of profile edits
 */
function list_getProfileEditCount($memID)
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*) AS edit_count
		FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:owner}',
		array(
			'log_type' => 2,
			'owner' => $memID,
		)
	);
	list ($edit_count) = $db->fetch_row($request);
	$db->free_result($request);

	return $edit_count;
}

/**
 * Callback function for createList in trackEdits().
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param int $memID
 * @return array
 */
function list_getProfileEdits($start, $items_per_page, $sort, $memID)
{
	global $txt, $scripturl, $context;

	$db = database();

	// Get a list of error messages from this ip (range).
	$request = $db->query('', '
		SELECT
			id_action, id_member, ip, log_time, action, extra
		FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:owner}
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'log_type' => 2,
			'owner' => $memID,
		)
	);
	$edits = array();
	$members = array();
	while ($row = $db->fetch_assoc($request))
	{
		$extra = @unserialize($row['extra']);
		if (!empty($extra['applicator']))
			$members[] = $extra['applicator'];

		// Work out what the name of the action is.
		if (isset($txt['trackEdit_action_' . $row['action']]))
			$action_text = $txt['trackEdit_action_' . $row['action']];
		elseif (isset($txt[$row['action']]))
			$action_text = $txt[$row['action']];
		// Custom field?
		elseif (isset($context['custom_field_titles'][$row['action']]))
			$action_text = $context['custom_field_titles'][$row['action']]['title'];
		else
			$action_text = $row['action'];

		// Parse BBC?
		$parse_bbc = isset($context['custom_field_titles'][$row['action']]) && $context['custom_field_titles'][$row['action']]['parse_bbc'] ? true : false;

		$edits[] = array(
			'id' => $row['id_action'],
			'ip' => $row['ip'],
			'id_member' => !empty($extra['applicator']) ? $extra['applicator'] : 0,
			'member_link' => $txt['trackEdit_deleted_member'],
			'action' => $row['action'],
			'action_text' => $action_text,
			'before' => !empty($extra['previous']) ? ($parse_bbc ? parse_bbc($extra['previous']) : $extra['previous']) : '',
			'after' => !empty($extra['new']) ? ($parse_bbc ? parse_bbc($extra['new']) : $extra['new']) : '',
			'time' => standardTime($row['log_time']),
		);
	}
	$db->free_result($request);

	// Get any member names.
	if (!empty($members))
	{
		require_once(SUBSDIR . '/Members.subs.php');
		$result = getBasicMemberData($members);
		$members = array();
		foreach ($result as $row)
			$members[$row['id_member']] = $row['real_name'];

		foreach ($edits as $key => $value)
			if (isset($members[$value['id_member']]))
				$edits[$key]['member_link'] = '<a href="' . $scripturl . '?action=profile;u=' . $value['id_member'] . '">' . $members[$value['id_member']] . '</a>';
	}

	return $edits;
}
