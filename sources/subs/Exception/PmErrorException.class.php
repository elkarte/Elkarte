<?php

/**
 * Extension of the default Exception class to handle controllers redirection.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * Class Pm_Error_Exception
 *
 * An exception to be cached by the PM controller.
 */
class Pm_Error_Exception extends Exception
{
	/**
	 * @var int[]
	 */
	public $recipientList;

	/**
	 * @var array|string[]
	 */
	public $namedRecipientList;

	/**
	 * @var \ElkArte\ValuesContainer
	 */
	public $msgOptions;

	/**
	 * Redefine the initialization.
	 * Do note that parent::__construct() is not called.
	 *
	 * @param array $recipientList Array of members ID separated into 'to' and 'bcc'
	 * @param \ElkArte\ValuesContainer $msgOptions Some values for common
	 * @param string[] $namedRecipientList Array of member names separated into 'to' and 'bcc'
	 *  this argument is deprecated
	 */
	public function __construct($recipientList, $msgOptions, $namedRecipientList = array())
	{
		$this->recipientList = $recipientList;
		$this->msgOptions = $msgOptions;
		$this->namedRecipientList = $namedRecipientList;
	}
}