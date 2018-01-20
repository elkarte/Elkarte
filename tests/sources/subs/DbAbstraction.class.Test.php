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
		require_once(SOURCEDIR . '/database/Boards.subs.php');
		require_once('/var/www/tests/sources/subs/Db-DummyMy.class.php');
		require_once('/var/www/tests/sources/subs/Db-DummyPg.class.php');
		$this->_dummy_dbs = array(
			'mysql' => new Database_DummyMy::initiate('', '', '', '', 'a_prefix'),
			'psql' => new Database_DummyPg::initiate('', '', '', '', 'a_prefix'),
		);

		$this->tests = array(
			array(
				'test' => '{string_case_sensitive:a_string}',
				'results' => array(
					'mysql' => 'BINARY \'a_string\'',
					'psql' => '\'a_string\'',
				)
			)
		);
	}

	public function testCallback()
	{
		foreach ($this->tests as $test)
		{
			foreach ($test['results'] as $db => $result)
			{
				$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', array($this->_dummy_dbs[$db], 'replacement__callback'), $test['test']);
				$this->assertEquals($db_string, $result, 'Wrong replacement for ' . $db . ' on test ' . $test['test']);
			}
		}
	}
}
