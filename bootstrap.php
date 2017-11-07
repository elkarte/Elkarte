<?php

/**
 * Initialize the ElkArte environment.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

// Bootstrap only once.
if (defined('ELKBOOT'))
{
	return true;
}

$time_start = microtime(true);

const ELKBOOT = 1;

// Shortcut for the browser cache stale
const CACHE_STALE = '?R110';

// We're going to want a few globals... these are all set later.
global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
global $boardurl, $webmaster_email, $cookiename;
global $db_type, $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send;
global $modSettings, $context, $sc, $user_info, $topic, $board, $txt;
global $ssi_db_user, $scripturl, $ssi_db_passwd, $db_passwd;
global $boarddir, $sourcedir;

// Report errors but not depreciated ones
$ssi_error_reporting = error_reporting(E_ALL | E_STRICT & ~8192);

// Directional only script time usage for display
// getrusage is missing in php < 7 on Windows
if (function_exists('getrusage'))
{
	$rusage_start = getrusage();
}
else
{
	$rusage_start = array();
}

$db_show_debug = false;

// We don't need no globals. (a bug in "old" versions of PHP)
foreach (array('db_character_set', 'cachedir') as $variable)
{
	if (isset($GLOBALS[$variable]))
	{
		unset($GLOBALS[$variable], $GLOBALS[$variable]);
	}
}

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
		if (file_exists($settings_loc) && empty($_SESSION['installing']))
		{
			$redirec_file = 'upgrade.php';
		}
		else
		{
			$redirec_file = 'install.php';
		}

		header('Location: http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on' ? 's' : '') . '://' . (empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] === '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST']) . (strtr(dirname($_SERVER['PHP_SELF']), '\\', '/') == '/' ? '' : strtr(dirname($_SERVER['PHP_SELF']), '\\', '/')) . '/install/' . $redirec_file);
		die();
	}
}
else
{
	require_once($settings_loc);
}

// Make sure the paths are correct... at least try to fix them.
if (!file_exists($boarddir) && file_exists(__DIR__ . '/agreement.txt'))
{
	$boarddir = __DIR__;
}
if (!file_exists($sourcedir . '/SiteDispatcher.class.php') && file_exists($boarddir . '/sources'))
{
	$sourcedir = $boarddir . '/sources';
}

// Check that directories which didn't exist in past releases are initialized.
if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
{
	$cachedir = $boarddir . '/cache';
}

if ((empty($extdir) || !file_exists($extdir)) && file_exists($sourcedir . '/ext'))
{
	$extdir = $sourcedir . '/ext';
}

if ((empty($languagedir) || !file_exists($languagedir)) && file_exists($boarddir . '/themes/default/languages'))
{
	$languagedir = $boarddir . '/themes/default/languages';
}

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
$autoloder = Elk_Autoloader::instance();
$autoloder->setupAutoloader(array(SOURCEDIR, SUBSDIR, CONTROLLERDIR, ADMINDIR, ADDONSDIR));
$autoloder->register(SOURCEDIR, '\\ElkArte');
$autoloder->register(SOURCEDIR . '/subs/BBC', '\\BBC');

/**
 * Set this to one of three values depending on what you want to happen in the case of a fatal error.
 *
 *  - false: Default, will just load the error sub template and die - not putting any theme layers around it.
 *  - true: Will load the error sub template AND put the template layers around it (Not useful if on total custom
 * pages).
 *  - string: Name of a callback function to call in the event of an error to allow you to define your own methods.
 * Will die after function returns.
 */
$ssi_on_error_method = false;

// Don't do john didley if the forum's been shut down completely.
if ($maintenance == 2 && (!isset($ssi_maintenance_off) || $ssi_maintenance_off !== true))
{
	die($mmessage);
}

if ($db_show_debug === true)
{
	Debug::instance()->rusage('start', $rusage_start);
}

// Forum in extended maintenance mode? Our trip ends here with a bland message.
if (!empty($maintenance) && $maintenance == 2)
{
	Errors::instance()->display_maintenance_message();
}

// Clean the request.
cleanRequest();

// Initiate the database connection and define some database functions to use.
loadDatabase();

// It's time for settings loaded from the database.
reloadSettings();

// Our good ole' contextual array, which will hold everything
if (!isset($context))
{
	$context = array();
}

// Seed the random generator.
elk_seed_generator();

// Check on any hacking attempts.
if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
{
	die('No access...');
}
elseif (isset($_REQUEST['ssi_theme']) && (int) $_REQUEST['ssi_theme'] == (int) $ssi_theme)
{
	die('No access...');
}
elseif (isset($_COOKIE['ssi_theme']) && (int) $_COOKIE['ssi_theme'] == (int) $ssi_theme)
{
	die('No access...');
}
elseif (isset($_REQUEST['ssi_layers'], $ssi_layers) && (@get_magic_quotes_gpc() ? stripslashes($_REQUEST['ssi_layers']) : $_REQUEST['ssi_layers']) == $ssi_layers)
{
	die('No access...');
}

if (isset($_REQUEST['context']))
{
	die('No access...');
}

// Gzip output? (because it must be boolean and true, this can't be hacked.)
if (isset($ssi_gzip) && $ssi_gzip === true && detectServer()->outPutCompressionEnabled())
{
	ob_start('ob_gzhandler');
}
else
{
	$modSettings['enableCompressedOutput'] = '0';
}

// Primarily, this is to fix the URLs...
ob_start('ob_sessrewrite');

// Start the session... known to scramble SSI includes in cases...
if (!headers_sent())
{
	loadSession();
}
else
{
	if (isset($_COOKIE[session_name()]) || isset($_REQUEST[session_name()]))
	{
		// Make a stab at it, but ignore the E_WARNINGs generated because we can't send headers.
		$temp = error_reporting(error_reporting() & !E_WARNING);
		loadSession();
		error_reporting($temp);
	}

	if (!isset($_SESSION['session_value']))
	{
		$tokenizer = new Token_Hash();
		$_SESSION['session_value'] = $tokenizer->generate_hash(32, session_id());
		$_SESSION['session_var'] = substr(preg_replace('~^\d+~', '', $tokenizer->generate_hash(16, session_id())), 0, rand(7, 12));
	}

	$sc = $_SESSION['session_value'];
	// This is here only to avoid session errors in PHP7
	// microtime effectively forces the replacing of the session in the db each
	// time the page is loaded
	$_SESSION['mictrotime'] = microtime();
}

// Get rid of $board and $topic... do stuff loadBoard would do.
unset($board, $topic);
$user_info['is_mod'] = false;
$context['user']['is_mod'] = &$user_info['is_mod'];
$context['linktree'] = array();

// Load the user and their cookie, as well as their settings.
loadUserSettings();

// Load the current user's permissions....
loadPermissions();

// Load the current or SSI theme. (just use $ssi_theme = id_theme;)
new ElkArte\Themes\ThemeLoader(isset($ssi_theme) ? (int) $ssi_theme : 0);

// Load BadBehavior functions
loadBadBehavior();

// @todo: probably not the best place, but somewhere it should be set...
if (!headers_sent())
{
	header('Content-Type: text/html; charset=UTF-8');
}

// Take care of any banning that needs to be done.
if (isset($_REQUEST['ssi_ban']) || (isset($ssi_ban) && $ssi_ban === true))
{
	is_not_banned();
}

// Do we allow guests in here?
if (empty($ssi_guest_access) && empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && basename($_SERVER['PHP_SELF']) != 'SSI.php')
{
	$controller = new Auth_Controller();
	$controller->action_kickguest();
	obExit(null, true);
}

// Load the stuff like the menu bar, etc.
if (isset($ssi_layers))
{
	$template_layers = Template_Layers::instance();
	$template_layers->removeAll();
	foreach ($ssi_layers as $layer)
	{
		$template_layers->addBegin($layer);
	}
	template_header();
}
else
{
	if (!empty($modSettings['front_page']) && is_callable(array($modSettings['front_page'], 'frontPageHook')))
	{
		$modSettings['default_forum_action'] = '?action=forum;';
	}
	else
	{
		$modSettings['default_forum_action'] = '';
	}

	setupThemeContext();
}

// We need to set up user agent, and make more checks on the request
$req = request();

// Make sure they didn't muss around with the settings... but only if it's not cli.
if (isset($_SERVER['REMOTE_ADDR']) && session_id() == '')
{
	trigger_error($txt['ssi_session_broken'], E_USER_NOTICE);
}

// Without visiting the forum this session variable might not be set on submit.
if (!isset($_SESSION['USER_AGENT']) && (!isset($_GET['ssi_function']) || $_GET['ssi_function'] !== 'pollVote'))
{
	$_SESSION['USER_AGENT'] = $req->user_agent();
}
