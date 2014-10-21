<?php

/**
 * This file is called before PHPUnit runs any tests.  Its purpose is
 * to initiate enough functions so the testcases can run with minimal
 * setup needs.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 */

// We're going to need, cough, a few globals
global $mbname, $language;
global $boardurl, $webmaster_email, $cookiename;
global $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send, $db_type, $db_port;
global $modSettings, $context, $user_info, $topic, $board, $txt;
global $scripturl, $db_passwd;
global $boarddir, $sourcedir;
global $ssi_db_user, $ssi_db_passwd;

// If we are running functional tests as well
if (defined('PHPUNIT_SELENIUM'))
	PHPUnit_Extensions_SeleniumTestCase::shareSession(true);

// Done to allow the option to runInSeparateProcess for phpunit
if (!defined('ELK'))
{
	DEFINE('ELK', 1);
	DEFINE('CACHE_STALE', '?R11');

	// Get the forum's settings for database and file paths.
	require_once('/var/www/Settings.php');

	// Set our site "variable" constants
	DEFINE('BOARDDIR', $boarddir);
	DEFINE('CACHEDIR', $cachedir);
	DEFINE('EXTDIR', $extdir);
	DEFINE('LANGUAGEDIR', $boarddir . '/themes/default/languages');
	DEFINE('SOURCEDIR', $sourcedir);
	DEFINE('ADMINDIR', $sourcedir . '/admin');
	DEFINE('CONTROLLERDIR', $sourcedir . '/controllers');
	DEFINE('SUBSDIR', $sourcedir . '/subs');
}
else
	require_once('/var/www/Settings.php');

// A few files we cannot live without and will not be autoload
require_once(SOURCEDIR . '/QueryString.php');
require_once(SOURCEDIR . '/Session.php');
require_once(SOURCEDIR . '/Subs.php');
require_once(SOURCEDIR . '/Errors.php');
require_once(SOURCEDIR . '/Logging.php');
require_once(SOURCEDIR . '/Load.php');
require_once(SOURCEDIR . '/Security.php');

// Get the autoloader rolling
spl_autoload_register('elk_autoloader');

require_once(SUBSDIR . '/Cache.subs.php');

// Used by the test, add others as needed or ...
$context = array();
$context['forum_name'] = $mbname;
$context['forum_name_html_safe'] = $context['forum_name'];

// Just like we are starting, almost
cleanRequest();
loadDatabase();
reloadSettings();
elk_seed_generator();
loadSession();
loadUserSettings();
loadPermissions();

// Basic language is good to have for functional tests
loadLanguage('index+Errors');