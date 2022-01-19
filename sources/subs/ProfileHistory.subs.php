<?php

/**
 * Functions to support the profile history controller
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
use ElkArte\Util;

/**
 * Get the number of user errors.
 * Callback for createList in action_trackip() and action_trackactivity()
 *
 * @param string $where
 * @param mixed[] $where_vars = array() or values used in the where statement
 * @return string number of user errors
 */
function getUserErrorCount($where, $where_vars = array())
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*) AS error_count
		FROM {db_prefix}log_errors
		WHERE ' . $where,
		$where_vars
	);
	list ($count) = $request->fetch_row();
	$request->free_result();

	return $count;
}

/**
 * Callback for createList in action_trackip() and action_trackactivity()
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param string $where
 * @param mixed[] $where_vars array of values used in the where statement
 * @return mixed[] error messages array
 */
function getUserErrors($start, $items_per_page, $sort, $where, $where_vars = array())
{
	global $txt;

	$db = database();

	// Get a list of error messages from this ip (range).
	$error_messages = array();
	$db->fetchQuery('
		SELECT
			le.log_time, le.ip, le.url, le.message, COALESCE(mem.id_member, 0) AS id_member,
			COALESCE(mem.real_name, {string:guest_title}) AS display_name, mem.member_name
		FROM {db_prefix}log_errors AS le
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = le.id_member)
		WHERE ' . $where . '
		ORDER BY ' . $sort . '
		LIMIT ' . $items_per_page . '  OFFSET ' . $start,
		array_merge($where_vars, array(
			'guest_title' => $txt['guest_title'],
		))
	)->fetch_callback(
		function ($row) use (&$error_messages) {
			$error_messages[] = array(
				'ip' => $row['ip'],
				'member_link' => $row['id_member'] > 0 ? '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $row['id_member']]) . '">' . $row['display_name'] . '</a>' : $row['display_name'],
				'message' => strtr($row['message'], array('&lt;span class=&quot;remove&quot;&gt;' => '', '&lt;/span&gt;' => '')),
				'url' => $row['url'],
				'time' => standardTime($row['log_time']),
				'html_time' => htmlTime($row['log_time']),
				'timestamp' => forum_time(true, $row['log_time']),
			);
		}
	);

	return $error_messages;
}

/**
 * Callback for createList() in TrackIP()
 *
 * @param string $where
 * @param mixed[] $where_vars array of values used in the where statement
 * @return string count of messages matching the IP
 */
function getIPMessageCount($where, $where_vars = array())
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*) AS message_count
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE {query_see_board} AND ' . $where,
		$where_vars
	);
	list ($count) = $request->fetch_row();
	$request->free_result();

	return $count;
}

/**
 * Callback for createList() in TrackIP()
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param string $where
 * @param mixed[] $where_vars array of values used in the where statement
 * @return mixed[] an array of basic messages / details
 */
function getIPMessages($start, $items_per_page, $sort, $where, $where_vars = array())
{
	$db = database();

	// Get all the messages fitting this where clause.
	// @todo SLOW This query is using a filesort.
	$messages = array();
	$db->fetchQuery('
		SELECT
			m.id_msg, m.poster_ip, COALESCE(mem.real_name, m.poster_name) AS display_name, mem.id_member,
			m.subject, m.poster_time, m.id_topic, m.id_board
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE {query_see_board} AND ' . $where . '
		ORDER BY ' . $sort . '
		LIMIT ' . $items_per_page . '  OFFSET ' . $start,
		array_merge($where_vars, array())
	)->fetch_callback(
		function ($row) use (&$messages) {
			$messages[] = array(
				'ip' => $row['poster_ip'],
				'member_link' => empty($row['id_member']) ? $row['display_name'] : '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $row['id_member']]) . '">' . $row['display_name'] . '</a>',
				'board' => array(
					'id' => $row['id_board'],
					'href' => getUrl('action', ['board' => $row['id_board']])
				),
				'topic' => $row['id_topic'],
				'id' => $row['id_msg'],
				'subject' => $row['subject'],
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time'])
			);
		}
	);

	return $messages;
}

/**
 * Get all times an account was logged into
 *
 * Callback for trackLogins for counting history.
 * (createList() in TrackLogins())
 *
 * @param string $where
 * @param mixed[] $where_vars array of values used in the where statement
 * @return string count of messages matching the IP
 */
function getLoginCount($where, $where_vars = array())
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*) AS message_count
		FROM {db_prefix}member_logins
		WHERE ' . $where,
		array(
			'id_member' => $where_vars['current_member'],
		)
	);
	list ($count) = $request->fetch_row();
	$request->free_result();

	return $count;
}

/**
 * List of login history for a user
 *
 * Callback for trackLogins data.
 *
 * @param string $where
 * @param mixed[] $where_vars array of values used in the where statement
 *
 * @return mixed[] an array of messages
 */
function getLogins($where, $where_vars = array())
{
	$db = database();

	$logins = array();
	$db->fetchQuery('
		SELECT 
			time, ip, ip2
		FROM {db_prefix}member_logins
		WHERE ' . $where . '
		ORDER BY time DESC',
		array(
			'current_member' => $where_vars['current_member'],
		)
	)->fetch_callback(
		function ($row) use (&$logins) {
			$logins[] = array(
				'time' => standardTime($row['time']),
				'html_time' => htmlTime($row['time']),
				'timestamp' => forum_time(true, $row['time']),
				'ip' => $row['ip'],
				'ip2' => $row['ip2'],
			);
		}
	);

	return $logins;
}

/**
 * Determine how many profile edits a user has done
 *
 * @param int $memID id_member
 * @return string number of profile edits
 */
function getProfileEditCount($memID)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*) AS edit_count
		FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:owner}',
		array(
			'log_type' => 2,
			'owner' => $memID,
		)
	);
	list ($edit_count) = $request->fetch_row();
	$request->free_result();

	return $edit_count;
}

/**
 * List of areas that a user has made profile edits to
 *
 * Callback function for createList in trackEdits().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID
 * @return mixed[] array of profile edits
 */
function getProfileEdits($start, $items_per_page, $sort, $memID)
{
	global $txt, $context;

	$db = database();

	// Get a list of error messages from this ip (range).
	$request = $db->query('', '
		SELECT
			id_action, id_member, ip, log_time, action, extra
		FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:owner}
		ORDER BY ' . $sort . '
		LIMIT ' . $items_per_page . '  OFFSET ' . $start,
		array(
			'log_type' => 2,
			'owner' => $memID,
		)
	);
	$edits = array();
	$members = array();
	$bbc_parser = ParserWrapper::instance();
	while (($row = $request->fetch_assoc()))
	{
		$extra = Util::unserialize($row['extra']);
		if (!empty($extra['applicator']))
		{
			$members[] = $extra['applicator'];
		}

		// Work out what the name of the action is.
		if (isset($txt['trackEdit_action_' . $row['action']]))
		{
			$action_text = $txt['trackEdit_action_' . $row['action']];
		}
		elseif (isset($txt[$row['action']]))
		{
			$action_text = $txt[$row['action']];
		}
		// Custom field?
		elseif (isset($context['custom_field_titles'][$row['action']]))
		{
			$action_text = $context['custom_field_titles'][$row['action']]['title'];
		}
		else
		{
			$action_text = $row['action'];
		}

		// Parse BBC?
		$parse_bbc = isset($context['custom_field_titles'][$row['action']]) && $context['custom_field_titles'][$row['action']]['parse_bbc'];

		$edits[] = array(
			'id' => $row['id_action'],
			'ip' => $row['ip'],
			'id_member' => !empty($extra['applicator']) ? $extra['applicator'] : 0,
			'member_link' => $txt['trackEdit_deleted_member'],
			'action' => $row['action'],
			'action_text' => $action_text,
			'before' => !empty($extra['previous']) ? ($parse_bbc ? $bbc_parser->parseCustomFields($extra['previous']) : $extra['previous']) : '',
			'after' => !empty($extra['new']) ? ($parse_bbc ? $bbc_parser->parseCustomFields($extra['new']) : $extra['new']) : '',
			'time' => standardTime($row['log_time']),
			'html_time' => htmlTime($row['log_time']),
			'timestamp' => forum_time(true, $row['log_time']),
		);
	}
	$request->free_result();

	// Get any member names.
	if (!empty($members))
	{
		require_once(SUBSDIR . '/Members.subs.php');
		$result = getBasicMemberData($members);

		$members = array();
		foreach ($result as $row)
		{
			$members[$row['id_member']] = $row['real_name'];
		}

		foreach ($edits as $key => $value)
		{
			if (isset($members[$value['id_member']]))
			{
				$edits[$key]['member_link'] = '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $value['id_member']]) . '">' . $members[$value['id_member']] . '</a>';
			}
		}
	}

	return $edits;
}
