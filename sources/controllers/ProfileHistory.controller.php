<?php

/**
 * Handles the access and viewing of a users history
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
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ProfileHistory Controller, show a users login, profile edits, IP history
 */
class ProfileHistory_Controller extends Action_Controller
{
	/**
	 * Member id for the history being viewed
	 * @todo should be changed to _memID
	 * @var int
	 */
	private $memID = 0;

	/**
	 * Profile history entry point.
	 * Re-directs to sub-actions.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt, $modSettings, $user_profile;

		$this->memID = currentMemberID();
		require_once(SUBSDIR . '/Action.class.php');

		$subActions = array(
			'activity' => array('controller' => $this, 'function' => 'action_trackactivity', 'label' => $txt['trackActivity']),
			'ip' => array('controller' => $this, 'function' => 'action_trackip', 'label' => $txt['trackIP']),
			'edits' => array('controller' => $this, 'function' => 'action_trackedits', 'label' => $txt['trackEdits']),
			'logins' => array('controller' => $this, 'function' => 'action_tracklogin', 'label' => $txt['trackLogins']),
		);

		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'activity';

		// Set up action/subaction stuff.
		$action = new Action();
		$action->initialize($subActions, 'activity');
		$context['sub_action'] = $subAction;

		// Create the tabs for the template.
		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['history'],
			'description' => $txt['history_description'],
			'class' => 'profile',
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
		$context['history_area'] = $subAction;
		$context['page_title'] = $txt['trackUser'] . ' - ' . $subActions[$subAction]['label'] . ' - ' . $user_profile[$this->memID]['real_name'];

		// Pass on to the actual method.
		$action->dispatch($subAction);
	}

	/**
	 * Subaction for profile history actions: activity log.
	 */
	public function action_trackactivity()
	{
		global $scripturl, $txt, $modSettings, $user_profile, $context;

		$memID = $this->memID;

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
				'function' => array($this, 'list_getUserErrors'),
				'params' => array(
					'le.id_member = {int:current_member}',
					array('current_member' => $memID),
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getUserErrorCount'),
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
		require_once(SUBSDIR . '/List.class.php');
		createList($listOptions);

		// Get all IP addresses this user has used for his messages.
		$ips = getMembersIPs($memID);
		$context['ips'] = array();
		$context['error_ips'] = array();

		foreach ($ips['message_ips'] as $ip)
			$context['ips'][] = '<a href="' . $scripturl . '?action=profile;area=history;sa=ip;searchip=' . $ip . ';u=' . $memID . '">' . $ip . '</a>';

		foreach ($ips['error_ips'] as $ip)
			$context['error_ips'][] = '<a href="' . $scripturl . '?action=profile;area=history;sa=ip;searchip=' . $ip . ';u=' . $memID . '">' . $ip . '</a>';

		// Find other users that might use the same IP.
		$context['members_in_range'] = array();

		$all_ips = array_unique(array_merge($ips['message_ips'], $ips['error_ips']));
		if (!empty($all_ips))
		{
			$members_in_range = getMembersInRange($all_ips, $memID);
			foreach ($members_in_range as $row)
				$context['members_in_range'][$row['id_member']] = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		}

		loadTemplate('ProfileHistory');
		$context['sub_template'] = 'trackActivity';
	}

	/**
	 * Track an IP address.
	 * Accessed through ?action=trackip
	 * and through ?action=profile;area=history;sa=ip
	 */
	public function action_trackip()
	{
		global $user_profile, $scripturl, $txt, $user_info, $modSettings, $context;

		$db = database();
		$memID = $this->memID;

		// Can the user do this?
		isAllowedTo('moderate_forum');

		loadTemplate('Profile');
		loadTemplate('ProfileHistory');
		loadLanguage('Profile');

		if ($memID == 0)
		{
			$context['ip'] = $user_info['ip'];
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
		require_once(SUBSDIR . '/List.class.php');

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
				'function' => array($this, 'list_getIPMessages'),
				'params' => array(
					'm.poster_ip ' . $ip_string,
					array('ip_address' => $ip_var),
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getIPMessageCount'),
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
				'function' => array($this, 'list_getUserErrors'),
				'params' => array(
					'le.ip ' . $ip_string,
					array('ip_address' => $ip_var),
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getUserErrorCount'),
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
					'url' => 'https://apps.db.ripe.net/search/query.html?searchtext=' . $context['ip'],
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
	 */
	public function action_tracklogin()
	{
		global $scripturl, $txt, $context;

		$memID = $this->memID;

		// Gonna want this for the list.
		require_once(SUBSDIR . '/List.class.php');

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
				'function' => array($this, 'list_getLogins'),
				'params' => array(
					'id_member = {int:current_member}',
					array('current_member' => $memID),
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getLoginCount'),
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
	 * Logs edits to a members profile.
	 */
	public function action_trackedits()
	{
		global $scripturl, $txt, $modSettings, $context;

		$db = database();
		$memID = $this->memID;

		require_once(SUBSDIR . '/List.class.php');

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
				'function' => array($this, 'list_getProfileEdits'),
				'params' => array(
					$memID,
				),
			),
			'get_count' => array(
				'function' => array($this, 'list_getProfileEditCount'),
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
	 * Get the number of user errors.
	 * Callback for createList in action_trackip() and action_trackactivity()
	 *
	 * @param string $where
	 * @param array $where_vars = array()
	 * @return string number of user errors
	 */
	public function list_getUserErrorCount($where, $where_vars = array())
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
	public function list_getUserErrors($start, $items_per_page, $sort, $where, $where_vars = array())
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
				'time' => standardTime($row['log_time']),
				'html_time' => htmlTime($row['log_time']),
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
	public function list_getIPMessageCount($where, $where_vars = array())
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
	public function list_getIPMessages($start, $items_per_page, $sort, $where, $where_vars = array())
	{
		global $scripturl;

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
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time'])
			);
		$db->free_result($request);

		return $messages;
	}

	/**
	 * Callback for trackLogins for counting history.
	 * (createList() in TrackLogins())
	 *
	 * @param string $where
	 * @param array $where_vars
	 * @return string count of messages matching the IP
	 */
	public function list_getLoginCount($where, $where_vars = array())
	{
		$db = database();

		$request = $db->query('', '
			SELECT COUNT(*) AS message_count
			FROM {db_prefix}member_logins
			WHERE ' . $where,
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
	public function list_getLogins($start, $items_per_page, $sort, $where, $where_vars = array())
	{
		$db = database();

		$request = $db->query('', '
			SELECT time, ip, ip2
			FROM {db_prefix}member_logins
			WHERE ' . $where .'
			ORDER BY time DESC',
			array(
				'current_member' => $where_vars['current_member'],
			)
		);
		$logins = array();
		while ($row = $db->fetch_assoc($request))
			$logins[] = array(
				'time' => standardTime($row['time']),
				'html_time' => htmlTime($row['time']),
				'timestamp' => forum_time(true, $row['time']),
				'ip' => $row['ip'],
				'ip2' => $row['ip2'],
			);
		$db->free_result($request);

		return $logins;
	}

	/**
	 * How many edits?
	 *
	 * @param int $memID id_member
	 * @return string number of profile edits
	 */
	public function list_getProfileEditCount($memID)
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
	public function list_getProfileEdits($start, $items_per_page, $sort, $memID)
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
				'html_time' => htmlTime($row['log_time']),
				'timestamp' => forum_time(true, $row['log_time']),
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
}