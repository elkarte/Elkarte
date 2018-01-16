<?php

/**
 * This file contains the integration of mentions into Display_Controller.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

/**
 * Class Mentions_Display_Module
 * 
 * @package Mentions
 */
class Mentions_Display_Module extends Mentions_Module_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\Event_Manager $eventsManager)
	{
		self::registerHooks('display', $eventsManager);

		return array();
	}
}