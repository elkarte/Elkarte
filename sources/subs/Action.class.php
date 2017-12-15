<?php

/**
 * Defines an action with its associated sub-actions
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

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
	 * Array of sub-actions.
	 * The accepted array format is:
	 *    'sub_action name' => 'function name',
	 *  or
	 *    'sub_action name' => array(
	 *    'function' => 'function name'),
	 *  or
	 *    'sub_action name' => array(
	 *        'controller' => 'controller name',
	 *        'function' => 'method name',
	 *        'enabled' => true/false,
	 *        'permission' => area),
	 *  or
	 *    'sub_action name' => array(
	 *        'controller object, i.e. $this',
	 *        'method name',
	 *        'enabled' => true/false
	 *        'permission' => area),
	 *  or
	 *    'sub_action name' => array(
	 *        'file' => 'file name',
	 *        'dir' => 'controller file location', if not set ADMINDIR is assumed
	 *        'controller' => 'controller name',
	 *        'function' => 'method name',
	 *        'enabled' => true/false,
	 *        'permission' => area)
	 */

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

	/** @var HttpReq Access to post/get data */
	protected $req;

	/**
	 * Constructor!
	 *
	 * @param string $name   Hook name
	 * @param        HttpReq Access to post/get data
	 */
	public function __construct(string $name = null, HttpReq $req = null)
	{
		$this->_name = $name;
		$this->req = $req ?: HttpReq::instance();
	}

	/**
	 * Initialize the instance with an array of sub-actions.
	 *
	 * What it does:
	 *
	 * - Sub-actions have to be in the format expected for Action::_subActions array,
	 * indexed by sa.
	 *
	 * @param mixed[] $subActions   array of known subactions
	 * @param string  $default      default action if unknown sa is requested
	 * @param string  $requestParam default key to check request value, defaults to sa
	 *
	 * @return string
	 */
	public function initialize(array $subActions, string $default = null, string $requestParam = 'sa'): string
	{
		if ($this->_name !== null)
			call_integration_hook('integrate_sa_' . $this->_name, [&$subActions])

		$this->_subActions = array_filter(
			$subActions,
			function ($subAction)
			{
				return !empty($subAction['enabled']);
			}
		);

			$this->_default = $default ?: key($this->_subActions);

		return $this->req->getQuery($requestParam, 'trim|strval', $this->_default);
	}

	/**
	 * Call the function or method for the selected subaction.
	 *
	 * Both the controller and the method are set up in the subactions array. If a controller
	 * is not specified, the function is assumed to be a regular callable.
	 *
	 * @param string $sub_id a valid index in the subactions array
	 */
	public function dispatch(string $sub_id): void
	{
		$subAction = $this->_subActions[$sub_id] ?? $this->_default;
		$this->isAllowedTo($sub_id);

		// Start off by assuming that this is a callable of some kind.
		$call = [$subAction];

		// Calling a method within a controller?
		if (isset($subAction['controller']))
		{
			// Instance of a class
			if (is_object($subAction['controller']))
			{
				$controller = $subAction['controller'];
			}
			else
			{
				// 'controller' => 'ManageAttachments_Controller'
				// 'function' => 'action_avatars'
				$controller = new $subAction['controller'](new Event_Manager());

				// always set up the environment
				$controller->pre_dispatch();
			}

			// Modify the call accordingly
			$call = [$controller, $subAction['function']];
		}
		elseif (isset($subAction['function']))
		{
			// This is just a good ole' function
			$call = $subAction['function'];
		}

		call_user_func($call);
	}

	/**
	 * Security check: verify that the user has the permission to perform the given action.
	 *
	 * What it does:
	 *
	 * - Verifies if the user has the permission set for the given action.
	 * - Return true if no permission was set for the action.
	 * - Results in a fatal_lang_error() if the user doesn't have permission,
	 * or this instance was not initialized, or the action cannot be found in it.
	 *
	 * @param string $sub_id The sub action
	 *
	 * @return bool
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
