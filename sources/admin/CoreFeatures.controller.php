<?php

/**
 * This controller allows to choose the features activated and disactivate them.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0.10
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This class takes care of the Core Features admin screen.
 *
 * What it does:
 * - It sets up the context, initializes the features info for display
 * - updates the settings for enabled/disabled core features as requested.
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
		$this->action_features();
	}

	/**
	 * This is an overall control panel enabling/disabling lots of the forums key features.
	 *
	 * What it does:
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
		global $txt, $scripturl, $context, $settings, $modSettings;

		require_once(SUBSDIR . '/Admin.subs.php');

		loadTemplate('CoreFeatures');

		$core_features = $this->settings();

		$this->loadGeneralSettingParameters();

		// Are we saving?
		if (isset($_POST['save']))
		{
			checkSession();

			if (isset($_GET['xml']))
			{
				$tokenValidation = validateToken('admin-core', 'post', false);

				if (empty($tokenValidation))
					return 'token_verify_fail';
			}
			else
				validateToken('admin-core');

			$setting_changes = array('admin_features' => array());

			// Cycle each feature and change things as required!
			foreach ($core_features as $id => $feature)
			{
				// Enabled?
				if (!empty($_POST['feature_' . $id]))
					$setting_changes['admin_features'][] = $id;

				// Setting values to change?
				if (isset($feature['settings']))
				{
					foreach ($feature['settings'] as $key => $value)
					{
						if (empty($_POST['feature_' . $id]) || (!empty($_POST['feature_' . $id]) && ($value < 2 || empty($modSettings[$key]))))
							$setting_changes[$key] = !empty($_POST['feature_' . $id]) ? $value : !$value;
					}
				}

				// Is there a call back for settings?
				if (isset($feature['setting_callback']))
				{
					$returned_settings = $feature['setting_callback'](!empty($_POST['feature_' . $id]));
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
					$feature['save_callback'](!empty($_POST['feature_' . $id]));
			}

			if (!isset($_REQUEST['xml']))
				redirectexit('action=admin;area=corefeatures;' . $context['session_var'] . '=' . $context['session_id']);
		}

		// Put them in context.
		$context['features'] = array();
		foreach ($core_features as $id => $feature)
			$context['features'][$id] = array(
				'title' => isset($feature['title']) ? $feature['title'] : $txt['core_settings_item_' . $id],
				'desc' => isset($feature['desc']) ? $feature['desc'] : $txt['core_settings_item_' . $id . '_desc'],
				'enabled' => in_array($id, $context['admin_features']),
				'state' => in_array($id, $context['admin_features']) ? 'on' : 'off',
				'url' => !empty($feature['url']) ? $scripturl . '?' . $feature['url'] . ';' . $context['session_var'] . '=' . $context['session_id'] : '',
				'image' => (file_exists($settings['theme_dir'] . '/images/admin/feature_' . $id . '.png') ? $settings['images_url'] : $settings['default_images_url']) . '/admin/feature_' . $id . '.png',
			);

		// Are they a new user?
		$context['is_new_install'] = !isset($modSettings['admin_features']);
		$context['force_disable_tabs'] = $context['is_new_install'];

		// Don't show them this twice!
		if ($context['is_new_install'])
			updateSettings(array('admin_features' => ''));

		// sub_template is already generic_xml and the token is created somewhere else
		if (isset($_REQUEST['xml']))
			return;

		$context['sub_template'] = 'core_features';
		$context['page_title'] = $txt['core_settings_title'];
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['core_settings_title'],
			'help' => '',
			'description' => $txt['core_settings_desc'],
		);

		// We love our tokens.
		createToken('admin-core');
	}

	/**
	 * Return the configuration settings available for core features page.
	 */
	public function settings()
	{
		$core_features = array(
			// cd = calendar.
			'cd' => array(
				'url' => 'action=admin;area=managecalendar',
				'settings' => array(
					'cal_enabled' => 1,
				),
			),
			// cp = custom profile fields.
			'cp' => array(
				'url' => 'action=admin;area=featuresettings;sa=profile',
				'save_callback' => 'custom_profiles_toggle_callback',
				'setting_callback' => create_function('$value', '
					if (!$value)
						return array(
							\'disabled_profile_fields\' => \'\',
							\'registration_fields\' => \'\',
							\'displayFields\' => \'\',
						);
					else
						return array();
				'),
			),
			// dr = drafts
			'dr' => array(
				'url' => 'action=admin;area=managedrafts',
				'settings' => array(
					'drafts_enabled' => 1,
					'drafts_post_enabled' => 2,
					'drafts_pm_enabled' => 2,
					'drafts_autosave_enabled' => 2,
					'drafts_show_saved_enabled' => 2,
				),
				'setting_callback' => 'drafts_toggle_callback',
			),
			// ih = Integration Hooks Handling.
			'ih' => array(
				'url' => 'action=admin;area=maintain;sa=hooks',
				'settings' => array(
					'handlinghooks_enabled' => 1,
				),
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
				'setting_callback' => create_function('$value', '
					require_once(SUBSDIR . \'/Mentions.subs.php\');

					// Makes all the like/rlike mentions invisible (or visible)
					toggleMentionsVisibility(\'like\', !empty($value));
					toggleMentionsVisibility(\'rlike\', !empty($value));
				'),
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
				'setting_callback' => create_function('$value', '

					// Cannot use warning post moderation if disabled!
					if (!$value)
					{
						require_once(SUBSDIR . \'/Moderation.subs.php\');
						approveAllUnapproved();

						return array(\'warning_moderate\' => 0);
					}
					else
						return array();
				'),
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
				'setting_callback' => create_function('$value', '
					global $modSettings;
					list ($modSettings[\'warning_enable\'], $modSettings[\'user_limit\'], $modSettings[\'warning_decrement\']) = explode(\',\', $modSettings[\'warning_settings\']);
					$warning_settings = ($value ? 1 : 0) . \',\' . $modSettings[\'user_limit\'] . \',\' . $modSettings[\'warning_decrement\'];
					if (!$value)
					{
						$returnSettings = array(
							\'warning_watch\' => 0,
							\'warning_moderate\' => 0,
							\'warning_mute\' => 0,
						);
					}
					elseif (empty($modSettings[\'warning_enable\']) && $value)
					{
						$returnSettings = array(
							\'warning_watch\' => 10,
							\'warning_moderate\' => 35,
							\'warning_mute\' => 60,
						);
					}
					else
						$returnSettings = array();

					$returnSettings[\'warning_settings\'] = $warning_settings;
					return $returnSettings;
				'),
			),
			// Search engines
			'sp' => array(
				'url' => 'action=admin;area=sengines',
				'settings' => array(
					'spider_mode' => 1,
				),
				'setting_callback' => create_function('$value', '
					// Turn off the spider group if disabling.
					if (!$value)
						return array(\'spider_group\' => 0, \'show_spider_online\' => 0);
				'),
				'on_save' => create_function('', '
					require_once(SUBSDIR . \'/SearchEngines.subs.php\');
				'),
			),
		);

		// Anyone who would like to add a core feature?
		call_integration_hook('integrate_core_features', array(&$core_features));

		return $core_features;
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
	 */
	public function loadGeneralSettingParameters($subActions = array(), $defaultAction = '')
	{
		global $context;

		// You need to be an admin to edit settings!
		isAllowedTo('admin_forum');

		loadLanguage('Help');
		loadLanguage('ManageSettings');

		// Will need the utility functions from here.
		require_once(SUBSDIR . '/SettingsForm.class.php');

		$context['sub_template'] = 'show_settings';

		// By default do the basic settings.
		if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
			$context['sub_action'] = $_REQUEST['sa'];
		elseif (!empty($defaultAction))
			$context['sub_action'] = $defaultAction;
		else
		{
			$temp = array_keys($subActions);
			$context['sub_action'] = array_pop($temp);
		}
	}
}