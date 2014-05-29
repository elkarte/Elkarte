<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');
// require_once(SOURCEDIR . '/SiteDispatcher.class.php');

/**
 * TestCase class for dispatching.
 * The few tests here test that for the actions known to us,
 * things can be found, and all expected methods exist.
 * Potentially useful during refactoring, as it will fail on us and
 * force to check that all expected subactions are still routed, and
 * update it.
 */
class TestDispatcher extends UnitTestCase
{
	/**
	 * Tests automagical routing to an action
	 */
	function testAutoDispatch()
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
			'xmlpreview' => array('index'),
		);

		foreach (array_keys($auto_actions) as $action)
		{
			$file_name = ucfirst($action) . '.controller.php';
			require_once(CONTROLLERDIR . '/' . $file_name);
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
	function testSaDispatch()
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

		foreach (array_keys($actions) as $action)
		{
			$file_name = ucfirst($actions[$action]) . '.controller.php';
			require_once(CONTROLLERDIR . '/' . $file_name);
			$controller_name = ucfirst($actions[$action]) . '_Controller';
			$controller = new $controller_name();
			$this->assertTrue(method_exists($controller, 'action_' . $action));
		}

	}

	function testLegacyDispatch()
	{
		// dunno how useful this is :P
		// controller and sa hardcoded
		$legacy_actions = array(

		);

		$leftovers = array(
			'editpoll' => array('Poll.controller.php', 'Poll_Controller', 'action_editpoll'),
			'editpoll2' => array('Poll.controller.php', 'Poll_Controller', 'action_editpoll2'),
			'jsmodify' => array('Post.controller.php', 'Post_Controller', 'action_jsmodify'),
			'jsoption' => array('Themes.php', 'Themes_Controller', 'action_jsoption'),
			'lockvoting' => array('Poll.controller.php', 'Poll_Controller', 'action_lockvoting'),
			'markasread' => array('Markasread.controller.php', 'MarkRead_Controller', 'action_index'),
			'mergetopics' => array('MergeTopics.controller.php', 'MergeTopics_Controller', 'action_index'),
			'memberlist' => array('Memberlist.controller.php', 'Memberlist_Controller', 'action_index'),
			'moderate' => array('ModerationCenter.controller.php', 'ModerationCenter_Controller', 'action_modcenter'),
			'karma' => array('Karma.controller.php', 'Karma_Controller', ''),
			'movetopic' => array('MoveTopic.controller.php', 'MoveTopic_Controller', 'action_movetopic'),
			'movetopic2' => array('MoveTopic.controller.php', 'MoveTopic_Controller', 'action_movetopic2'),
			'notify' => array('Notify.controller.php', 'Notify_Controller', 'action_notify'),
			'notifyboard' => array('Notify.controller.php', 'Notify_Controller', 'action_notifyboard'),
			'pm' => array('PersonalMessage.controller.php', 'PersonalMessage_Controller', 'action_index'),
			'post2' => array('Post.controller.php', 'Post_Controller', 'action_post2'),
// 			'recent' => array('Recent.controller.php', 'Recent_Controller', 'action_recent'),
			'register' => array('Register.controller.php', 'Register_Controller', 'action_register'),
			'register2' => array('Register.controller.php', 'Register_Controller', 'action_register2'),
// 			'removepoll' => array('Poll.controller.php', 'Poll_Controller', 'action_removepoll'),
			'removetopic2' => array('RemoveTopic.controller.php', 'RemoveTopic_Controller', 'action_removetopic2'),
			'reporttm' => array('Emailuser.controller.php', 'Emailuser_Controller', 'action_reporttm'),
			'restoretopic' => array('RemoveTopic.controller.php', 'RemoveTopic_Controller', 'action_restoretopic'),
// 			'search' => array('Search.controller.php', 'action_plushsearch1'),
// 			'search2' => array('Search.controller.php', 'action_plushsearch2'),
			'sendtopic' => array('Emailuser.controller.php', 'Emailuser_Controller', 'action_sendtopic'),
			'suggest' => array('Suggest.controller.php', 'Suggest_Controller', 'action_suggest'),
			'spellcheck' => array('Post.controller.php', 'Post_Controller', 'action_spellcheck'),
			'splittopics' => array('SplitTopics.controller.php', 'SplitTopics_Controller', 'action_splittopics'),
			'stats' => array('Stats.controller.php', 'Stats_Controller', 'action_stats'),
			'theme' => array('Themes.php', 'Themes_Controller', 'action_thememain'),
			'trackip' => array('ProfileHistory.controller.php', 'action_trackip'),
			'unread' => array('Recent.controller.php', 'Recent_Controller', 'action_unread'),
			'unreadreplies' => array('Recent.controller.php', 'Recent_Controller', 'action_unread'),
			'verificationcode' => array('Register.controller.php', 'Register_Controller', 'action_verificationcode'),
			'viewprofile' => array('Profile.controller.php', 'action_modifyprofile'),
// 			'vote' => array('Poll.controller.php', 'Poll_Controller', 'action_vote'),
			'viewquery' => array('AdminDebug.php', 'AdminDebug_Controller', 'action_viewquery'),
			'viewadminfile' => array('AdminDebug.php', 'AdminDebug_Controller', 'action_viewadminfile'),
			'.xml' => array('News.controller.php', 'News_Controller', 'action_showfeed'),
			'xmlhttp' => array('Xml.controller.php', 'Xml_Controller', 'action_index'),
		);

		$adminActions = array ('admin', 'attachapprove', 'jsoption', 'theme', 'viewadminfile', 'viewquery');

	}

	/**
	 * Does a round of tests on the dispatcher itself
	 */
	function testElkDispatcher()
	{
		global $topic, $board;

		$tests = array(
			// No action
			array(
				'result' => array(
					'file_name' => CONTROLLERDIR . '/BoardIndex.controller.php',
					'function_name' => 'action_boardindex',
					'controller_name' => 'BoardIndex_Controller',
				),
			),
			// A topic
			array(
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
				'board' => 1,
				'result' => array(
					'file_name' => CONTROLLERDIR . '/MessageIndex.controller.php',
					'function_name' => 'action_messageindex',
					'controller_name' => 'MessageIndex_Controller',
				),
			),
			// Non-existing action
			array(
				'action' => 'qwerty',
				'result' => array(
					'file_name' => CONTROLLERDIR . '/BoardIndex.controller.php',
					'function_name' => 'action_boardindex',
					'controller_name' => 'BoardIndex_Controller',
				),
			),
			// An existing one, no sub-action, naming patterns
			array(
				'action' => 'announce',
				'result' => array(
					'file_name' => CONTROLLERDIR . '/Announce.controller.php',
					'function_name' => 'action_index',
					'controller_name' => 'Announce_Controller',
				),
			),
			// An existing one, with sub-action, naming patterns
			array(
				'action' => 'announce',
				'sa' => 'test',
				'result' => array(
					'file_name' => CONTROLLERDIR . '/Announce.controller.php',
					'function_name' => 'action_test',
					'controller_name' => 'Announce_Controller',
				),
			),
			// An existing one, action array, naming patterns, ADMINDIR
			array(
				'action' => 'admin',
				'result' => array(
					'file_name' => ADMINDIR . '/Admin.controller.php',
					'function_name' => 'action_index',
					'controller_name' => 'Admin_Controller',
				),
			),
			// An existing one, action array
			array(
				'action' => 'removetopic2',
				'result' => array(
					'file_name' => CONTROLLERDIR . '/RemoveTopic.controller.php',
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
			$dispatcher = New Site_Dispatcher_Tester();
			$this->assertTrue($dispatcher->compare($test['result']));
		}
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