<?php

use PHPUnit\Framework\TestCase;

class TestEmailpost extends TestCase
{
	protected $backupGlobalsBlacklist = ['user_info'];
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		require_once(SUBSDIR . '/Emailpost.subs.php');

		$this->bbcTestCases = array(
			array(
				'Test bold',
				'**bold**',
				'[b]bold[/b]',
			),
			array(
				'Named links',
				'[ElkArte](http://www.elkarte.net/)',
				'[url=http://www.elkarte.net/]ElkArte[/url]',
			),
			array(
				'URL link',
				'[http://www.elkarte.net/](http://www.elkarte.net/)',
				'[url=http://www.elkarte.net/]http://www.elkarte.net/[/url]',
			),
			// This test is here only to remind that the Markdown library doesn't support nested lists
			array(
				'Lists',
				'* item
    * sub item
*item',
				'[list][li]item[/li][li]sub item*item[/li][/list]',
			),
		);
	}

	/**
	 * testHTML2BBcode, parse html to BBC and checks that the results are what we expect
	 */
	public function testpbe_email_to_bbc()
	{
		foreach ($this->bbcTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = $testcase[1];
			$expected = $testcase[2];

			// Convert the html to bbc
			$result = pbe_email_to_bbc($test, false);

			// Remove pretty print newlines
			$result = str_replace("\n", '', $result);

			$this->assertEquals($expected, $result);
		}
	}
}
