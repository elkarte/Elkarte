<?php

/**
 * 
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Sessions;

use \ElkArte\ValuesContainer;

/**
 *
 */
class SessionIndex extends ValuesContainer
{
	protected $_idx = '';

	/**
	 * Constructor
	 *
	 * @param string $idx The index of to be used in $_SESSION
	 * @param mixed[]|null $data Any array of data used to initialize the object (optional)
	 */
	public function __construct($idx, $data = null)
	{
		$this->_idx = $idx;

		if (!isset($_SESSION[$this->_idx]))
		{
			$_SESSION[$this->_idx] = $data === null ? array() : (array) $data;
		}

		$this->data = &$_SESSION[$this->_idx];
	}
}
