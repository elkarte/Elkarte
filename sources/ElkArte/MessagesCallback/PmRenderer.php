<?php

/**
 * Part of the files dealing with preparing the content for display posts
 * via callbacks (Display, PM, Search).
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\MessagesCallback;

use ElkArte\MembersList;
use ElkArte\MessagesCallback\BodyParser\BodyParserInterface;
use ElkArte\ValuesContainer;

/**
 * PmRenderer
 *
 * Used by the \ElkArte\Controller\PersonalMessage to prepare both the subjects (for
 * the list of messages in the index) and the bodies of the PMs.
 */
class PmRenderer extends Renderer
{
	public const BEFORE_PREPARE_HOOK = 'integrate_before_prepare_pm_context';
	public const CONTEXT_HOOK = 'integrate_prepare_pm_context';

	/**
	 * Array of selected personal messages
	 *
	 * @var int[]
	 */
	protected $_temp_pm_selected = null;

	/**
	 * {@inheritdoc }
	 */
	public function __construct($request, $user, BodyParserInterface $bodyParser, ValuesContainer $opt = null)
	{
		parent::__construct($request, $user, $bodyParser, $opt);
		$this->_idx_mapper = new ValuesContainer([
			'id_msg' => 'id_pm',
			'id_member' => 'id_member_from',
			'name' => 'from_name',
			'time' => 'msgtime',
		]);

		$this->_temp_pm_selected = $_SESSION['pm_selected'] ?? [];
		$_SESSION['pm_selected'] = [];
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
	protected function _adjustGuestContext($member_context)
	{
		global $context;

		MembersList::loadGuest();
		parent::_adjustGuestContext($member_context);

		// Sometimes the forum sends messages itself (Warnings are an example)
		// in this case don't label it from a guest.
		if ($this->_this_message['from_name'] === $context['forum_name'])
		{
			$member_context['group'] = '';
		}
		$member_context['email'] = '';
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _adjustAllMembers($member_context)
	{
		global $context, $settings;

		$member_context['show_profile_buttons'] = (!empty($member_context['can_view_profile']) || (!empty($member_context['website']['url']) && !isset($context['disabled_fields']['website'])) || (in_array($member_context['show_email'], array('yes', 'yes_permission_override', 'no_through_forum'))) || $context['can_send_pm']);
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _buildOutputArray()
	{
		global $context, $modSettings;

		$id_pm = $this->_this_message['id_pm'];

		$output = parent::_buildOutputArray();
		$output += array(
			'recipients' => $this->_options->recipients[$id_pm],
			'number_recipients' => count($this->_options->recipients[$id_pm]['to']),
			'labels' => &$context['message_labels'][$id_pm],
			'fully_labeled' => count($context['message_labels'][$id_pm]) === count($context['labels']),
			'is_replied_to' => &$context['message_replied'][$id_pm],
			'is_unread' => &$context['message_unread'][$id_pm],
			'is_selected' => !empty($this->_temp_pm_selected) && in_array($id_pm, $this->_temp_pm_selected),
			'is_message_author' => $this->_this_message['id_member_from'] == $this->user->id,
			'can_report' => !empty($modSettings['enableReportPM']),
		);

		// Or give / take karma for a PM
		if (!empty($output['member']['karma']['allow']))
		{
			$output['member']['karma'] += array(
				'applaud_url' => getUrl('action', ['action' => 'karma', 'sa' => 'applaud', 'uid' => $output['member']['id'], 'f' => $context['folder'], 'start' => $context['start'], 'pm' => $output['id'], '{session_data}'] + ($context['current_label_id'] != -1 ? ['l' => $context['current_label_id']] : [])),
				'smite_url' => getUrl('action', ['action' => 'karma', 'sa' => 'smite', 'uid' => $output['member']['id'], 'f' => $context['folder'], 'start' => $context['start'], 'pm' => $output['id'], '{session_data}'] + ($context['current_label_id'] != -1 ? ['l' => $context['current_label_id']] : [])),
			);
		}

		// Build the PM buttons, like reply, report, etc.
		$output += $this->_buildPmButtons($output);

		return $output;
	}

	/**
	 * Generates a PM button array suitable for consumption by template_button_strip
	 *
	 * @param $output
	 * @return array
	 */
	protected function _buildPmButtons($output)
	{
		global $context, $txt;

		$pmButtons = [
			// Show reply buttons if you have the permission to send PMs.
			// Is there than more than one recipient you can reply to?
			'reply_all_button' => [
				'text' => 'reply_to_all',
				'url' => getUrl('action', ['action' => 'pm', 'sa' => 'send', 'f' => $context['folder'], 'pmsg' => $output['id'], 'quote' => '', 'u' => 'all']) . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : ''),
				'class' => 'reply_all_button',
				'icon' => 'comments',
				'enabled' => !$output['member']['is_guest'] && $output['number_recipients'] > 1 && $context['can_send_pm'],
			],
			// Reply, Quote
			'reply_button' => [
				'text' => 'reply',
				'url' => getUrl('action', ['action' => 'pm', 'sa' => 'send', 'f' => $context['folder'], 'pmsg' => $output['id'], 'u' => $output['member']['id']]) . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : ''),
				'class' => 'reply_button',
				'icon' => 'modify',
				'enabled' => !$output['member']['is_guest'] && $context['can_send_pm'],
			],
			'quote_button' => [
				'text' => 'quote',
				'url' => getUrl('action', ['action' => 'pm', 'sa' => 'send', 'f' => $context['folder'], 'pmsg' => $output['id'], 'quote' => '']) . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ($context['folder'] === 'sent' ? '' : ';u=' . $output['member']['id']),
				'class' => 'quote_button',
				'icon' => 'quote',
				'enabled' => !$output['member']['is_guest'] && $context['can_send_pm'],
			],
			// This is for "forwarding" - even if the member is gone.
			'reply_quote_button' => [
				'text' => 'reply_quote',
				'url' => getUrl('action', ['action' => 'pm', 'sa' => 'send', 'f' => $context['folder'], 'pmsg' => $output['id'], 'quote' => '']) . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : ''),
				'class' => 'reply_button',
				'icon' => 'modify',
				'enabled' => $output['member']['is_guest'] && $context['can_send_pm'],
			],
			// Remove is always an option
			'remove_button' => [
				'text' => 'delete',
				'url' => getUrl('action', ['action' => 'pm', 'sa' => 'pmactions', 'pm_actions[' . $output['id'] . ']' => 'delete', 'f' => $context['folder'], 'start' => $context['start'], '{session_data}']) . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : ''),
				'icon' => 'delete',
				'custom' => 'onclick="return confirm(\'' . addslashes($txt['remove_message']) . '?\');"',
			],
			// Maybe there is something...more :P (this is the more button)
			// *** Submenu
			//
			// Or mark it as unread
			'restore_button' => [
				'text' => 'pm_mark_unread',
				'url' => getUrl('action', ['action' => 'pm', 'sa' => 'markunread', 'l' => $context['current_label_id'], 'pmsg' => $output['id'], '{session_data}']),
				'icon' => 'recycle',
				'enabled' => empty($output['is_unread']) && $context['folder'] !== 'sent' && $output['member']['id'] != $this->user->id,
				'submenu' => true,
			],
			// Can they report this message
			'warn_button' => [
				'text' => 'pm_report_to_admin',
				'url' => getUrl('action', ['action' => 'pm', 'sa' => 'report', 'l' => $context['current_label_id'], 'pmsg' => $output['id'], '{session_data}']),
				'icon' => 'warn',
				'enabled' => !empty($output['can_report']) && $context['folder'] !== 'sent' && $output['member']['id'] != $this->user->id,
				'submenu' => true,
			],
			// Showing all then give a remove item checkbox
			'inline_mod_check' => [
				'id' => 'deletedisplay' . $output['id'],
				'class' => 'inline_mod_check',
				'enabled' => empty($context['display_mode']),
				'custom' => 'onclick="document.getElementById(\'deletelisting', $output['id'], '\').checked = this.checked;"',
				'checkbox' => 'always',
				'name' => 'pms',
				'value' => $output['id'],
			]
		];

		// Drop any non-enabled ones
		$pmButtons = array_filter($pmButtons, static function ($button) {
			return !isset($button['enabled']) || (bool) $button['enabled'] !== false;
		});

		return ['pmbuttons' => $pmButtons];
	}
}
