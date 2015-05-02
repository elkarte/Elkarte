<?php

/**
 * Handles mentions of likes
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev Release Candidate 2
 *
 */

namespace ElkArte\sources\subs\MentionType;

if (!defined('ELK'))
	die('No access...');

class Likemsg_Mention extends Mention_BoardAccess_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	protected $_type = 'likemsg';

	/**
	 * {@inheritdoc }
	 */
	public function getNotificationBody($frequency, $members)
	{
		switch ($frequency)
		{
			case 'email_daily':
			case 'email_weekly':
				$keys = array('subject' => 'notify_new_likemsg_digest', 'body' => 'notify_new_likemsg_snippet');
				break;
			case 'email':
				// @todo send an email for any like received may be a bit too much. Consider not allowing this method of notification
				$keys = array('subject' => 'notify_new_likemsg_subject', 'body' => 'notify_new_likemsg_body');
				break;
			case 'notification':
			default:
				return $this->_getNotificationStrings('', array('subject' => $this->_type, 'body' => $this->_type), $members, $this->_task);
		}

		$notifier = $this->_task->getNotifierData();
		$replacements = array(
			'ACTIONNAME' => $notifier['real_name'],
			'MSGLINK' => replaceBasicActionUrl('{script_url}?msg=' . $this->_task->id_target),
		);

		return $this->_getNotificationStrings('notify_new_buddy', $keys, $members, $this->_task, array(), $replacements);
	}
}