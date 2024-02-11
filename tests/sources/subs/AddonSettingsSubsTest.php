<?php

use PHPUnit\Framework\TestCase;
use ElkArte\Languages\Loader;

class AddonSettingsSubsTest extends TestCase
{
	/**
	 * Prepare what is necessary to use in these tests.
	 *
	 * setUp() is run automatically by the testing framework before each test method.
	 */
	public function setUp(): void
	{
		global $context, $txt;

		$context['current_filter'] = '';

		$lang = new Loader('english', $txt, database());
		$lang->load('Admin');

		require_once(SUBSDIR . '/AddonSettings.subs.php');
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	protected function tearDown(): void
	{
	}

	/**
	 * Get a listing of all hooks in the system
	 */
	public function testListIntegrationHooksData()
	{
		$hooks = list_integration_hooks_data(0, 10, 'hook_name');

		// We should find integrate_additional_bbc in the system
		$key = array_search('integrate_editor_plugins', array_column($hooks, 'hook_name'), true);

		$this->assertNotFalse($key);
	}
}
