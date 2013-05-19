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
 * Template for the help popup page
 */
function template_popup()
{
	global $context, $settings, $txt;

	// Since this is a popup of its own we need to start the html, etc.
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta name="robots" content="noindex" />
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css?alp21" />
		<script src="', $settings['default_theme_url'], '/scripts/script.js"></script>
	</head>
	<body id="help_popup">
		<div class="windowbg description">
			', $context['help_text'], '<br />
			<br />
			<a href="javascript:self.close();">', $txt['close_window'], '</a>
		</div>
	</body>
</html>';
}

/**
 * The main help page.
 */
function template_manual()
{
	global $context, $scripturl, $txt;

	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['manual_elkarte_user_help'], '</h3>
			</div>
			<div id="help_container">
				<div id="helpmain" class="windowbg2 content">
					<p>', sprintf($txt['manual_welcome'], $context['forum_name']), '</p>
					<p>', $txt['manual_introduction'], '</p>
					<ul>';

	foreach ($context['manual_sections'] as $section_id => $wiki_id)
	{
		echo '
						<li><a href="', $context['wiki_url'], '/', $wiki_id, ($txt['lang_dictionary'] != 'en' ? '/' . $txt['lang_dictionary'] : ''), '" target="_blank" class="new_win">', $txt['manual_section_' . $section_id . '_title'], '</a> - ', $txt['manual_section_' . $section_id . '_desc'], '</li>';
	}

	echo '
					</ul>
					<p>', sprintf($txt['manual_docs_and_credits'], $context['wiki_url'], $scripturl . '?action=who;sa=credits'), '</p>
				</div>
			</div>';
}