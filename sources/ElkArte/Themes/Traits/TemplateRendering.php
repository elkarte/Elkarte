<?php

/**
 * Template rendering functionality for themes
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Themes\Traits;

use ElkArte\Http\Headers;
use ElkArte\Languages\Txt;

/**
 * Trait TemplateRendering
 *
 * Handles the core template lifecycle - header, footer, and rendering functionality
 */
trait TemplateRendering
{
	/**
	 * The header template
	 *
	 * What it does:
	 *  - Performs security checks
	 *  - Sets up the theme context
	 *  - Sets up headers (expiration, content type)
	 *  - Loads template layers
	 *  - Sends headers
	 */
	public function template_header(): void
	{
		doSecurityChecks();

		$this->setupThemeContext();

		$header = Headers::instance();
		$this->headersManager->setupHeadersExpiration($header);
		$this->headersManager->setupHeadersContentType($header, $this->headersManager->getRequestAPI());

		foreach ($this->getLayers()->prepareContext() as $layer)
		{
			$this->getTemplates()->loadSubTemplate($layer . '_above', 'ignore');
		}

		$this->loadDefaultThemeSettings();

		$header->sendHeaders();
	}

	/**
	 * The template footer
	 *
	 * What it does:
	 *  - Sets up load time display if enabled
	 *  - Restores theme settings if using default images
	 *  - Loads template layers in reverse order
	 */
	public function template_footer(): void
	{
		global $context, $settings, $modSettings, $time_start;

		$db = database();

		// Show the load time?  (only makes sense for the footer.)
		$context['show_load_time'] = !empty($modSettings['timeLoadPageEnable']);
		$context['load_time'] = round(microtime(true) - $time_start, 3);
		$context['load_queries'] = $db->num_queries();

		if (isset($settings['use_default_images'], $settings['default_template'])
			&& $settings['use_default_images'] === 'defaults')
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
	 * This is the only template included in the sources.
	 */
	public function template_rawdata(): void
	{
		global $context;

		echo $context['raw_data'];
	}

	/**
	 * Calls on template_show_error from index.template.php to show warnings
	 * and security errors for admins
	 */
	public function template_admin_warning_above(): void
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
	 * Show the copyright.
	 */
	public function theme_copyright(): void
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
	 * Makes the default layers and languages available
	 *
	 * - Loads index and addon language files as needed
	 * - Loads XML, index, or no templates as needed
	 * - Loads templates as defined by $settings['theme_templates']
	 */
	public function loadDefaultLayers(): void
	{
		global $settings;

		$simpleActions = [
			'quickhelp',
			'printpage',
			'quotefast',
		];

		call_integration_hook('integrate_simple_actions', [&$simpleActions]);

		// Output is fully XML and sent by our JavaScript
		$api = $this->_req->getRequest('api', 'trim', '');
		$valid = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
		$action = $this->_req->getRequest('action', 'trim', '');

		if ($valid && $api === 'xml')
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
}
