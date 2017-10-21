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
class DispatcherTest extends PHPUnit_Framework_TestCase
{
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
			'help' => array('index', 'help'),
			'topic' => array('lock', 'printpage', 'sticky'),
			'profile' => array('index'),
			'reminder' => array('picktype', 'secret2', 'setpassword', 'setpassword2'),
		);

		foreach (array_keys($auto_actions) as $action)
		{
			$controller_name = ucfirst($action) . '_Controller';
			$controller = new $controller_name();
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
			'activate' => 'Register',
			'attachapprove' => 'ModerateAttachments',
			'addbuddy' => 'Members',
			'collapse' => 'BoardIndex',
			'contact' => 'Register',
			'coppa' => 'Register',
			'deletemsg' => 'RemoveTopic',
			'dlattach' => 'Attachment',
			'unwatchtopic' => 'Notify',
			'quickhelp' => 'Help',
			'login' => 'Auth',
			'login2' => 'Auth',
			'logout' => 'Auth',
			'quotefast' => 'Post',
			'quickmod' => 'MessageIndex',
			'quickmod2' => 'Display',
			'openidreturn' => 'OpenID',

		);

		foreach (array_keys($actions) as $action)
		{
			$controller_name = ucfirst($actions[$action]) . '_Controller';
			$controller = new $controller_name();
			$this->assertTrue(method_exists($controller, 'action_' . $action));
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
					'controller_name' => 'BoardIndex_Controller',
				),
			),
			// A topic
			array(
				'test_name' => 'a topic',
				'board' => 1,
				'topic' => 1,
				'result' => array(
					'function_name' => 'action_display',
					'controller_name' => 'Display_Controller',
				),
			),
			// A board
			array(
				'test_name' => 'a board',
				'board' => 1,
				'result' => array(
					'function_name' => 'action_messageindex',
					'controller_name' => 'MessageIndex_Controller',
				),
			),
			// Non-existing action
			array(
				'test_name' => 'non-existing action',
				'action' => 'qwerty',
				'result' => array(
					'function_name' => 'action_boardindex',
					'controller_name' => 'BoardIndex_Controller',
				),
			),
			// An existing one, no sub-action, naming patterns
			array(
				'test_name' => 'existing action without sub-action',
				'action' => 'announce',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => 'Announce_Controller',
				),
			),
			// An existing one, with sub-action, naming patterns
			array(
				'test_name' => 'existing action with sub-action (naming pattern)',
				'action' => 'announce',
				'sa' => 'test',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => 'Announce_Controller',
				),
			),
			// An existing one, action array, naming patterns, ADMINDIR
			array(
				'test_name' => 'existing action using ADMINDIR',
				'action' => 'admin',
				'result' => array(
					'function_name' => 'action_index',
					'controller_name' => 'Admin_Controller',
				),
			),
			// An existing one, action array
			array(
				'test_name' => 'action from actionarray',
				'action' => 'removetopic2',
				'result' => array(
					'function_name' => 'action_removetopic2',
					'controller_name' => 'RemoveTopic_Controller',
				),
			),
		);

		foreach ($tests as $test)
		{
			// Prepare some variables
			$topic = isset($test['topic']) ? $test['topic'] : null;
			$board = isset($test['board']) ? $test['board'] : null;
			$_GET = array(
				'action' => isset($test['action']) ? $test['action'] : null,
				'sa' => isset($test['sa']) ? $test['sa'] : null,
			);

			// Start a new dispatcher every time (the dispatching is done on __construct)
			$dispatcher = New Site_Dispatcher_Tester(new HttpReq);
			$this->assertTrue($dispatcher->compare($test['result']), $test['test_name']);
		}
	}

	/**
	 * prepare some test data, to use in these tests
	 */
	public function setUp()
	{
		// set up some data for testing
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 */
	public function tearDown()
	{
		// remove useless data
	}
}

/**
 * A small variation of Site_Dispatcher that provides a method to expose the
 * otherwise hidden results of the dispaching (_function_name and _controller_name)
 */
class Site_Dispatcher_Tester extends Site_Dispatcher
{
	/**
	 * This method compares the values of _function_name and
	 * _controller_name obtained from the Site_Dispatcher and the expected values
	 *
	 * @param array An array containing the expected results of a dispaching in the form:
	 *              'function_name' => 'function_name',
	 *              'controller_name' => 'controller_name',
	 * @return true if exactly the same, false otherwise
	 */
	public function compare($action)
	{
		return $this->_controller_name == $action['controller_name'] &&
		       $this->_function_name == $action['function_name'];
	}
}
