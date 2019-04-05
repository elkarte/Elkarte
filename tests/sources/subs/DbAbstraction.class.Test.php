<?php

class TestDbAbstraction extends \PHPUnit\Framework\TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		$this->_dummy_db = database();

		$this->tests = array(
			array(
				'string_test' => '{string_case_sensitive:a_string}',
				'params_test' => array('a_string' => 'a_string'),
				'results' => array(
					'MySQL' => 'BINARY \'a_string\'',
					'PostgreSQL' => '\'a_string\'',
				)
			),
			array(
				'string_test' => '{string_case_insensitive:a_string}',
				'params_test' => array('a_string' => 'a_string'),
				'results' => array(
					'MySQL' => '\'a_string\'',
					'PostgreSQL' => 'LOWER(\'a_string\')',
				)
			),
			array(
				'string_test' => '{array_string_case_insensitive:a_string}',
				'params_test' => array('a_string' => array('a_string', 'another_string')),
				'results' => array(
					'MySQL' => '\'a_string\', \'another_string\'',
					'PostgreSQL' => 'LOWER(\'a_string\'), LOWER(\'another_string\')',
				)
			),
			array(
				'string_test' => '{column_case_insensitive:a_string}',
				'params_test' => array('a_string' => array('a_string', 'another_string')),
				'results' => array(
					'MySQL' => 'a_string',
					'PostgreSQL' => 'LOWER(a_string)',
				)
			),
		);
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
