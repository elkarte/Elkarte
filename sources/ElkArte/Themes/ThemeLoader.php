<?php

/**
 * The main abstract theme class
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Themes;

use Cache;

/**
 * Class ThemeLoader
 */
class ThemeLoader
{
	/** @var int The id of the theme being used */
	private $id;

	/** @var Theme The current theme. */
	private $theme;

	/**
	 * Resolves the ID of a theme.
	 *
	 * The identifier can be specified in:
	 * - a GET variable
	 * - the session
	 * - user's prefrences
	 * - board
	 * - forum default
	 *
	 * In addition, the ID is verified against a comma-seperated list of
	 * known good themes. This check is skipped if the user is an admin.
	 *
	 * @return void Theme ID to load
	 */
	private function getThemeId()
	{
		global $modSettings, $user_info, $board_info, $ssi_theme;

		if (!empty($modSettings['theme_allow']) || allowedTo('admin_forum'))
		{
			// The theme was specified by REQUEST.
			if (!empty($_REQUEST['theme']))
			{
				$this->id = (int) $_REQUEST['theme'];
				$_SESSION['theme'] = $this->id;
			}
			// The theme was specified by REQUEST... previously.
			elseif (!empty($_SESSION['theme']))
			{
				$this->id = (int) $_SESSION['theme'];
			}
			// The theme is just the user's choice. (might use ?board=1;theme=0 to force board theme.)
			elseif (!empty($user_info['theme']))
			{
				$this->id = $user_info['theme'];
			}
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

		// Ensure that the theme is known... no foul play.
		if (!allowedTo('admin_forum'))
		{
			$themes = explode(',', $modSettings['knownThemes']);
			if (!in_array($this->id, $themes) || (!empty($ssi_theme) && $this->id != $ssi_theme))
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

		$cache = \ElkArte\Cache\Cache::instance();

		// Do we already have this members theme data and specific options loaded (for aggressive cache settings)
		$temp = [];
		if ($cache->levelHigherThan(1) && $cache->getVar($temp, 'theme_settings-' . $this->id . ':' . $member,
				60) && time() - 60 > $modSettings['settings_updated']
		)
		{
			$themeData = $temp;
			$flag = true;
		}
		// Or do we just have the system wide theme settings cached
		elseif ($cache->getVar($temp, 'theme_settings-' . $this->id,
				90) && time() - 60 > $modSettings['settings_updated']
		)
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

			// Load variables from the current or default theme, global or this user's.
			$result = $db->query('', '
			SELECT variable, value, id_member, id_theme
			FROM {db_prefix}themes
			WHERE id_member' . (empty($themeData[0]) ? ' IN (-1, 0, {int:id_member})' : ' = {int:id_member}') . '
				AND id_theme' . ($this->id == 1 ? ' = {int:id_theme}' : ' IN ({int:id_theme}, 1)'),
				[
					'id_theme' => $this->id,
					'id_member' => $member,
				]
			);

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

			// Pick between $settings and $options depending on whose data it is.
			while ($row = $db->fetch_assoc($result))
			{
				// There are just things we shouldn't be able to change as members.
				if ($row['id_member'] != 0 && in_array($row['variable'], $immutable_theme_data))
				{
					continue;
				}

				// If this is the theme_dir of the default theme, store it.
				if (in_array($row['variable'], [
						'theme_dir',
						'theme_url',
						'images_url',
					]) && $row['id_theme'] == 1 && empty($row['id_member'])
				)
				{
					$themeData[0]['default_' . $row['variable']] = $row['value'];
				}

				// If this isn't set yet, is a theme option, or is not the default theme..
				if (!isset($themeData[$row['id_member']][$row['variable']]) || $row['id_theme'] != 1)
				{
					$themeData[$row['id_member']][$row['variable']] =
						substr($row['variable'], 0, 5) == 'show_' ? $row['value'] == 1 : $row['value'];
				}
			}
			$db->free_result($result);

			if (file_exists($themeData[0]['default_theme_dir'] . '/cache') && is_writable($themeData[0]['default_theme_dir'] . '/cache'))
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
	 * Initialize a theme for use
	 */
	private function initTheme()
	{
		global $user_info, $settings, $options, $context;

		// Validate / fetch the themes id
		$this->getThemeId();

		// Need to know who we are loading the theme for
		$member = empty($user_info['id']) ? -1 : $user_info['id'];

		// Load in the theme variables for them
		$themeData = $this->getThemeData($member);
		$settings = $themeData[0];
		$options = $themeData[$member];

		$settings['theme_id'] = $this->id;
		$settings['actual_theme_url'] = $settings['theme_url'];
		$settings['actual_images_url'] = $settings['images_url'];
		$settings['actual_theme_dir'] = $settings['theme_dir'];

		// Set the name of the default theme to something PHP will recognize.
		$themeName = basename($settings['theme_dir']) == 'default'
			? 'DefaultTheme'
			: ucfirst(basename($settings['theme_dir']));

		// The require should not be necessary, but I guess it's better to stay on the safe side.
		require_once(EXTDIR . '/ClassLoader.php');
		$loader = new \Composer\Autoload\ClassLoader();
		$loader->setPsr4('ElkArte\\Themes\\' . $themeName . '\\', $themeData[0]['default_theme_dir']);
		$loader->register();

		// Setup the theme file.
		require_once($settings['theme_dir'] . '/Theme.php');
		$class = 'ElkArte\\Themes\\' . $themeName . '\\Theme';
		$theme = new $class($this->id);
		$this->theme = $context['theme_instance'] = $theme;

		// Reload the templates
		$this->theme->getTemplates()->reloadDirectories($settings);
	}

	/**
	 * @return Theme the current theme
	 */
	public function getTheme()
	{
		return $this->theme;
	}

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
	 * @param int  $id_theme   = 0
	 * @param bool $initialize = true
	 */
	public function __construct($id_theme = 0, $initialize = true)
	{
		global $user_info, $user_settings;
		global $txt, $scripturl, $mbname, $modSettings;
		global $context, $settings, $options;

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
			$context['admin_preferences'] = serializeToJson($options['admin_preferences'], function ($array_form)
			{
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

		if (!$user_info['is_guest'])
		{
			if (!empty($options['minmax_preferences']))
			{
				$context['minmax_preferences'] = serializeToJson($options['minmax_preferences'], function ($array_form)
				{
					global $settings, $user_info;

					// Update the option.
					require_once(SUBSDIR . '/Themes.subs.php');
					updateThemeOptions([
						$settings['theme_id'],
						$user_info['id'],
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
		elseif ($user_info['is_guest'] && isset($_COOKIE['upshrink']))
		{
			$context['minmax_preferences'] = ['upshrink' => $_COOKIE['upshrink']];
		}

		$this->loadThemeContext();

		// @todo These really don't belong here since they are more general than the theme.
		$context['forum_name'] = $mbname;
		$context['forum_name_html_safe'] = $context['forum_name'];

		// Set some permission related settings.
		if ($user_info['is_guest'] && !empty($modSettings['enableVBStyleLogin']))
		{
			$context['show_login_bar'] = true;
			$context['theme_header_callbacks'][] = 'login_bar';
			loadJavascriptFile('sha256.js', ['defer' => true]);
		}

		// This determines the server... not used in many places, except for login fixing.
		detectServer();

		// Detect the browser. This is separated out because it's also used in attachment downloads
		detectBrowser();

		// Set the top level linktree up.
		array_unshift($context['linktree'], [
			'url' => $scripturl,
			'name' => $context['forum_name'],
		]);

		// Just some mobile-friendly settings
		if ($context['browser_body_id'] == 'mobile')
		{
			// Disable the preview text.
			$modSettings['message_index_preview'] = 0;
			// Force the usage of click menu instead of a hover menu.
			$options['use_click_menu'] = 1;
			// No space left for a sidebar
			$options['use_sidebar_menu'] = false;
			// Disable the search dropdown.
			$modSettings['search_dropdown'] = false;
		}

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
			$this->theme->getTemplates()->loadLanguageFile('ThemeStrings', '', false);
		}

		// We allow theme variants, because we're cool.
		if (!empty($settings['theme_variants']))
		{
			$this->theme->loadThemeVariant();
		}

		// A bit lonely maybe, though I think it should be set up *after* the theme variants detection
		$context['header_logo_url_html_safe'] =
			empty($settings['header_logo_url']) ? $settings['images_url'] . '/' . $context['theme_variant_url'] . 'logo_elk.png' : \ElkArte\Util::htmlspecialchars($settings['header_logo_url']);

		// Allow overriding the board wide time/number formats.
		if (empty($user_settings['time_format']) && !empty($txt['time_format']))
		{
			$user_info['time_format'] = $txt['time_format'];
		}

		if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'always')
		{
			$settings['theme_url'] = $settings['default_theme_url'];
			$settings['images_url'] = $settings['default_images_url'];
			$settings['theme_dir'] = $settings['default_theme_dir'];
		}

		// Make a special URL for the language.
		$settings['lang_images_url'] =
			$settings['images_url'] . '/' . (!empty($txt['image_lang']) ? $txt['image_lang'] : $user_info['language']);

		// RTL languages require an additional stylesheet.
		if ($context['right_to_left'])
		{
			loadCSSFile('rtl.css');
		}

		if (!empty($context['theme_variant']) && $context['right_to_left'])
		{
			loadCSSFile($context['theme_variant'] . '/rtl' . $context['theme_variant'] . '.css');
		}

		// This allows us to change the way things look for the admin.
		$context['admin_features'] = explode(',', isset($modSettings['admin_features']) ?
			$modSettings['admin_features'] : 'cd,cp,k,w,rg,ml,pm');

		if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		{
			$context['newsfeed_urls'] = [
				'rss' => $scripturl . '?action=.xml;type=rss2;limit=' . (!empty($modSettings['xmlnews_limit']) ? $modSettings['xmlnews_limit'] : 5),
				'atom' => $scripturl . '?action=.xml;type=atom;limit=' . (!empty($modSettings['xmlnews_limit']) ? $modSettings['xmlnews_limit'] : 5),
			];
		}

		$this->theme->loadThemeJavascript();

		\ElkArte\Hooks::instance()->newPath(['$themedir' => $settings['theme_dir']]);

		// Any files to include at this point?
		call_integration_include_hook('integrate_theme_include');

		// Call load theme integration functions.
		call_integration_hook('integrate_load_theme');

		// We are ready to go.
		$context['theme_loaded'] = true;
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
			$detected_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 'https://' : 'http://';
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
					if ($detected_url == trim($alias) || strtr($detected_url, [
							'http://' => '',
							'https://' => '',
						]) == trim($alias)
					)
					{
						$do_fix = true;
					}
				}
			}

			// Hmm... check #2 - is it just different by a www?  Send them to the correct place!!
			if (empty($do_fix) && strtr($detected_url,
					['://' => '://www.']) == $boardurl && (empty($_GET) || count($_GET) == 1) && ELK != 'SSI'
			)
			{
				// Okay, this seems weird, but we don't want an endless loop - this will make $_GET not empty ;).
				if (empty($_GET))
				{
					redirectexit('wwwRedirect');
				}
				else
				{
					if (key($_GET) !== 'wwwRedirect')
					{
						redirectexit('wwwRedirect;' . key($_GET) . '=' . current($_GET));
					}
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
			$context['linktree'][$k]['url'] = strtr($dummy['url'], array($oldurl => $boardurl));
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
			$context[$area] = isset($context[$area]) ? $context[$area] : $value;
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
}
