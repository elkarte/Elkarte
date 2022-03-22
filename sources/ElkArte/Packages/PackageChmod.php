<?php

/**
 * This deals with changing of file and directory permission either with PHP or FTP
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

namespace ElkArte\Packages;

use ElkArte\AbstractModel;
use ElkArte\FileFunctions;
use ElkArte\Http\FtpConnection;

/**
 * Class that handles teh chmod of files/directories via PHP or FTP
 */
class PackageChmod extends AbstractModel
{
	/** @var \ElkArte\FileFunctions */
	protected $fileFunc;

	/**
	 * Basic constructor
	 */
	public function __construct()
	{
		$this->fileFunc = FileFunctions::instance();
		parent::__construct();
	}

	/**
	 * Create a chmod control for, you guessed it, chmod-ing files / directories.
	 *
	 * @param string[] $chmodFiles
	 * @param mixed[] $chmodOptions  -- force_find_error, crash_on_error, destination_url
	 * @param bool $restore_write_status
	 * @return array|bool
	 * @package Packages
	 */
	public function createChmodControl($chmodFiles = array(), $chmodOptions = array(), $restore_write_status = false)
	{
		global $context, $package_ftp, $txt;

		// If we're restoring the status of existing files prepare the data.
		if ($restore_write_status && !empty($_SESSION['ftp_connection']['original_perms']))
		{
			$this->showList($restore_write_status, $chmodOptions);
		}
		// Otherwise, it's entirely irrelevant?
		elseif ($restore_write_status)
		{
			return true;
		}

		// This is where we report what we got up to.
		$return_data = array(
			'files' => array(
				'writable' => [],
				'notwritable' => [],
			),
		);

		// If we have some FTP information already, then let's assume it was required
		// and try to get ourselves reconnected.
		if (!empty($_SESSION['ftp_connection']['connected']))
		{
			$package_ftp = new FtpConnection($_SESSION['ftp_connection']['server'], $_SESSION['ftp_connection']['port'], $_SESSION['ftp_connection']['username'], $this->packageCrypt($_SESSION['ftp_connection']['password']));

			// Check for a valid connection
			if ($package_ftp->error !== false)
			{
				unset($package_ftp, $_SESSION['ftp_connection']);
			}
		}

		// Just got a submission, did we?
		if (isset($this->_req->post->ftp_username, $this->_req->post->ftp_password)
			&& (empty($package_ftp) || ($package_ftp->error !== false)))
		{
			$ftp = $this->getFTPControl();
		}

		// Now try to simply make the files writable, with whatever we might have.
		if (!empty($chmodFiles))
		{
			foreach ($chmodFiles as $k => $file)
			{
				// Sometimes this can somehow happen maybe?
				if (empty($file))
				{
					unset($chmodFiles[$k]);
				}
				// Already writable?
				elseif ($this->fileFunc->isWritable($file))
				{
					$return_data['files']['writable'][] = $file;
				}
				else
				{
					// Now try to change that.
					$return_data['files'][$this->pkgChmod($file,true) ? 'writable' : 'notwritable'][] = $file;
				}
			}
		}

		// Have we still got nasty files which ain't writable? Dear me we need more FTP good sir.
		if (empty($package_ftp)
			&& (!empty($return_data['files']['notwritable']) || !empty($chmodOptions['force_find_error'])))
		{
			$this->reportUnWritable($ftp ?? null, $chmodOptions, $return_data);

			// Sent here to die?
			if (!empty($chmodOptions['crash_on_error']))
			{
				$context['page_title'] = $txt['package_ftp_necessary'];
				$context['sub_template'] = 'ftp_required';
				obExit();
			}
		}

		return $return_data;
	}

	/**
	 * If file permissions were changed, provide the option to reset them
	 *
	 * @param bool $restore_write_status
	 * @param array $chmodOptions
	 * @return bool|void
	 */
	public function showList($restore_write_status, $chmodOptions)
	{
		global $context, $txt, $scripturl;

		// If we're restoring the status of existing files prepare the data.
		if ($restore_write_status && !empty($_SESSION['ftp_connection']['original_perms']))
		{
			$listOptions = array(
				'id' => 'restore_file_permissions',
				'title' => $txt['package_restore_permissions'],
				'get_items' => array(
					'function' => 'list_restoreFiles',
					'params' => array(
						!empty($this->_req->getPost('restore_perms')),
					),
				),
				'columns' => array(
					'path' => array(
						'header' => array(
							'value' => $txt['package_restore_permissions_filename'],
						),
						'data' => array(
							'db' => 'path',
							'class' => 'smalltext',
						),
					),
					'old_perms' => array(
						'header' => array(
							'value' => $txt['package_restore_permissions_orig_status'],
						),
						'data' => array(
							'db' => 'old_perms',
							'class' => 'smalltext',
						),
					),
					'cur_perms' => array(
						'header' => array(
							'value' => $txt['package_restore_permissions_cur_status'],
						),
						'data' => array(
							'function' => function ($rowData) {
								global $txt;

								$formatTxt = $rowData['result'] == '' || $rowData['result'] == 'skipped' ? $txt['package_restore_permissions_pre_change'] : $txt['package_restore_permissions_post_change'];

								return sprintf($formatTxt, $rowData['cur_perms'], $rowData['new_perms'], $rowData['writable_message']);
							},
							'class' => 'smalltext',
						),
					),
					'check' => array(
						'header' => array(
							'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
							'class' => 'centertext',
						),
						'data' => array(
							'sprintf' => array(
								'format' => '<input type="checkbox" name="restore_files[]" value="%1$s" class="input_check" />',
								'params' => array(
									'path' => false,
								),
							),
							'class' => 'centertext',
						),
					),
					'result' => array(
						'header' => array(
							'value' => $txt['package_restore_permissions_result'],
						),
						'data' => array(
							'function' => function ($rowData) {
								global $txt;

								return $txt['package_restore_permissions_action_' . $rowData['result']];
							},
							'class' => 'smalltext',
						),
					),
				),
				'form' => array(
					'href' => !empty($chmodOptions['destination_url']) ? $chmodOptions['destination_url'] : $scripturl . '?action=admin;area=packages;sa=perms;restore;' . $context['session_var'] . '=' . $context['session_id'],
				),
				'additional_rows' => array(
					array(
						'position' => 'below_table_data',
						'value' => '<input type="submit" name="restore_perms" value="' . $txt['package_restore_permissions_restore'] . '" class="right_submit" />',
						'class' => 'category_header',
					),
					array(
						'position' => 'after_title',
						'value' => '<span class="smalltext">' . $txt['package_restore_permissions_desc'] . '</span>',
					),
				),
			);

			// Work out what columns and the like to show.
			if (!empty($this->_req->getPost('restore_perms')))
			{
				$listOptions['additional_rows'][1]['value'] = sprintf($txt['package_restore_permissions_action_done'], $scripturl . '?action=admin;area=packages;sa=perms;' . $context['session_var'] . '=' . $context['session_id']);
				unset($listOptions['columns']['check'], $listOptions['form'], $listOptions['additional_rows'][0]);

				$context['sub_template'] = 'show_list';
				$context['default_list'] = 'restore_file_permissions';
			}
			else
			{
				unset($listOptions['columns']['result']);
			}

			// Create the list for display.
			createList($listOptions);

			// If we just restored permissions then wherever we are, we are now done and dusted.
			if (!empty($this->_req->getPost('restore_perms')))
			{
				obExit();
			}
		}
		// Otherwise, it's entirely irrelevant?
		elseif ($restore_write_status)
		{
			return true;
		}
	}

	/**
	 * Prepares $context['package_ftp'] with whatever information we may have available.
	 *
	 * @param \ElkArte\Http\FtpConnection|null $ftp
	 * @param array $chmodOptions
	 * @param array $return_data
	 */
	public function reportUnWritable($ftp, $chmodOptions, $return_data)
	{
		global $context;

		$ftp_server = $this->_req->getPost('ftp_server', 'trim');
		$ftp_port = $this->_req->getPost('ftp_port', 'intval');
		$ftp_username = $this->_req->getPost('ftp_username', 'trim');
		$ftp_path = $this->_req->getPost('ftp_path', 'trim');
		$ftp_error = $_SESSION['ftp_connection']['error'] ?? null;

		if (!isset($ftp) || $ftp->error !== false)
		{
			if (!isset($ftp))
			{
				$ftp = new FtpConnection(null);
			}
			elseif ($ftp->error !== false && !isset($ftp_error))
			{
				$ftp_error = $ftp->last_message ?? '';
			}

			list ($username, $detect_path, $found_path) = $ftp->detect_path(BOARDDIR);

			if ($found_path)
			{
				$ftp_path = $detect_path;
			}
			elseif (!isset($ftp_path))
			{
				$ftp_path = $this->_modSettings['package_path'] ?? $detect_path;
			}
		}

		// Place some hopefully useful information in the form
		$context['package_ftp'] = array(
			'server' => $ftp_server ?? ($this->_modSettings['package_server'] ?? 'localhost'),
			'port' => $ftp_port ?? ($this->_modSettings['package_port'] ?? '21'),
			'username' => $ftp_username ?? ($this->_modSettings['package_username'] ?? $username ?? ''),
			'path' => $ftp_path ?? ($this->_modSettings['package_path'] ?? ''),
			'error' => empty($ftp_error) ? null : $ftp_error,
			'destination' => !empty($chmodOptions['destination_url']) ? $chmodOptions['destination_url'] : '',
		);

		// Which files failed?
		$context['notwritable_files'] = $context['notwritable_files'] ?? [];
		$context['notwritable_files'] = array_merge($context['notwritable_files'], $return_data['files']['notwritable']);
	}

	/**
	 * Using the user supplied FTP information, attempts to create a connection.  If
	 * successful will save the supplied data in session for use in other steps.
	 *
	 * @return \ElkArte\Http\FtpConnection
	 */
	public function getFTPControl()
	{
		global $package_ftp;

		// CLean up what was sent
		$server = $this->_req->getPost('ftp_server', 'trim', '');
		$port = $this->_req->getPost('ftp_port', 'intval', 21);
		$username = $this->_req->getPost('ftp_username', 'trim', '');
		$password = $this->_req->getPost('ftp_password', 'trim', '');
		$path = $this->_req->getPost('ftp_path', 'trim', '');

		$ftp = new FtpConnection($server, $port, $username, $password);

		// We're connected, jolly good!
		if ($ftp->error === false)
		{
			// Common mistake, so let's try to remedy it...
			if (!$ftp->chdir($path))
			{
				$ftp_error = $ftp->last_message;

				if ($ftp->chdir(preg_replace('~^/home[2]?/[^/]+~', '', $path)))
				{
					$path = preg_replace('~^/home[2]?/[^/]+~', '', $path);
					$ftp_error = $ftp->last_message;
				}
			}

			// A valid path was entered
			if (!in_array($path, array('', '/')) && empty($ftp_error))
			{
				$ftp_root = substr(BOARDDIR, 0, -strlen($path));

				// Avoid double//slash entries
				if (substr($ftp_root, -1) === '/' && (substr($path, 0, 1) === '/'))
				{
					$ftp_root = substr($ftp_root, 0, -1);
				}
			}
			else
			{
				$ftp_root = BOARDDIR;
			}

			$_SESSION['ftp_connection'] = array(
				'server' => $server,
				'port' => $port,
				'username' => $username,
				'password' => $this->packageCrypt($password),
				'path' => $path,
				'root' => rtrim($ftp_root, '\/'),
				'connected' => true,
				'error' => empty($ftp_error) ? null : $ftp_error,
			);

			if (!isset($this->_modSettings['package_path']) || $this->_modSettings['package_path'] !== $path)
			{
				updateSettings(array('package_path' => $path));
			}

			// This is now the primary connection.
			$package_ftp = $ftp;
		}

		return $ftp;
	}

	/**
	 * Try to make a file writable using PHP and/or FTP if available
	 *
	 * @param string $filename
	 * @param bool $track_change = false
	 *
	 * @return bool True if it worked, false if it didn't
	 * @package Packages
	 */
	public function pkgChmod($filename, $track_change = false)
	{
		global $package_ftp;

		// File is already writable, easy
		if ($this->fileFunc->isWritable($filename))
		{
			return true;
		}

		// If we don't have FTP, see if we can get this done
		if (!isset($package_ftp) || $package_ftp === false)
		{
			return $this->chmodNoFTP($filename, $track_change);
		}

		// If we have FTP, then we take it for a spin
		if (!empty($_SESSION['ftp_connection']))
		{
			return $this->chmodWithFTP($filename, $track_change);
		}

		// Oh dear, we failed if we get here.
		return false;
	}

	/**
	 * Try to make a file writable using built in PHP SplFileInfo() functions
	 *
	 * @param string $filename
	 * @param bool $track_change = false
	 * @return bool True if it worked, false if it didn't
	 */
	public function chmodNoFTP($filename, $track_change)
	{
		$chmod_file = $filename;

		for ($i = 0; $i < 2; $i++)
		{
			// Start off with a less aggressive test.
			if ($i === 0)
			{
				// If this file doesn't exist, then we actually want to look at whatever parent directory does.
				$subTraverseLimit = 2;
				while (!$this->fileFunc->fileExists($chmod_file) && $subTraverseLimit)
				{
					$chmod_file = dirname($chmod_file);
					$subTraverseLimit--;
				}

				// Keep track of the writable status here.
				$file_permissions = $this->fileFunc->filePerms($chmod_file);
			}
			elseif (!$this->fileFunc->fileExists($chmod_file))
			{
				// This looks odd, but it's an attempt to work around PHP suExec.
				$file_permissions = $this->fileFunc->filePerms(dirname($chmod_file));
				mktree(dirname($chmod_file));
				@touch($chmod_file);
				$this->fileFunc->elk_chmod($chmod_file, 0755);
			}
			else
			{
				$file_permissions = $this->fileFunc->filePerms($chmod_file);
			}

			// Let chmod make this file or directory writable
			$this->fileFunc->chmod($chmod_file);

			// The ultimate writable test.
			if ($this->testAccess($chmod_file))
			{
				// It worked!
				if ($track_change)
				{
					$_SESSION['ftp_connection']['original_perms'][$chmod_file] = $file_permissions;
				}

				return true;
			}

			if (isset($_SESSION['ftp_connection']['original_perms'][$chmod_file]))
			{
				unset($_SESSION['ftp_connection']['original_perms'][$chmod_file]);
			}
		}

		// If we're here we're a failure.
		return false;
	}

	/**
	 * Try to make a file writable using FTP functions
	 *
	 * @param string $filename
	 * @param bool $track_change = false
	 * @return bool True if it worked, false if it didn't
	 */
	public function chmodWithFTP($filename, $track_change)
	{
		/** @var $package_ftp \ElkArte\Http\FtpConnection */
		global $package_ftp;

		$ftp_file = setFtpName($filename);

		// If the file does not yet exist, make sure its directory is at least writable
		if (!$this->fileFunc->fileExists($filename) && !$this->fileFunc->isDir($filename))
		{
			$file_permissions = $this->fileFunc->filePerms(dirname($filename));

			// Make sure the directory exits and is writable
			mktree(dirname($filename));

			$package_ftp->create_file($ftp_file);
			$package_ftp->chmod($ftp_file, 0755);
		}
		else
		{
			$file_permissions = $this->fileFunc->filePerms($filename);
		}

		// Directories
		if (!$this->fileFunc->isWritable(dirname($filename)))
		{
			$package_ftp->ftp_chmod(dirname($ftp_file), [0775, 0777]);
		}

		if ($this->fileFunc->isDir($filename) && !$this->fileFunc->isWritable($filename))
		{
			$package_ftp->ftp_chmod($ftp_file, [0775, 0777]);
		}

		// File
		if (!$this->fileFunc->isDir($filename) && !$this->fileFunc->isWritable($filename))
		{
			$package_ftp->ftp_chmod($ftp_file, [0664, 0666]);
		}

		if ($this->fileFunc->isWritable($filename))
		{
			if ($track_change)
			{
				$_SESSION['ftp_connection']['original_perms'][$filename] = $file_permissions;
			}

			return true;
		}

		return false;
	}

	/**
	 * The ultimate writable test.
	 *
	 * Mind you, I'm not sure why this is needed if PHP says it is writable, but
	 * sometimes you have to be a lemming. Plus windows ACL is not handled well.
	 *
	 * @param $item
	 * @return bool
	 */
	public function testAccess($item)
	{
		$fp = $this->fileFunc->isDir($item) ? @opendir($item) : @fopen($item, 'rb');
		if ($this->fileFunc->isWritable($item) && $fp !== false)
		{
			if (!$this->fileFunc->isDir($item))
			{
				fclose($fp);
			}
			else
			{
				closedir($fp);
			}

			return true;
		}

		return false;
	}

	/**
	 * Used to crypt the supplied ftp password in this session
	 *
	 * - Don't be fooled by the name, this is a reversing hash function.
	 *  It will hash a password, and if supplied that hash will return the
	 *  original password.  Uses the session_id as salt
	 *
	 * @param string $pass
	 * @return string The encrypted password
	 * @package Packages
	 */
	public function packageCrypt($pass)
	{
		$n = strlen($pass);

		$salt = session_id();
		while (strlen($salt) < $n)
		{
			$salt .= session_id();
		}

		for ($i = 0; $i < $n; $i++)
		{
			$pass[$i] = chr(ord($pass[$i]) ^ (ord($salt[$i]) - 32));
		}

		return $pass;
	}
}