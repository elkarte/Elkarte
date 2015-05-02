<?php

/**
 * This file contains the integration of mentions into Display_Controller.
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

class Mentions_Display_Module extends Mentions_Module_Abstract
{
	public static function hooks($eventsManager)
	{
		self::registerHooks('display', $eventsManager);

		return array();
	}
}