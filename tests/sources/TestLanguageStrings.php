<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');

/**
 * TestCase class for language files integrity
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
	 * Verify that all Elk $txt indexes contain only letters and numbers and underscores
	 */
	function testLanguageIndexes()
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
