<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0.10
 *
 */

########## Maintenance ##########
/**
 * The maintenance "mode"
 * Set to 1 to enable Maintenance Mode, 2 to make the forum untouchable. (you'll have to make it 0 again manually!)
 * 0 is default and disables maintenance mode.
 * @var int 0, 1, 2
 * @global int $maintenance
 */
$maintenance = 0;
/**
 * Title for the Maintenance Mode message.
 * @var string
 * @global int $mtitle
 */
$mtitle = 'Maintenance Mode';
/**
 * Description of why the forum is in maintenance mode.
 * @var string
 * @global string $mmessage
 */
$mmessage = 'Okay faithful users...we\'re attempting to restore an older backup of the database...news will be posted once we\'re back!';

########## Forum Info ##########
/**
 * The name of your forum.
 * @var string
 */
$mbname = 'My Community';
/**
 * The default language file set for the forum.
 * @var string
 */
$language = 'english';
/**
 * URL to your forum's folder. (without the trailing /!)
 * @var string
 */
$boardurl = 'http://127.0.0.1/elkarte';
/**
 * Email address to send emails from. (like noreply@yourdomain.com.)
 * @var string
 */
$webmaster_email = 'noreply@myserver.com';
/**
 * Name of the cookie to set for authentication.
 * @var string
 */
$cookiename = 'ElkArteCookie11';

########## Database Info ##########
/**
 * The database type
 * Default options: mysql, sqlite, postgresql
 * @var string
 */
$db_type = 'mysql';
/**
 * The server to connect to (or a Unix socket)
 * @var string
 */
$db_server = 'localhost';
/**
 * The port for the database server
 * @var string
 */
$db_port = '';
/**
 * The database name
 * @var string
 */
$db_name = 'elkarte';
/**
 * Database username
 * @var string
 */
$db_user = 'root';
/**
 * Database password
 * @var string
 */
$db_passwd = '';
/**
 * Database user for when connecting with SSI
 * @var string
 */
$ssi_db_user = '';
/**
 * Database password for when connecting with SSI
 * @var string
 */
$ssi_db_passwd = '';
/**
 * A prefix to put in front of your table names.
 * This helps to prevent conflicts
 * @var string
 */
$db_prefix = 'elkarte_';
/**
 * Use a persistent database connection
 * @var int|bool
 */
$db_persist = 0;
/**
 *
 * @var int|bool
 */
$db_error_send = 0;

########## Cache Info ##########
/**
 * Select a cache system. You want to leave this up to the cache area of the admin panel for
 * proper detection of apc, eaccelerator, memcache, mmcache, output_cache, xcache or filesystem-based
 * (you can add more with a mod).
 * @var string
 */
$cache_accelerator = '';
/**
 * Cache accelerator userid, needed by some engines in order to clear the cache
 * @var string
 */
$cache_uid = '';
/**
 * Cache accelerator password for when connecting to clear the cache
 * @var string
 */
$cache_password = '';
/**
 * The level at which you would like to cache. Between 0 (off) through 3 (cache a lot).
 * @var int
 */
$cache_enable = 0;
/**
 * This is only used for memcache / memcached. Should be a string of 'server:port,server:port'
 * @var array
 */
$cache_memcached = '';
/**
 * This is only for the 'filebased' cache system. It is the path to the cache directory.
 * It is also recommended that you place this in /tmp/ if you are going to use this.
 * @var string
 */
$cachedir = dirname(__FILE__) . '/cache';

########## Directories/Files ##########
# Note: These directories do not have to be changed unless you move things.
/**
 * The absolute path to the forum's folder. (not just '.'!)
 * @var string
 */
$boarddir = dirname(__FILE__);
/**
 * Path to the sources directory.
 * @var string
 */
$sourcedir = dirname(__FILE__) . '/sources';
/**
 * Path to the external resources directory.
 * @var string
 */
$extdir = dirname(__FILE__) . '/sources/ext';
/**
 * Path to the languages directory.
 * @var string
 */
$languagedir = dirname(__FILE__) . '/themes/default/languages';

########## Error-Catching ##########
# Note: You shouldn't touch these settings.
if (file_exists(dirname(__FILE__) . '/db_last_error.php'))
	include(dirname(__FILE__) . '/db_last_error.php');

if (!isset($db_last_error))
{
	// File does not exist so lets try to create it
	file_put_contents(dirname(__FILE__) . '/db_last_error.php', '<' . '?' . "php\n" . '$db_last_error = 0;');
	$db_last_error = 0;
}

if (file_exists(dirname(__FILE__) . '/install.php'))
{
	header('Location: http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 's' : '') . '://' . (empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST']) . (strtr(dirname($_SERVER['PHP_SELF']), '\\', '/') == '/' ? '' : strtr(dirname($_SERVER['PHP_SELF']), '\\', '/')) . '/install.php'); exit;
}
