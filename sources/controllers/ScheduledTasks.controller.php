<?php

/**
 * This handles the execution of scheduled tasks, mail queue scheduling included.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * ScheduledTasks_Controller Class
 * This controllers action handlers are automatically called.
 * It handles execution of scheduled tasks, mail queue scheduling included.
 */
class ScheduledTasks_Controller extends Action_Controller
{
	/**
	 * Default method for the class, just forwards to autotask
	 *
	 * @return bool
	 */
	public function action_index()
	{
		return $this->action_autotask();
	}

	/**
	 * This method works out what to run
	 *
	 * What it does:
	 *  - It checks if it's time for the next tasks
	 *  - Runs next tasks
	 *  - Update the database for the next round
	 */
	public function action_autotask()
	{
		// Include the ScheduledTasks subs worker.
		require_once(SUBSDIR . '/ScheduledTasks.subs.php');

		// The mail queue is also called from here.
		if ($this->_req->getQuery('scheduled') === 'mailq')
			$this->action_reducemailqueue();
		else
		{
			call_integration_include_hook('integrate_autotask_include');

			// Run tasks based on this time stamp
			$ts = $this->_req->getQuery('ts', 'intval', 0);
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
		if (!isset($this->_req->query->scheduled))
			return true;

		// Finally, send some bland image
		dieGif(true);
	}

	/**
	 * Reduce mail queue.
	 */
	public function action_reducemailqueue()
	{
		global $modSettings;

		// Lets not waste our time or resources.
		if (empty($modSettings['mail_queue_use_cron']))
		{
			// This does the hard work, it does.
			require_once(SUBSDIR . '/Mail.subs.php');
			reduceMailQueue();
		}
	}
}