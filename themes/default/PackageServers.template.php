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
 * @version 1.0.7
 *
 */

/**
 * Template used to manage package servers
 */
function template_servers()
{
	global $context, $txt, $scripturl;

	if (!empty($context['package_ftp']['error']))
		echo '
					<div class="errorbox">
						<span class="tt">', $context['package_ftp']['error'], '</span>
					</div>';

	echo '
	<div id="admin_form_wrapper">
		<h2 class="category_header">', $txt['package_servers'], '</h2>';

	if ($context['package_download_broken'])
		template_ftp_form_required();

	echo '
		<div class="content">
			<fieldset>
				<legend>' . $txt['package_servers'] . '</legend>
				<ul class="package_servers">';

	foreach ($context['servers'] as $server)
		echo '
					<li class="flow_auto">
						<strong>' . $server['name'] . '</strong>
						<span class="package_server floatright">
							<a class="linkbutton" href="' . $scripturl . '?action=admin;area=packageservers;sa=browse;server=' . $server['id'] . '">' . $txt['package_browse'] . '</a>
							&nbsp;<a class="linkbutton" href="' . $scripturl . '?action=admin;area=packageservers;sa=remove;server=' . $server['id'] . ';', $context['session_var'], '=', $context['session_id'], '">' . $txt['delete'] . '</a>
						</span>
					</li>';

	echo '
				</ul>
			</fieldset>
			<fieldset>
				<legend>' . $txt['add_server'] . '</legend>
				<form action="' . $scripturl . '?action=admin;area=packageservers;sa=add" method="post" accept-charset="UTF-8">
					<dl class="settings">
						<dt>
							<strong><label for="servername">' . $txt['server_name'] . '</label>:</strong>
						</dt>
						<dd>
							<input type="text" id="servername" name="servername" size="44" value="ElkArte" class="input_text" />
						</dd>
						<dt>
							<strong><label for="serverurl">' . $txt['serverurl'] . '</label>:</strong>
						</dt>
						<dd>
							<input type="text" id="serverurl" name="serverurl" size="44" value="http://" class="input_text" />
						</dd>
					</dl>
					<div class="submitbutton">
						<input type="submit" value="' . $txt['add_server'] . '" class="button_submit" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
					</div>
				</form>
			</fieldset>
			<fieldset>
				<legend>', $txt['package_download_by_url'], '</legend>
				<form action="', $scripturl, '?action=admin;area=packageservers;sa=download" method="post" accept-charset="UTF-8">
					<dl class="settings">
						<dt>
							<strong><label for="package">' . $txt['serverurl'] . '</label>:</strong>
						</dt>
						<dd>
							<input type="text" id="package" name="package" size="44" value="http://" class="input_text" />
						</dd>
						<dt>
							<strong><label for="filename">', $txt['package_download_filename'], '</label>:</strong>
						</dt>
						<dd>
							<input type="text" id="filename" name="filename" size="44" class="input_text" /><br />
							<span class="smalltext">', $txt['package_download_filename_info'], '</span>
						</dd>
					</dl>
					<div class="submitbutton">
						<input type="submit" value="', $txt['download'], '" class="button_submit" />
						<input type="hidden" value="byurl" name="byurl" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
					</div>
				</form>
			</fieldset>
		</div>
	</div>';
}

/**
 * Show a confirmation dialog
 */
function template_package_confirm()
{
	global $context, $txt;

	echo '
	<div id="admincenter">
		<h2 class="category_header">', $context['page_title'], '</h2>
		<div class="windowbg">
			<div class="content">
				<p>', $context['confirm_message'], '</p>
				<a href="', $context['proceed_href'], '">[ ', $txt['package_confirm_proceed'], ' ]</a>
				<a href="JavaScript:window.location.assign(document.referrer);">[ ', $txt['package_confirm_go_back'], ' ]</a>
			</div>
		</div>
	</div>';
}

/**
 * Shows all of the addon packs available on the package server
 */
function template_package_list()
{
	global $context, $settings, $txt;

	echo '
	<div id="admincenter">
		<h2 class="category_header">' . $context['page_title'] . '</h2>
			<div class="content">';

	// No packages, as yet.
	if (empty($context['package_list']))
		echo '
				<ul>
					<li>', $txt['no_packages'], '</li>
				</ul>';
	// List out the packages...
	else
	{
		echo '
				<ul id="package_list">';

		foreach ($context['package_list'] as $i => $packageSection)
		{
			echo '
					<li>
						<span class="package_toggle">&nbsp;
							<span id="ps_img_', $i, '" class="collapse" style="display: none;" title="', $txt['hide'], '"></span>
						</span>
						<a href="#" id="upshrink_link_', $i, '" class="highlight">', $packageSection['title'], '</a>';

			if (!empty($packageSection['text']))
				echo '
						<div class="content">', $packageSection['text'], '</div>';

			// List of addons available in this section
			echo '
						<ol id="package_section_', $i, '" class="packages">';

			$alt = false;

			foreach ($packageSection['items'] as $id => $package)
			{
				// 1. Some addon [ Download ].
				echo '
						<li>
							<img id="ps_img_', $i, '_pkg_', $id, '" src="', $settings['images_url'], '/selected_open.png" alt="*" style="display: none;" /> ';

				// Installed but newer one is available
				if ($package['is_installed'] && $package['is_newer'])
				{
					echo '
							<span class="package_id">', $package['name'], '</span>&nbsp;<a class="linkbutton" href="', $package['download']['href'], '">', $txt['download'], '</a>&nbsp;',
					sprintf($txt['package_update'], '<i class="fa fa-exclamation-circle" title="' . $txt['package_installed_old'] . '"></i>', $txt['package_installed']);
				}
				// Installed but nothing newer is available
				else if ($package['is_installed'])
				{
					echo '
							<span class="package_id">', $package['name'], '</span>&nbsp;',
					sprintf($txt['package_current'], '<i class="fa fa-check" title="' . $txt['package_installed_current'] . '"></i>', $txt['package_installed']);
				}
				// Downloaded, but there is a more recent version available
				else if ($package['is_downloaded'] && $package['is_newer'])
				{
					echo '
							<span class="package_id">', $package['name'], '</span>&nbsp;<a class="linkbutton" href="', $package['download']['href'], '">', $txt['download'], '</a>&nbsp;',
					sprintf($txt['package_update'], '<i class="fa fa-minus-circle" title="' . $txt['package_installed_old'] . '"></i>', $txt['package_downloaded']);
				}
				// Downloaded, and its current
				else if ($package['is_downloaded'])
				{
					echo '
							<span class="package_id">', $package['name'], '</span>&nbsp;',
					sprintf($txt['package_current'], '<i class="fa fa-plus-circle" title="' . $txt['package_installed_current'] . '"></i>', $txt['package_downloaded']);
				}
				// Not downloaded or installed
				else
				{
					echo '
							<span class="package_id">', $package['name'], '</span>&nbsp;<a class="linkbutton" href="', $package['download']['href'], '">', $txt['download'], '</a>';
				}

				echo '
							<ul id="package_section_', $i, '_pkg_', $id, '" class="package_section">';

				// Show the addon type?
				if ($package['type'] != '')
					echo '
								<li class="package_section">', $txt['package_type'], ':&nbsp; ', Util::ucwords(Util::strtolower($package['type'])), '</li>';

				// Show the version number?
				if ($package['version'] != '')
					echo '
								<li class="package_section">', $txt['mod_version'], ':&nbsp; ', $package['version'], '</li>';

				// Show the last date?
				if ($package['date'] != '')
					echo '
								<li class="package_section">', $txt['mod_date'], ':&nbsp; ', $package['date'], '</li>';

				// How 'bout the author?
				if (!empty($package['author']))
					echo '
								<li class="package_section">', $txt['mod_author'], ':&nbsp; ', $package['author'], '</li>';

				// Nothing but hooks ?
				if ($package['hooks'] != '' && in_array($package['hooks'] , array('yes', 'true')))
					echo '
								<li class="package_section">', $txt['mod_hooks'], ' <i class="fa fa-check-circle-o"></i></li>';

				// Location of file: http://someplace/.
				echo '
								<ul style="margin-left: 5em">
									<li class="package_section"><i class="fa fa-cloud-download"></i> ', $txt['file_location'], ':&nbsp; <a href="', $package['server']['download'], '">', $package['server']['download'], '</a></li>';

				// Location of issues?
				if (!empty($package['server']['bugs']))
					echo '
									<li class="package_section"><i class="fa fa-bug"></i> ', $txt['bug_location'], ':&nbsp; <a href="', $package['server']['bugs'], '">', $package['server']['bugs'], '</a></li>';

				// Location of support?
				if (!empty($package['server']['support']))
					echo '
									<li class="package_section"><i class="fa fa-support"></i> ', $txt['support_location'], ':&nbsp; <a href="', $package['server']['support'], '">', $package['server']['support'], '</a></li>';

				// Description: bleh bleh!
				echo '
								</ul>
								<li class="package_section"><div class="infobox">', $txt['package_description'], ':&nbsp; ', $package['description'], '</div></li>
							</ul>';

				$alt = !$alt;
				echo '
						</li>';
			}

			echo '
					</ol>
						</li>';
		}

		echo '
				</ul>';
	}

	echo '
		</div>
		<div>
			', $txt['package_installed_key'], '
			<img src="', $settings['images_url'], '/icons/package_installed.png" alt="" class="centericon" style="margin-left: 1ex;" /> ', $txt['package_installed_current'], '
			<img src="', $settings['images_url'], '/icons/package_old.png" alt="" class="centericon" style="margin-left: 2ex;" /> ', $txt['package_installed_old'], '
		</div>
	</div>';

	// Now go through and turn off / collapse all the sections.
	if (!empty($context['package_list']))
	{
		echo '
			<script><!-- // --><![CDATA[';
		foreach ($context['package_list'] as $section => $ps)
		{
			echo '
				var oPackageServerToggle_', $section, ' = new elk_Toggle({
					bToggleEnabled: true,
					bCurrentlyCollapsed: true,
					aSwappableContainers: [
						\'package_section_', $section, '\'
					],
					aSwapClasses: [
						{
							sId: \'ps_img_', $section, '\',
							classExpanded: \'collapse\',
							titleExpanded: ', JavaScriptEscape($txt['hide']), ',
							classCollapsed: \'expand\',
							titleCollapsed: ', JavaScriptEscape($txt['show']), '
						}
					],
					aSwapLinks: [
						{
							sId: \'upshrink_link_', $section, '\',
							msgExpanded: ', JavaScriptEscape($ps['title']), ',
							msgCollapsed: ', JavaScriptEscape($ps['title']), '
						}
					]
				});';

			foreach ($ps['items'] as $id => $package)
			{
				echo '
				var oPackageToggle_', $section, '_pkg_', $id, ' = new elk_Toggle({
					bToggleEnabled: true,
					bCurrentlyCollapsed: true,
					aSwappableContainers: [
						\'package_section_', $section, '_pkg_', $id, '\'
					],
					aSwapImages: [
						{
							sId: \'ps_img_', $section, '_pkg_', $id, '\',
							srcExpanded: elk_images_url + \'/selected.png\',
							altExpanded: \'*\',
							srcCollapsed: elk_images_url + \'/selected_open.png\',
							altCollapsed: \'*\'
						}
					]
				});';
			}
		}

		echo '
			// ]]></script>';
	}
}

/**
 * Displays a success message for the download or upload of a package.
 */
function template_downloaded()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admin_form_wrapper">
		<h2 class="category_header">', $context['page_title'], '</h2>
		<p class="infobox">', (empty($context['package_server']) ? $txt['package_uploaded_successfully'] : $txt['package_downloaded_successfully']), '</p>
		<div class="windowbg">
			<div class="content flow_auto">
				<ul>
					<li>
						<span class="floatleft"><strong>', $context['package']['name'], '</strong></span>
						<span class="package_server floatright">', $context['package']['list_files']['link'], '</span>
						<span class="package_server floatright">', $context['package']['install']['link'], '</span>
					</li>
				</ul>
			</div>
			<div class="submitbutton">
				<hr />
				<a class="linkbutton" href="', $scripturl, '?action=admin;', (!empty($context['package_server']) ? 'area=packageservers;sa=browse;server=' . $context['package_server'] : 'area=packages;sa=browse'), '">', $txt['back'], '</a>
			</div>
		</div>
	</div>';
}

/**
 * Shows a form to upload a package from the local computer.
 */
function template_upload()
{
	global $context, $txt, $scripturl;

	if (!empty($context['package_ftp']['error']))
		echo '
	<div class="errorbox">
		', $context['package_ftp']['error'], '
	</div>';

	echo '
	<div id="admin_form_wrapper">
		<h2 class="category_header">', $txt['upload_new_package'], '</h2>';

	if ($context['package_download_broken'])
	{
		template_ftp_form_required();

		echo '
			<h2 class="category_header">' . $txt['package_upload_title'] . '</h2>';
	}

	echo '
		<div class="windowbg">
			<div class="content">
				<form action="' . $scripturl . '?action=admin;area=packageservers;sa=upload2" method="post" accept-charset="UTF-8" enctype="multipart/form-data" style="margin-bottom: 0;">
					<dl class="settings">
						<dt>
							<strong><label for="package">' . $txt['package_upload_select'] . '</label>:</strong>
						</dt>
						<dd>
							<input type="file" id="package" name="package" size="38" class="input_file" />
						</dd>
					</dl>
					<hr />
					<div class="submitbutton">
						<input type="submit" value="' . $txt['package_upload'] . '" class="button_submit" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
					</div>
				</form>
			</div>
		</div>
	</div>';
}

/**
 * Section of package servers tabs
 * It displays a form to connect to admin's FTP account.
 */
function template_ftp_form_required()
{
	global $context, $txt, $scripturl;

	echo '
		<h3 class="category_header">', $txt['package_ftp_necessary'], '</h3>
		<div class="windowbg">
			<div class="content">
				<p>
					', $txt['package_ftp_why_download'], '
				</p>
				<form action="', $scripturl, '?action=admin;area=packageservers" method="post" accept-charset="UTF-8">
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
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="', $context['package_ftp']['username'], '" style="width: 99%;" class="input_text" />
						</dd>
						<dt>
							<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
						</dt>
						<dd>
							<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 99%;" class="input_password" />
						</dd>
						<dt>
							<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '" style="width: 99%;" class="input_text" />
						</dd>
					</dl>
					<div class="submitbutton">
						<input type="submit" value="', $txt['package_proceed'], '" class="button_submit" />
					</div>
				</form>
			</div>
		</div>';
}