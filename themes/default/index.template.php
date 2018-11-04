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
 * @version 2.0 dev
 *
 */

/**
 * This template is, perhaps, the most important template in the theme. It
 * contains the main template layer that displays the header and footer of
 * the forum, namely with body_above and body_below. It also contains the
 * menu sub template, which appropriately displays the menu; the init sub
 * template, which is there to set the theme up; (init can be missing.) and
 * the linktree sub template, which sorts out the link tree.
 *
 * The init sub template should load any data and set any hardcoded options.
 *
 * The body_above sub template is what is shown above the main content, and
 * should contain anything that should be shown up there.
 *
 * The body_below sub template, conversely, is shown after the main content.
 * It should probably contain the copyright statement and some other things.
 *
 * The linktree sub template should display the link tree, using the data
 * in the $context['linktree'] variable.
 *
 * The menu sub template should display all the relevant buttons the user
 * wants and or needs.
 */

/**
 * Simplify the use of callbacks in the templates.
 *
 * @param string $id - A prefix for the template functions the final name
 *                     should look like: template_{$id}_{$array[n]}
 * @param string[] $array - The array of function suffixes
 */
function call_template_callbacks($id, $array)
{
	if (empty($array))
		return;

	foreach ($array as $callback)
	{
		$func = 'template_' . $id . '_' . $callback;
		if (function_exists($func))
			$func();
	}
}

/**
 * The main sub template above the content.
 */
function template_html_above()
{
	global $context, $scripturl, $txt, $modSettings;

	// Show right to left and the character set for ease of translating.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
<head>
	<title>', $context['page_title_html_safe'], '</title>
	<meta charset="UTF-8" />';

	// Tell IE to render the page in standards not compatibility mode. really for ie >= 8
	// Note if this is not in the first 4k, its ignored, that's why its here
	if (isBrowser('ie'))
		echo '
	<meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1" />';

	echo '
	<meta name="viewport" content="width=device-width" />
	<meta name="mobile-web-app-capable" content="yes" />
	<meta name="description" content="', $context['page_title_html_safe'], '" />';

	// OpenID enabled? Advertise the location of our endpoint using YADIS protocol.
	if (!empty($modSettings['enableOpenID']))
		echo '
	<meta http-equiv="x-xrds-location" content="' . $scripturl . '?action=xrds" />';

	// Please don't index these Mr Robot.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex" />';

	// load in any css from addons or themes so they can overwrite if wanted
	template_css();

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '" />';

	// Show all the relative links, such as help, search, contents, and the like.
	echo '
	<link rel="shortcut icon" sizes="196x196" href="' . $context['favicon'] . '" />
	<link rel="help" href="', getUrl('action', ['action' => 'help']), '" />
	<link rel="contents" href="', $scripturl, '" />', ($context['allow_search'] ? '
	<link rel="search" href="' . getUrl('action', ['action' => 'search']) . '" />' : '');

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty($context['newsfeed_urls']))
	{
		echo '
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['rss'], '" href="', $context['newsfeed_urls']['rss'], '" />
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['atom'], '" href="', $context['newsfeed_urls']['atom'], '" />';
	}

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty($context['links']['next']))
	{
		echo '
	<link rel="next" href="', $context['links']['next'], '" />';
	}
	elseif (!empty($context['current_topic']))
	{
		echo '
	<link rel="next" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=next" />';
	}

	if (!empty($context['links']['prev']))
	{
		echo '
	<link rel="prev" href="', $context['links']['prev'], '" />';
	}
	elseif (!empty($context['current_topic']))
	{
		echo '
	<link rel="prev" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=prev" />';
	}

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty($context['current_board']))
	{
		echo '
	<link rel="index" href="', $scripturl, '?board=', $context['current_board'], '.0" />';
	}

	// load in any javascript files from addons and themes
	theme()->template_javascript();

	// load in any javascript files from addons and themes
	theme()->template_inlinecss();

	// Output any remaining HTML headers. (from addons, maybe?)
	echo $context['html_headers'];

	echo '
</head>
<body id="', $context['browser_body_id'], '" class="action_', !empty($context['current_action']) ? htmlspecialchars($context['current_action'], ENT_COMPAT, 'UTF-8') : (!empty($context['current_board']) ?
	'messageindex' : (!empty($context['current_topic']) ? 'display' : 'home')),
	!empty($context['current_board']) ? ' board_' . htmlspecialchars($context['current_board'], ENT_COMPAT, 'UTF-8') : '', '">';
}

/**
 * Section above the main contents of the page, after opening the body tag
 */
function template_body_above()
{
	global $context, $settings, $txt;

	// Go to top/bottom of page links and skipnav link for a11y.
	echo '
	<a id="top" href="#skipnav" tabindex="0">', $txt['skip_nav'], '</a>
	<a href="#top" id="gotop" title="', $txt['go_up'], '">&#8593;</a>
	<a href="#bot" id="gobottom" title="', $txt['go_down'], '">&#8595;</a>';

	// Skip nav link.
	echo '
	<header id="top_section">
		<aside class="wrapper">';

	call_template_callbacks('th', $context['theme_header_callbacks']);

	echo '
		</aside>
		<section id="header" class="wrapper', !empty($settings['header_layout']) ? ($settings['header_layout'] == 1 ? ' centerheader' : ' rightheader') : '', empty($context['minmax_preferences']['upshrink']) ? '"' : ' hide" aria-hidden="true"', '>
			<h1 id="forumtitle">
				<a class="forumlink" href="', getUrl('boardindex', []), '">', $context['forum_name'], '</a>';

	echo '
				<span id="logobox">
					<img id="logo" src="', $context['header_logo_url_html_safe'], '" alt="', $context['forum_name_html_safe'], '" title="', $context['forum_name_html_safe'], '" />', empty($settings['site_slogan']) ? '' : '
					<span id="siteslogan">' . $settings['site_slogan'] . '</span>', '
				</span>
			</h1>';

	// Show the menu here, according to the menu sub template.
	echo '
		</section>';

	template_menu();

	echo '
	</header>
	<div id="wrapper" class="wrapper">
		<aside id="upper_section"', empty($context['minmax_preferences']['upshrink']) ? '' : ' class="hide" aria-hidden="true"', '>';

	call_template_callbacks('uc', $context['upper_content_callbacks']);

	echo '
		</aside>';

	// Show the navigation tree.
	theme_linktree();

	// The main content should go here.
	echo '
		<div id="main_content_section"><a id="skipnav"></a>';
}

/**
 * If the user is logged in, display the time, or a maintenance warning for admins.
 * @todo - TBH I always intended the time/date to be more or less a place holder for more important things.
 * The maintenance mode warning for admins is an obvious one, but this could also be used for moderation notifications.
 * I also assumed this would be an obvious place for sites to put a string of icons to link to their FB, Twitter, etc.
 * This could still be done via conditional, so that administration and moderation notices were still active when
 * applicable.
 */
function template_th_login_bar()
{
	global $context, $modSettings, $txt, $scripturl;

	echo '
			<div id="top_section_notice" class="user">
				<form action="', $scripturl, '?action=login2;quicklogin" method="post" accept-charset="UTF-8" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
					<div id="password_login">
						<input type="text" name="user" size="10" class="input_text" placeholder="', $txt['username'], '" />
						<input type="password" name="passwrd" size="10" class="input_password" placeholder="', $txt['password'], '" />
						<select name="cookielength">
							<option value="60">', $txt['one_hour'], '</option>
							<option value="1440">', $txt['one_day'], '</option>
							<option value="10080">', $txt['one_week'], '</option>
							<option value="43200">', $txt['one_month'], '</option>
							<option value="-1" selected="selected">', $txt['forever'], '</option>
						</select>
						<input type="submit" value="', $txt['login'], '" />
						<input type="hidden" name="hash_passwrd" value="" />
						<input type="hidden" name="old_hash_passwrd" value="" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />';

	if (!empty($modSettings['enableOpenID']))
		echo '
						<a class="icon icon-margin i-openid" href="', $scripturl, '?action=login;openid" title="' . $txt['openid'] . '"><s>' . $txt['openid'] . '"</s></a>';
	echo '
					</div>
				</form>
			</div>';
}

/**
 * A simple search bar (used in the header)
 */
function template_th_search_bar()
{
	global $context, $modSettings, $txt;

	echo '
			<form id="search_form" action="', getUrl('action', ['action' => 'search', 'sa' => 'results']), '" method="post" accept-charset="UTF-8">
				<label for="quicksearch">
					<input type="text" name="search" id="quicksearch" value="" class="input_text" placeholder="', $txt['search'], '" />
				</label>';

	// Using the quick search dropdown?
	if (!empty($modSettings['search_dropdown']))
	{
		$selected = !empty($context['current_topic']) ? 'current_topic' : (!empty($context['current_board']) ? 'current_board' : 'all');

		echo '
				<label for="search_selection">
				<select name="search_selection" id="search_selection">
					<option value="all"', ($selected == 'all' ? ' selected="selected"' : ''), '>', $txt['search_entireforum'], ' </option>';

		// Can't limit it to a specific topic if we are not in one
		if (!empty($context['current_topic']))
			echo '
					<option value="topic"', ($selected == 'current_topic' ? ' selected="selected"' : ''), '>', $txt['search_thistopic'], '</option>';

		// Can't limit it to a specific board if we are not in one
		if (!empty($context['current_board']))
			echo '
					<option value="board"', ($selected == 'current_board' ? ' selected="selected"' : ''), '>', $txt['search_thisbrd'], '</option>';

		if (!empty($context['additional_dropdown_search']))
			foreach ($context['additional_dropdown_search'] as $name => $engine)
				echo '
					<option value="', $name, '">', $engine['name'], '</option>';

		echo '
					<option value="members"', ($selected == 'members' ? ' selected="selected"' : ''), '>', $txt['search_members'], ' </option>
				</select>
				</label>';
	}

	// Search within current topic?
	if (!empty($context['current_topic']))
		echo '
				<input type="hidden" name="', (!empty($modSettings['search_dropdown']) ? 'sd_topic' : 'topic'), '" value="', $context['current_topic'], '" />';
	// If we're on a certain board, limit it to this board ;).
	if (!empty($context['current_board']))
		echo '
				<input type="hidden" name="', (!empty($modSettings['search_dropdown']) ? 'sd_brd[' : 'brd['), $context['current_board'], ']"', ' value="', $context['current_board'], '" />';

	echo '
				<button type="submit" name="search;sa=results" class="', (!empty($modSettings['search_dropdown'])) ? 'with_select' : '', '"><i class="icon i-search icon-shade"></i></button>
				<input type="hidden" name="advanced" value="0" />
			</form>';
}

/**
 * The news fader wrapped in a div and with "news" text
 */
function template_uc_news_fader()
{
	global $settings, $context, $txt;

	// Display either news fader and random news lines (not both). These now run most of the same mark up and CSS. Less complication = happier n00bz. :)
	if (!empty($settings['enable_news']) && !empty($context['random_news_line']))
	{
		echo '
			<div id="news">
				<h2>', $txt['news'], '</h2>';

		template_news_fader();

		echo '
			</div>';
	}
}

/**
 * Section down the page, before closing body
 */
function template_body_below()
{
	global $context, $txt;

	echo '
		</div>
	</div>';

	// Show RSS link, as well as the copyright.
	// Footer is full-width. Wrapper inside automatically matches admin width setting.
	echo '
	<footer id="footer_section"><a id="bot"></a>
		<div class="wrapper">
			<ul>
				<li class="copyright">',
					theme_copyright(), '
				</li>',
				!empty($context['newsfeed_urls']['rss']) ? '<li>
					<a id="button_rss" href="' . $context['newsfeed_urls']['rss'] . '" class="rssfeeds new_win"><i class="icon icon-margin i-rss icon-big"><s>' . $txt['rss'] . '</s></i></a>
				</li>' : '',
			'</ul>';

	// Show the load time?
	if ($context['show_load_time'])
		echo '
			<p>', sprintf($txt['page_created_full'], $context['load_time'], $context['load_queries']), '</p>';
}

/**
 * Section down the page, at closing html tag
 */
function template_html_below()
{
	global $context;

	echo '
		</div>
	</footer>';

	// load in any javascript that could be deferred to the end of the page
	theme()->template_javascript(true);

	// Anything special to put out?
	if (!empty($context['insert_after_template']))
		echo $context['insert_after_template'];

	echo '
</body>
</html>';
}

/**
 * Show a linktree. This is that thing that shows
 * "My Community | General Category | General Discussion"..
 *
 * @param string $default a string representing the index in $context where
 *               the linktree is stored (default value is 'linktree')
 */
function theme_linktree($default = 'linktree')
{
	global $context, $settings, $txt;

	// If linktree is empty, just return - also allow an override.
	if (empty($context[$default]))
		return;

	// @todo - Look at changing markup here slightly. Need to incorporate relevant aria roles.
	echo '
			<nav>
				<ul class="navigate_section">';

	// Each tree item has a URL and name. Some may have extra_before and extra_after.
	// Added a linktree class to make targeting dividers easy.
	foreach ($context[$default] as $pos => $tree)
	{
		echo '
					<li class="linktree">
						<span>';

		// Dividers moved to pseudo-elements in CSS.
		// Show something before the link?
		if (isset($tree['extra_before']))
			echo $tree['extra_before'];

		// Show the link, including a URL if it should have one.
		echo $settings['linktree_link'] && isset($tree['url']) ? '<a href="' . $tree['url'] . '">' . ($pos == 0 ? '<i class="icon i-home"><s>' . $txt['home'] . '</s></i>' : $tree['name']) . '</a>' : $tree['name'];

		// Show something after the link...?
		if (isset($tree['extra_after']))
			echo $tree['extra_after'];

		echo '
						</span>
					</li>';
	}

	echo '
				</ul>
			</nav>';
}

/**
 * Show the menu up top. Something like [home] [help] [profile] [logout]...
 */
function template_menu()
{
	global $context, $txt;

	// WAI-ARIA a11y tweaks have been applied here.
	echo '
				<nav id="menu_nav">
					<ul id="main_menu" class="wrapper" role="menubar">';

	// The upshrink image, right-floated.
	echo '
						<li id="collapse_button" class="listlevel1">
							<a class="linklevel1 panel_toggle">
								<i id="upshrink" class="hide chevricon i-chevron-up icon icon-lg" title="', $txt['upshrink_description'], '"></i>
							</a>
						</li>';

	foreach ($context['menu_buttons'] as $act => $button)
	{
		echo '
						<li id="button_', $act, '" class="listlevel1', !empty($button['sub_buttons']) ? ' subsections" aria-haspopup="true"' : '"', '>
							<a class="linklevel1', !empty($button['active_button']) ? ' active' : '', (!empty($button['indicator']) ? ' indicator' : ''), '" href="', $button['href'], '" ', isset($button['target']) ? 'target="' . $button['target'] . '"' : '', '>', (!empty($button['data-icon']) ? '<i class="icon icon-menu icon-lg ' . $button['data-icon'] . '" title="' . (!empty($button['alttitle']) ? $button['alttitle'] : $button['title']) . '"></i> ' : ''), '<span class="button_title" aria-hidden="true">', $button['title'], '</span></a>';

		// Any 2nd level menus?
		if (!empty($button['sub_buttons']))
		{
			echo '
							<ul class="menulevel2" role="menu">';

			foreach ($button['sub_buttons'] as $childact => $childbutton)
			{
				echo '
								<li id="button_', $childact, '" class="listlevel2', !empty($childbutton['sub_buttons']) ? ' subsections" aria-haspopup="true"' : '"', '>
									<a class="linklevel2" href="', $childbutton['href'], '" ', isset($childbutton['target']) ? 'target="' . $childbutton['target'] . '"' : '', '>', $childbutton['title'], '</a>';

				// 3rd level menus :)
				if (!empty($childbutton['sub_buttons']))
				{
					echo '
									<ul class="menulevel3" role="menu">';

					foreach ($childbutton['sub_buttons'] as $grandchildact => $grandchildbutton)
						echo '
										<li id="button_', $grandchildact, '" class="listlevel3">
											<a class="linklevel3" href="', $grandchildbutton['href'], '" ', isset($grandchildbutton['target']) ? 'target="' . $grandchildbutton['target'] . '"' : '', '>', $grandchildbutton['title'], '</a>
										</li>';

					echo '
									</ul>';
				}

				echo '
								</li>';
			}

			echo '
							</ul>';
		}

		echo '
						</li>';
	}

	echo '
					</ul>
				</nav>';

	// Define the upper_section toggle in javascript.
	theme()->addInlineJavascript('
					var oMainHeaderToggle = new elk_Toggle({
						bToggleEnabled: true,
						bCurrentlyCollapsed: ' . (empty($context['minmax_preferences']['upshrink']) ? 'false' : 'true') . ',
						aSwappableContainers: [
							\'upper_section\',\'header\'
						],
						aSwapClasses: [
							{
								sId: \'upshrink\',
								classExpanded: \'chevricon i-chevron-up icon-lg\',
								titleExpanded: ' . JavaScriptEscape($txt['upshrink_description']) . ',
								classCollapsed: \'chevricon i-chevron-down icon-lg\',
								titleCollapsed: ' . JavaScriptEscape($txt['upshrink_description']) . '
							}
						],
						oThemeOptions: {
							bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
							sOptionName: \'minmax_preferences\',
							sSessionId: elk_session_id,
							sSessionVar: elk_session_var,
							sAdditionalVars: \';minmax_key=upshrink\'
						},
						oCookieOptions: {
							bUseCookie: elk_member_id == 0 ? true : false,
							sCookieName: \'upshrink\'
						}
					});
				', true);
}

/**
 * Generate a strip of buttons.
 *
 * @param mixed[] $button_strip
 * @param string $direction = ''
 * @param string[] $strip_options = array()
 *
 * @return string as echoed content
 */
function template_button_strip($button_strip, $direction = '', $strip_options = array())
{
	global $context, $txt;

	// Not sure if this can happen, but people can misuse functions very efficiently
	if (empty($button_strip))
		return '';

	if (!is_array($strip_options))
		$strip_options = array();

	// List the buttons in reverse order for RTL languages.
	if ($context['right_to_left'])
		$button_strip = array_reverse($button_strip, true);

	// Create the buttons... now with cleaner markup (yay!).
	$buttons = array();
	foreach ($button_strip as $key => $value)
	{
		if (!isset($value['test']) || !empty($context[$value['test']]))
			$buttons[] = '
								<li role="menuitem"><a' . (isset($value['id']) ? ' id="button_strip_' . $value['id'] . '"' : '') . ' class="linklevel1 button_strip_' . $key . (!empty($value['active']) ? ' active' : '') . '" href="' . $value['url'] . '"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $txt[$value['text']] . '</a></li>';
	}

	// No buttons? No button strip either.
	if (empty($buttons))
		return '';

	echo '
							<ul role="menubar" class="buttonlist', !empty($direction) ? ' float' . $direction : '', (empty($buttons) ? ' hide"' : '"'), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"' : ''), '>
								', implode('', $buttons), '
							</ul>';
}

/**
 * Generate a strip of "quick" buttons (those present next to each message)
 *
 * What it does:
 *
 * - Create a quick button, pass an array of the button name with key values
 * - array('somename' => array(href => '' text => '' custom => '' test => ''))
 *      - href => link to call when button is pressed
 *      - text => text to display in the button
 *      - custom => custom action to perform, generally used to add 'onclick' events (optional)
 *      - test => key to check in the $tests array before showing the button (optional)
 * 		- override => full and complete <li></li> to use for the button
 * - checkboxes can be shown as well as buttons,
 *		- use array('check' => array(checkbox => (true | always), name => value =>)
 *      - if true follows show moderation as checkbox setting, always will always show
 *      - name => name of the checkbox array, like delete, will have [] added for the form
 *      - value => value for the checkbox to return in the post
 *
 * @param array $strip - the $context index where the strip is stored
 * @param bool[] $tests - an array of tests to determine if the button should be displayed or not
 * @return string of buttons
 */
function template_quickbutton_strip($strip, $tests = array())
{
	global $options;

	$buttons = array();

	foreach ($strip as $key => $value)
	{
		if (isset($value['checkbox']))
		{
			if (!empty($value['checkbox']) && ((!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1) || $value['checkbox'] === 'always'))
			{
				$buttons[] = '
						<li class="listlevel1 ' . $key . '">
							<input class="input_check ' . $key . '_check" type="checkbox" name="' . $value['name'] . '[]" value="' . $value['value'] . '" />
						</li>';
			}
		}
		elseif (!isset($value['test']) || !empty($tests[$value['test']]))
		{
			if (!empty($value['override']))
			{
				$buttons[] = $value['override'];
			}
			else
			{
				$buttons[] = '
						<li class="listlevel1">
							<a href="' . $value['href'] . '" class="linklevel1 ' . $key . '_button"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $value['text'] . '</a>
						</li>';
			}
		}
	}

	// No buttons? No button strip either.
	if (empty($buttons))
	{
		return '';
	}

	echo '
					<ul class="quickbuttons">', implode('
						', $buttons), '
					</ul>';
}

/**
 * Very simple and basic template to display a legend explaining the meaning
 * of some icons used in the messages listing (locked, sticky, etc.)
 */
function template_basicicons_legend()
{
	global $context, $modSettings, $txt;

	echo '
		<p class="floatleft">', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
			<span class="topicicon i-profile"></span> ' . $txt['participation_caption'] : '<span class="topicicon img_normal"> </span>' . $txt['normal_topic'], '<br />
			' . (!empty($modSettings['pollMode']) ? '<span class="topicicon i-poll"> </span>' . $txt['poll'] : '') . '
		</p>
		<p>
			<span class="topicicon i-locked"> </span>' . $txt['locked_topic'] . '<br />
			<span class="topicicon i-sticky"> </span>' . $txt['sticky_topic'] . '<br />
		</p>';
}

/**
 * Show a box with a message, mostly used to show errors, but can be used to show
 * success as well
 *
 * Looks for the display information in the $context[$error_id] array
 * Keys of array are 'type'
 *  - empty or success for successbox
 *  - serious for error box
 *  - warning for warning box
 * 'title' - optional value to place above list
 * 'errors' - array of text strings to display in the box
 *
 * @param string $error_id
 */
function template_show_error($error_id)
{
	global $context;

	if (empty($error_id))
		return;

	$error = isset($context[$error_id]) ? $context[$error_id] : array();

	echo '
					<div id="', $error_id, '" class="', (isset($error['type']) ? ($error['type'] === 'serious' ? 'errorbox' : 'warningbox') : 'successbox'), empty($error['errors']) ? ' hide"' : '"', '>';

	// Optional title for our results
	if (!empty($error['title']))
		echo '
						<dl>
							<dt>
								<strong id="', $error_id, '_title">', $error['title'], '</strong>
							</dt>
							<dd>';

	// Everything that went wrong, or correctly :)
	if (!empty($error['errors']))
	{
		echo '
								<ul', (isset($error['type']) ? ' class="error"' : ''), ' id="', $error_id, '_list">';

		foreach ($error['errors'] as $key => $err)
			echo '
									<li id="', $error_id, '_', $key, '">', $err, '</li>';
		echo '
								</ul>';
	}

	// All done
	if (!empty($error['title']))
		echo '
							</dd>
						</dl>';

	echo '
					</div>';
}

/**
 * Another used and abused piece of template that can be found everywhere
 *
 * @param string|boolean $button_strip index of $context to create the button strip
 * @param string $strip_direction direction of the button strip (see template_button_strip for details)
 * @param array $options array of optional values, possible values:
 *     - 'page_index' (string) index of $context where is located the pages index generated by constructPageIndex
 *     - 'page_index_markup' (string) markup for the page index, overrides 'page_index' and can be used if
 *        the page index code is not in the first level of $context
 *     - 'extra' (string) used to add html markup at the end of the template
 *
 * @return string as echoed content
 */
function template_pagesection($button_strip = false, $strip_direction = '', $options = array())
{
	global $context;

	// Hmmm. I'm a tad wary of having floatleft here but anyway............
	// @todo - Try using table-cell display here. Should do auto rtl support. Less markup, less css. :)
	if (!empty($options['page_index_markup']))
		$pages = '<ul ' . (isset($options['page_index_id']) ? 'id="' . $options['page_index_id'] . '" ' : '') . 'class="pagelinks floatleft" role="menubar">' . $options['page_index_markup'] . '</ul>';
	else
	{
		if (!isset($options['page_index']))
			$options['page_index'] = 'page_index';
		$pages = empty($context[$options['page_index']]) ? '' : '<ul ' . (isset($options['page_index_id']) ? 'id="' . $options['page_index_id'] . '" ' : '') . 'class="pagelinks floatleft" role="menubar">' . $context[$options['page_index']] . '</ul>';
	}

	if (!isset($options['extra']))
		$options['extra'] = '';

	echo '
			<nav class="pagesection">
				', $pages, '
				', !empty($button_strip) && !empty($context[$button_strip]) ? template_button_strip($context[$button_strip], $strip_direction) : '',
	$options['extra'], '
			</nav>';
}

/**
 * This is the news fader
 */
function template_news_fader()
{
	global $settings, $context;

	echo '
		<ul id="elkFadeScroller">
			<li>
				', $settings['enable_news'] == 2 ? implode('</li><li>', $context['news_lines']) : $context['random_news_line'], '
			</li>
		</ul>';

	theme()->addInlineJavascript('
		$(\'#elkFadeScroller\').Elk_NewsFader(' . (empty($settings['newsfader_time']) ? '' : '{\'iFadeDelay\': ' . $settings['newsfader_time'] . '}') . ');', true);
}

/**
 *
 * @TODO: These need to be moved somewhere appropriate >_>
 *
 * @param array $member
 * @param bool $link
 *
 * @return string
 */
function template_member_online($member, $link = true)
{
	global $context;

	return ((!empty($context['can_send_pm']) && $link) ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['text'] . '">' : '') .
		   '<i class="' . ($member['online']['is_online'] ? 'iconline' : 'icoffline') . '" title="' . $member['online']['text'] . '"></i>' .
		   ((!empty($context['can_send_pm']) && $link) ? '</a>' : '');
}

/**
 * Similar to the above. Wanted to centralize this to make it easier to pull out the emailuser action and replace with
 * a mailto: href, which many sane board admins would prefer.
 *
 * @param array $member
 * @param bool  $text
 *
 * @return string
 */
function template_member_email($member, $text = false)
{
	global $context, $txt, $scripturl;

	if ($context['can_send_email'])
	{
		if ($text)
		{
			if ($member['show_email'] === 'no_through_forum')
			{
				return '<a class="linkbutton" href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '">' . $txt['email'] . '</a>';
			}
			elseif ($member['show_email'] === 'yes_permission_override' || $member['show_email'] === 'yes')
			{
				return '<a class="linkbutton" href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '">' . $member['email'] . '</a>';
			}
			else
			{
				return $txt['hidden'];
			}
		}
		else
		{
			if ($member['show_email'] !== 'no')
			{
				return '<a href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '" class="icon i-envelope-o' . ($member['online']['is_online'] ? '' : '-blank') . '" title="' . $txt['email'] . ' ' . $member['name'] . '"><s>' . $txt['email'] . ' ' . $member['name'] . '</s></a>';
			}
			else
			{
				return '<i class="icon i-envelope-o" title="' . $txt['email'] . ' ' . $txt['hidden'] . '"><s>' . $txt['email'] . ' ' . $txt['hidden'] . '</s></i>';
			}
		}
	}

	return '';
}

/**
 * Sometimes we only get a message id.
 *
 * @param      $id
 * @param bool $member
 *
 * @return string
 */
function template_msg_email($id, $member = false)
{
	global $context, $txt, $scripturl;

	if ($context['can_send_email'])
	{
		if ($member === false || $member['show_email'] != 'no')
		{
			return '<a href="' . $scripturl . '?action=emailuser;sa=email;msg=' . $id . '" class="icon i-envelope-o' . (($member !== false && $member['online']['is_online']) ? '' : '-blank') . '" title="' . $txt['email'] . '"><s>' . $txt['email'] . '</s></a>';
		}
		else
		{
			return '<i class="icon i-envelope-o" title="' . $txt['email'] . ' ' . $txt['hidden'] . '"><s>' . $txt['email'] . ' ' . $txt['hidden'] . '</s></i>';
		}
	}

	return '';
}
