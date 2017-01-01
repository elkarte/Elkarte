<?php

/**
 * This, as you have probably guessed, is the crux for all functions.
 * Everything should start here, so all the setup and security is done
 * properly.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

$time_start = microtime(true);

// The software version
const FORUM_VERSION = 'ElkArte 1.1 beta 4';

// First things first, but not necessarily in that order.
const ELK = '1';

// Shortcut for the browser cache stale
const CACHE_STALE = '?R11B4';

// Report errors but not depreciated ones
error_reporting(E_ALL | E_STRICT & ~8192);

// Directional only script time usage for display
// getrusage is missing in php < 7 on Windows
if (function_exists('getrusage'))
	$rusage_start = getrusage();
else
	$rusage_start = array();

// Turn on output buffering if it isn't already on (via php.ini for example)
if (!ob_get_level())
	ob_start();

$db_show_debug = false;

// We don't need no globals. (a bug in "old" versions of PHP)
foreach (array('db_character_set', 'cachedir') as $variable)
	if (isset($GLOBALS[$variable]))
		unset($GLOBALS[$variable], $GLOBALS[$variable]);

// Where the Settings.php file is located
$settings_loc = __DIR__ . '/Settings.php';

// First thing: if the install dir exists, just send anybody there
// The ignore_install_dir var is for developers only. Do not add it on production sites
if (file_exists('install'))
{
	if (file_exists($settings_loc))
	{
		require_once($settings_loc);
	}
	if (empty($ignore_install_dir))
	{
		// No install_time defined or finished the installing in the last 2 minutes
		if (empty($install_time) || $install_time - time() < 120)
		{
			$redirec_file = 'install.php';
		}
		else
		{
			$redirec_file = 'upgrade.php';
		}

		header('Location: http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 's' : '') . '://' . (empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST']) . (strtr(dirname($_SERVER['PHP_SELF']), '\\', '/') == '/' ? '' : strtr(dirname($_SERVER['PHP_SELF']), '\\', '/')) . '/install/' . $redirec_file);
		die();
	}
}
else
{
	require_once($settings_loc);
}

// Make sure the paths are correct... at least try to fix them.
if (!file_exists($boarddir) && file_exists(__DIR__ . '/agreement.txt'))
	$boarddir = __DIR__;
if (!file_exists($sourcedir . '/SiteDispatcher.class.php') && file_exists($boarddir . '/sources'))
	$sourcedir = $boarddir . '/sources';

// Check that directories which didn't exist in past releases are initialized.
if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';
if ((empty($extdir) || !file_exists($extdir)) && file_exists($sourcedir . '/ext'))
	$extdir = $sourcedir . '/ext';
if ((empty($languagedir) || !file_exists($languagedir)) && file_exists($boarddir . '/themes/default/languages'))
	$languagedir = $boarddir . '/themes/default/languages';

// Time to forget about variables and go with constants!
DEFINE('BOARDDIR', $boarddir);
DEFINE('CACHEDIR', $cachedir);
DEFINE('EXTDIR', $extdir);
DEFINE('LANGUAGEDIR', $languagedir);
DEFINE('SOURCEDIR', $sourcedir);
DEFINE('ADMINDIR', $sourcedir . '/admin');
DEFINE('CONTROLLERDIR', $sourcedir . '/controllers');
DEFINE('SUBSDIR', $sourcedir . '/subs');
DEFINE('ADDONSDIR', $boarddir . '/addons');
unset($boarddir, $cachedir, $sourcedir, $languagedir, $extdir);

// Files we cannot live without.
require_once(SOURCEDIR . '/QueryString.php');
require_once(SOURCEDIR . '/Session.php');
require_once(SOURCEDIR . '/Subs.php');
require_once(SOURCEDIR . '/Logging.php');
require_once(SOURCEDIR . '/Load.php');
require_once(SOURCEDIR . '/Security.php');
require_once(SUBSDIR . '/Cache.subs.php');

// Initialize the class Autoloader
require(SOURCEDIR . '/Autoloader.class.php');
$autoloder = Elk_Autoloader::getInstance();
$autoloder->setupAutoloader(array(SOURCEDIR, SUBSDIR, CONTROLLERDIR, ADMINDIR, ADDONSDIR));
$autoloder->register(SOURCEDIR, '\\ElkArte');
$autoloder->register(SOURCEDIR . '/subs/BBC', '\\BBC');

// Show lots of debug information below the page, not for production sites
if ($db_show_debug === true)
	Debug::get()->rusage('start', $rusage_start);

// Forum in extended maintenance mode? Our trip ends here with a bland message.
if (!empty($maintenance) && $maintenance == 2)
	Errors::instance()->display_maintenance_message();

// Clean the request.
cleanRequest();

// Initiate the database connection and define some database functions to use.
loadDatabase();

// Let's set up our shiny new hooks handler.
Hooks::init(database(), Debug::get());

// It's time for settings loaded from the database.
reloadSettings();

// Our good ole' contextual array, which will hold everything
if (!isset($context))
{
	$context = array();
}

// Seed the random generator.
elk_seed_generator();

// Before we get carried away, are we doing a scheduled task? If so save CPU cycles by jumping out!
if (isset($_GET['scheduled']))
{
	// Don't make people wait on us if we can help it.
	if (function_exists('fastcgi_finish_request'))
		fastcgi_finish_request();

	$controller = new ScheduledTasks_Controller();
	$controller->action_autotask();
}

// Check if compressed output is enabled, supported, and not already being done.
if (!empty($modSettings['enableCompressedOutput']) && !headers_sent())
{
	// If zlib is being used, turn off output compression.
	if (detectServer()->outPutCompressionEnabled())
		$modSettings['enableCompressedOutput'] = 0;
	else
	{
		@ob_end_clean();
		ob_start('ob_gzhandler');
	}
}

// Register error & exception handlers.
new ElkArte\Errors\ErrorHandler;

// Start the session. (assuming it hasn't already been.)
loadSession();

// Restore post data if we are revalidating OpenID.
if (isset($_GET['openid_restore_post']) && !empty($_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]['post']) && empty($_POST))
{
	$_POST = $_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]['post'];
	unset($_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]);
}

// Pre-dispatch
elk_main();

// Call obExit specially; we're coming from the main area ;).
obExit(null, null, true);

/**
 * The main dispatcher.
 * This delegates to each area.
 */
function elk_main()
{
	global $modSettings, $context;

	// A safer way to work with our form globals
	// @todo Use a DIC
	$_req = HttpReq::instance();

	// What shall we do?
	$dispatcher = new Site_Dispatcher($_req);

	if ($dispatcher->needSecurity())
	{
		// We should set our security headers now.
		frameOptionsHeader();
		securityOptionsHeader();

		// Load the user's cookie (or set as guest) and load their settings.
		loadUserSettings();

		// Load the current board's information.
		loadBoard();

		// Load the current user's permissions.
		loadPermissions();

		// Load the current theme.  (note that ?theme=1 will also work, may be used for guest theming.)
		if ($dispatcher->needTheme())
		{
			loadTheme();

			// Load BadBehavior before we go much further
			loadBadBehavior();

			// The parser is not a DIC just yet
			loadBBCParsers();
		}
		// Otherwise don't require the entire theme to be loaded.
		else
		{
			detectBrowser();
		}

		// Check if the user should be disallowed access.
		is_not_banned();

		// Do some logging, unless this is an attachment, avatar, toggle of editor buttons, theme option, XML feed etc.
		if ($dispatcher->trackStats())
		{
			// I see you!
			writeLog();

			// Track forum statistics and hits...?
			if (!empty($modSettings['hitStats']))
				trackStats(array('hits' => '+'));
		}

		// Show where we came from, and go
		$context['site_action'] = $dispatcher->site_action();
	}

	$dispatcher->dispatch();
}
