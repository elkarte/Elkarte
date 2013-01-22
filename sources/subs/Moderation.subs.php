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
	die('Hacking attempt...');

/**
 * How many open reports do we have?
 */
function recountOpenReports()
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
}

/**
 * Log a warning notice.
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
 * Log the warning being sent.
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