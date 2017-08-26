<?php

/**
 * Common methods shared by any type of mention so far.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 2
 *
 */

namespace ElkArte\sources\subs\MentionType;

/**
 * Class Mention_Message_Abstract
 *
 * @package ElkArte\sources\subs\MentionType
 */
abstract class Mention_Message_Abstract implements Mention_Type_Interface
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
	 * @var \Database
	 */
	protected $_db = null;

	/**
	 * The Notifications_Task in use
	 *
	 * @var \Notifications_Task
	 */
	protected $_task = null;

	/**
	 * This static function is used to find the events to attach to a controller.
	 * The implementation of this abstract class is empty because it's
	 * just a dummy to cover mentions that don't need to register anything.
	 *
	 * @param string $controller The name of the controller initializing the system
	 */
	public static function getEvents($controller)
	{
		return array();
	}

	/**
	 * {@inheritdoc }
	 */
	public static function getModules($modules)
	{
		return $modules;
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
	abstract public function view($type, &$mentions);

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
	public function setTask(\Notifications_Task $task)
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
	 * @param string $row A text string on which replacements are done
	 * @return string the input string with the placeholders replaced
	 */
	protected function _replaceMsg($row)
	{
		global $txt, $scripturl, $context;

		return str_replace(
			array(
				'{msg_link}',
				'{msg_url}',
				'{subject}',
			),
			array(
				'<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_target'] . ';mentionread;mark=read;' . $context['session_var'] . '=' . $context['session_id'] . ';item=' . $row['id_mention'] . '#msg' . $row['id_target'] . '">' . $row['subject'] . '</a>',
				$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_target'] . ';mentionread;' . $context['session_var'] . '=' . $context['session_id'] . 'item=' . $row['id_mention'] . '#msg' . $row['id_target'],
				$row['subject'],
			),
			$txt['mention_' . $row['mention_type']]);
	}

	/**
	 * Does the replacement of some placeholders with the corresponding
	 * text/link/url.
	 *
	 * @param string $template An email template to load
	 * @param string[] $keys Pair values to match the $txt indexes to subject and body
	 * @param int[] $members
	 * @param \Notifications_Task $task
	 * @param string[] $lang_files Language files to load (optional)
	 * @param string[] $replacements Additional replacements for the loadEmailTemplate function (optional)
	 * @return mixed[]
	 * @throws \Elk_Exception
	 */
	protected function _getNotificationStrings($template, $keys, $members, \Notifications_Task $task, $lang_files = array(), $replacements = array())
	{
		$members_data = $task->getMembersData();

		$return = array();
		if (!empty($template))
		{
			foreach ($members as $member)
			{
				$langstrings = $this->_loadStringsByTemplate($template, $keys, $members, $members_data, $lang_files, $replacements);
				$replacements['REALNAME'] = $members_data[$member]['real_name'];

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
	 * @param string[] $keys Pair values to match the $txt indexes to subject and body
	 * @param int[] $users
	 * @param mixed[] $users_data Should at least contain the lngfile index
	 * @param string[] $lang_files Language files to load (optional)
	 * @param string[] $replacements Additional replacements for the loadEmailTemplate function (optional)
	 * @return mixed[]
	 * @throws \Elk_Exception
	 */
	protected function _loadStringsByTemplate($template, $keys, $users, $users_data, $lang_files = array(), $replacements = array())
	{
		global $user_info;

		require_once(SUBSDIR . '/Mail.subs.php');

		$lang = $user_info['language'];
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
		if (!empty($lang_files) && $lang !== $user_info['language'])
		{
			foreach ($lang_files as $file)
				loadLanguage($file);
		}

		return $langtxt;
	}

	/**
	 * {@inheritdoc }
	 */
	public function setDb(\Database $db)
	{
		$this->_db = $db;
	}

	/**
	 * {@inheritdoc }
	 */
	public function insert($member_from, $members_to, $target, $time = null, $status = null, $is_accessible = null)
	{
		$inserts = array();

		// $time is not checked because it's useless
		$request = $this->_db->query('', '
			SELECT id_member
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
		);
		$existing = array();
		while ($row = $this->_db->fetch_assoc($request))
			$existing[] = $row['id_member'];
		$this->_db->free_result($request);

		// If the member has already been mentioned, it's not necessary to do it again
		foreach ($members_to as $id_member)
		{
			if (!in_array($id_member, $existing))
			{
				$inserts[] = array(
					$id_member,
					$target,
					$status === null ? 0 : $status,
					$is_accessible === null ? 1 : $is_accessible,
					$member_from,
					$time === null ? time() : $time,
					static::$_type
				);
			}
		}

		if (empty($inserts))
			return;

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
}
