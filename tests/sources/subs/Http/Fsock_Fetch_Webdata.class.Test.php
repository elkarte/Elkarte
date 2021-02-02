<?php

use ElkArte\Http\FsockFetchWebdata;
use PHPUnit\Framework\TestCase;

class TestFsockFetchWebdata extends TestCase
{
	protected $fetch_testcases = array();
	protected $post_testcases = array();
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		// url
		// post data
		// expected return code
		// expected in output
		$this->post_testcases = array(
			array(
				'https://www.google.com',
				array('gs_taif0' => 'elkarte'),
				405,
				'all we know',
			),
			array(
				'https://www.elkarte.net/community/index.php?action=search;sa=results',
				array('search' => 'panatone', 'search_selection' => 'all', 'advanced' => 0),
				200,
				'value="panatone"',
			),
		);

		// url
		// expected return code
		// expected in output
		// redirects
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
				'http://elkarte.net',
				200,
				'ElkArte, Free and Open Source Community Forum Software',
				2
			),
		);
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
	}

	/**
	 * Test Fsockopen fetching
	 */
	public function testFsockFetch()
	{
		// Start Fsockopen, pass some default values for a test
		$fsock = new FsockFetchWebdata(array(), 3);

		foreach ($this->fetch_testcases as $testcase)
		{
			// Fetch a page
			$fsock->get_url_data($testcase[0]);

			// Check for correct results
			if (!empty($testcase[1]))
				$this->assertEquals($testcase[1], $fsock->result('code'));

			if (!empty($testcase[2]))
				$this->assertContains($testcase[2], $fsock->result('body'));

			if (!empty($testcase[3]))
				$this->assertEquals($testcase[3], $fsock->result('redirects'));
		}
	}

	/**
	 * Test Fsockopen with posting data
	 */
	public function testFsockPost()
	{
		// Start curl, pass some default values for a test
		$fsock = new FsockFetchWebdata(array(), 3);

		foreach ($this->post_testcases as $testcase)
		{
			// Post to a page
			$fsock->get_url_data($testcase[0], $testcase[1]);

			// Check for correct fetch
			if (!empty($testcase[2]))
				$this->assertEquals($testcase[2], $fsock->result('code'));

			if (!empty($testcase[3]))
				$this->assertContains($testcase[3], $fsock->result('body'));
		}
	}
}
