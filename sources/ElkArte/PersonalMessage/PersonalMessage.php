<?php

/**
 * This file is mainly meant for controlling the actions related to personal
 * messages. It allows viewing, sending, deleting, and marking.
 * For compatibility reasons, they are often called "instant messages".
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\PersonalMessage;

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Cache\Cache;
use ElkArte\Errors\ErrorContext;
use ElkArte\Exceptions\ControllerRedirectException;
use ElkArte\Exceptions\Exception;
use ElkArte\Exceptions\PmErrorException;
use ElkArte\Helper\Util;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Languages\Txt;
use ElkArte\MembersList;
use ElkArte\MessagesCallback\BodyParser\Normal;
use ElkArte\MessagesCallback\PmRenderer;
use ElkArte\Profile\Profile;
use ElkArte\Profile\ProfileOptions;
use ElkArte\User;
use ElkArte\VerificationControls\VerificationControlsIntegrate;

/**
 * Class PersonalMessage
 *
 * It allows viewing, sending, deleting, and marking personal messages
 *
 * @package ElkArte\PersonalMessage
 */
class PersonalMessage extends AbstractController
{
	/**
	 * This method is executed before any other in this file (when the class is
	 * loaded by the dispatcher).
	 *
	 * What it does:
	 *
	 * - It sets the context, loads templates and language file(s), as necessary
	 * for the function that will be called.
	 */
	public function pre_dispatch()
	{
		global $txt, $context, $modSettings;

		// No guests!
		is_not_guest();

		// You're not supposed to be here at all if you can't even read PMs.
		isAllowedTo('pm_read');

		// This file contains PM functions such as mark, send, delete
		require_once(SUBSDIR . '/PersonalMessage.subs.php');

		// Templates, language, javascript
		Txt::load('PersonalMessage');
		loadJavascriptFile(['suggest.js', 'PersonalMessage.js']);

		if ($this->getApi() === false)
		{
			theme()->getTemplates()->load('PersonalMessage');
		}

		$this->_events->trigger('pre_dispatch', ['xml' => $this->getApi() !== false]);

		// Load up the members' maximum message capacity.
		$this->_loadMessageLimit();

		// A previous message was sent successfully? show a small indication.
		if ($this->_req->getQuery('done') === 'sent')
		{
			$context['pm_sent'] = true;
		}

		// Load the label counts data.
		if (User::$settings['new_pm'] || !Cache::instance()->getVar($context['labels'], 'labelCounts:' . $this->user->id, 720))
		{
			$this->_loadLabels();

			// Get the message count for each label
			$context['labels'] = loadPMLabels($context['labels']);
		}

		// Now we have the labels, and assuming we have unsorted mail, apply our rules!
		if (User::$settings['new_pm'])
		{
			// Apply our rules to the new PM's
			applyRules();

			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($this->user->id, ['new_pm' => 0]);

			// Turn the new PM's status off, for the popup alert, since they have entered the PM area
			toggleNewPM($this->user->id);
		}

		// This determines if we have more labels than just the standard inbox.
		$context['currently_using_labels'] = count($context['labels']) > 1 ? 1 : 0;

		// Some stuff for the labels...
		$label = $this->_req->getQuery('l', 'intval');
		$folder = $this->_req->getQuery('f', 'trim', '');
		$start = $this->_req->getQuery('start', 'trim');
		$context['current_label_id'] = isset($label, $context['labels'][$label]) ? (int) $label : -1;
		$context['current_label'] = &$context['labels'][$context['current_label_id']]['name'];
		$context['folder'] = $folder !== 'sent' ? 'inbox' : 'sent';

		// This is convenient.  Do you know how annoying it is to do this every time?!
		$context['current_label_redirect'] = 'action=pm;f=' . $context['folder'] . (isset($start) ? ';start=' . $start : '') . (empty($label) ? '' : ';l=' . $label);
		$context['can_issue_warning'] = featureEnabled('w') && allowedTo('issue_warning') && !empty($modSettings['warning_enable']);

		// Build the breadcrumbs for all the actions...
		$context['breadcrumbs'][] = [
			'url' => getUrl('action', ['action' => 'pm']),
			'name' => $txt['personal_messages']
		];

		// Preferences...
		$context['display_mode'] = PmHelper::getDisplayMode();
	}

	/**
	 * Load a members message limit and prepares the limit bar
	 */
	private function _loadMessageLimit(): void
	{
		global $context, $txt;

		$context['message_limit'] = loadMessageLimit();

		// Prepare the context for the capacity bar.
		if (!empty($context['message_limit']))
		{
			$bar = ($this->user->messages * 100) / $context['message_limit'];

			$context['limit_bar'] = [
				'messages' => $this->user->messages,
				'allowed' => $context['message_limit'],
				'percent' => $bar,
				'bar' => min(100, (int) $bar),
				'text' => sprintf($txt['pm_currently_using'], $this->user->messages, round($bar, 1)),
			];
		}
	}

	/**
	 * Loads the user defined label's for use in the template etc.
	 */
	private function _loadLabels(): void
	{
		global $context, $txt;

		$userLabels = explode(',', User::$settings['message_labels'] ?? '');

		foreach ($userLabels as $id_label => $label_name)
		{
			if (empty($label_name))
			{
				continue;
			}

			$context['labels'][$id_label] = [
				'id' => $id_label,
				'name' => trim($label_name),
				'messages' => 0,
				'unread_messages' => 0,
			];
		}

		// The default inbox is always available
		$context['labels'][-1] = [
			'id' => -1,
			'name' => $txt['pm_msg_label_inbox'],
			'messages' => 0,
			'unread_messages' => 0,
		];
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
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		global $context;

		// Finally, all the things we know how to do
		$subActions = [
			'manlabels' => [$this, 'action_manlabels', 'permission' => 'pm_read'],
			'manrules' => [$this, 'action_manrules', 'permission' => 'pm_read'],
			'markunread' => [$this, 'action_markunread', 'permission' => 'pm_read'],
			'pmactions' => [$this, 'action_pmactions', 'permission' => 'pm_read'],
			'prune' => [$this, 'action_prune', 'permission' => 'pm_read'],
			'removeall' => [$this, 'action_removeall', 'permission' => 'pm_read'],
			'removeall2' => [$this, 'action_removeall2', 'permission' => 'pm_read'],
			'report' => [$this, 'action_report', 'permission' => 'pm_read'],
			'search' => [$this, 'action_search', 'permission' => 'pm_read'],
			'search2' => [$this, 'action_search2', 'permission' => 'pm_read'],
			'send' => [$this, 'action_send', 'permission' => 'pm_read'],
			'send2' => [$this, 'action_send2', 'permission' => 'pm_read'],
			'settings' => [$this, 'action_settings', 'permission' => 'pm_read'],
			'inbox' => [$this, 'action_folder', 'permission' => 'pm_read'],
		];

		// Set up our action array
		$action = new Action('pm_index');

		// Known action, go to it, otherwise the inbox for you
		$subAction = $action->initialize($subActions, 'inbox');

		// Set the right index bar for the action
		if ($subAction === 'inbox')
		{
			$this->_messageIndexBar($context['current_label_id'] === -1 ? $context['folder'] : 'label' . $context['current_label_id']);
		}
		elseif ($this->getApi() === false)
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
	 */
	private function _messageIndexBar($area): void
	{
		global $txt, $context;

		require_once(SUBSDIR . '/Menu.subs.php');

		$pm_areas = [
			'folders' => [
				'title' => $txt['pm_messages'],
				'counter' => 'unread_messages',
				'areas' => [
					'inbox' => [
						'label' => $txt['inbox'],
						'custom_url' => getUrl('action', ['action' => 'pm']),
						'counter' => 'unread_messages',
					],
					'send' => [
						'label' => $txt['new_message'],
						'custom_url' => getUrl('action', ['action' => 'pm', 'sa' => 'send']),
						'permission' => 'pm_send',
					],
					'sent' => [
						'label' => $txt['sent_items'],
						'custom_url' => getUrl('action', ['action' => 'pm', 'f' => 'sent']),
					],
				],
			],
			'labels' => [
				'title' => $txt['pm_labels'],
				'counter' => 'labels_unread_total',
				'areas' => [],
			],
			'actions' => [
				'title' => $txt['pm_actions'],
				'areas' => [
					'search' => [
						'label' => $txt['pm_search_bar_title'],
						'custom_url' => getUrl('action', ['action' => 'pm', 'sa' => 'search']),
					],
					'prune' => [
						'label' => $txt['pm_prune'],
						'custom_url' => getUrl('action', ['action' => 'pm', 'sa' => 'prune']),
					],
				],
			],
			'pref' => [
				'title' => $txt['pm_preferences'],
				'areas' => [
					'manlabels' => [
						'label' => $txt['pm_manage_labels'],
						'custom_url' => getUrl('action', ['action' => 'pm', 'sa' => 'manlabels']),
					],
					'manrules' => [
						'label' => $txt['pm_manage_rules'],
						'custom_url' => getUrl('action', ['action' => 'pm', 'sa' => 'manrules']),
					],
					'settings' => [
						'label' => $txt['pm_settings'],
						'custom_url' => getUrl('action', ['action' => 'pm', 'sa' => 'settings']),
					],
				],
			],
		];

		// Handle labels.
		$label_counters = ['unread_messages' => $context['labels'][-1]['unread_messages']];
		if (empty($context['currently_using_labels']))
		{
			unset($pm_areas['labels']);
		}
		else
		{
			// Note we send labels by id as it will have fewer problems in the query string.
			$label_counters['labels_unread_total'] = 0;
			foreach ($context['labels'] as $label)
			{
				if ($label['id'] === -1)
				{
					continue;
				}

				// Count the number of unread items in labels.
				$label_counters['labels_unread_total'] += $label['unread_messages'];

				// Add the label to the menu.
				$pm_areas['labels']['areas']['label' . $label['id']] = [
					'label' => $label['name'],
					'custom_url' => getUrl('action', ['action' => 'pm', 'l' => $label['id']]),
					'counter' => 'label' . $label['id'],
					'messages' => $label['messages'],
				];

				$label_counters['label' . $label['id']] = $label['unread_messages'];
			}
		}

		// Do we have a limit on the number of messages we can keep?
		if (!empty($context['message_limit']))
		{
			$bar = round(($this->user->messages * 100) / $context['message_limit'], 1);

			$context['limit_bar'] = [
				'messages' => $this->user->messages,
				'allowed' => $context['message_limit'],
				'percent' => $bar,
				'bar' => $bar > 100 ? 100 : (int) $bar,
				'text' => sprintf($txt['pm_currently_using'], $this->user->messages, $bar)
			];
		}

		// Set a few options for the menu.
		$menuOptions = [
			'current_area' => $area,
			'hook' => 'pm',
			'disable_url_session_check' => true,
			'counters' => empty($label_counters) ? 0 : $label_counters,
		];

		// Actually create the menu!
		$pm_include_data = createMenu($pm_areas, $menuOptions);
		unset($pm_areas);

		// Make a note of the Unique ID for this menu.
		$context['pm_menu_id'] = $context['max_menu_id'];
		$context['pm_menu_name'] = 'menu_data_' . $context['pm_menu_id'];

		// Set the selected item.
		$context['menu_item_selected'] = $pm_include_data['current_area'];

		// Set the template for this area and add the profile layer.
		if ($this->getApi() === false)
		{
			$template_layers = theme()->getLayers();
			$template_layers->add('pm');
		}
	}

	/**
	 * Display a folder, i.e., inbox/sent etc.
	 *
	 * Display mode: 0 = all at once, 1 = one at a time, 2 = as a conversation
	 *
	 * @throws Exception
	 * @uses subject_list, pm template layers
	 * @uses folder sub template
	 */
	public function action_folder(): void
	{
		global $txt, $scripturl, $modSettings, $context, $subjects_request, $messages_request, $options;

		// Changing view?
		if ($this->_req->isSet('view'))
		{
			$context['display_mode'] = (int) $context['display_mode'] > 1 ? 0 : (int) $context['display_mode'] + 1;
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($this->user->id, ['pm_prefs' => (((int) User::$settings['pm_prefs']) & 252) | $context['display_mode']]);
		}

		// Make sure the starting location is valid.
		$start_raw = $this->_req->getQuery('start', 'trim');
		if ($start_raw !== null && $start_raw !== 'new')
		{
			$start = (int) $start_raw;
		}
		elseif ($start_raw === null && !empty($options['view_newest_pm_first']))
		{
			$start = 0;
		}
		else
		{
			$start = 'new';
		}

		// Set up some basic template stuff.
		$context['from_or_to'] = $context['folder'] !== 'sent' ? 'from' : 'to';
		$context['signature_enabled'] = str_starts_with($modSettings['signature_settings'], '1');
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : [];

		// Set the template layers we need
		$template_layers = theme()->getLayers();
		$template_layers->addAfter('subject_list', 'pm');

		$labelQuery = $context['folder'] !== 'sent' ? '
				AND FIND_IN_SET(' . $context['current_label_id'] . ', pmr.labels) != 0' : '';

		// They didn't pick a sort, so we use the forum by default.
		$sort_by = $this->_req->getQuery('sort', 'trim', 'date');
		$descending = $this->_req->hasQuery('desc');

		// Set our sort by query.
		switch ($sort_by)
		{
			case 'date':
				$sort_by_query = 'pm.id_pm';
				if (!empty($options['view_newest_pm_first']) && !$this->_req->hasQuery('desc') && !$this->_req->hasQuery('asc'))
				{
					$descending = true;
				}

				break;
			case 'name':
				$sort_by_query = "COALESCE(mem.real_name, '')";
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
			$context['breadcrumbs'][] = [
				'url' => getUrl('action', ['action' => 'pm', 'f' => $context['folder']]),
				'name' => $pmbox
			];
		}

		// Build it further if we also have a label.
		if ($context['current_label_id'] !== -1)
		{
			$context['breadcrumbs'][] = [
				'url' => getUrl('action', ['action' => 'pm', 'f' => $context['folder'], 'l' => $context['current_label_id']]),
				'name' => $txt['pm_current_label'] . ': ' . $context['current_label']
			];
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
		if ($this->_req->isSet('pmid'))
		{
			$pmID = $this->_req->getQuery('pmid', 'intval', 0);

			// Make sure you have access to this PM.
			if (!isAccessiblePM($pmID, $context['folder'] === 'sent' ? 'outbox' : 'inbox'))
			{
				throw new Exception('no_access', false);
			}

			$context['current_pm'] = $pmID;

			// With only one page of PM's we're gonna want page 1.
			if ($max_messages <= $modSettings['defaultMaxMessages'])
			{
				$start = 0;
			}
			// If we pass kstart we assume we're in the right place.
			elseif (!$this->_req->isSet('kstart'))
			{
				$start = getPMCount($descending, $pmID, $labelQuery);

				// To stop the page index's being abnormal, start the page on the page the message
				// would normally be located on...
				$start = $modSettings['defaultMaxMessages'] * (int) ($start / $modSettings['defaultMaxMessages']);
			}
		}

		// Sanitize and validate pmsg variable if set.
		if ($this->_req->isSet('pmsg'))
		{
			$pmsg = $this->_req->getQuery('pmsg', 'intval', 0);

			if (!isAccessiblePM($pmsg, $context['folder'] === 'sent' ? 'outbox' : 'inbox'))
			{
				throw new Exception('no_access', false);
			}
		}

		// Determine the navigation context
		$context['links'] += [
			'prev' => $start >= $modSettings['defaultMaxMessages'] ? $scripturl . '?action=pm;start=' . ($start - $modSettings['defaultMaxMessages']) : '',
			'next' => $start + $modSettings['defaultMaxMessages'] < $max_messages ? $scripturl . '?action=pm;start=' . ($start + $modSettings['defaultMaxMessages']) : '',
		];

		// We now know what they want, so let's fetch those PM's
		[$pms, $posters, $recipients, $lastData] = loadPMs([
			'sort_by_query' => $sort_by_query,
			'display_mode' => $context['display_mode'],
			'sort_by' => $sort_by,
			'label_query' => $labelQuery,
			'pmsg' => isset($pmsg) ? (int) $pmsg : 0,
			'descending' => $descending,
			'start' => $start,
			'limit' => $modSettings['defaultMaxMessages'],
			'folder' => $context['folder'],
			'pmid' => $pmID ?? 0,
		], $this->user->id);

		// Make sure that we have been given a correct head pm id if we are in conversation mode
		if ($context['display_mode'] === PmHelper::DISPLAY_AS_CONVERSATION && !empty($pmID) && $pmID != $lastData['id'])
		{
			throw new Exception('no_access', false);
		}

		// If loadPMs returned results, let's show the pm subject list
		if (!empty($pms))
		{
			// Tell the template if no pm has specifically been selected
			if (empty($pmID))
			{
				$context['current_pm'] = 0;
			}

			$display_pms = $context['display_mode'] === PmHelper::DISPLAY_ALL_AT_ONCE ? $pms : [$lastData['id']];

			// At this point we know the main id_pm's. But if we are looking at conversations, we need
			// the PMs that make up the conversation
			if ($context['display_mode'] === PmHelper::DISPLAY_AS_CONVERSATION)
			{
				[$display_pms, $posters] = loadConversationList($lastData['head'], $recipients, $context['folder']);

				// Conversation list may expose additional PM's being displayed
				$all_pms = array_unique(array_merge($pms, $display_pms));

				// See if any of these 'listing' PMs are in a conversation thread that has unread entries
				$context['conversation_unread'] = loadConversationUnreadStatus($all_pms);
			}
			// This is pretty much EVERY pm!
			else
			{
				$all_pms = array_unique(array_merge($pms, $display_pms));
			}

			// Get recipients (don't include bcc-recipients for your inbox, you're not supposed to know :P).
			[$context['message_labels'], $context['message_replied'], $context['message_unread']] = loadPMRecipientInfo($all_pms, $recipients, $context['folder']);

			// Make sure we don't load any unnecessary data for one at a time mode
			if ($context['display_mode'] === PmHelper::DISPLAY_ONE_AT_TIME)
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
				MembersList::load($posters);
			}

			// Always build the subject list request when we have PMs, so the subject list
			// renders in all modes (0: all-at-once, 1: one-at-a-time, 2: conversation).
			// This also prevents sharing a DB result between subject and message renderers.
			// Get the order right.
			$orderBy = [];
			foreach (array_reverse($pms) as $pm)
			{
				$orderBy[] = 'pm.id_pm = ' . $pm;
			}

			// Separate query for these bits, the callback will use it as required
			$subjects_request = loadPMSubjectRequest($pms, $orderBy);

			// Execute the load message query if a message has been chosen and let
			// the callback fetch the results.  Otherwise, just show the pm selection list
			if (empty($pmsg) && empty($pmID) && $context['display_mode'] !== PmHelper::DISPLAY_ALL_AT_ONCE)
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

		// Initialize the subject and message render callbacks
		$bodyParser = new Normal([], false);
		$opt = new ValuesContainer(['recipients' => $recipients]);
		$renderer = new PmRenderer($messages_request, $this->user, $bodyParser, $opt);
		$subject_renderer = new PmRenderer($subjects_request ?? $messages_request, $this->user, $bodyParser, $opt);

		// Subject and Message
		$context['get_pmessage'] = [$renderer, 'getContext'];
		$context['get_psubject'] = [$subject_renderer, 'getContext'];

		// Prepare some items for the template
		$context['topic_starter_id'] = 0;
		$context['can_send_pm'] = allowedTo('pm_send');
		$context['can_send_email'] = allowedTo('send_email_to_members');
		$context['sub_template'] = 'folder';
		$context['page_title'] = $txt['pm_inbox'];
		$context['sort_direction'] = $descending ? 'down' : 'up';
		$context['sort_by'] = $sort_by;

		if ($messages_request !== false && !empty($context['show_delete']) && $messages_request->hasResults())
		{
			theme()->getLayers()->addEnd('pm_pages_and_buttons');
		}

		// Set up the page index.
		$label_for_index = $this->_req->getQuery('l', 'intval');
		$context['page_index'] = constructPageIndex('{scripturl}?action=pm;f=' . $context['folder'] . ($label_for_index !== null ? ';l=' . (int) $label_for_index : '') . ';sort=' . $context['sort_by'] . ($descending ? ';desc' : ''), $start, $max_messages, $modSettings['defaultMaxMessages']);
		$context['start'] = $start;

		$context['pm_form_url'] = $scripturl . '?action=pm;sa=pmactions;' . ($context['display_mode'] === PmHelper::DISPLAY_AS_CONVERSATION ? 'conversation;' : '') . 'f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] !== -1 ? ';l=' . $context['current_label_id'] : '');

		// Finally, mark the relevant messages as read.
		if ($context['folder'] !== 'sent' && !empty($context['labels'][(int) $context['current_label_id']]['unread_messages']))
		{
			// If the display mode is "old sk00l" do them all...
			if ($context['display_mode'] === PmHelper::DISPLAY_ALL_AT_ONCE)
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
		if ($context['display_mode'] === PmHelper::DISPLAY_AS_CONVERSATION && !empty($context['current_pm']))
		{
			$context['conversation_buttons'] = [
				'delete' => [
					'text' => 'delete_conversation',
					'lang' => true,
					'url' => $scripturl . '?action=pm;sa=pmactions;pm_actions%5B' . $context['current_pm'] . '%5D=delete;conversation;f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] !== -1 ? ';l=' . $context['current_label_id'] : '') . ';' . $context['session_var'] . '=' . $context['session_id'],
					'custom' => 'onclick="return confirm(\'' . addslashes($txt['remove_message']) . '?\');"'
				],
			];

			// Allow mods to add additional buttons here
			call_integration_hook('integrate_conversation_buttons');
		}
	}

	/**
	 * Send a new personal message?
	 *
	 * @throws Exception pm_not_yours
	 */
	public function action_send(): void
	{
		global $txt, $modSettings, $context;

		// Load in some text and template dependencies
		Txt::load('PersonalMessage');
		theme()->getTemplates()->load('PersonalMessage');

		// Set the template we will use
		$context['sub_template'] = 'send';

		// Extract out the spam settings - cause it's neat.
		[$modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']] = explode(',', $modSettings['pm_spam_settings']);

		// Set up some items for the template
		$context['page_title'] = $txt['send_message'];
		$context['reply'] = $this->_req->hasQuery('pmsg') || $this->_req->hasQuery('quote');

		// Check whether we've gone over the limit of messages we can send per hour.
		if (!empty($modSettings['pm_posts_per_hour'])
			&& !allowedTo(['admin_forum', 'moderate_forum', 'send_mail'])
			&& $this->user->mod_cache['bq'] === '0=1'
			&& $this->user->mod_cache['gq'] === '0=1')
		{
			// How many messages did they send this last hour?
			$pmCount = pmCount($this->user->id, 3600);

			if (!empty($pmCount) && $pmCount >= $modSettings['pm_posts_per_hour'])
			{
				throw new Exception('pm_too_many_per_hour', true, [$modSettings['pm_posts_per_hour']]);
			}
		}

		try
		{
			$pmsg_event = $this->_req->getQuery('pmsg', 'intval');
			$pmsg_event_quote = $this->_req->getQuery('quote', 'trim', '');
			if ($pmsg_event !== null)
			{
				$this->_events->trigger('before_set_context', ['pmsg' => $pmsg_event, 'quote' => $pmsg_event_quote]);
			}
		}
		catch (PmErrorException $pmErrorException)
		{
			$this->messagePostError($pmErrorException->namedRecipientList, $pmErrorException->recipientList, $pmErrorException->msgOptions);
			return;
		}

		// Quoting / Replying to a message?
		if ($this->_req->hasQuery('pmsg'))
		{
			$pmsg = $this->_req->getQuery('pmsg', 'intval');

			// Make sure this is accessible (not deleted)
			if (!isAccessiblePM($pmsg))
			{
				throw new Exception('no_access', false);
			}

			// Validate that this is one has been received?
			$isReceived = checkPMReceived($pmsg);

			// Get the quoted message (and make sure you're allowed to see this quote!).
			$row_quoted = loadPMQuote($pmsg, $isReceived);
			if ($row_quoted === false)
			{
				throw new Exception('pm_not_yours', false);
			}

			// Censor the message.
			$row_quoted['subject'] = censor($row_quoted['subject']);
			$row_quoted['body'] = censor($row_quoted['body']);

			// Let's make sure we mark this one as read
			markMessages($pmsg);

			// Figure out which flavor or 'Re: ' to use
			$context['response_prefix'] = response_prefix();

			$form_subject = $row_quoted['subject'];

			// Add 'Re: ' to it....
			if ($context['reply'] && trim($context['response_prefix']) !== '' && Util::strpos($form_subject, trim($context['response_prefix'])) !== 0)
			{
				$form_subject = $context['response_prefix'] . $form_subject;
			}

			// If quoting, let's clean up some things and set the quote header for the pm body
			if ($this->_req->hasQuery('quote'))
			{
				// Remove any nested quotes and <br />...
				$form_message = preg_replace('~<br ?/?>~i', "\n", $row_quoted['body']);
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

			// Allow them to QQ the message they are replying to
			loadJavascriptFile('quickQuote.js', ['defer' => true]);
			theme()->addInlineJavascript("
				document.addEventListener('DOMContentLoaded', () => new Elk_QuickQuote(), false);", true
			);

			// Do the BBC thang on the message.
			$bbc_parser = ParserWrapper::instance();
			$row_quoted['body'] = $bbc_parser->parsePM($row_quoted['body']);

			// Set up the quoted message array.
			$context['quoted_message'] = [
				'id' => $row_quoted['id_pm'],
				'pm_head' => $row_quoted['pm_head'],
				'member' => [
					'name' => $row_quoted['real_name'],
					'username' => $row_quoted['member_name'],
					'id' => $row_quoted['id_member'],
					'href' => empty($row_quoted['id_member']) ? '' : getUrl('profile', ['action' => 'profile', 'u' => $row_quoted['id_member']]),
					'link' => empty($row_quoted['id_member']) ? $row_quoted['real_name'] : '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $row_quoted['id_member']]) . '">' . $row_quoted['real_name'] . '</a>',
				],
				'subject' => $row_quoted['subject'],
				'time' => standardTime($row_quoted['msgtime']),
				'html_time' => htmlTime($row_quoted['msgtime']),
				'timestamp' => forum_time(true, $row_quoted['msgtime']),
				'body' => $row_quoted['body']
			];
		}
		// A new message it is then
		else
		{
			$context['quoted_message'] = false;
			$form_subject = '';
			$form_message = '';
		}

		// Start of like we don't know where this is going
		$context['recipients'] = [
			'to' => [],
			'bcc' => [],
		];

		// Sending by ID?  Replying to all?  Fetch the real_name(s).
		if ($this->_req->hasQuery('u'))
		{
			// If the user is replying to all, get all the other members this was sent to.
			$u_param = $this->_req->getQuery('u', 'trim|strval', '');
			if ($u_param === 'all' && isset($row_quoted))
			{
				// Firstly, to reply to all, we clearly already have $row_quoted - so have the original member from.
				if ($row_quoted['id_member'] != $this->user->id)
				{
					$context['recipients']['to'][] = [
						'id' => $row_quoted['id_member'],
						'name' => htmlspecialchars($row_quoted['real_name'], ENT_COMPAT),
					];
				}

				// Now to get all the others.
				$context['recipients']['to'] = array_merge($context['recipients']['to'], isset($pmsg) ? loadPMRecipientsAll($pmsg) : []);
			}
			else
			{
				$users_csv = $u_param;
				$users = $users_csv === '' ? [] : array_map('intval', explode(',', $users_csv));
				$users = array_unique($users);

				// For all the member's this is going to get their display name.
				require_once(SUBSDIR . '/Members.subs.php');
				$result = getBasicMemberData($users);

				foreach ($result as $row)
				{
					$context['recipients']['to'][] = [
						'id' => $row['id_member'],
						'name' => $row['real_name'],
					];
				}
			}

			// Get a literal name list in case the user has JavaScript disabled.
			$names = [];
			foreach ($context['recipients']['to'] as $to)
			{
				$names[] = $to['name'];
			}

			$context['to_value'] = empty($names) ? '' : '&quot;' . implode('&quot;, &quot;', $names) . '&quot;';
		}
		else
		{
			$context['to_value'] = '';
		}

		// Set the defaults...
		$context['subject'] = $form_subject;
		$context['message'] = str_replace(['"', '<', '>', '&nbsp;'], ['&quot;', '&lt;', '&gt;', ' '], $form_message);

		// And build the link tree.
		$context['breadcrumbs'][] = [
			'url' => getUrl('action', ['action' => 'pm', 'sa' => 'send']),
			'name' => $txt['new_message']
		];

		// Needed for the editor.
		require_once(SUBSDIR . '/Editor.subs.php');

		// Now create the editor.
		$editorOptions = [
			'id' => 'message',
			'value' => $context['message'],
			'height' => '250px',
			'width' => '100%',
			'labels' => [
				'post_button' => $txt['send_message'],
			],
			'smiley_container' => 'smileyBox_message',
			'bbc_container' => 'bbcBox_message',
			'preview_type' => 2,
		];

		// Trigger the prepare_send_context PM event
		$this->_events->trigger('prepare_send_context', ['editorOptions' => &$editorOptions]);

		create_control_richedit($editorOptions);

		// No one is bcc'ed just yet
		$context['bcc_value'] = '';

		// Register this form and get a sequence number in $context.
		checkSubmitOnce('register');
	}

	/**
	 * An error in the message...
	 *
	 * @param array $named_recipients
	 * @param array $recipient_ids array keys of [bbc] => int[] and [to] => int[]
	 * @param object $msg_options body, subject, and reply values
	 *
	 * @throws Exception pm_not_yours
	 */
	public function messagePostError($named_recipients, $recipient_ids = [], $msg_options = null): void
	{
		global $txt, $context, $modSettings;

		if ($this->getApi() !== false)
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
		$context['recipients'] = [
			'to' => [],
			'bcc' => [],
		];

		if (!empty($recipient_ids['to']) || !empty($recipient_ids['bcc']))
		{
			$allRecipients = array_merge($recipient_ids['to'], $recipient_ids['bcc']);

			require_once(SUBSDIR . '/Members.subs.php');

			// Get the latest activated member's display name.
			$result = getBasicMemberData($allRecipients);
			foreach ($result as $row)
			{
				$recipientType = in_array($row['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';
				$context['recipients'][$recipientType][] = [
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				];
			}
		}

		// Set everything up like before...
		if (!empty($msg_options))
		{
			$context['subject'] = $msg_options->subject;
			$context['message'] = $msg_options->body;
			$context['reply'] = $msg_options->reply_to;
		}
		else
		{
			$subject_in = $this->_req->getPost('subject', 'trim|Util::htmlspecialchars', '');
			$message_in = $this->_req->getPost('message', 'trim|strval|cleanhtml', '');
			$context['subject'] = $subject_in;
			$context['message'] = str_replace(['  '], ['&nbsp; '], $message_in);
			$context['reply'] = $this->_req->getPost('replied_to', 'intval', 0) > 0;
		}

		// If this is a reply to a message, we need to reload the quote
		if ($context['reply'])
		{
			$pmsg = $this->_req->getPost('replied_to', 'intval', 0);
			$isReceived = $context['folder'] !== 'sent';
			$row_quoted = loadPMQuote($pmsg, $isReceived);
			if ($row_quoted === false)
			{
				if ($this->getApi() === false)
				{
					throw new Exception('pm_not_yours', false);
				}

				$error_types->addError('pm_not_yours');
			}
			else
			{
				$row_quoted['subject'] = censor($row_quoted['subject']);
				$row_quoted['body'] = censor($row_quoted['body']);
				$bbc_parser = ParserWrapper::instance();

				$context['quoted_message'] = [
					'id' => $row_quoted['id_pm'],
					'pm_head' => $row_quoted['pm_head'],
					'member' => [
						'name' => $row_quoted['real_name'],
						'username' => $row_quoted['member_name'],
						'id' => $row_quoted['id_member'],
						'href' => empty($row_quoted['id_member']) ? '' : getUrl('profile', ['action' => 'profile', 'u' => $row_quoted['id_member']]),
						'link' => empty($row_quoted['id_member']) ? $row_quoted['real_name'] : '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $row_quoted['id_member']]) . '">' . $row_quoted['real_name'] . '</a>',
					],
					'subject' => $row_quoted['subject'],
					'time' => standardTime($row_quoted['msgtime']),
					'html_time' => htmlTime($row_quoted['msgtime']),
					'timestamp' => forum_time(true, $row_quoted['msgtime']),
					'body' => $bbc_parser->parsePM($row_quoted['body']),
				];
			}
		}

		// Build the link tree...
		$context['breadcrumbs'][] = [
			'url' => getUrl('action', ['action' => 'pm', 'sa' => 'send']),
			'name' => $txt['new_message']
		];

		// Set each of the errors for the template.
		$context['post_error'] = [
			'errors' => $error_types->prepareErrors(),
			'type' => $error_types->getErrorType() == 0 ? 'minor' : 'serious',
			'title' => $txt['error_while_submitting'],
		];

		// We need to load the editor once more.
		require_once(SUBSDIR . '/Editor.subs.php');

		// Create it...
		$editorOptions = [
			'id' => 'message',
			'value' => $context['message'],
			'width' => '100%',
			'height' => '250px',
			'labels' => [
				'post_button' => $txt['send_message'],
			],
			'smiley_container' => 'smileyBox_message',
			'bbc_container' => 'bbcBox_message',
			'preview_type' => 2,
		];

		// Trigger the prepare_send_context PM event
		$this->_events->trigger('prepare_send_context', ['editorOptions' => &$editorOptions]);

		create_control_richedit($editorOptions);

		// Check whether we need to show the code again.
		$context['require_verification'] = $this->user->is_admin === false && !empty($modSettings['pm_posts_verification']) && $this->user->posts < $modSettings['pm_posts_verification'];
		if ($context['require_verification'] && $this->getApi() === false)
		{
			$verificationOptions = [
				'id' => 'pm',
			];
			$context['require_verification'] = VerificationControlsIntegrate::create($verificationOptions);
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
	 * Send a personal message.
 	 */
	public function action_send2()
	{
		global $txt, $context, $modSettings;

		// All the helpers we need
		require_once(SUBSDIR . '/Auth.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		Txt::load('PersonalMessage', false);

		// Extract out the spam settings - it saves database space!
		[$modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']] = explode(',', $modSettings['pm_spam_settings']);

		// Initialize the errors we're about to make.
		$post_errors = ErrorContext::context('pm', 1);

		// Check whether we've gone over the limit of messages we can send per hour - fatal error if fails!
		if (!empty($modSettings['pm_posts_per_hour'])
			&& !allowedTo(['admin_forum', 'moderate_forum', 'send_mail'])
			&& $this->user->mod_cache['bq'] === '0=1'
			&& $this->user->mod_cache['gq'] === '0=1')
		{
			// How many they sent this last hour?
			$pmCount = pmCount($this->user->id, 3600);

			if (!empty($pmCount) && $pmCount >= $modSettings['pm_posts_per_hour'])
			{
				if ($this->getApi() === false)
				{
					throw new Exception('pm_too_many_per_hour', true, [$modSettings['pm_posts_per_hour']]);
				}

				$post_errors->addError('pm_too_many_per_hour');
			}
		}

		// If your session timed out, show an error, but do allow to re-submit.
		if ($this->getApi() === false && checkSession('post', '', false) !== '')
		{
			$post_errors->addError('session_timeout');
		}

		// Local sanitized copies used for preview/sending
		$subject = $this->_req->getPost('subject', 'trim|strval', '');
		$subject = strtr(Util::htmltrim($subject), ["\r" => '', "\n" => '', "\t" => '']);
		$message = $this->_req->getPost('message', 'trim|strval', '');

		$this->_req->post->to = $this->_req->getPost('to', 'trim', empty($this->_req->query->to) ? '' : $this->_req->query->to);
		$this->_req->post->bcc = $this->_req->getPost('bcc', 'trim', empty($this->_req->query->bcc) ? '' : $this->_req->query->bcc);

		// Route the input from the 'u' parameter to the 'to'-list.
		if (!empty($this->_req->post->u))
		{
			$this->_req->post->recipient_to = explode(',', $this->_req->post->u);
		}

		$bbc_parser = ParserWrapper::instance();

		// Construct the list of recipients.
		$recipientList = [];
		$namedRecipientList = [];
		$namesNotFound = [];
		foreach (['to', 'bcc'] as $recipientType)
		{
			// First, let's see if there's user ID's given.
			$recipientList[$recipientType] = [];
			$type = 'recipient_' . $recipientType;
			if (!empty($this->_req->post->{$type}) && is_array($this->_req->post->{$type}))
			{
				$recipientList[$recipientType] = array_map('intval', $this->_req->post->{$type});
			}

			// Are there also literal names set?
			if (!empty($this->_req->post->{$recipientType}))
			{
				// We're going to take out the "s anyway ;).
				$recipientString = strtr($this->_req->post->{$recipientType}, ['\\"' => '"']);

				preg_match_all('~"([^"]+)"~', $recipientString, $matches);
				$namedRecipientList[$recipientType] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $recipientString))));

				// Clean any literal names entered
				foreach ($namedRecipientList[$recipientType] as $index => $recipient)
				{
					if (trim($recipient) !== '')
					{
						$namedRecipientList[$recipientType][$index] = Util::htmlspecialchars(Util::strtolower(trim($recipient)));
					}
					else
					{
						unset($namedRecipientList[$recipientType][$index]);
					}
				}

				// Now see if we can resolve any entered name (not suggest selected) to an actual user
				if (!empty($namedRecipientList[$recipientType]))
				{
					$foundMembers = findMembers($namedRecipientList[$recipientType]);

					// Assume all are not found until proven otherwise.
					$namesNotFound[$recipientType] = $namedRecipientList[$recipientType];

					// Make sure we only have each member listed once, in case they did not use the select list
					foreach ($foundMembers as $member)
					{
						$testNames = [
							Util::strtolower($member['username']),
							Util::strtolower($member['name']),
							Util::strtolower($member['email']),
						];

						if (array_intersect($testNames, $namedRecipientList[$recipientType]) !== [])
						{
							$recipientList[$recipientType][] = $member['id'];

							// Get rid of this username, since we found it.
							$namesNotFound[$recipientType] = array_diff($namesNotFound[$recipientType], $testNames);
						}
					}
				}
			}

			// Selected a recipient to be deleted? Remove them now.
			$delete_recipient = $this->_req->getPost('delete_recipient', 'intval', 0);
			if (!empty($delete_recipient))
			{
				$recipientList[$recipientType] = array_diff($recipientList[$recipientType], [$delete_recipient]);
			}

			// Make sure we don't include the same name twice
			$recipientList[$recipientType] = array_unique($recipientList[$recipientType]);
		}

		// Are we changing the recipients somehow?
		$is_recipient_change = $this->_req->hasPost('delete_recipient') || $this->_req->hasPost('to_submit') || $this->_req->hasPost('bcc_submit');

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
					{
						$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
					}
				}
			}
		}

		// Did they make any mistakes like no subject or message?
		if ($subject === '')
		{
			$post_errors->addError('no_subject');
		}

		if ($message === '')
		{
			$post_errors->addError('no_message');
		}
		elseif (!empty($modSettings['max_messageLength']) && Util::strlen($message) > $modSettings['max_messageLength'])
		{
			$post_errors->addError('long_message');
		}
		else
		{
			// Preparse the message.
			preparsecode($message);

			// Make sure there's still some content left without the tags.
			if (Util::htmltrim(strip_tags($bbc_parser->parsePM(Util::htmlspecialchars($message, ENT_QUOTES)), '<img>')) === ''
				&& (!allowedTo('admin_forum') || !str_contains($message, '[html]')))
			{
				$post_errors->addError('no_message');
			}
		}

		// If they made any errors, give them a chance to make amends.
		if ($post_errors->hasErrors()
			&& !$is_recipient_change
			&& !$this->_req->isSet('preview')
			&& $this->getApi() === false)
		{
			$this->messagePostError($namedRecipientList, $recipientList);

			return false;
		}

		// Want to take a second glance before you send?
		if ($this->_req->isSet('preview'))
		{
			// Set everything up to be displayed.
			$context['preview_subject'] = Util::htmlspecialchars($this->_req->getPost('subject', 'trim|strval', ''));
			$context['preview_message'] = Util::htmlspecialchars($this->_req->getPost('message', 'trim|strval',''),ENT_QUOTES, 'UTF-8', true);
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

		if ($is_recipient_change)
		{
			// Maybe we couldn't find one?
			foreach ($namesNotFound as $recipientType => $names)
			{
				$post_errors->addError('bad_' . $recipientType);
				foreach ($names as $name)
				{
					$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
				}
			}

			$this->messagePostError($namedRecipientList, $recipientList);

			return true;
		}

		// Adding a recipient cause JavaScript ain't working?
		try
		{
			$this->_events->trigger('before_sending', ['namedRecipientList' => $namedRecipientList, 'recipientList' => $recipientList, 'namesNotFound' => $namesNotFound, 'post_errors' => $post_errors]);
		}
		catch (ControllerRedirectException)
		{
			$this->messagePostError($namedRecipientList, $recipientList);

			return true;
		}

		// Safety net, it may be a module may just add to the list of errors without actually throw the error
		if ($post_errors->hasErrors() && !$this->_req->isSet('preview') && $this->getApi() === false)
		{
			$this->messagePostError($namedRecipientList, $recipientList);

			return false;
		}

		// Before we send the PM, let's make sure we don't have an abuse of numbers.
		if (!empty($modSettings['max_pm_recipients']) && count($recipientList['to']) + count($recipientList['bcc']) > $modSettings['max_pm_recipients'] && !allowedTo(['moderate_forum', 'send_mail', 'admin_forum']))
		{
			$context['send_log'] = [
				'sent' => [],
				'failed' => [sprintf($txt['pm_too_many_recipients'], $modSettings['max_pm_recipients'])],
			];

			$this->messagePostError($namedRecipientList, $recipientList);

			return false;
		}

		// Protect from message spamming.
		spamProtection('pm');

		// Prevent double submission of this form.
		checkSubmitOnce('check');

		// Finally, do the actual sending of the PM.
		if (!empty($recipientList['to']) || !empty($recipientList['bcc']))
		{
			// Reset the message to pre-check condition, sendpm will do the rest.
			$subject = $this->_req->getPost('subject', 'trim|strval', '');
			$message = $this->_req->getPost('message', 'trim|strval', '');
			$context['send_log'] = sendpm($recipientList, $subject, $message, true, null, empty($this->_req->post->pm_head) ? 0 : (int) $this->_req->post->pm_head);
		}
		else
		{
			$context['send_log'] = [
				'sent' => [],
				'failed' => []
			];
		}

		// Mark the message as "replied to".
		$replied_to = $this->_req->getPost('replied_to', 'intval', 0);
		$box = $this->_req->getPost('f', 'trim', '');
		if (!empty($context['send_log']['sent']) && !empty($replied_to) && $box === 'inbox')
		{
			require_once(SUBSDIR . '/PersonalMessage.subs.php');
			setPMRepliedStatus($this->user->id, $replied_to);
		}

		$failed = !empty($context['send_log']['failed']);
		$this->_events->trigger('message_sent', ['failed' => $failed]);

		// If one or more of the recipients were invalid, go back to the post screen with the failed usernames.
		if ($failed)
		{
			$this->messagePostError($namesNotFound, [
				'to' => array_intersect($recipientList['to'], $context['send_log']['failed']),
				'bcc' => array_intersect($recipientList['bcc'], $context['send_log']['failed'])
			]);

			return false;
		}

		// Message sent successfully
		$context['current_label_redirect'] .= ';done=sent';

		// Go back to where they sent from, if possible...
		redirectexit($context['current_label_redirect']);
	}

	/**
	 * This function performs all additional actions including the deleting
	 * and labeling of PM's
	 */
	public function action_pmactions(): void
	{
		global $context;

		checkSession('request');

		// Sending in the single pm choice via GET
		$pm_actions = $this->_req->getQuery('pm_actions', null, '');

		// Set the action to apply to the PMs defined by pm_actions (yes, it is that brilliant)
		$pm_action = $this->_req->getPost('pm_action', 'trim', '');
		$pm_action = empty($pm_action) && $this->_req->hasPost('del_selected') ? 'delete' : $pm_action;

		// Create a list of PMs that we need to work on
		$pms_list = $this->_req->getPost('pms', null, []);
		if ($pm_action !== ''
			&& !empty($pms_list)
			&& is_array($pms_list))
		{
			$pm_actions = [];
			foreach ($pms_list as $pm)
			{
				$pm_actions[(int) $pm] = $pm_action;
			}
		}

		// No messages to action then bug out
		if (empty($pm_actions))
		{
			redirectexit($context['current_label_redirect']);
		}

		// If we are in conversation, we may need to apply this to every message in that conversation.
		if ($context['display_mode'] === PmHelper::DISPLAY_AS_CONVERSATION && $this->_req->hasQuery('conversation'))
		{
			$id_pms = array_map('intval', array_keys($pm_actions));
			$pm_heads = getDiscussions($id_pms);
			$pms = getPmsFromDiscussion(array_keys($pm_heads));

			// Copy the action from the single to PM to the others in the conversation.
			foreach ($pms as $id_pm => $id_head)
			{
				if (isset($pm_heads[$id_head], $pm_actions[$pm_heads[$id_head]]))
				{
					$pm_actions[$id_pm] = $pm_actions[$pm_heads[$id_head]];
				}
			}
		}

		// Get to doing what we've been told
		$to_delete = [];
		$to_label = [];
		$label_type = [];
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

			if ((int) $action === -1 || (int) $action === 0 || (int) $action > 0)
			{
				$to_label[(int) $pm] = (int) $action;
				$label_type[(int) $pm] = $type ?? '';
			}
		}

		// Deleting, it looks like?
		if (!empty($to_delete))
		{
			deleteMessages($to_delete, $context['display_mode'] === PmHelper::DISPLAY_AS_CONVERSATION ? null : $context['folder']);
		}

		// Are we labeling anything?
		if (!empty($to_label) && $context['folder'] === 'inbox')
		{
			$updateErrors = changePMLabels($to_label, $label_type, $this->user->id);

			// Any errors?
			if (!empty($updateErrors))
			{
				throw new Exception('labels_too_many', false, [$updateErrors]);
			}
		}

		// Back to the folder.
		$_SESSION['pm_selected'] = array_keys($to_label);
		redirectexit($context['current_label_redirect'] . (count($to_label) === 1 ? '#msg_' . $_SESSION['pm_selected'][0] : ''));
	}

	/**
	 * Are you sure you want to PERMANENTLY (mostly) delete ALL your messages?
	 */
	public function action_removeall(): void
	{
		global $txt, $context;

		// Only have to set up the template...
		$context['sub_template'] = 'ask_delete';
		$context['page_title'] = $txt['delete_all'];
		$folder_flag = $this->_req->getQuery('f', 'trim|strval', '');
		$context['delete_all'] = $folder_flag === 'all';

		// And set the folder name...
		$txt['delete_all'] = str_replace('PMBOX', $context['folder'] != 'sent' ? $txt['inbox'] : $txt['sent_items'], $txt['delete_all']);
	}

	/**
	 * Delete ALL the messages!
	 */
	public function action_removeall2(): void
	{
		global $context;

		checkSession('get');

		// If all, then delete all messages the user has.
		$folder_flag = $this->_req->getQuery('f', 'trim|strval', '');
		if ($folder_flag === 'all')
		{
			deleteMessages(null);
		}
		// Otherwise just the selected folder.
		else
		{
			deleteMessages(null, $folder_flag !== 'sent' ? 'inbox' : 'sent');
		}

		// Done... all gone.
		redirectexit($context['current_label_redirect']);
	}

	/**
	 * This function allows the user to prune (delete) all messages older than a supplied duration.
	 */
	public function action_prune(): void
	{
		global $txt, $context;

		// Actually delete the messages.
		if ($this->_req->hasPost('age'))
		{
			checkSession();

			// Calculate the time to delete before.
			$age_days = $this->_req->getPost('age', 'intval', 0);
			$deleteTime = max(0, time() - (86400 * $age_days));

			// Select all the messages older than $deleteTime.
			$toDelete = getPMsOlderThan($this->user->id, $deleteTime);

			// Delete the actual messages.
			deleteMessages($toDelete);

			// Go back to their inbox.
			redirectexit($context['current_label_redirect']);
		}

		// Build the link tree elements.
		$context['breadcrumbs'][] = [
			'url' => getUrl('action', ['action' => 'pm', 'sa' => 'prune']),
			'name' => $txt['pm_prune']
		];
		$context['sub_template'] = 'prune';
		$context['page_title'] = $txt['pm_prune'];
	}

	/**
	 * This function handles adding, deleting, and editing labels on messages.
	 */
	public function action_manlabels(): void
	{
		$controller = new Labels($this->_events);
		$controller->setUser($this->user);
		$controller->action_manlabels();
	}

	/**
	 * Allows editing Personal Message Settings.
	 *
	 * @uses ProfileOptions controller. (@todo refactor this.)
	 * @uses Profile template.
	 * @uses Profile language file.
	 */
	public function action_settings(): void
	{
		$controller = new Settings($this->_events);
		$controller->setUser($this->user);
		$controller->action_settings();
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
	public function action_report(): void
	{
		$controller = new Report($this->_events);
		$controller->setUser($this->user);
		$controller->action_report();
	}

	/**
	 * List and allow adding/entering all man rules
	 *
	 * @uses sub template rules
	 */
	public function action_manrules(): void
	{
		$controller = new Rules($this->_events);
		$controller->setUser($this->user);
		$controller->action_manrules();
	}

	/**
	 * Actually do the search of personal messages and show the results
	 *
	 * What it does:
	 *
	 * - Accessed with ?action=pm;sa=search2
	 * - Checks user input and searches the pm table for messages matching the query.
	 * - Uses the search_results sub template of the PersonalMessage template.
	 * - Show the results of the search query.
	 */
	public function action_search2(): ?bool
	{
		$controller = new Search($this->_events);
		$controller->setUser($this->user);

		return $controller->action_search2();
	}


	/**
	 * Allows searching personal messages.
	 *
	 * What it does:
	 *
	 * - Accessed with ?action=pm;sa=search
	 * - Shows the screen to search PMs (?action=pm;sa=search)
	 * - Uses the search sub template of the PersonalMessage template.
	 * - Decodes and loads search parameters given in the URL (if any).
	 * - The form redirects to index.php?action=pm;sa=search2.
	 *
	 * @uses search sub template
	 */
	public function action_search(): void
	{
		$controller = new Search($this->_events);
		$controller->setUser($this->user);
		$controller->action_search();
	}


	/**
	 * Allows the user to mark a personal message as unread, so they remember to come back to it
	 */
	public function action_markunread(): void
	{
		global $context;

		checkSession('request');

		$pmsg = $this->_req->getQuery('pmsg', 'intval');

		// Marking a message as unread, we need a message that was sent to them
		// Can't mark your own reply as unread, that would be weird
		if (!is_null($pmsg) && checkPMReceived($pmsg))
		{
			// Make sure this is accessible, should be, of course
			if (!isAccessiblePM($pmsg, 'inbox'))
			{
				throw new Exception('no_access', false);
			}

			// Well then, you get to hear about it all over again
			markMessagesUnread($pmsg);
		}

		// Back to the folder.
		redirectexit($context['current_label_redirect']);
	}
}
