<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file is mainly meant for controlling the actions related to personal
 * messages. It allows viewing, sending, deleting, and marking personal
 * messages. For compatibility reasons, they are often called "instant messages".
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * This is the main function of personal messages, called before the action handler.
 * It should set the context, load templates and language file(s), as necessary
 * for the function that will be called.
 *
 * @todo this should be a (pre)dispatcher.
 */
function action_pm()
{
	global $txt, $scripturl, $context, $user_info, $user_settings, $smcFunc, $modSettings;

	// No guests!
	is_not_guest();

	// You're not supposed to be here at all, if you can't even read PMs.
	isAllowedTo('pm_read');

	// This file contains the our PM functions such as mark, send, delete
	require_once(SUBSDIR . '/PersonalMessage.subs.php');

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

	// a previous message was sent successfully? show a small indication.
	if (isset($_GET['done']) && ($_GET['done'] == 'sent'))
		$context['pm_sent'] = true;

	// Now we have the labels, and assuming we have unsorted mail, apply our rules!
	if ($user_settings['new_pm'])
	{
		$context['labels'] = $user_settings['message_labels'] == '' ? array() : explode(',', $user_settings['message_labels']);
		foreach ($context['labels'] as $id_label => $label_name)
			$context['labels'][(int) $id_label] = array(
				'id' => $id_label,
				'name' => trim($label_name),
				'messages' => 0,
				'unread_messages' => 0,
			);
		$context['labels'][-1] = array(
			'id' => -1,
			'name' => $txt['pm_msg_label_inbox'],
			'messages' => 0,
			'unread_messages' => 0,
		);

		applyRules();
		updateMemberData($user_info['id'], array('new_pm' => 0));
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pm_recipients
			SET is_new = {int:not_new}
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'not_new' => 0,
			)
		);
	}

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

	// Finally all the things we know how to do
	$subActions = array(
		'manlabels' => 'action_messagelabels',
		'manrules' => 'action_messagerules',
		'pmactions' => 'action_messageactions',
		'prune' => 'action_messageprune',
		'removeall' => 'action_removemessage',
		'removeall2' => 'action_removemessage2',
		'report' => 'action_reportmessage',
		'search' => array('Search.controller.php','MessageSearch'),
		'search2' => array('Search.controller.php','MessageSearch2'),
		'send' => 'action_sendmessage',
		'send2' => 'action_sendmessage2',
		'settings' => 'action_messagesettings',
		'showpmdrafts' => 'action_messagedrafts',
	);

	// Known action, go to it, otherwise the inbox for you
	if (!isset($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
		action_messagefolder();
	else
	{
		if (!isset($_REQUEST['xml']))
			messageIndexBar($_REQUEST['sa']);

		// So it was set - let's go to that action.
		if (is_array($subActions[$_REQUEST['sa']]))
		{
			require_once(CONTROLLERDIR . '/' . $subActions[$_REQUEST['sa']][0]);
			$subActions[$_REQUEST['sa']][1]();
		}
		else
			$subActions[$_REQUEST['sa']]();
	}
}

/**
 * A menu to easily access different areas of the PM section
 *
 * @param string $area
 */
function messageIndexBar($area)
{
	global $txt, $context, $scripturl, $sc, $modSettings, $settings, $user_info, $options;

	$pm_areas = array(
		'folders' => array(
			'title' => $txt['pm_messages'],
			'areas' => array(
				'send' => array(
					'label' => $txt['new_message'],
					'custom_url' => $scripturl . '?action=pm;sa=send',
					'permission' => allowedTo('pm_send'),
				),
				'inbox' => array(
					'label' => $txt['inbox'],
					'custom_url' => $scripturl . '?action=pm',
				),
				'sent' => array(
					'label' => $txt['sent_items'],
					'custom_url' => $scripturl . '?action=pm;f=sent',
				),
				'drafts' => array(
					'label' => $txt['drafts_show'],
					'custom_url' => $scripturl . '?action=pm;sa=showpmdrafts',
					'permission' => allowedTo('pm_draft'),
					'enabled' => !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_pm_enabled']),
				),
			),
		),
		'labels' => array(
			'title' => $txt['pm_labels'],
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
	if (empty($context['currently_using_labels']))
		unset($pm_areas['labels']);
	else
	{
		// Note we send labels by id as it will have less problems in the querystring.
		$unread_in_labels = 0;
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] == -1)
				continue;

			// Count the amount of unread items in labels.
			$unread_in_labels += $label['unread_messages'];

			// Add the label to the menu.
			$pm_areas['labels']['areas']['label' . $label['id']] = array(
				'label' => $label['name'] . (!empty($label['unread_messages']) ? ' (<strong>' . $label['unread_messages'] . '</strong>)' : ''),
				'custom_url' => $scripturl . '?action=pm;l=' . $label['id'],
				'unread_messages' => $label['unread_messages'],
				'messages' => $label['messages'],
			);
		}

		if (!empty($unread_in_labels))
			$pm_areas['labels']['title'] .= ' (' . $unread_in_labels . ')';
	}

	$pm_areas['folders']['areas']['inbox']['unread_messages'] = &$context['labels'][-1]['unread_messages'];
	$pm_areas['folders']['areas']['inbox']['messages'] = &$context['labels'][-1]['messages'];

	// If we have unread messages, make note of the number in the menus
	if (!empty($context['labels'][-1]['unread_messages']))
	{
		$pm_areas['folders']['areas']['inbox']['label'] .= ' (<strong>' . $context['labels'][-1]['unread_messages'] . '</strong>)';
		$pm_areas['folders']['title'] .= ' (' . $context['labels'][-1]['unread_messages'] . ')';
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

	require_once(SUBSDIR . '/Menu.subs.php');

	// What page is this, again?
	$current_page = $scripturl . '?action=pm' . (!empty($_REQUEST['sa']) ? ';sa=' . $_REQUEST['sa'] : '') . (!empty($context['folder']) ? ';f=' . $context['folder'] : '') . (!empty($context['current_label_id']) ? ';l=' . $context['current_label_id'] : '');

	// Set a few options for the menu.
	$menuOptions = array(
		'current_area' => $area,
		'disable_url_session_check' => true,
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
	$current_area = $pm_include_data['current_area'];
	$context['menu_item_selected'] = $current_area;

	// Set the template for this area and add the profile layer.
	if (!isset($_REQUEST['xml']))
		$context['template_layers'][] = 'pm';
}

/**
 * A folder, ie. inbox/sent etc.
 */
function action_messagefolder()
{
	global $txt, $scripturl, $modSettings, $context, $subjects_request;
	global $messages_request, $user_info, $recipients, $options, $smcFunc, $memberContext, $user_settings;

	// Changing view?
	if (isset($_GET['view']))
	{
		$context['display_mode'] = $context['display_mode'] > 1 ? 0 : $context['display_mode'] + 1;
		updateMemberData($user_info['id'], array('pm_prefs' => ($user_settings['pm_prefs'] & 252) | $context['display_mode']));
	}

	// Make sure the starting location is valid.
	$start = '';
	if (isset($_GET['start']) && $_GET['start'] != 'new')
		$start = (int) $_GET['start'];
	elseif (!isset($_GET['start']) && !empty($options['view_newest_pm_first']))
		$start = 0;
	else
		$start = 'new';

	// Set up some basic theme stuff.
	$context['from_or_to'] = $context['folder'] != 'sent' ? 'from' : 'to';
	$context['get_pmessage'] = 'prepareMessageContext';
	$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;
	$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

	$labelQuery = $context['folder'] != 'sent' ? '
			AND FIND_IN_SET(' . $context['current_label_id'] . ', pmr.labels) != 0' : '';

	// Set the index bar correct!
	messageIndexBar($context['current_label_id'] == -1 ? $context['folder'] : 'label' . $context['current_label_id']);

	// They didn't pick a sort, use the forum default.
	if (!isset($_GET['sort']))
	{
		$sort_by = 'date';
		$descending = !empty($options['view_newest_pm_first']);
	}
	// Otherwise use the defaults: ascending, by date.
	else
	{
		$sort_by = $_GET['sort'];
		$descending = isset($_GET['desc']);
	}

	// Set our sort by query
	switch ($sort_by)
	{
		case 'date':
			$sort_by_query = 'pm.id_pm';
		case 'name':
			$sort_by_query = 'IFNULL(mem.real_name, \'\')';
		case 'subject':
			$sort_by_query = 'pm.subject';
		default:
			$sort_by_query = 'pm.id_pm';
	}

	// Set the text to resemble the current folder.
	$pmbox = $context['folder'] != 'sent' ? $txt['inbox'] : $txt['sent_items'];
	$txt['delete_all'] = str_replace('PMBOX', $pmbox, $txt['delete_all']);

	// Now, build the link tree!
	if ($context['current_label_id'] == -1)
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;f=' . $context['folder'],
			'name' => $pmbox
		);

	// Build it further for a label.
	if ($context['current_label_id'] != -1)
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;f=' . $context['folder'] . ';l=' . $context['current_label_id'],
			'name' => $txt['pm_current_label'] . ': ' . $context['current_label']
		);

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

			// To stop the page index's being abnormal, start the page on the page the message would normally be located on...
			$start = $modSettings['defaultMaxMessages'] * (int) ($start / $modSettings['defaultMaxMessages']);
		}
	}

	// Sanitize and validate pmsg variable if set.
	if (isset($_GET['pmsg']))
	{
		$pmsg = (int) $_GET['pmsg'];

		if (!isAccessiblePM($pmsg, $context['folder'] == 'sent' ? 'outbox' : 'inbox'))
			fatal_lang_error('no_access', false);
	}

	// Determine the navigation context (especially useful for the wireless template).
	$context['links'] = array(
		'first' => $start >= $modSettings['defaultMaxMessages'] ? $scripturl . '?action=pm;start=0' : '',
		'prev' => $start >= $modSettings['defaultMaxMessages'] ? $scripturl . '?action=pm;start=' . ($start - $modSettings['defaultMaxMessages']) : '',
		'next' => $start + $modSettings['defaultMaxMessages'] < $max_messages ? $scripturl . '?action=pm;start=' . ($start + $modSettings['defaultMaxMessages']) : '',
		'last' => $start + $modSettings['defaultMaxMessages'] < $max_messages ? $scripturl . '?action=pm;start=' . (floor(($max_messages - 1) / $modSettings['defaultMaxMessages']) * $modSettings['defaultMaxMessages']) : '',
		'up' => $scripturl,
	);
	$context['page_info'] = array(
		'current_page' => $start / $modSettings['defaultMaxMessages'] + 1,
		'num_pages' => floor(($max_messages - 1) / $modSettings['defaultMaxMessages']) + 1
	);

	// First work out what messages we need to see - if grouped is a little trickier...
	if ($context['display_mode'] == 2)
	{
		// On a non-default sort due to PostgreSQL we have to do a harder sort.
		if ($smcFunc['db_title'] == 'PostgreSQL' && $sort_by_query != 'pm.id_pm')
		{
			$sub_request = $smcFunc['db_query']('', '
				SELECT MAX({raw:sort}) AS sort_param, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? ($sort_by == 'name' ? '
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
						AND pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:not_deleted}
						' . $labelQuery . ')') . ($sort_by == 'name' ? ( '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})') : '') . '
				WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {int:current_member}
					AND pm.deleted_by_sender = {int:not_deleted}' : '1=1') . (empty($pmsg) ? '' : '
					AND pm.id_pm = {int:id_pm}') . '
				GROUP BY pm.id_pm_head
				ORDER BY sort_param' . ($descending ? ' DESC' : ' ASC') . (empty($pmsg) ? '
				LIMIT ' . $start . ', ' . $modSettings['defaultMaxMessages'] : ''),
				array(
					'current_member' => $user_info['id'],
					'not_deleted' => 0,
					'id_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
					'id_pm' => isset($pmsg) ? $pmsg : '0',
					'sort' => $sort_by_query,
				)
			);
			$sub_pms = array();
			while ($row = $smcFunc['db_fetch_assoc']($sub_request))
				$sub_pms[$row['id_pm_head']] = $row['sort_param'];

			$smcFunc['db_free_result']($sub_request);

			$request = $smcFunc['db_query']('', '
				SELECT pm.id_pm AS id_pm, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? ($sort_by == 'name' ? '
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
						AND pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:not_deleted}
						' . $labelQuery . ')') . ($sort_by == 'name' ? ( '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})') : '') . '
				WHERE ' . (empty($sub_pms) ? '0=1' : 'pm.id_pm IN ({array_int:pm_list})') . '
				ORDER BY ' . ($sort_by_query == 'pm.id_pm' && $context['folder'] != 'sent' ? 'id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (empty($pmsg) ? '
				LIMIT ' . $start . ', ' . $modSettings['defaultMaxMessages'] : ''),
				array(
					'current_member' => $user_info['id'],
					'pm_list' => array_keys($sub_pms),
					'not_deleted' => 0,
					'sort' => $sort_by_query,
					'id_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
				)
			);
		}
		else
		{
			$request = $smcFunc['db_query']('pm_conversation_list', '
				SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? ($sort_by == 'name' ? '
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
						AND pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:deleted_by}
						' . $labelQuery . ')') . ($sort_by == 'name' ? ( '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
				WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {int:current_member}
					AND pm.deleted_by_sender = {int:deleted_by}' : '1=1') . (empty($pmsg) ? '' : '
					AND pm.id_pm = {int:pmsg}') . '
				GROUP BY pm.id_pm_head
				ORDER BY ' . ($sort_by_query == 'pm.id_pm' && $context['folder'] != 'sent' ? 'id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (isset($pmsg) ? '
				LIMIT ' . $start . ', ' . $modSettings['defaultMaxMessages'] : ''),
				array(
					'current_member' => $user_info['id'],
					'deleted_by' => 0,
					'sort' => $sort_by_query,
					'pm_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
					'pmsg' => isset($pmsg) ? (int) $pmsg : 0,
				)
			);
		}
	}
	// This is kinda simple!
	else
	{
		// @todo SLOW This query uses a filesort. (inbox only.)
		$request = $smcFunc['db_query']('', '
			SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '' . ($sort_by == 'name' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
					AND pmr.id_member = {int:current_member}
					AND pmr.deleted = {int:is_deleted}
					' . $labelQuery . ')') . ($sort_by == 'name' ? ( '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
			WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {raw:current_member}
				AND pm.deleted_by_sender = {int:is_deleted}' : '1=1') . (empty($pmsg) ? '' : '
				AND pm.id_pm = {int:pmsg}') . '
			ORDER BY ' . ($sort_by_query == 'pm.id_pm' && $context['folder'] != 'sent' ? 'pmr.id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (isset($pmsg) ? '
			LIMIT ' . $start . ', ' . $modSettings['defaultMaxMessages'] : ''),
			array(
				'current_member' => $user_info['id'],
				'is_deleted' => 0,
				'sort' => $sort_by_query,
				'pm_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
				'pmsg' => isset($pmsg) ? (int) $pmsg : 0,
			)
		);
	}
	// Load the id_pms and initialize recipients.
	$pms = array();
	$lastData = array();
	$posters = $context['folder'] == 'sent' ? array($user_info['id']) : array();
	$recipients = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($recipients[$row['id_pm']]))
		{
			if (isset($row['id_member_from']))
				$posters[$row['id_pm']] = $row['id_member_from'];
			$pms[$row['id_pm']] = $row['id_pm'];
			$recipients[$row['id_pm']] = array(
				'to' => array(),
				'bcc' => array()
			);
		}

		// Keep track of the last message so we know what the head is without another query!
		if ((empty($pmID) && (empty($options['view_newest_pm_first']) || !isset($lastData))) || empty($lastData) || (!empty($pmID) && $pmID == $row['id_pm']))
			$lastData = array(
				'id' => $row['id_pm'],
				'head' => $row['id_pm_head'],
			);
	}
	$smcFunc['db_free_result']($request);

	// Make sure that we have been given a correct head pm id!
	if ($context['display_mode'] === 2 && !empty($pmID) && $pmID != $lastData['id'])
		fatal_lang_error('no_access', false);

	if (!empty($pms))
	{
		// Select the correct current message.
		if (empty($pmID))
			$context['current_pm'] = $lastData['id'];

		// This is a list of the pm's that are used for "full" display.
		if ($context['display_mode'] == 0)
			$display_pms = $pms;
		else
			$display_pms = array($context['current_pm']);

		// At this point we know the main id_pm's. But - if we are looking at conversations we need the others!
		if ($context['display_mode'] == 2)
		{
			$request = $smcFunc['db_query']('', '
				SELECT pm.id_pm, pm.id_member_from, pm.deleted_by_sender, pmr.id_member, pmr.deleted
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				WHERE pm.id_pm_head = {int:id_pm_head}
					AND ((pm.id_member_from = {int:current_member} AND pm.deleted_by_sender = {int:not_deleted})
						OR (pmr.id_member = {int:current_member} AND pmr.deleted = {int:not_deleted}))
				ORDER BY pm.id_pm',
				array(
					'current_member' => $user_info['id'],
					'id_pm_head' => $lastData['head'],
					'not_deleted' => 0,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// This is, frankly, a joke. We will put in a workaround for people sending to themselves - yawn!
				if ($context['folder'] == 'sent' && $row['id_member_from'] == $user_info['id'] && $row['deleted_by_sender'] == 1)
					continue;
				elseif ($row['id_member'] == $user_info['id'] & $row['deleted'] == 1)
					continue;

				if (!isset($recipients[$row['id_pm']]))
					$recipients[$row['id_pm']] = array(
						'to' => array(),
						'bcc' => array()
					);
				$display_pms[] = $row['id_pm'];
				$posters[$row['id_pm']] = $row['id_member_from'];
			}
			$smcFunc['db_free_result']($request);
		}

		// This is pretty much EVERY pm!
		$all_pms = array_merge($pms, $display_pms);
		$all_pms = array_unique($all_pms);

		// Get recipients (don't include bcc-recipients for your inbox, you're not supposed to know :P).
		$request = $smcFunc['db_query']('', '
			SELECT pmr.id_pm, mem_to.id_member AS id_member_to, mem_to.real_name AS to_name, pmr.bcc, pmr.labels, pmr.is_read
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $all_pms,
			)
		);
		$context['message_labels'] = array();
		$context['message_replied'] = array();
		$context['message_unread'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($context['folder'] == 'sent' || empty($row['bcc']))
				$recipients[$row['id_pm']][empty($row['bcc']) ? 'to' : 'bcc'][] = empty($row['id_member_to']) ? $txt['guest_title'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_to'] . '">' . $row['to_name'] . '</a>';

			if ($row['id_member_to'] == $user_info['id'] && $context['folder'] != 'sent')
			{
				$context['message_replied'][$row['id_pm']] = $row['is_read'] & 2;
				$context['message_unread'][$row['id_pm']] = $row['is_read'] == 0;

				$row['labels'] = $row['labels'] == '' ? array() : explode(',', $row['labels']);
				foreach ($row['labels'] as $v)
				{
					if (isset($context['labels'][(int) $v]))
						$context['message_labels'][$row['id_pm']][(int) $v] = array('id' => $v, 'name' => $context['labels'][(int) $v]['name']);
				}
			}
		}
		$smcFunc['db_free_result']($request);

		// Make sure we don't load unnecessary data.
		if ($context['display_mode'] == 1)
		{
			foreach ($posters as $k => $v)
				if (!in_array($k, $display_pms))
					unset($posters[$k]);
		}

		// Load any users....
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

			// Seperate query for these bits!
			$subjects_request = $smcFunc['db_query']('', '
				SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.msgtime, IFNULL(mem.real_name, pm.from_name) AS from_name,
					IFNULL(mem.id_member, 0) AS not_guest
				FROM {db_prefix}personal_messages AS pm
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
				WHERE pm.id_pm IN ({array_int:pm_list})
				ORDER BY ' . implode(', ', $orderBy) . '
				LIMIT ' . count($pms),
				array(
					'pm_list' => $pms,
				)
			);
		}

		// Execute the query!
		$messages_request = $smcFunc['db_query']('', '
			SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') . ($sort_by == 'name' ? '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})' : '') . '
			WHERE pm.id_pm IN ({array_int:display_pms})' . ($context['folder'] == 'sent' ? '
			GROUP BY pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name' : '') . '
			ORDER BY ' . ($context['display_mode'] == 2 ? 'pm.id_pm' : $sort_by_query) . ($descending ? ' DESC' : ' ASC') . '
			LIMIT ' . count($display_pms),
			array(
				'display_pms' => $display_pms,
				'id_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
			)
		);
	}
	else
		$messages_request = false;

	// prepare some items for the template
	$context['can_send_pm'] = allowedTo('pm_send');
	$context['can_send_email'] = allowedTo('send_email_to_members');
	$context['sub_template'] = 'folder';
	$context['page_title'] = $txt['pm_inbox'];
	$context['sort_direction'] = $descending ? 'down' : 'up';
	$context['sort_by'] = $sort_by;

	// Set up the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=pm;f=' . $context['folder'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . ';sort=' . $context['sort_by'] . ($descending ? ';desc' : ''), $start, $max_messages, $modSettings['defaultMaxMessages']);
	$context['start'] = $start;

	// Finally mark the relevant messages as read.
	if ($context['folder'] != 'sent' && !empty($context['labels'][(int) $context['current_label_id']]['unread_messages']))
	{
		// If the display mode is "old sk00l" do them all...
		if ($context['display_mode'] == 0)
			markMessages(null, $context['current_label_id']);
		// Otherwise do just the current one!
		elseif (!empty($context['current_pm']))
			markMessages($display_pms, $context['current_label_id']);
	}

	// Build the conversation button array.
	if ($context['display_mode'] === 2 && !empty($context['current_pm']))
	{
		$context['conversation_buttons'] = array(
			'delete' => array('text' => 'delete_conversation', 'image' => 'delete.png', 'lang' => true, 'url' => $scripturl . '?action=pm;sa=pmactions;pm_actions[' . $context['current_pm'] . ']=delete;conversation;f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ';' . $context['session_var'] . '=' . $context['session_id'], 'custom' => 'onclick="return confirm(\'' . addslashes($txt['remove_message']) . '?\');"'),
		);

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_conversation_buttons');
	}
}

/**
 * Get a personal message for the theme.  (used to save memory.)
 *
 * @param $type
 * @param $reset
 */
function prepareMessageContext($type = 'subject', $reset = false)
{
	global $txt, $scripturl, $modSettings, $settings, $context, $messages_request, $memberContext, $recipients, $smcFunc;
	global $user_info, $subjects_request;

	// Count the current message number....
	static $counter = null;
	if ($counter === null || $reset)
		$counter = $context['start'];

	static $temp_pm_selected = null;
	if ($temp_pm_selected === null)
	{
		$temp_pm_selected = isset($_SESSION['pm_selected']) ? $_SESSION['pm_selected'] : array();
		$_SESSION['pm_selected'] = array();
	}

	// If we're in non-boring view do something exciting!
	if ($context['display_mode'] != 0 && $subjects_request && $type == 'subject')
	{
		$subject = $smcFunc['db_fetch_assoc']($subjects_request);
		if (!$subject)
		{
			$smcFunc['db_free_result']($subjects_request);
			return false;
		}

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
			'time' => timeformat($subject['msgtime']),
			'timestamp' => forum_time(true, $subject['msgtime']),
			'number_recipients' => count($recipients[$subject['id_pm']]['to']),
			'labels' => &$context['message_labels'][$subject['id_pm']],
			'fully_labeled' => count($context['message_labels'][$subject['id_pm']]) == count($context['labels']),
			'is_replied_to' => &$context['message_replied'][$subject['id_pm']],
			'is_unread' => &$context['message_unread'][$subject['id_pm']],
			'is_selected' => !empty($temp_pm_selected) && in_array($subject['id_pm'], $temp_pm_selected),
		);

		return $output;
	}

	// Bail if it's false, ie. no messages.
	if ($messages_request == false)
		return false;

	// Reset the data?
	if ($reset == true)
		return @$smcFunc['db_data_seek']($messages_request, 0);

	// Get the next one... bail if anything goes wrong.
	$message = $smcFunc['db_fetch_assoc']($messages_request);
	if (!$message)
	{
		if ($type != 'subject')
			$smcFunc['db_free_result']($messages_request);

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

	// Run UBBC interpreter on the message.
	$message['body'] = parse_bbc($message['body'], true, 'pm' . $message['id_pm']);

	// Send the array.
	$output = array(
		'alternate' => $counter % 2,
		'id' => $message['id_pm'],
		'member' => &$memberContext[$message['id_member_from']],
		'subject' => $message['subject'],
		'time' => timeformat($message['msgtime']),
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

	$counter++;

	return $output;
}

/**
 * Send a new message?
 */
function action_sendmessage()
{
	global $txt, $scripturl, $modSettings;
	global $context, $options, $smcFunc, $language, $user_info;

	isAllowedTo('pm_send');

	loadLanguage('PersonalMessage');

	// Just in case it was loaded from somewhere else.
	loadTemplate('PersonalMessage');
	$context['sub_template'] = 'send';

	// Extract out the spam settings - cause it's neat.
	list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

	// Set the title...
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

	// Quoting/Replying to a message?
	if (!empty($_REQUEST['pmsg']))
	{
		$pmsg = (int) $_REQUEST['pmsg'];

		// Make sure this is yours.
		if (!isAccessiblePM($pmsg))
			fatal_lang_error('no_access', false);

		// Work out whether this is one you've received?
		$request = $smcFunc['db_query']('', '
			SELECT
				id_pm
			FROM {db_prefix}pm_recipients
			WHERE id_pm = {int:id_pm}
				AND id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'id_pm' => $pmsg,
			)
		);
		$isReceived = $smcFunc['db_num_rows']($request) != 0;
		$smcFunc['db_free_result']($request);

		// Get the quoted message (and make sure you're allowed to see this quote!).
		$request = $smcFunc['db_query']('', '
			SELECT
				pm.id_pm, CASE WHEN pm.id_pm_head = {int:id_pm_head_empty} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
				pm.body, pm.subject, pm.msgtime, mem.member_name, IFNULL(mem.id_member, 0) AS id_member,
				IFNULL(mem.real_name, pm.from_name) AS real_name
			FROM {db_prefix}personal_messages AS pm' . (!$isReceived ? '' : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:id_pm})') . '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:id_pm}' . (!$isReceived ? '
				AND pm.id_member_from = {int:current_member}' : '
				AND pmr.id_member = {int:current_member}') . '
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'id_pm_head_empty' => 0,
				'id_pm' => $pmsg,
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('pm_not_yours', false);
		$row_quoted = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Censor the message.
		censorText($row_quoted['subject']);
		censorText($row_quoted['body']);

		// Add 'Re: ' to it....
		if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
		{
			if ($language === $user_info['language'])
				$context['response_prefix'] = $txt['response_prefix'];
			else
			{
				loadLanguage('index', $language, false);
				$context['response_prefix'] = $txt['response_prefix'];
				loadLanguage('index');
			}
			cache_put_data('response_prefix', $context['response_prefix'], 600);
		}
		$form_subject = $row_quoted['subject'];
		if ($context['reply'] && trim($context['response_prefix']) != '' && $smcFunc['strpos']($form_subject, trim($context['response_prefix'])) !== 0)
			$form_subject = $context['response_prefix'] . $form_subject;

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
			'time' => timeformat($row_quoted['msgtime']),
			'timestamp' => forum_time(true, $row_quoted['msgtime']),
			'body' => $row_quoted['body']
		);
	}
	else
	{
		$context['quoted_message'] = false;
		$form_subject = '';
		$form_message = '';
	}

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
				$context['recipients']['to'][] = array(
					'id' => $row_quoted['id_member'],
					'name' => htmlspecialchars($row_quoted['real_name']),
				);

			// Now to get the others.
			$request = $smcFunc['db_query']('', '
				SELECT mem.id_member, mem.real_name
				FROM {db_prefix}pm_recipients AS pmr
					INNER JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)
				WHERE pmr.id_pm = {int:id_pm}
					AND pmr.id_member != {int:current_member}
					AND pmr.bcc = {int:not_bcc}',
				array(
					'current_member' => $user_info['id'],
					'id_pm' => $pmsg,
					'not_bcc' => 0,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['recipients']['to'][] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				);
			$smcFunc['db_free_result']($request);
		}
		else
		{
			$_REQUEST['u'] = explode(',', $_REQUEST['u']);
			foreach ($_REQUEST['u'] as $key => $uID)
				$_REQUEST['u'][$key] = (int) $uID;

			$_REQUEST['u'] = array_unique($_REQUEST['u']);

			$request = $smcFunc['db_query']('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:member_list})
				LIMIT ' . count($_REQUEST['u']),
				array(
					'member_list' => $_REQUEST['u'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['recipients']['to'][] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				);
			$smcFunc['db_free_result']($request);
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
	$context['copy_to_outbox'] = !empty($options['copy_to_outbox']);

	// And build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=send',
		'name' => $txt['new_message']
	);

	$modSettings['disable_wysiwyg'] = !empty($modSettings['disable_wysiwyg']) || empty($modSettings['enableBBC']);

	// Generate a list of drafts that they can load in to the editor
	if (!empty($context['drafts_pm_save']))
	{
		require_once(CONTROLLERDIR . '/Drafts.controller.php');
		$pm_seed = isset($_REQUEST['pmsg']) ? $_REQUEST['pmsg'] : (isset($_REQUEST['quote']) ? $_REQUEST['quote'] : 0);
		action_showDrafts($user_info['id'], $pm_seed, 1);
	}

	// Needed for the WYSIWYG editor.
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

	// Store the ID for old compatibility.
	$context['post_box_name'] = $editorOptions['id'];

	$context['bcc_value'] = '';

	$context['require_verification'] = !$user_info['is_admin'] && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'];
	if ($context['require_verification'])
	{
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
 * This function allows the user to view their PM drafts
 * Accessed by ?action=pm;sa=showpmdrafts
 */
function action_messagedrafts()
{
	global $user_info;

	// validate with loadMemberData()
	$memberResult = loadMemberData($user_info['id'], false);
	if (!is_array($memberResult))
		fatal_lang_error('not_a_user', false);
	list ($memID) = $memberResult;

	// drafts is where the functions reside
	require_once(CONTROLLERDIR . '/Drafts.controller.php');
	action_showPMDrafts($memID);
}

/**
 * An error in the message...
 *
 * @param $named_recipients
 * @param $recipient_ids
 */
function messagePostError($named_recipients, $recipient_ids = array())
{
	global $txt, $context, $scripturl, $modSettings;
	global $smcFunc, $user_info;

	if (!isset($_REQUEST['xml']))
		$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'send';

	if (!isset($_REQUEST['xml']))
		$context['sub_template'] = 'send';
	elseif (isset($_REQUEST['xml']))
		$context['sub_template'] = 'pm';

	$context['page_title'] = $txt['send_message'];
	$error_types = error_context::context('pm', 1);

	// Got some known members?
	$context['recipients'] = array(
		'to' => array(),
		'bcc' => array(),
	);
	if (!empty($recipient_ids['to']) || !empty($recipient_ids['bcc']))
	{
		$allRecipients = array_merge($recipient_ids['to'], $recipient_ids['bcc']);

		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $allRecipients,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$recipientType = in_array($row['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';
			$context['recipients'][$recipientType][] = array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// Set everything up like before....
	$context['subject'] = isset($_REQUEST['subject']) ? $smcFunc['htmlspecialchars']($_REQUEST['subject']) : '';
	$context['message'] = isset($_REQUEST['message']) ? str_replace(array('  '), array('&nbsp; '), $smcFunc['htmlspecialchars']($_REQUEST['message'])) : '';
	$context['copy_to_outbox'] = !empty($_REQUEST['outbox']);
	$context['reply'] = !empty($_REQUEST['replied_to']);

	if ($context['reply'])
	{
		$_REQUEST['replied_to'] = (int) $_REQUEST['replied_to'];

		$request = $smcFunc['db_query']('', '
			SELECT
				pm.id_pm, CASE WHEN pm.id_pm_head = {int:no_id_pm_head} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
				pm.body, pm.subject, pm.msgtime, mem.member_name, IFNULL(mem.id_member, 0) AS id_member,
				IFNULL(mem.real_name, pm.from_name) AS real_name
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '' : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:replied_to})') . '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:replied_to}' . ($context['folder'] == 'sent' ? '
				AND pm.id_member_from = {int:current_member}' : '
				AND pmr.id_member = {int:current_member}') . '
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'no_id_pm_head' => 0,
				'replied_to' => $_REQUEST['replied_to'],
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
		{
			if (!isset($_REQUEST['xml']))
				fatal_lang_error('pm_not_yours', false);
			else
				$error_types->addError('pm_not_yours');
		}
		$row_quoted = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

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
			'time' => timeformat($row_quoted['msgtime']),
			'timestamp' => forum_time(true, $row_quoted['msgtime']),
			'body' => parse_bbc($row_quoted['body'], true, 'pm' . $row_quoted['id_pm']),
		);
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

	// ... and store the ID again...
	$context['post_box_name'] = $editorOptions['id'];

	// Check whether we need to show the code again.
	$context['require_verification'] = !$user_info['is_admin'] && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'];
	if ($context['require_verification'] && !isset($_REQUEST['xml']))
	{
		require_once(SUBSDIR . '/Editor.subs.php');
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
 * Actually send a personal message.
 */
function action_sendmessage2()
{
	global $txt, $context;
	global $user_info, $modSettings, $scripturl, $smcFunc;

	isAllowedTo('pm_send');
	require_once(SUBSDIR . '/Auth.subs.php');
	require_once(SUBSDIR . '/Post.subs.php');

	// PM Drafts enabled and needed?
	if ($context['drafts_pm_save'] && (isset($_POST['save_draft']) || isset($_POST['id_pm_draft'])))
		require_once(CONTROLLERDIR . '/Drafts.controller.php');

	loadLanguage('PersonalMessage', '', false);

	// Extract out the spam settings - it saves database space!
	list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

	// Initialize the errors we're about to make.
	$post_errors = error_context::context('pm', 1);

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

	$_REQUEST['subject'] = isset($_REQUEST['subject']) ? trim($_REQUEST['subject']) : '';
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

			foreach ($namedRecipientList[$recipientType] as $index => $recipient)
			{
				if (strlen(trim($recipient)) > 0)
					$namedRecipientList[$recipientType][$index] = $smcFunc['htmlspecialchars']($smcFunc['strtolower'](trim($recipient)));
				else
					unset($namedRecipientList[$recipientType][$index]);
			}

			if (!empty($namedRecipientList[$recipientType]))
			{
				$foundMembers = findMembers($namedRecipientList[$recipientType]);

				// Assume all are not found, until proven otherwise.
				$namesNotFound[$recipientType] = $namedRecipientList[$recipientType];

				foreach ($foundMembers as $member)
				{
					$testNames = array(
						$smcFunc['strtolower']($member['username']),
						$smcFunc['strtolower']($member['name']),
						$smcFunc['strtolower']($member['email']),
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
		foreach ($recipientList as $recipientType => $dummy)
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

	// Did they make any mistakes?
	if ($_REQUEST['subject'] == '')
		$post_errors->addError('no_subject');
	if (!isset($_REQUEST['message']) || $_REQUEST['message'] == '')
		$post_errors->addError('no_message');
	elseif (!empty($modSettings['max_messageLength']) && $smcFunc['strlen']($_REQUEST['message']) > $modSettings['max_messageLength'])
		$post_errors->addError('long_message');
	else
	{
		// Preparse the message.
		$message = $_REQUEST['message'];
		preparsecode($message);

		// Make sure there's still some content left without the tags.
		if ($smcFunc['htmltrim'](strip_tags(parse_bbc($smcFunc['htmlspecialchars']($message, ENT_QUOTES), false), '<img>')) === '' && (!allowedTo('admin_forum') || strpos($message, '[html]') === false))
			$post_errors->addError('no_message');
	}

	// Wrong verification code?
	if (!$user_info['is_admin'] && !isset($_REQUEST['xml']) && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'])
	{
		require_once(SUBSDIR . '/Editor.subs.php');
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);

		if ($context['require_verification'])
			$post_errors->addError('need_qr_verification', 0);
	}

	// If they did, give a chance to make ammends.
	if ($post_errors->hasErrors() && !$is_recipient_change && !isset($_REQUEST['preview']) && !isset($_REQUEST['xml']))
		return messagePostError($namedRecipientList, $recipientList);

	// Want to take a second glance before you send?
	if (isset($_REQUEST['preview']))
	{
		// Set everything up to be displayed.
		$context['preview_subject'] = $smcFunc['htmlspecialchars']($_REQUEST['subject']);
		$context['preview_message'] = $smcFunc['htmlspecialchars']($_REQUEST['message'], ENT_QUOTES);
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

	// Do the actual sending of the PM.
	if (!empty($recipientList['to']) || !empty($recipientList['bcc']))
		$context['send_log'] = sendpm($recipientList, $_REQUEST['subject'], $_REQUEST['message'], !empty($_REQUEST['outbox']), null, !empty($_REQUEST['pm_head']) ? (int) $_REQUEST['pm_head'] : 0);
	else
		$context['send_log'] = array(
			'sent' => array(),
			'failed' => array()
		);

	// Mark the message as "replied to".
	if (!empty($context['send_log']['sent']) && !empty($_REQUEST['replied_to']) && isset($_REQUEST['f']) && $_REQUEST['f'] == 'inbox')
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pm_recipients
			SET is_read = is_read | 2
			WHERE id_pm = {int:replied_to}
				AND id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'replied_to' => (int) $_REQUEST['replied_to'],
			)
		);
	}

	// If one or more of the recipient were invalid, go back to the post screen with the failed usernames.
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
 * This function performs all additional stuff...
 */
function action_messageactions()
{
	global $txt, $context, $user_info, $options, $smcFunc;

	checkSession('request');

	if (isset($_REQUEST['del_selected']))
		$_REQUEST['pm_action'] = 'delete';

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
		$id_pms = array();
		foreach ($_REQUEST['pm_actions'] as $pm => $dummy)
			$id_pms[] = (int) $pm;

		$request = $smcFunc['db_query']('', '
			SELECT id_pm_head, id_pm
			FROM {db_prefix}personal_messages
			WHERE id_pm IN ({array_int:id_pms})',
			array(
				'id_pms' => $id_pms,
			)
		);
		$pm_heads = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$pm_heads[$row['id_pm_head']] = $row['id_pm'];
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT id_pm, id_pm_head
			FROM {db_prefix}personal_messages
			WHERE id_pm_head IN ({array_int:pm_heads})',
			array(
				'pm_heads' => array_keys($pm_heads),
			)
		);
		// Copy the action from the single to PM to the others.
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (isset($pm_heads[$row['id_pm_head']]) && isset($_REQUEST['pm_actions'][$pm_heads[$row['id_pm_head']]]))
				$_REQUEST['pm_actions'][$row['id_pm']] = $_REQUEST['pm_actions'][$pm_heads[$row['id_pm_head']]];
		}
		$smcFunc['db_free_result']($request);
	}

	$to_delete = array();
	$to_label = array();
	$label_type = array();
	foreach ($_REQUEST['pm_actions'] as $pm => $action)
	{
		if ($action === 'delete')
			$to_delete[] = (int) $pm;
		else
		{
			if (substr($action, 0, 4) == 'add_')
			{
				$type = 'add';
				$action = substr($action, 4);
			}
			elseif (substr($action, 0, 4) == 'rem_')
			{
				$type = 'rem';
				$action = substr($action, 4);
			}
			else
				$type = 'unk';

			if ($action == '-1' || $action == '0' || (int) $action > 0)
			{
				$to_label[(int) $pm] = (int) $action;
				$label_type[(int) $pm] = $type;
			}
		}
	}

	// Deleting, it looks like?
	if (!empty($to_delete))
		deleteMessages($to_delete, $context['display_mode'] == 2 ? null : $context['folder']);

	// Are we labeling anything?
	if (!empty($to_label) && $context['folder'] == 'inbox')
	{
		$updateErrors = 0;

		// Get information about each message...
		$request = $smcFunc['db_query']('', '
			SELECT id_pm, labels
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:current_member}
				AND id_pm IN ({array_int:to_label})
			LIMIT ' . count($to_label),
			array(
				'current_member' => $user_info['id'],
				'to_label' => array_keys($to_label),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$labels = $row['labels'] == '' ? array('-1') : explode(',', trim($row['labels']));

			// Already exists?  Then... unset it!
			$ID_LABEL = array_search($to_label[$row['id_pm']], $labels);
			if ($ID_LABEL !== false && $label_type[$row['id_pm']] !== 'add')
				unset($labels[$ID_LABEL]);
			elseif ($label_type[$row['id_pm']] !== 'rem')
				$labels[] = $to_label[$row['id_pm']];

			if (!empty($options['pm_remove_inbox_label']) && $to_label[$row['id_pm']] != '-1' && ($key = array_search('-1', $labels)) !== false)
				unset($labels[$key]);

			$set = implode(',', array_unique($labels));
			if ($set == '')
				$set = '-1';

			// Check that this string isn't going to be too large for the database.
			if ($set > 60)
				$updateErrors++;
			else
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}pm_recipients
					SET labels = {string:labels}
					WHERE id_pm = {int:id_pm}
						AND id_member = {int:current_member}',
					array(
						'current_member' => $user_info['id'],
						'id_pm' => $row['id_pm'],
						'labels' => $set,
					)
				);
			}
		}
		$smcFunc['db_free_result']($request);

		// Any errors?
		// @todo Separate the sprintf?
		if (!empty($updateErrors))
			fatal_lang_error('labels_too_many', true, array($updateErrors));
	}

	// Back to the folder.
	$_SESSION['pm_selected'] = array_keys($to_label);
	redirectexit($context['current_label_redirect'] . (count($to_label) == 1 ? '#msg' . $_SESSION['pm_selected'][0] : ''), count($to_label) == 1 && isBrowser('ie'));
}

/**
 * Are you sure you want to PERMANENTLY (mostly) delete ALL your messages?
 */
function action_removemessage()
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
function action_removemessage2()
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
 * This function allows the user to delete all messages older than so many days.
 */
function action_messageprune()
{
	global $txt, $context, $user_info, $scripturl, $smcFunc;

	// Actually delete the messages.
	if (isset($_REQUEST['age']))
	{
		checkSession();

		// Calculate the time to delete before.
		$deleteTime = max(0, time() - (86400 * (int) $_REQUEST['age']));

		// Array to store the IDs in.
		$toDelete = array();

		// Select all the messages they have sent older than $deleteTime.
		$request = $smcFunc['db_query']('', '
			SELECT id_pm
			FROM {db_prefix}personal_messages
			WHERE deleted_by_sender = {int:not_deleted}
				AND id_member_from = {int:current_member}
				AND msgtime < {int:msgtime}',
			array(
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
				'msgtime' => $deleteTime,
			)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$toDelete[] = $row[0];
		$smcFunc['db_free_result']($request);

		// Select all messages in their inbox older than $deleteTime.
		$request = $smcFunc['db_query']('', '
			SELECT pmr.id_pm
			FROM {db_prefix}pm_recipients AS pmr
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			WHERE pmr.deleted = {int:not_deleted}
				AND pmr.id_member = {int:current_member}
				AND pm.msgtime < {int:msgtime}',
			array(
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
				'msgtime' => $deleteTime,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$toDelete[] = $row['id_pm'];
		$smcFunc['db_free_result']($request);

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
function action_messagelabels()
{
	global $txt, $context, $user_info, $scripturl, $smcFunc;

	// Build the link tree elements...
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=manlabels',
		'name' => $txt['pm_manage_labels']
	);

	$context['page_title'] = $txt['pm_manage_labels'];
	$context['sub_template'] = 'labels';

	$the_labels = array();
	// Add all existing labels to the array to save, slashing them as necessary...
	foreach ($context['labels'] as $label)
	{
		if ($label['id'] != -1)
			$the_labels[$label['id']] = $label['name'];
	}

	if (isset($_POST[$context['session_var']]))
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
			$_POST['label'] = strtr($smcFunc['htmlspecialchars'](trim($_POST['label'])), array(',' => '&#044;'));

			if ($smcFunc['strlen']($_POST['label']) > 30)
				$_POST['label'] = $smcFunc['substr']($_POST['label'], 0, 30);
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
					$_POST['label_name'][$id] = trim(strtr($smcFunc['htmlspecialchars']($_POST['label_name'][$id]), array(',' => '&#044;')));

					if ($smcFunc['strlen']($_POST['label_name'][$id]) > 30)
						$_POST['label_name'][$id] = $smcFunc['substr']($_POST['label_name'][$id], 0, 30);
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

			// Now find the messages to change.
			$request = $smcFunc['db_query']('', '
				SELECT id_pm, labels
				FROM {db_prefix}pm_recipients
				WHERE FIND_IN_SET({raw:find_label_implode}, labels) != 0
					AND id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
					'find_label_implode' => '\'' . implode('\', labels) != 0 OR FIND_IN_SET(\'', $searchArray) . '\'',
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// Do the long task of updating them...
				$toChange = explode(',', $row['labels']);

				foreach ($toChange as $key => $value)
					if (in_array($value, $searchArray))
					{
						if (isset($new_labels[$value]))
							$toChange[$key] = $new_labels[$value];
						else
							unset($toChange[$key]);
					}

				if (empty($toChange))
					$toChange[] = '-1';

				// Update the message.
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}pm_recipients
					SET labels = {string:new_labels}
					WHERE id_pm = {int:id_pm}
						AND id_member = {int:current_member}',
					array(
						'current_member' => $user_info['id'],
						'id_pm' => $row['id_pm'],
						'new_labels' => implode(',', array_unique($toChange)),
					)
				);
			}
			$smcFunc['db_free_result']($request);

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
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}pm_rules
						SET actions = {string:actions}
						WHERE id_rule = {int:id_rule}
							AND id_member = {int:current_member}',
						array(
							'current_member' => $user_info['id'],
							'id_rule' => $id,
							'actions' => serialize($context['rules'][$id]['actions']),
						)
					);
					unset($rule_changes[$k]);
				}

			// Anything left here means it's lost all actions...
			if (!empty($rule_changes))
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}pm_rules
					WHERE id_rule IN ({array_int:rule_list})
							AND id_member = {int:current_member}',
					array(
						'current_member' => $user_info['id'],
						'rule_list' => $rule_changes,
					)
				);
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
function action_messagesettings()
{
	global $txt, $user_settings, $user_info, $context, $smcFunc;
	global $scripturl, $profile_vars, $cur_profile, $user_profile;

	// We want them to submit back to here.
	$context['profile_custom_submit_url'] = $scripturl . '?action=pm;sa=settings;save';

	loadMemberData($user_info['id'], false, 'profile');
	$cur_profile = $user_profile[$user_info['id']];

	loadLanguage('Profile');
	loadTemplate('Profile');

	$context['page_title'] = $txt['pm_settings'];
	$context['user']['is_owner'] = true;
	$context['id_member'] = $user_info['id'];
	$context['require_password'] = false;
	$context['menu_item_selected'] = 'settings';
	$context['submit_button_text'] = $txt['pm_settings'];
	$context['profile_header_text'] = $txt['personal_messages'];

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
		require_once(SUBSDIR . '/Profile.subs.php');
		saveProfileFields();

		if (!empty($profile_vars))
			updateMemberData($user_info['id'], $profile_vars);
	}

	// Load up the fields.
	require_once(CONTROLLERDIR . '/ProfileOptions.controller.php');
	action_pmprefs($user_info['id']);
}

/**
 * Allows the user to report a personal message to an administrator.
 *
 * - In the first instance requires that the ID of the message to report is passed through $_GET.
 * - It allows the user to report to either a particular administrator - or the whole admin team.
 * - It will forward on a copy of the original message without allowing the reporter to make changes.
 *
 * @uses report_message sub-template.
 */
function action_reportmessage()
{
	global $txt, $context, $scripturl;
	global $user_info, $language, $modSettings, $smcFunc;

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

		// First, pull out the message contents, and verify it actually went to them!
		$request = $smcFunc['db_query']('', '
			SELECT pm.subject, pm.body, pm.msgtime, pm.id_member_from, IFNULL(m.real_name, pm.from_name) AS sender_name
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				LEFT JOIN {db_prefix}members AS m ON (m.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:id_pm}
				AND pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'id_pm' => $context['pm_id'],
				'not_deleted' => 0,
			)
		);
		// Can only be a hacker here!
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('no_access', false);
		list ($subject, $body, $time, $memberFromID, $memberFromName) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Remove the line breaks...
		$body = preg_replace('~<br ?/?' . '>~i', "\n", $body);

		// Get any other recipients of the email.
		$request = $smcFunc['db_query']('', '
			SELECT mem_to.id_member AS id_member_to, mem_to.real_name AS to_name, pmr.bcc
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm = {int:id_pm}
				AND pmr.id_member != {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'id_pm' => $context['pm_id'],
			)
		);
		$recipients = array();
		$hidden_recipients = 0;
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// If it's hidden still don't reveal their names - privacy after all ;)
			if ($row['bcc'])
				$hidden_recipients++;
			else
				$recipients[] = '[url=' . $scripturl . '?action=profile;u=' . $row['id_member_to'] . ']' . $row['to_name'] . '[/url]';
		}
		$smcFunc['db_free_result']($request);

		if ($hidden_recipients)
			$recipients[] = sprintf($txt['pm_report_pm_hidden'], $hidden_recipients);

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
					'subject' => ($smcFunc['strpos']($subject, $txt['pm_report_pm_subject']) === false ? $txt['pm_report_pm_subject'] : '') . un_htmlspecialchars($subject),
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
 * List all rules, and allow adding/entering etc...
 */
function action_messagerules()
{
	global $txt, $context, $user_info, $scripturl, $smcFunc;

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
	$request = $smcFunc['db_query']('', '
		SELECT mg.id_group, mg.group_name, IFNULL(gm.id_member, 0) AS can_moderate, mg.hidden
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:current_member})
		WHERE mg.min_posts = {int:min_posts}
			AND mg.id_group != {int:moderator_group}
			AND mg.hidden = {int:not_hidden}
		ORDER BY mg.group_name',
		array(
			'current_member' => $user_info['id'],
			'min_posts' => -1,
			'moderator_group' => 3,
			'not_hidden' => 0,
		)
	);
	$context['groups'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Hide hidden groups!
		if ($row['hidden'] && !$row['can_moderate'] && !allowedTo('manage_membergroups'))
			continue;

		$context['groups'][$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db_free_result']($request);

	// Applying all rules?
	if (isset($_GET['apply']))
	{
		checkSession('get');

		applyRules(true);
		redirectexit('action=pm;sa=manrules');
	}
	// Editing a specific one?
	if (isset($_GET['add']))
	{
		$context['rid'] = isset($_GET['rid']) && isset($context['rules'][$_GET['rid']])? (int) $_GET['rid'] : 0;
		$context['sub_template'] = 'add_rule';

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
				$request = $smcFunc['db_query']('', '
					SELECT id_member, member_name
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:member_list})',
					array(
						'member_list' => array_keys($members),
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$context['rule']['criteria'][$members[$row['id_member']]]['v'] = $row['member_name'];
				$smcFunc['db_free_result']($request);
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
	}
	// Saving?
	elseif (isset($_GET['save']))
	{
		checkSession('post');
		$context['rid'] = isset($_GET['rid']) && isset($context['rules'][$_GET['rid']])? (int) $_GET['rid'] : 0;

		// Name is easy!
		$ruleName = $smcFunc['htmlspecialchars'](trim($_POST['rule_name']));
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
				$name = trim($_POST['ruledef'][$ind]);
				$request = $smcFunc['db_query']('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE real_name = {string:member_name}
						OR member_name = {string:member_name}',
					array(
						'member_name' => $name,
					)
				);
				if ($smcFunc['db_num_rows']($request) == 0)
					continue;
				list ($memID) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				$criteria[] = array('t' => 'mid', 'v' => $memID);
			}
			elseif ($type == 'bud')
				$criteria[] = array('t' => 'bud', 'v' => 1);
			elseif ($type == 'gid')
				$criteria[] = array('t' => 'gid', 'v' => (int) $_POST['ruledefgroup'][$ind]);
			elseif (in_array($type, array('sub', 'msg')) && trim($_POST['ruledef'][$ind]) != '')
				$criteria[] = array('t' => $type, 'v' => $smcFunc['htmlspecialchars'](trim($_POST['ruledef'][$ind])));
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
			$smcFunc['db_insert']('',
				'{db_prefix}pm_rules',
				array(
					'id_member' => 'int', 'rule_name' => 'string', 'criteria' => 'string', 'actions' => 'string',
					'delete_pm' => 'int', 'is_or' => 'int',
				),
				array(
					$user_info['id'], $ruleName, $criteria, $actions, $doDelete, $isOr,
				),
				array('id_rule')
			);
		else
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}pm_rules
				SET rule_name = {string:rule_name}, criteria = {string:criteria}, actions = {string:actions},
					delete_pm = {int:delete_pm}, is_or = {int:is_or}
				WHERE id_rule = {int:id_rule}
					AND id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
					'delete_pm' => $doDelete,
					'is_or' => $isOr,
					'id_rule' => $context['rid'],
					'rule_name' => $ruleName,
					'criteria' => $criteria,
					'actions' => $actions,
				)
			);

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
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}pm_rules
				WHERE id_rule IN ({array_int:delete_list})
					AND id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
					'delete_list' => $toDelete,
				)
			);

		redirectexit('action=pm;sa=manrules');
	}
}

/**
 * This will apply rules to all unread messages.
 * If all_messages is set will, clearly, do it to all!
 *
 * @param bool $all_messages = false
 */
function applyRules($all_messages = false)
{
	global $user_info, $smcFunc, $context, $options;

	// Want this - duh!
	loadRules();

	// No rules?
	if (empty($context['rules']))
		return;

	// Just unread ones?
	$ruleQuery = $all_messages ? '' : ' AND pmr.is_new = 1';

	// @todo Apply all should have timeout protection!
	// Get all the messages that match this.
	$request = $smcFunc['db_query']('', '
		SELECT
			pmr.id_pm, pm.id_member_from, pm.subject, pm.body, mem.id_group, pmr.labels
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
		WHERE pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}
			' . $ruleQuery,
		array(
			'current_member' => $user_info['id'],
			'not_deleted' => 0,
		)
	);
	$actions = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		foreach ($context['rules'] as $rule)
		{
			$match = false;
			// Loop through all the criteria hoping to make a match.
			foreach ($rule['criteria'] as $criterium)
			{
				if (($criterium['t'] == 'mid' && $criterium['v'] == $row['id_member_from']) || ($criterium['t'] == 'gid' && $criterium['v'] == $row['id_group']) || ($criterium['t'] == 'sub' && strpos($row['subject'], $criterium['v']) !== false) || ($criterium['t'] == 'msg' && strpos($row['body'], $criterium['v']) !== false))
					$match = true;
				// If we're adding and one criteria don't match then we stop!
				elseif ($rule['logic'] == 'and')
				{
					$match = false;
					break;
				}
			}

			// If we have a match the rule must be true - act!
			if ($match)
			{
				if ($rule['delete'])
					$actions['deletes'][] = $row['id_pm'];
				else
				{
					foreach ($rule['actions'] as $ruleAction)
					{
						if ($ruleAction['t'] == 'lab')
						{
							// Get a basic pot started!
							if (!isset($actions['labels'][$row['id_pm']]))
								$actions['labels'][$row['id_pm']] = empty($row['labels']) ? array() : explode(',', $row['labels']);
							$actions['labels'][$row['id_pm']][] = $ruleAction['v'];
						}
					}
				}
			}
		}
	}
	$smcFunc['db_free_result']($request);

	// Deletes are easy!
	if (!empty($actions['deletes']))
		deleteMessages($actions['deletes']);

	// Relabel?
	if (!empty($actions['labels']))
	{
		foreach ($actions['labels'] as $pm => $labels)
		{
			// Quickly check each label is valid!
			$realLabels = array();
			foreach ($context['labels'] as $label)
				if (in_array($label['id'], $labels) && ($label['id'] != -1 || empty($options['pm_remove_inbox_label'])))
					$realLabels[] = $label['id'];

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}pm_recipients
				SET labels = {string:new_labels}
				WHERE id_pm = {int:id_pm}
					AND id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
					'id_pm' => $pm,
					'new_labels' => empty($realLabels) ? '' : implode(',', $realLabels),
				)
			);
		}
	}
}

/**
 * Load up all the rules for the current user.
 *
 * @param bool $reload = false
 */
function loadRules($reload = false)
{
	global $user_info, $context, $smcFunc;

	if (isset($context['rules']) && !$reload)
		return;

	$request = $smcFunc['db_query']('', '
		SELECT
			id_rule, rule_name, criteria, actions, delete_pm, is_or
		FROM {db_prefix}pm_rules
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $user_info['id'],
		)
	);
	$context['rules'] = array();
	// Simply fill in the data!
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['rules'][$row['id_rule']] = array(
			'id' => $row['id_rule'],
			'name' => $row['rule_name'],
			'criteria' => unserialize($row['criteria']),
			'actions' => unserialize($row['actions']),
			'delete' => $row['delete_pm'],
			'logic' => $row['is_or'] ? 'or' : 'and',
		);

		if ($row['delete_pm'])
			$context['rules'][$row['id_rule']]['actions'][] = array('t' => 'del', 'v' => 1);
	}
	$smcFunc['db_free_result']($request);
}
