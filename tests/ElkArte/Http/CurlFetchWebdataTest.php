<?php

/**
 * TestCase class for \ElkArte\Http\CurlFetchWebdata
 */

namespace ElkArte\Http;

use PHPUnit\Framework\TestCase;

class CurlFetchWebdataTest extends TestCase
{
	protected $curl_fetch_testcases = [];
	protected $curl_post_testcases = [];
	protected $backupGlobalsExcludeList = ['user_info'];

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
		$this->curl_post_testcases = array(
			//array(
			//	'https://www.google.com',
			//	array('gs_taif0' => 'elkarte'),
			//	404,
			//	'all we know',
			//),
			array(
				'https://duckduckgo.com/html',
				array('q' => 'stargate+sg1 site:www.imdb.com', 'b' => ''),
				[0, 200, 202, 403],
				'TV Series',
			),
		);

		// url
		// expected return code
		// expected in output
		$this->curl_fetch_testcases = array(
			array(
				'https://developer.mozilla.org/en-US/',
				200,
				'MDN Web Docs',
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
	protected function tearDown(): void
	{
	}

	/**
	 * Test curl fetching
	 */
	public function testCurlFetch()
	{
		// Start curl, pass some default values for a test
		$curl = new CurlFetchWebdata(array(CURLOPT_RETURNTRANSFER => 1), 1);

		foreach ($this->curl_fetch_testcases as $testcase)
		{
			// Fetch a page
			$curl->get_url_data($testcase[0]);

			// Check for correct results
			if (!empty($testcase[1]))
			{
				$this->assertEquals($testcase[1], (int) $curl->result('code'), 'FetchCodeError:: ' . $curl->result('code'));
			}
			if (!empty($testcase[2]))
			{
				$this->assertStringContainsString($testcase[2], $curl->result('body'));
			}
		}
	}

	/**
	 * Test curl with posting
	 */
	public function testCurlPost()
	{
		// Start curl, pass some default values for a test
		$curl = new CurlFetchWebdata();

		foreach ($this->curl_post_testcases as $testcase)
		{
			// Post to a page
			$curl->get_url_data($testcase[0], $testcase[1]);

			// Check for correct fetch
			if (!empty($testcase[2]))
			{
				$this->assertContains((int) $curl->result('code'), $testcase[2], 'FetchCodeError:: ' . $curl->result('code'));
			}

			if (!empty($testcase[3]) && (int) $curl->result('code') === 200)
			{
				$this->assertStringContainsString($testcase[3], $curl->result('body'));
			}
		}
	}
}
