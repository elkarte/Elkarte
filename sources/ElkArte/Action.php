<?php

/**
 * Defines an action with its associated sub-actions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use ElkArte\Helper\HttpReq;

/**
 * Action class defines an action with its associated sub-actions.
 *
 * Object-oriented controllers (with sub-actions) use it to set their action-subaction arrays, and have it call the
 * right function or method handlers.
 *
 * Replaces the sub-actions arrays in every dispatching function.
 * (the $subActions = ... etc, and calls for $_REQUEST['sa'])
 *
 */
class Action
{
	/** @var array All the subactions we understand */
	protected $_subActions = [];

	/** @var string The default subAction. */
	protected $_default;

	/** @var string A (unique !!) id that triggers a hook */
	protected $_name;

	/** @var HttpReq Access to post/get data */
	protected $req;

	/**
	 * Constructor!
	 *
	 * @param string|null $name Hook name
	 * @param HttpReq $req Access to post/get data
	 */
	public function __construct(string $name = null, $req = null)
	{
		$this->_name = $name;
		$this->req = $req ?: HttpReq::instance();
	}

	/**
	 * Initialize the instance with an array of sub-actions.
	 *
	 * - Sets a valid default action if none is supplied.
	 * - Returns the cleaned subaction or the default action if the subaction is not valid / available
	 * - Calls generic integration hook integrate_sa_XYZ where XYZ is the optional named passed via new Action('XYZ')
	 *
	 * @param array $subActions array of known subactions
	 * The accepted array format is:
	 *   'sub_action name' => 'function name'
	 *  or
	 *   'sub_action name' => array('function' => 'function name')
	 *  or
	 *   'sub_action name' => array(
	 *      'controller' => 'controller name',
	 *      'function' => 'method name',
	 *      'enabled' => true/false,
	 *      'permission' => area),
	 *  or
	 *   'sub_action name' => array(
	 *      'controller object, i.e. $this',
	 *      'method name',
	 *      'enabled' => true/false
	 *      'permission' => area),
	 *  or
	 *   'sub_action name' => array(
	 *      'controller' => 'controller name',
	 *      'function' => 'method name',
	 *      'enabled' => true/false,
	 *      'permission' => area)
	 *
	 *  If `enabled` is not present, it is assumed to be true.
	 *
	 * @param string $default default action if an unknown sa is requested
	 * @param string $requestParam key to check for the HTTP GET value, defaults to `sa`
	 *
	 * @event  integrate_sa_ the name specified in the constructor is appended to this
	 *
	 * @return string the valid subaction
	 */
	public function initialize(array $subActions, string $default = '', string $requestParam = 'sa'): string
	{
		// Controller action initialized as new Action('xyz'), then call xyz integration hook
		if ($this->_name !== null)
		{
			call_integration_hook('integrate_sa_' . $this->_name, [&$subActions]);
		}

		$this->_subActions = array_filter(
			$subActions,
			static function ($subAction) {
				if (isset($subAction['disabled']) && ($subAction['disabled'] === true || $subAction['disabled'] === 'true'))
				{
					return false;
				}

				return !(isset($subAction['enabled']) && ($subAction['enabled'] === false || $subAction['enabled'] === 'false'));
			}
		);

		$this->_default = $default ?: key($this->_subActions);

		$subAction = $this->req->getRequest($requestParam, 'trim|strval', $this->_default);

		return isset($this->_subActions[$subAction]) ? $subAction : $this->_default;
	}

	/**
	 * Call the function or method for the selected subaction.
	 *
	 * - Both the controller and the method are set up in the subactions array.
	 * - If a controller is not specified, the function is assumed to be a regular callable.
	 * - Checks on permission of the $sub_id IF a permission area/check was passed.
	 *
	 * @param string $sub_id a valid index in the subactions array
	 */
	public function dispatch(string $sub_id): void
	{
		$subAction = $this->_subActions[$sub_id] ?? $this->_subActions[$this->_default];
		$this->isAllowedTo($sub_id);

		// Start off by assuming that this is a callable of some kind.
		$call = $subAction['function'] ?? $subAction;

		// Calling a method within a controller?
		if (isset($subAction['controller'], $subAction['function']))
		{
			// Instance of a class
			if (is_object($subAction['controller']))
			{
				$controller = $subAction['controller'];
			}
			else
			{
				// Pointer to a controller to load
				$controller = new $subAction['controller'](new EventManager());

				// always set up the environment
				$controller->getHook();
				$controller->setUser(User::$info);
				$controller->pre_dispatch();
			}

			// Modify the call accordingly
			$call = [$controller, $subAction['function']];
		}
		// Callable directly within the array? Discard invalid entries.
		elseif (isset($subAction[0], $subAction[1]))
		{
			$call = [$subAction[0], $subAction[1]];
		}

		$call();
	}

	/**
	 * Security check: verify that the user has the permission to perform the
	 * given action, and throw an error otherwise.
	 *
	 * @param string $sub_id The sub action
	 */
	protected function isAllowedTo(string $sub_id): bool
	{
		if (isset($this->_subActions[$sub_id]['permission']))
		{
			isAllowedTo($this->_subActions[$sub_id]['permission']);
		}

		return true;
	}
}
