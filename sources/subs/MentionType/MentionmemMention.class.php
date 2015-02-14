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
			'post' => array('prepare_context' => array(), 'before_save_post' => array(), 'after_save_post' => array('msgOptions', 'becomesApproved')),
			'display' => array('prepare_context' => array('virtual_msg')),
		);

		if (isset($methods[$controller]))
			return $methods[$controller];
		else
			return array();
	}

	public function display_prepare_context($virtual_msg)
	{
		global $options;

		// Mark the mention as read if requested
		if (isset($_REQUEST['mentionread']) && !empty($virtual_msg))
		{
			$mentions = new Mentions_Controller(new Event_Manager());
			$mentions->pre_dispatch();
			$mentions->setData(array(
				'id_mention' => $_REQUEST['item'],
				'mark' => $_REQUEST['mark'],
			));
			$mentions->action_markread();
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

	public function post_after_save_post($msgOptions, $becomesApproved)
	{
		if (!empty($this->_actually_mentioned))
		{
			$mentions = new Mentions_Controller(new Event_Manager());
			$mentions->pre_dispatch();
			$mentions->setData(array(
				'id_member' => $this->_actually_mentioned,
				'type' => 'mentionmem',
				'id_msg' => $msgOptions['id'],
				'status' => $becomesApproved ? 'new' : 'unapproved',
			));
			$mentions->action_add();
		}
	}
}
