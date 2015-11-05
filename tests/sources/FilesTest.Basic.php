<?php

/**
 * TestCase class for file integrity
 */
class TestFiles extends PHPUnit_Framework_TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
	}

	/**
	 * Test that there are no syntax errors.
	 *
	 * @todo use recursive fetching of files
	 */
	public function testSyntaxErrors()
	{
		$dirs = array(
			'board' => BOARDDIR,
		);

		// Provide a way to skip eval of files where needed
		$skip_files = array(BOARDDIR . '/index.php');

		$directory = new RecursiveDirectoryIterator(BOARDDIR);
		$iterator = new RecursiveIteratorIterator($directory);
		$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

		foreach ($regex as $fileo)
		{
			$file = $fileo[0];
			$file_content = file_get_contents($file);

			// This is likely to be one of the two files emailpost.php or emailtopic.php
			if ($file_content[0] === '#')
				$file_content = trim(substr($file_content, strpos($file_content, "\n")));

			// Check the depth.
			$level = 0;
			$tokens = @token_get_all($file_content);
			foreach ($tokens as $token)
			{
				if ($token === '{')
					$level++;
				elseif ($token === '}')
					$level--;
			}

			if (!empty($level))
				$this->assertTrue($syntax_valid, empty($level));
			// Skipping the eval of this one?
			elseif (!in_array($file, $skip_files) && strpos($file, '/tests/') === false)
			{
				// Check the validity of the syntax.
				ob_start();
				$errorReporting = error_reporting(0);
				$result = shell_exec(str_replace('{filename}', $file, 'php -l {filename}'));
				error_reporting($errorReporting);
				@ob_end_clean();

				// Did eval run without error?
				$syntax_valid = strpos($result, 'No syntax errors') !== false;
				if (!$syntax_valid)
				{
					$error_message = $result;
					print_r($error_message);
				}
				else
					$error_message = '';

				$this->assertTrue($syntax_valid, $error_message);
			}
		}
	}
}