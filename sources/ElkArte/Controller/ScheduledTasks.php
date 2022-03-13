<?php

/**
 * This handles the execution of scheduled tasks, mail queue scheduling included.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Mail\QueueMail;

/**
 * This controllers action handlers are automatically called.
 * It handles execution of scheduled tasks, mail queue scheduling included.
 */
class ScheduledTasks extends AbstractController
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
		{
			$this->action_reducemailqueue();
		}
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
			{
				updateSettings(array('next_task_time' => time() + 86400));
			}
			else
			{
				updateSettings(array('next_task_time' => $nextTime));
			}
		}

		// Return, if we're not explicitly called.
		// @todo remove?
		if (!isset($this->_req->query->scheduled))
		{
			return true;
		}

		// Finally, send some bland image
		dieGif(true);
	}

	/**
	 * Reduce mail queue.
	 */
	public function action_reducemailqueue()
	{
		// This does the hard work, it does.
		(new QueueMail())->reduceMailQueue();
	}
}
