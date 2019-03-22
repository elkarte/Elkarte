<?php

/**
 * This file contains all the functions for paid subscriptions.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

/**
 * Returns how many people are subscribed to a paid subscription.
 * @todo refactor away
 *
 * @param int $id_sub
 * @param string $search_string
 * @param mixed[] $search_vars = array()
 */
function list_getSubscribedUserCount($id_sub, $search_string, $search_vars = array())
{
	$db = database();

	// Get the total amount of users.
	$request = $db->query('', '
		SELECT
			COUNT(*) AS total_subs
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
	list ($memberCount) = $db->fetch_row($request);
	$db->free_result($request);

	return $memberCount;
}

/**
 * Return the subscribed users list, for the given parameters.
 * @todo refactor outta here
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @param int $id_sub
 * @param string $search_string
 * @param mixed[] $search_vars
 */
function list_getSubscribedUsers($start, $items_per_page, $sort, $id_sub, $search_string, $search_vars = array())
{
	global $txt;

	$db = database();

	$request = $db->query('', '
		SELECT
			ls.id_sublog, ls.start_time, ls.end_time, ls.status, ls.payments_pending,
			COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, {string:guest}) AS name
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
	while ($row = $db->fetch_assoc($request))
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
	$db->free_result($request);

	return $subscribers;
}

/**
 * Reapplies all subscription rules for each of the users.
 *
 * @param int[]|int $users
 */
function reapplySubscriptions($users)
{
	$db = database();

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

	$request = $db->query('', '
		SELECT
			ls.id_member, ls.old_id_group,
			s.id_group, s.add_groups
		FROM {db_prefix}log_subscribed AS ls
			INNER JOIN {db_prefix}subscriptions AS s ON (s.id_subscribe = ls.id_subscribe)
		WHERE ls.id_member IN ({array_int:user_list})
			AND ls.end_time > {int:current_time}',
		array(
			'user_list' => $users,
			'current_time' => time(),
		)
	);
	while ($row = $db->fetch_assoc($request))
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
	$db->free_result($request);

	// Update all the members.
	foreach ($groups as $id => $group)
	{
		$group['additional'] = array_unique($group['additional']);
		$addgroups = array();
		foreach ($group['additional'] as $key => $value)
			if (!empty($value))
				$addgroups[] = $value;

		assignGroupsToMember($id, $group['primary'], $addgroups);
	}
}

/**
 * Add or extend a subscription of a user.
 *
 * @param int $id_subscribe
 * @param int $id_member
 * @param string $renewal options 'D', 'W', 'M', 'Y', ''
 * @param int $forceStartTime = 0
 * @param int $forceEndTime = 0
 */
function addSubscription($id_subscribe, $id_member, $renewal = '', $forceStartTime = 0, $forceEndTime = 0)
{
	global $context;

	$db = database();

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

	// Firstly, see whether it exists, and is active. If so then this is merely an extension.
	$request = $db->query('', '
		SELECT
			id_sublog, end_time, start_time
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
	if ($db->num_rows($request) != 0)
	{
		list ($id_sublog, $endtime, $starttime) = $db->fetch_row($request);

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
		$db->query('', '
			UPDATE {db_prefix}log_subscribed
			SET end_time = {int:end_time}, start_time = {int:start_time}, reminder_sent = {int:no_reminder}
			WHERE id_sublog = {int:current_subscription_item}',
			array(
				'end_time' => $endtime,
				'start_time' => $starttime,
				'current_subscription_item' => $id_sublog,
				'no_reminder' => 0,
			)
		);

		return;
	}
	$db->free_result($request);

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
	require_once(SUBSDIR . '/Members.subs.php');
	updateMemberData($id_member, array('id_group' => $id_group, 'additional_groups' => $newAddGroups));

	// Now log the subscription - maybe we have a dormant subscription we can restore?
	$request = $db->query('', '
		SELECT
			id_sublog, end_time, start_time
		FROM {db_prefix}log_subscribed
		WHERE id_subscribe = {int:current_subscription}
			AND id_member = {int:current_member}',
		array(
			'current_subscription' => $id_subscribe,
			'current_member' => $id_member,
		)
	);
	// @todo Don't really need to do this twice...
	if ($db->num_rows($request) != 0)
	{
		list ($id_sublog, $endtime, $starttime) = $db->fetch_row($request);

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
		$db->query('', '
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
	$db->free_result($request);

	// Otherwise a very simple insert.
	$endtime = time() + $duration;
	if ($forceEndTime != 0)
		$endtime = $forceEndTime;

	if ($forceStartTime == 0)
		$starttime = time();
	else
		$starttime = $forceStartTime;

	$db->insert('',
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
 *
 * What it does:
 *
 * - Checks the Sources directory for any files fitting the format of a payment gateway,
 * - Loads each file to check it's valid, includes each file and returns the
 * - Function name and whether it should work with this version of the software.
 *
 * @return array
 */
function loadPaymentGateways()
{
	$gateways = array();

	try
	{
		$files = new FilesystemIterator(SUBSDIR, FilesystemIterator::SKIP_DOTS);
		foreach ($files as $file)
		{
			if ($file->isFile() && preg_match('~^Subscriptions-([A-Za-z\d]+)\.class\.php$~', $file->getFilename(), $matches))
			{
				// Check this is definitely a valid gateway!
				$fp = fopen($file->getPathname(), 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				if (strpos($header, 'Payment Gateway: ' . $matches[1]) !== false)
				{
					require_once($file->getPathname());

					$gateways[] = array(
						'filename' => $file->getFilename(),
						'code' => strtolower($matches[1]),
						// Don't need anything snazzier than this yet.
						'valid_version' => class_exists($matches[1] . '_Payment') && class_exists($matches[1] . '_Display'),
						'payment_class' => $matches[1] . '_Payment',
						'display_class' => $matches[1] . '_Display',
					);
				}
			}
		}
	}
	catch (UnexpectedValueException $e)
	{
		// @todo
	}

	return $gateways;
}

/**
 * This just kind of catches all the subscription data.
 */
function loadSubscriptions()
{
	global $context, $txt, $modSettings;

	$db = database();

	if (!empty($context['subscriptions']))
		return;

	// Make sure this is loaded, just in case.
	loadLanguage('ManagePaid');

	$request = $db->query('', '
		SELECT
			id_subscribe, name, description, cost, length, id_group, add_groups, active, repeatable
		FROM {db_prefix}subscriptions',
		array(
		)
	);
	$context['subscriptions'] = array();
	while ($row = $db->fetch_assoc($request))
	{
		// Pick a cost.
		$costs = Util::unserialize($row['cost']);

		if ($row['length'] != 'F' && !empty($modSettings['paid_currency_symbol']) && !empty($costs['fixed']))
			$cost = sprintf($modSettings['paid_currency_symbol'], $costs['fixed']);
		else
			$cost = '???';

		// Do the span.
		$length = '??';
		$num_length = 0;
		if (preg_match('~^(\d*)([DWFMY]$)~', $row['length'], $match) === 1)
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
				default:
					$length = '??';
			}
		}

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
	$db->free_result($request);

	// Do the counts.
	$request = $db->query('', '
		SELECT COUNT(id_sublog) AS member_count, id_subscribe, status
		FROM {db_prefix}log_subscribed
		GROUP BY id_subscribe, status',
		array(
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		$ind = $row['status'] == 0 ? 'finished' : 'total';

		if (isset($context['subscriptions'][$row['id_subscribe']]))
			$context['subscriptions'][$row['id_subscribe']][$ind] = $row['member_count'];
	}
	$db->free_result($request);

	// How many payments are we waiting on?
	$request = $db->query('', '
		SELECT
			SUM(payments_pending) AS total_pending, id_subscribe
		FROM {db_prefix}log_subscribed
		GROUP BY id_subscribe',
		array(
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		if (isset($context['subscriptions'][$row['id_subscribe']]))
			$context['subscriptions'][$row['id_subscribe']]['pending'] = $row['total_pending'];
	}
	$db->free_result($request);
}

/**
 * Loads all of the members subscriptions from those that are active
 *
 * @param int $memID id of the member
 * @param mixed[] $active_subscriptions array of active subscriptions they can have
 */
function loadMemberSubscriptions($memID, $active_subscriptions)
{
	global $txt;

	$db = database();

	// Get the current subscriptions.
	$request = $db->query('', '
		SELECT
			id_sublog, id_subscribe, start_time, end_time, status, payments_pending, pending_details
		FROM {db_prefix}log_subscribed
		WHERE id_member = {int:selected_member}',
		array(
			'selected_member' => $memID,
		)
	);
	$current = array();
	while ($row = $db->fetch_assoc($request))
	{
		// The subscription must exist!
		if (!isset($active_subscriptions[$row['id_subscribe']]))
			continue;

		$current[$row['id_subscribe']] = array(
			'id' => $row['id_sublog'],
			'sub_id' => $row['id_subscribe'],
			'hide' => $row['status'] == 0 && $row['end_time'] == 0 && $row['payments_pending'] == 0,
			'name' => $active_subscriptions[$row['id_subscribe']]['name'],
			'start' => standardTime($row['start_time'], false),
			'end' => $row['end_time'] == 0 ? $txt['not_applicable'] : standardTime($row['end_time'], false),
			'pending_details' => $row['pending_details'],
			'status' => $row['status'],
			'status_text' => $row['status'] == 0 ? ($row['payments_pending'] ? $txt['paid_pending'] : $txt['paid_finished']) : $txt['paid_active'],
		);
	}
	$db->free_result($request);

	return $current;
}

/**
 * Find all members with an active subscription to a specific item
 *
 * @param int $sub_id id of the subscription we are looking for
 */
function loadAllSubsctiptions($sub_id)
{
	global $txt;

	$db = database();

	// Need a subscription id
	if (empty($sub_id))
		return array();

	// Find some basic information for each member that has subscribed
	$request = $db->query('', '
		SELECT
			ls.id_member, ls.old_id_group, ls.id_subscribe, ls.status,
			mem.id_group, mem.additional_groups, COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, {string:guest}) AS name
		FROM {db_prefix}log_subscribed AS ls
			INNER JOIN {db_prefix}members AS mem ON (ls.id_member = mem.id_member)
		WHERE ls.id_subscribe = {int:current_subscription}
			AND status = {int:is_active}',
		array(
			'current_subscription' => $sub_id,
			'is_active' => 1,
			'guest' => $txt['guest'],
		)
	);
	$members = array();
	while ($row = $db->fetch_assoc($request))
	{
		$id_member = $row['id_member'];
		$members[$id_member] = $row;
	}
	$db->free_result($request);

	return $members;
}

/**
 * Removes a subscription from the system
 * Updates members group subscriptions for members whose group associations
 * were related to the subscription
 *
 * @param int $id
 */
function deleteSubscription($id)
{
	$db = database();

	// Removing it, first lets see if anyone is subscribed
	$members = loadAllSubsctiptions($id);
	if (!empty($members))
	{
		$changes = array();

		// Get the specifics of this subscription
		$sub_detail = getSubscriptionDetails($id);

		// Do we need to reset the primary group?
		if (!empty($sub_detail['prim_group']))
		{
			// If this subscription changed the primary group, change it back
			foreach ($members as $id_member => $member_data)
			{
				if ($member_data['old_id_group'] != $member_data['id_group'] && $member_data['id_group'] == $sub_detail['prim_group'])
					$changes[$id_member]['id_group'] = $member_data['old_id_group'];
			}
		}

		// Did the subscription add secondary groups that we now must remove?
		if (!empty($sub_detail['add_groups']))
		{
			foreach ($members as $id_member => $member_data)
			{
				$current_groups = explode(',', $member_data['additional_groups']);
				$non_sub_groups = array_diff($current_groups, $sub_detail['add_groups']);

				// If they have any of the subscription groups, remove them
				if (implode(',', $non_sub_groups) != $member_data['additional_groups'])
					$changes[$id_member]['additional_groups'] = $non_sub_groups;
			}
		}

		// Apply the group changes, if there are any
		if (!empty($changes))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			foreach ($changes as $id_member => $new_values)
				updateMemberData($id_member, $new_values);
		}
	}

	// Remove the subscription as well
	$db->query('', '
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
 * @param mixed[] $insert
 */
function insertSubscription($insert)
{
	$db = database();

	$db->insert('',
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

	return $db->insert_id('{db_prefix}subscriptions', 'id_subscribe');
}

/**
 * Used to count active subscriptions.
 *
 * @param int $sub_id
 * @return int
 */
function countActiveSubscriptions($sub_id)
{
	$db = database();

	// Don't do groups if there are active members
	$request = $db->query('', '
		SELECT
			COUNT(*)
		FROM {db_prefix}log_subscribed
		WHERE id_subscribe = {int:current_subscription}
			AND status = {int:is_active}',
		array(
			'current_subscription' => $sub_id,
			'is_active' => 1,
		)
	);
	list ($isActive) = $db->fetch_row($request);
	$db->free_result($request);

	return $isActive;
}

/**
 * Updates a changed subscription.
 *
 * @param mixed[] $update
 * @param int $ignore_active - used to ignore already active subscriptions.
 */
function updateSubscription($update, $ignore_active)
{
	$db = database();

	$db->query('substring', '
		UPDATE {db_prefix}subscriptions
			SET name = SUBSTRING({string:name}, 1, 60), description = SUBSTRING({string:description}, 1, 255), active = {int:is_active},
			length = SUBSTRING({string:length}, 1, 4), cost = {string:cost}' . ($ignore_active ? '' : ', id_group = {int:id_group},
			add_groups = {string:additional_groups}') . ', repeatable = {int:repeatable}, allow_partial = {int:allow_partial},
			email_complete = {string:email_complete}, reminder = {int:reminder}
		WHERE id_subscribe = {int:current_subscription}',
		array(
			'is_active' => $update['is_active'],
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
			'email_complete' => $update['email_complete'],
		)
	);
}

/**
 * Update a non-recurrent subscription
 * (one-time payment)
 *
 * @param mixed[] $subscription_info
 */
function updateNonrecurrent($subscription_info)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_subscribed
		SET payments_pending = {int:payments_pending}, pending_details = {string:pending_details}
		WHERE id_sublog = {int:current_subscription_item}',
		array(
			'payments_pending' => $subscription_info['payments_pending'],
			'current_subscription_item' => $subscription_info['id_sublog'],
			'pending_details' => $subscription_info['pending_details'],
		)
	);
}

/**
 * Get the details from a given subscription.
 *
 * @param int $sub_id
 * @return array
 */
function getSubscriptionDetails($sub_id)
{
	$db = database();

	$request = $db->query('', '
		SELECT
			id_subscribe, name, description, cost, length, id_group, add_groups, active, repeatable,
			allow_partial, email_complete, reminder
		FROM {db_prefix}subscriptions
		WHERE id_subscribe = {int:current_subscription}
		LIMIT 1',
		array(
			'current_subscription' => $sub_id,
		)
	);
	$subscription = array();
	while ($row = $db->fetch_assoc($request))
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

		$subscription = array(
			'id' => $row['id_subscribe'],
			'name' => $row['name'],
			'desc' => $row['description'],
			'cost' => Util::unserialize($row['cost']),
			'span' => array(
				'value' => $span_value,
				'unit' => $span_unit,
			),
			'prim_group' => $row['id_group'],
			'add_groups' => explode(',', $row['add_groups']),
			'active' => $row['active'],
			'repeatable' => $row['repeatable'],
			'allow_partial' => $row['allow_partial'],
			'duration' => $row['length'] == 'F' ? 'flexible' : 'fixed',
			'email_complete' => htmlspecialchars($row['email_complete'], ENT_COMPAT, 'UTF-8'),
			'reminder' => $row['reminder'],
		);
	}
	$db->free_result($request);

	return $subscription;
}

/**
 * Used to validate an existing subscription ID.
 *
 * @param int $id
 *
 * @return int
 * @throws Elk_Exception no_access
 */
function validateSubscriptionID($id)
{
	$db = database();

	$request = $db->query('', '
		SELECT
			id_subscribe
		FROM {db_prefix}log_subscribed
		WHERE id_sublog = {int:current_log_item}
		LIMIT 1',
		array(
			'current_log_item' => $id,
		)
	);
	list ($sub_id) = $db->fetch_row($request);
	$db->free_result($request);

	// Humm this should not happen, if it does, boom
	if ($sub_id === null)
		throw new Elk_Exception('no_access', false);

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
	$db = database();

	// Ensure the member doesn't already have a subscription!
	$request = $db->query('', '
		SELECT
			id_subscribe
		FROM {db_prefix}log_subscribed
		WHERE id_subscribe = {int:current_subscription}
			AND id_member = {int:current_member}',
		array(
			'current_subscription' => $id_sub,
			'current_member' => $id_member,
		)
	);
	$result = $db->num_rows($request) != 0;
	$db->free_result($request);

	return $result;
}

/**
 * Get the current status from a given subscription.
 *
 * @param int $log_id
 *
 * @return int
 * @throws Elk_Exception no_access
 */
function getSubscriptionStatus($log_id)
{
	$db = database();

	$status = array();

	$request = $db->query('', '
		SELECT
			id_member, status
		FROM {db_prefix}log_subscribed
		WHERE id_sublog = {int:current_log_item}
		LIMIT 1',
		array(
			'current_log_item' => $log_id,
		)
	);
	if ($db->num_rows($request) !== 0)
		list ($status['id_member'], $status['old_status']) = $db->fetch_row($request);
	$db->free_result($request);

	// Nothing found?
	if (empty($status))
		throw new Elk_Exception('no_access', false);

	return $status;
}

/**
 * Somebody paid again? we need to log that.
 *
 * @param int[] $item
 */
function updateSubscriptionItem($item)
{
	$db = database();

	$db->query('', '
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
 * When a refund is processed, this either removes it or sets a new end time to
 * reflect its no longer re-occurring
 *
 * @param mixed[] $subscription_info the subscription information array
 * @param int $member_id
 * @param int $time
 */
function handleRefund($subscription_info, $member_id, $time)
{
	$db = database();

	// If the end time subtracted by current time is not greater than the duration
	// (length of subscription), then we close it.
	if ($subscription_info['end_time'] - time() < $subscription_info['length'])
	{
		// Delete user subscription.
		removeSubscription($subscription_info['id_subscribe'], $member_id);
		$subscription_act = time();
		$status = 0;
	}
	else
	{
		loadSubscriptions();
		$subscription_act = $subscription_info['end_time'] - $time;
		$status = 1;
	}

	// Mark it as complete so we have a record.
	$db->query('', '
		UPDATE {db_prefix}log_subscribed
		SET end_time = {int:current_time}
		WHERE id_subscribe = {int:current_subscription}
			AND id_member = {int:current_member}
			AND status = {int:status}',
		array(
			'current_time' => $subscription_act,
			'current_subscription' => $subscription_info['id_subscribe'],
			'current_member' => $member_id,
			'status' => $status,
		)
	);
}

/**
 * Want to delete a subscription? Prepare the delete for the members as well.
 *
 * @param int[] $toDelete
 * @return array $delete
 */
function prepareDeleteSubscriptions($toDelete)
{
	$db = database();

	$request = $db->query('', '
		SELECT
			id_subscribe, id_member
		FROM {db_prefix}log_subscribed
		WHERE id_sublog IN ({array_int:subscription_list})',
		array(
			'subscription_list' => $toDelete,
		)
	);
	$delete = array();
	while ($row = $db->fetch_assoc($request))
	{
		$delete[$row['id_subscribe']] = $row['id_member'];
	}
	$db->free_result($request);

	return $delete;
}

/**
 * Get all the pending subscriptions for a specific subscription id
 *
 * @param int $log_id
 * @return array
 */
function getPendingSubscriptions($log_id)
{
	$db = database();

	$request = $db->query('', '
		SELECT
			ls.id_sublog, ls.id_subscribe, ls.id_member,
			start_time, end_time, status, payments_pending, pending_details, COALESCE(mem.real_name, {string:blank_string}) AS username
		FROM {db_prefix}log_subscribed AS ls
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ls.id_member)
		WHERE ls.id_sublog = {int:current_subscription_item}
		LIMIT 1',
		array(
			'current_subscription_item' => $log_id,
			'blank_string' => '',
		)
	);
	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	return $row;
}

/**
 * Somebody paid the first time? Let's log ...
 *
 * @param mixed[] $details associative array for the insert
 */
function logSubscription($details)
{
	$db = database();

	$db->insert('',
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
 * Somebody paid the first time? Let's log
 *
 * @param int $sub_id
 * @param int $memID
 * @param string $pending_details
 */
function logNewSubscription($sub_id, $memID, $pending_details)
{
	$db = database();

	$db->insert('',
		'{db_prefix}log_subscribed',
		array(
			'id_subscribe' => 'int', 'id_member' => 'int', 'status' => 'int', 'payments_pending' => 'int',
			'pending_details' => 'string-65534', 'start_time' => 'int', 'vendor_ref' => 'string-255',
		),
		array(
			$sub_id, $memID, 0, 0, $pending_details, time(), '',
		),
		array('id_sublog')
	);
}

/**
 * Updated details for a pending subscription? Logging.
 *
 * @param int $sub_id
 * @param string $details
 */
function updatePendingSubscription($sub_id, $details)
{
	$db = database();

	// Update the entry.
	$db->query('', '
		UPDATE {db_prefix}log_subscribed
		SET payments_pending = payments_pending - 1, pending_details = {string:pending_details}
		WHERE id_sublog = {int:current_subscription_item}',
		array(
			'current_subscription_item' => $sub_id,
			'pending_details' => $details,
		)
	);
}

/**
 * Updates the number of pending subscriptions for a given product and user
 *
 * @param int $pending_count
 * @param int $sub_id
 * @param int $memID
 * @param string $details
 */
function updatePendingSubscriptionCount($pending_count, $sub_id, $memID, $details)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_subscribed
		SET payments_pending = {int:pending_count}, pending_details = {string:pending_details}
		WHERE id_sublog = {int:current_subscription_item}
			AND id_member = {int:selected_member}',
		array(
			'pending_count' => $pending_count,
			'current_subscription_item' => $sub_id,
			'selected_member' => $memID,
			'pending_details' => $details,
		)
	);
}

/**
 * Update a pending payment for a member
 * Generally used to change the status from prepay to payback to indicate that the user completed
 * the order screen and was redirected to the thank you screen (from the gateway).
 * Note the payment is still pending until the gateway posts to subscriptions.php and its validated
 *
 * @param int $sub_id
 * @param int $memID
 * @param string $details
 */
function updatePendingStatus($sub_id, $memID, $details)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_subscribed
		SET payments_pending = payments_pending + 1, pending_details = {string:pending_details}
		WHERE id_sublog = {int:current_subscription_id}
			AND id_member = {int:selected_member}',
		array(
			'current_subscription_id' => $sub_id,
			'selected_member' => $memID,
			'pending_details' => $details,
		)
	);
}

/**
 * Removes a subscription from a user, as in removes the groups.
 *
 * @param int $id_subscribe
 * @param int $id_member
 * @param boolean $delete
 */
function removeSubscription($id_subscribe, $id_member, $delete = false)
{
	global $context;

	$db = database();

	loadSubscriptions();

	// Load the user core bits.
	require_once(SUBSDIR . '/Members.subs.php');
	$member_info = getBasicMemberData($id_member, array('moderation' => true));

	// Just in case of errors.
	if (empty($member_info))
	{
		$db->query('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $id_member,
			)
		);

		return;
	}

	// Get all of the subscriptions for this user that are active - it will be necessary!
	$request = $db->query('', '
		SELECT
			id_subscribe, old_id_group
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
	$member = array();
	$member['id_group'] = 0;
	$new_id_group = -1;
	while ($row = $db->fetch_assoc($request))
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
	$db->free_result($request);

	// Now, for everything we are removing check they definitely are not allowed it.
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

	// Update the member
	assignGroupsToMember($id_member, $member_info['id_group'], $existingGroups);

	// Disable the subscription.
	if (!$delete)
		$db->query('', '
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
		$db->query('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE id_member = {int:current_member}
				AND id_subscribe = {int:current_subscription}',
			array(
				'current_member' => $id_member,
				'current_subscription' => $id_subscribe,
			)
		);
}
