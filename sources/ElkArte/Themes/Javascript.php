<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Themes;

use ElkArte\SiteCombiner;

/**
 * Part of core Theme functions.  Responsible for the output the Javascript, including files,
 * inline and vars.  Will do simple compression if enabled in the ACP
 */
class Javascript
{
	/** @var string */
	public const STANDARD = 'standard';
	/** @var string */
	public const DEFERRED = 'defer';
	/** @var array Any inline JS to output */
	protected $js_inline = ['standard' => [], 'defer' => []];
	/** @var array All the JS files to include */
	public $js_files = [];
	/** @var array JS variables to output */
	public $js_vars = [];

	/**
	 * Class constructor
	 *
	 * What it does:
	 * - Initializes the object and assigns references to the $js_files and $js_vars arrays
	 *   defined in the global context.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->js_files = &$GLOBALS['context']['js_files'];
		$this->js_vars = &$GLOBALS['context']['js_vars'];
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
	public function templateJquery()
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
	public function templateJavascriptFiles()
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
	public function formatInlineJS($files, $tabs = 3)
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
	 * Provide a way to fetch the js_files array
	 *
	 * @return array
	 */
	public function getJSFiles()
	{
		return $this->js_files;
	}
}
