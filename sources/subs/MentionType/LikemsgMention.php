<?php

/**
 * Handles mentions of likes
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

namespace ElkArte\sources\subs\MentionType;

/**
 * Class Likemsg_Mention
 *
 * Handles mentions of likes
 *
 * @package ElkArte\sources\subs\MentionType
 */
class Likemsg_Mention extends Mention_BoardAccess_Abstract
{
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
			'MSGLINK' => replaceBasicActionUrl('{script_url}?msg=' . $this->_task->id_target),
		);

		return $this->_getNotificationStrings('notify_new_likemsg', $keys, $members, $this->_task, array(), $replacements);
	}
}