<?php

/**
 * Most of the "models" require some common stuff (like a constructor).
 * Here it is.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0.3
 *
 */

namespace ElkArte\sources;

if (!defined('ELK'))
	die('No access...');

/**
 * Implementing this interface will make controllers usable as a front page
 * replacing the classic board index.
 */
interface Frontpage_Interface
{

	/**
	 * Used to attach to the integrate_action_frontpage hook, to change
	 * the default action.
	 *
	 * @param string[] $default_action
	 */
	public static function frontPageHook(&$default_action);

	/**
	 * Used to defin the parameters the controller may need for the front page
	 * action to work (e.g. specify a topic ID).
	 *
	 * @return mixed[]
	 */
	public static function frontPageOptions();

	/**
	 * Used to defin the parameters the controller may need for the front page
	 * action to work (e.g. specify a topic ID).
	 *
	 * @param Object $post
	 */
	public static function validateFrontPageOptions($post);
}