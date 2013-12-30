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
		template_ftp_required();

	echo '
		<div class="windowbg2">
			<div class="content">
				<fieldset>
					<legend>' . $txt['package_servers'] . '</legend>
					<ul class="package_servers">';

	foreach ($context['servers'] as $server)
		echo '
						<li class="flow_auto">
							<span class="floatleft">' . $server['name'] . '</span>
							<span class="package_server floatright"><a href="' . $scripturl . '?action=admin;area=packageservers;sa=remove;server=' . $server['id'] . ';', $context['session_var'], '=', $context['session_id'], '">[ ' . $txt['delete'] . ' ]</a></span>
							<span class="package_server floatright"><a href="' . $scripturl . '?action=admin;area=packageservers;sa=browse;server=' . $server['id'] . '">[ ' . $txt['package_browse'] . ' ]</a></span>
						</li>';

	echo '
					</ul>
				</fieldset>
				<fieldset>
					<legend>' . $txt['add_server'] . '</legend>
					<form action="' . $scripturl . '?action=admin;area=packageservers;sa=add" method="post" accept-charset="UTF-8">
						<dl class="settings">
							<dt>
								<strong>' . $txt['server_name'] . ':</strong>
							</dt>
							<dd>
								<input type="text" name="servername" size="44" value="ElkArte" class="input_text" />
							</dd>
							<dt>
								<strong>' . $txt['serverurl'] . ':</strong>
							</dt>
							<dd>
								<input type="text" name="serverurl" size="44" value="http://" class="input_text" />
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
					<form action="', $scripturl, '?action=admin;area=packageservers;sa=download;byurl;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
						<dl class="settings">
							<dt>
								<strong>' . $txt['serverurl'] . ':</strong>
							</dt>
							<dd>
								<input type="text" name="package" size="44" value="http://" class="input_text" />
							</dd>
							<dt>
								<strong>', $txt['package_download_filename'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="filename" size="44" class="input_text" /><br />
								<span class="smalltext">', $txt['package_download_filename_info'], '</span>
							</dd>
						</dl>
						<div class="submitbutton">
							<input type="submit" value="', $txt['download'], '" class="button_submit" />
						</div>
					</form>
				</fieldset>
			</div>
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
				<a href="JavaScript:history.go(-1);">[ ', $txt['package_confirm_go_back'], ' ]</a>
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
		<div class="windowbg">
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
						<img id="ps_img_', $i, '" src="', $settings['images_url'], '/collapse.png" class="floatright" alt="*" style="display: none;" /><strong>', $packageSection['title'], '</strong>';

			if (!empty($packageSection['text']))
				echo '
						<div class="information">', $packageSection['text'], '</div>';

			echo '
						<', $context['list_type'], ' id="package_section_', $i, '" class="packages">';

			$alt = false;

			foreach ($packageSection['items'] as $id => $package)
			{
				echo '
							<li>';
				// Textual message. Could be empty just for a blank line...
				if ($package['is_text'])
					echo '
								', empty($package['name']) ? '&nbsp;' : $package['name'];
				// This is supposed to be a rule..
				elseif ($package['is_line'])
					echo '
							<hr />';
				// A remote link.
				elseif ($package['is_remote'])
					echo '
							<strong>', $package['link'], '</strong>';
				// A title?
				elseif ($package['is_heading'] || $package['is_title'])
					echo '
							<strong>', $package['name'], '</strong>';
				// Otherwise, it's a package.
				else
				{
					// 1. Some addon [ Download ].
					echo '
							<strong><img id="ps_img_', $i, '_pkg_', $id, '" src="', $settings['images_url'], '/selected_open.png" alt="*" style="display: none;" /> ', $package['can_install'] ? '<strong>' . $package['name'] . '</strong> <a href="' . $package['download']['href'] . '">[ ' . $txt['download'] . ' ]</a>' : $package['name'];

					// Mark as installed and current?
					if ($package['is_installed'] && !$package['is_newer'])
						echo '<img src="', $settings['images_url'], '/icons/package_', $package['is_current'] ? 'installed' : 'old', '.png" class="centericon" style="width: 12px; height: 11px; margin-left: 2ex;" alt="', $package['is_current'] ? $txt['package_installed_current'] : $txt['package_installed_old'], '" />';

					echo '
							</strong>
							<ul id="package_section_', $i, '_pkg_', $id, '" class="package_section">';

					// Show the addon type?
					if ($package['type'] != '')
						echo '
								<li class="package_section">', $txt['package_type'], ':&nbsp; ', Util::ucwords(Util::strtolower($package['type'])), '</li>';
					// Show the version number?
					if ($package['version'] != '')
						echo '
								<li class="package_section">', $txt['mod_version'], ':&nbsp; ', $package['version'], '</li>';
					// How 'bout the author?
					if (!empty($package['author']) && $package['author']['name'] != '' && isset($package['author']['link']))
						echo '
								<li class="package_section">', $txt['mod_author'], ':&nbsp; ', $package['author']['link'], '</li>';
					// The homepage....
					if ($package['author']['website']['link'] != '')
						echo '
								<li class="package_section">', $txt['author_website'], ':&nbsp; ', $package['author']['website']['link'], '</li>';

					// Description: bleh bleh!
					// Location of file: http://someplace/.
					echo '
								<li class="package_section">', $txt['file_location'], ':&nbsp; <a href="', $package['href'], '">', $package['href'], '</a></li>
								<li class="package_section"><div class="information">', $txt['package_description'], ':&nbsp; ', $package['description'], '</div></li>
							</ul>';
				}

				$alt = !$alt;
				echo '
						</li>';
			}

			echo '
					</', $context['list_type'], '>
						</li>';
		}

		echo '
				</ul>';
	}

	echo '
			</div>
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
		$section_count = count($context['package_list']);
		echo '
			<script><!-- // --><![CDATA[';
		foreach ($context['package_list'] as $section => $ps)
		{
			echo '
				var oPackageServerToggle_', $section, ' = new elk_Toggle({
					bToggleEnabled: true,
					bCurrentlyCollapsed: ', count($ps['items']) == 1 || $section_count == 1 ? 'false' : 'true', ',
					aSwappableContainers: [
						\'package_section_', $section, '\'
					],
					aSwapImages: [
						{
							sId: \'ps_img_', $section, '\',
							srcExpanded: elk_images_url + \'/collapse.png\',
							altExpanded: \'*\',
							srcCollapsed: elk_images_url + \'/expand.png\',
							altCollapsed: \'*\'
						}
					]
				});';

			foreach ($ps['items'] as $id => $package)
			{
				if (!$package['is_text'] && !$package['is_line'] && !$package['is_remote'])
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
	<div id="admincenter">
		<h2 class="category_header">', $context['page_title'], '</h2>
		<div class="windowbg">
			<div class="content">
				<p>', (empty($context['package_server']) ? $txt['package_uploaded_successfully'] : $txt['package_downloaded_successfully']), '</p>
				<ul>
					<li><span class="floatleft"><strong>', $context['package']['name'], '</strong></span>
						<span class="package_server floatright">', $context['package']['list_files']['link'], '</span>
						<span class="package_server floatright">', $context['package']['install']['link'], '</span>
					</li>
				</ul>
				<br /><br />
				<p><a href="', $scripturl, '?action=admin;area=packageservers', (isset($context['package_server']) ? ';sa=browse;server=' . $context['package_server'] : ''), '">[ ', $txt['back'], ' ]</a></p>
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
						<span class="tt">', $context['package_ftp']['error'], '</span>
					</div>';

	echo '
	<div id="admin_form_wrapper">
		<h3 class="category_header">', $txt['upload_new_package'], '</h3>';

	if ($context['package_download_broken'])
	{
		template_ftp_required();

		echo '
			<h3 class="category_header">' . $txt['package_upload_title'] . '</h3>';
	}

	echo '
		<div class="windowbg">
			<div class="content">
				<form action="' . $scripturl . '?action=admin;area=packageservers;sa=upload2" method="post" accept-charset="UTF-8" enctype="multipart/form-data" style="margin-bottom: 0;">
					<dl class="settings">
						<dt>
							<strong>' . $txt['package_upload_select'] . ':</strong>
						</dt>
						<dd>
							<input type="file" name="package" size="38" class="input_file" />
						</dd>
					</dl>
					<hr />
					<input type="submit" value="' . $txt['package_upload'] . '" class="right_submit" />
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
				</form>
			</div>
		</div>
	</div>';
}

/**
 * Section of package servers tabs
 * It displays a form to connect to admin's FTP account.
 */
function template_ftp_required()
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