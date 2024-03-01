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

function template_install_above()
{
	global $incontext, $txt, $installurl;

	echo '<!DOCTYPE html>
<html ', empty($txt['lang_rtl']) ? '' : 'dir="rtl"', '>
	<head>
		<meta charset="utf-8" />
		<meta name="robots" content="noindex" />
		<title>', $txt['installer'], '</title>
		<link rel="stylesheet" href="../themes/default/css/index.css?20RC1" />
		<link rel="stylesheet" href="../themes/default/css/_light/index_light.css?20RC1" />
		<link rel="stylesheet" href="../themes/default/css/install.css?20RC1" />
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js" id="jquery"></script>
		<script>
			window.jQuery || document.write(\'<script src="../themes/default/scripts/jquery-3.7.1.min.js"><\/script>\');
			var elk_scripturl = ', JavaScriptEscape(str_replace('/install/install.php', '/index.php', $installurl)), ';
		</script>
		<script src="../themes/default/scripts/script.js"></script>
	</head>
	<body>
		<div id="header">
			<div class="frame">
				<h1 class="forumtitle">', $txt['installer'], '</h1>
				<img id="logo" src="../themes/default/images/logo.png" alt="ElkArte Community" title="ElkArte Community" />
			</div>
		</div>
		<div id="wrapper" class="wrapper">
			<div id="upper_section">
				<div id="inner_section">
					<div id="inner_wrap">';

	// Have we got a language drop down - if so do it on the first step only.
	if (!empty($incontext['detected_languages']) && count($incontext['detected_languages']) > 1 && $incontext['current_step'] == 0)
	{
		echo '
						<div class="news">
							<form action="', $installurl, '" method="get">
								<label for="installer_language">', $txt['installer_language'], ':</label>
								<select id="installer_language" name="lang_file" onchange="location.href = \'', $installurl, '?lang_file=\' + this.options[this.selectedIndex].value;">';

		foreach ($incontext['detected_languages'] as $lang => $name)
		{
			echo '
									<option', isset($_SESSION['installer_temp_lang']) && $_SESSION['installer_temp_lang'] == $lang ? ' selected="selected"' : '', ' value="', $lang, '">', $name, '</option>';
		}

		echo '
								</select>
								<noscript><input type="submit" value="', $txt['installer_language_set'], '" class="button_submit" /></noscript>
							</form>
						</div>
						<hr class="clear" />';
	}

	echo '
					</div>
				</div>
			</div>
			<div id="content_section">
				<div id="main_steps">
					<h2>', $txt['upgrade_progress'], '</h2>
					<ul>';

	foreach ($incontext['steps'] as $num => $step)
	{
		echo '
						<li class="', $num < $incontext['current_step'] ? 'stepdone' : ($num == $incontext['current_step'] ? 'stepcurrent' : 'stepwaiting'), '">', $txt['upgrade_step'], ' ', $step[0], ': ', $step[1], '</li>';
	}

	echo '
					</ul>
				</div>
				<div id="progress_bars">
					<div id="progress_bar">
						<div id="overall_text">', $incontext['overall_percent'], '%</div>
						<div id="overall_progress" style="width: ', $incontext['overall_percent'], '%;">&nbsp;</div>
						<div class="overall_progress">', $txt['upgrade_overall_progress'], '</div>
					</div>
				</div>
			</div>	
			<div id="main_screen">
				<h2>', $incontext['page_title'], '</h2>
				<div class="content">';
}

function template_install_below()
{
	global $incontext, $txt;

	if (!empty($incontext['continue']) || !empty($incontext['retry']))
	{
		echo '
						<div class="clear righttext">';

		if (!empty($incontext['continue']))
		{
			echo '
							<input type="submit" id="contbutt" name="contbutt" value="', $txt['upgrade_continue'], '" onclick="return submitThisOnce(this);" class="button_submit" />';
		}

		if (!empty($incontext['retry']))
		{
			echo '
							<input type="submit" id="contbutt" name="contbutt" value="', $txt['upgrade_retry'], '" onclick="return submitThisOnce(this);" class="button_submit" />';
		}

		echo '
						</div>';
	}

	// Show the closing form tag and other data only if not in the last step
	if (count($incontext['steps']) - 1 !== (int) $incontext['current_step'])
	{
		echo '
					</form>';
	}

	echo '
				</div>
			</div>
		</div>
		<div id="footer_section">
			<div class="frame copyright">
				<a href="', SITE_SOFTWARE, '" title="ElkArte Community" target="_blank" class="new_win">ElkArte &copy; 2012 - 2022, ElkArte Community</a>
			</div>
		</div>
	</body>
</html>';
}

/**
 * Welcome them to the wonderful world of ElkArte!
 */
function template_welcome_message()
{
	global $incontext, $txt;

	echo '
	<script src="../themes/default/scripts/admin.js"></script>
	<script>
		let oUpgradeCenter = new Elk_AdminIndex({
			bLoadAnnouncements: false,
			bLoadVersions: true,
			slatestVersionContainerId: \'latestVersion\',
			sinstalledVersionContainerId: \'version_warning\',
			sVersionOutdatedTemplate: ', JavaScriptEscape('
		<strong style="text-decoration: underline;">' . $txt['error_warning_notice'] . '</strong><p>
			' . sprintf($txt['error_script_outdated'], '<em id="elkVersion" style="white-space: nowrap;">??</em>', '<em style="white-space: nowrap;">' . CURRENT_VERSION . '</em>') . '
			</p>'), ',
			bLoadUpdateNotification: false
		});
	</script>
	<form id="welcome" action="', $incontext['form_url'], '" method="post">
		<p>', sprintf($txt['install_welcome_desc'], CURRENT_VERSION), '</p>
		<div id="version_warning" class="warningbox hide">', CURRENT_VERSION, '</div>
		<div id="latestVersion" class="hide">???</div>';

	// Show the warnings, or not.
	if (template_warning_divs())
	{
		echo '
		<h3>', $txt['install_all_lovely'], '</h3>';
	}

	// Say we want the continue button!
	if (empty($incontext['error']))
	{
		$incontext['continue'] = 1;
	}

	// For the latest version stuff.
	echo '
		<script>
			let currentVersionRounds = 0;

			// Latest version?
			function ourCurrentVersion()
			{
				let latestVer,
					setLatestVer;

				// After few many tries let the use run the script
				if (currentVersionRounds > 9)
					document.getElementById(\'contbutt\').disabled = 0;

				latestVer = document.getElementById(\'latestVersion\');
				setLatestVer = document.getElementById(\'elkVersion\');

				if (latestVer.innerHTML === \'???\')
				{
					setTimeout(\'ourCurrentVersion()\', 50);
					return;
				}

				if (setLatestVer !== null)
				{
					setLatestVer.innerHTML = latestVer.innerHTML.replace(\'ElkArte \', \'\');
					document.getElementById(\'version_warning\').classList.remove(\'hide\');
				}

				document.getElementById(\'contbutt\').disabled = 0;
			}
			
			window.addEventListener("load", ourCurrentVersion)
		</script>';
}

/**
 * A shortcut for any warning stuff.
 */
function template_warning_divs()
{
	global $txt, $incontext;

	// Errors are very serious..
	if (!empty($incontext['error']))
	{
		echo '
		<div class="errorbox">
			<strong style="text-decoration: underline;">', $txt['upgrade_critical_error'], '</strong>
			<br />
			<div>
				', $incontext['error'], '
			</div>
		</div>';
	}

	// A warning message?
	if (!empty($incontext['warning']))
	{
		echo '
		<div class="warningbox">
			<strong style="text-decoration: underline;">', $txt['upgrade_warning'], '</strong>
			<br />
			<div>
				', $incontext['warning'], '
			</div>
		</div>';
	}

	// Any informative message?
	if (!empty($incontext['infobox']))
	{
		echo '
		<div class="information">
			<strong style="text-decoration: underline;">', $txt['upgrade_note'], '</strong>
			<br />
			<div>
				', $incontext['infobox'], '
			</div>
		</div>';
	}

	return empty($incontext['error']) && empty($incontext['warning']);
}

/**
 * Let them know we need access to the files and FTP may be needed.
 */
function template_chmod_files()
{
	global $txt, $incontext;

	echo '
		<p>', $txt['ftp_setup_why_info'], '</p>
		<ul class="ftp_setup">
			<li>', implode('</li><li>', $incontext['failed_files']), '</li>
		</ul>';

	// This is serious!
	if (!template_warning_divs())
	{
		return;
	}

	echo '
		<hr />
		<p>', $txt['ftp_setup_info'], '</p>';

	if (!empty($incontext['ftp_errors']))
	{
		echo '
		<div class="errorbox">
			<div class="error">
				', $txt['error_ftp_no_connect'], '<br />
				<br />
				<code>', implode('<br />', $incontext['ftp_errors']), '</code>
			</div>
		</div>
		<br />';
	}

	echo '
		<form id="chmod" action="', $incontext['form_url'], '" method="post">
			<table class="chmod_table">
				<tr>
					<td class="textbox grid25">
						<label for="ftp_server">', $txt['ftp_server'], ':</label>
					</td>
					<td>
						<div style="float: ', empty($txt['lang_rtl']) ? 'right' : 'left', '; margin-', empty($txt['lang_rtl']) ? 'right' : 'left', ': 1px;">
							<label for="ftp_port" class="textbox"><strong>', $txt['ftp_port'], ':&nbsp;</strong></label>
 							<input type="text" size="3" name="ftp_port" id="ftp_port" value="', $incontext['ftp']['port'], '" class="input_text" />
						</div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', $incontext['ftp']['server'], '" style="width: 70%;" class="input_text" />
						<div class="notes">', $txt['ftp_server_info'], '</div>
					</td>
				</tr>
				<tr>
					<td class="textbox grid25"><label for="ftp_username">', $txt['ftp_username'], ':</label></td>
					<td>
						<input type="text" size="50" name="ftp_username" id="ftp_username" value="', $incontext['ftp']['username'], '" style="width: 99%;" class="input_text" />
						<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['ftp_username_info'], '</div>
					</td>
				</tr>
				<tr>
					<td class="textbox grid25"><label for="ftp_password">', $txt['ftp_password'], ':</label></td>
					<td>
						<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 99%;" class="input_password" />
						<div style="font-size: smaller; margin-bottom: 3ex;">', $txt['ftp_password_info'], '</div>
					</td>
				</tr>
				<tr>
					<td class="textbox grid25">
						<label for="ftp_path">', $txt['ftp_path'], ':</label>
					</td>
					<td style="padding-bottom: 1ex;">
						<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $incontext['ftp']['path'], '" style="width: 99%;" class="input_text" />
						<div style="font-size: smaller; margin-bottom: 2ex;">', $incontext['ftp']['path_msg'], '</div>
					</td>
				</tr>
			</table>
			<div style="margin: 1ex; margin-top: 1ex; text-align: ', empty($txt['lang_rtl']) ? 'right' : 'left', ';"><input type="submit" value="', $txt['ftp_connect'], '" onclick="return submitThisOnce(this);" class="button_submit" /></div>
		</form>
		<a href="', $incontext['form_url'], '">', $txt['error_message_click'], '</a> ', $txt['ftp_setup_again'];
}

/**
 * Template for the database settings form.
 */
function template_database_settings()
{
	global $incontext, $txt;

	echo '
	<form id="db_settings" action="', $incontext['form_url'], '" method="post">
		<p class="infobox">', $txt['db_settings_info'], '</p>';

	template_warning_divs();

	echo '
		<table class="step_table">';

	// More than one database type?
	if (count($incontext['supported_databases']) > 1)
	{
		echo '
			<tr>
				<td class="textbox grid25">
					<label for="db_type_input">', $txt['db_settings_type'], ':</label>
				</td>
				<td>
					<select name="db_type" id="db_type_input">';

		foreach ($incontext['supported_databases'] as $key => $db)
		{
			echo '
						<option value="', $key, '"', isset($_POST['db_type']) && $_POST['db_type'] == $key ? ' selected="selected"' : '', '>', $db['name'], '</option>';
		}

		echo '
					</select>
					<div class="notes">', $txt['db_settings_type_info'], '</div>
				</td>
			</tr>';
	}
	else
	{
		echo '
			<tr class="hide">
				<td>
					<input type="hidden" name="db_type" value="', $incontext['db']['type'], '" />
				</td>
			</tr>';
	}

	echo '
			<tr id="db_server_contain">
				<td class="textbox grid25">
					<label for="db_server_input">', $txt['db_settings_server'], ':</label>
				</td>
				<td>
					<input type="text" name="db_server" id="db_server_input" value="', $incontext['db']['server'], '" size="30" class="input_text" />
					<br />
					<div class="notes">', $txt['db_settings_server_info'], '</div>
				</td>
			</tr>
			<tr id="db_user_contain">
				<td class="textbox">
					<label for="db_user_input">', $txt['db_settings_username'], ':</label>
				</td>
				<td>
					<input type="text" name="db_user" id="db_user_input" value="', $incontext['db']['user'], '" size="30" class="input_text" />
					<br />
					<div class="notes">', $txt['db_settings_username_info'], '</div>
				</td>
			</tr>
			<tr id="db_passwd_contain">
				<td class="textbox">
					<label for="db_passwd_input">', $txt['db_settings_password'], ':</label>
				</td>
				<td>
					<input type="password" name="db_passwd" id="db_passwd_input" value="', $incontext['db']['pass'], '" size="30" class="input_password" />
					<br />
					<div class="notes">', $txt['db_settings_password_info'], '</div>
				</td>
			</tr>
			<tr id="db_name_contain">
				<td class="textbox">
					<label for="db_name_input">', $txt['db_settings_database'], ':</label>
				</td>
				<td>
					<input type="text" name="db_name" id="db_name_input" value="', empty($incontext['db']['name']) ? 'elkarte' : $incontext['db']['name'], '" size="30" class="input_text" />
					<br />
					<div class="notes">', $txt['db_settings_database_info'], '
						<span id="db_name_info_warning">', $txt['db_settings_database_info_note'], '</span>
					</div>
				</td>
			</tr>
			<tr>
				<td class="textbox">
					<label for="db_prefix_input">', $txt['db_settings_prefix'], ':</label>
				</td>
				<td>
					<input type="text" name="db_prefix" id="db_prefix_input" value="', $incontext['db']['prefix'], '" size="30" class="input_text" />
					<br />
					<div class="notes">', $txt['db_settings_prefix_info'], '</div>
				</td>
			</tr>
		</table>';

	// Allow the toggling of input boxes for Postgresql
	echo '
	<script>
		function validatePgsql()
		{
			let dbtype = document.getElementById(\'db_type_input\');

			if (dbtype !== null && dbtype.value == \'postgresql\')
				document.getElementById(\'db_name_info_warning\').style.display = \'none\';
			else
				document.getElementById(\'db_name_info_warning\').style.display = \'\';
		}
		validatePgsql();
	</script>';
}

/**
 * Stick in their forum settings.
 */
function template_forum_settings()
{
	global $incontext, $txt;

	echo '
	<form id="forum_settings" action="', $incontext['form_url'], '" method="post">
		<h3>', $txt['install_settings_info'], '</h3>';

	template_warning_divs();

	echo '
		<table class="step_table">
			<tr>
				<td class="textbox grid25">
					<label for="mbname_input">', $txt['install_settings_name'], ':</label>
				</td>
				<td>
					<input type="text" name="mbname" id="mbname_input" value="', $txt['install_settings_name_default'], '" size="65" class="input_text" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['install_settings_name_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox">
					<label for="boardurl_input">', $txt['install_settings_url'], ':</label>
				</td>
				<td>
					<input type="text" name="boardurl" id="boardurl_input" value="', $incontext['detected_url'], '" size="65" class="input_text" /><br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['install_settings_url_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox">', $txt['install_settings_compress'], ':</td>
				<td>
					<input type="checkbox" name="compress" id="compress_check" checked="checked" class="input_check" /> <label for="compress_check">', $txt['install_settings_compress_title'], '</label><br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['install_settings_compress_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox">', $txt['install_settings_dbsession'], ':</td>
				<td>
					<input type="checkbox" name="dbsession" id="dbsession_check" checked="checked" class="input_check" /> <label for="dbsession_check">', $txt['install_settings_dbsession_title'], '</label><br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $incontext['test_dbsession'] ? $txt['install_settings_dbsession_info1'] : $txt['install_settings_dbsession_info2'], '</div>
				</td>
			</tr>
		</table>';
}

/**
 * Show results of the database population.
 */
function template_populate_database()
{
	global $incontext, $txt;

	echo '
	<form id="populate_db" action="', $incontext['form_url'], '" method="post">
		<p>', empty($incontext['was_refresh']) ? $txt['db_populate_info'] : $txt['user_refresh_install_desc'], '</p>';

	if (!empty($incontext['sql_results']))
	{
		echo '
		<ul class="bbc_list">
			<li>', implode('</li><li>', $incontext['sql_results']), '</li>
		</ul>';
	}

	// Any errors we need to report?
	if (!empty($incontext['failures']))
	{
		echo '
				<div class="errorbox">', $txt['error_db_queries'], '
					<ul>';

		foreach ($incontext['failures'] as $line => $fail)
		{
			echo '
						<li><strong>', $txt['error_db_queries_line'], $line + 1, ':</strong> ', nl2br(htmlspecialchars($fail, ENT_COMPAT, 'UTF-8')), '</li>';
		}

		echo '
					</ul>
				</div>';
	}

	echo '
		<br /><p>', $txt['db_populate_info2'], '</p>';

	template_warning_divs();

	echo '
	<input type="hidden" name="pop_done" value="1" />';
}

/**
 * Template for the form to create the admin account.
 */
function template_admin_account()
{
	global $incontext, $txt;

	echo '
	<form id="admin_account" action="', $incontext['form_url'], '" method="post">
		<p class="infobox">', $txt['user_settings_info'], '</p>';

	template_warning_divs();

	echo '
		<table class="step_table">
			<tr>
				<td class="textbox grid17">
					<label for="username">', $txt['user_settings_username'], ':</label>
				</td>
				<td>
					<input type="text" name="username" id="username" value="', $incontext['username'], '" size="40" class="input_text" />
					<div class="notes">', $txt['user_settings_username_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox">
					<label for="password1">', $txt['user_settings_password'], ':</label>
				</td>
				<td>
					<input type="password" name="password1" id="password1" size="40" class="input_password" />
					<div class="notes">', $txt['user_settings_password_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox">
					<label for="password2">', $txt['user_settings_again'], ':</label>
				</td>
				<td>
					<input type="password" name="password2" id="password2" size="40" class="input_password" />
					<div class="notes">', $txt['user_settings_again_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox">
					<label for="email">', $txt['user_settings_email'], ':</label>
				</td>
				<td>
					<input type="email" name="email" id="email" value="', $incontext['email'], '" size="40" class="input_text" />
					<div class="notes">', $txt['user_settings_email_info'], '</div>
				</td>
			</tr>
		</table>';

	if ($incontext['require_db_confirm'])
	{
		echo '
		<h2>', $txt['user_settings_database'], '</h2>
		<p>', $txt['user_settings_database_info'], '</p>

		<div style="padding-bottom: 2ex; padding-', empty($txt['lang_rtl']) ? 'left' : 'right', ': 50px;">
			<input type="password" name="password3" size="30" class="input_password" />
		</div>';
	}
}

/**
 * Tell them it's done, and to delete.
 */
function template_delete_install()
{
	global $incontext, $installurl, $txt, $boardurl;

	echo '
		<p>', $txt['congratulations_help'], '</p>';

	template_warning_divs();

	// Install directory still writable?
	if ($incontext['dir_still_writable'])
	{
		echo '
		<em>', $txt['still_writable'], '</em>
		<br />
		<br />';
	}

	// Don't show the box if it's like 99% sure it won't work :P.
	if ($incontext['probably_delete_install'])
	{
		echo '
		<div id="delete_label" class="hide bbc_strong">
			<label for="delete_self">
				<input type="checkbox" id="delete_self" onclick="doTheDelete();" class="input_check" /> ', $txt['delete_installer'], isset($_SESSION['installer_temp_ftp']) ? '' : ' ' . $txt['delete_installer_maybe'], '
			</label>
		</div>
		<script>
			function doTheDelete()
			{
				let theCheck = document.getElementById ? document.getElementById("delete_self") : document.all.delete_self,
					tempImage = new Image();

				tempImage.src = "', $installurl, '?delete=1&ts_" + (new Date().getTime());
				tempImage.width = 0;
				theCheck.disabled = true;
			}
			document.getElementById(\'delete_label\').classList.remove(\'hide\');
		</script>
		<br />';
	}

	echo '
		', sprintf($txt['go_to_your_forum'], $boardurl . '/index.php'), '<br />
		<br />
		', $txt['good_luck'];
}
