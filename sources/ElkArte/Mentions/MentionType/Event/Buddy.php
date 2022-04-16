<?php

/**
 * Handles mentioning of buddies
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
use ElkArte\Mentions\MentionType\CommonConfigTrait;

/**
 * Class Buddy
 *
 * Handles mentioning of buddies
 */
class Buddy extends AbstractEventBoardAccess
{
	use CommonConfigTrait;

	/**
	 * {@inheritdoc }
	 */
	protected static $_type = 'buddy';

	/**
	 * {@inheritdoc }
	 */
	public function view($type, &$mentions)
	{
		foreach ($mentions as $key => $row)
		{
			// To ensure it is not done twice
			if ($row['mention_type'] !== static::$_type)
			{
				continue;
			}

			$mentions[$key]['message'] = $this->_replaceMsg($row);
		}

		return false;
	}
}
