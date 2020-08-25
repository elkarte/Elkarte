<?php

/**
 * Functions to support admin controller
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1.6
 *
 * This file contains functions that are specifically done by administrators.
 *
 */

/**
 * Get a list of versions that are currently installed on the server.
 *
 * @package Admin
 * @param string[] $checkFor
 */
function getServerVersions($checkFor)
{
	global $txt;

	$db = database();

	loadLanguage('Admin');

	$versions = array();

	// Is GD available?  If it is, we should show version information for it too.
	if (in_array('gd', $checkFor) && function_exists('gd_info'))
	{
		$temp = gd_info();
		$versions['gd'] = array('title' => $txt['support_versions_gd'], 'version' => $temp['GD Version']);
	}

	// Why not have a look at ImageMagick? If it is, we should show version information for it too.
	if (in_array('imagick', $checkFor) && class_exists('Imagick'))
	{
		$temp = new Imagick;
		$temp2 = $temp->getVersion();
		$versions['imagick'] = array('title' => $txt['support_versions_imagick'], 'version' => $temp2['versionString']);
	}

	// Now lets check for the Database.
	if (in_array('db_server', $checkFor))
	{
		$conn = $db->connection();
		if (empty($conn))
			trigger_error('getServerVersions(): you need to be connected to the database in order to get its server version', E_USER_NOTICE);
		else
		{
			$versions['db_server'] = array('title' => sprintf($txt['support_versions_db'], $db->db_title()), 'version' => '');
			$versions['db_server']['version'] = $db->db_server_version();
		}
	}

	require_once(SUBSDIR . '/Cache.subs.php');
	$cache_engines = loadCacheEngines();
	foreach ($cache_engines as $name => $details)
	{
		if (in_array($name, $checkFor))
			$versions[$name] = $details;
	}

	if (in_array('opcache', $checkFor) && extension_loaded('Zend OPcache'))
	{
		$opcache_config = @opcache_get_configuration();
		if (!empty($opcache_config['directives']['opcache.enable']))
			$versions['opcache'] = array('title' => $opcache_config['version']['opcache_product_name'], 'version' => $opcache_config['version']['version']);
	}

	// PHP Version
	if (in_array('php', $checkFor))
		$versions['php'] = array('title' => 'PHP', 'version' => PHP_VERSION . ' (' . php_sapi_name() . ')', 'more' => '?action=admin;area=serversettings;sa=phpinfo');

	// Server info
	if (in_array('server', $checkFor))
	{
		$req = request();
		$versions['server'] = array('title' => $txt['support_versions_server'], 'version' => $req->server_software());

		// Compute some system info, if we can
		$versions['server_name'] = array('title' => $txt['support_versions'], 'version' => php_uname());
		require_once(SUBSDIR . '/Server.subs.php');
		$loading = detectServerLoad();
		if ($loading !== false)
			$versions['server_load'] = array('title' => $txt['loadavg'], 'version' => $loading);
	}

	return $versions;
}

/**
 * Builds the available tasks for this admin / moderator
 *
 * What it does:
 *
 * - Sets up the support resource txt stings
 * - Called from Admin.controller action_home and action_credits
 *
 * @package Admin
 */
function getQuickAdminTasks()
{
	global $txt, $scripturl, $context;

	// The format of this array is: permission, action, title, description, icon.
	$quick_admin_tasks = array(
		array('', 'credits', 'support_credits_title', 'support_credits_info', 'support_and_credits.png'),
		array('admin_forum', 'featuresettings', 'modSettings_title', 'modSettings_info', 'features_and_options.png'),
		array('admin_forum', 'maintain', 'maintain_title', 'maintain_info', 'forum_maintenance.png'),
		array('manage_permissions', 'permissions', 'edit_permissions', 'edit_permissions_info', 'permissions_lg.png'),
		array('admin_forum', 'theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id'], 'theme_admin', 'theme_admin_info', 'themes_and_layout.png'),
		array('admin_forum', 'packages', 'package', 'package_info', 'packages_lg.png'),
		array('manage_smileys', 'smileys', 'smileys_manage', 'smileys_manage_info', 'smilies_and_messageicons.png'),
		array('moderate_forum', 'viewmembers', 'admin_users', 'member_center_info', 'members_lg.png'),
	);

	$available_admin_tasks = array();
	foreach ($quick_admin_tasks as $task)
	{
		if (!empty($task[0]) && !allowedTo($task[0]))
			continue;

		$available_admin_tasks[] = array(
			'href' => $scripturl . '?action=admin;area=' . $task[1],
			'link' => '<a href="' . $scripturl . '?action=admin;area=' . $task[1] . '">' . $txt[$task[2]] . '</a>',
			'title' => $txt[$task[2]],
			'description' => $txt[$task[3]],
			'icon' => $task[4],
			'is_last' => false
		);
	}

	if (count($available_admin_tasks) % 2 == 1)
	{
		$available_admin_tasks[] = array(
			'href' => '',
			'link' => '',
			'title' => '',
			'description' => '',
			'is_last' => true
		);
		$available_admin_tasks[count($available_admin_tasks) - 2]['is_last'] = true;
	}
	elseif (count($available_admin_tasks) != 0)
	{
		$available_admin_tasks[count($available_admin_tasks) - 1]['is_last'] = true;
		$available_admin_tasks[count($available_admin_tasks) - 2]['is_last'] = true;
	}

	// Lastly, fill in the blanks in the support resources paragraphs.
	$txt['support_resources_p1'] = sprintf($txt['support_resources_p1'],
		'https://github.com/elkarte/Elkarte/wiki',
		'https://github.com/elkarte/Elkarte/wiki/features',
		'https://github.com/elkarte/Elkarte/wiki/options',
		'https://github.com/elkarte/Elkarte/wiki/themes',
		'https://github.com/elkarte/Elkarte/wiki/packages'
	);
	$txt['support_resources_p2'] = sprintf($txt['support_resources_p2'],
		'http://www.elkarte.net/',
		'http://www.elkarte.net/redirect/support',
		'http://www.elkarte.net/redirect/customize_support'
	);

	return $available_admin_tasks;
}

/**
 * Search through source, theme and language files to determine their version.
 * Get detailed version information about the physical Elk files on the server.
 *
 * What it does:
 *
 * - the input parameter allows to set whether to include SSI.php and whether
 *   the results should be sorted.
 * - returns an array containing information on source files, templates and
 *   language files found in the default theme directory (grouped by language).
 * - options include include_ssi, include_subscriptions, sort_results
 *
 * @package Admin
 * @param mixed[] $versionOptions associative array of options
 * @return array
 */
function getFileVersions(&$versionOptions)
{
	global $settings;

	// Default place to find the languages would be the default theme dir.
	$lang_dir = $settings['default_theme_dir'] . '/languages';

	$version_info = array(
		'file_versions' => array(),
		'file_versions_admin' => array(),
		'file_versions_controllers' => array(),
		'file_versions_database' => array(),
		'file_versions_subs' => array(),
		'default_template_versions' => array(),
		'template_versions' => array(),
		'default_language_versions' => array(),
	);

	// Find the version in SSI.php's file header.
	if (!empty($versionOptions['include_ssi']) && file_exists(BOARDDIR . '/SSI.php'))
		readFileVersions($version_info, array('file_versions' => BOARDDIR), 'SSI.php');

	// Do the paid subscriptions handler?
	if (!empty($versionOptions['include_subscriptions']))
	{
		foreach (array(
			'subscriptions.php',
			'bootstrap.php',
			'email_imap_cron.php',
			'emailpost.php',
			'emailtopic.php') as $file)
		{
			if (file_exists(BOARDDIR . '/' . $file))
			{
				readFileVersions($version_info, array('file_versions' => BOARDDIR), $file);
			}
		}
	}

	// Load all the files in the sources and its sub directories
	$directories = array(
		'file_versions' => SOURCEDIR,
		'file_versions_admin' => ADMINDIR,
		'file_versions_controllers' => CONTROLLERDIR,
		'file_versions_database' => SOURCEDIR . '/database',
		'file_versions_lib' => EXTDIR
	);
	readFileVersions($version_info, $directories, '.php');
	$directories = array(
		'file_versions_subs' => SUBSDIR,
		'file_versions_modules' => SOURCEDIR . '/modules',
	);
	$tmp_version_info = array_combine(array_keys($directories),array_fill(0,count($directories),array()));
	readFileVersions($tmp_version_info, $directories, '.php', true);

	foreach ($tmp_version_info['file_versions_subs'] as $key => $val)
	{
		$version_info['file_versions_subs'][str_replace($directories['file_versions_subs'] . DIRECTORY_SEPARATOR, 'subs', $key)] = $val;
	}
	foreach ($tmp_version_info['file_versions_modules'] as $key => $val)
	{
		$version_info['file_versions_modules'][str_replace($directories['file_versions_modules'], 'modules', $key)] = $val;
	}
	// Load all the files in the default template directory - and the current theme if applicable.
	$directories = array('default_template_versions' => $settings['default_theme_dir']);
	if ($settings['theme_id'] != 1)
		$directories += array('template_versions' => $settings['theme_dir']);
	readFileVersions($version_info, $directories, 'template.php');
	readFileVersions($version_info, $directories, 'Theme.php');

	// Load up all the files in the default language directory and sort by language.
	// @todo merge this loop into readFileVersions
	$this_dir = dir($lang_dir);
	while ($path = $this_dir->read())
	{
		if ($path == '.' || $path == '..')
			continue;

		if (is_dir($lang_dir . '/' . $path))
		{
			$language = $path;
			$this_lang_path = $lang_dir . '/' . $language;
			$this_lang = dir($this_lang_path);
			while ($entry = $this_lang->read())
			{
				if (substr($entry, -4) == '.php' && $entry != 'index.php' && !is_dir($this_lang_path . '/' . $entry))
				{
					if (!is_writable($this_lang_path . '/' . $entry))
					{
						continue;
					}
					// Read the first 768 bytes from the file.... enough for the header.
					$header = file_get_contents($this_lang_path . '/' . $entry, false, null, 0, 768);

					// Split the file name off into useful bits.
					list ($name, $language) = explode('.', $entry);

					// Look for the version comment in the file header.
					if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
						$version_info['default_language_versions'][$language][$name] = $match[1];
					// It wasn't found, but the file was... show a '??'.
					else
						$version_info['default_language_versions'][$language][$name] = '??';
				}
			}
		}
	}
	$this_dir->close();

	// Sort the file versions by filename.
	if (!empty($versionOptions['sort_results']))
	{
		ksort($version_info['file_versions']);
		ksort($version_info['file_versions_admin']);
		ksort($version_info['file_versions_controllers']);
		ksort($version_info['file_versions_database']);
		ksort($version_info['file_versions_subs']);
		ksort($version_info['default_template_versions']);
		ksort($version_info['template_versions']);
		ksort($version_info['default_language_versions']);

		// For languages sort each language too.
		foreach ($version_info['default_language_versions'] as $language => $dummy)
			ksort($version_info['default_language_versions'][$language]);
	}

	return $version_info;
}

/**
 * Read a directory searching for files with a certain pattern in the name
 *
 * @param mixed[] $version_info -
 * @param string[] $directories - an array of directories to loop
 * @param string $pattern - how the name of the files should end
 * @param bool $recursive - if scan recursively the directories
 */
function readFileVersions(&$version_info, $directories, $pattern, $recursive = false)
{
	// The comment looks roughly like... that.
	$version_regex = '~\*\s@version\s+(.+)[\s]{2}~i';
	$unknown_version = '??';

	$ext_offset = -strlen($pattern);

	foreach ($directories as $type => $dirname)
	{
		if ($recursive === true)
		{
			$iter = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST,
				RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
			);
		}
		else
		{
			$iter = new IteratorIterator(new FilesystemIterator($dirname));
		}

		foreach ($iter as $dir)
		{
			if ($dir->isDir())
			{
				continue;
			}
			$entry = $dir->getFilename();

			if (substr($entry, $ext_offset) == $pattern)
			{
				if ($dir->isWritable() === false)
				{
					continue;
				}
				// Read the first 768 bytes from the file.... enough for the header.
				$header = file_get_contents($dir->getPathname(), false, null, 0, 768);

				if ($recursive === true)
				{
					$entry_key = $dir->getPathname();
				}
				else
				{
					$entry_key = $entry;
				}

				// Look for the version comment in the file header.
				if (preg_match($version_regex, $header, $match) == 1)
					$version_info[$type][$entry_key] = $match[1];
				// It wasn't found, but the file was... show a $unknown_version.
				else
					$version_info[$type][$entry_key] = $unknown_version;
			}
		}
	}
}

/**
 * Saves the time of the last db error for the error log
 *
 * What it does:
 *
 * - Done separately from Settings_Form::save_file() to avoid race conditions
 * which can occur during a db error
 * - If it fails Settings.php will assume 0
 *
 * @package Admin
 * @param int $time
 *
 * @todo seems a duplicate of Logging.php => logLastDatabaseError
 */
function updateDbLastError($time)
{
	// Write out the db_last_error file with the error timestamp
	file_put_contents(BOARDDIR . '/db_last_error.txt', $time, LOCK_EX);
}

/**
 * Saves the admins current preferences to the database.
 *
 * @package Admin
 */
function updateAdminPreferences()
{
	global $options, $context, $settings, $user_info;

	// This must exist!
	if (!isset($context['admin_preferences']))
		return false;

	// This is what we'll be saving.
	$options['admin_preferences'] = json_encode($context['admin_preferences']);

	require_once(SUBSDIR . '/Themes.subs.php');

	// Just check we haven't ended up with something theme exclusive somehow.
	removeThemeOptions('custom', 'all', 'admin_preferences');

	updateThemeOptions(array(1, $user_info['id'], 'admin_preferences', $options['admin_preferences']));

	// Make sure we invalidate any cache.
	Cache::instance()->put('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id'], null, 0);
}

/**
 * Send all the administrators a lovely email.
 *
 * What it does:
 *
 * - It loads all users who are admins or have the admin forum permission.
 * - It uses the email template and replacements passed in the parameters.
 * - It sends them an email.
 *
 * @package Admin
 * @param string $template
 * @param mixed[] $replacements
 * @param int[] $additional_recipients
 * @throws Elk_Exception
 */
function emailAdmins($template, $replacements = array(), $additional_recipients = array())
{
	global $language, $modSettings;

	$db = database();

	// We certainly want this.
	require_once(SUBSDIR . '/Mail.subs.php');

	// Load all groups which are effectively admins.
	$groups = $db->fetchQuery('
		SELECT id_group
		FROM {db_prefix}permissions
		WHERE permission = {string:admin_forum}
			AND add_deny = {int:add_deny}
			AND id_group != {int:id_group}',
		array(
			'add_deny' => 1,
			'id_group' => 0,
			'admin_forum' => 'admin_forum',
		)
	);
	$groups[] = 1;
	$groups = array_unique($groups);

	$emails_sent = $db->fetchQueryCallback('
		SELECT id_member, member_name, real_name, lngfile, email_address
		FROM {db_prefix}members
		WHERE (id_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:group_array_implode}, additional_groups) != 0)
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		array(
			'group_list' => $groups,
			'notify_types' => 4,
			'group_array_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		),
		function ($row) use($replacements, $modSettings, $language, $template)
		{
			// Stick their particulars in the replacement data.
			$replacements['IDMEMBER'] = $row['id_member'];
			$replacements['REALNAME'] = $row['member_name'];
			$replacements['USERNAME'] = $row['real_name'];

			// Load the data from the template.
			$emaildata = loadEmailTemplate($template, $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

			// Then send the actual email.
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);

			// Track who we emailed so we don't do it twice.
			return $row['email_address'];
		}
	);

	// Any additional users we must email this to?
	if (!empty($additional_recipients))
	{
		foreach ($additional_recipients as $recipient)
		{
			if (in_array($recipient['email'], $emails_sent))
				continue;

			$replacements['IDMEMBER'] = $recipient['id'];
			$replacements['REALNAME'] = $recipient['name'];
			$replacements['USERNAME'] = $recipient['name'];

			// Load the template again.
			$emaildata = loadEmailTemplate($template, $replacements, empty($recipient['lang']) || empty($modSettings['userLanguage']) ? $language : $recipient['lang']);

			// Send off the email.
			sendmail($recipient['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);
		}
	}
}

/**
 * Callback used in the core features page when the custom profiles
 * are enabled or disabled.
 *
 * @package Admin
 * @param bool $value the "new" status of the profile fields
 * (true => enabled, false => disabled)
 */
function custom_profiles_toggle_callback($value)
{
	$db = database();

	if (!$value)
	{
		// Disable all fields. Wouldn't want any to show when the feature is disabled.
		$db->query('', '
			UPDATE {db_prefix}custom_fields
			SET active = 0'
		);
	}
	else
	{
		// Set the display cache for the custom profile fields.
		require_once(SUBSDIR . '/ManageFeatures.subs.php');
		updateDisplayCache();
	}
}

/**
 * Callback used in the core features page when the paid subscriptions
 * are enabled or disabled.
 *
 * @package Admin
 * @param bool $value the "new" status of the paid subscriptions
 * (true => enabled, false => disabled)
 */
function subscriptions_toggle_callback($value)
{
	require_once(SUBSDIR . '/ScheduledTasks.subs.php');
	toggleTaskStatusByName('paid_subscriptions', $value);

	// Should we calculate next trigger?
	if ($value)
		calculateNextTrigger('paid_subscriptions');
}

/**
 * Callback used in the core features page when the post-by-email feature
 * is enabled or disabled.
 *
 * @package Admin
 * @param bool $value the "new" status of the post-by-email
 * (true => enabled, false => disabled)
 */
function postbyemail_toggle_callback($value)
{
	require_once(SUBSDIR . '/ScheduledTasks.subs.php');
	toggleTaskStatusByName('maillist_fetch_IMAP', $value);

	// Should we calculate next trigger?
	if ($value)
		calculateNextTrigger('maillist_fetch_IMAP');
}

/**
 * Enables a certain module on a set of controllers
 *
 * @package Admin
 * @param string $module the name of the module (e.g. drafts)
 * @param string[] $controllers list of controllers on which the module is
 *                 activated
 */
function enableModules($module, $controllers)
{
	global $modSettings;

	foreach ((array) $controllers as $controller)
	{
		if (!empty($modSettings['modules_' . $controller]))
			$existing = explode(',', $modSettings['modules_' . $controller]);
		else
			$existing = array();

		$existing[] = $module;
		$existing = array_filter(array_unique($existing));
		updateSettings(array('modules_' . $controller => implode(',', $existing)));
	}
}

/**
 * Disable a certain module on a set of controllers
 *
 * @package Admin
 * @param string $module the name of the module (e.g. drafts)
 * @param string[] $controllers list of controllers on which the module is
 *                 activated
 */
function disableModules($module, $controllers)
{
	global $modSettings;

	foreach ((array) $controllers as $controller)
	{
		if (!empty($modSettings['modules_' . $controller]))
			$existing = explode(',', $modSettings['modules_' . $controller]);
		else
			$existing = array();

		$existing = array_diff($existing, (array) $module);
		updateSettings(array('modules_' . $controller => implode(',', $existing)));
	}
}

/**
 * @param string $module the name of the module
 *
 * @return boolean
 */
function isModuleEnabled($module)
{
	global $modSettings;

	$module = strtolower($module);
	foreach ($modSettings as $key => $val)
	{
		if (substr($key, 0, 8) === 'modules_')
		{
			$modules = explode(',', $val);
			if (in_array($module, $modules))
				return true;
		}
	}

	return false;
}
