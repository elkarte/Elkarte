<?php

namespace ElkArte\PersonalMessage;

use ElkArte\EventManager;
use ElkArte\Helper\HttpReq;
use ElkArte\Languages\Loader;
use tests\ElkArteCommonSetupTest;

class RulesTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $txt;

		parent::setUp();
		parent::setSession();

		new \ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('PersonalMessage');
	}

	/**
	 * Test adding a rule UI
	 */
	public function testActionAddRule()
	{
		global $context;

		$req = HttpReq::instance();
		$req->query->sa = 'addrule';

		$controller = new Rules(new EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_addRule();

		$this->assertEquals('add_rule', $context['sub_template']);
		$this->assertArrayHasKey('rule', $context);
		$this->assertArrayHasKey('groups', $context);
	}
}
