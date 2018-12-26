<?php

/**
 * Defines an action with its associated sub-actions
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Action class defines an action with its associated sub-actions.
 * Object-oriented controllers (with sub-actions) uses it to set their action-subaction arrays, and have it call the
 * right function or method handlers.
 *
 * Replaces the sub-actions arrays in every dispatching function.
 * (the $subActions = ... etc, and calls for $_REQUEST['sa'])
 *
 */
class Action
{
	/**
	 * All the subactions we understand
	 * @var array
	 */
	protected $_subActions = [];

	/**
	 * The default subAction.
	 * @var string
	 */
	protected $_default;

	/**
	 * An (unique) id that triggers a hook
	 * @var string
	 */
	protected $_name;

	/** @var \ElkArte\HttpReq Access to post/get data */
	protected $req;

	/**
	 * Constructor!
	 *
	 * @param string  $name Hook name
	 * @param \ElkArte\HttpReq $req  Access to post/get data
	 */
	public function __construct(string $name = '', HttpReq $req = null)
	{
		$this->_name = $name;
		$this->req = $req ?: HttpReq::instance();
	}

	/**
	 * Initialize the instance with an array of sub-actions.
	 *
	 * @param array  $subActions    array of known subactions
	 *
	 *                              The accepted array format is:
	 *                              'sub_action name' => 'function name',
	 *                              or
	 *                              'sub_action name' => array(
	 *                              'function' => 'function name'),
	 *                              or
	 *                              'sub_action name' => array(
	 *                              'controller' => 'controller name',
	 *                              'function' => 'method name',
	 *                              'enabled' => true/false,
	 *                              'permission' => area),
	 *                              or
	 *                              'sub_action name' => array(
	 *                              'controller object, i.e. $this',
	 *                              'method name',
	 *                              'enabled' => true/false
	 *                              'permission' => area),
	 *                              or
	 *                              'sub_action name' => array(
	 *                              'controller' => 'controller name',
	 *                              'function' => 'method name',
	 *                              'enabled' => true/false,
	 *                              'permission' => area)
	 *
	 *                              If `enabled` is not present, it is aassumed to be true.
	 *
	 * @param string $default       default action if unknown sa is requested
	 * @param string $requestParam  key to check HTTP GET value, defaults to `sa`
	 *
	 * @event  integrate_sa_ the name specified in the constructor is appended to this
	 *
	 * @return string
	 */
	public function initialize(array $subActions, string $default = '', string $requestParam = 'sa'): string
	{
		if ($this->_name !== '')
		{
			call_integration_hook('integrate_sa_' . $this->_name, [&$subActions]);
		}

		$this->_subActions = array_filter(
			$subActions,
			function ($subAction)
			{
				return
					!isset($subAction['enabled'])
					|| isset($subAction['enabled'])
					&& $subAction['enabled'] == true;
			}
		);

		$this->_default = $default ?: key($this->_subActions);

		return $this->req->getQuery($requestParam, 'trim|strval', $this->_default);
	}

	/**
	 * Call the function or method for the selected subaction.
	 *
	 * Both the controller and the method are set up in the subactions array. If a
	 * controller is not specified, the function is assumed to be a regular callable.
	 *
	 * @param string $sub_id a valid index in the subactions array
	 */
	public function dispatch(string $sub_id): void
	{
		$subAction = $this->_subActions[$sub_id] ?? $this->_subActions[$this->_default];
		$this->isAllowedTo($sub_id);

		// Start off by assuming that this is a callable of some kind.
		$call = $subAction;

		if (isset($subAction['function']))
		{
			// This is just a good ole' function
			$call = $subAction['function'];
		}

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

		call_user_func($call);
	}

	/**
	 * Security check: verify that the user has the permission to perform the
	 * given action, and throw an error otherwise.
	 *
	 * @param string $sub_id The sub action
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function isAllowedTo(string $sub_id): bool
	{
		if (isset($this->_subActions[$sub_id], $this->_subActions[$sub_id]['permission']))
		{
			isAllowedTo($this->_subActions[$sub_id]['permission']);
		}

		return true;
	}
}
