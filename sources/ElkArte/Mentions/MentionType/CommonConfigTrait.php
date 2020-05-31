<?php

/**
 * Handles mentioning of buddies
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions\MentionType;

/**
 * trait CommonConfigTrait
 *
 * Handles mentioning of buddies
 */
trait CommonConfigTrait
{
	/**
	 * {@inheritdoc }
	 */
	public static function isBlacklisted($method)
	{
		return false;
	}

	/**
	 * {@inheritdoc }
	 */
	public static function canUse()
	{
		return true;
	}
}
