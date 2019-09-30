<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

/**
 * Template used to show a list created with createlist
 *
 * @param string|null $list_id
 */
function template_show_list($list_id = null)
{
	global $context, $txt;

	// Get a shortcut to the current list.
	$list_id = $list_id === null ? $context['default_list'] : $list_id;
	$cur_list = &$context[$list_id];

	if (isset($cur_list['form']))
	{
		echo '
	<form class="generic_list_wrapper" action="', $cur_list['form']['href'], '" method="post"', empty($cur_list['form']['name']) ? '' : ' name="' . $cur_list['form']['name'] . '" id="' . $cur_list['form']['name'] . '"', ' accept-charset="UTF-8">
		<div class="generic_list">';
	}
	else
	{
		echo '
		<div id="wrapper_', $list_id, '" class="generic_list_wrapper">';
	}

	// Show the title of the table (if any), with an icon (if defined)
	if (!empty($cur_list['title']))
	{
		echo '
			<h2 class="category_header', !empty($cur_list['icon']) ? ' hdicon cat_img_' . $cur_list['icon'] : '', '">', $cur_list['title'], '</h2>';
	}

	// Show any data right after the title
	if (isset($cur_list['additional_rows']['after_title']))
	{
		echo '
			<div class="information flow_hidden">';

		template_additional_rows('after_title', $cur_list);

		echo '
			</div>';
	}

	// Show some data above this list
	if (isset($cur_list['additional_rows']['top_of_list']))
	{
		template_additional_rows('top_of_list', $cur_list);
	}

	$close_div = false;
	if (isset($cur_list['additional_rows']['above_column_headers']))
	{
		$close_div = true;
		echo '
			<div class="flow_auto">', template_additional_rows('above_column_headers', $cur_list);
	}

	// These are the main tabs that is used all around the template.
	if (isset($cur_list['list_menu'], $cur_list['list_menu']['show_on']) && ($cur_list['list_menu']['show_on'] == 'both' || $cur_list['list_menu']['show_on'] == 'top'))
	{
		if (!$close_div)
		{
			echo '
			<div class="flow_auto">';
		}

		$close_div = true;

		template_create_list_menu($cur_list['list_menu']);
	}

	// Show the page index (if this list doesn't intend to show all items). @todo - Needs old top/bottom stuff cleaned up.
	if (!empty($cur_list['items_per_page']) && !empty($cur_list['page_index']))
	{
		if (!$close_div)
		{
			echo '
			<div class="flow_auto">';
		}

		echo '
				<div class="floatleft">', template_pagesection(false, '', array('page_index_markup' => $cur_list['page_index'])), '
				</div>';
		$close_div = true;
	}

	if ($close_div)
	{
		echo '
			</div>';
	}

	// Start of the main table
	echo '
			<table id="' . $list_id . '" class="table_grid"', !empty($cur_list['width']) ? ' style="width: ' . $cur_list['width'] . '"' : '', '>';

	// Show the column headers.
	$header_count = count($cur_list['headers']);
	if (!($header_count < 2 && empty($cur_list['headers'][0]['label'])))
	{
		echo '
			<thead>
				<tr class="table_head">';

		// Loop through each column and add a table header.
		$i = 0;
		foreach ($cur_list['headers'] as $col_header)
		{
			$i++;
			if ($i === 1)
			{
				$col_header['class'] = empty($col_header['class']) ? '' : $col_header['class'];
			}
			elseif ($i === $header_count)
			{
				$col_header['class'] = empty($col_header['class']) ? '' : $col_header['class'];
			}

			$sort_title = $col_header['sort_image'] === 'up' ? $txt['sort_desc'] : $txt['sort_asc'];

			echo '
					<th scope="col" id="header_', $list_id, '_', $col_header['id'], '"', empty($col_header['class']) ? '' : ' class="' . $col_header['class'] . '"', empty($col_header['style']) ? '' : ' style="' . $col_header['style'] . '"', empty($col_header['colspan']) ? '' : ' colspan="' . $col_header['colspan'] . '"', '>', empty($col_header['href']) ? '' : '<a href="' . $col_header['href'] . '" rel="nofollow">', empty($col_header['label']) ? '&nbsp;' : $col_header['label'], empty($col_header['href']) ? '' : (empty($col_header['sort_image']) ? '</a>' : ' <i class="sort icon i-sort-amount-' . $col_header['sort_image'] . '" title="' . $sort_title . '"></i></a>'), '</th>';
		}

		echo '
				</tr>
			</thead>';
	}

	echo '
			<tbody', empty($cur_list['sortable']) ? '' : ' id="table_grid_sortable"', '>';

	// Show a nice message informing there are no items in this list.
	if (empty($cur_list['rows']) && !empty($cur_list['no_items_label']))
	{
		echo '
				<tr>
					<td colspan="', $cur_list['num_columns'], '">
						<div class="', !empty($cur_list['no_items_align']) ? $cur_list['no_items_align'] : 'centertext', '">', $cur_list['no_items_label'], '</div>
					</td>
				</tr>';
	}

	// Show the list rows.
	elseif (!empty($cur_list['rows']))
	{
		foreach ($cur_list['rows'] as $id => $row)
		{
			echo '
				<tr class="standard_row ', $row['class'], '" id="list_', $list_id, '_', str_replace(' ', '_', $id), '">';

			foreach ($row['data'] as $row_data)
			{
				echo '
					<td', empty($row_data['class']) ? '' : ' class="' . $row_data['class'] . '"', empty($row_data['style']) ? '' : ' style="' . $row_data['style'] . '"', '>', $row_data['value'], '</td>';
			}

			echo '
				</tr>';
		}
	}

	echo '
			</tbody>
			</table>';

	echo '
			<div class="flow_auto">';

	// Do we have multiple pages to show or data to show below the table
	if ((!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])) || isset($cur_list['additional_rows']['below_table_data']))
	{
		// Show the page index (if this list doesn't intend to show all items).
		if (!empty($cur_list['items_per_page']) && !empty($cur_list['page_index']))
		{
			echo '
				<div class="floatleft">',
			template_pagesection(false, '', array('page_index_markup' => $cur_list['page_index'])), '
				</div>';
		}

		if (isset($cur_list['additional_rows']['below_table_data']))
		{
			template_additional_rows('below_table_data', $cur_list);
		}
	}

	// Tabs at the bottom.  Usually bottom aligned.
	if (isset($cur_list['list_menu'], $cur_list['list_menu']['show_on']) && ($cur_list['list_menu']['show_on'] == 'both' || $cur_list['list_menu']['show_on'] == 'bottom'))
	{
		template_create_list_menu($cur_list['list_menu']);
	}

	echo '
			</div>';

	// Last chance to show more data, like buttons and links
	if (isset($cur_list['additional_rows']['bottom_of_list']))
	{
		template_additional_rows('bottom_of_list', $cur_list);
	}

	if (isset($cur_list['form']))
	{
		foreach ($cur_list['form']['hidden_fields'] as $name => $value)
		{
			echo '
			<input type="hidden" name="', $name, '" value="', $value, '" />';
		}

		echo '
		</div>
	</form>';
	}
	else
	{
		echo '
		</div>';
	}
}

/**
 * Generic template used to show additional rows of data (above/below)
 *
 * @param int $row_position
 * @param mixed[] $cur_list
 *
 * @return string as echoed output
 */
function template_additional_rows($row_position, $cur_list)
{
	foreach ($cur_list['additional_rows'][$row_position] as $row)
	{
		echo '
				<div class="additional_row ', $row_position, empty($row['class']) ? '' : ' ' . $row['class'], '"', empty($row['style']) ? '' : ' style="' . $row['style'] . '"', '>', $row['value'], '</div>';
	}
}

/**
 * Used this if you want your generic lists to have navigation menus.
 *
 * $cur_list['list_menu'] = array(
 *    // The position of the tabs/buttons.  Left or Right.  By default is set to left.
 *    'position' => 'left',
 *    // Links.  This is the core of the array.  It has all the info that we need.
 *    'links' => array(
 *      'name' => array(
 *        // This will tell use were to go when they click it.
 *        'href' => $scripturl . '?action=theaction',
 *        // The name that you want to appear for the link.
 *        'label' => $txt['name'],
 *        // If we use tabs instead of buttons we highlight the current tab.
 *        // Must use conditions to determine if its selected or not.
 *        'is_selected' => isset($_REQUEST['name']),
 *      ),
 *    ),
 * );
 *
 * @param mixed[] $list_menu
 */
function template_create_list_menu($list_menu)
{
	echo '
		<ul class="generic_menu float', $list_menu['position'], empty($list_menu['class']) ? '' : ' ' . $list_menu['class'], '"', empty($list_menu['style']) ? '' : ' style="' . $list_menu['style'] . '"', '>';

	foreach ($list_menu['links'] as $link)
	{
		echo '
			<li class="listlevel1">
				<a class="linklevel1', $link['is_selected'] ? ' active' : '', '" href="', !empty($link['href']) ? $link['href'] : '#', '">',
				$link['label'], !empty($link['href']) ? '</a>' : '', '
			</li>';
	}

	echo '
		</ul>';
}
