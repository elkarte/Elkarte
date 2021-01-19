<?php

/**
 * Functions to support the bad behavior controller
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

/**
 * Delete all or some of the entries in the bad behavior log.
 *
 * What it does:
 *
 * - It applies any necessary filters to deletion.
 * - It attempts to TRUNCATE the table to reset the auto_increment.
 * - Redirects back to the badbehavior log when done.
 *
 * @param string $type
 * @param mixed[] $filter
 *
 * @return string
 * @package BadBehavior
 * @throws \ElkArte\Exceptions\Exception
 */
function deleteBadBehavior($type, $filter)
{
	$db = database();

	// Delete all or just some?
	if ($type === 'delall' && empty($filter))
	{
		$db->truncate('{db_prefix}log_badbehavior');
	}
	// Deleting all with a filter?
	elseif ($type === 'delall' && !empty($filter))
	{
		$db->query('', '
			DELETE FROM {db_prefix}log_badbehavior
			WHERE ' . $filter['variable'] . ' LIKE {string:filter}',
			array(
				'filter' => $filter['value']['sql'],
			)
		);
	}
	// Just specific entries?
	elseif ($type === 'delete')
	{
		$db->query('', '
			DELETE FROM {db_prefix}log_badbehavior
			WHERE id IN ({array_int:log_list})',
			array(
				'log_list' => array_unique($_POST['delete']),
			)
		);

		return 'delete';
	}

	return 'delall';
}

/**
 * Get the number of badbehavior log entries.
 *
 * What it does:
 *
 * - Will take in to account any current filter value in its count result
 *
 * @param mixed[] $filter
 * @return int
 * @package BadBehavior
 * @throws \ElkArte\Exceptions\Exception
 */
function getBadBehaviorLogEntryCount($filter)
{
	$db = database();

	$result = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}log_badbehavior' . (!empty($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : ''),
		array(
			'filter' => !empty($filter) ? $filter['value']['sql'] : '',
		)
	);
	list ($entry_count) = $result->fetch_row();
	$result->free_result();

	return $entry_count;
}

/**
 * Gets the badbehavior log entries that match the specified parameters.
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param string|mixed[]|null $filter
 *
 * @return array
 * @package BadBehavior
 * @throws \Exception
 */
function getBadBehaviorLogEntries($start, $items_per_page, $sort, $filter = '')
{
	$db = database();

	require_once(EXTDIR . '/bad-behavior/bad-behavior/responses.inc.php');

	$bb_entries = array();

	$db->fetchQuery('
		SELECT 
			id, ip, date, request_method, request_uri, server_protocol, http_headers, 
			user_agent, request_entity, valid, id_member, session
		FROM {db_prefix}log_badbehavior' . (!empty($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : '') . '
		ORDER BY id ' . ($sort === 'down' ? 'DESC' : '') . '
		LIMIT ' . $items_per_page . ' OFFSET ' . $start,
		array(
			'filter' => !empty($filter) ? $filter['value']['sql'] : '',
		)
	)->fetch_callback(
		function ($row) use (&$bb_entries, $db) {
			global $scripturl;

			// Turn the key in to something nice to show
			$key_response = bb2_get_response($row['valid']);

			// Prevent undefined errors and log ..
			if (isset($key_response[0]) && $key_response[0] == '00000000')
			{
				$key_response['response'] = '';
				$key_response['explanation'] = '';
				$key_response['log'] = '';
			}

			$bb_entries[$row['id']] = array(
				'ip' => $row['ip'],
				'request_method' => $row['request_method'],
				'server_protocol' => $row['server_protocol'],
				'user_agent' => array(
					'html' => $row['user_agent'],
					'href' => base64_encode($db->escape_wildcard_string($row['user_agent']))
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
				'time' => standardTime($row['date']),
				'html_time' => htmlTime($row['date']),
				'timestamp' => forum_time(true, $row['date']),
				'request_uri' => array(
					'html' => htmlspecialchars((substr($row['request_uri'], 0, 1) === '?' ? $scripturl : '') . $row['request_uri'], ENT_COMPAT, 'UTF-8'),
					'href' => base64_encode($db->escape_wildcard_string($row['request_uri']))
				),
				'http_headers' => array(
					'html' => str_replace("\n", '<br />', $row['http_headers']),
					'href' => '#'
				),
				'id' => $row['id'],
			);
		}
	);

	return $bb_entries;
}
