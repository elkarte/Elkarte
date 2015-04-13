<?php

/**
 * Functions concerned with viewing queries, and is used for debugging.
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
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Admin class for interfacing with the debug function viewquery
 */
class AdminDebug_Controller extends Action_Controller
{
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
	 * What this does:
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
		if ($db_show_debug !== true || !isset($_SESSION['debug']))
			Errors::fatal_lang_error('no_access', false);

		// Don't allow except for administrators.
		isAllowedTo('admin_forum');

		$debug = Debug::get();
		// If we're just hiding/showing, do it now.
		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'hide')
		{
			$debug->toggleViewQueries();

			if (strpos($_SESSION['old_url'], 'action=viewquery') !== false)
				redirectexit();
			else
				redirectexit($_SESSION['old_url']);
		}

		$query_id = isset($_REQUEST['qq']) ? (int) $_REQUEST['qq'] - 1 : -1;

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
	 * @deprecated since 1.1 - the action has been removeds
	 */
	public function action_viewadminfile()
	{
		Errors::fatal_lang_error('no_access', false);
	}
}