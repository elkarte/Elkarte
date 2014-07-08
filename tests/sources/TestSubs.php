<?php
require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');

/**
 * TestCase class for (ideally) all the functions in the Subs.php file
 * that do not fit in any other test
 */
class TestSubss extends UnitTestCase
{
	/**
	 * prepare what is necessary to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	function tearDown()
	{
	}

	/**
	 * Tests the response of the response_prefix function
	 */
	function testResponsePrefix()
	{
		global $txt;

		$this->assertEqual(response_prefix(), $txt['response_prefix']);
	}

	/**
	 * Tests the response of the response_prefix function
	 */
	function testReplaceBasicActionUrl()
	{
		global $scripturl, $context, $boardurl;

		$testStrings = array(
			'{forum_name}' => $context['forum_name'],
			'{forum_name_html_safe}' => $context['forum_name_html_safe'],
			'{script_url}' => $scripturl,
			'{board_url}' => $boardurl,
			'{login_url}' => $scripturl . '?action=login',
			'{register_url}' => $scripturl . '?action=register',
			'{activate_url}' => $scripturl . '?action=activate',
			'{help_url}' => $scripturl . '?action=help',
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
			$this->assertEqual(replaceBasicActionUrl($string), $value);
	}
}