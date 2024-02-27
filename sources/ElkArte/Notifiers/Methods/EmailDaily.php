<?php

/**
 * This class takes care of sending a notification as daily email digest
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Notifiers\Methods;

use ElkArte\Database\QueryInterface;
use ElkArte\Mentions\MentionType\NotificationInterface;
use ElkArte\Notifications\NotificationsTask;
use ElkArte\Notifiers\AbstractNotifier;
use ElkArte\UserInfo;

/**
 * Class Notifications
 *
 * Core area for notifications, defines the abstract model
 */
class EmailDaily extends AbstractNotifier
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
	 * @param QueryInterface $db
	 * @param UserInfo|null $user
	 */
	public function __construct($db, $user)
	{
		parent::__construct($db, $user);
		require_once(SUBSDIR . '/Mail.subs.php');

		$this->lang_data = ['subject' => 'subject', 'body' => 'snippet', 'suffix' => true];
	}

	/**
	 * {@inheritDoc}
	 */
	public function send(NotificationInterface $obj, NotificationsTask $task, $bodies)
	{
		$this->_send_daily_email($obj, $task, $bodies);
	}

	/**
	 * Stores data in the database to send a daily digest.
	 *
	 * @param NotificationInterface $obj
	 * @param NotificationsTask $task
	 * @param array $bodies
	 */
	protected function _send_daily_email(NotificationInterface $obj, NotificationsTask $task, $bodies)
	{
		foreach ($bodies as $body)
		{
			if (!in_array((int) $body['id_member_to'], [0, $this->user->id], true))
			{
				$this->_insert_delayed([
					$task['notification_type'],
					$body['id_member_to'],
					$task['log_time'],
					'd',
					$body['body']
				]);
			}
		}
	}

	/**
	 * Do the insert into the database for daily and weekly digests.
	 *
	 * @param array $insert_array
	 */
	protected function _insert_delayed($insert_array)
	{
		$this->db->insert('ignore',
			'{db_prefix}pending_notifications',
			[
				'notification_type' => 'string-20',
				'id_member' => 'int',
				'log_time' => 'int',
				'frequency' => 'string-1',
				'snippet' => 'string',
			],
			$insert_array,
			[]
		);
	}
}
