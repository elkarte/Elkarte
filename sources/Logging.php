<?php

/**
 * This file concerns itself with logging, whether in the database or files.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Put this user in the online log.
 *
 * @param bool $force = false
 */
function writeLog($force = false)
{
	global $user_info, $user_settings, $context, $modSettings, $settings, $topic, $board;

	$db = database();

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
		$req = request();
		$serialized = $_GET + array('USER_AGENT' => $req->user_agent());

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

	// Grab the last all-of-Elk-specific log_online deletion time.
	$do_delete = cache_get_data('log_online-update', 30) < time() - 30;

	// If the last click wasn't a long time ago, and there was a last click...
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= time() - $modSettings['lastActive'] * 20)
	{
		if ($do_delete)
		{
			$db->query('delete_log_online_interval', '
				DELETE FROM {db_prefix}log_online
				WHERE log_time < {int:log_time}
					AND session != {string:session}',
				array(
					'log_time' => time() - $modSettings['lastActive'] * 60,
					'session' => $session_id,
				)
			);

			// Cache when we did it last.
			cache_put_data('log_online-update', time(), 30);
		}

		$db->query('', '
			UPDATE {db_prefix}log_online
			SET log_time = {int:log_time}, ip = IFNULL(INET_ATON({string:ip}), 0), url = {string:url}
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
	else
		$_SESSION['log_time'] = 0;

	// Otherwise, we have to delete and insert.
	if (empty($_SESSION['log_time']))
	{
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
			array('session' => 'string', 'id_member' => 'int', 'id_spider' => 'int', 'log_time' => 'int', 'ip' => 'raw', 'url' => 'string'),
			array($session_id, $user_info['id'], empty($_SESSION['id_robot']) ? 0 : $_SESSION['id_robot'], time(), 'IFNULL(INET_ATON(\'' . $user_info['ip'] . '\'), 0)', $serialized),
			array('session')
		);
	}

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
		updateMemberData($user_info['id'], array('last_login' => time(), 'member_ip' => $user_info['ip'], 'member_ip2' => $req->ban_ip(), 'total_time_logged_in' => $user_settings['total_time_logged_in']));

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('user_settings-' . $user_info['id'], $user_settings, 60);

		$user_info['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		$_SESSION['timeOnlineUpdated'] = time();
	}
}

/**
 * Logs the last database error into a file.
 *
 * What it does:
 * - Attempts to use the backup file first, to store the last database error
 * - only updates db_last_error.php if the first was successful.
 */
function logLastDatabaseError()
{
	// Make a note of the last modified time in case someone does this before us
	$last_db_error_change = @filemtime(BOARDDIR . '/db_last_error.php');

	// Save the old file before we do anything
	$file = BOARDDIR . '/db_last_error.php';
	$dberror_backup_fail = !@is_writable(BOARDDIR . '/db_last_error_bak.php') || !@copy($file, BOARDDIR . '/db_last_error_bak.php');
	$dberror_backup_fail = !$dberror_backup_fail ? (!file_exists(BOARDDIR . '/db_last_error_bak.php') || filesize(BOARDDIR . '/db_last_error_bak.php') === 0) : $dberror_backup_fail;

	clearstatcache();
	if (filemtime(BOARDDIR . '/db_last_error.php') === $last_db_error_change)
	{
		// Write the change
		$write_db_change = '<' . '?' . "php\n" . '$db_last_error = ' . time() . ';';
		$written_bytes = file_put_contents(BOARDDIR . '/db_last_error.php', $write_db_change, LOCK_EX);

		// Survey says ...
		if ($written_bytes !== strlen($write_db_change) && !$dberror_backup_fail)
		{
			// Oops. maybe we have no more disk space left, or some other troubles, troubles...
			// Copy the file back and run for your life!
			@copy(BOARDDIR . '/db_last_error_bak.php', BOARDDIR . '/db_last_error.php');
		}
		else
		{
			@touch(BOARDDIR . '/Settings.php');
			return true;
		}
	}

	return false;
}

/**
 * This function shows the debug information tracked when $db_show_debug = true
 * in Settings.php
 */
function displayDebug()
{
	global $context, $scripturl, $modSettings;
	global $db_cache, $db_count, $db_show_debug, $cache_count, $cache_hits, $txt, $rusage_start;

	// Add to Settings.php if you want to show the debugging information.
	if (!isset($db_show_debug) || $db_show_debug !== true || (isset($_GET['action']) && $_GET['action'] == 'viewquery') || isset($_GET['api']))
		return;

	if (empty($_SESSION['view_queries']))
		$_SESSION['view_queries'] = 0;
	if (empty($context['debug']['language_files']))
		$context['debug']['language_files'] = array();
	if (empty($context['debug']['sheets']))
		$context['debug']['sheets'] = array();

	$files = get_included_files();
	$total_size = 0;
	for ($i = 0, $n = count($files); $i < $n; $i++)
	{
		if (file_exists($files[$i]))
			$total_size += filesize($files[$i]);
		$files[$i] = strtr($files[$i], array(BOARDDIR => '.'));
	}

	$warnings = 0;
	if (!empty($db_cache))
	{
		foreach ($db_cache as $q => $qq)
		{
			if (!empty($qq['w']))
				$warnings += count($qq['w']);
		}

		$_SESSION['debug'] = &$db_cache;
	}

	// Gotta have valid HTML ;).
	$temp = ob_get_contents();
	ob_clean();

	// Compute some system info, if we can
	$context['system'] = php_uname();
	$context['server_load'] = detectServerLoad();
	$context['memory_usage'] = round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB';

	// getrusage() information is CPU time, not wall clock time like microtime, *nix only
	if (function_exists('getrusage'))
	{
		$rusage_end = getrusage();
		$context['user_time'] = ($rusage_end['ru_utime.tv_sec'] - $rusage_start['ru_utime.tv_sec'] + ($rusage_end['ru_utime.tv_usec'] / 1000000));
		$context['system_time'] = ($rusage_end['ru_stime.tv_sec'] - $rusage_start['ru_stime.tv_sec'] + ($rusage_end['ru_stime.tv_usec'] / 1000000));
	}

	echo preg_replace('~</body>\s*</html>~', '', $temp), '
	<div id="debug_logging_wrapper">
		<div id="debug_logging" class="smalltext">
			', $txt['debug_system_type'], $context['system'], '<br />
			', !empty($context['server_load']) ? $txt['debug_server_load'] . $context['server_load'] . '<br />' : '', '
			', !empty($context['memory_usage']) ? $txt['debug_script_mem_load'] . $context['memory_usage'] . '<br />' : '', '
			', !empty($context['user_time']) ? $txt['debug_script_cpu_load'] . $context['user_time'] . ' / ' . $context['system_time'] . '<br />' : '', '
			', $txt['debug_browser'], $context['browser_body_id'], ' <em>(', implode('</em>, <em>', array_reverse(array_keys($context['browser'], true))), ')</em><br />
			', $txt['debug_templates'], count($context['debug']['templates']), ': <em>', implode('</em>, <em>', $context['debug']['templates']), '</em>.<br />
			', $txt['debug_subtemplates'], count($context['debug']['sub_templates']), ': <em>', implode('</em>, <em>', $context['debug']['sub_templates']), '</em>.<br />
			', $txt['debug_language_files'], count($context['debug']['language_files']), ': <em>', implode('</em>, <em>', $context['debug']['language_files']), '</em>.<br />
			', $txt['debug_stylesheets'], count($context['debug']['sheets']), ': <em>', implode('</em>, <em>', $context['debug']['sheets']), '</em>.<br />
			', $txt['debug_javascript'], !empty($context['debug']['javascript']) ? count($context['debug']['javascript']) . ': <em>' . implode('</em>, <em>', $context['debug']['javascript']) . '</em>.<br />' : '', '
			', $txt['debug_hooks'], empty($context['debug']['hooks']) ? 0 : count($context['debug']['hooks']) . ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_hooks\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', $txt['debug_show'], '</a><span id="debug_hooks" style="display: none;"><em>' . implode('</em>, <em>', $context['debug']['hooks']), '</em></span>)', '<br />
			', $txt['debug_files_included'], count($files), ' - ', round($total_size / 1024), $txt['debug_kb'], ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_include_info\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', $txt['debug_show'], '</a><span id="debug_include_info" style="display: none;"><em>', implode('</em>, <em>', $files), '</em></span>)<br />';

	// What tokens are active?
	if (isset($_SESSION['token']))
	{
		$token_list = array_keys($_SESSION['token']);
		echo '
			', $txt['debug_tokens'] . '<em>' . implode(',</em> <em>', $token_list), '</em>.<br />';
	}

	// If the cache is on, how successful was it?
	if (!empty($modSettings['cache_enable']) && !empty($cache_hits))
	{
		$entries = array();
		$total_t = 0;
		$total_s = 0;
		foreach ($cache_hits as $cache_hit)
		{
			$entries[] = $cache_hit['d'] . ' ' . $cache_hit['k'] . ': ' . sprintf($txt['debug_cache_seconds_bytes'], comma_format($cache_hit['t'], 5), $cache_hit['s']);
			$total_t += $cache_hit['t'];
			$total_s += $cache_hit['s'];
		}

		echo '
			', $txt['debug_cache_hits'], $cache_count, ': ', sprintf($txt['debug_cache_seconds_bytes_total'], comma_format($total_t, 5), comma_format($total_s)), ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_cache_info\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', $txt['debug_show'], '</a><span id="debug_cache_info" style="display: none;"><em>', implode('</em>, <em>', $entries), '</em></span>)<br />';
	}

	// Want to see the querys in a new windows?
	echo '
			<a href="', $scripturl, '?action=viewquery" target="_blank" class="new_win">', $warnings == 0 ? sprintf($txt['debug_queries_used'], (int) $db_count) : sprintf($txt['debug_queries_used_and_warnings'], (int) $db_count, $warnings), '</a><br />';

	if ($_SESSION['view_queries'] == 1 && !empty($db_cache))
		foreach ($db_cache as $q => $qq)
		{
			$is_select = strpos(trim($qq['q']), 'SELECT') === 0 || preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+SELECT .+$~s', trim($qq['q'])) != 0;

			// Temporary tables created in earlier queries are not explainable.
			if ($is_select)
			{
				foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
					if (strpos(trim($qq['q']), $tmp) !== false)
					{
						$is_select = false;
						break;
					}
			}
			// But actual creation of the temporary tables are.
			elseif (preg_match('~^CREATE TEMPORARY TABLE .+?SELECT .+$~s', trim($qq['q'])) != 0)
				$is_select = true;

			// Make the filenames look a bit better.
			if (isset($qq['f']))
				$qq['f'] = preg_replace('~^' . preg_quote(BOARDDIR, '~') . '~', '...', $qq['f']);

			echo '
	<strong>', $is_select ? '<a href="' . $scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '" target="_blank" class="new_win" style="text-decoration: none;">' : '', nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars(ltrim($qq['q'], "\n\r"), ENT_COMPAT, 'UTF-8'))) . ($is_select ? '</a></strong>' : '</strong>') . '<br />
	&nbsp;&nbsp;&nbsp;';
			if (!empty($qq['f']) && !empty($qq['l']))
				echo sprintf($txt['debug_query_in_line'], $qq['f'], $qq['l']);

			if (isset($qq['s'], $qq['t']) && isset($txt['debug_query_which_took_at']))
				echo sprintf($txt['debug_query_which_took_at'], round($qq['t'], 8), round($qq['s'], 8)) . '<br />';
			elseif (isset($qq['t']))
				echo sprintf($txt['debug_query_which_took'], round($qq['t'], 8)) . '<br />';
			echo '
	<br />';
		}

	// Or show/hide the querys in line with all of this data
	echo '
			<a href="' . $scripturl . '?action=viewquery;sa=hide">', $txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'], '</a>
		</div>
	</div>
</body></html>';
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

	$db = database();
	static $cache_stats = array();

	if (empty($modSettings['trackStats']))
		return false;

	if (!empty($stats))
		return $cache_stats = array_merge($cache_stats, $stats);
	elseif (empty($cache_stats))
		return false;

	$setStringUpdate = '';
	$insert_keys = array();
	$date = strftime('%Y-%m-%d', forum_time(false));
	$update_parameters = array(
		'current_date' => $date,
	);

	foreach ($cache_stats as $field => $change)
	{
		$setStringUpdate .= '
			' . $field . ' = ' . ($change === '+' ? $field . ' + 1' : '{int:' . $field . '}') . ',';

		if ($change === '+')
			$cache_stats[$field] = 1;
		else
			$update_parameters[$field] = $change;
		$insert_keys[$field] = 'int';
	}

	$db->query('', '
		UPDATE {db_prefix}log_activity
		SET' . substr($setStringUpdate, 0, -1) . '
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
 * @param string $action
 * @param string[] $extra = array()
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
 * @return int the last logged ID
 */
function logActions($logs)
{
	global $modSettings, $user_info;

	$db = database();

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
			$request = $db->query('', '
				SELECT id_report
				FROM {db_prefix}log_reported
				WHERE {raw:column_name} = {int:reported}
				LIMIT 1',
				array(
					'column_name' => !empty($msg_id) ? 'id_msg' : 'id_topic',
					'reported' => !empty($msg_id) ? $msg_id : $topic_id,
			));

			// Alright, if we get any result back, update open reports.
			if ($db->num_rows($request) > 0)
			{
				require_once(SUBSDIR . '/Moderation.subs.php');
				updateSettings(array('last_mod_report_action' => time()));
				recountOpenReports();
			}
			$db->free_result($request);
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