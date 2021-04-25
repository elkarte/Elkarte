<?php

/**
 * TestCase class for recent posts
 */
class TestRecentPosts extends PHPUnit_Framework_TestCase
{
	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
	{
		global $modSettings, $user_info, $settings;

		// Lets add in just enough info for the system to think we are logged
		$modSettings['smiley_sets_known'] = 'none';
		$modSettings['smileys_url'] = 'http://127.0.0.1/smileys';
		$modSettings['default_forum_action'] = '';
		$settings['default_theme_dir'] = '/var/www/themes/default';

		// We are not logged in for this test, so lets fake it
		$user_info = array(
			'id' => 1,
			'ip' => '127.0.0.1',
			'language' => 'english',
			'is_admin' => true,
			'is_guest' => false,
			'username' => 'testing',
			'query_wanna_see_board' => '1=1',
			'is_moderator' => &$user_info['is_moderator'],
			'email' => 'a@a.com',
			'ignoreusers' => '',
			'name' => 'itsme',
			'smiley_set' => 'none',
			'time_offset' => 0,
			'time_format' => '',
			'possibly_robot' => false,
			'posts' => '15',
			'buddies' => array(),
			'groups' => array(0 => 1),
			'ignoreboards' => array(),
			'mod_cache' => array('gq' => '1=1', 'bq' => '1=1', 'ap' => '1=1'),
		);

		$settings['page_index_template'] = array(
			'base_link' => '<li></li>',
			'previous_page' => '<span></span>',
			'current_page' => '<li></li>',
			'next_page' => '<span></span>',
			'expand_pages' => '<li></li>',
			'all' => '<span></span>',
		);

		loadTheme();
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
		global $modSettings, $user_info, $settings;

		// remove temporary test data
		unset($user_info, $settings, $modSettings);
	}

	/**
	 * Test the settings for recent post listing
	 */
	public function testRecent()
	{
		global $context;

		$controller = new Recent_Controller();
		$controller->pre_dispatch();
		$controller->action_recent();

		// Lets see some items loaded into context, there should be the first post
		$this->assertNotNull($context);
		$this->assertEquals($context['posts'][1]['subject'], 'Welcome to ElkArte!');
		$this->assertEquals($context['sub_template'], 'recent');
	}
}
