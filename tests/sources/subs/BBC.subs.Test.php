<?php

class TestBBC extends PHPUnit_Framework_TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		loadTheme();

		$this->bbcTestCases = array(
			array(
				'Test abbreviation',
				'[abbr=so have obtained random text]short[/abbr]',
				'<abbr title="so have obtained random text">short</abbr>',
			),
			array(
				'Test abbreviation',
				'[abbr=so have obtained random &quot;quoted&quot; text]shor"q"t[/abbr]',
				'<abbr title="so have obtained random &quot;quoted&quot; text">shor"q"t</abbr>',
			),
			array(
				'Test anchor',
				'[anchor=abc]destination[/anchor]',
				'<span id="post_abc">destination</span>',
			),
			array(
				'Test bold',
				'[b]bold[/b]',
				'<strong class="bbc_strong">bold</strong>',
			),
			array(
				'Test br',
				'[br]',
				'<br />',
			),
			array(
				'Named links',
				'[url=http://www.elkarte.net/]ElkArte[/url]',
				'<a href="http://www.elkarte.net/" class="bbc_link" target="_blank">ElkArte</a>',
			),
			array(
				'URL link',
				'http://www.elkarte.net/',
				'<a href="http://www.elkarte.net/" class="bbc_link" target="_blank">http://www.elkarte.net/</a>',
			),
			array(
				'Lists',
				'[list][li]item[/li][li][list][li]sub item[/li][/list][/li][li]item[/li][/list]',
				'<ul class="bbc_list"><li>item</li><li><ul class="bbc_list"><li>sub item</li></ul></li><li>item</li></ul>',
			),
			array(
				'Tables',
				'[table][tr][td][table][tr][td]test[/td][/tr][/table][/td][/tr][/table]',
				'<div class="bbc_table_container"><table class="bbc_table"><tr><td><div class="bbc_table_container"><table class="bbc_table"><tr><td>test</td></tr></table></div></td></tr></table></div>',
			),
			array(
				'Normal list',
				'[list][li]test[/li][/list]',
				'<ul class="bbc_list"><li>test</li></ul>',
			),
			array(
				'Decimal list',
				'[list type=decimal][li]test[/li][/list]',
				'<ul class="bbc_list" style="list-style-type: decimal;"><li>test</li></ul>',
			),
			array(
				'Footnote',
				'footnote[footnote]footnote[/footnote]',
				'footnote<sup class="bbc_footnotes"><a class="target" href="#fn1_0" id="ref1_0">[1]</a></sup><div class="bbc_footnotes"><div class="target" id="fn1_0"><sup>1&nbsp;</sup>footnote<a class="footnote_return" href="#ref1_0">&crarr;</a></div></div>',
			),
			array(
				'Font parsed',
				'[font=whatever]test[/font]',
				'<span style="font-family: whatever;" class="bbc_font">test</span>',
			),
			array(
				'Member',
				'[member=10]Name[/member]',
				'<span class="bbc_mention"><a href="http://127.0.0.1/index.php?action=profile;u=10">@Name</a></span>',
			),
		);

		// These are bbc that will not be converted to an html tag
		// Separated for convenience
		$this->bbcInvalidTestCases = array(
			array(
				'Test anchor',
				'[anchor=ab"c]destination[/anchor]',
			),
			array(
				'Test bdo',
				'[bdo=something]something[/bdo]',
			),
			array(
				'Test font',
				'[font=wha"t"ever]test[/font]',
			),
		);
	}

	/**
	 * testBBcode, parse bbcode and checks that the results are what we expect
	 */
	public function testBBcode()
	{
		foreach ($this->bbcTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = $testcase[1];
			$expected = $testcase[2];

			$result = parse_bbc($test);

			$this->assertEquals($expected, $result);
		}

		foreach ($this->bbcInvalidTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = $testcase[1];

			$result = parse_bbc($test);

			$this->assertEquals($test, $result);
		}
	}
}