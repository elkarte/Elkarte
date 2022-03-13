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

use ElkArte\Mentions\MentionType\AbstractNotificationBoardAccess;
use ElkArte\Mentions\MentionType\CommonConfigTrait;

/**
 * Class Rlikemsg
 *
 * Handles the notification (or non-notification) of removed likes.
 */
class Rlikemsg extends AbstractNotificationBoardAccess
{
	use CommonConfigTrait;

	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'rlikemsg';

	/**
	 * {@inheritdoc }
	 */
	public function setUsersToNotify()
	{
		if ($this->_task['source_data']['rlike_notif'])
		{
			parent::setUsersToNotify();
		}
		elseif (isset($this->_task))
		{
			// I'm not entirely sure if this is necessary, but better safe than sorry at this point.
			$this->_task->setMembers([]);
		}
	}

	/**
	 * We don't support email notification of this action, just notification
	 */
	public function getNotificationBody($lang_data, $members)
	{
		return $this->_getNotificationStrings('', array('subject' => static::$_type, 'body' => static::$_type), $members, $this->_task);
	}

	/**
	 * Depending on the value of $this->_task['source_data']['rlike_notif']
	 * May notify the user about a like removed, or softly and gently remove
	 * a 'likemsg' mention when the post is unliked.
	 *
	 * @param int $member_from the id of the member mentioning
	 * @param int[] $members_to an array of ids of the members mentioned
	 * @param int $target the id of the target involved in the mention
	 * @param string|null $time optional value to set the time of the mention, defaults to now
	 * @param int|null $status status to change the mention to, if no notification,
	 *             - default is to set it as read (status = 1)
	 * @param bool|null $is_accessible optional if the mention is accessible to the user
	 * @return array|int[]
	 * @package Mentions
	 * @throws \ElkArte\Exceptions\Exception
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
					'status' => $status ?? 1,
					'unread' => 0,
				)
			);

			return array();
		}
	}
}
