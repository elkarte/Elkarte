<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This class controls execution for admin actions in the bans area
 * of the admin panel.
 */
class ManageBans_Controller extends Action_Controller
{
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
	public function action_index()
	{
		global $context, $txt, $scripturl;

		isAllowedTo('manage_bans');

		loadTemplate('ManageBans');
		require_once(SUBSDIR . '/Bans.subs.php');

		$subActions = array(
			'add' => array($this, 'action_edit'),
			'browse' => array($this, 'action_browse'),
			'edittrigger' => array($this, 'action_edittrigger'),
			'edit' => array($this, 'action_edit'),
			'list' => array($this, 'action_list'),
			'log' => array($this, 'action_log'),
		);

		$action = new Action();
		$action->initialize($subActions);

		call_integration_hook('integrate_manage_bans', array(&$subActions));

		// Default the sub-action to 'view ban list'.
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'list';

		$context['page_title'] = $txt['ban_title'];
		$context['sub_action'] = $subAction;

		// Tabs for browsing the different ban functions.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['ban_title'],
			'help' => 'ban_members',
			'description' => $txt['ban_description'],
			'tabs' => array(
				'list' => array(
					'description' => $txt['ban_description'],
					'href' => $scripturl . '?action=admin;area=ban;sa=list',
					'is_selected' => $subAction == 'list' || $subAction == 'edit' || $subAction == 'edittrigger',
				),
				'add' => array(
					'description' => $txt['ban_description'],
					'href' => $scripturl . '?action=admin;area=ban;sa=add',
					'is_selected' => $subAction == 'add',
				),
				'browse' => array(
					'description' => $txt['ban_trigger_browse_description'],
					'href' => $scripturl . '?action=admin;area=ban;sa=browse',
					'is_selected' => $subAction == 'browse',
				),
				'log' => array(
					'description' => $txt['ban_log_description'],
					'href' => $scripturl . '?action=admin;area=ban;sa=log',
					'is_selected' => $subAction == 'log',
					'is_last' => true,
				),
			),
		);

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
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
	public function action_list()
	{
		global $txt, $context, $scripturl, $user_info;

		require_once(SUBSDIR . '/Bans.subs.php');
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

							return standardTime($rowData[\'ban_time\'], empty($context[\'ban_time_format\']) ? true : $context[\'ban_time_format\']);
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
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=ban;sa=edit;bg=%1$d">' . $txt['modify'] . '</a>',
							'params' => array(
								'id_ban_group' => false,
							),
						),
						'class' => 'centertext',
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id_ban_group' => false,
							),
						),
						'class' => 'centertext',
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
	 * This function is behind the screen for adding new bans and modifying existing ones.
	 * Adding new bans:
	 * 	- is accesssed by ?action=admin;area=ban;sa=add.
	 * 	- uses the ban_edit sub template of the ManageBans template.
	 * Modifying existing bans:
	 *  - is accesssed by ?action=admin;area=ban;sa=edit;bg=x
	 *  - uses the ban_edit sub template of the ManageBans template.
	 *  - shows a list of ban triggers for the specified ban.
	 */
	public function action_edit()
	{
		global $txt, $modSettings, $context, $scripturl;

		require_once(SUBSDIR . '/Bans.subs.php');
		$ban_errors = error_context::context('ban', 1);

		if ((isset($_POST['add_ban']) || isset($_POST['modify_ban']) || isset($_POST['remove_selection'])) && !$ban_errors->hasErrors())
			$this->action_edit2();

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

		if (!$ban_errors->hasErrors())
		{
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
						'href' => $scripturl . '?action=admin;area=ban;sa=edit;bg=' . $ban_group_id,
					),
					'additional_rows' => array(
						array(
							'position' => 'below_table_data',
							'value' => '
							<input type="submit" name="remove_selection" value="' . $txt['ban_remove_selected_triggers'] . '" class="button_submit" /> <a class="linkbutton" href="' . $scripturl . '?action=admin;area=ban;sa=edittrigger;bg=' . $ban_group_id . '">' . $txt['ban_add_trigger'] . '</a>',
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
					$context['ban_suggestions'] = array_merge($context['ban_suggestions'], getMemberData((int) $_REQUEST['u']));

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
		}

		// Template needs this to show errors using javascript
		loadLanguage('Errors');
		$context['sub_template'] = 'ban_edit';
		loadJavascriptFile('suggest.js', array('default_theme' => true), 'suggest.js');

	}

	/**
	 * This handles the listing of ban log entries, and allows their deletion.
	 * Shows a list of logged access attempts by banned users.
	 * It is accessed by ?action=admin;area=ban;sa=log.
	 * How it works:
	 *  - allows sorting of several columns.
	 *  - also handles deletion of (a selection of) log entries.
	 */
	public function action_log()
	{
		global $scripturl, $context, $txt;

		require_once(SUBSDIR . '/Bans.subs.php');

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
							return standardTime($rowData[\'log_time\']);
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
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id_ban_log' => false,
							),
						),
						'class' => 'centertext',
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
	 * This function handles submitted forms that add, modify or remove ban triggers.
	 */
	public function action_edit2()
	{
		global $context;

		require_once(SUBSDIR . '/Bans.subs.php');

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
			$ban_info['reason'] = !empty($_POST['reason']) ? Util::htmlspecialchars($_POST['reason'], ENT_QUOTES) : '';
			$ban_info['name'] = !empty($_POST['ban_name']) ? Util::htmlspecialchars($_POST['ban_name'], ENT_QUOTES) : '';
			$ban_info['notes'] = isset($_POST['notes']) ? Util::htmlspecialchars($_POST['notes'], ENT_QUOTES) : '';
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
			$saved_triggers = saveTriggers($_POST['ban_suggestions'], $ban_info['id'], isset($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0, isset($_REQUEST['bi']) ? (int) $_REQUEST['bi'] : 0);

		// Something went wrong somewhere... Oh well, let's go back.
		if ($ban_errors->hasErrors())
		{
			$context['ban_suggestions'] = $saved_triggers;
			$context['ban']['from_user'] = true;
			$context['ban_suggestions'] = array_merge($context['ban_suggestions'], getMemberData((int) $_REQUEST['u']));

			// Not strictly necessary, but it's nice
			if (!empty($context['ban_suggestions']['member']['id']))
				$context['ban_suggestions']['other_ips'] = banLoadAdditionalIPs($context['ban_suggestions']['member']['id']);

			return action_edit();
		}
		$context['ban_suggestions']['saved_triggers'] = $saved_triggers;

		if (isset($_POST['ban_items']))
		{
			$ban_group_id = isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0;
			array_map('intval', $_POST['ban_items']);

			removeBanTriggers($_POST['ban_items'], $ban_group_id);
		}

		// Register the last modified date.
		updateSettings(array('banLastUpdated' => time()));

		// Update the member table to represent the new ban situation.
		updateBanMembers();
		redirectexit('action=admin;area=ban;sa=edit;bg=' . $ban_group_id);
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
	public function action_edittrigger()
	{
		global $context, $scripturl;

		require_once(SUBSDIR . '/Bans.subs.php');

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
			$row = banDetails($ban_id, $ban_group);
			if (empty($row))
				fatal_lang_error('ban_not_found', false);

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
	public function action_browse()
	{
		global $modSettings, $context, $scripturl, $txt;

		require_once(SUBSDIR . '/Bans.subs.php');

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
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="remove[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id_ban' => false,
							),
						),
						'class' => 'centertext',
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
					return strtr(Util::htmlspecialchars($rowData[\'hostname\']), array(\'%\' => \'*\'));
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
					return strtr(Util::htmlspecialchars($rowData[\'email_address\']), array(\'%\' => \'*\'));
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
}