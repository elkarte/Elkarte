<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
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
 * This file contains all the screens that relate to search engines.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Do we think the current user is a spider?
 *
 * @return int
 */
function spiderCheck()
{
	global $modSettings;

	$db = database();

	if (isset($_SESSION['id_robot']))
		unset($_SESSION['id_robot']);
	$_SESSION['robot_check'] = time();

	// We cache the spider data for five minutes if we can.
	if (($spider_data = cache_get_data('spider_search', 300)) === null)
	{
		$request = $db->query('spider_check', '
			SELECT id_spider, user_agent, ip_info
			FROM {db_prefix}spiders',
			array(
			)
		);
		$spider_data = array();
		while ($row = $db->fetch_assoc($request))
			$spider_data[] = $row;
		$db->free_result($request);

		cache_put_data('spider_search', $spider_data, 300);
	}

	if (empty($spider_data))
		return false;

	// Only do these bits once.
	$ci_user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);

	// Always attempt IPv6 first.
	if (strpos($_SERVER['REMOTE_ADDR'], ':') !== false)
		$ip_parts = convertIPv6toInts($_SERVER['REMOTE_ADDR']);
	else
		preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $_SERVER['REMOTE_ADDR'], $ip_parts);

	foreach ($spider_data as $spider)
	{
		// User agent is easy.
		if (!empty($spider['user_agent']) && strpos($ci_user_agent, strtolower($spider['user_agent'])) !== false)
			$_SESSION['id_robot'] = $spider['id_spider'];
		// IP stuff is harder.
		elseif (!empty($ip_parts))
		{
			$ips = explode(',', $spider['ip_info']);
			foreach ($ips as $ip)
			{
				$ip = ip2range($ip);
				if (!empty($ip))
				{
					foreach ($ip as $key => $value)
					{
						if ($value['low'] > $ip_parts[$key + 1] || $value['high'] < $ip_parts[$key + 1])
							break;
						elseif (($key == 7 && strpos($_SERVER['REMOTE_ADDR'], ':') !== false) || ($key == 3 && strpos($_SERVER['REMOTE_ADDR'], ':') === false))
							$_SESSION['id_robot'] = $spider['id_spider'];
					}
				}
			}
		}

		if (isset($_SESSION['id_robot']))
			break;
	}

	// If this is low server tracking then log the spider here as oppossed to the main logging function.
	if (!empty($modSettings['spider_mode']) && $modSettings['spider_mode'] == 1 && !empty($_SESSION['id_robot']))
		logSpider();

	return !empty($_SESSION['id_robot']) ? $_SESSION['id_robot'] : 0;
}

/**
 * Log the spider presence online.
 */
function logSpider()
{
	global $modSettings, $context;

	$db = database();

	if (empty($modSettings['spider_mode']) || empty($_SESSION['id_robot']))
		return;

	// Attempt to update today's entry.
	if ($modSettings['spider_mode'] == 1)
	{
		$date = strftime('%Y-%m-%d', forum_time(false));
		$db->query('', '
			UPDATE {db_prefix}log_spider_stats
			SET last_seen = {int:current_time}, page_hits = page_hits + 1
			WHERE id_spider = {int:current_spider}
				AND stat_date = {date:current_date}',
			array(
				'current_date' => $date,
				'current_time' => time(),
				'current_spider' => $_SESSION['id_robot'],
			)
		);

		// Nothing updated?
		if ($db->affected_rows() == 0)
		{
			$db->insert('ignore',
				'{db_prefix}log_spider_stats',
				array(
					'id_spider' => 'int', 'last_seen' => 'int', 'stat_date' => 'date', 'page_hits' => 'int',
				),
				array(
					$_SESSION['id_robot'], time(), $date, 1,
				),
				array('id_spider', 'stat_date')
			);
		}
	}
	// If we're tracking better stats than track, better stats - we sort out the today thing later.
	else
	{
		if ($modSettings['spider_mode'] > 2)
		{
			$url = $_GET + array('USER_AGENT' => $_SERVER['HTTP_USER_AGENT']);
			unset($url['sesc'], $url[$context['session_var']]);
			$url = serialize($url);
		}
		else
			$url = '';

		$db->insert('insert',
			'{db_prefix}log_spider_hits',
			array('id_spider' => 'int', 'log_time' => 'int', 'url' => 'string'),
			array($_SESSION['id_robot'], time(), $url),
			array()
		);
	}
}

/**
 * This function takes any unprocessed hits
 * and updates stats accordingly.
 */
function consolidateSpiderStats()
{
	$db = database();

	$request = $db->query('consolidate_spider_stats', '
		SELECT id_spider, MAX(log_time) AS last_seen, COUNT(*) AS num_hits
		FROM {db_prefix}log_spider_hits
		WHERE processed = {int:not_processed}
		GROUP BY id_spider, MONTH(log_time), DAYOFMONTH(log_time)',
		array(
			'not_processed' => 0,
		)
	);
	$spider_hits = array();
	while ($row = $db->fetch_assoc($request))
		$spider_hits[] = $row;
	$db->free_result($request);

	if (empty($spider_hits))
		return;

	// Attempt to update the master data.
	$stat_inserts = array();
	foreach ($spider_hits as $stat)
	{
		// We assume the max date is within the right day.
		$date = strftime('%Y-%m-%d', $stat['last_seen']);
		$db->query('', '
			UPDATE {db_prefix}log_spider_stats
			SET page_hits = page_hits + ' . $stat['num_hits'] . ',
				last_seen = CASE WHEN last_seen > {int:last_seen} THEN last_seen ELSE {int:last_seen} END
			WHERE id_spider = {int:current_spider}
				AND stat_date = {date:last_seen_date}',
			array(
				'last_seen_date' => $date,
				'last_seen' => $stat['last_seen'],
				'current_spider' => $stat['id_spider'],
			)
		);
		if ($db->affected_rows() == 0)
			$stat_inserts[] = array($date, $stat['id_spider'], $stat['num_hits'], $stat['last_seen']);
	}

	// New stats?
	if (!empty($stat_inserts))
		$db->insert('ignore',
			'{db_prefix}log_spider_stats',
			array('stat_date' => 'date', 'id_spider' => 'int', 'page_hits' => 'int', 'last_seen' => 'int'),
			$stat_inserts,
			array('stat_date', 'id_spider')
		);

	// All processed.
	$db->query('', '
		UPDATE {db_prefix}log_spider_hits
		SET processed = {int:is_processed}
		WHERE processed = {int:not_processed}',
		array(
			'is_processed' => 1,
			'not_processed' => 0,
		)
	);
}

/**
 * Recache spider names.
 */
function recacheSpiderNames()
{
	$db = database();

	$request = $db->query('', '
		SELECT id_spider, spider_name
		FROM {db_prefix}spiders',
		array(
		)
	);
	$spiders = array();
	while ($row = $db->fetch_assoc($request))
		$spiders[$row['id_spider']] = $row['spider_name'];
	$db->free_result($request);

	updateSettings(array('spider_name_cache' => serialize($spiders)));
}

/**
 * Sort the search engine table by user agent name to avoid misidentification of engine.
 */
function sortSpiderTable()
{
	$db = database();

	$table = db_table();

	// Add a sorting column.
	$table->db_add_column('{db_prefix}spiders', array('name' => 'temp_order', 'size' => 8, 'type' => 'mediumint', 'null' => false));

	// Set the contents of this column.
	$db->query('set_spider_order', '
		UPDATE {db_prefix}spiders
		SET temp_order = LENGTH(user_agent)',
		array(
		)
	);

	// Order the table by this column.
	$db->query('alter_table_spiders', '
		ALTER TABLE {db_prefix}spiders
		ORDER BY temp_order DESC',
		array(
			'db_error_skip' => true,
		)
	);

	// Remove the sorting column.
	$table->db_remove_column('{db_prefix}spiders', 'temp_order');
}