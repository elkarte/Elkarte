<?php

/**
 * Contains all the functionality required to be able to edit the core server settings.
 * This includes anything from which an error may result in the forum destroying
 * itself in a firey fury.
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
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\User;

/**
 * ManageServer administration pages controller.
 *
 * This handles several screens, with low-level essential settings such as
 * database settings, cache, general forum settings, and others.
 * It sends the data for display, and it allows the admin to change it.
 */
class ManageServer extends AbstractController
{
	/**
	 * This is the main dispatcher. Sets up all the available sub-actions, all the tabs and selects
	 * the appropriate one based on the sub-action.
	 *
	 * What it does:
	 *
	 * - Requires the admin_forum permission.
	 * - Redirects to the appropriate function based on the sub-action.
	 *
	 * @event integrate_sa_server_settings
	 * @uses edit_settings adminIndex.
	 * @see  \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		// The settings are in here, I swear!
		theme()->getTemplates()->loadLanguageFile('ManageSettings');

		// This is just to keep the database password more secure.
		isAllowedTo('admin_forum');
		checkSession('request');

		$subActions = array(
			'general' => array($this, 'action_generalSettings_display', 'permission' => 'admin_forum'),
			'database' => array($this, 'action_databaseSettings_display', 'permission' => 'admin_forum'),
			'cookie' => array($this, 'action_cookieSettings_display', 'permission' => 'admin_forum'),
			'cache' => array($this, 'action_cacheSettings_display', 'permission' => 'admin_forum'),
			'loads' => array($this, 'action_loadavgSettings_display', 'permission' => 'admin_forum'),
			'phpinfo' => array($this, 'action_phpinfo', 'permission' => 'admin_forum'),
		);

		$action = new Action('server_settings');

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['admin_server_settings'],
			'help' => 'serversettings',
			'description' => $txt['admin_basic_settings'],
		);

		// By default we're editing the core settings, call integrate_sa_server_settings
		$subAction = $action->initialize($subActions, 'general');

		// Last things for the template
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['admin_server_settings'];
		$context['sub_template'] = 'show_settings';

		// Any messages to speak of?
		$context['settings_message'] = (isset($this->_req->query->msg) && isset($txt[$this->_req->query->msg])) ? $txt[$this->_req->query->msg] : '';

		// Warn the user if there's any relevant information regarding Settings.php.
		$settings_not_writable = !is_writable(BOARDDIR . '/Settings.php');

		// Warn the user if the backup of Settings.php failed.
		$settings_backup_fail = !@is_writable(BOARDDIR . '/Settings_bak.php') || !@copy(BOARDDIR . '/Settings.php', BOARDDIR . '/Settings_bak.php');

		if ($settings_not_writable)
		{
			$context['settings_message'] = $txt['settings_not_writable'];
			$context['error_type'] = 'notice';
		}
		elseif ($settings_backup_fail)
		{
			$context['settings_message'] = $txt['admin_backup_fail'];
			$context['error_type'] = 'notice';
		}

		$context['settings_not_writable'] = $settings_not_writable;

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * General forum settings - forum name, maintenance mode, etc.
	 *
	 * Practically, this shows an interface for the settings in Settings.php to
	 * be changed. The method handles the display, allows to edit, and saves
	 * the result for generalSettings form.
	 *
	 * What it does:
	 *
	 * - Requires the admin_forum permission.
	 * - Uses the edit_settings administration area.
	 * - Contains the actual array of settings to show from Settings.php.
	 * - Accessed from ?action=admin;area=serversettings;sa=general.
	 *
	 * @event integrate_save_general_settings
	 */
	public function action_generalSettings_display()
	{
		global $context, $txt;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::FILE_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_generalSettings());

		// Setup the template stuff.
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'serversettings', 'sa' => 'general;save']);
		$context['settings_title'] = $txt['general_settings'];

		// Saving settings?
		if (isset($this->_req->query->save))
		{
			call_integration_hook('integrate_save_general_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=serversettings;sa=general;' . $context['session_var'] . '=' . $context['session_id'] . ';msg=' . (!empty($context['settings_message']) ? $context['settings_message'] : 'core_settings_saved'));
		}

		// Fill the config array for the template and all that.
		$settingsForm->prepare();
	}

	/**
	 * This function returns all general settings.
	 *
	 * @event integrate_modify_general_settings
	 */
	private function _generalSettings()
	{
		global $txt;

		// initialize configuration
		$config_vars = array(
			array('mbname', $txt['admin_title'], 'file', 'text', 30),
			'',
			array('maintenance', $txt['admin_maintain'], 'file', 'check'),
			array('mtitle', $txt['maintenance_subject'], 'file', 'text', 36),
			array('mmessage', $txt['maintenance_message'], 'file', 'large_text', 6),
			'',
			array('webmaster_email', $txt['admin_webmaster_email'], 'file', 'text', 30),
			'',
			array('enableCompressedOutput', $txt['enableCompressedOutput'], 'db', 'check', null, 'enableCompressedOutput'),
			array('disableHostnameLookup', $txt['disableHostnameLookup'], 'db', 'check', null, 'disableHostnameLookup'),
			array('url_format', $txt['url_format'], 'file', 'select', array('standard' => $txt['url_format_standard'], 'semantic' => $txt['url_format_semantic'], 'queryless' => $txt['url_format_queryless'])),
		);

		// Notify the integration
		call_integration_hook('integrate_modify_general_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Basic database and paths settings - database name, host, etc.
	 *
	 * This method handles the display, allows to edit, and saves the results
	 * for _databaseSettings.
	 *
	 * What it does:
	 *
	 * - It shows an interface for the settings in Settings.php to be changed.
	 * - It contains the actual array of settings to show from Settings.php.
	 * - Requires the admin_forum permission.
	 * - Uses the edit_settings administration area.
	 * - Accessed from ?action=admin;area=serversettings;sa=database.
	 *
	 * @event integrate_save_database_settings
	 */
	public function action_databaseSettings_display()
	{
		global $context, $txt;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::FILE_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_databaseSettings());

		// Setup the template stuff.
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'serversettings', 'sa' => 'database;save']);
		$context['settings_title'] = $txt['database_paths_settings'];
		$context['save_disabled'] = $context['settings_not_writable'];

		// Saving settings?
		if (isset($this->_req->query->save))
		{
			call_integration_hook('integrate_save_database_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=serversettings;sa=database;' . $context['session_var'] . '=' . $context['session_id'] . ';msg=' . (!empty($context['settings_message']) ? $context['settings_message'] : 'core_settings_saved'));
		}

		// Fill the config array for the template.
		$settingsForm->prepare();
	}

	/**
	 * This function returns database settings.
	 *
	 * @event integrate_modify_database_settings
	 */
	private function _databaseSettings()
	{
		global $txt;

		// initialize settings
		$config_vars = array(
			array('db_server', $txt['database_server'], 'file', 'text'),
			array('db_user', $txt['database_user'], 'file', 'text'),
			array('db_passwd', $txt['database_password'], 'file', 'password'),
			array('db_name', $txt['database_name'], 'file', 'text'),
			array('db_prefix', $txt['database_prefix'], 'file', 'text'),
			array('db_persist', $txt['db_persist'], 'file', 'check', null, 'db_persist'),
			array('db_error_send', $txt['db_error_send'], 'file', 'check'),
			array('ssi_db_user', $txt['ssi_db_user'], 'file', 'text', null, 'ssi_db_user'),
			array('ssi_db_passwd', $txt['ssi_db_passwd'], 'file', 'password'),
			'',
			array('autoFixDatabase', $txt['autoFixDatabase'], 'db', 'check', false, 'autoFixDatabase'),
			array('autoOptMaxOnline', $txt['autoOptMaxOnline'], 'subtext' => $txt['zero_for_no_limit'], 'db', 'int'),
			'',
			array('boardurl', $txt['admin_url'], 'file', 'text', 36),
			array('boarddir', $txt['boarddir'], 'file', 'text', 36),
			array('sourcedir', $txt['sourcesdir'], 'file', 'text', 36),
			array('cachedir', $txt['cachedir'], 'file', 'text', 36),
		);

		// Notify the integration
		call_integration_hook('integrate_modify_database_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Modify cookies settings.
	 *
	 * This method handles the display, allows to edit, and saves the result
	 * for the _cookieSettings form.
	 *
	 * @event integrate_save_cookie_settings
	 */
	public function action_cookieSettings_display()
	{
		global $context, $txt, $modSettings, $cookiename, $boardurl;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::FILE_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_cookieSettings());

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'serversettings', 'sa' => 'cookie;save']);
		$context['settings_title'] = $txt['cookies_sessions_settings'];

		// Saving settings?
		if (isset($this->_req->query->save))
		{
			call_integration_hook('integrate_save_cookie_settings');

			// Its either local or global cookies
			if (!empty($this->_req->post->localCookies) && empty($this->_req->post->globalCookies))
			{
				unset($this->_req->post->globalCookies);
			}

			if (!empty($this->_req->post->globalCookiesDomain) && strpos($boardurl, $this->_req->post->globalCookiesDomain) === false)
			{
				throw new Exception('invalid_cookie_domain', false);
			}

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			// If the cookie name was changed, reset the cookie.
			if ($cookiename !== $this->_req->post->cookiename)
			{
				require_once(SUBSDIR . '/Auth.subs.php');

				$original_session_id = $context['session_id'];

				// Remove the old cookie, nom nom nom
				setLoginCookie(-3600, 0);

				// Set the new one.
				$cookiename = $this->_req->post->cookiename;
				setLoginCookie(60 * $modSettings['cookieTime'], (int) User::$settings['id_member'], hash('sha256', User::$settings['passwd'] . User::$settings['password_salt']));

				redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_var'] . '=' . $original_session_id, detectServer()->is('needs_login_fix'));
			}

			redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_var'] . '=' . $context['session_id'] . ';msg=' . (!empty($context['settings_message']) ? $context['settings_message'] : 'core_settings_saved'));
		}

		theme()->addInlineJavascript('
		// Initial state
		hideGlobalCookies();

		// Update when clicked
		$("#localCookies, #globalCookies").click(function () {
			hideGlobalCookies();
		});', true);

		// Fill the config array.
		$settingsForm->prepare();
	}

	/**
	 * This little function returns all cookie settings.
	 *
	 * @event integrate_modify_cookie_settings
	 */
	private function _cookieSettings()
	{
		global $txt;

		// Define the variables we want to edit or show in the cookie form.
		$config_vars = array(
			// Cookies...
			array('cookiename', $txt['cookie_name'], 'file', 'text', 20),
			array('cookieTime', $txt['cookieTime'], 'db', 'int', 'postinput' => $txt['minutes']),
			array('localCookies', $txt['localCookies'], 'subtext' => $txt['localCookies_note'], 'db', 'check', false, 'localCookies'),
			array('globalCookies', $txt['globalCookies'], 'subtext' => $txt['globalCookies_note'], 'db', 'check', false, 'globalCookies'),
			array('globalCookiesDomain', $txt['globalCookiesDomain'], 'subtext' => $txt['globalCookiesDomain_note'], 'db', 'text', false, 'globalCookiesDomain'),
			array('secureCookies', $txt['secureCookies'], 'subtext' => $txt['secureCookies_note'], 'db', 'check', false, 'secureCookies', 'disabled' => !isset($this->_req->server->HTTPS) || !(strtolower($this->_req->server->HTTPS) === 'on' || strtolower($this->_req->server->HTTPS) == '1')),
			array('httponlyCookies', $txt['httponlyCookies'], 'subtext' => $txt['httponlyCookies_note'], 'db', 'check', false, 'httponlyCookies'),
			'',
			// Sessions
			array('databaseSession_enable', $txt['databaseSession_enable'], 'db', 'check', false, 'databaseSession_enable'),
			array('databaseSession_loose', $txt['databaseSession_loose'], 'db', 'check', false, 'databaseSession_loose'),
			array('databaseSession_lifetime', $txt['databaseSession_lifetime'], 'db', 'int', false, 'databaseSession_lifetime', 'postinput' => $txt['seconds']),
		);

		// Notify the integration
		call_integration_hook('integrate_modify_cookie_settings', array(&$config_vars));

		// Set them vars for our settings form
		return $config_vars;
	}

	/**
	 * Cache settings editing and submission.
	 *
	 * This method handles the display, allows to edit, and saves the result
	 * for _cacheSettings form.
	 *
	 * @event integrate_save_cache_settings
	 */
	public function action_cacheSettings_display()
	{
		global $context, $txt;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::FILE_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_cacheSettings());

		// Saving again?
		if (isset($this->_req->query->save))
		{
			call_integration_hook('integrate_save_cache_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			// we need to save the $cache_enable to $modSettings as well
			// @TODO Still?
			updateSettings(array('cache_enable' => (int) $this->_req->post->cache_enable));

			// exit so we reload our new settings on the page
			redirectexit('action=admin;area=serversettings;sa=cache;' . $context['session_var'] . '=' . $context['session_id']);
		}

		theme()->getTemplates()->loadLanguageFile('Maintenance');
		createToken('admin-maint');
		theme()->getLayers()->add('clean_cache_button');

		// Some javascript to enable / disable certain settings if the option is not selected
		theme()->addInlineJavascript('
			var cache_type = document.getElementById(\'cache_accelerator\');

			createEventListener(cache_type);
			cache_type.addEventListener("change", toggleCache);
			toggleCache();', true);

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'serversettings', 'sa' => 'cache;save']);
		$context['settings_title'] = $txt['caching_settings'];
		$context['settings_message'] = $txt['caching_information'] . '<br /><br />' . $txt['cache_settings_message'];

		// Prepare the template.
		createToken('admin-ssc');

		// Prepare settings for display in the template.
		$settingsForm->prepare();
	}

	/**
	 * This little function returns all cache settings.
	 *
	 * @event integrate_modify_cache_settings
	 */
	private function _cacheSettings()
	{
		global $txt;

		// Detect all available optimizers
		require_once(SUBSDIR . '/Cache.subs.php');
		$detected = loadCacheEngines(false);
		$detected_names = array();
		$detected_supported = array();

		foreach ($detected as $key => $value)
		{
			$detected_names[] = $value->title();

			if (!empty($value->isAvailable()))
			{
				$detected_supported[$key] = $value->title();
			}
		}

		$txt['caching_information'] = str_replace('{supported_accelerators}', '<i>' . implode(', ', $detected_names) . '</i><br />', $txt['caching_information']);

		// Set our values to show what, if anything, we found
		$txt['cache_settings_message'] = sprintf($txt['detected_accelerators'], implode(', ', $detected_supported));
		$cache_level = array($txt['cache_off'], $txt['cache_level1'], $txt['cache_level2'], $txt['cache_level3']);

		// Define the variables we want to edit.
		$config_vars = array(
			// Only a few settings, but they are important
			array('cache_enable', $txt['cache_enable'], 'file', 'select', $cache_level, 'cache_enable'),
			array('cache_accelerator', $txt['cache_accelerator'], 'file', 'select', $detected_supported),
		);

		// If the cache engine has specific settings, add them in
		foreach ($detected as $key => $value)
		{
			if ($value->isAvailable())
			{
				$value->settings($config_vars);
			}
		}

		// Notify the integration that we're preparing to mess with cache settings...
		call_integration_hook('integrate_modify_cache_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Allows to edit load management settings.
	 *
	 * This method handles the display, allows to edit, and saves the result
	 * for the _loadavgSettings form.
	 *
	 * @event integrate_loadavg_settings
	 * @event integrate_save_loadavg_settings
	 */
	public function action_loadavgSettings_display()
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_loadavgSettings());

		call_integration_hook('integrate_loadavg_settings');

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'serversettings', 'sa' => 'loads;save']);
		$context['settings_title'] = $txt['loadavg_settings'];

		// Saving?
		if (isset($this->_req->query->save))
		{
			// Stupidity is not allowed.
			foreach ($this->_req->post as $key => $value)
			{
				if (strpos($key, 'loadavg') === 0 || $key === 'loadavg_enable')
				{
					continue;
				}

				if ($key === 'loadavg_auto_opt' && $value <= 1)
				{
					$this->_req->post->loadavg_auto_opt = '1.0';
				}
				elseif ($key === 'loadavg_forum' && $value < 10)
				{
					$this->_req->post->loadavg_forum = '10.0';
				}
				elseif ($value < 2)
				{
					$this->_req->{$key} = '2.0';
				}
			}

			call_integration_hook('integrate_save_loadavg_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=serversettings;sa=loads;' . $context['session_var'] . '=' . $context['session_id']);
		}

		createToken('admin-ssc');
		createToken('admin-dbsc');
		$settingsForm->prepare();
	}

	/**
	 * This little function returns load management settings.
	 *
	 * @event integrate_modify_loadavg_settings
	 */
	private function _loadavgSettings()
	{
		global $txt, $modSettings, $context;

		// Initialize settings for the form to show, disabled by default.
		$disabled = true;
		$context['settings_message'] = $txt['loadavg_disabled_conf'];

		// Don't say you're using that win-thing, no cookies for you :P
		if (stripos(PHP_OS, 'win') === 0)
		{
			$context['settings_message'] = $txt['loadavg_disabled_windows'];
		}
		else
		{
			require_once(SUBSDIR . '/Server.subs.php');
			$modSettings['load_average'] = detectServerLoad();

			if ($modSettings['load_average'] !== false)
			{
				$disabled = false;
				$context['settings_message'] = sprintf($txt['loadavg_warning'], $modSettings['load_average']);
			}
		}

		// Start with a simple checkbox.
		$config_vars = array(
			array('check', 'loadavg_enable', 'disabled' => $disabled),
		);

		// Set the default values for each option.
		$default_values = array(
			'loadavg_auto_opt' => '1.0',
			'loadavg_search' => '2.5',
			'loadavg_allunread' => '2.0',
			'loadavg_unreadreplies' => '3.5',
			'loadavg_show_posts' => '2.0',
			'loadavg_userstats' => '10.0',
			'loadavg_bbc' => '30.0',
			'loadavg_forum' => '40.0',
		);

		// Loop through the settings.
		foreach ($default_values as $name => $value)
		{
			// Use the default value if the setting isn't set yet.
			$value = isset($modSettings[$name]) ? $modSettings[$name] : $value;
			$config_vars[] = array('text', $name, 'value' => $value, 'disabled' => $disabled);
		}

		// Notify the integration that we're preparing to mess with load management settings...
		call_integration_hook('integrate_modify_loadavg_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Allows us to see the servers php settings
	 *
	 * What it does:
	 *
	 * - loads the settings into an array for display in a template
	 * - drops cookie values just in case
	 *
	 * @uses sub-template php_info
	 */
	public function action_phpinfo()
	{
		global $context, $txt;

		$category = $txt['phpinfo_settings'];
		$pinfo = array();

		// Get the data
		ob_start();
		phpinfo();

		// We only want it for its body, pigs that we are
		$info_lines = preg_replace('~^.*<body>(.*)</body>.*$~', '$1', ob_get_contents());
		$info_lines = explode("\n", strip_tags($info_lines, '<tr><td><h2>'));
		@ob_end_clean();

		// Remove things that could be considered sensitive
		$remove = '_COOKIE|Cookie|_GET|_REQUEST|REQUEST_URI|QUERY_STRING|REQUEST_URL|HTTP_REFERER';

		// Put all of it into an array
		foreach ($info_lines as $line)
		{
			if (preg_match('~(' . $remove . ')~', $line))
			{
				continue;
			}

			// New category?
			if (strpos($line, '<h2>') !== false)
			{
				$category = preg_match('~<h2>(.*)</h2>~', $line, $title) ? $title[1] : $category;
			}

			// Load it as setting => value or the old setting local master
			if (preg_match('~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~', $line, $val))
			{
				$pinfo[$category][$val[1]] = $val[2];
			}
			elseif (preg_match('~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~', $line, $val))
			{
				$pinfo[$category][$val[1]] = array($txt['phpinfo_localsettings'] => $val[2], $txt['phpinfo_defaultsettings'] => $val[3]);
			}
		}

		// Load it in to context and display it
		$context['pinfo'] = $pinfo;
		$context['page_title'] = $txt['admin_server_settings'];
		$context['sub_template'] = 'php_info';
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function generalSettings_search()
	{
		return $this->_generalSettings();
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function databaseSettings_search()
	{
		return $this->_databaseSettings();
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function cookieSettings_search()
	{
		return $this->_cookieSettings();
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function cacheSettings_search()
	{
		return $this->_cacheSettings();
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function balancingSettings_search()
	{
		return $this->_loadavgSettings();
	}
}
