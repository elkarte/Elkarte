<?php

/**
 * This file contains the post integration of mentions.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Mentions;

/**
 * Class \ElkArte\Modules\Mentions\AbstractMentions
 *
 * @package Mentions
 */
abstract class AbstractMentions extends \ElkArte\Modules\AbstractModule
{
	/**
	 * Based on the $action returns the enabled mention types to register to the
	 * event manager.
	 *
	 * @param string $action
	 * @param \ElkArte\EventManager $eventsManager
	 * @global $modSettings
	 */
	protected static function registerHooks($action, \ElkArte\EventManager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['mentions_enabled']))
		{
			$mentions = explode(',', $modSettings['enabled_mentions']);

			foreach ($mentions as $mention)
			{
				$class = '\\ElkArte\\Mentions\\MentionType\\Event\\' . ucfirst($mention);
				$hooks = $class::getEvents($action);

				foreach ($hooks as $method => $dependencies)
				{
					$eventsManager->register($method, array($method, array($class, $action . '_' . $method), $dependencies));
				}
			}
		}
	}
}
