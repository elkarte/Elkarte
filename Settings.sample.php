<?php

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
 * @var string
 */
$mbname = 'My Community';

/**
 * The default language file set for the forum.
 * @var string
 */
$language = 'English';

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
$cookiename = 'ElkArteCookie20';

########## Database Info ##########
/**
 * The database type. Options: mysqli, postgresql
 * @global string $db_type
 */
$db_type = 'mysqli';

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
 * A prefix to put in front of your table names. This helps to prevent conflicts
 * @global string $db_prefix
 */
$db_prefix = 'elkarte_';

/**
 * Use a persistent database connection
 * @global int|bool $db_persist
 */
$db_persist = 0;

/**
 * @global int|bool $db_error_send
 */
$db_error_send = 0;

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
 * The character set to use for the database connection, should be utf8.
 * @global string $db_character_set
 */
$db_character_set = 'utf8';

########## Cache Info ##########
/**
 * Select a cache system. You want to leave this up to the cache area of the admin panel for
 * proper detection of apc, redis, memcache, or filesystem-based
 * @global string $cache_accelerator
 */
$cache_accelerator = '';

/**
 * The level at which you would like to cache. Between 0 (off) through 3 (cache a lot).
 * @global int $cache_enable
 */
$cache_enable = 0;

/**
 * This is only used for memcache / redis. Should be a string of 'server:port,server:port'
 * @global string $cache_servers
 */
$cache_servers = '';

########## Directories/Files ##########
/**
 * This is for 'filebased' cache files. It is the path to the cache directory.
 * @global string $cachedir
 */
$cachedir = __DIR__ . '/cache';

/**
 * The absolute path to the forum's folder. (not just '.'!)
 * @global string $boarddir
 */
$boarddir =  __DIR__;

/**
 * Path to the sources directory.
 * @global string $sourcedir
 */
$sourcedir =  __DIR__ . '/sources';

/**
 * Path to the external resource directory.
 * @global string $extdir
 */
$extdir =  __DIR__ . '/sources/ext';

/**
 * Path to the language directory.
 * @global string $languagedir
 */
$languagedir = __DIR__ . '/sources/ElkArte/Languages';

########## Misc Settings ##########
$url_format = 'standard';
$db_show_debug = false;
$install_time = '0';
