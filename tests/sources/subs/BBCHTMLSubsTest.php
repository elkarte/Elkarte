<?php

use BBC\HtmlParser;
use tests\ElkArteCommonSetupTest;

class BBCHTMLSubsTest extends ElkArteCommonSetupTest
{
	protected $bbcTestCases;
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$GLOBALS['modSettings']['user_access_mentions'] = [];
		$GLOBALS['modSettings']['enablePostHTML'] = 1;

		new ElkArte\Themes\ThemeLoader();

		// Standard testcases
		$this->bbcTestCases = array(
			array(
				'Test image',
				'<img src="http://www.elkarte.com/pic_mountain.jpg" alt="Mountain View" />',
				'[img alt=Mountain View]http://www.elkarte.com/pic_mountain.jpg[/img]',
			),
			array(
				'Test bold',
				'<b>bold</b>',
				'<b>bold</b>',
			),
			array(
				'Test hr',
				'<hr>',
				'[hr /]',
			),
			array(
				'Test anchor',
				'<a href="http://www.elkarte.net">Elkarte</a>',
				'[url=http://www.elkarte.net]Elkarte[/url]',
			),
		);
	}

	/**
	 * testBBHTM code, parse html bbcode and checks that the results are what we expect
	 */
	public function testBBHTML()
	{
		$parser = new HtmlParser;

		foreach ($this->bbcTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = htmlspecialchars($testcase[1]);
			$expected = $testcase[2];

			$result = $parser->parse($test);

			$this->assertEquals($expected, $result, $name);
		}
	}
}
