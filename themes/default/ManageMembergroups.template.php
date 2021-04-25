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
 * @version 1.1.7
 *
 */

/**
 * Regular membergroups list template
 */
function template_regular_membergroups_list()
{
	template_show_list('regular_membergroups_list');

	echo '<br /><br />';

	template_show_list('post_count_membergroups_list');
}

/**
 * Template for a new group
 */
function template_new_group()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" name="new_group" action="', $scripturl, '?action=admin;area=membergroups;sa=add" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['membergroups_new_group'], '</h2>
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="group_name_input">', $txt['membergroups_group_name'], ':</label>
					</dt>
					<dd>
						<input type="text" name="group_name" id="group_name_input" size="30" class="input_text" />
					</dd>';

	if ($context['undefined_group'])
	{
		echo '
					<dt>
						<label for="group_type">', $txt['membergroups_edit_group_type'], ':</label>
					</dt>
					<dd>
						<fieldset id="group_type">
							<legend>', $txt['membergroups_edit_select_group_type'], '</legend>
							<br />
							<input type="radio" name="group_type" id="group_type_private" value="0" checked="checked" onclick="swapPostGroup(0);" />
							<label for="group_type_private">', $txt['membergroups_group_type_private'], '</label><br />';

		if ($context['allow_protected'])
			echo '
							<input type="radio" name="group_type" id="group_type_protected" value="1" onclick="swapPostGroup(0);" />
							<label for="group_type_protected">', $txt['membergroups_group_type_protected'], '</label><br />';

		echo '
							<input type="radio" name="group_type" id="group_type_request" value="2" onclick="swapPostGroup(0);" />
							<label for="group_type_request">', $txt['membergroups_group_type_request'], '</label><br />

							<input type="radio" name="group_type" id="group_type_free" value="3"  onclick="swapPostGroup(0);" />
							<label for="group_type_free">', $txt['membergroups_group_type_free'], '</label><br />

							<input type="radio" name="group_type" id="group_type_post" value="-1" onclick="swapPostGroup(1);" />
							<label for="group_type_post">', $txt['membergroups_group_type_post'], '</label><br />
						</fieldset>
					</dd>';
	}

	if ($context['post_group'] || $context['undefined_group'])
		echo '
					<dt id="min_posts_text">
						<label for="min_posts_input">', $txt['membergroups_min_posts'], ':</label>
					</dt>
					<dd>
						<input type="text" name="min_posts" id="min_posts_input" size="5" class="input_text" />
					</dd>';

	if (!$context['post_group'] || !empty($modSettings['permission_enable_postgroups']))
	{
		echo '
					<dt>
						<label for="permission_base">', $txt['membergroups_permissions'], ':</label><br />
						<span class="smalltext">', $txt['membergroups_can_edit_later'], '</span>
					</dt>
					<dd>
						<fieldset id="permission_base">
							<legend>', $txt['membergroups_select_permission_type'], '</legend>
							<input type="radio" name="perm_type" id="perm_type_inherit" value="inherit" checked="checked" />
							<label for="perm_type_inherit">', $txt['membergroups_new_as_inherit'], ':</label>
							<select name="inheritperm" id="inheritperm_select" onclick="document.getElementById(\'perm_type_inherit\').checked = true;">
								<option value="-1">', $txt['membergroups_guests'], '</option>
								<option value="0" selected="selected">', $txt['membergroups_members'], '</option>';

		foreach ($context['groups'] as $group)
			echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

		echo '
							</select>
							<br />
							<input type="radio" name="perm_type" id="perm_type_copy" value="copy" />
							<label for="perm_type_copy">', $txt['membergroups_new_as_copy'], ':</label>
							<select name="copyperm" id="copyperm_select" onclick="document.getElementById(\'perm_type_copy\').checked = true;">
								<option value="-1">', $txt['membergroups_guests'], '</option>
								<option value="0" selected="selected">', $txt['membergroups_members'], '</option>';

		foreach ($context['groups'] as $group)
			echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

		echo '
							</select>
							<br />
							<input type="radio" name="perm_type" id="perm_type_predefined" value="predefined" />
							<label for="perm_type_predefined">', $txt['membergroups_new_as_type'], ':</label>
							<select name="level" id="level_select" onclick="document.getElementById(\'perm_type_predefined\').checked = true;">
								<option value="restrict">', $txt['permitgroups_restrict'], '</option>
								<option value="standard" selected="selected">', $txt['permitgroups_standard'], '</option>
								<option value="moderator">', $txt['permitgroups_moderator'], '</option>
								<option value="maintenance">', $txt['permitgroups_maintenance'], '</option>
							</select>
						</fieldset>
					</dd>';
	}

	echo '
					<dt>
						<label>', $txt['membergroups_new_board'], ':</label>', $context['post_group'] ? '<br />
						<span class="smalltext" style="font-weight: normal;">' . $txt['membergroups_new_board_post_groups'] . '</span>' : '', '
					</dt>
					<dd>';

	template_add_edit_group_boards_list('new_group', false);

	echo '
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="submit" value="', $txt['membergroups_add_group'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-mmg_token_var'], '" value="', $context['admin-mmg_token'], '" />
				</div>
			</div>';

	if ($context['undefined_group'])
	{
		// Enable / disable the required posts box when the group type is post based
		echo '
			<script>
				function swapPostGroup(isChecked)
				{
					var min_posts_text = document.getElementById(\'min_posts_text\');

					document.getElementById(\'min_posts_input\').disabled = !isChecked;
					min_posts_text.style.color = isChecked ? "" : "#888";
				}

				swapPostGroup(', $context['post_group'] ? 'true' : 'false', ');
			</script>';
	}

	echo '
		</form>
	</div>';
}

/**
 * Template edit group
 */
function template_edit_group()
{
	global $context, $settings, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" name="groupForm" action="', $scripturl, '?action=admin;area=membergroups;sa=edit;group=', $context['group']['id'], '" method="post" accept-charset="UTF-8" >
			<h2 class="category_header">', $txt['membergroups_edit_group'], ' - ', $context['group']['name'], '</h2>
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="group_name_input">', $txt['membergroups_edit_name'], ':</label>
					</dt>
					<dd>
						<input type="text" name="group_name" id="group_name_input" value="', $context['group']['editable_name'], '" size="30" class="input_text" />
					</dd>';

	if ($context['group']['id'] != 3 && $context['group']['id'] != 4)
		echo '
					<dt id="group_desc_text">
						<label for="group_desc_input">', $txt['membergroups_edit_desc'], ':</label>
					</dt>
					<dd>
						<textarea name="group_desc" id="group_desc_input" rows="4" cols="40">', $context['group']['description'], '</textarea>
					</dd>';

	// Group type...
	if ($context['group']['allow_post_group'])
	{
		echo '
					<dt>
						<label for="group_type">', $txt['membergroups_edit_group_type'], ':</label>
					</dt>
					<dd>
						<fieldset id="group_type">
							<legend>', $txt['membergroups_edit_select_group_type'], '</legend>
							<br />
							<input type="radio" name="group_type" id="group_type_private" value="0" ', !$context['group']['is_post_group'] && $context['group']['type'] == 0 ? 'checked="checked"' : '', ' onclick="swapPostGroup(0);" />
							<label for="group_type_private">', $txt['membergroups_group_type_private'], '</label><br />';

		if ($context['group']['allow_protected'])
			echo '
							<input type="radio" name="group_type" id="group_type_protected" value="1" ', $context['group']['type'] == 1 ? 'checked="checked"' : '', ' onclick="swapPostGroup(0);" />
							<label for="group_type_protected">', $txt['membergroups_group_type_protected'], '</label><br />';


		echo '
							<input type="radio" name="group_type" id="group_type_request" value="2" ', $context['group']['type'] == 2 ? 'checked="checked"' : '', ' onclick="swapPostGroup(0);" />
							<label for="group_type_request">', $txt['membergroups_group_type_request'], '</label><br />
							<input type="radio" name="group_type" id="group_type_free" value="3" ', $context['group']['type'] == 3 ? 'checked="checked"' : '', ' onclick="swapPostGroup(0);" />
							<label for="group_type_free">', $txt['membergroups_group_type_free'], '</label><br />
							<input type="radio" name="group_type" id="group_type_post" value="-1" ', $context['group']['is_post_group'] ? 'checked="checked"' : '', ' onclick="swapPostGroup(1);" />
							<label for="group_type_post">', $txt['membergroups_group_type_post'], '</label><br />
						</fieldset>
					</dd>';
	}

	if ($context['group']['id'] != 3 && $context['group']['id'] != 4)
		echo '
					<dt id="group_moderators_text">
						<label for="group_moderators">', $txt['moderators'], ':</label>
					</dt>
					<dd>
						<input type="text" name="group_moderators" id="group_moderators" value="', $context['group']['moderator_list'], '" size="30" class="input_text" />
						<div id="moderator_container"></div>
					</dd>
					<dt id="group_hidden_text">
						<label for="group_hidden_input">', $txt['membergroups_edit_hidden'], ':</label>
					</dt>
					<dd>
						<select name="group_hidden" id="group_hidden_input" onchange="if (this.value == 2 &amp;&amp; !confirm(\'', $txt['membergroups_edit_hidden_warning'], '\')) this.value = 0;">
							<option value="0" ', $context['group']['hidden'] ? '' : 'selected="selected"', '>', $txt['membergroups_edit_hidden_no'], '</option>
							<option value="1" ', $context['group']['hidden'] == 1 ? 'selected="selected"' : '', '>', $txt['membergroups_edit_hidden_boardindex'], '</option>
							<option value="2" ', $context['group']['hidden'] == 2 ? 'selected="selected"' : '', '>', $txt['membergroups_edit_hidden_all'], '</option>
						</select>
					</dd>';

	// Can they inherit permissions?
	if ($context['group']['id'] > 1 && $context['group']['id'] != 3)
	{
		echo '
					<dt id="group_inherit_text">
						<label for="group_inherit_input">', $txt['membergroups_edit_inherit_permissions'], '</label>:<br />
						<span class="smalltext">', $txt['membergroups_edit_inherit_permissions_desc'], '</span>
					</dt>
					<dd>
						<select name="group_inherit" id="group_inherit_input">
							<option value="-2">', $txt['membergroups_edit_inherit_permissions_no'], '</option>
							<option value="-1" ', $context['group']['inherited_from'] == -1 ? 'selected="selected"' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $txt['membergroups_guests'], '</option>
							<option value="0" ', $context['group']['inherited_from'] == 0 ? 'selected="selected"' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $txt['membergroups_members'], '</option>';

		// For all the inheritable groups show an option.
		foreach ($context['inheritable_groups'] as $id => $group)
			echo '
							<option value="', $id, '" ', $context['group']['inherited_from'] == $id ? 'selected="selected"' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $group, '</option>';

		echo '
						</select>
						<input type="hidden" name="old_inherit" value="', $context['group']['inherited_from'], '" />
					</dd>';
	}

	if ($context['group']['allow_post_group'])
		echo '
					<dt id="min_posts_text">
						<label for="min_posts_input">', $txt['membergroups_min_posts'], ':</label>
					</dt>
					<dd>
						<input type="text" name="min_posts" id="min_posts_input"', $context['group']['is_post_group'] ? ' value="' . $context['group']['min_posts'] . '"' : '', ' size="6" class="input_text" />
					</dd>';

	// Hide the online color for our local moderators group.
	if ($context['group']['id'] != 3)
		echo '
					<dt>
						<label for="online_color_input">', $txt['membergroups_online_color'], ':</label>
					</dt>
					<dd>
						<input type="text" name="online_color" id="online_color_input" value="', $context['group']['color'], '" size="20" class="input_text" />
					</dd>';
	echo '
					<dt>
						<label for="icon_count_input">', $txt['membergroups_icon_count'], ':</label>
					</dt>
					<dd>
						<input type="number" min="0" max="10" step="1" name="icon_count" id="icon_count_input" value="', $context['group']['icon_count'], '" size="4" onkeyup="if (parseInt(this.value, 10) > 10) this.value = 10;" onchange="this.value = Math.floor(this.value);this.form.icon_image.onchange();" class="input_text" />
					</dd>
					<dt>
						<label for="icon_image_input">', $txt['membergroups_icon_image'], ':</label>
						<br />
						<span class="smalltext">', $txt['membergroups_icon_image_note'], '</span>
					</dt>
					<dd>
						<span class="floatleft">
							', $txt['membergroups_images_url'], '
							<input type="text" name="icon_image" id="icon_image_input" value="', $context['group']['icon_image'], '" onchange="if (this.value &amp;&amp; this.form.icon_count.value == 0) this.form.icon_count.value = 1;else if (!this.value) this.form.icon_count.value = 0; document.getElementById(\'msg_icon_0\').src = elk_images_url + \'/group_icons/\' + (this.value &amp;&amp; this.form.icon_count.value > 0 ? this.value : \'blank.png\')" size="20" class="input_text" />
						</span>
						<span id="messageicon_0" class="groupicon">
							<img id="msg_icon_0" src="', $settings['images_url'], '/group_icons/', $context['group']['icon_image'] == '' ? 'blank.png' : $context['group']['icon_image'], '" alt="*" />
						</span>
					</dd>
					<dt>
						<label for="max_messages_input">', $txt['membergroups_max_messages'], ':</label><br />
						<span class="smalltext">', $txt['membergroups_max_messages_note'], '</span>
					</dt>
					<dd>
						<input type="text" name="max_messages" id="max_messages_input" value="', $context['group']['id'] == 1 ? 0 : $context['group']['max_messages'], '" size="6"', $context['group']['id'] == 1 ? ' disabled="disabled"' : '', ' class="input_text" />
					</dd>';

	if (!empty($context['categories']))
	{
		echo '
					<dt>
						<label>', $txt['membergroups_new_board'], ':</label>', $context['group']['is_post_group'] ? '<br />
						<span class="smalltext">' . $txt['membergroups_new_board_post_groups'] . '</span>' : '', '
					</dt>
					<dd>';

		template_add_edit_group_boards_list('groupForm', true);

		echo '
					</dd>';
	}

	echo '
				</dl>
				<div class="submitbutton">
					<input type="submit" name="save" value="', $txt['membergroups_edit_save'], '" />', $context['group']['allow_delete'] ? '
					<input type="submit" name="delete" value="' . $txt['membergroups_delete'] . '" onclick="return confirm(\'' . $txt['membergroups_confirm_delete'] . '\');" />' : '', '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-mmg_token_var'], '" value="', $context['admin-mmg_token'], '" />
				</div>
			</div>
		</form>
	</div>';

	addInlineJavascript('
		aIconLists[aIconLists.length] = new IconList({
			sBackReference: "aIconLists[" + aIconLists.length + "]",
			sIconIdPrefix: "msg_icon_",
			bShowModify: false,
			sAction: "groupicons",
			sLabelIconList: ' . JavaScriptEscape($txt['membergroups_icons']) . ',
			sLabelIconBox: "icon_image_input",
			sBoxBackground: "transparent",
			sBoxBackgroundHover: "#fff",
			iBoxBorderWidthHover: 1,
			sBoxBorderColorHover: "#adadad",
			sContainerBackground: "#fff",
			sContainerBorder: "1px solid #adadad",
			sItemBorder: "1px solid #fff",
			sItemBorderHover: "1px dotted gray",
			sItemBackground: "transparent",
			sItemBackgroundHover: "#e0e0f0"
		});', true);

	if ($context['group']['id'] != 3 && $context['group']['id'] != 4)
	{
		$js = '
		var oModeratorSuggest = new smc_AutoSuggest({
			sSelf: \'oModeratorSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'group_moderators\',
			sControlId: \'group_moderators\',
			sSearchType: \'member\',
			bItemList: true,
			sPostName: \'moderator_list\',
			sURLMask: \'action=profile;u=%item_id%\',
			sTextDeleteItem: ' . JavaScriptEscape($txt['autosuggest_delete_item']) . ',
			sItemListContainerId: \'moderator_container\',
			aListItems: [';

		foreach ($context['group']['moderators'] as $id_member => $member_name)
			$js .= '
						{
							sItemId: ' . JavaScriptEscape($id_member) . ',
							sItemName: ' . JavaScriptEscape($member_name) . '
						}' . $id_member == $context['group']['last_moderator_id'] ? '' : ',';

		$js .= '
			]
		});';

		addInlineJavascript($js, true);
	}

	// If post based is selected, disable moderation selection, visability, group description and enable post count,
	if ($context['group']['allow_post_group'])
		addInlineJavascript('swapPostGroup(' . ($context['group']['is_post_group'] ? 'true' : 'false') . ');', true);
}

/**
 * Template to edit the boards and groups access to them
 *
 * Accessed with ?action=admin;area=membergroups;sa=add
 *
 * @param int $form_id
 * @param bool $collapse
 */
function template_add_edit_group_boards_list($form_id, $collapse = true)
{
	global $context, $txt, $modSettings;

	$deny = !empty($modSettings['deny_boards_access']);

	echo '
							<fieldset class="visible_boards">
								<legend', $collapse ? ' data-collapsed="true"' : '', '>', $txt['membergroups_new_board_desc'], '</legend>
								<ul>';

	foreach ($context['categories'] as $category)
	{
		if (empty($deny))
			echo '
									<li class="category">
										<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \'', $form_id, '\', \'boardaccess\'); return false;"><strong>', $category['name'], '</strong></a>
									<ul>';
		else
			echo '
									<li class="category">
										<strong>', $category['name'], '</strong>
										<ul id="boards_list_', $category['id'], '">';

		if (!empty($deny))
			echo '
										<li class="board select_category">
											', $txt['all_boards_in_cat'], ':
											<span class="floatright">
												<label for="all_sel_', $category['id'], '">
													<input type="radio" onchange="select_in_category(\'allow\', [', implode(',', array_keys($category['boards'])), ']);" id="all_sel_', $category['id'], '" name="all_', $category['id'], '" /> ', $txt['board_perms_allow'], '
												</label>
												<label for="all_ign_', $category['id'], '">
													<input type="radio" onchange="select_in_category(\'ignore\', [', implode(',', array_keys($category['boards'])), ']);" id="all_ign_', $category['id'], '" name="all_', $category['id'], '" /> ', $txt['board_perms_ignore'], '
												</label>
												<label for="all_den_', $category['id'], '">
													<input type="radio" onchange="select_in_category(\'deny\', [', implode(',', array_keys($category['boards'])), ']);" id="all_den_', $category['id'], '" name="all_', $category['id'], '" /> ', $txt['board_perms_deny'], '
												</label>
											</span>
										</li>';

		foreach ($category['boards'] as $board)
		{
			if (empty($deny))
				echo '
										<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
											<input id="brd', $board['id'], '"  name="boardaccess[', $board['id'], ']" type="checkbox" value="allow" ', $board['allow'] ? ' checked="checked"' : '', ' />
											<label for="brd', $board['id'], '">', $board['name'], '</label>
										</li>';
			else
				echo '
										<li class="board">
											<span style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">', $board['name'], ': </span>
											<span class="floatright">
												<label for="allow_brd', $board['id'], '">
													<input type="radio" name="boardaccess[', $board['id'], ']" id="allow_brd', $board['id'], '" value="allow" ', $board['allow'] ? ' checked="checked"' : '', ' /> ', $txt['permissions_option_on'], '
												</label>
												<label for="ignore_brd', $board['id'], '">
													<input type="radio" name="boardaccess[', $board['id'], ']" id="ignore_brd', $board['id'], '" value="ignore" ', !$board['allow'] && !$board['deny'] ? ' checked="checked"' : '', ' /> ', $txt['permissions_option_off'], '
												</label>
												<label for="deny_brd', $board['id'], '">
													<input type="radio" name="boardaccess[', $board['id'], ']" id="deny_brd', $board['id'], '" value="deny" ', $board['deny'] ? ' checked="checked"' : '', ' /> ', $txt['permissions_option_deny'], '
												</label>
											</span>
										</li>';
		}

		echo '
									</ul>
								</li>';
	}

	echo '
							</ul>';

	if (empty($deny))
		echo '
								<br />
								<div class="select_all_box">
									<input id="checkall_check" type="checkbox" onclick="invertAll(this, this.form, \'boardaccess\');" />
									<label for="checkall_check"><em>', $txt['check_all'], '</em></label>
								</div>';
	else
		echo '
								<div class="select_all_box">
									', $txt['all'], ':
									<span class="floatright">
										<label for="all_', $category['id'], '">
											<input type="radio" name="select_all" id="allow_all" onclick="selectAllRadio(this, this.form, \'boardaccess\', \'allow\');" /> ', $txt['board_perms_allow'], '
										</label>
										<label for="all_', $category['id'], '">
											<input type="radio" name="select_all" id="ignore_all" onclick="selectAllRadio(this, this.form, \'boardaccess\', \'ignore\');" /> ', $txt['board_perms_ignore'], '
										</label>
										<label for="all_', $category['id'], '">
											<input type="radio" name="select_all" id="deny_all" onclick="selectAllRadio(this, this.form, \'boardaccess\', \'deny\');" /> ', $txt['board_perms_deny'], '
										</label>
									</span>
								</div>';

	// select_all_box is hidden and it's made available only if js is enabled
	echo '
							</fieldset>
							<script>
								$(function() {
									$(".select_all_box").each(function () {
										$(this).show();
									});
								});
							</script>';
}

/**
 * Template for viewing the members of a group.
 */
function template_group_members()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;group=', $context['group']['id'], '" method="post" accept-charset="UTF-8" id="view_group">
			<h2 class="category_header">', $context['page_title'], '</h2>
				<div class="content">
				<dl class="settings">
					<dt>
						<label>', $txt['name'], ':</label>
					</dt>
					<dd>
						<span ', $context['group']['online_color'] ? 'style="color: ' . $context['group']['online_color'] . ';"' : '', '>', $context['group']['name'], '</span> ', $context['group']['icons'], '
					</dd>';

	// Any description to show?
	if (!empty($context['group']['description']))
		echo '
					<dt>
						<label>' . $txt['membergroups_members_description'] . ':</label>
					</dt>
					<dd>
						', $context['group']['description'], '
					</dd>';

	echo '
					<dt>
						<label>', $txt['membergroups_members_top'], ':</label>
					</dt>
					<dd>
						', $context['total_members'], '
					</dd>';

	// Any group moderators to show?
	if (!empty($context['group']['moderators']))
	{
		$moderators = array();
		foreach ($context['group']['moderators'] as $moderator)
			$moderators[] = '<a href="' . $scripturl . '?action=profile;u=' . $moderator['id'] . '">' . $moderator['name'] . '</a>';

		echo '
					<dt>
						<label>', $txt['membergroups_members_group_moderators'], ':</<label>
					</dt>
					<dd>
						', implode(', ', $moderators), '
					</dd>';
	}

	echo '
				</dl>
			</div>
			<h2 class="category_header">', $txt['membergroups_members_group_members'], '</h2>
			', template_pagesection(), '
			<table class="table_grid">
				<thead>
					<tr class="table_head">
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=name', $context['sort_by'] == 'name' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['name'], $context['sort_by'] == 'name' ? ' <i class="icon icon-small i-sort-alpha-' . $context['sort_direction'] . '"></i>' : '', '</a></th>';

	if ($context['can_send_email'])
		echo '
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=email', $context['sort_by'] == 'email' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['email'], $context['sort_by'] == 'email' ? ' <i class="icon icon-small i-sort-alpha-' . $context['sort_direction'] . '"></i>' : '', '</a></th>';

	echo '
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=active', $context['sort_by'] == 'active' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['membergroups_members_last_active'], $context['sort_by'] == 'active' ? '<i class="icon icon-small i-sort-numeric-' . $context['sort_direction'] . '"></i>' : '', '</a></th>
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=registered', $context['sort_by'] == 'registered' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['date_registered'], $context['sort_by'] == 'registered' ? '<i class="icon icon-small i-sort-numeric-' . $context['sort_direction'] . '"></i>' : '', '</a></th>
						<th ', empty($context['group']['assignable']) ? ' colspan="2"' : '', '><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=posts', $context['sort_by'] == 'posts' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['posts'], $context['sort_by'] == 'posts' ? ' <i class="icon icon-small i-sort-numeric-' . $context['sort_direction'] . '"></i>' : '', '</a></th>';

	if (!empty($context['group']['assignable']))
		echo '
						<th style="width: 4%;"><input type="checkbox" onclick="invertAll(this, this.form);" /></th>';

	echo '
					</tr>
				</thead>
				<tbody>';

	if (empty($context['members']))
		echo '
					<tr>
						<td colspan="6" class="centertext">', $txt['membergroups_members_no_members'], '</td>
					</tr>';

	foreach ($context['members'] as $member)
	{
		echo '
					<tr>
						<td>', $member['name'], '</td>';

		if ($context['can_send_email'])
		{
			echo '
						<td class="centertext"><em>' . template_member_email($member, true) . '</em></td>';
		}

		echo '
						<td>', $member['last_online'], '</td>
						<td>', $member['registered'], '</td>
						<td', empty($context['group']['assignable']) ? ' colspan="2"' : '', '>', $member['posts'], '</td>';

		if (!empty($context['group']['assignable']))
			echo '
						<td class="centertext" style="width: 4%;">
							<input type="checkbox" name="rem[]" value="', $member['id'], '" ', ($context['user']['id'] == $member['id'] && $context['group']['id'] == 1 ? 'onclick="if (this.checked) return confirm(\'' . $txt['membergroups_members_deadmin_confirm'] . '\')" ' : ''), '/>
						</td>';

		echo '
					</tr>';
	}

	echo '
				</tbody>
			</table>';

			template_pagesection(false, '', array('extra' => '<div class="floatright"><input type="submit" name="remove" value="' . $txt['membergroups_members_remove'] . '" /></div>'));

	if (!empty($context['group']['assignable']))
	{
		echo '
			<div class="separator">
				<h2 class="category_header">', $txt['membergroups_members_add_title'], '</h2>
				<div class="content">
					<dl class="settings">
						<dt>
							<label for="toAdd">', $txt['membergroups_members_add_desc'], ':</label>
						</dt>
						<dd>
							<input type="text" name="toAdd" id="toAdd" value="" class="input_text" />
							<div id="toAddItemContainer"></div>
						</dd>
					</dl>
					<div class="submitbutton">
						<input type="submit" name="add" value="', $txt['membergroups_members_add'], '" />
					</div>
				</div>
			</div>';
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['mod-mgm_token_var'], '" value="', $context['mod-mgm_token'], '" />
		</form>
	</div>';

	if (!empty($context['group']['assignable']))
		addInlineJavascript('
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: elk_session_id,
			sSessionVar: elk_session_var,
			sSuggestId: \'to_suggest\',
			sControlId: \'toAdd\',
			sSearchType: \'member\',
			sPostName: \'member_add\',
			sURLMask: \'action=profile;u=%item_id%\',
			sTextDeleteItem: \'' . $txt['autosuggest_delete_item'] . '\',
			bItemList: true,
			sItemListContainerId: \'toAddItemContainer\'
		});', true);
}

/**
 * Allow the moderator to enter a reason to each user being rejected.
 */
function template_group_request_reason()
{
	global $context, $txt, $scripturl;

	// Show a welcome message to the user.
	echo '
	<div id="moderationcenter">
		<form action="', $scripturl, '?action=groups;sa=requests" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['mc_groups_reason_title'], '</h2>
			<div class="content">
				<dl class="settings">';

	// Loop through and print out a reason box for each...
	foreach ($context['group_requests'] as $request)
		echo '
					<dt>
						<label for="groupreason">', sprintf($txt['mc_groupr_reason_desc'], $request['member_link'], $request['group_link']), ':</label>
					</dt>
					<dd>
						<input type="hidden" name="groupr[]" value="', $request['id'], '" />
						<textarea id="groupreason" name="groupreason[', $request['id'], ']" rows="3" cols="40" style="min-width: 80%; max-width: 99%;"></textarea>
					</dd>';

	echo '
				</dl>
				<div class="submitbutton">
					<input type="submit" name="go" value="', $txt['mc_groupr_submit'], '" />
					<input type="hidden" name="req_action" value="got_reason" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['mod-gr_token_var'], '" value="', $context['mod-gr_token'], '" />
				</div>
			</div>
		</form>
	</div>';
}
