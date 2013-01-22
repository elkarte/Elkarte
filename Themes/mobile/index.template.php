<?php
/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha

	This template is, perhaps, the most important template in the theme. It
	contains the main template layer that displays the header and footer of
	the forum, namely with main_above and main_below. It also contains the
	menu sub template, which appropriately displays the menu; the init sub
	template, which is there to set the theme up; (init can be missing.) and
	the linktree sub template, which sorts out the link tree.

	The init sub template should load any data and set any hardcoded options.

	The main_above sub template is what is shown above the main content, and
	should contain anything that should be shown up there.

	The main_below sub template, conversely, is shown after the main content.
	It should probably contain the copyright statement and some other things.

	The linktree sub template should display the link tree, using the data
	in the $context['linktree'] variable.

	The menu sub template should display all the relevant buttons the user
	wants and or needs.

	For more information on the templating system, please see the site at:
	http://www.simplemachines.org/
*/

/**
 * Initialize the template... mainly little settings.
 */
function template_init()
{
	global $context, $settings, $options, $txt;

	/* Use images from default theme when using templates from the default theme?
		if this is 'always', images from the default theme will be used.
		if this is 'defaults', images from the default theme will only be used with default templates.
		if this is 'never' or isn't set at all, images from the default theme will not be used. */
	$settings['use_default_images'] = 'never';

	// The version this template/theme is for. This should probably be the version of SMF it was created for.
	$settings['theme_version'] = '2.1 Alpha 1';

	// Set the following variable to true if this theme requires the optional theme strings file to be loaded.
	$settings['require_theme_strings'] = true;
}

/**
 * The main sub template above the content.
 */
function template_html_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Show right to left and the character set for ease of translating.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
<head>';

	echo '
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta charset="', $context['character_set'], '" />
	<meta name="description" content="', $context['page_title_html_safe'], '" />', !empty($context['meta_keywords']) ? '
	<meta name="keywords" content="' . $context['meta_keywords'] . '" />' : '', '
	<title>', $context['page_title_html_safe'], '</title>';

	// Please don't index these Mr Robot.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex" />';
	
	// Jquery Librarys
	if (isset($modSettings['jquery_source']) && $modSettings['jquery_source'] == 'cdn')
		echo '
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
	<script src="http://code.jquery.com/mobile/1.2.0/jquery.mobile-1.2.0.min.js"></script>';
	elseif (isset($modSettings['jquery_source']) && $modSettings['jquery_source'] == 'local')
		echo '
	<script src="', $settings['theme_url'], '/scripts/jquery-1.7.2.min.js"></script>
	<script src="', $settings['theme_url'], '/scripts/jquery.mobile-1.2.0.min.js"></script>';
	else
		echo '
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
	<script src="http://code.jquery.com/mobile/1.2.0/jquery.mobile-1.2.0.min.js"></script>';
	
	// Here comes the JavaScript bits!
	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
		// We cannot allow jquery mobile to hijack the hash in our forum urls.
		$.mobile.hashListeningEnabled = false;
		// We need the browser back button to work, so turn off page ajax
		$.mobile.ajaxEnabled = false;
		var smf_theme_url = "', $settings['theme_url'], '";
		var smf_default_theme_url = "', $settings['default_theme_url'], '";
		var smf_images_url = "', $settings['images_url'], '";
		var smf_scripturl = "', $scripturl, '";
		var smf_iso_case_folding = ', $context['server']['iso_case_folding'] ? 'true' : 'false', ';
		var smf_charset = "', $context['character_set'], '";
		var smf_session_id = "', $context['session_id'], '";
		var smf_session_var = "', $context['session_var'], '";
		var smf_member_id = "', $context['user']['id'], '";', $context['show_pm_popup'] ? '
		var fPmPopup = function ()
		{
			if (confirm("' . $txt['show_personal_messages'] . '"))
				window.open(smf_prepareScriptUrl(smf_scripturl) + "action=pm");
		}
		addLoadEvent(fPmPopup);' : '', '
		var ajax_notification_text = "', $txt['ajax_in_progress'], '";
		var ajax_notification_cancel_text = "', $txt['modify_cancel'], '";
	// ]]></script>';

	// Jquery Mobile CSS file
	echo '
	<link rel="stylesheet" href="', $settings['theme_url'], '/css/jquery.mobile-1.2.0.min.css" />';
	
	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '" />';

	// Show all the relative links, such as help, search, contents, and the like.
	echo '
	<link rel="help" href="', $scripturl, '?action=help" />
	<link rel="contents" href="', $scripturl, '" />', ($context['allow_search'] ? '
	<link rel="search" href="' . $scripturl . '?action=search" />' : '');

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		echo '
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['rss'], '" href="', $scripturl, '?type=rss2;action=.xml" />
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['atom'], '" href="', $scripturl, '?type=atom;action=.xml" />';

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty($context['current_topic']))
		echo '
	<link rel="prev" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=prev" />
	<link rel="next" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=next" />';

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty($context['current_board']))
		echo '
	<link rel="index" href="', $scripturl, '?board=', $context['current_board'], '.0" />';

	echo '
</head>
<body>';
}

function template_body_above()
{
	global $context, $scripturl, $txt;
	
	echo '
	<div data-role="page" id="the-page">
		<div data-role="header" class="ui-bar ui-bar-b">
			<a data-iconpos="notext" data-mini="true" data-role="button" data-icon="search" href="', $scripturl, '?action=search">', $txt['search'], '</a>
			<h1><a href="', $scripturl, '">', empty($context['header_logo_url_html_safe']) ? $context['forum_name'] : $context['page_title'], '</a></h1>
		</div>';
		
		template_menu();
		
		echo '
		<div data-role="content">';
		
		//-- Main content goes here --
}

function template_body_below()
{
	global $context;
	
	echo '
		</div>';

	// Page index is going here. We need to strip out some characters too.
	if (isset($context['page_index']))
		echo '
			<div class="ui-bar-c pageindex">
				<div class="pagenav">
					', str_replace(array('[', ']'), '', !empty($context['page_index']) ? $context['page_index'] : ''), '
				</div>
			</div>';
	// Show the copyright.
	echo '
		<div data-role="footer" data-theme="b" data-position="fixed" id="footer">
			<a data-iconpos="notext" data-mini="true" data-role="button" data-rel="back" data-icon="back" href="">Back</a>
			<p>', theme_copyright(), '</p>
		</div>	
	</div>';
}

function template_html_below()
{
	echo '
</body></html>';
}

/**
 * Show a linktree. This is that thing that shows "My Community | General Category | General Discussion"..
 * @param bool $force_show = false
 */
function theme_linktree($force_show = false)
{
	global $context, $settings, $options, $shown_linktree;

	// If linktree is empty, just return - also allow an override.
	if (empty($context['linktree']) || (!empty($context['dont_default_linktree']) && !$force_show))
		return;

	echo '
	<div class="navigate_section">
		<ul>';

	// Each tree item has a URL and name. Some may have extra_before and extra_after.
	foreach ($context['linktree'] as $link_num => $tree)
	{
		echo '
			<li', ($link_num == count($context['linktree']) - 1) ? ' class="last"' : '', '>';

		// Show something before the link?
		if (isset($tree['extra_before']))
			echo $tree['extra_before'];

		// Show the link, including a URL if it should have one.
		echo $settings['linktree_link'] && isset($tree['url']) ? '
				<a href="' . $tree['url'] . '"><span>' . $tree['name'] . '</span></a>' : '<span>' . $tree['name'] . '</span>';

		// Show something after the link...?
		if (isset($tree['extra_after']))
			echo $tree['extra_after'];

		// Don't show a separator for the last one.
		if ($link_num != count($context['linktree']) - 1)
			echo ' &#187;';

		echo '
			</li>';
	}
	echo '
		</ul>
	</div>';

	$shown_linktree = true;
}

/**
 * Show the main menu. Only the items we need for mobile
 */
function template_menu()
{
	global $context, $txt;
	
	$allowedItems = array('home', 'pm', 'profile', 'register', 'login', 'logout');

	echo '
	<div data-role="header" data-position="fixed">
		<div data-role="navbar">
			<ul>';
			
	foreach ($context['menu_buttons'] as $id => $item)
	{
		if ($id == 'pm')
			$item['title'] = $txt['mobile_pm']. ' <span>('. $context['user']['unread_messages'] . ')</span>';
			
		if (in_array($id, $allowedItems))
			echo '
				<li data-wrapperels="div"><a data-theme="d" href="', $item['href'], '"', $item['active_button'] ? ' class="ui-btn-active"' : '', '>', $item['title'], '</a></li>';
	}
	echo '
			</ul>
		</div>
	</div>';
}

/**
 * Generate a strip of buttons.
 * @param array $button_strip
 * @param string $direction = ''
 * @param array $strip_options = array()
 */
function template_button_strip($button_strip, $direction = '', $strip_options = array())
{
	global $settings, $context, $txt, $scripturl;

	if (!is_array($strip_options))
		$strip_options = array();

	// List the buttons in reverse order for RTL languages.
	if ($context['right_to_left'])
		$button_strip = array_reverse($button_strip, true);

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
	{
		if (!isset($value['test']) || !empty($context[$value['test']]))
			$buttons[] = '
				<li><a' . (isset($value['id']) ? ' id="button_strip_' . $value['id'] . '"' : '') . ' class="button_strip_' . $key . (isset($value['active']) ? ' active' : '') . '" href="' . $value['url'] . '"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '><span>' . $txt[$value['text']] . '</span></a></li>';
	}

	// No buttons? No button strip either.
	if (empty($buttons))
		return;

	// Make the last one, as easy as possible.
	$buttons[count($buttons) - 1] = str_replace('<span>', '<span class="last">', $buttons[count($buttons) - 1]);

	echo '
		<div class="buttonlist', !empty($direction) ? ' float' . $direction : '', '"', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"': ''), '>
			<ul>',
				implode('', $buttons), '
			</ul>
		</div>';
}


?>