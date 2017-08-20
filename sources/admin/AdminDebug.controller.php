<?php

/**
 * Functions concerned with viewing queries, and is used for debugging.
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
 * Admin class for interfacing with the debug function viewquery
 */
class AdminDebug_Controller extends Action_Controller
{
	/**
	 * {@inheritdoc }
	 */
	public function trackStats($action = '')
	{
		return false;
	}

	/**
	 * Main dispatcher.
	 *
	 * @see Action_Controller::action_index()
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
			throw new Elk_Exception('no_access', false);

		// Don't allow except for administrators.
		isAllowedTo('admin_forum');

		$debug = Debug::instance();

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
		$layers = Template_Layers::getInstance();
		$layers->removeAll();
		$layers->add('html');
		loadTemplate('Admin');

		$context['sub_template'] = 'viewquery';
		$context['queries_data'] = $debug->viewQueries($query_id);
	}

	/**
	 * Get admin information from the database.
	 * Accessed by ?action=viewadminfile.
	 *
	 * @deprecated since 1.1 - the action has been removed
	 */
	public function action_viewadminfile()
	{
		throw new Elk_Exception('no_access', false);
	}
}
