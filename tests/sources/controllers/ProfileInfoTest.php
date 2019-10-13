<?php

/**
 * TestCase class for the Profile Info Controller
 */
class TestProfileInfo extends \PHPUnit\Framework\TestCase
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
	{
		global $modSettings, $settings;

		// Lets add in just enough info for the system to think we are logged
		$modSettings['smiley_sets_known'] = 'none';
		$modSettings['smileys_url'] = 'http://127.0.0.1/smileys';
		$settings['default_theme_dir'] = '/var/www/themes/default';

		\ElkArte\User::$info = new \ElkArte\ValuesContainer([
			'id' => 1,
			'ip' => long2ip(rand(0, 2147483647)),
			'language' => 'english',
			'is_admin' => true,
			'is_guest' => false,
			'username' => 'testing',
			'query_wanna_see_board' => '1=1',
			'is_moderator' => isset(\ElkArte\User::$info->is_moderator) ? \ElkArte\User::$info->is_moderator : false,
			'email' => 'a@a.com',
			'ignoreusers' => '',
			'name' => 'itsme',
			'smiley_set' => 'none',
			'time_offset' => 0,
			'time_format' => '',
			'possibly_robot' => false,
			'posts' => '15',
			'buddies' => array(),
		]);

		$settings['page_index_template'] = array(
			'base_link' => '<li></li>',
			'previous_page' => '<span></span>',
			'current_page' => '<li></li>',
			'next_page' => '<span></span>',
			'expand_pages' => '<li></li>',
			'all' => '<span></span>',
		);

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Profile', 'english', true, true);
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
		global $modSettings, $settings;

		// remove temporary test data
		unset($settings, $modSettings);
		\ElkArte\User::$info = null;
	}

	/**
	 * Test the settings for profile summary
	 */
	public function testProfileSummary()
	{
		global $context, $modSettings;

		$controller = new \ElkArte\Controller\ProfileInfo(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Lets see some items loaded into context, there should some data
		$this->assertNotNull($context);
		$this->assertEquals($context['can_see_ip'], true);
		$this->assertEquals($modSettings['jquery_include_ui'], true);
	}
}
