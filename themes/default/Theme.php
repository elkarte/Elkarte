<?php

/**
 * The default theme
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:      BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

namespace ElkArte\Themes\DefaultTheme;

use ElkArte\Themes\Theme as BaseTheme;

/**
 * Class Theme
 *
 * - Extends the abstract theme class
 *
 * @package Themes\DefaultTheme
 */
class Theme extends BaseTheme
{
	/**
	 * Initialize the template... mainly little settings.
	 *
	 * @return array Theme settings
	 */
	function getSettings()
	{
		return array(
			/*
			 * Specifies whether images from default theme shall be
			 * fetched instead of the current theme when using
			 * templates from the default theme.
			 *
			 * - if this is 'always', images from the default theme will be used.
			 * - if this is 'defaults', images from the default theme will only be used with default templates.
			 * - if this is 'never' or isn't set at all, images from the default theme will not be used.
			 *
			 * This doesn't apply when custom templatees are being
			 * used; nor does it apply to the dafult theme.
			 */
			'use_default_images' => 'never',

			/*
			 * The version this template/theme is for. This should
			 * be the version of the forum it was created for.
			 */
			'theme_version' => '1.0',

			/*
			 * Whether this theme requires the optional theme strings
			 * file to be loaded. (ThemeStrings.[language].php)
			 */
			'require_theme_strings' => false,

			/*
			 * Specify the color variants. Each variant has its own
			 * directory, where additional CSS files may be loaded.
			 *
			 * Example:
			 * - index_light.css is loaded when index.css is needed.
			 */
			'theme_variants' => array(
				'light',
				'besocial',
			),

			/*
			 * Provides avatars for use on various indexes.
			 *
			 * Possible values:
			 * - 0 or not set, no avatars are available
			 * - 1 avatar of the poster of the last message
			 * - 2 avatar of the poster of the first message
			 * - 3 both avatars
			 *
			 * Since grabbing the avatar requires some work, it is
			 * better to set the variable to a sensible value
			 * depending on the needs of the theme.
			 */
			'avatars_on_indexes' => 1,

			/*
			 * This is used in the main menus to create a number next
			 * to the title of the menu to indicate the number of
			 * unread messages, moderation reports, etc. You can
			 * style each menu level indicator as desired.
			 */
			'menu_numeric_notice' => array(
				// Top level menu entries
				0 => ' <span class="pm_indicator">%1$s</span>',
				// First dropdown
				1 => ' <span>[<strong>%1$s</strong>]</span>',
				// Second level dropdown
				2 => ' <span>[<strong>%1$s</strong>]</span>',
			),

			// This slightly more complex array, instead, will deal with page indexes as frequently requested by Ant :P
			// Oh no you don't. :D This slightly less complex array now has cleaner markup. :P
			// @todo - God it's still ugly though. Can't we just have links where we need them, without all those spans?
			// How do we get anchors only, where they will work? Spans and strong only where necessary?
			'page_index_template' => array(
				'base_link' => '<li class="linavPages"><a class="navPages" href="{base_link}" role="menuitem">%2$s</a></li>',
				'previous_page' => '<span class="previous_page" role="menuitem">{prev_txt}</span>',
				'current_page' => '<li class="linavPages"><strong class="current_page" role="menuitem">%1$s</strong></li>',
				'next_page' => '<span class="next_page" role="menuitem">{next_txt}</span>',
				'expand_pages' => '<li class="linavPages expand_pages" role="menuitem" {custom}> <a href="#">...</a> </li>',
				'all' => '<span class="linavPages all_pages" role="menuitem">{all_txt}</span>',
			),

			// @todo find a better place if we are going to create a notifications template
			'mentions' => array(
				'mentioner_template' => '<a href="{mem_url}" class="mentionavatar">{avatar_img}{mem_name}</a>',
			)
		);
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
	 * The header template
	 */
	public function template_header()
	{
		global $context, $settings;

		doSecurityChecks();

		$this->setupThemeContext();

		// Print stuff to prevent caching of pages (except on attachment errors, etc.)
		if (empty($context['no_last_modified']))
		{
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

			if ($this->headerSent('Content-Type') === false && (!isset($_REQUEST['xml']) && !isset($_REQUEST['api'])))
			{
				header('Content-Type: text/html; charset=UTF-8');
			}
		}

		if ($this->headerSent('Content-Type') === false)
		{
			// Probably temporary ($_REQUEST['xml'] should be replaced by $_REQUEST['api'])
			if (isset($_REQUEST['api']) && $_REQUEST['api'] === 'json')
			{
				header('Content-Type: application/json; charset=UTF-8');
			}
			elseif (isset($_REQUEST['xml']) || isset($_REQUEST['api']))
			{
				header('Content-Type: text/xml; charset=UTF-8');
			}
			else
			{
				header('Content-Type: text/html; charset=UTF-8');
			}
		}

		foreach ($this->getLayers()->prepareContext() as $layer)
		{
			loadSubTemplate($layer . '_above', 'ignore');
		}

		if (isset($settings['use_default_images']) && $settings['use_default_images'] === 'defaults' && isset($settings['default_template']))
		{
			$settings['theme_url'] = $settings['default_theme_url'];
			$settings['images_url'] = $settings['default_images_url'];
			$settings['theme_dir'] = $settings['default_theme_dir'];
		}
	}

	/**
	 * Checks if a header of a given type has already been sent
	 *
	 * @param string $type
	 *
	 * @return bool
	 */
	protected function headerSent($type)
	{
		$sent = headers_list();
		foreach ($sent as $header)
		{
			if (substr($header, 0, strlen($type)) === $type)
			{
				return true;
			}
		}

		return false;
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
	 * The template footer
	 */
	public function template_footer()
	{
		global $context, $settings, $modSettings, $time_start;

		$db = database();

		// Show the load time?  (only makes sense for the footer.)
		$context['show_load_time'] = !empty($modSettings['timeLoadPageEnable']);
		$context['load_time'] = round(microtime(true) - $time_start, 3);
		$context['load_queries'] = $db->num_queries();

		if (isset($settings['use_default_images']) && $settings['use_default_images'] === 'defaults' && isset($settings['default_template']))
		{
			$settings['theme_url'] = $settings['actual_theme_url'];
			$settings['images_url'] = $settings['actual_images_url'];
			$settings['theme_dir'] = $settings['actual_theme_dir'];
		}

		foreach ($this->getLayers()->reverseLayers() as $layer)
		{
			$this->getTemplates()->loadSubTemplate($layer . '_below', 'ignore');
		}
	}

	/**
	 * Loads the required jQuery files for the system
	 *
	 * - Determines the correct script tags to add based on CDN/Local/Auto
	 */
	protected function templateJquery()
	{
		global $modSettings, $settings;

		// Using a specified version of jquery or what was shipped 3.1.1  / 1.12.1
		$jquery_version = (!empty($modSettings['jquery_default']) && !empty($modSettings['jquery_version'])) ? $modSettings['jquery_version'] : '3.1.1';
		$jqueryui_version = (!empty($modSettings['jqueryui_default']) && !empty($modSettings['jqueryui_version'])) ? $modSettings['jqueryui_version'] : '1.12.1';

		switch ($modSettings['jquery_source'])
		{
			// Only getting the files from the CDN?
			case 'cdn':
				echo '
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/' . $jquery_version . '/jquery.min.js" id="jquery"></script>',
				(!empty($modSettings['jquery_include_ui']) ? '
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/' . $jqueryui_version . '/jquery-ui.min.js" id="jqueryui"></script>' : '');
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
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/' . $jquery_version . '/jquery.min.js" id="jquery"></script>',
				(!empty($modSettings['jquery_include_ui']) ? '
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/' . $jqueryui_version . '/jquery-ui.min.js" id="jqueryui"></script>' : '');
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
	 * Loads the JS files that have been requested
	 *
	 * - Will combine / minify the files it the option is set.
	 * - Handles both above and below (deferred) files
	 *
	 * @param bool $do_deferred
	 */
	protected function templateJavascriptFiles($do_deferred)
	{
		global $modSettings, $settings;

		// Combine and minify javascript source files to save bandwidth and requests
		if (!empty($modSettings['minify_css_js']))
		{
			$combiner = new \Site_Combiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url']);
			$combine_name = $combiner->site_js_combine($this->js_files, $do_deferred);

			call_integration_hook('post_javascript_combine', array(&$combine_name, $combiner));

			if (!empty($combine_name))
			{
				echo '
	<script src="', $combine_name, '" id="jscombined', $do_deferred ? 'bottom' : 'top', '"></script>';
			}
			// While we have Javascript files to place in the template
			foreach ($combiner->getSpares() as $id => $js_file)
			{
				if ((!$do_deferred && empty($js_file['options']['defer'])) || ($do_deferred && !empty($js_file['options']['defer'])))
				{
					echo '
	<script src="', $js_file['filename'], '" id="', $id, '"', !empty($js_file['options']['async']) ? ' async="async"' : '', '></script>';
				}
			}
		}
		// Just give them the full load then
		else
		{
			// While we have Javascript files to place in the template
			foreach ($this->js_files as $id => $js_file)
			{
				if ((!$do_deferred && empty($js_file['options']['defer'])) || ($do_deferred && !empty($js_file['options']['defer'])))
				{
					echo '
	<script src="', $js_file['filename'], '" id="', $id, '"', !empty($js_file['options']['async']) ? ' async="async"' : '', '></script>';
				}
			}
		}
	}

	/**
	 * Deletes the hives (aggregated CSS and JS files) previously created.
	 *
	 * @param string $type = 'all' Filters the types of hives (valid values:
	 *                               * 'all'
	 *                               * 'css'
	 *                               * 'js'
	 */
	public function cleanHives($type = 'all')
	{
		global $settings;

		$combiner = new \Site_Combiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url']);
		$result = true;

		if ($type === 'all' || $type === 'css')
		{
			$result &= $combiner->removeCssHives();
		}

		if ($type === 'all' || $type === 'js')
		{
			$result &= $combiner->removeJsHives();
		}

		return $result;
	}

	/**
	 * Output the Javascript files
	 *
	 * What it does:
	 *
	 * - Tabbing in this function is to make the HTML source look proper
	 * - Outputs jQuery/jQueryUI from the proper source (local/CDN)
	 * - If deferred is set function will output all JS (source & inline) set to load at page end
	 * - If the admin option to combine files is set, will use Combiner.class
	 *
	 * @param bool $do_deferred = false
	 */
	public function template_javascript($do_deferred = false)
	{
		global $modSettings;

		// First up, load jQuery and jQuery UI
		if (isset($modSettings['jquery_source']) && !$do_deferred)
		{
			$this->templateJquery();
		}

		// Use this hook to work with Javascript files and vars pre output
		call_integration_hook('pre_javascript_output');

		// Load in the JS files
		if (!empty($this->js_files))
		{
			$this->templateJavascriptFiles($do_deferred);
		}

		// Build the declared Javascript variables script
		$js_vars = array();
		if (!empty($this->js_vars) && !$do_deferred)
		{
			foreach ($this->js_vars as $var => $value)
				$js_vars[] = $var . ' = ' . $value;

			// Newlines and tabs are here to make it look nice in the page source view, stripped if minimized though
			$this->js_inline['standard'][] = 'var ' . implode(",\n\t\t\t", $js_vars) . ';';
		}

		// Inline JavaScript - Actually useful some times!
		if (!empty($this->js_inline))
		{
			// Deferred output waits until we are deferring !
			if (!empty($this->js_inline['defer']) && $do_deferred)
			{
				// Combine them all in to one output
				$this->js_inline['defer'] = array_map('trim', $this->js_inline['defer']);
				$inline_defered_code = implode("\n\t\t", $this->js_inline['defer']);

				// Output the deferred script
				echo '
	<script>
		', $inline_defered_code, '
	</script>';
			}

			// Standard output, and our javascript vars, get output when we are not on a defered call
			if (!empty($this->js_inline['standard']) && !$do_deferred)
			{
				$this->js_inline['standard'] = array_map('trim', $this->js_inline['standard']);

				// And output the js vars and standard scripts to the page
				echo '
	<script>
		', implode("\n\t\t", $this->js_inline['standard']), '
	</script>';
			}
		}
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

		// Combine and minify the CSS files to save bandwidth and requests?
		if (!empty($this->css_files))
		{
			if (!empty($modSettings['minify_css_js']))
			{
				$combiner = new \Site_Combiner($settings['default_theme_cache_dir'], $settings['default_theme_cache_url']);
				$combine_name = $combiner->site_css_combine($this->css_files);

				call_integration_hook('post_css_combine', array(&$combine_name, $combiner));

				if (!empty($combine_name))
				{
					echo '
	<link rel="stylesheet" href="', $combine_name, '" id="csscombined" />';
				}

				foreach ($combiner->getSpares() as $id => $file)
					echo '
	<link rel="stylesheet" href="', $file['filename'], '" id="', $id, '" />';
			}
			else
			{
				foreach ($this->css_files as $id => $file)
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

		if (!empty($style_tag))
		{
			echo '
	<style>' . $style_tag . '
	</style>';
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
	 * If the option to pretty output code is on, this loads the JS and CSS
	 */
	public function addCodePrettify()
	{
		global $modSettings;

		if (!empty($modSettings['enableCodePrettify']))
		{
			loadCSSFile('prettify.css');
			loadJavascriptFile('prettify.min.js', array('defer' => true));

			addInlineJavascript('
			$(function() {
				prettyPrint();
			});', true);
		}
	}

	/**
	 * If video embedding is enabled, this loads the needed JS and vars
	 */
	public function autoEmbedVideo()
	{
		global $txt, $modSettings;

		if (!empty($modSettings['enableVideoEmbeding']))
		{
			addInlineJavascript('
			var oEmbedtext = ({
				preview_image : ' . JavaScriptEscape($txt['preview_image']) . ',
				ctp_video : ' . JavaScriptEscape($txt['ctp_video']) . ',
				hide_video : ' . JavaScriptEscape($txt['hide_video']) . ',
				youtube : ' . JavaScriptEscape($txt['youtube']) . ',
				vimeo : ' . JavaScriptEscape($txt['vimeo']) . ',
				dailymotion : ' . JavaScriptEscape($txt['dailymotion']) . '
			});', true);

			loadJavascriptFile('elk_jquery_embed.js', array('defer' => true));
		}
	}

	/**
	 * Ensures we kick the mail queue from time to time so that it gets
	 * checked as often as possible.
	 */
	public function doScheduledSendMail()
	{
		global $modSettings;

		if (isBrowser('possibly_robot'))
		{
			// @todo Maybe move this somewhere better?!
			$controller = new \ScheduledTasks_Controller();

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

			addInlineJavascript('
		function elkAutoTask()
		{
			var tempImage = new Image();
			tempImage.src = elk_scripturl + "?scheduled=' . $type . ';ts=' . $ts . '";
		}
		window.setTimeout("elkAutoTask();", 1);', true);
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
			addInlineJavascript('
			var oRttime = ({
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
			updateRelativeTime();', true);

			$context['using_relative_time'] = true;
		}
	}

	/**
	 * Sets up the basic theme context stuff.
	 *
	 * @param bool $forceload = false
	 */
	public function setupThemeContext($forceload = false)
	{
		global $modSettings, $user_info, $scripturl, $context, $settings, $options, $txt;

		static $loaded = false;

		// Under SSI this function can be called more then once.  That can cause some problems.
		// So only run the function once unless we are forced to run it again.
		if ($loaded && !$forceload)
		{
			return;
		}

		$loaded = true;

		$context['current_time'] = standardTime(time(), false);
		$context['current_action'] = isset($_GET['action']) ? $_GET['action'] : '';
		$context['show_quick_login'] = !empty($modSettings['enableVBStyleLogin']) && $user_info['is_guest'];
		$context['robot_no_index'] = in_array($context['current_action'], $this->no_index_actions);

		$bbc_parser = \BBC\ParserWrapper::instance();

		// Get some news...
		$context['news_lines'] = array_filter(explode("\n", str_replace("\r", '', trim(addslashes($modSettings['news'])))));
		for ($i = 0, $n = count($context['news_lines']); $i < $n; $i++)
		{
			if (trim($context['news_lines'][$i]) === '')
			{
				continue;
			}

			// Clean it up for presentation ;).
			$context['news_lines'][$i] = $bbc_parser->parseNews(stripslashes(trim($context['news_lines'][$i])));
		}

		// If we have some, setup for display
		if (!empty($context['news_lines']))
		{
			$context['random_news_line'] = $context['news_lines'][mt_rand(0, count($context['news_lines']) - 1)];
			$context['upper_content_callbacks'][] = 'news_fader';
		}

		if (!$user_info['is_guest'])
		{
			$context['user']['messages'] = &$user_info['messages'];
			$context['user']['unread_messages'] = &$user_info['unread_messages'];
			$context['user']['mentions'] = &$user_info['mentions'];

			// Personal message popup...
			if ($user_info['unread_messages'] > (isset($_SESSION['unread_messages']) ? $_SESSION['unread_messages'] : 0))
			{
				$context['user']['popup_messages'] = true;
			}
			else
			{
				$context['user']['popup_messages'] = false;
			}

			$_SESSION['unread_messages'] = $user_info['unread_messages'];

			$context['user']['avatar'] = array(
				'href' => !empty($user_info['avatar']['href']) ? $user_info['avatar']['href'] : '',
				'image' => !empty($user_info['avatar']['image']) ? $user_info['avatar']['image'] : '',
			);

			// Figure out how long they've been logged in.
			$context['user']['total_time_logged_in'] = array(
				'days' => floor($user_info['total_time_logged_in'] / 86400),
				'hours' => floor(($user_info['total_time_logged_in'] % 86400) / 3600),
				'minutes' => floor(($user_info['total_time_logged_in'] % 3600) / 60)
			);
		}
		else
		{
			$context['user']['messages'] = 0;
			$context['user']['unread_messages'] = 0;
			$context['user']['mentions'] = 0;
			$context['user']['avatar'] = array();
			$context['user']['total_time_logged_in'] = array('days' => 0, 'hours' => 0, 'minutes' => 0);
			$context['user']['popup_messages'] = false;

			if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1)
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

		// Setup the main menu items.
		$this->setupMenuContext();

		if (empty($settings['theme_version']))
		{
			$context['show_vBlogin'] = $context['show_quick_login'];
		}

		// This is here because old index templates might still use it.
		$context['show_news'] = !empty($settings['enable_news']);

		$context['additional_dropdown_search'] = prepareSearchEngines();

		// This is done to allow theme authors to customize it as they want.
		$context['show_pm_popup'] = $context['user']['popup_messages'] && !empty($options['popup_messages']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'pm');

		// Add the PM popup here instead. Theme authors can still override it simply by editing/removing the 'fPmPopup' in the array.
		if ($context['show_pm_popup'])
		{
			addInlineJavascript('
			$(function() {
				new smc_Popup({
					heading: ' . JavaScriptEscape($txt['show_personal_messages_heading']) . ',
					content: ' . JavaScriptEscape(sprintf($txt['show_personal_messages'], $context['user']['unread_messages'], $scripturl . '?action=pm')) . ',
					icon: \'i-envelope\'
				});
			});', true);
		}

		// This looks weird, but it's because BoardIndex.controller.php references the variable.
		$context['common_stats']['latest_member'] = array(
			'id' => $modSettings['latestMember'],
			'name' => $modSettings['latestRealName'],
			'href' => $scripturl . '?action=profile;u=' . $modSettings['latestMember'],
			'link' => '<a href="' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . '">' . $modSettings['latestRealName'] . '</a>',
		);

		$context['common_stats'] = array(
			'total_posts' => comma_format($modSettings['totalMessages']),
			'total_topics' => comma_format($modSettings['totalTopics']),
			'total_members' => comma_format($modSettings['totalMembers']),
			'latest_member' => $context['common_stats']['latest_member'],
		);

		$context['common_stats']['boardindex_total_posts'] = sprintf($txt['boardindex_total_posts'], $context['common_stats']['total_posts'], $context['common_stats']['total_topics'], $context['common_stats']['total_members']);

		if (empty($settings['theme_version']))
		{
			addJavascriptVar(array('elk_scripturl' => '\'' . $scripturl . '\''));
		}
		addJavascriptVar(array('elk_forum_action' => '\'' . substr($modSettings['default_forum_action'], 1, -1) . '\''));

		if (!isset($context['page_title']))
		{
			$context['page_title'] = '';
		}

		// Set some specific vars.
		$context['page_title_html_safe'] = \Util::htmlspecialchars(un_htmlspecialchars($context['page_title'])) . (!empty($context['current_page']) ? ' - ' . $txt['page'] . ' ' . ($context['current_page'] + 1) : '');

		// 1.0 backward compatibility: if you already put the icon in the theme dir
		// use that one, otherwise the default
		// @deprecated since 1.1
		if (file_exists($scripturl . '/mobile.png'))
		{
			$context['favicon'] = $scripturl . '/mobile.png';
		}
		else
		{
			$context['favicon'] = $settings['images_url'] . '/mobile.png';
		}

		// Since it's nice to have avatars all of the same size, and in some cases the size detection may fail,
		// let's add the css in any case
		if (!isset($context['html_headers']))
			$context['html_headers'] = '';

		if (!empty($modSettings['avatar_max_width']) || !empty($modSettings['avatar_max_height']))
		{
			$this->addCSSRules('
		.avatarresize {' . (!empty($modSettings['avatar_max_width']) ? '
			max-width:' . $modSettings['avatar_max_width'] . 'px;' : '') . (!empty($modSettings['avatar_max_height']) ? '
			max-height:' . $modSettings['avatar_max_height'] . 'px;' : '') . '
		}');
		}

		// Save some database hits, if a width for multiple wrappers is set in admin.
		if (!empty($settings['forum_width']))
		{
			$this->addCSSRules('
		.wrapper {width: ' . $settings['forum_width'] . ';}');
		}
	}

	/**
	 * Adds required support CSS files.
	 */
	public function loadSupportCSS()
	{
		global $modSettings, $settings;

		// Load the SVG support file with fallback to default theme
		loadCSSFile('icons_svg.css');

		// Load a base theme custom CSS file?
		if (file_exists($settings['theme_dir'] . '/css/custom.css'))
		{
			loadCSSFile('custom.css', array('fallback' => false));
		}

		// Load font Awesome fonts, @deprecated in 1.1 and will be removed in 2.0
		if (!empty($settings['require_font-awesome']) || !empty($modSettings['require_font-awesome']))
		{
			loadCSSFile('font-awesome.min.css');
		}
	}

	/**
	 * Sets up all of the top menu buttons
	 *
	 * What it does:
	 *
	 * - Defines every master item in the menu, as well as any sub-items
	 * - Ensures the chosen action is set so the menu is highlighted
	 * - Saves them in the cache if it is available and on
	 * - Places the results in $context
	 */
	public function setupMenuContext()
	{
		global $context, $modSettings, $user_info, $settings;

		// Set up the menu privileges.
		$context['allow_search'] = !empty($modSettings['allow_guestAccess']) ? allowedTo('search_posts') : (!$user_info['is_guest'] && allowedTo('search_posts'));
		$context['allow_admin'] = allowedTo(array('admin_forum', 'manage_boards', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_attachments', 'manage_smileys'));
		$context['allow_edit_profile'] = !$user_info['is_guest'] && allowedTo(array('profile_view_own', 'profile_view_any', 'profile_identity_own', 'profile_identity_any', 'profile_extra_own', 'profile_extra_any', 'profile_remove_own', 'profile_remove_any', 'moderate_forum', 'manage_membergroups', 'profile_title_own', 'profile_title_any'));
		$context['allow_memberlist'] = allowedTo('view_mlist');
		$context['allow_calendar'] = allowedTo('calendar_view') && !empty($modSettings['cal_enabled']);
		$context['allow_moderation_center'] = $context['user']['can_mod'];
		$context['allow_pm'] = allowedTo('pm_read');

		call_integration_hook('integrate_setup_allow');

		if ($context['allow_search'])
		{
			$context['theme_header_callbacks'] = elk_array_insert($context['theme_header_callbacks'], 'login_bar', array('search_bar'), 'after');
		}

		$cacheTime = $modSettings['lastActive'] * 60;

		// Update the Moderation menu items with action item totals
		if ($context['allow_moderation_center'])
		{
			// Get the numbers for the menu ...
			require_once(SUBSDIR . '/Moderation.subs.php');
			$menu_count = loadModeratorMenuCounts();
		}

		$menu_count['unread_messages'] = $context['user']['unread_messages'];
		$menu_count['mentions'] = $context['user']['mentions'];

		if (!empty($user_info['avatar']['href']))
		{
			$this->addCSSRules('
	.i-account:before {
		content: "";
		background-image: url("' . $user_info['avatar']['href'] . '");
	}');
		}

		// All the buttons we can possible want and then some, try pulling the final list of buttons from cache first.
		if (($menu_buttons = cache_get_data('menu_buttons-' . implode('_', $user_info['groups']) . '-' . $user_info['language'], $cacheTime)) === null || time() - $cacheTime <= $modSettings['settings_updated'])
		{
			// Start things up: this is what we know by default
			require_once(SUBSDIR . '/Menu.subs.php');
			$buttons = loadDefaultMenuButtons();

			// Allow editing menu buttons easily.
			call_integration_hook('integrate_menu_buttons', array(&$buttons, &$menu_count));

			// Now we put the buttons in the context so the theme can use them.
			$menu_buttons = array();
			foreach ($buttons as $act => $button)
			{
				if (!empty($button['show']))
				{
					$button['active_button'] = false;

					// This button needs some action.
					if (isset($button['action_hook']))
					{
						$needs_action_hook = true;
					}

					if (isset($button['counter']) && !empty($menu_count[$button['counter']]))
					{
						$button['alttitle'] = $button['title'] . ' [' . $menu_count[$button['counter']] . ']';
						if (!empty($settings['menu_numeric_notice'][0]))
						{
							$button['title'] .= sprintf($settings['menu_numeric_notice'][0], $menu_count[$button['counter']]);
							$button['indicator'] = true;
						}
					}

					// Go through the sub buttons if there are any.
					if (isset($button['sub_buttons']))
					{
						foreach ($button['sub_buttons'] as $key => $subbutton)
						{
							if (empty($subbutton['show']))
							{
								unset($button['sub_buttons'][$key]);
							}
							elseif (isset($subbutton['counter']) && !empty($menu_count[$subbutton['counter']]))
							{
								$button['sub_buttons'][$key]['alttitle'] = $subbutton['title'] . ' [' . $menu_count[$subbutton['counter']] . ']';
								if (!empty($settings['menu_numeric_notice'][1]))
								{
									$button['sub_buttons'][$key]['title'] .= sprintf($settings['menu_numeric_notice'][1], $menu_count[$subbutton['counter']]);
								}

								// 2nd level sub buttons next...
								if (isset($subbutton['sub_buttons']))
								{
									foreach ($subbutton['sub_buttons'] as $key2 => $subbutton2)
									{
										$button['sub_buttons'][$key]['sub_buttons'][$key2] = $subbutton2;
										if (empty($subbutton2['show']))
										{
											unset($button['sub_buttons'][$key]['sub_buttons'][$key2]);
										}
										elseif (isset($subbutton2['counter']) && !empty($menu_count[$subbutton2['counter']]))
										{
											$button['sub_buttons'][$key]['sub_buttons'][$key2]['alttitle'] = $subbutton2['title'] . ' [' . $menu_count[$subbutton2['counter']] . ']';
											if (!empty($settings['menu_numeric_notice'][2]))
											{
												$button['sub_buttons'][$key]['sub_buttons'][$key2]['title'] .= sprintf($settings['menu_numeric_notice'][2], $menu_count[$subbutton2['counter']]);
											}
											unset($menu_count[$subbutton2['counter']]);
										}
									}
								}
							}
						}
					}

					$menu_buttons[$act] = $button;
				}
			}

			if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			{
				cache_put_data('menu_buttons-' . implode('_', $user_info['groups']) . '-' . $user_info['language'], $menu_buttons, $cacheTime);
			}
		}

		if (!empty($menu_buttons['profile']['sub_buttons']['logout']))
		{
			$menu_buttons['profile']['sub_buttons']['logout']['href'] .= ';' . $context['session_var'] . '=' . $context['session_id'];
		}

		$context['menu_buttons'] = $menu_buttons;

		// Figure out which action we are doing so we can set the active tab.
		// Default to home.
		$current_action = 'home';

		if (isset($context['menu_buttons'][$context['current_action']]))
		{
			$current_action = $context['current_action'];
		}
		elseif ($context['current_action'] === 'profile')
		{
			$current_action = 'pm';
		}
		elseif ($context['current_action'] === 'theme')
		{
			$current_action = isset($_REQUEST['sa']) && $_REQUEST['sa'] === 'pick' ? 'profile' : 'admin';
		}
		elseif ($context['current_action'] === 'login2' || ($user_info['is_guest'] && $context['current_action'] === 'reminder'))
		{
			$current_action = 'login';
		}
		elseif ($context['current_action'] === 'groups' && $context['allow_moderation_center'])
		{
			$current_action = 'moderate';
		}
		elseif ($context['current_action'] === 'moderate' && $context['allow_admin'])
		{
			$current_action = 'admin';
		}

		// Not all actions are simple.
		if (!empty($needs_action_hook))
		{
			call_integration_hook('integrate_current_action', array(&$current_action));
		}

		if (isset($context['menu_buttons'][$current_action]))
		{
			$context['menu_buttons'][$current_action]['active_button'] = true;
		}
	}

	/**
	 * Load the base JS that gives Elkarte a nice rack
	 */
	public function loadThemeJavascript()
	{
		global $settings, $context, $modSettings, $scripturl, $txt, $options;

		// Queue our Javascript
		loadJavascriptFile(array('elk_jquery_plugins.js', 'script.js', 'script_elk.js', 'theme.js'));

		// Default JS variables for use in every theme
		$this->addJavascriptVar(array(
				'elk_theme_url' => JavaScriptEscape($settings['theme_url']),
				'elk_default_theme_url' => JavaScriptEscape($settings['default_theme_url']),
				'elk_images_url' => JavaScriptEscape($settings['images_url']),
				'elk_smiley_url' => JavaScriptEscape($modSettings['smileys_url']),
				'elk_scripturl' => '\'' . $scripturl . '\'',
				'elk_iso_case_folding' => detectServer()->is('iso_case_folding') ? 'true' : 'false',
				'elk_charset' => '"UTF-8"',
				'elk_session_id' => JavaScriptEscape($context['session_id']),
				'elk_session_var' => JavaScriptEscape($context['session_var']),
				'elk_member_id' => $context['user']['id'],
				'ajax_notification_text' => JavaScriptEscape($txt['ajax_in_progress']),
				'ajax_notification_cancel_text' => JavaScriptEscape($txt['modify_cancel']),
				'help_popup_heading_text' => JavaScriptEscape($txt['help_popup']),
				'use_click_menu' => !empty($options['use_click_menu']) ? 'true' : 'false',
				'todayMod' => !empty($modSettings['todayMod']) ? (int) $modSettings['todayMod'] : 0)
		);

		// Auto video embedding enabled, then load the needed JS
		$this->autoEmbedVideo();

		// Prettify code tags? Load the needed JS and CSS.
		$this->addCodePrettify();

		// Relative times for posts?
		$this->relativeTimes();

		// If we think we have mail to send, let's offer up some possibilities... robots get pain (Now with scheduled task support!)
		if ((!empty($modSettings['mail_next_send']) && $modSettings['mail_next_send'] < time() && empty($modSettings['mail_queue_use_cron'])) || empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
		{
			$this->doScheduledSendMail();
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

		$simpleActions = array(
			'quickhelp',
			'printpage',
			'quotefast',
			'spellcheck',
		);

		call_integration_hook('integrate_simple_actions', array(&$simpleActions));

		// Output is fully XML
		if (isset($_REQUEST['xml']))
		{
			theme()->getTemplates()->loadLanguageFile('index+Addons');
			$this->getTemplates()->load('Xml');
			$this->getLayers()->removeAll();
		}
		// These actions don't require the index template at all.
		elseif (!empty($_REQUEST['action']) && in_array($_REQUEST['action'], $simpleActions))
		{
			theme()->getTemplates()->loadLanguageFile('index+Addons');
			$this->getLayers()->removeAll();
		}
		else
		{
			// Custom templates to load, or just default?
			if (isset($settings['theme_templates']))
			{
				$templates = explode(',', $settings['theme_templates']);
			}
			else
			{
				$templates = array('index');
			}

			// Load each template...
			foreach ($templates as $template)
				$this->getTemplates()->load($template);

			// ...and attempt to load their associated language files.
			$required_files = implode('+', array_merge($templates, array('Addons')));
			theme()->getTemplates()->loadLanguageFile($required_files, '', false);

			// Custom template layers?
			if (isset($settings['theme_layers']))
			{
				$layers = explode(',', $settings['theme_layers']);
			}
			else
			{
				$layers = array('html', 'body');
			}

			$template_layers = \ElkArte\Themes\TemplateLayers::getInstance(true);
			foreach ($layers as $layer)
			{
				$template_layers->addBegin($layer);
			}
		}
	}

	/**
	 * If a variant CSS is needed, this loads it
	 */
	public function loadThemeVariant()
	{
		global $context, $settings, $options;

		// Overriding - for previews and that ilk.
		if (!empty($_REQUEST['variant']))
		{
			$_SESSION['id_variant'] = $_REQUEST['variant'];
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
			if (file_exists($settings['theme_dir'] . '/css/' . $context['theme_variant'] . '/icons_svg' . $context['theme_variant'] . '.css'))
			{
				loadCSSFile($context['theme_variant'] .  '/icons_svg' . $context['theme_variant'] . '.css');
			}

			// Load a theme variant custom CSS
			if (!empty($context['theme_variant']) && file_exists($settings['theme_dir'] . '/css/' . $context['theme_variant'] . '/custom' . $context['theme_variant'] . '.css'))
			{
				loadCSSFile($context['theme_variant'] . '/custom' . $context['theme_variant'] . '.css');
			}
		}
	}
}
