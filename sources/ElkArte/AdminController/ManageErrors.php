<?php

/**
 * The main purpose of this file is to show a list of all errors that were
 * logged on the forum and allow filtering and deleting them.
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
use ElkArte\Errors\Log;
use ElkArte\Languages\Txt;
use ElkArte\MembersList;

/**
 * ManageErrors controller, administration of error log.
 */
class ManageErrors extends AbstractController
{
	/** @var Log */
	private Log $errorLog;

	/**
	 * Calls the right handler.
	 * Requires admin_forum permission.
	 *
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		// Check for the administrative permission to do this.
		isAllowedTo('admin_forum');

		$this->errorLog = new Log(database());

		// The error log. View the list, view a file or backtrace?
		$activity = $this->_req->getQuery('activity', 'strval');

		if (isset($activity) && $activity === 'file')
		{
			// View the file with the error
			$this->action_viewfile();
		}
		elseif (isset($activity) && $activity === 'backtrace')
		{
			// View the error backtrace
		 	$this->action_backtrace();
		}
		else
		{
			// View error log
			$this->action_log();
		}
	}

	/**
	 * View a file specified in $_REQUEST['file'], with php highlighting on it
	 *
	 * What it does:
	 *  - File must be readable,
	 *  - Full file path must be base64 encoded,
	 *  - The line number is specified by $_REQUEST['line']...
	 *  - The function will try to get the 20 lines before and after the specified line.
	 */
	protected function action_viewfile(): void
	{
		global $context;

		$err = $this->_req->getQuery('err', 'intval');
		$error_details = $this->errorLog->getErrorLogData(
			0,
			'down',
			[
				'variable' => 'id_error',
				'value' => [
					'sql' => $err,
				],
			]
		)['errors'][$err]['file'];

		$data = iterator_to_array(
			theme()->getTemplates()->getHighlightedLinesFromFile(
				$error_details['file'],
				max($error_details['line'] - 16 - 9, 1),
				min($error_details['line'] + 21, count(file($error_details['file'])) + 1)
			)
		);

		// Mark the offending line.
		$data[$error_details['line']] = sprintf(
			'<div class="curline">%s</div>',
			$data[$error_details['line']]
		);

		$context['file_data'] = [
			'contents' => $data,
			'file' => strtr($error_details['file'], ['"' => '\\"']),
		];

		theme()->getTemplates()->load('Errors');
		theme()->getLayers()->removeAll();
		$context['sub_template'] = 'show_file';
	}

	/**
	 * View a debug backtrace
	 *
	 * What it does:
	 *  - Backtrace must exist,
	 *  - The error is specified by $_GET['err']...
	 */
	protected function action_backtrace(): void
	{
		global $context;

		Txt::load('Maintenance');
		$err = $this->_req->getQuery('err', 'intval');
		$error_details = $this->errorLog->getErrorLogData(
			0,
			'down',
			[
				'variable' => 'id_error',
				'value' => [
					'sql' => $err,
				],
			]
		)['errors'][$err]['backtrace'];

		$context['backtrace_data'] = [
			'contents' => json_decode($error_details['backtrace']),
			'file' => strtr($error_details['file'], ['"' => '\\"']),
			'line' => $error_details['line'],
			'message' => $error_details['message'] ?? ''
		];

		theme()->getTemplates()->load('Errors');
		theme()->getLayers()->removeAll();
		$context['sub_template'] = 'show_backtrace';
	}

	/**
	 * View the forum's error log.
	 *
	 * What it does:
	 *
	 * - This method sets all the context up to show the error log for maintenance.
	 * - It requires the admin_forum permission.
	 * - It is accessed from ?action=admin;area=logs;sa=errorlog.
	 *
	 * @uses the Errors template and error_log sub template.
	 */
	protected function action_log(): void
	{
		global $txt, $context, $modSettings;

		// Templates, etc...
		Txt::load('Maintenance');
		theme()->getTemplates()->load('Errors');

		// Set up any filters chosen
		$filter = $this->_setupFiltering();

		// Deleting, are we?
		$type = isset($this->_req->post->delall) ? 'delall' : (isset($this->_req->post->delete) ? 'delete' : false);
		if ($type !== false)
		{
			// Make sure the session exists and is correct; otherwise, might be a hacker.
			checkSession();
			validateToken('admin-el');

			$error_list = $this->_req->getPost('delete');
			$this->errorLog->deleteErrors($type, $filter, $error_list);

			// Go back to where we were.
			if ($type === 'delete')
			{
				redirectexit('action=admin;area=logs;sa=errorlog' . (isset($this->_req->query->desc) ? ';desc' : '') . ';start=' . $this->_req->query->start . (empty($filter) ? '' : ';filter=' . $this->_req->query->filter . ';value=' . $this->_req->query->value));
			}

			redirectexit('action=admin;area=logs;sa=errorlog' . (isset($this->_req->query->desc) ? ';desc' : ''));
		}

		$num_errors = $this->errorLog->numErrors($filter);
		$members = [];

		// If this filter is empty...
		if ($num_errors === 0 && !empty($filter))
		{
			redirectexit('action=admin;area=logs;sa=errorlog' . (isset($this->_req->query->desc) ? ';desc' : ''));
		}

		// Clean up start.
		$start = max($this->_req->getQuery('start', 'intval', 0), 0);

		// Do we want to reverse the error listing?
		$context['sort_direction'] = isset($this->_req->query->desc) ? 'down' : 'up';

		// How about filter it?
		$page_filter = isset($filter['href']) ? ';filter=' . $filter['href']['filter'] . ';value=' . $filter['href']['value'] : '';

		// Set the page listing up.
		$context['page_index'] = constructPageIndex('{scripturl}?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] === 'down' ? ';desc' : '') . $page_filter, $start, $num_errors, $modSettings['defaultMaxMessages']);
		$context['start'] = $start;
		$context['$page_filter'] = $page_filter;
		$context['errors'] = [];

		$logdata = $this->errorLog->getErrorLogData($start, $context['sort_direction'], $filter);
		if (!empty($logdata))
		{
			$context['errors'] = $logdata['errors'];
			$members = $logdata['members'];
		}

		// Load the member data.
		$this->_loadMemData($members);

		// Filtering anything?
		$this->_applyFilter($filter);

		// What type of errors do we have and how many do we have?
		$context['error_types'] = $this->errorLog->fetchErrorsByType($filter, $context['sort_direction']);
		$tmp = array_keys($context['error_types']);
		$sum = (int) end($tmp);

		$context['error_types']['all'] = [
			'label' => $txt['errortype_all'],
			'description' => $txt['errortype_all_desc'] ?? '',
			'url' => getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'errorlog', $context['sort_direction'] === 'down' ? 'desc' : '']),
			'is_selected' => empty($filter),
		];

		// Update the all errors tab with the total number of errors
		$context['error_types']['all']['label'] .= ' (' . $sum . ')';

		// Finally, work out what the last tab is!
		if (isset($context['error_types'][$sum]))
		{
			$context['error_types'][$sum]['is_last'] = true;
		}
		else
		{
			$context['error_types']['all']['is_last'] = true;
		}

		// And this is pretty basic ;).
		$context['page_title'] = $txt['errlog'];
		$context['has_filter'] = !empty($filter);
		$context['sub_template'] = 'error_log';

		createToken('admin-el');
	}

	/**
	 * Set up any filtering the user may have selected
	 */
	private function _setupFiltering(): array
	{
		global $txt;

		// We'll escape some strings...
		$db = database();

		// You can filter by any of the following columns:
		$filters = [
			'id_member' => $txt['username'],
			'ip' => $txt['ip_address'],
			'session' => $txt['session'],
			'url' => $txt['error_url'],
			'message' => $txt['error_message'],
			'error_type' => $txt['error_type'],
			'file' => $txt['file'],
			'line' => $txt['line'],
		];

		// Set up the filtering...
		$filter = $this->_req->getQuery('filter', 'trim');
		$value = $this->_req->getQuery('value', 'trim');
		if (isset($value, $filters[$filter]))
		{
			return [
				'variable' => $filter,
				'value' => [
					'sql' => in_array($filter, ['message', 'url', 'file'])
						? base64_decode(strtr($value, [' ' => '+']))
						: $db->escape_wildcard_string($value),
				],
				'href' => ['filter' => $filter, 'value' => $value],
				'entity' => $filters[$filter]
			];
		}

		if (isset($filter, $value))
		{
			$this->_req->clearValue('filter', 'query');
			$this->_req->clearValue('value', 'query');
		}

		return [];
	}

	/**
	 * Load basic member information for log viewing
	 *
	 * @param int[] $members
	 */
	private function _loadMemData(array $members): void
	{
		global $context, $txt;

		// Load the member data.
		if (!empty($members))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$members = getBasicMemberData($members, ['add_guest' => true]);

			// Go through each error and tac the data on.
			foreach ($context['errors'] as $id => $dummy)
			{
				$memID = $context['errors'][$id]['member']['id'];
				$context['errors'][$id]['member']['username'] = $members[$memID]['member_name'];
				$context['errors'][$id]['member']['name'] = $members[$memID]['real_name'];
				$context['errors'][$id]['member']['href'] = empty($memID) ? '' : getUrl('profile', ['action' => 'profile', 'u' => $memID, 'name' => $members[$memID]['real_name']]);
				$context['errors'][$id]['member']['link'] = empty($memID) ? $txt['guest_title'] : '<a href="' . $context['errors'][$id]['member']['href'] . '">' . $context['errors'][$id]['member']['name'] . '</a>';
			}
		}
	}

	/**
	 * Applys the filter to the template
	 *
	 * @param array $filter
	 */
	private function _applyFilter(array $filter): void
	{
		global $context, $scripturl;

		if (isset($filter['variable']))
		{
			$context['filter'] = &$filter;

			// Set the filtering context.
			switch ($filter['variable'])
			{
				case 'id_member':
					$id = $filter['value']['sql'];
					MembersList::load($id, false, 'minimal');
					$name = MembersList::get($id)->real_name;
					$context['filter']['value']['html'] = '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $id, 'name' => $name]) . '">' . $name . '</a>';
					break;
				case 'url':
					$context['filter']['value']['html'] = "'" . strtr(htmlspecialchars((str_starts_with($filter['value']['sql'], '?') ? $scripturl : '') . $filter['value']['sql'], ENT_COMPAT, 'UTF-8'), ['\_' => '_']) . "'";
					break;
				case 'message':
					$context['filter']['value']['html'] = "'" . strtr(htmlspecialchars($filter['value']['sql'], ENT_COMPAT, 'UTF-8'), ["\n" => '<br />', '&lt;br /&gt;' => '<br />', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\']) . "'";
					$context['filter']['value']['html'] = preg_replace('~&amp;lt;span class=&amp;quot;remove&amp;quot;&amp;gt;(.+?)&amp;lt;/span&amp;gt;~', '$1', $context['filter']['value']['html']);
					break;
				case 'error_type':
					$context['filter']['value']['html'] = "'" . strtr(htmlspecialchars($filter['value']['sql'], ENT_COMPAT, 'UTF-8'), ["\n" => '<br />', '&lt;br /&gt;' => '<br />', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\']) . "'";
					break;
				default:
					$context['filter']['value']['html'] = &$filter['value']['sql'];
			}
		}
	}
}
