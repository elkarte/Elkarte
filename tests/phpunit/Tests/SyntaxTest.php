<?php

class SyntaxTest extends PHPUnit_Framework_TestCase
{
	/**
	 * Feed files to the syntax tester
	 * 
	 * @return string
	 */
	public function filesDataProvider()
	{
		// Get all of the PHP files in the BOARDDIR
		return $GLOBALS['elkTestBootstrap']->getPHPFileIterator(BOARDDIR);
	}

	/**
	 * Test that there are no syntax errors
	 * 
	 * @provider filesDataProvider
	 */
	public function testSyntaxErrors($file)
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

	// @todo add JSON syntax check

	// @todo add Javascript syntax check
}