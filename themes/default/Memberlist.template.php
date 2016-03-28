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
 * @version 1.0.7
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
				<input id="mlsearch_input" onfocus="toggle_mlsearch_opt();" type="text" name="search" value="' . $context['old_search_value'] . '" class="input_text" placeholder="' . $txt['search'] . '" />&nbsp;
				<input type="submit" name="search2" value="' . $txt['search'] . '" class="button_submit" />
				<ul id="mlsearch_options" class="nojs">';

	foreach ($context['search_fields'] as $id => $title)
	{
		$extra .= '
					<li class="mlsearch_option">
						<label for="fields-' . $id . '">
							<input type="checkbox" name="fields[]" id="fields-' . $id . '" value="' . $id . '" ' . (in_array($id, $context['search_defaults']) ? 'checked="checked"' : '') . ' class="input_check" />' . $title . '
						</label>
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
		// Removes the nojs class to properly style the dropdown according to js availability
		$(\'#mlsearch_options\').removeClass(\'nojs\');
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

	if (!empty($context['letter_links']))
		echo '
				<span class="floatright letter_links">', $context['letter_links'], '</span>';

	echo '
		</h2>
		<div class="mlist_container">
			<ul class="mlist">
				<li class="mlist_header">';

	$table_span = 0;

	// Display each of the column headers of the table.
	foreach ($context['columns'] as $key => $column)
	{
		$table_span += isset($column['colspan']) ? $column['colspan'] : 1;

		// This is a selected column, so underline it or some such.
		if ($column['selected'])
			echo '
					<div class="' . $column['class'] . '">
						<a href="' . $column['href'] . '" rel="nofollow">' . $column['label'] . '</a><img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />
					</div>';
		// This is just some column... show the link and be done with it.
		else
			echo '
					<div class="' . $column['class'] . '">
						', $column['link'], '
					</div>';
	}

	echo '
				</li>';

	// Assuming there are members loop through each one displaying their data.
	$alternate = true;
	if (!empty($context['members']))
	{
		foreach ($context['members'] as $member)
		{
			if (!empty($member['sort_letter']))
			{
				echo '
			<li class="letter_row" id="letter', $member['sort_letter'], '">
				<h3>', $member['sort_letter'], '</h3>
			</li>';
			}

			echo '
				<li class="', $alternate ? 'alternate_' : 'standard_', 'row">';

			foreach ($context['columns'] as $column => $values)
			{
				if (isset($member[$column]))
				{
					echo '
					<div class="' . $values['class'] . '">';

					if ($column == 'online')
					{
						echo '
						', $context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['text'] . '">' : '', $settings['use_image_buttons'] ? '<img src="' . $member['online']['image_href'] . '" alt="' . $member['online']['text'] . '" />' : $member['online']['label'], $context['can_send_pm'] ? '</a>' : '';
					}
					elseif ($column == 'email_address')
					{
						echo '
					', $member['show_email'] == 'no' ? '' : '<a href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '" rel="nofollow"><img src="' . $settings['images_url'] . '/profile/email_sm.png" alt="' . $txt['email'] . '" title="' . $txt['email'] . ' ' . $member['name'] . '" /></a>';
					}
					else
						echo '
					', $member[$column];

					echo '
					</div>';
				}
				// Any custom fields on display?
				elseif (!empty($context['custom_profile_fields']['columns']) && isset($context['custom_profile_fields']['columns'][$column]))
				{
					echo '
					<div class="' . $values['class'] . '">', $member['options'][substr($column, 5)], '</div>';
				}
			}

			echo '
				</li>';

			$alternate = !$alternate;
		}

		echo '
			</ul>
		</div>';
	}
	// No members?
	else
		echo '
			</ul>
		</div>
		<div class="infobox">
			', $txt['search_no_results'], '
		</div>';
}

/**
 * Shows the pagination
 */
function template_mlsearch_below()
{
	global $context, $scripturl, $txt;

	// Show the page numbers again. (makes 'em easier to find!)
	template_pagesection(false, false);

	echo '
	</div>';
}