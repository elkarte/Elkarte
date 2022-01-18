<?php

/**
 * Handles mentioning of buddies
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions\MentionType\Notification;

use ElkArte\Mentions\MentionType\AbstractNotificationMessage;
use ElkArte\Mentions\MentionType\CommonConfigTrait;

/**
 * Class Buddy
 *
 * Handles mentioning of buddies
 */
class Buddy extends AbstractNotificationMessage
{
	use CommonConfigTrait;

	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'buddy';

	/**
	 * {@inheritdoc }
	 */
	public function getNotificationBody($lang_data, $members)
	{
		if (empty($lang_data['subject']))
		{
			return $this->_getNotificationStrings('', array('subject' => static::$_type, 'body' => static::$_type), $members, $this->_task);
		}
		else
		{
			$keys = array('subject' => 'notify_new_buddy_' . $lang_data['subject'], 'body' => 'notify_new_buddy_' . $lang_data['body']);
		}

		$notifier = $this->_task->getNotifierData();
		$replacements = array(
			'ACTIONNAME' => $notifier['real_name'],
		);

		return $this->_getNotificationStrings('notify_new_buddy', $keys, $members, $this->_task, array(), $replacements);
	}
}
