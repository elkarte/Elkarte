<?php

/**
 * Handles mentions of likes
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
 * Class Likemsg
 *
 * Handles mentions of likes
 */
class Likemsg extends AbstractEventBoardAccess
{
	/** {@inheritDoc} */
	protected static $_type = 'likemsg';
}
