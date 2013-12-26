<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');

/**
 * TestCase class for request parsing etc.
 * Without SSI: test Request methods as self-containing.
 */
class TestLanguageStrings extends UnitTestCase
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
		$files = glob(LANGUAGEDIR . '/english/*.php');
		foreach ($files as $file)
		{
			$file_content = file_get_contents($file);
			// Check the validity of the syntax.
			ob_start();
			$errorReporting = error_reporting(0);
			$result = @eval('
				if (false)
				{
					' . preg_replace('~^(?:\s*<\\?(?:php)?|\\?>\s*$)~', '', $file_content) . '
				}
			');
			error_reporting($errorReporting);
			ob_end_clean();

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

	/**
	 * Verify that all Elk $txt indexes contain only letters and numbers and underscores
	 */
	function testParseRequestNumeric()
	{
		$files = glob(LANGUAGEDIR . '/english/*.php');
		foreach ($files as $file)
		{
			$content = file($file);
			foreach ($content as $string)
			{
				if (substr($string, 0, 5) == '$txt[')
				{
					preg_match('~\$txt\[\'(.+?)\'\] = \'.*~', $string, $match);
					if (!empty($match[1]))
					{
						$m = preg_replace('~([^\w:])~', '-->$1<--', $match[1]);

						$this->assertTrue($m === $match[1], 'The index of the string \'' . $match[1] . '\' contains invalid characters: \'' . $m . '\'');
					}
				}
			}
		}
	}
}
