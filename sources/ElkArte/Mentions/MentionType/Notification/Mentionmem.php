<?php

/**
 * Handles the mentioning of members (@member stuff)
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
 * Class Mentionmem
 *
 * Handles the mentioning of members (@ member actions)
 */
class Mentionmem extends AbstractNotificationMessage
{
	/** {@inheritDoc} */
	protected static $_type = 'mentionmem';

	/**
	 * {@inheritDoc}
	 */
	public function getNotificationBody($lang_data, $members)
	{
		if (empty($lang_data['suffix']))
		{
			return $this->_getNotificationStrings('', [
				'subject' => static::$_type,
				'body' => static::$_type],
				$members, $this->_task);
		}

		$keys = [
			'subject' => 'notify_mentionmem_' . $lang_data['subject'],
			'body' => 'notify_mentionmem_' . $lang_data['body']]
		;

		$replacements = [
			'ACTIONNAME' => $this->_task['source_data']['notifier_data']['name'],
			'SUBJECT' => $this->_task['source_data']['subject'],
			'MSGLINK' => replaceBasicActionUrl('{script_url}?msg=' . $this->_task->id_target),
		];

		return $this->_getNotificationStrings('notify_mentionmem', $keys, $members, $this->_task, [], $replacements);
	}
}
