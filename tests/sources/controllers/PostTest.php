<?php

/**
 * TestCase class for the Post Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestPost extends \PHPUnit\Framework\TestCase
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
	{
		global $modSettings, $context, $settings;

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

		// Trick the session
		$_POST['elk_test_session'] = 'elk_test_session';
		$_SESSION['session_value'] = 'elk_test_session';
		$_SESSION['session_var'] = 'elk_test_session';
		$_SESSION['USER_AGENT'] = 'elkarte';
		$modSettings['disableCheckUA'] = 1;
		$context['session_var'] = 'elk_test_session';
		$context['session_value'] = 'elk_test_session';

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
	 * Test making a post
	 */
	public function testMakeReplyPost()
	{
		global $board, $topic;

		require_once(SUBSDIR . '/Topic.subs.php');

		// Set up for making a post
		$board = 1;
		$topic = 1;
		loadBoard();
		$_POST['subject'] = 'Welcome to ElkArte!';
		$_POST['message'] = 'Thanks, great to be here :D';
		$_POST['email'] = 'a@a.com';
		$_POST['icon'] = 'xx';
		$_POST['additonal_items'] = 0;

		// Used for the test to see if we updated the topic
		$topic_info = getTopicInfo($topic);
		$check = (int) $topic_info['num_replies'];

		// Post a reply
		$controller = new \ElkArte\Controller\Post(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_post2();

		// Check
		$topic_info = getTopicInfo($topic);
		$this->assertEquals($check + 1, $topic_info['num_replies']);
	}

	/**
	 * Test making a new topic
	 */
	public function testMakeNewTopic()
	{
		global $board, $board_info;

		require_once(SUBSDIR . '/Topic.subs.php');

		// Set up for making a post
		$board = 1;
		loadBoard();
		$_POST['subject'] = 'Welcome to Travis';
		$_POST['message'] = 'So you want to test on Travis, fine, sure.';
		$_POST['email'] = 'a@a.com';
		$_POST['icon'] = 'thumbup';
		$_POST['additonal_items'] = 0;

		// Used for the test to see if we updated the topic
		$check = (int) $board_info['num_topics'];

		// Bypass spam protection
		\ElkArte\User::$info->ip = long2ip(rand(0, 2147483647));

		// Post a new topic
		$controller = new \ElkArte\Controller\Post(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_post2();

		// Check
		loadBoard();
		$this->assertEquals($check + 1, $board_info['num_topics']);
	}

	/**
	 * Test making a post
	 */
	public function teestModifyPost()
	{
		global $context, $board, $topic, $modSettings;

		require_once(SUBSDIR . '/Topic.subs.php');

		// Set up for making a post
		$board = 1;
		$topic = 1;
		loadBoard();
		$_POST['subject'] = 'Welcome to ElkArte!';
		$_POST['message'] = 'Thanks, great to be here :D';
		$_POST['email'] = 'a@a.com';
		$_POST['icon'] = 'xx';
		$_POST['additonal_items'] = 0;

		// Used for the test to see if we updated the topic
		$topic_info = getTopicInfo($topic);
		$check = (int) $topic_info['num_replies'];
		$id = $topic_info['id_last_msg'];

		// Bypass spam protection
		\ElkArte\User::$info->ip = long2ip(rand(0, 2147483647));

		// Post a reply
		$controller = new \ElkArte\Controller\Post(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_post2();

		// Check
		$topic_info = getTopicInfo($topic);
		$this->assertEquals($check + 1, $topic_info['num_replies']);
	}
}