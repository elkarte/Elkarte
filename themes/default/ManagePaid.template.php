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
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * The template for adding or editing a subscription.
 */
function template_modify_subscription()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=paidsubscribe;sa=modify;sid=', $context['sub_id'], '" method="post">
			<h2 class="category_header">', $txt['paid_' . $context['action_type'] . '_subscription'], '</h2>';

	if (!empty($context['disable_groups']))
		echo '
			<div class="warningbox">
				', $txt['paid_mod_edit_note'], '
			</div>
			';

	echo '
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="name">', $txt['paid_mod_name'], '</label>:
					</dt>
					<dd>
						<input type="text" id="name" name="name" value="', $context['sub']['name'], '" size="30" class="input_text" />
					</dd>
					<dt>
						<label for="desc">', $txt['paid_mod_desc'], '</label>:
					</dt>
					<dd>
						<textarea id="desc" name="desc" rows="3" cols="40">', $context['sub']['desc'], '</textarea>
					</dd>
					<dt>
						<label for="repeatable_check">', $txt['paid_mod_repeatable'], '</label>:
					</dt>
					<dd>
						<input type="checkbox" name="repeatable" id="repeatable_check"', empty($context['sub']['repeatable']) ? '' : ' checked="checked"', ' />
					</dd>
					<dt>
						<label for="activated_check">', $txt['paid_mod_active'], '</label>:<br /><span class="smalltext">', $txt['paid_mod_active_desc'], '</span>
					</dt>
					<dd>
						<input type="checkbox" name="active" id="activated_check"', empty($context['sub']['active']) ? '' : ' checked="checked"', ' />
					</dd>
				</dl>
				<hr />
				<dl class="settings">
					<dt>
						<label for="prim_group">', $txt['paid_mod_prim_group'], '</label>:<br /><span class="smalltext">', $txt['paid_mod_prim_group_desc'], '</span>
					</dt>
					<dd>
						<select id="prim_group" name="prim_group" ', !empty($context['disable_groups']) ? 'disabled="disabled"' : '', '>
							<option value="0" ', $context['sub']['prim_group'] == 0 ? 'selected="selected"' : '', '>', $txt['paid_mod_no_group'], '</option>';

	// Put each group into the box.
	foreach ($context['groups'] as $groups)
		echo '
							<option value="', $groups['id'], '" ', $context['sub']['prim_group'] == $groups['id'] ? 'selected="selected"' : '', '>', $groups['name'], '</option>';

	echo '
						</select>
					</dd>
					<dt>
						<label>', $txt['paid_mod_add_groups'], ':</label><br /><span class="smalltext">', $txt['paid_mod_add_groups_desc'], '</span>
					</dt>
					<dd>';

	// Put a checkbox in for each group
	foreach ($context['groups'] as $groups)
		echo '
						<input type="checkbox" id="addgroup_', $groups['id'], '" name="addgroup[', $groups['id'], ']"', in_array($groups['id'], $context['sub']['add_groups']) ? ' checked="checked"' : '', ' ', !empty($context['disable_groups']) ? ' disabled="disabled"' : '', ' />&nbsp;<label for="addgroup_', $groups['id'], '" class="smalltext">', $groups['name'], '</label><br />';

	echo '
					</dd>
					<dt>
						<label for="reminder">', $txt['paid_mod_reminder'], '</label>:<br /><span class="smalltext">', $txt['paid_mod_reminder_desc'], '</span>
					</dt>
					<dd>
						<input type="text" id="reminder" name="reminder" value="', $context['sub']['reminder'], '" size="6" class="input_text" />
					</dd>
					<dt>
						<label for="emailcomplete">', $txt['paid_mod_email'], '</label>:<br /><span class="smalltext">', $txt['paid_mod_email_desc'], '</span>
					</dt>
					<dd>
						<textarea id="emailcomplete" name="emailcomplete" rows="6" cols="40">', $context['sub']['email_complete'], '</textarea>
					</dd>
				</dl>
				<hr />
				<input type="radio" name="duration_type" id="duration_type_fixed" value="fixed" ', empty($context['sub']['duration']) || $context['sub']['duration'] == 'fixed' ? 'checked="checked"' : '', ' onclick="toggleDuration(\'fixed\');" />
				<strong>', $txt['paid_mod_fixed_price'], '</strong>
				<br />
				<div id="fixed_area" ', empty($context['sub']['duration']) || $context['sub']['duration'] == 'fixed' ? '' : 'class="hide"', '>
					<fieldset>
						<dl class="settings">
							<dt>
								<label for="cost">', $txt['paid_cost'], '</label> (', str_replace('%1.2f', '', $modSettings['paid_currency_symbol']), '):
							</dt>
							<dd>
								<input type="text" id="cost" name="cost" value="', empty($context['sub']['cost']['fixed']) ? '0' : $context['sub']['cost']['fixed'], '" size="4" class="input_text" />
							</dd>
							<dt>
								<label for="span_value">', $txt['paid_mod_span'], '</label>:
							</dt>
							<dd>
								<input type="text" id="span_value" name="span_value" value="', $context['sub']['span']['value'], '" size="4" class="input_text" />
								<select name="span_unit">
									<option value="D" ', $context['sub']['span']['unit'] == 'D' ? 'selected="selected"' : '', '>', $txt['paid_mod_span_days'], '</option>
									<option value="W" ', $context['sub']['span']['unit'] == 'W' ? 'selected="selected"' : '', '>', $txt['paid_mod_span_weeks'], '</option>
									<option value="M" ', $context['sub']['span']['unit'] == 'M' ? 'selected="selected"' : '', '>', $txt['paid_mod_span_months'], '</option>
									<option value="Y" ', $context['sub']['span']['unit'] == 'Y' ? 'selected="selected"' : '', '>', $txt['paid_mod_span_years'], '</option>
								</select>
							</dd>
						</dl>
					</fieldset>
				</div>
				<input type="radio" name="duration_type" id="duration_type_flexible" value="flexible" ', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'flexible' ? 'checked="checked"' : '', ' onclick="toggleDuration(\'flexible\');" />
				<strong>', $txt['paid_mod_flexible_price'], '</strong>
				<br />
				<div id="flexible_area" ', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'flexible' ? '' : 'class="hide"', '>
					<fieldset>';

	/** Removed until implemented
	  if (!empty($sdflsdhglsdjgs))
	  echo '
	  <dl class="settings">
	  <dt>
	  <label for="allow_partial_check">', $txt['paid_mod_allow_partial'], '</label>:<br /><span class="smalltext">', $txt['paid_mod_allow_partial_desc'], '</span>
	  </dt>
	  <dd>
	  <input type="checkbox" name="allow_partial" id="allow_partial_check"', empty($context['sub']['allow_partial']) ? '' : ' checked="checked"', ' />
	  </dd>
	  </dl>';
	 */
	echo '
						<div class="information">
							<strong>', $txt['paid_mod_price_breakdown'], '</strong><br />
							', $txt['paid_mod_price_breakdown_desc'], '
						</div>
						<dl class="settings">
							<dt>
								<label>', $txt['paid_duration'], '</label>
							</dt>
							<dd>
								<strong>', $txt['paid_cost'], ' (', preg_replace('~%[df\.\d]+~', '', $modSettings['paid_currency_symbol']), ')</strong>
							</dd>
							<dt>
								<label for="cost_day">', $txt['paid_per_day'], '</label>:
							</dt>
							<dd>
								<input type="text" id="cost_day" name="cost_day" value="', empty($context['sub']['cost']['day']) ? '0' : $context['sub']['cost']['day'], '" size="5" class="input_text" />
							</dd>
							<dt>
								<label for="cost_week">', $txt['paid_per_week'], '</label>:
							</dt>
							<dd>
								<input type="text" id="cost_week" name="cost_week" value="', empty($context['sub']['cost']['week']) ? '0' : $context['sub']['cost']['week'], '" size="5" class="input_text" />
							</dd>
							<dt>
								<label for="cost_month">', $txt['paid_per_month'], '</label>:
							</dt>
							<dd>
								<input type="text" id="cost_month" name="cost_month" value="', empty($context['sub']['cost']['month']) ? '0' : $context['sub']['cost']['month'], '" size="5" class="input_text" />
							</dd>
							<dt>
								<label for="cost_year">', $txt['paid_per_year'], '</label>:
							</dt>
							<dd>
								<input type="text" id="cost_year" name="cost_year" value="', empty($context['sub']['cost']['year']) ? '0' : $context['sub']['cost']['year'], '" size="5" class="input_text" />
							</dd>
						</dl>
					</fieldset>
				</div>
				<div class="submitbutton">
					<input type="submit" name="save" value="', $txt['paid_settings_save'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-pms_token_var'], '" value="', $context['admin-pms_token'], '" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Template to delete a paid subscription
 */
function template_delete_subscription()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=paidsubscribe;sa=modify;sid=', $context['sub_id'], ';delete" method="post">
			<h2 class="category_header">', $txt['paid_delete_subscription'], '</h2>
				<div class="content">
				<p class="warningbox">', $txt['paid_mod_delete_warning'], '</p>
				<div class="submitbutton">
					<input type="submit" name="delete_confirm" value="', $txt['paid_delete_subscription'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-pmsd_token_var'], '" value="', $context['admin-pmsd_token'], '" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Add or edit an existing subscriber.
 */
function template_modify_user_subscription()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;sid=', $context['sub_id'], ';lid=', $context['log_id'], '" method="post">
			<h2 class="category_header">
				', $txt['paid_' . $context['action_type'] . '_subscription'], ' - ', $context['current_subscription']['name'], '
				', empty($context['sub']['username']) ? '' : ' (' . $txt['user'] . ': ' . $context['sub']['username'] . ')', '
			</h2>
			<div class="content">
				<dl class="settings">';

	// Do we need a username?
	if ($context['action_type'] == 'add')
		echo '
					<dt>
						<label for="name_control">', $txt['paid_username'], ':</label><br />
						<span class="smalltext">', $txt['one_username'], '</span>
					</dt>
					<dd>
						<input type="text" name="name" id="name_control" value="', $context['sub']['username'], '" size="30" class="input_text" />
					</dd>';

	echo '
					<dt>
						<label for="status">', $txt['paid_status'], ':</label>
					</dt>
					<dd>
						<select id="status" name="status">
							<option value="0" ', $context['sub']['status'] == 0 ? 'selected="selected"' : '', '>', $txt['paid_finished'], '</option>
							<option value="1" ', $context['sub']['status'] == 1 ? 'selected="selected"' : '', '>', $txt['paid_active'], '</option>
						</select>
					</dd>
				</dl>
				<fieldset>
					<legend>', $txt['start_date_and_time'], '</legend>
					<select name="year" id="year" onchange="generateDays();">';

	// Show a list of all the years we allow...
	for ($year = 2010; $year <= 2030; $year++)
		echo '
						<option value="', $year, '"', $year == $context['sub']['start']['year'] ? ' selected="selected"' : '', '>', $year, '</option>';

	echo '
					</select>&nbsp;
					', (isset($txt['calendar_month']) ? $txt['calendar_month'] : $txt['calendar_month']), '&nbsp;
					<select name="month" id="month" onchange="generateDays();">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
						<option value="', $month, '"', $month == $context['sub']['start']['month'] ? ' selected="selected"' : '', '>', $txt['months'][$month], '</option>';

	echo '
					</select>&nbsp;
					', (isset($txt['calendar_day']) ? $txt['calendar_day'] : $txt['calendar_day']), '&nbsp;
					<select name="day" id="day">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['sub']['start']['last_day']; $day++)
		echo '
						<option value="', $day, '"', $day == $context['sub']['start']['day'] ? ' selected="selected"' : '', '>', $day, '</option>';

	echo '
					</select>
					<label for="hour">', $txt['hour'], '</label>: <input type="text" id="hour" name="hour" value="', $context['sub']['start']['hour'], '" size="2" class="input_text" />
					<label for="minute">', $txt['minute'], '</label>: <input type="text" id="minute" name="minute" value="', $context['sub']['start']['min'], '" size="2" class="input_text" />
				</fieldset>
				<fieldset>
					<legend>', $txt['end_date_and_time'], '</legend>
					<label for="yearend">', $txt['calendar_year'], '</label>&nbsp;
					<select name="yearend" id="yearend" onchange="generateDays(\'end\');">';

	// Show a list of all the years we allow...
	for ($year = 2010; $year <= 2030; $year++)
		echo '
						<option value="', $year, '"', $year == $context['sub']['end']['year'] ? ' selected="selected"' : '', '>', $year, '</option>';

	echo '
					</select>&nbsp;
					<label for="monthend">', $txt['calendar_month'], '</label>&nbsp;
					<select name="monthend" id="monthend" onchange="generateDays(\'end\');">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
						<option value="', $month, '"', $month == $context['sub']['end']['month'] ? ' selected="selected"' : '', '>', $txt['months'][$month], '</option>';

	echo '
					</select>&nbsp;
					<label for="dayend">', $txt['calendar_day'], '</label>&nbsp;
					<select name="dayend" id="dayend">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['sub']['end']['last_day']; $day++)
		echo '
						<option value="', $day, '"', $day == $context['sub']['end']['day'] ? ' selected="selected"' : '', '>', $day, '</option>';

	echo '
					</select>
					<label for="hourend">', $txt['hour'], '</label>: <input type="text" id="hourend" name="hourend" value="', $context['sub']['end']['hour'], '" size="2" class="input_text" />
					<label for="minuteend">', $txt['minute'], '</label>: <input type="text" id="minuteend" name="minuteend" value="', $context['sub']['end']['min'], '" size="2" class="input_text" />
				</fieldset>
				<div class="submitbutton">
					<input type="submit" name="save_sub" value="', $txt['paid_settings_save'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>';

	addInlineJavascript('
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'name_subscriber\',
			sControlId: \'name_control\',
			sSearchType: \'member\',
			sTextDeleteItem: \'' . $txt['autosuggest_delete_item'] . '\',
			bItemList: false
			});', true);

	// If we have pending payments for this user, show them
	if (!empty($context['pending_payments']))
	{
		echo '
		<div class="generic_list_wrapper">
			<h2 class="category_header">', $txt['pending_payments'], '</h2>
			<div class="infobox">
				', $txt['pending_payments_desc'], '
			</div>
			<h2 class="category_header">', $txt['pending_payments_value'], '</h2>
			<div class="content">
				<ul class="pending_payments flow_auto">';

		foreach ($context['pending_payments'] as $id => $payment)
		{
			echo '
					<li>
						', $payment['desc'], '
						<a class="linkbutton_left" href="', $scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;lid=', $context['log_id'], ';pending=', $id, ';accept">', $txt['pending_payments_accept'], '</a>
						<a class="linkbutton_right" href="', $scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;lid=', $context['log_id'], ';pending=', $id, ';remove">', $txt['pending_payments_remove'], '</a>
					</li>';
		}

		echo '
				</ul>
			</div>';
	}

	echo '
		</div>';
}

/**
 * Template for a user to edit/pick their subscriptions.
 */
function template_user_subscription()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
	<div id="paid_subscription">
		<form action="', $scripturl, '?action=profile;u=', $context['id_member'], ';area=subscriptions;confirm" method="post">
			<h2 class="category_header">', $txt['subscriptions'], '</h2>';

	if (empty($context['subscriptions']))
	{
		echo '
			<div class="information">
				', $txt['paid_subs_none'], '
			</div>';
	}
	else
	{
		echo '
			<div class="infobox">
				', $txt['paid_subs_desc'], '
			</div>';

		// Print out all the subscriptions.
		foreach ($context['subscriptions'] as $id => $subscription)
		{
			// Ignore the inactive ones...
			if (empty($subscription['active']))
				continue;

			echo '
			<h2 class="category_header">', $subscription['name'], '</h2>
			<div class="content">
				<p><strong>', $subscription['name'], '</strong></p>
				<p class="content">', $subscription['desc'], '</p>';

			if (!$subscription['flexible'])
				echo '
				<div>
					<strong>', $txt['paid_duration'], ':</strong> ', $subscription['length'], '
				</div>';

			if ($context['user']['is_owner'])
			{
				echo '
				<label for="cur', $subscription['id'], '">', $txt['paid_cost'], ':</label>';

				if ($subscription['flexible'])
				{
					echo '
				<select id="cur', $subscription['id'], '" name="cur[', $subscription['id'], ']">';

					// Print out the costs for this one.
					foreach ($subscription['costs'] as $duration => $value)
						echo '
					<option value="', $duration, '">', sprintf($modSettings['paid_currency_symbol'], $value), '/', $txt[$duration], '</option>';

					echo '
				</select>';
				}
				else
					echo '
				', sprintf($modSettings['paid_currency_symbol'], $subscription['costs']['fixed']);

				echo '
				<input type="submit" name="sub_id[', $subscription['id'], ']" value="', $txt['paid_order'], '" class="right_submit" />';
			}
			else
				echo '
				<a class="linkbutton" href="', $scripturl, '?action=admin;area=paidsubscribe;sa=modifyuser;sid=', $subscription['id'], ';uid=', $context['member']['id'], (empty($context['current'][$subscription['id']]) ? '' : ';lid=' . $context['current'][$subscription['id']]['id']), '">', empty($context['current'][$subscription['id']]) ? $txt['paid_admin_add'] : $txt['paid_edit_subscription'], '</a>';

			echo '
			</div>';
		}
	}

	echo '
		</form>
		<div class="profile_center">
			<h2 class="category_header">', $txt['paid_current'], '</h2>
			<div class="infobox">
				', $txt['paid_current_desc'], '
			</div>
			<table class="table_grid">
				<thead>
					<tr class="table_head">
						<th class="grid33">', $txt['paid_name'], '</th>
						<th>', $txt['paid_status'], '</th>
						<th>', $txt['start_date'], '</th>
						<th>', $txt['end_date'], '</th>
					</tr>
				</thead>
				<tbody>';

	if (empty($context['current']))
		echo '
					<tr>
						<td class="centertext" colspan="4">
							', $txt['paid_none_ordered'], '
						</td>
					</tr>';

	foreach ($context['current'] as $sub)
	{
		if (!$sub['hide'])
			echo '
					<tr>
						<td>
							', (allowedTo('admin_forum') ? '<a href="' . $scripturl . '?action=admin;area=paidsubscribe;sa=modifyuser;lid=' . $sub['id'] . '">' . $sub['name'] . '</a>' : $sub['name']), '
						</td><td>
							<span style="color: ', ($sub['status'] == 2 ? 'green' : ($sub['status'] == 1 ? 'red' : 'orange')), '"><strong>', $sub['status_text'], '</strong></span>
						</td><td>
							', $sub['start'], '
						</td><td>
							', $sub['end'], '
						</td>
					</tr>';
	}
	echo '
				</tbody>
			</table>
		</div>
	</div>';
}

/**
 * The "choose payment" dialog.
 */
function template_choose_payment()
{
	global $context, $txt;

	echo '
	<div id="paid_subscription">
		<h2 class="category_header">', $txt['paid_confirm_payment'], '</h2>
		<div class="information">
			', $txt['paid_confirm_desc'], '
		</div>
		<div class="content">
			<dl class="settings">
				<dt>
					<label>', $txt['subscription'], ':</label>
				</dt>
				<dd>
					', $context['sub']['name'], '
				</dd>
				<dt>
					<label>', $txt['paid_cost'], ':</label>
				</dt>
				<dd>
					', $context['cost'], '
				</dd>
			</dl>
		</div>';

	// Do all the gateway options.
	foreach ($context['gateways'] as $gateway)
	{
		echo '
		<h2 class="category_header">', $gateway['title'], '</h2>
		<div class="content">
			', $gateway['desc'], '
			<form id="', $gateway['id'], '" action="', $gateway['form'], '" method="post">';

		foreach ($gateway['hidden'] as $name => $value)
			echo '
				<input type="hidden" id="', $gateway['id'], '_', $name, '" name="', $name, '" value="', $value, '" />';

		echo '
				<div class="submitbutton">
					<input type="submit" value="', $gateway['submit'], '" />
				</div>
			</form>
		</div>';
	}

	echo '
	</div>';
}

/**
 * The "thank you" bit, when paid subscription is completed.
 */
function template_paid_done()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="paid_subscription">
		<h2 class="category_header">', $txt['paid_done'], '</h2>
		<div class="content">
			<p class="successbox">', $txt['paid_done_desc'], '</p>
			<br />
			<a class="linkbutton_right" href="', $scripturl, '?action=profile;u=', $context['member']['id'], ';area=subscriptions">', $txt['paid_sub_return'], '</a>
		</div>
	</div>';
}