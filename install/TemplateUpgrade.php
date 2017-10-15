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
 * @version 1.1
 *
 */

/**
 * This is what is displayed if there's any chmod to be done. If not it returns nothing...
 */
function template_chmod()
{
	global $upcontext, $settings, $txt;

	// Don't call me twice!
	if (!empty($upcontext['chmod_called']))
		return;

	$upcontext['chmod_called'] = true;

	// Nothing?
	if (empty($upcontext['chmod']['files']) && empty($upcontext['chmod']['ftp_error']))
		return;

	// @todo Temporary!
	$txt['error_ftp_no_connect'] = 'Unable to connect to FTP server with this combination of details.';
	$txt['ftp_login'] = 'Your FTP connection information';
	$txt['ftp_login_info'] = 'This web installer needs your FTP information in order to automate the installation for you.  Please note that none of this information is saved in your installation, it is just used to setup ElkArte.';
	$txt['ftp_server'] = 'Server';
	$txt['ftp_server_info'] = 'The address (often localhost) and port for your FTP server.';
	$txt['ftp_port'] = 'Port';
	$txt['ftp_username'] = 'Username';
	$txt['ftp_username_info'] = 'The username to login with. <em>This will not be saved anywhere.</em>';
	$txt['ftp_password'] = 'Password';
	$txt['ftp_password_info'] = 'The password to login with. <em>This will not be saved anywhere.</em>';
	$txt['ftp_path'] = 'Install Path';
	$txt['ftp_path_info'] = 'This is the <em>relative</em> path you use in your FTP client <a href="' . $_SERVER['PHP_SELF'] . '?ftphelp" onclick="window.open(this.href, \'\', \'width=450,height=250\');return false;" target="_blank">(more help)</a>.';
	$txt['ftp_path_found_info'] = 'The path in the box above was automatically detected.';
	$txt['ftp_path_help'] = 'Your FTP path is the path you see when you log in to your FTP client.  It commonly starts with &quot;<span style="font-family: monospace;">www</span>&quot;, &quot;<span style="font-family: monospace;">public_html</span>&quot;, or &quot;<span style="font-family: monospace;">httpdocs</span>&quot; - but it should include the directory ElkArte is in too, such as &quot;/public_html/forum&quot;.  It is different from your URL and full path.<br /><br />Files in this path may be overwritten, so make sure it\'s correct.';
	$txt['ftp_path_help_close'] = 'Close';
	$txt['ftp_connect'] = 'Connect';

	// Was it a problem with Windows?
	if (!empty($upcontext['chmod']['ftp_error']) && $upcontext['chmod']['ftp_error'] == 'total_mess')
	{
		echo '
			<div class="error_message">
				<div style="color: red;">The following files need to be writable to continue the upgrade. Please ensure the Windows permissions are correctly set to allow this:</div>
				<ul style="margin: 2.5ex; font-family: monospace;">
				<li>' . implode('</li>
				<li>', $upcontext['chmod']['files']). '</li>
			</ul>
			</div>';

		return false;
	}

	echo '
		<div class="panel">
			<h2>Your FTP connection information</h2>
			<h3>The upgrader can fix any issues with file permissions to make upgrading as simple as possible. Simply enter your connection information below or alternatively click <a href="#" onclick="warning_popup();">here</a> for a list of files which need to be changed.</h3>
			<script>
				function warning_popup()
				{
					var popup = window.open(\'\',\'popup\',\'height=150,width=400,scrollbars=yes\'),
						content = popup.document;

					content.write(\'<!DOCTYPE html>\n\');
					content.write(\'<html ', $upcontext['right_to_left'] ? 'dir="rtl"' : '', '>\n\t<head>\n\t\t<meta name="robots" content="noindex" />\n\t\t\');
					content.write(\'<title>Warning</title>\n\t\t<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/index.css" />\n\t</head>\n\t<body id="popup">\n\t\t\');
					content.write(\'<div class="windowbg description">\n\t\t\t<h4>The following files needs to be made writable to continue:</h4>\n\t\t\t\');
					content.write(\'<p>', implode('<br />\n\t\t\t', $upcontext['chmod']['files']), '</p>\n\t\t\t\');
					content.write(\'<a href="javascript:self.close();">close</a>\n\t\t</div>\n\t</body>\n</html>\');
					content.close();
				}
		</script>';

	if (!empty($upcontext['chmod']['ftp_error']))
		echo '
			<div class="error_message">
				<div style="color: red;">
					The following error was encountered when trying to connect:<br />
					<br />
					<code>', $upcontext['chmod']['ftp_error'], '</code>
				</div>
			</div>
			<br />';

	if (empty($upcontext['chmod_in_form']))
		echo '
	<form action="', $upcontext['form_url'], '" method="post">';

	echo '
		<table style="width: 520px; margin: 1em 0; border-collapse:collapse; border-spacing: 0; padding: 0; text-align:center;">
			<tr>
				<td style="width: 26%; vertical-align: top;" class="textbox"><label for="ftp_server">', $txt['ftp_server'], ':</label></td>
				<td>
					<div style="float: right; margin-right: 1px;"><label for="ftp_port" class="textbox"><strong>', $txt['ftp_port'], ':&nbsp;</strong></label> <input type="text" size="3" name="ftp_port" id="ftp_port" value="', isset($upcontext['chmod']['port']) ? $upcontext['chmod']['port'] : '21', '" class="input_text" /></div>
					<input type="text" size="30" name="ftp_server" id="ftp_server" value="', isset($upcontext['chmod']['server']) ? $upcontext['chmod']['server'] : 'localhost', '" style="width: 70%;" class="input_text" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['ftp_server_info'], '</div>
				</td>
			</tr><tr>
				<td style="width: 26%; vertical-align: top;" class="textbox"><label for="ftp_username">', $txt['ftp_username'], ':</label></td>
				<td>
					<input type="text" size="50" name="ftp_username" id="ftp_username" value="', isset($upcontext['chmod']['username']) ? $upcontext['chmod']['username'] : '', '" style="width: 99%;" class="input_text" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['ftp_username_info'], '</div>
				</td>
			</tr><tr>
				<td style="width: 26%; vertical-align: top;" class="textbox"><label for="ftp_password">', $txt['ftp_password'], ':</label></td>
				<td>
					<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 99%;" class="input_password" />
					<div style="font-size: smaller; margin-bottom: 3ex;">', $txt['ftp_password_info'], '</div>
				</td>
			</tr><tr>
				<td style="width: 26%; vertical-align: top;" class="textbox"><label for="ftp_path">', $txt['ftp_path'], ':</label></td>
				<td style="padding-bottom: 1ex;">
					<input type="text" size="50" name="ftp_path" id="ftp_path" value="', isset($upcontext['chmod']['path']) ? $upcontext['chmod']['path'] : '', '" style="width: 99%;" class="input_text" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', !empty($upcontext['chmod']['path']) ? $txt['ftp_path_found_info'] : $txt['ftp_path_info'], '</div>
				</td>
			</tr>
		</table>

		<div class="righttext" style="margin: 1ex;"><input type="submit" value="', $txt['ftp_connect'], '" class="button_submit" /></div>
	</div>';

	if (empty($upcontext['chmod_in_form']))
		echo '
	</form>';
}

/**
 *
 */
function template_upgrade_above()
{
	global $txt, $settings, $upcontext, $upgradeurl;

	echo '<!DOCTYPE html>
<html ', $upcontext['right_to_left'] ? 'dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta name="robots" content="noindex" />
		<title>', $txt['upgrade_upgrade_utility'], '</title>
		<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/index.css?11RC1" />
		<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/_light/index_light.css?11RC1" />
		<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/install.css?11RC1" />
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js" id="jquery"></script>
		<script>
			window.jQuery || document.write(\'<script src="', $settings['default_theme_url'], '/scripts/jquery-3.1.1.min.js"><\/script>\');
		</script>
		<script src="', $settings['default_theme_url'], '/scripts/script.js"></script>
		<script>
			var elk_scripturl = \'', $upgradeurl, '\',
				elk_charset = \'UTF-8\',
				startPercent = ', $upcontext['overall_percent'], ';

			// This function dynamically updates the step progress bar - and overall one as required.
			function updateStepProgress(current, max, overall_weight)
			{
				// What out the actual percent.
				var width = parseInt((current / max) * 100);

				if (document.getElementById(\'step__progress\'))
				{
					document.getElementById(\'step__progress\').style.width = width + "%";
					document.getElementById(\'step__text\').innerHTML = width + "%";
				}

				if (overall_weight && document.getElementById(\'overall__progress\'))
				{
					overall_width = parseInt(startPercent + width * (overall_weight / 100));
					document.getElementById(\'overall__progress\').style.width = overall_width + "%";
					document.getElementById(\'overall__text\').innerHTML = overall_width + "%";
				}
			}
		</script>
	</head>
	<body>
	<div id="header">
		<div class="frame">
			<h1 class="forumtitle">', $txt['upgrade_upgrade_utility'], '</h1>
			<img id="logo" src="', $settings['default_theme_url'], '/images/logo.png" alt="ElkArte Community" title="ElkArte Community" />
		</div>
	</div>
	<div id="wrapper" class="wrapper">
		<div id="upper_section">
			<div id="inner_section">
				<div id="inner_wrap">';

	if (!empty($incontext['detected_languages']) && count($incontext['detected_languages']) > 1 && $incontext['current_step'] == 0)
	{
		echo '
						<div class="news">
							<form action="', $upgradeurl, '" method="get">
								<label for="installer_language">', $txt['installer_language'], ':</label>
								<select id="installer_language" name="lang_file" onchange="location.href = \'', $upgradeurl, '?lang_file=\' + this.options[this.selectedIndex].value;">';

		foreach ($incontext['detected_languages'] as $lang => $name)
			echo '
									<option', isset($_SESSION['installer_temp_lang']) && $_SESSION['installer_temp_lang'] == $lang ? ' selected="selected"' : '', ' value="', $lang, '">', $name, '</option>';

		echo '
								</select>
								<noscript><input type="submit" value="', $txt['installer_language_set'], '" class="button_submit" /></noscript>
							</form>
						</div>';
	}

	echo '
					</div>
				</div>
			</div>
			<div id="content_section">
				<div id="main_content_section">
					<div id="main_steps">
						<h2>', $txt['upgrade_progress'], '</h2>
						<ul>';

	foreach ($upcontext['steps'] as $num => $step)
		echo '
							<li class="', $num < $upcontext['current_step'] ? 'stepdone' : ($num == $upcontext['current_step'] ? 'stepcurrent' : 'stepwaiting'), '">', $txt['upgrade_step'], ' ', $step[0], ': ', $step[1], '</li>';

	echo '
						</ul>
					</div>
					<div style="float: left; width: 40%;">
						<div class="progress_bar">
							<div id="overall__text" class="full_bar">', $upcontext['overall_percent'], '%</div>
							<div id="overall__progress" class="green_percent" style="width: ', $upcontext['overall_percent'], '%;">&nbsp;</div>
						</div>
				';

	if (isset($upcontext['step_progress']))
		echo '
						<div class="progress_bar">
							<div id="step__text" class="full_bar">', $upcontext['step_progress'], '%</div>
							<div id="step__progress" class="blue_percent" style="width: ', $upcontext['step_progress'], '%;">&nbsp;</div>
						</div>';

	echo '
						<div id="substep_bar_div" class="smalltext" style="display: ', isset($upcontext['substep_progress']) ? '' : 'none', ';">', isset($upcontext['substep_progress_name']) ? trim(strtr($upcontext['substep_progress_name'], array('.' => ''))) : '', ':</div>
						<div id="substep_bar_div2" class="progress_bar" style="display: ', isset($upcontext['substep_progress']) ? '' : 'none', ';">
							<div id="substep_text" class="full_bar">', isset($upcontext['substep_progress']) ? $upcontext['substep_progress'] : '', '%</div>
							<div id="substep_progress" class="blue_percent" style="width: ', isset($upcontext['substep_progress']) ? $upcontext['substep_progress'] : 0, '%; background-color: #eebaf4;">&nbsp;</div>
						</div>';

	// How long have we been running this?
	$elapsed = time() - $upcontext['started'];
	$mins = (int) ($elapsed / 60);
	$seconds = $elapsed - $mins * 60;

	if (!empty($elapsed))
		echo '
						<div class="smalltext" style="padding: 5px; text-align: center;">', $txt['upgrade_time_elapsed'], ':
							<span id="mins_elapsed">', $mins, '</span> ', $txt['upgrade_time_mins'], ', <span id="secs_elapsed">', $seconds, '</span> ', $txt['upgrade_time_secs'], '.
						</div>';
	echo '
					</div>
					<div id="main_screen" class="clear">
						<h2>', $upcontext['page_title'], '</h2>
						<div class="panel">';
}

/**
 *
 */
function template_upgrade_below()
{
	global $upcontext, $txt;

	if (!empty($upcontext['pause']))
		echo '
								<em>', $txt['upgrade_incomplete'], '.</em><br />

								<h2 style="margin-top: 2ex;">', $txt['upgrade_not_quite_done'], '</h2>
								<h3>
									', $txt['upgrade_paused_overload'], '
								</h3>';

	if (!empty($upcontext['custom_warning']))
		echo '
								<div class="warningbox">
									<strong style="text-decoration: underline;">', $txt['upgrade_note'], '</strong><br />
									<div>', $upcontext['custom_warning'], '</div>
								</div>';

	echo '
								<div class="righttext" style="margin: 1ex;">';

	if (!empty($upcontext['continue']))
		echo '
									<input type="submit" id="contbutt" name="contbutt" value="', $txt['upgrade_continue'], '"', $upcontext['continue'] == 2 ? ' disabled="disabled"' : '', ' class="button_submit" />';
	if (!empty($upcontext['skip']))
		echo '
									<input type="submit" id="skip" name="skip" value="', $txt['upgrade_skip'], '" onclick="dontSubmit = true; document.getElementById(\'contbutt\').disabled = \'disabled\'; return true;" class="button_submit" />';

	echo '
								</div>
							</form>
						</div>
				</div>
			</div>
		</div>
	</div></div>
	<div id="footer_section"><div class="frame" style="height: 40px;">
		<div class="smalltext"><a href="', SITE_SOFTWARE, '" title="ElkArte Community" target="_blank" class="new_win">ElkArte &copy; 2012 - 2017, ElkArte</a></div>
	</div></div>
	</body>
</html>';

	// Are we on a pause?
	if (!empty($upcontext['pause']))
	{
		echo '
		<script>
			var countdown = 3,
				dontSubmit = false;

			window.onload = doAutoSubmit;

			function doAutoSubmit()
			{
				if (countdown == 0 && !dontSubmit)
					document.upform.submit();
				else if (countdown == -1)
					return;

				document.getElementById(\'contbutt\').value = "', $txt['upgrade_continue'], ' (" + countdown + ")";
				countdown--;

				setTimeout("doAutoSubmit();", 1000);
			}
		</script>';
	}
}

/**
 *
 */
function template_xml_above()
{
	global $upcontext;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
	<elk>';

	if (!empty($upcontext['get_data']))
		foreach ($upcontext['get_data'] as $k => $v)
			echo '
		<get key="', $k, '">', $v, '</get>';
}

/**
 *
 */
function template_xml_below()
{
	echo '
		</elk>';
}

/**
 *
 */
function template_error_message()
{
	global $upcontext;

	echo '
	<div class="errorbox">
		', $upcontext['error_msg'], '
		<br />
		<a href="', $_SERVER['PHP_SELF'], '">Click here to try again.</a>
	</div>';
}

/**
 *
 */
function template_welcome_message()
{
	global $upcontext, $disable_security, $settings, $txt;

	echo '
		<script src="', $settings['default_theme_url'], '/scripts/sha256.js"></script>
		<script src="', $settings['default_theme_url'], '/scripts/admin.js"></script>
		<script>
			var oUpgradeCenter = new elk_AdminIndex({
				bLoadAnnouncements: false,

				bLoadVersions: true,
				slatestVersionContainerId: \'latestVersion\',
				sinstalledVersionContainerId: \'version_warning\',
				sVersionOutdatedTemplate: ', JavaScriptEscape('
				<strong style="text-decoration: underline;">' . $txt['upgrade_warning'] . '</strong><br />
				<div style="padding-left: 6ex;">
					' . sprintf($txt['upgrade_warning_out_of_date'], CURRENT_VERSION, '%currentVersion%') . '
				</div>
				'), ',

				bLoadUpdateNotification: false
			});
		</script>
		<h3>', sprintf($txt['upgrade_ready_proceed'], CURRENT_VERSION), '</h3>
		<form id="upform" action="', $upcontext['form_url'], '" method="post" accept-charset="UTF-8" name="upform"', empty($upcontext['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $upcontext['rid'] . '\');"' : '', '>
		<input type="hidden" name="', $upcontext['login_token_var'], '" value="', $upcontext['login_token'], '" />
		<div id="version_warning" class="errorbox" style="display: none;">',CURRENT_VERSION, '</div>
		<div id="latestVersion" style="display: none;">???</div>';

	$upcontext['chmod_in_form'] = true;
	template_chmod();

	// For large, SMF pre-1.1 RC2 forums give them a warning about the possible impact of this upgrade!
	if ($upcontext['is_large_forum'])
		echo '
		<div class="warningbox">
			<strong style="text-decoration: underline;">', $txt['upgrade_warning'], '</strong><br />
			<div>
				', $txt['upgrade_warning_lots_data'], '
			</div>
		</div>';

	// A warning message?
	if (!empty($upcontext['warning']))
		echo '
		<div class="warningbox">
			<strong style="text-decoration: underline;">', $txt['upgrade_warning'], '</strong><br />
			<div>
				', $upcontext['warning'], '
			</div>
		</div>';

	// Paths are incorrect?
	echo '
		<div class="errorbox" style="', (file_exists($settings['default_theme_dir'] . '/scripts/script.js') ? 'display: none' : ''), '" id="js_script_missing_error">
			<strong style="text-decoration: underline;">', $txt['upgrade_critical_error'], '</strong><br />
			<div>
				', $txt['upgrade_error_script_js'], '
			</div>
		</div>';

	// Is there someone already doing this?
	if (!empty($upcontext['user']['id']) && (time() - $upcontext['started'] < 72600 || time() - $upcontext['updated'] < 3600))
	{
		$ago = time() - $upcontext['started'];
		if ($ago < 60)
			$ago = $ago . ' seconds';
		elseif ($ago < 3600)
			$ago = (int) ($ago / 60) . ' minutes';
		else
			$ago = (int) ($ago / 3600) . ' hours';

		$active = time() - $upcontext['updated'];
		if ($active < 60)
			$updated = $active . ' seconds';
		elseif ($active < 3600)
			$updated = (int) ($active / 60) . ' minutes';
		else
			$updated = (int) ($active / 3600) . ' hours';

		echo '
		<div class="warningbox">
			<strong style="text-decoration: underline;">', $txt['upgrade_warning'], '</strong>
			<br />
			<div>
				&quot;', $upcontext['user']['name'], '&quot; has been running the upgrade script for the last ', $ago, ' - and was last active ', $updated, ' ago.';

		if ($active < 600)
			echo '
				We recommend that you do not run this script unless you are sure that ', $upcontext['user']['name'], ' has completed their upgrade.';

		if ($active > $upcontext['inactive_timeout'])
			echo '
				<br /><br />You can choose to either run the upgrade again from the beginning - or alternatively continue from the last step reached during the last upgrade.';
		else
			echo '
				<br /><br />This upgrade script cannot be run until ', $upcontext['user']['name'], ' has been inactive for at least ', ($upcontext['inactive_timeout'] > 120 ? round($upcontext['inactive_timeout'] / 60, 1) . ' minutes!' : $upcontext['inactive_timeout'] . ' seconds!');

		echo '
			</div>
		</div>';
	}

	echo '
			<strong>Admin Login: ', $disable_security ? '(DISABLED)' : '', '</strong>
			<h3>For security purposes please login with your admin account to proceed with the upgrade.</h3>
			<table>
				<tr style="vertical-align: top;">
					<td><strong ', $disable_security ? 'style="color: lightgray;"' : '', '>Username:</strong></td>
					<td>
						<input type="text" name="user" value="', !empty($upcontext['username']) ? $upcontext['username'] : '', '" ', $disable_security ? 'disabled="disabled"' : '', ' class="input_text" />';

	if (!empty($upcontext['username_incorrect']))
		echo '
						<div class="error">Username Incorrect</div>';

	echo '
					</td>
				</tr>
				<tr style="vertical-align: top;">
					<td><strong ', $disable_security ? 'style="color: lightgray;"' : '', '>Password:</strong></td>
					<td>
						<input type="password" name="passwrd" value=""', $disable_security ? ' disabled="disabled"' : '', ' class="input_password" />
						<input type="hidden" name="hash_passwrd" value="" />';

	if (!empty($upcontext['login_hash_error']))
		echo '
						<div class="error">Password security has recently been upgraded. Please enter your password again.</div>';
	elseif (!empty($upcontext['password_failed']))
		echo '
						<div class="error">Password Incorrect</div>';

	echo '
					</td>
				</tr>';

	// Can they continue?
	if (!empty($upcontext['user']['id']) && time() - $upcontext['user']['updated'] >= $upcontext['inactive_timeout'] && $upcontext['user']['step'] > 1)
	{
		echo '
				<tr>
					<td colspan="2">
						<label for="cont"><input type="checkbox" id="cont" name="cont" checked="checked" class="input_check" />Continue from step reached during last execution of upgrade script.</label>
					</td>
				</tr>';
	}

	echo '
			</table><br />
			<span class="smalltext">
				<strong>Note:</strong> If necessary the above security check can be bypassed for users who may administrate a server but not have admin rights on the forum. In order to bypass the above check simply open &quot;upgrade.php&quot; in a text editor and replace &quot;$disable_security = false;&quot; with &quot;$disable_security = true;&quot; and refresh this page.
			</span>
			<input type="hidden" name="login_attempt" id="login_attempt" value="1" />
			<input type="hidden" name="js_works" id="js_works" value="0" />';

	// Say we want the continue button!
	$upcontext['continue'] = !empty($upcontext['user']['id']) && time() - $upcontext['user']['updated'] < $upcontext['inactive_timeout'] ? 2 : 1;

	// This defines whether javascript is going to work elsewhere :D
	echo '
		<script>
			if (document.getElementById(\'js_works\'))
				document.getElementById(\'js_works\').value = 1;
			var currentVersionRounds = 0;

			// Latest version?
			function ourCurrentVersion()
			{
				var latestVer,
					setLatestVer;

				latestVer = document.getElementById(\'latestVersion\');
				setLatestVer = document.getElementById(\'elkVersion\');

				if (latestVer.innerHTML == \'???\')
				{
					// After few many tries let the use run the script
					if (currentVersionRounds > 9)
						document.getElementById(\'contbutt\').disabled = 0;

					currentVersionRounds++;
					setTimeout(\'ourCurrentVersion()\', 50);
					return;
				}

				if (setLatestVer !== null)
				{
					setLatestVer.innerHTML = latestVer.innerHTML.replace(\'ElkArte \', \'\');
					document.getElementById(\'version_warning\').style.display = \'\';
				}
				document.getElementById(\'contbutt\').disabled = 0;
			}
			addLoadEvent(ourCurrentVersion);

			// This checks that the script file even exists!
			if (typeof(elkSelectText) == \'undefined\')
				document.getElementById(\'js_script_missing_error\').style.display = \'\';

		</script>';
}

/**
 *
 */
function template_upgrade_options()
{
	global $upcontext, $modSettings, $db_prefix, $mmessage, $mtitle, $db_type;

	echo '
			<h3>Before the upgrade gets underway please review the options below - and hit continue when you\'re ready to begin.</h3>
			<form action="', $upcontext['form_url'], '" method="post" name="upform" id="upform">';

	// Warning message?
	if (!empty($upcontext['upgrade_options_warning']))
		echo '
		<div style="margin: 1ex; padding: 1ex; border: 1px dashed #cc3344; color: black; background: #ffe4e9;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<strong style="text-decoration: underline;">Warning!</strong><br />
			<div style="padding-left: 4ex;">
				', $upcontext['upgrade_options_warning'], '
			</div>
		</div>';

	echo '
				<table style="border-collapse:collapse; border-spacing: 1; padding: 2px;">
					<tr style="vertical-align: top;">
						<td style="width: 2%;">
							<input type="checkbox" name="backup" id="backup" value="1"', $db_type != 'mysql' && $db_type != 'postgresql' ? ' disabled="disabled"' : '', ' class="input_check" />
						</td>
						<td style="width: 100%;">
							<label for="backup">Backup tables in your database with the prefix &quot;backup_' . $db_prefix . '&quot;.</label>', isset($modSettings['elkVersion']) ? '' : ' (recommended!)', '
						</td>
					</tr>
					<tr style="vertical-align: top;">
						<td style="width: 2%;">
							<input type="checkbox" name="maint" id="maint" value="1" checked="checked" class="input_check" />
						</td>
						<td style="width: 100%;">
							<label for="maint">Put the forum into maintenance mode during upgrade.</label> <span class="smalltext">(<a href="#" onclick="document.getElementById(\'mainmess\').style.display = document.getElementById(\'mainmess\').style.display == \'\' ? \'none\' : \'\'">Customize</a>)</span>
							<div id="mainmess" style="display: none;">
								<strong class="smalltext">Maintenance Title: </strong><br />
								<input type="text" name="maintitle" size="30" value="', htmlspecialchars($mtitle, ENT_COMPAT, 'UTF-8'), '" class="input_text" /><br />
								<strong class="smalltext">Maintenance Message: </strong><br />
								<textarea name="mainmessage" rows="3" cols="50">', htmlspecialchars($mmessage, ENT_COMPAT, 'UTF-8'), '</textarea>
							</div>
						</td>
					</tr>
					<tr style="vertical-align: top;">
						<td style="width: 2%;">
							<input type="checkbox" name="debug" id="debug" value="1" class="input_check" />
						</td>
						<td style="width: 100%;">
							<label for="debug">Output extra debugging information</label>
						</td>
					</tr>
					<tr style="vertical-align: top;">
						<td style="width: 2%;">
							<input type="checkbox" name="empty_error" id="empty_error" value="1" class="input_check" />
						</td>
						<td style="width: 100%;">
							<label for="empty_error">Empty error log before upgrading</label>
						</td>
					</tr>
				</table>
				<input type="hidden" name="upcont" value="1" />';

	// We need a normal continue button here!
	$upcontext['continue'] = 1;
}

/**
 * Template for the database backup tool
 */
function template_backup_database()
{
	global $upcontext, $support_js, $is_debug;

	echo '
			<h3>Please wait while a backup is created. For large forums this may take some time!</h3>';

	echo '
			<form action="', $upcontext['form_url'], '" name="upform" id="upform" method="post">
			<input type="hidden" name="backup_done" id="backup_done" value="0" />
			<strong>Completed <span id="tab_done">', $upcontext['cur_table_num'], '</span> out of ', $upcontext['table_count'], ' tables.</strong>
			<span id="debuginfo"></span>';

	// Dont any tables so far?
	if (!empty($upcontext['previous_tables']))
		foreach ($upcontext['previous_tables'] as $table)
			echo '
			<br />Completed Table: &quot;', $table, '&quot;.';

	echo '
			<h3 id="current_tab_div">Current Table: &quot;<span id="current_table">', $upcontext['cur_table_name'], '</span>&quot;</h3>
			<br /><span id="commess" style="font-weight: bold; display: ', $upcontext['cur_table_num'] == $upcontext['table_count'] ? 'inline' : 'none', ';">Backup Complete! Click Continue to Proceed.</span>';

	// Continue please!
	$upcontext['continue'] = $support_js ? 2 : 1;

	// If javascript allows we want to do this using XML.
	if ($support_js)
	{
		echo '
		<script>
			var lastTable = ', $upcontext['cur_table_num'], ';

			function getNextTables()
			{
				getXMLDocument(\'', $upcontext['form_url'], '&xml&substep=\' + lastTable, onBackupUpdate);
			}

			// Got an update!
			function onBackupUpdate(oXMLDoc)
			{
				var sCurrentTableName = "",
					iTableNum = 0,
					sCompletedTableName = document.getElementById(\'current_table\').innerHTML;

				for (var i = 0; i < oXMLDoc.getElementsByTagName("table")[0].childNodes.length; i++)
					sCurrentTableName += oXMLDoc.getElementsByTagName("table")[0].childNodes[i].nodeValue;
				iTableNum = oXMLDoc.getElementsByTagName("table")[0].getAttribute("num");

				// Update the page.
				document.getElementById(\'tab_done\').innerHTML = iTableNum;
				document.getElementById(\'current_table\').innerHTML = sCurrentTableName;
				lastTable = iTableNum;
				updateStepProgress(iTableNum, ', $upcontext['table_count'], ', ', $upcontext['step_weight'] * ((100 - $upcontext['step_progress']) / 100), ');';

		// If debug flood the screen.
		if ($is_debug)
			echo '
				setOuterHTML(document.getElementById(\'debuginfo\'), \'<br />Completed Table: &quot;\' + sCompletedTableName + \'&quot;.<span id="debuginfo"><\' + \'/span>\');';

		echo '
				// Get the next update...
				if (iTableNum == ', $upcontext['table_count'], ')
				{
					document.getElementById(\'commess\').style.display = "";
					document.getElementById(\'current_tab_div\').style.display = "none";
					document.getElementById(\'contbutt\').disabled = 0;
					document.getElementById(\'backup_done\').value = 1;
				}
				else
					getNextTables();
			}
			getNextTables();
		</script>';
	}
}

/**
 *
 */
function template_backup_xml()
{
	global $upcontext;

	echo '
	<table num="', $upcontext['cur_table_num'], '">', $upcontext['cur_table_name'], '</table>';
}

/**
 * Here is the actual "make the changes" template!
 */
function template_database_changes()
{
	global $upcontext, $support_js, $is_debug, $timeLimitThreshold;

	echo '
		<h3>Executing database changes</h3>
		<h4 style="font-style: italic;">Please be patient - this may take some time on large forums. The time elapsed increments from the server to show progress is being made!</h4>';

	echo '
		<form action="', $upcontext['form_url'], '&amp;filecount=', $upcontext['file_count'], '" name="upform" id="upform" method="post">
		<input type="hidden" name="database_done" id="database_done" value="0" />';

	// No javascript looks rubbish!
	if (!$support_js)
	{
		foreach ($upcontext['actioned_items'] as $num => $item)
		{
			if ($num != 0)
				echo ' Successful!';
			echo '<br />' . $item;
		}
		if (!empty($upcontext['changes_complete']))
			echo ' Successful!<br /><br /><span id="commess" style="font-weight: bold;">Database Updates Complete! Click Continue to Proceed.</span><br />';
	}
	else
	{
		// Tell them how many files we have in total.
		if ($upcontext['file_count'] > 1)
			echo '
		<strong id="info1">Executing upgrade script <span id="file_done">', $upcontext['cur_file_num'], '</span> of ', $upcontext['file_count'], '.</strong>';

		echo '
		<h3 id="info2"><strong>Executing:</strong> &quot;<span id="cur_item_name">', $upcontext['current_item_name'], '</span>&quot; (<span id="item_num">', $upcontext['current_item_num'], '</span> of <span id="total_items"><span id="item_count">', $upcontext['total_items'], '</span>', $upcontext['file_count'] > 1 ? ' - of this script' : '', ')</span></h3>
		<br /><span id="commess" style="font-weight: bold; display: ', !empty($upcontext['changes_complete']) || $upcontext['current_debug_item_num'] == $upcontext['debug_items'] ? 'inline' : 'none', ';">Database Updates Complete! Click Continue to Proceed.</span>';

		if ($is_debug)
		{
			echo '
			<div id="debug_section" class="roundframe" style="height: 200px; overflow: auto;">
			<span id="debuginfo"></span>
			</div>';
		}
	}

	// Place for the XML error message.
	echo '
		<div id="error_block" class="errorbox" style="display: ', empty($upcontext['error_message']) ? 'none' : '', ';">
			<strong style="text-decoration: underline;">Error!</strong>
			<br />
			<div id="error_message">', isset($upcontext['error_message']) ? $upcontext['error_message'] : 'Unknown Error!', '</div>
		</div>';

	// We want to continue at some point!
	$upcontext['continue'] = $support_js ? 2 : 1;

	// If javascript allows we want to do this using XML.
	if ($support_js)
	{
		echo '
		<script>
			var lastItem = ', $upcontext['current_debug_item_num'], ',
				sLastString = "', strtr($upcontext['current_debug_item_name'], array('"' => '&quot;')), '",
				iLastSubStepProgress = -1,
				curFile = ', $upcontext['cur_file_num'], ',
				totalItems = 0,
				prevFile = 0,
				retryCount = 0,
				testvar = 0,
				timeOutID = 0,
				getData = "",
				debugItems = ', $upcontext['debug_items'], ';

			function getNextItem()
			{
				// We want to track this...
				if (timeOutID)
					clearTimeout(timeOutID);
				timeOutID = window.setTimeout("retTimeout()", ', (10 * $timeLimitThreshold), '000);

				getXMLDocument(\'', $upcontext['form_url'], '&xml&filecount=', $upcontext['file_count'], '&substep=\' + lastItem + getData, onItemUpdate);
			}

			// Got an update!
			function onItemUpdate(oXMLDoc)
			{
				var sItemName = "",
					sDebugName = "",
					iItemNum = 0,
					iSubStepProgress = -1,
					iDebugNum = 0,
					bIsComplete = 0,
					getData = "";

				// We\'ve got something - so reset the timeout!
				if (timeOutID)
					clearTimeout(timeOutID);

				// Assume no error at this time...
				document.getElementById("error_block").style.display = "none";

				// Are we getting some duff info?
				if (!oXMLDoc || !oXMLDoc.getElementsByTagName("item")[0])
				{
					// Too many errors?
					if (retryCount > 15)
					{
						document.getElementById("error_block").style.display = "";
						document.getElementById("error_message").innerHTML = "Error retrieving information on step: " + (sDebugName == "" ? sLastString : sDebugName);';

	if ($is_debug)
		echo '
						setOuterHTML(document.getElementById(\'debuginfo\'), \'<span style="color: red;">failed<\' + \'/span><span id="debuginfo"><\' + \'/span>\');';

	echo '
					}
					else
					{
						retryCount++;
						getNextItem();
					}
					return false;
				}

				// Never allow loops.
				if (curFile == prevFile)
				{
					retryCount++;
					if (retryCount > 10)
					{
						document.getElementById("error_block").style.display = "";
						document.getElementById("error_message").innerHTML = "Upgrade script appears to be going into a loop - step: " + sDebugName;';

	if ($is_debug)
		echo '
						setOuterHTML(document.getElementById(\'debuginfo\'), \'<span style="color: red;">failed<\' + \'/span><span id="debuginfo"><\' + \'/span>\');';

	echo '
					}
				}
				retryCount = 0;

				for (var i = 0; i < oXMLDoc.getElementsByTagName("item")[0].childNodes.length; i++)
					sItemName += oXMLDoc.getElementsByTagName("item")[0].childNodes[i].nodeValue;
				for (var i = 0; i < oXMLDoc.getElementsByTagName("debug")[0].childNodes.length; i++)
					sDebugName += oXMLDoc.getElementsByTagName("debug")[0].childNodes[i].nodeValue;
				for (var i = 0; i < oXMLDoc.getElementsByTagName("get").length; i++)
				{
					getData += "&" + oXMLDoc.getElementsByTagName("get")[i].getAttribute("key") + "=";
					for (var j = 0; j < oXMLDoc.getElementsByTagName("get")[i].childNodes.length; j++)
					{
						getData += oXMLDoc.getElementsByTagName("get")[i].childNodes[j].nodeValue;
					}
				}

				iItemNum = oXMLDoc.getElementsByTagName("item")[0].getAttribute("num");
				iDebugNum = parseInt(oXMLDoc.getElementsByTagName("debug")[0].getAttribute("num"));
				bIsComplete = parseInt(oXMLDoc.getElementsByTagName("debug")[0].getAttribute("complete"));
				iSubStepProgress = parseFloat(oXMLDoc.getElementsByTagName("debug")[0].getAttribute("percent"));
				sLastString = sDebugName + " (Item: " + iDebugNum + ")";

				curFile = parseInt(oXMLDoc.getElementsByTagName("file")[0].getAttribute("num"));
				debugItems = parseInt(oXMLDoc.getElementsByTagName("file")[0].getAttribute("debug_items"));
				totalItems = parseInt(oXMLDoc.getElementsByTagName("file")[0].getAttribute("items"));

				// If we have an error we haven\'t completed!
				if (oXMLDoc.getElementsByTagName("error")[0] && bIsComplete)
					iDebugNum = lastItem;

				// Do we have the additional progress bar?
				if (iSubStepProgress != -1)
				{
					document.getElementById("substep_bar_div").style.display = "";
					document.getElementById("substep_bar_div2").style.display = "";
					document.getElementById("substep_progress").style.width = iSubStepProgress + "%";
					document.getElementById("substep_text").innerHTML = iSubStepProgress + "%";
					document.getElementById("substep_bar_div").innerHTML = sDebugName.replace(/\./g, "") + ":";
				}
				else
				{
					document.getElementById("substep_bar_div").style.display = "none";
					document.getElementById("substep_bar_div2").style.display = "none";
				}

				// Move onto the next item?
				if (bIsComplete)
					lastItem = iDebugNum;
				else
					lastItem = iDebugNum - 1;

				// Are we finished?
				if (bIsComplete && iDebugNum == -1 && curFile >= ', $upcontext['file_count'], ')
				{';

		if ($is_debug)
			echo '
					document.getElementById(\'debug_section\').style.display = "none";';

		echo '
					document.getElementById(\'commess\').style.display = "";
					document.getElementById(\'contbutt\').disabled = 0;
					document.getElementById(\'database_done\').value = 1;';

		if ($upcontext['file_count'] > 1)
			echo '
					document.getElementById(\'info1\').style.display = "none";';

		echo '
					document.getElementById(\'info2\').style.display = "none";
					updateStepProgress(100, 100, ', $upcontext['step_weight'] * ((100 - $upcontext['step_progress']) / 100), ');
					return true;
				}
				// Was it the last step in the file?
				else if (bIsComplete && iDebugNum == -1)
				{
					lastItem = 0;
					prevFile = curFile;';

		if ($is_debug)
			echo '
					setOuterHTML(document.getElementById(\'debuginfo\'), \'Moving to next script file...done<br /><span id="debuginfo"><\' + \'/span>\');';

		echo '
					getNextItem();
					return true;
				}';

		// If debug scroll the screen.
		if ($is_debug)
			echo '
				if (iLastSubStepProgress == -1)
				{
					// Give it consistent dots.
					dots = sDebugName.match(/\./g);
					numDots = dots ? dots.length : 0;
					for (var i = numDots; i < 3; i++)
						sDebugName += ".";
					setOuterHTML(document.getElementById(\'debuginfo\'), sDebugName + \'<span id="debuginfo"><\' + \'/span>\');
				}
				iLastSubStepProgress = iSubStepProgress;

				if (bIsComplete)
					setOuterHTML(document.getElementById(\'debuginfo\'), \'done<br /><span id="debuginfo"><\' + \'/span>\');
				else
					setOuterHTML(document.getElementById(\'debuginfo\'), \'...<span id="debuginfo"><\' + \'/span>\');

				if (document.getElementById(\'debug_section\').scrollHeight)
					document.getElementById(\'debug_section\').scrollTop = document.getElementById(\'debug_section\').scrollHeight';

		echo '
				// Update the page.
				document.getElementById(\'item_num\').innerHTML = iItemNum;
				document.getElementById(\'cur_item_name\').innerHTML = sItemName;';

		if ($upcontext['file_count'] > 1)
		{
			echo '
				document.getElementById(\'file_done\').innerHTML = curFile;
				document.getElementById(\'item_count\').innerHTML = totalItems;';
		}

		echo '
				// Is there an error?
				if (oXMLDoc.getElementsByTagName("error")[0])
				{
					var sErrorMsg = "";
					for (var i = 0; i < oXMLDoc.getElementsByTagName("error")[0].childNodes.length; i++)
						sErrorMsg += oXMLDoc.getElementsByTagName("error")[0].childNodes[i].nodeValue;
					document.getElementById("error_block").style.display = "";
					document.getElementById("error_message").innerHTML = sErrorMsg;
					return false;
				}

				// Get the progress bar right.
				barTotal = debugItems * ', $upcontext['file_count'], ';
				barDone = (debugItems * (curFile - 1)) + lastItem;

				updateStepProgress(barDone, barTotal, ', $upcontext['step_weight'] * ((100 - $upcontext['step_progress']) / 100), ');

				// Finally - update the time here as it shows the server is responding!
				curTime = new Date();
				iElapsed = (curTime.getTime() / 1000 - ', $upcontext['started'], ');
				mins = parseInt(iElapsed / 60);
				secs = parseInt(iElapsed - mins * 60);
				document.getElementById("mins_elapsed").innerHTML = mins;
				document.getElementById("secs_elapsed").innerHTML = secs;

				getNextItem();
				return true;
			}

			// What if we timeout?!
			function retTimeout(attemptAgain)
			{
				// Oh noes...
				if (!attemptAgain)
				{
					document.getElementById("error_block").style.display = "";
					document.getElementById("error_message").innerHTML = "Server has not responded for ', ($timeLimitThreshold * 10), ' seconds. It may be worth waiting a little longer or otherwise please click <a href=\"#\" onclick=\"retTimeout(true); return false;\">here<" + "/a> to try this step again";
				}
				else
				{
					document.getElementById("error_block").style.display = "none";
					getNextItem();
				}
			}';

		// Start things off assuming we've not errored.
		if (empty($upcontext['error_message']))
			echo '
			getNextItem();';

		echo '
		</script>';
	}
	return;
}

/**
 *
 */
function template_database_xml()
{
	global $upcontext;

	echo '
	<file num="', $upcontext['cur_file_num'], '" items="', $upcontext['total_items'], '" debug_items="', $upcontext['debug_items'], '">', $upcontext['cur_file_name'], '</file>
	<item num="', $upcontext['current_item_num'], '">', $upcontext['current_item_name'], '</item>
	<debug num="', $upcontext['current_debug_item_num'], '" percent="', isset($upcontext['substep_progress']) ? $upcontext['substep_progress'] : '-1', '" complete="', empty($upcontext['completed_step']) ? 0 : 1, '">', $upcontext['current_debug_item_name'], '</debug>';

	if (!empty($upcontext['error_message']))
		echo '
	<error>', $upcontext['error_message'], '</error>';
}

/**
 *
 */
function template_upgrade_complete()
{
	global $upcontext, $upgradeurl, $settings, $boardurl;

	echo '
	<h3>That wasn\'t so hard, was it?  Now you are ready to use <a href="', $boardurl, '/index.php">your installation of ElkArte</a>.  Hope you like it!</h3>
	<form action="', $boardurl, '/index.php">';

	if (!empty($upcontext['can_delete_script']))
		echo '
			<label for="delete_self"><input type="checkbox" id="delete_self" onclick="doTheDelete(this);" class="input_check" /> Delete this upgrade.php and its data files now.</label> <em>(doesn\'t work on all servers.)</em>
			<script>
				function doTheDelete(theCheck)
				{
					var theImage = document.getElementById ? document.getElementById("delete_upgrader") : document.all.delete_upgrader;

					theImage.src = "', $upgradeurl, '?delete=1&ts_" + (new Date().getTime());
					theCheck.disabled = true;
				}
			</script>
			<img src="', $settings['default_theme_url'], '/images/blank.png" alt="" id="delete_upgrader" /><br />';

	echo '<br />
			If you had any problems with this upgrade, or have any problems using ElkArte, please don\'t hesitate to <a href="', SITE_SOFTWARE, '/index.php">look to us for assistance</a>.<br />
			<br />
			Best of luck,<br />
			ElkArte';
}
