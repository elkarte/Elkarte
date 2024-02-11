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
use ElkArte\Controller\Announce;
use ElkArte\Controller\Attachment;
use ElkArte\Controller\Auth;
use ElkArte\Controller\BoardIndex;
use ElkArte\Controller\Display;
use ElkArte\Controller\Help;
use ElkArte\Controller\Members;
use ElkArte\Controller\MessageIndex;
use ElkArte\Controller\ModerateAttachments;
use ElkArte\Controller\Notify;
use ElkArte\Controller\Post;
use ElkArte\Controller\Register;
use ElkArte\Controller\RemoveTopic;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\SiteDispatcher;
use ElkArte\User;
use PHPUnit\Framework\TestCase;

class DispatcherTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];
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
			'emailuser' => array('sendtopic', 'email'),
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
			'addbuddy' => Members::class,
			'collapse' => BoardIndex::class,
			'contact' => Register::class,
			'coppa' => Register::class,
			'deletemsg' => RemoveTopic::class,
			'dlattach' => Attachment::class,
			'unwatchtopic' => Notify::class,
			'quickhelp' => Help::class,
			'login' => Auth::class,
			'login2' => Auth::class,
			'logout' => Auth::class,
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
				'action' => 'removetopic2',
				'result' => array(
					'function_name' => 'action_removetopic2',
					'controller_name' => RemoveTopic::class,
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
			$req->query->sa = $test['action'] ?? null;

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
