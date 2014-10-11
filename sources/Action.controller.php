<?php

/**
 * Abstract base class for controllers. Holds action_index and pre_dispatch
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Abstract base class for controllers.
 *
 * - Requires a default action handler, action_index().
 * - Defines an empty implementation for pre_dispatch() method.
 */
abstract class Action_Controller
{
	protected $_events = null;
	/**
	 * Default action handler.
	 *
	 * - This will be called by the dispatcher in many cases.
	 * - It may set up a menu, sub-dispatch at its turn to the method matching ?sa= parameter
	 * or simply forward the request to a known default method.
	 */
	abstract public function action_index();

	/**
	 * Called before any other action method in this class.
	 *
	 * - Allows for initializations, such as default values or
	 * loading templates or language files.
	 */
	public function pre_dispatch()
	{
		// By default, do nothing.
		// Sub-classes may implement their prerequisite loading,
		// such as load the template, load the language(s) file(s)
	}

	public function setEventManager($event_manager)
	{
		$this->_events = $event_manager;
		$this->_events->setSource($this);
	}

	public function provideDependencies($deps, &$dependecies)
	{
		foreach ($deps as $dep)
		{
			if (property_exists($this, $dep))
				$dependecies[$dep] = &$this->$dep;
			elseif (property_exists($this, '_' . $dep))
				$dependecies[$dep] = &$this->{'_' . $dep};
		}

		return $dependecies;
	}
}