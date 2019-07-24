<?php

/**
 * Forum maintenance. Important stuff.
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
 * Entry point class for all of the maintenance ,routine, members, database,
 * attachments, topics and hooks
 *
 * @package Maintenance
 */
class Maintenance extends \ElkArte\AbstractController
{
	/**
	 * Maximum topic counter
	 * @var int
	 */
	public $max_topics;

	/**
	 * How many actions to take for a maintenance actions
	 * @var int
	 */
	public $increment;

	/**
	 * Total steps for a given maintenance action
	 * @var int
	 */
	public $total_steps;

	/**
	 * reStart pointer for paused maintenance actions
	 * @var int
	 */
	public $start;

	/**
	 * Loop counter for paused maintenance actions
	 * @var int
	 */
	public $step;

	/**
	 * Main dispatcher, the maintenance access point.
	 *
	 * What it does:
	 *
	 * - This, as usual, checks permissions, loads language files,
	 * and forwards to the actual workers.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $txt, $context;

		// You absolutely must be an admin by here!
		isAllowedTo('admin_forum');

		// Need something to talk about?
		theme()->getTemplates()->loadLanguageFile('Maintenance');
		theme()->getTemplates()->load('Maintenance');

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
				),
			),
			'hooks' => array(
				'controller' => $this,
				'function' => 'action_hooks',
			),
			'attachments' => array(
				'controller' => '\\ElkArte\\AdminController\\ManageAttachments',
				'function' => 'action_maintenance',
			),
		);

		// Set up the action handler
		$action = new \ElkArte\Action('manage_maintenance');

		// Yep, sub-action time and call integrate_sa_manage_maintenance as well
		$subAction = $action->initialize($subActions, 'routine');

		// Doing something special, does it exist?
		if (isset($this->_req->query->activity, $subActions[$subAction]['activities'][$this->_req->query->activity]))
			$activity = $this->_req->query->activity;

		// Set a few things.
		$context[$context['admin_menu_name']]['current_subsection'] = $subAction;
		$context['page_title'] = $txt['maintain_title'];
		$context['sub_action'] = $subAction;

		// Finally fall through to what we are doing.
		$action->dispatch($subAction);

		// Any special activity defined, then go to it.
		if (isset($activity))
		{
			if (is_string($subActions[$subAction]['activities'][$activity]) && method_exists($this, $subActions[$subAction]['activities'][$activity]))
				$this->{$subActions[$subAction]['activities'][$activity]}();
			elseif (is_string($subActions[$subAction]['activities'][$activity]))
				$subActions[$subAction]['activities'][$activity]();
			else
			{
				if (is_array($subActions[$subAction]['activities'][$activity]))
				{
					$activity_obj = new $subActions[$subAction]['activities'][$activity]['class']();
					$activity_obj->{$subActions[$subAction]['activities'][$activity]['method']}();
				}
				else
				{
					$subActions[$subAction]['activities'][$activity]();
				}
			}
		}

		// Create a maintenance token.  Kinda hard to do it any other way.
		createToken('admin-maint');
	}

	/**
	 * Supporting function for the database maintenance area.
	 */
	public function action_database()
	{
		global $context, $modSettings, $maintenance;

		// We need this, really..
		require_once(SUBSDIR . '/Maintenance.subs.php');

		// Set up the sub-template
		$context['sub_template'] = 'maintain_database';
		$db = database();

		if ($db->supportMediumtext())
		{
			$body_type = fetchBodyType();

			$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';
			$context['convert_to_suggest'] = ($body_type != 'text' && !empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] < 65536);
		}

		// Check few things to give advices before make a backup
		// If safe mod is enable the external tool is *always* the best (and probably the only) solution
		$context['safe_mode_enable'] = false;
		if (version_compare(PHP_VERSION, '5.4.0', '<'))
		{
			$context['safe_mode_enable'] = @ini_get('safe_mode');
		}

		// This is just a...guess
		$messages = countMessages();

		// 256 is what we use in the backup script
		detectServer()->setMemoryLimit('256M');
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
		$current_time_limit = (int) ini_get('max_execution_time');
		@set_time_limit(159); //something strange just to be sure
		$new_time_limit = ini_get('max_execution_time');
		@set_time_limit($current_time_limit);

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

		theme()->getTemplates()->load('Packages');
		theme()->getTemplates()->loadLanguageFile('Packages');

		// $context['package_ftp'] may be set action_backup_display when an error occur
		if (!isset($context['package_ftp']))
		{
			$context['package_ftp'] = array(
				'form_elements_only' => true,
				'server' => '',
				'port' => '',
				'username' => '',
				'path' => '',
				'error' => '',
			);
		}
		$context['skip_security'] = defined('I_KNOW_IT_MAY_BE_UNSAFE');
	}

	/**
	 * Supporting function for the routine maintenance area.
	 *
	 * @event integrate_routine_maintenance, passed $context['routine_actions'] array to allow
	 * addons to add more options
	 * @uses Template Maintenance, sub template maintain_routine
	 */
	public function action_routine()
	{
		global $context, $txt;

		if ($this->_req->getQuery('done', 'trim|strval') === 'recount')
			$context['maintenance_finished'] = $txt['maintain_recount'];

		// set up the sub-template
		$context['sub_template'] = 'maintain_routine';
		$context['routine_actions'] = array(
			'version' => array(
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'maintain', 'sa' => 'routine', 'activity' => 'version']),
				'title' => $txt['maintain_version'],
				'description' => $txt['maintain_version_info'],
				'submit' => $txt['maintain_run_now'],
				'hidden' => array(
					'session_var' => 'session_id',
				)
			),
			'repair' => array(
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'repairboards']),
				'title' => $txt['maintain_errors'],
				'description' => $txt['maintain_errors_info'],
				'submit' => $txt['maintain_run_now'],
				'hidden' => array(
					'session_var' => 'session_id',
					'admin-maint_token_var' => 'admin-maint_token',
				)
			),
			'recount' => array(
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'maintain', 'sa' => 'routine', 'activity' => 'recount']),
				'title' => $txt['maintain_recount'],
				'description' => $txt['maintain_recount_info'],
				'submit' => $txt['maintain_run_now'],
				'hidden' => array(
					'session_var' => 'session_id',
					'admin-maint_token_var' => 'admin-maint_token',
				)
			),
			'logs' => array(
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'maintain', 'sa' => 'routine', 'activity' => 'logs']),
				'title' => $txt['maintain_logs'],
				'description' => $txt['maintain_logs_info'],
				'submit' => $txt['maintain_run_now'],
				'hidden' => array(
					'session_var' => 'session_id',
					'admin-maint_token_var' => 'admin-maint_token',
				)
			),
			'cleancache' => array(
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'maintain', 'sa' => 'routine', 'activity' => 'cleancache']),
				'title' => $txt['maintain_cache'],
				'description' => $txt['maintain_cache_info'],
				'submit' => $txt['maintain_run_now'],
				'hidden' => array(
					'session_var' => 'session_id',
					'admin-maint_token_var' => 'admin-maint_token',
				)
			),
		);

		call_integration_hook('integrate_routine_maintenance', array(&$context['routine_actions']));
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
		if ($this->_req->getQuery('done', 'strval') === 'recountposts')
			$context['maintenance_finished'] = array(
				'errors' => array(sprintf($txt['maintain_done'], $txt['maintain_recountposts'])),
			);

		loadJavascriptFile('suggest.js');

		// Set up the sub-template
		$context['sub_template'] = 'maintain_members';
	}

	/**
	 * Supporting function for the topics maintenance area.
	 *
	 * @event integrate_topics_maintenance, passed $context['topics_actions'] to allow addons
	 * to add additonal topic maintance functions
	 * @uses GenericBoards template, sub template maintain_topics
	 */
	public function action_topics()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/Boards.subs.php');

		// Let's load up the boards in case they are useful.
		$context += getBoardList(array('not_redirection' => true));

		// Include a list of boards per category for easy toggling.
		foreach ($context['categories'] as $cat => &$category)
		{
			$context['boards_in_category'][$cat] = count($category['boards']);
			$category['child_ids'] = array_keys($category['boards']);
		}

		// @todo Hacky!
		$txt['choose_board'] = $txt['maintain_old_all'];
		$context['boards_check_all'] = true;
		theme()->getTemplates()->load('GenericBoards');

		$context['topics_actions'] = array(
			'pruneold' => array(
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'maintain', 'sa' => 'topics', 'activity' => 'pruneold']),
				'title' => $txt['maintain_old'],
				'submit' => $txt['maintain_old_remove'],
				'confirm' => $txt['maintain_old_confirm'],
				'hidden' => array(
					'session_var' => 'session_id',
					'admin-maint_token_var' => 'admin-maint_token',
				)
			),
			'massmove' => array(
				'url' => getUrl('admin', ['action' => 'admin', 'area' => 'maintain', 'sa' => 'topics', 'activity' => 'massmove']),
				'title' => $txt['move_topics_maintenance'],
				'submit' => $txt['move_topics_now'],
				'confirm' => $txt['move_topics_confirm'],
				'hidden' => array(
					'session_var' => 'session_id',
					'admin-maint_token_var' => 'admin-maint_token',
				)
			),
		);

		call_integration_hook('integrate_topics_maintenance', array(&$context['topics_actions']));

		if ($this->_req->getQuery('done', 'strval') === 'purgeold')
			$context['maintenance_finished'] = array(
				'errors' => array(sprintf($txt['maintain_done'], $txt['maintain_old'])),
			);
		elseif ($this->_req->getQuery('done', 'strval') === 'massmove')
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

		$controller = new RepairBoards(new \ElkArte\EventManager());
		$controller->pre_dispatch();
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

		checkSession();
		validateToken('admin-maint');

		// Just wipe the whole cache directory!
		\ElkArte\Cache\Cache::instance()->clean();

		$context['maintenance_finished'] = $txt['maintain_cache'];
	}

	/**
	 * Empties all unimportant logs.
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
	 *
	 * - It requires the admin_forum permission.
	 * - This is needed only for MySQL.
	 * - During the conversion from MEDIUMTEXT to TEXT it check if any of the
	 * posts exceed the TEXT length and if so it aborts.
	 * - This action is linked from the maintenance screen (if it's applicable).
	 * - Accessed by ?action=admin;area=maintain;sa=database;activity=convertmsgbody.
	 *
	 * @uses the convert_msgbody sub template of the Admin template.
	 */
	public function action_convertmsgbody_display()
	{
		global $context, $txt, $modSettings, $time_start;

		// Show me your badge!
		isAllowedTo('admin_forum');
		$db = database();

		if ($db->supportMediumtext() === false)
			return;

		$body_type = '';

		// Find the body column "type" from the message table
		$colData = getMessageTableColumns();
		foreach ($colData as $column)
		{
			if ($column['name'] === 'body')
			{
				$body_type = $column['type'];
				break;
			}
		}

		$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';

		if ($body_type === 'text' || ($body_type !== 'text' && isset($this->_req->post->do_conversion)))
		{
			checkSession();
			validateToken('admin-maint');

			// Make it longer so we can do their limit.
			if ($body_type === 'text')
				resizeMessageTableBody('mediumtext');
			// Shorten the column so we can have a bit (literally per record) less space occupied
			else
				resizeMessageTableBody('text');

			$colData = getMessageTableColumns();
			foreach ($colData as $column)
				if ($column['name'] === 'body')
					$body_type = $column['type'];

			$context['maintenance_finished'] = $txt[$context['convert_to'] . '_title'];
			$context['convert_to'] = $body_type === 'text' ? 'mediumtext' : 'text';
			$context['convert_to_suggest'] = ($body_type !== 'text' && !empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] < 65536);

			return;
		}
		elseif ($body_type !== 'text' && (!isset($this->_req->post->do_conversion) || isset($this->_req->post->cont)))
		{
			checkSession();

			if (empty($this->_req->query->start))
				validateToken('admin-maint');
			else
				validateToken('admin-convertMsg');

			$context['page_title'] = $txt['not_done_title'];
			$context['continue_post_data'] = '';
			$context['continue_countdown'] = 3;
			$context['sub_template'] = 'not_done';
			$increment = 500;
			$id_msg_exceeding = isset($this->_req->post->id_msg_exceeding) ? explode(',', $this->_req->post->id_msg_exceeding) : array();

			$max_msgs = countMessages();
			$start = $this->_req->query->start;

			// Try for as much time as possible.
			detectServer()->setTimeLimit(600);

			while ($start < $max_msgs)
			{
				$id_msg_exceeding = detectExceedingMessages($start, $increment);

				$start += $increment;

				if (microtime(true) - $time_start > 3)
				{
					createToken('admin-convertMsg');
					$context['continue_post_data'] = '
						<input type="hidden" name="' . $context['admin-convertMsg_token_var'] . '" value="' . $context['admin-convertMsg_token'] . '" />
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
						<input type="hidden" name="id_msg_exceeding" value="' . implode(',', $id_msg_exceeding) . '" />';
					$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=convertmsgbody;start=' . $start;
					$context['continue_percent'] = round(100 * $start / $max_msgs);
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
	 *
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
			throw new \ElkArte\Exceptions\Exception('You appear to be running ElkArte in a flat file mode... fantastic!', false);

		// For each table....
		$context['optimized_tables'] = array();
		$db_table = db_table();

		foreach ($tables as $table)
		{
			// Optimize the table!  We use backticks here because it might be a custom table.
			$data_freed = $db_table->optimize($table['table_name']);

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
	 *
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
		require_once(SUBSDIR . '/Topic.subs.php');

		// Validate the request or the loop
		if (!isset($this->_req->query->step))
			validateToken('admin-maint');
		else
			validateToken('admin-boardrecount');

		// For the loop template
		$context['page_title'] = $txt['not_done_title'];
		$context['continue_post_data'] = '';
		$context['continue_countdown'] = 3;
		$context['sub_template'] = 'not_done';

		// Try for as much time as possible.
		detectServer()->setTimeLimit(600);

		// Step the number of topics at a time so things don't time out...
		$this->max_topics = getMaxTopicID();
		$this->increment = (int) min(max(50, ceil($this->max_topics / 4)), 2000);

		// An 8 step process, should be 12 for the admin
		$this->total_steps = 8;
		$this->start = $this->_req->getQuery('start', 'inval', 0);
		$this->step = $this->_req->getQuery('step', 'intval', 0);

		// Get each topic with a wrong reply count and fix it
		if (empty($this->step))
		{
			// let's just do some at a time, though.
			while ($this->start < $this->max_topics)
			{
				recountApprovedMessages($this->start, $this->increment);
				recountUnapprovedMessages($this->start, $this->increment);
				$this->start += $this->increment;

				if (microtime(true) - $time_start > 3)
				{
					$percent = round((100 * $this->start / $this->max_topics) / $this->total_steps);
					$this->_buildContinue($percent, 0);
					return;
				}
			}

			// Done with step 0, reset start for the next one
			$this->start = 0;
		}

		// Update the post count of each board.
		if ($this->step <= 1)
		{
			if (empty($this->start))
				resetBoardsCounter('num_posts');

			while ($this->start < $this->max_topics)
			{
				// Recount the posts
				updateBoardsCounter('posts', $this->start, $this->increment);
				$this->start += $this->increment;

				if (microtime(true) - $time_start > 3)
				{
					$percent = round((200 + 100 * $this->start / $this->max_topics) / $this->total_steps);
					$this->_buildContinue($percent, 1);
					return;
				}
			}

			// Done with step 1, reset start for the next one
			$this->start = 0;
		}

		// Update the topic count of each board.
		if ($this->step <= 2)
		{
			if (empty($this->start))
				resetBoardsCounter('num_topics');

			while ($this->start < $this->max_topics)
			{
				updateBoardsCounter('topics', $this->start, $this->increment);
				$this->start += $this->increment;

				if (microtime(true) - $time_start > 3)
				{
					$percent = round((300 + 100 * $this->start / $this->max_topics) / $this->total_steps);
					$this->_buildContinue($percent, 2);
					return;
				}
			}

			// Done with step 2, reset start for the next one
			$this->start = 0;
		}

		// Update the unapproved post count of each board.
		if ($this->step <= 3)
		{
			if (empty($this->start))
				resetBoardsCounter('unapproved_posts');

			while ($this->start < $this->max_topics)
			{
				updateBoardsCounter('unapproved_posts', $this->start, $this->increment);
				$this->start += $this->increment;

				if (microtime(true) - $time_start > 3)
				{
					$percent = round((400 + 100 * $this->start / $this->max_topics) / $this->total_steps);
					$this->_buildContinue($percent, 3);
					return;
				}
			}

			// Done with step 3, reset start for the next one
			$this->start = 0;
		}

		// Update the unapproved topic count of each board.
		if ($this->step <= 4)
		{
			if (empty($this->start))
				resetBoardsCounter('unapproved_topics');

			while ($this->start < $this->max_topics)
			{
				updateBoardsCounter('unapproved_topics', $this->start, $this->increment);
				$this->start += $this->increment;

				if (microtime(true) - $time_start > 3)
				{
					$percent = round((500 + 100 * $this->start / $this->max_topics) / $this->total_steps);
					$this->_buildContinue($percent, 4);
					return;
				}
			}

			// Done with step 4, reset start for the next one
			$this->start = 0;
		}

		// Get all members with wrong number of personal messages.
		if ($this->step <= 5)
		{
			updatePersonalMessagesCounter();

			if (microtime(true) - $time_start > 3)
			{
				$this->start = 0;
				$percent = round(700 / $this->total_steps);
				$this->_buildContinue($percent, 6);
				return;
			}

			// Done with step 5, reset start for the next one
			$this->start = 0;
		}

		// Any messages pointing to the wrong board?
		if ($this->step <= 6)
		{
			while ($this->start < $modSettings['maxMsgID'])
			{
				updateMessagesBoardID($this->_req->query->start, $this->increment);
				$this->start += $this->increment;

				if (microtime(true) - $time_start > 3)
				{
					$percent = round((700 + 100 * $this->start / $modSettings['maxMsgID']) / $this->total_steps);
					$this->_buildContinue($percent, 6);
					return;
				}
			}

			// Done with step 6, reset start for the next one
			$this->start = 0;
		}

		updateBoardsLastMessage();

		// Update all the basic statistics.
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberStats();
		require_once(SUBSDIR . '/Messages.subs.php');
		updateMessageStats();
		require_once(SUBSDIR . '/Topic.subs.php');
		updateTopicStats();

		// Finally, update the latest event times.
		require_once(SUBSDIR . '/ScheduledTasks.subs.php');
		calculateNextTrigger();

		// Ta-da
		redirectexit('action=admin;area=maintain;sa=routine;done=recount');
	}

	/**
	 * Helper function for teh recount process, build the continue values for
	 * the template
	 *
	 * @param int $percent percent done
	 * @param int $step step we are on
	 */
	private function _buildContinue($percent, $step)
	{
		global $context, $txt;

		createToken('admin-boardrecount');

		$context['continue_post_data'] = '
			<input type="hidden" name="' . $context['admin-boardrecount_token_var'] . '" value="' . $context['admin-boardrecount_token'] . '" />
			<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />';
		$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=' . $step . ';start=' . $this->start;
		$context['continue_percent'] = $percent;
		$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';
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
		global $txt, $context, $modSettings;

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
		$context['forum_version'] = FORUM_VERSION;

		$context['sub_template'] = 'view_versions';
		$context['page_title'] = $txt['admin_version_check'];
		$context['detailed_version_url'] = $modSettings['detailed-version.js'];
	}

	/**
	 * Re-attribute posts to the user sent from the maintenance page.
	 */
	public function action_reattribute_display()
	{
		global $context, $txt;

		checkSession();

		$validator = new \ElkArte\DataValidator();
		$validator->sanitation_rules(array('posts' => 'empty', 'type' => 'trim', 'from_email' => 'trim', 'from_name' => 'trim', 'to' => 'trim'));
		$validator->validation_rules(array('from_email' => 'valid_email', 'from_name' => 'required', 'to' => 'required', 'type' => 'contains[name,email]'));
		$validator->validate($this->_req->post);

		// Fetch the Mr. Clean values
		$our_post = array_replace((array) $this->_req->post, $validator->validation_data());

		// Do we have a valid set of options to continue?
		if (($our_post['type'] === 'name' && !empty($our_post['from_name'])) || ($our_post['type'] === 'email' && !$validator->validation_errors('from_email')))
		{
			// Find the member.
			require_once(SUBSDIR . '/Auth.subs.php');
			$members = findMembers($our_post['to']);

			// No members, no further
			if (empty($members))
				throw new \ElkArte\Exceptions\Exception('reattribute_cannot_find_member');

			$memID = array_shift($members);
			$memID = $memID['id'];

			$email = $our_post['type'] == 'email' ? $our_post['from_email'] : '';
			$membername = $our_post['type'] == 'name' ? $our_post['from_name'] : '';

			// Now call the reattribute function.
			require_once(SUBSDIR . '/Members.subs.php');
			reattributePosts($memID, $email, $membername, !$our_post['posts']);

			$context['maintenance_finished'] = array(
				'errors' => array(sprintf($txt['maintain_done'], $txt['maintain_reattribute_posts'])),
			);
		}
		else
		{
			// Show them the correct error
			if ($our_post['type'] === 'name' && empty($our_post['from_name']))
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
		global $user_info;

		validateToken('admin-maint');

		// Administrators only!
		if (!allowedTo('admin_forum'))
			throw new \ElkArte\Exceptions\Exception('no_dump_database', 'critical');

		checkSession('post');

		// Validate access
		if (defined('I_KNOW_IT_MAY_BE_UNSAFE') === false && $this->_validate_access() === false)
		{
			return $this->action_database();
		}
		else
		{
			require_once(SUBSDIR . '/Admin.subs.php');

			emailAdmins('admin_backup_database', array(
				'BAK_REALNAME' => $user_info['name']
			));
			logAction('database_backup', array('member' => $user_info['id']), 'admin');

			require_once(SOURCEDIR . '/DumpDatabase.php');
			DumpDatabase2();

			// Should not get here as DumpDatabase2 exits
			return true;
		}
	}

	/**
	 * Validates the user can make an FTP connection with the supplied uid/pass
	 *
	 * - Used as an extra layer of security when performing backups
	 */
	private function _validate_access()
	{
		global $context, $txt;

		$ftp = new \ElkArte\Http\FtpConnection($this->_req->post->ftp_server, $this->_req->post->ftp_port, $this->_req->post->ftp_username, $this->_req->post->ftp_password);

		// No errors on the connection, id/pass are good
		if ($ftp->error === false)
		{
			// I know, I know... but a lot of people want to type /home/xyz/... which is wrong, but logical.
			if (!$ftp->chdir($this->_req->post->ftp_path))
				$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $this->_req->post->ftp_path));
		}

		// If we had an error...
		if ($ftp->error !== false)
		{
			theme()->getTemplates()->loadLanguageFile('Packages');
			$ftp_error = $ftp->last_message === null ? (isset($txt['package_ftp_' . $ftp->error]) ? $txt['package_ftp_' . $ftp->error] : '') : $ftp->last_message;

			// Fill the boxes for a FTP connection with data from the previous attempt
			$context['package_ftp'] = array(
				'form_elements_only' => 1,
				'server' => $this->_req->post->ftp_server,
				'port' => $this->_req->post->ftp_port,
				'username' => $this->_req->post->ftp_username,
				'path' => $this->_req->post->ftp_path,
				'error' => empty($ftp_error) ? null : $ftp_error,
			);

			return false;
		}

		return true;
	}

	/**
	 * Removing old and inactive members.
	 */
	public function action_purgeinactive_display()
	{
		global $context, $txt;

		checkSession();
		validateToken('admin-maint');

		// Start with checking and cleaning what was sent
		$validator = new \ElkArte\DataValidator();
		$validator->sanitation_rules(array('maxdays' => 'intval'));
		$validator->validation_rules(array('maxdays' => 'required', 'groups' => 'isarray', 'del_type' => 'required'));

		// Validator says, you can pass or not
		if ($validator->validate($this->_req->post))
		{
			// Get the clean data
			$our_post = array_replace((array) $this->_req->post, $validator->validation_data());

			require_once(SUBSDIR . '/Maintenance.subs.php');
			require_once(SUBSDIR . '/Members.subs.php');

			$groups = array();
			foreach ($our_post['groups'] as $id => $dummy)
				$groups[] = (int) $id;

			$time_limit = (time() - ($our_post['maxdays'] * 24 * 3600));
			$members = purgeMembers($our_post['del_type'], $groups, $time_limit);
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

		isAllowedTo('admin_forum');
		checkSession('post', 'admin');

		// No boards at all?  Forget it then :/.
		if (empty($this->_req->post->boards))
			redirectexit('action=admin;area=maintain;sa=topics');

		$boards = array_keys($this->_req->post->boards);

		if (!isset($this->_req->post->delete_type) || !in_array($this->_req->post->delete_type, array('moved', 'nothing', 'locked')))
			$delete_type = 'nothing';
		else
			$delete_type = $this->_req->post->delete_type;

		$exclude_stickies = isset($this->_req->post->delete_old_not_sticky);

		// @todo what is the minimum for maxdays? Maybe throw an error?
		$older_than = time() - 3600 * 24 * max($this->_req->getPost('maxdays', 'intval', 0), 1);

		require_once(SUBSDIR . '/Topic.subs.php');
		removeOldTopics($boards, $delete_type, $exclude_stickies, $older_than);

		// Log an action into the moderation log.
		logAction('pruned', array('days' => max($this->_req->getPost('maxdays', 'intval', 0), 1)));

		redirectexit('action=admin;area=maintain;sa=topics;done=purgeold');
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
		$context['start'] = $this->_req->getQuery('start', 'intval', 0);

		// First time we do this?
		$id_board_from = $this->_req->getPost('id_board_from', 'intval', (int) $this->_req->query->id_board_from);
		$id_board_to = $this->_req->getPost('id_board_to', 'intval', (int) $this->_req->query->id_board_to);

		// No boards then this is your stop.
		if (empty($id_board_from) || empty($id_board_to))
			return;

		// These will be needed
		require_once(SUBSDIR . '/Maintenance.subs.php');
		require_once(SUBSDIR . '/Topic.subs.php');

		// How many topics are we moving?
		if (!isset($this->_req->query->totaltopics))
			$total_topics = countTopicsFromBoard($id_board_from);
		else
		{
			$total_topics = (int) $this->_req->query->totaltopics;
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
		\ElkArte\Cache\Cache::instance()->remove('board-' . $id_board_from);
		\ElkArte\Cache\Cache::instance()->remove('board-' . $id_board_to);

		redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
	}

	/**
	 * Generates a list of integration hooks for display
	 *
	 * - Accessed through ?action=admin;area=maintain;sa=hooks;
	 * - Allows for removal or disabling of selected hooks
	 */
	public function action_hooks()
	{
		global $context, $txt;

		require_once(SUBSDIR . '/AddonSettings.subs.php');

		$context['filter_url'] = '';
		$context['current_filter'] = '';

		// Get the list of the current system hooks, filter them if needed
		$currentHooks = get_integration_hooks();
		if (isset($this->_req->query->filter) && in_array($this->_req->query->filter, array_keys($currentHooks)))
		{
			$context['filter_url'] = ';filter=' . $this->_req->query->filter;
			$context['current_filter'] = $this->_req->query->filter;
		}

		$list_options = array(
			'id' => 'list_integration_hooks',
			'title' => $txt['maintain_sub_hooks_list'],
			'items_per_page' => 20,
			'base_href' => getUrl('admin', ['action' => 'admin', 'area' => 'maintain', 'sa' => 'hooks', $context['filter_url'], '{session_data}']),
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
						'function' => function ($data) {
							global $txt;

							if (!empty($data['included_file']))
								return $txt['hooks_field_function'] . ': ' . $data['real_function'] . '<br />' . $txt['hooks_field_included_file'] . ': ' . $data['included_file'];
							else
								return $data['real_function'];
						},
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
						'function' => function ($data) {
							return '<i class="icon i-post_moderation_' . $data['status'] . '" title="' . $data['img_text'] . '"></i>';
						},
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
					'value' => $txt['hooks_disable_legend'] . ':
					<ul>
						<li>
							<i class="icon i-post_moderation_allow" title="' . $txt['hooks_active'] . '"></i>' . $txt['hooks_disable_legend_exists'] . '
						</li>
						<li>
							<i class="icon i-post_moderation_moderate" title="' . $txt['hooks_disabled'] . '"></i>' . $txt['hooks_disable_legend_disabled'] . '
						</li>
						<li>
							<i class="icon i-post_moderation_deny" title="' . $txt['hooks_missing'] . '"></i>' . $txt['hooks_disable_legend_missing'] . '
						</li>
					</ul>'
				),
			),
		);

		createList($list_options);

		$context['page_title'] = $txt['maintain_sub_hooks_list'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'list_integration_hooks';
	}

	/**
	 * Recalculate all members post counts
	 *
	 * What it does:
	 *
	 * - It requires the admin_forum permission.
	 * - Recounts all posts for members found in the message table
	 * - Updates the members post count record in the members table
	 * - Honors the boards post count flag
	 * - Does not count posts in the recycle bin
	 * - Zeros post counts for all members with no posts in the message table
	 * - Runs as a delayed loop to avoid server overload
	 * - Uses the not_done template in Admin.template
	 * - Redirects back to action=admin;area=maintain;sa=members when complete.
	 * - Accessed via ?action=admin;area=maintain;sa=members;activity=recountposts
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
		$start = $this->_req->getQuery('start', 'intval', 0);

		// Ask for some extra time, on big boards this may take a bit
		detectServer()->setTimeLimit(600);
		\ElkArte\Debug::instance()->off();

		// The functions here will come in handy
		require_once(SUBSDIR . '/Maintenance.subs.php');

		// Only run this query if we don't have the total number of members that have posted
		if (!isset($this->_req->session->total_members) || $start === 0)
		{
			validateToken('admin-maint');
			$total_members = countContributors();
			$_SESSION['total_member'] = $total_members;
		}
		else
		{
			validateToken('admin-recountposts');
			$total_members = $this->_req->session->total_members;
		}

		// Lets get the next group of members and determine their post count
		// (from the boards that have post count enabled of course).
		$total_rows = updateMembersPostCount($start, $increment);

		// Continue?
		if ($total_rows == $increment)
		{
			createToken('admin-recountposts');

			$start += $increment;
			$context['continue_get_data'] = '?action=admin;area=maintain;sa=members;activity=recountposts;start=' . $start;
			$context['continue_percent'] = round(100 * $start / $total_members);
			$context['not_done_title'] = $txt['not_done_title'] . ' (' . $context['continue_percent'] . '%)';
			$context['continue_post_data'] = '<input type="hidden" name="' . $context['admin-recountposts_token_var'] . '" value="' . $context['admin-recountposts_token'] . '" />
				<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />';

			\ElkArte\Debug::instance()->on();
			return;
		}

		// No countable posts? set posts counter to 0
		updateZeroPostMembers();

		\ElkArte\Debug::instance()->on();
		// All done, clean up and go back to maintenance
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
		if (isset($this->_req->query->filter))
			$context['filter'] = $this->_req->query->filter;

		return integration_hooks_count($context['filter']);
	}

	/**
	 * Callback for createList(). Called by action_hooks
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 *
	 * @return array
	 */
	public function list_getIntegrationHooks($start, $items_per_page, $sort)
	{
		return list_integration_hooks_data($start, $items_per_page, $sort);
	}
}
