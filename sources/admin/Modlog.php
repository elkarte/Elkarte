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
 * The moderation log is this file's only job.
 * It views it, and that's about all it does.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Admin and moderation log controller.
 * Depending on permissions, this class will display and allow to act on the log
 * for administrators or for moderators.
 */
class Modlog_Controller
{
	/**
	 * Default method for this controller.
	 */
	public function action_index()
	{
		// we haz nothing to do. :P
		$this->action_modlog();
	}

	/**
	 * Prepares the information from the moderation log for viewing.
	 * Show the moderation log, or admin log...
	 * Disallows the deletion of events within twenty-four hours of now.
	 * Requires the admin_forum permission for admin log.
	 * Accessed via ?action=moderate;area=modlog.
	 *
	 * @uses Modlog template, main sub-template.
	 */
	public function action_modlog()
	{
		global $txt, $context, $scripturl;

		require_once(SUBSDIR . '/Modlog.subs.php');
		// Are we looking at the moderation log or the administration log.
		$context['log_type'] = isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'adminlog' ? 3 : 1;
		if ($context['log_type'] == 3)
			isAllowedTo('admin_forum');

		// These change dependant on whether we are viewing the moderation or admin log.
		if ($context['log_type'] == 3 || $_REQUEST['action'] == 'admin')
			$context['url_start'] = '?action=admin;area=logs;sa=' . ($context['log_type'] == 3 ? 'adminlog' : 'modlog') . ';type=' . $context['log_type'];
		else
			$context['url_start'] = '?action=moderate;area=modlog;type=' . $context['log_type'];

		$context['can_delete'] = allowedTo('admin_forum');

		loadLanguage('Modlog');

		$context['page_title'] = $context['log_type'] == 3 ? $txt['modlog_admin_log'] : $txt['modlog_view'];

		// The number of entries to show per page of log file.
		$context['displaypage'] = 30;
		// Amount of hours that must pass before allowed to delete file.
		$context['hoursdisable'] = 24;

		// Handle deletion...
		if (isset($_POST['removeall']) && $context['can_delete'])
		{
			checkSession();
			validateToken('mod-ml');
			deleteLogAction($context['log_type'], $context['hoursdisable']);
		}
		elseif (!empty($_POST['remove']) && isset($_POST['delete']) && $context['can_delete'])
		{
			checkSession();
			validateToken('mod-ml');
			deleteLogAction($context['log_type'], $context['hoursdisable'], $_POST['delete']);
		}

		// Do the column stuff!
		$sort_types = array(
			'action' =>'lm.action',
			'time' => 'lm.log_time',
			'member' => 'mem.real_name',
			'group' => 'mg.group_name',
			'ip' => 'lm.ip',
		);

		// Setup the direction stuff...
		$context['order'] = isset($_REQUEST['sort']) && isset($sort_types[$_REQUEST['sort']]) ? $_REQUEST['sort'] : 'time';

		// If we're coming from a search, get the variables.
		if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search']))
		{
			$search_params = base64_decode(strtr($_REQUEST['params'], array(' ' => '+')));
			$search_params = @unserialize($search_params);
		}

		// This array houses all the valid search types.
		$searchTypes = array(
			'action' => array('sql' => 'lm.action', 'label' => $txt['modlog_action']),
			'member' => array('sql' => 'mem.real_name', 'label' => $txt['modlog_member']),
			'group' => array('sql' => 'mg.group_name', 'label' => $txt['modlog_position']),
			'ip' => array('sql' => 'lm.ip', 'label' => $txt['modlog_ip'])
		);

		if (!isset($search_params['string']) || (!empty($_REQUEST['search']) && $search_params['string'] != $_REQUEST['search']))
			$search_params_string = empty($_REQUEST['search']) ? '' : $_REQUEST['search'];
		else
			$search_params_string = $search_params['string'];

		if (isset($_REQUEST['search_type']) || empty($search_params['type']) || !isset($searchTypes[$search_params['type']]))
			$search_params_type = isset($_REQUEST['search_type']) && isset($searchTypes[$_REQUEST['search_type']]) ? $_REQUEST['search_type'] : (isset($searchTypes[$context['order']]) ? $context['order'] : 'member');
		else
			$search_params_type = $search_params['type'];

		$search_params_column = $searchTypes[$search_params_type]['sql'];
		$search_params = array(
			'string' => $search_params_string,
			'type' => $search_params_type,
		);

		// Setup the search context.
		$context['search_params'] = empty($search_params['string']) ? '' : base64_encode(serialize($search_params));
		$context['search'] = array(
			'string' => $search_params['string'],
			'type' => $search_params['type'],
			'label' => $searchTypes[$search_params_type]['label'],
		);

		// If they are searching by action, then we must do some manual intervention to search in their language!
		if ($search_params['type'] == 'action' && !empty($search_params['string']))
		{
			// For the moment they can only search for ONE action!
			foreach ($txt as $key => $text)
			{
				if (substr($key, 0, 10) == 'modlog_ac_' && strpos($text, $search_params['string']) !== false)
				{
					$search_params['string'] = substr($key, 10);
					break;
				}
			}
		}

		require_once(SUBSDIR . '/List.subs.php');

		// This is all the information required for a watched user listing.
		$listOptions = array(
			'id' => 'moderation_log_list',
			'width' => '100%',
			'items_per_page' => $context['displaypage'],
			'no_items_label' => $txt['modlog_' . ($context['log_type'] == 3 ? 'admin_log_' : '') . 'no_entries_found'],
			'base_href' => $scripturl . $context['url_start'] . (!empty($context['search_params']) ? ';params=' . $context['search_params'] : ''),
			'default_sort_col' => 'time',
			'get_items' => array(
				'function' => 'list_getModLogEntries',
				'params' => array(
					(!empty($search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
					array('sql_type' => $search_params_column, 'search_string' => $search_params['string']),
					$context['log_type'],
				),
			),
			'get_count' => array(
				'function' => 'list_getModLogEntryCount',
				'params' => array(
					(!empty($search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
					array('sql_type' => $search_params_column, 'search_string' => $search_params['string']),
					$context['log_type'],
				),
			),
			// This assumes we are viewing by user.
			'columns' => array(
				'action' => array(
					'header' => array(
						'value' => $txt['modlog_action'],
						'class' => 'lefttext first_th',
					),
					'data' => array(
						'db' => 'action_text',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'lm.action',
						'reverse' => 'lm.action DESC',
					),
				),
				'time' => array(
					'header' => array(
						'value' => $txt['modlog_date'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'time',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'lm.log_time DESC',
						'reverse' => 'lm.log_time',
					),
				),
				'moderator' => array(
					'header' => array(
						'value' => $txt['modlog_member'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'moderator_link',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					),
				),
				'position' => array(
					'header' => array(
						'value' => $txt['modlog_position'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'position',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					),
				),
				'ip' => array(
					'header' => array(
						'value' => $txt['modlog_ip'],
						'class' => 'lefttext',
					),
					'data' => array(
						'db' => 'ip',
						'class' => 'smalltext',
					),
					'sort' => array(
						'default' => 'lm.ip',
						'reverse' => 'lm.ip DESC',
					),
				),
				'delete' => array(
					'header' => array(
						'value' => '<input type="checkbox" name="all" class="input_check" onclick="invertAll(this, this.form);" />',
						'class' => 'centertext',
					),
					'data' => array(
						'function' => create_function('$entry', '
							return \'<input type="checkbox" class="input_check" name="delete[]" value="\' . $entry[\'id\'] . \'"\' . ($entry[\'editable\'] ? \'\' : \' disabled="disabled"\') . \' />\';
						'),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . $context['url_start'],
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
					'params' => $context['search_params']
				),
				'token' => 'mod-ml',
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '
						' . $txt['modlog_search'] . ' (' . $txt['modlog_by'] . ': ' . $context['search']['label'] . '):
						<input type="text" name="search" size="18" value="' . Util::htmlspecialchars($context['search']['string']) . '" class="input_text" />
						<input type="submit" name="is_search" value="' . $txt['modlog_go'] . '" class="button_submit" style="float:none" />
						' . ($context['can_delete'] ? '&nbsp;|
						<input type="submit" name="remove" value="' . $txt['modlog_remove'] . '" onclick="return confirm(\'' . $txt['modlog_remove_selected_confirm'] . '\');" class="button_submit" />
						<input type="submit" name="removeall" value="' . $txt['modlog_removeall'] . '" onclick="return confirm(\'' . $txt['modlog_remove_all_confirm'] . '\');" class="button_submit" />' : ''),
					'class' => 'floatright',
				),
			),
		);

		createToken('mod-ml');

		// Create the watched user list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'moderation_log_list';
	}
}