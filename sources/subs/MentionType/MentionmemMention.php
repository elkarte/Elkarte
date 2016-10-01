<?php

/**
 * Handles the mentioning of members (@member stuff)
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 *
 */

namespace ElkArte\sources\subs\MentionType;

/**
 * Class Mentionmem_Mention
 *
 * Handles the mentioning of members (@ member actions)
 *
 * @package ElkArte\sources\subs\MentionType
 */
class Mentionmem_Mention extends Mention_BoardAccess_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'mentionmem';

	/**
	 * List of members mentioned
	 *
	 * @var int[]
	 */
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
		{
			return $methods[$controller];
		}
		else
		{
			return array();
		}
	}

	/**
	 * Listener attached to the prepare_context event of the Display controller
	 * used to mark a mention as read.
	 *
	 * @param int $virtual_msg
	 */
	public function display_prepare_context($virtual_msg)
	{
		global $options, $modSettings, $context;

		// Mark the mention as read if requested
		if (isset($_REQUEST['mentionread']) && !empty($virtual_msg))
		{
			$mentions = new \Mentioning(database(), new \Data_Validator(), $modSettings['enabled_mentions']);
			$mentions->markread((int) $_REQUEST['item']);
		}

		$context['mentions_enabled'] = true;

		$this->_setup_editor(empty($options['use_editor_quick_reply']));
	}

	/**
	 * Takes care of setting up the editor javascript.
	 *
	 * @param bool $simple If true means the plain textarea, otherwise SCEditor.
	 */
	protected function _setup_editor($simple = false)
	{
		// Just using the plain text quick reply and not the editor
		if ($simple)
		{
			loadJavascriptFile(array('jquery.atwho.min.js', 'jquery.caret.min.js'));
		}

		loadJavascriptFile(array('mentioning.js'));

		loadCSSFile('jquery.atwho.css');

		addInlineJavascript('
		$(function() {
			for (var i = 0, count = all_elk_mentions.length; i < count; i++)
				all_elk_mentions[i].oMention = new elk_mentions(all_elk_mentions[i].oOptions);
		});');
	}

	/**
	 * Listener attached to the prepare_context event of the Post controller.
	 *
	 * @global $_REQUEST
	 */
	public function post_prepare_context()
	{
		global $context;

		if (!empty($_REQUEST['uid']))
		{
			$context['member_ids'] = array_unique(array_map('intval', $_REQUEST['uid']));
		}

		$context['mentions_enabled'] = true;
		$this->_setup_editor();
	}

	/**
	 * Listener attached to the before_save_post event of the Post controller.
	 *
	 * @global $_REQUEST
	 * @global $_POST
	 */
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
				{
					$this->_actually_mentioned[] = $member['id_member'];
				}
			}
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
		if (!empty($this->_actually_mentioned))
		{
			$notifier = \Notifications::getInstance();
			$notifier->add(new \Notifications_Task(
				'mentionmem',
				$msgOptions['id'],
				$posterOptions['id'],
				array('id_members' => $this->_actually_mentioned, 'notifier_data' => $posterOptions, 'status' => $becomesApproved
					? 'new'
					: 'unapproved')
			));
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public static function getModules($modules)
	{
		$modules['mentions'] = array('post', 'display');

		return $modules;
	}

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
			$keys = array('subject' => 'notify_mentionmem_' . $lang_data['subject'], 'body' => 'notify_mentionmem_' . $lang_data['body']);
		}

		$replacements = array(
			'ACTIONNAME' => $this->_task['notifier_data']['name'],
			'MSGLINK' => replaceBasicActionUrl('{script_url}?msg=' . $this->_task->id_target),
		);

		return $this->_getNotificationStrings('notify_mentionmem', $keys, $members, $this->_task, array(), $replacements);
	}
}