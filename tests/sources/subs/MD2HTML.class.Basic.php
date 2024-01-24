<?php

use PHPUnit\Framework\TestCase;

class TestMD2HTML extends TestCase
{
	protected $mdTestCases = array();
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		require_once(EXTDIR . '/markdown/markdown.php');

		$this->mdTestCases = array(
			array(
				'Test bold',
				'**bold**',
				"<p><strong>bold</strong></p>",
			),
			array(
				'Named links',
				'[ElkArte](http://www.elkarte.net/)',
				'<p><a href="http://www.elkarte.net/">ElkArte</a></p>',
			),
			array(
				'Table',
				"### Simple table\n\n| Header 1 | Header 2 |\n| -------- | -------- | \n| Cell 1   | Cell 2   |\n| Cell 3   | Cell 4   |",
				"<h3>Simple table</h3>\n\n<table>\n<thead>\n<tr>\n  <th>Header 1</th>\n  <th>Header 2</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n  <td>Cell 1</td>\n  <td>Cell 2</td>\n</tr>\n<tr>\n  <td>Cell 3</td>\n  <td>Cell 4</td>\n</tr>\n</tbody>\n</table>",
			),
			array(
				'Lists',
				"* item\n* 	* sub item\n* item",
				"<ul>\n<li>item</li>\n<li><ul>\n<li>sub item</li>\n</ul></li>\n<li>item</li>\n</ul>",
			),
			array(
				'Quotes',
				">   Example:",
				"<blockquote>\n  <p>Example:</p>\n</blockquote>",
			),
			array(
				'Combo',
				'1. **_test test_**',
				"<ol>\n<li><strong><em>test test</em></strong></li>\n</ol>",
			),
		);
	}

	protected function tearDown(): void
	{

	}

	/**
	 * testToHTML, parse html to MD text and checks that the results are what we expect
	 */
	public function testToHTML()
	{
		foreach ($this->mdTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = $testcase[1];
			$expected = $testcase[2];

			// Convert HTML to MD
			$result = trim(Markdown($test));

			// See if the result is what we expect
			$this->assertEquals($expected, $result, $name);
		}
	}
}
