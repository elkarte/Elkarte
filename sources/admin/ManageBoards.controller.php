<?php

/**
 * Manage and maintain the boards and categories of the forum.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

/**
 * This class controls execution for actions in the manage boards area
 * of the admin panel.
 *
 * @package Boards
 */
class ManageBoards_Controller extends Action_Controller
{
	/**
	 * Category being worked on
	 * @var int
	 */
	public $cat;

	/**
	 * Current board id being modified
	 * @var int
	 */
	public $boardid;

	/**
	 * The main dispatcher; delegates.
	 *
	 * What it does:
	 *
	 * - This is the main entry point for all the manageboards admin screens.
	 * - Called by ?action=admin;area=manageboards.
	 * - It checks the permissions, based on the sub-action, and calls a function based on the sub-action.
	 *
	 * @uses ManageBoards language file.
	 */
	public function action_index()
	{
		global $context, $txt;

		// Everything's gonna need this.
		theme()->getTemplates()->loadLanguageFile('ManageBoards');

		// Format: 'sub-action' => array('controller', 'function', 'permission'=>'need')
		$subActions = array(
			'board' => array(
				'controller' => $this,
				'function' => 'action_board',
				'permission' => 'manage_boards'),
			'board2' => array(
				'controller' => $this,
				'function' => 'action_board2',
				'permission' => 'manage_boards'),
			'cat' => array(
				'controller' => $this,
				'function' => 'action_cat',
				'permission' => 'manage_boards'),
			'cat2' => array(
				'controller' => $this,
				'function' => 'action_cat2',
				'permission' => 'manage_boards'),
			'main' => array(
				'controller' => $this,
				'function' => 'action_main',
				'permission' => 'manage_boards'),
			'move' => array(
				'controller' => $this,
				'function' => 'action_main',
				'permission' => 'manage_boards'),
			'newcat' => array(
				'controller' => $this,
				'function' => 'action_cat',
				'permission' => 'manage_boards'),
			'newboard' => array(
				'controller' => $this,
				'function' => 'action_board',
				'permission' => 'manage_boards'),
			'settings' => array(
				'controller' => $this,
				'function' => 'action_boardSettings_display',
				'permission' => 'admin_forum'),
		);

		// Create the tabs for the template.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['boards_and_cats'],
			'help' => 'manage_boards',
			'description' => $txt['boards_and_cats_desc'],
			'tabs' => array(
				'main' => array(
				),
				'newcat' => array(
				),
				'settings' => array(
					'description' => $txt['mboards_settings_desc'],
				),
			),
		);

		// You way will end here if you don't have permission.
		$action = new Action('manage_boards');

		// Default to sub-action 'main' or 'settings' depending on permissions.
		$subAction = $action->initialize($subActions, allowedTo('manage_boards') ? 'main' : 'settings');
		$context['sub_action'] = $subAction;

		$action->dispatch($subAction);
	}

	/**
	 * The main control panel thing, the screen showing all boards and categories.
	 *
	 * What it does:
	 *
	 * - Called by ?action=admin;area=manageboards or ?action=admin;area=manageboards;sa=move.
	 * - Requires manage_boards permission.
	 * - It also handles the interface for moving boards.
	 *
	 * @event integrate_boards_main Used to access global board arrays before the template
	 * @uses ManageBoards template, main sub-template.
	 */
	public function action_main()
	{
		global $txt, $context, $cat_tree, $boards, $boardList, $scripturl;

		theme()->getTemplates()->load('ManageBoards');

		require_once(SUBSDIR . '/Boards.subs.php');

		// Moving a board, child of, before, after, top
		if (isset($this->_req->query->sa) && $this->_req->query->sa == 'move' && in_array($this->_req->query->move_to, array('child', 'before', 'after', 'top')))
		{
			checkSession('get');
			validateToken('admin-bm-' . (int) $this->_req->query->src_board, 'request');

			// Top is special, its the top!
			if ($this->_req->query->move_to === 'top')
				$boardOptions = array(
					'move_to' => $this->_req->query->move_to,
					'target_category' => (int) $this->_req->query->target_cat,
					'move_first_child' => true,
				);
			// Moving it after another board
			else
				$boardOptions = array(
					'move_to' => $this->_req->query->move_to,
					'target_board' => (int) $this->_req->query->target_board,
					'move_first_child' => true,
				);

			// Use modifyBoard to perform the action
			modifyBoard((int) $this->_req->query->src_board, $boardOptions);
		}

		getBoardTree();

		createToken('admin-sort');
		$context['move_board'] = !empty($this->_req->query->move) && isset($boards[(int) $this->_req->query->move]) ? (int) $this->_req->query->move : 0;

		$bbc_parser = \BBC\ParserWrapper::instance();

		$context['categories'] = array();
		foreach ($cat_tree as $catid => $tree)
		{
			$context['categories'][$catid] = array(
				'name' => &$tree['node']['name'],
				'id' => &$tree['node']['id'],
				'boards' => array()
			);
			$move_cat = !empty($context['move_board']) && $boards[$context['move_board']]['category'] == $catid;
			foreach ($boardList[$catid] as $boardid)
			{
				$boards[$boardid]['description'] = $bbc_parser->parseBoard($boards[$boardid]['description']);
				$context['categories'][$catid]['boards'][$boardid] = array(
					'id' => &$boards[$boardid]['id'],
					'name' => &$boards[$boardid]['name'],
					'description' => &$boards[$boardid]['description'],
					'child_level' => &$boards[$boardid]['level'],
					'move' => $move_cat && ($boardid == $context['move_board'] || isChildOf($boardid, (int) $context['move_board'])),
					'permission_profile' => &$boards[$boardid]['profile'],
				);
			}
		}

		if (!empty($context['move_board']))
		{
			createToken('admin-bm-' . $context['move_board'], 'request');

			$context['move_title'] = sprintf($txt['mboards_select_destination'], htmlspecialchars($boards[$context['move_board']]['name'], ENT_COMPAT, 'UTF-8'));
			foreach ($cat_tree as $catid => $tree)
			{
				$prev_child_level = 0;
				$prev_board = 0;
				$stack = array();

				// Just a shortcut, this is the same for all the urls
				$security = $context['session_var'] . '=' . $context['session_id'] . ';' . $context['admin-bm-' . $context['move_board'] . '_token_var'] . '=' . $context['admin-bm-' . $context['move_board'] . '_token'];
				foreach ($boardList[$catid] as $boardid)
				{
					if (!isset($context['categories'][$catid]['move_link']))
						$context['categories'][$catid]['move_link'] = array(
							'child_level' => 0,
							'label' => $txt['mboards_order_before'] . ' \'' . htmlspecialchars($boards[$boardid]['name'], ENT_COMPAT, 'UTF-8') . '\'',
							'href' => $scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_board=' . $boardid . ';move_to=before;' . $security,
						);

					if (!$context['categories'][$catid]['boards'][$boardid]['move'])
					$context['categories'][$catid]['boards'][$boardid]['move_links'] = array(
						array(
							'child_level' => $boards[$boardid]['level'],
							'label' => $txt['mboards_order_after'] . '\'' . htmlspecialchars($boards[$boardid]['name'], ENT_COMPAT, 'UTF-8') . '\'',
							'href' => $scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_board=' . $boardid . ';move_to=after;' . $security,
						),
						array(
							'child_level' => $boards[$boardid]['level'] + 1,
							'label' => $txt['mboards_order_child_of'] . ' \'' . htmlspecialchars($boards[$boardid]['name'], ENT_COMPAT, 'UTF-8') . '\'',
							'href' => $scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_board=' . $boardid . ';move_to=child;' . $security,
						),
					);

					$difference = $boards[$boardid]['level'] - $prev_child_level;
					if ($difference == 1)
						array_push($stack, !empty($context['categories'][$catid]['boards'][$prev_board]['move_links']) ? array_shift($context['categories'][$catid]['boards'][$prev_board]['move_links']) : null);
					elseif ($difference < 0)
					{
						if (empty($context['categories'][$catid]['boards'][$prev_board]['move_links']))
							$context['categories'][$catid]['boards'][$prev_board]['move_links'] = array();

						for ($i = 0; $i < -$difference; $i++)
							if (($temp = array_pop($stack)) !== null)
								array_unshift($context['categories'][$catid]['boards'][$prev_board]['move_links'], $temp);
					}

					$prev_board = $boardid;
					$prev_child_level = $boards[$boardid]['level'];

				}

				if (!empty($stack) && !empty($context['categories'][$catid]['boards'][$prev_board]['move_links']))
					$context['categories'][$catid]['boards'][$prev_board]['move_links'] = array_merge($stack, $context['categories'][$catid]['boards'][$prev_board]['move_links']);
				elseif (!empty($stack))
					$context['categories'][$catid]['boards'][$prev_board]['move_links'] = $stack;

				if (empty($boardList[$catid]))
					$context['categories'][$catid]['move_link'] = array(
						'child_level' => 0,
						'label' => $txt['mboards_order_before'] . ' \'' . htmlspecialchars($tree['node']['name'], ENT_COMPAT, 'UTF-8') . '\'',
						'href' => $scripturl . '?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_cat=' . $catid . ';move_to=top;' . $security,
					);
			}
		}

		call_integration_hook('integrate_boards_main');

		$context['page_title'] = $txt['boards_and_cats'];
		$context['sub_template'] = 'manage_boards';
		$context['can_manage_permissions'] = allowedTo('manage_permissions');
	}

	/**
	 * Modify a specific category.
	 *
	 * What it does:
	 *
	 * - screen for editing and repositioning a category.
	 * - Also used to show the confirm deletion of category screen
	 * - Called by ?action=admin;area=manageboards;sa=cat
	 * - Requires manage_boards permission.
	 *
	 * @event integrate_edit_category access category globals before the template
	 * @uses ManageBoards template, modify_category sub-template.
	 */
	public function action_cat()
	{
		global $txt, $context, $cat_tree, $boardList, $boards;

		theme()->getTemplates()->load('ManageBoards');
		require_once(SUBSDIR . '/Boards.subs.php');
		getBoardTree();

		// id_cat must be a number.... if it exists.
		$this->cat = $this->_req->getQuery('cat', 'intval', 0);

		// Start with one - "In first place".
		$context['category_order'] = array(
			array(
				'id' => 0,
				'name' => $txt['mboards_order_first'],
				'selected' => !empty($this->cat) ? $cat_tree[$this->cat]['is_first'] : false,
				'true_name' => ''
			)
		);

		// If this is a new category set up some defaults.
		if ($this->_req->query->sa == 'newcat')
		{
			$context['category'] = array(
				'id' => 0,
				'name' => $txt['mboards_new_cat_name'],
				'editable_name' => htmlspecialchars($txt['mboards_new_cat_name'], ENT_COMPAT, 'UTF-8'),
				'can_collapse' => true,
				'is_new' => true,
				'is_empty' => true
			);
		}
		// Category doesn't exist, man... sorry.
		elseif (!isset($cat_tree[$this->cat]))
			redirectexit('action=admin;area=manageboards');
		else
		{
			$context['category'] = array(
				'id' => $this->cat,
				'name' => $cat_tree[$this->cat]['node']['name'],
				'editable_name' => htmlspecialchars($cat_tree[$this->cat]['node']['name'], ENT_COMPAT, 'UTF-8'),
				'can_collapse' => !empty($cat_tree[$this->cat]['node']['can_collapse']),
				'children' => array(),
				'is_empty' => empty($cat_tree[$this->cat]['children'])
			);

			foreach ($boardList[$this->cat] as $child_board)
				$context['category']['children'][] = str_repeat('-', $boards[$child_board]['level']) . ' ' . $boards[$child_board]['name'];
		}

		$prevCat = 0;
		foreach ($cat_tree as $catid => $tree)
		{
			if ($catid == $this->cat && $prevCat > 0)
				$context['category_order'][$prevCat]['selected'] = true;
			elseif ($catid != $this->cat)
				$context['category_order'][$catid] = array(
					'id' => $catid,
					'name' => $txt['mboards_order_after'] . $tree['node']['name'],
					'selected' => false,
					'true_name' => $tree['node']['name']
				);

			$prevCat = $catid;
		}

		if (!isset($this->_req->query->delete))
		{
			$context['sub_template'] = 'modify_category';
			$context['page_title'] = $this->_req->query->sa == 'newcat' ? $txt['mboards_new_cat_name'] : $txt['catEdit'];
		}
		else
		{
			$context['sub_template'] = 'confirm_category_delete';
			$context['page_title'] = $txt['mboards_delete_cat'];
		}

		// Create a special token.
		createToken('admin-bc-' . $this->cat);
		$context['token_check'] = 'admin-bc-' . $this->cat;

		call_integration_hook('integrate_edit_category');
	}

	/**
	 * Function for handling a submitted form saving the category.
	 *
	 * What it does:
	 *
	 * - complete the modifications to a specific category.
	 * - It also handles deletion of a category.
	 * - It requires manage_boards permission.
	 * - Called by ?action=admin;area=manageboards;sa=cat2
	 * - Redirects to ?action=admin;area=manageboards.
	 */
	public function action_cat2()
	{
		checkSession();
		validateToken('admin-bc-' . $this->_req->post->cat);

		require_once(SUBSDIR . '/Categories.subs.php');

		$this->cat = (int) $this->_req->post->cat;

		// Add a new category or modify an existing one..
		if (isset($this->_req->post->edit) || isset($this->_req->post->add))
		{
			$catOptions = array();

			if (isset($this->_req->post->cat_order))
				$catOptions['move_after'] = (int) $this->_req->post->cat_order;

			// Change "This & That" to "This &amp; That" but don't change "&cent" to "&amp;cent;"...
			$catOptions['cat_name'] = preg_replace('~[&]([^;]{8}|[^;]{0,8}$)~', '&amp;$1', $this->_req->post->cat_name);

			$catOptions['is_collapsible'] = isset($this->_req->post->collapse);

			if (isset($this->_req->post->add))
				createCategory($catOptions);
			else
				modifyCategory($this->cat, $catOptions);
		}
		// If they want to delete - first give them confirmation.
		elseif (isset($this->_req->post->delete) && !isset($this->_req->post->confirmation) && !isset($this->_req->post->empty))
		{
			$this->action_cat();
			return;
		}
		// Delete the category!
		elseif (isset($this->_req->post->delete))
		{
			// First off - check if we are moving all the current boards first - before we start deleting!
			if (isset($this->_req->post->delete_action) && $this->_req->post->delete_action == 1)
			{
				if (empty($this->_req->post->cat_to))
					throw new Elk_Exception('mboards_delete_error');

				deleteCategories(array($this->cat), (int) $this->_req->post->cat_to);
			}
			else
				deleteCategories(array($this->cat));
		}

		redirectexit('action=admin;area=manageboards');
	}

	/**
	 * Modify a specific board...
	 *
	 * What it does
	 * - screen for editing and repositioning a board.
	 * - called by ?action=admin;area=manageboards;sa=board
	 * - also used to show the confirm deletion of category screen (sub-template confirm_board_delete).
	 * - requires manage_boards permission.
	 *
	 * @event integrate_edit_board
	 * @uses the modify_board sub-template of the ManageBoards template.
	 * @uses ManagePermissions language
	 */
	public function action_board()
	{
		global $txt, $context, $cat_tree, $boards, $boardList, $modSettings;

		theme()->getTemplates()->load('ManageBoards');
		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');
		getBoardTree();

		// For editing the profile we'll need this.
		theme()->getTemplates()->loadLanguageFile('ManagePermissions');
		require_once(SUBSDIR . '/ManagePermissions.subs.php');
		loadPermissionProfiles();

		// id_board must be a number....
		$this->boardid = $this->_req->getQuery('boardid', 'intval', 0);
		if (!isset($boards[$this->boardid]))
		{
			$this->boardid = 0;
			$this->_req->query->sa = 'newboard';
		}

		if ($this->_req->query->sa == 'newboard')
		{
			$this->cat = $this->_req->getQuery('cat', 'intval', 0);

			// Category doesn't exist, man... sorry.
			if (empty($this->cat))
				redirectexit('action=admin;area=manageboards');

			// Some things that need to be setup for a new board.
			$curBoard = array(
				'member_groups' => array(0, -1),
				'deny_groups' => array(),
				'category' => $this->cat
			);
			$context['board_order'] = array();
			$context['board'] = array(
				'is_new' => true,
				'id' => 0,
				'name' => $txt['mboards_new_board_name'],
				'description' => '',
				'count_posts' => 1,
				'posts' => 0,
				'topics' => 0,
				'theme' => 0,
				'profile' => 1,
				'override_theme' => 0,
				'redirect' => '',
				'category' => $this->cat,
				'no_children' => true,
			);
		}
		else
		{
			// Just some easy shortcuts.
			$curBoard = &$boards[$this->boardid];
			$context['board'] = $boards[$this->boardid];
			$context['board']['name'] = htmlspecialchars(strtr($context['board']['name'], array('&amp;' => '&')), ENT_COMPAT, 'UTF-8');
			$context['board']['description'] = un_preparsecode($context['board']['description']);
			$context['board']['no_children'] = empty($boards[$this->boardid]['tree']['children']);
			$context['board']['is_recycle'] = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) && $modSettings['recycle_board'] == $context['board']['id'];
		}

		// As we may have come from the permissions screen keep track of where we should go on save.
		$context['redirect_location'] = isset($this->_req->query->rid) && $this->_req->query->rid == 'permissions' ? 'permissions' : 'boards';

		// We might need this to hide links to certain areas.
		$context['can_manage_permissions'] = allowedTo('manage_permissions');

		// Default membergroups.
		$context['groups'] = array(
			-1 => array(
				'id' => '-1',
				'name' => $txt['parent_guests_only'],
				'allow' => in_array('-1', $curBoard['member_groups']),
				'deny' => in_array('-1', $curBoard['deny_groups']),
				'is_post_group' => false,
			),
			0 => array(
				'id' => '0',
				'name' => $txt['parent_members_only'],
				'allow' => in_array('0', $curBoard['member_groups']),
				'deny' => in_array('0', $curBoard['deny_groups']),
				'is_post_group' => false,
			)
		);

		$context['groups'] += getOtherGroups($curBoard, $this->_req->query->sa == 'newboard');

		// Category doesn't exist, man... sorry.
		if (!isset($boardList[$curBoard['category']]))
			redirectexit('action=admin;area=manageboards');

		foreach ($boardList[$curBoard['category']] as $boardid)
		{
			if ($boardid == $this->boardid)
			{
				$context['board_order'][] = array(
					'id' => $boardid,
					'name' => str_repeat('-', $boards[$boardid]['level']) . ' (' . $txt['mboards_current_position'] . ')',
					'children' => $boards[$boardid]['tree']['children'],
					'no_children' => empty($boards[$boardid]['tree']['children']),
					'is_child' => false,
					'selected' => true
				);
			}
			else
			{
				$context['board_order'][] = array(
					'id' => $boardid,
					'name' => str_repeat('-', $boards[$boardid]['level']) . ' ' . $boards[$boardid]['name'],
					'is_child' => empty($this->boardid) ? false : isChildOf($boardid, $this->boardid),
					'selected' => false
				);
			}
		}

		// Are there any places to move sub-boards to in the case where we are confirming a delete?
		if (!empty($this->boardid))
		{
			$context['can_move_children'] = false;
			$context['children'] = $boards[$this->boardid]['tree']['children'];
			foreach ($context['board_order'] as $board)
				if ($board['is_child'] === false && $board['selected'] === false)
					$context['can_move_children'] = true;
		}

		// Get other available categories.
		$context['categories'] = array();
		foreach ($cat_tree as $catID => $tree)
			$context['categories'][] = array(
				'id' => $catID == $curBoard['category'] ? 0 : $catID,
				'name' => $tree['node']['name'],
				'selected' => $catID == $curBoard['category']
			);

		$context['board']['moderators'] = getBoardModerators($this->boardid);
		$context['board']['moderator_list'] = empty($context['board']['moderators']) ? '' : '&quot;' . implode('&quot;, &quot;', $context['board']['moderators']) . '&quot;';

		if (!empty($context['board']['moderators']))
			list ($context['board']['last_moderator_id']) = array_slice(array_keys($context['board']['moderators']), -1);

		$context['themes'] = getAllThemes();

		if (!isset($this->_req->query->delete))
		{
			$context['sub_template'] = 'modify_board';
			$context['page_title'] = $txt['boardsEdit'];
			loadJavascriptFile('suggest.js', array('defer' => true));
		}
		else
		{
			$context['sub_template'] = 'confirm_board_delete';
			$context['page_title'] = $txt['mboards_delete_board'];
		}

		// Create a special token.
		createToken('admin-be-' . $this->boardid);

		call_integration_hook('integrate_edit_board');
	}

	/**
	 * Make changes to/delete a board.
	 *
	 * What it does:
	 *
	 * - function for handling a submitted form saving the board.
	 * - It also handles deletion of a board.
	 * - Called by ?action=admin;area=manageboards;sa=board2
	 * - Redirects to ?action=admin;area=manageboards.
	 * - It requires manage_boards permission.
	 *
	 * @event integrate_save_board
	 */
	public function action_board2()
	{
		global $context;

		$board_id = $this->_req->getPost('boardid', 'intval', 0);
		checkSession();
		validateToken('admin-be-' . $this->_req->post->boardid);

		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		$posts = getBoardProperties($this->_req->post->boardid)['numPosts'];

		// Mode: modify aka. don't delete.
		if (isset($this->_req->post->edit) || isset($this->_req->post->add))
		{
			$boardOptions = array();

			// Move this board to a new category?
			if (!empty($this->_req->post->new_cat))
			{
				$boardOptions['move_to'] = 'bottom';
				$boardOptions['target_category'] = (int) $this->_req->post->new_cat;
			}
			// Change the boardorder of this board?
			elseif (!empty($this->_req->post->placement) && !empty($this->_req->post->board_order))
			{
				if (!in_array($this->_req->post->placement, array('before', 'after', 'child')))
					throw new Elk_Exception('mangled_post', false);

				$boardOptions['move_to'] = $this->_req->post->placement;
				$boardOptions['target_board'] = (int) $this->_req->post->board_order;
			}

			// Checkboxes....
			$boardOptions['posts_count'] = isset($this->_req->post->count);
			$boardOptions['override_theme'] = isset($this->_req->post->override_theme);
			$boardOptions['board_theme'] = (int) $this->_req->post->boardtheme;
			$boardOptions['access_groups'] = array();
			$boardOptions['deny_groups'] = array();

			if (!empty($this->_req->post->groups))
			{
				foreach ($this->_req->post->groups as $group => $action)
				{
					if ($action == 'allow')
						$boardOptions['access_groups'][] = (int) $group;
					elseif ($action == 'deny')
						$boardOptions['deny_groups'][] = (int) $group;
				}
			}

			if (strlen(implode(',', $boardOptions['access_groups'])) > 255 || strlen(implode(',', $boardOptions['deny_groups'])) > 255)
				throw new Elk_Exception('too_many_groups', false);

			// Change '1 & 2' to '1 &amp; 2', but not '&amp;' to '&amp;amp;'...
			$boardOptions['board_name'] = preg_replace('~[&]([^;]{8}|[^;]{0,8}$)~', '&amp;$1', $this->_req->post->board_name);

			$boardOptions['board_description'] = Util::htmlspecialchars($this->_req->post->desc);
			preparsecode($boardOptions['board_description']);

			$boardOptions['moderator_string'] = $this->_req->post->moderators;

			if (isset($this->_req->post->moderator_list) && is_array($this->_req->post->moderator_list))
			{
				$moderators = array();
				foreach ($this->_req->post->moderator_list as $moderator)
					$moderators[(int) $moderator] = (int) $moderator;

				$boardOptions['moderators'] = $moderators;
			}

			// Are they doing redirection?
			$boardOptions['redirect'] = !empty($this->_req->post->redirect_enable) && isset($this->_req->post->redirect_address) && trim($this->_req->post->redirect_address) != '' ? trim($this->_req->post->redirect_address) : '';

			// Profiles...
			$boardOptions['profile'] = $this->_req->post->profile;
			$boardOptions['inherit_permissions'] = $this->_req->post->profile == -1;

			// We need to know what used to be case in terms of redirection.
			if (!empty($board_id))
			{
				$properties = getBoardProperties($board_id);

				// If we're turning redirection on check the board doesn't have posts in it - if it does don't make it a redirection board.
				if ($boardOptions['redirect'] && empty($properties['oldRedirect']) && $properties['numPosts'])
					unset($boardOptions['redirect']);
				// Reset the redirection count when switching on/off.
				elseif (empty($boardOptions['redirect']) != empty($properties['oldRedirect']))
					$boardOptions['num_posts'] = 0;
				// Resetting the count?
				elseif ($boardOptions['redirect'] && !empty($this->_req->post->reset_redirect))
					$boardOptions['num_posts'] = 0;
			}

			call_integration_hook('integrate_save_board', array($board_id, &$boardOptions));

			// Create a new board...
			if (isset($this->_req->post->add))
			{
				// New boards by default go to the bottom of the category.
				if (empty($this->_req->post->new_cat))
					$boardOptions['target_category'] = (int) $this->_req->post->cur_cat;
				if (!isset($boardOptions['move_to']))
					$boardOptions['move_to'] = 'bottom';

				createBoard($boardOptions);
			}
			// ...or update an existing board.
			else
				modifyBoard($board_id, $boardOptions);
		}
		elseif (isset($this->_req->post->delete) && !isset($this->_req->post->confirmation) && !isset($this->_req->post->no_children))
		{
			if ($posts) {
				throw new Elk_Exception('mboards_delete_board_has_posts');
			}
			else {
				$this->action_board();
			}
			return;
		}
		elseif (isset($this->_req->post->delete))
		{
			// First, check if our board still has posts or topics.
			if ($posts) {
				throw new Elk_Exception('mboards_delete_board_has_posts');
			}
			else if (isset($this->_req->post->delete_action) && $this->_req->post->delete_action == 1)
			{
				// Check if we are moving all the current sub-boards first - before we start deleting!
				if (empty($this->_req->post->board_to))
					throw new Elk_Exception('mboards_delete_board_error');

				deleteBoards(array($board_id), (int) $this->_req->post->board_to);
			}
			else
				deleteBoards(array($board_id), 0);
		}

		if (isset($this->_req->query->rid) && $this->_req->query->rid == 'permissions')
			redirectexit('action=admin;area=permissions;sa=board;' . $context['session_var'] . '=' . $context['session_id']);
		else
			redirectexit('action=admin;area=manageboards');
	}

	/**
	 * A screen to display and allow to set a few general board and category settings.
	 *
	 * @event integrate_save_board_settings called during manage board settings
	 * @uses modify_general_settings sub-template.
	 */
	public function action_boardSettings_display()
	{
		global $context, $txt, $scripturl;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Add some javascript stuff for the recycle box.
		theme()-addInlineJavascript('
				document.getElementById("recycle_board").disabled = !document.getElementById("recycle_enable").checked;', true);

		// Get the needed template bits
		theme()->getTemplates()->load('ManageBoards');
		$context['page_title'] = $txt['boards_and_cats'] . ' - ' . $txt['settings'];
		$context['sub_template'] = 'show_settings';
		$context['post_url'] = $scripturl . '?action=admin;area=manageboards;save;sa=settings';

		// Warn the admin against selecting the recycle topic without selecting a board.
		$context['force_form_onsubmit'] = 'if(document.getElementById(\'recycle_enable\').checked && document.getElementById(\'recycle_board\').value == 0) { return confirm(\'' . $txt['recycle_board_unselected_notice'] . '\');} return true;';

		// Doing a save?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_board_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=manageboards;sa=settings');
		}

		// Prepare the settings...
		$settingsForm->prepare();
	}

	/**
	 * Retrieve and return all admin settings for boards management.
	 *
	 * @event integrate_modify_board_settings add config settings to boards management
	 */
	private function _settings()
	{
		global $txt;

		// We need to borrow a string from here
		theme()->getTemplates()->loadLanguageFile('ManagePermissions');

		// Load the boards list - for the recycle bin!
		require_once(SUBSDIR . '/Boards.subs.php');
		$boards = getBoardList(array('override_permissions' => true, 'not_redirection' => true), true);
		$recycle_boards = array('');
		foreach ($boards as $board)
			$recycle_boards[$board['id_board']] = $board['cat_name'] . ' - ' . $board['board_name'];

		// Here and the board settings...
		$config_vars = array(
			array('title', 'settings'),
				// Inline permissions.
				array('permissions', 'manage_boards', 'helptext' => $txt['permissionhelp_manage_boards']),
			'',
				// Other board settings.
				array('check', 'countChildPosts'),
				array('check', 'recycle_enable', 'onclick' => 'document.getElementById(\'recycle_board\').disabled = !this.checked;'),
				array('select', 'recycle_board', $recycle_boards),
				array('check', 'allow_ignore_boards'),
				array('check', 'deny_boards_access'),
		);

		// Add new settings with a nice hook, makes them available for admin settings search as well
		call_integration_hook('integrate_modify_board_settings', array(&$config_vars));

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
