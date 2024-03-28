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

use ElkArte\Helper\FileFunctions;
use ElkArte\Http\Headers;
use ElkArte\Menu\MenuContext;
use ElkArte\Themes\Theme as BaseTheme;

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
		return [
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
			'theme_variants' => [
				'light',
				'besocial',
				'dark',
			],

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
			'menu_numeric_notice' => [
				// Top level menu entries
				0 => ' <span class="pm_indicator">%1$s</span>',
				// First dropdown
				1 => ' <span>[<strong>%1$s</strong>]</span>',
				// Second level dropdown
				2 => ' <span>[<strong>%1$s</strong>]</span>',
			],

			// This array deals with page indexes.
			'page_index_template' => [
				'base_link' => '<li class="linavPages"><a class="navPages" href="{base_link}">%2$s</a></li>',
				'previous_page' => '<span class="previous_page">{prev_txt}</span>',
				'current_page' => '<li class="linavPages"><strong class="current_page">%1$s</strong></li>',
				'next_page' => '<span class="next_page">{next_txt}</span>',
				'expand_pages' => '<li class="linavPages expand_pages" {custom}> <a href="#">&#8230;</a> </li>',
				'all' => '<span class="linavPages all_pages">{all_txt}</span>',
				'none' => '<li class="hide"><a href="#"></a></li>',
			],

			// @todo find a better place if we are going to create a notifications template
			'mentions' => [
				'mentioner_template' => '<a href="{mem_url}" class="mentionavatar">{avatar_img}{mem_name}</a>',
			]
		];
	}

	/**
	 * The header template
	 */
	public function template_header(): void
	{
		doSecurityChecks();

		$this->setupThemeContext();

		$header = Headers::instance();
		$this->setupHeadersExpiration($header);
		$this->setupHeadersContentType($header, $this->getRequestAPI());

		foreach ($this->getLayers()->prepareContext() as $layer)
		{
			$this->getTemplates()->loadSubTemplate($layer . '_above', 'ignore');
		}

		$this->loadDefaultThemeSettings();

		$header->sendHeaders();
	}

	/**
	 * Sets up the basic theme context.
	 *
	 * @param bool $forceload = false
	 */
	public function setupThemeContext($forceload = false)
	{
		global $context;

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
		$context['robot_no_index'] = in_array($context['current_action'], $this->no_index_actions, true);
		$context['additional_dropdown_search'] = prepareSearchEngines();

		$this->setupNewsLines();
		$this->setupCurrentUserContext();
		(new MenuContext())->setupMenuContext();
		$this->setContextShowPmPopup();
		$this->setContextCommonStats();
		$this->setContextThemeData();
		$this->loadCustomCSS();
	}

	/**
	 * Sets up the information context for the current user
	 *
	 * What it does:
	 * - Sets the current time and current action
	 * - Checks if the current action should be indexed by robots
	 * - Calls setupLoggedUserContext if the user is not a guest
	 * - Calls setupGuestContext if the user is a guest
	 * - Checks if the PM popup should be shown and adds the necessary JavaScript code
	 */
	public function setupCurrentUserContext()
	{
		global $scripturl, $context, $options, $txt;

		$context['current_time'] = standardTime(time(), false);
		$context['current_action'] = $this->_req->getQuery('action', 'trim', '');
		$context['robot_no_index'] = in_array($context['current_action'], $this->no_index_actions, true);

		if ($this->user->is_guest === false)
		{
			$this->setupLoggedUserContext();
		}
		else
		{
			$this->setupGuestContext();
		}

		$context['show_pm_popup'] = $context['user']['popup_messages'] && !empty($options['popup_messages']) && $context['current_action'] !== 'pm';
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
	}

	/**
	 * Load custom CSS files and add CSS rules
	 *
	 * What it does:
	 *  - loads custom.css if it exists for the theme
	 *  - Adds avatar resize rules
	 *  - Adds forum wrapper width (can use important to override in theme css)
	 *  - Sets show more quote rules (localization & --quote_height)
	 *  - Sets the profile button avatar
	 *
	 * @return void
	 */
	public function loadCustomCSS()
	{
		global $settings, $modSettings, $txt;

		// Load a base theme custom CSS file?
		$fileFunc = FileFunctions::instance();
		if ($fileFunc->fileExists($settings['theme_dir'] . '/css/custom.css'))
		{
			loadCSSFile('custom.css');
		}

		// Since it's nice to have avatars all the same size, and in some cases the size detection may fail,
		// let's add the css in any case
		if (!empty($modSettings['avatar_max_width']) || !empty($modSettings['avatar_max_height']))
		{
			$this->css->addCSSRules('
		.avatarresize {' . (empty($modSettings['avatar_max_width']) ? '' : '
			max-width:' . $modSettings['avatar_max_width'] . 'px;') . (empty($modSettings['avatar_max_height']) ? '' : '
			max-height:' . $modSettings['avatar_max_height'] . 'px;') . '
		}');
		}

		// Save some database hits, if a width for multiple wrappers is set in admin.
		if (!empty($settings['forum_width']))
		{
			$this->css->addCSSRules('
		.wrapper {width: ' . $settings['forum_width'] . ';}');
		}

		// Localization for the show more quote and its container height
		$quote_height = empty($modSettings['heightBeforeShowMore']) ? 'none' : $modSettings['heightBeforeShowMore'] . 'px';
		$this->css->addCSSRules('
		input[type=checkbox].quote-show-more:after {content: "' . $txt['quote_expand'] . '";}
		.quote-read-more > .bbc_quote {--quote_height: ' . $quote_height . ';}'
		);

		if (!empty($this->user->avatar['href']))
		{
			$this->css->addCSSRules('
		.i-menu-profile::before, .i-menu-profile.enabled::before {
			content: "";
			background-image: url("' . htmlspecialchars_decode($this->user->avatar['href']) . '");
			background-position: center;
			filter: unset;
		}');
		}
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
	 * Load the base JS that gives Elkarte a nice rack
	 */
	public function loadThemeJavascript()
	{
		global $settings, $context, $modSettings, $scripturl, $txt, $options;

		// Queue our Javascript
		loadJavascriptFile(['script.js', 'script_elk.js', 'elk_menu.js']);
		loadJavascriptFile(['theme.js'], ['defer' => true]);

		// Default JS variables for use in every theme
		$this->addJavascriptVar([
			'elk_theme_url' => JavaScriptEscape($settings['theme_url']),
			'elk_default_theme_url' => JavaScriptEscape($settings['default_theme_url']),
			'elk_images_url' => JavaScriptEscape($settings['images_url']),
			'elk_smiley_url' => JavaScriptEscape($modSettings['smileys_url']),
			'elk_scripturl' => "'" . $scripturl . "'",
			'elk_charset' => '"UTF-8"',
			'elk_session_id' => JavaScriptEscape($context['session_id']),
			'elk_session_var' => JavaScriptEscape($context['session_var']),
			'elk_member_id' => $context['user']['id'],
			'ajax_notification_text' => JavaScriptEscape($txt['ajax_in_progress']),
			'ajax_notification_cancel_text' => JavaScriptEscape($txt['modify_cancel']),
			'help_popup_heading_text' => JavaScriptEscape($txt['help_popup']),
			'use_click_menu' => empty($options['use_click_menu']) ? 'false' : 'true',
			'todayMod' => empty($modSettings['todayMod']) ? 0 : (int) $modSettings['todayMod']]
		);

		// PWA?
		$this->progressiveWebApp();

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
}
