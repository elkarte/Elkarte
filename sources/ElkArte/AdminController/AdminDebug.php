<?php

/**
 * Functions concerned with viewing queries and is used for debugging.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Debug;
use ElkArte\Exceptions\Exception;

/**
 * Admin class for interfacing with the debug function viewquery
 */
class AdminDebug extends AbstractController
{
	/**
	 * {@inheritDoc}
	 */
	public function trackStats($action = '')
	{
		return false;
	}

	/**
	 * Main dispatcher.
	 *
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		$subActions = [
			'view' => [$this, 'action_viewquery', 'permission' => 'admin_forum'],
			'hide' => [$this, 'action_hidequery', 'permission' => 'admin_forum'],
		];

		// Set up the action handler
		$action = new Action('viewquery');
		$subAction = $action->initialize($subActions, 'view');

		// Call the action
		$action->dispatch($subAction);
	}

	/**
	 * Toggle the viewing of queries.
	 */
	public function action_hidequery(): void
	{
		global $db_show_debug;

		// We should have debug mode enabled, as well as something to display!
		if ($db_show_debug !== true || !isset($_SESSION['debug']))
		{
			throw new Exception('no_access', false);
		}

		// Don't allow except for administrators.
		isAllowedTo('admin_forum');

		$debug = Debug::instance();
		$debug->toggleViewQueries();

		if (str_contains($_SESSION['old_url'], 'action=viewquery'))
		{
			redirectexit();
		}

		redirectexit($_SESSION['old_url']);
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
	public function action_viewquery(): void
	{
		global $context, $db_show_debug;

		// We should have debug mode enabled, as well as something to display!
		if ($db_show_debug !== true || !isset($_SESSION['debug']))
		{
			throw new Exception('no_access', false);
		}

		// Don't allow except for administrators.
		isAllowedTo('admin_forum');

		$debug = Debug::instance();

		// Looking at a specific query?
		$query_id = $this->_req->getQuery('qq', 'intval', 0);
		--$query_id;

		// Just to stay on the safe side, better remove any layer and add back only HTML
		$layers = theme()->getLayers();
		$layers->removeAll();
		$layers->add('html');
		theme()->getTemplates()->load('Admin');

		$context['sub_template'] = 'viewquery';
		$context['queries_data'] = $debug->viewQueries($query_id);
	}
}
