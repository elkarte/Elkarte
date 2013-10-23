<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Count the notifications of the current user
 * callback for createList in action_list of Notification_Controller
 *
 * @param bool $all: if true counts all the notifications, otherwise only the unread
 * @param string $type: the type of the notification
 */
function countUserNotifications($all = false, $type = '')
{
	global $user_info;

	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_notifications
		WHERE id_member = {int:current_user}' . ($all ? '
			AND status != {int:is_not_deleted}' : '
			AND status = {int:is_not_read}') . (empty($type) ? '' : '
			AND notif_type = {string:current_type}'),
		array(
			'current_user' => $user_info['id'],
			'current_type' => $type,
			'is_not_read' => 0,
			'is_not_deleted' => 2,
		)
	);

	list($count) = $db->fetch_row($request);
	$db->free_result($request);

	// Counts as maintenance! :P
	updateMemberdata($user_info['id'], array('notifications' => $count));

	return $count;
}

/**
 * Obviously retrieve all the info to render the notifications page
 * for the current user
 * callback for createList in action_list of Notification_Controller
 *
 * @param int Query starts sending results from here
 * @param int Number of notifications returned
 * @param string Sorting
 * @param bool if show all notifications or only unread ones
 * @param string $type: the type of the notification
 */
function getUserNotifications($start, $limit, $sort, $all = false, $type = '')
{
	global $user_info;

	$db = database();

	$request = $db->query('', '
		SELECT n.id_msg, n.id_member_from, n.log_time, n.notif_type,
			m.subject, m.id_topic, m.id_board,
			IFNULL(men.real_name, m.poster_name) as mentioner, men.avatar, men.email_address,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
		FROM {db_prefix}log_notifications as n
			LEFT JOIN {db_prefix}messages AS m ON (n.id_msg = m.id_msg)
			LEFT JOIN {db_prefix}members AS men ON (n.id_member_from = men.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = men.id_member)
		WHERE n.id_member = {int:current_user}' . ($all ? '
			AND n.status != {int:is_not_deleted}' : '
			AND n.status = {int:is_not_read}') . (empty($type) ? '' : '
			AND n.notif_type = {string:current_type}') . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:limit}',
		array(
			'current_user' => $user_info['id'],
			'current_type' => $type,
			'is_not_read' => 0,
			'is_not_deleted' => 2,
			'start' => $start,
			'limit' => $limit,
			'sort' => $sort,
		)
	);

	$notifications = array();
	while ($row = $db->fetch_assoc($request))
	{
		$row['avatar'] = determineAvatar($row);
		$notifications[] = $row;
	}
	$db->free_result($request);

	return $notifications;
}

/**
 * Inserts a new notification
 *
 * @param int the id of the member notifying
 * @param array an array of ids of the members notified
 * @param int the id of the message involved in the notification
 * @param string the type of notification
 */
function addNotifications($member_from, $members_to, $msg, $type, $time = null, $status = null)
{
	$inserts = array();

	foreach ($members_to as $id_member)
		$inserts[] = array(
			$id_member,
			$msg,
			$status === null ? 0 : $status,
			$member_from,
			$time === null ? time() : $time,
			$type
		);

	if (empty($inserts))
		return;

	$db = database();

	// @todo 'ignore' so that if a notification already exists is not added again...hopefully
	$db->insert('ignore',
		'{db_prefix}log_notifications',
		array(
			'id_member' => 'int',
			'id_msg' => 'int',
			'status' => 'int',
			'id_member_from' => 'int',
			'log_time' => 'int',
			'notif_type' => 'string-5',
		),
		$inserts,
		array('id_member', 'id_msg', 'id_member_from', 'log_time', 'notif_type')
	);
}

/**
 * Mark a notification for a certain member as read
 *
 * @param int the notified member
 * @param int the message the member was notified for
 * @param string the type of notification
 * @param int id member that notified
 * @param int the time it was notified
 */
function markNotificationAsRead($id_member, $msg, $type, $id_member_from, $log_time)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_notifications
		SET status = 1
		WHERE id_member = {int:member}
			AND id_msg = {string:msg}
			AND notif_type = {string:notif_type}
			AND id_member_from = {int:member_from}
			AND log_time = {int:log_time}
		LIMIT 1',
		array(
			'member' => $id_member,
			'msg' => $msg,
			'notif_type' => $type,
			'member_from' => $id_member_from,
			'log_time' => $log_time,
		)
	);

	return $db->affected_rows() != 0;
}

/**
 * Delete a certain notification for a certain member
 * I'm using a "soft-delete" because otherwise anyway we have to remember
 * when a user was already notified for a certain message (e.g. in case of editing)
 *
 * @param int the notified member
 * @param int the message the member was notified for
 * @param string the type of notification
 * @param int id member that notified
 * @param int the time it was notified
 */
function deleteNotification($id_member, $msg, $type, $id_member_from, $log_time)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_notifications
		SET status = 2
		WHERE id_member = {int:member}
			AND id_msg = {string:msg}
			AND notif_type = {string:notif_type}
			AND id_member_from = {int:member_from}
			AND log_time = {int:log_time}
		LIMIT 1',
		array(
			'member' => $id_member,
			'msg' => $msg,
			'notif_type' => $type,
			'member_from' => $id_member_from,
			'log_time' => $log_time,
		)
	);

	return $db->affected_rows() != 0;
}

function toggleNotificationsApproval($msgs, $approved)
{
	$db = database();

	$db->query('','
		UPDATE {db_prefix}log_notifications
		SET status = {int:status}
		WHERE id_msg IN ({array_int:messages})',
		array(
			'messages' => $msgs,
			'status' => $approved ? 0 : 3,
		)
	);
}