<?php

/**
 * Takes the actions defined in the package info xml file and then tests it for actionability and
 * if its passes will perform those actions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Packages;

use ElkArte\FileFunctions;

class PackageParser
{
	/** @var \ElkArte\FileFunctions */
	public $fileFunc;
	/** @var array the results of our efforts */
	protected $_return;
	/** @var array the parsed results of the package info */
	protected $action;
	/** @var int simple temporary file counter */
	protected $temp_auto = 0;
	/** @var bool If the package has a redirect or if to use the default */
	protected $has_redirect = false;
	/** @var bool that should be obvious */
	protected $not_done;
	/** @var bool the name of this class :P */
	protected $failure = false;

	/**
	 * Parses the actions in package-info.xml file from packages.
	 *
	 * What it does:
	 *
	 * - Package should be an \ElkArte\XmlArray with package-info as its base.
	 * - Testing_only should be true if the package should not actually be applied.
	 * - Method can be upgrade, install, or uninstall.  Its default is install.
	 * - Previous_version should be set to the previous installed version of this package, if any.
	 * - Does not handle failure terribly well; testing first is always better.
	 *
	 * @param \ElkArte\XmlArray $packageXML
	 * @param bool $testing_only = true
	 * @param string $method = 'install' ('install', 'upgrade', or 'uninstall')
	 * @param string $previous_version = ''
	 * @return array an array of those changes made.
	 * @package Packages
	 */
	public function parsePackageInfo($packageXML, $testing_only = true, $method = 'install', $previous_version = '')
	{
		global $context, $temp_path;

		// Mayday!  That action doesn't exist!!
		if (empty($packageXML) || !$packageXML->exists($method))
		{
			return [];
		}

		// Check on if emulation is enabled or needed
		$the_version = strtr(FORUM_VERSION, array('ElkArte ' => ''));
		$the_version = $this->setEmulation($the_version);

		// Get all the versions of this method (install, uninstall, upgrade) and find the right one.
		$script = $this->setMethod($packageXML, $the_version, $method, $previous_version);

		// Bad news, a matching script wasn't found!
		if ($script === false)
		{
			return [];
		}

		// Find all the actions in this method - in theory, these should only be allowed actions. (* means all.)
		$actions = $script->set('*');

		// Prepare for the script actions
		$this->_return = [];
		$temp_path = BOARDDIR . '/packages/temp/' . ($context['base_path'] ?? '');
		$context['readmes'] = [];
		$context['licences'] = [];

		// We work hard with the file system
		$this->fileFunc = FileFunctions::instance();

		// This is the testing phase... nothing shall be done yet.
		$this->testingPhase($actions);

		// Do we have to supply a redirect action or was one given
		$this->setRedirect();

		// Housekeeping
		$this->_return = array_filter($this->_return);

		// Only testing - just return a list of things to be done.
		if ($testing_only)
		{
			return $this->_return;
		}

		// This is the installation phase, where we try not to brick the forum
		$this->not_done = array(['type' => '!']);
		$this->installPhase();

		return $this->not_done;
	}

	/**
	 * Tests all actions to ensure there are no identifiable problems
	 *
	 * @param \ElkArte\XmlArray $actions
	 * @return void
	 */
	public function testingPhase($actions)
	{
		foreach ($actions as $action)
		{
			$actionType = $action->name();

			if (in_array($actionType, ['readme', 'code', 'database', 'modification', 'redirect', 'license']))
			{
				if (in_array($actionType, ['readme', 'license']) && !$this->_translation($actionType, $action))
				{
					continue;
				}

				$this->_return[] = $this->testText($actionType, $action);
			}
			elseif (in_array($actionType, ['hook', 'credits', 'requires', 'error']))
			{
				$this->_return[] = call_user_func(array($this, 'test' . ucfirst($actionType)), $actionType, $action);
			}
			elseif (in_array($actionType, ['require-file', 'remove-file', 'move-file', 'create-file',
										   'require-dir', 'remove-dir', 'move-dir', 'create-dir']))
			{
				$this_action = $this->_testFileDir($actionType, $action);

				$method = str_replace('-', '', ucfirst($actionType));
				$this->_return[] = call_user_func(array($this, 'test' . $method), $action, $this_action);
			}
			else
			{
				$this->_return[] = $this->testTheRest($actionType, $action);
			}
		}
	}

	/**
	 * Performs the actions specified by the chosen script
	 *
	 * @return void
	 */
	public function installPhase()
	{
		foreach ($this->_return as $action)
		{
			if (in_array($action['type'], ['modification', 'code', 'database', 'redirect', 'hook', 'credits']))
			{
				$this->not_done[] = $action;
			}
			elseif (in_array($action['type'], ['require-file', 'remove-file', 'move-file', 'create-file',
											   'require-dir', 'remove-dir', 'move-dir', 'create-dir']))
			{
				$method = str_replace('-', '', ucfirst($action['type']));
				$this->_return[] = call_user_func(array($this, 'install' . $method), $action);
			}
		}
	}

	/**
	 * Move a required file in the right location
	 *
	 * @param array $action
	 * @return void
	 */
	public function installRequirefile($action)
	{
		global $context;

		if (!mktree(dirname($action['destination']))
			|| !$this->fileFunc->isWritable(dirname($action['destination'])))
		{
			$this->failure |= true;
		}

		package_put_contents($action['destination'], package_get_contents($action['source']));

		$this->failure |= !copy($action['source'], $action['destination']);

		// Any other theme files?
		if (!empty($context['theme_copies'])
			&& !empty($context['theme_copies'][$action['type']][$action['destination']]))
		{
			foreach ($context['theme_copies'][$action['type']][$action['destination']] as $theme_destination)
			{
				if (!mktree(dirname($theme_destination))
					|| !$this->fileFunc->isWritable(dirname($theme_destination)))
				{
					$this->failure |= true;
				}

				package_put_contents($theme_destination, package_get_contents($action['source']));

				$this->failure |= !copy($action['source'], $theme_destination);
			}
		}
	}

	/**
	 * Removes a file, normally part of uninstall method
	 *
	 * @param $action
	 * @return void
	 */
	public function installRemovefile($action)
	{
		global $context;

		// Make sure the file exists before deleting it.
		if ($this->fileFunc->fileExists($action['filename']))
		{
			package_chmod($action['filename']);
			$this->failure |= !$this->fileFunc->delete($action['filename']);
		}
		// The file that was supposed to be deleted couldn't be found.
		else
		{
			$this->failure = true;
		}

		// Any other theme folders?
		if (!empty($context['theme_copies']) && !empty($context['theme_copies'][$action['type']][$action['filename']]))
		{
			foreach ($context['theme_copies'][$action['type']][$action['filename']] as $theme_destination)
			{
				if ($this->fileFunc->fileExists($theme_destination))
				{
					$this->failure |= !$this->fileFunc->delete($theme_destination);
				}
				else
				{
					$this->failure = true;
				}
			}
		}
	}

	/**
	 * Move a file to a specifed location
	 *
	 * @param array $action
	 * @return void
	 */
	public function installMovefile($action)
	{
		$this->_setDestination(dirname($action['destination']));

		$this->failure |= !rename($action['source'], $action['destination']);
	}

	/**
	 * Create a new file in a specified location
	 *
	 * @param $action
	 * @return void
	 */
	public function installCreatefile($action)
	{
		$this->_setDestination(dirname($action['destination']));

		// Create an empty file.
		package_put_contents($action['destination'], '');

		if (!$this->fileFunc->fileExists($action['destination']))
		{
			$this->failure = true;
		}
	}

	/**
	 * Require a directory in a specified location
	 *
	 * @param $action
	 * @return void
	 */
	public function installRequiredir($action)
	{
		copytree($action['source'], $action['destination']);

		// Any other theme folders?
		if (!empty($context['theme_copies'])
			&& !empty($context['theme_copies'][$action['type']][$action['destination']]))
		{
			foreach ($context['theme_copies'][$action['type']][$action['destination']] as $theme_destination)
			{
				copytree($action['source'], $theme_destination);
			}
		}
	}

	/**
	 * Remove a directory tree
	 *
	 * @param $action
	 * @return void
	 */
	public function installRemovedir($action)
	{
		deltree($action['filename']);

		// Any other theme folders?
		if (!empty($context['theme_copies'])
			&& !empty($context['theme_copies'][$action['type']][$action['filename']]))
		{
			foreach ($context['theme_copies'][$action['type']][$action['filename']] as $theme_destination)
			{
				deltree($theme_destination);
			}
		}
	}

	/**
	 * Create a new directory in a specified location
	 *
	 * @param $action
	 * @return void
	 */
	public function installCreatedir($action)
	{
		$this->_setDestination($action['destination']);
	}

	/**
	 * Move a directory to a specified location
	 *
	 * @param $action
	 * @return void
	 */
	public function installMovedir($action)
	{
		$this->_setDestination($action['destination']);

		$this->failure |= !rename($action['source'], $action['destination']);
	}

	/**
	 * Handles text actions, including determine language support and bbc support
	 *
	 * @param string $actionType
	 * @param object $action
	 * @return array
	 */
	public function testText($actionType, $action)
	{
		global $language, $temp_path;

		if ($actionType === 'redirect')
		{
			$this->has_redirect = true;
		}

		// @todo Make sure the file actually exists?  Might not work when testing?
		if ($action->exists('@type') && $action->fetch('@type') === 'inline')
		{
			$filename = $temp_path . '$auto_' . $this->temp_auto++ . (in_array($actionType, ['readme', 'redirect', 'license']) ? '.txt' : ($actionType === 'code' || $actionType === 'database' ? '.php' : '.mod'));
			package_put_contents($filename, $action->fetch('.'));
			$filename = strtr($filename, array($temp_path => ''));
		}
		else
		{
			$filename = $action->fetch('.');
		}

		return [
			'type' => $actionType,
			'filename' => $filename,
			'description' => '',
			'reverse' => $action->exists('@reverse') && $action->fetch('@reverse') === 'true',
			'redirect_url' => $action->exists('@url') ? $action->fetch('@url') : '',
			'redirect_timeout' => $action->exists('@timeout') ? (int) $action->fetch('@timeout') : 5000,
			'parse_bbc' => $action->exists('@parsebbc') && $action->fetch('@parsebbc') === 'true',
			'language' => (($actionType === 'readme' || $actionType === 'license')
				&& $action->exists('@lang')
				&& $action->fetch('@lang') === $language) ? $language : '',
		];
	}

	/**
	 * Sets hook values
	 *
	 * @param string $actionType
	 * @param object $action
	 * @return array
	 */
	public function testHook($actionType, $action)
	{
		return [
			'type' => $actionType,
			'function' => $action->exists('@function') ? $action->fetch('@function') : '',
			'hook' => $action->exists('@hook') ? $action->fetch('@hook') : $action->fetch('.'),
			'include_file' => $action->exists('@file') ? $action->fetch('@file') : '',
			'reverse' => $action->exists('@reverse') && $action->fetch('@reverse') == 'true',
			'description' => '',
		];
	}

	/**
	 * Sets credit values
	 *
	 * @param string $actionType
	 * @param object $action
	 * @return array
	 */
	public function testCredits($actionType, $action)
	{
		// Quick check of any supplied url
		$url = $action->exists('@url') ? $action->fetch('@url') : '';
		if (strlen(trim($url)) > 0)
		{
			$url = addProtocol($url, array('http://', 'https://'));
			if (strlen($url) < 8)
			{
				$url = '';
			}
		}

		return [
			'type' => $actionType,
			'url' => $url,
			'license' => $action->exists('@license') ? $action->fetch('@license') : '',
			'copyright' => $action->exists('@copyright') ? $action->fetch('@copyright') : '',
			'title' => $action->fetch('.'),
		];

	}

	/**
	 * Sets required values
	 *
	 * @param string $actionType
	 * @param object $action
	 * @return array
	 */
	public function testRequires($actionType, $action)
	{
		return [
			'type' => $actionType,
			'id' => $action->exists('@id') ? $action->fetch('@id') : '',
			'version' => $action->exists('@version') ? $action->fetch('@version') : $action->fetch('.'),
			'description' => '',
		];
	}

	/**
	 * Sets error?
	 *
	 * @param string $actionType
	 * @param object $action
	 * @return array
	 */
	public function testError($actionType, $action)
	{
		return array(
			'type' => 'error',
		);
	}

	/**
	 * Sets create file values, checks if chmod control will be needed
	 *
	 * @param object $action
	 * @param array $this_action
	 * @return array
	 */
	public function testCreatedir($action, $this_action)
	{
		// See if the destination is writable
		if (!dirTest($this_action['destination']))
		{
			$temp = $this_action['destination'];

			return [
				'type' => 'chmod',
				'filename' => $this->_getRoot($temp)
			];
		}

		return [];
	}

	/**
	 * Sets create file values, checks if chmod control will be needed
	 *
	 * @param object $action
	 * @param array $this_action
	 * @return array
	 */
	public function testCreatefile($action, $this_action)
	{
		// Can we create a file in a known location
		if (!dirTest(dirname($this_action['destination'])))
		{
			$temp = dirname($this_action['destination']);

			return [
				'type' => 'chmod',
				'filename' => $this->_getRoot($temp)
			];
		}

		if (!$this->fileFunc->isWritable($this_action['destination'])
			&& ($this->fileFunc->fileExists($this_action['destination']) || !$this->fileFunc->isWritable(dirname($this_action['destination']))))
		{
			return [
				'type' => 'chmod',
				'filename' => $this_action['destination']
			];
		}

		return [];
	}

	/**
	 * Sets required dir values, checks if chmod control will be needed
	 *
	 * @param object $action
	 * @param array $this_action
	 * @return array
	 */
	public function testRequiredir($action, $this_action)
	{
		if (!dirTest($this_action['destination']))
		{
			$temp = $this_action['destination'];

			return [
				'type' => 'chmod',
				'filename' => $this->_getRoot($temp)
			];
		}

		return [];
	}

	/**
	 * Sets required file values, checks if chmod control will be needed
	 *
	 * @param object $action
	 * @param array $this_action
	 * @return array
	 */
	public function testRequirefile($action, $this_action)
	{
		if ($action->exists('@theme'))
		{
			$this_action['theme_action'] = $action->fetch('@theme');
		}

		return $this->_getChmod($this_action);
	}

	/**
	 * Sets move dir values, checks if chmod control will be needed
	 *
	 * @param object $action
	 * @param array $this_action
	 * @return array
	 */
	public function testMovedir($action, $this_action)
	{
		return $this->_getChmod($this_action);
	}

	/**
	 * Sets move file values, checks if chmod control will be needed
	 *
	 * @param object $action
	 * @param array $this_action
	 * @return array
	 */
	public function testMovefile($action, $this_action)
	{
		return $this->_getChmod($this_action);
	}

	/**
	 * Sets remove dir values, checks if chmod control will be needed
	 *
	 * @param object $action
	 * @param array $this_action
	 * @return array
	 */
	public function testRemovedir($action, $this_action)
	{
		if (!$this->fileFunc->isWritable($this_action['filename'])
			&& $this->fileFunc->isDir($this_action['filename']))
		{
			return [
				'type' => 'chmod',
				'filename' => $this_action['filename']
			];
		}

		return [];
	}

	/**
	 * Sets remove file values, checks if chmod control will be needed
	 *
	 * @param object $action
	 * @param array $this_action
	 * @return array
	 */
	public function testRemovefile($action, $this_action)
	{
		if (!$this->fileFunc->isWritable($this_action['filename'])
			&& $this->fileFunc->fileExists($this_action['filename']))
		{
			return [
				'type' => 'chmod',
				'filename' => $this_action['filename']
			];
		}

		return [];
	}

	/**
	 * The rest are not known!
	 *
	 * @param string $actionType
	 * @param array $action
	 * @return array
	 */
	public function testTheRest($actionType, $action)
	{
		return [
			'type' => 'error',
			'error_msg' => 'unknown_action',
			'error_var' => $actionType
		];
	}

	/**
	 * Helper to the parent directory
	 *
	 * @param string $temp
	 * @return string
	 */
	private function _getRoot($temp)
	{
		while (!$this->fileFunc->isDir($temp) && strlen($temp) > 1)
		{
			$temp = dirname($temp);
		}

		return $temp;
	}

	/**
	 * Helper to determine if a chmod control will be required
	 *
	 * @param $this_action
	 * @return array
	 */
	private function _getChmod($this_action)
	{
		if (!dirTest(dirname($this_action['destination'])))
		{
			$temp = dirname($this_action['destination']);

			return [
				'type' => 'chmod',
				'filename' =>$this->_getRoot($temp)
			];
		}

		if (!$this->fileFunc->isWritable($this_action['destination'])
			&& ($this->fileFunc->isDir($this_action['destination']) || !$this->fileFunc->isWritable(dirname($this_action['destination']))))
		{
			return [
				'type' => 'chmod',
				'filename' => $this_action['destination']
			];
		}

		return [];
	}

	/**
	 * Setup for all file/dir actions
	 *
	 * @param string $actionType
	 * @param array $action
	 * @return array
	 */
	private function _testFileDir($actionType, $action)
	{
		global $temp_path;

		// Save the pointer to this entry for use in test Create.../Require.../Move...
		$this_action = &$this->_return[];
		$this_action = array(
			'type' => $actionType,
			'filename' => $action->fetch('@name'),
			'description' => $action->fetch('.')
		);

		// If there is a destination, make sure it makes sense.
		if (substr($actionType, 0, 6) !== 'remove')
		{
			$this_action['unparsed_destination'] = $action->fetch('@destination');
			$this_action['destination'] = parse_path($action->fetch('@destination')) . '/' . basename($this_action['filename']);
		}
		else
		{
			$this_action['unparsed_filename'] = $this_action['filename'];
			$this_action['filename'] = parse_path($this_action['filename']);
		}

		// If we're moving or requiring (copying) a file.
		if (substr($actionType, 0, 4) === 'move' || substr($actionType, 0, 7) === 'require')
		{
			if ($action->exists('@from'))
			{
				$this_action['source'] = parse_path($action->fetch('@from'));
			}
			else
			{
				$this_action['source'] = $temp_path . $this_action['filename'];
			}
		}

		return $this_action;
	}

	/**
	 * Helper function to create a specified location
	 *
	 * @param string $area
	 * @return void
	 */
	private function _setDestination($area)
	{
		if (!mktree($area) || !$this->fileFunc->isWritable($area))
		{
			$this->failure |= true;
		}
	}

	/**
	 * Allow for translated readme and license files.
	 *
	 * @return boolean
	 */
	private function _translation($actionType, $action)
	{
		global $context, $language;

		$type = $actionType . 's';
		if ($action->exists('@lang'))
		{
			// Auto-select the language based on either request variable or current language.
			if ((isset($_REQUEST['readme']) && $action->fetch('@lang') === $_REQUEST['readme'])
				|| (isset($_REQUEST['license']) && $action->fetch('@lang') === $_REQUEST['license'])
				|| (!isset($_REQUEST['readme']) && $action->fetch('@lang') === $language)
				|| (!isset($_REQUEST['license']) && $action->fetch('@lang') === $language))
			{
				// In case the user put the blocks in the wrong order.
				if (isset($context[$type]['selected']) && $context[$type]['selected'] === 'default')
				{
					$context[$type][] = 'default';
				}

				$context[$type]['selected'] = htmlspecialchars($action->fetch('@lang'), ENT_COMPAT);
			}
			else
			{
				// We don't want this now, but we'll allow the user to select to read it.
				$context[$type][] = htmlspecialchars($action->fetch('@lang'), ENT_COMPAT);

				return false;
			}
		}
		// Fallback when we have no lang parameter.
		elseif (isset($context[$type]['selected']))
		{
			// Already selected one for use?
			$context[$type][] = 'default';

			return false;
		}
		else
		{
			$context[$type]['selected'] = 'default';
		}

		return true;
	}

	/**
	 * Sets a default redirect back to package manager page if no redirect
	 * is specified in the package.info
	 *
	 * @return void
	 */
	private function setRedirect()
	{
		if (!$this->has_redirect)
		{
			$this->_return[] = [
				'type' => 'redirect',
				'filename' => '',
				'description' => '',
				'reverse' => false,
				'redirect_url' => '$scripturl?action=admin;area=packages',
				'redirect_timeout' => 5000,
				'parse_bbc' => false,
				'language' => '',
			];
		}
	}

	/**
	 * Sets up for an emulation, i.e., misinform about the version we are running
	 *
	 * @param string $the_version
	 * @return string
	 */
	private function setEmulation($the_version)
	{
		// Emulation support...
		if (!empty($_SESSION['version_emulate']))
		{
			$the_version = $_SESSION['version_emulate'];
		}

		// Single package emulation
		if (!empty($_REQUEST['ve']) && !empty($_REQUEST['package']))
		{
			$the_version = $_REQUEST['ve'];
			$_SESSION['single_version_emulate'][$_REQUEST['package']] = $the_version;
		}

		if (!empty($_REQUEST['package']) && (!empty($_SESSION['single_version_emulate'][$_REQUEST['package']])))
		{
			$the_version = $_SESSION['single_version_emulate'][$_REQUEST['package']];
		}

		return $the_version;
	}

	/**
	 * Get the right method (install, upgrade, uninstall) for this version of software
	 *
	 * @param \ElkArte\XmlArray $packageXML
	 * @param string $the_version
	 * @param string $method
	 * @param string $previous_version
	 * @return false|mixed
	 */
	private function setMethod($packageXML, $the_version, $method, $previous_version)
	{
		$script = false;

		// All the possible methods, like install for 1.1.1-1.1.99 etc
		$these_methods = $packageXML->set($method);

		// See if one of the methods matches our version, so we know which script to follow
		foreach ($these_methods as $this_method)
		{
			// They specified certain versions this part is for.
			if ($this_method->exists('@for'))
			{
				// Don't keep going if this won't work for this version.
				if (!matchPackageVersion($the_version, $this_method->fetch('@for')))
				{
					continue;
				}
			}

			// Upgrades may go from a certain old version of the addon.
			if ($method === 'upgrade' && $this_method->exists('@from'))
			{
				// Well, this is for the wrong old version...
				if (!matchPackageVersion($previous_version, $this_method->fetch('@from')))
				{
					continue;
				}
			}

			// We've found it!
			$script = $this_method;
			break;
		}

		return $script;
	}
}