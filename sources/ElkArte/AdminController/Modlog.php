<?php

/**
 * The moderation log is this file's only job. It views it, and that's about all it does.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;

/**
 * Admin and moderation log controller.
 * Depending on permissions, this class will display and allow acting on the log
 * for administrators or for moderators.
 */
class Modlog extends AbstractController
{
	/**
	 * Default method for this controller.
	 *
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		// We haz nothing to do. :P
		$this->action_log();
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
	public function action_log(): void
	{
		global $txt, $context;

		require_once(SUBSDIR . '/Modlog.subs.php');

		// Are we looking at the moderation log or the administration log?
		$context['log_type'] = $this->_req->compareQuery('sa', 'adminlog') ? 3 : 1;

		// Trying to view the admin log, let's check you can.
		if ($context['log_type'] === 3)
		{
			isAllowedTo('admin_forum');
		}

		// These change dependent on whether we are viewing the moderation or admin log.
		if ($context['log_type'] === 3 || $this->_req->query->action === 'admin')
		{
			$context['url_start'] = getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => ($context['log_type'] == 3 ? 'adminlog' : 'modlog'), 'type' => $context['log_type']]);
		}
		else
		{
			$context['url_start'] = getUrl('action', ['action' => 'moderate', 'area' => 'modlog', 'type' => $context['log_type']]);
		}

		$context['can_delete'] = allowedTo('admin_forum');

		Txt::load('Modlog');

		$context['page_title'] = $context['log_type'] === 3 ? $txt['modlog_admin_log'] : $txt['modlog_view'];

		// The number of entries to show per page of a log file.
		$context['displaypage'] = 30;

		// Number of hours that must pass before allowed to empty the file.
		$context['hoursdisable'] = 24;

		// Handle deletion...
		if (isset($this->_req->post->removeall) && $context['can_delete'])
		{
			checkSession();
			validateToken('mod-ml');
			deleteLogAction($context['log_type'], $context['hoursdisable']);
		}
		elseif (!empty($this->_req->post->remove) && isset($this->_req->post->delete) && $context['can_delete'])
		{
			checkSession();
			validateToken('mod-ml');
			deleteLogAction($context['log_type'], $context['hoursdisable'], $this->_req->post->delete);
		}

		// If we're coming from a search, get the variables.
		$isSearch = $this->_req->getPost('is_search', 'trim', '');
		$searchParams = $this->_req->getPost('params', 'trim', '');
		$sort = $this->_req->getQuery('sort', 'trim', 'member');
		$search = $this->_req->getPost('search', 'trim', '');
		$searchType = $this->_req->getPost('search_type', 'trim', null);

		if (!empty($searchParams) && empty($isSearch))
		{
			$search_params = base64_decode(strtr($searchParams, [' ' => '+']));
			$search_params = @json_decode($search_params, true);
		}

		// This array houses all the valid quick search types.
		$searchTypes = [
			'action' => ['sql' => 'lm.action', 'label' => $txt['modlog_action']],
			'member' => ['sql' => 'mem.real_name', 'label' => $txt['modlog_member']],
			'position' => ['sql' => 'mg.group_name', 'label' => $txt['modlog_position']],
			'ip' => ['sql' => 'lm.ip', 'label' => $txt['modlog_ip']]
		];

		// Set up the allowed search
		$context['order'] = isset($searchTypes[$sort]) ? $sort : 'member';

		if (!isset($search_params['string']) || (!empty($search) && $search_params['string'] !== $search))
		{
			$search_params_string = $search;
		}
		else
		{
			$search_params_string = $search_params['string'];
		}

		if (isset($searchType) || empty($search_params['type']) || !isset($searchTypes[$search_params['type']]))
		{
			$search_params_type = isset($searchType, $searchTypes[$searchType]) ? $searchType : $context['order'];
		}
		else
		{
			$search_params_type = $search_params['type'];
		}

		$search_params_column = $searchTypes[$search_params_type]['sql'];
		$search_params = [
			'string' => $search_params_string,
			'type' => $search_params_type,
		];

		// Set up the search context.
		$context['search_params'] = empty($search_params['string']) ? '' : base64_encode(json_encode($search_params));
		$context['search'] = [
			'string' => $search_params['string'],
			'type' => $search_params['type'],
			'label' => $searchTypes[$search_params_type]['label'],
		];

		// If they are searching by action, then we must do some manual intervention to search in their language!
		if ($search_params['type'] === 'action' && !empty($search_params['string']))
		{
			// Build a regex which looks for the words
			$regex = '';
			$search = explode(' ', $search_params['string']);
			foreach ($search as $word)
			{
				$regex .= '(?=[\w\s]*' . $word . ')';
			}

			// For the moment they can only search for ONE action!
			foreach ($txt as $key => $text)
			{
				if (str_starts_with($key, 'modlog_ac_') && preg_match('~' . $regex . '~i', $text))
				{
					$search_params['string'] = substr($key, 10);
					break;
				}
			}
		}

		// This is all the information required for a moderation/admin log listing.
		$listOptions = [
			'id' => 'moderation_log_list',
			'width' => '100%',
			'items_per_page' => $context['displaypage'],
			'no_items_label' => $txt['modlog_' . ($context['log_type'] == 3 ? 'admin_log_' : '') . 'no_entries_found'],
			'base_href' => $context['url_start'],
			'default_sort_col' => 'time',
			'get_items' => [
				'function' => fn($start, $items_per_page, $sort, $query_string, $query_params, $log_type) => $this->getModLogEntries($start, $items_per_page, $sort, $query_string, $query_params, $log_type),
				'params' => [
					(empty($search_params['string']) ? '' : ' INSTR({raw:sql_type}, {string:search_string})'),
					['sql_type' => $search_params_column, 'search_string' => $search_params['string']],
					$context['log_type'],
				],
			],
			'get_count' => [
				'function' => fn($query_string, $query_params, $log_type) => $this->getModLogEntryCount($query_string, $query_params, $log_type),
				'params' => [
					(empty($search_params['string']) ? '' : ' INSTR({raw:sql_type}, {string:search_string})'),
					['sql_type' => $search_params_column, 'search_string' => $search_params['string']],
					$context['log_type'],
				],
			],
			'columns' => [
				'action' => [
					'header' => [
						'value' => $txt['modlog_action'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'action_text',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'lm.action',
						'reverse' => 'lm.action DESC',
					],
				],
				'time' => [
					'header' => [
						'value' => $txt['modlog_date'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'time',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'lm.log_time DESC',
						'reverse' => 'lm.log_time',
					],
				],
				'moderator' => [
					'header' => [
						'value' => $txt['modlog_member'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'moderator_link',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					],
				],
				'position' => [
					'header' => [
						'value' => $txt['modlog_position'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'position',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					],
				],
				'ip' => [
					'header' => [
						'value' => $txt['modlog_ip'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'ip',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'lm.ip',
						'reverse' => 'lm.ip DESC',
					],
				],
				'delete' => [
					'header' => [
						'value' => '<input type="checkbox" name="all" class="input_check" onclick="invertAll(this, this.form);" />',
						'class' => 'centertext',
					],
					'data' => [
						'function' => static fn($entry) => '<input type="checkbox" name="delete[]" value="' . $entry['id'] . '"' . ($entry['editable'] ? '' : ' disabled="disabled"') . ' />',
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => $context['url_start'],
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					$context['session_var'] => $context['session_id'],
					'params' => $context['search_params']
				],
				'token' => 'mod-ml',
			],
			'additional_rows' => [
				[
					'class' => 'submitbutton',
					'position' => 'below_table_data',
					'value' => '
						' . $txt['modlog_search'] . ' (' . $txt['modlog_by'] . ': ' . $context['search']['label'] . ')
						<input type="text" name="search" size="18" value="' . Util::htmlspecialchars($context['search']['string']) . '" class="input_text" />
						<input type="submit" name="is_search" value="' . $txt['modlog_go'] . '" />
						' . ($context['can_delete'] ? '|&nbsp;
						<input type="submit" name="remove" value="' . $txt['modlog_remove'] . '" onclick="return confirm(\'' . $txt['modlog_remove_selected_confirm'] . '\');" />
						<input type="submit" name="removeall" value="' . $txt['modlog_removeall'] . '" onclick="return confirm(\'' . $txt['modlog_remove_all_confirm'] . '\');"/>' : ''),
				],
			],
		];

		createToken('mod-ml');

		// Create the log listing
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'moderation_log_list';
	}

	/**
	 * Callback for createList()
	 * Returns a list of moderation log entries
	 * Uses list_getModLogEntries in modlog subs
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param string $query_string
	 * @param array $query_params
	 * @param int $log_type
	 *
	 * @return array
	 */
	public function getModLogEntries(int $start, int $items_per_page, string $sort, string $query_string, array $query_params, int $log_type): array
	{
		// Get all entries of $log_type
		return list_getModLogEntries($start, $items_per_page, $sort, $query_string, $query_params, $log_type);
	}

	/**
	 * Callback for createList()
	 * Returns a count of moderation/admin log entries
	 * Uses list_getModLogEntryCount in modlog subs
	 *
	 * @param string $query_string
	 * @param array $query_params
	 * @param int $log_type
	 *
	 * @return int number of entries
	 */
	public function getModLogEntryCount(string $query_string, array $query_params, int $log_type): int
	{
		// Get the count of our solved topic entries
		return list_getModLogEntryCount($query_string, $query_params, $log_type);
	}
}
