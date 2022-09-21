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
use ElkArte\Packages\PackageParser;
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\Languages\Txt;
use ElkArte\Util;

/**
 * This class is in charge with administration of smileys and message icons.
 * It handles actions from the Smileys pages in admin panel.
 */
class ManageSmileys extends AbstractController
{
	/** @var array Contextual information about smiley sets. */
	private $_smiley_context = [];

	/** @var string[] allowed extensions for smiles */
	private $_smiley_types =  ['jpg', 'gif', 'jpeg', 'png', 'webp', 'svg'];

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

		$subActions = [
			'addsmiley' => [$this, 'action_addsmiley', 'permission' => 'manage_smileys'],
			'editicon' => [$this, 'action_editicon', 'enabled' => !empty($modSettings['messageIcons_enable']), 'permission' => 'manage_smileys'],
			'editicons' => [$this, 'action_editicon', 'enabled' => !empty($modSettings['messageIcons_enable']), 'permission' => 'manage_smileys'],
			'editsets' => [$this, 'action_edit', 'permission' => 'admin_forum'],
			'editsmileys' => [$this, 'action_editsmiley', 'permission' => 'manage_smileys'],
			'import' => [$this, 'action_edit', 'permission' => 'manage_smileys'],
			'modifyset' => [$this, 'action_edit', 'permission' => 'manage_smileys'],
			'modifysmiley' => [$this, 'action_editsmiley', 'permission' => 'manage_smileys'],
			'setorder' => [$this, 'action_setorder', 'permission' => 'manage_smileys'],
			'settings' => [$this, 'action_smileySettings_display', 'permission' => 'manage_smileys'],
			'install' => [$this, 'action_install', 'permission' => 'manage_smileys']
		];

		// Action controller
		$action = new Action('manage_smileys');

		// Set the smiley context.
		$this->_initSmileyContext();

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = [
			'title' => $txt['smileys_manage'],
			'help' => 'smileys',
			'description' => $txt['smiley_settings_explain'],
			'tabs' => [
				'editsets' => [
					'description' => $txt['smiley_editsets_explain'],
				],
				'addsmiley' => [
					'description' => $txt['smiley_addsmiley_explain'],
				],
				'editsmileys' => [
					'description' => $txt['smiley_editsmileys_explain'],
				],
				'setorder' => [
					'description' => $txt['smiley_setorder_explain'],
				],
				'editicons' => [
					'description' => $txt['icons_edit_icons_explain'],
				],
				'settings' => [
					'description' => $txt['smiley_settings_explain'],
				],
			],
		];

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
		$set_paths = explode(',', $modSettings['smiley_sets_known']);
		$set_names = explode("\n", $modSettings['smiley_sets_names']);

		foreach ($set_paths as $i => $set)
		{
			$this->_smiley_context[$set] = $set_names[$i];
		}
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
		$config_vars = [
			['title', 'settings'],
			['select', 'smiley_sets_default', $this->_smiley_context],
			['text', 'smileys_url', 40],
			['text', 'smileys_dir', 'invalid' => !$context['smileys_dir_found'], 40],
			'',
			// Message icons.
			['check', 'messageIcons_enable', 'subtext' => $txt['setting_messageIcons_enable_note']],
			// Inline permissions.
			'',
			['permissions', 'manage_smileys'],
		];

		call_integration_hook('integrate_modify_smiley_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Clear the cache to avoid changes not immediately appearing
	 */
	protected function clearSmileyCache()
	{
		Cache::instance()->remove('parsing_smileys');
		Cache::instance()->remove('posting_smileys');

		$this->createCustomTagsFile();
	}

	/**
	 * For any :emoji: codes in the current set, that have an image in the smile set directory,
	 * write the information to custom_tags.js so the emoji selector reflects the new or replacement image
	 *
	 * @return void
	 */
	protected function createCustomTagsFile()
	{
		global $context, $settings;

		$fileFunc = FileFunctions::instance();
		$custom = [];
		$smileys = list_getSmileys(0, 2000, 'code');
		foreach ($smileys as $smile)
		{
			if (preg_match('~:[\w-]{2,}:~u', $smile['code'], $match) === 1)
			{
				// If the emoji IS an image file in the currently selected set
				$filename = $context['smiley_dir'] . $smile['filename'] . '.' . $context['smiley_extension'];
				if ($fileFunc->fileExists($filename))
				{
					$custom[] = [
						'name' => trim($smile['code'], ':'),
						'key' => $smile['filename'],
						'type' => $context['smiley_extension']
					];
				}
			}
		}

		// Whatever we have, save it
		$header = '/*!
 * Auto-generated file, DO NOT manually edit
 *		
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 */
 
 let custom = [
 ';

		foreach ($custom as $emoji)
		{
			$header .= "\t" . '{name: \'' . $emoji['name'] . '\', key: \'' . $emoji['key'] . '\', type: \'' . $emoji['type'] . "'},\n";
		}

		$header .= '];';

		file_put_contents($settings['default_theme_dir'] . '/scripts/custom_tags.js', $header);
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

		// Have they submitted a form?
		$this->_subActionSubmit();

		// Load all available smileysets...
		$this->loadSmileySets();

		// Importing any smileys from an existing set?
		$this->_subActionImport();

		// If we're modifying or adding a smileyset, some context info needs to be set.
		$this->_subActionModifySet();

		// This is our save haven.
		createToken('admin-mss', 'request');

		$listOptions = [
			'id' => 'smiley_set_list',
			'title' => $txt['smiley_sets'],
			'no_items_label' => $txt['smiley_sets_none'],
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editsets']),
			'default_sort_col' => 'name',
			'get_items' => [
				'function' => 'list_getSmileySets',
			],
			'get_count' => [
				'function' => 'list_getNumSmileySets',
			],
			'columns' => [
				'default' => [
					'header' => [
						'value' => $txt['smiley_sets_default'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => function ($rowData) {
							return $rowData['selected'] ? '<i class="icon i-check"></i>' : '';
						},
						'class' => 'centertext',
					],
					'sort' => [
						'default' => 'selected',
						'reverse' => 'selected DESC',
					],
				],
				'name' => [
					'header' => [
						'value' => $txt['smiley_sets_name'],
					],
					'data' => [
						'db_htmlsafe' => 'name',
					],
					'sort' => [
						'default' => 'name',
						'reverse' => 'name DESC',
					],
				],
				'ext' => [
					'header' => [
						'value' => $txt['smiley_sets_ext'],
					],
					'data' => [
						'db_htmlsafe' => 'ext',
					],
					'sort' => [
						'default' => 'ext',
						'reverse' => 'ext DESC',
					],
				],
				'url' => [
					'header' => [
						'value' => $txt['smiley_sets_url'],
					],
					'data' => [
						'sprintf' => [
							'format' => $modSettings['smileys_url'] . '/<strong>%1$s</strong>/...',
							'params' => [
								'path' => true,
							],
						],
					],
					'sort' => [
						'default' => 'path',
						'reverse' => 'path DESC',
					],
				],
				'modify' => [
					'header' => [
						'value' => $txt['smiley_set_modify'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'modifyset', 'set' => '']) . '%1$d">' . $txt['smiley_set_modify'] . '</a>',
							'params' => [
								'id' => true,
							],
						],
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					],
					'data' => [
						'function' => function ($rowData) {
							return $rowData['id'] == 0 ? '' : sprintf('<input type="checkbox" name="smiley_set[%1$d]" class="input_check" />', $rowData['id']);
						},
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' =>getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editsets']),
				'token' => 'admin-mss',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'class' => 'submitbutton',
					'value' => '
						<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'modifyset']) . '">' . $txt['smiley_sets_add'] . '</a> 
						<input type="submit" name="delete_set" value="' . $txt['smiley_sets_delete'] . '" onclick="return confirm(\'' . $txt['smiley_sets_confirm'] . '\');" />',
				],
			],
		];

		createList($listOptions);
	}

	/**
	 * Submitted a smiley form, determine what actions are required.
	 *
	 * - Handle deleting of a smiley set
	 * - Adding a new set
	 * - Modifying an existing set, such as setting it as the default
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
				$set_extensions = explode(',', $modSettings['smiley_sets_extensions']);
				foreach ($this->_req->post->smiley_set as $id => $val)
				{
					if (isset($set_paths[$id], $set_names[$id]) && !empty($id))
					{
						unset($set_paths[$id], $set_names[$id], $set_extensions[$id]);
					}
				}

				// Update the modSettings with the new values
				updateSettings([
					'smiley_sets_known' => implode(',', $set_paths),
					'smiley_sets_names' => implode("\n", $set_names),
					'smiley_sets_extensions' => implode(',', $set_extensions),
					'smiley_sets_default' => in_array($modSettings['smiley_sets_default'], $set_paths, true) ? $modSettings['smiley_sets_default'] : $set_paths[0],
				]);
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
				if ($set === -1 && isset($this->_req->post->smiley_sets_path))
				{
					if (in_array($this->_req->post->smiley_sets_path, $set_paths, true))
					{
						throw new Exception('smiley_set_already_exists', false);
					}

					updateSettings([
						'smiley_sets_known' => $modSettings['smiley_sets_known'] . ',' . $this->_req->post->smiley_sets_path,
						'smiley_sets_names' => $modSettings['smiley_sets_names'] . "\n" . $this->_req->post->smiley_sets_name,
						'smiley_sets_extensions' => $modSettings['smiley_sets_extensions'] . ',' . $this->_req->post->smiley_sets_ext,
						'smiley_sets_default' => empty($this->_req->post->smiley_sets_default) ? $modSettings['smiley_sets_default'] : $this->_req->post->smiley_sets_path,
					]);
				}
				// Modify an existing smiley set.
				else
				{
					// Make sure the smiley set exists.
					if (!isset($set_paths[$set], $set_names[$set]))
					{
						throw new Exception('smiley_set_not_found', false);
					}

					// Make sure the path is not yet used by another smileyset.
					if (in_array($this->_req->post->smiley_sets_path, $set_paths, true) && $this->_req->post->smiley_sets_path !== $set_paths[$set])
					{
						throw new Exception('smiley_set_path_already_used', false);
					}

					$set_paths[$set] = $this->_req->post->smiley_sets_path;
					$set_names[$set] = $this->_req->post->smiley_sets_name;
					updateSettings([
						'smiley_sets_known' => implode(',', $set_paths),
						'smiley_sets_names' => implode("\n", $set_names),
						'smiley_sets_default' => empty($this->_req->post->smiley_sets_default) ? $modSettings['smiley_sets_default'] : $this->_req->post->smiley_sets_path
					]);
				}

				// The user might have checked to also import smileys.
				if (!empty($this->_req->post->smiley_sets_import))
				{
					$this->importSmileys($this->_req->post->smiley_sets_path);
				}
			}

			// Reset $context as the default set may have changed
			loadSmileyEmojiData();

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
		$fileFunc = FileFunctions::instance();

		if (empty($modSettings['smileys_dir']) || !$fileFunc->isDir($modSettings['smileys_dir'] . '/' . $smileyPath))
		{
			throw new Exception('smiley_set_unable_to_import', false);
		}

		// Fetch all smileys in the directory
		$foundSmileys = $fileFunc->listTree($modSettings['smileys_dir'] . '/' . $smileyPath);

		// Exclude the smileys that are already in the database.
		$smileys = $this->_getUniqueSmileys($foundSmileys);

		$smiley_order = getMaxSmileyOrder();

		$new_smileys = [];
		foreach ($smileys as $smiley)
		{
			if (strlen($smiley) <= 48)
			{
				$new_smileys[] = [':' . strtok($smiley, '.') . ':', $smiley, strtok($smiley, '.'), 0, ++$smiley_order];
			}
		}

		if (!empty($new_smileys))
		{
			addSmiley($new_smileys);

			$this->clearSmileyCache();
		}
	}

	/**
	 * Returns smile names in a directory that *do not* exist in the DB
	 *
	 * @param array $foundSmileys array as returned from file functions listTree
	 * @return array
	 * @throws \ElkArte\Exceptions\Exception
	 */
	private function _getUniqueSmileys($foundSmileys)
	{
		global $context;

		$smileys = [];
		foreach ($foundSmileys as $smile)
		{
			if (in_array($smile['filename'], ['.', '..', '.htaccess', 'index.php'], true))
			{
				continue;
			}

			// Get the name and extension
			$filename = pathinfo($smile['filename'], PATHINFO_FILENAME);
			if (in_array(strtolower(pathinfo($smile['filename'], PATHINFO_EXTENSION)), $this->_smiley_types, true))
			{
				$smileys[strtolower($filename)] = $filename;
			}
		}

		if (empty($smileys))
		{
			throw new Exception('smiley_set_dir_not_found', false, [$context['current_set']['name']]);
		}

		// Exclude the smileys that are already in the database.
		$duplicates = smileyExists($smileys);
		foreach ($duplicates as $duplicate)
		{
			if (isset($smileys[strtolower($duplicate)]))
			{
				unset($smileys[strtolower($duplicate)]);
			}
		}

		return $smileys;
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
			if ($set === -1 || !isset($context['smiley_sets'][$set]))
			{
				$context['current_set'] = [
					'id' => '-1',
					'path' => '',
					'name' => '',
					'ext' => '',
					'selected' => false,
					'is_new' => true,
				];
			}
			else
			{
				$context['current_set'] = &$context['smiley_sets'][$set];
				$context['current_set']['is_new'] = false;
				$context['smileys_dir'] = empty($modSettings['smileys_dir']) ? BOARDDIR . '/smileys' : $modSettings['smileys_dir'];
				$context['selected_set'] = $modSettings['smiley_sets_default'];

				// Calculate whether there are any smileys in the directory that can be imported.
				if (!empty($modSettings['smileys_dir'])
					&& $fileFunc->isDir($modSettings['smileys_dir'] . '/' . $context['current_set']['path']))
				{
					$foundSmileys = $fileFunc->listTree($modSettings['smileys_dir'] . '/' . $context['current_set']['path']);
					$smileys = $this->_getUniqueSmileys($foundSmileys);
					$context['current_set']['can_import'] = count($smileys);

					// Setup this string to look nice.
					$txt['smiley_set_import_multiple'] = sprintf($txt['smiley_set_import_multiple'], $context['current_set']['can_import']);
				}
			}

			// Retrieve all potential smiley set directories.
			$context['smiley_set_dirs'] = [];
			if (!empty($modSettings['smileys_dir']) && $fileFunc->isDir($modSettings['smileys_dir']))
			{
				// Do not include our emoji directories
				$disallow = ['.', '..', 'open-moji', 'tw-emoji', 'noto-emoji'];

				$dir = dir($modSettings['smileys_dir']);
				while (($entry = $dir->read()) !== false)
				{
					if (!in_array($entry, $disallow, true)
						&& $fileFunc->isDir($modSettings['smileys_dir'] . '/' . $entry))
					{
						$context['smiley_set_dirs'][] = [
							'id' => $entry,
							'path' => $modSettings['smileys_dir'] . '/' . $entry,
							'selectable' => $entry === $context['current_set']['path'] || !in_array($entry, explode(',', $modSettings['smiley_sets_known']), true),
							'current' => $entry === $context['current_set']['path'],
						];
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
		$context['sub_template'] = 'addsmiley';

		$this->loadSmileySets();

		// Submitting a form?
		if (isset($this->_req->post->{$context['session_var']}, $this->_req->post->smiley_code))
		{
			checkSession();

			// Some useful arrays... types we allow - and ports we don't!
			$disabledFiles = ['con', 'com1', 'com2', 'com3', 'com4', 'prn', 'aux', 'lpt1', '.htaccess', 'index.php'];

			$this->_req->post->smiley_code = $this->_req->getPost('smiley_code', '\\ElkArte\\Util::htmltrim', '');
			$this->_req->post->smiley_filename = $this->_req->getPost('smiley_filename', '\\ElkArte\\Util::htmltrim', '');
			$this->_req->post->smiley_location = $this->_req->getPost('smiley_location', 'intval', 0);
			$this->_req->post->smiley_location = min(max($this->_req->post->smiley_location, 0), 2);

			// Make sure some code was entered.
			if (empty($this->_req->post->smiley_code))
			{
				throw new Exception('smiley_has_no_code', false);
			}

			// Check whether the new code has duplicates. It should be unique.
			if (validateDuplicateSmiley($this->_req->post->smiley_code))
			{
				throw new Exception('smiley_not_unique', false);
			}

			// If we are uploading - check all the smiley sets are writable!
			if ($this->_req->post->method !== 'existing')
			{
				$writeErrors = [];
				foreach ($context['smiley_sets'] as $set)
				{
					if (!$fileFunc->isWritable($context['smileys_dir'] . '/' . un_htmlspecialchars($set['path'])))
					{
						$writeErrors[] = $set['path'];
					}
				}

				if (!empty($writeErrors))
				{
					throw new Exception('smileys_upload_error_notwritable', true, [implode(', ', $writeErrors)]);
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
				$_FILES['uploadSmiley']['name'] = preg_replace(['/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'], ['_', '.', ''], $_FILES['uploadSmiley']['name']);

				// We only allow image files - it's THAT simple - no messing around here...
				if (!in_array(strtolower(pathinfo($_FILES['uploadSmiley']['name'], PATHINFO_EXTENSION)), $this->_smiley_types))
				{
					throw new Exception('smileys_upload_error_types', false, [implode(', ', $this->_smiley_types)]);
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
						throw new Exception('smileys_upload_error_blank', false);
					}

					if (empty($newName))
					{
						$newName = basename($file['name']);
					}
					elseif (basename($file['name']) !== $newName)
					{
						throw new Exception('smileys_upload_error_name', false);
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
					$_FILES['individual_' . $set['name']]['name'] = preg_replace(['/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'], ['_', '.', ''], $_FILES['individual_' . $set['name']]['name']);

					// We only allow image files - it's THAT simple - no messing around here...
					if (!in_array(strtolower(pathinfo($_FILES['individual_' . $set['name']]['name'], PATHINFO_EXTENSION)), $this->_smiley_types))
					{
						throw new Exception('smileys_upload_error_types', false, [implode(', ', $this->_smiley_types)]);
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
				throw new Exception('smiley_has_no_filename', false);
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
			$param = [
				$this->_req->post->smiley_code,
				$this->_req->post->smiley_filename,
				$this->_req->post->smiley_description,
				(int) $this->_req->post->smiley_location,
				$smiley_order,
			];
			addSmiley($param);

			$this->clearSmileyCache();

			// No errors? Out of here!
			redirectexit('action=admin;area=smileys;sa=editsmileys');
		}

		$context['selected_set'] = $modSettings['smiley_sets_default'];

		// Get all possible filenames for the smileys.
		$context['filenames'] = $this->getAllPossibleFilenamesForTheSmileys($context['smiley_sets']);

		// Create a new smiley from scratch.
		$context['filenames'] = array_values($context['filenames']);
		$context['current_smiley'] = [
			'id' => 0,
			'code' => '',
			'filename' => $context['filenames'][0]['id'],
			'description' => $txt['smileys_default_description'],
			'location' => 0,
			'is_new' => true,
		];
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
					$displayTypes = [
						'post' => 0,
						'hidden' => 1,
						'popup' => 2
					];
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
					deleteSmileys([$this->_req->post->smiley]);
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
						throw new Exception('smiley_has_no_code', false);
					}

					// Also make sure a filename was given.
					if (empty($this->_req->post->smiley_filename))
					{
						throw new Exception('smiley_has_no_filename', false);
					}

					// Check whether the new code has duplicates. It should be unique.
					if (validateDuplicateSmiley($this->_req->post->smiley_code, $this->_req->post->smiley))
					{
						throw new Exception('smiley_not_unique', false);
					}

					$param = [
						'smiley_location' => $this->_req->post->smiley_location,
						'smiley' => $this->_req->post->smiley,
						'smiley_code' => $this->_req->post->smiley_code,
						'smiley_filename' => $this->_req->post->smiley_filename,
						'smiley_description' => $this->_req->post->smiley_description,
					];
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
			theme()->addJavascriptVar([
				'txt_remove' => JavaScriptEscape($txt['smileys_confirm']),
			]);

			// Determine the language specific sort order of smiley locations.
			$smiley_locations = [
				$txt['smileys_location_form'],
				$txt['smileys_location_hidden'],
				$txt['smileys_location_popup'],
			];
			asort($smiley_locations);

			// Create a list of options for selecting smiley sets.
			$smileyset_option_list = '
				<select id="set" name="set" onchange="changeSet(this.options[this.selectedIndex].value);">';

			foreach ($context['smiley_sets'] as $smiley_set)
			{
				$smileyset_option_list .= '
					<option data-ext="' . $smiley_set['ext'] . '" value="' . $smiley_set['path'] . '"' . ($modSettings['smiley_sets_default'] === $smiley_set['path'] ? ' selected="selected"' : '') . '>' . $smiley_set['name'] . '</option>';
			}

			$smileyset_option_list .= '
				</select>';

			$listOptions = [
				'id' => 'smiley_list',
				'title' => $txt['smileys_edit'],
				'items_per_page' => 40,
				'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editsmileys']),
				'default_sort_col' => 'filename',
				'get_items' => [
					'function' => 'list_getSmileys',
				],
				'get_count' => [
					'function' => 'list_getNumSmileys',
				],
				'no_items_label' => $txt['smileys_no_entries'],
				'columns' => [
					'picture' => [
						'data' => [
							'function' => static function ($rowData) use ($context) {
								return '
								<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'modifysmiley', 'smiley' => '']) . $rowData['id_smiley'] . '">
									<img class="smiley" src="' . $context['smiley_path'] . $rowData['filename'] . '.' . $context['smiley_extension'] . '" alt="' . $rowData['description'] . '" id="smiley' . $rowData['id_smiley'] . '" />
									<input type="hidden" name="smileys[' . $rowData['id_smiley'] . '][filename]" value="' . $rowData['filename'] . '" />
								</a>';
								}
						],
						'class' => 'imagecolumn',
					],
					'code' => [
						'header' => [
							'value' => $txt['smileys_code'],
						],
						'data' => [
							'db_htmlsafe' => 'code',
						],
						'sort' => [
							'default' => 'code',
							'reverse' => 'code DESC',
						],
					],
					'filename' => [
						'header' => [
							'value' => $txt['smileys_filename'],
						],
						'data' => [
							'db_htmlsafe' => 'filename',
						],
						'sort' => [
							'default' => 'filename',
							'reverse' => 'filename DESC',
						],
					],
					'location' => [
						'header' => [
							'value' => $txt['smileys_location'],
						],
						'data' => [
							'function' => function ($rowData) use ($smiley_locations) {
								return $smiley_locations[$rowData['hidden']];
							},
						],
						'sort' => [
							'default' => 'hidden',
							'reverse' => 'hidden DESC',
						],
					],
					'description' => [
						'header' => [
							'value' => $txt['smileys_description'],
						],
						'data' => [
							'function' => static function ($rowData) use ($fileFunc) {
								global $context, $txt, $modSettings;

								if (empty($modSettings['smileys_dir']) || !$fileFunc->isDir($modSettings['smileys_dir']))
								{
									return htmlspecialchars($rowData['description'], ENT_COMPAT, 'UTF-8');
								}

								// Check if there are smileys missing in some sets.
								$missing_sets = [];
								$found_replacement = '';
								foreach ($context['smiley_sets'] as $smiley_set)
								{
									$filename = $rowData['filename'] . '.' . $smiley_set['ext'];
									if (!$fileFunc->fileExists($modSettings['smileys_dir'] . '/' . $smiley_set['path'] . '/' . $filename))
									{
										$missing_sets[] = $smiley_set['path'];
										if (possibleSmileEmoji($rowData, $modSettings['smileys_dir'] . '/' . $smiley_set['path'] , $smiley_set['ext']))
										{
											$found_replacement = $rowData['emoji'] . '.svg';
										}
									}
								}

								$description = htmlspecialchars($rowData['description'], ENT_COMPAT, 'UTF-8');

								if (!empty($missing_sets))
								{
									$description .= '

										<p class="smalltext">
											<strong>' . $txt['smileys_not_found_in_set'] . '</strong> ' . implode(', ', $missing_sets);

									if (!empty($found_replacement))
									{
										$description .= '<br />' . sprintf($txt['smileys_emoji_found'], '<img class="smiley emoji" src="' . $context['emoji_path'] . $found_replacement . '" /> ');
									}

									$description .= '
										</p>';
								}

								return $description;
							},
						],
						'sort' => [
							'default' => 'description',
							'reverse' => 'description DESC',
						],
					],
					'modify' => [
						'header' => [
							'value' => $txt['smileys_modify'],
							'class' => 'centertext',
						],
						'data' => [
							'sprintf' => [
								'format' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'modifysmiley', 'smiley' => '']) . '%1$d">' . $txt['smileys_modify'] . '</a>',
								'params' => [
									'id_smiley' => false,
								],
							],
							'class' => 'centertext',
						],
					],
					'check' => [
						'header' => [
							'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
							'class' => 'centertext',
						],
						'data' => [
							'sprintf' => [
								'format' => '<input type="checkbox" name="checked_smileys[]" value="%1$d" class="input_check" />',
								'params' => [
									'id_smiley' => false,
								],
							],
							'class' => 'centertext',
						],
					],
				],
				'form' => [
					'href' => getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editsmileys']),
					'name' => 'smileyForm',
				],
				'additional_rows' => [
					[
						'position' => 'above_column_headers',
						'value' => $txt['smiley_sets'] . $smileyset_option_list,
						'class' => 'flow_flex_right',
					],
					[
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
					],
				],
			];

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
			$context['selected_set'] = $modSettings['smiley_sets_default'];

			$this->loadSmileySets();

			// Get all possible filenames for the smileys.
			$context['filenames'] = $this->getAllPossibleFilenamesForTheSmileys($context['smiley_sets']);

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
				$deleteIcons = [];
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
					throw new Exception('icon_not_found', false);
				}
				// There is a 16-character limit on message icons...
				elseif (strlen($this->_req->post->icon_filename) > 16)
				{
					throw new Exception('icon_name_too_long', false);
				}
				elseif ($this->_req->post->icon_location === $this->_req->query->icon && !empty($this->_req->query->icon))
				{
					throw new Exception('icon_after_itself', false);
				}

				// First do the sorting... if this is an edit reduce the order of everything after it by one ;)
				if ($this->_req->query->icon !== 0)
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
				$iconInsert = [];
				$iconInsert_new = [];
				foreach ($context['icons'] as $id => $icon)
				{
					if ($id != 0)
					{
						$iconInsert[] = [$id, $icon['board_id'], $icon['title'], $icon['filename'], $icon['true_order']];
					}
					else
					{
						$iconInsert_new[] = [$icon['board_id'], $icon['title'], $icon['filename'], $icon['true_order']];
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
				Cache::instance()->remove('posting_icons-0');
				redirectexit('action=admin;area=smileys;sa=editicons');
			}
		}

		$context[$context['admin_menu_name']]['current_subsection'] = 'editicons';
		$token = createToken('admin-sort');
		$listOptions = [
			'id' => 'message_icon_list',
			'title' => $txt['icons_edit_message_icons'],
			'sortable' => true,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editicons']),
			'get_items' => [
				'function' => function () {
					return $this->list_fetchMessageIconsDetails();
				},
			],
			'no_items_label' => $txt['icons_no_entries'],
			'columns' => [
				'icon' => [
					'data' => [
						'sprintf' => [
							'format' => '<img src="%1$s" alt="%2$s" />',
							'params' => [
								'image_url' => false,
								'filename' => true,
							],
						],
						'class' => 'centertext',
					],
				],
				'filename' => [
					'header' => [
						'value' => $txt['smileys_filename'],
					],
					'data' => [
						'sprintf' => [
							'format' => '%1$s.png',
							'params' => [
								'filename' => true,
							],
						],
					],
				],
				'tooltip' => [
					'header' => [
						'value' => $txt['smileys_description'],
					],
					'data' => [
						'db_htmlsafe' => 'title',
					],
				],
				'board' => [
					'header' => [
						'value' => $txt['icons_board'],
					],
					'data' => [
						'db' => 'board',
					],
				],
				'modify' => [
					'header' => [
						'value' => $txt['smileys_modify'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editicon', 'icon' => '']) . '%1$s">' . $txt['smileys_modify'] . '</a>',
							'params' => [
								'id' => false,
							],
						],
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="checked_icons[]" value="%1$d" class="input_check" />',
							'params' => [
								'id' => false,
							],
						],
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editicons']),
				'hidden_fields' => [
					'icons_save' => 1,
				]
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'class' => 'submitbutton',
					'value' => '
						<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'editicon']) . '">' . $txt['icons_add_new'] . '</a>
						<input type="submit" name="delete" value="' . $txt['quickmod_delete_selected'] . '" onclick="return confirm(\'' . $txt['icons_confirm'] . '\');" />',
				],
				[
					'position' => 'after_title',
					'value' => $txt['icons_reorder_note'],
				],
			],
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
		];

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
			$boardListOptions = [
				'selected_board' => $context['icon']['board_id'] ?? 0,
			];
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
			$location = empty($this->_req->query->location) || $this->_req->query->location !== 'popup' ? 0 : 2;
			$source = $this->_req->getQuery('source', 'intval', 0);
			$after = $this->_req->getQuery('after', 'intval', 0);
			$row = $this->_req->getQuery('row', 'intval', 0);

			checkSession('get');

			if (empty($source))
			{
				throw new Exception('smiley_not_found', false);
			}

			$smiley = [];

			if (!empty($after))
			{
				$smiley = getSmileyPosition($location, $after);
				if (empty($smiley))
				{
					throw new Exception('smiley_not_found');
				}
			}
			else
			{
				$smiley['row'] = $row;
				$smiley['order'] = -1;
				$smiley['location'] = $location;
			}

			moveSmileyPosition($smiley, $source);
		}

		$context['smileys'] = getSmileys();
		$context['move_smiley'] = $this->_req->getQuery('move', 'intval', 0);

		// Make sure all rows are sequential.
		foreach (array_keys($context['smileys']) as $location)
		{
			$context['smileys'][$location] = [
				'id' => $location,
				'title' => $location === 'postform' ? $txt['smileys_location_form'] : $txt['smileys_location_popup'],
				'description' => $location === 'postform' ? $txt['smileys_location_form_description'] : $txt['smileys_location_popup_description'],
				'last_row' => count($context['smileys'][$location]['rows']),
				'rows' => array_values($context['smileys'][$location]['rows']),
			];
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

			// Check that the smiley is from and authorized server... maybe add mirroring later.
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
				[BOARDDIR . '/packages/temp/delme.tmp'],
				[
					'destination_url' => $scripturl . '?action=admin;area=smileys;sa=install;set_gz=' . $this->_req->query->set_gz,
					'crash_on_error' => true
				]
			);

			deltree(BOARDDIR . '/packages/temp', false);
			throw new Exception('package_cant_download', false);
		}

		$extracted = read_tgz_file($destination, BOARDDIR . '/packages/temp');

		// @todo needs to change the URL in the next line ;)
		if (!$extracted)
		{
			throw new Exception('packageget_unable', false, ['https://custom.elkarte.net/index.php?action=search;type=12;basic_search=' . $name]);
		}

		if (!$fileFunc->fileExists(BOARDDIR . '/packages/temp/package-info.xml'))
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
		$parser = new PackageParser();
		$actions = $parser->parsePackageInfo($smileyInfo['xml'], true);

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'smileys', 'sa' => 'install', 'package' => $base_name]);
		$context['has_failure'] = false;
		$context['actions'] = [];
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
				$thisAction = [
					'type' => $txt['package_extract'] . ' ' . $txt['package_tree'],
					'action' => Util::htmlspecialchars(strtr($action['destination'], [BOARDDIR => '.']))
				];

				$file = BOARDDIR . '/packages/temp/' . $base_path . $action['filename'];
				if (isset($action['filename']) && (!$fileFunc->fileExists($file) || !$fileFunc->isWritable(dirname($action['destination']))))
				{
					$context['has_failure'] = true;

					$thisAction += [
						'description' => $txt['package_action_error'],
						'failed' => true,
					];
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
				$credits_tag = [
					'url' => $action['url'],
					'license' => $action['license'],
					'copyright' => $action['copyright'],
					'title' => $action['title'],
				];
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
				updateSettings([
					'smiley_sets_known' => $modSettings['smiley_sets_known'] . ',' . basename($action['action']),
					'smiley_sets_names' => $modSettings['smiley_sets_names'] . "\n" . $smileyInfo['name'] . (count($context['actions']) > 1 ? ' ' . (!empty($action['description']) ? Util::htmlspecialchars($action['description']) : basename($action['action'])) : ''),
				]);
			}

			package_flush_cache();

			// Time to tell pacman we have a new package installed!
			package_put_contents(BOARDDIR . '/packages/installed.list', time());

			// Credits tag?
			$credits_tag = (empty($credits_tag)) ? '' : serialize($credits_tag);
			$installed = [
				'filename' => $smileyInfo['filename'],
				'name' => $smileyInfo['name'],
				'package_id' => $smileyInfo['id'],
				'version' => $smileyInfo['filename'],
				'id_member' => $this->user->id,
				'member_name' => $this->user->name,
				'credits_tag' => $credits_tag,
			];
			logPackageInstall($installed);

			logAction('install_package', ['package' => Util::htmlspecialchars($smileyInfo['name']), 'version' => Util::htmlspecialchars($smileyInfo['version'])], 'admin');

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

		$set_paths = explode(',', $modSettings['smiley_sets_known']);
		$set_names = explode("\n", $modSettings['smiley_sets_names']);
		$set_exts = isset($modSettings['smiley_sets_extensions'])
			? explode(',', $modSettings['smiley_sets_extensions'])
			: setSmileyExtensionArray();

		foreach ($set_paths as $i => $set)
		{
			$context['smiley_sets'][$i] = [
				'id' => $i,
				'path' => htmlspecialchars($set, ENT_COMPAT),
				'name' => htmlspecialchars(stripslashes($set_names[$i]), ENT_COMPAT),
				'selected' => $set === $modSettings['smiley_sets_default'],
				'ext' => Util::htmlspecialchars($set_exts[$i]),
			];
		}
	}

	/**
	 * Perhaps a longer name for the function would better describe what this does. So it will
	 * search group of directories and return just the unique filenames, dis-regarding the extension.
	 * this allows us to match by name across sets that have different extensions
	 *
	 * @param array $smiley_sets array of smiley sets (end directory names) to search
	 * @return array of unique smiley names across one or many "sets"
	 */
	public function getAllPossibleFilenamesForTheSmileys($smiley_sets)
	{
		global $context, $modSettings;

		$filenames = [];
		$fileFunc = FileFunctions::instance();

		$smileys_dir = empty($modSettings['smileys_dir']) ? BOARDDIR . '/smileys' : $modSettings['smileys_dir'];
		if (!$fileFunc->isDir($smileys_dir))
		{
			return [];
		}

		foreach ($smiley_sets as $smiley_set)
		{
			$smiles = $fileFunc->listTree($context['smileys_dir'] . '/' . un_htmlspecialchars($smiley_set['path']));
			foreach($smiles as $smile)
			{
				if (in_array($smile['filename'], ['.', '..', '.htaccess', 'index.php'], true))
				{
					continue;
				}

				$key = strtolower(pathinfo($smile['filename'], PATHINFO_FILENAME));
				if (!in_array($key, $filenames, true)
					&& in_array(strtolower(pathinfo($smile['filename'], PATHINFO_EXTENSION)), $this->_smiley_types, true))
				{
					$filenames[strtolower($key)] = [
						'id' => Util::htmlspecialchars($key, ENT_COMPAT, 'UTF-8'),
						'selected' => false,
					];
				}
			}
		}

		ksort($filenames);

		return $filenames;
	}
}
