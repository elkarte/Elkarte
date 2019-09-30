<?php

/**
 * This module is attached to the Post action to enable mentions on it.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules\Mentions;

use ElkArte\EventManager;

/**
 * Class \ElkArte\Modules\Mentions\Post
 *
 * @package Mentions
 */
class Post extends AbstractMentions
{
	/**
	 * {@inheritdoc }
	 */
	public static function hooks(EventManager $eventsManager)
	{
		self::registerHooks('post', $eventsManager);

		return array();
	}
}
