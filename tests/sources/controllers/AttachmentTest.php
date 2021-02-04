<?php

use ElkArte\Controller\Attachment;
use ElkArte\EventManager;

/**
 * TestCase class for the Attachment Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestAttachment extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		$this->setSession();

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Post', 'english', true, true);
		theme()->getTemplates()->loadLanguageFile('Errors', 'english', true, true);
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
		$_FILES['attachment'] = [0 => 'blablabla', 'tmp_name' => 'blablabla'];
		$controller->action_ulattach();

		// just check for a key, the results are somewhat random from o'Travis
		$this->assertArrayHasKey('result', $context['json_data']);
	}
}