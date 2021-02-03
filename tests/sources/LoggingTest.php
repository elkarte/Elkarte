<?php

use PHPUnit\Framework\TestCase;

require_once(SUBSDIR . '/Logging.subs.php');

/**
 * TestCase class for logging
 * @backupGlobals disabled
 */
class TestLogging extends TestCase
{
	protected $backupGlobalsBlacklist = ['user_info'];
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		require_once(SUBSDIR . '/ProfileHistory.subs.php');
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
		// remove data
	}

	public function testLogLoginHistory()
	{
		logLoginHistory(1, '10.100.10.100', '11.111.100.10');

		$count = getLoginCount('id_member = {int:id_member}', array('current_member' => 1));
		$this->assertEquals(1, $count);
	}
}
