<?php

/**
 * Handles mentions of likes
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Mentions\MentionType\Event;

use ElkArte\Mentions\MentionType\AbstractEventBoardAccess;

/**
 * Class WatchedTopic
 *
 * Handles viewing of watched topics/board mentions only, email is done separately
 */
class Watchedtopic extends AbstractEventBoardAccess
{
	/** {@inheritDoc} */
	protected static $_type = 'watchedtopic';
}
