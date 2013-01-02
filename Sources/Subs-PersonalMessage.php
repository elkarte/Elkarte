<?php

/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
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
 * This file handles tasks related to personal messages. It performs all
 * the necessary (database updates, statistics updates) to add, delete, mark
 * etc personal messages.
 *
 * The functions in this file do NOT check permissions.
 *
 */

if (!defined('DIALOGO'))
	die('Hacking attempt...');

function loadMessageLimit()
{
	global $user_info, $context, $smcFunc;

	if ($user_info['is_admin'])
		$context['message_limit'] = 0;
	elseif (($context['message_limit'] = cache_get_data('msgLimit:' . $user_info['id'], 360)) === null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT MAX(max_messages) AS top_limit, MIN(max_messages) AS bottom_limit
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:users_groups})',
			array(
				'users_groups' => $user_info['groups'],
			)
		);
		list ($maxMessage, $minMessage) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['message_limit'] = $minMessage == 0 ? 0 : $maxMessage;

		// Save us doing it again!
		cache_put_data('msgLimit:' . $user_info['id'], $context['message_limit'], 360);
	}
}

function loadPMLabels()
{
	global $user_info, $context, $smcFunc;

	// Looks like we need to reseek!
	$result = $smcFunc['db_query']('', '
		SELECT labels, is_read, COUNT(*) AS num
		FROM {db_prefix}pm_recipients
		WHERE id_member = {int:current_member}
			AND deleted = {int:not_deleted}
		GROUP BY labels, is_read',
		array(
			'current_member' => $user_info['id'],
			'not_deleted' => 0,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$this_labels = explode(',', $row['labels']);
		foreach ($this_labels as $this_label)
		{
			$context['labels'][(int) $this_label]['messages'] += $row['num'];
			if (!($row['is_read'] & 1))
				$context['labels'][(int) $this_label]['unread_messages'] += $row['num'];
		}
	}
	$smcFunc['db_free_result']($result);

	// Store it please!
	cache_put_data('labelCounts:' . $user_info['id'], $context['labels'], 720);
}

function getPMCount($descending = false, $pmID = null, $labelQuery)
{
	global $user_info, $context, $smcFunc;

	// Figure out how many messages there are.
	if ($context['folder'] == 'sent')
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT id_pm_head' : '*') . ')
			FROM {db_prefix}personal_messages
			WHERE id_member_from = {int:current_member}
				AND deleted_by_sender = {int:not_deleted}' . ($pmID !== null ? '
				AND id_pm ' . ($descending ? '>' : '<') . ' {int:id_pm}' : ''),
			array(
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
				'id_pm' => $pmID,
			)
		);
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
			FROM {db_prefix}pm_recipients AS pmr' . ($context['display_mode'] == 2 ? '
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)' : '') . '
			WHERE pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}' . $labelQuery . ($pmID !== null ? '
				AND pmr.id_pm ' . ($descending ? '>' : '<') . ' {int:id_pm}' : ''),
			array(
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
				'id_pm' => $pmID,
			)
		);
	}

	list ($count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $count;
}

/**
 * Delete the specified personal messages.
 *
 * @param array $personal_messages array of pm ids
 * @param string $folder = null
 * @param int $owner = null
 */
function deleteMessages($personal_messages, $folder = null, $owner = null)
{
	global $user_info, $smcFunc;

	if ($owner === null)
		$owner = array($user_info['id']);
	elseif (empty($owner))
		return;
	elseif (!is_array($owner))
		$owner = array($owner);

	if ($personal_messages !== null)
	{
		if (empty($personal_messages) || !is_array($personal_messages))
			return;

		foreach ($personal_messages as $index => $delete_id)
			$personal_messages[$index] = (int) $delete_id;

		$where = '
				AND id_pm IN ({array_int:pm_list})';
	}
	else
		$where = '';

	if ($folder == 'sent' || $folder === null)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}personal_messages
			SET deleted_by_sender = {int:is_deleted}
			WHERE id_member_from IN ({array_int:member_list})
				AND deleted_by_sender = {int:not_deleted}' . $where,
			array(
				'member_list' => $owner,
				'is_deleted' => 1,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);
	}
	if ($folder != 'sent' || $folder === null)
	{
		// Calculate the number of messages each member's gonna lose...
		$request = $smcFunc['db_query']('', '
			SELECT id_member, COUNT(*) AS num_deleted_messages, CASE WHEN is_read & 1 >= 1 THEN 1 ELSE 0 END AS is_read
			FROM {db_prefix}pm_recipients
			WHERE id_member IN ({array_int:member_list})
				AND deleted = {int:not_deleted}' . $where . '
			GROUP BY id_member, is_read',
			array(
				'member_list' => $owner,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);
		// ...And update the statistics accordingly - now including unread messages!.
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['is_read'])
				updateMemberData($row['id_member'], array('instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages']));
			else
				updateMemberData($row['id_member'], array('instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages'], 'unread_messages' => $where == '' ? 0 : 'unread_messages - ' . $row['num_deleted_messages']));

			// If this is the current member we need to make their message count correct.
			if ($user_info['id'] == $row['id_member'])
			{
				$user_info['messages'] -= $row['num_deleted_messages'];
				if (!($row['is_read']))
					$user_info['unread_messages'] -= $row['num_deleted_messages'];
			}
		}
		$smcFunc['db_free_result']($request);

		// Do the actual deletion.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pm_recipients
			SET deleted = {int:is_deleted}
			WHERE id_member IN ({array_int:member_list})
				AND deleted = {int:not_deleted}' . $where,
			array(
				'member_list' => $owner,
				'is_deleted' => 1,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);
	}

	// If sender and recipients all have deleted their message, it can be removed.
	$request = $smcFunc['db_query']('', '
		SELECT pm.id_pm AS sender, pmr.id_pm
		FROM {db_prefix}personal_messages AS pm
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.deleted = {int:not_deleted})
		WHERE pm.deleted_by_sender = {int:is_deleted}
			' . str_replace('id_pm', 'pm.id_pm', $where) . '
		GROUP BY sender, pmr.id_pm
		HAVING pmr.id_pm IS null',
		array(
			'not_deleted' => 0,
			'is_deleted' => 1,
			'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
		)
	);
	$remove_pms = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$remove_pms[] = $row['sender'];
	$smcFunc['db_free_result']($request);

	if (!empty($remove_pms))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}personal_messages
			WHERE id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $remove_pms,
			)
		);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}pm_recipients
			WHERE id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $remove_pms,
			)
		);
	}

	// Any cached numbers may be wrong now.
	cache_put_data('labelCounts:' . $user_info['id'], null, 720);
}

/**
 * Mark the specified personal messages read.
 *
 * @param array $personal_messages = null, array of pm ids
 * @param string $label = null, if label is set, only marks messages with that label
 * @param int $owner = null, if owner is set, marks messages owned by that member id
 */
function markMessages($personal_messages = null, $label = null, $owner = null)
{
	global $user_info, $context, $smcFunc;

	if ($owner === null)
		$owner = $user_info['id'];

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}pm_recipients
		SET is_read = is_read | 1
		WHERE id_member = {int:id_member}
			AND NOT (is_read & 1 >= 1)' . ($label === null ? '' : '
			AND FIND_IN_SET({string:label}, labels) != 0') . ($personal_messages !== null ? '
			AND id_pm IN ({array_int:personal_messages})' : ''),
		array(
			'personal_messages' => $personal_messages,
			'id_member' => $owner,
			'label' => $label,
		)
	);

	// If something wasn't marked as read, get the number of unread messages remaining.
	if ($smcFunc['db_affected_rows']() > 0)
	{
		if ($owner == $user_info['id'])
		{
			foreach ($context['labels'] as $label)
				$context['labels'][(int) $label['id']]['unread_messages'] = 0;
		}

		$result = $smcFunc['db_query']('', '
			SELECT labels, COUNT(*) AS num
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:id_member}
				AND NOT (is_read & 1 >= 1)
				AND deleted = {int:is_not_deleted}
			GROUP BY labels',
			array(
				'id_member' => $owner,
				'is_not_deleted' => 0,
			)
		);
		$total_unread = 0;
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			$total_unread += $row['num'];

			if ($owner != $user_info['id'])
				continue;

			$this_labels = explode(',', $row['labels']);
			foreach ($this_labels as $this_label)
				$context['labels'][(int) $this_label]['unread_messages'] += $row['num'];
		}
		$smcFunc['db_free_result']($result);

		// Need to store all this.
		cache_put_data('labelCounts:' . $owner, $context['labels'], 720);
		updateMemberData($owner, array('unread_messages' => $total_unread));

		// If it was for the current member, reflect this in the $user_info array too.
		if ($owner == $user_info['id'])
			$user_info['unread_messages'] = $total_unread;
	}
}

/**
 * Check if the PM is available to the current user.
 *
 * @param int $pmID
 * @param $validFor
 * @return boolean
 */
function isAccessiblePM($pmID, $validFor = 'in_or_outbox')
{
	global $user_info, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT
			pm.id_member_from = {int:id_current_member} AND pm.deleted_by_sender = {int:not_deleted} AS valid_for_outbox,
			pmr.id_pm IS NOT NULL AS valid_for_inbox
		FROM {db_prefix}personal_messages AS pm
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.id_member = {int:id_current_member} AND pmr.deleted = {int:not_deleted})
		WHERE pm.id_pm = {int:id_pm}
			AND ((pm.id_member_from = {int:id_current_member} AND pm.deleted_by_sender = {int:not_deleted}) OR pmr.id_pm IS NOT NULL)',
		array(
			'id_pm' => $pmID,
			'id_current_member' => $user_info['id'],
			'not_deleted' => 0,
		)
	);

	if ($smcFunc['db_num_rows']($request) === 0)
	{
		$smcFunc['db_free_result']($request);
		return false;
	}

	$validationResult = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	switch ($validFor)
	{
		case 'inbox':
			return !empty($validationResult['valid_for_inbox']);
		break;

		case 'outbox':
			return !empty($validationResult['valid_for_outbox']);
		break;

		case 'in_or_outbox':
			return !empty($validationResult['valid_for_inbox']) || !empty($validationResult['valid_for_outbox']);
		break;

		default:
			trigger_error('Undefined validation type given', E_USER_ERROR);
		break;
	}
}

/**
 * Sends a personal message from the specified person to the specified people
 * ($from defaults to the user)
 *
 * @param array $recipients - an array containing the arrays 'to' and 'bcc', both containing id_member's.
 * @param string $subject - should have no slashes and no html entities
 * @param string $message - should have no slashes and no html entities
 * @param bool $store_outbox
 * @param array $from - an array with the id, name, and username of the member.
 * @param int $pm_head - the ID of the chain being replied to - if any.
 * @return array, an array with log entries telling how many recipients were successful and which recipients it failed to send to.
 */
function sendpm($recipients, $subject, $message, $store_outbox = false, $from = null, $pm_head = 0)
{
	global $scripturl, $txt, $user_info, $language;
	global $modSettings, $smcFunc, $sourcedir;

	// Make sure the PM language file is loaded, we might need something out of it.
	loadLanguage('PersonalMessage');

	// Needed for our email and post functions
	require_once($sourcedir . '/Subs-Mail.php');
	require_once($sourcedir . '/Subs-Post.php');

	$onBehalf = $from !== null;

	// Initialize log array.
	$log = array(
		'failed' => array(),
		'sent' => array()
	);

	if ($from === null)
		$from = array(
			'id' => $user_info['id'],
			'name' => $user_info['name'],
			'username' => $user_info['username']
		);
	// Probably not needed.  /me something should be of the typer.
	else
		$user_info['name'] = $from['name'];

	// This is the one that will go in their inbox.
	$htmlmessage = $smcFunc['htmlspecialchars']($message, ENT_QUOTES);
	preparsecode($htmlmessage);
	$htmlsubject = strtr($smcFunc['htmlspecialchars']($subject), array("\r" => '', "\n" => '', "\t" => ''));
	if ($smcFunc['strlen']($htmlsubject) > 100)
		$htmlsubject = $smcFunc['substr']($htmlsubject, 0, 100);

	// Make sure is an array
	if (!is_array($recipients))
		$recipients = array($recipients);

	// Integrated PMs
	call_integration_hook('integrate_personal_message', array(&$recipients, &$from, &$subject, &$message));

	// Get a list of usernames and convert them to IDs.
	$usernames = array();
	foreach ($recipients as $rec_type => $rec)
	{
		foreach ($rec as $id => $member)
		{
			if (!is_numeric($recipients[$rec_type][$id]))
			{
				$recipients[$rec_type][$id] = $smcFunc['strtolower'](trim(preg_replace('/[<>&"\'=\\\]/', '', $recipients[$rec_type][$id])));
				$usernames[$recipients[$rec_type][$id]] = 0;
			}
		}
	}

	if (!empty($usernames))
	{
		$request = $smcFunc['db_query']('pm_find_username', '
			SELECT id_member, member_name
			FROM {db_prefix}members
			WHERE ' . ($smcFunc['db_case_sensitive'] ? 'LOWER(member_name)' : 'member_name') . ' IN ({array_string:usernames})',
			array(
				'usernames' => array_keys($usernames),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if (isset($usernames[$smcFunc['strtolower']($row['member_name'])]))
				$usernames[$smcFunc['strtolower']($row['member_name'])] = $row['id_member'];
		$smcFunc['db_free_result']($request);

		// Replace the usernames with IDs. Drop usernames that couldn't be found.
		foreach ($recipients as $rec_type => $rec)
			foreach ($rec as $id => $member)
			{
				if (is_numeric($recipients[$rec_type][$id]))
					continue;

				if (!empty($usernames[$member]))
					$recipients[$rec_type][$id] = $usernames[$member];
				else
				{
					$log['failed'][$id] = sprintf($txt['pm_error_user_not_found'], $recipients[$rec_type][$id]);
					unset($recipients[$rec_type][$id]);
				}
			}
	}

	// Make sure there are no duplicate 'to' members.
	$recipients['to'] = array_unique($recipients['to']);

	// Only 'bcc' members that aren't already in 'to'.
	$recipients['bcc'] = array_diff(array_unique($recipients['bcc']), $recipients['to']);

	// Combine 'to' and 'bcc' recipients.
	$all_to = array_merge($recipients['to'], $recipients['bcc']);

	// Check no-one will want it deleted right away!
	$request = $smcFunc['db_query']('', '
		SELECT
			id_member, criteria, is_or
		FROM {db_prefix}pm_rules
		WHERE id_member IN ({array_int:to_members})
			AND delete_pm = {int:delete_pm}',
		array(
			'to_members' => $all_to,
			'delete_pm' => 1,
		)
	);
	$deletes = array();
	// Check whether we have to apply anything...
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$criteria = unserialize($row['criteria']);

		// Note we don't check the buddy status, cause deletion from buddy = madness!
		$delete = false;
		foreach ($criteria as $criterium)
		{
			$match = false;
			if (($criterium['t'] == 'mid' && $criterium['v'] == $from['id']) || ($criterium['t'] == 'gid' && in_array($criterium['v'], $user_info['groups'])) || ($criterium['t'] == 'sub' && strpos($subject, $criterium['v']) !== false) || ($criterium['t'] == 'msg' && strpos($message, $criterium['v']) !== false))
				$delete = true;
			// If we're adding and one criteria don't match then we stop!
			elseif (!$row['is_or'])
			{
				$delete = false;
				break;
			}
		}
		if ($delete)
			$deletes[$row['id_member']] = 1;
	}
	$smcFunc['db_free_result']($request);

	// Load the membergrounp message limits.
	static $message_limit_cache = array();
	if (!allowedTo('moderate_forum') && empty($message_limit_cache))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group, max_messages
			FROM {db_prefix}membergroups',
			array(
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$message_limit_cache[$row['id_group']] = $row['max_messages'];
		$smcFunc['db_free_result']($request);
	}

	// Load the groups that are allowed to read PMs.
	// @todo move into a separate function on $permission.
	$allowed_groups = array();
	$disallowed_groups = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_group, add_deny
		FROM {db_prefix}permissions
		WHERE permission = {string:read_permission}',
		array(
			'read_permission' => 'pm_read',
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (empty($row['add_deny']))
			$disallowed_groups[] = $row['id_group'];
		else
			$allowed_groups[] = $row['id_group'];
	}

	$smcFunc['db_free_result']($request);

	if (empty($modSettings['permission_enable_deny']))
		$disallowed_groups = array();

	$request = $smcFunc['db_query']('', '
		SELECT
			member_name, real_name, id_member, email_address, lngfile,
			pm_email_notify, instant_messages,' . (allowedTo('moderate_forum') ? ' 0' : '
			(pm_receive_from = {int:admins_only}' . (empty($modSettings['enable_buddylist']) ? '' : ' OR
			(pm_receive_from = {int:buddies_only} AND FIND_IN_SET({string:from_id}, buddy_list) = 0) OR
			(pm_receive_from = {int:not_on_ignore_list} AND FIND_IN_SET({string:from_id}, pm_ignore_list) != 0)') . ')') . ' AS ignored,
			FIND_IN_SET({string:from_id}, buddy_list) != 0 AS is_buddy, is_activated,
			additional_groups, id_group, id_post_group
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:recipients})
		ORDER BY lngfile
		LIMIT {int:count_recipients}',
		array(
			'not_on_ignore_list' => 1,
			'buddies_only' => 2,
			'admins_only' => 3,
			'recipients' => $all_to,
			'count_recipients' => count($all_to),
			'from_id' => $from['id'],
		)
	);
	$notifications = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Don't do anything for members to be deleted!
		if (isset($deletes[$row['id_member']]))
			continue;

		// We need to know this members groups.
		$groups = explode(',', $row['additional_groups']);
		$groups[] = $row['id_group'];
		$groups[] = $row['id_post_group'];

		$message_limit = -1;

		// For each group see whether they've gone over their limit - assuming they're not an admin.
		if (!in_array(1, $groups))
		{
			foreach ($groups as $id)
			{
				if (isset($message_limit_cache[$id]) && $message_limit != 0 && $message_limit < $message_limit_cache[$id])
					$message_limit = $message_limit_cache[$id];
			}

			if ($message_limit > 0 && $message_limit <= $row['instant_messages'])
			{
				$log['failed'][$row['id_member']] = sprintf($txt['pm_error_data_limit_reached'], $row['real_name']);
				unset($all_to[array_search($row['id_member'], $all_to)]);
				continue;
			}

			// Do they have any of the allowed groups?
			if (count(array_intersect($allowed_groups, $groups)) == 0 || count(array_intersect($disallowed_groups, $groups)) != 0)
			{
				$log['failed'][$row['id_member']] = sprintf($txt['pm_error_user_cannot_read'], $row['real_name']);
				unset($all_to[array_search($row['id_member'], $all_to)]);
				continue;
			}
		}

		// Note that PostgreSQL can return a lowercase t/f for FIND_IN_SET
		if (!empty($row['ignored']) && $row['ignored'] != 'f' && $row['id_member'] != $from['id'])
		{
			$log['failed'][$row['id_member']] = sprintf($txt['pm_error_ignored_by_user'], $row['real_name']);
			unset($all_to[array_search($row['id_member'], $all_to)]);
			continue;
		}

		// If the receiving account is banned (>=10) or pending deletion (4), refuse to send the PM.
		if ($row['is_activated'] >= 10 || ($row['is_activated'] == 4 && !$user_info['is_admin']))
		{
			$log['failed'][$row['id_member']] = sprintf($txt['pm_error_user_cannot_read'], $row['real_name']);
			unset($all_to[array_search($row['id_member'], $all_to)]);
			continue;
		}

		// Send a notification, if enabled - taking the buddy list into account.
		if (!empty($row['email_address']) && ($row['pm_email_notify'] == 1 || ($row['pm_email_notify'] > 1 && (!empty($modSettings['enable_buddylist']) && $row['is_buddy']))) && $row['is_activated'] == 1)
			$notifications[empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']][] = $row['email_address'];

		$log['sent'][$row['id_member']] = sprintf(isset($txt['pm_successfully_sent']) ? $txt['pm_successfully_sent'] : '', $row['real_name']);
	}
	$smcFunc['db_free_result']($request);

	// Only 'send' the message if there are any recipients left.
	if (empty($all_to))
		return $log;

	// Insert the message itself and then grab the last insert id.
	$smcFunc['db_insert']('',
		'{db_prefix}personal_messages',
		array(
			'id_pm_head' => 'int', 'id_member_from' => 'int', 'deleted_by_sender' => 'int',
			'from_name' => 'string-255', 'msgtime' => 'int', 'subject' => 'string-255', 'body' => 'string-65534',
		),
		array(
			$pm_head, $from['id'], ($store_outbox ? 0 : 1),
			$from['username'], time(), $htmlsubject, $htmlmessage,
		),
		array('id_pm')
	);
	$id_pm = $smcFunc['db_insert_id']('{db_prefix}personal_messages', 'id_pm');

	// Add the recipients.
	if (!empty($id_pm))
	{
		// If this is new we need to set it part of it's own conversation.
		if (empty($pm_head))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}personal_messages
				SET id_pm_head = {int:id_pm_head}
				WHERE id_pm = {int:id_pm_head}',
				array(
					'id_pm_head' => $id_pm,
				)
			);

		// Some people think manually deleting personal_messages is fun... it's not. We protect against it though :)
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}pm_recipients
			WHERE id_pm = {int:id_pm}',
			array(
				'id_pm' => $id_pm,
			)
		);

		$insertRows = array();
		$to_list = array();
		foreach ($all_to as $to)
		{
			$insertRows[] = array($id_pm, $to, in_array($to, $recipients['bcc']) ? 1 : 0, isset($deletes[$to]) ? 1 : 0, 1);
			if (!in_array($to, $recipients['bcc']))
				$to_list[] = $to;
		}

		$smcFunc['db_insert']('insert',
			'{db_prefix}pm_recipients',
			array(
				'id_pm' => 'int', 'id_member' => 'int', 'bcc' => 'int', 'deleted' => 'int', 'is_new' => 'int'
			),
			$insertRows,
			array('id_pm', 'id_member')
		);
	}

	censorText($subject);
	if (empty($modSettings['disallow_sendBody']))
	{
		censorText($message);
		$message = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc(htmlspecialchars($message), false), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));
	}
	else
		$message = '';

	$to_names = array();
	if (count($to_list) > 1)
	{
		$request = $smcFunc['db_query']('', '
			SELECT real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:to_members})',
			array(
				'to_members' => $to_list,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$to_names[] = un_htmlspecialchars($row['real_name']);
		$smcFunc['db_free_result']($request);
	}

	$replacements = array(
		'SUBJECT' => $subject,
		'MESSAGE' => $message,
		'SENDER' => un_htmlspecialchars($from['name']),
		'READLINK' => $scripturl . '?action=pm;pmsg=' . $id_pm . '#msg' . $id_pm,
		'REPLYLINK' => $scripturl . '?action=pm;sa=send;f=inbox;pmsg=' . $id_pm . ';quote;u=' . $from['id'],
		'TOLIST' => implode(', ', $to_names),
	);
	$email_template = 'new_pm' . (empty($modSettings['disallow_sendBody']) ? '_body' : '') . (!empty($to_names) ? '_tolist' : '');

	foreach ($notifications as $lang => $notification_list)
	{
		$mail = loadEmailTemplate($email_template, $replacements, $lang);

		// Off the notification email goes!
		sendmail($notification_list, $mail['subject'], $mail['body'], null, 'p' . $id_pm, false, 2, null, true);
	}

	// Integrated After PMs
	call_integration_hook('integrate_personal_message_after', array(&$id_pm, &$log, &$recipients, &$from, &$subject, &$message));

	// Back to what we were on before!
	loadLanguage('index+PersonalMessage');

	// Add one to their unread and read message counts.
	foreach ($all_to as $k => $id)
	{
		if (isset($deletes[$id]))
			unset($all_to[$k]);
	}

	if (!empty($all_to))
		updateMemberData($all_to, array('instant_messages' => '+', 'unread_messages' => '+', 'new_pm' => 1));

	return $log;
}
