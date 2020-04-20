<?php

/**
 * Class that centralize the "notification" process.
 * ... or at least tries to.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Notifiers;

/**
 * Class Notifications
 *
 * Core area for notifications, defines the abstract model
 */
Interface NotifierInterface
{
	/**
	 * Process a certain task in order to send out the notifications.
	 *
	 * @param \ElkArte\Mentions\MentionType\NotificationInterface $obj
	 * @param \ElkArte\NotificationsTask $task
	 * @param string[] $bodies
	 */
	public function send($obj, $task, $bodies);

	/**
	 * Returns the notifications in the system, daily, weekly, etc
	 *
	 * @return string[]
	 */
	public function getName();
}
