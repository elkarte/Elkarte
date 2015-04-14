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
			'board' => BOARDDIR . '/*.php',
			'source' => SOURCEDIR . '/*.php',
			'controllers' => CONTROLLERDIR . '/*.php',
			'database' => SOURCEDIR . '/database/*.php',
			'subs' => SUBSDIR . '/*.php',
			'cache_methods' => SUBSDIR . '/CacheMethod/*.php',
			'mention_type' => SUBSDIR . '/MentionType/*.php',
			'scheduled_task' => SUBSDIR . '/ScheduledTask/*.php',
			'admin' => ADMINDIR . '/*.php',
			'ext' => EXTDIR . '/*.php',
			'language' => LANGUAGEDIR . '/english/*.php',
			'defaulttheme' => BOARDDIR . '/themes/default/*.php',
		);

		// These create constants (when evaled) which are already defined (notice error)
		$skip_files = array('Db-mysql.class.php', 'Db-postgresql.class.php');

		foreach ($dirs as $dir)
		{
			$files = glob($dir);

			foreach ($files as $file)
			{
				// Skipping the eval of this one?
				if (in_array($file, $skip_files))
					continue;

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
				else
				{
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

					// Did eval run without error?
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
}