<?php

/**
 * Handles mentioning for watched topics with new posts.
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
 * Class WatchedTopic
 *
 * Handles notifying of members whose watched topics have received new posts
 *
 */
class Watchedtopic extends AbstractNotificationMessage
{
	/** {@inheritDoc} */
	protected static $_type = 'watchedtopic';

	/**
	 * {@inheritDoc}
	 */
	public function getNotificationBody($lang_data, $members)
	{
		// Email is handled elsewhere, this is only for on-site mentions
		return $this->_getNotificationStrings('', [
			'subject' => static::$_type,
			'body' => static::$_type,
		], $members, $this->_task);
	}

	/**
	 * We only use the mentions interface to allow on-site mention for new posts on watched boards
	 * Email and digests are handled in a separate process due to all the complications
	 */
	public static function isNotAllowed($method)
	{
		// Don't let watched be allowed to use email, that is handled by PostNotificaions
		if (in_array($method, ['email', 'emaildaily', 'emailweekly']))
		{
			return true;
		}

		return false;
	}

	/**
	 * There is no interface for this, its always available as an on-site mention and members set
	 * from profile options notifications
	 *
	 * @return true
	 */
	public static function hasHiddenInterface()
	{
		return true;
	}

	/**
	 * Only called when hasHiddenInterface is true.  Returns the application settings as if
	 * they had been selected in the ACP notifications area
	 *
	 * @return array Returns an array containing the settings.
	 */
	public static function getSettings(): array
	{
		return ['enable' => 1, 'notification' => 1, 'default' => [0 => 'notification']];
	}
}
