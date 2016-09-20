<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 2
 *
 */

/**
 * Renders a collapsible list of groups
 *
 * @param string $group defaults to default_groups_list
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
			<legend>', $current_group_list['select_group'], '</legend>';

	echo '
			<ul class="permission_groups">';

	foreach ($current_group_list['member_groups'] as $group)
	{
		$all_selected &= $group['status'] === 'on';
		echo '
				<li>
					<input type="checkbox" id="', $current_group_list['id'], '_', $group['id'], '" name="', $current_group_list['id'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked="checked"' : '', ' />
					<label for="', $current_group_list['id'], '_', $group['id'], '"', $group['is_postgroup'] ? ' class="em"' : '', '>', $group['name'], '</label> <em>(', $group['member_count'], ')</em>
				</li>';
	}

	echo '
				<li class="check_all">
					<input type="checkbox" id="check_all" ', $all_selected ? 'checked="checked" ' : '', 'onclick="invertAll(this, this.form, \'groups\');" />
					<label for="check_all">', $txt['check_all'], '</label>
				</li>
			</ul>
		</fieldset>';
}

/**
 * Dropdown usable to select a board
 *
 * @param string $name
 * @param string $label
 * @param string $extra
 * @param boolean $all
 *
 * @return string as echoed output
 */
function template_select_boards($name, $label = '', $extra = '', $all = false)
{
	global $context, $txt;

	if (!empty($label))
		echo '
	<label for="', $name, '">', $label, ' </label>';

	echo '
	<select name="', $name, '" id="', $name, '" ', $extra, ' >';

	if ($all)
		echo '
		<option value="">', $txt['icons_edit_icons_all_boards'], '</option>';

	foreach ($context['categories'] as $category)
	{
		echo '
		<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
			<option value="', $board['id'], '"', !empty($board['selected']) ? ' selected="selected"' : '', !empty($context['current_board']) && $board['id'] == $context['current_board'] && $context['boards_current_disabled'] ? ' disabled="disabled"' : '', '>', $board['child_level'] > 0 ? str_repeat('&#8195;', $board['child_level'] - 1) . '&#8195;&#10148;' : '', $board['name'], '</option>';
		echo '
		</optgroup>';
	}

	echo '
	</select>';
}