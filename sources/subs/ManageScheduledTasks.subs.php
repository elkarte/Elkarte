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


function loadTasks($tasks)
{
	global $smcFunc;
	
	$request = $smcFunc['db_query']('', '
		SELECT id_task, task
		FROM {db_prefix}scheduled_tasks
		WHERE id_task IN ({array_int:tasks})
		LIMIT ' . count($tasks),
		array(
			'tasks' => $tasks,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$task[$row['id_task']] = $row['task'];
	$smcFunc['db_free_result']($request);

	return $task;
}

function logTask($task_id, $total_time)
{
	global $smcFunc;

	$smcFunc['db_insert']('',
		'{db_prefix}log_scheduled_tasks',
		array('id_task' => 'int', 'time_run' => 'int', 'time_taken' => 'float'),
		array($task_id, time(), $total_time),
		array('id_task')
	);
}

function updateTaskStatus($enablers)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}scheduled_tasks
		SET disabled = CASE WHEN id_task IN ({array_int:id_task_enable}) THEN 0 ELSE 1 END',
		array(
			'id_task_enable' => $enablers,
		)
	);
}

function updateTask($id_task, $disabled, $offset, $interval, $unit)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
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

function loadTaskDetails($id_task)
{
	global $smcFunc, $txt;

	$task = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
		FROM {db_prefix}scheduled_tasks
		WHERE id_task = {int:id_task}',
		array(
			'id_task' => $id_task,
		)
	);

	// Should never, ever, happen!
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_access', false);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$task = array(
			'id' => $row['id_task'],
			'function' => $row['task'],
			'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
			'desc' => isset($txt['scheduled_task_desc_' . $row['task']]) ? $txt['scheduled_task_desc_' . $row['task']] : '',
			'next_time' => $row['disabled'] ? $txt['scheduled_tasks_na'] : timeformat($row['next_time'] == 0 ? time() : $row['next_time'], true, 'server'),
			'disabled' => $row['disabled'],
			'offset' => $row['time_offset'],
			'regularity' => $row['time_regularity'],
			'offset_formatted' => date('H:i', $row['time_offset']),
			'unit' => $row['time_unit'],
		);
	}
	$smcFunc['db_free_result']($request);

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
	global $smcFunc, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT id_task, next_time, time_offset, time_regularity, time_unit, disabled, task
		FROM {db_prefix}scheduled_tasks',
		array(
		)
	);
	$known_tasks = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Find the next for regularity - don't offset as it's always server time!
		$offset = sprintf($txt['scheduled_task_reg_starting'], date('H:i', $row['time_offset']));
		$repeating = sprintf($txt['scheduled_task_reg_repeating'], $row['time_regularity'], $txt['scheduled_task_reg_unit_' . $row['time_unit']]);

		$known_tasks[] = array(
			'id' => $row['id_task'],
			'function' => $row['task'],
			'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
			'desc' => isset($txt['scheduled_task_desc_' . $row['task']]) ? $txt['scheduled_task_desc_' . $row['task']] : '',
			'next_time' => $row['disabled'] ? $txt['scheduled_tasks_na'] : timeformat(($row['next_time'] == 0 ? time() : $row['next_time']), true, 'server'),
			'disabled' => $row['disabled'],
			'checked_state' => $row['disabled'] ? '' : 'checked="checked"',
			'regularity' => $offset . ', ' . $repeating,
		);
	}
	$smcFunc['db_free_result']($request);

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
	global $smcFunc, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT lst.id_log, lst.id_task, lst.time_run, lst.time_taken, st.task
		FROM {db_prefix}log_scheduled_tasks AS lst
			INNER JOIN {db_prefix}scheduled_tasks AS st ON (st.id_task = lst.id_task)
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
		)
	);
	$log_entries = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$log_entries[] = array(
			'id' => $row['id_log'],
			'name' => isset($txt['scheduled_task_' . $row['task']]) ? $txt['scheduled_task_' . $row['task']] : $row['task'],
			'time_run' => $row['time_run'],
			'time_taken' => $row['time_taken'],
		);
	$smcFunc['db_free_result']($request);

	return $log_entries;
}

/**
 * Callback function for createList() in action_log().
 */
function list_getNumaction_logEntries()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_scheduled_tasks',
		array(
		)
	);
	list ($num_entries) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_entries;
}