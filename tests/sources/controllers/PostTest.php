<?php

use ElkArte\Controller\Post;
use ElkArte\EventManager;
use ElkArte\User;

/**
 * TestCase class for the Post Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestPost extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		$this->setSession();

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Profile', 'english', true, true);
		theme()->getTemplates()->loadLanguageFile('Errors', 'english', true, true);
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
		$controller = new Post(new EventManager());
		$controller->setUser(User::$info);
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
		User::$info->ip = long2ip(rand(0, 2147483647));

		// Post a new topic
		$controller = new Post(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_post2();

		// Check
		loadBoard();
		$this->assertEquals($check + 1, $board_info['num_topics']);
	}

	/**
	 * Test making a post
	 */
	public function testModifyPost()
	{
		global $context, $board, $topic, $modSettings;

		require_once(SUBSDIR . '/Topic.subs.php');

		// Set up for modifying a post
		$board = 1;
		$topic = 2;
		loadBoard();
		$topic_info = getTopicInfo($topic, 'message');

		$_REQUEST['msg'] = $topic_info['id_last_msg'];
		$_POST['subject'] = $topic_info['subject'];
		$_POST['message'] = $topic_info['body'];
		$_POST['email'] = 'a@a.com';
		$_POST['icon'] = 'xx';
		$_POST['lock'] = 1;
		$_POST['additonal_items'] = 0;

		// Bypass spam protection
		User::$info->ip = long2ip(rand(0, 2147483647));

		// Lock the post
		$controller = new Post(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_post2();

		// Check
		$topic_info = getTopicInfo($topic);
		$this->assertEquals(1, $topic_info['locked']);
	}
}