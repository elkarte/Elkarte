<?php

/**
 * A small persistent job queue for post notifications, so notifying subscribers
 * can happen outside the post save request.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 *
 */

namespace ElkArte\Notifications;

use ElkArte\AbstractModel;
use ElkArte\Errors\Errors;

/**
 * Class PostNotificationQueue
 *
 * One job is stored per post, not per subscriber.  The subscribers are read when the job runs.
 *
 * - enqueue() adds a job
 * - claimBatch() locks and returns jobs, a job can only be claimed once until its lock expires
 * - complete() removes a finished job
 * - fail() releases a job for a later retry, or drops it once it has used all of its attempts
 */
class PostNotificationQueue extends AbstractModel
{
	/** Job for a reply, runs PostNotifications::sendNotifications */
	public const TYPE_REPLY = 'reply';

	/** Job for a new topic, runs PostNotifications::sendBoardNotifications */
	public const TYPE_TOPIC = 'topic';

	/** Default seconds a new job waits before it can run, so a "quick" edit of the post is what gets sent */
	public const GRACE_SECONDS = 180;

	/** How many times a job may be claimed before it is dropped */
	public const MAX_ATTEMPTS = 5;

	/** Seconds a claimed job stays locked, a crashed run is retried after this */
	public const LOCK_SECONDS = 300;

	/** Seconds added to the retry delay for each attempt already made */
	public const RETRY_DELAY = 60;

	/**
	 * Add a job to the queue
	 *
	 * @param string $type One of the TYPE constants
	 * @param int $id_topic The topic the post is in
	 * @param int $id_msg The message that was posted
	 * @param int $id_member The member that posted
	 * @param array $payload Anything else needed to rebuild the notice
	 * @param int $delay Seconds before the job may be claimed, a grace period for the poster to edit the post
	 * @return int The id of the new job
	 */
	public function enqueue($type, $id_topic, $id_msg, $id_member, $payload = [], $delay = 0): int
	{
		$this->_db->insert('',
			'{db_prefix}post_notification_queue',
			[
				'task_type' => 'string-10', 'id_topic' => 'int', 'id_msg' => 'int', 'id_member' => 'int', 'payload' => 'string',
				'attempts' => 'int', 'created' => 'int', 'locked_until' => 'int',
			],
			[
				$type, (int) $id_topic, (int) $id_msg, (int) $id_member, json_encode($payload),
				0, time(), $delay > 0 ? time() + (int) $delay : 0,
			],
			['id_job']
		);

		return (int) $this->_db->insert_id('{db_prefix}post_notification_queue');
	}

	/**
	 * Lock and return the oldest jobs that are free to run
	 *
	 * What it does:
	 * - Jobs that used all their attempts, for example by crashing every time, are dropped.
	 * - Each job is locked with an UPDATE that only matches while it is unlocked, and
	 *   the affected rows are checked, so two runs at the same time never get the same job.
	 * - The attempt counter is raised when the job is claimed.
	 *
	 * @param int $limit Most jobs to claim
	 * @param int $lock_seconds How long the claim lasts
	 * @param bool $force Claim jobs regardless of locked_until timestamp (e.g. manual queue run)
	 * @return array[] Jobs in the order they were added, each with a decoded payload
	 */
	public function claimBatch($limit = 10, $lock_seconds = self::LOCK_SECONDS, bool $force = false): array
	{
		$now = time();

		$this->_db->query('', '
			DELETE FROM {db_prefix}post_notification_queue
			WHERE attempts >= {int:max_attempts}
				AND locked_until < {int:now}',
			[
				'max_attempts' => self::MAX_ATTEMPTS,
				'now' => $now,
			]
		);

		$candidates = [];
		$this->_db->fetchQuery('
			SELECT 
				id_job
			FROM {db_prefix}post_notification_queue
			' . ($force ? '' : 'WHERE locked_until < {int:now}') . '
			ORDER BY id_job
			LIMIT {int:limit}',
			[
				'now' => $now,
				'limit' => max(1, (int) $limit),
			]
		)->fetch_callback(
			static function ($row) use (&$candidates) {
				$candidates[] = (int) $row['id_job'];
			}
		);

		$claimed = [];
		foreach ($candidates as $id_job)
		{
			$result = $this->_db->query('', '
				UPDATE {db_prefix}post_notification_queue
				SET locked_until = {int:locked_until}, attempts = attempts + 1
				WHERE id_job = {int:id_job}' . ($force ? '' : ' AND locked_until < {int:now}'),
				[
					'locked_until' => $now + (int) $lock_seconds,
					'id_job' => $id_job,
					'now' => $now,
				]
			);

			if ($result->affected_rows() > 0)
			{
				$claimed[] = $id_job;
			}
		}

		if (empty($claimed))
		{
			return [];
		}

		$jobs = [];
		$this->_db->fetchQuery('
			SELECT 
				id_job, task_type, id_topic, id_msg, id_member, payload, attempts, created
			FROM {db_prefix}post_notification_queue
			WHERE id_job IN ({array_int:claimed})
			ORDER BY id_job',
			[
				'claimed' => $claimed,
			]
		)->fetch_callback(
			static function ($row) use (&$jobs) {
				$payload = json_decode((string) $row['payload'], true);
				$jobs[] = [
					'id_job' => (int) $row['id_job'],
					'task_type' => $row['task_type'],
					'id_topic' => (int) $row['id_topic'],
					'id_msg' => (int) $row['id_msg'],
					'id_member' => (int) $row['id_member'],
					'payload' => is_array($payload) ? $payload : [],
					'attempts' => (int) $row['attempts'],
					'created' => (int) $row['created'],
				];
			}
		);

		return $jobs;
	}

	/**
	 * A job did not run, keep it for another try or drop it
	 *
	 * The attempt was already counted when the job was claimed.  If there are attempts
	 * left the job is unlocked after a delay that grows with each attempt, otherwise
	 * it is logged and removed.
	 *
	 * @param int $id_job
	 * @param string $reason What went wrong, used in the error log
	 * @return bool True if the job was dropped
	 */
	public function fail($id_job, $reason = ''): bool
	{
		$attempts = null;
		$this->_db->fetchQuery('
			SELECT 
				attempts
			FROM {db_prefix}post_notification_queue
			WHERE id_job = {int:id_job}',
			[
				'id_job' => (int) $id_job,
			]
		)->fetch_callback(
			static function ($row) use (&$attempts) {
				$attempts = (int) $row['attempts'];
			}
		);

		// Already gone
		if ($attempts === null)
		{
			return true;
		}

		if ($attempts >= self::MAX_ATTEMPTS)
		{
			Errors::instance()->log_error('Post notification job ' . (int) $id_job . ' dropped after ' . $attempts . ' attempts. ' . $reason, 'general');
			$this->complete($id_job);

			return true;
		}

		$this->_db->query('', '
			UPDATE {db_prefix}post_notification_queue
			SET locked_until = {int:locked_until}
			WHERE id_job = {int:id_job}',
			[
				'locked_until' => time() + self::RETRY_DELAY * $attempts,
				'id_job' => (int) $id_job,
			]
		);

		return false;
	}

	/**
	 * A job ran, remove it
	 *
	 * @param int $id_job
	 */
	public function complete($id_job): void
	{
		$this->_db->query('', '
			DELETE FROM {db_prefix}post_notification_queue
			WHERE id_job = {int:id_job}',
			[
				'id_job' => (int) $id_job,
			]
		);
	}

	/**
	 * How many jobs are waiting, locked or not
	 *
	 * @return int
	 */
	public function countPending(): int
	{
		$count = 0;
		$this->_db->fetchQuery('
			SELECT 
			    COUNT(*) AS total
			FROM {db_prefix}post_notification_queue',
			[]
		)->fetch_callback(
			static function ($row) use (&$count) {
				$count = (int) $row['total'];
			}
		);

		return $count;
	}

	/**
	 * Get the total number of items in the notification queue
	 *
	 * @return int
	 */
	public function getQueueSize(): int
	{
		return $this->countPending();
	}

	/**
	 * Fetch a paginated list of notification queue items with message and member info
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @return array
	 */
	public function getQueueItems(int $start, int $items_per_page, string $sort = 'id_job'): array
	{
		$allowed_sorts = [
			'id_job' => 'q.id_job',
			'id_job DESC' => 'q.id_job DESC',
			'task_type' => 'q.task_type',
			'task_type DESC' => 'q.task_type DESC',
			'subject' => 'm.subject',
			'subject DESC' => 'm.subject DESC',
			'poster' => 'mem.real_name',
			'poster DESC' => 'mem.real_name DESC',
			'age' => 'q.created',
			'age DESC' => 'q.created DESC',
			'attempts' => 'q.attempts',
			'attempts DESC' => 'q.attempts DESC',
			'status' => 'q.locked_until',
			'status DESC' => 'q.locked_until DESC',
		];

		$sort_clause = $allowed_sorts[$sort] ?? 'q.id_job DESC';

		$items = [];
		$this->_db->fetchQuery('
			SELECT 
				q.id_job, q.task_type, q.id_topic, q.id_msg, q.id_member, q.payload, q.attempts, q.created, q.locked_until,
				COALESCE(m.subject, {string:empty_subject}) AS subject,
				COALESCE(mem.real_name, mem.member_name, {string:empty_poster}) AS poster_name
			FROM {db_prefix}post_notification_queue AS q
				LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = q.id_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = q.id_member)
			ORDER BY ' . $sort_clause . '
			LIMIT {int:start}, {int:items_per_page}',
			[
				'empty_subject' => '',
				'empty_poster' => '',
				'start' => max(0, $start),
				'items_per_page' => max(1, $items_per_page),
			]
		)->fetch_callback(
			static function ($row) use (&$items) {
				$payload = json_decode((string) $row['payload'], true);
				$row['payload'] = is_array($payload) ? $payload : [];
				$items[] = $row;
			}
		);

		return $items;
	}

	/**
	 * Get summary statistics for the notification queue
	 *
	 * @return array{queue_size: int, oldest: int, ready: int, in_grace: int, locked: int}
	 */
	public function getQueueStatus(): array
	{
		$now = time();
		$status = [
			'queue_size' => 0,
			'oldest' => 0,
			'ready' => 0,
			'in_grace' => 0,
			'locked' => 0,
		];

		$this->_db->fetchQuery('
			SELECT 
				COUNT(*) AS queue_size,
				MIN(created) AS oldest,
				SUM(CASE WHEN locked_until <= {int:now} THEN 1 ELSE 0 END) AS ready,
				SUM(CASE WHEN locked_until > {int:now} AND attempts = 0 THEN 1 ELSE 0 END) AS in_grace,
				SUM(CASE WHEN locked_until > {int:now} AND attempts > 0 THEN 1 ELSE 0 END) AS locked
			FROM {db_prefix}post_notification_queue',
			[
				'now' => $now,
			]
		)->fetch_callback(
			static function ($row) use (&$status) {
				$status['queue_size'] = (int) $row['queue_size'];
				$status['oldest'] = (int) $row['oldest'];
				$status['ready'] = (int) $row['ready'];
				$status['in_grace'] = (int) $row['in_grace'];
				$status['locked'] = (int) $row['locked'];
			}
		);

		return $status;
	}

	/**
	 * Delete specific jobs by their IDs
	 *
	 * @param array $job_ids
	 */
	public function deleteJobs(array $job_ids): void
	{
		$job_ids = array_filter(array_map('intval', $job_ids));
		if (empty($job_ids))
		{
			return;
		}

		$this->_db->query('', '
			DELETE FROM {db_prefix}post_notification_queue
			WHERE id_job IN ({array_int:job_ids})',
			[
				'job_ids' => $job_ids,
			]
		);
	}

	/**
	 * Purge all jobs from the notification queue
	 */
	public function purgeQueue(): void
	{
		$this->_db->query('', '
			DELETE FROM {db_prefix}post_notification_queue',
			[]
		);
	}
}
