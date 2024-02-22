<?php

/**
 * This file has all the main functions in it that relate to adding removing, etc on hooks.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use ElkArte\Database\QueryInterface;
use FilesystemIterator;
use GlobIterator;

/**
 * Class Hooks
 */
final class Hooks
{
	/** @var Hooks The instance of the class */
	private static $_instance;

	/** @var array Holds our standard path replacement array */
	protected $_path_replacements = [];

	/** @var FileFunctions */
	protected $fileFunc;

	/**
	 * The class constructor, loads globals in to the class object
	 *
	 * @param QueryInterface $_db
	 * @param Debug $_debug
	 * @param string[]|string|null $paths - additional paths to add to the replacement array
	 */
	private function __construct(protected $_db, protected $_debug, $paths = null)
	{
		$this->_path_replacements = [
			'BOARDDIR' => BOARDDIR,
			'SOURCEDIR' => SOURCEDIR,
			'EXTDIR' => EXTDIR,
			'LANGUAGEDIR' => LANGUAGEDIR,
			'ADMINDIR' => ADMINDIR,
			'CONTROLLERDIR' => CONTROLLERDIR,
			'SUBSDIR' => SUBSDIR,
		];
		$this->fileFunc = FileFunctions::instance();

		if ($paths !== null)
		{
			$this->newPath($paths);
		}
	}

	/**
	 * Allows to set a new replacement path.
	 *
	 * @param string[]|string $path an array consisting of pairs "search" => "replace with"
	 */
	public function newPath($path)
	{
		$this->_path_replacements = array_merge($this->_path_replacements, (array) $path);
	}

	/**
	 * Process functions of an integration hook.
	 *
	 * What it does:
	 *
	 * - calls all functions of the given hook.
	 * - supports static class method calls.
	 *
	 * @param string $hook
	 * @param array $parameters = array()
	 *
	 * @return array the results of the functions
	 */
	public function hook($hook, $parameters = [])
	{
		global $modSettings;

		if ($this->_debug !== null)
		{
			$this->_debug->add('hooks', $hook);
		}

		$results = [];
		if (empty($modSettings[$hook]))
		{
			return $results;
		}

		// Loop through each function.
		$functions = $this->_prepare_hooks($modSettings[$hook]);
		foreach ($functions as $function => $call)
		{
			$results[$function] = call_user_func_array($call, $parameters);
		}

		return $results;
	}

	/**
	 * Splits up strings from $modSettings into functions and files to include.
	 *
	 * @param string $hook_calls
	 *
	 * @return array
	 */
	protected function _prepare_hooks($hook_calls)
	{
		// Loop through each function.
		$functions = explode(',', $hook_calls);
		$returns = [];

		foreach ($functions as $function)
		{
			$function = trim($function);

			if (strpos($function, '|') !== false)
			{
				[$call, $file] = explode('|', $function);
			}
			else
			{
				$call = $function;
				$file = '';
			}

			// OOP static method
			if (strpos($call, '::') !== false)
			{
				$call = explode('::', $call);
			}

			if (!empty($file))
			{
				$absPath = strtr(trim($file), $this->_path_replacements);

				if ($this->fileFunc->fileExists($absPath))
				{
					require_once($absPath);
				}
			}

			// Is it valid?
			if (is_callable($call))
			{
				$returns[$function] = $call;
			}
		}

		return $returns;
	}

	/**
	 * Includes files for hooks that only do that (i.e. integrate_pre_include)
	 *
	 * @param string $hook
	 */
	public function include_hook($hook)
	{
		global $modSettings;

		if ($this->_debug !== null)
		{
			$this->_debug->add('hooks', $hook);
		}

		// Any file to include?
		if (!empty($modSettings[$hook]))
		{
			$pre_includes = explode(',', $modSettings[$hook]);
			foreach ($pre_includes as $include)
			{
				$include = strtr(trim($include), $this->_path_replacements);

				if ($this->fileFunc->fileExists($include))
				{
					require_once($include);
				}
			}
		}
	}

	/**
	 * Special hook call executed during obExit
	 */
	public function buffer_hook()
	{
		global $modSettings;

		if ($this->_debug !== null)
		{
			$this->_debug->add('hooks', 'integrate_buffer');
		}

		if (empty($modSettings['integrate_buffer']))
		{
			return;
		}

		$buffers = $this->_prepare_hooks($modSettings['integrate_buffer']);

		foreach ($buffers as $call)
		{
			ob_start($call);
		}
	}

	/**
	 * Add a function for integration hook.
	 *
	 * - does nothing if the function is already added.
	 *
	 * @param string $hook
	 * @param string $function
	 * @param string $file
	 * @param bool $permanent = true if true, updates the value in settings table
	 */
	public function add($hook, $function, $file = '', $permanent = true)
	{
		global $modSettings;

		$integration_call = (!empty($file) && $file !== true) ? $function . '|' . $file : $function;

		// Is it going to be permanent?
		if ($permanent)
		{
			$this->_store($hook, $integration_call);
		}

		// Make current function list usable.
		$functions = empty($modSettings[$hook]) ? [] : explode(',', $modSettings[$hook]);

		// Do nothing, if it's already there.
		if (in_array($integration_call, $functions, true))
		{
			return;
		}

		$functions[] = $integration_call;
		$modSettings[$hook] = implode(',', $functions);
	}

	/**
	 * Registers non ACP integration hooks for enabled integrations
	 *
	 * What it does
	 * - reads enabled integrations (those identified in autoload_integrate modsettings)
	 * - verify the class exists
	 * - verify it has a static method ::register, if so calls that method and adds any hooks
	 * returned by that method
	 */
	public function loadIntegrations()
	{
		$enabled = $this->_get_enabled_integrations();

		foreach ($enabled as $class)
		{
			if (class_exists($class) && in_array('register', get_class_methods($class)))
			{
				$hooks = $class::register();

				if (empty($hooks))
				{
					continue;
				}

				foreach ($hooks as $hook)
				{
					$this->add($hook[0], $hook[1], $hook[2] ?? '', false);
				}
			}
		}
	}

	/**
	 * Loads ACP settings for enabled integrations
	 *
	 * What it does
	 * - reads enabled integrations (those identified in autoload_integrate modsettings)
	 * - verify the class exists
	 * - verify it has a static method ::settingsRegister, if so calls that method and adds any hooks
	 * returned by that method
	 */
	public function loadIntegrationsSettings()
	{
		$enabled = $this->_get_enabled_integrations();

		foreach ($enabled as $class)
		{
			if (class_exists($class) && in_array('settingsRegister', get_class_methods($class)))
			{
				$hooks = $class::settingsRegister();

				if (empty($hooks))
				{
					continue;
				}

				foreach ($hooks as $hook)
				{
					$this->add($hook[0], $hook[1], $hook[2] ?? '', false);
				}
			}
		}
	}

	/**
	 * Find all integration files (default is *.integrate.php) in the supplied directory
	 *
	 * What it does:
	 *
	 * - Searches the ADDONSDIR (and below) (by default) for xxxx.integrate.php files
	 * - Will use a composer.json (optional) to load basic information about the addon
	 * - Will set the call name as xxx_Integrate
	 *
	 * @param string $basepath
	 * @param string $ext
	 *
	 * @return array
	 */
	public function discoverIntegrations($basepath, $ext = '.integrate.php')
	{
		$path = $basepath . '/*/*' . $ext;
		$names = [];

		$glob = new GlobIterator($path, FilesystemIterator::SKIP_DOTS);

		// Find all integration files
		foreach ($glob as $file)
		{
			$name = str_replace($ext, '', $file->getBasename());
			$composer_file = $file->getPath() . '/composer.json';

			// Already have the integration compose file, then use it, otherwise create one
			if ($this->fileFunc->fileExists($composer_file))
			{
				$composer_data = json_decode(file_get_contents($composer_file));
			}
			else
			{
				$composer_data = json_decode('{
    "name": "' . $name . '",
    "description": "' . $name . '",
    "version": "1.0.0",
    "type": "addon",
    "homepage": "https://www.elkarte.net",
    "time": "",
    "license": "",
    "authors": [
        {
            "name": "Unknown",
            "email": "notprovided",
            "homepage": "https://www.elkarte.net",
            "role": "Developer"
        }
    ],
    "support": {
        "email": "",
        "issues": "https://www.elkarte.net/community",
        "forum": "https://www.elkarte.net/community",
        "wiki": "",
        "irc": "",
        "source": ""
    },
    "require": {
        "elkarte/elkarte": "' . substr(FORUM_VERSION, 0, -5) . '"
    },
    "repositories": [
        {
            "type": "composer",
            "url": "https://packages.example.com"
        }
    ],
    "extra": {
        "setting_url": ""
    }
}');
			}

			$names[] = [
				'id' => $name,
				'class' => str_replace('.integrate.php', '_Integrate', $file->getBasename()),
				'title' => $composer_data->name,
				'description' => $composer_data->description,
				'path' => str_replace($basepath, '', $file->getPathname()),
				'details' => $composer_data,
			];
		}

		return $names;
	}

	/**
	 * Enables the autoloading of a certain addon.
	 *
	 * @param string $call A string consisting of "path/filenameIntegrate.php"
	 */
	public function enableIntegration($call)
	{
		$existing = $this->_get_enabled_integrations();

		$existing[] = $call;

		$this->_store_autoload_integrate($existing);
	}

	/**
	 * Disables the autoloading of a certain addon.
	 *
	 * @param string $call A string consisting of "path/filename.integrate.php"
	 */
	public function disableIntegration($call)
	{
		$existing = $this->_get_enabled_integrations();

		$existing = array_diff($existing, (array) $call);

		$this->_store_autoload_integrate($existing);
	}

	/**
	 * Retrieves from the database a set of references to files containing addons.
	 *
	 * @return string[] An array of strings consisting of "path/filenameIntegrate.php"
	 */
	protected function _get_enabled_integrations()
	{
		global $modSettings;

		return empty($modSettings['autoload_integrate']) ? [] : explode(',', $modSettings['autoload_integrate']);
	}

	/**
	 * Saves into the database a set of references to files containing addons.
	 *
	 * @param string[] $existing An array of strings consisting of "path/filenameIntegrate.php"
	 */
	protected function _store_autoload_integrate($existing)
	{
		$existing = array_filter(array_unique($existing));
		updateSettings(['autoload_integrate' => implode(',', $existing)]);
	}

	/**
	 * Stores a function into the database.
	 *
	 * - does nothing if the function is already added.
	 *
	 * @param string $hook
	 * @param string $integration_call
	 */
	protected function _store($hook, $integration_call)
	{
		$request = $this->_db->query('', '
			SELECT 
				value
			FROM {db_prefix}settings
			WHERE variable = {string:variable}',
			[
				'variable' => $hook,
			]
		);
		[$current_functions] = $request->fetch_row();
		$request->free_result();

		if (!empty($current_functions))
		{
			$current_functions = explode(',', $current_functions);
			if (in_array($integration_call, $current_functions))
			{
				return;
			}

			$permanent_functions = array_merge($current_functions, [$integration_call]);
		}
		else
		{
			$permanent_functions = [$integration_call];
		}

		updateSettings([$hook => implode(',', $permanent_functions)]);
	}

	/**
	 * Remove an integration hook function.
	 *
	 * What it does:
	 *
	 * - Removes the given function from the given hook.
	 * - Does nothing if the function is not available.
	 *
	 * @param string $hook
	 * @param string $function
	 * @param string $file
	 */
	public function remove($hook, $function, $file = '')
	{
		global $modSettings;

		$integration_call = (!empty($file) && $file !== true) ? $function . '|' . $file : $function;

		// Get the permanent functions.
		$request = $this->_db->query('', '
			SELECT 
				value
			FROM {db_prefix}settings
			WHERE variable = {string:variable}',
			[
				'variable' => $hook,
			]
		);
		[$current_functions] = $request->fetch_row();
		$request->free_result();

		// If we found entries for this hook
		if (!empty($current_functions))
		{
			$current_functions = explode(',', $current_functions);

			if (in_array($integration_call, $current_functions))
			{
				updateSettings([$hook => implode(',', array_diff($current_functions, [$integration_call]))]);
				if (empty($modSettings[$hook]))
				{
					removeSettings($hook);
				}
			}
		}

		// Turn the function list into something usable.
		$functions = empty($modSettings[$hook]) ? [] : explode(',', $modSettings[$hook]);

		// You can only remove it if it's available.
		if (!in_array($integration_call, $functions))
		{
			return;
		}

		$functions = array_diff($functions, [$integration_call]);
		$modSettings[$hook] = implode(',', $functions);
	}

	/**
	 * Instantiation is a bit more complex, so let's give it a custom function
	 *
	 * @param QueryInterface|null $db A database connection
	 * @param Debug|null $debug A class for debugging
	 * @param string[]|null $paths An array of paths for replacement
	 */
	public static function init($db = null, $debug = null, $paths = null)
	{
		if ($db === null)
		{
			$db = database();
		}

		if ($debug === null)
		{
			$debug = Debug::instance();
		}

		self::$_instance = new Hooks($db, $debug, $paths);
	}

	/**
	 * Being a singleton, this is the static method to retrieve the instance of the class
	 *
	 * @param QueryInterface|null $db A database connection
	 * @param Debug|null $debug A class for debugging
	 * @param string[]|null $paths An array of paths for replacement
	 *
	 * @return Hooks An instance of the class.
	 */
	public static function instance($db = null, $debug = null, $paths = null)
	{
		if (self::$_instance === null)
		{
			self::init($db, $debug, $paths);
		}
		elseif ($paths !== null)
		{
			self::$_instance->newPath($paths);
		}

		return self::$_instance;
	}
}
