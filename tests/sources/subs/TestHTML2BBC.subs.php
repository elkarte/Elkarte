<?php

require_once(TESTDIR . 'simpletest/autorun.php');

// we are not in Elk, thereby need to set our define
if (!defined('ELK'))
	define('ELK', 'SSI');

require_once(TESTDIR . '../sources/subs/Html2BBC.class.php');

class TestHTML2BBC extends UnitTestCase
{
	/**
	 * prepare what is necessary to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
		$this->bbcTestCases = array(
			array(
				'Test bold',
				'<strong class="bbc_strong">bold</strong>',
				'[b]bold[/b]',
			),
			array(
				'Named links',
				'<a href="http://www.elkarte.net/" class="bbc_link" target="_blank">ElkArte</a>',
				'[url=http://www.elkarte.net/]ElkArte[/url]',
			),
			array(
				'URL link',
				'<a href="http://www.elkarte.net/" class="bbc_link" target="_blank">http://www.elkarte.net/</a>',
				'[url=http://www.elkarte.net/]http://www.elkarte.net/[/url]',
			),
			array(
				'Lists',
				'<ul class="bbc_list"><li>item</li><li><ul class="bbc_list"><li>sub item</li></ul></li><li>item</li></ul>',
				'[list][li]item[/li][li][list][li]sub item[/li][/list][br][/li][li]item[/li][/list]',
			),
			array(
				'Tables',
				'<table class="bbc_table"><tr><td><table class="bbc_table"><tr><td>test</td></tr></table></td></tr></table>',
				'[table][tr][td][br][table][tr][td]test[/td][/tr][/table][br][/td][/tr][/table]',
			),
		);
	}

	/**
	 * testHTML2BBcode, parse html to BBC and checks that the results are what we expect
	 */
	public function testHTML2BBcode()
	{
		foreach ($this->bbcTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = $testcase[1];
			$expected = $testcase[2];

			$parser = new Html_2_BBC($test);

			// Convert the html to bbc
			$result = $parser->get_bbc();

			// Remove pretty print newlines
			$result = str_replace("\n", '', $result);

			$this->assertEqual($expected, $result);
		}
	}
}