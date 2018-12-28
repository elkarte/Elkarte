<?php

/**
 * This file contains the integration of mentions into \ElkArte\Controller\Display.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Mentions;

/**
 * Class \ElkArte\Modules\Mentions\Display
 *
 * @package Mentions
 */
class Display extends AbstractMentions
{
	/**
	 * {@inheritdoc }
	 */
	public static function hooks(\ElkArte\EventManager $eventsManager)
	{
		self::registerHooks('display', $eventsManager);

		return array();
	}
}
