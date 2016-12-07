<?php

/* 
 * Bootstrap all of the tests
 */

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

define('TEST_BASE_DIR', __DIR__);
define('TEST_DIR', __DIR__ . '/Tests');

$elkTestBootstrap = new TestBootstrap;

/**
 * Just in case someone doesn't want to use the global
 * 
 * @staticvar TestBootstrap $elkTestBootstrap
 * @param TestBootstrap $elkTestBootstrap
 * @return TestBootstrap
 */
function elkTestBootstrap(TestBootstrap $elkTestBootstrap = null)
{
	static $elkTestBootstrap;
	return $elkTestBootstrap;
}
elkTestBootstrap($elkTestBootstrap);

class TestBootstrap
{
	protected $settings_file;
	protected $boardurl = 'http://127.0.0.1';

	// Database settings (defaults to MySQL)
	protected $db_server = 'localhost';
	protected $db_type = 'mysql';
	protected $db_name = 'hello_world_test';
	protected $db_user = 'root';
	protected $db_passwd = '';
	protected $db_prefix = 'elkarte_';

	public function __construct()
	{
		$this->settings_file = $this->findSettingsFile();
		if (false === $this->settings_file)
		{
			throw new \Exception('Unable to find the settings file. All tests FAIL!');
		}
	}

	/**
	 * Define the basic constants that are used everywhere
	 */
	public function constants($ssi = false)
	{
		define('ELK', $ssi ? 'SSI' : 1);

		define('BOARDDIR', $GLOBALS['boarddir']);
		define('CACHEDIR', $GLOBALS['cachedir']);
		define('EXTDIR', $GLOBALS['extdir']);
		define('LANGUAGEDIR', $GLOBALS['languagedir']);
		define('SOURCEDIR', $GLOBALS['sourcedir']);
		define('ADMINDIR', $GLOBALS['sourcedir'] . '/admin');
		define('CONTROLLERDIR', $GLOBALS['sourcedir'] . '/controllers');
		define('SUBSDIR', $GLOBALS['sourcedir'] . '/subs');

		// Now remove those globals
		unset($GLOBALS['boarddir'], $GLOBALS['cachedir'], $GLOBALS['extdir'], $GLOBALS['languagedir'], $GLOBALS['sourcedir']);
	}

	/**
	 * Finds the Settings.php file.
	 * That way these tests can be moved without breaking
	 * 
	 * @return string|boolean
	 */
	public function findSettingsFile()
	{
		$filename = 'Settings.php';

		// Try 5 levels deep to find the settings file.
		for ($i = 0; $i < 5; $i++)
		{
			if (file_exists($filename))
			{
				return $filename;
			}

			$filename = '../' . $filename;
		}

		return false;
	}

	/**
	 * Edit the Settings.php for the current environment
	 */
	public function settingsFile()
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
			'$boardurl = \'' . $this->boardurl . '\';',
			'$db_type = \'' . $this->db_type . '\';',
			'$db_name = \'' . $this->db_name . '\';',
			'$db_user = \'' . $this->db_user . '\';',
			'$db_prefix = \'' . $this->db_prefix . '\';',
			'$db_passwd = \'' . $this->db_passwd . '\';',
		),
		$file);
		if (strpos($file, 'if (file_exist') !== false)
			$file = substr($file, 0, strpos($file, 'if (file_exist'));
		$file .= "\n" . '$test_enabled = 1;';

		file_put_contents(BOARDDIR . '/Settings.php', $file);
	}

	public function getSettingsFile()
	{
		return $this->settings_file;
	}

	/**
	 * Not all of these are necessary.
	 * You don't have to call this method
	 */
	public function includes()
	{
		require_once(SOURCEDIR . '/QueryString.php');
		require_once(SOURCEDIR . '/Session.php');
		require_once(SOURCEDIR . '/Subs.php');
		require_once(SOURCEDIR . '/Errors.php');
		require_once(SOURCEDIR . '/Logging.php');
		require_once(SOURCEDIR . '/Load.php');
		require_once(SUBSDIR . '/Cache.subs.php');
		require_once(SOURCEDIR . '/Security.php');
		require_once(SOURCEDIR . '/BrowserDetect.class.php');
		require_once(SOURCEDIR . '/Errors.class.php');
		require_once(SUBSDIR . '/Util.class.php');
		require_once(SUBSDIR . '/TemplateLayers.class.php');
		require_once(SOURCEDIR . '/Action.controller.php');
	}

	public function getPHPFileIterator()
	{
		require_once('PHPFileIterator.php');

		return new PHPFileIterator(new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator()
		));
	}
}