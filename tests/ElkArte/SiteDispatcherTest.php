<?php

/**
 * TestCase class for dispatching.
 *
 * The few tests here test that for the actions known to us,
 * things can be found, and all expected methods exist.
 * Potentially useful during refactoring, as it will fail on us and
 * force to check that all expected subactions are still routed, and
 * update it.
 */

use ElkArte\AdminController\Admin;
use ElkArte\AdminController\AdminDebug;
use ElkArte\Controller\About;
use ElkArte\Controller\Announce;
use ElkArte\Controller\Attachment;
use ElkArte\Controller\BoardIndex;
use ElkArte\Controller\Display;
use ElkArte\Controller\Emailmoderator;
use ElkArte\Controller\Help;
use ElkArte\Controller\MessageIndex;
use ElkArte\Controller\ModerateAttachments;
use ElkArte\Controller\News;
use ElkArte\Controller\Post;
use ElkArte\Controller\Register;
use ElkArte\Controller\SplitTopics;
use ElkArte\Controller\Unread;
use ElkArte\EventManager;
use ElkArte\Helper\HttpReq;
use ElkArte\Profile\ProfileHistory;
use ElkArte\SiteDispatcher;
use ElkArte\User;
use PHPUnit\Framework\TestCase;

class SiteDispatcherTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	public static function setUpBeforeClass(): void
	{
		if (!defined('ELK'))
		{
			define('ELK', '1');
		}

		require_once(__DIR__ . '/../../sources/Load.php');
		require_once(__DIR__ . '/../../sources/Subs.php');
	}
	/**
	 * Tests automagical routing to an action
	 */
	public function testAutoDispatch()
	{
		// Auto-loaded actions.
		// ?action=something;sa=somedetail
		$auto_actions = array(
			'announce' => array('send', 'selectgroup'),
			'calendar' => array('calendar', 'ical', 'post'),
			'groups' => array('index', 'members', 'requests'),
			'help' => array('index'),
			'topic' => array('lock', 'printpage', 'sticky'),
			'reminder' => array('picktype', 'secret2', 'setpassword', 'setpassword2'),
		);

		foreach (array_keys($auto_actions) as $action)
		{
			$controller_name = '\\ElkArte\\Controller\\' . ucfirst($action);
			$controller = new $controller_name(new EventManager());
			$controller->setUser(User::$info);
			foreach ($auto_actions[$action] as $subaction)
				$this->assertTrue(method_exists($controller, 'action_' . $subaction));
		}
	}

	/**
	 * Tests auto-dispatch to sa, provided that the controller is hardcoded.
	 * (half-automagical dispatching)
	 */
	public function testSaDispatch()
	{
		// controller hardcoded, sa action
		// these are ?action=name routed to SomeController->action_name()
		$actions = array(
			'activate' => Register::class,
			'attachapprove' => ModerateAttachments::class,
			'collapse' => BoardIndex::class,
			'contact' => About::class,
			'coppa' => About::class,
			'dlattach' => Attachment::class,
			'quickhelp' => Help::class,
			'quotefast' => Post::class,
			'quickmod' => MessageIndex::class,
			'quickmod2' => Display::class,
		);

		foreach (array_keys($actions) as $action)
		{
			$controller_name = ucfirst($actions[$action]);
			$controller = new $controller_name(new EventManager());
			$this->assertTrue(method_exists($controller, 'action_' . $action, ), 'action_' . $action);
		}
	}

	/**
	 * Does a round of tests on the dispatcher itself
	 */
	public function testElkDispatcher()
	{
		global $topic, $board;

		$tests = array(
			// No action
			array(
				'test_name' => 'no action',
				'result' => array(
					'function_name' => 'action_boardindex',
					'controller_name' => BoardIndex::class,
				),
			),
			// A topic
			array(
				'test_name' => 'a topic',
				'board' => 1,
				'topic' => 1,
				'result' => array(
					'function_name' => 'action_display',
					'controller_name' => Display::class,
				),
			),
			// A board
			array(
				'test_name' => 'a board',
				'board' => 1,
				'result' => array(
					'function_name' => 'action_messageindex',
					'controller_name' => MessageIndex::class,
				),
			),
			// Non-existing action
			array(
				'test_name' => 'non-existing action',
				'action' => 'qwerty',
				'result' => array(
					'function_name' => 'action_boardindex',
					'controller_name' => BoardIndex::class,
				),
			),
			// An existing one, no sub-action, naming patterns
			array(
				'test_name' => 'existing action without sub-action',
				'action' => 'announce',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => Announce::class,
				),
			),
			// An existing one, with sub-action, naming patterns
			array(
				'test_name' => 'existing action with sub-action (naming pattern)',
				'action' => 'announce',
				'sa' => 'test',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => Announce::class,
				),
			),
			// An existing one, action array, naming patterns, ADMINDIR
			array(
				'test_name' => 'existing action using ADMINDIR',
				'action' => 'admin',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => Admin::class,
				),
			),
			// An existing one, action array
			array(
				'test_name' => 'action from actionarray',
				'action' => 'SplitTopics',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => SplitTopics::class,
				),
			),
			array(
				'test_name' => 'ModerateAttachments from naming pattern',
				'action' => 'ModerateAttachments',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => ModerateAttachments::class,
				),
			),
			array(
				'test_name' => 'Emailmoderator from naming pattern',
				'action' => 'Emailmoderator',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => Emailmoderator::class,
				),
			),
			array(
				'test_name' => 'unreadreplies from actionarray',
				'action' => 'unreadreplies',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => Unread::class,
				),
			),
			array(
				'test_name' => 'viewquery from actionarray',
				'action' => 'viewquery',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => AdminDebug::class,
				),
			),
			array(
				'test_name' => '.xml from actionarray',
				'action' => '.xml',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => News::class,
				),
			),
			array(
				'test_name' => 'collapse from actionarray',
				'action' => 'collapse',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => BoardIndex::class,
				),
			),
			array(
				'test_name' => 'forum from actionarray',
				'action' => 'forum',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => BoardIndex::class,
				),
			),
			array(
				'test_name' => 'quickhelp from actionarray',
				'action' => 'quickhelp',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => Help::class,
				),
			),
			array(
				'test_name' => 'post with jsmodify subaction from naming pattern',
				'action' => 'post',
				'sa' => 'jsmodify',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => Post::class,
				),
			),
			array(
				'test_name' => 'post with post2 subaction from naming pattern',
				'action' => 'post',
				'sa' => 'post2',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => Post::class,
				),
			),
			array(
				'test_name' => 'post with quotefast subaction from naming pattern',
				'action' => 'post',
				'sa' => 'quotefast',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => Post::class,
				),
			),
			array(
				'test_name' => 'quickmod from actionarray',
				'action' => 'quickmod',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => MessageIndex::class,
				),
			),
			array(
				'test_name' => 'quickmod2 from actionarray',
				'action' => 'quickmod2',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => Display::class,
				),
			),
			array(
				'test_name' => 'trackip from actionarray',
				'action' => 'trackip',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => ProfileHistory::class,
				),
			),
			// An existing one, not in the action array, naming patterns, defer to action_index
			array(
				'test_name' => 'action from naming pattern not in actionarray',
				'action' => 'about',
				'sa' => 'contact',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => About::class,
				),
			),
		);

		foreach ($tests as $test)
		{
			// Prepare some variables
			$topic = $test['topic'] ?? null;
			$board = $test['board'] ?? null;

			$req = HttpReq::instance();
			$req->query->action = $test['action'] ?? null;
			$req->query->sa = $test['sa'] ?? null;

			// Start a new dispatcher every time (the dispatching is done on __construct)
			$dispatcher = New SiteDispatcher_Tester($req);
			$this->assertTrue($dispatcher->compare($test['result']), $test['test_name']);
		}
	}

	/**
	 * prepare some test data, to use in these tests
	 */
	protected function setUp(): void
	{
		// set up some data for testing
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 */
	protected function tearDown(): void
	{
		// remove useless data
	}
}

/**
 * A small variation of SiteDispatcher that provides a method to expose the
 * otherwise hidden results of the dispaching (_function_name and _controller_name)
 */
class SiteDispatcher_Tester extends SiteDispatcher
{
	/**
	 * This method compares the values of _function_name and
	 * _controller_name obtained from the SiteDispatcher and the expected values
	 *
	 * @param array $action An array containing the expected results of a dispaching in the form:
	 *              'function_name' => 'function_name',
	 *              'controller_name' => 'controller_name',
	 * @return true if exactly the same, false otherwise
	 */
	public function compare($action)
	{
		return ltrim($this->_controller_name, '\\') === $action['controller_name'] &&
		       $this->_function_name === $action['function_name'];
	}
}
