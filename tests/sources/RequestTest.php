<?php

require_once(TESTDIR . 'simpletest/autorun.php');

/**
 * TestCase class for request parsing etc.
 * Without SSI: test Request methods as self-containing.
 */
class TestRequest extends UnitTestCase
{
	/**
	 * prepare what is necessary to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
		// we are not in Elk, thereby need to set our define
		if (!defined('ELK'))
			define('ELK', 'SSI');

		// and include our class. Kinda difficult without it.
		require_once(TESTDIR . '../sources/Subs.php');
		require_once(TESTDIR . '../sources/Request.php');
		require_once(TESTDIR . '../sources/QueryString.php');

		// clean slate please.
		$_REQUEST = array();
		$_GET = array();
		$_POST = array();

		$this->request = request();
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	function tearDown()
	{
		// remove useless data.
		$_REQUEST = array();
		$_GET = array();
		$_POST = array();
	}

	/**
	 * parseRequest() with a simple string board and no topic
	 */
	function testParseRequestString()
	{
		$_REQUEST['board'] = 'stuff<nm';
		$this->request->parseRequest();
		$board = $GLOBALS['board'];

		// we expect a nice board number
		$this->assertNotNull($board);
		$this->assertIsA($board, 'numeric');
		$this->assertEqual($board, 0);

		// we expect $topic initialized
		$this->assertTrue(isset($GLOBALS['topic']));
		$topic = $GLOBALS['topic'];
		$this->assertIsA($topic, 'numeric');
		$this->assertEqual($topic, 0);
	}

	/**
	 * parseRequest(), part numeric
	 */
	function testParseRequestNumeric()
	{
		$_REQUEST['board'] = '34%07stuff<nm3';
		$_REQUEST['topic'] = 0.34;
		$this->request->parseRequest();
		$board = $GLOBALS['board'];

		// we expect a nice board number
		$this->assertNotNull($board);
		$this->assertIsA($board, 'numeric');
		$this->assertEqual($board, 34);

		// $topic stripped down
		$topic = $GLOBALS['topic'];
		$this->assertIsA($topic, 'numeric');
		$this->assertEqual($topic, 0);
	}

	/**
	 * Old links, i.e. board=3/10
	 */
	function testOldLinks()
	{
		$_REQUEST['board'] = '3/10';
		$_REQUEST['topic'] = '7';
		$this->request->parseRequest();
		$board = $GLOBALS['board'];

		// we expect a nice board number
		$this->assertIsA($board, 'numeric');
		$this->assertEqual($board, 3);

		// $start should've been found
		$this->assertTrue(isset($_REQUEST['start']));
		$start = $_REQUEST['start'];
		$this->assertIsA($start, 'numeric');
		$this->assertEqual($start, 10);

		// $topic is set...
		$topic = $GLOBALS['topic'];
		$this->assertIsA($topic, 'numeric');
		$this->assertEqual($topic, 7);
	}

	/**
	 * YabbSE style threadid=number links
	 */
	function testYabbSeThreads()
	{
		$_REQUEST['threadid'] = '4';
		$this->request->parseRequest();
		$board = $GLOBALS['board'];

		// we *still* expect a nice board number
		$this->assertIsA($board, 'numeric');
		$this->assertEqual($board, 0);

		// and a start
		$this->assertTrue(isset($_REQUEST['start']));
		$start = $_REQUEST['start'];
		$this->assertIsA($start, 'numeric');
		$this->assertEqual($start, 0);

		// and the thread as $topic
		$topic = $GLOBALS['topic'];
		$this->assertIsA($topic, 'numeric');
		$this->assertEqual($topic, 4);
	}

	/**
	 * action should be present and string
	 */
	function testActionAsArray()
	{
		$_GET['action'] = 10;

		$this->request->parseRequest();
		$is_string = $_GET['action'] === '10';

		$this->assertTrue($is_string);
		// we expect 'action' as string
		$this->assertIsA($_GET['action'], 'string');
	}
}
