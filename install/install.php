<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:    BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1.9
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
load_lang_file();

// This is what we are.
$installurl = htmlspecialchars($_SERVER['PHP_SELF']);
$_SESSION['installing'] = true;

$action = new Install_Controller();

$incontext['steps'] = $action->steps;

// Default title...
$incontext['page_title'] = $txt['installer'];

// What step are we on?
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
	// Turn off magic quotes runtime and enable error reporting.
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

	// Add slashes, as long as they aren't already being added.
	foreach ($_POST as $k => $v)
	{
		if (strpos($k, 'password') === false && strpos($k, 'passwd') === false)
		{
			$_POST[$k] = addslashes($v);
		}
		else
		{
			$_POST[$k] = addcslashes($v, '\'');
		}
	}

	// PHP 5 might cry if we don't do this now.
	$server_offset = @mktime(0, 0, 0, 1, 1, 1970);
	date_default_timezone_set('Etc/GMT' . ($server_offset > 0 ? '+' : '') . ($server_offset / 3600));

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
 * Load the list of language files, and the current language file.
 */
function load_lang_file()
{
	global $incontext, $txt;

	$incontext['detected_languages'] = array();

	// Make sure the languages directory actually exists.
	if (file_exists(TMP_BOARDDIR . '/themes/default/languages'))
	{
		// Find all the "Install" language files in the directory.
		$dir = dir(TMP_BOARDDIR . '/themes/default/languages');
		while ($entry = $dir->read())
		{
			if (is_dir($dir->path . '/' . $entry) && file_exists($dir->path . '/' . $entry . '/Install.' . $entry . '.php'))
			{
				$incontext['detected_languages']['Install.' . $entry . '.php'] = ucfirst($entry);
			}
		}
		$dir->close();
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
		<h1 style="font-size: 14pt;">A critical error has occurred.</h1>

		<p>This installer was unable to find the installer\'s language file or files.  They should be found under:</p>

		<div style="margin: 1ex; font-family: monospace; font-weight: bold;">', dirname($_SERVER['PHP_SELF']) != '/' ? dirname($_SERVER['PHP_SELF']) : '', '/themes/default/languages</div>

		<p>In some cases, FTP clients do not properly upload files with this many folders.  Please double check to make sure you <span style="font-weight: 600;">have uploaded all the files in the distribution</span>.</p>
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
	if (!isset($_SESSION['installer_temp_lang']) || preg_match('~[^\\w_\\-.]~', $_SESSION['installer_temp_lang']) === 1 || !file_exists(TMP_BOARDDIR . '/themes/default/languages/' . substr($_SESSION['installer_temp_lang'], 8, -4) . '/' . $_SESSION['installer_temp_lang']))
	{
		// Use the first one...
		list ($_SESSION['installer_temp_lang']) = array_keys($incontext['detected_languages']);

		// If we have english and some other language, use the other language.  We Americans hate english :P.
		if ($_SESSION['installer_temp_lang'] == 'Install.english.php' && count($incontext['detected_languages']) > 1)
		{
			list (, $_SESSION['installer_temp_lang']) = array_keys($incontext['detected_languages']);
		}
	}

	// And now include the actual language file itself.
	require_once(TMP_BOARDDIR . '/themes/default/languages/' . substr($_SESSION['installer_temp_lang'], 8, -4) . '/' . $_SESSION['installer_temp_lang']);
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
		// The top install bit.
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
	if (count($settingsArray) == 1)
	{
		$settingsArray = preg_split('~[\r\n]~', $settingsArray[0]);
	}

	return saveFileSettings($config_vars, $settingsArray);
}

function parse_sqlLines($sql_file, $replaces)
{
	global $incontext, $txt, $db_prefix;

	$db = load_database();
	$db_table = db_table_install();
	$db_wrapper = new DbWrapper($db, $replaces);
	$db_table_wrapper = new DbTableWrapper($db_table);

	$exists = array();

	require_once($sql_file);

	$class_name = 'InstallInstructions_' . str_replace('-', '_', basename($sql_file, '.php'));
	$install_instance = new $class_name($db_wrapper, $db_table_wrapper);

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

	foreach ($tables as $table_method)
	{
		$table_name = substr($table_method, 6);

		// Copied from DbTable class
		// Strip out the table name, we might not need it in some cases
		$real_prefix = preg_match('~^("?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

		// With or without the database name, the fullname looks like this.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);

		if ($db_table->table_exists($full_table_name))
		{
			$incontext['sql_results']['table_dups']++;
			$exists[] = $table_method;
			continue;
		}

		$result = $install_instance->{$table_method}();

		if ($result === false)
		{
			$incontext['failures'][$table_method] = $db->last_error();
		}
	}

	foreach ($inserts as $insert_method)
	{
		$table_name = substr($insert_method, 6);

		if (in_array($table_name, $exists))
		{
			$db_wrapper->countMode();
			$incontext['sql_results']['insert_dups'] += $install_instance->{$insert_method}();
			$db_wrapper->countMode(false);

			continue;
		}

		$result = $install_instance->{$insert_method}();

		if ($result !== false)
		{
			$incontext['sql_results']['inserts'] += $result;
		}
		else
		{
			$incontext['failures'][] = $db->last_error();
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
		if ($number == 0)
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
 * Create an .htaccess file to prevent mod_security.
 * ElkArte has filtering built-in.
 */
function fixModSecurity()
{
	$htaccess_addition = '
<IfModule mod_security.c>
	# Turn off mod_security filtering.  We don\'t need our hands held.
	SecFilterEngine Off

	# The below probably isn\'t needed, but better safe than sorry.
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
		if (strpos($current_htaccess, '<IfModule mod_security.c>') !== false)
		{
			return true;
		}

		if ($ht_handle = fopen(TMP_BOARDDIR . '/.htaccess', 'ab'))
		{
			fwrite($ht_handle, $htaccess_addition);
			fclose($ht_handle);

			return true;
		}

		return false;
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
