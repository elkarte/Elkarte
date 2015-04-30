<?php

/**
	* Simple FTP protocol implementation.
	*
	* http://www.faqs.org/rfcs/rfc959.html
	*/
class Ftp_Connection
{
	/**
		* holds the connection response
		* @var resource
		*/
	public $connection;

	/**
		* holds any errors
		* @var string|boolean
		*/
	public $error;

	/**
		* holds last message from the server
		* @var string
		*/
	public $last_message;

	/**
		* Passive connection
		* @var mixed[]
		*/
	public $pasv;

	/**
		* Create a new FTP connection...
		*
		* @param string $ftp_server
		* @param int $ftp_port
		* @param string $ftp_user
		* @param string $ftp_pass
		*/
	public function __construct($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@yourdomain.org')
	{
		// Initialize variables.
		$this->connection = 'no_connection';
		$this->error = false;
		$this->pasv = array();

		if ($ftp_server !== null)
			$this->connect($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
	}

	/**
		* Connects to a server
		*
		* @param string $ftp_server
		* @param int $ftp_port
		* @param string $ftp_user
		* @param string $ftp_pass
		*/
	public function connect($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@yourdomain.org')
	{
		if (strpos($ftp_server, 'ftp://') === 0)
			$ftp_server = substr($ftp_server, 6);
		elseif (strpos($ftp_server, 'ftps://') === 0)
			$ftp_server = 'ssl://' . substr($ftp_server, 7);
		if (strpos($ftp_server, 'http://') === 0)
			$ftp_server = substr($ftp_server, 7);
		$ftp_server = strtr($ftp_server, array('/' => '', ':' => '', '@' => ''));

		// Connect to the FTP server.
		$this->connection = @fsockopen($ftp_server, $ftp_port, $err, $err, 5);
		if (!$this->connection)
		{
			$this->error = 'bad_server';
			return;
		}

		// Get the welcome message...
		if (!$this->check_response(220))
		{
			$this->error = 'bad_response';
			return;
		}

		// Send the username, it should ask for a password.
		fwrite($this->connection, 'USER ' . $ftp_user . "\r\n");
		if (!$this->check_response(331))
		{
			$this->error = 'bad_username';
			return;
		}

		// Now send the password... and hope it goes okay.
		fwrite($this->connection, 'PASS ' . $ftp_pass . "\r\n");
		if (!$this->check_response(230))
		{
			$this->error = 'bad_password';
			return;
		}
	}

	/**
		* Changes to a directory (chdir) via the ftp connection
		*
		* @param string $ftp_path
		* @return boolean
		*/
	public function chdir($ftp_path)
	{
		if (!is_resource($this->connection))
			return false;

		// No slash on the end, please...
		if ($ftp_path !== '/' && substr($ftp_path, -1) === '/')
			$ftp_path = substr($ftp_path, 0, -1);

		fwrite($this->connection, 'CWD ' . $ftp_path . "\r\n");
		if (!$this->check_response(250))
		{
			$this->error = 'bad_path';
			return false;
		}

		return true;
	}

	/**
		* Changes a files atrributes (chmod)
		*
		* @param string $ftp_file
		* @param int $chmod
		* @return boolean
		*/
	public function chmod($ftp_file, $chmod)
	{
		if (!is_resource($this->connection))
			return false;

		if ($ftp_file == '')
			$ftp_file = '.';

		// Convert the chmod value from octal (0777) to text ("777").
		fwrite($this->connection, 'SITE CHMOD ' . decoct($chmod) . ' ' . $ftp_file . "\r\n");
		if (!$this->check_response(200))
		{
			$this->error = 'bad_file';
			return false;
		}

		return true;
	}

	/**
		* Deletes a file
		*
		* @param string $ftp_file
		* @return boolean
		*/
	public function unlink($ftp_file)
	{
		// We are actually connected, right?
		if (!is_resource($this->connection))
			return false;

		// Delete file X.
		fwrite($this->connection, 'DELE ' . $ftp_file . "\r\n");
		if (!$this->check_response(250))
		{
			fwrite($this->connection, 'RMD ' . $ftp_file . "\r\n");

			// Still no love?
			if (!$this->check_response(250))
			{
				$this->error = 'bad_file';
				return false;
			}
		}

		return true;
	}

	/**
		* Reads the response to the command from the server
		*
		* @param string[]|string $desired string or array of acceptable return values
		*/
	public function check_response($desired)
	{
		// Wait for a response that isn't continued with -, but don't wait too long.
		$time = time();
		do
			$this->last_message = fgets($this->connection, 1024);
		while ((strlen($this->last_message) < 4 || strpos($this->last_message, ' ') === 0 || strpos($this->last_message, ' ', 3) !== 3) && time() - $time < 5);

		// Was the desired response returned?
		return is_array($desired) ? in_array(substr($this->last_message, 0, 3), $desired) : substr($this->last_message, 0, 3) == $desired;
	}

	/**
		* Used to create a passive connection
		*
		* @return boolean
		*/
	public function passive()
	{
		// We can't create a passive data connection without a primary one first being there.
		if (!is_resource($this->connection))
			return false;

		// Request a passive connection - this means, we'll talk to you, you don't talk to us.
		@fwrite($this->connection, 'PASV' . "\r\n");
		$time = time();
		do
			$response = fgets($this->connection, 1024);
		while (substr($response, 3, 1) !== ' ' && time() - $time < 5);

		// If it's not 227, we weren't given an IP and port, which means it failed.
		if (strpos($response, '227 ') !== 0)
		{
			$this->error = 'bad_response';
			return false;
		}

		// Snatch the IP and port information, or die horribly trying...
		if (preg_match('~\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))\)~', $response, $match) == 0)
		{
			$this->error = 'bad_response';
			return false;
		}

		// This is pretty simple - store it for later use ;).
		$this->pasv = array('ip' => $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4], 'port' => $match[5] * 256 + $match[6]);

		return true;
	}

	/**
		* Creates a new file on the server
		*
		* @param string $ftp_file
		* @return boolean
		*/
	public function create_file($ftp_file)
	{
		// First, we have to be connected... very important.
		if (!is_resource($this->connection))
			return false;

		// I'd like one passive mode, please!
		if (!$this->passive())
			return false;

		// Seems logical enough, so far...
		fwrite($this->connection, 'STOR ' . $ftp_file . "\r\n");

		// Okay, now we connect to the data port.  If it doesn't work out, it's probably "file already exists", etc.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(150))
		{
			$this->error = 'bad_file';
			@fclose($fp);
			return false;
		}

		// This may look strange, but we're just closing it to indicate a zero-byte upload.
		fclose($fp);
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return true;
	}

	/**
		* Generates a direcotry listing for the current directory
		*
		* @param string $ftp_path
		* @param string|boolean $search
		* @return false|string
		*/
	public function list_dir($ftp_path = '', $search = false)
	{
		// Are we even connected...?
		if (!is_resource($this->connection))
			return false;

		// Passive... non-aggressive...
		if (!$this->passive())
			return false;

		// Get the listing!
		fwrite($this->connection, 'LIST -1' . ($search ? 'R' : '') . ($ftp_path == '' ? '' : ' ' . $ftp_path) . "\r\n");

		// Connect, assuming we've got a connection.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(array(150, 125)))
		{
			$this->error = 'bad_response';
			@fclose($fp);
			return false;
		}

		// Read in the file listing.
		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// Everything go okay?
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return $data;
	}

	/**
		* Determins the current dirctory we are in
		*
		* @param string $file
		* @param string|null $listing
		* @return string|false
		*/
	public function locate($file, $listing = null)
	{
		if ($listing === null)
			$listing = $this->list_dir('', true);
		$listing = explode("\n", $listing);

		@fwrite($this->connection, 'PWD' . "\r\n");
		$time = time();
		do
			$response = fgets($this->connection, 1024);
		while (substr($response, 3, 1) !== ' ' && time() - $time < 5);

		// Check for 257!
		if (preg_match('~^257 "(.+?)" ~', $response, $match) != 0)
			$current_dir = strtr($match[1], array('""' => '"'));
		else
			$current_dir = '';

		for ($i = 0, $n = count($listing); $i < $n; $i++)
		{
			if (trim($listing[$i]) == '' && isset($listing[$i + 1]))
			{
				$current_dir = substr(trim($listing[++$i]), 0, -1);
				$i++;
			}

			// Okay, this file's name is:
			$listing[$i] = $current_dir . '/' . trim(strlen($listing[$i]) > 30 ? strrchr($listing[$i], ' ') : $listing[$i]);

			if ($file[0] == '*' && substr($listing[$i], -(strlen($file) - 1)) == substr($file, 1))
				return $listing[$i];
			if (substr($file, -1) == '*' && substr($listing[$i], 0, strlen($file) - 1) == substr($file, 0, -1))
				return $listing[$i];
			if (basename($listing[$i]) == $file || $listing[$i] == $file)
				return $listing[$i];
		}

		return false;
	}

	/**
		* Creates a new directory on the server
		*
		* @param string $ftp_dir
		* @return boolean
		*/
	public function create_dir($ftp_dir)
	{
		// We must be connected to the server to do something.
		if (!is_resource($this->connection))
			return false;

		// Make this new beautiful directory!
		fwrite($this->connection, 'MKD ' . $ftp_dir . "\r\n");
		if (!$this->check_response(257))
		{
			$this->error = 'bad_file';
			return false;
		}

		return true;
	}

	/**
		* Detects the current path
		*
		* @param string $filesystem_path
		* @param string|null $lookup_file
		* @return string[] $username, $path, found_path
		*/
	public function detect_path($filesystem_path, $lookup_file = null)
	{
		$username = '';

		if (isset($_SERVER['DOCUMENT_ROOT']))
		{
			if (preg_match('~^/home[2]?/([^/]+?)/public_html~', $_SERVER['DOCUMENT_ROOT'], $match))
			{
				$username = $match[1];

				$path = strtr($_SERVER['DOCUMENT_ROOT'], array('/home/' . $match[1] . '/' => '', '/home2/' . $match[1] . '/' => ''));

				if (substr($path, -1) == '/')
					$path = substr($path, 0, -1);

				if (strlen(dirname($_SERVER['PHP_SELF'])) > 1)
					$path .= dirname($_SERVER['PHP_SELF']);
			}
			elseif (strpos($filesystem_path, '/var/www/') === 0)
				$path = substr($filesystem_path, 8);
			else
				$path = strtr(strtr($filesystem_path, array('\\' => '/')), array($_SERVER['DOCUMENT_ROOT'] => ''));
		}
		else
			$path = '';

		if (is_resource($this->connection) && $this->list_dir($path) == '')
		{
			$data = $this->list_dir('', true);

			if ($lookup_file === null)
				$lookup_file = $_SERVER['PHP_SELF'];

			$found_path = dirname($this->locate('*' . basename(dirname($lookup_file)) . '/' . basename($lookup_file), $data));
			if ($found_path == false)
				$found_path = dirname($this->locate(basename($lookup_file)));
			if ($found_path != false)
				$path = $found_path;
		}
		elseif (is_resource($this->connection))
			$found_path = true;

		return array($username, $path, isset($found_path));
	}

	/**
		* Close the ftp connection
		*
		* @return boolean
		*/
	public function close()
	{
		// Goodbye!
		fwrite($this->connection, 'QUIT' . "\r\n");
		fclose($this->connection);

		return true;
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

	public function insert()
	{
		$args = func_get_args();

		if ($this->count_mode)
			return count($args[3]);

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
		call_user_func_array(array($this->db, 'db_create_table'), $args);

		return true;
	}
}

class DbTable_MySQL_Install extends DbTable_MySQL
{
	public static $_tbl_inst = null;
	/**
	* DbTable_MySQL::construct
	*
	* @param object $db - A Database_MySQL object
	*/
	private function __construct($db)
	{
		global $db_prefix;

		// We are doing install, of course we want to do any remove on these
		$this->_reservedTables = array();

		// let's be sure.
		$this->_package_log = array();

		// This executes queries and things
		$this->_db = $db;
	}

	/**
	* Static method that allows to retrieve or create an instance of this class.
	*
	* @param object $db - A Database_MySQL object
	* @return object - A DbTable_MySQL object
	*/
	public static function db_table($db)
	{
		if (is_null(self::$_tbl_inst))
			self::$_tbl_inst = new DbTable_MySQL_Install($db);
		return self::$_tbl_inst;
	}
}

class DbTable_PostgreSQL_Install extends DbTable_PostgreSQL
{
	public static $_tbl_inst = null;
	/**
	* DbTable_MySQL::construct
	*
	* @param object $db - A Database_MySQL object
	*/
	private function __construct($db)
	{
		global $db_prefix;

		// We are doing install, of course we want to do any remove on these
		$this->_reservedTables = array();

		// let's be sure.
		$this->_package_log = array();

		// This executes queries and things
		$this->_db = $db;
	}

	/**
	* Static method that allows to retrieve or create an instance of this class.
	*
	* @param object $db - A Database_MySQL object
	* @return object - A DbTable_MySQL object
	*/
	public static function db_table($db)
	{
		if (is_null(self::$_tbl_inst))
			self::$_tbl_inst = new DbTable_PostgreSQL_Install($db);
		return self::$_tbl_inst;
	}
}

/**
 * This handy function loads some settings and the like.
 */
function load_database()
{
	global $db_prefix, $db_connection, $modSettings, $db_type, $db_name, $db_user, $db_persist;

	// Connect the database.
	if (empty($db_connection))
	{
		if (!defined('SOURCEDIR'))
			define('SOURCEDIR', TMP_BOARDDIR . '/sources');

		// Need this to check whether we need the database password.
		require(TMP_BOARDDIR . '/Settings.php');

		if (!defined('ELK'))
			define('ELK', 1);

		require_once(SOURCEDIR . '/database/Database.subs.php');

		$db_connection = elk_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist, 'port' => $db_port), $db_type);
	}

	return database();
}

/**
 * The normal DbTable disallows to delete/create "core" tables
 */
function db_table_install()
{
	global $db_type;

	$db = load_database();

	require_once(SOURCEDIR . '/database/DbTable.class.php');
	require_once(SOURCEDIR . '/database/DbTable-' . $db_type . '.php');

	return call_user_func(array('DbTable_' . DB_TYPE . '_Install', 'db_table'), $db);
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
function db_version_check()
{
	global $db_type, $databases, $db_connection;

	$curver = $databases[$db_type]['version_check']($db_connection);
	$curver = preg_replace('~\-.+?$~', '', $curver);

	return version_compare($databases[$db_type]['version'], $curver, '<=');
}

/**
 * Delete the installer and its additional files.
 * Called by ?delete
 */
function action_deleteInstaller()
{
	global $databases, $package_ftp;

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
	header('Location: http://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT']) . dirname($_SERVER['PHP_SELF']) . '/../themes/default/images/blank.png');
	exit;
}

/**
 * Removes flagged settings
 * Appends new settings as passed in $config_vars to the array
 * Writes out a new Settings.php file, overwriting any that may have existed
 *
 * @param array $config_vars
 * @param array $settingsArray
 */
function saveFileSettings($config_vars, $settingsArray)
{
	if (count($settingsArray) == 1)
		$settingsArray = preg_split('~[\r\n]~', $settingsArray[0]);

	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		if (trim($settingsArray[$i]) === '?>')
			$settingsArray[$i] = '';
		// Don't trim or bother with it if it's not a variable.
		if (substr($settingsArray[$i], 0, 1) == '$')
		{
			$settingsArray[$i] = trim($settingsArray[$i]) . "\n";

			foreach ($config_vars as $var => $val)
			{
				if (isset($settingsArray[$i]) && strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
				{
					if ($val === '#remove#')
					{
						unset($settingsArray[$i]);
					}
					else
					{
						$comment = strstr(substr($settingsArray[$i], strpos($settingsArray[$i], ';')), '#');
						$settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment != '' ? "\t\t" . $comment : "\n");
					}

					unset($config_vars[$var]);
				}
			}
		}
	}

	// Add in the new vars we were passed
	if (!empty($config_vars))
	{
		$settingsArray[$i++] = '';
		foreach ($config_vars as $var => $val)
		{
			if ($val != '#remove#')
				$settingsArray[$i++] = "\n$" . $var . ' = ' . $val . ';';
		}
	}

	// Blank out the file - done to fix a oddity with some servers.
	$fp = @fopen(TMP_BOARDDIR . '/Settings.php', 'w');
	if (!$fp)
		return false;
	fclose($fp);

	$fp = fopen(TMP_BOARDDIR . '/Settings.php', 'r+');

	// Gotta have one of these ;)
	if (trim($settingsArray[0]) != '<?php')
		fwrite($fp, "<?php\n");

	$lines = count($settingsArray);
	for ($i = 0; $i < $lines; $i++)
	{
		// Don't just write a bunch of blank lines.
		if ($settingsArray[$i] != '' || @$settingsArray[$i - 1] != '')
			fwrite($fp, strtr($settingsArray[$i], "\r", ''));
	}
	fclose($fp);

	return true;

	// Blank out the file - done to fix a oddity with some servers.
	//file_put_contents(BOARDDIR . '/Settings.php', '', LOCK_EX);
	//file_put_contents(BOARDDIR . '/Settings.php', $settingsArray, LOCK_EX);
}
