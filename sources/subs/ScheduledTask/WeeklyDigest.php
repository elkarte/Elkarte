<?php

/**
 * Sends out email notifications for new/updated topics.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\sources\subs\ScheduledTask;

/**
 * Class Weekly_Digest
 * Sends out email notifications for new/updated topics.
 *
 * - Like the daily stuff - just seven times less regular ;)
 * - This method forwards to daily_digest()
 *
 * @package ScheduledTasks
 */
class Weekly_Digest implements Scheduled_Task_Interface
{
	/**
	 * Sends the weekly digest.
	 *
	 * @return bool
	 */
	public function run()
	{
		$digest = new Daily_Digest();

		return $digest->runDigest(true);
	}
}