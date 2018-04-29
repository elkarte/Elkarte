<?php

/**
 * Handles notifying users who have had email notifications disabled for failure to deliver
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
 * Class Mailfail_Mention
 *
 * Handles notifying users who have had email notifications disabled for failure to deliver
 *
 * @package ElkArte\sources\subs\MentionType
 */
class Mailfail_Mention extends Mention_BoardAccess_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'mailfail';

	/**
	 * {@inheritdoc }
	 */
	public function getNotificationBody($lang_data, $members)
	{
		$keys = array('subject' => 'notify_mailfail_' . $lang_data['subject'], 'body' => 'notify_mailfail_' . $lang_data['body']);

		$replacements = array(
			'ACTIONNAME' => $this->_task['source_data']['notifier_data']['name'],
			'MSGLINK' => replaceBasicActionUrl('{script_url}?msg=' . $this->_task->id_target),
		);

		return $this->_getNotificationStrings('notify_mailfail', $keys, $members, $this->_task, array(), $replacements);
	}
}