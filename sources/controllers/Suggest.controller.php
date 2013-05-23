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
 * This file contains those functions specific to the editing box and is
 * generally used for WYSIWYG type functionality.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class Suggest_Controller
{
	/**
	 * This keeps track of all registered handling functions for auto suggest
	 *  functionality and passes execution to them.
	 * Accessed by action=suggest.
	 *
	 * @param bool $checkRegistered = null
	 */
	function action_suggest($checkRegistered = null)
	{
		global $context;

		// These are all registered types.
		$searchTypes = array(
			'member' => 'member',
			'versions' => 'versions',
		);

		call_integration_hook('integrate_autosuggest', array(&$searchTypes));

		// If we're just checking the callback function is registered return true or false.
		if ($checkRegistered != null)
			return isset($searchTypes[$checkRegistered]) && function_exists('action_suggest_' . $checkRegistered);

		checkSession('get');
		loadTemplate('Xml');

		// Any parameters?
		$context['search_param'] = isset($_REQUEST['search_param']) ? unserialize(base64_decode($_REQUEST['search_param'])) : array();

		if (isset($_REQUEST['suggest_type'], $_REQUEST['search']) && isset($searchTypes[$_REQUEST['suggest_type']]))
		{
			$function = 'action_suggest_' . $searchTypes[$_REQUEST['suggest_type']];
			$context['sub_template'] = 'generic_xml';
			$context['xml_data'] = $this->$function();
		}
	}

	/**
	 * Search for a member - by real_name or member_name by default.
	 *
	 * @return string
	 */
	function action_suggest_member()
	{
		global $user_info, $txt, $context;

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
	function action_suggest_versions()
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
			'ELKARTE 1.0',
		);

		foreach ($versions as $id => $version)
			if (strpos($version, strtoupper($_REQUEST['search'])) !== false)
				$xml_data['items']['children'][] = array(
					'attributes' => array(
						'id' => $id,
					),
					'value' => $version,
				);

		return $xml_data;
	}
}
