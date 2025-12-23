<?php

/**
 * This file concerns itself with scheduled tasks management.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\Languages\Txt;

/**
 * ManageScheduledTasks admin Controller: handles the scheduled task pages
 * which allow one to see, edit and run the systems scheduled tasks
 *
 * @package ScheduledTasks
 */
class ManageScheduledTasks extends AbstractController
{
	/**
	 * Scheduled tasks management dispatcher.
	 *
	 * - This function checks permissions and delegates to the appropriate function
	 * based on the sub-action.
	 * - Everything here requires admin_forum permission.
	 *
	 * @event integrate_sa_manage_scheduled_tasks
	 * @uses ManageScheduledTasks template file
	 * @uses ManageScheduledTasks language file
	 *
	 * @see AbstractController::action_index()
	 */
	public function action_index(): void
	{
		global $context, $txt;

		Txt::load('ManageScheduled');
		theme()->getTemplates()->load('ManageScheduledTasks');

		$subActions = [
			'taskedit' => [$this, 'action_edit', 'permission' => 'admin_forum'],
			'tasklog' => [$this, 'action_log', 'permission' => 'admin_forum'],
			'tasks' => [$this, 'action_tasks', 'permission' => 'admin_forum'],
		];

		// Control those actions
		$action = new Action('manage_scheduled_tasks');

		// We need to find what's the action. Call integrate_sa_manage_scheduled_tasks
		$subAction = $action->initialize($subActions, 'tasks');

		// Page details
		$context['page_title'] = $txt['maintain_info'];
		$context['sub_action'] = $subAction;

		// Now for the lovely tabs. That we all love.
		$context[$context['admin_menu_name']]['object']->prepareTabData([
				'title' => 'scheduled_tasks_title',
				'description' => 'maintain_info',
				'tabs' => [
					'tasks' => [
						'description' => $txt['maintain_tasks_desc'],
					],
					'tasklog' => [
						'description' => $txt['scheduled_log_desc'],
					],
				]]
		);

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * List all the scheduled task in place on the forum.
	 *
	 * @event integrate_autotask_include (depreciated since 1.1)
	 * @event integrate_list_scheduled_tasks
	 * @uses ManageScheduledTasks template, view_scheduled_tasks sub-template
	 */
	public function action_tasks(): void
	{
		global $context, $txt;

		// We'll need to recalculate dates and stuff like that.
		require_once(SUBSDIR . '/ScheduledTasks.subs.php');

		$context['sub_template'] = 'view_scheduled_tasks';
		$context['page_title'] = $txt['maintain_tasks'];

		// Saving changes?
		if (isset($this->_req->post->save, $this->_req->post->enable_task))
		{
			checkSession();

			// Enable and disable as required.
			$enablers = [0];
			$enable = $this->_req->getPost('enable_task', null, []);
			if (!is_array($enable))
			{
				$enable = [$enable];
			}
			foreach ($enable as $id => $enabled)
			{
				if ($enabled)
				{
					$enablers[] = (int) $id;
				}
			}

			// Do the update!
			updateTaskStatus($enablers);

			// Pop along...
			calculateNextTrigger();
		}

		// Want to run any of the tasks?
		if (isset($this->_req->post->run, $this->_req->post->run_task))
		{
			// Let's figure out which ones they want to run.
			$tasks = [];
			foreach ($this->_req->post->run_task as $task => $dummy)
			{
				$tasks[] = (int) $task;
			}

			// Load up the tasks.
			$nextTasks = loadTasks($tasks);

			// Let's get it on!
			ignore_user_abort(true);

			foreach ($nextTasks as $task_id => $taskname)
			{
				run_this_task($task_id, $taskname);
			}

			// Things go as expected?  If not, save the error in session
			if (!empty($context['scheduled_errors']))
			{
				$_SESSION['st_error'] = $context['scheduled_errors'];
			}

			redirectexit('action=admin;area=scheduledtasks;done');
		}

		// Build the list so we can see the tasks
		$listOptions = [
			'id' => 'scheduled_tasks',
			'title' => $txt['maintain_tasks'],
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'scheduledtasks']),
			'get_items' => [
				'function' => fn() => $this->list_getScheduledTasks(),
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => $txt['scheduled_tasks_name'],
						'style' => 'width: 40%;',
					],
					'data' => [
						'sprintf' => [
							'format' => '
								<a class="linkbutton w_icon" href="' . getUrl('admin', ['action' => 'admin', 'area' => 'scheduledtasks', 'sa' => 'taskedit', 'tid' => '%1$d']) . '" title="' . $txt['scheduled_task_edit'] . ' %2$s"><i class="icon i-pencil"></i> %2$s</a><br /><span class="smalltext">%3$s</span>',
							'params' => [
								'id' => false,
								'name' => false,
								'desc' => false,
							],
						],
					],
				],
				'next_due' => [
					'header' => [
						'value' => $txt['scheduled_tasks_next_time'],
					],
					'data' => [
						'db' => 'next_time',
						'class' => 'smalltext',
					],
				],
				'regularity' => [
					'header' => [
						'value' => $txt['scheduled_tasks_regularity'],
					],
					'data' => [
						'db' => 'regularity',
						'class' => 'smalltext',
					],
				],
				'enabled' => [
					'header' => [
						'value' => $txt['scheduled_tasks_enabled'],
						'style' => 'width: 6%;text-align: center;',
					],
					'data' => [
						'sprintf' => [
							'format' => '
								<input type="hidden" name="enable_task[%1$d]" id="task_%1$d" value="0" /><input type="checkbox" name="enable_task[%1$d]" id="task_check_%1$d" %2$s class="input_check" />',
							'params' => [
								'id' => false,
								'checked_state' => false,
							],
						],
						'class' => 'centertext',
					],
				],
				'run_now' => [
					'header' => [
						'value' => $txt['scheduled_tasks_run_now'],
						'style' => 'width: 12%;text-align: center;',
					],
					'data' => [
						'sprintf' => [
							'format' => '
								<input type="checkbox" name="run_task[%1$d]" id="run_task_%1$d" class="input_check" />',
							'params' => [
								'id' => false,
							],
						],
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'scheduledtasks']),
			],
			'additional_rows' => [
				[
					'class' => 'submitbutton',
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="run" value="' . $txt['scheduled_tasks_run_now'] . '" class="right_submit" />
						<input type="submit" name="save" value="' . $txt['scheduled_tasks_save_changes'] . '" class="right_submit" />',
				],
				[
					'position' => 'after_title',
					'value' => $txt['scheduled_tasks_time_offset'],
				],
			],
		];

		createList($listOptions);

		$context['sub_template'] = 'view_scheduled_tasks';
		$context['tasks_were_run'] = $this->_req->hasQuery('done');

		// If we had any errors, place them in context as well
		if (isset($_SESSION['st_error']))
		{
			$context['scheduled_errors'] = $_SESSION['st_error'];
			unset($_SESSION['st_error']);
		}
	}

	/**
	 * Callback function for createList() in action_tasks().
	 *
	 */
	public function list_getScheduledTasks(): array
	{
		return scheduledTasks();
	}

	/**
	 * Function for editing a task.
	 *
	 * @uses ManageScheduledTasks template, edit_scheduled_tasks sub-template
	 */
	public function action_edit(): void
	{
		global $context, $txt;

		// Just set up some lovely context stuff.
		$context[$context['admin_menu_name']]['current_subsection'] = 'tasks';
		$context['sub_template'] = 'edit_scheduled_tasks';
		$context['page_title'] = $txt['scheduled_task_edit'];
		$context['server_time'] = standardTime(time(), false, 'server');

		// We'll need this to calculate the next event.
		require_once(SUBSDIR . '/ScheduledTasks.subs.php');

		// Cleaning...
		if (!$this->_req->hasQuery('tid'))
		{
			throw new Exception('no_access', false);
		}

		$tid = $this->_req->getQuery('tid', 'intval', 0);
		if ($tid === 0)
		{
			throw new Exception('no_access', false);
		}

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();
			validateToken('admin-st');

			// Do we have a valid offset?
			preg_match('~(\d{1,2}):(\d{1,2})~', $this->_req->post->offset, $matches);

			// If a half is empty, then assume zero offset!
			if (!isset($matches[2]) || $matches[2] > 59)
			{
				$matches[2] = 0;
			}

			if (!isset($matches[1]) || $matches[1] > 23)
			{
				$matches[1] = 0;
			}

			// Now the offset is easy; easy-peasy - except we need to offset by a few hours...
			$offset = $matches[1] * 3600 + $matches[2] * 60 - date('Z');

			// The other time bits are simple!
			$interval = max((int) $this->_req->post->regularity, 1);
			$unit = in_array(substr($this->_req->post->unit, 0, 1), ['m', 'h', 'd', 'w']) ? substr($this->_req->post->unit, 0, 1) : 'd';

			// Don't allow one-minute intervals.
			if ($interval === 1 && $unit === 'm')
			{
				$interval = 2;
			}

			// Is it disabled?
			$disabled = isset($this->_req->post->enabled) ? 0 : 1;

			// Do the update!
			updateTask($tid, $disabled, $offset, $interval, $unit);

			// Check the next event.
			calculateNextTrigger($tid, true);

			// Return to the main list.
			redirectexit('action=admin;area=scheduledtasks');
		}

		// Load the task, understand? Que? Que?
		$context['task'] = loadTaskDetails($tid);

		createToken('admin-st');
	}

	/**
	 * Show the log of all tasks that have taken place.
	 *
	 * @uses ManageScheduledTasks language file
	 */
	public function action_log(): void
	{
		global $context, $txt;

		require_once(SUBSDIR . '/ScheduledTasks.subs.php');

		// Let's load the language just in case we are outside the Scheduled area.
		Txt::load('ManageScheduled');

		// Empty the log?
		if (!empty($this->_req->post->removeAll))
		{
			checkSession();
			validateToken('admin-tl');

			emptyTaskLog();
		}

		// Set up the list.
		$listOptions = [
			'id' => 'task_log',
			'items_per_page' => 30,
			'title' => $txt['scheduled_log'],
			'no_items_label' => $txt['scheduled_log_empty'],
			'base_href' => $context['admin_area'] === 'scheduledtasks' ? getUrl('admin', ['action' => 'admin', 'area' => 'scheduledtasks', 'sa' => 'tasklog']) : getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'tasklog']),
			'default_sort_col' => 'date',
			'get_items' => [
				'function' => fn($start, $items_per_page, $sort) => $this->list_getTaskLogEntries($start, $items_per_page, $sort),
			],
			'get_count' => [
				'function' => fn() => $this->list_getNumTaskLogEntries(),
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => $txt['scheduled_tasks_name'],
					],
					'data' => [
						'db' => 'name'
					],
				],
				'date' => [
					'header' => [
						'value' => $txt['scheduled_log_time_run'],
					],
					'data' => [
						'function' => static fn($rowData) => standardTime($rowData['time_run'], true),
					],
					'sort' => [
						'default' => 'lst.id_log DESC',
						'reverse' => 'lst.id_log',
					],
				],
				'time_taken' => [
					'header' => [
						'value' => $txt['scheduled_log_time_taken'],
					],
					'data' => [
						'sprintf' => [
							'format' => $txt['scheduled_log_time_taken_seconds'],
							'params' => [
								'time_taken' => false,
							],
						],
					],
					'sort' => [
						'default' => 'lst.time_taken',
						'reverse' => 'lst.time_taken DESC',
					],
				],
				'task_completed' => [
					'header' => [
						'value' => $txt['scheduled_log_completed'],
					],
					'data' => [
						'function' => static function ($rowData) {
							global $txt;

							return '<i class="icon ' . ($rowData['task_completed'] ? 'i-check' : 'i-fail') . '" title="' . sprintf($txt[$rowData['task_completed'] ? 'maintain_done' : 'maintain_fail'], $rowData['name']) . '" />';
						},
					],
				],
			],
			'form' => [
				'href' => $context['admin_area'] === 'scheduledtasks' ? getUrl('admin', ['action' => 'admin', 'area' => 'scheduledtasks', 'sa' => 'tasklog']) : getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'tasklog']),
				'token' => 'admin-tl',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="removeAll" value="' . $txt['scheduled_log_empty_log'] . '" onclick="return confirm(\'' . $txt['scheduled_log_empty_log_confirm'] . '\');" class="right_submit" />',
				],
				[
					'position' => 'after_title',
					'value' => $txt['scheduled_tasks_time_offset'],
				],
			],
		];

		createToken('admin-tl');
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'task_log';

		// Make it all look tify.
		$context[$context['admin_menu_name']]['current_subsection'] = 'tasklog';
		$context['page_title'] = $txt['scheduled_log'];
	}

	/**
	 * Callback function for createList() in action_log().
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 *
	 * @return array
	 */
	public function list_getTaskLogEntries(int $start, int $items_per_page, string $sort): array
	{
		return getTaskLogEntries($start, $items_per_page, $sort);
	}

	/**
	 * Callback function for createList() in action_log().
	 */
	public function list_getNumTaskLogEntries(): int
	{
		return countTaskLogEntries();
	}
}
