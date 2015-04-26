<?php

/**
 * Interface for mentions objects
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev Release Candidate 2
 *
 */

namespace ElkArte\sources\subs\MentionType;

if (!defined('ELK'))
	die('No access...');

class Rlikemsg_Mention extends Mention_BoardAccess_Abstract
{
	protected static $_type = 'rlikemsg';

	/**
	 * {@inheritdoc }
	 */
	public function getUsersToNotify()
	{
		return array();
	}

	/**
	 * {@inheritdoc }
	 */
	public function getNotificationBody($frequency, $users, Notifications_Task $task)
	{
		return array();
	}

	/**
	 * Softly and gently removes a 'likemsg' mention when the post is unliked
	 *
	 * @package Mentions
	 * @param int $member_from the id of the member mentioning
	 * @param int[] $members_to an array of ids of the members mentioned
	 * @param int $target the id of the message involved in the mention
	 * @param string|null $time not used
	 * @param int $status status to change the mention to if found as unread,
	 *             - default is to set it as read (status = 1)
	 * @param bool|null $is_accessible not used
	 */
	public function insert($member_from, $members_to, $target, $time = null, $status = null, $is_accessible = null)
	{
		// If this like is still unread then we mark it as read and decrease the counter
		$this->_db->query('', '
			UPDATE {db_prefix}log_mentions
			SET status = {int:status}
			WHERE id_member IN ({array_int:members_to})
				AND mention_type = {string:type}
				AND id_member_from = {int:member_from}
				AND id_target = {int:target}
				AND status = {int:unread}',
			array(
				'members_to' => $members_to,
				'type' => 'likemsg',
				'member_from' => $member_from,
				'target' => $target,
				'status' => $newstatus,
				'unread' => 0,
			)
		);
	}
}