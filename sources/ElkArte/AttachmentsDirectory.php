<?php

/**
 * Handles the job of attachment and avatar maintenance / management.
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

use \Exception;

class AttachmentsDirectory
{
	public const AUTO_SEQUENCE = 1;
	public const AUTO_YEAR = 2;
	public const AUTO_YEAR_MONTH = 3;
	public const AUTO_RAND = 4;
	public const AUTO_RAND2 = 5;
	protected static $dir_size = 0;
	protected static $dir_files = 0;
	protected $automanage_attachments = 0;
	protected $currentAttachmentUploadDir = 0;
	protected $sizeLimit = 0;
	protected $numFilesLimit = 0;
	protected $basedirectory_for_attachments = '';
	protected $useSubdirectories = 0;
	protected $attachment_full_notified = false;
	protected $attachmentUploadDir = [];
	protected $baseDirectories = [];
	protected $last_dirs = [];
	protected $db = null;

	public function __construct($options, $db)
	{
		$this->db = $db;

		$this->automanage_attachments = (int) ($options['automanage_attachments'] ?? $this->automanage_attachments);
		$this->currentAttachmentUploadDir = $options['currentAttachmentUploadDir'] ?? $this->currentAttachmentUploadDir;
		$this->sizeLimit = $options['attachmentDirSizeLimit'] ?? $this->sizeLimit;
		$this->numFilesLimit = $options['attachmentDirFileLimit'] ?? $this->numFilesLimit;
		$this->baseDirectories = $options['attachment_basedirectories'] ?? serialize($this->baseDirectories);
		$this->last_dirs = $options['last_attachments_directory'] ?? serialize($this->last_dirs);
		$this->useSubdirectories = $options['use_subdirectories_for_attachments'] ?? $this->useSubdirectories;
		$this->basedirectory_for_attachments = $options['basedirectory_for_attachments'] ?? $this->basedirectory_for_attachments;
		$this->attachment_full_notified = !empty($options['attachment_full_notified'] ?? $this->basedirectory_for_attachments);

		$this->last_dirs = Util::unserialize($this->last_dirs);
		$this->baseDirectories = Util::unserialize($this->baseDirectories);

		if (empty($options['attachmentUploadDir']))
		{
			$options['attachmentUploadDir'] = serialize([1 => BOARDDIR . '/attachments']);
		}

		// It should be added to the install and upgrade scripts.
		// But since the converters need to be updated also. This is easier.
		if (empty($options['currentAttachmentUploadDir']))
		{
			$this->currentAttachmentUploadDir = 1;

			updateSettings(array(
				'attachmentUploadDir' => $options['attachmentUploadDir'],
				'currentAttachmentUploadDir' => 1,
			));
		}

		$this->attachmentUploadDir = Util::unserialize($options['attachmentUploadDir']);
		$this->attachmentUploadDir = $this->attachmentUploadDir ?: [1 => $options['attachmentUploadDir']];
	}

	/**
	 * Return how many files will still "fit" in the directory
	 *
	 * @param int $current_files
	 * @return false|mixed
	 */
	public function remainingFiles($current_files)
	{
		if ($this->hasNumFilesLimit())
		{
			return max($this->numFilesLimit - $current_files, 0);
		}

		return false;
	}

	/**
	 * Returns if a file limit (count) has been placed on a directory
	 *
	 * @return bool
	 */
	public function hasNumFilesLimit()
	{
		return !empty($this->numFilesLimit);
	}

	/**
	 * Returns how much physical space is remaining for a directory
	 *
	 * @param $current_dir_size
	 * @return false|mixed
	 */
	public function remainingSpace($current_dir_size)
	{
		if ($this->hasSizeLimit())
		{
			return max($this->sizeLimit - $current_dir_size, 0);
		}

		return false;
	}

	/**
	 * Return if a directory physical space limit has been set
	 *
	 * @return bool
	 */
	public function hasSizeLimit()
	{
		return !empty($this->sizeLimit);
	}

	/**
	 * Little utility function for the $id_folder computation for attachments.
	 *
	 * What it does:
	 *
	 * - This returns the id of the folder where the attachment or avatar will be saved.
	 * - If multiple attachment directories are not enabled, this will be 1 by default.
	 *
	 * @return int 1 if multiple attachment directories are not enabled,
	 * or the id of the current attachment directory otherwise.
	 */
	public function currentDirectoryId()
	{
		if (!array_key_exists($this->currentAttachmentUploadDir, $this->attachmentUploadDir))
		{
			$this->currentAttachmentUploadDir = max(array_keys($this->attachmentUploadDir));
		}

		return $this->currentAttachmentUploadDir;
	}

	/**
	 * Checks if a given id or named directory has been defined.  Does not
	 * check if it exists on disk.
	 *
	 * @param int|string $id
	 * @return bool
	 */
	public function directoryExists($id)
	{
		if (is_integer($id))
		{
			return array_key_exists($id, $this->attachmentUploadDir);
		}

		return in_array($id, $this->attachmentUploadDir)
			|| in_array(BOARDDIR . DIRECTORY_SEPARATOR . $id, $this->attachmentUploadDir);
	}

	/**
	 * Loop through the attach directory array to count any sub-directories
	 *
	 * @param string $dir
	 * @return int
	 */
	public function countSubdirs($dir)
	{
		$expected_dirs = 0;
		foreach ($this->getPaths() as $id => $sub)
		{
			if (strpos($sub, $dir . DIRECTORY_SEPARATOR) !== false)
			{
				$expected_dirs++;
			}
		}

		return $expected_dirs;
	}

	/**
	 * Returns the list of directories as an array.
	 *
	 * @return mixed[] the attachments directory/directories
	 */
	public function getPaths()
	{
		return $this->attachmentUploadDir;
	}

	/**
	 * Returns the directory name for a given key
	 *
	 * @param int $id key in our attachmentUploadDir array
	 * @return string
	 *
	 * @throws \Exception
	 */
	public function getPathById($id)
	{
		if (isset($this->attachmentUploadDir[$id]))
		{
			return $this->attachmentUploadDir[$id];
		}

		throw new Exception('dir_does_not_exists');
	}

	/**
	 * Return the base directory in use for attachments
	 *
	 * @return mixed
	 */
	public function getBaseDirs()
	{
		return $this->basedirectory_for_attachments;
	}

	/**
	 * Return if base directories have been defined
	 *
	 * @return bool
	 */
	public function hasBaseDir()
	{
		return !empty($this->baseDirectories);
	}

	/**
	 * Return if a given directory is a defined base dir
	 *
	 * @param $dir
	 * @return bool
	 */
	public function isBaseDir($dir)
	{
		return in_array($dir, $this->baseDirectories);
	}

	public function updateLastDirs($dir_id)
	{
		if (!empty($this->last_dirs) && (isset($this->last_dirs[$dir_id]) || isset($this->last_dirs[0])))
		{
			$num = substr(strrchr($this->attachmentUploadDir[$dir_id], '_'), 1);
			if (is_numeric($num))
			{
				// Need to find the base folder.
				$bid = -1;
				$use_subdirectories = 0;
				if (!empty($this->attachmentUploadDir))
				{
					foreach ($this->attachmentUploadDir as $bid => $base)
					{
						if (strpos($this->attachmentUploadDir[$dir_id], $base . DIRECTORY_SEPARATOR) !== false)
						{
							$use_subdirectories = 1;
							break;
						}
					}
				}

				if ($use_subdirectories == 0 && strpos($this->attachmentUploadDir[$dir_id], BOARDDIR . DIRECTORY_SEPARATOR) !== false)
				{
					$bid = 0;
				}

				$this->last_dirs[$bid] = (int) $num;
				$this->basedirectory_for_attachments = !empty($this->basedirectory_for_attachments) ? $this->basedirectory_for_attachments : '';
				$this->useSubdirectories = (int) $this->useSubdirectories;

				updateSettings(array(
					'last_attachments_directory' => serialize($this->last_dirs),
					'basedirectory_for_attachments' => $bid == 0 ? $this->basedirectory_for_attachments : $this->attachmentUploadDir[$bid],
					'use_subdirectories_for_attachments' => $use_subdirectories,
				));
			}
		}
	}

	/**
	 * If size/count limits are enabled, validates a given file will still fit
	 * within the given constraints.  If not will attempt to create new directorys
	 * based on ACP options
	 *
	 * @param $thumb_size
	 */
	public function checkDirSize($thumb_size)
	{
		if ($this->autoManageIsLevel(self::AUTO_SEQUENCE) && !empty($this->sizeLimit) || !empty($this->numFilesLimit))
		{
			self::$dir_size += $thumb_size;
			self::$dir_files++;

			// If the folder is full, try to create a new one and move the thumb to it.
			if (self::$dir_size > $this->sizeLimit * 1024 || self::$dir_files + 2 > $this->numFilesLimit)
			{
				if ($this->manageBySpace())
				{
					self::$dir_size = 0;
					self::$dir_files = 0;
				}
			}
		}
	}

	public function autoManageIsLevel($level)
	{
		return $this->automanage_attachments === (int) $level;
	}

	/**
	 * Determines the current base directory and attachment directory
	 *
	 * What it does:
	 *
	 * - Increments the above directory to the next available slot
	 * - Uses createDirectory to create the incremental directory
	 *
	 * @package Attachments
	 */
	public function manageBySpace()
	{
		if ($this->autoManageEnabled(self::AUTO_SEQUENCE))
		{
			return true;
		}

		$basedirectory = !empty($this->useSubdirectories) ? $this->basedirectory_for_attachments : BOARDDIR;

		// Just to be sure: I don't want directory separators at the end
		$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
		$basedirectory = rtrim($basedirectory, $sep);

		// Get the current base directory
		if (!empty($this->useSubdirectories) && !empty($this->baseDirectories))
		{
			$base_dir = array_search($this->basedirectory_for_attachments, $this->baseDirectories);
		}
		else
		{
			$base_dir = 0;
		}

		// Get the last attachment directory for that base directory
		$this->initLastDir($base_dir);

		// And increment it.
		$this->last_dirs[$base_dir]++;

		$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . $this->last_dirs[$base_dir];

		// make sure it exists and is writable
		try
		{
			$this->createDirectory($updir);

			$this->currentAttachmentUploadDir = array_search($updir, $this->attachmentUploadDir);
			updateSettings(array(
				'last_attachments_directory' => serialize($this->last_dirs),
				'currentAttachmentUploadDir' => $this->currentAttachmentUploadDir,
			));

			return true;
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	public function autoManageEnabled($minLevel = null)
	{
		if ($minLevel === null)
		{
			return !empty($this->automanage_attachments);
		}

		return $this->automanage_attachments > $minLevel;
	}

	protected function initLastDir($base_dir)
	{
		if (!isset($this->last_dirs[$base_dir]))
		{
			$this->last_dirs[$base_dir] = 0;
		}
	}

	/**
	 * Creates a directory as defined by the admin attach options
	 *
	 * What it does:
	 *
	 * - Attempts to make the directory writable
	 * - Places an .htaccess in new directories for security
	 *
	 * @param string $updir
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 * @package Attachments
	 *
	 */
	public function createDirectory($updir)
	{
		$tree = $this->getTreeElements($updir);
		$count = count($tree);

		$directory = !empty($tree) ? $this->initDir($tree, $count) : false;
		if ($directory === false)
		{
			// Maybe it's just the folder name
			$tree = $this->getTreeElements(BOARDDIR . DIRECTORY_SEPARATOR . $updir);
			$count = count($tree);

			$directory = !empty($tree) ? $this->initDir($tree, $count) : false;
			if ($directory === false)
			{
				throw new Exception('attachments_no_create');
			}
		}

		$directory .= DIRECTORY_SEPARATOR . array_shift($tree);

		while (!$this->isDir($directory) || $count != -1)
		{
			if (!$this->isDir($directory))
			{
				if (!@mkdir($directory, 0755))
				{
					throw new Exception('attachments_no_create');
				}
			}

			$directory .= DIRECTORY_SEPARATOR . array_shift($tree);
			$count--;
		}

		// Try to make it writable
		if (!is_writable($directory))
		{
			chmod($directory, 0755);
			if (!is_writable($directory))
			{
				chmod($directory, 0775);
				if (!is_writable($directory))
				{
					chmod($directory, 0777);
					if (!is_writable($directory))
					{
						throw new Exception('attachments_no_write');
					}
				}
			}
		}

		// Everything seems fine...let's create the .htaccess
		if (!file_exists($directory . DIRECTORY_SEPARATOR . '.htaccess'))
		{
			secureDirectory($updir, true);
		}

		$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
		$updir = rtrim($updir, $sep);

		// Only update if it's a new directory
		if (!in_array($updir, $this->attachmentUploadDir))
		{
			$this->currentAttachmentUploadDir = max(array_keys($this->attachmentUploadDir)) + 1;
			$this->attachmentUploadDir[$this->currentAttachmentUploadDir] = $updir;

			updateSettings(array(
				'attachmentUploadDir' => serialize($this->attachmentUploadDir),
				'currentAttachmentUploadDir' => $this->currentAttachmentUploadDir,
			), true);
		}

		return true;
	}

	/**
	 * Finds the current directory tree for the supplied base directory
	 *
	 * @param string $directory
	 * @return string[]|bool on fail else array of directory names
	 * @package Attachments
	 */
	protected function getTreeElements($directory)
	{
		/*
			In Windows server both \ and / can be used as directory separators in paths
			In Linux (and presumably *nix) servers \ can be part of the name
			So for this reasons:
				 * in Windows we need to explode for both \ and /
				 * while in linux should be safe to explode only for / (aka DIRECTORY_SEPARATOR)
		 */
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		{
			$tree = preg_split('#[\\\/]#', $directory);
		}
		else
		{
			if (substr($directory, 0, 1) != DIRECTORY_SEPARATOR)
			{
				return false;
			}

			$tree = explode(DIRECTORY_SEPARATOR, trim($directory, DIRECTORY_SEPARATOR));
		}

		return $tree;
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
	 *
	 * @return bool|mixed|string
	 * @package Attachments
	 *
	 */
	public function initDir(&$tree, &$count)
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

	public function countDirs()
	{
		return count($this->attachmentUploadDir);
	}

	public function getAttachmentsTree($file_tree)
	{
		// Are we using multiple attachment directories?
		if ($this->hasMultiPaths())
		{
			unset($file_tree[strtr(BOARDDIR, array('\\' => '/'))]['contents']['attachments']);

			// @todo Should we suggest non-current directories be read only?
			foreach ($this->attachmentUploadDir as $dir)
			{
				$file_tree[strtr($dir, array('\\' => '/'))] = array(
					'type' => 'dir',
					'writable_on' => 'restrictive',
				);
			}
		}
		else
		{
			if (substr($this->attachmentUploadDir[1], 0, strlen(BOARDDIR)) != BOARDDIR)
			{
				unset($file_tree[strtr(BOARDDIR, array('\\' => '/'))]['contents']['attachments']);
			}
			$file_tree[strtr($this->attachmentUploadDir[1], array('\\' => '/'))] = array(
				'type' => 'dir',
				'writable_on' => 'restrictive',
			);
		}

		return $file_tree;
	}

	public function hasMultiPaths()
	{
		return $this->autoManageEnabled() && count($this->attachmentUploadDir) > 1;
	}

	/**
	 * Check and create a directory automatically.
	 *
	 * @param bool $is_admin_interface
	 */
	public function automanageCheckDirectory($is_admin_interface = false)
	{
		if ($this->autoManageEnabled() === false)
		{
			return;
		}

		if ($this->checkNewDir($is_admin_interface) === false)
		{
			return;
		}

		// Get our date and random numbers for the directory choices
		$year = date('Y');
		$month = date('m');

		$rand = md5(mt_rand());
		$rand1 = $rand[1];
		$rand = $rand[0];

		if (!empty($this->baseDirectories) && !empty($this->useSubdirectories))
		{
			$base_dir = array_search($this->basedirectory_for_attachments, $this->baseDirectories);
		}
		else
		{
			$base_dir = 0;
		}

		$basedirectory = !empty($this->useSubdirectories) ? $this->basedirectory_for_attachments : BOARDDIR;

		// Just to be sure: I don't want directory separators at the end
		$sep = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '\/' : DIRECTORY_SEPARATOR;
		$basedirectory = rtrim($basedirectory, $sep);

		switch ($this->automanage_attachments)
		{
			case self::AUTO_SEQUENCE:
				$this->initLastDir($base_dir);
				$updir = $basedirectory . DIRECTORY_SEPARATOR . 'attachments_' . $this->last_dirs[$base_dir];
				break;
			case self::AUTO_YEAR:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . $year;
				break;
			case self::AUTO_YEAR_MONTH:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;
				break;
			case self::AUTO_RAND:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . (empty($this->useSubdirectories) ? 'attachments-' : 'random_') . $rand;
				break;
			case self::AUTO_RAND2:
				$updir = $basedirectory . DIRECTORY_SEPARATOR . (empty($this->useSubdirectories) ? 'attachments-' : 'random_') . $rand . DIRECTORY_SEPARATOR . $rand1;
				break;
			default:
				$updir = '';
		}

		if (!in_array($updir, $this->attachmentUploadDir) && !empty($updir))
		{
			try
			{
				$this->createDirectory($updir);
				$outputCreation = true;
			}
			catch (Exception $e)
			{
				$outputCreation = false;
			}
		}
		elseif (in_array($updir, $this->attachmentUploadDir))
		{
			$outputCreation = true;
		}
		else
		{
			$outputCreation = false;
		}

		if ($outputCreation)
		{
			$this->currentAttachmentUploadDir = array_search($updir, $this->attachmentUploadDir);

			updateSettings(array(
				'currentAttachmentUploadDir' => $this->currentAttachmentUploadDir,
			));
		}

		return $outputCreation;
	}

	/**
	 * Should we try to create a new directory or not?
	 *
	 * @param bool $is_admin_interface
	 * @return bool
	 */
	protected function checkNewDir($is_admin_interface)
	{
		// Not pretty, but since we don't want folders created for every post.
		// It'll do unless a better solution can be found.
		if ($is_admin_interface === true)
		{
			return true;
		}

		if ($this->autoManageEnabled() === false)
		{
			return false;
		}

		if (!isset($_FILES))
		{
			return false;
		}

		if (isset($_FILES['attachment']))
		{
			foreach ($_FILES['attachment']['tmp_name'] as $dummy)
			{
				if (!empty($dummy))
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks if the current active directory has space allowed for a new attachment file
	 *
	 * @param $sess_attach
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function checkDirSpace($sess_attach)
	{
		if (empty(self::$dir_size) || empty(self::$dir_files))
		{
			$this->dirSpace($sess_attach->getSize());
		}

		// Are we about to run out of room? Let's notify the admin then.
		if ($this->attachment_full_notified === false && !empty($this->sizeLimit) && $this->sizeLimit > 4000 && self::$dir_size > ($this->sizeLimit - 2000) * 1024
			|| (!empty($this->numFilesLimit) && $this->numFilesLimit * .95 < self::$dir_files && $this->numFilesLimit > 500))
		{
			require_once(SUBSDIR . '/Admin.subs.php');
			emailAdmins('admin_attachments_full');
			updateSettings(array('attachment_full_notified' => 1));
		}

		// No room left.... What to do now???
		if (!empty($this->numFilesLimit) && self::$dir_files + 2 > $this->numFilesLimit
			|| (!empty($this->sizeLimit) && self::$dir_size > $this->sizeLimit * 1024))
		{
			// If we are managing the directories space automatically, lets get to it
			if ($this->autoManageIsLevel(self::AUTO_SEQUENCE))
			{
				// Move it to the new folder if we can. (Throws Exception if it fails)
				if ($this->manageBySpace())
				{
					$sess_attach->moveTo($this->getCurrent());
					$sess_attach->setIdFolder($this->currentAttachmentUploadDir);
					self::$dir_size = 0;
					self::$dir_files = 0;
				}
			}
			else
			{
				throw new Exception('ran_out_of_space');
			}
		}
	}

	protected function dirSpace($tmp_attach_size = 0)
	{
		$request = $this->db->query('', '
			SELECT 
				COUNT(*), SUM(size)
			FROM {db_prefix}attachments
			WHERE id_folder = {int:folder_id}
				AND attachment_type != {int:type}',
			array(
				'folder_id' => $this->currentAttachmentUploadDir,
				'type' => 1,
			)
		);
		list (self::$dir_files, self::$dir_size) = $this->db->fetch_row($request);
		$this->db->free_result($request);
		self::$dir_files += empty($tmp_attach_size) ? 0 : 1;
		self::$dir_size += $tmp_attach_size;
	}

	/**
	 * The current attachments path:
	 *
	 * What it does: @return string
	 *
	 * @todo not really true at the moment
	 *  - BOARDDIR . '/attachments', if nothing is set yet.
	 *  - if the forum is using multiple attachments directories,
	 *    then the current path is stored as unserialize($modSettings['attachmentUploadDir'])[$modSettings['currentAttachmentUploadDir']]
	 *  - otherwise, the current path is $modSettings['attachmentUploadDir'].
	 *
	 */
	public function getCurrent()
	{
		if (empty($this->attachmentUploadDir))
		{
			return BOARDDIR . '/attachments';
		}

		return $this->attachmentUploadDir[$this->currentAttachmentUploadDir];
	}

	public function rename($id, &$real_path)
	{
		if (!empty($this->attachmentUploadDir[$id]) && $real_path != $this->attachmentUploadDir[$id])
		{
			if ($real_path != $this->attachmentUploadDir[$id] && !$this->isDir($real_path))
			{
				if (!@rename($this->attachmentUploadDir[$id], $real_path))
				{
					$real_path = $this->attachmentUploadDir[$id];
					throw new Exception('attach_dir_no_rename');
				}
			}
			else
			{
				$real_path = $this->attachmentUploadDir[$id];
				throw new Exception('attach_dir_exists_msg');
			}

			// Update the base directory path
			if (!empty($this->baseDirectories) && array_key_exists($id, $this->baseDirectories))
			{
				$base = $this->basedirectory_for_attachments === $this->attachmentUploadDir[$id] ? $real_path : $this->basedirectory_for_attachments;

				$this->baseDirectories[$id] = $real_path;
				updateSettings(array(
					'attachment_basedirectories' => serialize($this->baseDirectories),
					'basedirectory_for_attachments' => $base,
				));
			}
		}
	}

	/**
	 * Remove a directory if its empty (not counting .htaccess or index.php)
	 *
	 * @param $id
	 * @param $real_path
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function delete($id, &$real_path)
	{
		$real_path = $this->attachmentUploadDir[$id];

		// It's not a good idea to delete the current directory.
		if ($this->isCurrentDirectoryId($id))
		{
			throw new Exception('attach_dir_is_current');
		}

		// Or the current base directory
		if ($this->isCurrentBaseDir($id))
		{
			throw new Exception('attach_dir_is_current_bd');
		}
		else
		{
			// Let's not try to delete a path with files in it.
			$num_attach = countAttachmentsInFolders($id);

			// A check to see if it's a used base dir.
			if (!empty($this->baseDirectories))
			{
				// Count any sub-folders.
				foreach ($this->attachmentUploadDir as $sub)
				{
					if (strpos($sub, $real_path . DIRECTORY_SEPARATOR) !== false)
					{
						$num_attach++;
					}
				}
			}

			// It's safe to delete. So try to delete the folder also
			if ($num_attach == 0)
			{
				if ($this->isDir($real_path))
				{
					$doit = true;
				}
				elseif ($this->isDir(BOARDDIR . DIRECTORY_SEPARATOR . $real_path))
				{
					$doit = true;
					$real_path = BOARDDIR . DIRECTORY_SEPARATOR . $real_path;
				}

				if ($doit === true)
				{
					try
					{
						unlink($real_path . '/.htaccess');
						unlink($real_path . '/index.php');
						$result = rmdir($real_path);
					}
					catch (\Exception $e)
					{
						throw new Exception('attach_dir_no_delete');
					}

					if (!$result)
					{
						throw new Exception('attach_dir_no_delete');
					}
				}

				// Remove it from the base directory list.
				if (empty($error) && !empty($this->baseDirectories))
				{
					unset($this->baseDirectories[$id]);
					updateSettings(array(
						'attachment_basedirectories' => serialize($this->baseDirectories)
					));
				}
			}
			else
			{
				throw new Exception('attach_dir_no_remove');
			}
		}
	}

	public function isCurrentDirectoryId($id)
	{
		return $this->currentAttachmentUploadDir == $id;
	}

	/**
	 * Returns if a given directory is the current base directory used for attachments
	 *
	 * @param int $id
	 * @return bool
	 */
	public function isCurrentBaseDir($id)
	{
		if (is_integer($id))
		{
			return $this->basedirectory_for_attachments === $this->attachmentUploadDir[$id];
		}

		return $this->basedirectory_for_attachments === $id;
	}

	/**
	 * is_dir() helper using spl functions.  is_dir can throw an exception if open_basedir
	 * restrictions are in effect.  getType will not return true on a symlink
	 *
	 * @param string $dir
	 * @return bool
	 */
	private function isDir($dir)
	{
		try
		{
			$dir = new \SplFileInfo($dir);
			if ($dir->getType() === 'dir')
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
}
