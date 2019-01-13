<?php

class TestStreamFetchWebdata extends \PHPUnit\Framework\TestCase
{
	protected $fetch_testcases = array();
	protected $post_testcases = array();

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
				'https://www.w3schools.com/action_page.php',
				array('firstname' => 'elkarte', 'lastname' => 'forum'),
				200,
				'firstname=elkarte&lastname=forum&nbsp;',
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
		$this->fetch_testcases = array(
			array(
				'https://www.google.com',
				200,
				'Search the world\'s information',
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
	 * Test Stream fetching
	 */
	public function testFetch()
	{
		// Start Stream, pass some default values for a test
		$fsock = new \ElkArte\Http\StreamFetchWebdata(array(), 3);

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
	 * Test Stream with posting data
	 */
	public function testPost()
	{
		// Start curl, pass some default values for a test
		$fsock = new \ElkArte\Http\StreamFetchWebdata(array(), 3);

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
