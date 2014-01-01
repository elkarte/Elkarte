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
 * Sub-template for the find members actions
 * It shows a popup dialog with a list of members
 */
function template_find_members()
{
	global $context, $settings, $scripturl, $txt;

	echo '<!DOCTYPE html>
<html ', $context['right_to_left'] ? 'dir="rtl"' : '', '>
	<head>
		<title>', $txt['find_members'], '</title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta name="robots" content="noindex" />
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css?beta10" />
		<script src="', $settings['default_theme_url'], '/scripts/script.js"></script>
		<script><!-- // --><![CDATA[
			var membersAdded = [];
			function addMember(name)
			{
				var theTextBox = window.opener.document.getElementById("', $context['input_box_name'], '");

				if (name in membersAdded)
					return;

				// If we only accept one name don\'t remember what is there.
				if (', JavaScriptEscape($context['delimiter']), ' !== null)
					membersAdded[name] = true;

				if (theTextBox.value.length < 1 || ', JavaScriptEscape($context['delimiter']), ' === null)
					theTextBox.value = ', $context['quote_results'] ? '"\"" + name + "\""' : 'name', ';
				else
					theTextBox.value += ', JavaScriptEscape($context['delimiter']), ' + ', $context['quote_results'] ? '"\"" + name + "\""' : 'name', ';

				window.focus();
			}
		// ]]></script>
	</head>
	<body id="help_popup">
		<form action="', $scripturl, '?action=findmember;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
			<div class="roundframe">
				<h2 class="category_header">', $txt['find_members'], '</h2>
				<div>
					<strong>', $txt['find_username'], ':</strong><br />
					<input type="text" name="search" id="search" value="', isset($context['last_search']) ? $context['last_search'] : '', '" style="margin-top: 4px; width: 96%;" class="input_text" autofocus="autofocus" placeholder="', $txt['find_members'], '" required="required" /><br />
					<span class="smalltext"><em>', $txt['find_wildcards'], '</em></span><br />';

	// Only offer to search for buddies if we have some!
	if (!empty($context['show_buddies']))
		echo '
					<span class="smalltext"><label for="buddies"><input type="checkbox" class="input_check" name="buddies" id="buddies"', !empty($context['buddy_search']) ? ' checked="checked"' : '', ' /> ', $txt['find_buddies'], '</label></span><br />';

	echo '
					<input type="submit" value="', $txt['search'], '" class="right_submit" />
					<input type="button" value="', $txt['find_close'], '" onclick="window.close();" class="right_submit" />
				</div>
			</div>
			<br />
			<div class="roundframe">
				<h3 class="category_header">', $txt['find_results'], '</h3>';

	if (empty($context['results']))
		echo '
				<p class="error">', $txt['find_no_results'], '</p>';
	else
	{
		echo '
				<ul>';

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