<?php

/**
 * This controller allows to choose features to activated and deactivate them.
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

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Cache\Cache;
use ElkArte\Helper\FileFunctions;
use ElkArte\Hooks;
use ElkArte\Languages\Txt;
use FilesystemIterator;
use GlobIterator;

/**
 * This class takes care of the Core Features admin screen.
 *
 * What it does:
 *
 * - It sets up the context, initializes the features info for display
 * - updates the settings for enabled/disabled core features as requested.
 * - loads in module core features
 *
 */
class CoreFeatures extends AbstractController
{
	/**
	 * Default handler.
	 *
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		// Delegate to our preferred default
		return $this->action_features();
	}

	/**
	 * This is an overall control panel enabling/disabling lots of the forums key features.
	 *
	 * What it does:
	 *
	 * - Uses internally an array of all the features that can be enabled/disabled.
	 * - $core_features, each option can have the following:
	 *    - title - Text title of this item (If standard string does not exist).
	 *    - desc - Description of this feature (If standard string does not exist).
	 *    - settings - Array of settings to change (For each name => value) on enable
	 *      reverse is done for disabling. If value > 1 does not change value if set.
	 *    - setting_callback - Function that returns an array of settings to save
	 *      takes one parameter which is value for this feature.
	 *    - save_callback - Function called on save, takes state as parameter.
	 */
	public function action_features()
	{
		global $txt, $context, $modSettings;

		require_once(SUBSDIR . '/Admin.subs.php');

		theme()->getTemplates()->load('CoreFeatures');

		$core_features = $this->settings();

		$this->loadGeneralSettingParameters();

		$api = $this->getApi();

		// Are we saving?
		if (isset($this->_req->post->save))
		{
			checkSession();

			if ($api === 'xml')
			{
				$tokenValidation = validateToken('admin-core', 'post', false);

				if (empty($tokenValidation))
				{
					return 'token_verify_fail';
				}
			}
			else
			{
				validateToken('admin-core');
			}

			$this->_save_core_features($core_features);

			if ($api !== 'xml')
			{
				redirectexit('action=admin;area=corefeatures;' . $context['session_var'] . '=' . $context['session_id']);
			}
		}

		// Put them in context.
		$context['features'] = $this->_prepare_corefeatures($core_features);

		// Are they a new user?
		$context['is_new_install'] = !isset($modSettings['admin_features']);
		$context['force_disable_tabs'] = $context['is_new_install'];

		// Don't show them this twice!
		if ($context['is_new_install'])
		{
			updateSettings(['admin_features' => '']);
		}

		// sub_template is already generic_xml and the token is created somewhere else
		if ($api === 'xml')
		{
			return true;
		}

		$context['sub_template'] = 'core_features';
		$context['page_title'] = $txt['core_settings_title'];

		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'core_settings_title',
			'description' => 'core_settings_desc',
		]);

		theme()->addJavascriptVar([
			'token_name' => JavaScriptEscape(''),
			'token_value' => JavaScriptEscape(''),
			'feature_on_text' => JavaScriptEscape($txt['core_settings_switch_off']),
			'feature_off_text' => JavaScriptEscape($txt['core_settings_switch_on'])
		]);

		theme()->addInlineJavascript('window.addEventListener("load", (event) => {coreFeatures(event);})', ['defer' => true]);

		// We love our tokens.
		createToken('admin-core');

		return true;
	}

	/**
	 * Return the configuration settings available for core features page.
	 *
	 * @event integrate_core_features passed $core_features array allowing for adding new
	 * ones to the feature page
	 */
	public function settings()
	{
		$core_features = [
			// cp = custom profile fields.
			'cp' => [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profile', '{session_data}']),
				'save_callback' => 'custom_profiles_toggle_callback',
				'setting_callback' => static function ($value) {
					if (!$value)
					{
						return [
							'disabled_profile_fields' => '',
							'registration_fields' => '',
							'displayFields' => '',
						];
					}

					return [];
				},
			],
			// k = karma.
			'k' => [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'karma', '{session_data}']),
				'settings' => [
					'karmaMode' => 2,
				],
			],
			// l = likes.
			'l' => [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'likes', '{session_data}']),
				'settings' => [
					'likes_enabled' => 1,
				],
				'setting_callback' => static function ($value) {
					global $modSettings;

					require_once(SUBSDIR . '/Mentions.subs.php');
					require_once(SUBSDIR . '/Notification.subs.php');

					// Makes all the like/rlike mentions invisible (or visible)
					toggleMentionsVisibility('likemsg', !empty($value));
					toggleMentionsVisibility('rlikemsg', !empty($value));
					$current = getEnabledNotifications();
					if (!empty($value))
					{
						return ['enabled_mentions' => implode(',', array_unique(array_merge($current, ['likemsg', 'rlikemsg'])))];
					}

					return ['enabled_mentions' => implode(',', array_unique(array_diff($current, ['likemsg', 'rlikemsg'])))];
				},
			],
			// ml = moderation log.
			'ml' => [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'modlog', '{session_data}']),
				'settings' => [
					'modlog_enabled' => 1,
					'userlog_enabled' => 1,
				],
			],
			// pe = post email
			'pe' => [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'maillist', 'sa' => 'emailsettings']),
				'save_callback' => 'postbyemail_toggle_callback',
				'settings' => [
					'maillist_enabled' => 1,
					'pbe_post_enabled' => 2,
					'pbe_pm_enabled' => 2,
				],
			],
			// pm = post moderation.
			'pm' => [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'permissions', 'sa' => 'postmod', '{session_data}']),
				'setting_callback' => static function ($value) {
					// Cannot use warning post moderation if disabled!
					if (!$value)
					{
						require_once(SUBSDIR . '/Moderation.subs.php');
						approveAllUnapproved();

						return ['warning_moderate' => 0];
					}

					return [];
				},
			],
			// ps = Paid Subscriptions.
			'ps' => [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'paidsubscribe']),
				'settings' => [
					'paid_enabled' => 1,
				],
				'setting_callback' => 'subscriptions_toggle_callback',
			],
			// rg = report generator.
			'rg' => [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'reports']),
			],
			// w = warning.
			'w' => [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'securitysettings', 'sa' => 'moderation']),
				'setting_callback' => static function ($value) {
					global $modSettings;

					[$modSettings['warning_enable'], $modSettings['user_limit'], $modSettings['warning_decrement']] = explode(',', $modSettings['warning_settings']);
					$warning_settings = ($value ? 1 : 0) . ',' . $modSettings['user_limit'] . ',' . $modSettings['warning_decrement'];
					if (!$value)
					{
						$returnSettings = [
							'warning_watch' => 0,
							'warning_moderate' => 0,
							'warning_mute' => 0,
						];
					}
					elseif (empty($modSettings['warning_enable']))
					{
						$returnSettings = [
							'warning_watch' => 10,
							'warning_moderate' => 35,
							'warning_mute' => 60,
						];
					}
					else
					{
						$returnSettings = [];
					}

					$returnSettings['warning_settings'] = $warning_settings;
					return $returnSettings;
				},
			],
			// Search engines
			'sp' => [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'sengines']),
				'settings' => [
					'spider_mode' => 1,
				],
				'setting_callback' => static function ($value) {
					// Turn off the spider group if disabling.
					if (!$value)
					{
						return ['spider_group' => 0, 'show_spider_online' => 0, 'spider_no_guest' => 0];
					}
				},
				'on_save' => static function () {
					require_once(SUBSDIR . '/SearchEngines.subs.php');
				},
			],
		];

		$this->_discoverCoreFeatures($core_features);

		// Anyone who would like to add a core feature?
		call_integration_hook('integrate_core_features', [&$core_features]);

		return $core_features;
	}

	/**
	 * Retrieves configuration for all modules, including core features and addons, by scanning predefined directories
	 * for relevant files and classes, and integrating their configurations.
	 *
	 * - Searches the ADMINDIR looking for XXXModule.php managers and loads the "Core Feature" if existing.
	 * - Searches the ADDONSDIR looking for xxxIntegrate.php managers and loads the "Core Feature" if existing.
	 * - Calls discoverIntegrations() to search ADDONSDIR for .integrate.php files for Core Feature inclusion.
	 *
	 * @param array $core_features Reference to an array that will be populated with core feature configurations
	 *                             collected from the specified directories and integration hooks.
	 * @return void
	 */
	protected function _discoverCoreFeatures(array &$core_features): void
	{
		// Admin directory ManageXyzModule.php style
		$this->_getModulesConfig($core_features);

		// Addons abcIntegrate.php style
		$core_features = $this->_getAddonIntegrationsConfig($core_features);

		// Addons xyz.integrate.php style
		$core_features = $this->_getDotIntegrateCoreConfig($core_features);
	}

	/**
	 * Updates the provided core features array with module data by discovering and invoking addCoreFeature methods
	 * from appropriately named core feature files in the admin directory.
	 *
	 * @param array $core_features An associative array representing the core features to be updated with module data.
	 * @return void
	 */
	public function _getModulesConfig(array &$core_features): void
	{
		// Find appropriately named core feature files in the admin (\sources\ElkArte\AdminController) directory
		$glob = new GlobIterator(ADMINDIR . '/Manage*Module.php', FilesystemIterator::SKIP_DOTS);
		foreach ($glob as $file)
		{
			$class = '\ElkArte\AdminController\\' . $file->getBasename('.php');
			if (method_exists($class, 'addCoreFeature'))
			{
				$class::addCoreFeature($core_features);
			}
		}
	}

	/**
	 * Scans the ADDONSDIR directory for integration files and retrieves configuration for addon core features.
	 *
	 * - Searches for files with the pattern *Integrate.php within subdirectories of ADDONSDIR.
	 * - Validates that each file contains a class definition to avoid processing non-relevant files.
	 * - Calls the `addCoreFeature` method of classes found within these integration files to populate the
	 *   core features configuration array.
	 *
	 * @param array $core_features An associative array representing the core features to be updated with integration data.
	 * @return array The updated core features array containing additional details about the integrations.
     */
	public function _getAddonIntegrationsConfig(array $core_features): array
	{
		// Find appropriately named core feature files in the Addons (\Addons) and below directory
		$glob = new GlobIterator(ADDONSDIR . '/*/*Integrate.php', FilesystemIterator::SKIP_DOTS);
		foreach ($glob as $file)
		{
			// Read the start of the file and validate as a class. Prevents loading non-class files
			// possibly generating a fatal error for "including" the same file twice.
			$fileContent = file_get_contents($file->getRealPath(), false, null, 0, 2000);
			if (!preg_match('/^class\s+[a-zA-Z]\w+/m', $fileContent))
			{
				continue;
			}

			// Will call \Addons\SUBDIRECTORY\XyzIntegrate.php.
			$class = '\Addons' . str_replace(ADDONSDIR . '/', '\\', $file->getPath()) . '\\' . $file->getBasename('.php');
			if (method_exists($class, 'addCoreFeature'))
			{
				$class::addCoreFeature($core_features);
			}
		}

		return $core_features;
	}

	/**
	 * Populates the provided core features array with integration data discovered from the Addons directory.
	 *
	 * @param array $core_features An associative array representing the core features to be updated with integration data.
	 * @return array The updated core features array containing additional details about the integrations.
	 */
	public function _getDotIntegrateCoreConfig(array $core_features): array
	{
		$integrations = Hooks::instance()->discoverIntegrations(ADDONSDIR);
		foreach ($integrations as $integration)
		{
			$core_features[$integration['id']] = [
				'url' => empty($integration['details']->extra->setting_url) ? getUrl('admin', ['action' => 'admin', 'area' => 'addonsettings']) : $integration['details']->extra->setting_url,
				'title' => $integration['title'],
				'desc' => $integration['description'],
			];

			if (method_exists($integration['class'], 'setting_callback'))
			{
				$core_features[$integration['id']]['setting_callback'] = static function ($value) use ($integration) {
					$integration['class']::setting_callback($value);
				};
			}

			if (method_exists($integration['class'], 'on_save'))
			{
				$core_features[$integration['id']]['on_save'] = static function () use ($integration) {
					$integration['class']::on_save();
				};
			}
		}

		return $core_features;
	}

	/**
	 * This function makes sure the requested subaction does exists, if it
	 * doesn't, it sets a default action.
	 *
	 * @param array $subActions = array() An array containing all possible subactions.
	 * @param string $defaultAction = '' the default action to be called if no valid subaction was found.
	 */
	public function loadGeneralSettingParameters(array $subActions = [], string $defaultAction = ''): void
	{
		global $context;

		// You need to be an admin to edit settings!
		isAllowedTo('admin_forum');

		Txt::load('Help+ManageSettings');

		$context['sub_template'] = 'show_settings';

		// By default do the basic settings.
		$subAction = $this->_req->getQuery('sa', 'trim|strval', $defaultAction);
		if (empty($subAction) || empty($subActions[$subAction]))
		{
			$temp = array_keys($subActions);
			$context['sub_action'] = array_pop($temp);
		}
	}

	/**
	 * Takes care os saving the core features status (enabled/disabled)
	 *
	 * @param array $core_features - The array of all the core features, as
	 *                returned by $this->settings()
	 */
	private function _save_core_features(array $core_features): void
	{
		global $modSettings;

		$setting_changes = ['admin_features' => []];

		// Cycle each feature and change things as required!
		foreach ($core_features as $id => $feature)
		{
			$feature_id = $this->_req->getPost('feature_' . $id, 'trim|strval');

			// Enabled?
			if (!empty($feature_id))
			{
				$setting_changes['admin_features'][] = $id;
			}

			// Setting values to change?
			if (isset($feature['settings']))
			{
				foreach ($feature['settings'] as $key => $value)
				{
					if (empty($feature_id) || ($value < 2 || empty($modSettings[$key])))
					{
						$setting_changes[$key] = empty($feature_id) ? !$value : $value;
					}
				}
			}

			// Is there a call back for settings?
			if (isset($feature['setting_callback']))
			{
				$returned_settings = $feature['setting_callback'](!empty($feature_id));
				if (!empty($returned_settings))
				{
					$setting_changes = array_merge($setting_changes, $returned_settings);
				}
			}

			// Standard save callback?
			if (isset($feature['on_save']))
			{
				$feature['on_save']();
			}
		}

		// Make sure this one setting is a string!
		$setting_changes['admin_features'] = implode(',', $setting_changes['admin_features']);

		// Make any setting changes!
		updateSettings($setting_changes);

		// This is needed to let menus appear if cache > 2
		if (Cache::instance()->levelHigherThan(2))
		{
			Cache::instance()->clean('data');
		}

		// Any post save things?
		foreach ($core_features as $id => $feature)
		{
			// Standard save callback?
			if (isset($feature['save_callback']))
			{
				$status = $this->_req->getPost('feature_' . $id, 'trim|strval');
				$feature['save_callback'](!empty($status));
			}
		}
	}

	/**
	 * Puts the core features data into a format usable by the template
	 *
	 * @param array $core_features - The array of all the core features, as returned by $this->settings()
	 *
	 * @return array
	 */
	protected function _prepare_corefeatures(array $core_features): array
	{
		global $txt, $settings;

		$features = [];
		foreach ($core_features as $id => $feature)
		{
			$features[$id] = [
				'title' => $feature['title'] ?? $txt['core_settings_item_' . $id],
				'desc' => $feature['desc'] ?? $txt['core_settings_item_' . $id . '_desc'],
				'enabled' => featureEnabled($id),
				'state' => featureEnabled($id) ? 'on' : 'off',
				'url' => $feature['url'],
				'image' => (FileFunctions::instance()->fileExists($settings['theme_dir'] . '/images/admin/feature_' . $id . '.png') ? $settings['images_url'] : $settings['default_images_url']) . '/admin/feature_' . $id . '.png',
			];
		}

		// Sort by title attribute
		uasort($features, static fn($a, $b) => strcmp(strtolower($a['title']), strtolower($b['title'])));

		return $features;
	}

	/**
	 * Return the array of core features in the format expected by search.
	 *
	 * - Callback for admin internal search.
	 *
	 * @return array array in a config_var format
	 */
	public function config_vars(): array
	{
		global $txt;

		$return_data = [];

		$core_features = $this->settings();

		// Convert this to a format that admin search will understand
		foreach ($core_features as $id => $data)
		{
			$return_data[] = ['switch', $data['title'] ?? $txt['core_settings_item_' . $id]];
		}

		return $return_data;
	}
}
