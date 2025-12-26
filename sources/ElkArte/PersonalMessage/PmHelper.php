<?php

/**
 * This file contains common functions and constants for personal messages.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\PersonalMessage;

use ElkArte\User;

/**
 * Class PmHelper
 * Common functions and constants for personal messages
 */
class PmHelper
{
	/** @var int display_mode key is as follows */
	public const DISPLAY_ALL_AT_ONCE = 0;
	public const DISPLAY_ONE_AT_TIME = 1;
	public const DISPLAY_AS_CONVERSATION = 2;

	/**s
	 * Returns the current PM display mode
	 *
	 * @return int
	 */
	public static function getDisplayMode()
	{
		return (int) User::$settings['pm_prefs'] & 3;
	}
}
