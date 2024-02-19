<?php

/**
 * Extension of the default Exception class to handle controllers redirection.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Exceptions;

use ElkArte\ValuesContainer;

/**
 * Class PmErrorException
 *
 * An exception to be cached by the PM controller.
 */
class PmErrorException extends \Exception
{
	/**
	 * Redefine the initialization.
	 * Do note that parent::__construct() is NOT called.
	 *
	 * @param array $recipientList Array of members ID separated into 'to' and 'bcc'
	 * @param ValuesContainer $msgOptions Some values for common
	 * @param string[] $namedRecipientList Array of member names separated into 'to' and 'bcc'
	 *  this argument is deprecated
	 */
	public function __construct(public $recipientList, public $msgOptions, public $namedRecipientList = [])
	{
	}
}
