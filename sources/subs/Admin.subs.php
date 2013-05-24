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
 * This file contains functions that are specifically done by administrators.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Get a list of versions that are currently installed on the server.
 *
 * @param array $checkFor
 */
function getServerVersions($checkFor)
{
	global $txt, $db_connection, $_PHPA, $memcached, $modSettings;

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
		$temp = New Imagick;
		$temp2 = $temp->getVersion();
		$versions['imagick'] = array('title' => $txt['support_versions_imagick'], 'version' => $temp2['versionString']);
	}

	// Now lets check for the Database.
	if (in_array('db_server', $checkFor))
	{
		$db = database();
		if (!isset($db_connection) || $db_connection === false)
			trigger_error('getServerVersions(): you need to be connected to the database in order to get its server version', E_USER_NOTICE);
		else
		{
			$versions['db_server'] = array('title' => sprintf($txt['support_versions_db'], $db->db_title()), 'version' => '');
			$versions['db_server']['version'] = $db->db_server_version();
		}
	}

	// If we're using memcache we need the server info.
	if (empty($memcached) && function_exists('memcache_get') && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
		get_memcached_server();

	// Check to see if we have any accelerators installed...
	if (in_array('mmcache', $checkFor) && defined('MMCACHE_VERSION'))
		$versions['mmcache'] = array('title' => 'Turck MMCache', 'version' => MMCACHE_VERSION);
	if (in_array('eaccelerator', $checkFor) && defined('EACCELERATOR_VERSION'))
		$versions['eaccelerator'] = array('title' => 'eAccelerator', 'version' => EACCELERATOR_VERSION);
	if (in_array('phpa', $checkFor) && isset($_PHPA))
		$versions['phpa'] = array('title' => 'ionCube PHP-Accelerator', 'version' => $_PHPA['VERSION']);
	if (in_array('apc', $checkFor) && extension_loaded('apc'))
		$versions['apc'] = array('title' => 'Alternative PHP Cache', 'version' => phpversion('apc'));
	if (in_array('memcache', $checkFor) && function_exists('memcache_set'))
		$versions['memcache'] = array('title' => 'Memcached', 'version' => empty($memcached) ? '???' : memcache_get_version($memcached));
	if (in_array('xcache', $checkFor) && function_exists('xcache_set'))
		$versions['xcache'] = array('title' => 'XCache', 'version' => XCACHE_VERSION);

	if (in_array('php', $checkFor))
		$versions['php'] = array('title' => 'PHP', 'version' => PHP_VERSION, 'more' => '?action=admin;area=serversettings;sa=phpinfo');

	if (in_array('server', $checkFor))
		$versions['server'] = array('title' => $txt['support_versions_server'], 'version' => $_SERVER['SERVER_SOFTWARE']);

	return $versions;
}

/**
 * Search through source, theme and language files to determine their version.
 * Get detailed version information about the physical ELKARTE files on the server.
 *
 * - the input parameter allows to set whether to include SSI.php and whether
 *   the results should be sorted.
 * - returns an array containing information on source files, templates and
 *   language files found in the default theme directory (grouped by language).
 *
 * @param array &$versionOptions
 *
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

	// The comment looks rougly like... that.
	$version_regex = '~\*\s@version\s+(.+)[\s]{2}~i';
	$unknown_version = '??';

	// Find the version in SSI.php's file header.
	if (!empty($versionOptions['include_ssi']) && file_exists(BOARDDIR . '/SSI.php'))
	{
		$header = file_get_contents(BOARDDIR . '/SSI.php', NULL, NULL, 0, 768);
		if (preg_match($version_regex, $header, $match) == 1)
			$version_info['file_versions']['SSI.php'] = $match[1];
		// Not found!  This is bad.
		else
			$version_info['file_versions']['SSI.php'] = $unknown_version;
	}

	// Do the paid subscriptions handler?
	if (!empty($versionOptions['include_subscriptions']) && file_exists(BOARDDIR . '/subscriptions.php'))
	{
		$header = file_get_contents(BOARDDIR . '/subscriptions.php', NULL, NULL, 0, 768);
		if (preg_match($version_regex, $header, $match) == 1)
			$version_info['file_versions']['subscriptions.php'] = $match[1];
		// If we haven't how do we all get paid?
		else
			$version_info['file_versions']['subscriptions.php'] = $unknown_version;
	}

	// Load all the files in the sources and its sub directorys
	$directories = array(
		'file_versions' => SOURCEDIR,
		'file_versions_admin' => ADMINDIR,
		'file_versions_controllers' => CONTROLLERDIR,
		'file_versions_database' => SOURCEDIR . '/database',
		'file_versions_subs' => SUBSDIR,
		'file_versions_lib' => EXTDIR
	);
	foreach ($directories as $area => $dir)
	{
		$sources_dir = dir($dir);
		while ($entry = $sources_dir->read())
		{
			if (substr($entry, -4) === '.php' && !is_dir($dir . '/' . $entry) && $entry !== 'index.php' && $entry !== 'sphinxapi.php')
			{
				// Read the first 4k from the file.... enough for the header.
				$header = file_get_contents($dir . '/' . $entry, NULL, NULL, 0, 768);

				// Look for the version comment in the file header.
				if (preg_match($version_regex, $header, $match))
					$version_info[$area][$entry] = $match[1];
				// It wasn't found, but the file was... show a $unknown_version.
				else
					$version_info[$area][$entry] = '??';
			}
		}
		$sources_dir->close();
	}

	// Load all the files in the default template directory - and the current theme if applicable.
	$directories = array('default_template_versions' => $settings['default_theme_dir']);
	if ($settings['theme_id'] != 1)
		$directories += array('template_versions' => $settings['theme_dir']);

	foreach ($directories as $type => $dirname)
	{
		$this_dir = dir($dirname);
		while ($entry = $this_dir->read())
		{
			if (substr($entry, -12) == 'template.php' && !is_dir($dirname . '/' . $entry))
			{
				// Read the first 768 bytes from the file.... enough for the header.
				$header = file_get_contents($dirname . '/' . $entry, NULL, NULL, 0, 768);

				// Look for the version comment in the file header.
				if (preg_match($version_regex, $header, $match) == 1)
					$version_info[$type][$entry] = $match[1];
				// It wasn't found, but the file was... show a '??'.
				else
					$version_info[$type][$entry] = $unknown_version;
			}
		}
		$this_dir->close();
	}

	// Load up all the files in the default language directory and sort by language.
	$this_dir = dir($lang_dir);
	while ($entry = $this_dir->read())
	{
		if (substr($entry, -4) == '.php' && $entry != 'index.php' && !is_dir($lang_dir . '/' . $entry))
		{
			// Read the first 768 bytes from the file.... enough for the header.
			$header = file_get_contents($lang_dir . '/' . $entry, NULL, NULL, 0, 768);

			// Split the file name off into useful bits.
			list ($name, $language) = explode('.', $entry);

			// Look for the version comment in the file header.
			if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
				$version_info['default_language_versions'][$language][$name] = $match[1];
			// It wasn't found, but the file was... show a '??'.
			else
				$version_info['default_language_versions'][$language][$name] = $unknown_version;
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
 * Saves the time of the last db error for the error log
 * - Done separately from Settings_Form::save_file() to avoid race conditions
 *   which can occur during a db error
 * - If it fails Settings.php will assume 0
 *
 * @param type $time
 */
function updateDbLastError($time)
{
	// Write out the db_last_error file with the error timestamp
	file_put_contents(BOARDDIR . '/db_last_error.php', '<' . '?' . "php\n" . '$db_last_error = ' . $time . ';', LOCK_EX);
	@touch(BOARDDIR . '/' . 'Settings.php');
}

/**
 * Saves the admins current preferences to the database.
 */
function updateAdminPreferences()
{
	global $options, $context, $settings, $user_info;

	$db = database();

	// This must exist!
	if (!isset($context['admin_preferences']))
		return false;

	// This is what we'll be saving.
	$options['admin_preferences'] = serialize($context['admin_preferences']);

	// Just check we haven't ended up with something theme exclusive somehow.
	$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
		AND variable = {string:admin_preferences}',
		array(
			'default_theme' => 1,
			'admin_preferences' => 'admin_preferences',
		)
	);

	// Update the themes table.
	$db->insert('replace',
		'{db_prefix}themes',
		array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		array($user_info['id'], 1, 'admin_preferences', $options['admin_preferences']),
		array('id_member', 'id_theme', 'variable')
	);

	// Make sure we invalidate any cache.
	cache_put_data('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id'], null, 0);
}

/**
 * Send all the administrators a lovely email.
 * It loads all users who are admins or have the admin forum permission.
 * It uses the email template and replacements passed in the parameters.
 * It sends them an email.
 *
 * @param string $template
 * @param array $replacements
 * @param array $additional_recipients
 */
function emailAdmins($template, $replacements = array(), $additional_recipients = array())
{
	global $language, $modSettings;

	$db = database();

	// We certainly want this.
	require_once(SUBSDIR . '/Mail.subs.php');

	// Load all groups which are effectively admins.
	$request = $db->query('', '
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
	$groups = array(1);
	while ($row = $db->fetch_assoc($request))
		$groups[] = $row['id_group'];
	$db->free_result($request);

	$request = $db->query('', '
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
	);
	$emails_sent = array();
	while ($row = $db->fetch_assoc($request))
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
		$emails_sent[] = $row['email_address'];
	}
	$db->free_result($request);

	// Any additional users we must email this to?
	if (!empty($additional_recipients))
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