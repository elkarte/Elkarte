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
use ElkArte\Helper\ValuesContainer;
use ElkArte\Languages\Txt;
use ElkArte\MembersList;
use ElkArte\Notifications\NotificationsTask;
use ElkArte\UserInfo;

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

	protected $_to_members_data;

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
	public static function hasHiddenInterface()
	{
		return false;
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

		// If they are using a buddy/ignore list, we need to take that into account
		$this->getMembersData($members_to);

		foreach ($members_to as $id_member)
		{
			// If the notification can not be sent, mark it as not accessible and read
			if (!$this->_validateMemberRelationship($member_from, $id_member))
			{
				$is_accessible = 0;
				$status = 1;
			}

			if (!in_array($id_member, $existing, true))
			{
				$inserts[] = [
					$id_member,
					$target,
					$status ?? 0,
					$is_accessible ?? 1,
					$member_from,
					$time ?? time(),
					static::$_type
				];

				// Don't update the mention counter if they can't really see this
				if ($is_accessible !== 0)
				{
					$actually_mentioned[] = $id_member;
				}
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
	 * Returns basic data about the members to be notified.
	 *
	 * @return array
	 */
	protected function getMembersData($members_to): array
	{
		if ($this->_to_members_data === null)
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$this->_to_members_data = getBasicMemberData($members_to, ['preferences' => true, 'lists' => 'true']);
		}

		return $this->_to_members_data;
	}

	/**
	 * Validates the relationship between two members.
	 *
	 * @param int $fromMember The ID of the member sending the notification.
	 * @param int $toMember The ID of the member receiving the notification.
	 * @return bool Returns true if the notification can be sent, false otherwise.
	 */
	protected function _validateMemberRelationship($fromMember, $toMember): bool
	{
		// Everyone
		if (empty($this->_to_members_data[$toMember]['notify_from']))
		{
			return true;
		}

		// Not those on my ignore list
		if ($this->_to_members_data[$toMember]['notify_from'] === 1)
		{
			return $this->notOnIgnoreList($toMember, $fromMember);
		}

		// Only friends and pesky admins
		if ($this->_to_members_data[$toMember]['notify_from'] === 2)
		{
			return $this->isBuddyNotificationAllowed($fromMember, $toMember);
		}

		return false;
	}

	/**
	 * Check if a member is not on the ignore list of another member
	 *
	 * @param int $toMember The ID of the member to check if they are on the ignore list
	 * @param int $fromMember The ID of the member who is being checked against the ignore list
	 * @return bool Returns true if the fromMember is not on the ignore list of the toMember, false otherwise
	 */
	public function notOnIgnoreList(int $toMember, int $fromMember): bool
	{
		if (empty($this->_to_members_data[$toMember]['pm_ignore_list']))
		{
			return true;
		}

		$ignoreUsers = array_map('intval', explode(',', $this->_to_members_data[$toMember]['pm_ignore_list']));
		if (in_array($fromMember, $ignoreUsers, true))
		{
			return false;
		}

		return true;
	}

	/**
	 * Determines if buddy notification is allowed between two members.
	 *
	 * @param int $fromMember The member ID of the sender.
	 * @param int $toMember The member ID of the recipient.
	 *
	 * @return bool True if buddy notification is allowed, false otherwise.
	 */
	public function isBuddyNotificationAllowed(int $fromMember, int $toMember): bool
	{
		// Admins/Global mods get a pass
		MembersList::load($fromMember, false, 'profile');
		$fromProfile = MembersList::get($fromMember);
		$groups = array_map('intval', array_merge([$fromProfile['id_group'], $fromProfile['id_post_group']], (empty($fromProfile['additional_groups']) ? [] : explode(',', $fromProfile['additional_groups']))));
		if (in_array(1, $groups, true) || in_array(2, $groups, true))
		{
			return true;
		}

		// No buddies, no notification either
		if (empty($this->_to_members_data[$toMember]['buddy_list']))
		{
			return false;
		}

		$buddyList = array_map('intval', explode(',', $this->_to_members_data[$toMember]['buddy_list']));
		if (in_array($fromMember, $buddyList, true))
		{
			return true;
		}

		return false;
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
	protected function _getNotificationStrings($template, $keys, $members, NotificationsTask $task, $lang_files = [], $replacements = []): array
	{
		$recipientData = $task->getMembersData();

		$return = [];

		// Templates are for outbound emails
		if (!empty($template))
		{
			require_once(SUBSDIR . '/Notification.subs.php');

			foreach ($members as $member)
			{
				$replacements['REALNAME'] = $recipientData[$member]['real_name'];
				$replacements['UNSUBSCRIBELINK'] = replaceBasicActionUrl('{script_url}?action=notify;sa=unsubscribe;token=' .
					getNotifierToken($member, $recipientData[$member]['email_address'], $recipientData[$member]['password_salt'], $task->notification_type, $task->id_target));
				$langStrings = $this->_loadStringsByTemplate($template, $members, $recipientData, $lang_files, $replacements);

				$return[] = [
					'id_member_to' => $member,
					'email_address' => $recipientData[$member]['email_address'],
					'subject' => $langStrings[$recipientData[$member]['lngfile']]['subject'],
					'body' => $langStrings[$recipientData[$member]['lngfile']]['body'],
					'last_id' => 0
				];
			}
		}
		// No template, must be an on-site notification
		else
		{
			foreach ($members as $member)
			{
				$return[] = [
					'id_member_to' => $member,
					'email_address' => $recipientData[$member]['email_address'],
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
	protected function _loadStringsByTemplate($template, $users, $users_data, $lang_files = [], $replacements = []): array
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
			$langtxt[$lang] = loadEmailTemplate($template, $replacements, $lang, false, true, ['digest', 'snippet'], $lang_files);
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
