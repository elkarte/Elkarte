<?php

use PHPUnit\Framework\TestCase;

class BootstrapRunTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Verifies the bootstrap run until the end.
	 * If the database is not properly setup, bootstrap would fail, but the build
	 * exits with 0, giving green light.
	 * This file tries to avoid that situation.
	 */
	public function testBootstraplock()
	{
		$this->assertFileExists('bootstrapcompleted.lock');
	}
}