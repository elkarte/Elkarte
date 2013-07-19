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
 * Interface to allow to choose which type of report to run.
 */
function template_report_type()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=reports" method="post" accept-charset="', $context['character_set'], '">
			<div id="generate_reports_type">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['generate_reports_type'], '</h3>
				</div>
				<div class="windowbg">
					<div class="content">
						<dl class="generate_report">';

	// Go through each type of report they can run.
	foreach ($context['report_types'] as $type)
	{
		echo '
							<dt>
								<input type="radio" id="rt_', $type['id'], '" name="rt" value="', $type['id'], '"', $type['is_first'] ? ' checked="checked"' : '', ' class="input_radio" />
								<strong><label for="rt_', $type['id'], '">', $type['title'], '</label></strong>
							</dt>';
		if (isset($type['description']))
			echo '
							<dd>', $type['description'], '</dd>';
	}

	echo '
						</dl>
						<input type="submit" name="continue" value="', $txt['generate_reports_continue'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					</div>
				</div>
			</div>
		</form>
	</div>';
}

/**
 * This is the standard template for showing reports in.
 */
function template_main()
{
	global $context, $txt;

	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['results'], '</h3>
		</div>
		<div id="report_buttons">';

	if (!empty($context['report_buttons']))
		template_button_strip($context['report_buttons'], 'right');

	echo '
		</div>
		<div class="generic_list_wrapper">';

	// Go through each table!
	foreach ($context['tables'] as $table)
	{
		echo '
		<table class="table_grid report_results">';

		if (!empty($table['title']))
			echo '
			<thead>
				<tr class="table_head">
					<th scope="col" colspan="', $table['column_count'], '">', $table['title'], '</th>
				</tr>
			</thead>
			<tbody>';

		// Now do each row!
		$row_number = 0;
		$alternate = false;
		foreach ($table['data'] as $row)
		{
			if ($row_number == 0 && !empty($table['shading']['top']))
				echo '
				<tr class="windowbg table_caption">';
			else
				echo '
				<tr class="', !empty($row[0]['separator']) ? 'catbg' : ($alternate ? 'windowbg' : 'windowbg2'), '" style="vertical-align:top">';

			// Now do each column.
			$column_number = 0;

			foreach ($row as $key => $data)
			{
				// If this is a special separator, skip over!
				if (!empty($data['separator']) && $column_number == 0)
				{
					echo '
					<td colspan="', $table['column_count'], '" class="smalltext">
						', $data['v'], ':
					</td>';
					break;
				}

				// Shaded?
				if ($column_number == 0 && !empty($table['shading']['left']))
					echo '
					<td class="table_caption" style="text-align:', $table['align']['shaded'], $table['width']['shaded'] != 'auto' ? ';width:' . $table['width']['shaded'] . '"' : '"', '>
						', $data['v'] == $table['default_value'] ? '' : ($data['v'] . (empty($data['v']) ? '' : ':')), '
					</td>';
				else
					echo '
					<td class="smalltext" style="text-align:', $table['align']['normal'], $table['width']['normal'] != 'auto' ? ';width:' . $table['width']['normal'] . '' : '', !empty($data['style']) ? ';' . $data['style'] . '"' : '"', '>
						', $data['v'], '
					</td>';

				$column_number++;
			}

			echo '
				</tr>';

			$row_number++;
			$alternate = !$alternate;
		}

		echo '
			</tbody>
		</table>
		<br />';
	}

	echo '
		</div>
	</div>';
}

/**
 * Header of the print page.
 */
function template_print_above()
{
	global $context, $settings;

	echo '<!DOCTYPE html>
<html ', $context['right_to_left'] ? 'dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/report.css" />
	</head>
	<body>';
}

/**
 * Print a report.
 */
function template_print()
{
	global $context;

	// Go through each table!
	foreach ($context['tables'] as $table)
	{
		echo '
		<div style="overflow: visible;', $table['max_width'] != 'auto' ? ' width:' . $table['max_width'] . 'px;' : '', '">
			<table class="table_grid">';

		if (!empty($table['title']))
			echo '
				<tr class="table_head">
					<td colspan="', $table['column_count'], '">
						', $table['title'], '
					</td>
				</tr>';

		// Now do each row!
		$alternate = false;
		$row_number = 0;
		foreach ($table['data'] as $row)
		{
			if ($row_number == 0 && !empty($table['shading']['top']))
				echo '
				<tr class="titlebg" style="vertical-align:top">';
			else
				echo '
				<tr class="', $alternate ? 'windowbg' : 'windowbg2', '" style="vertical-align:top">';

			// Now do each column!!
			$column_number = 0;
			foreach ($row as $key => $data)
			{
				// If this is a special separator, skip over!
				if (!empty($data['separator']) && $column_number == 0)
				{
					echo '
					<td colspan="', $table['column_count'], '" class="catbg">
						<strong>', $data['v'], ':</strong>
					</td>';
					break;
				}

				// Shaded?
				if ($column_number == 0 && !empty($table['shading']['left']))
					echo '
					<td class="titlebg" style="text-align:', $table['align']['shaded'], $table['width']['shaded'] != 'auto' ? ';width:' . $table['width']['shaded'] . '"' : '"', '>
						', $data['v'] == $table['default_value'] ? '' : ($data['v'] . (empty($data['v']) ? '' : ':')), '
					</td>';
				else
					echo '
					<td style="text-align:', $table['align']['normal'], $table['width']['normal'] != 'auto' ? ';width:' . $table['width']['normal'] : '', !empty($data['style']) ? ';' . $data['style'] . '"' : '"', '>
						', $data['v'], '
					</td>';

				$column_number++;
			}

			echo '
				</tr>';

			$row_number++;
			$alternate = !$alternate;
		}

		echo '
			</table>
		</div><br />';
	}
}

/**
 * Footer of the print page.
 */
function template_print_below()
{
	echo '
		<div class="copyright">', theme_copyright(), '</div>
	</body>
</html>';
}