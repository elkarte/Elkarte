<?php

/**
 * This handles the execution of scheduled tasks, mail queue scheduling included.
 * 
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This controller class action handlers are automatically called.
 * It handles execution of scheduled tasks, mail queue scheduling included.
 */
class ScheduledTasks_Controller
{
	/**
	 * This method works out what to run:
	 *  - it checks if it's time for the next tasks
	 *  - runs next tasks
	 *  - update the database for the next round
	 */
	function action_autotask()
	{
		// Include the ScheduledTasks subs worker.
		require_once(SUBSDIR . '/ScheduledTasks.subs.php');

		// The mail queue is also called from here.
		if (isset($_GET['scheduled']) && $_GET['scheduled'] == 'mailq')
			$this->action_reducemailqueue();
		else
		{
			call_integration_include_hook('integrate_autotask_include');

			$ts = isset($_GET['ts']) ? (int) $_GET['ts'] : 0;
			processNextTasks($ts);

			// Get the timestamp stored for the next task, if any.
			$nextTime = nextTime();

			// If there was none, update with defaults
			if ($nextTime === false)
				updateSettings(array('next_task_time' => time() + 86400));
			else
				updateSettings(array('next_task_time' => $nextTime));
		}

		// Return, if we're not explicitly called.
		// @todo remove?
		if (!isset($_GET['scheduled']))
			return true;

		// Finally, send some bland image
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Type: image/gif');
		die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	}

	/**
	 * Reduce mail queue.
	 */
	public function action_reducemailqueue()
	{
		global $modSettings;

		// Lets not waste our time or resources.
		if (!empty($modSettings['mail_queue_use_cron']))
			return false;

		// This does the hard work, it does.
		require_once(SUBSDIR . '/Mail.subs.php');

		// @todo remove these returns from actions handlers
		return reduceMailQueue();
	}
}