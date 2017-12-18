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
 * @version 2.0-dev
 *
 */

/**
 * Template to search forum members according to criteria
 */
function template_search_members()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="UTF-8" id="admin_form_wrapper">
			<h2 class="category_header">
					<span class="floatleft">', $txt['search_for'], '</span>
					<span class="smalltext floatright">', $txt['wild_cards_allowed'], '</span>
			</h2>
			<input type="hidden" name="sa" value="query" />
			<div class="content">
				<div class="flow_hidden">
					<div class="msearch_details floatleft">
						<dl class="settings right">
							<dt class="righttext">
								<label for="mem_id">', $txt['member_id'], ':</label>
								<select name="types[mem_id]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected="selected">=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="text" name="mem_id" id="mem_id" value="" size="6" class="input_text" />
							</dd>
							<dt class="righttext">
								<label for="age">', $txt['age'], ':</label>
								<select name="types[age]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected="selected">=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="text" name="age" id="age" value="" size="6" class="input_text" />
							</dd>
							<dt class="righttext">
								<label for="posts">', $txt['member_postcount'], ':</label>
								<select name="types[posts]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected="selected">=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="text" name="posts" id="posts" value="" size="6" class="input_text" />
							</dd>
							<dt class="righttext">
								<label for="reg_date">', $txt['date_registered'], ':</label>
								<select name="types[reg_date]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected="selected">=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="text" name="reg_date" id="reg_date" value="" size="10" class="input_text" /><span class="smalltext">', $txt['date_format'], '</span>
							</dd>
							<dt class="righttext">
								<label for="last_online">', $txt['viewmembers_online'], ':</label>
								<select name="types[last_online]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected="selected">=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="text" name="last_online" id="last_online" value="" size="10" class="input_text" /><span class="smalltext">', $txt['date_format'], '</span>
							</dd>
						</dl>
					</div>
					<div class="msearch_details floatright">
						<dl class="settings right">
							<dt class="righttext">
								<label for="membername">', $txt['username'], ':</label>
							</dt>
							<dd>
								<input type="text" name="membername" id="membername" value="" class="input_text" />
							</dd>
							<dt class="righttext">
								<label for="email">', $txt['email_address'], ':</label>
							</dt>
							<dd>
								<input type="text" name="email" id="email" value="" class="input_text" />
							</dd>
							<dt class="righttext">
								<label for="website">', $txt['website'], ':</label>
							</dt>
							<dd>
								<input type="text" name="website" id="website" value="" class="input_text" />
							</dd>
							<dt class="righttext">
								<label for="ip">', $txt['ip_address'], ':</label>
							</dt>
							<dd>
								<input type="text" name="ip" id="ip" value="" class="input_text" />
							</dd>
						</dl>
					</div>
				</div>
				<div class="flow_hidden">
					<div class="msearch_details floatright">
						<fieldset>
							<legend>', $txt['activation_status'], '</legend>
							<label for="activated-0"><input type="checkbox" name="activated[]" value="1" id="activated-0" checked="checked" /> ', $txt['activated'], '</label>&nbsp;&nbsp;
							<label for="activated-1"><input type="checkbox" name="activated[]" value="0" id="activated-1" checked="checked" /> ', $txt['not_activated'], '</label>
							<label for="activated-2"><input type="checkbox" name="activated[]" value="11" id="activated-2" checked="checked" /> ', $txt['is_banned'], '</label>
						</fieldset>
					</div>
				</div>
			</div>
			<h2 class="category_header">', $txt['member_part_of_these_membergroups'], '</h2>
			<div class="flow_hidden">
				<table class="table_grid floatleft grid50">
					<thead>
						<tr class="table_head">
							<th scope="col">', $txt['membergroups'], '</th>
							<th scope="col" class="centertext">', $txt['primary'], '</th>
							<th scope="col" class="centertext">', $txt['additional'], '</th>
						</tr>
					</thead>
					<tbody>';

	foreach ($context['membergroups'] as $membergroup)
		echo '
						<tr>
							<td>', $membergroup['name'], '</td>
							<td class="centertext">
								<input type="checkbox" name="membergroups[1][]" value="', $membergroup['id'], '" checked="checked" />
							</td>
							<td class="centertext">
								', $membergroup['can_be_additional'] ? '<input type="checkbox" name="membergroups[2][]" value="' . $membergroup['id'] . '" checked="checked" />' : '', '
							</td>
						</tr>';

	echo '
						<tr>
							<td>
								<em>', $txt['check_all'], '</em>
							</td>
							<td class="centertext">
								<input type="checkbox" onclick="invertAll(this, this.form, \'membergroups[1]\');" checked="checked" />
							</td>
							<td class="centertext">
								<input type="checkbox" onclick="invertAll(this, this.form, \'membergroups[2]\');" checked="checked" />
							</td>
						</tr>
					</tbody>
				</table>

				<table class="table_grid floatright grid50">
					<thead>
						<tr class="table_head">
							<th scope="col">
								', $txt['membergroups_postgroups'], '
							</th>
							<th>&nbsp;</th>
						</tr>
					</thead>
					<tbody>';

	foreach ($context['postgroups'] as $postgroup)
		echo '
						<tr>
							<td>
								', $postgroup['name'], '
							</td>
							<td style="width: 40px;" class="centertext">
								<input type="checkbox" name="postgroups[]" value="', $postgroup['id'], '" checked="checked" />
							</td>
						</tr>';

	echo '
						<tr>
							<td>
								<em>', $txt['check_all'], '</em>
							</td>
							<td class="centertext">
								<input type="checkbox" onclick="invertAll(this, this.form, \'postgroups[]\');" checked="checked" />
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="submitbutton">
				<input type="submit" value="', $txt['search'], '" />
			</div>
		</form>
	</div>';
}

/**
 * Template to browse members in admin panel
 */
function template_admin_browse()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">';

	template_show_list('approve_list');

	// If we have lots of outstanding members try and make the admin's life easier.
	if ($context['approve_list']['total_num_items'] > 10)
	{
		echo '
		<br />
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="UTF-8" name="postFormOutstanding" id="postFormOutstanding" onsubmit="return onOutstandingSubmit();">
			<h2 class="category_header">', $txt['admin_browse_outstanding'], '</h2>
			<script>
				function onOutstandingSubmit()
				{
					if (document.forms.postFormOutstanding.todo.value === "")
						return;

					var message = "";
					if (document.forms.postFormOutstanding.todo.value.indexOf("delete") !== -1)
						message = "', $txt['admin_browse_w_delete'], '";
					else if (document.forms.postFormOutstanding.todo.value.indexOf("reject") !== -1)
						message = "', $txt['admin_browse_w_reject'], '";
					else if (document.forms.postFormOutstanding.todo.value === "remind")
						message = "', $txt['admin_browse_w_remind'], '";
					else
						message = "', $context['browse_type'] == 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate'], '";

					if (confirm(message + " ', $txt['admin_browse_outstanding_warn'], '"))
						return true;
					else
						return false;
				}
			</script>

			<div class="content">
				<dl class="settings">
					<dt>
						<label for="time_passed">', $txt['admin_browse_outstanding_days_1'], '</label>:
					</dt>
					<dd>
						<input type="text" id="time_passed" name="time_passed" value="14" maxlength="4" size="3" class="input_text" /> ', $txt['admin_browse_outstanding_days_2'], '.
					</dd>
					<dt>
						<label for="todo">', $txt['admin_browse_outstanding_perform'], '</label>:
					</dt>
					<dd>
						<select id="todo" name="todo">
							', $context['browse_type'] == 'activate' ? '
							<option value="ok">' . $txt['admin_browse_w_activate'] . '</option>' : '', '
							<option value="okemail">', $context['browse_type'] == 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate'], ' ', $txt['admin_browse_w_email'], '</option>', $context['browse_type'] == 'activate' ? '' : '
							<option value="require_activation">' . $txt['admin_browse_w_approve_require_activate'] . '</option>', '
							<option value="reject">', $txt['admin_browse_w_reject'], '</option>
							<option value="rejectemail">', $txt['admin_browse_w_reject'], ' ', $txt['admin_browse_w_email'], '</option>
							<option value="delete">', $txt['admin_browse_w_delete'], '</option>
							<option value="deleteemail">', $txt['admin_browse_w_delete'], ' ', $txt['admin_browse_w_email'], '</option>', $context['browse_type'] == 'activate' ? '
							<option value="remind">' . $txt['admin_browse_w_remind'] . '</option>' : '', '
						</select>
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="submit" value="', $txt['admin_browse_outstanding_go'], '" />
					<input type="hidden" name="type" value="', $context['browse_type'], '" />
					<input type="hidden" name="sort" value="', $context['approve_list']['sort']['id'], '" />
					<input type="hidden" name="start" value="', $context['approve_list']['start'], '" />
					<input type="hidden" name="orig_filter" value="', $context['current_filter'], '" />
					<input type="hidden" name="sa" value="approve" />', !empty($context['approve_list']['sort']['desc']) ? '
					<input type="hidden" name="desc" value="1" />' : '', '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>';
	}

	echo '
	</div>';
}

/**
 * Generate membergroup actions pull down form
 *
 * @param array $groups
 *
 * @return string
 */
function template_users_multiactions($groups)
{
	global $txt;

	$select = '
					<select name="maction" onchange="this.form.new_membergroup.disabled = (this.options[this.selectedIndex].value !== \'pgroup\' && this.options[this.selectedIndex].value !== \'agroup\');">
						<option value="">--------</option>
						<option value="delete">' . $txt['admin_delete_members'] . '</option>
						<option value="pgroup">' . $txt['admin_change_primary_membergroup'] . '</option>
						<option value="agroup">' . $txt['admin_change_secondary_membergroup'] . '</option>
						<option value="ban_names">' . $txt['admin_ban_usernames'] . '</option>
						<option value="ban_mails">' . $txt['admin_ban_useremails'] . '</option>
						<option value="ban_names_mails">' . $txt['admin_ban_usernames_and_emails'] . '</option>
						<option value="ban_ips">' . $txt['admin_ban_userips'] . '</option>
					</select>
					<select onchange="if(this.value==-1){if(!confirm(\'' . $txt['confirm_remove_membergroup'] . '\')){this.value=0;}}" name="new_membergroup" id="new_membergroup" disabled="disabled">';

	foreach ($groups as $member_group)
	{
		$select .= '
			<option value="' . $member_group['id'] . '"' . ($member_group['is_primary'] ? ' selected="selected"' : '') . '>
				' . $member_group['name'] . '
			</option>';
	}

	$select .= '</select>
					<input type="submit" name="maction_on_members" value="' . $txt['quick_mod_go'] . '" onclick="return confirm(\'' . $txt['quickmod_confirm'] . '\');" />';

	return $select;
}
