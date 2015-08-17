<?php

/**
 * Integration for display notifications to the user.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

class User_Notification_Integrate
{
	/**
	 * Dynamically registers the hooks for the feature.
	 */
	public static function register()
	{
		global $modSettings;

		if (empty($modSettings['usernotif_favicon_enable']) && empty($modSettings['usernotif_desktop_enable']))
			return array();

		// $hook, $function, $file
		return array(
			array('integrate_user_info', 'User_Notification_Integrate::integrate_user_info'),
		);
	}

	/**
	 * Dynamically registers the admin panel hooks for the feature.
	 */
	public static function settingsRegister()
	{
		// $hook, $function, $file
		return array(
			array('integrate_modify_mention_settings', 'User_Notification_Integrate::integrate_modify_mention_settings'),
			array('integrate_save_modify_mention_settings', 'User_Notification_Integrate::integrate_save_modify_mention_settings'),
		);
	}

	/**
	 * Adds the relevant javascript code when loading the page.
	 */
	public static function integrate_user_info()
	{
		global $modSettings;

		$notification = new User_Notification($modSettings);
		$notification->present();
	}

	/**
	 * Adds the settings to the admin page.
	 */
	public static function integrate_modify_mention_settings(&$config_vars)
	{
		global $modSettings;

		$notification = new User_Notification($modSettings);

		$notification_cfg = $notification->addConfig();
		$config_vars = elk_array_insert($config_vars, $config_vars[1], $notification_cfg, 'after', false);
	}

	/**
	 * Does some magic when settings are saved.
	 */
	public static function integrate_save_modify_mention_settings()
	{
		global $modSettings;

		$req = HttpReq::instance();

		$notification = new User_Notification($modSettings);
		$req->post = $notification->validate($req->post);
	}
}