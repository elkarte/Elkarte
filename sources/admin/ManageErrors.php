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
		global $scripturl, $txt, $context, $modSettings, $user_profile, $filter, $smcFunc;

		require_once(SUBSDIR . '/ManageErrors.subs.php');

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
					'sql' => in_array($_GET['filter'], array('message', 'url', 'file')) ? base64_decode(strtr($_GET['value'], array(' ' => '+'))) : $smcFunc['db_escape_wildcard_string']($_GET['value']),
				),
				'href' => ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'],
				'entity' => $filters[$_GET['filter']]
			);
		elseif (isset($_GET['filter']) || isset($_GET['value']))
			unset($_GET['filter'], $_GET['value']);

		// Deleting, are we?
		$type = isset($_POST['delall']) ? 'delall' : (isset($_POST['delete']) ? 'delete' : false);
		if ($type != false)
		{
			// Make sure the session exists and is correct; otherwise, might be a hacker.
			checkSession();
			validateToken('admin-el');

			deleteErrors($type, $filter);
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

		// Find and sort out the errors.
		$request = $smcFunc['db_query']('', '
			SELECT id_error, id_member, ip, url, log_time, message, session, error_type, file, line
			FROM {db_prefix}log_errors' . (isset($filter) ? '
			WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : '') . '
			ORDER BY id_error ' . ($context['sort_direction'] == 'down' ? 'DESC' : '') . '
			LIMIT ' . $_GET['start'] . ', ' . $modSettings['defaultMaxMessages'],
			array(
				'filter' => isset($filter) ? $filter['value']['sql'] : '',
			)
		);
		$context['errors'] = array();
		$members = array();

		for ($i = 0; $row = $smcFunc['db_fetch_assoc']($request); $i ++)
		{
			$search_message = preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '%', $smcFunc['db_escape_wildcard_string']($row['message']));
			if ($search_message == $filter['value']['sql'])
				$search_message = $smcFunc['db_escape_wildcard_string']($row['message']);
			$show_message = strtr(strtr(preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '$1', $row['message']), array("\r" => '', '<br />' => "\n", '<' => '&lt;', '>' => '&gt;', '"' => '&quot;')), array("\n" => '<br />'));

			$context['errors'][$row['id_error']] = array(
				'alternate' => $i %2 == 0,
				'member' => array(
					'id' => $row['id_member'],
					'ip' => $row['ip'],
					'session' => $row['session']
				),
				'time' => timeformat($row['log_time']),
				'timestamp' => $row['log_time'],
				'url' => array(
					'html' => htmlspecialchars((substr($row['url'], 0, 1) == '?' ? $scripturl : '') . $row['url']),
					'href' => base64_encode($smcFunc['db_escape_wildcard_string']($row['url']))
				),
				'message' => array(
					'html' => $show_message,
					'href' => base64_encode($search_message)
				),
				'id' => $row['id_error'],
				'error_type' => array(
					'type' => $row['error_type'],
					'name' => isset($txt['errortype_'.$row['error_type']]) ? $txt['errortype_'.$row['error_type']] : $row['error_type'],
				),
				'file' => array(),
			);
			if (!empty($row['file']) && !empty($row['line']))
			{
				// Eval'd files rarely point to the right location and cause havoc for linking, so don't link them.
				$linkfile = strpos($row['file'], 'eval') === false || strpos($row['file'], '?') === false; // De Morgan's Law.  Want this true unless both are present.

				$context['errors'][$row['id_error']]['file'] = array(
					'file' => $row['file'],
					'line' => $row['line'],
					'href' => $scripturl . '?action=admin;area=logs;sa=errorlog;file=' . base64_encode($row['file']) . ';line=' . $row['line'],
					'link' => $linkfile ? '<a href="' . $scripturl . '?action=admin;area=logs;sa=errorlog;file=' . base64_encode($row['file']) . ';line=' . $row['line'] . '" onclick="return reqWin(this.href, 600, 480, false);">' . $row['file'] . '</a>' : $row['file'],
					'search' => base64_encode($row['file']),
				);
			}

			// Make a list of members to load later.
			$members[$row['id_member']] = $row['id_member'];
		}
		$smcFunc['db_free_result']($request);

		// Load the member data.
		if (!empty($members))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$members = getBasicMemberData($members);

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

		$context['error_types']['all'] = array(
			'label' => $txt['errortype_all'],
			'description' => isset($txt['errortype_all_desc']) ? $txt['errortype_all_desc'] : '',
			'url' => $scripturl . '?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'down' ? ';desc' : ''),
			'is_selected' => empty($filter),
		);

		$sum = 0;
		// What type of errors do we have and how many do we have?
		$request = $smcFunc['db_query']('', '
			SELECT error_type, COUNT(*) AS num_errors
			FROM {db_prefix}log_errors
			GROUP BY error_type
			ORDER BY error_type = {string:critical_type} DESC, error_type ASC',
			array(
				'critical_type' => 'critical',
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Total errors so far?
			$sum += $row['num_errors'];

			$context['error_types'][$sum] = array(
				'label' => (isset($txt['errortype_' . $row['error_type']]) ? $txt['errortype_' . $row['error_type']] : $row['error_type']) . ' (' . $row['num_errors'] . ')',
				'description' => isset($txt['errortype_' . $row['error_type'] . '_desc']) ? $txt['errortype_' . $row['error_type'] . '_desc'] : '',
				'url' => $scripturl . '?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'down' ? ';desc' : '') . ';filter=error_type;value=' . $row['error_type'],
				'is_selected' => isset($filter) && $filter['value']['sql'] == $smcFunc['db_escape_wildcard_string']($row['error_type']),
			);
		}
		$smcFunc['db_free_result']($request);

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
		global $context, $sc;

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
		$context['template_layers'] = array();
		$context['sub_template'] = 'show_file';
	}
}