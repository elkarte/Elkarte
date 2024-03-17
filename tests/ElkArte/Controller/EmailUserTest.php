<?php

/**
 * TestCase class for the EmailUser Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */

namespace ElkArte\Controller;

use ElkArte;
use ElkArte\EventManager;
use ElkArte\Helper\HttpReq;
use ElkArte\Languages\Loader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class EmailUserTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $topic, $txt;

		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('Errors+Validation');

		$topic = 1;
	}

	protected function tearDown(): void
	{
		global $topic;

		parent::tearDown();

		unset($topic);
	}

	/**
	 * Test trying to report a post
	 */
	public function testActionReporttm()
	{
		global $context, $modSettings;

		$req = HttpReq::instance();
		$req->query->msg = 1;
		$req->post->msg = 1;
		$req->post->comment = 'some needless complaint';
		$req->post->email = 'complainer@nowhere.tld';

		// Get the controller
		$controller = new Emailuser(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_reporttm();

		// We are ready to show the report forum
		$this->assertEquals('report', $context['sub_template']);

		// Send the form, you will see a sendmail error in the CI log, ignore it.
		$req->post->save = 1;
		$controller->pre_dispatch();
		$controller->action_reporttm();

		$this->assertNotEmpty($modSettings['last_mod_report_action']);
	}
}