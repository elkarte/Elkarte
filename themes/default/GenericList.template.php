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

function template_show_list($list_id = null)
{
	global $context, $settings;

	// Get a shortcut to the current list.
	$list_id = $list_id === null ? $context['default_list'] : $list_id;
	$cur_list = &$context[$list_id];

	if (isset($cur_list['form']))
		echo '
	<form class="generic_list_wrapper" action="', $cur_list['form']['href'], '" method="post"', empty($cur_list['form']['name']) ? '' : ' name="' . $cur_list['form']['name'] . '" id="' . $cur_list['form']['name'] . '"', ' accept-charset="UTF-8">
		<div class="generic_list">';

	else
		echo '
		<div class="generic_list_wrapper">';

	// Show the title of the table (if any).
	if (!empty($cur_list['title']))
		echo '
			<div class="title_bar clear_right">
				<h3 class="titlebg">
					', $cur_list['title'], '
				</h3>
			</div>';

	if (isset($cur_list['additional_rows']['after_title']))
	{
		echo '
			<div class="information flow_hidden">';
		template_additional_rows('after_title', $cur_list);
		echo '
			</div>';
	}

	if (isset($cur_list['additional_rows']['top_of_list']))
		template_additional_rows('top_of_list', $cur_list);

	$close_div = false;
	if ((!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])) || isset($cur_list['additional_rows']['above_column_headers']))
	{
		$close_div = true;
		echo '
			<div class="flow_auto">';

		// Show the page index (if this list doesn't intend to show all items).
		if (!empty($cur_list['items_per_page']) && !empty($cur_list['page_index']))
			echo '
				<div class="floatleft">',
					template_pagesection(false, false, 'go_down', array('page_index_markup' => $cur_list['page_index'], 'top_button' => !empty($cur_list['show_topbot']))), '
				</div>';

		if (isset($cur_list['additional_rows']['above_column_headers']))
			template_additional_rows('above_column_headers', $cur_list);
	}

	// These are the main tabs that is used all around the template.
	if (isset($cur_list['list_menu'], $cur_list['list_menu']['show_on']) && ($cur_list['list_menu']['show_on'] == 'both' || $cur_list['list_menu']['show_on'] == 'top'))
	{
		if (!$close_div)
			echo '
			<div class="flow_auto">';
		$close_div = true;

		template_create_list_menu($cur_list['list_menu'], 'top');
	}

	if ($close_div)
		echo '
			</div>';

	echo '
			<table class="table_grid" style="width:', !empty($cur_list['width']) ? $cur_list['width'] : '100%', '">';

	// Show the column headers.
	$header_count = count($cur_list['headers']);
	if (!($header_count < 2 && empty($cur_list['headers'][0]['label'])))
	{
		echo '
			<thead>
				<tr class="catbg">';

		// Loop through each column and add a table header.
		$i = 0;
		foreach ($cur_list['headers'] as $col_header)
		{
			$i ++;
			if ($i === 1)
				$col_header['class'] = empty($col_header['class']) ? 'first_th' : 'first_th ' . $col_header['class'];
			elseif ($i === $header_count)
				$col_header['class'] = empty($col_header['class']) ? 'last_th' : 'last_th ' . $col_header['class'];

			echo '
					<th scope="col" id="header_', $list_id, '_', $col_header['id'], '"', empty($col_header['class']) ? '' : ' class="' . $col_header['class'] . '"', empty($col_header['style']) ? '' : ' style="' . $col_header['style'] . '"', empty($col_header['colspan']) ? '' : ' colspan="' . $col_header['colspan'] . '"', '>', empty($col_header['href']) ? '' : '<a href="' . $col_header['href'] . '" rel="nofollow">', empty($col_header['label']) ? '&nbsp;' : $col_header['label'], empty($col_header['href']) ? '' : (empty($col_header['sort_image']) ? '</a>' : ' <img class="sort" src="' . $settings['images_url'] . '/sort_' . $col_header['sort_image'] . '.png" alt="" /></a>'), '</th>';
		}

		echo '
				</tr>
			</thead>';
	}

		echo '
			<tbody>';

	// Show a nice message informing there are no items in this list.
	if (empty($cur_list['rows']) && !empty($cur_list['no_items_label']))
		echo '
				<tr>
					<td class="windowbg" colspan="', $cur_list['num_columns'], '" style="text-align:', !empty($cur_list['no_items_align']) ? $cur_list['no_items_align'] : 'center', '"><div class="padding">', $cur_list['no_items_label'], '</div></td>
				</tr>';

	// Show the list rows.
	elseif (!empty($cur_list['rows']))
	{
		$alternate = false;
		foreach ($cur_list['rows'] as $id => $row)
		{
			echo '
				<tr class="windowbg', $alternate ? '2' : '', $row['class'], '"', $row['style'], ' id="list_', $list_id, '_', $id, '">';

			foreach ($row['data'] as $row_data)
				echo '
					<td', empty($row_data['class']) ? '' : ' class="' . $row_data['class'] . '"', empty($row_data['style']) ? '' : ' style="' . $row_data['style'] . '"', '>', $row_data['value'], '</td>';

			echo '
				</tr>';

			$alternate = !$alternate;
		}
	}

	echo '
			</tbody>
			</table>';

	$close_div = false;
	if ((!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])) || isset($cur_list['additional_rows']['below_table_data']))
	{
		$close_div = true;
		echo '
			<div class="flow_auto">';

		// Show the page index (if this list doesn't intend to show all items).
		if (!empty($cur_list['items_per_page']) && !empty($cur_list['page_index']))
			echo '
				<div class="floatleft">',
					template_pagesection(false, false, 'go_down', array('page_index_markup' => $cur_list['page_index'], 'top_button' => !empty($cur_list['show_topbot']))), '
				</div>';

		if (isset($cur_list['additional_rows']['below_table_data']))
			template_additional_rows('below_table_data', $cur_list);
	}

	// Tabs at the bottom.  Usually bottom alligned.
	if (isset($cur_list['list_menu'], $cur_list['list_menu']['show_on']) && ($cur_list['list_menu']['show_on'] == 'both' || $cur_list['list_menu']['show_on'] == 'bottom'))
	{
		if (!$close_div)
			echo '
			<div class="flow_auto">';
		$close_div = true;

		template_create_list_menu($cur_list['list_menu'], 'bottom');
	}

	if ($close_div)
		echo '
			</div>';

	if (isset($cur_list['additional_rows']['bottom_of_list']))
		template_additional_rows('bottom_of_list', $cur_list);

	if (isset($cur_list['form']))
	{
		foreach ($cur_list['form']['hidden_fields'] as $name => $value)
			echo '
			<input type="hidden" name="', $name, '" value="', $value, '" />';

		echo '
		</div>
	</form>';
	}

	else
		echo '
		</div>';

	if (isset($cur_list['javascript']))
		echo '
	<script><!-- // --><![CDATA[
		', $cur_list['javascript'], '
	// ]]></script>';
}

function template_additional_rows($row_position, $cur_list)
{
	foreach ($cur_list['additional_rows'][$row_position] as $row)
		echo '
				<div class="additional_row', empty($row['class']) ? '' : ' ' . $row['class'], '"', empty($row['style']) ? '' : ' style="' . $row['style'] . '"', '>', $row['value'], '</div>';
}

function template_create_list_menu($list_menu, $direction = 'top')
{
	global $settings;

	/**
		// This is use if you want your generic lists to have tabs.
		$cur_list['list_menu'] = array(
			// The posisiton of the tabs/buttons.  Left or Right.  By default is set to left.
			'position' => 'left',
			// This gives you the option to show tabs only at the top, bottom or both.
			// By default they are just shown at the top.
			'show_on' => 'top',
			// Links.  This is the core of the array.  It has all the info that we need.
			'links' => array(
				'name' => array(
					// This will tell use were to go when they click it.
					'href' => $scripturl . '?action=theaction',
					// The name that you want to appear for the link.
					'label' => $txt['name'],
					// If we use tabs instead of buttons we highlight the current tab.
					// Must use conditions to determine if its selected or not.
					'is_selected' => isset($_REQUEST['name']),
				),
			),
		);
	*/

	echo '
			<div class="float', $list_menu['position'], ' additional_row', empty($list_menu['class']) ? '' : ' ' . $list_menu['class'], '"', empty($list_menu['style']) ? '' : ' style="' . $list_menu['style'] . '"', '>';
	$items = count($list_menu['links']);
	$count = 0;

	foreach ($list_menu['links'] as $link)
	{
		$count++;
		if (!empty($link['href']))
		echo '
				<a href="', $link['href'], '">', $link['is_selected'] ? '<img src="' . $settings['images_url'] . '/selected.png" alt="&gt;" /> ' : '';

		echo $link['label'], !empty($link['href']) ? '</a>' : '', $items !== $count ? '&nbsp;|&nbsp;' : '';
	}
	echo '
			</div>';
}