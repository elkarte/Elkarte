<?php

/**
 * Handles the administration page for membergroups.
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
 * ManageMembergroups controller, administration page for membergroups.
 *
 * @package Membergroups
 */
class ManageMembergroups_Controller extends Action_Controller
{
	/**
	 * Groups Settings form
	 * @var Settings_Form
	 */
	protected $_groupSettings;

	/**
	 * Main dispatcher, the en\trance point for all 'Manage Membergroup' actions.
	 *
	 * What it does:
	 * - It forwards to a function based on the given subaction, default being subaction 'index', or, without manage_membergroup
	 * permissions, then 'settings'.
	 * - Called by ?action=admin;area=membergroups.
	 * - Requires the manage_membergroups or the admin_forum permission.
	 *
	 * @uses ManageMembergroups template.
	 * @uses ManageMembers language file.
	 * @see Action_Controller::action_index()
	*/
	public function action_index()
	{
		global $context, $txt;

		// Language and template stuff, the usual.
		loadLanguage('ManageMembers');
		loadTemplate('ManageMembergroups');

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
				'controller' => 'Groups_Controller',
				'function' => 'action_index',
				'permission' => 'manage_membergroups'),
			'settings' => array(
				'controller' => $this,
				'function' => 'action_groupSettings_display',
				'permission' => 'admin_forum'),
		);

		$action = new Action('manage_membergroups');

		// Setup the admin tabs.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['membergroups_title'],
			'help' => 'membergroups',
			'description' => $txt['membergroups_description'],
		);

		// Default to sub action 'index' or 'settings' depending on permissions.
		$subAction = isset($this->_req->query->sa) && isset($subActions[$this->_req->query->sa]) ? $this->_req->query->sa : (allowedTo('manage_membergroups') ? 'index' : 'settings');

		// Set that subaction, call integrate_sa_manage_membergroups
		$subAction = $action->initialize($subActions, $subAction);

		// Final items for the template
		$context['page_title'] = $txt['membergroups_title'];
		$context['sub_action'] = $subAction;

		// Call the right function.
		$action->dispatch($subAction);
	}

	/**
	 * Shows an overview of the current membergroups.
	 *
	 * What it does:
	 * - Called by ?action=admin;area=membergroups.
	 * - Requires the manage_membergroups permission.
	 * - Splits the membergroups in regular ones and post count based groups.
	 * - It also counts the number of members part of each membergroup.
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
			'base_href' => $scripturl . '?action=admin;area=membergroups' . (isset($this->_req->query->sort2) ? ';sort2=' . urlencode($this->_req->query->sort2) : ''),
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
						'function' => function ($rowData) {
							global $scripturl;

							// Since the moderator group has no explicit members, no link is needed.
							if ($rowData['id_group'] == 3)
								$group_name = $rowData['group_name'];
							else
							{
								$group_name = sprintf('<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d">%3$s</a>', $scripturl, $rowData['id_group'], $rowData['group_name_color']);
							}

							// Add a help option for moderator and administrator.
							if ($rowData['id_group'] == 1)
								$group_name .= sprintf(' (<a href="%1$s?action=quickhelp;help=membergroup_administrator" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"></a>)', $scripturl);
							elseif ($rowData['id_group'] == 3)
								$group_name .= sprintf(' (<a href="%1$s?action=quickhelp;help=membergroup_moderator" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"></a>)', $scripturl);

							return $group_name;
						},
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
						'function' => function ($rowData) {
							global $settings;

							if (!empty($rowData['icons'][0]) && !empty($rowData['icons'][1]))
								return str_repeat('<img src="' . $settings['images_url'] . '/group_icons/' . $rowData['icons'][1] . '" alt="*" />', $rowData['icons'][0]);
							else
								return '';
						},
					),
					'sort' => array(
						'default' => 'mg.icons',
						'reverse' => 'mg.icons DESC',
					)
				),
				'members' => array(
					'header' => array(
						'value' => $txt['membergroups_members_top'],
					),
					'data' => array(
						'function' => function ($rowData) {
							global $txt;

							// No explicit members for the moderator group.
							return $rowData['id_group'] == 3 ? $txt['membergroups_guests_na'] : comma_format($rowData['num_members']);
						},
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					),
				),
				'modify' => array(
					'header' => array(
						'value' => $txt['modify'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
							'params' => array(
								'id_group' => false,
							),
						),
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'class' => 'submitbutton',
					'value' => '<a class="linkbutton" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;generalgroup">' . $txt['membergroups_add_group'] . '</a>',
				),
			),
		);

		createList($listOptions);

		// The second list shows the post count based groups.
		$listOptions = array(
			'id' => 'post_count_membergroups_list',
			'title' => $txt['membergroups_post'],
			'base_href' => $scripturl . '?action=admin;area=membergroups' . (isset($this->_req->query->sort) ? ';sort=' . urlencode($this->_req->query->sort) : ''),
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
						'function' => function ($rowData) {
							global $scripturl;

							return sprintf('<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d">%3$s</a>', $scripturl, $rowData['id_group'], $rowData['group_name_color']);
						},
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
						'function' => function ($rowData) {
							global $settings;

							if (!empty($rowData['icons'][0]) && !empty($rowData['icons'][1]))
								return str_repeat('<img src="' . $settings['images_url'] . '/group_icons/' . $rowData['icons'][1] . '" alt="*" />', $rowData['icons'][0]);
							else
								return '';
						},
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, icons',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, icons DESC',
					)
				),
				'members' => array(
					'header' => array(
						'value' => $txt['membergroups_members_top'],
					),
					'data' => array(
						'db' => 'num_members',
					),
					'sort' => array(
						'default' => '1 DESC',
						'reverse' => '1',
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
				'modify' => array(
					'header' => array(
						'value' => $txt['modify'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
							'params' => array(
								'id_group' => false,
							),
						),
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'class' => 'submitbutton',
					'value' => '<a class="linkbutton" href="' . $scripturl . '?action=admin;area=membergroups;sa=add;postgroup">' . $txt['membergroups_add_group'] . '</a>',
				),
			),
		);

		createList($listOptions);
	}

	/**
	 * This function handles adding a membergroup and setting some initial properties.
	 *
	 * What it does:
	 * -Called by ?action=admin;area=membergroups;sa=add.
	 * -It requires the manage_membergroups permission.
	 * -Allows to use a predefined permission profile or copy one from another group.
	 * -Redirects to action=admin;area=membergroups;sa=edit;group=x.
	 *
	 * @uses the new_group sub template of ManageMembergroups.
	 */
	public function action_add()
	{
		global $context, $txt, $modSettings;

		require_once(SUBSDIR . '/Membergroups.subs.php');

		// A form was submitted, we can start adding.
		if (isset($this->_req->post->group_name) && trim($this->_req->post->group_name) != '')
		{
			checkSession();
			validateToken('admin-mmg');

			$postCountBasedGroup = isset($this->_req->post->min_posts) && (!isset($this->_req->post->postgroup_based) || !empty($this->_req->post->postgroup_based));
			$group_type = !isset($this->_req->post->group_type) || $this->_req->post->group_type < 0 || $this->_req->post->group_type > 3 || ($this->_req->post->group_type == 1 && !allowedTo('admin_forum')) ? 0 : (int) $this->_req->post->group_type;

			// @todo Check for members with same name too?

			// Don't allow copying of a real privileged person!
			$permissionsObject = new Permissions;
			$illegal_permissions = $permissionsObject->getIllegalPermissions();
			$id_group = getMaxGroupID() + 1;
			$minposts = !empty($this->_req->post->min_posts) ? (int) $this->_req->post->min_posts : '-1';

			addMembergroup($id_group, $this->_req->post->group_name, $minposts, $group_type);

			call_integration_hook('integrate_add_membergroup', array($id_group, $postCountBasedGroup));

			// Update the post groups now, if this is a post group!
			if (isset($this->_req->post->min_posts))
			{
				require_once(SUBSDIR . '/Membergroups.subs.php');
				updatePostGroupStats();
			}

			// You cannot set permissions for post groups if they are disabled.
			if ($postCountBasedGroup && empty($modSettings['permission_enable_postgroups']))
				$this->_req->post->perm_type = '';

			if ($this->_req->post->perm_type == 'predefined')
			{
				// Set default permission level.
				require_once(SUBSDIR . '/ManagePermissions.subs.php');
				setPermissionLevel($this->_req->post->level, $id_group, null);
			}
			// Copy or inherit the permissions!
			elseif ($this->_req->post->perm_type == 'copy' || $this->_req->post->perm_type == 'inherit')
			{
				$copy_id = $this->_req->post->perm_type == 'copy' ? (int) $this->_req->post->copyperm : (int) $this->_req->post->inheritperm;

				// Are you a powerful admin?
				if (!allowedTo('admin_forum'))
				{
					$copy_type = membergroupById($copy_id);

					// Keep protected groups ... well, protected!
					if ($copy_type['group_type'] == 1)
						Errors::instance()->fatal_lang_error('membergroup_does_not_exist');
				}

				// Don't allow copying of a real privileged person!
				copyPermissions($id_group, $copy_id, $illegal_permissions);
				copyBoardPermissions($id_group, $copy_id);

				// Also get some membergroup information if we're copying and not copying from guests...
				if ($copy_id > 0 && $this->_req->post->perm_type == 'copy')
					updateCopiedGroup($id_group, $copy_id);

				// If inheriting say so...
				elseif ($this->_req->post->perm_type == 'inherit')
					updateInheritedGroup($id_group, $copy_id);
			}

			// Make sure all boards selected are stored in a proper array.
			$changed_boards = array();
			$accesses = empty($this->_req->post->boardaccess) || !is_array($this->_req->post->boardaccess) ? array() : $this->_req->post->boardaccess;
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
			if (empty($modSettings['show_group_membership']) && $group_type > 1)
				updateSettings(array('show_group_membership' => 1));

			// Rebuild the group cache.
			updateSettings(array(
				'settings_updated' => time(),
			));

			// We did it.
			logAction('add_group', array('group' => $this->_req->post->group_name), 'admin');

			// Go change some more settings.
			redirectexit('action=admin;area=membergroups;sa=edit;group=' . $id_group);
		}

		// Just show the 'add membergroup' screen.
		$context['page_title'] = $txt['membergroups_new_group'];
		$context['sub_template'] = 'new_group';
		$context['post_group'] = isset($this->_req->query->postgroup);
		$context['undefined_group'] = !isset($this->_req->query->postgroup) && !isset($this->_req->query->generalgroup);
		$context['allow_protected'] = allowedTo('admin_forum');

		if (!empty($modSettings['deny_boards_access']))
			loadLanguage('ManagePermissions');

		$context['groups'] = getBasicMembergroupData(array('globalmod'), array(), 'min_posts, id_group != {int:global_mod_group}, group_name');

		require_once(SUBSDIR . '/Boards.subs.php');
		$context += getBoardList();

		// Include a list of boards per category for easy toggling.
		foreach ($context['categories'] as $category)
			$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

		createToken('admin-mmg');
	}

	/**
	 * Deleting a membergroup by URL (not implemented).
	 *
	 * What it does:
	 * - Called by ?action=admin;area=membergroups;sa=delete;group=x;session_var=y.
	 * - Requires the manage_membergroups permission.
	 * - Redirects to ?action=admin;area=membergroups.
	 *
	 * @todo look at this
	 */
	public function action_delete()
	{
		checkSession('get');

		require_once(SUBSDIR . '/Membergroups.subs.php');
		deleteMembergroups((int) $this->_req->query->group);

		// Go back to the membergroup index.
		redirectexit('action=admin;area=membergroups;');
	}

	/**
	 * Editing a membergroup.
	 *
	 * What it does:
	 * - Screen to edit a specific membergroup.
	 * - Called by ?action=admin;area=membergroups;sa=edit;group=x.
	 * - It requires the manage_membergroups permission.
	 * - Also handles the delete button of the edit form.
	 * - Redirects to ?action=admin;area=membergroups.
	 *
	 * @uses the edit_group sub template of ManageMembergroups.
	 */
	public function action_edit()
	{
		global $context, $txt, $modSettings;

		$current_group_id = isset($this->_req->query->group) ? (int) $this->_req->query->group : 0;
		$current_group = array();

		if (!empty($modSettings['deny_boards_access']))
			loadLanguage('ManagePermissions');

		require_once(SUBSDIR . '/Membergroups.subs.php');

		// Make sure this group is editable.
		if (!empty($current_group_id))
			$current_group = membergroupById($current_group_id);

		// Now, do we have a valid id?
		if (!allowedTo('admin_forum') && !empty($current_group_id) && $current_group['group_type'] == 1)
			Errors::instance()->fatal_lang_error('membergroup_does_not_exist', false);

		// The delete this membergroup button was pressed.
		if (isset($this->_req->post->delete))
		{
			checkSession();
			validateToken('admin-mmg');

			if (empty($current_group_id))
				Errors::instance()->fatal_lang_error('membergroup_does_not_exist', false);

			// Let's delete the group
			deleteMembergroups($current_group['id_group']);

			redirectexit('action=admin;area=membergroups;');
		}
		// A form was submitted with the new membergroup settings.
		elseif (isset($this->_req->post->save))
		{
			// Validate the session.
			checkSession();
			validateToken('admin-mmg');

			if (empty($current_group_id))
				Errors::instance()->fatal_lang_error('membergroup_does_not_exist', false);

			$validator = new Data_Validator();

			// Cleanup the inputs! :D
			$validator->sanitation_rules(array(
				'max_messages' => 'intval',
				'min_posts' => 'intval|abs',
				'group_type' => 'intval',
				'group_desc' => 'trim|Util::htmlspecialchars',
				'group_name' => 'trim|Util::htmlspecialchars',
				'group_hidden' => 'intval',
				'group_inherit' => 'intval',
				'icon_count' => 'intval',
				'icon_image' => 'trim|Util::htmlspecialchars',
				'online_color' => 'trim|valid_color',
			));
			$validator->input_processing(array(
				'boardaccess' => 'array',
			));
			$validator->validation_rules(array(
				'boardaccess' => 'contains[allow,ignore,deny]',
			));
			$validator->validate($this->_req->post);

			// Insert the clean data
			$our_post = array_replace((array) $this->_req->post, $validator->validation_data());

			// Can they really inherit from this group?
			$inherit_type  = array();
			if ($our_post['group_inherit'] != -2 && !allowedTo('admin_forum'))
				$inherit_type = membergroupById($our_post['group_inherit']);

			$min_posts = $our_post['group_type'] == -1 && $our_post['min_posts'] >= 0 && $current_group['id_group'] > 3 ? $our_post['min_posts'] : ($current_group['id_group'] == 4 ? 0 : -1);
			$group_inherit = $current_group['id_group'] > 1 && $current_group['id_group'] != 3 && (empty($inherit_type['group_type']) || $inherit_type['group_type'] != 1) ? $our_post['group_inherit'] : -2;

			//@todo Don't set online_color for the Moderators group?

			// Do the update of the membergroup settings.
			$properties = array(
				'max_messages' => $our_post['max_messages'],
				'min_posts' => $min_posts,
				'group_type' => $our_post['group_type'] < 0 || $our_post['group_type'] > 3 || ($our_post['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : $our_post['group_type'],
				'hidden' => !$our_post['group_hidden'] || $min_posts != -1 || $current_group['id_group'] == 3 ? 0 : $our_post['group_hidden'],
				'id_parent' => $group_inherit,
				'current_group' => $current_group['id_group'],
				'group_name' => $our_post['group_name'],
				'online_color' => $our_post['online_color'],
				'icons' => $our_post['icon_count'] <= 0 ? '' : min($our_post['icon_count'], 10) . '#' . $our_post['icon_image'],
				// /me wonders why admin is *so* special
				'description' => $current_group['id_group'] == 1 || $our_post['group_type'] != -1 ? $our_post['group_desc'] : '',
			);
			updateMembergroupProperties($properties);

			call_integration_hook('integrate_save_membergroup', array($current_group['id_group']));

			// Time to update the boards this membergroup has access to.
			if ($current_group['id_group'] == 2 || $current_group['id_group'] > 3)
			{
				$changed_boards = array();
				$changed_boards['allow'] = array();
				$changed_boards['deny'] = array();
				$changed_boards['ignore'] = array();

				if ($our_post['boardaccess'])
					foreach ($our_post['boardaccess'] as $group_id => $action)
						$changed_boards[$action][] = (int) $group_id;

				foreach (array('allow', 'deny') as $board_action)
				{
					// Find all board this group is in, but shouldn't be in.
					detachGroupFromBoards($current_group['id_group'], $changed_boards, $board_action);

					// Add the membergroup to all boards that hadn't been set yet.
					if (!empty($changed_boards[$board_action]))
						assignGroupToBoards($current_group['id_group'], $changed_boards, $board_action);
				}
			}

			// Remove everyone from this group!
			if ($min_posts != -1)
				detachDeletedGroupFromMembers($current_group['id_group']);
			elseif ($current_group['id_group'] != 3)
			{
				// Making it a hidden group? If so remove everyone with it as primary group (Actually, just make them additional).
				if ($our_post['group_hidden'] == 2)
					setGroupToHidden($current_group['id_group']);

				// Either way, let's check our "show group membership" setting is correct.
				validateShowGroupMembership();
			}

			// Do we need to set inherited permissions?
			if ($group_inherit != -2 && $group_inherit != $this->_req->post->old_inherit)
			{
				$permissionsObject = new Permissions;
				$permissionsObject->updateChild($group_inherit);
			}

			// Lastly, moderators!
			$moderator_string = isset($this->_req->post->group_moderators) ? trim($this->_req->post->group_moderators) : '';
			detachGroupModerators($current_group['id_group']);

			if ((!empty($moderator_string) || !empty($this->_req->post->moderator_list)) && $min_posts == -1 && $current_group['id_group'] != 3)
			{
				// Get all the usernames from the string
				if (!empty($moderator_string))
				{
					$moderator_string = strtr(preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', htmlspecialchars($moderator_string, ENT_QUOTES, 'UTF-8')), array('&quot;' => '"'));
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
					foreach ($this->_req->post->moderator_list as $moderator)
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
					assignGroupModerators($current_group['id_group'], $group_moderators);
			}

			// There might have been some post group changes.
			require_once(SUBSDIR . '/Membergroups.subs.php');
			updatePostGroupStats();

			// We've definitely changed some group stuff.
			updateSettings(array(
				'settings_updated' => time(),
			));

			// Log the edit.
			logAction('edited_group', array('group' => $our_post['group_name']), 'admin');

			redirectexit('action=admin;area=membergroups');
		}

		// Fetch the current group information.
		$row = membergroupById($current_group['id_group'], true);

		if (empty($row) || (!allowedTo('admin_forum') && $row['group_type'] == 1))
			Errors::instance()->fatal_lang_error('membergroup_does_not_exist', false);

		$row['icons'] = explode('#', $row['icons']);

		$context['group'] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'description' => htmlspecialchars($row['description'], ENT_COMPAT, 'UTF-8'),
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
			'allow_post_group' => $row['id_group'] == 2 || $row['id_group'] > 4,
			'allow_delete' => $row['id_group'] == 2 || $row['id_group'] > 4,
			'allow_protected' => allowedTo('admin_forum'),
		);

		// Get any moderators for this group
		$context['group']['moderators'] = getGroupModerators($row['id_group']);
		$context['group']['moderator_list'] = empty($context['group']['moderators']) ? '' : '&quot;' . implode('&quot;, &quot;', $context['group']['moderators']) . '&quot;';

		if (!empty($context['group']['moderators']))
			list ($context['group']['last_moderator_id']) = array_slice(array_keys($context['group']['moderators']), -1);

		// Get a list of boards this membergroup is allowed to see.
		$context['boards'] = array();
		if ($row['id_group'] == 2 || $row['id_group'] > 3)
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$context += getBoardList(array('override_permissions' => true, 'access' => $row['id_group'], 'not_redirection' => true));

			// Include a list of boards per category for easy toggling.
			foreach ($context['categories'] as $category)
				$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
		}

		// Finally, get all the groups this could be inherited off.
		$context['inheritable_groups'] = getInheritableGroups($row['id_group']);

		call_integration_hook('integrate_view_membergroup');

		$context['sub_template'] = 'edit_group';
		$context['page_title'] = $txt['membergroups_edit_group'];

		// Use the autosuggest script when needed
		if ($context['group']['id'] != 3 && $context['group']['id'] != 4)
			loadJavascriptFile('suggest.js', array('defer' => true));

		createToken('admin-mmg');
	}

	/**
	 * Set some general membergroup settings and permissions.
	 *
	 * What it does:
	 * - Called by ?action=admin;area=membergroups;sa=settings
	 * - Requires the admin_forum permission (and manage_permissions for changing permissions)
	 * - Redirects to itself.
	 *
	 * @uses membergroup_settings sub template of ManageMembergroups.
	 */
	public function action_groupSettings_display()
	{
		global $context, $scripturl, $txt;

		$context['sub_template'] = 'show_settings';
		$context['page_title'] = $txt['membergroups_settings'];

		// initialize the form
		// Instantiate the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		if (isset($this->_req->query->save))
		{
			checkSession();
			call_integration_hook('integrate_save_membergroup_settings');

			// Yeppers, saving this...
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=membergroups;sa=settings');
		}

		// Some simple context.
		$context['post_url'] = $scripturl . '?action=admin;area=membergroups;save;sa=settings';
		$context['settings_title'] = $txt['membergroups_settings'];

		$settingsForm->prepare();
	}

	/**
	 * Return the configuration settings for membergroups management.
	 */
	private function _settings()
	{
		// Only one thing here!
		$config_vars = array(
			array('permissions', 'manage_membergroups'),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_membergroup_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the form settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}
