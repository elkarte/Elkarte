<?php

class TestBBCHTML extends PHPUnit_Framework_TestCase
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
		$modSettings['enablePostHTML'] = true;

		loadTheme();
		// Standard testcases
		$this->bbcTestCases = array(
			array(
				'Test bold',
				'<b>bold</b>',
				'[b]bold[/b]',
			),
			array(
				'Test underline',
				'<u>underline</u>',
				'[u]underline[/u]',
			),
			array(
				'Test italic',
				'<i>italic</i>',
				'[i]italic[/i]',
			),
			array(
				'Test image',
				'<img src="http://www,elkarte.com/pic_mountain.jpg" alt="Mountain View" style="width:304px;height:228px;">',
				'[img url=http://www,elkarte.com/pic_mountain.jpg width=304 height=228]]Mountain View[/img]',
			),
		);
	}

	/**
	 * testBBcode, parse bbcode and checks that the results are what we expect
	 */
	public function testBBHTMLcode()
	{
		foreach ($this->bbcTestCases as $testcase)
		{
			$name = $testcase[0];
			$test = htmlspecialchars($testcase[1]);
			$expected = $testcase[2];

			$result = parse_bbc($test);

			$this->assertEquals($expected, $result, $name);
		}
	}
}