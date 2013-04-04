<?php
/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

/**
 * Action class defines an action with its associated sub-actions.
 * Object-oriented controllers (with sub-actions) uses it to set
 * their action-subaction arrays, and have it call the right
 * function or method handlers.
 *
 * Replaces the sub-actions arrays in every dispatching function.
 * (the $subactions = ... etc, and calls for $_REQUEST['sa'])
 *
 */
class Action
{
	/**
	 * Array of sub-actions.
	 * The accepted array format is:
	 *  - 'sub_action name' => 'function name', or
	 *  - 'sub_action name' => array ('function' => 'function name'), or
	 *  - 'sub_action name' => array (
	 *  	'controller' => 'controller name',
	 *  	'function' => 'method name'), or
	 *  - 'sub_action name' => array ('controller object, i.e. $this', 'method name'), or
	 *  - 'sub_action name' => array (
	 *  	'file' => 'file name',
	 *		'dir' => 'controller file location', if not set ADMINDIR is assumed
	 *  	'controller' => 'controller name',
	 *  	'function' => 'method name')
	 * @var array
	 */
	protected $_subActions;

	/**
	 * Initialize the instance with an array of sub-actions.
	 * Sub-actions have to be in the format expected for Action::_subActions array,
	 * indexed by sa.
	 *
	 * @param array $subactions
	 */
	function initialize ($subactions)
	{
		$this->_subActions = array();

		if (!is_array($subactions))
			$subactions = array($subactions);

		$this->_subActions = $subactions;
	}

	/**
	 * Call the function or method which corresponds to the given $sa parameter.
	 * Must be a valid index in the _subActions array.
	 *
	 * @param string $sa
	 */
	function dispatch($sa)
	{
		// for our sanity...
		if (!key_exists($sa, $this->_subActions) || !is_array($this->_subActions[$sa]))
		{
			// send an error and get out of here
			fatal_lang_error('error_sa_not_set');
		}

		$subAction = $this->_subActions[$sa];

		if (isset($subAction['permission']))
			isAllowedTo($subAction['permission']);

		// is it in a file we need to load?
		if (isset($subAction['file']))
		{
			if (isset($subAction['dir']))
				require_once($subAction['dir'] . '/' . $subAction['file']);
			else
				require_once(ADMINDIR . '/' . $subAction['file']);

			// a brand new controller... so be it.
			if (isset($subAction['controller']))
			{
				// 'controller'->'function'
				$controller_name = $subAction['controller'];
				$controller = new $controller_name();
				$controller->{$subAction['function']}();
			}
			elseif (isset($subAction['function']))
			{
				// this is just a good ole' function
				$subAction['function']();
			}
		}
		else
		{
			// we still want to know if it's OOP or not. For debugging purposes. :P
			if (isset($subAction['controller']))
			{
				// an OOP controller, call it over
				$subAction['controller']->{$subAction['function']}();
			}
			elseif (is_array($subAction) && !isset($subAction['function']))
			{
				// an OOP controller, without explicit 'controller' index, lazy!
				$controller = $subAction[0];
				$controller->{$subAction[1]}();
			}
			else
			{
				// a function
				if (isset($subAction['function']))
					$subAction['function']();
				else
					$subAction();
			}
		}
	}

	/**
	 * Security check: verify that the user has the permission to perform the given action.
	 * Verifies if the user has the permission set for the given action.
	 * Return true if no permission was set for the action.
	 * Results in a fatal_lang_error() if the user doesn't have permission,
	 * or this instance wasn't initialized, or the action cannot be found in it.
	 *
	 * @param string $sa
	 */
	function isAllowedTo($sa)
	{
		if (is_array($this->_subActions) && key_exists($sa, $this->_subActions))
		{
			if (isset($this->_subActions[$sa]['permission']))
				isAllowedTo($this->_subActions[$sa]['permission']);
			return true;
		}

		// can't let you continue, sorry.
		fatal_lang_error('error_sa_not_set');

		// I said... can't.
		trigger_error('No access...', E_USER_ERROR);
	}
}