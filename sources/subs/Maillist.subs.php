<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Loads failed emails from the database
 *  - If its a message or topic will build the link to that for viewing
 *  - If supplied a specific ID will load only that failed email
 *
 * @param int $start
 * @param int $chunk_size
 * @param string $sort
 * @param int $id
 */
function list_maillist_unapproved($start, $chunk_size, $sort = '', $id = 0)
{
	global $smcFunc, $txt, $boardurl;

	// Init
	$i = 0;
	$sort = empty($sort) ? 'id_email DESC' : $sort;
	$postemail = array();
	require_once(SUBSDIR . '/Emailpost.subs.php');

	// Where can they approve items?
	$approve_boards = boardsAllowedTo('approve_posts');

	// Work out the query
	if ($approve_boards == array(0))
		$approve_query = '';
	elseif (!empty($approve_boards))
		$approve_query = ' AND e.id_board IN (' . implode(',', $approve_boards) . ')';
	else
		$approve_query = ' AND 0';

	// Load them errors
	$request = $smcFunc['db_query']('', '
		SELECT e.id_email, e.error, e.data_id, e.subject, e.id_message, e.email_from, e.message_type, e.message, e.id_board
		FROM {db_prefix}postby_emails_error e
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = e.id_board)
		WHERE id_email' . ($id === 0 ? '> {int:id}' : '= {int:id}') . '
			AND ({query_see_board}
				' . $approve_query . ')
			OR e.id_board = -1
		ORDER BY {raw:sort}
		' . ((!empty($chunk_size)) ? 'LIMIT {int:offset}, {int:limit} ' : ''),
		array(
			'offset' => $start,
			'limit' => $chunk_size,
			'sort' => $sort,
			'id' => $id,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$postemail[$i] = array(
			'id_email' => $row['id_email'],
			'error' =>  $txt[$row['error'] . '_short'],
			'error_code' => $row['error'],
			'key' => $row['data_id'],
			'subject' => $row['subject'],
			'message' => $row['id_message'],
			'from' => $row['email_from'],
			'type' => $row['message_type'],
			'body' => $row['message'],
			'link' => '#',
		);

		// Sender details we can use
		$temp = query_load_user_info($row['email_from']);
		$postemail[$i]['name'] = !empty($temp['user_info']['name']) ? $temp['user_info']['name']: '';
		$postemail[$i]['language'] = !empty($temp['user_info']['language']) ? $temp['user_info']['language']: '';

		// Build a link to the topic or message in case someone wants to take a look at that thread
		if ($row['message_type'] === 't')
			$postemail[$i]['link'] = $boardurl . '?topic=' . $row['id_message'];
		elseif ($row['message_type'] === 'm')
			$postemail[$i]['link'] = $boardurl . '?topic=' . $topic_id . '.msg' . $row['id_message'] . '#msg' . $row['id_message'];
		elseif ($row['message_type'] === 'p')
			$postemail[$i]['subject'] = $txt['private'];

		$i++;
	}
	$smcFunc['db_free_result']($request);

	return $postemail;
}

/**
 * Counts the number of errors (the user can see) for pagination
 *
 * @param int $id
 * @param int $id
 */
function list_maillist_count_unapproved()
{
	global $smcFunc;

	$total= 0;

	// Where can they approve items?
	$approve_boards = boardsAllowedTo('approve_posts');

	// Work out the query
	if ($approve_boards == array(0))
		$approve_query = '';
	elseif (!empty($approve_boards))
		$approve_query = ' AND e.id_board IN (' . implode(',', $approve_boards) . ')';
	else
		$approve_query = ' AND 0';

	// Get the total count of failed emails, needed for pages
	$request = $smcFunc['db_query']('', '
		SELECT
			COUNT(*) as total
		FROM {db_prefix}postby_emails_error AS e
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = e.id_board)
		WHERE {query_see_board}
			' . $approve_query,
		array(
		)
	);
	list ($total) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $total;
}

/**
 * Removes an single entry from the postby_emails_error table
 *
 * @param type $id
 */
function maillist_delete_entry($id)
{
	global $smcFunc;

	// bye bye error log entry
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}postby_emails_error
		WHERE id_email = {int:id}',
		array(
			'id' => $id,
		)
	);
}

/**
 * Loads the filers or parsers for the post by email system
 *  - If supplied an ID will load that specific filter/parser
 *	- Style defines if it will load parsers or filters
 *
 * @param int type $start
 * @param int $chunk_size
 * @param string $sort
 * @param int $id
 * @param string $style
 * @return type
 */
function list_get_filter_parser($start, $chunk_size, $sort = '', $id = 0, $style = 'filter')
{
	global $smcFunc;

	// init
	$i = 0;
	if (empty($sort))
		$sort = 'id_filter ASC';

	// Define $email_filters
	$email_filters = array();

	// Load all the email_filters, we need lots of these :0
	$request = $smcFunc['db_query']('', '
		SELECT *
		FROM {db_prefix}postby_emails_filters
		WHERE id_filter' . (($id == 0) ? ' > {int:id}' : ' = {int:id}') . '
			AND filter_style = {string:style}
		ORDER BY {raw:sort}
		' . ((!empty($chunk_size)) ? 'LIMIT {int:offset}, {int:limit} ' : ''),
		array(
			'offset' => $start,
			'limit' => $chunk_size,
			'sort' => $sort,
			'id' => $id,
			'style' => $style
		)
	);
	while($row = $smcFunc['db_fetch_assoc']($request))
	{
		$email_filters[$i] = array(
			'id_filter' => $row['id_filter'],
			'filter_type' => $row['filter_type'],
			'filter_to' => '<strong>"</strong>' . $smcFunc['htmlspecialchars']($row['filter_to']) . '<strong>"</strong>',
			'filter_from' => '<strong>"</strong>' . $smcFunc['htmlspecialchars']($row['filter_from']) . '<strong>"</strong>',
			'filter_name' => $smcFunc['htmlspecialchars']($row['filter_name']),
		);
		$i++;
	};
	$smcFunc['db_free_result']($request);

	return $email_filters;
}

/**
 * Get the count of the filters or parsers of the system
 *	- If supplied an ID will valid it exits and return 1
 *
 * @param int $id
 * @param string $style
 * @return
 */
function list_count_filter_parser($id, $style)
{
	global $smcFunc;

	$total = 0;

	// Get the total filter count, needed for pages
	$request = $smcFunc['db_query']('', '
		SELECT
			COUNT(*) as total
		FROM {db_prefix}postby_emails_filters
		WHERE id_filter' . (($id === 0) ? ' > {int:id}' : ' = {int:id}') . '
			AND filter_style = {string:style}',
		array(
			'id' => $id,
			'style' => $style
		)
	);
	list ($total) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $total;
}

/**
 * Loads a specific filter/parser from the database for display
 *	- If supplied an ID will load just that filter/parser
 *
 * @param type $id
 * @param type $style
 * @return array of filters/parsers
 */
function maillist_load_filter_parser($id, $style)
{
	global $smcFunc;

	$row = array();

	// Load filter/parser details for editing
	$request = $smcFunc['db_query']('', '
		SELECT *
		FROM {db_prefix}postby_emails_filters
		WHERE id_filter = {int:id}
			AND filter_style = {string:style}
		LIMIT 1',
		array(
			'id' => $id,
			'style' => $style
		)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Check that the filter does exist
	if (empty($row))
		fatal_lang_error('email_error_no_filter');

	return $row;
}

/**
 * Removes a specific filter or parser from the system
 *
 * @param type $id
 */
function maillist_delete_filter_parser($id)
{
	global $smcFunc;

	// Delete the rows from the database for the filter selected
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}postby_emails_filters
		WHERE id_filter = {int:id}',
		array(
			'id' => $id
		)
	);

	return;
}

/**
 * Creates a select list of boards for the admin
 *  - sets the first one as a blank for use in a template select element
 *
 * @global type $smcFunc
 * @return type
 */
function maillist_board_list()
{
	global $smcFunc;

	// Get the board and the id's, we need these for the templates
	$request = $smcFunc['db_query']('', '
		SELECT id_board, name
		FROM {db_prefix}boards
		WHERE id_board > {int:zero}',
		array(
			'zero' => 0,
		)
	);
	$result = array();
	$result[0] = '';
	while ($row = $smcFunc['db_fetch_row']($request))
		$result[$row[0]] = $row[1];
	$smcFunc['db_free_result']($request);

	return $result;
}

/**
 * Similar in construction to saveDBSettings,
 * - saves the config_vars to a specified table
 * - var names are considered the col names,
 * - values are cast per config vars
 * - if editing a row, the primary col index and existing index value must be
 *   supplied, otherwise a new row will be added
 *
 * @param array $config_vars
 * @param string $tablename
 * @param array $index
 * @param integer $editid
 * @param string $editname
 */
function saveTableSettings($config_vars, $tablename, $index = array(), $editid = -1, $editname = '')
{
	global $smcFunc;

	// Init
	$insert_type = array();
	$insert_value = array();

	// Cast all the config vars as defined
	foreach ($config_vars as $var)
	{
		if (!isset($var[1]) || (!isset($_POST[$var[1]]) && $var[0] !== 'check'))
			continue;

		// Checkboxes ...
		elseif ($var[0] === 'check')
		{
			$insert_type[$var[1]] = 'int';
			$insert_value[] = !empty($_POST[$var[1]]) ? 1 : 0;
		}
		// Or maybe even select boxes
		elseif ($var[0] === 'select' && in_array($_POST[$var[1]], array_keys($var[2])))
		{
			$insert_type[$var[1]] = 'string';
			$insert_value[] = $_POST[$var[1]];
		}
		elseif ($var[0] === 'select' && !empty($var['multiple']) && array_intersect($_POST[$var[1]], array_keys($var[2])) != array())
		{
			// For security purposes we need to validate this line by line.
			$options = array();
			foreach ($_POST[$var[1]] as $invar)
				if (in_array($invar, array_keys($var[2])))
					$options[] = $invar;

			$insert_type[$var[1]] = 'string';
			$insert_value[] = serialize($options);
		}
		// Integers are fundamental
		elseif ($var[0] == 'int')
		{
			$insert_type[$var[1]] = 'int';
			$insert_value[] = (int) $_POST[$var[1]];
		}
		// Floating points are easy
		elseif ($var[0] === 'float')
		{
			$insert_type[$var[1]] = 'float';
			$insert_value[] = (float) $_POST[$var[1]];
		}
		// Text is fine too!
		elseif ($var[0] === 'text' || $var[0] === 'large_text')
		{
			$insert_type[$var[1]] = 'string';
			$insert_value[] = $_POST[$var[1]];
		}
	}

	// Everything is now set so is this a new row or an edit?
	if ($editid !== -1 && !empty($editname))
	{
		// Time to edit, add in the id col name, assumed to be primary/unique!
		$insert_type[$editname] = 'int';
		$insert_value[] = $editid;
	}

	// Do it !!
	$smcFunc['db_insert']('replace',
		'{db_prefix}' . $tablename,
		$insert_type,
		$insert_value,
		$index
	);
}

/**
 * Turns on or off the "fake" cron job for imap email retrieval
 *
 * @global type $smcFunc
 * @param type $switch
 */
function enable_maillist_imap_cron($switch)
{
	global $smcFunc;

	// Enable or disable the fake cron
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}scheduled_tasks
		SET disabled = {int:onoff}, next_time = {int:time}
		WHERE task = {string:name}',
		array(
			'name' => 'pbeIMAP',
			'onoff' => empty($switch) ? 1 : 0,
			'time' => time() + (5 * 60),
		)
	);
}