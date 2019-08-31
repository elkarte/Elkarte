<?php

/**
 * Interface for mentions objects
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions\MentionType;

/**
 * Interface \ElkArte\Mentions\MentionType\NotificationInterface
 */
interface NotificationInterface
{
	/**
	 * Just returns the _type property.
	 */
	public static function getType();

	/**
	 * Used by the Notifications class to find the users that want a notification.
	 *
	 * @return int[] An array of members id
	 */
	public function getUsersToNotify();

	/**
	 * Used by the Notifications class to retrieve the notifications to send.
	 *
	 * @param array $lang_data
	 * @param int[] $users
	 *
	 * @return mixed[] array(array(
	 *                  id_member_to (int),
	 *                  email_address (text),
	 *                  subject (text),
	 *                  body (text),
	 *                  last_id (int), ???
	 *                ))
	 */
	public function getNotificationBody($lang_data, $users);

	/**
	 * The \ElkArte\NotificationsTask contains few data that may be necessary for the processing
	 * of the mention.
	 *
	 * @param \ElkArte\NotificationsTask $task
	 */
	public function setTask(\ElkArte\NotificationsTask $task);

	/**
	 * Used when sending an immediate email to get the last message id (email id)
	 * so that the PbE can do its magic.
	 *
	 * @return string
	 */
	public function getLastId();

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
	 *
	 * @return int[] An array of members id
	 */
	public function insert($member_from, $members_to, $target, $time = null, $status = null, $is_accessible = null);
}
