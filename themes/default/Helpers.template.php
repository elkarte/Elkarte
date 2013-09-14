<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

function template_list_groups_collapsible($group = 'default_groups_list')
{
	global $context, $txt;

	$current_group_list = $context[$group];
	$all_selected = true;
	if (!isset($current_group_list['id']))
		$current_group_list['id'] = $group;

	echo '
		<fieldset id="', $current_group_list['id'], '">
			<legend><a href="javascript:void(0);" onclick="document.getElementById(\'', $current_group_list['id'], '\').style.display = \'none\';document.getElementById(\'', $current_group_list['id'], '_groups_link\').style.display = \'block\'; return false;">', $current_group_list['select_group'], '</a></legend>';

	echo '
			<ul class="permission_groups">';

	foreach ($current_group_list['member_groups'] as $group)
	{
		$all_selected &= $group['status'] == 'on';
		echo '
				<li>
					<input type="checkbox" id="', $current_group_list['id'], '_', $group['id'], '" name="', $current_group_list['id'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked="checked"' : '', ' class="input_check" />
					<label for="', $current_group_list['id'], '_', $group['id'], '"', $group['is_postgroup'] ? ' style="font-style: italic;"' : '', '>', $group['name'], '</label> <em>(', $group['member_count'], ')</em>
				</li>';
	}

	echo '
			</ul>
			<label for="checkAllGroups', $current_group_list['id'], '"><input type="checkbox" id="checkAllGroups', $current_group_list['id'], '" ', $all_selected ? ' checked="checked"' : '', ' onclick="invertAll(this, this.form, \'', $current_group_list['id'], '\');" class="input_check" /> <em>', $txt['check_all'], '</em></label>
		</fieldset>

		<a href="javascript:void(0);" onclick="document.getElementById(\'', $current_group_list['id'], '\').style.display = \'block\'; document.getElementById(\'', $current_group_list['id'], '_groups_link\').style.display = \'none\'; return false;" id="', $current_group_list['id'], '_groups_link" style="display: none;">[ ', $current_group_list['select_group'], ' ]</a>';


		if (!empty($current_group_list['collapsed']))
			echo '
		<script><!-- // --><![CDATA[
			document.getElementById("', $current_group_list['id'], '").style.display = "none";
			document.getElementById("', $current_group_list['id'], '_groups_link").style.display = "";
		// ]]></script>';
}

