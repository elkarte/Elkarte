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
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0.10
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Personal Message Controller, It allows viewing, sending, deleting, and
 * marking personal messages
 *
 * @package PersonalMessage
 */
class PersonalMessage_Controller extends Action_Controller
{
	/**
	 * This method is executed before any other in this file (when the class is
	 * loaded by the dispatcher).
	 *
	 * What it does:
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
		loadLanguage('PersonalMessage+Drafts');
		loadJavascriptFile(array('PersonalMessage.js', 'suggest.js'));
		if (!isset($_REQUEST['xml']))
			loadTemplate('PersonalMessage');

		// Load up the members maximum message capacity.
		loadMessageLimit();

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

		// A previous message was sent successfully? show a small indication.
		if (isset($_GET['done']) && ($_GET['done'] === 'sent'))
			$context['pm_sent'] = true;

		// Load the label data.
		if ($user_settings['new_pm'] || ($context['labels'] = cache_get_data('labelCounts:' . $user_info['id'], 720)) === null)
		{
			$context['labels'] = $user_settings['message_labels'] == '' ? array() : explode(',', $user_settings['message_labels']);
			foreach ($context['labels'] as $id_label => $label_name)
			{
				$context['labels'][(int) $id_label] = array(
					'id' => $id_label,
					'name' => trim($label_name),
					'messages' => 0,
					'unread_messages' => 0,
				);
			}

			$context['labels'][-1] = array(
				'id' => -1,
				'name' => $txt['pm_msg_label_inbox'],
				'messages' => 0,
				'unread_messages' => 0,
			);

			loadPMLabels();
		}

		// Now we have the labels, and assuming we have unsorted mail, apply our rules!
		if ($user_settings['new_pm'])
		{
			// Apply our rules to the new PM's
			applyRules();
			updateMemberData($user_info['id'], array('new_pm' => 0));

			// Turn the new PM's status off, for the popup alert, since they have entered the PM area
			toggleNewPM($user_info['id']);
		}

		// This determines if we have more labels than just the standard inbox.
		$context['currently_using_labels'] = count($context['labels']) > 1 ? 1 : 0;

		// Some stuff for the labels...
		$context['current_label_id'] = isset($_REQUEST['l']) && isset($context['labels'][(int) $_REQUEST['l']]) ? (int) $_REQUEST['l'] : -1;
		$context['current_label'] = &$context['labels'][(int) $context['current_label_id']]['name'];
		$context['folder'] = !isset($_REQUEST['f']) || $_REQUEST['f'] != 'sent' ? 'inbox' : 'sent';

		// This is convenient.  Do you know how annoying it is to do this every time?!
		$context['current_label_redirect'] = 'action=pm;f=' . $context['folder'] . (isset($_GET['start']) ? ';start=' . $_GET['start'] : '') . (isset($_REQUEST['l']) ? ';l=' . $_REQUEST['l'] : '');
		$context['can_issue_warning'] = in_array('w', $context['admin_features']) && allowedTo('issue_warning') && !empty($modSettings['warning_enable']);

		// Are PM drafts enabled?
		$context['drafts_pm_save'] = !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_pm_enabled']) && allowedTo('pm_draft');
		$context['drafts_autosave'] = !empty($context['drafts_pm_save']) && !empty($modSettings['drafts_autosave_enabled']) && allowedTo('pm_autosave_draft');

		// Build the linktree for all the actions...
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm',
			'name' => $txt['personal_messages']
		);

		// Preferences...
		$context['display_mode'] = $user_settings['pm_prefs'] & 3;
	}

	/**
	 * This is the main function of personal messages, called before the action handler.
	 *
	 * What it does:
	 * - PersonalMessages is a menu-based controller.
	 * - It sets up the menu.
	 * - Calls from the menu the appropriate method/function for the current area.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context;

		require_once(SUBSDIR . '/Action.class.php');

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
			'showpmdrafts' => array('dir' => CONTROLLERDIR, 'file' => 'Draft.controller.php', 'controller' => 'Draft_Controller', 'function' => 'action_showPMDrafts', 'permission' => 'pm_read'),
			'inbox' => array($this, 'action_folder', 'permission' => 'pm_read'),
		);

		// Set up our action array
		$action = new Action();

		// Known action, go to it, otherwise the inbox for you
		$subAction = $action->initialize($subActions, 'inbox');

		// Set the right index bar for the action
		if ($subAction === 'inbox')
			messageIndexBar($context['current_label_id'] == -1 ? $context['folder'] : 'label' . $context['current_label_id']);
		elseif (!isset($_REQUEST['xml']))
			messageIndexBar($subAction);

		// And off we go!
		$action->dispatch($subAction);
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
		if (isset($_GET['view']))
		{
			$context['display_mode'] = $context['display_mode'] > 1 ? 0 : $context['display_mode'] + 1;
			updateMemberData($user_info['id'], array('pm_prefs' => ($user_settings['pm_prefs'] & 252) | $context['display_mode']));
		}

		// Make sure the starting location is valid.
		if (isset($_GET['start']) && $_GET['start'] !== 'new')
			$start = (int) $_GET['start'];
		elseif (!isset($_GET['start']) && !empty($options['view_newest_pm_first']))
			$start = 0;
		else
			$start = 'new';

		// Set up some basic template stuff.
		$context['from_or_to'] = $context['folder'] !== 'sent' ? 'from' : 'to';
		$context['get_pmessage'] = 'preparePMContext_callback';
		$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;
		$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

		// Set the template layers we need
		$template_layers = Template_Layers::getInstance();
		$template_layers->addAfter('subject_list', 'pm');

		$labelQuery = $context['folder'] != 'sent' ? '
				AND FIND_IN_SET(' . $context['current_label_id'] . ', pmr.labels) != 0' : '';

		// They didn't pick a sort, so we use the forum default.
		$sort_by = !isset($_GET['sort']) ? 'date' : $_GET['sort'];
		$descending = isset($_GET['desc']);

		// Set our sort by query
		switch ($sort_by)
		{
			case 'date':
				$sort_by_query = 'pm.id_pm';
				$descending = !empty($options['view_newest_pm_first']);
				break;
			case 'name':
				$sort_by_query = 'IFNULL(mem.real_name, \'\')';
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
			$start = ($max_messages - 1) - (($max_messages - 1) % $modSettings['defaultMaxMessages']);
		elseif ($start < 0)
			$start = 0;

		// ... but wait - what if we want to start from a specific message?
		if (isset($_GET['pmid']))
		{
			$pmID = (int) $_GET['pmid'];

			// Make sure you have access to this PM.
			if (!isAccessiblePM($pmID, $context['folder'] == 'sent' ? 'outbox' : 'inbox'))
				fatal_lang_error('no_access', false);

			$context['current_pm'] = $pmID;

			// With only one page of PM's we're gonna want page 1.
			if ($max_messages <= $modSettings['defaultMaxMessages'])
				$start = 0;
			// If we pass kstart we assume we're in the right place.
			elseif (!isset($_GET['kstart']))
			{
				$start = getPMCount($descending, $pmID, $labelQuery);

				// To stop the page index's being abnormal, start the page on the page the message
				// would normally be located on...
				$start = $modSettings['defaultMaxMessages'] * (int) ($start / $modSettings['defaultMaxMessages']);
			}
		}

		// Sanitize and validate pmsg variable if set.
		if (isset($_GET['pmsg']))
		{
			$pmsg = (int) $_GET['pmsg'];

			if (!isAccessiblePM($pmsg, $context['folder'] === 'sent' ? 'outbox' : 'inbox'))
				fatal_lang_error('no_access', false);
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

		// Make sure that we have been given a correct head pm id if we are in converstation mode
		if ($context['display_mode'] == 2 && !empty($pmID) && $pmID != $lastData['id'])
			fatal_lang_error('no_access', false);

		// If loadPMs returned results, lets show the pm subject list
		if (!empty($pms))
		{
			// Tell the template if no pm has specifically been selected
			if (empty($pmID))
				$context['current_pm'] = 0;

			// This is a list of the pm's that are used for "show all" display.
			if ($context['display_mode'] == 0)
				$display_pms = $pms;
			// Just use the last pm the user received to start things off
			else
				$display_pms = array($lastData['id']);

			// At this point we know the main id_pm's. But if we are looking at conversations we need
			// the PMs that make up the conversation
			if ($context['display_mode'] == 2)
			{
				list($display_pms, $posters) = loadConversationList($lastData['head'], $recipients, $context['folder']);

				// Conversation list may expose additonal PM's being displayed
				$all_pms = array_unique(array_merge($pms, $display_pms));

				// See if any of these 'listing' PM's are in a conversation thread that has unread entries
				$context['conversation_unread'] = loadConversationUnreadStatus($all_pms);
			}
			// This is pretty much EVERY pm!
			else
				$all_pms = array_unique(array_merge($pms, $display_pms));

			// Get recipients (don't include bcc-recipients for your inbox, you're not supposed to know :P).
			list($context['message_labels'], $context['message_replied'], $context['message_unread']) = loadPMRecipientInfo($all_pms, $recipients, $context['folder']);

			// Make sure we don't load any unnecessary data for one at a time mode
			if ($context['display_mode'] == 1)
			{
				foreach ($posters as $pm_key => $sender)
					if (!in_array($pm_key, $display_pms))
						unset($posters[$pm_key]);
			}

			// Load some information about the message sender
			$posters = array_unique($posters);
			if (!empty($posters))
				loadMemberData($posters);

			// If we're on grouped/restricted view get a restricted list of messages.
			if ($context['display_mode'] != 0)
			{
				// Get the order right.
				$orderBy = array();
				foreach (array_reverse($pms) as $pm)
					$orderBy[] = 'pm.id_pm = ' . $pm;

				// Separate query for these bits, preparePMContext_callback will use it as required
				$subjects_request = loadPMSubjectRequest($pms, $orderBy);
			}

			// Execute the load message query if a message has been chosen and let
			// preparePMContext_callback fetch the results.  Otherwise just show the pm selection list
			if (empty($pmsg) && empty($pmID) && $context['display_mode'] != 0)
				$messages_request = false;
			else
				$messages_request = loadPMMessageRequest($display_pms, $sort_by_query, $sort_by, $descending, $context['display_mode'], $context['folder']);
		}
		else
			$messages_request = false;

		// Prepare some items for the template
		$context['can_send_pm'] = allowedTo('pm_send');
		$context['can_send_email'] = allowedTo('send_email_to_members');
		$context['sub_template'] = 'folder';
		$context['page_title'] = $txt['pm_inbox'];
		$context['sort_direction'] = $descending ? 'down' : 'up';
		$context['sort_by'] = $sort_by;

		// Auto video embeding enabled, someone may have a link in a PM
		if (!empty($messages_request) && !empty($modSettings['enableVideoEmbeding']))
		{
			addInlineJavascript('
		$(document).ready(function() {
			$().linkifyvideo(oEmbedtext);
		});', true
			);
		}

		if (!empty($messages_request) && !empty($context['show_delete']))
			Template_Layers::getInstance()->addEnd('pm_pages_and_buttons');

		// Set up the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=pm;f=' . $context['folder'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . ';sort=' . $context['sort_by'] . ($descending ? ';desc' : ''), $start, $max_messages, $modSettings['defaultMaxMessages']);
		$context['start'] = $start;

		$context['pm_form_url'] = $scripturl . '?action=pm;sa=pmactions;' . ($context['display_mode'] == 2 ? 'conversation;' : '') . 'f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '');

		// Finally mark the relevant messages as read.
		if ($context['folder'] !== 'sent' && !empty($context['labels'][(int) $context['current_label_id']]['unread_messages']))
		{
			// If the display mode is "old sk00l" do them all...
			if ($context['display_mode'] == 0)
				markMessages(null, $context['current_label_id']);
			// Otherwise do just the currently displayed ones!
			elseif (!empty($context['current_pm']))
				markMessages($display_pms, $context['current_label_id']);
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
		loadLanguage('PersonalMessage');
		loadTemplate('PersonalMessage');

		// Set the template we will use
		$context['sub_template'] = 'send';

		// Extract out the spam settings - cause it's neat.
		list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

		// Set up some items for the template
		$context['page_title'] = $txt['send_message'];
		$context['reply'] = isset($_REQUEST['pmsg']) || isset($_REQUEST['quote']);

		// Check whether we've gone over the limit of messages we can send per hour.
		if (!empty($modSettings['pm_posts_per_hour']) && !allowedTo(array('admin_forum', 'moderate_forum', 'send_mail')) && $user_info['mod_cache']['bq'] == '0=1' && $user_info['mod_cache']['gq'] == '0=1')
		{
			// How many messages have they sent this last hour?
			$pmCount = pmCount($user_info['id'], 3600);

			if (!empty($pmCount) && $pmCount >= $modSettings['pm_posts_per_hour'])
				fatal_lang_error('pm_too_many_per_hour', true, array($modSettings['pm_posts_per_hour']));
		}

		// Quoting / Replying to a message?
		if (!empty($_REQUEST['pmsg']))
		{
			$pmsg = (int) $_REQUEST['pmsg'];

			// Make sure this is accessible (not deleted)
			if (!isAccessiblePM($pmsg))
				fatal_lang_error('no_access', false);

			// Validate that this is one has been received?
			$isReceived = checkPMReceived($pmsg);

			// Get the quoted message (and make sure you're allowed to see this quote!).
			$row_quoted = loadPMQuote($pmsg, $isReceived);
			if ($row_quoted === false)
				fatal_lang_error('pm_not_yours', false);

			// Censor the message.
			censorText($row_quoted['subject']);
			censorText($row_quoted['body']);

			// Lets make sure we mark this one as read
			markMessages($pmsg);

			// Figure out which flavor or 'Re: ' to use
			$context['response_prefix'] = response_prefix();

			$form_subject = $row_quoted['subject'];

			// Add 'Re: ' to it....
			if ($context['reply'] && trim($context['response_prefix']) != '' && Util::strpos($form_subject, trim($context['response_prefix'])) !== 0)
				$form_subject = $context['response_prefix'] . $form_subject;

			// If quoting, lets clean up some things and set the quote header for the pm body
			if (isset($_REQUEST['quote']))
			{
				// Remove any nested quotes and <br />...
				$form_message = preg_replace('~<br ?/?' . '>~i', "\n", $row_quoted['body']);
				if (!empty($modSettings['removeNestedQuotes']))
					$form_message = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $form_message);

				if (empty($row_quoted['id_member']))
					$form_message = '[quote author=&quot;' . $row_quoted['real_name'] . '&quot;]' . "\n" . $form_message . "\n" . '[/quote]';
				else
					$form_message = '[quote author=' . $row_quoted['real_name'] . ' link=action=profile;u=' . $row_quoted['id_member'] . ' date=' . $row_quoted['msgtime'] . ']' . "\n" . $form_message . "\n" . '[/quote]';
			}
			else
				$form_message = '';

			// Do the BBC thang on the message.
			$row_quoted['body'] = parse_bbc($row_quoted['body'], true, 'pm' . $row_quoted['id_pm']);

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
		if (isset($_REQUEST['u']))
		{
			// If the user is replying to all, get all the other members this was sent to..
			if ($_REQUEST['u'] == 'all' && isset($row_quoted))
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
				$users = array_map('intval', explode(',', $_REQUEST['u']));
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
			$context['to_value'] = '';

		// Set the defaults...
		$context['subject'] = $form_subject;
		$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_message);

		// And build the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;sa=send',
			'name' => $txt['new_message']
		);

		// If drafts are enabled, lets generate a list of drafts that they can load in to the editor
		if (!empty($context['drafts_pm_save']))
		{
			$pm_seed = isset($_REQUEST['pmsg']) ? $_REQUEST['pmsg'] : (isset($_REQUEST['quote']) ? $_REQUEST['quote'] : 0);
			prepareDraftsContext($user_info['id'], $pm_seed);
		}

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
		create_control_richedit($editorOptions);

		// No one is bcc'ed just yet
		$context['bcc_value'] = '';

		// Verification control needed for this PM?
		$context['require_verification'] = !$user_info['is_admin'] && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'];
		if ($context['require_verification'])
		{
			require_once(SUBSDIR . '/VerificationControls.class.php');

			$verificationOptions = array(
				'id' => 'pm',
			);
			$context['require_verification'] = create_control_verification($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}

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

		// PM Drafts enabled and needed?
		if ($context['drafts_pm_save'] && (isset($_POST['save_draft']) || isset($_POST['id_pm_draft'])))
			require_once(SUBSDIR . '/Drafts.subs.php');

		loadLanguage('PersonalMessage', '', false);

		// Extract out the spam settings - it saves database space!
		list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

		// Initialize the errors we're about to make.
		$post_errors = Error_Context::context('pm', 1);

		// Check whether we've gone over the limit of messages we can send per hour - fatal error if fails!
		if (!empty($modSettings['pm_posts_per_hour']) && !allowedTo(array('admin_forum', 'moderate_forum', 'send_mail')) && $user_info['mod_cache']['bq'] == '0=1' && $user_info['mod_cache']['gq'] == '0=1')
		{
			// How many have they sent this last hour?
			$pmCount = pmCount($user_info['id'], 3600);

			if (!empty($pmCount) && $pmCount >= $modSettings['pm_posts_per_hour'])
			{
				if (!isset($_REQUEST['xml']))
					fatal_lang_error('pm_too_many_per_hour', true, array($modSettings['pm_posts_per_hour']));
				else
					$post_errors->addError('pm_too_many_per_hour');
			}
		}

		// If your session timed out, show an error, but do allow to re-submit.
		if (!isset($_REQUEST['xml']) && checkSession('post', '', false) != '')
			$post_errors->addError('session_timeout');

		$_REQUEST['subject'] = isset($_REQUEST['subject']) ? strtr(Util::htmltrim($_POST['subject']), array("\r" => '', "\n" => '', "\t" => '')) : '';
		$_REQUEST['to'] = empty($_POST['to']) ? (empty($_GET['to']) ? '' : $_GET['to']) : $_POST['to'];
		$_REQUEST['bcc'] = empty($_POST['bcc']) ? (empty($_GET['bcc']) ? '' : $_GET['bcc']) : $_POST['bcc'];

		// Route the input from the 'u' parameter to the 'to'-list.
		if (!empty($_POST['u']))
			$_POST['recipient_to'] = explode(',', $_POST['u']);

		// Construct the list of recipients.
		$recipientList = array();
		$namedRecipientList = array();
		$namesNotFound = array();
		foreach (array('to', 'bcc') as $recipientType)
		{
			// First, let's see if there's user ID's given.
			$recipientList[$recipientType] = array();
			if (!empty($_POST['recipient_' . $recipientType]) && is_array($_POST['recipient_' . $recipientType]))
			{
				foreach ($_POST['recipient_' . $recipientType] as $recipient)
					$recipientList[$recipientType][] = (int) $recipient;
			}

			// Are there also literal names set?
			if (!empty($_REQUEST[$recipientType]))
			{
				// We're going to take out the "s anyway ;).
				$recipientString = strtr($_REQUEST[$recipientType], array('\\"' => '"'));

				preg_match_all('~"([^"]+)"~', $recipientString, $matches);
				$namedRecipientList[$recipientType] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $recipientString))));

				// Clean any literal names entered
				foreach ($namedRecipientList[$recipientType] as $index => $recipient)
				{
					if (strlen(trim($recipient)) > 0)
						$namedRecipientList[$recipientType][$index] = Util::htmlspecialchars(Util::strtolower(trim($recipient)));
					else
						unset($namedRecipientList[$recipientType][$index]);
				}

				// Now see if we can resolove the entered name to an actual user
				if (!empty($namedRecipientList[$recipientType]))
				{
					$foundMembers = findMembers($namedRecipientList[$recipientType]);

					// Assume all are not found, until proven otherwise.
					$namesNotFound[$recipientType] = $namedRecipientList[$recipientType];

					// Make sure we only have each member listed once, incase they did not use the select list
					foreach ($foundMembers as $member)
					{
						$testNames = array(
							Util::strtolower($member['username']),
							Util::strtolower($member['name']),
							Util::strtolower($member['email']),
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
			if (!empty($_POST['delete_recipient']))
				$recipientList[$recipientType] = array_diff($recipientList[$recipientType], array((int) $_POST['delete_recipient']));

			// Make sure we don't include the same name twice
			$recipientList[$recipientType] = array_unique($recipientList[$recipientType]);
		}

		// Are we changing the recipients some how?
		$is_recipient_change = !empty($_POST['delete_recipient']) || !empty($_POST['to_submit']) || !empty($_POST['bcc_submit']);

		// Check if there's at least one recipient.
		if (empty($recipientList['to']) && empty($recipientList['bcc']))
			$post_errors->addError('no_to');

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
		if ($_REQUEST['subject'] == '')
			$post_errors->addError('no_subject');

		if (!isset($_REQUEST['message']) || $_REQUEST['message'] == '')
			$post_errors->addError('no_message');
		elseif (!empty($modSettings['max_messageLength']) && Util::strlen($_REQUEST['message']) > $modSettings['max_messageLength'])
			$post_errors->addError('long_message');
		else
		{
			// Preparse the message.
			$message = $_REQUEST['message'];
			preparsecode($message);

			// Make sure there's still some content left without the tags.
			if (Util::htmltrim(strip_tags(parse_bbc(Util::htmlspecialchars($message, ENT_QUOTES), false), '<img>')) === '' && (!allowedTo('admin_forum') || strpos($message, '[html]') === false))
				$post_errors->addError('no_message');
		}

		// Wrong verification code?
		if (!$user_info['is_admin'] && !isset($_REQUEST['xml']) && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'])
		{
			require_once(SUBSDIR . '/VerificationControls.class.php');

			$verificationOptions = array(
				'id' => 'pm',
			);
			$context['require_verification'] = create_control_verification($verificationOptions, true);

			if (is_array($context['require_verification']))
				foreach ($context['require_verification'] as $error)
					$post_errors->addError($error);
		}

		// If they made any errors, give them a chance to make amends.
		if ($post_errors->hasErrors() && !$is_recipient_change && !isset($_REQUEST['preview']) && !isset($_REQUEST['xml']))
			return messagePostError($namedRecipientList, $recipientList);

		// Want to take a second glance before you send?
		if (isset($_REQUEST['preview']))
		{
			// Set everything up to be displayed.
			$context['preview_subject'] = Util::htmlspecialchars($_REQUEST['subject']);
			$context['preview_message'] = Util::htmlspecialchars($_REQUEST['message'], ENT_QUOTES, 'UTF-8', true);
			preparsecode($context['preview_message'], true);

			// Parse out the BBC if it is enabled.
			$context['preview_message'] = parse_bbc($context['preview_message']);

			// Censor, as always.
			censorText($context['preview_subject']);
			censorText($context['preview_message']);

			// Set a descriptive title.
			$context['page_title'] = $txt['preview'] . ' - ' . $context['preview_subject'];

			// Pretend they messed up but don't ignore if they really did :P.
			return messagePostError($namedRecipientList, $recipientList);
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

			return messagePostError($namedRecipientList, $recipientList);
		}

		// Want to save this as a draft and think about it some more?
		if ($context['drafts_pm_save'] && isset($_POST['save_draft']))
		{
			savePMDraft($recipientList);
			return messagePostError($namedRecipientList, $recipientList);
		}
		// Before we send the PM, let's make sure we don't have an abuse of numbers.
		elseif (!empty($modSettings['max_pm_recipients']) && count($recipientList['to']) + count($recipientList['bcc']) > $modSettings['max_pm_recipients'] && !allowedTo(array('moderate_forum', 'send_mail', 'admin_forum')))
		{
			$context['send_log'] = array(
				'sent' => array(),
				'failed' => array(sprintf($txt['pm_too_many_recipients'], $modSettings['max_pm_recipients'])),
			);

			return messagePostError($namedRecipientList, $recipientList);
		}

		// Protect from message spamming.
		spamProtection('pm');

		// Prevent double submission of this form.
		checkSubmitOnce('check');

		// Finally do the actual sending of the PM.
		if (!empty($recipientList['to']) || !empty($recipientList['bcc']))
			$context['send_log'] = sendpm($recipientList, $_REQUEST['subject'], $_REQUEST['message'], true, null, !empty($_REQUEST['pm_head']) ? (int) $_REQUEST['pm_head'] : 0);
		else
			$context['send_log'] = array(
				'sent' => array(),
				'failed' => array()
			);

		// Mark the message as "replied to".
		if (!empty($context['send_log']['sent']) && !empty($_REQUEST['replied_to']) && isset($_REQUEST['f']) && $_REQUEST['f'] == 'inbox')
		{
			require_once(SUBSDIR . '/PersonalMessage.subs.php');
			setPMRepliedStatus($user_info['id'], (int) $_REQUEST['replied_to'] );
		}

		// If one or more of the recipients were invalid, go back to the post screen with the failed usernames.
		if (!empty($context['send_log']['failed']))
			return messagePostError($namesNotFound, array(
				'to' => array_intersect($recipientList['to'], $context['send_log']['failed']),
				'bcc' => array_intersect($recipientList['bcc'], $context['send_log']['failed'])
			));

		// Message sent successfully?
		if (!empty($context['send_log']) && empty($context['send_log']['failed']))
		{
			$context['current_label_redirect'] = $context['current_label_redirect'] . ';done=sent';

			// If we had a PM draft for this one, then its time to remove it since it was just sent
			if ($context['drafts_pm_save'] && !empty($_POST['id_pm_draft']))
				deleteDrafts($_POST['id_pm_draft'], $user_info['id']);
		}

		// Go back to the where they sent from, if possible...
		redirectexit($context['current_label_redirect']);
	}

	/**
	 * This function performs all additional actions including the deleting
	 * and labeling of PM's
	 */
	public function action_pmactions()
	{
		global $context, $user_info;

		checkSession('request');

		if (isset($_REQUEST['del_selected']))
			$_REQUEST['pm_action'] = 'delete';

		// Create a list of pm's that we need to work on
		if (isset($_REQUEST['pm_action']) && $_REQUEST['pm_action'] != '' && !empty($_REQUEST['pms']) && is_array($_REQUEST['pms']))
		{
			foreach ($_REQUEST['pms'] as $pm)
				$_REQUEST['pm_actions'][(int) $pm] = $_REQUEST['pm_action'];
		}

		if (empty($_REQUEST['pm_actions']))
			redirectexit($context['current_label_redirect']);

		// If we are in conversation, we may need to apply this to every message in the conversation.
		if ($context['display_mode'] == 2 && isset($_REQUEST['conversation']))
		{
			$id_pms = array_map('intval', array_keys($_REQUEST['pm_actions']));
			$pm_heads = getDiscussions($id_pms);
			$pms = getPmsFromDiscussion(array_keys($pm_heads));

			// Copy the action from the single to PM to the others.
			foreach ($pms as $id_pm => $id_head)
			{
				if (isset($pm_heads[$id_head]) && isset($_REQUEST['pm_actions'][$pm_heads[$id_head]]))
					$_REQUEST['pm_actions'][$id_pm] = $_REQUEST['pm_actions'][$pm_heads[$id_head]];
			}
		}

		$to_delete = array();
		$to_label = array();
		$label_type = array();
		foreach ($_REQUEST['pm_actions'] as $pm => $action)
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

			if ($action == '-1' || $action == '0' || (int) $action > 0)
			{
				$to_label[(int) $pm] = (int) $action;
				$label_type[(int) $pm] = $type;
			}
		}

		// Deleting, it looks like?
		if (!empty($to_delete))
			deleteMessages($to_delete, $context['display_mode'] == 2 ? null : $context['folder']);

		// Are we labelling anything?
		if (!empty($to_label) && $context['folder'] == 'inbox')
		{
			$updateErrors = changePMLabels($to_label, $label_type, $user_info['id']);

			// Any errors?
			if (!empty($updateErrors))
				fatal_lang_error('labels_too_many', true, array($updateErrors));
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
		$context['delete_all'] = $_REQUEST['f'] == 'all';

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
		if ($_REQUEST['f'] == 'all')
			deleteMessages(null, null);
		// Otherwise just the selected folder.
		else
			deleteMessages(null, $_REQUEST['f'] != 'sent' ? 'inbox' : 'sent');

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
		if (isset($_REQUEST['age']))
		{
			checkSession();

			// Calculate the time to delete before.
			$deleteTime = max(0, time() - (86400 * (int) $_REQUEST['age']));

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
				$the_labels[$label['id']] = $label['name'];
		}

		// Submitting changes?
		if (isset($_POST['add']) || isset($_POST['delete']) || isset($_POST['save']))
		{
			checkSession('post');

			// This will be for updating messages.
			$message_changes = array();
			$new_labels = array();
			$rule_changes = array();

			// Will most likely need this.
			loadRules();

			// Adding a new label?
			if (isset($_POST['add']))
			{
				$_POST['label'] = strtr(Util::htmlspecialchars(trim($_POST['label'])), array(',' => '&#044;'));

				if (Util::strlen($_POST['label']) > 30)
					$_POST['label'] = Util::substr($_POST['label'], 0, 30);
				if ($_POST['label'] != '')
					$the_labels[] = $_POST['label'];
			}
			// Deleting an existing label?
			elseif (isset($_POST['delete'], $_POST['delete_label']))
			{
				$i = 0;
				foreach ($the_labels as $id => $name)
				{
					if (isset($_POST['delete_label'][$id]))
					{
						unset($the_labels[$id]);
						$message_changes[$id] = true;
					}
					else
						$new_labels[$id] = $i++;
				}
			}
			// The hardest one to deal with... changes.
			elseif (isset($_POST['save']) && !empty($_POST['label_name']))
			{
				$i = 0;
				foreach ($the_labels as $id => $name)
				{
					if ($id == -1)
						continue;
					elseif (isset($_POST['label_name'][$id]))
					{
						// Prepare the label name
						$_POST['label_name'][$id] = trim(strtr(Util::htmlspecialchars($_POST['label_name'][$id]), array(',' => '&#044;')));

						// Has to fit in the database as well
						if (Util::strlen($_POST['label_name'][$id]) > 30)
							$_POST['label_name'][$id] = Util::substr($_POST['label_name'][$id], 0, 30);

						if ($_POST['label_name'][$id] != '')
						{
							$the_labels[(int) $id] = $_POST['label_name'][$id];
							$new_labels[$id] = $i++;
						}
						else
						{
							unset($the_labels[(int) $id]);
							$message_changes[(int) $id] = true;
						}
					}
					else
						$new_labels[$id] = $i++;
				}
			}

			// Save the label status.
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
							continue;

						$rule_changes[] = $rule['id'];

						// If we're here we have a label which is either changed or gone...
						if (isset($new_labels[$action['v']]))
							$context['rules'][$k]['actions'][$k2]['v'] = $new_labels[$action['v']];
						else
							unset($context['rules'][$k]['actions'][$k2]);
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
					deletePMRules($user_info['id'], $rule_changes);
			}

			// Make sure we're not caching this!
			cache_put_data('labelCounts:' . $user_info['id'], null, 720);

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
		loadLanguage('Profile');
		loadTemplate('Profile');

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
		if (isset($_REQUEST['save']))
		{
			checkSession('post');

			// Mimic what profile would do.
			$_POST = htmltrim__recursive($_POST);
			$_POST = htmlspecialchars__recursive($_POST);

			// Save the fields.
			require_once(CONTROLLERDIR . '/ProfileOptions.controller.php');
			$fields = ProfileOptions_Controller::getFields('contactprefs');
			saveProfileFields($fields['fields'], $fields['hook']);

			if (!empty($profile_vars))
				updateMemberData($user_info['id'], $profile_vars);

			// Invalidate any cached data and reload so we show the saved values
			cache_put_data('member_data-profile-' . $user_info['id'], null, 0);
			loadMemberData($user_info['id'], false, 'profile');
			$cur_profile = $user_profile[$user_info['id']];
		}

		// Load up the fields.
		require_once(CONTROLLERDIR . '/ProfileOptions.controller.php');
		$controller = new ProfileOptions_Controller();
		$controller->action_pmprefs();
	}

	/**
	 * Allows the user to report a personal message to an administrator.
	 *
	 * What it does:
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
		if (empty($modSettings['enableReportPM']) || empty($_REQUEST['pmsg']))
			fatal_lang_error('no_access', false);

		$pmsg = (int) $_REQUEST['pmsg'];

		if (!isAccessiblePM($pmsg, 'inbox'))
			fatal_lang_error('no_access', false);

		$context['pm_id'] = $pmsg;
		$context['page_title'] = $txt['pm_report_title'];

		// We'll query some members, we will.
		require_once(SUBSDIR . '/Members.subs.php');

		// If we're here, just send the user to the template, with a few useful context bits.
		if (!isset($_POST['report']))
		{
			$context['sub_template'] = 'report_message';

			// Now, get all the administrators.
			$context['admins'] = admins();

			// How many admins in total?
			$context['admin_count'] = count($context['admins']);
		}
		// Otherwise, let's get down to the sending stuff.
		else
		{
			// Check the session before proceeding any further!
			checkSession('post');

			// First, load up the message they want to file a complaint against, and verify it actually went to them!
			list ($subject, $body, $time, $memberFromID, $memberFromName) = loadPersonalMessage($context['pm_id']);

			// Remove the line breaks...
			$body = preg_replace('~<br ?/?' . '>~i', "\n", $body);

			$recipients = array();
			$temp = loadPMRecipientsAll($context['pm_id'], true);
			foreach ($temp as $recipient)
				$recipients[] = $recipient['link'];

			// Now let's get out and loop through the admins.
			$admins = admins(isset($_POST['id_admin']) ? (int) $_POST['id_admin'] : 0);

			// Maybe we shouldn't advertise this?
			if (empty($admins))
				fatal_lang_error('no_access', false);

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
					loadLanguage('PersonalMessage', $cur_language, false);

					// Make the body.
					$report_body = str_replace(array('{REPORTER}', '{SENDER}'), array(un_htmlspecialchars($user_info['name']), $memberFromName), $txt['pm_report_pm_user_sent']);
					$report_body .= "\n" . '[b]' . $_POST['reason'] . '[/b]' . "\n\n";
					if (!empty($recipients))
						$report_body .= $txt['pm_report_pm_other_recipients'] . ' ' . implode(', ', $recipients) . "\n\n";
					$report_body .= $txt['pm_report_pm_unedited_below'] . "\n" . '[quote author=' . (empty($memberFromID) ? '&quot;' . $memberFromName . '&quot;' : $memberFromName . ' link=action=profile;u=' . $memberFromID . ' date=' . $time) . ']' . "\n" . un_htmlspecialchars($body) . '[/quote]';

					// Plonk it in the array ;)
					$messagesToSend[$cur_language] = array(
						'subject' => (Util::strpos($subject, $txt['pm_report_pm_subject']) === false ? $txt['pm_report_pm_subject'] : '') . un_htmlspecialchars($subject),
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
				loadLanguage('PersonalMessage', '', false);

			// Leave them with a template.
			$context['sub_template'] = 'report_message_complete';
		}
	}

	/**
	 * List and allow adding/entering all man rules, such as
	 *
	 * What it does:
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
		if (isset($_GET['apply']))
		{
			checkSession('get');

			applyRules(true);
			redirectexit('action=pm;sa=manrules');
		}

		// Editing a specific rule?
		if (isset($_GET['add']))
		{
			$context['rid'] = isset($_GET['rid']) && isset($context['rules'][$_GET['rid']]) ? (int) $_GET['rid'] : 0;
			$context['sub_template'] = 'add_rule';

			// Any known rule
			$js_rules = '';
			foreach ($context['known_rules'] as $rule)
				$js_rules .= JavaScriptEscape($rule) . ': ' . JavaScriptEscape($txt['pm_rule_' . $rule]) . ',';
			$js_rules = '{' . substr($js_rules, 0, -1) . '}';

			// Any known label
			$js_labels = '';
			foreach ($context['labels'] as $label)
				if ($label['id'] != -1)
					$js_labels .= JavaScriptEscape($label['id'] + 1) . ': ' . JavaScriptEscape($label['name']) . ',';
			$js_labels = '{' . substr($js_labels, 0, -1) . '}';

			// And all of the groups as well
			$js_groups = '';
			foreach ($context['groups'] as $id => $title)
				$js_groups .= JavaScriptEscape($id) . ': ' . JavaScriptEscape($title) . ',';
			$js_groups = '{' . substr($js_groups, 0, -1) . '}';

			// Oh my, we have a lot of text strings for this
			addJavascriptVar(array(
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
					if ($criteria['t'] == 'mid' && !empty($criteria['v']))
						$members[(int) $criteria['v']] = $k;

				if (!empty($members))
				{
					require_once(SUBSDIR . '/Members.subs.php');
					$result = getBasicMemberData(array_keys($members));
					foreach ($result as $row)
						$context['rule']['criteria'][$members[$row['id_member']]]['v'] = $row['member_name'];
				}
			}
			else
				$context['rule'] = array(
					'id' => '',
					'name' => '',
					'criteria' => array(),
					'actions' => array(),
					'logic' => 'and',
				);

			// Add a dummy criteria to allow expansion for none js users.
			$context['rule']['criteria'][] = array('t' => '', 'v' => '');
		}
		// Saving?
		elseif (isset($_GET['save']))
		{
			checkSession('post');
			$context['rid'] = isset($_GET['rid']) && isset($context['rules'][$_GET['rid']]) ? (int) $_GET['rid'] : 0;

			// Name is easy!
			$ruleName = Util::htmlspecialchars(trim($_POST['rule_name']));
			if (empty($ruleName))
				fatal_lang_error('pm_rule_no_name', false);

			// Sanity check...
			if (empty($_POST['ruletype']) || empty($_POST['acttype']))
				fatal_lang_error('pm_rule_no_criteria', false);

			// Let's do the criteria first - it's also hardest!
			$criteria = array();
			foreach ($_POST['ruletype'] as $ind => $type)
			{
				// Check everything is here...
				if ($type == 'gid' && (!isset($_POST['ruledefgroup'][$ind]) || !isset($context['groups'][$_POST['ruledefgroup'][$ind]])))
					continue;
				elseif ($type != 'bud' && !isset($_POST['ruledef'][$ind]))
					continue;

				// Members need to be found.
				if ($type == 'mid')
				{
					require_once(SUBSDIR . '/Members.subs.php');
					$name = trim($_POST['ruledef'][$ind]);
					$member = getMemberByName($name, true);
					if (empty($member))
						continue;

					$criteria[] = array('t' => 'mid', 'v' => $member['id_member']);
				}
				elseif ($type == 'bud')
					$criteria[] = array('t' => 'bud', 'v' => 1);
				elseif ($type == 'gid')
					$criteria[] = array('t' => 'gid', 'v' => (int) $_POST['ruledefgroup'][$ind]);
				elseif (in_array($type, array('sub', 'msg')) && trim($_POST['ruledef'][$ind]) != '')
					$criteria[] = array('t' => $type, 'v' => Util::htmlspecialchars(trim($_POST['ruledef'][$ind])));
			}

			// Also do the actions!
			$actions = array();
			$doDelete = 0;
			$isOr = $_POST['rule_logic'] == 'or' ? 1 : 0;
			foreach ($_POST['acttype'] as $ind => $type)
			{
				// Picking a valid label?
				if ($type == 'lab' && (!isset($_POST['labdef'][$ind]) || !isset($context['labels'][$_POST['labdef'][$ind] - 1])))
					continue;

				// Record what we're doing.
				if ($type == 'del')
					$doDelete = 1;
				elseif ($type == 'lab')
					$actions[] = array('t' => 'lab', 'v' => (int) $_POST['labdef'][$ind] - 1);
			}

			if (empty($criteria) || (empty($actions) && !$doDelete))
				fatal_lang_error('pm_rule_no_criteria', false);

			// What are we storing?
			$criteria = serialize($criteria);
			$actions = serialize($actions);

			// Create the rule?
			if (empty($context['rid']))
				addPMRule($user_info['id'], $ruleName, $criteria, $actions, $doDelete, $isOr);
			else
				updatePMRule($user_info['id'], $context['rid'], $ruleName, $criteria, $actions, $doDelete, $isOr);

			redirectexit('action=pm;sa=manrules');
		}
		// Deleting?
		elseif (isset($_POST['delselected']) && !empty($_POST['delrule']))
		{
			checkSession('post');
			$toDelete = array();
			foreach ($_POST['delrule'] as $k => $v)
				$toDelete[] = (int) $k;

			if (!empty($toDelete))
				deletePMRules($user_info['id'], $toDelete);

			redirectexit('action=pm;sa=manrules');
		}
	}

	/**
	 * Allows to search through personal messages.
	 *
	 * What it does:
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
		if (isset($_REQUEST['params']))
		{
			$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], array(' ' => '+'))));
			$context['search_params'] = array();
			foreach ($temp_params as $i => $data)
			{
				@list ($k, $v) = explode('|\'|', $data);
				$context['search_params'][$k] = $v;
			}
		}

		// Set up the search criteia, type, what, age, etc
		if (isset($_REQUEST['search']))
		{
			$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);
			$context['search_params']['search'] = htmlspecialchars($context['search_params']['search'], ENT_COMPAT, 'UTF-8');
		}

		if (isset($context['search_params']['userspec']))
			$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec'], ENT_COMPAT, 'UTF-8');

		// 1 => 'allwords' / 2 => 'anywords'.
		if (!empty($context['search_params']['searchtype']))
			$context['search_params']['searchtype'] = 2;

		// Minimum and Maximum age of the message
		if (!empty($context['search_params']['minage']))
			$context['search_params']['minage'] = (int) $context['search_params']['minage'];
		if (!empty($context['search_params']['maxage']))
			$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];

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
			loadLanguage('Errors');
			$context['search_errors']['messages'] = array();
			foreach ($context['search_errors'] as $search_error => $dummy)
			{
				if ($search_error === 'messages')
					continue;

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
	 * Actually do the search of personal messages and show the results
	 *
	 * What it does:
	 * - accessed with ?action=pm;sa=search2
	 * - checks user input and searches the pm table for messages matching the query.
	 * - uses the search_results sub template of the PersonalMessage template.
	 * - show the results of the search query.
	 */
	public function action_search2()
	{
		global $scripturl, $modSettings, $context, $txt, $memberContext;

		$db = database();

		// Make sure the server is able to do this right now
		if (!empty($modSettings['loadavg_search']) && $modSettings['current_load'] >= $modSettings['loadavg_search'])
			fatal_lang_error('loadavg_search_disabled', false);

		// Some useful general permissions.
		$context['can_send_pm'] = allowedTo('pm_send');

		// Some hardcoded variables that can be tweaked if required.
		$maxMembersToSearch = 500;

		// Extract all the search parameters.
		$search_params = array();
		if (isset($_REQUEST['params']))
		{
			$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], array(' ' => '+'))));
			foreach ($temp_params as $i => $data)
			{
				@list ($k, $v) = explode('|\'|', $data);
				$search_params[$k] = $v;
			}
		}

		$context['start'] = isset($_GET['start']) ? (int) $_GET['start'] : 0;

		// Store whether simple search was used (needed if the user wants to do another query).
		if (!isset($search_params['advanced']))
			$search_params['advanced'] = empty($_REQUEST['advanced']) ? 0 : 1;

		// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
		if (!empty($search_params['searchtype']) || (!empty($_REQUEST['searchtype']) && $_REQUEST['searchtype'] == 2))
			$search_params['searchtype'] = 2;

		// Minimum age of messages. Default to zero (don't set param in that case).
		if (!empty($search_params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0))
			$search_params['minage'] = !empty($search_params['minage']) ? (int) $search_params['minage'] : (int) $_REQUEST['minage'];

		// Maximum age of messages. Default to infinite (9999 days: param not set).
		if (!empty($search_params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] < 9999))
			$search_params['maxage'] = !empty($search_params['maxage']) ? (int) $search_params['maxage'] : (int) $_REQUEST['maxage'];

		// Search modifiers
		$search_params['subject_only'] = !empty($search_params['subject_only']) || !empty($_REQUEST['subject_only']);
		$search_params['show_complete'] = !empty($search_params['show_complete']) || !empty($_REQUEST['show_complete']);
		$search_params['sent_only'] = !empty($search_params['sent_only']) || !empty($_REQUEST['sent_only']);
		$context['folder'] = empty($search_params['sent_only']) ? 'inbox' : 'sent';

		// Default the user name to a wildcard matching every user (*).
		if (!empty($search_params['userspec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*'))
			$search_params['userspec'] = isset($search_params['userspec']) ? $search_params['userspec'] : $_REQUEST['userspec'];

		// This will be full of all kinds of parameters!
		$searchq_parameters = array();

		// If there's no specific user, then don't mention it in the main query.
		if (empty($search_params['userspec']))
			$userQuery = '';
		else
		{
			// Set up so we can seach by user name, wildcards, like, etc
			$userString = strtr(Util::htmlspecialchars($search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
			$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

			preg_match_all('~"([^"]+)"~', $userString, $matches);
			$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

			require_once(SUBSDIR . '/Members.subs.php');

			// Who matches those criteria?
			$members = membersBy('member_names', array('member_names' => $possible_users));

			foreach ($possible_users as $key => $possible_user)
				$searchq_parameters['guest_user_name_implode_' . $key] = defined('DB_CASE_SENSITIVE') ? strtolower($possible_user) : $possible_user;

			// Simply do nothing if there are too many members matching the criteria.
			if (count($members) > $maxMembersToSearch)
				$userQuery = '';
			elseif (count($members) == 0)
			{
				if ($context['folder'] === 'inbox')
				{
					$uq = array();
					$name = defined('DB_CASE_SENSITIVE') ? 'LOWER(pm.from_name)' : 'pm.from_name';
					foreach (array_keys($possible_users) as $key)
						$uq[] = 'AND pm.id_member_from = 0 AND (' . $name . ' LIKE {string:guest_user_name_implode_' . $key . '})';
					$userQuery = implode(' ', $uq);
					$searchq_parameters['pm_from_name'] = defined('DB_CASE_SENSITIVE') ? 'LOWER(pm.from_name)' : 'pm.from_name';
				}
				else
					$userQuery = '';
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
					$name = defined('DB_CASE_SENSITIVE') ? 'LOWER(pm.from_name)' : 'pm.from_name';
					foreach (array_keys($possible_users) as $key)
						$uq[] = 'AND (pm.id_member_from IN ({array_int:member_list}) OR (pm.id_member_from = 0 AND (' . $name . ' LIKE {string:guest_user_name_implode_' . $key . '})))';
					$userQuery = implode(' ', $uq);
				}
				else
					$userQuery = 'AND (pmr.id_member IN ({array_int:member_list}))';

				$searchq_parameters['pm_from_name'] = defined('DB_CASE_SENSITIVE') ? 'LOWER(pm.from_name)' : 'pm.from_name';
				$searchq_parameters['member_list'] = $memberlist;
			}
		}

		// Setup the sorting variables...
		$sort_columns = array(
			'pm.id_pm',
		);
		if (empty($search_params['sort']) && !empty($_REQUEST['sort']))
			list ($search_params['sort'], $search_params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
		$search_params['sort'] = !empty($search_params['sort']) && in_array($search_params['sort'], $sort_columns) ? $search_params['sort'] : 'pm.id_pm';
		$search_params['sort_dir'] = !empty($search_params['sort_dir']) && $search_params['sort_dir'] == 'asc' ? 'asc' : 'desc';

		// Sort out any labels we may be searching by.
		$labelQuery = '';
		if ($context['folder'] == 'inbox' && !empty($search_params['advanced']) && $context['currently_using_labels'])
		{
			// Came here from pagination?  Put them back into $_REQUEST for sanitization.
			if (isset($search_params['labels']))
				$_REQUEST['searchlabel'] = explode(',', $search_params['labels']);

			// Assuming we have some labels - make them all integers.
			if (!empty($_REQUEST['searchlabel']) && is_array($_REQUEST['searchlabel']))
				$_REQUEST['searchlabel'] = array_map('intval', $_REQUEST['searchlabel']);
			else
				$_REQUEST['searchlabel'] = array();

			// Now that everything is cleaned up a bit, make the labels a param.
			$search_params['labels'] = implode(',', $_REQUEST['searchlabel']);

			// No labels selected? That must be an error!
			if (empty($_REQUEST['searchlabel']))
				$context['search_errors']['no_labels_selected'] = true;
			// Otherwise prepare the query!
			elseif (count($_REQUEST['searchlabel']) != count($context['labels']))
			{
				$labelQuery = '
				AND {raw:label_implode}';

				$labelStatements = array();
				foreach ($_REQUEST['searchlabel'] as $label)
					$labelStatements[] = $db->quote('FIND_IN_SET({string:label}, pmr.labels) != 0', array('label' => $label,));

				$searchq_parameters['label_implode'] = '(' . implode(' OR ', $labelStatements) . ')';
			}
		}

		// Unfortunately, searching for words like this is going to be slow, so we're blacklisting them.
		$blacklisted_words = array('quote', 'the', 'is', 'it', 'are', 'if');

		// What are we actually searching for?
		$search_params['search'] = !empty($search_params['search']) ? $search_params['search'] : (isset($_REQUEST['search']) ? $_REQUEST['search'] : '');

		// If nothing is left to search on - we set an error!
		if (!isset($search_params['search']) || $search_params['search'] == '')
			$context['search_errors']['invalid_search_string'] = true;

		// Change non-word characters into spaces.
		$stripped_query = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', $search_params['search']);

		// Make the query lower case since it will case insensitive anyway.
		$stripped_query = un_htmlspecialchars(Util::strtolower($stripped_query));

		// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
		preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);
		$phraseArray = $matches[2];

		// Remove the phrase parts and extract the words.
		$wordArray = preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $search_params['search']);
		$wordArray = explode(' ', Util::htmlspecialchars(un_htmlspecialchars($wordArray), ENT_QUOTES));

		// A minus sign in front of a word excludes the word.... so...
		$excludedWords = array();

		// Check for things like -"some words", but not "-some words".
		foreach ($matches[1] as $index => $word)
		{
			if ($word === '-')
			{
				if (($word = trim($phraseArray[$index], '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
					$excludedWords[] = $word;
				unset($phraseArray[$index]);
			}
		}

		// Now we look for -test, etc
		foreach ($wordArray as $index => $word)
		{
			if (strpos(trim($word), '-') === 0)
			{
				if (($word = trim($word, '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
					$excludedWords[] = $word;
				unset($wordArray[$index]);
			}
		}

		// The remaining words and phrases are all included.
		$searchArray = array_merge($phraseArray, $wordArray);

		// Trim everything and make sure there are no words that are the same.
		foreach ($searchArray as $index => $value)
		{
			// Skip anything thats close to empty.
			if (($searchArray[$index] = trim($value, '-_\' ')) === '')
				unset($searchArray[$index]);
			// Skip blacklisted words. Make sure to note we skipped them as well
			elseif (in_array($searchArray[$index], $blacklisted_words))
			{
				$foundBlackListedWords = true;
				unset($searchArray[$index]);
			}

			$searchArray[$index] = Util::strtolower(trim($value));
			if ($searchArray[$index] == '')
				unset($searchArray[$index]);
			else
			{
				// Sort out entities first.
				$searchArray[$index] = Util::htmlspecialchars($searchArray[$index]);
			}
		}

		$searchArray = array_slice(array_unique($searchArray), 0, 10);

		// Create an array of replacements for highlighting.
		$context['mark'] = array();
		foreach ($searchArray as $word)
			$context['mark'][$word] = '<strong class="highlight">' . $word . '</strong>';

		// This contains *everything*
		$searchWords = array_merge($searchArray, $excludedWords);

		// Make sure at least one word is being searched for.
		if (empty($searchArray))
			$context['search_errors']['invalid_search_string' . (!empty($foundBlackListedWords) ? '_blacklist' : '')] = true;

		// Sort out the search query so the user can edit it - if they want.
		$context['search_params'] = $search_params;
		if (isset($context['search_params']['search']))
			$context['search_params']['search'] = Util::htmlspecialchars($context['search_params']['search']);
		if (isset($context['search_params']['userspec']))
			$context['search_params']['userspec'] = Util::htmlspecialchars($context['search_params']['userspec']);

		// Now we have all the parameters, combine them together for pagination and the like...
		$context['params'] = array();
		foreach ($search_params as $k => $v)
			$context['params'][] = $k . '|\'|' . $v;
		$context['params'] = base64_encode(implode('|"|', $context['params']));

		// Compile the subject query part.
		$andQueryParts = array();
		foreach ($searchWords as $index => $word)
		{
			if ($word == '')
				continue;

			if ($search_params['subject_only'])
				$andQueryParts[] = 'pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '}';
			else
				$andQueryParts[] = '(pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '} ' . (in_array($word, $excludedWords) ? 'AND pm.body NOT' : 'OR pm.body') . ' LIKE {string:search_' . $index . '})';

			$searchq_parameters['search_' . $index] = '%' . strtr($word, array('_' => '\\_', '%' => '\\%')) . '%';
		}

		$searchQuery = ' 1=1';
		if (!empty($andQueryParts))
			$searchQuery = implode(!empty($search_params['searchtype']) && $search_params['searchtype'] == 2 ? ' OR ' : ' AND ', $andQueryParts);

		// Age limits?
		$timeQuery = '';
		if (!empty($search_params['minage']))
			$timeQuery .= ' AND pm.msgtime < ' . (time() - $search_params['minage'] * 86400);
		if (!empty($search_params['maxage']))
			$timeQuery .= ' AND pm.msgtime > ' . (time() - $search_params['maxage'] * 86400);

		// If we have errors - return back to the first screen...
		if (!empty($context['search_errors']))
		{
			$_REQUEST['params'] = $context['params'];
			return $this->action_search();
		}

		// Get the number of results.
		$numResults = numPMSeachResults($userQuery, $labelQuery, $timeQuery, $searchQuery, $searchq_parameters);

		// Get all the matching message ids, senders and head pm nodes
		list($foundMessages, $posters, $head_pms) = loadPMSearchMessages($userQuery, $labelQuery, $timeQuery, $searchQuery, $searchq_parameters, $search_params);

		// Find the real head pm when in converstaion view
		if ($context['display_mode'] == 2 && !empty($head_pms))
			$real_pm_ids = loadPMSearchHeads($head_pms);

		// Load the found user data
		$posters = array_unique($posters);
		if (!empty($posters))
			loadMemberData($posters);

		// Sort out the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=pm;sa=search2;params=' . $context['params'], $_GET['start'], $numResults, $modSettings['search_results_per_page'], false);

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
			$search_results = loadPMSearchResults($foundMessages, $search_params);
			$counter = 0;
			foreach ($search_results as $row)
			{
				// If there's no subject, use the default.
				$row['subject'] = $row['subject'] == '' ? $txt['no_subject'] : $row['subject'];

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
				censorText($row['body']);
				censorText($row['subject']);

				// Parse out any BBC...
				$row['body'] = parse_bbc($row['body'], true, 'pm' . $row['id_pm']);

				// Highlight the hits
				$body_highlighted = '';
				$subject_highlighted = '';
				foreach ($searchArray as $query)
				{
					// Fix the international characters in the keyword too.
					$query = un_htmlspecialchars($query);
					$query = trim($query, '\*+');
					$query = strtr(Util::htmlspecialchars($query), array('\\\'' => '\''));

					$body_highlighted = preg_replace_callback('/((<[^>]*)|' . preg_quote(strtr($query, array('\'' => '&#039;')), '/') . ')/iu', array($this, '_highlighted_callback'), $row['body']);
					$subject_highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<strong class="highlight">$1</strong>', $row['subject']);
				}

				// Set a link using the first label information
				$href = $scripturl . '?action=pm;f=' . $context['folder'] . (isset($context['first_label'][$row['id_pm']]) ? ';l=' . $context['first_label'][$row['id_pm']] : '') . ';pmid=' . ($context['display_mode'] == 2 && isset($real_pm_ids[$head_pms[$row['id_pm']]]) && $context['folder'] == 'inbox' ? $real_pm_ids[$head_pms[$row['id_pm']]] : $row['id_pm']) . '#msg_' . $row['id_pm'];

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
	 * Used to highlight body text with strings that match the search term
	 *
	 * - Callback function used in $body_highlighted
	 *
	 * @param string[] $matches
	 */
	private function _highlighted_callback($matches)
	{
		return isset($matches[2]) && $matches[2] == $matches[1] ? stripslashes($matches[1]) : '<strong class="highlight">' . $matches[1] . '</strong>';
	}

	/**
	 * Allows the user to mark a personal message as unread so they remember to come back to it
	 */
	public function action_markunread()
	{
		global $context;

		checkSession('request');

		$pmsg = !empty($_REQUEST['pmsg']) ? (int) $_REQUEST['pmsg'] : null;

		// Marking a message as unread, we need a message that was sent to them
		// Can't mark your own reply as unread, that would be weird
		if (!is_null($pmsg) && checkPMReceived($pmsg))
		{
			// Make sure this is accessible, should be of course
			if (!isAccessiblePM($pmsg, 'inbox'))
				fatal_lang_error('no_access', false);

			// Well then, you get to hear about it all over again
			markMessagesUnread($pmsg);
		}

		// Back to the folder.
		redirectexit($context['current_label_redirect']);
	}
}

/**
 * A menu to easily access different areas of the PM section
 *
 * @param string $area
 */
function messageIndexBar($area)
{
	global $txt, $context, $scripturl, $modSettings, $user_info;

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
				'drafts' => array(
					'label' => $txt['drafts_show'],
					'custom_url' => $scripturl . '?action=pm;sa=showpmdrafts',
					'permission' => 'pm_draft',
					'enabled' => !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_pm_enabled']),
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
		unset($pm_areas['labels']);
	else
	{
		// Note we send labels by id as it will have less problems in the querystring.
		$label_counters['labels_unread_total'] = 0;
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] == -1)
				continue;

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
	if (!$pm_include_data && (!$user_info['is_guest'] || validateSession()))
		fatal_lang_error('no_access', false);

	// Make a note of the Unique ID for this menu.
	$context['pm_menu_id'] = $context['max_menu_id'];
	$context['pm_menu_name'] = 'menu_data_' . $context['pm_menu_id'];

	// Set the selected item.
	$context['menu_item_selected'] = $pm_include_data['current_area'];

	// Grab the file needed for this action
	if (isset($pm_include_data['file']))
		require_once($pm_include_data['file']);

	// Set the template for this area and add the profile layer.
	if (!isset($_REQUEST['xml']))
	{
		$template_layers = Template_Layers::getInstance();
		$template_layers->add('pm');
	}
}

/**
 * Get a personal message for the template. (used to save memory.)
 *
 * - This is a callback function that will fetch the actual results, as needed, of a previously run
 * subject (loadPMSubjectRequest) or message (loadPMMessageRequest) query.
 *
 * @param string $type
 * @param boolean $reset
 */
function preparePMContext_callback($type = 'subject', $reset = false)
{
	global $txt, $scripturl, $modSettings, $settings, $context, $memberContext, $recipients, $user_info;
	global $subjects_request, $messages_request;
	static $counter = null, $temp_pm_selected = null;

	// We need this
	$db = database();

	// Count the current message number....
	if ($counter === null || $reset)
		$counter = $context['start'];

	if ($temp_pm_selected === null)
	{
		$temp_pm_selected = isset($_SESSION['pm_selected']) ? $_SESSION['pm_selected'] : array();
		$_SESSION['pm_selected'] = array();
	}

	// If we're in non-boring view do something exciting!
	if ($context['display_mode'] != 0 && $subjects_request && $type == 'subject')
	{
		$subject = $db->fetch_assoc($subjects_request);
		if (!$subject)
		{
			$db->free_result($subjects_request);
			return false;
		}

		// Make sure we have a subject
		$subject['subject'] = $subject['subject'] == '' ? $txt['no_subject'] : $subject['subject'];
		censorText($subject['subject']);

		$output = array(
			'id' => $subject['id_pm'],
			'member' => array(
				'id' => $subject['id_member_from'],
				'name' => $subject['from_name'],
				'link' => $subject['not_guest'] ? '<a href="' . $scripturl . '?action=profile;u=' . $subject['id_member_from'] . '">' . $subject['from_name'] . '</a>' : $subject['from_name'],
			),
			'recipients' => &$recipients[$subject['id_pm']],
			'subject' => $subject['subject'],
			'time' => standardTime($subject['msgtime']),
			'html_time' => htmlTime($subject['msgtime']),
			'timestamp' => forum_time(true, $subject['msgtime']),
			'number_recipients' => count($recipients[$subject['id_pm']]['to']),
			'labels' => &$context['message_labels'][$subject['id_pm']],
			'fully_labeled' => count($context['message_labels'][$subject['id_pm']]) == count($context['labels']),
			'is_replied_to' => &$context['message_replied'][$subject['id_pm']],
			'is_unread' => &$context['message_unread'][$subject['id_pm']],
			'is_selected' => !empty($temp_pm_selected) && in_array($subject['id_pm'], $temp_pm_selected),
		);

		// In conversation view we need to indicate on the subject listing if any message inside of
		// that conversation is unread, not just if the latest is unread.
		if ($context['display_mode'] == 2 && isset($context['conversation_unread'][$output['id']]))
			$output['is_unread'] = true;

		return $output;
	}

	// Bail if it's false, ie. no messages.
	if ($messages_request == false)
		return false;

	// Reset the data?
	if ($reset == true)
		return $db->data_seek($messages_request, 0);

	// Get the next one... bail if anything goes wrong.
	$message = $db->fetch_assoc($messages_request);
	if (!$message)
	{
		if ($type != 'subject')
			$db->free_result($messages_request);

		return false;
	}

	// Use '(no subject)' if none was specified.
	$message['subject'] = $message['subject'] == '' ? $txt['no_subject'] : $message['subject'];

	// Load the message's information - if it's not there, load the guest information.
	if (!loadMemberContext($message['id_member_from'], true))
	{
		$memberContext[$message['id_member_from']]['name'] = $message['from_name'];
		$memberContext[$message['id_member_from']]['id'] = 0;

		// Sometimes the forum sends messages itself (Warnings are an example) - in this case don't label it from a guest.
		$memberContext[$message['id_member_from']]['group'] = $message['from_name'] == $context['forum_name'] ? '' : $txt['guest_title'];
		$memberContext[$message['id_member_from']]['link'] = $message['from_name'];
		$memberContext[$message['id_member_from']]['email'] = '';
		$memberContext[$message['id_member_from']]['show_email'] = showEmailAddress(true, 0);
		$memberContext[$message['id_member_from']]['is_guest'] = true;
	}
	else
	{
		$memberContext[$message['id_member_from']]['can_view_profile'] = allowedTo('profile_view_any') || ($message['id_member_from'] == $user_info['id'] && allowedTo('profile_view_own'));
		$memberContext[$message['id_member_from']]['can_see_warning'] = !isset($context['disabled_fields']['warning_status']) && $memberContext[$message['id_member_from']]['warning_status'] && ($context['user']['can_mod'] || (!empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $message['id_member_from'] == $user_info['id'])));
	}

	$memberContext[$message['id_member_from']]['show_profile_buttons'] = $settings['show_profile_buttons'] && (!empty($memberContext[$message['id_member_from']]['can_view_profile']) || (!empty($memberContext[$message['id_member_from']]['website']['url']) && !isset($context['disabled_fields']['website'])) || (in_array($memberContext[$message['id_member_from']]['show_email'], array('yes', 'yes_permission_override', 'no_through_forum'))) || $context['can_send_pm']);

	// Censor all the important text...
	censorText($message['body']);
	censorText($message['subject']);

	// Run BBC interpreter on the message.
	$message['body'] = parse_bbc($message['body'], true, 'pm' . $message['id_pm']);

	// Return the array.
	$output = array(
		'alternate' => $counter % 2,
		'id' => $message['id_pm'],
		'member' => &$memberContext[$message['id_member_from']],
		'subject' => $message['subject'],
		'time' => standardTime($message['msgtime']),
		'html_time' => htmlTime($message['msgtime']),
		'timestamp' => forum_time(true, $message['msgtime']),
		'counter' => $counter,
		'body' => $message['body'],
		'recipients' => &$recipients[$message['id_pm']],
		'number_recipients' => count($recipients[$message['id_pm']]['to']),
		'labels' => &$context['message_labels'][$message['id_pm']],
		'fully_labeled' => count($context['message_labels'][$message['id_pm']]) == count($context['labels']),
		'is_replied_to' => &$context['message_replied'][$message['id_pm']],
		'is_unread' => &$context['message_unread'][$message['id_pm']],
		'is_selected' => !empty($temp_pm_selected) && in_array($message['id_pm'], $temp_pm_selected),
		'is_message_author' => $message['id_member_from'] == $user_info['id'],
		'can_report' => !empty($modSettings['enableReportPM']),
		'can_see_ip' => allowedTo('moderate_forum') || ($message['id_member_from'] == $user_info['id'] && !empty($user_info['id'])),
	);

	$context['additional_pm_drop_buttons'] = array();

	// Can they report this message
	if (!empty($output['can_report']) && $context['folder'] !== 'sent' && $output['member']['id'] != $user_info['id'])
		$context['additional_pm_drop_buttons']['warn_button'] = array(
			'href' => $scripturl . '?action=pm;sa=report;l=' . $context['current_label_id'] . ';pmsg=' . $output['id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			'text' => $txt['pm_report_to_admin']
		);

	// Or mark it as unread
	if (empty($output['is_unread']) && $context['folder'] !== 'sent' && $output['member']['id'] != $user_info['id'])
		$context['additional_pm_drop_buttons']['restore_button'] = array(
			'href' => $scripturl . '?action=pm;sa=markunread;l=' . $context['current_label_id'] . ';pmsg=' . $output['id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			'text' => $txt['pm_mark_unread']
		);

	// Or give / take karma for a PM
	if (!empty($output['member']['karma']['allow']))
	{
		$output['member']['karma'] += array(
			'applaud_url' => $scripturl . '?action=karma;sa=applaud;uid=' . $output['member']['id'] . ';f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ';pm=' . $output['id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			'smite_url' => $scripturl . '?action=karma;sa=smite;uid=' . $output['member']['id'] . ';f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ';pm=' . $output['id'] . ';' . $context['session_var'] . '=' . $context['session_id'],
		);
	}

	$counter++;

	return $output;
}

/**
 * An error in the message...
 *
 * @param mixed[] $named_recipients
 * @param int[] $recipient_ids
 */
function messagePostError($named_recipients, $recipient_ids = array())
{
	global $txt, $context, $scripturl, $modSettings, $user_info;

	if (!isset($_REQUEST['xml']))
		$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'send';

	if (!isset($_REQUEST['xml']))
		$context['sub_template'] = 'send';
	elseif (isset($_REQUEST['xml']))
		$context['sub_template'] = 'generic_preview';

	$context['page_title'] = $txt['send_message'];
	$error_types = Error_Context::context('pm', 1);

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
	$context['subject'] = isset($_REQUEST['subject']) ? Util::htmlspecialchars($_REQUEST['subject']) : '';
	$context['message'] = isset($_REQUEST['message']) ? str_replace(array('  '), array('&nbsp; '), Util::htmlspecialchars($_REQUEST['message'], ENT_QUOTES, 'UTF-8', true)) : '';
	$context['reply'] = !empty($_REQUEST['replied_to']);

	// If this is a reply to message, we need to reload the quote
	if ($context['reply'])
	{
		$pmsg = (int) $_REQUEST['replied_to'];
		$isReceived = $context['folder'] !== 'sent';
		$row_quoted = loadPMQuote($pmsg, $isReceived);
		if ($row_quoted === false)
		{
			if (!isset($_REQUEST['xml']))
				fatal_lang_error('pm_not_yours', false);
			else
				$error_types->addError('pm_not_yours');
		}
		else
		{
			censorText($row_quoted['subject']);
			censorText($row_quoted['body']);

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
				'body' => parse_bbc($row_quoted['body'], true, 'pm' . $row_quoted['id_pm']),
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
		'width' => '90%',
		'height' => '250px',
		'labels' => array(
			'post_button' => $txt['send_message'],
		),
		'preview_type' => 2,
	);
	create_control_richedit($editorOptions);

	// Check whether we need to show the code again.
	$context['require_verification'] = !$user_info['is_admin'] && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'];
	if ($context['require_verification'] && !isset($_REQUEST['xml']))
	{
		require_once(SUBSDIR . '/VerificationControls.class.php');
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
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
 * Loads in a group of PM drafts for the user.
 *
 * What it does:
 * - Loads a specific draft for current use in pm editing box if selected.
 * - Used in the posting screens to allow draft selection
 * - Will load a draft if selected is supplied via post
 *
 * @param int $member_id
 * @param int|false $id_pm = false if set, it will try to load drafts for this id
 * @return false|null
 */
function prepareDraftsContext($member_id, $id_pm = false)
{
	global $scripturl, $context, $txt, $modSettings;

	$context['drafts'] = array();

	// Permissions
	if (empty($member_id))
		return false;

	// We haz drafts
	loadLanguage('Drafts');
	require_once(SUBSDIR . '/Drafts.subs.php');

	// Has a specific draft has been selected?  Load it up if there is not already a message already in the editor
	if (isset($_REQUEST['id_draft']) && empty($_POST['subject']) && empty($_POST['message']))
		loadDraft((int) $_REQUEST['id_draft'], 1, true, true);

	// Load all the drafts for this user that meet the criteria
	$order = 'poster_time DESC';
	$user_drafts = load_user_drafts($member_id, 1, $id_pm, $order);

	// Add them to the context draft array for template display
	foreach ($user_drafts as $draft)
	{
		$short_subject = empty($draft['subject']) ? $txt['drafts_none'] : Util::shorten_text(stripslashes($draft['subject']), !empty($modSettings['draft_subject_length']) ? $modSettings['draft_subject_length'] : 24);
		$context['drafts'][] = array(
			'subject' => censorText($short_subject),
			'poster_time' => standardTime($draft['poster_time']),
				'link' => '<a href="' . $scripturl . '?action=pm;sa=send;id_draft=' . $draft['id_draft'] . '">' . (!empty($draft['subject']) ? $draft['subject'] : $txt['drafts_none']) . '</a>',
			);
	}
}