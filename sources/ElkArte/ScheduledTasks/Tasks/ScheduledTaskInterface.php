<?php

/**
 * Interface for scheduled tasks objects
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
 * Interface ScheduledTaskInterface
 *
 * - Calls the run method for all registered tasks
 *
 * @package ElkArte\ScheduledTasks\Tasks
 */
interface ScheduledTaskInterface
{
	public function run();
}
