<?php

use ElkArte\Controller\Calendar;
use ElkArte\EventManager;
use ElkArte\Languages\Loader;

/**
 * TestCase class for the Calendar Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestCalendar extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $txt;
		// Load in the common items so the system thinks we have an active login
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('Errors');
	}

	/**
	 * Test getting the calendar
	 */
	public function testActionCalendar()
	{
		// Get the controller
		$check = '';
		$controller = new Calendar(new EventManager());
		try
		{
			$controller->action_index();
		}
		catch (Exception $e)
		{
			$check = $e->getMessage();
		}
		$this->assertStringContainsString('You cannot access the calendar right now because it is disabled', $check);

		// Try again with it on
		// Unfortunately the Calendar_Event.class.test has a section of mock functions which will cause
		// fatal errors here when Calendar.subs.php is properly loaded due to duplicate function names.

		//$modSettings['cal_enabled'] = 1;
		//$controller = new \ElkArte\Controller\Calendar(new \ElkArte\EventManager());
		//$controller->action_index();

		// Check
		//$this->assertIsArray($context['calendar_buttons']['post_event']);
	}
}