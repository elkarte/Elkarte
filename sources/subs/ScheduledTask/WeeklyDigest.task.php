<?php

/**
 * Sends out email notifications for new/updated topics.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Sends out email notifications for new/updated topics.
 *
 * - Like the daily stuff - just seven times less regular ;)
 * - This method forwards to daily_digest()
 *
 * @package ScheduledTasks
 */
class Weekly_Digest_Task implements Scheduled_Task_Interface
{
	public function run()
	{
		require_once(SUBSDIR . '/ScheduledTask/DailyDigest.class.php');

		$digest = new Daily_Digest();

		return $digest->runDigest(true);
	}
}