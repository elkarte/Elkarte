<?php

/**
 * This file handles the package servers and packages download, in Package Servers
 * area of administration panel.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0.4
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * PackageServers controller handles browsing, adding and removing
 * package servers, and download of a package from them.
 *
 * @package Packages
 */
class PackageServers_Controller extends Action_Controller
{
	/**
	 * Called before all other methods when coming from the dispatcher or
	 * action class.  Loads language and templates files so they are available
	 * to the other methods.
	 */
	public function pre_dispatch()
	{
		// Use the Packages language file. (split servers?)
		loadLanguage('Packages');

		// Use the PackageServers template.
		loadTemplate('PackageServers', 'admin');
	}

	/**
	 * Main dispatcher for package servers. Checks permissions,
	 * load files, and forwards to the right method.
	 *
	 * - Accessed by action=admin;area=packageservers
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $txt, $context;

		// This is for admins only.
		isAllowedTo('admin_forum');

		// Load our subs.
		require_once(SUBSDIR . '/Package.subs.php');

		// Use the Packages language file. (split servers?)
		loadLanguage('Packages');

		// Use the PackageServers template.
		loadTemplate('PackageServers', 'admin');

		$context['page_title'] = $txt['package_servers'];

		// Here is a list of all the potentially valid actions.
		$subActions = array(
			'servers' => array($this, 'action_list'),
			'add' => array($this, 'action_add'),
			'browse' => array($this, 'action_browse'),
			'download' => array($this, 'action_download'),
			'remove' => array($this, 'action_remove'),
			'upload' => array($this, 'action_upload'),
			'upload2' => array($this, 'action_upload2'),
		);

		// Set up action/subaction stuff.
		$action = new Action('package_servers');

		// Now let's decide where we are taking this... call integrate_sa_package_servers
		$subAction = $action->initialize($subActions, 'servers');

		// For the template
		$context['sub_action'] = $subAction;

		// Lets just do it!
		$action->dispatch($subAction);
	}

	/**
	 * Load a list of package servers.
	 *
	 * - Accessed by action=admin;area=packageservers;sa=servers
	 */
	public function action_list()
	{
		global $txt, $context;

		require_once(SUBSDIR . '/PackageServers.subs.php');

		// Ensure we use the correct template, and page title.
		$context['sub_template'] = 'servers';
		$context['page_title'] .= ' - ' . $txt['download_packages'];

		// Load the list of servers.
		$context['servers'] = fetchPackageServers();

		// Check if we will be able to write new archives in /packages folder.
		$context['package_download_broken'] = !is_writable(BOARDDIR . '/packages') || !is_writable(BOARDDIR . '/packages/installed.list');

		if ($context['package_download_broken'])
			$this->ftp_connect();
	}

	/**
	 * Browse a server's list of packages.
	 *
	 * - Accessed by action=admin;area=packageservers;sa=browse
	 */
	public function action_browse()
	{
		global $txt, $scripturl, $forum_version, $context;

		// Load our subs worker.
		require_once(SUBSDIR . '/PackageServers.subs.php');

		$server = '';
		$url = '';
		$name = '';

		// Browsing the packages from a server
		if (isset($_GET['server']))
			list($name, $url, $server) = $this->_package_server();
		// Minimum required parameter did not exist so dump out.
		else
			fatal_lang_error('couldnt_connect', false);

		// Might take some time.
		@set_time_limit(60);

		// Fetch the package listing from the server and json decode
		$listing = json_decode(fetch_web_data($url));

		// List out the packages...
		$context['package_list'] = array();

		// Pick the correct template.
		$context['sub_template'] = 'package_list';
		$context['page_title'] = $txt['package_servers'] . ($name != '' ? ' - ' . $name : '');
		$context['package_server'] = $server;

		// If we received data
		if (!empty($listing))
		{
			// Load the installed packages
			$instadds = loadInstalledPackages();

			// Look through the list of installed mods and get version information for the compare
			$installed_adds = array();
			foreach ($instadds as $installed_add)
				$installed_adds[$installed_add['package_id']] = $installed_add['version'];

			$the_version = strtr($forum_version, array('ElkArte ' => ''));
			if (!empty($_SESSION['version_emulate']))
				$the_version = $_SESSION['version_emulate'];

			// Parse the json file, each section contains a category of addons
			$packageNum = 0;
			foreach ($listing as $packageSection => $section_items)
			{
				// Section title / header for the category
				$context['package_list'][$packageSection] = array(
					'title' => Util::htmlspecialchars(ucwords($packageSection)),
					'text' => '',
					'items' => array(),
				);

				// Load each package array as an item
				$section_count = 0;
				foreach ($section_items as $thisPackage)
				{
					// Read in the package info from the fetched data
					$package = $this->_load_package_json($thisPackage, $packageSection);

					// Check the install status
					$package['can_install'] = false;
					$is_installed = array_intersect(array_keys($installed_adds), $package['id']);
					$package['is_installed'] = !empty($is_installed);

					// Set the ID from our potential list should the ID not be provided in the package .yaml
					$package['id'] = $package['is_installed'] ? array_shift($is_installed) : $package['id'][0];

					// Version installed vs version available
					$package['is_current'] = !empty($package['is_installed']) && ($installed_adds[$package['id']] == $package['version']);
					$package['is_newer'] = !empty($package['is_installed']) && ($installed_adds[$package['id']] > $package['version']);

					// Set the package filename for downloading and pre-existence checking
					$base_name = $this->_rename_master($package['server']['download']);
					$package['filename'] = basename($package['server']['download']);

					// This package is either not installed, or installed but old.
					if (!$package['is_installed'] || (!$package['is_current'] && !$package['is_newer']))
					{
						// Does it claim to install on this version of ElkArte?
						$path_parts = pathinfo($base_name);
						if (!empty($thisPackage->elkversion) && isset($path_parts['extension']) && in_array($path_parts['extension'], array('zip', 'tar', 'gz', 'tar.gz')))
						{
							// No install range given, then set one, it will all work out in the end.
							$for = strpos($thisPackage->elkversion, '-') === false ? $thisPackage->elkversion . '-' . $the_version : $thisPackage->elkversion;
							$package['can_install'] = matchPackageVersion($the_version, $for);
						}
					}

					// See if this filename already exists on the server
					$already_exists = getPackageInfo($base_name);
					$package['download_conflict'] = is_array($already_exists) && $already_exists['id'] == $package['id'] && $already_exists['version'] != $package['version'];
					$package['count'] = ++$packageNum;

					// Build the download to server link
					$server_att = $server != '' ? ';server=' . $server : '';
					$current_url = ';section=' . $packageSection . ';num=' . $section_count;
					$package['download']['href'] = $scripturl . '?action=admin;area=packageservers;sa=download' . $server_att . $current_url . ';package=' . $package['filename'] . ($package['download_conflict'] ? ';conflict' : '') . ';' . $context['session_var'] . '=' . $context['session_id'];
					$package['download']['link'] = '<a href="' . $package['download']['href'] . '">' . $package['name'] . '</a>';

					// Add this package to the list
					$context['package_list'][$packageSection]['items'][$packageNum] = $package;
					$section_count++;
				}

				$context['package_list'][$packageSection]['text'] = sprintf($txt['mod_section_count'], $section_count);
			}
		}

		// Good time to sort the categories, the packages inside each category will be by last modification date.
		asort($context['package_list']);
	}

	/**
	 * Returns a package array filled with the json information
	 *
	 * - Uses the parsed json file from the selected package server
	 *
	 * @param object $thisPackage
	 * @param string $packageSection
	 *
	 * @return array
	 */
	private function _load_package_json($thisPackage, $packageSection)
	{
		// Populate the package info from the fetched data
		return array(
			'id' => !empty($thisPackage->pkid) ? array($thisPackage->pkid) : $this->_assume_id($thisPackage),
			'type' => $packageSection,
			'name' => Util::htmlspecialchars($thisPackage->title),
			'date' => htmlTime(strtotime($thisPackage->date)),
			'author' =>  Util::htmlspecialchars($thisPackage->author),
			'description' => !empty($thisPackage->short) ? Util::htmlspecialchars($thisPackage->short) : '',
			'version' => $thisPackage->version,
			'elkversion' => $thisPackage->elkversion,
			'license' => $thisPackage->license,
			'hooks' => $thisPackage->allhooks,
			'server' => array(
				'download' => filter_var($thisPackage->server[0]->download, FILTER_VALIDATE_URL)
					? $thisPackage->server[0]->download : '',
				'support' => filter_var($thisPackage->server[0]->support, FILTER_VALIDATE_URL)
					? $thisPackage->server[0]->support : '',
				'bugs' => filter_var($thisPackage->server[0]->bugs, FILTER_VALIDATE_URL)
					? $thisPackage->server[0]->bugs : '',
				'link' => filter_var($thisPackage->server[0]->url, FILTER_VALIDATE_URL)
					? $thisPackage->server[0]->url : '',
			),
		);
	}

	/**
	 * If no ID is provided for a package, create the most
	 * common ones based on author:package patterns
	 *
	 * - Should not be relied on
	 *
	 * @param object $thisPackage
	 *
	 * @return array
	 */
	private function _assume_id($thisPackage)
	{
		$under = str_replace(' ', '_', $thisPackage->title);
		$none = str_replace(' ', '', $thisPackage->title);

		return array (
			$thisPackage->author . ':' . $under,
			$thisPackage->author . ':' . $none,
			strtolower($thisPackage->author) . ':' . $under,
			strtolower($thisPackage->author) . ':' . $none,
			ucfirst($thisPackage->author) . ':' . $under,
			ucfirst($thisPackage->author) . ':' . $none,
		);
	}

	/**
	 * Determine the package file name so we can see if its been downloaded
	 *
	 * - Determines a unique package name given a master.xyz file
	 * - Create the name based on the repo name
	 * - removes invalid filename characters
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	private function _rename_master($name)
	{
		// Is this a "master" package from github or bitbucket?
		if (preg_match('~^http(s)?://(www.)?(bitbucket\.org|github\.com)/(.+?(master(\.zip|\.tar\.gz)))$~', $name, $matches) == 1)
		{
			// Name this master.zip based on repo name in the link
			$path_parts = pathinfo($matches[4]);
			list (, $newname,) = explode('/', $path_parts['dirname']);

			// Just to be safe, no invalid file characters
			$invalid = array_merge(array_map('chr', range(0, 31)), array('<', '>', ':', '"', '/', '\\', '|', '?', '*'));

			// We could read the package info and see if we have a duplicate id & version, however that is
			// not always accurate, especially when dealing with repos.  So for now just put in in no conflict mode
			// and do the save.
			if (isset($_GET['area'], $_GET['sa']) && $_GET['area'] == 'packageservers' && $_GET['sa'] == 'download')
				$_REQUEST['auto'] = true;

			return str_replace($invalid, '_', $newname) . $matches[6];
		}
		else
			return basename($name);
	}

	/**
	 * Download a package.
	 *
	 * What it does:
	 * - Accessed by action=admin;area=packageservers;sa=download
	 * - If server is set, loads json file from package server
	 *     - requires both section and num values to validate the file to download from the json file
	 * - If $_POST['byurl']) $_POST['filename'])) are set, will download a file from the url and save it as filename
	 * - If just $_POST['byurl']) is set will fetch that file and save it
	 *     - github and bitbucket master files are renamed to repo name to avoid collisions
	 * - Files are saved to the package directory and validate to be ElkArte packages
	 */
	public function action_download()
	{
		global $txt, $scripturl, $context;

		require_once(SUBSDIR . '/PackageServers.subs.php');

		// Use the downloaded sub template.
		$context['sub_template'] = 'downloaded';

		// Security is good...
		if (isset($_GET['server']))
			checkSession('get');
		else
			checkSession();

		// To download something, we need either a valid server or url.
		if (empty($_GET['server']) && (!empty($_GET['get']) && !empty($_POST['package'])))
			fatal_lang_error('package_get_error_is_zero', false);

		// Start off with nothing
		$package_name = '';
		$server = '';
		$url = '';

		// Download from a package server?
		if (!empty($_GET['server']))
		{
			list(, $url, $server) = $this->_package_server();

			// Fetch the package listing from the package server
			$listing = json_decode(fetch_web_data($url));

			// Find the requested package by section and number, make sure it matches
			$section = $listing->$_GET['section'];

			// This is what they requested, yes?
			if (basename($section[$_GET['num']]->server[0]->download) === $_GET['package'])
			{
				// Where to download it from
				$package_name = $this->_rename_master($section[$_GET['num']]->server[0]->download);
				$path_url = pathinfo($section[$_GET['num']]->server[0]->download);
				$url = isset($path_url['dirname']) ? $path_url['dirname'] . '/' : '';
			}
			// Not found or some monkey business
			else
				fatal_lang_error('package_cant_download', false);
		}
		// Entered a url and optional filename
		elseif (isset($_POST['byurl']) && !empty($_POST['filename']))
			$package_name = basename($_POST['filename']);
		// Must just be a link then
		else
			$package_name = $this->_rename_master($_POST['package']);

		// Avoid over writing any existing package files of the same name
		if (isset($_REQUEST['conflict']) || (isset($_REQUEST['auto']) && file_exists(BOARDDIR . '/packages/' . $package_name)))
		{
			// Find the extension, change abc.tar.gz to abc_1.tar.gz...
			if (strrpos(substr($package_name, 0, -3), '.') !== false)
			{
				$ext = substr($package_name, strrpos(substr($package_name, 0, -3), '.'));
				$package_name = substr($package_name, 0, strrpos(substr($package_name, 0, -3), '.')) . '_';
			}
			else
				$ext = '';

			// Find the first available free name
			$i = 1;
			while (file_exists(BOARDDIR . '/packages/' . $package_name . $i . $ext))
				$i++;

			$package_name = $package_name . $i . $ext;
		}

		// First make sure it's a package.

		$packageInfo = getPackageInfo($url . $_REQUEST['package']);
		if (!is_array($packageInfo))
			fatal_lang_error($packageInfo);

		// Save the package to disk as $package_name, use FTP if necessary
		create_chmod_control(array(BOARDDIR . '/packages/' . $package_name), array('destination_url' => $scripturl . '?action=admin;area=packageservers;sa=download' . (isset($_GET['server']) ? ';server=' . $_GET['server'] : '') . (isset($_REQUEST['auto']) ? ';auto' : '') . ';package=' . $_REQUEST['package'] . (isset($_REQUEST['conflict']) ? ';conflict' : '') . ';' . $context['session_var'] . '=' . $context['session_id'], 'crash_on_error' => true));
		package_put_contents(BOARDDIR . '/packages/' . $package_name, fetch_web_data($url . $_REQUEST['package']));

		// Done!  Did we get this package automatically?
		if (preg_match('~^http://[\w_\-]+\.elkarte\.net/~', $_REQUEST['package']) == 1 && strpos($_REQUEST['package'], 'dlattach') === false && isset($_REQUEST['auto']))
			redirectexit('action=admin;area=packages;sa=install;package=' . $package_name);

		// You just downloaded a addon from SERVER_NAME_GOES_HERE.
		$context['package_server'] = $server;

		// Read in the newly saved package information
		$context['package'] = getPackageInfo($package_name);

		if (!is_array($context['package']))
			fatal_lang_error('package_cant_download', false);

		if ($context['package']['type'] == 'modification')
			$context['package']['install']['link'] = '<a href="' . $scripturl . '?action=admin;area=packages;sa=install;package=' . $context['package']['filename'] . '">[ ' . $txt['install_mod'] . ' ]</a>';
		elseif ($context['package']['type'] == 'avatar')
			$context['package']['install']['link'] = '<a href="' . $scripturl . '?action=admin;area=packages;sa=install;package=' . $context['package']['filename'] . '">[ ' . $txt['use_avatars'] . ' ]</a>';
		elseif ($context['package']['type'] == 'language')
			$context['package']['install']['link'] = '<a href="' . $scripturl . '?action=admin;area=packages;sa=install;package=' . $context['package']['filename'] . '">[ ' . $txt['add_languages'] . ' ]</a>';
		else
			$context['package']['install']['link'] = '';

		$context['package']['list_files']['link'] = '<a href="' . $scripturl . '?action=admin;area=packages;sa=list;package=' . $context['package']['filename'] . '">[ ' . $txt['list_files'] . ' ]</a>';

		// Free a little bit of memory...
		unset($context['package']['xml']);

		$context['page_title'] = $txt['download_success'];
	}

	/**
	 * Returns the contact details for a server
	 *
	 * - Reads the database to fetch the server url and name
	 *
	 * @return array
	 */
	private function _package_server()
	{
		// Initialize the required variables.
		$name = '';
		$url = '';
		$server = '';

		if (isset($_GET['server']))
		{
			if ($_GET['server'] == '')
				redirectexit('action=admin;area=packageservers');

			$server = (int) $_GET['server'];

			// Query the server table to find the requested server.
			$packageserver = fetchPackageServers($server);
			$url = $packageserver[0]['url'];
			$name = $packageserver[0]['name'];

			// If server does not exist then dump out.
			if (empty($url))
				fatal_lang_error('couldnt_connect', false);
		}

		return array($name, $url, $server);
	}

	/**
	 * Upload a new package to the packages directory.
	 *
	 * - Accessed by action=admin;area=packageservers;sa=upload2
	 */
	public function action_upload2()
	{
		global $txt, $scripturl, $context;

		// Setup the correct template, even though I'll admit we ain't downloading ;)
		$context['sub_template'] = 'downloaded';

		// @todo Use FTP if the packages directory is not writable.
		// Check the file was even sent!
		if (!isset($_FILES['package']['name']) || $_FILES['package']['name'] == '')
			fatal_lang_error('package_upload_error_nofile');
		elseif (!is_uploaded_file($_FILES['package']['tmp_name']) || (ini_get('open_basedir') == '' && !file_exists($_FILES['package']['tmp_name'])))
			fatal_lang_error('package_upload_error_failed');

		// Make sure it has a sane filename.
		$_FILES['package']['name'] = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $_FILES['package']['name']);

		if (strtolower(substr($_FILES['package']['name'], -4)) != '.zip' && strtolower(substr($_FILES['package']['name'], -4)) != '.tgz' && strtolower(substr($_FILES['package']['name'], -7)) != '.tar.gz')
			fatal_lang_error('package_upload_error_supports', false, array('zip, tgz, tar.gz'));

		// We only need the filename...
		$packageName = basename($_FILES['package']['name']);

		// Setup the destination and throw an error if the file is already there!
		$destination = BOARDDIR . '/packages/' . $packageName;

		// @todo Maybe just roll it like we do for downloads?
		if (file_exists($destination))
			fatal_lang_error('package_upload_error_exists');

		// Now move the file.
		move_uploaded_file($_FILES['package']['tmp_name'], $destination);
		@chmod($destination, 0777);

		// If we got this far that should mean it's available.
		$context['package'] = getPackageInfo($packageName);
		$context['package_server'] = '';

		// Not really a package, you lazy bum!
		if (!is_array($context['package']))
		{
			@unlink($destination);
			loadLanguage('Errors');
			$txt[$context['package']] = str_replace('{MANAGETHEMEURL}', $scripturl . '?action=admin;area=theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id'] . '#theme_install', $txt[$context['package']]);
			fatal_lang_error('package_upload_error_broken', false, $txt[$context['package']]);
		}
		// Is it already uploaded, maybe?
		elseif ($dir = @opendir(BOARDDIR . '/packages'))
		{
			while ($package = readdir($dir))
			{
				// No need to check these
				if ($package == '.' || $package == '..' || $package == 'temp' || $package == $packageName || (!(is_dir(BOARDDIR . '/packages/' . $package) && file_exists(BOARDDIR . '/packages/' . $package . '/package-info.xml')) && substr(strtolower($package), -7) != '.tar.gz' && substr(strtolower($package), -4) != '.tgz' && substr(strtolower($package), -4) != '.zip'))
					continue;

				// Read package info for the archive we found
				$packageInfo = getPackageInfo($package);
				if (!is_array($packageInfo))
					continue;

				// If it was already uploaded under another name don't upload it again.
				if ($packageInfo['id'] == $context['package']['id'] && $packageInfo['version'] == $context['package']['version'])
				{
					@unlink($destination);
					loadLanguage('Errors');
					fatal_lang_error('package_upload_already_exists', 'general', $package);
				}
			}
			closedir($dir);
		}

		if ($context['package']['type'] == 'modification')
			$context['package']['install']['link'] = '<a href="' . $scripturl . '?action=admin;area=packages;sa=install;package=' . $context['package']['filename'] . '">[ ' . $txt['install_mod'] . ' ]</a>';
		elseif ($context['package']['type'] == 'avatar')
			$context['package']['install']['link'] = '<a href="' . $scripturl . '?action=admin;area=packages;sa=install;package=' . $context['package']['filename'] . '">[ ' . $txt['use_avatars'] . ' ]</a>';
		elseif ($context['package']['type'] == 'language')
			$context['package']['install']['link'] = '<a href="' . $scripturl . '?action=admin;area=packages;sa=install;package=' . $context['package']['filename'] . '">[ ' . $txt['add_languages'] . ' ]</a>';
		else
			$context['package']['install']['link'] = '';

		$context['package']['list_files']['link'] = '<a href="' . $scripturl . '?action=admin;area=packages;sa=list;package=' . $context['package']['filename'] . '">[ ' . $txt['list_files'] . ' ]</a>';

		unset($context['package']['xml']);

		$context['page_title'] = $txt['package_uploaded_success'];
	}

	/**
	 * Add a package server to the list.
	 *
	 * - Accessed by action=admin;area=packageservers;sa=add
	 */
	public function action_add()
	{
		// Load our subs file.
		require_once(SUBSDIR . '/PackageServers.subs.php');

		// Validate the user.
		checkSession();

		// If they put a slash on the end, get rid of it.
		if (substr($_POST['serverurl'], -1) == '/')
			$_POST['serverurl'] = substr($_POST['serverurl'], 0, -1);

		// Are they both nice and clean?
		$servername = trim(Util::htmlspecialchars($_POST['servername']));
		$serverurl = trim(Util::htmlspecialchars($_POST['serverurl']));

		// Make sure the URL has the correct prefix.
		if (substr($serverurl, 0, 7) !== 'http://' && substr($serverurl, 0, 8) !== 'https://')
			$serverurl = 'http://' . $serverurl;

		// Add it to the list of package servers.
		addPackageServer($servername, $serverurl);

		redirectexit('action=admin;area=packageservers');
	}

	/**
	 * Remove a server from the list.
	 *
	 * - Accessed by action=admin;area=packageservers;sa=remove
	 */
	public function action_remove()
	{
		checkSession('get');

		require_once(SUBSDIR . '/PackageServers.subs.php');

		// We no longer browse this server.
		$_GET['server'] = (int) $_GET['server'];
		deletePackageServer($_GET['server']);

		redirectexit('action=admin;area=packageservers');
	}

	/**
	 * Display the upload package form.
	 */
	public function action_upload()
	{
		global $txt, $context;

		// Set up the upload template, and page title.
		$context['sub_template'] = 'upload';
		$context['page_title'] .= ' - ' . $txt['upload_packages'];

		// Check if we will be able to write new archives in /packages folder.
		$context['package_download_broken'] = !is_writable(BOARDDIR . '/packages') || !is_writable(BOARDDIR . '/packages/installed.list');

		// Let's initialize ftp context
		$context['package_ftp'] = array(
			'server' => '',
			'port' => '',
			'username' => '',
			'path' => '',
			'error' => '',
		);

		// Give FTP a chance...
		if ($context['package_download_broken'])
			$this->ftp_connect();
	}

	/**
	 * This method attempts to chmod packages and installed.list
	 *
	 * - uses FTP if necessary.
	 * - It sets the $context['package_download_broken'] status for the template.
	 * - Used by package servers pages.
	 */
	public function ftp_connect()
	{
		global $context, $modSettings;

		// Try to chmod from PHP first
		@chmod(BOARDDIR . '/packages', 0777);
		@chmod(BOARDDIR . '/packages/installed.list', 0777);

		$unwritable = !is_writable(BOARDDIR . '/packages') || !is_writable(BOARDDIR . '/packages/installed.list');

		// Let's initialize $context
		$context['package_ftp'] = array(
			'server' => '',
			'port' => '',
			'username' => '',
			'path' => '',
			'error' => '',
		);

		if ($unwritable)
		{
			// Are they connecting to their FTP account already?
			if (isset($_POST['ftp_username']))
			{
				require_once(SUBSDIR . '/FtpConnection.class.php');
				$ftp = new Ftp_Connection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

				if ($ftp->error === false)
				{
					// I know, I know... but a lot of people want to type /home/xyz/... which is wrong, but logical.
					if (!$ftp->chdir($_POST['ftp_path']))
					{
						$ftp_error = $ftp->error;
						$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
					}
				}
			}

			// No attempt yet, or we had an error last time
			if (!isset($ftp) || $ftp->error !== false)
			{
				// Maybe we didn't even try yet
				if (!isset($ftp))
				{
					require_once(SUBSDIR . '/FtpConnection.class.php');
					$ftp = new Ftp_Connection(null);
				}
				// ...or we failed
				elseif ($ftp->error !== false && !isset($ftp_error))
					$ftp_error = $ftp->last_message === null ? '' : $ftp->last_message;

				list ($username, $detect_path, $found_path) = $ftp->detect_path(BOARDDIR);

				if ($found_path || !isset($_POST['ftp_path']))
					$_POST['ftp_path'] = $detect_path;

				if (!isset($_POST['ftp_username']))
					$_POST['ftp_username'] = $username;

				// Fill the boxes for a FTP connection with data from the previous attempt too, if any
				$context['package_ftp'] = array(
					'server' => isset($_POST['ftp_server']) ? $_POST['ftp_server'] : (isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost'),
					'port' => isset($_POST['ftp_port']) ? $_POST['ftp_port'] : (isset($modSettings['package_port']) ? $modSettings['package_port'] : '21'),
					'username' => isset($_POST['ftp_username']) ? $_POST['ftp_username'] : (isset($modSettings['package_username']) ? $modSettings['package_username'] : ''),
					'path' => $_POST['ftp_path'],
					'error' => empty($ftp_error) ? null : $ftp_error,
				);

				// Announce the template it's time to display the ftp connection box.
				$context['package_download_broken'] = true;
			}
			else
			{
				// FTP connection has succeeded
				$context['package_download_broken'] = false;

				// Try to chmod packages folder and our list file.
				$ftp->chmod('packages', 0777);
				$ftp->chmod('packages/installed.list', 0777);

				$ftp->close();
			}
		}
	}
}