<?php

/**
 * The default theme
 *
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

namespace ElkArte\Themes\DefaultTheme;

use BBC\ParserWrapper;
use ElkArte\Cache\Cache;
use ElkArte\Controller\ScheduledTasks;
use ElkArte\EventManager;
use ElkArte\FileFunctions;
use ElkArte\Http\Headers;
use ElkArte\SiteCombiner;
use ElkArte\Themes\Theme as BaseTheme;
use ElkArte\Languages\Txt;
use ElkArte\User;
use ElkArte\Util;
use Patchwork\JSqueeze;

/**
 * Class Theme
 *
 * - Extends the abstract theme class
 *
 * @package Themes\DefaultTheme
 */
class Theme extends BaseTheme
{
	/**
	 * Initialize the template... mainly little settings.
	 *
	 * @return array Theme settings
	 */
	public function getSettings()
	{
		return array(
			/*
			 * Specifies whether images from default theme shall be
			 * fetched instead of the current theme when using
			 * templates from the default theme.
			 *
			 * - if this is 'always', images from the default theme will be used.
			 * - if this is 'defaults', images from the default theme will only be used with default templates.
			 * - if this is 'never' or isn't set at all, images from the default theme will not be used.
			 *
			 * This doesn't apply when custom templates are being
			 * used; nor does it apply to the default theme.
			 */
			'use_default_images' => 'never',

			/*
			 * The version this template/theme is for. This should
			 * be the version of the forum it was created for.
			 */
			'theme_version' => '2.0',

			/*
			 * Whether this theme requires the optional theme strings
			 * file to be loaded. (ThemeStrings.[language].php)
			 */
			'require_theme_strings' => false,

			/*
			 * Specify the color variants. Each variant has its own
			 * directory, where additional CSS files may be loaded.
			 *
			 * Example:
			 * - _light/index_light.css is loaded when index.css is needed.
			 */
			'theme_variants' => array(
				'light',
				'besocial',
				'dark',
			),

			/*
			 * Provides avatars for use on various indexes.
			 *
			 * Possible values:
			 * - 0 or not set, no avatars are available
			 * - 1 avatar of the poster of the last message
			 * - 2 avatar of the poster of the first message
			 * - 3 both avatars
			 *
			 * Since grabbing the avatar requires some work, it is
			 * better to set the variable to a sensible value
			 * depending on the needs of the theme.
			 */
			'avatars_on_indexes' => 1,

			/*
			 * This is used in the main menus to create a number next
			 * to the title of the menu to indicate the number of
			 * unread messages, moderation reports, etc. You can
			 * style each menu level indicator as desired.
			 */
			'menu_numeric_notice' => array(
				// Top level menu entries
				0 => ' <span class="pm_indicator">%1$s</span>',
				// First dropdown
				1 => ' <span>[<strong>%1$s</strong>]</span>',
				// Second level dropdown
				2 => ' <span>[<strong>%1$s</strong>]</span>',
			),

			// This slightly more complex array, instead, will deal with page indexes as frequently requested by Ant :P
			// Oh no you don't. :D This slightly less complex array now has cleaner markup. :P
			// @todo - God it's still ugly though. Can't we just have links where we need them, without all those spans?
			// How do we get anchors only, where they will work? Spans and strong only where necessary?
			'page_index_template' => array(
				'base_link' => '<li class="linavPages"><a class="navPages" href="{base_link}">%2$s</a></li>',
				'previous_page' => '<span class="previous_page">{prev_txt}</span>',
				'current_page' => '<li class="linavPages"><strong class="current_page">%1$s</strong></li>',
				'next_page' => '<span class="next_page">{next_txt}</span>',
				'expand_pages' => '<li class="linavPages expand_pages" {custom}> <a href="#">&#8230;</a> </li>',
				'all' => '<span class="linavPages all_pages">{all_txt}</span>',
				'none' => '<li class="hide"><a href="#"></a></li>',
			),

			// @todo find a better place if we are going to create a notifications template
			'mentions' => array(
				'mentioner_template' => '<a href="{mem_url}" class="mentionavatar">{avatar_img}{mem_name}</a>',
			)
		);
	}

	/**
	 * This is the only template included in the sources.
	 */
	public function template_rawdata()
	{
		global $context;

		echo $context['raw_data'];
	}

	/**
	 * The header template
	 */
	public function template_header()
	{
		global $context, $settings;

		doSecurityChecks();

		$this->setupThemeContext();
		$header = Headers::instance();

		// Print stuff to prevent caching of pages (except on attachment errors, etc.)
		if (empty($context['no_last_modified']))
		{
			$header
				->header('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT')
				->header('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT')
				->contentType('text/html', 'UTF-8');
		}

		$api = $this->_req->getRequest('api', 'trim');
		if (isset($api))
		{
			if ($api === 'json')
			{
				$header->contentType('application/json', 'UTF-8');
			}
			elseif ($api === 'xml')
			{
				$header->contentType('text/xml', 'UTF-8');
			}
			else
			{
				$header->contentType('text/html', 'UTF-8');
			}
		}

		foreach ($this->getLayers()->prepareContext() as $layer)
		{
			$this->getTemplates()->loadSubTemplate($layer . '_above', 'ignore');
		}

		if (isset($settings['use_default_images'], $settings['default_template'])
			&& $settings['use_default_images'] === 'defaults')
		{
			$settings['theme_url'] = $settings['default_theme_url'];
			$settings['images_url'] = $settings['default_images_url'];
			$settings['theme_dir'] = $settings['default_theme_dir'];
		}

		$header->sendHeaders();
	}

	/**
	 * Sets up the basic theme context stuff.
	 *
	 * @param bool $forceload = false
	 */
	public function setupThemeContext($forceload = false)
	{
		global $modSettings, $scripturl, $context, $settings, $options, $txt, $boardurl;

		static $loaded = false;

		// Under SSI this function can be called more than once.  That can cause some problems.
		// So only run the function once unless we are forced to run it again.
		if ($loaded && !$forceload)
		{
			return;
		}

		$loaded = true;

		$context['current_time'] = standardTime(time(), false);
		$context['current_action'] = $this->_req->getQuery('action', 'trim', '');
		$context['robot_no_index'] = in_array($context['current_action'], $this->no_index_actions);

		$bbc_parser = ParserWrapper::instance();

		// Get some news...
		$context['news_lines'] = array_filter(explode("\n", str_replace("\r", '', trim(addslashes($modSettings['news'])))));
		foreach ($context['news_lines'] as $i => $iValue)
		{
			if (trim($iValue) === '')
			{
				continue;
			}

			// Clean it up for presentation ;).
			$context['news_lines'][$i] = $bbc_parser->parseNews(stripslashes(trim($iValue)));
		}

		// If we have some, setup for display
		if (!empty($context['news_lines']))
		{
			$context['random_news_line'] = $context['news_lines'][mt_rand(0, count($context['news_lines']) - 1)];
			$context['upper_content_callbacks'][] = 'news_fader';
		}

		if ($this->user->is_guest === false)
		{
			$context['user']['messages'] = $this->user->messages;
			$context['user']['unread_messages'] = $this->user->unread_messages;
			$context['user']['mentions'] = $this->user->mentions;

			// Personal message popup...
			if ($this->user->unread_messages > ($_SESSION['unread_messages'] ?? 0))
			{
				$context['user']['popup_messages'] = true;
			}
			else
			{
				$context['user']['popup_messages'] = false;
			}

			$_SESSION['unread_messages'] = $this->user->unread_messages;

			$context['user']['avatar'] = array(
				'href' => !empty($this->user->avatar['href']) ? $this->user->avatar['href'] : '',
				'image' => !empty($this->user->avatar['image']) ? $this->user->avatar['image'] : '',
			);

			// Figure out how long they've been logged in.
			$context['user']['total_time_logged_in'] = array(
				'days' => floor($this->user->total_time_logged_in / 86400),
				'hours' => floor(($this->user->total_time_logged_in % 86400) / 3600),
				'minutes' => floor(($this->user->total_time_logged_in % 3600) / 60)
			);
		}
		else
		{
			$context['user']['messages'] = 0;
			$context['user']['unread_messages'] = 0;
			$context['user']['mentions'] = 0;
			$context['user']['avatar'] = array();
			$context['user']['total_time_logged_in'] = array('days' => 0, 'hours' => 0, 'minutes' => 0);
			$context['user']['popup_messages'] = false;

			if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1)
			{
				$txt['welcome_guest'] .= $txt['welcome_guest_activate'];
			}

			$txt['welcome_guest'] = replaceBasicActionUrl($txt['welcome_guest']);

			// If we've upgraded recently, go easy on the passwords.
			if (!empty($modSettings['enable_password_conversion']))
			{
				$context['disable_login_hashing'] = true;
			}
		}

		// Setup the main menu items.
		$this->setupMenuContext();

		// This is here because old index templates might still use it.
		$context['show_news'] = !empty($settings['enable_news']);

		$context['additional_dropdown_search'] = prepareSearchEngines();

		// This is done to allow theme authors to customize it as they want.
		$context['show_pm_popup'] = $context['user']['popup_messages'] && !empty($options['popup_messages']) && $context['current_action'] !== 'pm';

		// Add the PM popup here instead. Theme authors can still override it simply by editing/removing the 'fPmPopup' in the array.
		if ($context['show_pm_popup'])
		{
			$this->addInlineJavascript('
			$(function() {
				new elk_Popup({
					heading: ' . JavaScriptEscape($txt['show_personal_messages_heading']) . ',
					content: ' . JavaScriptEscape(sprintf($txt['show_personal_messages'], $context['user']['unread_messages'], $scripturl . '?action=pm')) . ',
					icon: \'i-envelope\'
				});
			});', true);
		}

		// This looks weird, but it's because BoardIndex.controller.php references the variable.
		$href = getUrl('profile', ['action' => 'profile', 'u' => $modSettings['latestMember'], 'name' => $modSettings['latestRealName']]);
		$context['common_stats']['latest_member'] = array(
			'id' => $modSettings['latestMember'],
			'name' => $modSettings['latestRealName'],
			'href' => $href,
			'link' => '<a href="' . $href . '">' . $modSettings['latestRealName'] . '</a>',
		);

		$context['common_stats'] = array(
			'total_posts' => comma_format($modSettings['totalMessages']),
			'total_topics' => comma_format($modSettings['totalTopics']),
			'total_members' => comma_format($modSettings['totalMembers']),
			'latest_member' => $context['common_stats']['latest_member'],
		);

		$context['common_stats']['boardindex_total_posts'] = sprintf($txt['boardindex_total_posts'], $context['common_stats']['total_posts'], $context['common_stats']['total_topics'], $context['common_stats']['total_members']);

		if (empty($settings['theme_version']))
		{
			$this->addJavascriptVar(array('elk_scripturl' => $scripturl), true);
		}
		$this->addJavascriptVar(array('elk_forum_action' =>  getUrlQuery('action', $modSettings['default_forum_action'])), true);

		if (!isset($context['page_title']))
		{
			$context['page_title'] = '';
		}

		// Set some specific vars.
		$context['page_title_html_safe'] = Util::htmlspecialchars(un_htmlspecialchars($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . ' ' . ($context['current_page'] + 1) : '');

		$context['favicon'] = $boardurl . '/mobile.png';

		$this->loadSupportCSS();

		// Since it's nice to have avatars all the same size, and in some cases the size detection may fail,
		// let's add the css in any case
		if (!isset($context['html_headers']))
		{
			$context['html_headers'] = '';
		}

		if (!empty($modSettings['avatar_max_width']) || !empty($modSettings['avatar_max_height']))
		{
			$this->addCSSRules('
		.avatarresize {' . (!empty($modSettings['avatar_max_width']) ? '
			max-width:' . $modSettings['avatar_max_width'] . 'px;' : '') . (!empty($modSettings['avatar_max_height']) ? '
			max-height:' . $modSettings['avatar_max_height'] . 'px;' : '') . '
		}');
		}

		// Save some database hits, if a width for multiple wrappers is set in admin.
		if (!empty($settings['forum_width']))
		{
			$this->addCSSRules('
		.wrapper {width: ' . $settings['forum_width'] . ';}');
		}

		// Localization for the show more quote and its container height
		$quote_height = !empty($modSettings['heightBeforeShowMore']) ? $modSettings['heightBeforeShowMore'] . 'px' : 'none';
		$this->addCSSRules('
		input[type=checkbox].quote-show-more:after {content: "' . $txt['quote_expand'] . '";}
		.quote-read-more > .bbc_quote {--quote_height: ' . $quote_height . ';}'
		);
	}

	/**
	 * Sets up all the top menu buttons
	 *
	 * What it does:
	 *
	 * - Defines every master item in the menu, as well as any sub-items
	 * - Ensures the chosen action is set so the menu is highlighted
	 * - Saves them in the cache if it is available and on
	 * - Places the results in $context
	 */
	public function setupMenuContext()
	{
		global $context, $modSettings, $settings;

		// Set up the menu privileges.
		$context['allow_search'] = !empty($modSettings['allow_guestAccess']) ? allowedTo('search_posts') : ($this->user->is_guest === false && allowedTo('search_posts'));
		$context['allow_admin'] = allowedTo(array('admin_forum', 'manage_boards', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_attachments', 'manage_smileys'));
		$context['allow_edit_profile'] = $this->user->is_guest === false && allowedTo(array('profile_view_own', 'profile_view_any', 'profile_identity_own', 'profile_identity_any', 'profile_extra_own', 'profile_extra_any', 'profile_remove_own', 'profile_remove_any', 'moderate_forum', 'manage_membergroups', 'profile_title_own', 'profile_title_any'));
		$context['allow_memberlist'] = allowedTo('view_mlist');
		$context['allow_calendar'] = allowedTo('calendar_view') && !empty($modSettings['cal_enabled']);
		$context['allow_moderation_center'] = $context['user']['can_mod'];
		$context['allow_pm'] = allowedTo('pm_read');
		$cache = Cache::instance();

		call_integration_hook('integrate_setup_allow');

		if ($context['allow_search'])
		{
			$context['theme_header_callbacks'] = elk_array_insert($context['theme_header_callbacks'], 'login_bar', array('search_bar'), 'after');
		}

		// Add in a top section notice callback
		$context['theme_header_callbacks'][] = 'header_bar';

		$cacheTime = $modSettings['lastActive'] * 60;

		// Update the Moderation menu items with action item totals
		if ($context['allow_moderation_center'])
		{
			// Get the numbers for the menu ...
			require_once(SUBSDIR . '/Moderation.subs.php');
			$menu_count = loadModeratorMenuCounts();
		}

		$menu_count['unread_messages'] = $context['user']['unread_messages'];
		$menu_count['mentions'] = $context['user']['mentions'];

		if (!empty($this->user->avatar['href']))
		{
			$this->addCSSRules('
		.i-menu-profile::before, .i-menu-profile.enabled::before {
			content: "";
			background-image: url("' . htmlspecialchars_decode($this->user->avatar['href']) . '");
			background-position: center;
			filter: unset;
		}');
		}

		// All the buttons we can possibly want and then some, try pulling the final list of buttons from cache first.
		if ((time() - $cacheTime <= $modSettings['settings_updated'])
			|| ($menu_buttons = $cache->get('menu_buttons-' . implode('_', $this->user->groups) . '-' . $this->user->language, $cacheTime)) === null)
		{
			// Start things up: this is what we know by default
			require_once(SUBSDIR . '/Menu.subs.php');
			$buttons = loadDefaultMenuButtons();

			// Allow editing menu buttons easily.
			call_integration_hook('integrate_menu_buttons', array(&$buttons, &$menu_count));

			// Now we put the buttons in the context so the theme can use them.
			$menu_buttons = [];
			foreach ($buttons as $act => $button)
			{
				if (!empty($button['show']))
				{
					$button['active_button'] = false;

					// This button needs some action.
					if (isset($button['action_hook']))
					{
						$needs_action_hook = true;
					}

					// This button has a [#] indicator
					if (isset($button['counter']) && !empty($menu_count[$button['counter']]))
					{
						$button['alttitle'] = $button['title'] . ' [' . $menu_count[$button['counter']] . ']';
						if (!empty($settings['menu_numeric_notice'][0]))
						{
							$button['title'] .= sprintf($settings['menu_numeric_notice'][0], $menu_count[$button['counter']]);
							$button['indicator'] = true;
						}
					}

					// Go through the sub buttons if there are any.
					if (isset($button['sub_buttons']))
					{
						foreach ($button['sub_buttons'] as $key => $subbutton)
						{
							if (empty($subbutton['show']))
							{
								unset($button['sub_buttons'][$key]);
							}
							elseif (isset($subbutton['counter']) && !empty($menu_count[$subbutton['counter']]))
							{
								$button['sub_buttons'][$key]['alttitle'] = $subbutton['title'] . ' [' . $menu_count[$subbutton['counter']] . ']';
								if (!empty($settings['menu_numeric_notice'][1]))
								{
									$button['sub_buttons'][$key]['title'] .= sprintf($settings['menu_numeric_notice'][1], $menu_count[$subbutton['counter']]);
								}

								// 2nd level sub buttons next...
								if (isset($subbutton['sub_buttons']))
								{
									foreach ($subbutton['sub_buttons'] as $key2 => $subbutton2)
									{
										$button['sub_buttons'][$key]['sub_buttons'][$key2] = $subbutton2;
										if (empty($subbutton2['show']))
										{
											unset($button['sub_buttons'][$key]['sub_buttons'][$key2]);
										}
										elseif (isset($subbutton2['counter']) && !empty($menu_count[$subbutton2['counter']]))
										{
											$button['sub_buttons'][$key]['sub_buttons'][$key2]['alttitle'] = $subbutton2['title'] . ' [' . $menu_count[$subbutton2['counter']] . ']';
											if (!empty($settings['menu_numeric_notice'][2]))
											{
												$button['sub_buttons'][$key]['sub_buttons'][$key2]['title'] .= sprintf($settings['menu_numeric_notice'][2], $menu_count[$subbutton2['counter']]);
											}
											unset($menu_count[$subbutton2['counter']]);
										}
									}
								}
							}
						}
					}

					$menu_buttons[$act] = $button;
				}
			}

			if ($cache->levelHigherThan(1))
			{
				$cache->put('menu_buttons-' . implode('_', $this->user->groups) . '-' . $this->user->language, $menu_buttons, $cacheTime);
			}
		}

		if (!empty($menu_buttons['profile']['sub_buttons']['logout']))
		{
			$menu_buttons['profile']['sub_buttons']['logout']['href'] .= ';' . $context['session_var'] . '=' . $context['session_id'];
		}

		$context['menu_buttons'] = $menu_buttons;

		// Figure out which action we are doing, so we can set the active tab.
		// Default to home.
		$current_action = 'home';

		if (isset($context['menu_buttons'][$context['current_action']]))
		{
			$current_action = $context['current_action'];
		}
		elseif ($context['current_action'] === 'profile')
		{
			$current_action = 'pm';
		}
		elseif ($context['current_action'] === 'theme')
		{
			$sa = $this->_req->getRequest('sa', 'trim', '');
			$current_action = $sa === 'pick' ? 'profile' : 'admin';
		}
		elseif ($context['current_action'] === 'login2' || ($this->user->is_guest && $context['current_action'] === 'reminder'))
		{
			$current_action = 'login';
		}
		elseif ($context['current_action'] === 'groups' && $context['allow_moderation_center'])
		{
			$current_action = 'moderate';
		}
		elseif ($context['current_action'] === 'moderate' && $context['allow_admin'])
		{
			$current_action = 'admin';
		}

		// Not all actions are simple.
		if (!empty($needs_action_hook))
		{
			call_integration_hook('integrate_current_action', array(&$current_action));
		}

		if (isset($context['menu_buttons'][$current_action]))
		{
			$context['menu_buttons'][$current_action]['active_button'] = true;
		}
	}

	/**
	 * Adds required support CSS files.
	 */
	public function loadSupportCSS()
	{
		global $settings;

		// Load a base theme custom CSS file?
		$fileFunc = FileFunctions::instance();
		if ($fileFunc->fileExists($settings['theme_dir'] . '/css/custom.css'))
		{
			loadCSSFile('custom.css');
		}
	}

	/**
	 * Show the copyright.
	 */
	public function theme_copyright()
	{
		global $forum_copyright;

		// Don't display copyright for things like SSI.
		if (!defined('FORUM_VERSION'))
		{
			return;
		}

		// Put in the version...
		$forum_copyright = replaceBasicActionUrl(sprintf($forum_copyright, FORUM_VERSION));

		echo '
					', $forum_copyright;
	}

	/**
	 * The template footer
	 */
	public function template_footer()
	{
		global $context, $settings, $modSettings, $time_start;

		$db = database();

		// Show the load time?  (only makes sense for the footer.)
		$context['show_load_time'] = !empty($modSettings['timeLoadPageEnable']);
		$context['load_time'] = round(microtime(true) - $time_start, 3);
		$context['load_queries'] = $db->num_queries();

		if (isset($settings['use_default_images'], $settings['default_template'])
			&& $settings['use_default_images'] === 'defaults')
		{
			$settings['theme_url'] = $settings['actual_theme_url'];
			$settings['images_url'] = $settings['actual_images_url'];
			$settings['theme_dir'] = $settings['actual_theme_dir'];
		}

		foreach ($this->getLayers()->reverseLayers() as $layer)
		{
			$this->getTemplates()->loadSubTemplate($layer . '_below', 'ignore');
		}
	}

	/**
	 * Output the Javascript, including files, inline and vars
	 *
	 * What it does:
	 *
	 * - Tabbing in this function is to make the HTML source look proper
	 * - Outputs in <head> all js variables added with addJavascriptVar()
	 * - Outputs jQuery/jQueryUI from the proper source (local/CDN)
	 * - Outputs in <head> all JS files added with loadJavascriptFile, uses defer/async where requested
	 * - Outputs in <head> all *inline* JS that is not deferred, deferred ones are placed after </body>
	 * - If the admin option to combine files is set, will use Combiner.class
	 */
	public function template_javascript()
	{
		global $modSettings;

		// Output any declared Javascript variables first, they tend to be globals
		$js_vars = [];
		if (!empty($this->js_vars))
		{
			foreach ($this->js_vars as $var => $value)
			{
				$js_vars[] = $var . ' = ' . $value;
			}

			echo '
	<script id="site_vars">
		let ', implode(",\n\t\t\t", $js_vars), ';
	</script>';
		}

		// Load jQuery and jQuery UI
		if (isset($modSettings['jquery_source']))
		{
			$this->templateJquery();
		}

		// Use this hook to work with Javascript files and vars pre output
		call_integration_hook('pre_javascript_output', []);

		// Load all the Javascript files
		$this->templateJavascriptFiles();

		// Output any <head> level inline JS
		$this->template_inline_javascript();
	}

	/**
	 * Loads the required jQuery files for the system
	 *
	 * - Determines the correct script tags to add based on CDN/Local/Auto
	 */
	protected function templateJquery()
	{
		global $modSettings, $settings;

		// Use a specified version of jquery 3.6.1  / 1.13.2
		$jquery_version = '3.6.1';
		$jqueryui_version = '1.13.2';

		$jquery_cdn = 'https://ajax.googleapis.com/ajax/libs/jquery/' . $jquery_version . '/jquery.min.js';
		$jqueryui_cdn = 'https://ajax.googleapis.com/ajax/libs/jqueryui/' . $jqueryui_version . '/jquery-ui.min.js';

		switch ($modSettings['jquery_source'])
		{
			// Only getting the files from the CDN?
			case 'cdn':
				echo '
	<script src="' . $jquery_cdn . '" id="jquery"></script>',
				(!empty($modSettings['jquery_include_ui']) ? '
	<script src="' . $jqueryui_cdn . '" id="jqueryui"></script>' : '');
				break;
			// Just use the local file
			case 'local':
				echo '
	<script src="', $settings['default_theme_url'], '/scripts/jquery-' . $jquery_version . '.min.js" id="jquery"></script>',
				(!empty($modSettings['jquery_include_ui']) ? '
	<script src="' . $settings['default_theme_url'] . '/scripts/jquery-ui-' . $jqueryui_version . '.min.js" id="jqueryui"></script>' : '');
				break;
			// CDN with local fallback
			case 'auto':
				echo '
	<script src="' . $jquery_cdn . '" id="jquery"></script>',
				(!empty($modSettings['jquery_include_ui']) ? '
	<script src="' . $jqueryui_cdn . '" id="jqueryui"></script>' : '');
				echo '
	<script>
		window.jQuery || document.write(\'<script src="', $settings['default_theme_url'], '/scripts/jquery-' . $jquery_version . '.min.js"><\/script>\');',
				(!empty($modSettings['jquery_include_ui']) ? '
		window.jQuery.ui || document.write(\'<script src="' . $settings['default_theme_url'] . '/scripts/jquery-ui-' . $jqueryui_version . '.min.js"><\/script>\')' : ''), '
	</script>';
				break;
		}
	}

	/**
	 * Loads the JS files that have been set in the controllers
	 *
	 * - Will combine / minify the files if the option is set.
	 * - Handles files that are output in template_html_above <head> section
	 * - Clears all files from $this->js_files so that it can be called multiple times.  Current
	 * this is called from here and then again in index.template (for files added by templates)
	 */
	protected function templateJavascriptFiles()
	{
		global $modSettings, $settings;

		if (empty($this->js_files))
		{
			return;
		}

		// Combine javascript
		if (!empty($modSettings['combine_css_js']))
		{
			// Maybe minify as well
			$minify = !empty($modSettings['minify_css_js']);
			$combiner = new SiteCombiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url'], $minify);
			$combine_standard_name = $combiner->site_js_combine($this->js_files, false);
			$combine_deferred_name = $combiner->site_js_combine($this->js_files, true);

			call_integration_hook('post_javascript_combine', array(&$combine_standard_name, &$combine_deferred_name, $combiner));

			if (!empty($combine_standard_name))
			{
				echo '
	<script src="', $combine_standard_name, '" id="jscombined_top"></script>';
			}

			if (!empty($combine_deferred_name))
			{
				echo '
	<script src="', $combine_deferred_name, '" id="jscombined_deferred" defer="defer"></script>';
			}

			// While we have any remaining Javascript files, (not local etc)
			$this->outputJavascriptFiles($combiner->getSpares());
		}
		// Just want to minify and not combine
		elseif (!empty($modSettings['minify_css_js']))
		{
			$combiner = new SiteCombiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url']);
			$this->js_files = $combiner->site_js_minify($this->js_files);

			// Output all the files
			$this->outputJavascriptFiles($this->js_files);
		}
		// Not combining or minifying, just give them the original files
		else
		{
			$this->outputJavascriptFiles($this->js_files);
		}

		// Reset, templates can still add _files_, but they will be output in template_html_below.
		$this->js_files = [];
	}

	/**
	 * Outputs script tags to the template with appropriate defer, async or void attributes
	 *
	 * Called from template_html_above to output JS defined in the *CONTROLLERS*
	 * Called from template_html_below to output JS defined in the *TEMPLATES*.
	 *
	 * @param array $files
	 * @return void
	 */
	public function outputJavascriptFiles($files)
	{
		// While we have Javascript files to place in the template
		foreach ($files as $id => $js_file)
		{
			$async = !empty($js_file['options']['async']) ? ' async="async"' : '';
			$defer = !empty($js_file['options']['defer']) ? ' defer="defer"' : '';

			echo '
	<script src="', $js_file['filename'], '" id="', $id, '"', $async, $defer, '></script>';
		}
	}

	/**
	 * Inline JavaScript - Actually useful sometimes!
	 *
	 * @param bool $do_deferred if true outputs the inline JS that was marked as deferred.
	 * @return void
	 */
	public function template_inline_javascript($do_deferred = false, $tabs = 3)
	{
		if (empty($this->js_inline))
		{
			return;
		}

		// Deferred output waits until we are deferring !
		if (!empty($this->js_inline['defer']) && $do_deferred)
		{
			$output = $this->formatInlineJS($this->js_inline['defer'], $tabs);
		}

		// Standard header output
		if (!empty($this->js_inline['standard']) && !$do_deferred)
		{
			$output = $this->formatInlineJS($this->js_inline['standard'], $tabs);
		}

		// Output the script
		if (!empty($output))
		{
			echo '
	<script id="site_inline', $do_deferred ? '_deferred"' : '"', '>
		', implode("\n" . str_repeat("\t", $tabs), $output), '
	</script>';
		}
	}

	/**
	 * Function to either compress or pretty indent inline JS
	 *
	 * @param array $files
	 * @param int $tabs
	 *
	 * @return array
	 */
	private function formatInlineJS($files, $tabs = 3)
	{
		global $modSettings;

		// Scrunch
		if (!empty($modSettings['minify_css_js']))
		{
			// Inline can have user prefs etc. so caching is not a viable option
			// Benchmarked: at 0.01627s wall clock, 16.26ms for computations, 42% size reduction
			// for large load, 10.3ms (.0104s) for normal sized inline.
			require_once(EXTDIR . '/JSqueeze.php');
			$jsqueeze = new JSqueeze();

			foreach ($files as $i => $js_block)
			{
				$files[$i] = $jsqueeze->squeeze($js_block);
			}

			return $files;
		}

		// Or pretty
		foreach ($files as $i => $js_block)
		{
			// Lines in this block
			$lines = explode("\n", $js_block);

			// One liner, just indent
			if (count($lines) === 1)
			{
				$files[$i] = str_repeat("\t", $tabs) . ltrim($js_block);
				continue;
			}

			// Current number of leading tabs due to source indenting
			$num = strspn($lines[1], "\t");
			$existing = str_repeat("\t", $num);
			$new = str_repeat("\t", $tabs);

			// Replace existing leading tabs with new count, allowing for excess of that
			foreach ($lines as $j => $line)
			{
				$pos = strpos($line, $existing);
				if ($pos === 0)
				{
					$lines[$j] = substr_replace($line, $new, 0, $num);
				}
				else
				{
					$lines[$j] = $new . ltrim($line);
				}
			}

			// Done
			$files[$i] = implode("\n", $lines);
		}

		return $files;
	}

	/**
	 * Deletes the hives (aggregated CSS and JS files) previously created.
	 *
	 * @param string $type           = 'all' Filters the types of hives (valid values:
	 *                               * 'all'
	 *                               * 'css'
	 *                               * 'js'
	 *
	 * @return bool
	 */
	public function cleanHives($type = 'all')
	{
		global $settings;

		$combiner = new SiteCombiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url']);
		$result = true;

		if ($type === 'all' || $type === 'css')
		{
			$result &= $combiner->removeCssHives();
		}

		if ($type === 'all' || $type === 'js')
		{
			$result &= $combiner->removeJsHives();
		}

		return $result;
	}

	/**
	 * Output the CSS files
	 *
	 * What it does:
	 *  - If the admin option to combine files is set, will use Combiner.class
	 */
	public function template_css()
	{
		global $modSettings, $settings;

		// Use this hook to work with CSS files pre output
		call_integration_hook('pre_css_output');

		if (empty($this->css_files))
		{
			return;
		}

		// Combine the CSS files?
		if (!empty($modSettings['combine_css_js']))
		{
			// Minify?
			$minify = !empty($modSettings['minify_css_js']);
			$combiner = new SiteCombiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url'], $minify);
			$combine_name = $combiner->site_css_combine($this->css_files);

			call_integration_hook('post_css_combine', array(&$combine_name, $combiner));

			if (!empty($combine_name))
			{
				echo '
	<link rel="stylesheet" href="', $combine_name, '" id="csscombined" />';
			}

			foreach ($combiner->getSpares() as $id => $file)
			{
				echo '
	<link rel="stylesheet" href="', $file['filename'], '" id="', $id, '" />';
			}
		}
		// Minify and not combine
		elseif (!empty($modSettings['minify_css_js']))
		{
			$combiner = new SiteCombiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url']);
			$this->css_files = $combiner->site_css_minify($this->css_files);

			// Output all the files
			foreach ($this->css_files as $id => $file)
			{
				echo '
	<link rel="stylesheet" href="', $file['filename'], '" id="', $id, '" />';
			}
		}
		// Just the original files
		else
		{
			foreach ($this->css_files as $id => $file)
			{
				echo '
	<link rel="stylesheet" href="', $file['filename'], '" id="', $id, '" />';
			}
		}
	}

	/**
	 * Output the inline-CSS in a style tag
	 */
	public function template_inlinecss()
	{
		$style_tag = '';

		// Combine and minify the CSS files to save bandwidth and requests?
		if (!empty($this->css_rules))
		{
			if (!empty($this->css_rules['all']))
			{
				$style_tag .= '
	' . $this->css_rules['all'];
			}

			if (!empty($this->css_rules['media']))
			{
				foreach ($this->css_rules['media'] as $key => $val)
				{
					$style_tag .= '
	@media ' . $key . '{
		' . $val . '
	}';
				}
			}
		}

		if (!empty($style_tag))
		{
			echo '
	<style>
		' . trim($style_tag) . '
	</style>';
		}
	}

	/**
	 * Calls on template_show_error from index.template.php to show warnings
	 * and security errors for admins
	 */
	public function template_admin_warning_above()
	{
		global $context, $txt;

		if (!empty($context['security_controls_files']))
		{
			$context['security_controls_files']['type'] = 'serious';
			template_show_error('security_controls_files');
		}

		if (!empty($context['security_controls_query']))
		{
			$context['security_controls_query']['type'] = 'serious';
			template_show_error('security_controls_query');
		}

		if (!empty($context['security_controls_ban']))
		{
			$context['security_controls_ban']['type'] = 'serious';
			template_show_error('security_controls_ban');
		}

		if (!empty($context['new_version_updates']))
		{
			template_show_error('new_version_updates');
		}

		if (!empty($context['accepted_agreement']))
		{
			template_show_error('accepted_agreement');
		}

		// Any special notices to remind the admin about?
		if (!empty($context['warning_controls']))
		{
			$context['warning_controls']['errors'] = $context['warning_controls'];
			$context['warning_controls']['title'] = $txt['admin_warning_title'];
			$context['warning_controls']['type'] = 'warning';
			template_show_error('warning_controls');
		}
	}

	/**
	 * Load the base JS that gives Elkarte a nice rack
	 */
	public function loadThemeJavascript()
	{
		global $settings, $context, $modSettings, $scripturl, $txt, $options;

		// Queue our Javascript
		loadJavascriptFile(array('script.js', 'script_elk.js'));
		loadJavascriptFile(array('elk_jquery_plugins.js', 'theme.js'), ['defer' => true]);

		// Default JS variables for use in every theme
		$this->addJavascriptVar(array(
			'elk_theme_url' => JavaScriptEscape($settings['theme_url']),
			'elk_default_theme_url' => JavaScriptEscape($settings['default_theme_url']),
			'elk_images_url' => JavaScriptEscape($settings['images_url']),
			'elk_smiley_url' => JavaScriptEscape($modSettings['smileys_url']),
			'elk_scripturl' => '\'' . $scripturl . '\'',
			'elk_iso_case_folding' => detectServer()->is('iso_case_folding') ? 'true' : 'false',
			'elk_charset' => '"UTF-8"',
			'elk_session_id' => JavaScriptEscape($context['session_id']),
			'elk_session_var' => JavaScriptEscape($context['session_var']),
			'elk_member_id' => $context['user']['id'],
			'ajax_notification_text' => JavaScriptEscape($txt['ajax_in_progress']),
			'ajax_notification_cancel_text' => JavaScriptEscape($txt['modify_cancel']),
			'help_popup_heading_text' => JavaScriptEscape($txt['help_popup']),
			'use_click_menu' => !empty($options['use_click_menu']) ? 'true' : 'false',
			'todayMod' => !empty($modSettings['todayMod']) ? (int) $modSettings['todayMod'] : 0)
		);

		// Auto video embedding enabled, then load the needed JS
		$this->autoEmbedVideo();

		// Prettify code tags? Load the needed JS and CSS.
		$this->addCodePrettify();

		// Relative times for posts?
		$this->relativeTimes();

		// If we think we have mail to send, let's offer up some possibilities... robots get pain (Now with scheduled task support!)
		if (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time() ||
			(!empty($modSettings['mail_next_send']) && $modSettings['mail_next_send'] < time() && empty($modSettings['mail_queue_use_cron'])))
		{
			$this->doScheduledSendMail();
		}
	}

	/**
	 * If video embedding is enabled, this loads the needed JS and vars
	 */
	public function autoEmbedVideo()
	{
		global $txt, $modSettings;

		if (!empty($modSettings['enableVideoEmbeding']))
		{
			loadJavascriptFile('elk_jquery_embed.js', array('defer' => true));

			$this->addInlineJavascript('
				const oEmbedtext = ({
					embed_limit : ' . (!empty($modSettings['video_embed_limit']) ? $modSettings['video_embed_limit'] : 25) . ',
					preview_image : ' . JavaScriptEscape($txt['preview_image']) . ',
					ctp_video : ' . JavaScriptEscape($txt['ctp_video']) . ',
					hide_video : ' . JavaScriptEscape($txt['hide_video']) . ',
					youtube : ' . JavaScriptEscape($txt['youtube']) . ',
					vimeo : ' . JavaScriptEscape($txt['vimeo']) . ',
					dailymotion : ' . JavaScriptEscape($txt['dailymotion']) . ',
					tiktok : ' . JavaScriptEscape($txt['tiktok']) . ',
					twitter : ' . JavaScriptEscape($txt['twitter']) . ',
					facebook : ' . JavaScriptEscape($txt['facebook']) . ',
					instagram : ' . JavaScriptEscape($txt['instagram']) . ',
				});
				document.addEventListener("DOMContentLoaded", () => {$().linkifyvideo(oEmbedtext);});', true);
		}
	}

	/**
	 * If the option to pretty output code is on, this loads the JS and CSS
	 */
	public function addCodePrettify()
	{
		global $modSettings;

		if (!empty($modSettings['enableCodePrettify']))
		{
			$this->loadVariant('prettify');
			loadJavascriptFile('prettify.min.js', array('defer' => true));

			$this->addInlineJavascript('
				document.addEventListener("DOMContentLoaded", () => {prettyPrint();});', true);
		}
	}

	/**
	 * Load a variant css file if found.  Fallback if not and it exists in this
	 * theme's directory
	 *
	 * @param string $cssFile
	 * @param boolean $fallBack
	 */
	public function loadVariant($cssFile, $fallBack = true)
	{
		global $settings, $context;

		$fileFunc = FileFunctions::instance();
		if ($fileFunc->fileExists($settings['theme_dir'] . '/css/' . $context['theme_variant'] . '/' . $cssFile . $context['theme_variant'] . '.css'))
		{
			loadCSSFile($context['theme_variant'] . '/' . $cssFile . $context['theme_variant'] . '.css');
			return;
		}

		if ($fallBack && $fileFunc->fileExists($settings['theme_dir'] . '/css/' . $cssFile . '.css'))
		{
			loadCSSFile($cssFile . '.css');
		}
	}

	/**
	 * Relative times require a few variables be set in the JS
	 */
	public function relativeTimes()
	{
		global $modSettings, $context, $txt;

		// Relative times?
		if (!empty($modSettings['todayMod']) && $modSettings['todayMod'] > 2)
		{
			$this->addInlineJavascript('
				const oRttime = ({
					referenceTime : ' . forum_time() * 1000 . ',
					now : ' . JavaScriptEscape($txt['rt_now']) . ',
					minute : ' . JavaScriptEscape($txt['rt_minute']) . ',
					minutes : ' . JavaScriptEscape($txt['rt_minutes']) . ',
					hour : ' . JavaScriptEscape($txt['rt_hour']) . ',
					hours : ' . JavaScriptEscape($txt['rt_hours']) . ',
					day : ' . JavaScriptEscape($txt['rt_day']) . ',
					days : ' . JavaScriptEscape($txt['rt_days']) . ',
					week : ' . JavaScriptEscape($txt['rt_week']) . ',
					weeks : ' . JavaScriptEscape($txt['rt_weeks']) . ',
					month : ' . JavaScriptEscape($txt['rt_month']) . ',
					months : ' . JavaScriptEscape($txt['rt_months']) . ',
					year : ' . JavaScriptEscape($txt['rt_year']) . ',
					years : ' . JavaScriptEscape($txt['rt_years']) . ',
				});
				document.addEventListener("DOMContentLoaded", () => {updateRelativeTime();});', true);

			$context['using_relative_time'] = true;
		}
	}

	/**
	 * Ensures we kick the mail queue from time to time so that it gets
	 * checked as often as possible.
	 */
	public function doScheduledSendMail()
	{
		global $modSettings;

		if (!empty(User::$info->possibly_robot))
		{
			// @todo Maybe move this somewhere better?!
			$controller = new ScheduledTasks(new EventManager());

			// What to do, what to do?!
			if (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
			{
				$controller->action_autotask();
			}
			else
			{
				$controller->action_reducemailqueue();
			}
		}
		else
		{
			$type = empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time() ? 'task' : 'mailq';
			$ts = $type === 'mailq' ? $modSettings['mail_next_send'] : $modSettings['next_task_time'];

			$this->addInlineJavascript('
		function elkAutoTask()
		{
			let tempImage = new Image();
			tempImage.src = elk_scripturl + "?scheduled=' . $type . ';ts=' . $ts . '";
		}
		window.setTimeout("elkAutoTask();", 1);', true);
		}
	}

	/**
	 * Makes the default layers and languages available
	 *
	 * - Loads index and addon language files as needed
	 * - Loads xml, index or no templates as needed
	 * - Loads templates as defined by $settings['theme_templates']
	 */
	public function loadDefaultLayers()
	{
		global $settings;

		$simpleActions = array(
			'quickhelp',
			'printpage',
			'quotefast',
		);

		call_integration_hook('integrate_simple_actions', array(&$simpleActions));

		// Output is fully XML
		$api = $this->_req->getRequest('api', 'trim', '');
		$action = $this->_req->getRequest('action', 'trim', '');

		if ($api === 'xml')
		{
			Txt::load('index+Addons');
			$this->getLayers()->removeAll();
			$this->getTemplates()->load('Xml');
		}
		// These actions don't require the index template at all.
		elseif (in_array($action, $simpleActions))
		{
			Txt::load('index+Addons');
			$this->getLayers()->removeAll();
		}
		else
		{
			// Custom templates to load, or just default?
			$templates = isset($settings['theme_templates']) ? explode(',', $settings['theme_templates']) : ['index'];

			// Load each template...
			foreach ($templates as $template)
			{
				$this->getTemplates()->load($template);
			}

			// ...and attempt to load their associated language files.
			Txt::load(array_merge($templates, ['Addons']), false);

			// Custom template layers?
			$layers = isset($settings['theme_layers']) ? explode(',', $settings['theme_layers']) : ['html', 'body'];

			$template_layers = $this->getLayers();
			$template_layers->setErrorSafeLayers($layers);
			foreach ($layers as $layer)
			{
				$template_layers->addBegin($layer);
			}
		}
	}

	/**
	 * If a variant CSS is needed, this loads it
	 */
	public function loadThemeVariant()
	{
		global $context, $settings, $options;

		// Overriding - for previews and that ilk.
		$variant = $this->_req->getRequest('variant', 'trim', '');
		if (!empty($variant))
		{
			$_SESSION['id_variant'] = $variant;
		}

		// User selection?
		if (empty($settings['disable_user_variant']) || allowedTo('admin_forum'))
		{
			$context['theme_variant'] = !empty($_SESSION['id_variant']) ? $_SESSION['id_variant'] : (!empty($options['theme_variant']) ? $options['theme_variant'] : '');
		}

		// If not a user variant, select the default.
		if ($context['theme_variant'] === '' || !in_array($context['theme_variant'], $settings['theme_variants']))
		{
			$context['theme_variant'] = !empty($settings['default_variant']) && in_array($settings['default_variant'], $settings['theme_variants']) ? $settings['default_variant'] : $settings['theme_variants'][0];
		}

		// Do this to keep things easier in the templates.
		$context['theme_variant'] = '_' . $context['theme_variant'];
		$context['theme_variant_url'] = $context['theme_variant'] . '/';

		// The most efficient way of writing multi themes is to use a master index.css plus variant.css files.
		if (!empty($context['theme_variant']))
		{
			loadCSSFile($context['theme_variant'] . '/index' . $context['theme_variant'] . '.css');

			// Variant icon definitions?
			$this->loadVariant('icons_svg', false);

			// Load a theme variant custom CSS
			$this->loadVariant('custom', false);
		}
	}
}
