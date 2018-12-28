<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

/**
 * Shows the screen for the package install / uninstall
 * Displays license, readme, and test results
 */
function template_view_package()
{
	global $context, $settings, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt[($context['uninstalling'] ? 'un' : '') . 'install_mod'], '</h2>
		<div class="information">';

	if ($context['is_installed'])
		echo '
			<strong>', $txt['package_installed_warning1'], '</strong><br />
			<br />
			', $txt['package_installed_warning2'], '<br />
			<br />';

	echo $txt['package_installed_warning3'], '
		</div>';

	// Do errors exist in the install? If so light them up like a Christmas tree.
	if ($context['has_failure'])
	{
		echo '
		<div class="errorbox">
			', sprintf($txt['package_will_fail_title'], $txt['package_' . ($context['uninstalling'] ? 'uninstall' : 'install')]), '<br />
			', sprintf($txt['package_will_fail_warning'], $txt['package_' . ($context['uninstalling'] ? 'uninstall' : 'install')]),
		!empty($context['failure_details']) ? '<br /><br /><strong>' . $context['failure_details'] . '</strong>' : '', '
		</div>';
	}

	// Display the package readme if one exists
	if (isset($context['package_readme']))
	{
		echo '
			<h2 class="category_header">', $txt['package_' . ($context['uninstalling'] ? 'un' : '') . 'install_readme'], '</h2>
			<div class="content">
				', $context['package_readme'], '
				<span class="floatright">', $txt['package_available_readme_language'], '
					<select name="readme_language" id="readme_language" onchange="if (this.options[this.selectedIndex].value) window.location.href = elk_prepareScriptUrl(elk_scripturl + \'', '?action=admin;area=packages;sa=', $context['uninstalling'] ? 'uninstall' : 'install', ';package=', $context['filename'], ';readme=\' + this.options[this.selectedIndex].value + \';license=\' + get_selected(\'license_language\'));">';

		foreach ($context['readmes'] as $a => $b)
			echo '
						<option value="', $b, '"', $a === 'selected' ? ' selected="selected"' : '', '>', $b == 'default' ? $txt['package_readme_default'] : ucfirst($b), '</option>';

		echo '
					</select>
				</span>
			</div>
			<br />';
	}

	// Did they specify a license to display?
	if (isset($context['package_license']))
	{
		echo '
			<h2 class="category_header">', $txt['package_install_license'], '</h2>
			<div class="content">
				', $context['package_license'], '
				<span class="floatright">', $txt['package_available_license_language'], '
					<select name="license_language" id="license_language" onchange="if (this.options[this.selectedIndex].value) window.location.href = elk_prepareScriptUrl(elk_scripturl + \'', '?action=admin;area=packages;sa=install', ';package=', $context['filename'], ';license=\' + this.options[this.selectedIndex].value + \';readme=\' + get_selected(\'readme_language\'));">';

		foreach ($context['licenses'] as $a => $b)
			echo '
						<option value="', $b, '"', $a === 'selected' ? ' selected="selected"' : '', '>', $b == 'default' ? $txt['package_license_default'] : ucfirst($b), '</option>';

		echo '
					</select>
				</span>
			</div>
			<br />';
	}

	if (!empty($context['post_url']))
		echo '
		<form action="', $context['post_url'], '" onsubmit="submitonce(this);" method="post" accept-charset="UTF-8">';
	echo '
			<h2 class="category_header">
				', $context['uninstalling'] ? $txt['package_uninstall_actions'] : $txt['package_install_actions'], ' &quot;', $context['package_name'], '&quot;
			</h2>';

	// Are there data changes to be removed?
	if ($context['uninstalling'] && !empty($context['database_changes']))
	{
		echo '
			<div class="content">
				<label for="do_db_changes"><input type="checkbox" name="do_db_changes" id="do_db_changes" />', $txt['package_db_uninstall'], '</label> [<a href="#" onclick="return swap_database_changes();">', $txt['package_db_uninstall_details'], '</a>]
				<div id="db_changes_div">
					', $txt['package_db_uninstall_actions'], ':
					<ul>';

		foreach ($context['database_changes'] as $change)
			echo '
						<li>', $change, '</li>';

		echo '
					</ul>
				</div>
			</div>';
	}

	echo '
			<div class="information">';

	if (empty($context['actions']) && empty($context['database_changes']))
		echo '
				<br />
				<div class="errorbox">
					', $txt['corrupt_compatible'], '
				</div>
			</div>';
	else
	{
		echo '
					', $txt['perform_actions'], '
			</div>
			<table class="table_grid">
			<thead>
				<tr class="table_head">
					<th scope="col" style="width: 20px;"></th>
					<th scope="col" style="width: 30px;"></th>
					<th scope="col" class="lefttext">', $txt['package_install_type'], '</th>
					<th scope="col" class="lefttext grid50">', $txt['package_install_action'], '</th>
					<th scope="col" class="lefttext grid20">', $txt['package_install_desc'], '</th>
				</tr>
			</thead>
			<tbody>';

		$i = 1;
		$action_num = 1;
		$js_operations = array();
		foreach ($context['actions'] as $packageaction)
		{
			// Did we pass or fail?  Need to know for later on.
			$js_operations[$action_num] = isset($packageaction['failed']) ? $packageaction['failed'] : 0;

			echo '
				<tr>
					<td>', isset($packageaction['operations']) ? '<img id="operation_img_' . $action_num . '" src="' . $settings['images_url'] . '/selected_open.png" alt="*" class="hide" />' : '', '</td>
					<td>', $i++, '.</td>
					<td>', $packageaction['type'], '</td>
					<td>', $packageaction['action'], '</td>
					<td>', $packageaction['description'], '</td>
				</tr>';

			// Is there water on the knee? Operation!
			if (isset($packageaction['operations']))
			{
				echo '
				<tr id="operation_', $action_num, '">
					<td colspan="5" class="standard_row">
						<table class="table_grid">';

				// Show the operations.
				$operation_num = 1;
				foreach ($packageaction['operations'] as $operation)
				{
					// Determine the position text.
					$operation_text = $operation['position'] == 'replace' ? 'operation_replace' : ($operation['position'] == 'before' ? 'operation_after' : 'operation_before');

					echo '
							<tr>
								<td style="width:0;"></td>
								<td style="width: 30px;" class="smalltext">
									<a href="' . $scripturl . '?action=admin;area=packages;sa=showoperations;operation_key=', $operation['operation_key'], ';package=', $context['filename'], ';filename=', $operation['filename'], (!empty($context['uninstalling']) ? ';reverse' : ''), '" onclick="return reqWin(this.href, 680, 400, false);">
										<img src="', $settings['default_images_url'], '/admin/package_ops.png" alt="" />
									</a>
								</td>
								<td style="width: 30px;" class="smalltext">', $operation_num, '.</td>
								<td class="smalltext">', $txt[$operation_text], '</td>
								<td class="smalltext grid50">', $operation['action'], '</td>
								<td class="smalltext grid20">', $operation['description'], !empty($operation['ignore_failure']) ? ' (' . $txt['operation_ignore'] . ')' : '', '</td>
							</tr>';

					$operation_num++;
				}

				echo '
						</table>
					</td>
				</tr>';

				// Increase it.
				$action_num++;
			}
		}

		echo '
			</tbody>
			</table>
			';

		// What if we have custom themes we can install into? List them too!
		if (!empty($context['theme_actions']))
		{
			echo '
			<br />
			<h2 class="category_header">
				', $context['uninstalling'] ? $txt['package_other_themes_uninstall'] : $txt['package_other_themes'], '
			</h2>
			<div id="custom_changes">
				<div class="information">
					', $txt['package_other_themes_desc'], '
				</div>
				<table class="table_grid">';

			// Loop through each theme and display it's name, and then it's details.
			foreach ($context['theme_actions'] as $id => $theme)
			{
				// Pass?
				$js_operations[$action_num] = !empty($theme['has_failure']);

				echo '
					<tr class="secondary_header">
						<td></td>
						<td class="centertext">';

				if (!empty($context['themes_locked']))
					echo '
							<input type="hidden" name="custom_theme[]" value="', $id, '" />';

				echo '
							<input type="checkbox" name="custom_theme[]" id="custom_theme_', $id, '" value="', $id, '" onclick="', (!empty($theme['has_failure']) ? 'if (this.form.custom_theme_' . $id . '.checked && !confirm(\'' . $txt['package_theme_failure_warning'] . '\')) return false;' : ''), 'invertAll(this, this.form, \'dummy_theme_', $id, '\', true);" ', !empty($context['themes_locked']) ? 'disabled="disabled" checked="checked"' : '', '/>
						</td>
						<td colspan="3">
							', $theme['name'], '
						</td>
					</tr>';

				foreach ($theme['actions'] as $action)
				{
					echo '
					<tr>
						<td>', isset($packageaction['operations']) ? '<img id="operation_img_' . $action_num . '" src="' . $settings['images_url'] . '/selected_open.png" alt="*" class="hide" />' : '', '</td>
						<td class="centertext" style="width: 30px;">
							<input type="checkbox" name="theme_changes[]" value="', !empty($action['value']) ? $action['value'] : '', '" id="dummy_theme_', $id, '" ', (!empty($action['not_mod']) ? '' : 'disabled="disabled"'), ' ', !empty($context['themes_locked']) ? 'checked="checked"' : '', '/>
						</td>
						<td>', $action['type'], '</td>
						<td class="grid50">', $action['action'], '</td>
						<td class="grid20"><strong>', $action['description'], '</strong></td>
					</tr>';

					// Is there water on the knee? Operation!
					if (isset($action['operations']))
					{
						echo '
					<tr id="operation_', $action_num, '">
						<td colspan="5" class="standard_row">
							<table class="table_grid">';

						$operation_num = 1;
						foreach ($action['operations'] as $operation)
						{
							// Determine the position text.
							$operation_text = $operation['position'] == 'replace' ? 'operation_replace' : ($operation['position'] == 'before' ? 'operation_after' : 'operation_before');

							echo '
								<tr>
									<td style="width:0;"></td>
									<td style="width: 30px;" class="smalltext">
										<a href="' . $scripturl . '?action=admin;area=packages;sa=showoperations;operation_key=', $operation['operation_key'], ';package=', $context['filename'], ';filename=', $operation['filename'], (!empty($context['uninstalling']) ? ';reverse' : ''), '" onclick="return reqWin(this.href, 600, 400, false);">
											<img src="', $settings['default_images_url'], '/admin/package_ops.png" alt="" />
										</a>
									</td>
									<td style="width: 30px;" class="smalltext">', $operation_num, '.</td>
									<td class="smalltext">', $txt[$operation_text], '</td>
									<td class="smalltext grid50">', $operation['action'], '</td>
									<td class="smalltext grid20">', $operation['description'], !empty($operation['ignore_failure']) ? ' (' . $txt['operation_ignore'] . ')' : '', '</td>
								</tr>';
							$operation_num++;
						}

						echo '
							</table>
						</td>
					</tr>';

						// Increase it.
						$action_num++;
					}
				}
			}

			echo '
				</table>
			</div>';
		}
	}

	// Are we effectively ready to install?
	if (!$context['ftp_needed'] && (!empty($context['actions']) || !empty($context['database_changes'])))
	{
		echo '
			<div class="submitbutton">
				<input type="submit" value="', $context['uninstalling'] ? $txt['package_uninstall_now'] : $txt['package_install_now'], '" onclick="return ', !empty($context['has_failure']) ? '(submitThisOnce(this) &amp;&amp; confirm(\'' . ($context['uninstalling'] ? $txt['package_will_fail_popup_uninstall'] : $txt['package_will_fail_popup']) . '\'))' : 'submitThisOnce(this)', ';" />
			</div>';
	}
	// If we need ftp information then demand it!
	elseif ($context['ftp_needed'])
	{
		echo '
			<h2 class="category_header">', $txt['package_ftp_necessary'], '</h2>

			<div>
				', template_control_chmod(), '
			</div>';
	}

	if (!empty($context['post_url']))
		echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />', (isset($context['form_sequence_number']) && !$context['ftp_needed']) ? '
			<input type="hidden" name="seqnum" value="' . $context['form_sequence_number'] . '" />' : '', '
		</form>';
	echo '
	</div>';

	// Toggle options.
	echo '
	<script>
		var aOperationElements = [];';

	// Operations.
	if (!empty($js_operations))
	{
		foreach ($js_operations as $key => $operation)
		{
			echo '
			aOperationElements[', $key, '] = new elk_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', $operation ? 'false' : 'true', ',
				aSwappableContainers: [
					\'operation_', $key, '\'
				],
				aSwapImages: [
					{
						sId: \'operation_img_', $key, '\',
						srcExpanded: elk_images_url + \'/selected_open.png\',
						altExpanded: ', JavaScriptEscape($txt['hide']), ',
						srcCollapsed: elk_images_url + \'/selected.png\',
						altCollapsed: ', JavaScriptEscape($txt['show']), ',
					}
				]
			});';
		}
	}

	echo '
	</script>';

	// Get the currently selected item from a select list
	echo '
	<script>
	function get_selected(id)
	{
		var aSelected = document.getElementById(id);
		for (var i = 0; i < aSelected.options.length; i++)
		{
			if (aSelected.options[i].selected == true)
				return aSelected.options[i].value;
		}
		return aSelected.options[0];
	}
	</script>';

	// And a bit more for database changes.
	if ($context['uninstalling'] && !empty($context['database_changes']))
		echo '
	<script>
		var database_changes_area = document.getElementById(\'db_changes_div\'),
			db_vis = false;

		database_changes_area.style.display = "none";
	</script>';
}

/**
 * Show after the package has been installed, redirects / permissions
 */
function template_extract_package()
{
	global $context, $txt, $scripturl;

	if (!empty($context['redirect_url']))
	{
		echo '
	<script>
		setTimeout(function() {doRedirect();}, ', $context['redirect_timeout'], ');

		function doRedirect()
		{
			window.location = "', $context['redirect_url'], '";
		}
	</script>';
	}

	echo '
	<div id="admincenter">';

	if (empty($context['redirect_url']))
		echo '
			<h2 class="category_header">', ($context['uninstalling'] ? $txt['uninstall'] : $txt['extracting']), '</h2>', ($context['uninstalling'] ? '' : '<div class="information">' . $txt['package_installed_extract'] . '</div>');
	else
		echo '
			<h2 class="category_header">', $txt['package_installed_redirecting'], '</h2>';

	echo '
		<div class="generic_list_wrapper">
			<div class="content">';

	// If we are going to redirect we have a slightly different agenda.
	if (!empty($context['redirect_url']))
	{
		echo '
				', $context['redirect_text'], '<br /><br />
				<a href="', $context['redirect_url'], '">', $txt['package_installed_redirect_go_now'], '</a> | <a href="', $scripturl, '?action=admin;area=packages;sa=browse">', $txt['package_installed_redirect_cancel'], '</a>';
	}
	elseif ($context['uninstalling'])
		echo '
				', $txt['package_uninstall_done'];
	elseif ($context['install_finished'])
	{
		if ($context['extract_type'] == 'avatar')
			echo '
				', $txt['avatars_extracted'];
		elseif ($context['extract_type'] == 'language')
			echo '
				', $txt['language_extracted'];
		else
			echo '
				', $txt['package_installed_done'];
	}
	else
		echo '
				', $txt['corrupt_compatible'];

	echo '
			</div>
		</div>';

	// Show the "restore permissions" screen?
	if (function_exists('template_show_list') && !empty($context['restore_file_permissions']['rows']))
	{
		echo '<br />';
		template_show_list('restore_file_permissions');
	}

	echo '
	</div>';
}

/**
 * List the files in an addon package
 */
function template_list()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt['list_file'], '</h2>
		<h2 class="category_header">', $txt['files_archive'], ' ', $context['filename'], ':</h2>
		<div class="content">
			<ol>';

	foreach ($context['files'] as $fileinfo)
		echo '
				<li><a href="', $scripturl, '?action=admin;area=packages;sa=examine;package=', $context['filename'], ';file=', $fileinfo['filename'], '" title="', $txt['view'], '">', $fileinfo['filename'], '</a> (', $fileinfo['size'], ' ', $txt['package_bytes'], ')</li>';

	echo '
			</ol>
			<br />
			<a class="linkbutton_right" href="', $scripturl, '?action=admin;area=packages">', $txt['back'], '</a>
		</div>
	</div>';
}

/**
 * Used to view an individual file from the package
 */
function template_examine()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt['package_examine_file'], '</h2>
		<h2 class="category_header">', $txt['package_file_contents'], ' ', $context['filename'], ':</h2>
		<div class="content">
			<pre class="file_content">', $context['filedata'], '</pre>
			<a href="', $scripturl, '?action=admin;area=packages;sa=list;package=', $context['package'], '">[ ', $txt['list_files'], ' ]</a>
		</div>
	</div>';
}

/**
 * Show the listing of addons on the system, installed, uninstalled, etc.
 */
function template_browse()
{
	global $context, $txt;

	echo '
	<div id="admincenter">';

	$adds_available = false;
	foreach ($context['package_types'] as $type)
	{
		if (!empty($context['available_' . $type]))
		{
			template_show_list('packages_lists_' . $type);
			$adds_available = true;
		}
	}

	if (!$adds_available)
		echo '
		<div class="infobox">', $context['sub_action'] == 'browse' ? $txt['no_packages'] : $txt['no_adds_installed'], '</div>';

	echo '
	</div>';
}

/**
 * Show the install options
 */
function template_install_options()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt['package_install_options'], '</h2>
		<div class="information">
			', $txt['package_install_options_ftp_why'], '
		</div>
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=packages;sa=options" method="post" accept-charset="UTF-8">
			<dl class="settings">
				<dt>
					<label for="pack_server">', $txt['package_install_options_ftp_server'], ':</label>
				</dt>
				<dd>
					<input type="text" name="pack_server" id="pack_server" value="', $context['package_ftp_server'], '" size="30" class="input_text" />
				</dd>
				<dt>
					<label for="pack_port">', $txt['package_install_options_ftp_port'], ':</label>
				</dt>
				<dd>
					<input type="text" name="pack_port" id="pack_port" size="3" value="', $context['package_ftp_port'], '" class="input_text" />
				</dd>
				<dt>
					<label for="pack_user">', $txt['package_install_options_ftp_user'], ':</label>
				</dt>
				<dd>
					<input type="text" name="pack_user" id="pack_user" value="', $context['package_ftp_username'], '" size="30" class="input_text" />
				</dd>
				<dt>
					<label for="package_make_backups">', $txt['package_install_options_make_backups'], '</label>
				</dt>
				<dd>
					<input type="checkbox" name="package_make_backups" id="package_make_backups" value="1" class="input_check"', $context['package_make_backups'] ? ' checked="checked"' : '', ' />
				</dd>
				<dt>
					<label for="package_make_full_backups">', $txt['package_install_options_make_full_backups'], '</label>
				</dt>
				<dd>
					<input type="checkbox" name="package_make_full_backups" id="package_make_full_backups" value="1" class="input_check"', $context['package_make_full_backups'] ? ' checked="checked"' : '', ' />
				</dd>
			</dl>
			<div class="submitbutton">
				<input type="submit" name="save" value="', $txt['save'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
		</form>
	</div>';
}

/**
 * Sometimes you have to set file permissions and hope for the best outcome
 */
function template_control_chmod()
{
	global $context, $txt;

	// Nothing to do? Brilliant!
	if (empty($context['package_ftp']))
		return false;

	if (empty($context['package_ftp']['form_elements_only']))
	{
		echo '
				', sprintf($txt['package_ftp_why'], 'document.getElementById(\'need_writable_list\').style.display = \'\'; return false;'), '<br />
				<div id="need_writable_list" class="smalltext">
					', $txt['package_ftp_why_file_list'], '
					<ul style="display: inline;">';

		if (!empty($context['notwritable_files']))
			foreach ($context['notwritable_files'] as $file)
				echo '
						<li>', $file, '</li>';

		echo '
					</ul>
				</div>';
	}

	echo '
				<div id="ftp_error_div" class="errorbox', !empty($context['package_ftp']['error']) ? '"' : ' hide"', '>
					<span id="ftp_error_message">', !empty($context['package_ftp']['error']) ? $context['package_ftp']['error'] : '', '</span>
				</div>';

	if (!empty($context['package_ftp']['destination']))
		echo '
				<form action="', $context['package_ftp']['destination'], '" method="post" accept-charset="UTF-8">';

	echo '
					<fieldset>
					<dl class="settings">
						<dt>
							<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
						</dt>
						<dd>
							<input type="text" size="30" name="ftp_server" id="ftp_server" value="', $context['package_ftp']['server'], '" class="input_text" />
							<label for="ftp_port">', $txt['package_ftp_port'], ':&nbsp;</label> <input type="text" size="3" name="ftp_port" id="ftp_port" value="', $context['package_ftp']['port'], '" class="input_text" />
						</dd>
						<dt>
							<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="', $context['package_ftp']['username'], '" class="input_text" />
						</dd>
						<dt>
							<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
						</dt>
						<dd>
							<input type="password" size="50" name="ftp_password" id="ftp_password" class="input_password" />
						</dd>
						<dt>
							<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '" class="input_text" />
						</dd>
					</dl>
					</fieldset>';

	if (empty($context['package_ftp']['form_elements_only']))
		echo '
					<div class="submitbutton">
						<span id="test_ftp_placeholder_full"></span>
						<input type="submit" value="', $txt['package_proceed'], '" class="right_submit" />
					</div>';

	if (!empty($context['package_ftp']['destination']))
		echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>';

	// Hide the details of the list.
	if (empty($context['package_ftp']['form_elements_only']))
		echo '
		<script>
			document.getElementById(\'need_writable_list\').style.display = \'none\';
		</script>';

	// Set up to generate the FTP test button.
	echo '
	<script>
		var generatedButton = false,
			package_ftp_test = "', $txt['package_ftp_test'], '",
			package_ftp_test_connection = "', $txt['package_ftp_test_connection'], '",
			package_ftp_test_failed = "', addcslashes($txt['package_ftp_test_failed'], "'"), '";
	</script>';

	// Make sure the button gets generated last.
	theme()->addInlineJavascript('
		generateFTPTest();', true);
}

/**
 * Show the ftp permissions panel when needed
 */
function template_ftp_required()
{
	global $txt;

	echo '
		<fieldset>
			<legend>
				', $txt['package_ftp_necessary'], '
			</legend>
			<div class="ftp_details">
				', template_control_chmod(), '
			</div>
		</fieldset>';
}

/**
 * Used to view a specific edit to a file as the xml defines
 */
function template_view_operations()
{
	global $context, $txt, $settings;

	echo '<!DOCTYPE html>
<html ', $context['right_to_left'] ? 'dir="rtl"' : '', '>
	<head>
		<title>', $txt['operation_title'], '</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/admin.css', CACHE_STALE, '" />
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/', $context['theme_variant_url'], 'index', $context['theme_variant'], '.css', CACHE_STALE, '" />
		<script src="', $settings['default_theme_url'], '/scripts/script.js', CACHE_STALE, '"></script>
		<script src="', $settings['default_theme_url'], '/scripts/theme.js', CACHE_STALE, '"></script>
	</head>
	<body>
		<div class="content">
			', $context['operations']['search'], '
			<br />
				', $context['operations']['replace'], '
		</div>
	</body>
</html>';
}

/**
 * Show the technicolor permissions screen that can be adjusted with FTP
 */
function template_file_permissions()
{
	global $txt, $scripturl, $context, $settings;

	// This will handle expanding the selection.
	// @todo most of this code should go in to admin.js
	echo '
	<script>
		var oRadioColors = {
			0: "#D1F7BF",
			1: "#FFBBBB",
			2: "#FDD7AF",
			3: "#C2C6C0",
			4: "#EEEEEE"
		}
		var oRadioValues = {
			0: "read",
			1: "writable",
			2: "execute",
			3: "custom",
			4: "no_change"
		}

		function dynamicAddMore()
		{
			ajax_indicator(true);

			getXMLDocument(elk_prepareScriptUrl(elk_scripturl) + \'action=admin;area=packages;fileoffset=\' + (parseInt(this.offset) + ', $context['file_limit'], ') + \';onlyfind=\' + escape(this.path) + \';sa=perms;xml;', $context['session_var'], '=', $context['session_id'], '\', onNewFolderReceived);
		}

		// Getting something back?
		function onNewFolderReceived(oXMLDoc)
		{
			ajax_indicator(false);

			var fileItems = oXMLDoc.getElementsByTagName(\'folders\')[0].getElementsByTagName(\'folder\');

			// No folders, no longer worth going further.
			if (fileItems.length < 1)
			{
				if (oXMLDoc.getElementsByTagName(\'roots\')[0].getElementsByTagName(\'root\')[0])
				{
					var rootName = oXMLDoc.getElementsByTagName(\'roots\')[0].getElementsByTagName(\'root\')[0].firstChild.nodeValue;
					var itemLink = document.getElementById(\'link_\' + rootName);

					// Move the children up.
					for (i = 0; i <= itemLink.childNodes.length; i++)
						itemLink.parentNode.insertBefore(itemLink.childNodes[0], itemLink);

					// And remove the link.
					itemLink.parentNode.removeChild(itemLink);
				}
				return false;
			}
			var tableHandle = false,
				isMore = false,
				ident = "",
				my_ident = "",
				curLevel = 0;

			for (var i = 0; i < fileItems.length; i++)
			{
				if (fileItems[i].getAttribute(\'more\') == 1)
				{
					isMore = true;
					var curOffset = fileItems[i].getAttribute(\'offset\');
				}

				if (fileItems[i].getAttribute(\'more\') != 1 && document.getElementById("insert_div_loc_" + fileItems[i].getAttribute(\'ident\')))
				{
					ident = fileItems[i].getAttribute(\'ident\');
					my_ident = fileItems[i].getAttribute(\'my_ident\');
					curLevel = fileItems[i].getAttribute(\'level\') * 5;
					curPath = fileItems[i].getAttribute(\'path\');

					// Get where we\'re putting it next to.
					tableHandle = document.getElementById("insert_div_loc_" + fileItems[i].getAttribute(\'ident\'));

					var curRow = document.createElement("tr");
					curRow.id = "content_" + my_ident;
					curRow.style.display = "table-row";

					var curCol = document.createElement("td");
					curCol.className = "smalltext grid30";

					// This is the name.
					var fileName = document.createTextNode(fileItems[i].firstChild.nodeValue);

					// Start by wacking in the spaces.
					curCol.innerHTML = php_str_repeat("&nbsp;", curLevel);

					// Create the actual text.
					if (fileItems[i].getAttribute(\'folder\') == 1)
					{
						var linkData = document.createElement("a");
						linkData.name = "fol_" + my_ident;
						linkData.id = "link_" + my_ident;
						linkData.href = \'#\';
						linkData.path = curPath + "/" + fileItems[i].firstChild.nodeValue;
						linkData.ident = my_ident;
						linkData.onclick = dynamicExpandFolder;

						var folderImage = document.createElement("img");
						folderImage.src = \'', addcslashes($settings['default_images_url'], '\\'), '/board.png\';
						linkData.appendChild(folderImage);

						linkData.appendChild(fileName);
						curCol.appendChild(linkData);
					}
					else
						curCol.appendChild(fileName);

					curRow.appendChild(curCol);

					// Right, the permissions.
					curCol = document.createElement("td");
					curCol.className = "smalltext grid30";

					var writeSpan = document.createElement("span");
					writeSpan.style.color = fileItems[i].getAttribute(\'writable\') ? "green" : "red";
					writeSpan.innerHTML = fileItems[i].getAttribute(\'writable\') ? \'', $txt['package_file_perms_writable'], '\' : \'', $txt['package_file_perms_not_writable'], '\';
					curCol.appendChild(writeSpan);

					if (fileItems[i].getAttribute(\'permissions\'))
					{
						var permData = document.createTextNode("\u00a0(', $txt['package_file_perms_chmod'], ': " + fileItems[i].getAttribute(\'permissions\') + ")");
						curCol.appendChild(permData);
					}

					curRow.appendChild(curCol);

					// Now add the five radio buttons.
					for (j = 0; j < 5; j++)
					{
						curCol = document.createElement("td");
						curCol.style.backgroundColor = oRadioColors[j];
						curCol.className = "centertext grid8";

						var curInput = document.createElement("input");
						curInput.name = "permStatus[" + curPath + "/" + fileItems[i].firstChild.nodeValue + "]";
						curInput.type = "radio";
						curInput.checked = (j == 4 ? "checked" : "");
						curInput.value = oRadioValues[j];

						curCol.appendChild(curInput);
						curRow.appendChild(curCol);
					}

					// Put the row in.
					tableHandle.parentNode.insertBefore(curRow, tableHandle);

					// Put in a new dummy section?
					if (fileItems[i].getAttribute(\'folder\') == 1)
					{
						var newRow = document.createElement("tr");
						newRow.id = "insert_div_loc_" + my_ident;
						newRow.style.display = "none";
						tableHandle.parentNode.insertBefore(newRow, tableHandle);

						var newCol = document.createElement("td");
						newCol.colspan = 2;
						newRow.appendChild(newCol);
					}
				}
			}

			// Is there some more to remove?
			if (document.getElementById("content_" + ident + "_more"))
			{
				document.getElementById("content_" + ident + "_more").parentNode.removeChild(document.getElementById("content_" + ident + "_more"));
			}

			// Add more?
			if (isMore && tableHandle)
			{
				// Create the actual link.
				var linkData = document.createElement("a");
				linkData.href = \'#fol_\' + my_ident;
				linkData.path = curPath;
				linkData.offset = curOffset;
				linkData.onclick = dynamicAddMore;

				linkData.appendChild(document.createTextNode(\'', $txt['package_file_perms_more_files'], '\'));

				curRow = document.createElement("tr");
				curRow.id = "content_" + ident + "_more";
				tableHandle.parentNode.insertBefore(curRow, tableHandle);
				curCol = document.createElement("td");
				curCol.className = "smalltext";
				curCol.width = "40%";

				curCol.innerHTML = php_str_repeat("&nbsp;", curLevel);
				curCol.appendChild(document.createTextNode(\'\\u00ab \'));
				curCol.appendChild(linkData);
				curCol.appendChild(document.createTextNode(\' \\u00bb\'));

				curRow.appendChild(curCol);
				curCol = document.createElement("td");
				curCol.className = "smalltext";
				curRow.appendChild(curCol);
			}

			// Keep track of it.
			var curInput = document.createElement("input");
			curInput.name = "back_look[]";
			curInput.type = "hidden";
			curInput.value = curPath;

			curCol.appendChild(curInput);
		}
	</script>';

	echo '
	<div class="warningbox">
		<div>
			<strong>', $txt['package_file_perms_warning'], ':</strong><br />
				', $txt['package_file_perms_warning_desc'], '
		</div>
	</div>

	<form id="admin_form_wrapper" class="file_permissions" action="', $scripturl, '?action=admin;area=packages;sa=perms;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
		<h2 class="category_header">
			<span class="floatleft">', $txt['package_file_perms'], '</span><span class="fperm floatright">', $txt['package_file_perms_new_status'], '</span>
		</h2>
		<table class="table_grid">
			<thead>
				<tr class="table_head">
					<th class="lefttext grid30">&nbsp;', $txt['package_file_perms_name'], '&nbsp;</th>
					<th class="lefttext grid30">', $txt['package_file_perms_status'], '</th>
					<th class="centertext grid8"><span class="filepermissions">', $txt['package_file_perms_status_read'], '</span></th>
					<th class="centertext grid8"><span class="filepermissions">', $txt['package_file_perms_status_write'], '</span></th>
					<th class="centertext grid8"><span class="filepermissions">', $txt['package_file_perms_status_execute'], '</span></th>
					<th class="centertext grid8"><span class="filepermissions">', $txt['package_file_perms_status_custom'], '</span></th>
					<th class="centertext grid8"><span class="filepermissions">', $txt['package_file_perms_status_no_change'], '</span></th>
				</tr>
			</thead>
			<tbody>';

	foreach ($context['file_tree'] as $name => $dir)
	{
		echo '
				<tr>
					<td class="grid30"><strong>';

		if (!empty($dir['type']) && ($dir['type'] == 'dir' || $dir['type'] == 'dir_recursive'))
			echo '
						<img src="', $settings['default_images_url'], '/board.png" alt="*" />';

		echo '
						', $name, '</strong>
					</td>
					<td class="grid30">
						<span style="color: ', ($dir['perms']['chmod'] ? 'green' : 'red'), '">', ($dir['perms']['chmod'] ? $txt['package_file_perms_writable'] : $txt['package_file_perms_not_writable']), '</span>
						', ($dir['perms']['perms'] ? '&nbsp;(' . $txt['package_file_perms_chmod'] . ': ' . substr(sprintf('%o', $dir['perms']['perms']), -4) . ')' : ''), '
					</td>
					<td class="perm_read centertext grid8"><input type="radio" name="permStatus[', $name, ']" value="read" /></td>
					<td class="perm_write centertext grid8"><input type="radio" name="permStatus[', $name, ']" value="writable" /></td>
					<td class="perm_execute centertext grid8"><input type="radio" name="permStatus[', $name, ']" value="execute" /></td>
					<td class="perm_custom centertext grid8"><input type="radio" name="permStatus[', $name, ']" value="custom" /></td>
					<td class="perm_nochange centertext grid8"><input type="radio" name="permStatus[', $name, ']" value="no_change" checked="checked" /></td>
				</tr>
			';

		if (!empty($dir['contents']))
			template_permission_show_contents($name, $dir['contents'], 1);
	}

	echo '
			</tbody>
		</table>
		<br />
		<h2 class="category_header">', $txt['package_file_perms_change'], '</h2>
		<div class="content">
			<fieldset>
				<dl>
					<dt>
						<input type="radio" name="method" value="individual" checked="checked" id="method_individual" />
						<label for="method_individual"><strong>', $txt['package_file_perms_apply'], '</strong></label>
					</dt>
					<dd>
						<em class="smalltext">', $txt['package_file_perms_custom'], ': <input type="text" name="custom_value" value="0755" maxlength="4" size="5" class="input_text" />&nbsp;<a href="', $scripturl, '?action=quickhelp;help=chmod_flags" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s></a></em>
					</dd>
					<dt>
						<input type="radio" name="method" value="predefined" id="method_predefined" />
						<label for="method_predefined"><strong>', $txt['package_file_perms_predefined'], ':</strong></label>
						<select name="predefined" onchange="document.getElementById(\'method_predefined\').checked = \'checked\';">
							<option value="restricted" selected="selected">', $txt['package_file_perms_pre_restricted'], '</option>
							<option value="standard">', $txt['package_file_perms_pre_standard'], '</option>
							<option value="free">', $txt['package_file_perms_pre_free'], '</option>
						</select>
					</dt>
					<dd>
						<em class="smalltext">', $txt['package_file_perms_predefined_note'], '</em>
					</dd>
				</dl>
			</fieldset>';

	// Likely to need FTP?
	if (empty($context['ftp_connected']))
		echo '
			<p>
				', $txt['package_file_perms_ftp_details'], ':
			</p>
			', template_control_chmod(), '
			<div class="information">', $txt['package_file_perms_ftp_retain'], '</div>';

	echo '
			<div class="submitbutton">
				<span id="test_ftp_placeholder_full"></span>
				<input type="hidden" name="action_changes" value="1" />
				<input type="submit" value="', $txt['package_file_perms_go'], '" name="go" />
			</div>
		</div>';

	// Any looks fors we've already done?
	foreach ($context['look_for'] as $path)
		echo '
			<input type="hidden" name="back_look[]" value="', $path, '" />';
	echo '
	</form>';
}

/**
 * @todo
 *
 * @param string $ident
 * @param array $contents
 * @param int $level
 * @param boolean $has_more
 */
function template_permission_show_contents($ident, $contents, $level, $has_more = false)
{
	global $settings, $txt, $scripturl, $context;

	$js_ident = preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident);

	// Have we actually done something?
	$drawn_div = false;

	foreach ($contents as $name => $dir)
	{
		if (isset($dir['perms']))
		{
			if (!$drawn_div)
			{
				$drawn_div = true;
				echo '
			</tbody>
			</table>
			<table class="table_grid" id="', $js_ident, '">
			<tbody>';
			}

			$cur_ident = preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident . '/' . $name);
			echo '
				<tr id="content_', $cur_ident, '">
					<td class="smalltext grid30">' . str_repeat('&nbsp;', $level * 5), '
						', (!empty($dir['type']) && $dir['type'] == 'dir_recursive') || !empty($dir['list_contents']) ? '<a id="link_' . $cur_ident . '" href="' . $scripturl . '?action=admin;area=packages;sa=perms;find=' . base64_encode($ident . '/' . $name) . ';back_look=' . $context['back_look_data'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '#fol_' . $cur_ident . '" onclick="return expandFolder(\'' . $cur_ident . '\', \'' . addcslashes($ident . '/' . $name, "'\\") . '\');">' : '';

			if (!empty($dir['type']) && ($dir['type'] == 'dir' || $dir['type'] == 'dir_recursive'))
				echo '
						<img src="', $settings['default_images_url'], '/board.png" alt="*" />';

			echo '
						', $name, '
						', (!empty($dir['type']) && $dir['type'] == 'dir_recursive') || !empty($dir['list_contents']) ? '</a>' : '', '
					</td>
					<td class="smalltext grid30">
						<span class="', ($dir['perms']['chmod'] ? 'success' : 'error'), '">', ($dir['perms']['chmod'] ? $txt['package_file_perms_writable'] : $txt['package_file_perms_not_writable']), '</span>
						', ($dir['perms']['perms'] ? '&nbsp;(' . $txt['package_file_perms_chmod'] . ': ' . substr(sprintf('%o', $dir['perms']['perms']), -4) . ')' : ''), '
					</td>
					<td class="perm_read centertext grid8"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="read" /></td>
					<td class="perm_write centertext grid8"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="writable" /></td>
					<td class="perm_execute centertext grid8"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="execute" /></td>
					<td class="perm_custom centertext grid8"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="custom" /></td>
					<td class="perm_nochange centertext grid8"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="no_change" checked="checked" /></td>
				</tr>
				<tr id="insert_div_loc_' . $cur_ident . '" class="hide">
					<td colspan="7"></td>
				</tr>';

			if (!empty($dir['contents']))
				template_permission_show_contents($ident . '/' . $name, $dir['contents'], $level + 1, !empty($dir['more_files']));
		}
	}

	// We have more files to show?
	if ($has_more)
		echo '
	<tr id="content_', $js_ident, '_more">
		<td class="smalltext" style="width: 40%;">', str_repeat('&nbsp;', $level * 5), '
			&#171; <a href="', $scripturl, '?action=admin;area=packages;sa=perms;find=', base64_encode($ident), ';fileoffset=', ($context['file_offset'] + $context['file_limit']), ';', $context['session_var'], '=', $context['session_id'], '#fol_', preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident), '">', $txt['package_file_perms_more_files'], '</a> &#187;
		</td>
		<td colspan="6"></td>
	</tr>';

	if ($drawn_div)
	{
		// Hide anything too far down the tree.
		$isFound = false;
		foreach ($context['look_for'] as $tree)
		{
			if (substr($tree, 0, strlen($ident)) == $ident)
				$isFound = true;
		}

		if ($level > 1 && !$isFound)
			echo '
		</tbody>
		</table><script>
			expandFolder(\'', $js_ident, '\', \'\');
		</script>
		<table class="table_grid">
			<tbody>
			<tr class="hide">
				<td colspan="7"></td>
			</tr>';
	}
}

/**
 * Used to show the pause screen when changing permissions
 */
function template_pause_action_permissions()
{
	global $txt, $scripturl, $context;

	// How many have we done?
	$countDown = 5;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $txt['package_file_perms_applying'], '</h2>';

	if (!empty($context['skip_ftp']))
		echo '
		<div class="errorbox">
			', $txt['package_file_perms_skipping_ftp'], '
		</div>';

	// First progress bar for the number of directories we are working
	echo '
		<div class="content">
			<div>
				<strong>', $context['progress_message'], '</strong>
				<div class="progress_bar">
					<div class="full_bar">', $context['progress_percent'], '%</div>
					<div class="blue_percent" style="width: ', $context['progress_percent'], '%;">&nbsp;</div>
				</div>
			</div>';

	// Second progress bar for progress in a specific directory?
	if ($context['method'] != 'individual' && !empty($context['total_files']))
	{
		echo '
			<br />
			<div>
				<strong>', $context['file_progress_message'], '</strong>
				<div class="progress_bar">
					<div class="full_bar">', $context['file_progress_percent'], '%</div>
					<div class="green_percent" style="width: ', $context['file_progress_percent'], '%;">&nbsp;</div>
				</div>
			</div>';
	}

	echo '
			<form action="', $scripturl, '?action=admin;area=packages;sa=perms;', $context['session_var'], '=', $context['session_id'], '" id="autoSubmit" name="autoSubmit" method="post" accept-charset="UTF-8">
				<div class="submitbutton">';

	// Put out the right hidden data.
	if ($context['method'] === 'individual')
		echo '
					<input type="hidden" name="custom_value" value="', $context['custom_value'], '" />
					<input type="hidden" name="totalItems" value="', $context['total_items'], '" />
					<input type="hidden" name="toProcess" value="', base64_encode(serialize($context['to_process'])), '" />';
	else
		echo '
					<input type="hidden" name="predefined" value="', $context['predefined_type'], '" />
					<input type="hidden" name="fileOffset" value="', $context['file_offset'], '" />
					<input type="hidden" name="totalItems" value="', $context['total_items'], '" />
					<input type="hidden" name="dirList" value="', base64_encode(serialize($context['directory_list'])), '" />
					<input type="hidden" name="specialFiles" value="', base64_encode(serialize($context['special_files'])), '" />';

	// Are we not using FTP for whatever reason.
	if (!empty($context['skip_ftp']))
		echo '
					<input type="hidden" name="skip_ftp" value="1" />';

	// Retain state.
	foreach ($context['back_look_data'] as $path)
		echo '
					<input type="hidden" name="back_look[]" value="', $path, '" />';

	// Standard fields
	echo '
					<input type="hidden" name="method" value="', $context['method'], '" />
					<input type="hidden" name="action_changes" value="1" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="submit" name="go" id="cont" value="', $txt['not_done_continue'], '" />
				</div>
			</form>
		</div>
	</div>';

	// Just the countdown stuff
	echo '
	<script>
		doAutoSubmit(', $countDown, ', ', JavaScriptEscape($txt['not_done_continue']), ');
	</script>';
}
