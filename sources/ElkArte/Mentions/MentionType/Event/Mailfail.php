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

namespace ElkArte\Mentions\MentionType\Event;

use ElkArte\Mentions\MentionType\AbstractEventBoardAccess;

/**
 * Class MailfailMention
 *
 * Handles notifying users who have had email notifications disabled for failure to deliver
 */
class Mailfail extends AbstractEventBoardAccess
{
	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'mailfail';
}
