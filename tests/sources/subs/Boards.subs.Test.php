<?php

use ElkArte\User;
use PHPUnit\Framework\TestCase;

/**
 * TestCase class for boards subs: working with boards.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */
class TestBoards extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Prepare some test data, to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		// Set up some data for testing
		//
		// @todo might want to insert some boards, topics, and use those all through the tests here
		require_once(SUBSDIR . '/Boards.subs.php');

		parent::setUp();
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
		// remove useless data.
		// leave database in initial state!
	}

	/**
	 * Tests for boardInfo()
	 */
	public function testBoardInfo()
	{
		// assume we have at least board no 1
		$boardInfo = boardInfo(1);

		// we expect the return to have a board name and some posts count for us
		$this->assertNotNull($boardInfo['name']);
		$this->assertNotNull($boardInfo['count_posts']);
		$this->assertIsNumeric($boardInfo['count_posts']);
	}

	/**
	 * Tests for boardPosts()
	 */
	public function testBoardPosts()
	{
		// assume we have board no 1
		$post_counts = boardsPosts(array(1), array());

		// we expect the return as an array of 'id_board' => post count
		$this->assertNotNull($post_counts[1]);
		$num = is_numeric($post_counts[1]);
		$this->assertTrue($num);
		$this->assertIsNumeric($post_counts[1]);
	}

	/**
	 * Mark the boards read
	 */
	public function testMarkBoardsRead()
	{
		global $modSettings;

		// Set a max that is greater than what we have done in the tests
		$modSettings['maxMsgID'] = 10;

		// Mark them as read
		markBoardsRead(1, false, true);

		require_once(SUBSDIR . '/Topic.subs.php');
		$num = getUnreadCountSince(1, 0);

		$this->assertEquals(0, $num, 'Missed unread topics, expected 0 saw ' . $num);
	}
}
