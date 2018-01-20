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
		$this->_dummy_dbs = database();

		$this->tests = array(
			array(
				'test' => '{string_case_sensitive:a_string}',
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
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', array($this->_dummy_dbs[DB_TYPE], 'replacement__callback'), $test['test']);
			$this->assertEquals($db_string, $test['results'][DB_TYPE], 'Wrong replacement for ' . DB_TYPE . ' on test ' . $test['test']);
		}
	}
}
