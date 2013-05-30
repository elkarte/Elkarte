<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Delete all or some of the errors in the error log.
 * It applies any necessary filters to deletion.
 * This should only be called by ManageErrors::action_log().
 * It attempts to TRUNCATE the table to reset the auto_increment.
 * Redirects back to the error log when done.
 */
function deleteErrors($type, $filter = null, $error_list = null)
{
	$db = database();

	// Delete all or just some?
	if ($type == 'delall' && !isset($filter))
		$db->query('truncate_table', '
			TRUNCATE {db_prefix}log_errors',
			array(
			)
		);
	// Deleting all with a filter?
	elseif ($type == 'delall' && isset($filter))
		$db->query('', '
			DELETE FROM {db_prefix}log_errors
			WHERE ' . $filter['variable'] . ' LIKE {string:filter}',
			array(
				'filter' => $filter['value']['sql'],
			)
		);
	// Just specific errors?
	elseif ($type == 'delete')
		$db->query('', '
			DELETE FROM {db_prefix}log_errors
			WHERE id_error IN ({array_int:error_list})',
			array(
				'error_list' => array_unique($error_list),
			)
		);
}

/**
 * Counts error log entries
 *
 * @return int
 */
function numErrors()
{
	$db = database();

	// Just how many errors are there?
	$result = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_errors' . (isset($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : ''),
		array(
			'filter' => isset($filter) ? $filter['value']['sql'] : '',
		)
	);
	list ($num_errors) = $db->fetch_row($result);

	$db->free_result($result);

	return $num_errors;
}

/**
 * Gets data from the error log
 *
 * @param int $start
 * @param string $sort_direction
 * @param array $filter
 * @return array
 */
function getErrorLogData($start, $sort_direction = 'DESC', $filter = null)
{
	global $modSettings, $scripturl, $txt;

	$db = database();

	$db = database();
	// Find and sort out the errors.
	$request = $db->query('', '
		SELECT id_error, id_member, ip, url, log_time, message, session, error_type, file, line
		FROM {db_prefix}log_errors' . (isset($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : '') . '
		ORDER BY id_error ' . ($sort_direction == 'down' ? 'DESC' : '') . '
		LIMIT ' . $start . ', ' . $modSettings['defaultMaxMessages'],
		array(
			'filter' => isset($filter) ? $filter['value']['sql'] : '',
		)
	);

	$log = array();

	for ($i = 0; $row = $db->fetch_assoc($request); $i ++)
	{
		$search_message = preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '%', $db->escape_wildcard_string($row['message']));
		if ($search_message == $filter['value']['sql'])
			$search_message = $db->escape_wildcard_string($row['message']);
		$show_message = strtr(strtr(preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '$1', $row['message']), array("\r" => '', '<br />' => "\n", '<' => '&lt;', '>' => '&gt;', '"' => '&quot;')), array("\n" => '<br />'));

		$log['errors'][$row['id_error']] = array(
			'alternate' => $i %2 == 0,
			'member' => array(
				'id' => $row['id_member'],
				'ip' => $row['ip'],
				'session' => $row['session']
			),
			'time' => standardTime($row['log_time']),
			'timestamp' => $row['log_time'],
			'url' => array(
				'html' => htmlspecialchars((substr($row['url'], 0, 1) == '?' ? $scripturl : '') . $row['url']),
				'href' => base64_encode($db->escape_wildcard_string($row['url']))
			),
			'message' => array(
				'html' => $show_message,
				'href' => base64_encode($search_message)
			),
			'id' => $row['id_error'],
			'error_type' => array(
				'type' => $row['error_type'],
				'name' => isset($txt['errortype_'.$row['error_type']]) ? $txt['errortype_'.$row['error_type']] : $row['error_type'],
			),
			'file' => array(),
		);
		if (!empty($row['file']) && !empty($row['line']))
		{
			// Eval'd files rarely point to the right location and cause havoc for linking, so don't link them.
			$linkfile = strpos($row['file'], 'eval') === false || strpos($row['file'], '?') === false; // De Morgan's Law.  Want this true unless both are present.
				$log['errors'][$row['id_error']]['file'] = array(
				'file' => $row['file'],
				'line' => $row['line'],
				'href' => $scripturl . '?action=admin;area=logs;sa=errorlog;file=' . base64_encode($row['file']) . ';line=' . $row['line'],
				'link' => $linkfile ? '<a href="' . $scripturl . '?action=admin;area=logs;sa=errorlog;file=' . base64_encode($row['file']) . ';line=' . $row['line'] . '" onclick="return reqWin(this.href, 600, 480, false);">' . $row['file'] . '</a>' : $row['file'],
				'search' => base64_encode($row['file']),
			);
		}

		// Make a list of members to load later.
		$log['members'][$row['id_member']] = $row['id_member'];
	}
	$db->free_result($request);

	return($log);
}

/**
 * Fetches errors and group them by error type
 *
 * @param bool $sort
 * @param int $filter
 * @return array
 */
function fetchErrorsByType($filter = null, $sort = null)
{
	global $txt, $scripturl;

	$db = database();

	$sum = 0;
	$types = array();

	$db = database();
	// What type of errors do we have and how many do we have?
	$request = $db->query('', '
		SELECT error_type, COUNT(*) AS num_errors
		FROM {db_prefix}log_errors
		GROUP BY error_type
		ORDER BY error_type = {string:critical_type} DESC, error_type ASC',
		array(
			'critical_type' => 'critical',
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		// Total errors so far?
		$sum += $row['num_errors'];

		$types[$sum] = array(
			'label' => (isset($txt['errortype_' . $row['error_type']]) ? $txt['errortype_' . $row['error_type']] : $row['error_type']) . ' (' . $row['num_errors'] . ')',
			'description' => isset($txt['errortype_' . $row['error_type'] . '_desc']) ? $txt['errortype_' . $row['error_type'] . '_desc'] : '',
			'url' => $scripturl . '?action=admin;area=logs;sa=errorlog' . ($sort == 'down' ? ';desc' : '') . ';filter=error_type;value=' . $row['error_type'],
			'is_selected' => isset($filter) && $filter['value']['sql'] == $db->escape_wildcard_string($row['error_type']),
		);
	}
	$db->free_result($request);

	return $types;
}