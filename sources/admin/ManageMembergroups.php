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
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * ManageMembergroups controller, administration page for membergroups.
 */
class ManageMembergroups_Controller
{
	/**
	 * Groups Settings form
	 * @var Settings_Form
	 */
	protected $_groupSettings;

	/**
	 * Main dispatcher, the entrance point for all 'Manage Membergroup' actions.
	 * It forwards to a function based on the given subaction, default being subaction 'index', or, without manage_membergroup
	 * permissions, then 'settings'.
	 * Called by ?action=admin;area=membergroups.
	 * Requires the manage_membergroups or the admin_forum permission.
	 *
	 * @uses ManageMembergroups template.
	 * @uses ManageMembers language file.
	*/
	public function action_index()
	{
		global $context, $txt;

		$subActions = array(
			'add' => array(
				'controller' => $this,
				'function' => 'action_add',
				'permission' => 'manage_membergroups'),
			'delete' => array(
				'controller' => $this,
				'function' => 'action_delete',
				'permission' => 'manage_membergroups'),
			'edit' => array(
				'controller' => $this,
				'function' => 'action_edit',
				'permission' => 'manage_membergroups'),
			'index' => array(
				'controller' => $this,
				'function' => 'action_list',
				'permission' => 'manage_membergroups'),
			'members' => array(
				'function' => 'action_groupmembers',
				'permission' => 'manage_membergroups',
				'file' => 'Groups.controller.php',
				'dir' => CONTROLLERDIR),
			'settings' => array(
				'controller' => $this,
				'function' => 'action_groupSettings_display',
				'permission' => 'admin_forum'),
		);

		call_integration_hook('integrate_manage_membergroups', array(&$subActions));

		// Default to sub action 'index' or 'settings' depending on permissions.
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('manage_membergroups') ? 'index' : 'settings');

		$action = new Action();
		$action->initialize($subActions);

		// You way will end here if you don't have permission.
		$action->isAllowedTo($subAction);

		// Language and template stuff, the usual.
		loadLanguage('ManageMembers');
		loadTemplate('ManageMembergroups');

		// Setup the admin tabs.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['membergroups_title'],
			'help' => 'membergroups',
			'description' => $txt['membergroups_description'],
		);

		// Call the right function.
		$action->dispatch($subAction);
	}

	/**
	 * Shows an overview of the current membergroups.
	 * Called by ?action=admin;area=membergroups.
	 * Requires the manage_membergroups permission.
	 * Splits the membergroups in regular ones and post count based groups.
	 * It also counts the number of members part of each membergroup.
	 *
	 * @uses ManageMembergroups template, main.
	 */
	public function action_list()
	{
		global $txt, $scripturl, $context, $user_info;

		$context['page_title'] = $txt['membergroups_title'];

		// The first list shows the regular membergroups.
		$listOptions = array(
			'id' => 'regular_membergroups_list',
			'title' => $txt['membergroups_regular'],
			'base_href' => $scripturl . '?action=admin;area=membergroups' . (isset($_REQUEST['sort2']) ? ';sort2=' . urlencode($_REQUEST['sort2']) : ''),
			'default_sort_col' => 'name',
			'get_items' => array(
				'file' => SUBSDIR . '/Membergroups.subs.php',
				'function' => 'list_getMembergroups',
				'params' => array(
					'regular',
					$user_info['id'],
					allowedTo('manage_membergroups'),
					allowedTo('admin_forum'),
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

							// Since the moderator group has no explicit members, no link is needed.
							if ($rowData[\'id_group\'] == 3)
								$group_name = $rowData[\'group_name\'];
							else
							{
								$color_style = empty($rowData[\'online_color\']) ? \'\' : sprintf(\' style="color: %1$s;"\', $rowData[\'online_color\']);
								$group_name = sprintf(\'<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d"%3$s>%4$s</a>\', $scripturl, $rowData[\'id_group\'], $color_style, $rowData[\'group_name\']);
							}

							// Add a help option for moderator and administrator.
							if ($rowData[\'id_group\'] == 1)
								$group_name .= sprintf(\' (<a href="%1$s?action=quickhelp;help=membergroup_administrator" onclick="return reqOverlayDiv(this.href);">?</a>)\', $scripturl);
							elseif ($rowData[\'id_group\'] == 3)
								$group_name .= sprintf(\' (<a href="%1$s?action=quickhelp;help=membergroup_moderator" onclick="return reqOverlayDiv(this.href);">?</a>)\', $scripturl);

							return $group_name;
						'),
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
					),
				),
				'icons' => array(
					'header' => array(
						'value' => $txt['membergroups_icons'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $settings;

							if (!empty($rowData[\'icons\'][0]) && !empty($rowData[\'icons\'][1]))
								return str_repeat(\'<img src="\' . $settings[\'images_url\'] . \'/group_icons/\' . $rowData[\'icons\'][1] . \'" alt="*" />\', $rowData[\'icons\'][0]);
							else
								return \'\';
						'),
					),
					'sort' => array(
						'default' => 'mg.icons',
						'reverse' => 'mg.icons DESC',
					)
				),
				'members' => array(
					'header' => array(
						'value' => $txt['membergroups_members_top'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt;

							// No explicit members for the moderator group.
							return $rowData[\'id_group\'] == 3 ? $txt[\'membergroups_guests_na\'] : comma_format($rowData[\'num_members\']);
						'),
						'class' => 'centertext',
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					),
				),
				'modify' => array(
					'header' => array(
						'value' => $txt['modify'],
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
							'params' => array(
								'id_group' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<a class="button_link" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . $txt['membergroups_add_group'] . '</a>',
				),
			),
		);

		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);

		// The second list shows the post count based groups.
		$listOptions = array(
			'id' => 'post_count_membergroups_list',
			'title' => $txt['membergroups_post'],
			'base_href' => $scripturl . '?action=admin;area=membergroups' . (isset($_REQUEST['sort']) ? ';sort=' . urlencode($_REQUEST['sort']) : ''),
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

							$colorStyle = empty($rowData[\'online_color\']) ? \'\' : sprintf(\' style="color: %1$s;"\', $rowData[\'online_color\']);
							return sprintf(\'<a href="%1$s?action=moderate;area=viewgroups;sa=members;group=%2$d"%3$s>%4$s</a>\', $scripturl, $rowData[\'id_group\'], $colorStyle, $rowData[\'group_name\']);
						'),
					),
					'sort' => array(
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					),
				),
				'icons' => array(
					'header' => array(
						'value' => $txt['membergroups_icons'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $settings;

							if (!empty($rowData[\'icons\'][0]) && !empty($rowData[\'icons\'][1]))
								return str_repeat(\'<img src="\' . $settings[\'images_url\'] . \'/group_icons/\' . $rowData[\'icons\'][1] . \'" alt="*" />\', $rowData[\'icons\'][0]);
							else
								return \'\';
						'),
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, icons',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, icons DESC',
					)
				),
				'members' => array(
					'header' => array(
						'value' => $txt['membergroups_members_top'],
						'class' => 'centertext',
					),
					'data' => array(
						'db' => 'num_members',
						'class' => 'centertext',
					),
					'sort' => array(
						'default' => '1 DESC',
						'reverse' => '1',
					),
				),
				'required_posts' => array(
					'header' => array(
						'value' => $txt['membergroups_min_posts'],
						'class' => 'centertext',
					),
					'data' => array(
						'db' => 'min_posts',
						'class' => 'centertext',
					),
					'sort' => array(
						'default' => 'mg.min_posts',
						'reverse' => 'mg.min_posts DESC',
					),
				),
				'modify' => array(
					'header' => array(
						'value' => $txt['modify'],
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
							'params' => array(
								'id_group' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<a class="button_link" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;postgroup">' . $txt['membergroups_add_group'] . '</a>',
				),
			),
		);

		createList($listOptions);
	}

	/**
	 * This function handles adding a membergroup and setting some initial properties.
	 * Called by ?action=admin;area=membergroups;sa=add.
	 * It requires the manage_membergroups permission.
	 * Allows to use a predefined permission profile or copy one from another group.
	 * Redirects to action=admin;area=membergroups;sa=edit;group=x.
	 *
	 * @uses the new_group sub template of ManageMembergroups.
	 */
	public function action_add()
	{
		global $context, $txt, $modSettings;

		require_once(SUBSDIR . '/Membergroups.subs.php');

		// A form was submitted, we can start adding.
		if (isset($_POST['group_name']) && trim($_POST['group_name']) != '')
		{
			checkSession();
			validateToken('admin-mmg');

			$postCountBasedGroup = isset($_POST['min_posts']) && (!isset($_POST['postgroup_based']) || !empty($_POST['postgroup_based']));
			$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];

			// @todo Check for members with same name too?

			// Don't allow copying of a real priviledged person!
			require_once(SUBSDIR . '/Permission.subs.php');

			loadIllegalPermissions();
			$id_group = getMaxGroupID() +1;
			$minposts = !empty($_POST['min_posts']) ? (int) $_POST['min_posts'] : '-1';

			addMembergroup($id_group, $_POST['group_name'], $minposts, $_POST['group_type']);


			call_integration_hook('integrate_add_membergroup', array($id_group, $postCountBasedGroup));

			// Update the post groups now, if this is a post group!
			if (isset($_POST['min_posts']))
				updateStats('postgroups');

			// You cannot set permissions for post groups if they are disabled.
			if ($postCountBasedGroup && empty($modSettings['permission_enable_postgroups']))
				$_POST['perm_type'] = '';

			if ($_POST['perm_type'] == 'predefined')
			{
				// Set default permission level.
				require_once(ADMINDIR . '/ManagePermissions.php');
				setPermissionLevel($_POST['level'], $id_group, null);
			}
			// Copy or inherit the permissions!
			elseif ($_POST['perm_type'] == 'copy' || $_POST['perm_type'] == 'inherit')
			{
				$copy_id = $_POST['perm_type'] == 'copy' ? (int) $_POST['copyperm'] : (int) $_POST['inheritperm'];

				// Are you a powerful admin?
				if (!allowedTo('admin_forum'))
				{
					require_once(SUBSDIR . '/Membergroups.subs.php');
					$copy_type = membergroupsById($copy_id);

					// Protected groups are... well, protected!
					if ($copy_type['group_type'] == 1)
						fatal_lang_error('membergroup_does_not_exist');
				}

				// Don't allow copying of a real priviledged person!
				require_once(ADMINDIR . '/ManagePermissions.php');
				loadIllegalPermissions();

				copyPermissions($id_group, $copy_id, $context['illegal_permissions']);
				copyBoardPermissions($id_group, $copy_id);

				// Also get some membergroup information if we're copying and not copying from guests...
				if ($copy_id > 0 && $_POST['perm_type'] == 'copy')
					updateCopiedGroup($id_group, $copy_id);

				// If inheriting say so...
				elseif ($_POST['perm_type'] == 'inherit')
					updateInheritedGroup($id_group, $copy_id);
			}

			// Make sure all boards selected are stored in a proper array.
			$accesses = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? array() : $_POST['boardaccess'];
			$changed_boards['allow'] = array();
			$changed_boards['deny'] = array();
			$changed_boards['ignore'] = array();
			foreach ($accesses as $group_id => $action)
				$changed_boards[$action][] = (int) $group_id;

			foreach (array('allow', 'deny') as $board_action)
			{
				// Only do this if they have special access requirements.
				if (!empty($changed_boards[$board_action]))
					assignGroupToBoards($id_group, $changed_boards, $board_action);
			}

			// If this is joinable then set it to show group membership in people's profiles.
			if (empty($modSettings['show_group_membership']) && $_POST['group_type'] > 1)
				updateSettings(array('show_group_membership' => 1));

			// Rebuild the group cache.
			updateSettings(array(
				'settings_updated' => time(),
			));

			// We did it.
			logAction('add_group', array('group' => $_POST['group_name']), 'admin');

			// Go change some more settings.
			redirectexit('action=admin;area=membergroups;sa=edit;group=' . $id_group);
		}

		// Just show the 'add membergroup' screen.
		$context['page_title'] = $txt['membergroups_new_group'];
		$context['sub_template'] = 'new_group';
		$context['post_group'] = isset($_REQUEST['postgroup']);
		$context['undefined_group'] = !isset($_REQUEST['postgroup']) && !isset($_REQUEST['generalgroup']);
		$context['allow_protected'] = allowedTo('admin_forum');

		if (!empty($modSettings['deny_boards_access']))
			loadLanguage('ManagePermissions');

		require_once(SUBSDIR . '/Membergroups.subs.php');
		$context['groups'] = getBasicMembergroupData(array('globalmod'), array(), 'min_posts, id_group != {int:global_mod_group}, group_name');

		require_once(SUBSDIR . '/Boards.subs.php');
		$context += getBoardList(array('use_permissions' => true));

		// Include a list of boards per category for easy toggling.
		foreach ($context['categories'] as $category)
			$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

		createToken('admin-mmg');
	}

	/**
	 * Deleting a membergroup by URL (not implemented).
	 * Called by ?action=admin;area=membergroups;sa=delete;group=x;session_var=y.
	 * Requires the manage_membergroups permission.
	 * Redirects to ?action=admin;area=membergroups.
	 *
	 * @todo look at this
	 */
	public function action_delete()
	{
		checkSession('get');

		require_once(SUBSDIR . '/Membergroups.subs.php');
		deleteMembergroups((int) $_REQUEST['group']);

		// Go back to the membergroup index.
		redirectexit('action=admin;area=membergroups;');
	}

	/**
	 * Editing a membergroup.
	 * Screen to edit a specific membergroup.
	 * Called by ?action=admin;area=membergroups;sa=edit;group=x.
	 * It requires the manage_membergroups permission.
	 * Also handles the delete button of the edit form.
	 * Redirects to ?action=admin;area=membergroups.
	 *
	 * @uses the edit_group sub template of ManageMembergroups.
	 */
	public function action_edit()
	{
		global $context, $txt, $modSettings;

		$_REQUEST['group'] = isset($_REQUEST['group']) && $_REQUEST['group'] > 0 ? (int) $_REQUEST['group'] : 0;

		if (!empty($modSettings['deny_boards_access']))
			loadLanguage('ManagePermissions');

		require_once(SUBSDIR . '/Membergroups.subs.php');
		// Make sure this group is editable.
		if (!empty($_REQUEST['group']))
			$groups = membergroupsById($_REQUEST['group'], 1, false, false, allowedTo('admin_forum'));

		// Now, do we have a valid id?
		if (empty($groups['id_group']))
			fatal_lang_error('membergroup_does_not_exist', false);

		// The delete this membergroup button was pressed.
		if (isset($_POST['delete']))
		{
			checkSession();
			validateToken('admin-mmg');

			// Let's delete the group
			deleteMembergroups($groups['id_group']);

			redirectexit('action=admin;area=membergroups;');
		}
		// A form was submitted with the new membergroup settings.
		elseif (isset($_POST['save']))
		{
			// Validate the session.
			checkSession();
			validateToken('admin-mmg');

			// Can they really inherit from this group?
			if (isset($_POST['group_inherit']) && $_POST['group_inherit'] != -2 && !allowedTo('admin_forum'))
				$inherit_type = membergroupsById((int) $_POST['group_inherit']);

			// Set variables to their proper value.
			$_POST['max_messages'] = isset($_POST['max_messages']) ? (int) $_POST['max_messages'] : 0;
			$_POST['min_posts'] = isset($_POST['min_posts']) && isset($_POST['group_type']) && $_POST['group_type'] == -1 && $groups['id_group'] > 3 ? abs($_POST['min_posts']) : ($groups['id_group'] == 4 ? 0 : -1);
			$_POST['icons'] = (empty($_POST['icon_count']) || $_POST['icon_count'] < 0) ? '' : min((int) $_POST['icon_count'], 99) . '#' . $_POST['icon_image'];
			$_POST['group_desc'] = isset($_POST['group_desc']) && ($groups['id_group'] == 1 || (isset($_POST['group_type']) && $_POST['group_type'] != -1)) ? trim($_POST['group_desc']) : '';
			$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];
			$_POST['group_hidden'] = empty($_POST['group_hidden']) || $_POST['min_posts'] != -1 || $groups['id_group'] == 3 ? 0 : (int) $_POST['group_hidden'];
			$_POST['group_inherit'] = $groups['id_group'] > 1 && $groups['id_group'] != 3 && (empty($inherit_type['group_type']) || $inherit_type['group_type'] != 1) ? (int) $_POST['group_inherit'] : -2;

			//@todo Don't set online_color for the Moderators group?

			// Do the update of the membergroup settings.
			$properties = array(
					'max_messages' => $_POST['max_messages'],
					'min_posts' => $_POST['min_posts'],
					'group_type' => $_POST['group_type'],
					'group_hidden' => $_POST['group_hidden'],
					'group_inherit' => $_POST['group_inherit'],
					'current_group' => $groups['id_group'],
					'group_name' => $_POST['group_name'],
					'online_color' => $_POST['online_color'],
					'icons' => $_POST['icons'],
					'group_desc' => $_POST['group_desc'],
				);
			updateMembergroupProperties($properties);

			call_integration_hook('integrate_save_membergroup', array($groups['id_group']));

			// Time to update the boards this membergroup has access to.
			if ($groups['id_group'] == 2 || $groups['id_group'] > 3)
			{
				$accesses = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? array() : $_POST['boardaccess'];
				$changed_boards['allow'] = array();
				$changed_boards['deny'] = array();
				$changed_boards['ignore'] = array();
				foreach ($accesses as $group_id => $action)
					$changed_boards[$action][] = (int) $group_id;

				foreach (array('allow', 'deny') as $board_action)
				{
					// Find all board this group is in, but shouldn't be in.
					detachGroupFromBoards($groups['id_group'], $changed_boards, $board_action);

					// Add the membergroup to all boards that hadn't been set yet.
					if (!empty($changed_boards[$board_action]))
						assignGroupToBoards($groups['id_group'], $changed_boards, $board_action);
				}
			}

			// Remove everyone from this group!
			if ($_POST['min_posts'] != -1)
				detachDeletedGroupFromMembers($groups['id_group']);

			elseif ($groups['id_group'] != 3)
			{
				// Making it a hidden group? If so remove everyone with it as primary group (Actually, just make them additional).
				if ($_POST['group_hidden'] == 2)
					setGroupToHidden($groups['id_group']);


				// Either way, let's check our "show group membership" setting is correct.
				validateShowGroupMembership();
			}

			// Do we need to set inherited permissions?
			if ($_POST['group_inherit'] != -2 && $_POST['group_inherit'] != $_POST['old_inherit'])
			{
				require_once(ADMINDIR . '/ManagePermissions.php');
				updateChildPermissions($_POST['group_inherit']);
			}

			// Finally, moderators!
			$moderator_string = isset($_POST['group_moderators']) ? trim($_POST['group_moderators']) : '';
			detachGroupModerators($groups['id_group']);

			if ((!empty($moderator_string) || !empty($_POST['moderator_list'])) && $_POST['min_posts'] == -1 && $groups['id_group'] != 3)
			{
				// Get all the usernames from the string
				if (!empty($moderator_string))
				{
					$moderator_string = strtr(preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', htmlspecialchars($moderator_string), ENT_QUOTES), array('&quot;' => '"'));
					preg_match_all('~"([^"]+)"~', $moderator_string, $matches);
					$moderators = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)));
					for ($k = 0, $n = count($moderators); $k < $n; $k++)
					{
						$moderators[$k] = trim($moderators[$k]);

						if (strlen($moderators[$k]) == 0)
							unset($moderators[$k]);
					}

					// Find all the id_member's for the member_name's in the list.
					if (!empty($moderators))
						$group_moderators = getIDMemberFromGroupModerators($moderators);
				}
				else
				{
					$moderators = array();
					foreach ($_POST['moderator_list'] as $moderator)
						$moderators[] = (int) $moderator;

					$group_moderators = array();
					if (!empty($moderators))
					{
						require_once(SUBSDIR . '/Members.subs.php');
						$members = getBasicMemberData($moderators);
						foreach ($members as $member)
							$group_moderators[] = $member['id_member'];
					}
				}

				// Found some?
				if (!empty($group_moderators))
					assignGroupModerators($groups['id_group'], $group_moderators);
			}

			// There might have been some post group changes.
			updateStats('postgroups');
			// We've definitely changed some group stuff.
			updateSettings(array(
				'settings_updated' => time(),
			));

			// Log the edit.
			logAction('edited_group', array('group' => $_POST['group_name']), 'admin');

			redirectexit('action=admin;area=membergroups');
		}

		// Fetch the current group information.
		$row = membergroupsById($groups['id_group'], 1, true, false, allowedTo('admin_forum'));

		if (empty($row))
			fatal_lang_error('membergroup_does_not_exist', false);

		$row['icons'] = explode('#', $row['icons']);

		$context['group'] = array(
			'id' => $groups['id_group'],
			'name' => $row['group_name'],
			'description' => htmlspecialchars($row['description']),
			'editable_name' => $row['group_name'],
			'color' => $row['online_color'],
			'min_posts' => $row['min_posts'],
			'max_messages' => $row['max_messages'],
			'icon_count' => (int) $row['icons'][0],
			'icon_image' => isset($row['icons'][1]) ? $row['icons'][1] : '',
			'is_post_group' => $row['min_posts'] != -1,
			'type' => $row['min_posts'] != -1 ? 0 : $row['group_type'],
			'hidden' => $row['min_posts'] == -1 ? $row['hidden'] : 0,
			'inherited_from' => $row['id_parent'],
			'allow_post_group' => $groups['id_group'] == 2 || $groups['id_group'] > 4,
			'allow_delete' => $groups['id_group'] == 2 || $groups['id_group'] > 4,
			'allow_protected' => allowedTo('admin_forum'),
		);

		// Get any moderators for this group
		$context['group']['moderators'] = getGroupModerators($groups['id_group']);
		$context['group']['moderator_list'] = empty($context['group']['moderators']) ? '' : '&quot;' . implode('&quot;, &quot;', $context['group']['moderators']) . '&quot;';

		if (!empty($context['group']['moderators']))
			list ($context['group']['last_moderator_id']) = array_slice(array_keys($context['group']['moderators']), -1);

		// Get a list of boards this membergroup is allowed to see.
		$context['boards'] = array();
		if ($groups['id_group'] == 2 || $groups['id_group'] > 3)
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$context += getBoardList(array('access' => $groups['id_group'], 'not_redirection' => true));

			// Include a list of boards per category for easy toggling.
			foreach ($context['categories'] as $category)
				$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
		}

		// Finally, get all the groups this could be inherited off.
		$context['inheritable_groups'] = getInheritableGroups($groups['id_group']);

		call_integration_hook('integrate_view_membergroup');

		$context['sub_template'] = 'edit_group';
		$context['page_title'] = $txt['membergroups_edit_group'];

		createToken('admin-mmg');
	}

	/**
	 * Set some general membergroup settings and permissions.
	 * Called by ?action=admin;area=membergroups;sa=settings
	 * Requires the admin_forum permission (and manage_permissions for changing permissions)
	 * Redirects to itself.
	 *
	 * @uses membergroup_settings sub template of ManageMembergroups.
	 */
	public function action_groupSettings_display()
	{
		global $context, $scripturl, $txt;

		$context['sub_template'] = 'show_settings';
		$context['page_title'] = $txt['membergroups_settings'];

		// Needed for the settings functions.
		require_once(SUBSDIR . '/Settings.class.php');

		// initialize the form
		$this->_initGroupSettingsForm();

		// Don't allow assignment of guests.
		$context['permissions_excluded'] = array(-1);

		$config_vars = $this->_groupSettings->settings();

		call_integration_hook('integrate_modify_membergroup_settings', array(&$config_vars));

		if (isset($_REQUEST['save']))
		{
			checkSession();
			call_integration_hook('integrate_save_membergroup_settings');

			// Yeppers, saving this...
			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=membergroups;sa=settings');
		}

		// Some simple context.
		$context['post_url'] = $scripturl . '?action=admin;area=membergroups;save;sa=settings';
		$context['settings_title'] = $txt['membergroups_settings'];

		// We need this for the in-line permissions
		createToken('admin-mp');

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Return the configuration settings for membergroups management.
	 */
	private function _initGroupSettingsForm()
	{
		// instantiate the form
		$this->_groupSettings = new Settings_Form();

		// Only one thing here!
		$config_vars = array(
				array('permissions', 'manage_membergroups'),
		);

		return $this->_groupSettings->settings($config_vars);
	}

	/**
	 * Return the configuration settings for membergroups management.
	 */
	public function settings()
	{
		// Only one thing here!
		$config_vars = array(
				array('permissions', 'manage_membergroups'),
		);

		return $config_vars;
	}
}