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
 * The main purpose of this file is to show a list of all errors that were
 * logged on the forum, and allow filtering and deleting them.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * ManageErrors controller, administration of error log.
 */
class ManageErrors_Controller
{
	/**
	 * View the forum's error log.
	 * This function sets all the context up to show the error log for maintenance.
	 * It requires the maintain_forum permission.
	 * It is accessed from ?action=admin;area=logs;sa=errorlog.
	 *
	 * @uses the Errors template and error_log sub template.
	 */
	public function action_log()
	{
		global $scripturl, $txt, $context, $modSettings, $user_profile, $filter;

		$db = database();

		require_once(SUBSDIR . '/Error.subs.php');

		// Viewing contents of a file?
		if (isset($_GET['file']))
			return $this->action_viewfile();

		// Check for the administrative permission to do this.
		isAllowedTo('admin_forum');

		// Templates, etc...
		loadLanguage('ManageMaintenance');
		loadTemplate('Errors');

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

		// Set up the filtering...
		if (isset($_GET['value'], $_GET['filter']) && isset($filters[$_GET['filter']]))
			$filter = array(
				'variable' => $_GET['filter'],
				'value' => array(
					'sql' => in_array($_GET['filter'], array('message', 'url', 'file')) ? base64_decode(strtr($_GET['value'], array(' ' => '+'))) : $db->db_escape_wildcard_string($_GET['value']),
				),
				'href' => ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'],
				'entity' => $filters[$_GET['filter']]
			);
		elseif (isset($_GET['filter']) || isset($_GET['value']))
			unset($_GET['filter'], $_GET['value']);

		// Deleting, are we?
		$type = isset($_POST['delall']) ? 'delall' : (isset($_POST['delete']) ? 'delete' : false);
		$error_list = isset($_POST['delete']) ? $_POST['delete'] : null;

		if ($type != false)
		{
			// Make sure the session exists and is correct; otherwise, might be a hacker.
			checkSession();
			validateToken('admin-el');

			deleteErrors($type, $filter, $error_list);

			// // Go back to where we were.
			if ($type == 'delete')
				redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : '') . ';start=' . $_GET['start'] . (isset($filter) ? ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'] : ''));// Go back to where we were.

			redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));

		}
		$num_errors = numErrors();

		// If this filter is empty...
		if ($num_errors == 0 && isset($filter))
			redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));

		// Clean up start.
		if (!isset($_GET['start']) || $_GET['start'] < 0)
			$_GET['start'] = 0;

		// Do we want to reverse error listing?
		$context['sort_direction'] = isset($_REQUEST['desc']) ? 'down' : 'up';

		// Set the page listing up.
		$context['page_index'] = constructPageIndex($scripturl . '?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'down' ? ';desc' : '') . (isset($filter) ? $filter['href'] : ''), $_GET['start'], $num_errors, $modSettings['defaultMaxMessages']);
		$context['start'] = $_GET['start'];
		$context['errors'] = array();

		$logdata = getErrorLogData($_GET['start'], $context['sort_direction'], $filter);
		if (!empty($logdata))
		{
			$context['errors'] = $logdata['errors'];
			$members = $logdata['members'];
		}

		// Load the member data.
		if (!empty($members))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$members = getBasicMemberData($members, array('add_guest'=> true));

			// Go through each error and tack the data on.
			foreach ($context['errors'] as $id => $dummy)
			{
				$memID = $context['errors'][$id]['member']['id'];
				$context['errors'][$id]['member']['username'] = $members[$memID]['member_name'];
				$context['errors'][$id]['member']['name'] = $members[$memID]['real_name'];
				$context['errors'][$id]['member']['href'] = empty($memID) ? '' : $scripturl . '?action=profile;u=' . $memID;
				$context['errors'][$id]['member']['link'] = empty($memID) ? $txt['guest_title'] : '<a href="' . $scripturl . '?action=profile;u=' . $memID . '">' . $context['errors'][$id]['member']['name'] . '</a>';
			}
		}

		// Filtering anything?
		if (isset($filter))
		{
			$context['filter'] = &$filter;

			// Set the filtering context.
			if ($filter['variable'] == 'id_member')
			{
				$id = $filter['value']['sql'];
				loadMemberData($id, false, 'minimal');
				$context['filter']['value']['html'] = '<a href="' . $scripturl . '?action=profile;u=' . $id . '">' . $user_profile[$id]['real_name'] . '</a>';
			}
			elseif ($filter['variable'] == 'url')
				$context['filter']['value']['html'] = '\'' . strtr(htmlspecialchars((substr($filter['value']['sql'], 0, 1) == '?' ? $scripturl : '') . $filter['value']['sql']), array('\_' => '_')) . '\'';
			elseif ($filter['variable'] == 'message')
			{
				$context['filter']['value']['html'] = '\'' . strtr(htmlspecialchars($filter['value']['sql']), array("\n" => '<br />', '&lt;br /&gt;' => '<br />', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\')) . '\'';
				$context['filter']['value']['html'] = preg_replace('~&amp;lt;span class=&amp;quot;remove&amp;quot;&amp;gt;(.+?)&amp;lt;/span&amp;gt;~', '$1', $context['filter']['value']['html']);
			}
			elseif ($filter['variable'] == 'error_type')
			{
				$context['filter']['value']['html'] = '\'' . strtr(htmlspecialchars($filter['value']['sql']), array("\n" => '<br />', '&lt;br /&gt;' => '<br />', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\')) . '\'';
			}
			else
				$context['filter']['value']['html'] = &$filter['value']['sql'];
		}

		$context['error_types'] = array();

		$sort = ($context['sort_direction'] == 'down') ? ';desc' : '';
		// What type of errors do we have and how many do we have?
		$context['error_types'] = fetchErrorsByType($filter, $sort);
		$sum = 0;
		foreach ($context['error_types'] as $key => $value)
			$sum += $key;

		$context['error_types']['all'] = array(
			'label' => $txt['errortype_all'],
			'description' => isset($txt['errortype_all_desc']) ? $txt['errortype_all_desc'] : '',
			'url' => $scripturl . '?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'down' ? ';desc' : ''),
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
		$context['has_filter'] = isset($filter);
		$context['sub_template'] = 'error_log';

		createToken('admin-el');
	}

	/**
	 * View a file specified in $_REQUEST['file'], with php highlighting on it
	 * Preconditions:
	 *  - file must be readable,
	 *  - full file path must be base64 encoded,
	 *  - user must have admin_forum permission.
	 * The line number number is specified by $_REQUEST['line']...
	 * The function will try to get the 20 lines before and after the specified line.
	 */
	public function action_viewfile()
	{
		global $context;

		// Check for the administrative permission to do this.
		isAllowedTo('admin_forum');

		// Decode the file and get the line
		$file = realpath(base64_decode($_REQUEST['file']));
		$line = isset($_REQUEST['line']) ? (int) $_REQUEST['line'] : 0;

		// Make sure things are normalized
		$real_board = realpath(BOARDDIR);
		$real_source = realpath(SOURCEDIR);
		$real_cache = realpath(CACHEDIR);

		// Make sure the file requested is one they are allowed to look at
		$excluded = array('settings.php', 'settings_bak.php');
		$basename = strtolower(basename($file));
		$ext = strrchr($basename, '.');
		if ($ext !== '.php' || (strpos($file, $real_board) === false && strpos($file, $real_source) === false) || strpos($file, $real_cache) !== false || in_array($basename, $excluded) || !is_readable($file))
			fatal_lang_error('error_bad_file', true, array(htmlspecialchars($file)));

		// get the min and max lines
		$min = $line - 20 <= 0 ? 1 : $line - 20;
		$max = $line + 21; // One additional line to make everything work out correctly

		if ($max <= 0 || $min >= $max)
			fatal_lang_error('error_bad_line');

		$file_data = explode('<br />', highlight_php_code(htmlspecialchars(implode('', file($file)))));

		// We don't want to slice off too many so lets make sure we stop at the last one
		$max = min($max, max(array_keys($file_data)));

		$file_data = array_slice($file_data, $min-1, $max - $min);

		$context['file_data'] = array(
			'contents' => $file_data,
			'min' => $min,
			'target' => $line,
			'file' => strtr($file, array('"' => '\\"')),
		);

		loadTemplate('Errors');
		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'show_file';
	}
}