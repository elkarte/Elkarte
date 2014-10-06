<?php

/**
 * Fetch emails from an imap box and process them
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
 * Fetch emails from an imap box and process them
 *
 * - If we can't run this via cron, run it as a task instead
 *
 * @package ScheduledTasks
 */
class Maillist_Fetch_IMAP_Task implements Scheduled_Task_Interface
{
	public function run()
	{
		// Only should be run if the user can't set up a proper cron job and can not pipe emails
		require_once(BOARDDIR . '/email_imap_cron.php');

		return true;
	}
}