<?php

/**
 * Handles permissions on using notification methods for Mailfail
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
trait MailfailConfigTrait
{
	/**
	 * {@inheritdoc }
	 */
	public static function isBlocklisted($method)
	{
		return in_array($method, ['email', 'emaildaily', 'emailweekly']);
	}

	/**
	 * {@inheritdoc }
	 */
	public static function canUse()
	{
		return true;
	}
}
