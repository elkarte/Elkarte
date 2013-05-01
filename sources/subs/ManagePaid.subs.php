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
 * This file contains all the administration functions for subscriptions.
 * (and some more than that :P)
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Returns how many people are subscribed to a paid subscription.
 * @todo refactor away
 *
 * @param int $id_sub
 * @param string $search_string
 * @param array $search_vars = array()
 */
function list_getSubscribedUserCount($id_sub, $search_string, $search_vars = array())
{
	global $smcFunc;

	// Get the total amount of users.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS total_subs
		FROM {db_prefix}log_subscribed AS ls
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ls.id_member)
		WHERE ls.id_subscribe = {int:current_subscription} ' . $search_string . '
			AND (ls.end_time != {int:no_end_time} OR ls.payments_pending != {int:no_pending_payments})',
		array_merge($search_vars, array(
			'current_subscription' => $id_sub,
			'no_end_time' => 0,
			'no_pending_payments' => 0,
		))
	);
	list ($memberCount) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $memberCount;
}

/**
 * Return the subscribed users list, for the given parameters.
 * @todo refactor outta here
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param int $id_sub
 * @param string $search_string
 * @param string $search_vars
 */
function list_getSubscribedUsers($start, $items_per_page, $sort, $id_sub, $search_string, $search_vars = array())
{
	global $smcFunc, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT ls.id_sublog, IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, {string:guest}) AS name, ls.start_time, ls.end_time,
			ls.status, ls.payments_pending
		FROM {db_prefix}log_subscribed AS ls
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ls.id_member)
		WHERE ls.id_subscribe = {int:current_subscription} ' . $search_string . '
			AND (ls.end_time != {int:no_end_time} OR ls.payments_pending != {int:no_payments_pending})
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($search_vars, array(
			'current_subscription' => $id_sub,
			'no_end_time' => 0,
			'no_payments_pending' => 0,
			'guest' => $txt['guest'],
		))
	);
	$subscribers = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$subscribers[] = array(
			'id' => $row['id_sublog'],
			'id_member' => $row['id_member'],
			'name' => $row['name'],
			'start_date' => standardTime($row['start_time'], false),
			'end_date' => $row['end_time'] == 0 ? 'N/A' : standardTime($row['end_time'], false),
			'pending' => $row['payments_pending'],
			'status' => $row['status'],
			'status_text' => $row['status'] == 0 ? ($row['payments_pending'] == 0 ? $txt['paid_finished'] : $txt['paid_pending']) : $txt['paid_active'],
		);
	$smcFunc['db_free_result']($request);

	return $subscribers;
}


/**
 * Reapplies all subscription rules for each of the users.
 *
 * @param array $users
 */
function reapplySubscriptions($users)
{
	global $smcFunc;

	// Make it an array.
	if (!is_array($users))
		$users = array($users);

	// Get all the members current groups.
	$groups = array();
	require_once(SUBSDIR . '/Members.subs.php');
	$members = getBasicMemberData($users, array('moderation' => true));
	foreach ($members as $row)
	{
		$groups[$row['id_member']] = array(
			'primary' => $row['id_group'],
			'additional' => explode(',', $row['additional_groups']),
		);
	}

	$request = $smcFunc['db_query']('', '
		SELECT ls.id_member, ls.old_id_group, s.id_group, s.add_groups
		FROM {db_prefix}log_subscribed AS ls
			INNER JOIN {db_prefix}subscriptions AS s ON (s.id_subscribe = ls.id_subscribe)
		WHERE ls.id_member IN ({array_int:user_list})
			AND ls.end_time > {int:current_time}',
		array(
			'user_list' => $users,
			'current_time' => time(),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Specific primary group?
		if ($row['id_group'] != 0)
		{
			// If this is changing - add the old one to the additional groups so it's not lost.
			if ($row['id_group'] != $groups[$row['id_member']]['primary'])
				$groups[$row['id_member']]['additional'][] = $groups[$row['id_member']]['primary'];
			$groups[$row['id_member']]['primary'] = $row['id_group'];
		}

		// Additional groups.
		if (!empty($row['add_groups']))
			$groups[$row['id_member']]['additional'] = array_merge($groups[$row['id_member']]['additional'], explode(',', $row['add_groups']));
	}
	$smcFunc['db_free_result']($request);

	// Update all the members.
	foreach ($groups as $id => $group)
	{
		$group['additional'] = array_unique($group['additional']);
		foreach ($group['additional'] as $key => $value)
			if (empty($value))
				unset($group['additional'][$key]);
		$addgroups = implode(',', $group['additional']);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET id_group = {int:primary_group}, additional_groups = {string:additional_groups}
			WHERE id_member = {int:current_member}
			LIMIT 1',
			array(
				'primary_group' => $group['primary'],
				'current_member' => $id,
				'additional_groups' => $addgroups,
			)
		);
	}
}


/**
 * Add or extend a subscription of a user.
 *
 * @param int $id_subscribe
 * @param int $id_member
 * @param string $renewal = 0, options 'D', 'W', 'M', 'Y'
 * @param int $forceStartTime = 0
 * @param int $forceEndTime = 0
 */
function addSubscription($id_subscribe, $id_member, $renewal = 0, $forceStartTime = 0, $forceEndTime = 0)
{
	global $context, $smcFunc;

	// Take the easy way out...
	loadSubscriptions();

	// Exists, yes?
	if (!isset($context['subscriptions'][$id_subscribe]))
		return;

	$curSub = $context['subscriptions'][$id_subscribe];

	// Grab the duration.
	$duration = $curSub['num_length'];

	// If this is a renewal change the duration to be correct.
	if (!empty($renewal))
	{
		switch ($renewal)
		{
			case 'D':
				$duration = 86400;
				break;
			case 'W':
				$duration = 604800;
				break;
			case 'M':
				$duration = 2629743;
				break;
			case 'Y':
				$duration = 31556926;
				break;
			default:
				break;
		}
	}

	// Firstly, see whether it exists, and is active. If so then this is meerly an extension.
	$request = $smcFunc['db_query']('', '
		SELECT id_sublog, end_time, start_time
		FROM {db_prefix}log_subscribed
		WHERE id_subscribe = {int:current_subscription}
			AND id_member = {int:current_member}
			AND status = {int:is_active}',
		array(
			'current_subscription' => $id_subscribe,
			'current_member' => $id_member,
			'is_active' => 1,
		)
	);
	if ($smcFunc['db_num_rows']($request) != 0)
	{
		list ($id_sublog, $endtime, $starttime) = $smcFunc['db_fetch_row']($request);

		// If this has already expired but is active, extension means the period from now.
		if ($endtime < time())
			$endtime = time();
		if ($starttime == 0)
			$starttime = time();

		// Work out the new expiry date.
		$endtime += $duration;

		if ($forceEndTime != 0)
			$endtime = $forceEndTime;

		// As everything else should be good, just update!
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_subscribed
			SET end_time = {int:end_time}, start_time = {int:start_time}
			WHERE id_sublog = {int:current_subscription_item}',
			array(
				'end_time' => $endtime,
				'start_time' => $starttime,
				'current_subscription_item' => $id_sublog,
			)
		);

		return;
	}
	$smcFunc['db_free_result']($request);

	// If we're here, that means we don't have an active subscription - that means we need to do some work!
	require_once(SUBSDIR . '/Members.subs.php');
	$member = getBasicMemberData($id_member, array('moderation' => true));

	// Prepare additional groups.
	$newAddGroups = explode(',', $curSub['add_groups']);
	$curAddGroups = explode(',', $member['additional_groups']);

	$newAddGroups = array_merge($newAddGroups, $curAddGroups);

	// Simple, simple, simple - hopefully... id_group first.
	if ($curSub['prim_group'] != 0)
	{
		$id_group = $curSub['prim_group'];

		// Ensure their old privileges are maintained.
		if ($member['id_group'] != 0)
			$newAddGroups[] = $member['id_group'];
	}
	else
		$id_group = $member['id_group'];

	// Yep, make sure it's unique, and no empties.
	foreach ($newAddGroups as $k => $v)
		if (empty($v))
			unset($newAddGroups[$k]);
	$newAddGroups = array_unique($newAddGroups);
	$newAddGroups = implode(',', $newAddGroups);

	// Store the new settings.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_group = {int:primary_group}, additional_groups = {string:additional_groups}
		WHERE id_member = {int:current_member}',
		array(
			'primary_group' => $id_group,
			'current_member' => $id_member,
			'additional_groups' => $newAddGroups,
		)
	);

	// Now log the subscription - maybe we have a dorment subscription we can restore?
	$request = $smcFunc['db_query']('', '
		SELECT id_sublog, end_time, start_time
		FROM {db_prefix}log_subscribed
		WHERE id_subscribe = {int:current_subscription}
			AND id_member = {int:current_member}',
		array(
			'current_subscription' => $id_subscribe,
			'current_member' => $id_member,
		)
	);
	// @todo Don't really need to do this twice...
	if ($smcFunc['db_num_rows']($request) != 0)
	{
		list ($id_sublog, $endtime, $starttime) = $smcFunc['db_fetch_row']($request);

		// If this has already expired but is active, extension means the period from now.
		if ($endtime < time())
			$endtime = time();
		if ($starttime == 0)
			$starttime = time();

		// Work out the new expiry date.
		$endtime += $duration;

		if ($forceEndTime != 0)
			$endtime = $forceEndTime;

		// As everything else should be good, just update!
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_subscribed
			SET start_time = {int:start_time}, end_time = {int:end_time}, old_id_group = {int:old_id_group}, status = {int:is_active},
				reminder_sent = {int:no_reminder_sent}
			WHERE id_sublog = {int:current_subscription_item}',
			array(
				'start_time' => $starttime,
				'end_time' => $endtime,
				'old_id_group' => $member['id_group'],
				'is_active' => 1,
				'no_reminder_sent' => 0,
				'current_subscription_item' => $id_sublog,
			)
		);

		return;
	}
	$smcFunc['db_free_result']($request);

	// Otherwise a very simple insert.
	$endtime = time() + $duration;
	if ($forceEndTime != 0)
		$endtime = $forceEndTime;

	if ($forceStartTime == 0)
		$starttime = time();
	else
		$starttime = $forceStartTime;

	$smcFunc['db_insert']('',
		'{db_prefix}log_subscribed',
		array(
			'id_subscribe' => 'int', 'id_member' => 'int', 'old_id_group' => 'int', 'start_time' => 'int',
			'end_time' => 'int', 'status' => 'int', 'pending_details' => 'string',
		),
		array(
			$id_subscribe, $id_member, $member['id_group'], $starttime,
			$endtime, 1, '',
		),
		array('id_sublog')
	);
}

/**
 * Load all the payment gateways.
 * Checks the Sources directory for any files fitting the format of a payment gateway,
 * loads each file to check it's valid, includes each file and returns the
 * function name and whether it should work with this version of the software.
 *
 * @return array
 */
function loadPaymentGateways()
{
	$gateways = array();
	if ($dh = opendir(SOURCEDIR))
	{
		while (($file = readdir($dh)) !== false)
		{
			if (is_file(SOURCEDIR .'/'. $file) && preg_match('~^Subscriptions-([A-Za-z\d]+)\.class\.php$~', $file, $matches))
			{
				// Check this is definitely a valid gateway!
				$fp = fopen(SOURCEDIR . '/' . $file, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				if (strpos($header, '// ELKARTE Payment Gateway: ' . strtolower($matches[1])) !== false)
				{
					require_once(SOURCEDIR . '/' . $file);

					$gateways[] = array(
						'filename' => $file,
						'code' => strtolower($matches[1]),
						// Don't need anything snazier than this yet.
						'valid_version' => class_exists(strtolower($matches[1]) . '_payment') && class_exists(strtolower($matches[1]) . '_display'),
						'payment_class' => strtolower($matches[1]) . '_payment',
						'display_class' => strtolower($matches[1]) . '_display',
					);
				}
			}
		}
	}
	closedir($dh);

	return $gateways;
}

/**
 * This just kind of catches all the subscription data.
 */
function loadSubscriptions()
{
	global $context, $txt, $modSettings, $smcFunc;

	if (!empty($context['subscriptions']))
		return;

	// Make sure this is loaded, just in case.
	loadLanguage('ManagePaid');

	$request = $smcFunc['db_query']('', '
		SELECT id_subscribe, name, description, cost, length, id_group, add_groups, active, repeatable
		FROM {db_prefix}subscriptions',
		array(
		)
	);
	$context['subscriptions'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Pick a cost.
		$costs = @unserialize($row['cost']);

		if ($row['length'] != 'F' && !empty($modSettings['paid_currency_symbol']) && !empty($costs['fixed']))
			$cost = sprintf($modSettings['paid_currency_symbol'], $costs['fixed']);
		else
			$cost = '???';

		// Do the span.
		preg_match('~(\d*)(\w)~', $row['length'], $match);
		if (isset($match[2]))
		{
			$num_length = $match[1];
			$length = $match[1] . ' ';
			switch ($match[2])
			{
				case 'D':
					$length .= $txt['paid_mod_span_days'];
					$num_length *= 86400;
					break;
				case 'W':
					$length .= $txt['paid_mod_span_weeks'];
					$num_length *= 604800;
					break;
				case 'M':
					$length .= $txt['paid_mod_span_months'];
					$num_length *= 2629743;
					break;
				case 'Y':
					$length .= $txt['paid_mod_span_years'];
					$num_length *= 31556926;
					break;
			}
		}
		else
			$length = '??';

		$context['subscriptions'][$row['id_subscribe']] = array(
			'id' => $row['id_subscribe'],
			'name' => $row['name'],
			'desc' => $row['description'],
			'cost' => $cost,
			'real_cost' => $row['cost'],
			'length' => $length,
			'num_length' => $num_length,
			'real_length' => $row['length'],
			'pending' => 0,
			'finished' => 0,
			'total' => 0,
			'active' => $row['active'],
			'prim_group' => $row['id_group'],
			'add_groups' => $row['add_groups'],
			'flexible' => $row['length'] == 'F' ? true : false,
			'repeatable' => $row['repeatable'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Do the counts.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_sublog) AS member_count, id_subscribe, status
		FROM {db_prefix}log_subscribed
		GROUP BY id_subscribe, status',
		array(
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$ind = $row['status'] == 0 ? 'finished' : 'total';

		if (isset($context['subscriptions'][$row['id_subscribe']]))
			$context['subscriptions'][$row['id_subscribe']][$ind] = $row['member_count'];
	}
	$smcFunc['db_free_result']($request);

	// How many payments are we waiting on?
	$request = $smcFunc['db_query']('', '
		SELECT SUM(payments_pending) AS total_pending, id_subscribe
		FROM {db_prefix}log_subscribed
		GROUP BY id_subscribe',
		array(
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (isset($context['subscriptions'][$row['id_subscribe']]))
			$context['subscriptions'][$row['id_subscribe']]['pending'] = $row['total_pending'];
	}
	$smcFunc['db_free_result']($request);
}

function deleteSubscription($id)
{
	global $smcFunc;

	$smcFunc['db_query']('delete_subscription', '
		DELETE FROM {db_prefix}subscriptions
		WHERE id_subscribe = {int:current_subscription}',
		array(
			'current_subscription' => $id,
		)
	);
}

/**
 * Adds a new subscription 
 *
 * @param array $insert
 */
function insertSubscription($insert)
{
	global $smcFunc;

	$smcFunc['db_insert']('',
	'{db_prefix}subscriptions',
		array(
			'name' => 'string-60', 'description' => 'string-255', 'active' => 'int', 'length' => 'string-4', 'cost' => 'string',
			'id_group' => 'int', 'add_groups' => 'string-40', 'repeatable' => 'int', 'allow_partial' => 'int', 'email_complete' => 'string',
			'reminder' => 'int',
		),
		array(
			$insert['name'], $insert['desc'], $insert['isActive'], $insert['span'], $insert['cost'],
			$insert['prim_group'], $insert['addgroups'], $insert['isRepeatable'], $insert['allowpartial'], $insert['emailComplete'],
			$insert['reminder'],
		),
		array('id_subscribe')
	);
}

/**
 * Used to count active subscriptions.
 *
 * @param int $sub_id
 * @return int
 */
function countActiveSubscriptions($sub_id)
{
	global $smcFunc;

	// Don't do groups if there are active members
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_subscribed
		WHERE id_subscribe = {int:current_subscription}
			AND status = {int:is_active}',
		array(
			'current_subscription' => $sub_id,
			'is_active' => 1,
		)
	);
	list ($isActive) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $isActive;
}

/**
 * Updates a changed subscription.
 *
 * @param array $update
 * @param int $ignore_active, used to ignore already active subscriptions.
 */
function updateSubscription($update, $ignore_active)
{
	global $smcFunc;

	$smcFunc['db_query']('substring', '
		UPDATE {db_prefix}subscriptions
			SET name = SUBSTRING({string:name}, 1, 60), description = SUBSTRING({string:description}, 1, 255), active = {int:is_active},
			length = SUBSTRING({string:length}, 1, 4), cost = {string:cost}' . ($ignore_active  ? '' : ', id_group = {int:id_group},
			add_groups = {string:additional_groups}') . ', repeatable = {int:repeatable}, allow_partial = {int:allow_partial},
			email_complete = {string:email_complete}, reminder = {int:reminder}
		WHERE id_subscribe = {int:current_subscription}',
		array(
			'is_active' => $update['isActive'],
			'id_group' => $update['id_group'],
			'repeatable' => $update['repeatable'],
			'allow_partial' => $update['allow_partial'],
			'reminder' => $update['reminder'],
			'current_subscription' => $update['current_subscription'],
			'name' => $update['name'],
			'description' => $update['desc'],
			'length' => $update['length'],
			'cost' => $update['cost'],
			'additional_groups' => $update['additional_groups'],
			'email_complete' => $update['emain_complete'],
		)
	);
}

/**
 * Get the details from a given subscription.
 *
 * @param type $sub_id
 * @return array
 */
function getSubscriptionDetails($sub_id)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT name, description, cost, length, id_group, add_groups, active, repeatable, allow_partial, email_complete, reminder
		FROM {db_prefix}subscriptions
		WHERE id_subscribe = {int:current_subscription}
		LIMIT 1',
		array(
			'current_subscription' => $sub_id,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Sort the date.
		preg_match('~(\d*)(\w)~', $row['length'], $match);
		if (isset($match[2]))
		{
			$span_value = $match[1];
			$span_unit = $match[2];
		}
		else
		{
			$span_value = 0;
			$span_unit = 'D';
		}

		// Is this a flexible one?
		if ($row['length'] == 'F')
			$isFlexible = true;
		else
			$isFlexible = false;

		$subscription = array(
			'name' => $row['name'],
			'desc' => $row['description'],
			'cost' => @unserialize($row['cost']),
			'span' => array(
				'value' => $span_value,
				'unit' => $span_unit,
			),
			'prim_group' => $row['id_group'],
			'add_groups' => explode(',', $row['add_groups']),
			'active' => $row['active'],
			'repeatable' => $row['repeatable'],
			'allow_partial' => $row['allow_partial'],
			'duration' => $isFlexible ? 'flexible' : 'fixed',
			'email_complete' => htmlspecialchars($row['email_complete']),
			'reminder' => $row['reminder'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $subscription;
}

/**
 * Gets some basic details from a given subsciption.
 *
 * @param int $id_sub
 * @return array
 */
function getSubscription($id_sub)
{
	global $smcFunc;

	// Load the subscription information.
	$request = $smcFunc['db_query']('', '
		SELECT id_subscribe, name, description, cost, length, id_group, add_groups, active
		FROM {db_prefix}subscriptions
		WHERE id_subscribe = {int:current_subscription}',
		array(
			'current_subscription' => $id_sub,
		)
	);
	// Something wrong?
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_access', false);

	// Do the subscription context.
	$row = $smcFunc['db_fetch_assoc']($request);
	$subscription = array(
		'id' => $row['id_subscribe'],
		'name' => $row['name'],
		'desc' => $row['description'],
		'active' => $row['active'],
	);
	$smcFunc['db_free_result']($request);

	return $subscription;
}

/**
 * Used to validate an existing subscription ID.
 *
 * @param int $id
 * @return int
 */
function validateSubscriptionID($id)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_subscribe
		FROM {db_prefix}log_subscribed
		WHERE id_sublog = {int:current_log_item}',
		array(
			'current_log_item' => $id,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_access', false);
	list ($sub_id) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $sub_id;
}

/**
 * Used to validate if a subscription is already in use.
 *
 * @param int $id_sub
 * @param int $id_member
 * @return boolean
 */
function alreadySubscribed($id_sub, $id_member)
{
	global $smcFunc;

	// Ensure the member doesn't already have a subscription!
	$request = $smcFunc['db_query']('', '
		SELECT id_subscribe
		FROM {db_prefix}log_subscribed
		WHERE id_subscribe = {int:current_subscription}
			AND id_member = {int:current_member}',
		array(
			'current_subscription' => $id_sub,
			'current_member' => $id_member,
		)
	);
	if ($smcFunc['db_num_rows']($request) != 0)
		return true;

	$smcFunc['db_free_result']($request);

	return false;
}

/**
 * Get the current status from a given subscription.
 *
 * @param int $log_id
 * @return array
 */
function getSubscriptionStatus($log_id)
{
	global $smcFunc;

	$status = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_member, status
		FROM {db_prefix}log_subscribed
		WHERE id_sublog = {int:current_log_item}',
		array(
			'current_log_item' => $log_id,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_access', false);

	list ($status['id_member'], $status['old_status']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $status;
}

/**
 * Somebody paid again? we need to log that.
 *
 * @param int $item
 */
function updateSubscriptionItem($item)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_subscribed
		SET start_time = {int:start_time}, end_time = {int:end_time}, status = {int:status}
		WHERE id_sublog = {int:current_log_item}',
		array(
			'start_time' => $item['start_time'],
			'end_time' => $item['end_time'],
			'status' => $item['status'],
			'current_log_item' => $item['current_log_item'],
		)
	);
}

/**
 * Wanna delete a subscription? Prepare the delete for the members as well.
 *
 * @param array $toDelete
 * @return array $delete
 */
function prepareDeleteSubscriptions($toDelete)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_subscribe, id_member
		FROM {db_prefix}log_subscribed
		WHERE id_sublog IN ({array_int:subscription_list})',
		array(
			'subscription_list' => $toDelete,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$delete[$row['id_subscribe']] = $row['id_member'];
	$smcFunc['db_free_result']($request);

	return $delete;
}

/**
 * Get all the pending subscriptions.
 *
 * @param int $log_id
 * @return array
 */
function getPendingSubscriptions($log_id)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT ls.id_sublog, ls.id_subscribe, ls.id_member, start_time, end_time, status, payments_pending, pending_details,
			IFNULL(mem.real_name, {string:blank_string}) AS username
		FROM {db_prefix}log_subscribed AS ls
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ls.id_member)
		WHERE ls.id_sublog = {int:current_subscription_item}
		LIMIT 1',
		array(
			'current_subscription_item' => $log_id,
			'blank_string' => '',
		)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);
	return $row;
}

/**
 * Somebody paid the first time? Let's log ...
 *
 * @param type $details
 */
function logSubscription($details)
{
	global $smcFunc;

	$smcFunc['db_insert']('',
		'{db_prefix}log_subscribed',
		array(
			'id_subscribe' => 'int', 'id_member' => 'int', 'old_id_group' => 'int', 'start_time' => 'int',
			'end_time' => 'int', 'status' => 'int',
		),
		array(
			$details['sub_id'], $details['id_member'], $details['id_group'], $details['start_time'],
			$details['end_time'], $details['status'],
		),
		array('id_sublog')
	);
}

/**
 * Updated details for a pending subscription? Logging..
 *
 * @param id $log_id
 * @param string $details
 */
function updatePendingSubscription($log_id, $details)
{
	global $smcFunc;

	// Update the entry.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_subscribed
		SET payments_pending = payments_pending - 1, pending_details = {string:pending_details}
		WHERE id_sublog = {int:current_subscription_item}',
		array(
			'current_subscription_item' => $log_id,
			'pending_details' => $details,
		)
	);
}

/**
 * Removes a subscription from a user, as in removes the groups.
 *
 * @param $id_subscribe
 * @param $id_member
 * @param $delete
 */
function removeSubscription($id_subscribe, $id_member, $delete = false)
{
	global $context, $smcFunc;

	loadSubscriptions();

	// Load the user core bits.
	require_once(SUBSDIR . '/Members.subs.php');
	$member_info = getBasicMemberData($id_member, array('moderation' => true));

	// Just in case of errors.
	if (empty($member_info))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $id_member,
			)
		);
		return;
	}

	// Get all of the subscriptions for this user that are active - it will be necessary!
	$request = $smcFunc['db_query']('', '
		SELECT id_subscribe, old_id_group
		FROM {db_prefix}log_subscribed
		WHERE id_member = {int:current_member}
			AND status = {int:is_active}',
		array(
			'current_member' => $id_member,
			'is_active' => 1,
		)
	);

	// These variables will be handy, honest ;)
	$removals = array();
	$allowed = array();
	$member['id_group'] = 0;
	$new_id_group = -1;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($context['subscriptions'][$row['id_subscribe']]))
			continue;

		// The one we're removing?
		if ($row['id_subscribe'] == $id_subscribe)
		{
			$removals = explode(',', $context['subscriptions'][$row['id_subscribe']]['add_groups']);
			if ($context['subscriptions'][$row['id_subscribe']]['prim_group'] != 0)
				$removals[] = $context['subscriptions'][$row['id_subscribe']]['prim_group'];
			$member['id_group'] = $row['old_id_group'];
		}
		// Otherwise things we allow.
		else
		{
			$allowed = array_merge($allowed, explode(',', $context['subscriptions'][$row['id_subscribe']]['add_groups']));
			if ($context['subscriptions'][$row['id_subscribe']]['prim_group'] != 0)
			{
				$allowed[] = $context['subscriptions'][$row['id_subscribe']]['prim_group'];
				$new_id_group = $context['subscriptions'][$row['id_subscribe']]['prim_group'];
			}
		}
	}
	$smcFunc['db_free_result']($request);

	// Now, for everything we are removing check they defintely are not allowed it.
	$existingGroups = explode(',', $member_info['additional_groups']);
	foreach ($existingGroups as $key => $group)
		if (empty($group) || (in_array($group, $removals) && !in_array($group, $allowed)))
			unset($existingGroups[$key]);

	// Finally, do something with the current primary group.
	if (in_array($member_info['id_group'], $removals))
	{
		// If this primary group is actually allowed keep it.
		if (in_array($member_info['id_group'], $allowed))
			$existingGroups[] = $member_info['id_group'];

		// Either way, change the id_group back.
		if ($new_id_group < 1)
		{
			// If we revert to the old id-group we need to ensure it wasn't from a subscription.
			foreach ($context['subscriptions'] as $id => $group)
				// It was? Make them a regular member then!
				if ($group['prim_group'] == $member['id_group'])
					$member['id_group'] = 0;

			$member_info['id_group'] = $member['id_group'];
		}
		else
			$member_info['id_group'] = $new_id_group;
	}

	// Crazy stuff, we seem to have our groups fixed, just make them unique
	$existingGroups = array_unique($existingGroups);
	$existingGroups = implode(',', $existingGroups);

	// Update the member
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_group = {int:primary_group}, additional_groups = {string:existing_groups}
		WHERE id_member = {int:current_member}',
		array(
			'primary_group' => $member_info['id_group'],
			'current_member' => $id_member,
			'existing_groups' => $existingGroups,
		)
	);

	// Disable the subscription.
	if (!$delete)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_subscribed
			SET status = {int:not_active}
			WHERE id_member = {int:current_member}
				AND id_subscribe = {int:current_subscription}',
			array(
				'not_active' => 0,
				'current_member' => $id_member,
				'current_subscription' => $id_subscribe,
			)
		);
	// Otherwise delete it!
	else
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE id_member = {int:current_member}
				AND id_subscribe = {int:current_subscription}',
			array(
				'current_member' => $id_member,
				'current_subscription' => $id_subscribe,
			)
		);
}