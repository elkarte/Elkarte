<?php

/**
 * Interface for scheduled tasks objects
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
 *
 */

namespace ElkArte\sources\subs\ScheduledTask;

/**
 * Interface Scheduled_Task_Interface
 *
 * - Calls the run method for all registered tasks
 *
 * @package ElkArte\sources\subs\ScheduledTask
 */
interface Scheduled_Task_Interface
{
	public function run();
}