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
	public function tearDown()
	{
		global $txt, $user_info;

		$txt = array();
		$this->controller = null;
		$user_info['permissions'][] = array();
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
		global $context, $user_info;

		loadLanguage('Admin', 'english', true, true);
		$user_info['permissions'][] = 'admin_forum';
		$this->controller = new Admin_Controller(new Event_Manager());
		$method = new ReflectionMethod($this->controller, 'loadMenu');
		$method->setAccessible(true);
		$method->invoke($this->controller);

		$context['search_term'] = 'enable';
		$this->controller->action_search_internal();

		return new ArrayIterator(array_map(function ($search_result)
			{
				return array($search_result['url'], strtolower($search_result['name']));
			}, $context['search_results']
		));
	}
}
