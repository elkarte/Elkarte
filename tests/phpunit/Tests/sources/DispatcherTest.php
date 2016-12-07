<?php

/**
 * TestCase class for dispatching.
 * The few tests here test that for the actions known to us,
 * things can be found, and all expected methods exist.
 * Potentially useful during refactoring, as it will fail on us and
 * force to check that all expected subactions are still routed, and
 * update it.
 */
class TestDispatcher extends PHPUnit_Framework_TestCase
{
	public function autoDispatchDataProvider()
	{
		// Auto-loaded actions.
		// ?action=something;sa=somedetail
		return array(
			'announce' => array('send', 'selectgroup'),
			'calendar' => array('calendar', 'ical', 'post'),
			'emailuser' => array('sendtopic', 'email'),
			'groups' => array('index', 'members', 'requests'),
			'help' => array('index', 'help'),
			'topic' => array('lock', 'printpage', 'sticky'),
			'profile' => array('index'),
			'reminder' => array('picktype', 'secret2', 'setpassword', 'setpassword2'),
			'xmlpreview' => array('index'),
		);
	}
	/**
	 * Tests automagical routing to an action
	 * @todo this should use a dataProvider annotation
	 */
	public function testAutoDispatch($action, $auto_actions)
	{
		$file_name = ucfirst($action) . '.controller.php';
		require_once(CONTROLLERDIR . '/' . $file_name);
		$controller_name = ucfirst($action) . '_Controller';
		$controller = new $controller_name();
		foreach ($auto_actions as $subaction)
			$this->assertTrue(method_exists($controller, 'action_' . $subaction));
	}

	public function subactionDataProvider()
	{
		// controller hardcoded, sa action
		// these are ?action=name routed to SomeController->action_name()
		return array(
			'activate' => 'Register',
			'attachapprove' => 'ModerateAttachments',
			'addbuddy' => 'Members',
			'collapse' => 'BoardIndex',
			'contact' => 'Register',
			'coppa' => 'Register',
			'deletemsg' => 'RemoveTopic',
			'dlattach' => 'Attachment',
			'findmember' => 'Members',
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
	}

	/**
	 * Tests auto-dispatch to sa, provided that the controller is hardcoded.
	 * (half-automagical dispatching)
	 * 
	 * @dataProvider subactionDataProvider
	 */
	public function testDispatchSubAction($action, $controller_name)
	{
		$controller_name = ucfirst($controller_name);
		$file_name = $controller_name . '.controller.php';

		require_once(CONTROLLERDIR . '/' . $file_name);
		$controller = new $controller_name();

		$this->assertTrue(method_exists($controller, 'action_' . $action));
	}

	/**
	 * 
	 */
	public function dispatcherDataProvider()
	{
		return array(
			// No action
			array(
				'action' => null,
				'subaction' => null,
				'board' => null,
				'topic' => null,
				'result' => array(
					'file_name' => CONTROLLERDIR . '/BoardIndex.controller.php',
					'function_name' => 'action_boardindex',
					'controller_name' => 'BoardIndex_Controller',
				),
			),
			// A topic
			array(
				'action' => null,
				'subaction' => null,
				'board' => 1,
				'topic' => 1,
				'result' => array(
					'file_name' => CONTROLLERDIR . '/Display.controller.php',
					'function_name' => 'action_display',
					'controller_name' => 'Display_Controller',
				),
			),
			// A board
			array(
				'action' => null,
				'subaction' => null,
				'board' => 1,
				'topic' => null,
				'result' => array(
					'file_name' => CONTROLLERDIR . '/MessageIndex.controller.php',
					'function_name' => 'action_messageindex',
					'controller_name' => 'MessageIndex_Controller',
				),
			),
			// Non-existing action
			array(
				'action' => 'qwerty',
				'subaction' => null,
				'board' => null,
				'topic' => null,
				'result' => array(
					'file_name' => CONTROLLERDIR . '/BoardIndex.controller.php',
					'function_name' => 'action_boardindex',
					'controller_name' => 'BoardIndex_Controller',
				),
			),
			// An existing one, no sub-action, naming patterns
			array(
				'action' => 'announce',
				'subaction' => null,
				'board' => null,
				'topic' => null,
				'result' => array(
					'file_name' => CONTROLLERDIR . '/Announce.controller.php',
					'function_name' => 'action_index',
					'controller_name' => 'Announce_Controller',
				),
			),
			// An existing one, with sub-action, naming patterns
			array(
				'action' => 'announce',
				'subaction' => 'test',
				'board' => null,
				'topic' => null,
				'result' => array(
					'file_name' => CONTROLLERDIR . '/Announce.controller.php',
					'function_name' => 'action_test',
					'controller_name' => 'Announce_Controller',
				),
			),
			// An existing one, action array, naming patterns, ADMINDIR
			array(
				'action' => 'admin',
				'subaction' => null,
				'board' => null,
				'topic' => null,
				'result' => array(
					'file_name' => ADMINDIR . '/Admin.controller.php',
					'function_name' => 'action_index',
					'controller_name' => 'Admin_Controller',
				),
			),
			// An existing one, action array
			array(
				'action' => 'removetopic2',
				'subaction' => null,
				'board' => null,
				'topic' => null,
				'result' => array(
					'file_name' => CONTROLLERDIR . '/RemoveTopic.controller.php',
					'function_name' => 'action_removetopic2',
					'controller_name' => 'RemoveTopic_Controller',
				),
			),
		);
	}
	
	/**
	 * Does a round of tests on the dispatcher itself
	 * 
	 * @dataProvider dispatcherDataProvider
	 */
	function testDispatcher(array $result, $action = null, $subaction = null, $board = null, $topic = null)
	{
		global $topic, $board;

		// Prepare some variables
		$topic = !empty($topic) ? $topic : null;
		$board = !empty($board) ? $board : null;
		$_GET = array(
			'action' => !empty($action) ? $action : null,
			'sa' => !empty($subaction) ? $subaction : null,
		);

		// Start a new dispatcher every time (the dispatching is done on __construct)
		$dispatcher = New Site_Dispatcher_Tester();
		$this->assertTrue($dispatcher->compare($result));
	}

	/**
	 * prepare some test data, to use in these tests
	 */
	function setUp()
	{
		// set up some data for testing
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 */
	function tearDown()
	{
		// remove useless data
	}
}

/**
 * A small variation of Site_Dispatcher that provides a method to expose the
 * otherwise hidden results of the dispaching (_file_name, _function_name and _controller_name)
 */
class Site_Dispatcher_Tester extends Site_Dispatcher
{
	/**
	 * This method compares the values of _file_name, _function_name and
	 * _controller_name obtained from the Site_Dispatcher and the expected values
	 *
	 * @param array An array containing the expected results of a dispaching in the form:
	 *              'file_name' => 'file_name',
	 *              'function_name' => 'function_name',
	 *              'controller_name' => 'controller_name',
	 * @return true if exactly the same, false otherwise
	 */
	public function compare($action)
	{
		return $this->_file_name == $action['file_name'] && 
		       $this->_controller_name == $action['controller_name'] &&
		       $this->_function_name == $action['function_name'];
	}
}