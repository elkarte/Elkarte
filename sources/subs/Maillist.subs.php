<?php

/**
 * All of the helper functions for use by the maillist controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
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
	global $txt, $boardurl, $user_info;

	$db = database();

	// Init
	$i = 0;
	$sort = empty($sort) ? 'id_email DESC' : $sort;
	$postemail = array();
	require_once(SUBSDIR . '/Emailpost.subs.php');

	// Where can they approve items?
	$approve_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');

	// Work out the query
	if ($approve_boards == array(0))
		$approve_query = '';
	elseif (!empty($approve_boards))
		$approve_query = ' AND e.id_board IN (' . implode(',', $approve_boards) . ')';
	else
		$approve_query = ' AND 0';

	// Load them errors
	$request = $db->query('', '
		SELECT e.id_email, e.error, e.data_id, e.subject, e.id_message, e.email_from, e.message_type, e.message, e.id_board
		FROM {db_prefix}postby_emails_error e
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = e.id_board)
		WHERE id_email' . ($id === 0 ? ' > {int:id}' : ' = {int:id}') . '
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
	while ($row = $db->fetch_assoc($request))
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
			$postemail[$i]['link'] = $boardurl . '?msg=' . $row['id_message'];
		elseif ($row['message_type'] === 'p')
			$postemail[$i]['subject'] = $txt['private'];

		$i++;
	}
	$db->free_result($request);

	return $postemail;
}

/**
 * Counts the number of errors (the user can see) for pagination
 */
function list_maillist_count_unapproved()
{
	global $user_info;

	$db = database();

	$total = 0;

	// Where can they approve items?
	$approve_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');

	// Work out the query
	if ($approve_boards == array(0))
		$approve_query = '';
	elseif (!empty($approve_boards))
		$approve_query = ' AND e.id_board IN (' . implode(',', $approve_boards) . ')';
	else
		$approve_query = ' AND 0';

	// Get the total count of failed emails, needed for pages
	$request = $db->query('', '
		SELECT
			COUNT(*) as total
		FROM {db_prefix}postby_emails_error AS e
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = e.id_board)
		WHERE {query_see_board}
			' . $approve_query,
		array(
		)
	);
	list ($total) = $db->fetch_row($request);
	$db->free_result($request);

	return $total;
}

/**
 * Removes an single entry from the postby_emails_error table
 *
 * @param int $id
 */
function maillist_delete_error_entry($id)
{
	$db = database();

	// bye bye error log entry
	$db->query('', '
		DELETE FROM {db_prefix}postby_emails_error
		WHERE id_email = {int:id}',
		array(
			'id' => $id,
		)
	);
}

/**
 * Loads the filers or parsers for the post by email system
 *  - If an ID is supplied, it will load that specific filter/parser
 *  - Style defines if it will load parsers or filters
 *
 * @param int $start
 * @param int $chunk_size
 * @param string $sort
 * @param int $id
 * @param string $style
 */
function list_get_filter_parser($start, $chunk_size, $sort = '', $id = 0, $style = 'filter')
{
	$db = database();

	// Init
	if (empty($sort))
		$sort = 'id_filter ASC';

	// Define $email_filters
	$email_filters = array();

	// Load all the email_filters, we need lots of these :0
	$request = $db->query('', '
		SELECT id_filter, filter_style, filter_type, filter_to, filter_from, filter_name, filter_order
		FROM {db_prefix}postby_emails_filters
		WHERE id_filter' . (($id == 0) ? ' > {int:id}' : ' = {int:id}') . '
			AND filter_style = {string:style}
		ORDER BY {raw:sort}, filter_type ASC, filter_order ASC
		' . ((!empty($chunk_size)) ? 'LIMIT {int:offset}, {int:limit} ' : ''),
		array(
			'offset' => $start,
			'limit' => $chunk_size,
			'sort' => $sort,
			'id' => $id,
			'style' => $style
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		$email_filters[$row['id_filter']] = array(
			'id_filter' => $row['id_filter'],
			'filter_type' => $row['filter_type'],
			'filter_to' => '<strong>"</strong>' . Util::htmlspecialchars($row['filter_to']) . '<strong>"</strong>',
			'filter_from' => '<strong>"</strong>' . Util::htmlspecialchars($row['filter_from']) . '<strong>"</strong>',
			'filter_name' => Util::htmlspecialchars($row['filter_name']),
			'filter_order' => $row['filter_order'],
		);
	}
	$db->free_result($request);

	return $email_filters;
}

/**
 * Get the count of the filters or parsers of the system
 * If ID is 0, it will retrieve the count.
 * If ID is a valid positive integer, it will return 1 and exit.
 *
 * @param int $id
 * @param string $style
 * @return
 */
function list_count_filter_parser($id, $style)
{
	$db = database();

	$total = 0;

	// Get the total filter count, needed for pages
	$request = $db->query('', '
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
	list ($total) = $db->fetch_row($request);
	$db->free_result($request);

	return $total;
}

/**
 * Loads a specific filter/parser from the database for display
 * It will load only that filter/parser
 *
 * @param int $id
 * @param string $style parser or filter
 * @return array of filters/parsers
 */
function maillist_load_filter_parser($id, $style)
{
	$db = database();

	$row = array();

	// Load filter/parser details for editing
	$request = $db->query('', '
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
	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	// Check that the filter does exist
	if (empty($row))
		fatal_lang_error('email_error_no_filter');

	return $row;
}

/**
 * Removes a specific filter or parser from the system
 *
 * @param int $id ID of the filter/parser
 */
function maillist_delete_filter_parser($id)
{
	$db = database();

	// Delete the rows from the database for the filter selected
	$db->query('', '
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
 *  - Sets the first one as a blank for use in a template select element
 */
function maillist_board_list()
{
	$db = database();

	// Get the board and the id's, we need these for the templates
	$request = $db->query('', '
		SELECT id_board, name
		FROM {db_prefix}boards
		WHERE id_board > {int:zero}',
		array(
			'zero' => 0,
		)
	);
	$result = array();
	$result[0] = '';
	while ($row = $db->fetch_row($request))
		$result[$row[0]] = $row[1];
	$db->free_result($request);

	return $result;
}

/**
 * Turns on or off the "fake" cron job for imap email retrieval
 *
 * @param boolean $switch
 */
function enable_maillist_imap_cron($switch)
{
	$db = database();

	// Enable or disable the fake cron
	$db->query('', '
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

/**
 * Load in the custom (public and this users private) email templates
 *
 * @param string $template_type - the type of template (e.g. 'bounce', 'warntpl', etc.)
 * @param string $subject - A subject for the template
 */
function maillist_templates($template_type, $subject = null)
{
	global $user_info;

	$db = database();

	$notification_templates = array();

	$request = $db->query('', '
		SELECT recipient_name AS template_title, body
		FROM {db_prefix}log_comments
		WHERE comment_type = {string:tpltype}
			AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
		array(
			'tpltype' => $template_type,
			'generic' => 0,
			'current_member' => $user_info['id'],
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		$template = array(
			'title' => $row['template_title'],
			'body' => $row['body'],
		);

		if ($subject !== null)
			$template['subject'] = $subject;

		$notification_templates[] = $template;
	}
	$db->free_result($request);

	return $notification_templates;
}

/**
 * Log in post-by emails an email being sent
 *
 * @param array $sent
 */
function log_email($sent)
{
	$db = database();

	$db->insert('ignore',
		'{db_prefix}postby_emails',
		array(
			'id_email' => 'int', 'time_sent' => 'string', 'email_to' => 'string'
		),
		$sent,
		array('id_email')
	);
}

/**
 * Updates the processing order for the parser and filter fields
 * Done as a CASE WHEN one two three ELSE 0 END in place of many updates\
 * Called by Xmlcontroller as part of drag sort event
 *
 * @param string $replace constucted as WHEN fieldname=value THEN new viewvalue WHEN .....
 * @param array $filters list of ids in the WHEN clause to keep from updating the entire table
 */
function updateParserFilterOrder($replace, $filters)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}postby_emails_filters
		SET filter_order = CASE ' . $replace . ' ELSE filter_order END
		WHERE id_filter IN ({array_int:filters})',
		array(
			'filters' => $filters,
		)
	);
}