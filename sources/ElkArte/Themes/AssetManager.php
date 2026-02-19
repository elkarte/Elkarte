<?php

/**
 * Asset management for themes - CSS, JavaScript, and variants
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Themes;

use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\HttpReq;
use ElkArte\Helper\SiteCombiner;

/**
 * Class AssetManager
 *
 * Handles CSS/JS loading, theme variants, caching, and PWA functionality
 */
class AssetManager
{
	/** @var Javascript JavaScript manager instance */
	private $javascript;

	/** @var Css CSS manager instance */
	private $css;

	/**
	 * AssetManager constructor
	 *
	 * @param Javascript $javascript
	 * @param Css $css
	 */
	public function __construct(Javascript $javascript, Css $css)
	{
		$this->javascript = $javascript;
		$this->css = $css;
	}

	/**
	 * Load custom CSS files and add CSS rules
	 *
	 * What it does:
	 *  - Loads base (not variant) custom.css if it exists for the theme
	 *  - Adds avatar resize rules
	 *  - Adds forum wrapper width (can use important to override in theme CSS)
	 *  - Sets show more quote rules (localization & --quote_height)
	 *  - Sets the profile button avatar
	 *
	 * @param array $user User data array
	 */
	public function loadCustomCSS(array $user): void
	{
		global $settings, $modSettings, $txt;

		// Load a base theme custom CSS file?
		$fileFunc = FileFunctions::instance();
		if ($fileFunc->fileExists($settings['theme_dir'] . '/css/custom.css'))
		{
			loadCSSFile('custom.css');
		}

		// Since it's nice to have avatars all the same size, and in some cases the size detection may fail,
		// let's add the CSS in any case
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

		// Localization for the show more quote and it's container height
		$quote_height = empty($modSettings['heightBeforeShowMore']) ? 'none' : $modSettings['heightBeforeShowMore'] . 'px';
		$this->css->addCSSRules('
		input[type=checkbox].quote-show-more:after {content: "' . $txt['quote_expand'] . '";}
		.quote-read-more > .bbc_quote {--quote_height: ' . $quote_height . ';}'
		);

		if (!empty($user['avatar']['href']))
		{
			$this->css->addCSSRules('
		.i-menu-profile::before, .i-menu-profile.enabled::before {
			content: "";
			background-image: url("' . htmlspecialchars_decode($user['avatar']['href']) . '");
			background-position: center;
			filter: unset;
		}');
		}
	}

	/**
	 * Load the base JS that gives ElkArte functionality
	 *
	 * What it does:
	 *  - Loads core JavaScript files
	 *  - Sets up default JS variables
	 *  - Initializes PWA, video embedding, code prettify, relative times
	 */
	public function loadThemeJavascript(): void
	{
		global $settings, $context, $modSettings, $scripturl, $txt, $options;

		// Queue our Javascript
		loadJavascriptFile(['script.js', 'script_elk.js', 'elk_menu.js']);
		loadJavascriptFile(['theme.js'], ['defer' => true]);

		// Default JS variables for use in every theme
		$this->javascript->addJavascriptVar([
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
	}

	/**
	 * Clean (delete) the hives (cache) for CSS and JS files
	 *
	 * @param string $type (Optional) The type of hives to clean. Default is 'all'. Possible values are 'all', 'css', 'js'.
	 * @return bool Returns true if the hives are successfully cleaned, otherwise false.
	 */
	public function cleanHives($type = 'all'): bool
	{
		global $settings;

		$combiner = new SiteCombiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url']);
		$result = true;

		if ($type === 'all' || $type === 'css')
		{
			$result = $combiner->removeCssHives();
		}

		if ($type === 'all' || $type === 'js')
		{
			$result = $result && $combiner->removeJsHives();
		}

		// Force a cache refresh for the PWA
		setPWACacheStale(true);

		return $result;
	}

	/**
	 * Load a variant CSS file if found.  Fallback if not, and it exists in this
	 * theme's directory
	 *
	 * @param string $cssFile
	 * @param bool $fallBack
	 */
	public function loadVariant($cssFile, $fallBack = true): void
	{
		global $settings, $context;

		$fileFunc = FileFunctions::instance();
		if ($fileFunc->fileExists($settings['theme_dir'] . '/css/' . $context['theme_variant'] . '/' . $cssFile . $context['theme_variant'] . '.css'))
		{
			loadCSSFile($context['theme_variant'] . '/' . $cssFile . $context['theme_variant'] . '.css');
			return;
		}

		if (!$fallBack)
		{
			return;
		}

		if (!$fileFunc->fileExists($settings['theme_dir'] . '/css/' . $cssFile . '.css'))
		{
			return;
		}

		loadCSSFile($cssFile . '.css');
	}

	/**
	 * Load the theme variant CSS file if needed
	 *
	 * What it does:
	 *  - Checks for a user-selected theme variant and loads it if allowed
	 *  - Falls back to the default variant if the selected one is not valid
	 *  - Loads the appropriate CSS files for the variant including
	 *    - custom_variant.css
	 *    - index_variant.css
	 *    - icons_svg_variant.css
	 *
	 * @param HttpReq $req Request object containing potential variant selection
	 */
	public function loadThemeVariant(HttpReq $req): void
	{
		global $context, $settings, $options;

		// Overriding - for previews and that ilk.
		$variant = $req->getRequest('variant', 'trim', '');
		if (!empty($variant))
		{
			$_SESSION['id_variant'] = $variant;
		}

		// User selection?
		if (empty($settings['disable_user_variant']) || allowedTo('admin_forum'))
		{
			$context['theme_variant'] = empty($_SESSION['id_variant']) ? (!empty($options['theme_variant']) ? $options['theme_variant'] : '') : ($_SESSION['id_variant']);
		}

		// If not a user variant, select the default.
		if ($context['theme_variant'] === '' || !in_array($context['theme_variant'], $settings['theme_variants'], true))
		{
			$context['theme_variant'] = !empty($settings['default_variant']) && in_array($settings['default_variant'], $settings['theme_variants'], true) ? $settings['default_variant'] : $settings['theme_variants'][0];
		}

		// Do this to keep things easier in the templates.
		$context['theme_variant'] = '_' . $context['theme_variant'];
		$context['theme_variant_url'] = $context['theme_variant'] . '/';

		// The most efficient way of writing multi themes is to use a master index.css plus variant.css files.
		if (!empty($context['theme_variant']))
		{
			// Load a theme variant custom CSS file if it exists for the theme (structural overrides)
			$this->loadVariant('custom', false);

			// Load variant CSS file for the theme (color overrides)
			loadCSSFile($context['theme_variant'] . '/index' . $context['theme_variant'] . '.css');

			// Variant icon definitions?
			$this->loadVariant('icons_svg', false);
		}
	}

	/**
	 * Progressive Web App initialization
	 *
	 * What it does:
	 *  - Sets up the necessary configurations for the Progressive Web App (PWA).
	 *  - Adds JavaScript variables, loads necessary JavaScript files, and adds inline JavaScript code.
	 *
	 * @return void
	 */
	public function progressiveWebApp(): void
	{
		global $modSettings, $boardurl, $settings;

		$this->javascript->addJavascriptVar([
			'elk_board_url' => JavaScriptEscape($boardurl),
		]);
		loadJavascriptFile('elk_pwa.js', ['defer' => false]);

		// Not enabled, let's be sure to remove it should it exist
		if (empty($modSettings['pwa_enabled']))
		{
			$this->javascript->addInlineJavascript('
				elkPwa().removeServiceWorker();
			');

			return;
		}

		setPWACacheStale();
		$theme_scope = $this->getScopeFromUrl($settings['actual_theme_url']);
		$default_theme_scope = $this->getScopeFromUrl($settings['default_theme_url']);
		$sw_scope = $this->getScopeFromUrl($boardurl);
		$this->javascript->addInlineJavascript('
			document.addEventListener("DOMContentLoaded", function() {
				let myOptions = {
					swUrl: "elkServiceWorker.js",
					swOpt: {
						cache_stale: ' . JavaScriptEscape(CACHE_STALE) . ',
						cache_id: ' . JavaScriptEscape($modSettings['elk_pwa_cache_stale']) . ',
						theme_scope: ' . JavaScriptEscape($theme_scope) . ',
						default_theme_scope: ' . JavaScriptEscape($default_theme_scope) . ',
						sw_scope: ' . JavaScriptEscape($sw_scope) . ',
						nav_preload: 1, // set to 1 to enable, 0 to disable
					}
				};
	
				let elkPwaInstance = elkPwa(myOptions);
				elkPwaInstance.init();
				elkPwaInstance.sendMessage("deleteOldCache", {cache_id: ' . JavaScriptEscape($modSettings['elk_pwa_cache_stale']) . '});
				elkPwaInstance.sendMessage("pruneCache");
			});'
		);
	}

	/**
	 * Get the scope from the given URL
	 *
	 * @param string $url The URL from which to extract the scope
	 *
	 * @return string The scope extracted from the URL, or the root scope if not found
	 */
	public function getScopeFromUrl($url): string
	{
		$parts = parse_url($url);

		return empty($parts['path']) ? '/' : '/' . trim($parts['path'], '/') . '/';
	}
}
