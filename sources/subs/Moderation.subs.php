<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * Moderation helper functions.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * How many open reports do we have?
 *  - if flush is true will clear the moderator menu count
 *  - returns the number of open reports 
 *  - sets $context['open_mod_reports'] for template use
 * 
 * @param boolean $flush
 */
function recountOpenReports($flush = true)
{
	global $user_info, $context, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_reported
		WHERE ' . $user_info['mod_cache']['bq'] . '
			AND closed = {int:not_closed}
			AND ignore_all = {int:not_ignored}',
		array(
			'not_closed' => 0,
			'not_ignored' => 0,
		)
	);
	list ($open_reports) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$_SESSION['rc'] = array(
		'id' => $user_info['id'],
		'time' => time(),
		'reports' => $open_reports,
	);

	$context['open_mod_reports'] = $open_reports;
	if ($flush)
		cache_put_data('num_menu_errors', null, 900);
	return $open_reports;
}

/**
 * How many unapproved posts and topics do we have?
 * 	- Sets $context['total_unapproved_topics']
 *  - Sets $context['total_unapproved_posts']
 *  - approve_query is set to list of boards they can see
 * 
 * @param string $approve_query
 * @return array of values
 */
function recountUnapprovedPosts($approve_query = null)
{
	global $context, $smcFunc;

	if ($approve_query === null)
		return array('posts' => 0, 'topics' => 0);

	// Any unapproved posts?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
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
	list ($unapproved_posts) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// What about topics? 
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(m.id_topic)
		FROM {db_prefix}topics AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.approved = {int:not_approved}
			AND {query_see_board}
			' . $approve_query,
		array(
			'not_approved' => 0,
		)
	);
	list ($unapproved_topics) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$context['total_unapproved_topics'] = $unapproved_topics;
	$context['total_unapproved_posts'] = $unapproved_posts;
	return array('posts' => $unapproved_posts, 'topics' => $unapproved_topics);
}

/**
 * Loads the number of items awaiting moderation attention
 *  - Only loads the value a given permission level can see
 *  - If supplied a board number will load the values only for that board
 *  - Unapproved posts
 *  - Unapproved topics
 *  - Unapproved attachments
 *  - Reported posts
 *
 * @param int $brd
 */
function loadModeratorMenuCounts($brd = null)
{
	global $modSettings, $user_info;

	$menu_errors = array();

	// Work out what boards they can work in!
	$approve_boards = boardsAllowedTo('approve_posts');

	// Supplied a specific board to check?
	if (!empty($brd))
	{
		$filter_board = array((int) $brd);
		$approve_boards = $approve_boards == array(0) ? $filter_board : array_intersect($approve_boards, $filter_board);
	}

	// Work out the query
	if ($approve_boards == array(0))
		$approve_query = '';
	elseif (!empty($approve_boards))
		$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
	else
		$approve_query = ' AND 0';

	// Set up the cache key for this one
	$cache_key = md5($user_info['query_see_board'] . $approve_query);

	// If its been cached, guess what, thats right use it!
	$temp = cache_get_data('num_menu_errors', 900);
	if ($temp === null || !isset($temp[$cache_key]))
	{
		// Starting out with nothing is a good start
		$menu_errors[$cache_key]['attachments'] = 0;
		$menu_errors[$cache_key]['reports'] = 0;
		$menu_errors[$cache_key]['postmod'] = 0;
		$menu_errors[$cache_key]['topics'] = 0;
		$menu_errors[$cache_key]['posts'] = 0;

		if ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']))
		{
			$totals = recountUnapprovedPosts($approve_query);
			$menu_errors[$cache_key]['posts'] = $totals['posts'];
			$menu_errors[$cache_key]['topics'] = $totals['topics'];

			// Totals for the menu item unapproved posts and topics
			$menu_errors[$cache_key]['postmod'] = $menu_errors[$cache_key]['topics'] + $menu_errors[$cache_key]['posts'];
		}

		// Attachments
		if ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']))
		{
			require_once(CONTROLLERDIR . '/PostModeration.controller.php');
			$menu_errors[$cache_key]['attachments'] = list_getNumUnapprovedAttachments($approve_query);
		}
		
		// Reported posts
		if (!empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1')
			$menu_errors[$cache_key]['reports'] = recountOpenReports(false);

		// Grand Totals for the top most menu
		$menu_errors[$cache_key]['total'] = $menu_errors[$cache_key]['postmod'] + $menu_errors[$cache_key]['reports'] + $menu_errors[$cache_key]['attachments'];

		// Add this key in to the array, technically this resets the cache time for all keys
		// done this way as the entire thing needs to go null once *any* moderation action is taken
		$menu_errors = is_array($temp) ? array_merge($temp, $menu_errors) : $menu_errors;

		// Store it away for a while, not like this should change that often
		cache_put_data('num_menu_errors', $menu_errors, 900);
	}
	else
		$menu_errors = $temp === null ? array() : $temp;

	return $menu_errors[$cache_key];
}

/**
 * Log a warning notice.
 * 
 * @param string $subject
 * @param string $body
 */
function logWarningNotice($subject, $body)
{
	global $smcFunc;

	// Log warning notice.
	$smcFunc['db_insert']('',
		'{db_prefix}log_member_notices',
		array(
			'subject' => 'string-255', 'body' => 'string-65534',
		),
		array(
			$smcFunc['htmlspecialchars']($subject), $smcFunc['htmlspecialchars']($body),
		),
		array('id_notice')
	);
	$id_notice = $smcFunc['db_insert_id']('{db_prefix}log_member_notices', 'id_notice');

	return $id_notice;
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
	global $smcFunc, $user_info;

	$smcFunc['db_insert']('',
		'{db_prefix}log_comments',
		array(
			'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int', 'recipient_name' => 'string-255',
			'log_time' => 'int', 'id_notice' => 'int', 'counter' => 'int', 'body' => 'string-65534',
		),
		array(
			$user_info['id'], $user_info['name'], 'warning', $memberID, $real_name,
			time(), $id_notice, $level_change, $warn_reason,
		),
		array('id_comment')
	);
}