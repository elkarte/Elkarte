<?php

/**
 * This file contains some useful functions for logging.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

/**
 * @todo
 *
 * @param string $session_id
 */
function deleteLogOnlineInterval($session_id)
{
	global $modSettings;

	$db = database();

	$db->query('delete_log_online_interval', '
		DELETE FROM {db_prefix}log_online
		WHERE log_time < {int:log_time}
			AND session != {string:session}',
		array(
			'log_time' => time() - $modSettings['lastActive'] * 60,
			'session' => $session_id,
		)
	);
}

/**
 * Update a users entry in the online log
 *
 * @param string $session_id
 * @param string $serialized
 */
function updateLogOnline($session_id, $serialized)
{
	global $user_info;

	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_online
		SET
			log_time = {int:log_time},
			ip = {string:ip},
			url = {string:url}
		WHERE session = {string:session}',
		array(
			'log_time' => time(),
			'ip' => $user_info['ip'],
			'url' => $serialized,
			'session' => $session_id,
		)
	);

	// Guess it got deleted.
	if ($db->affected_rows() == 0)
		$_SESSION['log_time'] = 0;
}

/**
 * Update a users entry in the online log
 *
 * @param string $session_id
 * @param string $serialized
 * @param boolean $do_delete
 */
function insertdeleteLogOnline($session_id, $serialized, $do_delete = false)
{
	global $user_info, $modSettings;

	$db = database();

	if ($do_delete || !empty($user_info['id']))
		$db->query('', '
			DELETE FROM {db_prefix}log_online
			WHERE ' . ($do_delete ? 'log_time < {int:log_time}' : '') . ($do_delete && !empty($user_info['id']) ? ' OR ' : '') . (empty($user_info['id']) ? '' : 'id_member = {int:current_member}'),
			array(
				'current_member' => $user_info['id'],
				'log_time' => time() - $modSettings['lastActive'] * 60,
			)
		);

	$db->insert($do_delete ? 'ignore' : 'replace',
		'{db_prefix}log_online',
		array(
			'session' => 'string', 'id_member' => 'int', 'id_spider' => 'int', 'log_time' => 'int', 'ip' => 'string', 'url' => 'string'
		),
		array(
			$session_id, $user_info['id'], empty($_SESSION['id_robot']) ? 0 : $_SESSION['id_robot'], time(), $user_info['ip'], $serialized
		),
		array(
			'session'
		)
	);
}

/**
 * Update the system tracking statistics
 *
 * - Used by trackStats
 *
 * @param mixed[] $update_parameters
 * @param string $setStringUpdate
 * @param mixed[] $insert_keys
 * @param mixed[] $cache_stats
 * @param string $date
 */
function updateLogActivity($update_parameters, $setStringUpdate, $insert_keys, $cache_stats, $date)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_activity
		SET ' . $setStringUpdate . '
		WHERE date = {date:current_date}',
		$update_parameters
	);

	if ($db->affected_rows() == 0)
	{
		$db->insert('ignore',
			'{db_prefix}log_activity',
			array_merge($insert_keys, array('date' => 'date')),
			array_merge($cache_stats, array($date)),
			array('date')
		);
	}
}

/**
 * Actualize login history, for the passed member and IPs.
 *
 * - It will log it as entry for the current time.
 *
 * @param int $id_member
 * @param string $ip
 * @param string $ip2
 */
function logLoginHistory($id_member, $ip, $ip2)
{
	$db = database();

	$db->insert('insert',
		'{db_prefix}member_logins',
		array(
			'id_member' => 'int', 'time' => 'int', 'ip' => 'string', 'ip2' => 'string',
		),
		array(
			$id_member, time(), $ip, $ip2
		),
		array(
			'id_member', 'time'
		)
	);
}

/**
 * Checks if a messages or topic has been reported
 *
 * @param string $msg_id
 * @param string $topic_id
 * @param string $type
 */
function loadLogReported($msg_id, $topic_id, $type = 'msg')
{
	$db = database();

	$request = $db->query('', '
		SELECT id_report
		FROM {db_prefix}log_reported
		WHERE {raw:column_name} = {int:reported}
			AND type = {string:type}
		LIMIT 1',
		array(
			'column_name' => !empty($msg_id) ? 'id_msg' : 'id_topic',
			'reported' => !empty($msg_id) ? $msg_id : $topic_id,
			'type' => $type,
		)
	);
	$num = $db->num_rows($request);
	$db->free_result($request);

	return ($num > 0);
}

/**
 * Log a change to the forum, such as moderation events or administrative changes.
 *
 * @param mixed[] $inserts
 */
function insertLogActions($inserts)
{
	$db = database();

	$db->insert('',
		'{db_prefix}log_actions',
		array(
			'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'string-16', 'action' => 'string',
			'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
		),
		$inserts,
		array('id_action')
	);

	return $db->insert_id('{db_prefix}log_actions', 'id_action');
}

function deleteMemberLogOnline()
{
	global $user_info;

	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}log_online
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $user_info['id'],
		)
	);
}

/**
 * Delete expired/outdated session from log_online
 *
 * @package Authorization
 * @param string $session
 */
function deleteOnline($session)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}log_online
		WHERE session = {string:session}',
		array(
			'session' => $session,
		)
	);
}

/**
 * Set the passed users online or not, in the online log table
 *
 * @package Authorization
 * @param int[]|int $ids ids of the member(s) to log
 * @param bool $on = false if true, add the user(s) to online log, if false, remove 'em
 */
function logOnline($ids, $on = false)
{
	$db = database();

	if (!is_array($ids))
		$ids = array($ids);

	if (empty($on))
	{
		// set the user(s) out of log_online
		$db->query('', '
			DELETE FROM {db_prefix}log_online
			WHERE id_member IN ({array_int:members})',
			array(
				'members' => $ids,
			)
		);
	}
}