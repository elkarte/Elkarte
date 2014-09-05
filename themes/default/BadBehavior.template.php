
<?php

/**
 * This template file contains only the sub template badbehavior_log.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 */

/**
 * Displays the bad behavior 'hit' log
 */
function template_badbehavior_log()
{
	global $context, $settings, $scripturl, $txt;

	echo '
		<form class="generic_list_wrapper" action="', $scripturl, '?action=admin;area=logs;sa=badbehaviorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';start=', $context['start'], $context['has_filter'] ? $context['filter']['href'] : '', '" method="post" accept-charset="UTF-8">
			<h3 class="category_header">
				<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=badbehaviorlog" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a> ', $txt['badbehaviorlog_log'], '
			</h3>
			', template_pagesection(), '
			<table class="table_grid" id="error_log">';

	if ($context['has_filter'])
		echo '
				<tr>
					<td colspan="3" class="windowbg">
						<strong>&nbsp;&nbsp;', $txt['badbehaviorlog_applying_filter'], ':</strong> ', $context['filter']['entity'], ' ', $context['filter']['value']['html'], '&nbsp;&nbsp;[<a href="', $scripturl, '?action=admin;area=logs;sa=badbehaviorlog', $context['sort_direction'] == 'down' ? ';desc' : '', '">', $txt['badbehaviorlog_clear_filter'], '</a>]
					</td>
				</tr>';

	// The checkall box
	echo '
				<tr class="secondary_header">
					<td colspan="3" class="righttext">
						<label for="check_all_1"><strong>', $txt['check_all'], '</strong></label>&nbsp;
						<input type="checkbox" id="check_all_1" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all_2.checked = this.checked;" class="input_check" />
					</td>
				</tr>';

	// No log entries, then show a message
	if (count($context['bb_entries']) == 0)
		echo '
				<tr class="windowbg">
					<td class="centertext" colspan="2">', $txt['badbehaviorlog_no_entries_found'], '</td>
				</tr>';

	// We have some log entries, maybe even some spammers
	$i = 0;
	foreach ($context['bb_entries'] as $entries)
	{
		$i++;
		echo '
				<tr class="windowbg', $entries['alternate'] ? '2' : '', '">
					<td>
						<div class="error_who">
							<a href="', $scripturl, '?action=admin;area=logs;sa=badbehaviorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $entries['member']['id'], '" title="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_member'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_member'], '" /></a>
							<strong>', $entries['member']['link'], '</strong><br />

							<a href="', $scripturl, '?action=admin;area=logs;sa=badbehaviorlog', $context['sort_direction'] == 'down' ? '' : ';desc', $context['has_filter'] ? $context['filter']['href'] : '', '" title="', $txt['badbehaviorlog_reverse_direction'], '"><img src="', $settings['images_url'], '/sort_', $context['sort_direction'], '.png" alt="', $txt['badbehaviorlog_reverse_direction'], '" /></a>
							', $entries['time'], '<br />

							<a href="', $scripturl, '?action=admin;area=logs;sa=badbehaviorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $entries['member']['ip'], '" title="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_ip'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_ip'], '" /></a>
							<strong><a href="', $scripturl, '?action=trackip;searchip=', $entries['member']['ip'], '">', $entries['member']['ip'], '</a></strong>&nbsp;&nbsp;<br />
						</div>
						<div class="error_type">';

		if ($entries['member']['session'] !== '')
			echo '
							<a href="', $scripturl, '?action=admin;area=logs;sa=badbehaviorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=session;value=', $entries['member']['session'], '" title="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_session'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_session'], '" /></a>', $entries['member']['session'], '<br />';

		echo '
							<a href="', $scripturl, '?action=admin;area=logs;sa=badbehaviorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=valid;value=', $entries['valid']['code'], '" title="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_type'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_type'], '" /></a>', $txt['badbehaviorlog_error_valid_response'], ': ', $entries['valid']['response'], '<br />
							<a href="', $scripturl, '?action=admin;area=logs;sa=badbehaviorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=valid;value=', $entries['valid']['code'], '" title="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_type'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_type'], '" /></a>', $txt['badbehaviorlog_error_valid_log'], ': ', $entries['valid']['log'], '<br />
							<a class="bbfilter" href="', $scripturl, '?action=admin;area=logs;sa=badbehaviorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=request_uri;value=', $entries['request_uri']['href'], '" title="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_headers'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_headers'], '" /></a><a class="bbrequest_uri" href="', $entries['request_uri']['html'], '">', $entries['request_uri']['html'], '</a>
						</div>
						<div class="error_where">
							<a class="scope" href="', $scripturl, '?action=admin;area=logs;sa=badbehaviorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=user_agent;value=', $entries['user_agent']['href'], '" title="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_agent'], '"><img src="', $settings['images_url'], '/filter.png" alt="', $txt['badbehaviorlog_apply_filter'], ': ', $txt['badbehaviorlog_filter_only_agent'], '" /></a><span class="bbrequest_uri">', $entries['user_agent']['html'], '</span>
						</div>
						<div class="error_where">
							<a onclick="expandCollapse(\'details', $i, '\', \'icon', $i, '\'); return false;">
							<img id="icon', $i, '" src="', $settings['images_url'], '/selected.png" alt="*" />&nbsp;<strong>', $txt['badbehaviorlog_details'], '</strong></a><div id="details', $i, '" class="padding" style="display: none">', $entries['http_headers']['html'], '</div>
						</div>
					</td>
					<td class="checkbox_column">
						<input type="checkbox" name="delete[]" value="', $entries['id'], '" class="input_check" />
					</td>
				</tr>';
	}

	echo '
				<tr class="secondary_header">
					<td colspan="3" class="righttext">
						<label for="check_all_2"><strong>', $txt['check_all'], '</strong></label>&nbsp;
						<input type="checkbox" id="check_all_2" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all_1.checked = this.checked;" class="input_check" />
					</td>
				</tr>
			</table>
			<div class="flow_auto">
				<div class="floatleft">';

	template_pagesection();

	echo '
				</div>
				<div class="additional_row floatright">
					<input type="submit" name="removeSelection" value="' . $txt['badbehaviorlog_remove_selection'] . '" onclick="return confirm(\'' . $txt['badbehaviorlog_remove_selection_confirm'] . '\');" class="button_submit" />
					<input type="submit" name="delall" value="', $context['has_filter'] ? $txt['badbehaviorlog_remove_filtered_results'] : $txt['remove_all'], '" onclick="return confirm(\'', $context['has_filter'] ? $txt['badbehaviorlog_remove_filtered_results_confirm'] : $txt['badbehaviorlog_sure_remove'], '\');" class="button_submit" />
				</div>
			</div>';

	if ($context['sort_direction'] == 'down')
		echo '
			<input type="hidden" name="desc" value="1" />';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['admin-bbl_token_var'], '" value="', $context['admin-bbl_token'], '" />
		</form>';
}

/**
 * Template to add an IP to the BB whitelist
 */
function template_callback_badbehavior_add_ip()
{
	global $txt, $context, $scripturl, $settings;

	// Whitelist by IP
	echo '
		</dl>
		<hr />
		<a href="' . $scripturl . '?action=quickhelp;help=badbehavior_ip_wl" onclick="return reqOverlayDiv(this.href);" class="help"><img src="' . $settings['images_url'] . '/helptopics.png" class="icon" alt="' . $txt['help'] . '" /></a>', $txt['badbehavior_ip_wl'], '
		<dl class="settings">
			<dt>',
				$txt['badbehavior_wl_comment'], '
			</dt>
			<dd>',
				$txt['badbehavior_ip_wl_desc'], '
			</dd>';

	// Show any existing IP's that are on the whitelist
	foreach ($context['badbehavior_ip_wl'] as $key => $data)
	{
		$comment = isset($context['badbehavior_ip_wl_desc'][$key]) ? $context['badbehavior_ip_wl_desc'][$key] : '';
		echo '
			<dt>
				<input type="text" name="badbehavior_ip_wl_desc[', $key, ']" value="', $comment, '" class="input_text" />
			</dt>
			<dd>
				<input type="text" name="badbehavior_ip_wl[', $key, ']" value="', $data, '" class="input_text" />
			</dd>';
	}

	// If we have none, then lets show a blank one.
	if (empty($context['badbehavior_ip_wl']))
	{
		echo '
			<dt>
				<input type="text" name="badbehavior_ip_wl_desc[]" class="input_text" />
			</dt>
			<dd>
				<input type="text" name="badbehavior_ip_wl[]" class="input_text float" />
			</dd>';
	}

	// And a link so they can add more
	echo '
			<dt id="add_more_ip_placeholder" style="display: none;"></dt>
			<dd></dd>
			<dt id="add_more_ip_div"><a href="#" onclick="addAnotherOption(sIpParent, oIpOptionsdt, oIpOptionsdd); return false;" class="linkbutton_left">', $txt['badbehavior_ip_wl_add'], '</a></dt>
			<dd></dd>';
}

/**
 * Template to add an URL to the BB whitelist
 */
function template_callback_badbehavior_add_url()
{
	global $txt, $context, $scripturl, $settings;

	// whitelist by URL
	echo '
		</dl>
		<hr />
		<a href="' . $scripturl . '?action=quickhelp;help=badbehavior_url_wl" onclick="return reqOverlayDiv(this.href);" class="help"><img src="' . $settings['images_url'] . '/helptopics.png" class="icon" alt="' . $txt['help'] . '" /></a>', $txt['badbehavior_url_wl'], '
		<dl class="settings">
			<dt>',
				$txt['badbehavior_wl_comment'], '
			</dt>
			<dd>',
				$txt['badbehavior_url_wl_desc'], '
			</dd>';

	// Show any existing URLs that are on the whitelist
	foreach ($context['badbehavior_url_wl'] as $key => $data)
	{
		$comment = isset($context['badbehavior_url_wl_desc'][$key]) ? $context['badbehavior_url_wl_desc'][$key] : '';
		echo '
			<dt>
				<input type="text" name="badbehavior_url_wl_desc[', $key, ']" value="', $comment, '" class="input_text" />
			</dt>
			<dd>
				<input type="text" name="badbehavior_url_wl[', $key, ']" value="', $data, '" class="input_text" />
			</dd>';
	}

	// If we have none, then lets show a blank one.
	if (empty($context['badbehavior_url_wl']))
	{
		echo '
			<dt>
				<input type="text" name="badbehavior_url_wl_desc[]" class="input_text" />
			</dt>
			<dd>
				<input type="text" name="badbehavior_url_wl[]" class="input_text" />
			</dd>';
	}

	// And a link so they can add more
	echo '
			<dt id="add_more_url_placeholder" style="display: none;"></dt>
			<dd></dd>
			<dt id="add_more_url_div"><a href="#" onclick="addAnotherOption(sUrlParent, oUrlOptionsdt, oUrlOptionsdd); return false;" class="linkbutton_left">', $txt['badbehavior_url_wl_add'], '</a></dt>
			<dd></dd>';
}

/**
 * Template to add an User Agent to the BB whitelist, use with CAUTION as this
 * will allow a user to bypass the checks
 */
function template_callback_badbehavior_add_useragent()
{
	global $txt, $context, $scripturl, $settings;

	// whitelist by User Agent String
	echo '
		</dl>
		<hr />
		<a href="' . $scripturl . '?action=quickhelp;help=badbehavior_useragent_wl" onclick="return reqOverlayDiv(this.href);" class="help"><img src="' . $settings['images_url'] . '/helptopics.png" class="icon" alt="' . $txt['help'] . '" /></a>', $txt['badbehavior_useragent_wl'], '
		<dl class="settings">
			<dt>',
				$txt['badbehavior_wl_comment'], '
			</dt>
			<dd>',
				$txt['badbehavior_useragent_wl_desc'], '
			</dd>';

	// Show any existing useragent's that are on the whitelist
	foreach ($context['badbehavior_useragent_wl'] as $key => $data)
	{
		$comment = isset($context['badbehavior_useragent_wl_desc'][$key]) ? $context['badbehavior_useragent_wl_desc'][$key] : '';
		echo '
			<dt>
				<input type="text" name="badbehavior_useragent_wl_desc[', $key, ']" value="', $comment, '" class="input_text" />
			</dt>
			<dd>
				<input type="text" name="badbehavior_useragent_wl[', $key, ']" value="', $data, '" class="input_text" />
			</dd>';
	}

	// If we have none, then lets show a blank one.
	if (empty($context['badbehavior_useragent_wl']))
		echo '
			<dt>
				<input type="text" name="badbehavior_useragent_wl_desc[]" class="input_text" />
			</dt>
			<dd>
				<input type="text" name="badbehavior_useragent_wl[]" class="input_text" />
			</dd>';

	// And a link so they can add more
	echo '
			<dt id="add_more_useragent_placeholder" style="display: none;"></dt>
			<dd></dd>
			<dt id="add_more_useragent_div"><a href="#" onclick="addAnotherOption(sUseragentParent, oUseragentOptionsdt, oUseragentOptionsdd); return false;" class="linkbutton_left">', $txt['badbehavior_useragent_wl_add'], '</a></dt>
			<dd></dd>';
}