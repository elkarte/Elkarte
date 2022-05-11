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
	{
		return;
	}

	foreach ($array as $callback)
	{
		$func = 'template_' . $id . '_' . $callback;
		if (function_exists($func))
		{
			$func();
		}
	}
}

/**
 * The main sub template above the content.
 */
function template_html_above()
{
	global $context, $scripturl, $txt;

	// Show right to left and the character set for ease of translating.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', ' lang="', str_replace('_', '-', $txt['lang_locale']), '">
<head>
	<title>', $context['page_title_html_safe'], '</title>
	<meta charset="utf-8" />';

	echo '
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="mobile-web-app-capable" content="yes" />
	<meta name="description" content="', $context['page_title_html_safe'], '" />';

	// Please don't index these Mr Robot.
	if (!empty($context['robot_no_index']))
	{
		echo '
	<meta name="robots" content="noindex" />';
	}

	// If we have any Open Graph data, here is where is inserted.
	if (!empty($context['open_graph']))
	{
		echo '
	' .implode("\n\t", $context['open_graph']);
	}

	// load in any css from addons or themes, so they can overwrite if wanted
	template_css();

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
	{
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '" />';
	}

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
<body class="action_', !empty($context['current_action']) ? htmlspecialchars($context['current_action'], ENT_COMPAT, 'UTF-8') : (!empty($context['current_board']) ?
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

	echo '
	<header id="top_section">
		<aside id="top_header" class="wrapper">';

	// Load in all register header templates
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

	// Load in all registered upper content templates
	call_template_callbacks('uc', $context['upper_content_callbacks']);

	echo '
		</aside>';

	// Show the navigation tree.
	theme_linktree();

	// The main content should go here.
	echo '
		<div id="main_content_section">
			<a id="skipnav"></a>';
}

/**
 * More or less a place holder for now, sits at the very page top.
 * The maintenance mode warning for admins is an obvious one, but this could also be used for moderation notifications.
 * I also assumed this would be an obvious place for sites to put a string of icons to link to their FB, Twitter, etc.
 * This could still be done via conditional, so that administration and moderation notices were still active when
 * applicable.
 */
function template_th_header_bar()
{
	global $context, $txt, $scripturl;

	echo '
			<div id="top_section_notice" class="user">
			</div>';
}

/**
 * Search bar form, expands to input form when search icon is clicked
 */
function template_search_form()
{
	global $context, $modSettings, $txt;

	echo '
			<form id="search_form_menu" action="', getUrl('action', ['action' => 'search', 'sa' => 'results']), '" method="post" role="search" accept-charset="UTF-8">';

	// Using the quick search dropdown?
	if (!empty($modSettings['search_dropdown']))
	{
		$selected = !empty($context['current_topic']) ? 'current_topic' : (!empty($context['current_board']) ? 'current_board' : 'all');
		echo '
				<label for="search_selection">
					<select name="search_selection" id="search_selection" class="linklevel1" aria-label="search selection">
						<option value="all"', ($selected === 'all' ? ' selected="selected"' : ''), '>', $txt['search_entireforum'], ' </option>';

		// Can't limit it to a specific topic if we are not in one
		if (!empty($context['current_topic']))
		{
			echo '
						<option value="topic"', ($selected === 'current_topic' ? ' selected="selected"' : ''), '>', $txt['search_thistopic'], '</option>';
		}

		// Can't limit it to a specific board if we are not in one
		if (!empty($context['current_board']))
		{
			echo '
						<option value="board"', ($selected === 'current_board' ? ' selected="selected"' : ''), '>', $txt['search_thisbrd'], '</option>';
		}

		if (!empty($context['additional_dropdown_search']))
		{
			foreach ($context['additional_dropdown_search'] as $name => $engine)
			{
				echo '
						<option value="', $name, '">', $engine['name'], '</option>';
			}
		}

		echo '
						<option value="members"', ($selected === 'members' ? ' selected="selected"' : ''), '>', $txt['search_members'], ' </option>
					</select>
				</label>';
	}

	// Search within current topic?
	if (!empty($context['current_topic']) && !empty($modSettings['search_dropdown']))
	{
		echo '
				<input type="hidden" name="', (!empty($modSettings['search_dropdown']) ? 'sd_topic' : 'topic'), '" value="', $context['current_topic'], '" />';
	}

	// If we're on a certain board, limit it to this board ;).
	if (!empty($context['current_board']) && !empty($modSettings['search_dropdown']))
	{
		echo '
				<input type="hidden" name="', (!empty($modSettings['search_dropdown']) ? 'sd_brd[' : 'brd['), $context['current_board'], ']"', ' value="', $context['current_board'], '" />';
	}

	echo '					
				<label for="quicksearch">
					<input type="search" name="search" id="quicksearch" value="" class="linklevel1" placeholder="', $txt['search'], '" />
				</label>
				<button type="submit" aria-label="' . $txt['search'] . '" name="search;sa=results" class="', (!empty($modSettings['search_dropdown'])) ? 'with_select' : '', '">
					<i class="icon i-search icon-shade"><s>', $txt['search'], '</s></i>
				</button>
				<button type="button" aria-label="' . $txt['find_close'] . '">
					<label for="search_form_check">
						<i class="icon i-close icon-shade"><s>', $txt['find_close'], '</s></i>
					</label>
				</button>
				<input type="hidden" name="advanced" value="0" />
			</form>';
}

/**
 * Search bar menu icon
 */
function template_mb_search_bar()
{
	global $txt;

	echo '
			<li id="search_form_button" class="listlevel1" role="none">
				<label for="search_form_check">
					<a class="linklevel1 panel_search" role="menuitem">
						<i class="main-menu-icon i-search colorize-white"><s>', $txt['search'], '</s></i>
					</a>
				</label>
			</li>';
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
				!empty($context['newsfeed_urls']['rss']) ? '
				<li>
					<a id="button_rss" href="' . $context['newsfeed_urls']['rss'] . '" class="rssfeeds new_win">
						<i class="icon icon-margin i-rss icon-big"><s>' . $txt['rss'] . '</s></i>
					</a>
				</li>' : '', '
			</ul>';

	// Show the load time?
	if ($context['show_load_time'])
	{
		echo '
			<p>', sprintf($txt['page_created_full'], $context['load_time'], $context['load_queries']), '</p>';
	}
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

	// This is here to catch any late loading of JS files via templates
	theme()->outputJavascriptFiles(theme()->getJSFiles());

	// load inline javascript that needed to be deferred to the end of the page
	theme()->template_inline_javascript(true);

	// Schema microdata about the organization?
	if (!empty($context['smd_site']))
	{
		echo '
	<script type="application/ld+json">
	', json_encode($context['smd_site'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), '
	</script>';
	}

	// Schema microdata about the post?
	if (!empty($context['smd_article']))
	{
		echo '
	<script type="application/ld+json">
	', json_encode($context['smd_article'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), '
	</script>';
	}

	// Anything special to put out?
	if (!empty($context['insert_after_template']))
	{
		echo $context['insert_after_template'];
	}

	echo '
</body>
</html>';
}

/**
 * Show a linktree / breadcrumbs. This is that thing that shows
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
	{
		return;
	}

	echo '
		<nav class="breadcrumb" aria-label="breadcrumbs">';

	// Each tree item has a URL and name. Some may have extra_before and extra_after.
	// Added a linktree class to make targeting dividers easy.
	foreach ($context[$default] as $pos => $tree)
	{
		$tree['name'] = ($tree['extra_before'] ?? '') . $tree['name'] . ($tree['extra_after'] ?? '');

		// Show the link, including a URL if it should have one.
		echo $settings['linktree_link'] && isset($tree['url'])
			? '
			<span class="crumb">
				<a href="' . $tree['url'] . '">' .
					($pos === 0
						? '<i class="icon i-home"><s>' . $txt['home'] . '</s></i>'
						: $tree['name']) . '
				</a>
			</span>'
			: '
			<span class="crumb">
				<a href="#">' . $tree['name'] . '</a>
			<span>	';
	}

	echo '
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
				
				<nav id="menu_nav" aria-label="', $txt['main_menu'], '">
					<div class="wrapper no_js">
					<input type="checkbox" id="search_form_check">
					<ul id="main_menu" aria-label="', $txt['main_menu'], '" role="menubar">';

	// Add any additional menu buttons from addons
	call_template_callbacks('mb', $context['theme_header_callbacks']);

	// This defines the start of right aligned buttons, simply set your button order > 10
	echo '
						<li id="button_none" class="listlevel1" role="none">
							<a role="none"></a>
						</li>';

	// The upshrink image.
	echo '
						<li id="collapse_button" class="listlevel1" role="none">
							<a class="linklevel1 panel_toggle" role="menuitem">
								<i id="upshrink" class="hide main-menu-icon i-chevron-up" title="', $txt['upshrink_description'], '"></i>
							</a>
						</li>';

	// Now all the buttons from menu.subs
	foreach ($context['menu_buttons'] as $act => $button)
	{
		// Top link details, easier to maintain broken out
		$class = 'class="linklevel1' . (!empty($button['active_button']) ? ' active' : '') . (!empty($button['indicator']) ? ' indicator' : '') . '"';
		$href = ' href="' . $button['href'] . '"';
		$target = isset($button['target']) ? ' target="' . $button['target'] . '"' : '';
		$onclick = isset($button['onclick']) ? ' onclick="' . $button['onclick'] . '"' : '';
		$altTitle = 'title="' . (!empty($button['alttitle']) ? $button['alttitle'] : $button['title']) . '"';
		$ally = !empty($button['active_button']) ? 'aria-current="page"' : '';

		echo '
						<li id="button_', $act, '" class="listlevel1', !empty($button['sub_buttons']) ? ' subsections"' : '"', ' role="none">
							<a ', $class, $href, $target, $ally, $onclick, ' role="menuitem"', !empty($button['sub_buttons']) ? ' aria-haspopup="true"' : '', '>',
								(!empty($button['data-icon']) ? '<i class="icon icon-menu icon-lg ' . $button['data-icon'] . (!empty($button['active_button']) ? ' enabled' : '') . '" ' . $altTitle . '></i> ' : ''),
								'<span class="button_title" aria-hidden="', (empty($button['sub_buttons']) ? 'false' : 'true'), '">', $button['title'], '</span>
							</a>';

		// Any 2nd level menus?
		if (!empty($button['sub_buttons']))
		{
			echo '
							<ul class="menulevel2" role="menu">';

			foreach ($button['sub_buttons'] as $childact => $childbutton)
			{
				echo '
								<li id="button_', $childact, '" class="listlevel2', !empty($childbutton['sub_buttons']) ? ' subsections"' : '"', ' role="none">
									<a class="linklevel2" href="', $childbutton['href'], '" ', isset($childbutton['target']) ? 'target="' . $childbutton['target'] . '"' : '', isset($childbutton['onclick']) ? ' onclick="' . $childbutton['onclick'] . '"' : '', !empty($childbutton['sub_buttons']) ? ' aria-haspopup="true"' : '', ' role="menuitem">',
										$childbutton['title'], '
									</a>';

				// 3rd level menus :)
				if (!empty($childbutton['sub_buttons']))
				{
					echo '
									<ul class="menulevel3" role="menu">';

					foreach ($childbutton['sub_buttons'] as $grandchildact => $grandchildbutton)
					{
						echo '
										<li id="button_', $grandchildact, '" class="listlevel3" role="none">
											<a class="linklevel3" href="', $grandchildbutton['href'], '" ', isset($grandchildbutton['target']) ? 'target="' . $grandchildbutton['target'] . '"' : '', isset($grandchildbutton['onclick']) ? ' onclick="' . $grandchildbutton['onclick'] . '"' : '', ' role="menuitem">',
												$grandchildbutton['title'], '
											</a>
										</li>';
					}

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
						
					</ul>';

	// If search is enabled, plop in the form
	if ($context['allow_search'])
	{
		template_search_form();
	}

	echo '</div>
				</nav>';

	// Define the upper_section toggle in javascript.
	theme()->addInlineJavascript('
		var oMainHeaderToggle = new elk_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ' . (empty($context['minmax_preferences']['upshrink']) ? 'false' : 'true') . ',
			aSwappableContainers: [
				\'upper_section\',\'header\',\'top_header\'
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
 * Generate a strip of buttons (like those present at the top of the message display)
 *
 * What it does:
 *
 * - Create a button list area, pass an array of the button name with key values
 * - array('somename' => array(url => '' text => '' custom => '' test => '', lang => bool, submenu => bool))
 *      - text => text to display in the button
 *      - custom => custom action to perform, generally used to add 'onclick' events (optional)
 *      - test => permission key to check in the $tests array before showing the button (optional)
 * 		- url => link to call when button is pressed
 * 		- lang => bool
 *      - submenu => if the button should be shown in a "more" button
 * 		- id => css id to use on link as #button_strip_ID (optional)
 *
 * @param mixed[] $button_strip
 * @param string $class = ''
 * @param string[] $strip_options = array()
 * @return void string as echoed content
 */
function template_button_strip($button_strip, $class = '', $strip_options = array())
{
	global $context, $txt, $options;

	// Not sure if this can happen, but people can misuse functions very efficiently
	if (empty($button_strip))
	{
		return;
	}

	if (!is_array($strip_options))
	{
		$strip_options = array();
	}

	// List the buttons in reverse order for RTL languages.
	if ($context['right_to_left'])
	{
		$class .= ' rtl';
	}

	// Create the buttons... now with cleaner markup (yay!).
	$buttons = [];
	$subMenu = [];
	foreach ($button_strip as $key => $value)
	{
		$id = (isset($value['id']) ? ' id="button_strip_' . $value['id'] . '"' : '');

		// Don't need any or have the right permission, you get a button
		if (!isset($value['test']) || !empty($context[$value['test']]))
		{
			if (!empty($value['submenu']))
			{
				$subMenu[] = '
						<li class="listlevel2">
							<a href="' . $value['url'] . '" class="linklevel2 button_strip_' . $key . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $txt[$value['text']] . '</a>
						</li>';
				continue;
			}

			$buttons[] = '
						<li role="menuitem">
							<a' . $id . ' class="linklevel1 button_strip_' . $key . (!empty($value['active']) ? ' active' : '') . '" href="' . $value['url'] . '"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $txt[$value['text']] . '</a>
						</li>';
		}
	}

	// Is a more options button needed, if so, it goes at the end
	if (!empty($subMenu))
	{
		$buttons[] = '
						<li class="listlevel1 subsections" aria-haspopup="true" role="menuitem">
							<a href="#" ' . (!empty($options['use_click_menu']) ? '' : 'onclick="event.stopPropagation();return false;"') . ' class="linklevel1 post_options">' .
								$txt['post_options'] . '
							</a>
							<ul class="menulevel2">' . implode("\n", $subMenu) . '</ul>
						</li>';
	}

	// No buttons? No button strip either.
	if (!empty($buttons))
	{
		echo '
						<ul role="menubar" class="no_js buttonlist', !empty($class) ? ' ' . $class : '', '"', (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"' : ''), '>
							', implode('', $buttons), '
						</ul>';
	}
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
 *      - override => full and complete <li></li> to use for the button
 * - checkboxes can be shown as well as buttons,
 *      - use array('check' => array(checkbox => (true | always), name => value =>)
 *      - if true follows show moderation as checkbox setting, always will always show
 *      - name => name of the checkbox array, like delete, will have [] added for the form
 *      - value => value for the checkbox to return in the post
 *
 * @param array $strip - the $context index where the strip is stored
 * @param bool[] $tests - an array of tests to determine if the button should be displayed or not
 * @return void echos a string of buttons
 */
function template_quickbutton_strip($strip, $tests = array())
{
	global $options;

	$buttons = [];

	foreach ($strip as $key => $value)
	{
		if (!empty($value['checkbox']) && ((!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1) || $value['checkbox'] === 'always'))
		{
			$buttons[] = '
					<li class="listlevel1 ' . $key . '">
						<input class="input_check ' . $key . '_check" type="checkbox" name="' . $value['name'] . '[]" value="' . $value['value'] . '" />
					</li>';

			continue;
		}

		// No special permission needed, or you have valid permission, then get a button!
		if (!isset($value['test']) || !empty($tests[$value['test']]))
		{
			if (!empty($value['override']))
			{
				$buttons[] = $value['override'];
				continue;
			}

			$buttons[] = '
					<li class="listlevel1">
						<a href="' . $value['href'] . '" class="linklevel1 ' . $key . '_button"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $value['text'] . '</a>
					</li>';
			}
		}

	// No buttons? No button strip either.
	if (!empty($buttons))
	{
		echo '
					<ul class="quickbuttons">', implode('
						', $buttons), '
					</ul>';
	}
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
	{
		return;
	}

	$error = $context[$error_id] ?? array();

	echo '
					<div id="', $error_id, '" class="', (isset($error['type']) ? ($error['type'] === 'serious' ? 'errorbox' : 'warningbox') : 'successbox'), empty($error['errors']) ? ' hide"' : '"', '>';

	// Optional title for our results
	if (!empty($error['title']))
	{
		echo '
						<dl>
							<dt>
								<strong id="', $error_id, '_title">', $error['title'], '</strong>
							</dt>
							<dd>';
	}

	// Everything that went wrong, or correctly :)
	if (!empty($error['errors']))
	{
		echo '
								<ul', (isset($error['type']) ? ' class="error"' : ''), ' id="', $error_id, '_list">';

		foreach ($error['errors'] as $key => $err)
		{
			echo '
									<li id="', $error_id, '_', $key, '">', $err, '</li>';
		}
		echo '
								</ul>';
	}

	// All done
	if (!empty($error['title']))
	{
		echo '
							</dd>
						</dl>';
	}

	echo '
					</div>';
}

/**
 * Is this used?
 */
function template_uc_generic_infobox()
{
	global $context;

	if (empty($context['generic_infobox']))
	{
		return;
	}

	foreach ($context['generic_infobox'] as $key)
	{
		template_show_error($key);
	}
}

/**
 * Another used and abused piece of template that can be found everywhere
 *
 * @param string|bool $button_strip index of $context to create the button strip
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

	if (!empty($options['page_index_markup']))
	{
		$pages = '<ul ' . (isset($options['page_index_id']) ? 'id="' . $options['page_index_id'] . '" ' : '') . 'class="pagelinks" role="navigation">' . $options['page_index_markup'] . '</ul>';
	}
	else
	{
		if (!isset($options['page_index']))
		{
			$options['page_index'] = 'page_index';
		}

		$pages = empty($context[$options['page_index']]) ? '' : '<ul ' . (isset($options['page_index_id']) ? 'id="' . $options['page_index_id'] . '" ' : '') . 'class="pagelinks" role="navigation">' . $context[$options['page_index']] . '</ul>';
	}

	if (!isset($options['extra']))
	{
		$options['extra'] = '';
	}

	if (!empty($strip_direction) && $strip_direction === 'left')
	{
		$strip_direction = 'rtl';
	}

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
				</ul>
				<script type="module">
					$("#elkFadeScroller").Elk_NewsFader(' . (empty($settings['newsfader_time']) ? '' : '{iFadeDelay: ' . $settings['newsfader_time'] . '}') . ');
				</script>';
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
 * @param bool $text
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

			if ($member['show_email'] === 'yes_permission_override' || $member['show_email'] === 'yes')
			{
				return '<a class="linkbutton" href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '">' . $member['email'] . '</a>';
			}

			return $txt['hidden'];
		}
		elseif ($member['show_email'] !== 'no')
		{
			return '<a href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '" class="icon i-envelope-o' . ($member['online']['is_online'] ? '' : '-blank') . '" title="' . $txt['email'] . ' ' . $member['name'] . '"><s>' . $txt['email'] . ' ' . $member['name'] . '</s></a>';
		}

		return '<i class="icon i-envelope-o" title="' . $txt['email'] . ' ' . $txt['hidden'] . '"><s>' . $txt['email'] . ' ' . $txt['hidden'] . '</s></i>';
	}

	return '';
}

/**
 * Sometimes we only get a message id.
 *
 * @param      $id
 * @param bool|mixed[] $member
 *
 * @return string
 */
function template_msg_email($id, $member = false)
{
	global $context, $txt, $scripturl;

	if (!$context['can_send_email'])
	{
		return '';
	}

	if ($member === false || $member['show_email'] !== 'no')
	{
		if (empty($member['id']))
		{
			return '<a href="' . $scripturl . '?action=emailuser;sa=email;msg=' . $id . '" class="icon i-envelope-o' . (($member !== false && $member['online']['is_online']) ? '' : '-blank') . '" title="' . $txt['email'] . '"><s>' . $txt['email'] . '</s></a>';
		}

		return '<a href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '" class="icon i-envelope-o' . (($member !== false && $member['online']['is_online']) ? '' : '-blank') . '" title="' . $txt['email'] . '"><s>' . $txt['email'] . '</s></a>';
	}

	return '<i class="icon i-envelope-o" title="' . $txt['email'] . ' ' . $txt['hidden'] . '"><s>' . $txt['email'] . ' ' . $txt['hidden'] . '</s></i>';
}
