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
 * @version 1.1 Release Candidate 2
 *
 */

/**
 * Template for the section that allows to modify weights for search settings
 * in admin panel.
 */
function template_modify_weights()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=managesearch;sa=weights" method="post" accept-charset="UTF-8">
			<h2 class="category_header">
				<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=search_weight_commonheader" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a> ', $txt['search_weights'], '
			</h2>';

	if (!empty($modSettings['search_index']) && (stripos($modSettings['search_index'], 'sphinx') === 0))
		echo '
			<div class="content">
				<div class="infobox">',
					$txt['search_weights_sphinx'], '
				</div>
			</div>';

	echo '
			<div class="content">
				<dl class="settings large_caption">
					<dt>
						<a href="', $scripturl, '?action=quickhelp;help=search_weight_frequency" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
						<label for="weight1_val">', $txt['search_weight_frequency'], ':</label>
					</dt>
					<dd>
						<span class="search_weight"><input type="text" name="search_weight_frequency" id="weight1_val" value="', empty($modSettings['search_weight_frequency']) ? '0' : $modSettings['search_weight_frequency'], '" onchange="calculateNewValues()" size="3" class="input_text" /></span>
						<span id="weight1" class="search_weight">', $context['relative_weights']['search_weight_frequency'], '%</span>
					</dd>
					<dt>
						<a href="', $scripturl, '?action=quickhelp;help=search_weight_age" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
						<label for="weight2_val">', $txt['search_weight_age'], ':</label>
					</dt>
					<dd>
						<span class="search_weight"><input type="text" name="search_weight_age" id="weight2_val" value="', empty($modSettings['search_weight_age']) ? '0' : $modSettings['search_weight_age'], '" onchange="calculateNewValues()" size="3" class="input_text" /></span>
						<span id="weight2" class="search_weight">', $context['relative_weights']['search_weight_age'], '%</span>
					</dd>
					<dt>
						<a href="', $scripturl, '?action=quickhelp;help=search_weight_length" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
						<label for="weight3_val">', $txt['search_weight_length'], ':</label>
					</dt>
					<dd>
						<span class="search_weight"><input type="text" name="search_weight_length" id="weight3_val" value="', empty($modSettings['search_weight_length']) ? '0' : $modSettings['search_weight_length'], '" onchange="calculateNewValues()" size="3" class="input_text" /></span>
						<span id="weight3" class="search_weight">', $context['relative_weights']['search_weight_length'], '%</span>
					</dd>
					<dt>
						<a href="', $scripturl, '?action=quickhelp;help=search_weight_subject" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
						<label for="weight4_val">', $txt['search_weight_subject'], ':</label>
					</dt>
					<dd>
						<span class="search_weight"><input type="text" name="search_weight_subject" id="weight4_val" value="', empty($modSettings['search_weight_subject']) ? '0' : $modSettings['search_weight_subject'], '" onchange="calculateNewValues()" size="3" class="input_text" /></span>
						<span id="weight4" class="search_weight">', $context['relative_weights']['search_weight_subject'], '%</span>
					</dd>
					<dt>
						<a href="', $scripturl, '?action=quickhelp;help=search_weight_first_message" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
						<label for="weight5_val">', $txt['search_weight_first_message'], ':</label>
					</dt>
					<dd>
						<span class="search_weight"><input type="text" name="search_weight_first_message" id="weight5_val" value="', empty($modSettings['search_weight_first_message']) ? '0' : $modSettings['search_weight_first_message'], '" onchange="calculateNewValues()" size="3" class="input_text" /></span>
						<span id="weight5" class="search_weight">', $context['relative_weights']['search_weight_first_message'], '%</span>
					</dd>
					<dt>
						<a href="', $scripturl, '?action=quickhelp;help=search_weight_sticky" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
						<label for="weight6_val">', $txt['search_weight_sticky'], ':</label>
					</dt>
					<dd>
						<span class="search_weight"><input type="text" name="search_weight_sticky" id="weight6_val" value="', empty($modSettings['search_weight_sticky']) ? '0' : $modSettings['search_weight_sticky'], '" onchange="calculateNewValues()" size="3" class="input_text" /></span>
						<span id="weight6" class="search_weight">', $context['relative_weights']['search_weight_sticky'], '%</span>
					</dd>
					<dt>
						<a href="', $scripturl, '?action=quickhelp;help=search_weight_likes" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a>
						<label for="weight7_val">						', $txt['search_weight_likes'], ':</label>
					</dt>
					<dd>
						<span class="search_weight"><input type="text" name="search_weight_likes" id="weight7_val" value="', empty($modSettings['search_weight_likes']) ? '0' : $modSettings['search_weight_likes'], '" onchange="calculateNewValues()" size="3" class="input_text" /></span>
						<span id="weight7" class="search_weight">', $context['relative_weights']['search_weight_likes'], '%</span>
					</dd>
					<dt>
						<strong>', $txt['search_weights_total'], '</strong>
					</dt>
					<dd>
						<span id="weighttotal" class="search_weight"><strong>', $context['relative_weights']['total'], '</strong></span>
						<span class="search_weight"><strong>&nbsp;&nbsp;&nbsp;&nbsp;100%</strong></span>
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="submit" name="save" value="', $txt['search_weights_save'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-msw_token_var'], '" value="', $context['admin-msw_token'], '" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Template for the section to select a search method
 * in search area of admin panel.
 */
function template_select_search_method()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt['search_method'], '</h2>
		<div class="infobox">
			<a href="', $scripturl, '?action=quickhelp;help=search_why_use_index" onclick="return reqOverlayDiv(this.href);">', $txt['search_create_index_why'], '</a>
		</div>
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=managesearch;sa=method" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['search_method'], '</h2>
			<div class="content">
				<dl class="settings">

			';
	if (!empty($context['table_info']))
		echo '
					<dt>
						<label>', $txt['search_method_messages_table_space'], ':</label>
					</dt>
					<dd>
						', $context['table_info']['data_length'], '
					</dd>
					<dt>
						<label>', $txt['search_method_messages_index_space'], ':</label>
					</dt>
					<dd>
						', $context['table_info']['index_length'], '
					</dd>';

	echo '
				</dl>
				', $context['double_index'] ? '<div class="warningbox">
				' . $txt['search_double_index'] . '</div>' : '', '
				<fieldset id="search_index" class="search_settings">
					<legend>', $txt['search_index'], '</legend>
					<dl>
						<dt>
							<input type="radio" id="search_index_none" name="search_index" value="none"', empty($modSettings['search_index']) ? ' checked="checked"' : '', ' />
							<label for="search_index_none">', $txt['search_index_none'], '</label>
						</dt>';

	if ($context['supports_fulltext'])
	{
		echo '
						<dt>
							<input type="radio" id="search_index_full" name="search_index" value="fulltext"', !empty($modSettings['search_index']) && $modSettings['search_index'] == 'fulltext' ? ' checked="checked"' : '', empty($context['fulltext_index']) ? ' onclick="alert(\'' . $txt['search_method_fulltext_warning'] . '\'); selectRadioByName(this.form.search_index, \'none\');"' : '', ' />
							<label for="search_index_full">', $txt['search_method_fulltext_index'], '</label>
						</dt>
						<dd>
							<p>';

		if (empty($context['fulltext_index']) && empty($context['cannot_create_fulltext']))
			echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_no_index_exists'], ' <a class="linkbutton" href="', $scripturl, '?action=admin;area=managesearch;sa=createfulltext;', $context['session_var'], '=', $context['session_id'], ';', $context['admin-msm_token_var'], '=', $context['admin-msm_token'], '">', $txt['search_method_fulltext_create'], '</a>';
		elseif (empty($context['fulltext_index']) && !empty($context['cannot_create_fulltext']))
			echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_fulltext_cannot_create'];
		else
			echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_index_already_exists'], ' <a class="linkbutton" href="', $scripturl, '?action=admin;area=managesearch;sa=removefulltext;', $context['session_var'], '=', $context['session_id'], ';', $context['admin-msm_token_var'], '=', $context['admin-msm_token'], '">', $txt['search_method_fulltext_remove'], '</a><br />
								<strong>', $txt['search_index_size'], ':</strong> ', $context['table_info']['fulltext_length'];

		echo '
							</p>
						</dd>';
	}

	echo '
						<dt>
							<input type="radio" id="search_index_custom" name="search_index" value="custom"', !empty($modSettings['search_index']) && $modSettings['search_index'] == 'custom' ? ' checked="checked"' : '', $context['custom_index'] ? '' : ' onclick="alert(\'' . $txt['search_index_custom_warning'] . '\'); selectRadioByName(this.form.search_index, \'none\');"', ' />
							<label for="search_index_custom">', $txt['search_index_custom'], '</label>
						</dt>
						<dd>
							<p>';

	if ($context['custom_index'])
		echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_index_already_exists'], ' <a class="linkbutton" href="', $scripturl, '?action=admin;area=managesearch;sa=removecustom;', $context['session_var'], '=', $context['session_id'], ';', $context['admin-msm_token_var'], '=', $context['admin-msm_token'], '">', $txt['search_index_custom_remove'], '</a><br />
								<strong>', $txt['search_index_size'], ':</strong> ', $context['table_info']['custom_index_length'];
	elseif ($context['partial_custom_index'])
		echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_index_partial'], ' <a class="linkbutton" href="', $scripturl, '?action=admin;area=managesearch;sa=removecustom;', $context['session_var'], '=', $context['session_id'], ';', $context['admin-msm_token_var'], '=', $context['admin-msm_token'], '">', $txt['search_index_custom_remove'], '</a> <a class="linkbutton" href="', $scripturl, '?action=admin;area=managesearch;sa=createmsgindex;resume;', $context['session_var'], '=', $context['session_id'], ';', $context['admin-msm_token_var'], '=', $context['admin-msm_token'], '">', $txt['search_index_custom_resume'], '</a><br />
								<strong>', $txt['search_index_size'], ':</strong> ', $context['table_info']['custom_index_length'];
	else
		echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_no_index_exists'], ' <a class="linkbutton" href="', $scripturl, '?action=admin;area=managesearch;sa=createmsgindex">', $txt['search_index_create_custom'], '</a>';

	echo '
							</p>
						</dd>';

	// Any search API's to include
	foreach ($context['search_apis'] as $api)
	{
		if (empty($api['label']) || $api['has_template'])
			continue;

		echo '
						<dt>
							<input type="radio" id="search_index_', $api['setting_index'], '" name="search_index" value="', $api['setting_index'], '"', !empty($modSettings['search_index']) && $modSettings['search_index'] == $api['setting_index'] ? ' checked="checked"' : '', ' />
							<label for="search_index_', $api['setting_index'], '">', $api['label'], '</label>
						</dt>';

		if ($api['desc'])
			echo '
						<dd>
							<p>', $api['desc'], '</p>
						</dd>';
	}

	echo '
					</dl>
				</fieldset>
				<fieldset id="search_method" class="search_settings">
				<legend>', $txt['search_method'], '</legend>
					<input type="checkbox" name="search_force_index" id="search_force_index_check" value="1"', empty($modSettings['search_force_index']) ? '' : ' checked="checked"', ' /><label for="search_force_index_check">', $txt['search_force_index'], '</label><br />
					<input type="checkbox" name="search_match_words" id="search_match_words_check" value="1"', empty($modSettings['search_match_words']) ? '' : ' checked="checked"', ' /><label for="search_match_words_check">', $txt['search_match_words'], '</label>
				</fieldset>
			</div>
			<div class="submitbutton">
				<input type="submit" name="save" value="', $txt['search_method_save'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-msmpost_token_var'], '" value="', $context['admin-msmpost_token'], '" />
			</div>
		</form>
	</div>
	<script>
		showhideSearchMethod();

		$("#search_index").find("input").change(function() {
			showhideSearchMethod();
		});
   </script>';
}

/**
 * Template to create a search index.
 */
function template_create_index()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=managesearch;sa=createmsgindex" method="post" accept-charset="UTF-8" name="create_index">
			<h2 class="category_header">', $txt['search_create_index'], '</h2>
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="predefine_select">', $txt['search_predefined'], ':</label>
					</dt>
					<dd>
						<select name="bytes_per_word" id="predefine_select">
							<option value="2">', $txt['search_predefined_small'], '</option>
							<option value="4" selected="selected">', $txt['search_predefined_moderate'], '</option>
							<option value="5">', $txt['search_predefined_large'], '</option>
						</select>
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="submit" name="save" value="', $txt['search_create_index_start'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="step" value="1" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Template to show progress during creation of a search index.
 */
function template_create_index_progress()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=managesearch;sa=createmsgindex;step=1" name="autoSubmit" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['search_create_index'], '</h2>
			<div class="content">
				<div>
					<p>', $txt['search_create_index_not_ready'], '</p>
					<div class="progress_bar">
						<div class="full_bar">', $context['percentage'], '%</div>
						<div class="green_percent" style="width: ', $context['percentage'], '%;">&nbsp;</div>
					</div>
				</div>
				<div class="submitbutton">
					<input type="submit" name="cont" value="', $txt['search_create_index_continue'], '" />
					<input type="hidden" name="step" value="', $context['step'], '" />
					<input type="hidden" name="start" value="', $context['start'], '" />
					<input type="hidden" name="bytes_per_word" value="', $context['index_settings']['bytes_per_word'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</div>
			</div>
		</form>
	</div>
	<script>
		doAutoSubmit(10, ', JavaScriptEscape($txt['search_create_index_continue']), ');
	</script>';
}

/**
 * Used to show the completion of the search index creation
 */
function template_create_index_done()
{
	global $scripturl, $txt;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt['search_create_index'], '</h2>
		<div class="content">
			<p>', $txt['search_create_index_done'], '</p>
			<div class="submitbutton">
				<strong><a class="linkbutton" href="', $scripturl, '?action=admin;area=managesearch;sa=method">', $txt['search_create_index_done_link'], '</a></strong>
			</div>
		</div>
	</div>';
}

/**
 * Add or edit a search engine spider.
 */
function template_spider_edit()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=sengines;sa=editspiders;sid=', $context['spider']['id'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $context['page_title'], '</h2>
			<div class="information">
				', $txt['add_spider_desc'], '
			</div>
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="spider_name">', $txt['spider_name'], ':</label><br />
						<span class="smalltext">', $txt['spider_name_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="spider_name" id="spider_name" value="', $context['spider']['name'], '" class="input_text" />
					</dd>
					<dt>
						<label for="spider_agent">', $txt['spider_agent'], ':</label><br />
						<span class="smalltext">', $txt['spider_agent_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="spider_agent" id="spider_agent" value="', $context['spider']['agent'], '" class="input_text" />
					</dd>
					<dt>
						<label for="spider_ip">', $txt['spider_ip_info'], ':</label><br />
						<span class="smalltext">', $txt['spider_ip_info_desc'], '</span>
					</dt>
					<dd>
						<textarea name="spider_ip" id="spider_ip" rows="4" cols="20">', $context['spider']['ip_info'], '</textarea>
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="submit" name="save" value="', $context['page_title'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-ses_token_var'], '" value="', $context['admin-ses_token'], '" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Spider logs page.
 */
function template_show_spider_logs()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">';

	// Standard fields.
	template_show_list('spider_logs');

	echo '
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=sengines;sa=logs" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['spider_logs_delete'], '</h2>
			<div class="content">
				<p>
					<label for="older">', sprintf($txt['spider_stats_delete_older'], '<input type="text" name="older" id="older" value="7" size="3" class="input_text" />'), '</label>
				</p>
				<div class="submitbutton">
					<input type="submit" name="delete_entries" value="', $txt['spider_logs_delete_submit'], '" onclick="if (document.getElementById(\'older\').value &lt; 1 &amp;&amp; !confirm(\'' . addcslashes($txt['spider_logs_delete_confirm'], "'") . '\')) return false; return true;" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-sl_token_var'], '" value="', $context['admin-sl_token'], '" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Spider stats section.
 */
function template_show_spider_stats()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">';

	// Standard fields.
	template_show_list('spider_stat_list');

	echo '
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=sengines;sa=stats" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['spider_logs_delete'], '</h2>
			<div class="content">
				<p>
					<label for="older">', sprintf($txt['spider_stats_delete_older'], '<input type="text" name="older" id="older" value="7" size="3" class="input_text" />'), '</label>
				</p>
				<div class="submitbutton">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-ss_token_var'], '" value="', $context['admin-ss_token'], '" />
					<input type="submit" name="delete_entries" value="', $txt['spider_logs_delete_submit'], '" onclick="if (document.getElementById(\'older\').value &lt; 1 &amp;&amp; !confirm(\'' . addcslashes($txt['spider_logs_delete_confirm'], "'") . '\')) return false; return true;" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * The settings page for sphinx search
 */
function template_manage_sphinx()
{
	global $context, $modSettings, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=managesearch;sa=managesphinx;save=1" method="post"  accept-charset="UTF-8" name="create_index">
			<h2 class="category_header">', $context['page_title'], '</h2>';

	// any results to show?
	if (!empty($context['settings_message']))
	{
		echo '
			<div class="', (empty($context['error_type']) ? 'successbox' : ($context['error_type'] !== 'serious' ? 'warningbox' : 'errorbox')), '" id="errors">
				<ul>
					<li>', implode('</li><li>', $context['settings_message']), '</li>
				</ul>
			</div>';
	}

	echo '
			<div class="information">
				', $context['page_description'], '
			</div>
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="sphinx_index_prefix_input">', $txt['sphinx_index_prefix'], '</label><br />
						<span class="smalltext">', $txt['sphinx_index_prefix_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="sphinx_index_prefix" id="sphinx_index_prefix_input" value="', isset($modSettings['sphinx_index_prefix']) ? $modSettings['sphinx_data_path'] : 'elkarte', '" size="65" />
					</dd>
					<dt>
						<label for="sphinx_data_path_input">', $txt['sphinx_index_data_path'], '</label><br />
						<span class="smalltext">', $txt['sphinx_index_data_path_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="sphinx_data_path" id="sphinx_data_path_input" value="', isset($modSettings['sphinx_data_path']) ? $modSettings['sphinx_data_path'] : '/var/sphinx/data', '" size="65" />
					</dd>
					<dt>
						<label for="sphinx_log_path_input">', $txt['sphinx_log_file_path'], '</label><br />
						<span class="smalltext">', $txt['sphinx_log_file_path_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="sphinx_log_path" id="sphinx_log_path_input" value="', isset($modSettings['sphinx_log_path']) ? $modSettings['sphinx_log_path'] : '/var/sphinx/log', '" size="65" />
					</dd>
					<dt>
						<label for="sphinx_stopword_path_input">', $txt['sphinx_stop_word_path'], '</label><br />
						<span class="smalltext">', $txt['sphinx_stop_word_path_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="sphinx_stopword_path" id="sphinx_stopword_path_input" value="', isset($modSettings['sphinx_stopword_path']) ? $modSettings['sphinx_stopword_path'] : '', '" size="65" />
					</dd>
					<dt>
						<label for="sphinx_indexer_mem_input">', $txt['sphinx_memory_limit'], '</label><br />
						<span class="smalltext">', $txt['sphinx_memory_limit_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="sphinx_indexer_mem" id="sphinx_indexer_mem_input" value="', isset($modSettings['sphinx_indexer_mem']) ? $modSettings['sphinx_indexer_mem'] : '128', '" size="4" /> MB
					</dd>
					<dt>
						<label for="sphinx_searchd_server_input">', $txt['sphinx_searchd_server'], '</label><br />
						<span class="smalltext">', $txt['sphinx_searchd_server_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="sphinx_searchd_server" id="sphinx_searchd_server_input" value="', isset($modSettings['sphinx_searchd_server']) ? $modSettings['sphinx_searchd_server'] : 'localhost', '" size="65" />
					</dd>
					<dt>
						<label for="sphinx_searchd_port_input">', $txt['sphinx_searchd_port'], '</label><br />
						<span class="smalltext">', $txt['sphinx_searchd_port_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="sphinx_searchd_port" id="sphinx_searchd_port_input" value="', isset($modSettings['sphinx_searchd_port']) ? $modSettings['sphinx_searchd_port'] : '9312', '" size="4" />
					</dd>
					<dt>
						<label for="sphinxql_searchd_port_input">', $txt['sphinx_searchd_qlport'], '</label><br />
						<span class="smalltext">', $txt['sphinx_searchd_qlport_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="sphinxql_searchd_port" id="sphinxql_searchd_port_input" value="', isset($modSettings['sphinxql_searchd_port']) ? $modSettings['sphinxql_searchd_port'] : '9306', '" size="4" />
					</dd>
					<dt>
						<label for="sphinx_max_results_input">', $txt['sphinx_max_matches'], '</label><br />
						<span class="smalltext">', $txt['sphinx_max_matches_desc'], '</span>
					</dt>
					<dd>
						<input type="text" name="sphinx_max_results" id="sphinx_max_results_input" value="', isset($modSettings['sphinx_max_results']) ? $modSettings['sphinx_max_results'] : '2000', '" size="4" />
					</dd>
				</dl>
				<div class="submitbutton">
					<input type="submit" name="createconfig" value="', $txt['sphinx_create_config'], '" />
					<input type="submit" name="checkconnect" value="', $txt['sphinx_test_connection'], '" />
					<input type="submit" name="save" value="', $txt['save'], '"  />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-mssphinx_token_var'], '" value="', $context['admin-mssphinx_token'], '" />
				</div>
			</div>
		</form>
	</div>';
}
