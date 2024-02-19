<?php

/**
 * Handles notifying users who have had email notifications disabled for failure to deliver
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions\MentionType\Notification;

use ElkArte\Mentions\MentionType\AbstractNotificationMessage;

/**
 * Class Mailfail
 *
 * Handles notifying users who have had email notifications disabled for failure to deliver
 */
class Mailfail extends AbstractNotificationMessage
{
	/** {@inheritDoc} */
	protected static $_type = 'mailfail';

	/**
	 * {@inheritDoc}
	 * No need to send emails, because we have just disabled email notifications for this user.
	 */
	public function getNotificationBody($lang_data, $members)
	{
		return $this->_getNotificationStrings('', [
			'subject' => static::$_type,
			'body' => static::$_type],
			$members, $this->_task);
	}

	public static function isNotAllowed($method)
	{
		// Don't let mailfail be allowed to send email.
		if ($method === 'email' || $method === 'emaildaily' || $method === 'emailweekly')
		{
			return true;
		}

		return false;
	}
}
