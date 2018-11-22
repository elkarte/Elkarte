<?php

/**
 * Installs the ElkArte db on the travis test server
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

global $txt;

define('BOARDDIR', dirname(__FILE__) . '/../..');
define('CACHEDIR', BOARDDIR . '/cache');
define('ELK', '1');

// Lots of needs
require_once(BOARDDIR . '/sources/Subs.php');
require_once(BOARDDIR . '/sources/subs/Cache.subs.php');
require_once(BOARDDIR . '/sources/database/Database.subs.php');
require_once(BOARDDIR . '/install/installcore.php');

// Composer-Autoloader
require_once(BOARDDIR . '/sources/ext/ClassLoader.php');

$loader = new \ElkArte\ext\Composer\Autoload\ClassLoader();
$loader->setPsr4('ElkArte\\', BOARDDIR . '/sources/ElkArte');
$loader->setPsr4('BBC\\', BOARDDIR . '/sources/ElkArte/BBC');
$loader->register();

/**
 * Used to install ElkArte SQL files to a database scheme
 */
Class Elk_Testing_Setup
{
	protected $_db;
	protected $_install_instance;
	protected $_queries;
	protected $_dbserver;
	protected $_name;
	protected $_user;
	protected $_passwd;

	// Initialized from extended class
	protected $_boardutl;
	protected $_db_server;
	protected $_db_user;
	protected $_db_passwd;
	protected $_db_type;
	protected $_db_name;
	protected $_db_prefix;
	protected $_db_table;
	protected $_boardurl;

	/**
	 * Runs the query's defined in the install files to the db
	 */
	public function run_queries()
	{
		$exists = array();
		foreach ($this->_queries['tables'] as $table_method)
		{
			$table_name = substr($table_method, 6);

			// Copied from DbTable class
			// Strip out the table name, we might not need it in some cases
			$real_prefix = preg_match('~^("?)(.+?)\\1\\.(.*?)$~', $this->_db_prefix, $match) === 1 ? $match[3] : $this->_db_prefix;

			// With or without the database name, the fullname looks like this.
			$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);

			if ($this->_db_table->table_exists($full_table_name))
			{
				$exists[] = $table_method;
				continue;
			}

			$this->_install_instance->{$table_method}();
		}

		foreach ($this->_queries['inserts'] as $insert_method)
		{
			$table_name = substr($insert_method, 6);

			if (in_array($table_name, $exists))
			{
				continue;
			}

			$this->_install_instance->{$insert_method}();
		}

		// Errors here are ignored
		foreach ($this->_queries['others'] as $other_method)
		{
			$this->_install_instance->{$other_method}();
		}
	}

	/**
	 * Loads the query's from the supplied database install file
	 *
	 * @param string $sql_file
	 */
	public function load_queries($sql_file)
	{
		global $txt;

		require_once(BOARDDIR . '/themes/default/languages/english/Install.english.php');
		require_once(BOARDDIR . '/themes/default/languages/english/index.english.php');

		$replaces = array(
			'{$db_prefix}' => $this->_db_prefix,
			'{BOARDDIR}' => BOARDDIR,
			'{$boardurl}' => $this->_boardurl,
			'{$enableCompressedOutput}' => 0,
			'{$databaseSession_enable}' => 1,
			'{$current_version}' => CURRENT_VERSION,
			'{$current_time}' => time(),
			'{$sched_task_offset}' => 82800 + mt_rand(0, 86399),
		);

		foreach ($txt as $key => $value)
		{
			if (substr($key, 0, 8) == 'default_')
			{
				$replaces['{$' . $key . '}'] = addslashes($value);
			}
		}
		$replaces['{$default_reserved_names}'] = strtr($replaces['{$default_reserved_names}'], array('\\\\n' => '\\n'));

		$this->_db->skip_next_error();
		$db_wrapper = new DbWrapper($this->_db, $replaces);
		$db_table_wrapper = new DbTableWrapper($this->_db_table);

		$current_statement = '';
		$exists = array();

		require_once($sql_file);

		$class_name = 'InstallInstructions_' . str_replace('-', '_', basename($sql_file, '.php'));
		$this->_install_instance = new $class_name($db_wrapper, $db_table_wrapper);
		$methods = get_class_methods($this->_install_instance);

		$this->_queries['tables'] = array_filter($methods, function ($method)
		{
			return strpos($method, 'table_') === 0;
		});

		$this->_queries['inserts'] = array_filter($methods, function ($method)
		{
			return strpos($method, 'insert_') === 0;
		});

		$this->_queries['others'] = array_filter($methods, function ($method)
		{
			return substr($method, 0, 2) !== '__' && strpos($method, 'insert_') !== 0 && strpos($method, 'table_') !== 0;
		});
	}

	/**
	 * Clear the DB for a new install
	 */
	public function clear_tables()
	{
		// Get all the tables.
		$tables = $this->_db->list_tables($this->_db_name, $this->_db_prefix . '%');

		// Bu-bye
		foreach ($tables as $table)
		{
			$this->_db_table->drop_table($table);
		}
	}

	/**
	 * Updates the settings.php file to work with a given install / db scheme
	 */
	public function prepare_settings()
	{
		$file = file_get_contents(BOARDDIR . '/Settings.php');

		$file = str_replace(array(
			'$boardurl = \'http://127.0.0.1/elkarte\';',
			'$db_type = \'mysql\';',
			'$db_name = \'elkarte\';',
			'$db_user = \'root\';',
			'$db_prefix = \'elkarte_\';',
			'$db_passwd = \'\';',
		),
			array(
				'$boardurl = \'' . $this->_boardurl . '\';',
				'$db_type = \'' . $this->_db_type . '\';',
				'$db_name = \'' . $this->_db_name . '\';',
				'$db_user = \'' . $this->_db_user . '\';',
				'$db_prefix = \'' . $this->_db_prefix . '\';',
				'$db_passwd = \'' . $this->_db_passwd . '\';',
			),
			$file
		);

		if (strpos($file, 'if (file_exist') !== false)
		{
			$file = substr($file, 0, strpos($file, 'if (file_exist'));
		}

		file_put_contents(BOARDDIR . '/Settings.php', $file);
	}

	/**
	 * Called after db is setup, calls functions to prepare for testing
	 */
	public function prepare()
	{
		$this->prepare_settings();
		$this->update();

		//$this->createTests();
	}

	/**
	 * Adds a user, sets time, prepares the forum for phpunit tests
	 */
	public function update()
	{
		global $settings, $db_type;
		global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
		global $boardurl, $webmaster_email, $cookiename;
		global $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send;
		global $modSettings, $context, $sc, $user_info, $topic, $board, $txt;
		global $ssi_db_user, $scripturl, $ssi_db_passwd, $db_passwd;
		global $sourcedir, $boarddir;

		DEFINE('SUBSDIR', BOARDDIR . '/sources/subs');
		DEFINE('EXTDIR', BOARDDIR . '/sources/ext');
		DEFINE('SOURCEDIR', BOARDDIR . '/sources');
		DEFINE('LANGUAGEDIR', BOARDDIR . '/themes/default/languages');
		DEFINE('ADMINDIR', SOURCEDIR . '/admin');
		DEFINE('CONTROLLERDIR', SOURCEDIR . '/controllers');
		DEFINE('ADDONSDIR', SOURCEDIR . '/addons');

		require_once(BOARDDIR . '/Settings.php');
		require_once(SOURCEDIR . '/Subs.php');
		require_once(SOURCEDIR . '/Load.php');
		require_once(SUBSDIR . '/Auth.subs.php');
		require_once(EXTDIR . '/ClassLoader.php');
		require_once(SOURCEDIR . '/database/Database.subs.php');

		$loader = new \ElkArte\ext\Composer\Autoload\ClassLoader();
		$loader->setPsr4('ElkArte\\', SOURCEDIR . '/ElkArte');
		$loader->setPsr4('BBC\\', SOURCEDIR . '/ElkArte/BBC');
		$loader->register();

		$settings['theme_dir'] = $settings['default_theme_dir'] = BOARDDIR . '/Themes/default';
		$settings['theme_url'] = $settings['default_theme_url'] = $boardurl . '/themes/default';

		// Create an admin member
		$db = database();

		// Get a security hash for this combination
		$password = stripslashes('test_admin_pwd');
		$passwd = validateLoginPassword($password, '', 'test_admin', true);

		$db->insert('', '
			{db_prefix}members',
			array(
				'member_name' => 'string-25', 'real_name' => 'string-25', 'passwd' => 'string', 'email_address' => 'string',
				'id_group' => 'int', 'posts' => 'int', 'date_registered' => 'int', 'hide_email' => 'int',
				'password_salt' => 'string', 'lngfile' => 'string', 'avatar' => 'string',
				'member_ip' => 'string', 'member_ip2' => 'string', 'buddy_list' => 'string', 'pm_ignore_list' => 'string',
				'message_labels' => 'string', 'website_title' => 'string', 'website_url' => 'string',
				'signature' => 'string', 'usertitle' => 'string', 'secret_question' => 'string',
				'additional_groups' => 'string', 'ignore_boards' => 'string', 'openid_uri' => 'string',
			),
			array(
				'test_admin', 'test_admin', $passwd, 'email@testadmin.tld',
				1, 0, time(), 0,
				substr(md5(mt_rand()), 0, 4), '', '',
				'123.123.123.123', '123.123.123.123', '', '',
				'', '', '',
				'', '', '',
				'', '', '',
			),
			array('id_member')
		);

		$server_offset = @mktime(0, 0, 0, 1, 1, 1970);
		$timezone_id = 'Etc/GMT' . ($server_offset > 0 ? '+' : '') . ($server_offset / 3600);

		if (date_default_timezone_set($timezone_id))
		{
			$db->insert('',
				$db_prefix . 'settings',
				array(
					'variable' => 'string-255', 'value' => 'string-65534',
				),
				array(
					'default_timezone', $timezone_id,
				),
				array('variable')
			);
		}

		// @todo Do we really need to update stats cn testing mode? Comented for now...
		//~ require_once(SUBSDIR . '/Members.subs.php');
		//~ updateMemberStats();

		//~ require_once(SUBSDIR . '/Messages.subs.php');
		//~ updateMessageStats();

		//~ require_once(SUBSDIR . '/Topic.subs.php');
		//~ updateTopicStats();

		//~ theme()->getTemplates()->loadLanguageFile('Install');
		//~ updateSubjectStats(1, htmlspecialchars($txt['default_topic_subject']));
	}
}

class DbWrapper
{
	protected $db = null;
	protected $count_mode = false;
	protected $replaces = array();

	public function __construct($db, $replaces)
	{
		$this->db = $db;
		$this->replaces = $replaces;
	}

	public function __call($name, $args)
	{
		return call_user_func_array(array($this->db, $name), $args);
	}

	public function insert()
	{
		$args = func_get_args();

		if ($this->count_mode)
		{
			return count($args[3]);
		}

		foreach ($args[3] as $key => $data)
		{
			foreach ($data as $k => $v)
			{
				$args[3][$key][$k] = strtr($v, $this->replaces);
			}
		}

		call_user_func_array(array($this->db, 'insert'), $args);

		return $this->db->affected_rows();
	}

	public function countMode($on = true)
	{
		$this->count_mode = (bool) $on;
	}
}

class DbTableWrapper
{
	protected $db = null;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function __call($name, $args)
	{
		return call_user_func_array(array($this->db, $name), $args);
	}

	public function db_add_index()
	{
		$args = func_get_args();

		// In this case errors are ignored, so the return is always true
		call_user_func_array(array($this->db, 'create_table'), $args);

		return true;
	}
}
