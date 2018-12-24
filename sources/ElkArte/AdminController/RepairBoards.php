<?php

/**
 * This is here for the "repair any errors" feature in the admin center.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

/**
 * Repair boards controller handles a special admin action:
 * boards and categories attempt to repair, from maintenance.
 */
class RepairBoards extends \ElkArte\AbstractController
{
	/**
	 * Default method.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		isAllowedTo('admin_forum');

		// we do nothing... our one and only method does. :P
		$this->action_repairboards();
	}

	/**
	 * Finds or repairs errors in the database to fix possible problems.
	 *
	 * - Requires the admin_forum permission.
	 * - Accessed by ?action=admin;area=repairboards.
	 *
	 * @uses raw_data sub-template.
	 */
	public function action_repairboards()
	{
		global $txt, $context, $db_show_debug;

		isAllowedTo('admin_forum');

		require_once(SUBSDIR . '/RepairBoards.subs.php');

		// Try secure more memory.
		detectServer()->setMemoryLimit('128M');

		// Print out the top of the webpage.
		$context['page_title'] = $txt['admin_repair'];
		$context['sub_template'] = 'repair_boards';
		$context[$context['admin_menu_name']]['current_subsection'] = 'general';

		// Load the language file.
		theme()->getTemplates()->loadLanguageFile('Maintenance');

		// Make sure the tabs stay nice.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['maintain_title'],
			'help' => '',
			'description' => $txt['maintain_info'],
			'tabs' => array(),
		);

		// Start displaying errors without fixing them.
		if (isset($this->_req->query->fixErrors))
			checkSession('get');

		// Giant if/else. The first displays the forum errors if a variable is not set and asks
		// if you would like to continue, the other fixes the errors.
		if (!isset($this->_req->query->fixErrors))
		{
			$context['error_search'] = true;
			$context['repair_errors'] = array();

			// Logging may cause session issues with many queries
			$old_db_show_debug = $db_show_debug;
			$db_show_debug = false;

			$context['to_fix'] = findForumErrors();

			// Restore previous debug state
			$db_show_debug = $old_db_show_debug;

			if (!empty($context['to_fix']))
			{
				$_SESSION['repairboards_to_fix'] = $context['to_fix'];
				$_SESSION['repairboards_to_fix2'] = null;

				if (empty($context['repair_errors']))
					$context['repair_errors'][] = '???';
			}
		}
		else
		{
			$context['error_search'] = false;
			$context['to_fix'] = isset($this->_req->session->repairboards_to_fix) ? $this->_req->session->repairboards_to_fix : array();

			require_once(SUBSDIR . '/Boards.subs.php');

			// Logging may cause session issues with many queries
			$old_db_show_debug = $db_show_debug;
			$db_show_debug = false;

			// Actually do the fix.
			findForumErrors(true);

			// Restore previous debug state
			$db_show_debug = $old_db_show_debug;

			// Note that we've changed everything possible ;)
			updateSettings(array(
				'settings_updated' => time(),
			));

			require_once(SUBSDIR . '/Messages.subs.php');
			updateMessageStats();

			require_once(SUBSDIR . '/Topic.subs.php');
			updateTopicStats();

			updateSettings(array(
				'calendar_updated' => time(),
			));

			if (!empty($_SESSION['redirect_to_recount']))
			{
				$context['redirect_to_recount'] = true;
				$_SESSION['redirect_to_recount'] = null;
			}

			$_SESSION['repairboards_to_fix'] = null;
			$_SESSION['repairboards_to_fix2'] = null;
		}
	}
}
