<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(SUBSDIR . '/Boards.subs.php');

/**
 * TestCase class for boards subs
 */
class TestBoards extends UnitTestCase
{
	/**
	 * @todo prepare some test data, to use in these tests
	 */
	function setUp()
	{
		// set up some data for testing
		// might wanna insert some boards, topics, and use those all through the tests here
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 */
	function tearDown()
	{
		// remove useless data
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
