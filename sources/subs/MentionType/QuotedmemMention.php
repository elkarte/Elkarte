<?php

/**
 * Handles mentionf of members whose messages has been quoted.
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

class Quotedmem_Mention extends Mention_BoardAccess_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	protected $_type = 'quotedmem';

	/**
	 * {@inheritdoc }
	 */
	public static function getEvents($controller)
	{
		$methods = array(
			'post' => array(
				'after_save_post' => array('msgOptions', 'becomesApproved', 'posterOptions')
			),
			'display' => array('prepare_context' => array('virtual_msg')),
		);

		if (isset($methods[$controller]))
			return $methods[$controller];
		else
			return array();
	}

	/**
	 * Listener attached to the prepare_context event of the Display controller
	 * used to mark a mention as read.
	 *
	 * @param int $virtual_msg
	 * @global $modSettings
	 * @global $_REQUEST
	 */
	public function display_prepare_context($virtual_msg)
	{
		global $modSettings;

		// Mark the mention as read if requested
		if (isset($_REQUEST['mentionread']) && !empty($virtual_msg))
		{
			$mentions = new \Mentioning(database(), new \Data_Validator(), $modSettings['enabled_mentions']);
			$mentions->markread((int) $_REQUEST['item']);
		}
	}

	/**
	 * Listener attached to the after_save_post event of the Post controller.
	 *
	 * @param mixed[] $msgOptions
	 * @param bool $becomesApproved
	 * @param mixed[] $posterOptions
	 */
	public function post_after_save_post($msgOptions, $becomesApproved, $posterOptions)
	{
		if ($becomesApproved)
			$this->_sendNotification($msgOptions['body'], $msgOptions['id'], $posterOptions);
	}

	/**
	 * Checks if a message has been quoted and if so notifies the owner
	 *
	 * @param string $text The message body
	 * @param int $msg_id The message id of the post containing the quote
	 * @param mixed[] $posterOptions
	 */
	protected function _sendNotification($text, $msg_id, $posterOptions)
	{
		$quoted_names = $this->_findQuotedMembers($text);
		if (!empty($quoted_names))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$members_id = membersBy(array(array('or' => 'member_names')), array('member_names' => $quoted_names, 'limit' => count($quoted_names)));
		}

		if (!empty($members_id))
		{
			$notifier = \Notifications::getInstance();
			$notifier->add(new \Notifications_Task(
				'quotedmem',
				$msg_id,
				$posterOptions['id'],
				array('id_members' => $members_id, 'notifier_data' => $posterOptions)
			));
		}
	}

	/**
	 * Finds member names in quote tags present in a passed string.
	 *
	 * @param string $text the string to look into for member names
	 * @return string[] An array of member names
	 */
	protected function _findQuotedMembers($text)
	{
/*
The following bbcode is for testing, to be moved to a test when ready.

[quote author=emanuele date=1430141592 link=msg=972]
[quote author=lele]test[/quote]
[/quote]

[quote author=lele non nested]test[/quote]

[quote author=lele full date=1430141592 link=msg=972]test[/quote]


[quote author=emanuele date=1430141592 link=msg=972]
[quote author=lele multi1]
[quote author=lele multi2]
[quote author=lele multi3]
[quote author=lele]test[/quote]
[/quote]
[/quote]
[/quote]
[/quote]
*/
		if (strpos($text, '[quote ') !== false)
		{
			$quoted = array();
			$blocks = preg_split('~\[quote~', $text);

			$skip_next = false;
			foreach ($blocks as $block)
			{
				if (empty($block))
					continue;

				if (!$skip_next)
				{
					preg_match('~author=(.*?)(\]|date=|link=)~', $block, $match);

					if (!empty($match[1]))
						$quoted[] = trim($match[1]);
				}

				$skip_next = strpos($block, '[/quote]') === false;
			}
			return $quoted;
		}
		else
			return false;
	}

	/**
	 * {@inheritdoc }
	 */
	public function getNotificationBody($frequency, $members)
	{
		switch ($frequency)
		{
			case 'email_daily':
			case 'email_weekly':
				$keys = array('subject' => 'notify_quotedmem_digest', 'body' => 'notify_quotedmem_snippet');
				break;
			case 'email':
				// @todo send an email for any like received may be a bit too much. Consider not allowing this method of notification
				$keys = array('subject' => 'notify_quotedmem_subject', 'body' => 'notify_quotedmem_body');
				break;
			case 'notification':
			default:
				return $this->_getNotificationStrings('', array('subject' => $this->_type, 'body' => $this->_type), $members, $this->_task);
		}

		$replacements = array(
			'ACTIONNAME' => $this->_task['notifier_data']['name'],
			'MSGLINK' => replaceBasicActionUrl('{script_url}?msg=' . $this->_task->id_target),
		);

		return $this->_getNotificationStrings('notify_quotedmem', $keys, $members, $this->_task, array(), $replacements);
	}
}