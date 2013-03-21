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

/**
 * Main dispatcher, the maintenance access point.
 * This, as usual, checks permissions, loads language files, and forwards to the actual workers.
 */
function ManageMaintenance()
{
	global $txt, $modSettings, $scripturl, $context, $options;

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
			'function' => 'MaintainRoutine',
			'template' => 'maintain_routine',
			'activities' => array(
				'version' => 'VersionDetail',
				'repair' => 'MaintainFindFixErrors',
				'recount' => 'AdminBoardRecount',
				'logs' => 'MaintainEmptyUnimportantLogs',
				'cleancache' => 'MaintainCleanCache',
			),
		),
		'database' => array(
			'function' => 'MaintainDatabase',
			'template' => 'maintain_database',
			'activities' => array(
				'optimize' => 'OptimizeTables',
				'backup' => 'MaintainDownloadBackup',
				'convertmsgbody' => 'ConvertMsgBody',
			),
		),
		'members' => array(
			'function' => 'MaintainMembers',
			'template' => 'maintain_members',
			'activities' => array(
				'reattribute' => 'MaintainReattributePosts',
				'purgeinactive' => 'MaintainPurgeInactiveMembers',
				'recountposts' => 'MaintainRecountPosts',
			),
		),
		'topics' => array(
			'function' => 'MaintainTopics',
			'template' => 'maintain_topics',
			'activities' => array(
				'massmove' => 'MaintainMassMoveTopics',
				'pruneold' => 'MaintainRemoveOldPosts',
				'olddrafts' => 'MaintainRemoveOldDrafts',
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
	$context['sub_template'] = !empty($subActions[$subAction]['template']) ? $subActions[$subAction]['template'] : '';

	// Finally fall through to what we are doing.
	$subActions[$subAction]['function']();

	// Any special activity?
	if (isset($activity))
		$subActions[$subAction]['activities'][$activity]();

	// Create a maintenance token.  Kinda hard to do it any other way.
	createToken('admin-maint');
}

/**
 * Supporting function for the database maintenance area.
 */
function MaintainDatabase()
{
	global $context, $db_type, $db_character_set, $modSettings, $smcFunc, $txt, $maintenance;

	if ($db_type == 'mysql')
	{
		db_extend('packages');

		$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);
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
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}messages',
		array()
	);
	list($messages) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

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
function MaintainRoutine()
{
	global $context, $txt;

	if (isset($_GET['done']) && $_GET['done'] == 'recount')
		$context['maintenance_finished'] = $txt['maintain_recount'];
}

/**
 * Supporting function for the members maintenance area.
 */
function MaintainMembers()
{
	global $context, $smcFunc, $txt;

	// Get membergroups - for deleting members and the like.
	$result = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$context['membergroups'] = array(
		array(
			'id' => 0,
			'name' => $txt['maintain_members_ungrouped']
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		$context['membergroups'][] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	}
	$smcFunc['db_free_result']($result);

	if (isset($_GET['done']) && $_GET['done'] == 'recountposts')
		$context['maintenance_finished'] = $txt['maintain_recountposts'];
}

/**
 * Supporting function for the topics maintenance area.
 */
function MaintainTopics()
{
	global $context, $smcFunc, $txt;

	require_once(SUBSDIR . '/MessageIndex.subs.php');
	// Let's load up the boards in case they are useful.
	$context += getBoardList(array('use_permissions' => true, 'not_redirection' => true));

	if (isset($_GET['done']) && $_GET['done'] == 'purgeold')
		$context['maintenance_finished'] = $txt['maintain_old'];
	elseif (isset($_GET['done']) && $_GET['done'] == 'massmove')
		$context['maintenance_finished'] = $txt['move_topics_maintenance'];
}

/**
 * Find and fix all errors on the forum.
 */
function MaintainFindFixErrors()
{
	// Honestly, this should be done in the sub function.
	validateToken('admin-maint');

	require_once(ADMINDIR . '/RepairBoards.php');
	action_repairboards();
}

/**
 * Wipes the current cache entries as best it can.
 * This only applies to our own cache entries, opcache and data
 */
function MaintainCleanCache()
{
	global $context, $txt;

	checkSession();
	validateToken('admin-maint');

	// Just wipe the whole cache directory!
	clean_cache();

	$context['maintenance_finished'] = $txt['maintain_cache'];
}

/**
 * Empties all uninmportant logs
 */
function MaintainEmptyUnimportantLogs()
{
	global $context, $smcFunc, $txt;

	checkSession();
	validateToken('admin-maint');

	// No one's online now.... MUHAHAHAHA :P.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online');

	// Dump the banning logs.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_banned');

	// Start id_error back at 0 and dump the error log.
	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_errors');

	// Clear out the spam log.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_floodcontrol');

	// Clear out the karma actions.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_karma');

	// Last but not least, the search logs!
	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_topics');

	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_messages');

	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_results');

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
function ConvertMsgBody()
{
	global $scripturl, $context, $txt, $language, $db_character_set, $db_type;
	global $modSettings, $user_info, $smcFunc, $db_prefix, $time_start;

	// Show me your badge!
	isAllowedTo('admin_forum');

	if ($db_type != 'mysql')
		return;

	db_extend('packages');

	$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);
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
			$smcFunc['db_change_column']('{db_prefix}messages', 'body', array('type' => 'mediumtext'));
		// Shorten the column so we can have a bit (literally per record) less space occupied
		else
			$smcFunc['db_change_column']('{db_prefix}messages', 'body', array('type' => 'text'));

		$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);
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

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) as count
			FROM {db_prefix}messages',
			array()
		);
		list($max_msgs) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Try for as much time as possible.
		@set_time_limit(600);

		while ($_REQUEST['start'] < $max_msgs)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ id_msg
				FROM {db_prefix}messages
				WHERE id_msg BETWEEN {int:start} AND {int:start} + {int:increment}
					AND LENGTH(body) > 65535',
				array(
					'start' => $_REQUEST['start'],
					'increment' => $increment - 1,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$id_msg_exceeding[] = $row['id_msg'];
			$smcFunc['db_free_result']($request);

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

			$context['exceeding_messages'] = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_msg, id_topic, subject
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:messages})',
				array(
					'messages' => $query_msg,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['exceeding_messages'][] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
			$smcFunc['db_free_result']($request);
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
function OptimizeTables()
{
	global $db_type, $db_name, $db_prefix, $txt, $context, $scripturl, $smcFunc;

	isAllowedTo('admin_forum');

	checkSession('post');
	validateToken('admin-maint');

	ignore_user_abort(true);
	db_extend();

	// Start with no tables optimized.
	$opttab = 0;

	$context['page_title'] = $txt['database_optimize'];
	$context['sub_template'] = 'optimize';

	// Only optimize the tables related to this installation, not all the tables in the db
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// Get a list of tables, as well as how many there are.
	$temp_tables = $smcFunc['db_list_tables'](false, $real_prefix . '%');
	$tables = array();
	foreach ($temp_tables as $table)
		$tables[] = array('table_name' => $table);

	// If there aren't any tables then I believe that would mean the world has exploded...
	$context['num_tables'] = count($tables);
	if ($context['num_tables'] == 0)
		fatal_error('You appear to be running ELKARTE in a flat file mode... fantastic!', false);

	// For each table....
	$context['optimized_tables'] = array();
	foreach ($tables as $table)
	{
		// Optimize the table!  We use backticks here because it might be a custom table.
		$data_freed = $smcFunc['db_optimize_table']($table['table_name']);

		// Optimizing one sqlite table optimizes them all.
		if ($db_type == 'sqlite')
			break;

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
	CalculateNextTrigger('auto_optimize', true);
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
function AdminBoardRecount()
{
	global $txt, $context, $scripturl, $modSettings;
	global $time_start, $smcFunc;

	isAllowedTo('admin_forum');
	checkSession('request');

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
	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_topic)
		FROM {db_prefix}topics',
		array(
		)
	);
	list ($max_topics) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

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
			// Recount approved messages
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.num_replies) AS num_replies,
					CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END AS real_num_replies
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS ma ON (ma.id_topic = t.id_topic AND ma.approved = {int:is_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END != MAX(t.num_replies)',
				array(
					'is_approved' => 1,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET num_replies = {int:num_replies}
					WHERE id_topic = {int:id_topic}',
					array(
						'num_replies' => $row['real_num_replies'],
						'id_topic' => $row['id_topic'],
					)
				);
			$smcFunc['db_free_result']($request);

			// Recount unapproved messages
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.unapproved_posts) AS unapproved_posts,
					COUNT(mu.id_msg) AS real_unapproved_posts
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = {int:not_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING COUNT(mu.id_msg) != MAX(t.unapproved_posts)',
				array(
					'not_approved' => 0,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET unapproved_posts = {int:unapproved_posts}
					WHERE id_topic = {int:id_topic}',
					array(
						'unapproved_posts' => $row['real_unapproved_posts'],
						'id_topic' => $row['id_topic'],
					)
				);
			$smcFunc['db_free_result']($request);

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
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET num_posts = {int:num_posts}
				WHERE redirect = {string:redirect}',
				array(
					'num_posts' => 0,
					'redirect' => '',
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_num_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 1,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET num_posts = num_posts + {int:real_num_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_posts' => $row['real_num_posts'],
					)
				);
			$smcFunc['db_free_result']($request);

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
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET num_topics = {int:num_topics}',
				array(
					'num_topics' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_num_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_approved' => 1,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET num_topics = num_topics + {int:real_num_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_topics' => $row['real_num_topics'],
					)
				);
			$smcFunc['db_free_result']($request);

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
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET unapproved_posts = {int:unapproved_posts}',
				array(
					'unapproved_posts' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_unapproved_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 0,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET unapproved_posts = unapproved_posts + {int:unapproved_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'unapproved_posts' => $row['real_unapproved_posts'],
					)
				);
			$smcFunc['db_free_result']($request);

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
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET unapproved_topics = {int:unapproved_topics}',
				array(
					'unapproved_topics' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_unapproved_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_approved' => 0,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET unapproved_topics = unapproved_topics + {int:real_unapproved_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_unapproved_topics' => $row['real_unapproved_topics'],
					)
				);
			$smcFunc['db_free_result']($request);

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
		$request = $smcFunc['db_query']('', '
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.instant_messages) AS instant_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.instant_messages)',
			array(
				'is_not_deleted' => 0,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			updateMemberData($row['id_member'], array('instant_messages' => $row['real_num']));
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.unread_messages) AS unread_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted} AND pmr.is_read = {int:is_not_read})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.unread_messages)',
			array(
				'is_not_deleted' => 0,
				'is_not_read' => 0,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			updateMemberData($row['id_member'], array('unread_messages' => $row['real_num']));
		$smcFunc['db_free_result']($request);

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
			$request = $smcFunc['db_query']('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_board != m.id_board)
				WHERE m.id_msg > {int:id_msg_min}
					AND m.id_msg <= {int:id_msg_max}',
				array(
					'id_msg_min' => $_REQUEST['start'],
					'id_msg_max' => $_REQUEST['start'] + $increment,
				)
			);
			$boards = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$boards[$row['id_board']][] = $row['id_msg'];
			$smcFunc['db_free_result']($request);

			foreach ($boards as $board_id => $messages)
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET id_board = {int:id_board}
					WHERE id_msg IN ({array_int:id_msg_array})',
					array(
						'id_msg_array' => $messages,
						'id_board' => $board_id,
					)
				);

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

	// Update the latest message of each board.
	$request = $smcFunc['db_query']('', '
		SELECT m.id_board, MAX(m.id_msg) AS local_last_msg
		FROM {db_prefix}messages AS m
		WHERE m.approved = {int:is_approved}
		GROUP BY m.id_board',
		array(
			'is_approved' => 1,
		)
	);
	$realBoardCounts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$realBoardCounts[$row['id_board']] = $row['local_last_msg'];
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_board, id_parent, id_last_msg, child_level, id_msg_updated
		FROM {db_prefix}boards',
		array(
		)
	);
	$resort_me = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['local_last_msg'] = isset($realBoardCounts[$row['id_board']]) ? $realBoardCounts[$row['id_board']] : 0;
		$resort_me[$row['child_level']][] = $row;
	}
	$smcFunc['db_free_result']($request);

	krsort($resort_me);

	$lastModifiedMsg = array();
	foreach ($resort_me as $rows)
		foreach ($rows as $row)
		{
			// The latest message is the latest of the current board and its children.
			if (isset($lastModifiedMsg[$row['id_board']]))
				$curLastModifiedMsg = max($row['local_last_msg'], $lastModifiedMsg[$row['id_board']]);
			else
				$curLastModifiedMsg = $row['local_last_msg'];

			// If what is and what should be the latest message differ, an update is necessary.
			if ($row['local_last_msg'] != $row['id_last_msg'] || $curLastModifiedMsg != $row['id_msg_updated'])
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}boards
					SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
					WHERE id_board = {int:id_board}',
					array(
						'id_last_msg' => $row['local_last_msg'],
						'id_msg_updated' => $curLastModifiedMsg,
						'id_board' => $row['id_board'],
					)
				);

			// Parent boards inherit the latest modified message of their children.
			if (isset($lastModifiedMsg[$row['id_parent']]))
				$lastModifiedMsg[$row['id_parent']] = max($row['local_last_msg'], $lastModifiedMsg[$row['id_parent']]);
			else
				$lastModifiedMsg[$row['id_parent']] = $row['local_last_msg'];
		}

	// Update all the basic statistics.
	updateStats('member');
	updateStats('message');
	updateStats('topic');

	// Finally, update the latest event times.
	require_once(SOURCEDIR . '/ScheduledTasks.php');
	CalculateNextTrigger();

	redirectexit('action=admin;area=maintain;sa=routine;done=recount');
}

/**
 * Perform a detailed version check.  A very good thing ;).
 * The function parses the comment headers in all files for their version information,
 * and outputs that for some javascript to check with simplemachines.org.
 * It does not connect directly with simplemachines.org, but rather expects the client to.
 *
 * It requires the admin_forum permission.
 * Uses the view_versions admin area.
 * Accessed through ?action=admin;area=maintain;sa=routine;activity=version.
 * @uses Admin template, view_versions sub-template.
 */
function VersionDetail()
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
 * Re-attribute posts.
 */
function MaintainReattributePosts()
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
 */
function MaintainDownloadBackup()
{
	validateToken('admin-maint');

	require_once(SOURCEDIR . '/DumpDatabase.php');
	DumpDatabase2();
}

/**
 * Removing old members. Done and out!
 * @todo refactor
 */
function MaintainPurgeInactiveMembers()
{
	global $context, $smcFunc, $txt;

	$_POST['maxdays'] = empty($_POST['maxdays']) ? 0 : (int) $_POST['maxdays'];
	if (!empty($_POST['groups']) && $_POST['maxdays'] > 0)
	{
		checkSession();
		validateToken('admin-maint');

		$groups = array();
		foreach ($_POST['groups'] as $id => $dummy)
			$groups[] = (int) $id;
		$time_limit = (time() - ($_POST['maxdays'] * 24 * 3600));
		$where_vars = array(
			'time_limit' => $time_limit,
		);
		if ($_POST['del_type'] == 'activated')
		{
			$where = 'mem.date_registered < {int:time_limit} AND mem.is_activated = {int:is_activated}';
			$where_vars['is_activated'] = 0;
		}
		else
			$where = 'mem.last_login < {int:time_limit} AND (mem.last_login != 0 OR mem.date_registered < {int:time_limit})';

		// Need to get *all* groups then work out which (if any) we avoid.
		$request = $smcFunc['db_query']('', '
			SELECT id_group, group_name, min_posts
			FROM {db_prefix}membergroups',
			array(
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Avoid this one?
			if (!in_array($row['id_group'], $groups))
			{
				// Post group?
				if ($row['min_posts'] != -1)
				{
					$where .= ' AND mem.id_post_group != {int:id_post_group_' . $row['id_group'] . '}';
					$where_vars['id_post_group_' . $row['id_group']] = $row['id_group'];
				}
				else
				{
					$where .= ' AND mem.id_group != {int:id_group_' . $row['id_group'] . '} AND FIND_IN_SET({int:id_group_' . $row['id_group'] . '}, mem.additional_groups) = 0';
					$where_vars['id_group_' . $row['id_group']] = $row['id_group'];
				}
			}
		}
		$smcFunc['db_free_result']($request);

		// If we have ungrouped unselected we need to avoid those guys.
		if (!in_array(0, $groups))
		{
			$where .= ' AND (mem.id_group != 0 OR mem.additional_groups != {string:blank_add_groups})';
			$where_vars['blank_add_groups'] = '';
		}

		// Select all the members we're about to murder/remove...
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, IFNULL(m.id_member, 0) AS is_mod
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}moderators AS m ON (m.id_member = mem.id_member)
			WHERE ' . $where,
			$where_vars
		);
		$members = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!$row['is_mod'] || !in_array(3, $groups))
				$members[] = $row['id_member'];
		}
		$smcFunc['db_free_result']($request);

		require_once(SUBSDIR . '/Members.subs.php');
		deleteMembers($members);
	}

	$context['maintenance_finished'] = $txt['maintain_members'];
	createToken('admin-maint');
}

/**
 * Removing old posts doesn't take much as we really pass through.
 */
function MaintainRemoveOldPosts()
{
	global $context, $txt;

	validateToken('admin-maint');

	// Actually do what we're told!
	require_once(SUBSDIR . '/Topic.subs.php');
	removeOldTopics();
}

/**
 * Removing old drafts
 */
function MaintainRemoveOldDrafts()
{
	global $smcFunc;

	validateToken('admin-maint');

	$drafts = array();

	// Find all of the old drafts
	$request = $smcFunc['db_query']('', '
		SELECT id_draft
		FROM {db_prefix}user_drafts
		WHERE poster_time <= {int:poster_time_old}',
		array(
			'poster_time_old' => time() - (86400 * $_POST['draftdays']),
		)
	);

	while ($row = $smcFunc['db_fetch_row']($request))
		$drafts[] = (int) $row[0];
	$smcFunc['db_free_result']($request);

	// If we have old drafts, remove them
	if (count($drafts) > 0)
	{
		require_once(SUBSDIR . '/Drafts.subs.php');
		deleteDrafts($drafts, -1, false);
	}
}

/**
 * Moves topics from one board to another.
 *
 * @uses not_done template to pause the process.
 */
function MaintainMassMoveTopics()
{
	global $smcFunc, $context, $txt;

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
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics
			WHERE id_board = {int:id_board_from}',
			array(
				'id_board_from' => $id_board_from,
			)
		);
		list ($total_topics) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}
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
			$request = $smcFunc['db_query']('', '
				SELECT id_topic
				FROM {db_prefix}topics
				WHERE id_board = {int:id_board_from}
				LIMIT 10',
				array(
					'id_board_from' => $id_board_from,
				)
			);

			// Get the ids.
			$topics = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$topics[] = $row['id_topic'];

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
function MaintainRecountPosts()
{
	global $txt, $context, $modSettings, $smcFunc;

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

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(DISTINCT m.id_member)
			FROM ({db_prefix}messages AS m, {db_prefix}boards AS b)
			WHERE m.id_member != 0
				AND b.count_posts = 0
				AND m.id_board = b.id_board',
			array(
			)
		);

		// save it so we don't do this again for this task
		list ($_SESSION['total_members']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}
	else
		validateToken('admin-recountposts');

	// Lets get a group of members and determine their post count (from the boards that have post count enabled of course).
	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ m.id_member, COUNT(m.id_member) AS posts
		FROM ({db_prefix}messages AS m, {db_prefix}boards AS b)
		WHERE m.id_member != {int:zero}
			AND b.count_posts = {int:zero}
			AND m.id_board = b.id_board ' . (!empty($modSettings['recycle_enable']) ? '
			AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_member
		LIMIT {int:start}, {int:number}',
		array(
			'start' => $_REQUEST['start'],
			'number' => $increment,
			'recycle' => $modSettings['recycle_board'],
			'zero' => 0,
		)
	);
	$total_rows = $smcFunc['db_num_rows']($request);

	// Update the post count for this group
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET posts = {int:posts}
			WHERE id_member = {int:row}',
			array(
				'row' => $row['id_member'],
				'posts' => $row['posts'],
			)
		);
	}
	$smcFunc['db_free_result']($request);

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

	// final steps ... made more difficult since we don't yet support sub-selects on joins
	// place all members who have posts in the message table in a temp table
	$createTemporary = $smcFunc['db_query']('', '
		CREATE TEMPORARY TABLE {db_prefix}tmp_maint_recountposts (
			id_member mediumint(8) unsigned NOT NULL default {string:string_zero},
			PRIMARY KEY (id_member)
		)
		SELECT m.id_member
		FROM ({db_prefix}messages AS m,{db_prefix}boards AS b)
		WHERE m.id_member != {int:zero}
			AND b.count_posts = {int:zero}
			AND m.id_board = b.id_board ' . (!empty($modSettings['recycle_enable']) ? '
			AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_member',
		array(
			'zero' => 0,
			'string_zero' => '0',
			'db_error_skip' => true,
		)
	) !== false;

	if ($createTemporary)
	{
		// outer join the members table on the temporary table finding the members that have a post count but no posts in the message table
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, mem.posts
			FROM {db_prefix}members AS mem
			LEFT OUTER JOIN {db_prefix}tmp_maint_recountposts AS res
			ON res.id_member = mem.id_member
			WHERE res.id_member IS null
				AND mem.posts != {int:zero}',
			array(
				'zero' => 0,
			)
		);

		// set the post count to zero for any delinquents we may have found
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}members
				SET posts = {int:zero}
				WHERE id_member = {int:row}',
				array(
					'row' => $row['id_member'],
					'zero' => 0,
				)
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// all done
	unset($_SESSION['total_members']);
	$context['maintenance_finished'] = $txt['maintain_recountposts'];
	redirectexit('action=admin;area=maintain;sa=members;done=recountposts');
}
