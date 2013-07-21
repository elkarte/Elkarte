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
 * This file concerns itself with scheduled tasks management.
 *
 */

if (!defined('ELK'))
	die('No access...');

class ManageScheduledTasks_Controller extends Action_Controller
{
	/**
	 * Scheduled tasks management dispatcher.
	 * This function checks permissions and delegates to
	 *  the appropriate function based on the sub-action.
	 * Everything here requires admin_forum permission.
	 *
	 * @uses ManageScheduledTasks template file
	 * @uses ManageScheduledTasks language file
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		isAllowedTo('admin_forum');

		loadLanguage('ManageScheduledTasks');
		loadTemplate('ManageScheduledTasks');

		$subActions = array(
			'taskedit' => array($this, 'action_edit'),
			'tasklog' => array($this, 'action_log'),
			'tasks' => array($this, 'action_tasks'),
		);

		call_integration_hook('integrate_manage_scheduled_tasks', array(&$subActions));

		// We need to find what's the action.
		if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
			$subAction = $_REQUEST['sa'];
		else
			$subAction = 'tasks';

		// Set up action/subaction stuff.
		$action = new Action();
		$action->initialize($subActions, 'tasks');

		// Now for the lovely tabs. That we all love.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['scheduled_tasks_title'],
			'help' => '',
			'description' => $txt['maintain_info'],
			'tabs' => array(
				'tasks' => array(
					'description' => $txt['maintain_tasks_desc'],
				),
				'tasklog' => array(
					'description' => $txt['scheduled_log_desc'],
				),
			),
		);
		// @todo is this useless?
		$context['sub_action'] = $subAction;

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * List all the scheduled task in place on the forum.
	 *
	 * @uses ManageScheduledTasks template, view_scheduled_tasks sub-template
	 */
	public function action_tasks()
	{
		global $context, $txt, $scripturl;

		require_once(SUBSDIR . '/ManageScheduledTasks.subs.php');
		// Mama, setup the template first - cause it's like the most important bit, like pickle in a sandwich.
		// ... ironically I don't like pickle. </grudge>
		$context['sub_template'] = 'view_scheduled_tasks';
		$context['page_title'] = $txt['maintain_tasks'];

		// Saving changes?
		if (isset($_REQUEST['save']) && isset($_POST['enable_task']))
		{
			checkSession();

			// We'll recalculate the dates at the end!
			require_once(SUBSDIR . '/ScheduledTasks.subs.php');

			// Enable and disable as required.
			$enablers = array(0);
			foreach ($_POST['enable_task'] as $id => $enabled)
				if ($enabled)
					$enablers[] = (int) $id;

			// Do the update!
			updateTaskStatus($enablers);

			// Pop along...
			calculateNextTrigger();
		}

		// Want to run any of the tasks?
		if (isset($_REQUEST['run']) && isset($_POST['run_task']))
		{
			// Lets figure out which ones they want to run.
			$tasks = array();
			foreach ($_POST['run_task'] as $task => $dummy)
				$tasks[] = (int) $task;

			// Load up the tasks.

			$nextTasks = loadTasks($tasks);

			// Lets get it on!
			require_once(SOURCEDIR . '/ScheduledTasks.php');
			ignore_user_abort(true);
			foreach ($nextTasks as $task_id => $taskname)
			{
				$start_time = microtime(true);
				// The functions got to exist for us to use it.
				if (!function_exists('scheduled_' . $taskname))
					continue;

				// Try to stop a timeout, this would be bad...
				@set_time_limit(300);
				if (function_exists('apache_reset_timeout'))
					@apache_reset_timeout();

				// Do the task...
				$completed = call_user_func('scheduled_' . $taskname);

				// Log that we did it ;)
				if ($completed)
				{
					$total_time = round(microtime(true) - $start_time, 3);
					logTask($task_id, $total_time);
				}
			}
			redirectexit('action=admin;area=scheduledtasks;done');
		}

		$listOptions = array(
			'id' => 'scheduled_tasks',
			'title' => $txt['maintain_tasks'],
			'base_href' => $scripturl . '?action=admin;area=scheduledtasks',
			'get_items' => array(
				'function' => 'list_getScheduledTasks',
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['scheduled_tasks_name'],
						'style' => 'width: 40%;',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '
								<a href="' . $scripturl . '?action=admin;area=scheduledtasks;sa=taskedit;tid=%1$d">%2$s</a><br /><span class="smalltext">%3$s</span>',
							'params' => array(
								'id' => false,
								'name' => false,
								'desc' => false,
							),
						),
					),
				),
				'next_due' => array(
					'header' => array(
						'value' => $txt['scheduled_tasks_next_time'],
					),
					'data' => array(
						'db' => 'next_time',
						'class' => 'smalltext',
					),
				),
				'regularity' => array(
					'header' => array(
						'value' => $txt['scheduled_tasks_regularity'],
					),
					'data' => array(
						'db' => 'regularity',
						'class' => 'smalltext',
					),
				),
				'enabled' => array(
					'header' => array(
						'value' => $txt['scheduled_tasks_enabled'],
						'style' => 'width: 6%;',
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' =>
								'<input type="hidden" name="enable_task[%1$d]" id="task_%1$d" value="0" /><input type="checkbox" name="enable_task[%1$d]" id="task_check_%1$d" %2$s class="input_check" />',
							'params' => array(
								'id' => false,
								'checked_state' => false,
							),
						),
						'class' => 'centertext',
					),
				),
				'run_now' => array(
					'header' => array(
						'value' => $txt['scheduled_tasks_run_now'],
						'style' => 'width: 12%;',
						'class' => 'centertext',
					),
					'data' => array(
						'sprintf' => array(
							'format' =>
								'<input type="checkbox" name="run_task[%1$d]" id="run_task_%1$d" class="input_check" />',
							'params' => array(
								'id' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=scheduledtasks',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="save" value="' . $txt['scheduled_tasks_save_changes'] . '" class="button_submit" />
						<input type="submit" name="run" value="' . $txt['scheduled_tasks_run_now'] . '" class="button_submit" />',
				),
				array(
					'position' => 'after_title',
					'value' => $txt['scheduled_tasks_time_offset'],
					'class' => 'windowbg2',
				),
			),
		);

		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);

		$context['sub_template'] = 'view_scheduled_tasks';

		$context['tasks_were_run'] = isset($_GET['done']);
	}

	/**
	 * Function for editing a task.
	 *
	 * @uses ManageScheduledTasks template, edit_scheduled_tasks sub-template
	 */
	public function action_edit()
	{
		global $context, $txt;

		// Just set up some lovely context stuff.
		$context[$context['admin_menu_name']]['current_subsection'] = 'tasks';
		$context['sub_template'] = 'edit_scheduled_tasks';
		$context['page_title'] = $txt['scheduled_task_edit'];
		$context['server_time'] = standardTime(time(), false, 'server');

		require_once(SUBSDIR . '/ManageScheduledTasks.subs.php');
		// Cleaning...
		if (!isset($_GET['tid']))
			fatal_lang_error('no_access', false);
		$_GET['tid'] = (int) $_GET['tid'];

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();
			validateToken('admin-st');

			// We'll need this for calculating the next event.
			require_once(SUBSDIR . '/ScheduledTasks.subs.php');

			// Do we have a valid offset?
			preg_match('~(\d{1,2}):(\d{1,2})~', $_POST['offset'], $matches);

			// If a half is empty then assume zero offset!
			if (!isset($matches[2]) || $matches[2] > 59)
				$matches[2] = 0;
			if (!isset($matches[1]) || $matches[1] > 23)
				$matches[1] = 0;

			// Now the offset is easy; easy peasy - except we need to offset by a few hours...
			$offset = $matches[1] * 3600 + $matches[2] * 60 - date('Z');

			// The other time bits are simple!
			$interval = max((int) $_POST['regularity'], 1);
			$unit = in_array(substr($_POST['unit'], 0, 1), array('m', 'h', 'd', 'w')) ? substr($_POST['unit'], 0, 1) : 'd';

			// Don't allow one minute intervals.
			if ($interval == 1 && $unit == 'm')
				$interval = 2;

			// Is it disabled?
			$disabled = !isset($_POST['enabled']) ? 1 : 0;

			// Do the update!
			$_GET['tid'] = (int) $_GET['tid'];
			updateTask($_GET['tid'], $disabled, $offset, $interval, $unit);

			// Check the next event.
			calculateNextTrigger($_GET['tid'], true);

			// Return to the main list.
			redirectexit('action=admin;area=scheduledtasks');
		}

		// Load the task, understand? Que? Que?
		$_GET['tid'] = (int) $_GET['tid'];
		$context['task'] = loadTaskDetails($_GET['tid']);

		createToken('admin-st');
	}

	/**
	 * Show the log of all tasks that have taken place.
	 *
	 * @uses ManageScheduledTasks language file
	 */
	function action_log()
	{
		global $scripturl, $context, $txt;

		$db = database();

		require_once(SUBSDIR . '/ManageScheduledTasks.subs.php');
		// Lets load the language just incase we are outside the Scheduled area.
		loadLanguage('ManageScheduledTasks');

		// Empty the log?
		if (!empty($_POST['removeAll']))
		{
			checkSession();
			validateToken('admin-tl');

			$db->query('truncate_table', '
				TRUNCATE {db_prefix}log_scheduled_tasks',
				array(
				)
			);
		}

		// Setup the list.
		$listOptions = array(
			'id' => 'task_log',
			'items_per_page' => 30,
			'title' => $txt['scheduled_log'],
			'no_items_label' => $txt['scheduled_log_empty'],
			'base_href' => $context['admin_area'] == 'scheduledtasks' ? $scripturl . '?action=admin;area=scheduledtasks;sa=tasklog' : $scripturl . '?action=admin;area=logs;sa=tasklog',
			'default_sort_col' => 'date',
			'get_items' => array(
				'function' => 'list_getTaskLogEntries',
			),
			'get_count' => array(
				'function' => 'list_getNumaction_logEntries(',
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['scheduled_tasks_name'],
					),
					'data' => array(
						'db' => 'name'
					),
				),
				'date' => array(
					'header' => array(
						'value' => $txt['scheduled_log_time_run'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							return standardTime($rowData[\'time_run\'], true);
						'),
					),
					'sort' => array(
						'default' => 'lst.id_log DESC',
						'reverse' => 'lst.id_log',
					),
				),
				'time_taken' => array(
					'header' => array(
						'value' => $txt['scheduled_log_time_taken'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => $txt['scheduled_log_time_taken_seconds'],
							'params' => array(
								'time_taken' => false,
							),
						),
					),
					'sort' => array(
						'default' => 'lst.time_taken',
						'reverse' => 'lst.time_taken DESC',
					),
				),
			),
			'form' => array(
				'href' => $context['admin_area'] == 'scheduledtasks' ? $scripturl . '?action=admin;area=scheduledtasks;sa=tasklog' : $scripturl . '?action=admin;area=logs;sa=tasklog',
				'token' => 'admin-tl',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="removeAll" value="' . $txt['scheduled_log_empty_log'] . '" onclick="return confirm(\'' . $txt['scheduled_log_empty_log_confirm'] . '\');" class="button_submit" />',
				),
				array(
					'position' => 'after_title',
					'value' => $txt['scheduled_tasks_time_offset'],
					'class' => 'windowbg2',
				),
			),
		);

		createToken('admin-tl');

		require_once(SUBSDIR . '/List.subs.php');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'task_log';

		// Make it all look tify.
		$context[$context['admin_menu_name']]['current_subsection'] = 'tasklog';
		$context['page_title'] = $txt['scheduled_log'];
	}
}