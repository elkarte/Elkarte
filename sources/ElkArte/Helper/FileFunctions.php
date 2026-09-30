<?php

/**
 * This class provides many common file and directory functions such as creating directories, checking existence, etc.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Helper;

use Exception;

class FileFunctions
{
	/** @var FileFunctions The instance of the class */
	private static $_instance;

	/**
	 * Chmod control will attempt to make a file or directory writable
	 *
	 * - Progressively attempts various chmod values until item is writable or failure
	 *
	 * @param string $item file or directory
	 * @return bool
	 */
	public function chmod($item): bool
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
			$this->elk_chmod($item, $mode);
			clearstatcache(false, $item);

			if ($this->isWritable($item))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Simple wrapper around chmod
	 *
	 * - Checks proper value for mode if one is supplied
	 * - Consolidates chmod error suppression to single function
	 *
	 * @param string $item
	 * @param string|int $mode
	 *
	 * @return bool
	 */
	public function elk_chmod($item, $mode = ''): bool
	{
		$result = false;

		if (is_string($mode))
		{
			$mode = trim($mode);
			if ($mode === '' || !is_numeric($mode))
			{
				$mode = $this->isDir($item) ? 0755 : 0644;
			}
			elseif (preg_match('/^0?[0-7]{3,4}$/', $mode))
			{
				$mode = (int) octdec($mode);
			}
			else
			{
				$mode = (int) $mode;
			}
		}
		elseif (is_int($mode))
		{
			// If mode was passed as a decimal number representing octal digits (e.g. 755 or 644 instead of 0755 or 0644)
			if ($mode >= 600 && $mode <= 777 && preg_match('/^[0-7]{3}$/', (string) $mode))
			{
				$mode = (int) octdec((string) $mode);
			}
		}
		else
		{
			$mode = $this->isDir($item) ? 0755 : 0644;
		}

		// Ensure $mode is an integer within valid permission bit range
		if (is_int($mode) && $mode >= 0 && $mode <= 07777)
		{
			$result = @chmod($item, $mode);
		}

		return $result;
	}

	/**
	 * is_dir() helper using spl functions.
	 * Returns true if the path is an existing directory or a link pointing to a directory.
	 *
	 * @param string $dir
	 * @return bool
	 */
	public function isDir($dir): bool
	{
		try
		{
			return (new \SplFileInfo($dir))->isDir();
		}
		catch (\RuntimeException)
		{
			return false;
		}
	}

	/**
	 * file_exists() helper.
	 * Returns true if the filename (file or link) exists.
	 *
	 * @param string $item a file or directory location
	 * @return bool
	 */
	public function fileExists($item): bool
	{
		try
		{
			$fileInfo = new \SplFileInfo($item);
			return $fileInfo->isFile() || $fileInfo->isLink();
		}
		catch (\RuntimeException)
		{
			return false;
		}
	}

	/**
	 * fileperms() helper using spl functions. fileperms can throw an e-warning
	 *
	 * @param string $item
	 * @return int|bool
	 */
	public function filePerms($item)
	{
		try
		{
			$fileInfo = new \SplFileInfo($item);
			if ($perms = $fileInfo->getPerms())
			{
				return $perms;
			}
		}
		catch (\RuntimeException)
		{
			return false;
		}

		return false;
	}

	/**
	 * filesize() helper. filesize can throw an E_WARNING on failure.
	 * Returns the filesize in bytes on success or false on failure.
	 *
	 * @param string $item a file location
	 * @return int|bool
	 */
	public function fileSize($item)
	{
		try
		{
			$fileInfo = new \SplFileInfo($item);
			$size = $fileInfo->getSize();
		}
		catch (\RuntimeException)
		{
			$size = false;
		}

		return $size;
	}

	/**
	 * is_writable() helper. is_writable can throw an E_WARNING on failure.
	 * Returns true if the filename/directory exists and is writable.
	 *
	 * @param string $item a file or directory location
	 * @return bool
	 */
	public function isWritable($item): bool
	{
		try
		{
			$fileInfo = new \SplFileInfo($item);
			if ($fileInfo->isWritable())
			{
				return true;
			}
		}
		catch (\RuntimeException)
		{
			return false;
		}

		return false;
	}

	/**
	 * file_get_contents() helper with error suppression.
	 *
	 * @param string $filename The file to read
	 *
	 * @return string|false The file contents on success, or false on failure
	 */
	public function fileGetContents($filename)
	{
		if (!$this->fileExists($filename) && !is_file($filename))
		{
			return false;
		}

		return @file_get_contents($filename);
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
	public function createDirectory($path, $makeSecure = true): bool
	{
		if (empty($path))
		{
			throw new Exception('attachments_no_create');
		}

		// Normalize windows and linux paths
		$normalizedPath = str_replace('\\', '/', $path);
		$normalizedPath = rtrim($normalizedPath, '/');

		// Path already exists?
		if (file_exists($normalizedPath))
		{
			if ($this->isDir($normalizedPath))
			{
				return true;
			}

			// A file exists at this location with this name
			throw new Exception('attach_dir_duplicate_file');
		}

		// If relative path and does not exist, prefix with BOARDDIR if defined
		$isAbsolute = str_starts_with($normalizedPath, '/')
			|| (str_starts_with(PHP_OS_FAMILY, 'Win') && preg_match('/^[a-zA-Z]:\//', $normalizedPath))
			|| str_starts_with($normalizedPath, '//');

		if (!$isAbsolute && defined('BOARDDIR') && !file_exists($normalizedPath))
		{
			$boardDirNormalized = rtrim(str_replace('\\', '/', BOARDDIR), '/');
			$normalizedPath = $boardDirNormalized . '/' . $normalizedPath;
		}

		// Split into path segments and root prefix
		if (str_starts_with(PHP_OS_FAMILY, 'Win') && preg_match('/^([a-zA-Z]:)(\/.*)?$/', $normalizedPath, $matches))
		{
			$prefix = $matches[1];
			$rest = $matches[2] ?? '';
			$segments = array_values(array_filter(explode('/', $rest), 'strlen'));
		}
		elseif (str_starts_with($normalizedPath, '//'))
		{
			$parts = array_values(array_filter(explode('/', $normalizedPath), 'strlen'));
			if (count($parts) >= 2)
			{
				$prefix = '//' . $parts[0] . '/' . $parts[1];
				$segments = array_slice($parts, 2);
			}
			else
			{
				$prefix = '//';
				$segments = $parts;
			}
		}
		elseif (str_starts_with($normalizedPath, '/'))
		{
			$prefix = '';
			$segments = array_values(array_filter(explode('/', $normalizedPath), 'strlen'));
		}
		else
		{
			$prefix = '';
			$segments = array_values(array_filter(explode('/', $normalizedPath), 'strlen'));
		}

		if (empty($segments) && empty($prefix))
		{
			throw new Exception('attachments_no_create');
		}

		// Walk down the path until we find a part that exists
		$count = count($segments);
		$existingIndex = -1;
		$currentPath = '';

		for ($i = $count - 1; $i >= 0; $i--)
		{
			$testPath = $prefix . '/' . implode('/', array_slice($segments, 0, $i + 1));
			if (file_exists($testPath))
			{
				if (!is_dir($testPath))
				{
					throw new Exception('attach_dir_duplicate_file');
				}

				$existingIndex = $i;
				$currentPath = $testPath;
				break;
			}
		}

		if ($existingIndex === -1)
		{
			$basePrefix = $prefix === '' ? '/' : $prefix . '/';
			if (!file_exists($basePrefix) || !is_dir($basePrefix))
			{
				throw new Exception('attachments_no_create');
			}
			$currentPath = rtrim($basePrefix, '/');
		}

		// Walk forward and create the missing parts
		for ($i = $existingIndex + 1; $i < $count; $i++)
		{
			$currentPath .= '/' . $segments[$i];
			if (!file_exists($currentPath))
			{
				if (!@mkdir($currentPath, 0755) && !$this->isDir($currentPath))
				{
					return false;
				}
			}
			elseif (!is_dir($currentPath))
			{
				throw new Exception('attach_dir_duplicate_file');
			}

			// Make it writable
			if (!$this->chmod($currentPath))
			{
				throw new Exception('attachments_no_write');
			}

			if ($makeSecure && function_exists('secureDirectory'))
			{
				secureDirectory($currentPath, true);
			}
		}

		clearstatcache(false, $currentPath);

		return true;
	}

	/**
	 * Deletes a file (not a directory) at a given location
	 *
	 * @param string $path
	 * @return bool
	 */
	public function delete($path): bool
	{
		if (!is_file($path) && !is_link($path) && !$this->fileExists($path))
		{
			return false;
		}

		error_clear_last();
		$result = @unlink($path);

		if (!$result && ($this->fileExists($path) || is_file($path) || is_link($path)))
		{
			$this->chmod($path);
			$result = @unlink($path);
		}

		if ($result)
		{
			clearstatcache(false, $path);
		}

		return $result;
	}

	/**
	 * Recursively removes a directory and all files and subdirectories contained within.
	 * Use with *caution*, it is thorough, destructive, and irreversible.
	 *
	 * @param string $path
	 * @param bool $delete_dir if to remove the directory structure as well
	 * @return bool
	 */
	public function rmDir($path, $delete_dir = true): bool
	{
		// @todo build a list of excluded directories
		if (!$this->isDir($path))
		{
			return true;
		}

		$success = true;
		$iterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
		$files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);

		/** @var \SplFileInfo $file */
		foreach ($files as $file)
		{
			$target = $file->getPathname();

			// If it's a directory (and not a symlink to a directory)
			if ($file->isDir() && !$file->isLink())
			{
				if ($delete_dir)
				{
					if (!$file->isWritable())
					{
						$this->chmod($target);
					}

					$success = @rmdir($target) && $success;
				}
			}
			else
			{
				// It's a file or symlink
				if (!$file->isWritable() && !$file->isLink())
				{
					$this->chmod($target);
				}

				$success = @unlink($target) && $success;
			}
		}

		if ($delete_dir)
		{
			if (!$this->isWritable($path))
			{
				$this->chmod($path);
			}

			$success = @rmdir($path) && $success;
		}

		clearstatcache(false, $path);

		return $success;
	}

	/**
	 * Create a full tree listing of files for a given directory path
	 *
	 * @param string $path
	 * @return array
	 */
	public function listTree($path): array
	{
		$tree = [];
		if (!$this->isDir($path))
		{
			return $tree;
		}

		$normalizedBasePath = rtrim(str_replace('\\', '/', $path), '/');
		$baseLength = strlen($normalizedBasePath);

		$iterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
		$files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);
		/** @var \SplFileInfo $file */
		foreach ($files as $file)
		{
			if ($file->isDir())
			{
				continue;
			}

			$filePath = str_replace('\\', '/', $file->getPath());
			$sub_path = '';
			if (str_starts_with($filePath, $normalizedBasePath))
			{
				$sub_path = ltrim(substr($filePath, $baseLength), '/');
			}

			$tree[] = [
				'filename' => $sub_path === '' ? $file->getFilename() : $sub_path . '/' . $file->getFilename(),
				'size' => $file->getSize(),
				'skipped' => false,
			];
		}

		return $tree;
	}

	/**
	 * Being a singleton, use this static method to retrieve the instance of the class
	 *
	 * @return FileFunctions An instance of the class.
	 */
	public static function instance(): FileFunctions
	{
		if (self::$_instance === null)
		{
			self::$_instance = new FileFunctions();
		}

		return self::$_instance;
	}

	/**
	 * Set or reset the singleton instance (useful for testing)
	 *
	 * @param FileFunctions|null $instance
	 * @return void
	 */
	public static function setInstance(?FileFunctions $instance = null): void
	{
		self::$_instance = $instance;
	}
}
