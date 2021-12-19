<?php

/**
 * Initialize the ElkArte environment.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 */

use ElkArte\Debug;
use ElkArte\ext\Composer\Autoload\ClassLoader;
use ElkArte\Hooks;
use ElkArte\User;
use ElkArte\TokenHash;
use ElkArte\Errors\Errors;
use ElkArte\MembersList;
use ElkArte\Cache\Cache;
use ElkArte\Themes\ThemeLoader;
use ElkArte\Controller\Auth;
use ElkArte\EventManager;
use BBC\ParserWrapper;

/**
 * Class Bootstrap
 *
 * Takes care of the initial loading and feeding of Elkarte from
 * either SSI or Index
 */
class Bootstrap
{
	/**
	 * What is returned by the function getrusage.
	 *
	 * @var mixed[]
	 */
	protected $rusage_start = [];

	/**
	 * Bootstrap constructor.
	 *
	 * @param bool $standalone
	 *  - true to boot outside elkarte
	 *  - false to bootstrap the main elkarte site.
	 */
	public function __construct($standalone = true)
	{
		// Bootstrap only once.
		if (!defined('ELKBOOT'))
		{
			// We're going to set a few globals
			global $time_start, $ssi_error_reporting, $db_show_debug;

			// Your on the clock
			$time_start = microtime(true);

			// Unless settings.php tells us otherwise
			$db_show_debug = false;

			// Report errors but not depreciated ones
			$ssi_error_reporting = error_reporting(E_ALL & ~E_DEPRECATED);

			// Get the things needed for ALL modes
			$this->bringUpBasics();

			// Going to run from the side entrance and not directly from inside elkarte
			if ($standalone)
			{
				$this->ssi_main();
			}
		}
	}

	/**
	 * Calls the various initialization functions in the needed order
	 */
	public function bringUpBasics()
	{
		$this->setConstants();
		$this->setRusage();
		$this->clearGlobals();
		$this->loadSettingsFile();
		$this->validatePaths();
		$this->loadDependants();
		$this->loadAutoloader();
		$this->checkMaintance();
		$this->setDebug();
		$this->bringUp();
	}

	/**
	 * Set the core constants, you know the ones we often forget to
	 * update on new releases.
	 */
	private function setConstants()
	{
		// First things first, but not necessarily in that order.
		if (!defined('ELK'))
		{
			define('ELK', '1');
		}
		define('ELKBOOT', '1');

		// The software version
		define('FORUM_VERSION', 'ElkArte 2.0 dev');

		// Shortcut for the browser cache stale
		define('CACHE_STALE', '?20dev');
	}

	/**
	 * Get initial resource usage
	 */
	private function setRusage()
	{
		$this->rusage_start = getrusage();
	}

	/**
	 * If they glo, they need to be cleaned.
	 */
	private function clearGlobals()
	{
		// We don't need no globals. (a bug in "old" versions of PHP)
		foreach (array('db_character_set', 'cachedir') as $variable)
		{
			if (isset($GLOBALS[$variable]))
			{
				unset($GLOBALS[$variable], $GLOBALS[$variable]);
			}
		}
	}

	/**
	 * Loads the settings values into the global space
	 */
	private function loadSettingsFile()
	{
		// All those wonderful things found in settings
		global $maintenance, $mtitle, $msubject, $mmessage, $mbname, $language, $boardurl, $webmaster_email;
		global $cookiename, $db_type, $db_server, $db_port, $db_name, $db_user, $db_passwd;
		global $ssi_db_user, $ssi_db_passwd, $db_prefix, $db_persist, $db_error_send, $cache_accelerator;
		global $cache_uid, $cache_password, $cache_enable, $cache_memcached, $db_show_debug, $url_format;
		global $cachedir, $boarddir, $sourcedir, $extdir, $languagedir;

		// Where the Settings.php file is located
		$settings_loc = __DIR__ . '/Settings.php';

		// First thing: if the installation dir exists, just send anybody there
		// The IGNORE_INSTALL_DIR constant is for developers only. Do not add it on production sites
		if (file_exists('install') && (file_exists('install/install.php') || file_exists('install/upgrade.php')))
		{
			if (file_exists($settings_loc))
			{
				require_once($settings_loc);
			}

			if (!defined('IGNORE_INSTALL_DIR'))
			{
				if (file_exists($settings_loc) && empty($_SESSION['installing']))
				{
					$redirec_file = 'upgrade.php';
				}
				else
				{
					$redirec_file = 'install.php';
				}

				$version_running = str_replace('ElkArte ', '', FORUM_VERSION);
				$proto = 'http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on' ? 's' : '');
				$port = empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] === '80' ? '' : ':' . $_SERVER['SERVER_PORT'];
				$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . $port : $_SERVER['HTTP_HOST'];
				$path = strtr(dirname($_SERVER['PHP_SELF']), '\\', '/') == '/' ? '' : strtr(dirname($_SERVER['PHP_SELF']), '\\', '/');

				// Too early to use Headers class etc.
				header('Location:' . $proto . '://' . $host . $path . '/install/' . $redirec_file . '?v=' . $version_running);
				die();
			}
		}
		else
		{
			require_once($settings_loc);
		}
	}

	/**
	 * Validate the paths set in Settings.php, correct as needed and move
	 * them to constants.
	 */
	private function validatePaths()
	{
		global $boarddir, $sourcedir, $cachedir, $extdir, $languagedir;

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
		define('BOARDDIR', $boarddir);
		define('CACHEDIR', $cachedir);
		define('EXTDIR', $extdir);
		define('LANGUAGEDIR', $languagedir);
		define('SOURCEDIR', $sourcedir);
		define('ADMINDIR', $sourcedir . '/ElkArte/AdminController');
		define('CONTROLLERDIR', $sourcedir . '/ElkArte/Controller');
		define('SUBSDIR', $sourcedir . '/subs');
		define('ADDONSDIR', $boarddir . '/addons');
		unset($boarddir, $cachedir, $sourcedir, $languagedir, $extdir);
	}

	/**
	 * We require access to several important files, so load them upfront
	 */
	private function loadDependants()
	{
		// Files we cannot live without.
		require_once(SOURCEDIR . '/QueryString.php');
		require_once(SOURCEDIR . '/Session.php');
		require_once(SOURCEDIR . '/Subs.php');
		require_once(SOURCEDIR . '/Logging.php');
		require_once(SOURCEDIR . '/Load.php');
		require_once(SOURCEDIR . '/Security.php');
		require_once(SUBSDIR . '/Cache.subs.php');
	}

	/**
	 * The autoloader will take care most requests for files
	 */
	private function loadAutoloader()
	{
		require_once(EXTDIR . '/ClassLoader.php');

		$loader = new ClassLoader();
		$loader->setPsr4('ElkArte\\', SOURCEDIR . '/ElkArte');
		$loader->setPsr4('BBC\\', SOURCEDIR . '/ElkArte/BBC');
		$loader->register();
	}

	/**
	 * Check if we are in maintance mode, if so end here.
	 */
	private function checkMaintance()
	{
		global $maintenance, $ssi_maintenance_off;

		// Don't do john didley if the forum's been shut down completely.
		if (!empty($maintenance) && $maintenance == 2 && (!isset($ssi_maintenance_off) || $ssi_maintenance_off !== true))
		{
			Errors::instance()->display_maintenance_message();
		}
	}

	/**
	 * If you like lots of debug information in error messages and below the footer
	 * then set $db_show_debug to true in settings.  Don't do this on a production site.
	 */
	private function setDebug()
	{
		global $db_show_debug;

		// Show lots of debug information below the page, not for production sites
		if ($db_show_debug === true)
		{
			Debug::instance()->rusage('start', $this->rusage_start);
		}
	}

	/**
	 * Time to see what has been requested, by whom and dispatch it to the proper handler
	 */
	private function bringUp()
	{
		global $context;

		// Initiate the database connection and define some database functions to use.
		loadDatabase();

		// Let's set up our shiny new hooks handler.
		Hooks::init(database(), Debug::instance());

		// It's time for settings loaded from the database.
		reloadSettings();

		// Clean the request.
		cleanRequest();

		// Make sure we have the list of members for populating it
		MembersList::init(database(), Cache::instance(), ParserWrapper::instance());

		// Our good ole' contextual array, which will hold everything
		if (empty($context))
		{
			$context = array();
		}
	}

	/**
	 * If you are running SSI standalone, you need to call this function after bootstrap is
	 * initialized.
	 */
	public function ssi_main()
	{
		global $ssi_layers, $ssi_theme, $ssi_gzip, $ssi_ban, $ssi_guest_access;
		global $modSettings, $context, $board, $topic, $txt;

		// Check on any hacking attempts.
		$this->_validRequestCheck();

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
				$tokenizer = new TokenHash();
				$_SESSION['session_value'] = $tokenizer->generate_hash(32, session_id());
				$_SESSION['session_var'] = substr(preg_replace('~^\d+~', '', $tokenizer->generate_hash(16, session_id())), 0, rand(7, 12));
			}

			// This is here only to avoid session errors in PHP7
			// microtime effectively forces the replacing of the session in the db each
			// time the page is loaded
			$_SESSION['mictrotime'] = microtime();
		}

		// Get rid of $board and $topic... do stuff loadBoard would do.
		unset($board, $topic);
		$context['user']['is_mod'] = User::$info->is_mod = false;
		$context['linktree'] = array();

		// Load the user and their cookie, as well as their settings.
		User::load(true);

		// Load the current user's permissions....
		loadPermissions();

		// Load the current or SSI theme. (just use $ssi_theme = id_theme;)
		new ThemeLoader(isset($ssi_theme) ? (int) $ssi_theme : 0);

		// Load BadBehavior functions, but not when running from CLI
		if (!defined('STDIN'))
		{
			loadBadBehavior();
		}

		// Take care of any banning that needs to be done.
		if (isset($_REQUEST['ssi_ban']) || (isset($ssi_ban) && $ssi_ban === true))
		{
			is_not_banned();
		}

		// Do we allow guests in here?
		if (empty($ssi_guest_access) && empty($modSettings['allow_guestAccess']) && User::$info->is_guest && basename($_SERVER['PHP_SELF']) !== 'SSI.php')
		{
			$controller = new Auth(new EventManager());
			$controller->setUser(User::$info);
			$controller->action_kickguest();
			obExit(null, true);
		}

		if (!empty($modSettings['front_page']) && class_exists($modSettings['front_page'])
			&& in_array('frontPageHook', get_class_methods($modSettings['front_page'])))
		{
			$modSettings['default_forum_action'] = ['action' => 'forum'];
		}
		else
		{
			$modSettings['default_forum_action'] = [];
		}

		// Load the stuff like the menu bar, etc.
		if (isset($ssi_layers))
		{
			$template_layers = theme()->getLayers();
			$template_layers->removeAll();
			foreach ($ssi_layers as $layer)
			{
				$template_layers->addBegin($layer);
			}
			template_header();
		}
		else
		{
			setupThemeContext();
		}

		// We need to set up user agent, and make more checks on the request
		$req = request();

		// Make sure they didn't muss around with the settings... but only if it's not cli.
		if (isset($_SERVER['REMOTE_ADDR']) && session_id() === '')
		{
			trigger_error($txt['ssi_session_broken'], E_USER_NOTICE);
		}

		// Without visiting the forum this session variable might not be set on submit.
		if (!isset($_SESSION['USER_AGENT']) && (!isset($_GET['ssi_function']) || $_GET['ssi_function'] !== 'pollVote'))
		{
			$_SESSION['USER_AGENT'] = $req->user_agent();
		}
	}

	/**
	 * Used to ensure SSI requests are valid and not a probing attempt
	 */
	private function _validRequestCheck()
	{
		global $ssi_theme, $ssi_layers;

		// Check on any hacking attempts.
		if (
			isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS'])
			|| isset($_REQUEST['ssi_theme']) && (int) $_REQUEST['ssi_theme'] == (int) $ssi_theme
			|| isset($_COOKIE['ssi_theme']) && (int) $_COOKIE['ssi_theme'] == (int) $ssi_theme
			|| isset($_REQUEST['ssi_layers'], $ssi_layers) && $_REQUEST['ssi_layers'] == $ssi_layers
			|| isset($_REQUEST['context']))
		{
			die('No access...');
		}
	}
}
