<?php

/**
 * Functions to support admin controller
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
 * This file contains functions that are specifically done by administrators.
 *
 */

use ElkArte\Cache\Cache;
use ElkArte\Languages\Txt;
use ElkArte\User;

/**
 * Get a list of versions that are currently installed on the server.
 *
 * @param string[] $checkFor
 *
 * @return array
 * @package Admin
 */
function getServerVersions($checkFor)
{
	global $txt;

	$db = database();

	Txt::load('Admin');

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
		$temp = new Imagick();
		$temp2 = $temp->getVersion();
		$versions['imagick'] = array('title' => $txt['support_versions_imagick'], 'version' => $temp2['versionString']);
	}

	// Now lets check for the Database.
	if (in_array('db_server', $checkFor))
	{
		$conn = $db->connection();
		if (empty($conn))
		{
			trigger_error('getServerVersions(): you need to be connected to the database in order to get its server version', E_USER_NOTICE);
		}
		else
		{
			$versions['db_server'] = array(
				'title' => sprintf($txt['support_versions_db'], $db->title()),
				'version' => $db->server_version());
		}
	}

	require_once(SUBSDIR . '/Cache.subs.php');
	$cache_engines = loadCacheEngines();
	foreach ($cache_engines as $name => $details)
	{
		if (in_array($name, $checkFor))
		{
			$versions[$name] = $details;
		}
	}

	if (in_array('opcache', $checkFor) && extension_loaded('Zend OPcache'))
	{
		$opcache_config = @opcache_get_configuration();
		if (!empty($opcache_config['directives']['opcache.enable']))
		{
			$versions['opcache'] = array('title' => $opcache_config['version']['opcache_product_name'], 'version' => $opcache_config['version']['version']);
		}
	}

	// PHP Version
	if (in_array('php', $checkFor))
	{
		$versions['php'] = array('title' => 'PHP', 'version' => PHP_VERSION . ' (' . PHP_SAPI . ')', 'more' => '?action=admin;area=serversettings;sa=phpinfo');
	}

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
		{
			$versions['server_load'] = array('title' => $txt['loadavg'], 'version' => $loading);
		}
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
	global $txt, $context;

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
		{
			continue;
		}

		$available_admin_tasks[] = array(
			'href' => getUrl('admin', ['action' => 'admin', 'area' => $task[1]]),
			'link' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => $task[1]]) . '">' . $txt[$task[2]] . '</a>',
			'title' => $txt[$task[2]],
			'description' => $txt[$task[3]],
			'icon' => $task[4],
			'is_last' => false
		);
	}

	if (count($available_admin_tasks) % 2 === 1)
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
	elseif (count($available_admin_tasks) !== 0)
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
		'https://www.elkarte.net/',
		'https://www.elkarte.net/redirect/support',
		'https://www.elkarte.net/redirect/customize_support'
	);

	return $available_admin_tasks;
}

/**
 * Saves the admins current preferences to the database.
 *
 * @package Admin
 */
function updateAdminPreferences()
{
	global $options, $context, $settings;

	// This must exist!
	if (!isset($context['admin_preferences']))
	{
		return false;
	}

	// This is what we'll be saving.
	$options['admin_preferences'] = json_encode($context['admin_preferences']);

	require_once(SUBSDIR . '/Themes.subs.php');

	// Just check we haven't ended up with something theme exclusive somehow.
	removeThemeOptions('custom', 'all', 'admin_preferences');

	updateThemeOptions(array(1, User::$info->id, 'admin_preferences', $options['admin_preferences']));

	// Make sure we invalidate any cache.
	Cache::instance()->put('theme_settings-' . $settings['theme_id'] . ':' . User::$info->id, null, 0);

	return true;
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
 * @param string $template
 * @param mixed[] $replacements
 * @param int[] $additional_recipients
 * @package Admin
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
	)->fetch_all();
	$groups[] = 1;
	$groups = array_unique($groups);

	$emails_sent = $db->fetchQuery('
		SELECT id_member, member_name, real_name, lngfile, email_address
		FROM {db_prefix}members
		WHERE (id_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:group_array_implode}, additional_groups) != 0)
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		array(
			'group_list' => $groups,
			'notify_types' => 4,
			'group_array_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	)->fetch_callback(
		function ($row) use ($replacements, $modSettings, $language, $template) {
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
			{
				continue;
			}

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
 * @param bool $value the "new" status of the profile fields
 * (true => enabled, false => disabled)
 * @package Admin
 */
function custom_profiles_toggle_callback($value)
{
	$db = database();

	if (!$value)
	{
		// Disable all active fields. Wouldn't want any to show when the feature is disabled.
		$db->query('', '
			UPDATE {db_prefix}custom_fields
			SET active = {int:inactive}
			WHERE active = {int:active}',
			array(
				'active' => 1,
				'inactive' => -1,
			)
		);
	}
	else
	{
		// Set back what was formally active
		$db->query('', '
			UPDATE {db_prefix}custom_fields
			SET active = {int:inactive}
			WHERE active = {int:active}',
			array(
				'active' => -1,
				'inactive' => 1,
			)
		);

		// Set the display cache for the custom profile fields.
		require_once(SUBSDIR . '/ManageFeatures.subs.php');
		updateDisplayCache();
	}
}

/**
 * Callback used in the core features page when the paid subscriptions
 * are enabled or disabled.
 *
 * @param bool $value the "new" status of the paid subscriptions
 * (true => enabled, false => disabled)
 * @package Admin
 */
function subscriptions_toggle_callback($value)
{
	require_once(SUBSDIR . '/ScheduledTasks.subs.php');
	toggleTaskStatusByName('paid_subscriptions', $value);

	// Should we calculate next trigger?
	if ($value)
	{
		calculateNextTrigger('paid_subscriptions');
	}
}

/**
 * Callback used in the core features page when the post-by-email feature
 * is enabled or disabled.
 *
 * @param bool $value the "new" status of the post-by-email
 * (true => enabled, false => disabled)
 * @package Admin
 */
function postbyemail_toggle_callback($value)
{
	require_once(SUBSDIR . '/ScheduledTasks.subs.php');
	toggleTaskStatusByName('maillist_fetch_IMAP', $value);

	// Should we calculate next trigger?
	if ($value)
	{
		calculateNextTrigger('maillist_fetch_IMAP');
	}
}

/**
 * Enables a certain module on a set of controllers
 *
 * @param string $module the name of the module (e.g. drafts)
 * @param string[] $controllers list of controllers on which the module is
 *                 activated
 * @package Admin
 */
function enableModules($module, $controllers)
{
	global $modSettings;

	foreach ((array) $controllers as $controller)
	{
		if (!empty($modSettings['modules_' . $controller]))
		{
			$existing = explode(',', $modSettings['modules_' . $controller]);
		}
		else
		{
			$existing = array();
		}

		$existing[] = $module;
		$existing = array_filter(array_unique($existing));
		updateSettings(array('modules_' . $controller => implode(',', $existing)));
	}
}

/**
 * Disable a certain module on a set of controllers
 *
 * @param string $module the name of the module (e.g. drafts)
 * @param string[] $controllers list of controllers on which the module is
 *                 activated
 * @package Admin
 */
function disableModules($module, $controllers)
{
	global $modSettings;

	foreach ((array) $controllers as $controller)
	{
		if (!empty($modSettings['modules_' . $controller]))
		{
			$existing = explode(',', $modSettings['modules_' . $controller]);
		}
		else
		{
			$existing = array();
		}

		$existing = array_diff($existing, (array) $module);
		updateSettings(array('modules_' . $controller => implode(',', $existing)));
	}
}

/**
 * @param string $module the name of the module
 *
 * @return bool
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
			{
				return true;
			}
		}
	}

	return false;
}
