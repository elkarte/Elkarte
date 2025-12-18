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

use BBC\ParserWrapper;
use DateTimeZone;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\DataValidator;
use ElkArte\Helper\Util;
use ElkArte\Hooks;
use ElkArte\Languages\Txt;
use ElkArte\Mentions\MentionType\AbstractNotificationMessage;
use ElkArte\MetadataIntegrate;
use ElkArte\Notifications\Notifications;
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
	 * @see  AbstractController::action_index()
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
			'basic' => [
				'controller' => $this,
				'function' => 'action_basicSettings_display',
				'permission' => 'admin_forum'
			],
			'layout' => [
				'controller' => $this,
				'function' => 'action_layoutSettings_display',
				'permission' => 'admin_forum'
			],
			'pwa' => [
				'controller' => $this,
				'function' => 'action_pwaSettings_display',
				'enabled' => true,
				'permission' => 'admin_forum'
			],
			'karma' => [
				'controller' => $this,
				'function' => 'action_karmaSettings_display',
				'enabled' => featureEnabled('k'),
				'permission' => 'admin_forum'
			],
			'pmsettings' => [
				'controller' => $this,
				'function' => 'action_pmsettings',
				'permission' => 'admin_forum'
			],
			'likes' => [
				'controller' => $this,
				'function' => 'action_likesSettings_display',
				'enabled' => featureEnabled('l'),
				'permission' => 'admin_forum'
			],
			'mention' => [
				'controller' => $this,
				'function' => 'action_notificationsSettings_display',
				'permission' => 'admin_forum'
			],
			'sig' => [
				'controller' => $this,
				'function' => 'action_signatureSettings_display',
				'permission' => 'admin_forum'
			],
			'profile' => [
				'controller' => $this,
				'function' => 'action_profile',
				'enabled' => featureEnabled('cp'),
				'permission' => 'admin_forum'
			],
			'profileedit' => [
				'controller' => $this,
				'function' => 'action_profileedit',
				'permission' => 'admin_forum'
			],
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
				'mention' => [
					'description' => $txt['mentions_settings_desc'],
				],
				'sig' => [
					'description' => $txt['signature_settings_desc'],
				],
				'profile' => [
					'description' => $txt['custom_profile_desc'],
				],
				'pwa' => [
					'description' => $txt['pwa_settings_desc'],
				],
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
	 * Allows modifying the global layout settings in the forum
	 *
	 * - Accessed through ?action=admin;area=featuresettings;sa=layout;
	 *
	 * @event integrate_save_layout_settings
	 */
	public function action_layoutSettings_display(): void
	{
		global $txt, $context, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_layoutSettings());

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			// Setting a custom frontpage, set the hook to the FrontpageInterface of the controller
			if (!empty($this->_req->post->front_page))
			{
				// Addons may have left this blank
				$modSettings['front_page'] = empty($modSettings['front_page']) ? 'MessageIndex_Controller' : $modSettings['front_page'];

				$front_page = (string) $this->_req->post->front_page;
				if (
					class_exists($modSettings['front_page'])
					&& in_array('validateFrontPageOptions', get_class_methods($modSettings['front_page']))
					&& !$front_page::validateFrontPageOptions($this->_req->post)
				)
				{
					$this->_req->post->front_page = '';
				}
			}

			checkSession();

			call_integration_hook('integrate_save_layout_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			writeLog();

			redirectexit('action=admin;area=featuresettings;sa=layout');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'layout', 'save']);
		$context['settings_title'] = $txt['mods_cat_layout'];

		$settingsForm->prepare();
	}

	/**
	 * Return layout settings.
	 *
	 * @event integrate_modify_layout_settings Adds options to Configuration->Layout
	 */
	private function _layoutSettings()
	{
		global $txt;

		$config_vars = array_merge(getFrontPageControllers(), [
			'',
			// Pagination stuff.
			['check', 'compactTopicPagesEnable'],
			['int', 'compactTopicPagesContiguous', 'subtext' => str_replace(' ', '&nbsp;', '"3" ' . $txt['to_display'] . ': <strong>1 ... 4 [5] 6 ... 9</strong>') . '<br />' . str_replace(' ', '&nbsp;', '"5" ' . $txt['to_display'] . ': <strong>1 ... 3 4 [5] 6 7 ... 9</strong>')],
			['int', 'defaultMaxMembers'],
			['check', 'displayMemberNames'],
			'',
			// Stuff that just is everywhere - today, search, online, etc.
			['select', 'todayMod', [$txt['today_disabled'], $txt['today_only'], $txt['yesterday_today'], $txt['relative_time']]],
			['check', 'onlineEnable'],
			['check', 'enableVBStyleLogin'],
			'',
			// Automagic image resizing.
			['int', 'max_image_width', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'max_image_height', 'subtext' => $txt['zero_for_no_limit']],
			'',
			// This is like debugging sorta.
			['check', 'timeLoadPageEnable'],
		]);

		call_integration_hook('integrate_modify_layout_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Display configuration settings page for progressive web application settings.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=pwa;
	 *
	 * @event integrate_save_pwa_settings
	 */
	public function action_pwaSettings_display(): void
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_pwaSettings());

		// Saving, lots of checks then
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			call_integration_hook('integrate_save_pwa_settings');

			// Don't allow it to be enabled if we don't have SSL
			$canUse = detectServer()->supportsSSL();
			if (!$canUse)
			{
				$this->_req->post->pwa_enabled = 0;
			}

			// And you must enable this if PWA is enabled
			if ($this->_req->getPost('pwa_enabled', 'intval') === 1)
			{
				$this->_req->post->pwa_manifest_enabled = 1;
			}

			$validator = new DataValidator();
			$validation_rules = [
				'pwa_theme_color' => 'valid_color',
				'pwa_background_color' => 'valid_color',
				'pwa_short_name' => 'max_length[12]'
			];

			// Only check the rest if they entered something.
			$valid_urls = ['pwa_small_icon', 'pwa_large_icon', 'favicon_icon', 'apple_touch_icon'];
			foreach ($valid_urls as $url)
			{
				if ($this->_req->getPost($url, 'trim') !== '')
				{
					$validation_rules[$url] = 'valid_url';
				}
			}
			$validator->validation_rules($validation_rules);

			if (!$validator->validate($this->_req->post))
			{
				// Some input error, let's tell them what is wrong
				$context['error_type'] = 'minor';
				$context['settings_message'] = [];
				foreach ($validator->validation_errors() as $error)
				{
					$context['settings_message'][] = $error;
				}
			}
			else
			{
				$settingsForm->setConfigValues((array) $this->_req->post);
				$settingsForm->save();
				redirectexit('action=admin;area=featuresettings;sa=pwa');
			}
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'pwa', 'save']);
		$context['settings_title'] = $txt['pwa_settings'];
		theme()->addInlineJavascript('
			pwaPreview("pwa_small_icon");
			pwaPreview("pwa_large_icon");
			pwaPreview("favicon_icon");
			pwaPreview("apple_touch_icon");', true);

		$settingsForm->prepare();
	}

	/**
	 * Return PWA settings.
	 *
	 * @event integrate_modify_karma_settings Adds to Configuration->Pwa
	 */
	private function _pwaSettings()
	{
		global $txt;

		// PWA requires SSL
		$canUse = detectServer()->supportsSSL();

		$config_vars = [
			// PWA - On or off?
			['check', 'pwa_enabled', 'disabled' => !$canUse, 'invalid' => !$canUse, 'postinput' => !$canUse ? $txt['pwa_disabled'] : ''],
			'',
			['check', 'pwa_manifest_enabled', 'helptext' => $txt['pwa_manifest_enabled_desc']],
			['text', 'pwa_short_name', 12, 'mask' => 'nohtml', 'helptext' => $txt['pwa_short_name_desc'], 'maxlength' => 12],
			['color', 'pwa_theme_color', 'helptext' => $txt['pwa_theme_color_desc']],
			['color', 'pwa_background_color', 'helptext' => $txt['pwa_background_color_desc']],
			'',
			['url', 'pwa_small_icon', 'size' => 40, 'helptext' => $txt['pwa_small_icon_desc'], 'onchange' => "pwaPreview('pwa_small_icon');"],
			['url', 'pwa_large_icon', 'size' => 40, 'helptext' => $txt['pwa_large_icon_desc'], 'onchange' => "pwaPreview('pwa_large_icon');"],
			['title', 'other_icons_title'],
			['url', 'favicon_icon', 'size' => 40, 'helptext' => $txt['favicon_icon_desc'], 'onchange' => "pwaPreview('favicon_icon');"],
			['url', 'apple_touch_icon', 'size' => 40, 'helptext' => $txt['apple_touch_icon_desc'], 'onchange' => "pwaPreview('apple_touch_icon');"],
		];

		call_integration_hook('integrate_modify_pwa_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Display configuration settings page for karma settings.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=karma;
	 *
	 * @event integrate_save_karma_settings
	 */
	public function action_karmaSettings_display(): void
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_karmaSettings());

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			call_integration_hook('integrate_save_karma_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=karma');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'karma', 'save']);
		$context['settings_title'] = $txt['karma'];

		$settingsForm->prepare();
	}

	/**
	 * Return karma settings.
	 *
	 * @event integrate_modify_karma_settings Adds to Configuration->Karma
	 */
	private function _karmaSettings()
	{
		global $txt;

		$config_vars = [
			// Karma - On or off?
			['select', 'karmaMode', explode('|', $txt['karma_options'])],
			'',
			// Who can do it... and who is restricted by time limits?
			['int', 'karmaMinPosts', 6, 'postinput' => $txt['manageposts_posts']],
			['float', 'karmaWaitTime', 6, 'postinput' => $txt['hours']],
			['check', 'karmaTimeRestrictAdmins'],
			['check', 'karmaDisableSmite'],
			'',
			// What does it look like?  [smite]?
			['text', 'karmaLabel'],
			['text', 'karmaApplaudLabel', 'mask' => 'nohtml'],
			['text', 'karmaSmiteLabel', 'mask' => 'nohtml'],
		];

		call_integration_hook('integrate_modify_karma_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Display configuration settings page for likes settings.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=likes;
	 *
	 * @event integrate_save_likes_settings
	 */
	public function action_likesSettings_display(): void
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_likesSettings());

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			call_integration_hook('integrate_save_likes_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=likes');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'likes', 'save']);
		$context['settings_title'] = $txt['likes'];

		$settingsForm->prepare();
	}

	/**
	 * Return likes settings.
	 *
	 * @event integrate_modify_likes_settings Adds to Configuration->Likes
	 */
	private function _likesSettings()
	{
		global $txt;

		$config_vars = [
			// Likes - On or off?
			['check', 'likes_enabled'],
			'',
			// Who can do it... and who is restricted by count limits?
			['int', 'likeMinPosts', 6, 'postinput' => $txt['manageposts_posts']],
			['int', 'likeWaitTime', 6, 'postinput' => $txt['minutes']],
			['int', 'likeWaitCount', 6],
			['check', 'likeRestrictAdmins'],
			['check', 'likeAllowSelf'],
			['check', 'useLikesNotViews'],
			'',
			['int', 'likeDisplayLimit', 6]
		];

		call_integration_hook('integrate_modify_likes_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Initializes the mentions settings admin page.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=mention;
	 *
	 * @event integrate_save_modify_mention_settings
	 */
	public function action_notificationsSettings_display(): void
	{
		global $txt, $context, $modSettings;

		Txt::load('Mentions');

		// Instantiate the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_notificationsSettings());

		// Some context stuff
		$context['page_title'] = $txt['mentions_settings'];
		$context['sub_template'] = 'show_settings';

		// Saving the settings?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			call_integration_hook('integrate_save_modify_mention_settings');

			if (!empty($this->_req->post->mentions_enabled))
			{
				enableModules('mentions', ['post', 'display']);
			}
			else
			{
				disableModules('mentions', ['post', 'display']);
			}

			if (!empty($modSettings['hidden_notification_methods']))
			{
				foreach ($modSettings['hidden_notification_methods'] as $class)
				{
					$this->_req->post->notifications[$class::getType()] = $class::getSettings();
				}
			}

			if (empty($this->_req->post->notifications))
			{
				$notification_methods = serialize([]);
			}
			else
			{
				$notification_methods = [];
				foreach ($this->_req->post->notifications as $type => $notification)
				{
					if (!empty($notification['enable']))
					{
						$defaults = $notification['default'] ?? [];
						unset($notification['enable'], $notification['default']);
						foreach ($notification as $k => $v)
						{
							$notification[$k] = in_array($k, $defaults) ? Notifications::DEFAULT_LEVEL : $v;
						}

						$notification_methods[$type] = $notification;
					}
				}

				$notification_methods = serialize($notification_methods);
			}

			require_once(SUBSDIR . '/Mentions.subs.php');
			$enabled_mentions = [];
			$current_settings = Util::unserialize($modSettings['notification_methods']);

			// Fist hide what was visible
			$modules_toggle = ['enable' => [], 'disable' => []];
			foreach ($current_settings as $type => $val)
			{
				if (!isset($this->_req->post->notifications[$type]))
				{
					toggleMentionsVisibility($type, false);
					$modules_toggle['disable'][] = $type;
				}
			}

			// Then make visible what was hidden, but only if there is anything
			if (!empty($this->_req->post->notifications))
			{
				foreach ($this->_req->post->notifications as $type => $val)
				{
					if (!isset($current_settings[$type]))
					{
						toggleMentionsVisibility($type, true);
						$modules_toggle['enable'][] = $type;
					}
				}

				$enabled_mentions = array_keys($this->_req->post->notifications);
			}

			// Let's just keep it active, there are too many reasons it should be.
			require_once(SUBSDIR . '/ScheduledTasks.subs.php');
			toggleTaskStatusByName('user_access_mentions', true);

			// Disable or enable modules as needed
			foreach ($modules_toggle as $action => $toggles)
			{
				if (!empty($toggles))
				{
					// The modules associated with the notification (mentionmem, likes, etc.) area
					$modules = getMentionsModules($toggles);

					// The action will either be enabled to disable
					$function = $action . 'Modules';

					// Something like enableModule('mentions', array('post', 'display');
					foreach ($modules as $key => $val)
					{
						$function($key, $val);
					}
				}
			}

			updateSettings(['enabled_mentions' => implode(',', array_unique($enabled_mentions)), 'notification_methods' => $notification_methods]);
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=mention');
		}

		// Prepare the settings for display
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'mention', 'save']);
		$settingsForm->prepare();
	}

	/**
	 * Return mentions settings.
	 *
	 * @event integrate_modify_mention_settings Adds to Configuration->Mentions
	 */
	private function _notificationsSettings()
	{
		global $txt, $modSettings;

		Txt::load('Profile+UserNotifications');
		loadJavascriptFile('ext/jquery.multiselect.min.js');
		theme()->addInlineJavascript('
			$(\'.select_multiple\').multiselect({\'language_strings\': {\'Select all\': ' . JavaScriptEscape($txt['notify_select_all']) . '}});
			document.addEventListener("DOMContentLoaded", function() {
                 prepareNotificationOptions();
			});', true);
		loadCSSFile('multiselect.css');

		// Mentions settings
		$config_vars = [
			['title', 'mentions_settings'],
			['check', 'mentions_enabled'],
		];

		$notification_methods = Notifications::instance()->getNotifiers();
		$notification_classes = getAvailableNotifications();
		$current_settings = unserialize($modSettings['notification_methods'], ['allowed_classes' => false]);

		foreach ($notification_classes as $class)
		{
			// The canUse can be set by each notifier based on conditions, default is true;
			/* @var $class AbstractNotificationMessage */
			if ($class::canUse() === false)
			{
				continue;
			}

			if ($class::hasHiddenInterface() === true)
			{
				$modSettings['hidden_notification_methods'][] = $class;
				continue;
			}

			// Set up config enable/disable setting for all notifications.
			$title = strtolower($class::getType());
			$config_vars[] = ['title', 'setting_' . $title];
			$config_vars[] = ['check', 'notifications[' . $title . '][enable]', 'text_label' => $txt['setting_notify_enable_this']];
			$modSettings['notifications[' . $title . '][enable]'] = !empty($current_settings[$title]);
			$default_values = [];
			$is_default = [];

			// If it is enabled, show all the available ways, like email, notify, weekly ...
			foreach (array_keys($notification_methods) as $method_name)
			{
				$method_name = strtolower($method_name);

				// Are they excluding any, like don't let mailfail be allowed to send email!
				if ($class::isNotAllowed($method_name))
				{
					continue;
				}

				$config_vars[] = ['check', 'notifications[' . $title . '][' . $method_name . ']', 'text_label' => $txt['notify_' . $method_name]];
				$modSettings['notifications[' . $title . '][' . $method_name . ']'] = !empty($current_settings[$title][$method_name]);
				$default_values[] = [$method_name, $txt['notify_' . $method_name]];
				if (empty($current_settings[$title][$method_name]))
				{
					continue;
				}

				if ((int) $current_settings[$title][$method_name] !== Notifications::DEFAULT_LEVEL)
				{
					continue;
				}

				$is_default[] = $method_name;
			}

			$config_vars[] = ['select', 'notifications[' . $title . '][default]', $default_values, 'text_label' => $txt['default_active'], 'multiple' => true, 'value' => $is_default];
			$modSettings['notifications[' . $title . '][default]'] = $is_default;
		}

		call_integration_hook('integrate_modify_mention_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Display configuration settings for signatures on forum.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=sig;
	 *
	 * @event integrate_save_signature_settings
	 */
	public function action_signatureSettings_display(): void
	{
		global $context, $txt, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_signatureSettings());

		// Set up the template.
		$context['page_title'] = $txt['signature_settings'];
		$context['sub_template'] = 'show_settings';

		// Disable the max smileys option if we don't allow smileys at all!
		theme()->addInlineJavascript('
			document.getElementById(\'signature_max_smileys\').disabled = !document.getElementById(\'signature_allow_smileys\').checked;', true);

		// Load all the signature settings.
		[$sig_limits, $sig_bbc] = explode(':', $modSettings['signature_settings']);
		$sig_limits = explode(',', $sig_limits);
		$disabledTags = empty($sig_bbc) ? [] : explode(',', $sig_bbc);

		// @todo temporary since it does not work, and seriously why would you do this?
		$disabledTags[] = 'footnote';

		// Applying to ALL signatures?!!
		if ($this->_req->hasQuery('apply'))
		{
			// Security!
			checkSession('get');

			// This is horrid - but I suppose some people will want the option to do it.
			$applied_sigs = $this->_req->getQuery('step', 'intval', 0);
			updateAllSignatures($applied_sigs);

			$settings_applied = true;
		}

		$context['signature_settings'] = [
			'enable' => $sig_limits[0] ?? 0,
			'max_length' => $sig_limits[1] ?? 0,
			'max_lines' => $sig_limits[2] ?? 0,
			'max_images' => $sig_limits[3] ?? 0,
			'allow_smileys' => isset($sig_limits[4]) && $sig_limits[4] == -1 ? 0 : 1,
			'max_smileys' => isset($sig_limits[4]) && $sig_limits[4] != -1 ? $sig_limits[4] : 0,
			'max_image_width' => $sig_limits[5] ?? 0,
			'max_image_height' => $sig_limits[6] ?? 0,
			'max_font_size' => $sig_limits[7] ?? 0,
			'repetition_guests' => $sig_limits[8] ?? 0,
			'repetition_members' => $sig_limits[9] ?? 0,
		];

		// Temporarily make each setting a modSetting!
		foreach ($context['signature_settings'] as $key => $value)
		{
			$modSettings['signature_' . $key] = $value;
		}

		// Make sure we check the right tags!
		$modSettings['bbc_disabled_signature_bbc'] = $disabledTags;

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			// Clean up the tag stuff!
			$codes = ParserWrapper::instance()->getCodes();
			$bbcTags = $codes->getTags();

			$signature_bbc_enabledTags = $this->_req->getPost('signature_bbc_enabledTags', null, []);
			if (!is_array($signature_bbc_enabledTags))
			{
				$signature_bbc_enabledTags = [$signature_bbc_enabledTags];
			}

			// Do not mutate the request; keep a local copy for settings persistence
			$signature_bbc_enabledTags_local = $signature_bbc_enabledTags;

			$sig_limits = [];
			foreach (array_keys($context['signature_settings']) as $key)
			{
				if ($key === 'allow_smileys')
				{
					continue;
				}
				if ($key === 'max_smileys' && empty($this->_req->post->signature_allow_smileys))
				{
					$sig_limits[] = -1;
				}
				else
				{
					$current_key = $this->_req->getPost('signature_' . $key, 'intval');
					$sig_limits[] = empty($current_key) ? 0 : max(1, $current_key);
				}
			}

			call_integration_hook('integrate_save_signature_settings', [&$sig_limits, &$bbcTags]);

			// Build the combined signature settings string using locals (do not write back to request)
			$signature_settings_local = implode(',', $sig_limits) . ':' . implode(',', array_diff($bbcTags, $signature_bbc_enabledTags_local));

			// Even though we have practically no settings, let's keep the convention going!
			$save_vars = [];
			$save_vars[] = ['text', 'signature_settings'];

			$settingsForm->setConfigVars($save_vars);
			// Start from posted values but override with our local computed values
			$config_values = (array) $this->_req->post;
			$config_values['signature_bbc_enabledTags'] = $signature_bbc_enabledTags_local;
			$config_values['signature_settings'] = $signature_settings_local;
			$settingsForm->setConfigValues($config_values);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=sig');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'sig', 'save']);
		$context['settings_title'] = $txt['signature_settings'];
		$context['settings_message'] = empty($settings_applied) ? sprintf($txt['signature_settings_warning'], getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'sig', 'apply', '{session_data}'])) : $txt['signature_settings_applied'];

		$settingsForm->prepare();
	}

	/**
	 * Return signature settings.
	 *
	 * - Used in admin center search and settings form
	 *
	 * @event integrate_modify_signature_settings Adds options to Signature Settings
	 */
	private function _signatureSettings()
	{
		global $txt;

		$config_vars = [
			// Are signatures even enabled?
			['check', 'signature_enable'],
			'',
			// Tweaking settings!
			['int', 'signature_max_length', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'signature_max_lines', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'signature_max_font_size', 'subtext' => $txt['zero_for_no_limit']],
			['check', 'signature_allow_smileys', 'onclick' => "document.getElementById('signature_max_smileys').disabled = !this.checked;"],
			['int', 'signature_max_smileys', 'subtext' => $txt['zero_for_no_limit']],
			['select', 'signature_repetition_guests',
				[
					$txt['signature_always'],
					$txt['signature_onlyfirst'],
					$txt['signature_never'],
				],
			],
			['select', 'signature_repetition_members',
				[
					$txt['signature_always'],
					$txt['signature_onlyfirst'],
					$txt['signature_never'],
				],
			],
			'',
			// Image settings.
			['int', 'signature_max_images', 'subtext' => $txt['signature_max_images_note']],
			['int', 'signature_max_image_width', 'subtext' => $txt['zero_for_no_limit']],
			['int', 'signature_max_image_height', 'subtext' => $txt['zero_for_no_limit']],
			'',
			['bbc', 'signature_bbc'],
		];

		call_integration_hook('integrate_modify_signature_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Show all the custom profile fields available to the user.
	 *
	 * - Allows for drag/drop sorting of custom profile fields
	 * - Accessed with ?action=admin;area=featuresettings;sa=profile
	 *
	 * @uses sub template show_custom_profile
	 */
	public function action_profile(): void
	{
		global $txt, $context;

		theme()->getTemplates()->load('ManageFeatures');
		$context['page_title'] = $txt['custom_profile_title'];
		$context['sub_template'] = 'show_custom_profile';

		// What about standard fields they can tweak?
		$standard_fields = ['website', 'posts', 'warning_status', 'date_registered', 'action'];

		// What fields can't you put on the registration page?
		$context['fields_no_registration'] = ['posts', 'warning_status', 'date_registered', 'action'];

		// Are we saving any standard field changes?
		if ($this->_req->hasPost('save'))
		{
			checkSession();
			validateToken('admin-scp');

			$changes = [];

			// Do the active ones first.
			$disable_fields = array_flip($standard_fields);
			if (!empty($this->_req->post->active))
			{
				foreach ($this->_req->post->active as $value)
				{
					if (isset($disable_fields[$value]))
					{
						unset($disable_fields[$value]);
					}
				}
			}

			// What we have left!
			$changes['disabled_profile_fields'] = empty($disable_fields) ? '' : implode(',', array_keys($disable_fields));

			// Things we want to show on registration?
			$reg_fields = [];
			if (!empty($this->_req->post->reg))
			{
				foreach ($this->_req->post->reg as $value)
				{
					if (!in_array($value, $standard_fields))
					{
						continue;
					}

					if (isset($disable_fields[$value]))
					{
						continue;
					}

					$reg_fields[] = $value;
				}
			}

			// What we have left!
			$changes['registration_fields'] = empty($reg_fields) ? '' : implode(',', $reg_fields);

			updateSettings($changes);
		}

		createToken('admin-scp');

		// Create a listing for all our standard fields
		$listOptions = [
			'id' => 'standard_profile_fields',
			'title' => $txt['standard_profile_title'],
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profile']),
			'get_items' => [
				'function' => 'list_getProfileFields',
				'params' => [
					true,
				],
			],
			'columns' => [
				'field' => [
					'header' => [
						'value' => $txt['standard_profile_field'],
					],
					'data' => [
						'db' => 'label',
						'style' => 'width: 60%;',
					],
				],
				'active' => [
					'header' => [
						'value' => $txt['custom_edit_active'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static function ($rowData) {
							$isChecked = $rowData['disabled'] ? '' : ' checked="checked"';
							$onClickHandler = $rowData['can_show_register'] ? sprintf('onclick="document.getElementById(\'reg_%1$s\').disabled = !this.checked;"', $rowData['id']) : '';

							return sprintf('<input type="checkbox" name="active[]" id="active_%1$s" value="%1$s" class="input_check" %2$s %3$s />', $rowData['id'], $isChecked, $onClickHandler);
						},
						'style' => 'width: 20%;',
						'class' => 'centertext',
					],
				],
				'show_on_registration' => [
					'header' => [
						'value' => $txt['custom_edit_registration'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static function ($rowData) {
							$isChecked = $rowData['on_register'] && !$rowData['disabled'] ? ' checked="checked"' : '';
							$isDisabled = $rowData['can_show_register'] ? '' : ' disabled="disabled"';

							return sprintf('<input type="checkbox" name="reg[]" id="reg_%1$s" value="%1$s" class="input_check" %2$s %3$s />', $rowData['id'], $isChecked, $isDisabled);
						},
						'style' => 'width: 20%;',
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profile']),
				'name' => 'standardProfileFields',
				'token' => 'admin-scp',
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="save" value="' . $txt['save'] . '" class="right_submit" />',
				],
			],
		];
		createList($listOptions);

		// And now we do the same for all of our custom ones
		$token = createToken('admin-sort');
		$listOptions = [
			'id' => 'custom_profile_fields',
			'title' => $txt['custom_profile_title'],
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profile']),
			'default_sort_col' => 'vieworder',
			'no_items_label' => $txt['custom_profile_none'],
			'items_per_page' => 25,
			'sortable' => true,
			'get_items' => [
				'function' => 'list_getProfileFields',
				'params' => [
					false,
				],
			],
			'get_count' => [
				'function' => 'list_getProfileFieldSize',
			],
			'columns' => [
				'vieworder' => [
					'header' => [
						'value' => '',
						'class' => 'hide',
					],
					'data' => [
						'db' => 'vieworder',
						'class' => 'hide',
					],
					'sort' => [
						'default' => 'vieworder',
					],
				],
				'field_name' => [
					'header' => [
						'value' => $txt['custom_profile_fieldname'],
					],
					'data' => [
						'function' => static fn($rowData) => sprintf('<a href="%1$s">%2$s</a><div class="smalltext">%3$s</div>', getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profileedit', 'fid' => (int) $rowData['id_field']]), $rowData['field_name'], $rowData['field_desc']),
						'style' => 'width: 65%;',
					],
					'sort' => [
						'default' => 'field_name',
						'reverse' => 'field_name DESC',
					],
				],
				'field_type' => [
					'header' => [
						'value' => $txt['custom_profile_fieldtype'],
					],
					'data' => [
						'function' => static function ($rowData) {
							global $txt;

							$textKey = sprintf('custom_profile_type_%1$s', $rowData['field_type']);

							return $txt[$textKey] ?? $textKey;
						},
						'style' => 'width: 10%;',
					],
					'sort' => [
						'default' => 'field_type',
						'reverse' => 'field_type DESC',
					],
				],
				'cust' => [
					'header' => [
						'value' => $txt['custom_profile_active'],
						'class' => 'centertext',
					],
					'data' => [
						'function' => static function ($rowData) {
							$isChecked = $rowData['active'] === '1' ? ' checked="checked"' : '';

							return sprintf('<input type="checkbox" name="cust[]" id="cust_%1$s" value="%1$s" class="input_check"%2$s />', $rowData['id_field'], $isChecked);
						},
						'style' => 'width: 8%;',
						'class' => 'centertext',
					],
					'sort' => [
						'default' => 'active DESC',
						'reverse' => 'active',
					],
				],
				'placement' => [
					'header' => [
						'value' => $txt['custom_profile_placement'],
					],
					'data' => [
						'function' => static function ($rowData) {
							global $txt;

							$placement = 'custom_profile_placement_';
							switch ((int) $rowData['placement'])
							{
								case 0:
									$placement .= 'standard';
									break;
								case 1:
									$placement .= 'withicons';
									break;
								case 2:
									$placement .= 'abovesignature';
									break;
								case 3:
									$placement .= 'aboveicons';
									break;
							}

							return $txt[$placement];
						},
						'style' => 'width: 5%;',
					],
					'sort' => [
						'default' => 'placement DESC',
						'reverse' => 'placement',
					],
				],
				'modify' => [
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profileedit']) . ';fid=%1$s">' . $txt['modify'] . '</a>',
							'params' => [
								'id_field' => false,
							],
						],
						'style' => 'width: 5%;',
					],
				],
			],
			'form' => [
				'href' => getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'profileedit']),
				'name' => 'customProfileFields',
				'token' => 'admin-scp',
			],
			'additional_rows' => [
				[
					'class' => 'submitbutton flow_flex_additional_row',
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="onoff" value="' . $txt['save'] . '" />
						<input type="submit" name="new" value="' . $txt['custom_profile_make_new'] . '" />',
				],
				[
					'position' => 'top_of_list',
					'value' => '<p class="infobox">' . $txt['custom_profile_sort'] . '</p>',
				],
			],
			'javascript' => '
				$().elkSortable({
					sa: "profileorder",
					error: "' . $txt['admin_order_error'] . '",
					title: "' . $txt['admin_order_title'] . '",
					placeholder: "ui-state-highlight",
					href: "?action=admin;area=featuresettings;sa=profile",
					token: {token_var: "' . $token['admin-sort_token_var'] . '", token_id: "' . $token['admin-sort_token'] . '"}
				});
			',
		];

		createList($listOptions);
	}

	/**
	 * Edit some profile fields?
	 *
	 * - Accessed with ?action=admin;area=featuresettings;sa=profileedit
	 *
	 * @uses sub template edit_profile_field
	 */
	public function action_profileedit(): void
	{
		global $txt, $context;

		theme()->getTemplates()->load('ManageFeatures');

		// Sort out the context!
		$context['fid'] = $this->_req->getQuery('fid', 'intval', 0);
		$context[$context['admin_menu_name']]['current_subsection'] = 'profile';
		$context['page_title'] = $context['fid'] ? $txt['custom_edit_title'] : $txt['custom_add_title'];
		$context['sub_template'] = 'edit_profile_field';

		// Any error messages to show?
		if ($this->_req->hasQuery('msg'))
		{
			Txt::load('Errors');
			$msg_key = $this->_req->getQuery('msg', 'trim|strval', '');
			if (isset($txt['custom_option_' . $msg_key]))
			{
				$context['custom_option__error'] = $txt['custom_option_' . $msg_key];
			}
		}

		// Load the profile language for section names.
		Txt::load('Profile');

		// Load up the profile field if one was supplied
		if ($context['fid'])
		{
			$context['field'] = getProfileField($context['fid']);
		}

		// Set up the default values as needed.
		if (empty($context['field']))
		{
			$context['field'] = [
				'name' => '',
				'colname' => '???',
				'desc' => '',
				'profile_area' => 'forumprofile',
				'reg' => false,
				'display' => false,
				'memberlist' => false,
				'type' => 'text',
				'max_length' => 255,
				'rows' => 4,
				'cols' => 30,
				'bbc' => false,
				'default_check' => false,
				'default_select' => '',
				'default_value' => '',
				'options' => ['', '', ''],
				'active' => true,
				'private' => false,
				'can_search' => false,
				'mask' => 'nohtml',
				'regex' => '',
				'enclose' => '',
				'placement' => 0,
			];
		}

		// All the JavaScript for this page... everything else is in admin.js
		theme()->addJavascriptVar(['startOptID' => count($context['field']['options'])]);
		theme()->addInlineJavascript('updateInputBoxes();', true);

		// Are we toggling which ones are active?
		if (isset($this->_req->post->onoff))
		{
			checkSession();
			validateToken('admin-scp');

			// Enable and disable custom fields as required.
			$enabled = [0];
			if (isset($this->_req->post->cust) && is_array($this->_req->post->cust))
			{
				foreach ($this->_req->post->cust as $id)
				{
					$enabled[] = (int) $id;
				}
			}

			updateRenamedProfileStatus($enabled);
		}
		// Are we saving?
		elseif ($this->_req->hasPost('save'))
		{
			checkSession();
			validateToken('admin-ecp');

			// Everyone needs a name - even the (bracket) unknown...
			if (trim($this->_req->post->field_name) === '')
			{
				redirectexit('action=admin;area=featuresettings;sa=profileedit;fid=' . (int) $context['fid'] . ';msg=need_name');
			}

			// Regex, you say?  Do a very basic test to see if the pattern is valid
			if (!empty($this->_req->post->regex) && @preg_match($this->_req->post->regex, 'dummy') === false)
			{
				redirectexit('action=admin;area=featuresettings;sa=profileedit;fid=' . (int) $context['fid'] . ';msg=regex_error');
			}

			$this->_req->post->field_name = $this->_req->getPost('field_name', 'Util::htmlspecialchars');
			$this->_req->post->field_desc = $this->_req->getPost('field_desc', 'Util::htmlspecialchars');

			$rows = isset($this->_req->post->rows) ? (int) $this->_req->post->rows : 4;
			$cols = isset($this->_req->post->cols) ? (int) $this->_req->post->cols : 30;

			// Checkboxes...
			$show_reg = $this->_req->getPost('reg', 'intval', 0);
			$show_display = isset($this->_req->post->display) ? 1 : 0;
			$show_memberlist = isset($this->_req->post->memberlist) ? 1 : 0;
			$bbc = isset($this->_req->post->bbc) ? 1 : 0;
			$show_profile = $this->_req->post->profile_area;
			$active = isset($this->_req->post->active) ? 1 : 0;
			$private = $this->_req->getPost('private', 'intval', 0);
			$can_search = isset($this->_req->post->can_search) ? 1 : 0;

			// Some masking stuff...
			$mask = $this->_req->getPost('mask', 'strval', '');
			if ($mask === 'regex' && isset($this->_req->post->regex))
			{
				$mask .= $this->_req->post->regex;
			}

			$field_length = $this->_req->getPost('max_length', 'intval', 255);
			$enclose = $this->_req->getPost('enclose', 'strval', '');
			$placement = $this->_req->getPost('placement', 'intval', 0);

			// Select options?
			$field_options = '';
			$newOptions = [];

			// Set default
			$default = '';

			switch ($this->_req->post->field_type)
			{
				case 'check':
					$default = isset($this->_req->post->default_check) ? 1 : '';
					break;
				case 'select':
				case 'radio':
					if (!empty($this->_req->post->select_option))
					{
						foreach ($this->_req->post->select_option as $k => $v)
						{
							// Clean, clean, clean...
							$v = Util::htmlspecialchars($v);
							$v = strtr($v, [',' => '']);

							// Nada, zip, etc...
							if (trim($v) === '')
							{
								continue;
							}

							// Otherwise, save it boy.
							$field_options .= $v . ',';

							// This is just for working out what happened with old options...
							$newOptions[$k] = $v;

							// Is it default?
							if (!isset($this->_req->post->default_select))
							{
								continue;
							}

							if ($this->_req->post->default_select != $k)
							{
								continue;
							}

							$default = $v;
						}

						if (isset($_POST['default_select']) && $_POST['default_select'] === 'no_default')
						{
							$default = 'no_default';
						}

						$field_options = substr($field_options, 0, -1);
					}

					break;
				default:
					$default = $this->_req->post->default_value ?? '';
			}

			// Come up with the unique name?
			if (empty($context['fid']))
			{
				$colname = Util::substr(strtr($this->_req->post->field_name, [' ' => '']), 0, 6);
				preg_match('~([\w_-]+)~', $colname, $matches);

				// If there is nothing to the name, then let's start our own - for foreign languages etc.
				if (isset($matches[1]))
				{
					$colname = 'cust_' . strtolower($matches[1]);
					$initial_colname = 'cust_' . strtolower($matches[1]);
				}
				else
				{
					$colname = 'cust_' . mt_rand(1, 999999);
					$initial_colname = 'cust_' . mt_rand(1, 999999);
				}

				$unique = ensureUniqueProfileField($colname, $initial_colname);

				// Still not a unique column name? Leave it up to the user, then.
				if (!$unique)
				{
					throw new Exception('custom_option_not_unique');
				}

				// And create a new field
				$new_field = [
					'col_name' => $colname,
					'field_name' => $this->_req->post->field_name,
					'field_desc' => $this->_req->post->field_desc,
					'field_type' => $this->_req->post->field_type,
					'field_length' => $field_length,
					'field_options' => $field_options,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'show_memberlist' => $show_memberlist,
					'show_profile' => $show_profile,
					'private' => $private,
					'active' => $active,
					'default_value' => $default,
					'rows' => $rows,
					'cols' => $cols,
					'can_search' => $can_search,
					'bbc' => $bbc,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
					'vieworder' => list_getProfileFieldSize() + 1,
				];
				addProfileField($new_field);
			}
			// Work out what to do with the user data otherwise...
			else
			{
				// Anything going to check or select is pointless keeping - as is anything coming from check!
				if (($this->_req->post->field_type === 'check' && $context['field']['type'] !== 'check')
					|| (($this->_req->post->field_type === 'select' || $this->_req->post->field_type === 'radio') && $context['field']['type'] !== 'select' && $context['field']['type'] !== 'radio')
					|| ($context['field']['type'] === 'check' && $this->_req->post->field_type !== 'check'))
				{
					deleteProfileFieldUserData($context['field']['colname']);
				}
				// Otherwise - if the select is edited may need to adjust!
				elseif ($this->_req->post->field_type === 'select' || $this->_req->post->field_type === 'radio')
				{
					$optionChanges = $context['field']['options'];
					$takenKeys = [];

					// Work out what's changed!
					foreach ($optionChanges as $k => $option)
					{
						if (trim($option) === '')
						{
							continue;
						}

						// Still exists?
						if (in_array($option, $newOptions))
						{
							$takenKeys[] = $k;
						}
					}

					// Finally - have we renamed it - or is it really gone?
					foreach ($optionChanges as $k => $option)
					{
						// Just been renamed?
						if (in_array($k, $takenKeys))
						{
							continue;
						}

						if (empty($newOptions[$k]))
						{
							continue;
						}

						updateRenamedProfileField($k, $newOptions, $context['field']['colname'], $option);
					}
				}

				// @todo Maybe we should adjust based on new text length limits?

				// And finally update an existing field
				$field_data = [
					'field_length' => $field_length,
					'show_reg' => $show_reg,
					'show_display' => $show_display,
					'show_memberlist' => $show_memberlist,
					'private' => $private,
					'active' => $active,
					'can_search' => $can_search,
					'bbc' => $bbc,
					'current_field' => $context['fid'],
					'field_name' => $this->_req->post->field_name,
					'field_desc' => $this->_req->post->field_desc,
					'field_type' => $this->_req->post->field_type,
					'field_options' => $field_options,
					'show_profile' => $show_profile,
					'default_value' => $default,
					'mask' => $mask,
					'enclose' => $enclose,
					'placement' => $placement,
					'rows' => $rows,
					'cols' => $cols,
				];

				updateProfileField($field_data);

				// Just clean up any old selects - these are a pain!
				if (($this->_req->post->field_type == 'select' || $this->_req->post->field_type == 'radio') && !empty($newOptions))
				{
					deleteOldProfileFieldSelects($newOptions, $context['field']['colname']);
				}
			}
		}
		// Deleting?
		elseif (isset($this->_req->post->delete) && $context['field']['colname'])
		{
			checkSession();
			validateToken('admin-ecp');

			// Delete the old data first, then the field.
			deleteProfileFieldUserData($context['field']['colname']);
			deleteProfileField($context['fid']);
		}

		// Rebuild display cache etc.
		if (isset($this->_req->post->delete) || isset($this->_req->post->save) || isset($this->_req->post->onoff))
		{
			checkSession();

			// Update the display cache
			updateDisplayCache();
			redirectexit('action=admin;area=featuresettings;sa=profile');
		}

		createToken('admin-ecp');
	}

	/**
	 * Editing personal messages settings
	 *
	 * - Accessed with ?action=admin;area=featuresettings;sa=pmsettings
	 *
	 * @event integrate_save_pmsettings_settings
	 */
	public function action_pmsettings(): void
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_pmSettings());

		require_once(SUBSDIR . '/PersonalMessage.subs.php');
		Txt::load('ManageMembers');

		$context['pm_limits'] = loadPMLimits();

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			require_once(SUBSDIR . '/Membergroups.subs.php');
			foreach ($context['pm_limits'] as $group_id => $group)
			{
				if (!isset($this->_req->post->group[$group_id]))
				{
					continue;
				}

				if ($this->_req->post->group[$group_id] == $group['max_messages'])
				{
					continue;
				}

				updateMembergroupProperties(['current_group' => $group_id, 'max_messages' => $this->_req->post->group[$group_id]]);
			}

			call_integration_hook('integrate_save_pmsettings_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=pmsettings');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'pmsettings', 'save']);
		$context['settings_title'] = $txt['personal_messages'];

		$settingsForm->prepare();
	}

	/**
	 * Return pm settings.
	 *
	 * - Used in admin center search and settings form
	 *
	 * @event integrate_modify_pmsettings_settings Adds / Modifies PM Settings
	 */
	private function _pmSettings()
	{
		global $txt;

		$config_vars = [
			// Reporting of personal messages?
			['check', 'enableReportPM'],
			// Inline permissions.
			['permissions', 'pm_send'],
			// PM Settings
			['title', 'antispam_PM'],
			'pm1' => ['int', 'max_pm_recipients', 'postinput' => $txt['max_pm_recipients_note']],
			'pm2' => ['int', 'pm_posts_verification', 'postinput' => $txt['pm_posts_verification_note']],
			'pm3' => ['int', 'pm_posts_per_hour', 'postinput' => $txt['pm_posts_per_hour_note']],
			['title', 'membergroups_max_messages'],
			['desc', 'membergroups_max_messages_desc'],
			['callback', 'pm_limits'],
		];

		call_integration_hook('integrate_modify_pmsettings_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Public method to return the basic settings, used for admin search
	 */
	public function basicSettings_search()
	{
		return $this->_basicSettings();
	}

	/**
	 * Public method to return the layout settings, used for admin search
	 */
	public function layoutSettings_search()
	{
		return $this->_layoutSettings();
	}

	/**
	 * Public method to return the karma settings, used for admin search
	 */
	public function karmaSettings_search()
	{
		global $modSettings;

		// Karma - On or off?
		if (empty($modSettings['karmaMode']))
		{
			return ['check', 'dummy_karma'];
		}

		return $this->_karmaSettings();
	}

	/**
	 * Public method to return the likes settings, used for admin search
	 */
	public function likesSettings_search()
	{
		global $modSettings;

		// Likes - On or off?
		if (empty($modSettings['enable_likes']))
		{
			return ['check', 'dummy_likes'];
		}

		return $this->_likesSettings();
	}

	/**
	 * Public method to return the mention settings, used for admin search
	 */
	public function mentionSettings_search()
	{
		return $this->_notificationsSettings();
	}

	/**
	 * Public method to return the signature settings, used for admin search
	 */
	public function signatureSettings_search()
	{
		return $this->_signatureSettings();
	}

	/**
	 * Public method to return the PM settings, used for admin search
	 */
	public function pmSettings_search()
	{
		return $this->_pmSettings();
	}
}
