<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Calculate the next time the passed tasks should be triggered.
 *
 * @param array $tasks = array() the tasks
 * @param boolean $forceUpdate
 */
function calculateNextTrigger($tasks = array(), $forceUpdate = false)
{
	global $modSettings;

	$db = database();

	$task_query = '';
	if (!is_array($tasks))
		$tasks = array($tasks);

	// Actually have something passed?
	if (!empty($tasks))
	{
		if (!isset($tasks[0]) || is_numeric($tasks[0]))
			$task_query = ' AND id_task IN ({array_int:tasks})';
		else
			$task_query = ' AND task IN ({array_string:tasks})';
	}
	$nextTaskTime = empty($tasks) ? time() + 86400 : $modSettings['next_task_time'];

	// Get the critical info for the tasks.
	$request = $db->query('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit
		FROM {db_prefix}scheduled_tasks
		WHERE disabled = {int:no_disabled}
			' . $task_query,
		array(
			'no_disabled' => 0,
			'tasks' => $tasks,
		)
	);
	$tasks = array();
	while ($row = $db->fetch_assoc($request))
	{
		$next_time = next_time($row['time_regularity'], $row['time_unit'], $row['time_offset']);

		// Only bother moving the task if it's out of place or we're forcing it!
		if ($forceUpdate || $next_time < $row['next_time'] || $row['next_time'] < time())
			$tasks[$row['id_task']] = $next_time;
		else
			$next_time = $row['next_time'];

		// If this is sooner than the current next task, make this the next task.
		if ($next_time < $nextTaskTime)
			$nextTaskTime = $next_time;
	}
	$db->free_result($request);

	// Now make the changes!
	foreach ($tasks as $id => $time)
		$db->query('', '
			UPDATE {db_prefix}scheduled_tasks
			SET next_time = {int:next_time}
			WHERE id_task = {int:id_task}',
			array(
				'next_time' => $time,
				'id_task' => $id,
			)
		);

	// If the next task is now different update.
	if ($modSettings['next_task_time'] != $nextTaskTime)
		updateSettings(array('next_task_time' => $nextTaskTime));
}

/**
 * Returns a time stamp of the next instance of these time parameters.
 *
 * @param int $regularity
 * @param string $unit
 * @param int $offset
 * @return int
 */
function next_time($regularity, $unit, $offset)
{
	// Just in case!
	if ($regularity == 0)
		$regularity = 2;

	$curMin = date('i', time());
	$next_time = 9999999999;

	// If the unit is minutes only check regularity in minutes.
	if ($unit == 'm')
	{
		$off = date('i', $offset);

		// If it's now just pretend it ain't,
		if ($off == $curMin)
			$next_time = time() + $regularity;
		else
		{
			// Make sure that the offset is always in the past.
			$off = $off > $curMin ? $off - 60 : $off;

			while ($off <= $curMin)
				$off += $regularity;

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
			$applyOffset = 86400;
		// Otherwise a week.
		if ($unit == 'w')
			$applyOffset = 604800;

		$applyOffset *= $regularity;

		// Just add on the offset.
		while ($next_time <= time())
		{
			$next_time += $applyOffset;
		}
	}

	return $next_time;
}