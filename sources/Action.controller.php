<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Abstract base class for controllers.
 * Requires a default action handler, action_index().
 * Defines an empty implementation for pre_dispatch()
 * method.
 */
abstract class Action_Controller
{
	/**
	 * Default action handler. This will be called
	 * if no other subaction matches the ?sa= parameter
	 * in the request.
	 */
	abstract public function action_index();

	/**
	 * Called before any other action method in this class.
	 * Allows for initializations, such as default values or
	 * loading templates or language files.
	 */
	public function pre_dispatch()
	{
		// By default, do nothing.
		// Sub-classes may implement their prerequisite loading,
		// such as load the template, load the language(s) file(s)
	}
}