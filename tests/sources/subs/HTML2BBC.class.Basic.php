<?php

class TestHTML2BBC extends PHPUnit\Framework\TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		require_once(SUBSDIR . '/Html2BBC.class.php');

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
				'[list][li]item[/li][li][list][li]sub item[/li][/list][/li][li]item[/li][/list]',
			),
			array(
				'Tables',
				'<table class="bbc_table"><tr><td><table class="bbc_table"><tr><td>test</td></tr></table></td></tr></table>',
				'[table][tr][td][table][tr][td]test[/td][/tr][/table][/td][/tr][/table]',
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

			$this->assertEquals($expected, $result, $name);
		}
	}
}