<?php

use ElkArte\HttpReq;
use ElkArte\SiteDispatcher;
use ElkArte\Themes\ThemeLoader;
use ElkArte\User;
use ElkArte\Languages\Loader;
use PHPUnit\Framework\TestCase;

/**
 * TestCase class for the admin search
 */
class TestAdminSearch extends TestCase
{
	/**
	 * @var ActionController
	 */
	private $controller;
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Cleans up the environment after running a test.
	 */
	protected function tearDown(): void
	{
		User::$info->permissions = array();
		global $context, $user_info;

		$user_info['permissions'] = array();
		unset($context['search_term'], $context['search_results']);
	}

	/**
	 * Hacky solution to generate coverage for the internal search methods
	 * since calling the function within the data provider doesn't seem
	 * to indicate any extra coverage generation.
	 */
	public function testBeforeSearchSettings()
	{
		global $context;

		$this->settingsProvider();
		$this->assertNotEmpty($context['search_results']);
	}

	/**
	 * @dataProvider settingsProvider
	 */
	public function testSearchSettings($url, $name)
	{
		global $context, $scripturl;

		$this->assertStringContainsString($scripturl, $url);
		$this->assertStringContainsString($context['search_term'], $name);
	}

	public function settingsProvider()
	{
		global $context, $txt;

		/*
		 * Forcefully reload language files to combat PHPUnit
		 * messing up globals between tests.
		 */
		$lang = new Loader('english', $txt);
		$lang->load('admin');

		// Set up the controller.
		$req = HttpReq::instance();
		$_GET['action'] = 'admin';
		$req->query->action = 'admin';
		User::$info->permissions = array_merge(User::$info->permissions, ['admin_forum']);
		$dispatcher = new SiteDispatcher($req);

		$this->controller = $dispatcher->getController();

		// Won't hurt to call this again...
		$method = new ReflectionMethod($this->controller, 'loadMenu');
		$method->setAccessible(true);
		$method->invoke($this->controller);

		$context['search_term'] = 'enable';
		$this->controller->action_search_internal();
		destroyMenu('last');

		return array_map(function ($search_result)
			{
				return array($search_result['url'], strtolower($search_result['name']));
			}, $context['search_results']
		);
	}
}
