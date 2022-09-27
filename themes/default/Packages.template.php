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
	{
		echo '
			<strong>', $txt['package_installed_warning1'], '</strong><br />
			<br />
			', $txt['package_installed_warning2'], '<br />
			<br />';
	}

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
		{
			echo '
						<option value="', $b, '"', $a === 'selected' ? ' selected="selected"' : '', '>', $b == 'default' ? $txt['package_readme_default'] : ucfirst($b), '</option>';
		}

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
		{
			echo '
						<option value="', $b, '"', $a === 'selected' ? ' selected="selected"' : '', '>', $b == 'default' ? $txt['package_license_default'] : ucfirst($b), '</option>';
		}

		echo '
					</select>
				</span>
			</div>
			<br />';
	}

	if (!empty($context['post_url']))
	{
		echo '
		<form action="', $context['post_url'], '" onsubmit="submitonce(this);" method="post" accept-charset="UTF-8">';
	}

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
		{
			echo '
						<li>', $change, '</li>';
		}

		echo '
					</ul>
				</div>
			</div>';
	}

	echo '
			<div class="information">';

	if (empty($context['actions']) && empty($context['database_changes']))
	{
		echo '
				<br />
				<div class="errorbox">
					', $txt['corrupt_compatible'], '
				</div>
			</div>';
	}
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
								<td class="hide"></td>
								<td class="grid4 smalltext">
									<a href="' . $scripturl . '?action=admin;area=packages;sa=showoperations;operation_key=', $operation['operation_key'], ';package=', $context['filename'], ';filename=', $operation['filename'], (!empty($context['uninstalling']) ? ';reverse' : ''), '" onclick="return reqWin(this.href, 680, 400, false);">
										<i class="icon i-view"></i>
									</a>
								</td>
								<td class="grid4 smalltext">', $operation_num, '.</td>
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
				{
					echo '
							<input type="hidden" name="custom_theme[]" value="', $id, '" />';
				}

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
									<td class="hide"></td>
									<td class="grid4 smalltext">
										<a href="' . $scripturl . '?action=admin;area=packages;sa=showoperations;operation_key=', $operation['operation_key'], ';package=', $context['filename'], ';filename=', $operation['filename'], (!empty($context['uninstalling']) ? ';reverse' : ''), '" onclick="return reqWin(this.href, 600, 400, false);">
											<i class="icon i-view"></i>
										</a>
									</td>
									<td class="grid4 smalltext">', $operation_num, '.</td>
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
			<h2 class="category_header hdicon i-warning">', $txt['package_ftp_necessary'], '</h2>

			<div>
				', template_control_chmod(), '
			</div>';
	}

	if (!empty($context['post_url']))
	{
		echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />', (isset($context['form_sequence_number']) && !$context['ftp_needed']) ? '
			<input type="hidden" name="seqnum" value="' . $context['form_sequence_number'] . '" />' : '', '
		</form>';
	}

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
	{
		echo '
	<script>
		var database_changes_area = document.getElementById(\'db_changes_div\'),
			db_vis = false;

		database_changes_area.style.display = "none";
	</script>';
	}
}

/**
 * Show after the package has been installed, redirects / permissions
 */
function template_extract_package()
{
	global $context, $txt, $scripturl;

	// Override any redirect if we have to show the permissions changed dialog
	if (function_exists('template_show_list') && !empty($context['restore_file_permissions']['rows']))
	{
		$context['redirect_url'] = '';
	}

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
	{
		echo '
			<h2 class="category_header">', ($context['uninstalling'] ? $txt['uninstall'] : $txt['extracting']), '</h2>', ($context['uninstalling'] ? '' : '<div class="information">' . $txt['package_installed_extract'] . '</div>');
	}
	else
	{
		echo '
			<h2 class="category_header">', $txt['package_installed_redirecting'], '</h2>';
	}

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
	{
		echo '
				', $txt['package_uninstall_done'];
	}
	elseif ($context['install_finished'])
	{
		if ($context['extract_type'] == 'avatar')
		{
			echo '
				', $txt['avatars_extracted'];
		}
		elseif ($context['extract_type'] == 'language')
		{
			echo '
				', $txt['language_extracted'];
		}
		else
		{
			echo '
				', $txt['package_installed_done'];
		}
	}
	else
	{
		echo '
				', $txt['corrupt_compatible'];
	}

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
	{
		echo '
				<li><a href="', $scripturl, '?action=admin;area=packages;sa=examine;package=', $context['filename'], ';file=', $fileinfo['filename'], '" title="', $txt['view'], '">', $fileinfo['filename'], '</a> (', $fileinfo['formatted_size'], ')</li>';
	}

	echo '
			</ol>
			<br />
			<a class="linkbutton floatright" href="', $scripturl, '?action=admin;area=packages">', $txt['back'], '</a>
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
		<h2 class="category_header">', $txt['package_examine_file'], ' : ', $context['package'], '</h2>
		<h3 class="category_header">', $txt['package_file_contents'], ' ', $context['filename'], ':</h3>
		<div class="content largetext">
			<code><pre class="file_content prettyprint">', $context['filedata'], '</pre></code>
			<a href="', $scripturl, '?action=admin;area=packages;sa=list;package=', $context['package'], '" class="linkbutton floatright">', $txt['list_files'], '</a>
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
	{
		echo '
		<div class="infobox">', $context['sub_action'] === 'browse' ? $txt['no_packages'] : $txt['no_adds_installed'], '</div>';
	}

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
		<div class="description">
			', $txt['package_install_options_ftp_why'], '
		</div>
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=packages;sa=options" method="post" accept-charset="UTF-8">
			<div class="content">
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
			</dl>
			<hr />
			<dl class="settings">
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
	{
		return false;
	}

	// Asked to show the list of files/Directories we need access to in order to change
	if (empty($context['package_ftp']['form_elements_only']))
	{
		echo '
				', sprintf($txt['package_ftp_why'], 'document.getElementById(\'need_writable_list\').style.display = \'\'; return false;'), '<br />
				<div id="need_writable_list" class="smalltext">
					', $txt['package_ftp_why_file_list'], '
					<ul class="bbc_list">';

		if (!empty($context['notwritable_files']))
		{
			foreach ($context['notwritable_files'] as $file)
			{
				echo '
						<li>', $file, '</li>';
			}
		}

		echo '
					</ul>
				</div>';
	}

	echo '
				<div id="ftp_error_div" class="errorbox', !empty($context['package_ftp']['error']) ? '"' : ' hide"', '>
					<span id="ftp_error_message">', !empty($context['package_ftp']['error']) ? $context['package_ftp']['error'] : '', '</span>
				</div>';

	if (!empty($context['package_ftp']['destination']))
	{
		echo '
				<form action="', $context['package_ftp']['destination'], '" method="post" accept-charset="UTF-8">';
	}

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
	{
		echo '
					<div class="submitbutton">
						<span id="test_ftp_placeholder_full"></span>
						<input type="submit" value="', $txt['package_proceed'], '" class="right_submit" />
					</div>';
	}

	if (!empty($context['package_ftp']['destination']))
	{
		echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>';
	}

	// Hide the details of the list.
	if (empty($context['package_ftp']['form_elements_only']))
	{
		echo '
		<script>
			document.getElementById(\'need_writable_list\').style.display = \'none\';
		</script>';
	}

	// Set up to generate the FTP test button.
	echo '
	<script>
		var generatedButton = false,
			package_ftp_test = "', $txt['package_ftp_test'], '",
			package_ftp_test_connection = "', $txt['package_ftp_test_connection'], '",
			package_ftp_test_failed = "', addcslashes($txt['package_ftp_test_failed'], "'"), '";
	</script>';

	// Make sure the button gets generated last.
	// Prevent browsers from auto completing the FTP password
	theme()->addInlineJavascript('
		disableAutoComplete();
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
