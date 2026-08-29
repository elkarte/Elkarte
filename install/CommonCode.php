<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

use ElkArte\Database\QueryInterface;
use ElkArte\ext\Composer\Autoload\ClassLoader;
use ElkArte\Helper\FileFunctions;

/**
 * Grabs all the files with db definitions and loads them.
 * That's the easy way, in the future we can make it as complex as possible. :P
 */
function load_possible_databases($type = null)
{
	$files = new \GlobIterator(__DIR__ . '/Db-check-*.php', \FilesystemIterator::SKIP_DOTS);

	foreach ($files as $file)
	{
		if ($type !== null)
		{
			if (strtolower($file->getPathname()) === strtolower(__DIR__ . '/Db-check-' . $type . '.php'))
			{
				require($file);
			}
		}
		else
		{
			require($file->getPathname());
		}
	}
}

/**
 * This handy function loads the database and some settings and the like.
 *
 * @param bool $force
 * @return QueryInterface
 */
function load_database($force = false)
{
	// These globals are needed
	global $db_prefix, $db_connection, $db_type, $db_name, $db_user, $db_persist, $db_server, $db_passwd, $db_port;

	// Connect the database.
	if (empty($db_connection) || $force === true)
	{
		if (empty($db_prefix) || $force === true)
		{
			// Need this to check whether we need the database password.
			require(TMP_BOARDDIR . '/Settings.php');
			definePaths();
		}

		if (!defined('SOURCEDIR'))
		{
			define('SOURCEDIR', TMP_BOARDDIR . '/sources');
		}

		if (!defined('ELK'))
		{
			define('ELK', 1);
		}

		if (empty($db_connection))
		{
			require_once(EXTDIR . '/ClassLoader.php');

			$loader = new ClassLoader();
			$loader->setPsr4('ElkArte\\ext\\', EXTDIR);
			$loader->setPsr4('ElkArte\\', SOURCEDIR . '/ElkArte');
			$loader->setPsr4('BBC\\', SOURCEDIR . '/ElkArte/BBC');
			// Not needed, but for consistency
			$loader->setPsr4('Addons\\', BOARDDIR . '/Addons');
			$loader->setPsr4('Wikimedia\\Minify\\', EXTDIR . '/Wikimedia/Minify');
			$loader->setPsr4('Michelf\\', EXTDIR . '/Michelf');
			$loader->register();

			require_once(SOURCEDIR . '/database/Database.subs.php');
			require_once(TMP_BOARDDIR . '/install/DatabaseCode.php');
		}

		$db_connection = database(false, true);
	}

	return database();
}

/**
 * Test if our database connection works
 *
 * @return false|mixed
 */
function test_db_connection()
{
	global $db_persist, $db_server, $db_user, $db_passwd, $db_port, $db_type, $db_name, $db_prefix, $mysql_set_mode;

	$db_options = [
		'persist' => $db_persist,
		'select_db' => false,
		'port' => $db_port,
		'mysql_set_mode' => (bool) ($mysql_set_mode ?? false)
	];
	$type = strtolower($db_type);
	$type = $type === 'mysql' ? 'mysqli' : $type;

	/** @var \ElkArte\Database\ $class */
	$class = '\\ElkArte\\Database\\' . ucfirst($type) . '\\Connection';
	try
	{
		return $class::initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options);
	}
	catch (\Exception)
	{
		return false;
	}
}

/**
 * The normal DbTable disallows to delete/create "core" tables
 */
function db_table_install()
{
	global $db_type, $db_prefix;

	$db = load_database();

	return call_user_func(['DbTable_' . $db_type . '_Install', 'db_table'], $db, $db_prefix);
}

/**
 * Logs db errors as they happen
 */
function updateLastError()
{
	// Clear out the db_last_error file
	file_put_contents(TMP_BOARDDIR . '/db_last_error.txt', '0');
}

/**
 * Checks the servers database version against our requirements
 */
function db_version_check($db = null)
{
	global $db_type;

	if ($db === null)
	{
		$db = load_database();
	}

	$current_version = $db->server_version();
	$current_version = preg_replace('~\-.+?$~', '', $current_version);

	return version_compare($GLOBALS['databases'][$db_type]['version'], $current_version, '<=');
}

/**
 * Delete the installer and its additional files.
 * Called by ?delete
 */
function action_deleteInstaller()
{
	global $package_ftp;

	definePaths();
	define('ELK', 'SSI');
	require_once(SUBSDIR . '/Package.subs.php');

	if (isset($_SESSION['installer_temp_ftp']))
	{
		$_SESSION['pack_ftp']['root'] = BOARDDIR;
		$package_ftp = new Ftp_Connection($_SESSION['installer_temp_ftp']['server'], $_SESSION['installer_temp_ftp']['port'], $_SESSION['installer_temp_ftp']['username'], $_SESSION['installer_temp_ftp']['password']);
		$package_ftp->chdir($_SESSION['installer_temp_ftp']['path']);
	}

	deltree(__DIR__);

	if (isset($_SESSION['installer_temp_ftp']))
	{
		$package_ftp->close();

		unset($_SESSION['installer_temp_ftp']);
	}

	// Now just redirect to a blank.png...
	$secure = false;

	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
	{
		$secure = true;
	}
	elseif ((!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
		|| (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on'))
	{
		$secure = true;
	}

	header('location: http' . ($secure ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT']) . dirname($_SERVER['PHP_SELF']) . '/../themes/default/images/blank.png');
	exit;
}

/**
 * Removes flagged settings
 * Updates existing settings with new values if passed
 * Appends new settings as passed in $config_vars to the array
 * Writes out a new Settings.php file, overwriting any that may have existed
 *
 * @param array $config_vars
 * @param array $settingsArray
 */
function saveFileSettings($config_vars, $settingsArray)
{
	definePaths();
	require_once(SOURCEDIR . '/Subs.php');

	if (count($settingsArray) === 1)
	{
		$settingsArray = preg_split('~[\r\n]~', $settingsArray[0]);
	}

	// Step line by line and see whats changing
	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		if (trim($settingsArray[$i]) === '?>')
		{
			$settingsArray[$i] = '';
		}

		// Don't trim or bother with it if it's not a variable.
		if (!str_starts_with($settingsArray[$i], '$'))
		{
			continue;
		}

		$settingsArray[$i] = trim($settingsArray[$i]) . "\n";

		// Update as requested
		foreach ($config_vars as $var => $val)
		{
			if (isset($settingsArray[$i]) &&
				strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) === 0)
			{
				if ($val === '#remove#')
				{
					unset($settingsArray[$i]);
					continue;
				}

				$comment = strstr(substr(un_htmlspecialchars($settingsArray[$i]), strpos(un_htmlspecialchars($settingsArray[$i]), ';')), '#');
				$settingsArray[$i] = '$' . $var . " = '" . $val . "';" . ($comment === '' ? '' : "\t\t" . rtrim($comment)) . "\n";

				unset($config_vars[$var]);
			}
		}
	}

	// Now add in any new vars we were passed
	if (!empty($config_vars))
	{
		$settingsArray[$i++] = '';
		foreach ($config_vars as $var => $val)
		{
			if ($val !== '#remove#')
			{
				$settingsArray[$i++] = "\n" . '$' . $var . " = '" . $val . "';" . "\n";
			}
		}
	}

	// Write out the new settings.php file
	if (trim($settingsArray[0]) !== '<?php')
	{
		array_unshift($settingsArray,'<?php' . "\n" );
	}

	$result = file_put_contents(BOARDDIR . '/Settings.php', implode('', $settingsArray), LOCK_EX);

	// Don't let the OPcache fool us, we need the new file
	if ($result !== false && extension_loaded('Zend OPcache') && ini_get('opcache.enable') &&
		((ini_get('opcache.restrict_api') === '' || stripos(BOARDDIR, (string) ini_get('opcache.restrict_api')) !== 0)))
	{
		opcache_invalidate(TMP_BOARDDIR . '/Settings.php', true);
	}

	clearstatcache(true, TMP_BOARDDIR . '/Settings.php');

	return $result !== false;
}

/**
 * Check files are writable - make them writable if necessary...
 *
 * @param array $files
 */
function makeFilesWritable(&$files)
{
	global $upcontext;

	if (empty($files))
	{
		return true;
	}

	foreach ($files as $k => $file)
	{
		if (FileFunctions::instance()->isWritable($file))
		{
			unset($files[$k]);
			continue;
		}

		if (FileFunctions::instance()->chmod($file))
		{
			unset($files[$k]);
		}
	}

	if (empty($files) || !isset($_SERVER))
	{
		return true;
	}

	// What still needs to be done?
	$upcontext['chmod']['files'] = $files;

	// If it's windows it's a mess...
	if (substr(__FILE__, 1, 2) === ':\\')
	{
		$upcontext['chmod']['ftp_error'] = 'total_mess';

		return false;
	}

	// We're going to have to use... FTP!
	// Load any session data we might have...
	if (!isset($_POST['ftp_username']) && isset($_SESSION['installer_temp_ftp']))
	{
		$upcontext['chmod']['server'] = $_SESSION['installer_temp_ftp']['server'];
		$upcontext['chmod']['port'] = $_SESSION['installer_temp_ftp']['port'];
		$upcontext['chmod']['username'] = $_SESSION['installer_temp_ftp']['username'];
		$upcontext['chmod']['password'] = $_SESSION['installer_temp_ftp']['password'];
		$upcontext['chmod']['path'] = $_SESSION['installer_temp_ftp']['path'];
	}
	// Or have we submitted?
	elseif (isset($_POST['ftp_username']))
	{
		$upcontext['chmod']['server'] = $_POST['ftp_server'];
		$upcontext['chmod']['port'] = $_POST['ftp_port'];
		$upcontext['chmod']['username'] = $_POST['ftp_username'];
		$upcontext['chmod']['password'] = $_POST['ftp_password'];
		$upcontext['chmod']['path'] = $_POST['ftp_path'];
	}

	if (isset($upcontext['chmod']['username']))
	{
		$ftp = new Ftp_Connection($upcontext['chmod']['server'], $upcontext['chmod']['port'], $upcontext['chmod']['username'], $upcontext['chmod']['password']);

		// Try it without /home/abc just in case they messed up.
		if (($ftp->error === false) && !$ftp->chdir($upcontext['chmod']['path']))
		{
			$upcontext['chmod']['ftp_error'] = $ftp->last_message;
			$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $upcontext['chmod']['path']));
		}
	}

	if (!isset($ftp) || $ftp->error !== false)
	{
		if (!isset($ftp))
		{
			$ftp = new Ftp_Connection(null);
		}
		// Save the error, so we can mess with listing...
		elseif ($ftp->error !== false && !isset($upcontext['chmod']['ftp_error']))
		{
			$upcontext['chmod']['ftp_error'] = $ftp->last_message ?? '';
		}

		[$username, $detect_path, $found_path] = $ftp->detect_path(TMP_BOARDDIR);

		if ($found_path || !isset($upcontext['chmod']['path']))
		{
			$upcontext['chmod']['path'] = $detect_path;
		}

		if (!isset($upcontext['chmod']['username']))
		{
			$upcontext['chmod']['username'] = $username;
		}

		return false;
	}

	// We want to do a relative path for FTP.
	if (!in_array($upcontext['chmod']['path'], ['', '/'], true))
	{
		$ftp_root = strtr(BOARDDIR, [$upcontext['chmod']['path'] => '']);
		if (str_ends_with($ftp_root, '/') && $upcontext['chmod']['path'][0] === '/')
		{
			$ftp_root = substr($ftp_root, 0, -1);
		}
	}
	else
	{
		$ftp_root = BOARDDIR;
	}

	// Save the info for next time!
	$_SESSION['installer_temp_ftp'] = [
		'server' => $upcontext['chmod']['server'],
		'port' => $upcontext['chmod']['port'],
		'username' => $upcontext['chmod']['username'],
		'password' => $upcontext['chmod']['password'],
		'path' => $upcontext['chmod']['path'],
		'root' => $ftp_root,
	];

	foreach ($files as $k => $file)
	{
		if (!is_writable($file))
		{
			$ftp->chmod($file, 0755);
		}

		if (!is_writable($file))
		{
			$ftp->chmod($file, 0777);
		}

		// Assuming that didn't work calculate the path without the boarddir.
		if (!is_writable($file) && str_starts_with($file, (string) BOARDDIR))
		{
			$ftp_file = strtr($file, [$_SESSION['installer_temp_ftp']['root'] => '']);
			$ftp->chmod($ftp_file, 0755);
			if (!is_writable($file))
			{
				$ftp->chmod($ftp_file, 0777);
			}

			// Sometimes an extra slash can help...
			$ftp_file = '/' . $ftp_file;
			if (!is_writable($file))
			{
				$ftp->chmod($ftp_file, 0755);
			}

			if (!is_writable($file))
			{
				$ftp->chmod($ftp_file, 0777);
			}
		}

		if (is_writable($file))
		{
			unset($files[$k]);
		}
	}

	$ftp->close();

	// What remains?
	$upcontext['chmod']['files'] = $files;

	return empty($files);
}

/**
 * Identify forum paths, set forum use constants.
 */
function definePaths()
{
	global $boarddir, $cachedir, $extdir, $languagedir, $sourcedir;

	// Make sure the paths are correct... at least try to fix them.
	if (!file_exists($boarddir) && file_exists(TMP_BOARDDIR . '/bootstrap.php'))
	{
		$boarddir = TMP_BOARDDIR;
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

	if ((empty($languagedir) || !file_exists($languagedir)) && file_exists($sourcedir . '/ElkArte/Languages'))
	{
		$languagedir = $sourcedir . '/ElkArte/Languages';
	}

	if (!defined('BOARDDIR'))
	{
		DEFINE('BOARDDIR', $boarddir);
	}

	if (!defined('CACHEDIR'))
	{
		DEFINE('CACHEDIR', $cachedir);
	}

	if (!defined('EXTDIR'))
	{
		DEFINE('EXTDIR', $extdir);
	}

	if (!defined('LANGUAGEDIR'))
	{
		DEFINE('LANGUAGEDIR', $languagedir);
	}

	if (!defined('ADDONSDIR'))
	{
		DEFINE('ADDONSDIR', $boarddir . '/Addons');
	}

	if (!defined('SOURCEDIR'))
	{
		DEFINE('SOURCEDIR', $sourcedir);
	}

	if (!defined('ADMINDIR'))
	{
		DEFINE('ADMINDIR', $sourcedir . '/admin');
	}

	if (!defined('CONTROLLERDIR'))
	{
		DEFINE('CONTROLLERDIR', $sourcedir . '/controllers');
	}

	if (!defined('SUBSDIR'))
	{
		DEFINE('SUBSDIR', $sourcedir . '/subs');
	}

	if (!defined('ELKARTEDIR'))
	{
		DEFINE('ELKARTEDIR', $sourcedir . '/ElkArte');
	}
}
