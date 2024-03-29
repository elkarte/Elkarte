<?php

/**
 * This file handles the package servers and packages download, in Package Servers
 * area of administration panel.
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

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\Util;
use ElkArte\Http\FtpConnection;
use ElkArte\Languages\Txt;
use FilesystemIterator;
use IteratorIterator;
use UnexpectedValueException;

/**
 * PackageServers controller handles browsing, adding and removing
 * package servers, and download of a package from them.
 *
 * @package Packages
 */
class PackageServers extends AbstractController
{
	/** @var FileFunctions */
	protected $fileFunc;

	/**
	 * Called before all other methods when coming from the dispatcher or
	 * action class.  Loads language and templates files such that they are available
	 * to the other methods.
	 */
	public function pre_dispatch()
	{
		// Use the Packages language file. (split servers?)
		Txt::load('Packages');

		// Use the PackageServers template.
		theme()->getTemplates()->load('PackageServers');
		loadCSSFile('admin.css');

		// Load our subs.
		require_once(SUBSDIR . '/Package.subs.php');

		// Going to come in handy!
		$this->fileFunc = FileFunctions::instance();
	}

	/**
	 * Main dispatcher for package servers. Checks permissions,
	 * load files, and forwards to the right method.
	 *
	 * - Accessed by action=admin;area=packageservers
	 *
	 * @event integrate_sa_package_servers
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		global $txt, $context;

		// This is for admins only.
		isAllowedTo('admin_forum');

		$context['page_title'] = $txt['package_servers'];

		// Here is a list of all the potentially valid actions.
		$subActions = [
			'servers' => [$this, 'action_list'],
			'browse' => [$this, 'action_browse'],
			'download' => [$this, 'action_download'],
			'upload2' => [$this, 'action_upload2'],
		];

		// Set up action/subaction stuff.
		$action = new Action('package_servers');

		// Now let's decide where we are taking this... call integrate_sa_package_servers
		$subAction = $action->initialize($subActions, 'servers');

		// For the template
		$context['sub_action'] = $subAction;

		// Set up some tabs, used when the add packages button (servers) is selected to mimic that controller
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => $txt['package_manager'],
			'description' => $txt['package_servers_desc'],
			'class' => 'i-package',
			'tabs' => [
				'browse' => [
					'url' => getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'browse']),
					'label' => $txt['browse_packages'],
				],
				'servers' => [
					'url' => getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'servers', 'desc']),
					'description' => $txt['upload_packages_desc'],
					'label' => $txt['add_packages'],
				],
				'options' => [
					'url' => getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => 'options', 'desc']),
					'label' => $txt['package_settings'],
				],
			],
		]);

		// Lets just do it!
		$action->dispatch($subAction);
	}

	/**
	 * Load the package servers into context.
	 *
	 * - Accessed by action=admin;area=packageservers;sa=servers
	 */
	public function action_list()
	{
		global $txt, $context;

		// Ensure we use the correct template, and page title.
		$context['sub_template'] = 'servers';
		$context['page_title'] .= ' - ' . $txt['download_packages'];

		// Load the addon server.
		[$context['server']['name'], $context['server']['id']] = $this->_package_server();

		// Check if we will be able to write new archives in /packages folder.
		$context['package_download_broken'] = !$this->fileFunc->isWritable(BOARDDIR . '/packages') || !$this->fileFunc->isWritable(BOARDDIR . '/packages/installed.list');
		if ($context['package_download_broken'])
		{
			$this->ftp_connect();
		}
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
		global $context, $modSettings, $txt;

		// Try to chmod from PHP first
		$this->fileFunc->chmod(BOARDDIR . '/packages');
		$this->fileFunc->chmod(BOARDDIR . '/packages/installed.list');

		$unwritable = !$this->fileFunc->isWritable(BOARDDIR . '/packages') || !$this->fileFunc->isWritable(BOARDDIR . '/packages/installed.list');
		if (!$unwritable)
		{
			// Using PHP was successful, no need for FTP
			$context['package_download_broken'] = false;
			return;
		}

		// Let's initialize $context
		$context['package_ftp'] = [
			'server' => '',
			'port' => '',
			'username' => '',
			'path' => '',
			'error' => '',
		];

		// Are they connected to their FTP account already?
		if (isset($this->_req->post->ftp_username))
		{
			$ftp_server = $this->_req->getPost('ftp_server', 'trim');
			$ftp_port = $this->_req->getPost('ftp_port', 'intval', 21);
			$ftp_username = $this->_req->getPost('ftp_username', 'trim', '');
			$ftp_password = $this->_req->getPost('ftp_password', 'trim', '');
			$ftp_path = $this->_req->getPost('ftp_path', 'trim');

			$ftp = new FtpConnection($ftp_server, $ftp_port, $ftp_username, $ftp_password);

			// I know, I know... but a lot of people want to type /home/xyz/... which is wrong, but logical.
			if (($ftp->error === false) && !$ftp->chdir($ftp_path))
			{
				$ftp_error = $ftp->error;
				$ftp->chdir(preg_replace('~^/home[2]?/[^/]+~', '', $ftp_path));
			}
		}

		// No attempt yet, or we had an error last time
		if (!isset($ftp) || $ftp->error !== false)
		{
			// Maybe we didn't even try yet
			if (!isset($ftp))
			{
				$ftp = new FtpConnection(null);
			}
			// ...or we failed
			elseif ($ftp->error !== false && !isset($ftp_error))
			{
				$response = $txt['package_ftp_' . $ftp->error] ?? $ftp->error;
				$ftp_error = empty($ftp->last_message) ? $response : $ftp->last_message;
			}

			// Grab a few, often wrong, items to fill in the form.
			[$username, $detect_path, $found_path] = $ftp->detect_path(BOARDDIR);

			if ($found_path || !isset($ftp_path))
			{
				$ftp_path = $detect_path;
			}

			if (empty($ftp_username))
			{
				$ftp_username = $modSettings['package_username'] ?? $username;
			}

			// Fill the boxes for a FTP connection with data from the previous attempt too, if any
			$context['package_ftp'] = [
				'server' => $ftp_server ?? ($modSettings['package_server'] ?? 'localhost'),
				'port' => $ftp_port ?? ($modSettings['package_port'] ?? '21'),
				'username' => $ftp_username ?? ($modSettings['package_username'] ?? ''),
				'path' => $ftp_path,
				'error' => empty($ftp_error) ? null : $ftp_error,
			];

			// Announce the template it's time to display the ftp connection box.
			$context['package_download_broken'] = true;
		}
		else
		{
			// FTP connection has succeeded
			$context['package_download_broken'] = false;
			$context['package_ftp']['connection'] = $txt['package_ftp_test_success'];

			// Try to chmod packages folder and our list file.
			$ftp->ftp_chmod('packages', [0755, 0775, 0777]);
			$ftp->ftp_chmod('packages/installed.list', [0664, 0666]);
			$ftp->close();
		}
	}

	/**
	 * Browse a server's list of packages.
	 *
	 * - Accessed by action=admin;area=packageservers;sa=browse
	 */
	public function action_browse()
	{
		global $txt, $context;

		// Want to browsing the packages from the addon server
		if (isset($this->_req->query->server))
		{
			[$name, $url] = $this->_package_server();
		}

		// Minimum required parameter did not exist so dump out.
		else
		{
			throw new Exception('couldnt_connect', false);
		}

		// Might take some time.
		detectServer()->setTimeLimit(60);

		// Fetch the package listing from the server and json decode
		$packageListing = json_decode(fetch_web_data($url));

		// List out the packages...
		$context['package_list'] = [];

		// Pick the correct template.
		$context['sub_template'] = 'package_list';
		$context['page_title'] = $txt['package_servers'] . ($name !== '' ? ' - ' . $name : '');
		$context['package_server'] = $name;

		// If we received data
		$this->ifWeReceivedData($packageListing, $name, $txt['mod_section_count']);

		// Good time to sort the categories, the packages inside each category will be by last modification date.
		asort($context['package_list']);
	}

	/**
	 * Returns the contact details for the ElkArte package server
	 *
	 * - This is no longer necessary, but a leftover from when you could add insecure servers
	 * now it just returns what is saved in modSettings 'elkarte_addon_server'
	 *
	 * @return array
	 */
	private function _package_server()
	{
		$modSettings['elkarte_addon_server'] = $modSettings['elkarte_addon_server'] ?? 'https://elkarte.github.io/addons/package.json';

		// Initialize the required variables.
		$name = 'ElkArte';
		$url = $modSettings['elkarte_addon_server'];

		return [$name, $url];
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
		return [
			'id' => empty($thisPackage->pkid) ? $this->_assume_id($thisPackage) : [$thisPackage->pkid],
			'type' => $packageSection,
			'name' => Util::htmlspecialchars($thisPackage->title),
			'date' => htmlTime(strtotime($thisPackage->date)),
			'author' => Util::htmlspecialchars($thisPackage->author),
			'description' => empty($thisPackage->short) ? '' : Util::htmlspecialchars($thisPackage->short),
			'version' => $thisPackage->version,
			'elkversion' => $thisPackage->elkversion,
			'license' => $thisPackage->license,
			'hooks' => $thisPackage->allhooks,
			'server' => [
				'download' => (strpos($thisPackage->server[0]->download, 'http://') === 0 || strpos($thisPackage->server[0]->download, 'https://') === 0) && filter_var($thisPackage->server[0]->download, FILTER_VALIDATE_URL)
					? $thisPackage->server[0]->download : '',
				'support' => (strpos($thisPackage->server[0]->support, 'http://') === 0 || strpos($thisPackage->server[0]->support, 'https://') === 0) && filter_var($thisPackage->server[0]->support, FILTER_VALIDATE_URL)
					? $thisPackage->server[0]->support : '',
				'bugs' => (strpos($thisPackage->server[0]->bugs, 'http://') === 0 || strpos($thisPackage->server[0]->bugs, 'https://') === 0) && filter_var($thisPackage->server[0]->bugs, FILTER_VALIDATE_URL)
					? $thisPackage->server[0]->bugs : '',
				'link' => (strpos($thisPackage->server[0]->url, 'http://') === 0 || strpos($thisPackage->server[0]->url, 'https://') === 0) && filter_var($thisPackage->server[0]->url, FILTER_VALIDATE_URL)
					? $thisPackage->server[0]->url : '',
			],
		];
	}

	/**
	 * If no ID is provided for a package, create the most
	 * common ones based on author:package patterns
	 *
	 * - Should not be relied on
	 *
	 * @param object $thisPackage
	 *
	 * @return string[]
	 */
	private function _assume_id($thisPackage)
	{
		$under = str_replace(' ', '_', $thisPackage->title);
		$none = str_replace(' ', '', $thisPackage->title);

		return [
			$thisPackage->author . ':' . $under,
			$thisPackage->author . ':' . $none,
			strtolower($thisPackage->author) . ':' . $under,
			strtolower($thisPackage->author) . ':' . $none,
			ucfirst($thisPackage->author) . ':' . $under,
			ucfirst($thisPackage->author) . ':' . $none,
			strtolower($thisPackage->author . ':' . $under),
			strtolower($thisPackage->author . ':' . $none),
		];
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
		if (preg_match('~^http(s)?://(www.)?(bitbucket\.org|github\.com)/(.+?(master(\.zip|\.tar\.gz)))$~', $name, $matches) === 1)
		{
			// Name this master.zip based on repo name in the link
			$path_parts = pathinfo($matches[4]);
			[, $newname,] = explode('/', $path_parts['dirname']);

			// Just to be safe, no invalid file characters
			$invalid = array_merge(array_map('chr', range(0, 31)), ['<', '>', ':', '"', '/', '\\', '|', '?', '*']);

			// We could read the package info and see if we have a duplicate id & version, however that is
			// not always accurate, especially when dealing with repos.  So for now just put in no conflict mode
			// and do the save.
			if ($this->_req->getQuery('area') === 'packageservers' && $this->_req->getQuery('sa') === 'download')
			{
				$this->_req->query->auto = true;
			}

			return str_replace($invalid, '_', $newname) . $matches[6];
		}

		return basename($name);
	}

	/**
	 * Case-insensitive natural sort for packages
	 *
	 * @param array $a
	 * @param array $b
	 *
	 * @return int
	 */
	public function package_sort($a, $b)
	{
		return strcasecmp($a['name'], $b['name']);
	}

	/**
	 * Download a package.
	 *
	 * What it does:
	 *
	 * - Accessed by action=admin;area=packageservers;sa=download
	 * - If server is set, loads json file from package server
	 *     - requires both section and num values to validate the file to download from the json file
	 * - If $_POST['byurl'] $_POST['filename'])) are set, will download a file from the url and save it as filename
	 * - If just $_POST['byurl'] is set will fetch that file and save it
	 *     - github and bitbucket master files are renamed to repo name to avoid collisions
	 * - Files are saved to the package directory and validate to be ElkArte packages
	 */
	public function action_download()
	{
		global $txt, $context;

		// Use the downloaded sub template.
		$context['sub_template'] = 'downloaded';

		// Security is good...
		checkSession(isset($this->_req->query->server) ? 'get' : '');

		// To download something, we need either a valid server or url.
		if (empty($this->_req->query->server)
			&& (!empty($this->_req->query->get) && !empty($this->_req->post->package)))
		{
			throw new Exception('package_get_error_is_zero', false);
		}

		// Start off with nothing
		$url = '';
		$name = '';

		// Download from a package server?
		if (isset($this->_req->query->server))
		{
			[$name, $url] = $this->_package_server();

			// Fetch the package listing from the package server
			$listing = json_decode(fetch_web_data($url));

			// Find the requested package by section and number, make sure it matches
			$section = $this->_req->query->section;
			$section = $listing->{$section};

			// This is what they requested, yes?
			if (basename($section[$this->_req->query->num]->server[0]->download) === $this->_req->query->package)
			{
				// Where to download it from
				$package_id = $this->_req->query->package;
				$package_name = $this->_rename_master($section[$this->_req->query->num]->server[0]->download);
				$path_url = pathinfo($section[$this->_req->query->num]->server[0]->download);
				$url = isset($path_url['dirname']) ? $path_url['dirname'] . '/' : '';

				// No extension ... set a default or nothing will show up in the listing
				if (strrpos(substr($name, 0, -3), '.') === false)
				{
					$needs_extension = true;
				}
			}
			// Not found or some monkey business
			else
			{
				throw new Exception('package_cant_download', false);
			}
		}
		// Entered a url and optional filename
		elseif (isset($this->_req->post->byurl) && !empty($this->_req->post->filename))
		{
			$package_id = $this->_req->post->package;
			$package_name = basename($this->_req->post->filename);
		}
		// Must just be a link then
		else
		{
			$package_id = $this->_req->post->package;
			$package_name = $this->_rename_master($this->_req->post->package);
		}

		// First make sure it's a package.
		$packageInfo = getPackageInfo($url . $package_id);
		if (!is_array($packageInfo))
		{
			throw new Exception($packageInfo);
		}

		if (!empty($needs_extension) && isset($packageInfo['name']))
		{
			$package_name = $this->_rename_master($packageInfo['name']) . '.zip';
		}

		// Avoid over writing any existing package files of the same name
		if (isset($this->_req->query->conflict) || (isset($this->_req->query->auto) && $this->fileFunc->fileExists(BOARDDIR . '/packages/' . $package_name)))
		{
			// Find the extension, change abc.tar.gz to abc_1.tar.gz...
			$ext = '';
			if (strrpos(substr($package_name, 0, -3), '.') !== false)
			{
				$ext = substr($package_name, strrpos(substr($package_name, 0, -3), '.'));
				$package_name = substr($package_name, 0, strrpos(substr($package_name, 0, -3), '.')) . '_';
			}

			// Find the first available free name
			$i = 1;
			while ($this->fileFunc->fileExists(BOARDDIR . '/packages/' . $package_name . $i . $ext))
			{
				$i++;
			}

			$package_name .= $i . $ext;
		}

		// Save the package to disk, use FTP if necessary
		$create_chmod_control = new PackageChmod();
		$create_chmod_control->createChmodControl(
			[BOARDDIR . '/packages/' . $package_name],
			[
				'destination_url' => getUrl('admin', ['action' => 'admin', 'area' => 'packageservers', 'sa' => 'download', 'package' => $package_id, '{session_data}']
					+ (isset($this->_req->query->server) ? ['server' => $this->_req->query->server] : [])
					+ (isset($this->_req->query->auto) ? ['auto' => ''] : [])
					+ (isset($this->_req->query->conflict) ? ['conflict' => ''] : [])),
				'crash_on_error' => true
			]
		);

		package_put_contents(BOARDDIR . '/packages/' . $package_name, fetch_web_data($url . $package_id));

		// You just downloaded an addon from SERVER_NAME_GOES_HERE.
		$context['package_server'] = $name;

		// Read in the newly saved package information
		$context['package'] = getPackageInfo($package_name);

		if (!is_array($context['package']))
		{
			throw new Exception('package_cant_download', false);
		}

		$context['package']['install']['link'] = '';
		if ($context['package']['type'] === 'modification' || $context['package']['type'] === 'addon')
		{
			$context['package']['install']['link'] = $this->getInstallLink('install_mod', $context['package']['filename']);
		}
		elseif ($context['package']['type'] === 'avatar')
		{
			$context['package']['install']['link'] = $this->getInstallLink('use_avatars', $context['package']['filename']);
		}
		elseif ($context['package']['type'] === 'language')
		{
			$context['package']['install']['link'] = $this->getInstallLink('add_languages', $context['package']['filename']);
		}

		$context['package']['list_files']['link'] = $this->getInstallLink('list_files', $context['package']['filename'], 'list');

		// Free a little bit of memory...
		unset($context['package']['xml']);

		$context['page_title'] = $txt['download_success'];
	}

	/**
	 * Upload a new package to the package directory.
	 *
	 * - Accessed by action=admin;area=packageservers;sa=upload2
	 */
	public function action_upload2()
	{
		global $txt, $context;

		// Setup the correct template, even though I'll admit we ain't downloading ;)
		$context['sub_template'] = 'downloaded';

		// @todo Use FTP if the packages directory is not writable.
		// Check the file was even sent!
		if (!isset($_FILES['package']['name']) || $_FILES['package']['name'] === '')
		{
			throw new Exception('package_upload_error_nofile');
		}

		if (!is_uploaded_file($_FILES['package']['tmp_name']) || (ini_get('open_basedir') === '' && !$this->fileFunc->fileExists($_FILES['package']['tmp_name'])))
		{
			throw new Exception('package_upload_error_failed');
		}

		// Make sure it has a sane filename.
		$_FILES['package']['name'] = preg_replace(['/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'], ['_', '.', ''], $_FILES['package']['name']);

		if (strtolower(substr($_FILES['package']['name'], -4)) !== '.zip' && strtolower(substr($_FILES['package']['name'], -4)) !== '.tgz' && strtolower(substr($_FILES['package']['name'], -7)) !== '.tar.gz')
		{
			throw new Exception('package_upload_error_supports', false, ['zip, tgz, tar.gz']);
		}

		// We only need the filename...
		$packageName = basename($_FILES['package']['name']);

		// Setup the destination and throw an error if the file is already there!
		$destination = BOARDDIR . '/packages/' . $packageName;

		// @todo Maybe just roll it like we do for downloads?
		if ($this->fileFunc->fileExists($destination))
		{
			throw new Exception('package_upload_error_exists');
		}

		// Now move the file.
		move_uploaded_file($_FILES['package']['tmp_name'], $destination);
		$this->fileFunc->chmod($destination);

		// If we got this far that should mean it's available.
		$context['package'] = getPackageInfo($packageName);
		$context['package_server'] = '';

		// Not really a package, you lazy bum!
		if (!is_array($context['package']))
		{
			$this->fileFunc->delete($destination);
			Txt::load('Errors');
			$txt[$context['package']] = str_replace('{MANAGETHEMEURL}', getUrl('admin', ['action' => 'admin', 'area' => 'theme', 'sa' => 'admin', '{session_data}', 'hash' => '#theme_install']), $txt[$context['package']]);
			throw new Exception('package_upload_error_broken', false, $txt[$context['package']]);
		}
		try
		{
			$dir = new FilesystemIterator(BOARDDIR . '/packages', FilesystemIterator::SKIP_DOTS);

			$filter = new PackagesFilterIterator($dir);
			$packages = new IteratorIterator($filter);

			foreach ($packages as $package)
			{
				// No need to check these
				if ($package->getFilename() === $packageName)
				{
					continue;
				}

				// Read package info for the archive we found
				$packageInfo = getPackageInfo($package->getFilename());
				if (!is_array($packageInfo))
				{
					continue;
				}

				// If it was already uploaded under another name don't upload it again.
				if ($packageInfo['id'] === $context['package']['id'] && compareVersions($packageInfo['version'], $context['package']['version']) == 0)
				{
					$this->fileFunc->delete($destination);
					throw new Exception('Errors.package_upload_already_exists', 'general', $package->getFilename());
				}
			}
		}
		catch (UnexpectedValueException)
		{
			// @todo for now do nothing...
		}

		$context['package']['install']['link'] = '';
		if ($context['package']['type'] === 'modification' || $context['package']['type'] === 'addon')
		{
			$context['package']['install']['link'] = $this->getInstallLink('install_mod', $context['package']['filename']);
		}
		elseif ($context['package']['type'] === 'avatar')
		{
			$context['package']['install']['link'] = $this->getInstallLink('use_avatars', $context['package']['filename']);
		}
		elseif ($context['package']['type'] === 'language')
		{
			$context['package']['install']['link'] = $this->getInstallLink('add_languages', $context['package']['filename']);
		}

		$context['package']['list_files']['link'] = $this->getInstallLink('list_files', $context['package']['filename'], 'list');

		unset($context['package']['xml']);

		$context['page_title'] = $txt['package_uploaded_success'];
	}

	/**
	 * Generates an action link for a package.
	 *
	 * @param string $type The type of the package.
	 * @param string $filename The filename of the package.
	 * @param string $action (optional) The action to perform on the package. Default is 'install'.
	 *
	 * @return string Returns an HTML link for the package action.
	 */
	public function getInstallLink($type, $filename, $action = 'install')
	{
		global $txt;

		return '<a class="linkbutton" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'packages', 'sa' => $action, 'package' => $filename]) . '">' . $txt[$type] . '</a>';
	}

	/**
	 * Process received data to generate a package list
	 *
	 * @param mixed $packageListing The data containing the package list
	 * @param string $name The name of the package server
	 * @param int $mod_section_count The count of sections for the package list
	 *
	 * @return void
	 */
	public function ifWeReceivedData(mixed $packageListing, string $name, $mod_section_count)
	{
		global $context;

		if (!empty($packageListing))
		{
			// Load the installed packages
			$installAdds = loadInstalledPackages();

			// Look through the list of installed mods and get version information for the compare
			$installed_adds = [];
			foreach ($installAdds as $installed_add)
			{
				$installed_adds[$installed_add['package_id']] = $installed_add['version'];
			}

			$the_version = strtr(FORUM_VERSION, ['ElkArte ' => '']);
			if (!empty($_SESSION['version_emulate']))
			{
				$the_version = $_SESSION['version_emulate'];
			}

			// Parse the json file, each section contains a category of addons
			$packageNum = 0;
			foreach ($packageListing as $packageSection => $section_items)
			{
				// Section title / header for the category
				$context['package_list'][$packageSection] = [
					'title' => Util::htmlspecialchars(ucwords($packageSection)),
					'text' => '',
					'items' => [],
				];

				// Load each package array as an item
				$section_count = 0;
				foreach ($section_items as $thisPackage)
				{
					// Read in the package info from the fetched data
					$package = $this->_load_package_json($thisPackage, $packageSection);
					$package['possible_ids'] = $package['id'];

					// Check the installation status
					$package['can_install'] = false;
					$is_installed = array_intersect(array_keys($installed_adds), $package['possible_ids']);
					$package['is_installed'] = !empty($is_installed);

					// Set the ID from our potential list should the ID not be provided in the package .yaml
					$package['id'] = $package['is_installed'] ? array_shift($is_installed) : $package['id'][0];

					// Version installed vs version available
					$package['is_current'] = !empty($package['is_installed']) && compareVersions($installed_adds[$package['id']], $package['version']) == 0;
					$package['is_newer'] = !empty($package['is_installed']) && compareVersions($package['version'], $installed_adds[$package['id']]) > 0;

					// Set the package filename for downloading and pre-existence checking
					$base_name = $this->_rename_master($package['server']['download']);
					$package['filename'] = basename($package['server']['download']);

					// This package is either not installed, or installed but old.
					if (!$package['is_installed'] || (!$package['is_current'] && !$package['is_newer']))
					{
						// Does it claim to install on this version of ElkArte?
						$path_parts = pathinfo($base_name);
						if (!empty($thisPackage->elkversion) && isset($path_parts['extension']) && in_array($path_parts['extension'], ['zip', 'tar', 'gz', 'tar.gz']))
						{
							// No install range given, then set one, it will all work out in the end.
							$for = strpos($thisPackage->elkversion, '-') === false ? $thisPackage->elkversion . '-' . $the_version : $thisPackage->elkversion;
							$package['can_install'] = matchPackageVersion($the_version, $for);
						}
					}

					// See if this filename already exists on the server
					$already_exists = getPackageInfo($base_name);
					$package['download_conflict'] = is_array($already_exists) && in_array($already_exists['id'], $package['possible_ids']) && compareVersions($already_exists['version'], $package['version']) != 0;
					$package['count'] = ++$packageNum;

					// Maybe they have downloaded it but not installed it
					$package['is_downloaded'] = !$package['is_installed'] && (is_array($already_exists) && in_array($already_exists['id'], $package['possible_ids']));
					if ($package['is_downloaded'])
					{
						// Is the available package newer than whats been downloaded?
						$package['is_newer'] = compareVersions($package['version'], $already_exists['version']) > 0;
					}

					// Build the download to server link
					$package['download']['href'] = getUrl('admin', ['action' => 'admin', 'area' => 'packageservers', 'sa' => 'download', 'server' => $name, 'section' => $packageSection, 'num' => $section_count, 'package' => $package['filename']] + ($package['download_conflict'] ? ['conflict'] : []) + ['{session_data}']);
					$package['download']['link'] = '<a href="' . $package['download']['href'] . '">' . $package['name'] . '</a>';

					// Add this package to the list
					$context['package_list'][$packageSection]['items'][$packageNum] = $package;
					$section_count++;
				}

				// Sort them naturally
				usort($context['package_list'][$packageSection]['items'], fn($a, $b) => $this->package_sort($a, $b));

				$context['package_list'][$packageSection]['text'] = sprintf($mod_section_count, $section_count);
			}
		}
	}
}
