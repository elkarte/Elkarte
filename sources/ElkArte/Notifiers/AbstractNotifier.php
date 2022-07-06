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

use ElkArte\Database\QueryInterface;
use ElkArte\Mentions\MentionType\NotificationInterface;
use ElkArte\Notifications\NotificationsTask;
use ElkArte\UserInfo;


/**
 * Class Notifications
 *
 * Core area for notifications, defines the abstract model
 */
abstract class AbstractNotifier implements NotifierInterface
{
	/**
	 * Hash defining what is needed to build the message
	 *
	 * @var string[]
	 */
	public $lang_data;

	/**
	 * The database object
	 *
	 * @var \ElkArte\Database\QueryInterface
	 */
	protected $db = null;

	/**
	 * The current user data
	 *
	 * @var \ElkArte\UserInfo
	 */
	protected $user = null;

	public function __construct(QueryInterface $db, UserInfo $user)
	{
		$this->db = $db;
		$this->user = $user;
	}
	/**
	 * {@inheritdoc }
	 */
	abstract public function send(NotificationInterface $obj, NotificationsTask $task, $bodies);
}
