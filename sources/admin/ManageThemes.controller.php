<?php

/**
 * This file concerns itself almost completely with theme administration.
 * Its tasks include changing theme settings, installing and removing
 * themes, choosing the current theme, and editing themes.
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
 *
 * @todo Update this for the new package manager?
 *
 * Creating and distributing theme packages:
 * There isn't that much required to package and distribute your own themes...
 * just do the following:
 *  - create a theme_info.xml file, with the root element theme-info.
 *  - its name should go in a name element, just like description.
 *  - your name should go in author. (email in the email attribute.)
 *  - any support website for the theme should be in website.
 *  - layers and templates (non-default) should go in those elements ;).
 *  - if the images dir isn't images, specify in the images element.
 *  - any extra rows for themes should go in extra, serialized. (as in array(variable => value).)
 *  - tar and gzip the directory - and you're done!
 *  - please include any special license in a license.txt file.
 */

/**
 * Class to deal with theme administration.
 *
 * Its tasks include changing theme settings, installing and removing
 * themes, choosing the current theme, and editing themes.
 *
 * @package Themes
 */
class ManageThemes_Controller extends Action_Controller
{
	/**
	 * Holds the selected theme options
	 * @var mixed[]
	 */
	private $_options;

	/**
	 * Holds the selected default theme options
	 * @var mixed[]
	 */
	private $_default_options;

	/**
	 * Holds the selected master options for a theme
	 * @var mixed[]
	 */
	private $_options_master;

	/**
	 * Holds the selected default master options for a theme
	 * @var mixed[]
	 */
	private $_default_options_master;

	/**
	 * Name of the theme
	 * @var string
	 */
	private $theme_name;

	/**
	 * Full path to the theme
	 * @var string
	 */
	private $theme_dir;

	/**
	 * The themes images url if any
	 * @var string|null
	 */
	private $images_url;

	/**
	 * {@inheritdoc }
	 */
	public function trackStats($action = '')
	{
		if ($action === 'action_jsoption')
		{
			return false;
		}

		return parent::trackStats($action);
	}

	/**
	 * Subaction handler - manages the action and delegates control to the proper
	 * sub-action.
	 *
	 * What it does:
	 * - It loads both the Themes and Settings language files.
	 * - Checks the session by GET or POST to verify the sent data.
	 * - Requires the user to not be a guest.
	 * - Accessed via ?action=admin;area=theme.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $txt, $context;

		if (isset($this->_req->query->api))
		{
			$this->action_index_api();
			return;
		}

		// Load the important language files...
		loadLanguage('ManageThemes');
		loadLanguage('Settings');

		// No guests in here.
		is_not_guest();

		// Theme administration, removal, choice, or installation...
		$subActions = array(
			'admin' => array($this, 'action_admin', 'permission' => 'admin_forum'),
			'list' => array($this, 'action_list', 'permission' => 'admin_forum'),
			'reset' => array($this, 'action_options', 'permission' => 'admin_forum'),
			'options' => array($this, 'action_options', 'permission' => 'admin_forum'),
			'install' => array($this, 'action_install', 'permission' => 'admin_forum'),
			'remove' => array($this, 'action_remove', 'permission' => 'admin_forum'),
			'pick' => array($this, 'action_pick'), // @todo ugly having that in this controller
			'edit' => array($this, 'action_edit', 'permission' => 'admin_forum'),
			'copy' => array($this, 'action_copy', 'permission' => 'admin_forum'),
			'themelist' => array($this, 'action_themelist', 'permission' => 'admin_forum'),
			'browse' => array($this, 'action_browse', 'permission' => 'admin_forum'),
		);

		// Action controller
		$action = new Action('manage_themes');

		// @todo Layout Settings?
		if (!empty($context['admin_menu_name']))
		{
			$context[$context['admin_menu_name']]['tab_data'] = array(
				'title' => $txt['themeadmin_title'],
				'description' => $txt['themeadmin_description'],
				'tabs' => array(
					'admin' => array(
						'description' => $txt['themeadmin_admin_desc'],
					),
					'list' => array(
						'description' => $txt['themeadmin_list_desc'],
					),
					'reset' => array(
						'description' => $txt['themeadmin_reset_desc'],
					),
					'edit' => array(
						'description' => $txt['themeadmin_edit_desc'],
					),
					'themelist' => array(
						'description' => $txt['themeadmin_edit_desc'],
					),
					'browse' => array(
						'description' => $txt['themeadmin_edit_desc'],
					),
				),
			);
		}

		// Follow the sa or just go to administration, call integrate_sa_manage_themes
		$subAction = $action->initialize($subActions, 'admin');

		// Default the page title to Theme Administration by default.
		$context['page_title'] = $txt['themeadmin_title'];
		$context['sub_action'] = $subAction;

		// Go to the action, if you have permissions
		$action->dispatch($subAction);
	}

	/**
	 * Responds to an ajax button request, currently only for remove
	 *
	 * @uses generic_xml_buttons sub template
	 */
	public function action_index_api()
	{
		global $txt, $context, $user_info;

		loadTemplate('Xml');

		// Remove any template layers that may have been created, this is XML!
		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		// No guests in here.
		if ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_guests']
			);

			return;
		}

		// Theme administration, removal, choice, or installation...
		// Of all the actions we currently know only this
		$subActions = array(
		// 'admin' => 'action_admin',
		// 'list' => 'action_list',
		// 'reset' => 'action_options',
		// 'options' => 'action_options',
		// 'install' => 'action_install',
			'remove' => 'action_remove_api',
		// 'pick' => 'action_pick',
		// 'edit' => 'action_edit',
		// 'copy' => 'action_copy',
		// 'themelist' => 'action_themelist',
		// 'browse' => 'action_browse',
		);

		// Follow the sa or just go to administration.
		if (isset($this->_req->query->sa) && !empty($subActions[$this->_req->query->sa]))
			$this->{$subActions[$this->_req->query->sa]}();
		else
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['error_sa_not_set']
			);
			return;
		}
	}

	/**
	 * This function allows administration of themes and their settings,
	 * as well as global theme settings.
	 *
	 * What it does:
	 * - sets the settings theme_allow, theme_guests, and knownThemes.
	 * - requires the admin_forum permission.
	 * - accessed with ?action=admin;area=theme;sa=admin.
	 *
	 * @uses Themes template
	 * @uses Admin language file
	 */
	public function action_admin()
	{
		global $context, $modSettings;

		loadLanguage('Admin');

		// Saving?
		if (isset($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-tm');

			// What themes are being made as known to the members
			if (isset($this->_req->post->options['known_themes']))
			{
				foreach ($this->_req->post->options['known_themes'] as $key => $id)
					$this->_req->post->options['known_themes'][$key] = (int) $id;
			}
			else
				Errors::instance()->fatal_lang_error('themes_none_selectable', false);

			if (!in_array($this->_req->post->options['theme_guests'], $this->_req->post->options['known_themes']))
				Errors::instance()->fatal_lang_error('themes_default_selectable', false);

			// Commit the new settings.
			updateSettings(array(
				'theme_allow' => !empty($this->_req->post->options['theme_allow']),
				'theme_guests' => $this->_req->post->options['theme_guests'],
				'knownThemes' => implode(',', $this->_req->post->options['known_themes']),
			));

			if ((int) $this->_req->post->theme_reset == 0 || in_array($this->_req->post->theme_reset, $this->_req->post->options['known_themes']))
			{
				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData(null, array('id_theme' => (int) $this->_req->post->theme_reset));
			}

			redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=admin');
		}
		// If we aren't submitting - that is, if we are about to...
		else
		{
			loadTemplate('ManageThemes');
			$context['sub_template'] = 'manage_themes';

			// Make our known themes a little easier to work with.
			$knownThemes = !empty($modSettings['knownThemes']) ? explode(',', $modSettings['knownThemes']) : array();

			// Load up all the themes.
			require_once(SUBSDIR . '/Themes.subs.php');
			$context['themes'] = loadThemes($knownThemes);

			// Can we create a new theme?
			$context['can_create_new'] = is_writable(BOARDDIR . '/themes');
			$context['new_theme_dir'] = substr(realpath(BOARDDIR . '/themes/default'), 0, -7);

			// Look for a non existent theme directory. (ie theme87.)
			$theme_dir = BOARDDIR . '/themes/theme';
			$i = 1;
			while (file_exists($theme_dir . $i))
				$i++;
			$context['new_theme_name'] = 'theme' . $i;

			createToken('admin-tm');
		}
	}

	/**
	 * This function lists the available themes and provides an interface
	 * to reset the paths of all the installed themes.
	 *
	 * @uses sub template list_themes, template ManageThemes
	 */
	public function action_list()
	{
		global $context, $boardurl, $txt;

		// Load in the helpers we need
		require_once(SUBSDIR . '/Themes.subs.php');
		loadLanguage('Admin');

		if (isset($this->_req->query->th))
			return $this->action_setthemesettings();

		// Saving?
		if (isset($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-tl');

			$themes = installedThemes();

			$setValues = array();
			foreach ($themes as $id => $theme)
			{
				if (file_exists($this->_req->post->reset_dir . '/' . basename($theme['theme_dir'])))
				{
					$setValues[] = array($id, 0, 'theme_dir', realpath($this->_req->post->reset_dir . '/' . basename($theme['theme_dir'])));
					$setValues[] = array($id, 0, 'theme_url', $this->_req->post->reset_url . '/' . basename($theme['theme_dir']));
					$setValues[] = array($id, 0, 'images_url', $this->_req->post->reset_url . '/' . basename($theme['theme_dir']) . '/' . basename($theme['images_url']));
				}

				if (isset($theme['base_theme_dir']) && file_exists($this->_req->post->reset_dir . '/' . basename($theme['base_theme_dir'])))
				{
					$setValues[] = array($id, 0, 'base_theme_dir', realpath($this->_req->post->reset_dir . '/' . basename($theme['base_theme_dir'])));
					$setValues[] = array($id, 0, 'base_theme_url', $this->_req->post->reset_url . '/' . basename($theme['base_theme_dir']));
					$setValues[] = array($id, 0, 'base_images_url', $this->_req->post->reset_url . '/' . basename($theme['base_theme_dir']) . '/' . basename($theme['base_images_url']));
				}

				Cache::instance()->remove('theme_settings-' . $id);
			}

			if (!empty($setValues))
				updateThemeOptions($setValues);

			redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id']);
		}

		loadTemplate('ManageThemes');

		$context['themes'] = installedThemes();

		// For each theme, make sure the directory exists, and try to fetch the theme version
		foreach ($context['themes'] as $i => $theme)
		{
			$context['themes'][$i]['theme_dir'] = realpath($context['themes'][$i]['theme_dir']);

			if (file_exists($context['themes'][$i]['theme_dir'] . '/index.template.php'))
			{
				// Fetch the header... a good 256 bytes should be more than enough.
				$fp = fopen($context['themes'][$i]['theme_dir'] . '/index.template.php', 'rb');
				$header = fread($fp, 256);
				fclose($fp);

				// Can we find a version comment, at all?
				if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
					$context['themes'][$i]['version'] = $match[1];
			}

			$context['themes'][$i]['valid_path'] = file_exists($context['themes'][$i]['theme_dir']) && is_dir($context['themes'][$i]['theme_dir']);
		}

		// Off to the template we go
		$context['sub_template'] = 'list_themes';
		addJavascriptVar(array('txt_theme_remove_confirm' => $txt['theme_remove_confirm']), true);
		$context['reset_dir'] = realpath(BOARDDIR . '/themes');
		$context['reset_url'] = $boardurl . '/themes';

		createToken('admin-tl');
		createToken('admin-tr', 'request');
	}

	/**
	 * Administrative global settings.
	 *
	 * - Accessed by ?action=admin;area=theme;sa=reset;
	 *
	 * @uses sub template set_options, template file Settings
	 * @uses template file ManageThemes
	 */
	public function action_options()
	{
		global $txt, $context, $settings, $modSettings;

		require_once(SUBSDIR . '/Themes.subs.php');
		$theme = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval', 0));

		if (empty($theme) && empty($this->_req->query->id))
		{
			$context['themes'] = installedThemes();

			// How many options do we have setup for guests?
			$guestOptions = countConfiguredGuestOptions();
			foreach ($guestOptions as $guest_option)
				$context['themes'][$guest_option['id_theme']]['num_default_options'] = $guest_option['value'];

			// How many options do we have setup for members?
			$memberOptions = countConfiguredMemberOptions();
			foreach ($memberOptions as $member_option)
				$context['themes'][$member_option['id_theme']]['num_members'] = $member_option['value'];

			// There has to be a Settings template!
			foreach ($context['themes'] as $k => $v)
				if (empty($v['theme_dir']) || (!file_exists($v['theme_dir'] . '/Settings.template.php') && empty($v['num_members'])))
					unset($context['themes'][$k]);

			loadTemplate('ManageThemes');
			$context['sub_template'] = 'reset_list';

			createToken('admin-stor', 'request');
			return;
		}

		// Submit?
		if (isset($this->_req->post->submit) && empty($this->_req->post->who))
		{
			checkSession();
			validateToken('admin-sto');

			if (empty($this->_req->post->options))
				$this->_options = array();

			if (empty($this->_req->post->default_options))
				$this->_default_options = array();

			// Set up the query values.
			$setValues = array();
			foreach ($this->_options as $opt => $val)
				$setValues[] = array($theme, -1, $opt, is_array($val) ? implode(',', $val) : $val);

			$old_settings = array();
			foreach ($this->_default_options as $opt => $val)
			{
				$old_settings[] = $opt;
				$setValues[] = array(1, -1, $opt, is_array($val) ? implode(',', $val) : $val);
			}

			// If we're actually inserting something..
			if (!empty($setValues))
			{
				// Are there options in non-default themes set that should be cleared?
				if (!empty($old_settings))
					removeThemeOptions('custom', 'guests', $old_settings);

				updateThemeOptions($setValues);
			}

			// Cache the theme settings
			Cache::instance()->remove('theme_settings-' . $theme);
			Cache::instance()->remove('theme_settings-1');

			redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
		}
		// Changing the current options for all members using this theme
		elseif (isset($this->_req->post->submit) && $this->_req->post->who == 1)
		{
			checkSession();
			validateToken('admin-sto');

			$this->_options = empty($this->_req->post->options) ? array() : $this->_req->post->options;
			$this->_options_master = empty($this->_req->post->options_master) ? array() : $this->_req->post->options_master;
			$this->_default_options = empty($this->_req->post->default_options) ? array() : $this->_req->post->default_options;
			$this->_default_options_master = empty($this->_req->post->default_options_master) ? array() : $this->_req->post->default_options_master;

			$old_settings = array();
			foreach ($this->_default_options as $opt => $val)
			{
				if ($this->_default_options_master[$opt] == 0)
					continue;
				elseif ($this->_default_options_master[$opt] == 1)
				{
					// Delete then insert for ease of database compatibility!
					removeThemeOptions('default', 'members', $opt);
					addThemeOptions(1, $opt, $val);

					$old_settings[] = $opt;
				}
				elseif ($this->_default_options_master[$opt] == 2)
					removeThemeOptions('all', 'members', $opt);
			}

			// Delete options from other themes.
			if (!empty($old_settings))
				removeThemeOptions('custom', 'members', $old_settings);

			foreach ($this->_options as $opt => $val)
			{
				if ($this->_options_master[$opt] == 0)
					continue;
				elseif ($this->_options_master[$opt] == 1)
				{
					// Delete then insert for ease of database compatibility - again!
					removeThemeOptions($theme, 'non_default', $opt);
					addThemeOptions($theme, $opt, $val);
				}
				elseif ($this->_options_master[$opt] == 2)
					removeThemeOptions($theme, 'all', $opt);
			}

			redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
		}
		// Remove all members options and use the defaults
		elseif (!empty($this->_req->query->who) && $this->_req->query->who == 2)
		{
			checkSession('get');
			validateToken('admin-stor', 'request');

			removeThemeOptions($theme, 'members');

			redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
		}

		$old_id = $settings['theme_id'];
		$old_settings = $settings;

		loadTheme($theme, false);
		loadLanguage('Profile');

		// @todo Should we just move these options so they are no longer theme dependant?
		loadLanguage('PersonalMessage');

		// Let the theme take care of the settings.
		loadTemplate('Settings');
		loadSubTemplate('options');

		// Set up for the template
		$context['sub_template'] = 'set_options';
		$context['page_title'] = $txt['theme_settings'];
		$context['options'] = $context['theme_options'];
		$context['theme_settings'] = $settings;

		// Load the options for these theme
		if (empty($this->_req->query->who))
		{
			$context['theme_options'] = loadThemeOptionsInto(array(1, $theme), -1, $context['theme_options']);
			$context['theme_options_reset'] = false;
		}
		else
		{
			$context['theme_options'] = array();
			$context['theme_options_reset'] = true;
		}

		// Prepare the options for the template
		foreach ($context['options'] as $i => $setting)
		{
			// Is this disabled?
			if ($setting['id'] == 'calendar_start_day' && empty($modSettings['cal_enabled']))
			{
				unset($context['options'][$i]);
				continue;
			}
			elseif (($setting['id'] == 'topics_per_page' || $setting['id'] == 'messages_per_page') && !empty($modSettings['disableCustomPerPage']))
			{
				unset($context['options'][$i]);
				continue;
			}

			// Type of field so we display the right input field
			if (!isset($setting['type']) || $setting['type'] == 'bool')
				$context['options'][$i]['type'] = 'checkbox';
			elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
				$context['options'][$i]['type'] = 'number';
			elseif ($setting['type'] == 'string')
				$context['options'][$i]['type'] = 'text';

			if (isset($setting['options']))
				$context['options'][$i]['type'] = 'list';

			$context['options'][$i]['value'] = !isset($context['theme_options'][$setting['id']]) ? '' : $context['theme_options'][$setting['id']];
		}

		// Restore the existing theme.
		loadTheme($old_id, false);
		$settings = $old_settings;

		loadTemplate('ManageThemes');
		createToken('admin-sto');
	}

	/**
	 * Administrative global settings.
	 *
	 * What it does:
	 * - Saves and requests global theme settings. ($settings)
	 * - Loads the Admin language file.
	 * - Calls action_admin() if no theme is specified. (the theme center.)
	 * - Requires admin_forum permission.
	 * - Accessed with ?action=admin;area=theme;sa=list&th=xx.
	 */
	public function action_setthemesettings()
	{
		global $txt, $context, $settings, $modSettings;

		require_once(SUBSDIR . '/Themes.subs.php');

		// Nothing chosen, back to the start you go
		if (empty($this->_req->query->th) && empty($this->_req->query->id))
			return $this->action_admin();

		// The theme's ID is needed
		$theme = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval', 0));

		// Validate inputs/user.
		if (empty($theme))
			Errors::instance()->fatal_lang_error('no_theme', false);

		// Select the best fitting tab.
		$context[$context['admin_menu_name']]['current_subsection'] = 'list';
		loadLanguage('Admin');

		// Fetch the smiley sets...
		$sets = explode(',', 'none,' . $modSettings['smiley_sets_known']);
		$set_names = explode("\n", $txt['smileys_none'] . "\n" . $modSettings['smiley_sets_names']);
		$context['smiley_sets'] = array(
			'' => $txt['smileys_no_default']
		);
		foreach ($sets as $i => $set)
			$context['smiley_sets'][$set] = htmlspecialchars($set_names[$i], ENT_COMPAT, 'UTF-8');

		$old_id = $settings['theme_id'];
		$old_settings = $settings;

		loadTheme($theme, false);

		// Also load the actual themes language file - in case of special settings.
		loadLanguage('Settings', '', true, true);

		// And the custom language strings...
		loadLanguage('ThemeStrings', '', false, true);

		// Let the theme take care of the settings.
		loadTemplate('Settings');
		loadSubTemplate('settings');

		// Load the variants separately...
		$settings['theme_variants'] = array();
		if (file_exists($settings['theme_dir'] . '/index.template.php'))
		{
			$file_contents = implode("\n", file($settings['theme_dir'] . '/index.template.php'));
			if (preg_match('~\'theme_variants\'\s*=>(.+?\)),$~sm', $file_contents, $matches))
				eval('global $settings; $settings[\'theme_variants\'] = ' . $matches[1] . ';');

				call_integration_hook('integrate_init_theme', array($theme, &$settings));
		}

		// Submitting!
		if (isset($this->_req->post->save))
		{
			// Allowed?
			checkSession();
			validateToken('admin-sts');

			$options = array();
			$options['options'] = empty($this->_req->post->options) ? array() : (array) $this->_req->post->options;
			$options['default_options'] = empty($this->_req->post->default_options) ? array() : (array) $this->_req->post->default_options;

			// Make sure items are cast correctly.
			foreach ($context['theme_settings'] as $item)
			{
				// Unwatch this item if this is just a separator.
				if (!is_array($item))
					continue;

				// Clean them up for the database
				foreach (array('options', 'default_options') as $option)
				{
					if (!isset($options[$option][$item['id']]))
						continue;
					// Checkbox.
					elseif (empty($item['type']))
						$options[$option][$item['id']] = $options[$option][$item['id']] ? 1 : 0;
					// Number
					elseif ($item['type'] == 'number')
						$options[$option][$item['id']] = (int) $options[$option][$item['id']];
				}
			}

			// Set up the sql query.
			$inserts = array();
			foreach ($options['options'] as $opt => $val)
				$inserts[] = array($theme, 0, $opt, is_array($val) ? implode(',', $val) : $val);

			foreach ($options['default_options'] as $opt => $val)
				$inserts[] = array(1, 0, $opt, is_array($val) ? implode(',', $val) : $val);

			// If we're actually inserting something..
			if (!empty($inserts))
				updateThemeOptions($inserts);

			// Clear and Invalidate the cache.
			Cache::instance()->remove('theme_settings-' . $theme);
			Cache::instance()->remove('theme_settings-1');
			updateSettings(array('settings_updated' => time()));

			redirectexit('action=admin;area=theme;sa=list;th=' . $theme . ';' . $context['session_var'] . '=' . $context['session_id']);
		}

		$context['sub_template'] = 'set_settings';
		$context['page_title'] = $txt['theme_settings'];

		foreach ($settings as $setting => $dummy)
		{
			if (!in_array($setting, array('theme_url', 'theme_dir', 'images_url', 'template_dirs')))
				$settings[$setting] = htmlspecialchars__recursive($settings[$setting]);
		}

		$context['settings'] = $context['theme_settings'];
		$context['theme_settings'] = $settings;

		foreach ($context['settings'] as $i => $setting)
		{
			// Separators are dummies, so leave them alone.
			if (!is_array($setting))
				continue;

			// Create the right input fields for the data
			if (!isset($setting['type']) || $setting['type'] == 'bool')
				$context['settings'][$i]['type'] = 'checkbox';
			elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
				$context['settings'][$i]['type'] = 'number';
			elseif ($setting['type'] == 'string')
				$context['settings'][$i]['type'] = 'text';

			if (isset($setting['options']))
				$context['settings'][$i]['type'] = 'list';

			$context['settings'][$i]['value'] = !isset($settings[$setting['id']]) ? '' : $settings[$setting['id']];
		}

		// Do we support variants?
		if (!empty($settings['theme_variants']))
		{
			$context['theme_variants'] = array();
			foreach ($settings['theme_variants'] as $variant)
			{
				// Have any text, old chap?
				$context['theme_variants'][$variant] = array(
					'label' => isset($txt['variant_' . $variant]) ? $txt['variant_' . $variant] : $variant,
					'thumbnail' => !file_exists($settings['theme_dir'] . '/images/thumbnail.png') || file_exists($settings['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? $settings['images_url'] . '/thumbnail_' . $variant . '.png' : ($settings['images_url'] . '/thumbnail.png'),
				);
			}
			$context['default_variant'] = !empty($settings['default_variant']) && isset($context['theme_variants'][$settings['default_variant']]) ? $settings['default_variant'] : $settings['theme_variants'][0];
		}

		// Restore the current theme.
		loadTheme($old_id, false);

		$settings = $old_settings;

		// Reinit just incase.
		if (function_exists('template_init'))
			$settings += template_init();

		loadTemplate('ManageThemes');

		// We like Kenny better than Token.
		createToken('admin-sts');
	}

	/**
	 * Remove a theme from the database.
	 *
	 * What it does:
	 * - Removes an installed theme.
	 * - Requires an administrator.
	 * - Accessed with ?action=admin;area=theme;sa=remove.
	 */
	public function action_remove()
	{
		global $modSettings, $context;

		require_once(SUBSDIR . '/Themes.subs.php');

		checkSession('get');
		validateToken('admin-tr', 'request');

		// The theme's ID must be an integer.
		$theme = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval', 0));

		// You can't delete the default theme!
		if ($theme == 1)
			Errors::instance()->fatal_lang_error('no_access', false);

		// Its no longer known
		$known = explode(',', $modSettings['knownThemes']);
		for ($i = 0, $n = count($known); $i < $n; $i++)
		{
			if ($known[$i] == $theme)
				unset($known[$i]);
		}
		$known = strtr(implode(',', $known), array(',,' => ','));

		// Remove it as an option everywhere
		deleteTheme($theme);

		// Fix it if the theme was the overall default theme.
		if ($modSettings['theme_guests'] == $theme)
			updateSettings(array('theme_guests' => '1', 'knownThemes' => $known));
		else
			updateSettings(array('knownThemes' => $known));

		redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id']);
	}

	/**
	 * Remove a theme from the database in response to an ajax api request
	 *
	 * What it does:
	 * - Removes an installed theme.
	 * - Requires an administrator.
	 * - Accessed with ?action=admin;area=theme;sa=remove;api
	 */
	public function action_remove_api()
	{
		global $modSettings, $context, $txt;

		require_once(SUBSDIR . '/Themes.subs.php');

		// Validate what was sent
		if (checkSession('get', '', false))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['session_verify_fail'],
			);

			return;
		}

		// Not just any John Smith can send in a api request
		if (!allowedTo('admin_forum'))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['cannot_admin_forum'],
			);
			return;
		}

		// Even if you are John Smith, you still need a ticket
		if (!validateToken('admin-tr', 'request', true, false))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['token_verify_fail'],
			);

			return;
		}

		// The theme's ID must be an integer.
		$theme = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval', 0));

		// You can't delete the default theme!
		if ($theme == 1)
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['no_access'],
			);
			return;
		}

		// It is a theme we know about?
		$known = explode(',', $modSettings['knownThemes']);
		for ($i = 0, $n = count($known); $i < $n; $i++)
		{
			if ($known[$i] == $theme)
				unset($known[$i]);
		}

		// Finally, remove it
		deleteTheme($theme);

		$known = strtr(implode(',', $known), array(',,' => ','));

		// Fix it if the theme was the overall default theme.
		if ($modSettings['theme_guests'] == $theme)
			updateSettings(array('theme_guests' => '1', 'knownThemes' => $known));
		else
			updateSettings(array('knownThemes' => $known));

		// Let them know it worked, all without a page refresh
		createToken('admin-tr', 'request');
		$context['xml_data'] = array(
			'success' => 1,
			'token_var' => $context['admin-tr_token_var'],
			'token' => $context['admin-tr_token'],
		);
	}

	/**
	 * Choose a theme from a list.
	 * Allows a user or administrator to pick a new theme with an interface.
	 *
	 * What it does:
	 * - Can edit everyone's (u = 0), guests' (u = -1), or a specific user's.
	 * - Uses the Themes template. (pick sub template.)
	 * - Accessed with ?action=admin;area=theme;sa=pick.
	 *
	 * @uses Profile language text
	 * @uses ManageThemes template
	 * @todo thought so... Might be better to split this file in ManageThemes and Themes,
	 * with centralized admin permissions on ManageThemes.
	 */
	public function action_pick()
	{
		global $txt, $context, $modSettings, $user_info, $scripturl, $settings;

		require_once(SUBSDIR . '/Themes.subs.php');

		if (!$modSettings['theme_allow'] && $settings['disable_user_variant'] && !allowedTo('admin_forum'))
			Errors::instance()->fatal_lang_error('no_access', false);

		loadLanguage('Profile');
		loadTemplate('ManageThemes');

		// Build the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=theme;sa=pick;u=' . (!empty($this->_req->query->u) ? (int) $this->_req->query->u : 0),
			'name' => $txt['theme_pick'],
		);
		$context['default_theme_id'] = $modSettings['theme_default'];

		$_SESSION['id_theme'] = 0;

		if (isset($this->_req->query->id))
			$this->_req->query->th = $this->_req->query->id;

		// Saving a variant cause JS doesn't work - pretend it did ;)
		if (isset($this->_req->post->save))
		{
			// Which theme?
			foreach ($this->_req->post->save as $k => $v)
				$this->_req->query->th = (int) $k;

			if (isset($this->_req->post->vrt[$k]))
				$this->_req->query->vrt = $this->_req->post->vrt[$k];
		}

		// Have we made a decision, or are we just browsing?
		if (isset($this->_req->query->th))
		{
			checkSession('get');

			$th = $this->_req->getQuery('th', 'intval');
			$vrt = $this->_req->getQuery('vrt', 'cleanhtml');
			$u = $this->_req->getQuery('u', 'intval');

			// Save for this user.
			if (!isset($u) || !allowedTo('admin_forum'))
			{
				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData($user_info['id'], array('id_theme' => $th));

				// A variants to save for the user?
				if (!empty($vrt))
				{
					updateThemeOptions(array($th, $user_info['id'], 'theme_variant', $vrt));

					Cache::instance()->remove('theme_settings-' . $th . ':' . $user_info['id']);

					$_SESSION['id_variant'] = 0;
				}

				redirectexit('action=profile;area=theme');
			}

			// If changing members or guests - and there's a variant - assume changing default variant.
			if (!empty($vrt) && ($u === 0 || $u === -1))
			{
				updateThemeOptions(array($th, 0, 'default_variant', $vrt));

				// Make it obvious that it's changed
				Cache::instance()->remove('theme_settings-' . $th);
			}

			// For everyone.
			if ($u === 0)
			{
				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData(null, array('id_theme' => $th));

				// Remove any custom variants.
				if (!empty($vrt))
					deleteVariants($th);

				redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
			}
			// Change the default/guest theme.
			elseif ($u === -1)
			{
				updateSettings(array('theme_guests' => $th));

				redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
			}
			// Change a specific member's theme.
			else
			{
				// The forum's default theme is always 0 and we
				if (isset($th) && $th == 0)
					$th = $modSettings['theme_guests'];

				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData($u, array('id_theme' => $th));

				if (!empty($vrt))
				{
					updateThemeOptions(array($th, $u, 'theme_variant', $vrt));
					Cache::instance()->remove('theme_settings-' . $th . ':' . $u);

					if ($user_info['id'] == $u)
						$_SESSION['id_variant'] = 0;
				}

				redirectexit('action=profile;u=' . $u . ';area=theme');
			}
		}

		$u = $this->_req->getQuery('u', 'intval');

		// Figure out who the member of the minute is, and what theme they've chosen.
		if (!isset($u) || !allowedTo('admin_forum'))
		{
			$context['current_member'] = $user_info['id'];
			$current_theme = $user_info['theme'];
		}
		// Everyone can't chose just one.
		elseif ($u === 0)
		{
			$context['current_member'] = 0;
			$current_theme = 0;
		}
		// Guests and such...
		elseif ($u === -1)
		{
			$context['current_member'] = -1;
			$current_theme = $modSettings['theme_guests'];
		}
		// Someones else :P.
		else
		{
			$context['current_member'] = $u;

			require_once(SUBSDIR . '/Members.subs.php');
			$member = getBasicMemberData($context['current_member']);

			$current_theme = $member['id_theme'];
		}

		// Get the theme name and descriptions.
		list ($context['available_themes'], $guest_theme) = availableThemes($current_theme, $context['current_member']);

		// As long as we're not doing the default theme...
		if (!isset($u) || $u >= 0)
		{
			if ($guest_theme != 0)
				$context['available_themes'][0] = $context['available_themes'][$guest_theme];

			$context['available_themes'][0]['id'] = 0;
			$context['available_themes'][0]['name'] = $txt['theme_forum_default'];
			$context['available_themes'][0]['selected'] = $current_theme == 0;
			$context['available_themes'][0]['description'] = $txt['theme_global_description'];
		}

		ksort($context['available_themes']);

		$context['page_title'] = $txt['theme_pick'];
		$context['sub_template'] = 'pick';
	}

	/**
	 * Installs new themes, either from a gzip or copy of the default.
	 *
	 * What it does:
	 * - Puts themes in $boardurl/themes.
	 * - Assumes the gzip has a root directory in it. (ie default.)
	 * - Requires admin_forum.
	 * - Accessed with ?action=admin;area=theme;sa=install.
	 *
	 * @uses ManageThemes template
	 */
	public function action_install()
	{
		global $boardurl, $txt, $context, $settings, $modSettings;

		checkSession('request');

		require_once(SUBSDIR . '/Themes.subs.php');
		require_once(SUBSDIR . '/Package.subs.php');

		loadTemplate('ManageThemes');

		// Passed an ID, then the install is complete, lets redirect and show them
		if (isset($this->_req->query->theme_id))
		{
			$this->_req->query->theme_id = (int) $this->_req->query->theme_id;

			$context['sub_template'] = 'installed';
			$context['page_title'] = $txt['theme_installed'];
			$context['installed_theme'] = array(
				'id' => $this->_req->query->theme_id,
				'name' => getThemeName($this->_req->query->theme_id),
			);

			return null;
		}

		// How are we going to install this theme, from a dir, zip, copy of default?
		if ((!empty($_FILES['theme_gz']) && (!isset($_FILES['theme_gz']['error']) || $_FILES['theme_gz']['error'] != 4)) || !empty($this->_req->query->theme_gz))
			$method = 'upload';
		elseif (isset($this->_req->query->theme_dir) && rtrim(realpath($this->_req->query->theme_dir), '/\\') != realpath(BOARDDIR . '/themes') && file_exists($this->_req->query->theme_dir))
			$method = 'path';
		else
			$method = 'copy';

		// Copy the default theme?
		if (!empty($this->_req->post->copy) && $method == 'copy')
			$this->copyDefault();
		// Install from another directory
		elseif (isset($this->_req->post->theme_dir) && $method == 'path')
			$this->installFromDir();
		// Uploaded a zip file to install from
		elseif ($method == 'upload')
			$this->installFromZip();
		else
			Errors::instance()->fatal_lang_error('theme_install_general', false);

		// Something go wrong?
		if ($this->theme_dir != '' && basename($this->theme_dir) != 'themes')
		{
			// Defaults.
			$install_info = array(
				'theme_url' => $boardurl . '/themes/' . basename($this->theme_dir),
				'images_url' => isset($this->images_url) ? $this->images_url : $boardurl . '/themes/' . basename($this->theme_dir) . '/images',
				'theme_dir' => $this->theme_dir,
				'name' => $this->theme_name
			);
			$explicit_images = false;

			if (file_exists($this->theme_dir . '/theme_info.xml'))
			{
				$theme_info = file_get_contents($this->theme_dir . '/theme_info.xml');

				// Parse theme-info.xml into an Xml_Array.
				$theme_info_xml = new Xml_Array($theme_info);

				// @todo Error message of some sort?
				if (!$theme_info_xml->exists('theme-info[0]'))
					return 'package_get_error_packageinfo_corrupt';

				$theme_info_xml = $theme_info_xml->path('theme-info[0]');
				$theme_info_xml = $theme_info_xml->to_array();

				$xml_elements = array(
					'name' => 'name',
					'theme_layers' => 'layers',
					'theme_templates' => 'templates',
					'based_on' => 'based-on',
				);
				foreach ($xml_elements as $var => $name)
				{
					if (!empty($theme_info_xml[$name]))
						$install_info[$var] = $theme_info_xml[$name];
				}

				if (!empty($theme_info_xml['images']))
				{
					$install_info['images_url'] = $install_info['theme_url'] . '/' . $theme_info_xml['images'];
					$explicit_images = true;
				}

				if (!empty($theme_info_xml['extra']))
					$install_info += Util::unserialize($theme_info_xml['extra']);
			}

			if (isset($install_info['based_on']))
			{
				if ($install_info['based_on'] == 'default')
				{
					$install_info['theme_url'] = $settings['default_theme_url'];
					$install_info['images_url'] = $settings['default_images_url'];
				}
				elseif ($install_info['based_on'] != '')
				{
					$install_info['based_on'] = preg_replace('~[^A-Za-z0-9\-_ ]~', '', $install_info['based_on']);

					$temp = loadBasedOnTheme($install_info['based_on'], $explicit_images);

					// @todo An error otherwise?
					if (is_array($temp))
					{
						$install_info = $temp + $install_info;

						if (empty($explicit_images) && !empty($install_info['base_theme_url']))
							$install_info['theme_url'] = $install_info['base_theme_url'];
					}
				}

				unset($install_info['based_on']);
			}

			// Find the newest id_theme.
			$id_theme = nextTheme();

			$inserts = array();
			foreach ($install_info as $var => $val)
				$inserts[] = array($id_theme, $var, $val);

			if (!empty($inserts))
				addTheme($inserts);

			updateSettings(array('knownThemes' => strtr($modSettings['knownThemes'] . ',' . $id_theme, array(',,' => ','))));
		}

		redirectexit('action=admin;area=theme;sa=install;theme_id=' . $id_theme . ';' . $context['session_var'] . '=' . $context['session_id']);
	}

	/**
	 * Install a new theme from an uploaded zip archive
	 */
	public function installFromZip()
	{
		global $context;

		// Hopefully the themes directory is writable, or we might have a problem.
		if (!is_writable(BOARDDIR . '/themes'))
			Errors::instance()->fatal_lang_error('theme_install_write_error', 'critical');

		// This happens when the admin session is gone and the user has to login again
		if (empty($_FILES['theme_gz']) && empty($this->_req->post->theme_gz))
			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);

		// Set the default settings...
		$this->theme_name = strtok(basename(isset($_FILES['theme_gz']) ? $_FILES['theme_gz']['name'] : $this->_req->post->theme_gz), '.');
		$this->theme_name = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $this->theme_name);
		$this->theme_dir = BOARDDIR . '/themes/' . $this->theme_name;

		if (isset($_FILES['theme_gz']) && is_uploaded_file($_FILES['theme_gz']['tmp_name']) && (ini_get('open_basedir') != '' || file_exists($_FILES['theme_gz']['tmp_name'])))
			read_tgz_file($_FILES['theme_gz']['tmp_name'], BOARDDIR . '/themes/' . $this->theme_name, false, true);
		elseif (isset($this->_req->post->theme_gz))
		{
			if (!isAuthorizedServer($this->_req->post->theme_gz))
				Errors::instance()->fatal_lang_error('not_valid_server');

			read_tgz_file($this->_req->post->theme_gz, BOARDDIR . '/themes/' . $this->theme_name, false, true);
		}
		else
			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
	}

	/**
	 * Install a theme from a directory on the server
	 *
	 * - Expects the directory is properly loaded with theme files
	 */
	public function installFromDir()
	{
		if (!is_dir($this->_req->post->theme_dir) || !file_exists($this->_req->post->theme_dir . '/theme_info.xml'))
			Errors::instance()->fatal_lang_error('theme_install_error', false);

		$this->theme_name = basename($this->_req->post->theme_dir);
		$this->theme_dir = $this->_req->post->theme_dir;
	}

	/**
	 * Make a copy of the default theme in a new directory
	 */
	public function copyDefault()
	{
		global $boardurl, $modSettings, $settings;

		// Hopefully the themes directory is writable, or we might have a problem.
		if (!is_writable(BOARDDIR . '/themes'))
			Errors::instance()->fatal_lang_error('theme_install_write_error', 'critical');

		// Make the new directory, standard characters only
		$this->theme_dir = BOARDDIR . '/themes/' . preg_replace('~[^A-Za-z0-9_\- ]~', '', $this->_req->post->copy);
		umask(0);
		mkdir($this->theme_dir, 0777);

		// Get some more time if we can
		detectServer()->setTimeLimit(600);

		// Create the subdirectories for css, javascript and font files.
		mkdir($this->theme_dir . '/css', 0777);
		mkdir($this->theme_dir . '/scripts', 0777);
		mkdir($this->theme_dir . '/webfonts', 0777);

		// Copy over the default non-theme files.
		$to_copy = array('/index.php', '/index.template.php', '/scripts/theme.js');
		foreach ($to_copy as $file)
		{
			copy($settings['default_theme_dir'] . $file, $this->theme_dir . $file);
			@chmod($this->theme_dir . $file, 0777);
		}

		// And now the entire css, images and webfonts directories!
		copytree($settings['default_theme_dir'] . '/css', $this->theme_dir . '/css');
		copytree($settings['default_theme_dir'] . '/images', $this->theme_dir . '/images');
		copytree($settings['default_theme_dir'] . '/webfonts', $this->theme_dir . '/webfonts');
		package_flush_cache();

		$this->theme_name = $this->_req->post->copy;
		$this->images_url = $boardurl . '/themes/' . basename($this->theme_dir) . '/images';
		$this->theme_dir = realpath($this->theme_dir);

		// Lets get some data for the new theme (default theme (1), default settings (0)).
		$theme_values = loadThemeOptionsInto(1, 0, array(), array('theme_templates', 'theme_layers'));

		// Lets add a theme_info.xml to this theme.
		write_theme_info($this->_req->query->copy, $modSettings['elkVersion'], $this->theme_dir, $theme_values);
	}

	/**
	 * Set a theme option via javascript.
	 *
	 * What it does:
	 * - sets a theme option without outputting anything.
	 * - can be used with javascript, via a dummy image... (which doesn't require
	 *   the page to reload.)
	 * - requires someone who is logged in.
	 * - accessed via ?action=jsoption;var=variable;val=value;session_var=sess_id.
	 * - optionally contains &th=theme id
	 * - does not log access to the Who's Online log. (in index.php..)
	 */
	public function action_jsoption()
	{
		global $settings, $user_info, $options;

		// Check the session id.
		checkSession('get');

		// This good-for-nothing pixel is being used to keep the session alive.
		if (empty($this->_req->query->var) || !isset($this->_req->query->val))
			redirectexit($settings['images_url'] . '/blank.png');

		// Sorry, guests can't go any further than this..
		if ($user_info['is_guest'] || $user_info['id'] == 0)
			obExit(false);

		$reservedVars = array(
			'actual_theme_url',
			'actual_images_url',
			'base_theme_dir',
			'base_theme_url',
			'default_images_url',
			'default_theme_dir',
			'default_theme_url',
			'default_template',
			'images_url',
			'number_recent_posts',
			'smiley_sets_default',
			'theme_dir',
			'theme_id',
			'theme_layers',
			'theme_templates',
			'theme_url',
			'name',
		);

		// Can't change reserved vars.
		if (in_array(strtolower($this->_req->query->var), $reservedVars))
			redirectexit($settings['images_url'] . '/blank.png');

		// Use a specific theme?
		if (isset($this->_req->query->th) || isset($this->_req->query->id))
		{
			// Invalidate the current themes cache too.
			Cache::instance()->remove('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id']);

			$settings['theme_id'] = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval'));
		}

		// If this is the admin preferences the passed value will just be an element of it.
		if ($this->_req->query->var == 'admin_preferences')
		{
			if (!empty($options['admin_preferences']))
			{
				$options['admin_preferences'] = serializeToJson($options['admin_preferences'], function ($array_form) {
					global $context;

					$context['admin_preferences'] = $array_form;
					require_once(SUBSDIR . '/Admin.subs.php');
					updateAdminPreferences();
				});
			}
			else
			{
				$options['admin_preferences'] = array();
			}

			// New thingy...
			if (isset($this->_req->query->admin_key) && strlen($this->_req->query->admin_key) < 5)
				$options['admin_preferences'][$this->_req->query->admin_key] = $this->_req->query->val;

			// Change the value to be something nice,
			$this->_req->query->val = json_encode($options['admin_preferences']);
		}
		// If this is the window min/max settings, the passed window name will just be an element of it.
		elseif ($this->_req->query->var == 'minmax_preferences')
		{
			if (!empty($options['minmax_preferences']))
			{
				$minmax_preferences = serializeToJson($options['minmax_preferences'], function ($array_form) {
					global $settings, $user_info;

					// Update the option.
					require_once(SUBSDIR . '/Themes.subs.php');
					updateThemeOptions(array($settings['theme_id'], $user_info['id'], 'minmax_preferences', json_encode($array_form)));
				});
			}
			else
			{
				$minmax_preferences = array();
			}

			// New value for them
			if (isset($this->_req->query->minmax_key) && strlen($this->_req->query->minmax_key) < 10)
				$minmax_preferences[$this->_req->query->minmax_key] = $this->_req->query->val;

			// Change the value to be something nice,
			$this->_req->query->val = json_encode($minmax_preferences);
		}

		// Update the option.
		require_once(SUBSDIR . '/Themes.subs.php');
		updateThemeOptions(array($settings['theme_id'], $user_info['id'], $this->_req->query->var, is_array($this->_req->query->val) ? implode(',', $this->_req->query->val) : $this->_req->query->val));

		Cache::instance()->remove('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id']);

		// Don't output anything...
		redirectexit($settings['images_url'] . '/blank.png');
	}

	/**
	 * Allows choosing, browsing, and editing a themes files.
	 *
	 * What it does:
	 * - Its subactions handle several features:
	 *   - edit_template: display and edit a PHP template file
	 *   - edit_style: display and edit a CSS file
	 *   - edit_file: display and edit other files in the theme
	 * - accessed via ?action=admin;area=theme;sa=edit
	 *
	 * @uses the ManageThemes template
	 */
	public function action_edit()
	{
		global $context;

		loadTemplate('ManageThemes');

		// We'll work hard with them themes!
		require_once(SUBSDIR . '/Themes.subs.php');

		$selectedTheme = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval', 0));

		// Unfortunately we cannot edit an unknown theme.. redirect.
		if (empty($selectedTheme))
			redirectexit('action=admin;area=theme;sa=themelist');
		// You're browsing around, aren't you
		elseif (!isset($this->_req->query->filename) && !isset($this->_req->post->save))
			redirectexit('action=admin;area=theme;sa=browse;th=' . $selectedTheme);

		// We don't have errors. Yet.
		$context['session_error'] = false;

		// We're editing a theme file.
		// Get the directory of the theme we are editing.
		$context['theme_id'] = $selectedTheme;
		$this->theme_dir = themeDirectory($context['theme_id']);

		$this->prepareThemeEditContext();

		// Saving?
		if (isset($this->_req->post->save))
		{
			$this->_action_edit_submit();

			// Now lets get out of here!
			return;
		}

		// We're editing .css, .template.php, .{language}.php or others.
		// Note: we're here sending $theme_dir as parameter to action_()
		// controller functions, which isn't cool. To be refactored.
		if (substr($this->_req->query->filename, -4) == '.css')
			$this->_action_edit_style();
		elseif (substr($this->_req->query->filename, -13) == '.template.php')
			$this->_action_edit_template();
		else
			$this->_action_edit_file();

		// Create a special token to allow editing of multiple files.
		createToken('admin-te-' . md5($selectedTheme . '-' . $this->_req->query->filename));
	}

	/**
	 * Displays for editing in admin panel a css file.
	 *
	 * This function is forwarded to, from
	 * ?action=admin;area=theme;sa=edit
	 */
	private function _action_edit_style()
	{
		global $context, $settings;

		addJavascriptVar(array(
			'previewData' => '',
			'previewTimeout' => '',
			'refreshPreviewCache' => '',
			'editFilename' => $context['edit_filename'],
			'theme_id' => $settings['theme_id'],
		), true);

		// pick the template and send it the file
		$context['sub_template'] = 'edit_style';
		$context['entire_file'] = htmlspecialchars(strtr(file_get_contents($this->theme_dir . '/' . $this->_req->query->filename), array("\t" => '   ')), ENT_COMPAT, 'UTF-8');
	}

	/**
	 * Displays for editing in the admin panel a template file.
	 *
	 * This function is forwarded to, from
	 * ?action=admin;area=theme;sa=edit
	 */
	private function _action_edit_template()
	{
		global $context;

		// Make sure the sub-template is set
		$context['sub_template'] = 'edit_template';

		// Retrieve the contents of the file
		$file_data = file($this->theme_dir . '/' . $this->_req->query->filename);

		// For a PHP template file, we display each function in separate boxes.
		$j = 0;
		$context['file_parts'] = array(array('lines' => 0, 'line' => 1, 'data' => '', 'function' => ''));
		for ($i = 0, $n = count($file_data); $i < $n; $i++)
		{
			// @todo refactor this so the docblocks are in the function content window
			if (substr($file_data[$i], 0, 9) === 'function ')
			{
				// Try to format the functions a little nicer...
				$context['file_parts'][$j]['data'] = trim($context['file_parts'][$j]['data']);

				if (empty($context['file_parts'][$j]['lines']))
					unset($context['file_parts'][$j]);

				// Start a new function block
				$context['file_parts'][++$j] = array('lines' => 0, 'line' => $i, 'data' => '');
			}

			$context['file_parts'][$j]['lines']++;
			$context['file_parts'][$j]['data'] .= htmlspecialchars(strtr($file_data[$i], array("\t" => '   ')), ENT_COMPAT, 'UTF-8');
		}

		$context['entire_file'] = htmlspecialchars(strtr(implode('', $file_data), array("\t" => '   ')), ENT_COMPAT, 'UTF-8');
	}

	/**
	 * Handles editing in admin of other types of files from a theme,
	 * except templates and css.
	 *
	 * This function is forwarded to, from
	 * ?action=admin;area=theme;sa=edit
	 */
	private function _action_edit_file()
	{
		global $context;

		// Simply set the template and the file contents.
		$context['sub_template'] = 'edit_file';
		$context['entire_file'] = htmlspecialchars(strtr(file_get_contents($this->theme_dir . '/' . $this->_req->query->filename), array("\t" => '   ')), ENT_COMPAT, 'UTF-8');
	}

	/**
	 * This function handles submission of a template file.
	 * It checks the file for syntax errors, and if it passes, it saves it.
	 *
	 * This function is forwarded to, from
	 * ?action=admin;area=theme;sa=edit
	 */
	private function _action_edit_submit()
	{
		global $context, $settings, $user_info;

		$selectedTheme = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval', 0));
		if (empty($selectedTheme))
		{
			// This should never be happening. Never I say. But... in case it does :P
			Errors::instance()->fatal_lang_error('theme_edit_missing');
		}

		$theme_dir = themeDirectory($context['theme_id']);
		$file = isset($this->_req->post->entire_file) ? $this->_req->post->entire_file : '';

		// You did submit *something*, didn't you?
		if (empty($file))
		{
			// @todo a better error message
			Errors::instance()->fatal_lang_error('theme_edit_missing');
		}

		// Checking PHP syntax on css files is not a most constructive use of processing power :P
		// We need to know what kind of file we have
		$is_php = substr($this->_req->post->filename, -4) == '.php';
		$is_template = substr($this->_req->post->filename, -13) == '.template.php';
		$is_css = substr($this->_req->post->filename, -4) == '.css';

		// Check you up
		if (checkSession('post', '', false) === '' && validateToken('admin-te-' . md5($selectedTheme . '-' . $this->_req->post->filename), 'post', false) === true)
		{
			// Consolidate the format in which we received the file contents
			if (is_array($file))
				$entire_file = implode("\n", $file);
			else
				$entire_file = $file;

			// Convert our tabs back to tabs!
			$entire_file = rtrim(strtr($entire_file, array("\r" => '', '   ' => "\t")));

			// Errors? No errors!
			$errors = array();

			// For PHP files, we check the syntax.
			if ($is_php)
			{
				require_once(SUBSDIR . '/Modlog.subs.php');

				// Since we are running php code, let's track it, but only once in a while.
				if (!recentlyLogged('editing_theme', 60))
				{
					logAction('editing_theme', array('member' => $user_info['id']), 'admin');

					// But the email only once every 60 minutes should be fine
					if (!recentlyLogged('editing_theme', 3600))
					{
						require_once(SUBSDIR . '/Themes.subs.php');
						require_once(SUBSDIR . '/Admin.subs.php');

						$theme_info = getBasicThemeInfos($context['theme_id']);
						emailAdmins('editing_theme', array(
							'EDIT_REALNAME' => $user_info['name'],
							'FILE_EDITED' => $this->_req->post->filename,
							'THEME_NAME' => $theme_info[$context['theme_id']],
						));
					}
				}

				$validator = new Data_Validator();
				$validator->validation_rules(array(
					'entire_file' => 'php_syntax'
				));
				$validator->validate(array('entire_file' => $entire_file));

				// Retrieve the errors
				$errors = $validator->validation_errors();
			}

			// If successful so far, we'll take the plunge and save this piece of art.
			if (empty($errors))
			{
				// Try to save the new file contents
				$fp = fopen($theme_dir . '/' . $this->_req->post->filename, 'w');
				fwrite($fp, $entire_file);
				fclose($fp);

				if (function_exists('opcache_invalidate'))
					opcache_invalidate($theme_dir . '/' . $_REQUEST['filename']);

				// We're done here.
				redirectexit('action=admin;area=theme;th=' . $selectedTheme . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=browse;directory=' . dirname($this->_req->post->filename));
			}
			// I can't let you off the hook yet: syntax errors are a nasty beast.
			else
			{
				// Pick the right sub-template for the next try
				if ($is_template)
					$context['sub_template'] = 'edit_template';
				else
					$context['sub_template'] = 'edit_file';

				// Fill contextual data for the template, the errors to show
				foreach ($errors as $error)
					$context['parse_error'][] = $error;

				// The format of the data depends on template/non-template file.
				if (!is_array($file))
					$file = array($file);

				// Send back the file contents
				$context['entire_file'] = htmlspecialchars(strtr(implode('', $file), array("\t" => '   ')), ENT_COMPAT, 'UTF-8');

				foreach ($file as $i => $file_part)
				{
					$context['file_parts'][$i]['lines'] = strlen($file_part);
					$context['file_parts'][$i]['data'] = $file_part;
				}

				// Re-create token for another try
				createToken('admin-te-' . md5($selectedTheme . '-' . $this->_req->post->filename));

				return;
			}
		}
		// Session timed out.
		else
		{
			loadLanguage('Errors');

			// Notify the template of trouble
			$context['session_error'] = true;

			// Recycle the submitted data.
			if (is_array($file))
				$context['entire_file'] = htmlspecialchars(implode("\n", $file), ENT_COMPAT, 'UTF-8');
			else
				$context['entire_file'] = htmlspecialchars($file, ENT_COMPAT, 'UTF-8');

			$context['edit_filename'] = htmlspecialchars($this->_req->post->filename, ENT_COMPAT, 'UTF-8');

			// Choose sub-template
			if ($is_template)
				$context['sub_template'] = 'edit_template';
			elseif ($is_css)
			{
				addJavascriptVar(array(
					'previewData' => '\'\'',
					'previewTimeout' => '\'\'',
					'refreshPreviewCache' => '\'\'',
					'editFilename' => JavaScriptEscape($context['edit_filename']),
					'theme_id' => $settings['theme_id'],
				));
				$context['sub_template'] = 'edit_style';
			}
			else
				$context['sub_template'] = 'edit_file';

			// Re-create the token so that it can be used
			createToken('admin-te-' . md5($selectedTheme . '-' . $this->_req->post->filename));

			return;
		}
	}

	/**
	 * Handles user browsing in theme directories.
	 *
	 * What it does:
	 * - The display will allow to choose a file for editing,
	 * if it is writable.
	 * - accessed with ?action=admin;area=theme;sa=browse
	 */
	public function action_browse()
	{
		global $context, $scripturl;

		loadTemplate('ManageThemes');

		// We'll work hard with them themes!
		require_once(SUBSDIR . '/Themes.subs.php');

		$selectedTheme = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval', 0));
		if (empty($selectedTheme))
			redirectexit('action=admin;area=theme;sa=themelist');

		// Get first the directory of the theme we are editing.
		$context['theme_id'] = isset($this->_req->query->th) ? (int) $this->_req->query->th : (isset($this->_req->query->id) ? (int) $this->_req->query->id : 0);
		$theme_dir = themeDirectory($context['theme_id']);

		// Eh? not trying to sneak a peek outside the theme directory are we
		if (!file_exists($theme_dir . '/index.template.php') && !file_exists($theme_dir . '/css/index.css'))
			Errors::instance()->fatal_lang_error('theme_edit_missing', false);

		// Now, where exactly are you?
		if (isset($this->_req->query->directory))
		{
			if (substr($this->_req->query->directory, 0, 1) === '.')
				$this->_req->query->directory = '';
			else
			{
				$this->_req->query->directory = preg_replace(array('~^[\./\\:\0\n\r]+~', '~[\\\\]~', '~/[\./]+~'), array('', '/', '/'), $this->_req->query->directory);

				$temp = realpath($theme_dir . '/' . $this->_req->query->directory);
				if (empty($temp) || substr($temp, 0, strlen(realpath($theme_dir))) != realpath($theme_dir))
					$this->_req->query->directory = '';
			}
		}

		if (isset($this->_req->query->directory) && $this->_req->query->directory != '')
		{
			$context['theme_files'] = get_file_listing($theme_dir . '/' . $this->_req->query->directory, $this->_req->query->directory . '/');

			$temp = dirname($this->_req->query->directory);
			array_unshift($context['theme_files'], array(
				'filename' => $temp == '.' || $temp == '' ? '/ (..)' : $temp . ' (..)',
				'is_writable' => is_writable($theme_dir . '/' . $temp),
				'is_directory' => true,
				'is_template' => false,
				'is_image' => false,
				'is_editable' => false,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $context['theme_id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=browse;directory=' . $temp,
				'size' => '',
			));
		}
		else
			$context['theme_files'] = get_file_listing($theme_dir, '');

		// finally, load the sub-template
		$context['sub_template'] = 'browse';
	}

	/**
	 * List installed themes.
	 * The listing will allow editing if the files are writable.
	 */
	public function action_themelist()
	{
		global $context;

		loadTemplate('ManageThemes');

		// We'll work hard with them themes!
		require_once(SUBSDIR . '/Themes.subs.php');

		$context['themes'] = installedThemes();

		foreach ($context['themes'] as $key => $theme)
		{
			// There has to be a Settings template!
			if (!file_exists($theme['theme_dir'] . '/index.template.php') && !file_exists($theme['theme_dir'] . '/css/index.css'))
				unset($context['themes'][$key]);
			else
			{
				if (!isset($theme['theme_templates']))
					$templates = array('index');
				else
					$templates = explode(',', $theme['theme_templates']);

				foreach ($templates as $template)
					if (file_exists($theme['theme_dir'] . '/' . $template . '.template.php'))
					{
						// Fetch the header... a good 256 bytes should be more than enough.
						$fp = fopen($theme['theme_dir'] . '/' . $template . '.template.php', 'rb');
						$header = fread($fp, 256);
						fclose($fp);

						// Can we find a version comment, at all?
						if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
						{
							$ver = $match[1];
							if (!isset($context['themes'][$key]['version']) || $context['themes'][$key]['version'] > $ver)
								$context['themes'][$key]['version'] = $ver;
						}
					}

				$context['themes'][$key]['can_edit_style'] = file_exists($theme['theme_dir'] . '/css/index.css');
			}
		}

		$context['sub_template'] = 'themelist';
	}

	/**
	 * Makes a copy of a template file in a new location
	 *
	 * @uses ManageThemes template, copy_template sub-template.
	 */
	public function action_copy()
	{
		global $context, $settings;

		loadTemplate('ManageThemes');
		require_once(SUBSDIR . '/Themes.subs.php');

		$context[$context['admin_menu_name']]['current_subsection'] = 'edit';

		$context['theme_id'] = isset($this->_req->query->th) ? (int) $this->_req->query->th : (int) $this->_req->query->id;

		$theme_dirs = array();
		$theme_dirs = loadThemeOptionsInto($context['theme_id'], null, $theme_dirs, array('base_theme_dir', 'theme_dir'));

		if (isset($this->_req->query->template) && preg_match('~[\./\\\\:\0]~', $this->_req->query->template) == 0)
		{
			if (!empty($theme_dirs['base_theme_dir']) && file_exists($theme_dirs['base_theme_dir'] . '/' . $this->_req->query->template . '.template.php'))
				$filename = $theme_dirs['base_theme_dir'] . '/' . $this->_req->query->template . '.template.php';
			elseif (file_exists($settings['default_theme_dir'] . '/' . $this->_req->query->template . '.template.php'))
				$filename = $settings['default_theme_dir'] . '/' . $this->_req->query->template . '.template.php';
			else
				Errors::instance()->fatal_lang_error('no_access', false);

			$fp = fopen($theme_dirs['theme_dir'] . '/' . $this->_req->query->template . '.template.php', 'w');
			fwrite($fp, file_get_contents($filename));
			fclose($fp);

			if (function_exists('opcache_invalidate'))
				opcache_invalidate($filename);

			redirectexit('action=admin;area=theme;th=' . $context['theme_id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=copy');
		}
		elseif (isset($this->_req->query->lang_file) && preg_match('~^[^\./\\\\:\0]\.[^\./\\\\:\0]$~', $this->_req->query->lang_file) != 0)
		{
			if (!empty($theme_dirs['base_theme_dir']) && file_exists($theme_dirs['base_theme_dir'] . '/languages/' . $this->_req->query->lang_file . '.php'))
				$filename = $theme_dirs['base_theme_dir'] . '/languages/' . $this->_req->query->template . '.php';
			elseif (file_exists($settings['default_theme_dir'] . '/languages/' . $this->_req->query->template . '.php'))
				$filename = $settings['default_theme_dir'] . '/languages/' . $this->_req->query->template . '.php';
			else
				Errors::instance()->fatal_lang_error('no_access', false);

			$fp = fopen($theme_dirs['theme_dir'] . '/languages/' . $this->_req->query->lang_file . '.php', 'w');
			fwrite($fp, file_get_contents($filename));
			fclose($fp);

			if (function_exists('opcache_invalidate'))
				opcache_invalidate($filename);

			redirectexit('action=admin;area=theme;th=' . $context['theme_id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=copy');
		}

		$templates = array();
		$lang_files = array();

		$dir = dir($settings['default_theme_dir']);
		while ($entry = $dir->read())
		{
			if (substr($entry, -13) == '.template.php')
				$templates[] = substr($entry, 0, -13);
		}
		$dir->close();

		$dir = dir($settings['default_theme_dir'] . '/languages');
		while ($entry = $dir->read())
		{
			if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches))
				$lang_files[] = $matches[1];
		}
		$dir->close();

		if (!empty($theme_dirs['base_theme_dir']))
		{
			$dir = dir($theme_dirs['base_theme_dir']);
			while ($entry = $dir->read())
			{
				if (substr($entry, -13) == '.template.php' && !in_array(substr($entry, 0, -13), $templates))
					$templates[] = substr($entry, 0, -13);
			}
			$dir->close();

			if (file_exists($theme_dirs['base_theme_dir'] . '/languages'))
			{
				$dir = dir($theme_dirs['base_theme_dir'] . '/languages');
				while ($entry = $dir->read())
				{
					if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches) && !in_array($matches[1], $lang_files))
						$lang_files[] = $matches[1];
				}
				$dir->close();
			}
		}

		natcasesort($templates);
		natcasesort($lang_files);

		$context['available_templates'] = array();
		foreach ($templates as $template)
			$context['available_templates'][$template] = array(
				'filename' => $template . '.template.php',
				'value' => $template,
				'already_exists' => false,
				'can_copy' => is_writable($theme_dirs['theme_dir']),
			);
		$context['available_language_files'] = array();
		foreach ($lang_files as $file)
			$context['available_language_files'][$file] = array(
				'filename' => $file . '.php',
				'value' => $file,
				'already_exists' => false,
				'can_copy' => file_exists($theme_dirs['theme_dir'] . '/languages') ? is_writable($theme_dirs['theme_dir'] . '/languages') : is_writable($theme_dirs['theme_dir']),
			);

		$dir = dir($theme_dirs['theme_dir']);
		while ($entry = $dir->read())
		{
			if (substr($entry, -13) == '.template.php' && isset($context['available_templates'][substr($entry, 0, -13)]))
			{
				$context['available_templates'][substr($entry, 0, -13)]['already_exists'] = true;
				$context['available_templates'][substr($entry, 0, -13)]['can_copy'] = is_writable($theme_dirs['theme_dir'] . '/' . $entry);
			}
		}
		$dir->close();

		if (file_exists($theme_dirs['theme_dir'] . '/languages'))
		{
			$dir = dir($theme_dirs['theme_dir'] . '/languages');
			while ($entry = $dir->read())
			{
				if (preg_match('~^([^\.]+\.[^\.]+)\.php$~', $entry, $matches) && isset($context['available_language_files'][$matches[1]]))
				{
					$context['available_language_files'][$matches[1]]['already_exists'] = true;
					$context['available_language_files'][$matches[1]]['can_copy'] = is_writable($theme_dirs['theme_dir'] . '/languages/' . $entry);
				}
			}
			$dir->close();
		}

		$context['sub_template'] = 'copy_template';
	}

	/**
	 * This function makes necessary pre-checks and fills
	 * the contextual data as needed by theme editing functions.
	 */
	private function prepareThemeEditContext()
	{
		global $context;

		// Eh? not trying to sneak a peek outside the theme directory are we
		if (!file_exists($this->theme_dir . '/index.template.php') && !file_exists($this->theme_dir . '/css/index.css'))
			Errors::instance()->fatal_lang_error('theme_edit_missing', false);

		// Get the filename from the appropriate spot
		$filename = isset($this->_req->post->save) ? $this->_req->getPost('filename', 'strval', '') : $this->_req->getQuery('filename', 'strval', '');

		// You're editing a file: we have extra-checks coming up first.
		if (substr($filename, 0, 1) === '.')
			$filename = '';
		else
		{
			$filename = preg_replace(array('~^[\./\\:\0\n\r]+~', '~[\\\\]~', '~/[\./]+~'), array('', '/', '/'), $filename);

			$temp = realpath($this->theme_dir . '/' . $filename);
			if (empty($temp) || substr($temp, 0, strlen(realpath($this->theme_dir))) !== realpath($this->theme_dir))
				$filename = '';
		}

		// We shouldn't end up with no file
		if (empty($filename))
			Errors::instance()->fatal_lang_error('theme_edit_missing', false);

		// Initialize context
		$context['allow_save'] = is_writable($this->theme_dir . '/' . $filename);
		$context['allow_save_filename'] = strtr($this->theme_dir . '/' . $filename, array(BOARDDIR => '...'));
		$context['edit_filename'] = htmlspecialchars($filename, ENT_COMPAT, 'UTF-8');
	}
}