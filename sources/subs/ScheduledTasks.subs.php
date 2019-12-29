<?php

/**
 * Functions to support schedules tasks
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Util;

/**
 * Calculate the next time the passed tasks should be triggered.
 *
 * @param string[]|string $tasks = array() the tasks
 * @param bool $forceUpdate
 * @throws \ElkArte\Exceptions\Exception
 * @package ScheduledTasks
 */
function calculateNextTrigger($tasks = array(), $forceUpdate = false)
{
	global $modSettings;

	$db = database();

	$task_query = '';

	if (!is_array($tasks))
	{
		$tasks = array($tasks);
	}

	// Actually have something passed?
	if (!empty($tasks))
	{
		if (!isset($tasks[0]) || is_numeric($tasks[0]))
		{
			$task_query = ' AND id_task IN ({array_int:tasks})';
		}
		else
		{
			$task_query = ' AND task IN ({array_string:tasks})';
		}
	}

	$nextTaskTime = empty($tasks) ? time() + 86400 : $modSettings['next_task_time'];

	// Get the critical info for the tasks.
	$request = $db->query('', '
		SELECT 
			id_task, next_time, time_offset, time_regularity, time_unit, task
		FROM {db_prefix}scheduled_tasks
		WHERE disabled = {int:no_disabled}
			' . $task_query,
		array(
			'no_disabled' => 0,
			'tasks' => $tasks,
		)
	);
	$tasks = array();
	$scheduleTaskImmediate = !empty($modSettings['scheduleTaskImmediate']) ? Util::unserialize($modSettings['scheduleTaskImmediate']) : array();
	while (($row = $request->fetch_assoc()))
	{
		// scheduleTaskImmediate is a way to speed up scheduled tasks and fire them as fast as possible
		if (!empty($scheduleTaskImmediate) && isset($scheduleTaskImmediate[$row['task']]))
		{
			$next_time = next_time(1, '', rand(0, 60), true);
		}
		else
		{
			$next_time = next_time($row['time_regularity'], $row['time_unit'], $row['time_offset']);
		}

		// Only bother moving the task if it's out of place or we're forcing it!
		if ($forceUpdate || $next_time < $row['next_time'] || $row['next_time'] < time())
		{
			$tasks[$row['id_task']] = $next_time;
		}
		else
		{
			$next_time = $row['next_time'];
		}

		// If this is sooner than the current next task, make this the next task.
		if ($next_time < $nextTaskTime)
		{
			$nextTaskTime = $next_time;
		}
	}
	$request->free_result();

	// Now make the changes!
	foreach ($tasks as $id => $time)
	{
		$db->query('', '
			UPDATE {db_prefix}scheduled_tasks
			SET 
				next_time = {int:next_time}
			WHERE id_task = {int:id_task}',
			array(
				'next_time' => $time,
				'id_task' => $id,
			)
		);
	}

	// If the next task is now different update.
	if ($modSettings['next_task_time'] != $nextTaskTime)
	{
		updateSettings(array('next_task_time' => $nextTaskTime));
	}
}

/**
 * Returns a time stamp of the next instance of these time parameters.
 *
 * @param int $regularity
 * @param string $unit
 * @param int $offset
 * @param bool $immediate
 * @return int
 * @package ScheduledTasks
 */
function next_time($regularity, $unit, $offset, $immediate = false)
{
	// Just in case!
	if ($regularity == 0)
	{
		$regularity = 2;
	}

	$curMin = date('i', time());

	// If we have scheduleTaskImmediate running, then it's 10 seconds
	if (empty($unit) && $immediate)
	{
		$next_time = time() + 10;
	}
	// If the unit is minutes only check regularity in minutes.
	elseif ($unit === 'm')
	{
		$off = date('i', $offset);

		// If it's now just pretend it ain't,
		if ($off == $curMin)
		{
			$next_time = time() + $regularity;
		}
		else
		{
			// Make sure that the offset is always in the past.
			$off = $off > $curMin ? $off - 60 : $off;

			while ($off <= $curMin)
			{
				$off += $regularity;
			}

			// Now we know when the time should be!
			$next_time = time() + 60 * ($off - $curMin);
		}
	}
	// Otherwise, work out what the offset would be with todays date.
	else
	{
		$next_time = mktime(date('H', $offset), date('i', $offset), 0, date('m'), date('d'), date('Y'));

		// Make the time offset in the past!
		if ($next_time > time())
		{
			$next_time -= 86400;
		}

		// Default we'll jump in hours.
		$applyOffset = 3600;

		// 24 hours = 1 day.
		if ($unit == 'd')
		{
			$applyOffset = 86400;
		}

		// Otherwise a week.
		if ($unit == 'w')
		{
			$applyOffset = 604800;
		}

		$applyOffset *= $regularity;

		// Just add on the offset.
		while ($next_time <= time())
		{
			$next_time += $applyOffset;
		}
	}

	return $next_time;
}

/**
 * Loads a basic tasks list.
 *
 * @param int[] $tasks
 * @return array
 * @throws \Exception
 * @package ScheduledTasks
 */
function loadTasks($tasks)
{
	$db = database();

	$task = array();
	$db->fetchQuery('
		SELECT 
			id_task, task
		FROM {db_prefix}scheduled_tasks
		WHERE id_task IN ({array_int:tasks})
		LIMIT ' . count($tasks),
		array(
			'tasks' => $tasks,
		)
	)->fetch_callback(
		function ($row) use (&$task) {
			$task[$row['id_task']] = $row['task'];
		}
	);

	return $task;
}

/**
 * Logs a task.
 *
 * @param int $id_log the id of the log entry of the task just run. If empty it is considered a new log entry
 * @param int $task_id the id of the task run (from the table scheduled_tasks)
 * @param int|null $total_time How long the task took to finish. If NULL (default value) -1 will be used
 * @return int the id_log value
 * @throws \ElkArte\Exceptions\Exception
 * @package ScheduledTasks
 */
function logTask($id_log, $task_id, $total_time = null)
{
	$db = database();

	if (empty($id_log))
	{
		$db->insert('',
			'{db_prefix}log_scheduled_tasks',
			array('id_task' => 'int', 'time_run' => 'int', 'time_taken' => 'float'),
			array($task_id, time(), $total_time === null ? -1 : $total_time),
			array('id_task')
		);

		return $db->insert_id('{db_prefix}log_scheduled_tasks');
	}
	else
	{
		$db->query('', '
			UPDATE {db_prefix}log_scheduled_tasks
			SET 
				time_taken = {float:time_taken}
			WHERE id_log = {int:id_log}',
			array(
				'time_taken' => $total_time,
				'id_log' => $id_log,
			)
		);

		return $id_log;
	}
}

/**
 * All the scheduled tasks associated with the id passed to the function are
 * enabled, while the remaining are disabled
 *
 * @param int[] $enablers array od task IDs
 * @throws \ElkArte\Exceptions\Exception
 * @package ScheduledTasks
 */
function updateTaskStatus($enablers)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}scheduled_tasks
		SET 
			disabled = CASE WHEN id_task IN ({array_int:id_task_enable}) THEN 0 ELSE 1 END',
		array(
			'id_task_enable' => $enablers,
		)
	);
}

/**
 * Sets the task status to enabled / disabled by task name (i.e. function)
 *
 * @param string $enabler the name (the function) of a task
 * @param bool $enable is if the tasks should be enabled or disabled
 * @throws \ElkArte\Exceptions\Exception
 * @package ScheduledTasks
 */
function toggleTaskStatusByName($enabler, $enable = true)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}scheduled_tasks
		SET 
			disabled = {int:status}
		WHERE task = {string:task_enable}',
		array(
			'task_enable' => $enabler,
			'status' => $enable ? 0 : 1,
		)
	);
}

/**
 * Update the properties of a scheduled task.
 *
 * @param int $id_task
 * @param int|null $disabled
 * @param int|null $offset
 * @param int|null $interval
 * @param string|null $unit
 * @throws \ElkArte\Exceptions\Exception
 * @package ScheduledTasks
 */
function updateTask($id_task, $disabled = null, $offset = null, $interval = null, $unit = null)
{
	$db = database();

	$sets = array(
		'disabled' => 'disabled = {int:disabled}',
		'offset' => 'time_offset = {int:time_offset}',
		'interval' => 'time_regularity = {int:time_regularity}',
		'unit' => 'time_unit = {string:time_unit}',
	);

	$updates = array();
	foreach ($sets as $key => $set)
	{
		if (isset($$key))
		{
			$updates[] = $set;
		}
	}

	$db->query('', '
		UPDATE {db_prefix}scheduled_tasks
		SET 
			' . (implode(',', $updates)) . '
		WHERE id_task = {int:id_task}',
		array(
			'disabled' => $disabled,
			'time_offset' => $offset,
			'time_regularity' => $interval,
			'id_task' => $id_task,
			'time_unit' => $unit,
		)
	);
}

/**
 * Loads the details from a given task.
 *
 * @param int $id_task
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception no_access
 * @package ScheduledTasks
 *
 */
function loadTaskDetails($id_task)
{
	$db = database();

	$task = array();

	$db->fetchQuery('
		SELECT 
			id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
		FROM {db_prefix}scheduled_tasks
		WHERE id_task = {int:id_task}',
		array(
			'id_task' => $id_task,
		)
	)->fetch_callback(
		function ($row) use (&$task) {
			global $txt;

			$task = array(
				'id' => $row['id_task'],
				'function' => $row['task'],
				'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
				'desc' => isset($txt['scheduled_task_desc_' . $row['task']]) ? $txt['scheduled_task_desc_' . $row['task']] : '',
				'next_time' => $row['disabled'] ? $txt['scheduled_tasks_na'] : standardTime($row['next_time'] == 0 ? time() : $row['next_time'], true, 'server'),
				'disabled' => $row['disabled'],
				'offset' => $row['time_offset'],
				'regularity' => $row['time_regularity'],
				'offset_formatted' => date('H:i', $row['time_offset']),
				'unit' => $row['time_unit'],
			);
		}
	);

	// Should never, ever, happen!
	if (empty($task))
	{
		throw new \ElkArte\Exceptions\Exception('no_access', false);
	}

	return $task;
}

/**
 * Returns an array of registered scheduled tasks.
 *
 * - Used also by createList() callbacks.
 *
 * @return array
 * @throws \Exception
 * @package ScheduledTasks
 */
function scheduledTasks()
{
	$db = database();

	$known_tasks = array();
	$db->fetchQuery('
		SELECT 
			id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
		FROM {db_prefix}scheduled_tasks',
		array()
	)->fetch_callback(
		function ($row, &$known_tasks) {
			global $txt;

			// Find the next for regularity - don't offset as it's always server time!
			$offset = sprintf($txt['scheduled_task_reg_starting'], date('H:i', $row['time_offset']));
			$repeating = sprintf($txt['scheduled_task_reg_repeating'], $row['time_regularity'], $txt['scheduled_task_reg_unit_' . $row['time_unit']]);

			$known_tasks[] = array(
				'id' => $row['id_task'],
				'function' => $row['task'],
				'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
				'desc' => isset($txt['scheduled_task_desc_' . $row['task']]) ? $txt['scheduled_task_desc_' . $row['task']] : '',
				'next_time' => $row['disabled'] ? $txt['scheduled_tasks_na'] : standardTime(($row['next_time'] == 0 ? time() : $row['next_time']), true, 'server'),
				'disabled' => $row['disabled'],
				'checked_state' => $row['disabled'] ? '' : 'checked="checked"',
				'regularity' => $offset . ', ' . $repeating,
			);
		}
	);

	return $known_tasks;
}

/**
 * Return task log entries, within the passed limits.
 *
 * - Used by createList() callbacks.
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 *
 * @return array
 * @throws \Exception
 * @package ScheduledTasks
 */
function getTaskLogEntries($start, $items_per_page, $sort)
{
	$db = database();

	$log_entries = array();
	$db->fetchQuery('
		SELECT 
			lst.id_log, lst.id_task, lst.time_run, lst.time_taken, st.task
		FROM {db_prefix}log_scheduled_tasks AS lst
			INNER JOIN {db_prefix}scheduled_tasks AS st ON (st.id_task = lst.id_task)
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array()
	)->fetch_callback(
		function ($row) use (&$log_entries) {
			global $txt;

			$log_entries[] = array(
				'id' => $row['id_log'],
				'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
				'time_run' => $row['time_run'],
				// -1 means failed task, but in order to look better in the UI we switch it to 0
				'time_taken' => $row['time_taken'] == -1 ? 0 : $row['time_taken'],
				'task_completed' => $row['time_taken'] != -1,
			);
		}
	);

	return $log_entries;
}

/**
 * Return the number of task log entries.
 *
 * - Used by createList() callbacks.
 *
 * @return int
 * @throws \ElkArte\Exceptions\Exception
 * @package ScheduledTasks
 */
function countTaskLogEntries()
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}log_scheduled_tasks',
		array()
	);
	list ($num_entries) = $request->fetch_row();
	$request->free_result();

	return $num_entries;
}

/**
 * Empty the scheduled tasks log.
 */
function emptyTaskLog()
{
	$db = database();

	$db->query('truncate_table', '
		TRUNCATE {db_prefix}log_scheduled_tasks',
		array()
	);
}

/**
 * Process the next tasks, one by one, and update the results.
 *
 * @param int $ts = 0
 * @throws \ElkArte\Exceptions\Exception
 * @package ScheduledTasks
 */
function processNextTasks($ts = 0)
{
	$db = database();

	// Select the next task to do.
	$request = $db->fetchQuery('
		SELECT 
			id_task, task, next_time, time_offset, time_regularity, time_unit
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
	if ($request->num_rows() != 0)
	{
		// The two important things really...
		$row = $request->fetch_assoc();

		// When should this next be run?
		$next_time = next_time($row['time_regularity'], $row['time_unit'], $row['time_offset']);

		// How long in seconds is the gap?
		$duration = $row['time_regularity'];
		switch ($row['time_unit']) {
			case 'm':
				$duration *= 60;
				break;
			case 'h':
			 	$duration *= 3600;
				break;
			case 'd':
		   		$duration *= 86400;
				break;
			case 'w':
				$duration *= 604800;
				break;
		}

		// If we were really late running this task actually skip the next one.
		if (time() + ($duration / 2) > $next_time)
		{
			$next_time += $duration;
		}

		// Update it now, so no others run this!
		$affected_rows = $db->query('', '
			UPDATE {db_prefix}scheduled_tasks
			SET 
				next_time = {int:next_time}
			WHERE id_task = {int:id_task}
				AND next_time = {int:current_next_time}',
			array(
				'next_time' => $next_time,
				'id_task' => $row['id_task'],
				'current_next_time' => $row['next_time'],
			)
		)->affected_rows();

		// Do also some timestamp checking,
		// and do this only if we updated it before.
		if ((empty($ts) || $ts == $row['next_time']) && $affected_rows)
		{
			ignore_user_abort(true);
			run_this_task($row['id_task'], $row['task']);
		}

	}
}

/**
 * Calls the supplied task_name so that it is executed.
 *
 * - Logs that the task was executed with success or if it failed
 *
 * @param int $id_task specific id of the task to run, used for logging
 * @param string $task_name name of the task, class name, function name, method in ScheduledTask.class
 * @throws \ElkArte\Exceptions\Exception
 * @package ScheduledTasks
 */
function run_this_task($id_task, $task_name)
{
	global $time_start, $modSettings;

	// Let's start logging the task and saying we failed it
	$log_task_id = logTask(0, $id_task);

	$class = '\\ElkArte\\ScheduledTasks\\Tasks\\' . implode('', array_map('ucfirst', explode('_', $task_name)));

	if (class_exists($class))
	{
		$task = new $class();

		$completed = $task->run();
	}
	else
	{
		$completed = false;
	}

	$scheduleTaskImmediate = !empty($modSettings['scheduleTaskImmediate']) ? Util::unserialize($modSettings['scheduleTaskImmediate']) : array();
	// Log that we did it ;)
	if ($completed)
	{
		// Taking care of scheduleTaskImmediate having a maximum of 10 "fast" executions
		if (!empty($scheduleTaskImmediate) && isset($scheduleTaskImmediate[$task_name]))
		{
			$scheduleTaskImmediate[$task_name]++;

			if ($scheduleTaskImmediate[$task_name] > 9)
			{
				removeScheduleTaskImmediate($task_name, false);
			}
			else
			{
				updateSettings(array('scheduleTaskImmediate' => serialize($scheduleTaskImmediate)));
			}
		}

		$total_time = round(microtime(true) - $time_start, 3);

		// If the task ended successfully, then log the proper time taken to complete
		logTask($log_task_id, $id_task, $total_time);
	}
}

/**
 * Retrieve info if there's any next task scheduled and when.
 *
 * @return mixed int|false
 * @throws \ElkArte\Exceptions\Exception
 * @package ScheduledTasks
 */
function nextTime()
{
	$db = database();

	// The next stored timestamp, is there any?
	$request = $db->query('', '
		SELECT 
			next_time
		FROM {db_prefix}scheduled_tasks
		WHERE disabled = {int:not_disabled}
		ORDER BY next_time ASC
		LIMIT 1',
		array(
			'not_disabled' => 0,
		)
	);
	// No new task scheduled?
	if ($request->num_rows() === 0)
	{
		$result = false;
	}
	else
	{
		list ($result) = $request->fetch_row();
	}

	$request->free_result();

	return $result;
}
