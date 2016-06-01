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
				'Test anchor',
				'[anchor=#abc]destination[/anchor]',
				'<span id="post_#abc">destination</span>',
			),
			array(
				'Test bold',
				'[b]bold[/b]',
				'<strong class="bbc_strong">bold</strong>',
			),
			array(
				'Test br',
				'First line[br]Second line',
				'First line<br />Second line',
			),
			array(
				'Test center',
				'[center]text[/center]',
				'<div class="centertext">text</div>',
			),
			array(
				'Unparsed code',
				'[code]This is some code[/code]',
				'<div class="codeheader">Code: <a href="#" onclick="return elkSelectText(this);" class="codeoperation">[Select]</a></div><pre class="bbc_code prettyprint">This is some code</pre>',
			),
			array(
				'Unparsed equals code',
				'[code=unparsed text]This is some code[/code]',
				'<div class="codeheader">Code: (unparsed text) <a href="#" onclick="return elkSelectText(this);" class="codeoperation">[Select]</a></div><pre class="bbc_code prettyprint">This is some code</pre>',
			),
			array(
				'Coloring 1',
				'[color=#000]text[/color]',
				'<span style="color: #000;" class="bbc_color">text</span>',
			),
			array(
				'Coloring 2',
				'[color=#abcdef]text[/color]',
				'<span style="color: #abcdef;" class="bbc_color">text</span>',
			),
			array(
				'Coloring 3',
				'[color=red]text[/color]',
				'<span style="color: red;" class="bbc_color">text</span>',
			),
			array(
				'Coloring 4',
				'[color=somerubbish]text[/color]',
				'<span style="color: somerubbish;" class="bbc_color">text</span>',
			),
			array(
				'Coloring 5',
				'[color=rgb(255,0,130)]text[/color]',
				'<span style="color: rgb(255,0,130);" class="bbc_color">text</span>',
			),
			array(
				'email linking',
				'[email]anything[/email]',
				'<a href="mailto:anything" class="bbc_email">anything</a>',
			),
			array(
				'email linking 2',
				'[email=anything]some text[/email]',
				'<a href="mailto:anything" class="bbc_email">some text</a>',
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
				'HR',
				'Some[hr]text',
				'Some<hr />text',
			),
			array(
				'Test italic',
				'[i]Italic[/i]',
				'<em>Italic</em>',
			),
			array(
				'Test img 1',
				'[img]http://adomain.tld/an_image.png[/img]',
				'<img src="http://adomain.tld/an_image.png" alt="" class="bbc_img" />',
			),
			array(
				'Test img 2',
				'[img]adomain.tld/an_image.png[/img]',
				'<img src="http://adomain.tld/an_image.png" alt="" class="bbc_img" />',
			),
			array(
				'Test img 3',
				'[img width=100]http://adomain.tld/an_image.png[/img]',
				'<img src="http://adomain.tld/an_image.png" alt="" style="width:100%;max-width:100px;" class="bbc_img resized" />',
			),
			array(
				'Test img 4',
				'[img height=100]http://adomain.tld/an_image.png[/img]',
				'<img src="http://adomain.tld/an_image.png" alt="" style="max-height:100px;" class="bbc_img resized" />',
			),
			array(
				'Test img 5',
				'[img height=100 width=150]http://adomain.tld/an_image.png[/img]',
				'<img src="http://adomain.tld/an_image.png" alt="" style="width:100%;max-width:150px;max-height:100px;" class="bbc_img resized" />',
			),
			array(
				'Test img 6',
				'[img alt=some text width=150 height=100]http://adomain.tld/an_image.png[/img]',
				'<img src="http://adomain.tld/an_image.png" alt="some text" style="width:100%;max-width:150px;max-height:100px;" class="bbc_img resized" />',
			),
			array(
				'Test img 7',
				'[img width=150 height=100 alt=some text]http://adomain.tld/an_image.png[/img]',
				'<img src="http://adomain.tld/an_image.png" alt="some text" style="width:100%;max-width:150px;max-height:100px;" class="bbc_img resized" />',
			),
			array(
				'Unnamed iurl links',
				'[iurl=http://www.elkarte.net/]ElkArte[/iurl]',
				'<a href="http://www.elkarte.net/" class="bbc_link">ElkArte</a>',
			),
			array(
				'Named iurl links',
				'[iurl=http://www.elkarte.net/]ElkArte[/iurl]',
				'<a href="http://www.elkarte.net/" class="bbc_link">ElkArte</a>',
			),
			array(
				'Left tag',
				'[left]ElkArte[/left]',
				'<div style="text-align: left;">ElkArte</div>',
			),
			array(
				'Lists',
				'[list][li]item[/li][li][list][li]sub item[/li][/list][/li][li]item[/li][/list]',
				'<ul class="bbc_list"><li>item</li><li><ul class="bbc_list"><li>sub item</li></ul></li><li>item</li></ul>',
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
				'/me',
				'[me=member name]text[/me]',
				'<div class="meaction">&nbsp;member name text</div>',
			),
			array(
				'Member',
				'[member=10]Name[/member]',
				'<span class="bbc_mention"><a href="http://127.0.0.1/index.php?action=profile;u=10">@Name</a></span>',
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
				'Tables',
				'[table][tr][td][table][tr][td]test[/td][/tr][/table][/td][/tr][/table]',
				'<div class="bbc_table_container"><table class="bbc_table"><tr><td><div class="bbc_table_container"><table class="bbc_table"><tr><td>test</td></tr></table></div></td></tr></table></div>',
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
				'Coloring 1',
				'[color=#1]text[/color]',
			),
			array(
				'Coloring 2',
				'[color=#12]text[/color]',
			),
			array(
				'Coloring 3',
				'[color=#1234]text[/color]',
			),
			array(
				'Coloring 4',
				'[color=#12345]text[/color]',
			),
			array(
				'Coloring 5',
				'[color=rgb(600,600,600)]text[/color]',
			),
			array(
				'Coloring 6',
				'[color=rgb(600,5,5)]text[/color]',
			),
			array(
				'Coloring 7',
				'[color=rgb(5,5,5,5)]text[/color]',
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