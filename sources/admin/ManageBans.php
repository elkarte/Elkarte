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
 * This file contains all the functions used for the ban center.
 * @todo refactor as controller-model
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Ban center. The main entrance point for all ban center functions.
 * It is accesssed by ?action=admin;area=ban.
 * It choses a function based on the 'sa' parameter, like many others.
 * The default sub-action is action_list().
 * It requires the ban_members permission.
 * It initializes the admin tabs.
 *
 * @uses ManageBans template.
 */
function Ban()
{
	global $context, $txt, $scripturl;

	isAllowedTo('manage_bans');

	loadTemplate('ManageBans');
	require_once(SUBSDIR . '/Bans.subs.php');

	$subActions = array(
		'add' => 'BanEdit',
		'browse' => 'action_browse',
		'edittrigger' => 'action_edittrigger',
		'edit' => 'BanEdit',
		'list' => 'action_list',
		'log' => 'action_log',
	);

	call_integration_hook('integrate_manage_bans', array(&$subActions));

	// Default the sub-action to 'view ban list'.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'list';

	$context['page_title'] = $txt['ban_title'];
	$context['sub_action'] = $_REQUEST['sa'];

	// Tabs for browsing the different ban functions.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['ban_title'],
		'help' => 'ban_members',
		'description' => $txt['ban_description'],
		'tabs' => array(
			'list' => array(
				'description' => $txt['ban_description'],
				'href' => $scripturl . '?action=admin;area=ban;sa=list',
				'is_selected' => $_REQUEST['sa'] == 'list' || $_REQUEST['sa'] == 'edit' || $_REQUEST['sa'] == 'edittrigger',
			),
			'add' => array(
				'description' => $txt['ban_description'],
				'href' => $scripturl . '?action=admin;area=ban;sa=add',
				'is_selected' => $_REQUEST['sa'] == 'add',
			),
			'browse' => array(
				'description' => $txt['ban_trigger_browse_description'],
				'href' => $scripturl . '?action=admin;area=ban;sa=browse',
				'is_selected' => $_REQUEST['sa'] == 'browse',
			),
			'log' => array(
				'description' => $txt['ban_log_description'],
				'href' => $scripturl . '?action=admin;area=ban;sa=log',
				'is_selected' => $_REQUEST['sa'] == 'log',
				'is_last' => true,
			),
		),
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

/**
 * Shows a list of bans currently set.
 * It is accesssed by ?action=admin;area=ban;sa=list.
 * It removes expired bans.
 * It allows sorting on different criteria.
 * It also handles removal of selected ban items.
 *
 * @uses the main ManageBans template.
 */
function action_list()
{
	global $txt, $context, $ban_request, $ban_counts, $scripturl;
	global $user_info, $smcFunc;

	// User pressed the 'remove selection button'.
	if (!empty($_POST['removeBans']) && !empty($_POST['remove']) && is_array($_POST['remove']))
	{
		checkSession();

		// Make sure every entry is a proper integer.
		array_map('intval', $_POST['remove']);

		// Unban them all!
		removeBanGroups($_POST['remove']);
		removeBanTriggers($_POST['remove']);

		// No more caching this ban!
		updateSettings(array('banLastUpdated' => time()));

		// Some members might be unbanned now. Update the members table.
		updateBanMembers();
	}

	// Create a date string so we don't overload them with date info.
	if (preg_match('~%[AaBbCcDdeGghjmuYy](?:[^%]*%[AaBbCcDdeGghjmuYy])*~', $user_info['time_format'], $matches) == 0 || empty($matches[0]))
		$context['ban_time_format'] = $user_info['time_format'];
	else
		$context['ban_time_format'] = $matches[0];

	$listOptions = array(
		'id' => 'ban_list',
		'title' => $txt['ban_title'],
		'items_per_page' => 20,
		'base_href' => $scripturl . '?action=admin;area=ban;sa=list',
		'default_sort_col' => 'added',
		'default_sort_dir' => 'desc',
		'get_items' => array(
			'function' => 'list_getBans',
		),
		'get_count' => array(
			'function' => 'list_getNumBans',
		),
		'no_items_label' => $txt['ban_no_entries'],
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['ban_name'],
				),
				'data' => array(
					'db' => 'name',
				),
				'sort' => array(
					'default' => 'bg.name',
					'reverse' => 'bg.name DESC',
				),
			),
			'notes' => array(
				'header' => array(
					'value' => $txt['ban_notes'],
				),
				'data' => array(
					'db' => 'notes',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'LENGTH(bg.notes) > 0 DESC, bg.notes',
					'reverse' => 'LENGTH(bg.notes) > 0, bg.notes DESC',
				),
			),
			'reason' => array(
				'header' => array(
					'value' => $txt['ban_reason'],
				),
				'data' => array(
					'db' => 'reason',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'LENGTH(bg.reason) > 0 DESC, bg.reason',
					'reverse' => 'LENGTH(bg.reason) > 0, bg.reason DESC',
				),
			),
			'added' => array(
				'header' => array(
					'value' => $txt['ban_added'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $context;

						return timeformat($rowData[\'ban_time\'], empty($context[\'ban_time_format\']) ? true : $context[\'ban_time_format\']);
					'),
				),
				'sort' => array(
					'default' => 'bg.ban_time',
					'reverse' => 'bg.ban_time DESC',
				),
			),
			'expires' => array(
				'header' => array(
					'value' => $txt['ban_expires'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						// This ban never expires...whahaha.
						if ($rowData[\'expire_time\'] === null)
							return $txt[\'never\'];

						// This ban has already expired.
						elseif ($rowData[\'expire_time\'] < time())
							return sprintf(\'<span style="color: red">%1$s</span>\', $txt[\'ban_expired\']);

						// Still need to wait a few days for this ban to expire.
						else
							return sprintf(\'%1$d&nbsp;%2$s\', ceil(($rowData[\'expire_time\'] - time()) / (60 * 60 * 24)), $txt[\'ban_days\']);
					'),
				),
				'sort' => array(
					'default' => 'IFNULL(bg.expire_time, 1=1) DESC, bg.expire_time DESC',
					'reverse' => 'IFNULL(bg.expire_time, 1=1), bg.expire_time',
				),
			),
			'num_triggers' => array(
				'header' => array(
					'value' => $txt['ban_triggers'],
				),
				'data' => array(
					'db' => 'num_triggers',
				),
				'sort' => array(
					'default' => 'num_triggers DESC',
					'reverse' => 'num_triggers',
				),
			),
			'actions' => array(
				'header' => array(
					'value' => $txt['ban_actions'],
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=%1$d">' . $txt['modify'] . '</a>',
						'params' => array(
							'id_ban_group' => false,
						),
					),
					'class' => 'centercol',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
						'params' => array(
							'id_ban_group' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=ban;sa=list',
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="removeBans" value="' . $txt['ban_remove_selected'] . '" onclick="return confirm(\'' . $txt['ban_remove_selected_confirm'] . '\');" class="button_submit" />',
			),
		),
	);

	require_once(SUBSDIR . '/List.subs.php');
	createList($listOptions);
}

/**
 * Get bans, what else? For the given options.
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @return array
 */
function list_getBans($start, $items_per_page, $sort)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes, COUNT(bi.id_ban) AS num_triggers
		FROM {db_prefix}ban_groups AS bg
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
		GROUP BY bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	);
	$bans = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$bans[] = $row;

	$smcFunc['db_free_result']($request);

	return $bans;
}

/**
 * Get the total number of ban from the ban group table
 *
 * @return int
 */
function list_getNumBans()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS num_bans
		FROM {db_prefix}ban_groups',
		array(
		)
	);
	list ($numBans) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $numBans;
}

/**
 * This function is behind the screen for adding new bans and modifying existing ones.
 * Adding new bans:
 * 	- is accesssed by ?action=admin;area=ban;sa=add.
 * 	- uses the ban_edit sub template of the ManageBans template.
 * Modifying existing bans:
 *  - is accesssed by ?action=admin;area=ban;sa=edit;bg=x
 *  - uses the ban_edit sub template of the ManageBans template.
 *  - shows a list of ban triggers for the specified ban.
 */
function BanEdit()
{
	global $txt, $modSettings, $context, $ban_request, $scripturl, $smcFunc;

	$ban_errors = error_context::context('ban', 1);

	if ((isset($_POST['add_ban']) || isset($_POST['modify_ban']) || isset($_POST['remove_selection'])) && !$ban_errors->hasErrors())
		BanEdit2();

	$ban_group_id = isset($context['ban']['id']) ? $context['ban']['id'] : (isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0);

	// Template needs this to show errors using javascript
	loadLanguage('Errors');
	createToken('admin-bet');
	$context['form_url'] = $scripturl . '?action=admin;area=ban;sa=edit';

	$ban_errors = error_context::context('ban', 1);

	$context['ban_errors'] = array(
		'errors' => $ban_errors->prepareErrors(),
		'type' => $ban_errors->getErrorType() == 0 ? 'minor' : 'serious',
		'title' => $txt['ban_errors_detected'],
	);

	// If we're editing an existing ban, get it from the database.
	if (!empty($ban_group_id))
	{
		$context['ban_group_id'] = $ban_group_id;

		// We're going to want this for making our list.
		require_once(SUBSDIR . '/List.subs.php');

		$listOptions = array(
			'id' => 'ban_items',
			'base_href' => $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $ban_group_id,
			'no_items_label' => $txt['ban_no_triggers'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'get_items' => array(
				'function' => 'list_getBanItems',
				'params' => array(
					'ban_group_id' => $ban_group_id,
				),
			),
			'get_count' => array(
				'function' => 'list_getNumBanItems',
				'params' => array(
					'ban_group_id' => $ban_group_id,
				),
			),
			'columns' => array(
				'type' => array(
					'header' => array(
						'value' => $txt['ban_banned_entity'],
						'style' => 'width: 60%;text-align: left;',
					),
					'data' => array(
						'function' => create_function('$ban_item', '
							global $txt;

							if (in_array($ban_item[\'type\'], array(\'ip\', \'hostname\', \'email\')))
								return \'<strong>\' . $txt[$ban_item[\'type\']] . \':</strong>&nbsp;\' . $ban_item[$ban_item[\'type\']];
							elseif ($ban_item[\'type\'] == \'user\')
								return \'<strong>\' . $txt[\'username\'] . \':</strong>&nbsp;\' . $ban_item[\'user\'][\'link\'];
							else
								return \'<strong>\' . $txt[\'unknown\'] . \':</strong>&nbsp;\' . $ban_item[\'no_bantype_selected\'];
						'),
						'style' => 'text-align: left;',
					),
				),
				'hits' => array(
					'header' => array(
						'value' => $txt['ban_hits'],
						'style' => 'width: 15%; text-align: center;',
					),
					'data' => array(
						'db' => 'hits',
						'style' => 'text-align: center;',
					),
				),
				'id' => array(
					'header' => array(
						'value' => $txt['ban_actions'],
						'style' => 'width: 15%; text-align: center;',
					),
					'data' => array(
						'function' => create_function('$ban_item', '
							global $txt, $context, $scripturl;

							return \'<a href="\' . $scripturl . \'?action=admin;area=ban;sa=edittrigger;bg=\' . $context[\'ban\'][\'id\'] . \';bi=\' . $ban_item[\'id\'] . \'">\' . $txt[\'ban_edit_trigger\'] . \'</a>\';
						'),
						'style' => 'text-align: center;',
					),
				),
				'checkboxes' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form, \'ban_items\');" class="input_check" />',
						'style' => 'width: 5%; text-align: center;',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="ban_items[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id' => false,
							),
						),
						'style' => 'text-align: center;',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=featuresettings;sa=profile',
				'name' => 'standardProfileFields',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '
					<input type="submit" name="remove_selection" value="' . $txt['ban_remove_selected_triggers'] . '" class="button_submit" /> <a class="button_link" href="' . $scripturl . '?action=admin;area=ban;sa=edittrigger;bg=' . $ban_group_id . '">' . $txt['ban_add_trigger'] . '</a>',
					'style' => 'text-align: right;',
				),
				array(
					'position' => 'below_table_data',
					'value' => '
					<input type="hidden" name="bg" value="' . $ban_group_id . '" />
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
					<input type="hidden" name="' . $context['admin-bet_token_var'] . '" value="' . $context['admin-bet_token'] . '" />',
				),
			),
		);
		createList($listOptions);
	}
	// Not an existing one, then it's probably a new one.
	else
	{
		$context['ban'] = array(
			'id' => 0,
			'name' => '',
			'expiration' => array(
				'status' => 'never',
				'days' => 0
			),
			'reason' => '',
			'notes' => '',
			'ban_days' => 0,
			'cannot' => array(
				'access' => true,
				'post' => false,
				'register' => false,
				'login' => false,
			),
			'is_new' => true,
		);
		$context['ban_suggestions'] = array(
			'main_ip' => '',
			'hostname' => '',
			'email' => '',
			'member' => array(
				'id' => 0,
			),
		);

		// Overwrite some of the default form values if a user ID was given.
		if (!empty($_REQUEST['u']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_member, real_name, member_ip, email_address
				FROM {db_prefix}members
				WHERE id_member = {int:current_user}
				LIMIT 1',
				array(
					'current_user' => (int) $_REQUEST['u'],
				)
			);
			if ($smcFunc['db_num_rows']($request) > 0)
				list ($context['ban_suggestions']['member']['id'], $context['ban_suggestions']['member']['name'], $context['ban_suggestions']['main_ip'], $context['ban_suggestions']['email']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if (!empty($context['ban_suggestions']['member']['id']))
			{
				$context['ban_suggestions']['href'] = $scripturl . '?action=profile;u=' . $context['ban_suggestions']['member']['id'];
				$context['ban_suggestions']['member']['link'] = '<a href="' . $context['ban_suggestions']['href'] . '">' . $context['ban_suggestions']['member']['name'] . '</a>';

				// Default the ban name to the name of the banned member.
				$context['ban']['name'] = $context['ban_suggestions']['member']['name'];
				// @todo: there should be a better solution...used to lock the "Ban on Username" input when banning from profile
				$context['ban']['from_user'] = true;

				// Would be nice if we could also ban the hostname.
				if ((preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $context['ban_suggestions']['main_ip']) == 1 || isValidIPv6($context['ban_suggestions']['main_ip'])) && empty($modSettings['disableHostnameLookup']))
					$context['ban_suggestions']['hostname'] = host_from_ip($context['ban_suggestions']['main_ip']);

				$context['ban_suggestions']['other_ips'] = banLoadAdditionalIPs($context['ban_suggestions']['member']['id']);
			}
		}
	}

	// Template needs this to show errors using javascript
	loadLanguage('Errors');
	$context['sub_template'] = 'ban_edit';
	loadJavascriptFile('suggest.js', array('default_theme' => true), 'suggest.js');

}

/**
 * Retrieves all the ban items belonging to a certain ban group
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param int $ban_group_id
 * @return array
 */
function list_getBanItems($start = 0, $items_per_page = 0, $sort = 0, $ban_group_id = 0)
{
	global $context, $smcFunc, $scripturl;

	$ban_items = array();
	$request = $smcFunc['db_query']('', '
		SELECT
			bi.id_ban, bi.hostname, bi.email_address, bi.id_member, bi.hits,
			bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4,
			bi.ip_low5, bi.ip_high5, bi.ip_low6, bi.ip_high6, bi.ip_low7, bi.ip_high7, bi.ip_low8, bi.ip_high8,
			bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes, bg.cannot_access, bg.cannot_register, bg.cannot_login, bg.cannot_post,
			IFNULL(mem.id_member, 0) AS id_member, mem.member_name, mem.real_name
		FROM {db_prefix}ban_groups AS bg
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
		WHERE bg.id_ban_group = {int:current_ban}
		LIMIT {int:start}, {int:items_per_page}',
		array(
			'current_ban' => $ban_group_id,
			'start' => $start,
			'items_per_page' => $items_per_page,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('ban_not_found', false);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($context['ban']))
		{
			$context['ban'] = array(
				'id' => $row['id_ban_group'],
				'name' => $row['name'],
				'expiration' => array(
					'status' => $row['expire_time'] === null ? 'never' : ($row['expire_time'] < time() ? 'expired' : 'one_day'),
					'days' => $row['expire_time'] > time() ? floor(($row['expire_time'] - time()) / 86400) : 0
				),
				'reason' => $row['reason'],
				'notes' => $row['notes'],
				'cannot' => array(
					'access' => !empty($row['cannot_access']),
					'post' => !empty($row['cannot_post']),
					'register' => !empty($row['cannot_register']),
					'login' => !empty($row['cannot_login']),
				),
				'is_new' => false,
				'hostname' => '',
				'email' => '',
			);
		}

		if (!empty($row['id_ban']))
		{
			$ban_items[$row['id_ban']] = array(
				'id' => $row['id_ban'],
				'hits' => $row['hits'],
			);
			if (!empty($row['ip_high1']))
			{
				$ban_items[$row['id_ban']]['type'] = 'ip';
				$ban_items[$row['id_ban']]['ip'] = range2ip(array($row['ip_low1'], $row['ip_low2'], $row['ip_low3'], $row['ip_low4'] ,$row['ip_low5'], $row['ip_low6'], $row['ip_low7'], $row['ip_low8']), array($row['ip_high1'], $row['ip_high2'], $row['ip_high3'], $row['ip_high4'], $row['ip_high5'], $row['ip_high6'], $row['ip_high7'], $row['ip_high8']));
			}
			elseif (!empty($row['hostname']))
			{
				$ban_items[$row['id_ban']]['type'] = 'hostname';
				$ban_items[$row['id_ban']]['hostname'] = str_replace('%', '*', $row['hostname']);
			}
			elseif (!empty($row['email_address']))
			{
				$ban_items[$row['id_ban']]['type'] = 'email';
				$ban_items[$row['id_ban']]['email'] = str_replace('%', '*', $row['email_address']);
			}
			elseif (!empty($row['id_member']))
			{
				$ban_items[$row['id_ban']]['type'] = 'user';
				$ban_items[$row['id_ban']]['user'] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
					'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				);
			}
			// Invalid ban (member probably doesn't exist anymore).
			else
			{
				unset($ban_items[$row['id_ban']]);
				removeBanTriggers($row['id_ban']);
			}
		}
	}
	$smcFunc['db_free_result']($request);

	return $ban_items;
}

/**
 * Gets the number of ban items belonging to a certain ban group
 *
 * @return int
 */
function list_getNumBanItems()
{
	global $smcFunc, $context;

	$ban_group_id = isset($context['ban_group_id']) ? $context['ban_group_id'] : 0;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(bi.id_ban)
		FROM {db_prefix}ban_groups AS bg
			LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
		WHERE bg.id_ban_group = {int:current_ban}',
		array(
			'current_ban' => $ban_group_id,
		)
	);
	list($banNumber) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $banNumber;
}

/**
 * Finds additional IPs related to a certain user
 *
 * @param int $member_id
 * @return array
 */
function banLoadAdditionalIPs($member_id)
{
	// Borrowing a few language strings from profile.
	loadLanguage('Profile');

	$search_list = array();
	call_integration_hook('integrate_load_addtional_ip_ban', array(&$search_list));
	$search_list += array('ips_in_messages' => 'banLoadAdditionalIPsMember', 'ips_in_errors' => 'banLoadAdditionalIPsError');

	$return = array();
	foreach ($search_list as $key => $callable)
		if (is_callable($callable))
			$return[$key] = call_user_func($callable, $member_id);

	return $return;
}

function banLoadAdditionalIPsMember($member_id)
{
	global $smcFunc;

	// Find some additional IP's used by this member.
	$message_ips = array();
	$request = $smcFunc['db_query']('ban_suggest_message_ips', '
		SELECT DISTINCT poster_ip
		FROM {db_prefix}messages
		WHERE id_member = {int:current_user}
			AND poster_ip RLIKE {string:poster_ip_regex}
		ORDER BY poster_ip',
		array(
			'current_user' => $member_id,
			'poster_ip_regex' => '^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$message_ips[] = $row['poster_ip'];
	$smcFunc['db_free_result']($request);

	return $message_ips;
}

function banLoadAdditionalIPsError($member_id)
{
	global $smcFunc;

	$error_ips = array();
	$request = $smcFunc['db_query']('ban_suggest_error_ips', '
		SELECT DISTINCT ip
		FROM {db_prefix}log_errors
		WHERE id_member = {int:current_user}
			AND ip RLIKE {string:poster_ip_regex}
		ORDER BY ip',
		array(
			'current_user' => $member_id,
			'poster_ip_regex' => '^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$error_ips[] = $row['ip'];
	$smcFunc['db_free_result']($request);

	return $error_ips;
}

/**
 * This function handles submitted forms that add, modify or remove ban triggers.
 */
function banEdit2()
{
	global $smcFunc, $context;

	checkSession();
	validateToken('admin-bet');

	$ban_errors = error_context::context('ban', 1);

	// Adding or editing a ban group
	if (isset($_POST['add_ban']) || isset($_POST['modify_ban']))
	{
		// Let's collect all the information we need
		$ban_info['id'] = isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0;
		$ban_info['is_new'] = empty($ban_info['id']);
		$ban_info['expire_date'] = !empty($_POST['expire_date']) ? (int) $_POST['expire_date'] : 0;
		$ban_info['expiration'] = array(
			'status' => isset($_POST['expiration']) && in_array($_POST['expiration'], array('never', 'one_day', 'expired')) ? $_POST['expiration'] : 'never',
			'days' => $ban_info['expire_date'],
		);
		$ban_info['db_expiration'] = $ban_info['expiration']['status'] == 'never' ? 'NULL' : ($ban_info['expiration']['status'] == 'one_day' ? time() + 24 * 60 * 60 * $ban_info['expire_date'] : 0);
		$ban_info['full_ban'] = empty($_POST['full_ban']) ? 0 : 1;
		$ban_info['reason'] = !empty($_POST['reason']) ? $smcFunc['htmlspecialchars']($_POST['reason'], ENT_QUOTES) : '';
		$ban_info['name'] = !empty($_POST['ban_name']) ? $smcFunc['htmlspecialchars']($_POST['ban_name'], ENT_QUOTES) : '';
		$ban_info['notes'] = isset($_POST['notes']) ? $smcFunc['htmlspecialchars']($_POST['notes'], ENT_QUOTES) : '';
		$ban_info['notes'] = str_replace(array("\r", "\n", '  '), array('', '<br />', '&nbsp; '), $ban_info['notes']);
		$ban_info['cannot']['access'] = empty($ban_info['full_ban']) ? 0 : 1;
		$ban_info['cannot']['post'] = !empty($ban_info['full_ban']) || empty($_POST['cannot_post']) ? 0 : 1;
		$ban_info['cannot']['register'] = !empty($ban_info['full_ban']) || empty($_POST['cannot_register']) ? 0 : 1;
		$ban_info['cannot']['login'] = !empty($ban_info['full_ban']) || empty($_POST['cannot_login']) ? 0 : 1;

		// Adding a new ban group
		if (empty($_REQUEST['bg']))
			$ban_group_id = insertBanGroup($ban_info);
		// Editing an existing ban group
		else
			$ban_group_id = updateBanGroup($ban_info);

		if (is_numeric($ban_group_id))
		{
			$ban_info['id'] = $ban_group_id;
			$ban_info['is_new'] = false;
		}

		$context['ban'] = $ban_info;
	}

	if (isset($_POST['ban_suggestions']))
		// @TODO: is $_REQUEST['bi'] ever set?
		$context['ban_suggestions']['saved_triggers'] = saveTriggers($_POST['ban_suggestions'], $ban_info['id'], isset($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0, isset($_REQUEST['bi']) ? (int) $_REQUEST['bi'] : 0);

	// Something went wrong somewhere... Oh well, let's go back.
	if ($ban_errors->hasErrors())
	{
		// Not strictly necessary, but it's nice
		if (!empty($context['ban_suggestions']['member']['id']))
			$context['ban_suggestions']['other_ips'] = banLoadAdditionalIPs($context['ban_suggestions']['member']['id']);
		return BanEdit();
	}

	if (isset($_POST['ban_items']))
	{
		$items_ids = array();
		$group_id = isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0;
		array_map('intval', $_POST['ban_items']);

		removeBanTriggers($_POST['ban_items'], $group_id);
	}

	// Register the last modified date.
	updateSettings(array('banLastUpdated' => time()));

	// Update the member table to represent the new ban situation.
	updateBanMembers();
}

/**
 * This function handles the ins and outs of the screen for adding new ban
 * triggers or modifying existing ones.
 * Adding new ban triggers:
 * 	- is accessed by ?action=admin;area=ban;sa=edittrigger;bg=x
 * 	- uses the ban_edit_trigger sub template of ManageBans.
 * Editing existing ban triggers:
 *  - is accessed by ?action=admin;area=ban;sa=edittrigger;bg=x;bi=y
 *  - uses the ban_edit_trigger sub template of ManageBans.
 */
function action_edittrigger()
{
	global $context, $smcFunc, $scripturl;

	$context['sub_template'] = 'ban_edit_trigger';
	$context['form_url'] = $scripturl . '?action=admin;area=ban;sa=edittrigger';

	$ban_group = isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0;
	$ban_id = isset($_REQUEST['bi']) ? (int) $_REQUEST['bi'] : 0;

	if (empty($ban_group))
		fatal_lang_error('ban_not_found', false);

	if (isset($_POST['add_new_trigger']) && !empty($_POST['ban_suggestions']))
	{
		saveTriggers($_POST['ban_suggestions'], $ban_group, 0, $ban_id);
		redirectexit('action=admin;area=ban;sa=edit' . (!empty($ban_group) ? ';bg=' . $ban_group : ''));
	}
	elseif (isset($_POST['edit_trigger']) && !empty($_POST['ban_suggestions']))
	{
		// The first replaces the old one, the others are added new (simplification, otherwise it would require another query and some work...)
		saveTriggers(array_shift($_POST['ban_suggestions']), $ban_group, 0, $ban_id);
		if (!empty($_POST['ban_suggestions']))
			saveTriggers($_POST['ban_suggestions'], $ban_group);

		redirectexit('action=admin;area=ban;sa=edit' . (!empty($ban_group) ? ';bg=' . $ban_group : ''));
	}
	elseif (isset($_POST['edit_trigger']))
	{
		removeBanTriggers($ban_id);
		redirectexit('action=admin;area=ban;sa=edit' . (!empty($ban_group) ? ';bg=' . $ban_group : ''));
	}

	loadJavascriptFile('suggest.js', array('default_theme' => true), 'suggest.js');

	if (empty($ban_id))
	{
		$context['ban_trigger'] = array(
			'id' => 0,
			'group' => $ban_group,
			'ip' => array(
				'value' => '',
				'selected' => true,
			),
			'hostname' => array(
				'selected' => false,
				'value' => '',
			),
			'email' => array(
				'value' => '',
				'selected' => false,
			),
			'banneduser' => array(
				'value' => '',
				'selected' => false,
			),
			'is_new' => true,
		);
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT
				bi.id_ban, bi.id_ban_group, bi.hostname, bi.email_address, bi.id_member,
				bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4,
				bi.ip_low5, bi.ip_high5, bi.ip_low6, bi.ip_high6, bi.ip_low7, bi.ip_high7, bi.ip_low8, bi.ip_high8,
				mem.member_name, mem.real_name
			FROM {db_prefix}ban_items AS bi
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
			WHERE bi.id_ban = {int:ban_item}
				AND bi.id_ban_group = {int:ban_group}
			LIMIT 1',
			array(
				'ban_item' => $ban_id,
				'ban_group' => $ban_group,
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('ban_not_found', false);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		$context['ban_trigger'] = array(
			'id' => $row['id_ban'],
			'group' => $row['id_ban_group'],
			'ip' => array(
				'value' => empty($row['ip_low1']) ? '' : range2ip(array($row['ip_low1'], $row['ip_low2'], $row['ip_low3'], $row['ip_low4'], $row['ip_low5'], $row['ip_low6'], $row['ip_low7'], $row['ip_low8']), array($row['ip_high1'], $row['ip_high2'], $row['ip_high3'], $row['ip_high4'], $row['ip_high5'], $row['ip_high6'], $row['ip_high7'], $row['ip_high8'])),
				'selected' => !empty($row['ip_low1']),
			),
			'hostname' => array(
				'value' => str_replace('%', '*', $row['hostname']),
				'selected' => !empty($row['hostname']),
			),
			'email' => array(
				'value' => str_replace('%', '*', $row['email_address']),
				'selected' => !empty($row['email_address'])
			),
			'banneduser' => array(
				'value' => $row['member_name'],
				'selected' => !empty($row['member_name'])
			),
			'is_new' => false,
		);
	}

	createToken('admin-bet');
}

/**
 * This handles the screen for showing the banned entities
 * It is accessed by ?action=admin;area=ban;sa=browse
 * It uses sub-tabs for browsing by IP, hostname, email or username.
 *
 * @uses ManageBans template, browse_triggers sub template.
 */
function action_browse()
{
	global $modSettings, $context, $scripturl, $smcFunc, $txt;
	global $settings;

	if (!empty($_POST['remove_triggers']) && !empty($_POST['remove']) && is_array($_POST['remove']))
	{
		checkSession();

		removeBanTriggers($_POST['remove']);

		// Rehabilitate some members.
		if ($_REQUEST['entity'] == 'member')
			updateBanMembers();

		// Make sure the ban cache is refreshed.
		updateSettings(array('banLastUpdated' => time()));
	}

	$context['selected_entity'] = isset($_REQUEST['entity']) && in_array($_REQUEST['entity'], array('ip', 'hostname', 'email', 'member')) ? $_REQUEST['entity'] : 'ip';

	$listOptions = array(
		'id' => 'ban_trigger_list',
		'title' => $txt['ban_trigger_browse'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'base_href' => $scripturl . '?action=admin;area=ban;sa=browse;entity=' . $context['selected_entity'],
		'default_sort_col' => 'banned_entity',
		'no_items_label' => $txt['ban_no_triggers'],
		'get_items' => array(
			'function' => 'list_getBanTriggers',
			'params' => array(
				$context['selected_entity'],
			),
		),
		'get_count' => array(
			'function' => 'list_getNumBanTriggers',
			'params' => array(
				$context['selected_entity'],
			),
		),
		'columns' => array(
			'banned_entity' => array(
				'header' => array(
					'value' => $txt['ban_banned_entity'],
				),
			),
			'ban_name' => array(
				'header' => array(
					'value' => $txt['ban_name'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=%1$d">%2$s</a>',
						'params' => array(
							'id_ban_group' => false,
							'name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'bg.name',
					'reverse' => 'bg.name DESC',
				),
			),
			'hits' => array(
				'header' => array(
					'value' => $txt['ban_hits'],
				),
				'data' => array(
					'db' => 'hits',
				),
				'sort' => array(
					'default' => 'bi.hits DESC',
					'reverse' => 'bi.hits',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
						'params' => array(
							'id_ban' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=ban;sa=browse;entity=' . $context['selected_entity'],
			'include_start' => true,
			'include_sort' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="remove_triggers" value="' . $txt['ban_remove_selected_triggers'] . '" onclick="return confirm(\'' . $txt['ban_remove_selected_triggers_confirm'] . '\');" class="button_submit" />',
			),
		),
		'list_menu' => array(
			array(
				'show_on' => 'top',
				'value' => array(
					array(
						'href' => $scripturl . '?action=admin;area=ban;sa=browse;entity=ip',
						'is_selected' => $context['selected_entity'] == 'ip',
						'label' => $txt['ip']
					),
					array(
						'href' => $scripturl . '?action=admin;area=ban;sa=browse;entity=hostname',
						'is_selected' => $context['selected_entity'] == 'hostname',
						'label' => $txt['hostname']
					),
					array(
						'href' => $scripturl . '?action=admin;area=ban;sa=browse;entity=email',
						'is_selected' => $context['selected_entity'] == 'email',
						'label' => $txt['email']
					),
					array(
						'href' => $scripturl . '?action=admin;area=ban;sa=browse;entity=member',
						'is_selected' => $context['selected_entity'] == 'member',
						'label' => $txt['username']
					)
				),
			),
		),
	);

	// Specific data for the first column depending on the selected entity.
	if ($context['selected_entity'] === 'ip')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'function' => create_function('$rowData', '
				return range2ip(array(
					$rowData[\'ip_low1\'],
					$rowData[\'ip_low2\'],
					$rowData[\'ip_low3\'],
					$rowData[\'ip_low4\'],
					$rowData[\'ip_low5\'],
					$rowData[\'ip_low6\'],
					$rowData[\'ip_low7\'],
					$rowData[\'ip_low8\']
				), array(
					$rowData[\'ip_high1\'],
					$rowData[\'ip_high2\'],
					$rowData[\'ip_high3\'],
					$rowData[\'ip_high4\'],
					$rowData[\'ip_high5\'],
					$rowData[\'ip_high6\'],
					$rowData[\'ip_high7\'],
					$rowData[\'ip_high8\']
				));
			'),
		);
		$listOptions['columns']['banned_entity']['sort'] = array(
			'default' => 'bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4, bi.ip_low5, bi.ip_high5, bi.ip_low6, bi.ip_high6, bi.ip_low7, bi.ip_high7, bi.ip_low8, bi.ip_high8',
			'reverse' => 'bi.ip_low1 DESC, bi.ip_high1 DESC, bi.ip_low2 DESC, bi.ip_high2 DESC, bi.ip_low3 DESC, bi.ip_high3 DESC, bi.ip_low4 DESC, bi.ip_high4 DESC, bi.ip_low5 DESC, bi.ip_high5 DESC, bi.ip_low6 DESC, bi.ip_high6 DESC, bi.ip_low7 DESC, bi.ip_high7 DESC, bi.ip_low8 DESC, bi.ip_high8 DESC',
		);
	}
	elseif ($context['selected_entity'] === 'hostname')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'function' => create_function('$rowData', '
				global $smcFunc;
				return strtr($smcFunc[\'htmlspecialchars\']($rowData[\'hostname\']), array(\'%\' => \'*\'));
			'),
		);
		$listOptions['columns']['banned_entity']['sort'] = array(
			'default' => 'bi.hostname',
			'reverse' => 'bi.hostname DESC',
		);
	}
	elseif ($context['selected_entity'] === 'email')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'function' => create_function('$rowData', '
				global $smcFunc;
				return strtr($smcFunc[\'htmlspecialchars\']($rowData[\'email_address\']), array(\'%\' => \'*\'));
			'),
		);
		$listOptions['columns']['banned_entity']['sort'] = array(
			'default' => 'bi.email_address',
			'reverse' => 'bi.email_address DESC',
		);
	}
	elseif ($context['selected_entity'] === 'member')
	{
		$listOptions['columns']['banned_entity']['data'] = array(
			'sprintf' => array(
				'format' => '<a href="' . $scripturl . '?action=profile;u=%1$d">%2$s</a>',
				'params' => array(
					'id_member' => false,
					'real_name' => false,
				),
			),
		);
		$listOptions['columns']['banned_entity']['sort'] = array(
			'default' => 'mem.real_name',
			'reverse' => 'mem.real_name DESC',
		);
	}

	// Create the list.
	require_once(SUBSDIR . '/List.subs.php');
	createList($listOptions);
}

/**
 * Get ban triggers for the given parameters.
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param string $trigger_type
 * @return array
 */
function list_getBanTriggers($start, $items_per_page, $sort, $trigger_type)
{
	global $smcFunc;

	$where = array(
		'ip' => 'bi.ip_low1 > 0',
		'hostname' => 'bi.hostname != {string:blank_string}',
		'email' => 'bi.email_address != {string:blank_string}',
	);

	$request = $smcFunc['db_query']('', '
		SELECT
			bi.id_ban, bi.ip_low1, bi.ip_high1, bi.ip_low2, bi.ip_high2, bi.ip_low3, bi.ip_high3, bi.ip_low4, bi.ip_high4, bi.ip_low5, bi.ip_high5, bi.ip_low6, bi.ip_high6, bi.ip_low7, bi.ip_high7, bi.ip_low8, bi.ip_high8, bi.hostname, bi.email_address, bi.hits,
			bg.id_ban_group, bg.name' . ($trigger_type === 'member' ? ',
			mem.id_member, mem.real_name' : '') . '
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)' . ($trigger_type === 'member' ? '
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)' : '
		WHERE ' . $where[$trigger_type]) . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'blank_string' => '',
		)
	);
	$ban_triggers = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$ban_triggers[] = $row;
	$smcFunc['db_free_result']($request);

	return $ban_triggers;
}

/**
 * This returns the total number of ban triggers of the given type.
 *
 * @param string $trigger_type
 * @return int
 */
function list_getNumBanTriggers($trigger_type)
{
	global $smcFunc;

	$where = array(
		'ip' => 'bi.ip_low1 > 0',
		'hostname' => 'bi.hostname != {string:blank_string}',
		'email' => 'bi.email_address != {string:blank_string}',
	);

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}ban_items AS bi' . ($trigger_type === 'member' ? '
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)' : '
		WHERE ' . $where[$trigger_type]),
		array(
			'blank_string' => '',
		)
	);
	list ($num_triggers) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_triggers;
}

/**
 * This handles the listing of ban log entries, and allows their deletion.
 * Shows a list of logged access attempts by banned users.
 * It is accessed by ?action=admin;area=ban;sa=log.
 * How it works:
 *  - allows sorting of several columns.
 *  - also handles deletion of (a selection of) log entries.
 */
function action_log()
{
	global $scripturl, $context, $smcFunc, $txt;
	global $context;

	// Delete one or more entries.
	if (!empty($_POST['removeAll']) || (!empty($_POST['removeSelected']) && !empty($_POST['remove'])))
	{
		checkSession();
		validateToken('admin-bl');

		// 'Delete all entries' button was pressed.
		if (!empty($_POST['removeAll']))
			removeBanLogs();
		// 'Delete selection' button was pressed.
		else
		{
			array_map('intval', $_POST['remove']);
			removeBanLogs($_POST['remove']);
		}
	}

	$listOptions = array(
		'id' => 'ban_log',
		'title' => $txt['ban_log'],
		'items_per_page' => 30,
		'base_href' => $context['admin_area'] == 'ban' ? $scripturl . '?action=admin;area=ban;sa=log' : $scripturl . '?action=admin;area=logs;sa=banlog',
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getBanLogEntries',
		),
		'get_count' => array(
			'function' => 'list_getNumBanLogEntries',
		),
		'no_items_label' => $txt['ban_log_no_entries'],
		'columns' => array(
			'ip' => array(
				'header' => array(
					'value' => $txt['ban_log_ip'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=trackip;searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'lb.ip',
					'reverse' => 'lb.ip DESC',
				),
			),
			'email' => array(
				'header' => array(
					'value' => $txt['ban_log_email'],
				),
				'data' => array(
					'db_htmlsafe' => 'email',
				),
				'sort' => array(
					'default' => 'lb.email = \'\', lb.email',
					'reverse' => 'lb.email != \'\', lb.email DESC',
				),
			),
			'member' => array(
				'header' => array(
					'value' => $txt['ban_log_member'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id_member' => false,
							'real_name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'IFNULL(mem.real_name, 1=1), mem.real_name',
					'reverse' => 'IFNULL(mem.real_name, 1=1) DESC, mem.real_name DESC',
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['ban_log_date'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return timeformat($rowData[\'log_time\']);
					'),
				),
				'sort' => array(
					'default' => 'lb.log_time DESC',
					'reverse' => 'lb.log_time',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
						'params' => array(
							'id_ban_log' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $context['admin_area'] == 'ban' ? $scripturl . '?action=admin;area=ban;sa=log' : $scripturl . '?action=admin;area=logs;sa=banlog',
			'include_start' => true,
			'include_sort' => true,
			'token' => 'admin-bl',
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="removeSelected" value="' . $txt['ban_log_remove_selected'] . '" onclick="return confirm(\'' . $txt['ban_log_remove_selected_confirm'] . '\');" class="button_submit" />
					<input type="submit" name="removeAll" value="' . $txt['ban_log_remove_all'] . '" onclick="return confirm(\'' . $txt['ban_log_remove_all_confirm'] . '\');" class="button_submit" />',
			),
		),
	);

	createToken('admin-bl');

	require_once(SUBSDIR . '/List.subs.php');
	createList($listOptions);

	$context['page_title'] = $txt['ban_log'];
}

/**
 * Load a list of ban log entries from the database.
 * (no permissions check)
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 */
function list_getBanLogEntries($start, $items_per_page, $sort)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT lb.id_ban_log, lb.id_member, IFNULL(lb.ip, {string:dash}) AS ip, IFNULL(lb.email, {string:dash}) AS email, lb.log_time, IFNULL(mem.real_name, {string:blank_string}) AS real_name
		FROM {db_prefix}log_banned AS lb
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lb.id_member)
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'blank_string' => '',
			'dash' => '-',
		)
	);
	$log_entries = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$log_entries[] = $row;
	$smcFunc['db_free_result']($request);

	return $log_entries;
}

/**
 * This returns the total count of ban log entries.
 */
function list_getNumBanLogEntries()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_banned AS lb',
		array(
		)
	);
	list ($num_entries) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_entries;
}
