<?php

/**
 * Handles the notification (or non-notification) of removed likes.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mentions\MentionType\Event;

use ElkArte\Mentions\MentionType\AbstractEventBoardAccess;

/**
 * Class RlikemsgMention
 *
 * Handles the notification (or non-notification) of removed likes.
 */
class Rlikemsg extends AbstractEventBoardAccess
{
	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'rlikemsg';
}
