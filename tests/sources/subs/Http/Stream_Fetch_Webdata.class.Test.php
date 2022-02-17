<?php

use ElkArte\Http\StreamFetchWebdata;
use PHPUnit\Framework\TestCase;

class TestStreamFetchWebdata extends TestCase
{
	protected $fetch_testcases = array();
	protected $post_testcases = array();
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		// url
		// post data
		// expected return code
		// expected in output
		$this->post_testcases = array(
			array(
				'https://www.w3schools.com/action_page.php',
				array('firstname' => 'elkarte', 'lastname' => 'forum'),
				200,
				'firstname=elkarte&lastname=forum&nbsp;',
			),
			array(
				'https://www.elkarte.net/community/index.php?action=search;sa=results',
				array('search' => 'stuff', 'search_selection' => 'all', 'advanced' => 0),
				200,
				'Please enter the verification code below to continue to the results',
			),
		);

		// url
		// expected return code
		// expected in output
		$this->fetch_testcases = array(
			array(
				'https://developer.mozilla.org/en-US/',
				200,
				'Resources for developers',
			),
			array(
				'http://www.google.com/elkarte',
				404,
			),
			array(
				'http://elkarte.github.io/addons/',
				200,
				'Addons to extend',
				1
			),
		);
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
	}

	/**
	 * Test Stream fetching
	 */
	public function testStreamFetch()
	{
		// Start Stream, pass some default values for a test
		$fsock = new StreamFetchWebdata(array(), 3);

		foreach ($this->fetch_testcases as $testcase)
		{
			// Fetch a page
			$fsock->get_url_data($testcase[0]);

			// Check for correct results
			if (!empty($testcase[1]))
				$this->assertEquals($testcase[1], $fsock->result('code'), 'FetchCodeError:: ' . $testcase[0]);

			if (!empty($testcase[2]))
				$this->assertStringContainsString($testcase[2], $fsock->result('body'), 'FetchBodyError:: ' . $testcase[0]);

			if (!empty($testcase[3]))
				$this->assertEquals($testcase[3], $fsock->result('redirects'), 'FetchRedirectError:: ' . $testcase[0]);
		}
	}

	/**
	 * Test Stream with posting data
	 */
	public function testStreamPost()
	{
		// Start stream, pass some default values for a test
		$fsock = new StreamFetchWebdata(array(), 3);

		foreach ($this->post_testcases as $testcase)
		{
			// Post to a page
			$fsock->get_url_data($testcase[0], $testcase[1]);

			// Check for correct fetch
			if (!empty($testcase[2]))
				$this->assertEquals($testcase[2], $fsock->result('code'), 'PostCodeError:: ' . $testcase[0]);

			if (!empty($testcase[3]))
				$this->assertStringContainsString($testcase[3], $fsock->result('body'), 'PostBodyError:: ' . $testcase[0]);
		}
	}
}
