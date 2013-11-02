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
 * @param bool $all : if true counts all the notifications, otherwise only the unread
 * @param string $type : the type of the notification
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

	list ($count) = $db->fetch_row($request);
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
 * @param int $start Query starts sending results from here
 * @param int $limit Number of notifications returned
 * @param string $sort Sorting
 * @param bool $all if show all notifications or only unread ones
 * @param string $type : the type of the notification
 */
function getUserNotifications($start, $limit, $sort, $all = false, $type = '')
{
	global $user_info;

	$db = database();

	$request = $db->query('', '
		SELECT n.id_notification, n.id_msg, n.id_member_from, n.log_time, n.notif_type, n.status,
			m.subject, m.id_topic, m.id_board,
			IFNULL(men.real_name, m.poster_name) as mentioner, men.avatar, men.email_address,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
		FROM {db_prefix}log_notifications AS n
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
 * @param int $member_from the id of the member notifying
 * @param array $members_to an array of ids of the members notified
 * @param int $msg the id of the message involved in the notification
 * @param string $type the type of notification
 * @param string $time optional value to set the time of the notification, defaults to now
 * @param string $status optional value to set a status, defaults to 0
 */
function addNotifications($member_from, $members_to, $msg, $type, $time = null, $status = null)
{
	$inserts = array();

	$db = database();

	// $time is not checked because it's useless
	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}log_notifications
		WHERE id_member IN ({array_int:members_to})
			AND notif_type = {string:type}
			AND id_member_from = {int:member_from}
			AND id_msg = {int:msg}',
		array(
			'members_to' => $members_to,
			'type' => $type,
			'member_from' => $member_from,
			'msg' => $msg,
		)
	);
	$existing = array();
	while ($row = $db->fetch_assoc($request))
		$existing[] = $row['id_member'];
	$db->free_result($request);

	foreach ($members_to as $id_member)
		if (!in_array($id_member, $existing))
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

	$db->insert('',
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
		array('id_notification')
	);
}

/**
 * Changes a specific notification status for a member
 * Can be used to mark as read, new, deleted, etc
 *
 * note that delete is a "soft-delete" because otherwise anyway we have to remember
 * when a user was already notified for a certain message (e.g. in case of editing)
 *
 * @param int $id_member the notified member
 * @param int $msg the message the member was notified for
 * @param string $type the type of notification
 * @param int $id_member_from id of member that notified
 * @param int $log_time the time it was notified
 * @param int $status status to update, 'new' => 0,	'read' => 1, 'deleted' => 2, 'unapproved' => 3
 */
function changeNotificationStatus($id_notification, $status = 1)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}log_notifications
		SET status = {int:status}
		WHERE id_notification = {int:id_notification}',
		array(
			'id_notification' => $id_notification,
			'status' => $status,
		)
	);

	return $db->affected_rows() != 0;
}

/**
 * Toggles a notification on/off
 * This is used to turn notifications on when a message is approved
 *
 * @param array $msgs array of messages that you want to toggle
 * @param type $approved direction of the toggle read / unread
 */
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

/**
 * Provided a notification id and a member id,
 * checks if the notification belongs to that user
 *
 * @param integer $id_notification the id of an existing notification
 * @param integer $id_member id of a member
 * @return bool true if the notification belongs to the member, false otherwise
 */
function findMemberNotification($id_notification, $id_member)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_notification
		FROM {db_prefix}log_notifications
		WHERE id_notification = {int:id_notification}
			AND id_member = {int:id_member}
		LIMIT 1',
		array(
			'id_notification' => $id_notification,
			'id_member' => $id_member,
		)
	);
	$return = $db->num_rows($request);
	$db->free_result($request);

	return !empty($return);
}

/**
 * To validate access to read/unread/delete notifications we need
 */
function validate_ownnotification($field, $input, $validation_parameters = null)
{
	global $user_info;

	if (!isset($input[$field]))
		return;

	if (!findMemberNotification($input[$field], $user_info['id']))
	{
		return array(
			'field' => $field,
			'input' => $input[$field],
			'function' => __FUNCTION__,
			'param' => $validation_parameters
		);
	}
}