<?php

/**
 * This class provides many common file and directory functions such as creating directories, checking existence etc.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use \Exception;

class FileFunctions
{
	/** @var \ElkArte\FileFunctions The instance of the class */
	private static $_instance = null;

	/**
	 * chmod control will attempt to make a file or directory writable
	 *
	 * - Progressively attempts various chmod values until item is writable or failure
	 *
	 * @param string $item file or directory
	 * @return bool
	 */
	public function chmod($item)
	{
		$fileChmod = [0644, 0666];
		$dirChmod = [0755, 0775, 0777];

		// Already writable?
		if ($this->isWritable($item))
		{
			return true;
		}

		$modes = $this->isDir($item) ? $dirChmod : $fileChmod;
		foreach ($modes as $mode)
		{
			if ($this->isWritable($item))
			{
				return true;
			}

			$this->elk_chmod($item, $mode);
		}

		return false;
	}

	/**
	 * Simple wrapper around chmod
	 *
	 * - Checks proper value for mode if one is supplied
	 * - Consolidates chmod error suppression to single function
	 *
	 * @param string $file
	 * @param string|int $mode
	 *
	 * @return bool
	 */
	public function elk_chmod($item, $mode = '')
	{
		$result = false;
		$mode = trim($mode);

		if (empty($mode) || !is_numeric($mode))
		{
			$mode = $this->isDir($item) ? 0755 : 0644;
		}

		// Make sure we have a form of 0777 or '777' or '0777' so its safe for intval '8'
		if (($mode % 10) >= 8)
		{
			$mode = decoct($mode);
		}

		if ($mode == decoct(octdec("$mode")))
		{
			$result = @chmod($item, intval($mode, 8));
		}

		return $result;
	}

	/**
	 * is_dir() helper using spl functions.  is_dir can throw an exception if open_basedir
	 * restrictions are in effect.
	 *
	 * @param string $dir
	 * @return bool
	 */
	public function isDir($dir)
	{
		try
		{
			$dir = new \SplFileInfo($dir);
			if ($dir->getType() === 'dir' && !$dir->isLink())
			{
				return true;
			}
		}
		catch (\RuntimeException $e)
		{
			return false;
		}

		return false;
	}

	/**
	 * file_exists() helper.  file_exists can throw an E_WARNING on failure.
	 * Returns true if the filename (not a directory or link) exists.
	 *
	 * @param string $item a file or directory location
	 * @return bool
	 */
	public function fileExists($item)
	{
		try
		{
			$fileInfo = new \SplFileInfo($item);
			if ($fileInfo->isFile() && !$fileInfo->isLink())
			{
				return true;
			}
		}
		catch (\RuntimeException $e)
		{
			return false;
		}

		return false;
	}

	/**
	 * is_writable() helper.  is_writable can throw an E_WARNING on failure.
	 * Returns true if the filename/directory exists and is writable.
	 *
	 * @param string $item a file or directory location
	 * @return bool
	 */
	public function isWritable($item)
	{
		try
		{
			$fileInfo = new \SplFileInfo($item);
			if ($fileInfo->isWritable())
			{
				return true;
			}
		}
		catch (\RuntimeException $e)
		{
			return false;
		}

		return false;
	}


	/**
	 * Creates a directory as defined by a supplied path
	 *
	 * What it does:
	 *
	 * - Attempts to make the directory writable
	 * - Will create a full tree structure
	 * - Optionally places an .htaccess in created directories for security
	 *
	 * @param string $path the path to fully create
	 * @param bool $makeSecure if to create .htaccess file in created directory
	 * @return bool
	 * @throws \Exception
	 */
	public function createDirectory($path, $makeSecure = true)
	{
		// Path already exists?
		if (file_exists($path))
		{
			if ($this->isDir($path))
			{
				return true;
			}

			// A file exists at this location with this name
			throw new Exception('attach_dir_duplicate_file');
		}

		// Normalize windows and linux path's
		$path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		$tree = explode(DIRECTORY_SEPARATOR, $path);
		$count = count($tree);
		$partialTree = '';

		// Make sure we have a valid path format
		$directory = !empty($tree) ? $this->_initDir($tree, $count) : false;
		if ($directory === false)
		{
			// Maybe it's just the folder name
			$tree = explode(DIRECTORY_SEPARATOR,BOARDDIR . DIRECTORY_SEPARATOR . $path);
			$count = count($tree);

			$directory = !empty($tree) ? $this->_initDir($tree, $count) : false;
			if ($directory === false)
			{
				throw new Exception('attachments_no_create');
			}
		}

		// Walk down the path until we find a part that exists
		for ($i = $count - 1; $i >= 0; $i--)
		{
			$partialTree = implode('/', array_slice($tree, 0, $i + 1));

			// If this exists, lets ensure it is a directory
			if (file_exists($partialTree))
			{
				if (!is_dir($partialTree))
				{
					throw new Exception('attach_dir_duplicate_file');
				}
				else
				{
					break;
				}
			}
		}

		// Can't find this path anywhere
		if ($i < 0)
		{
			throw new Exception('attachments_no_create');
		}

		// Walk forward and create the missing parts
		for ($i++; $i < $count; $i++)
		{
			$partialTree .= '/' . $tree[$i];
			if (!mkdir($partialTree))
			{
				return false;
			}

			// Make it writable
			if (!$this->chmod($partialTree))
			{
				throw new Exception('attachments_no_write');
			}

			if ($makeSecure)
			{
				secureDirectory($partialTree, true);
			}
		}

		clearstatcache(false, $partialTree);

		return true;
	}

	/**
	 * Deletes a file (not a directory) at a given location
	 *
	 * @param $path
	 * @return bool
	 */
	public function delete($path)
	{
		if (!$this->fileExists($path) || !$this->isWritable($path))
		{
			return false;
		}

		error_clear_last();

		if (!@unlink($path))
		{
			return false;
		}

		return true;
	}

	/**
	 * Helper function for createDirectory
	 *
	 * What it does:
	 *
	 * - Gets the directory w/o drive letter for windows
	 *
	 * @param string[] $tree
	 * @param int $count
	 * @return false|mixed|string|null
	 */
	private function _initDir(&$tree, &$count)
	{
		$directory = '';

		// If on Windows servers the first part of the path is the drive (e.g. "C:")
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		{
			// Better be sure that the first part of the path is actually a drive letter...
			// ...even if, I should check this in the admin page...isn't it?
			// ...NHAAA Let's leave space for users' complains! :P
			if (preg_match('/^[a-z]:$/i', $tree[0]))
			{
				$directory = array_shift($tree);
			}
			else
			{
				return false;
			}

			$count--;
		}

		return $directory;
	}

	/**
	 * Being a singleton, use this static method to retrieve the instance of the class
	 *
	 * @return \ElkArte\FileFunctions An instance of the class.
	 */
	public static function instance()
	{
		if (self::$_instance === null)
		{
			self::$_instance = new FileFunctions();
		}

		return self::$_instance;
	}
}