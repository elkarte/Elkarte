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
 *
 * @package ElkArte\Mentions\MentionType
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
}
