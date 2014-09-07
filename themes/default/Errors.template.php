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
 * @version 1.0
 *
 */

/**
 * Show an error message.....
 *
 * It is shown when an error occurs, and should show at least a back
 * button and $context['error_message'].
 */
function template_fatal_error()
{
	global $context, $txt;

	echo '
	<div id="fatal_error">
		<h2 class="category_header">', $context['error_title'], '</h2>
		<div class="generic_list_wrapper">
			<div class="errorbox" ', $context['error_code'], '>', $context['error_message'], '</div>
		</div>
	</div>
	<div class="centertext">
		<a class="linkbutton" href="javascript:window.location.assign(document.referrer);">', $txt['back'], '</a>
	</div>';
}

/**
 * Shows the forum error log in all its detail
 * Supports filtering for viewing all errors of a 'type'
 */
function template_error_log()
{
	global $context, $settings, $scripturl, $txt;

	echo '
		<form class="generic_list_wrapper" action="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';start=', $context['start'], $context['has_filter'] ? $context['filter']['href'] : '', '" method="post" accept-charset="UTF-8">
			<h3 class="category_header">
				<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=error_log" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a> ', $txt['errlog'], '
			</h3>
			<div class="flow_auto">
				<div class="floatleft">';

	template_pagesection();

	echo '
				</div>
				<div class="additional_row floatright">
					<input type="submit" name="removeSelection" value="' . $txt['remove_selection'] . '" onclick="return confirm(\'' . $txt['remove_selection_confirm'] . '\');" class="button_submit" />
					<input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="return confirm(\'', $context['has_filter'] ? $txt['remove_filtered_results_confirm'] : $txt['sure_about_errorlog_remove'], '\');" class="button_submit" />
				</div>
			</div>';

	echo '
			<table class="table_grid" id="error_log">
				<tr>
					<td colspan="3" class="windowbg">
						&nbsp;&nbsp;', $txt['apply_filter_of_type'], ':';

	$error_types = array();
	foreach ($context['error_types'] as $type => $details)
		$error_types[] = ($details['is_selected'] ? '<img src="' . $settings['images_url'] . '/selected.png" alt="" /> ' : '') . '<a href="' . $details['url'] . '" ' . ($details['is_selected'] ? 'style="font-weight: bold;"' : '') . ' title="' . $details['description'] . '">' . $details['label'] . '</a>';

	echo '
						', implode('&nbsp;|&nbsp;', $error_types), '
					</td>
				</tr>';

	if ($context['has_filter'])
		echo '
				<tr>
					<td colspan="3" class="windowbg">
						<strong>&nbsp;&nbsp;', $txt['applying_filter'], ':</strong> ', $context['filter']['entity'], ' ', $context['filter']['value']['html'], '&nbsp;&nbsp;[<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', '">', $txt['clear_filter'], '</a>]
					</td>
				</tr>';

	echo '
				<tr class="secondary_header">
					<td colspan="3" class="righttext" style="padding: 4px 8px;">
						<label for="check_all1"><strong>', $txt['check_all'], '</strong></label>&nbsp;
						<input type="checkbox" id="check_all1" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all2.checked = this.checked;" class="input_check" />
					</td>
				</tr>';

	// No errors, then show a message
	if (count($context['errors']) == 0)
		echo '
				<tr class="windowbg">
					<td class="centertext" colspan="2">', $txt['errlog_no_entries'], '</td>
				</tr>';

	// We have some errors, show them...
	foreach ($context['errors'] as $error)
	{
		echo '
				<tr class="windowbg', $error['alternate'] ? '2' : '', '">
					<td>

						<div class="error_who">
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $error['member']['id'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '" /></a>
							<strong>', $error['member']['link'], '</strong><br />
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? '' : ';desc', $context['has_filter'] ? $context['filter']['href'] : '', '" title="', $txt['reverse_direction'], '"><img src="', $settings['images_url'], '/sort_', $context['sort_direction'], '.png" alt="', $txt['reverse_direction'], '" /></a>
							', $error['time'], '<br />
							<a  href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $error['member']['ip'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '" /></a>
							<strong><a href="', $scripturl, '?action=trackip;searchip=', $error['member']['ip'], '">', $error['member']['ip'], '</a></strong>&nbsp;&nbsp;<br />
						</div>

						<div class="error_type">';

		if ($error['member']['session'] != '')
			echo '
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=session;value=', $error['member']['session'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_session'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['apply_filter'], ': ', $txt['filter_only_session'], '" /></a>
							', $error['member']['session'], '<br />
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=error_type;value=', $error['error_type']['type'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '" /></a>
							', $txt['error_type'], ': ', $error['error_type']['name'], '<br />
							<a style="display: table-cell; padding: 4px 0; width: 20px; vertical-align: top;" href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=message;value=', $error['message']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '" /></a>
							<span style="display: table-cell;">', $error['message']['html'], '</span>';

		echo '
						</div>

						<div class="error_where">
							<a class="scope" href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=url;value=', $error['url']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '" /></a>
							<a style="display: table-cell;" href="', $error['url']['html'], '">', $error['url']['html'], '</a>
						</div>';

		if (!empty($error['file']))
			echo '
						<div class="error_where">
							<a class="scope" href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=file;value=', $error['file']['search'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '" /></a>
							<div style="display: table-cell;">
								', $txt['file'], ': ', $error['file']['link'], '<br />
								', $txt['line'], ': ', $error['file']['line'], '
							</div>
						</div>';

		echo '
					</td>
					<td class="checkbox_column">
						<input type="checkbox" name="delete[]" value="', $error['id'], '" class="input_check" />
					</td>
					<td></td>
				</tr>';
	}

	echo '
				<tr class="secondary_header">
					<td colspan="3" class="righttext" style="padding-right: 1.2ex">
						<label for="check_all2"><strong>', $txt['check_all'], '</strong></label>&nbsp;
						<input type="checkbox" id="check_all2" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all1.checked = this.checked;" class="input_check" />
					</td>
				</tr>
			</table>
			<div class="flow_auto">
				<div class="floatleft">';

	template_pagesection();

	echo '
				</div>
				<div class="additional_row floatright">
					<input type="submit" name="removeSelection" value="' . $txt['remove_selection'] . '" onclick="return confirm(\'' . $txt['remove_selection_confirm'] . '\');" class="button_submit" />
					<input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="return confirm(\'', $context['has_filter'] ? $txt['remove_filtered_results_confirm'] : $txt['sure_about_errorlog_remove'], '\');" class="button_submit" />
				</div>
			</div>';

	if ($context['sort_direction'] == 'down')
		echo '
			<input type="hidden" name="desc" value="1" />';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['admin-el_token_var'], '" value="', $context['admin-el_token'], '" />
		</form>';
}

/**
 * Shows the subsection of a file where an error occurred
 */
function template_show_file()
{
	global $context, $settings;

	echo '<!DOCTYPE html>
<html ', $context['right_to_left'] ? 'dir="rtl"' : '', '>
	<head>
		<title>', $context['file_data']['file'], '</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/admin.css', CACHE_STALE, '" />
	</head>
	<body>
		<table id="errorfile_table" class="table_grid">';

	foreach ($context['file_data']['contents'] as $index => $line)
	{
		$line_num = $index + $context['file_data']['min'];
		$is_target = $line_num == $context['file_data']['target'];
		echo '
			<tr>
				<td', $is_target ? ' class="righttext current">==&gt;' : '>', $line_num, ':</td>
				<td style="white-space: nowrap;', $is_target ? ' border: 1px solid black;border-width: 1px 1px 1px 0;' : '', '">', $line, '</td>
			</tr>';
	}

	echo '
		</table>
	</body>
</html>';
}

/**
 * When an attachment fails to upload, this template will show
 * all the issues to the user
 */
function template_attachment_errors()
{
	global $context, $txt;

	echo '
	<div>
		<h2 class="category_header">', $txt['attach_error_title'], '</h2>
		<div class="windowbg">';

	foreach ($context['attachment_error_keys'] as $key)
		template_show_error($key);

	echo '
		</div>
	</div>';
}