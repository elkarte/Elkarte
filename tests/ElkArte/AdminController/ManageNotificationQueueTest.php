<?php

/**
 * TestCase class for ManageNotificationQueue controller
 */

namespace ElkArte\AdminController;

use ElkArte\EventManager;
use ElkArte\Languages\Loader;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class ManageNotificationQueueTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	protected function setUp(): void
	{
		parent::setUp();

		global $txt, $boardurl;

		new \ElkArte\Themes\ThemeLoader();

		// Load languages used by the controller/settings
		$lang = new Loader('english', $txt, database());
		$lang->load('ManageNotificationQueue');
		$lang->load('Admin');

		// Minimal globals commonly used
		$boardurl = 'http://example.com';

		require_once(SUBSDIR . '/Notification.subs.php');
		require_once(SUBSDIR . '/Mail.subs.php');

		require_once(BOARDDIR . '/install/install_2-0.php');
		$install = new \InstallInstructions_install_2_0(database(), db_table());
		$install->table_post_notification_queue();

		database()->query('', 'DELETE FROM {db_prefix}post_notification_queue', []);
	}

	protected function tearDown(): void
	{
		database()->query('', 'DELETE FROM {db_prefix}post_notification_queue', []);

		parent::tearDown();
	}

	public function testSettingsSearch(): void
	{
		$controller = new ManageNotificationQueue(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();

		$settings = $controller->settings_search();

		$this->assertIsArray($settings);
		$this->assertArrayHasKey('deferred_post_notifications', $settings);
		$this->assertArrayHasKey('deferred_post_notifications_delay', $settings);
	}

	public function testQueueStatusAndItemsSubs(): void
	{
		$status = list_NotificationQueueStatus();
		$this->assertSame(0, $status['queue_size']);
		$this->assertSame(0, list_getNotificationQueueSize());

		$items = list_getNotificationQueue(0, 10, 'id_job');
		$this->assertIsArray($items);
		$this->assertEmpty($items);
	}

	public function testTimeSinceFormatting(): void
	{
		$this->assertStringContainsString('Second', time_since(1));
		$this->assertStringContainsString('Seconds', time_since(15));
		$this->assertStringContainsString('Minute', time_since(65));
		$this->assertStringContainsString('Hour', time_since(3700));
		$this->assertStringContainsString('Day', time_since(90000));
	}

	public function testActionBrowseWithItemsInQueue(): void
	{
		global $context;

		$queue = new \ElkArte\Notifications\PostNotificationQueue(database());
		$queue->enqueue(\ElkArte\Notifications\PostNotificationQueue::TYPE_TOPIC, 1, 1, 1, []);

		$controller = new ManageNotificationQueue(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();
		$controller->action_browse();

		$this->assertSame('1', $context['queue_size']);
		$this->assertNotEmpty($context['oldest_job']);
		$this->assertStringNotContainsString('N/A', $context['oldest_job']);
	}
}
