<?php

/**
 * Interface for modules.
 * Actually is just a way to write the hooks method documentation only once.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules;

/**
 * Interface ModuleInterface
 *
 * @package ElkArte\sources\modules
 */
interface ModuleInterface
{
	/**
	 * The method called by the EventManager to find out which trigger the
	 * module is attached to and which parameters the listener wants to receive.
	 *
	 * @param \ElkArte\EventManager $eventsManager an instance of the event manager
	 *
	 * @return mixed[]
	 */
	public static function hooks(\ElkArte\EventManager $eventsManager);
}
