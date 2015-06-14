<?php

/**
 * TestCase class for the censorText function
 */
class CensoringTest extends PHPUnit_Framework_TestCase
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

	function testWholeWordsCaseSensitive()
	{
		global $modSettings, $options;

		$inputText = 'This is a bit of text that will be used to test the censoring';
		$tests = array(
			'this' => 'not_this',
			'This' => 'not_case_this',
			'ex' => 'not_ex',
			'EX' => 'not_ex',
		);
		$results = array(
			'this' => 'This is a bit of text that will be used to test the censoring',
			'This' => 'not_case_this is a bit of text that will be used to test the censoring',
			'ex' => 'This is a bit of text that will be used to test the censoring',
			'EX' => 'This is a bit of text that will be used to test the censoring',
		);
		$this->setCensors($tests);

		$modSettings['allow_no_censored'] = false;
		$modSettings['censorWholeWord'] = true;
		$modSettings['censorIgnoreCase'] = false;
	}

	function testWholeWordsCaseInsensitive()
	{
		global $modSettings, $options;

		$inputText = 'This is a bit of text that will be used to test the censoring';
		$tests = array(
			'this' => 'not_this',
			'This' => 'not_case_this',
			'ex' => 'not_ex',
			'EX' => 'not_ex',
		);
		$results = array(
			'this' => 'not_case_this is a bit of text that will be used to test the censoring',
			'This' => 'not_case_this is a bit of text that will be used to test the censoring',
			'ex' => 'This is a bit of text that will be used to test the censoring',
			'EX' => 'This is a bit of text that will be used to test the censoring',
		);
		$this->setCensors($tests);

		$modSettings['allow_no_censored'] = false;
		$modSettings['censorWholeWord'] = true;
		$modSettings['censorIgnoreCase'] = true;
	}

	function testNotWholeWordsCaseSensitive()
	{
		global $modSettings, $options;

		$inputText = 'This is a bit of text that will be used to test the censoring';
		$tests = array(
			'this' => 'not_this',
			'This' => 'not_case_this',
			'ex' => 'not_ex',
			'EX' => 'not_ex',
		);
		$results = array(
			'this' => 'This is a bit of text that will be used to test the censoring',
			'This' => 'not_case_this is a bit of text that will be used to test the censoring',
			'ex' => 'This is a bit of tnot_ext that will be used to test the censoring',
			'EX' => 'This is a bit of text that will be used to test the censoring',
		);
		$this->setCensors($tests);

		$modSettings['allow_no_censored'] = false;
		$modSettings['censorWholeWord'] = false;
		$modSettings['censorIgnoreCase'] = false;
	}

	function testNotWholeWordsCaseInsensitive()
	{
		global $modSettings, $options;

		$inputText = 'This is a bit of text that will be used to test the censoring';
		$tests = array(
			'this' => 'not_this',
			'This' => 'not_case_this',
			'ex' => 'not_ex',
			'EX' => 'not_ex',
		);
		$results = array(
			'this' => 'not_case_this is a bit of text that will be used to test the censoring',
			'This' => 'not_case_this is a bit of text that will be used to test the censoring',
			'ex' => 'This is a bit of tnot_ext that will be used to test the censoring',
			'EX' => 'This is a bit of tnot_ext that will be used to test the censoring',
		);
		$this->setCensors($tests);

		$modSettings['allow_no_censored'] = false;
		$modSettings['censorWholeWord'] = false;
		$modSettings['censorIgnoreCase'] = true;
	}

	protected function setCensors($pairs)
	{
		global $modSettings;
		$vulgar = array();
		$proper = array();

		foreach ($pairs as $key => $val)
		{
			$vulgar[] = $key;
			$proper[] = $val;
		}

		$modSettings['censor_vulgar'] = implode("\n", $vulgar);
		$modSettings['censor_proper'] = implode("\n", $proper);
	}
}