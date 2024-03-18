<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use PHPUnit\Framework\TestCase;

class MaillistPostSubsTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];
	public $bbcTestCases;

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		require_once(SUBSDIR . '/Maillist.subs.php');
		require_once(SUBSDIR . '/MaillistPost.subs.php');

		$this->bbcTestCases = [
			[
				'Test bold',
				'**bold**',
				'[b]bold[/b]',
			],
			[
				'Named links',
				'[ElkArte](http://www.elkarte.net/)',
				'[url=http://www.elkarte.net/]ElkArte[/url]',
			],
			[
				'URL link',
				'[http://www.elkarte.net/](http://www.elkarte.net/)',
				'[url=http://www.elkarte.net/]http://www.elkarte.net/[/url]',
			],
			// This test is here only to remind that the Markdown library doesn't support nested lists
			[
				'Lists',
				'* item
    * sub item
*item',
				'[list][li]item[/li][li]sub item*item[/li][/list]',
			],
		];
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
