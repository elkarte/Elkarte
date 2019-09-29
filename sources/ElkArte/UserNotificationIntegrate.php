<?php

/**
 * Integration for display notifications to the user.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class UserNotificationIntegrate
 *
 * @package ElkArte
 */
class UserNotificationIntegrate
{
	/**
	 * Dynamically registers the hooks for the feature.
	 */
	public static function register()
	{
		global $modSettings;

		if (empty($modSettings['usernotif_favicon_enable']) && empty($modSettings['usernotif_desktop_enable']))
		{
			return array();
		}

		// $hook, $function, $file
		return array(
			array('integrate_load_theme', '\\ElkArte\\UserNotificationIntegrate::integrate_load_theme'),
		);
	}

	/**
	 * Dynamically registers the admin panel hooks for the feature.
	 */
	public static function settingsRegister()
	{
		// $hook, $function, $file
		return array(
			array('integrate_modify_mention_settings', '\\ElkArte\\UserNotificationIntegrate::integrate_modify_mention_settings'),
			array('integrate_save_modify_mention_settings', '\\ElkArte\\UserNotificationIntegrate::integrate_save_modify_mention_settings'),
		);
	}

	/**
	 * Adds the relevant javascript code when loading the page.
	 */
	public static function integrate_load_theme()
	{
		if (User::$info->is_guest)
		{
			return;
		}

		$notification = new UserNotification(database(), User::$info);
		$notification->present();
	}

	/**
	 * Adds the settings to the admin page.
	 *
	 * @param mixed[] $config_vars
	 */
	public static function integrate_modify_mention_settings(&$config_vars)
	{
		$notification = new UserNotification(database(), User::$info);

		$notification_cfg = $notification->addConfig();
		$config_vars = elk_array_insert($config_vars, $config_vars[1], $notification_cfg, 'after', false);
	}

	/**
	 * Does some magic when settings are saved.
	 */
	public static function integrate_save_modify_mention_settings()
	{
		$req = HttpReq::instance();

		$notification = new UserNotification(database(), User::$info);
		$req->post = $notification->validate($req->post);
	}
}
