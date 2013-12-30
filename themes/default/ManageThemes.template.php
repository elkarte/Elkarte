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
 * The main sub template - for theme administration.
 */
function template_manage_themes()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<h2 class="category_header">
			<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=themes" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a>
			', $txt['themeadmin_title'], '
		</h2>
		<div class="information">
			', $txt['themeadmin_explain'], '
		</div>
		<div id="admin_form_wrapper">
			<form action="', $scripturl, '?action=admin;area=theme;sa=admin" method="post" accept-charset="UTF-8">
				<h3 class="category_header">', $txt['settings'], '</h3>
				<div class="windowbg2">
					<div class="content">
						<dl class="settings">
							<dt>
								<label for="options-theme_allow"> ', $txt['theme_allow'], '</label>
							</dt>
							<dd>
								<input type="checkbox" name="options[theme_allow]" id="options-theme_allow" value="1"', !empty($modSettings['theme_allow']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt>
								<label for="known_themes_list">', $txt['themeadmin_selectable'], '</label>:
							</dt>
							<dd>
								<fieldset id="known_themes_list">
									<legend data-collapsed="true">', $txt['themeadmin_themelist_link'], '</legend>
									<ul id="known_themes_list_ul">';

	foreach ($context['themes'] as $theme)
		echo '
										<li>
											<label for="options-known_themes_', $theme['id'], '"><input type="checkbox" name="options[known_themes][]" id="options-known_themes_', $theme['id'], '" value="', $theme['id'], '"', $theme['known'] ? ' checked="checked"' : '', ' class="input_check" /> ', $theme['name'], '</label>
										</li>';

	echo '
									</ul>
								</fieldset>
							</dd>
							<dt>
								<label for="theme_guests">', $txt['theme_guests'], ':</label>
							</dt>
							<dd>
								<select name="options[theme_guests]" id="theme_guests">';

	// Put an option for each theme in the select box.
	foreach ($context['themes'] as $theme)
		echo '
									<option value="', $theme['id'], '"', $modSettings['theme_guests'] == $theme['id'] ? ' selected="selected"' : '', '>', $theme['name'], '</option>';

	echo '
								</select>
								<span class="smalltext pick_theme"><a href="', $scripturl, '?action=theme;sa=pick;u=-1;', $context['session_var'], '=', $context['session_id'], '">', $txt['theme_select'], '</a></span>
							</dd>
							<dt>
								<label for="theme_reset">', $txt['theme_reset'], '</label>:
							</dt>
							<dd>
								<select name="theme_reset" id="theme_reset">
									<option value="-1" selected="selected">', $txt['theme_nochange'], '</option>
									<option value="0">', $txt['theme_forum_default'], '</option>';

	// Same thing, this time for changing the theme of everyone.
	foreach ($context['themes'] as $theme)
		echo '
									<option value="', $theme['id'], '">', $theme['name'], '</option>';

	echo '
								</select>
								<span class="smalltext pick_theme"><a href="', $scripturl, '?action=theme;sa=pick;u=0;', $context['session_var'], '=', $context['session_id'], '">', $txt['theme_select'], '</a></span>
							</dd>
						</dl>
						<input type="submit" name="save" value="' . $txt['save'] . '" class="right_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-tm_token_var'], '" value="', $context['admin-tm_token'], '" />
					</div>
				</div>
			</form>';

	// Warn them if theme creation isn't possible!
	if (!$context['can_create_new'])
		echo '
			<div class="errorbox">', $txt['theme_install_writable'], '</div>';

	echo '
			<h3 class="category_header">
				<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=theme_install" onclick="return reqOverlayDiv(this.href);" id="theme_install" title="', $txt['help'], '"></a> ', $txt['theme_install'], '
			</h3>
			<form action="', $scripturl, '?action=admin;area=theme;sa=install" method="post" accept-charset="UTF-8" enctype="multipart/form-data" onsubmit="return confirm(\'', $txt['theme_install_new_confirm'], '\');">
				<div class="windowbg">
					<div class="content">
						<dl class="settings">';

	// Here's a little box for installing a new theme.
	if ($context['can_create_new'])
		echo '
							<dt>
								<label for="theme_gz">', $txt['theme_install_file'], '</label>:
							</dt>
							<dd>
								<input type="file" name="theme_gz" id="theme_gz" value="theme_gz" size="40" onchange="this.form.copy.disabled = this.value != \'\'; this.form.theme_dir.disabled = this.value != \'\';" class="input_file" />
							</dd>';

	echo '
							<dt>
								<label for="theme_dir">', $txt['theme_install_dir'], '</label>:
							</dt>
							<dd>
								<input type="text" name="theme_dir" id="theme_dir" value="', $context['new_theme_dir'], '" size="40" style="width: 70%;" class="input_text" />
							</dd>';

	if ($context['can_create_new'])
		echo '
							<dt>
								<label for="copy">', $txt['theme_install_new'], ':</label>
							</dt>
							<dd>
								<input type="text" name="copy" id="copy" value="', $context['new_theme_name'], '" size="40" class="input_text" />
							</dd>';

	echo '
						</dl>
						<input type="submit" name="save" value="', $txt['theme_install_go'], '" class="right_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-tm_token_var'], '" value="', $context['admin-tm_token'], '" />
					</div>
				</div>
			</form>
		</div>
	</div>';
}

/**
 * Interface to list the existing themes.
 */
function template_list_themes()
{
	global $context, $settings, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt['themeadmin_list_heading'], '</h2>
		<div class="information">
			', $txt['themeadmin_list_tip'], '
		</div>

		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=theme;', $context['session_var'], '=', $context['session_id'], ';sa=list" method="post" accept-charset="UTF-8">
			<h3 class="category_header">', $txt['theme_settings'], '</h3>
			<br />';

	// Show each theme.... with X for delete and a link to settings.
	foreach ($context['themes'] as $theme)
	{
		echo '
			<div class="theme_', $theme['id'], '">
				<h3 class="category_header">
					', $theme['name'], '', !empty($theme['version']) ? ' <em>(' . $theme['version'] . ')</em>' : '';

		// You *cannot* delete the default theme. It's important!
		if ($theme['id'] != 1)
			echo '
					<span class="floatright"><a class="delete_theme" data-theme_id="', $theme['id'], '" href="', $scripturl, '?action=admin;area=theme;sa=remove;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';', $context['admin-tr_token_var'], '=', $context['admin-tr_token'], '"><img src="', $settings['images_url'], '/icons/delete.png" alt="', $txt['theme_remove'], '" title="', $txt['theme_remove'], '" /></a></span>';

		echo '
				</h3>
			</div>
			<div class="theme_', $theme['id'], ' windowbg">
				<div class="content">
					<dl class="settings themes_list">
						<dt><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=list" class="linkbutton floatleft">', $txt['theme_edit_settings'], '</a></dt>
						<dt>', $txt['themeadmin_list_theme_dir'], ':</dt>
						<dd', $theme['valid_path'] ? '' : ' class="error"', '>', $theme['theme_dir'], $theme['valid_path'] ? '' : ' ' . $txt['themeadmin_list_invalid'], '</dd>
						<dt>', $txt['themeadmin_list_theme_url'], ':</dt>
						<dd>', $theme['theme_url'], '</dd>
						<dt>', $txt['themeadmin_list_images_url'], ':</dt>
						<dd>', $theme['images_url'], '</dd>
					</dl>
				</div>
			</div>';
	}

	echo '
			<h3 class="category_header">', $txt['themeadmin_list_reset'], '</h3>
			<div class="windowbg">
				<div class="content">
					<dl class="settings">
						<dt>
							<label for="reset_dir">', $txt['themeadmin_list_reset_dir'], '</label>:
						</dt>
						<dd>
							<input type="text" name="reset_dir" id="reset_dir" value="', $context['reset_dir'], '" size="40" style="width: 80%;" class="input_text" />
						</dd>
						<dt>
							<label for="reset_url">', $txt['themeadmin_list_reset_url'], '</label>:
						</dt>
						<dd>
							<input type="text" name="reset_url" id="reset_url" value="', $context['reset_url'], '" size="40" style="width: 80%;" class="input_text" />
						</dd>
					</dl>
					<input type="submit" name="save" value="', $txt['themeadmin_list_reset_go'], '" class="right_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-tl_token_var'], '" value="', $context['admin-tl_token'], '" />
				</div>
			</div>
			<script><!-- // --><![CDATA[
				$(document).ready(function () {
					$(".delete_theme").bind("click", function (event) {
						event.preventDefault();
						var theme_id = $(this).data("theme_id"),
							base_url = $(this).attr("href"),
							pattern = new RegExp(elk_session_var + "=" + elk_session_id + ";(.*)$"),
							tokens = pattern.exec(base_url)[1].split("="),
							token = tokens[1],
							token_var = tokens[0];

						if (confirm(\'', $txt['theme_remove_confirm'], '\'))
						{
							$.ajax({
								type: "GET",
								url: base_url + ";api;xml",
								beforeSend: ajax_indicator(true)
							})
							.done(function(request) {
								if ($(request).find("error").length === 0)
								{
									var new_token = $(request).find("token").text(),
										new_token_var = $(request).find("token_var").text();

									$(".theme_" + theme_id).slideToggle("slow", function () {
										$(this).remove();
									});

									$(".delete_theme").each(function () {
										$(this).attr("href", $(this).attr("href").replace(token_var + "=" + token, new_token_var + "=" + new_token));
									});
								}
								// @todo improve error handling
								else
								{
									alert($(request).find("text").text());
									window.location = base_url;
								}
							})
							.fail(function(request) {
								window.location = base_url;
							})
							.always(function() {
								// turn off the indicator
								ajax_indicator(false);
							});
						}
					});
				});
			// ]]></script>
		</form>
	</div>';
}

/**
 * Page to allow reset of themes.
 */
function template_reset_list()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt['themeadmin_reset_title'], '</h2>
		<div class="information">
			', $txt['themeadmin_reset_tip'], '
		</div>
		<div id="admin_form_wrapper">';

	// Show each theme with the links to modify the settings
	$alternate = false;

	foreach ($context['themes'] as $theme)
	{
		$alternate = !$alternate;

		echo '
			<h3 class="secondary_header">', $theme['name'], '</h3>
			<div class="windowbg', $alternate ? '' : '2', '">
				<div class="content">
					<ul>
						<li>
							<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset">', $txt['themeadmin_reset_defaults'], '</a> <em class="smalltext">(', $theme['num_default_options'], ' ', $txt['themeadmin_reset_defaults_current'], ')</em>
						</li>
						<li>
							<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset;who=1">', $txt['themeadmin_reset_members'], '</a>
						</li>
						<li>
							<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset;who=2;', $context['admin-stor_token_var'], '=', $context['admin-stor_token'], '" onclick="return confirm(\'', $txt['themeadmin_reset_remove_confirm'], '\');">', $txt['themeadmin_reset_remove'], '</a> <em class="smalltext">(', $theme['num_members'], ' ', $txt['themeadmin_reset_remove_current'], ')</em>
						</li>
					</ul>
				</div>
			</div>';
	}

	echo '
		</div>
	</div>';
}

/**
 * Template to allow to set options.
 */
function template_set_options()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';sa=reset" method="post" accept-charset="UTF-8">
			<input type="hidden" name="who" value="', $context['theme_options_reset'] ? 1 : 0, '" />
			<h2 class="category_header">', $txt['theme_options_title'], ' - ', $context['theme_settings']['name'], '</h2>
			<div class="information">
				', $context['theme_options_reset'] ? $txt['themeadmin_reset_options_info'] : $txt['theme_options_defaults'], '
			</div>
			<div class="windowbg2">
				<div class="content">';

	echo '
					<dl class="settings">';

	foreach ($context['options'] as $setting)
	{
		echo '
						<dt ', $context['theme_options_reset'] ? 'style="width:50%"' : '', '>';

		// Show the change option box ?
		if ($context['theme_options_reset'])
			echo '
							<span class="floatleft"><select name="', !empty($setting['default']) ? 'default_' : '', 'options_master[', $setting['id'], ']" onchange="this.form.options_', $setting['id'], '.disabled = this.selectedIndex != 1;">
								<option value="0" selected="selected">', $txt['themeadmin_reset_options_none'], '</option>
								<option value="1">', $txt['themeadmin_reset_options_change'], '</option>
								<option value="2">', $txt['themeadmin_reset_options_default'], '</option>
							</select>&nbsp;</span>';

		// Display checkbox options
		if ($setting['type'] == 'checkbox')
		{
			echo '
							<label for="options_', $setting['id'], '">', $setting['label'], '</label>';
			if (isset($setting['description']))
				echo '
							<br /><span class="smalltext">', $setting['description'], '</span>';

			echo '
						</dt>
						<dd ', $context['theme_options_reset'] ? 'style="width:40%"' : '', '>
							<input type="hidden" name="' . (!empty($setting['default']) ? 'default_' : '') . 'options[' . $setting['id'] . ']" value="0" />
							<input type="checkbox" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '"', !empty($setting['value']) ? ' checked="checked"' : '', $context['theme_options_reset'] ? ' disabled="disabled"' : '', ' value="1" class="input_check floatleft" />';
		}
		// How about selection lists, we all love them
		elseif ($setting['type'] == 'list')
		{
			echo '
							<label for="options_', $setting['id'], '">', $setting['label'], '</label>';

			if (isset($setting['description']))
				echo '
							<br /><span class="smalltext">', $setting['description'], '</span>';

			echo '
						</dt>
						<dd ', $context['theme_options_reset'] ? 'style="width:40%"' : '', '>
							&nbsp;<select class="floatleft" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '"', $context['theme_options_reset'] ? ' disabled="disabled"' : '', '>';

			foreach ($setting['options'] as $value => $label)
				echo '
								<option value="', $value, '"', $value == $setting['value'] ? ' selected="selected"' : '', '>', $label, '</option>';

			echo '
							</select>';
		}
		// a textbox it is then
		else
		{
			echo '
							<label for="options_', $setting['id'], '">', $setting['label'], '</label>';

			if (isset($setting['description']))
				echo '
							<br /><span class="smalltext">', $setting['description'], '</span>';

			echo '
						</dt>
						<dd ', $context['theme_options_reset'] ? 'style="width:40%"' : '', '>
							<input type="text" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '" value="', $setting['value'], '"', $setting['type'] == 'number' ? ' size="5"' : '', $context['theme_options_reset'] ? ' disabled="disabled"' : '', ' class="input_text" />';
		}

		// End of this defintion
		echo '
						</dd>';
	}

	// Close the option page up
	echo '
					</dl>
					<input type="submit" name="submit" value="', $txt['save'], '" class="right_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-sto_token_var'], '" value="', $context['admin-sto_token'], '" />
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Allows to set settings for a theme.
 */
function template_set_settings()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admin_form_wrapper">
		<form action="', $scripturl, '?action=admin;area=theme;sa=list;th=', $context['theme_settings']['theme_id'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">
				<a class="hdicon cat_img_helptopics help" href="', $scripturl, '?action=quickhelp;help=theme_settings" onclick="return reqOverlayDiv(this.href);" title="', $txt['help'], '"></a> ', $txt['theme_settings'], ' - ', $context['theme_settings']['name'], '
			</h2>
			<br />';

	// @todo Why can't I edit the default theme popup.
	if ($context['theme_settings']['theme_id'] != 1)
		echo '
			<h3 class="category_header hdicon cat_img_config">
				', $txt['theme_edit'], '
			</h3>
			<div class="windowbg">
				<div class="content">
					<ul>
						<li>
							<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit;filename=index.template.php">', $txt['theme_edit_index'], '</a>
						</li>
						<li>
							<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=browse;directory=css">', $txt['theme_edit_style'], '</a>
						</li>
					</ul>
				</div>
			</div>';

	echo '
			<h3 class="category_header hdicon cat_img_config">
				', $txt['theme_url_config'], '
			</h3>
			<div class="windowbg2">
				<div class="content">
					<dl class="settings">
						<dt>
							<label for="theme_name">', $txt['actual_theme_name'], '</label>
						</dt>
						<dd>
							<input type="text" id="theme_name" name="options[name]" value="', $context['theme_settings']['name'], '" size="32" class="input_text" />
						</dd>
						<dt>
							<label for="theme_url">', $txt['actual_theme_url'], '</label>
						</dt>
						<dd>
							<input type="text" id="theme_url" name="options[theme_url]" value="', $context['theme_settings']['actual_theme_url'], '" size="50" style="max-width: 100%; width: 50ex;" class="input_text" />
						</dd>
						<dt>
							<label for="images_url">', $txt['actual_images_url'], '</label>
						</dt>
						<dd>
							<input type="text" id="images_url" name="options[images_url]" value="', $context['theme_settings']['actual_images_url'], '" size="50" style="max-width: 100%; width: 50ex;" class="input_text" />
						</dd>
						<dt>
							<label for="theme_dir">', $txt['actual_theme_dir'], '</label>
						</dt>
						<dd>
							<input type="text" id="theme_dir" name="options[theme_dir]" value="', $context['theme_settings']['actual_theme_dir'], '" size="50" style="max-width: 100%; width: 50ex;" class="input_text" />
						</dd>
					</dl>
				</div>
			</div>';

	// Do we allow theme variants?
	if (!empty($context['theme_variants']))
	{
		echo '
			<h3 class="category_header hdicon cat_img_config">
				', $txt['theme_variants'], '
			</h3>
			<div class="windowbg2">
				<div class="content">
					<dl class="settings">
						<dt>
							<label for="variant">', $txt['theme_variants_default'], '</label>:
						</dt>
						<dd>
							<select id="variant" name="options[default_variant]" onchange="changeVariant(this.value)">';

		foreach ($context['theme_variants'] as $key => $variant)
			echo '
								<option value="', $key, '" ', $context['default_variant'] == $key ? 'selected="selected"' : '', '>', $variant['label'], '</option>';

		echo '
							</select>
						</dd>
						<dt>
							<label for="disable_user_variant">', $txt['theme_variants_user_disable'], '</label>:
						</dt>
						<dd>
							<input type="hidden" name="options[disable_user_variant]" value="0" />
							<input type="checkbox" name="options[disable_user_variant]" id="disable_user_variant"', !empty($context['theme_settings']['disable_user_variant']) ? ' checked="checked"' : '', ' value="1" class="input_check" />
						</dd>
					</dl>
					<img src="', $context['theme_variants'][$context['default_variant']]['thumbnail'], '" id="variant_preview" alt="" />
				</div>
			</div>';
	}

	echo '
			<h3 class="category_header hdicon cat_img_config">
				', $txt['theme_options'], '
			</h3>
			<div class="windowbg">
				<div class="content">
					<dl class="settings">';

	foreach ($context['settings'] as $setting)
	{
		// Is this a separator?
		if (empty($setting))
		{
			echo '
					</dl>
					<hr />
					<dl class="settings">';
		}
		// A checkbox?
		elseif ($setting['type'] == 'checkbox')
		{
			echo '
						<dt id="dt_', $setting['id'], '">
							<label for="', $setting['id'], '">', $setting['label'], '</label>:';

			if (isset($setting['description']))
				echo '<br />
							<span class="smalltext">', $setting['description'], '</span>';

			echo '
						</dt>
						<dd id="dd_', $setting['id'], '">
							<input type="hidden" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" value="0" />
							<input type="checkbox" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '"', !empty($setting['value']) ? ' checked="checked"' : '', ' value="1" class="input_check" />
						</dd>';
		}
		// A textarea?
		elseif ($setting['type'] == 'textarea')
		{
			echo '
						<dt id="dt_', $setting['id'], '">
							<label for="', $setting['id'], '">', $setting['label'], '</label>:';

			if (isset($setting['description']))
				echo '<br />
							<span class="smalltext">', $setting['description'], '</span>';

			echo '
						</dt>
						<dd id="dd_', $setting['id'], '">
							<textarea name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '"class="input_textarea">', $setting['value'], '</textarea>
						</dd>';
		}
		// A list with options?
		elseif ($setting['type'] == 'list')
		{
			echo '
						<dt id="dt_', $setting['id'], '">
							<label for="', $setting['id'], '">', $setting['label'], '</label>:';

			if (isset($setting['description']))
				echo '<br />
							<span class="smalltext">', $setting['description'], '</span>';

			echo '
						</dt>
						<dd id="dd_', $setting['id'], '">
							<select name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '">';

			foreach ($setting['options'] as $value => $label)
				echo '
							<option value="', $value, '"', $value == $setting['value'] ? ' selected="selected"' : '', '>', $label, '</option>';

			echo '
							</select>
						</dd>';
		}
		// A regular input box, then?
		else
		{
			echo '
						<dt id="dt_', $setting['id'], '">
							<label for="', $setting['id'], '">', $setting['label'], '</label>:';

			if (isset($setting['description']))
				echo '<br />
							<span class="smalltext">', $setting['description'], '</span>';

			echo '
						</dt>
						<dd id="dd_', $setting['id'], '">
							<input type="text" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '" value="', $setting['value'], '"', $setting['type'] == 'number' ? ' size="5"' : (empty($setting['size']) ? ' size="40"' : ' size="' . $setting['size'] . '"'), ' class="input_text" />
						</dd>';
		}
	}

	echo '
					</dl>
					<input type="submit" name="save" value="', $txt['save'], '" class="right_submit" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-sts_token_var'], '" value="', $context['admin-sts_token'], '" />
				</div>
			</div>
		</form>
	</div>';

	if (!empty($context['theme_variants']))
	{
		echo '
		<script><!-- // --><![CDATA[
		var oThumbnails = {';

		// All the variant thumbnails.
		$count = 1;
		foreach ($context['theme_variants'] as $key => $variant)
		{
			echo '
			\'', $key, '\': \'', $variant['thumbnail'], '\'', (count($context['theme_variants']) == $count ? '' : ',');
			$count++;
		}

		echo '
		};
		// ]]></script>';
	}
}

/**
 * This template allows for the selection of different themes.
 */
function template_pick()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="pick_theme">
		<form action="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">';

	// Just go through each theme and show its information - thumbnail, etc.
	foreach ($context['available_themes'] as $theme)
	{
		echo '
			<h3 class="category_header">
				<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], !empty($theme['variants']) ? ';vrt=' . $theme['selected_variant'] : '', '">', $theme['name'], '</a>
			</h3>
			<div class="', $theme['selected'] ? 'windowbg' : 'windowbg2', '">
				<div class="flow_hidden content">
					<div class="floatright"><a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';theme=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '" id="theme_thumb_preview_', $theme['id'], '" title="', $txt['theme_preview'], '"><img src="', $theme['thumbnail_href'], '" id="theme_thumb_', $theme['id'], '" alt="" /></a></div>
					<p>', $theme['description'], '</p>';

		if (!empty($theme['variants']))
		{
			echo '
					<label for="variant', $theme['id'], '"><strong>', $theme['pick_label'], '</strong></label>:
					<select id="variant', $theme['id'], '" name="vrt[', $theme['id'], ']" onchange="changeVariant', $theme['id'], '(this.value);">';

			foreach ($theme['variants'] as $key => $variant)
			{
				echo '
						<option value="', $key, '" ', $theme['selected_variant'] == $key ? 'selected="selected"' : '', '>', $variant['label'], '</option>';
			}

			echo '
					</select>
					<noscript>
						<input type="submit" name="save[', $theme['id'], ']" value="', $txt['save'], '" class="right_submit" />
					</noscript>';
		}

		echo '
					<br />
					<p>
						<em class="smalltext">', $theme['num_users'], ' ', ($theme['num_users'] == 1 ? $txt['theme_user'] : $txt['theme_users']), '</em>
					</p>
					<br />
					<ul>
						<li>
							<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], !empty($theme['variants']) ? ';vrt=' . $theme['selected_variant'] : '', '" id="theme_use_', $theme['id'], '">[', $txt['theme_set'], ']</a>
						</li>
						<li>
							<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';theme=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '" id="theme_preview_', $theme['id'], '">[', $txt['theme_preview'], ']</a>
						</li>
					</ul>
				</div>
			</div>';

		if (!empty($theme['variants']))
		{
			echo '
			<script><!-- // --><![CDATA[
				var sBaseUseUrl', $theme['id'], ' = elk_prepareScriptUrl(elk_scripturl) + \'action=theme;sa=pick;u=', $context['current_member'], ';th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '\',
					sBasePreviewUrl', $theme['id'], ' = elk_prepareScriptUrl(elk_scripturl) + \'action=theme;sa=pick;u=', $context['current_member'], ';theme=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '\',
					oThumbnails', $theme['id'], ' = {';

			// All the variant thumbnails.
			$count = 1;
			foreach ($theme['variants'] as $key => $variant)
			{
				echo '
					\'', $key, '\': \'', $variant['thumbnail'], '\'', (count($theme['variants']) == $count ? '' : ',');

				$count++;
			}

			echo '
				};

				function changeVariant', $theme['id'], '(sVariant)
				{
					document.getElementById(\'theme_thumb_', $theme['id'], '\').src = oThumbnails', $theme['id'], '[sVariant];
					document.getElementById(\'theme_use_', $theme['id'], '\').href = sBaseUseUrl', $theme['id'] == 0 ? $context['default_theme_id'] : $theme['id'], ' + \';vrt=\' + sVariant;
					document.getElementById(\'theme_thumb_preview_', $theme['id'], '\').href = sBasePreviewUrl', $theme['id'], ' + \';vrt=\' + sVariant + \';variant=\' + sVariant;
					document.getElementById(\'theme_preview_', $theme['id'], '\').href = sBasePreviewUrl', $theme['id'], ' + \';vrt=\' + sVariant + \';variant=\' + sVariant;
				}
			// ]]></script>';
		}
	}

	echo '
		</form>
	</div>';
}

/**
 * Messages to show when a theme was installed successfully.
 */
function template_installed()
{
	global $context, $scripturl, $txt;

	// Not much to show except a link back...
	echo '
	<div id="admincenter">
		<h2 class="category_header">', $context['page_title'], '</h2>
		<div class="windowbg">
			<div class="content">
				<p>
					<a href="', $scripturl, '?action=admin;area=theme;sa=list;th=', $context['installed_theme']['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $context['installed_theme']['name'], '</a> ', $txt['theme_installed_message'], '
				</p>
				<p>
					<a href="', $scripturl, '?action=admin;area=theme;sa=admin;', $context['session_var'], '=', $context['session_id'], '">', $txt['back'], '</a>
				</p>
			</div>
		</div>
	</div>';
}

/**
 * Interface to edit a list.
 */
function template_themelist()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admin_form_wrapper">
		<h3 class="category_header">', $txt['themeadmin_edit_title'], '</h3>
		<br />';

	$alternate = false;

	foreach ($context['themes'] as $theme)
	{
		$alternate = !$alternate;

		echo '
		<h3 class="category_header">
			<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=browse">', $theme['name'], '</a>', !empty($theme['version']) ? '
			<em>(' . $theme['version'] . ')</em>' : '', '
		</h3>
		<div class="windowbg', $alternate ? '' : '2', '">
			<div class="content">
				<ul>
					<li><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=browse">', $txt['themeadmin_edit_browse'], '</a></li>', $theme['can_edit_style'] ? '
					<li><a href="' . $scripturl . '?action=admin;area=theme;th=' . $theme['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=browse;directory=css">' . $txt['themeadmin_edit_style'] . '</a></li>' : '', '
					<li><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=copy">', $txt['themeadmin_edit_copy_template'], '</a></li>
				</ul>
			</div>
		</div>';
	}

	echo '
	</div>';
}

/**
 * Interface to copy a template.
 */
function template_copy_template()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt['themeadmin_edit_filename'], '</h2>
		<div class="information">
			', $txt['themeadmin_edit_copy_warning'], '
		</div>
		<div class="windowbg">
			<div class="content">
				<ul class="theme_options">';

	$alternate = false;
	foreach ($context['available_templates'] as $template)
	{
		$alternate = !$alternate;

		echo '
					<li class="flow_hidden windowbg', $alternate ? '2' : '', '">
						<span class="floatleft">', $template['filename'], $template['already_exists'] ? ' <span class="error">(' . $txt['themeadmin_edit_exists'] . ')</span>' : '', '</span>
						<span class="floatright">';

		if ($template['can_copy'])
			echo '<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=copy;template=', $template['value'], '" onclick="return confirm(\'', $template['already_exists'] ? $txt['themeadmin_edit_overwrite_confirm'] : $txt['themeadmin_edit_copy_confirm'], '\');">', $txt['themeadmin_edit_do_copy'], '</a>';
		else
			echo $txt['themeadmin_edit_no_copy'];

		echo '
						</span>
					</li>';
	}

	echo '
				</ul>
			</div>
		</div>
	</div>';
}

/**
 * Interface to browse the files of a theme in admin panel.
 */
function template_browse()
{
	global $context, $txt;

	echo '
	<div id="admincenter">
		<table class="table_grid">
		<thead>
			<tr class="table_head">
				<th scope="col" class="lefttext" style="width:50%">', $txt['themeadmin_edit_filename'], '</th>
				<th scope="col" style="width:35%">', $txt['themeadmin_edit_modified'], '</th>
				<th scope="col" style="width:15%">', $txt['themeadmin_edit_size'], '</th>
			</tr>
		</thead>
		<tbody>';

	$alternate = false;

	foreach ($context['theme_files'] as $file)
	{
		$alternate = !$alternate;

		echo '
			<tr class="windowbg', $alternate ? '2' : '', '">
				<td>';

		if ($file['is_editable'])
			echo '<a href="', $file['href'], '"', $file['is_template'] ? ' style="font-weight: bold;"' : '', '>', $file['filename'], '</a>';
		elseif ($file['is_directory'])
			echo '<a href="', $file['href'], '" class="is_directory">', $file['filename'], '</a>';
		else
			echo $file['filename'];

		echo '
				</td>
				<td>', !empty($file['last_modified']) ? $file['last_modified'] : '', '</td>
				<td>', $file['size'], '</td>
			</tr>';
	}

	echo '
		</tbody>
		</table>
	</div>';
}

/**
 * Allows to edit a stylesheet.
 */
function template_edit_style()
{
	global $context, $settings, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	// From now on no one can complain that editing css is difficult. If you disagree, go to www.w3schools.com.
	echo '
	<div id="admincenter">
		<script><!-- // --><![CDATA[
			var previewData = "",
				previewTimeout,
				editFilename = ', JavaScriptEscape($context['edit_filename']), ';

			// Load up a page, but apply our stylesheet.
			function navigatePreview(url)
			{
				var myDoc = new XMLHttpRequest();
				myDoc.onreadystatechange = function ()
				{
					if (myDoc.readyState !== 4)
						return;

					if (myDoc.responseText !== null && myDoc.status === 200)
					{
						previewData = myDoc.responseText;
						document.getElementById("css_preview_box").style.display = "";

						// Revert to the theme they actually use ;).
						var tempImage = new Image();
						tempImage.src = elk_prepareScriptUrl(elk_scripturl) + "action=admin;area=theme;sa=edit;theme=', $settings['theme_id'], ';preview;" + (new Date().getTime());

						refreshPreviewCache = null;
						refreshPreview(false);
					}
				};

				var anchor = "";
				if (url.indexOf("#") !== -1)
				{
					anchor = url.substr(url.indexOf("#"));
					url = url.substr(0, url.indexOf("#"));
				}

				myDoc.open("GET", url + (url.indexOf("?") === -1 ? "?" : ";") + "theme=', $context['theme_id'], '" + anchor, true);
				myDoc.send(null);
			}
			navigatePreview(elk_scripturl);

			var refreshPreviewCache;
			function refreshPreview(check)
			{
				var identical = document.forms.stylesheetForm.entire_file.value == refreshPreviewCache;

				// Don\'t reflow the whole thing if nothing changed!!
				if (check && identical)
					return;

				refreshPreviewCache = document.forms.stylesheetForm.entire_file.value;

				// Replace the paths for images.
				refreshPreviewCache = refreshPreviewCache.replace(/url\(\.\.\/images/gi, "url(" + elk_images_url);

				// Try to do it without a complete reparse.
				if (identical)
				{
					try
					{
					';
	if (isBrowser('is_ie'))
		echo '
						var sheets = frames["css_preview_box"].document.styleSheets;
						for (var j = 0; j < sheets.length; j++)
						{
							if (sheets[j].id == "css_preview_box")
								sheets[j].cssText = document.forms.stylesheetForm.entire_file.value;
						}';
	else
		echo '
						frames["css_preview_box"].document.getElementById("css_preview_sheet").innerHTML = document.forms.stylesheetForm.entire_file.value;';
	echo '
					}
					catch (e)
					{
						identical = false;
					}
				}

				// This will work most of the time... could be done with an after-apply, maybe.
				if (!identical)
				{
					var data = previewData,
						preview_sheet = document.forms.stylesheetForm.entire_file.value,
						stylesheetMatch = new RegExp(\'<link rel="stylesheet"[^>]+href="[^"]+\' + editFilename + \'[^>]*>\');

					// Replace the paths for images.
					preview_sheet = preview_sheet.replace(/url\(\.\.\/images/gi, "url(" + elk_images_url);
					data = data.replace(stylesheetMatch, "<style type=\"text/css\" id=\"css_preview_sheet\">" + preview_sheet + "<" + "/style>");

					iframe = document.getElementById("css_preview_box");
					iframe.contentWindow.document.open()
					iframe.contentWindow.document.write(data);
					iframe.contentWindow.document.close();

					// Next, fix all its links so we can handle them and reapply the new css!
					iframe.onload = function ()
					{
						var fixLinks = frames["css_preview_box"].document.getElementsByTagName("a");
						for (var i = 0; i < fixLinks.length; i++)
						{
							if (fixLinks[i].onclick)
								continue;

							fixLinks[i].onclick = function ()
							{
								window.parent.navigatePreview(this.href);
								return false;
							};
						}
					};
				}
			}
		// ]]></script>
		<iframe id="css_preview_box" name="css_preview_box" src="about:blank" frameborder="0" style="width:99%; height:300px; display: none; margin-bottom: 2ex; border: 1px solid black;"></iframe>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="UTF-8" name="stylesheetForm" id="stylesheetForm">
			<h3 class="category_header">', $txt['theme_edit'], ' - ', $context['edit_filename'], '</h3>
			<div class="windowbg">
				<div class="content">';

	if (!$context['allow_save'])
		echo '
					', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '<br />';

	echo '
					<textarea name="entire_file" cols="80" rows="20" class="edit_file" onkeyup="setPreviewTimeout();" onchange="refreshPreview(true);">', $context['entire_file'], '</textarea><br />
					<div class="submitbutton">
						<input type="button" value="', $txt['themeadmin_edit_preview'], '" onclick="refreshPreview(false);" class="button_submit" />
						<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled="disabled"', ' class="button_submit" />
					</div>
				</div>
			</div>
			<input type="hidden" name="filename" value="', $context['edit_filename'], '" />
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />';

	// Hopefully our token exists.
	if (isset($context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token']))
		echo '
			<input type="hidden" name="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token_var'], '" value="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token'], '" />';

	echo '
		</form>
	</div>';
}

/**
 * Allow to edit the template.
 */
function template_edit_template()
{
	global $context, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	if (isset($context['parse_error']))
		foreach ($context['parse_error'] as $error)
			echo '
	<div class="errorbox">
		', $txt['themeadmin_edit_error'], '
			<div><span class="tt">', $error, '</span></div>
	</div>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['theme_edit'], ' - ', $context['edit_filename'], '</h2>
			<div class="windowbg">
				<div class="content">';

	if (!$context['allow_save'])
		echo '
					', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '<br />';

	foreach ($context['file_parts'] as $part)
		echo '
					<label for="on_line', $part['line'], '">', $txt['themeadmin_edit_on_line'], ' ', $part['line'], '</label>:<br />
					<div class="centertext">
						<textarea id="on_line', $part['line'], '" name="entire_file[]" cols="80" rows="', $part['lines'] > 14 ? '14' : $part['lines'], '" class="edit_file">', $part['data'], '</textarea>
					</div>';

	echo '
					<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled="disabled"', ' class="right_submit" />
					<input type="hidden" name="filename" value="', $context['edit_filename'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />';

	// You better have one of these to do that
	if (isset($context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token']))
		echo '
					<input type="hidden" name="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token_var'], '" value="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token'], '" />';

	echo '
				</div>
			</div>
		</form>
	</div>';
}

/**
 * Interface to edit a file.
 */
function template_edit_file()
{
	global $context, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	// Is this file writeable?
	if (!$context['allow_save'])
		echo '
	<div class="errorbox">
		', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '
	</div>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['theme_edit'], ' - ', $context['edit_filename'], '</h2>
			<div class="windowbg">
				<div class="content">
					<textarea name="entire_file" id="entire_file" cols="80" rows="20" class="edit_file">', $context['entire_file'], '</textarea><br />
					<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled="disabled"', ' class="right_submit" />
					<input type="hidden" name="filename" value="', $context['edit_filename'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />';

	// Hopefully it exists.
	if (isset($context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token']))
		echo '
					<input type="hidden" name="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token_var'], '" value="', $context['admin-te-' . md5($context['theme_id'] . '-' . $context['edit_filename']) . '_token'], '" />';

	echo '
				</div>
			</div>
		</form>
	</div>';
}