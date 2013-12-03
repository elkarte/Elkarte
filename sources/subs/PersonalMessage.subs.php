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
 * @version 1.0 Beta
 *
 * This file handles tasks related to personal messages. It performs all
 * the necessary (database updates, statistics updates) to add, delete, mark
 * etc personal messages.
 *
 * The functions in this file do NOT check permissions.
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Loads information about the users message limit.
 */
function loadMessageLimit()
{
	global $user_info, $context;

	$db = database();

	if ($user_info['is_admin'])
		$context['message_limit'] = 0;
	elseif (($context['message_limit'] = cache_get_data('msgLimit:' . $user_info['id'], 360)) === null)
	{
		$request = $db->query('', '
			SELECT MAX(max_messages) AS top_limit, MIN(max_messages) AS bottom_limit
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:users_groups})',
			array(
				'users_groups' => $user_info['groups'],
			)
		);
		list ($maxMessage, $minMessage) = $db->fetch_row($request);
		$db->free_result($request);

		$context['message_limit'] = $minMessage == 0 ? 0 : $maxMessage;

		// Save us doing it again!
		cache_put_data('msgLimit:' . $user_info['id'], $context['message_limit'], 360);
	}
}

/**
 * Loads the list of PM labels.
 */
function loadPMLabels()
{
	global $user_info, $context;

	$db = database();

	// Looks like we need to reseek!
	$result = $db->query('', '
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
	while ($row = $db->fetch_assoc($result))
	{
		$this_labels = explode(',', $row['labels']);
		foreach ($this_labels as $this_label)
		{
			$context['labels'][(int) $this_label]['messages'] += $row['num'];
			if (!($row['is_read'] & 1))
				$context['labels'][(int) $this_label]['unread_messages'] += $row['num'];
		}
	}
	$db->free_result($result);

	// Store it please!
	cache_put_data('labelCounts:' . $user_info['id'], $context['labels'], 720);
}

/**
 * Get the number of PMs.
 *
 * @param bool $descending
 * @param int $pmID
 * @param int $labelQuery
 * @return int
 */
function getPMCount($descending = false, $pmID = null, $labelQuery = '')
{
	global $user_info, $context;

	$db = database();

	// Figure out how many messages there are.
	if ($context['folder'] == 'sent')
	{
		$request = $db->query('', '
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
		$request = $db->query('', '
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

	list ($count) = $db->fetch_row($request);
	$db->free_result($request);

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
	global $user_info;

	$db = database();

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
		$db->query('', '
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
		$request = $db->query('', '
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
		while ($row = $db->fetch_assoc($request))
		{
			if ($row['is_read'])
				updateMemberData($row['id_member'], array('personal_messages' => $where == '' ? 0 : 'personal_messages - ' . $row['num_deleted_messages']));
			else
				updateMemberData($row['id_member'], array('personal_messages' => $where == '' ? 0 : 'personal_messages - ' . $row['num_deleted_messages'], 'unread_messages' => $where == '' ? 0 : 'unread_messages - ' . $row['num_deleted_messages']));

			// If this is the current member we need to make their message count correct.
			if ($user_info['id'] == $row['id_member'])
			{
				$user_info['messages'] -= $row['num_deleted_messages'];
				if (!($row['is_read']))
					$user_info['unread_messages'] -= $row['num_deleted_messages'];
			}
		}
		$db->free_result($request);

		// Do the actual deletion.
		$db->query('', '
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
	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
		$remove_pms[] = $row['sender'];
	$db->free_result($request);

	if (!empty($remove_pms))
	{
		$db->query('', '
			DELETE FROM {db_prefix}personal_messages
			WHERE id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $remove_pms,
			)
		);

		$db->query('', '
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
	global $user_info, $context;

	$db = database();

	if ($owner === null)
		$owner = $user_info['id'];

	$db->query('', '
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
	if ($db->affected_rows() > 0)
	{
		if ($owner == $user_info['id'])
		{
			foreach ($context['labels'] as $label)
				$context['labels'][(int) $label['id']]['unread_messages'] = 0;
		}

		$result = $db->query('', '
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
		while ($row = $db->fetch_assoc($result))
		{
			$total_unread += $row['num'];

			if ($owner != $user_info['id'])
				continue;

			$this_labels = explode(',', $row['labels']);
			foreach ($this_labels as $this_label)
				$context['labels'][(int) $this_label]['unread_messages'] += $row['num'];
		}
		$db->free_result($result);

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
	global $user_info;

	$db = database();

	$request = $db->query('', '
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

	if ($db->num_rows($request) === 0)
	{
		$db->free_result($request);
		return false;
	}

	$validationResult = $db->fetch_assoc($request);
	$db->free_result($request);

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
function sendpm($recipients, $subject, $message, $store_outbox = true, $from = null, $pm_head = 0)
{
	global $scripturl, $txt, $user_info, $language;
	global $modSettings, $webmaster_email;

	$db = database();

	// Make sure the PM language file is loaded, we might need something out of it.
	loadLanguage('PersonalMessage');

	// Needed for our email and post functions
	require_once(SUBSDIR . '/Mail.subs.php');
	require_once(SUBSDIR . '/Post.subs.php');

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
	$htmlmessage = Util::htmlspecialchars($message, ENT_QUOTES);
	preparsecode($htmlmessage);
	$htmlsubject = strtr(Util::htmlspecialchars($subject), array("\r" => '', "\n" => '', "\t" => ''));
	if (Util::strlen($htmlsubject) > 100)
		$htmlsubject = Util::substr($htmlsubject, 0, 100);

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
				$recipients[$rec_type][$id] = Util::strtolower(trim(preg_replace('/[<>&"\'=\\\]/', '', $recipients[$rec_type][$id])));
				$usernames[$recipients[$rec_type][$id]] = 0;
			}
		}
	}

	if (!empty($usernames))
	{
		$request = $db->query('pm_find_username', '
			SELECT id_member, member_name
			FROM {db_prefix}members
			WHERE ' . (defined('DB_CASE_SENSITIVE') ? 'LOWER(member_name)' : 'member_name') . ' IN ({array_string:usernames})',
			array(
				'usernames' => array_keys($usernames),
			)
		);
		while ($row = $db->fetch_assoc($request))
			if (isset($usernames[Util::strtolower($row['member_name'])]))
				$usernames[Util::strtolower($row['member_name'])] = $row['id_member'];
		$db->free_result($request);

		// Replace the usernames with IDs. Drop usernames that couldn't be found.
		foreach ($recipients as $rec_type => $rec)
		{
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
	}

	// Make sure there are no duplicate 'to' members.
	$recipients['to'] = array_unique($recipients['to']);

	// Only 'bcc' members that aren't already in 'to'.
	$recipients['bcc'] = array_diff(array_unique($recipients['bcc']), $recipients['to']);

	// Combine 'to' and 'bcc' recipients.
	$all_to = array_merge($recipients['to'], $recipients['bcc']);

	// Check no-one will want it deleted right away!
	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
	{
		$criteria = unserialize($row['criteria']);

		// Note we don't check the buddy status, cause deletion from buddy = madness!
		$delete = false;
		foreach ($criteria as $criterium)
		{
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
	$db->free_result($request);

	// Load the membergrounp message limits.
	static $message_limit_cache = array();
	if (!allowedTo('moderate_forum') && empty($message_limit_cache))
	{
		$request = $db->query('', '
			SELECT id_group, max_messages
			FROM {db_prefix}membergroups',
			array(
			)
		);
		while ($row = $db->fetch_assoc($request))
			$message_limit_cache[$row['id_group']] = $row['max_messages'];
		$db->free_result($request);
	}

	// Load the groups that are allowed to read PMs.
	// @todo move into a separate function on $permission.
	$allowed_groups = array();
	$disallowed_groups = array();
	$request = $db->query('', '
		SELECT id_group, add_deny
		FROM {db_prefix}permissions
		WHERE permission = {string:read_permission}',
		array(
			'read_permission' => 'pm_read',
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		if (empty($row['add_deny']))
			$disallowed_groups[] = $row['id_group'];
		else
			$allowed_groups[] = $row['id_group'];
	}

	$db->free_result($request);

	if (empty($modSettings['permission_enable_deny']))
		$disallowed_groups = array();

	$request = $db->query('', '
		SELECT
			member_name, real_name, id_member, email_address, lngfile,
			pm_email_notify, personal_messages,' . (allowedTo('moderate_forum') ? ' 0' : '
			(receive_from = {int:admins_only}' . (empty($modSettings['enable_buddylist']) ? '' : ' OR
			(receive_from = {int:buddies_only} AND FIND_IN_SET({string:from_id}, buddy_list) = 0) OR
			(receive_from = {int:not_on_ignore_list} AND FIND_IN_SET({string:from_id}, pm_ignore_list) != 0)') . ')') . ' AS ignored,
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
	while ($row = $db->fetch_assoc($request))
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

			if ($message_limit > 0 && $message_limit <= $row['personal_messages'])
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
	$db->free_result($request);

	// Only 'send' the message if there are any recipients left.
	if (empty($all_to))
		return $log;

	// Track the pm count for our stats
	if (!empty($modSettings['trackStats']))
		trackStats(array('pm' => '+'));

	// Insert the message itself and then grab the last insert id.
	$db->insert('',
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
	$id_pm = $db->insert_id('{db_prefix}personal_messages', 'id_pm');

	// Add the recipients.
	if (!empty($id_pm))
	{
		// If this is new we need to set it part of it's own conversation.
		if (empty($pm_head))
			$db->query('', '
				UPDATE {db_prefix}personal_messages
				SET id_pm_head = {int:id_pm_head}
				WHERE id_pm = {int:id_pm_head}',
				array(
					'id_pm_head' => $id_pm,
				)
			);

		// Some people think manually deleting personal_messages is fun... it's not. We protect against it though :)
		$db->query('', '
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

		$db->insert('insert',
			'{db_prefix}pm_recipients',
			array(
				'id_pm' => 'int', 'id_member' => 'int', 'bcc' => 'int', 'deleted' => 'int', 'is_new' => 'int'
			),
			$insertRows,
			array('id_pm', 'id_member')
		);
	}

	censorText($subject);
	$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_pm_enabled']);

	// If they have post by email enabled, override disallow_sendBody
	if ($maillist)
	{
		require_once(SUBSDIR . '/Emailpost.subs.php');
		pbe_prepare_text($message, $subject);
	}
	elseif (empty($modSettings['disallow_sendBody']))
	{
		censorText($message);
		$message = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc(htmlspecialchars($message, ENT_COMPAT, 'UTF-8'), false), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));
	}
	else
		$message = '';

	$to_names = array();
	if (count($to_list) > 1)
	{
		require_once(SUBSDIR . '/Members.subs.php');
		$result = getBasicMemberData($to_list);
		foreach ($result as $row)
			$to_names[] = un_htmlspecialchars($row['real_name']);
	}

	$replacements = array(
		'SUBJECT' => $subject,
		'MESSAGE' => $message,
		'SENDER' => un_htmlspecialchars($from['name']),
		'READLINK' => $scripturl . '?action=pm;pmsg=' . $id_pm . '#msg' . $id_pm,
		'REPLYLINK' => $scripturl . '?action=pm;sa=send;f=inbox;pmsg=' . $id_pm . ';quote;u=' . $from['id'],
		'TOLIST' => implode(', ', $to_names),
	);

	// Select the right template
	$email_template = ($maillist ? 'pbe_' : '') . 'new_pm' . (empty($modSettings['disallow_sendBody']) ? '_body' : '') . (!empty($to_names) ? '_tolist' : '');

	foreach ($notifications as $lang => $notification_list)
	{
		// Using maillist functionality
		if ($maillist)
		{
			$sender_details = query_sender_wrapper($from['id']);
			$from_wrapper = !empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']);

			// Add in the signature
			$replacements['SIGNATURE'] = $sender_details['signature'];

			// And off it goes, looking a bit more personal
			$mail = loadEmailTemplate($email_template, $replacements, $lang);
			$reference = !empty($pm_head) ? $pm_head : null;
			sendmail($notification_list, $mail['subject'], $mail['body'], $from['name'], 'p' . $id_pm, false, 2, null, true, $from_wrapper, $reference);
		}
		else
		{
			// Off the notification email goes!
			$mail = loadEmailTemplate($email_template, $replacements, $lang);
			sendmail($notification_list, $mail['subject'], $mail['body'], null, 'p' . $id_pm, false, 2, null, true);
		}
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
		updateMemberData($all_to, array('personal_messages' => '+', 'unread_messages' => '+', 'new_pm' => 1));

	return $log;
}

/**
 * Mark personal messages as read (no new messages)
 * for a particular member.
 *
 * @param int $memberID member id
 */
function markPMsRead($memberID)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}pm_recipients
		SET is_new = {int:not_new}
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $memberID,
			'not_new' => 0,
		)
	);
}

/**
 * Load personal messages.
 *
 * This function loads messages considering the options given, an array of:
 * 'display_mode' - the PMs display mode (i.e. conversation, all)
 * 'is_postgres' - (temporary) boolean to allow choice of PostgreSQL-specific sorting query
 * 'sort_by_query' - query to sort by
 * 'descending' - whether to sort descending
 * 'sort_by' - field to sort by
 * 'pmgs' - personal message id (if any). Note: it may not be set.
 * 'label_query' - query by labels
 * 'start' - start id, if any
 *
 * @param array $pm_options options for loading
 * @param int $id_member id member
 */
function loadPMs($pm_options, $id_member)
{
	global $options;

	$db = database();

	// First work out what messages we need to see - if grouped is a little trickier...
	// Conversation mode
	if ($pm_options['display_mode'] === 2)
	{
		// On a non-default sort due to PostgreSQL we have to do a harder sort.
		if ($db->db_title() == 'PostgreSQL' && $pm_options['sort_by_query'] != 'pm.id_pm')
		{
			$sub_request = $db->query('', '
				SELECT MAX({raw:sort}) AS sort_param, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm' . ($pm_options['folder'] == 'sent' ? ($pm_options['sort_by'] == 'name' ? '
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
						AND pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:not_deleted}
						' . $pm_options['label_query'] . ')') . ($pm_options['sort_by'] == 'name' ? ( '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})') : '') . '
				WHERE ' . ($pm_options['folder'] == 'sent' ? 'pm.id_member_from = {int:current_member}
					AND pm.deleted_by_sender = {int:not_deleted}' : '1=1') . (empty($pm_options['pmsg']) ? '' : '
					AND pm.id_pm = {int:id_pm}') . '
				GROUP BY pm.id_pm_head
				ORDER BY sort_param' . ($pm_options['descending'] ? ' DESC' : ' ASC') . (empty($pm_options['pmsg']) ? '
				LIMIT ' . $pm_options['start'] . ', ' . $pm_options['limit'] : ''),
				array(
					'current_member' => $id_member,
					'not_deleted' => 0,
					'id_member' => $pm_options['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
					'id_pm' => isset($pm_options['pmsg']) ? $pm_options['pmsg'] : '0',
					'sort' => $pm_options['sort_by_query'],
				)
			);
			$sub_pms = array();
			while ($row = $db->fetch_assoc($sub_request))
				$sub_pms[$row['id_pm_head']] = $row['sort_param'];

			$db->free_result($sub_request);

			$request = $db->query('', '
				SELECT pm.id_pm AS id_pm, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm' . ($pm_options['folder'] == 'sent' ? ($pm_options['sort_by'] == 'name' ? '
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
						AND pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:not_deleted}
						' . $pm_options['label_query'] . ')') . ($pm_options['sort_by'] == 'name' ? ( '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})') : '') . '
				WHERE ' . (empty($sub_pms) ? '0=1' : 'pm.id_pm IN ({array_int:pm_list})') . '
				ORDER BY ' . ($pm_options['sort_by_query'] == 'pm.id_pm' && $pm_options['folder'] != 'sent' ? 'id_pm' : '{raw:sort}') . ($pm_options['descending'] ? ' DESC' : ' ASC') . (empty($pm_options['pmsg']) ? '
				LIMIT ' . $pm_options['start'] . ', ' . $pm_options['limit'] : ''),
				array(
					'current_member' => $id_member,
					'pm_list' => array_keys($sub_pms),
					'not_deleted' => 0,
					'sort' => $pm_options['sort_by_query'],
					'id_member' => $pm_options['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
				)
			);
		}
		else
		{
			$request = $db->query('pm_conversation_list', '
				SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm' . ($pm_options['folder'] == 'sent' ? ($pm_options['sort_by'] == 'name' ? '
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
						AND pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:deleted_by}
						' . $pm_options['label_query'] . ')') . ($pm_options['sort_by'] == 'name' ? ( '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
				WHERE ' . ($pm_options['folder'] == 'sent' ? 'pm.id_member_from = {int:current_member}
					AND pm.deleted_by_sender = {int:deleted_by}' : '1=1') . (empty($pm_options['pmsg']) ? '' : '
					AND pm.id_pm = {int:pmsg}') . '
				GROUP BY pm.id_pm_head
				ORDER BY ' . ($pm_options['sort_by_query'] == 'pm.id_pm' && $pm_options['folder'] != 'sent' ? 'id_pm' : '{raw:sort}') . ($pm_options['descending'] ? ' DESC' : ' ASC') . (isset($pm_options['pmsg']) ? '
				LIMIT ' . $pm_options['start'] . ', ' . $pm_options['limit'] : ''),
				array(
					'current_member' => $id_member,
					'deleted_by' => 0,
					'sort' => $pm_options['sort_by_query'],
					'pm_member' => $pm_options['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
					'pmsg' => isset($pm_options['pmsg']) ? (int) $pm_options['pmsg'] : 0,
				)
			);
		}
	}
	// This is kinda simple!
	else
	{
		// @todo SLOW This query uses a filesort. (inbox only.)
		$request = $db->query('', '
			SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from
			FROM {db_prefix}personal_messages AS pm' . ($pm_options['folder'] == 'sent' ? '' . ($pm_options['sort_by'] == 'name' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
					AND pmr.id_member = {int:current_member}
					AND pmr.deleted = {int:is_deleted}
					' . $pm_options['label_query'] . ')') . ($pm_options['sort_by'] == 'name' ? ( '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
			WHERE ' . ($pm_options['folder'] == 'sent' ? 'pm.id_member_from = {raw:current_member}
				AND pm.deleted_by_sender = {int:is_deleted}' : '1=1') . (empty($pm_options['pmsg']) ? '' : '
				AND pm.id_pm = {int:pmsg}') . '
			ORDER BY ' . ($pm_options['sort_by_query'] == 'pm.id_pm' && $pm_options['folder'] != 'sent' ? 'pmr.id_pm' : '{raw:sort}') . ($pm_options['descending'] ? ' DESC' : ' ASC') . (isset($pm_options['pmsg']) ? '
			LIMIT ' . $pm_options['start'] . ', ' . $pm_options['limit'] : ''),
			array(
				'current_member' => $id_member,
				'is_deleted' => 0,
				'sort' => $pm_options['sort_by_query'],
				'pm_member' => $pm_options['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
				'pmsg' => isset($pm_options['pmsg']) ? (int) $pm_options['pmsg'] : 0,
			)
		);
	}
	// Load the id_pms and initialize recipients.
	$pms = array();
	$lastData = array();
	$posters = $pm_options['folder'] == 'sent' ? array($id_member) : array();
	$recipients = array();

	while ($row = $db->fetch_assoc($request))
	{
		if (!isset($recipients[$row['id_pm']]))
		{
			if (isset($row['id_member_from']))
				$posters[$row['id_pm']] = $row['id_member_from'];
			$pms[$row['id_pm']] = $row['id_pm'];
			$recipients[$row['id_pm']] = array(
				'to' => array(),
				'bcc' => array()
			);
		}

		// Keep track of the last message so we know what the head is without another query!
		if ((empty($pm_options['pmid']) && (empty($options['view_newest_pm_first']) || !isset($lastData))) || empty($lastData) || (!empty($pm_options['pmid']) && $pm_options['pmid'] == $row['id_pm']))
			$lastData = array(
				'id' => $row['id_pm'],
				'head' => $row['id_pm_head'],
			);
	}
	$db->free_result($request);

	return array($pms, $posters, $recipients, $lastData);
}

/**
 * How many PMs have you sent lately?
 *
 * @param int $id_member id member
 * @param int $time time interval (in seconds)
 */
function pmCount($id_member, $time)
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(pr.id_pm) AS post_count
		FROM {db_prefix}personal_messages AS pm
			INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
		WHERE pm.id_member_from = {int:current_member}
			AND pm.msgtime > {int:msgtime}',
		array(
			'current_member' => $id_member,
			'msgtime' => time() - $time,
		)
	);
	list ($pmCount) = $db->fetch_row($request);
	$db->free_result($request);

	return $pmCount;
}

/**
 * This will apply rules to all unread messages.
 * If all_messages is set will, clearly, do it to all!
 *
 * @param bool $all_messages = false
 */
function applyRules($all_messages = false)
{
	global $user_info, $context, $options;

	$db = database();

	// Want this - duh!
	loadRules();

	// No rules?
	if (empty($context['rules']))
		return;

	// Just unread ones?
	$ruleQuery = $all_messages ? '' : ' AND pmr.is_new = 1';

	// @todo Apply all should have timeout protection!
	// Get all the messages that match this.
	$request = $db->query('', '
		SELECT
			pmr.id_pm, pm.id_member_from, pm.subject, pm.body, mem.id_group, pmr.labels
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
		WHERE pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}
			' . $ruleQuery,
		array(
			'current_member' => $user_info['id'],
			'not_deleted' => 0,
		)
	);
	$actions = array();
	while ($row = $db->fetch_assoc($request))
	{
		foreach ($context['rules'] as $rule)
		{
			$match = false;

			// Loop through all the criteria hoping to make a match.
			foreach ($rule['criteria'] as $criterium)
			{
				if (($criterium['t'] == 'mid' && $criterium['v'] == $row['id_member_from']) || ($criterium['t'] == 'gid' && $criterium['v'] == $row['id_group']) || ($criterium['t'] == 'sub' && strpos($row['subject'], $criterium['v']) !== false) || ($criterium['t'] == 'msg' && strpos($row['body'], $criterium['v']) !== false))
					$match = true;
				// If we're adding and one criteria don't match then we stop!
				elseif ($rule['logic'] == 'and')
				{
					$match = false;
					break;
				}
			}

			// If we have a match the rule must be true - act!
			if ($match)
			{
				if ($rule['delete'])
					$actions['deletes'][] = $row['id_pm'];
				else
				{
					foreach ($rule['actions'] as $ruleAction)
					{
						if ($ruleAction['t'] == 'lab')
						{
							// Get a basic pot started!
							if (!isset($actions['labels'][$row['id_pm']]))
								$actions['labels'][$row['id_pm']] = empty($row['labels']) ? array() : explode(',', $row['labels']);
							$actions['labels'][$row['id_pm']][] = $ruleAction['v'];
						}
					}
				}
			}
		}
	}
	$db->free_result($request);

	// Deletes are easy!
	if (!empty($actions['deletes']))
		deleteMessages($actions['deletes']);

	// Relabel?
	if (!empty($actions['labels']))
	{
		foreach ($actions['labels'] as $pm => $labels)
		{
			// Quickly check each label is valid!
			$realLabels = array();
			foreach ($context['labels'] as $label)
				if (in_array($label['id'], $labels) && ($label['id'] != -1 || empty($options['pm_remove_inbox_label'])))
					$realLabels[] = $label['id'];

			$db->query('', '
				UPDATE {db_prefix}pm_recipients
				SET labels = {string:new_labels}
				WHERE id_pm = {int:id_pm}
					AND id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
					'id_pm' => $pm,
					'new_labels' => empty($realLabels) ? '' : implode(',', $realLabels),
				)
			);
		}
	}
}

/**
 * Load up all the rules for the current user.
 *
 * @param bool $reload = false
 */
function loadRules($reload = false)
{
	global $user_info, $context;

	$db = database();

	if (isset($context['rules']) && !$reload)
		return;

	$request = $db->query('', '
		SELECT
			id_rule, rule_name, criteria, actions, delete_pm, is_or
		FROM {db_prefix}pm_rules
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $user_info['id'],
		)
	);
	$context['rules'] = array();
	// Simply fill in the data!
	while ($row = $db->fetch_assoc($request))
	{
		$context['rules'][$row['id_rule']] = array(
			'id' => $row['id_rule'],
			'name' => $row['rule_name'],
			'criteria' => unserialize($row['criteria']),
			'actions' => unserialize($row['actions']),
			'delete' => $row['delete_pm'],
			'logic' => $row['is_or'] ? 'or' : 'and',
		);

		if ($row['delete_pm'])
			$context['rules'][$row['id_rule']]['actions'][] = array('t' => 'del', 'v' => 1);
	}
	$db->free_result($request);
}

/**
 * Update PM recipient when they receive or read a new PM
 *
 * @param int $id_member
 * @param $new = false
 */
function toggleNewPM($id_member, $new = false)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}pm_recipients
		SET is_new = ' . ($new ? '{int:new}' : '{int:not_new}') . '
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
			'new' => 1,
			'not_new' => 0
		)
	);
}

/**
 * Load the PM limits for each group or for a specified group
 *
 * @param int (optional) the id of a membergroup
 */
function loadPMLimits($id_group = false)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_group, group_name, max_messages
		FROM {db_prefix}membergroups' . ($id_group ? '
		WHERE id_group = {int:id_group}' : '
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name'),
		array(
			'id_group' => $id_group,
			'newbie_group' => 4,
		)
	);

	$groups = array();
	while ($row = $db->fetch_assoc($request))
		if ($row['id_group'] != 1)
			$groups[$row['id_group']] = $row;

	$db->free_result($request);

	return $groups;
}

/**
 * Retrieve the discussion one or more PMs belong to
 */
function getDiscussions($id_pms)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_pm_head, id_pm
		FROM {db_prefix}personal_messages
		WHERE id_pm IN ({array_int:id_pms})',
		array(
			'id_pms' => $id_pms,
		)
	);
	$pm_heads = array();
	while ($row = $db->fetch_assoc($request))
		$pm_heads[$row['id_pm_head']] = $row['id_pm'];
	$db->free_result($request);

	return $pm_heads;
}

/**
 * Return all the PMs belonging to one or more discussions
 */
function getPmsFromDiscussion($pm_heads)
{
	$db = database();

	$pms = array();
	$request = $db->query('', '
		SELECT id_pm, id_pm_head
		FROM {db_prefix}personal_messages
		WHERE id_pm_head IN ({array_int:pm_heads})',
		array(
			'pm_heads' => array_keys($pm_heads),
		)
	);
	// Copy the action from the single to PM to the others.
	while ($row = $db->fetch_assoc($request))
		$pms[$row['id_pm']] = $row['id_pm_head'];
	$db->free_result($request);

	return $pms;
}

/**
 * Determines the PMs which need an updated label.
 *
 * @param array $to_label
 * @param string $label_type
 * @param int $user_id
 * @return updatePMLabels
 */
function changePMLabels($to_label, $label_type, $user_id)
{
	global $options;

	$db = database();
	$labels = array();
	$to_update = array();

	// Get information about each message...
	$request = $db->query('', '
		SELECT id_pm, labels
		FROM {db_prefix}pm_recipients
		WHERE id_member = {int:current_member}
			AND id_pm IN ({array_int:to_label})
		LIMIT ' . count($to_label),
		array(
			'current_member' => $user_id,
			'to_label' => array_keys($to_label),
		)
	);

	while ($row = $db->fetch_assoc($request))
	{
		$labels = $row['labels'] == '' ? array('-1') : explode(',', trim($row['labels']));

		// Already exists?  Then... unset it!
		$ID_LABEL = array_search($to_label[$row['id_pm']], $labels);
		if ($ID_LABEL !== false && $label_type[$row['id_pm']] !== 'add')
			unset($labels[$ID_LABEL]);
		elseif ($label_type[$row['id_pm']] !== 'rem')
			$labels[] = $to_label[$row['id_pm']];

		if (!empty($options['pm_remove_inbox_label']) && $to_label[$row['id_pm']] != '-1' && ($key = array_search('-1', $labels)) !== false)
			unset($labels[$key]);

		$set = implode(',', array_unique($labels));
		if ($set == '')
			$set = '-1';

		$to_update[$row['id_pm']] = $set;
	}
	$db->free_result($request);

	if (!empty($to_update))
		return updatePMLabels($to_update);
}

/**
 * Detects personal messages which need  a new label.
 *
 * @param array $searchArray
 * @param array $new_labels
 * @param int $user_id
 * @return updatePMLabels
 */
function updateLabelsToPM($searchArray, $new_labels, $user_id)
{
	$db = database();

	// Now find the messages to change.
	$request = $db->query('', '
		SELECT id_pm, labels
		FROM {db_prefix}pm_recipients
		WHERE FIND_IN_SET({raw:find_label_implode}, labels) != 0
			AND id_member = {int:current_member}',
		array(
			'current_member' => $user_id,
			'find_label_implode' => '\'' . implode('\', labels) != 0 OR FIND_IN_SET(\'', $searchArray) . '\'',
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		// Do the long task of updating them...
		$toChange = explode(',', $row['labels']);

		foreach ($toChange as $key => $value)
			if (in_array($value, $searchArray))
			{
				if (isset($new_labels[$value]))
					$toChange[$key] = $new_labels[$value];
				else
					unset($toChange[$key]);
			}

		if (empty($toChange))
			$toChange[] = '-1';

		$to_update[$row['id_pm']] = implode(',', array_unique($toChange));
	}
	$db->free_result($request);

	if (!empty($to_update))
		return updatePMLabels($to_update, $user_id);
}

/**
 * Updates PMs with their new label.
 *
 * @param array $to_update
 * @param int $user_id
 * @return int
 */
function updatePMLabels($to_update, $user_id)
{
	$db = database();

	$updateErrors = 0;

	foreach ($to_update as $id_pm => $set)
	{
		// Check that this string isn't going to be too large for the database.
		if (strlen($set) > 60)
		{
			$updateErrors++;
			// Make the string as long as possible and update anyway
			$set = substr($set, 0, 60);
			$set = substr($set, 0, strrpos($set, ','));
		}

		$db->query('', '
			UPDATE {db_prefix}pm_recipients
			SET labels = {string:labels}
			WHERE id_pm = {int:id_pm}
				AND id_member = {int:current_member}',
			array(
				'current_member' => $user_id,
				'id_pm' => $id_pm,
				'labels' => $set,
			)
		);
	}

	return $updateErrors;
}

/**
 * Gets PMs older than a specific date.
 *
 * @param int $user_id the user's id.
 * @param int $time timestamp with a specific date
 * @return array
 */
function getPMsOlderThan($user_id, $time)
{
	$db = database();

	// Array to store the IDs in.
	$pm_ids = array();

	// Select all the messages they have sent older than $time.
	$request = $db->query('', '
		SELECT id_pm
		FROM {db_prefix}personal_messages
		WHERE deleted_by_sender = {int:not_deleted}
			AND id_member_from = {int:current_member}
			AND msgtime < {int:msgtime}',
		array(
			'current_member' => $user_id,
			'not_deleted' => 0,
			'msgtime' => $time,
		)
	);
	while ($row = $db->fetch_row($request))
		$pm_ids[] = $row[0];
	$db->free_result($request);

	// This is the inbox
	$request = $db->query('', '
		SELECT pmr.id_pm
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
		WHERE pmr.deleted = {int:not_deleted}
			AND pmr.id_member = {int:current_member}
			AND pm.msgtime < {int:msgtime}',
		array(
			'current_member' => $user_id,
			'not_deleted' => 0,
			'msgtime' => $time,
		)
	);
	while ($row = $db->fetch_row($request))
		$pm_ids[] = $row[0];
	$db->free_result($request);

	return $pm_ids;
}

/**
 * Used to delete PM rules from the given member.
 *
 * @param int $id_member
 * @param array $rule_changes
 */
function deletePMRules($id_member, $rule_changes)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}pm_rules
		WHERE id_rule IN ({array_int:rule_list})
		AND id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
			'rule_list' => $rule_changes,
		)
	);
}

/**
 * Updates a personal messaging rule action for the given member.
 *
 * @param int $id_rule
 * @param int $id_member
 * @param array $actions
 */
function updatePMRuleAction($id_rule, $id_member, $actions)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}pm_rules
		SET actions = {string:actions}
		WHERE id_rule = {int:id_rule}
			AND id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
			'id_rule' => $id_rule,
			'actions' => serialize($actions),
		)
	);
}

/**
 * Add a new PM rule to the database.
 *
 * @param int $id_member
 * @param string $ruleName
 * @param string $criteria
 * @param string $actions
 * @param int $doDelete
 * @param int $isOr
 */
function addPMRule($id_member, $ruleName, $criteria, $actions, $doDelete, $isOr)
{
	$db = database();

	$db->insert('',
		'{db_prefix}pm_rules',
		array(
			'id_member' => 'int', 'rule_name' => 'string', 'criteria' => 'string', 'actions' => 'string',
			'delete_pm' => 'int', 'is_or' => 'int',
		),
		array(
			$id_member, $ruleName, $criteria, $actions, $doDelete, $isOr,
		),
		array('id_rule')
	);
}

/**
 * Updates a personal messaging rule for the given member.
 *
 * @param int $id_member
 * @param int $id_rule
 * @param string $ruleName
 * @param string $criteria
 * @param string $actions
 * @param int $doDelete
 * @param int $isOr
 */
function updatePMRule($id_member, $id_rule, $ruleName, $criteria, $actions, $doDelete, $isOr)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}pm_rules
		SET rule_name = {string:rule_name}, criteria = {string:criteria}, actions = {string:actions},
			delete_pm = {int:delete_pm}, is_or = {int:is_or}
		WHERE id_rule = {int:id_rule}
			AND id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
			'delete_pm' => $doDelete,
			'is_or' => $isOr,
			'id_rule' => $id_rule,
			'rule_name' => $ruleName,
			'criteria' => $criteria,
			'actions' => $actions,
		)
	);
}

/**
 * Used to set a replied status for a given PM.
 *
 * @param int $id_member
 * @param int $replied_to
 */
function setPMRepliedStatus($id_member, $replied_to)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}pm_recipients
		SET is_read = is_read | 2
		WHERE id_pm = {int:replied_to}
			AND id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
			'replied_to' => $replied_to,
		)
	);
}