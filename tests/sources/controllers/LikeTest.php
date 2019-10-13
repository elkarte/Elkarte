<?php

/**
 * TestCase class for the Likes Controller
 */
class TestLike extends \PHPUnit\Framework\TestCase
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
		$modSettings['default_forum_action'] = [];
		$settings['default_theme_dir'] = '/var/www/themes/default';

		\ElkArte\User::$info = new \ElkArte\ValuesContainer([
			'id' => 1,
			'ip' => long2ip(rand(0, 2147483647)),
			'language' => 'english',
			'is_admin' => true,
			'is_guest' => false,
			'username' => 'testing',
			'query_wanna_see_board' => '1=1',
			'query_see_board' => '1=1',
			'is_moderator' => false,
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
		theme()->getTemplates()->loadLanguageFile('Errors', 'english', true, true);
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
	 * Test the showing the Likes Listing
	 */
	public function testShowLikes()
	{
		global $context, $modSettings;

		$modSettings['likes_enabled'] = 1;
		$context['profile_menu_name'] = 'menu_data_view_likes';
		require_once(SUBSDIR . '/Profile.subs.php');

		$controller = new \ElkArte\Controller\Likes(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_showProfileLikes();

		// Lets see some items loaded into context, as createlist will have run
		$this->assertEquals('Posts you liked', $context['menu_data_view_likes']['tab_data']['title']);
		$this->assertEquals(4, $context['view_likes']['num_columns']);
		$this->assertEquals('Likes', $context['view_likes']['title']);
	}

	/**
	 * Test liking a post
	 */
	public function testLikePost()
	{
		global $modSettings, $context;

		// Set the form
		$_req = \ElkArte\HttpReq::instance();
		$_req->query['msg'] = 1;
		$_req->query['xml'] = '';
		$_req->query['api'] = 'json';

		// Trick the session
		$_GET['elk_test_session'] = 'elk_test_session';
		$_SESSION['session_value'] = 'elk_test_session';
		$_SESSION['session_var'] = 'elk_test_session';
		$_SESSION['USER_AGENT'] = 'elkarte';
		$modSettings['disableCheckUA'] = 1;

		// Enabled but no mentions please
		$modSettings['likes_enabled'] = 1;
		$modSettings['mentions_enabled'] = 0;

		// Make a lik
		$controller = new \ElkArte\Controller\Likes(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_likepost_api();

		$this->assertEquals(1, $context['json_data']['count']);
	}
}
