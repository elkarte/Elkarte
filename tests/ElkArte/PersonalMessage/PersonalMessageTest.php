<?php

/**
 * TestCase class for the PersonalMessage Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */

namespace ElkArte\PersonalMessage;

use ElkArte;
use ElkArte\EventManager;
use ElkArte\Helper\HttpReq;
use ElkArte\Languages\Loader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class PersonalMessageTest extends ElkArteCommonSetupTest
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
		$req->query->sa = 'inbox';

		// Get the controller, call index
		$controller = new PersonalMessage(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Labels and limits should be set
		$this->assertEquals(0, $context['message_limit']);
		$this->assertNotEmpty($context['labels'][-1]);

		// We should be ready to show the pm inbox, it's empty right now
		$this->assertEquals('folder', $context['sub_template']);
		$this->assertEquals($txt['pm_inbox'], $context['page_title']);
	}

	public function testActionSendPM()
	{
		global $context, $modSettings;

		$req = HttpReq::instance();
		$req->query->sa = 'send';

		// Get the controller, call index
		$controller = new PersonalMessage(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_index();

		// Check if some things are set for the form.
		$this->assertEquals('', $context['bcc_value']);
		$this->assertFalse($context['quoted_message']);
		$this->assertEquals('', $context['subject']);

		// Let's try and send it now
		$modSettings['pm_spam_settings'] = "100, 100, 0";
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
