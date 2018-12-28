<?php

/**
 * Fetch emails from an imap box and process them
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
 * Class Maillist_Fetch_IMAP - Fetch emails from an imap box and process them
 *
 * - If we can't run this via cron, run it as a task instead
 *
 * @package ScheduledTasks
 */
class MaillistFetchIMAP implements ScheduledTaskInterface
{
	/**
	 * Run the the pseudo cron for IMAP email collection
	 *
	 * @return bool
	 */
	public function run()
	{
		// Only should be run if the user can't set up a proper cron job and can not pipe emails
		require_once(BOARDDIR . '/email_imap_cron.php');

		return true;
	}
}
