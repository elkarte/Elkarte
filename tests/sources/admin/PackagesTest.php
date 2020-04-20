<?php

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
	public function setUp()
	{
		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		if (!defined('FORUM_VERSION'))
		{
			define('FORUM_VERSION', 'ElkArte 2.0');
		}

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Packages', 'english', false, true);
	}

	/**
	 * Show the package list, hint there are none
	 */
	public function testActionIndexPK()
	{
		global $context;

		// Get the controller, call index
		$controller = new \ElkArte\AdminController\Packages(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// We should be ready to show the pm inbox, its empty right now
		$this->assertEquals($context['sub_template'], 'browse');
		$this->assertEquals($context['default_list'], 'packages_lists');
	}

	public function testActionDownload()
	{
		global $context;

		$req = \ElkArte\HttpReq::instance();
		$req->query->sa='download';
		$req->post->package='https://github.com/Spuds/Elk_Resize_Attachment_Images/releases/download/V1.0.5/elk_ResizeAttachedImages.zip';

		// Get the controller, call index
		$controller = new \ElkArte\AdminController\PackageServers(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_index();
		$req->query->sa = null;
		$req->query->area = null;

		$this->assertEquals($context['page_title'], 'Package downloaded successfully');
		$this->assertEquals($context['package']['name'], 'Attachment Image Resize');
	}

	public function testActionInstall()
	{
		global $context;

		$req = \ElkArte\HttpReq::instance();
		$req->query->ve = '1.1';
		$req->query->package = 'elk_ResizeAttachedImages.zip';
		$req->query->sa = 'install';
		$req->query->area = 'packages';

		// Stupid package subs still uses the super global directly
		$_REQUEST['ve'] = '1.1';
		$_REQUEST['package'] = 'elk_ResizeAttachedImages.zip';

		// Get the controller, call index
		$controller = new \ElkArte\AdminController\Packages(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_index();
		$req->query->sa = null;
		$req->query->area = null;

		$this->assertEquals($context['is_installed'], false);
		$this->assertEquals($context['actions'][7]['description'], '<strong>Test successful</strong>', $context['actions'][7]['description']);
	}
}
