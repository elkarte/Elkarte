<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . '../SSI.php');
require_once(SUBSDIR . '/Logging.subs.php');

/**
 * TestCase class for logging
 */
class TestLogging extends UnitTestCase
{
	function tearDown()
	{
		// remove useless data
	}

	function testLogLoginHistory()
	{
		logLoginHistory(1, '10.100.10.100', '11.111.100.10');
	}
}
