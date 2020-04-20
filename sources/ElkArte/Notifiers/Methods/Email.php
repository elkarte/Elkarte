<?php

/**
 * This class takes care of sending a notification as immediate email
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Notifiers;

use ElkArte\Notifiers\NotifierInterface;

/**
 * Class Notifications
 *
 * Core area for notifications, defines the abstract model
 */
class Email implements NotifierInterface
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
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function __construct($db, $user)
	{
		parent::__construct($db, $user);
		require_once(SUBSDIR . '/Mail.subs.php');

		$this->lang_data = ['subject' => 'subject', 'body' => 'body', 'suffix' => true];
	}

	/**
	 * {@inheritdoc }
	 */
	public function send($obj, $task, $bodies)
	{
		$this->_send_email($obj, $task, $bodies);
	}

	/**
	 * Sends an immediate email notification.
	 *
	 * @param \ElkArte\Mentions\MentionType\NotificationInterface $obj
	 * @param \ElkArte\NotificationsTask $task
	 * @param mixed[] $bodies
	 */
	protected function _send_email(NotificationInterface $obj, NotificationsTask $task, $bodies)
	{
		$last_id = $obj->getLastId();
		foreach ($bodies as $body)
		{
			if (!in_array($body['id_member_to'], [0, $this->user->id]))
			{
				sendmail($body['email_address'], $body['subject'], $body['body'], null, $last_id);
			}
		}
	}
}
