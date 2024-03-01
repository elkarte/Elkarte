<?php

/**
 * The main ThemeLoader class
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Themes;

use ElkArte\Cache\Cache;
use ElkArte\ext\Composer\Autoload\ClassLoader;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\HttpReq;
use ElkArte\Helper\Util;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Hooks;
use ElkArte\Languages\Txt;
use ElkArte\User;
use ElkArte\UserInfo;

/**
 * The ThemeLoader class is responsible for loading and initializing themes in ElkArte.
 */
class ThemeLoader
{
	/** @var Directories The list of directories. */
	protected static $dirs;

	/** @var ValuesContainer */
	public $user;

	/** @var string[] Theme items we shouldn't be able to change */
	protected $immutable_theme_data = [
		'actual_theme_url',
		'actual_images_url',
		'base_theme_dir',
		'base_theme_url',
		'default_images_url',
		'default_theme_dir',
		'default_theme_url',
		'default_template',
		'images_url',
		'number_recent_posts',
		'smiley_sets_default',
		'theme_dir',
		'theme_id',
		'theme_layers',
		'theme_templates',
		'theme_url',
	];

	/** @var Theme The current theme. */
	private $theme;

	/**
	 * Load a theme, by ID.
	 *
	 * What it does:
	 * - identify the theme to be loaded.
	 * - Checks that the theme is valid and that the user has permission to use it
	 * - load the users theme settings and site settings into $options.
	 * - prepares the list of folders to search for template loading.
	 * - sets up $context['user']
	 * - detects the users browser and sets a mobile friendly environment if needed
	 * - loads default JS variables for use in every theme
	 * - loads default JS scripts for use in every theme
	 *
	 * @param int $id = 0
	 * @param bool $initialize = true
	 */
	public function __construct(private $id = 0, $initialize = true)
	{
		global $context;

		$this->user = User::$info;

		$this->initTheme();
		if (!$initialize)
		{
			return;
		}

		$this->loadThemeUrls();

		// Load various user and server values into context.
		loadUserContext();
		detectServer();

		// Fetch/Set theme and min-max window preferences
		$this->setAdminPreferences();
		$this->setUserPreferences();

		$this->setupContext();
		$this->loadThemeSettings();
		$this->loadThemeVariantAndCSS();
		$this->processAgreements();

		$this->theme->loadThemeJavascript();
		$this->callIntegrationHooks();

		$context['theme_loaded'] = true;
	}

	/**
	 * Initialize a theme for use
	 */
	private function initTheme()
	{
		global $settings, $options, $context;

		// Validate / fetch the themes id
		$this->getThemeId();

		// Need to know who we are loading the theme for
		$member = empty($this->user->id) ? -1 : $this->user->id;

		// Load in the theme variables for them
		$themeData = $this->getThemeData($member);
		$settings = $themeData[0];
		$options = $themeData[$member];

		$settings['theme_id'] = $this->id;
		$settings['actual_theme_url'] = $settings['theme_url'];
		$settings['actual_images_url'] = $settings['images_url'];
		$settings['actual_theme_dir'] = $settings['theme_dir'];

		// Set the name of the default theme to something PHP will recognize.
		$themeName = basename($settings['theme_dir']) === 'default'
			? 'DefaultTheme'
			: ucfirst(basename($settings['theme_dir']));

		$loader = new ClassLoader();
		$loader->setPsr4('ElkArte\\Themes\\' . $themeName . '\\', $themeData[0]['default_theme_dir']);
		$loader->register();

		// Setup the theme file.
		require_once($settings['theme_dir'] . '/Theme.php');
		$class = 'ElkArte\\Themes\\' . $themeName . '\\Theme';

		static::$dirs = new Directories($settings);
		User::$info = User::$info ?? new UserInfo([]);

		// Initialize Theme.php, from the default or if it exists from the custom theme
		$this->theme = new $class($this->id, User::$info, static::$dirs);
		$context['theme_instance'] = $this->theme;
	}

	/**
	 * Resolves the ID of a theme.
	 *
	 * The identifier can be specified in:
	 * - a GET variable if theme selection is enabled
	 * - the session
	 * - user's preferences
	 * - board
	 * - forum default
	 *
	 * In addition, the ID is verified against a comma-separated list of
	 * known good themes. This check is skipped if the user is an admin.
	 *
	 * @return void Theme ID to load
	 */
	private function getThemeId()
	{
		global $modSettings, $board_info;

		// The user has selected a theme
		if (!empty($modSettings['theme_allow']) || allowedTo('admin_forum'))
		{
			$this->_chooseTheme();
		}
		// The theme was specified by the board.
		elseif (!empty($board_info['theme']))
		{
			$this->id = $board_info['theme'];
		}
		// The theme is the forum's default.
		else
		{
			$this->id = $modSettings['theme_guests'];
		}

		// Whatever we found, make sure its valid
		$this->_validThemeID();
	}

	/**
	 * Sets the chosen theme id
	 */
	private function _chooseTheme()
	{
		$_req = HttpReq::instance();

		// The theme was previously set by th (ACP)
		if (!empty($this->id) && !empty($_req->isSet('th')))
		{
			return;
		}

		// The theme was specified by Get or Post.
		if (!empty($_req->getRequest('theme', 'intval')))
		{
			$this->id = $_req->get('theme');
			$_SESSION['theme'] = $this->id;
		}
		// The theme was specified by REQUEST... previously.
		elseif (!empty($_req->getSession('theme')))
		{
			$this->id = (int) $_req->getSession('theme');
		}
		// The theme is just the user's choice. (might use ?board=1;theme=0 to force board theme.)
		elseif (!empty($this->user->theme))
		{
			$this->id = $this->user->theme;
		}
	}

	/**
	 * Validates, and corrects for error, that the theme id is capable of being used.
	 */
	private function _validThemeID()
	{
		global $modSettings, $ssi_theme;

		// Ensure that the theme is known... no foul play.
		if (!allowedTo('admin_forum'))
		{
			$themes = explode(',', $modSettings['knownThemes']);
			if ((!empty($ssi_theme) && $this->id !== (int) $ssi_theme)
				|| !in_array($this->id, $themes, true))
			{
				$this->id = $modSettings['theme_guests'];
			}
		}
	}

	/**
	 * Load in the theme variables for a given theme / member combination
	 *
	 * @param int $member
	 *
	 * @return array
	 */
	private function getThemeData($member)
	{
		global $modSettings, $boardurl;

		$cache = Cache::instance();

		// Do we already have this members theme data and specific options loaded (for aggressive cache settings)
		$temp = [];
		if ($cache->levelHigherThan(1)
			&& $cache->getVar($temp, 'theme_settings-' . $this->id . ':' . $member, 60)
			&& time() - 60 > $modSettings['settings_updated'])
		{
			$themeData = $temp;
			$flag = true;
		}
		// Or do we just have the system wide theme settings cached
		elseif ($cache->getVar($temp, 'theme_settings-' . $this->id, 90)
			&& time() - 60 > $modSettings['settings_updated'])
		{
			$themeData = $temp + [$member => []];
		}
		// Nothing at all then
		else
		{
			$themeData = [-1 => [], 0 => [], $member => []];
		}

		if (empty($flag))
		{
			$db = database();

			$immutable_theme_data = $this->immutable_theme_data;

			// Load variables from the current or default theme, global or this user's.
			$db->fetchQuery('
			SELECT 
				variable, value, id_member, id_theme
			FROM {db_prefix}themes
			WHERE id_member' . (empty($themeData[0]) ? ' IN (-1, 0, {int:id_member})' : ' = {int:id_member}') . '
				AND id_theme' . ($this->id === 1 ? ' = {int:id_theme}' : ' IN ({int:id_theme}, 1)'),
				[
					'id_theme' => $this->id,
					'id_member' => $member,
				]
			)->fetch_callback(
				static function ($row) use ($immutable_theme_data, &$themeData) {
					// There are just things we shouldn't be able to change as members.
					if ((int) $row['id_member'] !== 0 && in_array($row['variable'], $immutable_theme_data, true))
					{
						return;
					}

					// If this is the theme_dir of the default theme, store it.
					if ((int) $row['id_theme'] === 1 && empty($row['id_member'])
						&& in_array($row['variable'], ['theme_dir', 'theme_url', 'images_url']))
					{
						$themeData[0]['default_' . $row['variable']] = $row['value'];
					}

					// If this isn't set yet, is a theme option, or is not the default theme..
					if (!isset($themeData[$row['id_member']][$row['variable']]) || (int) $row['id_theme'] !== 1)
					{
						$themeData[$row['id_member']][$row['variable']] = strpos($row['variable'], 'show_') === 0 ? (int) $row['value'] === 1 : $row['value'];
					}
				}
			);

			$fileFunctions = FileFunctions::instance();
			if ($fileFunctions->fileExists($themeData[0]['default_theme_dir'] . '/cache')
				&& $fileFunctions->isWritable($themeData[0]['default_theme_dir'] . '/cache'))
			{
				$themeData[0]['default_theme_cache_dir'] = $themeData[0]['default_theme_dir'] . '/cache';
				$themeData[0]['default_theme_cache_url'] = $themeData[0]['default_theme_url'] . '/cache';
			}
			else
			{
				$themeData[0]['default_theme_cache_dir'] = CACHEDIR;
				$themeData[0]['default_theme_cache_url'] = $boardurl . '/cache';
			}

			// Set the defaults if the user has not chosen on their own
			if (!empty($themeData[-1]))
			{
				foreach ($themeData[-1] as $k => $v)
				{
					if (!isset($themeData[$member][$k]))
					{
						$themeData[$member][$k] = $v;
					}
				}
			}

			// If being aggressive we save the site wide and member theme settings
			if ($cache->levelHigherThan(1))
			{
				$cache->put('theme_settings-' . $this->id . ':' . $member, $themeData, 60);
			}
			// Only if we didn't already load that part of the cache...
			elseif (!isset($temp))
			{
				$cache->put('theme_settings-' . $this->id, [-1 => $themeData[-1], 0 => $themeData[0]], 90);
			}
		}

		return $themeData;
	}

	/**
	 * Detects url and checks against expected boardurl
	 *
	 * Attempts to correct improper URL's
	 */
	private function loadThemeUrls()
	{
		global $scripturl, $boardurl;

		// Check to see if they're accessing it from the wrong place.
		if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
		{
			$detected_url = detectServer()->supportsSSL() ? 'https://' : 'http://';
			$detected_url .= detectServer()->getHost();

			$temp = preg_replace('~/' . preg_quote(basename($scripturl), '~') . '(/.+)?$~', '', str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])));
			$detected_url .= ($temp !== '/') ? $temp : '';
		}

		if (isset($detected_url) && $detected_url !== $boardurl)
		{
			// Try #1 - check if it's in a list of alias addresses.
			$do_fix = $this->checkAlias($detected_url);

			// Hmm... check #2 - is it just different by a www?  Send them to the correct place!!
			if ($do_fix === false)
			{
				$this->checkWWWRedirect($detected_url);
			}

			// #3 is just a check for SSL...
			if (str_replace('https://', 'http://', $detected_url) === $boardurl)
			{
				$do_fix = true;
			}

			// Okay, #4 - perhaps it's an IP address?  We're gonna want to use that one, then. (assuming it's the IP or something...)
			if ($do_fix === true || preg_match('~^http[s]?://(?:[\d\.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) === 1)
			{
				$this->fixThemeUrls($detected_url);
			}
		}
	}

	/**
	 * Checks if the detected URL needs to be redirected to its www counterpart.
	 *
	 * @param string $detected_url The detected URL to check for redirection.
	 */
	private function checkWWWRedirect($detected_url)
	{
		global $boardurl;

		$detected_url = str_replace('://', '://www.', $detected_url);
		if ($detected_url !== $boardurl)
		{
			return;
		}

		if (ELK === 'SSI')
		{
			return;
		}

		if (!empty($_GET) && count($_GET) !== 1)
		{
			return;
		}

		// Okay, this seems weird, but we don't want an endless loop - this will make $_GET not empty ;).
		if (empty($_GET))
		{
			redirectexit('wwwRedirect');
		}
		elseif (key($_GET) !== 'wwwRedirect')
		{
			redirectexit('wwwRedirect;' . key($_GET) . '=' . current($_GET));
		}
	}

	/**
	 * Checks if the provided URL matches any of the forum alias URLs.
	 *
	 * @param string $detected_url The detected URL to check against the forum alias URLs.
	 *
	 * @return bool Returns true if the provided URL matches any of the forum alias URLs, otherwise false.
	 */
	private function checkAlias($detected_url)
	{
		global $modSettings;

		$do_fix = false;
		if (!empty($modSettings['forum_alias_urls']))
		{
			$aliases = explode(',', $modSettings['forum_alias_urls']);
			foreach ($aliases as $alias)
			{
				// Rip off all the boring parts, spaces, etc.
				$alias = trim($alias);
				if ($detected_url === $alias || strtr($detected_url, ['http://' => '', 'https://' => '']) === $alias)
				{
					$do_fix = true;
				}
			}
		}

		return $do_fix;
	}

	/**
	 * Called if the detected URL is not the same as boardurl but is a common
	 * variation in which case it updates key system variables so it works.
	 *
	 * @param string $detected_url
	 */
	private function fixThemeUrls($detected_url)
	{
		global $boardurl, $scripturl, $settings, $modSettings, $context, $board_info;

		// Caching is good ;).
		$oldurl = $boardurl;

		// Fix $boardurl and $scripturl.
		$boardurl = $detected_url;
		$scripturl = strtr($scripturl, [$oldurl => $boardurl]);
		$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], [$oldurl => $boardurl]);

		// Fix the theme urls...
		$settings['theme_url'] = strtr($settings['theme_url'], [$oldurl => $boardurl]);
		$settings['default_theme_url'] = strtr($settings['default_theme_url'], [$oldurl => $boardurl]);
		$settings['actual_theme_url'] = strtr($settings['actual_theme_url'], [$oldurl => $boardurl]);
		$settings['images_url'] = strtr($settings['images_url'], [$oldurl => $boardurl]);
		$settings['default_images_url'] = strtr($settings['default_images_url'], [$oldurl => $boardurl]);
		$settings['actual_images_url'] = strtr($settings['actual_images_url'], [$oldurl => $boardurl]);

		// And just a few mod settings :).
		$modSettings['smileys_url'] = strtr($modSettings['smileys_url'], [$oldurl => $boardurl]);
		$modSettings['avatar_url'] = strtr($modSettings['avatar_url'], [$oldurl => $boardurl]);

		// Clean up after loadBoard().
		if (isset($board_info['moderators']))
		{
			foreach ($board_info['moderators'] as $k => $dummy)
			{
				$board_info['moderators'][$k]['href'] = strtr($dummy['href'], [$oldurl => $boardurl]);
				$board_info['moderators'][$k]['link'] = strtr($dummy['link'], ['"' . $oldurl => '"' . $boardurl]);
			}
		}

		foreach ($context['linktree'] as $k => $dummy)
		{
			$context['linktree'][$k]['url'] = strtr($dummy['url'], [$oldurl => $boardurl]);
		}
	}

	/**
	 * Sets the admin preferences for the current user.
	 */
	private function setAdminPreferences()
	{
		global $context, $options;

		$context['admin_preferences'] = [];
		// Update the option.
		if ($this->user->is_guest !== false)
		{
			return;
		}

		if (empty($options['admin_preferences']))
		{
			return;
		}

		$context['admin_preferences'] = serializeToJson($options['admin_preferences'], static function ($array_form) {
			global $context;

			require_once(SUBSDIR . '/Admin.subs.php');

			// Required by updateAdminPreferences
			$context['admin_preferences'] = $array_form;
			updateAdminPreferences();
		});
	}

	/**
	 * Sets user preferences.
	 *
	 * Updates the `minmax_preferences` option with the user's preferences if the user is not a guest and if the `minmax_preferences` option is not empty.
	 *
	 * If the user is a guest and the `upshrink` cookie is set, the `minmax_preferences` array is set with the `upshrink` cookie value to prevent collapse jumping.
	 */
	private function setUserPreferences()
	{
		global $context, $options;

		$context['minmax_preferences'] = [];

		// Update the option.
		if (($this->user->is_guest === false) && !empty($options['minmax_preferences']))
		{
			$context['minmax_preferences'] = serializeToJson($options['minmax_preferences'], static function ($array_form) {
				global $settings;

				require_once(SUBSDIR . '/Themes.subs.php');
				updateThemeOptions([
					$settings['theme_id'],
					User::$info->id,
					'minmax_preferences',
					json_encode($array_form),
				]);
			});
		}

		// Guest may have collapsed the header, check the cookie to prevent collapse jumping
		if (!$this->user->is_guest)
		{
			return;
		}

		if (!isset($_COOKIE['upshrink']))
		{
			return;
		}

		$context['minmax_preferences'] = ['upshrink' => $_COOKIE['upshrink']];
	}

	/**
	 * Set up the context with necessary data.
	 */
	private function setupContext()
	{
		global $mbname, $context, $scripturl, $modSettings, $txt;

		$this->loadThemeContext();

		// @todo These really don't belong here since they are more general than the theme.
		$context['forum_name'] = $mbname;
		$context['forum_name_html_safe'] = $context['forum_name'];

		// Showing the login bar?
		if ($this->isGuestShowLoginBar())
		{
			$this->showLoginBar();
		}

		// Set the top level linktree up.
		array_unshift($context['linktree'], [
			'url' => $scripturl,
			'name' => $context['forum_name'],
		]);

		// Just some mobile-friendly settings
		if (strpos(request()->user_agent(), 'Mobi'))
		{
			// Disable the search dropdown.
			$modSettings['search_dropdown'] = false;
		}

		// Guests may still need a name.
		if ($context['user']['is_guest'] && empty($context['user']['name']))
		{
			$context['user']['name'] = $txt['guest_title'];
		}

		// Set the new feed links for use in the template
		if (empty($modSettings['xmlnews_enable']))
		{
			return;
		}

		if (empty($modSettings['allow_guestAccess']) && !$context['user']['is_logged'])
		{
			return;
		}

		$context['newsfeed_urls'] = [
			'rss' => getUrl('action', ['action' => '.xml', 'type' => 'rss2', 'limit' => (empty($modSettings['xmlnews_limit']) ? 5 : $modSettings['xmlnews_limit'])]),
			'atom' => getUrl('action', ['action' => '.xml', 'type' => 'atom', 'limit' => (empty($modSettings['xmlnews_limit']) ? 5 : $modSettings['xmlnews_limit'])]),
		];
	}

	/**
	 * Loads various theme related settings into context and sets system-wide theme defaults
	 */
	private function loadThemeContext()
	{
		global $context, $settings, $modSettings, $txt;

		// Some basic information...
		$init = [
			'html_headers' => '',
			'links' => [],
			'css_files' => [],
			'javascript_files' => [],
			'css_rules' => [],
			'javascript_inline' => ['standard' => [], 'defer' => []],
			'javascript_vars' => [],
		];
		foreach ($init as $area => $value)
		{
			$context[$area] = $context[$area] ?? $value;
		}

		// Set a couple of bits for the template.
		$context['right_to_left'] = !empty($txt['lang_rtl']);
		$context['tabindex'] = 1;

		$context['theme_variant'] = '';
		$context['theme_variant_url'] = '';

		$context['menu_separator'] = empty($settings['use_image_buttons']) ? ' | ' : ' ';
		$context['can_register'] = empty($modSettings['registration_method']) || (int) $modSettings['registration_method'] !== 3;

		foreach (['theme_header', 'upper_content'] as $call)
		{
			if (!isset($context[$call . '_callbacks']))
			{
				$context[$call . '_callbacks'] = [];
			}
		}

		// This allows sticking some HTML on the page output - useful for controls.
		$context['insert_after_template'] = '';
	}

	/**
	 * Determines whether to show the login bar for guest users.
	 *
	 * @return bool Returns `true` if the login bar should be shown for guest users, otherwise `false`.
	 */
	private function isGuestShowLoginBar()
	{
		global $modSettings;

		return $this->user->is_guest && $modSettings['enableVBStyleLogin'];
	}

	/**
	 * Sets up the login bar.
	 */
	private function showLoginBar()
	{
		global $context;

		$context['show_login_bar'] = true;
		$context['theme_header_callbacks'][] = 'login_bar';
		loadJavascriptFile('sha256.js', ['defer' => true]);
	}

	/**
	 * Loads the theme settings.
	 *
	 * This method initializes the theme settings and loads the basic layers.
	 */
	private function loadThemeSettings()
	{
		global $modSettings, $settings, $txt;

		// Defaults in case of odd things
		$settings['avatars_on_indexes'] = 0;

		// Initialize the theme.
		$settings = array_merge($settings, $this->theme->getSettings());

		// Load the basic layers
		$this->theme->loadDefaultLayers();

		// Call initialization theme integration functions.
		call_integration_hook('integrate_init_theme', [$this->id, &$settings]);

		// Any theme-related strings that need to be loaded?
		if (!empty($settings['require_theme_strings']))
		{
			Txt::load('ThemeStrings', false);
		}

		// Allow overriding the board wide time/number formats.
		if (empty(User::$settings['time_format']) && !empty($modSettings['time_format']))
		{
			$this->user->time_format = $modSettings['time_format'];
		}

		if (isset($settings['use_default_images']) && $settings['use_default_images'] === 'always')
		{
			$settings['theme_url'] = $settings['default_theme_url'];
			$settings['images_url'] = $settings['default_images_url'];
			$settings['theme_dir'] = $settings['default_theme_dir'];
		}

		// Make a special URL for the language.
		$settings['lang_images_url'] = $settings['images_url'] . '/' . (empty($txt['image_lang']) ? $this->user->language : $txt['image_lang']);
	}

	/**
	 * Load the theme variant and CSS files.
	 *
	 * What it does:
	 * - Loads the icon SVG support file with fallback to the default theme.
	 * - Loads the theme variant if it exists.
	 * - Sets up the header logo URL.
	 * - Loads RTL (right-to-left) CSS file for RTL languages.
	 * - Loads RTL theme variant CSS file for RTL languages and if a theme variant is defined.
	 */
	private function loadThemeVariantAndCSS()
	{
		global $context, $settings;

		// Load icon SVG support file with fallback to default theme
		loadCSSFile('icons_svg.css');

		// We allow theme variants, because we're cool.
		if (!empty($settings['theme_variants']))
		{
			$this->theme->loadThemeVariant();
		}

		// A bit lonely maybe, though I think it should be set up *after* the theme variants detection
		$context['header_logo_url_html_safe'] = empty($settings['header_logo_url'])
			? $settings['images_url'] . '/' . $context['theme_variant_url'] . 'logo_elk.png'
			: Util::htmlspecialchars($settings['header_logo_url']);

		// RTL languages require an additional stylesheet.
		if ($context['right_to_left'])
		{
			loadCSSFile('rtl.css');
		}

		if (empty($context['theme_variant']))
		{
			return;
		}

		if (!$context['right_to_left'])
		{
			return;
		}

		loadCSSFile($context['theme_variant'] . '/rtl' . $context['theme_variant'] . '.css');
	}

	/**
	 * Process the agreements and update the context accordingly.
	 *
	 * What it does:
	 * - Clears the session variables for agreement acceptance.
	 * - Sets the appropriate error messages in the context if an agreement has been accepted.
	 */
	public function processAgreements()
	{
		global $context, $txt;

		if (!empty($_SESSION['agreement_accepted']))
		{
			$_SESSION['agreement_accepted'] = null;
			$context['accepted_agreement'] = [
				'errors' => [
					'accepted_agreement' => $txt['agreement_accepted']
				]
			];
		}

		if (!empty($_SESSION['privacypolicy_accepted']))
		{
			$_SESSION['privacypolicy_accepted'] = null;
			$context['accepted_agreement'] = [
				'errors' => [
					'accepted_privacy_policy' => $txt['privacypolicy_accepted']
				]
			];
		}
	}

	/**
	 * Calls the integration hooks related to the theme.
	 *
	 * - Sets the theme directory path for integration hooks.
	 * - Includes any additional files specified by the 'integrate_theme_include' hook.
	 * - Calls the 'integrate_load_theme' hook to load theme integration functions.
	 */
	private function callIntegrationHooks()
	{
		global $settings;

		Hooks::instance()->newPath(['$themedir' => $settings['theme_dir']]);

		// Any files to include at this point?
		call_integration_include_hook('integrate_theme_include');

		// Call load theme integration functions.
		call_integration_hook('integrate_load_theme');
	}

	/**
	 * This loads the bare minimum data.
	 *
	 * - Needed by scheduled tasks,
	 * - Needed by any other code that needs language files before the forum (the theme) is loaded.
	 */
	public static function loadEssentialThemeData()
	{
		global $settings, $modSettings, $mbname, $context;

		if (!function_exists('database'))
		{
			throw new \Exception('');
		}

		$db = database();

		// Get all the default theme variables.
		$db->fetchQuery('
			SELECT 
				id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_member = {int:no_member}
				AND id_theme IN (1, {int:theme_guests})',
			[
				'no_member' => 0,
				'theme_guests' => $modSettings['theme_guests'],
			]
		)->fetch_callback(
			static function ($row) {
				global $settings;

				$settings[$row['variable']] = $row['value'];
				$indexes_to_default = [
					'theme_dir',
					'theme_url',
					'images_url',
				];

				// Is this the default theme?
				if ($row['id_theme'] !== '1')
				{
					return;
				}

				if (!in_array($row['variable'], $indexes_to_default, true))
				{
					return;
				}

				$settings['default_' . $row['variable']] = $row['value'];
			}
		);

		static::$dirs = new Directories($settings);

		// Check we have some directories' setup.
		if (static::$dirs->hasDirectories() === false)
		{
			static::$dirs->reloadDirectories($settings);
		}

		// Assume we want this.
		$context['forum_name'] = $mbname;
		$context['forum_name_html_safe'] = $context['forum_name'];

		static::loadLanguageFile('index+Addons');
	}

	/**
	 * Load a language file.
	 *
	 * - Tries the current and default themes as well as the user and global languages.
	 *
	 * @param string $template_name
	 * @param string $lang = ''
	 * @param bool $fatal = true
	 * @param bool $force_reload = false
	 *
	 * @return string The language actually loaded.
	 */
	public static function loadLanguageFile(
		$template_name,
		$lang = '',
		$fatal = false, // @todo reset to true when appropriate
		$force_reload = false
	)
	{
		return static::loadLanguageFiles(
			explode('+', $template_name),
			$lang,
			$fatal,
			$force_reload
		);
	}

	/**
	 * Load a language file.
	 *
	 * - Tries the current and default themes as well as the user and global languages.
	 *
	 * @param string[] $template_name
	 * @param string $lang = ''
	 * @param bool $fatal = true
	 * @param bool $force_reload = false
	 *
	 * @return string The language actually loaded.
	 */
	public static function loadLanguageFiles(array $template_name, $lang = '', $fatal = true, $force_reload = false)
	{
		// Needed by the loaded files
		global $language, $settings, $modSettings, $db_show_debug, $txt;
		static $already_loaded = [];

		// For each file open it up and write it out!
		foreach ($template_name as $template)
		{
			$fix_arrays = $template === 'index';

			Txt::load($template, true, $fix_arrays);
		}

		// Return the language actually loaded.
		return $lang;
	}

	/**
	 * @return Theme the current theme
	 */
	public function getTheme()
	{
		return $this->theme;
	}
}
