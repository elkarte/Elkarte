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
 * Download a new language file.
 */
function template_download_language()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	// Actually finished?
	if (!empty($context['install_complete']))
	{
		echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt['languages_download_complete'], '</h2>
		<div class="windowbg">
			<div class="content">
				', $context['install_complete'], '
			</div>
		</div>
	</div>';
		return;
	}

	// An error?
	if (!empty($context['error_message']))
		echo '
	<div class="errorbox">
		', $context['error_message'], '
	</div>';

	// Provide something of an introduction...
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=languages;sa=downloadlang;did=', $context['download_id'], ';', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['languages_download'], '</h2>
			<div class="windowbg">
				<div class="content">
					<p>
						', $txt['languages_download_note'], '
					</p>
					<div class="smalltext">
						', $txt['languages_download_info'], '
					</div>
				</div>
			</div>';

	// Show the main files.
	template_show_list('lang_main_files_list');

	// Now, all the images and the likes, hidden via javascript 'cause there are so fecking many.
	echo '
			<br />
			<h3 class="category_header">', $txt['languages_download_theme_files'], '</h3>
			<table class="table_grid">
				<thead>
					<tr class="table_head">
						<th scope="col">
							', $txt['languages_download_filename'], '
						</th>
						<th scope="col" style="width: 100px;">
							', $txt['languages_download_writable'], '
						</th>
						<th scope="col" style="width: 100px;">
							', $txt['languages_download_exists'], '
						</th>
						<th scope="col" style="width: 4%;">
							', $txt['languages_download_copy'], '
						</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['files']['images'] as $theme => $group)
	{
		$count = 0;
		echo '
				<tr class="secondary_header">
					<td colspan="4">
						<img class="sort" src="', $settings['images_url'], '/sort_down.png" id="toggle_image_', $theme, '" alt="*" />&nbsp;', isset($context['theme_names'][$theme]) ? $context['theme_names'][$theme] : $theme, '
					</td>
				</tr>';

		$alternate = false;
		foreach ($group as $file)
		{
			echo '
				<tr class="windowbg', $alternate ? '2' : '', '" id="', $theme, '-', $count++, '">
					<td>
						<strong>', $file['name'], '</strong><br />
						<span class="smalltext">', $txt['languages_download_dest'], ': ', $file['destination'], '</span>
					</td>
					<td>
						<span style="color: ', ($file['writable'] ? 'green' : 'red'), ';">', ($file['writable'] ? $txt['yes'] : $txt['no']), '</span>
					</td>
					<td>
						', $file['exists'] ? ($file['exists'] == 'same' ? $txt['languages_download_exists_same'] : $txt['languages_download_exists_different']) : $txt['no'], '
					</td>
					<td class="centertext">
						<input type="checkbox" name="copy_file[]" value="', $file['generaldest'], '"', ($file['default_copy'] ? ' checked="checked"' : ''), ' class="input_check" />
					</td>
				</tr>';
			$alternate = !$alternate;
		}
	}

	echo '
			</tbody>
			</table>';

	// Do we want some FTP baby?
	// If the files are not writable, we might!
	if (!empty($context['still_not_writable']))
	{
		if (!empty($context['package_ftp']['error']))
			echo '
			<div class="errorbox">
				', $context['package_ftp']['error'], '
			</div>';

		echo '
			<h3 class="category_header">', $txt['package_ftp_necessary'], '</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<p>', $txt['package_ftp_why'], '</p>
					<dl class="settings">
						<dt
							<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
						</dt>
						<dd>
							<div class="floatright" style="margin-right: 1px;"><label for="ftp_port" style="padding-top: 2px; padding-right: 2ex;">', $txt['package_ftp_port'], ':&nbsp;</label> <input type="text" size="3" name="ftp_port" id="ftp_port" value="', isset($context['package_ftp']['port']) ? $context['package_ftp']['port'] : (isset($modSettings['package_port']) ? $modSettings['package_port'] : '21'), '" class="input_text" /></div>
							<input type="text" size="30" name="ftp_server" id="ftp_server" value="', isset($context['package_ftp']['server']) ? $context['package_ftp']['server'] : (isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost'), '" style="width: 70%;" class="input_text" />
						</dd>

						<dt>
							<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="', isset($context['package_ftp']['username']) ? $context['package_ftp']['username'] : (isset($modSettings['package_username']) ? $modSettings['package_username'] : ''), '" style="width: 99%;" class="input_text" />
						</dd>

						<dt>
							<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
						</dt>
						<dd>
							<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 99%;" class="input_text" />
						</dd>

						<dt>
							<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '" style="width: 99%;" class="input_text" />
						</dd>
					</dl>
				</div>
			</div>';
	}

	// Install?
	echo '
			<div class="submitbutton">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-dlang_token_var'], '" value="', $context['admin-dlang_token'], '" />
				<input type="submit" name="do_install" value="', $txt['add_language_elk_install'], '" class="button_submit" />
			</div>
		</form>
	</div>';

	// The javascript for expand and collapse of sections.
	echo '
	<script><!-- // --><![CDATA[';

	// Each theme gets its own handler.
	foreach ($context['files']['images'] as $theme => $group)
	{
		$count = 0;
		echo '
			var oTogglePanel_', $theme, ' = new elk_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [';

		foreach ($group as $file)
			echo '
					', JavaScriptEscape($theme . '-' . $count++), ',';

		echo '
					null
				],
				aSwapImages: [
					{
						sId: \'toggle_image_', $theme, '\',
						srcExpanded: elk_images_url + \'/selected_open.png\',
						altExpanded: \'*\',
						srcCollapsed: elk_images_url + \'/selected.png\',
						altCollapsed: \'*\'
					}
				]
			});';
	}

	echo '
	// ]]></script>';
}

/**
 * Edit language entries.
 */
function template_modify_language_entries()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['edit_languages'], '</h2>
			<div class="information">
				', $txt['edit_language_entries_primary'], '
			</div>';

	// Not writable?
	if ($context['lang_file_not_writable_message'])
	{
		// Oops, show an error for ya.
		echo '
			<div class="errorbox">
				', $context['lang_file_not_writable_message'], '
			</div>';
	}

	// Show the language entries
	echo '
			<div class="windowbg">
				<div class="content">
					<fieldset>
						<legend>', $context['primary_settings']['name'], '</legend>
						<dl class="settings">
							<dt>
								<label for="locale">', $txt['languages_locale'], ':</label>
							</dt>
							<dd>
								<input type="text" name="locale" id="locale" size="20" value="', $context['primary_settings']['locale'], '"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' class="input_text" />
							</dd>
							<dt>
								<label for="dictionary">', $txt['languages_dictionary'], ':</label>
							</dt>
							<dd>
								<input type="text" name="dictionary" id="dictionary" size="20" value="', $context['primary_settings']['dictionary'], '"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' class="input_text" />
							</dd>
							<dt>
								<label for="spelling">', $txt['languages_spelling'], ':</label>
							</dt>
							<dd>
								<input type="text" name="spelling" id="spelling" size="20" value="', $context['primary_settings']['spelling'], '"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' class="input_text" />
							</dd>
							<dt>
								<label for="rtl">', $txt['languages_rtl'], ':</label>
							</dt>
							<dd>
								<input type="checkbox" name="rtl" id="rtl" ', $context['primary_settings']['rtl'] ? ' checked="checked"' : '', ' class="input_check"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' />
							</dd>
						</dl>
					</fieldset>
					<div class="submitbutton">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-mlang_token_var'], '" value="', $context['admin-mlang_token'], '" />
						<input type="submit" name="save_main" value="', $txt['save'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled="disabled"' : '', ' class="button_submit" />';

	// Allow deleting entries.
	if (!empty($context['langpack_uninstall_link']))
	{
		// English can't be deleted though.
		echo '
						<a href="', $context['langpack_uninstall_link'], '" class="linkbutton">' . $txt['delete'] . '</a>';
	}

	echo '
					</div>
				</div>
			</div>
		</form>

		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], ';entries" id="entry_form" method="post" accept-charset="UTF-8">
			<div class="category_header">
				<h3 class="floatleft">
					', $txt['edit_language_entries'], '
				</h3>
				<div id="taskpad" class="floatright">
					<label for="tfid">', $txt['edit_language_entries_file'], '</label>:
					<select id="tfid" name="tfid" onchange="if (this.value != -1) document.forms.entry_form.submit();">';

	foreach ($context['possible_files'] as $id_theme => $theme)
	{
		echo '
						<option value="-1">', $theme['name'], '</option>';

		foreach ($theme['files'] as $file)
			echo '
						<option value="', $id_theme, '+', $file['id'], '"', $file['selected'] ? ' selected="selected"' : '', '> =&gt; ', $file['name'], '</option>';
	}

	echo '
					</select>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-mlang_token_var'], '" value="', $context['admin-mlang_token'], '" />
					<noscript><input type="submit" value="', $txt['go'], '" class="button_submit submitgo" /></noscript>
				</div>
			</div>';

	// Is it not writable?
	// Show an error.
	if (!empty($context['entries_not_writable_message']))
		echo '
			<div class="errorbox">
				', $context['entries_not_writable_message'], '
			</div>';

	// Already have some file entries?
	if (!empty($context['file_entries']))
	{
		echo '
			<div class="content">
				<ul class="strings_edit settings">';

		foreach ($context['file_entries'] as $entry)
		{
			echo '
					<li>
						<label for="entry_', $entry['key'], '" class="smalltext">', $entry['display_key'], '</label>
						<input type="hidden" name="comp[', $entry['key'], ']" value="', $entry['value'], '" />
						<textarea id="entry_', $entry['key'], '" name="entry[', $entry['key'], ']" cols="40" rows="', $entry['rows'] < 2 ? 2 : $entry['rows'], '" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 96%; min-width: 96%' : '') . ';">', $entry['value'], '</textarea>
					</li>';
		}

		echo '
				</ul>
				<input type="submit" name="save_entries" value="', $txt['save'], '"', !empty($context['entries_not_writable_message']) ? ' disabled="disabled"' : '', ' class="button_submit" />
			</div>';
	}

	echo '
		</form>
	</div>';
}

/**
 * Add a new language
 */
function template_add_language()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper"action="', $scripturl, '?action=admin;area=languages;sa=add;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
			<h2 class="category_header">', $txt['add_language'], '</h2>
			<div class="windowbg">
				<div class="content">
					<fieldset>
						<legend>', $txt['add_language_elk'], '</legend>
						<label for="lang_add" class="smalltext">', $txt['add_language_elk_browse'], '</label>
						<input type="text" id="lang_add" name="lang_add" size="40" value="', !empty($context['elk_search_term']) ? $context['elk_search_term'] : '', '" class="input_text" />';

	// Do we have some errors? Too bad.
	if (!empty($context['langfile_error']))
	{
		// Display a little error box.
		echo '
						<div>
							<br />
							<p class="errorbox">', $txt['add_language_error_' . $context['langfile_error']], '</p>
						</div>';
	}

	echo '
					</fieldset>', isBrowser('is_ie') ? '<input type="text" name="ie_fix" style="display: none;" class="input_text" /> ' : '', '
					<input type="submit" name="lang_add_sub" value="', $txt['search'], '" class="right_submit" />
					<br />
				</div>
			</div>
		';

	// Had some results?
	if (!empty($context['languages']))
	{
		echo '
			<div class="information">', $txt['add_language_elk_found'], '</div>';

		template_show_list('languages');
	}

	echo '
		</form>
	</div>';
}