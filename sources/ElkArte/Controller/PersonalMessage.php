<?php

/**
 * This file is mainly meant for controlling the actions related to personal
 * messages. It allows viewing, sending, deleting, and marking.
 * For compatibility reasons, they are often called "instant messages".
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\Errors\ErrorContext;
use ElkArte\Exceptions\ControllerRedirectException;

/**
 * It allows viewing, sending, deleting, and marking personal messages
 *
 * @package PersonalMessage
 */
class PersonalMessage extends \ElkArte\AbstractController
{
	/**
	 * $_search_params will carry all settings that differ from the default
	 * search parameters. That way, the URLs involved in a search page will
	 * be kept as short as possible.
	 * @var array
	 */
	private $_search_params = array();

	/**
	 * $_searchq_parameters will carry all the values needed by S_search_params
	 * @var array
	 */
	private $_searchq_parameters = array();

	/**
	 * This method is executed before any other in this file (when the class is
	 * loaded by the dispatcher).
	 *
	 * What it does:
	 *
	 * - It sets the context, load templates and language file(s), as necessary
	 * for the function that will be called.
	 */
	public function pre_dispatch()
	{
		global $txt, $scripturl, $context, $user_info, $user_settings, $modSettings;

		// No guests!
		is_not_guest();

		// You're not supposed to be here at all, if you can't even read PMs.
		isAllowedTo('pm_read');

		// This file contains the our PM functions such as mark, send, delete
		require_once(SUBSDIR . '/PersonalMessage.subs.php');

		// Templates, language, javascripts
		theme()->getTemplates()->loadLanguageFile('PersonalMessage');
		loadJavascriptFile(array('PersonalMessage.js', 'suggest.js'));

		if (!isset($this->_req->query->xml))
		{
			theme()->getTemplates()->load('PersonalMessage');
		}

		$this->_events->trigger('pre_dispatch', array('xml' => isset($this->_req->query->xml)));

		// Load up the members maximum message capacity.
		$this->_loadMessageLimit();

		// A previous message was sent successfully? show a small indication.
		if ($this->_req->getQuery('done') === 'sent')
		{
			$context['pm_sent'] = true;
		}

		// Load the label counts data.
		if ($user_settings['new_pm'] || !\ElkArte\Cache\Cache::instance()->getVar($context['labels'], 'labelCounts:' . $user_info['id'], 720))
		{
			$this->_loadLabels();

			// Get the message count for each label
			$context['labels'] = loadPMLabels($context['labels']);
		}

		// Now we have the labels, and assuming we have unsorted mail, apply our rules!
		if ($user_settings['new_pm'])
		{
			// Apply our rules to the new PM's
			applyRules();

			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($user_info['id'], array('new_pm' => 0));

			// Turn the new PM's status off, for the popup alert, since they have entered the PM area
			toggleNewPM($user_info['id']);
		}

		// This determines if we have more labels than just the standard inbox.
		$context['currently_using_labels'] = count($context['labels']) > 1 ? 1 : 0;

		// Some stuff for the labels...
		$context['current_label_id'] = isset($this->_req->query->l) && isset($context['labels'][(int) $this->_req->query->l]) ? (int) $this->_req->query->l : -1;
		$context['current_label'] = &$context['labels'][(int) $context['current_label_id']]['name'];
		$context['folder'] = !isset($this->_req->query->f) || $this->_req->query->f !== 'sent' ? 'inbox' : 'sent';

		// This is convenient.  Do you know how annoying it is to do this every time?!
		$context['current_label_redirect'] = 'action=pm;f=' . $context['folder'] . (isset($this->_req->query->start) ? ';start=' . $this->_req->query->start : '') . (isset($this->_req->query->l) ? ';l=' . $this->_req->query->l : '');
		$context['can_issue_warning'] = in_array('w', $context['admin_features']) && allowedTo('issue_warning') && !empty($modSettings['warning_enable']);

		// Build the linktree for all the actions...
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm',
			'name' => $txt['personal_messages']
		);

		// Preferences...
		$context['display_mode'] = $user_settings['pm_prefs'] & 3;
	}

	/**
	 * Load a members message limit and prepares the limit bar
	 */
	private function _loadMessageLimit()
	{
		global $context, $txt, $user_info;

		$context['message_limit'] = loadMessageLimit();

		// Prepare the context for the capacity bar.
		if (!empty($context['message_limit']))
		{
			$bar = ($user_info['messages'] * 100) / $context['message_limit'];

			$context['limit_bar'] = array(
				'messages' => $user_info['messages'],
				'allowed' => $context['message_limit'],
				'percent' => $bar,
				'bar' => min(100, (int) $bar),
				'text' => sprintf($txt['pm_currently_using'], $user_info['messages'], round($bar, 1)),
			);
		}
	}

	/**
	 * Loads the user defined label's for use in the template etc.
	 */
	private function _loadLabels()
	{
		global $context, $txt, $user_settings;

		$context['labels'] = $user_settings['message_labels'] === '' ? array() : explode(',', $user_settings['message_labels']);

		foreach ($context['labels'] as $id_label => $label_name)
		{
			$context['labels'][(int) $id_label] = array(
				'id' => $id_label,
				'name' => trim($label_name),
				'messages' => 0,
				'unread_messages' => 0,
			);
		}

		// The default inbox is always available
		$context['labels'][-1] = array(
			'id' => -1,
			'name' => $txt['pm_msg_label_inbox'],
			'messages' => 0,
			'unread_messages' => 0,
		);
	}

	/**
	 * This is the main function of personal messages, called before the action handler.
	 *
	 * What it does:
	 *
	 * - PersonalMessages is a menu-based controller.
	 * - It sets up the menu.
	 * - Calls from the menu the appropriate method/function for the current area.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context;

		// Finally all the things we know how to do
		$subActions = array(
			'manlabels' => array($this, 'action_manlabels', 'permission' => 'pm_read'),
			'manrules' => array($this, 'action_manrules', 'permission' => 'pm_read'),
			'markunread' => array($this, 'action_markunread', 'permission' => 'pm_read'),
			'pmactions' => array($this, 'action_pmactions', 'permission' => 'pm_read'),
			'prune' => array($this, 'action_prune', 'permission' => 'pm_read'),
			'removeall' => array($this, 'action_removeall', 'permission' => 'pm_read'),
			'removeall2' => array($this, 'action_removeall2', 'permission' => 'pm_read'),
			'report' => array($this, 'action_report', 'permission' => 'pm_read'),
			'search' => array($this, 'action_search', 'permission' => 'pm_read'),
			'search2' => array($this, 'action_search2', 'permission' => 'pm_read'),
			'send' => array($this, 'action_send', 'permission' => 'pm_read'),
			'send2' => array($this, 'action_send2', 'permission' => 'pm_read'),
			'settings' => array($this, 'action_settings', 'permission' => 'pm_read'),
			'inbox' => array($this, 'action_folder', 'permission' => 'pm_read'),
		);

		// Set up our action array
		$action = new \ElkArte\Action('pm_index');

		// Known action, go to it, otherwise the inbox for you
		$subAction = $action->initialize($subActions, 'inbox');

		// Set the right index bar for the action
		if ($subAction === 'inbox')
		{
			$this->_messageIndexBar($context['current_label_id'] == -1 ? $context['folder'] : 'label' . $context['current_label_id']);
		}
		elseif (!isset($this->_req->query->xml))
		{
			$this->_messageIndexBar($subAction);
		}

		// And off we go!
		$action->dispatch($subAction);
	}

	/**
	 * A menu to easily access different areas of the PM section
	 *
	 * @param string $area
	 *
	 * @throws \ElkArte\Exceptions\Exception no_access
	 */
	private function _messageIndexBar($area)
	{
		global $txt, $context, $scripturl, $user_info;

		require_once(SUBSDIR . '/Menu.subs.php');

		$pm_areas = array(
			'folders' => array(
				'title' => $txt['pm_messages'],
				'counter' => 'unread_messages',
				'areas' => array(
					'inbox' => array(
						'label' => $txt['inbox'],
						'custom_url' => $scripturl . '?action=pm',
						'counter' => 'unread_messages',
					),
					'send' => array(
						'label' => $txt['new_message'],
						'custom_url' => $scripturl . '?action=pm;sa=send',
						'permission' => 'pm_send',
					),
					'sent' => array(
						'label' => $txt['sent_items'],
						'custom_url' => $scripturl . '?action=pm;f=sent',
					),
				),
			),
			'labels' => array(
				'title' => $txt['pm_labels'],
				'counter' => 'labels_unread_total',
				'areas' => array(),
			),
			'actions' => array(
				'title' => $txt['pm_actions'],
				'areas' => array(
					'search' => array(
						'label' => $txt['pm_search_bar_title'],
						'custom_url' => $scripturl . '?action=pm;sa=search',
					),
					'prune' => array(
						'label' => $txt['pm_prune'],
						'custom_url' => $scripturl . '?action=pm;sa=prune'
					),
				),
			),
			'pref' => array(
				'title' => $txt['pm_preferences'],
				'areas' => array(
					'manlabels' => array(
						'label' => $txt['pm_manage_labels'],
						'custom_url' => $scripturl . '?action=pm;sa=manlabels',
					),
					'manrules' => array(
						'label' => $txt['pm_manage_rules'],
						'custom_url' => $scripturl . '?action=pm;sa=manrules',
					),
					'settings' => array(
						'label' => $txt['pm_settings'],
						'custom_url' => $scripturl . '?action=pm;sa=settings',
					),
				),
			),
		);

		// Handle labels.
		$label_counters = array('unread_messages' => $context['labels'][-1]['unread_messages']);
		if (empty($context['currently_using_labels']))
		{
			unset($pm_areas['labels']);
		}
		else
		{
			// Note we send labels by id as it will have less problems in the query string.
			$label_counters['labels_unread_total'] = 0;
			foreach ($context['labels'] as $label)
			{
				if ($label['id'] == -1)
				{
					continue;
				}

				// Count the amount of unread items in labels.
				$label_counters['labels_unread_total'] += $label['unread_messages'];

				// Add the label to the menu.
				$pm_areas['labels']['areas']['label' . $label['id']] = array(
					'label' => $label['name'],
					'custom_url' => $scripturl . '?action=pm;l=' . $label['id'],
					'counter' => 'label' . $label['id'],
					'messages' => $label['messages'],
				);

				$label_counters['label' . $label['id']] = $label['unread_messages'];
			}
		}

		// Do we have a limit on the amount of messages we can keep?
		if (!empty($context['message_limit']))
		{
			$bar = round(($user_info['messages'] * 100) / $context['message_limit'], 1);

			$context['limit_bar'] = array(
				'messages' => $user_info['messages'],
				'allowed' => $context['message_limit'],
				'percent' => $bar,
				'bar' => $bar > 100 ? 100 : (int) $bar,
				'text' => sprintf($txt['pm_currently_using'], $user_info['messages'], $bar)
			);
		}

		// Set a few options for the menu.
		$menuOptions = array(
			'current_area' => $area,
			'hook' => 'pm',
			'disable_url_session_check' => true,
			'counters' => !empty($label_counters) ? $label_counters : 0,
			'default_include_dir' => CONTROLLERDIR,
		);

		// Actually create the menu!
		$pm_include_data = createMenu($pm_areas, $menuOptions);
		unset($pm_areas);

		// No menu means no access.
		if (!$pm_include_data && (!$user_info['is_guest'] || validateSession() !== true))
		{
			throw new \ElkArte\Exceptions\Exception('no_access', false);
		}

		// Make a note of the Unique ID for this menu.
		$context['pm_menu_id'] = $context['max_menu_id'];
		$context['pm_menu_name'] = 'menu_data_' . $context['pm_menu_id'];

		// Set the selected item.
		$context['menu_item_selected'] = $pm_include_data['current_area'];

		// Grab the file needed for this action
		if (isset($pm_include_data['file']))
		{
			require_once($pm_include_data['file']);
		}

		// Set the template for this area and add the profile layer.
		if (!isset($this->_req->query->xml))
		{
			$template_layers = theme()->getLayers();
			$template_layers->add('pm');
		}
	}

	/**
	 * Display a folder, ie. inbox/sent etc.
	 *
	 * @uses folder sub template
	 * @uses subject_list, pm template layers
	 */
	public function action_folder()
	{
		global $txt, $scripturl, $modSettings, $context, $subjects_request;
		global $messages_request, $user_info, $recipients, $options, $user_settings;

		// Changing view?
		if (isset($this->_req->query->view))
		{
			$context['display_mode'] = $context['display_mode'] > 1 ? 0 : $context['display_mode'] + 1;
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($user_info['id'], array('pm_prefs' => ($user_settings['pm_prefs'] & 252) | $context['display_mode']));
		}

		// Make sure the starting location is valid.
		if (isset($this->_req->query->start) && $this->_req->query->start !== 'new')
		{
			$start = (int) $this->_req->query->start;
		}
		elseif (!isset($this->_req->query->start) && !empty($options['view_newest_pm_first']))
		{
			$start = 0;
		}
		else
		{
			$start = 'new';
		}

		// Set up some basic template stuff.
		$context['from_or_to'] = $context['folder'] !== 'sent' ? 'from' : 'to';
		$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

		// Set the template layers we need
		$template_layers = theme()->getLayers();
		$template_layers->addAfter('subject_list', 'pm');

		$labelQuery = $context['folder'] !== 'sent' ? '
				AND FIND_IN_SET(' . $context['current_label_id'] . ', pmr.labels) != 0' : '';

		// They didn't pick a sort, so we use the forum default.
		$sort_by = !isset($this->_req->query->sort) ? 'date' : $this->_req->query->sort;
		$descending = isset($this->_req->query->desc);

		// Set our sort by query
		switch ($sort_by)
		{
			case 'date':
				$sort_by_query = 'pm.id_pm';
				if (!empty($options['view_newest_pm_first']) && !isset($this->_req->query->desc) && !isset($this->_req->query->asc))
				{
					$descending = true;
				}
				break;
			case 'name':
				$sort_by_query = 'COALESCE(mem.real_name, \'\')';
				break;
			case 'subject':
				$sort_by_query = 'pm.subject';
				break;
			default:
				$sort_by_query = 'pm.id_pm';
		}

		// Set the text to resemble the current folder.
		$pmbox = $context['folder'] !== 'sent' ? $txt['inbox'] : $txt['sent_items'];
		$txt['delete_all'] = str_replace('PMBOX', $pmbox, $txt['delete_all']);

		// Now, build the link tree!
		if ($context['current_label_id'] === -1)
		{
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=pm;f=' . $context['folder'],
				'name' => $pmbox
			);
		}

		// Build it further if we also have a label.
		if ($context['current_label_id'] !== -1)
		{
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=pm;f=' . $context['folder'] . ';l=' . $context['current_label_id'],
				'name' => $txt['pm_current_label'] . ': ' . $context['current_label']
			);
		}

		// Figure out how many messages there are.
		$max_messages = getPMCount(false, null, $labelQuery);

		// Only show the button if there are messages to delete.
		$context['show_delete'] = $max_messages > 0;

		// Start on the last page.
		if (!is_numeric($start) || $start >= $max_messages)
		{
			$start = ($max_messages - 1) - (($max_messages - 1) % $modSettings['defaultMaxMessages']);
		}
		elseif ($start < 0)
		{
			$start = 0;
		}

		// ... but wait - what if we want to start from a specific message?
		if (isset($this->_req->query->pmid))
		{
			$pmID = (int) $this->_req->query->pmid;

			// Make sure you have access to this PM.
			if (!isAccessiblePM($pmID, $context['folder'] === 'sent' ? 'outbox' : 'inbox'))
			{
				throw new \ElkArte\Exceptions\Exception('no_access', false);
			}

			$context['current_pm'] = $pmID;

			// With only one page of PM's we're gonna want page 1.
			if ($max_messages <= $modSettings['defaultMaxMessages'])
			{
				$start = 0;
			}
			// If we pass kstart we assume we're in the right place.
			elseif (!isset($this->_req->query->kstart))
			{
				$start = getPMCount($descending, $pmID, $labelQuery);

				// To stop the page index's being abnormal, start the page on the page the message
				// would normally be located on...
				$start = $modSettings['defaultMaxMessages'] * (int) ($start / $modSettings['defaultMaxMessages']);
			}
		}

		// Sanitize and validate pmsg variable if set.
		if (isset($this->_req->query->pmsg))
		{
			$pmsg = (int) $this->_req->query->pmsg;

			if (!isAccessiblePM($pmsg, $context['folder'] === 'sent' ? 'outbox' : 'inbox'))
			{
				throw new \ElkArte\Exceptions\Exception('no_access', false);
			}
		}

		// Determine the navigation context
		$context['links'] += array(
			'prev' => $start >= $modSettings['defaultMaxMessages'] ? $scripturl . '?action=pm;start=' . ($start - $modSettings['defaultMaxMessages']) : '',
			'next' => $start + $modSettings['defaultMaxMessages'] < $max_messages ? $scripturl . '?action=pm;start=' . ($start + $modSettings['defaultMaxMessages']) : '',
		);
		$context['page_info'] = array(
			'current_page' => $start / $modSettings['defaultMaxMessages'] + 1,
			'num_pages' => floor(($max_messages - 1) / $modSettings['defaultMaxMessages']) + 1
		);

		// We now know what they want, so lets fetch those PM's
		list ($pms, $posters, $recipients, $lastData) = loadPMs(array(
			'sort_by_query' => $sort_by_query,
			'display_mode' => $context['display_mode'],
			'sort_by' => $sort_by,
			'label_query' => $labelQuery,
			'pmsg' => isset($pmsg) ? (int) $pmsg : 0,
			'descending' => $descending,
			'start' => $start,
			'limit' => $modSettings['defaultMaxMessages'],
			'folder' => $context['folder'],
			'pmid' => isset($pmID) ? $pmID : 0,
		), $user_info['id']);

		// Make sure that we have been given a correct head pm id if we are in conversation mode
		if ($context['display_mode'] == 2 && !empty($pmID) && $pmID != $lastData['id'])
		{
			throw new \ElkArte\Exceptions\Exception('no_access', false);
		}

		// If loadPMs returned results, lets show the pm subject list
		if (!empty($pms))
		{
			// Tell the template if no pm has specifically been selected
			if (empty($pmID))
			{
				$context['current_pm'] = 0;
			}

			// This is a list of the pm's that are used for "show all" display.
			if ($context['display_mode'] == 0)
			{
				$display_pms = $pms;
			}
			// Just use the last pm the user received to start things off
			else
			{
				$display_pms = array($lastData['id']);
			}

			// At this point we know the main id_pm's. But if we are looking at conversations we need
			// the PMs that make up the conversation
			if ($context['display_mode'] == 2)
			{
				list($display_pms, $posters) = loadConversationList($lastData['head'], $recipients, $context['folder']);

				// Conversation list may expose additional PM's being displayed
				$all_pms = array_unique(array_merge($pms, $display_pms));

				// See if any of these 'listing' PM's are in a conversation thread that has unread entries
				$context['conversation_unread'] = loadConversationUnreadStatus($all_pms);
			}
			// This is pretty much EVERY pm!
			else
			{
				$all_pms = array_unique(array_merge($pms, $display_pms));
			}

			// Get recipients (don't include bcc-recipients for your inbox, you're not supposed to know :P).
			list($context['message_labels'], $context['message_replied'], $context['message_unread']) = loadPMRecipientInfo($all_pms, $recipients, $context['folder']);

			// Make sure we don't load any unnecessary data for one at a time mode
			if ($context['display_mode'] == 1)
			{
				foreach ($posters as $pm_key => $sender)
				{
					if (!in_array($pm_key, $display_pms))
					{
						unset($posters[$pm_key]);
					}
				}
			}

			// Load some information about the message sender
			$posters = array_unique($posters);
			if (!empty($posters))
			{
				loadMemberData($posters);
			}

			// If we're on grouped/restricted view get a restricted list of messages.
			if ($context['display_mode'] != 0)
			{
				// Get the order right.
				$orderBy = array();
				foreach (array_reverse($pms) as $pm)
					$orderBy[] = 'pm.id_pm = ' . $pm;

				// Separate query for these bits, the callback will use it as required
				$subjects_request = loadPMSubjectRequest($pms, $orderBy);
			}

			// Execute the load message query if a message has been chosen and let
			// the callback fetch the results.  Otherwise just show the pm selection list
			if (empty($pmsg) && empty($pmID) && $context['display_mode'] != 0)
			{
				$messages_request = false;
			}
			else
			{
				$messages_request = loadPMMessageRequest($display_pms, $sort_by_query, $sort_by, $descending, $context['display_mode'], $context['folder']);
			}
		}
		else
		{
			$messages_request = false;
		}

		$bodyParser = new \ElkArte\MessagesCallback\BodyParser\Normal(array(), false);
		$renderer = new \ElkArte\MessagesCallback\PmRenderer($messages_request, $bodyParser);
		$srenderer = new \ElkArte\MessagesCallback\PmRenderer($subjects_request, $bodyParser);

		$context['get_pmessage'] = array($renderer, 'getContext');
		$context['get_psubject'] = array($srenderer, 'getContext');

		$context['topic_starter_id'] = 0;
		// Prepare some items for the template
		$context['can_send_pm'] = allowedTo('pm_send');
		$context['can_send_email'] = allowedTo('send_email_to_members');
		$context['sub_template'] = 'folder';
		$context['page_title'] = $txt['pm_inbox'];
		$context['sort_direction'] = $descending ? 'down' : 'up';
		$context['sort_by'] = $sort_by;

		if ($messages_request->hasResults())
		{
			// Auto video embedding enabled, someone may have a link in a PM
			if (!empty($modSettings['enableVideoEmbeding']))
			{
				theme()->addInlineJavascript('
					$(function() {
						$().linkifyvideo(oEmbedtext);
					});', true
				);
			}

			if (!empty($context['show_delete']))
			{
				theme()->getLayers()->addEnd('pm_pages_and_buttons');
			}
		}

		// Set up the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=pm;f=' . $context['folder'] . (isset($this->_req->query->l) ? ';l=' . (int) $this->_req->query->l : '') . ';sort=' . $context['sort_by'] . ($descending ? ';desc' : ''), $start, $max_messages, $modSettings['defaultMaxMessages']);
		$context['start'] = $start;

		$context['pm_form_url'] = $scripturl . '?action=pm;sa=pmactions;' . ($context['display_mode'] == 2 ? 'conversation;' : '') . 'f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '');

		// Finally mark the relevant messages as read.
		if ($context['folder'] !== 'sent' && !empty($context['labels'][(int) $context['current_label_id']]['unread_messages']))
		{
			// If the display mode is "old sk00l" do them all...
			if ($context['display_mode'] == 0)
			{
				markMessages(null, $context['current_label_id']);
			}
			// Otherwise do just the currently displayed ones!
			elseif (!empty($context['current_pm']))
			{
				markMessages($display_pms, $context['current_label_id']);
			}
		}

		// Build the conversation button array.
		if ($context['display_mode'] === 2 && !empty($context['current_pm']))
		{
			$context['conversation_buttons'] = array(
				'delete' => array(
					'text' => 'delete_conversation',
					'image' => 'delete.png',
					'lang' => true,
					'url' => $scripturl . '?action=pm;sa=pmactions;pm_actions%5B' . $context['current_pm'] . '%5D=delete;conversation;f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ';' . $context['session_var'] . '=' . $context['session_id'],
					'custom' => 'onclick="return confirm(\'' . addslashes($txt['remove_message']) . '?\');"'
				),
			);

			// Allow mods to add additional buttons here
			call_integration_hook('integrate_conversation_buttons');
		}
	}

	/**
	 * Send a new personal message?
	 */
	public function action_send()
	{
		global $txt, $scripturl, $modSettings, $context, $user_info;

		// Load in some text and template dependencies
		theme()->getTemplates()->loadLanguageFile('PersonalMessage');
		theme()->getTemplates()->load('PersonalMessage');

		// Set the template we will use
		$context['sub_template'] = 'send';

		// Extract out the spam settings - cause it's neat.
		list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

		// Set up some items for the template
		$context['page_title'] = $txt['send_message'];
		$context['reply'] = isset($this->_req->query->pmsg) || isset($this->_req->query->quote);

		// Check whether we've gone over the limit of messages we can send per hour.
		if (!empty($modSettings['pm_posts_per_hour']) && !allowedTo(array('admin_forum', 'moderate_forum', 'send_mail')) && $user_info['mod_cache']['bq'] === '0=1' && $user_info['mod_cache']['gq'] === '0=1')
		{
			// How many messages have they sent this last hour?
			$pmCount = pmCount($user_info['id'], 3600);

			if (!empty($pmCount) && $pmCount >= $modSettings['pm_posts_per_hour'])
			{
				throw new \ElkArte\Exceptions\Exception('pm_too_many_per_hour', true, array($modSettings['pm_posts_per_hour']));
			}
		}

		try
		{
			$this->_events->trigger('before_set_context', array('pmsg' => isset($this->_req->query->pmsg) ? $this->_req->query->pmsg : (isset($this->_req->query->quote) ? $this->_req->query->quote : 0)));
		}
		catch (\ElkArte\Exceptions\PmErrorException $e)
		{
			return $this->messagePostError($e->namedRecipientList, $e->recipientList, $e->msgOptions);
		}

		// Quoting / Replying to a message?
		if (!empty($this->_req->query->pmsg))
		{
			$pmsg = $this->_req->getQuery('pmsg', 'intval');

			// Make sure this is accessible (not deleted)
			if (!isAccessiblePM($pmsg))
			{
				throw new \ElkArte\Exceptions\Exception('no_access', false);
			}

			// Validate that this is one has been received?
			$isReceived = checkPMReceived($pmsg);

			// Get the quoted message (and make sure you're allowed to see this quote!).
			$row_quoted = loadPMQuote($pmsg, $isReceived);
			if ($row_quoted === false)
			{
				throw new \ElkArte\Exceptions\Exception('pm_not_yours', false);
			}

			// Censor the message.
			$row_quoted['subject'] = censor($row_quoted['subject']);
			$row_quoted['body'] = censor($row_quoted['body']);

			// Lets make sure we mark this one as read
			markMessages($pmsg);

			// Figure out which flavor or 'Re: ' to use
			$context['response_prefix'] = response_prefix();

			$form_subject = $row_quoted['subject'];

			// Add 'Re: ' to it....
			if ($context['reply'] && trim($context['response_prefix']) != '' && \ElkArte\Util::strpos($form_subject, trim($context['response_prefix'])) !== 0)
			{
				$form_subject = $context['response_prefix'] . $form_subject;
			}

			// If quoting, lets clean up some things and set the quote header for the pm body
			if (isset($this->_req->query->quote))
			{
				// Remove any nested quotes and <br />...
				$form_message = preg_replace('~<br ?/?' . '>~i', "\n", $row_quoted['body']);
				$form_message = removeNestedQuotes($form_message);

				if (empty($row_quoted['id_member']))
				{
					$form_message = '[quote author=&quot;' . $row_quoted['real_name'] . '&quot;]' . "\n" . $form_message . "\n" . '[/quote]';
				}
				else
				{
					$form_message = '[quote author=' . $row_quoted['real_name'] . ' link=action=profile;u=' . $row_quoted['id_member'] . ' date=' . $row_quoted['msgtime'] . ']' . "\n" . $form_message . "\n" . '[/quote]';
				}
			}
			else
			{
				$form_message = '';
			}

			// Do the BBC thang on the message.
			$bbc_parser = \BBC\ParserWrapper::instance();
			$row_quoted['body'] = $bbc_parser->parsePM($row_quoted['body']);

			// Set up the quoted message array.
			$context['quoted_message'] = array(
				'id' => $row_quoted['id_pm'],
				'pm_head' => $row_quoted['pm_head'],
				'member' => array(
					'name' => $row_quoted['real_name'],
					'username' => $row_quoted['member_name'],
					'id' => $row_quoted['id_member'],
					'href' => !empty($row_quoted['id_member']) ? $scripturl . '?action=profile;u=' . $row_quoted['id_member'] : '',
					'link' => !empty($row_quoted['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row_quoted['id_member'] . '">' . $row_quoted['real_name'] . '</a>' : $row_quoted['real_name'],
				),
				'subject' => $row_quoted['subject'],
				'time' => standardTime($row_quoted['msgtime']),
				'html_time' => htmlTime($row_quoted['msgtime']),
				'timestamp' => forum_time(true, $row_quoted['msgtime']),
				'body' => $row_quoted['body']
			);
		}
		// A new message it is then
		else
		{
			$context['quoted_message'] = false;
			$form_subject = '';
			$form_message = '';
		}

		// Start of like we don't know where this is going
		$context['recipients'] = array(
			'to' => array(),
			'bcc' => array(),
		);

		// Sending by ID?  Replying to all?  Fetch the real_name(s).
		if (isset($this->_req->query->u))
		{
			// If the user is replying to all, get all the other members this was sent to..
			if ($this->_req->query->u === 'all' && isset($row_quoted))
			{
				// Firstly, to reply to all we clearly already have $row_quoted - so have the original member from.
				if ($row_quoted['id_member'] != $user_info['id'])
				{
					$context['recipients']['to'][] = array(
						'id' => $row_quoted['id_member'],
						'name' => htmlspecialchars($row_quoted['real_name'], ENT_COMPAT, 'UTF-8'),
					);
				}

				// Now to get all the others.
				$context['recipients']['to'] = array_merge($context['recipients']['to'], isset($pmsg) ? loadPMRecipientsAll($pmsg) : array());
			}
			else
			{
				$users = array_map('intval', explode(',', $this->_req->query->u));
				$users = array_unique($users);

				// For all the member's this is going to, get their display name.
				require_once(SUBSDIR . '/Members.subs.php');
				$result = getBasicMemberData($users);

				foreach ($result as $row)
				{
					$context['recipients']['to'][] = array(
						'id' => $row['id_member'],
						'name' => $row['real_name'],
					);
				}
			}

			// Get a literal name list in case the user has JavaScript disabled.
			$names = array();
			foreach ($context['recipients']['to'] as $to)
				$names[] = $to['name'];
			$context['to_value'] = empty($names) ? '' : '&quot;' . implode('&quot;, &quot;', $names) . '&quot;';
		}
		else
		{
			$context['to_value'] = '';
		}

		// Set the defaults...
		$context['subject'] = $form_subject;
		$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_message);

		// And build the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=send',
			'name' => $txt['new_message']
		);

		// Needed for the editor.
		require_once(SUBSDIR . '/Editor.subs.php');

		// Now create the editor.
		$editorOptions = array(
			'id' => 'message',
			'value' => $context['message'],
			'height' => '250px',
			'width' => '100%',
			'labels' => array(
				'post_button' => $txt['send_message'],
			),
			'preview_type' => 2,
		);

		// Trigger the prepare_send_context PM event
		$this->_events->trigger('prepare_send_context', array('pmsg' => isset($this->_req->query->pmsg) ? $this->_req->query->pmsg : (isset($this->_req->query->quote) ? $this->_req->query->quote : 0), 'editorOptions' => &$editorOptions, 'recipientList' => &$context['recipients']));

		create_control_richedit($editorOptions);

		// No one is bcc'ed just yet
		$context['bcc_value'] = '';

		// Register this form and get a sequence number in $context.
		checkSubmitOnce('register');
	}

	/**
	 * Send a personal message.
	 */
	public function action_send2()
	{
		global $txt, $context, $user_info, $modSettings;

		// All the helpers we need
		require_once(SUBSDIR . '/Auth.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		theme()->getTemplates()->loadLanguageFile('PersonalMessage', '', false);

		// Extract out the spam settings - it saves database space!
		list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

		// Initialize the errors we're about to make.
		$post_errors = ErrorContext::context('pm', 1);

		// Check whether we've gone over the limit of messages we can send per hour - fatal error if fails!
		if (!empty($modSettings['pm_posts_per_hour'])
			&& !allowedTo(array('admin_forum', 'moderate_forum', 'send_mail'))
			&& $user_info['mod_cache']['bq'] === '0=1'
			&& $user_info['mod_cache']['gq'] === '0=1'
		)
		{
			// How many have they sent this last hour?
			$pmCount = pmCount($user_info['id'], 3600);

			if (!empty($pmCount) && $pmCount >= $modSettings['pm_posts_per_hour'])
			{
				if (!isset($this->_req->query->xml))
				{
					throw new \ElkArte\Exceptions\Exception('pm_too_many_per_hour', true, array($modSettings['pm_posts_per_hour']));
				}
				else
				{
					$post_errors->addError('pm_too_many_per_hour');
				}
			}
		}

		// If your session timed out, show an error, but do allow to re-submit.
		if (!isset($this->_req->query->xml) && checkSession('post', '', false) != '')
		{
			$post_errors->addError('session_timeout');
		}

		$this->_req->post->subject = isset($this->_req->post->subject) ? strtr(\ElkArte\Util::htmltrim($this->_req->post->subject), array("\r" => '', "\n" => '', "\t" => '')) : '';
		$this->_req->post->to = $this->_req->getPost('to', 'trim', empty($this->_req->query->to) ? '' : $this->_req->query->to);
		$this->_req->post->bcc = $this->_req->getPost('bcc', 'trim', empty($this->_req->query->bcc) ? '' : $this->_req->query->bcc);

		// Route the input from the 'u' parameter to the 'to'-list.
		if (!empty($this->_req->post->u))
		{
			$this->_req->post->recipient_to = explode(',', $this->_req->post->u);
		}

		$bbc_parser = \BBC\ParserWrapper::instance();

		// Construct the list of recipients.
		$recipientList = array();
		$namedRecipientList = array();
		$namesNotFound = array();
		foreach (array('to', 'bcc') as $recipientType)
		{
			// First, let's see if there's user ID's given.
			$recipientList[$recipientType] = array();
			$type = 'recipient_' . $recipientType;
			if (!empty($this->_req->post->{$type}) && is_array($this->_req->post->{$type}))
			{
				$recipientList[$recipientType] = array_map('intval', $this->_req->post->{$type});
			}

			// Are there also literal names set?
			if (!empty($this->_req->post->{$recipientType}))
			{
				// We're going to take out the "s anyway ;).
				$recipientString = strtr($this->_req->post->{$recipientType}, array('\\"' => '"'));

				preg_match_all('~"([^"]+)"~', $recipientString, $matches);
				$namedRecipientList[$recipientType] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $recipientString))));

				// Clean any literal names entered
				foreach ($namedRecipientList[$recipientType] as $index => $recipient)
				{
					if (strlen(trim($recipient)) > 0)
					{
						$namedRecipientList[$recipientType][$index] = \ElkArte\Util::htmlspecialchars(\ElkArte\Util::strtolower(trim($recipient)));
					}
					else
					{
						unset($namedRecipientList[$recipientType][$index]);
					}
				}

				// Now see if we can resolve the entered name to an actual user
				if (!empty($namedRecipientList[$recipientType]))
				{
					$foundMembers = findMembers($namedRecipientList[$recipientType]);

					// Assume all are not found, until proven otherwise.
					$namesNotFound[$recipientType] = $namedRecipientList[$recipientType];

					// Make sure we only have each member listed once, in case they did not use the select list
					foreach ($foundMembers as $member)
					{
						$testNames = array(
							\ElkArte\Util::strtolower($member['username']),
							\ElkArte\Util::strtolower($member['name']),
							\ElkArte\Util::strtolower($member['email']),
						);

						if (count(array_intersect($testNames, $namedRecipientList[$recipientType])) !== 0)
						{
							$recipientList[$recipientType][] = $member['id'];

							// Get rid of this username, since we found it.
							$namesNotFound[$recipientType] = array_diff($namesNotFound[$recipientType], $testNames);
						}
					}
				}
			}

			// Selected a recipient to be deleted? Remove them now.
			if (!empty($this->_req->post->delete_recipient))
			{
				$recipientList[$recipientType] = array_diff($recipientList[$recipientType], array((int) $this->_req->post->delete_recipient));
			}

			// Make sure we don't include the same name twice
			$recipientList[$recipientType] = array_unique($recipientList[$recipientType]);
		}

		// Are we changing the recipients some how?
		$is_recipient_change = !empty($this->_req->post->delete_recipient) || !empty($this->_req->post->to_submit) || !empty($this->_req->post->bcc_submit);

		// Check if there's at least one recipient.
		if (empty($recipientList['to']) && empty($recipientList['bcc']))
		{
			$post_errors->addError('no_to');
		}

		// Make sure that we remove the members who did get it from the screen.
		if (!$is_recipient_change)
		{
			foreach (array_keys($recipientList) as $recipientType)
			{
				if (!empty($namesNotFound[$recipientType]))
				{
					$post_errors->addError('bad_' . $recipientType);

					// Since we already have a post error, remove the previous one.
					$post_errors->removeError('no_to');

					foreach ($namesNotFound[$recipientType] as $name)
						$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
				}
			}
		}

		// Did they make any mistakes like no subject or message?
		if ($this->_req->post->subject === '')
		{
			$post_errors->addError('no_subject');
		}

		if (!isset($this->_req->post->message) || $this->_req->post->message === '')
		{
			$post_errors->addError('no_message');
		}
		elseif (!empty($modSettings['max_messageLength']) && \ElkArte\Util::strlen($this->_req->post->message) > $modSettings['max_messageLength'])
		{
			$post_errors->addError('long_message');
		}
		else
		{
			// Preparse the message.
			$message = $this->_req->post->message;
			preparsecode($message);

			// Make sure there's still some content left without the tags.
			if (\ElkArte\Util::htmltrim(strip_tags($bbc_parser->parsePM(\ElkArte\Util::htmlspecialchars($message, ENT_QUOTES)), '<img>')) === '' && (!allowedTo('admin_forum') || strpos($message, '[html]') === false))
			{
				$post_errors->addError('no_message');
			}
		}

		// If they made any errors, give them a chance to make amends.
		if ($post_errors->hasErrors() && !$is_recipient_change && !isset($this->_req->query->preview) && !isset($this->_req->query->xml))
		{
			$this->messagePostError($namedRecipientList, $recipientList);

			return false;
		}

		// Want to take a second glance before you send?
		if (isset($this->_req->query->preview))
		{
			// Set everything up to be displayed.
			$context['preview_subject'] = \ElkArte\Util::htmlspecialchars($this->_req->post->subject);
			$context['preview_message'] = \ElkArte\Util::htmlspecialchars($this->_req->post->message, ENT_QUOTES, 'UTF-8', true);
			preparsecode($context['preview_message'], true);

			// Parse out the BBC if it is enabled.
			$context['preview_message'] = $bbc_parser->parsePM($context['preview_message']);

			// Censor, as always.
			$context['preview_subject'] = censor($context['preview_subject']);
			$context['preview_message'] = censor($context['preview_message']);

			// Set a descriptive title.
			$context['page_title'] = $txt['preview'] . ' - ' . $context['preview_subject'];

			// Pretend they messed up but don't ignore if they really did :P.
			$this->messagePostError($namedRecipientList, $recipientList);

			return false;
		}
		// Adding a recipient cause javascript ain't working?
		elseif ($is_recipient_change)
		{
			// Maybe we couldn't find one?
			foreach ($namesNotFound as $recipientType => $names)
			{
				$post_errors->addError('bad_' . $recipientType);
				foreach ($names as $name)
					$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
			}

			$this->messagePostError($namedRecipientList, $recipientList);
			return true;
		}

		try
		{
			$this->_events->trigger('before_sending', array('namedRecipientList' => $namedRecipientList, 'recipientList' => $recipientList, 'namesNotFound' => $namesNotFound, 'post_errors' => $post_errors));
		}
		catch (ControllerRedirectException $e)
		{
			return $this->messagePostError($namedRecipientList, $recipientList);
		}

		// Safety net, it may be a module may just add to the list of errors without actually throw the error
		if ($post_errors->hasErrors() && !isset($this->_req->query->preview) && !isset($this->_req->query->xml))
		{
			$this->messagePostError($namedRecipientList, $recipientList);

			return false;
		}

		// Before we send the PM, let's make sure we don't have an abuse of numbers.
		if (!empty($modSettings['max_pm_recipients']) && count($recipientList['to']) + count($recipientList['bcc']) > $modSettings['max_pm_recipients'] && !allowedTo(array('moderate_forum', 'send_mail', 'admin_forum')))
		{
			$context['send_log'] = array(
				'sent' => array(),
				'failed' => array(sprintf($txt['pm_too_many_recipients'], $modSettings['max_pm_recipients'])),
			);

			$this->messagePostError($namedRecipientList, $recipientList);
			return false;
		}

		// Protect from message spamming.
		spamProtection('pm');

		// Prevent double submission of this form.
		checkSubmitOnce('check');

		// Finally do the actual sending of the PM.
		if (!empty($recipientList['to']) || !empty($recipientList['bcc']))
		{
			$context['send_log'] = sendpm($recipientList, $this->_req->post->subject, $this->_req->post->message, true, null, !empty($this->_req->post->pm_head) ? (int) $this->_req->post->pm_head : 0);
		}
		else
		{
			$context['send_log'] = array(
				'sent' => array(),
				'failed' => array()
			);
		}

		// Mark the message as "replied to".
		if (!empty($context['send_log']['sent']) && !empty($this->_req->post->replied_to) && $this->_req->getQuery('f') === 'inbox')
		{
			require_once(SUBSDIR . '/PersonalMessage.subs.php');
			setPMRepliedStatus($user_info['id'], (int) $this->_req->post->replied_to);
		}

		$failed = !empty($context['send_log']['failed']);
		$this->_events->trigger('message_sent', array('failed' => $failed));

		// If one or more of the recipients were invalid, go back to the post screen with the failed usernames.
		if ($failed)
		{
			$this->messagePostError($namesNotFound, array(
				'to' => array_intersect($recipientList['to'], $context['send_log']['failed']),
				'bcc' => array_intersect($recipientList['bcc'], $context['send_log']['failed'])
			));

			return false;
		}
		// Message sent successfully?
		else
		{
			$context['current_label_redirect'] = $context['current_label_redirect'] . ';done=sent';
		}

		// Go back to the where they sent from, if possible...
		redirectexit($context['current_label_redirect']);

		return true;
	}

	/**
	 * An error in the message...
	 *
	 * @param mixed[] $named_recipients
	 * @param mixed[] $recipient_ids array keys of [bbc] => int[] and [to] => int[]
	 * @param mixed[] $msg_options body, subject and reply values
	 *
	 * @throws \ElkArte\Exceptions\Exception pm_not_yours
	 */
	public function messagePostError($named_recipients, $recipient_ids = array(), $msg_options = null)
	{
		global $txt, $context, $scripturl, $modSettings, $user_info;

		if (isset($this->_req->query->xml))
		{
			$context['sub_template'] = 'generic_preview';
		}
		else
		{
			$context['sub_template'] = 'send';
			$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'send';
		}

		$context['page_title'] = $txt['send_message'];
		$error_types = ErrorContext::context('pm', 1);

		// Got some known members?
		$context['recipients'] = array(
			'to' => array(),
			'bcc' => array(),
		);

		if (!empty($recipient_ids['to']) || !empty($recipient_ids['bcc']))
		{
			$allRecipients = array_merge($recipient_ids['to'], $recipient_ids['bcc']);

			require_once(SUBSDIR . '/Members.subs.php');

			// Get the latest activated member's display name.
			$result = getBasicMemberData($allRecipients);
			foreach ($result as $row)
			{
				$recipientType = in_array($row['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';
				$context['recipients'][$recipientType][] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				);
			}
		}

		// Set everything up like before....
		if (!empty($msg_options))
		{
			$context['subject'] = $msg_options->subject;
			$context['message'] = $msg_options->body;
			$context['reply'] = $msg_options->reply_to;
		}
		else
		{
			$context['subject'] = isset($this->_req->post->subject) ? \ElkArte\Util::htmlspecialchars($this->_req->post->subject) : '';
			$context['message'] = isset($this->_req->post->message) ? str_replace(array('  '), array('&nbsp; '), \ElkArte\Util::htmlspecialchars($this->_req->post->message, ENT_QUOTES, 'UTF-8', true)) : '';
			$context['reply'] = !empty($this->_req->post->replied_to);
		}

		// If this is a reply to message, we need to reload the quote
		if ($context['reply'])
		{
			$pmsg = (int) $this->_req->post->replied_to;
			$isReceived = $context['folder'] !== 'sent';
			$row_quoted = loadPMQuote($pmsg, $isReceived);
			if ($row_quoted === false)
			{
				if (!isset($this->_req->query->xml))
				{
					throw new \ElkArte\Exceptions\Exception('pm_not_yours', false);
				}
				else
				{
					$error_types->addError('pm_not_yours');
				}
			}
			else
			{
				$row_quoted['subject'] = censor($row_quoted['subject']);
				$row_quoted['body'] = censor($row_quoted['body']);
				$bbc_parser = \BBC\ParserWrapper::instance();

				$context['quoted_message'] = array(
					'id' => $row_quoted['id_pm'],
					'pm_head' => $row_quoted['pm_head'],
					'member' => array(
						'name' => $row_quoted['real_name'],
						'username' => $row_quoted['member_name'],
						'id' => $row_quoted['id_member'],
						'href' => !empty($row_quoted['id_member']) ? $scripturl . '?action=profile;u=' . $row_quoted['id_member'] : '',
						'link' => !empty($row_quoted['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row_quoted['id_member'] . '">' . $row_quoted['real_name'] . '</a>' : $row_quoted['real_name'],
					),
					'subject' => $row_quoted['subject'],
					'time' => standardTime($row_quoted['msgtime']),
					'html_time' => htmlTime($row_quoted['msgtime']),
					'timestamp' => forum_time(true, $row_quoted['msgtime']),
					'body' => $bbc_parser->parsePM($row_quoted['body']),
				);
			}
		}

		// Build the link tree....
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=send',
			'name' => $txt['new_message']
		);

		// Set each of the errors for the template.
		$context['post_error'] = array(
			'errors' => $error_types->prepareErrors(),
			'type' => $error_types->getErrorType() == 0 ? 'minor' : 'serious',
			'title' => $txt['error_while_submitting'],
		);

		// We need to load the editor once more.
		require_once(SUBSDIR . '/Editor.subs.php');

		// Create it...
		$editorOptions = array(
			'id' => 'message',
			'value' => $context['message'],
			'width' => '100%',
			'height' => '250px',
			'labels' => array(
				'post_button' => $txt['send_message'],
			),
			'preview_type' => 2,
		);

		// Trigger the prepare_send_context PM event
		$this->_events->trigger('prepare_send_context', array('pmsg' => isset($this->_req->query->pmsg) ? $this->_req->query->pmsg : (isset($this->_req->query->quote) ? $this->_req->query->quote : 0), 'editorOptions' => &$editorOptions, 'recipientList' => &$recipient_ids));

		create_control_richedit($editorOptions);

		// Check whether we need to show the code again.
		$context['require_verification'] = !$user_info['is_admin'] && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'];
		if ($context['require_verification'] && !isset($this->_req->query->xml))
		{
			$verificationOptions = array(
				'id' => 'pm',
			);
			$context['require_verification'] = \ElkArte\VerificationControls\VerificationControlsIntegrate::create($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}

		$context['to_value'] = empty($named_recipients['to']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['to']) . '&quot;';
		$context['bcc_value'] = empty($named_recipients['bcc']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['bcc']) . '&quot;';

		// No check for the previous submission is needed.
		checkSubmitOnce('free');

		// Acquire a new form sequence number.
		checkSubmitOnce('register');
	}

	/**
	 * This function performs all additional actions including the deleting
	 * and labeling of PM's
	 */
	public function action_pmactions()
	{
		global $context, $user_info;

		checkSession('request');

		// Sending in the single pm choice via GET
		$pm_actions = $this->_req->getQuery('pm_actions', null, '');

		// Set the action to apply to the pm's defined by pm_actions (yes its that brilliant)
		$pm_action = $this->_req->getPost('pm_action', 'trim', '');
		$pm_action = empty($pm_action) && isset($this->_req->post->del_selected) ? 'delete' : $pm_action;

		// Create a list of pm's that we need to work on
		if ($pm_action != ''
			&& !empty($this->_req->post->pms)
			&& is_array($this->_req->post->pms))
		{
			$pm_actions = array();
			foreach ($this->_req->post->pms as $pm)
				$pm_actions[(int) $pm] = $pm_action;
		}

		// No messages to action then bug out
		if (empty($pm_actions))
			redirectexit($context['current_label_redirect']);

		// If we are in conversation, we may need to apply this to every message in that conversation.
		if ($context['display_mode'] == 2 && isset($this->_req->query->conversation))
		{
			$id_pms = array_map('intval', array_keys($pm_actions));
			$pm_heads = getDiscussions($id_pms);
			$pms = getPmsFromDiscussion(array_keys($pm_heads));

			// Copy the action from the single to PM to the others in the conversation.
			foreach ($pms as $id_pm => $id_head)
			{
				if (isset($pm_heads[$id_head]) && isset($pm_actions[$pm_heads[$id_head]]))
				{
					$pm_actions[$id_pm] = $pm_actions[$pm_heads[$id_head]];
				}
			}
		}

		// Lets get to doing what we've been told
		$to_delete = array();
		$to_label = array();
		$label_type = array();
		foreach ($pm_actions as $pm => $action)
		{
			// What are we doing with the selected messages, adding a label, removing, other?
			switch (substr($action, 0, 4))
			{
				case 'dele':
					$to_delete[] = (int) $pm;
					break;
				case 'add_':
					$type = 'add';
					$action = substr($action, 4);
					break;
				case 'rem_':
					$type = 'rem';
					$action = substr($action, 4);
					break;
				default:
					$type = 'unk';
			}

			if ($action === '-1' || $action === '0' || (int) $action > 0)
			{
				$to_label[(int) $pm] = (int) $action;
				$label_type[(int) $pm] = $type;
			}
		}

		// Deleting, it looks like?
		if (!empty($to_delete))
		{
			deleteMessages($to_delete, $context['display_mode'] == 2 ? null : $context['folder']);
		}

		// Are we labelling anything?
		if (!empty($to_label) && $context['folder'] === 'inbox')
		{
			$updateErrors = changePMLabels($to_label, $label_type, $user_info['id']);

			// Any errors?
			if (!empty($updateErrors))
			{
				throw new \ElkArte\Exceptions\Exception('labels_too_many', true, array($updateErrors));
			}
		}

		// Back to the folder.
		$_SESSION['pm_selected'] = array_keys($to_label);
		redirectexit($context['current_label_redirect'] . (count($to_label) == 1 ? '#msg_' . $_SESSION['pm_selected'][0] : ''), count($to_label) == 1 && isBrowser('ie'));
	}

	/**
	 * Are you sure you want to PERMANENTLY (mostly) delete ALL your messages?
	 */
	public function action_removeall()
	{
		global $txt, $context;

		// Only have to set up the template....
		$context['sub_template'] = 'ask_delete';
		$context['page_title'] = $txt['delete_all'];
		$context['delete_all'] = $this->_req->query->f === 'all';

		// And set the folder name...
		$txt['delete_all'] = str_replace('PMBOX', $context['folder'] != 'sent' ? $txt['inbox'] : $txt['sent_items'], $txt['delete_all']);
	}

	/**
	 * Delete ALL the messages!
	 */
	public function action_removeall2()
	{
		global $context;

		checkSession('get');

		// If all then delete all messages the user has.
		if ($this->_req->query->f === 'all')
		{
			deleteMessages(null, null);
		}
		// Otherwise just the selected folder.
		else
		{
			deleteMessages(null, $this->_req->query->f != 'sent' ? 'inbox' : 'sent');
		}

		// Done... all gone.
		redirectexit($context['current_label_redirect']);
	}

	/**
	 * This function allows the user to prune (delete) all messages older than a supplied duration.
	 */
	public function action_prune()
	{
		global $txt, $context, $user_info, $scripturl;

		// Actually delete the messages.
		if (isset($this->_req->post->age))
		{
			checkSession();

			// Calculate the time to delete before.
			$deleteTime = max(0, time() - (86400 * (int) $this->_req->post->age));

			// Select all the messages older than $deleteTime.
			$toDelete = getPMsOlderThan($user_info['id'], $deleteTime);

			// Delete the actual messages.
			deleteMessages($toDelete);

			// Go back to their inbox.
			redirectexit($context['current_label_redirect']);
		}

		// Build the link tree elements.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=prune',
			'name' => $txt['pm_prune']
		);
		$context['sub_template'] = 'prune';
		$context['page_title'] = $txt['pm_prune'];
	}

	/**
	 * This function handles adding, deleting and editing labels on messages.
	 */
	public function action_manlabels()
	{
		global $txt, $context, $user_info, $scripturl;

		require_once(SUBSDIR . '/PersonalMessage.subs.php');

		// Build the link tree elements...
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=manlabels',
			'name' => $txt['pm_manage_labels']
		);

		// Some things for the template
		$context['page_title'] = $txt['pm_manage_labels'];
		$context['sub_template'] = 'labels';

		// Add all existing labels to the array to save, slashing them as necessary...
		$the_labels = array();
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] != -1)
			{
				$the_labels[$label['id']] = $label['name'];
			}
		}

		// Submitting changes?
		if (isset($this->_req->post->add) || isset($this->_req->post->delete) || isset($this->_req->post->save))
		{
			checkSession('post');

			// This will be for updating messages.
			$message_changes = array();
			$new_labels = array();
			$rule_changes = array();

			// Will most likely need this.
			loadRules();

			// Adding a new label?
			if (isset($this->_req->post->add))
			{
				$this->_req->post->label = strtr(\ElkArte\Util::htmlspecialchars(trim($this->_req->post->label)), array(',' => '&#044;'));

				if (\ElkArte\Util::strlen($this->_req->post->label) > 30)
				{
					$this->_req->post->label = \ElkArte\Util::substr($this->_req->post->label, 0, 30);
				}
				if ($this->_req->post->label != '')
				{
					$the_labels[] = $this->_req->post->label;
				}
			}
			// Deleting an existing label?
			elseif (isset($this->_req->post->delete, $this->_req->post->delete_label))
			{
				$i = 0;
				foreach ($the_labels as $id => $name)
				{
					if (isset($this->_req->post->delete_label[$id]))
					{
						unset($the_labels[$id]);
						$message_changes[$id] = true;
					}
					else
					{
						$new_labels[$id] = $i++;
					}
				}
			}
			// The hardest one to deal with... changes.
			elseif (isset($this->_req->post->save) && !empty($this->_req->post->label_name))
			{
				$i = 0;
				foreach ($the_labels as $id => $name)
				{
					if ($id == -1)
					{
						continue;
					}
					elseif (isset($this->_req->post->label_name[$id]))
					{
						// Prepare the label name
						$this->_req->post->label_name[$id] = trim(strtr(\ElkArte\Util::htmlspecialchars($this->_req->post->label_name[$id]), array(',' => '&#044;')));

						// Has to fit in the database as well
						if (\ElkArte\Util::strlen($this->_req->post->label_name[$id]) > 30)
						{
							$this->_req->post->label_name[$id] = \ElkArte\Util::substr($this->_req->post->label_name[$id], 0, 30);
						}

						if ($this->_req->post->label_name[$id] != '')
						{
							$the_labels[(int) $id] = $this->_req->post->label_name[$id];
							$new_labels[$id] = $i++;
						}
						else
						{
							unset($the_labels[(int) $id]);
							$message_changes[(int) $id] = true;
						}
					}
					else
					{
						$new_labels[$id] = $i++;
					}
				}
			}

			// Save the label status.
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($user_info['id'], array('message_labels' => implode(',', $the_labels)));

			// Update all the messages currently with any label changes in them!
			if (!empty($message_changes))
			{
				$searchArray = array_keys($message_changes);

				if (!empty($new_labels))
				{
					for ($i = max($searchArray) + 1, $n = max(array_keys($new_labels)); $i <= $n; $i++)
						$searchArray[] = $i;
				}

				updateLabelsToPM($searchArray, $new_labels, $user_info['id']);

				// Now do the same the rules - check through each rule.
				foreach ($context['rules'] as $k => $rule)
				{
					// Each action...
					foreach ($rule['actions'] as $k2 => $action)
					{
						if ($action['t'] != 'lab' || !in_array($action['v'], $searchArray))
						{
							continue;
						}

						$rule_changes[] = $rule['id'];

						// If we're here we have a label which is either changed or gone...
						if (isset($new_labels[$action['v']]))
						{
							$context['rules'][$k]['actions'][$k2]['v'] = $new_labels[$action['v']];
						}
						else
						{
							unset($context['rules'][$k]['actions'][$k2]);
						}
					}
				}
			}

			// If we have rules to change do so now.
			if (!empty($rule_changes))
			{
				$rule_changes = array_unique($rule_changes);

				// Update/delete as appropriate.
				foreach ($rule_changes as $k => $id)
					if (!empty($context['rules'][$id]['actions']))
					{
						updatePMRuleAction($id, $user_info['id'], $context['rules'][$id]['actions']);
						unset($rule_changes[$k]);
					}

				// Anything left here means it's lost all actions...
				if (!empty($rule_changes))
				{
					deletePMRules($user_info['id'], $rule_changes);
				}
			}

			// Make sure we're not caching this!
			\ElkArte\Cache\Cache::instance()->remove('labelCounts:' . $user_info['id']);

			// To make the changes appear right away, redirect.
			redirectexit('action=pm;sa=manlabels');
		}
	}

	/**
	 * Allows to edit Personal Message Settings.
	 *
	 * @uses ProfileOptions controller. (@todo refactor this.)
	 * @uses Profile template.
	 * @uses Profile language file.
	 */
	public function action_settings()
	{
		global $txt, $user_info, $context, $scripturl, $profile_vars, $cur_profile, $user_profile;

		require_once(SUBSDIR . '/Profile.subs.php');

		// Load the member data for editing
		loadMemberData($user_info['id'], false, 'profile');
		$cur_profile = $user_profile[$user_info['id']];

		// Load up the profile template, its where PM settings are located
		theme()->getTemplates()->loadLanguageFile('Profile');
		theme()->getTemplates()->load('Profile');

		// We want them to submit back to here.
		$context['profile_custom_submit_url'] = $scripturl . '?action=pm;sa=settings;save';

		$context['page_title'] = $txt['pm_settings'];
		$context['user']['is_owner'] = true;
		$context['id_member'] = $user_info['id'];
		$context['require_password'] = false;
		$context['menu_item_selected'] = 'settings';
		$context['submit_button_text'] = $txt['pm_settings'];

		// Add our position to the linktree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=settings',
			'name' => $txt['pm_settings']
		);

		// Are they saving?
		if (isset($this->_req->post->save))
		{
			checkSession('post');

			// Mimic what profile would do.
			// @todo fix this when Profile.subs is not dependant on this behavior
			$_POST = htmltrim__recursive((array) $this->_req->post);
			$_POST = htmlspecialchars__recursive($_POST);

			// Save the fields.
			$fields = \ElkArte\Controller\ProfileOptions::getFields('contactprefs');
			saveProfileFields($fields['fields'], $fields['hook']);

			if (!empty($profile_vars))
			{
				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData($user_info['id'], $profile_vars);
			}

			// Invalidate any cached data and reload so we show the saved values
			\ElkArte\Cache\Cache::instance()->remove('member_data-profile-' . $user_info['id']);
			loadMemberData($user_info['id'], false, 'profile');
			$cur_profile = $user_profile[$user_info['id']];
		}

		// Load up the fields.
		$controller = new \ElkArte\Controller\ProfileOptions(new \ElkArte\EventManager());
		$controller->pre_dispatch();
		$controller->action_pmprefs();
	}

	/**
	 * Allows the user to report a personal message to an administrator.
	 *
	 * What it does:
	 *
	 * - In the first instance requires that the ID of the message to report is passed through $_GET.
	 * - It allows the user to report to either a particular administrator - or the whole admin team.
	 * - It will forward on a copy of the original message without allowing the reporter to make changes.
	 *
	 * @uses report_message sub-template.
	 */
	public function action_report()
	{
		global $txt, $context, $user_info, $language, $modSettings;

		// Check that this feature is even enabled!
		if (empty($modSettings['enableReportPM']) || empty($this->_req->query->pmsg))
		{
			throw new \ElkArte\Exceptions\Exception('no_access', false);
		}

		$pmsg = $this->_req->getQuery('pmsg', 'intval', $this->_req->getPost('pmsg', 'intval', 0));

		if (!isAccessiblePM($pmsg, 'inbox'))
		{
			throw new \ElkArte\Exceptions\Exception('no_access', false);
		}

		$context['pm_id'] = $pmsg;
		$context['page_title'] = $txt['pm_report_title'];
		$context['sub_template'] = 'report_message';

		// We'll query some members, we will.
		require_once(SUBSDIR . '/Members.subs.php');

		// If we're here, just send the user to the template, with a few useful context bits.
		if (isset($this->_req->post->report))
		{
			$poster_comment = strtr(\ElkArte\Util::htmlspecialchars($this->_req->post->reason), array("\r" => '', "\t" => ''));

			if (\ElkArte\Util::strlen($poster_comment) > 254)
			{
				throw new \ElkArte\Exceptions\Exception('post_too_long', false);
			}

			// Check the session before proceeding any further!
			checkSession('post');

			// First, load up the message they want to file a complaint against, and verify it actually went to them!
			list ($subject, $body, $time, $memberFromID, $memberFromName, $poster_name, $time_message) = loadPersonalMessage($pmsg);

			require_once(SUBSDIR . '/Messages.subs.php');

			recordReport(array(
				'id_msg' => $pmsg,
				'id_topic' => 0,
				'id_board' => 0,
				'type' => 'pm',
				'id_poster' => $memberFromID,
				'real_name' => $memberFromName,
				'poster_name' => $poster_name,
				'subject' => $subject,
				'body' => $body,
				'time_message' => $time_message,
			), $poster_comment);

			// Remove the line breaks...
			$body = preg_replace('~<br ?/?' . '>~i', "\n", $body);

			$recipients = array();
			$temp = loadPMRecipientsAll($context['pm_id'], true);
			foreach ($temp as $recipient)
				$recipients[] = $recipient['link'];

			// Now let's get out and loop through the admins.
			$admins = admins(isset($this->_req->post->id_admin) ? (int) $this->_req->post->id_admin : 0);

			// Maybe we shouldn't advertise this?
			if (empty($admins))
			{
				throw new \ElkArte\Exceptions\Exception('no_access', false);
			}

			$memberFromName = un_htmlspecialchars($memberFromName);

			// Prepare the message storage array.
			$messagesToSend = array();

			// Loop through each admin, and add them to the right language pile...
			foreach ($admins as $id_admin => $admin_info)
			{
				// Need to send in the correct language!
				$cur_language = empty($admin_info['lngfile']) || empty($modSettings['userLanguage']) ? $language : $admin_info['lngfile'];

				if (!isset($messagesToSend[$cur_language]))
				{
					theme()->getTemplates()->loadLanguageFile('PersonalMessage', $cur_language, false);

					// Make the body.
					$report_body = str_replace(array('{REPORTER}', '{SENDER}'), array(un_htmlspecialchars($user_info['name']), $memberFromName), $txt['pm_report_pm_user_sent']);
					$report_body .= "\n" . '[b]' . $this->_req->post->reason . '[/b]' . "\n\n";
					if (!empty($recipients))
					{
						$report_body .= $txt['pm_report_pm_other_recipients'] . ' ' . implode(', ', $recipients) . "\n\n";
					}
					$report_body .= $txt['pm_report_pm_unedited_below'] . "\n" . '[quote author=' . (empty($memberFromID) ? '&quot;' . $memberFromName . '&quot;' : $memberFromName . ' link=action=profile;u=' . $memberFromID . ' date=' . $time) . ']' . "\n" . un_htmlspecialchars($body) . '[/quote]';

					// Plonk it in the array ;)
					$messagesToSend[$cur_language] = array(
						'subject' => (\ElkArte\Util::strpos($subject, $txt['pm_report_pm_subject']) === false ? $txt['pm_report_pm_subject'] : '') . un_htmlspecialchars($subject),
						'body' => $report_body,
						'recipients' => array(
							'to' => array(),
							'bcc' => array()
						),
					);
				}

				// Add them to the list.
				$messagesToSend[$cur_language]['recipients']['to'][$id_admin] = $id_admin;
			}

			// Send a different email for each language.
			foreach ($messagesToSend as $lang => $message)
				sendpm($message['recipients'], $message['subject'], $message['body']);

			// Give the user their own language back!
			if (!empty($modSettings['userLanguage']))
			{
				theme()->getTemplates()->loadLanguageFile('PersonalMessage', '', false);
			}

			// Leave them with a template.
			$context['sub_template'] = 'report_message_complete';
		}
	}

	/**
	 * List and allow adding/entering all man rules, such as
	 *
	 * What it does:
	 *
	 * - If it itches, it will be scratched.
	 * - Yes or No are perfectly acceptable answers to almost every question.
	 * - Men see in only 16 colors, Peach, for example, is a fruit, not a color.
	 *
	 * @uses sub template rules
	 */
	public function action_manrules()
	{
		global $txt, $context, $user_info, $scripturl;

		require_once(SUBSDIR . '/PersonalMessage.subs.php');

		// The link tree - gotta have this :o
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=manrules',
			'name' => $txt['pm_manage_rules']
		);

		$context['page_title'] = $txt['pm_manage_rules'];
		$context['sub_template'] = 'rules';

		// Load them... load them!!
		loadRules();

		// Likely to need all the groups!
		require_once(SUBSDIR . '/Membergroups.subs.php');
		$context['groups'] = accessibleGroups();

		// Applying all rules?
		if (isset($this->_req->query->apply))
		{
			checkSession('get');

			applyRules(true);
			redirectexit('action=pm;sa=manrules');
		}

		// Editing a specific rule?
		if (isset($this->_req->query->add))
		{
			$context['rid'] = isset($this->_req->query->rid) && isset($context['rules'][$this->_req->query->rid]) ? (int) $this->_req->query->rid : 0;
			$context['sub_template'] = 'add_rule';

			// Any known rule
			$js_rules = '';
			foreach ($context['known_rules'] as $rule)
			{
				$js_rules[$rule] = $txt['pm_rule_' . $rule];
			}
			$js_rules = json_encode($js_rules);

			// Any known label
			$js_labels = '';
			foreach ($context['labels'] as $label)
			{
				if ($label['id'] != -1)
				{
					$js_labels[$label['id'] + 1] = $label['name'];
				}
			}
			$js_labels = json_encode($js_labels);

			// And all of the groups as well
			$js_groups = json_encode($context['groups']);

			// Oh my, we have a lot of text strings for this
			theme()->addJavascriptVar(array(
				'criteriaNum' => 0,
				'actionNum' => 0,
				'groups' => $js_groups,
				'labels' => $js_labels,
				'rules' => $js_rules,
				'txt_pm_readable_and' => $txt['pm_readable_and'],
				'txt_pm_readable_or' => $txt['pm_readable_or'],
				'txt_pm_readable_member' => $txt['pm_readable_member'],
				'txt_pm_readable_group' => $txt['pm_readable_group'],
				'txt_pm_readable_subject ' => $txt['pm_readable_subject'],
				'txt_pm_readable_body' => $txt['pm_readable_body'],
				'txt_pm_readable_buddy' => $txt['pm_readable_buddy'],
				'txt_pm_readable_label' => $txt['pm_readable_label'],
				'txt_pm_readable_delete' => $txt['pm_readable_delete'],
				'txt_pm_readable_start' => $txt['pm_readable_start'],
				'txt_pm_readable_end' => $txt['pm_readable_end'],
				'txt_pm_readable_then' => $txt['pm_readable_then'],
				'txt_pm_rule_not_defined' => $txt['pm_rule_not_defined'],
				'txt_pm_rule_criteria_pick' => $txt['pm_rule_criteria_pick'],
				'txt_pm_rule_sel_group' => $txt['pm_rule_sel_group'],
				'txt_pm_rule_sel_action' => $txt['pm_rule_sel_action'],
				'txt_pm_rule_label' => $txt['pm_rule_label'],
				'txt_pm_rule_delete' => $txt['pm_rule_delete'],
				'txt_pm_rule_sel_label' => $txt['pm_rule_sel_label'],
			), true);

			// Current rule information...
			if ($context['rid'])
			{
				$context['rule'] = $context['rules'][$context['rid']];
				$members = array();

				// Need to get member names!
				foreach ($context['rule']['criteria'] as $k => $criteria)
					if ($criteria['t'] === 'mid' && !empty($criteria['v']))
					{
						$members[(int) $criteria['v']] = $k;
					}

				if (!empty($members))
				{
					require_once(SUBSDIR . '/Members.subs.php');
					$result = getBasicMemberData(array_keys($members));
					foreach ($result as $row)
						$context['rule']['criteria'][$members[$row['id_member']]]['v'] = $row['member_name'];
				}
			}
			else
			{
				$context['rule'] = array(
					'id' => '',
					'name' => '',
					'criteria' => array(),
					'actions' => array(),
					'logic' => 'and',
				);
			}

			// Add a dummy criteria to allow expansion for none js users.
			$context['rule']['criteria'][] = array('t' => '', 'v' => '');
		}
		// Saving?
		elseif (isset($this->_req->query->save))
		{
			checkSession('post');
			$context['rid'] = isset($this->_req->query->rid) && isset($context['rules'][$this->_req->query->rid]) ? (int) $this->_req->query->rid : 0;

			// Name is easy!
			$ruleName = \ElkArte\Util::htmlspecialchars(trim($this->_req->post->rule_name));
			if (empty($ruleName))
			{
				throw new \ElkArte\Exceptions\Exception('pm_rule_no_name', false);
			}

			// Sanity check...
			if (empty($this->_req->post->ruletype) || empty($this->_req->post->acttype))
			{
				throw new \ElkArte\Exceptions\Exception('pm_rule_no_criteria', false);
			}

			// Let's do the criteria first - it's also hardest!
			$criteria = array();
			foreach ($this->_req->post->ruletype as $ind => $type)
			{
				// Check everything is here...
				if ($type === 'gid' && (!isset($this->_req->post->ruledefgroup[$ind]) || !isset($context['groups'][$this->_req->post->ruledefgroup[$ind]])))
				{
					continue;
				}
				elseif ($type != 'bud' && !isset($this->_req->post->ruledef[$ind]))
				{
					continue;
				}

				// Members need to be found.
				if ($type === 'mid')
				{
					require_once(SUBSDIR . '/Members.subs.php');
					$name = trim($this->_req->post->ruledef[$ind]);
					$member = getMemberByName($name, true);
					if (empty($member))
					{
						continue;
					}

					$criteria[] = array('t' => 'mid', 'v' => $member['id_member']);
				}
				elseif ($type === 'bud')
				{
					$criteria[] = array('t' => 'bud', 'v' => 1);
				}
				elseif ($type === 'gid')
				{
					$criteria[] = array('t' => 'gid', 'v' => (int) $this->_req->post->ruledefgroup[$ind]);
				}
				elseif (in_array($type, array('sub', 'msg')) && trim($this->_req->post->ruledef[$ind]) != '')
				{
					$criteria[] = array('t' => $type, 'v' => \ElkArte\Util::htmlspecialchars(trim($this->_req->post->ruledef[$ind])));
				}
			}

			// Also do the actions!
			$actions = array();
			$doDelete = 0;
			$isOr = $this->_req->post->rule_logic === 'or' ? 1 : 0;
			foreach ($this->_req->post->acttype as $ind => $type)
			{
				// Picking a valid label?
				if ($type === 'lab' && (!isset($this->_req->post->labdef[$ind]) || !isset($context['labels'][$this->_req->post->labdef[$ind] - 1])))
				{
					continue;
				}

				// Record what we're doing.
				if ($type === 'del')
				{
					$doDelete = 1;
				}
				elseif ($type === 'lab')
				{
					$actions[] = array('t' => 'lab', 'v' => (int) $this->_req->post->labdef[$ind] - 1);
				}
			}

			if (empty($criteria) || (empty($actions) && !$doDelete))
			{
				throw new \ElkArte\Exceptions\Exception('pm_rule_no_criteria', false);
			}

			// What are we storing?
			$criteria = serialize($criteria);
			$actions = serialize($actions);

			// Create the rule?
			if (empty($context['rid']))
			{
				addPMRule($user_info['id'], $ruleName, $criteria, $actions, $doDelete, $isOr);
			}
			else
			{
				updatePMRule($user_info['id'], $context['rid'], $ruleName, $criteria, $actions, $doDelete, $isOr);
			}

			redirectexit('action=pm;sa=manrules');
		}
		// Deleting?
		elseif (isset($this->_req->post->delselected) && !empty($this->_req->post->delrule))
		{
			checkSession('post');
			$toDelete = array();
			foreach ($this->_req->post->delrule as $k => $v)
				$toDelete[] = (int) $k;

			if (!empty($toDelete))
			{
				deletePMRules($user_info['id'], $toDelete);
			}

			redirectexit('action=pm;sa=manrules');
		}
	}

	/**
	 * Actually do the search of personal messages and show the results
	 *
	 * What it does:
	 *
	 * - accessed with ?action=pm;sa=search2
	 * - checks user input and searches the pm table for messages matching the query.
	 * - uses the search_results sub template of the PersonalMessage template.
	 * - show the results of the search query.
	 */
	public function action_search2()
	{
		global $scripturl, $modSettings, $context, $txt, $memberContext;

		// Make sure the server is able to do this right now
		if (!empty($modSettings['loadavg_search']) && $modSettings['current_load'] >= $modSettings['loadavg_search'])
		{
			throw new \ElkArte\Exceptions\Exception('loadavg_search_disabled', false);
		}

		// Some useful general permissions.
		$context['can_send_pm'] = allowedTo('pm_send');

		// Extract all the search parameters if coming in from pagination, etc
		$this->_searchParamsFromString();

		// Set a start for pagination
		$context['start'] = $this->_req->getQuery('start', 'intval', 0);

		// Set/clean search criteria
		$this->_prepareSearchParams();

		$context['folder'] = empty($this->_search_params['sent_only']) ? 'inbox' : 'sent';

		// Searching for specific members
		$userQuery = $this->_setUserQuery();

		// Setup the sorting variables...
		$this->_setSortParams();

		// Sort out any labels we may be searching by.
		$labelQuery = $this->_setLabelQuery();

		// Unfortunately, searching for words like this is going to be slow, so we're blacklisting them.
		$blacklisted_words = array('quote', 'the', 'is', 'it', 'are', 'if');

		// What are we actually searching for?
		$this->_search_params['search'] = !empty($this->_search_params['search']) ? $this->_search_params['search'] : (isset($this->_req->post->search) ? $this->_req->post->search : '');

		// If nothing is left to search on - we set an error!
		if (!isset($this->_search_params['search']) || $this->_search_params['search'] === '')
		{
			$context['search_errors']['invalid_search_string'] = true;
		}

		// Change non-word characters into spaces.
		$stripped_query = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', $this->_search_params['search']);

		// Make the query lower case since it will case insensitive anyway.
		$stripped_query = un_htmlspecialchars(\ElkArte\Util::strtolower($stripped_query));

		// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
		preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);
		$phraseArray = $matches[2];

		// Remove the phrase parts and extract the words.
		$wordArray = preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $this->_search_params['search']);
		$wordArray = explode(' ', \ElkArte\Util::htmlspecialchars(un_htmlspecialchars($wordArray), ENT_QUOTES));

		// A minus sign in front of a word excludes the word.... so...
		$excludedWords = array();

		// Check for things like -"some words", but not "-some words".
		foreach ($matches[1] as $index => $word)
		{
			if ($word === '-')
			{
				if (($word = trim($phraseArray[$index], '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
				{
					$excludedWords[] = $word;
				}
				unset($phraseArray[$index]);
			}
		}

		// Now we look for -test, etc
		foreach ($wordArray as $index => $word)
		{
			if (strpos(trim($word), '-') === 0)
			{
				if (($word = trim($word, '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
				{
					$excludedWords[] = $word;
				}
				unset($wordArray[$index]);
			}
		}

		// The remaining words and phrases are all included.
		$searchArray = array_merge($phraseArray, $wordArray);

		// Trim everything and make sure there are no words that are the same.
		foreach ($searchArray as $index => $value)
		{
			// Skip anything that's close to empty.
			if (($searchArray[$index] = trim($value, '-_\' ')) === '')
			{
				unset($searchArray[$index]);
			}
			// Skip blacklisted words. Make sure to note we skipped them as well
			elseif (in_array($searchArray[$index], $blacklisted_words))
			{
				$foundBlackListedWords = true;
				unset($searchArray[$index]);

			}

			if (isset($searchArray[$index]))
			{
				$searchArray[$index] = \ElkArte\Util::strtolower(trim($value));

				if ($searchArray[$index] === '')
				{
					unset($searchArray[$index]);
				}
				else
				{
					// Sort out entities first.
					$searchArray[$index] = \ElkArte\Util::htmlspecialchars($searchArray[$index]);
				}
			}
		}

		$searchArray = array_slice(array_unique($searchArray), 0, 10);

		// This contains *everything*
		$searchWords = array_merge($searchArray, $excludedWords);

		// Make sure at least one word is being searched for.
		if (empty($searchArray))
		{
			$context['search_errors']['invalid_search_string' . (!empty($foundBlackListedWords) ? '_blacklist' : '')] = true;
		}

		// Sort out the search query so the user can edit it - if they want.
		$context['search_params'] = $this->_search_params;
		if (isset($context['search_params']['search']))
		{
			$context['search_params']['search'] = \ElkArte\Util::htmlspecialchars($context['search_params']['search']);
		}

		if (isset($context['search_params']['userspec']))
		{
			$context['search_params']['userspec'] = \ElkArte\Util::htmlspecialchars($context['search_params']['userspec']);
		}

		// Now we have all the parameters, combine them together for pagination and the like...
		$context['params'] = $this->_compileURLparams();

		// Compile the subject query part.
		$andQueryParts = array();
		foreach ($searchWords as $index => $word)
		{
			if ($word === '')
			{
				continue;
			}

			if ($this->_search_params['subject_only'])
			{
				$andQueryParts[] = 'pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '}';
			}
			else
			{
				$andQueryParts[] = '(pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '} ' . (in_array($word, $excludedWords) ? 'AND pm.body NOT' : 'OR pm.body') . ' LIKE {string:search_' . $index . '})';
			}

			$this->_searchq_parameters ['search_' . $index] = '%' . strtr($word, array('_' => '\\_', '%' => '\\%')) . '%';
		}

		$searchQuery = ' 1=1';
		if (!empty($andQueryParts))
		{
			$searchQuery = implode(!empty($this->_search_params['searchtype']) && $this->_search_params['searchtype'] == 2 ? ' OR ' : ' AND ', $andQueryParts);
		}

		// Age limits?
		$timeQuery = '';
		if (!empty($this->_search_params['minage']))
		{
			$timeQuery .= ' AND pm.msgtime < ' . (time() - $this->_search_params['minage'] * 86400);
		}

		if (!empty($this->_search_params['maxage']))
		{
			$timeQuery .= ' AND pm.msgtime > ' . (time() - $this->_search_params['maxage'] * 86400);
		}

		// If we have errors - return back to the first screen...
		if (!empty($context['search_errors']))
		{
			$this->_req->post->params = $context['params'];

			$this->action_search();
			return false;
		}

		// Get the number of results.
		$numResults = numPMSeachResults($userQuery, $labelQuery, $timeQuery, $searchQuery, $this->_searchq_parameters);

		// Get all the matching message ids, senders and head pm nodes
		list($foundMessages, $posters, $head_pms) = loadPMSearchMessages($userQuery, $labelQuery, $timeQuery, $searchQuery, $this->_searchq_parameters, $this->_search_params);

		// Find the real head pm when in conversation view
		if ($context['display_mode'] == 2 && !empty($head_pms))
		{
			$real_pm_ids = loadPMSearchHeads($head_pms);
		}

		// Load the found user data
		$posters = array_unique($posters);
		if (!empty($posters))
		{
			loadMemberData($posters);
		}

		// Sort out the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=pm;sa=search2;params=' . $context['params'], $this->_req->query->start, $numResults, $modSettings['search_results_per_page'], false);

		$context['message_labels'] = array();
		$context['message_replied'] = array();
		$context['personal_messages'] = array();
		$context['first_label'] = array();

		// If we have results, we have work to do!
		if (!empty($foundMessages))
		{
			$recipients = array();
			list($context['message_labels'], $context['message_replied'], $context['message_unread'], $context['first_label']) = loadPMRecipientInfo($foundMessages, $recipients, $context['folder'], true);

			// Prepare for the callback!
			$search_results = loadPMSearchResults($foundMessages, $this->_search_params);
			$counter = 0;
			$bbc_parser = \BBC\ParserWrapper::instance();
			foreach ($search_results as $row)
			{
				// If there's no subject, use the default.
				$row['subject'] = $row['subject'] === '' ? $txt['no_subject'] : $row['subject'];

				// Load this posters context info, if its not there then fill in the essentials...
				if (!loadMemberContext($row['id_member_from'], true))
				{
					$memberContext[$row['id_member_from']]['name'] = $row['from_name'];
					$memberContext[$row['id_member_from']]['id'] = 0;
					$memberContext[$row['id_member_from']]['group'] = $txt['guest_title'];
					$memberContext[$row['id_member_from']]['link'] = $row['from_name'];
					$memberContext[$row['id_member_from']]['email'] = '';
					$memberContext[$row['id_member_from']]['show_email'] = showEmailAddress(true, 0);
					$memberContext[$row['id_member_from']]['is_guest'] = true;
				}

				// Censor anything we don't want to see...
				$row['body'] = censor($row['body']);
				$row['subject'] = censor($row['subject']);

				// Parse out any BBC...
				$row['body'] = $bbc_parser->parsePM($row['body']);

				// Highlight the hits
				$body_highlighted = '';
				$subject_highlighted = '';
				foreach ($searchArray as $query)
				{
					// Fix the international characters in the keyword too.
					$query = un_htmlspecialchars($query);
					$query = trim($query, '\*+');
					$query = strtr(\ElkArte\Util::htmlspecialchars($query), array('\\\'' => '\''));

					$body_highlighted = preg_replace_callback('/((<[^>]*)|' . preg_quote(strtr($query, array('\'' => '&#039;')), '/') . ')/iu', array($this, '_highlighted_callback'), $row['body']);
					$subject_highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<strong class="highlight">$1</strong>', $row['subject']);
				}

				// Set a link using the first label information
				$href = $scripturl . '?action=pm;f=' . $context['folder'] . (isset($context['first_label'][$row['id_pm']]) ? ';l=' . $context['first_label'][$row['id_pm']] : '') . ';pmid=' . ($context['display_mode'] == 2 && isset($real_pm_ids[$head_pms[$row['id_pm']]]) && $context['folder'] === 'inbox' ? $real_pm_ids[$head_pms[$row['id_pm']]] : $row['id_pm']) . '#msg_' . $row['id_pm'];

				$context['personal_messages'][] = array(
					'id' => $row['id_pm'],
					'member' => &$memberContext[$row['id_member_from']],
					'subject' => $subject_highlighted,
					'body' => $body_highlighted,
					'time' => standardTime($row['msgtime']),
					'html_time' => htmlTime($row['msgtime']),
					'timestamp' => forum_time(true, $row['msgtime']),
					'recipients' => &$recipients[$row['id_pm']],
					'labels' => &$context['message_labels'][$row['id_pm']],
					'fully_labeled' => count($context['message_labels'][$row['id_pm']]) == count($context['labels']),
					'is_replied_to' => &$context['message_replied'][$row['id_pm']],
					'href' => $href,
					'link' => '<a href="' . $href . '">' . $subject_highlighted . '</a>',
					'counter' => ++$counter,
				);
			}
		}

		// Finish off the context.
		$context['page_title'] = $txt['pm_search_title'];
		$context['sub_template'] = 'search_results';
		$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'search';
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=search',
			'name' => $txt['pm_search_bar_title'],
		);
	}

	/**
	 * Read / Set the sort parameters for the results listing
	 */
	private function _setSortParams()
	{
		$sort_columns = array(
			'pm.id_pm',
		);

		if (empty($this->_search_params['sort']) && !empty($this->_req->post->sort))
		{
			list ($this->_search_params['sort'], $this->_search_params['sort_dir']) = array_pad(explode('|', $this->_req->post->sort), 2, '');
		}

		$this->_search_params['sort'] = !empty($this->_search_params['sort']) && in_array($this->_search_params['sort'], $sort_columns) ? $this->_search_params['sort'] : 'pm.id_pm';
		$this->_search_params['sort_dir'] = !empty($this->_search_params['sort_dir']) && $this->_search_params['sort_dir'] === 'asc' ? 'asc' : 'desc';
	}

	/**
	 * Handles the parameters when searching on specific labels
	 *
	 * What it does:
	 *
	 * - Returns the label query for use in the main search query
	 * - Sets the parameters for use in the query
	 *
	 * @return string
	 */
	private function _setLabelQuery()
	{
		global $context;

		$db = database();

		$labelQuery = '';

		if ($context['folder'] === 'inbox' && !empty($this->_search_params['advanced']) && $context['currently_using_labels'])
		{
			// Came here from pagination?  Put them back into $_REQUEST for sanitation.
			if (isset($this->_search_params['labels']))
			{
				$this->_req->post->searchlabel = explode(',', $this->_search_params['labels']);
			}

			// Assuming we have some labels - make them all integers.
			if (!empty($this->_req->post->searchlabel) && is_array($this->_req->post->searchlabel))
			{
				$this->_req->post->searchlabel = array_map('intval', $this->_req->post->searchlabel);
			}
			else
			{
				$this->_req->post->searchlabel = array();
			}

			// Now that everything is cleaned up a bit, make the labels a param.
			$this->_search_params['labels'] = implode(',', $this->_req->post->searchlabel);

			// No labels selected? That must be an error!
			if (empty($this->_req->post->searchlabel))
			{
				$context['search_errors']['no_labels_selected'] = true;
			}
			// Otherwise prepare the query!
			elseif (count($this->_req->post->searchlabel) != count($context['labels']))
			{
				$labelQuery = '
				AND {raw:label_implode}';

				$labelStatements = array();
				foreach ($this->_req->post->searchlabel as $label)
					$labelStatements[] = $db->quote('FIND_IN_SET({string:label}, pmr.labels) != 0', array('label' => $label,));

				$this->_searchq_parameters ['label_implode'] = '(' . implode(' OR ', $labelStatements) . ')';
			}
		}

		return $labelQuery;
	}

	/**
	 * Handles the parameters when searching on specific users
	 *
	 * What it does:
	 *
	 * - Returns the user query for use in the main search query
	 * - Sets the parameters for use in the query
	 *
	 * @return string
	 */
	private function _setUserQuery()
	{
		global $context;

		// Hardcoded variables that can be tweaked if required.
		$maxMembersToSearch = 500;

		// Init to not be searching based on members
		$userQuery = '';

		// If there's no specific user, then don't mention it in the main query.
		if (!empty($this->_search_params['userspec']))
		{
			// Set up so we can search by user name, wildcards, like, etc
			$userString = strtr(\ElkArte\Util::htmlspecialchars($this->_search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
			$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

			preg_match_all('~"([^"]+)"~', $userString, $matches);
			$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

			// Who matches those criteria?
			require_once(SUBSDIR . '/Members.subs.php');
			$members = membersBy('member_names', array('member_names' => $possible_users));

			foreach ($possible_users as $key => $possible_user)
			{
				$this->_searchq_parameters['guest_user_name_implode_' . $key] = '{string_case_insensitive:' . $possible_user . '}';
			}

			// Simply do nothing if there are too many members matching the criteria.
			if (count($members) > $maxMembersToSearch)
			{
				$userQuery = '';
			}
			elseif (count($members) == 0)
			{
				if ($context['folder'] === 'inbox')
				{
					$uq = array();
					$name = '{column_case_insensitive:pm.from_name}';
					foreach (array_keys($possible_users) as $key)
					{
						$uq[] = 'AND pm.id_member_from = 0 AND (' . $name . ' LIKE {string:guest_user_name_implode_' . $key . '})';
					}
					$userQuery = implode(' ', $uq);
					$this->_searchq_parameters['pm_from_name'] = $name;
				}
				else
				{
					$userQuery = '';
				}
			}
			else
			{
				$memberlist = array();
				foreach ($members as $id)
					$memberlist[] = $id;

				// Use the name as as sent from or sent to
				if ($context['folder'] === 'inbox')
				{
					$uq = array();
					$name = '{column_case_insensitive:pm.from_name}';

					foreach (array_keys($possible_users) as $key)
						$uq[] = 'AND (pm.id_member_from IN ({array_int:member_list}) OR (pm.id_member_from = 0 AND (' . $name . ' LIKE {string:guest_user_name_implode_' . $key . '})))';

					$userQuery = implode(' ', $uq);
				}
				else
				{
					$userQuery = 'AND (pmr.id_member IN ({array_int:member_list}))';
				}

				$this->_searchq_parameters['pm_from_name'] = '{column_case_insensitive:pm.from_name}';
				$this->_searchq_parameters['member_list'] = $memberlist;
			}
		}

		return $userQuery;
	}

	/**
	 * Sets the search params for the query
	 *
	 * What it does:
	 *
	 * - Uses existing ones if coming from pagination or uses those passed from the search pm form
	 * - Validates passed params are valid
	 */
	private function _prepareSearchParams()
	{
		// Store whether simple search was used (needed if the user wants to do another query).
		if (!isset($this->_search_params['advanced']))
		{
			$this->_search_params['advanced'] = empty($this->_req->post->advanced) ? 0 : 1;
		}

		// 1 => 'allwords' (default, don't set as param),  2 => 'anywords'.
		if (!empty($this->_search_params['searchtype']) || (!empty($this->_req->post->searchtype) && $this->_req->post->searchtype == 2))
		{
			$this->_search_params['searchtype'] = 2;
		}

		// Minimum age of messages. Default to zero (don't set param in that case).
		if (!empty($this->_search_params['minage']) || (!empty($this->_req->post->minage) && $this->_req->post->minage > 0))
		{
			$this->_search_params['minage'] = !empty($this->_search_params['minage']) ? (int) $this->_search_params['minage'] : (int) $this->_req->post->minage;
		}

		// Maximum age of messages. Default to infinite (9999 days: param not set).
		if (!empty($this->_search_params['maxage']) || (!empty($this->_req->post->maxage) && $this->_req->post->maxage < 9999))
		{
			$this->_search_params['maxage'] = !empty($this->_search_params['maxage']) ? (int) $this->_search_params['maxage'] : (int) $this->_req->post->maxage;
		}

		// Default the user name to a wildcard matching every user (*).
		if (!empty($this->_search_params['userspec']) || (!empty($this->_req->post->userspec) && $this->_req->post->userspec != '*'))
		{
			$this->_search_params['userspec'] = isset($this->_search_params['userspec']) ? $this->_search_params['userspec'] : $this->_req->post->userspec;
		}

		// Search modifiers
		$this->_search_params['subject_only'] = !empty($this->_search_params['subject_only']) || !empty($this->_req->post->subject_only);
		$this->_search_params['show_complete'] = !empty($this->_search_params['show_complete']) || !empty($this->_req->post->show_complete);
		$this->_search_params['sent_only'] = !empty($this->_search_params['sent_only']) || !empty($this->_req->post->sent_only);
	}

	/**
	 * Extract search params from a string
	 *
	 * What it does:
	 *
	 * - When paging search results, reads and decodes the passed parameters
	 * - Places what it finds back in search_params
	 */
	private function _searchParamsFromString()
	{
		$this->_search_params = array();

		if (isset($this->_req->query->params) || isset($this->_req->post->params))
		{
			// Feed it
			$temp_params = isset($this->_req->query->params) ? $this->_req->query->params : $this->_req->post->params;

			// Decode and replace the uri safe characters we added
			$temp_params = base64_decode(str_replace(array('-', '_', '.'), array('+', '/', '='), $temp_params));

			$temp_params = explode('|"|', $temp_params);
			foreach ($temp_params as $i => $data)
			{
				list ($k, $v) = array_pad(explode('|\'|', $data), 2, '');
				$this->_search_params[$k] = $v;
			}
		}

		return $this->_search_params;
	}

	/**
	 * Encodes search params in an URL-compatible way
	 *
	 * @return string - the encoded string to be appended to the URL
	 */
	private function _compileURLparams()
	{
		$encoded = array();

		// Now we have all the parameters, combine them together for pagination and the like...
		foreach ($this->_search_params as $k => $v)
			$encoded[] = $k . '|\'|' . $v;

		// Base64 encode, then replace +/= with uri safe ones that can be reverted
		$encoded = str_replace(array('+', '/', '='), array('-', '_', '.'), base64_encode(implode('|"|', $encoded)));

		return $encoded;
	}

	/**
	 * Allows to search through personal messages.
	 *
	 * What it does:
	 *
	 * - accessed with ?action=pm;sa=search
	 * - shows the screen to search pm's (?action=pm;sa=search)
	 * - uses the search sub template of the PersonalMessage template.
	 * - decodes and loads search parameters given in the URL (if any).
	 * - the form redirects to index.php?action=pm;sa=search2.
	 *
	 * @uses search sub template
	 */
	public function action_search()
	{
		global $context, $txt, $scripturl;

		// If they provided some search parameters, we need to extract them
		if (isset($this->_req->post->params))
		{
			$context['search_params'] = $this->_searchParamsFromString();
		}

		// Set up the search criteria, type, what, age, etc
		if (isset($this->_req->post->search))
		{
			$context['search_params']['search'] = un_htmlspecialchars($this->_req->post->search);
			$context['search_params']['search'] = htmlspecialchars($context['search_params']['search'], ENT_COMPAT, 'UTF-8');
		}

		if (isset($context['search_params']['userspec']))
		{
			$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec'], ENT_COMPAT, 'UTF-8');
		}

		// 1 => 'allwords' / 2 => 'anywords'.
		if (!empty($context['search_params']['searchtype']))
		{
			$context['search_params']['searchtype'] = 2;
		}

		// Minimum and Maximum age of the message
		if (!empty($context['search_params']['minage']))
		{
			$context['search_params']['minage'] = (int) $context['search_params']['minage'];
		}
		if (!empty($context['search_params']['maxage']))
		{
			$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];
		}

		$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);
		$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);

		// Create the array of labels to be searched.
		$context['search_labels'] = array();
		$searchedLabels = isset($context['search_params']['labels']) && $context['search_params']['labels'] != '' ? explode(',', $context['search_params']['labels']) : array();
		foreach ($context['labels'] as $label)
		{
			$context['search_labels'][] = array(
				'id' => $label['id'],
				'name' => $label['name'],
				'checked' => !empty($searchedLabels) ? in_array($label['id'], $searchedLabels) : true,
			);
		}

		// Are all the labels checked?
		$context['check_all'] = empty($searchedLabels) || count($context['search_labels']) == count($searchedLabels);

		// Load the error text strings if there were errors in the search.
		if (!empty($context['search_errors']))
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['search_errors']['messages'] = array();
			foreach ($context['search_errors'] as $search_error => $dummy)
			{
				if ($search_error === 'messages')
				{
					continue;
				}

				$context['search_errors']['messages'][] = $txt['error_' . $search_error];
			}
		}

		$context['page_title'] = $txt['pm_search_title'];
		$context['sub_template'] = 'search';
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=search',
			'name' => $txt['pm_search_bar_title'],
		);
	}

	/**
	 * Allows the user to mark a personal message as unread so they remember to come back to it
	 */
	public function action_markunread()
	{
		global $context;

		checkSession('request');

		$pmsg = !empty($this->_req->query->pmsg) ? (int) $this->_req->query->pmsg : null;

		// Marking a message as unread, we need a message that was sent to them
		// Can't mark your own reply as unread, that would be weird
		if (!is_null($pmsg) && checkPMReceived($pmsg))
		{
			// Make sure this is accessible, should be of course
			if (!isAccessiblePM($pmsg, 'inbox'))
			{
				throw new \ElkArte\Exceptions\Exception('no_access', false);
			}

			// Well then, you get to hear about it all over again
			markMessagesUnread($pmsg);
		}

		// Back to the folder.
		redirectexit($context['current_label_redirect']);
	}

	/**
	 * Used to highlight body text with strings that match the search term
	 *
	 * - Callback function used in $body_highlighted
	 *
	 * @param string[] $matches
	 *
	 * @return string
	 */
	private function _highlighted_callback($matches)
	{
		return isset($matches[2]) && $matches[2] == $matches[1] ? stripslashes($matches[1]) : '<strong class="highlight">' . $matches[1] . '</strong>';
	}
}
