<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

/**
 * Delete all or some of the errors in the error log.
 * It applies any necessary filters to deletion.
 * This should only be called by ManageErrors::action_log().
 * It attempts to TRUNCATE the table to reset the auto_increment.
 * Redirects back to the error log when done.
 */
function deleteErrors($type, $filter)
{
	global $smcFunc;

	// Make sure the session exists and is correct; otherwise, might be a hacker.
	checkSession();
	validateToken('admin-el');

	// Delete all or just some?
	if ($type == 'delall' && !isset($filter))
		$smcFunc['db_query']('truncate_table', '
			TRUNCATE {db_prefix}log_errors',
			array(
			)
		);
	// Deleting all with a filter?
	elseif ($type == 'delall' && isset($filter))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_errors
			WHERE ' . $filter['variable'] . ' LIKE {string:filter}',
			array(
				'filter' => $filter['value']['sql'],
			)
		);
	// Just specific errors?
	elseif ($type == 'delete')
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_errors
			WHERE id_error IN ({array_int:error_list})',
			array(
				'error_list' => array_unique($_POST['delete']),
			)
		);

		// Go back to where we were.
		redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : '') . ';start=' . $_GET['start'] . (isset($filter) ? ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'] : ''));
	}

	// Back to the error log!
	redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));
}

function numErrors()
{
	global $smcFunc;

	// Just how many errors are there?
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_errors' . (isset($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : ''),
		array(
			'filter' => isset($filter) ? $filter['value']['sql'] : '',
		)
	);
	list ($num_errors) = $smcFunc['db_fetch_row']($result);

	$smcFunc['db_free_result']($result);

	return $num_errors;
}