<?php

/**
 * Handles all possible permission items, permissions by membergroup
 * permissions by board, adding, modifying, etc
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
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ManagePermissions handles all possible permission stuff.
 */
class ManagePermissions_Controller extends Action_Controller
{
	/**
	 * Permissions settings form
	 * @var Settings_Form
	 */
	protected $_permSettings;

	/**
	 * Dispaches to the right function based on the given subaction.
	 * Checks the permissions, based on the sub-action.
	 * Called by ?action=managepermissions.
	 *
	 * @uses ManagePermissions language file.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $txt, $context;

		loadLanguage('ManagePermissions+ManageMembers');
		loadTemplate('ManagePermissions');

		// We're working with them settings here.
		require_once(SUBSDIR . '/Settings.class.php');

		// Format: 'sub-action' => array('function_to_call', 'permission_needed'),
		$subActions = array(
			'board' => array(
				'controller' => $this,
				'function' => 'action_board',
				'permission' => 'manage_permissions'),
			'index' => array(
				'controller' => $this,
				'function' => 'action_list',
				'permission' => 'manage_permissions'),
			'modify' => array(
				'controller' => $this,
				'function' => 'action_modify',
				'permission' => 'manage_permissions'),
			'modify2' => array(
				'controller' => $this,
				'function' => 'action_modify2',
				'permission' => 'manage_permissions'),
			'quick' => array(
				'controller' => $this,
				'function' => 'action_quick',
				'permission' => 'manage_permissions'),
			'quickboard' => array(
				'controller' => $this,
				'function' => 'action_quickboard',
				'permission' => 'manage_permissions'),
			'postmod' => array(
				'controller' => $this,
				'function' => 'action_postmod',
				'permission' => 'manage_permissions',
				'disabled' => !in_array('pm', $context['admin_features'])),
			'profiles' => array(
				'controller' => $this,
				'function' => 'action_profiles',
				'permission' => 'manage_permissions'),
			'settings' => array(
				'controller' => $this,
				'function' => 'action_permSettings_display',
				'permission' => 'admin_forum'),
		);

		call_integration_hook('integrate_manage_permissions', array(&$subActions));

		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) && empty($subActions[$_REQUEST['sa']]['disabled']) ? $_REQUEST['sa'] : (allowedTo('manage_permissions') ? 'index' : 'settings');

		$context['page_title'] = $txt['permissions_title'];
		$context['sub_action'] = $subAction;

		// Create the tabs for the template.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['permissions_title'],
			'help' => 'permissions',
			'description' => '',
			'tabs' => array(
				'index' => array(
					'description' => $txt['permissions_groups'],
				),
				'board' => array(
					'description' => $txt['permission_by_board_desc'],
				),
				'profiles' => array(
					'description' => $txt['permissions_profiles_desc'],
				),
				'postmod' => array(
					'description' => $txt['permissions_post_moderation_desc'],
				),
				'settings' => array(
					'description' => $txt['permission_settings_desc'],
				),
			),
		);

		// Call the right function for this sub-action.
		$action = new Action();
		$action->initialize($subActions);
		$action->dispatch($subAction);
	}

	/**
	 * Sets up the permissions by membergroup index page.
	 * Called by ?action=managepermissions
	 * Creates an array of all the groups with the number of members and permissions.
	 *
	 * @uses ManagePermissions language file.
	 * @uses ManagePermissions template file.
	 * @uses ManageBoards template, permission_index sub-template.
	 */
	public function action_list()
	{
		global $txt, $scripturl, $context, $user_info, $modSettings;

		require_once(SUBSDIR . '/Membergroups.subs.php');
		require_once(SUBSDIR . '/Members.subs.php');
		require_once(SUBSDIR . '/ManagePermissions.subs.php');

		$context['page_title'] = $txt['permissions_title'];

		// pid = profile id
		if (!empty($_REQUEST['pid']))
			$_REQUEST['pid'] = (int) $_REQUEST['pid'];

		// We can modify any permission set apart from the read only, reply only and no polls ones as they are redefined.
		$context['can_modify'] = empty($_REQUEST['pid']) || $_REQUEST['pid'] == 1 || $_REQUEST['pid'] > 4;

		// Load all the permissions. We'll need them in the template.
		loadAllPermissions();

		// Also load profiles, we may want to reset.
		loadPermissionProfiles();

		$listOptions = array(
			'id' => 'regular_membergroups_list',
			'title' => $txt['membergroups_regular'],
			'base_href' => $scripturl . '?action=admin;area=permissions;sa=index' . (isset($_REQUEST['sort2']) ? ';sort2=' . urlencode($_REQUEST['sort2']) : '') . (isset($_REQUEST['pid']) ? ';pid=' . $_REQUEST['pid'] : ''),
			'default_sort_col' => 'name',
			'get_items' => array(
				'file' => SUBSDIR . '/Membergroups.subs.php',
				'function' => 'list_getMembergroups',
				'params' => array(
					'all',
					$user_info['id'],
					allowedTo('manage_membergroups'),
					allowedTo('admin_forum'),
					true,
					true,
					isset($_REQUEST['pid']) ? $_REQUEST['pid'] : null,
				),
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['membergroups_name'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $scripturl, $txt;

							// Since the moderator group has no explicit members, no link is needed.
							// Since guests and regular members are not groups, no link is needed.
							if (in_array($rowData[\'id_group\'], array(-1, 0, 3)))
								$group_name = $rowData[\'group_name\'];
							else
							{
								$group_name = sprintf(\'<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d">%3$s</a>\', $scripturl, $rowData[\'id_group\'], $rowData[\'group_name_color\']);
							}

							// Add a help option for guests, regular members, moderator and administrator.
							if (!empty($rowData[\'help\']))
								$group_name .= sprintf(\' (<a href="%1$s?action=quickhelp;help=\' . $rowData[\'help\'] . \'" onclick="return reqOverlayDiv(this.href);">?</a>)\', $scripturl);

							if (!empty($rowData[\'children\']))
								$group_name .= \'
									<br />
									<span class="smalltext">\' . $txt[\'permissions_includes_inherited\'] . \': &quot;\' . implode(\'&quot;, &quot;\', $rowData[\'children\']) . \'&quot;</span>\';

							return $group_name;
						'),
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
					),
				),
				'members' => array(
					'header' => array(
						'value' => $txt['membergroups_members_top'],
						'style' => 'width:10%;',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt, $scripturl;

							// No explicit members for guests and the moderator group.
							if (in_array($rowData[\'id_group\'], array(-1, 3)))
								return $txt[\'membergroups_guests_na\'];
							elseif ($rowData[\'can_search\'])
								return \'<a href="\' . $scripturl . \'?action=moderate;area=viewgroups;sa=members;group=\' . $rowData[\'id_group\'] . \'">\' . comma_format($rowData[\'num_members\']) . \'</a>\';
							else
								return comma_format($rowData[\'num_members\']);
						'),
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					),
				),
				'permissions_allowed' => array(
					'header' => array(
						'value' => empty($modSettings['permission_enable_deny']) ? $txt['membergroups_permissions'] : $txt['permissions_allowed'],
						'style' => 'width:8%;',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt, $scripturl;

							return $rowData[\'num_permissions\'][\'allowed\'];
						'),
					),
				),
				'permissions_denied' => array(
					'evaluate' => !empty($modSettings['permission_enable_deny']),
					'header' => array(
						'value' => $txt['permissions_denied'],
						'style' => 'width:8%;',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt, $scripturl;

							return $rowData[\'num_permissions\'][\'denied\'];
						'),
					),
				),
				'modify' => array(
					'header' => array(
						'value' => $context['can_modify'] ? $txt['permissions_modify'] : $txt['permissions_view'],
						'style' => 'width:8%;',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $scripturl;

							if ($rowData[\'id_group\'] != 1)
								return \'<a href="\' . $scripturl . \'?action=admin;area=permissions;sa=modify;group=\' . $rowData[\'id_group\'] . \'' . (isset($_REQUEST['pid']) ? ';pid=' . $_REQUEST['pid'] : '') . '">' . $txt['membergroups_modify'] . '</a>\';
						'),
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
						'style' => 'width:4%;',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							if ($rowData[\'id_group\'] != 1)
								return \'<input type="checkbox" name="group[]" value="\' . $rowData[\'id_group\'] . \'" class="input_check" />\';
						'),
						'class' => 'centertext',
					),
				),
			),
		);

		require_once(SUBSDIR . '/List.class.php');
		createList($listOptions);

		// The second list shows the post count based groups...if enabled
		if (!empty($modSettings['permission_enable_postgroups']))
		{
			$listOptions = array(
				'id' => 'post_count_membergroups_list',
				'title' => $txt['membergroups_post'],
				'base_href' => $scripturl . '?action=admin;area=permissions;sa=index' . (isset($_REQUEST['sort2']) ? ';sort2=' . urlencode($_REQUEST['sort2']) : '') . (isset($_REQUEST['pid']) ? ';pid=' . $_REQUEST['pid'] : ''),
				'default_sort_col' => 'required_posts',
				'request_vars' => array(
					'sort' => 'sort2',
					'desc' => 'desc2',
				),
				'get_items' => array(
					'file' => SUBSDIR . '/Membergroups.subs.php',
					'function' => 'list_getMembergroups',
					'params' => array(
						'post_count',
						$user_info['id'],
						allowedTo('manage_membergroups'),
						allowedTo('admin_forum'),
						false,
						true,
						isset($_REQUEST['pid']) ? $_REQUEST['pid'] : null,
					),
				),
				'columns' => array(
					'name' => array(
						'header' => array(
							'value' => $txt['membergroups_name'],
						),
						'data' => array(
							'function' => create_function('$rowData', '
								global $scripturl;

								return sprintf(\'<a href="%1$s?action=admin;area=permissions;sa=members;group=%2$d">%3$s</a>\', $scripturl, $rowData[\'id_group\'], $rowData[\'group_name_color\']);
							'),
						),
						'sort' => array(
							'default' => 'mg.group_name',
							'reverse' => 'mg.group_name DESC',
						),
					),
					'required_posts' => array(
						'header' => array(
							'value' => $txt['membergroups_min_posts'],
						),
						'data' => array(
							'db' => 'min_posts',
						),
						'sort' => array(
							'default' => 'mg.min_posts',
							'reverse' => 'mg.min_posts DESC',
						),
					),
					'members' => array(
						'header' => array(
							'value' => $txt['membergroups_members_top'],
							'style' => 'width:10%;',
						),
						'data' => array(
							'function' => create_function('$rowData', '
								global $scripturl;

								if ($rowData[\'can_search\'])
									return \'<a href="\' . $scripturl . \'?action=moderate;area=viewgroups;sa=members;group=\' . $rowData[\'id_group\'] . \'">\' . comma_format($rowData[\'num_members\']) . \'</a>\';
								else
									return comma_format($rowData[\'num_members\']);
							'),
						),
						'sort' => array(
							'default' => '1 DESC',
							'reverse' => '1',
						),
					),
					'permissions_allowed' => array(
						'header' => array(
							'value' => empty($modSettings['permission_enable_deny']) ? $txt['membergroups_permissions'] : $txt['permissions_allowed'],
							'style' => 'width:8%;',
						),
						'data' => array(
							'function' => create_function('$rowData', '
								return $rowData[\'num_permissions\'][\'allowed\'];
							'),
						),
					),
					'permissions_denied' => array(
						'evaluate' => !empty($modSettings['permission_enable_deny']),
						'header' => array(
							'value' => $txt['permissions_denied'],
							'style' => 'width:8%;',
						),
						'data' => array(
							'function' => create_function('$rowData', '
								return $rowData[\'num_permissions\'][\'denied\'];
							'),
						),
					),
					'modify' => array(
						'header' => array(
							'value' => $txt['modify'],
							'style' => 'width:8%;',
						),
						'data' => array(
							'sprintf' => array(
								'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d' . (isset($_REQUEST['pid']) ? ';pid=' . $_REQUEST['pid'] : '') . '">' . $txt['membergroups_modify'] . '</a>',
								'params' => array(
									'id_group' => false,
								),
							),
						),
					),
					'check' => array(
						'header' => array(
							'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
							'class' => 'centertext',
							'style' => 'width:4%;',
						),
						'data' => array(
							'sprintf' => array(
								'format' => '<input type="checkbox" name="group[]" value="%1$d" class="input_check" />',
								'params' => array(
									'id_group' => false,
								),
							),
							'class' => 'centertext',
						),
					),
				),
			);

			createList($listOptions);
		}

		// pid = profile id
		if (!empty($_REQUEST['pid']))
		{
			if (!isset($context['profiles'][$_REQUEST['pid']]))
				fatal_lang_error('no_access', false);

			// Change the selected tab to better reflect that this really is a board profile.
			$context[$context['admin_menu_name']]['current_subsection'] = 'profiles';

			$context['profile'] = array(
				'id' => $_REQUEST['pid'],
				'name' => $context['profiles'][$_REQUEST['pid']]['name'],
			);
		}

		$context['groups'] = array_merge(array(0 => $txt['membergroups_members']), getInheritableGroups());

		// Load the proper template.
		$context['sub_template'] = 'permission_index';
		createToken('admin-mpq');
	}

	/**
	 * Handle permissions by board... more or less. :P
	 */
	public function action_board()
	{
		global $context, $txt, $cat_tree, $boardList, $boards;

		require_once(SUBSDIR . '/ManagePermissions.subs.php');

		$context['page_title'] = $txt['permissions_boards'];
		$context['edit_all'] = isset($_GET['edit']);

		// Saving?
		if (!empty($_POST['save_changes']) && !empty($_POST['boardprofile']))
		{
			checkSession('request');
			validateToken('admin-mpb');

			$changes = array();
			foreach ($_POST['boardprofile'] as $board => $profile)
			{
				$changes[(int) $profile][] = (int) $board;
			}

			if (!empty($changes))
			{
				foreach ($changes as $profile => $boards)
					assignPermissionProfileToBoard($profile, $boards);
			}

			$context['edit_all'] = false;
		}

		// Load all permission profiles.
		loadPermissionProfiles();

		if (!$context['edit_all'])
		{
			$js = 'new Array(';
			foreach ($context['profiles'] as $id => $profile)
				$js .= '{name: ' . JavaScriptEscape($profile['name']) . ', id: ' . $id . '},';
			addJavascriptVar(array(
				'permission_profiles' => substr($js, 0, -1) . ')',
				'txt_save' => JavaScriptEscape($txt['save']),
			));
		}

		// Get the board tree.
		require_once(SUBSDIR . '/Boards.subs.php');

		getBoardTree();

		// Build the list of the boards.
		$context['categories'] = array();
		foreach ($cat_tree as $catid => $tree)
		{
			$context['categories'][$catid] = array(
				'name' => &$tree['node']['name'],
				'id' => &$tree['node']['id'],
				'boards' => array()
			);
			foreach ($boardList[$catid] as $boardid)
			{
				if (!isset($context['profiles'][$boards[$boardid]['profile']]))
					$boards[$boardid]['profile'] = 1;

				$context['categories'][$catid]['boards'][$boardid] = array(
					'id' => &$boards[$boardid]['id'],
					'name' => &$boards[$boardid]['name'],
					'description' => &$boards[$boardid]['description'],
					'child_level' => &$boards[$boardid]['level'],
					'profile' => &$boards[$boardid]['profile'],
					'profile_name' => $context['profiles'][$boards[$boardid]['profile']]['name'],
				);
			}
		}

		$context['sub_template'] = 'by_board';
		createToken('admin-mpb');
	}

	/**
	 * Handles permission modification actions from the upper part of the
	 * permission manager index.
	 */
	public function action_quick()
	{
		global $context;

		checkSession();
		validateToken('admin-mpq', 'quick');

		// we'll need to init illegal permissions, update permissions, etc.
		require_once(SUBSDIR . '/Permission.subs.php');
		require_once(SUBSDIR . '/ManagePermissions.subs.php');

		loadIllegalPermissions();
		loadIllegalGuestPermissions();

		// Make sure only one of the quick options was selected.
		if ((!empty($_POST['predefined']) && ((isset($_POST['copy_from']) && $_POST['copy_from'] != 'empty') || !empty($_POST['permissions']))) || (!empty($_POST['copy_from']) && $_POST['copy_from'] != 'empty' && !empty($_POST['permissions'])))
			fatal_lang_error('permissions_only_one_option', false);

		if (empty($_POST['group']) || !is_array($_POST['group']))
			$_POST['group'] = array();

		// Only accept numeric values for selected membergroups.
		foreach ($_POST['group'] as $id => $group_id)
			$_POST['group'][$id] = (int) $group_id;
		$_POST['group'] = array_unique($_POST['group']);

		if (empty($_REQUEST['pid']))
			$_REQUEST['pid'] = 0;
		else
			$_REQUEST['pid'] = (int) $_REQUEST['pid'];

		// Fix up the old global to the new default!
		$bid = max(1, $_REQUEST['pid']);

		// No modifying the predefined profiles.
		if ($_REQUEST['pid'] > 1 && $_REQUEST['pid'] < 5)
			fatal_lang_error('no_access', false);

		// Clear out any cached authority.
		updateSettings(array('settings_updated' => time()));

		// No groups where selected.
		if (empty($_POST['group']))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		// Set a predefined permission profile.
		if (!empty($_POST['predefined']))
		{
			// Make sure it's a predefined permission set we expect.
			if (!in_array($_POST['predefined'], array('restrict', 'standard', 'moderator', 'maintenance')))
				redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

			foreach ($_POST['group'] as $group_id)
			{
				if (!empty($_REQUEST['pid']))
					setPermissionLevel($_POST['predefined'], $group_id, $_REQUEST['pid']);
				else
					setPermissionLevel($_POST['predefined'], $group_id);
			}
		}
		// Set a permission profile based on the permissions of a selected group.
		elseif ($_POST['copy_from'] != 'empty')
		{
			// Just checking the input.
			if (!is_numeric($_POST['copy_from']))
				redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

			// Make sure the group we're copying to is never included.
			$_POST['group'] = array_diff($_POST['group'], array($_POST['copy_from']));

			// No groups left? Too bad.
			if (empty($_POST['group']))
				redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

			if (empty($_REQUEST['pid']))
				copyPermission($_POST['copy_from'], $_POST['group'], $context['illegal_permissions'], $context['non_guest_permissions']);

			// Now do the same for the board permissions.
			copyBoardPermission($_POST['copy_from'], $_POST['group'], $bid, $context['non_guest_permissions']);

			// Update any children out there!
			updateChildPermissions($_POST['group'], $_REQUEST['pid']);
		}
		// Set or unset a certain permission for the selected groups.
		elseif (!empty($_POST['permissions']))
		{
			// Unpack two variables that were transported.
			list ($permissionType, $permission) = explode('/', $_POST['permissions']);

			// Check whether our input is within expected range.
			if (!in_array($_POST['add_remove'], array('add', 'clear', 'deny')) || !in_array($permissionType, array('membergroup', 'board')))
				redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

			if ($_POST['add_remove'] == 'clear')
			{
				if ($permissionType == 'membergroup')
					deletePermission($_POST['group'], $permission, $context['illegal_permissions']);
				else
					deleteBoardPermission($_POST['group'], $bid, $permission);
			}
			// Add a permission (either 'set' or 'deny').
			else
			{
				$add_deny = $_POST['add_remove'] == 'add' ? '1' : '0';
				$permChange = array();
				foreach ($_POST['group'] as $groupID)
				{
					if ($groupID == -1 && in_array($permission, $context['non_guest_permissions']))
						continue;

					if ($permissionType == 'membergroup' && $groupID != 1 && $groupID != 3 && (empty($context['illegal_permissions']) || !in_array($permission, $context['illegal_permissions'])))
						$permChange[] = array($permission, $groupID, $add_deny);
					elseif ($permissionType != 'membergroup')
						$permChange[] = array($permission, $groupID, $add_deny, $bid);
				}

				if (!empty($permChange))
				{
					if ($permissionType == 'membergroup')
						replacePermission($permChange);
					// Board permissions go into the other table.
					else
						replaceBoardPermission($permChange);
				}
			}

			// Another child update!
			updateChildPermissions($_POST['group'], $_REQUEST['pid']);
		}

		redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
	}

	/**
	 * Initializes the necessary to modify a membergroup's permissions.
	 */
	public function action_modify()
	{
		global $context, $txt;

		if (!isset($_GET['group']))
			fatal_lang_error('no_access', false);

		require_once(SUBSDIR . '/ManagePermissions.subs.php');
		$context['group']['id'] = (int) $_GET['group'];

		// It's not likely you'd end up here with this setting disabled.
		if ($_GET['group'] == 1)
			redirectexit('action=admin;area=permissions');

		loadAllPermissions();
		loadPermissionProfiles();

		if ($context['group']['id'] > 0)
		{
			require_once(SUBSDIR . '/Membergroups.subs.php');

			$group = membergroupById($context['group']['id'], true);
			$context['group']['name'] = $group['group_name'];
			$parent = $group['id_parent'];

			// Cannot edit an inherited group!
			if ($parent != -2)
				fatal_lang_error('cannot_edit_permissions_inherited');
		}
		elseif ($context['group']['id'] == -1)
			$context['group']['name'] = $txt['membergroups_guests'];
		else
			$context['group']['name'] = $txt['membergroups_members'];

		$context['profile']['id'] = empty($_GET['pid']) ? 0 : (int) $_GET['pid'];

		// If this is a moderator and they are editing "no profile" then we only do boards.
		if ($context['group']['id'] == 3 && empty($context['profile']['id']))
		{
			// For sanity just check they have no general permissions.
			removeModeratorPermissions();

			$context['profile']['id'] = 1;
		}

		$context['permission_type'] = empty($context['profile']['id']) ? 'membergroup' : 'board';
		$context['profile']['can_modify'] = !$context['profile']['id'] || $context['profiles'][$context['profile']['id']]['can_modify'];

		// Set up things a little nicer for board related stuff...
		if ($context['permission_type'] == 'board')
		{
			$context['profile']['name'] = $context['profiles'][$context['profile']['id']]['name'];
			$context[$context['admin_menu_name']]['current_subsection'] = 'profiles';
		}

		// Fetch the current permissions.
		$permissions = array(
			'membergroup' => array('allowed' => array(), 'denied' => array()),
			'board' => array('allowed' => array(), 'denied' => array())
		);

		// General permissions?
		if ($context['permission_type'] == 'membergroup')
			$permissions['membergroup'] = fetchPermissions($_GET['group']);

		// Fetch current board permissions...
		$permissions['board'] = fetchBoardPermissions( $context['group']['id'], $context['permission_type'], $context['profile']['id']);

		// Loop through each permission and set whether it's checked.
		foreach ($context['permissions'] as $permissionType => $tmp)
		{
			foreach ($tmp['columns'] as $position => $permissionGroups)
			{
				foreach ($permissionGroups as $permissionGroup => $permissionArray)
				{
					foreach ($permissionArray['permissions'] as $perm)
					{
						// Create a shortcut for the current permission.
						$curPerm = &$context['permissions'][$permissionType]['columns'][$position][$permissionGroup]['permissions'][$perm['id']];

						if ($perm['has_own_any'])
						{
							$curPerm['any']['select'] = in_array($perm['id'] . '_any', $permissions[$permissionType]['allowed']) ? 'on' : (in_array($perm['id'] . '_any', $permissions[$permissionType]['denied']) ? 'denied' : 'off');
							$curPerm['own']['select'] = in_array($perm['id'] . '_own', $permissions[$permissionType]['allowed']) ? 'on' : (in_array($perm['id'] . '_own', $permissions[$permissionType]['denied']) ? 'denied' : 'off');
						}
						else
							$curPerm['select'] = in_array($perm['id'], $permissions[$permissionType]['denied']) ? 'denied' : (in_array($perm['id'], $permissions[$permissionType]['allowed']) ? 'on' : 'off');
					}
				}
			}
		}
		$context['sub_template'] = 'modify_group';
		$context['page_title'] = $txt['permissions_modify_group'];

		createToken('admin-mp');
	}

	/**
	 * This function actually saves modifications to a membergroup's board permissions.
	 */
	public function action_modify2()
	{
		global $context;

		checkSession();
		validateToken('admin-mp');

		// We'll need to init illegal permissions, update child permissions, etc.
		require_once(SUBSDIR . '/Permission.subs.php');
		require_once(SUBSDIR . '/ManagePermissions.subs.php');

		loadIllegalPermissions();

		$current_group_id = (int) $_GET['group'];
		$_GET['pid'] = (int) $_GET['pid'];

		// Cannot modify predefined profiles.
		if ($_GET['pid'] > 1 && $_GET['pid'] < 5)
			fatal_lang_error('no_access', false);

		// Verify this isn't inherited.
		if ($current_group_id == -1 || $current_group_id == 0)
			$parent = -2;
		else
		{
			require_once(SUBSDIR . '/Membergroups.subs.php');
			$group = membergroupById($current_group_id, true);
			$parent = $group['id_parent'];
		}

		if ($parent != -2)
			fatal_lang_error('cannot_edit_permissions_inherited');

		$givePerms = array('membergroup' => array(), 'board' => array());

		// Guest group, we need illegal, guest permissions.
		if ($current_group_id == -1)
		{
			loadIllegalGuestPermissions();
			$context['illegal_permissions'] = array_merge($context['illegal_permissions'], $context['non_guest_permissions']);
		}

		// Prepare all permissions that were set or denied for addition to the DB.
		if (isset($_POST['perm']) && is_array($_POST['perm']))
		{
			foreach ($_POST['perm'] as $perm_type => $perm_array)
			{
				if (is_array($perm_array))
				{
					foreach ($perm_array as $permission => $value)
						if ($value == 'on' || $value == 'deny')
						{
							// Don't allow people to escalate themselves!
							if (!empty($context['illegal_permissions']) && in_array($permission, $context['illegal_permissions']))
								continue;

							$givePerms[$perm_type][] = array($permission, $current_group_id, $value == 'deny' ? 0 : 1);
						}
				}
			}
		}

		// Insert the general permissions.
		if ($current_group_id != 3 && empty($_GET['pid']))
		{
			deleteInvalidPermissions($current_group_id, $context['illegal_permissions']);

			if (!empty($givePerms['membergroup']))
				replacePermission($givePerms['membergroup']);
		}

		// Insert the boardpermissions.
		$profileid = max(1, $_GET['pid']);
		deleteAllBoardPermissions($current_group_id, $profileid);

		if (!empty($givePerms['board']))
		{
			foreach ($givePerms['board'] as $k => $v)
				$givePerms['board'][$k][] = $profileid;
			replaceBoardPermission($givePerms['board']);
		}

		// Update any inherited permissions as required.
		updateChildPermissions($current_group_id, $_GET['pid']);

		// Clear cached privs.
		updateSettings(array('settings_updated' => time()));

		redirectexit('action=admin;area=permissions;pid=' . $_GET['pid']);
	}

	/**
	 * A screen to set some general settings for permissions.
	 */
	public function action_permSettings_display()
	{
		global $context, $modSettings, $txt, $scripturl;

		require_once(SUBSDIR . '/ManagePermissions.subs.php');
		
		// Initialize the form
		$this->_initPermSettingsForm();

		$config_vars = $this->_permSettings->settings();

		call_integration_hook('integrate_modify_permission_settings', array(&$config_vars));

		$context['page_title'] = $txt['permission_settings_title'];
		$context['sub_template'] = 'show_settings';

		// Don't let guests have these permissions.
		$context['post_url'] = $scripturl . '?action=admin;area=permissions;save;sa=settings';
		$context['permissions_excluded'] = array(-1);

		// Saving the settings?
		if (isset($_GET['save']))
		{
			checkSession('post');
			call_integration_hook('integrate_save_permission_settings');
			Settings_Form::save_db($config_vars);

			// Clear all deny permissions...if we want that.
			if (empty($modSettings['permission_enable_deny']))
				clearDenyPermissions();

			// Make sure there are no postgroup based permissions left.
			if (empty($modSettings['permission_enable_postgroups']))
				clearPostgroupPermissions();

			redirectexit('action=admin;area=permissions;sa=settings');
		}

		// We need this for the in-line permissions
		createToken('admin-mp');

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize the settings form.
	 */
	private function _initPermSettingsForm()
	{
		// Instantiate the form
		$this->_permSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_settings();

		return $this->_permSettings->settings($config_vars);
	}

	/**
	 * Simple function to return settings in config_vars format.
	 */
	private function _settings()
	{
		global $txt;

		// All the setting variables
		$config_vars = array(
			array('title', 'settings'),
				// Inline permissions.
				array('permissions', 'manage_permissions'),
			'',
				// A few useful settings
				array('check', 'permission_enable_deny', 0, $txt['permission_settings_enable_deny'], 'help' => 'permissions_deny'),
				array('check', 'permission_enable_postgroups', 0, $txt['permission_settings_enable_postgroups'], 'help' => 'permissions_postgroups'),
		);

		return $config_vars;
	}

	/**
	 * Return the permission settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}

	/**
	 * Add/Edit/Delete profiles.
	 */
	public function action_profiles()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/ManagePermissions.subs.php');

		// Setup the template, first for fun.
		$context['page_title'] = $txt['permissions_profile_edit'];
		$context['sub_template'] = 'edit_profiles';

		// If we're creating a new one do it first.
		if (isset($_POST['create']) && trim($_POST['profile_name']) != '')
		{
			checkSession();
			validateToken('admin-mpp');
			copyPermissionProfile($_POST['profile_name'], (int) $_POST['copy_from']);
		}
		// Renaming?
		elseif (isset($_POST['rename']))
		{
			checkSession();
			validateToken('admin-mpp');

			// Just showing the boxes?
			if (!isset($_POST['rename_profile']))
				$context['show_rename_boxes'] = true;
			else
			{
				foreach ($_POST['rename_profile'] as $id => $name)
					renamePermissionProfile($id, $name);
			}
		}
		// Deleting?
		elseif (isset($_POST['delete']) && !empty($_POST['delete_profile']))
		{
			checkSession('post');
			validateToken('admin-mpp');

			$profiles = array();
			foreach ($_POST['delete_profile'] as $profile)
				if ($profile > 4)
					$profiles[] = (int) $profile;

			deletePermissionProfiles($profiles);
		}

		// Clearly, we'll need this!
		loadPermissionProfiles();

		// Work out what ones are in use.
		$context['profiles'] = permProfilesInUse($context['profiles']);

		// What can we do with these?
		$context['can_edit_something'] = false;
		foreach ($context['profiles'] as $id => $profile)
		{
			// Can't delete special ones.
			$context['profiles'][$id]['can_edit'] = isset($txt['permissions_profile_' . $profile['unformatted_name']]) ? false : true;
			if ($context['profiles'][$id]['can_edit'])
				$context['can_edit_something'] = true;

			// You can only delete it if you can edit it AND it's not in use.
			$context['profiles'][$id]['can_delete'] = $context['profiles'][$id]['can_edit'] && empty($profile['in_use']) ? true : false;
		}

		createToken('admin-mpp');
	}

	/**
	 * Present a nice way of applying post moderation.
	 */
	public function action_postmod()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/ManagePermissions.subs.php');

		// Just in case.
		checkSession('get');

		$context['page_title'] = $txt['permissions_post_moderation'];
		$context['sub_template'] = 'postmod_permissions';
		$context['current_profile'] = isset($_REQUEST['pid']) ? (int) $_REQUEST['pid'] : 1;

		// Load all the permission profiles.
		loadPermissionProfiles();

		// Mappings, our key => array(can_do_moderated, can_do_all)
		$mappings = array(
			'new_topic' => array('post_new', 'post_unapproved_topics'),
			'replies_own' => array('post_reply_own', 'post_unapproved_replies_own'),
			'replies_any' => array('post_reply_any', 'post_unapproved_replies_any'),
			'attachment' => array('post_attachment', 'post_unapproved_attachments'),
		);

		call_integration_hook('integrate_post_moderation_mapping', array(&$mappings));

		// Load the groups.
		require_once(SUBSDIR . '/Membergroups.subs.php');
		$context['profile_groups'] = prepareMembergroupPermissions();

		// What are the permissions we are querying?
		$all_permissions = array();
		foreach ($mappings as $perm_set)
			$all_permissions = array_merge($all_permissions, $perm_set);

		// If we're saving the changes then do just that - save them.
		if (!empty($_POST['save_changes']) && ($context['current_profile'] == 1 || $context['current_profile'] > 4))
		{
			validateToken('admin-mppm');

			// Start by deleting all the permissions relevant.
			deleteBoardPermissions($context['profile_groups'], $context['current_profile'], $all_permissions);

			// Do it group by group.
			$new_permissions = array();
			foreach ($context['profile_groups'] as $id => $group)
			{
				foreach ($mappings as $index => $data)
				{
					if (isset($_POST[$index][$group['id']]))
					{
						if ($_POST[$index][$group['id']] == 'allow')
						{
							// Give them both sets for fun.
							$new_permissions[] = array($context['current_profile'], $group['id'], $data[0], 1);
							$new_permissions[] = array($context['current_profile'], $group['id'], $data[1], 1);
						}
						elseif ($_POST[$index][$group['id']] == 'moderate')
							$new_permissions[] = array($context['current_profile'], $group['id'], $data[1], 1);
					}
				}
			}

			// Insert new permissions.
			if (!empty($new_permissions))
				insertBoardPermission($new_permissions);
		}

		// Now get all the permissions!
		$perm = getPermission(array_keys($context['profile_groups']), $context['current_profile'], $all_permissions);

		foreach ($perm as $id_group => $row)
		{
			foreach ($mappings as $key => $data)
			{
				foreach ($data as $index => $perm)
				{
					// Only bother if it's not denied.
					if (!empty($row['add']) && in_array($perm, $row['add']))
					{
						// Full allowance?
						if ($index == 0)
							$context['profile_groups'][$id_group][$key] = 'allow';
						// Otherwise only bother with moderate if not on allow.
						elseif ($context['profile_groups'][$id_group][$key] != 'allow')
							$context['profile_groups'][$id_group][$key] = 'moderate';
					}
				}
			}
		}

		createToken('admin-mppm');
	}
}