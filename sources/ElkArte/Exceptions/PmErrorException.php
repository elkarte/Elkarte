<?php

/**
 * Extension of the default Exception class to handle controllers redirection.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Exceptions;

/**
 * Class PmErrorException
 *
 * An exception to be cached by the PM controller.
 */
class PmErrorException extends \Exception
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
