<?php

/**
 * This file contains the integration of mentions into Display_Controller.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

class Mention_Display_Module extends Mention_Module_Abstract
{
	public static function hooks($eventsManager)
	{
		self::registerHooks('display', $eventsManager);

		return array();
	}
}