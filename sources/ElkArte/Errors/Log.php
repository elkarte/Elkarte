<?php

/**
 * Functions to assist in viewing and maintaining the error logs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

namespace ElkArte\Errors;

use ElkArte\AbstractModel;

/**
 * Class to handle all forum errors and exceptions
 */
class Log extends AbstractModel
{
	/**
	 * Delete all or some of the errors in the error log.
	 * It applies any necessary filters to deletion.
	 * This should only be called by ManageErrors.controller::action_log().
	 * It attempts to TRUNCATE the table to reset the auto_increment.
	 * Redirects back to the error log when done.
	 *
	 * @param string $type action
	 * @param array|null $filter this->_db query of the view filter being used
	 * @param int[]|null $error_list int list of error ID's to work on
	 */
	public function deleteErrors($type, $filter = null, $error_list = null): void
	{
		// Delete all or just some?
		if ($type === 'delall' && empty($filter))
		{
			$this->_db->truncate('{db_prefix}log_errors');
		}
		// Deleting all with a filter?
		elseif ($type === 'delall' && !empty($filter))
		{
			$this->_db->query('', '
				DELETE FROM {db_prefix}log_errors
				WHERE ' . $filter['variable'] . ' LIKE {string:filter}',
				[
					'filter' => $filter['value']['sql'],
				]
			);
		}
		// Just specific errors?
		elseif ($type === 'delete')
		{
			$this->_db->query('', '
				DELETE FROM {db_prefix}log_errors
				WHERE id_error IN ({array_int:error_list})',
				[
					'error_list' => is_array($error_list) ? array_unique($error_list) : '',
				]
			);
		}
	}

	/**
	 * Counts error log entries
	 *
	 * @param array $filter this->_db query of the filter being used
	 *
	 * @return int
	 */
	public function numErrors($filter = []): int
	{
		// Just how many errors are there?
		$result = $this->_db->query('', '
			SELECT
			 	COUNT(*)
			FROM {db_prefix}log_errors' . (empty($filter) ? '' : '
			WHERE ' . $filter['variable'] . ' LIKE {string:filter}'),
			[
				'filter' => empty($filter) ? '' : $filter['value']['sql'],
			]
		);
		[$num_errors] = $result->fetch_row();

		$result->free_result();

		return $num_errors;
	}

	/**
	 * Gets data from the error log
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param string $sort_direction DESC or ASC results
	 * @param array|null $filter
	 *
	 * @return array
	 */
	public function getErrorLogData($start, $sort_direction = 'DESC', $filter = null): array
	{
		global $scripturl, $txt;

		// Find and sort out the errors.
		$log = [];
		$this->_db->fetchQuery('
			SELECT 
				id_error, id_member, ip, url, log_time, message, session, error_type, file, line
			FROM {db_prefix}log_errors' . (empty($filter) ? '' : '
			WHERE ' . $filter['variable'] . ' LIKE {string:filter}') . '
			ORDER BY id_error ' . ($sort_direction === 'down' ? 'DESC' : '') . '
			LIMIT ' . $this->_modSettings['defaultMaxMessages'] . ' OFFSET ' . $start,
			[
				'filter' => empty($filter) ? '' : $filter['value']['sql'],
			]
		)->fetch_callback(
			function ($row) use (&$log, $filter, $scripturl, $txt) {
				$search_message = preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '%', $this->_db->escape_wildcard_string($row['message']));
				if (!empty($filter) && $search_message == $filter['value']['sql'])
				{
					$search_message = $this->_db->escape_wildcard_string($row['message']);
				}

				$show_message = strtr(strtr(preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '$1', $row['message']), ["\r" => '', '<br />' => "\n", '<' => '&lt;', '>' => '&gt;', '"' => '&quot;']), ["\n" => '<br />']);

				$log['errors'][$row['id_error']] = [
					'member' => [
						'id' => $row['id_member'],
						'ip' => $row['ip'],
						'session' => $row['session'],
					],
					'time' => standardTime($row['log_time']),
					'html_time' => htmlTime($row['log_time']),
					'timestamp' => forum_time(true, $row['log_time']),
					'url' => [
						'html' => htmlspecialchars((str_starts_with($row['url'], '?') ? $scripturl : '') . $row['url'], ENT_COMPAT, 'UTF-8'),
						'href' => base64_encode($this->_db->escape_wildcard_string($row['url'])),
					],
					'message' => [
						'html' => $show_message,
						'href' => base64_encode($search_message),
					],
					'id' => $row['id_error'],
					'error_type' => [
						'type' => $row['error_type'],
						'name' => $txt['errortype_' . $row['error_type']] ?? $row['error_type'],
					],
					'file' => [],
				];

				if (!empty($row['file']) && !empty($row['line']))
				{
					$log['errors'][$row['id_error']]['file'] = [
						'file' => $row['file'],
						'line' => $row['line'],
						'href' => getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'errorlog', 'activity' => 'file', 'err' => $row['id_error']]),
						'link' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'errorlog', 'activity' => 'file', 'err' => $row['id_error']]) . '" onclick="return reqWin(this.href, 600, 480, false);">' . $row['file'] . '</a>',
						'search' => base64_encode($row['file']),
					];
				}

				// Make a list of members to load later.
				$log['members'][$row['id_member']] = $row['id_member'];
			}
		);

		return ($log);
	}

	/**
	 * Fetches errors and group them by error type
	 *
	 * @param array|null $filter
	 * @param string|null $sort
	 *
	 * @return array
	 */
	public function fetchErrorsByType($filter = null, $sort = null): array
	{
		global $txt;

		$sum = 0;
		$types = [];

		// What type of errors do we have and how many do we have?
		$this->_db->fetchQuery('
			SELECT 
				error_type, COUNT(*) AS num_errors
			FROM {db_prefix}log_errors
			GROUP BY error_type
			ORDER BY error_type = {string:critical_type} DESC, error_type ASC',
			[
				'critical_type' => 'critical',
			]
		)->fetch_callback(
			function ($row) use (&$types, $filter, $txt, $sort, &$sum) {
				// Total errors so far?
				$sum += $row['num_errors'];

				$types[$sum] = [
					'label' => ($txt['errortype_' . $row['error_type']] ?? $row['error_type']) . ' (' . $row['num_errors'] . ')',
					'description' => $txt['errortype_' . $row['error_type'] . '_desc'] ?? '',
					'url' => getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'errorlog'] + ($sort === null || $sort === 'down' ? ['desc'] : []) + ['filter' => 'error_type', 'value' => $row['error_type']]),
					'is_selected' => !empty($filter) && $filter['value']['sql'] == $this->_db->escape_wildcard_string($row['error_type']),
				];
			}
		);

		return $types;
	}
}
