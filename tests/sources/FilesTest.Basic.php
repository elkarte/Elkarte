<?php

/**
 * TestCase class for file integrity
 */
class TestFiles extends PHPUnit_Framework_TestCase
{
	protected $_ourFiles = array();

	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp()
	{
		$this->_ourFiles = array();

		$directory = new RecursiveDirectoryIterator(BOARDDIR);
		$iterator = new RecursiveIteratorIterator($directory);
		$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

		foreach ($regex as $fileo)
		{
			$file = $fileo[0];

			// We do not care about non-project files
			if (strpos($file, '/sources/ext/') !== false)
				continue;
			if (strpos($file, '/vendor/') !== false)
				continue;

			$this->_ourFiles[] = $file;
		}
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
	 */
	public function testSyntaxErrors()
	{
		// Provide a way to skip eval of files where needed
		$skip_files = array(BOARDDIR . '/index.php');

		foreach ($this->_ourFiles as $file)
		{
			$syntax_valid = false;
			if (!is_writable($file))
			{
				continue;
			}

			$file_content = file_get_contents($file);

			if (empty($file_content[0]))
			{
				continue;
			}

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
				// This is for variables in strings "something {$a_variable} in a string"
				elseif (is_array($token) && $token[1] === '{')
					$level++;
			}

			if (!empty($level))
				$this->assertTrue($syntax_valid, 'Syntax error in: ' . $file);
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

	/**
	 * Test that the headers are in place
	 * @todo distinguish between SMF-derived and non-SMF-derived
	 */
	public function testHeaders()
	{
		$header_styles = array(
			// Pure ElkArte
			'^<\?php

\/\*\*
(?: \*.{0,200}\n)* \* @name\s{1,10}ElkArte Forum
 \* @copyright ElkArte Forum contributors
 \* @license\s+?BSD http:\/\/opensource\.org\/licenses\/BSD-3-Clause
 \*
 \* @version \d+\.\d+(?:\.\d+|\sdev|\s(beta|RC|Release Candidate)\s\d+)
(?:(?: \*\n)?|(?: \*(?:\s.{0,200})?\n))+ \*\/',
			// Pure ElkArte with php path
			'^#!\/usr\/bin\/php -q
<\?php

\/\*\*
(?: \*.{0,200}\n)* \* @name\s{1,10}ElkArte Forum
 \* @copyright ElkArte Forum contributors
 \* @license\s+?BSD http:\/\/opensource\.org\/licenses\/BSD-3-Clause
 \*
 \* @version \d+\.\d+(?:\.\d+|\sdev|\s(beta|RC|Release Candidate)\s\d+)
(?:(?: \*\n)?|(?: \*(?:\s.{0,200})?\n))+ \*\/',
			// SMF-derived
			'^<\?php

\/\*\*
(?: \*.{0,200}\n)* \* @name\s{1,10}ElkArte Forum
 \* @copyright ElkArte Forum contributors
 \* @license\s+?BSD http:\/\/opensource\.org\/licenses\/BSD-3-Clause
 \*
 \* This file contains code covered by:
 \* copyright:\s+?20\d\d Simple Machines (?:Forum )?\(http:\/\/www\.simplemachines\.org\)
 \* license:\s+?BSD, See included LICENSE\.TXT for terms and conditions\.(
 \*
 \* copyright:\s+?2004-2011, GreyWyvern - All rights reserved\.
 \* license:\s+?BSD, See included LICENSE.TXT for terms and conditions.)?
 \*
 \* @version \d+\.\d+(?:\.\d+|\sdev|\s(beta|RC|Release Candidate)\s\d+)
(?:(?: \*\n)?|(?: \*(?:\s.{0,200})?\n))+ \*\/',
		);
		foreach ($this->_ourFiles as $file)
		{
			// We do not care about some project files
			if (strpos($file, BOARDDIR . '/cache/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/addons/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/attachments/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/avatars/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/docs/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/packages/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/release_tools/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/smileys/') !== false)
				continue;
			// This is the path used by ElkArteInstallWeb
			if (strpos($file, BOARDDIR . '/test/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/tests/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/wiki/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/install/patch') !== false)
				continue;
			if (strpos($file, '/vendor/') !== false)
				continue;
			if (strpos($file, BOARDDIR . '/themes/default/languages/') !== false)
				continue;
			if (strpos($file, 'phpunit_coverage.php') !== false)
				continue;
			if (basename($file) == 'index.php' && $file != BOARDDIR . '/index.php')
				continue;
			if ($file == BOARDDIR . '/Settings.php'
				|| $file == BOARDDIR . '/Settings_bak.php'
				|| $file == BOARDDIR . '/Settings.sample.php'
				|| $file == BOARDDIR . '/Settings_bak.sample.php')
				continue;
			if (is_link($file))
				continue;

			$file_content = @file_get_contents($file);
			if (empty($file_content))
				continue;

			$found = false;

			foreach ($header_styles as $style)
			{
				if (preg_match('%' . strtr($style, array("\n" => '\n')) . '%', $file_content) == 1)
				{
					$found = true;
					continue;
				}
			}
			$this->assertTrue($found, 'Failing on: ' . $file);
		}
	}
}