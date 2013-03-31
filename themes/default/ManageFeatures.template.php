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

// Template for showing custom profile fields.
function template_show_custom_profile()
{
	global $context, $txt, $settings, $scripturl;

	// Standard fields.
	template_show_list('standard_profile_fields');

	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		var iNumChecks = document.forms.standardProfileFields.length;
		for (var i = 0; i < iNumChecks; i++)
			if (document.forms.standardProfileFields[i].id.indexOf(\'reg_\') == 0)
				document.forms.standardProfileFields[i].disabled = document.forms.standardProfileFields[i].disabled || !document.getElementById(\'active_\' + document.forms.standardProfileFields[i].id.substr(4)).checked;
	// ]]></script><br />';

	// Custom fields.
	template_show_list('custom_profile_fields');
}

// Edit a profile field?
function template_edit_profile_field()
{
	global $context, $txt, $settings, $scripturl;

	// All the javascript for this page - quite a bit in script.js!
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		var startOptID = ', count($context['field']['options']), ';
	// ]]></script>';

	// any errors messages to show?
	if (isset($_GET['msg']))
	{
		loadLanguage('Errors');
		if (isset($txt['custom_option_' . $_GET['msg']]))
			echo '
	<div class="errorbox">',
		$txt['custom_option_' . $_GET['msg']], '
	</div>';
	}

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=featuresettings;sa=profileedit;fid=', $context['fid'], ';', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
			<div id="section_header" class="cat_bar">
				<h3 class="catbg">
					', $context['page_title'], '
				</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<fieldset>
						<legend>', $txt['custom_edit_general'], '</legend>

						<dl class="settings">
							<dt>
								<strong><label for="field_name">', $txt['custom_edit_name'], ':</label></strong>
							</dt>
							<dd>
								<input type="text" name="field_name" id="field_name" value="', $context['field']['name'], '" size="20" maxlength="40" class="input_text" />
							</dd>
							<dt>
								<strong><label for="field_desc">', $txt['custom_edit_desc'], ':</label></strong>
							</dt>
							<dd>
								<textarea name="field_desc" id="field_desc" rows="3" cols="40">', $context['field']['desc'], '</textarea>
							</dd>
							<dt>
								<strong><label for="profile_area">', $txt['custom_edit_profile'], ':</label></strong><br />
								<span class="smalltext">', $txt['custom_edit_profile_desc'], '</span>
							</dt>
							<dd>
								<select name="profile_area" id="profile_area">
									<option value="none"', $context['field']['profile_area'] == 'none' ? ' selected="selected"' : '', '>', $txt['custom_edit_profile_none'], '</option>
									<option value="account"', $context['field']['profile_area'] == 'account' ? ' selected="selected"' : '', '>', $txt['account'], '</option>
									<option value="forumprofile"', $context['field']['profile_area'] == 'forumprofile' ? ' selected="selected"' : '', '>', $txt['forumprofile'], '</option>
									<option value="theme"', $context['field']['profile_area'] == 'theme' ? ' selected="selected"' : '', '>', $txt['theme'], '</option>
								</select>
							</dd>
							<dt>
								<strong><label for="reg">', $txt['custom_edit_registration'], ':</label></strong>
							</dt>
							<dd>
								<select name="reg" id="reg">
									<option value="0"', $context['field']['reg'] == 0 ? ' selected="selected"' : '', '>', $txt['custom_edit_registration_disable'], '</option>
									<option value="1"', $context['field']['reg'] == 1 ? ' selected="selected"' : '', '>', $txt['custom_edit_registration_allow'], '</option>
									<option value="2"', $context['field']['reg'] == 2 ? ' selected="selected"' : '', '>', $txt['custom_edit_registration_require'], '</option>
								</select>
							</dd>
							<dt>
								<strong><label for="display">', $txt['custom_edit_display'], ':</label></strong>
							</dt>
							<dd>
								<input type="checkbox" name="display" id="display"', $context['field']['display'] ? ' checked="checked"' : '', ' class="input_check" />
							</dd>

							<dt>
								<strong><label for="placement">', $txt['custom_edit_placement'], ':</label></strong>
							</dt>
							<dd>
								<select name="placement" id="placement">
									<option value="0"', $context['field']['placement'] == '0' ? ' selected="selected"' : '', '>', $txt['custom_edit_placement_standard'], '</option>
									<option value="1"', $context['field']['placement'] == '1' ? ' selected="selected"' : '', '>', $txt['custom_edit_placement_withicons'], '</option>
									<option value="2"', $context['field']['placement'] == '2' ? ' selected="selected"' : '', '>', $txt['custom_edit_placement_abovesignature'], '</option>
								</select>
							</dd>
							<dt>
								<a id="field_show_enclosed" href="', $scripturl, '?action=quickhelp;help=field_show_enclosed" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" class="icon" alt="', $txt['help'], '" /></a>
								<strong><label for="enclose">', $txt['custom_edit_enclose'], ':</label></strong><br />
								<span class="smalltext">', $txt['custom_edit_enclose_desc'], '</span>
							</dt>
							<dd>
								<textarea name="enclose" id="enclose" rows="10" cols="50">', @$context['field']['enclose'], '</textarea>
							</dd>
						</dl>
					</fieldset>
					<fieldset>
						<legend>', $txt['custom_edit_input'], '</legend>
						<dl class="settings">
							<dt>
								<strong><label for="field_type">', $txt['custom_edit_picktype'], ':</label></strong>
							</dt>
							<dd>
								<select name="field_type" id="field_type" onchange="updateInputBoxes();">
									<option value="text"', $context['field']['type'] == 'text' ? ' selected="selected"' : '', '>', $txt['custom_profile_type_text'], '</option>
									<option value="textarea"', $context['field']['type'] == 'textarea' ? ' selected="selected"' : '', '>', $txt['custom_profile_type_textarea'], '</option>
									<option value="select"', $context['field']['type'] == 'select' ? ' selected="selected"' : '', '>', $txt['custom_profile_type_select'], '</option>
									<option value="radio"', $context['field']['type'] == 'radio' ? ' selected="selected"' : '', '>', $txt['custom_profile_type_radio'], '</option>
									<option value="check"', $context['field']['type'] == 'check' ? ' selected="selected"' : '', '>', $txt['custom_profile_type_check'], '</option>
								</select>
							</dd>
							<dt id="max_length_dt">
								<strong><label for="max_length_dd">', $txt['custom_edit_max_length'], ':</label></strong><br />
								<span class="smalltext">', $txt['custom_edit_max_length_desc'], '</span>
							</dt>
							<dd>
								<input type="text" name="max_length" id="max_length_dd" value="', $context['field']['max_length'], '" size="7" maxlength="6" class="input_text" />
							</dd>
							<dt id="dimension_dt">
								<strong><label for="dimension_dd">', $txt['custom_edit_dimension'], ':</label></strong>
							</dt>
							<dd id="dimension_dd">
								<strong>', $txt['custom_edit_dimension_row'], ':</strong> <input type="text" name="rows" value="', $context['field']['rows'], '" size="5" maxlength="3" class="input_text" />
								<strong>', $txt['custom_edit_dimension_col'], ':</strong> <input type="text" name="cols" value="', $context['field']['cols'], '" size="5" maxlength="3" class="input_text" />
							</dd>
							<dt id="bbc_dt">
								<strong><label for="bbc_dd">', $txt['custom_edit_bbc'], '</label></strong>
							</dt>
							<dd >
								<input type="checkbox" name="bbc" id="bbc_dd"', $context['field']['bbc'] ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt id="options_dt">
								<a href="', $scripturl, '?action=quickhelp;help=customoptions" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" class="icon" alt="', $txt['help'], '" /></a>
								<strong><label for="options_dd">', $txt['custom_edit_options'], ':</label></strong><br />
								<span class="smalltext">', $txt['custom_edit_options_desc'], '</span>
							</dt>
							<dd id="options_dd">
								<div>';

	foreach ($context['field']['options'] as $k => $option)
	{
		echo '
								', $k == 0 ? '' : '<br />', '<input type="radio" name="default_select" value="', $k, '"', $context['field']['default_select'] == $option ? ' checked="checked"' : '', ' class="input_radio" /><input type="text" name="select_option[', $k, ']" value="', $option, '" class="input_text" />';
	}
	echo '
								<span id="addopt"></span>
								[<a href="" onclick="addOption(); return false;">', $txt['custom_edit_options_more'], '</a>]
								</div>
							</dd>
							<dt id="default_dt">
								<strong><label for="default_dd">', $txt['custom_edit_default'], ':</label></strong>
							</dt>
							<dd>
								<input type="checkbox" name="default_check" id="default_dd"', $context['field']['default_check'] ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
						</dl>
					</fieldset>
					<fieldset>
						<legend>', $txt['custom_edit_advanced'], '</legend>
						<dl class="settings">
							<dt id="mask_dt">
								<a id="custom_mask" href="', $scripturl, '?action=quickhelp;help=custom_mask" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" class="icon" alt="', $txt['help'], '" /></a>
								<strong><label for="mask">', $txt['custom_edit_mask'], ':</label></strong><br />
								<span class="smalltext">', $txt['custom_edit_mask_desc'], '</span>
							</dt>
							<dd>
								<select name="mask" id="mask" onchange="updateInputBoxes();">
									<option value="nohtml"', $context['field']['mask'] == 'nohtml' ? ' selected="selected"' : '', '>', $txt['custom_edit_mask_nohtml'], '</option>
									<option value="email"', $context['field']['mask'] == 'email' ? ' selected="selected"' : '', '>', $txt['custom_edit_mask_email'], '</option>
									<option value="number"', $context['field']['mask'] == 'number' ? ' selected="selected"' : '', '>', $txt['custom_edit_mask_number'], '</option>
									<option value="regex"', strpos($context['field']['mask'], 'regex') === 0 ? ' selected="selected"' : '', '>', $txt['custom_edit_mask_regex'], '</option>
								</select>
								<br />
								<span id="regex_div">
									<input type="text" name="regex" value="', $context['field']['regex'], '" size="30" class="input_text" />
								</span>
							</dd>
							<dt>
								<strong><label for="private">', $txt['custom_edit_privacy'], ':</label></strong>
								<span class="smalltext">', $txt['custom_edit_privacy_desc'], '</span>
							</dt>
							<dd>
								<select name="private" id="private" onchange="updateInputBoxes();" style="width: 100%">
									<option value="0"', $context['field']['private'] == 0 ? ' selected="selected"' : '', '>', $txt['custom_edit_privacy_all'], '</option>
									<option value="1"', $context['field']['private'] == 1 ? ' selected="selected"' : '', '>', $txt['custom_edit_privacy_see'], '</option>
									<option value="2"', $context['field']['private'] == 2 ? ' selected="selected"' : '', '>', $txt['custom_edit_privacy_owner'], '</option>
									<option value="3"', $context['field']['private'] == 3 ? ' selected="selected"' : '', '>', $txt['custom_edit_privacy_none'], '</option>
								</select>
							</dd>
							<dt id="can_search_dt">
								<strong><label for="can_search_dd">', $txt['custom_edit_can_search'], ':</label></strong><br />
								<span class="smalltext">', $txt['custom_edit_can_search_desc'], '</span>
							</dt>
							<dd>
								<input type="checkbox" name="can_search" id="can_search_dd"', $context['field']['can_search'] ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
							<dt>
								<strong><label for="can_search_check">', $txt['custom_edit_active'], ':</label></strong><br />
								<span class="smalltext">', $txt['custom_edit_active_desc'], '</span>
							</dt>
							<dd>
								<input type="checkbox" name="active" id="can_search_check"', $context['field']['active'] ? ' checked="checked"' : '', ' class="input_check" />
							</dd>
						</dl>
					</fieldset>
					<hr class="hrcolor" />
						<input type="submit" name="save" value="', $txt['save'], '" class="button_submit" />';

	if ($context['fid'])
		echo '
						<input type="submit" name="delete" value="', $txt['delete'], '" onclick="return confirm(\'', $txt['custom_edit_delete_sure'], '\');" class="button_submit" />';

	echo '
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['admin-ecp_token_var'], '" value="', $context['admin-ecp_token'], '" />
		</form>
	</div>';

	// Get the javascript bits right!
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		updateInputBoxes();
	// ]]></script>';
}