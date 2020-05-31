<?php

/**
 * Handles mentioning of members whose messages has been quoted.
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
 * Class QuotedmemMention
 *
 * Handles mentioning of members whose messages has been quoted
 */
class Quotedmem extends AbstractNotificationBoardAccess
{
	use CommonConfigTrait;

	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'quotedmem';

	/**
	 * {@inheritdoc }
	 */
	public function getNotificationBody($lang_data, $members)
	{
		if (empty($lang_data['suffix']))
		{
			return $this->_getNotificationStrings('', array('subject' => static::$_type, 'body' => static::$_type), $members, $this->_task);
		}
		else
		{
			$keys = array('subject' => 'notify_quotedmem_' . $lang_data['subject'], 'body' => 'notify_quotedmem_' . $lang_data['body']);
		}

		$replacements = array(
			'ACTIONNAME' => $this->_task['source_data']['notifier_data']['name'],
			'MSGLINK' => replaceBasicActionUrl('{script_url}?msg=' . $this->_task->id_target),
		);

		return $this->_getNotificationStrings('notify_quotedmem', $keys, $members, $this->_task, array(), $replacements);
	}
}
