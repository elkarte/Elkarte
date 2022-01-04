<?php

use ElkArte\Html2Md;
use PHPUnit\Framework\TestCase;

class TestHTML2Md extends TestCase
{
	protected $mdTestCases = array();
	protected $restore_txt = false;

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		global $txt;

		if (!isset($txt['link']))
		{
			$txt['link'] = 'Link';
			$this->restore_txt = true;
		}

		$this->mdTestCases = array(
			array(
				'Test bold',
				'<strong class="bbc_strong">bold</strong>',
				'**bold**',
			),
			array(
				'Named links',
				'<a href="https://www.elkarte.net/" class="bbc_link" target="_blank">ElkArte</a>',
				'[ElkArte]( https://www.elkarte.net/ )',
			),
			array(
				'URL link',
				'<a href="https://www.elkarte.net/" class="bbc_link" target="_blank">https://www.elkarte.net/</a>',
				'[Link]( https://www.elkarte.net/ )',
			),
			array(
				'Lists',
				'<ul class="bbc_list"><li>item</li><li><ul class="bbc_list"><li>sub item</li></ul></li><li>item</li></ul>',
				"* item\n* 	* sub item\n* item",
			),
			array(
				'Table',
				'<h3>Simple table</h3><table><thead><tr><th>Header 1</th><th>Header 2</th></tr></thead><tbody><tr><td>Cell 1</td><td>Cell 2</td></tr><tr><td>Cell 3</td><td>Cell 4</td></tr></tbody></table>',
				"### Simple table\n\n| Header 1 | Header 2 |\n| -------- | -------- | \n| Cell 1   | Cell 2   |\n| Cell 3   | Cell 4   |",
			),
			array(
				'Quotes',
				'<blockquote><br /><p>Example:</p><pre><code>sub status {<br />    print "working";<br />}<br /></code></pre><p>Or:</p><pre><code>sub status {<br />    return "working";<br />}<br /></code></pre></blockquote>',
				">   \n> \n> Example:\n> \n>     sub status {\n>         print \"working\";\n>     }\n> \n> \n> Or:\n> \n>     sub status {\n>         return \"working\";\n>     }"
			),
			array(
				'Combo',
				'<ol><li><strong><em>test test</em></strong></li></ol>',
				'1. **_test test_**'
			),
		);
	}

	protected function tearDown(): void
	{
		global $txt;

		if ($this->restore_txt)
			unset($txt['link']);
	}

	/**
	 * testToMarkdown, parse html to MD text and checks that the results are what we expect
	 */
	public function testToMarkdown()
	{
		foreach ($this->mdTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = $testcase[1];
			$expected = $testcase[2];

			$parser = new Html2Md($test);

			// Convert the html to bbc
			$result = trim($parser->get_markdown(), "\x00");

			// See if its the result we expect
			$this->assertEquals($expected . "\n", $result, 'Error:: ' . $result);
		}
	}
}
