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

/**
 * Class Buddy
 *
 * Handles mentioning of buddies
 */
class Buddy extends AbstractNotificationMessage
{
	/** {@inheritDoc} */
	protected static $_type = 'buddy';

	/**
	 * {@inheritDoc}
	 */
	public function getNotificationBody($lang_data, $members)
	{
		if (empty($lang_data['subject']))
		{
			return $this->_getNotificationStrings('',
				[
					'subject' => static::$_type,
					'body' => static::$_type
				], $members, $this->_task);
		}

		$keys = [
			'subject' => 'notify_new_buddy_' . $lang_data['subject'],
			'body' => 'notify_new_buddy_' . $lang_data['body']
		];

		$notifier = $this->_task->getNotifierData();

		$replacements = [
			'ACTIONNAME' => $notifier['real_name'],
		];

		return $this->_getNotificationStrings('notify_new_buddy', $keys, $members, $this->_task, [], $replacements);
	}
}
