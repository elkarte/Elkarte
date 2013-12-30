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
 * @version 1.0 Beta
 *
 */

/**
 * Memberlist search form
 */
function template_mlsearch_above()
{
	global $context, $scripturl, $txt;

	$extra = '
	<form id="mlsearch" action="' . $scripturl . '?action=memberlist;sa=search" method="post" accept-charset="UTF-8">
		<ul class="floatright">
			<li>
				<input id="mlsearch_input" onfocus="toggle_mlsearch_opt();" type="text" name="search" value="" class="input_text" placeholder="' . $txt['search'] . '" />&nbsp;
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

	template_pagesection('memberlist_buttons', 'right', array('extra' => $extra));

	echo '
	<script><!-- // --><![CDATA[
		function toggle_mlsearch_opt()
		{
			$(\'body\').on(\'click\', mlsearch_opt_hide);
			$(\'#mlsearch_options\').slideToggle(\'fast\');
		}

		function mlsearch_opt_hide(ev)
		{
			if (ev.target.id === \'mlsearch_options\' || ev.target.id === \'mlsearch_input\')
				return;

			$(\'body\').off(\'click\', mlsearch_opt_hide);
			$(\'#mlsearch_options\').slideToggle(\'fast\');
		}
	// ]]></script>';
}

/**
 * Displays a sortable listing of all members registered on the forum.
 */
function template_memberlist()
{
	global $context, $settings, $scripturl, $txt;

	echo '
	<div id="memberlist">
		<h2 class="category_header">
				<span class="floatleft">', $txt['members_list'], '</span>';

	if (!isset($context['old_search']))
		echo '
				<span class="floatright" letter_links>', $context['letter_links'], '</span>';

	echo '
		</h2>
		<table class="table_grid">
			<thead>
				<tr class="table_head">';

	$table_span = 0;

	// Display each of the column headers of the table.
	foreach ($context['columns'] as $key => $column)
	{
		$table_span += isset($column['colspan']) ? $column['colspan'] : 1;
		// This is a selected column, so underline it or some such.
		if ($column['selected'])
			echo '
					<th scope="col"', isset($column['class']) ? ' class="' . $column['class'] . '"' : '', ' style="width: auto; white-space: nowrap"' . (isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '') . '>
						<a href="' . $column['href'] . '" rel="nofollow">' . $column['label'] . '</a><img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />
					</th>';
		// This is just some column... show the link and be done with it.
		else
			echo '
					<th scope="col" ', isset($column['class']) ? ' class="' . $column['class'] . '"' : '', isset($column['width']) ? ' style="width:' . $column['width'] . '"' : '', isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '', '>
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
			if (!empty($member['sort_letter']))
			{
				echo '
				<tr class="standard_row" id="letter', $member['sort_letter'], '">
					<th class="letterspacing" colspan="', $table_span, '">', $member['sort_letter'], '</th>
				</tr>';

				$alternate = true;
			}

			echo '
				<tr class="', $alternate ? 'alternate_' : 'standard_', 'row">';

			foreach ($context['columns'] as $column => $values)
			{
				if (isset($member[$column]))
				{
					if ($column == 'online')
					{
						echo '
						<td>
							', $context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['text'] . '">' : '', $settings['use_image_buttons'] ? '<img src="' . $member['online']['image_href'] . '" alt="' . $member['online']['text'] . '" class="centericon" />' : $member['online']['label'], $context['can_send_pm'] ? '</a>' : '', '
						</td>';
						continue;
					}
					elseif ($column == 'email_address')
					{
						echo '
						<td>', $member['show_email'] == 'no' ? '' : '<a href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '" rel="nofollow"><img src="' . $settings['images_url'] . '/profile/email_sm.png" alt="' . $txt['email'] . '" title="' . $txt['email'] . ' ' . $member['name'] . '" /></a>', '</td>';
						continue;
					}
					else
						echo '
						<td>', $member[$column], '</td>';
				}
				// Any custom fields on display?
				elseif (!empty($context['custom_profile_fields']['columns']) && isset($context['custom_profile_fields']['columns'][$column]))
				{
					echo '
							<td>', $member['options'][substr($column, 5)], '</td>';
				}
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
}

/**
 * Shows the search again button to allow editing the parameters
 */
function template_mlsearch_below()
{
	global $context, $scripturl, $txt;

	// If it is displaying the result of a search show a "search again" link to edit their criteria.
	if (isset($context['old_search']))
		$extra = '
			<a class="linkbutton_right" href="' . $scripturl . '?action=memberlist;sa=search;search=' . $context['old_search_value'] . '">' . $txt['mlist_search_again'] . '</a>';
	else
		$extra = '';

	// Show the page numbers again. (makes 'em easier to find!)
	template_pagesection(false, false, array('extra' => $extra));

	echo '
	</div>';
}