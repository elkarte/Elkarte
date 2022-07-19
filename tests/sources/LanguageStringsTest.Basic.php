<?php

/**
 * TestCase class for language files integrity
 */
class TestLanguageStrings extends PHPUnit\Framework\TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	protected function setUp(): void
	{
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
	}

	/**
	 * Verify that all Elk $txt indexes contain only letters and numbers and underscores
	 */
	public function testLanguageIndexes()
	{
		$files = glob(LANGUAGEDIR . '/english/*.php');
		foreach ($files as $file)
		{
			$content = file($file);
			$multiline = false;
			$full_string = '';
			foreach ($content as $string)
			{
				$string = trim($string);
				// A string begins with $txt['
				if (substr($string, 0, 6) == '$txt[\'')
				{
					$full_string = $string;
					$multiline = true;
				}
				elseif ($multiline)
					$full_string .= $string;

				// This is the end of the string and not some odd stuff
				if ((substr($string, -2) == '\';' && substr($string, -3) != '\\\';') || (substr($string, -2) == '";' && substr($string, -3) != '\\";'))
				{
					preg_match('~\$txt\[\'(.+?)\'\] = \'(.*)$~', $full_string, $match);
					if (!empty($match[1]))
					{
						$m = preg_replace('~([^\w:])~', '-->$1<--', $match[1]);

						$this->assertTrue($m === $match[1], 'The index of the string \'' . $match[1] . '\' contains invalid characters: \'' . $m . '\'');
					}
					if (!empty($match[2]))
					{
						$contains_concat = (strpos($match[2], '\' . \'') !== false || strpos($match[2], '" . "') !== false);
						$this->assertFalse($contains_concat, 'The string \'' . $match[1] . '\' seems to contain some kind of PHP string concatenation, please fix it (or fix the test).');
					}
					$full_string = '';
					$multiline = false;
				}
			}
		}
	}
}