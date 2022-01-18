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

use ElkArte\Mentions\MentionType\AbstractNotificationBoardAccess;
use ElkArte\Mentions\MentionType\MailfailConfigTrait;

/**
 * Class Mailfail
 *
 * Handles notifying users who have had email notifications disabled for failure to deliver
 */
class Mailfail extends AbstractNotificationBoardAccess
{
	use MailfailConfigTrait;

	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'mailfail';

	/**
	 * {@inheritdoc }
	 * No need to send emails, because we have just disabled email notifications
	 * for this user.
	 */
	public function getNotificationBody($lang_data, $members)
	{
		return $this->_getNotificationStrings('', array('subject' => static::$_type, 'body' => static::$_type), $members, $this->_task);
	}
}
