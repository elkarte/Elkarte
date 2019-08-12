<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * This class holds all the data belonging to a certain member.
 */
class User
{
	protected static $instance = null;

	protected static $id = 0;

	protected static $session_password = '';

	public static $info = null;

	/**
	 * Contains the data read from the db.
	 * Read-only by means of ValuesContainerReadOnly
	 * @var ElkArte\ValuesContainerReadOnly
	 */
	public static $settings = null;

	public static function load()
	{
		if (self::$instance === null)
		{
			$db = database();
			$cache = \ElkArte\Cache\Cache::instance();
			$req = request();

			self::$instance = new \ElkArte\UserSettings($db, $cache, $req);
			$already_verified = self::loadFromIntegration();
			self::loadFromCookie($req->user_agent());
			self::$instance->loadUserById(self::$id, $already_verified, self::$session_password);
			self::$settings = self::$instance->getSettings();
			self::$info = self::$instance->getInfo();
		}
	}

	protected static function loadFromIntegration()
	{
		// Check first the integration, then the cookie, and last the session.
		if (count($integration_ids = \ElkArte\Hooks::instance()->hook('integrate_verify_user')) > 0)
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

	protected static function loadFromCookie($user_agent)
	{
		global $cookiename, $modSettings;

		if (empty(self::$id) && isset($_COOKIE[$cookiename]))
		{
			list ($id, self::$session_password) = serializeToJson($_COOKIE[$cookiename], function ($array_from) use ($cookiename) {
				global $modSettings;

				require_once(SUBSDIR . '/Auth.subs.php');
				$_COOKIE[$cookiename] = json_encode($array_from);
				setLoginCookie(60 * $modSettings['cookieTime'], $array_from[0], $array_from[1]);
			});
			self::$id = !empty($id) && strlen(self::$session_password) > 0 ? (int) $id : 0;
		}
		elseif (empty(self::$id) && isset($_SESSION['login_' . $cookiename]) && (!empty($modSettings['disableCheckUA']) || $_SESSION['USER_AGENT'] == $user_agent))
		{
			// @todo Perhaps we can do some more checking on this, such as on the first octet of the IP?
			list ($id, self::$session_password, $login_span) = serializeToJson($_SESSION['login_' . $cookiename], function ($array_from) use ($cookiename) {
				$_SESSION['login_' . $cookiename] = json_encode($array_from);
			});
			self::$id = !empty($id) && strlen(self::$session_password) == 64 && $login_span > time() ? (int) $id : 0;
		}
	}
}
