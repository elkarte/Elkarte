<?php

use ElkArte\User;
use ElkArte\ValuesContainer;
use PHPUnit\Framework\TestCase;

class PrepaseBBC extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];
	public $bbPreparse_tests;

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		require_once(SUBSDIR . '/Post.subs.php');

		User::$info = new ValuesContainer([
			'name' => 'istme',
		]);

		$this->bbPreparse_tests = [
			[
				'[font=something]text[/font]',
				'[font=something]text[/font]',
			],
			[
				'[font=something, someother]text[/font]',
				'[font=something]text[/font]',
			],
			[
				'[font=something, \'someother\']text[/font]',
				'[font=something]text[/font]',
			],
			[
				'[font=\'something\', someother]text[/font]',
				'[font=something]text[/font]',
			],
			[
				'something[quote][/quote]',
				'something',
			],
			[
				'something[code]without a closing tag',
				'something[code]without a closing tag[/code]',
			],
			[
				'some open list[list][li]one[/list]',
				'some open list[list][li]one[/li][/list]',
			],
			[
				'some list[code][list][li]one[/list][/code]',
				'some list[code][list][li]one[/list][/code]',
			],
			[
				'something [icode]that is not closed',
				'something [icode]that is not closed[/icode]',
			],
			[
				'something inside an [icode]that is [b]not closed[/icode]',
				'something inside an [icode]that is [b]not closed[/icode]',
			],
		];
	}

	protected function tearDown(): void
	{
		User::$info = null;
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
