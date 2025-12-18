<?php

/**
 * Functions that deal with the database work involved with mentions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

use ElkArte\User;

/**
 * Counts the number of mentions for a user.
 *
 * @param bool $all Specifies whether to count all mentions or only unread.
 * @param string $type Specifies the type of mention to count. Empty string to count all types.
 * @param int|null $id_member Specifies the ID of the member. If null, the current user's ID will be used.
 *
 * @return int|array The total count of mentions if $type is empty, otherwise an array with counts for each type.
 */
function countUserMentions($all = false, $type = '', $id_member = null)
{
	static $counts;

	$db = database();
	$id_member = $id_member === null ? User::$info->id : (int) $id_member;

	if (isset($counts[$id_member][$type]))
	{
		return $counts[$id_member][$type];
	}

	$allTypes = getMentionTypes($id_member, $all === true ? 'system' : 'user');
	foreach ($allTypes as $thisType)
	{
		$counts[$id_member][$thisType] = 0;
	}
	$counts[$id_member]['total'] = 0;

	$db->fetchQuery('
		SELECT 
			mention_type, COUNT(*) AS cnt
		FROM {db_prefix}log_mentions as mtn
		WHERE mtn.id_member = {int:current_user}
			AND mtn.is_accessible = {int:is_accessible}
			AND mtn.status IN ({array_int:status})
			AND mtn.mention_type IN ({array_string:all_type})
		GROUP BY mtn.mention_type',
		[
			'current_user' => $id_member,
			'status' => $all ? [0, 1] : [0],
			'is_accessible' => 1,
			'all_type' => empty($allTypes) ? [$type] : $allTypes,
		]
	)->fetch_callback(function ($row) use (&$counts, $id_member) {
		$counts[$id_member][$row['mention_type']] = (int) $row['cnt'];
		$counts[$id_member]['total'] += $row['cnt'];
	});

	// Counts as maintenance! :P
	if ($all === false)
	{
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($id_member, ['mentions' => $counts[$id_member]['total']]);
	}

	return empty($type) ? $counts[$id_member]['total'] : $counts[$id_member][$type] ?? 0;
}

/**
 * Retrieve all the info to render the mentions page for the current user
 * callback for createList in action_list of \ElkArte\Controller\Mentions
 *
 * @param int $start Query starts sending results from here
 * @param int $limit Number of mentions returned
 * @param string $sort Sorting
 * @param bool $all if show all mentions or only unread ones
 * @param string[]|string $type : the type of the mention can be a string or an array of strings.
 *
 * @return array
 * @package Mentions
 *
 */
function getUserMentions($start, $limit, $sort, $all = false, $type = '')
{
	global $txt;

	$db = database();

	return $db->fetchQuery('
		SELECT
			mtn.id_mention, mtn.id_target, mtn.id_member_from, mtn.log_time, mtn.mention_type, mtn.status,
			m.subject, m.id_topic, m.id_board,
			COALESCE(mem.real_name, {string:guest_text}) as mentioner, mem.avatar, mem.email_address,
			COALESCE(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
		FROM {db_prefix}log_mentions AS mtn
			LEFT JOIN {db_prefix}messages AS m ON (mtn.id_target = m.id_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mtn.id_member_from = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
		WHERE mtn.id_member = {int:current_user}
			AND mtn.is_accessible = {int:is_accessible}
			AND mtn.status IN ({array_int:status})' . (empty($type) ? '' : (is_array($type) ? '
			AND mtn.mention_type IN ({array_string:current_type})' : '
			AND mtn.mention_type = {string:current_type}')) . '
		ORDER BY {raw:sort}
		LIMIT {int:limit} OFFSET {int:start} ',
		[
			'current_user' => User::$info->id,
			'current_type' => $type,
			'status' => $all ? [0, 1] : [0],
			'guest_text' => $txt['guest'],
			'is_accessible' => 1,
			'start' => $start,
			'limit' => $limit,
			'sort' => $sort,
		]
	)->fetch_callback(
		function ($row) {
			$row['avatar'] = determineAvatar($row);

			return $row;
		}
	);
}

/**
 * Completely remove from the database a set of mentions.
 *
 * Doesn't check permissions, access, anything. It just deletes everything.
 *
 * @param int[] $id_mentions the mention ids
 *
 * @return bool
 * @package Mentions
 *
 */
function removeMentions($id_mentions)
{
	$db = database();

	$request = $db->query('', '
		DELETE FROM {db_prefix}log_mentions
		WHERE id_mention IN ({array_int:id_mentions})',
		[
			'id_mentions' => $id_mentions,
		]
	);
	$success = $request->affected_rows() !== 0;

	// Update the top level mentions count
	if ($success)
	{
		updateMentionMenuCount(null, User::$info->id);
	}

	return $success;
}

/**
 * Toggles a mention on/off
 *
 * - This is used to turn mentions on when a message is approved
 *
 * @param int[] $msgs array of messages that you want to toggle
 * @param bool $approved direction of the toggle read / unread
 * @package Mentions
 */
function toggleMentionsApproval($msgs, $approved)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_mentions
		SET 
			status = {int:status}
		WHERE id_target IN ({array_int:messages})',
		[
			'messages' => $msgs,
			'status' => $approved ? 0 : 3,
		]
	);

	// Update the mentions menu count for the members that have this message
	$status = $approved ? 0 : 3;
	$db->fetchQuery('
		SELECT 
			id_member, status
		FROM {db_prefix}log_mentions
		WHERE id_target IN ({array_int:messages})',
		[
			'messages' => $msgs,
		]
	)->fetch_callback(
		function ($row) use ($status) {
			updateMentionMenuCount($status, $row['id_member']);
		}
	);
}

/**
 * Toggles a mention visibility on/off
 *
 * - If off is restored to visible,
 * - If on is switched to invisible for all the users
 *
 * @param string $type type of the mention that you want to toggle
 * @param bool $enable if true enables the mentions, otherwise disables them
 * @package Mentions
 */
function toggleMentionsVisibility($type, $enable)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_mentions
		SET
			status = status ' . ($enable ? '-' : '+') . ' {int:toggle}
		WHERE mention_type = {string:type}
			AND status ' . ($enable ? '>=' : '<') . ' {int:toggle}
			AND is_accessible = 1',
		[
			'type' => $type,
			'toggle' => 10,
		]
	);

	$db->query('', '
		UPDATE {db_prefix}log_mentions
		SET
			status = status ' . ($enable ? '+' : '-') . ' {int:toggle}
		WHERE mention_type = {string:type}
			AND status ' . ($enable ? '<' : '>=') . ' 0
			AND is_accessible = 0',
		[
			'type' => $type,
			'toggle' => 10,
		]
	);
}

/**
 * Toggles a bunch of mentions accessibility on/off
 *
 * @param int[] $mentions an array of mention id
 * @param bool $access if true make the mentions accessible (if visible and other things), otherwise marks them as inaccessible
 * @package Mentions
 */
function toggleMentionsAccessibility($mentions, $access)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_mentions
		SET
			is_accessible = CASE WHEN is_accessible = 1 THEN 0 ELSE 1 END
		WHERE id_mention IN ({array_int:mentions})
			AND is_accessible ' . ($access ? '=' : '!=') . ' 0',
		[
			'mentions' => $mentions,
		]
	);
}

/**
 * To validate access to read/unread/delete mentions
 *
 * - Called from the validation class via Mentioning.php
 *
 * @param string $field
 * @param array $input
 * @param string|null $validation_parameters
 *
 * @return array|void
 * @package Mentions
 *
 */
function validate_own_mention($field, $input, $validation_parameters = null)
{
	if (!isset($input[$field]))
	{
		return;
	}

	if (!findMemberMention($input[$field], User::$info->id))
	{
		return [
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		];
	}
}

/**
 * Provided a mentions id and a member id, checks if the mentions belongs to that user
 *
 * @param int $id_mention the id of an existing mention
 * @param int $id_member id of a member
 * @return bool true if the mention belongs to the member, false otherwise
 * @package Mentions
 */
function findMemberMention($id_mention, $id_member)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			id_mention
		FROM {db_prefix}log_mentions
		WHERE id_mention = {int:id_mention}
			AND id_member = {int:id_member}
		LIMIT 1',
		[
			'id_mention' => $id_mention,
			'id_member' => $id_member,
		]
	);
	$return = $request->num_rows();
	$request->free_result();

	return !empty($return);
}

/**
 * Updates the mention count as a result of an action, read, new, delete, etc
 *
 * @param int|null $status
 * @param int $member_id
 * @package Mentions
 */
function updateMentionMenuCount($status, $member_id)
{
	require_once(SUBSDIR . '/Members.subs.php');

	// If its new add to our menu count
	if ($status === 0)
	{
		updateMemberData($member_id, ['mentions' => '+']);
	}
	// Mark as read we decrease the count
	elseif ($status === 1)
	{
		updateMemberData($member_id, ['mentions' => '-']);
	}
	// Deleting or un-approving may have been read or not, so a count is required
	else
	{
		countUserMentions(false, '', $member_id);
	}
}

/**
 * Retrieves the time the last notification of a certain member was added.
 *
 * @param int $id_member
 * @return int A timestamp (log_time)
 * @package Mentions
 */
function getTimeLastMention($id_member)
{
	$db = database();

	$request = $db->fetchQuery('
		SELECT 
			log_time
		FROM {db_prefix}log_mentions
		WHERE status = {int:status}
			AND id_member = {int:member}
		ORDER BY id_mention DESC
		LIMIT 1',
		[
			'status' => 0,
			'member' => $id_member
		]
	);
	list ($log_time) = $request->fetch_row();
	$request->free_result();

	return empty($log_time) ? 0 : $log_time;
}

/**
 * Counts all the notifications received by a certain member after a certain time.
 *
 * @param int $id_member
 * @param int $timestamp
 * @return int Number of new mentions
 * @package Mentions
 */
function getNewMentions($id_member, $timestamp)
{
	$db = database();

	if (empty($timestamp))
	{
		$result = $db->fetchQuery('
			SELECT 
				COUNT(*) AS c
			FROM {db_prefix}log_mentions
			WHERE status = {int:status}
				AND id_member = {int:member}
				AND is_accessible = {int:has_access}',
			[
				'status' => 0,
				'has_access' => 1,
				'member' => $id_member
			]
		)->fetch_assoc();
	}
	else
	{
		$result = $db->fetchQuery('
			SELECT 
				COUNT(*) AS c
			FROM {db_prefix}log_mentions
			WHERE status = {int:status}
				AND log_time > {int:last_seen}
				AND id_member = {int:member}
				AND is_accessible = {int:has_access}',
			[
				'status' => 0,
				'has_access' => 1,
				'last_seen' => $timestamp,
				'member' => $id_member
			]
		)->fetch_assoc();
	}

	return (int) $result['c'];
}

/**
 * Get the available mention types for a user.
 *
 * @param int|null $user The user ID. If null, User::$info->id will be used.
 * @param string $type The type of mentions. "user" will return only those that the user has enabled and set
 * as on site notification.
 *
 * By default, will filter out notification types with a method set to none, e.g. the user has disabled that
 * type of mention.  Use type "system" to return everything, or type "user" to return only those
 * that they want on-site notifications.
 *
 * @return array The available mention types.
 */
function getMentionTypes($user, $type = 'user')
{
	require_once(SUBSDIR . '/Notification.subs.php');

	$user = $user ?? User::$info->id;

	$enabled = getEnabledNotifications();

	if ($type !== 'user')
	{
		sort($enabled);
		return $enabled;
	}

	$userAllEnabled = getUsersNotificationsPreferences($enabled, $user);

	// Drop ones they do not have enabled (primarily used to drop watchedtopic / watched board)
	foreach ($enabled as $key => $notificationType)
	{
		if (!isset($userAllEnabled[$user][$notificationType]))
		{
			unset($enabled[$key]);
		}
	}

	// Filter the remaining as requested
	foreach ($userAllEnabled[$user] as $notificationType => $allowedMethods)
	{
		if (!in_array('notification', $allowedMethods, true))
		{
			$key = array_search($notificationType, $enabled, true);
			if ($key !== false)
			{
				unset($enabled[$key]);
			}
		}
	}

	sort($enabled);
	return $enabled;
}

/**
 * Marks a set of notifications as read.
 *
 * Intended to be called when viewing a topic page.
 *
 * @param array $messages
 */
function markNotificationsRead($messages)
{
	// Guests can't mark notifications
	if (User::$info->is_guest || empty($messages))
	{
		return;
	}

	// These are the types associated with messages (where the id_target is a msg_id)
	$mentionTypes = ['mentionmem', 'likemsg', 'rlikemsg', 'quotedmem', 'watchedtopic', 'watchedboard'];
	$messages = is_array($messages) ? $messages : [$messages];
	$changes = [];

	// Find unread notifications for this group of messages for this member
	$db = database();
	$db->fetchQuery('
		SELECT 
			id_mention
		FROM {db_prefix}log_mentions
		WHERE status = {int:status}
			AND id_member = {int:member}
			AND id_target IN ({array_int:targets})
			AND mention_type IN ({array_string:mention_types})',
		[
			'status' => 0,
			'member' => User::$info->id,
			'targets' => $messages,
			'mention_types' => $mentionTypes,
		]
	)->fetch_callback(
	function ($row) use (&$changes) {
		$changes[] = (int) $row['id_mention'];
	});

	if (!empty($changes))
	{
		changeStatus(array_unique($changes), User::$info->id);
	}
}

/**
 * Change the status of mentions
 *
 * Updates the status of mentions in the database. Also updates the mentions count for the member.
 *
 *  - Can be used to mark as read, new, deleted, etc. a group of mention id's
 *  - Note that delete is a "soft-delete" because otherwise anyway we have to remember
 *  - When a user was already mentioned for a certain message (e.g., in case of editing)
 *
 * @param int|int[] $id_mentions The id(s) of the mentions to update
 * @param int $member_id The id of the member
 * @param int $status The new status for the mentions (default: 1)
 * @param bool $update Whether to update the mentions count (default: true)
 *
 * @return bool Returns true if the update was successful, false otherwise
 */
function changeStatus($id_mentions, $member_id, $status = 1, $update = true)
{
	$db = database();

	$id_mentions = is_array($id_mentions) ? $id_mentions : [$id_mentions];
	$status = $status ?? 1;

	$success = $db->query('', '
		UPDATE {db_prefix}log_mentions
		SET status = {int:status}
		WHERE id_mention IN ({array_int:id_mentions})',
		[
			'id_mentions' => $id_mentions,
			'status' => $status,
		]
	)->affected_rows() !== 0;

	// Update the mentions count
	if ($success && $update)
	{
		$number = count($id_mentions);
		require_once(SUBSDIR . '/Members.subs.php');

		// Increase the count by 1
		if ($number === 1 && $status === 0)
		{
			updateMemberData($member_id, ['mentions' => '+']);
			return true;
		}

		// Mark as read we decrease the count by 1
		if ($number === 1 && $status === 1)
		{
			updateMemberData($member_id, ['mentions' => '-']);
			return true;
		}

		// A full recount is required
		countUserMentions(false, '', $member_id);
	}

	return $success;
}
