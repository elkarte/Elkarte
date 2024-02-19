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
use ElkArte\Exceptions\Exception;
use ElkArte\Languages\Txt;
use ElkArte\Notifications\NotificationsTask;
use ElkArte\UserInfo;
use ElkArte\ValuesContainer;

/**
 * Class AbstractNotificationMessage
 */
abstract class AbstractNotificationMessage implements NotificationInterface
{
	/** @var string The identifier of the mention (the name that is stored in the db) */
	protected static $_type = '';

	/** @var QueryInterface The database object */
	protected $_db;

	/** @var ValuesContainer The current user object */
	protected $user;

	/** @var NotificationsTask The \ElkArte\NotificationsTask in use */
	protected $_task;

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param QueryInterface $db The database query interface to use.
	 * @param UserInfo $user The user info object to use.
	 * @return void
	 */
	public function __construct(QueryInterface $db, UserInfo $user)
	{
		$this->_db = $db;
		$this->user = $user;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getType()
	{
		return static::$_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setUsersToNotify()
	{
		if ($this->_task !== null)
		{
			$this->_task->setMembers((array) $this->_task['source_data']['id_members']);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	abstract public function getNotificationBody($lang_data, $members);

	/**
	 * {@inheritDoc}
	 */
	public function setTask(NotificationsTask $task)
	{
		$this->_task = $task;
	}

	/**
	 * {@inheritDoc}
	 * By default returns null.
	 */
	public function getLastId()
	{
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function isNotAllowed($method)
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function canUse()
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert($member_from, $members_to, $target, $time = null, $status = null, $is_accessible = null)
	{
		$inserts = [];

		// $time is not checked because it's useless
		$existing = [];
		$this->_db->fetchQuery('
			SELECT 
				id_member
			FROM {db_prefix}log_mentions
			WHERE id_member IN ({array_int:members_to})
				AND mention_type = {string:type}
				AND id_member_from = {int:member_from}
				AND id_target = {int:target}',
			[
				'members_to' => $members_to,
				'type' => static::$_type,
				'member_from' => $member_from,
				'target' => $target,
			]
		)->fetch_callback(
			static function ($row) use (&$existing) {
				$existing[] = (int) $row['id_member'];
			}
		);

		// If the member has already been mentioned, it's not necessary to do it again
		$actually_mentioned = [];
		foreach ($members_to as $id_member)
		{
			if (!in_array((int) $id_member, $existing, true))
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
				[
					'id_member' => 'int',
					'id_target' => 'int',
					'status' => 'int',
					'is_accessible' => 'int',
					'id_member_from' => 'int',
					'log_time' => 'int',
					'mention_type' => 'string-12',
				],
				$inserts,
				['id_mention']
			);
		}

		return $actually_mentioned;
	}

	/**
	 * Returns an array of notification strings based on template and replacements.
	 *
	 * @param string $template The email notification template to use.
	 * @param array $keys Pair values to match the $txt indexes to subject and body
	 * @param array $members The array of member IDs to generate notification strings for.
	 * @param NotificationsTask $task The NotificationsTask object to retrieve member data from.
	 * @param array $lang_files An optional array of language files to load strings from.
	 * @param array $replacements Additional replacements for the loadEmailTemplate function (optional)
	 * @return array The array of generated notification strings.
	 */
	protected function _getNotificationStrings($template, $keys, $members, NotificationsTask $task, $lang_files = array(), $replacements = array())
	{
		$members_data = $task->getMembersData();

		$return = [];
		if (!empty($template))
		{
			require_once(SUBSDIR . '/Notification.subs.php');

			foreach ($members as $member)
			{
				$replacements['REALNAME'] = $members_data[$member]['real_name'];
				$replacements['UNSUBSCRIBELINK'] = replaceBasicActionUrl('{script_url}?action=notify;sa=unsubscribe;token=' .
					getNotifierToken($member, $members_data[$member]['email_address'], $members_data[$member]['password_salt'], $task->notification_type, $task->id_target));
				$langStrings = $this->_loadStringsByTemplate($template, $members, $members_data, $lang_files, $replacements);

				$return[] = [
					'id_member_to' => $member,
					'email_address' => $members_data[$member]['email_address'],
					'subject' => $langStrings[$members_data[$member]['lngfile']]['subject'],
					'body' => $langStrings[$members_data[$member]['lngfile']]['body'],
					'last_id' => 0
				];
			}
		}
		else
		{
			foreach ($members as $member)
			{
				$return[] = [
					'id_member_to' => $member,
					'email_address' => $members_data[$member]['email_address'],
					'subject' => $keys['subject'],
					'body' => $keys['body'] ?? '',
					'last_id' => 0
				];
			}
		}

		return $return;
	}

	/**
	 * Loads template strings for multiple languages based on a template, using $txt values
	 *
	 * @param string $template The email template name to load strings for.
	 * @param array $users An array of user IDs.
	 * @param array $users_data An array containing user data, must contain lngfile index
	 * @param array $lang_files Optional. An array of language files to load.
	 * @param array $replacements Optional. An array of replacements for the template.
	 * @return array An associative array where the keys are language codes and the values are the loaded template strings.
	 */
	protected function _loadStringsByTemplate($template, $users, $users_data, $lang_files = array(), $replacements = array())
	{
		require_once(SUBSDIR . '/Mail.subs.php');

		$lang = $this->user->language;
		$langs = [];
		foreach ($users as $user)
		{
			$langs[$users_data[$user]['lngfile']] = $users_data[$user]['lngfile'];
		}

		// Let's load all the languages into a cache thingy.
		$langtxt = [];
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
