<?php

define('TESTDIR', dirname(__FILE__) . '/');

require_once('simpletest/autorun.php');

// SSI mode should work for most tests. (core and subs)
// For web tester tests, it should not be used.
// For install/upgrade, if we even can test those, SSI is a no-no.
// Note: SSI mode is our way to work-around limitations in defining a 'unit' of behavior,
// since the code is very tightly coupled. If possible, and where possible, tests should
// not use SSI either.

// Might wanna make two or three different suites.
require_once(TESTDIR . '../Settings.php');
global $test_enabled;

echo "WARNING! Tests may work directly with the local database. DON'T run them on ANY other than test installs!\n";
echo "To run the tests, set test_enabled = 1 in Settings.php file.\n";

if (empty($test_enabled))
	die('Testing disabled.');

/**
 * All tests suite. This suite adds all files/classes/folders currently being tested.
 * Many of the tests are integration tests, strictly speaking, since they use both SSI
 * and database work.
 *
 * @todo set up a testing database, i.e. on sqlite maybe, or mysql, like populate script, at the
 * beginning of the suite, and remove or clean it up completely at the end of it.
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
		$this->addFile(TESTDIR . 'sources/controllers/TestAuth.php');
		$this->addFile(TESTDIR . 'sources/controllers/TestRegister.php');

		// admin controllers (web tests)
		$this->addFile(TESTDIR . 'sources/admin/TestManageBoardsSettings.php');
		$this->addFile(TESTDIR . 'sources/admin/TestManagePostsSettings.php');

		// install
		if (!defined('SKIPINSTALL'))
			$this->addFile(TESTDIR . 'install/TestInstall.php');
		$this->addFile(TESTDIR . 'install/TestDatabase.php');

		// data integrity
		$this->addFile(TESTDIR . 'sources/TestFiles.php');
		$this->addFile(TESTDIR . 'sources/TestLanguageStrings.php');

		// core sources
		$this->addFile(TESTDIR . 'sources/TestLogging.php');
		$this->addFile(TESTDIR . 'sources/TestDispatcher.php');
		$this->addFile(TESTDIR . 'sources/TestRequest.php');

		// subs APIs
		$this->addFile(TESTDIR . 'sources/subs/TestBoards.subs.php');
		$this->addFile(TESTDIR . 'sources/subs/TestPoll.subs.php');
		$this->addFile(TESTDIR . 'sources/subs/TestBBC.subs.php');
		$this->addFile(TESTDIR . 'sources/subs/TestHTML2BBC.subs.php');
		$this->addFile(TESTDIR . 'sources/subs/TestValidator.subs.php');
		$this->addFile(TESTDIR . 'sources/subs/TestLike.subs.php');

		// caching
		$this->addFile(TESTDIR . 'sources/subs/TestCache.class.php');
	}
}
