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

class Mentionmem_Mention extends Mention_BoardAccess_Abstract
{
	protected $_type = 'mentionmem';
	protected $_actually_mentioned = array();

	/**
	 * {@inheritdoc }
	 */
	public static function getEvents($controller)
	{
		$methods = array(
			'post' => array(
				'prepare_context' => array(),
				'before_save_post' => array(),
				'after_save_post' => array('msgOptions', 'becomesApproved', 'posterOptions')
			),
			'display' => array('prepare_context' => array('virtual_msg')),
		);

		if (isset($methods[$controller]))
			return $methods[$controller];
		else
			return array();
	}

	public function display_prepare_context($virtual_msg)
	{
		global $options, $modSettings;

		// Mark the mention as read if requested
		if (isset($_REQUEST['mentionread']) && !empty($virtual_msg))
		{
			$mentions = new \Mentioning(database(), new \Data_Validator(), $modSettings['enabled_mentions']);
			$mentions->markread((int) $_REQUEST['item']);
		}

		$this->_setup_editor(empty($options['use_editor_quick_reply']));
	}

	protected function _setup_editor($simple = false)
	{
		// Just using the plain text quick reply and not the editor
		if ($simple)
			loadJavascriptFile(array('jquery.atwho.js', 'jquery.caret.min.js', 'mentioning.js'));

		loadCSSFile('jquery.atwho.css');

		addInlineJavascript('
		$(document).ready(function () {
			for (var i = 0, count = all_elk_mentions.length; i < count; i++)
				all_elk_mentions[i].oMention = new elk_mentions(all_elk_mentions[i].oOptions);
		});');
	}

	public function post_prepare_context()
	{
		global $context;

		if (!empty($_REQUEST['uid']))
			$context['member_ids'] = array_unique(array_map('intval', $_REQUEST['uid']));

		$context['mentions_enabled'] = true;
		$this->_setup_editor();
	}

	public function post_before_save_post()
	{

		if (!empty($_REQUEST['uid']))
		{
			$query_params = array(
				'member_ids' => array_unique(array_map('intval', $_REQUEST['uid']))
			);

			require_once(SUBSDIR . '/Members.subs.php');
			$mentioned_members = membersBy('member_ids', $query_params, true);
			$replacements = 0;
			$this->_actually_mentioned = array();

			foreach ($mentioned_members as $member)
			{
				$_POST['message'] = str_replace('@' . $member['real_name'], '[member=' . $member['id_member'] . ']' . $member['real_name'] . '[/member]', $_POST['message'], $replacements);
				if ($replacements > 0)
					$this->_actually_mentioned[] = $member['id_member'];
			}
		}
	}

	public function post_after_save_post($msgOptions, $becomesApproved, $posterOptions)
	{
		if (!empty($this->_actually_mentioned))
		{
			$notifier = Notifications::getInstance();
			$notifier->add(new \Notifications_Task(
				'mentionmem',
				$msgOptions['id'],
				$posterOptions['id'],
				array('id_members' => $this->_actually_mentioned, 'notifier_data' => $posterOptions)
			));
		}
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
				$keys = array('subject' => 'notify_mentionmem_digest', 'body' => 'notify_mentionmem_snippet');
				break;
			case 'email':
				// @todo send an email for any like received may be a bit too much. Consider not allowing this method of notification
				$keys = array('subject' => 'notify_mentionmem_subject', 'body' => 'notify_mentionmem_body');
				break;
			case 'notification':
			default:
				return $this->_getNotificationStrings('', array('subject' => $this->_type, 'body' => $this->_type), $members, $this->_task);
		}

		$replacements = array(
			'ACTIONNAME' => $this->_task['notifier_data']['name'],
			'MSGLINK' => replaceBasicActionUrl('{script_url}?msg=' . $this->_task->id_target),
		);

		return $this->_getNotificationStrings('notify_mentionmem', $keys, $members, $this->_task, array(), $replacements);
	}
}