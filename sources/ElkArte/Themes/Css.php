<?php

/**
 * Helper class for Theme, handles CSS
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Themes;

use ElkArte\Helper\SiteCombiner;

/**
 * Class Css
 *
 * This class provides functionality for managing CSS files and inline CSS rules,
 * including adding rules, combining files, minification, and output.
 */
class Css
{
	/** @var array Inline CSS */
	protected $css_rules = [];

	/** @var array CSS files */
	protected $css_files = [];

	/**
	 * Constructor for the class.
	 *
	 * This method initializes the object and sets the CSS files and rules.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->css_files = &$GLOBALS['context']['css_files'];
		$this->css_rules = &$GLOBALS['context']['css_rules'];
		if (empty($this->css_rules))
		{
			$this->css_rules = ['all' => '', 'media' => []];
		}
	}

	/**
	 * Output the CSS files
	 *
	 * What it does:
	 *  - If the admin option to combine files is set, will use the Combiner.class
	 */
	public function template_css(): void
	{
		global $modSettings, $settings;

		// Use this hook to work with CSS files pre-output
		call_integration_hook('pre_css_output');

		if (empty($this->css_files))
		{
			return;
		}

		// Minify
		if (!empty($modSettings['minify_css_js']))
		{
			$combiner = new SiteCombiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url']);
			$this->css_files = $combiner->site_css_minify($this->css_files);
		}

		// Output all the files
		foreach ($this->css_files as $id => $file)
		{
			echo '
	<link rel="stylesheet" href="', $file['filename'], '" id="', $id, '" />';
		}
	}

	/**
	 * Add a CSS rule to a style tag in head.
	 *
	 * @param string $rules the CSS rule/s
	 * @param null|string $media = null, the media query the rule belongs to
	 */
	public function addCSSRules($rules, $media = null): void
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
	 * Generates inline CSS code based on predefined CSS rules and outputs it within a `<style>` tag.
	 *
	 * The method processes the available CSS rules, optionally combines and minifies them,
	 * and outputs the result as inline CSS within the HTML document. It handles both general
	 * CSS rules and media-specific rules, ensuring media are placed after general.
	 *
	 * @return void
	 */
	public function template_inlinecss(): void
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
				$style_tag = $combiner->cssMinify($style_tag);
			}

			echo '
	<style>
	' . $style_tag . '
	</style>';
		}
	}
}
