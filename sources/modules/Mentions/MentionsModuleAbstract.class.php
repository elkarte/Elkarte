<?php

/**
 * This file contains the post integration of mentions.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

abstract class Mentions_Module_Abstract implements ElkArte\sources\modules\Module_Interface
{
	/**
	 * Based on the $action returns the enabled mention types to register to the
	 * event manager.
	 *
	 * @param string $action
	 * @param \Event_Manager $eventsManager
	 * @global $modSettings
	 */
	protected static function registerHooks($action, \Event_Manager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['mentions_enabled']))
		{
			Elk_Autoloader::getInstance()->register(SUBSDIR . '/MentionType', '\\ElkArte\\sources\\subs\\MentionType');

			$mentions = explode(',', $modSettings['enabled_mentions']);

			foreach ($mentions as $mention)
			{
				$class = '\\ElkArte\\sources\\subs\\MentionType\\' . ucfirst($mention) . '_Mention';
				$hooks = $class::getEvents($action);

				foreach ($hooks as $method => $dependencies)
				{
					$eventsManager->register($method, array($method, array($class, $action . '_' . $method), $dependencies));
				}
			}
		}
	}
}