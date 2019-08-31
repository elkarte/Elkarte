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
}
