<?php

class TestBBC extends PHPUnit_Framework_TestCase
{
	protected $bbcTestCases;
	protected $bbcInvalidTestCases;
	protected $bbcPreparseTestCases;

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		global $modSettings;
		$modSettings['user_access_mentions'] = array();

		loadTheme();

		// Standard testcases
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
				'Footnote 2',
				'footnote[footnote]footnote :)[/footnote]something',
				'footnote<sup class="bbc_footnotes"><a class="target" href="#fn1_1" id="ref1_1">[1]</a></sup>something<div class="bbc_footnotes"><div class="target" id="fn1_1"><sup>1&nbsp;</sup>footnote <img src="http://127.0.0.1/smileys/default/smiley.gif" alt="&#58;&#41;" title="Smiley" class="smiley" /><a class="footnote_return" href="#ref1_1">&crarr;</a></div></div>',
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
				'nobbc',
				'[nobbc][code]this is a code-block in a nobbc[/code][/nobbc]',
				'[code]this is a code-block in a nobbc[/code]',
			),
			array(
				'pre',
				'[pre]this is a pre-block[/pre]',
				'<pre class="bbc_pre">this is a pre-block</pre>',
			),
			array(
				'Quoting is a pain 1',
				'[quote]This is a quote[/quote]',
				'<div class="quoteheader">Quote</div><blockquote class="bbc_quote">This is a quote</blockquote>',
			),
			array(
				'Quoting is a pain 1 bis',
				'[quote]This is a quote[quote]of a quote[/quote][/quote]',
				'<div class="quoteheader">Quote</div><blockquote class="bbc_quote">This is a quote<div class="quoteheader bbc_alt_quoteheader">Quote</div><blockquote class="bbc_quote bbc_alternate_quote">of a quote</blockquote></blockquote>',
			),
			array(
				'Quoting is a pain 2',
				'[quote author=unquoted author]This is a quote[/quote]',
				'<div class="quoteheader">Quote from: unquoted author</div><blockquote class="bbc_quote">This is a quote</blockquote>',
			),
			array(
				'Quoting is a pain 3',
				'[quote author=&quot;quoted author&quot;]This is a quote[/quote]',
				'<div class="quoteheader">Quote from: quoted author</div><blockquote class="bbc_quote">This is a quote</blockquote>',
			),
			array(
				'Quoting is a pain 4',
				'[quote author=q]This is a quote[/quote]',
				'<div class="quoteheader">Quote from: q</div><blockquote class="bbc_quote">This is a quote</blockquote>',
			),
			array(
				'Quoting is a pain 5',
				'[quote author=qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuiop]This is a quote[/quote]',
				'<div class="quoteheader">Quote from: qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuiop</div><blockquote class="bbc_quote">This is a quote</blockquote>',
			),
			array(
				'Quoting is a pain 6',
				'[quote=something]This is a quote[/quote]',
				'<div class="quoteheader">Quote from: something</div><blockquote class="bbc_quote">This is a quote</blockquote>',
			),
			array(
				'Quoting is a pain 7',
				'[quote author=an author link=board=1;topic=123 date=12345678]This is a quote[/quote]',
				'<div class="quoteheader"><a href="http://127.0.0.1/index.php?topic=123">Quote from: an author on ' . htmlTime(12345678) . '</a></div><blockquote class="bbc_quote">This is a quote</blockquote>',
			),
			array(
				'Quoting is a pain 8',
				'[quote author=an author link=topic=123.msg123#msg123 date=12345678]This is a quote[/quote]',
				'<div class="quoteheader"><a href="http://127.0.0.1/index.php?topic=123.msg123#msg123">Quote from: an author on ' . htmlTime(12345678) . '</a></div><blockquote class="bbc_quote">This is a quote</blockquote>',
			),
			array(
				'Quoting is a pain 9',
				'[quote author=an author link=threadid=123.msg123#msg123 date=12345678]This is a quote[/quote]',
				'<div class="quoteheader"><a href="http://127.0.0.1/index.php?threadid=123.msg123#msg123">Quote from: an author on ' . htmlTime(12345678) . '</a></div><blockquote class="bbc_quote">This is a quote</blockquote>',
			),
			array(
				'Quoting is a pain 10',
				'[quote author=an author link=action=profile;u=123 date=12345678]This is a quote[/quote]',
				'<div class="quoteheader"><a href="http://127.0.0.1/index.php?action=profile;u=123">Quote from: an author on ' . htmlTime(12345678) . '</a></div><blockquote class="bbc_quote">This is a quote</blockquote>',
			),
			array(
				'Right tag',
				'[right]ElkArte[/right]',
				'<div style="text-align: right;">ElkArte</div>',
			),
			array(
				'Strike',
				'[s]ElkArte[/s]',
				'<del>ElkArte</del>',
			),
			array(
				'Sizes 1',
				'[size=1]ElkArte[/size]',
				'<span style="font-size: 0.7em;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 2',
				'[size=7]ElkArte[/size]',
				'<span style="font-size: 3.95em;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 3',
				'[size=7px]ElkArte[/size]',
				'<span style="font-size: 7px;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 4',
				'[size=71px]ElkArte[/size]',
				'<span style="font-size: 71px;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 3',
				'[size=7pt]ElkArte[/size]',
				'<span style="font-size: 7pt;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 4',
				'[size=71pt]ElkArte[/size]',
				'<span style="font-size: 71pt;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 5',
				'[size=small]ElkArte[/size]',
				'<span style="font-size: small;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 6',
				'[size=smaller]ElkArte[/size]',
				'<span style="font-size: smaller;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 7',
				'[size=large]ElkArte[/size]',
				'<span style="font-size: large;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 8',
				'[size=larger]ElkArte[/size]',
				'<span style="font-size: larger;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 9',
				'[size=x-small]ElkArte[/size]',
				'<span style="font-size: x-small;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 10',
				'[size=xx-small]ElkArte[/size]',
				'<span style="font-size: xx-small;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 11',
				'[size=x-large]ElkArte[/size]',
				'<span style="font-size: x-large;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 12',
				'[size=xx-large]ElkArte[/size]',
				'<span style="font-size: xx-large;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 13',
				'[size=medium]ElkArte[/size]',
				'<span style="font-size: medium;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 13',
				'[size=0.1em]ElkArte[/size]',
				'<span style="font-size: 0.1em;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Sizes 13',
				'[size=9.11em]ElkArte[/size]',
				'<span style="font-size: 9.11em;" class="bbc_size">ElkArte</span>',
			),
			array(
				'Shhhh spoiler!',
				'[spoiler]ElkArte[/spoiler]',
				'<span class="spoilerheader">Spoiler (click to show/hide)</span><div class="spoiler"><div class="bbc_spoiler" style="display: none;">ElkArte</div></div>',
			),
			array(
				'Sub',
				'[sub]ElkArte[/sub]',
				'<sub>ElkArte</sub>',
			),
			array(
				'Sup',
				'[sup]ElkArte[/sup]',
				'<sup>ElkArte</sup>',
			),
			array(
				'Tables',
				'[table][tr][td][table][tr][td]test[/td][/tr][/table][/td][/tr][/table]',
				'<div class="bbc_table_container"><table class="bbc_table"><tr><td><div class="bbc_table_container"><table class="bbc_table"><tr><td>test</td></tr></table></div></td></tr></table></div>',
			),
			array(
				'tt',
				'[tt]ElkArte[/tt]',
				'<span class="bbc_tt">ElkArte</span>',
			),
			array(
				'Underline',
				'[u]ElkArte[/u]',
				'<span class="bbc_u">ElkArte</span>',
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
			array(
				'Sizes 1',
				'[size=8]ElkArte[/size]',
			),
			array(
				'Sizes 2',
				'[size=711px]ElkArte[/size]',
			),
			array(
				'Sizes 3',
				'[size=711pt]ElkArte[/size]',
			),
			array(
				'Sizes 4',
				'[size=anything]ElkArte[/size]',
			),
			array(
				'Sizes 5',
				'[size=91.11em]ElkArte[/size]',
			),
			array(
				'Quoting is a pain 1',
				'[quote author=qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuiopl]This is a quote[/quote]',
			),
			// This form currently parses using "]This is a quote" as author. It's not correct but that is.
			// 			array(
			// 				'Quoting is a pain 2',
			// 				'[quote author=]This is a quote[/quote]',
			// 			),
		);

		// Others that preparse should fix or damage
		$this->bbcPreparseTestCases = array(
			array(
				'Table1',
				'[table][tr][td]let me see[/td][td][table][tr][td]if[/td][td]I[/td][/tr][tr][td]can[/td][td]break[/td][/tr][tr][td]the[/td][td]internet[/td][/td][/tr][/table]',
				'<div class="bbc_table_container"><table class="bbc_table"><tr><td>let me see</td><td><div class="bbc_table_container"><table class="bbc_table"><tr><td>if</td><td>I</td></tr><tr><td>can</td><td>break</td></tr><tr><td>the</td><td>internet</td></tr></table></div></td></tr></table></div>'
			),
			array(
				'Item Codes',
				'[*]Ahoy!\n[*]Me[@]Matey\n[+]Shiver\n[x]Me\n[#]Timbers\n[!]\n[*]I[*]dunno[*]why',
				'<ul style="list-style-type: disc" class="bbc_list"><li>Ahoy!\n</li><li>Me</li><li>Matey\n</li><li>Shiver\n</li><li>Me\n</li><li>Timbers\n[!]\n</li><li>I</li><li>dunno</li><li>why</li></ul>'
			),
			array(
				'UTF8',
				'[url]www.ñchan.org[/url]',
				'<a href="http://www.ñchan.org" class="bbc_link" target="_blank">www.ñchan.org</a>'
			),
			array(
				'ListCode1',
				'[list][li]Test[/li][li]More [code]Some COde[/code][/li][/list]',
				'<ul class="bbc_list"><li>Test</li><li>More <div class="codeheader">Code: <a href="#" onclick="return elkSelectText(this);" class="codeoperation">[Select]</a></div><pre class="bbc_code prettyprint">Some COde</pre></li></ul>'
			),
			array(
				'ListCode2',
				'some list[code][list][li]one[/list][/code]',
				'some list<div class="codeheader">Code: <a href="#" onclick="return elkSelectText(this);" class="codeoperation">[Select]</a></div><pre class="bbc_code prettyprint">[list][li]one[/list]</pre>'
			),
			array(
				'emptyQuote',
				'something[quote][/quote]',
				'something'
			),
			array(
				'openCode',
				'something[code]without a closing tag',
				'something<div class="codeheader">Code: <a href="#" onclick="return elkSelectText(this);" class="codeoperation">[Select]</a></div><pre class="bbc_code prettyprint">without a closing tag</pre>'
			),
			array(
				'openList',
				'some open list[list][li]one[/list]',
				'some open list<ul class="bbc_list"><li>one</li></ul>'
			),
			array(
				'manyFonts',
				'[font=something, someother]text[/font]',
				'<span style="font-family: something;" class="bbc_font">text</span>'
			),
			array(
				'itsMe',
				'/me likes this',
				'<div class="meaction">&nbsp; likes this</div>'
			),
			array(
				'schemelessUrl',
				'[url=//www.google.com]Google[/url]',
				'<a href="http://www.google.com" class="bbc_link" target="_blank">Google</a>'
			)
		);
	}

	/**
	 * testBBcode, parse bbcode and checks that the results are what we expect
	 */
	public function testBBcode()
	{
		$parsers = \BBC\ParserWrapper::getInstance();
		foreach ($this->bbcTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = $testcase[1];
			$expected = $testcase[2];

			$result = $parsers->parseMessage($test);

			$this->assertEquals($expected, $result, $name);
		}
	}

	/**
	 * testBBcode, parse bbcode and checks that the results are what we expect
	 */
	public function testInvalidBBcode()
	{
		$parsers = \BBC\ParserWrapper::getInstance();
		foreach ($this->bbcInvalidTestCases as $testcase)
		{
			$name = 'Broken ' . $testcase[0];
			$test = $testcase[1];

			$result = $parsers->parseMessage($test);

			$this->assertEquals($test, $result, $name);
		}
	}

	/**
	 * testPreparseBBcode, preparse bbcode and check that the results are what we expect
	 */
	public function testPreparseBBcode()
	{
		$preparse = new \BBC\PreparseCode;
		$parse = \BBC\ParserWrapper::getInstance();

		foreach ($this->bbcPreparseTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = $testcase[1];
			$expected = $testcase[2];

			$preparse->preparsecode($test, false);
			$result = $parse->parseMessage($test, true);

			$this->assertEquals($expected, $result, $name);
		}
	}
}