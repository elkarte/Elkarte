<?php

/**
 * TestCase class for the ProfileAccount Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */

use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\Profile\ProfileAccount;
use tests\ElkArteCommonSetupTest;
use ElkArte\Languages\Loader;

class ProfileAccountTest extends ElkArteCommonSetupTest
{
	private $profileAccount;

	// Set up the object to be used in the tests
	public function setUp(): void
	{
		global $txt;

		parent::setUp();
		$this->setSession();
		$this->profileAccount = new ProfileAccount(new EventManager());

		$GLOBALS['modSettings']['warning_enable'] = 1;
		$GLOBALS['modSettings']['user_limit'] = 0;

		$lang = new Loader('english', $txt, database());
		$lang->load('Profile+Errors');
	}

	// Test the 'action_issuewarning' method
	public function testActionIssueWarning(): void
	{
		global $context, $cur_profile;

		$_req = HttpReq::instance();
		$_req->query['msg'] = 1;
		unset($_req->post->save);

		$context['user']['is_owner'] = false;
		$cur_profile['warning'] = 0;
		$cur_profile['real_name'] = 'bad user';

		$this->profileAccount->action_issuewarning();

		$this->assertCount(3, $context['notification_templates']);
		$this->assertEquals('Spamming', $context['notification_templates'][0]['title']);
    }

	// Test the 'action_deleteaccount' method
	public function testActionDeleteAccount(): void
	{
		global $context, $cur_profile;

		$context['user']['is_owner'] = false;
		$cur_profile['real_name'] = 'bad user';

		$this->profileAccount->action_deleteaccount();

		// All the method really does is setup the page
		$this->assertEquals('deleteAccount', $context['sub_template']);
	}

	// Clear up the object created
	public function tearDown(): void
	{
		unset($this->profileAccount);
	}
}
