<?php

/**
 * This file concerns itself with logging, whether in the database or files.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Put this user in the online log.
 *
 * @param bool $force = false
 */
function writeLog($force = false)
{
	global $user_info, $user_settings, $context, $modSettings, $settings, $topic, $board;

	// If we are showing who is viewing a topic, let's see if we are, and force an update if so - to make it accurate.
	if (!empty($settings['display_who_viewing']) && ($topic || $board))
	{
		// Take the opposite approach!
		$force = true;

		// Don't update for every page - this isn't wholly accurate but who cares.
		if ($topic)
		{
			if (isset($_SESSION['last_topic_id']) && $_SESSION['last_topic_id'] == $topic)
				$force = false;
			$_SESSION['last_topic_id'] = $topic;
		}
	}

	// Are they a spider we should be tracking? Mode = 1 gets tracked on its spider check...
	if (!empty($user_info['possibly_robot']) && !empty($modSettings['spider_mode']) && $modSettings['spider_mode'] > 1)
	{
		require_once(SUBSDIR . '/SearchEngines.subs.php');
		logSpider();
	}

	// Don't mark them as online more than every so often.
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= (time() - 8) && !$force)
		return;

	if (!empty($modSettings['who_enabled']))
	{
		$serialized = $_GET;

		// In the case of a dlattach action, session_var may not be set.
		if (!isset($context['session_var']))
			$context['session_var'] = $_SESSION['session_var'];

		unset($serialized['sesc'], $serialized[$context['session_var']]);
		$serialized = serialize($serialized);
	}
	else
		$serialized = '';

	// Guests use 0, members use their session ID.
	$session_id = $user_info['is_guest'] ? 'ip' . $user_info['ip'] : session_id();

	$cache = Cache::instance();

	// Grab the last all-of-Elk-specific log_online deletion time.
	$do_delete = $cache->get('log_online-update', 30) < time() - 30;

	require_once(SUBSDIR . '/Logging.subs.php');

	// If the last click wasn't a long time ago, and there was a last click...
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= time() - $modSettings['lastActive'] * 20)
	{
		if ($do_delete)
		{
			deleteLogOnlineInterval($session_id);

			// Cache when we did it last.
			$cache->put('log_online-update', time(), 30);
		}

		updateLogOnline($session_id, $serialized);
	}
	else
		$_SESSION['log_time'] = 0;

	// Otherwise, we have to delete and insert.
	if (empty($_SESSION['log_time']))
		insertdeleteLogOnline($session_id, $serialized, $do_delete);

	// Mark your session as being logged.
	$_SESSION['log_time'] = time();

	// Well, they are online now.
	if (empty($_SESSION['timeOnlineUpdated']))
		$_SESSION['timeOnlineUpdated'] = time();

	// Set their login time, if not already done within the last minute.
	if (ELK != 'SSI' && !empty($user_info['last_login']) && $user_info['last_login'] < time() - 60)
	{
		// We log IPs the request came with, around here
		$req = request();

		// Don't count longer than 15 minutes.
		if (time() - $_SESSION['timeOnlineUpdated'] > 60 * 15)
			$_SESSION['timeOnlineUpdated'] = time();

		$user_settings['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($user_info['id'], array('last_login' => time(), 'member_ip' => $user_info['ip'], 'member_ip2' => $req->ban_ip(), 'total_time_logged_in' => $user_settings['total_time_logged_in']));

		if ($cache->levelHigherThan(1))
			$cache->put('user_settings-' . $user_info['id'], $user_settings, 60);

		$user_info['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		$_SESSION['timeOnlineUpdated'] = time();
	}
}

/**
 * Logs the last database error into a file.
 *
 * What it does:
 * - Attempts to use the backup file first, to store the last database error
 * - only updates db_last_error.txt if the first was successful.
 */
function logLastDatabaseError()
{
	// Make a note of the last modified time in case someone does this before us
	$last_db_error_change = @filemtime(BOARDDIR . '/db_last_error.txt');

	// Save the old file before we do anything
	$file = BOARDDIR . '/db_last_error.txt';
	$dberror_backup_fail = !@is_writable(BOARDDIR . '/db_last_error_bak.txt') || !@copy($file, BOARDDIR . '/db_last_error_bak.txt');
	$dberror_backup_fail = !$dberror_backup_fail ? (!file_exists(BOARDDIR . '/db_last_error_bak.txt') || filesize(BOARDDIR . '/db_last_error_bak.txt') === 0) : $dberror_backup_fail;

	clearstatcache();
	if (filemtime(BOARDDIR . '/db_last_error.txt') === $last_db_error_change)
	{
		// Write the change
		$write_db_change = time();
		$written_bytes = file_put_contents(BOARDDIR . '/db_last_error.txt', $write_db_change, LOCK_EX);

		// Survey says ...
		if ($written_bytes !== strlen($write_db_change) && !$dberror_backup_fail)
		{
			// Oops. maybe we have no more disk space left, or some other troubles, troubles...
			// Copy the file back and run for your life!
			@copy(BOARDDIR . '/db_last_error_bak.txt', BOARDDIR . '/db_last_error.txt');
			return false;
		}
		return true;
	}

	return false;
}

/**
 * Track Statistics.
 *
 * What it does:
 * - Caches statistics changes, and flushes them if you pass nothing.
 * - If '+' is used as a value, it will be incremented.
 * - It does not actually commit the changes until the end of the page view.
 * - It depends on the trackStats setting.
 *
 * @param mixed[] $stats = array() array of array => direction (+/-)
 * @return boolean|array
 */
function trackStats($stats = array())
{
	global $modSettings;
	static $cache_stats = array();

	if (empty($modSettings['trackStats']))
		return false;

	if (!empty($stats))
		return $cache_stats = array_merge($cache_stats, $stats);
	elseif (empty($cache_stats))
		return false;

	$setStringUpdate = array();
	$insert_keys = array();

	$date = strftime('%Y-%m-%d', forum_time(false));
	$update_parameters = array(
		'current_date' => $date,
	);

	foreach ($cache_stats as $field => $change)
	{
		$setStringUpdate[] = $field . ' = ' . ($change === '+' ? $field . ' + 1' : '{int:' . $field . '}');

		if ($change === '+')
			$cache_stats[$field] = 1;
		else
			$update_parameters[$field] = $change;

		$insert_keys[$field] = 'int';
	}

	$setStringUpdate = implode(',', $setStringUpdate);

	require_once(SUBSDIR . '/Logging.subs.php');
	updateLogActivity($update_parameters, $setStringUpdate, $insert_keys, $cache_stats, $date);

	// Don't do this again.
	$cache_stats = array();

	return true;
}

/**
 * This function logs a single action in the respective log. (database log)
 *
 * - You should use {@link logActions()} instead if you have multiple entries to add
 * @example logAction('remove', array('starter' => $id_member_started));
 *
 * @param string $action The action to log
 * @param string[] $extra = array() An array of extra data
 * @param string $log_type options: 'moderate', 'admin', ...etc.
 */
function logAction($action, $extra = array(), $log_type = 'moderate')
{
	// Set up the array and pass through to logActions
	return logActions(array(array(
		'action' => $action,
		'log_type' => $log_type,
		'extra' => $extra,
	)));
}

/**
 * Log changes to the forum, such as moderation events or administrative changes.
 *
 * - This behaves just like logAction() did, except that it is designed to
 * log multiple actions at once.
 *
 * @param mixed[] $logs array of actions to log [] = array(action => log_type=> extra=>)
 *   - action => A code for the log
 *   - extra => An associated array of parameters for the item being logged.
 *     This will include 'topic' for the topic id or message for the message id
 *   - log_type => A string reflecting the type of log, moderate for moderation actions,
 *     admin for administrative actions, user for user
 *
 * @return int the last logged ID
 */
function logActions($logs)
{
	global $modSettings, $user_info;

	$inserts = array();
	$log_types = array(
		'moderate' => 1,
		'user' => 2,
		'admin' => 3,
	);

	call_integration_hook('integrate_log_types', array(&$log_types));

	// No point in doing anything, if the log isn't even enabled.
	if (empty($modSettings['modlog_enabled']))
		return false;

	foreach ($logs as $log)
	{
		if (!isset($log_types[$log['log_type']]))
			return false;

		// Do we have something to log here, after all?
		if (!is_array($log['extra']))
			trigger_error('logActions(): data is not an array with action \'' . $log['action'] . '\'', E_USER_NOTICE);

		// Pull out the parts we want to store separately, but also make sure that the data is proper
		if (isset($log['extra']['topic']))
		{
			if (!is_numeric($log['extra']['topic']))
				trigger_error('logActions(): data\'s topic is not a number', E_USER_NOTICE);

			$topic_id = empty($log['extra']['topic']) ? 0 : (int) $log['extra']['topic'];
			unset($log['extra']['topic']);
		}
		else
			$topic_id = 0;

		if (isset($log['extra']['message']))
		{
			if (!is_numeric($log['extra']['message']))
				trigger_error('logActions(): data\'s message is not a number', E_USER_NOTICE);
			$msg_id = empty($log['extra']['message']) ? 0 : (int) $log['extra']['message'];
			unset($log['extra']['message']);
		}
		else
			$msg_id = 0;

		// @todo cache this?
		// Is there an associated report on this?
		if (in_array($log['action'], array('move', 'remove', 'split', 'merge')))
		{
			require_once(SUBSDIR . '/Logging.subs.php');
			if (loadLogReported($msg_id, $topic_id))
			{
				require_once(SUBSDIR . '/Moderation.subs.php');
				updateSettings(array('last_mod_report_action' => time()));
				recountOpenReports(true, allowedTo('admin_forum'));
			}
		}

		if (isset($log['extra']['member']) && !is_numeric($log['extra']['member']))
			trigger_error('logActions(): data\'s member is not a number', E_USER_NOTICE);

		if (isset($log['extra']['board']))
		{
			if (!is_numeric($log['extra']['board']))
				trigger_error('logActions(): data\'s board is not a number', E_USER_NOTICE);

			$board_id = empty($log['extra']['board']) ? 0 : (int) $log['extra']['board'];
			unset($log['extra']['board']);
		}
		else
			$board_id = 0;

		if (isset($log['extra']['board_to']))
		{
			if (!is_numeric($log['extra']['board_to']))
				trigger_error('logActions(): data\'s board_to is not a number', E_USER_NOTICE);

			if (empty($board_id))
			{
				$board_id = empty($log['extra']['board_to']) ? 0 : (int) $log['extra']['board_to'];
				unset($log['extra']['board_to']);
			}
		}

		if (isset($log['extra']['member_affected']))
			$memID = $log['extra']['member_affected'];
		else
			$memID = $user_info['id'];

		$inserts[] = array(
			time(), $log_types[$log['log_type']], $memID, $user_info['ip'], $log['action'],
			$board_id, $topic_id, $msg_id, serialize($log['extra']),
		);
	}

	require_once(SUBSDIR . '/Logging.subs.php');

	return insertLogActions($inserts);
}