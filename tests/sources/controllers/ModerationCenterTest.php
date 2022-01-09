<?php

use ElkArte\Controller\ModerationCenter;
use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\User;

/**
 * TestCase class for the ModerationCenter Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */
class TestModerationCenterController extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	protected function setUp(): void
	{
		global $context, $modSettings;

		// Load in the common items so the system thinks we have an active login
		parent::setUp();
		parent::setSession();

		User::$info->mod_cache = [
			'bq' => '1=1',
			'ap' => [0],
			'gq' => '1=1',
			'time' => time(),
			'id' => 1,
			'mb' => 1,
			'mq' => 'b.id_board IN (1)',
		];

		unset($context['admin_area']);
		$modSettings['securityDisable_moderate'] = true;
		$modSettings['securityDisable'] = true;

		new ElkArte\Themes\ThemeLoader();
		$lang = new Loader('english');
		$lang->load('ModerationCenter+ManageMembers');
	}

	/**
	 * Show the ModerationCenter, well prepare to show it
	 */
	public function testActionIndexMC()
	{
		global $context, $txt;

		$req = HttpReq::instance();
		$req->query->area = 'index';

		// Get the controller, call index
		$controller = new ModerationCenter(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_index();

		$this->assertEquals($context[$context['moderation_menu_name']]['tab_data']['title'], $txt['moderation_center']);
		$this->assertEquals('My Community', $context['linktree'][1]['name']);
	}

	/**
	 * Show a reported post listing
	 *
	 * Test testActionReporttm in EmailUserTest.php made a report, we depend on that
	 * being run first.  That order dependency should be defined in the XML at some point
	 */
	public function testActionReportedPosts()
	{
		global $context;

		$req = HttpReq::instance();
		$req->query->area = 'reports';

		// Get the controller, call index to dispatch to reported posts
		$controller = new ModerationCenter(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_index();

		$this->assertEquals(1, $context['total_reports'], $context['total_reports']);
		$this->assertEquals('some needless complaint', $context['reports'][1]['comments'][0]['message'], $context['reports'][1]['comments'][0]['message']);
	}

	/**
	 * Show a reported post detail
	 */
	public function testActionReportedPost()
	{
		global $context;

		$req = HttpReq::instance();
		$req->query->area = 'reports';
		$req->query->report = 1;

		// Get the controller, call index to dispatch to reported posts and then to details
		$controller = new ModerationCenter(new EventManager());
		$controller->setUser(User::$info);
		$controller->action_index();

		$this->assertEquals('some needless complaint', $context['report']['comments'][0]['message'], $context['report']['comments'][0]['message']);
		$this->assertEquals('viewmodreport', $context['sub_template'], $context['sub_template']);
	}
}