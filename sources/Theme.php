<?php

/**
 * The main theme class
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:        BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
{
	die('No access...');
}

abstract class Theme
{
	const STANDARD = 'standard';
	const DEFERRED = 'defer';
	const ALL = -1;

	protected $id;

	protected $templates;
	protected $layers;

	protected $html_headers = array();
	protected $links = array();
	protected $js_files = array();
	protected $js_inline = array(
		'standard' => array(),
		'defer' => array()
	);
	protected $js_vars = array();
	protected $css_files = array();

	protected $rtl;

	/**
	 * @param int $id
	 */
	public function __construct($id)
	{
		$this->layers = Template_Layers::getInstance();
		$this->templates = Templates::getInstance();

		$this->css_files = &$GLOBALS['context']['css_files'];
		$this->js_files = &$GLOBALS['context']['js_files'];
	}

	/**
	 * Add a Javascript variable for output later (for feeding text strings and similar to JS)
	 *
	 * @param mixed[] $vars array of vars to include in the output done as 'varname' => 'var value'
	 * @param bool $escape = false, whether or not to escape the value
	 */
	public function addJavascriptVar($vars, $escape = false)
	{
		if (empty($vars) || !is_array($vars))
		{
			return;
		}

		foreach ($vars as $key => $value)
			$this->js_vars[$key] = !empty($escape) ? JavaScriptEscape($value) : $value;
	}

	public function getJavascriptVars()
	{
		return $this->js_vars;
	}

	/**
	 * @param int|self::ALL $type One of ALL, SELF, DEFERRED class constants
	 *
	 * @return array
	 * @throws Exception if the type is not known
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
	function addInlineJavascript($javascript, $defer = false)
	{
		if (!empty($javascript))
		{
			$this->js_inline[(!empty($defer) ? self::DEFERRED : self::STANDARD)][] = $javascript;
		}
	}

	public function setRTL($toggle)
	{
		$this->rtl = (bool) $toggle;

		return $this;
	}
}