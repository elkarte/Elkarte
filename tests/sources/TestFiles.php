<?php

require_once(TESTDIR . 'simpletest/autorun.php');

/**
 * TestCase class for file integrity
 */
class TestFiles extends UnitTestCase
{
	/**
	 * prepare what is necessary to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	function tearDown()
	{
	}

	/**
	 * Test that there are no syntax errors
	 */
	function testSyntaxErrors()
	{
		$dirs = array(
			'board' => TESTDIR . '/../*.php',
			'source' => TESTDIR . '/../sources/*.php',
			'controllers' => TESTDIR . '/../sources/controllers/*.php',
			'subs' => TESTDIR . '/../sources/subs/*.php',
			'admin' => TESTDIR . '/../sources/admin/*.php',
			'ext' => TESTDIR . '/../sources/ext/*.php',
			'language' => TESTDIR . '/../themes/default/languages/english/*.php',
			'defaulttheme' => TESTDIR . '/../themes/default/*.php',
		);

		foreach ($dirs as $dir)
		{
			$files = glob($dir);

			foreach ($files as $file)
			{
				$file_content = file_get_contents($file);

				// This is likely to be one of the two files emailpost.php or emailtopic.php
				if ($file_content[0] == '#')
					$file_content = trim(substr($file_content, strpos($file_content, "\n")));

				// Check the validity of the syntax.
				ob_start();
				$errorReporting = error_reporting(0);
				$result = @eval('
					if (false)
					{
						' . preg_replace('~(?:^\s*<\\?(?:php)?|\\?>\s*$)~', '', $file_content) . '
					}
				');
				error_reporting($errorReporting);
				@ob_end_clean();

				$syntax_valid = $result !== false;
				if (!$syntax_valid)
				{
					$error = error_get_last();
					$error_message = $error['message'] . ' at [' . $file . ' line ' . ($error['line'] - 3) . ']' . "\n";
					print_r($error);
				}
				else
					$error_message = '';

				$this->assertTrue($syntax_valid, $error_message);
			}
		}
	}
}
