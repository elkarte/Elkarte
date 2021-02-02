<?php

/**
 * This file is called before PHPUnit runs any tests.  Its purpose is
 * to initiate enough functions so the testcases can run with minimal
 * setup needs.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use BBC\ParserWrapper;
use ElkArte\Cache\Cache;
use ElkArte\Debug;
use ElkArte\ext\Composer\Autoload\ClassLoader;
use ElkArte\Hooks;
use ElkArte\MembersList;

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
	define('ELK', '1');
	define('CACHE_STALE', '?R20B1');

	// Get the forum's settings for database and file paths.
	require_once('Settings.php');

	// Set our site "variable" constants
	define('BOARDDIR', $boarddir);
	define('CACHEDIR', $cachedir);
	define('EXTDIR', $extdir);
	define('LANGUAGEDIR', $boarddir . '/themes/default/languages');
	define('SOURCEDIR', $sourcedir);
	define('ADMINDIR', $sourcedir . '/admin');
	define('CONTROLLERDIR', $sourcedir . '/controllers');
	define('SUBSDIR', $sourcedir . '/subs');
	define('ADDONSDIR', $sourcedir . '/addons');
	define('PHPUNITBOOTSTRAP', true);
}
else
{
	require_once('Settings.php');
}

// A few files we cannot live without and will not be autoload
require_once(SOURCEDIR . '/QueryString.php');
require_once(SOURCEDIR . '/Session.php');
require_once(SOURCEDIR . '/Subs.php');
require_once(SOURCEDIR . '/Logging.php');
require_once(SOURCEDIR . '/Load.php');
require_once(SOURCEDIR . '/Security.php');
require_once(EXTDIR . '/ClassLoader.php');

$loader = new ClassLoader();
$loader->setPsr4('ElkArte\\', SOURCEDIR . '/ElkArte');
$loader->setPsr4('BBC\\', SOURCEDIR . '/ElkArte/BBC');
$loader->register();

// Used by the test, add others as needed or ...
$context = array();
$context['forum_name'] = $mbname;
$context['forum_name_html_safe'] = $context['forum_name'];

// Just like we are starting, almost
cleanRequest();
loadDatabase();
Hooks::init(database(), Debug::instance());
reloadSettings();
MembersList::init(database(), Cache::instance(),  ParserWrapper::instance());

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

/**
 * This next line is pointless, but without it DatabaseTestExt tests fail in postgre.
 * This 'mentions_member_check' setting looks to be added as part of a scheduled task
 * which is only happening on the postgre install *shrugs* removing it allows the line count
 * to match whats expected.
 */
removeSettings('mentions_member_check');

// Basic language is good to have for functional tests
theme()->getTemplates()->loadLanguageFile('index+Errors');

// If we are running functional tests as well
if (defined('PHPUNIT_SELENIUM'))
{
	require_once('tests/sources/controllers/ElkArteWebTest.php');
	PHPUnit_Extensions_Selenium2TestCase::shareSession(true);
}

file_put_contents('bootstrapcompleted.lock', '1');
