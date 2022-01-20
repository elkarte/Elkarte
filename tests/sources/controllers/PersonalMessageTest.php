<?php

use ElkArte\Controller\PersonalMessage;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\User;
use ElkArte\Languages\Loader;

/**
 * TestCase class for the PersonalMessage Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestPersonalMessageController extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $context, $txt;

		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt);
		$lang->load('PersonalMessage+Post');

	}

	/**
	 * Show the PersonalMessage inbox
	 */
	public function testActionIndexPM()
	{
		global $context, $txt;

		$req = HttpReq::instance();
		$req->query->area = 'index';

		// Get the controller, call index
		$controller = new PersonalMessage(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Labels and limits should be set
		$this->assertEquals(0, $context['message_limit']);
		$this->assertNotEmpty($context['labels'][-1]);

		// We should be ready to show the pm inbox, its empty right now
		$this->assertEquals($context['page_title'], $txt['pm_inbox']);
	}

	public function testActionSendPM()
	{
		global $context;

		$req = HttpReq::instance();
		$req->query->sa = 'send';

		// Get the controller, call index
		$controller = new PersonalMessage(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Check if some things are set for the form.
		$this->assertEquals('', $context['bcc_value']);
		$this->assertEquals(false, $context['quoted_message']);
		$this->assertEquals('', $context['subject']);

		// Lets try and send it now
		$modSettings['pm_spam_settings'] = [100,100,0];
		$req->query->sa = 'send2';
		$req->post->subject = 'Yo';
		$req->post->message = 'This is for you, ok, have a great day';
		$req->post->to = 'test_admin';
		$req->post->bcc = '';
		$req->post->u = 1;
		$controller->pre_dispatch();
		$controller->action_index();
		$req->query->sa = null;
		$req->query->area = null;

		// It went, maybe?
		$this->assertStringContainsString(';done=sent', $context['current_label_redirect'], $context['current_label_redirect']);
		$this->assertEquals("PM successfully sent to 'test_admin'.", $context['send_log']['sent'][1]);
	}
}