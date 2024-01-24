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
use ElkArte\FileFunctions;
use ElkArte\Http\Headers;
use ElkArte\HttpReq;
use ElkArte\Languages\Txt;
use ElkArte\SiteCombiner;
use ElkArte\User;
use ElkArte\Util;
use ElkArte\ValuesContainer;

/**
 * Class Theme
 */
abstract class Theme
{
	/** @var string */
	public const DEFAULT_EXPIRES = 'Mon, 26 Jul 1997 05:00:00 GMT';
	/** @var string */
	public const STANDARD = 'standard';
	/** @var string */
	public const DEFERRED = 'defer';
	/** @var int */
	public const ALL = -1;
	/** @var array */
	private const CONTENT_TYPES = [
		'fatal_error' => 'text/html',
		'json' => 'application/json',
		'xml' => 'text/xml'
	];
	/** @var ValuesContainer */
	public $user;
	/** @var HttpReq user input variables */
	public $_req;
	/** @var int The id of the theme being used */
	protected $id;
	/** @var array */
	protected $links = [];
	/** @var array All of the JS files to include */
	protected $js_files = [];
	/** @var array Any inline JS to output */
	protected $js_inline = [
		'standard' => [],
		'defer' => [],
	];
	/** @var array JS variables to output */
	protected $js_vars = [];
	/** @var array Inline CSS */
	protected $css_rules = [];
	/** @var array CSS files */
	protected $css_files = [];
	/** @var string[] Holds base actions that we do not want crawled / indexed */
	public $no_index_actions = [];
	/** @var bool Right to left language support */
	protected $rtl;
	/** @var Templates */
	private $templates;
	/** @var TemplateLayers */
	private $layers;

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

		$this->js_files = &$GLOBALS['context']['js_files'];
		$this->js_vars = &$GLOBALS['context']['js_vars'];

		$this->css_files = &$GLOBALS['context']['css_files'];
		$this->css_rules = &$GLOBALS['context']['css_rules'];
		if (empty($this->css_rules))
		{
			$this->css_rules = [
				'all' => '',
				'media' => [],
			];
		}

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
	}

	/**
	 * Initialize the template... mainly little settings.
	 *
	 * @return array Theme settings
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
	 * Add a Javascript variable for output later (for feeding text strings and similar to JS)
	 *
	 * @param array $vars array of vars to include in the output done as 'varname' => 'var value'
	 * @param bool $escape = false, whether or not to escape the value
	 */
	public function addJavascriptVar($vars, $escape = false)
	{
		if (empty($vars) || !is_array($vars))
		{
			return;
		}

		foreach ($vars as $key => $value)
		{
			$this->js_vars[$key] = !empty($escape) ? JavaScriptEscape($value) : $value;
		}
	}

	/**
	 * Add a CSS rule to a style tag in head.
	 *
	 * @param string $rules the CSS rule/s
	 * @param null|string $media = null, the media query the rule belongs to
	 */
	public function addCSSRules($rules, $media = null)
	{
		if (empty($rules))
		{
			return;
		}

		if ($media === null)
		{
			$this->css_rules['all'] = $this->css_rules['all'] ?? '';
			$this->css_rules['all'] .= $rules;
		}
		else
		{
			$this->css_rules['media'][$media] = $this->css_rules['media'][$media] ?? '';
			$this->css_rules['media'][$media] .= $rules;
		}
	}

	/**
	 * Returns javascript vars loaded with addJavascriptVar function
	 *
	 * @return array
	 */
	public function getJavascriptVars()
	{
		return $this->js_vars;
	}

	/**
	 * Returns inline javascript of a give type that was added with addInlineJavascript function
	 *
	 * @param int $type One of ALL, SELF, DEFERRED class constants
	 *
	 * @return array
	 * @throws \Exception if the type is not known
	 */
	public function getInlineJavascript($type = self::ALL)
	{
		switch ($type)
		{
			case self::ALL:
				return $this->js_inline;
			case self::DEFERRED:
				return $this->js_inline[self::DEFERRED];
			case self::STANDARD:
				return $this->js_inline[self::STANDARD];
		}

		throw new \Exception('Unknown inline Javascript type');
	}

	/**
	 * Add a block of inline Javascript code to be executed later
	 *
	 * What it does:
	 * - only use this if you have to, generally external JS files are better, but for very small scripts
	 *   or for scripts that require help from PHP/whatever, this can be useful.
	 * - all code added with this function is added to the same <script> tag so do make sure your JS is clean!
	 *
	 * @param string $javascript
	 * @param bool $defer = false, define if the script should load in <head> or before the closing <html> tag
	 */
	public function addInlineJavascript($javascript, $defer = false)
	{
		if (!empty($javascript))
		{
			$this->js_inline[(!empty($defer) ? self::DEFERRED : self::STANDARD)][] = $javascript;
		}
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
		$this->rtl = (bool)$toggle;

		return $this;
	}

	/**
	 * Provide a way to fetch the js_files array
	 *
	 * @return array
	 */
	public function getJSFiles()
	{
		return $this->js_files;
	}

	public function getRequestAPI(): string
	{
		return $this->_req->getRequest('api', 'trim', '');
	}

	public function setupHeadersExpiration(Headers $header, array $context): void
	{
		if (empty($context['no_last_modified']))
		{
			$header
				->header('Expires', self::DEFAULT_EXPIRES)
				->header('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
		}
	}

	/**
	 * Setup the logged user context
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
		if ($this->user->unread_messages > ($_SESSION['unread_messages'] ?? 0))
		{
			$context['user']['popup_messages'] = true;
		}
		else
		{
			$context['user']['popup_messages'] = false;
		}

		$_SESSION['unread_messages'] = $this->user->unread_messages;

		$context['user']['avatar'] = [
			'href' => !empty($this->user->avatar['href']) ? $this->user->avatar['href'] : '',
			'image' => !empty($this->user->avatar['image']) ? $this->user->avatar['image'] : '',
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

		if (!empty($modSettings['registration_method']) && (int)$modSettings['registration_method'] === 1)
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

	public function setupHeadersContentType(Headers $header, array $context, string $api): void
	{
		if (isset($context['sub_template']))
		{
			$api = $context['sub_template'];
		}

		$contentType = self::CONTENT_TYPES[$api] ?? 'text/html';

		$header->contentType($contentType, 'UTF-8');
	}

	public function loadDefaultThemeSettings(array &$settings): void
	{
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
	 * Output the Javascript, including files, inline and vars
	 *
	 * What it does:
	 *
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

		// Use a specified version of jquery 3.7.1  / 1.13.2
		$jquery_version = '3.7.1';
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

			call_integration_hook('post_javascript_combine', [&$combine_standard_name, &$combine_deferred_name, $combiner]);

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
		global $modSettings, $settings;

		// Scrunch
		if (!empty($modSettings['minify_css_js']))
		{
			// Inline can have user prefs etc. so caching is not a viable option
			// Benchmarked: at 0.01627s wall clock, 16.26ms for computations, 42% size reduction
			// for large load, 10.3ms (.0104s) for normal sized inline.
			$combiner = new SiteCombiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url'], true);
			foreach ($files as $i => $js_block)
			{
				$files[$i] = $combiner->jsMinify($js_block);
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

			call_integration_hook('post_css_combine', [&$combine_name, $combiner]);

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
		global $modSettings, $settings;

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

		if ($style_tag !== '')
		{
			if (!empty($modSettings['minify_css_js']))
			{
				$combiner = new SiteCombiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url'], true);
				$style_tag = $combiner->cssMinify($style_tag, true);
			}

			echo '
	<style>
	' . $style_tag . '
	</style>';
		}
	}

	/**
	 * Clean (delete) the hives (cache) for CSS and JS files
	 *
	 * @param string $type (Optional) The type of hives to clean. Default is 'all'. Possible values are 'all', 'css', 'js'.
	 * @return bool Returns true if the hives are successfully cleaned, otherwise false.
	 */
	private function cleanHives($type = 'all')
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
				document.addEventListener("DOMContentLoaded", () => {
					if ($.isFunction($.fn.linkifyvideo))
					{
						$().linkifyvideo(oEmbedtext);
					}
				});', true);
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
			loadJavascriptFile('prettify.min.js', ['defer' => true]);

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
		global $context, $scripturl, $settings, $boardurl, $modSettings, $txt;

		if (empty($settings['theme_version']))
		{
			$this->addJavascriptVar(['elk_scripturl' => $scripturl], true);
		}

		$this->addJavascriptVar(['elk_forum_action' => getUrlQuery('action', $modSettings['default_forum_action'])], true);

		$context['page_title'] = $context['page_title'] ?? '';
		$context['page_title_html_safe'] = Util::htmlspecialchars(un_htmlspecialchars($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . (' ' . ($context['current_page'] + 1)) : '');
		$context['favicon'] = $boardurl . '/mobile.png';

		$context['html_headers'] = $context['html_headers'] ?? '';
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
}
