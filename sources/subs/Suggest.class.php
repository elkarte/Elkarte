<?php

/**
 * Functions to seach for a member by real name or member name, invoked
 * via xml form requests
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Suggest Controler
 */
class Suggest
{
	/**
	 * Search for a member - by real_name or member_name by default.
	 *
	 * @return string
	 */
	public function member()
	{
		global $user_info, $context;

		$search = trim(Util::strtolower($_REQUEST['search'])) . '*';
		$search = strtr($search, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

		require_once(SUBSDIR . '/Members.subs.php');

		// Find the member.
		$xml_data = getMember($search, !empty($context['search_param']['buddies']) ? $user_info['buddies'] : array());

		return $xml_data;
	}
}