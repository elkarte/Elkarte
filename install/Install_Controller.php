<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1.9
 *
 */

/**
 * This does the installation steps
 */
class Install_Controller
{
	public $steps = array();
	public $overall_percent = 0;

	public function __construct()
	{
		global $txt;

		// All the steps in detail.
		// Number,Name,Function,Progress Weight.
		$this->steps = array(
			0 => array(1, $txt['install_step_welcome'], 'action_welcome', 0),
			1 => array(2, $txt['install_step_exist'], 'action_checkFilesExist', 5),
			2 => array(3, $txt['install_step_writable'], 'action_checkFilesWritable', 5),
			3 => array(4, $txt['install_step_databaseset'], 'action_databaseSettings', 15),
			4 => array(5, $txt['install_step_forum'], 'action_forumSettings', 40),
			5 => array(6, $txt['install_step_databasechange'], 'action_databasePopulation', 15),
			6 => array(7, $txt['install_step_admin'], 'action_adminAccount', 20),
			7 => array(8, $txt['install_step_delete'], 'action_deleteInstall', 0),
		);
	}

	public function dispatch($current_step)
	{
		global $incontext;

		// Loop through all the steps doing each one as required.
		foreach ($this->steps as $num => $step)
		{
			if ($num >= $current_step)
			{
				// The current weight of this step in terms of overall progress.
				$incontext['step_weight'] = $step[3];

				// Call the step and if it returns false that means pause!
				if ($this->{$step[2]}() === false)
					break;
				else
					$current_step++;

				// No warnings pass on.
				$incontext['warning'] = '';
			}
			$this->overall_percent += $step[3];
		}

		return $current_step;
	}

	/**
	 * Welcome screen.
	 * It makes a few basic checks for compatibility
	 * and informs the user if there are problems.
	 */
	private function action_welcome()
	{
		global $incontext, $txt, $databases, $installurl, $db_type;

		$incontext['page_title'] = $txt['install_welcome'];
		$incontext['sub_template'] = 'welcome_message';

		// Done the submission?
		if (isset($_POST['contbutt']))
			return true;

		// Check the PHP version.
		if (version_compare(REQUIRED_PHP_VERSION, PHP_VERSION, '>'))
		{
			$incontext['warning'] = $txt['error_php_too_low'];
		}

		// See if we think they have already installed it?
		if (is_readable(TMP_BOARDDIR . '/Settings.php'))
		{
			$probably_installed = 0;
			foreach (file(TMP_BOARDDIR . '/Settings.php') as $line)
			{
				if (preg_match('~^\$boarddir\s=\s\'([^\']+)\';$~', $line))
					$probably_installed++;
				if (preg_match('~^\$boardurl\s=\s\'([^\']+)\';~', $line) && !preg_match('~^\$boardurl\s=\s\'http://127\.0\.0\.1/elkarte\';~', $line))
					$probably_installed++;
			}

			if ($probably_installed == 2)
				$incontext['warning'] = str_replace('{try_delete}', '
		<div id="delete_label" style="font-weight: bold; display: none">
			<label for="delete_self"><input type="checkbox" id="delete_self" onclick="doTheDelete();" class="input_check" /> ' . $txt['delete_installer'] . (!isset($_SESSION['installer_temp_ftp']) ? ' ' . $txt['delete_installer_maybe'] : '') . '</label>
		<script><!-- // --><![CDATA[
			function doTheDelete()
			{
				var theCheck = document.getElementById ? document.getElementById("delete_self") : document.all.delete_self,
					tempImage = new Image();

				tempImage.src = "' . $installurl . '?delete=1&ts_" + (new Date().getTime());
				tempImage.width = 0;
				theCheck.disabled = true;
				window.location.href = elk_scripturl;
			}
			document.getElementById(\'delete_label\').style.display = \'block\';
		// ]]></script>
		</div>', $txt['error_already_installed']);
		}

		// If there is no Settings.php then we need a new one that only the owner can provide
		if (!file_exists(TMP_BOARDDIR . '/Settings.php'))
		{
			$incontext['infobox'] = $txt['error_no_settings'];
		}

		// Is some database support even compiled in?
		$incontext['supported_databases'] = array();
		$db_missing = array();
		foreach ($databases as $key => $db)
		{
			if ($db['supported'])
			{
				if (!empty($db['additional_file']) && !file_exists(__DIR__ . '/' . $db['additional_file']))
				{
					$databases[$key]['supported'] = false;
					$notFoundSQLFile = true;
					$txt['error_db_script_missing'] = sprintf($txt['error_db_script_missing'], $db['additional_file']);
				}
				else
				{
					$db_type = $key;
					$incontext['supported_databases'][] = $db;
				}
			}
			else
				$db_missing[] = $db['extension'];
		}

		if (count($db_missing) === count($databases))
			$incontext['error'] = sprintf($txt['error_db_missing'], implode(', ', $db_missing));
		elseif (empty($incontext['supported_databases']))
			$error = empty($notFoundSQLFile) ? 'error_db_missing' : 'error_db_script_missing';
		// How about session support?  Some crazy sysadmin remove it?
		elseif (!function_exists('session_start'))
			$error = 'error_session_missing';
		// Make sure they uploaded all the files.
		elseif (!file_exists(TMP_BOARDDIR . '/index.php'))
			$error = 'error_missing_files';
		// Very simple check on the session.save_path for Windows.
		// @todo Move this down later if they don't use database-driven sessions?
		elseif (@ini_get('session.save_path') == '/tmp' && substr(__FILE__, 1, 2) == ':\\')
			$error = 'error_session_save_path';

		// Since each of the three messages would look the same, anyway...
		if (isset($error))
			$incontext['error'] = $txt[$error];

		// Mod_security blocks everything that smells funny. Let us handle security.
		if (!fixModSecurity() && !isset($_GET['overmodsecurity']))
			$incontext['error'] = $txt['error_mod_security'] . '<br /><br /><a href="' . $installurl . '?overmodsecurity=true">' . $txt['error_message_click'] . '</a> ' . $txt['error_message_bad_try_again'];

		return false;
	}

	/**
	 * Verify and try to make writable the files and folders that need to be.
	 */
	private function action_checkFilesExist()
	{
		global $incontext, $txt;

		$incontext['page_title'] = $txt['install_welcome'];
		$incontext['sub_template'] = 'welcome_message';

		$exist_files = array(
			'db_last_error.sample.txt' => 'db_last_error.txt',
			'Settings.sample.php' => 'Settings.php',
			'Settings_bak.sample.php' => 'Settings_bak.php'
		);
		$missing_files = array();

		foreach ($exist_files as $orig => $file)
		{
			// First thing (for convenience' sake) if they are not there yet,
			// try to rename Settings and Settings_bak and db_last_error
			if (!file_exists(TMP_BOARDDIR . '/' . $file))
			{
				// Silenced because the source file may or may not exist
				@rename (TMP_BOARDDIR. '/' . $orig, TMP_BOARDDIR . '/' . $file);

				// If it still doesn't exist, add it to the missing list
				if (!file_exists(TMP_BOARDDIR . '/' . $file))
					$missing_files[$orig] = $file;
			}
		}

		if (empty($missing_files))
			return true;

		$rename_array = array();
		foreach ($missing_files as $orig => $file)
			$rename_array[] = '<li>' . $orig . ' => ' . $file . '</li>';

		$incontext['error'] = sprintf($txt['error_settings_do_not_exist'], implode(', ', $missing_files), implode('', $rename_array));

		$incontext['retry'] = 1;

		return false;
	}

	/**
	 * Verify and try to make writable the files and folders that need to be.
	 */
	private function action_checkFilesWritable()
	{
		global $txt, $incontext;

		$incontext['page_title'] = $txt['ftp_checking_writable'];
		$incontext['sub_template'] = 'chmod_files';

		$writable_files = array(
			'attachments',
			'avatars',
			'cache',
			'packages',
			'packages/installed.list',
			'smileys',
			'themes',
			'agreement.txt',
			'privacypolicy.txt',
			'db_last_error.txt',
			'Settings.php',
			'Settings_bak.php'
		);
		foreach ($incontext['detected_languages'] as $lang => $temp)
			$extra_files[] = 'themes/default/languages/' . $lang;

		// With mod_security installed, we could attempt to fix it with .htaccess.
		if (function_exists('apache_get_modules') && in_array('mod_security', apache_get_modules()))
			$writable_files[] = file_exists(TMP_BOARDDIR . '/.htaccess') ? '.htaccess' : '.';

		$failed_files = array();

		// On linux, it's easy - just use is_writable!
		if (substr(__FILE__, 1, 2) != ':\\')
		{
			foreach ($writable_files as $file)
			{
				if (!is_writable(TMP_BOARDDIR . '/' . $file))
				{
					@chmod(TMP_BOARDDIR . '/' . $file, 0755);

					// Well, 755 hopefully worked... if not, try 777.
					if (!is_writable(TMP_BOARDDIR . '/' . $file) && !@chmod(TMP_BOARDDIR . '/' . $file, 0777))
						$failed_files[] = $file;
				}
			}
			foreach ($extra_files as $file)
				@chmod(TMP_BOARDDIR . (empty($file) ? '' : '/' . $file), 0777);
		}
		// Windows is trickier.  Let's try opening for r+...
		else
		{
			foreach ($writable_files as $file)
			{
				// Folders can't be opened for write... but the index.php in them can ;)
				if (is_dir(TMP_BOARDDIR . '/' . $file))
					$file .= '/index.php';

				// Funny enough, chmod actually does do something on windows - it removes the read only attribute.
				@chmod(TMP_BOARDDIR . '/' . $file, 0777);
				$fp = @fopen(TMP_BOARDDIR . '/' . $file, 'r+');

				// Hmm, okay, try just for write in that case...
				if (!is_resource($fp))
					$fp = @fopen(TMP_BOARDDIR . '/' . $file, 'w');

				if (!is_resource($fp))
					$failed_files[] = $file;

				@fclose($fp);
			}
			foreach ($extra_files as $file)
				@chmod(TMP_BOARDDIR . (empty($file) ? '' : '/' . $file), 0777);
		}

		$failure = count($failed_files) >= 1;

		if (!isset($_SERVER))
			return !$failure;

		// Put the list into context.
		$incontext['failed_files'] = $failed_files;

		// It's not going to be possible to use FTP on windows to solve the problem...
		if ($failure && substr(__FILE__, 1, 2) == ':\\')
		{
			$incontext['error'] = $txt['error_windows_chmod'] . '
						<ul style="margin: 2.5ex; font-family: monospace;">
							<li>' . implode('</li>
							<li>', $failed_files) . '</li>
						</ul>';

			return false;
		}
		// We're going to have to use... FTP!
		elseif ($failure)
		{
			// Load any session data we might have...
			if (!isset($_POST['ftp_username']) && isset($_SESSION['installer_temp_ftp']))
			{
				$_POST['ftp_server'] = $_SESSION['installer_temp_ftp']['server'];
				$_POST['ftp_port'] = $_SESSION['installer_temp_ftp']['port'];
				$_POST['ftp_username'] = $_SESSION['installer_temp_ftp']['username'];
				$_POST['ftp_password'] = $_SESSION['installer_temp_ftp']['password'];
				$_POST['ftp_path'] = $_SESSION['installer_temp_ftp']['path'];
			}

			$incontext['ftp_errors'] = array();

			if (isset($_POST['ftp_username']))
			{
				$ftp = new Ftp_Connection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

				if ($ftp->error === false)
				{
					// Try it without /home/abc just in case they messed up.
					if (!$ftp->chdir($_POST['ftp_path']))
					{
						$incontext['ftp_errors'][] = $ftp->last_message;
						$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
					}
				}
			}

			if (!isset($ftp) || $ftp->error !== false)
			{
				if (!isset($ftp))
					$ftp = new Ftp_Connection(null);
				// Save the error so we can mess with listing...
				elseif ($ftp->error !== false && empty($incontext['ftp_errors']) && !empty($ftp->last_message))
					$incontext['ftp_errors'][] = $ftp->last_message;

				list ($username, $detect_path, $found_path) = $ftp->detect_path(TMP_BOARDDIR);

				if (empty($_POST['ftp_path']) && $found_path)
					$_POST['ftp_path'] = $detect_path;

				if (!isset($_POST['ftp_username']))
					$_POST['ftp_username'] = $username;

				// Set the username etc, into context.
				$incontext['ftp'] = array(
					'server' => isset($_POST['ftp_server']) ? $_POST['ftp_server'] : 'localhost',
					'port' => isset($_POST['ftp_port']) ? $_POST['ftp_port'] : '21',
					'username' => isset($_POST['ftp_username']) ? $_POST['ftp_username'] : '',
					'path' => isset($_POST['ftp_path']) ? $_POST['ftp_path'] : '/',
					'path_msg' => !empty($found_path) ? $txt['ftp_path_found_info'] : $txt['ftp_path_info'],
				);

				return false;
			}
			else
			{
				$_SESSION['installer_temp_ftp'] = array(
					'server' => $_POST['ftp_server'],
					'port' => $_POST['ftp_port'],
					'username' => $_POST['ftp_username'],
					'password' => $_POST['ftp_password'],
					'path' => $_POST['ftp_path']
				);

				$failed_files_updated = array();

				foreach ($failed_files as $file)
				{
					if (!is_writable(TMP_BOARDDIR . '/' . $file))
						$ftp->chmod($file, 0755);
					if (!is_writable(TMP_BOARDDIR . '/' . $file))
						$ftp->chmod($file, 0777);
					if (!is_writable(TMP_BOARDDIR . '/' . $file))
					{
						$failed_files_updated[] = $file;
						$incontext['ftp_errors'][] = rtrim($ftp->last_message) . ' -> ' . $file . "\n";
					}
				}

				$ftp->close();

				// Are there any errors left?
				if (count($failed_files_updated) >= 1)
				{
					// Guess there are...
					$incontext['failed_files'] = $failed_files_updated;

					// Set the username etc, into context.
					$incontext['ftp'] = $_SESSION['installer_temp_ftp'] += array(
						'path_msg' => $txt['ftp_path_info'],
					);

					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Ask for database settings, verify and save them.
	 */
	private function action_databaseSettings()
	{
		global $txt, $databases, $incontext, $db_type, $db_connection, $modSettings, $db_server, $db_name, $db_user, $db_passwd;

		$incontext['sub_template'] = 'database_settings';
		$incontext['page_title'] = $txt['db_settings'];
		$incontext['continue'] = 1;

		// Set up the defaults.
		$incontext['db']['server'] = 'localhost';
		$incontext['db']['port'] = '';
		$incontext['db']['user'] = '';
		$incontext['db']['name'] = '';
		$incontext['db']['pass'] = '';
		$incontext['db']['type'] = '';
		$incontext['supported_databases'] = array();

		$foundOne = false;
		foreach ($databases as $key => $db)
		{
			// Override with the defaults for this DB if appropriate.
			if ($db['supported'])
			{
				$incontext['supported_databases'][$key] = $db;

				if (!$foundOne)
				{
					if (isset($db['default_host']))
					{
						$default_host = ini_get($db['default_host']);
						$incontext['db']['server'] = !empty($default_host) ? $default_host : 'localhost';
					}
					if (isset($db['default_user']))
					{
						$incontext['db']['user'] = ini_get($db['default_user']);
						$incontext['db']['name'] = ini_get($db['default_user']);
					}
					if (isset($db['default_password']))
						$incontext['db']['pass'] = ini_get($db['default_password']);
					if (isset($db['default_port']))
						$db_port = ini_get($db['default_port']);

					$incontext['db']['type'] = $key;
					$foundOne = true;
				}
			}
		}

		// Override for repost.
		if (isset($_POST['db_user']))
		{
			$incontext['db']['user'] = $_POST['db_user'];
			$incontext['db']['name'] = $_POST['db_name'];
			$incontext['db']['server'] = $_POST['db_server'];
			$incontext['db']['port'] = !empty($_POST['db_port']) ? $_POST['db_port'] : '';
			$incontext['db']['prefix'] = $_POST['db_prefix'];
		}
		else
		{
			$incontext['db']['prefix'] = 'elkarte_';
		}

		// Are we submitting?
		if (isset($_POST['db_type']))
		{
			if (isset($_POST['db_filename']))
			{
				// You better enter enter a database name for SQLite.
				if (trim($_POST['db_filename']) == '')
				{
					$incontext['error'] = $txt['error_db_filename'];
					return false;
				}

				// Duplicate name in the same dir?  Can't do that with SQLite.  Weird things happen.
				if (file_exists($_POST['db_filename'] . (substr($_POST['db_filename'], -3) != '.db' ? '.db' : '')))
				{
					$incontext['error'] = $txt['error_db_filename_exists'];
					return false;
				}
			}

			// What type are they trying?
			$db_type = preg_replace('~[^A-Za-z0-9]~', '', $_POST['db_type']);
			$db_prefix = $_POST['db_prefix'];

			// Validate the prefix.
			$valid_prefix = $databases[$db_type]['validate_prefix']($db_prefix);
			if ($valid_prefix !== true)
			{
				$incontext['error'] = $valid_prefix;
				return false;
			}

			// Take care of these variables...
			$vars = array(
				'db_type' => $db_type,
				'db_name' => $_POST['db_name'],
				'db_user' => $_POST['db_user'],
				'db_passwd' => isset($_POST['db_passwd']) ? $_POST['db_passwd'] : '',
				'db_server' => $_POST['db_server'],
				'db_port' => !empty($_POST['db_port']) ? $_POST['db_port'] : '',
				'db_prefix' => $db_prefix,
				// The cookiename is special; we want it to be the same if it ever needs to be reinstalled with the same info.
				'cookiename' => 'ElkArteCookie' . abs(crc32($_POST['db_name'] . preg_replace('~[^A-Za-z0-9_$]~', '', $_POST['db_prefix'])) % 1000),
			);

			// God I hope it saved!
			if (!updateSettingsFile($vars))
			{
				$incontext['error'] = $txt['settings_error'];
				return false;
			}

			// Make sure it works.
			require(TMP_BOARDDIR . '/Settings.php');

			if (!defined('SOURCEDIR'))
				define('SOURCEDIR', TMP_BOARDDIR . '/sources');

			if (!defined('SUBSDIR'))
				define('SUBSDIR', TMP_BOARDDIR . '/sources/subs');

			// Better find the database file!
			if (!file_exists(SOURCEDIR . '/database/Db-' . $db_type . '.class.php'))
			{
				$incontext['error'] = sprintf($txt['error_db_file'], 'Db-' . $db_type . '.class.php');
				return false;
			}

			// Now include it for database functions!
			define('ELK', 1);
			$modSettings['disableQueryCheck'] = true;

			require_once(SOURCEDIR . '/database/Database.subs.php');
			require_once(SUBSDIR . '/Util.class.php');

			// Attempt a connection.
			$db_connection = elk_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('non_fatal' => true, 'dont_select_db' => true, 'port' => $db_port), $db_type);
			$db = database();

			// No dice?  Let's try adding the prefix they specified, just in case they misread the instructions ;)
			if ($db_connection === null)
			{
				$db_error = $db->last_error();

				$db_connection = elk_db_initiate($db_server, $db_name, $_POST['db_prefix'] . $db_user, $db_passwd, $db_prefix, array('non_fatal' => true, 'dont_select_db' => true, 'port' => $db_port), $db_type);
				if ($db_connection !== null)
				{
					$db_user = $_POST['db_prefix'] . $db_user;
					updateSettingsFile(array('db_user' => $db_user));
				}
			}

			// Still no connection?  Big fat error message :P.
			if (!$db_connection)
			{
				$incontext['error'] = $txt['error_db_connect'] . '<div style="margin: 2.5ex; font-family: monospace;"><strong>' . $db_error . '</strong></div>';
				return false;
			}

			// Do they meet the install requirements?
			// @todo Old client, new server?
			if (!db_version_check())
			{
				$incontext['error'] = $txt['error_db_too_low'];
				return false;
			}

			// Let's try that database on for size... assuming we haven't already lost the opportunity.
			if ($db_name != '')
			{
				$db->skip_next_error();
				$db->query('', "
					CREATE DATABASE IF NOT EXISTS `$db_name`",
					array(
						'security_override' => true,
					),
					$db_connection
				);

				// Okay, let's try the prefix if it didn't work...
				if (!$db->select_db($db_name, $db_connection) && $db_name != '')
				{
					$db->skip_next_error();
					$db->query('', "
						CREATE DATABASE IF NOT EXISTS `$_POST[db_prefix]$db_name`",
						array(
							'security_override' => true,
						),
						$db_connection
					);

					if ($db->select_db($_POST['db_prefix'] . $db_name, $db_connection))
					{
						$db_name = $_POST['db_prefix'] . $db_name;
						updateSettingsFile(array('db_name' => $db_name));
					}
				}

				// Okay, now let's try to connect...
				if (!$db->select_db($db_name, $db_connection))
				{
					$incontext['error'] = sprintf($txt['error_db_database'], $db_name);
					return false;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Basic forum type settings.
	 */
	private function action_forumSettings()
	{
		global $txt, $incontext, $databases, $db_type, $db_connection;

		$incontext['sub_template'] = 'forum_settings';
		$incontext['page_title'] = $txt['install_settings'];

		if (!defined('SUBSDIR'))
			define('SUBSDIR', TMP_BOARDDIR . '/sources/subs');

		require_once(SUBSDIR . '/Util.class.php');

		// Let's see if we got the database type correct.
		if (isset($_POST['db_type'], $databases[$_POST['db_type']]))
			$db_type = $_POST['db_type'];

		// Else we'd better be able to get the connection.
		else
			load_database();

		$db_type = isset($_POST['db_type']) ? $_POST['db_type'] : $db_type;

		// What host and port are we on?
		$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];

		// Now, to put what we've learned together... and add a path.
		$incontext['detected_url'] = 'http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 's' : '') . '://' . $host . strtr(substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/')), array('/install' => ''));

		// Check if the database sessions will even work.
		$incontext['test_dbsession'] = ini_get('session.auto_start') != 1;
		$incontext['continue'] = 1;

		// Submitting?
		if (isset($_POST['boardurl']))
		{
			if (substr($_POST['boardurl'], -10) == '/index.php')
				$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
			elseif (substr($_POST['boardurl'], -1) == '/')
				$_POST['boardurl'] = substr($_POST['boardurl'], 0, -1);

			if (substr($_POST['boardurl'], 0, 7) != 'http://' && substr($_POST['boardurl'], 0, 7) != 'file://' && substr($_POST['boardurl'], 0, 8) != 'https://')
				$_POST['boardurl'] = 'http://' . $_POST['boardurl'];

			// Save these variables.
			$vars = array(
				'boardurl' => $_POST['boardurl'],
				'boarddir' => addslashes(TMP_BOARDDIR),
				'sourcedir' => addslashes(TMP_BOARDDIR) . '/sources',
				'cachedir' => addslashes(TMP_BOARDDIR) . '/cache',
				'mbname' => strtr($_POST['mbname'], array('\"' => '"')),
				'language' => substr($_SESSION['installer_temp_lang'], 8, -4),
				'extdir' => addslashes(TMP_BOARDDIR) . '/sources/ext',
			);

			// Must save!
			if (!updateSettingsFile($vars))
			{
				$incontext['error'] = $txt['settings_error'];
				return false;
			}

			// Make sure it works.
			require(TMP_BOARDDIR . '/Settings.php');

			// UTF-8 requires a setting to override any language charset.
			if (!empty($databases[$db_type]['utf8_version_check']) && version_compare($databases[$db_type]['utf8_version'], preg_replace('~\-.+?$~', '', $databases[$db_type]['utf8_version_check']($db_connection)), '>'))
			{
				// our uft-8 check support on the db failed ....
				$incontext['error'] = sprintf($txt['error_utf8_version'], $databases[$db_type]['utf8_version']);
				return false;
			}
			else
				// Set our db character_set to utf8
				updateSettingsFile(array('db_character_set' => 'utf8'));

			// Good, skip on.
			return true;
		}

		definePaths();

		return false;
	}

	/**
	 * Step one. Populate database.
	 */
	private function action_databasePopulation()
	{
		global $txt, $databases, $modSettings, $db_type, $db_prefix, $incontext, $db_name, $boardurl;

		$incontext['sub_template'] = 'populate_database';
		$incontext['page_title'] = $txt['db_populate'];
		$incontext['continue'] = 1;

		// Already done?
		if (isset($_POST['pop_done']))
			return true;

		// Reload settings.
		require(TMP_BOARDDIR . '/Settings.php');
		definePaths();

		$db = load_database();
		db_table_install();

		// Before running any of the queries, let's make sure another version isn't already installed.
		$db->skip_next_error();
		$result = $db->query('', '
			SELECT 
			    variable, value
			FROM {db_prefix}settings',
			array()
		);

		$modSettings = array();
		if ($result !== false)
		{
			while ($row = $db->fetch_assoc($result))
				$modSettings[$row['variable']] = $row['value'];
			$db->free_result($result);

			// Do they match?  If so, this is just a refresh so charge on!
			if (!isset($modSettings['elkVersion']) || $modSettings['elkVersion'] != CURRENT_VERSION)
			{
				$incontext['error'] = $txt['error_versions_do_not_match'];
				return false;
			}
		}
		$modSettings['disableQueryCheck'] = true;
		$modSettings['time_offset'] = empty($modSettings['time_offset']) ? 0 : $modSettings['time_offset'];

		// Since we are UTF8, select it. PostgreSQL requires passing it as a string...
		$db->skip_next_error();
		$db->query('', '
			SET NAMES {'. ($db_type == 'postgresql' ? 'string' : 'raw') . ':utf8}',
			array(
				'utf8' => 'utf8',
			)
		);

		$replaces = array(
			'{$db_prefix}' => $db_prefix,
			'{BOARDDIR}' => TMP_BOARDDIR,
			'{$boardurl}' => $boardurl,
			'{$enableCompressedOutput}' => isset($_POST['compress']) ? '1' : '0',
			'{$databaseSession_enable}' => isset($_POST['dbsession']) ? '1' : '0',
			'{$current_version}' => CURRENT_VERSION,
			'{$current_time}' => time(),
			'{$sched_task_offset}' => 82800 + mt_rand(0, 86399),
		);

		foreach ($txt as $key => $value)
		{
			if (substr($key, 0, 8) == 'default_')
				$replaces['{$' . $key . '}'] = addslashes($value);
		}
		$replaces['{$default_reserved_names}'] = strtr($replaces['{$default_reserved_names}'], array('\\\\n' => '\\n'));

		// Execute the SQL.
		$exists = array();
		$incontext['failures'] = array();
		$incontext['sql_results'] = array(
			'tables' => 0,
			'inserts' => 0,
			'table_dups' => 0,
			'insert_dups' => 0,
		);

		if (!empty($databases[$db_type]['additional_file']))
		{
			parse_sqlLines(__DIR__ . '/' . $databases[$db_type]['additional_file'], $replaces);
		}

		// Read in the SQL.  Turn this on and that off... internationalize... etc.
		parse_sqlLines(__DIR__ . '/install_' . DB_SCRIPT_VERSION . '.php', $replaces);

		// Make sure UTF will be used globally.
		$db->insert('replace',
			$db_prefix . 'settings',
			array(
				'variable' => 'string-255', 'value' => 'string-65534',
			),
			array(
				'global_character_set', 'UTF-8',
			),
			array('variable')
		);

		// Better safe, than sorry, just in case the autoloader doesn't cope well with the upgrade
		require_once(TMP_BOARDDIR . '/sources/subs/Agreement.class.php');
		require_once(TMP_BOARDDIR . '/sources/subs/PrivacyPolicy.class.php');

		$agreement = new \Agreement('english');
		$success = $agreement->storeBackup();
		$db->insert('replace',
			$db_prefix . 'settings',
			array(
				'variable' => 'string-255', 'value' => 'string-65534',
			),
			array(
				'agreementRevision', $success,
			),
			array('variable')
		);

		if (file_exists(TMP_BOARDDIR . '/privacypolicy.txt'))
		{
			$privacypol = new \PrivacyPolicy('english');
			$success = $privacypol->storeBackup();
			$db->insert('replace',
				$db_prefix . 'settings',
				array(
					'variable' => 'string-255', 'value' => 'string-65534',
				),
				array(
					'privacypolicyRevision', $success,
				),
				array('variable')
			);
		}

		// Maybe we can auto-detect better cookie settings?
		preg_match('~^http[s]?://([^\.]+?)([^/]*?)(/.*)?$~', $boardurl, $matches);
		if (!empty($matches))
		{
			// Default = both off.
			$localCookies = false;
			$globalCookies = false;

			// Okay... let's see.  Using a subdomain other than www.? (not a perfect check.)
			if ($matches[2] != '' && (strpos(substr($matches[2], 1), '.') === false || in_array($matches[1], array('forum', 'board', 'community', 'forums', 'support', 'chat', 'help', 'talk', 'boards', 'www'))))
				$globalCookies = true;

			// If there's a / in the middle of the path, or it starts with ~... we want local.
			if (isset($matches[3]) && strlen($matches[3]) > 3 && (substr($matches[3], 0, 2) == '/~' || strpos(substr($matches[3], 1), '/') !== false))
				$localCookies = true;

			if ($globalCookies)
				$rows[] = array('globalCookies', '1');
			if ($localCookies)
				$rows[] = array('localCookies', '1');

			if (!empty($rows))
			{
				$db->insert('replace',
					$db_prefix . 'settings',
					array('variable' => 'string-255', 'value' => 'string-65534'),
					$rows,
					array('variable')
				);
			}
		}

		// As of PHP 5.1, setting a timezone is required.
		if (!isset($modSettings['default_timezone']))
		{
			$server_offset = mktime(0, 0, 0, 1, 1, 1970);
			$timezone_id = 'Etc/GMT' . ($server_offset > 0 ? '+' : '') . ($server_offset / 3600);
			if (date_default_timezone_set($timezone_id))
				$db->insert('',
					$db_prefix . 'settings',
					array(
						'variable' => 'string-255', 'value' => 'string-65534',
					),
					array(
						'default_timezone', $timezone_id,
					),
					array('variable')
				);
		}

		// Let's optimize those new tables.
		$tables = $db->db_list_tables($db_name, $db_prefix . '%');
		$db_table = db_table();
		foreach ($tables as $table)
		{
			if ($db_table->optimize($table) == -1)
			{
				$incontext['failures'][-1] = $db->last_error();
				break;
			}
		}

		// Check for the ALTER privilege.
		$db->skip_next_error();
		$can_alter_table = $db->query('', "
			ALTER TABLE {$db_prefix}log_digest
			ORDER BY id_topic",
				array()
			) === false;

		if (!empty($databases[$db_type]['alter_support']) && $can_alter_table)
		{
			$incontext['error'] = $txt['error_db_alter_priv'];
			return false;
		}

		if (!empty($exists))
		{
			$incontext['page_title'] = $txt['user_refresh_install'];
			$incontext['was_refresh'] = true;
		}

		return false;
	}

	/**
	 * Ask for the administrator login information.
	 */
	private function action_adminAccount()
	{
		global $txt, $db_type, $db_connection, $databases, $incontext, $db_prefix, $db_passwd, $webmaster_email;

		$incontext['sub_template'] = 'admin_account';
		$incontext['page_title'] = $txt['user_settings'];
		$incontext['continue'] = 1;

		// Need this to check whether we need the database password.
		require(TMP_BOARDDIR . '/Settings.php');
		if (!defined('ELK'))
			define('ELK', 1);
		definePaths();

		// These files may be or may not be already included, better safe than sorry for now
		require_once(SOURCEDIR . '/Subs.php');
		require_once(SUBSDIR . '/Util.class.php');

		$db = load_database();

		if (!isset($_POST['username']))
			$_POST['username'] = '';
		if (!isset($_POST['email']))
			$_POST['email'] = '';

		$incontext['username'] = htmlspecialchars(stripslashes($_POST['username']), ENT_COMPAT, 'UTF-8');
		$incontext['email'] = htmlspecialchars(stripslashes($_POST['email']), ENT_COMPAT, 'UTF-8');

		$incontext['require_db_confirm'] = empty($db_type) || !empty($databases[$db_type]['require_db_confirm']);

		$db->skip_next_error();
		// Only allow create an admin account if they don't have one already.
		$request = $db->query('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0
			LIMIT 1',
			array(
				'admin_group' => 1,
			)
		);

		// Skip the step if an admin already exists
		if ($db->num_rows($request) != 0)
		{
			return true;
		}
		$db->free_result($request);

		// Trying to create an account?
		if (isset($_POST['password1']) && !empty($_POST['contbutt']))
		{
			// Wrong password?
			if ($incontext['require_db_confirm'] && $_POST['password3'] != $db_passwd)
			{
				$incontext['error'] = $txt['error_db_connect'];
				return false;
			}

			// Not matching passwords?
			if ($_POST['password1'] != $_POST['password2'])
			{
				$incontext['error'] = $txt['error_user_settings_again_match'];
				return false;
			}

			// No password?
			if (strlen($_POST['password1']) < 4)
			{
				$incontext['error'] = $txt['error_user_settings_no_password'];
				return false;
			}

			if (!file_exists(SOURCEDIR . '/Subs.php'))
			{
				$incontext['error'] = $txt['error_subs_missing'];
				return false;
			}

			// Update the main contact email?
			if (!empty($_POST['email']) && (empty($webmaster_email) || $webmaster_email == 'noreply@myserver.com'))
				updateSettingsFile(array('webmaster_email' => $_POST['email']));

			// Work out whether we're going to have dodgy characters and remove them.
			$invalid_characters = preg_match('~[<>&"\'=\\\]~', $_POST['username']) != 0;
			$_POST['username'] = preg_replace('~[<>&"\'=\\\]~', '', $_POST['username']);

			$db->skip_next_error();
			$result = $db->query('', '
				SELECT id_member, password_salt
				FROM {db_prefix}members
				WHERE member_name = {string:username} OR email_address = {string:email}
				LIMIT 1',
				array(
					'username' => stripslashes($_POST['username']),
					'email' => stripslashes($_POST['email']),
				)
			);

			if ($db->num_rows($result) != 0)
			{
				list ($incontext['member_id'], $incontext['member_salt']) = $db->fetch_row($result);
				$db->free_result($result);

				$incontext['account_existed'] = $txt['error_user_settings_taken'];
			}
			elseif ($_POST['username'] == '' || strlen($_POST['username']) > 25)
			{
				// Try the previous step again.
				$incontext['error'] = $_POST['username'] == '' ? $txt['error_username_left_empty'] : $txt['error_username_too_long'];
				return false;
			}
			elseif ($invalid_characters || $_POST['username'] == '_' || $_POST['username'] == '|' || strpos($_POST['username'], '[code') !== false || strpos($_POST['username'], '[/code') !== false)
			{
				// Try the previous step again.
				$incontext['error'] = $txt['error_invalid_characters_username'];
				return false;
			}
			elseif (empty($_POST['email']) || !filter_var(stripslashes($_POST['email']), FILTER_VALIDATE_EMAIL) || strlen(stripslashes($_POST['email'])) > 255)
			{
				// One step back, this time fill out a proper email address.
				$incontext['error'] = sprintf($txt['error_valid_email_needed'], $_POST['username']);
				return false;
			}
			elseif ($_POST['username'] != '')
			{
				require_once(SUBSDIR . '/Auth.subs.php');

				$incontext['member_salt'] = substr(base64_encode(sha1(mt_rand() . microtime(), true)), 0, 16);

				// Format the username properly.
				$_POST['username'] = preg_replace('~[\t\n\r\x0B\0\xA0]+~', ' ', $_POST['username']);
				$ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 255) : '';

				// Get a security hash for this combination
				$password = stripslashes($_POST['password1']);
				$incontext['passwd'] = validateLoginPassword($password, '', $_POST['username'], true);

				$request = $db->insert('',
					$db_prefix . 'members',
					array(
						'member_name' => 'string-25', 'real_name' => 'string-25', 'passwd' => 'string', 'email_address' => 'string',
						'id_group' => 'int', 'posts' => 'int', 'date_registered' => 'int', 'hide_email' => 'int',
						'password_salt' => 'string', 'lngfile' => 'string', 'avatar' => 'string',
						'member_ip' => 'string', 'member_ip2' => 'string', 'buddy_list' => 'string', 'pm_ignore_list' => 'string',
						'message_labels' => 'string', 'website_title' => 'string', 'website_url' => 'string',
						'signature' => 'string', 'usertitle' => 'string', 'secret_question' => 'string',
						'additional_groups' => 'string', 'ignore_boards' => 'string', 'openid_uri' => 'string',
					),
					array(
						stripslashes($_POST['username']), stripslashes($_POST['username']), $incontext['passwd'], stripslashes($_POST['email']),
						1, 0, time(), 0,
						$incontext['member_salt'], '', '',
						$ip, $ip, '', '',
						'', '', '',
						'', '', '',
						'', '', '',
					),
					array('id_member')
				);

				// Awww, crud!
				if ($request === false)
				{
					$incontext['error'] = $txt['error_user_settings_query'] . '<br />
					<div style="margin: 2ex;">' . nl2br(htmlspecialchars($db->last_error($db_connection), ENT_COMPAT, 'UTF-8')) . '</div>';
					return false;
				}

				$incontext['member_id'] = $db->insert_id("{$db_prefix}members", 'id_member');
			}

			// If we're here we're good.
			return true;
		}

		return false;
	}

	/**
	 * Final step, clean up and a complete message!
	 */
	private function action_deleteInstall()
	{
		global $txt, $incontext, $db_character_set;
		global $databases, $modSettings, $user_info, $db_type;

		// A few items we will load in from settings and make avaialble.
		global $boardurl, $db_prefix, $cookiename, $mbname, $language;

		$incontext['page_title'] = $txt['congratulations'];
		$incontext['sub_template'] = 'delete_install';
		$incontext['continue'] = 0;

		require(TMP_BOARDDIR . '/Settings.php');
		if (!defined('ELK'))
			define('ELK', 1);
		definePaths();

		$db = load_database();

		if (!defined('SUBSDIR'))
			define('SUBSDIR', TMP_BOARDDIR . '/sources/subs');

		chdir(TMP_BOARDDIR);

		require_once(SOURCEDIR . '/Errors.class.php');
		require_once(SOURCEDIR . '/Logging.php');
		require_once(SOURCEDIR . '/Subs.php');
		require_once(SOURCEDIR . '/Load.php');
		require_once(SUBSDIR . '/Cache.subs.php');
		require_once(SOURCEDIR . '/Security.php');
		require_once(SUBSDIR . '/Auth.subs.php');
		require_once(SUBSDIR . '/Util.class.php');
		require_once(SOURCEDIR . '/Autoloader.class.php');
		$autoloder = Elk_Autoloader::instance();
		$autoloder->setupAutoloader(array(SOURCEDIR, SUBSDIR, CONTROLLERDIR, ADMINDIR, ADDONSDIR));
		$autoloder->register(SOURCEDIR, '\\ElkArte');

		// Bring a warning over.
		if (!empty($incontext['account_existed']))
		{
			$incontext['warning'] = $incontext['account_existed'];
		}

		if (!empty($db_character_set) && !empty($databases[$db_type]['utf8_support']))
		{
			$db->skip_next_error();
			$db->query('', '
				SET NAMES {raw:db_character_set}',
				array(
					'db_character_set' => $db_character_set,
				)
			);
		}

		// As track stats is by default enabled let's add some activity.
		$db->insert('ignore',
			'{db_prefix}log_activity',
			array('date' => 'date', 'topics' => 'int', 'posts' => 'int', 'registers' => 'int'),
			array(Util::strftime('%Y-%m-%d', time()), 1, 1, (!empty($incontext['member_id']) ? 1 : 0)),
			array('date')
		);

		// Take notes of when the installation was finished not to send to the
		// upgrade when pointing to index.php and the install directory is still there.
		updateSettingsFile(array('install_time' => time()));

		$db->skip_next_error();
		// We're going to want our lovely $modSettings now.
		$request = $db->query('', '
			SELECT variable, value
			FROM {db_prefix}settings',
			array()
		);
		// Only proceed if we can load the data.
		if ($request)
		{
			while ($row = $db->fetch_row($request))
				$modSettings[$row[0]] = $row[1];
			$db->free_result($request);
		}

		// Automatically log them in ;)
		if (isset($incontext['member_id']) && isset($incontext['member_salt']))
			setLoginCookie(3153600 * 60, $incontext['member_id'], hash('sha256', $incontext['passwd'] . $incontext['member_salt']));

		$db->skip_next_error();
		$result = $db->query('', '
			SELECT value
			FROM {db_prefix}settings
			WHERE variable = {string:db_sessions}',
			array(
				'db_sessions' => 'databaseSession_enable',
			)
		);

		if ($db->num_rows($result) != 0)
		{
			list ($db_sessions) = $db->fetch_row($result);
		}
		$db->free_result($result);

		if (empty($db_sessions))
			$_SESSION['admin_time'] = time();
		else
		{
			$_SERVER['HTTP_USER_AGENT'] = substr($_SERVER['HTTP_USER_AGENT'], 0, 211);

			$db->insert('replace',
				'{db_prefix}sessions',
				array(
					'session_id' => 'string', 'last_update' => 'int', 'data' => 'string',
				),
				array(
					session_id(), time(), 'USER_AGENT|s:' . strlen($_SERVER['HTTP_USER_AGENT']) . ':"' . $_SERVER['HTTP_USER_AGENT'] . '";admin_time|i:' . time() . ';',
				),
				array('session_id')
			);
		}

		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberStats();
		require_once(SUBSDIR . '/Messages.subs.php');
		updateMessageStats();
		require_once(SUBSDIR . '/Topic.subs.php');
		updateTopicStats();

		$db->skip_next_error();
		$request = $db->query('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_msg = 1
				AND modified_time = 0
			LIMIT 1',
			array()
		);

		if ($db->num_rows($request) > 0)
			updateStats('subject', 1, htmlspecialchars($txt['default_topic_subject']));
		$db->free_result($request);

		// Sanity check that they loaded earlier!
		if (isset($modSettings['recycle_board']))
		{
			// The variable is usually defined in index.php so lets just use our variable to do it for us.
			$forum_version = CURRENT_VERSION;

			// We've just installed!
			$user_info['ip'] = $_SERVER['REMOTE_ADDR'];
			$user_info['id'] = isset($incontext['member_id']) ? $incontext['member_id'] : 0;
			logAction('install', array('version' => $forum_version), 'admin');
		}

		// Some final context for the template.
		$incontext['dir_still_writable'] = is_writable(__DIR__) && substr(__FILE__, 1, 2) != ':\\';
		$incontext['probably_delete_install'] = isset($_SESSION['installer_temp_ftp']) || is_writable(__DIR__) || is_writable(__FILE__);

		return false;
	}
}
