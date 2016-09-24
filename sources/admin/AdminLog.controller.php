<?php

/**
 * This manages the admin logs, and forwards to display, pruning,
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Admin logs controller.
 *
 * What it does:
 * - This class manages logs, and forwards to display, pruning,
 * and other actions on logs.
 *
 * @package AdminLog
 */
class AdminLog_Controller extends Action_Controller
{
	/**
	 * Pruning Settings form
	 * @var Settings_Form
	 */
	protected $_pruningSettings;

	/**
	 * This method decides which log to load.
	 * Accessed by ?action=admin;area=logs
	 *
	 * @uses _initPruningSettingsForm
	 */
	public function action_index()
	{
		global $context, $txt, $scripturl, $modSettings;

		// These are the logs they can load.
		$log_functions = array(
			'errorlog' => array(
				'function' => 'action_index',
				'controller' => 'ManageErrors_Controller'),
			'adminlog' => array(
				'function' => 'action_log',
				'controller' => 'Modlog_Controller'),
			'modlog' => array(
				'function' => 'action_log',
				'controller' => 'Modlog_Controller',
				'disabled' => !in_array('ml', $context['admin_features'])),
			'badbehaviorlog' => array(
				'function' => 'action_log',
				'disabled' => empty($modSettings['badbehavior_enabled']),
				'controller' => 'BadBehavior_Controller'),
			'banlog' => array(
				'function' => 'action_log',
				'controller' => 'ManageBans_Controller'),
			'spiderlog' => array(
				'function' => 'action_logs',
				'controller' => 'ManageSearchEngines_Controller'),
			'tasklog' => array(
				'function' => 'action_log',
				'controller' => 'ManageScheduledTasks_Controller'),
			'pruning' => array(
				'init' => '_initPruningSettingsForm',
				'display' => 'action_pruningSettings_display'),
		);

		// Setup the tabs.
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

		// Give integration a way to add items
		call_integration_hook('integrate_manage_logs', array(&$log_functions));
		$sub_action = isset($this->_req->query->sa, $log_functions[$this->_req->query->sa]) && empty($log_functions[$this->_req->query->sa]['disabled']) ? $this->_req->query->sa : 'errorlog';

		// If it's not got a sa set it must have come here for first time, pretend error log should be reversed.
		if (!isset($this->_req->query->sa))
			$this->_req->query->desc = true;

		// figure out what to call
		if (isset($log_functions[$sub_action]['file']))
		{
			// different file
			require_once(ADMINDIR . '/' . $log_functions[$sub_action]['file']);
		}

		if (isset($log_functions[$sub_action]['controller']))
		{
			// if we have an object oriented controller, call its method
			$controller = new $log_functions[$sub_action]['controller'](new Event_Manager());
			$controller->pre_dispatch();
			$controller->{$log_functions[$sub_action]['function']}();
		}
		elseif (isset($log_functions[$sub_action]['function']))
		{
			// procedural: call the function
			$log_functions[$sub_action]['function']();
		}
		else
		{
			// our own method then

			// initialize the form
			$this->{$log_functions[$sub_action]['init']}();

			// call the action handler
			// this is hardcoded now, to be fixed
			$this->{$log_functions[$sub_action]['display']}();
		}
	}

	/**
	 * Allow to edit the settings on the pruning screen.
	 *
	 * Uses the _pruningSettings form.
	 */
	public function action_pruningSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings;

		// Make sure we understand what's going on.
		loadLanguage('ManageSettings');

		$context['page_title'] = $txt['pruning_title'];

		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize settings
		$settingsForm->setConfigVars($this->_settings());

		return $settingsForm->setConfigVars($config_vars);

		call_integration_hook('integrate_prune_settings');

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			$savevar = array(
				array('text', 'pruningOptions')
			);

			if (!empty($this->_req->post->pruningOptions))
			{
				$vals = array();
				foreach ($config_vars as $index => $dummy)
				{
					if (!is_array($dummy) || $index == 'pruningOptions')
						continue;

					$vals[] = empty($this->_req->post->{$dummy[1]}) || $this->_req->post->{$dummy[1]} < 0 ? 0 : $this->_req->getPost($dummy[1], 'intval');
				}
				$_POST['pruningOptions'] = implode(',', $vals);
			}
			else
				$_POST['pruningOptions'] = '';

			$settingsForm->setConfigVars($savevar);
			$settingsForm->setConfigValues((array) $_POST);
			$settingsForm->save();
			redirectexit('action=admin;area=logs;sa=pruning');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=logs;save;sa=pruning';
		$context['settings_title'] = $txt['pruning_title'];
		$context['sub_template'] = 'show_settings';

		// Get the actual values
		if (!empty($modSettings['pruningOptions']))
			list ($modSettings['pruneErrorLog'], $modSettings['pruneModLog'], $modSettings['pruneBanLog'], $modSettings['pruneReportLog'], $modSettings['pruneScheduledTaskLog'], $modSettings['pruneBadbehaviorLog'], $modSettings['pruneSpiderHitLog']) = array_pad(explode(',', $modSettings['pruningOptions']), 7, 0);
		else
			$modSettings['pruneErrorLog'] = $modSettings['pruneModLog'] = $modSettings['pruneBanLog'] = $modSettings['pruneReportLog'] = $modSettings['pruneScheduledTaskLog'] = $modSettings['pruneBadbehaviorLog'] = $modSettings['pruneSpiderHitLog'] = 0;

		$settingsForm->prepare();
	}

	/**
	 * Returns the configuration settings for pruning logs.
	 */
	private function _settings()
	{
		global $txt;

		$config_vars = array(
			// Even do the pruning?
			// The array indexes are there so we can remove/change them before saving.
			'pruningOptions' => array('check', 'pruningOptions'),
		'',
			// Various logs that could be pruned.
			array('int', 'pruneErrorLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Error log.
			array('int', 'pruneModLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Moderation log.
			array('int', 'pruneBanLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Ban hit log.
			array('int', 'pruneReportLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Report to moderator log.
			array('int', 'pruneScheduledTaskLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Log of the scheduled tasks and how long they ran.
			array('int', 'pruneBadbehaviorLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Bad Behavior log.
			array('int', 'pruneSpiderHitLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_to_disable']), // Log of the scheduled tasks and how long they ran.
			// If you add any additional logs make sure to add them after this point.  Additionally, make sure you add them to the weekly scheduled task.
		);

		return $config_vars;
	}

	/**
	 * Return the search engine settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}
