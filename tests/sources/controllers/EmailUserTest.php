<?php

/**
 * TestCase class for the EmailUser Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestEmailUserController extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];
	protected $data = '';

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	function setUp()
	{
		global $topic;

		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Errors', 'english', false, true);
		theme()->getTemplates()->loadLanguageFile('Validation', 'english', false, true);

		$topic = 1;
	}

	function tearDown()
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
		$controller = new \ElkArte\Controller\Emailuser(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Check that the send topic template was set
		$context['sub_template'] = 'send_topic';
		$this->assertEquals($context['sub_template'], 'send_topic');

		// Now try to send it, but without filling out the form we get a error instead
		$req = \ElkArte\HttpReq::instance();
		$req->post->send = true;

		$controller->pre_dispatch();
		$controller->action_index();

		$this->assertNotEmpty($context['sendtopic_error']);
	}

	/**
	 * Test trying to post at a pbe message
	 */
	public function testActionReporttm()
	{
		global $context, $modSettings;

		$req = \ElkArte\HttpReq::instance();
		$req->query->msg = 1;
		$req->post->msg = 1;
		$req->post->comment = 'some needless complaint';
		$req->post->email = 'complainer@nowhere.tld';

		// Get the controller
		$controller = new \ElkArte\Controller\Emailuser(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_reporttm();

		// We will fail since the key does not exist
		$this->assertEquals($context['sub_template'], 'report');

		$req->post->save = 1;
		$controller->pre_dispatch();
		$controller->action_reporttm();

		$this->assertNotEmpty($modSettings['last_mod_report_action']);
	}
}