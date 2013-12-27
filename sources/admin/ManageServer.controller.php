<?php

/**
 * Contains all the functionality required to be able to edit the core server settings.
 * This includes anything from which an error may result in the forum destroying itself in a firey fury.
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
 *
 * Adding options to one of the setting screens isn't hard.
 * Call prepareDBSettingsContext;
 * The basic format for a checkbox is:
 *     array('check', 'nameInModSettingsAndSQL'),
 * And for a text box:
 *     array('text', 'nameInModSettingsAndSQL')
 * (NOTE: You have to add an entry for this at the bottom!)
 *
 * In these cases, it will look for $txt['nameInModSettingsAndSQL'] as the description,
 * and $helptxt['nameInModSettingsAndSQL'] as the (?) help popup description.
 *
 * Here's a quick explanation of how to add a new item:
 *
 * - A text input box.  For textual values.
 *     array('text', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For numerical values.
 *     array('int', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For floating point values.
 *     array('float', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A large text input box. Used for textual values spanning multiple lines.
 *     array('large_text', 'nameInModSettingsAndSQL', 'OptionalNumberOfRows'),
 * - A check box.  Either one or zero. (boolean)
 *     array('check', 'nameInModSettingsAndSQL'),
 * - A selection box.  Used for the selection of something from a list.
 *     array('select', 'nameInModSettingsAndSQL', array('valueForSQL' => $txt['displayedValue'])),
 *     Note that just saying array('first', 'second') will put 0 in the SQL for 'first'.
 * - A password input box. Used for passwords, no less!
 *     array('password', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A permission - for picking groups who have a permission.
 *     array('permissions', 'manage_groups'),
 * - A BBC selection box.
 *     array('bbc', 'sig_bbc'),
 *
 * For each option:
 *  - type (see above), variable name, size/possible values.
 *		OR make type '' for an empty string for a horizontal rule.
 *  - SET preinput - to put some HTML prior to the input box.
 *  - SET postinput - to put some HTML following the input box.
 *  - SET invalid - to mark the data as invalid.
 *  - PLUS you can override label and help parameters by forcing their keys in the array, for example:
 *    array('text', 'invalidlabel', 3, 'label' => 'Actual Label')
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ManageServer administration pages controller.
 * This handles several screens, with low-level essential settings such as
 * database settings, cache, general forum settings, and others.
 * It sends the data for display, and it allows the admin to change it.
 */
class ManageServer_Controller extends Action_Controller
{
	/**
	 * Database settings form
	 * @var Settings_Form
	 */
	protected $_databaseSettingsForm;

	/**
	 * General settings form
	 * @var Settings_Form
	 */
	protected $_generalSettingsForm;

	/**
	 * Cache settings form
	 * @var Settings_Form
	 */
	protected $_cacheSettingsForm;

	/**
	 * Cookies settings form
	 * @var Settings_Form
	 */
	protected $_cookieSettingsForm;

	/**
	 * Load balancing settings form
	 * @var Settings_Form
	 */
	protected $_balancingSettingsForm;

	/**
	 * This is the main dispatcher. Sets up all the available sub-actions, all the tabs and selects
	 * the appropriate one based on the sub-action.
	 *
	 * Requires the admin_forum permission.
	 * Redirects to the appropriate function based on the sub-action.
	 *
	 * @uses edit_settings adminIndex.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		// We're working with them settings here.
		require_once(SUBSDIR . '/Settings.class.php');

		// The settings are in here, I swear!
		loadLanguage('ManageSettings');

		// This is just to keep the database password more secure.
		isAllowedTo('admin_forum');
		checkSession('request');

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['admin_server_settings'],
			'help' => 'serversettings',
			'description' => $txt['admin_basic_settings'],
		);

		$subActions = array(
			'general' => array(
				$this, 'action_generalSettings_display', 'permission' => 'admin_forum'),
			'database' => array(
				$this, 'action_databaseSettings_display', 'permission' => 'admin_forum'),
			'cookie' => array(
				$this, 'action_cookieSettings_display', 'permission' => 'admin_forum'),
			'cache' => array(
				$this, 'action_cacheSettings_display', 'permission' => 'admin_forum'),
			'loads' => array(
				$this, 'action_balancingSettings_display', 'permission' => 'admin_forum'),
			'phpinfo' => array(
				$this, 'action_phpinfo', 'permission' => 'admin_forum'),
		);

		call_integration_hook('integrate_server_settings', array(&$subActions));

		// By default we're editing the core settings
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'general';

		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['admin_server_settings'];
		$context['sub_template'] = 'show_settings';

		// Any messages to speak of?
		$context['settings_message'] = (isset($_REQUEST['msg']) && isset($txt[$_REQUEST['msg']])) ? $txt[$_REQUEST['msg']] : '';

		// Warn the user if there's any relevant information regarding Settings.php.
		if ($subAction != 'cache')
		{
			// Warn the user if the backup of Settings.php failed.
			$settings_not_writable = !is_writable(BOARDDIR . '/Settings.php');
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
		}

		// Call the right function for this sub-action.
		$action = new Action();
		$action->initialize($subActions, 'general');
		$action->dispatch($subAction);
	}

	/**
	 * General forum settings - forum name, maintenance mode, etc.
	 * Practically, this shows an interface for the settings in Settings.php to be changed.
	 *
	 * - It uses the rawdata sub-template (not theme-able.)
	 * - Requires the admin_forum permission.
	 * - Uses the edit_settings administration area.
	 * - Contains the actual array of settings to show from Settings.php.
	 * - Accessed from ?action=admin;area=serversettings;sa=general.
	 *
	 * This method handles the display, allows to edit, and saves the result
	 * for generalSettings form.
	 */
	public function action_generalSettings_display()
	{
		global $scripturl, $context, $txt;

		// Initialize the form
		$this->_initGeneralSettingsForm();

		call_integration_hook('integrate_general_settings');

		// Setup the template stuff.
		$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=general;save';
		$context['settings_title'] = $txt['general_settings'];

		// Saving settings?
		if (isset($_REQUEST['save']))
		{
			call_integration_hook('integrate_save_general_settings');

			$this->_generalSettingsForm->save();
			redirectexit('action=admin;area=serversettings;sa=general;' . $context['session_var'] . '=' . $context['session_id'] . ';msg=' . (!empty($context['settings_message']) ? $context['settings_message'] : 'core_settings_saved'));
		}

		// Fill the config array for the template and all that.
		$this->_generalSettingsForm->prepare_file();
	}

	/**
	 * Initialize _generalSettings form.
	 */
	private function _initGeneralSettingsForm()
	{
		// Start the form
		$this->_generalSettingsForm = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_generalSettings();

		// Set them vars for our settings form
		return $this->_generalSettingsForm->settings($config_vars);
	}

	/**
	 * Basic database and paths settings - database name, host, etc.
	 *
	 * - It shows an interface for the settings in Settings.php to be changed.
	 * - It contains the actual array of settings to show from Settings.php.
	 * - It uses the rawdata sub-template (not theme-able.)
	 * - Requires the admin_forum permission.
	 * - Uses the edit_settings administration area.
	 * - Accessed from ?action=admin;area=serversettings;sa=database.
	 *
	 * This method handles the display, allows to edit, and saves the result
	 * for _databaseSettings form.
	 */
	public function action_databaseSettings_display()
	{
		global $scripturl, $context, $txt;

		// Initialize the form
		$this->_initDatabaseSettingsForm();

		call_integration_hook('integrate_database_settings');

		// Setup the template stuff.
		$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=database;save';
		$context['settings_title'] = $txt['database_paths_settings'];
		$context['save_disabled'] = $context['settings_not_writable'];

		// Saving settings?
		if (isset($_REQUEST['save']))
		{
			call_integration_hook('integrate_save_database_settings');

			$this->_databaseSettingsForm->save();
			redirectexit('action=admin;area=serversettings;sa=database;' . $context['session_var'] . '=' . $context['session_id'] . ';msg=' . (!empty($context['settings_message']) ? $context['settings_message'] : 'core_settings_saved'));
		}

		// Fill the config array for the template.
		$this->_databaseSettingsForm->prepare_file();
	}

	/**
	 * Initialize _databaseSettings form.
	 */
	private function _initDatabaseSettingsForm()
	{
		// instantiate the form
		$this->_databaseSettingsForm = new Settings_Form();
		$config_vars = $this->_databaseSettings();

		// Set them vars for our settings form
		return $this->_databaseSettingsForm->settings($config_vars);
	}

	/**
	 * Modify cookies settings.
	 * This method handles the display, allows to edit, and saves the result
	 * for the _cookieSettings form.
	 */
	public function action_cookieSettings_display()
	{
		global $context, $scripturl, $txt, $modSettings, $cookiename, $user_settings, $boardurl;

		// Initialize the form
		$this->_initCookieSettingsForm();

		call_integration_hook('integrate_cookie_settings');

		$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=cookie;save';
		$context['settings_title'] = $txt['cookies_sessions_settings'];

		// Saving settings?
		if (isset($_REQUEST['save']))
		{
			call_integration_hook('integrate_save_cookie_settings');

			// Its either local or global cookies
			if (!empty($_POST['localCookies']) && empty($_POST['globalCookies']))
				unset($_POST['globalCookies']);

			if (!empty($_POST['globalCookiesDomain']) && strpos($boardurl, $_POST['globalCookiesDomain']) === false)
				fatal_lang_error('invalid_cookie_domain', false);

			//Settings_Form::save_db($config_vars);
			$this->_cookieSettingsForm->save();

			// If the cookie name was changed, reset the cookie.
			if ($cookiename != $_POST['cookiename'])
			{
				$original_session_id = $context['session_id'];
				include_once(SUBSDIR . '/Auth.subs.php');

				// Remove the old cookie.
				setLoginCookie(-3600, 0);

				// Set the new one.
				$cookiename = $_POST['cookiename'];
				setLoginCookie(60 * $modSettings['cookieTime'], $user_settings['id_member'], sha1($user_settings['passwd'] . $user_settings['password_salt']));

				redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_var'] . '=' . $original_session_id, $context['server']['needs_login_fix']);
			}

			redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_var'] . '=' . $context['session_id'] . ';msg=' . (!empty($context['settings_message']) ? $context['settings_message'] : 'core_settings_saved'));
		}

		addInlineJavascript('
		function hideGlobalCookies()
		{
			var usingLocal = $("#localCookies").prop("checked"),
				usingGlobal = !usingLocal && $("#globalCookies").prop("checked");

			// Show/Hide the areas based on what they have chosen
			if (!usingLocal)
			{
				$("#setting_globalCookies").parent().slideDown();
				$("#globalCookies").parent().slideDown();
			}
			else
			{
				$("#setting_globalCookies").parent().slideUp();
				$("#globalCookies").parent().slideUp();
			}

			if (usingGlobal)
			{
				$("#setting_globalCookiesDomain").closest("dt").slideDown();
				$("#globalCookiesDomain").closest("dd").slideDown();
			}
			else
			{
				$("#setting_globalCookiesDomain").closest("dt").slideUp();
				$("#globalCookiesDomain").closest("dd").slideUp();
			}
		};
		hideGlobalCookies();

		$("#localCookies, #globalCookies").click(function() {
			hideGlobalCookies();
		});', true);

		// Fill the config array.
		$this->_cookieSettingsForm->prepare_file();
	}

	/**
	 * Initialize _cookieSettings form.
	 */
	private function _initCookieSettingsForm()
	{
		// Start a new form
		$this->_cookieSettingsForm = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_cookieSettings();

		// Set them vars for our settings form
		return $this->_cookieSettingsForm->settings($config_vars);
	}

	/**
	 * Cache settings editing and submission.
	 * This method handles the display, allows to edit, and saves the result
	 * for _cacheSettings form.
	 */
	public function action_cacheSettings_display()
	{
		global $context, $scripturl, $txt;

		// initialize the form
		$this->_initCacheSettingsForm();

		// some javascript to enable / disable certain settings if the option is not selected
		addInlineJavascript('
			var cache_type = document.getElementById(\'cache_accelerator\');

			createEventListener(cache_type);
			cache_type.addEventListener("change", toggleCache);
			toggleCache();', true);

		call_integration_hook('integrate_modify_cache_settings');

		// Saving again?
		if (isset($_GET['save']))
		{
			call_integration_hook('integrate_save_cache_settings');

			$this->_cacheSettingsForm->save();

			// we need to save the $cache_enable to $modSettings as well
			updatesettings(array('cache_enable' => (int) $_POST['cache_enable']));

			// exit so we reload our new settings on the page
			redirectexit('action=admin;area=serversettings;sa=cache;' . $context['session_var'] . '=' . $context['session_id']);
		}

		loadLanguage('Maintenance');
		createToken('admin-maint');
		Template_Layers::getInstance()->add('clean_cache_button');

		$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=cache;save';
		$context['settings_title'] = $txt['caching_settings'];
		$context['settings_message'] = $txt['caching_information'];

		// Prepare the template.
		createToken('admin-ssc');

		// Prepare settings for display in the template.
		$this->_cacheSettingsForm->prepare_file();
	}

	/**
	 * Initialize _cacheSettings form.
	 */
	private function _initCacheSettingsForm()
	{
		// We need a setting form
		$this->_cacheSettingsForm = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_cacheSettings();

		// Set them vars for our settings form
		return $this->_cacheSettingsForm->settings($config_vars);
	}

	/**
	 * Allows to edit load balancing settings.
	 *
	 * This method handles the display, allows to edit, and saves the result
	 * for the _balancingSettings form.
	 */
	public function action_balancingSettings_display()
	{
		global $txt, $scripturl, $context;

		// Initialize the form
		$this->_initBalancingSettingsForm();

		// Initialize it with our settings
		$config_vars = $this->_balancingSettingsForm->settings();

		call_integration_hook('integrate_loadavg_settings');

		$context['post_url'] = $scripturl . '?action=admin;area=serversettings;sa=loads;save';
		$context['settings_title'] = $txt['load_balancing_settings'];

		// Saving?
		if (isset($_GET['save']))
		{
			// Stupidity is not allowed.
			foreach ($_POST as $key => $value)
			{
				if (strpos($key, 'loadavg') === 0 || $key === 'loadavg_enable')
					continue;
				elseif ($key == 'loadavg_auto_opt' && $value <= 1)
					$_POST['loadavg_auto_opt'] = '1.0';
				elseif ($key == 'loadavg_forum' && $value < 10)
					$_POST['loadavg_forum'] = '10.0';
				elseif ($value < 2)
					$_POST[$key] = '2.0';
			}

			call_integration_hook('integrate_save_loadavg_settings');

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=serversettings;sa=loads;' . $context['session_var'] . '=' . $context['session_id']);
		}

		createToken('admin-ssc');
		createToken('admin-dbsc');
		$this->_balancingSettingsForm->prepare_db($config_vars);
	}

	/**
	 * Initialize balancingSettings form.
	 */
	private function _initBalancingSettingsForm()
	{
		// Forms, we need them
		$this->_balancingSettingsForm = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_balancingSettings();

		// Set them vars for our settings form
		return $this->_balancingSettingsForm->settings($config_vars);
	}

	/**
	 * Handles the submission of new/changed load balancing settings.
	 * Uses the _balancingSettings form.
	 */
	public function action_balancingSettings_save()
	{
		global $context;

		// Initialize the form
		$this->_initBalancingSettingsForm();

		// Initialize it with our settings
		$config_vars = $this->_balancingSettingsForm->settings();

		// Double-check ourselves, we are about to save
		if (isset($_GET['save']))
		{
			// Stupidity is not allowed.
			foreach ($_POST as $key => $value)
			{
				if (strpos($key, 'loadavg') === 0 || $key === 'loadavg_enable')
					continue;
				elseif ($key == 'loadavg_auto_opt' && $value <= 1)
					$_POST['loadavg_auto_opt'] = '1.0';
				elseif ($key == 'loadavg_forum' && $value < 10)
					$_POST['loadavg_forum'] = '10.0';
				elseif ($value < 2)
					$_POST[$key] = '2.0';
			}

			call_integration_hook('integrate_save_loadavg_settings');

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=serversettings;sa=loads;' . $context['session_var'] . '=' . $context['session_id']);
		}
	}

	/**
	 * Allows us to see the servers php settings
	 *
	 * - loads the settings into an array for display in a template
	 * - drops cookie values just in case
	 */
	public function action_phpinfo()
	{
		global $context, $txt;

		$info_lines = array();
		$category = $txt['phpinfo_settings'];
		$pinfo = array();

		// Get the data
		ob_start();
		phpinfo();

		// We only want it for its body, pigs that we are
		$info_lines = preg_replace('~^.*<body>(.*)</body>.*$~', '$1', ob_get_contents());
		$info_lines = explode("\n", strip_tags($info_lines, "<tr><td><h2>"));
		ob_end_clean();

		// Remove things that could be considered sensitive
		$remove = '_COOKIE|Cookie|_GET|_REQUEST|REQUEST_URI|QUERY_STRING|REQUEST_URL|HTTP_REFERER';

		// Put all of it into an array
		foreach ($info_lines as $line)
		{
			if (preg_match('~(' . $remove . ')~', $line))
				continue;

			// New category?
			if (strpos($line, '<h2>') !== false)
				$category = preg_match('~<h2>(.*)</h2>~', $line, $title) ? $category = $title[1] : $category;

			// Load it as setting => value or the old setting local master
			if (preg_match('~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~', $line, $val))
				$pinfo[$category][$val[1]] = $val[2];
			elseif (preg_match('~<tr><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td><td[^>]+>([^<]*)</td></tr>~', $line, $val))
				$pinfo[$category][$val[1]] = array($txt['phpinfo_localsettings'] => $val[2], $txt['phpinfo_defaultsettings'] => $val[3]);
		}

		// Load it in to context and display it
		$context['pinfo'] = $pinfo;
		$context['page_title'] = $txt['admin_server_settings'];
		$context['sub_template'] = 'php_info';

		return;
	}

	/**
	 * This function returns all general settings.
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
				array('mmessage', $txt['maintenance_message'], 'file', 'text', 36),
			'',
				array('webmaster_email', $txt['admin_webmaster_email'], 'file', 'text', 30),
			'',
				array('enableCompressedOutput', $txt['enableCompressedOutput'], 'db', 'check', null, 'enableCompressedOutput'),
				array('disableTemplateEval', $txt['disableTemplateEval'], 'db', 'check', null, 'disableTemplateEval'),
				array('disableHostnameLookup', $txt['disableHostnameLookup'], 'db', 'check', null, 'disableHostnameLookup'),
		);

		return $config_vars;
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function generalSettings_search()
	{
		return $this->_generalSettings();
	}

	/**
	 * This function returns database settings.
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

		return $config_vars;
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function databaseSettings_search()
	{
		return $this->_databaseSettings();
	}

	/**
	 * This little function returns all cookie settings.
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
				array('secureCookies', $txt['secureCookies'], 'subtext' => $txt['secureCookies_note'], 'db', 'check', false, 'secureCookies', 'disabled' => !isset($_SERVER['HTTPS']) || !(strtolower($_SERVER['HTTPS']) == 'on' || strtolower($_SERVER['HTTPS']) == '1')),
				array('httponlyCookies', $txt['httponlyCookies'], 'subtext' => $txt['httponlyCookies_note'], 'db', 'check', false, 'httponlyCookies'),
			'',
				// Sessions
				array('databaseSession_enable', $txt['databaseSession_enable'], 'db', 'check', false, 'databaseSession_enable'),
				array('databaseSession_loose', $txt['databaseSession_loose'], 'db', 'check', false, 'databaseSession_loose'),
				array('databaseSession_lifetime', $txt['databaseSession_lifetime'], 'db', 'int', false, 'databaseSession_lifetime', 'postinput' => $txt['seconds']),
		);

		// Set them vars for our settings form
		return $config_vars;
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function cookieSettings_search()
	{
		return $this->_cookieSettings();
	}

	/**
	 * This little function returns all cache settings.
	 */
	private function _cacheSettings()
	{
		global $txt;

		// Detect all available optimizers
		$detected = array();
		if (function_exists('eaccelerator_put'))
			$detected['eaccelerator'] = $txt['eAccelerator_cache'];
		if (function_exists('mmcache_put'))
			$detected['mmcache'] = $txt['mmcache_cache'];
		if (function_exists('apc_store'))
			$detected['apc'] = $txt['apc_cache'];
		if (function_exists('output_cache_put') || function_exists('zend_shm_cache_store'))
			$detected['zend'] = $txt['zend_cache'];
		if (function_exists('memcache_set') || function_exists('memcached_set'))
			$detected['memcached'] = $txt['memcached_cache'];
		if (function_exists('xcache_set'))
			$detected['xcache'] = $txt['xcache_cache'];
		if (function_exists('file_put_contents'))
			$detected['filebased'] = $txt['default_cache'];

		// Set our values to show what, if anything, we found
		if (empty($detected))
		{
			$txt['cache_settings_message'] = $txt['detected_no_caching'];
			$cache_level = array($txt['cache_off']);
			$detected['none'] = $txt['cache_off'];
		}
		else
		{
			$txt['cache_settings_message'] = sprintf($txt['detected_accelerators'], implode(', ', $detected));
			$cache_level = array($txt['cache_off'], $txt['cache_level1'], $txt['cache_level2'], $txt['cache_level3']);
		}

		// Define the variables we want to edit.
		$config_vars = array(
			// Only a few settings, but they are important
			array('', $txt['cache_settings_message'], '', 'desc'),
			array('cache_enable', $txt['cache_enable'], 'file', 'select', $cache_level, 'cache_enable'),
			array('cache_accelerator', $txt['cache_accelerator'], 'file', 'select', $detected),
			array('cache_uid', $txt['cache_uid'], 'file', 'text', $txt['cache_uid'], 'cache_uid'),
			array('cache_password', $txt['cache_password'], 'file', 'password', $txt['cache_password'], 'cache_password'),
			array('cache_memcached', $txt['cache_memcached'], 'file', 'text', $txt['cache_memcached'], 'cache_memcached'),
			array('cachedir', $txt['cachedir'], 'file', 'text', 36, 'cache_cachedir'),
		);

		return $config_vars;
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function cacheSettings_search()
	{
		return $this->_cacheSettings();
	}

	/**
	 * This little function returns load balancing settings.
	 */
	private function _balancingSettings()
	{
		global $txt, $modSettings, $context;

		// Initialize settings for the form to show, disabled by default.
		$disabled = true;
		$context['settings_message'] = $txt['loadavg_disabled_conf'];

		// don't say you're using that win-thing, no cookies for you :P
		if (stripos(PHP_OS, 'win') === 0)
			$context['settings_message'] = $txt['loadavg_disabled_windows'];
		else
		{
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
			$value = !isset($modSettings[$name]) ? $value : $modSettings[$name];
			$config_vars[] = array('text', $name, 'value' => $value, 'disabled' => $disabled);
		}

		return $config_vars;
	}

	/**
	 * Return the search settings for use in admin search
	 */
	public function balancingSettings_search()
	{
		return $this->_balancingSettings();
	}
}