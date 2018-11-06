<?php

/**
 * Part of the files dealing with preparing the content for display posts
 * via callbacks (Display, PM, Search).
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\MessagesCallback;

use \ElkArte\MessagesCallback\BodyParser\BodyParserInterface;
use \ElkArte\ValuesContainer;

/**
 * PmRenderer
 *
 * Used by the \ElkArte\Controller\PersonalMessage to prepare both the subjects (for
 * the list of messages in the index) and the bodies of the PMs.
 */
class PmRenderer extends Renderer
{
	const BEFORE_PREPARE_HOOK = 'integrate_before_prepare_pm_context';
	const CONTEXT_HOOK = 'integrate_prepare_pm_context';

	/**
	 * Array of selected personal messages
	 * @var int[]
	 */
	protected $_temp_pm_selected = null;

	/**
	 * {@inheritdoc }
	 */
	public function __construct($request, BodyParserInterface $bodyParser, ValuesContainer $opt = null)
	{
		parent::__construct($request, $bodyParser, $opt);
		$this->_idx_mapper = new ValuesContainer([
			'id_msg' => 'id_pm',
			'id_member' => 'id_member_from',
			'name' => 'from_name',
			'time' => 'msgtime',
		]);

		$this->_temp_pm_selected = isset($_SESSION['pm_selected']) ? $_SESSION['pm_selected'] : array();
		$_SESSION['pm_selected'] = array();
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _setupPermissions()
	{
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _adjustGuestContext()
	{
		global $memberContext, $context;

		parent::_adjustGuestContext();

		// Sometimes the forum sends messages itself (Warnings are an example)
		// in this case don't label it from a guest.
		if ($this->_this_message['from_name'] === $context['forum_name'])
		{
			$memberContext[$this->_this_message['id_member_from']]['group'] = '';
		}
		$memberContext[$this->_this_message['id_member_from']]['email'] = '';
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _adjustAllMembers()
	{
		global $memberContext, $context, $settings;

		$memberContext[$this->_this_message['id_member_from']]['show_profile_buttons'] = $settings['show_profile_buttons'] && (!empty($memberContext[$this->_this_message['id_member_from']]['can_view_profile']) || (!empty($memberContext[$this->_this_message['id_member_from']]['website']['url']) && !isset($context['disabled_fields']['website'])) || (in_array($memberContext[$this->_this_message['id_member_from']]['show_email'], array('yes', 'yes_permission_override', 'no_through_forum'))) || $context['can_send_pm']);
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _buildOutputArray()
	{
		global $recipients, $context, $user_info, $modSettings, $scripturl, $txt;

		$id_pm = $this->_this_message['id_pm'];

		$output = parent::_buildOutputArray();
		$output += array(
			'recipients' => &$recipients[$id_pm],
			'number_recipients' => count($recipients[$id_pm]['to']),
			'labels' => &$context['message_labels'][$id_pm],
			'fully_labeled' => count($context['message_labels'][$id_pm]) == count($context['labels']),
			'is_replied_to' => &$context['message_replied'][$id_pm],
			'is_unread' => &$context['message_unread'][$id_pm],
			'is_selected' => !empty($this->_temp_pm_selected) && in_array($id_pm, $this->_temp_pm_selected),
			'is_message_author' => $this->_this_message['id_member_from'] == $user_info['id'],
			'can_report' => !empty($modSettings['enableReportPM']),
		);

		$context['additional_pm_drop_buttons'] = array();

		// Can they report this message
		if (!empty($output['can_report']) && $context['folder'] !== 'sent' && $output['member']['id'] != $user_info['id'])
		{
			$context['additional_pm_drop_buttons']['warn_button'] = array(
				'href' => $scripturl . '?action=pm;sa=report;l=' . $context['current_label_id'] . ';pmsg=' . $output['id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'text' => $txt['pm_report_to_admin']
			);
		}

		// Or mark it as unread
		if (empty($output['is_unread']) && $context['folder'] !== 'sent' && $output['member']['id'] != $user_info['id'])
		{
			$context['additional_pm_drop_buttons']['restore_button'] = array(
				'href' => $scripturl . '?action=pm;sa=markunread;l=' . $context['current_label_id'] . ';pmsg=' . $output['id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'text' => $txt['pm_mark_unread']
			);
		}

		// Or give / take karma for a PM
		if (!empty($output['member']['karma']['allow']))
		{
			$output['member']['karma'] += array(
				'applaud_url' => $scripturl . '?action=karma;sa=applaud;uid=' . $output['member']['id'] . ';f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ';pm=' . $output['id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'smite_url' => $scripturl . '?action=karma;sa=smite;uid=' . $output['member']['id'] . ';f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ';pm=' . $output['id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			);
		}

		return $output;
	}
}
