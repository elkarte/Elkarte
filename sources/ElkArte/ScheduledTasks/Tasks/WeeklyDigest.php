<?php

/**
 * Sends out email notifications for new/updated topics.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\ScheduledTasks\Tasks;

/**
 * Class Weekly_Digest
 * Sends out email notifications for new/updated topics.
 *
 * - Like the daily stuff - just seven times less regular ;)
 * - This method forwards to daily_digest()
 *
 * @package ScheduledTasks
 */
class WeeklyDigest implements ScheduledTaskInterface
{
	/**
	 * Sends the weekly digest.
	 *
	 * @return bool
	 */
	public function run()
	{
		$digest = new DailyDigest();

		return $digest->runDigest(true);
	}
}
