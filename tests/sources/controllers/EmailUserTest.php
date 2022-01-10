<?php

use ElkArte\Controller\Emailuser;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\User;
use ElkArte\Languages\Loader;

/**
 * TestCase class for the EmailUser Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestEmailUserController extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $topic;

		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english');
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
	 * Test trying to email a topic
	 */
	public function testActionSendTopic()
	{
		global $context;

		// Get the controller, call index
		$controller = new Emailuser(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Check that the send topic template was set
		$this->assertEquals('send_topic', $context['sub_template']);

		// Now try to send it, but without filling out the form we get a error instead
		$req = HttpReq::instance();
		$req->post->send = true;

		$controller->pre_dispatch();
		$controller->action_index();

		$this->assertNotEmpty($context['sendtopic_error']);
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