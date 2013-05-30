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
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Loads a basic tasks list.
 *
 * @param array $tasks
 * @return array
 */
function loadTasks($tasks)
{
	$db = database();
	
	$request = $db->query('', '
		SELECT id_task, task
		FROM {db_prefix}scheduled_tasks
		WHERE id_task IN ({array_int:tasks})
		LIMIT ' . count($tasks),
		array(
			'tasks' => $tasks,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$task[$row['id_task']] = $row['task'];
	$db->free_result($request);

	return $task;
}

/**
 * Logs a finished task.
 *
 * @param int $task_id
 * @param int $total_time
 */
function logTask($task_id, $total_time)
{
	$db = database();

	$db->insert('',
		'{db_prefix}log_scheduled_tasks',
		array('id_task' => 'int', 'time_run' => 'int', 'time_taken' => 'float'),
		array($task_id, time(), $total_time),
		array('id_task')
	);
}

/**
 * Sets the tasks status to enabled / disabled
 *
 * @param array $enablers
 */
function updateTaskStatus($enablers)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}scheduled_tasks
		SET disabled = CASE WHEN id_task IN ({array_int:id_task_enable}) THEN 0 ELSE 1 END',
		array(
			'id_task_enable' => $enablers,
		)
	);
}

/**
 * Update the properties of a scheduled task.
 *
 * @param int $id_task
 * @param int $disabled
 * @param int $offset
 * @param int $interval
 * @param string $unit
 */
function updateTask($id_task, $disabled, $offset, $interval, $unit)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}scheduled_tasks
		SET disabled = {int:disabled}, time_offset = {int:time_offset}, time_unit = {string:time_unit},
			time_regularity = {int:time_regularity}
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
 * @return array
 */
function loadTaskDetails($id_task)
{
	global $txt;

	$db = database();

	$task = array();

	$request = $db->query('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
		FROM {db_prefix}scheduled_tasks
		WHERE id_task = {int:id_task}',
		array(
			'id_task' => $id_task,
		)
	);

	// Should never, ever, happen!
	if ($db->num_rows($request) == 0)
		fatal_lang_error('no_access', false);

	while ($row = $db->fetch_assoc($request))
	{
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
	$db->free_result($request);

	return $task;
}

/**
 * Callback function for createList() in ScheduledTasks().
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 */
function list_getScheduledTasks()
{
	global $txt;

	$db = database();

	$request = $db->query('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
		FROM {db_prefix}scheduled_tasks',
		array(
		)
	);
	$known_tasks = array();
	while ($row = $db->fetch_assoc($request))
	{
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
	$db->free_result($request);

	return $known_tasks;
}

/**
 * Callback function for createList() in action_log().
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 */
function list_getTaskLogEntries($start, $items_per_page, $sort)
{
	global $txt;

	$db = database();

	$request = $db->query('', '
		SELECT lst.id_log, lst.id_task, lst.time_run, lst.time_taken, st.task
		FROM {db_prefix}log_scheduled_tasks AS lst
			INNER JOIN {db_prefix}scheduled_tasks AS st ON (st.id_task = lst.id_task)
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
		)
	);
	$log_entries = array();
	while ($row = $db->fetch_assoc($request))
		$log_entries[] = array(
			'id' => $row['id_log'],
			'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
			'time_run' => $row['time_run'],
			'time_taken' => $row['time_taken'],
		);
	$db->free_result($request);

	return $log_entries;
}

/**
 * Callback function for createList() in action_log().
 */
function list_getNumaction_logEntries()
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_scheduled_tasks',
		array(
		)
	);
	list ($num_entries) = $db->fetch_row($request);
	$db->free_result($request);

	return $num_entries;
}