<?php

/**
 * Manage features and options administration page.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use DateTimeZone;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Hooks;
use ElkArte\Languages\Txt;
use ElkArte\MetadataIntegrate;
use ElkArte\SettingsForm\SettingsForm;

/**
 * Manage features and options administration page.
 *
 * This controller handles the pages which allow the admin
 * to see and change the basic feature settings of their site.
 */
class ManageFeatures extends AbstractController
{
	/**
	 * Pre-dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		// We need this in a few places, so it's easier to have it loaded here
		require_once(SUBSDIR . '/ManageFeatures.subs.php');
	}

	/**
	 * This function passes control through to the relevant tab.
	 *
	 * @event integrate_sa_modify_features Use to add new Configuration tabs
	 * @see AbstractController::action_index()
	 * @uses Help, ManageSettings languages
	 * @uses sub_template show_settings
	 */
	public function action_index()
	{
		global $context, $txt, $settings;

		// Often Helpful
		Txt::load('Help+ManageSettings+Mentions');

		// All the actions we know about.  These must exist in loadMenu() of the admin controller.
		$subActions = [
			'basic' => [$this, 'action_basicSettings_display', 'permission' => 'admin_forum'],
			'layout' => ['controller' => ManageLayout::class, 'function' => 'action_index', 'permission' => 'admin_forum'],
			'pwa' => ['controller' => ManagePwa::class, 'function' => 'action_index', 'permission' => 'admin_forum'],
			'pmsettings' => ['controller' => ManagePmSettings::class, 'function' => 'action_index', 'permission' => 'admin_forum'],
			'mentions' => ['controller' => ManageMentions::class, 'function' => 'action_index', 'permission' => 'admin_forum'],
			'profile' => ['controller' => ManageCustomProfile::class, 'function' => 'action_index', 'enabled' => featureEnabled('cp'), 'permission' => 'admin_forum'],
			'profileedit' => ['controller' => ManageCustomProfile::class, 'function' => 'action_index', 'enabled' => featureEnabled('cp'), 'permission' => 'admin_forum'],
		];

		// Set up the action control
		$action = new Action('modify_features');

		// By default, do the basic settings, call integrate_sa_modify_features
		$subAction = $action->initialize($subActions, 'basic');

		// Some final pieces for the template
		$context['sub_template'] = 'show_settings';
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['modSettings_title'];

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'modSettings_title',
			'help' => 'featuresettings',
			'description' => sprintf($txt['modSettings_desc'], getUrl('admin', ['action' => 'admin', 'area' => 'theme', 'sa' => 'list', 'th' => $settings['theme_id'], '{session_data}'])),
			// All valid $subActions will be added, here you just specify any special tab data
			'tabs' => [
				'mention' => ['description' => $txt['mentions_settings_desc'],],
				'profile' => ['description' => $txt['custom_profile_desc'],],
				'pwa' => ['description' => $txt['pwa_settings_desc'],],
			],
		]);

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Config array for changing the basic forum settings
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=basic;
	 *
	 * @event integrate_save_basic_settings
	 */
	public function action_basicSettings_display(): void
	{
		global $txt, $context, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_basicSettings());

		theme()->addJavascriptVar(['txt_invalid_response' => $txt['ajax_bad_response']], true);

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			// Prevent absurd boundaries here - make it a day tops.
			if (isset($this->_req->post->lastActive))
			{
				$this->_req->post->lastActive = min((int) $this->_req->post->lastActive, 1440);
			}

			call_integration_hook('integrate_save_basic_settings');

			// Microdata needs to enable its integration
			if ($this->_req->isSet('metadata_enabled'))
			{
				Hooks::instance()->enableIntegration(MetadataIntegrate::class);
			}
			else
			{
				Hooks::instance()->disableIntegration(MetadataIntegrate::class);
			}

			// If they have changed Hive settings, let's clear them to avoid issues
			if (empty($modSettings['minify_css_js']) !== empty($this->_req->post->minify_css_js))
			{
				theme()->cleanHives();
			}

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			writeLog();
			redirectexit('action=admin;area=featuresettings;sa=basic');
		}

		if (isset($this->_req->post->cleanhives) && $this->getApi() === 'json')
		{
			$clean_hives_result = theme()->cleanHives();

			setJsonTemplate();
			$context['json_data'] = [
				'success' => $clean_hives_result,
				'response' => $clean_hives_result ? $txt['clean_hives_success'] : $txt['clean_hives_failed']
			];

			return;
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'basic', 'save']);
		$context['settings_title'] = $txt['mods_cat_features'];

		$settingsForm->prepare();
	}

	/**
	 * Return basic feature settings.
	 *
	 * @event integrate_modify_basic_settings Adds to General features and Options
	 */
	private function _basicSettings()
	{
		global $txt;

		$config_vars = [
			// Basic stuff, titles, permissions...
			['check', 'allow_guestAccess'],
			['check', 'enable_buddylist'],
			['check', 'allow_editDisplayName'],
			['check', 'allow_hideOnline'],
			['check', 'titlesEnable'],
			'',
			// JavaScript and CSS options
			['select', 'jquery_source', ['auto' => $txt['jquery_auto'], 'local' => $txt['jquery_local'], 'cdn' => $txt['jquery_cdn']]],
			['check', 'minify_css_js', 'postinput' => '<a href="#" id="clean_hives" class="linkbutton">' . $txt['clean_hives'] . '</a>'],
			'',
			// Number formatting, timezones.
			['text', 'time_format'],
			['float', 'time_offset', 'subtext' => $txt['setting_time_offset_note'], 6, 'postinput' => $txt['hours']],
			'default_timezone' => ['select', 'default_timezone', []],
			'',
			// Who's online?
			['check', 'who_enabled'],
			['int', 'lastActive', 6, 'postinput' => $txt['minutes']],
			'',
			// Statistics.
			['check', 'trackStats'],
			['check', 'hitStats'],
			'',
			// Option-ish things... miscellaneous sorta.
			['check', 'metadata_enabled'],
			['check', 'allow_disableAnnounce'],
			['check', 'disallow_sendBody'],
			['select', 'enable_contactform', ['disabled' => $txt['contact_form_disabled'], 'registration' => $txt['contact_form_registration'], 'menu' => $txt['contact_form_menu']]],
		];

		// Get all the time zones.
		$all_zones = DateTimeZone::listIdentifiers();
		if (empty($all_zones))
		{
			unset($config_vars['default_timezone']);
		}
		else
		{
			// Make sure we set the value to the same as the printed value.
			foreach ($all_zones as $zone)
			{
				$config_vars['default_timezone'][2][$zone] = $zone;
			}
		}

		theme()->addInlineJavascript('
			document.getElementById("clean_hives").addEventListener("click", function(event) {return cleanHives(event);});', ['defer' => true]);

		call_integration_hook('integrate_modify_basic_settings', [&$config_vars]);

		return $config_vars;
	}


	/**
	 * Public method to return the basic settings, used for admin search
	 */
	public function basicSettings_search()
	{
		return $this->_basicSettings();
	}
}
