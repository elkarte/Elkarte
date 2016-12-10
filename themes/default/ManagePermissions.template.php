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
 * @version 1.1 beta 4
 *
 */

/**
 * Template for the permissions page in admin panel.
 */
function template_permission_index()
{
	global $context, $scripturl, $txt, $modSettings;

	// Not allowed to edit?
	if (!$context['can_modify'])
		echo '
	<div class="errorbox">
		', sprintf($txt['permission_cannot_edit'], $scripturl . '?action=admin;area=permissions;sa=profiles'), '
	</div>';

	echo '
	<div id="admin_form_wrapper">
		<form action="', $scripturl, '?action=admin;area=permissions;sa=quick" method="post" accept-charset="UTF-8" name="permissionForm" id="permissionForm">';

	if (!empty($context['profile']))
		echo '
			<h2 class="category_header">', $txt['permissions_for_profile'], ': &quot;', $context['profile']['name'], '&quot;</h2>';
	else
		echo '
			<h2 class="category_header">', $txt['permissions_title'], '</h2>';

	template_show_list('regular_membergroups_list');

	if (!empty($context['post_count_membergroups_list']))
		template_show_list('post_count_membergroups_list');

	echo '
			<br />';

	// Advanced stuff...
	if ($context['can_modify'])
	{
		echo '
			<h2 class="category_header panel_toggle">
					<i id="upshrink_ic" class="chevricon i-chevron-', empty($context['admin_preferences']['app']) ? 'up' : 'down', ' hide" title="', $txt['hide'], '"></i>
				<a href="#" id="permissions_panel_link">', $txt['permissions_advanced_options'], '</a>
			</h2>
			<div id="permissions_panel_advanced">
				<div class="content">
					<fieldset>
						<legend>', $txt['permissions_with_selection'], '</legend>
						<dl class="settings admin_permissions">
							<dt>
								<a class="helpicon i-help" href="', $scripturl, '?action=quickhelp;help=permissions_quickgroups" onclick="return reqOverlayDiv(this.href);"><s>' . $txt['help'] . '</s></a>', $txt['permissions_apply_pre_defined'], ':
							</dt>
							<dd>
								<select name="predefined">
									<option value="">(', $txt['permissions_select_pre_defined'], ')</option>
									<option value="restrict">', $txt['permitgroups_restrict'], '</option>
									<option value="standard">', $txt['permitgroups_standard'], '</option>
									<option value="moderator">', $txt['permitgroups_moderator'], '</option>
									<option value="maintenance">', $txt['permitgroups_maintenance'], '</option>
								</select>
							</dd>
							<dt>
								', $txt['permissions_like_group'], ':
							</dt>
							<dd>
								<select name="copy_from">
									<option value="empty">(', $txt['permissions_select_membergroup'], ')</option>';
		foreach ($context['groups'] as $group_id => $group_name)
			echo '
									<option value="', $group_id, '">', $group_name, '</option>';

		echo '
								</select>
							</dd>
							<dt>
								<select name="add_remove">
									<option value="add">', $txt['permissions_add'], '...</option>
									<option value="clear">', $txt['permissions_remove'], '...</option>';

		if (!empty($modSettings['permission_enable_deny']))
			echo '
									<option value="deny">', $txt['permissions_deny'], '...</option>';

		echo '
								</select>
							</dt>
							<dd style="overflow:auto;">
								<select name="permissions">
									<option value="">(', $txt['permissions_select_permission'], ')</option>';

		foreach ($context['permissions'] as $permissionType)
		{
			if ($permissionType['id'] == 'membergroup' && !empty($context['profile']))
				continue;

			foreach ($permissionType['columns'] as $column)
			{
				foreach ($column as $permissionGroup)
				{
					if ($permissionGroup['hidden'])
						continue;

					echo '
									<option value="" disabled="disabled">[', $permissionGroup['name'], ']</option>';
					foreach ($permissionGroup['permissions'] as $perm)
					{
						if ($perm['hidden'])
							continue;

						if ($perm['has_own_any'])
							echo '
									<option value="', $permissionType['id'], '/', $perm['own']['id'], '">&nbsp;&nbsp;&nbsp;', $perm['name'], ' (', $perm['own']['name'], ')</option>
									<option value="', $permissionType['id'], '/', $perm['any']['id'], '">&nbsp;&nbsp;&nbsp;', $perm['name'], ' (', $perm['any']['name'], ')</option>';
						else
							echo '
									<option value="', $permissionType['id'], '/', $perm['id'], '">&nbsp;&nbsp;&nbsp;', $perm['name'], '</option>';
					}
				}
			}
		}

		echo '
								</select>
							</dd>
						</dl>
					</fieldset>
					<input type="submit" value="', $txt['permissions_set_permissions'], '" onclick="return checkSubmit();" class="right_submit" />
				</div>
			</div>';

		// Javascript for the advanced stuff.
		addInlineJavascript('
		var oPermissionsPanelToggle = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ' . (empty($context['admin_preferences']['app']) ? 'false' : 'true') . ',
			aSwappableContainers: [
				\'permissions_panel_advanced\'
			],
			aSwapClasses: [
				{
					sId: \'upshrink_ic\',
					classExpanded: \'chevricon i-chevron-up\',
					titleExpanded: ' . JavaScriptEscape($txt['hide']) . ',
					classCollapsed: \'chevricon i-chevron-down\',
					titleCollapsed: ' . JavaScriptEscape($txt['show']) . '
				}
			],
			aSwapLinks: [
				{
					sId: \'permissions_panel_link\',
					msgExpanded: ' . JavaScriptEscape($txt['permissions_advanced_options']) . ',
					msgCollapsed: ' . JavaScriptEscape($txt['permissions_advanced_options']) . '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
				sOptionName: \'admin_preferences\',
				sSessionVar: elk_session_var,
				sSessionId: elk_session_id,
				sThemeId: \'1\',
				sAdditionalVars: \';admin_key=app\'
			}
		});', true);

		echo '
	<script>
		function checkSubmit()
		{
			if ((document.forms.permissionForm.predefined.value !== "" && (document.forms.permissionForm.copy_from.value !== "empty" || document.forms.permissionForm.permissions.value !== "")) || (document.forms.permissionForm.copy_from.value !== "empty" && document.forms.permissionForm.permissions.value !== ""))
			{
				alert("', $txt['permissions_only_one_option'], '");
				return false;
			}

			if (document.forms.permissionForm.predefined.value === "" && document.forms.permissionForm.copy_from.value === "" && document.forms.permissionForm.permissions.value === "")
			{
				alert("', $txt['permissions_no_action'], '");
				return false;
			}

			if (document.forms.permissionForm.permissions.value !== "" && document.forms.permissionForm.add_remove.value === "deny")
				return confirm("', $txt['permissions_deny_dangerous'], '");

			return true;
		}
	</script>';

		if (!empty($context['profile']))
			echo '
			<input type="hidden" name="pid" value="', $context['profile']['id'], '" />';

		echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['admin-mpq_token_var'], '" value="', $context['admin-mpq_token'], '" />';
	}
	else
		echo '
			</table>';

	echo '
		</form>
	</div>';
}

/**
 * Template for setting permissions by board
 */
function template_by_board()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=permissions;sa=board" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['permissions_boards'], '</h2>
			<div class="information">
				', $txt['permissions_boards_desc'], '
			</div>';

	if (!$context['edit_all'])
		echo '
			<div class="content submitbutton">
				<a class="edit_all_board_profiles linkbutton" href="', $scripturl, '?action=admin;area=permissions;sa=board;edit;', $context['session_var'], '=', $context['session_id'], '">', $txt['permissions_board_all'], '</a>
			</div>';

	foreach ($context['categories'] as $category)
	{
		echo '
			<h2 class="category_header"><strong>', $category['name'], '</strong></h2>';

		if (!empty($category['boards']))
			echo '
			<div class="content">
				<ul class="perm_boards flow_hidden">
					<li class="flow_hidden">
						<span class="perm_name floatleft">', $txt['board_name'], '</span>
						<span class="perm_profile floatleft">', $txt['permission_profile'], '</span>
					</li>';

		foreach ($category['boards'] as $board)
		{
			echo '
					<li class="flow_hidden">
						<span class="perm_board floatleft">
							<a href="', $scripturl, '?action=admin;area=manageboards;sa=board;boardid=', $board['id'], ';rid=permissions;', $context['session_var'], '=', $context['session_id'], '">', str_repeat('-', $board['child_level']), ' ', $board['name'], '</a>
						</span>
						<span class="perm_boardprofile floatleft">';

			if ($context['edit_all'])
			{
				echo '
							<select name="boardprofile[', $board['id'], ']">';

				foreach ($context['profiles'] as $id => $profile)
					echo '
								<option value="', $id, '" ', $id == $board['profile'] ? 'selected="selected"' : '', '>', $profile['name'], '</option>';

				echo '
							</select>
						</span>';
			}
			else
				echo '
							<a id="edit_board_', $board['id'], '" href="', $scripturl, '?action=admin;area=permissions;sa=index;pid=', $board['profile'], ';', $context['session_var'], '=', $context['session_id'], '"> [', $board['profile_name'], ']</a>
						</span>
						<a class="edit_board" data-boardid="', $board['id'], '" data-boardprofile="', $board['profile'], '" href="', $scripturl, '?action=admin;area=permissions;sa=board;edit;', $context['session_var'], '=', $context['session_id'], '"></a>';

			echo '
					</li>';
		}

		if (!empty($category['boards']))
			echo '
				</ul>
			</div>';
	}

	echo '
			<div class="content submitbutton">';

	if ($context['edit_all'])
		echo '
				<input type="submit" name="save_changes" value="', $txt['save'], '" class="right_submit" />';
	else
		echo '
				<a class="edit_all_board_profiles linkbutton" href="', $scripturl, '?action=admin;area=permissions;sa=board;edit;', $context['session_var'], '=', $context['session_id'], '">', $txt['permissions_board_all'], '</a>
				<script>
					initEditProfileBoards();
				</script>';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-mpb_token_var'], '" value="', $context['admin-mpb_token'], '" />
			</div>
		</form>
	</div>';
}

/**
 * Edit permission profiles (predefined).
 */
function template_edit_profiles()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admin_form_wrapper">
		<form action="', $scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['permissions_profile_edit'], '</h2>
			<table class="table_grid">
				<thead>
					<tr class="table_head">
						<th>', $txt['permissions_profile_name'], '</th>
						<th>', $txt['permissions_profile_used_by'], '</th>
						<th class="perm_profile_delete', !empty($context['show_rename_boxes']) ? ' hide"' : '"', ' >', $txt['delete'], '</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['profiles'] as $profile)
	{
		echo '
					<tr>
						<td>';

		if (!empty($context['show_rename_boxes']) && $profile['can_edit'])
			echo '
							<input type="text" name="rename_profile[', $profile['id'], ']" value="', $profile['name'], '" class="input_text" />';
		else
			echo '
							<a ', $profile['can_edit'] ? 'class="rename_profile" data-pid="' . $profile['id'] . '" ' : '', 'href="', $scripturl, '?action=admin;area=permissions;sa=index;pid=', $profile['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $profile['name'], '</a>';

		echo '
						</td>
						<td>
							', !empty($profile['boards_text']) ? $profile['boards_text'] : $txt['permissions_profile_used_by_none'], '
						</td>
						<td class="centertext perm_profile_delete', !empty($context['show_rename_boxes']) ? ' hide"' : '"', '>
							<input type="checkbox" name="delete_profile[]" value="', $profile['id'], '" ', $profile['can_delete'] ? '' : 'disabled="disabled"', ' />
						</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<div class="submitbutton">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-mpp_token_var'], '" value="', $context['admin-mpp_token'], '" />';

	if ($context['can_edit_something'])
		echo '
				<input  id="rename" type="submit" name="rename" value="', empty($context['show_rename_boxes']) ? $txt['permissions_profile_rename'] : $txt['permissions_commit'], '" />';

	echo '
				<span', !empty($context['show_rename_boxes']) ? ' class="hide"' : '', '>
					<input id="delete" type="submit" name="delete" value="', $txt['quickmod_delete_selected'], '" />
				</span>
			</div>
		</form>
		<br />
		<form action="', $scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['permissions_profile_new'], '</h2>
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="profile_name">', $txt['permissions_profile_name'], ':</label>
					</dt>
					<dd>
						<input type="text" id="profile_name" name="profile_name" value="" class="input_text" />
					</dd>
					<dt>
						<label for="copy_from">', $txt['permissions_profile_copy_from'], ':</label>
					</dt>
					<dd>
						<select id="copy_from" name="copy_from">';

	foreach ($context['profiles'] as $id => $profile)
		echo '
							<option value="', $id, '">', $profile['name'], '</option>';

	echo '
						</select>
					</dd>
				</dl>
				<hr />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-mpp_token_var'], '" value="', $context['admin-mpp_token'], '" />
				<input type="submit" name="create" value="', $txt['permissions_profile_new_create'], '" class="right_submit" />
			</div>
		</form>
		<script>
			initEditPermissionProfiles();
		</script>
	</div>';
}

/**
 * Template for modifying group permissions
 */
function template_modify_group()
{
	global $context, $scripturl, $txt, $modSettings;

	// Cannot be edited?
	if (!$context['profile']['can_modify'])
	{
		echo '
		<div class="errorbox">
			', sprintf($txt['permission_cannot_edit'], $scripturl . '?action=admin;area=permissions;sa=profiles'), '
		</div>';
	}
	else
	{
		echo '
		<script>
			window.elk_usedDeny = false;

			function warnAboutDeny()
			{
				if (window.elk_usedDeny)
					return confirm("', $txt['permissions_deny_dangerous'], '");
				else
					return true;
			}
		</script>';
	}

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=permissions;sa=modify2;group=', $context['group']['id'], ';pid=', $context['profile']['id'], '" method="post" accept-charset="UTF-8" name="permissionForm" onsubmit="return warnAboutDeny();">';

	if (!empty($modSettings['permission_enable_deny']) && $context['group']['id'] != -1)
		echo '
			<div class="information">
				', $txt['permissions_option_desc'], '
			</div>';

	echo '
			<h2 class="category_header">';
	if ($context['permission_type'] == 'board')
		echo '
				', $txt['permissions_local_for'], ' &quot;', $context['group']['name'], '&quot; ', $txt['permissions_on'], ' &quot;', $context['profile']['name'], '&quot;';
	else
		echo '
				', $context['permission_type'] == 'membergroup' ? $txt['permissions_general'] : $txt['permissions_board'], ' - &quot;', $context['group']['name'], '&quot;';

	echo '
			</h2>
			<div class="flow_hidden">';

	// Draw out the main bits.
	template_modify_group_classic($context['permission_type']);

	echo '
			</div>';

	// If this is general permissions also show the default profile.
	if ($context['permission_type'] == 'membergroup')
	{
		echo '
			<br />
			<h2 class="category_header">', $txt['permissions_board'], '</h2>
			<div class="information">
				', $txt['permissions_board_desc'], '
			</div>
			<div class="flow_hidden">';

		template_modify_group_classic('board');

		echo '
			</div>';
	}

	if ($context['profile']['can_modify'])
		echo '
			<input type="submit" value="', $txt['permissions_commit'], '" class="right_submit" />';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['admin-mp_token_var'], '" value="', $context['admin-mp_token'], '" />
		</form>
	</div>';
}

/**
 * The classic way of looking at permissions.
 *
 * @param string $type
 */
function template_modify_group_classic($type)
{
	global $context, $scripturl, $txt, $modSettings;

	$permission_type = &$context['permissions'][$type];
	$disable_field = $context['profile']['can_modify'] ? '' : 'disabled="disabled" ';

	echo '
				<div class="content">';

	foreach ($permission_type['columns'] as $column)
	{
		echo '
					<table class="table_grid perm_classic floatleft">';

		foreach ($column as $permissionGroup)
		{
			if (empty($permissionGroup['permissions']))
				continue;

			// Are we likely to have something in this group to display or is it all hidden?
			$has_display_content = false;
			if (!$permissionGroup['hidden'])
			{
				// Before we go any further check we are going to have some data to print otherwise we just have a silly heading.
				foreach ($permissionGroup['permissions'] as $permission)
					if (!$permission['hidden'])
						$has_display_content = true;

				if ($has_display_content)
				{
					echo '
						<tr class="table_head">
							<th class="lefttext" colspan="2">
								<strong class="smalltext">', $permissionGroup['name'], '</strong>
							</th>';

					if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
						echo '
							<th></th><th></th><th></th>';
					else
						echo '
							<th><div>', $txt['permissions_option_on'], '</div></th>
							<th><div>', $txt['permissions_option_off'], '</div></th>
							<th><div>', $txt['permissions_option_deny'], '</div></th>';

					echo '
						</tr>';
				}
			}

			foreach ($permissionGroup['permissions'] as $permission)
			{
				// If it's hidden keep the last value.
				if ($permission['hidden'] || $permissionGroup['hidden'])
				{
					echo '
						<tr class="hide">
							<td>';

					if ($permission['has_own_any'])
					{
						// Guests can't have own permissions.
						if ($context['group']['id'] != -1)
							echo '
								<input type="hidden" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']" value="', $permission['own']['select'] == 'denied' && !empty($modSettings['permission_enable_deny']) ? 'deny' : $permission['own']['select'], '" />';

						echo '
								<input type="hidden" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']" value="', $permission['any']['select'] == 'denied' && !empty($modSettings['permission_enable_deny']) ? 'deny' : $permission['any']['select'], '" />';
					}
					else
						echo '
								<input type="hidden" name="perm[', $permission_type['id'], '][', $permission['id'], ']" value="', $permission['select'] == 'denied' && !empty($modSettings['permission_enable_deny']) ? 'deny' : $permission['select'], '" />';

					echo '
							</td>
						</tr>';
				}
				else
				{
					echo '
						<tr>
							<td style="width: 10px;">
								', $permission['show_help'] ? '<a href="' . $scripturl . '?action=quickhelp;help=permissionhelp_' . $permission['id'] . '" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>' . $txt['help'] . '</s></a>' : '', '
							</td>';

					if ($permission['has_own_any'])
					{
						echo '
							<td class="lefttext" colspan="4">', $permission['name'], '</td>
						</tr>
						<tr>';

						// Guests can't do their own thing.
						if ($context['group']['id'] != -1)
						{
							echo '
							<td></td>
							<td class="smalltext righttext">', $permission['own']['name'], ':</td>';

							if (empty($modSettings['permission_enable_deny']))
								echo '
							<td colspan="3">
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" id="', $permission['own']['id'], '_on" ', $disable_field, '/>
							</td>';
							else
								echo '
							<td style="width: 10px;">
								<input type="radio" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" id="', $permission['own']['id'], '_on" ', $disable_field, '/>
							</td>
							<td style="width: 10px;">
								<input type="radio" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'off' ? ' checked="checked"' : '', ' value="off" ', $disable_field, '/>
							</td>
							<td style="width: 10px;">
								<input type="radio" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'denied' ? ' checked="checked"' : '', ' value="deny" ', $disable_field, '/>
							</td>';

							echo '
						</tr>
						<tr>';
						}

						echo '
							<td></td>
							<td class="smalltext righttext">', $permission['any']['name'], ':</td>';

						if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
							echo '
							<td colspan="3">
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" ', $disable_field, '/>
							</td>';
						else
							echo '
							<td>
								<input type="radio" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'on' ? ' checked="checked"' : '', ' value="on" onclick="document.forms.permissionForm.', $permission['own']['id'], '_on.checked = true;" ', $disable_field, '/>
							</td>
							<td>
								<input type="radio" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'off' ? ' checked="checked"' : '', ' value="off" ', $disable_field, '/>
							</td>
							<td>
								<input type="radio" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'denied' ? ' checked="checked"' : '', ' value="deny" id="', $permission['any']['id'], '_deny" onclick="window.elk_usedDeny = true;" ', $disable_field, '/>
							</td>';

						echo '
						</tr>';
					}
					else
					{
						echo '
							<td class="lefttext">', $permission['name'], '</td>';

						if (empty($modSettings['permission_enable_deny']) || $context['group']['id'] == -1)
							echo '
							<td colspan="3">
								<input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked="checked"' : '', ' value="on" ', $disable_field, '/>
							</td>';
						else
							echo '
							<td>
								<input type="radio" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked="checked"' : '', ' value="on" ', $disable_field, '/>
							</td>
							<td>
								<input type="radio" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'off' ? ' checked="checked"' : '', ' value="off" ', $disable_field, '/>
							</td>
							<td>
								<input type="radio" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'denied' ? ' checked="checked"' : '', ' value="deny" onclick="window.elk_usedDeny = true;" ', $disable_field, '/>
							</td>';

						echo '
						</tr>';
					}
				}
			}

			if (!$permissionGroup['hidden'] && $has_display_content)
				echo '
						<tr>
							<td colspan="5"><!--separator--></td>
						</tr>';
		}
		echo '
					</table>';
	}
	echo '
			</div>';
}

/**
 * Show a collapsible box to set a specific permission.
 *
 * @param string $permission
 */
function template_inline_permissions($permission)
{
	global $context, $txt, $modSettings;

	echo '
		<fieldset id="', $permission, '">
			<legend>', $txt['avatar_select_permission'], '</legend>';

	if (empty($modSettings['permission_enable_deny']))
		echo '
			<ul class="permission_groups">';
	else
		echo '
			<div class="information">', $txt['permissions_option_desc'], '</div>
			<dl class="settings">
				<dt>
					<span class="perms"><strong>', $txt['permissions_option_on'], '</strong></span>
					<span class="perms"><strong>', $txt['permissions_option_off'], '</strong></span>
					<span class="perms" style="color: red;"><strong>', $txt['permissions_option_deny'], '</strong></span>
				</dt>
				<dd>
				</dd>';

	foreach ($context['permissions'][$permission] as $group)
	{
		if (!empty($modSettings['permission_enable_deny']))
			echo '
				<dt>';
		else
			echo '
				<li>';

		if (empty($modSettings['permission_enable_deny']))
			echo '
					<input type="checkbox" name="', $permission, '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked="checked"' : '', ' />';
		else
			echo '
					<span class="perms"><input type="radio" name="', $permission, '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked="checked"' : '', ' /></span>
					<span class="perms"><input type="radio" name="', $permission, '[', $group['id'], ']" value="off"', $group['status'] == 'off' ? ' checked="checked"' : '', ' /></span>
					<span class="perms"><input type="radio" name="', $permission, '[', $group['id'], ']" value="deny"', $group['status'] == 'deny' ? ' checked="checked"' : '', ' /></span>';

		if (!empty($modSettings['permission_enable_deny']))
			echo '
				</dt>
				<dd>
					<span', $group['is_postgroup'] ? ' class="em"' : '', '>', $group['name'], '</span>
				</dd>';
		else
			echo '
					<span', $group['is_postgroup'] ? ' class="em"' : '', '>', $group['name'], '</span>
				</li>';
	}

	if (empty($modSettings['permission_enable_deny']))
		echo '
			</ul>';
	else
		echo '
			</dl>';

	echo '
		</fieldset>';
}

/**
 * Edit post moderation permissions.
 */
function template_postmod_permissions()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	echo '
	<div id="admin_form_wrapper">
		<form action="', $scripturl, '?action=admin;area=permissions;sa=postmod;', $context['session_var'], '=', $context['session_id'], '" method="post" name="postmodForm" id="postmodForm" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['permissions_post_moderation'], '</h2>';

	// Got advanced permissions - if so warn!
	if (!empty($modSettings['permission_enable_deny']))
		echo '
				<div class="information">', $txt['permissions_post_moderation_deny_note'], '</div>';

	echo '
				<div class="submitbutton">
					', $txt['permissions_post_moderation_select'], ':
					<select name="pid" onchange="document.forms.postmodForm.submit();">';

	foreach ($context['profiles'] as $profile)
		if ($profile['can_modify'])
			echo '
						<option value="', $profile['id'], '" ', $profile['id'] == $context['current_profile'] ? 'selected="selected"' : '', '>', $profile['name'], '</option>';

	echo '
					</select>
					<input type="submit" value="', $txt['go'], '" class="right_submit" />
				</div>
				<table class="table_grid">
				<thead>
					<tr class="table_head">
						<th></th>
						<th class="centertext" colspan="3">
							', $txt['permissions_post_moderation_new_topics'], '
						</th>
						<th class="centertext" colspan="3">
							', $txt['permissions_post_moderation_replies_own'], '
						</th>
						<th class="centertext" colspan="3">
							', $txt['permissions_post_moderation_replies_any'], '
						</th>
						<th class="centertext" colspan="3">
							', $txt['permissions_post_moderation_attachments'], '
						</th>
					</tr>
					<tr class="secondary_header">
						<th class="grid30">
							', $txt['permissions_post_moderation_group'], '
						</th>
						<th><i class="icon icon-small i-check" title="', $txt['permissions_post_moderation_allow'], '"></i></th>
						<th><i class="icon icon-small i-post_moderation_moderate title="', $txt['permissions_post_moderation_moderate'], '"></i></th>
						<th><i class="icon icon-small i-post_moderation_deny" title="', $txt['permissions_post_moderation_disallow'], '"></i></th>
						<th><i class="icon icon-small i-check" title="', $txt['permissions_post_moderation_allow'], '"></i></th>
						<th><i class="icon icon-small i-post_moderation_moderate title="', $txt['permissions_post_moderation_moderate'], '"></i></th>
						<th><i class="icon icon-small i-post_moderation_deny" title="', $txt['permissions_post_moderation_disallow'], '"></i></th>
						<th><i class="icon icon-small i-check" title="', $txt['permissions_post_moderation_allow'], '"></i></th>
						<th><i class="icon icon-small i-post_moderation_moderate title="', $txt['permissions_post_moderation_moderate'], '"></i></th>
						<th><i class="icon icon-small i-post_moderation_deny" title="', $txt['permissions_post_moderation_disallow'], '"></i></th>
						<th><i class="icon icon-small i-check" title="', $txt['permissions_post_moderation_allow'], '"></i></th>
						<th><i class="icon icon-small i-post_moderation_moderate title="', $txt['permissions_post_moderation_moderate'], '"></i></th>
						<th><i class="icon icon-small i-post_moderation_deny" title="', $txt['permissions_post_moderation_disallow'], '"></i></th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['profile_groups'] as $group)
	{
		echo '
					<tr>
						<td style="width: 40%;">
							<span ', ($group['color'] ? 'style="color: ' . $group['color'] . '"' : ''), '>', $group['name'], '</span>';

		if (!empty($group['children']))
			echo '
							<br /><span class="smalltext">', $txt['permissions_includes_inherited'], ': &quot;', implode('&quot;, &quot;', $group['children']), '&quot;</span>';

		echo '
						</td>
						<td class="centertext"><input type="radio" name="new_topic[', $group['id'], ']" value="allow" ', $group['new_topic'] == 'allow' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="new_topic[', $group['id'], ']" value="moderate" ', $group['new_topic'] == 'moderate' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="new_topic[', $group['id'], ']" value="disallow" ', $group['new_topic'] == 'disallow' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="replies_own[', $group['id'], ']" value="allow" ', $group['replies_own'] == 'allow' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="replies_own[', $group['id'], ']" value="moderate" ', $group['replies_own'] == 'moderate' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="replies_own[', $group['id'], ']" value="disallow" ', $group['replies_own'] == 'disallow' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="replies_any[', $group['id'], ']" value="allow" ', $group['replies_any'] == 'allow' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="replies_any[', $group['id'], ']" value="moderate" ', $group['replies_any'] == 'moderate' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="replies_any[', $group['id'], ']" value="disallow" ', $group['replies_any'] == 'disallow' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="attachment[', $group['id'], ']" value="allow" ', $group['attachment'] == 'allow' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="attachment[', $group['id'], ']" value="moderate" ', $group['attachment'] == 'moderate' ? 'checked="checked"' : '', ' /></td>
						<td class="centertext"><input type="radio" name="attachment[', $group['id'], ']" value="disallow" ', $group['attachment'] == 'disallow' ? 'checked="checked"' : '', ' /></td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<div class="submitbutton">
				<input type="submit" name="save_changes" value="', $txt['permissions_commit'], '" />
				<input type="hidden" name="', $context['admin-mppm_token_var'], '" value="', $context['admin-mppm_token'], '" />
			</div>
		</form>
		<p class="smalltext" style="padding-left: 10px;">
			<strong>', $txt['permissions_post_moderation_legend'], ':</strong><br />
			<i class="icon icon-small i-post_moderation_allow"></i><br />
			<i class="icon icon-small i-post_moderation_moderate"></i> - ', $txt['permissions_post_moderation_moderate'], '<br />
			<i class="icon icon-small i-post_moderation_deny"></i> - ', $txt['permissions_post_moderation_disallow'], '
		</p>
	</div>';
}