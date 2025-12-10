<?php

/**
 * Handles xml requests
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\AdminController\CoreFeatures;
use ElkArte\BoardsTree;
use ElkArte\Cache\Cache;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\Languages\Txt;
use ElkArte\User;

/**
 * Receives XMLhttp requests of various types such as
 * jump to, message and group icons, core features, drag and drop ordering
 */
class Xml extends AbstractController
{
	/**
	 * {@inheritDoc}
	 */
	public function trackStats($action = '')
	{
		return false;
	}

	/**
	 * Main dispatcher for action=xmlhttp.
	 *
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		theme()->getTemplates()->load('Xml');
		theme()->getLayers()->removeAll();

		$subActions = [
			'jumpto' => ['controller' => $this, 'function' => 'action_jumpto'],
			'messageicons' => ['controller' => $this, 'function' => 'action_messageicons'],
			'groupicons' => ['controller' => $this, 'function' => 'action_groupicons'],
			'corefeatures' => ['controller' => $this, 'function' => 'action_corefeatures', 'permission' => 'admin_forum'],
			'profileorder' => ['controller' => $this, 'function' => 'action_profileorder', 'permission' => 'admin_forum'],
			'messageiconorder' => ['controller' => $this, 'function' => 'action_messageiconorder', 'permission' => 'admin_forum'],
			'smileyorder' => ['controller' => $this, 'function' => 'action_smileyorder', 'permission' => 'admin_forum'],
			'boardorder' => ['controller' => $this, 'function' => 'action_boardorder', 'permission' => 'manage_boards'],
			'parserorder' => ['controller' => $this, 'function' => 'action_parserorder', 'permission' => 'admin_forum'],
			'videoembed' => ['controller' => $this, 'function' => 'action_videoembed'],
		];

		// Easy adding of xml sub actions with integrate_sa_xmlhttp
		$action = new Action('xmlhttp');
		$subAction = $action->initialize($subActions);

		// Act a bit special for XML, probably never see it anyway :P
		if (empty($subAction))
		{
			throw new Exception('no_access', false);
		}

		// Off we go then, (it will check permissions)
		$action->dispatch($subAction);
	}

	/**
	 * Get a list of boards and categories used for the jumpto dropdown.
	 */
	public function action_jumpto(): void
	{
		global $context;

		// Find the boards/categories they can see.
		require_once(SUBSDIR . '/Boards.subs.php');
		$boardListOptions = [
			'selected_board' => $context['current_board'] ?? 0,
		];
		$context += getBoardList($boardListOptions);

		// Make the board safe for display.
		foreach ($context['categories'] as $id_cat => $cat)
		{
			$context['categories'][$id_cat]['name'] = un_htmlspecialchars(strip_tags($cat['name']));
			foreach ($cat['boards'] as $id_board => $board)
			{
				$context['categories'][$id_cat]['boards'][$id_board]['name'] = un_htmlspecialchars(strip_tags($board['name']));
			}
		}

		$context['sub_template'] = 'jump_to';
	}

	/**
	 * Get the message icons available for a given board
	 */
	public function action_messageicons(): void
	{
		global $context, $board;

		require_once(SUBSDIR . '/MessageIcons.subs.php');

		$context['icons'] = array_values(getMessageIcons($board));
		$context['sub_template'] = 'message_icons';
	}

	/**
	 * Get the member group icons
	 */
	public function action_groupicons(): void
	{
		global $context, $settings;

		// Only load images
		$allowedTypes = ['jpeg', 'jpg', 'gif', 'png', 'bmp'];
		$context['membergroup_icons'] = [];
		$directory = $settings['theme_dir'] . '/images/group_icons';
		$icons = [];

		// Get all the available member group icons
		$files = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);
		foreach ($files as $file)
		{
			if ($file->getFilename() === 'blank.png')
			{
				continue;
			}

			if (in_array(strtolower($file->getExtension()), $allowedTypes))
			{
				$icons[] = [
					'value' => $file->getFilename(),
					'name' => '',
					'url' => $settings['images_url'] . '/group_icons/' . $file->getFilename(),
					'is_last' => false,
				];
			}
		}

		$context['icons'] = array_values($icons);
		$context['sub_template'] = 'message_icons';
	}

	/**
	 * Turns on or off a core forum feature via ajax
	 */
	public function action_corefeatures(): void
	{
		global $context, $txt;

		$context['xml_data'] = [];

		// Just in case, maybe we don't need it
		Txt::load('Errors');
		Txt::load('Admin');

		// We need (at least) this to ensure that mod files are included
		call_integration_include_hook('integrate_admin_include');

		$errors = [];
		$returns = [];
		$tokens = [];
		$feature_title = '';

		// You have to be allowed to do this of course
		$validation = validateSession();
		if ($validation === true)
		{
			$controller = new CoreFeatures(new EventManager());
			$controller->setUser(User::$info);
			$controller->pre_dispatch();
			$result = $controller->action_index();

			// Load up the core features of the system
			if ($result === true)
			{
				$id = $this->_req->getPost('feature_id', 'trim', '');

				// The feature being enabled does exist, no messing about
				if (!empty($id) && isset($context['features'][$id]))
				{
					$feature = $context['features'][$id];
					$feature_id = 'feature_' . $id;
					$is_checked = $this->_req->hasPost($feature_id);
					$feature_title = ($is_checked && $feature['url'] ? '<a href="' . $feature['url'] . '">' . $feature['title'] . '</a>' : $feature['title']);
					$returns[] = [
						'value' => $feature_title,
					];

					createToken('admin-core', 'post');
					$tokens = [
						[
							'value' => $context['admin-core_token'],
							'attributes' => ['type' => 'token_var'],
						],
						[
							'value' => $context['admin-core_token_var'],
							'attributes' => ['type' => 'token'],
						],
					];
				}
				else
				{
					$errors[] = ['value' => $txt['feature_no_exists']];
				}
			}
			// Some problem loading in the core feature set
			else
			{
				$errors[] = ['value' => $txt[$result]];
			}
		}
		// Failed session validation I'm afraid
		else
		{
			$errors[] = ['value' => $txt[$validation] ?? $txt['error_occurred']];
		}

		// Return the response to the calling program
		$context['sub_template'] = 'generic_xml';
		theme()->addJavascriptVar(['core_settings_generic_error' => $txt['core_settings_generic_error']], true);

		$message = str_replace('{core_feature}', $feature_title, !empty($feature_id) && $this->_req->hasPost($feature_id) ? $txt['core_settings_activation_message'] : $txt['core_settings_deactivation_message']);
		$context['xml_data'] = [
			'corefeatures' => [
				'identifier' => 'corefeature',
				'children' => $returns,
			],
			'messages' => [
				'identifier' => 'message',
				'children' => [[
					'value' => $message
				]],
			],
			'tokens' => [
				'identifier' => 'token',
				'children' => $tokens,
			],
			'errors' => [
				'identifier' => 'error',
				'children' => $errors,
			],
		];
	}

	/**
	 * Reorders the custom profile fields from a drag/drop event
	 */
	public function action_profileorder(): void
	{
		global $context, $txt;

		// Start off with nothing
		$context['xml_data'] = [];
		$errors = [];
		$order = [];

		// Chances are
		Txt::load('Errors');
		Txt::load('ManageSettings');
		require_once(SUBSDIR . '/ManageFeatures.subs.php');

		// You have to be allowed to do this
		$validation_token = validateToken('admin-sort', 'post', false, false);
		$validation_session = validateSession();

		if ($validation_session === true && $validation_token === true)
		{
			// No questions that we are reordering
			if ($this->_req->getPost('order', 'trim', '') === 'reorder')
			{
				$view_order = 1;
				$replace = '';

				// The field ids arrive in 1-n view order ...
				$field_ids = (array) $this->_req->getPost('list_custom_profile_fields', null, []);
				foreach ($field_ids as $id)
				{
					$id = (int) $id;
					$replace .= '
						WHEN id_field = ' . $id . ' THEN ' . $view_order++;
				}

				// With the replace set
				if (!empty($replace))
				{
					updateProfileFieldOrder($replace);
				}
				else
				{
					$errors[] = ['value' => $txt['no_sortable_items']];
				}
			}

			$order[] = [
				'value' => $txt['custom_profile_reordered'],
			];
		}
		// Failed validation, tough to be you
		else
		{
			if ($validation_session !== true)
			{
				$errors[] = ['value' => $txt['session_verify_fail']];
			}

			if ($validation_token === false)
			{
				$errors[] = ['value' => $txt['token_verify_fail']];
			}
		}

		// New generic token for use
		createToken('admin-sort', 'post');
		$tokens = [
			[
				'value' => $context['admin-sort_token'],
				'attributes' => ['type' => 'token'],
			],
			[
				'value' => $context['admin-sort_token_var'],
				'attributes' => ['type' => 'token_var'],
			],
		];

		// Return the response
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = [
			'orders' => [
				'identifier' => 'order',
				'children' => $order,
			],
			'tokens' => [
				'identifier' => 'token',
				'children' => $tokens,
			],
			'errors' => [
				'identifier' => 'error',
				'children' => $errors,
			],
		];
	}

	/**
	 * Reorders the boards in response to an ajax sortable request
	 */
	public function action_boardorder(): void
	{
		global $context, $txt;

		// Start off clean
		$context['xml_data'] = [];
		$errors = [];
		$order = [];
		$board_tree = [];

		// Chances are we will need these
		Txt::load('Errors');
		Txt::load('ManageBoards');
		require_once(SUBSDIR . '/ManageFeatures.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');

		// Validating that you can do this is always a good idea
		$validation_token = validateToken('admin-sort', 'post', false, false);
		$validation_session = validateSession();

		if ($validation_session === true && $validation_token === true)
		{
			// No question that we are doing some board reordering
			if ($this->_req->getPost('order', 'trim', '') === 'reorder'
				&& $this->_req->hasPost('moved'))
			{
				$list_order = 0;
				$moved_key = 0;

				// What board was drag and dropped?
				$moved = $this->_req->getPost('moved', 'trim', '');
				[, $board_moved,] = explode(',', $moved);
				$board_moved = (int) $board_moved;

				// The board ids arrive in 1-n view order ...
				$cbp = (array) $this->_req->getPost('cbp', null, []);
				foreach ($cbp as $id)
				{
					[$category, $board, $childof] = explode(',', $id);

					if ($board == -1)
					{
						continue;
					}

					$board_tree[] = [
						'category' => (int) $category,
						'parent' => (int) $childof,
						'order' => $list_order,
						'id' => (int) $board,
					];

					// Keep track of where the moved board is in the sort stack
					if ($board == $board_moved)
					{
						$moved_key = $list_order;
					}

					$list_order++;
				}

				// Look behind for the previous board and previous sibling
				$board_previous = (isset($board_tree[$moved_key - 1]) && $board_tree[$moved_key]['category'] === $board_tree[$moved_key - 1]['category']) ? $board_tree[$moved_key - 1] : null;
				$board_previous_sibling = null;
				for ($i = $moved_key - 1; $i >= 0; $i--)
				{
					// Sibling must have the same category and same parent tree
					if ($board_tree[$moved_key]['category'] === $board_tree[$i]['category'])
					{
						if ($board_tree[$moved_key]['parent'] === $board_tree[$i]['parent'])
						{
							$board_previous_sibling = $board_tree[$i];
							break;
						}

						if ($board_tree[$i]['parent'] == 0)
						{
							break;
						}
						// Don't go to another parent tree
					}
					// Don't go to another category
					else
					{
						break;
					}
				}

				// Retrieve the current saved state
				$boardTree = new BoardsTree(database());
				$board_current = $boardTree->getBoardById($board_moved);
				$board_new = $board_tree[$moved_key];

				// Dropped on a sibling node, move after that
				if (isset($board_previous_sibling))
				{
					$boardOptions = [
						'move_to' => 'after',
						'target_board' => $board_previous_sibling['id'],
					];
					$order[] = ['value' => $board_current['name'] . ' ' . $txt['mboards_order_after'] . ' ' . $boardTree->getBoardById($board_previous_sibling['id'])['name']];
				}
				// No sibling, maybe a new child
				elseif (isset($board_previous))
				{
					$boardOptions = [
						'move_to' => 'child',
						'target_board' => $board_previous['id'],
						'move_first_child' => true,
					];
					$order[] = ['value' => $board_current['name'] . ' ' . $txt['mboards_order_child_of'] . ' ' . $boardTree->getBoardById($board_previous['id'])['name']];
				}
				// Nothing before this board at all, move to the top of the cat
				else
				{
					$boardOptions = [
						'move_to' => 'top',
						'target_category' => $board_new['category'],
						'move_first_child' => true,
					];
					$order[] = ['value' => $board_current['name'] . ' ' . $txt['mboards_order_in_category'] . ' ' . $boardTree->getCategoryNodeById($board_new['category'])['node']['name']];
				}

				// If we have figured out what to do
				if (!empty($boardOptions))
				{
					modifyBoard($board_moved, $boardOptions);
				}
				else
				{
					$errors[] = ['value' => $txt['mboards_board_error']];
				}
			}
		}
		// Failed validation, extra work for you I'm afraid
		else
		{
			if ($validation_session !== true)
			{
				$errors[] = ['value' => $txt['session_verify_fail']];
			}

			if ($validation_token === false)
			{
				$errors[] = ['value' => $txt['token_verify_fail']];
			}
		}

		// New generic token for use
		createToken('admin-sort', 'post');
		$tokens = [
			[
				'value' => $context['admin-sort_token'],
				'attributes' => ['type' => 'token'],
			],
			[
				'value' => $context['admin-sort_token_var'],
				'attributes' => ['type' => 'token_var'],
			],
		];

		// Return the response
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = [
			'orders' => [
				'identifier' => 'order',
				'children' => $order,
			],
			'tokens' => [
				'identifier' => 'token',
				'children' => $tokens,
			],
			'errors' => [
				'identifier' => 'error',
				'children' => $errors,
			],
		];
	}

	/**
	 * Reorders the smileys from a drag/drop event
	 *
	 * What it does:
	 *
	 * - Will move them from post to popup location and visa-versa
	 * - Will move them to new rows
	 */
	public function action_smileyorder(): void
	{
		global $context, $txt;

		// Start off with an empty response
		$context['xml_data'] = [];
		$errors = [];
		$order = [];

		Txt::load('Errors');
		Txt::load('ManageSmileys');
		require_once(SUBSDIR . '/Smileys.subs.php');

		// You have to be allowed to do this
		$validation_token = validateToken('admin-sort', 'post', false, false);
		$validation_session = validateSession();

		if ($validation_session === true && $validation_token === true)
		{
			// Valid posting
			if ($this->_req->getPost('order', 'trim', '') === 'reorder')
			{
				// Get the details on the moved smile
				$moved = $this->_req->getPost('moved', 'trim', '');
				[, $smile_moved] = explode('_', $moved);
				$smile_moved = (int) $smile_moved;
				$smile_moved_details = getSmiley($smile_moved);

				// Check if we moved rows or locations
				$smile_received_location = null;
				$smile_received_row = null;
				$received = $this->_req->getPost('received', 'trim', '');
				if ($received !== '')
				{
					$displayTypes = [
						'postform' => 0,
						'popup' => 2
					];
					[$smile_received_location, $smile_received_row] = explode('|', $received);
					$smile_received_location = $displayTypes[substr($smile_received_location, 7)];
				}

				// If these are not set, we are kind of lost :P
				if (isset($smile_received_location, $smile_received_row))
				{
					// Read the new ordering, remember where the moved smiley is in the stack
					$list_order = 0;
					$moved_key = 0;
					$smiley_tree = [];

					$smiles = (array) $this->_req->getPost('smile', null, []);
					foreach ($smiles as $smile_id)
					{
						$smiley_tree[] = (int) $smile_id;

						// Keep track of where the moved smiley is in the sort stack
						if ($smile_id == $smile_moved)
						{
							$moved_key = $list_order;
						}

						$list_order++;
					}

					// Now get the updated row, location, order
					$smiley = [];
					$smiley['row'] = $smile_received_row;
					$smiley['location'] = $smile_received_location;
					$smiley['order'] = -1;

					// If the node after the drop zone is in the same row/container, we use its position
					if (isset($smiley_tree[$moved_key + 1], $smiley_tree[$moved_key - 1]))
					{
						$possible_after = getSmiley($smiley_tree[$moved_key - 1]);
						if ($possible_after['row'] == $smiley['row'] && $possible_after['location'] == $smiley['location'])
						{
							$smiley = getSmileyPosition($smiley['location'], $smiley_tree[$moved_key - 1]);
						}
					}

					// Empty means getSmileyPosition failed and so do we
					if (!empty($smiley))
					{
						moveSmileyPosition($smiley, $smile_moved);

						// Done with the move, now we clean up across the containers/rows
						$smileys = getSmileys();
						foreach (array_keys($smileys) as $location)
						{
							foreach ($smileys[$location]['rows'] as $id => $smiley_row)
							{
								// Fix empty rows if any.
								if ($id != $smiley_row[0]['row'])
								{
									updateSmileyRow($id, $smiley_row[0]['row'], $location);

									// Only change the first row value of the first smiley.
									$smileys[$location]['rows'][$id][0]['row'] = $id;
								}

								// Make sure the smiley order is always sequential.
								foreach ($smiley_row as $order_id => $smiley)
								{
									if ($order_id != $smiley['order'])
									{
										updateSmileyOrder($smiley['id'], $order_id);
									}
								}
							}
						}

						// Clear the cache, its stale now
						Cache::instance()->remove('parsing_smileys');
						Cache::instance()->remove('posting_smileys');
						$order[] = ['value' => $txt['smileys_moved_done']];
					}
				}
			}
			else
			{
				$errors[] = ['value' => $txt['smileys_moved_fail']];
			}
		}
		// Failed validation :'(
		else
		{
			if ($validation_session !== true)
			{
				$errors[] = ['value' => $txt['session_verify_fail']];
			}

			if ($validation_token === false)
			{
				$errors[] = ['value' => $txt['token_verify_fail']];
			}
		}

		// New generic token for use
		createToken('admin-sort', 'post');
		$tokens = [
			[
				'value' => $context['admin-sort_token'],
				'attributes' => ['type' => 'token'],
			],
			[
				'value' => $context['admin-sort_token_var'],
				'attributes' => ['type' => 'token_var'],
			],
		];

		// Return the response, whatever it is
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = [
			'orders' => [
				'identifier' => 'order',
				'children' => $order,
			],
			'tokens' => [
				'identifier' => 'token',
				'children' => $tokens,
			],
			'errors' => [
				'identifier' => 'error',
				'children' => $errors,
			],
		];
	}

	/**
	 * Reorders the PBE parsers or filters from a drag/drop event
	 */
	public function action_parserorder(): void
	{
		global $context, $txt;

		// Start off with nothing
		$context['xml_data'] = [];
		$errors = [];
		$order = [];

		// Chances are
		Txt::load('Errors');
		Txt::load('Maillist');
		require_once(SUBSDIR . '/Maillist.subs.php');

		// You have to be allowed to do this
		$validation_token = validateToken('admin-sort', 'post', false, false);
		$validation_session = validateSession();

		if ($validation_session === true && $validation_token === true)
		{
			// No questions that we are reordering
			if ($this->_req->getPost('order', 'trim', '') === 'reorder')
			{
				$filters = [];
				$filter_order = 1;
				$replace = '';

				// The field ids arrive in 1-n view order ...
				$list = (array) $this->_req->getPost('list_sort_email_fp', null, []);
				foreach ($list as $id)
				{
					$filters[] = (int) $id;
					$replace .= '
						WHEN id_filter = ' . $id . ' THEN ' . $filter_order++;
				}

				// With the replace set
				if (!empty($replace))
				{
					updateParserFilterOrder($replace, $filters);
				}
				else
				{
					$errors[] = ['value' => $txt['no_sortable_items']];
				}
			}

			$order[] = [
				'value' => $txt['parser_reordered'],
			];
		}
		// Failed validation, tough to be you
		else
		{
			if ($validation_session !== true)
			{
				$errors[] = ['value' => $txt['session_verify_fail']];
			}

			if ($validation_token === false)
			{
				$errors[] = ['value' => $txt['token_verify_fail']];
			}
		}

		// New generic token for use
		createToken('admin-sort', 'post');
		$tokens = [
			[
				'value' => $context['admin-sort_token'],
				'attributes' => ['type' => 'token'],
			],
			[
				'value' => $context['admin-sort_token_var'],
				'attributes' => ['type' => 'token_var'],
			],
		];

		// Return the response
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = [
			'orders' => [
				'identifier' => 'order',
				'children' => $order,
			],
			'tokens' => [
				'identifier' => 'token',
				'children' => $tokens,
			],
			'errors' => [
				'identifier' => 'error',
				'children' => $errors,
			],
		];
	}

	/**
	 * Reorders the message icons from a drag/drop event
	 */
	public function action_messageiconorder(): void
	{
		global $context, $txt;

		// Initialize
		$context['xml_data'] = [];
		$errors = [];
		$order = [];

		// Seems these will be needed
		Txt::load('Errors');
		Txt::load('ManageSmileys');
		require_once(SUBSDIR . '/MessageIcons.subs.php');

		// You have to be allowed to do this
		$validation_token = validateToken('admin-sort', 'post', false, false);
		$validation_session = validateSession();

		if ($validation_session === true && $validation_token === true)
		{
			// No questions that we are reordering
			if ($this->_req->getPost('order', 'trim', '') === 'reorder')
			{
				// Get the current list of icons.
				$message_icons = fetchMessageIconsDetails();

				$view_order = 0;
				$iconInsert = [];

				// The field ids arrive in 1-n view order, so we simply build an update array
				$icon_list = (array) $this->_req->getPost('list_message_icon_list', null, []);
				foreach ($icon_list as $id)
				{
					$id = (int) $id;
					$iconInsert[] = [$id, $message_icons[$id]['board_id'], $message_icons[$id]['title'], $message_icons[$id]['filename'], $view_order];
					$view_order++;
				}

				// With the replace set
				if (!empty($iconInsert))
				{
					updateMessageIcon($iconInsert);
				}
				else
				{
					$errors[] = ['value' => $txt['no_sortable_items']];
				}
			}

			$order[] = [
				'value' => $txt['icons_reordered'],
			];
		}
		// Failed validation, tough to be you
		else
		{
			if ($validation_session !== true)
			{
				$errors[] = ['value' => $txt['session_verify_fail']];
			}

			if ($validation_token === false)
			{
				$errors[] = ['value' => $txt['token_verify_fail']];
			}
		}

		// New generic token for use
		createToken('admin-sort', 'post');
		$tokens = [
			[
				'value' => $context['admin-sort_token'],
				'attributes' => ['type' => 'token'],
			],
			[
				'value' => $context['admin-sort_token_var'],
				'attributes' => ['type' => 'token_var'],
			],
		];

		// Return the response
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = [
			'orders' => [
				'identifier' => 'order',
				'children' => $order,
			],
			'tokens' => [
				'identifier' => 'token',
				'children' => $tokens,
			],
			'errors' => [
				'identifier' => 'error',
				'children' => $errors,
			],
		];
	}

	/**
	 * An experimental function to fetch a videos embed code when JS will throw CORS errors
	 */
	public function action_videoembed(): void
	{
		global $context;

		theme()->getLayers()->removeAll();
		theme()->getTemplates()->load('Json');

		$context['sub_template'] = 'send_json_raw';
		$context['json_data'] = json_encode([]);

		$videoID = $this->_req->getQuery('videoid', 'trim');
		$site = $this->_req->getQuery('site', 'trim');

		if (checkSession('get', '', false))
		{
			$context['json_data'] = json_encode(['session' => 'failed']);
			$videoID = 0;
		}

		// Right now only one site, but a fetch based on site is the idea
		if (!empty($videoID) && !empty($site))
		{
			require_once(SUBSDIR . '/Package.subs.php');
			$data = fetch_web_data('https://api.x.com/1.1/statuses/oembed.json?id=' . $videoID);
			if ($data !== false)
			{
				$context['json_data'] = trim($data);
			}
		}
	}
}
