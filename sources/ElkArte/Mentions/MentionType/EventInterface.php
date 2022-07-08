<?php

/**
 * Interface for mentions objects
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions\MentionType;

/**
 * Interface \ElkArte\Mentions\MentionType\EventInterface
 */
interface EventInterface
{
	/**
	 * This static function is used to obtain the events to register to a controller.
	 *
	 * @param string $controller The name of the controller initializing the system
	 */
	public static function getEvents($controller);

	/**
	 * Returns the modules to enable when turning on the mention.
	 *
	 * @param string[] $modules An empty array, or array of active modules
	 *                 in the form array('module' => array('controller'))
	 * @return string[] Array of modules to activate on controllers in the form:
	 *                  array('module' => array('controller'))
	 */
	public static function getModules($modules);

	/**
	 * Used by \ElkArte\Controller\Mentions to filter the mentions to display in the list.
	 *
	 * @param string $type
	 * @param array $mentions
	 */
// 	public function view($type, &$mentions);

	/**
	 * Provides a list of methods that should not be used by this mention type.
	 *
	 * @param string $method the Notifier method that is being considered
	 *
	 * @return bool
	 */
	public static function isBlocklisted($method);

	/**
	 * If needed checks for permissions to use this specific notification
	 *
	 * @return bool
	 */
	public static function canUse();
}
