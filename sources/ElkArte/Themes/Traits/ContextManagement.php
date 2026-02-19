<?php

/**
 * Context management functionality for themes.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Themes\Traits;

use BBC\ParserWrapper;
use ElkArte\Helper\Util;
use ElkArte\Menu\MenuContext;

/**
 * Trait ContextManagement
 *
 * Handles setting up theme and user context data
 */
trait ContextManagement
{
	/**
	 * Sets up the basic theme context.
	 *
	 * What it does:
	 *  - Sets the current time and action
	 *  - Checks if the current action should be indexed by robots
	 *  - Prepares search engines dropdown
	 *  - Sets up news lines, user context, menu, PM popup, common stats, theme data
	 *  - Loads custom CSS
	 *
	 * @param bool $forceload = false
	 */
	public function setupThemeContext($forceload = false): void
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
		$this->assetManager->loadCustomCSS($this->user->toArray());
	}

	/**
	 * Sets up the information context for the current user
	 *
	 * What it does:
	 *  - Sets the current time and current action
	 *  - Checks if the current action should be indexed by robots
	 *  - Calls setupLoggedUserContext if the user is not a guest
	 *  - Calls setupGuestContext if the user is a guest
	 *  - Checks if the PM popup should be shown and adds the necessary JavaScript code
	 */
	public function setupCurrentUserContext(): void
	{
		global $context;

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

		$this->setContextShowPmPopup();
	}

	/**
	 * Set up the logged user context
	 *
	 * What it does:
	 *  - Copies relevant user data from the user object to the global context.
	 */
	public function setupLoggedUserContext(): void
	{
		global $context;

		$context['user']['messages'] = $this->user->messages;
		$context['user']['unread_messages'] = $this->user->unread_messages;
		$context['user']['mentions'] = $this->user->mentions;

		// Personal message popup...
		$context['user']['popup_messages'] = $this->user->unread_messages > ($_SESSION['unread_messages'] ?? 0);

		$_SESSION['unread_messages'] = $this->user->unread_messages;

		$context['user']['avatar'] = [
			'href' => empty($this->user->avatar['href']) ? '' : $this->user->avatar['href'],
			'image' => empty($this->user->avatar['image']) ? '' : $this->user->avatar['image'],
		];

		// Figure out how long they've been logged in.
		$context['user']['total_time_logged_in'] = [
			'days' => floor($this->user->total_time_logged_in / 86400),
			'hours' => floor(($this->user->total_time_logged_in % 86400) / 3600),
			'minutes' => floor(($this->user->total_time_logged_in % 3600) / 60)
		];
	}

	/**
	 * Set up guest context
	 *
	 * What it does:
	 *  - Initializes global variables for guest user context
	 */
	public function setupGuestContext(): void
	{
		global $modSettings, $context, $txt;

		$context['user']['messages'] = 0;
		$context['user']['unread_messages'] = 0;
		$context['user']['mentions'] = 0;
		$context['user']['avatar'] = [];
		$context['user']['total_time_logged_in'] = ['days' => 0, 'hours' => 0, 'minutes' => 0];
		$context['user']['popup_messages'] = false;

		if (!empty($modSettings['registration_method']) && (int) $modSettings['registration_method'] === 1)
		{
			$txt['welcome_guest'] .= $txt['welcome_guest_activate'];
		}

		$txt['welcome_guest'] = replaceBasicActionUrl($txt['welcome_guest']);
	}

	/**
	 * Set the common stats in the context
	 *
	 * What it does:
	 *  - Sets the total posts, total topics, total members, and latest member stats in the common_stats array of the context
	 *  - Sets the formatted string for displaying the total posts in the boardindex_total_posts variable of the context
	 */
	public function setContextCommonStats(): void
	{
		global $context, $txt, $modSettings;

		// This looks weird, but it's because BoardIndex.controller.php references the variable.
		$href = getUrl('profile', ['action' => 'profile', 'u' => $modSettings['latestMember'], 'name' => $modSettings['latestRealName']]);

		$context['common_stats'] = [
			'total_posts' => comma_format($modSettings['totalMessages']),
			'total_topics' => comma_format($modSettings['totalTopics']),
			'total_members' => comma_format($modSettings['totalMembers']),
			'latest_member' => [
				'id' => $modSettings['latestMember'],
				'name' => $modSettings['latestRealName'],
				'href' => $href,
				'link' => '<a href="' . $href . '">' . $modSettings['latestRealName'] . '</a>',
			],
		];

		$context['common_stats']['boardindex_total_posts'] = sprintf($txt['boardindex_total_posts'], $context['common_stats']['total_posts'], $context['common_stats']['total_topics'], $context['common_stats']['total_members']);
	}

	/**
	 * Set the context for showing the PM popup
	 *
	 * What it does:
	 *  - Sets the context variable $context['show_pm_popup'] based on user preferences and current action
	 */
	public function setContextShowPmPopup(): void
	{
		global $context, $options, $txt, $scripturl;

		// This is done to allow theme authors to customize it as they want.
		$context['show_pm_popup'] = $context['user']['popup_messages'] && !empty($options['popup_messages']) && $context['current_action'] !== 'pm';

		// Add the PM popup. Theme authors can still override it as needed.
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
	 * Set the context theme data
	 *
	 * What it does:
	 *  - Sets the theme data in the context array
	 *  - Adds necessary JavaScript variables
	 *  - Sets the page title and favicon
	 *  - Updates the HTML headers
	 */
	public function setContextThemeData(): void
	{
		global $context, $scripturl, $settings, $boardurl, $modSettings, $txt, $mbname;

		if (empty($settings['theme_version']))
		{
			$this->addJavascriptVar(['elk_scripturl' => $scripturl], true);
		}

		$this->addJavascriptVar(['elk_forum_action' => getUrlQuery('action', $modSettings['default_forum_action'] ?? [])], true);

		$context['page_title'] = $context['page_title'] ?? $mbname;
		$context['page_title_html_safe'] = Util::htmlspecialchars(un_htmlspecialchars($context['page_title'])) . (empty($context['current_page']) ? '' : ' - ' . $txt['page'] . (' ' . ($context['current_page'] + 1)));
		$context['favicon'] = $boardurl . '/favicon.ico';
		$context['apple_touch'] = $boardurl . '/themes/default/images/logos/apple-touch-icon.png';
		$context['html_headers'] = $context['html_headers'] ?? '';
		$context['theme-color'] = $modSettings['pwa_theme-color'] ?? '#3d6e32';
		$context['pwa_manifest_enabled'] = !empty($modSettings['pwa_manifest_enabled']);
	}

	/**
	 * Sets up the news lines for display
	 *
	 * What it does:
	 *  - Retrieves the news lines from the modSettings variable
	 *  - Filters out empty lines and trims whitespace
	 *  - Parses the news lines using the BBC parser
	 *  - Sets a random news line as the 'random_news_line' variable in the context
	 *  - Adds the 'news_fader' callback to the 'upper_content_callbacks' array in the context
	 *  - Sets the 'show_news' variable in the context based on the 'enable_news' setting in $settings
	 */
	public function setupNewsLines(): void
	{
		global $context, $modSettings, $settings;

		$context['news_lines'] = array_filter(explode("\n", str_replace("\r", '', trim(addslashes($modSettings['news'])))));
		$bbc_parser = ParserWrapper::instance();
		foreach ($context['news_lines'] as $i => $iValue)
		{
			if (trim($iValue) === '')
			{
				continue;
			}

			$context['news_lines'][$i] = $bbc_parser->parseNews(stripslashes(trim($iValue)));
		}

		if (empty($context['news_lines']))
		{
			return;
		}

		$context['random_news_line'] = $context['news_lines'][mt_rand(0, count($context['news_lines']) - 1)];
		$context['upper_content_callbacks'][] = 'news_fader';

		// This is here because old index templates might still use it.
		$context['show_news'] = !empty($settings['enable_news']);
	}
}
