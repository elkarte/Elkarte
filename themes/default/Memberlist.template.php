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
 */

/**
 * Displays a sortable listing of all members registered on the forum.
 */
function template_main()
{
	global $context, $settings, $scripturl, $txt;

	$extra = '
	<form id="mlsearch" action="' . $scripturl . '?action=memberlist;sa=search" method="post" accept-charset="UTF-8">
		<ul class="floatright">
			<li>
				<input onfocus="toggle_mlsearch_opt();" onblur="toggle_mlsearch_opt();" type="text" name="search" value="" class="input_text" placeholder="' . $txt['search'] . '" />&nbsp;
				<input type="submit" name="search2" value="' . $txt['search'] . '" class="button_submit" />
				<ul id="mlsearch_options">';

	foreach ($context['search_fields'] as $id => $title)
	{
		$extra .= '
				<li class="mlsearch_option">
					<label for="fields-' . $id . '"><input type="checkbox" name="fields[]" id="fields-' . $id . '" value="' . $id . '" ' . (in_array($id, $context['search_defaults']) ? 'checked="checked"' : '') . ' class="input_check floatright" />' . $title . '</label>
				</li>';
	}

	$extra .= '
				</ul>
			</li>
		</ul>
	</form>';

	template_pagesection('memberlist_buttons', 'right', 'go_down', array('extra' => $extra));

	echo '
	<script><!-- // --><![CDATA[
		function toggle_mlsearch_opt()
		{
			$("#mlsearch_options").slideToggle("fast");
		}
	// ]]></script>
	<div id="memberlist">
		<h2 class="category_header">
				<span class="floatleft">', $txt['members_list'], '</span>';
		if (!isset($context['old_search']))
				echo '
				<span class="floatright" letter_links>', $context['letter_links'], '</span>';
		echo '
		</h2>';

	echo '
		<table class="table_grid">
			<thead>
				<tr class="table_head">';

	// Display each of the column headers of the table.
	foreach ($context['columns'] as $key => $column)
	{
		// @TODO maybe find something nicer?
		if ($key == 'email_address' && !$context['can_send_email'])
			continue;

		// This is a selected column, so underline it or some such.
		if ($column['selected'])
			echo '
					<th scope="col" class="', isset($column['class']) ? ' ' . $column['class'] : '', '" style="width: auto; white-space: nowrap"' . (isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '') . '>
						<a href="' . $column['href'] . '" rel="nofollow">' . $column['label'] . '</a><img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />
					</th>';
		// This is just some column... show the link and be done with it.
		else
			echo '
					<th scope="col" class="', isset($column['class']) ? ' ' . $column['class'] : '', '"', isset($column['width']) ? ' style="width:' . $column['width'] . '"' : '', isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '', '>
						', $column['link'], '
					</th>';
	}
	echo '
				</tr>
			</thead>
			<tbody>';

	// Assuming there are members loop through each one displaying their data.
	$alternate = true;
	if (!empty($context['members']))
	{
		foreach ($context['members'] as $member)
		{
			echo '
				<tr class="', $alternate ? 'alternate_' : 'standard_', 'row"', empty($member['sort_letter']) ? '' : ' id="letter' . $member['sort_letter'] . '"', '>
					<td class="centertext">
						', $context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['text'] . '">' : '', $settings['use_image_buttons'] ? '<img src="' . $member['online']['image_href'] . '" alt="' . $member['online']['text'] . '" class="centericon" />' : $member['online']['label'], $context['can_send_pm'] ? '</a>' : '', '
					</td>
					<td>', $member['link'], '</td>';

			if ($context['can_send_email'])
				echo '
					<td class="centertext">', $member['show_email'] == 'no' ? '' : '<a href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '" rel="nofollow"><img src="' . $settings['images_url'] . '/profile/email_sm.png" alt="' . $txt['email'] . '" title="' . $txt['email'] . ' ' . $member['name'] . '" /></a>', '</td>';

			if (!isset($context['disabled_fields']['website']))
				echo '
					<td class="centertext">', $member['website']['url'] != '' ? '<a href="' . $member['website']['url'] . '" target="_blank" class="new_win"><img src="' . $settings['images_url'] . '/profile/www.png" alt="' . $member['website']['title'] . '" title="' . $member['website']['title'] . '" /></a>' : '', '</td>';

			// Group and date.
			echo '
					<td>', empty($member['group']) ? $member['post_group'] : $member['group'], '</td>
					<td>', $member['registered_date'], '</td>';

			if (!isset($context['disabled_fields']['posts']))
			{
				echo '
						<td class="centertext">', $member['posts'], '</td>';
			}

			// Any custom fields on display?
			if (!empty($context['custom_profile_fields']['columns']))
			{
				foreach ($context['custom_profile_fields']['columns'] as $key => $column)
					echo '
						<td>', $member['options'][substr($key, 5)], '</td>';
			}

			echo '
					</tr>';

				$alternate = !$alternate;
		}
	}
	// No members?
	else
		echo '
				<tr>
					<td colspan="', $context['colspan'], '" class="standard_row">', $txt['search_no_results'], '</td>
				</tr>';

				echo '
			</tbody>
		</table>';

	// If it is displaying the result of a search show a "search again" link to edit their criteria.
	if (isset($context['old_search']))
		$extra = '
			<a class="button_link" href="' . $scripturl . '?action=memberlist;sa=search;search=' . $context['old_search_value'] . '">' . $txt['mlist_search_again'] . '</a>';
	else
		$extra = '';

	// Show the page numbers again. (makes 'em easier to find!)
	template_pagesection(false, false, 'go_up', array('extra' => $extra));

	echo '
	</div>';

}

/**
 * A page allowing people to search the member list.
 */
function template_search()
{
	global $context, $settings, $scripturl, $txt;

	template_pagesection('memberlist_buttons', 'right', '', array('top_button' => false));

	// Start the submission form for the search!
	echo '
	<form id="memberlist" class="standard_category" action="', $scripturl, '?action=memberlist;sa=search" method="post" accept-charset="UTF-8">
		<h2 class="category_header">
			', !empty($settings['use_buttons']) ? '<img src="' . $settings['images_url'] . '/buttons/search_hd.png" alt="" class="icon" />' : '', $txt['mlist_search'], '
		</h2>
		<div class="content">
			<dl id="memberlist_search" class="settings">
				<dt>
					<label><strong>', $txt['search_for'], ':</strong></label>
				</dt>
				<dd>
					<input type="text" name="search" value="', $context['old_search'], '" size="40" class="input_text" placeholder="', $txt['search'], '" autofocus="autofocus" required="required" />
				</dd>
				<dt>
					<label><strong>', $txt['mlist_search_filter'], ':</strong></label>
				</dt>';

	foreach ($context['search_fields'] as $id => $title)
	{
		echo '
				<dd>
					<label for="fields-', $id, '"><input type="checkbox" name="fields[]" id="fields-', $id, '" value="', $id, '" ', in_array($id, $context['search_defaults']) ? 'checked="checked"' : '', ' class="input_check floatright" />', $title, '</label>
				</dd>';
	}

	echo '
			</dl>
			<div class="submit_buttons_wrap">
				<input type="submit" name="submit" value="' . $txt['search'] . '" class="button_submit" />
			</div>
		</div>
	</form>';
}