<?php

/**
 * Handles common mention/event traits
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
 */
trait CommonConfigTrait
{
	/**
	 * {@inheritdoc }
	 */
	public static function isBlocklisted($method)
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
