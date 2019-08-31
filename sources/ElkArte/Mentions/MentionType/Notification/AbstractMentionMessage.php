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

namespace ElkArte\Mentions\MentionType\Notification;

use ElkArte\Mentions\MentionType\NotificationInterface;
use ElkArte\Database\QueryInterface;
use ElkArte\UserInfo;

/**
 * Class AbstractMentionMessage
 */
abstract class AbstractMentionMessage implements NotificationInterface
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
	protected $_db = null;

	/**
	 * The current user object
	 *
	 * @var \ElkArte\ValuesContainer
	 */
	protected $user = null;

	/**
	 * The \ElkArte\NotificationsTask in use
	 *
	 * @var \ElkArte\NotificationsTask
	 */
	protected $_task = null;

	/**
	 * @param \ElkArte\Database\QueryInterface $db
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
	public function getUsersToNotify()
	{
		return (array) $this->_task['source_data']['id_members'];
	}

	/**
	 * {@inheritdoc }
	 */
	abstract public function getNotificationBody($lang_data, $members);

	/**
	 * {@inheritdoc }
	 */
	public function setTask(\ElkArte\NotificationsTask $task)
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
	 * Does the replacement of some placeholders with the corresponding
	 * text/link/url.
	 *
	 * @param string $template An email template to load
	 * @param string[] $keys Pair values to match the $txt indexes to subject and body
	 * @param int[] $members
	 * @param \ElkArte\NotificationsTask $task
	 * @param string[] $lang_files Language files to load (optional)
	 * @param string[] $replacements Additional replacements for the loadEmailTemplate function (optional)
	 * @return mixed[]
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _getNotificationStrings($template, $keys, $members, \ElkArte\NotificationsTask $task, $lang_files = array(), $replacements = array())
	{
		$members_data = $task->getMembersData();

		$return = array();
		if (!empty($template))
		{
			foreach ($members as $member)
			{
				$replacements['REALNAME'] = $members_data[$member]['real_name'];
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
			$langtxt[$lang] = loadEmailTemplate($template, $replacements, $lang, true, array('digest', 'snippet'), $lang_files);
		}

		// Better be sure we have the correct language loaded (though it may be useless)
		if (!empty($lang_files) && $lang !== $this->user->language)
		{
			foreach ($lang_files as $file)
				theme()->getTemplates()->loadLanguageFile($file);
		}

		return $langtxt;
	}
}
