<?php

/**
 * The main abstract theme class
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
use ElkArte\Hooks;
use ElkArte\HttpReq;
use ElkArte\User;
use ElkArte\UserInfo;
use ElkArte\Util;
use ElkArte\Languages\Txt;

/**
 * Class ThemeLoader
 */
class ThemeLoader
{
	/** @var mixed|\ElkArte\ValuesContainer */
	public $user;

	/** @var int The id of the theme being used */
	private $id;

	/** @var Theme The current theme. */
	private $theme;

	/** @var Directories The list of directories. */
	protected static $dirs;

	/**
	 * Load a theme, by ID.
	 *
	 * What it does:
	 * - identify the theme to be loaded.
	 * - validate that the theme is valid and that the user has permission to use it
	 * - load the users theme settings and site settings into $options.
	 * - prepares the list of folders to search for template loading.
	 * - identify what smiley set to use.
	 * - sets up $context['user']
	 * - detects the users browser and sets a mobile friendly environment if needed
	 * - loads default JS variables for use in every theme
	 * - loads default JS scripts for use in every theme
	 *
	 * @param int $id_theme = 0
	 * @param bool $initialize = true
	 */
	public function __construct($id_theme = 0, $initialize = true)
	{
		global $txt, $scripturl, $mbname, $modSettings, $context, $settings, $options;

		$this->user = User::$info;
		$this->id = $id_theme;
		$this->initTheme();

		if (!$initialize)
		{
			return;
		}

		$this->loadThemeUrls();
		loadUserContext();

		// Set up some additional interface preference context
		if (!empty($options['admin_preferences']))
		{
			$context['admin_preferences'] = serializeToJson($options['admin_preferences'], function ($array_form) {
				global $context;

				$context['admin_preferences'] = $array_form;
				require_once(SUBSDIR . '/Admin.subs.php');
				updateAdminPreferences();
			});
		}
		else
		{
			$context['admin_preferences'] = [];
		}

		if ($this->user->is_guest === false)
		{
			if (!empty($options['minmax_preferences']))
			{
				$context['minmax_preferences'] = serializeToJson($options['minmax_preferences'], function ($array_form) {
					global $settings;

					// Update the option.
					require_once(SUBSDIR . '/Themes.subs.php');
					updateThemeOptions([
						$settings['theme_id'],
						User::$info->id,
						'minmax_preferences',
						json_encode($array_form),
					]);
				});
			}
			else
			{
				$context['minmax_preferences'] = [];
			}
		}
		// Guest may have collapsed the header, check the cookie to prevent collapse jumping
		elseif ($this->user->is_guest && isset($_COOKIE['upshrink']))
		{
			$context['minmax_preferences'] = ['upshrink' => $_COOKIE['upshrink']];
		}

		$this->loadThemeContext();

		// @todo These really don't belong here since they are more general than the theme.
		$context['forum_name'] = $mbname;
		$context['forum_name_html_safe'] = $context['forum_name'];

		// Set some permission related settings.
		if ($this->user->is_guest && !empty($modSettings['enableVBStyleLogin']))
		{
			$context['show_login_bar'] = true;
			$context['theme_header_callbacks'][] = 'login_bar';
			loadJavascriptFile('sha256.js', ['defer' => true]);
		}

		// This determines the server... not used in many places, except for login fixing.
		detectServer();

		// Set the top level linktree up.
		array_unshift($context['linktree'], [
			'url' => $scripturl,
			'name' => $context['forum_name'],
		]);

		// Just some mobile-friendly settings
		$req = request();
		if (strpos($req->user_agent(), 'Mobi'))
		{
			// Disable the search dropdown.
			$modSettings['search_dropdown'] = false;
		}

		// @todo Hummm this seems a bit wanky
		if (!isset($txt))
		{
			$txt = [];
		}

		// Defaults in case of odd things
		$settings['avatars_on_indexes'] = 0;

		// Initialize the theme.
		$settings = array_merge($settings, $this->theme->getSettings());

		// Load the basic layers
		$this->theme->loadDefaultLayers();

		// Call initialization theme integration functions.
		call_integration_hook('integrate_init_theme', [$this->id, &$settings]);

		// Guests may still need a name.
		if ($context['user']['is_guest'] && empty($context['user']['name']))
		{
			$context['user']['name'] = $txt['guest_title'];
		}

		// Any theme-related strings that need to be loaded?
		if (!empty($settings['require_theme_strings']))
		{
			Txt::load('ThemeStrings', false);
		}

		// Load the SVG support file with fallback to default theme
		loadCSSFile('icons_svg.css');

		// We allow theme variants, because we're cool.
		if (!empty($settings['theme_variants']))
		{
			$this->theme->loadThemeVariant();
		}

		// A bit lonely maybe, though I think it should be set up *after* the theme variants detection
		$context['header_logo_url_html_safe'] =
			empty($settings['header_logo_url']) ? $settings['images_url'] . '/' . $context['theme_variant_url'] . 'logo_elk.png' : Util::htmlspecialchars($settings['header_logo_url']);

		// Allow overriding the board wide time/number formats.
		if (empty(User::$settings['time_format']) && !empty($txt['time_format']))
		{
			$this->user->time_format = $txt['time_format'];
		}

		if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'always')
		{
			$settings['theme_url'] = $settings['default_theme_url'];
			$settings['images_url'] = $settings['default_images_url'];
			$settings['theme_dir'] = $settings['default_theme_dir'];
		}

		// Make a special URL for the language.
		$settings['lang_images_url'] = $settings['images_url'] . '/' . (!empty($txt['image_lang']) ? $txt['image_lang'] : $this->user->language);

		// RTL languages require an additional stylesheet.
		if ($context['right_to_left'])
		{
			loadCSSFile('rtl.css');
		}

		if (!empty($context['theme_variant']) && $context['right_to_left'])
		{
			loadCSSFile($context['theme_variant'] . '/rtl' . $context['theme_variant'] . '.css');
		}

		if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		{
			$context['newsfeed_urls'] = [
				'rss' => getUrl('action', ['action' => '.xml', 'type' => 'rss2', 'limit' => (!empty($modSettings['xmlnews_limit']) ? $modSettings['xmlnews_limit'] : 5)]),
				'atom' => getUrl('action', ['action' => '.xml', 'type' => 'atom', 'limit' => (!empty($modSettings['xmlnews_limit']) ? $modSettings['xmlnews_limit'] : 5)]),
			];
		}

		if (!empty($_SESSION['agreement_accepted']))
		{
			$_SESSION['agreement_accepted'] = null;
			$context['accepted_agreement'] = array(
				'errors' => array(
					'accepted_agreement' => $txt['agreement_accepted']
				)
			);
		}

		if (!empty($_SESSION['privacypolicy_accepted']))
		{
			$_SESSION['privacypolicy_accepted'] = null;
			$context['accepted_agreement'] = array(
				'errors' => array(
					'accepted_privacy_policy' => $txt['privacypolicy_accepted']
				)
			);
		}

		$this->theme->loadThemeJavascript();

		Hooks::instance()->newPath(['$themedir' => $settings['theme_dir']]);

		// Any files to include at this point?
		call_integration_include_hook('integrate_theme_include');

		// Call load theme integration functions.
		call_integration_hook('integrate_load_theme');

		// We are ready to go.
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

		// The require should not be necessary, but I guess it's better to stay on the safe side.
		require_once(EXTDIR . '/ClassLoader.php');
		$loader = new ClassLoader();
		$loader->setPsr4('ElkArte\\Themes\\' . $themeName . '\\', $themeData[0]['default_theme_dir']);
		$loader->register();

		// Setup the theme file.
		require_once($settings['theme_dir'] . '/Theme.php');
		$class = 'ElkArte\\Themes\\' . $themeName . '\\Theme';
		static::$dirs = new Directories($settings);
		User::$info = User::$info ?? new UserInfo([]);
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
			$this->id = (int) $this->id;
		}
		// The theme was specified by Get or Post.
		elseif (!empty($_req->getRequest('theme', 'intval', null)))
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
	 * Validates, and corrects if in error, that the theme id is capable of
	 * being used.
	 */
	private function _validThemeID()
	{
		global $modSettings, $ssi_theme;

		// Ensure that the theme is known... no foul play.
		if (!allowedTo('admin_forum'))
		{
			$themes = explode(',', $modSettings['knownThemes']);
			if ((!empty($ssi_theme) && $this->id != $ssi_theme) || !in_array($this->id, $themes))
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

			$immutable_theme_data = [
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

			// Load variables from the current or default theme, global or this user's.
			$db->fetchQuery('
			SELECT 
				variable, value, id_member, id_theme
			FROM {db_prefix}themes
			WHERE id_member' . (empty($themeData[0]) ? ' IN (-1, 0, {int:id_member})' : ' = {int:id_member}') . '
				AND id_theme' . ($this->id == 1 ? ' = {int:id_theme}' : ' IN ({int:id_theme}, 1)'),
				[
					'id_theme' => $this->id,
					'id_member' => $member,
				]
			)->fetch_callback(
				function ($row) use ($immutable_theme_data, &$themeData) {
					// There are just things we shouldn't be able to change as members.
					if ($row['id_member'] != 0 && in_array($row['variable'], $immutable_theme_data))
					{
						return;
					}

					// If this is the theme_dir of the default theme, store it.
					if ($row['id_theme'] == 1 && empty($row['id_member'])
						&& in_array($row['variable'], ['theme_dir', 'theme_url', 'images_url']))
					{
						$themeData[0]['default_' . $row['variable']] = $row['value'];
					}

					// If this isn't set yet, is a theme option, or is not the default theme..
					if (!isset($themeData[$row['id_member']][$row['variable']]) || $row['id_theme'] != 1)
					{
						$themeData[$row['id_member']][$row['variable']] = substr($row['variable'], 0, 5) === 'show_' ? $row['value'] == 1 : $row['value'];
					}
				}
			);

			if (file_exists($themeData[0]['default_theme_dir'] . '/cache')
				&& is_writable($themeData[0]['default_theme_dir'] . '/cache'))
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
		global $scripturl, $boardurl, $modSettings;

		// Check to see if they're accessing it from the wrong place.
		if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
		{
			$detected_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on' ? 'https://' : 'http://';
			$detected_url .= empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
			$temp = preg_replace('~/' . basename($scripturl) . '(/.+)?$~', '',
				strtr(dirname($_SERVER['PHP_SELF']), '\\', '/'));
			if ($temp != '/')
			{
				$detected_url .= $temp;
			}
		}

		if (isset($detected_url) && $detected_url != $boardurl)
		{
			// Try #1 - check if it's in a list of alias addresses.
			if (!empty($modSettings['forum_alias_urls']))
			{
				$aliases = explode(',', $modSettings['forum_alias_urls']);
				foreach ($aliases as $alias)
				{
					// Rip off all the boring parts, spaces, etc.
					if ($detected_url === trim($alias) || strtr($detected_url, [
							'http://' => '',
							'https://' => '',
						]) === trim($alias)
					)
					{
						$do_fix = true;
					}
				}
			}

			// Hmm... check #2 - is it just different by a www?  Send them to the correct place!!
			if (empty($do_fix) && strtr($detected_url,
					['://' => '://www.']) == $boardurl && (empty($_GET) || count($_GET) === 1) && ELK !== 'SSI'
			)
			{
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

			// #3 is just a check for SSL...
			if (strtr($detected_url, ['https://' => 'http://']) == $boardurl)
			{
				$do_fix = true;
			}

			// Okay, #4 - perhaps it's an IP address?  We're gonna want to use that one, then. (assuming it's the IP or something...)
			if (!empty($do_fix) || preg_match('~^http[s]?://(?:[\d\.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~',
					$detected_url) == 1
			)
			{
				$this->fixThemeUrls($detected_url);
			}
		}
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
		$scripturl = strtr($scripturl, array($oldurl => $boardurl));
		$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], array($oldurl => $boardurl));

		// Fix the theme urls...
		$settings['theme_url'] = strtr($settings['theme_url'], array($oldurl => $boardurl));
		$settings['default_theme_url'] = strtr($settings['default_theme_url'], array($oldurl => $boardurl));
		$settings['actual_theme_url'] = strtr($settings['actual_theme_url'], array($oldurl => $boardurl));
		$settings['images_url'] = strtr($settings['images_url'], array($oldurl => $boardurl));
		$settings['default_images_url'] = strtr($settings['default_images_url'], array($oldurl => $boardurl));
		$settings['actual_images_url'] = strtr($settings['actual_images_url'], array($oldurl => $boardurl));

		// And just a few mod settings :).
		$modSettings['smileys_url'] = strtr($modSettings['smileys_url'], array($oldurl => $boardurl));
		$modSettings['avatar_url'] = strtr($modSettings['avatar_url'], array($oldurl => $boardurl));

		// Clean up after loadBoard().
		if (isset($board_info['moderators']))
		{
			foreach ($board_info['moderators'] as $k => $dummy)
			{
				$board_info['moderators'][$k]['href'] = strtr($dummy['href'], array($oldurl => $boardurl));
				$board_info['moderators'][$k]['link'] = strtr($dummy['link'], array('"' . $oldurl => '"' . $boardurl));
			}
		}

		foreach ($context['linktree'] as $k => $dummy)
		{
			$context['linktree'][$k]['url'] = strtr($dummy['url'], array($oldurl => $boardurl));
		}
	}

	/**
	 * Loads various theme related settings into context and sets system wide theme defaults
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

		$context['menu_separator'] = !empty($settings['use_image_buttons']) ? ' ' : ' | ';
		$context['can_register'] =
			empty($modSettings['registration_method']) || $modSettings['registration_method'] != 3;

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
	 * @return Theme the current theme
	 */
	public function getTheme()
	{
		return $this->theme;
	}

	/**
	 * This loads the bare minimum data.
	 *
	 * - Needed by scheduled tasks,
	 * - Needed by any other code that needs language files before the forum (the theme)
	 * is loaded.
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
			function ($row) {
				global $settings;

				$settings[$row['variable']] = $row['value'];
				$indexes_to_default = [
					'theme_dir',
					'theme_url',
					'images_url',
				];

				// Is this the default theme?
				if ($row['id_theme'] == '1' && in_array($row['variable'], $indexes_to_default))
				{
					$settings['default_' . $row['variable']] = $row['value'];
				}
			}
		);

		static::$dirs = new Directories($settings);

		// Check we have some directories setup.
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
	 * @param string[] $template_name
	 * @param string $lang = ''
	 * @param bool $fatal = true
	 * @param bool $force_reload = false
	 *
	 * @return string The language actually loaded.
	 */
	public static function loadLanguageFiles(
		array $template_name,
		$lang = '',
		$fatal = true,
		$force_reload = false
	) {
		global $language, $settings, $modSettings;
		global $db_show_debug, $txt;
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
	) {
		return static::loadLanguageFiles(
			explode('+', $template_name),
			$lang,
			$fatal,
			$force_reload
		);
	}
}
