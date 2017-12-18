<?php

/**
 * TestCase class for the censorText function
 */
class CensoringTest extends \PHPUnit\Framework\TestCase
{
	protected $tests;

	/**
	 * prepare what is necessary to use in these tests.
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	function setUp()
	{
		$this->tests = array(
			'this' => array('this' => 'not_this'),
			'This' => array('This' => 'not_case_this'),
			'ex' => array('ex' => 'not_ex'),
			'EX' => array('EX' => 'not_ex'),
		);
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
		global $modSettings;

		$inputText = 'This is a bit of text that will be used to test the censoring';

		$results = array(
			'this' => 'This is a bit of text that will be used to test the censoring',
			'This' => 'not_case_this is a bit of text that will be used to test the censoring',
			'ex' => 'This is a bit of text that will be used to test the censoring',
			'EX' => 'This is a bit of text that will be used to test the censoring',
		);

		$modSettings['allow_no_censored'] = false;
		$modSettings['censorWholeWord'] = true;
		$modSettings['censorIgnoreCase'] = false;

		foreach ($this->tests as $key => $test)
		{
			$this->setCensors($test);
			$censor = new Censor(explode("\n", $modSettings['censor_vulgar']), explode("\n", $modSettings['censor_proper']), $modSettings);
			$censored = $censor->censor($inputText);

			$this->assertEquals($censored, $results[$key]);
		}
	}

	function testWholeWordsCaseInsensitive()
	{
		global $modSettings;

		$inputText = 'This is a bit of text that will be used to test the censoring';

		$results = array(
			'this' => 'not_this is a bit of text that will be used to test the censoring',
			'This' => 'not_case_this is a bit of text that will be used to test the censoring',
			'ex' => 'This is a bit of text that will be used to test the censoring',
			'EX' => 'This is a bit of text that will be used to test the censoring',
		);

		$modSettings['allow_no_censored'] = false;
		$modSettings['censorWholeWord'] = true;
		$modSettings['censorIgnoreCase'] = true;

		foreach ($this->tests as $key => $test)
		{
			$this->setCensors($test);
			$censor = new Censor(explode("\n", $modSettings['censor_vulgar']), explode("\n", $modSettings['censor_proper']), $modSettings);
			$censored = $censor->censor($inputText);

			$this->assertEquals($censored, $results[$key]);
		}
	}

	function testNotWholeWordsCaseSensitive()
	{
		global $modSettings;

		$inputText = 'This is a bit of text that will be used to test the censoring';

		$results = array(
			'this' => 'This is a bit of text that will be used to test the censoring',
			'This' => 'not_case_this is a bit of text that will be used to test the censoring',
			'ex' => 'This is a bit of tnot_ext that will be used to test the censoring',
			'EX' => 'This is a bit of text that will be used to test the censoring',
		);

		$modSettings['allow_no_censored'] = false;
		$modSettings['censorWholeWord'] = false;
		$modSettings['censorIgnoreCase'] = false;

		foreach ($this->tests as $key => $test)
		{
			$this->setCensors($test);
			$censor = new Censor(explode("\n", $modSettings['censor_vulgar']), explode("\n", $modSettings['censor_proper']), $modSettings);
			$censored = $censor->censor($inputText);

			$this->assertEquals($censored, $results[$key]);
		}
	}

	function testNotWholeWordsCaseInsensitive()
	{
		global $modSettings;

		$inputText = 'This is a bit of text that will be used to test the censoring';

		$results = array(
			'this' => 'not_this is a bit of text that will be used to test the censoring',
			'This' => 'not_case_this is a bit of text that will be used to test the censoring',
			'ex' => 'This is a bit of tnot_ext that will be used to test the censoring',
			'EX' => 'This is a bit of tnot_ext that will be used to test the censoring',
		);

		$modSettings['allow_no_censored'] = false;
		$modSettings['censorWholeWord'] = false;
		$modSettings['censorIgnoreCase'] = true;

		foreach ($this->tests as $key => $test)
		{
			$this->setCensors($test);
			$censor = new Censor(explode("\n", $modSettings['censor_vulgar']), explode("\n", $modSettings['censor_proper']), $modSettings);
			$censored = $censor->censor($inputText);

			$this->assertEquals($censored, $results[$key]);
		}
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