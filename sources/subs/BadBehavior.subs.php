<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Delete all or some of the entries in the bad behavior log.
 * It applies any necessary filters to deletion.
 * It attempts to TRUNCATE the table to reset the auto_increment.
 * Redirects back to the badbehavior log when done.
 *
 * @param array $filter
 */
function deleteBadBehavior($filter)
{
	global $smcFunc;

	// Make sure the session exists and the token is correct
	checkSession();
	validateToken('admin-bbl');

	// Delete all or just some?
	if (isset($_POST['delall']) && empty($filter))
	{
		$smcFunc['db_query']('truncate_table', '
			TRUNCATE {db_prefix}log_badbehavior',
			array(
			)
		);
	}
	// Deleting all with a filter?
	elseif (isset($_POST['delall']) && !empty($filter))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_badbehavior
			WHERE ' . $filter['variable'] . ' LIKE {string:filter}',
			array(
				'filter' => $filter['value']['sql'],
			)
		);
	}
	// Just specific entries?
	elseif (!empty($_POST['delete']))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_badbehavior
			WHERE id IN ({array_int:log_list})',
			array(
				'log_list' => array_unique($_POST['delete']),
			)
		);

		$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : 0;

		// Go back to where we were.
		redirectexit('action=admin;area=logs;sa=badbehaviorlog' . (isset($_REQUEST['desc']) ? ';desc' : '') . ';start=' . $start . (!empty($filter) ? ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'] : ''));
	}

	// Back to the badbehavior log
	redirectexit('action=admin;area=logs;sa=badbehaviorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));
}

/**
 * Get the number of badbehavior log entries.
 * Will take in to acount any current filter value in its count result
 *
 * @param array $filter
 */
function getBadBehaviorLogEntryCount($filter)
{
	global $smcFunc;

	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_badbehavior' . (!empty($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : ''),
		array(
			'filter' => !empty($filter) ? $filter['value']['sql'] : '',
		)
	);
	list ($entry_count) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	return $entry_count;
}

/**
 * Gets the badbehavior log entries that match the specified parameters.
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param array $filter
 * @param array $members
 */
function getBadBehaviorLogEntries($start, $items_per_page, $sort, &$members, $filter = '')
{
	global $context, $scripturl, $smcFunc;

	require_once(EXTDIR . '/bad-behavior/bad-behavior/responses.inc.php');

	$request = $smcFunc['db_query']('', '
		SELECT id, ip, date, request_method, request_uri, server_protocol, http_headers, user_agent, request_entity, valid, id_member, session
		FROM {db_prefix}log_badbehavior' . (!empty($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : '') . '
		ORDER BY id ' . ($sort === 'down' ? 'DESC' : '') . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'filter' => !empty($filter) ? $filter['value']['sql'] : '',
		)
	);
	$context['bb_entries'] = array();
	$members = array();
	for ($i = 0; $row = $smcFunc['db_fetch_assoc']($request); $i++)
	{
		// Turn the key in to something nice to show
		$key_response = bb2_get_response($row['valid']);

		$context['bb_entries'][$row['id']] = array(
			'alternate' => $i %2 == 0,
			'ip' => $row['ip'],
			'request_method' => $row['request_method'],
			'server_protocol' => $row['server_protocol'],
			'user_agent' => array(
				'html' => $row['user_agent'],
				'href' => base64_encode($smcFunc['db_escape_wildcard_string']($row['user_agent']))
			),
			'request_entity' => $row['request_entity'],
			'valid' => array(
				'code' => $row['valid'],
				'response' => $key_response['response'],
				'explanation' => $key_response['explanation'],
				'log' => $key_response['log'],
			),
			'member' => array(
				'id' => $row['id_member'],
				'ip' => $row['ip'],
				'session' => $row['session']
			),
			'date' => timeformat($row['date']),
			'timestamp' => $row['date'],
			'request_uri' => array(
				'html' => htmlspecialchars((substr($row['request_uri'], 0, 1) === '?' ? $scripturl : '') . $row['request_uri']),
				'href' => base64_encode($smcFunc['db_escape_wildcard_string']($row['request_uri']))
			),
			'http_headers' => array(
				'html' => str_replace("\n", '<br />', $row['http_headers']),
				'href' => '#'
			),
			'id' => $row['id'],
		);

		// Make a list of members to load later.
		$members[$row['id_member']] = $row['id_member'];
	}
	$smcFunc['db_free_result']($request);
}