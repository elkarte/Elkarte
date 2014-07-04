<?php

require_once(TESTDIR . 'simpletest/autorun.php');
require_once(TESTDIR . 'simpletest/web_tester.php');
// require_once('../install/install.php');

/**
 * TestCase class for installer script
 */
class TestInstall extends WebTestCase
{
	function setUp()
	{
		global $scripturl;

		// it'd be kinda difficult without this :P
		$this->scripturl = $scripturl;
	}

	function testWelcome()
	{
		$this->get(substr($this->scripturl, 0, -9) . '/install/install.php');
		$this->assertTitle('ElkArte Installer');
		$this->assertText('Welcome to ElkArte. This script will guide you through the process for installing');

		// Mmm...
		$this->assertText('continuing with installation may result in the loss or corruption of existing data.');
	}
}
