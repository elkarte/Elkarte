<?php

/**
 * TestCase class for the post notification job queue.
 *
 * WARNING. These tests work directly with the local database. Don't run
 * them if you need to keep your data untouched!
 */

namespace ElkArte\Notifications;

use tests\ElkArteCommonSetupTest;

class PostNotificationQueueTest extends ElkArteCommonSetupTest
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/** @var PostNotificationQueue */
	protected $queue;

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

		$this->queue = new PostNotificationQueue();
		$this->emptyQueue();
	}

	protected function tearDown(): void
	{
		$this->emptyQueue();

		parent::tearDown();
	}

	protected function emptyQueue(): void
	{
		database()->query('', 'DELETE FROM {db_prefix}post_notification_queue', []);
	}

	public function testEnqueueWritesOneRow()
	{
		$id = $this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 5, 10, 2, ['members_only' => [3]]);

		$this->assertGreaterThan(0, $id);
		$this->assertSame(1, $this->queue->countPending());
	}

	public function testClaimBatchReturnsJobOnlyOnce()
	{
		$id = $this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 5, 10, 2, ['members_only' => [3]]);

		$first = $this->queue->claimBatch();
		$this->assertCount(1, $first);
		$this->assertSame($id, $first[0]['id_job']);
		$this->assertSame(PostNotificationQueue::TYPE_REPLY, $first[0]['task_type']);
		$this->assertSame(5, $first[0]['id_topic']);
		$this->assertSame(10, $first[0]['id_msg']);
		$this->assertSame(2, $first[0]['id_member']);
		$this->assertSame(['members_only' => [3]], $first[0]['payload']);
		$this->assertSame(1, $first[0]['attempts']);

		// A second run, e.g. a concurrent one, gets nothing while the lock holds
		$this->assertSame([], $this->queue->claimBatch());
	}

	public function testClaimBatchKeepsOrderAndLimit()
	{
		$a = $this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 1, 1, 1);
		$b = $this->queue->enqueue(PostNotificationQueue::TYPE_TOPIC, 2, 2, 1);
		$c = $this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 3, 3, 1);

		$batch = $this->queue->claimBatch(2);
		$this->assertSame([$a, $b], array_column($batch, 'id_job'));

		$batch = $this->queue->claimBatch(2);
		$this->assertSame([$c], array_column($batch, 'id_job'));
	}

	public function testCompleteRemovesJob()
	{
		$this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 5, 10, 2);
		$job = $this->queue->claimBatch()[0];

		$this->queue->complete($job['id_job']);

		$this->assertSame(0, $this->queue->countPending());
	}

	public function testFailKeepsJobUntilRetryDelayPasses()
	{
		$this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 5, 10, 2);
		$job = $this->queue->claimBatch()[0];

		$this->assertFalse($this->queue->fail($job['id_job'], 'test'));
		$this->assertSame(1, $this->queue->countPending());

		// Delayed, so not available straight away
		$this->assertSame([], $this->queue->claimBatch());

		// Once the delay is over it is claimed again and the attempt is counted
		$this->expireLocks();
		$again = $this->queue->claimBatch();
		$this->assertCount(1, $again);
		$this->assertSame(2, $again[0]['attempts']);
	}

	public function testJobIsDroppedAfterMaximumAttempts()
	{
		$this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 5, 10, 2);

		for ($i = 1; $i < PostNotificationQueue::MAX_ATTEMPTS; $i++)
		{
			$job = $this->queue->claimBatch()[0];
			$this->assertSame($i, $job['attempts']);
			$this->assertFalse($this->queue->fail($job['id_job'], 'test'));
			$this->expireLocks();
		}

		$job = $this->queue->claimBatch()[0];
		$this->assertSame(PostNotificationQueue::MAX_ATTEMPTS, $job['attempts']);
		$this->assertTrue($this->queue->fail($job['id_job'], 'test'));
		$this->assertSame(0, $this->queue->countPending());
	}

	public function testCrashedJobIsRetriedThenDropped()
	{
		$this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 5, 10, 2);

		// A run that claims and never reports back, the lock expires each time
		for ($i = 1; $i <= PostNotificationQueue::MAX_ATTEMPTS; $i++)
		{
			$this->assertCount(1, $this->queue->claimBatch());
			$this->expireLocks();
		}

		$this->assertSame([], $this->queue->claimBatch());
		$this->assertSame(0, $this->queue->countPending());
	}

	public function testGetQueueItemsEmptyQueue()
	{
		$items = $this->queue->getQueueItems(0, 10, 'id_job');
		$this->assertSame([], $items);
	}

	public function testGetQueueItemsAndStatus()
	{
		$id1 = $this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 1, 10, 2, ['members_only' => [3]]);
		$id2 = $this->queue->enqueue(PostNotificationQueue::TYPE_TOPIC, 2, 20, 2);

		$this->assertSame(2, $this->queue->getQueueSize());

		$status = $this->queue->getQueueStatus();
		$this->assertSame(2, $status['queue_size']);
		$this->assertGreaterThan(0, $status['oldest']);

		$items = $this->queue->getQueueItems(0, 10, 'id_job');
		$this->assertCount(2, $items);
		$this->assertSame($id1, (int) $items[0]['id_job']);
		$this->assertSame($id2, (int) $items[1]['id_job']);

		$this->queue->deleteJobs([$id1]);
		$this->assertSame(1, $this->queue->getQueueSize());

		$this->queue->purgeQueue();
		$this->assertSame(0, $this->queue->getQueueSize());
	}

	public function testForceClaimBatch()
	{
		// Enqueue with future delay (e.g. 180 seconds)
		$this->queue->enqueue(PostNotificationQueue::TYPE_REPLY, 1, 10, 2, [], 180);

		// Without force, locked item is not claimed
		$this->assertSame([], $this->queue->claimBatch(10));

		// With force, it is claimed immediately
		$claimed = $this->queue->claimBatch(10, PostNotificationQueue::LOCK_SECONDS, true);
		$this->assertCount(1, $claimed);
	}

	protected function expireLocks(): void
	{
		database()->query('', '
			UPDATE {db_prefix}post_notification_queue
			SET locked_until = {int:past}',
			['past' => 1]
		);
	}
}
