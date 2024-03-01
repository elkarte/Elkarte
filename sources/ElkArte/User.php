<?php

/**
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

namespace ElkArte;

use ElkArte\Cache\Cache;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Helper\ValuesContainerReadOnly;

/**
 * This class holds all the data belonging to a certain member.
 */
class User
{
	/** @var ValuesContainer Contains data regarding the user in a form that may be useful in the code Basically the former $user_info */
	public static $info;

	/** @var ValuesContainerReadOnly Contains the data read from the db. Read-only by means of ValuesContainerReadOnly */
	public static $settings;

	/** @var UserSettings The user object */
	protected static $instance;

	/** @var int The user id */
	protected static $id = 0;

	/** @var string The hashed password read from the cookies */
	protected static $session_password = '';

	/**
	 * Load all the important user information.
	 *
	 * @param bool $compat_mode if true sets the deprecated $user_info global
	 * @throws \Exception
	 */
	public static function load($compat_mode = false)
	{
		if (self::$instance === null)
		{
			$db = database();
			$cache = Cache::instance();
			$req = request();

			self::$instance = new UserSettingsLoader($db, $cache, $req);
			$already_verified = self::loadFromIntegration();
			self::loadFromCookie($req->user_agent());
			self::$instance->loadUserById(self::$id, $already_verified, self::$session_password);
			self::$settings = self::$instance->getSettings();
			self::$info = self::$instance->getInfo();
			if ($compat_mode)
			{
				global $user_info;
				$user_info = User::$info;
			}
		}
	}

	/**
	 * Tests any hook set to integrate_verify_user to set users
	 * according to alternative validations
	 *
	 * @event integrate_verify_user allow for integration to verify a user
	 */
	protected static function loadFromIntegration()
	{
		// Check first the integration, then the cookie, and last the session.
		if (count($integration_ids = Hooks::instance()->hook('integrate_verify_user')) > 0)
		{
			foreach ($integration_ids as $integration_id)
			{
				$integration_id = (int) $integration_id;
				if ($integration_id > 0)
				{
					self::$id = $integration_id;

					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Reads data from the cookie to load the user identity
	 *
	 * @param string $user_agent the Browser user agent, used to do some checkes
	 *               based on the session data to reduce spamming and hacking
	 */
	protected static function loadFromCookie($user_agent)
	{
		global $cookiename, $modSettings;

		if (empty(self::$id) && isset($_COOKIE[$cookiename]))
		{
			[$id, self::$session_password] = serializeToJson($_COOKIE[$cookiename], static function ($array_from) use ($cookiename) {
				global $modSettings;
				require_once(SUBSDIR . '/Auth.subs.php');
				$_COOKIE[$cookiename] = json_encode($array_from);
				setLoginCookie(60 * $modSettings['cookieTime'], $array_from[0], $array_from[1]);
			});

			self::$id = !empty($id) && self::$session_password !== '' ? (int) $id : 0;
		}
		elseif (empty(self::$id) && isset($_SESSION['login_' . $cookiename]) && (!empty($modSettings['disableCheckUA']) || (!empty($_SESSION['USER_AGENT']) && $_SESSION['USER_AGENT'] == $user_agent)))
		{
			// @todo Perhaps we can do some more checking on this, such as on the first octet of the IP?
			[$id, self::$session_password, $login_span] = serializeToJson($_SESSION['login_' . $cookiename], static function ($array_from) use ($cookiename) {
				$_SESSION['login_' . $cookiename] = json_encode($array_from);
			});

			self::$id = !empty($id) && strlen(self::$session_password) === 64 && $login_span > time() ? (int) $id : 0;
		}
	}

	/**
	 * Logout by setting user to guest
	 *
	 * @param false $compat_mode
	 */
	public static function logOutUser($compat_mode = false)
	{
		self::$instance->loadUserById(0, true, '');
		self::reloadByUser(self::$instance, $compat_mode);
	}

	/**
	 * Reload all the important user information into the static variables
	 * based on the \ElkArte\UserSettings object passed to it
	 *
	 * @param \ElkArte\UserSettingsLoader $user An user
	 * @param bool $compat_mode if true sets the deprecated $user_info global
	 */
	public static function reloadByUser(UserSettingsLoader $user, $compat_mode = false)
	{
		self::$instance = $user;
		self::$settings = self::$instance->getSettings();
		self::$info = self::$instance->getInfo();
		if ($compat_mode)
		{
			global $user_info;
			$user_info = User::$info;
		}
	}
}
