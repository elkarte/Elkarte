<?php

/**
 * This file has functions dealing with loading and precessing template files.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 * license:   BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Themes;

use BadFunctionCallException;
use Debug;
use \ElkArte\Exceptions\Exception;
use ElkArte\Errors\Errors;
use Error;
use Generator;


/**
 * Class Templates
 *
 * This class loads and processes template files and sheets.
 */
class Templates
{
	/**
	 * Template directory's that we will be searching for the sheets
	 *
	 * @var array
	 */
	public $dirs = [];

	/**
	 * Template sheets that have not loaded
	 *
	 * @var array
	 */
	protected $delayed = [];

	/**
	 * Holds the file that are in the include list
	 *
	 * @var array
	 */
	protected $templates = [];

	/**
	 * Tracks if the default index.css has been loaded
	 *
	 * @var bool
	 */
	protected $default_loaded = false;

	/**
	 * Templates constructor.
	 */
	public function __construct()
	{
		// We want to be able to figure out any errors...
		@ini_set('track_errors', '1');
	}

	/**
	 * Load a template - if the theme doesn't include it, use the default.
	 *
	 * What it does:
	 * - Loads a template file with the name template_name from the current, default, or
	 * base theme.
	 * - Detects a wrong default theme directory and tries to work around it.
	 * - Can be used to only load style sheets by using false as the template name
	 *   loading of style sheets with this function is deprecated, use loadCSSFile
	 * instead
	 * - If $this->dirs is empty, it delays the loading of the template
	 *
	 * @uses $this->requireTemplate() to actually load the file.
	 *
	 * @param string|false $template_name
	 * @param string[]|string $style_sheets any style sheets to load with the template
	 * @param bool $fatal = true if fatal is true, dies with an error message if the
	 *     template cannot be found
	 *
	 * @return boolean|null
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function load($template_name, $style_sheets = [], $fatal = true): ?bool
	{
		// If we don't know yet the default theme directory, let's wait a bit.
		if (empty($this->dirs))
		{
			$this->delayed[] = [
				$template_name,
				$style_sheets,
				$fatal,
			];

			return null;
		}
		// If instead we know the default theme directory and we have delayed something, it's time to process
		elseif (!empty($this->delayed))
		{
			foreach ($this->delayed as $val)
			{
				$this->requireTemplate($val[0], $val[1], $val[2]);
			}

			// Forget about them (load them only once)
			$this->delayed = [];
		}

		return $this->requireTemplate($template_name, $style_sheets, $fatal);
	}

	/**
	 * <b>Internal function! Do not use it, use theme()->getTemplates()->load instead</b>
	 *
	 * What it does:
	 * - Loads a template file with the name template_name from the current, default, or
	 * base theme.
	 * - Detects a wrong default theme directory and tries to work around it.
	 * - Can be used to only load style sheets by using false as the template name
	 *  loading of style sheets with this function is deprecated, use loadCSSFile instead
	 *
	 * @uses $this->templateInclude() to include the file.
	 *
	 * @param string|false $template_name
	 * @param string[]|string $style_sheets any style sheets to load with the template
	 * @param bool $fatal = true if fatal is true, dies with an error message if the
	 *     template cannot be found
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception theme_template_error
	 */
	protected function requireTemplate($template_name, $style_sheets, $fatal): bool
	{
		global $context, $settings, $txt, $scripturl, $db_show_debug;

		if (!is_array($style_sheets))
		{
			$style_sheets = [$style_sheets];
		}

		if ($this->default_loaded === false)
		{
			loadCSSFile('index.css');
			$this->default_loaded = true;
		}

		// Any specific template style sheets to load?
		if (!empty($style_sheets))
		{
			trigger_error(
				'Use of theme()->getTemplates()->load to add style sheets to the head is deprecated.',
				E_USER_DEPRECATED
			);
			$sheets = [];
			foreach ($style_sheets as $sheet)
			{
				$sheets[] = stripos('.css', $sheet) !== false ? $sheet : $sheet . '.css';
				if ($sheet == 'admin' && !empty($context['theme_variant']))
				{
					$sheets[] =
						$context['theme_variant'] . '/admin' . $context['theme_variant'] . '.css';
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
				$this->templateInclude(
					$template_dir . '/' . $template_name . '.template.php',
					true
				);
				break;
			}
		}

		if ($loaded)
		{
			if ($db_show_debug === true)
			{
				\ElkArte\Debug::instance()->add(
					'templates',
					$template_name . ' (' . basename($template_dir) . ')'
				);
			}

			// If they have specified an initialization function for this template, go ahead and call it now.
			if (function_exists('template_' . $template_name . '_init'))
			{
				call_user_func('template_' . $template_name . '_init');
			}
		}
		// Hmmm... doesn't exist?!  I don't suppose the directory is wrong, is it?
		elseif (!file_exists($settings['default_theme_dir']) && file_exists(
				BOARDDIR . '/themes/default'
			))
		{
			$settings['default_theme_dir'] = BOARDDIR . '/themes/default';
			$this->addDirectory($settings['default_theme_dir']);

			if (!empty($context['user']['is_admin']) && !isset($_GET['th']))
			{
				$this->loadLanguageFile('Errors');

				if (!isset($context['security_controls_files']['title']))
				{
					$context['security_controls_files']['title'] =
						$txt['generic_warning'];
				}

				$context['security_controls_files']['errors']['theme_dir'] =
					'<a href="' . $scripturl . '?action=admin;area=theme;sa=list;th=1;' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['theme_dir_wrong'] . '</a>';
			}

			$this->load($template_name);
		}
		// Cause an error otherwise.
		elseif ($template_name !== 'Errors' && $template_name !== 'index' && $fatal)
		{
			throw new Exception(
				'theme_template_error',
				'template',
				[(string) $template_name]
			);
		}
		elseif ($fatal)
		{
			throw new Exception(
				sprintf(
					isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load themes/default/%s.template.php!',
					(string) $template_name
				), 'template'
			);
		}
		else
		{
			return false;
		}

		return true;
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
	public function loadLanguageFiles(
		array $template_name,
		$lang = '',
		$fatal = true,
		$force_reload = false
	) {
		global $user_info, $language, $settings, $modSettings;
		global $db_show_debug, $txt;
		static $already_loaded = [];

		// Default to the user's language.
		if ($lang == '')
		{
			$lang = isset($user_info['language']) ? $user_info['language'] : $language;
		}

		// Make sure we have $settings - if not we're in trouble and need to find it!
		if (empty($settings['default_theme_dir']))
		{
			$this->loadEssentialThemeData();
		}

		$fix_arrays = false;
		// For each file open it up and write it out!
		foreach ($template_name as $template)
		{
			if (!$force_reload && isset($already_loaded[$template]) && $already_loaded[$template] == $lang)
			{
				return $lang;
			}

			if ($template === 'index')
			{
				$fix_arrays = true;
			}

			// Do we want the English version of language file as fallback?
			if (empty($modSettings['disable_language_fallback']) && $lang != 'english')
			{
				$this->loadLanguageFiles([$template], 'english', false);
			}

			// Try to find the language file.
			$found = false;
			foreach ($this->dirs as $template_dir)
			{
				if (file_exists(
					$file =
						$template_dir . '/languages/' . $lang . '/' . $template . '.' . $lang . '.php'
				))
				{
					// Include it!
					$this->templateInclude($file);

					// Note that we found it.
					$found = true;

					// Keep track of what we're up to, soldier.
					if ($db_show_debug === true)
					{
						\ElkArte\Debug::instance()->add(
							'language_files',
							$template . '.' . $lang . ' (' . basename(
								$settings['theme_url']
							) . ')'
						);
					}

					// Remember what we have loaded, and in which language.
					$already_loaded[$template] = $lang;

					break;
				}
			}

			// That couldn't be found!  Log the error, but *try* to continue normally.
			if (!$found && $fatal)
			{
				Errors::instance()->log_error(
					sprintf(
						$txt['theme_language_error'],
						$template_name . '.' . $lang,
						'template'
					)
				);
				break;
			}
		}

		if ($fix_arrays)
		{
			fix_calendar_text();
		}

		// Return the language actually loaded.
		return $lang;
	}

	/**
	 * This loads the bare minimum data.
	 *
	 * - Needed by scheduled tasks,
	 * - Needed by any other code that needs language files before the forum (the theme)
	 * is loaded.
	 */
	public function loadEssentialThemeData()
	{
		global $settings, $modSettings, $mbname, $context;

		if (function_exists('database') === false)
		{
			throw new \Exception('');
		}

		$db = database();

		// Get all the default theme variables.
		$db->fetchQuery(
			'
			SELECT id_theme, variable, value
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

		// Check we have some directories setup.
		if (!$this->hasDirectories())
		{
			$this->reloadDirectories($settings);
		}

		// Assume we want this.
		$context['forum_name'] = $mbname;
		$context['forum_name_html_safe'] = $context['forum_name'];

		$this->loadLanguageFiles(['index', 'Addons']);
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
	public function loadLanguageFile(
		$template_name,
		$lang = '',
		$fatal = true,
		$force_reload = false
	) {
		return $this->loadLanguageFiles(
			explode('+', $template_name),
			$lang,
			$fatal,
			$force_reload
		);
	}

	/**
	 * Load the template/language file using eval or require? (with eval we can show an
	 * error message!)
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
		/*
		 * I know this looks weird but this is used to include $txt files.
		 * If the parent doesn't declare them global, the scope will be
		 * local to this function. IOW, don't remove this line!
		 */
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

		try
		{
			if ($once && $file_found)
			{
				require_once($filename);
			}
			elseif ($file_found)
			{
				require($filename);
			}
		} catch (Error $e)
		{
			$this->templateNotFound($e);
		}
	}

	/**
	 * Displays an error when a template is not found or has syntax errors preventing its
	 * loading
	 *
	 * @param Error $e
	 */
	protected function templateNotFound(Error $e)
	{
		global $context, $txt, $scripturl, $boardurl;

		obStart();

		// Don't cache error pages!!
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		if (!isset($txt['template_parse_error']))
		{
			$txt['template_parse_error'] = 'Template Parse Error!';
			$txt['template_parse_error_message'] =
				'It seems something has gone sour on the forum with the template system.  This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please contact the administrator.<br /><br />You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			$txt['template_parse_error_details'] =
				'There was a problem loading the <span style="font-family: monospace;"><strong>%1$s</strong></span> template or language file.  Please check the syntax and try again - remember, single quotes (<span style="font-family: monospace;">\'</span>) often have to be escaped with a slash (<span style="font-family: monospace;">\\</span>).  To see more specific error information from PHP, try <a href="%2$s%1$s" class="extern">accessing the file directly</a>.<br /><br />You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="%3$s">use the default theme</a>.';
			$txt['template_parse_undefined'] =
				'An undefined error occurred during the parsing of this template';
		}

		// First, let's get the doctype and language information out of the way.
		echo '<!DOCTYPE html>
<html ', !empty($context['right_to_left']) ? 'dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<style>
			body {
				color: #222;
				background-color: #FAFAFA;
				font-family: Verdana, arial, helvetica, serif;
				font-size: small;
			}
			a {color: #49643D;}
			.curline {background: #ffe; display: inline-block;}
			.lineno {color:#222; -webkit-user-select: none;-moz-user-select: none; -ms-user-select: none;user-select: none;}
		</style>';

		if (!allowedTo('admin_forum'))
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
			$error = $e->getMessage();

			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', sprintf(
				$txt['template_parse_error_details'],
				strtr(
					$e->getFile(),
					[
						BOARDDIR => '',
						strtr(BOARDDIR, '\\', '/') => '',
					]
				),
				$boardurl,
				$scripturl . '?theme=1'
			);

			echo '
		<hr />

		<div style="margin: 0 20px;"><span style="font-family: monospace;">', strtr(
				strtr(
					$error,
					[
						'<strong>' . BOARDDIR => '<strong>...',
						'<strong>' . strtr(BOARDDIR, '\\', '/') => '<strong>...',
					]
				),
				'\\',
				'/'
			), '</span></div>';

			$this->printLines($e);

			echo '
	</body>
</html>';
		}

		die;
	}

	/**
	 * Highlights PHP syntax.
	 *
	 * @param string $file Name of file to highlight.
	 * @param int $min Minimum line numer to return.
	 * @param int $max Maximum line numer to return.
	 *
	 * @used-by printLines() Prints syntax for template files with errors.
	 * @uses    highlight_file() Highlights syntax.
	 *
	 * @return Generator Highlighted lines ranging from $min to $max.
	 */
	public function getHighlightedLinesFromFile(
		string $file,
		int $min,
		int $max
	): Generator
	{
		foreach (preg_split(
			         '~\<br( /)?\>~',
			         highlight_file($file, true)
		         ) as $line => $content)
		{
			if ($line >= $min && $line <= $max)
			{
				yield $line + 1 => $content;
			}
		}
	}

	/**
	 * Print lines from the file with the error.
	 *
	 * @uses getHighlightedLinesFromFile() Highlights syntax.
	 *
	 * @param Error $e
	 */
	private function printLines(Error $e): void
	{
		if (allowedTo('admin_forum'))
		{
			$data = iterator_to_array(
				$this->getHighlightedLinesFromFile(
					$e->getFile(),
					max($e->getLine() - 9, 1),
					min($e->getLine() + 4, count(file($e->getFile())) + 1)
				)
			);

			// Mark the offending line.
			$data[$e->getLine()] = sprintf(
				'<div class="curline">%s</div>',
				$data[$e->getLine()]
			);

			echo '
		<div style="margin: 2ex 20px; width: 96%; overflow: auto;"><pre style="margin: 0;">';

			// Show the relevant lines...
			foreach ($data as $line => $content)
			{
				printf(
					'<span class="lineno">%d:</span> ',
					$line
				);

				echo $content, "\n";
			}

			echo '</pre></div>';
		}
	}

	/**
	 * Load a sub-template.
	 *
	 * What it does:
	 * - loads the sub template specified by sub_template_name, which must be in an
	 * already-loaded template.
	 * - if ?debug is in the query string, shows administrators a marker after every sub
	 * template for debugging purposes.
	 *
	 * @todo get rid of reading $_REQUEST directly
	 *
	 * @param string $sub_template_name
	 * @param bool|string $fatal = false, $fatal = true is for templates that
	 *                           shouldn't get a 'pretty' error screen 'ignore' to skip
	 *
	 * @throws \ElkArte\Exceptions\Exception theme_template_error
	 */
	public function loadSubTemplate($sub_template_name, $fatal = false)
	{
		global $txt, $db_show_debug;

		if (!empty($sub_template_name))
		{
			if ($db_show_debug === true)
			{
				\ElkArte\Debug::instance()->add('sub_templates', $sub_template_name);
			}

			// Figure out what the template function is named.
			$theme_function = 'template_' . $sub_template_name;

			if (function_exists($theme_function))
			{
				try
				{
					$theme_function();
				} catch (Error $e)
				{
					$this->templateNotFound($e);
				}
			}
			elseif ($fatal === false)
			{
				throw new Exception(
					'theme_template_error',
					'template',
					[(string) $sub_template_name]
				);
			}
			elseif ($fatal !== 'ignore')
			{
				throw new BadFunctionCallException(
					Errors::instance()->log_error(
						sprintf(
							isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load the %s sub template!',
							(string) $sub_template_name
						),
						'template'
					)
				);
			}
		}
	}

	/**
	 * Reloads the directory stack/queue to ensure they are searched in the proper order
	 *
	 * @param array $settings
	 */
	public function reloadDirectories(array $settings)
	{
		$this->dirs = [];

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
}
