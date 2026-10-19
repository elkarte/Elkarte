<?php

/**
 * Sends the notifications for new replies and topics that were queued when the post was saved.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 *
 */

namespace ElkArte\ScheduledTasks\Tasks;

use ElkArte\Notifications\PostNotificationQueue;
use ElkArte\Notifications\PostNotificationRunner;

/**
 * Sends the notifications for new replies and topics that were queued when the post was saved.
 *
 * - When saving a post only adds a job to the queue, this does the actual slow part of looking up
 *   and mailing the subscribers.
 * - Runs batches until the queue is empty or the time budget is used, anything left is picked up by the next run.
 *
 * @package ScheduledTasks
 */
class PostNotifications implements ScheduledTaskInterface
{
	/** How many jobs are claimed at once */
	public const BATCH_SIZE = 10;

	/** Seconds after which no new batch is started */
	public const TIME_BUDGET = 60;

	/**
	 * Run a batch of queued post notification jobs
	 *
	 * @return bool
	 */
	public function run()
	{
		// Mailing a lot of subscribers can take a while
		detectServer()->setTimeLimit(300);

		$runner = new PostNotificationRunner(null, null, new PostNotificationQueue());
		$stop = time() + self::TIME_BUDGET;

		do
		{
			$claimed = $runner->runBatch(self::BATCH_SIZE);
		} while ($claimed > 0 && time() < $stop);

		return true;
	}
}
