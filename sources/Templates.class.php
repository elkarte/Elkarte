<?php

/**
 * This file has functions dealing with loading and precessing template files.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:        BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Class Templates
 *
 * This class loads and processes template files and sheets.
 */
class Templates
{
	protected static $instance = null;

	/**
	 * Template directory's that we will be searching for the sheets
	 * @var array
	 */
	public $dirs = array();

	/**
	 * Template sheets that have not loaded
	 * @var array
	 */
	protected $delayed = array();

	/**
	 * Holds the file that are in the include list
	 * @var array
	 */
	protected $templates = array();

	/**
	 * Tracks if the default index.css has been loaded
	 * @var bool
	 */
	protected $default_loaded = false;

	/**
	 * Templates constructor.
	 */
	protected function __construct()
	{
		// We want to be able to figure out any errors...
		@ini_set('track_errors', '1');
	}

	/**
	 * Load a template - if the theme doesn't include it, use the default.
	 *
	 * What it does:
	 * - Loads a template file with the name template_name from the current, default, or base theme.
	 * - Detects a wrong default theme directory and tries to work around it.
	 * - Can be used to only load style sheets by using false as the template name
	 *   loading of style sheets with this function is deprecated, use loadCSSFile instead
	 * - If $this->dirs is empty, it delays the loading of the template
	 *
	 * @uses $this->requireTemplate() to actually load the file.
	 *
	 * @param string|false $template_name
	 * @param string[]|string $style_sheets any style sheets to load with the template
	 * @param bool $fatal = true if fatal is true, dies with an error message if the template cannot be found
	 *
	 * @return boolean|null
	 */
	public function load($template_name, $style_sheets = array(), $fatal = true)
	{
		// If we don't know yet the default theme directory, let's wait a bit.
		if (empty($this->dirs))
		{
			$this->delayed[] = array(
				$template_name,
				$style_sheets,
				$fatal
			);

			return;
		}
		// If instead we know the default theme directory and we have delayed something, it's time to process
		elseif (!empty($this->delayed))
		{
			foreach ($this->delayed as $val)
			{
				$this->requireTemplate($val[0], $val[1], $val[2]);
			}

			// Forget about them (load them only once)
			$this->delayed = array();
		}

		$this->requireTemplate($template_name, $style_sheets, $fatal);
	}

	/**
	 * <b>Internal function! Do not use it, use loadTemplate instead</b>
	 *
	 * What it does:
	 * - Loads a template file with the name template_name from the current, default, or base theme.
	 * - Detects a wrong default theme directory and tries to work around it.
	 * - Can be used to only load style sheets by using false as the template name
	 *  loading of style sheets with this function is deprecated, use loadCSSFile instead
	 *
	 * @uses $this->templateInclude() to include the file.
	 *
	 * @param string|false $template_name
	 * @param string[]|string $style_sheets any style sheets to load with the template
	 * @param bool $fatal = true if fatal is true, dies with an error message if the template cannot be found
	 *
	 * @return boolean|null
	 */
	protected function requireTemplate($template_name, $style_sheets, $fatal)
	{
		global $context, $settings, $txt, $scripturl, $db_show_debug;

		if (!is_array($style_sheets))
		{
			$style_sheets = array($style_sheets);
		}

		if ($this->default_loaded === false)
		{
			loadCSSFile('index.css');
			$this->default_loaded = true;
		}

		// Any specific template style sheets to load?
		if (!empty($style_sheets))
		{
			trigger_error('Use of loadTemplate to add style sheets to the head is deprecated.', E_USER_DEPRECATED);
			$sheets = array();
			foreach ($style_sheets as $sheet)
			{
				$sheets[] = stripos('.css', $sheet) !== false ? $sheet : $sheet . '.css';
				if ($sheet == 'admin' && !empty($context['theme_variant']))
				{
					$sheets[] = $context['theme_variant'] . '/admin' . $context['theme_variant'] . '.css';
				}
			}

			loadCSSFile($sheets);
		}

		// No template to load?
		if ($template_name === false)
		{
			return true;
		}

		$loaded = false;
		$template_dir = '';
		foreach ($this->dirs as $template_dir)
		{
			if (file_exists($template_dir . '/' . $template_name . '.template.php'))
			{
				$loaded = true;
				$this->templateInclude($template_dir . '/' . $template_name . '.template.php', true);
				break;
			}
		}

		if ($loaded)
		{
			if ($db_show_debug === true)
			{
				Debug::get()->add('templates', $template_name . ' (' . basename($template_dir) . ')');
			}

			// If they have specified an initialization function for this template, go ahead and call it now.
			if (function_exists('template_' . $template_name . '_init'))
			{
				call_user_func('template_' . $template_name . '_init');
			}
		}
		// Hmmm... doesn't exist?!  I don't suppose the directory is wrong, is it?
		elseif (!file_exists($settings['default_theme_dir']) && file_exists(BOARDDIR . '/themes/default'))
		{
			$settings['default_theme_dir'] = BOARDDIR . '/themes/default';
			$this->addDirectory($settings['default_theme_dir']);

			if (!empty($context['user']['is_admin']) && !isset($_GET['th']))
			{
				loadLanguage('Errors');

				if (!isset($context['security_controls_files']['title']))
				{
					$context['security_controls_files']['title'] = $txt['generic_warning'];
				}

				$context['security_controls_files']['errors']['theme_dir'] = '<a href="' . $scripturl . '?action=admin;area=theme;sa=list;th=1;' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['theme_dir_wrong'] . '</a>';
			}

			loadTemplate($template_name);
		}
		// Cause an error otherwise.
		elseif ($template_name !== 'Errors' && $template_name !== 'index' && $fatal)
		{
			throw new Elk_Exception('theme_template_error', 'template', array((string) $template_name));
		}
		elseif ($fatal)
		{
			die(Errors::instance()->log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load themes/default/%s.template.php!', (string) $template_name), 'template'));
		}
		else
		{
			return false;
		}

		return true;
	}

	/**
	 * Load the template/language file using eval or require? (with eval we can show an error message!)
	 *
	 * What it does:
	 * - Loads the template or language file specified by filename.
	 * - Uses eval unless disableTemplateEval is enabled.
	 * - Outputs a parse error if the file did not exist or contained errors.
	 * - Attempts to detect the error and line, and show detailed information.
	 *
	 * @param string $filename
	 * @param bool $once = false, if true only includes the file once (like include_once)
	 */
	public function templateInclude($filename, $once = false)
	{
		// I know this looks weird but this is used to include $txt files. If the parent doesn't declare them global
		// the scope will be local to this function. IOW, don't remove this line!
		global $txt;

		// Don't include the file more than once, if $once is true.
		if ($once && in_array($filename, $this->templates))
		{
			return;
		}
		// Add this file to the include list, whether $once is true or not.
		else
		{
			$this->templates[] = $filename;
		}

		// Load it if we find it
		$file_found = file_exists($filename);

		if ($once && $file_found)
		{
			require_once($filename);
		}
		elseif ($file_found)
		{
			require($filename);
		}

		if ($file_found !== true)
		{
			$this->templateNotFound($filename);
		}
	}

	/**
	 * Displays an error when a template is not found or has syntax errors preventing its loading
	 *
	 * @param string $filename
	 */
	protected function templateNotFound($filename)
	{
		global $context, $txt, $scripturl, $modSettings, $boardurl;
		global $maintenance, $mtitle, $mmessage;

		@ob_end_clean();
		if (!empty($modSettings['enableCompressedOutput']))
		{
			ob_start('ob_gzhandler');
		}
		else
		{
			ob_start();
		}

		if (isset($_GET['debug']))
		{
			header('Content-Type: application/xhtml+xml; charset=UTF-8');
		}

		// Don't cache error pages!!
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		if (!isset($txt['template_parse_error']))
		{
			$txt['template_parse_error'] = 'Template Parse Error!';
			$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system.  This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please contact the administrator.<br /><br />You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			$txt['template_parse_error_details'] = 'There was a problem loading the <span style="font-family: monospace;"><strong>%1$s</strong></span> template or language file.  Please check the syntax and try again - remember, single quotes (<span style="font-family: monospace;">\'</span>) often have to be escaped with a slash (<span style="font-family: monospace;">\\</span>).  To see more specific error information from PHP, try <a href="%2$s%1$s" class="extern">accessing the file directly</a>.<br /><br />You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="%3$s">use the default theme</a>.';
			$txt['template_parse_undefined'] = 'An undefined error occurred during the parsing of this template';
		}

		// First, let's get the doctype and language information out of the way.
		echo '<!DOCTYPE html>
<html ', !empty($context['right_to_left']) ? 'dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';

		if (!empty($maintenance) && !allowedTo('admin_forum'))
		{
			echo '
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';
		}
		elseif (!allowedTo('admin_forum'))
		{
			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', $txt['template_parse_error_message'], '
	</body>
</html>';
		}
		else
		{
			require_once(SUBSDIR . '/Package.subs.php');

			$error = fetch_web_data($boardurl . strtr($filename, array(BOARDDIR => '', strtr(BOARDDIR, '\\', '/') => '')));
			if (empty($error) && ini_get('track_errors') && !empty($php_errormsg))
			{
				$error = $php_errormsg;
			}
			elseif (empty($error))
			{
				$error = $txt['template_parse_undefined'];
			}

			$error = strtr($error, array('<b>' => '<strong>', '</b>' => '</strong>'));

			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', sprintf($txt['template_parse_error_details'], strtr($filename, array(BOARDDIR => '', strtr(BOARDDIR, '\\', '/') => '')), $boardurl, $scripturl . '?theme=1');

			if (!empty($error))
			{
				echo '
		<hr />

		<div style="margin: 0 20px;"><span style="font-family: monospace;">', strtr(strtr($error, array('<strong>' . BOARDDIR => '<strong>...', '<strong>' . strtr(BOARDDIR, '\\', '/') => '<strong>...')), '\\', '/'), '</span></div>';
			}

			// I know, I know... this is VERY COMPLICATED.  Still, it's good.
			if (preg_match('~ <strong>(\d+)</strong><br( /)?' . '>$~i', $error, $match) != 0)
			{
				$data = file($filename);
				$data2 = highlight_php_code(implode('', $data));
				$data2 = preg_split('~\<br( /)?\>~', $data2);

				// Fix the PHP code stuff...
				if (!isBrowser('gecko'))
				{
					$data2 = str_replace("\t", '<span style="white-space: pre;">' . "\t" . '</span>', $data2);
				}
				else
				{
					$data2 = str_replace('<pre style="display: inline;">' . "\t" . '</pre>', "\t", $data2);
				}

				// Now we get to work around a bug in PHP where it doesn't escape <br />s!
				$j = -1;
				foreach ($data as $line)
				{
					$j++;

					if (substr_count($line, '<br />') == 0)
					{
						continue;
					}

					$n = substr_count($line, '<br />');
					for ($i = 0; $i < $n; $i++)
					{
						$data2[$j] .= '&lt;br /&gt;' . $data2[$j + $i + 1];
						unset($data2[$j + $i + 1]);
					}
					$j += $n;
				}
				$data2 = array_values($data2);
				array_unshift($data2, '');

				echo '
		<div style="margin: 2ex 20px; width: 96%; overflow: auto;"><pre style="margin: 0;">';

				// Figure out what the color coding was before...
				$line = max($match[1] - 9, 1);
				$last_line = '';
				for ($line2 = $line - 1; $line2 > 1; $line2--)
				{
					if (strpos($data2[$line2], '<') !== false)
					{
						if (preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line2], $color_match) != 0)
						{
							$last_line = $color_match[1];
						}
						break;
					}
				}

				// Show the relevant lines...
				for ($n = min($match[1] + 4, count($data2) + 1); $line <= $n; $line++)
				{
					if ($line == $match[1])
					{
						echo '</pre><div style="background: #ffb0b5;"><pre style="margin: 0;">';
					}

					echo '<span style="color: black;">', sprintf('%' . strlen($n) . 's', $line), ':</span> ';
					if (isset($data2[$line]) && $data2[$line] != '')
					{
						echo substr($data2[$line], 0, 2) == '</' ? preg_replace('~^</[^>]+>~', '', $data2[$line]) : $last_line . $data2[$line];
					}

					if (isset($data2[$line]) && preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line], $color_match) != 0)
					{
						$last_line = $color_match[1];
						echo '</', substr($last_line, 1, 4), '>';
					}
					elseif ($last_line != '' && strpos($data2[$line], '<') !== false)
					{
						$last_line = '';
					}
					elseif ($last_line != '' && $data2[$line] != '')
					{
						echo '</', substr($last_line, 1, 4), '>';
					}

					if ($line == $match[1])
					{
						echo '</pre></div><pre style="margin: 0;">';
					}
					else
					{
						echo "\n";
					}
				}

				echo '</pre></div>';
			}

			echo '
	</body>
</html>';
		}

		die;
	}

	/**
	 * Load a sub-template.
	 *
	 * What it does:
	 * - loads the sub template specified by sub_template_name, which must be in an already-loaded template.
	 * - if ?debug is in the query string, shows administrators a marker after every sub template
	 * for debugging purposes.
	 *
	 * @todo get rid of reading $_REQUEST directly
	 *
	 * @param string $sub_template_name
	 * @param bool|string $fatal = false, $fatal = true is for templates that
	 *   shouldn't get a 'pretty' error screen 'ignore' to skip
	 */
	public function loadSubTemplate($sub_template_name, $fatal = false)
	{
		global $txt, $db_show_debug;

		if ($sub_template_name === false)
		{
			return;
		}

		if ($db_show_debug === true)
		{
			Debug::get()->add('sub_templates', $sub_template_name);
		}

		// Figure out what the template function is named.
		$theme_function = 'template_' . $sub_template_name;

		if (function_exists($theme_function))
		{
			$theme_function();
		}
		elseif ($fatal === false)
		{
			throw new Elk_Exception('theme_template_error', 'template', array((string) $sub_template_name));
		}
		elseif ($fatal !== 'ignore')
		{
			die(Errors::instance()->log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load the %s sub template!', (string) $sub_template_name), 'template'));
		}
	}

	/**
	 * Reloads the directory stack/queue to ensure they are searched in the proper order
	 *
	 * @param array $settings
	 */
	public function reloadDirectories(array $settings)
	{
		$this->dirs = array();

		if (!empty($settings['theme_dir']))
		{
			$this->addDirectory($settings['theme_dir']);
		}

		// Based on theme (if there is one).
		if (!empty($settings['base_theme_dir']))
		{
			$this->addDirectory($settings['base_theme_dir']);
		}

		// Lastly the default theme.
		if ($settings['theme_dir'] !== $settings['default_theme_dir'])
		{
			$this->addDirectory($settings['default_theme_dir']);
		}
	}

	/**
	 * Add a template directory to the search stack
	 *
	 * @param string $dir
	 *
	 * @return $this
	 */
	public function addDirectory($dir)
	{
		$this->dirs[] = (string) $dir;

		return $this;
	}

	/**
	 * Sets the directory array in to the class
	 *
	 * @param array $dirs
	 */
	public function setDirectories(array $dirs)
	{
		$this->dirs = $dirs;
	}

	/**
	 * Returns if theme directory's have been loaded
	 *
	 * @return bool
	 */
	public function hasDirectories()
	{
		return !empty($this->dirs);
	}

	/**
	 * Return the directory's that have been loaded
	 *
	 * @return array
	 */
	public function getTemplateDirectories()
	{
		return $this->dirs;
	}

	/**
	 * Return the template sheet stack
	 *
	 * @return array
	 */
	public function getIncludedTemplates()
	{
		return $this->templates;
	}

	/**
	 * Find and return Templates instance if it exists,
	 * or create a new instance
	 *
	 * @return Templates
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = new Templates;
		}

		return self::$instance;
	}
}