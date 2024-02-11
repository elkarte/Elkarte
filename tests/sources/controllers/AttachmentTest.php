<?php

/**
 * TestCase class for the Attachment Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */

use ElkArte\Controller\Attachment;
use ElkArte\EventManager;
use ElkArte\Languages\Loader;
use tests\ElkArteCommonSetupTest;

class AttachmentTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $txt;
		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		$this->setSession();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('Post+Errors');
	}

	/**
	 * Test getting the group list for an announcement
	 */
	public function testNeedTheme()
	{
		// Get the attachment controller
		$controller = new Attachment(new EventManager());
		$check = $controller->needTheme();

		// Check
		$this->assertFalse($check);
	}

	public function testUlattach()
	{
		global $context;

		// Get the attachment controller
		$controller = new Attachment(new EventManager());
		$controller->action_ulattach();

		// With no files supplied, the json result should be false
		$this->assertFalse($context['json_data']['result']);

		// Some BS file name
		$_FILES['attachment'] = ['name' => [0 => 'blablabla'], 'tmp_name' => [0 => 'blablabla']];
		$controller->action_ulattach();

		// just check for a key, the results are somewhat random from CI
		$this->assertArrayHasKey('result', $context['json_data']);
	}
}