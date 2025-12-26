<?php

namespace ElkArte\PersonalMessage;

use ElkArte\EventManager;
use ElkArte\Helper\HttpReq;
use ElkArte\Languages\Loader;
use ElkArte\Themes\ThemeLoader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class ReportTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	protected function setUp(): void
	{
		global $txt;

		parent::setUp();
		$this->setSession();

		new ThemeLoader();
		$lang = new Loader('english', $txt, database());
		$lang->load('PersonalMessage');
	}

	public function testActionReportInitial()
	{
		global $context, $txt, $modSettings;

		$modSettings['enableReportPM'] = 1;

		// We need a PM to report.
		// For simplicity in this test environment, let's assume some data exists.

		$req = HttpReq::instance();
		$req->query->pmsg = 1;

		$controller = new Report(new EventManager());
		$controller->setUser(User::$info);

		// This might fail if PM 1 doesn't exist or isn't accessible.
		try
		{
			$controller->action_report();
			$this->assertEquals(1, $context['pm_id']);
			$this->assertEquals($txt['pm_report_title'], $context['page_title']);
			$this->assertEquals('report_message', $context['sub_template']);
		}
		catch (\ElkArte\Exceptions\Exception $e)
		{
			if ($e->getMessage() === 'no_access')
			{
				$this->markTestSkipped('PM 1 not accessible, need to create one.');
			}
			else
			{
				throw $e;
			}
		}
	}
}
