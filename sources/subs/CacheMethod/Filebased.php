<?php
/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

use FilesystemIterator;
use UnexpectedValueException;

if (!defined('ELK'))
	die('No access...');

/**
 * Filebased caching is the fallback if nothing else is available, it simply
 * uses the filesystem to store queries results in order to try to reduce the
 * number of queries per time period.
 *
 * The performance gain may or may not exist depending on many factors.
 *
 * It requires the CACHEDIR constant to be defined and pointing to a
 * writable directory.
 */
class Filebased extends Cache_Method_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	public function init()
	{
		return @is_dir(CACHEDIR) && @is_writable(CACHEDIR);
	}

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		// Clearing this data
		if ($value === null)
			@unlink(CACHEDIR . '/data_' . $key . '.php');
		// Or stashing it away
		else
		{
			$cache_data = '<?php if (!defined(\'ELK\')) die; if (' . (time() + $ttl) . ' < time()) return false; else{return \'' . addcslashes($value, '\\\'') . '\';}';

			// Write out the cache file, check that the cache write was successful; all the data must be written
			// If it fails due to low diskspace, or other, remove the cache file
			if (@file_put_contents(CACHEDIR . '/data_' . $key . '.php', $cache_data, LOCK_EX) !== strlen($cache_data))
				@unlink(CACHEDIR . '/data_' . $key . '.php');
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		// Otherwise it's ElkArte data!
		if (file_exists(CACHEDIR . '/data_' . $key . '.php') && filesize(CACHEDIR . '/data_' . $key . '.php') > 10)
		{
			// php will cache file_exists et all, we can't 100% depend on its results so proceed with caution
			$value = @include(CACHEDIR . '/data_' . $key . '.php');
			if ($value === false)
			{
				@unlink(CACHEDIR . '/data_' . $key . '.php');
				$return = null;
			}
			else
				$return = $value;

			unset($value);

			return $return;
		}

		return null;
	}

	/**
	 * {@inheritdoc }
	 */
	public function clean($type = '')
	{
		// To be complete, we also clear out the cache dir so we get any js/css hive files
		// Remove the cache files in our disk cache directory
		try
		{
			$files = new FilesystemIterator(CACHEDIR, FilesystemIterator::SKIP_DOTS);

			foreach ($files as $file)
			{
				if ($file !== 'index.php' && $file !== '.htaccess' && (!$type || $file->getExtension() == $type))
					@unlink($file->getPathname());
			}
		}
		catch (UnexpectedValueException $e)
		{
			// @todo
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public function fixkey($key)
	{
		return strtr($key, ':/', '-_');
	}

	/**
	 * {@inheritdoc }
	 */
	public static function available()
	{
		return @is_dir(CACHEDIR) && @is_writable(CACHEDIR);
	}

	/**
	 * {@inheritdoc }
	 */
	public static function details()
	{
		return array('title' => self::title(), 'version' => 'N/A');
	}

	/**
	 * {@inheritdoc }
	 */
	public static function title()
	{
		if (self::available())
			add_integration_function('integrate_modify_cache_settings', 'Filebased_Cache::settings', '', false);

		return 'File-based caching';
	}

	/**
	 * Adds the settings to the settings page.
	 *
	 * Used by integrate_modify_cache_settings added in the title method
	 */
	public static function settings(&$config_vars)
	{
		global $txt;

		$config_vars[] = array('cachedir', $txt['cachedir'], 'file', 'text', 36, 'cache_cachedir', 'force_div_id' => 'filebased_cachedir');
	}
}