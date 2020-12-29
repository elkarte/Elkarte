<?php

/**
 * Handles mentions of likes
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
 * Class LikemsgMention
 *
 * Handles mentions of likes
 */
class Likemsg extends AbstractNotificationBoardAccess
{
	use CommonConfigTrait;

	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'likemsg';

	/**
	 * {@inheritdoc }
	 */
	public function getNotificationBody($lang_data, $members)
	{
		// @todo send an email for any like received may be a bit too much. Consider not allowing this method of notification
		if (empty($lang_data['suffix']))
		{
			return $this->_getNotificationStrings('', array('subject' => static::$_type, 'body' => static::$_type), $members, $this->_task);
		}
		else
		{
			$keys = array('subject' => 'notify_new_likemsg_' . $lang_data['subject'], 'body' => 'notify_new_likemsg_' . $lang_data['body']);
		}

		$notifier = $this->_task->getNotifierData();
		$replacements = array(
			'ACTIONNAME' => $notifier['real_name'],
			'SUBJECT' =>  $this->_task['source_data']['subject'],
			'MSGLINK' => replaceBasicActionUrl('{script_url}?msg=' . $this->_task->id_target),
		);

		return $this->_getNotificationStrings('notify_new_likemsg', $keys, $members, $this->_task, array(), $replacements);
	}
}
