<?php

/**
 * This file has functions dealing with loading and precessing template files.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Themes;

use BadFunctionCallException;
use ElkArte\Debug;
use ElkArte\Errors\Errors;
use ElkArte\Exceptions\Exception;
use ElkArte\FileFunctions;
use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;
use Error;
use Generator;
use JetBrains\PhpStorm\NoReturn;


/**
 * Class Templates
 *
 * This class loads and processes template files and sheets.
 */
class Templates
{
	/** @var Directories Template directory's that we will be searching for the sheets */
	public $dirs;

	/** @var array Template sheets that have not loaded */
	protected $delayed = [];

	/** @var bool Tracks if the default index.css has been loaded */
	protected $default_loaded = false;

	/**
	 * Templates constructor.
	 */
	public function __construct(Directories $dirs)
	{
		$this->dirs = $dirs;

		// We want to be able to figure out any errors...
		error_clear_last();
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
	 * @param string|false $template_name
	 * @param string[]|string $style_sheets any style sheets to load with the template
	 * @param bool $fatal = true if fatal is true, dies with an error message if the
	 *     template cannot be found
	 *
	 * @uses $this->requireTemplate() to actually load the file.
	 *
	 */
	public function load($template_name, $style_sheets = [], $fatal = true): ?bool
	{
		// If we don't know yet the default theme directory, let's wait a bit.
		if ($this->dirs->hasDirectories() === false)
		{
			$this->delayed[] = [
				$template_name,
				$style_sheets,
				$fatal,
			];

			return null;
		}

		// If instead we know the default theme directory, and we have delayed something, it's time to process
		if (!empty($this->delayed))
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
	 * @param string|false $template_name
	 * @param string[]|string $style_sheets any style sheets to load with the template
	 * @param bool $fatal = true if fatal is true, dies with an error message if the
	 *     template cannot be found
	 *
	 * @uses $this->dirs->fileInclude() to include the file.
	 *
	 */
	protected function requireTemplate($template_name, $style_sheets, $fatal): bool
	{
		global $context, $settings, $txt, $db_show_debug;

		if (!is_array($style_sheets))
		{
			$style_sheets = [$style_sheets];
		}

		if (!$this->default_loaded)
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
				if ($sheet !== 'admin')
				{
					continue;
				}

				if (empty($context['theme_variant']))
				{
					continue;
				}

				$sheets[] = $context['theme_variant'] . '/admin' . $context['theme_variant'] . '.css';
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
		$file_functions = FileFunctions::instance();
		foreach ($this->dirs->getDirectories() as $template_dir)
		{
			if ($file_functions->fileExists($template_dir . '/' . $template_name . '.template.php'))
			{
				$loaded = true;
				try
				{
					$this->dirs->fileInclude(
						$template_dir . '/' . $template_name . '.template.php',
						true
					);
				}
				catch (Error $e)
				{
					$this->templateNotFound($e);
				}

				break;
			}
		}

		if ($loaded)
		{
			if ($db_show_debug === true)
			{
				Debug::instance()->add(
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
		elseif (!$file_functions->fileExists($settings['default_theme_dir'])
			&& $file_functions->fileExists(BOARDDIR . '/themes/default'))
		{
			$settings['default_theme_dir'] = BOARDDIR . '/themes/default';
			$this->dirs->addDirectory($settings['default_theme_dir']);

			if (!empty($context['user']['is_admin']) && !isset($_GET['th']))
			{
				Txt::load('Errors');

				if (!isset($context['security_controls_files']['title']))
				{
					$context['security_controls_files']['title'] = $txt['generic_warning'];
				}

				$context['security_controls_files']['errors']['theme_dir'] =
					'<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'theme', 'sa' => 'list', 'th' => 1, '{session_data}']) . '">' . $txt['theme_dir_wrong'] . '</a>';
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
					$txt['theme_template_error'] ?? 'Unable to load themes/default/%s.template.php!',
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
	 * Displays an error when a template is not found or has syntax errors preventing its
	 * loading
	 *
	 * @param Error $e
	 */
	#[NoReturn] protected function templateNotFound(Error $e)
	{
		global $context, $txt, $scripturl, $boardurl;

		obStart();

		// Don't cache error pages!!
		Headers::instance()
			->removeHeader('all')
			->header('Expires', 'Mon, 26 Jul 1997 05:00:00 GMT')
			->header('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT')
			->header('Cache-Control', 'no-cache')
			->contentType('text/html', 'UTF-8')
			->sendHeaders();

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
<html ', empty($context['right_to_left']) ? '' : 'dir="rtl"', '>
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

		<div style="margin: 0 20px;"><span style="font-family: monospace;">', str_replace('\\', '/', strtr(
				$error,
				[
					'<strong>' . BOARDDIR => '<strong>...',
					'<strong>' . strtr(BOARDDIR, '\\', '/') => '<strong>...',
				]
			)), '</span></div>';

			$this->printLines($e);

			echo '
	</body>
</html>';
		}

		die;
	}

	/**
	 * Print lines from the file with the error.
	 *
	 * @param Error $e
	 * @uses getHighlightedLinesFromFile() Highlights syntax.
	 *
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
	 * Highlights PHP syntax.
	 *
	 * @param string $file Name of file to highlight.
	 * @param int $min Minimum line number to return.
	 * @param int $max Maximum line number to return.
	 *
	 * @used-by printLines() Prints syntax for template files with errors.
	 * @return Generator Highlighted lines ranging from $min to $max.
	 * @uses    highlight_file() Highlights syntax.
	 *
	 */
	public function getHighlightedLinesFromFile(string $file, int $min, int $max): Generator
	{
		foreach (preg_split('~<br( /)?>~', highlight_file($file, true)) as $line => $content)
		{
			if ($line < $min)
			{
				continue;
			}

			if ($line > $max)
			{
				continue;
			}

			yield $line + 1 => $content;
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
	 * @param string $sub_template_name
	 * @param bool|string $fatal = false, $fatal = true is for templates that
	 *                           shouldn't get a 'pretty' error screen 'ignore' to skip
	 *
	 * @throws Exception theme_template_error
	 *
	 */
	public function loadSubTemplate($sub_template_name, $fatal = false)
	{
		global $txt, $db_show_debug;

		if (!empty($sub_template_name))
		{
			if ($db_show_debug === true)
			{
				Debug::instance()->add('sub_templates', $sub_template_name);
			}

			// Figure out what the template function is named.
			$theme_function = 'template_' . $sub_template_name;

			if (function_exists($theme_function))
			{
				try
				{
					$theme_function();
				}
				catch (Error $e)
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
							$txt['theme_template_error'] ?? 'Unable to load the %s sub template!',
							(string) $sub_template_name
						),
						'template'
					)
				);
			}
		}
	}

	/**
	 * @return Directories
	 */
	public function getDirectory()
	{
		return $this->dirs;
	}
}
