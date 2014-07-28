<?php

/**
 * Forum maintenance. Important stuff.
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
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Entry point class for all of the maintance ,routine, members, database,
 * attachments, topics and hooks
 *
 * @package Maintenance
 */
class Maintenance_Controller extends Action_Controller
{
	/**
	 * Main dispatcher, the maintenance access point.
	 *
	 * What it does:
	 * - This, as usual, checks permissions, loads language files,
	 * and forwards to the actual workers.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $txt, $context;

		// You absolutely must be an admin by here!
		isAllowedTo('admin_forum');

		// Need something to talk about?
		loadLanguage('Maintenance');
		loadTemplate('Maintenance');

		// This uses admin tabs - as it should!
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['maintain_title'],
			'class' => 'database',
			'description' => $txt['maintain_info'],
			'tabs' => array(
				'routine' => array(),
				'database' => array(),
				'members' => array(),
				'topics' => array(),
				'topics' => array(),
				'hooks' => array(),
				'attachments' => array('label' => $txt['maintain_sub_attachments']),
			),
		);

		// So many things you can do - but frankly I won't let you - just these!
		$subActions = array(
			'routine' => array(
				'controller' => $this,
				'function' => 'action_routine',
				'activities' => array(
					'version' => 'action_version_display',
					'repair' => 'action_repair_display',
					'recount' => 'action_recount_display',
					'logs' => 'action_logs_display',
					'cleancache' => 'action_cleancache_display',
				),
			),
			'database' => array(
				'controller' => $this,
				'function' => 'action_database',
				'activities' => array(
					'optimize' => 'action_optimize_display',
					'backup' => 'action_backup_display',
					'convertmsgbody' => 'action_convertmsgbody_display',
				),
			),
			'members' => array(
				'controller' => $this,
				'function' => 'action_members',
				'activities' => array(
					'reattribute' => 'action_reattribute_display',
					'purgeinactive' => 'action_purgeinactive_display',
					'recountposts' => 'action_recountposts_display',
				),
			),
			'topics' => array(
				'controller' => $this,
				'function' => 'action_topics',
				'activities' => array(
					'massmove' => 'action_massmove_display',
					'pruneold' => 'action_pruneold_display',
					'olddrafts' => 'action_olddrafts_display',
				),
			),
			'hooks' => array(
				'controller' => $this,
				'function' => 'action_hooks',
			),
			'attachments' => array(
				'file' => 'ManageAttachments.controller.php',
				'controller' => 'ManageAttachments_Controller',
				'function' => 'action_maintenance',
			),
		);

		// Set up the action handeler
		$action = new Action('manage_maintenance');

		// Yep, sub-action time and call integrate_sa_manage_maintenance as well
		$subAction = $action->initialize($subActions, 'routine');

		// Doing something special, does it exist?
		if (isset($_REQUEST['activity']) && isset($subActions[$subAction]['activities'][$_REQUEST['activity']]))
			$activity = $_REQUEST['activity'];

		// Set a few things.
		$context[$context['admin_menu_name']]['current_subsection'] = $subAction;
		$context['page_title'] = $txt['maintain_title'];
		$context['sub_action'] = $subAction;

		// Finally fall through to what we are doing.
		$action->dispatch($subAction);

		// Any special activity defined, then go to it.
		if (isset($activity))
		{
			if (method_exists($this, $subActions[$subAction]['activities'][$activity]))
				$this->{$subActions[$subAction]['activities'][$activity]}();
			else
				$subActions[$subAction]['activities'][$activity]();
		}

		// Create a maintenance token.  Kinda hard to do it any other way.
		createToken('admin-maint');
	}

	/**
	 * Supporting function for the database maintenance area.
	 */
	public function action_database()
	{
		global $context, $db_type, $modSettings, $maintenance;

		// We need this, really..
		require_once(SUBSDIR . '/Maintenance.subs.php');

		// Set up the sub-template
		$context['sub_template'] = 'maintain_database';

		if ($db_type == 'mysql')
		{
			$body_type = fetchBodyType();

			$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';
			$context['convert_to_suggest'] = ($body_type != 'text' && !empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] < 65536);
		}

		// Check few things to give advices before make a backup
		// If safe mod is enable the external tool is *always* the best (and probably the only) solution
		$context['safe_mode_enable'] = @ini_get('safe_mode');

		// This is just a...guess
		$messages = countMessages();

		// 256 is what we use in the backup script
		setMemoryLimit('256M');
		$memory_limit = memoryReturnBytes(ini_get('memory_limit')) / (1024 * 1024);

		// Zip limit is set to more or less 1/4th the size of the available memory * 1500
		// 1500 is an estimate of the number of messages that generates a database of 1 MB (yeah I know IT'S AN ESTIMATION!!!)
		// Why that? Because the only reliable zip package is the one sent out the first time,
		// so when the backup takes 1/5th (just to stay on the safe side) of the memory available
		$zip_limit = $memory_limit * 1500 / 5;

		// Here is more tricky: it depends on many factors, but the main idea is that
		// if it takes "too long" the backup is not reliable. So, I know that on my computer it take
		// 20 minutes to backup 2.5 GB, of course my computer is not representative, so I'll multiply by 4 the time.
		// I would consider "too long" 5 minutes (I know it can be a long time, but let's start with that):
		// 80 minutes for a 2.5 GB and a 5 minutes limit means 160 MB approx
		$plain_limit = 240000;

		// Last thing: are we able to gain time?
		$current_time_limit = ini_get('max_execution_time');
		@set_time_limit(159); //something strange just to be sure
		$new_time_limit = ini_get('max_execution_time');

		$context['use_maintenance'] = 0;

		// External tool if:
		//  * safe_mode enable OR
		//  * cannot change the execution time OR
		//  * cannot reset timeout
		if ($context['safe_mode_enable'] || empty($new_time_limit) || ($current_time_limit == $new_time_limit && !function_exists('apache_reset_timeout')))
			$context['suggested_method'] = 'use_external_tool';
		elseif ($zip_limit < $plain_limit && $messages < $zip_limit)
			$context['suggested_method'] = 'zipped_file';
		elseif ($zip_limit > $plain_limit || ($zip_limit < $plain_limit && $plain_limit < $messages))
		{
			$context['suggested_method'] = 'use_external_tool';
			$context['use_maintenance'] = empty($maintenance) ? 2 : 0;
		}
		else
		{
			$context['use_maintenance'] = 1;
			$context['suggested_method'] = 'plain_text';
		}
	}

	/**
	 * Supporting function for the routine maintenance area.
	 *
	 * @uses sub template maintain_routine, Template file Maintenance
	 */
	public function action_routine()
	{
		global $context, $txt, $scripturl;

		if (isset($_GET['done']) && $_GET['done'] == 'recount')
			$context['maintenance_finished'] = $txt['maintain_recount'];

		// set up the sub-template
		$context['sub_template'] = 'maintain_routine';
		$context['routine_actions'] = array(
			'version' => array(
				'url' => $scripturl . '?action=admin;area=maintain;sa=routine;activity=version',
				'title' => $txt['maintain_version'],
				'description' => $txt['maintain_version_info'],
				'submit' => $txt['maintain_run_now'],
				'hidden' => array(
					'session_var' => 'session_id',
				)
			),
			'repair' => array(
				'url' => $scripturl . '?action=admin;area=repairboards',
				'title' => $txt['maintain_errors'],
				'description' => $txt['maintain_errors_info'],
				'submit' => $txt['maintain_run_now'],
				'hidden' => array(
					'session_var' => 'session_id',
					'admin-maint_token_var' => 'admin-maint_token',
				)
			),
			'recount' => array(
				'url' => $scripturl . '?action=admin;area=maintain;sa=routine;activity=recount',
				'title' => $txt['maintain_recount'],
				'description' => $txt['maintain_recount_info'],
				'submit' => $txt['maintain_run_now'],
				'hidden' => array(
					'session_var' => 'session_id',
					'admin-maint_token_var' => 'admin-maint_token',
				)
			),
			'logs' => array(
				'url' => $scripturl . '?action=admin;area=maintain;sa=routine;activity=logs',
				'title' => $txt['maintain_logs'],
				'description' => $txt['maintain_logs_info'],
				'submit' => $txt['maintain_run_now'],
				'hidden' => array(
					'session_var' => 'session_id',
					'admin-maint_token_var' => 'admin-maint_token',
				)
			),
			'cleancache' => array(
				'url' => $scripturl . '?action=admin;area=maintain;sa=routine;activity=cleancache',
				'title' => $txt['maintain_cache'],
				'description' => $txt['maintain_cache_info'],
				'submit' => $txt['maintain_run_now'],
				'hidden' => array(
					'session_var' => 'session_id',
					'admin-maint_token_var' => 'admin-maint_token',
				)
			),
		);

		call_integration_hook('integrate_routine_maintenance');
	}

	/**
	 * Supporting function for the members maintenance area.
	 */
	public function action_members()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/Membergroups.subs.php');

		// Get all membergroups - for deleting members and the like.
		$context['membergroups'] = getBasicMembergroupData(array('all'));

		// Show that we completed this action
		if (isset($_REQUEST['done']) && $_REQUEST['done'] == 'recountposts')
			$context['maintenance_finished'] = array(
				'errors' => array(sprintf($txt['maintain_done'], $txt['maintain_recountposts'])),
			);

		loadJavascriptFile('suggest.js');

		// Set up the sub-template
		$context['sub_template'] = 'maintain_members';
	}

	/**
	 * Supporting function for the topics maintenance area.
	 */
	public function action_topics()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/Boards.subs.php');

		// Let's load up the boards in case they are useful.
		$context += getBoardList(array('not_redirection' => true));

		if (isset($_GET['done']) && $_GET['done'] == 'purgeold')
			$context['maintenance_finished'] = array(
				'errors' => array(sprintf($txt['maintain_done'], $txt['maintain_old'])),
			);
		elseif (isset($_GET['done']) && $_GET['done'] == 'massmove')
			$context['maintenance_finished'] = array(
				'errors' => array(sprintf($txt['maintain_done'], $txt['move_topics_maintenance'])),
			);

		// Set up the sub-template
		$context['sub_template'] = 'maintain_topics';
	}

	/**
	 * Find and try to fix all errors on the forum.
	 *
	 * - Forwards to repair boards controller.
	 */
	public function action_repair_display()
	{
		// Honestly, this should be done in the sub function.
		validateToken('admin-maint');

		require_once(ADMINDIR . '/RepairBoards.controller.php');

		$controller = new RepairBoards_Controller();
		$controller->action_repairboards();
	}

	/**
	 * Wipes the current cache entries as best it can.
	 *
	 * - This only applies to our own cache entries, opcache and data.
	 * - This action, like other maintenance tasks, may be called automatically
	 * by the task scheduler or manually by the admin in Maintenance area.
	 */
	public function action_cleancache_display()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/Cache.subs.php');

		checkSession();
		validateToken('admin-maint');

		// Just wipe the whole cache directory!
		clean_cache();

		$context['maintenance_finished'] = $txt['maintain_cache'];
	}

	/**
	 * Empties all uninmportant logs.
	 *
	 * - This action may be called periodically, by the tasks scheduler,
	 * or manually by the admin in Maintenance area.
	 */
	public function action_logs_display()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/Maintenance.subs.php');

		checkSession();
		validateToken('admin-maint');

		// Maintenance time was scheduled!
		// When there is no intelligent life on this planet.
		// Apart from me, I mean.
		flushLogTables();

		updateSettings(array('search_pointer' => 0));

		$context['maintenance_finished'] = $txt['maintain_logs'];
	}

	/**
	 * Convert the column "body" of the table {db_prefix}messages from TEXT to
	 * MEDIUMTEXT and vice versa.
	 *
	 * What it does:
	 * - It requires the admin_forum permission.
	 * - This is needed only for MySQL.
	 * - During the convertion from MEDIUMTEXT to TEXT it check if any of the
	 * posts exceed the TEXT length and if so it aborts.
	 * - This action is linked from the maintenance screen (if it's applicable).
	 * - Accessed by ?action=admin;area=maintain;sa=database;activity=convertmsgbody.
	 *
	 * @uses the convert_msgbody sub template of the Admin template.
	 */
	public function action_convertmsgbody_display()
	{
		global $context, $txt, $db_type, $modSettings, $time_start;

		// Show me your badge!
		isAllowedTo('admin_forum');

		if ($db_type != 'mysql')
			return;

		$colData = getMessageTableColumns();
		foreach ($colData as $column)
		{
			if ($column['name'] == 'body')
			{
				$body_type = $column['type'];
				break;
			}
		}

		$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';

		if ($body_type == 'text' || ($body_type != 'text' && isset($_POST['do_conversion'])))
		{
			checkSession();
			validateToken('admin-maint');

			// Make it longer so we can do their limit.
			if ($body_type == 'text')
				resizeMessageTableBody('mediumtext');
			// Shorten the column so we can have a bit (literally per record) less space occupied
			else
				resizeMessageTableBody('text');

			$colData = getMessageTableColumns();
			foreach ($colData as $column)
				if ($column['name'] == 'body')
					$body_type = $column['type'];

			$context['maintenance_finished'] = $txt[$context['convert_to'] . '_title'];
			$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';
			$context['convert_to_suggest'] = ($body_type != 'text' && !empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] < 65536);

			return;
		}
		elseif ($body_type != 'text' && (!isset($_POST['do_conversion']) || isset($_POST['cont'])))
		{
			checkSession();

			if (empty($_REQUEST['start']))
				validateToken('admin-maint');
			else
				validateToken('admin-convertMsg');

			$context['page_title'] = $txt['not_done_title'];
			$context['continue_post_data'] = '';
			$context['continue_countdown'] = 3;
			$context['sub_template'] = 'not_done';
			$increment = 500;
			$id_msg_exceeding = isset($_POST['id_msg_exceeding']) ? explode(',', $_POST['id_msg_exceeding']) : array();

			$max_msgs = countMessages();

			// Try for as much time as possible.
			@set_time_limit(600);

			while ($_REQUEST['start'] < $max_msgs)
			{
				$id_msg_exceeding = detectExceedingMessages($_REQUEST['start'], $increment);

				$_REQUEST['start'] += $increment;

				if (microtime(true) - $time_start > 3)
				{
					createToken('admin-convertMsg');
					$context['continue_post_data'] = '
						<input type="hidden" name="' . $context['admin-convertMsg_token_var'] . '" value="' . $context['admin-convertMsg_token'] . '" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
						<input type="hidden" name="id_msg_exceeding" value="' . implode(',', $id_msg_exceeding) . '" />';
					$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=convertmsgbody;start=' . $_REQUEST['start'];
					$context['continue_percent'] = round(100 * $_REQUEST['start'] / $max_msgs);
					$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';

					return;
				}
			}

			createToken('admin-maint');
			$context['page_title'] = $txt[$context['convert_to'] . '_title'];
			$context['sub_template'] = 'convert_msgbody';

			if (!empty($id_msg_exceeding))
			{
				if (count($id_msg_exceeding) > 100)
				{
					$query_msg = array_slice($id_msg_exceeding, 0, 100);
					$context['exceeding_messages_morethan'] = sprintf($txt['exceeding_messages_morethan'], count($id_msg_exceeding));
				}
				else
					$query_msg = $id_msg_exceeding;

				$context['exceeding_messages'] = getExceedingMessages($query_msg);
			}
		}
	}

	/**
	 * Optimizes all tables in the database and lists how much was saved.
	 *
	 * What it does:
	 * - It requires the admin_forum permission.
	 * - It shows as the maintain_forum admin area.
	 * - It is accessed from ?action=admin;area=maintain;sa=database;activity=optimize.
	 * - It also updates the optimize scheduled task such that the tables are not automatically optimized again too soon.
	 */
	public function action_optimize_display()
	{
		global $txt, $context;

		isAllowedTo('admin_forum');

		// Some validation
		checkSession('post');
		validateToken('admin-maint');

		ignore_user_abort(true);

		require_once(SUBSDIR . '/Maintenance.subs.php');

		$context['page_title'] = $txt['database_optimize'];
		$context['sub_template'] = 'optimize';

		$tables = getElkTables();

		// If there aren't any tables then I believe that would mean the world has exploded...
		$context['num_tables'] = count($tables);
		if ($context['num_tables'] == 0)
			fatal_error('You appear to be running ElkArte in a flat file mode... fantastic!', false);

		// For each table....
		$context['optimized_tables'] = array();
		foreach ($tables as $table)
		{
			// Optimize the table!  We use backticks here because it might be a custom table.
			$data_freed = optimizeTable($table['table_name']);

			if ($data_freed > 0)
				$context['optimized_tables'][] = array(
					'name' => $table['table_name'],
					'data_freed' => $data_freed,
				);
		}

		// Number of tables, etc....
		$txt['database_numb_tables'] = sprintf($txt['database_numb_tables'], $context['num_tables']);
		$context['num_tables_optimized'] = count($context['optimized_tables']);

		// Check that we don't auto optimise again too soon!
		require_once(SUBSDIR . '/ScheduledTasks.subs.php');
		calculateNextTrigger('auto_optimize', true);
	}

	/**
	 * Recount many forum totals that can be recounted automatically without harm.
	 *
	 * What it does:
	 * - it requires the admin_forum permission.
	 * - It shows the maintain_forum admin area.
	 * - The function redirects back to ?action=admin;area=maintain when complete.
	 * - It is accessed via ?action=admin;area=maintain;sa=database;activity=recount.
	 *
	 * Totals recounted:
	 * - fixes for topics with wrong num_replies.
	 * - updates for num_posts and num_topics of all boards.
	 * - recounts personal_messages but not unread_messages.
	 * - repairs messages pointing to boards with topics pointing to other boards.
	 * - updates the last message posted in boards and children.
	 * - updates member count, latest member, topic count, and message count.
	 */
	public function action_recount_display()
	{
		global $txt, $context, $modSettings, $time_start;

		isAllowedTo('admin_forum');
		checkSession();

		// Functions
		require_once(SUBSDIR . '/Maintenance.subs.php');

		// Validate the request or the loop
		if (!isset($_REQUEST['step']))
			validateToken('admin-maint');
		else
			validateToken('admin-boardrecount');

		// For the loop template
		$context['page_title'] = $txt['not_done_title'];
		$context['continue_post_data'] = '';
		$context['continue_countdown'] = 3;
		$context['sub_template'] = 'not_done';

		// Try for as much time as possible.
		@set_time_limit(600);

		// Step the number of topics at a time so things don't time out...
		$max_topics = getMaxTopicID();

		$increment = min(max(50, ceil($max_topics / 4)), 2000);
		if (empty($_REQUEST['start']))
			$_REQUEST['start'] = 0;

		$total_steps = 8;

		// Get each topic with a wrong reply count and fix it - let's just do some at a time, though.
		if (empty($_REQUEST['step']))
		{
			$_REQUEST['step'] = 0;

			while ($_REQUEST['start'] < $max_topics)
			{
				recountApprovedMessages($_REQUEST['start'], $increment);
				recountUnapprovedMessages($_REQUEST['start'], $increment);

				$_REQUEST['start'] += $increment;

				if (microtime(true) - $time_start > 3)
				{
					createToken('admin-boardrecount');
					$context['continue_post_data'] = '
						<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />';
					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=0;start=' . $_REQUEST['start'];
					$context['continue_percent'] = round((100 * $_REQUEST['start'] / $max_topics) / $total_steps);
					$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Update the post count of each board.
		if ($_REQUEST['step'] <= 1)
		{
			if (empty($_REQUEST['start']))
				resetBoardsCounter('num_posts');

			while ($_REQUEST['start'] < $max_topics)
			{
				// Recount the posts
				updateBoardsCounter('posts', $_REQUEST['start'], $increment);
				$_REQUEST['start'] += $increment;

				if (microtime(true) - $time_start > 3)
				{
					createToken('admin-boardrecount');
					$context['continue_post_data'] = '
						<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />';
					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=1;start=' . $_REQUEST['start'];
					$context['continue_percent'] = round((200 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);
					$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Update the topic count of each board.
		if ($_REQUEST['step'] <= 2)
		{
			if (empty($_REQUEST['start']))
				resetBoardsCounter('num_topics');

			while ($_REQUEST['start'] < $max_topics)
			{
				updateBoardsCounter('topics', $_REQUEST['start'], $increment);
				$_REQUEST['start'] += $increment;

				if (microtime(true) - $time_start > 3)
				{
					createToken('admin-boardrecount');
					$context['continue_post_data'] = '
						<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />';
					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=2;start=' . $_REQUEST['start'];
					$context['continue_percent'] = round((300 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);
					$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Update the unapproved post count of each board.
		if ($_REQUEST['step'] <= 3)
		{
			if (empty($_REQUEST['start']))
				resetBoardsCounter('unapproved_posts');

			while ($_REQUEST['start'] < $max_topics)
			{
				updateBoardsCounter('unapproved_posts', $_REQUEST['start'], $increment);

				$_REQUEST['start'] += $increment;

				if (microtime(true) - $time_start > 3)
				{
					createToken('admin-boardrecount');
					$context['continue_post_data'] = '
						<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />';
					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=3;start=' . $_REQUEST['start'];
					$context['continue_percent'] = round((400 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);
					$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Update the unapproved topic count of each board.
		if ($_REQUEST['step'] <= 4)
		{
			if (empty($_REQUEST['start']))
				resetBoardsCounter('unapproved_topics');

			while ($_REQUEST['start'] < $max_topics)
			{
				updateBoardsCounter('unapproved_topics', $_REQUEST['start'], $increment);
				$_REQUEST['start'] += $increment;

				if (microtime(true) - $time_start > 3)
				{
					createToken('admin-boardrecount');
					$context['continue_post_data'] = '
						<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />';
					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=4;start=' . $_REQUEST['start'];
					$context['continue_percent'] = round((500 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);
					$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Get all members with wrong number of personal messages.
		if ($_REQUEST['step'] <= 5)
		{
			updatePersonalMessagesCounter();

			if (microtime(true) - $time_start > 3)
			{
				createToken('admin-boardrecount');
				$context['continue_post_data'] = '
					<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />';
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=0';
				$context['continue_percent'] = round(700 / $total_steps);
				$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';

				return;
			}
		}

		// Any messages pointing to the wrong board?
		if ($_REQUEST['step'] <= 6)
		{
			while ($_REQUEST['start'] < $modSettings['maxMsgID'])
			{
				updateMessagesBoardID($_REQUEST['start'], $increment);

				$_REQUEST['start'] += $increment;

				if (microtime(true) - $time_start > 3)
				{
					createToken('admin-boardrecount');
					$context['continue_post_data'] = '
						<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />';
					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=' . $_REQUEST['start'];
					$context['continue_percent'] = round((700 + 100 * $_REQUEST['start'] / $modSettings['maxMsgID']) / $total_steps);
					$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		updateBoardsLastMessage();

		// Update all the basic statistics.
		updateStats('member');
		updateStats('message');
		updateStats('topic');

		// Finally, update the latest event times.
		require_once(SUBSDIR . '/ScheduledTasks.subs.php');
		calculateNextTrigger();

		redirectexit('action=admin;area=maintain;sa=routine;done=recount');
	}

	/**
	 * Perform a detailed version check.  A very good thing ;).
	 *
	 * What it does
	 * - The function parses the comment headers in all files for their version information,
	 * and outputs that for some javascript to check with simplemachines.org.
	 * - It does not connect directly with elkarte.net, but rather expects the client to.
	 * - It requires the admin_forum permission.
	 * - Uses the view_versions admin area.
	 * - Accessed through ?action=admin;area=maintain;sa=routine;activity=version.
	 *
	 * @uses Admin template, view_versions sub-template.
	 */
	public function action_version_display()
	{
		global $forum_version, $txt, $context, $modSettings;

		isAllowedTo('admin_forum');

		// Call the function that'll get all the version info we need.
		require_once(SUBSDIR . '/Admin.subs.php');
		$versionOptions = array(
			'include_ssi' => true,
			'include_subscriptions' => true,
			'sort_results' => true,
		);
		$version_info = getFileVersions($versionOptions);

		// Add the new info to the template context.
		$context += array(
			'file_versions' => $version_info['file_versions'],
			'file_versions_admin' => $version_info['file_versions_admin'],
			'file_versions_controllers' => $version_info['file_versions_controllers'],
			'file_versions_database' => $version_info['file_versions_database'],
			'file_versions_subs' => $version_info['file_versions_subs'],
			'default_template_versions' => $version_info['default_template_versions'],
			'template_versions' => $version_info['template_versions'],
			'default_language_versions' => $version_info['default_language_versions'],
			'default_known_languages' => array_keys($version_info['default_language_versions']),
		);

		// Make it easier to manage for the template.
		$context['forum_version'] = $forum_version;

		$context['sub_template'] = 'view_versions';
		$context['page_title'] = $txt['admin_version_check'];
		// @deprecated since 1.0 - remember to remove from 1.1 this is here just to avoid errors from not using upgrade.php
		$context['detailed_version_url'] = !empty($modSettings['detailed-version.js']) ? $modSettings['detailed-version.js'] : 'https://elkarte.github.io/Elkarte/site/detailed-version.js';
	}

	/**
	 * Re-attribute posts to the user sent from the maintenance page.
	 */
	public function action_reattribute_display()
	{
		global $context, $txt;

		checkSession();

		// Start by doing some data checking
		require_once(SUBSDIR . '/DataValidator.class.php');
		$validator = new Data_Validator();
		$validator->sanitation_rules(array('posts' => 'empty', 'type' => 'trim', 'from_email' => 'trim', 'from_name' => 'trim', 'to' => 'trim'));
		$validator->validation_rules(array('from_email' => 'valid_email', 'from_name' => 'required', 'to' => 'required', 'type' => 'contains[name,email]'));
		$validator->validate($_POST);

		// Do we have a valid set of options to continue?
		if (($validator->type === 'name' && !empty($validator->from_name)) || ($validator->type === 'email' && !$validator->validation_errors('from_email')))
		{
			// Find the member.
			require_once(SUBSDIR . '/Auth.subs.php');
			$members = findMembers($validator->to);

			// No members, no further
			if (empty($members))
				fatal_lang_error('reattribute_cannot_find_member');

			$memID = array_shift($members);
			$memID = $memID['id'];

			$email = $validator->type == 'email' ? $validator->from_email : '';
			$membername = $validator->type == 'name' ? $validator->from_name : '';

			// Now call the reattribute function.
			require_once(SUBSDIR . '/Members.subs.php');
			reattributePosts($memID, $email, $membername, !$validator->posts);

			$context['maintenance_finished'] = array(
				'errors' => array(sprintf($txt['maintain_done'], $txt['maintain_reattribute_posts'])),
			);
		}
		else
		{
			// Show them the correct error
			if ($validator->type === 'name' && empty($validator->from_name))
				$error = $validator->validation_errors(array('from_name', 'to'));
			else
				$error = $validator->validation_errors(array('from_email', 'to'));

			$context['maintenance_finished'] = array(
				'errors' => $error,
				'type' => 'minor',
			);
		}
	}

	/**
	 * Handling function for the backup stuff.
	 *
	 * - It requires an administrator and the session hash by post.
	 * - This method simply forwards to DumpDatabase2().
	 */
	public function action_backup_display()
	{
		validateToken('admin-maint');

		// Administrators only!
		if (!allowedTo('admin_forum'))
			fatal_lang_error('no_dump_database', 'critical');

		checkSession('post');

		require_once(SOURCEDIR . '/DumpDatabase.php');
		DumpDatabase2();
	}

	/**
	 * Removing old and inactive members.
	 */
	public function action_purgeinactive_display()
	{
		global $context, $txt;

		checkSession();
		validateToken('admin-maint');

		require_once(SUBSDIR . '/DataValidator.class.php');

		// Start with checking and cleaning what was sent
		$validator = new Data_Validator();
		$validator->sanitation_rules(array('maxdays' => 'intval'));
		$validator->validation_rules(array('maxdays' => 'required', 'groups' => 'isarray', 'del_type' => 'required'));

		// Validator says, you can pass or not
		if ($validator->validate($_POST))
		{
			require_once(SUBSDIR . '/Maintenance.subs.php');
			require_once(SUBSDIR . '/Members.subs.php');

			$groups = array();
			foreach ($validator->groups as $id => $dummy)
				$groups[] = (int) $id;

			$time_limit = (time() - ($validator->maxdays * 24 * 3600));
			$members = purgeMembers($validator->type, $groups, $time_limit);
			deleteMembers($members);

			$context['maintenance_finished'] = array(
				'errors' => array(sprintf($txt['maintain_done'], $txt['maintain_members'])),
			);
		}
		else
		{
			$context['maintenance_finished'] = array(
				'errors' => $validator->validation_errors(),
				'type' => 'minor',
			);
		}
	}

	/**
	 * This method takes care of removal of old posts.
	 * They're very very old, perhaps even older.
	 */
	public function action_pruneold_display()
	{
		validateToken('admin-maint');

		require_once(SUBSDIR . '/Topic.subs.php');
		removeOldTopics();
	}

	/**
	 * This method removes old drafts.
	 */
	public function action_olddrafts_display()
	{
		global $context, $txt;

		validateToken('admin-maint');

		require_once(SUBSDIR . '/Drafts.subs.php');
		$drafts = getOldDrafts($_POST['draftdays']);

		// If we have old drafts, remove them
		if (count($drafts) > 0)
			deleteDrafts($drafts, -1, false);

		// Errors?  no errors, only success !
		$context['maintenance_finished'] = array(
			'errors' => array(sprintf($txt['maintain_done'], $txt['maintain_old_drafts'])),
		);
	}

	/**
	 * Moves topics from one board to another.
	 *
	 * @uses not_done template to pause the process.
	 */
	public function action_massmove_display()
	{
		global $context, $txt, $time_start;

		// Only admins.
		isAllowedTo('admin_forum');

		// And valid requests
		checkSession();

		// Set up to the context.
		$context['page_title'] = $txt['not_done_title'];
		$context['continue_countdown'] = 3;
		$context['continue_post_data'] = '';
		$context['continue_get_data'] = '';
		$context['sub_template'] = 'not_done';
		$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

		// First time we do this?
		$id_board_from = isset($_POST['id_board_from']) ? (int) $_POST['id_board_from'] : (int) $_REQUEST['id_board_from'];
		$id_board_to = isset($_POST['id_board_to']) ? (int) $_POST['id_board_to'] : (int) $_REQUEST['id_board_to'];

		// No boards then this is your stop.
		if (empty($id_board_from) || empty($id_board_to))
			return;

		// These will be needed
		require_once(SUBSDIR . '/Maintenance.subs.php');
		require_once(SUBSDIR . '/Topic.subs.php');

		// How many topics are we moving?
		if (!isset($_REQUEST['totaltopics']))
			$total_topics = countTopicsFromBoard($id_board_from);
		else
		{
			$total_topics = (int) $_REQUEST['totaltopics'];
			validateToken('admin_movetopics');
		}

		// We have topics to move so start the process.
		if (!empty($total_topics))
		{
			while ($context['start'] <= $total_topics)
			{
				// Lets get the next 10 topics.
				$topics = getTopicsToMove($id_board_from);

				// Just return if we don't have any topics left to move.
				if (empty($topics))
					break;

				// Lets move them.
				moveTopics($topics, $id_board_to);

				// Increase the counter
				$context['start'] += 10;

				// If this is really taking some time, show the pause screen
				if (microtime(true) - $time_start > 3)
				{
					createToken('admin_movetopics');

					// What's the percent?
					$context['continue_percent'] = round(100 * ($context['start'] / $total_topics), 1);

					// Set up for the form
					$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . $context['start'];
					$context['continue_post_data'] = '
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
						<input type="hidden" name="' . $context['admin_movetopics_token_var'] . '" value="' . $context['admin_movetopics_token'] . '" />';

					// Let the template system do it's thang.
					return;
				}
			}
		}

		// Don't confuse admins by having an out of date cache.
		cache_put_data('board-' . $id_board_from, null, 120);
		cache_put_data('board-' . $id_board_to, null, 120);

		redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
	}

	/**
	 * Generates a list of integration hooks for display
	 *
	 * - Accessed through ?action=admin;area=maintain;sa=hooks;
	 * - Allows for removal or disabing of selected hooks
	 */
	public function action_hooks()
	{
		global $scripturl, $context, $txt, $modSettings, $settings;

		require_once(SUBSDIR . '/AddonSettings.subs.php');

		$context['filter_url'] = '';
		$context['current_filter'] = '';

		// Get the list of the current system hooks, filter them if needed
		$currentHooks = get_integration_hooks();
		if (isset($_GET['filter']) && in_array($_GET['filter'], array_keys($currentHooks)))
		{
			$context['filter_url'] = ';filter=' . $_GET['filter'];
			$context['current_filter'] = $_GET['filter'];
		}

		if (!empty($modSettings['handlinghooks_enabled']))
		{
			if (!empty($_REQUEST['do']) && isset($_REQUEST['hook']) && isset($_REQUEST['function']))
			{
				checkSession('request');
				validateToken('admin-hook', 'request');

				if ($_REQUEST['do'] == 'remove')
					remove_integration_function($_REQUEST['hook'], urldecode($_REQUEST['function']));
				else
				{
					if ($_REQUEST['do'] == 'disable')
					{
						// It's a hack I know...but I'm way too lazy!!!
						$function_remove = $_REQUEST['function'];
						$function_add = $_REQUEST['function'] . ']';
					}
					else
					{
						$function_remove = $_REQUEST['function'] . ']';
						$function_add = $_REQUEST['function'];
					}

					$file = !empty($_REQUEST['includedfile']) ? urldecode($_REQUEST['includedfile']) : '';

					remove_integration_function($_REQUEST['hook'], $function_remove, $file);
					add_integration_function($_REQUEST['hook'], $function_add, $file);

					// Clean the cache.
					require_once(SUBSDIR . '/Cache.subs.php');
					clean_cache();
				}

				redirectexit('action=admin;area=maintain;sa=hooks' . $context['filter_url']);
			}
		}

		$list_options = array(
			'id' => 'list_integration_hooks',
			'title' => $txt['maintain_sub_hooks_list'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=maintain;sa=hooks' . $context['filter_url'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			'default_sort_col' => 'hook_name',
			'get_items' => array(
				'function' => array($this, 'list_getIntegrationHooks'),
			),
			'get_count' => array(
				'function' => array($this, 'list_getIntegrationHooksCount'),
			),
			'no_items_label' => $txt['hooks_no_hooks'],
			'columns' => array(
				'hook_name' => array(
					'header' => array(
						'value' => $txt['hooks_field_hook_name'],
					),
					'data' => array(
						'db' => 'hook_name',
					),
					'sort' => array(
						'default' => 'hook_name',
						'reverse' => 'hook_name DESC',
					),
				),
				'function_name' => array(
					'header' => array(
						'value' => $txt['hooks_field_function_name'],
					),
					'data' => array(
						'function' => create_function('$data', '
							global $txt;

							if (!empty($data[\'included_file\']))
								return $txt[\'hooks_field_function\'] . \': \' . $data[\'real_function\'] . \'<br />\' . $txt[\'hooks_field_included_file\'] . \': \' . $data[\'included_file\'];
							else
								return $data[\'real_function\'];
						'),
					),
					'sort' => array(
						'default' => 'function_name',
						'reverse' => 'function_name DESC',
					),
				),
				'file_name' => array(
					'header' => array(
						'value' => $txt['hooks_field_file_name'],
					),
					'data' => array(
						'db' => 'file_name',
					),
					'sort' => array(
						'default' => 'file_name',
						'reverse' => 'file_name DESC',
					),
				),
				'status' => array(
					'header' => array(
						'value' => $txt['hooks_field_hook_exists'],
						'class' => 'nowrap',
					),
					'data' => array(
						'function' => create_function('$data', '
							global $txt, $settings, $scripturl, $context;

							$change_status = array(\'before\' => \'\', \'after\' => \'\');
							if ($data[\'can_be_disabled\'] && $data[\'status\'] != \'deny\')
							{
								$change_status[\'before\'] = \'<a href="\' . $scripturl . \'?action=admin;area=maintain;sa=hooks;do=\' . ($data[\'enabled\'] ? \'disable\' : \'enable\') . \';hook=\' . $data[\'hook_name\'] . \';function=\' . $data[\'real_function\'] . (!empty($data[\'included_file\']) ? \';includedfile=\' . urlencode($data[\'included_file\']) : \'\') . $context[\'filter_url\'] . \';\' . $context[\'admin-hook_token_var\'] . \'=\' . $context[\'admin-hook_token\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \'" onclick="return confirm(\' . javaScriptEscape($txt[\'quickmod_confirm\']) . \');">\';
								$change_status[\'after\'] = \'</a>\';
							}
							return $change_status[\'before\'] . \'<img src="\' . $settings[\'images_url\'] . \'/admin/post_moderation_\' . $data[\'status\'] . \'.png" alt="\' . $data[\'img_text\'] . \'" title="\' . $data[\'img_text\'] . \'" />\' . $change_status[\'after\'];
						'),
						'class' => 'centertext',
					),
					'sort' => array(
						'default' => 'status',
						'reverse' => 'status DESC',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value' => $txt['hooks_disable_instructions'] . '<br />
						' . $txt['hooks_disable_legend'] . ':
					<ul>
						<li>
							<img src="' . $settings['images_url'] . '/admin/post_moderation_allow.png" alt="' . $txt['hooks_active'] . '" title="' . $txt['hooks_active'] . '" /> ' . $txt['hooks_disable_legend_exists'] . '
						</li>
						<li>
							<img src="' . $settings['images_url'] . '/admin/post_moderation_moderate.png" alt="' . $txt['hooks_disabled'] . '" title="' . $txt['hooks_disabled'] . '" /> ' . $txt['hooks_disable_legend_disabled'] . '
						</li>
						<li>
							<img src="' . $settings['images_url'] . '/admin/post_moderation_deny.png" alt="' . $txt['hooks_missing'] . '" title="' . $txt['hooks_missing'] . '" /> ' . $txt['hooks_disable_legend_missing'] . '
						</li>
					</ul>'
				),
			),
		);

		if (!empty($modSettings['handlinghooks_enabled']))
		{
			createToken('admin-hook', 'request');

			$list_options['columns']['remove'] = array(
				'header' => array(
					'value' => $txt['hooks_button_remove'],
					'style' => 'width:3%',
				),
				'data' => array(
					'function' => create_function('$data', '
						global $txt, $settings, $scripturl, $context;

						if (!$data[\'hook_exists\'])
							return \'
							<a href="\' . $scripturl . \'?action=admin;area=maintain;sa=hooks;do=remove;hook=\' . $data[\'hook_name\'] . \';function=\' . urlencode($data[\'function_name\']) . $context[\'filter_url\'] . \';\' . $context[\'admin-hook_token_var\'] . \'=\' . $context[\'admin-hook_token\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \'" onclick="return confirm(\' . javaScriptEscape($txt[\'quickmod_confirm\']) . \');">
								<img src="\' . $settings[\'images_url\'] . \'/icons/quick_remove.png" alt="\' . $txt[\'hooks_button_remove\'] . \'" title="\' . $txt[\'hooks_button_remove\'] . \'" />
							</a>\';
					'),
					'class' => 'centertext',
				),
			);

			$list_options['form'] = array(
				'href' => $scripturl . '?action=admin;area=maintain;sa=hooks' . $context['filter_url'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'name' => 'list_integration_hooks',
			);
		}

		require_once(SUBSDIR . '/GenericList.class.php');
		createList($list_options);

		$context['page_title'] = $txt['maintain_sub_hooks_list'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'list_integration_hooks';
	}

	/**
	 * Recalculate all members post counts
	 *
	 * What it does:
	 * - it requires the admin_forum permission.
	 * - recounts all posts for members found in the message table
	 * - updates the members post count record in the members table
	 * - honors the boards post count flag
	 * - does not count posts in the recyle bin
	 * - zeros post counts for all members with no posts in the message table
	 * - runs as a delayed loop to avoid server overload
	 * - uses the not_done template in Admin.template
	 * - redirects back to action=admin;area=maintain;sa=members when complete.
	 * - accessed via ?action=admin;area=maintain;sa=members;activity=recountposts
	 */
	public function action_recountposts_display()
	{
		global $txt, $context;

		// Check the session
		checkSession();

		// Set up to the context for the pause screen
		$context['page_title'] = $txt['not_done_title'];
		$context['continue_countdown'] = 3;
		$context['continue_get_data'] = '';
		$context['sub_template'] = 'not_done';

		// Init, do 200 members in a bunch
		$increment = 200;
		$_REQUEST['start'] = !isset($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

		// Ask for some extra time, on big boards this may take a bit
		@set_time_limit(600);

		// The functions here will come in handy
		require_once(SUBSDIR . '/Maintenance.subs.php');

		// Only run this query if we don't have the total number of members that have posted
		if (!isset($_SESSION['total_members']) || $_REQUEST['start'] == 0)
		{
			validateToken('admin-maint');
			$_SESSION['total_members'] = countContributors();
		}
		else
			validateToken('admin-recountposts');

		// Lets get the next group of members and determine their post count
		// (from the boards that have post count enabled of course).
		$total_rows = updateMembersPostCount($_REQUEST['start'], $increment);

		// Continue?
		if ($total_rows == $increment)
		{
			createToken('admin-recountposts');

			$_REQUEST['start'] += $increment;
			$context['continue_get_data'] = '?action=admin;area=maintain;sa=members;activity=recountposts;start=' . $_REQUEST['start'];
			$context['continue_percent'] = round(100 * $_REQUEST['start'] / $_SESSION['total_members']);
			$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';
			$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-recountposts_token_var'] . '" value="' . $context['admin-recountposts_token'] . '" />
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />';

			if (function_exists('apache_reset_timeout'))
				apache_reset_timeout();

			return;
		}

		// No countable posts? set posts counter to 0
		updateZeroPostMembers();

		// All done, clean up and go back to maintainance
		unset($_SESSION['total_members']);
		redirectexit('action=admin;area=maintain;sa=members;done=recountposts');
	}

	/**
	 * Simply returns the total count of integration hooks
	 * Callback for createList().
	 *
	 * @return int
	 */
	public function list_getIntegrationHooksCount()
	{
		global $context;

		$context['filter'] = false;
		if (isset($_GET['filter']))
			$context['filter'] = $_GET['filter'];

		return integration_hooks_count($context['filter']);
	}

	/**
	 * Callback for createList(). Called by action_hooks
	 *
	 * @param int $start
	 * @param int $per_page
	 * @param string $sort
	 */
	public function list_getIntegrationHooks($start, $per_page, $sort)
	{
		return list_integration_hooks_data($start, $per_page, $sort);
	}
}