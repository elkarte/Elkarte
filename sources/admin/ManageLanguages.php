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
 * This file handles the administration of languages tasks.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Manage languages controller class.
 */
class ManageLanguages_Controller
{
	/**
	 * Language settings form
	 * @var Settings_Form
	 */
	protected $_languageSettings;

	/**
	 * This is the main function for the languages area.
	 * It dispatches the requests.
	 * Loads the ManageLanguages template. (sub-actions will use it)
	 *
	 * @uses ManageSettings language file
	 */
	public function action_index()
	{
		global $context, $txt;

		loadTemplate('ManageLanguages');
		loadLanguage('ManageSettings');

		$context['page_title'] = $txt['edit_languages'];
		$context['sub_template'] = 'show_settings';

		$subActions = array(
			'edit' => array ($this, 'action_edit'),
			'add' => array ($this, 'action_add'),
			'settings' => array(
				$this, 'action_languageSettings_display'
			),
			'downloadlang' => array ($this, 'action_downloadlang'),
			'editlang' => array ($this, 'action_editlang'),
		);

		call_integration_hook('integrate_manage_languages', array(&$subActions));

		// By default we're managing languages.
		$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'edit';
		$context['sub_action'] = $_REQUEST['sa'];

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['language_configuration'],
			'description' => $txt['language_description'],
		);

		// Call the right function for this sub-action.
		$action = new Action();
		$action->initialize($subActions);
		$action->dispatch($_REQUEST['sa']);
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
		if (!empty($_POST['lang_add_sub']))
		{
			// Need fetch_web_data.
			require_once(SUBSDIR . '/Package.subs.php');
			require_once(SUBSDIR . '/Language.subs.php');

			$context['elk_search_term'] = htmlspecialchars(trim($_POST['lang_add']));

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

			require_once(SUBSDIR . '/List.subs.php');
			createList($listOptions);
		}

		$context['sub_template'] = 'add_language';
	}

	/**
	 * This lists all the current languages and allows editing of them.
	 */
	public function action_edit()
	{
		global $txt, $context, $scripturl, $language;

		require_once(SUBSDIR . '/Language.subs.php');

		// Setting a new default?
		if (!empty($_POST['set_default']) && !empty($_POST['def_language']))
		{
			checkSession();
			validateToken('admin-lang');

			$lang_exists = false;
			$available_langs = getLanguages();
			foreach ($available_langs as $lang)
			{
				if ($_POST['def_language'] == $lang['filename'])
				{
					$lang_exists = true;
					break;
				}
			}

			if ($_POST['def_language'] != $language && $lang_exists)
			{
				require_once(SUBSDIR . '/Settings.class.php');
				Settings_Form::save_file(array('language' => '\'' . $_POST['def_language'] . '\''));
				$language = $_POST['def_language'];
			}
		}

		// Create another one time token here.
		createToken('admin-lang');

		$listOptions = array(
			'id' => 'language_list',
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=languages',
			'title' => $txt['edit_languages'],
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
						'function' => create_function('$rowData', '
							return \'<input type="radio" name="def_language" value="\' . $rowData[\'id\'] . \'" \' . ($rowData[\'default\'] ? \'checked="checked"\' : \'\') . \' onclick="highlightSelected(\\\'list_language_list_\' . $rowData[\'id\'] . \'\\\');" class="input_radio" />\';
						'),
						'style' => 'width: 8%;',
						'class' => 'centertext',
					),
				),
				'name' => array(
					'header' => array(
						'value' => $txt['languages_lang_name'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $scripturl, $context;

							return sprintf(\'<a href="%1$s?action=admin;area=languages;sa=editlang;lid=%2$s">%3$s</a>\', $scripturl, $rowData[\'id\'], $rowData[\'name\']);
						'),
					),
				),
				'character_set' => array(
					'header' => array(
						'value' => $txt['languages_character_set'],
					),
					'data' => array(
						'db_htmlsafe' => 'char_set',
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
				'href' => $scripturl . '?action=admin;area=languages',
				'token' => 'admin-lang',
			),
			'additional_rows' => array(
				array(
					'position' => 'bottom_of_list',
					'value' => '<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" /><input type="submit" name="set_default" value="' . $txt['save'] . '"' . (is_writable(BOARDDIR . '/Settings.php') ? '' : ' disabled="disabled"') . ' class="button_submit" />',
				),
			),
			// For highlighting the default.
			'javascript' => '
						var prevClass = "";
						var prevDiv = "";
						highlightSelected("list_language_list_' . ($language == '' ? 'english' : $language). '");
			',
		);

		// Display a warning if we cannot edit the default setting.
		if (!is_writable(BOARDDIR . '/Settings.php'))
			$listOptions['additional_rows'][] = array(
					'position' => 'after_title',
					'value' => $txt['language_settings_writable'],
					'class' => 'smalltext alert',
				);

		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'language_list';
	}

	/**
	 * Download a language file from the website.
	 * Requires a valid download ID ("did") in the URL.
	 * Also handles installing language files.
	 * Attempts to chmod things as needed.
	 * Uses a standard list to display information about all the files and where they'll be put.
	 *
	 * @uses ManageLanguages template, download_language sub-template.
	 * @uses Admin template, show_list sub-template.
	 */
	public function action_downloadlang()
	{
		global $context, $forum_version, $txt, $scripturl, $modSettings;

		loadLanguage('ManageSettings');
		require_once(SUBSDIR . '/Package.subs.php');

		// Clearly we need to know what to request.
		if (!isset($_GET['did']))
			fatal_lang_error('no_access', false);

		// Some lovely context.
		$context['download_id'] = $_GET['did'];
		$context['sub_template'] = 'download_language';
		$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'add';

		// Can we actually do the installation - and do they want to?
		if (!empty($_POST['do_install']) && !empty($_POST['copy_file']))
		{
			checkSession('get');
			validateToken('admin-dlang');

			$chmod_files = array();
			$install_files = array();

			// Check writable status.
			foreach ($_POST['copy_file'] as $file)
			{
				// Check it's not very bad.
				if (strpos($file, '..') !== false || (strpos($file, 'themes') !== 0 && !preg_match('~agreement\.[A-Za-z-_0-9]+\.txt$~', $file)))
					fatal_error($txt['languages_download_illegal_paths']);

				$chmod_files[] = BOARDDIR . '/' . $file;
				$install_files[] = $file;
			}

			// Call this in case we have work to do.
			$file_status = create_chmod_control($chmod_files);
			$files_left = $file_status['files']['notwritable'];

			// Something not writable?
			if (!empty($files_left))
				$context['error_message'] = $txt['languages_download_not_chmod'];
			// Otherwise, go go go!
			elseif (!empty($install_files))
			{
				// @todo retrieve the language pack per naming pattern from our sites
				$archive_content = read_tgz_file('http://download.elkarte.net/fetch_language.php?version=' . urlencode(strtr($forum_version, array('ELKARTE ' => ''))) . ';fetch=' . urlencode($_GET['did']), BOARDDIR, false, true, $install_files);
				// Make sure the files aren't stuck in the cache.
				package_flush_cache();
				$context['install_complete'] = sprintf($txt['languages_download_complete_desc'], $scripturl . '?action=admin;area=languages');

				return;
			}
		}

		// @todo Open up the old china.
		if (!isset($archive_content))
			$archive_content = read_tgz_file('http://download.elkarte.net/fetch_language.php?version=' . urlencode(strtr($forum_version, array('ELKARTE ' => ''))) . ';fetch=' . urlencode($_GET['did']), null);

		if (empty($archive_content))
			fatal_error($txt['add_language_error_no_response']);

		// Now for each of the files, let's do some *stuff*
		$context['files'] = array(
			'lang' => array(),
			'other' => array(),
		);
		$context['make_writable'] = array();
		foreach ($archive_content as $file)
		{
			$dirname = dirname($file['filename']);
			$filename = basename($file['filename']);
			$extension = substr($filename, strrpos($filename, '.') + 1);

			// Don't do anything with files we don't understand.
			if (!in_array($extension, array('php', 'jpg', 'gif', 'jpeg', 'png', 'txt')))
				continue;

			// Basic data.
			$context_data = array(
				'name' => $filename,
				'destination' => BOARDDIR . '/' . $file['filename'],
				'generaldest' => $file['filename'],
				'size' => $file['size'],
				// Does chmod status allow the copy?
				'writable' => false,
				// Should we suggest they copy this file?
				'default_copy' => true,
				// Does the file already exist, if so is it same or different?
				'exists' => false,
			);

			// Does the file exist, is it different and can we overwrite?
			if (file_exists(BOARDDIR . '/' . $file['filename']))
			{
				if (is_writable(BOARDDIR . '/' . $file['filename']))
					$context_data['writable'] = true;

				// Finally, do we actually think the content has changed?
				if ($file['size'] == filesize(BOARDDIR . '/' . $file['filename']) && $file['md5'] === md5_file(BOARDDIR . '/' . $file['filename']))
				{
					$context_data['exists'] = 'same';
					$context_data['default_copy'] = false;
				}
				// Attempt to discover newline character differences.
				elseif ($file['md5'] === md5(preg_replace("~[\r]?\n~", "\r\n", file_get_contents(BOARDDIR . '/' . $file['filename']))))
				{
					$context_data['exists'] = 'same';
					$context_data['default_copy'] = false;
				}
				else
					$context_data['exists'] = 'different';
			}
			// No overwrite?
			else
			{
				// Can we at least stick it in the directory...
				if (is_writable(BOARDDIR . '/' . $dirname))
					$context_data['writable'] = true;
			}

			// I love PHP files, that's why I'm a developer and not an artistic type spending my time drinking absinth and living a life of sin...
			if ($extension == 'php' && preg_match('~\w+\.\w+(?:-utf8)?\.php~', $filename))
			{
				$context_data += array(
					'version' => '??',
					'cur_version' => false,
					'version_compare' => 'newer',
				);

				list ($name, $language) = explode('.', $filename);

				// Let's get the new version, I like versions, they tell me that I'm up to date.
				if (preg_match('~\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '~i', $file['preview'], $match) == 1)
					$context_data['version'] = $match[1];

				// Now does the old file exist - if so what is it's version?
				if (file_exists(BOARDDIR . '/' . $file['filename']))
				{
					// OK - what is the current version?
					$fp = fopen(BOARDDIR . '/' . $file['filename'], 'rb');
					$header = fread($fp, 768);
					fclose($fp);

					// Find the version.
					if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
					{
						$context_data['cur_version'] = $match[1];

						// How does this compare?
						if ($context_data['cur_version'] == $context_data['version'])
							$context_data['version_compare'] = 'same';
						elseif ($context_data['cur_version'] > $context_data['version'])
							$context_data['version_compare'] = 'older';

						// Don't recommend copying if the version is the same.
						if ($context_data['version_compare'] != 'newer')
							$context_data['default_copy'] = false;
					}
				}

				// Add the context data to the main set.
				$context['files']['lang'][] = $context_data;
			}
			else
			{
				// If we think it's a theme thing, work out what the theme is.
				if (strpos($dirname, 'themes') === 0 && preg_match('~themes[\\/]([^\\/]+)[\\/]~', $dirname, $match))
					$theme_name = $match[1];
				else
					$theme_name = 'misc';

				// Assume it's an image, could be an acceptance note etc but rare.
				$context['files']['images'][$theme_name][] = $context_data;
			}

			// Collect together all non-writable areas.
			if (!$context_data['writable'])
				$context['make_writable'][] = $context_data['destination'];
		}

		// So, I'm a perfectionist - let's get the theme names.
		$indexes = array();
		foreach ($context['files']['images'] as $k => $dummy)
			$indexes[] = $k;

		$context['theme_names'] = array();
		if (!empty($indexes))
		{
			require_once(SUBSDIR . '/Themes.subs.php');
			$value_data = array(
				'query' => array(),
				'params' => array(),
			);

			foreach ($indexes as $k => $index)
			{
				$value_data['query'][] = 'value LIKE {string:value_' . $k . '}';
				$value_data['params']['value_' . $k] = '%' . $index;
			}

			$themes = validateThemeName($indexes, $value_data);

			if (!empty($themes))
				// Now we have the id_theme we can get the pretty description.
				$context['themes'] = getBasicThemeInfos($themes);
		}

		// Before we go to far can we make anything writable, eh, eh?
		if (!empty($context['make_writable']))
		{
			// What is left to be made writable?
			$file_status = create_chmod_control($context['make_writable']);
			$context['still_not_writable'] = $file_status['files']['notwritable'];

			// Mark those which are now writable as such.
			foreach ($context['files'] as $type => $data)
			{
				if ($type == 'lang')
				{
					foreach ($data as $k => $file)
						if (!$file['writable'] && !in_array($file['destination'], $context['still_not_writable']))
							$context['files'][$type][$k]['writable'] = true;
				}
				else
				{
					foreach ($data as $theme => $files)
						foreach ($files as $k => $file)
							if (!$file['writable'] && !in_array($file['destination'], $context['still_not_writable']))
								$context['files'][$type][$theme][$k]['writable'] = true;
				}
			}

			// Are we going to need more language stuff?
			if (!empty($context['still_not_writable']))
				loadLanguage('Packages');
		}

		// This is the list for the main files.
		$listOptions = array(
			'id' => 'lang_main_files_list',
			'title' => $txt['languages_download_main_files'],
			'get_items' => array(
				'function' => create_function('', '
					global $context;
					return $context[\'files\'][\'lang\'];
				'),
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['languages_download_filename'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $context, $txt;

							return \'<strong>\' . $rowData[\'name\'] . \'</strong><br /><span class="smalltext">\' . $txt[\'languages_download_dest\'] . \': \' . $rowData[\'destination\'] . \'</span>\' . ($rowData[\'version_compare\'] == \'older\' ? \'<br />\' . $txt[\'languages_download_older\'] : \'\');
						'),
					),
				),
				'writable' => array(
					'header' => array(
						'value' => $txt['languages_download_writable'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt;

							return \'<span style="color: \' . ($rowData[\'writable\'] ? \'green\' : \'red\') . \';">\' . ($rowData[\'writable\'] ? $txt[\'yes\'] : $txt[\'no\']) . \'</span>\';
						'),
					),
				),
				'version' => array(
					'header' => array(
						'value' => $txt['languages_download_version'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt;

							return \'<span style="color: \' . ($rowData[\'version_compare\'] == \'older\' ? \'red\' : ($rowData[\'version_compare\'] == \'same\' ? \'orange\' : \'green\')) . \';">\' . $rowData[\'version\'] . \'</span>\';
						'),
					),
				),
				'exists' => array(
					'header' => array(
						'value' => $txt['languages_download_exists'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt;

							return $rowData[\'exists\'] ? ($rowData[\'exists\'] == \'same\' ? $txt[\'languages_download_exists_same\'] : $txt[\'languages_download_exists_different\']) : $txt[\'no\'];
						'),
					),
				),
				'copy' => array(
					'header' => array(
						'value' => $txt['languages_download_copy'],
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$rowData', '
							return \'<input type="checkbox" name="copy_file[]" value="\' . $rowData[\'generaldest\'] . \'" \' . ($rowData[\'default_copy\'] ? \'checked="checked"\' : \'\') . \' class="input_check" />\';
						'),
						'style' => 'width: 4%;',
						'class' => 'centertext',
					),
				),
			),
		);

		// Kill the cache, as it is now invalid..
		if (!empty($modSettings['cache_enable']))
			cache_put_data('known_languages', null, !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);

		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);

		createToken('admin-dlang');
	}

	/**
	 * Edit a particular set of language entries.
	 */
	public function action_editlang()
	{
		global $settings, $context, $txt, $modSettings, $language;

		require_once(SUBSDIR . '/Language.subs.php');
		loadLanguage('ManageSettings');

		// Select the languages tab.
		$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'edit';
		$context['page_title'] = $txt['edit_languages'];
		$context['sub_template'] = 'modify_language_entries';

		$context['lang_id'] = $_GET['lid'];
		list($theme_id, $file_id) = empty($_REQUEST['tfid']) || strpos($_REQUEST['tfid'], '+') === false ? array(1, '') : explode('+', $_REQUEST['tfid']);

		// Clean the ID - just in case.
		preg_match('~([A-Za-z0-9_-]+)~', $context['lang_id'], $matches);
		$context['lang_id'] = $matches[1];

		// Get all the theme data.
		require_once(SUBSDIR . '/Themes.subs.php');
		$themes = getCustomThemes();

		// This will be where we look
		$lang_dirs = array();

		// Check we have themes with a path and a name - just in case - and add the path.
		foreach ($themes as $id => $data)
		{
			if (count($data) != 2)
				unset($themes[$id]);
			elseif (is_dir($data['theme_dir'] . '/languages'))
				$lang_dirs[$id] = $data['theme_dir'] . '/languages';

			// How about image directories?
			if (is_dir($data['theme_dir'] . '/images/' . $context['lang_id']))
				$images_dirs[$id] = $data['theme_dir'] . '/images/' . $context['lang_id'];
		}

		$current_file = $file_id ? $lang_dirs[$theme_id] . '/' . $file_id . '.' . $context['lang_id'] . '.php' : '';

		// Now for every theme get all the files and stick them in context!
		$context['possible_files'] = array();
		foreach ($lang_dirs as $theme => $theme_dir)
		{
			// Open it up.
			$dir = dir($theme_dir);
			while ($entry = $dir->read())
			{
				// We're only after the files for this language.
				if (preg_match('~^([A-Za-z]+)\.' . $context['lang_id'] . '\.php$~', $entry, $matches) == 0)
					continue;

				if (!isset($context['possible_files'][$theme]))
					$context['possible_files'][$theme] = array(
						'id' => $theme,
						'name' => $themes[$theme]['name'],
						'files' => array(),
					);

				$context['possible_files'][$theme]['files'][] = array(
					'id' => $matches[1],
					'name' => isset($txt['lang_file_desc_' . $matches[1]]) ? $txt['lang_file_desc_' . $matches[1]] : $matches[1],
					'selected' => $theme_id == $theme && $file_id == $matches[1],
				);
			}
			$dir->close();
			usort($context['possible_files'][$theme]['files'], create_function('$val1, $val2', 'return strcmp($val1[\'name\'], $val2[\'name\']);'));
		}

		// We no longer wish to speak this language.
		if (!empty($_POST['delete_main']) && $context['lang_id'] != 'english')
		{
			checkSession();
			validateToken('admin-mlang');

			// @todo Todo: FTP Controls?
			require_once(SUBSDIR . '/Package.subs.php');

			// First, Make a backup?
			if (!empty($modSettings['package_make_backups']) && (!isset($_SESSION['last_backup_for']) || $_SESSION['last_backup_for'] != $context['lang_id'] . '$$$'))
			{
				$_SESSION['last_backup_for'] = $context['lang_id'] . '$$$';
				package_create_backup('backup_lang_' . $context['lang_id']);
			}

			// Second, loop through the array to remove the files.
			foreach ($lang_dirs as $curPath)
			{
				foreach ($context['possible_files'][1]['files'] as $lang)
					if (file_exists($curPath . '/' . $lang['id'] . '.' . $context['lang_id'] . '.php'))
						unlink($curPath . '/' . $lang['id'] . '.' . $context['lang_id'] . '.php');

				// Check for the email template.
				if (file_exists($curPath . '/EmailTemplates.' . $context['lang_id'] . '.php'))
					unlink($curPath . '/EmailTemplates.' . $context['lang_id'] . '.php');
			}

			// Third, the agreement file.
			if (file_exists(BOARDDIR . '/agreement.' . $context['lang_id'] . '.txt'))
				unlink(BOARDDIR . '/agreement.' . $context['lang_id'] . '.txt');

			// Fourth, a related images folder?
			foreach ($images_dirs as $curPath)
				if (is_dir($curPath))
					deltree($curPath);

			// Members can no longer use this language.
			removeLanguageFromMember($context['lang_id']);

			// Fifth, update getLanguages() cache.
			if (!empty($modSettings['cache_enable']))
				cache_put_data('known_languages', null, !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);

			// Sixth, if we deleted the default language, set us back to english?
			if ($context['lang_id'] == $language)
			{
				require_once(SUBSDIR . '/Settings.class.php');
				$language = 'english';
				Settings_Form::save_file(array('language' => '\'' . $language . '\''));
			}

			// Seventh, get out of here.
			redirectexit('action=admin;area=languages;sa=edit;' . $context['session_var'] . '=' . $context['session_id']);
		}

		// Saving primary settings?
		$madeSave = false;
		if (!empty($_POST['save_main']) && !$current_file)
		{
			checkSession();
			validateToken('admin-mlang');

			// Read in the current file.
			$current_data = implode('', file($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php'));
			// These are the replacements. old => new
			$replace_array = array(
				'~\$txt\[\'lang_character_set\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_character_set\'] = \'' . addslashes($_POST['character_set']) . '\';',
				'~\$txt\[\'lang_locale\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_locale\'] = \'' . addslashes($_POST['locale']) . '\';',
				'~\$txt\[\'lang_dictionary\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_dictionary\'] = \'' . addslashes($_POST['dictionary']) . '\';',
				'~\$txt\[\'lang_spelling\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_spelling\'] = \'' . addslashes($_POST['spelling']) . '\';',
				'~\$txt\[\'lang_rtl\'\]\s=\s[A-Za-z0-9]+;~' => '$txt[\'lang_rtl\'] = ' . (!empty($_POST['rtl']) ? 'true' : 'false') . ';',
			);
			$current_data = preg_replace(array_keys($replace_array), array_values($replace_array), $current_data);
			$fp = fopen($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php', 'w+');
			fwrite($fp, $current_data);
			fclose($fp);

			$madeSave = true;
		}

		// Quickly load index language entries.
		$old_txt = $txt;
		require($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php');
		$context['lang_file_not_writable_message'] = is_writable($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php') ? '' : sprintf($txt['lang_file_not_writable'], $settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php');
		// Setup the primary settings context.
		$context['primary_settings'] = array(
			'name' => Util::ucwords(strtr($context['lang_id'], array('_' => ' ', '-utf8' => ''))),
			'character_set' => 'UTF-8',
			'locale' => $txt['lang_locale'],
			'dictionary' => $txt['lang_dictionary'],
			'spelling' => $txt['lang_spelling'],
			'rtl' => $txt['lang_rtl'],
		);

		// Restore normal service.
		$txt = $old_txt;

		// Are we saving?
		$save_strings = array();
		if (isset($_POST['save_entries']) && !empty($_POST['entry']))
		{
			checkSession();
			validateToken('admin-mlang');

			// Clean each entry!
			foreach ($_POST['entry'] as $k => $v)
			{
				// Only try to save if it's changed!
				if ($_POST['entry'][$k] != $_POST['comp'][$k])
					$save_strings[$k] = cleanLangString($v, false);
			}
		}

		// If we are editing a file work away at that.
		if ($current_file)
		{
			$context['entries_not_writable_message'] = is_writable($current_file) ? '' : sprintf($txt['lang_entries_not_writable'], $current_file);

			$entries = array();
			// We can't just require it I'm afraid - otherwise we pass in all kinds of variables!
			$multiline_cache = '';
			foreach (file($current_file) as $line)
			{
				// Got a new entry?
				if ($line[0] == '$' && !empty($multiline_cache))
				{
					preg_match('~\$(helptxt|txt|editortxt)\[\'(.+)\'\]\s?=\s?(.+);~ms', strtr($multiline_cache, array("\r" => '')), $matches);
					if (!empty($matches[3]))
					{
						$entries[$matches[2]] = array(
							'type' => $matches[1],
							'full' => $matches[0],
							'entry' => $matches[3],
						);
						$multiline_cache = '';
					}
				}
				$multiline_cache .= $line;
			}
			// Last entry to add?
			if ($multiline_cache)
			{
				preg_match('~\$(helptxt|txt|editortxt)\[\'(.+)\'\]\s?=\s?(.+);~ms', strtr($multiline_cache, array("\r" => '')), $matches);
				if (!empty($matches[3]))
					$entries[$matches[2]] = array(
						'type' => $matches[1],
						'full' => $matches[0],
						'entry' => $matches[3],
					);
			}

			// These are the entries we can definitely save.
			$final_saves = array();

			$context['file_entries'] = array();
			foreach ($entries as $entryKey => $entryValue)
			{
				// Ignore some things we set separately.
				$ignore_files = array('lang_character_set', 'lang_locale', 'lang_dictionary', 'lang_spelling', 'lang_rtl');
				if (in_array($entryKey, $ignore_files))
					continue;

				// These are arrays that need breaking out.
				$arrays = array('days', 'days_short', 'months', 'months_titles', 'months_short', 'happy_birthday_author', 'karlbenson1_author', 'nite0859_author', 'zwaldowski_author', 'geezmo_author', 'karlbenson2_author');
				if (in_array($entryKey, $arrays))
				{
					// Get off the first bits.
					$entryValue['entry'] = substr($entryValue['entry'], strpos($entryValue['entry'], '(') + 1, strrpos($entryValue['entry'], ')') - strpos($entryValue['entry'], '('));
					$entryValue['entry'] = explode(',', strtr($entryValue['entry'], array(' ' => '')));

					// Now create an entry for each item.
					$cur_index = 0;
					$save_cache = array(
						'enabled' => false,
						'entries' => array(),
					);
					foreach ($entryValue['entry'] as $id => $subValue)
					{
						// Is this a new index?
						if (preg_match('~^(\d+)~', $subValue, $matches))
						{
							$cur_index = $matches[1];
							$subValue = substr($subValue, strpos($subValue, '\''));
						}

						// Clean up some bits.
						$subValue = strtr($subValue, array('"' => '', '\'' => '', ')' => ''));

						// Can we save?
						if (isset($save_strings[$entryKey . '-+- ' . $cur_index]))
						{
							$save_cache['entries'][$cur_index] = strtr($save_strings[$entryKey . '-+- ' . $cur_index], array('\'' => ''));
							$save_cache['enabled'] = true;
						}
						else
							$save_cache['entries'][$cur_index] = $subValue;

						$context['file_entries'][] = array(
							'key' => $entryKey . '-+- ' . $cur_index,
							'value' => $subValue,
							'rows' => 1,
						);
						$cur_index++;
					}

					// Do we need to save?
					if ($save_cache['enabled'])
					{
						// Format the string, checking the indexes first.
						$items = array();
						$cur_index = 0;
						foreach ($save_cache['entries'] as $k2 => $v2)
						{
							// Manually show the custom index.
							if ($k2 != $cur_index)
							{
								$items[] = $k2 . ' => \'' . $v2 . '\'';
								$cur_index = $k2;
							}
							else
								$items[] = '\'' . $v2 . '\'';

							$cur_index++;
						}
						// Now create the string!
						$final_saves[$entryKey] = array(
							'find' => $entryValue['full'],
							'replace' => '$' . $entryValue['type'] . '[\'' . $entryKey . '\'] = array(' . implode(', ', $items) . ');',
						);
					}
				}
				else
				{
					// Saving?
					if (isset($save_strings[$entryKey]) && $save_strings[$entryKey] != $entryValue['entry'])
					{
						// @todo Fix this properly.
						if ($save_strings[$entryKey] == '')
							$save_strings[$entryKey] = '\'\'';

						// Set the new value.
						$entryValue['entry'] = $save_strings[$entryKey];
						// And we know what to save now!
						$final_saves[$entryKey] = array(
							'find' => $entryValue['full'],
							'replace' => '$' . $entryValue['type'] . '[\'' . $entryKey . '\'] = ' . $save_strings[$entryKey] . ';',
						);
					}

					$editing_string = cleanLangString($entryValue['entry'], true);
					$context['file_entries'][] = array(
						'key' => $entryKey,
						'value' => $editing_string,
						'rows' => (int) (strlen($editing_string) / 38) + substr_count($editing_string, "\n") + 1,
					);
				}
			}

			// Any saves to make?
			if (!empty($final_saves))
			{
				checkSession();

				$file_contents = implode('', file($current_file));
				foreach ($final_saves as $save)
					$file_contents = strtr($file_contents, array($save['find'] => $save['replace']));

				// Save the actual changes.
				$fp = fopen($current_file, 'w+');
				fwrite($fp, strtr($file_contents, array("\r" => '')));
				fclose($fp);

				$madeSave = true;
			}

			// Another restore.
			$txt = $old_txt;
		}

		// If we saved, redirect.
		if ($madeSave)
			redirectexit('action=admin;area=languages;sa=editlang;lid=' . $context['lang_id']);

		createToken('admin-mlang');
	}

	/**
	 * Edit language related settings.
	 *
	 * This method handles the display, allows to edit, and saves the result
	 * for the _languageSettings form.
	 */
	public function action_languageSettings_display()
	{
		global $scripturl, $context, $txt;

		// initialize the form
		$this->_initLanguageSettingsForm();

		// Warn the user if the backup of Settings.php failed.
		$settings_not_writable = !is_writable(BOARDDIR . '/Settings.php');
		$settings_backup_fail = !@is_writable(BOARDDIR . '/Settings_bak.php') || !@copy(BOARDDIR . '/Settings.php', BOARDDIR . '/Settings_bak.php');

		$config_vars = $this->_languageSettings->settings();

		// Saving settings?
		if (isset($_REQUEST['save']))
		{
			checkSession();

			// @todo review these hooks: do they need param and how else can we do this
			call_integration_hook('integrate_save_language_settings', array(&$config_vars));

			$this->_languageSettings->save();
			redirectexit('action=admin;area=languages;sa=settings');
		}

		// Setup the template stuff.
		$context['post_url'] = $scripturl . '?action=admin;area=languages;sa=settings;save';
		$context['settings_title'] = $txt['language_settings'];
		$context['save_disabled'] = $settings_not_writable;

		if ($settings_not_writable)
			$context['settings_message'] = '<div class="centertext"><strong>' . $txt['settings_not_writable'] . '</strong></div><br />';
		elseif ($settings_backup_fail)
			$context['settings_message'] = '<div class="centertext"><strong>' . $txt['admin_backup_fail'] . '</strong></div><br />';

		// Fill the config array in contextual data for the template.
		$this->_languageSettings->prepare_file();
	}

	/**
	 * Administration settings for languages area:
	 *  the method will initialize the form config array with all settings.
	 *
	 * Format of the array:
	 *  - either, variable name, description, type (constant), size/possible values, helptext.
	 *  - or, an empty string for a horizontal rule.
	 *	- or, a string for a titled section.
	 *
	 * Initialize _languageSettings form.
	 */
	private function _initLanguageSettingsForm()
	{
		global $txt, $context;

		// We'll want to use them someday. That is, right now.
		require_once(SUBSDIR . '/Settings.class.php');

		// make it happen!
		$this->_languageSettings = new Settings_Form();

		// Warn the user if the backup of Settings.php failed.
		$settings_not_writable = !is_writable(BOARDDIR . '/Settings.php');
		
		$config_vars = array(
			'language' => array('language', $txt['default_language'], 'file', 'select', array(), null, 'disabled' => $settings_not_writable),
			array('userLanguage', $txt['userLanguage'], 'db', 'check', null, 'userLanguage'),
		);

		call_integration_hook('integrate_language_settings', array(&$config_vars));

		// Get our languages. No cache.
		$languages = getLanguages(false);
		foreach ($languages as $lang)
			$config_vars['language'][4][$lang['filename']] = array($lang['filename'], strtr($lang['name'], array('-utf8' => ' (UTF-8)')));

		// initialize the little form
		return $this->_languageSettings->settings($config_vars);
	}

	public function settings()
	{
		global $txt;
		
		// Warn the user if the backup of Settings.php failed.
		$settings_not_writable = !is_writable(BOARDDIR . '/Settings.php');
		
		$config_vars = array(
			'language' => array('language', $txt['default_language'], 'file', 'select', array(), null, 'disabled' => $settings_not_writable),
			array('userLanguage', $txt['userLanguage'], 'db', 'check', null, 'userLanguage'),
		);

		call_integration_hook('integrate_language_settings', array(&$config_vars));

		// Get all languages we speak.
		$languages = getLanguages(false);
		foreach ($languages as $lang)
			$config_vars['language'][4][$lang['filename']] = array($lang['filename'], strtr($lang['name'], array('-utf8' => ' (UTF-8)')));

		return $config_vars;
	}
}
