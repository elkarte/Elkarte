<?php

/**
 * Interface for mentions objects
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

namespace ElkArte\sources\subs\MentionType;

if (!defined('ELK'))
	die('No access...');

class Buddy_Mention extends Mention_Message_Abstract
{
	protected $_type = 'buddy';

	/**
	 * {@inheritdoc }
	 */
	public function view($type, &$mentions)
	{
		foreach ($mentions as $key => $row)
		{
			// To ensure it is not done twice
			if ($row['mention_type'] != $type)
				continue;

			$mentions[$key]['message'] = $this->_replaceMsg($row);
		}

		return false;
	}

	/**
	 * {@inheritdoc }
	 */
	public function getNotificationBody($frequency, $members)
	{
		switch ($frequency)
		{
			case 'email_weekly':
			case 'email_daily':
				$keys = array('subject' => 'notify_new_buddy_digest', 'body' => 'notify_new_buddy_snippet');
				break;
			case 'email':
				$keys = array('subject' => 'notify_new_buddy_subject', 'body' => 'notify_new_buddy_body');
				break;
			case 'notification':
			default:
				return $this->_getNotificationStrings('', array('subject' => $this->_type, 'body' => $this->_type), $members, $this->_task);
		}

		$notifier = $this->_task->getNotifierData();
		$replacements = array(
			'ACTIONNAME' => $notifier['real_name'],
		);

		return $this->_getNotificationStrings('notify_new_buddy', $keys, $members, $this->_task, array(), $replacements);
	}
}