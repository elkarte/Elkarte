<?php

/**
 * Class that centrilize the "notification" process.
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
	 * Process a certain task in order to send out the notifications.
	 *
	 * @param \Notifications_Task $task
	 */
	protected function _send_task(\Notifications_Task $task)
	{
		$class = $task->getClass();
		$obj = new $class($this->_db);
		$obj->setTask($task);

		// Cleanup the list of members to notify,
		// in certain cases it may differ from the list passed (if any)
		$task->setMembers($obj->getUsersToNotify());
		$notif_prefs = $this->_getNotificationPreferences($task->notification_type, $task->getMembers());

		foreach ($this->_notification_frequencies as $key)
		{
			if (!empty($notif_prefs[$key]))
			{
				$bodies = $obj->getNotificationBody($key, $notif_prefs[$key]);

				// This is made for cases when a certain setting may not be available:
				// just return an empty body array and the notifications are skipped.
				if (empty($bodies))
					continue;

				$method = '_send_' . $key;
				$this->{$method}($obj, $task, $bodies);
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
	 * @param string $notification_type
	 * @param int[] $members
	 */
	protected function _getNotificationPreferences($notification_type, $members)
	{
		$query_members = $members;
		// The member 0 is the "default" setting
		$query_members[] = 0;

		$request = $this->_db->query('', '
			SELECT id_member, notification_level
			FROM {db_prefix}notifications_pref
			WHERE id_member IN ({array_int:members_to})
				AND mention_type LIKE {string:mention_type}',
			array(
				'members_to' => $query_members,
				'mention_type' => $notification_type,
			)
		);
		$preferences = array();
		while ($row = $this->_db->fetch_assoc($request))
			$preferences[$row['id_member']] = $row['notification_level'];

		$this->_db->free_result($request);

		$notification_types = array();
		foreach ($this->_notification_frequencies as $freq)
			$notification_types[$freq] = array();

		// notification_level can be:
		//    - 0 => no notification
		//    - 1 => only mention
		//    - 2 => mention + immediate email
		//    - 3 => mention + daily email
		//    - 4 => mention + weekly email
		foreach ($members as $member)
		{

			if (!isset($preferences[$member]))
				$level = isset($preferences[0]) ? (int) $preferences[0] : 1;
			else
				$level = $preferences[$member];

			if ($level === 0)
				continue;

			if (isset($this->_notification_frequencies[1]))
				$notification_types[$this->_notification_frequencies[1]][] = $member;

			if (isset($this->_notification_frequencies[$level]))
				$notification_types[$this->_notification_frequencies[$level]][] = $member;
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