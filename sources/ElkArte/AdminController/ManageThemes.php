<?php

/**
 * This file concerns itself almost completely with theme administration.
 * Its tasks include changing theme settings, installing and removing
 * themes, choosing the current theme, and editing themes.
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
 *
 * @todo Update this for the new package manager?
 *
 * Creating and distributing theme packages:
 * There isn't that much required to package and distribute your own themes...
 * just do the following:
 *
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

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Cache\Cache;
use ElkArte\Exceptions\Exception;
use ElkArte\FileFunctions;
use ElkArte\Themes\ThemeLoader;
use ElkArte\Languages\Txt;
use ElkArte\User;
use ElkArte\Util;
use ElkArte\XmlArray;

/**
 * Class to deal with theme administration.
 *
 * Its tasks include changing theme settings, installing and removing
 * themes, choosing the current theme, and editing themes.
 *
 * @package Themes
 */
class ManageThemes extends AbstractController
{
	/** @var string Name of the theme */
	private $theme_name;

	/** @var string Full path to the theme */
	private $theme_dir;

	/** @var string|null The themes image url if any */
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
	 *
	 * - It loads both the Themes and Settings language files.
	 * - Checks the session by GET or POST to verify the data.
	 * - Requires the user to not be a guest.
	 * - Accessed via ?action=admin;area=theme.
	 *
	 * @see \ElkArte\AbstractController::action_index()
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
		Txt::load('ManageThemes');
		Txt::load('Settings');

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
			'pick' => array($this, 'action_pick', 'permission' => 'admin_forum'),
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
		global $txt, $context;

		theme()->getTemplates()->load('Xml');

		// Remove any template layers that may have been created, this is XML!
		theme()->getLayers()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		// No guests in here.
		if ($this->user->is_guest)
		{
			Txt::load('Errors');
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
		);

		// Follow the sa or just go to administration.
		if (isset($this->_req->query->sa) && !empty($subActions[$this->_req->query->sa]))
		{
			$this->{$subActions[$this->_req->query->sa]}();
		}
		else
		{
			Txt::load('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['error_sa_not_set']
			);
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
		Txt::load('Admin');
		$fileFunc = FileFunctions::instance();

		if (isset($this->_req->query->th))
		{
			$this->action_setthemesettings();
			return;
		}

		// Saving?
		if (isset($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-tl');

			$themes = installedThemes();
			$setValues = array();

			foreach ($themes as $id => $theme)
			{
				if ($fileFunc->isDir($this->_req->post->reset_dir . '/' . basename($theme['theme_dir'])))
				{
					$setValues[] = array($id, 0, 'theme_dir', realpath($this->_req->post->reset_dir . '/' . basename($theme['theme_dir'])));
					$setValues[] = array($id, 0, 'theme_url', $this->_req->post->reset_url . '/' . basename($theme['theme_dir']));
					$setValues[] = array($id, 0, 'images_url', $this->_req->post->reset_url . '/' . basename($theme['theme_dir']) . '/' . basename($theme['images_url']));
				}

				if (isset($theme['base_theme_dir']) && $fileFunc->isDir($this->_req->post->reset_dir . '/' . basename($theme['base_theme_dir'])))
				{
					$setValues[] = array($id, 0, 'base_theme_dir', realpath($this->_req->post->reset_dir . '/' . basename($theme['base_theme_dir'])));
					$setValues[] = array($id, 0, 'base_theme_url', $this->_req->post->reset_url . '/' . basename($theme['base_theme_dir']));
					$setValues[] = array($id, 0, 'base_images_url', $this->_req->post->reset_url . '/' . basename($theme['base_theme_dir']) . '/' . basename($theme['base_images_url']));
				}

				Cache::instance()->remove('theme_settings-' . $id);
			}

			if (!empty($setValues))
			{
				updateThemeOptions($setValues);
			}

			redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id']);
		}

		theme()->getTemplates()->load('ManageThemes');

		$context['themes'] = installedThemes();

		// For each theme, make sure the directory exists, and try to fetch the theme version
		foreach ($context['themes'] as $i => $theme)
		{
			$context['themes'][$i]['theme_dir'] = realpath($context['themes'][$i]['theme_dir']);

			if ($fileFunc->fileExists($context['themes'][$i]['theme_dir'] . '/index.template.php'))
			{
				// Fetch the header... a good 256 bytes should be more than enough.
				$fp = fopen($context['themes'][$i]['theme_dir'] . '/index.template.php', 'rb');
				$header = fread($fp, 256);
				fclose($fp);

				// Can we find a version comment, at all?
				if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
				{
					$context['themes'][$i]['version'] = $match[1];
				}
			}

			$context['themes'][$i]['valid_path'] = $fileFunc->isDir($context['themes'][$i]['theme_dir']);
		}

		// Off to the template we go
		$context['sub_template'] = 'list_themes';
		theme()->addJavascriptVar(array('txt_theme_remove_confirm' => $txt['theme_remove_confirm']), true);
		$context['reset_dir'] = realpath(BOARDDIR . '/themes');
		$context['reset_url'] = $boardurl . '/themes';

		createToken('admin-tl');
		createToken('admin-tr', 'request');
	}

	/**
	 * Administrative global settings.
	 *
	 * What it does:
	 *
	 * - Saves and requests global theme settings. ($settings)
	 * - Loads the Admin language file.
	 * - Calls action_admin() if no theme is specified. (the theme center.)
	 * - Requires admin_forum permission.
	 * - Accessed with ?action=admin;area=theme;sa=list&th=xx.
	 *
	 * @event integrate_init_theme
	 */
	public function action_setthemesettings()
	{
		global $txt, $context, $settings, $modSettings;

		require_once(SUBSDIR . '/Themes.subs.php');
		$fileFunc = FileFunctions::instance();

		// Nothing chosen, back to the start you go
		$theme = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval', 0));
		if (empty($theme))
		{
			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
		}

		// The theme's ID is needed
		$theme = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval', 0));

		// Validate inputs/user.
		if (empty($theme))
		{
			throw new Exception('no_theme', false);
		}

		// Select the best fitting tab.
		$context[$context['admin_menu_name']]['current_subsection'] = 'list';
		Txt::load('Admin');

		// Fetch the smiley sets...
		$sets = explode(',', 'none,' . $modSettings['smiley_sets_known']);
		$set_names = explode("\n", $txt['smileys_none'] . "\n" . $modSettings['smiley_sets_names']);
		$context['smiley_sets'] = array('' => $txt['smileys_no_default']);
		foreach ($sets as $i => $set)
		{
			$context['smiley_sets'][$set] = htmlspecialchars($set_names[$i], ENT_COMPAT);
		}

		$old_id = $settings['theme_id'];
		$old_settings = $settings;

		new ThemeLoader($theme, false);

		// Also load the actual themes language file - in case of special settings.
		Txt::load('Settings', false, true);

		// And the custom language strings...
		Txt::load('ThemeStrings', false);

		// Let the theme take care of the settings.
		theme()->getTemplates()->load('Settings');
		theme()->getTemplates()->loadSubTemplate('settings');

		// Load the variants separately...
		if ($fileFunc->fileExists($settings['theme_dir'] . '/index.template.php'))
		{
			$variants = theme()->getSettings();
			$settings['theme_variants'] = $variants['theme_variants'] ?? array();
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
				{
					continue;
				}

				// Clean them up for the database
				foreach (array('options', 'default_options') as $option)
				{
					if (!isset($options[$option][$item['id']]))
					{
						continue;
					}
					// Checkbox.
					elseif (empty($item['type']))
					{
						$options[$option][$item['id']] = $options[$option][$item['id']] ? 1 : 0;
					}
					// Number
					elseif ($item['type'] === 'number')
					{
						$options[$option][$item['id']] = (int) $options[$option][$item['id']];
					}
				}
			}

			// Set up the sql query.
			$inserts = array();
			foreach ($options['options'] as $opt => $val)
			{
				$inserts[] = array($theme, 0, $opt, is_array($val) ? implode(',', $val) : $val);
			}

			foreach ($options['default_options'] as $opt => $val)
			{
				$inserts[] = array(1, 0, $opt, is_array($val) ? implode(',', $val) : $val);
			}

			// If we're actually inserting something..
			if (!empty($inserts))
			{
				updateThemeOptions($inserts);
			}

			// Clear and Invalidate the cache.
			Cache::instance()->remove('theme_settings-' . $theme);
			Cache::instance()->remove('theme_settings-1');
			updateSettings(array('settings_updated' => time()));

			redirectexit('action=admin;area=theme;sa=list;th=' . $theme . ';' . $context['session_var'] . '=' . $context['session_id']);
		}

		$context['sub_template'] = 'set_settings';
		$context['page_title'] = $txt['theme_settings'];

		foreach ($settings as $setting => $set)
		{
			if (!in_array($setting, array('theme_url', 'theme_dir', 'images_url', 'template_dirs')))
			{
				$settings[$setting] = Util::htmlspecialchars__recursive($set);
			}
		}

		$context['settings'] = $context['theme_settings'];
		$context['theme_settings'] = $settings;

		foreach ($context['settings'] as $i => $setting)
		{
			// Separators are dummies, so leave them alone.
			if (!is_array($setting))
			{
				continue;
			}

			// Create the right input fields for the data
			if (!isset($setting['type']) || $setting['type'] === 'bool')
			{
				$context['settings'][$i]['type'] = 'checkbox';
			}
			elseif ($setting['type'] === 'int' || $setting['type'] === 'integer')
			{
				$context['settings'][$i]['type'] = 'number';
			}
			elseif ($setting['type'] === 'string')
			{
				$context['settings'][$i]['type'] = 'text';
			}

			if (isset($setting['options']))
			{
				$context['settings'][$i]['type'] = 'list';
			}

			$context['settings'][$i]['value'] = $settings[$setting['id']] ?? '';
		}

		// Do we support variants?
		if (!empty($settings['theme_variants']))
		{
			$context['theme_variants'] = array();
			foreach ($settings['theme_variants'] as $variant)
			{
				// Have any text, old chap?
				$context['theme_variants'][$variant] = array(
					'label' => $txt['variant_' . $variant] ?? $variant,
					'thumbnail' => !$fileFunc->fileExists($settings['theme_dir'] . '/images/thumbnail.png') || $fileFunc->fileExists($settings['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? $settings['images_url'] . '/thumbnail_' . $variant . '.png' : ($settings['images_url'] . '/thumbnail.png'),
				);
			}
			$context['default_variant'] = !empty($settings['default_variant']) && isset($context['theme_variants'][$settings['default_variant']]) ? $settings['default_variant'] : $settings['theme_variants'][0];
		}

		// Restore the current theme.
		new ThemeLoader($old_id, true);

		$settings = $old_settings;

		// Reinit just incase.
		theme()->getSettings();

		theme()->getTemplates()->load('ManageThemes');

		createToken('admin-sts');
	}

	/**
	 * This function allows administration of themes and their settings,
	 * as well as global theme settings.
	 *
	 * What it does:
	 *
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

		Txt::load('Admin');

		// Saving?
		if (isset($this->_req->post->save))
		{
			checkSession();
			validateToken('admin-tm');

			// What themes are being made as known to the members
			if (isset($this->_req->post->options['known_themes']))
			{
				foreach ($this->_req->post->options['known_themes'] as $key => $id)
				{
					$this->_req->post->options['known_themes'][$key] = (int) $id;
				}
			}
			else
			{
				throw new Exception('themes_none_selectable', false);
			}

			if (!in_array($this->_req->post->options['theme_guests'], $this->_req->post->options['known_themes']))
			{
				throw new Exception('themes_default_selectable', false);
			}

			// Commit the new settings.
			updateSettings(array(
				'theme_allow' => !empty($this->_req->post->options['theme_allow']),
				'theme_guests' => $this->_req->post->options['theme_guests'],
				'knownThemes' => implode(',', $this->_req->post->options['known_themes']),
			));

			if ((int) $this->_req->post->theme_reset === 0 || in_array($this->_req->post->theme_reset, $this->_req->post->options['known_themes']))
			{
				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData(null, array('id_theme' => (int) $this->_req->post->theme_reset));
			}

			redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=admin');
		}
		// If we aren't submitting - that is, if we are about to...
		else
		{
			$fileFunc = FileFunctions::instance();

			theme()->getTemplates()->load('ManageThemes');
			$context['sub_template'] = 'manage_themes';

			// Make our known themes a little easier to work with.
			$knownThemes = !empty($modSettings['knownThemes']) ? explode(',', $modSettings['knownThemes']) : array();

			// Load up all the themes.
			require_once(SUBSDIR . '/Themes.subs.php');
			$context['themes'] = loadThemes($knownThemes);

			// Can we create a new theme?
			$context['can_create_new'] = $fileFunc->isWritable(BOARDDIR . '/themes');
			$context['new_theme_dir'] = substr(realpath(BOARDDIR . '/themes/default'), 0, -7);

			// Look for a nonexistent theme directory. (ie theme87.)
			$theme_dir = BOARDDIR . '/themes/theme';
			$i = 1;
			while ($fileFunc->isDir($theme_dir . $i))
			{
				$i++;
			}
			$context['new_theme_name'] = 'theme' . $i;

			createToken('admin-tm');
		}
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

		// No theme selected, so show the theme list with theme option count and member count not using default
		if (empty($theme))
		{
			$context['themes'] = installedThemes();

			// How many options do we have set for guests?
			$guestOptions = countConfiguredGuestOptions();
			foreach ($guestOptions as $guest_option)
			{
				$context['themes'][$guest_option['id_theme']]['num_default_options'] = $guest_option['value'];
			}

			// How many options do we have set for members?
			$memberOptions = countConfiguredMemberOptions();
			foreach ($memberOptions as $member_option)
			{
				$context['themes'][$member_option['id_theme']]['num_members'] = $member_option['value'];
			}

			// There has to be a Settings template!
			$fileFunc = FileFunctions::instance();
			foreach ($context['themes'] as $k => $v)
			{
				if (empty($v['theme_dir']) || (!$fileFunc->fileExists($v['theme_dir'] . '/Settings.template.php') && empty($v['num_members'])))
				{
					unset($context['themes'][$k]);
				}
			}

			theme()->getTemplates()->load('ManageThemes');
			$context['sub_template'] = 'reset_list';

			createToken('admin-stor', 'request');

			return;
		}

		// Submit?
		$who = $this->_req->getPost('who', 'intval', 0);
		if (isset($this->_req->post->submit) && empty($who))
		{
			checkSession();
			validateToken('admin-sto');

			$_options = $this->_req->getPost('options', '', array());
			$_default_options = $this->_req->getPost('default_options', '', array());

			// Set up the query values.
			$setValues = array();
			foreach ($_options as $opt => $val)
			{
				$setValues[] = array($theme, -1, $opt, is_array($val) ? implode(',', $val) : $val);
			}

			$old_settings = array();
			foreach ($_default_options as $opt => $val)
			{
				$old_settings[] = $opt;
				$setValues[] = array(1, -1, $opt, is_array($val) ? implode(',', $val) : $val);
			}

			// If we're actually inserting something..
			if (!empty($setValues))
			{
				// Are there options in non-default themes set that should be cleared?
				if (!empty($old_settings))
				{
					removeThemeOptions('custom', 'guests', $old_settings);
				}

				updateThemeOptions($setValues);
			}

			// Cache the theme settings
			Cache::instance()->remove('theme_settings-' . $theme);
			Cache::instance()->remove('theme_settings-1');

			redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
		}

		// Changing the current options for all members using this theme
		if (isset($this->_req->post->submit) && $who === 1)
		{
			checkSession();
			validateToken('admin-sto');

			$_options = $this->_req->getPost('options', '', array());
			$_options_master = $this->_req->getPost('options_master', '', array());

			$_default_options = $this->_req->getPost('default_options', '', array());
			$_default_options_master = $this->_req->getPost('default_options_master', '', array());

			$old_settings = array();
			foreach ($_default_options as $opt => $val)
			{
				if ($_default_options_master[$opt] == 0)
				{
					continue;
				}

				if ($_default_options_master[$opt] == 1)
				{
					// Delete then insert for ease of database compatibility!
					removeThemeOptions('default', 'members', $opt);
					addThemeOptions(1, $opt, $val);

					$old_settings[] = $opt;
				}
				elseif ($_default_options_master[$opt] == 2)
				{
					removeThemeOptions('all', 'members', $opt);
				}
			}

			// Delete options from other themes.
			if (!empty($old_settings))
			{
				removeThemeOptions('custom', 'members', $old_settings);
			}

			foreach ($_options as $opt => $val)
			{
				if ($_options_master[$opt] == 0)
				{
					continue;
				}

				if ($_options_master[$opt] == 1)
				{
					// Delete then insert for ease of database compatibility - again!
					removeThemeOptions($theme, 'non_default', $opt);
					addThemeOptions($theme, $opt, $val);
				}
				elseif ($_options_master[$opt] == 2)
				{
					removeThemeOptions($theme, 'all', $opt);
				}
			}

			redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
		}

		// Remove all members options and use the defaults
		if (!empty($this->_req->query->who) && $who === 2)
		{
			checkSession('get');
			validateToken('admin-stor', 'request');

			removeThemeOptions($theme, 'members');

			redirectexit('action=admin;area=theme;' . $context['session_var'] . '=' . $context['session_id'] . ';sa=reset');
		}

		$old_id = $settings['theme_id'];
		$old_settings = $settings;

		new ThemeLoader($theme, false);
		Txt::load('Profile');

		// @todo Should we just move these options so they are no longer theme dependant?
		Txt::load('PersonalMessage');

		// Let the theme take care of the settings.
		theme()->getTemplates()->load('Settings');
		theme()->getTemplates()->loadSubTemplate('options');

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
			if ($setting['id'] === 'calendar_start_day' && empty($modSettings['cal_enabled']))
			{
				unset($context['options'][$i]);
				continue;
			}
			elseif (($setting['id'] === 'topics_per_page' || $setting['id'] === 'messages_per_page') && !empty($modSettings['disableCustomPerPage']))
			{
				unset($context['options'][$i]);
				continue;
			}

			// Type of field so we display the right input field
			if (!isset($setting['type']) || $setting['type'] === 'bool')
			{
				$context['options'][$i]['type'] = 'checkbox';
			}
			elseif ($setting['type'] === 'int' || $setting['type'] === 'integer')
			{
				$context['options'][$i]['type'] = 'number';
			}
			elseif ($setting['type'] === 'string')
			{
				$context['options'][$i]['type'] = 'text';
			}

			if (isset($setting['options']))
			{
				$context['options'][$i]['type'] = 'list';
			}

			$context['options'][$i]['value'] = !isset($context['theme_options'][$setting['id']]) ? '' : $context['theme_options'][$setting['id']];
		}

		// Restore the existing theme and its settings.
		new ThemeLoader($old_id, true);
		$settings = $old_settings;

		theme()->getTemplates()->load('ManageThemes');
		createToken('admin-sto');
	}

	/**
	 * Remove a theme from the database.
	 *
	 * What it does:
	 *
	 * - Removes an installed theme.
	 * - Requires an administrator.
	 * - Accessed with ?action=admin;area=theme;sa=remove.
	 * - Does not remove files
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
		if ($theme === 1)
		{
			throw new Exception('no_access', false);
		}

		// Its no longer known
		$known = $this->_knownTheme($theme);

		// Remove it as an option everywhere
		deleteTheme($theme);

		// Fix it if the theme was the overall default theme.
		if ($modSettings['theme_guests'] === $theme)
		{
			updateSettings(array('theme_guests' => '1', 'knownThemes' => $known));
		}
		else
		{
			updateSettings(array('knownThemes' => $known));
		}

		redirectexit('action=admin;area=theme;sa=list;' . $context['session_var'] . '=' . $context['session_id']);
	}

	/**
	 * Small helper to return the list of known themes other than the current
	 *
	 * @param string $theme current theme
	 * @return string
	 */
	private function _knownTheme($theme)
	{
		global $modSettings;

		$known = explode(',', $modSettings['knownThemes']);
		foreach ($known as $i => $knew)
		{
			if ($knew === $theme)
			{
				// I knew them at one time
				unset($known[$i]);
			}
		}

		return strtr(implode(',', $known), array(',,' => ','));
	}

	/**
	 * Remove a theme from the database in response to an ajax api request
	 *
	 * What it does:
	 *
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
			Txt::load('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['session_verify_fail'],
			);

			return;
		}

		// Not just any John Smith can send in a api request
		if (!allowedTo('admin_forum'))
		{
			Txt::load('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['cannot_admin_forum'],
			);

			return;
		}

		// Even if you are John Smith, you still need a ticket
		if (!validateToken('admin-tr', 'request', true, false))
		{
			Txt::load('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['token_verify_fail'],
			);

			return;
		}

		// The theme's ID must be an integer.
		$theme = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval', 0));

		// You can't delete the default theme!
		if ($theme === 1)
		{
			Txt::load('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['no_access'],
			);

			return;
		}

		// It is a theme we know about?
		$known = $this->_knownTheme($theme);

		// Finally, remove it
		deleteTheme($theme);

		// Fix it if the theme was the overall default theme.
		if ($modSettings['theme_guests'] === $theme)
		{
			updateSettings(array('theme_guests' => '1', 'knownThemes' => $known));
		}
		else
		{
			updateSettings(array('knownThemes' => $known));
		}

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
	 *
	 * - Can edit everyone's (u = 0) or guests' (u = -1).
	 * - Uses the Themes template. (pick sub template.)
	 * - Accessed with ?action=admin;area=theme;sa=pick.
	 *
	 * @uses Profile language text
	 * @uses ManageThemes template
	 * with centralized admin permissions on ManageThemes.
	 */
	public function action_pick()
	{
		global $txt, $context, $modSettings;

		require_once(SUBSDIR . '/Themes.subs.php');

		theme()->getTemplates()->load('ManageThemes');

		// 0 is reset all members, -1 is set forum default
		$u = $this->_req->getQuery('u', 'intval');

		$context['default_theme_id'] = $modSettings['theme_default'];

		$_SESSION['theme'] = 0;

		if (isset($this->_req->query->id))
		{
			$this->_req->query->th = $this->_req->query->id;
		}

		// Saving a variant cause JS doesn't work - pretend it did ;)
		if (isset($this->_req->post->save))
		{
			// Which theme?
			foreach ($this->_req->post->save as $k => $v)
			{
				$this->_req->query->th = (int) $k;
			}

			if (isset($this->_req->post->vrt[$k]))
			{
				$this->_req->query->vrt = $this->_req->post->vrt[$k];
			}
		}

		// Have we made a decision, or are we just browsing?
		if (isset($this->_req->query->th))
		{
			checkSession('get');

			$th = $this->_req->getQuery('th', 'intval');
			$vrt = $this->_req->getQuery('vrt', 'cleanhtml');

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
				{
					deleteVariants($th);
				}

				redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
			}
			// Change the default/guest theme.
			elseif ($u === -1)
			{
				updateSettings(array('theme_guests' => $th));

				redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
			}
		}

		// Everyone can't choose just one.
		if ($u === 0)
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

		// Get the theme name and descriptions.
		list ($context['available_themes'], $guest_theme) = availableThemes($current_theme, $context['current_member']);

		// As long as we're not doing the default theme...
		if (!isset($u) || $u >= 0)
		{
			if ($guest_theme != 0)
			{
				$context['available_themes'][0] = $context['available_themes'][$guest_theme];
			}

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
	 *
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
		$fileFunc = FileFunctions::instance();

		theme()->getTemplates()->load('ManageThemes');

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
		{
			$method = 'upload';
		}
		elseif (isset($this->_req->post->theme_dir) && rtrim(realpath($this->_req->post->theme_dir), '/\\') != realpath(BOARDDIR . '/themes') && $fileFunc->isDir($this->_req->post->theme_dir))
		{
			$method = 'path';
		}
		else
		{
			$method = 'copy';
		}

		// Copy the default theme?
		if (!empty($this->_req->post->copy) && $method === 'copy')
		{
			$this->copyDefault();
		}
		// Install from another directory
		elseif (isset($this->_req->post->theme_dir) && $method === 'path')
		{
			$this->installFromDir();
		}
		// Uploaded a zip file to install from
		elseif ($method === 'upload')
		{
			$this->installFromZip();
		}
		else
		{
			throw new Exception('theme_install_general', false);
		}

		// Something go wrong?
		if ($this->theme_dir !== '' && basename($this->theme_dir) !== 'themes')
		{
			// Defaults.
			$install_info = array(
				'theme_url' => $boardurl . '/themes/' . basename($this->theme_dir),
				'images_url' => $this->images_url ?? $boardurl . '/themes/' . basename($this->theme_dir) . '/images',
				'theme_dir' => $this->theme_dir,
				'name' => $this->theme_name
			);
			$explicit_images = false;

			if ($fileFunc->fileExists($this->theme_dir . '/theme_info.xml'))
			{
				$theme_info = file_get_contents($this->theme_dir . '/theme_info.xml');

				// Parse theme-info.xml into an \ElkArte\XmlArray.
				$theme_info_xml = new XmlArray($theme_info);

				// @todo Error message of some sort?
				if (!$theme_info_xml->exists('theme-info[0]'))
				{
					return 'package_get_error_packageinfo_corrupt';
				}

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
					{
						$install_info[$var] = $theme_info_xml[$name];
					}
				}

				if (!empty($theme_info_xml['images']))
				{
					$install_info['images_url'] = $install_info['theme_url'] . '/' . $theme_info_xml['images'];
					$explicit_images = true;
				}

				if (!empty($theme_info_xml['extra']))
				{
					$install_info += Util::unserialize($theme_info_xml['extra']);
				}
			}

			if (isset($install_info['based_on']))
			{
				if ($install_info['based_on'] === 'default')
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
						{
							$install_info['theme_url'] = $install_info['base_theme_url'];
						}
					}
				}

				unset($install_info['based_on']);
			}

			// Find the newest id_theme.
			$id_theme = nextTheme();

			$inserts = array();
			foreach ($install_info as $var => $val)
			{
				$inserts[] = array($id_theme, $var, $val);
			}

			if (!empty($inserts))
			{
				addTheme($inserts);
			}

			updateSettings(array('knownThemes' => strtr($modSettings['knownThemes'] . ',' . $id_theme, array(',,' => ','))));

			redirectexit('action=admin;area=theme;sa=install;theme_id=' . $id_theme . ';' . $context['session_var'] . '=' . $context['session_id']);
		}

		redirectexit('action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id']);
	}

	/**
	 * Make a copy of the default theme in a new directory
	 */
	public function copyDefault()
	{
		global $boardurl, $modSettings, $settings;

		$fileFunc = FileFunctions::instance();

		// Hopefully the theme directory is writable, or we might have a problem.
		if (!$fileFunc->chmod(BOARDDIR . '/themes'))
		{
			throw new Exception('theme_install_write_error', 'critical');
		}

		// Make the new directory, standard characters only
		$new_theme_name = preg_replace('~[^A-Za-z0-9_\- ]~', '', $this->_req->post->copy);
		$this->theme_dir = BOARDDIR . '/themes/' . $new_theme_name;
		$fileFunc->createDirectory($this->theme_dir, false);

		// Get some more time if we can
		detectServer()->setTimeLimit(600);

		// Create the subdirectories for css, javascript and font files.
		$fileFunc->createDirectory($this->theme_dir . '/css', false);
		$fileFunc->createDirectory($this->theme_dir . '/scripts', false);
		$fileFunc->createDirectory($this->theme_dir . '/webfonts', false);

		// Copy over the default non-theme files.
		$to_copy = array('/index.php', '/index.template.php', '/scripts/theme.js', '/Theme.php');
		foreach ($to_copy as $file)
		{
			copy($settings['default_theme_dir'] . $file, $this->theme_dir . $file);
			$fileFunc->chmod($this->theme_dir . $file);
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
		write_theme_info($this->_req->post->copy, $modSettings['elkVersion'], $this->theme_dir, $theme_values);

		// Finish by setting the namespace
		$theme = file_get_contents($this->theme_dir . '/Theme.php');
		$theme = str_replace('namespace ElkArte\Themes\DefaultTheme;', 'namespace ElkArte\Themes\\' . $new_theme_name . ';', $theme);
		file_put_contents($this->theme_dir . '/Theme.php', $theme);
	}

	/**
	 * Install a theme from a directory on the server
	 *
	 * - Expects the directory is properly loaded with theme files
	 */
	public function installFromDir()
	{
		$fileFunc = FileFunctions::instance();

		if (!$fileFunc->isDir($this->_req->post->theme_dir) || !$fileFunc->fileExists($this->_req->post->theme_dir . '/theme_info.xml'))
		{
			throw new Exception('theme_install_error', false);
		}

		$this->theme_name = basename($this->_req->post->theme_dir);
		$this->theme_dir = $this->_req->post->theme_dir;
	}

	/**
	 * Install a new theme from an uploaded zip archive
	 */
	public function installFromZip()
	{
		$fileFunc = FileFunctions::instance();

		// Hopefully the theme directory is writable, or we might have a problem.
		if (!$fileFunc->chmod(BOARDDIR . '/themes'))
		{
			throw new Exception('theme_install_write_error', 'critical');
		}

		// This happens when the admin session is gone and the user has to login again
		if (empty($_FILES['theme_gz']) && empty($this->_req->post->theme_gz))
		{
			return;
		}

		// Set the default settings...
		$this->theme_name = strtok(basename(isset($_FILES['theme_gz']) ? $_FILES['theme_gz']['name'] : $this->_req->post->theme_gz), '.');
		$this->theme_name = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $this->theme_name);
		$this->theme_dir = BOARDDIR . '/themes/' . $this->theme_name;

		if (isset($_FILES['theme_gz']) && is_uploaded_file($_FILES['theme_gz']['tmp_name']) && (ini_get('open_basedir') != '' || $fileFunc->fileExists($_FILES['theme_gz']['tmp_name'])))
		{
			read_tgz_file($_FILES['theme_gz']['tmp_name'], BOARDDIR . '/themes/' . $this->theme_name, false, true);
		}
		elseif (isset($this->_req->post->theme_gz))
		{
			if (!isAuthorizedServer($this->_req->post->theme_gz))
			{
				throw new Exception('not_valid_server');
			}

			read_tgz_file($this->_req->post->theme_gz, BOARDDIR . '/themes/' . $this->theme_name, false, true);
		}
	}

	/**
	 * Set a theme option via javascript.
	 *
	 * What it does:
	 *
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
		global $settings, $options;

		// Check the session id.
		checkSession('get');

		// This good-for-nothing pixel is being used to keep the session alive.
		if (empty($this->_req->query->var) || !isset($this->_req->query->val))
		{
			redirectexit($settings['images_url'] . '/blank.png');
		}

		// Sorry, guests can't go any further than this..
		if ($this->user->is_guest || $this->user->id == 0)
		{
			obExit(false);
		}

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
		{
			redirectexit($settings['images_url'] . '/blank.png');
		}

		// Use a specific theme?
		if (isset($this->_req->query->th) || isset($this->_req->query->id))
		{
			// Invalidate the current themes cache too.
			Cache::instance()->remove('theme_settings-' . $settings['theme_id'] . ':' . $this->user->id);

			$settings['theme_id'] = $this->_req->getQuery('th', 'intval', $this->_req->getQuery('id', 'intval'));
		}

		// If this is the admin preferences the passed value will just be an element of it.
		if ($this->_req->query->var === 'admin_preferences')
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
			{
				$options['admin_preferences'][$this->_req->query->admin_key] = $this->_req->query->val;
			}

			// Change the value to be something nice,
			$this->_req->query->val = json_encode($options['admin_preferences']);
		}
		// If this is the window min/max settings, the passed window name will just be an element of it.
		elseif ($this->_req->query->var === 'minmax_preferences')
		{
			if (!empty($options['minmax_preferences']))
			{
				$minmax_preferences = serializeToJson($options['minmax_preferences'], function ($array_form) use ($settings) {
					// Update the option.
					require_once(SUBSDIR . '/Themes.subs.php');
					updateThemeOptions(array($settings['theme_id'], User::$info->id, 'minmax_preferences', json_encode($array_form)));
				});
			}
			else
			{
				$minmax_preferences = array();
			}

			// New value for them
			if (isset($this->_req->query->minmax_key) && strlen($this->_req->query->minmax_key) < 10)
			{
				$minmax_preferences[$this->_req->query->minmax_key] = $this->_req->query->val;
			}

			// Change the value to be something nice,
			$this->_req->query->val = json_encode($minmax_preferences);
		}

		// Update the option.
		require_once(SUBSDIR . '/Themes.subs.php');
		updateThemeOptions(array($settings['theme_id'], $this->user->id, $this->_req->query->var, is_array($this->_req->query->val) ? implode(',', $this->_req->query->val) : $this->_req->query->val));

		Cache::instance()->remove('theme_settings-' . $settings['theme_id'] . ':' . $this->user->id);

		// Don't output anything...
		redirectexit($settings['images_url'] . '/blank.png');
	}
}
