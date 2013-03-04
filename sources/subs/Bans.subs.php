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
 * This file contains functions that are specifically done by administrators.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Saves one or more ban triggers into a ban item: according to the suggestions
 * checks the $_POST variable to verify if the trigger is present
 * If 
 *
 * @param array $suggestions
 * @param int $ban_group
 * @param int $member
 * @param int $ban_id
 *
 * @return mixed array with the saved triggers or false on failure
 */
function saveTriggers($suggestions = array(), $ban_group, $member = 0, $ban_id = 0)
{
	$triggers = array(
		'main_ip' => '',
		'hostname' => '',
		'email' => '',
		'member' => array(
			'id' => $member,
		)
	);
	$ban_triggers = array();
	$ban_errors = error_context::context('ban', 1);

	foreach ($suggestions as $key => $value)
	{
		if (is_array($value))
			$triggers[$key] = $value;
		else
			$triggers[$value] = !empty($_POST[$value]) ? $_POST[$value] : '';
	}

	$ban_triggers = validateTriggers($triggers);

	// Time to save!
	if (!empty($ban_triggers['ban_triggers']) && !$ban_errors->hasErrors())
	{
		if (empty($ban_id))
			addTriggers($ban_group, $ban_triggers['ban_triggers'], $ban_triggers['log_info']);
		else
			updateTriggers($ban_id, $ban_group, array_shift($ban_triggers['ban_triggers']), $ban_triggers['log_info']);
	}
	if ($ban_errors->hasErrors())
		return $triggers;
	else
		return false;
}

/**
 * This function removes a bunch of triggers based on ids
 * Doesn't clean the inputs
 *
 * @param array $items_ids
 * @return bool
 */
function removeBanTriggers($items_ids = array(), $group_id = false)
{
	global $smcFunc;

	if ($group_id !== false)
		$group_id = (int) $group_id;

	if (empty($group_id) && empty($items_ids))
		return false;

	if (!is_array($items_ids))
		$items_ids = array($items_ids);

	if ($group_id !== false)
	{
		$smcFunc['db_query']('', '
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
		$smcFunc['db_query']('', '
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
 * This function removes a bunch of ban groups based on ids
 * Doesn't clean the inputs
 *
 * @param array $group_ids
 * @return bool
 */
function removeBanGroups($group_ids)
{
	global $smcFunc;

	if (!is_array($group_ids))
		$group_ids = array($group_ids);

	$group_ids = array_unique($group_ids);

	if (empty($group_ids))
		return false;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}ban_groups
		WHERE id_ban_group IN ({array_int:ban_list})',
		array(
			'ban_list' => $group_ids,
		)
	);

	return true;
}

/**
 * Removes logs - by default truncate the table
 * Doesn't clean the inputs
 *
 * @param array (optional) $ids
 * @return bool
 */
function removeBanLogs($ids = array())
{
	global $smcFunc;

	if (empty($ids))
		$smcFunc['db_query']('truncate_table', '
			TRUNCATE {db_prefix}log_banned',
			array(
			)
		);
	else
	{
		if (!is_array($ids))
			$ids = array($ids);

		$ids = array_unique($ids);

		if (empty($ids))
			return false;

		$smcFunc['db_query']('', '
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
 * @param array $triggers
 * @return array triggers and logs info ready to be used
 */
function validateTriggers(&$triggers)
{
	global $smcFunc;

	$ban_errors = error_context::context('ban', 1);
	if (empty($triggers))
		$ban_errors->addError('ban_empty_triggers');

	$ban_triggers = array();
	$log_info = array();
	$array_keys = array();
	call_integration_hook('integrate_extend_ban_triggers', array(&$array_keys));
	$array_keys += array('ips_in_messages', 'ips_in_errors');

	foreach ($triggers as $key => $value)
	{
		if (!empty($value))
		{
			if ($key == 'main_ip')
			{
				$value = trim($value);
				$ip_parts = ip2range($value);
				if (!checkExistingTriggerIP($ip_parts, $value))
					$ban_errors->addError('invalid_ip');
				else
				{
					$ban_triggers['main_ip'] = array(
						'ip_low1' => $ip_parts[0]['low'],
						'ip_high1' => $ip_parts[0]['high'],
						'ip_low2' => $ip_parts[1]['low'],
						'ip_high2' => $ip_parts[1]['high'],
						'ip_low3' => $ip_parts[2]['low'],
						'ip_high3' => $ip_parts[2]['high'],
						'ip_low4' => $ip_parts[3]['low'],
						'ip_high4' => $ip_parts[3]['high'],
						'ip_low5' => $ip_parts[4]['low'],
						'ip_high5' => $ip_parts[4]['high'],
						'ip_low6' => $ip_parts[5]['low'],
						'ip_high6' => $ip_parts[5]['high'],
						'ip_low7' => $ip_parts[6]['low'],
						'ip_high7' => $ip_parts[6]['high'],
						'ip_low8' => $ip_parts[7]['low'],
						'ip_high8' => $ip_parts[7]['high'],
					);
				}
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
				$request = $smcFunc['db_query']('', '
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
				if ($smcFunc['db_num_rows']($request) != 0)
					$ban_errors->addError('no_ban_admin');
				$smcFunc['db_free_result']($request);

				$value = substr(strtolower(str_replace('*', '%', $value)), 0, 255);

				$ban_triggers['email']['email_address'] = $value;
			}
			elseif ($key == 'user')
			{
				$user = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $smcFunc['htmlspecialchars']($value, ENT_QUOTES));

				$request = $smcFunc['db_query']('', '
					SELECT id_member, (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0) AS isAdmin
					FROM {db_prefix}members
					WHERE member_name = {string:username} OR real_name = {string:username}
					LIMIT 1',
					array(
						'admin_group' => 1,
						'username' => $user,
					)
				);
				if ($smcFunc['db_num_rows']($request) == 0)
					$ban_errors->addError('invalid_username');
				list ($value, $isAdmin) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				if ($isAdmin && strtolower($isAdmin) != 'f')
				{
					unset($value);
					$ban_errors->addError('no_ban_admin');
				}
				else
					$ban_triggers['user']['id_member'] = $value;
			}
			elseif (in_array($key, $array_keys))
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
					if (!checkExistingTriggerIP($ip_parts, $val))
						$ban_errors->addError('invalid_ip');
					else
					{
						$ban_triggers[$key][] = array(
							'ip_low1' => $ip_parts[0]['low'],
							'ip_high1' => $ip_parts[0]['high'],
							'ip_low2' => $ip_parts[1]['low'],
							'ip_high2' => $ip_parts[1]['high'],
							'ip_low3' => $ip_parts[2]['low'],
							'ip_high3' => $ip_parts[2]['high'],
							'ip_low4' => $ip_parts[3]['low'],
							'ip_high4' => $ip_parts[3]['high'],
							'ip_low5' => $ip_parts[4]['low'],
							'ip_high5' => $ip_parts[4]['high'],
							'ip_low6' => $ip_parts[5]['low'],
							'ip_high6' => $ip_parts[5]['high'],
							'ip_low7' => $ip_parts[6]['low'],
							'ip_high7' => $ip_parts[6]['high'],
							'ip_low8' => $ip_parts[7]['low'],
							'ip_high8' => $ip_parts[7]['high'],
						);

						$log_info[] = array(
							'value' => $val,
							'bantype' => 'ip_range',
						);
					}
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
 * @param int $group_id
 * @param array $triggers
 * @param array $logs
 * @return nothing
 */
function addTriggers($group_id = 0, $triggers = array(), $logs = array())
{
	global $smcFunc;

	$ban_errors = error_context::context('ban', 1);

	if (empty($group_id))
		$ban_errors->addError('ban_group_id_empty');

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

	$array_keys = array();
	call_integration_hook('integrate_extend_ban_triggers', array(&$array_keys));
	$array_keys += array('ips_in_messages', 'ips_in_errors');

	$insertTriggers = array();
	foreach ($triggers as $key => $trigger)
	{
		// Exceptions, exceptions, exceptions...always exceptions... :P
		if (in_array($key, $array_keys))
			foreach ($trigger as $real_trigger)
				$insertTriggers[] = array_merge($values, $real_trigger);
		else
			$insertTriggers[] = array_merge($values, $trigger);
	}

	if (empty($insertTriggers))
		$ban_errors->addError('ban_no_triggers');

	if ($ban_errors->hasErrors())
		return false;

	$smcFunc['db_insert']('',
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
 * @param int $ban_item
 * @param int $group_id
 * @param array $trigger
 * @param array $logs
 * @return nothing
 */
function updateTriggers($ban_item = 0, $group_id = 0, $trigger = array(), $logs = array())
{
	global $smcFunc;

	$ban_errors = error_context::context('ban', 1);

	if (empty($ban_item))
		$ban_errors->addError('ban_ban_item_empty');
	if (empty($group_id))
		$ban_errors->addError('ban_group_id_empty');
	if (empty($trigger))
		$ban_errors->addError('ban_no_triggers');

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

	$smcFunc['db_query']('', '
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
 * @param array $logs an array of logs, each log contains the following keys:
 *                - bantype: a known type of ban (ip_range, hostname, email, user, main_ip)
 *                - value: the value of the bantype (e.g. the IP or the email address banned)
 * @param bool $new if the trigger is new or an update of an existing one
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

	// Log the addion of the ban entries into the moderation log.
	foreach ($logs as $log)
		logAction('ban', array(
			$log_name_map[$log['bantype']] => $log['value'],
			'new' => empty($new) ? 0 : 1,
			'type' => $log['bantype'],
		));
}

/**
 * Updates an existing ban group
 * If the name doesn't exists a new one is created
 * 
 * @param array $ban_info
 * @return nothing
 */
function updateBanGroup($ban_info = array())
{
	global $smcFunc;

	$ban_errors = error_context::context('ban', 1);

	if (empty($ban_info['name']))
		$ban_errors->addError('ban_name_empty');
	if (empty($ban_info['id']))
		$ban_errors->addError('ban_id_empty');

	if ($ban_errors->hasErrors())
		return;

	$request = $smcFunc['db_query']('', '
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
	if ($smcFunc['db_num_rows']($request) == 0)
		return insertBanGroup($ban_info);
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
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

}

/**
 * Creates a new ban group
 * If a ban group with the same name already exists or the group s sucessfully created the ID is returned
 * On error the error code is returned or false
 * 
 * @param array $ban_info
 * @return int the ban group's ID
 */
function insertBanGroup($ban_info = array())
{
	global $smcFunc;

	$ban_errors = error_context::context('ban', 1);

	if (empty($ban_info['name']))
		$ban_errors->addError('ban_name_empty');
	if (empty($ban_info['cannot']['access']) && empty($ban_info['cannot']['register']) && empty($ban_info['cannot']['post']) && empty($ban_info['cannot']['login']))
		$ban_errors->addError('ban_unknown_restriction_type');

	if ($ban_errors->hasErrors())
		return;

	// Check whether a ban with this name already exists.
	$request = $smcFunc['db_query']('', '
		SELECT id_ban_group
		FROM {db_prefix}ban_groups
		WHERE name = {string:new_ban_name}' . '
		LIMIT 1',
		array(
			'new_ban_name' => $ban_info['name'],
		)
	);

	if ($smcFunc['db_num_rows']($request) == 1)
	{
		list($id_ban) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		return $id_ban;
	}
	$smcFunc['db_free_result']($request);

	// Yes yes, we're ready to add now.
	$smcFunc['db_insert']('',
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
	$ban_info['id'] = $smcFunc['db_insert_id']('{db_prefix}ban_groups', 'id_ban_group');

	if (empty($ban_info['id']))
		$ban_errors->addError('impossible_insert_new_bangroup');

	return $ban_info['id'];
}

/**
 * Convert a range of given IP number into a single string.
 * It's practically the reverse function of ip2range().
 *
 * @example
 * range2ip(array(10, 10, 10, 0), array(10, 10, 20, 255)) returns '10.10.10-20.*
 *
 * @param array $low IPv4 format
 * @param array $high IPv4 format
 * @return string
 */
function range2ip($low, $high)
{
	// IPv6 check.
	if (!empty($high[4]) || !empty($high[5]) || !empty($high[6]) || !empty($high[7]))
	{
		if (count($low) != 8 || count($high) != 8)
			return '';

		$ip = array();
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
 * If yes, it returns an error message. Otherwise, it returns an array
 *  optimized for the database.
 *
 * @param array $ip_array
 * @param string $fullip
 * @return boolean
 */
function checkExistingTriggerIP($ip_array, $fullip = '')
{
	global $smcFunc, $scripturl;

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
		return false;

	$request = $smcFunc['db_query']('', '
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
	if ($smcFunc['db_num_rows']($request) != 0)
	{
		list ($error_id_ban, $error_ban_name) = $smcFunc['db_fetch_row']($request);
		fatal_lang_error('ban_trigger_already_exists', false, array(
			$fullip,
			'<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $error_id_ban . '">' . $error_ban_name . '</a>',
		));
	}
	$smcFunc['db_free_result']($request);

	return $values;
}

/**
 * As it says... this tries to review the list of banned members, to match new bans.
 * Note: is_activated >= 10: a member is banned.
 */
function updateBanMembers()
{
	global $smcFunc;

	$updates = array();
	$allMembers = array();
	$newMembers = array();

	// Start by getting all active bans - it's quicker doing this in parts...
	$request = $smcFunc['db_query']('', '
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
		)
	);
	$memberIDs = array();
	$memberEmails = array();
	$memberEmailWild = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
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
	$smcFunc['db_free_result']($request);

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
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, mem.is_activated
			FROM {db_prefix}members AS mem
			WHERE ' . implode( ' OR ', $queryPart),
			$queryValues
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
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
		$smcFunc['db_free_result']($request);
	}

	// We welcome our new members in the realm of the banned.
	if (!empty($newMembers))
	{
		require_once(SUBSDIR . '/Auth.subs.php');
		logOnline($newMembers, false);
	}

	// Find members that are wrongfully marked as banned.
	$request = $smcFunc['db_query']('', '
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
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Don't do this twice!
		if (!in_array($row['id_member'], $allMembers))
		{
			$updates[$row['new_value']][] = $row['id_member'];
			$allMembers[] = $row['id_member'];
		}
	}
	$smcFunc['db_free_result']($request);

	if (!empty($updates))
		foreach ($updates as $newStatus => $members)
			updateMemberData($members, array('is_activated' => $newStatus));

	// Update the latest member and our total members as banning may change them.
	updateStats('member');
}
