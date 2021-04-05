<?php

class TestCurl_Fetch_Webdata extends PHPUnit_Framework_TestCase
{
	protected $curl_fetch_testcases = array();
	protected $curl_post_testcases = array();

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
		$this->curl_post_testcases = array(
			array(
				'https://www.google.com',
				array('gs_taif0' => 'elkarte'),
				405,
				'all we know',
			),
			array(
				'https://duckduckgo.com/html',
				array('q' => 'elkarte', 'ia' => 'about'),
				200,
				'Free and Open Source Community Forum Software',
			),
		);

		// url
		// expected return code
		// expected in output
		$this->curl_fetch_testcases = array(
			array(
				'https://www.google.com',
				200,
				'Search the world\'s information',
			),
			array(
				'http://www.google.com/elkarte',
				404,
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
	* Test curl fetching
	*/
	public function testFetch()
	{
		// Start curl, pass some default values for a test
		$curl = new Curl_Fetch_Webdata(array(CURLOPT_RETURNTRANSFER => 1), 1);

		foreach ($this->curl_fetch_testcases as $testcase)
		{
			// Fetch a page
			$curl->get_url_data($testcase[0]);

			// Check for correct results
			if (!empty($testcase[1]))
				$this->assertEquals($testcase[1], $curl->result('code'));
			if (!empty($testcase[2]))
				$this->assertContains($testcase[2], $curl->result('body'));
		}
	}

	/**
	* Test curl with posting
	*/
	public function testPost()
	{
		// Start curl, pass some default values for a test
		$curl = new Curl_Fetch_Webdata();

		foreach ($this->curl_post_testcases as $testcase)
		{
			// Post to a page
			$curl->get_url_data($testcase[0], $testcase[1]);

			// Check for correct fetch
			if (!empty($testcase[2]))
				$this->assertEquals($testcase[2], $curl->result('code'));
			if (!empty($testcase[3]))
				$this->assertContains($testcase[3], $curl->result('body'));
		}
	}
}