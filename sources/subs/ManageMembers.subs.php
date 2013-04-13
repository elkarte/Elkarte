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
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Nifty function to calculate the number of days ago a given date was.
 * Requires a unix timestamp as input, returns an integer.
 * Named in honour of Jeff Lewis, the original creator of...this function.
 *
 * @param $old
 * @return int, the returned number of days, based on the forum time.
 */
function jeffsdatediff($old)
{
	// Get the current time as the user would see it...
	$forumTime = forum_time();

	// Calculate the seconds that have passed since midnight.
	$sinceMidnight = date('H', $forumTime) * 60 * 60 + date('i', $forumTime) * 60 + date('s', $forumTime);

	// Take the difference between the two times.
	$dis = time() - $old;

	// Before midnight?
	if ($dis < $sinceMidnight)
		return 0;
	else
		$dis -= $sinceMidnight;

	// Divide out the seconds in a day to get the number of days.
	return ceil($dis / (24 * 60 * 60));
}

/**
 * Retrieves MemberData based on conditions
 *
 * @param string $condition
 * @param string $current_filter
 * @param int $timeBefore
 * @param array $members
 * @return array
 */
function retrieveMemberData($condition, $current_filter, $timeBefore, $members)
{
	global $smcFunc, $modSettings, $language;

	$data = array();

	// Get information on each of the members, things that are important to us, like email address...
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, email_address, validation_code, lngfile
		FROM {db_prefix}members
		WHERE is_activated = {int:activated_status}' . $condition . '
		ORDER BY lngfile',
		array(
			'activated_status' => $current_filter,
			'time_before' => empty($timeBefore) ? 0 : $timeBefore,
			'members' => empty($members) ? array() : $members,
		)
	);

	$data['member_count'] = $smcFunc['db_num_rows']($request);

	if ($data['member_count'] == 0)
		return $data;

	// Fill the info array.
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$data['members'][] = $row['id_member'];
		$data['member_info'][] = array(
			'id' => $row['id_member'],
			'username' => $row['member_name'],
			'name' => $row['real_name'],
			'email' => $row['email_address'],
			'language' => empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'],
			'code' => $row['validation_code']
		);
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

/**
 * Activate members
 *
 * @param type $members
 * @param type $condition
 * @param type $timeBefore
 * @param type $current_filter
 */
function approveMembers($members, $condition, $timeBefore, $current_filter)
{
	global $smcFunc;

	// Approve/activate this member.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET validation_code = {string:blank_string}, is_activated = {int:is_activated}
		WHERE is_activated = {int:activated_status}' . $condition,
		array(
			'is_activated' => 1,
			'time_before' => empty($timeBefore) ? 0 : $timeBefore,
			'members' => empty($members) ? array() : $members,
			'activated_status' => $current_filter,
			'blank_string' => '',
		)
	);
}

/**
 * Set these members for activation
 *
 * @param type $member
 * @param type $condition
 * @param type $current_filter
 * @param type $members
 * @param type $timeBefore
 * @param type $validation_code
 */
function enforceReactivation($member, $condition, $current_filter, $members, $timeBefore, $validation_code)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET validation_code = {string:validation_code}, is_activated = {int:not_activated}
		WHERE is_activated = {int:activated_status}
			' . $condition . '
			AND id_member = {int:selected_member}',
		array(
			'not_activated' => 0,
			'activated_status' => $current_filter,
			'selected_member' => $member['id'],
			'validation_code' => $validation_code,
			'time_before' => empty($timeBefore) ? 0 : $timeBefore,
			'members' => empty($members) ? array() : $members,
		)
	);
}