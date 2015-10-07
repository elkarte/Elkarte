<?php

/**
 * Initialize the ElkArte environment.
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
 * @version 1.1 dev
 *
 */

// Don't do anything if ElkArte is already loaded.
if (defined('ELK'))
	return true;

define('ELK', 'SSI');

// Shortcut for the browser cache stale
define('CACHE_STALE', '?R11');

// We're going to want a few globals... these are all set later.
global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
global $boardurl, $webmaster_email, $cookiename;
global $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send;
global $modSettings, $context, $sc, $user_info, $topic, $board, $txt;
global $ssi_db_user, $scripturl, $ssi_db_passwd, $db_passwd;
global $boarddir, $sourcedir;

$ssi_error_reporting = error_reporting(E_ALL | E_STRICT);

// Directional only script time usage for display
if (function_exists('getrusage'))
	$rusage_start = getrusage();
else
	$rusage_start = array();

$time_start = microtime(true);
$db_show_debug = false;

// We don't need no globals. (a bug in "old" versions of PHP)
foreach (array('db_character_set', 'cachedir') as $variable)
	if (isset($GLOBALS[$variable]))
		unset($GLOBALS[$variable], $GLOBALS[$variable]);

// Get the forum's settings for database and file paths.
require_once(__DIR__ . '/Settings.php');

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

require(SOURCEDIR . '/Autoloader.class.php');
$autoloder = Elk_Autoloader::getInstance();
$autoloder->setupAutoloader(array(SOURCEDIR, SUBSDIR, CONTROLLERDIR, ADMINDIR, ADDONSDIR));
$autoloder->register(SOURCEDIR, '\\ElkArte');

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
	die($mmessage);

if ($db_show_debug === true && isset($rusage_start))
{
	Debug::get()->rusage('start', $rusage_start);
}

// Forum in extended maintenance mode? Our trip ends here with a bland message.
if (!empty($maintenance) && $maintenance == 2)
	display_maintenance_message();

// Clean the request.
cleanRequest();

// Initiate the database connection and define some database functions to use.
loadDatabase();

// It's time for settings loaded from the database.
reloadSettings();

// Our good ole' contextual array, which will hold everything
$context = array();

// Seed the random generator.
elk_seed_generator();

// Check on any hacking attempts.
if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
	die('No access...');
elseif (isset($_REQUEST['ssi_theme']) && (int) $_REQUEST['ssi_theme'] == (int) $ssi_theme)
	die('No access...');
elseif (isset($_COOKIE['ssi_theme']) && (int) $_COOKIE['ssi_theme'] == (int) $ssi_theme)
	die('No access...');
elseif (isset($_REQUEST['ssi_layers'], $ssi_layers) && (@get_magic_quotes_gpc() ? stripslashes($_REQUEST['ssi_layers']) : $_REQUEST['ssi_layers']) == $ssi_layers)
	die('No access...');
if (isset($_REQUEST['context']))
	die('No access...');

// Gzip output? (because it must be boolean and true, this can't be hacked.)
if (isset($ssi_gzip) && $ssi_gzip === true && ini_get('zlib.output_compression') != '1' && ini_get('output_handler') != 'ob_gzhandler' && version_compare(PHP_VERSION, '4.2.0', '>='))
	ob_start('ob_gzhandler');
else
	$modSettings['enableCompressedOutput'] = '0';

// Primarily, this is to fix the URLs...
ob_start('ob_sessrewrite');

// Start the session... known to scramble SSI includes in cases...
if (!headers_sent())
	loadSession();
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
		$_SESSION['session_var'] = substr(md5(mt_rand() . session_id() . mt_rand()), 0, rand(7, 12));
		$_SESSION['session_value'] = md5(session_id() . mt_rand());
	}
	$sc = $_SESSION['session_value'];
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

// Load BadBehavior functions
loadBadBehavior();

// Load the current or SSI theme. (just use $ssi_theme = id_theme;)
loadTheme(isset($ssi_theme) ? (int) $ssi_theme : 0);

// @todo: probably not the best place, but somewhere it should be set...
if (!headers_sent())
	header('Content-Type: text/html; charset=UTF-8');

// Take care of any banning that needs to be done.
if (isset($_REQUEST['ssi_ban']) || (isset($ssi_ban) && $ssi_ban === true))
	is_not_banned();

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
	$template_layers = Template_Layers::getInstance();
	$template_layers->removeAll();
	foreach ($ssi_layers as $layer)
		$template_layers->addBegin($layer);
	template_header();
}
else
	setupThemeContext();

// We need to set up user agent, and make more checks on the request
$req = request();

// Make sure they didn't muss around with the settings... but only if it's not cli.
if (isset($_SERVER['REMOTE_ADDR']) && session_id() == '')
	trigger_error($txt['ssi_session_broken'], E_USER_NOTICE);

// Without visiting the forum this session variable might not be set on submit.
if (!isset($_SESSION['USER_AGENT']) && (!isset($_GET['ssi_function']) || $_GET['ssi_function'] !== 'pollVote'))
	$_SESSION['USER_AGENT'] = $req->user_agent();