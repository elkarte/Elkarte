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
 * This is the administration center home.
 */
function template_admin()
{
	global $context, $settings, $scripturl, $txt;

	// Welcome message for the admin.
	echo '
		<div id="admincenter">';

	// Is there an update available?
	echo '
			<div id="update_section"></div>';

	echo '
			<div id="admin_main_section">';

	// Display the "live news"
	echo '
				<div id="live_news" class="floatleft">
					<h2 class="category_header">
						<a href="', $scripturl, '?action=quickhelp;help=live_news" onclick="return reqOverlayDiv(this.href);" class="hdicon cat_img_helptopics help"></a>', $txt['live'], '
					</h2>
					<div class="content">
						<div id="ourAnnouncements">', $txt['lfyi'], '</div>
					</div>
				</div>';

	// Show the user version information from their server.
	echo '
				<div id="supportVersionsTable" class="floatright">
					<h2 class="category_header">
						<a class="hdicon cat_img_plus" href="', $scripturl, '?action=admin;area=credits">', $txt['support_title'], '</a>
					</h2>
						<div class="content">
						<div id="version_details">
							<strong>', $txt['support_versions'], ':</strong><br />
							', $txt['support_versions_forum'], ':
							<em id="installedVersion">', $context['forum_version'], '</em><br />
							', $txt['support_versions_current'], ':
							<em id="latestVersion">??</em><br />
							', $context['can_admin'] ? '<a href="' . $scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . $txt['version_check_more'] . '</a>' : '', '<br />';

	// Display all the members who can administrate the forum.
	echo '
							<br />
							<strong>', $txt['administrators'], ':</strong>
							', implode(', ', $context['administrators']);

	// If we have lots of admins... don't show them all.
	if (!empty($context['more_admins_link']))
	{
		echo '
							(', $context['more_admins_link'], ')';
	}

	echo '
						</div>
					</div>
				</div>
			</div>';

	echo '
			<div class="quick_tasks">
				<ul id="quick_tasks" class="flow_hidden">';

	foreach ($context['quick_admin_tasks'] as $task)
	{
		echo '
					<li>
						', !empty($task['icon']) ? '<a href="' . $task['href'] . '"><img src="' . $settings['default_images_url'] . '/admin/' . $task['icon'] . '" alt="" class="home_image" /></a>' : '', '
						<h5>', $task['link'], '</h5>
						<span class="task">', $task['description'], '</span>
					</li>';
	}

	echo '
				</ul>
			</div>
		</div>';

	// This sets the announcements and current versions themselves ;).
	echo '
		<script>
			var oAdminCenter = new elk_AdminIndex({
				bLoadAnnouncements: true,
				sAnnouncementTemplate: ', JavaScriptEscape('
					<dl>
						%content%
					</dl>
				'), ',
				sAnnouncementMessageTemplate: ', JavaScriptEscape('
					<dt><a href="%href%">%subject%</a> ' . $txt['on'] . ' %time%</dt>
					<dd>
						%message%
					</dd>
				'), ',
				sAnnouncementContainerId: \'ourAnnouncements\',

				bLoadVersions: true,
				slatestVersionContainerId: \'latestVersion\',
				sinstalledVersionContainerId: \'installedVersion\',
				sVersionOutdatedTemplate: ', JavaScriptEscape('
					<span class="alert">%currentVersion%</span>
				'), ',

				bLoadUpdateNotification: true,
				sUpdateNotificationContainerId: \'update_section\',
				sUpdateNotificationDefaultTitle: ', JavaScriptEscape($txt['update_available']), ',
				sUpdateNotificationDefaultMessage: ', JavaScriptEscape($txt['update_message']), ',
				sUpdateNotificationTemplate: ', JavaScriptEscape('
						<h3 id="update_title" class="category_header">
							%title%
						</h3>
						<div class="content">
							<div id="update_message" class="smalltext">
								%message%
							</div>
						</div>
				'), ',
				sUpdateNotificationLink: elk_scripturl + ', JavaScriptEscape('?action=admin;area=packageservers;sa=download;auto;package=%package%;' . $context['session_var'] . '=' . $context['session_id']), '

			});
		</script>';
}

/**
 * Show some support information and credits to those who helped make this.
 */
function template_credits()
{
	global $context, $settings, $scripturl, $txt;

	// Show the user version information from their server.
	echo '
					<div id="admincenter">
						<div id="support_credits">
							<h2 class="category_header">
								', $txt['support_title'], ' <img id="credits_logo" src="', $settings['images_url'], '/', $context['theme_variant_url'], 'logo_elk.png" alt="" />
							</h2>
							<div class="content">
								<strong>', $txt['support_versions'], ':</strong><br />
									', $txt['support_versions_forum'], ':
								<em id="installedVersion">', $context['forum_version'], '</em>', $context['can_admin'] ? ' <a href="' . $scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . $txt['version_check_more'] . '</a>' : '', '<br />
									', $txt['support_versions_current'], ':
								<em id="latestVersion">??</em><br />';

	// Display all the variables we have server information for.
	foreach ($context['current_versions'] as $version)
	{
		echo '
									', $version['title'], ':
								<em>', $version['version'], '</em>';

		// More details for this item, show them a link
		if ($context['can_admin'] && isset($version['more']))
		{
			echo
			' <a class="linkbutton" href="', $scripturl, $version['more'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['version_check_more'], '</a>';
		}

		echo '
								<br />';
	}

	echo '
							</div>';

	// Display latest important updates
	if (!empty($context['latest_updates']))
	{
		echo '
							<h3 id="latest_updates" class="category_header">
								', $txt['latest_updates'], '
							</h3>
							<div class="content">
								', $context['latest_updates'], '
							</div>';
	}

	// Point the admin to common support resources.
	echo '
							<div id="support_resources">
								<h2 class="category_header">
									', $txt['support_resources'], '
								</h2>
							</div>
							<div class="content">
								<p>', $txt['support_resources_p1'], '</p>
								<p>', $txt['support_resources_p2'], '</p>
							</div>';

	// The most important part - the credits :P.
	echo '
							<div id="credits_sections">
								<h2 class="category_header">
									', $txt['admin_credits'], '
								</h2>
							</div>
							<div class="content">';

	foreach ($context['credits'] as $section)
	{
		if (isset($section['pretext']))
		{
			echo '
								<p>', $section['pretext'], '</p><hr />';
		}

		echo '
								<dl>';

		foreach ($section['groups'] as $group)
		{
			if (isset($group['title']))
			{
				echo '
									<dt>
										<strong>', $group['title'], ':</strong>
									</dt>';
			}

			echo '
									<dd>', implode(', ', $group['members']), '</dd>';
		}

		echo '
								</dl>';

		if (isset($section['posttext']))
		{
			echo '
								<hr />
								<p>', $section['posttext'], '</p>';
		}
	}

	echo '
							</div>
						</div>
					</div>';

	// This makes all the support information available to the support script...
	echo '
					<script>
						var ourSupportVersions = {};

						ourSupportVersions.forum = "', $context['forum_version'], '";';

	// Don't worry, none of this is logged, it's just used to give information that might be of use.
	foreach ($context['current_versions'] as $variable => $version)
	{
		echo '
						ourSupportVersions.', $variable, ' = "', $version['version'], '";';
	}

	echo '
					</script>';

	// This sets the latest support stuff.
	echo '
					<script>
						var oAdminCenter = new elk_AdminIndex({
							bLoadVersions: true,
							slatestVersionContainerId: \'latestVersion\',
							sinstalledVersionContainerId: \'installedVersion\',
							sVersionOutdatedTemplate: ', JavaScriptEscape('
								<span class="alert">%currentVersion%</span>
							'), '

						});
					</script>';
}

/**
 * Displays information about file versions installed, and compares them to current version.
 */
function template_view_versions()
{
	global $context, $txt;

	echo '
					<div id="admincenter">
						<h2 class="category_header">
							', $txt['admin_version_check'], '
						</h2>
						<div class="information">', $txt['version_check_desc'], '</div>
							<table class="table_grid">
								<thead>
									<tr class="table_head lefttext">
										<th scope="col" class="versionFile">
											<strong>', $txt['admin_elkfile'], '</strong>
										</th>
										<th scope="col" class="versionNumber">
											<strong>', $txt['dvc_your'], '</strong>
										</th>
										<th scope="col" class="versionNumber">
											<strong>', $txt['dvc_current'], '</strong>
										</th>
									</tr>
								</thead>
								<tbody>';

	// The current version of the core package.
	echo '
									<tr>
										<td>
											', $txt['admin_elkpackage'], '
										</td>
										<td>
											<em id="yourVersion">', $context['forum_version'], '</em>
										</td>
										<td>
											<em id="ourVersion">??</em>
										</td>
									</tr>';

	// Now list all the source file versions, starting with the overall version (if all match!).
	echo '
									<tr>
										<td>
											<a href="#" id="sources-link">', $txt['dvc_sources'], '</a>
										</td>
										<td>
											<em id="yoursources">??</em>
										</td>
										<td>
											<em id="oursources">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="sources" class="table_grid">
							<tbody>';

	// Loop through every source file displaying its version - using javascript.
	foreach ($context['file_versions'] as $filename => $version)
	{
		echo '
								<tr>
									<td class="versionFilePad">
										', $filename, '
									</td>
									<td class="versionNumber">
										<em id="yoursources', $filename, '">', $version, '</em>
									</td>
									<td class="versionNumber">
										<em id="oursources', $filename, '">??</em>
									</td>
								</tr>';
	}

	// Done with sources
	echo '
							</tbody>
							</table>';

	// List all the admin file versions, starting with the overall version (if all match!).
	echo '
							<table class="table_grid">
								<tbody>
									<tr>
										<td class="versionFile">
											<a href="#" id="admin-link">', $txt['dvc_admin'], '</a>
										</td>
										<td class="versionNumber">
											<em id="youradmin">??</em>
										</td>
										<td class="versionNumber">
											<em id="ouradmin">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="admin" class="table_grid">
							<tbody>';

	// Loop through every admin file displaying its version - using javascript.
	foreach ($context['file_versions_admin'] as $filename => $version)
	{
		echo '
								<tr>
									<td class="versionFilePad">
										', $filename, '
									</td>
									<td class="versionNumber">
										<em id="youradmin', $filename, '">', $version, '</em>
									</td>
									<td class="versionNumber">
										<em id="ouradmin', $filename, '">??</em>
									</td>
								</tr>';
	}

	// Close the admin section
	echo '
							</tbody>
							</table>';

	// List all the controller file versions, starting with the overall version (if all match!).
	echo '
							<table class="table_grid">
								<tbody>
									<tr>
										<td class="versionFile">
											<a href="#" id="controllers-link">', $txt['dvc_controllers'], '</a>
										</td>
										<td class="versionNumber">
											<em id="yourcontrollers">??</em>
										</td>
										<td class="versionNumber">
											<em id="ourcontrollers">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="controllers" class="table_grid">
							<tbody>';

	// Loop through every controller file displaying its version - using javascript.
	foreach ($context['file_versions_controllers'] as $filename => $version)
	{
		echo '
								<tr>
									<td class="versionFilePad">
										', $filename, '
									</td>
									<td class="versionNumber">
										<em id="yourcontrollers', $filename, '">', $version, '</em>
									</td>
									<td class="versionNumber">
										<em id="ourcontrollers', $filename, '">??</em>
									</td>
								</tr>';
	}

	// Close the controller section
	echo '
							</tbody>
							</table>';

	// List all the database file versions, starting with the overall version (if all match!).
	echo '
							<table class="table_grid">
								<tbody>
									<tr>
										<td class="versionFile">
											<a href="#" id="database-link">', $txt['dvc_database'], '</a>
										</td>
										<td class="versionNumber">
											<em id="yourdatabase">??</em>
										</td>
										<td class="versionNumber">
											<em id="ourdatabase">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="database" class="table_grid">
							<tbody>';

	// Loop through every database file displaying its version - using javascript.
	foreach ($context['file_versions_database'] as $filename => $version)
	{
		echo '
								<tr>
									<td class="versionFilePad">
										', $filename, '
									</td>
									<td class="versionNumber">
										<em id="yourdatabase', $filename, '">', $version, '</em>
									</td>
									<td class="versionNumber">
										<em id="ourdatabase', $filename, '">??</em>
									</td>
								</tr>';
	}

	// Close the database section
	echo '
							</tbody>
							</table>';

	// List all the subs file versions, starting with the overall version (if all match!).
	echo '
							<table class="table_grid">
								<tbody>
									<tr>
										<td class="versionFile">
											<a href="#" id="subs-link">', $txt['dvc_subs'], '</a>
										</td>
										<td class="versionNumber">
											<em id="yoursubs">??</em>
										</td>
										<td class="versionNumber">
											<em id="oursubs">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="subs" class="table_grid">
							<tbody>';

	// Loop through every subs file displaying its version - using javascript.
	foreach ($context['file_versions_subs'] as $filename => $version)
	{
		echo '
								<tr>
									<td class="versionFilePad">
										', $filename, '
									</td>
									<td class="versionNumber">
										<em id="yoursubs', $filename, '">', $version, '</em>
									</td>
									<td class="versionNumber">
										<em id="oursubs', $filename, '">??</em>
									</td>
								</tr>';
	}

	// Close the subs section
	echo '
							</tbody>
							</table>';

	// Now the templates
	echo '
							<table class="table_grid">
								<tbody>
									<tr>
										<td class="versionFile">
											<a href="#" id="default-link">', $txt['dvc_default'], '</a>
										</td>
										<td class="versionNumber">
											<em id="yourdefault">??</em>
										</td>
										<td class="versionNumber">
											<em id="ourdefault">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="default" class="table_grid">
								<tbody>';

	foreach ($context['default_template_versions'] as $filename => $version)
	{
		echo '
									<tr>
										<td class="versionFilePad">
											', $filename, '
										</td>
										<td class="versionNumber">
											<em id="yourdefault', $filename, '">', $version, '</em>
										</td>
										<td class="versionNumber">
											<em id="ourdefault', $filename, '">??</em>
										</td>
									</tr>';
	}

	// Now the language files...
	echo '
								</tbody>
							</table>

							<table class="table_grid">
								<tbody>
									<tr>
										<td class="versionFile">
											<a href="#" id="Languages-link">', $txt['dvc_languages'], '</a>
										</td>
										<td class="versionNumber">
											<em id="yourLanguages">??</em>
										</td>
										<td class="versionNumber">
											<em id="ourLanguages">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Languages" class="table_grid">
								<tbody>';

	foreach ($context['default_language_versions'] as $language => $files)
	{
		echo '
									<tr>
										<td colspan=3" class="versionFilePad">
											<strong>', $language, '</strong>
										</td>
									</tr>';
		foreach ($files as $filename => $version)
		{
			echo '
									<tr>
										<td class="versionFilePad">
											', $filename, '.<em>', $language, '</em>.php
										</td>
										<td class="versionNumber">
											<em id="your', $filename, '.', $language, '">', $version, '</em>
										</td>
										<td class="versionNumber">
											<em id="our', $filename, '.', $language, '">??</em>
										</td>
									</tr>';
		}
	}

	echo '
								</tbody>
							</table>';

	// Finally, display the version information for the currently selected theme - if it is not the default one.
	if (!empty($context['template_versions']))
	{
		echo '
							<table class="table_grid">
								<tbody>
									<tr>
										<td class="versionFile">
											<a href="#" id="Templates-link">', $txt['dvc_templates'], '</a>
										</td>
										<td class="versionNumber">
											<em id="yourTemplates">??</em>
										</td>
										<td class="versionNumber">
											<em id="ourTemplates">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="Templates" class="table_grid">
								<tbody>';

		foreach ($context['template_versions'] as $filename => $version)
		{
			echo '
									<tr>
										<td class="versionFilePad">
											', $filename, '
										</td>
										<td class="versionNumber">
											<em id="yourTemplates', $filename, '">', $version, '</em>
										</td>
										<td class="versionNumber">
											<em id="ourTemplates', $filename, '">??</em>
										</td>
									</tr>';
		}

		echo '
								</tbody>
							</table>';
	}

	echo '
						</div>';

	/* Below is the javascript for this. Upon opening the page it checks the current file versions with ones
	  held at ElkArte.net and works out if they are up to date.  If they aren't it colors that files number
	  red.  It also contains the function, swapOption, that toggles showing the detailed information for each of the
	  file categories. (sources, languages, and templates.) */
	echo '
						<script src="', $context['detailed_version_url'], '"></script>
						<script>
							var oViewVersions = new elk_ViewVersions({
								aKnownLanguages: [
									\'.', implode('\',
									\'.', $context['default_known_languages']), '\'
								],
								oSectionContainerIds: {
									sources: \'sources\',
									admin: \'admin\',
									controllers: \'controllers\',
									database: \'database\',
									subs: \'subs\',
									Default: \'Default\',
									Languages: \'Languages\',
									Templates: \'Templates\'
								}
							});
							var oAdminCenter = new elk_AdminIndex({
								bLoadVersions: true,
								slatestVersionContainerId: \'ourVersion\',
								sinstalledVersionContainerId: \'yourVersion\',
								sVersionOutdatedTemplate: ', JavaScriptEscape('
									<span class="alert">%currentVersion%</span>
								'), '

							});
						</script>';
}

/**
 * Form for stopping people using naughty words, etc.
 */
function template_edit_censored()
{
	global $context, $scripturl, $txt, $modSettings;

	// First section is for adding/removing words from the censored list.
	echo '
	<div id="admincenter" class="admincenter">
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=postsettings;sa=censor" method="post" accept-charset="UTF-8">
			<h2 class="category_header">
				', $txt['admin_censored_words'], '
			</h2>
			<div class="content">
				<div class="information">', $txt['admin_censored_where'], '</div>';

	// Show text boxes for censoring [bad] => [good].
	foreach ($context['censored_words'] as $vulgar => $proper)
	{
		echo '
				<div class="censorWords">
					<input type="text" name="censor_vulgar[]" value="', $vulgar, '" size="30" /> <i class="icon i-chevron-circle-right"></i>
 					<input type="text" name="censor_proper[]" value="', $proper, '" size="30" />
				</div>';
	}

	// Now provide a way to censor more words.
	echo '
				<div class="censorWords">
					<input type="text" name="censor_vulgar[]" size="30" class="input_text" />
					 <i class="icon i-chevron-circle-right"></i>
					 <input type="text" name="censor_proper[]" size="30" class="input_text" />
				</div>
				<div id="moreCensoredWords"></div>
				<div class="censorWords hide" id="moreCensoredWords_link">
					<a class="linkbutton_left" href="#" onclick="addNewWord(); return false;">', $txt['censor_clickadd'], '</a><br />
				</div>
				<script>
					document.getElementById("moreCensoredWords_link").style.display = "block";
				</script>
				<hr class="clear" />
				<dl class="settings">
					<dt>
						<label for="censorWholeWord_check">', $txt['censor_whole_words'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="censorWholeWord" value="1" id="censorWholeWord_check"', empty($modSettings['censorWholeWord']) ? '' : ' checked="checked"', ' />
					</dd>
					<dt>
						<label for="censorIgnoreCase_check">', $txt['censor_case'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="censorIgnoreCase" value="1" id="censorIgnoreCase_check"', empty($modSettings['censorIgnoreCase']) ? '' : ' checked="checked"', ' />
					</dd>
					<dt>
						<a href="' . $scripturl . '?action=quickhelp;help=allow_no_censored" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>' . $txt['help'] . '</s></a><label for="allow_no_censored">', $txt['censor_allow'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="allow_no_censored" value="1" id="allow_no_censored"', empty($modSettings['allow_no_censored']) ? '' : ' checked="checked"', ' />
					</dd>
				</dl>
				<input type="submit" name="save_censor" value="', $txt['save'], '" class="right_submit" />
			</div>
			<br />';

	// This lets you test out your filters by typing in rude words and seeing what comes out.
	echo '
			<h2 class="category_header">', $txt['censor_test'], '</h2>
			<div class="content">
				<div class="centertext">
					<p id="censor_result" class="information hide">', empty($context['censor_test']) ? '' : $context['censor_test'], '</p>
					<input id="censortest" type="text" name="censortest" value="', empty($context['censor_test']) ? '' : $context['censor_test'], '" class="input_text" />
					<input id="preview_button" type="submit" value="', $txt['censor_test_save'], '" />
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input id="token" type="hidden" name="', $context['admin-censor_token_var'], '" value="', $context['admin-censor_token'], '" />
		</form>
	</div>
	<script>
		$(function() {
			$("#preview_button").click(function() {
				return ajax_getCensorPreview();
			});
		});
	</script>';
}

/**
 * Template to show that a task is in progress
 * (not done yet).
 * Maintenance is a lovely thing, isn't it?
 */
function template_not_done()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', empty($context['not_done_title']) ? $txt['not_done_title'] : $context['not_done_title'], '</h2>
		<div class="content">
			<div class="infobox">
				', $txt['not_done_reason'], '
			</div>
			<form id="autoSubmit" name="autoSubmit" action="', $scripturl, $context['continue_get_data'], '" method="post" accept-charset="UTF-8" >';

	// Show the progress bars
	if (!empty($context['continue_percent']))
	{
		echo '
				<div class="progress_bar">
					<div class="full_bar">', $context['continue_percent'], '%</div>
					<div class="green_percent" style="width: ', $context['continue_percent'], '%;">&nbsp;</div>
				</div>';
	}

	if (!empty($context['substep_enabled']))
	{
		echo '
				<div class="progress_bar">
					<div class="full_bar">', $context['substep_title'], ' (', $context['substep_continue_percent'], '%)</div>
					<div class="blue_percent" style="width: ', $context['substep_continue_percent'], '%;">&nbsp;</div>
				</div>';
	}

	echo '
				<input type="submit" name="cont" value="', $txt['not_done_continue'], '" class="right_submit" />
				', $context['continue_post_data'];
	if (!empty($context['admin-maint_token_var']))
	{
		echo '
				<input type="hidden" name="' . $context['admin-maint_token_var'] . '" value="' . $context['admin-maint_token'] . '" />';
	}

	echo '
			</form>
		</div>
	</div>
	<script>
		doAutoSubmit(', $context['continue_countdown'], ', ', JavaScriptEscape($txt['not_done_continue']), ');
	</script>';
}

/**
 * Template for showing settings (Of any kind really!)
 */
function template_show_settings()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="', isset($context['current_subaction']) ? $context['current_subaction'] : 'admincenter', '" class="admincenter">
		<form id="admin_form_wrapper" action="', $context['post_url'], '" method="post" accept-charset="UTF-8"', !empty($context['force_form_onsubmit']) ? ' onsubmit="' . $context['force_form_onsubmit'] . '"' : '', '>';

	// Is there a custom title, maybe even with an icon?
	if (isset($context['settings_title']))
	{
		echo '
			<h2 class="category_header', !empty($context['settings_icon']) ? ' hdicon cat_img_' . $context['settings_icon'] : '', '">', $context['settings_title'], '</h2>';
	}

	// any messages or errors to show?
	if (!empty($context['settings_message']))
	{
		if (!is_array($context['settings_message']))
		{
			$context['settings_message'] = array($context['settings_message']);
		}

		echo '
			<div class="', (empty($context['error_type']) ? 'infobox' : ($context['error_type'] !== 'serious' ? 'warningbox' : 'errorbox')), '" id="errors">
				<ul>
					<li>', implode('</li><li>', $context['settings_message']), '</li>
				</ul>
			</div>';
	}

	// Now actually loop through all the variables.
	$is_open = false;
	foreach ($context['config_vars'] as $config_var)
	{
		// Is it a title or a description?
		if (is_array($config_var) && ($config_var['type'] == 'title' || $config_var['type'] == 'desc'))
		{
			// Not a list yet?
			if ($is_open)
			{
				$is_open = false;
				echo '
					</dl>
				</div>';
			}

			// A title, maybe even with an icon or a help icon?
			if ($config_var['type'] == 'title')
			{
				echo
				(isset($config_var['name']) ? '<a href="#" id="' . $config_var['name'] . '"></a>' : ''), '
					<h3 class="', !empty($config_var['class']) ? $config_var['class'] : 'category_header', '"', !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '', '>';

				if (isset($config_var['helptext']))
				{
					if (empty($config_var['class']))
					{
						echo '
						<a href="' . $scripturl . '?action=quickhelp;help=' . $config_var['helptext'] . '" onclick="return reqOverlayDiv(this.href);" class="hdicon cat_img_helptopics help" title="' . $txt['help'] . '"></a>';
					}
					else
					{
						echo '
						<a href="' . $scripturl . '?action=quickhelp;help=' . $config_var['helptext'] . '" onclick="return reqOverlayDiv(this.href);" class="' . $config_var['class'] . ' help"><i class="helpicon i-help icon-lg"><s>', $txt['help'], '</s></i></a>';
					}
				}
				elseif (isset($config_var['icon']))
				{
					echo
						'<span class="hdicon cat_img_' . $config_var['icon'] . '"></span>';
				}

				echo
				$config_var['label'], '
					</h3>';
			}
			// A description?
			else
			{
				echo '
					<div class="description">
						', $config_var['label'], '
					</div>';
			}

			continue;
		}

		// Not a list yet?
		if (!$is_open)
		{
			$is_open = true;
			echo '
			<div class="content">
				<dl class="settings">';
		}

		// Hang about? Are you pulling my leg - a callback?!
		if (is_array($config_var) && $config_var['type'] == 'callback')
		{
			if (function_exists('template_callback_' . $config_var['name']))
			{
				call_user_func('template_callback_' . $config_var['name']);
			}

			continue;
		}

		if (is_array($config_var))
		{
			// First off, is this a span like a message?
			if (in_array($config_var['type'], array('message', 'warning')))
			{
				echo '
					<dt></dt>
					<dd', $config_var['type'] == 'warning' ? ' class="alert"' : '', (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : ''), '>
						', $config_var['label'], '
					</dd>';
			}
			// Otherwise it's an input box of some kind.
			else
			{
				echo '
					<dt', is_array($config_var) && !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '', '>';

				// Some quick helpers...
				$preinput = !empty($config_var['preinput']) ? $config_var['preinput'] : '';
				$javascript = !empty($config_var['javascript']) ? $config_var['javascript'] : '';
				$disabled = !empty($config_var['disabled']) ? ' disabled="disabled"' : '';
				$invalid = !empty($config_var['invalid']) ? ' class="error"' : '';
				$size = !empty($config_var['size']) && is_numeric($config_var['size']) ? ' size="' . $config_var['size'] . '"' : '';
				$subtext = !empty($config_var['subtext']) ? '<br /><span class="smalltext' . ($disabled ? ' disabled' : ($invalid ? ' error' : '')) . '"> ' . $config_var['subtext'] . '</span>' : '';

				// Show the [?] button.
				if (isset($config_var['helptext']))
				{
					echo '
						<a id="setting_', $config_var['name'], '" href="', $scripturl, '?action=quickhelp;help=', $config_var['helptext'], '" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"><s>', $txt['help'], '</s>';
				}
				else
				{
					echo '
						<a id="setting_', $config_var['name'], '">';
				}

				echo '</a><label for="', $config_var['name'], '"', ($config_var['disabled'] ? ' class="disabled"' : $invalid), '>', $config_var['label'], '</label>', $subtext, '
					</dt>
					<dd', (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : ''), '>',
				$preinput;

				// Show a check box.
				if ($config_var['type'] == 'check')
				{
					echo '
						<input type="checkbox"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '"', ($config_var['value'] ? ' checked="checked"' : ''), ' value="1" />';
				}
				// Escape (via htmlspecialchars.) the text box.
				elseif ($config_var['type'] == 'password')
				{
					echo '
						<input type="password"', $disabled, $javascript, ' name="', $config_var['name'], '[0]" id="', $config_var['name'], '"', $size, ' value="*#fakepass#*" onfocus="this.value = \'\'; this.form.', $config_var['name'], '_confirm.disabled = false;" class="input_password" />
					</dd>
					<dt>
						<a id="setting_', $config_var['name'], '_confirm"></a><span', ($config_var['disabled'] ? ' class="disabled"' : $invalid), '><label for="', $config_var['name'], '_confirm"><em>', $txt['admin_confirm_password'], '</em></label></span>
					</dt>
					<dd ', (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_confirm_dd"' : ''), ' >
						<input type="password" disabled id="', $config_var['name'], '_confirm" name="', $config_var['name'], '[1]"', $size, ' class="input_password" />';
				}
				// Show a selection box.
				elseif ($config_var['type'] == 'select')
				{
					echo '
						<select name="', $config_var['name'], '" id="', $config_var['name'], '" ', $javascript, $disabled, (!empty($config_var['multiple']) ? ' multiple="multiple" class="select_multiple"' : ''), '>';

					foreach ($config_var['data'] as $option)
					{
						if (empty($config_var['multiple']))
						{
							$selected = $option[0] == $config_var['value'];
						}
						else
						{
							$selected = in_array($option[0], $config_var['value']);
						}

						echo '
							<option value="', $option[0], '"', $selected ? ' selected' : '', '>', $option[1], '</option>';
					}

					echo '
						</select>';
				}
				// Text area?
				elseif ($config_var['type'] == 'large_text')
				{
					echo '
						<textarea rows="', (!empty($config_var['size']) ? $config_var['size'] : (!empty($config_var['rows']) ? $config_var['rows'] : 4)), '" cols="', (!empty($config_var['cols']) ? $config_var['cols'] : 30), '" name="', $config_var['name'], '" id="', $config_var['name'], '">', $config_var['value'], '</textarea>';
				}
				// Permission group?
				elseif ($config_var['type'] == 'permissions')
				{
					template_inline_permissions($config_var['name']);
				}
				// BBC selection?
				elseif ($config_var['type'] == 'bbc')
				{
					echo '
						<fieldset id="', $config_var['name'], '">
							<legend>', $txt['bbcTagsToUse_select'], '</legend>
							<ul class="list_bbc">';

					foreach ($config_var['data'] as $bbcTag)
					{
						echo '
								<li>
									<label>
										<input type="checkbox" name="', $config_var['name'], '_enabledTags[]" value="', $bbcTag['tag'], '"', !in_array($bbcTag['tag'], $config_var['disabled_tags']) ? ' checked' : '', ' /> ', $bbcTag['tag'], '
									</label>', $bbcTag['show_help'] ? '
									<a href="' . $scripturl . '?action=quickhelp;help=tag_' . $bbcTag['tag'] . '" onclick="return reqOverlayDiv(this.href);" class="helpicon i-help"></a>' : '', '
								</li>';
					}

					echo '
							</ul>
							<label>
								<input type="checkbox" onclick="invertAll(this, this.form, \'', $config_var['name'], '_enabledTags\');"', $config_var['all_selected'] ? ' checked' : '', ' />
								<em>', $txt['bbcTagsToUse_select_all'], '</em>
							</label>
						</fieldset>';
				}
				// A simple message?
				elseif ($config_var['type'] == 'var_message')
				{
					echo '
						<div', !empty($config_var['name']) ? ' id="' . $config_var['name'] . '"' : '', '>', $config_var['message'], '</div>';
				}
				// Color picker?
				elseif ($config_var['type'] == 'color')
				{
					echo '
						<input type="color"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', $size, ' class="input_text" />';
				}
				// Assume it must be a text box.
				else
				{
					echo '
						<input type="text"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', $size, ' class="input_text" />';
				}

				echo ($config_var['invalid']) ? '
						<i class="icon i-alert"></i>' : '';

				echo isset($config_var['postinput']) && $config_var['postinput'] !== '' ? '
							' . $config_var['postinput'] : '', '
					</dd>';
			}
		}
		else
		{
			// Just show a separator.
			if ($config_var == '')
			{
				echo '
				</dl>
				<hr class="clear" />
				<dl class="settings">';
			}
			else
			{
				echo '
					<dd>
						<strong>' . $config_var . '</strong>
					</dd>';
			}
		}
	}

	if ($is_open)
	{
		echo '
				</dl>';
	}

	if (empty($context['settings_save_dont_show']))
	{
		echo '
				<div class="submitbutton">
					<input type="submit" value="', $txt['save'], '"', (!empty($context['save_disabled']) ? ' disabled="disabled"' : ''), (!empty($context['settings_save_onclick']) ? ' onclick="' . $context['settings_save_onclick'] . '"' : ''), ' />
				</div>';
	}

	if ($is_open)
	{
		echo '
			</div>';
	}

	// At least one token has to be used!
	if (isset($context['admin-ssc_token']))
	{
		echo '
			<input type="hidden" name="', $context['admin-ssc_token_var'], '" value="', $context['admin-ssc_token'], '" />';
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>';
}

/**
 * Results page for an admin search.
 */
function template_admin_search_results()
{
	global $context, $txt;

	echo '
					<h2 class="category_header hdicon cat_img_search">
						', sprintf($txt['admin_search_results_desc'], $context['search_term']) . template_admin_quick_search() . '
					</h2>
					<div class="generic_list_wrapper">
						<div class="content">';

	if (empty($context['search_results']))
	{
		echo '
							<p class="centertext"><strong>', $txt['admin_search_results_none'], '</strong></p>';
	}
	else
	{
		echo '
							<ol class="search_results">';

		foreach ($context['search_results'] as $result)
		{
			// Is it a result from the online manual?
			if ($context['search_type'] == 'online')
			{
				echo '
								<li>
									<p>
										<a href="', $context['doc_scripturl'], str_replace(' ', '_', $result['title']), '" target="_blank" class="new_win"><strong>', $result['title'], '</strong></a>
									</p>
									<p class="double_height">
										', $result['snippet'], '
									</p>
								</li>';
			}
			// Otherwise it's... not!
			else
			{
				echo '
								<li>
									<a href="', $result['url'], '"><strong>', $result['name'], '</strong></a> [', isset($txt['admin_search_section_' . $result['type']]) ? $txt['admin_search_section_' . $result['type']] : $result['type'], ']';

				if ($result['help'])
				{
					echo '
									<p class="double_height">', $result['help'], '</p>';
				}

				echo '
								</li>';
			}
		}

		echo '
							</ol>';
	}

	echo '
						</div>
					</div>';
}

/**
 * This little beauty shows questions and answer from the captcha type feature.
 */
function template_callback_question_answer_list()
{
	global $txt, $context;

	echo '
			<dt class="questions">
				<strong>', $txt['setup_verification_question'], '</strong>
			</dt>
			<dd class="questions">
				<strong>', $txt['setup_verification_answer'], '</strong>
			</dd>';

	foreach ($context['question_answers'] as $data)
	{
		echo '
			<dt class="questions">
				<input type="text" name="question[', $data['id_question'], ']" value="', $data['question'], '" size="40" class="input_text verification_question" />';

		if (!empty($context['languages']))
		{
			echo '
				<select name="language[', $data['id_question'], ']">';

			foreach ($context['languages'] as $lang)
			{
				echo '
					<option value="', $lang['filename'], '"', $lang['filename'] == $data['language'] ? ' selected="selected"' : '', '>', $lang['name'], '</option>';
			}

			echo '
				</select>';
		}

		echo '
			</dt>
			<dd class="questions">';

		$count = count($data['answer']) - 1;
		foreach ($data['answer'] as $id => $answer)
		{
			echo '
				<input type="text" name="answer[', $data['id_question'], '][]" value="', $answer, '" size="40" class="input_text verification_answer" />', $id == $count ? '<br />
				<a href="#" onclick="addAnotherAnswer(this, ' . $data['id_question'] . '); return false;">&#171; ' . $txt['setup_verification_add_more_answers'] . ' &#187;</a>' : '<br />';
		}

		echo '
			</dd>';
	}

	$lang_dropdown = '';
	if (!empty($context['languages']))
	{
		$lang_dropdown .= '
			<select name="language[b-%question_last_blank%]">';

		foreach ($context['languages'] as $lang)
		{
			$lang_dropdown .= '
				<option value="' . $lang['filename'] . '"' . ($lang['selected'] ? ' selected="selected"' : '') . '>' . $lang['name'] . '</option>';
		}

		$lang_dropdown .= '
			</select>';
	}

	// Some blank ones.
	for ($count = 0; $count < 3; $count++)
	{
		echo '
			<dt class="questions">
				<input type="text" name="question[b-', $count, ']" size="40" class="input_text verification_question" />',
		str_replace('%question_last_blank%', $count, $lang_dropdown), '
			</dt>
			<dd class="questions">
				<input type="text" name="answer[b-', $count, '][]" size="40" class="input_text verification_answer" /><br />
				<a href="#" onclick="addAnotherAnswer(this, \'b-', $count, '\'); return false;">&#171; ', $txt['setup_verification_add_more_answers'], ' &#187;</a>
			</dd>';
	}

	echo '
		<dt id="add_more_question_placeholder" class="hide"></dt>
		<dd></dd>
		<dt id="add_more_link_div" class="hide">
			<a href="#" onclick="addAnotherQuestion(); return false;">&#171; ', $txt['setup_verification_add_more'], ' &#187;</a>
		</dt><dd></dd>';

	theme()->addInlineJavascript('
				document.getElementById(\'add_more_link_div\').style.display = \'block\';
				var question_last_blank = ' . $count . ';
				var txt_add_another_answer = ' . JavaScriptEscape('&#171; ' . $txt['setup_verification_add_more_answers'] . ' &#187;') . ';
				var add_question_template = ' . JavaScriptEscape('
			<dt class="questions">
				<input type="text" name="question[b-%question_last_blank%]" size="40" class="input_text verification_question" />' .
			$lang_dropdown . '
			</dt>
			<dd class="questions">
				<input type="text" name="answer[b-%question_last_blank%][]" size="40" class="input_text verification_answer" /><br />
				<a href="#" onclick="addAnotherAnswer(this, \'b-%question_last_blank%\'); return false;">%setup_verification_add_more_answers%</a>
			</dd>
			<dt id="add_more_question_placeholder" class="hide"></dt>') . ';
				var add_answer_template = ' . JavaScriptEscape('
				<input type="text" name="answer[%question_last_blank%][]" size="40" class="input_text verification_answer" /><br />
				<a href="#" onclick="addAnotherAnswer(this, \'%question_last_blank%\'); return false;">&#171; ' . $txt['setup_verification_add_more_answers'] . ' &#187;</a>') . ';', true);
}

/**
 * Repairing boards.
 */
function template_repair_boards()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $context['error_search'] ? $txt['errors_list'] : $txt['errors_fixing'], '</h2>
		<div class="content">';

	// Are we actually fixing them, or is this just a prompt?
	if ($context['error_search'])
	{
		if (!empty($context['to_fix']))
		{
			echo '
				', $txt['errors_found'], ':
			<ul>';

			foreach ($context['repair_errors'] as $error)
			{
				echo '
				<li>
					', $error, '
				</li>';
			}

			echo '
			</ul>
			<p class="noticebox">
				', $txt['errors_fix'], '
			</p>
			<p>
				<strong><a class="linkbutton" href="', $scripturl, '?action=admin;area=repairboards;fixErrors;', $context['session_var'], '=', $context['session_id'], '">', $txt['yes'], '</a> - <a href="', $scripturl, '?action=admin;area=maintain">', $txt['no'], '</a></strong>
			</p>';
		}
		else
		{
			echo '
			<p class="infobox">', $txt['maintain_no_errors'], '</p>
			<p>
				<a class="linkbutton" href="', $scripturl, '?action=admin;area=maintain;sa=routine">', $txt['maintain_return'], '</a>
			</p>';
		}
	}
	else
	{
		if (!empty($context['redirect_to_recount']))
		{
			echo '
			<p>
				', $txt['errors_do_recount'], '
			</p>
			<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=recount" id="recount_form" method="post">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="submit" name="cont" id="cont" value="', $txt['errors_recount_now'], '" />
			</form>';
		}
		else
		{
			echo '
		<p class="successbox">', $txt['errors_fixed'], '</p>
		<p>
			<a class="linkbutton" href="', $scripturl, '?action=admin;area=maintain;sa=routine">', $txt['maintain_return'], '</a>
		</p>';
		}
	}

	echo '
		</div>
	</div>';

	if (!empty($context['redirect_to_recount']))
	{
		echo '
	<script>
		doAutoSubmit(5, ', JavaScriptEscape($txt['errors_recount_now']), ', "recount_form");
	</script>';
	}
}

/**
 * Retrieves info from the php_info function, scrubs and preps it for display
 */
function template_php_info()
{
	global $context, $txt;

	echo '
	<div id="admin_form_wrapper">
		<h2 class="category_header">', $txt['phpinfo_settings'], '</h2>';

	// for each php info area
	foreach ($context['pinfo'] as $area => $php_area)
	{
		echo '
		<table id="', str_replace(' ', '_', $area), '" class="table_grid wordbreak">
			<thead>
			<tr class="table_head">
				<th class="grid33" scope="col"></th>
				<th class="centertext grid33" scope="col"><strong>', $area, '</strong></th>
				<th class="centertext grid33" scope="col"></th>
			</tr>
			</thead>
			<tbody>';

		$localmaster = true;

		// and for each setting in this category
		foreach ($php_area as $key => $setting)
		{
			// start of a local / master setting (3 col)
			if (is_array($setting))
			{
				if ($localmaster)
				{
					// heading row for the settings section of this category's settings
					echo '
			<tr class="secondary_header">
				<td><strong>', $txt['phpinfo_itemsettings'], '</strong></td>
				<td class="centertext"><strong>', $txt['phpinfo_localsettings'], '</strong></td>
				<td class="centertext"><strong>', $txt['phpinfo_defaultsettings'], '</strong></td>
			</tr>';
					$localmaster = false;
				}

				echo '
			<tr>
				<td>', $key, '</td>';

				foreach ($setting as $key_lm => $value)
				{
					echo '
				<td class="centertext">', $value, '</td>';
				}

				echo '
			</tr>';
			}
			// just a single setting (2 col)
			else
			{
				echo '
			<tr>
				<td>', $key, '</td>
				<td colspan="2">', $setting, '</td>
			</tr>';
			}
		}
		echo '
			</tbody>
		</table>
		<br />';
	}

	echo '
	</div>';
}

/**
 * Shows the clean cache button
 */
function template_clean_cache_button_below()
{
	global $txt, $scripturl, $context;

	echo '
	<div class="generic_list_wrapper">
		<h2 class="category_header">', $txt['maintain_cache'], '</h2>
		<div class="content">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=cleancache" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_cache_info'], '</p>
				<input type="submit" value="', $txt['maintain_run_now'], '" class="right_submit" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="', $context['admin-maint_token_var'], '" value="', $context['admin-maint_token'], '" />
			</form>
		</div>
	</div>';
}

/**
 * Admin quick search box.
 */
function template_admin_quick_search()
{
	global $context, $txt, $scripturl;

	if ($context['user']['is_admin'])
	{
		echo '
			<form action="', $scripturl, '?action=admin;area=search" method="post" accept-charset="UTF-8" id="quick_search" class="floatright">
				<input type="text" name="search_term" placeholder="', $txt['admin_search'], '" class="input_text" />
				<select name="sa">
					<option value="internal"', (empty($context['admin_preferences']['sb']) || $context['admin_preferences']['sb'] == 'internal' ? ' selected="selected"' : ''), '>', $txt['admin_search_type_internal'], '</option>
					<option value="member"', (!empty($context['admin_preferences']['sb']) && $context['admin_preferences']['sb'] == 'member' ? ' selected="selected"' : ''), '>', $txt['admin_search_type_member'], '</option>
					<option value="online"', (!empty($context['admin_preferences']['sb']) && $context['admin_preferences']['sb'] == 'online' ? ' selected="selected"' : ''), '>', $txt['admin_search_type_online'], '</option>
				</select>
				<button type="submit" name="search_go" id="search_go" ><i class="icon i-search"></i></button>
			</form>';
	}
}

/**
 * A list of URLs and "words separators" for new search engines in the dropdown
 */
function template_callback_external_search_engines()
{
	global $txt, $context;

	if (!empty($context['search_engines']))
	{
		foreach ($context['search_engines'] as $data)
		{
			echo '
			<dt>
				<label>', $txt['name'], ': <input type="text" name="engine_name[]" value="', $data['name'], '" size="50" class="input_text verification_question" /></label>
			</dt>
			<dd>
				<label>', $txt['url'], ': <input type="text" name="engine_url[]" value="', $data['url'], '" size="35" class="input_text verification_answer" /></label><br />
				<label>', $txt['words_sep'], ': <input type="text" name="engine_separator[]" value="', $data['separator'], '" size="5" class="input_text verification_answer" /></label>
			</dd>';
		}
	}

	echo '
		<dt id="add_more_searches" class="hide"></dt>
		<dd></dd>
		<dt id="add_more_link_div" class="hide">
			<a class="linkbutton" href="#" onclick="addAnotherSearch(', JavaScriptEscape($txt['name']), ', ', JavaScriptEscape($txt['url']), ', ', JavaScriptEscape($txt['words_sep']), '); return false;">', $txt['setup_search_engine_add_more'], '</a>
		</dt>
		<dd></dd>';

	theme()->addInlineJavascript('
				document.getElementById(\'add_more_link_div\').style.display = \'block\';', true);
}

/**
 * Used to show all of the pm message limits each group allows
 */
function template_callback_pm_limits()
{
	global $context;

	foreach ($context['pm_limits'] as $group_id => $group)
	{
		echo '
			<dt>
				<label for="id_group_', $group_id, '">', $group['group_name'], '</label>
			</dt>
			<dd>
				<input type="text" id="id_group_', $group_id, '" name="group[', $group_id, ']" value="', $group['max_messages'], '" size="6" class="input_text" />
			</dd>';
	}
}

/**
 * Template used to show the queries run in the previous page load
 */
function template_viewquery()
{
	global $context, $scripturl;

	foreach ($context['queries_data'] as $q => $query_data)
	{
		echo '
	<div id="qq', $q, '" class="query">
		<a ', $query_data['is_select'] ? 'href="' . $scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '"' : '', '>
			', $query_data['text'], '
		</a><br />', $query_data['position_time'], '
	</div>';

		if (!empty($query_data['explain']['is_error']))
		{
			echo '
	<table class="explain">
		<tr><td>', $query_data['explain']['error_text'], '</td></tr>
	</table>';
		}
		elseif (!empty($query_data['explain']['headers']))
		{
			echo '
	<table class="explain">
		<tr>
			<th>' . implode('</th>
			<th>', array_keys($query_data['explain']['headers'])) . '</th>
		</tr>';

			foreach ($query_data['explain']['body'] as $row)
			{
				echo '
		<tr>
			<td>' . implode('</td>
			<td>', $row) . '</td>
		</tr>';
			}

			echo '
	</table>';
		}
	}
}
