<?php

/**
 * TestCase class for how the Post controller hands new post notifications to the job queue.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */

use ElkArte\Controller\Post;
use ElkArte\EventManager;
use ElkArte\Notifications\PostNotificationQueue;
use ElkArte\User;
use tests\ElkArteCommonSetupTest;

class PostDeferredNotificationsTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/** @var PostNotificationQueue */
	protected $queue;

	public static function setUpBeforeClass(): void
	{
		// Make sure the table exists, using the same definition the installer uses
		require_once(BOARDDIR . '/install/install_2-0.php');
		$install = new InstallInstructions_install_2_0(database(), db_table());
		$install->table_post_notification_queue();
	}

	protected function setUp(): void
	{
		parent::setUp();

		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Notification.subs.php');

		$this->queue = new PostNotificationQueue();
		database()->query('', 'DELETE FROM {db_prefix}post_notification_queue', []);
	}

	protected function tearDown(): void
	{
		database()->query('', 'DELETE FROM {db_prefix}post_notification_queue', []);

		parent::tearDown();
	}

	/**
	 * Calls the private notify method of the controller
	 */
	protected function notify($type, $topic, $msg, $payload = []): void
	{
		$controller = new Post(new EventManager());
		$controller->setUser(User::$info);

		$method = new ReflectionMethod($controller, '_notifySubscribers');
		$method->setAccessible(true);
		$method->invoke($controller, $type, $topic, $msg, $payload);
	}

	protected function makeTopic(): array
	{
		$topicOptions = ['id' => 0, 'board' => 1, 'mark_as_read' => false];
		$posterOptions = [
			'id' => 1,
			'name' => 'test-user',
			'email' => 'noemail@test.tes',
			'update_post_count' => false,
			'ip' => '127.0.0.1',
		];
		$msgOptions = ['id' => 0, 'subject' => 'Deferred topic', 'smileys_enabled' => true, 'body' => 'Deferred body', 'attachments' => [], 'approved' => 1];
		createPost($msgOptions, $topicOptions, $posterOptions);

		return [(int) $topicOptions['id'], (int) $msgOptions['id']];
	}

	public function testReplyIsQueuedWhenSettingIsOn()
	{
		global $modSettings;

		$modSettings['deferred_post_notifications'] = 1;
		[$topic, $msg] = $this->makeTopic();

		$this->notify(PostNotificationQueue::TYPE_REPLY, $topic, $msg);

		$jobs = $this->queue->claimBatch();
		$this->assertCount(1, $jobs);
		$this->assertSame(PostNotificationQueue::TYPE_REPLY, $jobs[0]['task_type']);
		$this->assertSame($topic, $jobs[0]['id_topic']);
		$this->assertSame($msg, $jobs[0]['id_msg']);
		$this->assertSame((int) User::$info->id, $jobs[0]['id_member']);
		$this->assertSame(User::$info->name, $jobs[0]['payload']['name']);
		$this->assertSame(User::$info->language, $jobs[0]['payload']['language']);
	}

	public function testNewTopicIsQueuedWithItsDataWhenSettingIsOn()
	{
		global $modSettings;

		$modSettings['deferred_post_notifications'] = 1;
		[$topic, $msg] = $this->makeTopic();
		$topicData = ['body' => 'Deferred body', 'subject' => 'Deferred topic', 'name' => 'itsme', 'poster' => 1, 'msg' => $msg, 'board' => 1, 'topic' => $topic, 'signature' => ''];

		$this->notify(PostNotificationQueue::TYPE_TOPIC, $topic, $msg, ['topic_data' => $topicData]);

		$jobs = $this->queue->claimBatch();
		$this->assertCount(1, $jobs);
		$this->assertSame(PostNotificationQueue::TYPE_TOPIC, $jobs[0]['task_type']);
		$this->assertSame($topicData, $jobs[0]['payload']['topic_data']);
	}

	public function testNothingIsQueuedWhenSettingIsOff()
	{
		global $modSettings;

		$modSettings['deferred_post_notifications'] = 0;
		[$topic, $msg] = $this->makeTopic();

		// The member does not exist, so the inline send has nobody to mail
		$this->notify(PostNotificationQueue::TYPE_REPLY, $topic, $msg, ['members_only' => [999999]]);

		$this->assertSame(0, $this->queue->countPending());
	}
}
