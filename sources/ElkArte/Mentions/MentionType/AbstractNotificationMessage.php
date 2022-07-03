<?php

/**
 * Common methods shared by any type of mention so far.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions\MentionType;

use ElkArte\Database\QueryInterface;
use ElkArte\Languages\Txt;
use ElkArte\Notifications\NotificationsTask;
use ElkArte\UserInfo;

/**
 * Class AbstractNotificationMessage
 */
abstract class AbstractNotificationMessage implements NotificationInterface
{
	/**
	 * The identifier of the mention (the name that is stored in the db)
	 *
	 * @var string
	 */
	protected static $_type = '';

	/**
	 * The database object
	 *
	 * @var \ElkArte\Database\QueryInterface
	 */
	protected $_db;

	/**
	 * The current user object
	 *
	 * @var \ElkArte\ValuesContainer
	 */
	protected $user;

	/**
	 * The \ElkArte\NotificationsTask in use
	 *
	 * @var \ElkArte\Notifications\NotificationsTask
	 */
	protected $_task;

	/**
	 * @param \ElkArte\Database\QueryInterface $db
	 * @param \ElkArte\UserInfo $user
	 */
	public function __construct(QueryInterface $db, UserInfo $user)
	{
		$this->_db = $db;
		$this->user = $user;
	}

	/**
	 * {@inheritdoc }
	 */
	public static function getType()
	{
		return static::$_type;
	}

	/**
	 * {@inheritdoc }
	 */
	public function setUsersToNotify()
	{
		if (isset($this->_task))
		{
			$this->_task->setMembers((array) $this->_task['source_data']['id_members']);
		}
	}

	/**
	 * {@inheritdoc }
	 */
	abstract public function getNotificationBody($lang_data, $members);

	/**
	 * {@inheritdoc }
	 */
	public function setTask(NotificationsTask $task)
	{
		$this->_task = $task;
	}

	/**
	 * {@inheritdoc }
	 * By default returns null.
	 */
	public function getLastId()
	{
		return null;
	}

	/**
	 * {@inheritdoc }
	 */
	public function insert($member_from, $members_to, $target, $time = null, $status = null, $is_accessible = null)
	{
		$inserts = array();

		// $time is not checked because it's useless
		$existing = array();
		$this->_db->fetchQuery('
			SELECT 
				id_member
			FROM {db_prefix}log_mentions
			WHERE id_member IN ({array_int:members_to})
				AND mention_type = {string:type}
				AND id_member_from = {int:member_from}
				AND id_target = {int:target}',
			array(
				'members_to' => $members_to,
				'type' => static::$_type,
				'member_from' => $member_from,
				'target' => $target,
			)
		)->fetch_callback(
			function ($row) use (&$existing) {
				$existing[] = $row['id_member'];
			}
		);

		$actually_mentioned = array();
		// If the member has already been mentioned, it's not necessary to do it again
		foreach ($members_to as $id_member)
		{
			if (!in_array($id_member, $existing))
			{
				$inserts[] = array(
					$id_member,
					$target,
					$status ?? 0,
					$is_accessible ?? 1,
					$member_from,
					$time ?? time(),
					static::$_type
				);
				$actually_mentioned[] = $id_member;
			}
		}

		if (!empty($inserts))
		{
			// Insert the new mentions
			$this->_db->insert('',
				'{db_prefix}log_mentions',
				array(
					'id_member' => 'int',
					'id_target' => 'int',
					'status' => 'int',
					'is_accessible' => 'int',
					'id_member_from' => 'int',
					'log_time' => 'int',
					'mention_type' => 'string-12',
				),
				$inserts,
				array('id_mention')
			);
		}

		return $actually_mentioned;
	}

	/**
	 * Does the replacement of some placeholders with the corresponding
	 * text/link/url.
	 *
	 * @param string $template An email template to load
	 * @param string[] $keys Pair values to match the $txt indexes to subject and body
	 * @param int[] $members
	 * @param \ElkArte\Notifications\NotificationsTask $task
	 * @param string[] $lang_files Language files to load (optional)
	 * @param string[] $replacements Additional replacements for the loadEmailTemplate function (optional)
	 * @return mixed[]
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _getNotificationStrings($template, $keys, $members, NotificationsTask $task, $lang_files = array(), $replacements = array())
	{
		$members_data = $task->getMembersData();

		$return = array();
		if (!empty($template))
		{
			require_once(SUBSDIR . '/Notification.subs.php');

			foreach ($members as $member)
			{
				$replacements['REALNAME'] = $members_data[$member]['real_name'];
				$replacements['UNSUBSCRIBELINK'] = replaceBasicActionUrl('{script_url}?action=notify;sa=unsubscribe;token=' .
					getNotifierToken($member, $members_data[$member]['email_address'], $members_data[$member]['password_salt'], $task->notification_type, $task->id_target));
				$langstrings = $this->_loadStringsByTemplate($template, $members, $members_data, $lang_files, $replacements);

				$return[] = array(
					'id_member_to' => $member,
					'email_address' => $members_data[$member]['email_address'],
					'subject' => $langstrings[$members_data[$member]['lngfile']]['subject'],
					'body' => $langstrings[$members_data[$member]['lngfile']]['body'],
					'last_id' => 0
				);
			}
		}
		else
		{
			foreach ($members as $member)
			{
				$return[] = array(
					'id_member_to' => $member,
					'email_address' => $members_data[$member]['email_address'],
					'subject' => $keys['subject'],
					'body' => $keys['body'],
					'last_id' => 0
				);
			}
		}

		return $return;
	}

	/**
	 * Retrieves the strings from the $txt variable.
	 *
	 * @param string $template An email template to load
	 * @param int[] $users
	 * @param mixed[] $users_data Should at least contain the lngfile index
	 * @param string[] $lang_files Language files to load (optional)
	 * @param string[] $replacements Additional replacements for the loadEmailTemplate function (optional)
	 *
	 * @return mixed[]
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _loadStringsByTemplate($template, $users, $users_data, $lang_files = array(), $replacements = array())
	{
		require_once(SUBSDIR . '/Mail.subs.php');

		$lang = $this->user->language;
		$langs = array();
		foreach ($users as $user)
		{
			$langs[$users_data[$user]['lngfile']] = $users_data[$user]['lngfile'];
		}

		// Let's load all the languages into a cache thingy.
		$langtxt = array();
		foreach ($langs as $lang)
		{
			$langtxt[$lang] = loadEmailTemplate($template, $replacements, $lang, false, true, array('digest', 'snippet'), $lang_files);
		}

		// Better be sure we have the correct language loaded (though it may be useless)
		if (!empty($lang_files) && $lang !== $this->user->language)
		{
			foreach ($lang_files as $file)
			{
				Txt::load($file);
			}
		}

		return $langtxt;
	}
}
