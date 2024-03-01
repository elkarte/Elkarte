<?php

/**
 * Handles the tracking of all registered handling functions for auto suggests
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Helper\FileFunctions;
use ElkArte\Helper\SuggestMember;

/**
 * Suggest Controller
 */
class Suggest extends AbstractController
{
	/**
	 * {@inheritDoc}
	 */
	public function trackStats($action = '')
	{
		return false;
	}

	/**
	 * Intended entry point for this class.
	 *
	 * @see AbstractController::action_index
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
			'member' => array('class' => SuggestMember::class, 'function' => 'member'),
		);

		// Allow integration a way to register their own type
		call_integration_hook('integrate_autosuggest', [&$searchTypes]);

		// Good old session check
		checkSession('post');

		// This requires the XML template
		theme()->getTemplates()->load('Xml');
		theme()->getLayers()->removeAll();

		// Any parameters?
		$search_param = isset($this->_req->post->search_param) ? json_decode(base64_decode($this->_req->post->search_param), true) : [];
		$suggest_type = $this->_req->getPost('suggest_type', 'trim');
		$search = $this->_req->getPost('search', 'trim');

		if (isset($suggest_type, $search, $searchTypes[$suggest_type]))
		{
			// Shortcut
			$currentSearch = $searchTypes[$suggest_type];

			// Do we have a file to include?
			if (!empty($currentSearch['file']) && FileFunctions::instance()->fileExists($currentSearch['file']))
			{
				require_once($currentSearch['file']);
			}

			// If a class, let's instantiate it
			if (!empty($currentSearch['class']) && class_exists($currentSearch['class']))
			{
				$suggest = new $currentSearch['class']($search, $search_param);

				// Okay, let's at least assume the method exists... *rolleyes*
				$context['xml_data'] = $suggest->{$currentSearch['function']}();
			}
			// Let's maintain the "namespace" action_suggest_
			elseif (function_exists('action_suggest_' . $currentSearch['function']))
			{
				$function = 'action_suggest_' . $searchTypes[$suggest_type];
				$context['xml_data'] = $function($search, $search_param);
			}

			// If we have data, return it
			if (!empty($context['xml_data']))
			{
				$context['sub_template'] = 'generic_xml';
			}
		}
	}
}
