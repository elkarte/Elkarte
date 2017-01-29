<?php

/**
 * Handles the tracking of all registered handling functions for auto suggests
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * Suggest Controller
 */
class Suggest_Controller extends Action_Controller
{
	/**
	 * {@inheritdoc }
	 */
	public function trackStats($action = '')
	{
		return false;
	}

	/**
	 * Intended entry point for this class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Call the right method for this user request.
		$this->action_suggest();
	}

	/**
	 * This keeps track of all registered handling functions for auto suggest
	 * functionality and passes execution to them.
	 *
	 * What it does:
	 *
	 * - Accessed by action=suggest.
	 * - Passes execution to the registered handler (member)
	 * - Allows integration to register additional handlers
	 *
	 * @uses template_generic_xml() in Xml.template
	 */
	public function action_suggest()
	{
		global $context;

		// These are all registered types.
		$searchTypes = array(
			'member' => array(
				'class' => 'Suggest',
				'function' => 'member'
			),
		);

		// Allow integration a way to register their own type
		call_integration_hook('integrate_autosuggest', array(&$searchTypes));

		// Good old session check
		checkSession('post');

		// This requires the XML template
		loadTemplate('Xml');

		// Any parameters?
		$search_param = isset($this->_req->post->search_param) ? json_decode(base64_decode($this->_req->post->search_param), true) : array();

		if (isset($this->_req->post->suggest_type, $this->_req->post->search) && isset($searchTypes[$this->_req->post->suggest_type]))
		{
			// Shortcut
			$currentSearch = $searchTypes[$this->_req->post->suggest_type];

			// Do we have a file to include?
			if (!empty($currentSearch['file']) && file_exists($currentSearch['file']))
				require_once($currentSearch['file']);

			// If it is a class, let's instantiate it
			if (!empty($currentSearch['class']) && class_exists($currentSearch['class']))
			{
				$suggest = new $currentSearch['class']($this->_req->post->search, $search_param);

				// Okay, let's at least assume the method exists... *rolleyes*
				$context['xml_data'] = $suggest->{$currentSearch['function']}();
			}
			// Let's maintain the "namespace" action_suggest_
			elseif (function_exists('action_suggest_' . $currentSearch['function']))
			{
				$function = 'action_suggest_' . $searchTypes[$this->_req->post->suggest_type];
				$context['xml_data'] = $function($this->_req->post->search, $search_param);
			}

			// If we have data, return it
			if (!empty($context['xml_data']))
				$context['sub_template'] = 'generic_xml';
		}
	}
}
