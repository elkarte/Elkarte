<?php

/**
 * This file contains the post integration of mentions.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Modules\Mentions;

use ElkArte\EventManager;
use ElkArte\Modules\AbstractModule;

/**
 * Class \ElkArte\Modules\Mentions\AbstractMentions
 *
 * @package Mentions
 */
abstract class AbstractMentions extends AbstractModule
{
	/**
	 * Based on the $action returns the enabled mention types to register to the
	 * event manager.
	 *
	 * @param string $action
	 * @param EventManager $eventsManager
	 * @global $modSettings
	 */
	protected static function registerHooks($action, EventManager $eventsManager): void
	{
		global $modSettings;

		if (!empty($modSettings['mentions_enabled']))
		{
			require_once(SUBSDIR . '/Notification.subs.php');
			$mentions = getEnabledNotifications();

			foreach ($mentions as $mention)
			{
				$class = '\\ElkArte\\Mentions\\MentionType\\Event\\' . ucfirst($mention);
				if (!is_callable([$class, 'getEvents']))
				{
					continue;
				}

				$hooks = $class::getEvents($action);

				foreach ($hooks as $method => $dependencies)
				{
					$eventsManager->register($method, [$method, [$class, $action . '_' . $method], $dependencies]);
				}
			}
		}
	}
}
