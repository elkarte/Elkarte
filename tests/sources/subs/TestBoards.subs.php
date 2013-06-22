<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');
require_once(SUBSDIR . '/Boards.subs.php');

/**
 * TestCase class for boards subs: working with boards.
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */
class TestBoards extends UnitTestCase
{
	/**
	 * @todo prepare some test data, to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
		// set up some data for testing
		// might wanna insert some boards, topics, and use those all through the tests here
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	function tearDown()
	{
		// remove useless data.
		// leave database in initial state!
	}

	/**
	 * Tests for boardInfo()
	 */
	function testBoardInfo()
	{
		// assume we have at least board no 1
		$boardInfo = boardInfo(1);

		// we expect the return to have a board name and some posts count for us
		$this->assertNotNull($boardInfo['name']);
		$this->assertNotNull($boardInfo['count_posts']);
		$this->assertIsA($boardInfo['count_posts'], 'numeric');
	}

	/**
	 * Tests for boardPosts()
	 */
	function testBoardPosts()
	{
		// assume we have board no 1
		$post_counts = boardsPosts(array(1), array());

		// we expect the return as an array of 'id_board' => post count
		$this->assertNotNull($post_counts[1]);
		$num = is_numeric($post_counts[1]);
		$this->assertTrue($num);
		$this->assertIsA($post_counts[1], 'numeric');
	}
}
