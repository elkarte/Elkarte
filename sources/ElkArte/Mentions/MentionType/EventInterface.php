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
	 * @param mixed[] $mentions
	 */
	public function view($type, &$mentions);

	/**
	 * Inserts a new mention into the database.
	 * Checks if the mention already exists (in any status) to prevent any duplicates
	 *
	 * @package Mentions
	 * @param int $member_from the id of the member mentioning
	 * @param int[] $members_to an array of ids of the members mentioned
	 * @param int $target the id of the target involved in the mention
	 * @param string|null $time optional value to set the time of the mention, defaults to now
	 * @param int|null $status optional value to set a status, defaults to 0
	 * @param bool|null $is_accessible optional if the mention is accessible to the user
	 *
	 * @return int[] An array of members id
	 */
	public function insert($member_from, $members_to, $target, $time = null, $status = null, $is_accessible = null);
}
