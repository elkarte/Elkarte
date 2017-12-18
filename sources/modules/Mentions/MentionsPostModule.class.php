<?php

/**
 * This module is attached to the Post action to enable mentions on it.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0-dev
 *
 */

/**
 * Class Mentions_Post_Module
 *
 * @package Mentions
 */
class Mentions_Post_Module extends Mentions_Module_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		self::registerHooks('post', $eventsManager);

		return array();
	}
}