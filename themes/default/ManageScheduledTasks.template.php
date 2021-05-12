<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

/**
 * Template for listing all scheduled tasks.
 */
function template_view_scheduled_tasks()
{
	global $context, $txt;

	// We completed some tasks?
	if (!empty($context['tasks_were_run']))
	{
		if (empty($context['scheduled_errors']))
		{
			echo '
	<div id="task_completed" class="successbox">
		', $txt['scheduled_tasks_were_run'], '
	</div>';
		}
		else
		{
			echo '
	<div id="errors" class="errorbox">
		', $txt['scheduled_tasks_were_run_errors'], '<br />';

			foreach ($context['scheduled_errors'] as $task => $errors)
			{
				echo
				isset($txt['scheduled_task_' . $task]) ? $txt['scheduled_task_' . $task] : $task, '
				<ul>
					<li class="listlevel1">', implode('</li><li class="listlevel1">', $errors), '</li>
				</ul>';
			}

			echo '
	</div>';
		}
	}

	template_show_list('scheduled_tasks');
}

/**
 * A template for, you guessed it, editing a task!
 */
function template_edit_scheduled_tasks()
{
	global $context, $txt;

	// Starts off with general maintenance procedures.
	echo '
	<div id="admincenter">
		<form action="', getUrl('admin', ['action' => 'admin', 'area' => 'scheduledtasks', 'sa' => 'taskedit', 'save', 'tid' => $context['task']['id']]), '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['scheduled_task_edit'], '</h2>
			<div class="information">
				<em>', sprintf($txt['scheduled_task_time_offset'], $context['server_time']), ' </em>
			</div>
			<div class="content">
				<dl class="settings">
					<dt>
						<label>', $txt['scheduled_tasks_name'], ':</label>
					</dt>
					<dd>
						', $context['task']['name'], '<br />
						<span class="smalltext">', $context['task']['desc'], '</span>
					</dd>
					<dt>
						<label for="regularity">', $txt['scheduled_task_edit_interval'], ':</label>
					</dt>
					<dd>
						', $txt['scheduled_task_edit_repeat'], '
						<input type="text" name="regularity" id="regularity" value="', empty($context['task']['regularity']) ? 1 : $context['task']['regularity'], '" onchange="if (this.value < 1) this.value = 1;" size="2" maxlength="2" class="input_text" />
						<select name="unit">
							<option value="0">', $txt['scheduled_task_edit_pick_unit'], '</option>
							<option value="0" disabled="disabled">', str_repeat('&#8212;', strlen($txt['scheduled_task_edit_pick_unit'])), '</option>
							<option value="m" ', empty($context['task']['unit']) || $context['task']['unit'] == 'm' ? 'selected="selected"' : '', '>', $txt['scheduled_task_reg_unit_m'], '</option>
							<option value="h" ', $context['task']['unit'] == 'h' ? 'selected="selected"' : '', '>', $txt['scheduled_task_reg_unit_h'], '</option>
							<option value="d" ', $context['task']['unit'] == 'd' ? 'selected="selected"' : '', '>', $txt['scheduled_task_reg_unit_d'], '</option>
							<option value="w" ', $context['task']['unit'] == 'w' ? 'selected="selected"' : '', '>', $txt['scheduled_task_reg_unit_w'], '</option>
						</select>
					</dd>
					<dt>
						<label for="start_time">', $txt['scheduled_task_edit_start_time'], ':</label><br />
						<span class="smalltext">', $txt['scheduled_task_edit_start_time_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="offset" id="start_time" value="', $context['task']['offset_formatted'], '" size="6" maxlength="5" class="input_text" />
					</dd>
					<dt>
						<label for="enabled">', $txt['scheduled_tasks_enabled'], ':</label>
					</dt>
					<dd>
						<input type="checkbox" name="enabled" id="enabled" ', !$context['task']['disabled'] ? 'checked="checked"' : '', ' />
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-st_token_var'], '" value="', $context['admin-st_token'], '" />
					<input type="submit" name="save" value="', $txt['scheduled_tasks_save_changes'], '" />
				</div>
			</div>
		</form>
	</div>';
}
