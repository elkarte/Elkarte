<?php

########## Maintenance ##########
/**
 * The maintenance "mode"
 * Set to 1 to enable Maintenance Mode, 2 to make the forum untouchable. (you'll have to make it 0 again manually!)
 * 0 is default and disables maintenance mode.
 * @global int $maintenance
 */
$maintenance = 0;

/**
 * Title for the Maintenance Mode message.
 * @global string $mtitle
 */
$mtitle = 'Maintenance Mode';

/**
 * Description of why the forum is in maintenance mode.
 * @global string $mmessage
 */
$mmessage = 'Okay faithful users...we\'re attempting to restore an older backup of the database...news will be posted once we\'re back!';

########## Forum Info ##########
/**
 * The name of your forum.
 * @global string $mbname
 */
$mbname = 'My Community';

/**
 * The default language file set for the forum.
 * @global string $language
 */
$language = 'English';

/**
 * URL to your forum's folder. (without the trailing /!)
 * @global string $boardurl
 */
$boardurl = 'http://127.0.0.1/elkarte';

/**
 * Email address to send emails from. (like noreply@yourdomain.com.)
 * @global string $webmaster_email
 */
$webmaster_email = 'noreply@myserver.com';

/**
 * Name of the cookie to set for authentication.
 * @global string $cookiename
 */
$cookiename = 'ElkArteCookie11';

########## Database Info ##########
/**
 * The database type
 * Default options: mysql, sqlite, postgresql
 * @global string $db_type
 */
$db_type = 'mysql';

/**
 * The server to connect to (or a Unix socket)
 * @global string $db_server
 */
$db_server = 'localhost';

/**
 * The port for the database server
 * @global string $db_port
 */
$db_port = '';

/**
 * The database name
 * @global string $db_name
 */
$db_name = 'elkarte';

/**
 * Database username
 * @global string $db_user
 */
$db_user = 'root';

/**
 * Database password
 * @global string $db_passwd
 */
$db_passwd = '';

/**
 * Database user for when connecting with SSI
 * @global string $ssi_db_user
 */
$ssi_db_user = '';

/**
 * Database password for when connecting with SSI
 * @global string $ssi_db_passwd
 */
$ssi_db_passwd = '';

/**
 * A prefix to put in front of your table names.
 * This helps to prevent conflicts
 * @global string $db_prefix
 */
$db_prefix = 'elkarte_';

/**
 * Use a persistent database connection
 * @global int|bool $db_persist
 */
$db_persist = 0;

/**
 *
 * @global int|bool $db_error_send
 */
$db_error_send = 0;

########## Cache Info ##########
/**
 * Select a cache system. You want to leave this up to the cache area of the admin panel for
 * proper detection of apc, eaccelerator, memcache, mmcache, output_cache or filesystem-based
 * (you can add more with a mod).
 * @global string $cache_accelerator
 */
$cache_accelerator = '';

/**
 * The level at which you would like to cache. Between 0 (off) through 3 (cache a lot).
 * @global int $cache_enable
 */
$cache_enable = 0;

/**
 * This is only used for memcache / memcached / redis. Should be a string of 'server:port,server:port'
 * @global string $cache_servers
 */
$cache_servers = '';

/**
 * This is only for the 'filebased' cache system. It is the path to the cache directory.
 * It is also recommended that you place this in /tmp/ if you are going to use this.
 * @global string $cachedir
 */
$cachedir = __DIR__ . '/cache';

/**
 * Cache accelerator userid / dbname, required by some engines
 * @global string $cache_uid
 */
$cache_uid = '';

/**
 * Cache accelerator password for connecting, required by somme engines
 * @global string $cache_password
 */
$cache_password = '';

########## Directories/Files ##########
# Note: These directories do not have to be changed unless you move things.
/**
 * The absolute path to the forum's folder. (not just '.'!)
 * @global string $boarddir
 */
$boarddir = __DIR__;

/**
 * Path to the sources directory.
 * @global string $sourcedir
 */
$sourcedir = __DIR__ . '/sources';

/**
 * Path to the external support directory.
 * @global string $extdir
 */
$extdir = __DIR__ . '/sources/ext';

/**
 * Path to the languages directory.
 * @global string $languagedir
 */
$languagedir = __DIR__ . '/sources/ElkArte/Languages';

########## Misc Settings ##########
/**
 * How topic/board urls are displayed.
 * @global string $url_format
 */
$url_format = 'standard';

/**
 * If extra debugging information should be shown on the screen. False for production.
 * @global bool $db_show_debug
 */
$db_show_debug = false;

/**
 * The install time of the forum.
 * @global string $install_time
 */
$install_time = '0';
