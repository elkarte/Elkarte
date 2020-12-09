<?php

/**
 * Handles xml requests
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.7
 *
 */

/**
 * Xml_Controller Class
 *
 * Receives XMLhttp requests of various types such as
 * jump to, message and group icons, core features, drag and drop ordering
 */
class Xml_Controller extends Action_Controller
{
	/**
	 * {@inheritdoc }
	 */
	public function trackStats($action = '')
	{
		return false;
	}

	/**
	 * Main dispatcher for action=xmlhttp.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		loadTemplate('Xml');

		$subActions = array(
			'jumpto' => array('controller' => $this, 'function' => 'action_jumpto'),
			'messageicons' => array('controller' => $this, 'function' => 'action_messageicons'),
			'groupicons' => array('controller' => $this, 'function' => 'action_groupicons'),
			'corefeatures' => array('controller' => $this, 'function' => 'action_corefeatures', 'permission' => 'admin_forum'),
			'profileorder' => array('controller' => $this, 'function' => 'action_profileorder', 'permission' => 'admin_forum'),
			'messageiconorder' => array('controller' => $this, 'function' => 'action_messageiconorder', 'permission' => 'admin_forum'),
			'smileyorder' => array('controller' => $this, 'function' => 'action_smileyorder', 'permission' => 'admin_forum'),
			'boardorder' => array('controller' => $this, 'function' => 'action_boardorder', 'permission' => 'manage_boards'),
			'parserorder' => array('controller' => $this, 'function' => 'action_parserorder', 'permission' => 'admin_forum'),
		);

		// Easy adding of xml sub actions with integrate_xmlhttp
		$action = new Action('xmlhttp');
		$subAction = $action->initialize($subActions);

		// Act a bit special for XML, probably never see it anyway :P
		if (empty($subAction))
			throw new Elk_Exception('no_access', false);

		// Off we go then, (it will check permissions)
		$action->dispatch($subAction);
	}

	/**
	 * Get a list of boards and categories used for the jumpto dropdown.
	 */
	public function action_jumpto()
	{
		global $context;

		// Find the boards/categories they can see.
		require_once(SUBSDIR . '/Boards.subs.php');
		$boardListOptions = array(
			'selected_board' => isset($context['current_board']) ? $context['current_board'] : 0,
		);
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
	public function action_messageicons()
	{
		global $context, $board;

		require_once(SUBSDIR . '/Editor.subs.php');

		$context['icons'] = getMessageIcons($board);
		$context['sub_template'] = 'message_icons';
	}

	/**
	 * Get the member group icons
	 */
	public function action_groupicons()
	{
		global $context, $settings;

		// Only load images
		$allowedTypes = array('jpeg', 'jpg', 'gif', 'png', 'bmp');
		$context['membergroup_icons'] = array();
		$directory = $settings['theme_dir'] . '/images/group_icons';
		$icons = array();

		// Get all the available member group icons
		$files = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
		foreach ($files as $file)
		{
			if ($file->getFilename() === 'blank.png')
				continue;

			if (in_array(strtolower($file->getExtension()), $allowedTypes))
			{
				$icons[] = array(
					'value' => $file->getFilename(),
					'name' => '',
					'url' => $settings['images_url'] . '/group_icons/' . $file->getFilename(),
					'is_last' => false,
				);
			}
		}

		$context['icons'] = array_values($icons);
		$context['sub_template'] = 'message_icons';
	}

	/**
	 * Turns on or off a core forum feature via ajax
	 */
	public function action_corefeatures()
	{
		global $context, $txt;

		$context['xml_data'] = array();

		// Just in case, maybe we don't need it
		loadLanguage('Errors');
		loadLanguage('Admin');

		// We need (at least) this to ensure that mod files are included
		call_integration_include_hook('integrate_admin_include');

		$errors = array();
		$returns = array();
		$tokens = array();
		$feature_title = '';

		// You have to be allowed to do this of course
		$validation = validateSession();
		if ($validation === true)
		{
			$controller = new CoreFeatures_Controller(new Event_Manager());
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
					$feature_title = (!empty($this->_req->post->{$feature_id}) && $feature['url'] ? '<a href="' . $feature['url'] . '">' . $feature['title'] . '</a>' : $feature['title']);
					$returns[] = array(
						'value' => $feature_title,
					);

					createToken('admin-core', 'post');
					$tokens = array(
						array(
							'value' => $context['admin-core_token'],
							'attributes' => array('type' => 'token_var'),
						),
						array(
							'value' => $context['admin-core_token_var'],
							'attributes' => array('type' => 'token'),
						),
					);
				}
				else
					$errors[] = array('value' => $txt['feature_no_exists']);
			}
			// Some problem loading in the core feature set
			else
				$errors[] = array('value' => $txt[$result]);
		}
		// Failed session validation I'm afraid
		else
			$errors[] = array('value' => isset($txt[$validation]) ? $txt[$validation] : $txt['error_occurred']);

		// Return the response to the calling program
		$context['sub_template'] = 'generic_xml';
		addJavascriptVar(array('core_settings_generic_error' => $txt['core_settings_generic_error']), true);

		$message = str_replace('{core_feature}', $feature_title, !empty($feature_id) && !empty($this->_req->post->{$feature_id}) ? $txt['core_settings_activation_message'] : $txt['core_settings_deactivation_message']);
		$context['xml_data'] = array(
			'corefeatures' => array(
				'identifier' => 'corefeature',
				'children' => $returns,
			),
			'messages' => array(
				'identifier' => 'message',
				'children' => array(array(
					'value' => $message
				)),
			),
			'tokens' => array(
				'identifier' => 'token',
				'children' => $tokens,
			),
			'errors' => array(
				'identifier' => 'error',
				'children' => $errors,
			),
		);
	}

	/**
	 * Reorders the custom profile fields from a drag/drop event
	 */
	public function action_profileorder()
	{
		global $context, $txt;

		// Start off with nothing
		$context['xml_data'] = array();
		$errors = array();
		$order = array();

		// Chances are
		loadLanguage('Errors');
		loadLanguage('ManageSettings');
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
				foreach ($this->_req->post->list_custom_profile_fields as $id)
				{
					$id = (int) $id;
					$replace .= '
						WHEN id_field = ' . $id . ' THEN ' . $view_order++;
				}

				// With the replace set
				if (!empty($replace))
					updateProfileFieldOrder($replace);
				else
					$errors[] = array('value' => $txt['no_sortable_items']);
			}

			$order[] = array(
				'value' => $txt['custom_profile_reordered'],
			);
		}
		// Failed validation, tough to be you
		else
		{
			if ($validation_session !== true)
				$errors[] = array('value' => $txt['session_verify_fail']);

			if ($validation_token === false)
				$errors[] = array('value' => $txt['token_verify_fail']);
		}

		// New generic token for use
		createToken('admin-sort', 'post');
		$tokens = array(
			array(
				'value' => $context['admin-sort_token'],
				'attributes' => array('type' => 'token'),
			),
			array(
				'value' => $context['admin-sort_token_var'],
				'attributes' => array('type' => 'token_var'),
			),
		);

		// Return the response
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = array(
			'orders' => array(
				'identifier' => 'order',
				'children' => $order,
			),
			'tokens' => array(
				'identifier' => 'token',
				'children' => $tokens,
			),
			'errors' => array(
				'identifier' => 'error',
				'children' => $errors,
			),
		);
	}

	/**
	 * Reorders the boards in response to an ajax sortable request
	 */
	public function action_boardorder()
	{
		global $context, $txt, $boards, $cat_tree;

		// Start off clean
		$context['xml_data'] = array();
		$errors = array();
		$order = array();
		$board_tree = array();
		$board_moved = null;

		// Chances are we will need these
		loadLanguage('Errors');
		loadLanguage('ManageBoards');
		require_once(SUBSDIR . '/ManageFeatures.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');

		// Validating that you can do this is always a good idea
		$validation_token = validateToken('admin-sort', 'post', false, false);
		$validation_session = validateSession();

		if ($validation_session === true && $validation_token === true)
		{
			// No question that we are doing some board reordering
			if ($this->_req->getPost('order', 'trim', '') === 'reorder' && isset($this->_req->post->moved))
			{
				$list_order = 0;
				$moved_key = 0;

				// What board was drag and dropped?
				list (, $board_moved,) = explode(',', $this->_req->post->moved);
				$board_moved = (int) $board_moved;

				// The board ids arrive in 1-n view order ...
				foreach ($this->_req->post->cbp as $id)
				{
					list ($category, $board, $childof) = explode(',', $id);

					if ($board == -1)
						continue;

					$board_tree[] = array(
						'category' => $category,
						'parent' => $childof,
						'order' => $list_order,
						'id' => $board,
					);

					// Keep track of where the moved board is in the sort stack
					if ($board == $board_moved)
						$moved_key = $list_order;

					$list_order++;
				}

				// Look behind for the previous board and previous sibling
				$board_previous = (isset($board_tree[$moved_key - 1]) && $board_tree[$moved_key]['category'] == $board_tree[$moved_key - 1]['category']) ? $board_tree[$moved_key - 1] : null;
				$board_previous_sibling = null;
				for ($i = $moved_key - 1; $i >= 0; $i--)
				{
					// Sibling must have the same category and same parent tree
					if ($board_tree[$moved_key]['category'] == $board_tree[$i]['category'])
					{
						if ($board_tree[$moved_key]['parent'] == $board_tree[$i]['parent'])
						{
							$board_previous_sibling = $board_tree[$i];
							break;
						}
						// Don't go to another parent tree
						elseif ($board_tree[$i]['parent'] == 0)
							break;
					}
					// Don't go to another category
					else
						break;
				}

				// Retrieve the current saved state, returned in global $boards
				getBoardTree();

				$boardOptions = array();
				$board_current = $boards[$board_moved];
				$board_new = $board_tree[$moved_key];

				// Dropped on a sibling node, move after that
				if (isset($board_previous_sibling))
				{
					$boardOptions = array(
						'move_to' => 'after',
						'target_board' => $board_previous_sibling['id'],
					);
					$order[] = array('value' => $board_current['name'] . ' ' . $txt['mboards_order_after'] . ' ' . $boards[$board_previous_sibling['id']]['name']);
				}
				// No sibling, maybe a new child
				elseif (isset($board_previous))
				{
					$boardOptions = array(
						'move_to' => 'child',
						'target_board' => $board_previous['id'],
						'move_first_child' => true,
					);
					$order[] = array('value' => $board_current['name'] . ' ' . $txt['mboards_order_child_of'] . ' ' . $boards[$board_previous['id']]['name']);
				}
				// Nothing before this board at all, move to the top of the cat
				elseif (!isset($board_previous))
				{
					$boardOptions = array(
						'move_to' => 'top',
						'target_category' => $board_new['category'],
					);
					$order[] = array('value' => $board_current['name'] . ' ' . $txt['mboards_order_in_category'] . ' ' . $cat_tree[$board_new['category']]['node']['name']);
				}

				// If we have figured out what to do
				if (!empty($boardOptions))
					modifyBoard($board_moved, $boardOptions);
				else
					$errors[] = array('value' => $txt['mboards_board_error']);
			}
		}
		// Failed validation, extra work for you I'm afraid
		else
		{
			if ($validation_session !== true)
				$errors[] = array('value' => $txt['session_verify_fail']);

			if ($validation_token === false)
				$errors[] = array('value' => $txt['token_verify_fail']);
		}

		// New generic token for use
		createToken('admin-sort', 'post');
		$tokens = array(
			array(
				'value' => $context['admin-sort_token'],
				'attributes' => array('type' => 'token'),
			),
			array(
				'value' => $context['admin-sort_token_var'],
				'attributes' => array('type' => 'token_var'),
			),
		);

		// Return the response
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = array(
			'orders' => array(
				'identifier' => 'order',
				'children' => $order,
			),
			'tokens' => array(
				'identifier' => 'token',
				'children' => $tokens,
			),
			'errors' => array(
				'identifier' => 'error',
				'children' => $errors,
			),
		);
	}

	/**
	 * Reorders the smileys from a drag/drop event
	 *
	 * What it does:
	 *
	 * - Will move them from post to popup location and visa-versa
	 * - Will move them to new rows
	 */
	public function action_smileyorder()
	{
		global $context, $txt;

		// Start off with an empty response
		$context['xml_data'] = array();
		$errors = array();
		$order = array();

		// Chances are I wear a silly ;D
		loadLanguage('Errors');
		loadLanguage('ManageSmileys');
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
				list (, $smile_moved) = explode('_', $this->_req->post->moved);
				$smile_moved = (int) $smile_moved;
				$smile_moved_details = getSmiley($smile_moved);

				// Check if we moved rows or locations
				$smile_received_location = null;
				$smile_received_row = null;
				if (!empty($this->_req->post->received))
				{
					$displayTypes = array(
						'postform' => 0,
						'popup' => 2
					);
					list ($smile_received_location, $smile_received_row) = explode('|', $this->_req->post->received);
					$smile_received_location = $displayTypes[substr($smile_received_location, 7)];
				}

				// If these are not set, we are kind of lost :P
				if (isset($smile_received_location, $smile_received_row))
				{
					// Read the new ordering, remember where the moved smiley is in the stack
					$list_order = 0;
					$moved_key = 0;
					$smiley_tree = array();

					foreach ($this->_req->post->smile as $smile_id)
					{
						$smiley_tree[] = $smile_id;

						// Keep track of where the moved smiley is in the sort stack
						if ($smile_id == $smile_moved)
							$moved_key = $list_order;

						$list_order++;
					}

					// Now get the updated row, location, order
					$smiley = array();
					$smiley['row'] = !isset($smile_received_row) ? $smile_moved_details['row'] : $smile_received_row;
					$smiley['location'] = !isset($smile_received_location) ? $smile_moved_details['location'] : $smile_received_location;
					$smiley['order'] = -1;

					// If the node after the drop zone is in the same row/container, we use its position
					if (isset($smiley_tree[$moved_key + 1]))
					{
						$possible_after = getSmiley($smiley_tree[$moved_key - 1]);
						if ($possible_after['row'] == $smiley['row'] && $possible_after['location'] == $smiley['location'])
							$smiley = getSmileyPosition($smiley['location'], $smiley_tree[$moved_key - 1]);
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
									if ($order_id != $smiley['order'])
										updateSmileyOrder($smiley['id'], $order_id);
							}
						}

						// Clear the cache, its stale now
						Cache::instance()->remove('parsing_smileys');
						Cache::instance()->remove('posting_smileys');
						$order[] = array('value' => $txt['smileys_moved_done']);
					}
				}
			}
			else
				$errors[] = array('value' => $txt['smileys_moved_fail']);
		}
		// Failed validation :'(
		else
		{
			if ($validation_session !== true)
				$errors[] = array('value' => $txt['session_verify_fail']);

			if ($validation_token === false)
				$errors[] = array('value' => $txt['token_verify_fail']);
		}

		// New generic token for use
		createToken('admin-sort', 'post');
		$tokens = array(
			array(
				'value' => $context['admin-sort_token'],
				'attributes' => array('type' => 'token'),
			),
			array(
				'value' => $context['admin-sort_token_var'],
				'attributes' => array('type' => 'token_var'),
			),
		);

		// Return the response, whatever it is
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = array(
			'orders' => array(
				'identifier' => 'order',
				'children' => $order,
			),
			'tokens' => array(
				'identifier' => 'token',
				'children' => $tokens,
			),
			'errors' => array(
				'identifier' => 'error',
				'children' => $errors,
			),
		);
	}

	/**
	 * Reorders the PBE parsers or filters from a drag/drop event
	 */
	public function action_parserorder()
	{
		global $context, $txt;

		// Start off with nothing
		$context['xml_data'] = array();
		$errors = array();
		$order = array();

		// Chances are
		loadLanguage('Errors');
		loadLanguage('Maillist');
		require_once(SUBSDIR . '/Maillist.subs.php');

		// You have to be allowed to do this
		$validation_token = validateToken('admin-sort', 'post', false, false);
		$validation_session = validateSession();

		if ($validation_session === true && $validation_token === true)
		{
			// No questions that we are reordering
			if (isset($this->_req->post->order, $this->_req->post->list_sort_email_fp) && $this->_req->post->order === 'reorder')
			{
				$filters = array();
				$filter_order = 1;
				$replace = '';

				// The field ids arrive in 1-n view order ...
				foreach ($this->_req->post->list_sort_email_fp as $id)
				{
					$filters[] = (int) $id;
					$replace .= '
						WHEN id_filter = ' . $id . ' THEN ' . $filter_order++;
				}

				// With the replace set
				if (!empty($replace))
					updateParserFilterOrder($replace, $filters);
				else
					$errors[] = array('value' => $txt['no_sortable_items']);
			}

			$order[] = array(
				'value' => $txt['parser_reordered'],
			);
		}
		// Failed validation, tough to be you
		else
		{
			if ($validation_session !== true)
				$errors[] = array('value' => $txt['session_verify_fail']);

			if ($validation_token === false)
				$errors[] = array('value' => $txt['token_verify_fail']);
		}

		// New generic token for use
		createToken('admin-sort', 'post');
		$tokens = array(
			array(
				'value' => $context['admin-sort_token'],
				'attributes' => array('type' => 'token'),
			),
			array(
				'value' => $context['admin-sort_token_var'],
				'attributes' => array('type' => 'token_var'),
			),
		);

		// Return the response
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = array(
			'orders' => array(
				'identifier' => 'order',
				'children' => $order,
			),
			'tokens' => array(
				'identifier' => 'token',
				'children' => $tokens,
			),
			'errors' => array(
				'identifier' => 'error',
				'children' => $errors,
			),
		);
	}

	/**
	 * Reorders the message icons from a drag/drop event
	 */
	public function action_messageiconorder()
	{
		global $context, $txt;

		// Initialize
		$context['xml_data'] = array();
		$errors = array();
		$order = array();

		// Seems these will be needed
		loadLanguage('Errors');
		loadLanguage('ManageSmileys');
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
				$iconInsert = array();

				// The field ids arrive in 1-n view order, so we simply build an update array
				foreach ($this->_req->post->list_message_icon_list as $id)
				{
						$iconInsert[] = array($id, $message_icons[$id]['board_id'], $message_icons[$id]['title'], $message_icons[$id]['filename'], $view_order);
						$view_order++;
				}

 				// With the replace set
				if (!empty($iconInsert))
				{
					updateMessageIcon($iconInsert);
					sortMessageIconTable();
				}
				else
					$errors[] = array('value' => $txt['no_sortable_items']);
			}

			$order[] = array(
				'value' => $txt['icons_reordered'],
			);
		}
		// Failed validation, tough to be you
		else
		{
			if ($validation_session !== true)
				$errors[] = array('value' => $txt['session_verify_fail']);

			if ($validation_token === false)
				$errors[] = array('value' => $txt['token_verify_fail']);
		}

		// New generic token for use
		createToken('admin-sort', 'post');
		$tokens = array(
			array(
				'value' => $context['admin-sort_token'],
				'attributes' => array('type' => 'token'),
			),
			array(
				'value' => $context['admin-sort_token_var'],
				'attributes' => array('type' => 'token_var'),
			),
		);

		// Return the response
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = array(
			'orders' => array(
				'identifier' => 'order',
				'children' => $order,
			),
			'tokens' => array(
				'identifier' => 'token',
				'children' => $tokens,
			),
			'errors' => array(
				'identifier' => 'error',
				'children' => $errors,
			),
		);
	}
}
