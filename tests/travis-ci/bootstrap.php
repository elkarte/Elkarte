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
 * @version 1.1
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

// Done to allow the option to runInSeparateProcess for phpunit
// as done in Auth.subs.Test
if (!defined('ELK'))
{
	DEFINE('ELK', '1');
	DEFINE('CACHE_STALE', '?R11B2');

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
	DEFINE('ADDONSDIR', $sourcedir . '/addons');
}
else
{
	require_once('/var/www/Settings.php');
}

// A few files we cannot live without and will not be autoload
require_once(SOURCEDIR . '/QueryString.php');
require_once(SOURCEDIR . '/Session.php');
require_once(SOURCEDIR . '/Subs.php');
require_once(SOURCEDIR . '/Logging.php');
require_once(SOURCEDIR . '/Load.php');
require_once(SOURCEDIR . '/Security.php');
require_once(SUBSDIR . '/Cache.subs.php');

// Get the autoloader rolling
$autoloder = Elk_Autoloader::instance();
$autoloder->setupAutoloader(array(SOURCEDIR, SUBSDIR, CONTROLLERDIR, ADMINDIR, ADDONSDIR));
$autoloder->register(SOURCEDIR, '\\ElkArte');

// Used by the test, add others as needed or ...
$context = array();
$context['forum_name'] = $mbname;
$context['forum_name_html_safe'] = $context['forum_name'];

// Just like we are starting, almost
cleanRequest();
loadDatabase();
Hooks::init(database(), Debug::instance());
reloadSettings();
elk_seed_generator();
loadSession();
loadUserSettings();
loadBoard();
loadPermissions();
new ElkArte\Themes\ThemeLoader();

// It should be added to the install and upgrade scripts.
// But since the converters need to be updated also. This is easier.
updateSettings(array(
	'attachmentUploadDir' => serialize(array(1 => $modSettings['attachmentUploadDir'])),
	'currentAttachmentUploadDir' => 1,
));

// Basic language is good to have for functional tests
theme()->getTemplates()->loadLanguageFile('index+Errors');

// If we are running functional tests as well
if (defined('PHPUNIT_SELENIUM'))
{
	require_once('/var/www/tests/sources/controllers/ElkArteWebTest.php');
	PHPUnit_Extensions_Selenium2TestCase::shareSession(true);
}

file_put_contents('/var/www/bootstrapcompleted.lock', '1');
