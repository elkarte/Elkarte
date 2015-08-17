<?php

/**
 * Interface for scheduled tasks objects
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

namespace ElkArte\sources\subs\ScheduledTask;

if (!defined('ELK'))
	die('No access...');

interface Scheduled_Task_Interface
{
	public function run();
}