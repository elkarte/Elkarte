<?php

/**
 * This file contains the post integration of mentions.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

class Module_Mention_Post
{
	protected static $_enabled = false;
	protected $_actually_mentioned = array();

	public static function hooks()
	{
		global $context;

		// Posting an event?
		self::$_enabled = !empty($modSettings['mentions_enabled']);

		if (self::$_enabled)
			return array(
				array('prepare_post', array('Module_Mention_Post', 'prepare_post'), array('events')),
				array('prepare_context', array('Module_Mention_Post', 'prepare_context'), array()),
				array('after_save_post', array('Module_Mention_Post', 'after_save_post'), array()),
				array('save_post', array('Module_Mention_Post', 'save_post'), array('msgOptions', 'becomesApproved')),
			);
		else
			return array();
	}

	public function prepare_post($events)
	{
		global $modSettings;

		$mentions = explode(',', $modSettings['enabled_mentions']);

		foreach ($mentions as $mention)
		{
			$events->register('prepare_post', array('prepare_post', array(ucfirst($mention) . '_Mention', 'prepare_post', 0)));
		}
	}

	public function prepare_context()
	{
		global $context;

		$context['mentions_enabled'] = true;
		loadCSSFile('jquery.atwho.css');

		addInlineJavascript('
		$(document).ready(function () {
			for (var i = 0, count = all_elk_mentions.length; i < count; i++)
				all_elk_mentions[i].oMention = new elk_mentions(all_elk_mentions[i].oOptions);
		});');
	}

	public function after_save_post()
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

	public function save_post($msgOptions, $becomesApproved)
	{
		global $user_info, $modSettings;

		if (!empty($this->_actually_mentioned))
		{
			$mentions = new Mentions_Controller();
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
