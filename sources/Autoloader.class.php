<?php

/**
 * Used to auto load class files given a class name
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Elkarte autoloader
 *
 * What it does:
 * - Automatically finds and includes the files for a given class name
 * - Follows controller naming conventions to find the right file to load
 */
class Elk_Autoloader
{
	/**
	 * Instance manager
	 *
	 * @var Elk_Autoloader
	 */
	private static $_instance;

	/**
	* Stores whether the autoloader has been initalized
	*
	* @var boolean
	*/
	protected $_setup = false;

	/**
	* Stores whether the autoloader verifies file existance or not
	*
	* @var boolean
	*/
	protected $_strict = false;

	/**
	* Path to directory containing elkarte
	*
	* @var string
	*/
	protected $_dir = '.';

	/**
	 * Holds the name of the exploded class name
	 *
	 * @var array
	 */
	protected $_name;

	/**
	 * Holds the class name ahdead of any _ if any
	 *
	 * @var string
	 */
	protected $_surname;

	/**
	 * Holds the class name after the first _ if any
	 *
	 * @var string
	 */
	protected $_givenname;

	/**
	 * Holds the name full file name of the file to load (require)
	 *
	 * @var string
	 */
	protected $_file_name = false;

	/**
	 * Holds the pairs namespace => paths
	 *
	 * @var string[]
	 */
	protected $_paths;

	/**
	* Constructor, not used, instead use getInstance()
	*/
	protected function __construct()
	{
	}

	/**
	 * Setup the autoloader environment
	 */
	public function setupAutoloader($dir)
	{
		if (!is_array($dir))
			$dir = array($dir);

		foreach ($dir as $path)
			$this->register($path);
	}

	/**
	 * Registers new paths for the autoloader
	 */
	public function register($dir, $namespace = null)
	{
		if ($namespace === null)
			$namespace = 0;

		if (!isset($this->_paths[$namespace]))
			$this->_paths[$namespace] = array();

		$this->_paths[$namespace][] = $dir;
		array_unique($this->_paths[$namespace]);

		$this->_buildPaths((array) $dir);
	}

	protected function _buildPaths($dir)
	{
		// Build the paths where we are going to look for files
		foreach ($dir as $include)
			$this->_dir .= $include .  PATH_SEPARATOR;

		// Initialize
		$this->_setupAutoloader();
		$this->_setup = true;
	}

	/**
	 * Method that actually registers the autoloader.
	 */
	protected function _setupAutoloader()
	{
		// Make sure our paths are in the include path
		set_include_path($this->_dir . '.' . (!@ini_get('open_basedir') ? PATH_SEPARATOR . get_include_path() : ''));

		// The autoload "magic"
		spl_autoload_register(array($this, 'elk_autoloader'));
	}

	/**
	 * Callback for the spl_autoload_register, loads the requested class
	 *
	 * @param string $class
	 */
	public function elk_autoloader($class)
	{
		// Break the class name in to its parts
		if (!$this->_string_to_class($class))
			return false;

		if ($this->_current_namespace !== false)
			$this->_handle_nacespaces();

		// Basic cases like Util.class, Action.class, Request.class
		if ($this->_file_name === false)
			$this->_handle_basic_cases();

		// All the rest
		if ($this->_file_name === false)
			$this->_handle_other_cases();

		$file = $this->_file_name;

		// Start fresh for the next one
		$this->_file_name = false;
		// Well do we have something to do?
		if (!empty($file))
		{
			if ($this->_strict && stream_resolve_include_path($file))
				require_once($file);
			else
				require_once($file);
		}
	}

	/**
	 * Resolves a class name to an autoload name
	 *
	 * @param string $class - Name of class to autoload
	 */
	private function _string_to_class($class)
	{
		$namespaces = explode('\\', ltrim($class, '\\'));
		if (count($namespaces) > 1)
		{
			$class = array_pop($namespaces);
			$this->_current_namespace = '\\' . implode('\\', $namespaces);
		}
		else
			$this->_current_namespace = false;

		// The name must be letters, numbers and _ only
		if (preg_match('~[^a-z0-9_]~i', $class))
			return false;

		$this->_name = explode('_', $class);
		$this->_surname = array_pop($this->_name);
		$this->_givenname = implode('', $this->_name);

		return true;
	}

	/**
	 * This handles any case where a namespace is present.
	 */
	protected function _handle_nacespaces()
	{
		if (isset($this->_paths[$this->_current_namespace]))
		{
			foreach ($this->_paths[$this->_current_namespace] as $possible_dir)
			{
				$file = $possible_dir . '/' . $this->_givenname . $this->_surname . '.php';

				if (file_exists($file))
				{
					$this->_file_name = $file;
					break;
				}
			}
		}
	}

	/**
	 * This handles the simple cases, mostly single word class names.
	 *
	 * - Bypasses db classes as those are done elsewhere
	 */
	private function _handle_basic_cases()
	{
		switch ($this->_givenname)
		{
			case 'VerificationControls':
				$this->_file_name = SUBSDIR . '/VerificationControls.class.php';
				break;
			case 'AdminSettings':
				$this->_file_name = SUBSDIR . '/AdminSettingsSearch.class.php';
				break;
			// We don't handle these with the autoloader
			case 'Database':
			case 'DbSearch':
			case 'DbTable':
				$this->_file_name = '';
				break;
			// Simple one word class names like Util.class, Action.class, Request.class ...
			case '':
				$this->_file_name = $this->_surname;

				// validate the file since it can vary
				if (stream_resolve_include_path($this->_file_name . '.class.php'))
					$this->_file_name = $this->_file_name . '.class.php';
				elseif (stream_resolve_include_path($this->_file_name . '.php'))
					$this->_file_name = $this->_file_name . '.php';
				else
					$this->_file_name = '';
				break;
			default:
				$this->_file_name = false;
		}
	}

	/**
	 * This handles Some_Controller style classes
	 */
	private function _handle_other_cases()
	{
		switch ($this->_surname)
		{
			// Some_Controller => Some.controller.php
			case 'Controller':
				$this->_file_name = $this->_givenname . '.controller.php';

				// Try source, controller, admin, then addons
				if (stream_resolve_include_path($this->_file_name))
					$this->_file_name = $this->_file_name;
				else
					$this->_file_name = '';
				break;
			// Some_Search => SearchAPI-Some.class
			case 'Search':
				$this->_file_name = SUBSDIR . '/SearchAPI-' . $this->_givenname . '.class.php';
				break;
			// Some_Thing_Exception => /Exception/SomeThingException.class.php
			case 'Exception':
				$this->_file_name = SUBSDIR . '/Exception/' . $this->_givenname . $this->_surname . '.class.php';
				break;
			// Some_Cache => SomeCache.class.php
			case 'Integrate':
				$this->_file_name = SUBSDIR . '/' . $this->_givenname . '.integrate.php';
				if (!stream_resolve_include_path($this->_file_name))
					$this->_file_name = '';
				break;
			// Some_Display => Subscriptions-Some.class.php
			case 'Display':
			case 'Payment':
				$this->_file_name = SUBSDIR . '/Subscriptions-' . implode('_', $this->_name) . '.class.php';
				break;
			case 'Module':
				$this->_file_name = SOURCEDIR . '/modules/' . $this->_name[0] . '/' . $this->_givenname . 'Module.class.php';
				break;
			case 'Interface':
			case 'Abstract':
				if ($this->_surname == 'Interface')
					$this->_file_name = $this->_givenname . '.interface.php';
				else
					$this->_file_name = $this->_givenname . 'Abstract.class.php';

				if (file_exists(SUBSDIR . '/' . $this->_file_name))
					$this->_file_name = SUBSDIR . '/' . $this->_file_name;
				else
				{
					$dir = SUBSDIR . '/' . $this->_givenname;

					if (file_exists($dir . '/' . $this->_file_name))
						$this->_file_name = $dir . '/' . $this->_file_name;
					elseif (!empty($this->_name[1]) && $this->_name[1] == 'Module')
						$this->_file_name = SOURCEDIR . '/modules/' . $this->_name[0] . '/' . $this->_file_name;
					// Not knowing what it is, better leave it empty
					else
						$this->_file_name = '';
				}
				break;
			// All the rest, like Browser_Detector, Template_Layers, Site_Dispatcher ...
			default:
				$this->_file_name = $this->_givenname . $this->_surname;

				if (stream_resolve_include_path($this->_file_name . '.class.php'))
					$this->_file_name = $this->_file_name . '.class.php';
				elseif (stream_resolve_include_path($this->_file_name . '.php'))
					$this->_file_name = $this->_file_name . '.php';
				else
					$this->_file_name = '';
		}
	}

	/**
	 * Returns the instance of the autoloader
	 *
	 * - Uses final definition to prevent child classes from overriding this method
	 */
	public static final function getInstance()
	{
		if (!self::$_instance)
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Manually sets the autoloader instance.
	 *
	 * - Use this to inject a modified version.
	 */
	public static function setInstance(Elk_Autoloader $loader = null)
	{
		self::$_instance = $loader;
	}
}