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
 * This file, unpredictable as this might be, handles basic administration.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Admin logs controller.
 * This class manages logs, and forwards to display, pruning,
 *  and other actions on logs.
 */
class AdminLog_Controller
{
	/**
 	 * This method decides which log to load.
 	 * Accessed by ?action=admin;area=logs
 	 */
	function action_index()
	{
		global $context, $txt, $scripturl, $modSettings;

		// These are the logs they can load.
		$log_functions = array(
			'errorlog' => array('ManageErrors.php', 'action_log', 'controller' => 'ManageErrors_Controller'),
			'adminlog' => array('Modlog.php', 'action_modlog', 'controller' => 'Modlog_Controller'),
			'modlog' => array('Modlog.php', 'action_modlog', 'disabled' => !in_array('ml', $context['admin_features']), 'controller' => 'Modlog_Controller'),
			'badbehaviorlog' => array('ManageBadBehavior.php', 'action_badbehaviorlog', 'disabled' => empty($modSettings['badbehavior_enabled']), 'controller' => 'ManageBadBehavior_Controller'),
			'banlog' => array('ManageBans.php', 'action_log', 'controller' => 'ManageBans_Controller'),
			'spiderlog' => array('ManageSearchEngines.php', 'action_logs', 'ManageSearchEngines_Controller'),
			'action_log' => array('ManageScheduledTasks.php', 'action_log', 'controller' => 'ManageScheduledTasks_Controller'),
			'pruning' => array('ManageSettings.php', 'ModifyPruningSettings'),
		);

		call_integration_hook('integrate_manage_logs', array(&$log_functions));

		$sub_action = isset($_REQUEST['sa']) && isset($log_functions[$_REQUEST['sa']]) && empty($log_functions[$_REQUEST['sa']]['disabled']) ? $_REQUEST['sa'] : 'errorlog';
		// If it's not got a sa set it must have come here for first time, pretend error log should be reversed.
		if (!isset($_REQUEST['sa']))
			$_REQUEST['desc'] = true;

		// Setup some tab stuff.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['logs'],
			'help' => '',
			'description' => $txt['maintain_info'],
			'tabs' => array(
				'errorlog' => array(
					'url' => $scripturl . '?action=admin;area=logs;sa=errorlog;desc',
					'description' => sprintf($txt['errlog_desc'], $txt['remove']),
				),
				'adminlog' => array(
					'description' => $txt['admin_log_desc'],
				),
				'modlog' => array(
					'description' => $txt['moderation_log_desc'],
				),
				'banlog' => array(
					'description' => $txt['ban_log_description'],
				),
				'spiderlog' => array(
					'description' => $txt['spider_log_desc'],
				),
				'tasklog' => array(
					'description' => $txt['scheduled_log_desc'],
				),
				'badbehaviorlog' => array(
					'description' => $txt['badbehavior_log_desc'],
				),
				'pruning' => array(
					'description' => $txt['pruning_log_desc'],
				),
			),
		);

		require_once(ADMINDIR . '/' . $log_functions[$sub_action][0]);
		if (isset($log_functions[$sub_action]['controller']))
		{
			// if we have an object oriented controller, call its method
			$controller = new $log_functions[$sub_action]['controller']();
			$controller->{$log_functions[$sub_action][1]}();
		}
		else
		{
			// procedural: call the function
			$log_functions[$sub_action][1]();
		}
	}
}