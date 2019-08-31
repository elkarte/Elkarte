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

namespace ElkArte\Mentions\MentionType\Event;

use ElkArte\Mentions\MentionType\EventInterface;

/**
 * Class AbstractMentionMessage
 */
abstract class AbstractMentionMessage implements EventInterface
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
	 * @var \ElkArte\HttpReq
	 */
	protected $_request = null;

	/**
	 * The current user object
	 *
	 * @var \ElkArte\ValuesContainer
	 */
	protected $user = null;

	/**
	 * @param \ElkArte\HttpReq $http_req
	 */
	public function __construct(\ElkArte\HttpReq $http_req, $user)
	{
		$this->_request = $http_req;
		$this->user = $user;
	}

	/**
	 * This static function is used to find the events to attach to a controller.
	 * The implementation of this abstract class is empty because it's
	 * just a dummy to cover mentions that don't need to register anything.
	 *
	 * @param string $controller The name of the controller initializing the system
	 *
	 * @return array
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
	 * Does the replacement of some placeholders with the corresponding
	 * text/link/url.
	 *
	 * @param string[] $row A text string on which replacements are done
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

		$actually_mentioned = array();
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
}
