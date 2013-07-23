<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Xml controller receives XMLhttp requests of various types.
 * (jump to, message and group icons, core features)
 */
class Xml_Controller extends Action_Controller
{
	/**
	 * Main dispatcher for action=xmlhttp.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		loadTemplate('Xml');

		$subActions = array(
			'jumpto' => array('action_jumpto'),
			'messageicons' => array('action_messageicons'),
			'groupicons' => array('action_groupicons'),
			'corefeatures' => array('action_corefeatures', 'admin_forum'),
		);

		// Easy adding of xml sub actions
	 	call_integration_hook('integrate_xmlhttp', array(&$subActions));

		// Valid action?
		if (!isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
			fatal_lang_error('no_access', false);

		// Permissions check in the subAction?
		if (isset($subActions[$_REQUEST['sa']][1]))
			isAllowedTo($subActions[$_REQUEST['sa']][1]);

		// Off we go then
		$this->{$subActions[$_REQUEST['sa']][0]}();
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
			'use_permissions' => true,
			'selected_board' => isset($context['current_board']) ? $context['current_board'] : 0,
		);
		$context += getBoardList($boardListOptions);

		// Make the board safe for display.
		foreach ($context['categories'] as $id_cat => $cat)
		{
			$context['categories'][$id_cat]['name'] = un_htmlspecialchars(strip_tags($cat['name']));
			foreach ($cat['boards'] as $id_board => $board)
				$context['categories'][$id_cat]['boards'][$id_board]['name'] = un_htmlspecialchars(strip_tags($board['name']));
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

		// Get all the available member group icons
		$files = scandir($directory);
		foreach ($files as $id => $file)
		{
			if ($file === 'blank.png')
				continue;

			if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $allowedTypes))
			{
				$icons[$id] = array(
					'value' => $file,
					'name' => '',
					'url' => $settings['images_url'] . '/group_icons/' .  $file,
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
		global $context, $modSettings, $txt, $settings;

		$context['xml_data'] = array();

		// Just in case, maybe we don't need it
		loadLanguage('Errors');

		// We need (at least) this to ensure that mod files are included
		if (!empty($modSettings['integrate_admin_include']))
		{
			$admin_includes = explode(',', $modSettings['integrate_admin_include']);
			foreach ($admin_includes as $include)
			{
				$include = strtr(trim($include), array('BOARDDIR' => BOARDDIR, 'SOURCEDIR' => SOURCEDIR, '$themedir' => $settings['theme_dir']));
				if (file_exists($include))
					require_once($include);
			}
		}

		$errors = array();
		$returns = array();
		$tokens = array();

		// You have to be allowed to do this of course
		$validation = validateSession();
		if (empty($validation))
		{
			require_once(ADMINDIR . '/ManageCoreFeatures.controller.php');
			$controller = new ManageCoreFeatures_Controller();
			$result = $controller->action_index();

			// Load up the core features of the system
			if (empty($result))
			{
				$id = isset($_POST['feature_id']) ? $_POST['feature_id'] : '';

				// The feature being enabled does exist, no messing about
				if (!empty($id) && isset($context['features'][$id]))
				{
					$feature = $context['features'][$id];
					$returns[] = array(
						'value' => (!empty($_POST['feature_' . $id]) && $feature['url'] ? '<a href="' . $feature['url'] . '">' . $feature['title'] . '</a>' : $feature['title']),
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
			$errors[] = array('value' => $txt[$validation]);


		// Return the response to the calling program
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = array(
			'corefeatures' => array(
				'identifier' => 'corefeature',
				'children' => $returns,
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