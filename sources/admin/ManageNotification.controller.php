<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Notifications administration controller.
 * This class allows to modify notification settings for the forum.
 */
class ManageDrafts_Controller extends Action_Controller
{
	/**
	 * Notifications settings form
	 * @var Settings_Form
	 */
	protected $_notificationSettings;

	/**
	 * Default method.
	 */
	public function action_index()
	{
		isAllowedTo('admin_forum');
		loadLanguage('Notification');

		// We're working with settings here.
		require_once(SUBSDIR . '/Settings.class.php');

		$this->action_notificationSettings_display();
	}

	/**
	 * Modify any setting related to notifications.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=notificaions
	 *
	 * @uses Admin template, show_settings sub-template.
	 */
	public function action_notificationSettings_display()
	{
		global $context, $scripturl;

		// initialize the form
		$this->_initNotificationSettingsForm();
		$config_vars = $this->_notificationSettings->settings();

		// Saving the settings?
		if (isset($_GET['save']))
		{
			checkSession();
			Settings_Form::save_db($config_vars);

			redirectexit('action=admin;area=notification;sa=settings');
		}

		// Prepare the settings for display
		$context['post_url'] = $scripturl . '?action=admin;area=notification;save;sa=settings';
		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Retrieve and return all admin settings for notifications.
	 */
	private function _initNotificationSettingsForm()
	{
		global $txt, $context;

		// instantiate the form
		$this->_notificationSettings = new Settings_Form();

		// The notification settings
		$config_vars = array(
			array('title', 'notification_settings'),
			array('check', 'enable_notifications'),
		);

		// Some context stuff
		$context['page_title'] = $txt['notification_settings'];
		$context['sub_template'] = 'show_settings';

		return $this->_notificationSettings->settings($config_vars);
	}

	/**
	 * Retrieve and return all admin settings for the calendar.
	 * @todo is this still need by admin search ???
	 */
	public function settings()
	{
		$config_vars = array(
			array('title', 'notification_settings'),
			array('check', 'enable_notifications'),
		);

		return $config_vars;
	}
}