<?php

/**
 * This class takes care of sending a notification as internal ElkArte notification
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Notifiers\Methods;

use ElkArte\Notifiers\AbstractNotifier;
use ElkArte\Mentions\MentionType\NotificationInterface;
use ElkArte\NotificationsTask;
use ElkArte\Mentions\Mentioning;
use ElkArte\DataValidator;


/**
 * Class Notifications
 *
 * Core area for notifications, defines the abstract model
 */
class Notification extends AbstractNotifier
{
	/**
	 * Hash defining what is needed to build the message
	 *
	 * @var string[]
	 */
	public $lang_data;

	/**
	 * Notifications constructor.
	 *
	 * Registers the known notifications to the system, allows for integration to add more
	 *
	 * @param \ElkArte\Database\QueryInterface $db
	 * @param \ElkArte\UserInfo|null $user
	 */
	public function __construct($db, $user)
	{
		parent::__construct($db, $user);

		$this->lang_data = [];
	}

	/**
	 * {@inheritdoc }
	 */
	public function send(NotificationInterface $obj, NotificationsTask $task, $bodies)
	{
		$this->_send_notification($obj, $task, $bodies);
	}

	/**
	 * Inserts a new mention in the database (those that appear in the mentions area).
	 *
	 * @param \ElkArte\Mentions\MentionType\MentionType\NotificationInterface $obj
	 * @param \ElkArte\NotificationsTask $task
	 * @param mixed[] $bodies
	 */
	protected function _send_notification(NotificationInterface $obj, NotificationsTask $task, $bodies)
	{
		global $modSettings;
		$mentioning = new Mentioning($this->db, $this->user, new DataValidator(), $modSettings['enabled_mentions']);
		foreach ($bodies as $body)
		{
			$mentioning->create($obj, array(
				'id_member_from' => $task['id_member_from'],
				'id_member' => $body['id_member_to'],
				'id_msg' => $task['id_target'],
				'type' => $task['notification_type'],
				'log_time' => $task['log_time'],
				'status' => $task['source_data']['status'],
			));
		}
	}
}
