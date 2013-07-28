<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file is automatically called and handles all manner of scheduled things.
 *
 */

if (!defined('ELK'))
	die('No access...');

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
		global $time_start;

		$db = database();

		// Include the ScheduledTasks subs and class.
		require_once(SUBSDIR . '/ScheduledTasks.subs.php');
		require_once(SUBSDIR . '/ScheduledTask.class.php');

		// Special case for doing the mail queue.
		if (isset($_GET['scheduled']) && $_GET['scheduled'] == 'mailq')
			$this->action_reducemailqueue();
		else
		{
			call_integration_hook('integrate_autotask_include');

			// Select the next task to do.
			$request = $db->query('', '
				SELECT id_task, task, next_time, time_offset, time_regularity, time_unit
				FROM {db_prefix}scheduled_tasks
				WHERE disabled = {int:not_disabled}
					AND next_time <= {int:current_time}
				ORDER BY next_time ASC
				LIMIT 1',
				array(
					'not_disabled' => 0,
					'current_time' => time(),
				)
			);
			if ($db->num_rows($request) != 0)
			{
				// The two important things really...
				$row = $db->fetch_assoc($request);

				// When should this next be run?
				$next_time = next_time($row['time_regularity'], $row['time_unit'], $row['time_offset']);

				// How long in seconds it the gap?
				$duration = $row['time_regularity'];
				if ($row['time_unit'] == 'm')
					$duration *= 60;
				elseif ($row['time_unit'] == 'h')
					$duration *= 3600;
				elseif ($row['time_unit'] == 'd')
					$duration *= 86400;
				elseif ($row['time_unit'] == 'w')
					$duration *= 604800;

				// If we were really late running this task actually skip the next one.
				if (time() + ($duration / 2) > $next_time)
					$next_time += $duration;

				// Update it now, so no others run this!
				$db->query('', '
					UPDATE {db_prefix}scheduled_tasks
					SET next_time = {int:next_time}
					WHERE id_task = {int:id_task}
						AND next_time = {int:current_next_time}',
					array(
						'next_time' => $next_time,
						'id_task' => $row['id_task'],
						'current_next_time' => $row['next_time'],
					)
				);
				$affected_rows = $db->affected_rows();

				// The method must exist in ScheduledTask class, or we are wasting our time.
				// Do also some timestamp checking,
				// and do this only if we updated it before.
				$task = new ScheduledTask();
				if (method_exists($task, $row['task']) && (!isset($_GET['ts']) || $_GET['ts'] == $row['next_time']) && $affected_rows)
				{
					ignore_user_abort(true);

					// Do the task...
					$completed = $task->{$row['task']}();

					// Log that we did it ;)
					if ($completed)
					{
						$total_time = round(microtime(true) - $time_start, 3);
						logTask($row['id_task'], (int)$total_time);
					}
				}
			}
			$db->free_result($request);

			// Get the next timestamp right.
			$request = $db->query('', '
				SELECT next_time
				FROM {db_prefix}scheduled_tasks
				WHERE disabled = {int:not_disabled}
				ORDER BY next_time ASC
				LIMIT 1',
				array(
					'not_disabled' => 0,
				)
			);
			// No new task scheduled yet?
			if ($db->num_rows($request) === 0)
				$nextEvent = time() + 86400;
			else
				list ($nextEvent) = $db->fetch_row($request);
			$db->free_result($request);

			updateSettings(array('next_task_time' => $nextEvent));
		}

		// Shall we return?
		if (!isset($_GET['scheduled']))
			return true;

		// Finally, send some stuff...
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
		// This does the hard work, it does.
		require_once(SUBSDIR . '/Mail.subs.php');

		reduceMailQueue();
	}
}