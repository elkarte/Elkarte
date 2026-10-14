<?php

/**
 * TestCase class for running the queued post notification jobs.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */

namespace ElkArte\Notifications;

use ElkArte\User;
use ElkArte\UserInfo;
use tests\ElkArteCommonSetupTest;

class PostNotificationRunnerTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/** @var PostNotificationQueue */
	protected $queue;

	/** @var PostNotificationRunner */
	protected $runner;

	public static function setUpBeforeClass(): void
	{
		// Make sure the table exists, using the same definition the installer uses
		require_once(BOARDDIR . '/install/install_2-0.php');
		$install = new \InstallInstructions_install_2_0(database(), db_table());
		$install->table_post_notification_queue();
	}

	protected function setUp(): void
	{
		parent::setUp();

		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Notification.subs.php');

		$this->queue = new PostNotificationQueue();
		$this->runner = new PostNotificationRunner(null, null, $this->queue);
		database()->query('', 'DELETE FROM {db_prefix}post_notification_queue', []);
	}

	protected function tearDown(): void
	{
		database()->query('', 'DELETE FROM {db_prefix}post_notification_queue', []);

		parent::tearDown();
	}

	/**
	 * Makes a topic with a first post and a reply
	 *
	 * @return int[] topic, first message, reply
	 */
	protected function makeTopicWithReply(): array
	{
		$topicOptions = ['id' => 0, 'board' => 1, 'mark_as_read' => false];
		$posterOptions = [
			'id' => 1,
			'name' => 'test-user',
			'email' => 'noemail@test.tes',
			'update_post_count' => false,
			'ip' => '127.0.0.1',
		];

		$first = ['id' => 0, 'subject' => 'Queue topic', 'smileys_enabled' => true, 'body' => 'First post body', 'attachments' => [], 'approved' => 1];
		createPost($first, $topicOptions, $posterOptions);

		$reply = ['id' => 0, 'subject' => 'Re: Queue topic', 'smileys_enabled' => true, 'body' => 'Second post body', 'attachments' => [], 'approved' => 1];
		$replyTopicOptions = ['id' => $topicOptions['id'], 'board' => 1, 'mark_as_read' => false];
		createPost($reply, $replyTopicOptions, $posterOptions);

		return [(int) $topicOptions['id'], (int) $first['id'], (int) $reply['id']];
	}

	public function testGetTopicInfosUsesTheRequestedMessage()
	{
		[$topic, $first, $reply] = $this->makeTopicWithReply();

		// No message given, it is the latest one as before
		[, $latest] = getTopicInfos([$topic], 'reply');
		$this->assertSame($reply, $latest[$topic]['last_id']);
		$this->assertSame('Second post body', $latest[$topic]['body']);

		// A message given, it is that one even though a newer one exists
		[, $older] = getTopicInfos([$topic], 'reply', $first);
		$this->assertSame($first, $older[$topic]['last_id']);
		$this->assertSame('First post body', $older[$topic]['body']);
	}

	public function testJobForRemovedTopicIsCompletedWithoutSending()
	{
		$this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 999999, 999999, 1, ['name' => 'gone']);

		$this->assertSame(1, $this->runner->runBatch());
		$this->assertSame(0, $this->queue->countPending());
	}

	public function testJobForRemovedMessageIsCompletedWithoutSending()
	{
		[$topic] = $this->makeTopicWithReply();
		$this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, $topic, 999999, 1, ['name' => 'gone']);

		$this->assertSame(1, $this->runner->runBatch());
		$this->assertSame(0, $this->queue->countPending());
	}

	public function testReplyJobForExistingMessageIsCompleted()
	{
		[$topic, , $reply] = $this->makeTopicWithReply();
		$this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, $topic, $reply, 1, ['name' => 'test-user', 'language' => 'english']);

		$this->assertSame(1, $this->runner->runBatch());
		$this->assertSame(0, $this->queue->countPending());
	}

	public function testUnknownJobTypeIsFailedAndKept()
	{
		[$topic, , $reply] = $this->makeTopicWithReply();
		$this->queue->enqueue('bogus', $topic, $reply, 1);

		$this->assertSame(1, $this->runner->runBatch());

		// Kept for another try, but not claimable until the retry delay passes
		$this->assertSame(1, $this->queue->countPending());
		$this->assertSame([], $this->queue->claimBatch());
	}

	public function testPosterIsOnlyInPlaceWhileJobRuns()
	{
		[$topic, , $reply] = $this->makeTopicWithReply();

		$current = new UserInfo(['id' => 7, 'name' => 'visitor', 'language' => 'english']);
		User::$info = $current;

		$job = [
			'id_job' => 1,
			'task_type' => PostNotificationQueue::TYPE_REPLY,
			'id_topic' => $topic,
			'id_msg' => $reply,
			'id_member' => 1,
			'payload' => ['name' => 'test-user', 'language' => 'english'],
			'attempts' => 1,
			'created' => time(),
		];

		$this->assertTrue($this->runner->runJob($job));
		$this->assertSame($current, User::$info);
	}
}
