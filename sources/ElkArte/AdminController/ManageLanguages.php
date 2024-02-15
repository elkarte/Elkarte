<?php

/**
 * This file handles the administration of languages tasks.
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

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\FileFunctions;
use ElkArte\Languages\Editor as LangEditor;
use ElkArte\Languages\Loader as LangLoader;
use ElkArte\Languages\Txt;
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\Util;

/**
 * Manage languages controller class.
 *
 * @package Languages
 */
class ManageLanguages extends AbstractController
{
	/**
	 * This is the main function for the languages' area.
	 *
	 * What it does:
	 *
	 * - It dispatches the requests.
	 * - Loads the ManageLanguages template. (sub-actions will use it)
	 *
	 * @event integrate_sa_manage_languages Used to add more sub actions
	 * @uses ManageSettings language file
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		theme()->getTemplates()->load('ManageLanguages');
		Txt::load('ManageSettings');

		$subActions = array(
			'edit' => array($this, 'action_edit', 'permission' => 'admin_forum'),
			'settings' => array($this, 'action_languageSettings_display', 'permission' => 'admin_forum'),
			'downloadlang' => array($this, 'action_downloadlang', 'permission' => 'admin_forum'),
			'editlang' => array($this, 'action_editlang', 'permission' => 'admin_forum'),
		);

		// Get ready for action
		$action = new Action('manage_languages');

		// By default we're managing languages, call integrate_sa_manage_languages
		$subAction = $action->initialize($subActions, 'edit');

		// Some final bits
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['edit_languages'];
		$context['sub_template'] = 'show_settings';

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'language_configuration',
			'description' => 'language_description',
		]);

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Interface for adding a new language.
	 *
	 * @uses ManageLanguages template, add_language sub-template.
	 */
	public function action_add()
	{
		global $context, $txt;

		// Are we searching for new languages on the site?
		if (!empty($this->_req->post->lang_add_sub))
		{
			// Need fetch_web_data.
			require_once(SUBSDIR . '/Package.subs.php');
			require_once(SUBSDIR . '/Language.subs.php');

			$context['elk_search_term'] = $this->_req->getPost('lang_add', 'trim|htmlspecialchars[ENT_COMPAT]');

			$listOptions = array(
				'id' => 'languages',
				'get_items' => array(
					'function' => 'list_getLanguagesList',
				),
				'columns' => array(
					'name' => array(
						'header' => array(
							'value' => $txt['name'],
						),
						'data' => array(
							'db' => 'name',
						),
					),
					'description' => array(
						'header' => array(
							'value' => $txt['add_language_elk_desc'],
						),
						'data' => array(
							'db' => 'description',
						),
					),
					'version' => array(
						'header' => array(
							'value' => $txt['add_language_elk_version'],
						),
						'data' => array(
							'db' => 'version',
						),
					),
					'utf8' => array(
						'header' => array(
							'value' => $txt['add_language_elk_utf8'],
						),
						'data' => array(
							'db' => 'utf8',
						),
					),
					'install_link' => array(
						'header' => array(
							'value' => $txt['add_language_elk_install'],
							'class' => 'centertext',
						),
						'data' => array(
							'db' => 'install_link',
							'class' => 'centertext',
						),
					),
				),
			);

			createList($listOptions);
		}

		$context['sub_template'] = 'add_language';
	}

	/**
	 * This lists all the current languages and allows editing of them.
	 */
	public function action_edit()
	{
		global $txt, $context, $language;

		require_once(SUBSDIR . '/Language.subs.php');
		$fileFunc = FileFunctions::instance();

		// Setting a new default?
		if (!empty($this->_req->post->set_default) && !empty($this->_req->post->def_language))
		{
			checkSession();
			validateToken('admin-lang');

			$lang_exists = false;
			$available_langs = getLanguages();
			foreach ($available_langs as $lang)
			{
				if ($this->_req->post->def_language === $lang['filename'])
				{
					$lang_exists = true;
					break;
				}
			}

			if ($this->_req->post->def_language !== $language && $lang_exists)
			{
				$language = $this->_req->post->def_language;
				$this->updateLanguage($language);
				redirectexit('action=admin;area=languages;sa=edit');
			}
		}

		// Create another one time token here.
		createToken('admin-lang');
		createToken('admin-ssc');

		$listOptions = array(
			'id' => 'language_list',
			'items_per_page' => 20,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'languages']),
			'title' => $txt['edit_languages'],
			'data_check' => array(
				'class' => static function ($rowData) {
					if ($rowData['default'])
					{
						return 'highlight2';
					}

					return '';
				},
			),
			'get_items' => array(
				'function' => 'list_getLanguages',
			),
			'get_count' => array(
				'function' => 'list_getNumLanguages',
			),
			'columns' => array(
				'default' => array(
					'header' => array(
						'value' => $txt['languages_default'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => static fn($rowData) => '<input type="radio" name="def_language" value="' . $rowData['id'] . '" ' . ($rowData['default'] ? 'checked="checked"' : '') . ' class="input_radio" />',
						'style' => 'width: 8%;',
						'class' => 'centertext',
					),
				),
				'name' => array(
					'header' => array(
						'value' => $txt['languages_lang_name'],
					),
					'data' => array(
						'function' => static fn($rowData) => sprintf('<a href="%1$s">%2$s<i class="icon icon-small i-modify"></i></a>', getUrl('admin', ['action' => 'admin', 'area' => 'languages', 'sa' => 'editlang', 'lid' => $rowData['id']]), $rowData['name']),
					),
				),
				'count' => array(
					'header' => array(
						'value' => $txt['languages_users'],
					),
					'data' => array(
						'db_htmlsafe' => 'count',
					),
				),
				'locale' => array(
					'header' => array(
						'value' => $txt['languages_locale'],
					),
					'data' => array(
						'db_htmlsafe' => 'locale',
					),
				),
			),
			'form' => array(
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'languages']),
				'token' => 'admin-lang',
			),
			'additional_rows' => array(
				array(
					'class' => 'submitbutton',
					'position' => 'bottom_of_list',
					'value' => '
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
						<input type="submit" name="set_default" value="' . $txt['save'] . '"' . ($fileFunc->isWritable(BOARDDIR . '/Settings.php') ? '' : ' disabled="disabled"') . ' />
						<input type="hidden" name="' . $context['admin-ssc_token_var'] . '" value="' . $context['admin-ssc_token'] . '" />',
				),
			),
			// For highlighting the default.
			'javascript' => '
				initHighlightSelection(\'language_list\');
			',
		);

		// Display a warning if we cannot edit the default setting.
		if (!$fileFunc->isWritable(BOARDDIR . '/Settings.php'))
		{
			$listOptions['additional_rows'][] = array(
				'position' => 'after_title',
				'value' => $txt['language_settings_writable'],
				'class' => 'smalltext alert',
			);
		}

		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'language_list';
	}

	/**
	 * Update the language in use
	 *
	 * @param string $language
	 */
	private function updateLanguage($language)
	{
		$configVars = array(
			array('language', '', 'file')
		);
		$configValues = array(
			'language' => $language
		);
		$settingsForm = new SettingsForm(SettingsForm::FILE_ADAPTER);
		$settingsForm->setConfigVars($configVars);
		$settingsForm->setConfigValues($configValues);
		$settingsForm->save();
	}

	/**
	 * Download a language file from the website.
	 *
	 * What it does:
	 *
	 * - Requires a valid download ID ("did") in the URL.
	 * - Also handles installing language files.
	 * - Attempts to chmod things as needed.
	 * - Uses a standard list to display information about all the files and where they'll be put.
	 *
	 * @uses ManageLanguages template, download_language sub-template.
	 * @uses Admin template, show_list sub-template.
	 */
	public function action_downloadlang()
	{
		// @todo for the moment there is no facility to download packages, so better kill it here
		throw new Exception('no_access', false);
	}

	/**
	 * Edit a particular set of language entries.
	 */
	public function action_editlang()
	{
		global $context, $txt;

		$base_lang_dir = LANGUAGEDIR;
		require_once(SUBSDIR . '/Language.subs.php');
		Txt::load('ManageSettings');

		// Select the languages tab.
		$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'edit';
		$context['page_title'] = $txt['edit_languages'];
		$context['sub_template'] = 'modify_language_entries';

		$context['lang_id'] = $this->_req->query->lid;
		$file_id = empty($this->_req->post->tfid) ? '' : $this->_req->post->tfid;

		// Clean the ID - just in case.
		preg_match('~([A-Za-z0-9_-]+)~', $context['lang_id'], $matches);
		$context['lang_id'] = $matches[1];
		$matches = '';
		preg_match('~([A-Za-z0-9_-]+)~', $file_id, $matches);
		$file_id = ucfirst($matches[1] ?? '');

		// This will be where we look
		$lang_dirs = glob($base_lang_dir . '/*', GLOB_ONLYDIR);

		// Now for every theme get all the files and stick them in context!
		$context['possible_files'] = array_map(static fn($file) => [
			'id' => basename($file, '.php'),
			'name' => $txt['lang_file_desc_' . basename($file)] ?? basename($file),
			'path' => $file,
			'selected' => $file_id === basename($file),
		], $lang_dirs);

		if ($context['lang_id'] !== 'english')
		{
			$possiblePackage = findPossiblePackages($context['lang_id']);
			if ($possiblePackage !== false)
			{
				$context['langpack_uninstall_link'] = getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'uninstall', 'package' => $possiblePackage[1], 'pid' => $possiblePackage[0]]);
			}
		}

		// Quickly load index language entries.
		$mtxt = [];
		$new_lang = new LangLoader($context['lang_id'], $mtxt, database());
		$new_lang->load('Index', true);

		// Setup the primary settings context.
		$context['primary_settings'] = array(
			'name' => Util::ucwords(strtr($context['lang_id'], array('_' => ' ', '-utf8' => ''))),
			'locale' => $mtxt['lang_locale'],
			'dictionary' => $mtxt['lang_dictionary'],
			'spelling' => $mtxt['lang_spelling'],
			'rtl' => $mtxt['lang_rtl'],
		);

		// Quickly load index language entries.
		$edit_lang = new LangEditor($context['lang_id'], database());
		$edit_lang->load($file_id);

		$context['file_entries'] = $edit_lang->getForEditing();

		// Are we saving?
		if (isset($this->_req->post->save_entries) && !empty($this->_req->post->entry))
		{
			checkSession();
			validateToken('admin-mlang');

			$edit_lang->save($file_id, $this->_req->post->entry);

			redirectexit('action=admin;area=languages;sa=editlang;lid=' . $context['lang_id']);
		}

		createToken('admin-mlang');
	}

	/**
	 * Edit language related settings.
	 *
	 * - Accessed by ?action=admin;area=languages;sa=settings
	 * - This method handles the display, allows editing, and saves the result
	 * for the _languageSettings form.
	 *
	 * @event integrate_save_language_settings
	 */
	public function action_languageSettings_display()
	{
		global $context, $txt;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::FILE_ADAPTER);
		$fileFunc = FileFunctions::instance();

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Warn the user if the backup of Settings.php failed.
		$settings_not_writable = !$fileFunc->isWritable(BOARDDIR . '/Settings.php');
		$settings_backup_fail = !$fileFunc->isWritable(BOARDDIR . '/Settings_bak.php') || !@copy(BOARDDIR . '/Settings.php', BOARDDIR . '/Settings_bak.php');

		// Saving settings?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_language_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=languages;sa=settings');
		}

		// Setup the template stuff.
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'languages', 'sa' => 'settings', 'save']);
		$context['settings_title'] = $txt['language_settings'];
		$context['save_disabled'] = $settings_not_writable;

		if ($settings_not_writable)
		{
			$context['error_type'] = 'notice';
			$context['settings_message'] = $txt['settings_not_writable'];
		}
		elseif ($settings_backup_fail)
		{
			$context['error_type'] = 'notice';
			$context['settings_message'] = $txt['admin_backup_fail'];
		}

		// Fill the config array in contextual data for the template.
		$settingsForm->prepare();
	}

	/**
	 * Load up all language settings
	 *
	 * @event integrate_modify_language_settings Use to add new config options
	 */
	private function _settings()
	{
		global $txt;

		// Warn the user if the backup of Settings.php failed.
		$settings_not_writable = !is_writable(BOARDDIR . '/Settings.php');

		$config_vars = array(
			'language' => array('language', $txt['default_language'], 'file', 'select', array(), null, 'disabled' => $settings_not_writable),
			array('userLanguage', $txt['userLanguage'], 'db', 'check', null, 'userLanguage'),
		);

		call_integration_hook('integrate_modify_language_settings', array(&$config_vars));

		// Get our languages. No cache.
		$languages = getLanguages(false);
		foreach ($languages as $lang)
		{
			$config_vars['language'][4][] = array($lang['name'], strtr($lang['name'], array('-utf8' => ' (UTF-8)')));
		}

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
