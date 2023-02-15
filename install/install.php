<?php

/**
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

require('installcore.php');
require('CommonCode.php');
require('Install_Controller.php');

// Don't have PHP support, do you?
// ><html dir="ltr"><head><title>Error!</title></head><body>Sorry, this installer requires PHP!<div style="display: none;">

// Database info.
$databases = array();
load_possible_databases();

// Initialize everything and load the language files.
$txt = array();
initialize_inputs();
loadLanguageFile();

// This is what we are.
$installurl = htmlspecialchars($_SERVER['PHP_SELF']);
$_SESSION['installing'] = true;

// Query the installation controller for the number of steps
$action = new Install_Controller();
$incontext['steps'] = $action->steps;

// What step are we on?
$incontext['page_title'] = $txt['installer'];
$incontext['current_step'] = $action->dispatch(isset($_GET['step']) ? (int) $_GET['step'] : 0);
$incontext['overall_percent'] = $action->overall_percent;

// Actually do the template stuff.
installExit();

/**
 * Initialization step. Called at each request.
 * It either sets up variables for other steps, or handle a few requests on its own.
 */
function initialize_inputs()
{
	// Enable error reporting.
	error_reporting(E_ALL & ~E_DEPRECATED);

	if (!defined('TMP_BOARDDIR'))
	{
		define('TMP_BOARDDIR', realpath(__DIR__ . '/..'));
	}

	// This is the test for support of compression
	if (isset($_GET['obgz']))
	{
		require('test_compression.php');
		die;
	}

	// This is really quite simple; if ?delete is on the URL, delete the installer...
	if (isset($_GET['delete']))
	{
		return action_deleteInstaller();
	}

	ob_start();
	if (ini_get('session.save_handler') === 'user')
	{
		@ini_set('session.save_handler', 'files');
	}
	if (function_exists('session_start'))
	{
		@session_start();
	}

	// Get/Set a timezone
	setTimeZone();

	header('X-Frame-Options: SAMEORIGIN');
	header('X-XSS-Protection: 1');
	header('X-Content-Type-Options: nosniff');

	// Force an integer step, defaulting to 0.
	$_GET['step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;

	require_once(__DIR__ . '/LegacyCode.php');
	require_once(__DIR__ . '/ToRefactorCode.php');
	require_once(__DIR__ . '/TemplateInstall.php');
}

/**
 * Get or Set the default timezone, if available
 */
function setTimeZone() {

	$ini_timezone = ini_get('date.timezone');
	$timezone_id = '';
	if (!empty($ini_timezone))
	{
		$timezone_id = $ini_timezone;
	}

	// Validate this is a valid zone
	if (!in_array($timezone_id, timezone_identifiers_list(), true))
	{
		// Tray and make one up
		$server_offset = @mktime(0, 0, 0, 1, 1, 1970) * -1;
		$timezone_id = timezone_name_from_abbr('', $server_offset, 0);

		if (empty($timezone_id))
		{
			$timezone_id = 'UTC';
		}
	}

	date_default_timezone_set($timezone_id);
}

/**
 * Read the list of available language files, and ensure one is set.
 * Hint: they are dumped into the $txt global
 */
function loadLanguageFile()
{
	global $incontext, $txt;

	$incontext['detected_languages'] = array();

	// Make sure the languages' directory actually exists.
	if (file_exists(TMP_BOARDDIR . '/sources/ElkArte/Languages/Install'))
	{
		// Find all the "Install" language files in the directory.
		$entry = new \GlobIterator(TMP_BOARDDIR . '/sources/ElkArte/Languages/Install/*.php', \FilesystemIterator::SKIP_DOTS);
		foreach ($entry as $file)
		{
			$entry = $file->getFilename();
			$incontext['detected_languages'][$entry] = basename($entry, '.php');
		}
	}

	// Didn't find any, show an error message!
	if (empty($incontext['detected_languages']))
	{
		// Let's not cache this message, eh?
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		echo '<!DOCTYPE html>
<html>
	<head>
		<title>Installer: Error!</title>
	</head>
	<body style="font-family: sans-serif;">
	<div style="width: 600px;">
		<h1>A critical error has occurred.</h1>

		<p>This installer was unable to find the installer\'s language file or files.  They should be found under:</p>

		<div style="font-family: monospace; font-weight: bold;">', dirname($_SERVER['PHP_SELF']) != '/' ? dirname($_SERVER['PHP_SELF']) : '', '/themes/default/languages</div>

		<p>In some cases, FTP clients do not properly upload files with this many folders.  Please double-check to make sure you <span style="font-weight: 600;">have uploaded all the files in the distribution</span>.</p>
		<p>If that doesn\'t help, please make sure this install.php file is in the same place as the themes folder.</p>
		<p>If you continue to get this error message, feel free to <a href="', SITE_SOFTWARE, '">look to us for support</a>.</p>
	</div>
	</body>
</html>';
		die;
	}

	// Override the language file?
	if (isset($_GET['lang_file']))
	{
		$_SESSION['installer_temp_lang'] = $_GET['lang_file'];
	}

	// Make sure it exists, if it doesn't reset it.
	if (!isset($_SESSION['installer_temp_lang']) || preg_match('~[^\\w_\\-.]~', $_SESSION['installer_temp_lang']) === 1 || !file_exists(TMP_BOARDDIR . '/sources/ElkArte/Languages/Install/' . $_SESSION['installer_temp_lang']))
	{
		// Use the first one...
		list ($_SESSION['installer_temp_lang']) = array_keys($incontext['detected_languages']);

		// If we have english and some other language, use the other language.  We Americans hate english :P.
		if ($_SESSION['installer_temp_lang'] == 'English.php' && count($incontext['detected_languages']) > 1)
		{
			list (, $_SESSION['installer_temp_lang']) = array_values($incontext['detected_languages']);
		}
	}

	// And now include the actual language file itself.
	require_once(TMP_BOARDDIR . '/sources/ElkArte/Languages/Install/' . $_SESSION['installer_temp_lang']);
}

/**
 * This is called upon exiting the installer, for template etc.
 *
 * @param bool $fallThrough
 */
function installExit($fallThrough = false)
{
	global $incontext, $installurl;

	// Send character set.
	header('Content-Type: text/html; charset=UTF-8');

	// We usually dump our templates out.
	if (!$fallThrough)
	{
		// The top installation bit.
		template_install_above();

		// Call the template.
		if (isset($incontext['sub_template']))
		{
			$incontext['form_url'] = $installurl . '?step=' . $incontext['current_step'];

			call_user_func('template_' . $incontext['sub_template']);
		}

		// Show the footer.
		template_install_below();
	}

	// Bang - gone!
	die();
}

/**
 * Write out the contents of Settings.php file.
 *
 * This function will add the variables passed to it in $config_vars,
 * to the Settings.php file.
 *
 * @param array $config_vars the configuration variables to write out.
 */
function updateSettingsFile($config_vars)
{
	// Lets ensure its writable
	if (!is_writeable(dirname(__FILE__, 2) . '/Settings.php'))
	{
		@chmod(dirname(__FILE__, 2) . '/Settings.php', 0777);

		if (!is_writeable(dirname(__FILE__, 2) . '/Settings.php'))
		{
			return false;
		}
	}

	// Modify Settings.php.
	$settingsArray = file(TMP_BOARDDIR . '/Settings.php');

	// @todo Do we just want to read the file in clean, and split it this way always?
	return saveFileSettings($config_vars, $settingsArray);
}

/**
 * Parse the database install file and execute its queries
 *
 * @param $sql_file
 * @param $replaces
 */
function parseSqlLines($sql_file, $replaces)
{
	global $incontext, $txt, $db_prefix;

	// All the database handlers
	$db = load_database();
	$db_table = db_table_install();
	$db_wrapper = new DbWrapper($db, $replaces);
	$db_table_wrapper = new DbTableWrapper($db_table);

	// The file with the commands we will run
	$exists = array();
	require_once($sql_file);

	// InstallInstructions_install_2_0 or InstallInstructions_install_2_0_postgresql
	$class_name = 'InstallInstructions_' . str_replace('-', '_', basename($sql_file, '.php'));
	$install_instance = new $class_name($db_wrapper, $db_table_wrapper);

	// Each method is a separate installation step
	$methods = get_class_methods($install_instance);
	$tables = array_filter($methods, static function ($method) {
		return strpos($method, 'table_') === 0;
	});
	$inserts = array_filter($methods, static function ($method) {
		return strpos($method, 'insert_') === 0;
	});
	$others = array_filter($methods, static function ($method) {
		return strpos($method, '__') !== 0 && strpos($method, 'insert_') !== 0 && strpos($method, 'table_') !== 0;
	});

	// Create tables if they do not exist
	foreach ($tables as $table_method)
	{
		$table_name = substr($table_method, 6);

		// Copied from DbTable class
		// Strip out the table name, we might not need it in some cases
		$real_prefix = preg_match('~^("?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

		// With or without the database name, the fullname looks like this.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);

		// Note it as a duplicate
		if ($db_table->table_exists($full_table_name))
		{
			$incontext['sql_results']['table_dups']++;
			$exists[] = $table_method;
			continue;
		}

		// Create the table
		$result = $install_instance->{$table_method}();

		// If we fail, lets record why
		if ($result === false)
		{
			$incontext['failures'][$table_method] = $db->last_error();
		}
		else
		{
			$incontext['sql_results']['tables']++;
		}
	}

	// Now insert data into tables
	foreach ($inserts as $insert_method)
	{
		$table_name = substr($insert_method, 6);

		// Don't insert data if the table already existed before we started
		if (in_array($table_name, $exists, true))
		{
			$db_wrapper->countMode();
			$incontext['sql_results']['insert_dups'] += $install_instance->{$insert_method}();
			$db_wrapper->countMode(false);

			continue;
		}

		// Run the insert
		$result = $install_instance->{$insert_method}();

		// Log our progress or failures
		if ($result === false)
		{
			$incontext['failures'][] = $db->last_error();
		}
		else
		{
			$incontext['sql_results']['inserts'] += $result;
		}
	}

	// Errors here are ignored
	foreach ($others as $other_method)
	{
		$install_instance->{$other_method}();
	}

	// Sort out the context for the SQL.
	foreach ($incontext['sql_results'] as $key => $number)
	{
		if ($number === 0)
		{
			unset($incontext['sql_results'][$key]);
		}
		else
		{
			$incontext['sql_results'][$key] = sprintf($txt['db_populate_' . $key], $number);
		}
	}
}

/**
 * Create an .htaccess file to prevent mod_security from interfering
 * ElkArte has filtering built-in.
 */
function fixModSecurity()
{
	$htaccess_addition = '
<IfModule mod_security.c>
	# Turn off mod_security filtering. 
	SecFilterEngine Off
	SecFilterScanPOST Off
</IfModule>';

	if (!function_exists('apache_get_modules') || !in_array('mod_security', apache_get_modules()))
	{
		return true;
	}

	if (file_exists(TMP_BOARDDIR . '/.htaccess') && is_writable(TMP_BOARDDIR . '/.htaccess'))
	{
		$current_htaccess = implode('', file(TMP_BOARDDIR . '/.htaccess'));

		// Only change something if mod_security hasn't been addressed yet.
		if (strpos($current_htaccess, '<IfModule mod_security.c>') === false)
		{
			if ($ht_handle = fopen(TMP_BOARDDIR . '/.htaccess', 'a'))
			{
				fwrite($ht_handle, $htaccess_addition);
				fclose($ht_handle);

				return true;
			}

			return false;
		}

		return true;
	}

	if (file_exists(TMP_BOARDDIR . '/.htaccess'))
	{
		return strpos(implode('', file(TMP_BOARDDIR . '/.htaccess')), '<IfModule mod_security.c>') !== false;
	}

	if (is_writable(TMP_BOARDDIR))
	{
		if ($ht_handle = fopen(TMP_BOARDDIR . '/.htaccess', 'wb'))
		{
			fwrite($ht_handle, $htaccess_addition);
			fclose($ht_handle);

			return true;
		}

		return false;
	}

	return false;
}

/**
 * Escapes (replaces) characters in strings to make them safe for use in javascript
 *
 * @param string $string
 * @return string
 */
function JavaScriptEscape($string)
{
	return '\'' . strtr($string, array(
			"\r" => '',
			"\n" => '\\n',
			"\t" => '\\t',
			'\\' => '\\\\',
			'\'' => '\\\'',
			'</' => '<\' + \'/',
			'<script' => '<scri\'+\'pt',
			'<body>' => '<bo\'+\'dy>',
			'<a href' => '<a hr\'+\'ef',
		)) . '\'';
}
