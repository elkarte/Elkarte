<?php

/**
 * Functions that deal with the database work involved with mentions
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Count the mentions of the current user
 * callback for createList in action_list of Mentions_Controller
 *
 * @package Mentions
 * @param bool $all : if true counts all the mentions, otherwise only the unread
 * @param string[]|string $type : the type of the mention can be a string or an array of strings.
 * @param string|null $id_member : the id of the member the counts are for, defaults to user_info['id']
 */
function countUserMentions($all = false, $type = '', $id_member = null)
{
	global $user_info;
	static $counts;

	$db = database();
	$id_member = $id_member === null ? $user_info['id'] : (int) $id_member;

	if (isset($counts[$id_member]))
		return $counts[$id_member];

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_mentions as mtn
		WHERE mtn.id_member = {int:current_user}
			AND mtn.is_accessible = {int:is_accessible}
			AND mtn.status IN ({array_int:status})' . (empty($type) ? '' : (is_array($type) ? '
			AND mtn.mention_type IN ({array_string:current_type})' : '
			AND mtn.mention_type = {string:current_type}')),
		array(
			'current_user' => $id_member,
			'current_type' => $type,
			'status' => $all ? array(0, 1) : array(0),
			'is_accessible' => 1,
		)
	);
	list ($counts[$id_member]) = $db->fetch_row($request);
	$db->free_result($request);

	// Counts as maintenance! :P
	if ($all === false && empty($type))
	{
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($id_member, array('mentions' => $counts[$id_member]));
	}

	return $counts[$id_member];
}

/**
 * Retrieve all the info to render the mentions page for the current user
 * callback for createList in action_list of Mentions_Controller
 *
 * @package Mentions
 * @param int $start Query starts sending results from here
 * @param int $limit Number of mentions returned
 * @param string $sort Sorting
 * @param bool $all if show all mentions or only unread ones
 * @param string[]|string $type : the type of the mention can be a string or an array of strings.
 */
function getUserMentions($start, $limit, $sort, $all = false, $type = '')
{
	global $user_info;

	$db = database();

	return $db->fetchQueryCallback('
		SELECT
			mtn.id_mention, mtn.id_target, mtn.id_member_from, mtn.log_time, mtn.mention_type, mtn.status,
			m.subject, m.id_topic, m.id_board,
			IFNULL(mem.real_name, m.poster_name) as mentioner, mem.avatar, mem.email_address,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
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
		LIMIT {int:start}, {int:limit}',
		array(
			'current_user' => $user_info['id'],
			'current_type' => $type,
			'status' => $all ? array(0, 1) : array(0),
			'is_accessible' => 1,
			'start' => $start,
			'limit' => $limit,
			'sort' => $sort,
		),
		function($row)
		{
			$row['avatar'] = determineAvatar($row);
			return $row;
		}
	);
}

/**
 * Inserts a new mention
 * Checks if the mention already exists (in any status) to prevent any duplicates
 *
 * @package Mentions
 * @param int $member_from the id of the member mentioning
 * @param int[] $members_to an array of ids of the members mentioned
 * @param int $target the id of the target involved in the mention
 * @param string $type the type of mention
 * @param string|null $time optional value to set the time of the mention, defaults to now
 * @param string|null $status optional value to set a status, defaults to 0
 * @param bool|null $is_accessible optional if the mention is accessible to the user
 *
 * @deprecated since 1.1 - Use Mentioning::create() instead
 */
function addMentions($member_from, $members_to, $target, $type, $time = null, $status = null, $is_accessible = null)
{
	$inserts = array();

	$db = database();

	// $time is not checked because it's useless
	$existing = $db->fetchQueryCallback('
		SELECT id_member
		FROM {db_prefix}log_mentions
		WHERE id_member IN ({array_int:members_to})
			AND mention_type = {string:type}
			AND id_member_from = {int:member_from}
			AND id_target = {int:target}
			AND log_time = {int:log_time}',
		array(
			'members_to' => $members_to,
			'type' => $type,
			'member_from' => $member_from,
			'target' => $target,
			'log_time' => $time === null ? time() : $time,
		),
		function($row)
		{
			return $row['id_member'];
		}
	);

	// If the member has already been mentioned, it's not necessary to do it again
	foreach ($members_to as $id_member)
	{
		if (!in_array($id_member, $existing))
		{
			$inserts[] = array(
				$id_member,
				$target,
				$status === null ? 0 : $status,
				$is_accessible === null ? 1 : $is_accessible,
				$member_from,
				$time === null ? time() : $time,
				$type
			);
		}
	}

	if (empty($inserts))
		return;

	// Insert the new mentions
	$db->insert('',
		'{db_prefix}log_mentions',
		array(
			'id_member' => 'int',
			'id_target' => 'int',
			'status' => 'int',
			'is_accessible' => 'int',
			'id_member_from' => 'int',
			'log_time' => 'int',
			'mention_type' => 'string-12',
		),
		$inserts,
		array('id_mention')
	);

	// Update the member mention count
	foreach ($inserts as $insert)
		updateMentionMenuCount($insert[2], $insert[0]);
}

/**
 * Softly and gently removes a 'likemsg' mention when the post is unliked
 *
 * @package Mentions
 * @param int $member_from the id of the member mentioning
 * @param int[] $members_to an array of ids of the members mentioned
 * @param int $target the id of the message involved in the mention
 * @param int $newstatus status to change the mention to if found as unread,
 *             - default is to set it as read (status = 1)
 * @deprecated since 1.1 use Mentioning::create() instead
 */
function rlikeMentions($member_from, $members_to, $target, $newstatus = 1)
{
	$db = database();

	// If this like is still unread then we mark it as read and decrease the counter
	$db->query('', '
		UPDATE {db_prefix}log_mentions
		SET status = {int:status}
		WHERE id_member IN ({array_int:members_to})
			AND mention_type = {string:type}
			AND id_member_from = {int:member_from}
			AND id_target = {int:target}
			AND status = {int:unread}',
		array(
			'members_to' => $members_to,
			'type' => 'likemsg',
			'member_from' => $member_from,
			'target' => $target,
			'status' => $newstatus,
			'unread' => 0,
		)
	);

	// Update the member mention count
	foreach ($members_to as $member)
		updateMentionMenuCount($newstatus, $member);
}

/**
 * Changes a specific mention status for a member
 *
 * - Can be used to mark as read, new, deleted, etc
 * - note that delete is a "soft-delete" because otherwise anyway we have to remember
 * - when a user was already mentioned for a certain message (e.g. in case of editing)
 *
 * @package Mentions
 * @param int $id_mention the mention id in the db
 * @param int $status status to update, 'new' => 0, 'read' => 1, 'deleted' => 2, 'unapproved' => 3
 *
 * @deprecated since 1.1 - Use Mentioning::changestatus() instead
 */
function changeMentionStatus($id_mention, $status = 1)
{
	global $user_info;

	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_mentions
		SET status = {int:status}
		WHERE id_mention = {int:id_mention}',
		array(
			'id_mention' => $id_mention,
			'status' => $status,
		)
	);
	$success = $db->affected_rows() != 0;

	// Update the top level mentions count
	if ($success)
		updateMentionMenuCount($status, $user_info['id']);

	return $success;
}

/**
 * Completely remove from the database a set of mentions.
 *
 * Doesn't check permissions, access, anything. It just deletes everything.
 *
 * @package Mentions
 * @param int[] $id_mentions the mention ids
 */
function removeMentions($id_mentions)
{
	global $user_info;

	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}log_mentions
		WHERE id_mention IN ({array_int:id_mentions})',
		array(
			'id_mentions' => $id_mentions,
		)
	);
	$success = $db->affected_rows() != 0;

	// Update the top level mentions count
	if ($success)
		updateMentionMenuCount($status, $user_info['id']);

	return $success;
}

/**
 * Toggles a mention on/off
 *
 * - This is used to turn mentions on when a message is approved
 *
 * @package Mentions
 * @param int[] $msgs array of messages that you want to toggle
 * @param bool $approved direction of the toggle read / unread
 */
function toggleMentionsApproval($msgs, $approved)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_mentions
		SET status = {int:status}
		WHERE id_target IN ({array_int:messages})',
		array(
			'messages' => $msgs,
			'status' => $approved ? 0 : 3,
		)
	);

	// Update the mentions menu count for the members that have this message
	$status = $approved ? 0 : 3;
	$db->fetchQueryCallback('
		SELECT id_member, status
		FROM {db_prefix}log_mentions
		WHERE id_target IN ({array_int:messages})',
		array(
			'messages' => $msgs,
		),
		function($row) use ($status)
		{
			updateMentionMenuCount($status, $row['id_member']);
		}
	);
}

/**
 * Toggles a mention visibility on/off
 *
 * - if off is restored to visible,
 * - if on is switched to unvisible for all the users
 *
 * @package Mentions
 * @param string $type type of the mention that you want to toggle
 * @param bool $enable if true enables the mentions, otherwise disables them
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
		array(
			'type' => $type,
			'toggle' => 10,
		)
	);

	$db->query('', '
		UPDATE {db_prefix}log_mentions
		SET
			status = status ' . ($enable ? '-' : '+') . ' {int:toggle}
		WHERE mention_type = {string:type}
			AND status ' . ($enable ? '>=' : '<') . ' {int:toggle}
			AND is_accessible = 0',
		array(
			'type' => $type,
			'toggle' => -10,
		)
	);
}

/**
 * Toggles a bunch of mentions accessibility on/off
 *
 * @package Mentions
 * @param int[] $mentions an array of mention id
 * @param bool $access if true make the mentions accessible (if visible and other things), otherwise marks them as inaccessible
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
		array(
			'mentions' => $mentions,
		)
	);
}

/**
 * To validate access to read/unread/delete mentions
 *
 * - Called from the validation class
 *
 * @package Mentions
 * @param string $field
 * @param mixed[] $input
 * @param string|null $validation_parameters
 */
function validate_ownmention($field, $input, $validation_parameters = null)
{
	global $user_info;

	if (!isset($input[$field]))
		return;

	if (!findMemberMention($input[$field], $user_info['id']))
	{
		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}
}

/**
 * Provided a mentions id and a member id, checks if the mentions belongs to that user
 *
 * @package Mentions
 * @param integer $id_mention the id of an existing mention
 * @param integer $id_member id of a member
 * @return bool true if the mention belongs to the member, false otherwise
 */
function findMemberMention($id_mention, $id_member)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_mention
		FROM {db_prefix}log_mentions
		WHERE id_mention = {int:id_mention}
			AND id_member = {int:id_member}
		LIMIT 1',
		array(
			'id_mention' => $id_mention,
			'id_member' => $id_member,
		)
	);
	$return = $db->num_rows($request);
	$db->free_result($request);

	return !empty($return);
}

/**
 * Updates the mention count as a result of an action, read, new, delete, etc
 *
 * @package Mentions
 * @param int $status
 * @param int $member_id
 */
function updateMentionMenuCount($status, $member_id)
{
	require_once(SUBSDIR . '/Members.subs.php');

	// If its new add to our menu count
	if ($status === 0)
		updateMemberdata($member_id, array('mentions' => '+'));
	// Mark as read we decrease the count
	elseif ($status === 1)
		updateMemberdata($member_id, array('mentions' => '-'));
	// Deleting or unapproving may have been read or not, so a count is required
	else
		countUserMentions(false, '', $member_id);
}

/**
 * Retrieves the time the last notification of a certain member was added.
 *
 * @package Mentions
 * @param int $id_member
 * @return int A timestamp (log_time)
 */
function getTimeLastMention($id_member)
{
	$db = database();

	list ($request) = $db->fetchQuery('
		SELECT log_time
		FROM {db_prefix}log_mentions
		WHERE status = {int:status}
			AND id_member = {int:member}
		ORDER BY id_mention DESC
		LIMIT 1',
		array(
			'status' => 0,
			'member' => $id_member
		)
	);
	return $request['log_time'];
}

/**
 * Counts all the notifications received by a certain member after a certain time.
 *
 * @package Mentions
 * @param int $id_member
 * @param int $timestamp
 * @return int Number of new mentions
 */
function getNewMentions($id_member, $timestamp)
{
	$db = database();

	if (empty($timestamp))
	{
		list ($result) = $db->fetchQuery('
			SELECT COUNT(*) AS c
			FROM {db_prefix}log_mentions
			WHERE status = {int:status}
				AND id_member = {int:member}',
			array(
				'status' => 0,
				'member' => $id_member
			)
		);
	}
	else
	{
		list ($result) = $db->fetchQuery('
			SELECT COUNT(*) AS c
			FROM {db_prefix}log_mentions
			WHERE status = {int:status}
				AND log_time > {int:last_seen}
				AND id_member = {int:member}',
			array(
				'status' => 0,
				'last_seen' => $timestamp,
				'member' => $id_member
			)
		);
	}

	return $result['c'];
}