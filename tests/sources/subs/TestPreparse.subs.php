<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');

class TestBBC extends UnitTestCase
{
	/**
	 * prepare what is necessary to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
		$this->bbPreparse_tests = array(
			array(
				'[font=something]text[/font]',
				'[font=something]text[/font]',
			),
			array(
				'[font=\'something\']text[/font]',
				'[font=something]text[/font]',
			),
			array(
				'[font=something1, something2]text[/font]',
				'[font=something1]text[/font]',
			),
			array(
				'[font=something1, \'something2\']text[/font]',
				'[font=something1]text[/font]',
			),
			array(
				'[font=\'something1\', something2]text[/font]',
				'[font=something1]text[/font]',
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

			$this->assertEqual($expected, $test);
		}
	}
}