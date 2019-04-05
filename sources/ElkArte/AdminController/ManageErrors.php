<?php

/**
 * The main purpose of this file is to show a list of all errors that were
 * logged on the forum, and allow filtering and deleting them.
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
 * ManageErrors controller, administration of error log.
 */
class ManageErrors extends \ElkArte\AbstractController
{
	/** @var \ElkArte\Errors\Log */
	private $errorLog;

	/**
	 * Calls the right handler.
	 * Requires admin_forum permission.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// Check for the administrative permission to do this.
		isAllowedTo('admin_forum');

		$this->errorLog = new \ElkArte\Errors\Log(database());

		// The error log. View the list or view a file?
		$activity = $this->_req->getQuery('activity', 'strval');

		// Some code redundancy... and we only take this!
		if (isset($activity) && $activity == 'file')
			// View the file with the error
			$this->action_viewfile();
		else
			// View error log
			$this->action_log();
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
	protected function action_log()
	{
		global $scripturl, $txt, $context, $modSettings;

		// Templates, etc...
		theme()->getTemplates()->loadLanguageFile('Maintenance');
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
			if ($type == 'delete')
				redirectexit('action=admin;area=logs;sa=errorlog' . (isset($this->_req->query->desc) ? ';desc' : '') . ';start=' . $this->_req->query->start . (!empty($filter) ? ';filter=' . $this->_req->query->filter . ';value=' . $this->_req->query->value : ''));

			redirectexit('action=admin;area=logs;sa=errorlog' . (isset($this->_req->query->desc) ? ';desc' : ''));
		}

		$num_errors = $this->errorLog->numErrors($filter);
		$members = array();

		// If this filter is empty...
		if ($num_errors == 0 && !empty($filter))
			redirectexit('action=admin;area=logs;sa=errorlog' . (isset($this->_req->query->desc) ? ';desc' : ''));

		// Clean up start.
		if (!isset($this->_req->query->start) || $this->_req->query->start < 0)
			$this->_req->query->start = 0;

		// Do we want to reverse error listing?
		$context['sort_direction'] = isset($this->_req->query->desc) ? 'down' : 'up';

		// Set the page listing up.
		$context['page_index'] = constructPageIndex($scripturl . '?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'down' ? ';desc' : '') . (isset($filter['href']) ? $filter['href'] : ''), $this->_req->query->start, $num_errors, $modSettings['defaultMaxMessages']);
		$context['start'] = $this->_req->query->start;
		$context['errors'] = array();

		$logdata = $this->errorLog->getErrorLogData($this->_req->query->start, $context['sort_direction'], $filter);
		if (!empty($logdata))
		{
			$context['errors'] = $logdata['errors'];
			$members = $logdata['members'];
		}

		// Load the member data.
		$this->_loadMemData($members);

		// Filtering anything?
		$this->_applyFilter($filter);

		$sort = ($context['sort_direction'] == 'down') ? ';desc' : '';

		// What type of errors do we have and how many do we have?
		$context['error_types'] = array();
		$context['error_types'] = $this->errorLog->fetchErrorsByType($filter, $sort);
		$tmp = array_keys($context['error_types']);
		$sum = (int) end($tmp);

		$context['error_types']['all'] = array(
			'label' => $txt['errortype_all'],
			'description' => isset($txt['errortype_all_desc']) ? $txt['errortype_all_desc'] : '',
			'url' => getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'errorlog', $context['sort_direction'] == 'down' ? 'desc' : '']),
			'is_selected' => empty($filter),
		);

		// Update the all errors tab with the total number of errors
		$context['error_types']['all']['label'] .= ' (' . $sum . ')';

		// Finally, work out what is the last tab!
		if (isset($context['error_types'][$sum]))
			$context['error_types'][$sum]['is_last'] = true;
		else
			$context['error_types']['all']['is_last'] = true;

		// And this is pretty basic ;).
		$context['page_title'] = $txt['errlog'];
		$context['has_filter'] = !empty($filter);
		$context['sub_template'] = 'error_log';

		createToken('admin-el');
	}

	/**
	 * Applys the filter to the template
	 *
	 * @param array $filter
	 */
	private function _applyFilter($filter)
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
					\ElkArte\MembersList::load($id, false, 'minimal');
					$name = \ElkArte\MembersList::get($id)->real_name;
					$context['filter']['value']['html'] = '<a href="' . getUrl('profile', ['action' => 'profile', 'u' => $id,  'name' => $name]) . '">' . $name . '</a>';
					break;
				case 'url':
					$context['filter']['value']['html'] = '\'' . strtr(htmlspecialchars((substr($filter['value']['sql'], 0, 1) == '?' ? $scripturl : '') . $filter['value']['sql'], ENT_COMPAT, 'UTF-8'), array('\_' => '_')) . '\'';
					break;
				case 'message':
					$context['filter']['value']['html'] = '\'' . strtr(htmlspecialchars($filter['value']['sql'], ENT_COMPAT, 'UTF-8'), array("\n" => '<br />', '&lt;br /&gt;' => '<br />', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\')) . '\'';
					$context['filter']['value']['html'] = preg_replace('~&amp;lt;span class=&amp;quot;remove&amp;quot;&amp;gt;(.+?)&amp;lt;/span&amp;gt;~', '$1', $context['filter']['value']['html']);
					break;
				case 'error_type':
					$context['filter']['value']['html'] = '\'' . strtr(htmlspecialchars($filter['value']['sql'], ENT_COMPAT, 'UTF-8'), array("\n" => '<br />', '&lt;br /&gt;' => '<br />', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\')) . '\'';
					break;
				default:
					$context['filter']['value']['html'] = &$filter['value']['sql'];
			}
		}
	}

	/**
	 * Load basic member information for log viewing
	 *
	 * @param int[] $members
	 */
	private function _loadMemData($members)
	{
		global $context, $txt;

		// Load the member data.
		if (!empty($members))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$members = getBasicMemberData($members, array('add_guest' => true));

			// Go through each error and tack the data on.
			foreach ($context['errors'] as $id => $dummy)
			{
				$memID = $context['errors'][$id]['member']['id'];
				$context['errors'][$id]['member']['username'] = $members[$memID]['member_name'];
				$context['errors'][$id]['member']['name'] = $members[$memID]['real_name'];
				$context['errors'][$id]['member']['href'] = empty($memID) ? '' : getUrl('profile', ['action' => 'profile', 'u' => $memID,  'name' => $members[$memID]['real_name']]);
				$context['errors'][$id]['member']['link'] = empty($memID) ? $txt['guest_title'] : '<a href="' . $context['errors'][$id]['member']['href'] . '">' . $context['errors'][$id]['member']['name'] . '</a>';
			}
		}
	}

	/**
	 * Setup any filtering the user may have selected
	 */
	private function _setupFiltering()
	{
		global $txt;

		// We'll escape some strings...
		$db = database();

		// You can filter by any of the following columns:
		$filters = array(
			'id_member' => $txt['username'],
			'ip' => $txt['ip_address'],
			'session' => $txt['session'],
			'url' => $txt['error_url'],
			'message' => $txt['error_message'],
			'error_type' => $txt['error_type'],
			'file' => $txt['file'],
			'line' => $txt['line'],
		);

		$filter = array();

		// Set up the filtering...
		if (isset($this->_req->query->value, $this->_req->query->filter) && isset($filters[$this->_req->query->filter]))
		{
			$filter = array(
				'variable' => $this->_req->query->filter,
				'value' => array(
					'sql' => in_array($this->_req->query->filter, array('message', 'url', 'file'))
						? base64_decode(strtr($this->_req->query->value, array(' ' => '+')))
						: $db->escape_wildcard_string($this->_req->query->value),
				),
				'href' => ';filter=' . $this->_req->query->filter . ';value=' . $this->_req->query->value,
				'entity' => $filters[$this->_req->query->filter]
			);
		}
		elseif (isset($this->_req->query->filter) || isset($this->_req->query->value))
			unset($this->_req->query->filter, $this->_req->query->value);

		return $filter;
	}

	/**
	 * View a file specified in $_REQUEST['file'], with php highlighting on it
	 *
	 * Preconditions:
	 *  - file must be readable,
	 *  - full file path must be base64 encoded,
	 *
	 * - The line number number is specified by $_REQUEST['line']...
	 * - The function will try to get the 20 lines before and after the specified line.
	 */
	protected function action_viewfile()
	{
		global $context;

		$error_details = $this->errorLog->getErrorLogData(
			0,
			'down',
			[
				'variable' => 'id_error',
				'value' => [
					'sql' => $this->_req->query->err,
				],
			]
		)['errors'][$this->_req->query->err]['file'];

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
}
