<?php

/**
 * TestCase class for the ProfileAccount Controller
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them local if you need to keep your data untouched!
 */

use ElkArte\EventManager;
use ElkArte\HttpReq;
use ElkArte\MembersList;
use ElkArte\Profile\ProfileHistory;
use tests\ElkArteCommonSetupTest;
use ElkArte\Languages\Loader;

class ProfileHistoryTest extends ElkArteCommonSetupTest
{
	// Set up the object to be used in the tests
	public function setUp(): void
	{
		global $txt;

		parent::setUp();

		$lang = new Loader('english', $txt, database());
		$lang->load('Profile+Errors');
	}

	// Test the 'action_trackactivity' method
	public function testActionTrackActivity(): void
	{
		global $context;

		require_once(SUBSDIR . '/Profile.subs.php');
		MembersList::load('1', false, 'profile');

		$_req = HttpReq::instance();
		$_req->query->searchip = '127.0.0.1';

		$profileHistory = new profileHistory(new EventManager());

		// Start reflection on the class so we can set the private vars
		$reflectionClass = new \ReflectionClass($profileHistory);
		$reflectionClass->getProperty('_memID')->setValue($profileHistory, 1);
		$reflectionClass->getProperty('_profile')->setValue($profileHistory, MembersList::get(1));

		$profileHistory->action_trackactivity();

		$this->assertEquals('trackActivity', $context['sub_template']);
		$this->assertCount(3, $context['ips']);
		$this->assertStringContainsString('123.123.123.123', $context['ips'][0]);
	}

	// Test the 'action_trackip' method
	public function testActionTrackIp(): void
	{
		global $context;

		require_once(SUBSDIR . '/Profile.subs.php');
		MembersList::load('1', false, 'profile');

		$_req = HttpReq::instance();
		$_req->query->searchip = '127.0.0.1';

		$profileHistory = new profileHistory(new EventManager());

		// Start reflection on the class so we can set private vars
		$reflectionClass = new \ReflectionClass($profileHistory);
		$reflectionClass->getProperty('_memID')->setValue($profileHistory, 1);
		$reflectionClass->getProperty('_profile')->setValue($profileHistory, MembersList::get(1));

		$profileHistory->action_trackip();

		$this->assertEquals('trackIP', $context['sub_template']);
		$this->assertEquals('127.0.0.1', $context['ip']);
		$this->assertArrayHasKey('track_ip_user_list', $context);
	}

	// Clear up the object created
	public function tearDown(): void
	{
		unset($profileHistory);
	}
}
