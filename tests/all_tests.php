<?php

define('TESTDIR', dirname(__FILE__) . '/');

require_once('simpletest/autorun.php');

// SSI mode should work for most tests. (core and subs)
// For web tester tests, it should not be necessary.
// For install/upgrade, if we even can test those, SSI is a no-no.
// Might wanna make two or three different suites.
require_once('../SSI.php');

/**
 * All tests suite. This suite adds all files/classes/folders currently
 * being tested.
 * 
 * To run all tests, execute php all_tests.php in tests directory
 * Or, scripturl/tests/all_tests.php
 */
class AllTests extends TestSuite
{
	function AllTests()
	{
		$this->TestSuite('All tests');

		// controllers (web tests)
		$this->addFile('sources/controllers/TestAuth.php');

		// admin controllers (web tests)
		$this->addFile('sources/admin/TestManageBoardsSettings.php');
		$this->addFile('sources/admin/TestManagePostsSettings.php');

		// install
		$this->addFile('install/TestInstall.php');

		// core sources
		$this->addFile('sources/TestLogging.php');
		$this->addFile('sources/TestDispatcher.php');

		// subs APIs
		$this->addFile('sources/subs/TestBoards.subs.php');
		$this->addFile('sources/subs/TestPoll.subs.php');
	}
}
