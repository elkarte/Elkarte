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
			)
		);
	}

	public function testCallback()
	{
		foreach ($this->tests as $test)
		{
			$db_string = $this->_dummy_db->quote($test['string_test'], $test['params_test']);
			$this->assertEquals($db_string, $test['results'][DB_TYPE], 'Wrong replacement for ' . DB_TYPE . ' on test ' . $test['string_test']);
		}
	}
}
