<?php

class PrepaseBBC extends \PHPUnit\Framework\TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		require_once(SUBSDIR . '/Post.subs.php');

		$this->bbPreparse_tests = array(
			array(
				'[font=something]text[/font]',
				'[font=something]text[/font]',
			),
			array(
				'[font=something, someother]text[/font]',
				'[font=something]text[/font]',
			),
			array(
				'[font=something, \'someother\']text[/font]',
				'[font=something]text[/font]',
			),
			array(
				'[font=\'something\', someother]text[/font]',
				'[font=something]text[/font]',
			),
			array(
				'something[quote][/quote]',
				'something',
			),
			array(
				'something[code]without a closing tag',
				'something[code]without a closing tag[/code]',
			),
			array(
				'some open list[list][li]one[/list]',
				'some open list[list][li]one[/li][/list]',
			),
			array(
				'some list[code][list][li]one[/list][/code]',
				'some list[code][list][li]one[/list][/code]',
			),
		);
	}

	/**
	 * testPreparseCode, runs preparsecode on the bbcode
	 */
	public function testPreparseCode()
	{
		foreach ($this->bbPreparse_tests as $testcase)
		{
			$test = $testcase[0];
			$expected = $testcase[1];

			preparsecode($test);

			$this->assertEquals($expected, $test);
		}
	}
}