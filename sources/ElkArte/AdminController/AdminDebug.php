<?php

/**
 * Functions concerned with viewing queries, and is used for debugging.
 *
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

namespace ElkArte\AdminController;

/**
 * Admin class for interfacing with the debug function viewquery
 */
class AdminDebug extends \ElkArte\AbstractController
{
	/**
	 * {@inheritdoc }
	 */
	public function trackStats()
	{
		return false;
	}

	/**
	 * Main dispatcher.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// what to do first... viewquery! What, it'll work or it won't.
		// $this->action_viewquery();
	}

	/**
	 * Show the database queries for debugging
	 *
	 * What this does:
	 *
	 * - Toggles the session variable 'view_queries'.
	 * - Views a list of queries and analyzes them.
	 * - Requires the admin_forum permission.
	 * - Is accessed via ?action=viewquery.
	 * - Strings in this function have not been internationalized.
	 */
	public function action_viewquery()
	{
		global $context, $db_show_debug;

		// We should have debug mode enabled, as well as something to display!
		if ($db_show_debug !== true || !isset($this->_req->session->debug))
			throw new \ElkArte\Exceptions\Exception('no_access', false);

		// Don't allow except for administrators.
		isAllowedTo('admin_forum');

		$debug = \ElkArte\Debug::instance();

		// If we're just hiding/showing, do it now.
		if (isset($this->_req->query->sa) && $this->_req->query->sa === 'hide')
		{
			$debug->toggleViewQueries();

			if (strpos($this->_req->session->old_url, 'action=viewquery') !== false)
				redirectexit();
			else
				redirectexit($this->_req->session->old_url);
		}

		// Looking at a specific query?
		$query_id = $this->_req->getQuery('qq', 'intval');
		$query_id = $query_id === null ? -1 : $query_id - 1;

		// Just to stay on the safe side, better remove any layer and add back only html
		$layers = theme()->getLayers();
		$layers->removeAll();
		$layers->add('html');
		theme()->getTemplates()->load('Admin');

		$context['sub_template'] = 'viewquery';
		$context['queries_data'] = $debug->viewQueries($query_id);
	}
}
