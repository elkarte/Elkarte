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

use ElkArte\Helper\ValuesContainer;

/**
 * Class SessionIndex
 */
class SessionIndex extends ValuesContainer
{
	/**
	 * Constructor
	 *
	 * @param string $_idx The index of to be used in $_SESSION
	 * @param array|null $data Any array of data used to initialize the object (optional)
	 */
	public function __construct(protected $_idx, $data = null)
	{
		if (!isset($_SESSION[$this->_idx]))
		{
			$_SESSION[$this->_idx] = $data === null ? [] : (array) $data;
		}

		$this->data = &$_SESSION[$this->_idx];
	}
}
