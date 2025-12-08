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
 * Class WatchedBoard
 *
 * Handles viewing of watched topics/board mentions only, email is done separately
 */
class Watchedboard extends AbstractEventBoardAccess
{
	/** {@inheritDoc} */
	protected static $_type = 'watchedboard';
}
