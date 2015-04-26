<?php

/**
 * Interface for mentions objects
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

namespace ElkArte\sources\subs\MentionType;

if (!defined('ELK'))
	die('No access...');

interface Mention_Type_Interface
{
	/**
	 * This static function is used to obtain the events to register to a controller.
	 *
	 * @param string $controller The name of the controller initializing the system
	 */
	public static function getEvents($controller);

	/**
	 * Used by Mentions_Controller to filter the mentions to display in the list.
	 *
	 * @param string $type
	 * @param mixed[] $mentions
	 */
	public function view($type, &$mentions);

	/**
	 * Used by the Notifications class to find the users that want a notification.
	 *
	 * @param mixed[] $task
	 * @return int[] An array of members id
	 */
	public function getUsersToNotify($task);

	/**
	 * Used to inject the database object.
	 *
	 * @param Database $db
	 */
	public function setDb($db);

	/**
	 * Inserts a new mention into the database.
	 * Checks if the mention already exists (in any status) to prevent any duplicates
	 *
	 * @package Mentions
	 * @param int $member_from the id of the member mentioning
	 * @param int[] $members_to an array of ids of the members mentioned
	 * @param int $target the id of the target involved in the mention
	 * @param string|null $time optional value to set the time of the mention, defaults to now
	 * @param int|null $status optional value to set a status, defaults to 0
	 * @param bool|null $is_accessible optional if the mention is accessible to the user
	 */
	public function insert($member_from, $members_to, $target, $time = null, $status = null, $is_accessible = null);

	/**
	 * Used by the Notifications class to retrieve the notifications to send.
	 *
	 * @param string $frequency
	 * @param int[] $users
	 * @param Notifications_Task $task
	 * @return mixed[] array(array(
	 *                  id_member_to (int),
	 *                  email_address (text),
	 *                  subject (text),
	 *                  body (text),
	 *                  last_id (int), ???
	 *                ))
	 */
	public function getNotificationBody($frequency, $users, Notifications_Task $task);

	/**
	 * Used when sending an immediate email to get the last message id (email id)
	 * so that the PbE can do its magic.
	 *
	 * @return string
	 */
	public function getLastId();
}