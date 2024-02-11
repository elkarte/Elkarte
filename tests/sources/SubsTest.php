<?php

use PHPUnit\Framework\TestCase;

/**
 * TestCase class for (ideally) all the functions in the Subs.php file
 * that do not fit in any other test
 */
class SubsTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
	}

	/**
	 * Tests the response of the response_prefix function
	 */
	public function testResponsePrefix()
	{
		global $txt;

		$this->assertEquals(response_prefix(), $txt['response_prefix']);
	}

	/**
	 * Tests the response of the response_prefix function
	 */
	public function testReplaceBasicActionUrl()
	{
		global $scripturl, $context, $boardurl;

		$testStrings = array(
			'{forum_name}' => $context['forum_name'],
			'{forum_name_html_safe}' => $context['forum_name'],
			'{script_url}' => $scripturl,
			'{board_url}' => $boardurl,
			'{login_url}' => $scripturl . '?action=login',
			'{register_url}' => $scripturl . '?action=register',
			'{activate_url}' => $scripturl . '?action=register;sa=activate',
			'{admin_url}' => $scripturl . '?action=admin',
			'{moderate_url}' => $scripturl . '?action=moderate',
			'{recent_url}' => $scripturl . '?action=recent',
			'{search_url}' => $scripturl . '?action=search',
			'{who_url}' => $scripturl . '?action=who',
			'{credits_url}' => $scripturl . '?action=who;sa=credits',
			'{calendar_url}' => $scripturl . '?action=calendar',
			'{memberlist_url}' => $scripturl . '?action=memberlist',
			'{stats_url}' => $scripturl . '?action=stats',
		);

		foreach ($testStrings as $string => $value)
			$this->assertEquals(replaceBasicActionUrl($string), $value);
	}

	public function testValidEmailsTLD()
	{
		$testemails = array(
			// Shortest TLD
			'simple.email@domain.it',
			'simple.email@domain.tld',
			'simple.email@domain.stupid',
			// This is the longest TLD currently available at http://data.iana.org/TLD/tlds-alpha-by-domain.txt
			'simple.email@domain.cancerresearch',
			// These are longer than the maximum currently known
			'simple.email@domain.cancerresearch1',
			'simple.email@domain.cancerresearch12',
			'simple.email@domain.cancerresearch123',
		);
		foreach ($testemails as $email)
			$this->assertTrue(isValidEmail($email) !== false);
	}

	public function testRemoveNestedQuotes()
	{
		global $modSettings;

		$testquote = '[quote]1[quote]2[quote]3[/quote][/quote][/quote]';

		$modSettings['removeNestedQuotes'] = 0;
		$test = removeNestedQuotes($testquote);
		$this->assertEmpty($test, 'Expected empty but found' . $test);

		$modSettings['removeNestedQuotes'] = 1;
		$test = removeNestedQuotes($testquote);
		$this->assertEquals('[quote]1[/quote]', $test, 'Expected one quote but found'  . $test);
	}
}
