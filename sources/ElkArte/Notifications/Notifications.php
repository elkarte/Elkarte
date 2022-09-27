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

namespace ElkArte\Notifications;

use ElkArte\AbstractModel;
use ElkArte\DataValidator;
use ElkArte\Exceptions;
use ElkArte\Mentions;
use ElkArte\Mentions\MentionType\NotificationInterface;
use ElkArte\User;

/**
 * Class Notifications
 *
 * Core area for notifications, defines the abstract model
 */
class Notifications extends AbstractModel
{
	/**
	 * Where the notifiers are stored
	 */
	const NOTIFIERS_PATH = SOURCEDIR . '/ElkArte/Notifiers/Methods';

	/**
	 * Since we have to call them dynamically we need to know both path and namespace...
	 */
	const NOTIFIERS_NAMESPACE = '\\ElkArte\\Notifiers\\Methods';

	/**
	 * When in settings is stored with this value, it means it's the default for users that
	 * have no specific setting
	 */
	const DEFAULT_LEVEL = 2;

	/**
	 * When notifications_pref has this value, no notifications are sent
	 */
	const DEFAULT_NONE = 'none';

	/**
	 * Instance manager
	 *
	 * @var Notifications
	 */
	protected static $_instance;

	/**
	 * List of notifications to send
	 *
	 * @var \ElkArte\Notifications\NotificationsTask[]
	 */
	protected $_to_send;

	/**
	 * Available notification frequencies
	 *
	 * @var string[]
	 */
	protected $_notification_frequencies;

	/**
	 * Available notification frequencies
	 *
	 * @var array
	 */
	protected $_notifiers;

	/**
	 * Only the members that should be notified.
	 * For example, in case of editing a message, quoted members
	 * should not be mentioned twice.
	 *
	 * @var array
	 */
	protected $_to_actually_mention = array();

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

		// Let's register all the notifications we know by default
		$glob = new \GlobIterator(Notifications::NOTIFIERS_PATH . '/*.php', \FilesystemIterator::SKIP_DOTS);
		foreach ($glob as $file)
		{
			$this->register($file->getBasename('.php'));
		}

		call_integration_hook('integrate_notifications_methods', array($this));
	}

	/**
	 * Function to register any new notification method.
	 *
	 * @param string $class_name the name of a class.
	 * @param string $namespace the namespace of the class.
	 *
	 * Used to identify the strings for the subject and body respectively of the notification.
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function register($class_name, $namespace = null)
	{
		if ($namespace === null)
		{
			$namespace = Notifications::NOTIFIERS_NAMESPACE;
		}

		$class = $namespace . '\\' . $class_name;
		$index = strtolower($class_name);

		if (isset($this->_notifiers[$index]))
		{
			throw new Exceptions\Exception('error_notifier_already_instantiated');
		}

		$this->_notifiers[$index] = new $class($this->_db, $this->user);
	}

	/**
	 * Singleton... until we have something better.
	 *
	 * @return Notifications
	 */
	public static function instance()
	{
		if (self::$_instance === null)
		{
			self::$_instance = new Notifications(database(), User::$info);
		}

		return self::$_instance;
	}

	/**
	 * We haz a new notification to send out!
	 * Let's add it to the queue, later on (just before shutting down)
	 * we will take care of sending it (see send)
	 *
	 * @param \ElkArte\Notifications\NotificationsTask $task
	 */
	public function add(NotificationsTask $task)
	{
		$this->_to_send[] = $task;
	}

	/**
	 * Time to notify our beloved members! YAY!
	 * This is usually used in obExit or close to it, just before the script ends
	 * to actually send any type of notification that has piled up during
	 * the execution
	 */
	public function send()
	{
		if (!empty($this->_to_send))
		{
			foreach ($this->_to_send as $task)
			{
				$this->_send_task($task);
			}
		}

		$this->_to_send = array();
	}

	/**
	 * Process a certain task in order to send out the notifications.
	 *
	 * @param \ElkArte\Notifications\NotificationsTask $task
	 */
	protected function _send_task(NotificationsTask $task)
	{
		/** @var \ElkArte\Mentions\MentionType\NotificationInterface $class */
		$class = $task->getClass();

		/** @var \ElkArte\Mentions\MentionType\AbstractNotificationBoardAccess $obj */
		$obj = new $class($this->_db, $this->user);
		$obj->setTask($task);

		// The enabled notification methods (on site, email ...) for this type (mention, liked, buddy ...)
		require_once(SUBSDIR . '/Notification.subs.php');
		$active_notifiers = filterNotificationMethods(array_keys($this->_notifiers), $class::getType());

		// Cleanup the list of members to notify,
		// in certain cases it may differ from the list passed (if any)
		$obj->setUsersToNotify();

		// How do these members actually want to be notified
		$notif_prefs = $this->_getNotificationPreferences($active_notifiers, $task->notification_type, $task->getMembers());

		// For each notification method enabled for this (on site, email etc)
		foreach ($notif_prefs as $notifier => $members)
		{
			// No members signed up for this combo
			if (empty($members))
			{
				continue;
			}

			$bodies = $obj->getNotificationBody($this->_notifiers[$notifier]->lang_data, $members);

			// Just in case...
			if (empty($bodies))
			{
				continue;
			}

			$this->_notifiers[$notifier]->send($obj, $task, $bodies);
		}
	}

	/**
	 * Loads from the database the notification preferences for a certain type
	 * of mention for a bunch of members.
	 *
	 * @param string[] $notifiers
	 * @param string $notification_type
	 * @param int[] $members
	 *
	 * @return array
	 */
	protected function _getNotificationPreferences($notifiers, $notification_type, $members)
	{
		$preferences = getUsersNotificationsPreferences($notification_type, $members);

		$notification_methods = [];
		foreach ($notifiers as $notifier)
		{
			$notification_methods[$notifier] = [];
		}

		foreach ($members as $member)
		{
			// This user for this mention doesn't want any notification. Move on.
			// Memo: at a certain point I used 'none', the two are basically equivalent
			// since an \ElkArte\Notifiers\Methods\None doesn't exist, so nothing will be
			// instantiated
			if (empty($preferences[$member][$notification_type]))
			{
				continue;
			}

			$this_prefs = $preferences[$member][$notification_type];
			foreach ($this_prefs as $this_pref)
			{
				if (!isset($notification_methods[$this_pref]))
				{
					continue;
				}

				$notification_methods[$this_pref][] = $member;
			}
		}

		return $notification_methods;
	}

	/**
	 * Returns the notifications in the system, daily, weekly, etc
	 *
	 * @return string[]
	 */
	public function getNotifiers()
	{
		return $this->_notifiers;
	}

	/**
	 * Inserts a new mention in the database (those that appear in the mentions area).
	 *
	 * @param \ElkArte\Mentions\MentionType\NotificationInterface $obj
	 * @param \ElkArte\Notifications\NotificationsTask $task
	 * @param array $bodies
	 */
	protected function _send_notification(NotificationInterface $obj, NotificationsTask $task, $bodies)
	{
		$mentioning = new Mentions\Mentioning($this->_db, $this->user, new DataValidator(), $this->_modSettings->enabled_mentions);
		foreach ($bodies as $body)
		{
			$this->_to_actually_mention[$task['notification_type']] = $mentioning->create($obj, array(
				'id_member_from' => $task['id_member_from'],
				'id_member' => $body['id_member_to'],
				'id_msg' => $task['id_target'],
				'type' => $task['notification_type'],
				'log_time' => $task['log_time'],
				'status' => $task['source_data']['status'],
			));
		}
	}

	/**
	 * Sends an immediate email notification.
	 *
	 * @param \ElkArte\Mentions\MentionType\NotificationInterface $obj
	 * @param \ElkArte\Notifications\NotificationsTask $task
	 * @param array $bodies
	 */
	protected function _send_email(NotificationInterface $obj, NotificationsTask $task, $bodies)
	{
		$last_id = $obj->getLastId();
		foreach ($bodies as $body)
		{
			if (in_array($body['id_member_to'], $this->_to_actually_mention[$task['notification_type']]))
			{
				sendmail($body['email_address'], $body['subject'], $body['body'], null, $last_id);
			}
		}
	}

	/**
	 * Stores data in the database to send a daily digest.
	 *
	 * @param \ElkArte\Mentions\MentionType\NotificationInterface $obj
	 * @param \ElkArte\Notifications\NotificationsTask $task
	 * @param array $bodies
	 */
	protected function _send_daily_email(NotificationInterface $obj, NotificationsTask $task, $bodies)
	{
		foreach ($bodies as $body)
		{
			if (in_array($body['id_member_to'], $this->_to_actually_mention[$task['notification_type']]))
			{
				$this->_insert_delayed(array(
					$task['notification_type'],
					$body['id_member_to'],
					$task['log_time'],
					'd',
					$body['body']
				));
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
		$this->_db->insert('ignore',
			'{db_prefix}pending_notifications',
			array(
				'notification_type' => 'string-20',
				'id_member' => 'int',
				'log_time' => 'int',
				'frequency' => 'string-1',
				'snippet' => 'string',
			),
			$insert_array,
			array()
		);
	}

	/**
	 * Stores data in the database to send a weekly digest.
	 *
	 * @param \ElkArte\Mentions\MentionType\NotificationInterface $obj
	 * @param \ElkArte\Notifications\NotificationsTask $task
	 * @param array $bodies
	 */
	protected function _send_weekly_email(NotificationInterface $obj, NotificationsTask $task, $bodies)
	{
		foreach ($bodies as $body)
		{
			if (in_array($body['id_member_to'], $this->_to_actually_mention[$task['notification_type']]))
			{
				$this->_insert_delayed(array(
					$task['notification_type'],
					$body['id_member_to'],
					$task['log_time'],
					'w',
					$body['body']
				));
			}
		}
	}
}
