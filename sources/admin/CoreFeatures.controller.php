<?php

/**
 * This controller allows to choose features to activated and deactivate them.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0-dev
 *
 */

/**
 * This class takes care of the Core Features admin screen.
 *
 * What it does:
 *
 * - It sets up the context, initializes the features info for display
 * - updates the settings for enabled/disabled core features as requested.
 * - loads in module core features
 *
 * @package CoreFeatures
 */
class CoreFeatures_Controller extends Action_Controller
{
	/**
	 * Default handler.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// just delegate to our preferred default
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
	 *      reverse is done for disable. If value > 1 will not change value if set.
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

		// Are we saving?
		if (isset($this->_req->post->save))
		{
			checkSession();

			if (isset($this->_req->query->xml))
			{
				$tokenValidation = validateToken('admin-core', 'post', false);

				if (empty($tokenValidation))
					return 'token_verify_fail';
			}
			else
				validateToken('admin-core');

			$this->_save_core_features($core_features);

			if (!isset($this->_req->query->xml))
				redirectexit('action=admin;area=corefeatures;' . $context['session_var'] . '=' . $context['session_id']);
		}

		// Put them in context.
		$context['features'] = $this->_prepare_corefeatures($core_features);

		// Are they a new user?
		$context['is_new_install'] = !isset($modSettings['admin_features']);
		$context['force_disable_tabs'] = $context['is_new_install'];

		// Don't show them this twice!
		if ($context['is_new_install'])
			updateSettings(array('admin_features' => ''));

		// sub_template is already generic_xml and the token is created somewhere else
		if (isset($this->_req->query->xml))
			return true;

		$context['sub_template'] = 'core_features';
		$context['page_title'] = $txt['core_settings_title'];
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['core_settings_title'],
			'help' => '',
			'description' => $txt['core_settings_desc'],
		);
		theme()->addJavascriptVar(array(
			'token_name' => '',
			'token_value' => '',
			'feature_on_text' => $txt['core_settings_switch_off'],
			'feature_off_text' => $txt['core_settings_switch_on']
		), true);

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
		$core_features = array(
			// cp = custom profile fields.
			'cp' => array(
				'url' => 'action=admin;area=featuresettings;sa=profile',
				'save_callback' => 'custom_profiles_toggle_callback',
				'setting_callback' => function ($value) {
					if (!$value)
						return array(
							'disabled_profile_fields' => '',
							'registration_fields' => '',
							'displayFields' => '',
						);
					else
						return array();
				},
			),
			// k = karma.
			'k' => array(
				'url' => 'action=admin;area=featuresettings;sa=karma',
				'settings' => array(
					'karmaMode' => 2,
				),
			),
			// l = likes.
			'l' => array(
				'url' => 'action=admin;area=featuresettings;sa=likes',
				'settings' => array(
					'likes_enabled' => 1,
				),
				'setting_callback' => function ($value) {
					global $modSettings;

					require_once(SUBSDIR . '/Mentions.subs.php');

					// Makes all the like/rlike mentions invisible (or visible)
					toggleMentionsVisibility('likemsg', !empty($value));
					toggleMentionsVisibility('rlikemsg', !empty($value));

					$current = !empty($modSettings['enabled_mentions']) ? explode(',', $modSettings['enabled_mentions']) : array();

					if (!empty($value))
						return array('enabled_mentions' => implode(',', array_merge($current, array('likemsg', 'rlikemsg'))));
					else
						return array('enabled_mentions' => implode(',', array_diff($current, array('likemsg', 'rlikemsg'))));
				},
			),
			// ml = moderation log.
			'ml' => array(
				'url' => 'action=admin;area=logs;sa=modlog',
				'settings' => array(
					'modlog_enabled' => 1,
				),
			),
			// pe = post email
			'pe' => array(
				'url' => 'action=admin;area=maillist',
				'save_callback' => 'postbyemail_toggle_callback',
				'settings' => array(
					'maillist_enabled' => 1,
					'pbe_post_enabled' => 2,
					'pbe_pm_enabled' => 2,
				),
			),
			// pm = post moderation.
			'pm' => array(
				'url' => 'action=admin;area=permissions;sa=postmod',
				'setting_callback' => function ($value) {
					// Cannot use warning post moderation if disabled!
					if (!$value)
					{
						require_once(SUBSDIR . '/Moderation.subs.php');
						approveAllUnapproved();

						return array('warning_moderate' => 0);
					}
					else
						return array();
				},
			),
			// ps = Paid Subscriptions.
			'ps' => array(
				'url' => 'action=admin;area=paidsubscribe',
				'settings' => array(
					'paid_enabled' => 1,
				),
				'setting_callback' => 'subscriptions_toggle_callback',
			),
			// rg = report generator.
			'rg' => array(
				'url' => 'action=admin;area=reports',
			),
			// w = warning.
			'w' => array(
				'url' => 'action=admin;area=securitysettings;sa=moderation',
				'setting_callback' => function ($value) {
					global $modSettings;

					list ($modSettings['warning_enable'], $modSettings['user_limit'], $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);
					$warning_settings = ($value ? 1 : 0) . ',' . $modSettings['user_limit'] . ',' . $modSettings['warning_decrement'];
					if (!$value)
					{
						$returnSettings = array(
							'warning_watch' => 0,
							'warning_moderate' => 0,
							'warning_mute' => 0,
						);
					}
					elseif (empty($modSettings['warning_enable']) && $value)
					{
						$returnSettings = array(
							'warning_watch' => 10,
							'warning_moderate' => 35,
							'warning_mute' => 60,
						);
					}
					else
						$returnSettings = array();

					$returnSettings['warning_settings'] = $warning_settings;
					return $returnSettings;
				},
			),
			// Search engines
			'sp' => array(
				'url' => 'action=admin;area=sengines',
				'settings' => array(
					'spider_mode' => 1,
				),
				'setting_callback' => function ($value) {
					// Turn off the spider group if disabling.
					if (!$value)
						return array('spider_group' => 0, 'show_spider_online' => 0);
				},
				'on_save' => function () {
					require_once(SUBSDIR . '/SearchEngines.subs.php');
				},
			),
		);

		$this->_getModulesConfig($core_features);

		// Anyone who would like to add a core feature?
		call_integration_hook('integrate_core_features', array(&$core_features));

		return $core_features;
	}

	/**
	 * Searches the ADMINDIR looking for module managers and load the "Core Feature"
	 * if existing.
	 *
	 * @param mixed[] $core_features The core features array
	 */
	protected function _getModulesConfig(&$core_features)
	{
		// Find appropriately named core feature files in the admin directory
		$glob = new GlobIterator(ADMINDIR . '/Manage*Module.controller.php', FilesystemIterator::SKIP_DOTS);

		foreach ($glob as $file)
		{
			$class = $file->getBasename('.controller.php') . '_Controller';

			if (method_exists($class, 'addCoreFeature'))
				$class::addCoreFeature($core_features);
		}

		$integrations = Hooks::instance()->discoverIntegrations(ADDONSDIR);

		foreach ($integrations as $integration)
		{
			$core_features[$integration['id']] = array(
				'url' => empty($integration['details']->extra->setting_url) ? '?action=admin;area=addonsettings' : $integration['details']->extra->setting_url,
				'title' => $integration['title'],
				'desc' => $integration['description'],
			);

			if (method_exists($integration['class'], 'setting_callback'))
			{
				$core_features[$integration['id']]['setting_callback'] = function ($value) use ($integration) {
					$integration['class']::setting_callback($value);
				};
			}

			if (method_exists($integration['class'], 'on_save'))
			{
				$core_features[$integration['id']]['on_save'] = function () use ($integration) {
					$integration['class']::on_save();
				};
			}
		}
	}

	/**
	 * Return the array of core features in the format expected by search.
	 *
	 * - Callback for admin internal search.
	 *
	 * @return mixed[] array in a config_var format
	 */
	public function config_vars()
	{
		global $txt;

		$return_data = array();

		$core_features = $this->settings();

		// Convert this to a format that admin search will understand
		foreach ($core_features as $id => $data)
			$return_data[] = array('switch', isset($data['title']) ? $data['title'] : $txt['core_settings_item_' . $id]);

		return $return_data;
	}

	/**
	 * This function makes sure the requested subaction does exists, if it
	 * doesn't, it sets a default action.
	 *
	 * @param mixed[] $subActions = array() An array containing all possible subactions.
	 * @param string $defaultAction = '' the default action to be called if no valid subaction was found.
	 * @throws Elk_Exception
	 */
	public function loadGeneralSettingParameters($subActions = array(), $defaultAction = '')
	{
		global $context;

		// You need to be an admin to edit settings!
		isAllowedTo('admin_forum');

		theme()->getTemplates()->loadLanguageFile('Help');
		theme()->getTemplates()->loadLanguageFile('ManageSettings');

		$context['sub_template'] = 'show_settings';

		// By default do the basic settings.
		if (isset($this->_req->query->sa, $subActions[$this->_req->query->sa]))
			$context['sub_action'] = $this->_req->query->sa;
		elseif (!empty($defaultAction))
			$context['sub_action'] = $defaultAction;
		else
		{
			$temp = array_keys($subActions);
			$context['sub_action'] = array_pop($temp);
		}
	}

	/**
	 * Takes care os saving the core features status (enabled/disabled)
	 *
	 * @param mixed[] $core_features - The array of all the core features, as
	 *                returned by $this->settings()
	 */
	private function _save_core_features($core_features)
	{
		global $modSettings;

		$setting_changes = array('admin_features' => array());

		// Cycle each feature and change things as required!
		foreach ($core_features as $id => $feature)
		{
			$feature_id = $this->_req->getPost('feature_' . $id);

			// Enabled?
			if (!empty($feature_id))
				$setting_changes['admin_features'][] = $id;

			// Setting values to change?
			if (isset($feature['settings']))
			{
				foreach ($feature['settings'] as $key => $value)
				{
					if (empty($feature_id) || (!empty($feature_id) && ($value < 2 || empty($modSettings[$key]))))
						$setting_changes[$key] = !empty($feature_id) ? $value : !$value;
				}
			}

			// Is there a call back for settings?
			if (isset($feature['setting_callback']))
			{
				$returned_settings = $feature['setting_callback'](!empty($feature_id));
				if (!empty($returned_settings))
					$setting_changes = array_merge($setting_changes, $returned_settings);
			}

			// Standard save callback?
			if (isset($feature['on_save']))
				$feature['on_save']();
		}

		// Make sure this one setting is a string!
		$setting_changes['admin_features'] = implode(',', $setting_changes['admin_features']);

		// Make any setting changes!
		updateSettings($setting_changes);

		// This is needed to let menus appear if cache > 2
		if ($modSettings['cache_enable'] > 2)
			clean_cache('data');

		// Any post save things?
		foreach ($core_features as $id => $feature)
		{
			// Standard save callback?
			if (isset($feature['save_callback']))
			{
				$status = $this->_req->getPost('feature_' . $id);
				$feature['save_callback'](!empty($status));
			}
		}
	}

	/**
	 * Puts the core features data into a format usable by the template
	 *
	 * @param mixed[] $core_features - The array of all the core features, as
	 *                returned by $this->settings()
	 */
	protected function _prepare_corefeatures($core_features)
	{
		global $context, $txt, $settings, $scripturl;

		$features = array();
		foreach ($core_features as $id => $feature)
		{
			$features[$id] = array(
				'title' => isset($feature['title']) ? $feature['title'] : $txt['core_settings_item_' . $id],
				'desc' => isset($feature['desc']) ? $feature['desc'] : $txt['core_settings_item_' . $id . '_desc'],
				'enabled' => in_array($id, $context['admin_features']),
				'state' => in_array($id, $context['admin_features']) ? 'on' : 'off',
				'url' => !empty($feature['url']) ? $scripturl . '?' . $feature['url'] . ';' . $context['session_var'] . '=' . $context['session_id'] : '',
				'image' => (file_exists($settings['theme_dir'] . '/images/admin/feature_' . $id . '.png') ? $settings['images_url'] : $settings['default_images_url']) . '/admin/feature_' . $id . '.png',
			);
		}

		// Sort by title attribute
		uasort($features, function ($a, $b) {
			return strcmp(strtolower($a['title']), strtolower($b['title']));
		});

		return $features;
	}
}
