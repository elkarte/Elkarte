<?php

/**
 * Runs the queued post notification jobs, using PostNotifications to do the actual work.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Notifications;

use ElkArte\AbstractModel;
use ElkArte\Errors\Errors;
use ElkArte\User;
use ElkArte\UserInfo;

/**
 * Class PostNotificationRunner
 *
 * What it does:
 *   - Takes jobs from the PostNotificationQueue and runs them with PostNotifications, so the
 *     recipients, permission checks, and "first reply after a visit" rule are the same as when
 *     the notifications were sent while the post was being saved.
 *   - PostNotifications treats the current user as the poster.  There is no poster when this runs
 *     from the scheduler, so the poster saved in the job is put in place while it runs.
 *   - A topic or message removed before the job ran is not an error, there is nothing to send.
 */
class PostNotificationRunner extends AbstractModel
{
	/** @var PostNotificationQueue */
	protected $queue;

	/** @var string What went wrong in the last failed job */
	protected $lastError = '';

	/**
	 * @param object|null $db
	 * @param object|null $user
	 * @param PostNotificationQueue|null $queue
	 */
	public function __construct($db = null, $user = null, ?PostNotificationQueue $queue = null)
	{
		parent::__construct($db, $user);

		$this->queue = $queue ?? new PostNotificationQueue($this->_db);
	}

	/**
	 * Claim a batch of jobs, run them in the order they were made, and complete or fail each one
	 *
	 * @param int $limit Jobs to run
	 * @return int How many jobs were claimed, 0 when there was nothing to do
	 */
	public function runBatch($limit = 10): int
	{
		$jobs = $this->queue->claimBatch($limit);

		foreach ($jobs as $job)
		{
			if ($this->runJob($job))
			{
				$this->queue->complete($job['id_job']);
			}
			else
			{
				$this->queue->fail($job['id_job'], $this->lastError);
			}
		}

		return count($jobs);
	}

	/**
	 * Send the notifications for one job
	 *
	 * @param array $job As returned by PostNotificationQueue::claimBatch
	 * @return bool False if it should be tried again later
	 */
	public function runJob($job): bool
	{
		$this->lastError = '';

		// Nothing to tell anyone about
		if (!$this->exists($job))
		{
			return true;
		}

		$previous_user = User::$info;
		User::$info = $this->getPoster($job);

		try
		{
			$notifier = new PostNotifications();

			if ($job['task_type'] === PostNotificationQueue::TYPE_REPLY)
			{
				$notifier->sendNotifications($job['id_topic'], 'reply', $job['payload']['members_only'] ?? [], [], $job['id_msg']);
			}
			elseif ($job['task_type'] === PostNotificationQueue::TYPE_TOPIC)
			{
				// Use the text as it is now, the poster may have edited it since it was saved
				$topicData = $this->refreshTopicData($job['payload']['topic_data'] ?? [], $job['id_msg']);
				$notifier->sendBoardNotifications($topicData);
			}
			else
			{
				throw new \InvalidArgumentException('Unknown post notification job type ' . $job['task_type']);
			}

			// Onsite notifications are collected and sent on request, not as they are made
			Notifications::instance()->send();
		}
		catch (\Throwable $e)
		{
			$this->lastError = get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();

			// Log a failing job so it can be diagnosed from the error log
			Errors::instance()->log_error('Post notification job ' . (int) $job['id_job'] . ' failed. ' . $this->lastError . ' ' . $e->getTraceAsString(), 'general');

			return false;
		}
		finally
		{
			User::$info = $previous_user;
		}

		return true;
	}

	/**
	 * Update the saved subject and body of a new topic with what the message currently holds
	 * This is so the notification uses the current text, in case the poster edited it
	 * since it was saved.
	 *
	 * @param array $topicData
	 * @param int $id_msg
	 * @return array
	 */
	protected function refreshTopicData($topicData, $id_msg): array
	{
		$this->_db->fetchQuery('
			SELECT 
				subject, body
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}',
			[
				'id_msg' => (int) $id_msg,
			]
		)->fetch_callback(
			static function ($row) use (&$topicData) {
				$topicData['subject'] = $row['subject'];
				$topicData['body'] = $row['body'];
			}
		);

		return $topicData;
	}

	/**
	 * The topic, and for replies the message, must still be there
	 *
	 * @param array $job
	 * @return bool
	 */
	protected function exists($job): bool
	{
		$found = false;
		$this->_db->fetchQuery('
			SELECT 
			    t.id_topic
			FROM {db_prefix}topics AS t' . ($job['task_type'] === PostNotificationQueue::TYPE_REPLY ? '
				INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.id_msg = {int:id_msg})' : '') . '
			WHERE t.id_topic = {int:id_topic}',
			[
				'id_topic' => (int) $job['id_topic'],
				'id_msg' => (int) $job['id_msg'],
			]
		)->fetch_callback(
			static function ($row) use (&$found) {
				$found = true;
			}
		);

		return $found;
	}

	/**
	 * Who the notifications are from, as saved when the post was made
	 *
	 * @param array $job
	 * @return UserInfo
	 */
	protected function getPoster($job): UserInfo
	{
		global $language;

		return new UserInfo([
			'id' => (int) $job['id_member'],
			'is_guest' => empty($job['id_member']),
			'name' => $job['payload']['name'] ?? '',
			'language' => $job['payload']['language'] ?? $language,
		]);
	}
}
