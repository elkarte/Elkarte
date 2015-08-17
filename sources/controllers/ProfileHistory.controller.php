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
 * @version 1.1 dev
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
	 * @var int
	 */
	private $_memID = 0;

	/**
	 * Profile history entry point.
	 * Re-directs to sub-actions.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt, $modSettings, $user_profile;

		$this->_memID = currentMemberID();

		$subActions = array(
			'activity' => array('controller' => $this, 'function' => 'action_trackactivity', 'label' => $txt['trackActivity']),
			'ip' => array('controller' => $this, 'function' => 'action_trackip', 'label' => $txt['trackIP']),
			'edits' => array('controller' => $this, 'function' => 'action_trackedits', 'label' => $txt['trackEdits']),
			'logins' => array('controller' => $this, 'function' => 'action_tracklogin', 'label' => $txt['trackLogins']),
		);

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

		// Set up action/subaction stuff.
		$action = new Action('profile_history');

		// Yep, sub-action time and call integrate_sa_profile_history as well
		$subAction = $action->initialize($subActions, 'activity');
		$context['sub_action'] = $subAction;

		// Moderation must be on to track edits.
		if (empty($modSettings['modlog_enabled']))
			unset($context[$context['profile_menu_name']]['tab_data']['edits']);

		// Set a page title.
		$context['history_area'] = $subAction;
		$context['page_title'] = $txt['trackUser'] . ' - ' . $subActions[$subAction]['label'] . ' - ' . $user_profile[$this->_memID]['real_name'];

		// Pass on to the actual method.
		$action->dispatch($subAction);
	}

	/**
	 * Subaction for profile history actions: activity log.
	 */
	public function action_trackactivity()
	{
		global $scripturl, $txt, $modSettings, $user_profile, $context;

		$memID = $this->_memID;

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

		$memID = $this->_memID;

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
			Errors::instance()->fatal_lang_error('invalid_tracking_ip', false);

		$ip_var = str_replace('*', '%', $context['ip']);
		$ip_string = strpos($ip_var, '%') === false ? '= {string:ip_address}' : 'LIKE {string:ip_address}';

		if (empty($context['history_area']))
			$context['page_title'] = $txt['trackIP'] . ' - ' . $context['ip'];

		// Fetch the members that are associated with the ip's
		require_once(SUBSDIR . '/Members.subs.php');
		$context['ips'] = loadMembersIPs($ip_string, $ip_var);

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
				),
				'apnic' => array(
					'name' => $txt['whois_apnic'],
					'url' => 'http://wq.apnic.net/apnic-bin/whois.pl?searchtext=' . $context['ip'],
				),
				'arin' => array(
					'name' => $txt['whois_arin'],
					'url' => 'http://whois.arin.net/rest/ip/' . $context['ip'],
				),
				'lacnic' => array(
					'name' => $txt['whois_lacnic'],
					'url' => 'http://lacnic.net/cgi-bin/lacnic/whois?query=' . $context['ip'],
				),
				'ripe' => array(
					'name' => $txt['whois_ripe'],
					'url' => 'https://apps.db.ripe.net/search/query.html?searchtext=' . $context['ip'],
				),
			);

			// Let integration add whois servers easily
			call_integration_hook('integrate_trackip');
		}
		$context['sub_template'] = 'trackIP';
	}

	/**
	 * Tracks the logins of a given user.
	 *
	 * - Accessed by ?action=trackip and ?action=profile;area=history;sa=ip
	 */
	public function action_tracklogin()
	{
		global $scripturl, $txt, $context;

		$memID = $this->_memID;

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

		$memID = $this->_memID;

		// Get the names of any custom fields.
		require_once(SUBSDIR . '/ManageFeatures.subs.php');
		$context['custom_field_titles'] = loadAllCustomFields();

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
	 * Get the number of user errors
	 *
	 * Passthough for createList to getUserErrorCount
	 * used in action_trackip() and action_trackactivity()
	 *
	 * @param string $where
	 * @param mixed[] $where_vars = array() or values used in the where statement
	 * @return string number of user errors
	 */
	public function list_getUserErrorCount($where, $where_vars = array())
	{
		require_once(SUBSDIR . '/ProfileHistory.subs.php');
		$count = getUserErrorCount($where, $where_vars);

		return $count;
	}

	/**
	 * Get a list of error messages from this ip (range).
	 *
	 * Passthough to getUserErrors for createList in action_trackip() and action_trackactivity()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param string $where
	 * @param mixed[] $where_vars array of values used in the where statement
	 * @return mixed[] error messages array
	 */
	public function list_getUserErrors($start, $items_per_page, $sort, $where, $where_vars = array())
	{
		require_once(SUBSDIR . '/ProfileHistory.subs.php');
		$error_messages = getUserErrors($start, $items_per_page, $sort, $where, $where_vars);

		return $error_messages;
	}

	/**
	 * count of messages from a matching IP
	 *
	 * Passthough to getIPMessageCount for createList() in TrackIP()
	 *
	 * @param string $where
	 * @param mixed[] $where_vars array of values used in the where statement
	 * @return string count of messages matching the IP
	 */
	public function list_getIPMessageCount($where, $where_vars = array())
	{
		require_once(SUBSDIR . '/ProfileHistory.subs.php');
		$count = getIPMessageCount($where, $where_vars);

		return $count;
	}

	/**
	 * Fetch a listing of messages made from a given IP
	 *
	 * Passthrough to getIPMessages used by createList() in TrackIP()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param string $where
	 * @param mixed[] $where_vars array of values used in the where statement
	 * @return mixed[] an array of basic messages / details
	 */
	public function list_getIPMessages($start, $items_per_page, $sort, $where, $where_vars = array())
	{
		require_once(SUBSDIR . '/ProfileHistory.subs.php');
		$messages = getIPMessages($start, $items_per_page, $sort, $where, $where_vars);

		return $messages;
	}

	/**
	 * Get list of all times this account was logged into
	 *
	 * Passthrough to getLoginCount for trackLogins for counting history.
	 * (createList() in TrackLogins())
	 *
	 * @param string $where
	 * @param mixed[] $where_vars array of values used in the where statement
	 * @return string count of messages matching the IP
	 */
	public function list_getLoginCount($where, $where_vars = array())
	{
		require_once(SUBSDIR . '/ProfileHistory.subs.php');
		$count = getLoginCount($where, $where_vars);

		return $count;
	}

	/**
	 * List of login history for a user
	 *
	 * Passthrough to getLogins for trackLogins data.
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param string $where
	 * @param mixed[] $where_vars array of values used in the where statement
	 * @return mixed[] an array of messages
	 */
	public function list_getLogins($start, $items_per_page, $sort, $where, $where_vars = array())
	{
		require_once(SUBSDIR . '/ProfileHistory.subs.php');
		$logins = getLogins($start, $items_per_page, $sort, $where, $where_vars);

		return $logins;
	}

	/**
	 * How many profile edits
	 *
	 * Passthrough to getProfileEditCount.
	 *
	 * @param int $memID id_member
	 * @return string number of profile edits
	 */
	public function list_getProfileEditCount($memID)
	{
		require_once(SUBSDIR . '/ProfileHistory.subs.php');
		$edit_count = getProfileEditCount($memID);

		return $edit_count;
	}

	/**
	 * List of profile edits for display
	 *
	 * Passthrough to  getProfileEditsfunction for createList in trackEdits().
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param int $memID
	 * @return mixed[] array of profile edits
	 */
	public function list_getProfileEdits($start, $items_per_page, $sort, $memID)
	{
		require_once(SUBSDIR . '/ProfileHistory.subs.php');
		$edits = getProfileEdits($start, $items_per_page, $sort, $memID);

		return $edits;
	}
}