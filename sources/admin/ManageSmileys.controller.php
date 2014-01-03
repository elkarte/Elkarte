<?php

/**
 * This file takes care of all administration of smileys.
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
 * This class is in charge with administration of smileys and message icons.
 * It handles actions from the Smileys pages in admin panel.
 */
class ManageSmileys_Controller extends Action_Controller
{
	/**
	 * Smileys configuration settings form
	 * @var Settings_Form
	 */
	protected $_smileySettings;

	/**
	 * Contextual information about smiley sets.
	 */
	private $_smiley_context = array();

	/**
	 * This is the dispatcher of smileys administration.
	 *
	 * @uses ManageSmileys language
	 * @uses ManageSmileys template
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt, $modSettings;

		loadLanguage('ManageSmileys');
		loadTemplate('ManageSmileys');

		$subActions = array(
			'addsmiley' => array($this, 'action_addsmiley', 'enabled' => !empty($modSettings['smiley_enable']), 'permission' => 'manage_smileys'),
			'editicon' => array($this, 'action_editicon', 'enabled' => !empty($modSettings['messageIcons_enable']), 'permission' => 'manage_smileys'),
			'editicons' => array($this, 'action_editicon', 'enabled' => !empty($modSettings['messageIcons_enable']), 'permission' => 'manage_smileys'),
			'editsets' => array($this, 'action_edit', 'permission' => 'admin_forum'),
			'editsmileys' => array($this, 'action_editsmiley', 'enabled' => !empty($modSettings['smiley_enable']), 'permission' => 'manage_smileys'),
			'import' => array($this, 'action_edit', 'permission' => 'manage_smileys'),
			'modifyset' => array($this, 'action_edit', 'permission' => 'manage_smileys'),
			'modifysmiley' => array($this, 'action_editsmiley', 'enabled' => !empty($modSettings['smiley_enable']), 'permission' => 'manage_smileys'),
			'setorder' => array($this, 'action_setorder', 'enabled' => !empty($modSettings['smiley_enable']), 'permission' => 'manage_smileys'),
			'settings' => array($this, 'action_smileySettings_display', 'permission' => 'manage_smileys'),
			'install' => array($this, 'action_install', 'permission' => 'manage_smileys')
		);

		call_integration_hook('integrate_manage_smileys', array(&$subActions));

		// Set the smiley context.
		$this->_initSmileyContext();

		// Default the sub-action to 'edit smiley settings'.
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'editsets';

		// Set up template stuff
		$context['page_title'] = $txt['smileys_manage'];
		$context['sub_action'] = $subAction;

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['smileys_manage'],
			'help' => 'smileys',
			'description' => $txt['smiley_settings_explain'],
			'tabs' => array(
				'editsets' => array(
					'description' => $txt['smiley_editsets_explain'],
				),
				'addsmiley' => array(
					'description' => $txt['smiley_addsmiley_explain'],
				),
				'editsmileys' => array(
					'description' => $txt['smiley_editsmileys_explain'],
				),
				'setorder' => array(
					'description' => $txt['smiley_setorder_explain'],
				),
				'editicons' => array(
					'description' => $txt['icons_edit_icons_explain'],
				),
				'settings' => array(
					'description' => $txt['smiley_settings_explain'],
				),
			),
		);

		// Some settings may not be enabled, disallow these from the tabs as appropriate.
		if (empty($modSettings['messageIcons_enable']))
			$context[$context['admin_menu_name']]['tab_data']['tabs']['editicons']['disabled'] = true;

		if (empty($modSettings['smiley_enable']))
		{
			$context[$context['admin_menu_name']]['tab_data']['tabs']['addsmiley']['disabled'] = true;
			$context[$context['admin_menu_name']]['tab_data']['tabs']['editsmileys']['disabled'] = true;
			$context[$context['admin_menu_name']]['tab_data']['tabs']['setorder']['disabled'] = true;
		}

		// Call the right function for this sub-action.
		$action = new Action();
		$action->initialize($subActions, 'editsets');
		$action->dispatch($subAction);
	}

	/**
	 * Displays and allows to modify smileys settings.
	 * @uses show_settings sub template
	 */
	public function action_smileySettings_display()
	{
		global $context, $scripturl;

		// initialize the form
		$this->_initSmileySettingsForm();

		$config_vars = $this->_smileySettings->settings();

		call_integration_hook('integrate_modify_smiley_settings');

		// For the basics of the settings.
		require_once(SUBSDIR . '/Settings.class.php');
		require_once(SUBSDIR . '/Smileys.subs.php');
		$context['sub_template'] = 'show_settings';

		// Finish up the form...
		$context['post_url'] = $scripturl . '?action=admin;area=smileys;save;sa=settings';
		$context['permissions_excluded'] = array(-1);

		// Saving the settings?
		if (isset($_GET['save']))
		{
			checkSession();

			$_POST['smiley_sets_default'] = empty($this->_smiley_context[$_POST['smiley_sets_default']]) ? 'default' : $_POST['smiley_sets_default'];

			// Make sure that the smileys are in the right order after enabling them.
			if (isset($_POST['smiley_enable']))
				sortSmileyTable();

			call_integration_hook('integrate_save_smiley_settings');

			Settings_Form::save_db($config_vars);

			cache_put_data('parsing_smileys', null, 480);
			cache_put_data('posting_smileys', null, 480);

			redirectexit('action=admin;area=smileys;sa=settings');
		}

		// We need this for the in-line permissions
		createToken('admin-mp');

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Retrieve and initialize the form with smileys administration settings.
	 */
	private function _initSmileySettingsForm()
	{
		// This is really quite wanting.
		require_once(SUBSDIR . '/Settings.class.php');

		// Instantiate the form
		$this->_smileySettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_settings();

		return $this->_smileySettings->settings($config_vars);
	}

	/**
	 * Retrieve and return smileys administration settings.
	 */
	private function _settings()
	{
		global $txt, $modSettings;

		// The directories...
		$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? BOARDDIR . '/smileys' : $modSettings['smileys_dir'];
		$context['smileys_dir_found'] = is_dir($context['smileys_dir']);

		// All the settings for the page...
		$config_vars = array(
			array('title', 'settings'),
				// Inline permissions.
				array('permissions', 'manage_smileys'),
			'',
				array('select', 'smiley_sets_default', $this->_smiley_context),
				array('check', 'smiley_sets_enable'),
				array('check', 'smiley_enable', 'subtext' => $txt['smileys_enable_note']),
				array('text', 'smileys_url', 40),
				array('text', 'smileys_dir', 'invalid' => !$context['smileys_dir_found'], 40),
			'',
				// Message icons.
				array('check', 'messageIcons_enable', 'subtext' => $txt['setting_messageIcons_enable_note']),
		);

		return $config_vars;
	}

	/**
	 * Return the smiley settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}

	/**
	 * List, add, remove, modify smileys sets.
	 */
	public function action_edit()
	{
		global $modSettings, $context, $txt, $scripturl;

		require_once(SUBSDIR . '/Smileys.subs.php');

		// Set the right tab to be selected.
		$context[$context['admin_menu_name']]['current_subsection'] = 'editsets';
		$context['sub_template'] = $context['sub_action'];

		// They must've been submitted a form.
		if (isset($_POST['smiley_save']) || isset($_POST['delete_set']))
		{
			checkSession();
			validateToken('admin-mss', 'request');

			// Delete selected smiley sets.
			if (!empty($_POST['delete_set']) && !empty($_POST['smiley_set']))
			{
				$set_paths = explode(',', $modSettings['smiley_sets_known']);
				$set_names = explode("\n", $modSettings['smiley_sets_names']);
				foreach ($_POST['smiley_set'] as $id => $val)
				{
					if (isset($set_paths[$id], $set_names[$id]) && !empty($id))
						unset($set_paths[$id], $set_names[$id]);
				}

				updateSettings(array(
					'smiley_sets_known' => implode(',', $set_paths),
					'smiley_sets_names' => implode("\n", $set_names),
					'smiley_sets_default' => in_array($modSettings['smiley_sets_default'], $set_paths) ? $modSettings['smiley_sets_default'] : $set_paths[0],
				));
			}
			// Add a new smiley set.
			elseif (!empty($_POST['add']))
				$context['sub_action'] = 'modifyset';
			// Create or modify a smiley set.
			elseif (isset($_POST['set']))
			{
				$set_paths = explode(',', $modSettings['smiley_sets_known']);
				$set_names = explode("\n", $modSettings['smiley_sets_names']);

				// Create a new smiley set.
				if ($_POST['set'] == -1 && isset($_POST['smiley_sets_path']))
				{
					if (in_array($_POST['smiley_sets_path'], $set_paths))
						fatal_lang_error('smiley_set_already_exists');

					updateSettings(array(
						'smiley_sets_known' => $modSettings['smiley_sets_known'] . ',' . $_POST['smiley_sets_path'],
						'smiley_sets_names' => $modSettings['smiley_sets_names'] . "\n" . $_POST['smiley_sets_name'],
						'smiley_sets_default' => empty($_POST['smiley_sets_default']) ? $modSettings['smiley_sets_default'] : $_POST['smiley_sets_path'],
					));
				}
				// Modify an existing smiley set.
				else
				{
					// Make sure the smiley set exists.
					if (!isset($set_paths[$_POST['set']]) || !isset($set_names[$_POST['set']]))
						fatal_lang_error('smiley_set_not_found');

					// Make sure the path is not yet used by another smileyset.
					if (in_array($_POST['smiley_sets_path'], $set_paths) && $_POST['smiley_sets_path'] != $set_paths[$_POST['set']])
						fatal_lang_error('smiley_set_path_already_used');

					$set_paths[$_POST['set']] = $_POST['smiley_sets_path'];
					$set_names[$_POST['set']] = $_POST['smiley_sets_name'];
					updateSettings(array(
						'smiley_sets_known' => implode(',', $set_paths),
						'smiley_sets_names' => implode("\n", $set_names),
						'smiley_sets_default' => empty($_POST['smiley_sets_default']) ? $modSettings['smiley_sets_default'] : $_POST['smiley_sets_path']
					));
				}

				// The user might have checked to also import smileys.
				if (!empty($_POST['smiley_sets_import']))
					$this->importSmileys($_POST['smiley_sets_path']);
			}

			cache_put_data('parsing_smileys', null, 480);
			cache_put_data('posting_smileys', null, 480);
		}

		// Load all available smileysets...
		$context['smiley_sets'] = explode(',', $modSettings['smiley_sets_known']);
		$set_names = explode("\n", $modSettings['smiley_sets_names']);
		foreach ($context['smiley_sets'] as $i => $set)
			$context['smiley_sets'][$i] = array(
				'id' => $i,
				'path' => htmlspecialchars($set, ENT_COMPAT, 'UTF-8'),
				'name' => htmlspecialchars($set_names[$i], ENT_COMPAT, 'UTF-8'),
				'selected' => $set == $modSettings['smiley_sets_default']
			);

		// Importing any smileys from an existing set?
		if ($context['sub_action'] == 'import')
		{
			checkSession('get');
			validateToken('admin-mss', 'request');

			$_GET['set'] = (int) $_GET['set'];

			// Sanity check - then import.
			if (isset($context['smiley_sets'][$_GET['set']]))
				$this->importSmileys(un_htmlspecialchars($context['smiley_sets'][$_GET['set']]['path']));

			// Force the process to continue.
			$context['sub_action'] = 'modifyset';
			$context['sub_template'] = 'modifyset';
		}

		// If we're modifying or adding a smileyset, some context info needs to be set.
		if ($context['sub_action'] == 'modifyset')
		{
			$_GET['set'] = !isset($_GET['set']) ? -1 : (int) $_GET['set'];
			if ($_GET['set'] == -1 || !isset($context['smiley_sets'][$_GET['set']]))
				$context['current_set'] = array(
					'id' => '-1',
					'path' => '',
					'name' => '',
					'selected' => false,
					'is_new' => true,
				);
			else
			{
				$context['current_set'] = &$context['smiley_sets'][$_GET['set']];
				$context['current_set']['is_new'] = false;

				// Calculate whether there are any smileys in the directory that can be imported.
				if (!empty($modSettings['smiley_enable']) && !empty($modSettings['smileys_dir']) && is_dir($modSettings['smileys_dir'] . '/' . $context['current_set']['path']))
				{
					$smileys = array();
					$dir = dir($modSettings['smileys_dir'] . '/' . $context['current_set']['path']);
					while ($entry = $dir->read())
					{
						if (in_array(strrchr($entry, '.'), array('.jpg', '.gif', '.jpeg', '.png')))
							$smileys[strtolower($entry)] = $entry;
					}
					$dir->close();

					if (empty($smileys))
						fatal_lang_error('smiley_set_dir_not_found', false, array($context['current_set']['name']));

					// Exclude the smileys that are already in the database.
					$found = smileyExists($smileys);
					foreach ($found as $smiley)
					{
						if (isset($smileys[$smiley]))
							unset($smileys[$smiley]);
					}

					$context['current_set']['can_import'] = count($smileys);

					// Setup this string to look nice.
					$txt['smiley_set_import_multiple'] = sprintf($txt['smiley_set_import_multiple'], $context['current_set']['can_import']);
				}
			}

			// Retrieve all potential smiley set directories.
			$context['smiley_set_dirs'] = array();
			if (!empty($modSettings['smileys_dir']) && is_dir($modSettings['smileys_dir']))
			{
				$dir = dir($modSettings['smileys_dir']);
				while ($entry = $dir->read())
				{
					if (!in_array($entry, array('.', '..')) && is_dir($modSettings['smileys_dir'] . '/' . $entry))
						$context['smiley_set_dirs'][] = array(
							'id' => $entry,
							'path' => $modSettings['smileys_dir'] . '/' . $entry,
							'selectable' => $entry == $context['current_set']['path'] || !in_array($entry, explode(',', $modSettings['smiley_sets_known'])),
							'current' => $entry == $context['current_set']['path'],
						);
				}
				$dir->close();
			}
		}

		// This is our save haven.
		createToken('admin-mss', 'request');

		$listOptions = array(
			'id' => 'smiley_set_list',
			'title' => $txt['smiley_sets'],
			'no_items_label' => $txt['smiley_sets_none'],
			'base_href' => $scripturl . '?action=admin;area=smileys;sa=editsets',
			'default_sort_col' => 'default',
			'get_items' => array(
				'function' => 'list_getSmileySets',
			),
			'get_count' => array(
				'function' => 'list_getNumSmileySets',
			),
			'columns' => array(
				'default' => array(
					'header' => array(
						'value' => $txt['smiley_sets_default'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $settings;
							return $rowData[\'selected\'] ? \'<img src="\' . $settings[\'images_url\'] . \'/icons/field_valid.png" alt="*" class="icon" />\' : \'\';
						'),
						'class' => 'centertext',
					),
					'sort' => array(
						'default' => 'selected DESC',
					),
				),
				'name' => array(
					'header' => array(
						'value' => $txt['smiley_sets_name'],
					),
					'data' => array(
						'db_htmlsafe' => 'name',
					),
					'sort' => array(
						'default' => 'name',
						'reverse' => 'name DESC',
					),
				),
				'url' => array(
					'header' => array(
						'value' => $txt['smiley_sets_url'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => $modSettings['smileys_url'] . '/<strong>%1$s</strong>/...',
							'params' => array(
								'path' => true,
							),
						),
					),
					'sort' => array(
						'default' => 'path',
						'reverse' => 'path DESC',
					),
				),
				'modify' => array(
					'header' => array(
						'value' => $txt['smiley_set_modify'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=smileys;sa=modifyset;set=%1$d">' . $txt['smiley_set_modify'] . '</a>',
							'params' => array(
								'id' => true,
							),
						),
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							return $rowData[\'id\'] == 0 ? \'\' : sprintf(\'<input type="checkbox" name="smiley_set[%1$d]" class="input_check" />\', $rowData[\'id\']);
						'),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=smileys;sa=editsets',
				'token' => 'admin-mss',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="delete_set" value="' . $txt['smiley_sets_delete'] . '" onclick="return confirm(\'' . $txt['smiley_sets_confirm'] . '\');" class="right_submit" />
						<a class="linkbutton_right" href="' . $scripturl . '?action=admin;area=smileys;sa=modifyset">' . $txt['smiley_sets_add'] . '</a> ',
				),
			),
		);

		require_once(SUBSDIR . '/List.class.php');
		createList($listOptions);
	}

	/**
	 * Add a smiley, that's right.
	 */
	public function action_addsmiley()
	{
		global $modSettings, $context, $txt;

		require_once(SUBSDIR . '/Smileys.subs.php');

		// Get a list of all known smiley sets.
		$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? BOARDDIR . '/smileys' : $modSettings['smileys_dir'];
		$context['smileys_dir_found'] = is_dir($context['smileys_dir']);
		$context['smiley_sets'] = explode(',', $modSettings['smiley_sets_known']);
		$context['sub_template'] = 'addsmiley';

		$set_names = explode("\n", $modSettings['smiley_sets_names']);
		foreach ($context['smiley_sets'] as $i => $set)
			$context['smiley_sets'][$i] = array(
				'id' => $i,
				'path' => htmlspecialchars($set, ENT_COMPAT, 'UTF-8'),
				'name' => htmlspecialchars($set_names[$i], ENT_COMPAT, 'UTF-8'),
				'selected' => $set == $modSettings['smiley_sets_default']
			);

		// Submitting a form?
		if (isset($_POST[$context['session_var']], $_POST['smiley_code']))
		{
			checkSession();

			// Some useful arrays... types we allow - and ports we don't!
			$allowedTypes = array('jpeg', 'jpg', 'gif', 'png', 'bmp');
			$disabledFiles = array('con', 'com1', 'com2', 'com3', 'com4', 'prn', 'aux', 'lpt1', '.htaccess', 'index.php');

			$_POST['smiley_code'] = htmltrim__recursive($_POST['smiley_code']);
			$_POST['smiley_location'] = empty($_POST['smiley_location']) || $_POST['smiley_location'] > 2 || $_POST['smiley_location'] < 0 ? 0 : (int) $_POST['smiley_location'];
			$_POST['smiley_filename'] = htmltrim__recursive($_POST['smiley_filename']);

			// Make sure some code was entered.
			if (empty($_POST['smiley_code']))
				fatal_lang_error('smiley_has_no_code');

			// Check whether the new code has duplicates. It should be unique.
			if (validateDuplicateSmiley($_POST['smiley_code']))
				fatal_lang_error('smiley_not_unique');

			// If we are uploading - check all the smiley sets are writable!
			if ($_POST['method'] != 'existing')
			{
				$writeErrors = array();
				foreach ($context['smiley_sets'] as $set)
				{
					if (!is_writable($context['smileys_dir'] . '/' . un_htmlspecialchars($set['path'])))
						$writeErrors[] = $set['path'];
				}

				if (!empty($writeErrors))
					fatal_lang_error('smileys_upload_error_notwritable', true, array(implode(', ', $writeErrors)));
			}

			// Uploading just one smiley for all of them?
			if (isset($_POST['sameall']) && isset($_FILES['uploadSmiley']['name']) && $_FILES['uploadSmiley']['name'] != '')
			{
				if (!is_uploaded_file($_FILES['uploadSmiley']['tmp_name']) || (ini_get('open_basedir') == '' && !file_exists($_FILES['uploadSmiley']['tmp_name'])))
					fatal_lang_error('smileys_upload_error');

				// Sorry, no spaces, dots, or anything else but letters allowed.
				$_FILES['uploadSmiley']['name'] = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $_FILES['uploadSmiley']['name']);

				// We only allow image files - it's THAT simple - no messing around here...
				if (!in_array(strtolower(substr(strrchr($_FILES['uploadSmiley']['name'], '.'), 1)), $allowedTypes))
					fatal_lang_error('smileys_upload_error_types', false, array(implode(', ', $allowedTypes)));

				// We only need the filename...
				$destName = basename($_FILES['uploadSmiley']['name']);

				// Make sure they aren't trying to upload a nasty file - for their own good here!
				if (in_array(strtolower($destName), $disabledFiles))
					fatal_lang_error('smileys_upload_error_illegal');

				// Check if the file already exists... and if not move it to EVERY smiley set directory.
				$i = 0;

				// Keep going until we find a set the file doesn't exist in. (or maybe it exists in all of them?)
				while (isset($context['smiley_sets'][$i]) && file_exists($context['smileys_dir'] . '/' . un_htmlspecialchars($context['smiley_sets'][$i]['path']) . '/' . $destName))
					$i++;

				// Okay, we're going to put the smiley right here, since it's not there yet!
				if (isset($context['smiley_sets'][$i]['path']))
				{
					$smileyLocation = $context['smileys_dir'] . '/' . un_htmlspecialchars($context['smiley_sets'][$i]['path']) . '/' . $destName;
					move_uploaded_file($_FILES['uploadSmiley']['tmp_name'], $smileyLocation);
					@chmod($smileyLocation, 0644);

					// Now, we want to move it from there to all the other sets.
					for ($n = count($context['smiley_sets']); $i < $n; $i++)
					{
						$currentPath = $context['smileys_dir'] . '/' . un_htmlspecialchars($context['smiley_sets'][$i]['path']) . '/' . $destName;

						// The file is already there!  Don't overwrite it!
						if (file_exists($currentPath))
							continue;

						// Okay, so copy the first one we made to here.
						copy($smileyLocation, $currentPath);
						@chmod($currentPath, 0644);
					}
				}

				// Finally make sure it's saved correctly!
				$_POST['smiley_filename'] = $destName;
			}
			// What about uploading several files?
			elseif ($_POST['method'] != 'existing')
			{
				$newName = '';
				foreach ($_FILES as $name => $dummy)
				{
					if ($_FILES[$name]['name'] == '')
						fatal_lang_error('smileys_upload_error_blank');

					if (empty($newName))
						$newName = basename($_FILES[$name]['name']);
					elseif (basename($_FILES[$name]['name']) != $newName)
						fatal_lang_error('smileys_upload_error_name');
				}

				foreach ($context['smiley_sets'] as $i => $set)
				{
					$set['name'] = un_htmlspecialchars($set['name']);
					$set['path'] = un_htmlspecialchars($set['path']);

					if (!isset($_FILES['individual_' . $set['name']]['name']) || $_FILES['individual_' . $set['name']]['name'] == '')
						continue;

					// Got one...
					if (!is_uploaded_file($_FILES['individual_' . $set['name']]['tmp_name']) || (ini_get('open_basedir') == '' && !file_exists($_FILES['individual_' . $set['name']]['tmp_name'])))
						fatal_lang_error('smileys_upload_error');

					// Sorry, no spaces, dots, or anything else but letters allowed.
					$_FILES['individual_' . $set['name']]['name'] = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $_FILES['individual_' . $set['name']]['name']);

					// We only allow image files - it's THAT simple - no messing around here...
					if (!in_array(strtolower(substr(strrchr($_FILES['individual_' . $set['name']]['name'], '.'), 1)), $allowedTypes))
						fatal_lang_error('smileys_upload_error_types', false, array(implode(', ', $allowedTypes)));

					// We only need the filename...
					$destName = basename($_FILES['individual_' . $set['name']]['name']);

					// Make sure they aren't trying to upload a nasty file - for their own good here!
					if (in_array(strtolower($destName), $disabledFiles))
						fatal_lang_error('smileys_upload_error_illegal');

					// If the file exists - ignore it.
					$smileyLocation = $context['smileys_dir'] . '/' . $set['path'] . '/' . $destName;
					if (file_exists($smileyLocation))
						continue;

					// Finally - move the image!
					move_uploaded_file($_FILES['individual_' . $set['name']]['tmp_name'], $smileyLocation);
					@chmod($smileyLocation, 0644);

					// Should always be saved correctly!
					$_POST['smiley_filename'] = $destName;
				}
			}

			// Also make sure a filename was given.
			if (empty($_POST['smiley_filename']))
				fatal_lang_error('smiley_has_no_filename');

			// Find the position on the right.
			$smiley_order = '0';
			if ($_POST['smiley_location'] != 1)
			{
				$_POST['smiley_location'] = (int) $_POST['smiley_location'];
				$smiley_order = nextSmileyLocation($_POST['smiley_location']);

				if (empty($smiley_order))
					$smiley_order = '0';
			}
			$param = array(
				$_POST['smiley_code'],
				$_POST['smiley_filename'],
				$_POST['smiley_description'],
				(int) $_POST['smiley_location'],
				$smiley_order,
			);
			addSmiley($param);

			cache_put_data('parsing_smileys', null, 480);
			cache_put_data('posting_smileys', null, 480);

			// No errors? Out of here!
			redirectexit('action=admin;area=smileys;sa=editsmileys');
		}

		$context['selected_set'] = $modSettings['smiley_sets_default'];

		// Get all possible filenames for the smileys.
		$context['filenames'] = array();
		if ($context['smileys_dir_found'])
		{
			foreach ($context['smiley_sets'] as $smiley_set)
			{
				if (!file_exists($context['smileys_dir'] . '/' . un_htmlspecialchars($smiley_set['path'])))
					continue;

				$dir = dir($context['smileys_dir'] . '/' . un_htmlspecialchars($smiley_set['path']));
				while ($entry = $dir->read())
				{
					if (!in_array($entry, $context['filenames']) && in_array(strrchr($entry, '.'), array('.jpg', '.gif', '.jpeg', '.png')))
						$context['filenames'][strtolower($entry)] = array(
							'id' => htmlspecialchars($entry, ENT_COMPAT, 'UTF-8'),
							'selected' => false,
						);
				}
				$dir->close();
			}
			ksort($context['filenames']);
		}

		// Create a new smiley from scratch.
		$context['filenames'] = array_values($context['filenames']);
		$context['current_smiley'] = array(
			'id' => 0,
			'code' => '',
			'filename' => $context['filenames'][0]['id'],
			'description' => $txt['smileys_default_description'],
			'location' => 0,
			'is_new' => true,
		);
	}

	/**
	 * Add, remove, edit smileys.
	 */
	public function action_editsmiley()
	{
		global $modSettings, $context, $txt, $scripturl;

		require_once(SUBSDIR . '/Smileys.subs.php');

		// Force the correct tab to be displayed.
		$context[$context['admin_menu_name']]['current_subsection'] = 'editsmileys';
		$context['sub_template'] = $context['sub_action'];

		// Submitting a form?
		if (isset($_POST['smiley_save']) || isset($_POST['smiley_action']))
		{
			checkSession();

			// Changing the selected smileys?
			if (isset($_POST['smiley_action']) && !empty($_POST['checked_smileys']))
			{
				foreach ($_POST['checked_smileys'] as $id => $smiley_id)
					$_POST['checked_smileys'][$id] = (int) $smiley_id;

				if ($_POST['smiley_action'] == 'delete')
					deleteSmileys($_POST['checked_smileys']);
				// Changing the status of the smiley?
				else
				{
					// Check it's a valid type.
					$displayTypes = array(
						'post' => 0,
						'hidden' => 1,
						'popup' => 2
					);
					if (isset($displayTypes[$_POST['smiley_action']]))
						updateSmileyDisplayType($_POST['checked_smileys'], $displayTypes[$_POST['smiley_action']]);
				}
			}
			// Create/modify a smiley.
			elseif (isset($_POST['smiley']))
			{
				$_POST['smiley'] = (int) $_POST['smiley'];

				// Is it a delete?
				if (!empty($_POST['deletesmiley']))
					deleteSmileys(array($_POST['smiley']));
				// Otherwise an edit.
				else
				{
					$_POST['smiley_code'] = htmltrim__recursive($_POST['smiley_code']);
					$_POST['smiley_filename'] = htmltrim__recursive($_POST['smiley_filename']);
					$_POST['smiley_location'] = empty($_POST['smiley_location']) || $_POST['smiley_location'] > 2 || $_POST['smiley_location'] < 0 ? 0 : (int) $_POST['smiley_location'];

					// Make sure some code was entered.
					if (empty($_POST['smiley_code']))
						fatal_lang_error('smiley_has_no_code');

					// Also make sure a filename was given.
					if (empty($_POST['smiley_filename']))
						fatal_lang_error('smiley_has_no_filename');

					// Check whether the new code has duplicates. It should be unique.
					if (validateDuplicateSmiley($_POST['smiley_code'], $_POST['smiley']))
						fatal_lang_error('smiley_not_unique');

					$param = array(
						'smiley_location' => $_POST['smiley_location'],
						'smiley' => $_POST['smiley'],
						'smiley_code' => $_POST['smiley_code'],
						'smiley_filename' => $_POST['smiley_filename'],
						'smiley_description' => $_POST['smiley_description'],
					);
					updateSmiley($param);
				}

				// Sort all smiley codes for more accurate parsing (longest code first).
				sortSmileyTable();
			}

			cache_put_data('parsing_smileys', null, 480);
			cache_put_data('posting_smileys', null, 480);
		}

		// Load all known smiley sets.
		$context['smiley_sets'] = explode(',', $modSettings['smiley_sets_known']);
		$set_names = explode("\n", $modSettings['smiley_sets_names']);
		foreach ($context['smiley_sets'] as $i => $set)
			$context['smiley_sets'][$i] = array(
				'id' => $i,
				'path' => htmlspecialchars($set, ENT_COMPAT, 'UTF-8'),
				'name' => htmlspecialchars($set_names[$i], ENT_COMPAT, 'UTF-8'),
				'selected' => $set == $modSettings['smiley_sets_default']
			);

		// Prepare overview of all (custom) smileys.
		if ($context['sub_action'] == 'editsmileys')
		{
			// Determine the language specific sort order of smiley locations.
			$smiley_locations = array(
				$txt['smileys_location_form'],
				$txt['smileys_location_hidden'],
				$txt['smileys_location_popup'],
			);
			asort($smiley_locations);

			// Create a list of options for selecting smiley sets.
			$smileyset_option_list = '
				<select name="set" onchange="changeSet(this.options[this.selectedIndex].value);">';
			foreach ($context['smiley_sets'] as $smiley_set)
				$smileyset_option_list .= '
					<option value="' . $smiley_set['path'] . '"' . ($modSettings['smiley_sets_default'] == $smiley_set['path'] ? ' selected="selected"' : '') . '>' . $smiley_set['name'] . '</option>';
			$smileyset_option_list .= '
				</select>';

			$listOptions = array(
				'id' => 'smiley_list',
				'title' => $txt['smileys_edit'],
				'items_per_page' => 40,
				'base_href' => $scripturl . '?action=admin;area=smileys;sa=editsmileys',
				'default_sort_col' => 'filename',
				'get_items' => array(
					'function' => 'list_getSmileys',
				),
				'get_count' => array(
					'function' => 'list_getNumSmileys',
				),
				'no_items_label' => $txt['smileys_no_entries'],
				'columns' => array(
					'picture' => array(
						'data' => array(
							'sprintf' => array(
								'format' => '<a href="' . $scripturl . '?action=admin;area=smileys;sa=modifysmiley;smiley=%1$d"><img src="' . $modSettings['smileys_url'] . '/' . $modSettings['smiley_sets_default'] . '/%2$s" alt="%3$s" id="smiley%1$d" /><input type="hidden" name="smileys[%1$d][filename]" value="%2$s" /></a>',
								'params' => array(
									'id_smiley' => false,
									'filename' => true,
									'description' => true,
								),
							),
							'class' => 'imagecolumn',
						),
					),
					'code' => array(
						'header' => array(
							'value' => $txt['smileys_code'],
						),
						'data' => array(
							'db_htmlsafe' => 'code',
						),
						'sort' => array(
							'default' => 'code',
							'reverse' => 'code DESC',
						),
					),
					'filename' => array(
						'header' => array(
							'value' => $txt['smileys_filename'],
						),
						'data' => array(
							'db_htmlsafe' => 'filename',
						),
						'sort' => array(
							'default' => 'filename',
							'reverse' => 'filename DESC',
						),
					),
					'location' => array(
						'header' => array(
							'value' => $txt['smileys_location'],
						),
						'data' => array(
							'function' => create_function('$rowData', '
								global $txt;

								if (empty($rowData[\'hidden\']))
									return $txt[\'smileys_location_form\'];
								elseif ($rowData[\'hidden\'] == 1)
									return $txt[\'smileys_location_hidden\'];
								else
									return $txt[\'smileys_location_popup\'];
							'),
						),
						'sort' => array(
							'default' => 'FIND_IN_SET(hidden, \'' . implode(',', array_keys($smiley_locations)) . '\')',
							'reverse' => 'FIND_IN_SET(hidden, \'' . implode(',', array_keys($smiley_locations)) . '\') DESC',
						),
					),
					'tooltip' => array(
						'header' => array(
							'value' => $txt['smileys_description'],
						),
						'data' => array(
							'function' => create_function('$rowData', empty($modSettings['smileys_dir']) || !is_dir($modSettings['smileys_dir']) ? '
								return htmlspecialchars($rowData[\'description\'], ENT_COMPAT, \'UTF-8\');
							' : '
								global $context, $txt, $modSettings;

								// Check if there are smileys missing in some sets.
								$missing_sets = array();
								foreach ($context[\'smiley_sets\'] as $smiley_set)
									if (!file_exists(sprintf(\'%1$s/%2$s/%3$s\', $modSettings[\'smileys_dir\'], $smiley_set[\'path\'], $rowData[\'filename\'])))
										$missing_sets[] = $smiley_set[\'path\'];

								$description = htmlspecialchars($rowData[\'description\'], ENT_COMPAT, \'UTF-8\');

								if (!empty($missing_sets))
									$description .= sprintf(\'<br /><span class="smalltext"><strong>%1$s:</strong> %2$s</span>\', $txt[\'smileys_not_found_in_set\'], implode(\', \', $missing_sets));

								return $description;
							'),
						),
						'sort' => array(
							'default' => 'description',
							'reverse' => 'description DESC',
						),
					),
					'modify' => array(
						'header' => array(
							'value' => $txt['smileys_modify'],
							'class' => 'centertext',
						),
						'data' => array(
							'sprintf' => array(
								'format' => '<a href="' . $scripturl . '?action=admin;area=smileys;sa=modifysmiley;smiley=%1$d">' . $txt['smileys_modify'] . '</a>',
								'params' => array(
									'id_smiley' => false,
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
								'format' => '<input type="checkbox" name="checked_smileys[]" value="%1$d" class="input_check" />',
								'params' => array(
									'id_smiley' => false,
								),
							),
							'class' => 'centertext',
						),
					),
				),
				'form' => array(
					'href' => $scripturl . '?action=admin;area=smileys;sa=editsmileys',
					'name' => 'smileyForm',
				),
				'additional_rows' => array(
					array(
						'position' => 'above_column_headers',
						'value' => $smileyset_option_list,
						'class' => 'righttext',
					),
					array(
						'position' => 'below_table_data',
						'value' => '
							<select name="smiley_action" onchange="makeChanges(this.value);">
								<option value="-1">' . $txt['smileys_with_selected'] . ':</option>
								<option value="-1">--------------</option>
								<option value="hidden">' . $txt['smileys_make_hidden'] . '</option>
								<option value="post">' . $txt['smileys_show_on_post'] . '</option>
								<option value="popup">' . $txt['smileys_show_on_popup'] . '</option>
								<option value="delete">' . $txt['smileys_remove'] . '</option>
							</select>
							<noscript>
								<input type="submit" name="perform_action" value="' . $txt['go'] . '" class="right_submit" />
							</noscript>',
						'class' => 'righttext',
					),
				),
				'javascript' => '
					function makeChanges(action)
					{
						if (action == \'-1\')
							return false;
						else if (action == \'delete\')
						{
							if (confirm(\'' . $txt['smileys_confirm'] . '\'))
								document.forms.smileyForm.submit();
						}
						else
							document.forms.smileyForm.submit();
						return true;
					}
					function changeSet(newSet)
					{
						var currentImage, i, knownSmileys = [];

						if (knownSmileys.length == 0)
						{
							for (var i = 0, n = document.images.length; i < n; i++)
								if (document.images[i].id.substr(0, 6) == \'smiley\')
									knownSmileys[knownSmileys.length] = document.images[i].id.substr(6);
						}

						for (i = 0; i < knownSmileys.length; i++)
						{
							currentImage = document.getElementById("smiley" + knownSmileys[i]);
							currentImage.src = "' . $modSettings['smileys_url'] . '/" + newSet + "/" + document.forms.smileyForm["smileys[" + knownSmileys[i] + "][filename]"].value;
						}
					}',
			);

			require_once(SUBSDIR . '/List.class.php');
			createList($listOptions);

			// The list is the only thing to show, so make it the main template.
			$context['default_list'] = 'smiley_list';
			$context['sub_template'] = 'show_list';
		}
		// Modifying smileys.
		elseif ($context['sub_action'] == 'modifysmiley')
		{
			// Get a list of all known smiley sets.
			$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? BOARDDIR . '/smileys' : $modSettings['smileys_dir'];
			$context['smileys_dir_found'] = is_dir($context['smileys_dir']);
			$context['smiley_sets'] = explode(',', $modSettings['smiley_sets_known']);
			$set_names = explode("\n", $modSettings['smiley_sets_names']);
			foreach ($context['smiley_sets'] as $i => $set)
				$context['smiley_sets'][$i] = array(
					'id' => $i,
					'path' => htmlspecialchars($set, ENT_COMPAT, 'UTF-8'),
					'name' => htmlspecialchars($set_names[$i], ENT_COMPAT, 'UTF-8'),
					'selected' => $set == $modSettings['smiley_sets_default']
				);

			$context['selected_set'] = $modSettings['smiley_sets_default'];

			// Get all possible filenames for the smileys.
			$context['filenames'] = array();
			if ($context['smileys_dir_found'])
			{
				foreach ($context['smiley_sets'] as $smiley_set)
				{
					if (!file_exists($context['smileys_dir'] . '/' . un_htmlspecialchars($smiley_set['path'])))
						continue;

					$dir = dir($context['smileys_dir'] . '/' . un_htmlspecialchars($smiley_set['path']));
					while ($entry = $dir->read())
					{
						if (!in_array($entry, $context['filenames']) && in_array(strrchr($entry, '.'), array('.jpg', '.gif', '.jpeg', '.png')))
							$context['filenames'][strtolower($entry)] = array(
								'id' => htmlspecialchars($entry, ENT_COMPAT, 'UTF-8'),
								'selected' => false,
							);
					}
					$dir->close();
				}
				ksort($context['filenames']);
			}

			$_REQUEST['smiley'] = (int) $_REQUEST['smiley'];
			$context['current_smiley'] = getSmiley($_REQUEST['smiley']);
			$context['current_smiley']['code'] = htmlspecialchars($context['current_smiley']['code'], ENT_COMPAT, 'UTF-8');
			$context['current_smiley']['filename'] = htmlspecialchars($context['current_smiley']['filename'], ENT_COMPAT, 'UTF-8');
			$context['current_smiley']['description'] = htmlspecialchars($context['current_smiley']['description'], ENT_COMPAT, 'UTF-8');

			if (isset($context['filenames'][strtolower($context['current_smiley']['filename'])]))
				$context['filenames'][strtolower($context['current_smiley']['filename'])]['selected'] = true;
		}
	}

	/**
	 * Allows to edit the message icons.
	 */
	public function action_editicon()
	{
		global $context, $settings, $txt, $scripturl;

		require_once(SUBSDIR . '/MessageIcons.subs.php');

		// Get a list of icons.
		$context['icons'] = fetchMessageIconsDetails();

		// Submitting a form?
		if (isset($_POST['icons_save']))
		{
			checkSession();

			// Deleting icons?
			if (isset($_POST['delete']) && !empty($_POST['checked_icons']))
			{
				$deleteIcons = array();
				foreach ($_POST['checked_icons'] as $icon)
					$deleteIcons[] = (int) $icon;

				// Do the actual delete!
				deleteMessageIcons($deleteIcons);
			}
			// Editing/Adding an icon?
			elseif ($context['sub_action'] == 'editicon' && isset($_GET['icon']))
			{
				$_GET['icon'] = (int) $_GET['icon'];

				// Do some preperation with the data... like check the icon exists *somewhere*
				if (strpos($_POST['icon_filename'], '.png') !== false)
					$_POST['icon_filename'] = substr($_POST['icon_filename'], 0, -4);

				if (!file_exists($settings['default_theme_dir'] . '/images/post/' . $_POST['icon_filename'] . '.png'))
					fatal_lang_error('icon_not_found');
				// There is a 16 character limit on message icons...
				elseif (strlen($_POST['icon_filename']) > 16)
					fatal_lang_error('icon_name_too_long');
				elseif ($_POST['icon_location'] == $_GET['icon'] && !empty($_GET['icon']))
					fatal_lang_error('icon_after_itself');

				// First do the sorting... if this is an edit reduce the order of everything after it by one ;)
				if ($_GET['icon'] != 0)
				{
					$oldOrder = $context['icons'][$_GET['icon']]['true_order'];
					foreach ($context['icons'] as $id => $data)
						if ($data['true_order'] > $oldOrder)
							$context['icons'][$id]['true_order']--;
				}

				// If there are no existing icons and this is a new one, set the id to 1 (mainly for non-mysql)
				if (empty($_GET['icon']) && empty($context['icons']))
					$_GET['icon'] = 1;

				// Get the new order.
				$newOrder = $_POST['icon_location'] == 0 ? 0 : $context['icons'][$_POST['icon_location']]['true_order'] + 1;

				// Do the same, but with the one that used to be after this icon, done to avoid conflict.
				foreach ($context['icons'] as $id => $data)
					if ($data['true_order'] >= $newOrder)
						$context['icons'][$id]['true_order']++;

				// Finally set the current icon's position!
				$context['icons'][$_GET['icon']]['true_order'] = $newOrder;

				// Simply replace the existing data for the other bits.
				$context['icons'][$_GET['icon']]['title'] = $_POST['icon_description'];
				$context['icons'][$_GET['icon']]['filename'] = $_POST['icon_filename'];
				$context['icons'][$_GET['icon']]['board_id'] = (int) $_POST['icon_board'];

				// Do a huge replace ;)
				$iconInsert = array();
				$iconInsert_new = array();
				foreach ($context['icons'] as $id => $icon)
				{
					if ($id != 0)
						$iconInsert[] = array($id, $icon['board_id'], $icon['title'], $icon['filename'], $icon['true_order']);
					else
						$iconInsert_new[] = array($icon['board_id'], $icon['title'], $icon['filename'], $icon['true_order']);
				}

				updateMessageIcon($iconInsert);

				if (!empty($iconInsert_new))
					addMessageIcon($iconInsert_new);
			}

			// Sort by order, so it is quicker :)
			sortMessageIconTable();

			// Unless we're adding a new thing, we'll escape
			if (!isset($_POST['add']))
				redirectexit('action=admin;area=smileys;sa=editicons');
		}

		$context[$context['admin_menu_name']]['current_subsection'] = 'editicons';

		$listOptions = array(
			'id' => 'message_icon_list',
			'title' => $txt['icons_edit_message_icons'],
			'base_href' => $scripturl . '?action=admin;area=smileys;sa=editicons',
			'get_items' => array(
				'function' => array($this, 'list_fetchMessageIconsDetails'),
			),
			'no_items_label' => $txt['icons_no_entries'],
			'columns' => array(
				'icon' => array(
					'data' => array(
						'sprintf' => array(
							'format' => '<img src="%1$s" alt="%2$s" />',
							'params' => array(
								'image_url' => false,
								'filename' => true,
							),
						),
						'class' => 'centertext',
					),
				),
				'filename' => array(
					'header' => array(
						'value' => $txt['smileys_filename'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '%1$s.png',
							'params' => array(
								'filename' => true,
							),
						),
					),
				),
				'tooltip' => array(
					'header' => array(
						'value' => $txt['smileys_description'],
					),
					'data' => array(
						'db_htmlsafe' => 'title',
					),
				),
				'board' => array(
					'header' => array(
						'value' => $txt['icons_board'],
					),
					'data' => array(
						'db' => 'board',
					),
				),
				'modify' => array(
					'header' => array(
						'value' => $txt['smileys_modify'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . $scripturl . '?action=admin;area=smileys;sa=editicon;icon=%1$s">' . $txt['smileys_modify'] . '</a>',
							'params' => array(
								'id' => false,
							),
						),
					),
				),
				'check' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="checked_icons[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=smileys;sa=editicons',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="delete" value="' . $txt['quickmod_delete_selected'] . '" class="right_submit" />
						<a class="linkbutton_right" href="' . $scripturl . '?action=admin;area=smileys;sa=editicon">' . $txt['icons_add_new'] . '</a>',
				),
			),
		);

		require_once(SUBSDIR . '/List.class.php');
		createList($listOptions);

		// If we're adding/editing an icon we'll need a list of boards
		if ($context['sub_action'] == 'editicon' || isset($_POST['add']))
		{
			// Force the sub_template just in case.
			$context['sub_template'] = 'editicon';
			$context['new_icon'] = !isset($_GET['icon']);

			// Get the properties of the current icon from the icon list.
			if (!$context['new_icon'])
				$context['icon'] = $context['icons'][$_GET['icon']];

			// Get a list of boards needed for assigning this icon to a specific board.
			$boardListOptions = array(
				'selected_board' => isset($context['icon']['board_id']) ? $context['icon']['board_id'] : 0,
			);
			require_once(SUBSDIR . '/Boards.subs.php');
			$context += getBoardList($boardListOptions);
		}
	}

	/**
	 * Allows to edit smileys order.
	 */
	public function action_setorder()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/Smileys.subs.php');
		$context['sub_template'] = 'setorder';

		// Move smileys to another position.
		if (isset($_REQUEST['reorder']))
		{
			checkSession('get');

			$_GET['location'] = empty($_GET['location']) || $_GET['location'] != 'popup' ? 0 : 2;
			$_GET['source'] = empty($_GET['source']) ? 0 : (int) $_GET['source'];

			if (empty($_GET['source']))
				fatal_lang_error('smiley_not_found');

			$smiley = array();

			if (!empty($_GET['after']))
			{
				$_GET['after'] = (int) $_GET['after'];

				$smiley = getSmileyPosition($_GET['location'], $_GET['after']);
				if (empty($smiley))
					fatal_lang_error('smiley_not_found');
			}
			else
			{
				$smiley['row'] = (int) $_GET['row'];
				$smiley['order'] = -1;
				$smiley['location'] = (int) $_GET['location'];
			}

			moveSmileyPosition($smiley, $_GET['source']);
		}

		$context['smileys'] = getSmileys();
		$context['move_smiley'] = empty($_REQUEST['move']) ? 0 : (int) $_REQUEST['move'];

		// Make sure all rows are sequential.
		foreach (array_keys($context['smileys']) as $location)
			$context['smileys'][$location] = array(
				'id' => $location,
				'title' => $location == 'postform' ? $txt['smileys_location_form'] : $txt['smileys_location_popup'],
				'description' => $location == 'postform' ? $txt['smileys_location_form_description'] : $txt['smileys_location_popup_description'],
				'last_row' => count($context['smileys'][$location]['rows']),
				'rows' => array_values($context['smileys'][$location]['rows']),
			);

		// Check & fix smileys that are not ordered properly in the database.
		foreach (array_keys($context['smileys']) as $location)
		{
			foreach ($context['smileys'][$location]['rows'] as $id => $smiley_row)
			{
				// Fix empty rows if any.
				if ($id != $smiley_row[0]['row'])
				{
					updateSmileyRow($id, $smiley_row[0]['row'], $location);
					// Only change the first row value of the first smiley (we don't need the others :P).
					$context['smileys'][$location]['rows'][$id][0]['row'] = $id;
				}

				// Make sure the smiley order is always sequential.
				foreach ($smiley_row as $order_id => $smiley)
					if ($order_id != $smiley['order'])
						updateSmileyOrder($smiley['id'], $order_id);
			}
		}

		cache_put_data('parsing_smileys', null, 480);
		cache_put_data('posting_smileys', null, 480);

		createToken('admin-sort');
	}

	/**
	 * Install a smiley set.
	 */
	function action_install()
	{
		global $modSettings, $scripturl, $context, $txt, $user_info;

		isAllowedTo('manage_smileys');
		checkSession('request');

		// One of these two may be necessary
		loadLanguage('Errors');
		loadLanguage('Packages');

		require_once(SUBSDIR . '/Smileys.subs.php');
		require_once(SUBSDIR . '/Package.subs.php');

		// Installing unless proven otherwise
		$testing = false;

		if (isset($_REQUEST['set_gz']))
		{
			$base_name = strtr(basename($_REQUEST['set_gz']), ':/', '-_');
			$name = Util::htmlspecialchars(strtok(basename($_REQUEST['set_gz']), '.'));
			$name_pr = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $name);
			$context['filename'] = $base_name;

			// Check that the smiley is from simplemachines.org, for now... maybe add mirroring later.
			if (!isAuthorizedServer($_REQUEST['set_gz']) == 0)
				fatal_lang_error('not_valid_server');

			$destination = BOARDDIR . '/packages/' . $base_name;

			if (file_exists($destination))
				fatal_lang_error('package_upload_error_exists');

			// Let's copy it to the packages directory
			file_put_contents($destination, fetch_web_data($_REQUEST['set_gz']));
			$testing = true;
		}
		elseif (isset($_REQUEST['package']))
		{
			$base_name = basename($_REQUEST['package']);
			$name = Util::htmlspecialchars(strtok(basename($_REQUEST['package']), '.'));
			$name_pr = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $name);
			$context['filename'] = $base_name;

			$destination = BOARDDIR . '/packages/' . basename($_REQUEST['package']);
		}

		if (!file_exists($destination))
			fatal_lang_error('package_no_file', false);

		// Make sure temp directory exists and is empty.
		if (file_exists(BOARDDIR . '/packages/temp'))
			deltree(BOARDDIR . '/packages/temp', false);

		if (!mktree(BOARDDIR . '/packages/temp', 0755))
		{
			deltree(BOARDDIR . '/packages/temp', false);
			if (!mktree(BOARDDIR . '/packages/temp', 0777))
			{
				deltree(BOARDDIR . '/packages/temp', false);
				// @todo not sure about url in destination_url
				create_chmod_control(array(BOARDDIR . '/packages/temp/delme.tmp'), array('destination_url' => $scripturl . '?action=admin;area=smileys;sa=install;set_gz=' . $_REQUEST['set_gz'], 'crash_on_error' => true));

				deltree(BOARDDIR . '/packages/temp', false);
				if (!mktree(BOARDDIR . '/packages/temp', 0777))
					fatal_lang_error('package_cant_download', false);
			}
		}

		$extracted = read_tgz_file($destination, BOARDDIR . '/packages/temp');
		if (!$extracted) // @todo needs to change the URL in the next line ;)
			fatal_lang_error('packageget_unable', false, array('http://custom.elkarte.net/index.php?action=search;type=12;basic_search=' . $name));
		if ($extracted && !file_exists(BOARDDIR . '/packages/temp/package-info.xml'))
			foreach ($extracted as $file)
				if (basename($file['filename']) == 'package-info.xml')
				{
					$base_path = dirname($file['filename']) . '/';
					break;
				}

		if (!isset($base_path))
			$base_path = '';

		if (!file_exists(BOARDDIR . '/packages/temp/' . $base_path . 'package-info.xml'))
			fatal_lang_error('package_get_error_missing_xml', false);

		$smileyInfo = getPackageInfo($context['filename']);
		if (!is_array($smileyInfo))
			fatal_lang_error($smileyInfo);

		// See if it is installed?
		if (isSmileySetInstalled($smileyInfo['id']))
			fata_lang_error('package_installed_warning1');

		// Everything is fine, now it's time to do something
		$actions = parsePackageInfo($smileyInfo['xml'], true, 'install');

		$context['post_url'] = $scripturl . '?action=admin;area=smileys;sa=install;package=' . $base_name;
		$context['has_failure'] = false;
		$context['actions'] = array();
		$context['ftp_needed'] = false;

		$has_readme = false;
		foreach ($actions as $action)
		{
			if ($action['type'] == 'readme' || $action['type'] == 'license')
			{
				$has_readme = true;
				$type = 'package_' . $action['type'];
				if (file_exists(BOARDDIR . '/packages/temp/' . $base_path . $action['filename']))
					$context[$type] = htmlspecialchars(trim(file_get_contents(BOARDDIR . '/packages/temp/' . $base_path . $action['filename']), "\n\r"), ENT_COMPAT, 'UTF-8');
				elseif (file_exists($action['filename']))
					$context[$type] = htmlspecialchars(trim(file_get_contents($action['filename']), "\n\r"), ENT_COMPAT, 'UTF-8');

				if (!empty($action['parse_bbc']))
				{
					require_once(SUBSDIR . '/Post.subs.php');
					preparsecode($context[$type]);
					$context[$type] = parse_bbc($context[$type]);
				}
				else
					$context[$type] = nl2br($context[$type]);

				continue;
			}
			elseif ($action['type'] == 'require-dir')
			{
				// Do this one...
				$thisAction = array(
					'type' => $txt['package_extract'] . ' ' . ($action['type'] == 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
					'action' => Util::htmlspecialchars(strtr($action['destination'], array(BOARDDIR => '.')))
				);

				$file = BOARDDIR . '/packages/temp/' . $base_path . $action['filename'];
				if (isset($action['filename']) && (!file_exists($file) || !is_writable(dirname($action['destination']))))
				{
					$context['has_failure'] = true;

					$thisAction += array(
						'description' => $txt['package_action_error'],
						'failed' => true,
					);
				}

				// Show a description for the action if one is provided
				if (empty($thisAction['description']))
					$thisAction['description'] = isset($action['description']) ? $action['description'] : '';

				$context['actions'][] = $thisAction;
			}
			elseif ($action['type'] == 'credits')
			{
				// Time to build the billboard
				$credits_tag = array(
					'url' => $action['url'],
					'license' => $action['license'],
					'copyright' => $action['copyright'],
					'title' => $action['title'],
				);
			}
		}

		if ($testing)
		{
			$context['sub_template'] = 'view_package';
			$context['uninstalling'] = false;
			$context['is_installed'] = false;
			$context['package_name'] = $smileyInfo['name'];
			loadTemplate('Packages');
		}
		// Do the actual install
		else
		{
			$actions = parsePackageInfo($smileyInfo['xml'], false, 'install');
			foreach ($context['actions'] as $action)
			{
				updateSettings(array(
					'smiley_sets_known' => $modSettings['smiley_sets_known'] . ',' . basename($action['action']),
					'smiley_sets_names' => $modSettings['smiley_sets_names'] . "\n" . $smileyInfo['name'] . (count($context['actions']) > 1 ? ' ' . (!empty($action['description']) ? Util::htmlspecialchars($action['description']) : basename($action['action'])) : ''),
				));
			}

			package_flush_cache();

			// Time to tell pacman we have a new package installed!
			package_put_contents(BOARDDIR . '/packages/installed.list', time());

			// Credits tag?
			$credits_tag = (empty($credits_tag)) ? '' : serialize($credits_tag);
			$installed = array(
				'filename' => $smileyInfo['filename'],
				'name' => $smileyInfo['name'],
				'package_id' => $smileyInfo['id'],
				'version' => $smileyInfo['filename'],
				'id_member' => $user_info['id'],
				'member_name' => $user_info['name'],
				'credits_tag' => $credits_tag,
			);
			logPackageInstall($installed);

			logAction('install_package', array('package' => Util::htmlspecialchars($smileyInfo['name']), 'version' => Util::htmlspecialchars($smileyInfo['version'])), 'admin');

			cache_put_data('parsing_smileys', null, 480);
			cache_put_data('posting_smileys', null, 480);
		}

		if (file_exists(BOARDDIR . '/packages/temp'))
			deltree(BOARDDIR . '/packages/temp');

		if (!$testing)
			redirectexit('action=admin;area=smileys');
	}

	/**
	 * Sets our internal smiley context.
	 */
	private function _initSmileyContext()
	{
		global $modSettings;

		// Validate the smiley set name.
		$smiley_sets = explode(',', $modSettings['smiley_sets_known']);
		$set_names = explode("\n", $modSettings['smiley_sets_names']);
		$smiley_context = array();

		foreach ($smiley_sets as $i => $set)
			$smiley_context[$set] = $set_names[$i];

		$this->_smiley_context = $smiley_context;
	}

	/**
	 * A function to import new smileys from an existing directory into the database.
	 *
	 * @param string $smileyPath
	 */
	public function importSmileys($smileyPath)
	{
		global $modSettings;

		require_once(SUBSDIR . '/Smileys.subs.php');

		if (empty($modSettings['smileys_dir']) || !is_dir($modSettings['smileys_dir'] . '/' . $smileyPath))
			fatal_lang_error('smiley_set_unable_to_import');

		$smileys = array();
		$dir = dir($modSettings['smileys_dir'] . '/' . $smileyPath);
		while ($entry = $dir->read())
		{
			if (in_array(strrchr($entry, '.'), array('.jpg', '.gif', '.jpeg', '.png')))
				$smileys[strtolower($entry)] = $entry;
		}
		$dir->close();

		// Exclude the smileys that are already in the database.
		$duplicates = smileyExists($smileys);

		foreach ($duplicates as $duplicate)
		{
			if (isset($smileys[strtolower($duplicate)]))
				unset($smileys[strtolower($duplicate)]);
		}

		$smiley_order = getMaxSmileyOrder();

		$new_smileys = array();
		foreach ($smileys as $smiley)
			if (strlen($smiley) <= 48)
				$new_smileys[] = array(':' . strtok($smiley, '.') . ':', $smiley, strtok($smiley, '.'), 0, ++$smiley_order);

		if (!empty($new_smileys))
		{
			addSmiley($new_smileys);

			// Make sure the smiley codes are still in the right order.
			sortSmileyTable();

			cache_put_data('parsing_smileys', null, 480);
			cache_put_data('posting_smileys', null, 480);
		}
	}

	/**
	 * Callback function for createList().
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 */
	public function list_fetchMessageIconsDetails($start, $items_per_page, $sort)
	{
		return fetchMessageIconsDetails();
	}
}