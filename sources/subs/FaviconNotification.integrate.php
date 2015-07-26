<?php

/**
 * Integration for the notifications in the favicon.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

class Favicon_Notification_Integrate
{
	public static function register()
	{
		global $modSettings;

		if (empty($modSettings['usernotif_favicon_enable']))
			return array();

		// $hook, $function, $file
		return array(
			array('integrate_user_info', 'Favicon_Notification_Integrate::integrate_user_info'),
		);
	}

	public static function settingsRegister()
	{
		// $hook, $function, $file
		return array(
			array('integrate_modify_mention_settings', 'Favicon_Notification_Integrate::integrate_modify_mention_settings'),
			array('integrate_save_modify_mention_settings', 'Favicon_Notification_Integrate::integrate_save_modify_mention_settings'),
		);
	}

	public static function integrate_user_info()
	{
		global $modSettings;

		$favicon = new Favicon_Notification($modSettings);
		$favicon->present();
	}

	public static function integrate_modify_mention_settings(&$config_vars)
	{
		global $modSettings;

		$favicon = new Favicon_Notification($modSettings);

		$favicon_cfg = $favicon->addConfig();
		$config_vars = elk_array_insert($config_vars, $config_vars[1], $favicon_cfg, 'after', false);
	}

	public static function integrate_save_modify_mention_settings()
	{
		global $modSettings;

		$req = HttpReq::instance();

		$favicon = new Favicon_Notification($modSettings);
		$req->post = $favicon->validate($req->post);
	}
}