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
 * @version 2.0 dev
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
			<h2 class="category_header">
				<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=error_log" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a> ', $txt['errlog'], '
			</h2>
			<div class="flow_auto">
				<div class="floatleft">';

	template_pagesection();

	echo '
				</div>
				<div class="submitbutton">
					<input type="submit" name="removeSelection" value="' . $txt['remove_selection'] . '" onclick="return confirm(\'' . $txt['remove_selection_confirm'] . '\');" />
					<input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="return confirm(\'', $context['has_filter'] ? $txt['remove_filtered_results_confirm'] : $txt['sure_about_errorlog_remove'], '\');" />
				</div>
			</div>';

	echo '
			<table class="table_grid" id="error_log">
				<tr>
					<td colspan="3">
						&nbsp;&nbsp;', $txt['apply_filter_of_type'], ':';

	$error_types = array();
	foreach ($context['error_types'] as $type => $details)
		$error_types[] = ($details['is_selected'] ? '<img src="' . $settings['images_url'] . '/selected.png" alt="" /> ' : '') . '<a href="' . $details['url'] . '" ' . ($details['is_selected'] ? 'class="selected"' : '') . ' title="' . $details['description'] . '">' . $details['label'] . '</a>';

	echo '
						', implode('&nbsp;|&nbsp;', $error_types), '
					</td>
				</tr>';

	if ($context['has_filter'])
		echo '
				<tr>
					<td colspan="3">
						<strong>&nbsp;&nbsp;', $txt['applying_filter'], ':</strong> ', $context['filter']['entity'], ' ', $context['filter']['value']['html'], '&nbsp;&nbsp;[<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', '">', $txt['clear_filter'], '</a>]
					</td>
				</tr>';

	echo '
				<tr class="secondary_header">
					<td colspan="3" class="righttext">
						<label for="check_all1"><strong>', $txt['check_all'], '</strong></label>&nbsp;
						<input type="checkbox" id="check_all1" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all2.checked = this.checked;" />
					</td>
				</tr>';

	// No errors, then show a message
	if (count($context['errors']) == 0)
		echo '
				<tr>
					<td class="centertext" colspan="3">', $txt['errlog_no_entries'], '</td>
				</tr>';

	// We have some errors, show them...
	foreach ($context['errors'] as $error)
	{
		echo '
				<tr>
					<td class="grid60">
						<div>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=error_type;value=', $error['error_type']['type'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '" class="nosel icon i-search"></a>
							', $txt['error_type'], ': ', $error['error_type']['name'], '
						</div>
						<div>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=message;value=', $error['message']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '" class="nosel icon i-search"></a>
						', $error['message']['html'], '
						</div>
						<div>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=url;value=', $error['url']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '" class="nosel icon i-search"></a>
							<a href="', $error['url']['html'], '">', $error['url']['html'], '</a>
						</div>';

		if (!empty($error['file']))
			echo '
						<div>
							<a class="scope" href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=file;value=', $error['file']['search'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '" class="nosel icon i-search"></a>
							', $txt['file'], ': ', $error['file']['link'], '<br />
							', $txt['line'], ': ', $error['file']['line'], '
						</div>';

		echo '
					</td>
					<td>
						<div>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $error['member']['id'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '" class="nosel icon i-search"></a>
							<strong>', $error['member']['link'], '</strong>
						</div>
						<div>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? '' : ';desc', $context['has_filter'] ? $context['filter']['href'] : '', '" title="', $txt['reverse_direction'], '"><i class="nosel icon icon-small i-sort-numeric-', $context['sort_direction'], '" title="', $txt['reverse_direction'], '"></i></a>
							', $error['time'], '
						</div>
						<div>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $error['member']['ip'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '" class="nosel icon i-search"></a>
							<strong><a href="', $scripturl, '?action=trackip;searchip=', $error['member']['ip'], '">', $error['member']['ip'], '</a></strong>
						</div>';

		if ($error['member']['session'] != '')
			echo '
						<div>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=session;value=', $error['member']['session'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_session'], '" class="nosel icon i-search"></a>
							', $error['member']['session'], '
						</div>';

		echo '
					</td>
					<td class="checkbox_column">
						<input type="checkbox" name="delete[]" value="', $error['id'], '" />
					</td>
				</tr>';
	}

	echo '
				<tr class="secondary_header">
					<td colspan="3" class="righttext">
						<label for="check_all2"><strong>', $txt['check_all'], '</strong></label>&nbsp;
						<input type="checkbox" id="check_all2" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all1.checked = this.checked;" />
					</td>
				</tr>
			</table>
			<div class="flow_auto">
				<div class="floatleft">';

	template_pagesection();

	echo '
				</div>
				<div class="submitbutton">
					<input type="submit" name="removeSelection" value="' . $txt['remove_selection'] . '" onclick="return confirm(\'' . $txt['remove_selection_confirm'] . '\');" />
					<input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="return confirm(\'', $context['has_filter'] ? $txt['remove_filtered_results_confirm'] : $txt['sure_about_errorlog_remove'], '\');" />';

	if ($context['sort_direction'] == 'down')
		echo '
					<input type="hidden" name="desc" value="1" />';

	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-el_token_var'], '" value="', $context['admin-el_token'], '" />
				</div>
			</div>
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
		<style>
			body {
				color: #222;
				background-color: #FAFAFA;
				font-family: Verdana, arial, helvetica, serif;
				font-size: small;
			}
			a {color: #49643D;}
			.curline {background: #ffe; display: inline-block; font-weight: bold;}
			.lineno {color:#222;}
		</style>
	</head>
	<body>
		<div style="overflow: auto;"><pre style="margin: 0;">';

	foreach ($context['file_data']['contents'] as $line => $content)
	{
		printf(
			'<span class="lineno">%d:</span> ',
			$line
		);

		echo $content, "\n";
	}

	echo '
		</pre></div>
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
		<div class="content">';

	foreach ($context['attachment_error_keys'] as $key)
		template_show_error($key);

	echo '
		</div>
	</div>';
}
