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
 * @version 1.0 Release Candidate 1
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
			fatal_lang_error('no_access', false);

		// Don't allow except for administrators.
		isAllowedTo('admin_forum');

		// If we're just hiding/showing, do it now.
		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'hide')
		{
			$_SESSION['view_queries'] = $_SESSION['view_queries'] == 1 ? 0 : 1;

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

		$query_analysis = new Query_Analysis();

		$context['sub_template'] = 'viewquery';
		$context['queries_data'] = array();

		foreach ($_SESSION['debug'] as $q => $query_data)
		{
			$context['queries_data'][$q] = $query_analysis->extractInfo($query_data);

			// Explain the query.
			if ($query_id == $q && $context['queries_data'][$q]['is_select'])
			{
				$context['queries_data'][$q]['explain'] = $query_analysis->doExplain();
			}
		}
	}

	/**
	 * Get admin information from the database.
	 * Accessed by ?action=viewadminfile.
	 */
	public function action_viewadminfile()
	{
		global $modSettings;

		require_once(SUBSDIR . '/AdminDebug.subs.php');

		// Don't allow non-administrators.
		isAllowedTo('admin_forum');

		setMemoryLimit('128M');

		if (empty($_REQUEST['filename']) || !is_string($_REQUEST['filename']))
			fatal_lang_error('no_access', false);

		$file = adminInfoFile($_REQUEST['filename']);

		// @todo Temp
		// Figure out if sesc is still being used.
		if (strpos($file['file_data'], ';sesc=') !== false)
			$file['file_data'] = '
if (!(\'elkForum_sessionvar\' in window))
	window.elkForum_sessionvar = \'sesc\';
' . strtr($file['file_data'], array(';sesc=' => ';\' + window.elkForum_sessionvar + \'='));

		Template_Layers::getInstance()->removeAll();

		// Lets make sure we aren't going to output anything nasty.
		@ob_end_clean();
		if (!empty($modSettings['enableCompressedOutput']))
			ob_start('ob_gzhandler');
		else
			ob_start();

		// Make sure they know what type of file we are.
		header('Content-Type: ' . $file['filetype']);
		echo $file['file_data'];
		obExit(false);
	}
}