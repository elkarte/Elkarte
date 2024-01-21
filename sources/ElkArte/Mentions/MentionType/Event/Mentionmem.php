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

use ElkArte\DataValidator;
use ElkArte\Mentions\Mentioning;
use ElkArte\Mentions\MentionType\AbstractEventBoardAccess;
use ElkArte\Mentions\MentionType\CommonConfigTrait;
use ElkArte\Notifications\Notifications;
use ElkArte\Notifications\NotificationsTask;

/**
 * Class Mentionmem
 *
 * Handles the mentioning of members (@ member actions)
 */
class Mentionmem extends AbstractEventBoardAccess
{
	use CommonConfigTrait;

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

		return $methods[$controller] ?? array();
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
	 * Listener attached to the prepare_context event of the Display controller
	 * used to mark a mention as read.
	 *
	 * @param int $virtual_msg
	 */
	public function display_prepare_context($virtual_msg)
	{
		global $modSettings, $context;

		// Mark the mention as read if requested
		if (isset($_REQUEST['mentionread']) && !empty($virtual_msg))
		{
			$mentions = new Mentioning(database(), $this->user, new DataValidator(), $modSettings['enabled_mentions']);
			$mentions->markread((int) $_REQUEST['item']);
		}

		$context['mentions_enabled'] = true;

		$this->_setup_editor();
	}

	/**
	 * Takes care of setting up the editor javascript.
	 */
	protected function _setup_editor()
	{
		loadCSSFile('jquery.atwho.css');

		theme()->addInlineJavascript('
			document.addEventListener("DOMContentLoaded", function () {
				all_elk_mentions.forEach(elk_mention => {
					elk_mention.oMention = new elk_mentions(elk_mention.oOptions);
				});
			});
		');
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
	 * @param array $msgOptions
	 * @param bool $becomesApproved
	 * @param array $posterOptions
	 */
	public function post_after_save_post($msgOptions, $becomesApproved, $posterOptions)
	{
		if (!empty($this->_actually_mentioned))
		{
			$notifier = Notifications::instance();
			$notifier->add(new NotificationsTask(
				'mentionmem',
				$msgOptions['id'],
				$posterOptions['id'],
				array('id_members' => $this->_actually_mentioned, 'notifier_data' => $posterOptions, 'subject' => $msgOptions['subject'], 'status' => $becomesApproved
					? 'new'
					: 'unapproved')
			));
		}
	}
}
