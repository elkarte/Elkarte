<?php

namespace ElkArte\PersonalMessage;

use ElkArte\EventManager;
use ElkArte\Helper\HttpReq;
use ElkArte\Languages\Loader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;
use ElkArte\Themes\ThemeLoader;

class LabelsTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	protected function setUp(): void
	{
		global $context, $txt;

		parent::setUp();
		$this->setSession();

		new ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('PersonalMessage');

		// Standard labels
		$context['labels'] = [
			-1 => ['id' => -1, 'name' => 'Inbox', 'messages' => 0, 'unread_messages' => 0],
			0 => ['id' => 0, 'name' => 'Sent', 'messages' => 0, 'unread_messages' => 0],
		];

		// Some rules as well
		$context['rules'] = [];
	}

	public function testActionManLabels()
	{
		global $context, $txt;

		$req = HttpReq::instance();
		$req->query->sa = 'manlabels';

		$controller = new Labels(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_manlabels();

		$this->assertEquals($txt['pm_manage_labels'], $context['page_title']);
		$this->assertEquals('labels', $context['sub_template']);
	}
}
