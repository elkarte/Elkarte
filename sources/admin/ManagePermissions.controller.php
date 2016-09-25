<?php

/**
 * Handles all possible permission items, permissions by membergroup
 * permissions by board, adding, modifying, etc
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * ManagePermissions handles all possible permission stuff.
 *
 * @package Permissions
 */
class ManagePermissions_Controller extends Action_Controller
{
	/**
	 * Permissions settings form
	 *
	 * @var Settings_Form
	 */
	protected $_permSettings;

	/**
	 * Permissions object
	 *
	 * @var Permissions
	 */
	private $permissionsObject;

	/**
	 * @var string[]
	 */
	private $illegal_permissions = array();

	/**
	 * @var string[]
	 */
	private $illegal_guest_permissions = array();

	/**
	 * The profile ID that we are working with
	 * @var int|null
	 */
	protected $_pid = null;

	/**
	 * Dispatches to the right function based on the given subaction.
	 *
	 * - Checks the permissions, based on the sub-action.
	 * - Called by ?action=managepermissions.
	 *
	 * @uses ManagePermissions language file.
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $txt, $context;

		// Make sure they can't do certain things,
		// unless they have the right permissions.
		$this->permissionsObject = new Permissions;
		$this->illegal_permissions = $this->permissionsObject->getIllegalPermissions();
		$this->illegal_guest_permissions = $this->permissionsObject->getIllegalGuestPermissions();

		loadLanguage('ManagePermissions+ManageMembers');
		loadTemplate('ManagePermissions');

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

		// Action controller
		$action = new Action('manage_permissions');

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

		// Set the subAction, taking permissions in to account
		$subAction = isset($this->_req->query->sa) && isset($subActions[$this->_req->query->sa]) && empty($subActions[$this->_req->query->sa]['disabled']) ? $this->_req->query->sa : (allowedTo('manage_permissions') ? 'index' : 'settings');

		// Load the subactions, call integrate_sa_manage_permissions
		$action->initialize($subActions);

		// Last items needed
		$context['page_title'] = $txt['permissions_title'];
		$context['sub_action'] = $subAction;

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Sets up the permissions by membergroup index page.
	 *
	 * - Called by ?action=managepermissions
	 * - Creates an array of all the groups with the number of members and permissions.
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
		if (!empty($this->_req->query->pid))
			$this->_pid = (int) $this->_req->query->pid;

		// Needed for <5.4 due to lack of $this support in closures
		$_pid = isset($this->_pid) ? $this->_pid : null;

		// We can modify any permission set apart from the read only, reply only and no polls ones as they are redefined.
		$context['can_modify'] = empty($this->_pid) || $this->_pid == 1 || $this->_pid > 4;

		// Load all the permissions. We'll need them in the template.
		loadAllPermissions();

		// Also load profiles, we may want to reset.
		loadPermissionProfiles();

		$listOptions = array(
			'id' => 'regular_membergroups_list',
			'title' => $txt['membergroups_regular'],
			'base_href' => $scripturl . '?action=admin;area=permissions;sa=index' . (isset($this->_req->query->sort2) ? ';sort2=' . urlencode($this->_req->query->sort2) : '') . (isset($this->_pid) ? ';pid=' . $this->_pid : ''),
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
					$this->_pid,
				),
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['membergroups_name'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $scripturl, $txt;

							// Since the moderator group has no explicit members, no link is needed.
							// Since guests and regular members are not groups, no link is needed.
							if (in_array($rowData['id_group'], array(-1, 0, 3)))
								$group_name = $rowData['group_name'];
							else
							{
								$group_name = sprintf('<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d">%3$s</a>', $scripturl, $rowData['id_group'], $rowData['group_name_color']);
							}

							// Add a help option for guests, regular members, moderator and administrator.
							if (!empty($rowData['help']))
								$group_name .= sprintf(' (<a href="%1$s?action=quickhelp;help=' . $rowData['help'] . '" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"></a>)', $scripturl);

							if (!empty($rowData['children']))
								$group_name .= '
									<br>
									<span class="smalltext">' . $txt['permissions_includes_inherited'] . ': &quot;' . implode('&quot;, &quot;', $rowData['children']) . '&quot;</span>';

							return $group_name;
						},
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
					),
				),
				'members' => array(
					'header' => array(
						'value' => $txt['membergroups_members_top'],
						'class' => 'grid17',
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt, $scripturl;

							// No explicit members for guests and the moderator group.
							if (in_array($rowData['id_group'], array(-1, 3)))
								return $txt['membergroups_guests_na'];
							elseif ($rowData['can_search'])
								return '<a href="' . $scripturl . '?action=moderate;area=viewgroups;sa=members;group=' . $rowData['id_group'] . '">' . comma_format($rowData['num_members']) . '</a>';
							else
								return comma_format($rowData['num_members']);
						},
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					),
				),
				'permissions_allowed' => array(
					'header' => array(
						'value' => empty($modSettings['permission_enable_deny']) ? $txt['membergroups_permissions'] : $txt['permissions_allowed'],
						'class' => 'grid17',
					),
					'data' => array(
						'function' => function ($rowData) {
							return $rowData['num_permissions']['allowed'];
						},
					),
				),
				'permissions_denied' => array(
					'evaluate' => !empty($modSettings['permission_enable_deny']),
					'header' => array(
						'value' => $txt['permissions_denied'],
						'class' => 'grid17',
					),
					'data' => array(
						'function' => function ($rowData) {
							return $rowData['num_permissions']['denied'];
						},
					),
				),
				'modify' => array(
					'header' => array(
						'value' => $context['can_modify'] ? $txt['permissions_modify'] : $txt['permissions_view'],
						'class' => 'grid17',
					),
					'data' => array(
						'function' => function ($rowData) use ($_pid) {
							global $scripturl, $txt;

							if ($rowData['id_group'] != 1)
								return '<a href="' . $scripturl . '?action=admin;area=permissions;sa=modify;group=' . $rowData['id_group'] . '' . (isset($_pid) ? ';pid=' . $_pid : '') . '">' . $txt['membergroups_modify'] . '</a>';

							return '';
						},
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
						'style' => 'width:4%;',
					),
					'data' => array(
						'function' => function ($rowData) {
							if ($rowData['id_group'] != 1)
								return '<input type="checkbox" name="group[]" value="' . $rowData['id_group'] . '" class="input_check" />';

							return '';
						},
						'class' => 'centertext',
					),
				),
			),
		);

		createList($listOptions);

		// The second list shows the post count based groups...if enabled
		if (!empty($modSettings['permission_enable_postgroups']))
		{
			$listOptions = array(
				'id' => 'post_count_membergroups_list',
				'title' => $txt['membergroups_post'],
				'base_href' => $scripturl . '?action=admin;area=permissions;sa=index' . (isset($this->_req->query->sort2) ? ';sort2=' . urlencode($this->_req->query->sort2) : '') . (isset($this->_pid) ? ';pid=' . $this->_pid : ''),
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
						$this->_pid,
					),
				),
				'columns' => array(
					'name' => array(
						'header' => array(
							'value' => $txt['membergroups_name'],
							'class' => 'grid25',
						),
						'data' => array(
							'function' => function ($rowData) {
								global $scripturl;

								return sprintf('<a href="%1$s?action=admin;area=permissions;sa=members;group=%2$d">%3$s</a>', $scripturl, $rowData['id_group'], $rowData['group_name_color']);
							},
						),
						'sort' => array(
							'default' => 'mg.group_name',
							'reverse' => 'mg.group_name DESC',
						),
					),
					'required_posts' => array(
						'header' => array(
							'value' => $txt['membergroups_min_posts'],
							'class' => 'grid25',
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
							'class' => 'grid10',
						),
						'data' => array(
							'function' => function ($rowData) {
								global $scripturl;

								if ($rowData['can_search'])
									return '<a href="' . $scripturl . '?action=moderate;area=viewgroups;sa=members;group=' . $rowData['id_group'] . '">' . comma_format($rowData['num_members']) . '</a>';
								else
									return comma_format($rowData['num_members']);
							},
						),
						'sort' => array(
							'default' => '1 DESC',
							'reverse' => '1',
						),
					),
					'permissions_allowed' => array(
						'header' => array(
							'value' => empty($modSettings['permission_enable_deny']) ? $txt['membergroups_permissions'] : $txt['permissions_allowed'],
							'class' => 'grid8',
						),
						'data' => array(
							'function' => function ($rowData) {
								return $rowData['num_permissions']['allowed'];
							},
						),
					),
					'permissions_denied' => array(
						'evaluate' => !empty($modSettings['permission_enable_deny']),
						'header' => array(
							'value' => $txt['permissions_denied'],
							'class' => 'grid8',
						),
						'data' => array(
							'function' => function ($rowData) {
								return $rowData['num_permissions']['denied'];
							},
						),
					),
					'modify' => array(
						'header' => array(
							'value' => $txt['modify'],
							'class' => 'grid17',
						),
						'data' => array(
							'function' => function ($rowData) use ($_pid) {
								global $scripturl, $txt;

								if ($rowData['id_parent'] == -2)
										return '<a href="' . $scripturl . '?action=admin;area=permissions;sa=modify;group=' . $rowData['id_group'] . (isset($_pid) ? ';pid=' . $_pid : '') . '">' . $txt['membergroups_modify'] . '</a>';
									else
										return '<span class="smalltext">' . $txt['permissions_includes_inherited_from'] . '&quot;' . $rowData['parent_name'] . '&quot;</span>
											<br />
											<a href="' . $scripturl . '?action=admin;area=permissions;sa=modify;group=' . $rowData['id_parent'] . (isset($_pid) ? ';pid=' . $_pid : '') . '">' . $txt['membergroups_modify_parent'] . '</a>';
							}
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
		if (!empty($this->_pid))
		{
			if (!isset($context['profiles'][$this->_pid]))
				Errors::instance()->fatal_lang_error('no_access', false);

			// Change the selected tab to better reflect that this really is a board profile.
			$context[$context['admin_menu_name']]['current_subsection'] = 'profiles';

			$context['profile'] = array(
				'id' => $this->_pid,
				'name' => $context['profiles'][$this->_pid]['name'],
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
		$context['edit_all'] = isset($this->_req->query->edit);

		// Saving?
		if (!empty($this->_req->post->save_changes) && !empty($this->_req->post->boardprofile))
		{
			checkSession('request');
			validateToken('admin-mpb');

			$changes = array();
			foreach ($this->_req->post->boardprofile as $board => $profile)
				$changes[(int) $profile][] = (int) $board;

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
		$bbc_parser = \BBC\ParserWrapper::getInstance();
		foreach ($cat_tree as $catid => $tree)
		{
			$context['categories'][$catid] = array(
				'name' => &$tree['node']['name'],
				'id' => &$tree['node']['id'],
				'boards' => array()
			);
			foreach ($boardList[$catid] as $boardid)
			{
				$boards[$boardid]['description'] = $bbc_parser->parseBoard($boards[$boardid]['description']);

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
		checkSession();
		validateToken('admin-mpq', 'quick');

		// we'll need to init illegal permissions, update permissions, etc.
		require_once(SUBSDIR . '/Permission.subs.php');
		require_once(SUBSDIR . '/ManagePermissions.subs.php');

		// Make sure only one of the quick options was selected.
		if ((!empty($this->_req->post->predefined) && ((isset($this->_req->post->copy_from) && $this->_req->post->copy_from != 'empty') || !empty($this->_req->post->permissions))) || (!empty($this->_req->post->copy_from) && $this->_req->post->copy_from != 'empty' && !empty($this->_req->post->permissions)))
			Errors::instance()->fatal_lang_error('permissions_only_one_option', false);

		if (empty($this->_req->post->group) || !is_array($this->_req->post->group))
			$this->_req->post->group = array();

		// Only accept numeric values for selected membergroups.
		foreach ($this->_req->post->group as $id => $group_id)
			$this->_req->post->group[$id] = (int) $group_id;
		$this->_req->post->group = array_unique($this->_req->post->group);

		$this->_pid = $this->_req->getQuery('pid', 'intval', 0);

		// Fix up the old global to the new default!
		$bid = max(1, $this->_pid);

		// No modifying the predefined profiles.
		if ($this->_pid > 1 && $this->_pid < 5)
			Errors::instance()->fatal_lang_error('no_access', false);

		// Clear out any cached authority.
		updateSettings(array('settings_updated' => time()));

		// No groups where selected.
		if (empty($this->_req->post->group))
			redirectexit('action=admin;area=permissions;pid=' . $this->_pid);

		// Set a predefined permission profile.
		if (!empty($this->_req->post->predefined))
		{
			// Make sure it's a predefined permission set we expect.
			if (!in_array($this->_req->post->predefined, array('restrict', 'standard', 'moderator', 'maintenance')))
				redirectexit('action=admin;area=permissions;pid=' . $this->_pid);

			foreach ($this->_req->post->group as $group_id)
			{
				if (!empty($this->_pid))
					setPermissionLevel($this->_req->post->predefined, $group_id, $this->_pid);
				else
					setPermissionLevel($this->_req->post->predefined, $group_id);
			}
		}
		// Set a permission profile based on the permissions of a selected group.
		elseif ($this->_req->post->copy_from != 'empty')
		{
			// Just checking the input.
			if (!is_numeric($this->_req->post->copy_from))
				redirectexit('action=admin;area=permissions;pid=' . $this->_pid);

			// Make sure the group we're copying to is never included.
			$this->_req->post->group = array_diff($this->_req->post->group, array($this->_req->post->copy_from));

			// No groups left? Too bad.
			if (empty($this->_req->post->group))
				redirectexit('action=admin;area=permissions;pid=' . $this->_pid);

			if (empty($this->_pid))
				copyPermission($this->_req->post->copy_from, $this->_req->post->group, $this->illegal_permissions, $this->illegal_guest_permissions);

			// Now do the same for the board permissions.
			copyBoardPermission($this->_req->post->copy_from, $this->_req->post->group, $bid, $this->illegal_guest_permissions);

			// Update any children out there!
			$this->permissionsObject->updateChild($this->_req->post->group, $this->_pid);
		}
		// Set or unset a certain permission for the selected groups.
		elseif (!empty($this->_req->post->permissions))
		{
			// Unpack two variables that were transported.
			list ($permissionType, $permission) = explode('/', $this->_req->post->permissions);

			// Check whether our input is within expected range.
			if (!in_array($this->_req->post->add_remove, array('add', 'clear', 'deny')) || !in_array($permissionType, array('membergroup', 'board')))
				redirectexit('action=admin;area=permissions;pid=' . $this->_pid);

			if ($this->_req->post->add_remove == 'clear')
			{
				if ($permissionType == 'membergroup')
					deletePermission($this->_req->post->group, $permission, $this->illegal_permissions);
				else
					deleteBoardPermission($this->_req->post->group, $bid, $permission);
			}
			// Add a permission (either 'set' or 'deny').
			else
			{
				$add_deny = $this->_req->post->add_remove == 'add' ? '1' : '0';
				$permChange = array();
				foreach ($this->_req->post->group as $groupID)
				{
					if ($groupID == -1 && in_array($permission, $this->illegal_guest_permissions))
						continue;

					if ($permissionType == 'membergroup' && $groupID != 1 && $groupID != 3 && (empty($this->illegal_permissions) || !in_array($permission, $this->illegal_permissions)))
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
			$this->permissionsObject->updateChild($this->_req->post->group, $this->_pid);
		}

		redirectexit('action=admin;area=permissions;pid=' . $this->_pid);
	}

	/**
	 * Initializes the necessary to modify a membergroup's permissions.
	 */
	public function action_modify()
	{
		global $context, $txt;

		if (!isset($this->_req->query->group))
			Errors::instance()->fatal_lang_error('no_access', false);

		require_once(SUBSDIR . '/ManagePermissions.subs.php');
		$context['group']['id'] = (int) $this->_req->query->group;

		// It's not likely you'd end up here with this setting disabled.
		if ($this->_req->query->group == 1)
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
				Errors::instance()->fatal_lang_error('cannot_edit_permissions_inherited');
		}
		elseif ($context['group']['id'] == -1)
			$context['group']['name'] = $txt['membergroups_guests'];
		else
			$context['group']['name'] = $txt['membergroups_members'];

		$context['profile']['id'] = $this->_req->getQuery('pid', 'intval', 0);

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
			$permissions['membergroup'] = fetchPermissions($this->_req->query->group);

		// Fetch current board permissions...
		$permissions['board'] = fetchBoardPermissions($context['group']['id'], $context['permission_type'], $context['profile']['id']);

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
		checkSession();
		validateToken('admin-mp');

		// We'll need to init illegal permissions, update child permissions, etc.
		require_once(SUBSDIR . '/Permission.subs.php');
		require_once(SUBSDIR . '/ManagePermissions.subs.php');

		$current_group_id = (int) $this->_req->query->group;
		$this->_pid = $this->_req->getQuery('pid', 'intval');

		// Cannot modify predefined profiles.
		if ($this->_pid > 1 && $this->_pid < 5)
			Errors::instance()->fatal_lang_error('no_access', false);

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
			Errors::instance()->fatal_lang_error('cannot_edit_permissions_inherited');

		$givePerms = array('membergroup' => array(), 'board' => array());

		// Guest group, we need illegal, guest permissions.
		if ($current_group_id == -1)
		{
			$this->illegal_permissions = array_merge($this->illegal_permissions, $this->illegal_guest_permissions);
		}

		// Prepare all permissions that were set or denied for addition to the DB.
		if (isset($this->_req->post->perm) && is_array($this->_req->post->perm))
		{
			foreach ($this->_req->post->perm as $perm_type => $perm_array)
			{
				if (is_array($perm_array))
				{
					foreach ($perm_array as $permission => $value)
						if ($value == 'on' || $value == 'deny')
						{
							// Don't allow people to escalate themselves!
							if (in_array($permission, $this->illegal_permissions))
								continue;

							$givePerms[$perm_type][] = array($permission, $current_group_id, $value == 'deny' ? 0 : 1);
						}
				}
			}
		}

		// Insert the general permissions.
		if ($current_group_id != 3 && empty($this->_pid))
		{
			deleteInvalidPermissions($current_group_id, $this->illegal_permissions);

			if (!empty($givePerms['membergroup']))
				replacePermission($givePerms['membergroup']);
		}

		// Insert the boardpermissions.
		$profileid = max(1, $this->_pid);
		deleteAllBoardPermissions(array($current_group_id), $profileid);

		if (!empty($givePerms['board']))
		{
			foreach ($givePerms['board'] as $k => $v)
				$givePerms['board'][$k][] = $profileid;
			replaceBoardPermission($givePerms['board']);
		}

		// Update any inherited permissions as required.
		$this->permissionsObject->updateChild($current_group_id, $this->_pid);

		// Clear cached privs.
		updateSettings(array('settings_updated' => time()));

		redirectexit('action=admin;area=permissions;pid=' . $this->_pid);
	}

	/**
	 * A screen to set some general settings for permissions.
	 */
	public function action_permSettings_display()
	{
		global $context, $modSettings, $txt, $scripturl;

		require_once(SUBSDIR . '/ManagePermissions.subs.php');

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Some items for the template
		$context['page_title'] = $txt['permission_settings_title'];
		$context['sub_template'] = 'show_settings';
		$context['post_url'] = $scripturl . '?action=admin;area=permissions;save;sa=settings';

		// Saving the settings?
		if (isset($this->_req->query->save))
		{
			checkSession('post');
			call_integration_hook('integrate_save_permission_settings');
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

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

		$settingsForm->prepare();
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

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_permission_settings', array(&$config_vars));

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
		if (isset($this->_req->post->create) && trim($this->_req->post->profile_name) != '')
		{
			checkSession();
			validateToken('admin-mpp');
			copyPermissionProfile($this->_req->post->profile_name, (int) $this->_req->post->copy_from);
		}
		// Renaming?
		elseif (isset($this->_req->post->rename))
		{
			checkSession();
			validateToken('admin-mpp');

			// Just showing the boxes?
			if (!isset($this->_req->post->rename_profile))
				$context['show_rename_boxes'] = true;
			else
			{
				foreach ($this->_req->post->rename_profile as $id => $name)
					renamePermissionProfile($id, $name);
			}
		}
		// Deleting?
		elseif (isset($this->_req->post->delete) && !empty($this->_req->post->delete_profile))
		{
			checkSession('post');
			validateToken('admin-mpp');

			$profiles = array();
			foreach ($this->_req->post->delete_profile as $profile)
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

		addJavascriptVar(array(
			'txt_permissions_commit' => $txt['permissions_commit'],
			'txt_permissions_profile_rename' => $txt['permissions_profile_rename'],
		), true);
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
		$context['current_profile'] = $this->_req->getQuery('pid', 'intval', 1);

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
		if (!empty($this->_req->post->save_changes) && ($context['current_profile'] == 1 || $context['current_profile'] > 4))
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
					$temp = $this->_req->post->{$index};
					if (isset($temp[$group['id']]))
					{
						if ($temp[$group['id']] == 'allow')
						{
							// Give them both sets for fun.
							$new_permissions[] = array($context['current_profile'], $group['id'], $data[0], 1);
							$new_permissions[] = array($context['current_profile'], $group['id'], $data[1], 1);
						}
						elseif ($temp[$group['id']] == 'moderate')
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
