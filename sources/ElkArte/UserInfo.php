<?php

/**
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * This class holds all the data belonging to a certain member.
 */
class UserInfo extends \ElkArte\ValuesContainer
{
	public function isFirstLogin()
	{
		return $this->data['last_login'] === 0;
	}

	public function canMod($postmodActive)
	{
		return allowedTo('access_mod_center') || ($this->data['is_guest'] === false && ($this->data['mod_cache']['gq'] != '0=1' || $this->data['mod_cache']['bq'] != '0=1' || ($postmodActive && !empty($this->data['mod_cache']['ap']))));
	}
}
