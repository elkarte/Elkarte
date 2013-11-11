<?php

/**
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
 * @version 1.0 Alpha
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

	/**
	 * Provides a list of possible SMF and ElkArte versions to use in emulation
	 *
	 * @return string
	 */
	public function versions()
	{
		$xml_data = array(
			'items' => array(
				'identifier' => 'item',
				'children' => array(),
			),
		);

		$versions = array(
			'SMF 1.1',
			'SMF 1.1.1',
			'SMF 1.1.2',
			'SMF 1.1.3',
			'SMF 1.1.4',
			'SMF 1.1.5',
			'SMF 1.1.6',
			'SMF 1.1.7',
			'SMF 1.1.8',
			'SMF 1.1.9',
			'SMF 1.1.10',
			'SMF 1.1.11',
			'SMF 1.1.12',
			'SMF 1.1.13',
			'SMF 1.1.14',
			'SMF 1.1.15',
			'SMF 1.1.16',
			'SMF 2.0 beta 1',
			'SMF 2.0 beta 1.2',
			'SMF 2.0 beta 2',
			'SMF 2.0 beta 3',
			'SMF 2.0 RC 1',
			'SMF 2.0 RC 1.2',
			'SMF 2.0 RC 2',
			'SMF 2.0 RC 3',
			'SMF 2.0 RC 4',
			'SMF 2.0 RC 5',
			'SMF 2.0',
			'SMF 2.0.1',
			'SMF 2.0.2',
			'SMF 2.0.3',
			'SMF 2.0.4',
			'ElkArte 1.0',
		);

		foreach ($versions as $id => $version)
		{
			if (strpos($version, strtoupper($_REQUEST['search'])) !== false)
				$xml_data['items']['children'][] = array(
					'attributes' => array(
						'id' => $id,
					),
					'value' => $version,
				);
		}

		return $xml_data;
	}
}