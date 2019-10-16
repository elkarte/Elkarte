<?php

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
	function setUp()
	{
		// Load in the common items so the system thinks we have an active login
		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
	}

	/**
	 * Cleanup data we no longer need at the end of the tests in this class.
	 *
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
		parent::tearDown();
	}

	/**
	 * Test getting the calendar
	 * @runInSeparateProcess
	 */
	public function testActionCalendar()
	{
		global $context, $modSettings;

		// Get the controller
		$controller = new \ElkArte\Controller\Calendar(new \ElkArte\EventManager());
		try
		{
			$controller->action_index();
		}
		catch (\Exception $e)
		{
			$check = $e->getMessage();
		}
		$this->assertEquals('calendar_off', $check);

		// Try again with it on
		$modSettings['cal_enabled'] = 1;
		$controller = new \ElkArte\Controller\Calendar(new \ElkArte\EventManager());
		$controller->action_index();

		// Check
		$this->assertIsArray($context['calendar_buttons']['post_event']);
	}
}