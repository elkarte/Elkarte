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

/**
 * This is the administration center home.
 */
function template_admin()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

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
				<div class="cat_bar">
					<h3 class="catbg">
						<a href="', $scripturl, '?action=quickhelp;help=live_news" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" class="icon" alt="', $txt['help'], '" /></a> ', $txt['live'], '
					</h3>
				</div>
				<div class="windowbg nopadding">
					<div class="content">
						<div id="ourAnnouncements">', $txt['lfyi'], '</div>
					</div>
				</div>
			</div>';

	// Show the user version information from their server.
	echo '
			<div id="supportVersionsTable" class="floatright">
				<div class="cat_bar">
					<h3 class="catbg">
						<a href="', $scripturl, '?action=admin;area=credits">', $txt['support_title'], '</a>
					</h3>
				</div>
				<div class="windowbg nopadding">
					<div class="content">
						<div id="version_details">
							<strong>', $txt['support_versions'], ':</strong><br />
							', $txt['support_versions_forum'], ':
							<em id="yourVersion" style="white-space: nowrap;">', $context['forum_version'], '</em><br />
							', $txt['support_versions_current'], ':
							<em id="ourVersion" style="white-space: nowrap;">??</em><br />
							', $context['can_admin'] ? '<a href="' . $scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . $txt['version_check_more'] . '</a>' : '', '<br />';

	// Display all the members who can administrate the forum.
	echo '
							<br />
							<strong>', $txt['administrators'], ':</strong>
							', implode(', ', $context['administrators']);

	// If we have lots of admins... don't show them all.
	if (!empty($context['more_admins_link']))
		echo '
							(', $context['more_admins_link'], ')';

	echo '
						</div>
					</div>
				</div>
			</div>
		</div>';

	echo '
		<div class="windowbg2 quick_tasks">
			<div class="content">
				<ul id="quick_tasks" class="flow_hidden">';

	foreach ($context['quick_admin_tasks'] as $task)
		echo '
					<li>
						', !empty($task['icon']) ? '<a href="' . $task['href'] . '"><img src="' . $settings['default_images_url'] . '/admin/' . $task['icon'] . '" alt="" class="home_image" /></a>' : '', '
						<h5>', $task['link'], '</h5>
						<span class="task">', $task['description'],'</span>
					</li>';

	echo '
				</ul>
			</div>
		</div>
	</div>';

	// The below functions include all the scripts needed from the simplemachines.org site. The language and format are passed for internationalization.
	if (empty($modSettings['disable_elk_js']))
		echo '
		<script type="text/javascript" src="', $scripturl, '?action=viewadminfile;filename=current-version.js"></script>
		<script type="text/javascript" src="', $scripturl, '?action=viewadminfile;filename=latest-news.js"></script>';

	// This sets the announcements and current versions themselves ;).
	echo '
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/admin.js?alp21"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
			var oAdminIndex = new smf_AdminIndex({
				sSelf: \'oAdminCenter\',

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
				sOurVersionContainerId: \'ourVersion\',
				sYourVersionContainerId: \'yourVersion\',
				sVersionOutdatedTemplate: ', JavaScriptEscape('
					<span class="alert">%currentVersion%</span>
				'), ',

				bLoadUpdateNotification: true,
				sUpdateNotificationContainerId: \'update_section\',
				sUpdateNotificationDefaultTitle: ', JavaScriptEscape($txt['update_available']), ',
				sUpdateNotificationDefaultMessage: ', JavaScriptEscape($txt['update_message']), ',
				sUpdateNotificationTemplate: ', JavaScriptEscape('
					<div class="cat_bar">
						<h3 id="update_title" class="catbg">
							%title%
						</h3>
					</div>
					<div class="windowbg">
						<div class="content">
							<div id="update_message" class="smalltext">
								%message%
							</div>
						</div>
					</div>
				'), ',
				sUpdateNotificationLink: smf_scripturl + ', JavaScriptEscape('?action=admin;area=packages;pgdownload;auto;package=%package%;' . $context['session_var'] . '=' . $context['session_id']), '

			});
		// ]]></script>';
}

/**
 * Show some support information and credits to those who helped make this.
 */
function template_credits()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Show the user version information from their server.
	echo '

	<div id="admincenter">
		<div id="section_header" class="cat_bar">
			<h3 class="catbg">
				', $txt['support_title'], '
			</h3>
		</div>
		<div class="windowbg">
			<div class="content">
				<strong>', $txt['support_versions'], ':</strong><br />
					', $txt['support_versions_forum'], ':
				<em id="yourVersion" style="white-space: nowrap;">', $context['forum_version'], '</em>', $context['can_admin'] ? ' <a href="' . $scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . $txt['version_check_more'] . '</a>' : '', '<br />
					', $txt['support_versions_current'], ':
				<em id="ourVersion" style="white-space: nowrap;">??</em><br />';

	// Display all the variables we have server information for.
	foreach ($context['current_versions'] as $version)
	{
		echo '
					', $version['title'], ':
				<em>', $version['version'], '</em>';

		// more details for this item, show them a link
		if ($context['can_admin'] && isset($version['more']))
			echo
				' <a href="', $scripturl, $version['more'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['version_check_more'], '</a>';
		echo '
				<br />';
	}

	echo '
			</div>
		</div>';

	// Point the admin to common support resources.
	echo '
		<div id="support_resources" class="cat_bar">
			<h3 class="catbg">
				', $txt['support_resources'], '
			</h3>
		</div>
		<div class="windowbg2">
			<div class="content">
				<p>', $txt['support_resources_p1'], '</p>
				<p>', $txt['support_resources_p2'], '</p>
			</div>

		</div>';

	// Display latest support questions from simplemachines.org.
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<a href="', $scripturl, '?action=quickhelp;help=latest_support" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" class="icon" alt="', $txt['help'], '" /></a> ', $txt['support_latest'], '
			</h3>
		</div>
		<div class="windowbg">
			<div class="content">
				<div id="latestSupport">', $txt['support_latest_fetch'], '</div>
			</div>

		</div>';

	// The most important part - the credits :P.
	echo '
		<div id="credits_sections" class="cat_bar">
			<h3 class="catbg">
				', $txt['admin_credits'], '
			</h3>
		</div>
		<div class="windowbg2">
			<div class="content">';

	foreach ($context['credits'] as $section)
	{
		if (isset($section['pretext']))
			echo '
				<p>', $section['pretext'], '</p>';

		echo '
				<dl>';

		foreach ($section['groups'] as $group)
		{
			if (isset($group['title']))
				echo '
					<dt>
						<strong>', $group['title'], ':</strong>
					</dt>';

			echo '
					<dd>', implode(', ', $group['members']), '</dd>';
		}

		echo '
				</dl>';

		if (isset($section['posttext']))
			echo '
				<p>', $section['posttext'], '</p>';
	}

	echo '
			</div>
		</div>
	</div>';

	// This makes all the support information available to the support script...
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			var ourSupportVersions = {};

			ourSupportVersions.forum = "', $context['forum_version'], '";';

	// Don't worry, none of this is logged, it's just used to give information that might be of use.
	foreach ($context['current_versions'] as $variable => $version)
		echo '
			ourSupportVersions.', $variable, ' = "', $version['version'], '";';

	// Now we just have to include the script and wait ;).
	echo '
		// ]]></script>
		<script type="text/javascript" src="', $scripturl, '?action=viewadminfile;filename=current-version.js"></script>
		<script type="text/javascript" src="', $scripturl, '?action=viewadminfile;filename=latest-news.js"></script>
		<script type="text/javascript" src="', $scripturl, '?action=viewadminfile;filename=latest-support.js"></script>';

	// This sets the latest support stuff.
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			function ourCurrentVersion()
			{
				var ourVer, yourVer;

				if (!window.elkVersion)
					return;

				ourVer = document.getElementById("ourVersion");
				yourVer = document.getElementById("yourVersion");

				setInnerHTML(ourVer, window.elkVersion);

				var currentVersion = getInnerHTML(yourVer);
				if (currentVersion != window.elkVersion)
					setInnerHTML(yourVer, "<span class=\"alert\">" + currentVersion + "</span>");
			}
			addLoadEvent(ourCurrentVersion)
		// ]]></script>';
}

/**
 * Displays information about file versions installed, and compares them to current version.
 */
function template_view_versions()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<div id="section_header" class="cat_bar">
			<h3 class="catbg">
				', $txt['admin_version_check'], '
			</h3>
		</div>
		<div class="information">', $txt['version_check_desc'], '</div>
			<table width="100%" class="table_grid">
				<thead>
					<tr class="catbg" align="left">
						<th class="first_th" scope="col" width="50%">
							<strong>', $txt['admin_elkfile'], '</strong>
						</th>
						<th scope="col" width="25%">
							<strong>', $txt['dvc_your'], '</strong>
						</th>
						<th class="last_th" scope="col" width="25%">
							<strong>', $txt['dvc_current'], '</strong>
						</th>
					</tr>
				</thead>
				<tbody>';

	// The current version of the core package.
	echo '
					<tr>
						<td class="windowbg">
							', $txt['admin_elkpackage'], '
						</td>
						<td class="windowbg">
							<em id="yourVersion">', $context['forum_version'], '</em>
						</td>
						<td class="windowbg">
							<em id="ourVersion">??</em>
						</td>
					</tr>';

	// Now list all the source file versions, starting with the overall version (if all match!).
	echo '
									<tr>
										<td class="windowbg">
											<a href="#" id="sources-link">', $txt['dvc_sources'], '</a>
										</td>
										<td class="windowbg">
											<em id="yoursources">??</em>
										</td>
										<td class="windowbg">
											<em id="oursources">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="sources" width="100%" class="table_grid">
							<tbody>';

	// Loop through every source file displaying its version - using javascript.
	foreach ($context['file_versions'] as $filename => $version)
		echo '
								<tr>
									<td class="windowbg2" width="50%" style="padding-left: 3ex;">
										', $filename, '
									</td>
									<td class="windowbg2" width="25%">
										<em id="yoursources', $filename, '">', $version, '</em>
									</td>
									<td class="windowbg2" width="25%">
										<em id="oursources', $filename, '">??</em>
									</td>
								</tr>';

	// Done with sources
	echo '
							</tbody>
							</table>';

	// List all the admin file versions, starting with the overall version (if all match!).
	echo '
							<table width="100%" class="table_grid">
								<tbody>
									<tr>
										<td class="windowbg" width="50%">
											<a href="#" id="admin-link">', $txt['dvc_admin'], '</a>
										</td>
										<td class="windowbg" width="25%">
											<em id="youradmin">??</em>
										</td>
										<td class="windowbg" width="25%">
											<em id="ouradmin">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="admin" width="100%" class="table_grid">
							<tbody>';

	// Loop through every admin file displaying its version - using javascript.
	foreach ($context['file_versions_admin'] as $filename => $version)
		echo '
								<tr>
									<td class="windowbg2" width="50%" style="padding-left: 3ex;">
										', $filename, '
									</td>
									<td class="windowbg2" width="25%">
										<em id="youradmin', $filename, '">', $version, '</em>
									</td>
									<td class="windowbg2" width="25%">
										<em id="ouradmin', $filename, '">??</em>
									</td>
								</tr>';

	// Close the admin section
	echo '
							</tbody>
							</table>';

	// List all the controller file versions, starting with the overall version (if all match!).
	echo '
							<table width="100%" class="table_grid">
								<tbody>
									<tr>
										<td class="windowbg" width="50%">
											<a href="#" id="controllers-link">', $txt['dvc_controllers'], '</a>
										</td>
										<td class="windowbg" width="25%">
											<em id="yourcontrollers">??</em>
										</td>
										<td class="windowbg" width="25%">
											<em id="ourcontrollers">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="controllers" width="100%" class="table_grid">
							<tbody>';

	// Loop through every controller file displaying its version - using javascript.
	foreach ($context['file_versions_controllers'] as $filename => $version)
		echo '
								<tr>
									<td class="windowbg2" width="50%" style="padding-left: 3ex;">
										', $filename, '
									</td>
									<td class="windowbg2" width="25%">
										<em id="yourcontrollers', $filename, '">', $version, '</em>
									</td>
									<td class="windowbg2" width="25%">
										<em id="ourcontrollers', $filename, '">??</em>
									</td>
								</tr>';

	// Close the controller section
	echo '
							</tbody>
							</table>';

	// List all the database file versions, starting with the overall version (if all match!).
	echo '
							<table width="100%" class="table_grid">
								<tbody>
									<tr>
										<td class="windowbg" width="50%">
											<a href="#" id="database-link">', $txt['dvc_database'], '</a>
										</td>
										<td class="windowbg" width="25%">
											<em id="yourdatabase">??</em>
										</td>
										<td class="windowbg" width="25%">
											<em id="ourdatabase">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="database" width="100%" class="table_grid">
							<tbody>';

	// Loop through every database file displaying its version - using javascript.
	foreach ($context['file_versions_database'] as $filename => $version)
		echo '
								<tr>
									<td class="windowbg2" width="50%" style="padding-left: 3ex;">
										', $filename, '
									</td>
									<td class="windowbg2" width="25%">
										<em id="yourdatabase', $filename, '">', $version, '</em>
									</td>
									<td class="windowbg2" width="25%">
										<em id="ourdatabase', $filename, '">??</em>
									</td>
								</tr>';

	// Close the database section
	echo '
							</tbody>
							</table>';

	// List all the subs file versions, starting with the overall version (if all match!).
	echo '
							<table width="100%" class="table_grid">
								<tbody>
									<tr>
										<td class="windowbg" width="50%">
											<a href="#" id="subs-link">', $txt['dvc_subs'], '</a>
										</td>
										<td class="windowbg" width="25%">
											<em id="yoursubs">??</em>
										</td>
										<td class="windowbg" width="25%">
											<em id="oursubs">??</em>
										</td>
									</tr>
								</tbody>
							</table>

							<table id="subs" width="100%" class="table_grid">
							<tbody>';

	// Loop through every subs file displaying its version - using javascript.
	foreach ($context['file_versions_subs'] as $filename => $version)
		echo '
								<tr>
									<td class="windowbg2" width="50%" style="padding-left: 3ex;">
										', $filename, '
									</td>
									<td class="windowbg2" width="25%">
										<em id="yoursubs', $filename, '">', $version, '</em>
									</td>
									<td class="windowbg2" width="25%">
										<em id="oursubs', $filename, '">??</em>
									</td>
								</tr>';

	// Close the subs section
	echo '
							</tbody>
							</table>';

	// Default template files.
	echo '
			</tbody>
			</table>

			<table width="100%" class="table_grid">
				<tbody>
					<tr>
						<td class="windowbg" width="50%">
							<a href="#" id="default-link">', $txt['dvc_default'], '</a>
						</td>
						<td class="windowbg" width="25%">
							<em id="yourdefault">??</em>
						</td>
						<td class="windowbg" width="25%">
							<em id="ourdefault">??</em>
						</td>
					</tr>
				</tbody>
			</table>

			<table id="default" width="100%" class="table_grid">
				<tbody>';

	foreach ($context['default_template_versions'] as $filename => $version)
		echo '
					<tr>
						<td class="windowbg2" width="50%" style="padding-left: 3ex;">
							', $filename, '
						</td>
						<td class="windowbg2" width="25%">
							<em id="yourdefault', $filename, '">', $version, '</em>
						</td>
						<td class="windowbg2" width="25%">
							<em id="ourdefault', $filename, '">??</em>
						</td>
					</tr>';

	// Now the language files...
	echo '
				</tbody>
			</table>

			<table width="100%" class="table_grid">
				<tbody>
					<tr>
						<td class="windowbg" width="50%">
							<a href="#" id="Languages-link">', $txt['dvc_languages'], '</a>
						</td>
						<td class="windowbg" width="25%">
							<em id="yourLanguages">??</em>
						</td>
						<td class="windowbg" width="25%">
							<em id="ourLanguages">??</em>
						</td>
					</tr>
				</tbody>
			</table>

			<table id="Languages" width="100%" class="table_grid">
				<tbody>';

	foreach ($context['default_language_versions'] as $language => $files)
	{
		foreach ($files as $filename => $version)
			echo '
					<tr>
						<td class="windowbg2" width="50%" style="padding-left: 3ex;">
							', $filename, '.<em>', $language, '</em>.php
						</td>
						<td class="windowbg2" width="25%">
							<em id="your', $filename, '.', $language, '">', $version, '</em>
						</td>
						<td class="windowbg2" width="25%">
							<em id="our', $filename, '.', $language, '">??</em>
						</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>';

	// Finally, display the version information for the currently selected theme - if it is not the default one.
	if (!empty($context['template_versions']))
	{
		echo '
			<table width="100%" class="table_grid">
				<tbody>
					<tr>
						<td class="windowbg" width="50%">
							<a href="#" id="Templates-link">', $txt['dvc_templates'], '</a>
						</td>
						<td class="windowbg" width="25%">
							<em id="yourTemplates">??</em>
						</td>
						<td class="windowbg" width="25%">
							<em id="ourTemplates">??</em>
						</td>
					</tr>
				</tbody>
			</table>

			<table id="Templates" width="100%" class="table_grid">
				<tbody>';

		foreach ($context['template_versions'] as $filename => $version)
			echo '
					<tr>
						<td class="windowbg2" width="50%" style="padding-left: 3ex;">
							', $filename, '
						</td>
						<td class="windowbg2" width="25%">
							<em id="yourTemplates', $filename, '">', $version, '</em>
						</td>
						<td class="windowbg2" width="25%">
							<em id="ourTemplates', $filename, '">??</em>
						</td>
					</tr>';

		echo '
				</tbody>
			</table>';
	}

	echo '
		</div>';

	/* Below is the hefty javascript for this. Upon opening the page it checks the current file versions with ones
	   held at elkarte.net and works out if they are up to date.  If they aren't it colors that files number
	   red.  It also contains the function, swapOption, that toggles showing the detailed information for each of the
	   file categories. (sources, languages, and templates.) */
	echo '
		<script type="text/javascript" src="', $scripturl, '?action=viewadminfile;filename=detailed-version.js"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
			var oViewVersions = new smf_ViewVersions({
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
		// ]]></script>';
}

/**
 * Form for stopping people using naughty words, etc.
 */
function template_edit_censored()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// First section is for adding/removing words from the censored list.
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=postsettings;sa=censor" method="post" accept-charset="UTF-8">
			<div id="section_header" class="cat_bar">
				<h3 class="catbg">
					', $txt['admin_censored_words'], '
				</h3>
			</div>
			<div class="windowbg2">
				<div class="content">
					<p>', $txt['admin_censored_where'], '</p>';

	// Show text boxes for censoring [bad   ] => [good  ].
	foreach ($context['censored_words'] as $vulgar => $proper)
		echo '
					<div style="margin-top: 1ex;">
						<input type="text" name="censor_vulgar[]" value="', $vulgar, '" size="30" /> => <input type="text" name="censor_proper[]" value="', $proper, '" size="30" />
					</div>';

	// Now provide a way to censor more words.
	echo '
					<div style="margin-top: 1ex;">
						<input type="text" name="censor_vulgar[]" size="30" class="input_text" /> => <input type="text" name="censor_proper[]" size="30" class="input_text" />
					</div>
					<div id="moreCensoredWords"></div><div style="margin-top: 1ex; display: none;" id="moreCensoredWords_link">
						<a class="button_link" style="float: left" href="#;" onclick="addNewWord(); return false;">', $txt['censor_clickadd'], '</a><br />
					</div>
					<script type="text/javascript"><!-- // --><![CDATA[
						document.getElementById("moreCensoredWords_link").style.display = "";
					// ]]></script>
					<hr width="100%" size="1" class="hrcolor clear" />
					<dl class="settings">
						<dt>
							<strong><label for="censorWholeWord_check">', $txt['censor_whole_words'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="censorWholeWord" value="1" id="censorWholeWord_check"', empty($modSettings['censorWholeWord']) ? '' : ' checked="checked"', ' class="input_check" />
						</dd>
						<dt>
							<strong><label for="censorIgnoreCase_check">', $txt['censor_case'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="censorIgnoreCase" value="1" id="censorIgnoreCase_check"', empty($modSettings['censorIgnoreCase']) ? '' : ' checked="checked"', ' class="input_check" />
						</dd>
					</dl>
					<hr class="hrcolor" />
					<input type="submit" name="save_censor" value="', $txt['save'], '" class="button_submit" />
				</div>
			</div>
			<br />';

	// This table lets you test out your filters by typing in rude words and seeing what comes out.
	echo '
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['censor_test'], '
				</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<p class="centertext">
						<input type="text" name="censortest" value="', empty($context['censor_test']) ? '' : $context['censor_test'], '" class="input_text" />
						<input type="submit" value="', $txt['censor_test_save'], '" class="button_submit" />
					</p>
				</div>
			</div>

			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['admin-censor_token_var'], '" value="', $context['admin-censor_token'], '" />
		</form>
	</div>';
}

/**
 * Template to show that a task is in progress
 * (not done yet).
 * Maintenance is a lovely thing, isn't it?
 */
function template_not_done()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<div id="section_header" class="cat_bar">
			<h3 class="catbg">
				', $txt['not_done_title'], '
			</h3>
		</div>
		<div class="windowbg">
			<div class="content">
				', $txt['not_done_reason'];

	if (!empty($context['continue_percent']))
		echo '
				<br /><br />
				<div class="progress_bar">
					<div class="full_bar">', $context['continue_percent'], '%</div>
					<div class="green_percent" style="width: ', $context['continue_percent'], '%;">&nbsp;</div>
				</div>';

	if (!empty($context['substep_enabled']))
		echo '
				<br /><br />
				<div class="progress_bar">
					<div class="full_bar">', $context['substep_title'], ' (', $context['substep_continue_percent'], '%)</div>
					<div class="blue_percent" style="width: ', $context['substep_continue_percent'], '%;">&nbsp;</div>
				</div>';

	echo '
				<form action="', $scripturl, $context['continue_get_data'], '" method="post" accept-charset="UTF-8" style="margin: 0;" name="autoSubmit" id="autoSubmit">
					<hr class="hrcolor" />
					<input type="submit" name="cont" value="', $txt['not_done_continue'], '" class="button_submit" />
					', $context['continue_post_data'], '
				</form>
			</div>
		</div>
	</div>
	<script type="text/javascript"><!-- // --><![CDATA[
		var countdown = ', $context['continue_countdown'], ';
		var txt_message = "', $txt['not_done_continue'], '";
		doAutoSubmit();
	// ]]></script>';
}

/**
 * Template for showing settings (Of any kind really!)
 */
function template_show_settings()
{
	global $context, $txt, $settings, $scripturl;

	if (!empty($context['settings_pre_javascript']))
		echo '
	<script type="text/javascript"><!-- // --><![CDATA[', $context['settings_pre_javascript'], '// ]]></script>';

	if (!empty($context['settings_insert_above']))
		echo $context['settings_insert_above'];

	echo '
	<div id="admincenter">
		<form id="admin_form_wrapper" action="', $context['post_url'], '" method="post" accept-charset="UTF-8"', !empty($context['force_form_onsubmit']) ? ' onsubmit="' . $context['force_form_onsubmit'] . '"' : '', '>';

	// Is there a custom title?
	if (isset($context['settings_title']))
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					', $context['settings_title'], '
				</h3>
			</div>';

	// any messages or errors to show?
	if (!empty($context['settings_message']))
	{
		if (!is_array($context['settings_message']))
			$context['settings_message'] = array($context['settings_message']);

		echo '
			<div class="', (empty($context['error_type']) ? 'infobox' : ($context['error_type'] !== 'serious' ? 'noticebox' : 'errorbox')), '" id="errors">
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
				</div>
			</div>';
			}

			// A title?
			if ($config_var['type'] == 'title')
			{
				echo '
					<div class="cat_bar">
						<h3 class="', !empty($config_var['class']) ? $config_var['class'] : 'catbg', '"', !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '', '>
							', ($config_var['help'] ? '<a href="' . $scripturl . '?action=quickhelp;help=' . $config_var['help'] . '" onclick="return reqOverlayDiv(this.href);" class="help"><img src="' . $settings['images_url'] . '/helptopics.png" class="icon" alt="' . $txt['help'] . '" /></a>' : ''), '
							', $config_var['label'], '
						</h3>
					</div>';
			}
			// A description?
			else
			{
				echo '
					<p class="description">
						', $config_var['label'], '
					</p>';
			}

			continue;
		}

		// Not a list yet?
		if (!$is_open)
		{
			$is_open = true;
			echo '
			<div class="windowbg2">
				<div class="content">
					<dl class="settings">';
		}

		// Hang about? Are you pulling my leg - a callback?!
		if (is_array($config_var) && $config_var['type'] == 'callback')
		{
			if (function_exists('template_callback_' . $config_var['name']))
				call_user_func('template_callback_' . $config_var['name']);

			continue;
		}

		if (is_array($config_var))
		{
			// First off, is this a span like a message?
			if (in_array($config_var['type'], array('message', 'warning')))
			{
				echo '
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
				$javascript = $config_var['javascript'];
				$disabled = !empty($config_var['disabled']) ? ' disabled="disabled"' : '';
				$subtext = !empty($config_var['subtext']) ? '<br /><span class="smalltext"> ' . $config_var['subtext'] . '</span>' : '';

				// Show the [?] button.
				if ($config_var['help'])
					echo '
							<a id="setting_', $config_var['name'], '" href="', $scripturl, '?action=quickhelp;help=', $config_var['help'], '" onclick="return reqOverlayDiv(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.png" class="icon" alt="', $txt['help'], '" /></a><span', ($config_var['disabled'] ? ' style="color: #777777;"' : ($config_var['invalid'] ? ' class="error"' : '')), '><label for="', $config_var['name'], '">', $config_var['label'], '</label>', $subtext, ($config_var['type'] == 'password' ? '<br /><em>' . $txt['admin_confirm_password'] . '</em>' : ''), '</span>
						</dt>';
				else
					echo '
							<a id="setting_', $config_var['name'], '"></a> <span', ($config_var['disabled'] ? ' style="color: #777777;"' : ($config_var['invalid'] ? ' class="error"' : '')), '><label for="', $config_var['name'], '">', $config_var['label'], '</label>', $subtext, ($config_var['type'] == 'password' ? '<br /><em>' . $txt['admin_confirm_password'] . '</em>' : ''), '</span>
						</dt>';

				echo '
						<dd', (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : ''), '>',
							$config_var['preinput'];

				// Show a check box.
				if ($config_var['type'] == 'check')
					echo '
							<input type="checkbox"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '"', ($config_var['value'] ? ' checked="checked"' : ''), ' value="1" class="input_check" />';
				// Escape (via htmlspecialchars.) the text box.
				elseif ($config_var['type'] == 'password')
					echo '
							<input type="password"', $disabled, $javascript, ' name="', $config_var['name'], '[0]"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' value="*#fakepass#*" onfocus="this.value = \'\'; this.form.', $config_var['name'], '.disabled = false;" class="input_password" /><br />
							<input type="password" disabled="disabled" id="', $config_var['name'], '" name="', $config_var['name'], '[1]"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' class="input_password" />';
				// Show a selection box.
				elseif ($config_var['type'] == 'select')
				{
					echo '
							<select name="', $config_var['name'], '" id="', $config_var['name'], '" ', $javascript, $disabled, (!empty($config_var['multiple']) ? ' multiple="multiple"' : ''), '>';
					foreach ($config_var['data'] as $option)
						echo '
								<option value="', $option[0], '"', (!empty($config_var['value']) && ($option[0] == $config_var['value'] || (!empty($config_var['multiple']) && in_array($option[0], $config_var['value']))) ? ' selected="selected"' : ''), '>', $option[1], '</option>';
					echo '
							</select>';
				}
				// Text area?
				elseif ($config_var['type'] == 'large_text')
					echo '
							<textarea rows="', ($config_var['size'] ? $config_var['size'] : 4), '" cols="30" ', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '">', $config_var['value'], '</textarea>';
				// Permission group?
				elseif ($config_var['type'] == 'permissions')
					theme_inline_permissions($config_var['name']);
				// BBC selection?
				elseif ($config_var['type'] == 'bbc')
				{
					echo '
							<fieldset id="', $config_var['name'], '">
								<legend>', $txt['bbcTagsToUse_select'], '</legend>
									<ul class="reset">';

					foreach ($context['bbc_columns'] as $bbcColumn)
					{
						foreach ($bbcColumn as $bbcTag)
							echo '
										<li class="list_bbc floatleft">
											<input type="checkbox" name="', $config_var['name'], '_enabledTags[]" id="tag_', $config_var['name'], '_', $bbcTag['tag'], '" value="', $bbcTag['tag'], '"', !in_array($bbcTag['tag'], $context['bbc_sections'][$config_var['name']]['disabled']) ? ' checked="checked"' : '', ' class="input_check" /> <label for="tag_', $config_var['name'], '_', $bbcTag['tag'], '">', $bbcTag['tag'], '</label>', $bbcTag['show_help'] ? ' (<a href="' . $scripturl . '?action=quickhelp;help=tag_' . $bbcTag['tag'] . '" onclick="return reqOverlayDiv(this.href);">?</a>)' : '', '
										</li>';
					}
					echo '			</ul>
								<input type="checkbox" id="bbc_', $config_var['name'], '_select_all" onclick="invertAll(this, this.form, \'', $config_var['name'], '_enabledTags\');"', $context['bbc_sections'][$config_var['name']]['all_selected'] ? ' checked="checked"' : '', ' class="input_check" /> <label for="bbc_', $config_var['name'], '_select_all"><em>', $txt['bbcTagsToUse_select_all'], '</em></label>
							</fieldset>';
				}
				// A simple message?
				elseif ($config_var['type'] == 'var_message')
					echo '
							<div', !empty($config_var['name']) ? ' id="' . $config_var['name'] . '"' : '', '>', $config_var['var_message'], '</div>';
				// Assume it must be a text box.
				else
					echo '
							<input type="text"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' class="input_text" />';

				echo ($config_var['invalid']) ? '
							<img class="icon" src="' . $settings['images_url'] . '/icons/field_invalid.png" />' : '';

				echo isset($config_var['postinput']) ? '
							' . $config_var['postinput'] : '',
						'</dd>';
			}
		}
		else
		{
			// Just show a separator.
			if ($config_var == '')
				echo '
					</dl>
					<hr class="hrcolor clear" />
					<dl class="settings">';
			else
				echo '
						<dd>
							<strong>' . $config_var . '</strong>
						</dd>';
		}
	}

	if ($is_open)
		echo '
					</dl>';

	if (empty($context['settings_save_dont_show']))
		echo '
					<hr class="hrcolor" />
					<input type="submit" value="', $txt['save'], '"', (!empty($context['save_disabled']) ? ' disabled="disabled"' : ''), (!empty($context['settings_save_onclick']) ? ' onclick="' . $context['settings_save_onclick'] . '"' : ''), ' class="button_submit" />';

	if ($is_open)
		echo '
				</div>
			</div>';

	// At least one token has to be used!
	if (isset($context['admin-ssc_token']))
		echo '
		<input type="hidden" name="', $context['admin-ssc_token_var'], '" value="', $context['admin-ssc_token'], '" />';

	if (isset($context['admin-dbsc_token']))
		echo '
		<input type="hidden" name="', $context['admin-dbsc_token_var'], '" value="', $context['admin-dbsc_token'], '" />';

	if (isset($context['admin-mp_token']))
		echo '
		<input type="hidden" name="', $context['admin-mp_token_var'], '" value="', $context['admin-mp_token'], '" />';

	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>';

	if (!empty($context['settings_post_javascript']))
		echo '
	<script type="text/javascript"><!-- // --><![CDATA[
	', $context['settings_post_javascript'], '
	// ]]></script>';

	if (!empty($context['settings_insert_below']))
		echo $context['settings_insert_below'];
}

/**
 * Results page for an admin search.
 */
function template_admin_search_results()
{
	global $context, $txt, $settings, $options, $scripturl;

	echo '
		<div id="section_header" class="cat_bar">
			<h3 class="catbg">
				<object id="quick_search">
					<form action="', $scripturl, '?action=admin;area=search" method="post" accept-charset="UTF-8" class="floatright">
						<input type="text" name="search_term" value="', $context['search_term'], '" class="input_text" />
						<input type="hidden" name="search_type" value="', $context['search_type'], '" />
						<input type="submit" name="search_go" value="', $txt['admin_search_results_again'], '" class="button_submit" />
					</form>
				</object>
				<img class="icon" src="' . $settings['images_url'] . '/buttons/search.png" alt="" />&nbsp;', sprintf($txt['admin_search_results_desc'], $context['search_term']), '
			</h3>
		</div>
	<div class="windowbg nopadding">
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
				<li class="windowbg">
					<a href="', $result['url'], '"><strong>', $result['name'], '</strong></a> [', isset($txt['admin_search_section_' . $result['type']]) ? $txt['admin_search_section_' . $result['type']] : $result['type'] , ']';

				if ($result['help'])
					echo '
					<p class="double_height">', $result['help'], '</p>';

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
	global $txt, $context, $settings;

	echo '
			<dt>
				<strong>', $txt['setup_verification_question'], '</strong>
			</dt>
			<dd>
				<strong>', $txt['setup_verification_answer'], '</strong>
			</dd>';

	foreach ($context['question_answers'] as $data)
		echo '

			<dt>
				<input type="text" name="question[', $data['id'], ']" value="', $data['question'], '" size="50" class="input_text verification_question" />
			</dt>
			<dd>
				<input type="text" name="answer[', $data['id'], ']" value="', $data['answer'], '" size="50" class="input_text verification_answer" />
			</dd>';

	// Some blank ones.
	for ($count = 0; $count < 3; $count++)
		echo '
			<dt>
				<input type="text" name="question[]" size="50" class="input_text verification_question" />
			</dt>
			<dd>
				<input type="text" name="answer[]" size="50" class="input_text verification_answer" />
			</dd>';

	echo '
		<dt id="add_more_question_placeholder" style="display: none;"></dt><dd></dd>
		<dt id="add_more_link_div" style="display: none;">
			<a href="#" onclick="addAnotherQuestion(); return false;">&#171; ', $txt['setup_verification_add_more'], ' &#187;</a>
		</dt><dd></dd>';

	// The javascript needs to go at the end but we'll put it in this template for looks.
	$context['settings_post_javascript'] .= '
		var placeHolder = document.getElementById(\'add_more_question_placeholder\');
		document.getElementById(\'add_more_link_div\').style.display = \'\';
	';
}

/**
 * Repairing boards.
 */
function template_repair_boards()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<div id="section_header" class="cat_bar">
			<h3 class="catbg">',
				$context['error_search'] ? $txt['errors_list'] : $txt['errors_fixing'] , '
			</h3>
		</div>
		<div class="windowbg">
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
				echo '
					<li>
						', $error, '
					</li>';

			echo '
				</ul>
				<p>
					', $txt['errors_fix'], '
				</p>
				<p class="padding">
					<strong><a href="', $scripturl, '?action=admin;area=repairboards;fixErrors;', $context['session_var'], '=', $context['session_id'], '">', $txt['yes'], '</a> - <a href="', $scripturl, '?action=admin;area=maintain">', $txt['no'], '</a></strong>
				</p>';
		}
		else
			echo '
				<p>', $txt['maintain_no_errors'], '</p>
				<p class="padding">
					<a href="', $scripturl, '?action=admin;area=maintain;sa=routine">', $txt['maintain_return'], '</a>
				</p>';

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
				<p>', $txt['errors_fixed'], '</p>
				<p class="padding">
					<a href="', $scripturl, '?action=admin;area=maintain;sa=routine">', $txt['maintain_return'], '</a>
				</p>';
		}
	}

	echo '
			</div>
		</div>
	</div>';

	if (!empty($context['redirect_to_recount']))
	{
		echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		var countdown = 5;
		var txt_message = "', $txt['errors_recount_now'], '";
		var formName = "recount_form";
		doAutoSubmit();
	// ]]></script>';
	}
}

/**
 * Retrieves info from the php_info function, scrubs and preps it for display
 */
function template_php_info()
{
	global $context, $txt;

	// for each php info area
	foreach ($context['pinfo'] as $area => $php_area)
	{
		echo '
	<table id="', str_replace(' ', '_', $area), '" width="100%" class="table_grid">
		<thead>
		<tr class="catbg" align="center">
			<th class="first_th" scope="col" width="33%"></th>
			<th scope="col" width="33%" class="centertext"><strong>', $area, '</strong></th>
			<th class="last_th" scope="col" width="33%"></th>
		</tr>
		</thead>
		<tbody>';

		$alternate = true;
		$localmaster = true;

		// and for each setting in this category
		foreach ($php_area as $key => $setting)
		{
			// start of a local / master setting (3 col)
			if (is_array($setting))
			{
				if ($localmaster)
				{
					// heading row for the settings section of this categorys settings
					echo '
		<tr class="titlebg">
			<td align="center" width="33%"><strong>', $txt['phpinfo_itemsettings'], '</strong></td>
			<td align="center" width="33%"><strong>', $txt['phpinfo_localsettings'], '</strong></td>
			<td align="center" width="33%"><strong>', $txt['phpinfo_defaultsettings'], '</strong></td>
		</tr>';
					$localmaster = false;
				}

				echo '
		<tr>
			<td align="left" width="33%" class="windowbg', $alternate ? '2' : '', '">', $key, '</td>';

				foreach ($setting as $key_lm => $value)
				{
					echo '
			<td align="left" width="33%" class="windowbg', $alternate ? '2' : '', '">', $value, '</td>';
				}
				echo '
		</tr>';
			}
			// just a single setting (2 col)
			else
			{
				echo '
		<tr>
			<td align="left" width="33%" class="windowbg', $alternate ? '2' : '', '">', $key,  '</td>
			<td align="left" class="windowbg', $alternate ? '2' : '', '" colspan="2">', $setting, '</td>
		</tr>';
			}

			$alternate = !$alternate;
		}
		echo '
		</tbody>
	</table>
	<br class="clear" />';
	}
}

function template_clean_cache_button_above()
{
}

function template_clean_cache_button_below()
{
	global $txt, $scripturl, $context;

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['maintain_cache'], '</h3>
	</div>
	<div class="windowbg">
		<div class="content">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=cleancache" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_cache_info'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
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
	global $context, $settings, $txt, $scripturl;
	if ($context['user']['is_admin'])
		echo '
			<object id="quick_search">
				<form action="', $scripturl, '?action=admin;area=search" method="post" accept-charset="', $context['character_set'], '" class="floatright">
					<img class="icon" src="', $settings['images_url'] , '/filter.png" alt="" />
					<input type="text" name="search_term" value="', $txt['admin_search'], '" onclick="if (this.value == \'', $txt['admin_search'], '\') this.value = \'\';" class="input_text" />
					<select name="search_type">
						<option value="internal"', (empty($context['admin_preferences']['sb']) || $context['admin_preferences']['sb'] == 'internal' ? ' selected="selected"' : ''), '>', $txt['admin_search_type_internal'], '</option>
						<option value="member"', (!empty($context['admin_preferences']['sb']) && $context['admin_preferences']['sb'] == 'member' ? ' selected="selected"' : ''), '>', $txt['admin_search_type_member'], '</option>
						<option value="online"', (!empty($context['admin_preferences']['sb']) && $context['admin_preferences']['sb'] == 'online' ? ' selected="selected"' : ''), '>', $txt['admin_search_type_online'], '</option>
					</select>
					<input type="submit" name="search_go" id="search_go" value="', $txt['admin_search_go'], '" class="button_submit" />
				</form>
			</object>';
}