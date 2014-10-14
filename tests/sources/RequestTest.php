<?php

/**
 * TestCase class for request parsing etc.
 *
 * Without SSI: test Request methods as self-containing.
 */
class TestRequest extends PHPUnit_Framework_TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		// clean slate please.
		$_REQUEST = array();
		$_GET = array();
		$_POST = array();

		$this->request = request();
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
		// Remove useless data.
		$_REQUEST = array();
		$_GET = array();
		$_POST = array();
	}

	/**
	 * parseRequest() with a simple string board and no topic
	 */
	public function testParseRequestString()
	{
		$_REQUEST['board'] = 'stuff<nm';
		$this->request->parseRequest();
		$board = $GLOBALS['board'];

		// We expect a nice board number
		$this->assertNotNull($board);
		$this->assertInternalType('numeric', $board);
		$this->assertEquals($board, 0);

		// We expect $topic initialized
		$this->assertTrue(isset($GLOBALS['topic']));
		$topic = $GLOBALS['topic'];
		$this->assertInternalType('numeric', $topic);
		$this->assertEquals($topic, 0);
	}

	/**
	 * parseRequest(), part numeric
	 */
	public function testParseRequestNumeric()
	{
		$_REQUEST['board'] = '34%07stuff<nm3';
		$_REQUEST['topic'] = 0.34;
		$this->request->parseRequest();
		$board = $GLOBALS['board'];

		// We expect a nice board number
		$this->assertNotNull($board);
		$this->assertInternalType('numeric', $board);
		$this->assertEquals($board, 34);

		// $topic stripped down
		$topic = $GLOBALS['topic'];
		$this->assertInternalType('numeric', $topic);
		$this->assertEquals($topic, 0);
	}

	/**
	 * Old links, i.e. board=3/10
	 */
	public function testOldLinks()
	{
		$_REQUEST['board'] = '3/10';
		$_REQUEST['topic'] = '7';
		$this->request->parseRequest();
		$board = $GLOBALS['board'];

		// We expect a nice board number
		$this->assertInternalType('numeric', $board);
		$this->assertEquals($board, 3);

		// $start should've been found
		$this->assertTrue(isset($_REQUEST['start']));
		$start = $_REQUEST['start'];
		$this->assertInternalType('numeric', $start);
		$this->assertEquals($start, 10);

		// $topic is set...
		$topic = $GLOBALS['topic'];
		$this->assertInternalType('numeric', $topic);
		$this->assertEquals($topic, 7);
	}

	/**
	 * YabbSE style threadid=number links
	 */
	public function testYabbSeThreads()
	{
		$_REQUEST['threadid'] = '4';
		$this->request->parseRequest();
		$board = $GLOBALS['board'];

		// We *still* expect a nice board number
		$this->assertInternalType('numeric', $board);
		$this->assertEquals($board, 0);

		// And a start
		$this->assertTrue(isset($_REQUEST['start']));
		$start = $_REQUEST['start'];
		$this->assertInternalType('numeric', $start);
		$this->assertEquals($start, 0);

		// And the thread as $topic
		$topic = $GLOBALS['topic'];
		$this->assertInternalType('numeric', $topic);
		$this->assertEquals($topic, 4);
	}

	/**
	 * Action should be present and string
	 */
	public function testActionAsArray()
	{
		$_GET['action'] = 10;

		$this->request->parseRequest();
		$is_string = $_GET['action'] === '10';

		$this->assertTrue($is_string);

		// We expect 'action' as string
		$this->assertInternalType('string', $_GET['action']);
	}
}