<?php

/**
 * This file contains functions that are specifically done by administrators.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Saves one or more ban triggers into a ban item: according to the suggestions
 *
 * What it does:
 * - Checks the $_POST variable to verify if the trigger is present
 * - Load triggers in to an array for validation
 * - Validates and saves/updates the triggers for a given ban
 *
 * @package Bans
 * @param mixed[] $suggestions A bit messy array, it should look something like:
 *                 array(
 *                   'main_ip' => '123.123.123.123',
 *                   'hostname' => 'hostname.tld',
 *                   'email' => 'email@address.tld',
 *                   'ban_suggestions' => array(
 *                     'main_ip',     // <= these two are those that will be
 *                     'hostname',    // <= used for the ban, so no email
 *                     'other_ips' => array(
 *                       'ips_in_messages' => array(...),
 *                       'ips_in_errors' => array(...),
 *                       'other_custom' => array(...),
 *                     )
 *                   )
 *                 )
 * @param int $ban_group
 * @param int $member
 * @param int $trigger_id
 * @return mixed array with the saved triggers or false on failure
 */
function saveTriggers($suggestions, $ban_group, $member = 0, $trigger_id = 0)
{
	$triggers = array(
		'main_ip' => '',
		'hostname' => '',
		'email' => '',
		'member' => array(
			'id' => $member,
		)
	);

	$ban_errors = Error_Context::context('ban', 1);

	if (!is_array($suggestions))
		return false;

	// What triggers are we adding (like ip, host, email, etc)
	foreach ($suggestions['ban_suggestions'] as $key => $value)
	{
		if (is_array($value))
			$triggers[$key] = $value;
		else
			$triggers[$value] = !empty($suggestions[$value]) ? $suggestions[$value] : '';
	}

	// Make sure the triggers for this ban are valid
	$ban_triggers = validateTriggers($triggers);

	// Time to save or update!
	if (!empty($ban_triggers['ban_triggers']) && !$ban_errors->hasErrors())
	{
		if (empty($trigger_id))
			addTriggers($ban_group, $ban_triggers['ban_triggers'], $ban_triggers['log_info']);
		else
			updateTriggers($trigger_id, $ban_group, array_shift($ban_triggers['ban_triggers']), $ban_triggers['log_info']);
	}

	// No errors, then return the ban triggers
	if ($ban_errors->hasErrors())
		return $triggers;
	else
		return false;
}

/**
 * This function removes a batch of triggers based on ids
 *
 * What it does:
 * - Doesn't clean the inputs, expects valid input
 * - Removes the ban triggers by id or group
 *
 * @package Bans
 * @param int[]|int $items_ids
 * @param int|boolean $group_id
 * @return bool
 */
function removeBanTriggers($items_ids = array(), $group_id = false)
{
	$db = database();

	if ($group_id !== false)
		$group_id = (int) $group_id;

	if (empty($group_id) && empty($items_ids))
		return false;

	if (!is_array($items_ids))
		$items_ids = array($items_ids);

	// Log the ban removals so others know
	$log_info = banLogItems(banDetails($items_ids, $group_id));
	logTriggersUpdates($log_info, 'remove');

	// Remove the ban triggers by id's or groups
	if ($group_id !== false)
	{
		$db->query('', '
			DELETE FROM {db_prefix}ban_items
			WHERE id_ban IN ({array_int:ban_list})
				AND id_ban_group = {int:ban_group}',
			array(
				'ban_list' => $items_ids,
				'ban_group' => $group_id,
			)
		);
	}
	elseif (!empty($items_ids))
	{
		$db->query('', '
			DELETE FROM {db_prefix}ban_items
			WHERE id_ban IN ({array_int:ban_list})',
			array(
				'ban_list' => $items_ids,
			)
		);
	}

	return true;
}

/**
 * This function removes a batch of ban groups based on ids
 *
 * What it does:
 * - Doesn't clean the inputs
 * - Removes entries from the ban group list, one or many
 *
 * @package Bans
 * @param int[]|int $group_ids
 * @return bool
 */
function removeBanGroups($group_ids)
{
	$db = database();

	if (!is_array($group_ids))
		$group_ids = array($group_ids);

	$group_ids = array_unique($group_ids);

	if (empty($group_ids))
		return false;

	$db->query('', '
		DELETE FROM {db_prefix}ban_groups
		WHERE id_ban_group IN ({array_int:ban_list})',
		array(
			'ban_list' => $group_ids,
		)
	);

	return true;
}

/**
 * Removes ban logs
 *
 * What it does:
 * - By default (no id's passed) truncate the table
 * - Doesn't clean the inputs
 *
 * @package Bans
 * @param int[]|int|null $ids (optional)
 * @return bool
 */
function removeBanLogs($ids = array())
{
	$db = database();

	// No specific id's passed, we truncate the entire table
	if (empty($ids))
		$db->query('truncate_table', '
			TRUNCATE {db_prefix}log_banned',
			array(
			)
		);
	else
	{
		if (!is_array($ids))
			$ids = array($ids);

		// Can only remove it once
		$ids = array_unique($ids);

		if (empty($ids))
			return false;

		// Remove this grouping
		$db->query('', '
			DELETE FROM {db_prefix}log_banned
			WHERE id_ban_log IN ({array_int:ban_list})',
			array(
				'ban_list' => $ids,
			)
		);
	}

	return true;
}

/**
 * This function validates the ban triggers
 *
 * @package Bans
 * @param mixed[] $triggers
 */
function validateTriggers(&$triggers)
{
	$db = database();

	$ban_errors = Error_Context::context('ban', 1);
	if (empty($triggers))
		$ban_errors->addError('ban_empty_triggers');

	$ban_triggers = array();
	$log_info = array();

	// Go through each trigger and make sure its valid
	foreach ($triggers as $key => $value)
	{
		if (!empty($value))
		{
			if ($key == 'member')
				continue;

			if ($key == 'main_ip')
			{
				$value = trim($value);
				$ip_parts = ip2range($value);
				$ban_trigger = validateIPBan($ip_parts, $value);
				if (empty($ban_trigger['error']))
				{
					$ban_triggers['main_ip'] = $ban_trigger;
				}
				else
					$ban_errors->addError($ban_trigger['error']);
			}
			elseif ($key == 'hostname')
			{
				if (preg_match('/[^\w.\-*]/', $value) == 1)
					$ban_errors->addError('invalid_hostname');
				else
				{
					// Replace the * wildcard by a MySQL wildcard %.
					$value = substr(str_replace('*', '%', $value), 0, 255);

					$ban_triggers['hostname']['hostname'] = $value;
				}
			}
			elseif ($key == 'email')
			{
				if (preg_match('/[^\w.\-\+*@]/', $value) == 1)
					$ban_errors->addError('invalid_email');

				// Check the user is not banning an admin.
				$request = $db->query('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
						AND email_address LIKE {string:email}
					LIMIT 1',
					array(
						'admin_group' => 1,
						'email' => $value,
					)
				);
				if ($db->num_rows($request) != 0)
					$ban_errors->addError('no_ban_admin');
				$db->free_result($request);

				$value = substr(strtolower(str_replace('*', '%', $value)), 0, 255);

				$ban_triggers['email']['email_address'] = $value;
			}
			elseif ($key == 'user')
			{
				$user = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', Util::htmlspecialchars($value, ENT_QUOTES));

				$request = $db->query('', '
					SELECT id_member, (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0) AS isAdmin
					FROM {db_prefix}members
					WHERE member_name = {string:username} OR real_name = {string:username}
					LIMIT 1',
					array(
						'admin_group' => 1,
						'username' => $user,
					)
				);
				if ($db->num_rows($request) == 0)
					$ban_errors->addError('invalid_username');
				list ($value, $isAdmin) = $db->fetch_row($request);
				$db->free_result($request);

				if ($isAdmin && strtolower($isAdmin) != 'f')
				{
					unset($value);
					$ban_errors->addError('no_ban_admin');
				}
				else
					$ban_triggers['user']['id_member'] = $value;
			}
			elseif (in_array($key, array('ips_in_messages', 'ips_in_errors')))
			{
				// Special case, those two are arrays themselves
				$values = array_unique($value);

				// Don't add the main IP again.
				if (isset($triggers['main_ip']))
					$values = array_diff($values, array($triggers['main_ip']));

				unset($value);
				foreach ($values as $val)
				{
					$val = trim($val);
					$ip_parts = ip2range($val);
					$ban_trigger = validateIPBan($ip_parts, $val);

					if (empty($ban_trigger['error']))
					{
						$ban_triggers[$key][] = $ban_trigger;

						$log_info[] = array(
							'value' => $val,
							'bantype' => 'ip_range',
						);
					}
					else
						$ban_errors->addError($ban_trigger['error']);
				}
			}
			else
				$ban_errors->addError('no_bantype_selected');

			if (isset($value) && !is_array($value))
				$log_info[] = array(
					'value' => $value,
					'bantype' => $key,
				);
		}
	}

	return array('ban_triggers' => $ban_triggers, 'log_info' => $log_info);
}

/**
 * This function actually inserts the ban triggers into the database
 *
 * @package Bans
 * @param int $group_id
 * @param mixed[] $triggers associative array of trigger keys and the values
 * @param mixed[] $logs
 * @return boolean
 */
function addTriggers($group_id = 0, $triggers = array(), $logs = array())
{
	$db = database();

	$ban_errors = Error_Context::context('ban', 1);

	if (empty($group_id))
		$ban_errors->addError('ban_not_found');

	// Preset all values that are required.
	$values = array(
		'id_ban_group' => $group_id,
		'hostname' => '',
		'email_address' => '',
		'id_member' => 0,
		'ip_low1' => 0,
		'ip_high1' => 0,
		'ip_low2' => 0,
		'ip_high2' => 0,
		'ip_low3' => 0,
		'ip_high3' => 0,
		'ip_low4' => 0,
		'ip_high4' => 0,
		'ip_low5' => 0,
		'ip_high5' => 0,
		'ip_low6' => 0,
		'ip_high6' => 0,
		'ip_low7' => 0,
		'ip_high7' => 0,
		'ip_low8' => 0,
		'ip_high8' => 0,
	);

	$insertKeys = array(
		'id_ban_group' => 'int',
		'hostname' => 'string',
		'email_address' => 'string',
		'id_member' => 'int',
		'ip_low1' => 'int',
		'ip_high1' => 'int',
		'ip_low2' => 'int',
		'ip_high2' => 'int',
		'ip_low3' => 'int',
		'ip_high3' => 'int',
		'ip_low4' => 'int',
		'ip_high4' => 'int',
		'ip_low5' => 'int',
		'ip_high5' => 'int',
		'ip_low6' => 'int',
		'ip_high6' => 'int',
		'ip_low7' => 'int',
		'ip_high7' => 'int',
		'ip_low8' => 'int',
		'ip_high8' => 'int',
	);

	$insertTriggers = array();
	foreach ($triggers as $key => $trigger)
	{
		// Exceptions, exceptions, exceptions...always exceptions... :P
		if (in_array($key, array('ips_in_messages', 'ips_in_errors')))
			foreach ($trigger as $real_trigger)
				$insertTriggers[] = array_merge($values, $real_trigger);
		else
			$insertTriggers[] = array_merge($values, $trigger);
	}

	if (empty($insertTriggers))
		$ban_errors->addError('ban_no_triggers');

	if ($ban_errors->hasErrors())
		return false;

	$db->insert('ignore',
		'{db_prefix}ban_items',
		$insertKeys,
		$insertTriggers,
		array('id_ban')
	);

	logTriggersUpdates($logs, true);

	return true;
}

/**
 * This function updates an existing ban trigger into the database
 *
 * @package Bans
 * @param int $ban_item
 * @param int $group_id
 * @param mixed[] $trigger associative array of ban trigger => value
 * @param mixed[] $logs
 */
function updateTriggers($ban_item = 0, $group_id = 0, $trigger = array(), $logs = array())
{
	$db = database();

	$ban_errors = Error_Context::context('ban', 1);

	if (empty($ban_item))
		$ban_errors->addError('ban_ban_item_empty');
	if (empty($group_id))
		$ban_errors->addError('ban_not_found');
	if (empty($trigger))
		$ban_errors->addError('ban_no_triggers');

	// Any errors then we are not updating it
	if ($ban_errors->hasErrors())
		return;

	// Preset all values that are required.
	$values = array(
		'id_ban_group' => $group_id,
		'hostname' => '',
		'email_address' => '',
		'id_member' => 0,
		'ip_low1' => 0,
		'ip_high1' => 0,
		'ip_low2' => 0,
		'ip_high2' => 0,
		'ip_low3' => 0,
		'ip_high3' => 0,
		'ip_low4' => 0,
		'ip_high4' => 0,
		'ip_low5' => 0,
		'ip_high5' => 0,
		'ip_low6' => 0,
		'ip_high6' => 0,
		'ip_low7' => 0,
		'ip_high7' => 0,
		'ip_low8' => 0,
		'ip_high8' => 0,
	);

	$trigger = array_merge($values, $trigger);

	$db->query('', '
		UPDATE {db_prefix}ban_items
		SET
			hostname = {string:hostname}, email_address = {string:email_address}, id_member = {int:id_member},
			ip_low1 = {int:ip_low1}, ip_high1 = {int:ip_high1},
			ip_low2 = {int:ip_low2}, ip_high2 = {int:ip_high2},
			ip_low3 = {int:ip_low3}, ip_high3 = {int:ip_high3},
			ip_low4 = {int:ip_low4}, ip_high4 = {int:ip_high4},
			ip_low5 = {int:ip_low5}, ip_high5 = {int:ip_high5},
			ip_low6 = {int:ip_low6}, ip_high6 = {int:ip_high6},
			ip_low7 = {int:ip_low7}, ip_high7 = {int:ip_high7},
			ip_low8 = {int:ip_low8}, ip_high8 = {int:ip_high8}
		WHERE id_ban = {int:ban_item}
			AND id_ban_group = {int:id_ban_group}',
		array_merge($trigger, array(
			'id_ban_group' => $group_id,
			'ban_item' => $ban_item,
		))
	);

	logTriggersUpdates($logs, false);
}

/**
 * A small function to unify logging of triggers (updates and new)
 *
 * @package Bans
 * @param mixed[] $logs an array of logs, each log contains the following keys:
 * - bantype: a known type of ban (ip_range, hostname, email, user, main_ip)
 * - value: the value of the bantype (e.g. the IP or the email address banned)
 * @param boolean|string $new type of trigger
 * - if the trigger is new (true), an update (false), or a removal ('remove') of an existing one
 */
function logTriggersUpdates($logs, $new = true)
{
	if (empty($logs))
		return;

	$log_name_map = array(
		'main_ip' => 'ip_range',
		'hostname' => 'hostname',
		'email' => 'email',
		'user' => 'member',
		'ip_range' => 'ip_range',
	);

	// Log the addition of the ban entries into the moderation log.
	foreach ($logs as $log)
		logAction('ban', array(
			$log_name_map[$log['bantype']] => $log['value'],
			'new' => empty($new) ? 0 : ($new === true ? 1 : -1),
			'type' => $log['bantype'],
		));
}

/**
 * Updates an existing ban group
 *
 * - If the name doesn't exists a new one is created
 *
 * @package Bans
 * @param mixed[] $ban_info
 * @return nothing
 */
function updateBanGroup($ban_info = array())
{
	$db = database();

	// Lets check for errors first
	$ban_errors = Error_Context::context('ban', 1);

	if (empty($ban_info['name']))
		$ban_errors->addError('ban_name_empty');

	if (empty($ban_info['id']))
		$ban_errors->addError('ban_id_empty');

	if ($ban_errors->hasErrors())
		return false;

	// No problems found, so lets add this to the ban list
	$request = $db->query('', '
		SELECT id_ban_group
		FROM {db_prefix}ban_groups
		WHERE name = {string:new_ban_name}
			AND id_ban_group = {int:ban_group}
		LIMIT 1',
		array(
			'ban_group' => $ban_info['id'],
			'new_ban_name' => $ban_info['name'],
		)
	);
	if ($db->num_rows($request) == 0)
		return insertBanGroup($ban_info);
	$db->free_result($request);

	$db->query('', '
		UPDATE {db_prefix}ban_groups
		SET
			name = {string:ban_name},
			reason = {string:reason},
			notes = {string:notes},
			expire_time = {raw:expiration},
			cannot_access = {int:cannot_access},
			cannot_post = {int:cannot_post},
			cannot_register = {int:cannot_register},
			cannot_login = {int:cannot_login}
		WHERE id_ban_group = {int:id_ban_group}',
		array(
			'expiration' => $ban_info['db_expiration'],
			'cannot_access' => $ban_info['cannot']['access'],
			'cannot_post' => $ban_info['cannot']['post'],
			'cannot_register' => $ban_info['cannot']['register'],
			'cannot_login' => $ban_info['cannot']['login'],
			'id_ban_group' => $ban_info['id'],
			'ban_name' => $ban_info['name'],
			'reason' => $ban_info['reason'],
			'notes' => $ban_info['notes'],
		)
	);

	return $ban_info['id'];
}

/**
 * Creates a new ban group
 *
 * What it does:
 * - If a ban group with the same name already exists or the group s successfully created the ID is returned
 * - On error the error code is returned or false
 *
 * @package Bans
 * @param mixed[] $ban_info
 * @return int the ban group's ID
 */
function insertBanGroup($ban_info = array())
{
	$db = database();

	$ban_errors = Error_Context::context('ban', 1);

	if (empty($ban_info['name']))
		$ban_errors->addError('ban_name_empty');

	if (empty($ban_info['cannot']['access']) && empty($ban_info['cannot']['register']) && empty($ban_info['cannot']['post']) && empty($ban_info['cannot']['login']))
		$ban_errors->addError('ban_unknown_restriction_type');

	if ($ban_errors->hasErrors())
		return false;

	// Check whether a ban with this name already exists.
	$request = $db->query('', '
		SELECT id_ban_group
		FROM {db_prefix}ban_groups
		WHERE name = {string:new_ban_name}' . '
		LIMIT 1',
		array(
			'new_ban_name' => $ban_info['name'],
		)
	);

	// @todo shouldn't be an error here?
	if ($db->num_rows($request) == 1)
	{
		list ($id_ban) = $db->fetch_row($request);
		$db->free_result($request);
		return $id_ban;
	}
	$db->free_result($request);

	// Yes yes, we're ready to add now.
	$db->insert('',
		'{db_prefix}ban_groups',
		array(
			'name' => 'string-20', 'ban_time' => 'int', 'expire_time' => 'raw', 'cannot_access' => 'int', 'cannot_register' => 'int',
			'cannot_post' => 'int', 'cannot_login' => 'int', 'reason' => 'string-255', 'notes' => 'string-65534',
		),
		array(
			$ban_info['name'], time(), $ban_info['db_expiration'], $ban_info['cannot']['access'], $ban_info['cannot']['register'],
			$ban_info['cannot']['post'], $ban_info['cannot']['login'], $ban_info['reason'], $ban_info['notes'],
		),
		array('id_ban_group')
	);
	$ban_info['id'] = $db->insert_id('{db_prefix}ban_groups', 'id_ban_group');

	if (empty($ban_info['id']))
		$ban_errors->addError('impossible_insert_new_bangroup');

	return $ban_info['id'];
}

/**
 * Convert a range of given IP number into a single string.
 *
 * - It's practically the reverse function of ip2range().
 *
 * @example
 * range2ip(array(10, 10, 10, 0), array(10, 10, 20, 255)) returns '10.10.10-20.*
 * @package Bans
 * @param int[] $low IPv4 format
 * @param int[] $high IPv4 format
 * @return string
 */
function range2ip($low, $high)
{
	$ip = array();

	// IPv6 check.
	if (!empty($high[4]) || !empty($high[5]) || !empty($high[6]) || !empty($high[7]))
	{
		if (count($low) != 8 || count($high) != 8)
			return '';

		for ($i = 0; $i < 8; $i++)
		{
			if ($low[$i] == $high[$i])
				$ip[$i] = dechex($low[$i]);
			elseif ($low[$i] == '0' && $high[$i] == '255')
				$ip[$i] = '*';
			else
				$ip[$i] = dechex($low[$i]) . '-' . dechex($high[$i]);
		}

		return implode(':', $ip);
	}

	// Legacy IPv4 stuff.
	// (count($low) != 4 || count($high) != 4) would not work because $low and $high always contain 8 elements!
	if ((count($low) != 4 || count($high) != 4) && (count($low) != 8 || count($high) != 8))
			return '';

	for ($i = 0; $i < 4; $i++)
	{
		if ($low[$i] == $high[$i])
			$ip[$i] = $low[$i];
		elseif ($low[$i] == '0' && $high[$i] == '255')
			$ip[$i] = '*';
		else
			$ip[$i] = $low[$i] . '-' . $high[$i];
	}

	// Pretending is fun... the IP can't be this, so use it for 'unknown'.
	if ($ip == array(255, 255, 255, 255))
		return 'unknown';

	return implode('.', $ip);
}

/**
 * Checks whether a given IP range already exists in the trigger list.
 *
 * What it does:
 * - If yes, it returns an error message.
 * - Otherwise, it returns an array
 * - optimized for the database.
 *
 * @package Bans
 * @param int[] $ip_array array of ip array ints
 * @param string $fullip
 * @return boolean
 */
function validateIPBan($ip_array, $fullip = '')
{
	global $scripturl;

	$db = database();

	if (count($ip_array) == 4 || count($ip_array) == 8)
		$values = array(
			'ip_low1' => $ip_array[0]['low'],
			'ip_high1' => $ip_array[0]['high'],
			'ip_low2' => $ip_array[1]['low'],
			'ip_high2' => $ip_array[1]['high'],
			'ip_low3' => $ip_array[2]['low'],
			'ip_high3' => $ip_array[2]['high'],
			'ip_low4' => $ip_array[3]['low'],
			'ip_high4' => $ip_array[3]['high'],
			'ip_low5' => $ip_array[4]['low'],
			'ip_high5' => $ip_array[4]['high'],
			'ip_low6' => $ip_array[5]['low'],
			'ip_high6' => $ip_array[5]['high'],
			'ip_low7' => $ip_array[6]['low'],
			'ip_high7' => $ip_array[6]['high'],
			'ip_low8' => $ip_array[7]['low'],
			'ip_high8' => $ip_array[7]['high'],
		);
	else
		$values = array('error' => 'invalid_ip');

	$request = $db->query('', '
		SELECT bg.id_ban_group, bg.name
		FROM {db_prefix}ban_groups AS bg
		INNER JOIN {db_prefix}ban_items AS bi ON
			(bi.id_ban_group = bg.id_ban_group)
			AND ip_low1 = {int:ip_low1} AND ip_high1 = {int:ip_high1}
			AND ip_low2 = {int:ip_low2} AND ip_high2 = {int:ip_high2}
			AND ip_low3 = {int:ip_low3} AND ip_high3 = {int:ip_high3}
			AND ip_low4 = {int:ip_low4} AND ip_high4 = {int:ip_high4}
			AND ip_low5 = {int:ip_low5} AND ip_high5 = {int:ip_high5}
			AND ip_low6 = {int:ip_low6} AND ip_high6 = {int:ip_high6}
			AND ip_low7 = {int:ip_low7} AND ip_high7 = {int:ip_high7}
			AND ip_low8 = {int:ip_low8} AND ip_high8 = {int:ip_high8}
		LIMIT 1',
		$values
	);
	if ($db->num_rows($request) != 0)
	{
		list ($error_id_ban, $error_ban_name) = $db->fetch_row($request);
		$values = array('error' => array('ban_trigger_already_exists', array(
			$fullip,
			'<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $error_id_ban . '">' . $error_ban_name . '</a>',
		)));
	}
	$db->free_result($request);

	return $values;
}

/**
 * Checks whether a given IP range already exists in the trigger list.
 *
 * What it does:
 * - If yes, it returns an error message.
 * - Otherwise, it returns an array
 * - optimized for the database.
 *
 * @package Bans
 * @param int[] $ip_array array of ip array ints
 * @param string $fullip
 * @return boolean
 * @deprecated since 1.1 - use validateIPBan instead
 */
function checkExistingTriggerIP($ip_array, $fullip = '')
{
	$return = validateIPBan($ip_array, $fullip);

	if (empty($return['error']))
		return $return;

	if ($return['error'] === 'ban_trigger_already_exists')
		Errors::instance()->fatal_lang_error($return['error'][0], false, $return['error'][1]);

	return false;
}

/**
 * As it says... this tries to review the list of banned members, to match new bans.
 *
 * - Note: is_activated >= 10: a member is banned.
 *
 * @package Bans
 */
function updateBanMembers()
{
	$db = database();

	$updates = array();
	$allMembers = array();
	$newMembers = array();
	$memberIDs = array();
	$memberEmails = array();
	$memberEmailWild = array();

	// Start by getting all active bans - it's quicker doing this in parts...
	$db->fetchQueryCallback('
		SELECT bi.id_member, bi.email_address
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
		WHERE (bi.id_member > {int:no_member} OR bi.email_address != {string:blank_string})
			AND bg.cannot_access = {int:cannot_access_on}
			AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})',
		array(
			'no_member' => 0,
			'cannot_access_on' => 1,
			'current_time' => time(),
			'blank_string' => '',
		),
		function($row) use (&$memberIDs, &$memberEmails, &$memberEmailWild)
		{
			if ($row['id_member'])
				$memberIDs[$row['id_member']] = $row['id_member'];
			if ($row['email_address'])
			{
				// Does it have a wildcard - if so we can't do a IN on it.
				if (strpos($row['email_address'], '%') !== false)
					$memberEmailWild[$row['email_address']] = $row['email_address'];
				else
					$memberEmails[$row['email_address']] = $row['email_address'];
			}
		}
	);

	// Build up the query.
	$queryPart = array();
	$queryValues = array();
	if (!empty($memberIDs))
	{
		$queryPart[] = 'mem.id_member IN ({array_string:member_ids})';
		$queryValues['member_ids'] = $memberIDs;
	}

	if (!empty($memberEmails))
	{
		$queryPart[] = 'mem.email_address IN ({array_string:member_emails})';
		$queryValues['member_emails'] = $memberEmails;
	}

	$count = 0;
	foreach ($memberEmailWild as $email)
	{
		$queryPart[] = 'mem.email_address LIKE {string:wild_' . $count . '}';
		$queryValues['wild_' . $count++] = $email;
	}

	// Find all banned members.
	if (!empty($queryPart))
	{
		$db->fetchQueryCallback('
			SELECT mem.id_member, mem.is_activated
			FROM {db_prefix}members AS mem
			WHERE ' . implode(' OR ', $queryPart),
			$queryValues,
			function($row) use (&$allMembers, &$updates, &$newMembers)
			{
				if (!in_array($row['id_member'], $allMembers))
				{
					$allMembers[] = $row['id_member'];
					// Do they need an update?
					if ($row['is_activated'] < 10)
					{
						$updates[($row['is_activated'] + 10)][] = $row['id_member'];
						$newMembers[] = $row['id_member'];
					}
				}
			}
		);
	}

	// We welcome our new members in the realm of the banned.
	if (!empty($newMembers))
	{
		require_once(SUBSDIR . '/Logging.subs.php');
		logOnline($newMembers, false);
	}

	// Find members that are wrongfully marked as banned.
	$db->fetchQueryCallback('
		SELECT mem.id_member, mem.is_activated - 10 AS new_value
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_member = mem.id_member OR mem.email_address LIKE bi.email_address)
			LEFT JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND bg.cannot_access = {int:cannot_access_activated} AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time}))
		WHERE (bi.id_ban IS NULL OR bg.id_ban_group IS NULL)
			AND mem.is_activated >= {int:ban_flag}',
		array(
			'cannot_access_activated' => 1,
			'current_time' => time(),
			'ban_flag' => 10,
		),
		function($row) use (&$allMembers, &$updates)
		{
			// Don't do this twice!
			if (!in_array($row['id_member'], $allMembers))
			{
				$updates[$row['new_value']][] = $row['id_member'];
				$allMembers[] = $row['id_member'];
			}
		}
	);

	if (!empty($updates))
	{
		require_once(SUBSDIR . '/Members.subs.php');
		foreach ($updates as $newStatus => $members)
			updateMemberData($members, array('is_activated' => $newStatus));
	}

	// Update the latest member and our total members as banning may change them.
	require_once(SUBSDIR . '/Members.subs.php');
	updateMemberStats();
}

/**
 * Returns member data for a given member id in a suggestion format used by bans
 *
 * @package Bans
 * @uses getBasicMemberData
 * @param int $id
 */
function getMemberData($id)
{
	$suggestions = array();

	require_once(SUBSDIR . '/Members.subs.php');

	$result = getBasicMemberData($id, array('moderation' => true));
	if (!empty($result))
		$suggestions = array(
			'member' => array(
				'id' => $result['id_member'],
				'name' => $result['real_name'],
			),
			'main_ip' => $result['member_ip'],
			'email' => $result['email_address'],
		);

	return $suggestions;
}

/**
 * Get ban triggers for the given parameters.
 *
 * @package Bans
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param string $trigger_type
 * @return array
 */
function list_getBanTriggers($start, $items_per_page, $sort, $trigger_type)
{
	$db = database();

	$where = array(
		'ip' => 'bi.ip_low1 > 0',
		'hostname' => 'bi.hostname != {string:blank_string}',
		'email' => 'bi.email_address != {string:blank_string}',
	);

	return $db->fetchQuery('
		SELECT
			bi.id_ban, bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4, bi.ip_low5, bi.ip_high5, bi.ip_low6, bi.ip_high6, bi.ip_low7, bi.ip_high7, bi.ip_low8, bi.ip_high8, bi.hostname, bi.email_address, bi.hits,
			bg.id_ban_group, bg.name' . ($trigger_type === 'member' ? ',
			mem.id_member, mem.real_name' : '') . '
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)' . ($trigger_type === 'member' ? '
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)' : '
		WHERE ' . $where[$trigger_type]) . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'blank_string' => '',
		)
	);
}

/**
 * Used to see if a user is banned
 *
 * - Checks banning by ip, hostname, email or member id
 *
 * @package Bans
 * @param int $memID
 * @param string $hostname
 * @param string $email
 */
function BanCheckUser($memID, $hostname = '', $email = '')
{
	global $memberContext, $scripturl, $txt;

	$db = database();
	$bans = array();

	// This is a valid member id, we at least need that
	if (loadMemberContext($memID) && isset($memberContext[$memID]))
	{
		$ban_query = array();
		$ban_query_vars = array(
			'time' => time(),
		);

		// Member id and ip
		$ban_query[] = 'id_member = ' . $memID;
		require_once(SOURCEDIR . '/Security.php');
		$ban_query[] = constructBanQueryIP($memberContext[$memID]['ip']);

		// Do we have a hostname?
		if (!empty($hostname))
		{
			$ban_query[] = '({string:hostname} LIKE hostname)';
			$ban_query_vars['hostname'] = $hostname;
		}

		// Check their email as well...
		if (strlen($email) != 0)
		{
			$ban_query[] = '({string:email} LIKE bi.email_address)';
			$ban_query_vars['email'] = $email;
		}

		// So... are they banned?  Dying to know!
		$request = $db->query('', '
			SELECT bg.id_ban_group, bg.name, bg.cannot_access, bg.cannot_post, bg.cannot_register,
				bg.cannot_login, bg.reason
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND (bg.expire_time IS NULL OR bg.expire_time > {int:time}))
			WHERE (' . implode(' OR ', $ban_query) . ')',
			$ban_query_vars
		);
		$bans = array();
		while ($row = $db->fetch_assoc($request))
		{
			// Work out what restrictions we actually have.
			$ban_restrictions = array();
			foreach (array('access', 'register', 'login', 'post') as $type)
				if ($row['cannot_' . $type])
					$ban_restrictions[] = $txt['ban_type_' . $type];

			// No actual ban in place?
			if (empty($ban_restrictions))
				continue;

			// Prepare the link for context.
			$ban_explanation = sprintf($txt['user_cannot_due_to'], implode(', ', $ban_restrictions), '<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $row['id_ban_group'] . '">' . $row['name'] . '</a>');

			$bans[$row['id_ban_group']] = array(
				'reason' => empty($row['reason']) ? '' : '<br /><br /><strong>' . $txt['ban_reason'] . ':</strong> ' . $row['reason'],
				'cannot' => array(
					'access' => !empty($row['cannot_access']),
					'register' => !empty($row['cannot_register']),
					'post' => !empty($row['cannot_post']),
					'login' => !empty($row['cannot_login']),
				),
				'explanation' => $ban_explanation,
			);
		}
		$db->free_result($request);
	}

	return $bans;
}

/**
 * This returns the total number of ban triggers of the given type.
 *
 * @package Bans
 * @param string $trigger_type
 * @return int
 */
function list_getNumBanTriggers($trigger_type)
{
	$db = database();

	$where = array(
		'ip' => 'bi.ip_low1 > 0',
		'hostname' => 'bi.hostname != {string:blank_string}',
		'email' => 'bi.email_address != {string:blank_string}',
	);

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}ban_items AS bi' . ($trigger_type === 'member' ? '
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)' : '
		WHERE ' . $where[$trigger_type]),
		array(
			'blank_string' => '',
		)
	);
	list ($num_triggers) = $db->fetch_row($request);
	$db->free_result($request);

	return $num_triggers;
}

/**
 * Load a list of ban log entries from the database.
 *
 * - no permissions checks are done
 *
 * @package Bans
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 */
function list_getBanLogEntries($start, $items_per_page, $sort)
{
	$db = database();

	return $db->fetchQuery('
		SELECT lb.id_ban_log, lb.id_member, IFNULL(lb.ip, {string:dash}) AS ip, IFNULL(lb.email, {string:dash}) AS email, lb.log_time, IFNULL(mem.real_name, {string:blank_string}) AS real_name
		FROM {db_prefix}log_banned AS lb
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lb.id_member)
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'blank_string' => '',
			'dash' => '-',
		)
	);
}

/**
 * This returns the total count of ban log entries.
 *
 * @package Bans
 */
function list_getNumBanLogEntries()
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_banned AS lb',
		array(
		)
	);
	list ($num_entries) = $db->fetch_row($request);
	$db->free_result($request);

	return $num_entries;
}

/**
 * Get the total number of ban from the ban group table
 *
 * @package Bans
 * @return int
 */
function list_getNumBans()
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*) AS num_bans
		FROM {db_prefix}ban_groups',
		array(
		)
	);
	list ($numBans) = $db->fetch_row($request);
	$db->free_result($request);

	return $numBans;
}

/**
 * Retrieves all the ban items belonging to a certain ban group
 *
 * @package Bans
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param int $sort A string indicating how to sort the results
 * @param int $ban_group_id
 * @return array
 */
function list_getBanItems($start = 0, $items_per_page = 0, $sort = 0, $ban_group_id = 0)
{
	global $context, $scripturl;

	$db = database();

	$ban_items = array();
	$request = $db->query('', '
		SELECT
			bi.id_ban, bi.hostname, bi.email_address, bi.id_member, bi.hits,
			bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4,
			bi.ip_low5, bi.ip_high5, bi.ip_low6, bi.ip_high6, bi.ip_low7, bi.ip_high7, bi.ip_low8, bi.ip_high8,
			bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes, bg.cannot_access, bg.cannot_register, bg.cannot_login, bg.cannot_post,
			IFNULL(mem.id_member, 0) AS id_member, mem.member_name, mem.real_name
		FROM {db_prefix}ban_groups AS bg
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
		WHERE bg.id_ban_group = {int:current_ban}
		LIMIT {int:start}, {int:items_per_page}',
		array(
			'current_ban' => $ban_group_id,
			'start' => $start,
			'items_per_page' => $items_per_page,
		)
	);
	if ($db->num_rows($request) == 0)
		Errors::instance()->fatal_lang_error('ban_not_found', false);
	while ($row = $db->fetch_assoc($request))
	{
		if (!isset($context['ban']))
		{
			$context['ban'] = array(
				'id' => $row['id_ban_group'],
				'name' => $row['name'],
				'expiration' => array(
					'status' => $row['expire_time'] === null ? 'never' : ($row['expire_time'] < time() ? 'expired' : 'one_day'),
					'days' => $row['expire_time'] > time() ? floor(($row['expire_time'] - time()) / 86400) : 0
				),
				'reason' => $row['reason'],
				'notes' => $row['notes'],
				'cannot' => array(
					'access' => !empty($row['cannot_access']),
					'post' => !empty($row['cannot_post']),
					'register' => !empty($row['cannot_register']),
					'login' => !empty($row['cannot_login']),
				),
				'is_new' => false,
				'hostname' => '',
				'email' => '',
			);
		}

		if (!empty($row['id_ban']))
		{
			$ban_items[$row['id_ban']] = array(
				'id' => $row['id_ban'],
				'hits' => $row['hits'],
			);
			if (!empty($row['ip_high1']))
			{
				$ban_items[$row['id_ban']]['type'] = 'ip';
				$ban_items[$row['id_ban']]['ip'] = range2ip(array($row['ip_low1'], $row['ip_low2'], $row['ip_low3'], $row['ip_low4'], $row['ip_low5'], $row['ip_low6'], $row['ip_low7'], $row['ip_low8']), array($row['ip_high1'], $row['ip_high2'], $row['ip_high3'], $row['ip_high4'], $row['ip_high5'], $row['ip_high6'], $row['ip_high7'], $row['ip_high8']));
			}
			elseif (!empty($row['hostname']))
			{
				$ban_items[$row['id_ban']]['type'] = 'hostname';
				$ban_items[$row['id_ban']]['hostname'] = str_replace('%', '*', $row['hostname']);
			}
			elseif (!empty($row['email_address']))
			{
				$ban_items[$row['id_ban']]['type'] = 'email';
				$ban_items[$row['id_ban']]['email'] = str_replace('%', '*', $row['email_address']);
			}
			elseif (!empty($row['id_member']))
			{
				$ban_items[$row['id_ban']]['type'] = 'user';
				$ban_items[$row['id_ban']]['user'] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				);
			}
			// Invalid ban (member probably doesn't exist anymore).
			else
			{
				unset($ban_items[$row['id_ban']]);
				removeBanTriggers($row['id_ban']);
			}
		}
	}
	$db->free_result($request);

	return $ban_items;
}

/**
 * Get bans, what else? For the given options.
 *
 * @package Bans
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array
 */
function list_getBans($start, $items_per_page, $sort)
{
	$db = database();

	return $db->fetchQuery('
		SELECT bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes, COUNT(bi.id_ban) AS num_triggers
		FROM {db_prefix}ban_groups AS bg
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
		GROUP BY bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	);
}

/**
 * Gets the number of ban items belonging to a certain ban group
 *
 * @package Bans
 * @return int
 */
function list_getNumBanItems()
{
	global $context;

	$db = database();

	$ban_group_id = isset($context['ban_group_id']) ? (int) $context['ban_group_id'] : 0;

	$request = $db->query('', '
		SELECT COUNT(bi.id_ban)
		FROM {db_prefix}ban_groups AS bg
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
		WHERE bg.id_ban_group = {int:current_ban}',
		array(
			'current_ban' => $ban_group_id,
		)
	);
	list ($banNumber) = $db->fetch_row($request);
	$db->free_result($request);

	return $banNumber;
}

/**
 * Load other IPs the given member has used on forum while posting.
 *
 * @package Bans
 * @param int $member_id
 */
function banLoadAdditionalIPsMember($member_id)
{
	$db = database();

	// Find some additional IP's used by this member.
	$message_ips = array();
	$request = $db->query('ban_suggest_message_ips', '
		SELECT DISTINCT poster_ip
		FROM {db_prefix}messages
		WHERE id_member = {int:current_user}
			AND poster_ip RLIKE {string:poster_ip_regex}
		ORDER BY poster_ip',
		array(
			'current_user' => $member_id,
			'poster_ip_regex' => '^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$',
		)
	);
	while ($row = $db->fetch_assoc($request))
		$message_ips[] = $row['poster_ip'];
	$db->free_result($request);

	return $message_ips;
}

/**
 * Load other IPs the given member has received errors logged while they were using them.
 *
 * @package Bans
 * @param int $member_id
 */
function banLoadAdditionalIPsError($member_id)
{
	$db = database();

	$error_ips = array();
	$request = $db->query('ban_suggest_error_ips', '
		SELECT DISTINCT ip
		FROM {db_prefix}log_errors
		WHERE id_member = {int:current_user}
			AND ip RLIKE {string:poster_ip_regex}
		ORDER BY ip',
		array(
			'current_user' => $member_id,
			'poster_ip_regex' => '^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$',
		)
	);
	while ($row = $db->fetch_assoc($request))
		$error_ips[] = $row['ip'];
	$db->free_result($request);

	return $error_ips;
}

/**
 * Finds additional IPs related to a certain user
 *
 * @package Bans
 * @param int $member_id
 * @return array
 */
function banLoadAdditionalIPs($member_id)
{
	// Borrowing a few language strings from profile.
	loadLanguage('Profile');

	$search_list = array();
	call_integration_hook('integrate_load_additional_ip_ban', array(&$search_list));
	$search_list += array('ips_in_messages' => 'banLoadAdditionalIPsMember', 'ips_in_errors' => 'banLoadAdditionalIPsError');

	$return = array();
	foreach ($search_list as $key => $callable)
		if (is_callable($callable))
			$return[$key] = call_user_func($callable, $member_id);

	return $return;
}

/**
 * Fetches ban details
 *
 * @package Bans
 * @param int[]|int $ban_ids
 * @param int|bool $ban_group
 */
function banDetails($ban_ids, $ban_group = false)
{
	$db = database();

	if (!is_array($ban_ids))
		$ban_ids = array($ban_ids);

	$request = $db->query('', '
		SELECT
			bi.id_ban, bi.id_ban_group, bi.hostname, bi.email_address, bi.id_member,
			bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4,
			bi.ip_low5, bi.ip_high5, bi.ip_low6, bi.ip_high6, bi.ip_low7, bi.ip_high7, bi.ip_low8, bi.ip_high8,
			mem.member_name, mem.real_name
		FROM {db_prefix}ban_items AS bi
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
		WHERE bi.id_ban IN ({array_int:ban_items})' . ($ban_group !== false ? '
			AND bi.id_ban_group = {int:ban_group}' : ''),
		array(
			'ban_items' => $ban_ids,
			'ban_group' => $ban_group,
		)
	);
	$details = array();
	while ($row = $db->fetch_assoc($request))
		$details[$row['id_ban']] = $row;
	$db->free_result($request);

	return $details;
}

/**
 * When removing a ban trigger, this will return the specifics of whats being
 * removed so it can be logged
 *
 * @package Bans
 * @param mixed[] $ban_details
 */
function banLogItems($ban_details)
{
	$log_info = array();

	// For each ban, get the details for logging
	foreach ($ban_details as $row)
	{
		// An ip ban
		if (!empty($row['ip_high1']))
		{
			$ip = range2ip(array($row['ip_low1'], $row['ip_low2'], $row['ip_low3'], $row['ip_low4'], $row['ip_low5'], $row['ip_low6'], $row['ip_low7'], $row['ip_low8']), array($row['ip_high1'], $row['ip_high2'], $row['ip_high3'], $row['ip_high4'], $row['ip_high5'], $row['ip_high6'], $row['ip_high7'], $row['ip_high8']));
			$is_range = (strpos($ip, '-') !== false || strpos($ip, '*') !== false);

			$log_info[] = array(
				'bantype' => ($is_range ? 'ip_range' : 'main_ip'),
				'value' => $ip,
			);
		}
		// Hostname
		elseif (!empty($row['hostname']))
		{
			$log_info[] = array(
				'bantype' => 'hostname',
				'value' => $row['hostname'],
			);
		}
		// Email Address
		elseif (!empty($row['email_address']))
		{
			$log_info[] = array(
				'bantype' => 'email',
				'value' => str_replace('%', '*', $row['email_address']),
			);
		}
		// Member ID
		elseif (!empty($row['id_member']))
		{
			$log_info[] = array(
				'bantype' => 'user',
				'value' => $row['id_member'],
			);
		}
	}

	return $log_info;
}