<?php

/**
 * Implementing this interface will make controllers usable as a front page
 * replacing the classic board index.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Implementing this interface will make controllers usable as a front page
 * replacing the classic board index.
 */
interface FrontpageInterface
{
	/**
	 * Used to attach integrate_action_frontpage hook, to change the default action.
	 *
	 * - e.g. \ElkArte\Hooks::instance()->add('integrate_action_frontpage', 'ControllerName::frontPageHook');
	 *
	 * @param string[] $default_action
	 */
	public static function frontPageHook(&$default_action);

	/**
	 * Used to define the parameters the controller may need for the front page
	 * action to work
	 *
	 * - e.g. specify a topic ID or a board listing
	 *
	 * @return array
	 */
	public static function frontPageOptions();

	/**
	 * Used to define the parameters the controller may need for the front page
	 * action to work
	 *
	 * - e.g. specify a topic ID
	 * - should return true or false based on if its able to show the front page
	 *
	 * @param Object $post
	 */
	public static function validateFrontPageOptions($post);
}
