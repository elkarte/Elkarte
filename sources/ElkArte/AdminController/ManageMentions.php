<?php

/**
 * Manage mentions (notifications) settings.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;
use ElkArte\Mentions\MentionType\AbstractNotificationMessage;
use ElkArte\Notifications\Notifications;
use ElkArte\SettingsForm\SettingsForm;

/**
 * Mentions administration controller.
 * This class allows modifying mentions and notification settings for the forum.
 */
class ManageMentions extends AbstractController
{
	/**
	 * Pre-dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		// We need this in a few places, so it's easier to have it loaded here
		require_once(SUBSDIR . '/ManageFeatures.subs.php');

		Txt::load('Help+ManageSettings+Mentions');
	}

	/**
	 * Default action for this controller.
	 */
	public function action_index()
	{
		$subActions = [
			'mentions' => [$this, 'action_notificationsSettings_display', 'permission' => 'admin_forum'],
		];

		// Set up the action control
		$action = new Action('modify_mentions');

		// There is only one option
		$subAction = $action->initialize($subActions, 'mentions');
		$action->dispatch($subAction);
	}

	/**
	 * Initializes the mentions settings admin page.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=mentions;
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
			toggleTaskStatusByName('user_access_mentions');

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
			redirectexit('action=admin;area=featuresettings;sa=mentions');
		}

		// Prepare the settings for display
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'mentions', 'save']);
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
	 * Public method to return the mention settings, used for admin search
	 */
	public function mentionSettings_search()
	{
		return $this->_notificationsSettings();
	}
}
