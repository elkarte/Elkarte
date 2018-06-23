<?php

/**
 * Handles notifying users who have had email notifications disabled for failure to deliver
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.4
 *
 */

namespace ElkArte\sources\subs\MentionType;

/**
 * Class Mailfail_Mention
 *
 * Handles notifying users who have had email notifications disabled for failure to deliver
 *
 * @package ElkArte\sources\subs\MentionType
 */
class Mailfail_Mention extends Mention_BoardAccess_Abstract
{
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
