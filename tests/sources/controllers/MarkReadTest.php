<?php

use ElkArte\Controller\MarkRead;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\User;

/**
 * TestCase class for the MarkRead Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestMarkReadController extends ElkArteCommonSetupTest
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

		new ElkArte\Themes\ThemeLoader();
	}

	public function tearDown()
	{
		parent::tearDown();
		unset($board, $topic);
	}

	/**
	 * Mark the boards Read
	 */
	public function testActionMarkboards()
	{
		global $modSettings;

		// Get the controller, call index
		$controller = new MarkRead(new EventManager());
		$controller->setUser(User::$info);
		$result = $controller->action_markboards();

		// Check that the url was set
		$this->assertEquals($result, '', $result);
	}

	/**
	 * Mark a unread relies as read
	 */
	public function testActionMarkReplies()
	{
		$req = HttpReq::instance();
		$req->query->topics = 1;

		// Get the controller, call index
		$controller = new MarkRead(new EventManager());
		$controller->setUser(User::$info);
		$result = $controller->action_markreplies();

		// Check that the result was set
		$this->assertEquals($result, 'action=unreadreplies', $result);
	}

	/**
	 * Mark a topic unread
	 */
	public function testActionMarkUnreadTopic()
	{
		global $board, $topic;

		$board = 1;
		$topic = 1;

		$req = HttpReq::instance();
		$req->query->t = 1;

		// Get the controller, call index
		$controller = new MarkRead(new EventManager());
		$controller->setUser(User::$info);
		$result = $controller->action_marktopic();

		// Check that result was set
		$this->assertEquals($result, 'board=1.0', $result);
	}

	/**
	 * Mark stuff read
	 */
	public function testActionMarkAsRead()
	{
		global $board, $topic;

		$board = 1;
		$topic = 1;

		$req = HttpReq::instance();
		$req->query->t = 1;
		$req->query->c = 1;
		$req->query->boards = 1;
		$req->query->children = 1;

		// Get the controller, call index
		$controller = new MarkRead(new EventManager());
		$controller->setUser(User::$info);
		$result = $controller->action_markasread();

		// Check that result was set
		$this->assertEquals($result, 'board=1.0', $result);
	}
}