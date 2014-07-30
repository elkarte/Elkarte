<?php

class Filebased_Cache extends Cache_Method_Abstract
{
	public function init()
	{
		return @is_dir(CACHEDIR) && @is_writable(CACHEDIR);
	}

	public function put($key, $value, $ttl)
	{
		// Otherwise custom cache?
		if ($value === null)
			@unlink(CACHEDIR . '/data_' . $key . '.php');
		else
		{
			$cache_data = '<?php if (!defined(\'ELK\')) die; if (' . (time() + $ttl) . ' < time()) return false; else{return $value = \'' . addcslashes($value, '\\\'') . '\';}';

			// Write out the cache file, check that the cache write was successful; all the data must be written
			// If it fails due to low diskspace, or other, remove the cache file
			if (@file_put_contents(CACHEDIR . '/data_' . $key . '.php', $cache_data, LOCK_EX) !== strlen($cache_data))
				@unlink(CACHEDIR . '/data_' . $key . '.php');
		}
	}

	public function get($key, $ttl)
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
	}

	public function clean($type)
	{
		// To be complete, we also clear out the cache dir so we get any js/css hive files
		// Remove the cache files in our disk cache directory
		$dh = opendir(CACHEDIR);
		while ($file = readdir($dh))
		{
			if ($file != '.' && $file != '..' && $file != 'index.php' && $file != '.htaccess' && (!$type || substr($file, 0, strlen($type)) == $type))
				@unlink(CACHEDIR . '/' . $file);
		}

		closedir($dh);
	}

	public function fixkey($key)
	{
		return strtr($key, ':/', '-_');
	}
}