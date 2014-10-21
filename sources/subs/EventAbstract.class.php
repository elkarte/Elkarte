<?php

/**
 * Each event triggers "something", beucase I'm not good at finding names,
 * these "somethings" are called events as well...
 * This is a little confusing, I know.
 * Anyway this file contains an abstract class with some common methods for
 * all the "somethings" that are executed when an event is fired.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 alpha 1
 *
 */

abstract class Event_Abstract
{
	protected $_hook = null;

	public function setHook($hook)
	{
		$this->_hook = $hook;
	}
}