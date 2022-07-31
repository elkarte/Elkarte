<?php

/**
 * Moderation helper functions.
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
use ElkArte\Cache\Cache;
use ElkArte\User;
use ElkArte\Util;

/**
 * How many open reports do we have?
 *  - if flush is true will clear the moderator menu count
 *  - returns the number of open reports
 *  - sets $context['open_mod_reports'] for template use
 *
 * @param bool $flush = true if moderator menu count will be cleared
 * @param bool $count_pms Default false, if false returns the number of message
 *                           reports, if true sets $context['open_pm_reports'] and
 *                           returns the both number of open PM and message reports
 *
 * @return array
 */
function recountOpenReports($flush = true, $count_pms = false)
{
	global $context;

	$db = database();

	$open_reports = array(
		'msg' => 0,
		'pm' => 0,
	);
	$db->fetchQuery('
		SELECT 
			type, COUNT(*) as num_reports
		FROM {db_prefix}log_reported
		WHERE ' . User::$info->mod_cache['bq'] . '
			AND type IN ({array_string:rep_type})
			AND closed = {int:not_closed}
			AND ignore_all = {int:not_ignored}
		GROUP BY type',
		array(
			'not_closed' => 0,
			'not_ignored' => 0,
			'rep_type' => array('pm', 'msg'),
		)
	)->fetch_callback(
		function ($row) use (&$open_reports) {
			$open_reports[$row['type']] = $row['num_reports'];
		}
	);

	if ($count_pms !== true)
	{
		$open_reports['pm'] = 0;
	}

	$_SESSION['rc'] = array(
		'id' => User::$info->id,
		'time' => time(),
		'reports' => $open_reports['msg'],
		'pm_reports' => $open_reports['pm'],
	);

	// Safety net, even though this (and the above)  should not be done here at all.
	$context['open_mod_reports'] = $open_reports['msg'];
	$context['open_pm_reports'] = $open_reports['pm'];

	if ($flush)
	{
		Cache::instance()->remove('num_menu_errors');
	}

	return $open_reports;
}

/**
 * How many unapproved posts and topics do we have?
 *  - Sets $context['total_unapproved_topics']
 *  - Sets $context['total_unapproved_posts']
 *  - approve_query is set to list of boards they can see
 *
 * @param string|null $approve_query
 * @return array of values
 */
function recountUnapprovedPosts($approve_query = null)
{
	global $context;

	$db = database();

	if ($approve_query === null)
	{
		return array('posts' => 0, 'topics' => 0);
	}

	// Any unapproved posts?
	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_first_msg != m.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE m.approved = {int:not_approved}
			AND {query_see_board}
			' . $approve_query,
		array(
			'not_approved' => 0,
		)
	);
	list ($unapproved_posts) = $request->fetch_row();
	$request->free_result();

	// What about topics?
	$request = $db->query('', '
		SELECT 
			COUNT(m.id_topic)
		FROM {db_prefix}topics AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.approved = {int:not_approved}
			AND {query_see_board}
			' . $approve_query,
		array(
			'not_approved' => 0,
		)
	);
	list ($unapproved_topics) = $request->fetch_row();
	$request->free_result();

	$context['total_unapproved_topics'] = $unapproved_topics;
	$context['total_unapproved_posts'] = $unapproved_posts;

	return array('posts' => $unapproved_posts, 'topics' => $unapproved_topics);
}

/**
 * How many failed emails (that they can see) do we have?
 *
 * @param string|null $approve_query
 *
 * @return int
 */
function recountFailedEmails($approve_query = null)
{
	global $context;

	$db = database();

	if ($approve_query === null)
	{
		return 0;
	}

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}postby_emails_error AS m
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE {query_see_board}
			' . $approve_query . '
			OR m.id_board = -1',
		array()
	);
	list ($failed_emails) = $request->fetch_row();
	$request->free_result();

	$context['failed_emails'] = $failed_emails;

	return $failed_emails;
}

/**
 * How many entries are we viewing?
 *
 * @param int $status
 * @param bool $show_pms
 *
 * @return int
 */
function totalReports($status = 0, $show_pms = false)
{
	$db = database();

	$request = $db->fetchQuery('
		SELECT 
			COUNT(*)
		FROM {db_prefix}log_reported AS lr
		WHERE lr.closed = {int:view_closed}
			AND lr.type IN ({array_string:type})
			AND ' . (User::$info->mod_cache['bq'] == '1=1' || User::$info->mod_cache['bq'] == '0=1' ? User::$info->mod_cache['bq'] : 'lr.' . User::$info->mod_cache['bq']),
		array(
			'view_closed' => $status,
			'type' => $show_pms ? array('pm') : array('msg'),
		)
	);
	list ($total_reports) = $request->fetch_row();
	$request->free_result();

	return $total_reports;
}

/**
 * Changes a property of all the reports passed (and the user can see)
 *
 * @param int[]|int $reports_id an array of report IDs
 * @param string $property the property to update ('close' or 'ignore')
 * @param int $status the status of the property (mainly: 0 or 1)
 *
 * @return int
 */
function updateReportsStatus($reports_id, $property = 'close', $status = 0)
{
	if (empty($reports_id))
	{
		return 0;
	}

	$db = database();

	$reports_id = is_array($reports_id) ? $reports_id : array($reports_id);

	$request = $db->query('', '
		UPDATE {db_prefix}log_reported
		SET 
			' . ($property == 'close' ? 'closed' : 'ignore_all') . '= {int:status}
		WHERE id_report IN ({array_int:report_list})
			AND ' . User::$info->mod_cache['bq'],
		array(
			'report_list' => $reports_id,
			'status' => $status,
		)
	);

	return $request->affected_rows();
}

/**
 * Loads the number of items awaiting moderation attention
 *  - Only loads the value a given permission level can see
 *  - If supplied a board number will load the values only for that board
 *  - Unapproved posts
 *  - Unapproved topics
 *  - Unapproved attachments
 *  - Failed emails
 *  - Reported posts
 *  - Members awaiting approval (activation, deletion, group requests)
 *
 * @param int|null $brd
 *
 * @return mixed
 */
function loadModeratorMenuCounts($brd = null)
{
	global $modSettings;

	static $menu_errors = array();

	// Work out what boards they can work in!
	$approve_boards = !empty(User::$info->mod_cache['ap']) ? User::$info->mod_cache['ap'] : boardsAllowedTo('approve_posts');

	// Supplied a specific board to check?
	if (!empty($brd))
	{
		$filter_board = array((int) $brd);
		$approve_boards = $approve_boards == array(0) ? $filter_board : array_intersect($approve_boards, $filter_board);
	}

	// Work out the query
	if ($approve_boards == array(0))
	{
		$approve_query = '';
	}
	elseif (!empty($approve_boards))
	{
		$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
	}
	else
	{
		$approve_query = ' AND 1=0';
	}

	// Set up the cache key for this permissions level
	$cache_key = md5(User::$info->query_see_board . $approve_query . User::$info->mod_cache['bq'] . User::$info->mod_cache['gq'] . User::$info->mod_cache['mq'] . (int) allowedTo('approve_emails') . '_' . (int) allowedTo('moderate_forum'));

	if (isset($menu_errors[$cache_key]))
	{
		return $menu_errors[$cache_key];
	}

	// If its been cached, guess what, that's right use it!
	$temp = Cache::instance()->get('num_menu_errors', 900);

	if ($temp === null || !isset($temp[$cache_key]))
	{
		// Starting out with nothing is a good start
		$menu_errors[$cache_key] = array(
			'memberreq' => 0,
			'groupreq' => 0,
			'attachments' => 0,
			'reports' => 0,
			'emailmod' => 0,
			'postmod' => 0,
			'topics' => 0,
			'posts' => 0,
			'pm_reports' => 0,
		);

		if ($modSettings['postmod_active'] && !empty($approve_boards))
		{
			$totals = recountUnapprovedPosts($approve_query);
			$menu_errors[$cache_key]['posts'] = $totals['posts'];
			$menu_errors[$cache_key]['topics'] = $totals['topics'];

			// Totals for the menu item unapproved posts and topics
			$menu_errors[$cache_key]['postmod'] = $menu_errors[$cache_key]['topics'] + $menu_errors[$cache_key]['posts'];
		}

		// Attachments
		if ($modSettings['postmod_active'] && !empty($approve_boards))
		{
			require_once(SUBSDIR . '/ManageAttachments.subs.php');
			$menu_errors[$cache_key]['attachments'] = list_getNumUnapprovedAttachments($approve_query);
		}

		// Reported posts (and PMs?)
		if (!empty(User::$info->mod_cache) && User::$info->mod_cache['bq'] != '0=1')
		{
			$reports = recountOpenReports(false, allowedTo('admin_forum'));
			$menu_errors[$cache_key]['reports'] = $reports['msg'];

			// Reported PMs
			if (!empty($reports['pm']))
			{
				$menu_errors[$cache_key]['pm_reports'] = $reports['pm'];
			}
		}

		// Email failures that require attention
		if (!empty($modSettings['maillist_enabled']) && allowedTo('approve_emails'))
		{
			$menu_errors[$cache_key]['emailmod'] = recountFailedEmails($approve_query);
		}

		// Group requests
		if (!empty(User::$info->mod_cache) && User::$info->mod_cache['gq'] != '0=1')
		{
			$menu_errors[$cache_key]['groupreq'] = count(groupRequests());
		}

		// Member requests
		if (allowedTo('moderate_forum') && ((!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 2) || !empty($modSettings['approveAccountDeletion'])))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$awaiting_activation = 0;
			$activation_numbers = countInactiveMembers();

			// 5 = COPPA, 4 = Awaiting Deletion, 3 = Awaiting Approval
			foreach ($activation_numbers as $activation_type => $total_members)
			{
				if (in_array($activation_type, array(3, 4, 5)))
				{
					$awaiting_activation += $total_members;
				}
			}
			$menu_errors[$cache_key]['memberreq'] = $awaiting_activation;
		}

		// Grand Totals for the top most menus
		$menu_errors[$cache_key]['pt_total'] = $menu_errors[$cache_key]['emailmod'] + $menu_errors[$cache_key]['postmod'] + $menu_errors[$cache_key]['reports'] + $menu_errors[$cache_key]['attachments'] + $menu_errors[$cache_key]['pm_reports'];
		$menu_errors[$cache_key]['mg_total'] = $menu_errors[$cache_key]['memberreq'] + $menu_errors[$cache_key]['groupreq'];
		$menu_errors[$cache_key]['grand_total'] = $menu_errors[$cache_key]['pt_total'] + $menu_errors[$cache_key]['mg_total'];

		// Add this key in to the array, technically this resets the cache time for all keys
		// done this way as the entire thing needs to go null once *any* moderation action is taken
		$menu_errors = is_array($temp) ? array_merge($temp, $menu_errors) : $menu_errors;

		// Store it away for a while, not like this should change that often
		Cache::instance()->put('num_menu_errors', $menu_errors, 900);
	}
	else
	{
		$menu_errors = $temp ?? array();
	}

	return $menu_errors[$cache_key];
}

/**
 * Log a warning notice.
 *
 * @param string $subject
 * @param string $body
 * @return int
 */
function logWarningNotice($subject, $body)
{
	$db = database();

	// Log warning notice.
	$db->insert('',
		'{db_prefix}log_member_notices',
		array(
			'subject' => 'string-255', 'body' => 'string-65534',
		),
		array(
			Util::htmlspecialchars($subject), Util::htmlspecialchars($body),
		),
		array('id_notice')
	);

	return (int) $db->insert_id('{db_prefix}log_member_notices');
}

/**
 * Logs the warning being sent to the user so other moderators can see
 *
 * @param int $memberID
 * @param string $real_name
 * @param int $id_notice
 * @param int $level_change
 * @param string $warn_reason
 */
function logWarning($memberID, $real_name, $id_notice, $level_change, $warn_reason)
{
	$db = database();

	$db->insert('',
		'{db_prefix}log_comments',
		array(
			'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int', 'recipient_name' => 'string-255',
			'log_time' => 'int', 'id_notice' => 'int', 'counter' => 'int', 'body' => 'string-65534',
		),
		array(
			User::$info->id, User::$info->name, 'warning', $memberID, $real_name,
			time(), $id_notice, $level_change, $warn_reason,
		),
		array('id_comment')
	);
}

/**
 * Removes a custom moderation center template from log_comments
 *  - Logs the template removal action for each warning affected
 *  - Removes the details for all warnings that used the template being removed
 *
 * @param int $id_tpl id of the template to remove
 * @param string $template_type type of template, defaults to warntpl
 */
function removeWarningTemplate($id_tpl, $template_type = 'warntpl')
{
	$db = database();

	// Log the actions.
	$db->fetchQuery('
		SELECT 
			recipient_name
		FROM {db_prefix}log_comments
		WHERE id_comment IN ({array_int:delete_ids})
			AND comment_type = {string:tpltype}
			AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
		array(
			'delete_ids' => $id_tpl,
			'tpltype' => $template_type,
			'generic' => 0,
			'current_member' => User::$info->id,
		)
	)->fetch_callback(
		function ($row) {
			logAction('delete_warn_template', array('template' => $row['recipient_name']));
		}
	);

	// Do the deletes.
	$db->query('', '
		DELETE FROM {db_prefix}log_comments
		WHERE id_comment IN ({array_int:delete_ids})
			AND comment_type = {string:tpltype}
			AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
		array(
			'delete_ids' => $id_tpl,
			'tpltype' => $template_type,
			'generic' => 0,
			'current_member' => User::$info->id,
		)
	);
}

/**
 * Returns all the templates of a type from the system.
 * (used by createList() callbacks)
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param string $template_type type of template to load
 *
 * @return array
 */
function warningTemplates($start, $items_per_page, $sort, $template_type = 'warntpl')
{
	$db = database();

	$templates = array();
	$db->query('', '
		SELECT 
			lc.id_comment, COALESCE(mem.id_member, 0) AS id_member,
			COALESCE(mem.real_name, lc.member_name) AS creator_name, recipient_name AS template_title,
			lc.log_time, lc.body
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
		WHERE lc.comment_type = {string:tpltype}
			AND (id_recipient = {string:generic} OR id_recipient = {int:current_member})
		ORDER BY ' . $sort . '
		LIMIT ' . $items_per_page . '  OFFSET ' . $start,
		array(
			'tpltype' => $template_type,
			'generic' => 0,
			'current_member' => User::$info->id,
		)
	)->fetch_callback(
		function ($row) use (&$templates) {
			global $scripturl;

			$templates[] = array(
				'id_comment' => $row['id_comment'],
				'creator' => $row['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['creator_name'] . '</a>') : $row['creator_name'],
				'time' => standardTime($row['log_time']),
				'html_time' => htmlTime($row['log_time']),
				'timestamp' => forum_time(true, $row['log_time']),
				'title' => $row['template_title'],
				'body' => Util::htmlspecialchars($row['body']),
			);
		}
	);

	return $templates;
}

/**
 * Get the number of templates of a type in the system
 *  - Loads the public and users private templates
 *  - Loads warning templates by default
 *  (used by createList() callbacks)
 *
 * @param string $template_type
 *
 * @return int
 */
function warningTemplateCount($template_type = 'warntpl')
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}log_comments
		WHERE comment_type = {string:tpltype}
			AND (id_recipient = {string:generic} OR id_recipient = {int:current_member})',
		array(
			'tpltype' => $template_type,
			'generic' => 0,
			'current_member' => User::$info->id,
		)
	);
	list ($totalWarns) = $request->fetch_row();
	$request->free_result();

	return $totalWarns;
}

/**
 * Get all issued warnings in the system given the specified query parameters
 *
 * Callback for createList() in \ElkArte\Controller\ModerationCenter::action_viewWarningLog().
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param string|null $query_string
 * @param mixed[] $query_params
 *
 * @return array
 */
function warnings($start, $items_per_page, $sort, $query_string = '', $query_params = array())
{
	$db = database();

	$warnings = array();
	$db->fetchQuery('
		SELECT 
			COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name_col,
			COALESCE(mem2.id_member, 0) AS id_recipient, COALESCE(mem2.real_name, lc.recipient_name) AS recipient_name,
			lc.log_time, lc.body, lc.id_notice, lc.counter
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = lc.id_recipient)
		WHERE lc.comment_type = {string:warning}' . (!empty($query_string) ? '
			AND ' . $query_string : '') . '
		ORDER BY ' . $sort . '
		LIMIT ' . $items_per_page . '  OFFSET ' . $start,
		array_merge($query_params, array(
			'warning' => 'warning',
		))
	)->fetch_callback(
		function ($row) use (&$warnings) {
			global $scripturl;

			$warnings[] = array(
				'issuer_link' => $row['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['member_name_col'] . '</a>') : $row['member_name_col'],
				'recipient_link' => $row['id_recipient'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_recipient'] . '">' . $row['recipient_name'] . '</a>') : $row['recipient_name'],
				'time' => standardTime($row['log_time']),
				'html_time' => htmlTime($row['log_time']),
				'timestamp' => forum_time(true, $row['log_time']),
				'reason' => $row['body'],
				'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
				'id_notice' => $row['id_notice'],
			);
		}
	);

	return $warnings;
}

/**
 * Get the count of all current warnings.
 *
 * Callback for createList() in \ElkArte\Controller\ModerationCenter::action_viewWarningLog().
 *
 * @param string|null $query_string
 * @param mixed[] $query_params
 *
 * @return int
 */
function warningCount($query_string = '', $query_params = array())
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = lc.id_recipient)
		WHERE comment_type = {string:warning}' . (!empty($query_string) ? '
			AND ' . $query_string : ''),
		array_merge($query_params, array(
			'warning' => 'warning',
		))
	);
	list ($totalWarns) = $request->fetch_row();
	$request->free_result();

	return $totalWarns;
}

/**
 * Loads a moderation template in to context for use in editing a template
 *
 * @param int $id_template
 * @param string $template_type
 */
function modLoadTemplate($id_template, $template_type = 'warntpl')
{
	$db = database();

	$db->fetchQuery('
		SELECT 
			id_member, id_recipient, recipient_name AS template_title, body
		FROM {db_prefix}log_comments
		WHERE id_comment = {int:id}
			AND comment_type = {string:tpltype}
			AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
		array(
			'id' => $id_template,
			'tpltype' => $template_type,
			'generic' => 0,
			'current_member' => User::$info->id,
		)
	)->fetch_callback(
		function ($row) {
			global $context;

			$context['template_data'] = array(
				'title' => $row['template_title'],
				'body' => Util::htmlspecialchars($row['body']),
				'personal' => $row['id_recipient'],
				'can_edit_personal' => $row['id_member'] == User::$info->id,
			);
		}
	);
}

/**
 * Updates an existing template or adds in a new one to the log comments table
 *
 * @param int $recipient_id
 * @param string $template_title
 * @param string $template_body
 * @param int $id_template
 * @param bool $edit true to update, false to insert a new row
 * @param string $type
 */
function modAddUpdateTemplate($recipient_id, $template_title, $template_body, $id_template, $edit = true, $type = 'warntpl')
{
	$db = database();

	if ($edit)
	{
		// Simple update...
		$db->query('', '
			UPDATE {db_prefix}log_comments
			SET 
				id_recipient = {int:personal}, recipient_name = {string:title}, body = {string:body}
			WHERE id_comment = {int:id}
				AND comment_type = {string:comment_type}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})' .
			($recipient_id ? ' AND id_member = {int:current_member}' : ''),
			array(
				'personal' => $recipient_id,
				'title' => $template_title,
				'body' => $template_body,
				'id' => $id_template,
				'comment_type' => $type,
				'generic' => 0,
				'current_member' => User::$info->id,
			)
		);
	}
	// Or inserting a new row
	else
	{
		$db->insert('',
			'{db_prefix}log_comments',
			array(
				'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int',
				'recipient_name' => 'string-255', 'body' => 'string-65535', 'log_time' => 'int',
			),
			array(
				User::$info->id, User::$info->name, $type, $recipient_id,
				$template_title, $template_body, time(),
			),
			array('id_comment')
		);
	}
}

/**
 * Get the report details, need this so we can limit access to a particular board
 *  - returns false if they are requesting a report they can not see or does not exist
 *
 * @param int $id_report
 * @param bool $show_pms
 *
 * @return bool
 */
function modReportDetails($id_report, $show_pms = false)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
			lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
			COALESCE(mem.real_name, lr.membername) AS author_name, COALESCE(mem.id_member, 0) AS id_author
		FROM {db_prefix}log_reported AS lr
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
		WHERE lr.id_report = {int:id_report}
			AND lr.type IN ({array_string:rep_type})
			AND ' . (User::$info->mod_cache['bq'] == '1=1' || User::$info->mod_cache['bq'] == '0=1' ? User::$info->mod_cache['bq'] : 'lr.' . User::$info->mod_cache['bq']) . '
		LIMIT 1',
		array(
			'id_report' => $id_report,
			'rep_type' => $show_pms ? array('pm') : array('msg'),
		)
	);
	// So did we find anything?
	if (!$request->num_rows())
	{
		$row = false;
	}
	else
	{
		$row = $request->fetch_assoc();
	}
	$request->free_result();

	return $row;
}

/**
 * Get the details for a bunch of open/closed reports
 *
 * @param int $status 0 => show open reports, 1 => closed reports
 * @param int $start starting point
 * @param int $limit the number of reports
 * @param bool $show_pms
 *
 * @return array
 * @todo move to createList?
 */
function getModReports($status = 0, $start = 0, $limit = 10, $show_pms = false)
{
	$db = database();

	$reports = array();
	$db->fetchQuery('
			SELECT 
				lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
				lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
				COALESCE(mem.real_name, lr.membername) AS author_name, COALESCE(mem.id_member, 0) AS id_author
			FROM {db_prefix}log_reported AS lr
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
			WHERE lr.closed = {int:view_closed}
				AND lr.type IN ({array_string:rep_type})
				AND ' . (User::$info->mod_cache['bq'] == '1=1' || User::$info->mod_cache['bq'] == '0=1' ? User::$info->mod_cache['bq'] : 'lr.' . User::$info->mod_cache['bq']) . '
			ORDER BY lr.time_updated DESC
			LIMIT {int:limit} OFFSET {int:start} ',
		array(
			'view_closed' => $status,
			'start' => $start,
			'limit' => $limit,
			'rep_type' => $show_pms ? array('pm') : array('msg'),
		)
	)->fetch_callback(
		function ($row) use (&$reports) {
			$reports[$row['id_report']] = $row;
		}
	);

	return $reports;
}

/**
 * Grabs all the comments made by the reporters to a set of reports
 *
 * @param int[] $id_reports an array of report ids
 *
 * @return array
 */
function getReportsUserComments($id_reports)
{
	$db = database();

	$id_reports = is_array($id_reports) ? $id_reports : array($id_reports);
	$comments = array();
	$db->fetchQuery('
		SELECT 
			lrc.id_comment, lrc.id_report, lrc.time_sent, lrc.comment, lrc.member_ip,
			COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lrc.membername) AS reporter
		FROM {db_prefix}log_reported_comments AS lrc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lrc.id_member)
		WHERE lrc.id_report IN ({array_int:report_list})',
		array(
			'report_list' => $id_reports,
		)
	)->fetch_callback(
		function ($row) use (&$comments) {
			$comments[$row['id_report']][] = $row;
		}
	);

	return $comments;
}

/**
 * Retrieve all the comments made by the moderators to a certain report
 *
 * @param int $id_report the id of a report
 *
 * @return array
 */
function getReportModeratorsComments($id_report)
{
	$db = database();

	$comments = array();
	$db->fetchQuery('
		SELECT 
			lc.id_comment, lc.id_notice, lc.log_time, lc.body,
			COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS moderator
		FROM {db_prefix}log_comments AS lc
		LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
		WHERE lc.id_notice = {int:id_report}
			AND lc.comment_type = {string:reportc}',
		array(
			'id_report' => $id_report,
			'reportc' => 'reportc',
		)
	)->fetch_callback(
		function ($row) use (&$comments) {
			$comments[] = $row;
		}
	);

	return $comments;
}

/**
 * This is a helper function: approve everything unapproved.
 * Used from moderation panel.
 */
function approveAllUnapproved()
{
	$db = database();

	// Start with messages and topics.
	$msgs = array();
	$db->fetchQuery('
		SELECT 
			id_msg
		FROM {db_prefix}messages
		WHERE approved = {int:not_approved}',
		array(
			'not_approved' => 0,
		)
	)->fetch_callback(
		function ($row) use (&$msgs) {
			$msgs[] = $row['id_msg'];
		}
	);

	if (!empty($msgs))
	{
		require_once(SUBSDIR . '/Post.subs.php');
		approvePosts($msgs);
		Cache::instance()->remove('num_menu_errors');
	}

	// Now do attachments
	$attaches = array();
	$db->fetchQuery('
		SELECT 
			id_attach
		FROM {db_prefix}attachments
		WHERE approved = {int:not_approved}',
		array(
			'not_approved' => 0,
		)
	)->fetch_callback(
		function ($row) use (&$attaches) {
			$attaches[] = $row['id_attach'];
		}
	);

	if (!empty($attaches))
	{
		require_once(SUBSDIR . '/ManageAttachments.subs.php');
		approveAttachments($attaches);
		Cache::instance()->remove('num_menu_errors');
	}
}

/**
 * Returns the number of watched users in the system.
 * (used by createList() callbacks).
 *
 * @param int $warning_watch
 * @return int
 */
function watchedUserCount($warning_watch = 0)
{
	$db = database();

	// @todo $approve_query is not used

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}members
		WHERE warning >= {int:warning_watch}',
		array(
			'warning_watch' => $warning_watch,
		)
	);
	list ($totalMembers) = $request->fetch_row();
	$request->free_result();

	return $totalMembers;
}

/**
 * Retrieved the watched users in the system.
 * (used by createList() callbacks).
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 *
 * @return array
 */
function watchedUsers($start, $items_per_page, $sort)
{
	global $modSettings;

	$db = database();

	$watched_users = array();
	$members = array();
	$db->query('', '
		SELECT 
			id_member, real_name, last_login, posts, warning
		FROM {db_prefix}members
		WHERE warning >= {int:warning_watch}
		ORDER BY {raw:sort}
		LIMIT ' . $items_per_page . '  OFFSET ' . $start,
		array(
			'warning_watch' => $modSettings['warning_watch'],
			'sort' => $sort,
		)
	)->fetch_callback(
		function ($row) use (&$watched_users, &$members) {
			global $txt;

			$watched_users[$row['id_member']] = array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'last_login' => $row['last_login'] ? standardTime($row['last_login']) : $txt['never'],
				'last_post' => $txt['not_applicable'],
				'last_post_id' => 0,
				'warning' => $row['warning'],
				'posts' => $row['posts'],
			);

			$members[] = $row['id_member'];
		}
	);

	if (!empty($members))
	{
		// First get the latest messages from these users.
		$latest_posts = array();
		$db->fetchQuery('
			SELECT 
				m.id_member, MAX(m.id_msg) AS last_post_id
			FROM {db_prefix}messages AS m' . (User::$info->query_see_board == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member IN ({array_int:member_list})' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}') . '
			GROUP BY m.id_member',
			array(
				'member_list' => $members,
				'is_approved' => 1,
			)
		)->fetch_callback(
			function ($row) use (&$latest_posts) {
				$latest_posts[$row['id_member']] = $row['last_post_id'];
			}
		);

		if (!empty($latest_posts))
		{
			// Now get the time those messages were posted.
			$db->fetchQuery('
				SELECT 
					id_member, poster_time
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:message_list})',
				array(
					'message_list' => $latest_posts,
				)
			)->fetch_callback(
				function ($row) use (&$watched_users, $latest_posts) {
					$watched_users[$row['id_member']]['last_post'] = standardTime($row['poster_time']);
					$watched_users[$row['id_member']]['last_post_id'] = $latest_posts[$row['id_member']];
				}
			);
		}

		$db->fetchQuery('
			SELECT 
				MAX(m.poster_time) AS last_post, MAX(m.id_msg) AS last_post_id, m.id_member
			FROM {db_prefix}messages AS m' . (User::$info->query_see_board == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member IN ({array_int:member_list})' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}') . '
			GROUP BY m.id_member',
			array(
				'member_list' => $members,
				'is_approved' => 1,
			)
		)->fetch_callback(
			function ($row) use (&$watched_users) {
				$watched_users[$row['id_member']]['last_post'] = standardTime($row['last_post']);
				$watched_users[$row['id_member']]['last_post_id'] = $row['last_post_id'];
			}
		);
	}

	return $watched_users;
}

/**
 * Count of posts of watched users.
 * (used by createList() callbacks)
 *
 * @param string $approve_query
 * @param int $warning_watch
 * @return int
 */
function watchedUserPostsCount($approve_query, $warning_watch)
{
	global $modSettings;

	$db = database();

	// @todo $approve_query is not used in the function

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE mem.warning >= {int:warning_watch}
			AND {query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle}' : '') .
		$approve_query,
		array(
			'warning_watch' => $warning_watch,
			'recycle' => $modSettings['recycle_board'],
		)
	);
	list ($totalMemberPosts) = $request->fetch_row();
	$request->free_result();

	return $totalMemberPosts;
}

/**
 * Retrieve the posts of watched users.
 * (used by createList() callbacks).
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $approve_query
 * @param int[] $delete_boards
 *
 * @return array
 */
function watchedUserPosts($start, $items_per_page, $approve_query, $delete_boards)
{
	global $modSettings;

	$db = database();

	$member_posts = array();
	$bbc_parser = ParserWrapper::instance();
	$db->fetchQuery('
		SELECT 
			m.id_msg, m.id_topic, m.id_board, m.id_member, m.subject, m.body, m.poster_time,
			m.approved, mem.real_name, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE mem.warning >= {int:warning_watch}
			AND {query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle}' : '') .
		$approve_query . '
		ORDER BY m.id_msg DESC
		LIMIT ' . $items_per_page . '  OFFSET ' . $start,
		array(
			'warning_watch' => $modSettings['warning_watch'],
			'recycle' => $modSettings['recycle_board'],
		)
	)->fetch_callback(
		function ($row) use (&$member_posts, $bbc_parser, $start, $delete_boards) {
			global $scripturl;

			$row['subject'] = censor($row['subject']);
			$row['body'] = censor($row['body']);

			$member_posts[$row['id_msg']] = array(
				'id' => $row['id_msg'],
				'id_topic' => $row['id_topic'],
				'author_link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'subject' => $row['subject'],
				'body' => $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']),
				'poster_time' => standardTime($row['poster_time']),
				'approved' => $row['approved'],
				'can_delete' => $delete_boards == array(0) || in_array($row['id_board'], $delete_boards),
				'counter' => ++$start,
			);
		}
	);

	return $member_posts;
}

/**
 * Show a list of all the group requests they can see.
 * Checks permissions for group moderation.
 */
function groupRequests()
{
	$db = database();

	$group_requests = array();
	$i = 0;

	// Make sure they can even moderate someone!
	if (User::$info->mod_cache['gq'] == '0=1')
	{
		return array();
	}

	// What requests are outstanding?
	$db->fetchQuery('
		SELECT 
			lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, mem.member_name, mg.group_name, mem.real_name
		FROM {db_prefix}log_group_requests AS lgr
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
			INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
		WHERE ' . (User::$info->mod_cache['gq'] == '1=1' || User::$info->mod_cache['gq'] == '0=1' ? User::$info->mod_cache['gq'] : 'lgr.' . User::$info->mod_cache['gq']) . '
		ORDER BY lgr.id_request DESC
		LIMIT 10',
		array()
	)->fetch_callback(
		function ($row) use (&$group_requests, &$i) {
			global $scripturl;

			$group_requests[] = array(
				'id' => $row['id_request'],
				'request_href' => $scripturl . '?action=groups;sa=requests;gid=' . $row['id_group'],
				'member' => array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
					'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
					'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				),
				'group' => array(
					'id' => $row['id_group'],
					'name' => $row['group_name'],
				),
				'time_submitted' => standardTime($row['time_applied']),
			);

			$i++;
		}
	);

	return $group_requests;
}

/**
 * Returns an array of basic info about the most active watched users.
 */
function basicWatchedUsers()
{
	global $modSettings;

	$db = database();

	$watched_users = array();
	if (!Cache::instance()->getVar($watched_users, 'recent_user_watches', 240))
	{
		$modSettings['warning_watch'] = empty($modSettings['warning_watch']) ? 1 : $modSettings['warning_watch'];
		$db->fetchQuery('
			SELECT
				id_member, real_name, last_login
			FROM {db_prefix}members
			WHERE warning >= {int:warning_watch}
			ORDER BY last_login DESC
			LIMIT 10',
			array(
				'warning_watch' => $modSettings['warning_watch'],
			)
		)->fetch_callback(
			function ($row) use (&$watched_users) {
				$watched_users[] = $row;
			}
		);

		Cache::instance()->put('recent_user_watches', $watched_users, 240);
	}

	return $watched_users;
}

/**
 * Returns the most recent reported posts as array
 *
 * @param bool $show_pms
 *
 * @return array
 */
function reportedPosts($show_pms = false)
{
	$db = database();

	// Got the info already?
	$cachekey = md5(serialize(User::$info->mod_cache['bq']));

	$reported_posts = array();
	if (!Cache::instance()->getVar($reported_posts, 'reported_posts_' . $cachekey, 90))
	{
		$reported_posts = array();
		// By George, that means we in a position to get the reports, jolly good.
		$db->fetchQuery('
			SELECT
				lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject,
				lr.num_reports, COALESCE(mem.real_name, lr.membername) AS author_name,
				COALESCE(mem.id_member, 0) AS id_author
			FROM {db_prefix}log_reported AS lr
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
			WHERE ' . (User::$info->mod_cache['bq'] == '1=1' || User::$info->mod_cache['bq'] == '0=1' ? User::$info->mod_cache['bq'] : 'lr.' . User::$info->mod_cache['bq']) . '
				AND lr.closed = {int:not_closed}
				AND lr.type IN ({array_string:rep_type})
				AND lr.ignore_all = {int:not_ignored}
			ORDER BY lr.time_updated DESC
			LIMIT 10',
			array(
				'not_closed' => 0,
				'not_ignored' => 0,
				'rep_type' => $show_pms ? array('pm') : array('msg'),
			)
		)->fetch_callback(
			function ($row) use (&$reported_posts) {
				$reported_posts[] = $row;
			}
		);

		// Cache it.
		Cache::instance()->put('reported_posts_' . $cachekey, $reported_posts, 90);
	}

	return $reported_posts;
}

/**
 * Remove a moderator note.
 *
 * @param int $id_note
 */
function removeModeratorNote($id_note)
{
	$db = database();

	// Lets delete it.
	$db->query('', '
		DELETE FROM {db_prefix}log_comments
		WHERE id_comment = {int:note}
			AND comment_type = {string:type}',
		array(
			'note' => $id_note,
			'type' => 'modnote',
		)
	);
}

/**
 * Get the number of moderator notes stored on the site.
 *
 * @return int
 */
function countModeratorNotes()
{
	$db = database();

	$moderator_notes_total = 0;
	if (!Cache::instance()->getVar($moderator_notes_total, 'moderator_notes_total', 240))
	{
		$request = $db->query('', '
			SELECT
				COUNT(*)
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.comment_type = {string:modnote}',
			array(
				'modnote' => 'modnote',
			)
		);
		list ($moderator_notes_total) = $request->fetch_row();
		$request->free_result();

		Cache::instance()->put('moderator_notes_total', $moderator_notes_total, 240);
	}

	return $moderator_notes_total;
}

/**
 * Adds a moderation note to the moderation center "shoutbox"
 *
 * @param int $id_poster who is posting the add
 * @param string $poster_name a name to show
 * @param string $contents what they are posting
 */
function addModeratorNote($id_poster, $poster_name, $contents)
{
	$db = database();

	// Insert it into the database
	$db->insert('',
		'{db_prefix}log_comments',
		array(
			'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
			'body' => 'string', 'log_time' => 'int',
		),
		array(
			$id_poster, $poster_name, 'modnote', '', $contents, time(),
		),
		array('id_comment')
	);
}

/**
 * Add a moderation comment to an actual moderation report
 *
 * @param int $report
 * @param string $newComment
 */
function addReportComment($report, $newComment)
{
	$db = database();

	// Insert it into the database
	$db->insert('',
		'{db_prefix}log_comments',
		array(
			'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
			'id_notice' => 'int', 'body' => 'string', 'log_time' => 'int',
		),
		array(
			User::$info->id, User::$info->name, 'reportc', '',
			$report, $newComment, time(),
		),
		array('id_comment')
	);
}

/**
 * Get the list of current notes in the moderation center "shoutbox"
 *
 * @param int $offset
 *
 * @return array
 */
function moderatorNotes($offset)
{
	$db = database();

	// Grab the current notes.
	// We can only use the cache for the first page of notes.
	if ($offset != 0 || !Cache::instance()->getVar($moderator_notes, 'moderator_notes', 240))
	{
		$moderator_notes = array();
		$db->fetchQuery('
			SELECT 
				COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name,
				lc.log_time, lc.body, lc.id_comment AS id_note
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.comment_type = {string:modnote}
			ORDER BY id_comment DESC
			LIMIT 10 OFFSET {int:offset} ',
			array(
				'modnote' => 'modnote',
				'offset' => $offset,
			)
		)->fetch_callback(
			function ($row) use (&$moderator_notes) {
				$moderator_notes[] = $row;
			}
		);

		if ($offset == 0)
		{
			Cache::instance()->put('moderator_notes', $moderator_notes, 240);
		}
	}

	return $moderator_notes;
}

/**
 * Gets a warning notice by id that was sent to a user.
 *
 * @param int $id_notice
 *
 * @return array
 */
function moderatorNotice($id_notice)
{
	$db = database();

	// Get the body and subject of this notice
	$request = $db->query('', '
		SELECT 
			body, subject
		FROM {db_prefix}log_member_notices
		WHERE id_notice = {int:id_notice}',
		array(
			'id_notice' => $id_notice,
		)
	);
	if ($request->num_rows() === 0)
	{
		return array();
	}
	list ($notice_body, $notice_subject) = $request->fetch_row();
	$request->free_result();

	// Make it look nice
	$bbc_parser = ParserWrapper::instance();
	$notice_body = $bbc_parser->parseNotice($notice_body);

	return array($notice_body, $notice_subject);
}

/**
 * Make sure the "current user" (uses User::$info) cannot go outside of the limit for the day.
 *
 * @param int $member The member we are going to issue the warning to
 *
 * @return int
 */
function warningDailyLimit($member)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			SUM(counter)
		FROM {db_prefix}log_comments
		WHERE id_recipient = {int:selected_member}
			AND id_member = {int:current_member}
			AND comment_type = {string:warning}
			AND log_time > {int:day_time_period}',
		array(
			'current_member' => User::$info->id,
			'selected_member' => $member,
			'day_time_period' => time() - 86400,
			'warning' => 'warning',
		)
	);
	list ($current_applied) = $request->fetch_row();
	$request->free_result();

	return $current_applied;
}

/**
 * Make sure the "current user" (uses User::$info) cannot go outside of the limit for the day.
 *
 * @param string $approve_query additional condition for the query
 * @param string $current_view defined whether return the topics (first
 *                messages) or the messages. If set to 'topics' it returns
 *                the topics, otherwise the messages
 * @param mixed[] $boards_allowed array of arrays, it must contain three
 *                 indexes:
 *                  - delete_own_boards
 *                  - delete_any_boards
 *                  - delete_own_replies
 *                 each of which must be an array of boards the user is allowed
 *                 to perform a certain action (return of boardsAllowedTo)
 * @param int $start start of the query LIMIT
 * @param int $limit number of elements to return (default 10)
 *
 * @return array
 */
function getUnapprovedPosts($approve_query, $current_view, $boards_allowed, $start, $limit = 10)
{
	$db = database();

	$unapproved_items = array();
	$bbc_parser = ParserWrapper::instance();
	$i = 1;
	$db->fetchQuery('
		SELECT 
			m.id_msg, m.id_topic, m.id_board, m.subject, m.body, m.id_member,
			COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.smileys_enabled,
			t.id_member_started, t.id_first_msg, b.name AS board_name, c.id_cat, c.name AS cat_name
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE m.approved = {int:not_approved}
			AND t.id_first_msg ' . ($current_view == 'topics' ? '=' : '!=') . ' m.id_msg
			AND {query_see_board}
			' . $approve_query . '
		LIMIT {int:limit} OFFSET {int:start} ',
		array(
			'start' => $start,
			'limit' => $limit,
			'not_approved' => 0,
		)
	)->fetch_callback(
		function ($row) use (&$unapproved_items, $bbc_parser, &$i, $boards_allowed) {
			global $context, $scripturl, $modSettings;

			// Can delete is complicated, let's solve it first... is it their own post?
			if ($row['id_member'] == User::$info->id && ($boards_allowed['delete_own_boards'] == array(0) || in_array($row['id_board'], $boards_allowed['delete_own_boards'])))
			{
				$can_delete = true;
			}
			// Is it a reply to their own topic?
			elseif ($row['id_member'] == $row['id_member_started'] && $row['id_msg'] != $row['id_first_msg'] && ($boards_allowed['delete_own_replies'] == array(0) || in_array($row['id_board'], $boards_allowed['delete_own_replies'])))
			{
				$can_delete = true;
			}
			// Someone else's?
			elseif ($row['id_member'] != User::$info->id && ($boards_allowed['delete_any_boards'] == array(0) || in_array($row['id_board'], $boards_allowed['delete_any_boards'])))
			{
				$can_delete = true;
			}
			else
			{
				$can_delete = false;
			}

			$unapproved_items[] = array(
				'id' => $row['id_msg'],
				'counter' => $context['start'] + $i,
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				'subject' => $row['subject'],
				'body' => $bbc_parser->parseMessage($row['body'], $row['smileys_enabled']),
				'time' => standardTime($row['poster_time']),
				'html_time' => htmlTime($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'poster' => array(
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				),
				'topic' => array(
					'id' => $row['id_topic'],
				),
				'board' => array(
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>',
				),
				'category' => array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'link' => '<a href="' . getUrl('action', $modSettings['default_forum_action']) . '#c' . $row['id_cat'] . '">' . $row['cat_name'] . '</a>',
				),
				'can_delete' => $can_delete,
			);

			$i++;
		}
	);

	return $unapproved_items;
}
