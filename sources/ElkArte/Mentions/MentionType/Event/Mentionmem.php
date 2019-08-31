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

namespace ElkArte\Mentions\MentionType\Event;

use ElkArte\Mentions\MentionType\Event\AbstractMentionBoardAccess;

/**
 * Class MentionmemMention
 *
 * Handles the mentioning of members (@ member actions)
 *
 * @package ElkArte\Mentions\MentionType
 */
class Mentionmem extends AbstractMentionBoardAccess
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
			$mentions = new \ElkArte\Mentions\Mentioning(database(), $this->user, new \ElkArte\DataValidator(), $modSettings['enabled_mentions']);
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

		theme()->addInlineJavascript('
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
			$notifier = \ElkArte\Notifications::instance();
			$notifier->add(new \ElkArte\NotificationsTask(
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
}
