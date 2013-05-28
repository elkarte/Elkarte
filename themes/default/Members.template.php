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
 * Sub-template for the find members actions
 * It shows a popup dialog with a list of members
 */
function template_find_members()
{
	global $context, $settings, $scripturl, $txt;

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>', $txt['find_members'], '</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta name="robots" content="noindex" />
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css?alp21" />
		<script src="', $settings['default_theme_url'], '/scripts/script.js"></script>
		<script><!-- // --><![CDATA[
			var membersAdded = [];
			function addMember(name)
			{
				var theTextBox = window.opener.document.getElementById("', $context['input_box_name'], '");

				if (name in membersAdded)
					return;

				// If we only accept one name don\'t remember what is there.
				if (', JavaScriptEscape($context['delimiter']), ' != \'null\')
					membersAdded[name] = true;

				if (theTextBox.value.length < 1 || ', JavaScriptEscape($context['delimiter']), ' == \'null\')
					theTextBox.value = ', $context['quote_results'] ? '"\"" + name + "\""' : 'name', ';
				else
					theTextBox.value += ', JavaScriptEscape($context['delimiter']), ' + ', $context['quote_results'] ? '"\"" + name + "\""' : 'name', ';

				window.focus();
			}
		// ]]></script>
	</head>
	<body id="help_popup">
		<form action="', $scripturl, '?action=findmember;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8" class="padding description">
			<div class="roundframe">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['find_members'], '</h3>
				</div>
				<div class="padding">
					<strong>', $txt['find_username'], ':</strong><br />
					<input type="text" name="search" id="search" value="', isset($context['last_search']) ? $context['last_search'] : '', '" style="margin-top: 4px; width: 96%;" class="input_text" autofocus="autofocus" placeholder="', $txt['find_members'], '" required="required" /><br />
					<span class="smalltext"><em>', $txt['find_wildcards'], '</em></span><br />';

	// Only offer to search for buddies if we have some!
	if (!empty($context['show_buddies']))
		echo '
					<span class="smalltext"><label for="buddies"><input type="checkbox" class="input_check" name="buddies" id="buddies"', !empty($context['buddy_search']) ? ' checked="checked"' : '', ' /> ', $txt['find_buddies'], '</label></span><br />';

	echo '
					<input type="submit" value="', $txt['search'], '" class="button_submit" />
					<input type="button" value="', $txt['find_close'], '" onclick="window.close();" class="button_submit" />
				</div>
			</div>
			<br />
			<div class="roundframe">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['find_results'], '</h3>
				</div>';

	if (empty($context['results']))
		echo '
				<p class="error">', $txt['find_no_results'], '</p>';
	else
	{
		echo '
				<ul class="reset padding">';

		$alternate = true;
		foreach ($context['results'] as $result)
		{
			echo '
					<li class="', $alternate ? 'windowbg2' : 'windowbg', '">
						<a href="', $result['href'], '" target="_blank" class="new_win"><img src="', $settings['images_url'], '/icons/profile_sm.png" alt="', $txt['view_profile'], '" title="', $txt['view_profile'], '" /></a>
						<a href="javascript:void(0);" onclick="addMember(this.innerHTML); return false;">', $result['name'], '</a>
					</li>';

			$alternate = !$alternate;
		}

		echo '
				</ul>
				<div class="pagesection">
					', $context['page_index'], '
				</div>';
	}

	echo '

			</div>
			<input type="hidden" name="input" value="', $context['input_box_name'], '" />
			<input type="hidden" name="delim" value="', $context['delimiter'], '" />
			<input type="hidden" name="quote" value="', $context['quote_results'] ? '1' : '0', '" />
		</form>';

	if (empty($context['results']))
		echo '
		<script><!-- // --><![CDATA[
			document.getElementById("search").focus();
		// ]]></script>';

	echo '
	</body>
</html>';
}