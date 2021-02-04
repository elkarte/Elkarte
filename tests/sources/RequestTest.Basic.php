<?php

use PHPUnit\Framework\TestCase;

/**
 * TestCase class for request parsing etc.
 *
 * Without SSI: test Request methods as self-containing.
 */
class TestRequest extends TestCase
{
	protected $backupGlobalsBlacklist = ['user_info'];
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
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
	protected function tearDown(): void
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
		$this->assertIsNumeric($board);
		$this->assertEquals(0, $board);

		// We expect $topic initialized
		$this->assertTrue(isset($GLOBALS['topic']));
		$topic = $GLOBALS['topic'];
		$this->assertIsNumeric($topic);
		$this->assertEquals(0, $topic);
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
		$this->assertIsNumeric($board);
		$this->assertEquals(34, $board);

		// $topic stripped down
		$topic = $GLOBALS['topic'];
		$this->assertIsNumeric($topic);
		$this->assertEquals(0, $topic);
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
		$this->assertIsNumeric($board);
		$this->assertEquals(3, $board);

		// $start should've been found
		$this->assertTrue(isset($_REQUEST['start']));
		$start = $_REQUEST['start'];
		$this->assertIsNumeric($start);
		$this->assertEquals(10, $start);

		// $topic is set...
		$topic = $GLOBALS['topic'];
		$this->assertIsNumeric($topic);
		$this->assertEquals(7, $topic);
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
		$this->assertIsNumeric($board);
		$this->assertEquals(0, $board);

		// And a start
		$this->assertTrue(isset($_REQUEST['start']));
		$start = $_REQUEST['start'];
		$this->assertIsNumeric($start);
		$this->assertEquals(0, $start);

		// And the thread as $topic
		$topic = $GLOBALS['topic'];
		$this->assertIsNumeric($topic);
		$this->assertEquals(4, $topic);
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
		$this->assertIsString($_GET['action']);
	}
}
