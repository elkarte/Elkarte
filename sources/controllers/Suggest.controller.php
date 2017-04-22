<?php

/**
 * Handles the tracking of all registered handling functions for auto suggests
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0.10
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Suggest Controller
 */
class Suggest_Controller extends Action_Controller
{
	/**
	 * Intended entry point for this class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Call the right method for this user request.
	}

	/**
	 * This keeps track of all registered handling functions for auto suggest
	 * functionality and passes execution to them.
	 * Accessed by action=suggest.
	 * @uses Xml template
	 */
	public function action_suggest()
	{
		global $context;

		// These are all registered types.
		$searchTypes = array(
			'member' => array(
				'file' => SUBSDIR . '/Suggest.class.php',
				'class' => 'Suggest',
				'function' => 'member'
			),
		);

		call_integration_hook('integrate_autosuggest', array(&$searchTypes));

		checkSession('get');
		loadTemplate('Xml');

		// Any parameters?
		$context['search_param'] = isset($_REQUEST['search_param']) ? json_decode(base64_decode($_REQUEST['search_param']), true) : array();

		if (isset($_REQUEST['suggest_type'], $_REQUEST['search']) && isset($searchTypes[$_REQUEST['suggest_type']]))
		{
			// Shortcut
			$currentSearch = $searchTypes[$_REQUEST['suggest_type']];

			// Do we have a file to include?
			if (!empty($currentSearch['file']) && file_exists($currentSearch['file']))
				require_once($currentSearch['file']);

			// If it is a class, let's instantiate it
			if (!empty($currentSearch['class']) && class_exists($currentSearch['class']))
			{
				$suggest = new $currentSearch['class'];

				// Okay, let's at least assume the method exists... *rolleyes*
				$context['xml_data'] = $suggest->{$currentSearch['function']}();
			}
			// Let's maintain the "namespace" action_suggest_
			elseif (function_exists('action_suggest_' . $currentSearch['function']))
			{
				$function = 'action_suggest_' . $searchTypes[$_REQUEST['suggest_type']];
				$context['xml_data'] = $function();
			}

			if (!empty($context['xml_data']))
				$context['sub_template'] = 'generic_xml';
		}
	}
}