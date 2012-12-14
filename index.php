<?php

/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
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
 * This, as you have probably guessed, is the crux for all functions.
 * Everything should start here, so all the setup and security is done
 * properly.  The most interesting part of this file is the action array in
 * the smf_main() function.  It is formatted as so:
 * 	'action-in-url' => array('Source-File.php', 'FunctionToCall'),
 *
 * Then, you can access the FunctionToCall() function from Source-File.php
 * with the URL index.php?action=action-in-url.  Relatively simple, no?
 *
 */

$forum_version = 'DIALOGO 1.0 Alpha';

// Get everything started up...
define('DIALOGO', 1);
if (function_exists('set_magic_quotes_runtime'))
	@set_magic_quotes_runtime(0);
error_reporting(defined('E_STRICT') ? E_ALL | E_STRICT : E_ALL);
$time_start = microtime();

// This makes it so headers can be sent!
ob_start();

// Do some cleaning, just in case.
foreach (array('db_character_set', 'cachedir') as $variable)
	if (isset($GLOBALS[$variable]))
		unset($GLOBALS[$variable], $GLOBALS[$variable]);

// Load the settings...
require_once(dirname(__FILE__) . '/Settings.php');

// Make absolutely sure the cache directory is defined.
if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';

// And important includes.
require_once($sourcedir . '/QueryString.php');
require_once($sourcedir . '/Session.php');
require_once($sourcedir . '/Subs.php');
require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Logging.php');
require_once($sourcedir . '/Load.php');
require_once($sourcedir . '/Subs-Cache.php');
require_once($sourcedir . '/Security.php');
require_once($sourcedir . '/Class-BrowserDetect.php');

// If $maintenance is set specifically to 2, then we're upgrading or something.
if (!empty($maintenance) && $maintenance == 2)
	display_maintenance_message();

// Create a variable to store some specific functions in.
$smcFunc = array();

// Initiate the database connection and define some database functions to use.
loadDatabase();

// Load the settings from the settings table, and perform operations like optimizing.
reloadSettings();
// Clean the request variables, add slashes, etc.
cleanRequest();
$context = array();

// Seed the random generator.
if (empty($modSettings['rand_seed']) || mt_rand(1, 250) == 69)
	smf_seed_generator();

// Before we get carried away, are we doing a scheduled task? If so save CPU cycles by jumping out!
if (isset($_GET['scheduled']))
{
	require_once($sourcedir . '/ScheduledTasks.php');
	AutoTask();
}

// Check if compressed output is enabled, supported, and not already being done.
if (!empty($modSettings['enableCompressedOutput']) && !headers_sent())
{
	// If zlib is being used, turn off output compression.
	if (ini_get('zlib.output_compression') >=  1 || ini_get('output_handler') == 'ob_gzhandler')
		$modSettings['enableCompressedOutput'] = 0;
	else
	{
		ob_end_clean();
		ob_start('ob_gzhandler');
	}
}

// Register an error handler.
set_error_handler('error_handler');

// Start the session. (assuming it hasn't already been.)
loadSession();

// Restore post data if we are revalidating OpenID.
if (isset($_GET['openid_restore_post']) && !empty($_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]['post']) && empty($_POST))
{
	$_POST = $_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]['post'];
	unset($_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]);
}

// Pre-dispatch
smf_main();

// Call obExit specially; we're coming from the main area ;).
obExit(null, null, true);

/**
 * The main dispatcher.
 * This delegates to each area.
 */
function smf_main()
{
	global $modSettings, $settings, $user_info, $board, $topic, $board_info, $maintenance, $sourcedir;

	// Special case: session keep-alive, output a transparent pixel.
	if (isset($_GET['action']) && $_GET['action'] == 'keepalive')
	{
		header('Content-Type: image/gif');
		die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	}

	// Load the user's cookie (or set as guest) and load their settings.
	loadUserSettings();

	// Load the current board's information.
	loadBoard();

	// Load the current user's permissions.
	loadPermissions();

	// Attachments don't require the entire theme to be loaded.
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'dlattach' && (!empty($modSettings['allow_guestAccess']) && $user_info['is_guest']))
		detectBrowser();
	// Load the current theme.  (note that ?theme=1 will also work, may be used for guest theming.)
	else
		loadTheme();

	// Check if the user should be disallowed access.
	is_not_banned();

	// If we are in a topic and don't have permission to approve it then duck out now.
	if (!empty($topic) && empty($board_info['cur_topic_approved']) && !allowedTo('approve_posts') && ($user_info['id'] != $board_info['cur_topic_starter'] || $user_info['is_guest']))
		fatal_lang_error('not_a_topic', false);

	$no_stat_actions = array('dlattach', 'findmember', 'jsoption', 'requestmembers', '.xml', 'xmlhttp', 'verificationcode', 'viewquery', 'viewadminfile');
	call_integration_hook('integrate_pre_log_stats', array($no_stat_actions));
	// Do some logging, unless this is an attachment, avatar, toggle of editor buttons, theme option, XML feed etc.
	if (empty($_REQUEST['action']) || !in_array($_REQUEST['action'], $no_stat_actions))
	{
		// Log this user as online.
		writeLog();

		// Track forum statistics and hits...?
		if (!empty($modSettings['hitStats']))
			trackStats(array('hits' => '+'));
	}
	unset($no_stat_actions);

	// What shall we do?
	require_once $sourcedir . '/Class-Dispatcher.php';
	$dispatcher = new site_Dispatcher();
	$dispatcher->dispatch();
}
