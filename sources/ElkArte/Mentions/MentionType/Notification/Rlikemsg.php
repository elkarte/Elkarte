<?php

/**
 * Handles the notification (or non-notification) of removed likes.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions\MentionType\Notification;

use ElkArte\Mentions\MentionType\Notification\AbstractMentionBoardAccess;

/**
 * Class RlikemsgMention
 *
 * Handles the notification (or non-notification) of removed likes.
 */
class Rlikemsg extends AbstractMentionBoardAccess
{
	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'rlikemsg';

	/**
	 * {@inheritdoc }
	 */
	public function getUsersToNotify()
	{
		if ($this->_task['source_data']['rlike_notif'])
		{
			return (array) $this->_task['source_data']['id_members'];
		}
		else
		{
			return array();
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public function getNotificationBody($lang_data, $users)
	{
		return array();
	}

	/**
	 * Depending on the value of $this->_task['source_data']['rlike_notif']
	 * May notify the user about a like removed, or softly and gently remove
	 * a 'likemsg' mention when the post is unliked.
	 *
	 * @package Mentions
	 *
	 * @param int $member_from the id of the member mentioning
	 * @param int[] $members_to an array of ids of the members mentioned
	 * @param int $target the id of the target involved in the mention
	 * @param string|null $time optional value to set the time of the mention, defaults to now
	 * @param int|null $status status to change the mention to, if no notification,
	 *             - default is to set it as read (status = 1)
	 * @param bool|null $is_accessible optional if the mention is accessible to the user
	 * @return array|int[]
	 */
	public function insert($member_from, $members_to, $target, $time = null, $status = null, $is_accessible = null)
	{
		if ($this->_task['source_data']['rlike_notif'])
		{
			return parent::insert($member_from, $members_to, $target, $time, $status, $is_accessible);
		}
		else
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
					'status' => $status === null ? 1 : $status,
					'unread' => 0,
				)
			);

			return array();
		}
	}
}
