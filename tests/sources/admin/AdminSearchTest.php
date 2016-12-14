<?php

/**
 * TestCase class for the admin seaech
 */
class TestAdminSearch extends PHPUnit_Framework_TestCase
{
	/**
	 * @var Action_Controller
	 */
	private $controller;

	/**
	 * Cleans up the environment after running a test.
	 */
	public function __destruct()
	{
		global $user_info;

		$user_info['permissions'] = array();
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
		global $user_info;

		loadLanguage('Admin', 'english', true, true);
		$user_info['permissions'][] = 'admin_forum';
		$this->controller = new Admin_Controller(new Event_Manager());

		// Won't hurt to call this again...
		$method = new ReflectionMethod($this->controller, 'loadMenu');
		$method->setAccessible(true);
		$method->invoke($this->controller);

		$context['search_term'] = 'enable';
		$this->controller->action_search_internal();

		return array_map(function ($search_result)
			{
				return array($search_result['url'], strtolower($search_result['name']));
			}, $context['search_results']
		);
	}
}
