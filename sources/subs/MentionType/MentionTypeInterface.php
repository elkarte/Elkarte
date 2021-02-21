<?php

/**
 * Interface for mentions objects
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.7
 *
 */

namespace ElkArte\sources\subs\MentionType;

/**
 * Interface Mention_Type_Interface
 *
 * @package ElkArte\sources\subs\MentionType
 */
interface Mention_Type_Interface
{
	/**
	 * This static function is used to obtain the events to register to a controller.
	 *
	 * @param string $controller The name of the controller initializing the system
	 */
	public static function getEvents($controller);

	/**
	 * Just returns the _type property.
	 */
	public static function getType();

	/**
	 * Returns which frequencies (notification, email, daily, weekly, etc) are supported
	 */
	public static function getSupportedFrequency();

	/**
	 * Returns the modules to enable when turning on the mention.
	 *
	 * @param string[] $modules An empty array, or array of active modules
	 *                 in the form array('module' => array('controller'))
	 * @return string[] Array of modules to activate on controllers in the form:
	 *                  array('module' => array('controller'))
	 */
	public static function getModules($modules);

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
	 * @return int[] An array of members id
	 */
	public function getUsersToNotify();

	/**
	 * Used to inject the database object.
	 *
	 * @param \Database $db
	 */
	public function setDb(\Database $db);

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

	/**
	 * Used by the Notifications class to retrieve the notifications to send.
	 *
	 * @param array $lang_data
	 * @param int[] $members
	 *
	 * @return mixed[] array(array(
	 *                  id_member_to (int),
	 *                  email_address (text),
	 *                  subject (text),
	 *                  body (text),
	 *                  last_id (int), ???
	 *                ))
	 */
	public function getNotificationBody($lang_data, $members);

	/**
	 * The Notifications_Task contains few data that may be necessary for the processing
	 * of the mention.
	 *
	 * @param \Notifications_Task $task
	 */
	public function setTask(\Notifications_Task $task);

	/**
	 * Used when sending an immediate email to get the last message id (email id)
	 * so that the PbE can do its magic.
	 *
	 * @return string
	 */
	public function getLastId();
}
