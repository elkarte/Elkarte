<?php

/**
 * The purpose of this file is... errors. (hard to guess, I guess?)  It takes
 * care of logging, error messages, error handling, database errors, and
 * error log administration.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
 *
 */

use ElkArte\Errors\Errors as E;

/**
 * Class to handle all forum errors and exceptions
 */
class Errors
{
	/**
	 * Retrieve the sole instance of this class.
	 *
	 * @return Errors
	 */
	public static function instance()
	{
		return E::instance();
	}
}
