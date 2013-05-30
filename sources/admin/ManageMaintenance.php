<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * Forum maintenance. Important stuff.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class ManageMaintenance_Controller
{
	/**
	 * Main dispatcher, the maintenance access point.
	 * This, as usual, checks permissions, loads language files, and forwards to the actual workers.
	 */
	public function action_index()
	{
		global $txt, $context;

		// You absolutely must be an admin by here!
		isAllowedTo('admin_forum');

		// Need something to talk about?
		loadLanguage('ManageMaintenance');
		loadTemplate('ManageMaintenance');

		// This uses admin tabs - as it should!
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['maintain_title'],
			'description' => $txt['maintain_info'],
			'tabs' => array(
				'routine' => array(),
				'database' => array(),
				'members' => array(),
				'topics' => array(),
			),
		);

		// So many things you can do - but frankly I won't let you - just these!
		$subActions = array(
			'routine' => array(
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
				'function' => 'action_database',
				'activities' => array(
					'optimize' => 'action_optimize_display',
					'backup' => 'action_backup_display',
					'convertmsgbody' => 'action_convertmsgbody_display',
				),
			),
			'members' => array(
				'function' => 'action_members',
				'activities' => array(
					'reattribute' => 'action_reattribute_display',
					'purgeinactive' => 'action_purgeinactive_display',
					'recountposts' => 'action_recountposts_display',
				),
			),
			'topics' => array(
				'function' => 'action_topics',
				'activities' => array(
					'massmove' => 'action_massmove_display',
					'pruneold' => 'action_pruneold_display',
					'olddrafts' => 'action_olddrafts_display',
				),
			),
		);

		call_integration_hook('integrate_manage_maintenance', array(&$subActions));

		// Yep, sub-action time!
		if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
			$subAction = $_REQUEST['sa'];
		else
			$subAction = 'routine';

		// Doing something special?
		if (isset($_REQUEST['activity']) && isset($subActions[$subAction]['activities'][$_REQUEST['activity']]))
			$activity = $_REQUEST['activity'];

		// Set a few things.
		$context['page_title'] = $txt['maintain_title'];
		$context['sub_action'] = $subAction;

		// Finally fall through to what we are doing.
		$this->{$subActions[$subAction]['function']}();

		// Any special activity?
		if (isset($activity))
			$this->{$subActions[$subAction]['activities'][$activity]}();

		// Create a maintenance token.  Kinda hard to do it any other way.
		createToken('admin-maint');
	}

	/**
	 * Supporting function for the database maintenance area.
	 */
	public function action_database()
	{
		global $context, $db_type, $modSettings, $maintenance;

		$db = database();

		// We need this, really..
		require_once(SUBSDIR . '/ManageMaintenance.subs.php');

		// set up the sub-template
		$context['sub_template'] = 'maintain_database';

		if ($db_type == 'mysql')
		{
			$table = db_table();

			$colData = $table->db_list_columns('{db_prefix}messages', true);
			foreach ($colData as $column)
				if ($column['name'] == 'body')
					$body_type = $column['type'];

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
	 */
	public function action_routine()
	{
		global $context, $txt;

		if (isset($_GET['done']) && $_GET['done'] == 'recount')
			$context['maintenance_finished'] = $txt['maintain_recount'];

		// set up the sub-template
		$context['sub_template'] = 'maintain_routine';
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

		if (isset($_GET['done']) && $_GET['done'] == 'recountposts')
			$context['maintenance_finished'] = $txt['maintain_recountposts'];

		// set up the sub-template
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
		$context += getBoardList(array('use_permissions' => true, 'not_redirection' => true));

		if (isset($_GET['done']) && $_GET['done'] == 'purgeold')
			$context['maintenance_finished'] = $txt['maintain_old'];
		elseif (isset($_GET['done']) && $_GET['done'] == 'massmove')
			$context['maintenance_finished'] = $txt['move_topics_maintenance'];

		// set up the sub-template
		$context['sub_template'] = 'maintain_topics';
	}

	/**
	 * Find and try to fix all errors on the forum.
	 * Forwards to repair boards controller.
	 */
	public function action_repair_display()
	{
		// Honestly, this should be done in the sub function.
		validateToken('admin-maint');

		require_once(ADMINDIR . '/RepairBoards.php');
		$controller = new RepairBoards_Controller();
		$controller->action_repairboards();
	}

	/**
	 * Wipes the current cache entries as best it can.
	 * This only applies to our own cache entries, opcache and data.
	 * This action, like other maintenance tasks, may be called automatically
	 * by the task scheduler or manually by the admin in Maintenance area.
	 */
	public function action_cleancache_display()
	{
		global $context, $txt;

		checkSession();
		validateToken('admin-maint');

		// Just wipe the whole cache directory!
		clean_cache();

		$context['maintenance_finished'] = $txt['maintain_cache'];
	}

	/**
	 * Empties all uninmportant logs.
	 * This action may be called periodically, by the tasks scheduler,
	 * or manually by the admin in Maintenance area.
	 */
	public function action_logs_display()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/ManageMaintenance.subs.php');

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
	 * Convert the column "body" of the table {db_prefix}messages from TEXT to MEDIUMTEXT and vice versa.
	 * It requires the admin_forum permission.
	 * This is needed only for MySQL.
	 * During the convertion from MEDIUMTEXT to TEXT it check if any of the posts exceed the TEXT length and if so it aborts.
	 * This action is linked from the maintenance screen (if it's applicable).
	 * Accessed by ?action=admin;area=maintain;sa=database;activity=convertmsgbody.
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
			if ($column['name'] == 'body')
				$body_type = $column['type'];

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
			redirectexit('action=admin;area=maintain;sa=database');
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
	 * It requires the admin_forum permission.
	 * It shows as the maintain_forum admin area.
	 * It is accessed from ?action=admin;area=maintain;sa=database;activity=optimize.
	 * It also updates the optimize scheduled task such that the tables are not automatically optimized again too soon.

	 * @uses the rawdata sub template (built in.)
	 */
	public function action_optimize_display()
	{
		global $db_type, $txt, $context;

		isAllowedTo('admin_forum');

		checkSession('post');
		validateToken('admin-maint');

		ignore_user_abort(true);

		require_once(SUBSDIR . '/ManageMaintenance.subs.php');

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
		require_once(SOURCEDIR . '/ScheduledTasks.php');
		calculateNextTrigger('auto_optimize', true);
	}

	/**
	 * Recount many forum totals that can be recounted automatically without harm.
	 * it requires the admin_forum permission.
	 * It shows the maintain_forum admin area.
	 *
	 * Totals recounted:
	 * - fixes for topics with wrong num_replies.
	 * - updates for num_posts and num_topics of all boards.
	 * - recounts instant_messages but not unread_messages.
	 * - repairs messages pointing to boards with topics pointing to other boards.
	 * - updates the last message posted in boards and children.
	 * - updates member count, latest member, topic count, and message count.
	 *
	 * The function redirects back to ?action=admin;area=maintain when complete.
	 * It is accessed via ?action=admin;area=maintain;sa=database;activity=recount.
	 */
	public function action_recount_display()
	{
		global $txt, $context, $modSettings, $time_start;

		isAllowedTo('admin_forum');
		checkSession('request');

		require_once(SUBSDIR . '/ManageMaintenance.subs.php');

		// validate the request or the loop
		if (!isset($_REQUEST['step']))
			validateToken('admin-maint');
		else
			validateToken('admin-boardrecount');

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
					$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />';

					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=0;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
					$context['continue_percent'] = round((100 * $_REQUEST['start'] / $max_topics) / $total_steps);

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
					$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />';

					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=1;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
					$context['continue_percent'] = round((200 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

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
					$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />';

					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=2;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
					$context['continue_percent'] = round((300 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

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
					$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />';

					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=3;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
					$context['continue_percent'] = round((400 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

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
					$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />';

					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=4;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
					$context['continue_percent'] = round((500 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

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
				$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />';

				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=0;' . $context['session_var'] . '=' . $context['session_id'];
				$context['continue_percent'] = round(700 / $total_steps);

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
					$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />';

					$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
					$context['continue_percent'] = round((700 + 100 * $_REQUEST['start'] / $modSettings['maxMsgID']) / $total_steps);

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
		require_once(SOURCEDIR . '/ScheduledTasks.php');
		calculateNextTrigger();

		redirectexit('action=admin;area=maintain;sa=routine;done=recount');
	}

	/**
	 * Perform a detailed version check.  A very good thing ;).
	 * The function parses the comment headers in all files for their version information,
	 * and outputs that for some javascript to check with simplemachines.org.
	 * It does not connect directly with elkarte.net, but rather expects the client to.
	 *
	 * It requires the admin_forum permission.
	 * Uses the view_versions admin area.
	 * Accessed through ?action=admin;area=maintain;sa=routine;activity=version.
	 * @uses Admin template, view_versions sub-template.
	 */
	public function action_version_display()
	{
		global $forum_version, $txt, $context;

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
	}

	/**
	 * Re-attribute posts to the user sent from the maintenance page.
	 */
	public function action_reattribute_display()
	{
		global $context, $txt;

		checkSession();

		// Find the member.
		require_once(SUBSDIR . '/Auth.subs.php');
		$members = findMembers($_POST['to']);

		if (empty($members))
			fatal_lang_error('reattribute_cannot_find_member');

		$memID = array_shift($members);
		$memID = $memID['id'];

		$email = $_POST['type'] == 'email' ? $_POST['from_email'] : '';
		$membername = $_POST['type'] == 'name' ? $_POST['from_name'] : '';

		// Now call the reattribute function.
		require_once(SUBSDIR . '/Members.subs.php');
		reattributePosts($memID, $email, $membername, !empty($_POST['posts']));

		$context['maintenance_finished'] = $txt['maintain_reattribute_posts'];
	}

	/**
	 * Handling function for the backup stuff.
	 * It requires an administrator and the session hash by post.
	 * This method simply forwards to DumpDatabase2().
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

		$_POST['maxdays'] = empty($_POST['maxdays']) ? 0 : (int) $_POST['maxdays'];
		if (!empty($_POST['groups']) && $_POST['maxdays'] > 0)
		{
			checkSession();
			validateToken('admin-maint');

			$groups = array();
			foreach ($_POST['groups'] as $id => $dummy)
				$groups[] = (int) $id;
			$time_limit = (time() - ($_POST['maxdays'] * 24 * 3600));
			$members = purgeMembers($_POST['type'], $groups, $time_limit);

			require_once(SUBSDIR . '/Members.subs.php');
			deleteMembers($members);
		}

		$context['maintenance_finished'] = $txt['maintain_members'];
		createToken('admin-maint');
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
		validateToken('admin-maint');

		require_once(SUBSDIR . '/Drafts.subs.php');
		$drafts = getOldDrafts($_POST['draftdays']);


		// If we have old drafts, remove them
		if (count($drafts) > 0)
			deleteDrafts($drafts, -1, false);
	}

	/**
	 * Moves topics from one board to another.
	 *
	 * @uses not_done template to pause the process.
	 */
	public function action_massmove_display()
	{
		global $context, $txt;

		// Only admins.
		isAllowedTo('admin_forum');

		checkSession('request');
		validateToken('admin-maint');

		// Set up to the context.
		$context['page_title'] = $txt['not_done_title'];
		$context['continue_countdown'] = 3;
		$context['continue_post_data'] = '';
		$context['continue_get_data'] = '';
		$context['sub_template'] = 'not_done';
		$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
		$context['start_time'] = time();

		// First time we do this?
		$id_board_from = isset($_POST['id_board_from']) ? (int) $_POST['id_board_from'] : (int) $_REQUEST['id_board_from'];
		$id_board_to = isset($_POST['id_board_to']) ? (int) $_POST['id_board_to'] : (int) $_REQUEST['id_board_to'];

		// No boards then this is your stop.
		if (empty($id_board_from) || empty($id_board_to))
			return;

		// How many topics are we converting?
		if (!isset($_REQUEST['totaltopics']))
			$total_topics = countTopicsFromBoard($id_board_from);

		else
			$total_topics = (int) $_REQUEST['totaltopics'];

		// Seems like we need this here.
		$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];

		// We have topics to move so start the process.
		if (!empty($total_topics))
		{
			while ($context['start'] <= $total_topics)
			{
				// Lets get the topics.
				$topics = getTopicsToMove($id_board_from);

				// Just return if we don't have any topics left to move.
				if (empty($topics))
				{
					cache_put_data('board-' . $id_board_from, null, 120);
					cache_put_data('board-' . $id_board_to, null, 120);
					redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
				}

				// Lets move them.
				require_once(SUBSDIR . '/Topic.subs.php');
				moveTopics($topics, $id_board_to);

				// We've done at least ten more topics.
				$context['start'] += 10;

				// Lets wait a while.
				if (time() - $context['start_time'] > 3)
				{
					// What's the percent?
					$context['continue_percent'] = round(100 * ($context['start'] / $total_topics), 1);
					$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];

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
	 * Recalculate all members post counts
	 * it requires the admin_forum permission.
	 *
	 * - recounts all posts for members found in the message table
	 * - updates the members post count record in the members talbe
	 * - honors the boards post count flag
	 * - does not count posts in the recyle bin
	 * - zeros post counts for all members with no posts in the message table
	 * - runs as a delayed loop to avoid server overload
	 * - uses the not_done template in Admin.template
	 *
	 * The function redirects back to action=admin;area=maintain;sa=members when complete.
	 * It is accessed via ?action=admin;area=maintain;sa=members;activity=recountposts
	 */
	public function action_recountposts_display()
	{
		global $txt, $context;

		// You have to be allowed in here
		isAllowedTo('admin_forum');
		checkSession('request');

		// Set up to the context.
		$context['page_title'] = $txt['not_done_title'];
		$context['continue_countdown'] = 3;
		$context['continue_get_data'] = '';
		$context['sub_template'] = 'not_done';

		// init
		$increment = 200;
		$_REQUEST['start'] = !isset($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

		// Ask for some extra time, on big boards this may take a bit
		@set_time_limit(600);

		// Only run this query if we don't have the total number of members that have posted
		if (!isset($_SESSION['total_members']))
		{
			validateToken('admin-maint');
			$_SESSION['total_members'] = countContributors();
		}
		else
			validateToken('admin-recountposts');

		// Lets get a group of members and determine their post count (from the boards that have post count enabled of course).
		$total_rows = updateMembersPostCount($_REQUEST['start'], $increment);

		// Continue?
		if ($total_rows == $increment)
		{
			$_REQUEST['start'] += $increment;
			$context['continue_get_data'] = '?action=admin;area=maintain;sa=members;activity=recountposts;start=' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'];
			$context['continue_percent'] = round(100 * $_REQUEST['start'] / $_SESSION['total_members']);

			createToken('admin-recountposts');
			$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-recountposts_token_var'] . '" value="' . $context['admin-recountposts_token'] . '" />';

			if (function_exists('apache_reset_timeout'))
				apache_reset_timeout();
			return;
		}
		// No countable posts? set posts counter to 0
		 updateZeroPostMembers();

		// all done
		unset($_SESSION['total_members']);
		$context['maintenance_finished'] = $txt['maintain_recountposts'];
		redirectexit('action=admin;area=maintain;sa=members;done=recountposts');
	}
}