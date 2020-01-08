<?php

/**
 * TestCase class for the admin search
 */
class TestAdminSearch extends \PHPUnit\Framework\TestCase
{
	/**
	 * @var ActionController
	 */
	private $controller;
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Cleans up the environment after running a test.
	 */
	protected function tearDown()
	{
		\ElkArte\User::$info->permissions = array();
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

		$this->assertContains($scripturl, $url);
		$this->assertContains($context['search_term'], $name);
	}

	public function settingsProvider()
	{
		global $context;

		/*
		 * Forcefully reload language files to combat PHPUnit
		 * messing up globals between tests.
		 */
		theme()->getTemplates()->loadLanguageFile('Admin', 'english', true, true);

		// Set up the controller.
		$_GET['action'] = 'admin';
		\ElkArte\User::$info->permissions = array_merge(\ElkArte\User::$info->permissions, ['admin_forum']);
		$dispatcher = new \ElkArte\SiteDispatcher(new \ElkArte\HttpReq);
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
