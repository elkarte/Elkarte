<?php

/**
 * Interface for mentions objects
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

class Buddy_Mention extends Mention_Message_Abstract
{
	protected $_type = 'buddy';

	public function view(&$dependencies)
	{
		$mentions = &$dependencies[1];
		$type = &$dependencies[0];
		foreach ($mentions as $key => $row)
		{
			// To ensure it is not done twice
			if ($row['mention_type'] != $type)
				continue;

			$mentions[$key]['message'] = $this->_replaceMsg($row);
		}

		return false;
	}
}