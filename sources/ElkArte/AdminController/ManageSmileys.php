<?php

/**
 * This file takes care of all administration of smileys.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

use BBC\ParserWrapper;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Cache\Cache;
use ElkArte\Errors\Errors;
use ElkArte\Exceptions\Exception;
use ElkArte\FileFunctions;
use ElkArte\Packages\PackageChmod;
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\Languages\Txt;
use ElkArte\Util;

/**
 * This class is in charge with administration of smileys and message icons.
 * It handles actions from the Smileys pages in admin panel.
 */
class ManageSmileys extends AbstractController
{
	/** @var mixed[] Contextual information about smiley sets. */
	private $_smiley_context = array();

	/**
	 * This is the dispatcher of smileys administration.
	 *
	 * @uses ManageSmileys language
	 * @uses ManageSmileys template
	 * @see  \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt, $modSettings;

		Txt::load('ManageSmileys');
		theme()->getTemplates()->load('ManageSmileys');

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

		// Action controller
		$action = new Action('manage_smileys');

		// Set the smiley context.
		$this->_initSmileyContext();

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

		// Default the sub-action to 'edit smiley settings'. call integrate_sa_manage_smileys
		$subAction = $action->initialize($subActions, 'editsets');

		// Set up the template
		$context['page_title'] = $txt['smileys_manage'];
		$context['sub_action'] = $subAction;

		// Some settings may not be enabled, disallow these from the tabs as appropriate.
		if (empty($modSettings['messageIcons_enable']))
		{
			$context[$context['admin_menu_name']]['tab_data']['tabs']['editicons']['disabled'] = true;
		}

		if (empty($modSettings['smiley_enable']))
		{
			$context[$context['admin_menu_name']]['tab_data']['tabs']['addsmiley']['disabled'] = true;
			$context[$context['admin_menu_name']]['tab_data']['tabs']['editsmileys']['disabled'] = true;
			$context[$context['admin_menu_name']]['tab_data']['tabs']['setorder']['disabled'] = true;
		}

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
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
		{
			$smiley_context[$set] = $set_names[$i];
		}

		$this->_smiley_context = $smiley_context;
	}

	/**
	 * Displays and allows modification to smileys settings.
	 *
	 * @uses show_settings sub template
	 */
	public function action_smileySettings_display()
	{
		global $context;

		// initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// For the basics of the settings.
		require_once(SUBSDIR . '/Smileys.subs.php');
		$context['sub_template'] = 'show_settings';

		// Finish up the form...
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'save', 'sa' => 'settings']);

		// Saving the settings?
		if (isset($this->_req->query->save))
		{
			checkSession();

			$this->_req->post->smiley_sets_default = $this->_req->getPost('smiley_sets_default', 'trim|strval', 'default');

			call_integration_hook('integrate_save_smiley_settings');

			// Save away
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			// Flush the cache so the new settings take effect
			$this->clearSmileyCache();

			redirectexit('action=admin;area=smileys;sa=settings');
		}

		$settingsForm->prepare();
	}

	/**
	 * Retrieve and return smiley administration settings.
	 */
	private function _settings()
	{
		global $txt, $modSettings, $context;

		// The directories...
		$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? BOARDDIR . '/smileys' : $modSettings['smileys_dir'];
		$context['smileys_dir_found'] = FileFunctions::instance()->isDir($context['smileys_dir']);

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

		call_integration_hook('integrate_modify_smiley_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Clear the cache to avoid changes not immediately appearing
	 */
	protected function clearSmileyCache()
	{
		Cache::instance()->remove('parsing_smileys');
		Cache::instance()->remove('posting_smileys');
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
	 *
	 * @event integrate_list_smiley_set_list
	 */
	public function action_edit()
	{
		global $modSettings, $context, $txt;

		require_once(SUBSDIR . '/Smileys.subs.php');

		// Set the right tab to be selected.
		$context[$context['admin_menu_name']]['current_subsection'] = 'editsets';
		$context['sub_template'] = $context['sub_action'];

		// They must've been submitted a form.
		$this->_subActionSubmit();

		// Load all available smileysets...
		$this->loadSmileySets();

		// Importing any smileys from an existing set?
		$this->_subActionImport();

		// If we're modifying or adding a smileyset, some context info needs to be set.
		$this->_subActionModifySet();

		// This is our save haven.
		createToken('admin-mss', 'request');

		$listOptions = array(
			'id' => 'smiley_set_list',
			'title' => $txt['smiley_sets'],
			'no_items_label' => $txt['smiley_sets_none'],
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editsets']),
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
						'function' => function ($rowData) {
							return $rowData['selected'] ? '<i class="icon i-check"></i>' : '';
						},
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
							'format' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'modifyset', 'set' => '']) . '%1$d">' . $txt['smiley_set_modify'] . '</a>',
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
						'function' => function ($rowData) {
							return $rowData['id'] == 0 ? '' : sprintf('<input type="checkbox" name="smiley_set[%1$d]" class="input_check" />', $rowData['id']);
						},
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' =>getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editsets']),
				'token' => 'admin-mss',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'class' => 'submitbutton',
					'value' => '
						<input type="submit" name="delete_set" value="' . $txt['smiley_sets_delete'] . '" onclick="return confirm(\'' . $txt['smiley_sets_confirm'] . '\');" />
						<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'modifyset']) . '">' . $txt['smiley_sets_add'] . '</a> ',
				),
			),
		);

		createList($listOptions);
	}

	/**
	 * Submitted a smiley form, determine what actions are required.
	 *
	 * - Handle deleting of a smiley set
	 * - Adding a new set
	 * - Modifying an existing set
	 */
	private function _subActionSubmit()
	{
		global $context, $modSettings;

		if (isset($this->_req->post->smiley_save) || isset($this->_req->post->delete_set))
		{
			// Security first
			checkSession();
			validateToken('admin-mss', 'request');

			// Delete selected smiley sets.
			if (!empty($this->_req->post->delete_set) && !empty($this->_req->post->smiley_set))
			{
				$set_paths = explode(',', $modSettings['smiley_sets_known']);
				$set_names = explode("\n", $modSettings['smiley_sets_names']);
				foreach ($this->_req->post->smiley_set as $id => $val)
				{
					if (isset($set_paths[$id], $set_names[$id]) && !empty($id))
					{
						unset($set_paths[$id], $set_names[$id]);
					}
				}

				// Update the modsettings with the new values
				updateSettings(array(
					'smiley_sets_known' => implode(',', $set_paths),
					'smiley_sets_names' => implode("\n", $set_names),
					'smiley_sets_default' => in_array($modSettings['smiley_sets_default'], $set_paths) ? $modSettings['smiley_sets_default'] : $set_paths[0],
				));
			}
			// Add a new smiley set.
			elseif (!empty($this->_req->post->add))
			{
				$context['sub_action'] = 'modifyset';
			}
			// Create or modify a smiley set.
			elseif (isset($this->_req->post->set))
			{
				$set = $this->_req->getPost('set', 'intval', 0);

				$set_paths = explode(',', $modSettings['smiley_sets_known']);
				$set_names = explode("\n", $modSettings['smiley_sets_names']);

				// Create a new smiley set.
				if ($set == -1 && isset($this->_req->post->smiley_sets_path))
				{
					if (in_array($this->_req->post->smiley_sets_path, $set_paths))
					{
						throw new Exception('smiley_set_already_exists');
					}

					updateSettings(array(
						'smiley_sets_known' => $modSettings['smiley_sets_known'] . ',' . $this->_req->post->smiley_sets_path,
						'smiley_sets_names' => $modSettings['smiley_sets_names'] . "\n" . $this->_req->post->smiley_sets_name,
						'smiley_sets_default' => empty($this->_req->post->smiley_sets_default) ? $modSettings['smiley_sets_default'] : $this->_req->post->smiley_sets_path,
					));
				}
				// Modify an existing smiley set.
				else
				{
					// Make sure the smiley set exists.
					if (!isset($set_paths[$set]) || !isset($set_names[$set]))
					{
						throw new Exception('smiley_set_not_found');
					}

					// Make sure the path is not yet used by another smileyset.
					if (in_array($this->_req->post->smiley_sets_path, $set_paths) && $this->_req->post->smiley_sets_path != $set_paths[$set])
					{
						throw new Exception('smiley_set_path_already_used');
					}

					$set_paths[$set] = $this->_req->post->smiley_sets_path;
					$set_names[$set] = $this->_req->post->smiley_sets_name;
					updateSettings(array(
						'smiley_sets_known' => implode(',', $set_paths),
						'smiley_sets_names' => implode("\n", $set_names),
						'smiley_sets_default' => empty($this->_req->post->smiley_sets_default) ? $modSettings['smiley_sets_default'] : $this->_req->post->smiley_sets_path
					));
				}

				// The user might have checked to also import smileys.
				if (!empty($this->_req->post->smiley_sets_import))
				{
					$this->importSmileys($this->_req->post->smiley_sets_path);
				}
			}

			// No matter what, reset the cache
			$this->clearSmileyCache();
		}
	}

	/**
	 * A function to import new smileys from an existing directory into the database.
	 *
	 * @param string $smileyPath
	 *
	 * @throws \ElkArte\Exceptions\Exception smiley_set_unable_to_import
	 */
	public function importSmileys($smileyPath)
	{
		global $modSettings;

		require_once(SUBSDIR . '/Smileys.subs.php');

		if (empty($modSettings['smileys_dir']) || !FileFunctions::instance()->isDir($modSettings['smileys_dir'] . '/' . $smileyPath))
		{
			throw new Exception('smiley_set_unable_to_import');
		}

		$smileys = array();
		$dir = dir($modSettings['smileys_dir'] . '/' . $smileyPath);
		while (($entry = $dir->read()))
		{
			if (in_array(strrchr($entry, '.'), array('.jpg', '.gif', '.jpeg', '.png', '.webp')))
			{
				$smileys[strtolower($entry)] = $entry;
			}
		}
		$dir->close();

		// Exclude the smileys that are already in the database.
		$duplicates = smileyExists($smileys);

		foreach ($duplicates as $duplicate)
		{
			if (isset($smileys[strtolower($duplicate)]))
			{
				unset($smileys[strtolower($duplicate)]);
			}
		}

		$smiley_order = getMaxSmileyOrder();

		$new_smileys = array();
		foreach ($smileys as $smiley)
		{
			if (strlen($smiley) <= 48)
			{
				$new_smileys[] = array(':' . strtok($smiley, '.') . ':', $smiley, strtok($smiley, '.'), 0, ++$smiley_order);
			}
		}

		if (!empty($new_smileys))
		{
			addSmiley($new_smileys);

			$this->clearSmileyCache();
		}
	}

	/**
	 * Importing smileys from an existing smiley set
	 */
	private function _subActionImport()
	{
		global $context;

		// Importing any smileys from an existing set?
		if ($context['sub_action'] === 'import')
		{
			checkSession('get');
			validateToken('admin-mss', 'request');

			$set = (int) $this->_req->query->set;

			// Sanity check - then import.
			if (isset($context['smiley_sets'][$set]))
			{
				$this->importSmileys(un_htmlspecialchars($context['smiley_sets'][$set]['path']));
			}

			// Force the process to continue.
			$context['sub_action'] = 'modifyset';
			$context['sub_template'] = 'modifyset';
		}
	}

	/**
	 * If we're modifying or adding a smileyset, or if we imported from another
	 * set, then some context info needs to be set.
	 *
	 * @throws \ElkArte\Exceptions\Exception in superfluity
	 */
	private function _subActionModifySet()
	{
		global $context, $txt, $modSettings;

		$fileFunc = FileFunctions::instance();

		if ($context['sub_action'] === 'modifyset')
		{
			$set = $this->_req->getQuery('set', 'intval', -1);
			if ($set == -1 || !isset($context['smiley_sets'][$set]))
			{
				$context['current_set'] = array(
					'id' => '-1',
					'path' => '',
					'name' => '',
					'selected' => false,
					'is_new' => true,
				);
			}
			else
			{
				$context['current_set'] = &$context['smiley_sets'][$set];
				$context['current_set']['is_new'] = false;

				// Calculate whether there are any smileys in the directory that can be imported.
				if (!empty($modSettings['smiley_enable']) && !empty($modSettings['smileys_dir'])
					&& $fileFunc->isDir($modSettings['smileys_dir'] . '/' . $context['current_set']['path']))
				{
					$smileys = array();
					$dir = dir($modSettings['smileys_dir'] . '/' . $context['current_set']['path']);
					while (($entry = $dir->read()) !== false)
					{
						if (in_array(strrchr($entry, '.'), array('.jpg', '.gif', '.jpeg', '.png', '.webp')))
						{
							$smileys[strtolower($entry)] = $entry;
						}
					}
					$dir->close();

					if (empty($smileys))
					{
						throw new Exception('smiley_set_dir_not_found', false, array($context['current_set']['name']));
					}

					// Exclude the smileys that are already in the database.
					$found = smileyExists($smileys);
					foreach ($found as $smiley)
					{
						if (isset($smileys[$smiley]))
						{
							unset($smileys[$smiley]);
						}
					}

					$context['current_set']['can_import'] = count($smileys);

					// Setup this string to look nice.
					$txt['smiley_set_import_multiple'] = sprintf($txt['smiley_set_import_multiple'], $context['current_set']['can_import']);
				}
			}

			// Retrieve all potential smiley set directories.
			$context['smiley_set_dirs'] = array();
			if (!empty($modSettings['smileys_dir']) && $fileFunc->isDir($modSettings['smileys_dir']))
			{
				// Do not include our emoji directories
				$disallow = ['.', '..', 'emojitwo', 'twemoji', 'noto-emoji'];

				$dir = dir($modSettings['smileys_dir']);
				while (($entry = $dir->read()) !== false)
				{
					if (!in_array($entry, $disallow)
						&& $fileFunc->isDir($modSettings['smileys_dir'] . '/' . $entry))
					{
						$context['smiley_set_dirs'][] = array(
							'id' => $entry,
							'path' => $modSettings['smileys_dir'] . '/' . $entry,
							'selectable' => $entry == $context['current_set']['path'] || !in_array($entry, explode(',', $modSettings['smiley_sets_known'])),
							'current' => $entry == $context['current_set']['path'],
						);
					}
				}
				$dir->close();
			}
		}
	}

	/**
	 * Add a smiley, that's right.
	 */
	public function action_addsmiley()
	{
		global $modSettings, $context, $txt;

		$fileFunc = FileFunctions::instance();
		require_once(SUBSDIR . '/Smileys.subs.php');

		// Get a list of all known smiley sets.
		$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? BOARDDIR . '/smileys' : $modSettings['smileys_dir'];
		$context['smileys_dir_found'] = $fileFunc->isDir($context['smileys_dir']);
		$context['sub_template'] = 'addsmiley';

		$this->loadSmileySets();

		// Submitting a form?
		if (isset($this->_req->post->{$context['session_var']}, $this->_req->post->smiley_code))
		{
			checkSession();

			// Some useful arrays... types we allow - and ports we don't!
			$allowedTypes = array('jpeg', 'jpg', 'gif', 'png', 'bmp', 'webp');
			$disabledFiles = array('con', 'com1', 'com2', 'com3', 'com4', 'prn', 'aux', 'lpt1', '.htaccess', 'index.php');

			$this->_req->post->smiley_code = $this->_req->getPost('smiley_code', '\\ElkArte\\Util::htmltrim', '');
			$this->_req->post->smiley_filename = $this->_req->getPost('smiley_filename', '\\ElkArte\\Util::htmltrim', '');
			$this->_req->post->smiley_location = $this->_req->getPost('smiley_location', 'intval', 0);
			$this->_req->post->smiley_location = min(max($this->_req->post->smiley_location, 0), 2);

			// Make sure some code was entered.
			if (empty($this->_req->post->smiley_code))
			{
				throw new Exception('smiley_has_no_code');
			}

			// Check whether the new code has duplicates. It should be unique.
			if (validateDuplicateSmiley($this->_req->post->smiley_code))
			{
				throw new Exception('smiley_not_unique');
			}

			// If we are uploading - check all the smiley sets are writable!
			if ($this->_req->post->method !== 'existing')
			{
				$writeErrors = array();
				foreach ($context['smiley_sets'] as $set)
				{
					if (!$fileFunc->isWritable($context['smileys_dir'] . '/' . un_htmlspecialchars($set['path'])))
					{
						$writeErrors[] = $set['path'];
					}
				}

				if (!empty($writeErrors))
				{
					throw new Exception('smileys_upload_error_notwritable', true, array(implode(', ', $writeErrors)));
				}
			}

			// Uploading just one smiley for all of them?
			if (isset($this->_req->post->sameall, $_FILES['uploadSmiley']['name']) && $_FILES['uploadSmiley']['name'] != '')
			{
				if (!is_uploaded_file($_FILES['uploadSmiley']['tmp_name'])
					|| (ini_get('open_basedir') === '' && !$fileFunc->fileExists($_FILES['uploadSmiley']['tmp_name'])))
				{
					throw new Exception('smileys_upload_error');
				}

				// Sorry, no spaces, dots, or anything else but letters allowed.
				$_FILES['uploadSmiley']['name'] = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $_FILES['uploadSmiley']['name']);

				// We only allow image files - it's THAT simple - no messing around here...
				if (!in_array(strtolower(substr(strrchr($_FILES['uploadSmiley']['name'], '.'), 1)), $allowedTypes))
				{
					throw new Exception('smileys_upload_error_types', false, array(implode(', ', $allowedTypes)));
				}

				// We only need the filename...
				$destName = basename($_FILES['uploadSmiley']['name']);

				// Make sure they aren't trying to upload a nasty file - for their own good here!
				if (in_array(strtolower($destName), $disabledFiles))
				{
					throw new Exception('smileys_upload_error_illegal');
				}

				// Check if the file already exists... and if not move it to EVERY smiley set directory.
				$i = 0;

				// Keep going until we find a set the file doesn't exist in. (or maybe it exists in all of them?)
				while (isset($context['smiley_sets'][$i])
					&& $fileFunc->fileExists($context['smileys_dir'] . '/' . un_htmlspecialchars($context['smiley_sets'][$i]['path']) . '/' . $destName))
				{
					$i++;
				}

				// Okay, we're going to put the smiley right here, since it's not there yet!
				if (isset($context['smiley_sets'][$i]['path']))
				{
					$smileyLocation = $context['smileys_dir'] . '/' . un_htmlspecialchars($context['smiley_sets'][$i]['path']) . '/' . $destName;
					move_uploaded_file($_FILES['uploadSmiley']['tmp_name'], $smileyLocation);
					$fileFunc->chmod($smileyLocation);

					// Now, we want to move it from there to all the other sets.
					for ($n = count($context['smiley_sets']); $i < $n; $i++)
					{
						$currentPath = $context['smileys_dir'] . '/' . un_htmlspecialchars($context['smiley_sets'][$i]['path']) . '/' . $destName;

						// The file is already there!  Don't overwrite it!
						if ($fileFunc->fileExists($currentPath))
						{
							continue;
						}

						// Okay, so copy the first one we made to here.
						copy($smileyLocation, $currentPath);
						$fileFunc->chmod($currentPath);
					}
				}

				// Finally, make sure it's saved correctly!
				$this->_req->post->smiley_filename = $destName;
			}
			// What about uploading several files?
			elseif ($this->_req->post->method !== 'existing')
			{
				$newName = '';
				foreach ($_FILES as $file)
				{
					if ($file['name'] === '')
					{
						throw new Exception('smileys_upload_error_blank');
					}

					if (empty($newName))
					{
						$newName = basename($file['name']);
					}
					elseif (basename($file['name']) !== $newName)
					{
						throw new Exception('smileys_upload_error_name');
					}
				}

				foreach ($context['smiley_sets'] as $i => $set)
				{
					$set['name'] = un_htmlspecialchars($set['name']);
					$set['path'] = un_htmlspecialchars($set['path']);

					if (!isset($_FILES['individual_' . $set['name']]['name']) || $_FILES['individual_' . $set['name']]['name'] === '')
					{
						continue;
					}

					// Got one...
					if (!is_uploaded_file($_FILES['individual_' . $set['name']]['tmp_name']) || (ini_get('open_basedir') === ''
						&& !$fileFunc->fileExists($_FILES['individual_' . $set['name']]['tmp_name'])))
					{
						throw new Exception('smileys_upload_error');
					}

					// Sorry, no spaces, dots, or anything else but letters allowed.
					$_FILES['individual_' . $set['name']]['name'] = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $_FILES['individual_' . $set['name']]['name']);

					// We only allow image files - it's THAT simple - no messing around here...
					if (!in_array(strtolower(substr(strrchr($_FILES['individual_' . $set['name']]['name'], '.'), 1)), $allowedTypes))
					{
						throw new Exception('smileys_upload_error_types', false, array(implode(', ', $allowedTypes)));
					}

					// We only need the filename...
					$destName = basename($_FILES['individual_' . $set['name']]['name']);

					// Make sure they aren't trying to upload a nasty file - for their own good here!
					if (in_array(strtolower($destName), $disabledFiles))
					{
						throw new Exception('smileys_upload_error_illegal');
					}

					// If the file exists - ignore it.
					$smileyLocation = $context['smileys_dir'] . '/' . $set['path'] . '/' . $destName;
					if ($fileFunc-fileExists($smileyLocation))
					{
						continue;
					}

					// Finally - move the image!
					move_uploaded_file($_FILES['individual_' . $set['name']]['tmp_name'], $smileyLocation);
					$fileFunc->chmod($smileyLocation);

					// Should always be saved correctly!
					$this->_req->post->smiley_filename = $destName;
				}
			}

			// Also make sure a filename was given.
			if (empty($this->_req->post->smiley_filename))
			{
				throw new Exception('smiley_has_no_filename');
			}

			// Find the position on the right.
			$smiley_order = '0';
			if ($this->_req->post->smiley_location != 1)
			{
				$this->_req->post->smiley_location = (int) $this->_req->post->smiley_location;
				$smiley_order = nextSmileyLocation($this->_req->post->smiley_location);

				if (empty($smiley_order))
				{
					$smiley_order = '0';
				}
			}
			$param = array(
				$this->_req->post->smiley_code,
				$this->_req->post->smiley_filename,
				$this->_req->post->smiley_description,
				(int) $this->_req->post->smiley_location,
				$smiley_order,
			);
			addSmiley($param);

			$this->clearSmileyCache();

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
				if (!$fileFunc->isDir($context['smileys_dir'] . '/' . un_htmlspecialchars($smiley_set['path'])))
				{
					continue;
				}

				$dir = dir($context['smileys_dir'] . '/' . un_htmlspecialchars($smiley_set['path']));
				while (($entry = $dir->read()))
				{
					if (!in_array($entry, $context['filenames'])
						&& in_array(strrchr($entry, '.'), array('.jpg', '.gif', '.jpeg', '.png', '.webp')))
					{
						$context['filenames'][strtolower($entry)] = array(
							'id' => htmlspecialchars($entry, ENT_COMPAT, 'UTF-8'),
							'selected' => false,
						);
					}
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
	 *
	 * @event integrate_list_smiley_list
	 */
	public function action_editsmiley()
	{
		global $modSettings, $context, $txt;

		$fileFunc = FileFunctions::instance();
		require_once(SUBSDIR . '/Smileys.subs.php');

		// Force the correct tab to be displayed.
		$context[$context['admin_menu_name']]['current_subsection'] = 'editsmileys';
		$context['sub_template'] = $context['sub_action'];

		// Submitting a form?
		if (isset($this->_req->post->smiley_save) || isset($this->_req->post->smiley_action))
		{
			checkSession();

			// Changing the selected smileys?
			if (isset($this->_req->post->smiley_action) && !empty($this->_req->post->checked_smileys))
			{
				foreach ($this->_req->post->checked_smileys as $id => $smiley_id)
				{
					$this->_req->post->checked_smileys[$id] = (int) $smiley_id;
				}

				if ($this->_req->post->smiley_action === 'delete')
				{
					deleteSmileys($this->_req->post->checked_smileys);
				}
				// Changing the status of the smiley?
				else
				{
					// Check it's a valid type.
					$displayTypes = array(
						'post' => 0,
						'hidden' => 1,
						'popup' => 2
					);
					if (isset($displayTypes[$this->_req->post->smiley_action]))
					{
						updateSmileyDisplayType($this->_req->post->checked_smileys, $displayTypes[$this->_req->post->smiley_action]);
					}
				}
			}
			// Create/modify a smiley.
			elseif (isset($this->_req->post->smiley))
			{
				$this->_req->post->smiley = (int) $this->_req->post->smiley;

				// Is it a delete?
				if (!empty($this->_req->post->deletesmiley))
				{
					deleteSmileys(array($this->_req->post->smiley));
				}
				// Otherwise an edit.
				else
				{
					$this->_req->post->smiley_code = $this->_req->getPost('smiley_code', '\\ElkArte\\Util::htmltrim', '');
					$this->_req->post->smiley_filename = $this->_req->getPost('smiley_filename', '\\ElkArte\\Util::htmltrim', '');
					$this->_req->post->smiley_location = empty($this->_req->post->smiley_location)
						|| $this->_req->post->smiley_location > 2
						|| $this->_req->post->smiley_location < 0 ? 0 : (int) $this->_req->post->smiley_location;

					// Make sure some code was entered.
					if (empty($this->_req->post->smiley_code))
					{
						throw new Exception('smiley_has_no_code');
					}

					// Also make sure a filename was given.
					if (empty($this->_req->post->smiley_filename))
					{
						throw new Exception('smiley_has_no_filename');
					}

					// Check whether the new code has duplicates. It should be unique.
					if (validateDuplicateSmiley($this->_req->post->smiley_code, $this->_req->post->smiley))
					{
						throw new Exception('smiley_not_unique');
					}

					$param = array(
						'smiley_location' => $this->_req->post->smiley_location,
						'smiley' => $this->_req->post->smiley,
						'smiley_code' => $this->_req->post->smiley_code,
						'smiley_filename' => $this->_req->post->smiley_filename,
						'smiley_description' => $this->_req->post->smiley_description,
					);
					updateSmiley($param);
				}
			}

			$this->clearSmileyCache();
		}

		// Load all known smiley sets.
		$this->loadSmileySets();

		// Prepare overview of all (custom) smileys.
		if ($context['sub_action'] === 'editsmileys')
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
			{
				$smileyset_option_list .= '
					<option value="' . $smiley_set['path'] . '"' . ($modSettings['smiley_sets_default'] === $smiley_set['path'] ? ' selected="selected"' : '') . '>' . $smiley_set['name'] . '</option>';
			}
			$smileyset_option_list .= '
				</select>';

			$listOptions = array(
				'id' => 'smiley_list',
				'title' => $txt['smileys_edit'],
				'items_per_page' => 40,
				'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editsmileys']),
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
								'format' => '
								<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'modifysmiley', 'smiley' => '']) . '%1$d">
									<img src="' . $modSettings['smileys_url'] . '/' . $modSettings['smiley_sets_default'] . '/%2$s" alt="%3$s" id="smiley%1$d" />
									<input type="hidden" name="smileys[%1$d][filename]" value="%2$s" />
								</a>',
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
							'function' => function ($rowData) use ($smiley_locations) {
								return $smiley_locations[$rowData['hidden']];
							},
						),
						'sort' => array(
							'default' => 'hidden',
							'reverse' => 'hidden DESC',
						),
					),
					'tooltip' => array(
						'header' => array(
							'value' => $txt['smileys_description'],
						),
						'data' => array(
							'function' => function ($rowData) use ($fileFunc) {
								global $context, $txt, $modSettings;

								if (empty($modSettings['smileys_dir']) || !$fileFunc->isDir($modSettings['smileys_dir']))
								{
									return htmlspecialchars($rowData['description'], ENT_COMPAT, 'UTF-8');
								}

								// Check if there are smileys missing in some sets.
								$missing_sets = array();
								foreach ($context['smiley_sets'] as $smiley_set)
								{
									if (!$fileFunc->fileExists(sprintf('%1$s/%2$s/%3$s', $modSettings['smileys_dir'], $smiley_set['path'], $rowData['filename'])))
									{
										$missing_sets[] = $smiley_set['path'];
									}
								}

								$description = htmlspecialchars($rowData['description'], ENT_COMPAT, 'UTF-8');

								if (!empty($missing_sets))
								{
									$description .= sprintf('<br /><span class="smalltext"><strong>%1$s:</strong> %2$s</span>', $txt['smileys_not_found_in_set'], implode(', ', $missing_sets));
								}

								return $description;
							},
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
								'format' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'modifysmiley', 'smiley' => '']) . '%1$d">' . $txt['smileys_modify'] . '</a>',
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
					'href' => getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editsmileys']),
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

			createList($listOptions);

			// The list is the only thing to show, so make it the main template.
			$context['default_list'] = 'smiley_list';
			$context['sub_template'] = 'show_list';
		}
		// Modifying smileys.
		elseif ($context['sub_action'] === 'modifysmiley')
		{
			// Get a list of all known smiley sets.
			$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? BOARDDIR . '/smileys' : $modSettings['smileys_dir'];
			$context['smileys_dir_found'] = $fileFunc->isDir($context['smileys_dir']);
			$context['smiley_sets'] = explode(',', $modSettings['smiley_sets_known']);

			$set_names = explode("\n", $modSettings['smiley_sets_names']);
			foreach ($context['smiley_sets'] as $i => $set)
			{
				$context['smiley_sets'][$i] = array(
					'id' => $i,
					'path' => htmlspecialchars($set, ENT_COMPAT, 'UTF-8'),
					'name' => htmlspecialchars(stripslashes($set_names[$i]), ENT_COMPAT, 'UTF-8'),
					'selected' => $set === $modSettings['smiley_sets_default']
				);
			}

			$context['selected_set'] = $modSettings['smiley_sets_default'];

			// Get all possible filenames for the smileys.
			$context['filenames'] = array();
			if ($context['smileys_dir_found'])
			{
				foreach ($context['smiley_sets'] as $smiley_set)
				{
					if (!$fileFunc->fileExists($context['smileys_dir'] . '/' . un_htmlspecialchars($smiley_set['path'])))
					{
						continue;
					}

					$dir = dir($context['smileys_dir'] . '/' . un_htmlspecialchars($smiley_set['path']));
					while (($entry = $dir->read()))
					{
						if (!in_array($entry, $context['filenames']) && in_array(strrchr($entry, '.'), array('.jpg', '.gif', '.jpeg', '.png', '.webp')))
						{
							$context['filenames'][strtolower($entry)] = array(
								'id' => htmlspecialchars($entry, ENT_COMPAT, 'UTF-8'),
								'selected' => false,
							);
						}
					}
					$dir->close();
				}
				ksort($context['filenames']);
			}

			$thisSmiley = (int) $this->_req->query->smiley;
			$context['current_smiley'] = getSmiley($thisSmiley);
			$context['current_smiley']['code'] = htmlspecialchars($context['current_smiley']['code'], ENT_COMPAT, 'UTF-8');
			$context['current_smiley']['filename'] = htmlspecialchars($context['current_smiley']['filename'], ENT_COMPAT, 'UTF-8');
			$context['current_smiley']['description'] = htmlspecialchars($context['current_smiley']['description'], ENT_COMPAT, 'UTF-8');

			if (isset($context['filenames'][strtolower($context['current_smiley']['filename'])]))
			{
				$context['filenames'][strtolower($context['current_smiley']['filename'])]['selected'] = true;
			}
		}
	}

	/**
	 * Allows to edit the message icons.
	 *
	 * @event integrate_list_message_icon_list
	 */
	public function action_editicon()
	{
		global $context, $settings, $txt;

		$fileFunc = FileFunctions::instance();
		require_once(SUBSDIR . '/MessageIcons.subs.php');

		// Get a list of icons.
		$context['icons'] = fetchMessageIconsDetails();

		// Submitting a form?
		if (isset($this->_req->post->icons_save))
		{
			checkSession();

			// Deleting icons?
			if (isset($this->_req->post->delete) && !empty($this->_req->post->checked_icons))
			{
				$deleteIcons = array();
				foreach ($this->_req->post->checked_icons as $icon)
				{
					$deleteIcons[] = (int) $icon;
				}

				// Do the actual delete!
				deleteMessageIcons($deleteIcons);
			}
			// Editing/Adding an icon?
			elseif ($context['sub_action'] === 'editicon' && isset($this->_req->query->icon))
			{
				$this->_req->query->icon = (int) $this->_req->query->icon;

				// Do some preparation with the data... like check the icon exists *somewhere*
				if (strpos($this->_req->post->icon_filename, '.png') !== false)
				{
					$this->_req->post->icon_filename = substr($this->_req->post->icon_filename, 0, -4);
				}

				if (!$fileFunc->fileExists($settings['default_theme_dir'] . '/images/post/' . $this->_req->post->icon_filename . '.png'))
				{
					throw new Exception('icon_not_found');
				}
				// There is a 16 character limit on message icons...
				elseif (strlen($this->_req->post->icon_filename) > 16)
				{
					throw new Exception('icon_name_too_long');
				}
				elseif ($this->_req->post->icon_location == $this->_req->query->icon && !empty($this->_req->query->icon))
				{
					throw new Exception('icon_after_itself');
				}

				// First do the sorting... if this is an edit reduce the order of everything after it by one ;)
				if ($this->_req->query->icon != 0)
				{
					$oldOrder = $context['icons'][$this->_req->query->icon]['true_order'];
					foreach ($context['icons'] as $id => $data)
					{
						if ($data['true_order'] > $oldOrder)
						{
							$context['icons'][$id]['true_order']--;
						}
					}
				}

				// If there are no existing icons and this is a new one, set the id to 1 (mainly for non-mysql)
				if (empty($this->_req->query->icon) && empty($context['icons']))
				{
					$this->_req->query->icon = 1;
				}

				// Get the new order.
				$newOrder = $this->_req->post->icon_location == 0 ? 0 : $context['icons'][$this->_req->post->icon_location]['true_order'] + 1;

				// Do the same, but with the one that used to be after this icon, done to avoid conflict.
				foreach ($context['icons'] as $id => $data)
				{
					if ($data['true_order'] >= $newOrder)
					{
						$context['icons'][$id]['true_order']++;
					}
				}

				// Finally set the current icon's position!
				$context['icons'][$this->_req->query->icon]['true_order'] = $newOrder;

				// Simply replace the existing data for the other bits.
				$context['icons'][$this->_req->query->icon]['title'] = $this->_req->post->icon_description;
				$context['icons'][$this->_req->query->icon]['filename'] = $this->_req->post->icon_filename;
				$context['icons'][$this->_req->query->icon]['board_id'] = (int) $this->_req->post->icon_board;

				// Do a huge replace ;)
				$iconInsert = array();
				$iconInsert_new = array();
				foreach ($context['icons'] as $id => $icon)
				{
					if ($id != 0)
					{
						$iconInsert[] = array($id, $icon['board_id'], $icon['title'], $icon['filename'], $icon['true_order']);
					}
					else
					{
						$iconInsert_new[] = array($icon['board_id'], $icon['title'], $icon['filename'], $icon['true_order']);
					}
				}

				updateMessageIcon($iconInsert);

				if (!empty($iconInsert_new))
				{
					addMessageIcon($iconInsert_new);

				// Flush the cache so the changes are reflected
				Cache::instance()->remove('posting_icons-' . (int) $this->_req->post->icon_board);
				}
			}

			// Unless we're adding a new thing, we'll escape
			if (!isset($this->_req->post->add))
			{
				redirectexit('action=admin;area=smileys;sa=editicons');
			}
		}

		$context[$context['admin_menu_name']]['current_subsection'] = 'editicons';
		$token = createToken('admin-sort');
		$listOptions = array(
			'id' => 'message_icon_list',
			'title' => $txt['icons_edit_message_icons'],
			'sortable' => true,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editicons']),
			'get_items' => array(
				'function' => function () {
					return $this->list_fetchMessageIconsDetails();
				},
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
							'format' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editicon', 'icon' => '']) . '%1$s">' . $txt['smileys_modify'] . '</a>',
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
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editicons']),
				'hidden_fields' => array(
					'icons_save' => 1,
				)
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'class' => 'submitbutton',
					'value' => '
						<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editicon']) . '">' . $txt['icons_add_new'] . '</a>
						<input type="submit" name="delete" value="' . $txt['quickmod_delete_selected'] . '" onclick="return confirm(\'' . $txt['icons_confirm'] . '\');" />',
				),
				array(
					'position' => 'after_title',
					'value' => $txt['icons_reorder_note'],
				),
			),
			'javascript' => '
				$().elkSortable({
					sa: "messageiconorder",
					error: "' . $txt['admin_order_error'] . '",
					title: "' . $txt['admin_order_title'] . '",
					placeholder: "ui-state-highlight",
					href: "?action=admin;area=smileys;sa=editicons",
					token: {token_var: "' . $token['admin-sort_token_var'] . '", token_id: "' . $token['admin-sort_token'] . '"}
				});
			',
		);

		createList($listOptions);

		// If we're adding/editing an icon we'll need a list of boards
		if ($context['sub_action'] === 'editicon' || isset($this->_req->post->add))
		{
			// Force the sub_template just in case.
			$context['sub_template'] = 'editicon';
			$context['new_icon'] = !isset($this->_req->query->icon);

			// Get the properties of the current icon from the icon list.
			if (!$context['new_icon'])
			{
				$context['icon'] = $context['icons'][$this->_req->query->icon];
			}

			// Get a list of boards needed for assigning this icon to a specific board.
			$boardListOptions = array(
				'selected_board' => $context['icon']['board_id'] ?? 0,
			);
			require_once(SUBSDIR . '/Boards.subs.php');
			$context += getBoardList($boardListOptions);
		}
	}

	/**
	 * Callback function for createList().
	 */
	public function list_fetchMessageIconsDetails()
	{
		return fetchMessageIconsDetails();
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
		if (isset($this->_req->query->reorder))
		{
			checkSession('get');

			$this->_req->query->location = empty($this->_req->query->location) || $this->_req->query->location !== 'popup' ? 0 : 2;
			$this->_req->query->source = empty($this->_req->query->source) ? 0 : (int) $this->_req->query->source;

			if (empty($this->_req->query->source))
			{
				throw new Exception('smiley_not_found');
			}

			$smiley = array();

			if (!empty($this->_req->query->after))
			{
				$this->_req->query->after = (int) $this->_req->query->after;

				$smiley = getSmileyPosition($this->_req->query->location, $this->_req->query->after);
				if (empty($smiley))
				{
					throw new Exception('smiley_not_found');
				}
			}
			else
			{
				$smiley['row'] = (int) $this->_req->query->row;
				$smiley['order'] = -1;
				$smiley['location'] = (int) $this->_req->query->location;
			}

			moveSmileyPosition($smiley, $this->_req->query->source);
		}

		$context['smileys'] = getSmileys();
		$context['move_smiley'] = empty($this->_req->query->move) ? 0 : (int) $this->_req->query->move;

		// Make sure all rows are sequential.
		foreach (array_keys($context['smileys']) as $location)
		{
			$context['smileys'][$location] = array(
				'id' => $location,
				'title' => $location === 'postform' ? $txt['smileys_location_form'] : $txt['smileys_location_popup'],
				'description' => $location === 'postform' ? $txt['smileys_location_form_description'] : $txt['smileys_location_popup_description'],
				'last_row' => count($context['smileys'][$location]['rows']),
				'rows' => array_values($context['smileys'][$location]['rows']),
			);
		}

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
				{
					if ($order_id != $smiley['order'])
					{
						updateSmileyOrder($smiley['id'], $order_id);
					}
				}
			}
		}

		$this->clearSmileyCache();

		createToken('admin-sort');
	}

	/**
	 * Install a smiley set.
	 */
	public function action_install()
	{
		global $modSettings, $context, $txt, $scripturl;

		isAllowedTo('manage_smileys');
		checkSession('request');

		// One of these two may be necessary
		Txt::load('Errors');
		Txt::load('Packages');

		$fileFunc = FileFunctions::instance();
		require_once(SUBSDIR . '/Smileys.subs.php');
		require_once(SUBSDIR . '/Package.subs.php');

		// Installing unless proven otherwise
		$testing = false;
		$destination = '';
		$name = '';
		$base_name = '';

		if (isset($this->_req->query->set_gz))
		{
			$base_name = strtr(basename($this->_req->query->set_gz), ':/', '-_');
			$name = Util::htmlspecialchars(strtok(basename($this->_req->query->set_gz), '.'));
			$context['filename'] = $base_name;

			// Check that the smiley is from simplemachines.org, for now... maybe add mirroring later.
			if (!isAuthorizedServer($this->_req->query->set_gz) == 0)
			{
				throw new Exception('not_valid_server');
			}

			$destination = BOARDDIR . '/packages/' . $base_name;

			if ($fileFunc->fileExists($destination))
			{
				throw new Exception('package_upload_error_exists');
			}

			// Let's copy it to the packages directory
			file_put_contents($destination, fetch_web_data($this->_req->query->set_gz));
			$testing = true;
		}
		elseif (isset($this->_req->query->package))
		{
			$base_name = basename($this->_req->query->package);
			$name = Util::htmlspecialchars(strtok(basename($this->_req->query->package), '.'));
			$context['filename'] = $base_name;

			$destination = BOARDDIR . '/packages/' . basename($this->_req->query->package);
		}

		if (!$fileFunc->fileExists($destination))
		{
			throw new Exception('package_no_file', false);
		}

		// Make sure temp directory exists and is empty.
		if ($fileFunc->fileExists(BOARDDIR . '/packages/temp'))
		{
			deltree(BOARDDIR . '/packages/temp', false);
		}

		if (!mktree(BOARDDIR . '/packages/temp'))
		{
			deltree(BOARDDIR . '/packages/temp', false);
			$chmod_control = new PackageChmod();
			$chmod_control->createChmodControl(
				array(BOARDDIR . '/packages/temp/delme.tmp'),
				array(
					'destination_url' => $scripturl . '?action=admin;area=smileys;sa=install;set_gz=' . $this->_req->query->set_gz,
					'crash_on_error' => true
				)
			);

			deltree(BOARDDIR . '/packages/temp', false);
			throw new Exception('package_cant_download', false);
		}

		$extracted = read_tgz_file($destination, BOARDDIR . '/packages/temp');

		// @todo needs to change the URL in the next line ;)
		if (!$extracted)
		{
			throw new Exception('packageget_unable', false, array('http://custom.elkarte.net/index.php?action=search;type=12;basic_search=' . $name));
		}

		if ($extracted && !$fileFunc->fileExists(BOARDDIR . '/packages/temp/package-info.xml'))
		{
			foreach ($extracted as $file)
			{
				if (basename($file['filename']) === 'package-info.xml')
				{
					$base_path = dirname($file['filename']) . '/';
					break;
				}
			}
		}

		if (!isset($base_path))
		{
			$base_path = '';
		}

		if (!$fileFunc->fileExists(BOARDDIR . '/packages/temp/' . $base_path . 'package-info.xml'))
		{
			throw new Exception('package_get_error_missing_xml', false);
		}

		$smileyInfo = getPackageInfo($context['filename']);
		if (!is_array($smileyInfo))
		{
			throw new Exception($smileyInfo);
		}

		// See if it is installed?
		if (isSmileySetInstalled($smileyInfo['id']))
		{
			Errors::instance()->fatal_lang_error('package_installed_warning1');
		}

		// Everything is fine, now it's time to do something, first we test
		$actions = parsePackageInfo($smileyInfo['xml'], true, 'install');

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'install', 'package' => $base_name]);
		$context['has_failure'] = false;
		$context['actions'] = array();
		$context['ftp_needed'] = false;

		$bbc_parser = ParserWrapper::instance();

		foreach ($actions as $action)
		{
			if ($action['type'] === 'readme' || $action['type'] === 'license')
			{
				$type = 'package_' . $action['type'];
				if ($fileFunc->fileExists(BOARDDIR . '/packages/temp/' . $base_path . $action['filename']))
				{
					$context[$type] = htmlspecialchars(trim(file_get_contents(BOARDDIR . '/packages/temp/' . $base_path . $action['filename']), "\n\r"), ENT_COMPAT, 'UTF-8');
				}
				elseif ($fileFunc->fileExists($action['filename']))
				{
					$context[$type] = htmlspecialchars(trim(file_get_contents($action['filename']), "\n\r"), ENT_COMPAT, 'UTF-8');
				}

				if (!empty($action['parse_bbc']))
				{
					require_once(SUBSDIR . '/Post.subs.php');
					preparsecode($context[$type]);
					$context[$type] = $bbc_parser->parsePackage($context[$type]);
				}
				else
				{
					$context[$type] = nl2br($context[$type]);
				}
			}
			elseif ($action['type'] === 'require-dir')
			{
				// Do this one...
				$thisAction = array(
					'type' => $txt['package_extract'] . ' ' . ($action['type'] === 'require-dir' ? $txt['package_tree'] : $txt['package_file']),
					'action' => Util::htmlspecialchars(strtr($action['destination'], array(BOARDDIR => '.')))
				);

				$file = BOARDDIR . '/packages/temp/' . $base_path . $action['filename'];
				if (isset($action['filename']) && (!$fileFunc->fileExists($file) || !$fileFunc->isWritable(dirname($action['destination']))))
				{
					$context['has_failure'] = true;

					$thisAction += array(
						'description' => $txt['package_action_error'],
						'failed' => true,
					);
				}

				// Show a description for the action if one is provided
				if (empty($thisAction['description']))
				{
					$thisAction['description'] = $action['description'] ?? '';
				}

				$context['actions'][] = $thisAction;
			}
			elseif ($action['type'] === 'credits')
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
			theme()->getTemplates()->load('Packages');
		}
		// Do the actual install
		else
		{
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
				'id_member' => $this->user->id,
				'member_name' => $this->user->name,
				'credits_tag' => $credits_tag,
			);
			logPackageInstall($installed);

			logAction('install_package', array('package' => Util::htmlspecialchars($smileyInfo['name']), 'version' => Util::htmlspecialchars($smileyInfo['version'])), 'admin');

			$this->clearSmileyCache();
		}

		if ($fileFunc->fileExists(BOARDDIR . '/packages/temp'))
		{
			deltree(BOARDDIR . '/packages/temp');
		}

		if (!$testing)
		{
			redirectexit('action=admin;area=smileys');
		}
	}

	/**
	 * Load known smiley set information into context
	 *
	 * @return void
	 */
	public function loadSmileySets()
	{
		global $context, $modSettings;

		$context['smiley_sets'] = explode(',', $modSettings['smiley_sets_known']);
		$set_names = explode("\n", $modSettings['smiley_sets_names']);
		foreach ($context['smiley_sets'] as $i => $set)
		{
			$context['smiley_sets'][$i] = [
				'id' => $i,
				'path' => htmlspecialchars($set, ENT_COMPAT),
				'name' => htmlspecialchars(stripslashes($set_names[$i]), ENT_COMPAT),
				'selected' => $set === $modSettings['smiley_sets_default']
			];
		}
	}
}
