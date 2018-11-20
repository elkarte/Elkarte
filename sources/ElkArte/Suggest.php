<?php

/**
 * Functions to search for a member by real name or member name, invoked
 * via xml form requests
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Suggesting names (basically a wrapper for getMember)
 */
class Suggest
{
	/**
	 * What we are going to search for
	 * @var string
	 */
	private $_search;

	/**
	 * Any special parameters for the search
	 * @var
	 */
	private $_params;

	/**
	 * @param $search
	 * @param params
	 */
	public function __construct($search, $params)
	{
		$this->_search = trim(\ElkArte\Util::strtolower($search)) . '*';
		$this->_params = $params;
	}

	/**
	 * Search for a member - by real_name or member_name by default.
	 *
	 * @return array
	 */
	public function member()
	{
		global $user_info;

		// Escape the search string
		$this->_search = strtr($this->_search, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

		require_once(SUBSDIR . '/Members.subs.php');

		// Find the member.
		$xml_data = getMember($this->_search, !empty($this->_params['buddies']) ? $user_info['buddies'] : array());

		return $xml_data;
	}
}
