<?php

use ElkArte\AdminController\Packages;
use ElkArte\AdminController\PackageServers;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\Themes\ThemeLoader;
use ElkArte\User;

/**
 * TestCase class for the Packages & PackageServer Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestPackagesController extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $context;

		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		if (!defined('FORUM_VERSION'))
		{
			define('FORUM_VERSION', 'ElkArte 2.0');
		}

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english');
		$lang->load('Packages');

		$context['admin_menu_id'] = 1;
		$context['admin_menu_name'] = 'menu_data_' . $context['admin_menu_id'];
	}

	/**
	 * Show the package list, hint there are none
	 */
	public function testActionIndexPK()
	{
		global $context;

		// Get the controller, call index
		$controller = new Packages(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// We should be ready to show the package listing, its empty right now
		$this->assertEquals('browse', $context['sub_template']);
		$this->assertEquals('packages_lists', $context['default_list']);
	}

	public function testActionDownload()
	{
		global $context;

		$req = HttpReq::instance();
		$req->query->sa='download';
		$req->post->package='https://github.com/Spuds/Elk_Resize_Attachment_Images/releases/download/V1.0.5/elk_ResizeAttachedImages.zip';

		// Get the controller, call index
		$controller = new PackageServers(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();
		$req->query->sa = null;
		$req->query->area = null;

		$this->assertEquals('Package downloaded successfully', $context['page_title']);
		$this->assertEquals('Attachment Image Resize', $context['package']['name']);
	}

	public function testActionInstall()
	{
		global $context;

		$req = HttpReq::instance();
		$req->query->ve = '1.1';
		$req->query->package = 'elk_ResizeAttachedImages.zip';
		$req->query->sa = 'install';
		$req->query->area = 'packages';

		// Stupid package subs still uses the super global directly
		$_REQUEST['ve'] = '1.1';
		$_REQUEST['package'] = 'elk_ResizeAttachedImages.zip';

		// Get the controller, call index
		$controller = new Packages(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();
		$req->query->sa = null;
		$req->query->area = null;

		$this->assertEquals(false, $context['is_installed']);
		$this->assertEquals('<strong>Test successful</strong>', $context['actions'][7]['description'], $context['actions'][7]['description']);
	}
}
