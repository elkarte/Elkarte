<?php

/**
 * Class that centralize the "notification" process.
 * ... or at least tries to.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0.3
 *
 */

if (!defined('ELK'))
	die('No access...');

class Notifications extends AbstractModel
{
	/**
	 * Instance manager
	 *
	 * @var Notifications
	 */
	protected static $_instance;

	/**
	 * List of notifications to send
	 *
	 * @var \Notifications_Task[]
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
	 * @var string[]
	 */
	protected $_notifiers;

	/**
	 * Disallows to register notification types with id < 5
	 *
	 * @var bool
	 */
	protected $_protect_id = true;

	public function __construct($db)
	{
		parent::__construct($db);

		$this->_protect_id = false;

		// Let's register all the notifications we know by default
		$this->register(1, 'notification', array($this, '_send_notification'));
		$this->register(2, 'email', array($this, '_send_email'), array('subject' => 'subject', 'body' => 'body'));
		$this->register(3, 'email_daily', array($this, '_send_daily_email'), array('subject' => 'subject', 'body' => 'snippet'));
		$this->register(4, 'email_weekly', array($this, '_send_weekly_email'), array('subject' => 'subject', 'body' => 'snippet'));

		$this->_protect_id = true;

		call_integration_hook('integrate_notifications_methods', array($this));
	}

	/**
	 * We hax a new notification to send out!
	 *
	 * @param \Notifications_Task $task
	 */
	public function add(\Notifications_Task $task)
	{
		$this->_to_send[] = $task;
	}

	/**
	 * Time to notify our beloved members! YAY!
	 */
	public function send()
	{
		Elk_Autoloader::getInstance()->register(SUBSDIR . '/MentionType', '\\ElkArte\\sources\\subs\\MentionType');

		$this->_notification_frequencies = array(
			// 0 is for no notifications, so we start from 1 the counting, that saves a +1 later
			1 => 'notification',
			'email',
			'email_daily',
			'email_weekly',
		);

		if (!empty($this->_to_send))
		{
			foreach ($this->_to_send as $task)
				$this->_send_task($task);
		}

		$this->_to_send = array();
	}

	/**
	 * Function to register any new notification method.
	 *
	 * @param int $id This shall be a unique integer representing the
	 *            notification method.
	 *            <b>WARNING for addons developers</b>: please note that this has
	 *            to be unique across addons, so if you develop an addon that
	 *            extends notifications, please verify this id has not been
	 *            "taken" by someone else!
	 * @param int $key The string name identifying the notification method
	 * @param mixed|mixed[] $callback A callable function/array/whatever that
	 *                      will take care of sending the notification
	 * @param null|string[] $lang_data For the moment an array containing at least:
	 *                        - 'subject' => 'something'
	 *                        - 'body' => 'something_else'
	 *                       the two will be used to identify the strings to be
	 *                       used for the subject and the body respectively of
	 *                       the notification.
	 * @throws Elk_Exception
	 */
	public function register($id, $key, $callback, $lang_data = null)
	{
		if ($this->_protect_id && $id < 5)
			throw new Elk_Exception('error_invalid_notification_id');

		$this->_notifiers[$key] = array(
			'id' => $id,
			'key' => $key,
			'callback' => $callback,
			'lang_data' => $lang_data,
		);

		$this->_notification_frequencies[$id] = $key;
	}

	public function getNotifiers()
	{
		return $this->_notification_frequencies;
	}

	/**
	 * Process a certain task in order to send out the notifications.
	 *
	 * @param \Notifications_Task $task
	 */
	protected function _send_task(\Notifications_Task $task)
	{
		$class = $task->getClass();
		$obj = new $class($this->_db);
		$obj->setTask($task);

		require_once(SUBSDIR . '/Notification.subs.php');
		$notification_frequencies = filterNotificationMethods($this->_notification_frequencies, $class::getType());

		// Cleanup the list of members to notify,
		// in certain cases it may differ from the list passed (if any)
		$task->setMembers($obj->getUsersToNotify());
		$notif_prefs = $this->_getNotificationPreferences($notification_frequencies, $task->notification_type, $task->getMembers());

		foreach ($notification_frequencies as $key)
		{
			if (!empty($notif_prefs[$key]))
			{
				$bodies = $obj->getNotificationBody($this->_notifiers[$key]['lang_data'], $notif_prefs[$key]);

				// Just in case...
				if (empty($bodies))
					continue;

				call_user_func_array($this->_notifiers[$key]['callback'], array($obj, $task, $bodies, $this->_db));
			}
		}
	}

	/**
	 * Inserts a new mention in the database (those that appear in the mentions area).
	 *
	 * @param \ElkArte\sources\subs\MentionType\Mention_Type_Interface $obj
	 * @param \Notifications_Task $task
	 * @param mixed[] $bodies
	 * @global $modSettings - Not sure if actually necessary
	 */
	protected function _send_notification(\ElkArte\sources\subs\MentionType\Mention_Type_Interface $obj, \Notifications_Task $task, $bodies)
	{
		global $modSettings;

		$mentioning = new Mentioning($this->_db, new Data_Validator(), $modSettings['enabled_mentions']);
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

	/**
	 * Sends an immediate email notification.
	 *
	 * @param \ElkArte\sources\subs\MentionType\Mention_Type_Interface $obj
	 * @param \Notifications_Task $task
	 * @param mixed[] $bodies
	 */
	protected function _send_email(\ElkArte\sources\subs\MentionType\Mention_Type_Interface $obj, \Notifications_Task $task, $bodies)
	{
		$last_id = $obj->getLastId();
		foreach ($bodies as $body)
		{
			sendmail($body['email_address'], $body['subject'], $body['body'], null, $last_id);
		}
	}

	/**
	 * Stores data in the database to send a daily digest.
	 *
	 * @param \ElkArte\sources\subs\MentionType\Mention_Type_Interface $obj
	 * @param \Notifications_Task $task
	 * @param mixed[] $bodies
	 */
	protected function _send_daily_email(\ElkArte\sources\subs\MentionType\Mention_Type_Interface $obj, \Notifications_Task $task, $bodies)
	{
		foreach ($bodies as $body)
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

	/**
	 * Stores data in the database to send a weekly digest.
	 *
	 * @param \ElkArte\sources\subs\MentionType\Mention_Type_Interface $obj
	 * @param \Notifications_Task $task
	 * @param mixed[] $bodies
	 */
	protected function _send_weekly_email(\ElkArte\sources\subs\MentionType\Mention_Type_Interface $obj, \Notifications_Task $task, $bodies)
	{
		foreach ($bodies as $body)
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

	/**
	 * Do the insert into the database for daily and weekly digests.
	 *
	 * @param mixed[] $insert_array
	 */
	protected function _insert_delayed($insert_array)
	{
		$this->_db->insert('ignore',
			'{db_prefix}pending_notifications',
			array(
				'notification_type' => 'string-10',
				'id_member' => 'int',
				'log_time' => 'int',
				'frequency' => 'string-1',
				'snippet ' => 'string',
			),
			$insert_array,
			array()
		);
	}

	/**
	 * Loads from the database the notification preferences for a certain type
	 * of mention for a bunch of members.
	 *
	 * @param string[] $notification_frequencies
	 * @param string $notification_type
	 * @param int[] $members
	 */
	protected function _getNotificationPreferences($notification_frequencies, $notification_type, $members)
	{
		$query_members = $members;
		// The member 0 is the "default" setting
		$query_members[] = 0;

		require_once(SUBSDIR . '/Notification.subs.php');
		$preferences = getUsersNotificationsPreferences($notification_type, $query_members);

		$notification_types = array();
		foreach ($notification_frequencies as $freq)
			$notification_types[$freq] = array();

		// notification_level can be:
		//    - 0 => no notification
		//    - 1 => only mention
		//    - 2 => mention + immediate email
		//    - 3 => mention + daily email
		//    - 4 => mention + weekly email
		//    - 5+ => usable by addons
		foreach ($members as $member)
		{
			$this_pref = $preferences[$member][$notification_type];
			if ($this_pref === 0)
				continue;

			// In the following two checks the use of the $this->_notification_frequencies
			// is intended, because the numeric id is important and is not preserved
			// in the local $notification_frequencies
			if (isset($this->_notification_frequencies[1]))
				$notification_types[$this->_notification_frequencies[1]][] = $member;

			if ($this_pref > 1)
			{
				if (isset($this->_notification_frequencies[$this_pref]) && isset($notification_types[$this->_notification_frequencies[$this_pref]]))
					$notification_types[$this->_notification_frequencies[$this_pref]][] = $member;
			}
		}

		return $notification_types;
	}

	/**
	 * Singleton... until we have something better.
	 *
	 * @return Notifications
	 */
	public static function getInstance()
	{
		if (self::$_instance === null)
			self::$_instance = new Notifications(database());

		return self::$_instance;
	}
}