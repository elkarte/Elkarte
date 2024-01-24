<?php

use PHPUnit\Framework\TestCase;

class TestDbAbstraction extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];
	public $_dummy_db;
	public $tests;

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
		$this->_dummy_db = database();

		$this->tests = [
			[
				'string_test' => '{string_case_sensitive:a_string}',
				'params_test' => ['a_string' => 'a_string'],
				'results' => [
					'MySQL' => 'BINARY \'a_string\'',
					'PostgreSQL' => '\'a_string\'',
				]
			],
			[
				'string_test' => '{string_case_insensitive:a_string}',
				'params_test' => ['a_string' => 'a_string'],
				'results' => [
					'MySQL' => '\'a_string\'',
					'PostgreSQL' => 'LOWER(\'a_string\')',
				]
			],
			[
				'string_test' => '{array_string_case_insensitive:a_string}',
				'params_test' => ['a_string' => ['a_string', 'another_string']],
				'results' => [
					'MySQL' => '\'a_string\', \'another_string\'',
					'PostgreSQL' => 'LOWER(\'a_string\'), LOWER(\'another_string\')',
				]
			],
			[
				'string_test' => '{column_case_insensitive:a_string}',
				'params_test' => ['a_string' => ['a_string', 'another_string']],
				'results' => [
					'MySQL' => 'a_string',
					'PostgreSQL' => 'LOWER(a_string)',
				]
			],
		];
	}

	public function testCallback()
	{
		$db_type = $this->_dummy_db->title();
		foreach ($this->tests as $test)
		{
			$db_string = $this->_dummy_db->quote($test['string_test'], $test['params_test']);
			$this->assertEquals($db_string, $test['results'][$db_type], 'Wrong replacement for ' . $db_type . ' on test ' . $test['string_test']);
		}
	}
}
