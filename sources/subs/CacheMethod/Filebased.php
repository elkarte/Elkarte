<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

use FilesystemIterator;
use UnexpectedValueException;

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
	 * {@inheritdoc}
	 */
	protected $title = 'File-based caching';

	/**
	 * {@inheritdoc}
	 */
	protected $prefix = 'data_';

	/**
	 * File extension.
	 *
	 * @var string
	 */
	protected $ext = 'php';

	/**
	 * Obtain from the parent class the variables necessary
	 * to help the tests stay running smoothly.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function getFileName($key)
	{
		return $this->prefix . '_' . $key . '.' . $this->ext;
	}

	/**
	 * {@inheritdoc}
	 */
	public function exists($key)
	{
		return file_exists(CACHEDIR . '/' . $this->getFileName($key));
	}

	/**
	 * {@inheritdoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
		$fName = $this->getFileName($key);

		// Clearing this data
		if ($value === null)
		{
			@unlink(CACHEDIR . '/' . $fName);
		}
		// Or stashing it away
		else
		{
			$cache_data = "<?php '" . json_encode(array('expiration' => time() + $ttl, 'data' => $value)) . "';";

			// Write out the cache file, check that the cache write was successful; all the data must be written
			// If it fails due to low diskspace, or other, remove the cache file
			if (@file_put_contents(CACHEDIR . '/' . $fName, $cache_data, LOCK_EX) !== strlen($cache_data))
			{
				@unlink(CACHEDIR . '/' . $fName);
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($key, $ttl = 120)
	{
		$return = null;
		$fName = $this->getFileName($key);

		if (file_exists(CACHEDIR . '/' . $fName))
		{
			if (filesize(CACHEDIR . '/' . $fName) > 10)
			{
				$value = json_decode(substr(file_get_contents(CACHEDIR . '/' . $fName), 7, -2));

				if ($value->expiration < time())
				{
					@unlink(CACHEDIR . '/' . $fName);
					$return = null;
				}
				else
				{
					$return = $value->data;
				}

				unset($value);
				$this->is_miss = $return === null;

				return $return;
			}
		}

		$this->is_miss = true;

		return $return;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clean($type = '')
	{
		try
		{
			$files = new FilesystemIterator(CACHEDIR, FilesystemIterator::SKIP_DOTS);

			foreach ($files as $file)
			{
				if ($file->getFileName() !== 'index.php' && $file->getFileName() !== '.htaccess' && $file->getExtension() === $this->ext)
				{
					@unlink($file->getPathname());
				}
			}
		}
		catch (UnexpectedValueException $e)
		{
			// @todo
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function fixkey($key)
	{
		return strtr($key, ':/', '-_');
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable()
	{
		return @is_dir(CACHEDIR) && @is_writable(CACHEDIR);
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{
		return array('title' => $this->title, 'version' => 'N/A');
	}

	/**
	 * Adds the settings to the settings page.
	 *
	 * Used by integrate_modify_cache_settings added in the title method
	 *
	 * @param array $config_vars
	 */
	public function settings(&$config_vars)
	{
		global $txt;

		$config_vars[] = array('cachedir', $txt['cachedir'], 'file', 'text', 36, 'cache_cachedir', 'force_div_id' => 'filebased_cachedir');
	}
}
