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

use BBC\ParserWrapper;
use ElkArte\Controller\ScheduledTasks;
use ElkArte\EventManager;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\HttpReq;
use ElkArte\Helper\SiteCombiner;
use ElkArte\Helper\Util;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;
use ElkArte\User;

/**
 * Class Theme
 */
abstract class Theme
{
	/** @var string */
	public const DEFAULT_EXPIRES = 'Mon, 26 Jul 1997 05:00:00 GMT';

	/** @var int */
	public const ALL = -1;

	/** @var array */
	private const CONTENT_TYPES = [
		'fatal_error' => 'text/html',
		'json' => 'application/json',
		'xml' => 'text/xml',
		'generic_xml' => 'text/xml'
	];

	/** @var ValuesContainer */
	public $user;

	/** @var HttpReq user input variables */
	public $_req;

	/** @var int The id of the theme being used */
	protected $id;

	/** @var array */
	protected $links = [];

	/** @var string[] Holds base actions that we do not want crawled / indexed */
	public $no_index_actions = [];

	/** @var bool Right to left language support */
	protected $rtl;

	/** @var Templates */
	private $templates;

	/** @var TemplateLayers */
	private $layers;

	/** @var Javascript */
	public $javascript;

	/** @var Css */
	public $css;

	/**
	 * Theme constructor.
	 *
	 * @param int $id
	 * @param ValuesContainer $user
	 * @param Directories $dirs
	 */
	public function __construct(int $id, ValuesContainer $user, Directories $dirs)
	{
		$this->id = $id;
		$this->user = $user;
		$this->layers = new TemplateLayers();
		$this->templates = new Templates($dirs);

		$this->no_index_actions = [
			'profile',
			'search',
			'calendar',
			'memberlist',
			'help',
			'who',
			'stats',
			'login',
			'reminder',
			'register',
			'contact'
		];

		$this->_req = HttpReq::instance();

		// Theme posse
		$this->javascript = new Javascript();
		$this->css = new Css();
	}

	/**
	 * The following are expected in the custom Theme.php (or just use the default)
	 */
	abstract public function getSettings();

	abstract public function template_header();

	abstract public function setupThemeContext();

	abstract public function setupCurrentUserContext();

	abstract public function loadCustomCSS();

	abstract public function template_footer();

	abstract public function loadThemeJavascript();

	/**
	 * Get the layers associated with the current theme
	 */
	public function getLayers()
	{
		return $this->layers;
	}

	/**
	 * Get the templates associated with the current theme
	 */
	public function getTemplates()
	{
		return $this->templates;
	}

	/**
	 * Turn on/off RTL language support
	 *
	 * @param $toggle
	 *
	 * @return $this
	 */
	public function setRTL($toggle)
	{
		$this->rtl = (bool) $toggle;

		return $this;
	}

	/**
	 * Get the value of 'api' from the request
	 *
	 * What it does:
	 *  - Retrieves the value of the 'api' parameter from the request.
	 *
	 * @return string The value of the 'api' parameter from the request, trimmed.
	 */
	public function getRequestAPI(): string
	{
		return $this->_req->getRequest('api', 'trim', '');
	}

	/**
	 * Set the headers expiration
	 *
	 * What it does:
	 *  - Sets the Expires and Last-Modified headers in the Headers object.
	 *
	 * @param Headers $header The Headers object to set the headers in.
	 */
	public function setupHeadersExpiration(Headers $header): void
	{
		global $context;

		if (empty($context['no_last_modified']))
		{
			$header
				->header('Expires', self::DEFAULT_EXPIRES)
				->header('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
		}
	}

	/**
	 * Set up the logged user context
	 *
	 * What it does:
	 *  - Copies relevant user data from the user object to the global context.
	 */
	public function setupLoggedUserContext()
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
	 * Setup guest context
	 *
	 * What it does:
	 *  - Initializes global variables for guest user context
	 */
	public function setupGuestContext()
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
	public function setContextCommonStats()
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
	 * This is the only template included in the sources.
	 */
	public function template_rawdata()
	{
		global $context;

		echo $context['raw_data'];
	}

	/**
	 * Set the headers content type
	 *
	 * What it does:
	 *  - Sets the content type of the headers based on the provided context and API.
	 *
	 * @param Headers $header The Headers instance used to set the content type.
	 * @param string $api The API string used to determine the content type.
	 */
	public function setupHeadersContentType(Headers $header, string $api): void
	{
		$contentType = self::CONTENT_TYPES[$api] ?? 'text/html';

		$header->contentType($contentType, 'UTF-8');
	}

	/**
	 * Load default theme settings
	 *
	 * Updates the theme settings by replacing the URL and directory values with the default ones if the 'use_default_images'
	 * setting is set to 'defaults' and the 'default_template' setting is provided.
	 */
	public function loadDefaultThemeSettings(): void
	{
		global $settings;

		if (isset($settings['use_default_images'], $settings['default_template'])
			&& $settings['use_default_images'] === 'defaults')
		{
			$settings['theme_url'] = $settings['default_theme_url'];
			$settings['images_url'] = $settings['default_images_url'];
			$settings['theme_dir'] = $settings['default_theme_dir'];
		}
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
	public function setupNewsLines()
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
	 * Add a block of inline Javascript code to be executed later
	 *
	 * @param string $javascript
	 * @param bool $defer = false, define if the script should load in <head> or before the closing <html> tag
	 */
	public function addInlineJavascript($javascript, $defer = false)
	{
		$this->javascript->addInlineJavascript($javascript, $defer);
	}

	/**
	 * Add a Javascript variable for output later (for feeding text strings and similar to JS)
	 *
	 * @param array $vars array of vars to include in the output done as 'varname' => 'var value'
	 * @param bool $escape = false, whether or not to escape the value
	 */
	public function addJavascriptVar($vars, $escape = false)
	{
		$this->javascript->addJavascriptVar($vars, $escape);
	}

	/**
	 * Clean (delete) the hives (cache) for CSS and JS files
	 *
	 * @param string $type (Optional) The type of hives to clean. Default is 'all'. Possible values are 'all', 'css', 'js'.
	 * @return bool Returns true if the hives are successfully cleaned, otherwise false.
	 */
	public function cleanHives($type = 'all')
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
	 * If video embedding is enabled, this loads the needed JS and vars
	 */
	public function autoEmbedVideo()
	{
		global $txt, $modSettings;

		if (!empty($modSettings['enableVideoEmbeding']))
		{
			loadJavascriptFile('elk_jquery_embed.js', ['defer' => true]);

			$this->addInlineJavascript('
				const oEmbedtext = ({
					embed_limit : ' . (empty($modSettings['video_embed_limit']) ? 25 : $modSettings['video_embed_limit']) . ',
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
				document.addEventListener("DOMContentLoaded", () => {
					if ($.isFunction($.fn.linkifyvideo))
					{
						$().linkifyvideo(oEmbedtext);
					}
				});', true);
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
	public function progressiveWebApp()
	{
		global $modSettings, $boardurl, $settings;

//$modSettings['pwa_enabled'] = 1==1;

		$this->addJavascriptVar([
			'elk_board_url' => JavaScriptEscape($boardurl),
		]);
		loadJavascriptFile('elk_pwa.js', ['defer' => false]);

		// Not enabled, lets be sure to remove it should it exist
		if (empty($modSettings['pwa_enabled']))
		{
			$this->addInlineJavascript('
				elkPwa().removeServiceWorker();
			');

			return;
		}

		setPWACacheStale();
		$theme_scope = $this->getScopeFromUrl($settings['actual_theme_url']);
		$default_theme_scope = $this->getScopeFromUrl($settings['default_theme_url']);
		$sw_scope = $this->getScopeFromUrl($boardurl);
		$this->addInlineJavascript('
			document.addEventListener("DOMContentLoaded", function() {
				let myOptions = {
					swUrl: "elkServiceWorker.js",
					swOpt: {
						cache_stale: ' . JavaScriptEscape(CACHE_STALE) . ',
						cache_id: ' . JavaScriptEscape($modSettings['elk_pwa_cache_stale']) . ',
						theme_scope: ' . JavaScriptEscape($theme_scope) . ',
						default_theme_scope: ' . JavaScriptEscape($default_theme_scope) . ',
						sw_scope: ' . JavaScriptEscape($sw_scope) . ',
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
	public function getScopeFromUrl($url)
	{
		$parts = parse_url($url);

		return empty($parts['path']) ? '/' : '/' . trim($parts['path'], '/') . '/';
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
			loadJavascriptFile('ext/prettify.min.js', ['defer' => true]);

			$this->addInlineJavascript('
				document.addEventListener("DOMContentLoaded", () => {
				if (typeof prettyPrint === "function")
				{
					prettyPrint();
				}
			});', true);
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
	 * Relative times require a few variables be set in the JS
	 */
	public function relativeTimes()
	{
		global $modSettings, $context, $txt;

		// Relative times?
		if (!empty($modSettings['todayMod']) && $modSettings['todayMod'] > 2)
		{
			loadJavascriptFile('elk_relativeTime.js', ['defer' => true]);
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
	 * Set the context for showing the PM popup
	 *
	 * What it does:
	 *  - Sets the context variable $context['show_pm_popup'] based on user preferences and current action
	 */
	public function setContextShowPmPopup()
	{
		global $context, $options, $txt, $scripturl;

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
	public function setContextThemeData()
	{
		global $context, $scripturl, $settings, $boardurl, $modSettings, $txt, $mbname;

		if (empty($settings['theme_version']))
		{
			$this->addJavascriptVar(['elk_scripturl' => $scripturl], true);
		}

		$this->addJavascriptVar(['elk_forum_action' => getUrlQuery('action', $modSettings['default_forum_action'])], true);

		$context['page_title'] = $context['page_title'] ?? $mbname;
		$context['page_title_html_safe'] = Util::htmlspecialchars(un_htmlspecialchars($context['page_title'])) . (empty($context['current_page']) ? '' : ' - ' . $txt['page'] . (' ' . ($context['current_page'] + 1)));
		$context['favicon'] = $boardurl . '/favicon.ico';
		$context['apple_touch'] = $boardurl . '/themes/default/images/apple-touch-icon.png';
		$context['html_headers'] = $context['html_headers'] ?? '';
		$context['theme-color'] = $modSettings['pwa_theme-color'] ?? '#3d6e32';
		$context['pwa_manifest_enabled'] = !empty($modSettings['pwa_manifest_enabled']);
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
			$context['theme_variant'] = empty($_SESSION['id_variant']) ? (!empty($options['theme_variant']) ? $options['theme_variant'] : '') : ($_SESSION['id_variant']);
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
	 * Makes the default layers and languages available
	 *
	 * - Loads index and addon language files as needed
	 * - Loads xml, index or no templates as needed
	 * - Loads templates as defined by $settings['theme_templates']
	 */
	public function loadDefaultLayers()
	{
		global $settings;

		$simpleActions = [
			'quickhelp',
			'printpage',
			'quotefast',
		];

		call_integration_hook('integrate_simple_actions', [&$simpleActions]);

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
		elseif (in_array($action, $simpleActions, true))
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
	 * Return the instance of /ElkArte/Themes/Css
	 */
	public function themeCss()
	{
		return $this->css;
	}

	/**
	 * Return the instance of /ElkArte/Themes/Javascript
	 */
	public function themeJs()
	{
		return $this->javascript;
	}
}
